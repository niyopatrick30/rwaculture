<?php
$page_title = 'Delete Record';
require_once '../config/config.php';
require_once '../includes/functions.php';

$user_id = $_SESSION['user_id'];
$user_role = $_SESSION['role'] ?? '';

// Check if user is logged in
requireLogin();

// Get type and ID
$type = isset($_GET['type']) ? $_GET['type'] : '';
$id   = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$type) {
    header('Location: dashboard.php');
    exit();
}

// Function to delete record safely
function deleteRecord($conn, $table, $id_column, $id) {
    $stmt = $conn->prepare("DELETE FROM $table WHERE $id_column = ?");
    $stmt->bind_param("i", $id);
    return $stmt->execute();
}

// Handle deletion based on type
$success = false;
$message = '';

switch ($type) {
    case 'order':
        if ($user_role === 'admin' || $user_role === 'seller') {
            $success = deleteRecord($conn, 'orders', 'id', $id);
            $message = 'Order deleted successfully';
        }
        break;

    case 'product':
        if ($user_role === 'admin' || $user_role === 'seller') {
            $success = deleteRecord($conn, 'products', 'id', $id);
            $message = 'Product deleted successfully';
        }
        break;

    case 'buyer':
        if ($user_role === 'admin') {
            $success = deleteRecord($conn, 'users', 'id', $id);
            $message = 'Buyer deleted successfully';
        }
        break;

    case 'seller':
        if ($user_role === 'admin') {
            $success = deleteRecord($conn, 'users', 'id', $id);
            $message = 'Seller deleted successfully';
        }
        break;

    case 'ticket':
        if ($user_role === 'admin' || $user_role === 'seller') {
            $success = deleteRecord($conn, 'support_tickets', 'id', $id);
            $message = 'Support ticket deleted successfully';
        }
        break;

    case 'chat':
        if ($user_role === 'admin' || $user_role === 'seller') {
            $success = deleteRecord($conn, 'chats', 'id', $id);
            $message = 'Chat deleted successfully';
        }
        break;

    case 'report':
        if ($user_role === 'admin') {
            $success = deleteRecord($conn, 'reports', 'id', $id);
            $message = 'Report deleted successfully';
        }
        break;

    case 'log':
        if ($user_role === 'admin') {
            $success = deleteRecord($conn, 'activity_logs', 'id', $id);
            $message = 'Activity log deleted successfully';
        }
        break;

    default:
        $message = 'Invalid delete type';
        break;
}

// Redirect back to dashboard or referer
if ($success) {
    $_SESSION['flash_success'] = $message;
} else {
    $_SESSION['flash_error'] = $message ?: 'You do not have permission to delete this record';
}

$redirect = $_SERVER['HTTP_REFERER'] ?? 'dashboard.php';
header("Location: $redirect");
exit();
