<?php
$page_title = 'Manage Products';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('admin');

$error = '';
$success = '';

// Update product status (activate/inactivate)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');

    $allowed = ['active', 'inactive', 'out_of_stock'];
    if ($product_id <= 0 || !in_array($status, $allowed, true)) {
        $error = 'Invalid request.';
    } else {
        $stmt = $conn->prepare("UPDATE products SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $status, $product_id);
        if ($stmt->execute()) {
            $success = 'Product status updated.';
            logActivity($_SESSION['user_id'], 'admin_product_status', "Product #{$product_id} set to {$status}");
        } else {
            $error = 'Failed to update product.';
        }
        $stmt->close();
    }
}

$stmt = $conn->query("SELECT p.*, c.name AS category_name, u.first_name, u.last_name
                      FROM products p
                      JOIN categories c ON p.category_id = c.id
                      JOIN users u ON p.seller_id = u.id
                      ORDER BY p.created_at DESC");
$products = [];
while ($row = $stmt->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Products</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to Dashboard</a>
    </div>

    <?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert alert-success"><?php echo $success; ?></div><?php endif; ?>

    <div class="dashboard-card">
        <?php if (empty($products)): ?>
            <p class="mb-0">No products found.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Seller</th>
                            <th>Category</th>
                            <th>Price</th>
                            <th>Stock</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $p): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['first_name'] . ' ' . $p['last_name']); ?></td>
                                <td><?php echo htmlspecialchars($p['category_name']); ?></td>
                                <td><?php echo formatCurrency($p['price']); ?></td>
                                <td><?php echo (int)$p['stock']; ?></td>
                                <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $p['status'])); ?></span></td>
                                <td>
                                    <form method="POST" class="d-flex gap-2">
                                        <input type="hidden" name="product_id" value="<?php echo (int)$p['id']; ?>">
                                        <select class="form-control" name="status" style="width: 160px;">
                                            <?php foreach (['active','inactive','out_of_stock'] as $s): ?>
                                                <option value="<?php echo $s; ?>" <?php echo $p['status'] === $s ? 'selected' : ''; ?>>
                                                    <?php echo ucfirst(str_replace('_', ' ', $s)); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="submit" name="update_status" class="btn btn-sm btn-primary">Save</button>
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

