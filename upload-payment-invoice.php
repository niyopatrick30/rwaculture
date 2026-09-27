<?php
$page_title = 'Upload Payment & Invoice';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("
    SELECT o.*, u.first_name, u.last_name, u.email, u.phone 
    FROM orders o 
    JOIN users u ON o.buyer_id = u.id 
    WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header("Location: index.php");
    exit();
}

$stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$items = $stmt->get_result();
$stmt->close();

$uploaded = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['payment_proof'])) {
    $target = 'uploads/payments/';
    if (!is_dir($target)) mkdir($target, 0777, true);

    $filename = time().'_'.basename($_FILES['payment_proof']['name']);
    move_uploaded_file($_FILES['payment_proof']['tmp_name'], $target.$filename);
    $uploaded = true;
}

include 'includes/header.php';
?>

<div class="container my-5">
    <h3 class="mb-4">Upload Payment Proof</h3>

    <?php if (!$uploaded): ?>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="payment_proof" class="form-control mb-3" required>
            <button class="btn btn-primary">Upload & Get Invoice</button>
        </form>
    <?php else: ?>

    <div class="card p-4">
        <img src="Rwaculture logoo.png" style="max-width:150px;">
        <h3 class="mt-2">Rwaculture</h3>

        <p><strong>Buyer:</strong> <?php echo $order['first_name'].' '.$order['last_name']; ?></p>
        <p><strong>Phone:</strong> <?php echo $order['phone']; ?></p>
        <p><strong>Email:</strong> <?php echo $order['email']; ?></p>
        <p><strong>Date:</strong> <?php echo date('F j, Y'); ?></p>

        <table class="table mt-3">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Qty</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php while($row = $items->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['name']; ?></td>
                    <td><?php echo $row['quantity']; ?></td>
                    <td><?php echo formatCurrency($row['price']); ?></td>
                    <td><?php echo formatCurrency($row['subtotal']); ?></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>

        <h4>Total: <?php echo formatCurrency($order['total_amount']); ?></h4>

        <p class="mt-3 text-success">
            Thank you for shopping with Rwaculture Company Ltd.
        </p>
    </div>

    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
