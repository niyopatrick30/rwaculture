<?php
$page_title = 'Order Details';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$order_id) { header('Location: orders.php'); exit(); }

/* ================= ORDER ================= */
$stmt = $conn->prepare("
    SELECT o.*, 
           b.first_name AS buyer_first,
           b.last_name AS buyer_last,
           b.email AS buyer_email,
           b.phone AS buyer_phone,

           o.buyer_address,
           o.buyer_city,
           o.buyer_latitude,
           o.buyer_longitude,

           s.first_name AS seller_first,
           s.last_name AS seller_last,
           s.email AS seller_email
    FROM orders o
    JOIN users b ON o.buyer_id = b.id
    JOIN users s ON o.seller_id = s.id
    WHERE o.id = ?
");
if (!$stmt) { die("SQL Error (Orders): ".$conn->error); }
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) { header('Location: orders.php'); exit(); }

/* ================= ITEMS ================= */
$stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
if (!$stmt) { die("SQL Error (Order Items): ".$conn->error); }
$stmt->bind_param("i",$order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

/* ================= PAYMENT PROOF ================= */
$stmt = $conn->prepare("SELECT * FROM payment_proofs WHERE order_id=? LIMIT 1");
if (!$stmt) { die("SQL Error (Payment Proof): ".$conn->error); }
$stmt->bind_param("i",$order_id);
$stmt->execute();
$payment_proof = $stmt->get_result()->fetch_assoc();
$stmt->close();

/* ================= CONFIRM / REJECT ORDER ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_action'])) {

    if ($_POST['order_action'] === 'confirm') {
        $stmt = $conn->prepare("UPDATE orders SET status='payment_confirmed' WHERE id=?");
        if(!$stmt) { die("SQL Error (Confirm Order): ".$conn->error); }
        $stmt->bind_param("i",$order_id);
        $stmt->execute();
        $stmt->close();

        createNotification(
            $order['buyer_id'],
            'order_confirmed',
            'Order Confirmed',
            "Your order #{$order['order_number']} has been confirmed."
        );

        $order['status'] = 'payment_confirmed';

    } elseif ($_POST['order_action'] === 'reject') {
        $reason = trim($_POST['rejection_reason']);
        if(empty($reason)) {
            $error = "Rejection reason is required!";
        } else {
            // Update order status only
            $stmt = $conn->prepare("UPDATE orders SET status='cancelled' WHERE id=?");
            if(!$stmt) { die("SQL Error (Reject Order): ".$conn->error); }
            $stmt->bind_param("i",$order_id);
            $stmt->execute();
            $stmt->close();

            // Notify buyer with reason
            createNotification(
                $order['buyer_id'],
                'order_rejected',
                'Order Rejected',
                "Your order #{$order['order_number']} has been rejected. Reason: {$reason}"
            );

            $order['status'] = 'cancelled';
            $order['rejection_reason'] = $reason;
        }
    }

    if(!isset($error)) {
        header("Location: order-details.php?id={$order_id}");
        exit();
    }
}

include '../includes/header.php';
?>

<div class="container my-4">
<h1>Order Details</h1>

<?php if(isset($error)): ?>
    <div class="alert alert-danger"><?=$error?></div>
<?php endif; ?>

<div class="dashboard-card mb-4">
    <h3>Order #<?=htmlspecialchars($order['order_number'])?></h3>
    <p><strong>Status:</strong> <?=ucfirst(str_replace('_',' ',$order['status']))?></p>
    <?php if(isset($order['rejection_reason'])): ?>
        <p><strong>Rejection Reason:</strong> <?=htmlspecialchars($order['rejection_reason'])?></p>
    <?php endif; ?>
    <p><strong>Total:</strong> <?=formatCurrency($order['total_amount'])?></p>

    <?php if(!in_array($order['status'],['cancelled','payment_confirmed'])): ?>
        <form method="POST" class="mt-4">
            <div class="mb-3">
                <label class="form-label"><strong>Rejection Reason</strong></label>
                <textarea name="rejection_reason" class="form-control" placeholder="Required if rejecting"></textarea>
            </div>

            <div class="d-flex gap-3">
                <button name="order_action" value="confirm" class="btn btn-success">Confirm Order</button>
                <button name="order_action" value="reject" class="btn btn-danger">Reject Order</button>
            </div>
        </form>
    <?php endif; ?>

    <?php if($order['status'] === 'payment_confirmed'): ?>
        <div class="mt-3">
            <a href="<?=SITE_URL?>/order-confirmation.php?order_id=<?=$order_id?>" class="btn btn-primary">
               Get your invoice here
            </a>
        </div>
    <?php endif; ?>
</div>

<div class="dashboard-card mb-4">
    <h3>Order Items</h3>
    <table class="table">
        <thead>
            <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach($order_items as $item): ?>
            <tr>
                <td><?=$item['name']?></td>
                <td><?=$item['quantity']?></td>
                <td><?=formatCurrency($item['price'])?></td>
                <td><?=formatCurrency($item['subtotal'])?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</div>

<?php include '../includes/footer.php'; ?>
