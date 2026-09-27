<?php
ob_start();
$page_title = 'Checkout';
require_once 'config/config.php';
require_once 'includes/functions.php';
requireLogin();

$user_id = $_SESSION['user_id'];

// Fetch cart
$stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.image, p.stock, p.seller_id 
                        FROM cart c 
                        JOIN products p ON c.product_id = p.id 
                        WHERE c.user_id = ? AND p.status = 'active' AND p.stock > 0");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
$subtotal = 0;
$seller_id = null;
$cart_seller_ids = [];

while ($row = $result->fetch_assoc()) {
    if (!$seller_id) $seller_id = $row['seller_id'];
    $cart_seller_ids[(int)$row['seller_id']] = true;
    $row['subtotal'] = $row['price'] * $row['quantity'];
    $subtotal += $row['subtotal'];
    $cart_items[] = $row;
}
$stmt->close();
$shipping = SHIPPING_FEE;
$tax = ($subtotal * TAX_RATE) / 100;
$total = $subtotal + $shipping + $tax;

if (empty($cart_items)) {
    header('Location: cart.php');
    exit();
}

// User info
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';
$cart_has_multiple_sellers = count($cart_seller_ids) > 1;
if ($cart_has_multiple_sellers) {
    $error = 'Your cart contains products from multiple sellers. Please return to your cart and complete one seller order at a time.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = sanitize($_POST['full_name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $latitude = $_POST['latitude'] ?? null;
    $longitude = $_POST['longitude'] ?? null;
    $address = sanitize($_POST['address'] ?? '');
    $city = sanitize($_POST['city'] ?? '');

    $has_coordinates = is_numeric($latitude) && is_numeric($longitude);
    if ($cart_has_multiple_sellers) {
        $error = 'Please remove products from other sellers before continuing to payment.';
    } elseif (!$full_name || !$email || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$phone || strlen($address) < 8) {
        $error = "Please provide your full name, email, phone, and a complete shipping address.";
    } else {
        $order_number = generateOrderNumber();
        $commission = ($subtotal * COMMISSION_RATE) / 100;

        $conn->begin_transaction();
        try {
            $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("ssi", $email, $phone, $user_id);
            $stmt->execute();
            $stmt->close();

            if ($has_coordinates) {
                $stmt = $conn->prepare("INSERT INTO orders (order_number, buyer_id, seller_id, total_amount, commission, buyer_latitude, buyer_longitude, buyer_address, buyer_city)
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiiddsss", $order_number, $user_id, $seller_id, $total, $commission, $latitude, $longitude, $address, $city);
            } else {
                $stmt = $conn->prepare("INSERT INTO orders (order_number, buyer_id, seller_id, total_amount, commission, buyer_address, buyer_city)
                                        VALUES (?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("siiddss", $order_number, $user_id, $seller_id, $total, $commission, $address, $city);
            }
            $stmt->execute();
            $order_id = $stmt->insert_id;
            $stmt->close();

            $platform_amount = $total - $commission;
            $stmt = $conn->prepare("INSERT INTO seller_commissions (order_id, seller_id, commission_rate, commission_amount, platform_amount) VALUES (?, ?, ?, ?, ?)");
            $commission_rate = COMMISSION_RATE;
            $stmt->bind_param("iiddd", $order_id, $seller_id, $commission_rate, $commission, $platform_amount);
            $stmt->execute();
            $stmt->close();

            foreach ($cart_items as $item) {
                if ((int)$item['quantity'] > (int)$item['stock']) {
                    throw new RuntimeException("Insufficient stock for {$item['name']}.");
                }

                $stmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, subtotal) VALUES (?, ?, ?, ?, ?)");
                $stmt->bind_param("iiidd", $order_id, $item['product_id'], $item['quantity'], $item['price'], $item['subtotal']);
                $stmt->execute();
                $stmt->close();

                $new_stock = $item['stock'] - $item['quantity'];
                if ($new_stock === 0) {
                    $stmt = $conn->prepare("UPDATE products SET stock = 0, status = 'out_of_stock' WHERE id = ?");
                    $stmt->bind_param("i", $item['product_id']);
                } else {
                    $stmt = $conn->prepare("UPDATE products SET stock = ? WHERE id = ?");
                    $stmt->bind_param("ii", $new_stock, $item['product_id']);
                }
                $stmt->execute();
                $stmt->close();

                if ($new_stock === 0) {
                    createNotification(
                        (int)$item['seller_id'],
                        'product_out_of_stock',
                        'Product out of stock',
                        "{$item['name']} has sold out. Update the product to add stock or delete it.",
                        SITE_URL . '/seller/edit-product.php?id=' . (int)$item['product_id']
                    );
                }
            }

            $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $stmt->close();

            $conn->commit();
            echo "<script>window.location.href='payment.php?order_id={$order_id}';</script>";
            exit();
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Checkout failed. Please try again.";
        }
    }
}

include 'includes/header.php';
?>

<style>
body { background: #f5f5f5; font-family: 'Segoe UI', sans-serif; }
.container { max-width: 1100px; }
.section-title { font-weight: 700; color: #222; text-align: center; margin-bottom: 30px; }
.checkout-card { border-radius: 15px; box-shadow: 0 8px 25px rgba(0,0,0,0.08); background: #fff; padding: 30px; margin-bottom: 30px; }
.order-summary { background: #f8f9fa; border-radius: 12px; padding: 20px; }
.order-summary h5 { border-bottom: 1px solid #ddd; padding-bottom: 10px; margin-bottom: 15px; }
.order-summary img { width: 55px; height: 55px; object-fit: cover; border-radius: 8px; border: 1px solid #ddd; }
.total-box { background: #000; color: #fff; padding: 15px; border-radius: 10px; font-weight: 600; display: flex; justify-content: space-between; }
.form-control { border-radius: 10px; }
.btn-checkout { background: linear-gradient(135deg, #000, #333); color: #fff; border-radius: 30px; padding: 12px; font-size: 18px; font-weight: 500; }
.btn-checkout:hover { background: #111; color: #fff; }
</style>

<div class="container my-5">
    <h2 class="section-title">Secure Checkout</h2>

    <?php if($error): ?>
        <div class="alert alert-danger text-center"><?= $error ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Customer Info -->
        <div class="col-md-7">
            <div class="checkout-card">
                <h5 class="mb-3">Customer Information</h5>

                <form method="POST" id="checkoutForm">
                    <div class="mb-3">
                        <label>Full Name</label>
                        <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($user['first_name'].' '.$user['last_name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Email</label>
                        <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label>Phone</label>
                        <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone']) ?>" required>
                    </div>
                    <div class="border rounded p-3 mb-3 bg-light">
                        <h6 class="mb-2">Delivery Location</h6>
                        <p class="small text-muted mb-3">Enter the address where you want your order delivered.</p>
                        <div class="mb-3">
                            <label for="address" class="form-label">Full delivery address</label>
                            <textarea name="address" id="address" class="form-control" rows="3" placeholder="House number, street, sector, landmark" required></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="city" class="form-label">City or District</label>
                            <input type="text" name="city" id="city" class="form-control" placeholder="Example: Nyanza" required>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-checkout w-100 mt-3">
                        Proceed to Payment
                    </button>
                </form>
            </div>
        </div>

        <!-- Order Summary -->
        <div class="col-md-5">
            <div class="checkout-card order-summary">
                <h5>Order Summary</h5>

                <?php foreach($cart_items as $item): ?>
                    <div class="d-flex align-items-center mb-3 cart-item">
                        <img 
                            src="<?= SITE_URL ?>/images/<?= htmlspecialchars($item['image'] ?: 'imigongo1.jpg') ?>" 
                            alt="<?= htmlspecialchars($item['name']) ?>"
                        >
                        <div class="ms-3 flex-grow-1">
                            <strong><?= htmlspecialchars($item['name']) ?></strong><br>
                            <small>Qty: <?= $item['quantity'] ?> | Stock: <?= $item['stock'] ?></small>
                        </div>
                        <span><?= formatCurrency($item['subtotal']) ?></span>
                    </div>
                <?php endforeach; ?>

                <div class="total-box mt-3">
                    <span>Total</span>
                    <span><?= formatCurrency($total) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
<?php ob_end_flush(); ?>
