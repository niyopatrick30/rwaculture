<?php
$page_title = 'Commission Settlement';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');
$seller_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

$account = $conn->query("SELECT * FROM admin_payment_accounts WHERE is_active = 1 ORDER BY id LIMIT 1")->fetch_assoc();

function sellerCommissionTotals($seller_id) {
    global $conn;
    $stmt = $conn->prepare("SELECT
        COALESCE((SELECT SUM(sc.commission_amount) FROM seller_commissions sc WHERE sc.seller_id = ? AND sc.status != 'cancelled' AND EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed')), 0) AS total_due,
        COALESCE((SELECT SUM(admin_verified_amount) FROM commission_settlements WHERE seller_id = ? AND status = 'confirmed'), 0) AS total_paid,
        COALESCE((SELECT SUM(amount_requested) FROM commission_settlements WHERE seller_id = ? AND status = 'submitted'), 0) AS submitted_amount");
    $stmt->bind_param('iii', $seller_id, $seller_id, $seller_id);
    $stmt->execute();
    $totals = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    $totals['balance'] = max(0, (float)$totals['total_due'] - (float)$totals['total_paid'] - (float)$totals['submitted_amount']);
    return $totals;
}

$totals = sellerCommissionTotals($seller_id);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (float)($_POST['amount_requested'] ?? 0);
    $seller_message = trim($_POST['seller_message'] ?? '');
    $file = $_FILES['payment_proof'] ?? null;

    if ($amount <= 0 || $amount > $totals['balance']) {
        $error = 'Enter an amount up to the available commission balance of ' . formatCurrency($totals['balance']) . '.';
    } elseif (!$file || $file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Please upload the payment proof sent to the admin.';
    } else {
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (!in_array($extension, $allowed, true) || $file['size'] > MAX_FILE_SIZE) {
            $error = 'Use a JPG, PNG, WEBP, or PDF file up to 10 MB.';
        } else {
            $directory = __DIR__ . '/../uploads/commission_settlements/';
            if (!is_dir($directory)) mkdir($directory, 0777, true);
            $filename = uniqid('commission_', true) . '.' . $extension;
            if (!move_uploaded_file($file['tmp_name'], $directory . $filename)) {
                $error = 'The payment proof could not be saved.';
            } else {
                $stmt = $conn->prepare("INSERT INTO commission_settlements (seller_id, amount_requested, proof_path, proof_type, proof_size, seller_message) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param('idssis', $seller_id, $amount, $filename, $file['type'], $file['size'], $seller_message);
                if ($stmt->execute()) {
                    $settlement_id = $stmt->insert_id;
                    notifyAllAdmins('commission_settlement', 'Commission payment submitted', 'A seller submitted ' . formatCurrency($amount) . ' for commission settlement.', SITE_URL . '/admin/commissions.php?id=' . $settlement_id);
                    $success = 'Your payment proof was submitted. The admin will verify the amount shown on it.';
                    $totals = sellerCommissionTotals($seller_id);
                } else {
                    $error = 'Unable to submit the commission payment.';
                }
                $stmt->close();
            }
        }
    }
}

$stmt = $conn->prepare("SELECT cs.*, u.first_name, u.last_name FROM commission_settlements cs LEFT JOIN users u ON u.id = cs.reviewed_by WHERE cs.seller_id = ? ORDER BY cs.submitted_at DESC");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$settlements = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stmt = $conn->prepare("SELECT sc.*, o.order_number,
                               EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed') AS payment_confirmed
                        FROM seller_commissions sc JOIN orders o ON o.id = sc.order_id
                                                WHERE sc.seller_id = ?
                                                    AND sc.status != 'cancelled'
                                                    AND EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed')
                                                ORDER BY sc.created_at DESC LIMIT 100");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$commission_rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../includes/header.php';
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Commission Settlement</h2>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>

    <div class="row mb-4">
        <div class="col-md-3"><div class="stat-card"><h4><?php echo formatCurrency($totals['total_due']); ?></h4><p>Total commission due</p></div></div>
        <div class="col-md-3"><div class="stat-card"><h4><?php echo formatCurrency($totals['total_paid']); ?></h4><p>Confirmed paid</p></div></div>
        <div class="col-md-3"><div class="stat-card"><h4><?php echo formatCurrency($totals['submitted_amount']); ?></h4><p>Awaiting review</p></div></div>
        <div class="col-md-3"><div class="stat-card"><h4><?php echo formatCurrency($totals['balance']); ?></h4><p>Available to pay</p></div></div>
    </div>

    <div class="row">
        <div class="col-md-5 mb-4">
            <div class="dashboard-card">
                <h4>Admin payment account</h4>
                <?php if ($account): ?>
                    <p><strong>Provider:</strong> <?php echo htmlspecialchars($account['provider_name']); ?></p>
                    <p><strong>Registered name:</strong> <?php echo htmlspecialchars($account['account_name']); ?></p>
                    <p><strong>Account / Phone:</strong> <?php echo htmlspecialchars($account['account_number']); ?></p>
                    <?php if ($account['instructions']): ?><p><?php echo nl2br(htmlspecialchars($account['instructions'])); ?></p><?php endif; ?>
                <?php else: ?><div class="alert alert-warning">The admin has not configured a payment account yet.</div><?php endif; ?>
                <a href="bank-account.php" class="btn btn-outline-secondary">Manage my buyer payment account</a>
            </div>
            <div class="dashboard-card">
                <h4>Submit commission payment</h4>
                <p class="text-muted">Pay the available balance to the admin account, then upload proof. The admin must enter the amount they verify.</p>
                <form method="POST" enctype="multipart/form-data">
                    <label class="form-label">Amount paid</label>
                    <input type="number" name="amount_requested" class="form-control mb-3" min="1" max="<?php echo htmlspecialchars($totals['balance']); ?>" step="0.01" required>
                    <label class="form-label">Message to admin (optional)</label>
                    <textarea name="seller_message" class="form-control mb-3" rows="3"></textarea>
                    <label class="form-label">Payment proof</label>
                    <input type="file" name="payment_proof" class="form-control mb-3" accept=".jpg,.jpeg,.png,.webp,.pdf" required>
                    <button class="btn btn-primary" <?php echo (!$account || $totals['balance'] <= 0) ? 'disabled' : ''; ?>>Submit payment proof</button>
                </form>
            </div>
        </div>
        <div class="col-md-7">
            <div class="dashboard-card mb-4">
                <h4>Settlement history</h4>
                <div class="table-responsive"><table class="table"><thead><tr><th>Date</th><th>Requested</th><th>Verified</th><th>Status</th><th>Admin message</th></tr></thead><tbody>
                <?php foreach ($settlements as $row): ?><tr><td><?php echo date('M j, Y', strtotime($row['submitted_at'])); ?></td><td><?php echo formatCurrency($row['amount_requested']); ?></td><td><?php echo $row['admin_verified_amount'] !== null ? formatCurrency($row['admin_verified_amount']) : '-'; ?></td><td><span class="badge <?php echo $row['status'] === 'confirmed' ? 'bg-success' : ($row['status'] === 'rejected' ? 'bg-danger' : 'bg-warning'); ?>"><?php echo ucfirst($row['status']); ?></span></td><td><?php echo htmlspecialchars($row['admin_message'] ?? ''); ?></td></tr><?php endforeach; ?>
                <?php if (!$settlements): ?><tr><td colspan="5">No settlement submissions yet.</td></tr><?php endif; ?></tbody></table></div>
            </div>
            <div class="dashboard-card">
                <h4>Commission by confirmed order</h4>
                <p class="text-muted">Only orders with buyer payment confirmed by you are included.</p>
                <div class="table-responsive"><table class="table"><thead><tr><th>Order</th><th>Rate</th><th>Commission</th><th>Buyer payment</th><th>Date</th></tr></thead><tbody>
                <?php foreach ($commission_rows as $row): ?><tr><td><?php echo htmlspecialchars($row['order_number']); ?></td><td><?php echo htmlspecialchars($row['commission_rate']); ?>%</td><td><?php echo formatCurrency($row['commission_amount']); ?></td><td><span class="badge bg-success">Confirmed by seller</span></td><td><?php echo date('M j, Y', strtotime($row['created_at'])); ?></td></tr><?php endforeach; ?>
                <?php if (!$commission_rows): ?><tr><td colspan="5">No seller-confirmed commissions recorded yet.</td></tr><?php endif; ?></tbody></table></div>
            </div>
        </div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
