<?php
$page_title = 'Commission Management';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');
$admin_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_account') {
        $account_type = in_array($_POST['account_type'] ?? '', ['bank', 'mobile_money'], true) ? $_POST['account_type'] : 'bank';
        $provider_name = trim($_POST['provider_name'] ?? '');
        $account_name = trim($_POST['account_name'] ?? '');
        $account_number = trim($_POST['account_number'] ?? '');
        $instructions = trim($_POST['instructions'] ?? '');
        if ($provider_name === '' || $account_name === '' || $account_number === '') {
            $error = 'Provider, registered account name, and account number are required.';
        } else {
            $account_id = (int)($_POST['account_id'] ?? 0);
            if ($account_id > 0) {
                $stmt = $conn->prepare('UPDATE admin_payment_accounts SET account_type = ?, provider_name = ?, account_name = ?, account_number = ?, instructions = ?, is_active = 1 WHERE id = ?');
                $stmt->bind_param('sssssi', $account_type, $provider_name, $account_name, $account_number, $instructions, $account_id);
            } else {
                $stmt = $conn->prepare('INSERT INTO admin_payment_accounts (account_type, provider_name, account_name, account_number, instructions) VALUES (?, ?, ?, ?, ?)');
                $stmt->bind_param('sssss', $account_type, $provider_name, $account_name, $account_number, $instructions);
            }
            $success = $stmt->execute() ? 'Admin payment account saved.' : 'Unable to save the admin payment account.';
            if (!$success) $error = 'Unable to save the admin payment account.';
            $stmt->close();
        }
    }

    if ($action === 'review_settlement') {
        $settlement_id = (int)($_POST['settlement_id'] ?? 0);
        $decision = $_POST['decision'] ?? '';
        $verified_amount = (float)($_POST['admin_verified_amount'] ?? 0);
        $admin_message = trim($_POST['admin_message'] ?? '');
        $stmt = $conn->prepare("SELECT cs.*, u.first_name, u.last_name FROM commission_settlements cs JOIN users u ON u.id = cs.seller_id WHERE cs.id = ? AND cs.status = 'submitted'");
        $stmt->bind_param('i', $settlement_id);
        $stmt->execute();
        $settlement = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$settlement) {
            $error = 'Settlement is no longer awaiting review.';
        } elseif (!in_array($decision, ['confirm', 'reject'], true)) {
            $error = 'Choose confirm or reject.';
        } elseif ($decision === 'confirm' && ($verified_amount <= 0 || $verified_amount > (float)$settlement['amount_requested'])) {
            $error = 'The verified amount must be greater than zero and no more than the submitted amount.';
        } else {
            $status = $decision === 'confirm' ? 'confirmed' : 'rejected';
            $verified_value = $decision === 'confirm' ? $verified_amount : null;
            $stmt = $conn->prepare('UPDATE commission_settlements SET status = ?, admin_verified_amount = ?, admin_message = ?, reviewed_at = NOW(), reviewed_by = ? WHERE id = ? AND status = \'submitted\'');
            $stmt->bind_param('sdsii', $status, $verified_value, $admin_message, $admin_id, $settlement_id);
            if ($stmt->execute()) {
                $message = $decision === 'confirm'
                    ? 'Your commission payment of ' . formatCurrency($verified_amount) . ' was confirmed by admin.'
                    : 'Your commission payment proof was rejected. ' . ($admin_message ?: 'Please review and submit again.');
                createNotification((int)$settlement['seller_id'], 'commission_settlement', 'Commission payment ' . ucfirst($status), $message, SITE_URL . '/seller/commissions.php');
                $success = 'Settlement review saved and the seller was notified.';
            } else {
                $error = 'Unable to save the settlement review.';
            }
            $stmt->close();
        }
    }
}

