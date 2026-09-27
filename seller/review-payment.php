<?php
$page_title = 'Payment Review';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

requireRole('seller');

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT o.*, u.first_name, u.last_name, pp.file_path, pp.id AS proof_id
    FROM orders o
    JOIN users u ON o.buyer_id = u.id
    JOIN payment_proofs pp ON pp.order_id = o.id
    WHERE o.id = ? AND o.seller_id = ?
    ORDER BY pp.created_at DESC
    LIMIT 1
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$data) {
    http_response_code(404);
    die('Payment proof not found.');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $seller_comment = trim($_POST['seller_comment'] ?? '');
    $delivery_date = $_POST['estimated_delivery_date'] ?? '';
    $delivery_time = $_POST['estimated_delivery_time'] ?? '';

    if (!in_array($action, ['confirm', 'reject'], true)) {
        $error = 'Please choose a payment decision.';
    } elseif ($seller_comment === '') {
        $error = $action === 'reject'
            ? 'Please state why the payment was rejected.'
            : 'Please add a message for the buyer.';
    } elseif ($action === 'confirm' && (!$delivery_date || !$delivery_time)) {
        $error = 'Please provide the expected delivery date and time.';
    } else {
        $status = $action === 'confirm' ? 'confirmed' : 'rejected';
        $order_status = $action === 'confirm' ? 'payment_confirmed' : 'cancelled';
        $message = $action === 'confirm'
            ? 'Your payment has been confirmed: ' . $seller_comment
            : 'Your payment was rejected: ' . $seller_comment;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE payment_proofs SET status = ?, seller_comment = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->bind_param("ssi", $status, $seller_comment, $data['proof_id']);
            $stmt->execute();
            $stmt->close();

            if ($action === 'confirm') {
                $stmt = $conn->prepare("UPDATE orders SET status = ?, estimated_delivery_date = ?, estimated_delivery_time = ? WHERE id = ? AND seller_id = ?");
                $stmt->bind_param("sssii", $order_status, $delivery_date, $delivery_time, $order_id, $_SESSION['user_id']);
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE id = ? AND seller_id = ?");
                $stmt->bind_param("sii", $order_status, $order_id, $_SESSION['user_id']);
            }
            $stmt->execute();
            $stmt->close();

            $title = 'Payment Review';
            $link = SITE_URL . '/buyer/order-details.php?id=' . $order_id;
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, 'payment', ?, ?, ?)");
            $stmt->bind_param("isss", $data['buyer_id'], $title, $message, $link);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            $success = 'Payment decision saved and the buyer has been notified.';
        } catch (Throwable $exception) {
            $conn->rollback();
            $error = 'Unable to save the payment decision.';
        }
    }
}

include __DIR__ . '/../includes/header.php';
?>

<div class="container my-5">
    <h2>Payment Review</h2>

    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <p><strong>Buyer:</strong> <?php echo htmlspecialchars($data['first_name'] . ' ' . $data['last_name']); ?></p>
    <p>
        <a href="<?php echo SITE_URL; ?>/uploads/payment_proofs/<?php echo rawurlencode(basename($data['file_path'])); ?>" target="_blank" rel="noopener">
            View Payment Proof
        </a>
    </p>

    <form method="POST">
        <div class="mb-3">
            <label for="seller_comment" class="form-label">Message for buyer / rejection reason</label>
            <textarea id="seller_comment" name="seller_comment" class="form-control" rows="4" required></textarea>
        </div>
        <div class="row mb-3">
            <div class="col-md-6">
                <label for="estimated_delivery_date" class="form-label">Expected delivery date</label>
                <input id="estimated_delivery_date" type="date" name="estimated_delivery_date" class="form-control">
            </div>
            <div class="col-md-6">
                <label for="estimated_delivery_time" class="form-label">Expected delivery time</label>
                <input id="estimated_delivery_time" type="time" name="estimated_delivery_time" class="form-control">
            </div>
        </div>
        <button name="action" value="confirm" class="btn btn-primary">Confirm Payment</button>
        <button name="action" value="reject" class="btn btn-danger">Reject Payment</button>
    </form>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>