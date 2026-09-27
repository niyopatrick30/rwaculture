<?php
$page_title = 'Order Confirmation';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin(); // Only logged-in buyers

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Get order and seller info
$stmt = $conn->prepare("
    SELECT o.*, u.first_name AS seller_first, u.last_name AS seller_last, u.email AS seller_email
    FROM orders o
    JOIN users u ON o.seller_id = u.id
    WHERE o.id = ? AND o.buyer_id = ?
");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: orders.php');
    exit();
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name, p.image 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order_items = [];
while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

include 'includes/header.php';
$additional_scripts = ['location.js'];
?>

<div class="container my-4">
    <h1 class="mb-4">Order Confirmed!</h1>

    <div class="alert alert-success">
        Your order <strong>#<?= htmlspecialchars($order['order_number']) ?></strong> has been successfully confirmed.
        Below are your order details:
    </div>

    <div class="mb-3">
        <a href="download-invoice.php?order_id=<?= $order_id ?>" class="btn btn-primary">
            <i class="fas fa-file-pdf"></i> Download PDF Invoice
        </a>
    </div>

    <div class="dashboard-card mb-4">
        <h3>Order Information</h3>
        <p><strong>Order Number:</strong> <?= htmlspecialchars($order['order_number']) ?></p>
        <p><strong>Status:</strong> <span class="badge bg-success"><?= ucfirst(str_replace('_',' ', $order['status'])) ?></span></p>
        <p><strong>Total Amount:</strong> <?= formatCurrency($order['total_amount']) ?></p>
        <p><strong>Estimated Delivery:</strong> <?= date('F j, Y', strtotime($order['estimated_delivery_date'])) ?> at <?= date('g:i A', strtotime($order['estimated_delivery_time'])) ?></p>
        <p><strong>ETA:</strong> <?= $order['delivery_eta_hours'] ?> hours</p>
    </div>

    <div class="dashboard-card mb-4">
        <h3>Buyer Address</h3>
        <?php if ($order['buyer_address']): ?>
            <p><strong>Address:</strong> <?= htmlspecialchars($order['buyer_address']) ?></p>
        <?php endif; ?>
        <?php if ($order['buyer_city']): ?>
            <p><strong>City:</strong> <?= htmlspecialchars($order['buyer_city']) ?></p>
        <?php endif; ?>
    </div>

    <?php if ($order['buyer_latitude'] && $order['buyer_longitude']): ?>
        <div class="dashboard-card mb-4">
            <h3>Delivery Location</h3>
            <div id="confirmationMap" class="map-container" style="height: 300px;"></div>
        </div>
    <?php endif; ?>

    <div class="dashboard-card mb-4">
        <h3>Order Items</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Quantity</th>
                    <th>Price</th>
                    <th>Subtotal</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($order_items as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['name']) ?></td>
                        <td><?= $item['quantity'] ?></td>
                        <td><?= formatCurrency($item['price']) ?></td>
                        <td><?= formatCurrency($item['subtotal']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php if ($order['buyer_latitude'] && $order['buyer_longitude']): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script>
document.addEventListener('DOMContentLoaded', function() {
    const lat = <?= $order['buyer_latitude'] ?>;
    const lng = <?= $order['buyer_longitude'] ?>;

    const map = L.map('confirmationMap').setView([lat, lng], 14);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup('Delivery Location')
        .openPopup();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
