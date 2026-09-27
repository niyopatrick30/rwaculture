 <?php
$page_title = 'Admin Dashboard';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$user_id = $_SESSION['user_id'];

// Get statistics
$stats = [];

// Total sales
$stmt = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE status != 'cancelled'");
$stats['total_sales'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total commission
$stmt = $conn->query("SELECT SUM(sc.commission_amount) as total FROM seller_commissions sc WHERE sc.status != 'cancelled' AND EXISTS (SELECT 1 FROM payment_proofs pp WHERE pp.order_id = sc.order_id AND pp.status = 'confirmed')");
$stats['total_commission'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total orders
$stmt = $conn->query("SELECT COUNT(*) as total FROM orders");
$stats['total_orders'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total users
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role != 'admin'");
$stats['total_users'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total sellers
$stmt = $conn->query("SELECT COUNT(*) as total FROM users WHERE role = 'seller'");
$stats['total_sellers'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Total products
$stmt = $conn->query("SELECT COUNT(*) as total FROM products");
$stats['total_products'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Recent orders
$stmt = $conn->query("SELECT o.*, u.first_name, u.last_name 
                      FROM orders o 
                      JOIN users u ON o.buyer_id = u.id 
                      ORDER BY o.created_at DESC 
                      LIMIT 10");
$recent_orders = [];
while ($row = $stmt->fetch_assoc()) {
    $recent_orders[] = $row;
}
$stmt->close();

// Pending support tickets
$stmt = $conn->query("SELECT COUNT(*) as total FROM support_tickets WHERE status IN ('open', 'in_progress')");
$stats['pending_tickets'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

// Active chats
$stmt = $conn->query("SELECT COUNT(*) as total FROM chats WHERE status = 'active'");
$stats['active_chats'] = $stmt->fetch_assoc()['total'] ?? 0;
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4">Admin Dashboard</h1>
    
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
                <h4><?php echo formatCurrency($stats['total_commission']); ?></h4>
                <p>Platform Commission</p>
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
                <h4><?php echo $stats['total_users']; ?></h4>
                <p>Total Users</p>
            </div>
        </div>
    </div>
    
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['total_sellers']; ?></h4>
                <p>Total Sellers</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['total_products']; ?></h4>
                <p>Total Products</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['pending_tickets']; ?></h4>
                <p>Pending Support Tickets</p>
            </div>
        </div>
        <div class="col-md-3 mb-3">
            <div class="stat-card">
                <h4><?php echo $stats['active_chats']; ?></h4>
                <p>Active Chats</p>
            </div>
        </div>
    </div>
    
    <!-- Quick Links -->
    <div class="row mb-4">
        <div class="col-md-12">
            <div class="dashboard-card">
                <h3>Quick Links</h3>
                <div class="row">
                    <div class="col-md-3 mb-2">
                        <a href="orders.php" class="btn btn-outline-primary w-100">Manage Orders</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="sellers.php" class="btn btn-outline-primary w-100">Manage Sellers</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="products.php" class="btn btn-outline-primary w-100">Manage Products</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="users.php" class="btn btn-outline-primary w-100">Manage Users</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="../support/tickets.php?admin=1" class="btn btn-outline-primary w-100">Support Tickets</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="chats.php" class="btn btn-outline-primary w-100">Live Chats</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="reports.php" class="btn btn-outline-primary w-100">Reports & Analytics</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="commissions.php" class="btn btn-outline-primary w-100">Commission Management</a>
                    </div>
                    <div class="col-md-3 mb-2">
                        <a href="activity_logs.php" class="btn btn-outline-primary w-100">Activity Logs</a>
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
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recent_orders)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">No orders yet</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo htmlspecialchars($order['first_name'] . ' ' . $order['last_name']); ?></td>
                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td><a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
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

<?php include '../includes/footer.php'; ?>
