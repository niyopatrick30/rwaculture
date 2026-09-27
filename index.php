<?php
$page_title = 'Home';
require_once 'config/config.php';
require_once 'includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ================= LANGUAGE HANDLER ================= */
if (isset($_GET['lang'])) {
    $_SESSION['lang'] = $_GET['lang'];
}
$lang = $_SESSION['lang'] ?? 'en';
/* =================================================== */

// Get featured products
$featured_products = [];
$stmt = $conn->prepare("SELECT p.*, u.first_name, u.last_name, c.name as category_name 
                        FROM products p 
                        JOIN users u ON p.seller_id = u.id 
                        JOIN categories c ON p.category_id = c.id 
                        WHERE p.status = 'active' AND p.stock > 0 
                        ORDER BY p.created_at DESC 
                        LIMIT 12");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $featured_products[] = $row;
}
$stmt->close();

// Get categories
$categories = [];
$stmt = $conn->prepare("SELECT * FROM categories WHERE status = 'active' ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $categories[] = $row;
}
$stmt->close();

include 'includes/header.php';
?>

<!-- ================= LANGUAGE SWITCHER STYLE ================= -->
<style>
/* Language bar positioned under & next to search bar */
.language-switcher {
    display: flex;
    justify-content: flex-end;
    margin-top: 8px;
    margin-right: 25px;
}

.language-switcher select {
    padding: 6px 10px;
    border-radius: 6px;
    border: 1px solid #ccc;
    font-size: 14px;
}
</style>

<!-- 🌍 LANGUAGE SWITCHER -->
<div class="language-switcher">
    <form method="get">
        <select name="lang" onchange="this.form.submit()">
            <option value="en" <?= $lang == 'en' ? 'selected' : '' ?>>English</option>
            <option value="rw" <?= $lang == 'rw' ? 'selected' : '' ?>>Kinyarwanda</option>
            <option value="fr" <?= $lang == 'fr' ? 'selected' : '' ?>>Français</option>
        </select>
    </form>
</div>

<!-- ================= CEO MESSAGE STYLES ================= -->
<style>
.ceo-wrapper {
    background: #fff;
    border-radius: 8px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.08);
    margin-bottom: 40px;
    overflow: hidden;
}

.ceo-message-box {
    padding: 15px 20px;
    border-bottom: 1px solid #eee;
}

/* 🎬 SIDE-BY-SIDE VIDEOS */
.ceo-video {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 15px;
    padding: 15px;
    flex-wrap: wrap;
}

.ceo-video video {
    width: 28%;
    max-width: 520px;
    height: 540px;
    object-fit: cover;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .ceo-video {
        flex-direction: column;
    }

    .ceo-video video {
        width: 100%;
        height: 200px;
    }
}
</style>

<div class="container my-4">
    <!-- ================= CATEGORIES ================= -->
    <div class="categories-section mb-5">
        <h2 class="section-title">
            <?= $lang == 'rw' ? 'Gura Ukurikije Icyiciro' : ($lang == 'fr' ? 'Acheter par catégorie' : 'Shop by Category') ?>
        </h2>

        <div class="row">
            <?php foreach ($categories as $category): ?>
                <div class="col-md-2 col-sm-4 col-6 mb-3">
                    <a href="products.php?category=<?php echo $category['id']; ?>" class="category-card">
                        <div class="category-icon">
                            <i class="fas fa-folder"></i>
                        </div>
                        <h6><?php echo htmlspecialchars($category['name']); ?></h6>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ================= CEO MESSAGE ================= -->
    <div class="ceo-wrapper">
        <div class="ceo-message-box">
            <strong>
                <?= $lang == 'rw' ? 'Ubutumwa bwa CEO:' : ($lang == 'fr' ? 'Message du PDG :' : 'Message from our CEO:') ?>
            </strong><br>

            <?= $lang == 'rw'
                ? 'Murakaza neza ku isoko ryacu! Iyi platform yashyizweho mu guhuza abacuruzi bacu n’abaguzi bashaka ubuziranenge n’umuco.'
                : ($lang == 'fr'
                ? 'Bienvenue sur notre marché ! Cette plateforme relie les vendeurs locaux aux clients qui apprécient la qualité et la culture.'
                : 'Welcome to our marketplace! This platform was built to connect talented local sellers with customers who value quality and authenticity.') ?>
        </div>

        <div class="ceo-video">
            <video src="Advert1.mp4" autoplay muted loop controls></video>
            <video src="Advert2.mp4" autoplay muted loop controls></video>
        </div>
    </div>

    <!-- ================= FEATURED PRODUCTS ================= -->
    <div class="products-section">
        <h2 class="section-title">
            <?= $lang == 'rw' ? 'Ibicuruzwa byihariye' : ($lang == 'fr' ? 'Produits en vedette' : 'Featured Products') ?>
        </h2>

        <div class="row">
            <?php if (empty($featured_products)): ?>
                <div class="col-12">
                    <p class="text-center">No products available yet.</p>
                </div>
            <?php else: ?>
                <?php foreach ($featured_products as $product): ?>
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
                                        By <?php echo htmlspecialchars($product['first_name'].' '.$product['last_name']); ?>
                                    </p>
                                </div>
                            </a>

                            <button class="btn btn-add-cart" onclick="addToCart(<?php echo $product['id']; ?>)">
                                <i class="fas fa-cart-plus"></i>
                                <?= $lang == 'rw' ? 'Shyira mu gitebo' : ($lang == 'fr' ? 'Ajouter au panier' : 'Add to Cart') ?>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
