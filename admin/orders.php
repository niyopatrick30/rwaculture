<?php
$page_title = 'Manage Orders';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$stmt = $conn->query("SELECT o.*, b.first_name AS buyer_first, b.last_name AS buyer_last, s.first_name AS seller_first, s.last_name AS seller_last
                      FROM orders o
                      JOIN users b ON o.buyer_id = b.id
                      JOIN users s ON o.seller_id = s.id
                      ORDER BY o.created_at DESC");
$orders = [];
while ($row = $stmt->fetch_assoc()) {
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

    <?php
    $error = '';
    $success = '';
    
    // Handle order status update
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
        $update_order_id = (int)($_POST['order_id'] ?? 0);
        $new_status = sanitize(isset($_POST['status']) ? $_POST['status'] : '');
        $new_delivery_status = sanitize(isset($_POST['delivery_status']) ? $_POST['delivery_status'] : '');
        $notes = sanitize(isset($_POST['notes']) ? $_POST['notes'] : '');
        
        $allowed_status = ['pending', 'payment_confirmed', 'preparing', 'shipped', 'delivered', 'cancelled'];
        $allowed_delivery = ['pending', 'preparing', 'out_for_delivery', 'delivered'];
        
        if ($update_order_id > 0 && in_array($new_status, $allowed_status) && in_array($new_delivery_status, $allowed_delivery)) {
            $conn->begin_transaction();
            try {
                // Update order
                $stmt = $conn->prepare("UPDATE orders SET status = ?, delivery_status = ? WHERE id = ?");
                $stmt->bind_param("ssi", $new_status, $new_delivery_status, $update_order_id);
                $stmt->execute();
                $stmt->close();
                
                // Update delivery tracking
                $stmt = $conn->prepare("INSERT INTO delivery_tracking (order_id, status, notes, updated_by) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("issi", $update_order_id, $new_delivery_status, $notes, $_SESSION['user_id']);
                $stmt->execute();
                $stmt->close();
                
                // Get order for notification
                $stmt = $conn->prepare("SELECT order_number, buyer_id, seller_id FROM orders WHERE id = ?");
                $stmt->bind_param("i", $update_order_id);
                $stmt->execute();
                $updated_order = $stmt->get_result()->fetch_assoc();
                $stmt->close();
                
                // Create notifications
                if ($updated_order) {
                    createNotification($updated_order['buyer_id'], 'order_updated', 'Order Updated', "Your order #{$updated_order['order_number']} status has been updated to " . ucfirst(str_replace('_', ' ', $new_status)));
                    createNotification($updated_order['seller_id'], 'order_updated', 'Order Updated', "Order #{$updated_order['order_number']} status has been updated to " . ucfirst(str_replace('_', ' ', $new_status)));
                }
                
                logActivity($_SESSION['user_id'], 'admin_order_update', "Order #{$updated_order['order_number']} updated to {$new_status}/{$new_delivery_status}");
                
                $conn->commit();
                $success = 'Order status updated successfully.';
                
                // Refresh orders list
                header("Location: orders.php");
                exit();
            } catch (Exception $e) {
                $conn->rollback();
                $error = 'Failed to update order status.';
            }
        } else {
            $error = 'Invalid order update request.';
        }
    }
    
    if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="dashboard-card">
        <?php if (empty($orders)): ?>
            <p class="mb-0">No orders found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Buyer</th>
                            <th>Seller</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Delivery</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td><?php echo htmlspecialchars($order['buyer_first'] . ' ' . $order['buyer_last']); ?></td>
                                <td><?php echo htmlspecialchars($order['seller_first'] . ' ' . $order['seller_last']); ?></td>
                                <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        echo $order['status'] === 'delivered' ? 'bg-success' : 
                                            ($order['status'] === 'cancelled' ? 'bg-danger' : 
                                            ($order['status'] === 'payment_confirmed' ? 'bg-primary' : 'bg-info')); 
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge 
                                        <?php 
                                        echo $order['delivery_status'] === 'delivered' ? 'bg-success' : 
                                            ($order['delivery_status'] === 'out_for_delivery' ? 'bg-primary' : 'bg-warning'); 
                                        ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>
                                    <a class="btn btn-sm btn-primary" href="order-details.php?id=<?php echo (int)$order['id']; ?>">View</a>
                                    <button class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#updateOrderModal<?php echo (int)$order['id']; ?>">Update</button>
                                </td>
                            </tr>
                            
                            <!-- Update Order Modal -->
                            <div class="modal fade" id="updateOrderModal<?php echo (int)$order['id']; ?>" tabindex="-1">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Order #<?php echo htmlspecialchars($order['order_number']); ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="order_id" value="<?php echo (int)$order['id']; ?>">
                                                
                                                <div class="mb-3">
                                                    <label for="status<?php echo (int)$order['id']; ?>" class="form-label">Order Status</label>
                                                    <select class="form-control" id="status<?php echo (int)$order['id']; ?>" name="status" required>
                                                        <option value="pending" <?php echo $order['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="payment_confirmed" <?php echo $order['status'] === 'payment_confirmed' ? 'selected' : ''; ?>>Payment Confirmed</option>
                                                        <option value="preparing" <?php echo $order['status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                        <option value="shipped" <?php echo $order['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                                                        <option value="delivered" <?php echo $order['status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                        <option value="cancelled" <?php echo $order['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="delivery_status<?php echo (int)$order['id']; ?>" class="form-label">Delivery Status</label>
                                                    <select class="form-control" id="delivery_status<?php echo (int)$order['id']; ?>" name="delivery_status" required>
                                                        <option value="pending" <?php echo $order['delivery_status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="preparing" <?php echo $order['delivery_status'] === 'preparing' ? 'selected' : ''; ?>>Preparing</option>
                                                        <option value="out_for_delivery" <?php echo $order['delivery_status'] === 'out_for_delivery' ? 'selected' : ''; ?>>Out for Delivery</option>
                                                        <option value="delivered" <?php echo $order['delivery_status'] === 'delivered' ? 'selected' : ''; ?>>Delivered</option>
                                                    </select>
                                                </div>
                                                
                                                <div class="mb-3">
                                                    <label for="notes<?php echo (int)$order['id']; ?>" class="form-label">Notes (Optional)</label>
                                                    <textarea class="form-control" id="notes<?php echo (int)$order['id']; ?>" name="notes" rows="3" placeholder="Add any notes about this order update..."></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                <button type="submit" name="update_order" class="btn btn-primary">Update Order</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

