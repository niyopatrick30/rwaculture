<?php
$page_title = 'Delivery Tracking';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT o.id, o.order_number, o.delivery_status, o.estimated_delivery_date, o.estimated_delivery_time, o.created_at,
                               o.buyer_address, o.buyer_city, o.buyer_latitude, o.buyer_longitude,
                               u.first_name, u.last_name, u.email, u.phone
                        FROM orders o
                        JOIN users u ON o.buyer_id = u.id
                        WHERE o.seller_id = ?
                        ORDER BY o.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$orders = [];
while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Delivery Tracking</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="dashboard-card">
        <?php if (empty($orders)): ?>
            <p class="mb-0">No deliveries yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Buyer</th>
                            <th>Contact &amp; Delivery Location</th>
                            <th>Delivery Status</th>
                            <th>ETA</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td>
                                    <div><?php if (!empty($order['phone'])): ?><a href="tel:<?php echo htmlspecialchars($order['phone']); ?>"><?php echo htmlspecialchars($order['phone']); ?></a><?php else: ?>Phone not provided<?php endif; ?></div>
                                    <div><?php if (!empty($order['email'])): ?><a href="mailto:<?php echo htmlspecialchars($order['email']); ?>"><?php echo htmlspecialchars($order['email']); ?></a><?php else: ?>Email not provided<?php endif; ?></div>
                                    <div><?php echo htmlspecialchars(trim(($order['buyer_address'] ?? '') . (!empty($order['buyer_city']) ? ', ' . $order['buyer_city'] : '')) ?: 'Address not provided'); ?></div>
                                    <?php if ($order['buyer_latitude'] !== null && $order['buyer_longitude'] !== null): ?>
                                        <a href="https://www.google.com/maps?q=<?php echo rawurlencode($order['buyer_latitude'] . ',' . $order['buyer_longitude']); ?>" target="_blank" rel="noopener">Open delivery pin</a>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></span></td>
                                <td>
                                    <?php if (!empty($order['estimated_delivery_date'])): ?>
                                        <?php echo date('M j, Y', strtotime($order['estimated_delivery_date'])); ?>
                                        <?php if (!empty($order['estimated_delivery_time'])): ?>
                                            at <?php echo date('g:i A', strtotime($order['estimated_delivery_time'])); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        N/A
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a class="btn btn-sm btn-primary" href="order-details.php?id=<?php echo (int)$order['id']; ?>">Update</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

