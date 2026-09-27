<?php
$page_title = 'Order Details';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireBuyer();

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Get order
$stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, u.email as seller_email,
                               pp.status AS payment_status, pp.seller_comment AS payment_message
                        FROM orders o 
                        JOIN users u ON o.seller_id = u.id 
                        LEFT JOIN payment_proofs pp ON pp.order_id = o.id
                        WHERE o.id = ? AND o.buyer_id = ?");
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
$stmt = $conn->prepare("SELECT oi.*, p.name, p.image 
                        FROM order_items oi 
                        JOIN products p ON oi.product_id = p.id 
                        WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order_items = [];
while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();

// Get delivery tracking
$stmt = $conn->prepare("SELECT * FROM delivery_tracking WHERE order_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$tracking = [];
while ($row = $result->fetch_assoc()) {
    $tracking[] = $row;
}
$stmt->close();

include '../includes/header.php';
$additional_scripts = ['location.js'];
?>

<div class="container my-4">
    <h1 class="mb-4">Order Details</h1>
    
    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-card mb-4">
                <h3>Order Information</h3>
                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                <p><strong>Status:</strong> <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></p>
                <?php if ($order['payment_status'] === 'confirmed' || $order['payment_status'] === 'rejected'): ?>
                    <p><strong>Payment Decision:</strong>
                        <span class="badge <?php echo $order['payment_status'] === 'confirmed' ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $order['payment_status'] === 'confirmed' ? 'Approved' : 'Rejected'; ?>
                        </span>
                    </p>
                    <?php if (!empty($order['payment_message'])): ?>
                        <p><strong>Seller Message:</strong> <?php echo htmlspecialchars($order['payment_message']); ?></p>
                    <?php endif; ?>
                <?php endif; ?>
                <p><strong>Delivery Status:</strong> <span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></span></p>
                <p><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>

                <!-- ================= INVOICE BUTTON ================= -->
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
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['image']): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($item['image']); ?>" 
                                                 style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </div>
                                </td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td><?php echo formatCurrency($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total:</th>
                            <th><?php echo formatCurrency($order['total_amount']); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            
            <?php if ($order['buyer_latitude'] && $order['buyer_longitude']): ?>
                <div class="dashboard-card mb-4">
                    <h3>Delivery Location</h3>
                    <?php if ($order['buyer_address']): ?>
                        <p><strong>Address:</strong> <?php echo htmlspecialchars($order['buyer_address']); ?></p>
                    <?php endif; ?>
                    <?php if ($order['buyer_city']): ?>
                        <p><strong>City:</strong> <?php echo htmlspecialchars($order['buyer_city']); ?></p>
                    <?php endif; ?>
                    <p><strong>Estimated Delivery:</strong> 
                        <?php echo date('F j, Y', strtotime($order['estimated_delivery_date'])); ?> 
                        at <?php echo date('g:i A', strtotime($order['estimated_delivery_time'])); ?>
                    </p>
                    <p><strong>ETA:</strong> <?php echo $order['delivery_eta_hours']; ?> hours</p>
                    <div id="orderMap" class="map-container"></div>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Seller Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order['seller_email']); ?></p>
                <button type="button" class="btn btn-outline-primary w-100" id="chatWithSeller">
                    <i class="fas fa-comments"></i> Chat with Seller
                </button>
            </div>
            
            <div class="dashboard-card mt-3">
                <h3>Delivery Tracking</h3>
                <?php if (empty($tracking)): ?>
                    <p>No tracking information available</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($tracking as $track): ?>
                            <li class="list-group-item">
                                <strong><?php echo ucfirst(str_replace('_', ' ', $track['status'])); ?></strong><br>
                                <small><?php echo date('M j, Y g:i A', strtotime($track['created_at'])); ?></small>
                                <?php if ($track['notes']): ?>
                                    <p class="mb-0"><?php echo htmlspecialchars($track['notes']); ?></p>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
window.RWACULTURE_CHAT_SELLERS = [{id: <?php echo (int)$order['seller_id']; ?>, name: <?php echo json_encode($order['first_name'] . ' ' . $order['last_name']); ?>}];
document.getElementById('chatWithSeller')?.addEventListener('click', function() {
    const chatTarget = document.getElementById('chatTarget');
    if (chatTarget) {
        chatTarget.value = '<?php echo (int)$order['seller_id']; ?>';
        chatTarget.dispatchEvent(new Event('change'));
    }
    document.getElementById('chatToggle')?.click();
});
</script>

<?php if ($order['buyer_latitude'] && $order['buyer_longitude']): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script>
document.addEventListener('DOMContentLoaded', function() {
    initMap('orderMap', <?= $order['buyer_latitude']; ?>, <?= $order['buyer_longitude']; ?>);
    addMarker(<?= $order['buyer_latitude']; ?>, <?= $order['buyer_longitude']; ?>, 'Delivery Location');
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
