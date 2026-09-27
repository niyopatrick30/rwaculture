<?php
$page_title = 'My Products';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireRole('seller');

$user_id = $_SESSION['user_id'];

// Get products
$stmt = $conn->prepare("SELECT p.*, c.name as category_name 
                        FROM products p 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.seller_id = ? 
                        ORDER BY p.created_at DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>My Products</h1>
        <a href="add-product.php" class="btn btn-primary">Add New Product</a>
    </div>
    
    <?php if (empty($products)): ?>
        <div class="dashboard-card text-center">
            <p>No products yet. <a href="add-product.php">Add your first product!</a></p>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($products as $product): ?>
                        <tr>
                            <td>
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($product['image']); ?>" 
                                         style="width: 60px; height: 60px; object-fit: cover;">
                                <?php else: ?>
                                    <span>No image</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($product['name']); ?></td>
                            <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                            <td><?php echo formatCurrency($product['price']); ?></td>
                            <td><?php echo $product['stock']; ?></td>
                            <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $product['status'])); ?></span></td>
                            <td>
                                <a href="edit-product.php?id=<?php echo $product['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php include '../includes/footer.php'; ?>
