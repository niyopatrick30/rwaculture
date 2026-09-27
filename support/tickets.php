<?php
$page_title = 'Support Tickets';
require_once '../config/config.php';
require_once '../includes/functions.php';

$is_admin = isLoggedIn() && checkRole('admin');
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;

$error = '';
$success = '';

// Create new ticket
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_ticket'])) {
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    $priority = sanitize($_POST['priority']);
    
    if (empty($subject) || empty($message)) {
        $error = 'Please fill in all fields';
    } else {
        $ticket_number = generateTicketNumber();
        
        if ($user_id) {
            $stmt = $conn->prepare("INSERT INTO support_tickets (ticket_number, user_id, subject, status, priority) VALUES (?, ?, ?, 'open', ?)");
            $stmt->bind_param("siss", $ticket_number, $user_id, $subject, $priority);
        } else {
            $guest_email = sanitize($_POST['guest_email']);
            $stmt = $conn->prepare("INSERT INTO support_tickets (ticket_number, guest_email, subject, status, priority) VALUES (?, ?, ?, 'open', ?)");
            $stmt->bind_param("ssss", $ticket_number, $guest_email, $subject, $priority);
        }
        $stmt->execute();
        $ticket_id = $stmt->insert_id;
        $stmt->close();
        
        // Add first message
        $stmt = $conn->prepare("INSERT INTO support_messages (ticket_id, user_id, message, is_admin) VALUES (?, ?, ?, 0)");
        $stmt->bind_param("iis", $ticket_id, $user_id, $message);
        $stmt->execute();
        $stmt->close();
        
        // Notify admin
        $admin_stmt = $conn->prepare("SELECT id FROM users WHERE role = 'admin' LIMIT 1");
        $admin_stmt->execute();
        $admin_result = $admin_stmt->get_result();
        if ($admin_result->num_rows > 0) {
            $admin = $admin_result->fetch_assoc();
            createNotification($admin['id'], 'new_ticket', 'New Support Ticket', "New support ticket #{$ticket_number} created.");
        }
        $admin_stmt->close();
        
        $success = 'Support ticket created successfully!';
    }
}

// Get tickets
if ($is_admin) {
    $stmt = $conn->prepare("SELECT st.*, u.first_name, u.last_name 
                            FROM support_tickets st 
                            LEFT JOIN users u ON st.user_id = u.id 
                            ORDER BY st.created_at DESC");
} elseif ($user_id) {
    $stmt = $conn->prepare("SELECT st.*, u.first_name, u.last_name 
                            FROM support_tickets st 
                            LEFT JOIN users u ON st.user_id = u.id 
                            WHERE st.user_id = ? 
                            ORDER BY st.created_at DESC");
    $stmt->bind_param("i", $user_id);
} else {
    $tickets = [];
    $stmt = null;
}

if ($stmt) {
    $stmt->execute();
    $result = $stmt->get_result();
    $tickets = [];
    while ($row = $result->fetch_assoc()) {
        $tickets[] = $row;
    }
    $stmt->close();
} else {
    $tickets = [];
}

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4"><?php echo $is_admin ? 'Support Tickets' : 'My Support Tickets'; ?></h1>
    
    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <div class="row">
        <div class="col-md-<?php echo $is_admin ? '12' : '8'; ?>">
            <?php if (empty($tickets)): ?>
                <div class="dashboard-card">
                    <p>No support tickets yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Ticket Number</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($tickets as $ticket): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($ticket['ticket_number']); ?></td>
                                    <td><?php echo htmlspecialchars($ticket['subject']); ?></td>
                                    <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $ticket['status'])); ?></span></td>
                                    <td><span class="badge bg-warning"><?php echo ucfirst($ticket['priority']); ?></span></td>
                                    <td><?php echo date('M j, Y', strtotime($ticket['created_at'])); ?></td>
                                    <td><a href="ticket-details.php?id=<?php echo $ticket['id']; ?>" class="btn btn-sm btn-primary">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <?php if (!$is_admin): ?>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <h3>Create New Ticket</h3>
                    <form method="POST" action="">
                        <?php if (!isLoggedIn()): ?>
                            <div class="mb-3">
                                <label for="guest_email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="guest_email" name="guest_email" required>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Subject</label>
                            <input type="text" class="form-control" id="subject" name="subject" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="priority" class="form-label">Priority</label>
                            <select class="form-control" id="priority" name="priority" required>
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="5" required></textarea>
                        </div>
                        
                        <button type="submit" name="create_ticket" class="btn btn-primary w-100">Create Ticket</button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
