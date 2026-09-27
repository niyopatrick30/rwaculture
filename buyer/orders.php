<?php
$page_title = 'My Orders';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireBuyer();

$user_id = $_SESSION['user_id'];

// Get all orders
$stmt = $conn->prepare("SELECT o.*, pp.status AS payment_status, pp.seller_comment AS payment_message
                        FROM orders o
                        LEFT JOIN payment_proofs pp ON pp.order_id = o.id
                        WHERE o.buyer_id = ?
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
    <h1 class="mb-4">My Orders</h1>
    
    <?php if (empty($orders)): ?>
        <div class="text-center my-5">
            <p>No orders yet. <a href="../index.php">Start shopping!</a></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Order Number</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Payment Decision</th>
                        <th>Delivery Status</th>
                        <th>Estimated Delivery</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($orders as $order): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                            <td><?php echo formatCurrency($order['total_amount']); ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                            <td>
                                <?php if ($order['payment_status'] === 'confirmed'): ?>
                                    <span class="badge bg-success">Approved</span>
                                <?php elseif ($order['payment_status'] === 'rejected'): ?>
                                    <span class="badge bg-danger">Rejected</span>
                                    <?php if (!empty($order['payment_message'])): ?>
                                        <div class="small mt-1"><?php echo htmlspecialchars($order['payment_message']); ?></div>
                                    <?php endif; ?>
                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></span></td>
                            <td>
                                <?php if ($order['estimated_delivery_date']): ?>
                                    <?php echo date('M j, Y', strtotime($order['estimated_delivery_date'])); ?>
                                    <?php if ($order['estimated_delivery_time']): ?>
                                        at <?php echo date('g:i A', strtotime($order['estimated_delivery_time'])); ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    N/A
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                            <td>
                                <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View Details</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
