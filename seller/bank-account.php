<?php
$page_title = 'Seller Payment Account';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');
$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

$stmt = $conn->prepare('SELECT * FROM seller_bank_accounts WHERE seller_id = ?');
$stmt->bind_param('i', $user_id);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc() ?: [];
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $bank_name = trim($_POST['bank_name'] ?? '');
    $account_name = trim($_POST['account_name'] ?? '');
    $account_number = trim($_POST['account_number'] ?? '');
    $branch_name = trim($_POST['branch_name'] ?? '');
    $payment_instructions = trim($_POST['payment_instructions'] ?? '');

    if ($bank_name === '' || $account_name === '' || $account_number === '') {
        $error = 'Bank name, account holder name, and account number are required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO seller_bank_accounts (seller_id, bank_name, account_name, account_number, branch_name, payment_instructions)
                                VALUES (?, ?, ?, ?, ?, ?)
                                ON DUPLICATE KEY UPDATE bank_name = VALUES(bank_name), account_name = VALUES(account_name), account_number = VALUES(account_number), branch_name = VALUES(branch_name), payment_instructions = VALUES(payment_instructions)");
        $stmt->bind_param('isssss', $user_id, $bank_name, $account_name, $account_number, $branch_name, $payment_instructions);
        if ($stmt->execute()) {
            $success = 'Your payment account was saved. Buyers will see it during payment.';
            $account = compact('bank_name', 'account_name', 'account_number', 'branch_name', 'payment_instructions');
        } else {
            $error = 'Unable to save your payment account.';
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>
<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Buyer Payment Account</h2>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>
    <p class="text-muted">Buyers pay you directly. Keep these details accurate; they are shown on your order payment page.</p>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div><?php endif; ?>
    <div class="dashboard-card">
        <form method="POST">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Bank or Mobile Money Provider</label>
                    <input name="bank_name" class="form-control" required value="<?php echo htmlspecialchars($account['bank_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Registered Account Holder Name</label>
                    <input name="account_name" class="form-control" required value="<?php echo htmlspecialchars($account['account_name'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Account or Phone Number</label>
                    <input name="account_number" class="form-control" required value="<?php echo htmlspecialchars($account['account_number'] ?? ''); ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Branch (Optional)</label>
                    <input name="branch_name" class="form-control" value="<?php echo htmlspecialchars($account['branch_name'] ?? ''); ?>">
                </div>
            </div>
            <div class="mb-3">
                <label class="form-label">Instructions for Buyers (Optional)</label>
                <textarea name="payment_instructions" class="form-control" rows="3" placeholder="Example: Use the order number as the payment reference."><?php echo htmlspecialchars($account['payment_instructions'] ?? ''); ?></textarea>
            </div>
            <button class="btn btn-primary">Save Payment Account</button>
        </form>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
