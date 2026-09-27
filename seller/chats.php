<?php
$page_title = 'Buyer Chats';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');
$seller_id = (int)$_SESSION['user_id'];
$chat_id = (int)($_GET['id'] ?? 0);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $chat_id = (int)($_POST['chat_id'] ?? 0);
    $message = trim($_POST['message'] ?? '');
    if ($message === '') {
        $error = 'Message is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, user_id, message, is_admin) SELECT id, ?, ?, 0 FROM chats WHERE id = ? AND seller_id = ?");
        $stmt->bind_param('isii', $seller_id, $message, $chat_id, $seller_id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $stmt->close();
            $stmt = $conn->prepare("SELECT user_id FROM chats WHERE id = ? AND seller_id = ?");
            $stmt->bind_param('ii', $chat_id, $seller_id);
            $stmt->execute();
            $buyer = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            if ($buyer) {
                createNotification((int)$buyer['user_id'], 'chat_message', 'New message from seller', 'Your seller sent you a new live-chat message.', SITE_URL . '/buyer/order-details.php');
            }
            header('Location: chats.php?id=' . $chat_id);
            exit();
        }
        $stmt->close();
        $error = 'Unable to send this message.';
    }
}

$stmt = $conn->prepare("SELECT c.id, c.updated_at, u.first_name, u.last_name, u.email,
                               (SELECT cm.message FROM chat_messages cm WHERE cm.chat_id = c.id ORDER BY cm.created_at DESC LIMIT 1) AS last_message
                        FROM chats c
                        JOIN users u ON u.id = c.user_id
                        WHERE c.seller_id = ? AND c.status = 'active'
                        ORDER BY c.updated_at DESC");
$stmt->bind_param('i', $seller_id);
$stmt->execute();
$chats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$selected_chat = null;
$messages = [];
if ($chat_id > 0) {
    $stmt = $conn->prepare("SELECT c.id, u.first_name, u.last_name, u.email FROM chats c JOIN users u ON u.id = c.user_id WHERE c.id = ? AND c.seller_id = ?");
    $stmt->bind_param('ii', $chat_id, $seller_id);
    $stmt->execute();
    $selected_chat = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($selected_chat) {
        $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name FROM chat_messages cm LEFT JOIN users u ON u.id = cm.user_id WHERE cm.chat_id = ? ORDER BY cm.created_at ASC");
        $stmt->bind_param('i', $chat_id);
        $stmt->execute();
        $messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
    }
}

include '../includes/header.php';
?>
<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Buyer Chats</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>
    <?php if ($error): ?><div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div><?php endif; ?>
    <div class="row">
        <div class="col-md-4"><div class="dashboard-card"><h3>Buyers</h3><?php if (!$chats): ?><p class="text-muted">No buyer conversations yet.</p><?php else: ?><div class="list-group"><?php foreach ($chats as $chat): ?><a class="list-group-item list-group-item-action <?php echo $chat_id === (int)$chat['id'] ? 'active' : ''; ?>" href="chats.php?id=<?php echo (int)$chat['id']; ?>"><strong><?php echo htmlspecialchars($chat['first_name'] . ' ' . $chat['last_name']); ?></strong><br><small><?php echo htmlspecialchars($chat['last_message'] ?? 'No messages yet'); ?></small></a><?php endforeach; ?></div><?php endif; ?></div></div>
        <div class="col-md-8"><div class="dashboard-card"><h3><?php echo $selected_chat ? 'Chat with ' . htmlspecialchars($selected_chat['first_name'] . ' ' . $selected_chat['last_name']) : 'Select a buyer'; ?></h3><?php if ($selected_chat): ?><div class="border rounded p-3 mb-3" style="max-height:420px;overflow-y:auto;"><?php foreach ($messages as $message): ?><div class="mb-3"><strong><?php echo $message['user_id'] == $seller_id ? 'You' : 'Buyer'; ?></strong><p class="mb-1"><?php echo nl2br(htmlspecialchars($message['message'])); ?></p><small class="text-muted"><?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?></small></div><?php endforeach; ?></div><form method="POST"><input type="hidden" name="chat_id" value="<?php echo $chat_id; ?>"><textarea name="message" class="form-control mb-2" rows="3" placeholder="Write a message to this buyer" required></textarea><button type="submit" name="send_message" class="btn btn-primary">Send Message</button></form><?php else: ?><p class="text-muted">Select a buyer conversation to read and reply.</p><?php endif; ?></div></div>
    </div>
</div>
<?php include '../includes/footer.php'; ?>
