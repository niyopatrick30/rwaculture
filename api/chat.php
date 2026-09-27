<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : (isset($_POST['action']) ? $_POST['action'] : '');
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$guest_email = '';
if (!$user_id) {
    if (empty($_SESSION['guest_chat_email'])) {
        $_SESSION['guest_chat_email'] = 'guest-' . bin2hex(random_bytes(16)) . '@rwaculture.local';
    }
    $guest_email = $_SESSION['guest_chat_email'];
}

if ($action === 'get_chat') {
    $seller_id = (int)($_GET['seller_id'] ?? 0);

    // Direct buyer-to-seller chat; support chats continue using seller_id = NULL.
    if ($user_id) {
        $user_role = $_SESSION['user_role'] ?? '';
        if ($user_role === 'buyer' && $seller_id > 0) {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ? AND seller_id = ? AND status = 'active' ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("ii", $user_id, $seller_id);
        } elseif ($user_role === 'seller') {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ? AND seller_id IS NULL AND status = 'active' ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("i", $user_id);
        } else {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE user_id = ? AND seller_id IS NULL AND status = 'active' ORDER BY created_at DESC LIMIT 1");
            $stmt->bind_param("i", $user_id);
        }
    } else {
        $stmt = $conn->prepare("SELECT id FROM chats WHERE guest_email = ? AND seller_id IS NULL AND status = 'active' ORDER BY created_at DESC LIMIT 1");
        $stmt->bind_param("s", $guest_email);
    }
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $chat = $result->fetch_assoc();
        $chat_id = $chat['id'];
    } else {
        // Create new chat
        if ($user_id) {
            if (($_SESSION['user_role'] ?? '') === 'buyer' && $seller_id > 0) {
                $stmt = $conn->prepare("INSERT INTO chats (user_id, seller_id, status) VALUES (?, ?, 'active')");
                $stmt->bind_param("ii", $user_id, $seller_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO chats (user_id, status) VALUES (?, 'active')");
                $stmt->bind_param("i", $user_id);
            }
        } else {
            $guest_name = 'Guest';
            $stmt = $conn->prepare("INSERT INTO chats (guest_email, guest_name, status) VALUES (?, ?, 'active')");
            $stmt->bind_param("ss", $guest_email, $guest_name);
        }
        $stmt->execute();
        $chat_id = $stmt->insert_id;
        $stmt->close();
    }
    $stmt->close();
    
    // Get messages
    $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name 
                            FROM chat_messages cm 
                            LEFT JOIN users u ON cm.user_id = u.id 
                            WHERE cm.chat_id = ? 
                            ORDER BY cm.created_at ASC");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $row['user_name'] = $row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : 'Guest';
        $messages[] = $row;
    }
    $stmt->close();
    
    echo json_encode(['success' => true, 'chat_id' => $chat_id, 'messages' => $messages]);
    
} elseif ($action === 'send') {
    $chat_id = isset($_POST['chat_id']) ? (int)$_POST['chat_id'] : 0;
    $seller_id = (int)($_POST['seller_id'] ?? 0);
    $message = isset($_POST['message']) ? sanitize($_POST['message']) : '';
    
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit();
    }
    
    if ($chat_id > 0) {
        if (!$user_id) {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND guest_email = ? AND seller_id IS NULL");
            $stmt->bind_param("is", $chat_id, $guest_email);
        } elseif (($_SESSION['user_role'] ?? '') === 'seller') {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND (seller_id = ? OR (user_id = ? AND seller_id IS NULL))");
            $stmt->bind_param("iii", $chat_id, $user_id, $user_id);
        } elseif (($_SESSION['user_role'] ?? '') === 'buyer') {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ? AND user_id = ?");
            $stmt->bind_param("ii", $chat_id, $user_id);
        } else {
            $stmt = $conn->prepare("SELECT id FROM chats WHERE id = ?");
            $stmt->bind_param("i", $chat_id);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows === 0) {
            echo json_encode(['success' => false, 'message' => 'Chat not found']);
            exit();
        }
        $stmt->close();
    } else {
        // Create new chat
        if ($user_id) {
            if (($_SESSION['user_role'] ?? '') === 'buyer' && $seller_id > 0) {
                $stmt = $conn->prepare("INSERT INTO chats (user_id, seller_id, status) VALUES (?, ?, 'active')");
                $stmt->bind_param("ii", $user_id, $seller_id);
            } else {
                $stmt = $conn->prepare("INSERT INTO chats (user_id, status) VALUES (?, 'active')");
                $stmt->bind_param("i", $user_id);
            }
        } else {
            $guest_name = 'Guest';
            $stmt = $conn->prepare("INSERT INTO chats (guest_email, guest_name, status) VALUES (?, ?, 'active')");
            $stmt->bind_param("ss", $guest_email, $guest_name);
        }
        $stmt->execute();
        $chat_id = $stmt->insert_id;
        $stmt->close();
    }
    
    // Insert message
    $is_admin = checkRole('admin');
    $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, user_id, message, is_admin) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("iisi", $chat_id, $user_id, $message, $is_admin);
    $stmt->execute();
    $stmt->close();
    
    $chat_stmt = $conn->prepare("SELECT user_id, seller_id FROM chats WHERE id = ?");
    $chat_stmt->bind_param("i", $chat_id);
    $chat_stmt->execute();
    $chat_participants = $chat_stmt->get_result()->fetch_assoc();
    $chat_stmt->close();

    // Notify the other participant in direct chats, or an admin for support chats.
    if (!$is_admin) {
        if (!empty($chat_participants['seller_id'])) {
            $recipient_id = (int)$chat_participants['user_id'] === (int)$user_id
                ? (int)$chat_participants['seller_id']
                : (int)$chat_participants['user_id'];
            $sender_role = $_SESSION['user_role'] ?? '';
            $sender_label = $sender_role === 'seller' ? 'seller' : 'buyer';
            createNotification($recipient_id, 'chat_message', 'New message from ' . $sender_label, 'You have a new live-chat message.', $sender_role === 'seller' ? SITE_URL . '/buyer/orders.php' : SITE_URL . '/seller/chats.php');
        } else {
            $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' AND status = 'active' LIMIT 1");
            $admin_stmt->execute();
            $admin_result = $admin_stmt->get_result();
            if ($admin_result->num_rows > 0) {
                $admin = $admin_result->fetch_assoc();
                createNotification($admin['id'], 'new_chat', 'New Chat Message', 'You have a new message in the live chat.', SITE_URL . '/admin/chats.php?id=' . $chat_id);
            }
            $admin_stmt->close();
        }
    }
    
    echo json_encode(['success' => true, 'chat_id' => $chat_id]);
    
} elseif ($action === 'get_messages') {
    $chat_id = isset($_GET['chat_id']) ? (int)$_GET['chat_id'] : 0;
    
    if ($chat_id > 0) {
        if ($user_id && ($_SESSION['user_role'] ?? '') === 'seller') {
            $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name FROM chat_messages cm LEFT JOIN users u ON cm.user_id = u.id JOIN chats c ON c.id = cm.chat_id WHERE cm.chat_id = ? AND (c.seller_id = ? OR (c.user_id = ? AND c.seller_id IS NULL)) ORDER BY cm.created_at ASC");
            $stmt->bind_param("iii", $chat_id, $user_id, $user_id);
        } elseif ($user_id && ($_SESSION['user_role'] ?? '') === 'buyer') {
            $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name FROM chat_messages cm LEFT JOIN users u ON cm.user_id = u.id JOIN chats c ON c.id = cm.chat_id WHERE cm.chat_id = ? AND c.user_id = ? ORDER BY cm.created_at ASC");
            $stmt->bind_param("ii", $chat_id, $user_id);
        } elseif (checkRole('admin')) {
            $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name FROM chat_messages cm LEFT JOIN users u ON cm.user_id = u.id WHERE cm.chat_id = ? ORDER BY cm.created_at ASC");
            $stmt->bind_param("i", $chat_id);
        } elseif (!$user_id) {
            $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name FROM chat_messages cm JOIN chats c ON c.id = cm.chat_id WHERE cm.chat_id = ? AND c.guest_email = ? AND c.seller_id IS NULL ORDER BY cm.created_at ASC");
            $stmt->bind_param("is", $chat_id, $guest_email);
        } else {
            echo json_encode(['success' => false, 'message' => 'Login required']);
            exit();
        }
        $stmt->execute();
        $result = $stmt->get_result();
        $messages = [];
        while ($row = $result->fetch_assoc()) {
            $row['user_name'] = $row['first_name'] ? $row['first_name'] . ' ' . $row['last_name'] : 'Guest';
            $messages[] = $row;
        }
        $stmt->close();
        
        echo json_encode(['success' => true, 'messages' => $messages]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Chat ID required']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
