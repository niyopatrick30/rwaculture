<?php
$page_title = 'Order Details';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Load order (must belong to seller)
$stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, u.email as buyer_email, u.phone as buyer_phone
                        FROM orders o
                        JOIN users u ON o.buyer_id = u.id
                        WHERE o.id = ? AND o.seller_id = ?");
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    header('Location: orders.php');
    exit();
}

$error = '';
$success = '';

// Update status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['status'] ?? $order['status']);
    $new_delivery = sanitize($_POST['delivery_status'] ?? $order['delivery_status']);
    $notes = sanitize($_POST['notes'] ?? '');

    $allowed_status = ['pending', 'payment_confirmed', 'preparing', 'shipped', 'delivered', 'cancelled'];
    $allowed_delivery = ['pending', 'preparing', 'out_for_delivery', 'delivered'];

    if (!in_array($new_status, $allowed_status, true) || !in_array($new_delivery, $allowed_delivery, true)) {
        $error = 'Invalid status selection.';
    } else {
        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE orders SET status = ?, delivery_status = ? WHERE id = ? AND seller_id = ?");
            $stmt->bind_param("ssii", $new_status, $new_delivery, $order_id, $user_id);
            $stmt->execute();
            $stmt->close();

            $stmt = $conn->prepare("INSERT INTO delivery_tracking (order_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
            $stmt->bind_param("issi", $order_id, $new_delivery, $notes, $user_id);
            $stmt->execute();
            $stmt->close();

            createNotification((int)$order['buyer_id'], 'order_updated', 'Order Updated', "Your order #{$order['order_number']} status was updated.");
            logActivity($user_id, 'order_status_updated', "Updated order #{$order['order_number']} to {$new_status}/{$new_delivery}");

            $conn->commit();
            $success = 'Order status updated.';
        } catch (Exception $e) {
            $conn->rollback();
            $error = 'Failed to update order status.';
        }

        // Refresh order
        $stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name, u.email as buyer_email, u.phone as buyer_phone
                                FROM orders o
                                JOIN users u ON o.buyer_id = u.id
                                WHERE o.id = ? AND o.seller_id = ?");
        $stmt->bind_param("ii", $order_id, $user_id);
        $stmt->execute();
        $order = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

// Items
$stmt = $conn->prepare("SELECT oi.*, p.name, p.image
                        FROM order_items oi
                        JOIN products p ON oi.product_id = p.id
                        WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$items = [];
while ($row = $result->fetch_assoc()) {
    $items[] = $row;
}
$stmt->close();

// Tracking
$stmt = $conn->prepare("SELECT * FROM delivery_tracking WHERE order_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$tracking = [];
while ($row = $result->fetch_assoc()) {
    $tracking[] = $row;
}
$stmt->close();

$additional_scripts = ['location.js'];
include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Order Details</h1>
        <a href="orders.php" class="btn btn-outline-primary">Back to Orders</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-card mb-4">
                <h3>Order</h3>
                <p><strong>Order Number:</strong> <?php echo htmlspecialchars($order['order_number']); ?></p>
                <p><strong>Buyer:</strong> <?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></p>
                <p><strong>Email:</strong> <a href="mailto:<?php echo htmlspecialchars($order['buyer_email']); ?>"><?php echo htmlspecialchars($order['buyer_email']); ?></a></p>
                <p><strong>Telephone:</strong>
                    <?php if (!empty($order['buyer_phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($order['buyer_phone']); ?>"><?php echo htmlspecialchars($order['buyer_phone']); ?></a>
                    <?php else: ?>
                        Not provided
                    <?php endif; ?>
                </p>
                <p><strong>Status:</strong> <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></p>
                <p><strong>Delivery:</strong> <span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></span></p>
            </div>

            <div class="dashboard-card mb-4">
                <h3>Items</h3>
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
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if (!empty($item['image'])): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($item['image']); ?>"
                                                 style="width: 50px; height: 50px; object-fit: cover; margin-right: 10px;">
                                        <?php endif; ?>
                                        <?php echo htmlspecialchars($item['name']); ?>
                                    </div>
                                </td>
                                <td><?php echo (int)$item['quantity']; ?></td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td><?php echo formatCurrency($item['subtotal']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3">Total</th>
                            <th><?php echo formatCurrency($order['total_amount']); ?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="dashboard-card mb-4">
                <h3>Delivery Location</h3>
                <?php if (!empty($order['buyer_address'])): ?>
                    <p><strong>Address:</strong> <?php echo htmlspecialchars($order['buyer_address']); ?></p>
                <?php endif; ?>
                <?php if (!empty($order['buyer_city'])): ?>
                    <p><strong>City:</strong> <?php echo htmlspecialchars($order['buyer_city']); ?></p>
                <?php endif; ?>
                <?php if (!empty($order['buyer_latitude']) && !empty($order['buyer_longitude'])): ?>
                    <div id="orderMap" class="map-container"></div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Update Status</h3>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Order Status</label>
                        <select class="form-control" name="status">
                            <?php foreach (['pending','payment_confirmed','preparing','shipped','delivered','cancelled'] as $s): ?>
                                <option value="<?php echo $s; ?>" <?php echo $order['status'] === $s ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $s)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Delivery Status</label>
                        <select class="form-control" name="delivery_status">
                            <?php foreach (['pending','preparing','out_for_delivery','delivered'] as $d): ?>
                                <option value="<?php echo $d; ?>" <?php echo $order['delivery_status'] === $d ? 'selected' : ''; ?>>
                                    <?php echo ucfirst(str_replace('_', ' ', $d)); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes (optional)</label>
                        <textarea class="form-control" name="notes" rows="3" placeholder="Add a delivery update..."></textarea>
                    </div>
                    <button class="btn btn-primary w-100" type="submit" name="update_status">Update</button>
                </form>
            </div>

            <div class="dashboard-card mt-3">
                <h3>Tracking</h3>
                <?php if (empty($tracking)): ?>
                    <p class="mb-0">No tracking updates.</p>
                <?php else: ?>
                    <ul class="list-group">
                        <?php foreach ($tracking as $track): ?>
                            <li class="list-group-item">
                                <strong><?php echo ucfirst(str_replace('_', ' ', $track['status'])); ?></strong><br>
                                <small><?php echo date('M j, Y g:i A', strtotime($track['created_at'])); ?></small>
                                <?php if (!empty($track['notes'])): ?>
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

<?php if (!empty($order['buyer_latitude']) && !empty($order['buyer_longitude'])): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script>
document.addEventListener('DOMContentLoaded', function() {
    initMap('orderMap', <?php echo $order['buyer_latitude']; ?>, <?php echo $order['buyer_longitude']; ?>);
    addMarker(<?php echo $order['buyer_latitude']; ?>, <?php echo $order['buyer_longitude']; ?>, 'Delivery Location');
});
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>

