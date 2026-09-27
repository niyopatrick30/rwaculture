<?php
$page_title = 'Edit Product';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header('Location: products.php');
    exit();
}

// Load product (must belong to seller)
$stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
$stmt->bind_param("ii", $product_id, $user_id);
$stmt->execute();
$product = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: products.php');
    exit();
}

// Categories
$stmt = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = [];
while ($row = $stmt->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update';

    if ($action === 'delete') {
        $stmt = $conn->prepare("DELETE FROM products WHERE id = ? AND seller_id = ?");
        $stmt->bind_param("ii", $product_id, $user_id);
        if ($stmt->execute()) {
            logActivity($user_id, 'product_deleted', "Product #{$product_id} deleted");
            $stmt->close();
            header('Location: products.php');
            exit();
        }
        $stmt->close();
        $error = 'Failed to delete product.';
    } else {
        $name = sanitize($_POST['name'] ?? '');
        $description = sanitize($_POST['description'] ?? '');
        $price = (float)($_POST['price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
        $category_id = (int)($_POST['category_id'] ?? 0);
        $status = sanitize($_POST['status'] ?? 'active');

        if (empty($name) || empty($description) || $price <= 0 || $stock < 1 || $category_id <= 0) {
            $error = 'Please fill in all fields correctly. Stock must be at least 1.';
        } else {
            $image_name = $product['image'] ?? '';
            if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['image'];
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];

                if (in_array($ext, $allowed, true) && $file['size'] <= MAX_FILE_SIZE) {
                    $image_name = uniqid() . '.' . $ext;
                    $upload_path = __DIR__ . '/../images/' . $image_name;
                    move_uploaded_file($file['tmp_name'], $upload_path);
                }
            }

            // Stock rules: if 0 force out_of_stock
            if ($stock === 0) {
                $status = 'out_of_stock';
            }

            $stmt = $conn->prepare("UPDATE products
                                    SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ?, status = ?
                                    WHERE id = ? AND seller_id = ?");
            $stmt->bind_param("issdissii", $category_id, $name, $description, $price, $stock, $image_name, $status, $product_id, $user_id);
            if ($stmt->execute()) {
                logActivity($user_id, 'product_updated', "Product '{$name}' updated");
                $success = 'Product updated successfully.';
            } else {
                $error = 'Failed to update product.';
            }
            $stmt->close();

            // Refresh
            $stmt = $conn->prepare("SELECT * FROM products WHERE id = ? AND seller_id = ?");
            $stmt->bind_param("ii", $product_id, $user_id);
            $stmt->execute();
            $product = $stmt->get_result()->fetch_assoc();
            $stmt->close();
        }
    }
}

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Edit Product</h1>
        <a href="products.php" class="btn btn-outline-primary">Back to Products</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-card">
                <form method="POST" action="" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="update">

                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="name" name="name" required value="<?php echo htmlspecialchars($product['name']); ?>">
                    </div>

                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo (int)$category['id']; ?>" <?php echo ((int)$product['category_id'] === (int)$category['id']) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($category['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required><?php echo htmlspecialchars($product['description']); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Price (RWF)</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required value="<?php echo htmlspecialchars($product['price']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="0" required value="<?php echo (int)$product['stock']; ?>">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="status" class="form-label">Status</label>
                        <select class="form-control" id="status" name="status">
                            <option value="active" <?php echo $product['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                            <option value="inactive" <?php echo $product['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                            <option value="out_of_stock" <?php echo $product['status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of stock</option>
                        </select>
                        <small class="text-muted">If stock is 0, status will automatically become out of stock.</small>
                    </div>

                    <div class="mb-3">
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="text-muted">Upload a new image to replace the current one.</small>
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>

                <hr>
                <form method="POST" action="" onsubmit="return confirm('Delete this product? This cannot be undone.');">
                    <input type="hidden" name="action" value="delete">
                    <button type="submit" class="btn btn-danger">Delete Product</button>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Current Image</h3>
                <?php if (!empty($product['image'])): ?>
                    <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($product['image']); ?>" class="img-fluid" alt="Product image">
                <?php else: ?>
                    <img src="<?php echo SITE_URL; ?>/images/imigongo1.jpg" class="img-fluid" alt="No image">
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>

