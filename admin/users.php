<?php
$page_title = 'Manage Users';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_user'])) {
    $target_id = (int)($_POST['user_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    $role = sanitize($_POST['role'] ?? 'buyer');

    $allowed_status = ['active', 'inactive', 'suspended'];
    $allowed_roles = ['buyer', 'seller', 'admin'];

    if ($target_id <= 0 || !in_array($status, $allowed_status, true) || !in_array($role, $allowed_roles, true)) {
        $error = 'Invalid update request.';
    } else {
        // Prevent self-lockout
        if ($target_id === (int)$_SESSION['user_id'] && $status !== 'active') {
            $error = 'You cannot deactivate your own admin account.';
        } else {
            $stmt = $conn->prepare("UPDATE users SET status = ?, role = ? WHERE id = ?");
            $stmt->bind_param("ssi", $status, $role, $target_id);
            if ($stmt->execute()) {
                $success = 'User updated.';
                logActivity($_SESSION['user_id'], 'admin_user_update', "User #{$target_id} set to {$role}/{$status}");
            } else {
                $error = 'Failed to update user.';
            }
            $stmt->close();
        }
    }
}

$stmt = $conn->query("SELECT id, email, first_name, last_name, phone, role, status, created_at FROM users ORDER BY created_at DESC");
$users = [];
while ($row = $stmt->fetch_assoc()) {
    $users[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Users</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="dashboard-card">
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></td>
                            <td><?php echo htmlspecialchars($u['email']); ?></td>
                            <td><?php echo htmlspecialchars($u['phone'] ?? ''); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($u['role']); ?></span></td>
                            <td><span class="badge bg-warning"><?php echo htmlspecialchars($u['status']); ?></span></td>
                            <td><?php echo date('M j, Y', strtotime($u['created_at'])); ?></td>
                            <td>
                                <form method="POST" class="d-flex gap-2">
                                    <input type="hidden" name="user_id" value="<?php echo (int)$u['id']; ?>">
                                    <select class="form-control" name="role" style="width: 120px;">
                                        <?php foreach (['buyer','seller','admin'] as $r): ?>
                                            <option value="<?php echo $r; ?>" <?php echo $u['role'] === $r ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($r); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select class="form-control" name="status" style="width: 140px;">
                                        <?php foreach (['active','inactive','suspended'] as $s): ?>
                                            <option value="<?php echo $s; ?>" <?php echo $u['status'] === $s ? 'selected' : ''; ?>>
                                                <?php echo ucfirst($s); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button type="submit" name="update_user" class="btn btn-sm btn-primary">Save</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

