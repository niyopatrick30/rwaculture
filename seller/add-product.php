<?php
$page_title = 'Add Product';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];

// Get categories
$stmt = $conn->query("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$categories = [];
while ($row = $stmt->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = sanitize($_POST['name']);
    $description = sanitize($_POST['description']);
    $price = (float)$_POST['price'];
    $stock = (int)$_POST['stock'];
    $category_id = (int)$_POST['category_id'];
    
    if (empty($name) || empty($description) || $price <= 0 || $stock < 1 || $category_id <= 0) {
        $error = 'Please fill in all fields correctly. Stock must be at least 1.';
    } else {
        // Handle image upload
        $image_name = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'];
            
            if (in_array($ext, $allowed) && $file['size'] <= MAX_FILE_SIZE) {
                $image_name = uniqid() . '.' . $ext;
                $upload_path = __DIR__ . '/../images/' . $image_name;
                move_uploaded_file($file['tmp_name'], $upload_path);
            }
        }
        
        $stmt = $conn->prepare("INSERT INTO products (seller_id, category_id, name, description, price, stock, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
        $stmt->bind_param("iissdis", $user_id, $category_id, $name, $description, $price, $stock, $image_name);
        
        if ($stmt->execute()) {
            logActivity($user_id, 'product_added', "Product '{$name}' added");
            $success = 'Product added successfully!';
            header('Location: products.php');
            exit();
        } else {
            $error = 'Failed to add product';
        }
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="container my-4">
    <h1 class="mb-4">Add New Product</h1>
    
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
                    <div class="mb-3">
                        <label for="name" class="form-label">Product Name</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="category_id" class="form-label">Category</label>
                        <select class="form-control" id="category_id" name="category_id" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Description</label>
                        <textarea class="form-control" id="description" name="description" rows="5" required></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="price" class="form-label">Price (RWF)</label>
                            <input type="number" class="form-control" id="price" name="price" step="0.01" min="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="stock" class="form-label">Stock Quantity</label>
                            <input type="number" class="form-control" id="stock" name="stock" min="1" required>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="image" class="form-label">Product Image</label>
                        <input type="file" class="form-control" id="image" name="image" accept="image/*">
                        <small class="text-muted">Max size: 10MB. Formats: JPG, JPEG, PNG, GIF, WEBP, BMP</small>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Add Product</button>
                    <a href="products.php" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