$account = $conn->query('SELECT * FROM admin_payment_accounts ORDER BY is_active DESC, id LIMIT 1')->fetch_assoc();
$summary = $conn->query("SELECT
    COALESCE((SELECT SUM(sc.commission_amount) FROM seller_commissions sc WHERE sc.status != 'cancelled' AND EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed')), 0) AS total_due,
    COALESCE((SELECT SUM(admin_verified_amount) FROM commission_settlements WHERE status = 'confirmed'), 0) AS total_paid,
    COALESCE((SELECT SUM(amount_requested) FROM commission_settlements WHERE status = 'submitted'), 0) AS awaiting_review")->fetch_assoc();

$result = $conn->query("SELECT cs.*, u.first_name, u.last_name, u.email FROM commission_settlements cs JOIN users u ON u.id = cs.seller_id ORDER BY FIELD(cs.status, 'submitted', 'rejected', 'confirmed'), cs.submitted_at DESC");
$settlements = $result->fetch_all(MYSQLI_ASSOC);
$settlements_by_seller = [];
foreach ($settlements as $settlement) {
    $seller_key = (int)$settlement['seller_id'];
    if (!isset($settlements_by_seller[$seller_key])) {
        $settlements_by_seller[$seller_key] = [
            'name' => $settlement['first_name'] . ' ' . $settlement['last_name'],
            'email' => $settlement['email'],
            'requests' => 0,
            'requested' => 0,
            'confirmed' => 0,
            'pending' => 0,
            'rows' => []
        ];
    }
    $settlements_by_seller[$seller_key]['requests']++;
    $settlements_by_seller[$seller_key]['requested'] += (float)$settlement['amount_requested'];
    if ($settlement['status'] === 'confirmed') {
        $settlements_by_seller[$seller_key]['confirmed'] += (float)$settlement['admin_verified_amount'];
    } elseif ($settlement['status'] === 'submitted') {
        $settlements_by_seller[$seller_key]['pending'] += (float)$settlement['amount_requested'];
    }
    $settlements_by_seller[$seller_key]['rows'][] = $settlement;
}

$seller_commissions = $conn->query("SELECT u.id, u.first_name, u.last_name, u.email,
                                           COALESCE(SUM(CASE WHEN EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed') THEN sc.commission_amount ELSE 0 END), 0) AS total_due,
                                           COALESCE((SELECT SUM(cs.admin_verified_amount) FROM commission_settlements cs WHERE cs.seller_id = u.id AND cs.status = 'confirmed'), 0) AS total_paid,
                                           COALESCE((SELECT SUM(cs.amount_requested) FROM commission_settlements cs WHERE cs.seller_id = u.id AND cs.status = 'submitted'), 0) AS awaiting_review
                                    FROM users u
                                    LEFT JOIN seller_commissions sc ON sc.seller_id = u.id AND sc.status != 'cancelled'
                                    WHERE u.role = 'seller'
                                    GROUP BY u.id, u.first_name, u.last_name, u.email
                                    ORDER BY total_due DESC, u.first_name, u.last_name")->fetch_all(MYSQLI_ASSOC);

$result = $conn->query("SELECT o.order_number, o.total_amount, o.commission, o.created_at,
                               b.first_name AS buyer_first, b.last_name AS buyer_last,
                               s.first_name AS seller_first, s.last_name AS seller_last,
                               pp.file_path, pp.status AS payment_status, pp.reviewed_at
                        FROM orders o
                        JOIN users b ON b.id = o.buyer_id
                        JOIN users s ON s.id = o.seller_id
                                                LEFT JOIN payment_proofs pp ON pp.id = (SELECT MAX(pp2.id) FROM payment_proofs pp2 WHERE pp2.order_id = o.id AND pp2.status = 'confirmed')
                                                WHERE o.status != 'cancelled'
                                                    AND EXISTS (SELECT 1 FROM payment_proofs confirmed_pp WHERE confirmed_pp.order_id = o.id AND confirmed_pp.status = 'confirmed')
                        ORDER BY o.created_at DESC LIMIT 100");
$sales = $result->fetch_all(MYSQLI_ASSOC);

include '../includes/header.php';
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Commission Management</h2>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-4"><div class="stat-card"><h4><?php echo formatCurrency($summary['total_due']); ?></h4><p>Total commission due</p></div></div>
        <div class="col-md-4"><div class="stat-card"><h4><?php echo formatCurrency($summary['total_paid']); ?></h4><p>Total confirmed paid</p></div></div>
        <div class="col-md-4"><div class="stat-card"><h4><?php echo formatCurrency($summary['awaiting_review']); ?></h4><p>Awaiting review</p></div></div>
    </div>

    <div class="dashboard-card mb-4">
        <h4>Admin receiving account</h4>
        <p class="text-muted">Sellers use this account to pay their 10% commission.</p>
        <form method="POST"><input type="hidden" name="action" value="save_account"><input type="hidden" name="account_id" value="<?php echo (int)($account['id'] ?? 0); ?>">
            <div class="row">
                <div class="col-md-2 mb-3"><label class="form-label">Type</label><select name="account_type" class="form-select"><option value="bank" <?php echo (($account['account_type'] ?? '') === 'bank') ? 'selected' : ''; ?>>Bank</option><option value="mobile_money" <?php echo (($account['account_type'] ?? '') === 'mobile_money') ? 'selected' : ''; ?>>Mobile Money</option></select></div>
                <div class="col-md-2 mb-3"><label class="form-label">Provider</label><input name="provider_name" class="form-control" required value="<?php echo htmlspecialchars($account['provider_name'] ?? ''); ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Registered name</label><input name="account_name" class="form-control" required value="<?php echo htmlspecialchars($account['account_name'] ?? ''); ?>"></div>
                <div class="col-md-3 mb-3"><label class="form-label">Account / Phone</label><input name="account_number" class="form-control" required value="<?php echo htmlspecialchars($account['account_number'] ?? ''); ?>"></div>
                <div class="col-md-2 mb-3 d-flex align-items-end"><button class="btn btn-primary w-100">Save account</button></div>
            </div>
            <label class="form-label">Instructions</label><textarea name="instructions" class="form-control" rows="2"><?php echo htmlspecialchars($account['instructions'] ?? ''); ?></textarea>
        </form>
    </div>

    <div class="dashboard-card">
        <h4>Commission by seller</h4>
        <p class="text-muted">Each seller has one row showing the complete commission position. Open the seller's details to review payment proofs and messages.</p>
        <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Seller</th><th>Total commission due</th><th>Confirmed paid</th><th>Awaiting review</th><th>Remaining</th><th>Requests</th></tr></thead><tbody>
        <?php foreach ($seller_commissions as $seller): ?>
            <?php $remaining = max(0, (float)$seller['total_due'] - (float)$seller['total_paid'] - (float)$seller['awaiting_review']); ?>
            <tr>
                <td><strong><?php echo htmlspecialchars($seller['first_name'] . ' ' . $seller['last_name']); ?></strong><br><small><?php echo htmlspecialchars($seller['email']); ?></small></td>
                <td><?php echo formatCurrency($seller['total_due']); ?></td>
                <td class="text-success"><?php echo formatCurrency($seller['total_paid']); ?></td>
                <td class="text-warning"><?php echo formatCurrency($seller['awaiting_review']); ?></td>
                <td><strong><?php echo formatCurrency($remaining); ?></strong></td>
                <td><?php echo count($settlements_by_seller[(int)$seller['id']]['rows'] ?? []); ?></td>
            </tr>
            <?php if (!empty($settlements_by_seller[(int)$seller['id']]['rows'])): ?>
                <tr><td colspan="6" class="p-0">
                    <details class="p-3">
                        <summary class="fw-bold">View <?php echo count($settlements_by_seller[(int)$seller['id']]['rows']); ?> settlement request(s) for this seller</summary>
                        <div class="table-responsive mt-3"><table class="table table-sm align-middle"><thead><tr><th>Submitted</th><th>Requested</th><th>Status</th><th>Seller message</th><th>Proof</th><th>Admin review</th></tr></thead><tbody>
                        <?php foreach ($settlements_by_seller[(int)$seller['id']]['rows'] as $row): ?>
                            <tr><td><?php echo date('M j, Y g:i A', strtotime($row['submitted_at'])); ?></td><td><?php echo formatCurrency($row['amount_requested']); ?></td><td><span class="badge <?php echo $row['status'] === 'confirmed' ? 'bg-success' : ($row['status'] === 'rejected' ? 'bg-danger' : 'bg-warning'); ?>"><?php echo ucfirst($row['status']); ?></span></td><td><?php echo $row['seller_message'] ? nl2br(htmlspecialchars($row['seller_message'])) : '<span class="text-muted">No message</span>'; ?></td><td><a href="<?php echo SITE_URL; ?>/uploads/commission_settlements/<?php echo rawurlencode(basename($row['proof_path'])); ?>" target="_blank">View proof</a></td><td><?php if ($row['status'] === 'submitted'): ?><form method="POST" class="border p-2"><input type="hidden" name="action" value="review_settlement"><input type="hidden" name="settlement_id" value="<?php echo (int)$row['id']; ?>"><input type="number" name="admin_verified_amount" class="form-control form-control-sm mb-2" min="0.01" max="<?php echo htmlspecialchars($row['amount_requested']); ?>" step="0.01" placeholder="Verified amount" required><textarea name="admin_message" class="form-control form-control-sm mb-2" rows="2" placeholder="Message to seller"></textarea><button name="decision" value="confirm" class="btn btn-sm btn-success">Confirm</button> <button name="decision" value="reject" class="btn btn-sm btn-danger">Reject</button></form><?php else: ?><?php echo $row['admin_verified_amount'] !== null ? formatCurrency($row['admin_verified_amount']) : '-'; ?><br><small><?php echo htmlspecialchars($row['admin_message'] ?? ''); ?></small><?php endif; ?></td></tr>
                        <?php endforeach; ?></tbody></table></div>
                    </details>
                </td></tr>
            <?php endif; ?>
        <?php endforeach; ?>
        <?php if (!$seller_commissions): ?><tr><td colspan="6">No seller commission records yet.</td></tr><?php endif; ?></tbody></table></div>
    </div>

    <div class="dashboard-card mt-4">
        <h4>Confirmed buyer payments and commission register</h4>
        <p class="text-muted">Only payments confirmed by the seller are shown here. These are the orders whose 10% commission is payable.</p>
        <div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Buyer</th><th>Seller</th><th>Buyer paid</th><th>Commission</th><th>Confirmed proof</th><th>Confirmed date</th></tr></thead><tbody>
        <?php foreach ($sales as $sale): ?><tr><td><?php echo htmlspecialchars($sale['order_number']); ?></td><td><?php echo htmlspecialchars($sale['buyer_first'] . ' ' . $sale['buyer_last']); ?></td><td><?php echo htmlspecialchars($sale['seller_first'] . ' ' . $sale['seller_last']); ?></td><td><?php echo formatCurrency($sale['total_amount']); ?></td><td><?php echo formatCurrency($sale['commission']); ?></td><td><?php if ($sale['file_path']): ?><a href="<?php echo SITE_URL; ?>/uploads/payment_proofs/<?php echo rawurlencode(basename($sale['file_path'])); ?>" target="_blank">View proof</a><br><span class="badge bg-success">Confirmed by seller</span><?php else: ?>Proof unavailable<?php endif; ?></td><td><?php echo !empty($sale['reviewed_at']) ? date('M j, Y g:i A', strtotime($sale['reviewed_at'])) : '-'; ?></td></tr><?php endforeach; ?>
        <?php if (!$sales): ?><tr><td colspan="7">No sales recorded yet.</td></tr><?php endif; ?></tbody></table></div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
