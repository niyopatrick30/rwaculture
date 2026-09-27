<?php
$page_title = 'Manage Sellers';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_seller'])) {
    $seller_id = (int)($_POST['seller_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    $allowed_status = ['active', 'inactive', 'suspended'];

    if ($seller_id <= 0 || !in_array($status, $allowed_status, true)) {
        $error = 'Invalid request.';
    } else {
        $stmt = $conn->prepare("UPDATE users SET status = ? WHERE id = ? AND role = 'seller'");
        $stmt->bind_param("si", $status, $seller_id);
        if ($stmt->execute()) {
            $success = 'Seller updated.';
            logActivity($_SESSION['user_id'], 'admin_seller_update', "Seller #{$seller_id} status {$status}");
        } else {
            $error = 'Failed to update seller.';
        }
        $stmt->close();
    }
}

$stmt = $conn->query("SELECT id, email, first_name, last_name, phone, status, created_at
                      FROM users
                      WHERE role = 'seller'
                      ORDER BY created_at DESC");
$sellers = [];
while ($row = $stmt->fetch_assoc()) {
    $sellers[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Sellers</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="dashboard-card">
        <?php if (empty($sellers)): ?>
            <p class="mb-0">No sellers found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sellers as $s): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($s['first_name'] . ' ' . $s['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($s['email']); ?></td>
                                <td><?php echo htmlspecialchars($s['phone'] ?? ''); ?></td>
                                <td><span class="badge bg-warning"><?php echo htmlspecialchars($s['status']); ?></span></td>
                                <td><?php echo date('M j, Y', strtotime($s['created_at'])); ?></td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="seller_id" value="<?php echo (int)$s['id']; ?>">
                                        <select class="form-control" name="status" style="width: 160px;">
                                            <?php foreach (['active','inactive','suspended'] as $st): ?>
                                                <option value="<?php echo $st; ?>" <?php echo $s['status'] === $st ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst($st); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-sm btn-primary" type="submit" name="update_seller">Save</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

