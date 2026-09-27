<?php
$page_title = 'Ticket Details';
require_once '../config/config.php';
require_once '../includes/functions.php';

$ticket_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$ticket_id) {
    header('Location: tickets.php');
    exit();
}

$is_admin = isLoggedIn() && checkRole('admin');
$user_id = isLoggedIn() ? (int)$_SESSION['user_id'] : null;

// Load ticket (admin sees all; user sees own)
if ($is_admin) {
    $stmt = $conn->prepare("SELECT st.*, u.first_name, u.last_name, u.email AS user_email
                            FROM support_tickets st
                            LEFT JOIN users u ON st.user_id = u.id
                            WHERE st.id = ?");
    $stmt->bind_param("i", $ticket_id);
} else {
    if (!$user_id) {
        header('Location: tickets.php');
        exit();
    }
    $stmt = $conn->prepare("SELECT st.*, u.first_name, u.last_name, u.email AS user_email
                            FROM support_tickets st
                            LEFT JOIN users u ON st.user_id = u.id
                            WHERE st.id = ? AND st.user_id = ?");
    $stmt->bind_param("ii", $ticket_id, $user_id);
}
$stmt->execute();
$ticket = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$ticket) {
    header('Location: tickets.php');
    exit();
}

$error = '';
$success = '';

// Post message
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_message'])) {
    $message = sanitize($_POST['message'] ?? '');
    if (empty($message)) {
        $error = 'Message is required.';
    } else {
        $stmt = $conn->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, ?)");
        $is_admin_flag = $is_admin ? 1 : 0;
        $stmt->bind_param("iisi", $ticket_id, $user_id, $message, $is_admin_flag);
        if ($stmt->execute()) {
            $success = 'Message sent.';
            $stmt->close();

            // If admin replied, notify user
            if ($is_admin && !empty($ticket['user_id'])) {
                createNotification((int)$ticket['user_id'], 'ticket_reply', 'Support Reply', "Your support ticket #{$ticket['ticket_number']} received a reply.");
            }
            header('Location: ticket-details.php?id=' . $ticket_id);
            exit();
        }
        $stmt->close();
        $error = 'Failed to send message.';
    }
}

// Admin update ticket status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ticket']) && $is_admin) {
    $status = sanitize($_POST['status'] ?? $ticket['status']);
    $priority = sanitize($_POST['priority'] ?? $ticket['priority']);
    $allowed_status = ['open', 'in_progress', 'resolved', 'closed'];
    $allowed_priority = ['low', 'medium', 'high', 'urgent'];

    if (!in_array($status, $allowed_status, true) || !in_array($priority, $allowed_priority, true)) {
        $error = 'Invalid ticket update.';
    } else {
        $stmt = $conn->prepare("UPDATE support_tickets SET status = ?, priority = ? WHERE id = ?");
        $stmt->bind_param("ssi", $status, $priority, $ticket_id);
        if ($stmt->execute()) {
            logActivity($_SESSION['user_id'], 'ticket_updated', "Ticket #{$ticket['ticket_number']} updated");
            $stmt->close();
            header('Location: ticket-details.php?id=' . $ticket_id);
            exit();
        }
        $stmt->close();
        $error = 'Failed to update ticket.';
    }
}

// Messages
$stmt = $conn->prepare("SELECT sm.*, u.first_name, u.last_name
                        FROM support_messages sm
                        LEFT JOIN users u ON sm.user_id = u.id
                        WHERE sm.ticket_id = ?
                        ORDER BY sm.created_at ASC");
$stmt->bind_param("i", $ticket_id);
$stmt->execute();
$result = $stmt->get_result();
$messages = [];
while ($row = $result->fetch_assoc()) {
    $messages[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Ticket #<?php echo htmlspecialchars($ticket['ticket_number']); ?></h1>
        <a href="tickets.php" class="btn btn-outline-primary">Back to Tickets</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-card mb-3">
                <h3><?php echo htmlspecialchars($ticket['subject']); ?></h3>
                <p class="mb-1"><strong>Status:</strong> <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span></p>
                <p class="mb-1"><strong>Priority:</strong> <span class="badge bg-warning"><?php echo ucfirst($ticket['priority']); ?></span></p>
                <p class="mb-0"><strong>Created:</strong> <?php echo date('M j, Y g:i A', strtotime($ticket['created_at'])); ?></p>
            </div>

            <div class="dashboard-card">
                <h3>Messages</h3>
                <div style="max-height: 420px; overflow-y: auto; background: var(--accent-light); padding: 12px; border: 2px solid var(--primary-black); border-radius: 8px;">
                    <?php if (empty($messages)): ?>
                        <p class="mb-0 text-muted">No messages yet.</p>
                    <?php else: ?>
                        <?php foreach ($messages as $m): ?>
                            <div class="mb-2 p-2" style="background: <?php echo $m['is_admin'] ? 'var(--primary-black)' : 'var(--primary-white)'; ?>; color: <?php echo $m['is_admin'] ? 'var(--primary-white)' : 'var(--primary-black)'; ?>; border-radius: 6px; border: 1px solid var(--accent-dark);">
                                <div class="d-flex justify-content-between">
                                    <small><?php echo $m['is_admin'] ? 'Support (Admin)' : 'You'; ?></small>
                                    <small><?php echo date('M j, g:i A', strtotime($m['created_at'])); ?></small>
                                </div>
                                <div><?php echo nl2br(htmlspecialchars($m['message'])); ?></div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <form method="POST" class="mt-3">
                    <div class="mb-2">
                        <textarea class="form-control" name="message" rows="3" placeholder="Type your message..."></textarea>
                    </div>
                    <button class="btn btn-primary" type="submit" name="send_message">Send</button>
                </form>
            </div>
        </div>

        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Ticket Owner</h3>
                <?php if (!empty($ticket['user_id'])): ?>
                    <p class="mb-1"><strong>User:</strong> <?php echo htmlspecialchars(($ticket['first_name'] ?? '') . ' ' . ($ticket['last_name'] ?? '')); ?></p>
                    <p class="mb-0"><strong>Email:</strong> <?php echo htmlspecialchars($ticket['user_email'] ?? ''); ?></p>
                <?php else: ?>
                    <p class="mb-0"><strong>Guest Email:</strong> <?php echo htmlspecialchars($ticket['guest_email'] ?? ''); ?></p>
                <?php endif; ?>
            </div>

            <?php if ($is_admin): ?>
                <div class="dashboard-card mt-3">
                    <h3>Admin Actions</h3>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-control" name="status">
                                <?php foreach (['open','in_progress','resolved','closed'] as $st): ?>
                                    <option value="<?php echo $st; ?>" <?php echo $ticket['status'] === $st ? 'selected' : ''; ?>>
                                        <?php echo ucfirst(str_replace('_', ' ', $st)); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Priority</label>
                            <select class="form-control" name="priority">
                                <?php foreach (['low','medium','high','urgent'] as $p): ?>
                                    <option value="<?php echo $p; ?>" <?php echo $ticket['priority'] === $p ? 'selected' : ''; ?>>
                                        <?php echo ucfirst($p); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button class="btn btn-primary w-100" type="submit" name="update_ticket">Save</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

