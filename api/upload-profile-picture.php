<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'Please login first']);
    exit();
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    
    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'File upload error']);
        exit();
    }
    
    // Check file type - support multiple formats
    $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
    $file_type = mime_content_type($file['tmp_name']);
    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($file_type, $allowed_types) || !in_array($file_ext, $allowed_extensions)) {
        echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, GIF, WEBP, and BMP files are allowed']);
        exit();
    }
    
    // Check file size (max 10MB)
    $max_size = 10 * 1024 * 1024; // 10MB
    if ($file['size'] > $max_size) {
        echo json_encode(['success' => false, 'message' => 'File size must be less than 10MB']);
        exit();
    }
    
    // Create uploads/profile directory if it doesn't exist
    $upload_dir = __DIR__ . '/../uploads/profile/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    // Generate secure filename
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $filename = 'profile_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $ext;
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $filepath)) {
        // Get old profile picture
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();
        
        // Delete old profile picture if exists
        if ($user['profile_picture'] && file_exists(__DIR__ . '/../uploads/profile/' . $user['profile_picture'])) {
            unlink(__DIR__ . '/../uploads/profile/' . $user['profile_picture']);
        }
        
        // Update database
        $stmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
        $stmt->bind_param("si", $filename, $user_id);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'profile_picture_updated', 'Profile picture updated');
            echo json_encode([
                'success' => true, 
                'message' => 'Profile picture uploaded successfully',
                'filename' => $filename,
                'url' => SITE_URL . '/uploads/profile/' . $filename
            ]);
        } else {
            // Delete uploaded file if database update fails
            unlink($filepath);
            echo json_encode(['success' => false, 'message' => 'Failed to update database']);
        }
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'No file uploaded']);
}
?>
