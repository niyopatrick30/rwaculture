<?php
$page_title = 'Notifications';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = $_SESSION['user_id'];

// Get notifications
$admin_notification_filter = ($_SESSION['user_role'] ?? '') === 'admin' ? " AND type <> 'cart_item_added'" : '';
$stmt = $conn->prepare("
    SELECT * 
    FROM notifications 
    WHERE user_id = ?{$admin_notification_filter}
    ORDER BY created_at DESC 
    LIMIT 100
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$stmt->close();

// Mark all as read
if (isset($_GET['mark_all_read'])) {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    header('Location: notifications.php');
    exit();
}

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Notifications</h2>
        <a href="?mark_all_read=1" class="btn btn-outline-primary">
            Mark All as Read
        </a>
    </div>
    
    <?php if (empty($notifications)): ?>
        <div class="text-center my-5">
            <p>No notifications yet.</p>
        </div>
    <?php else: ?>
        <div class="list-group">
            <?php foreach ($notifications as $notification): ?>
                <div class="list-group-item <?php echo $notification['is_read'] ? '' : 'bg-light'; ?>">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="mb-1">
                                <?php echo htmlspecialchars($notification['title']); ?>
                            </h5>

                            <!-- MESSAGE (SAFE TEXT ONLY) -->
                            <p class="mb-2">
                                <?php echo htmlspecialchars($notification['message']); ?>
                            </p>

                            <small class="text-muted">
                                <?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?>
                            </small>

                            <!-- ✅ CONTINUE ORDER BUTTON (NEW, SAFE, WORKING) -->
                            <?php if ($notification['type'] === 'order_confirmed' && !empty($notification['link'])): ?>
                                <div class="mt-2">
                                    <a href="<?php echo htmlspecialchars($notification['link']); ?>"
                                       class="btn btn-primary btn-sm">
                                        Continue Order
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>

                        <?php if (!$notification['is_read']): ?>
                            <span class="badge bg-primary">New</span>
                        <?php endif; ?>
                    </div>

                    <!-- EXISTING GENERIC LINK (UNCHANGED) -->
                    <?php if (!empty($notification['link']) && $notification['type'] !== 'order_confirmed'): ?>
                        <a href="<?php echo htmlspecialchars($notification['link']); ?>"
                           class="btn btn-sm btn-outline-primary mt-2">
                            View
                        </a>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
