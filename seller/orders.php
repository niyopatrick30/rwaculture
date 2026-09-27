<?php
$page_title = 'My Orders';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name
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
        <h1 class="mb-0">Orders</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="dashboard-card">
        <?php if (empty($orders)): ?>
            <p class="mb-0">No orders yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Buyer</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Delivery</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                                <td><span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td><a class="btn btn-sm btn-primary" href="order-details.php?id=<?php echo (int)$order['id']; ?>">View</a></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

