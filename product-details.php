<?php
$page_title = 'Product Details';
require_once 'config/config.php';
require_once 'includes/functions.php';

$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$product_id) {
    header('Location: index.php');
    exit();
}

$stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name, u.email as seller_email, c.name as category_name 
                        FROM products p 
                        JOIN users u ON p.seller_id = u.id 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.id = ? AND p.status = 'active' AND p.stock > 0");
$stmt->bind_param("i", $product_id);
$stmt->execute();
$result = $stmt->get_result();
$product = $result->fetch_assoc();
$stmt->close();

if (!$product) {
    header('Location: index.php');
    exit();
}

// Get related products (same category, different product)
$related_products = [];
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name, c.name as category_name 
                        FROM products p 
                        JOIN users u ON p.seller_id = u.id 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' AND p.stock > 0 
                        ORDER BY RAND() 
                        LIMIT 4");
$stmt->bind_param("ii", $product['category_id'], $product_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $related_products[] = $row;
}
$stmt->close();

include 'includes/header.php';
?>

<div class="container my-5">
    <div class="row">
        <div class="col-md-6">
            <div class="product-image-large">
                <?php if ($product['image']): ?>
                    <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($product['image']); ?>" 
                         alt="<?php echo htmlspecialchars($product['name']); ?>" 
                         class="img-fluid">
                <?php else: ?>
                    <img src="<?php echo SITE_URL; ?>/images/imigongo1.jpg" alt="No image" class="img-fluid">
                <?php endif; ?>
            </div>
        </div>
        <div class="col-md-6">
            <h1><?php echo htmlspecialchars($product['name']); ?></h1>
            <p class="text-muted">Category: <?php echo htmlspecialchars($product['category_name']); ?></p>
            <h2 class="text-primary"><?php echo formatCurrency($product['price']); ?></h2>
            
            <div class="product-info-section my-4">
                <h4>Description</h4>
                <p><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>
            </div>
            
            <div class="product-info-section my-4">
                <p><strong>Stock:</strong> <?php echo $product['stock']; ?> available</p>
                <p><strong>Seller:</strong> <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?></p>
            </div>
            
            <?php if ($product['stock'] > 0): ?>
                <div class="quantity-selector mb-3">
                    <label for="quantity">Quantity:</label>
                    <input type="number" id="quantity" class="form-control" value="1" min="1" max="<?php echo $product['stock']; ?>" style="width: 100px; display: inline-block;">
                </div>
                <button class="btn btn-primary btn-lg" onclick="addToCart(<?php echo $product['id']; ?>, parseInt(document.getElementById('quantity').value))">
                    <i class="fas fa-cart-plus"></i> Add to Cart
                </button>
                <?php if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'buyer'): ?>
                    <button type="button" class="btn btn-outline-primary btn-lg ms-2" id="chatWithSeller">
                        <i class="fas fa-comments"></i> Chat with Seller
                    </button>
                <?php endif; ?>
            <?php else: ?>
                <button class="btn btn-secondary btn-lg" disabled>Out of Stock</button>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Related Products -->
    <?php if (!empty($related_products)): ?>
        <div class="row mt-5">
            <div class="col-12">
                <h3 class="section-title">Related Products</h3>
                <div class="row">
                    <?php foreach ($related_products as $related): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                            <div class="product-card">
                                <a href="product-details.php?id=<?php echo $related['id']; ?>">
                                    <div class="product-image">
                                        <?php if ($related['image']): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($related['image']); ?>" alt="<?php echo htmlspecialchars($related['name']); ?>">
                                        <?php else: ?>
                                            <img src="<?php echo SITE_URL; ?>/images/imigongo1.jpg" alt="No image">
                                        <?php endif; ?>
                                    </div>
                                    <div class="product-info">
                                        <h5 class="product-name"><?php echo htmlspecialchars($related['name']); ?></h5>
                                        <p class="product-category"><?php echo htmlspecialchars($related['category_name']); ?></p>
                                        <p class="product-price"><?php echo formatCurrency($related['price']); ?></p>
                                        <?php if ($related['description']): ?>
                                            <p class="product-description"><?php echo htmlspecialchars(substr($related['description'], 0, 80)); ?><?php echo strlen($related['description']) > 80 ? '...' : ''; ?></p>
                                        <?php endif; ?>
                                    </div>
                                </a>
                                <button class="btn btn-add-cart" onclick="addToCart(<?php echo $related['id']; ?>)">
                                    <i class="fas fa-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'buyer'): ?>
<script>
window.RWACULTURE_CHAT_SELLERS = [{id: <?php echo (int)$product['seller_id']; ?>, name: <?php echo json_encode($product['first_name'] . ' ' . $product['last_name']); ?>}];
document.getElementById('chatWithSeller')?.addEventListener('click', function() {
    const chatTarget = document.getElementById('chatTarget');
    if (chatTarget) {
        chatTarget.value = '<?php echo (int)$product['seller_id']; ?>';
        chatTarget.dispatchEvent(new Event('change'));
    }
    document.getElementById('chatToggle')?.click();
});
</script>
<?php endif; ?>

<?php include 'includes/footer.php'; ?>
