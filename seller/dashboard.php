<?php
$page_title = 'Seller Dashboard';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT * FROM seller_bank_accounts WHERE seller_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$seller_payment_account = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Get statistics
$stats = [];

// Total sales
$stmt = $conn->prepare("SELECT SUM(total_amount) as total FROM orders WHERE seller_id = ? AND status != 'cancelled'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_sales'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total orders
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE seller_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_orders'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total products
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM products WHERE seller_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['total_products'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Pending orders
$stmt = $conn->prepare("SELECT COUNT(*) as total FROM orders WHERE seller_id = ? AND status IN ('pending', 'payment_confirmed', 'preparing')");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$stats['pending_orders'] = $result->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Commission balance
$stmt = $conn->prepare("SELECT
    COALESCE((SELECT SUM(sc.commission_amount) FROM seller_commissions sc WHERE sc.seller_id = ? AND sc.status != 'cancelled' AND EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed')), 0) -
    COALESCE((SELECT SUM(admin_verified_amount) FROM commission_settlements WHERE seller_id = ? AND status = 'confirmed'), 0) AS balance");
$stmt->bind_param("ii", $user_id, $user_id);
$stmt->execute();
$stats['commission_balance'] = max(0, (float)($stmt->get_result()->fetch_assoc()['balance'] ?? 0));
$stmt->close();

// Recent orders
$stmt = $conn->prepare("SELECT o.*, u.first_name, u.last_name 
                        FROM orders o 
                        JOIN users u ON o.buyer_id = u.id 
                        WHERE o.seller_id = ? 
                        ORDER BY o.created_at DESC 
                        LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_orders = [];
while ($row = $result->fetch_assoc()) {
    $recent_orders[] = $row;
}
$stmt->close();

// Messages sent by admin in the seller's live-chat conversation
$stmt = $conn->prepare("SELECT cm.message, cm.created_at
                        FROM chat_messages cm
                        JOIN chats c ON c.id = cm.chat_id
                        WHERE c.user_id = ? AND cm.is_admin = 1
                        ORDER BY cm.created_at DESC
                        LIMIT 10");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$admin_chat_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4">Seller Dashboard</h1>

    <?php if (!$seller_payment_account): ?>
        <div class="alert alert-warning d-flex justify-content-between align-items-center" data-persistent="true">
            <span><strong>Buyer payments are not configured.</strong> Add your bank or mobile-money account so customers can pay you.</span>
            <a href="bank-account.php" class="btn btn-warning">Add payment account</a>
        </div>
    <?php endif; ?>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo formatCurrency($stats['total_sales']); ?></h4>
                <p>Total Sales</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['total_orders']; ?></h4>
                <p>Total Orders</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['total_products']; ?></h4>
                <p>My Products</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['pending_orders']; ?></h4>
                <p>Pending Orders</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo formatCurrency($stats['commission_balance']); ?></h4>
                <p>Commission Balance</p>
            </div>
        </div>
    </div>

    <div class="dashboard-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h3 class="mb-0">Messages from Admin</h3>
            <button type="button" class="btn btn-outline-primary btn-sm" id="openSellerChat">Open Live Chat</button>
        </div>
        <?php if ($admin_chat_messages): ?>
            <?php foreach ($admin_chat_messages as $chat_message): ?>
                <div class="border-start border-primary border-3 ps-3 mb-3">
                    <p class="mb-1"><?php echo nl2br(htmlspecialchars($chat_message['message'])); ?></p>
                    <small class="text-muted">Admin, <?php echo date('M j, Y g:i A', strtotime($chat_message['created_at'])); ?></small>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p class="text-muted mb-0">No messages from admin yet. Use Live Chat to discuss a topic with support.</p>
        <?php endif; ?>
    </div>
    
    <!-- Quick Links -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="dashboard-card">
                <h3>Quick Links</h3>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="products.php" class="btn btn-outline-primary w-100">Manage Products</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="orders.php" class="btn btn-outline-primary w-100">View Orders</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="add-product.php" class="btn btn-outline-primary w-100">Add Product</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="deliveries.php" class="btn btn-outline-primary w-100">Delivery Tracking</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="bank-account.php" class="btn btn-outline-primary w-100">Buyer Payment Account</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="commissions.php" class="btn btn-outline-primary w-100">Commission Settlement</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="chats.php" class="btn btn-outline-primary w-100">Buyer Chats</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Recent Orders -->
    <div class="row">
        <div class="col-md-12">
            <div class="dashboard-card">
                <h3>Recent Orders</h3>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Order Number</th>
                                <th>Buyer</th>
                                <th>Amount</th>
                                <th>Status</th>
                                <th>Delivery Status</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr>
                                    <td colspan="7" class="text-center">No orders yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                                        <td><span class="badge bg-warning"><?php echo ucfirst(str_replace('_', ' ', $order['delivery_status'])); ?></span></td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td>
                                            <a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('openSellerChat')?.addEventListener('click', function() {
    document.getElementById('chatToggle')?.click();
});
</script>

<?php include '../includes/footer.php'; ?>
