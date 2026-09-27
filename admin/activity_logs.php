<?php
$page_title = 'Activity Logs';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$stmt = $conn->query("SELECT al.*, u.first_name, u.last_name
                      FROM activity_logs al
                      LEFT JOIN users u ON al.user_id = u.id
                      ORDER BY al.created_at DESC
                      LIMIT 500");
$logs = [];
while ($row = $stmt->fetch_assoc()) {
    $logs[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Activity Logs</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <div class="dashboard-card">
        <?php if (empty($logs)): ?>
            <p class="mb-0">No activity yet.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>User</th>
                            <th>Action</th>
                            <th>Description</th>
                            <th>IP</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($logs as $log): ?>
                            <tr>
                                <td><?php echo date('M j, Y g:i A', strtotime($log['created_at'])); ?></td>
                                <td>
                                    <?php if (!empty($log['first_name'])): ?>
                                        <?php echo htmlspecialchars($log['first_name'] . ' ' . $log['last_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">System/Unknown</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($log['action']); ?></td>
                                <td><?php echo htmlspecialchars($log['description'] ?? ''); ?></td>
                                <td><?php echo htmlspecialchars($log['ip_address'] ?? ''); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

