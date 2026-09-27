<?php
$page_title = 'Products';
require_once 'config/config.php';
require_once 'includes/functions.php';

$category_id = isset($_GET['category']) ? (int)$_GET['category'] : 0;
$search = isset($_GET['search']) ? sanitize($_GET['search']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * ITEMS_PER_PAGE;

/*
|--------------------------------------------------------------------------
| Base condition
| - Active products
| - In stock
| - EXCLUDE admin user products
|--------------------------------------------------------------------------
*/
$where = "p.status = 'active' AND p.stock > 0 AND u.role != 'admin'";
$params = [];
$types = '';

if ($category_id > 0) {
    $where .= " AND p.category_id = ?";
    $params[] = $category_id;
    $types .= 'i';
}

if (!empty($search)) {
    $where .= " AND (p.name LIKE ? OR p.description LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $types .= 'ss';
}

$sql = "SELECT p.*, u.first_name, u.last_name, c.name AS category_name
        FROM products p
        JOIN users u ON p.seller_id = u.id
        JOIN categories c ON p.category_id = c.id
        WHERE $where
        ORDER BY p.created_at DESC
        LIMIT ? OFFSET ?";

$types .= 'ii';
$params[] = ITEMS_PER_PAGE;
$params[] = $offset;

$stmt = $conn->prepare($sql);
if (!empty($types)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}
$stmt->close();

/*
|--------------------------------------------------------------------------
| Total product count (for pagination)
|--------------------------------------------------------------------------
*/
$count_sql = "SELECT COUNT(*) AS total
              FROM products p
              JOIN users u ON p.seller_id = u.id
              WHERE $where";

$count_params = array_slice($params, 0, -2); // remove limit & offset
$count_types = substr($types, 0, -2);

$count_stmt = $conn->prepare($count_sql);
if (!empty($count_types)) {
    $count_stmt->bind_param($count_types, ...$count_params);
}
$count_stmt->execute();
$count_result = $count_stmt->get_result();

$total_products = $count_result->fetch_assoc()['total'];
$total_pages = ceil($total_products / ITEMS_PER_PAGE);
$count_stmt->close();

include 'includes/header.php';
?>

<div class="container my-4">
    <h2 class="section-title">Products</h2>

    <?php if (!empty($search)): ?>
        <p>Search results for: <strong><?php echo htmlspecialchars($search); ?></strong></p>
    <?php endif; ?>

    <div class="row">
        <?php if (empty($products)): ?>
            <div class="col-12">
                <p class="text-center">No products found.</p>
            </div>
        <?php else: ?>
            <?php foreach ($products as $product): ?>
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                    <div class="product-card">
                        <a href="product-details.php?id=<?php echo $product['id']; ?>">
                            <div class="product-image">
                                <?php if ($product['image']): ?>
                                    <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($product['image']); ?>" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <img src="<?php echo SITE_URL; ?>/images/imigongo1.jpg" alt="No image">
                                <?php endif; ?>
                            </div>
                            <div class="product-info">
                                <h5 class="product-name"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <p class="product-category"><?php echo htmlspecialchars($product['category_name']); ?></p>
                                <p class="product-price"><?php echo formatCurrency($product['price']); ?></p>

                                <?php if ($product['description']): ?>
                                    <p class="product-description">
                                        <?php echo htmlspecialchars(substr($product['description'], 0, 100)); ?>
                                        <?php echo strlen($product['description']) > 100 ? '...' : ''; ?>
                                    </p>
                                <?php endif; ?>

                                <p class="product-seller">
                                    By <?php echo htmlspecialchars($product['first_name'] . ' ' . $product['last_name']); ?>
                                </p>
                            </div>
                        </a>

                        <button class="btn btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                            <i class="fas fa-cart-plus"></i> Add to Cart
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($total_pages > 1): ?>
        <nav aria-label="Page navigation">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page - 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Previous
                        </a>
                    </li>
                <?php endif; ?>

                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $total_pages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=<?php echo $page + 1; ?><?php echo $category_id ? '&category=' . $category_id : ''; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            Next
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
