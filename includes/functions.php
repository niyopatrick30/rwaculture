<?php
// Helper Functions

// Build an absolute site URL for redirects/links
function siteUrl($path = '') {
    $base = rtrim(SITE_URL, '/');
    $path = ltrim((string)$path, '/');
    return $path === '' ? $base : ($base . '/' . $path);
}

function redirectTo($path) {
    header('Location: ' . siteUrl($path));
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check user role
function checkRole($required_role) {
    if (!isLoggedIn()) {
        return false;
    }
    return $_SESSION['user_role'] === $required_role;
}

// Require login
function requireLogin() {
    if (!isLoggedIn()) {
        $redirect = $_SERVER['REQUEST_URI'] ?? '';
        header('Location: ' . siteUrl('login.php') . ($redirect ? ('?redirect=' . urlencode($redirect)) : ''));
        exit();
    }
}

// Require specific role
function requireRole($role) {
    requireLogin();
    if (!checkRole($role)) {
        redirectTo('index.php');
    }
}

// Require buyer role (buyers only, not sellers or admins)
function requireBuyer() {
    requireLogin();
    if ($_SESSION['user_role'] !== 'buyer') {
        redirectTo('index.php');
    }
}

// Sanitize input
function sanitize($data) {
    global $conn;
    return mysqli_real_escape_string($conn, trim($data));
}

// Generate order number
function generateOrderNumber() {
    return 'RWA' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Generate ticket number
function generateTicketNumber() {
    return 'TKT' . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// Format currency
function formatCurrency($amount) {
    return 'RWF ' . number_format($amount, 2);
}

// Calculate distance between two coordinates (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Earth radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    
    return $earthRadius * $c; // Distance in kilometers
}

// Calculate ETA based on distance
function calculateETA($distance_km, $same_city = false) {
    if ($same_city) {
        // Same city: same day or next day
        $hours = rand(12, 36); // 12-36 hours
    } else {
        // Different city: 2-5 days
        $hours = rand(48, 120); // 48-120 hours (2-5 days)
    }
    
    return $hours;
}

// Create notification
function createNotification($user_id, $type, $title, $message, $link = '') {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, link) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $type, $title, $message, $link);
    $stmt->execute();
    $stmt->close();
}

// Get all admin users
function getAllAdmins() {
    global $conn;
    
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active'");
    $stmt->execute();
    $result = $stmt->get_result();
    $admins = [];
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row['id'];
    }
    $stmt->close();
    
    return $admins;
}

// Notify all admins
function notifyAllAdmins($type, $title, $message, $link = '') {
    $admins = getAllAdmins();
    foreach ($admins as $admin_id) {
        createNotification($admin_id, $type, $title, $message, $link);
    }
}

// Log activity
function logActivity($user_id, $action, $description = '') {
    global $conn;
    
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("issss", $user_id, $action, $description, $ip_address, $user_agent);
    $stmt->execute();
    $stmt->close();
}

// Get unread notification count
function getUnreadNotificationCount($user_id) {
    global $conn;

    $admin_notification_filter = ($_SESSION['user_role'] ?? '') === 'admin' ? " AND type <> 'cart_item_added'" : '';
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0{$admin_notification_filter}");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();
    
    return $row['count'] ?? 0;
}

// Get profile picture URL
function getProfilePictureUrl($profile_picture) {
    if (!empty($profile_picture) && file_exists(__DIR__ . '/../uploads/profile/' . $profile_picture)) {
        return SITE_URL . '/uploads/profile/' . $profile_picture;
    }
    return SITE_URL . '/images/default-avatar.png';
}

// Get user profile picture
function getUserProfilePicture($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user = $result->fetch_assoc();
    $stmt->close();
    
    return getProfilePictureUrl($user['profile_picture'] ?? '');
}
?>
