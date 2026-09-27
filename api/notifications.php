<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Not authenticated']);
    exit();
}

$user_id = $_SESSION['user_id'];
session_write_close();

if ($action === 'get_count') {
    $count = getUnreadNotificationCount($user_id);
    echo json_encode(['count' => $count]);
    
} elseif ($action === 'get_all') {
    $admin_notification_filter = ($_SESSION['user_role'] ?? '') === 'admin' ? " AND type <> 'cart_item_added'" : '';
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ?{$admin_notification_filter} ORDER BY created_at DESC LIMIT 50");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $notifications = [];
    while ($row = $result->fetch_assoc()) {
        $notifications[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'notifications' => $notifications]);
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_read') {
    $data = json_decode(file_get_contents('php://input'), true);
    $notification_id = (int)($data['notification_id'] ?? $_POST['notification_id'] ?? 0);
    
    if ($notification_id > 0) {
        $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->bind_param("ii", $notification_id, $user_id);
        $stmt->execute();
        $stmt->close();
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid notification ID']);
    }
    
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'mark_all_read') {
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => true]);
    
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
