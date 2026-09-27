<?php
$page_title = 'Live Chats';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$chat_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// List chats
$stmt = $conn->query("SELECT c.*, u.first_name, u.last_name,
                 s.first_name AS seller_first_name, s.last_name AS seller_last_name
              FROM chats c
              LEFT JOIN users u ON c.user_id = u.id
              LEFT JOIN users s ON c.seller_id = s.id
              ORDER BY c.updated_at DESC, c.created_at DESC
              LIMIT 200");
$chats = [];
while ($row = $stmt->fetch_assoc()) {
    $chats[] = $row;
}
$stmt->close();

$messages = [];
$chat = null;

if ($chat_id) {
    $stmt = $conn->prepare("SELECT c.*, u.first_name, u.last_name
                            FROM chats c
                            LEFT JOIN users u ON c.user_id = u.id
                            WHERE c.id = ?");
    $stmt->bind_param("i", $chat_id);
    $stmt->execute();
    $chat = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($chat) {
        $stmt = $conn->prepare("SELECT cm.*, u.first_name, u.last_name
                                FROM chat_messages cm
                                LEFT JOIN users u ON cm.user_id = u.id
                                WHERE cm.chat_id = ?
                                ORDER BY cm.created_at ASC");
        $stmt->bind_param("i", $chat_id);
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $messages[] = $row;
        }
        $stmt->close();
    }
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message']) && $chat_id) {
    $message = sanitize($_POST['message'] ?? '');
    if (empty($message)) {
        $error = 'Message is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO chat_messages (chat_id, user_id, message, is_admin) VALUES (?, ?, ?, 1)");
        $admin_id = (int)$_SESSION['user_id'];
        $stmt->bind_param("iis", $chat_id, $admin_id, $message);
        if ($stmt->execute()) {
            if (!empty($chat['user_id']) && !empty($chat['seller_id'])) {
                createNotification(
                    (int)$chat['user_id'],
                    'chat_message',
                    'New message from admin',
                    'Admin sent you a new live-chat message.',
                    SITE_URL . '/buyer/dashboard.php?open_chat=1&seller_id=' . (int)$chat['seller_id'] . '&chat_id=' . $chat_id
                );
            }
            $success = 'Message sent.';
            $stmt->close();
            header('Location: chats.php?id=' . $chat_id);
            exit();
        }
        $stmt->close();
        $error = 'Failed to send message.';
    }
}

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Live Chats</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="row">
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Chats</h3>
                <?php if (empty($chats)): ?>
                    <p class="mb-0">No chats.</p>
                <?php else: ?>
                    <div class="list-group">
                        <?php foreach ($chats as $c): ?>
                            <?php
                                $title = !empty($c['first_name']) ? ($c['first_name'] . ' ' . $c['last_name']) : ($c['guest_name'] ?: $c['guest_email'] ?: 'Guest');
                                $chat_type = !empty($c['seller_id']) ? 'Buyer to Seller: ' . ($c['seller_first_name'] . ' ' . $c['seller_last_name']) : 'Admin Support';
                            ?>
                            <a class="list-group-item list-group-item-action <?php echo ((int)$c['id'] === $chat_id) ? 'active' : ''; ?>"
                               href="chats.php?id=<?php echo (int)$c['id']; ?>">
                                <div class="d-flex justify-content-between">
                                    <strong><?php echo htmlspecialchars($title); ?></strong>
                                    <small><?php echo htmlspecialchars($c['status']); ?></small>
                                </div>
                                <small class="text-primary"><?php echo htmlspecialchars($chat_type); ?></small><br>
                                <small class="text-muted">Updated <?php echo date('M j, g:i A', strtotime($c['updated_at'])); ?></small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="col-md-8">
            <div class="dashboard-card">
                <h3>Conversation</h3>

                <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
                <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

                <?php if (!$chat): ?>
                    <p class="mb-0 text-muted">Select a chat to view messages.</p>
                <?php else: ?>
                    <div style="max-height: 420px; overflow-y: auto; background: var(--accent-light); padding: 12px; border: 2px solid var(--primary-black); border-radius: 8px;">
                        <?php if (empty($messages)): ?>
                            <p class="mb-0 text-muted">No messages yet.</p>
                        <?php else: ?>
                            <?php foreach ($messages as $m): ?>
                                <div class="mb-2 p-2" style="background: <?php echo $m['is_admin'] ? 'var(--primary-black)' : 'var(--primary-white)'; ?>; color: <?php echo $m['is_admin'] ? 'var(--primary-white)' : 'var(--primary-black)'; ?>; border-radius: 6px; border: 1px solid var(--accent-dark);">
                                    <div class="d-flex justify-content-between">
                                        <small>
                                            <?php echo $m['is_admin'] ? 'Admin' : htmlspecialchars(trim(($m['first_name'] ?? '') . ' ' . ($m['last_name'] ?? '')) ?: 'User/Guest'); ?>
                                        </small>
                                        <small><?php echo date('M j, g:i A', strtotime($m['created_at'])); ?></small>
                                    </div>
                                    <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <form method="POST" action="" class="mt-3">
                        <div class="mb-2">
                            <textarea class="form-control" name="message" rows="3" placeholder="Type a reply..."></textarea>
                        </div>
                        <button class="btn btn-primary" type="submit" name="send_message">Send</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

