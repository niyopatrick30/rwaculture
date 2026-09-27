<?php
$page_title = 'My Account';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireBuyer();

$user_id = $_SESSION['user_id'];

// Get user info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Get recent orders
$stmt = $conn->prepare("SELECT * FROM orders WHERE buyer_id = ? ORDER BY created_at DESC LIMIT 5");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$recent_orders = [];
while ($row = $result->fetch_assoc()) {
    $recent_orders[] = $row;
}
$stmt->close();

$chat_sellers = [];
$requested_seller_id = (int)($_GET['seller_id'] ?? 0);
if (($_GET['open_chat'] ?? '') === '1' && $requested_seller_id > 0 && (int)($_GET['chat_id'] ?? 0) > 0) {
    $requested_chat_id = (int)$_GET['chat_id'];
    $stmt = $conn->prepare("SELECT c.id AS chat_id, s.id AS seller_id, s.first_name, s.last_name
                            FROM chats c
                            JOIN users s ON s.id = c.seller_id
                            WHERE c.id = ? AND c.user_id = ? AND c.seller_id = ? AND s.role = 'seller' AND s.status = 'active'");
    $stmt->bind_param("iii", $requested_chat_id, $user_id, $requested_seller_id);
    $stmt->execute();
    $chat_seller = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($chat_seller) {
        $chat_sellers[] = [
            'id' => (int)$chat_seller['seller_id'],
            'name' => $chat_seller['first_name'] . ' ' . $chat_seller['last_name'],
            'chat_id' => (int)$chat_seller['chat_id'],
        ];
    }
}

include '../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4">My Account</h1>
    
    <div class="row">
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Profile Information</h3>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
                <p><strong>Phone:</strong> <?php echo htmlspecialchars($user['phone'] ?? 'Not provided'); ?></p>
                <a href="edit-profile.php" class="btn btn-primary">Edit Profile</a>
            </div>
        </div>
        
        <div class="col-md-8">
            <div class="dashboard-card">
                <h3>Recent Orders</h3>
                <?php if (empty($recent_orders)): ?>
                    <p>No orders yet. <a href="../index.php">Start shopping!</a></p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Order Number</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Date</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                        <td><?php echo formatCurrency($order['total_amount']); ?></td>
                                        <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td><a href="order-details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <a href="orders.php" class="btn btn-outline-primary">View All Orders</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($chat_sellers): ?>
<script>
window.RWACULTURE_CHAT_SELLER_ID = <?php echo $chat_sellers[0]['id']; ?>;
window.RWACULTURE_CHAT_ID = <?php echo $chat_sellers[0]['chat_id']; ?>;
window.RWACULTURE_CHAT_SELLERS = <?php echo json_encode($chat_sellers, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT); ?>;
</script>
<?php endif; ?>

<?php include '../includes/footer.php'; ?>
