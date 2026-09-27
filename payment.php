<?php
$page_title = 'Payment';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
if (!$order_id) { header('Location: index.php'); exit(); }

// Get order
$stmt = $conn->prepare("SELECT o.*, u.first_name AS seller_first_name, u.last_name AS seller_last_name,
                               sba.bank_name, sba.account_name, sba.account_number, sba.branch_name, sba.payment_instructions
                        FROM orders o
                        JOIN users u ON o.seller_id = u.id
                        LEFT JOIN seller_bank_accounts sba ON sba.seller_id = o.seller_id
                        WHERE o.id = ? AND o.buyer_id = ?");
$stmt->bind_param("ii", $order_id, $_SESSION['user_id']);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$order) { header('Location: index.php'); exit(); }

// Get order items
$stmt = $conn->prepare("SELECT oi.*, p.name, p.image FROM order_items oi 
JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->bind_param("i", $order_id);
$stmt->execute();
$order_items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += (float)$item['subtotal'];
}
$shipping = SHIPPING_FEE;
$tax = ($subtotal * TAX_RATE) / 100;
$total_due = $subtotal + $shipping + $tax;

// Get user
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

$error = '';
$success = '';

$estimatedDelivery = date('F j, Y, g:i A', strtotime('+2 hours'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($order['account_number'])) {
        $error = 'The seller has not configured a payment account yet. Please contact support.';
    } elseif (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== 0) {
        $error = 'Please upload a valid payment proof file.';
    } else {

        $uploadDir = 'uploads/payment_proofs/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $fileName = time().'_'.basename($_FILES['payment_proof']['name']);
        $filePath = $uploadDir.$fileName;
        $fileType = $_FILES['payment_proof']['type'];
        $fileSize = $_FILES['payment_proof']['size'];

        move_uploaded_file($_FILES['payment_proof']['tmp_name'], $filePath);

        // Save to payment_proofs table
        $stmt = $conn->prepare("
            INSERT INTO payment_proofs 
            (order_id, buyer_id, seller_id, file_path, file_type, file_size, status) 
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");
        $stmt->bind_param(
            "iiissi",
            $order_id,
            $_SESSION['user_id'],
            $order['seller_id'],
            $fileName,
            $fileType,
            $fileSize
        );
        $stmt->execute();
        $stmt->close();

        // Notify seller
        $title = "New Payment Proof";
        $msg = "A buyer uploaded payment proof for Order #{$order['order_number']}";
        $link = "seller/review-payment.php?order_id=".$order_id;

        $stmt = $conn->prepare("
            INSERT INTO notifications (user_id, type, title, message, link) 
            VALUES (?, 'payment', ?, ?, ?)
        ");
        $stmt->bind_param("isss", $order['seller_id'], $title, $msg, $link);
        $stmt->execute();
        $stmt->close();

        $success = "Payment proof uploaded successfully. Please wait for seller payment confirmation.";
    }
}

include 'includes/header.php';
?>

<style>
body {background:#f5f5f5;font-family:'Segoe UI',sans-serif;}
.container{max-width:1100px;}
.payment-card{border-radius:15px;box-shadow:0 8px 25px rgba(0,0,0,0.08);background:#fff;padding:30px;margin-bottom:30px;}
.order-summary{background:#f8f9fa;border-radius:12px;padding:20px;}
.order-summary h5{border-bottom:1px solid #ddd;padding-bottom:10px;margin-bottom:15px;}
.order-summary img{width:55px;height:55px;object-fit:cover;border-radius:8px;border:1px solid #ddd;}
.total-box{background:#000;color:#fff;padding:15px;border-radius:10px;font-weight:600;display:flex;justify-content:space-between;}
.btn-blue{background:#0d6efd;color:#fff;border-radius:30px;padding:12px;font-size:18px;font-weight:500;width:100%;}
.btn-blue:hover{background:#0b5ed7;color:#fff;}
.payment-credentials{background:#eef2f7;padding:10px;border-radius:8px;margin-top:10px;display:none;}
</style>

<div class="container my-5">
<div class="row justify-content-center">
<div class="col-md-6">
<div class="payment-card">

<h2 class="text-center mb-4">Upload Payment Proof</h2>

<?php if($error): ?><div class="alert alert-danger"><?=$error?></div><?php endif; ?>
<?php if($success): ?><div class="alert alert-success"><?=$success?></div><?php endif; ?>

<div class="order-summary mb-4">
<h5>Order #<?=htmlspecialchars($order['order_number'])?></h5>
<p><strong>Amount to pay:</strong> <?=formatCurrency($total_due)?></p>
<p><strong>Estimated Delivery:</strong> <?=$estimatedDelivery?></p>

<?php foreach($order_items as $item): ?>
<div class="d-flex align-items-center mb-2">
<img src="<?= SITE_URL ?>/images/<?=htmlspecialchars($item['image'] ?: 'imigongo1.jpg')?>">
<div class="ms-2 flex-grow-1">
<strong><?=htmlspecialchars($item['name'])?></strong><br>
<small>Quantity: <?=$item['quantity']?></small>
</div>
<span><?=formatCurrency($item['subtotal'])?></span>
</div>
<?php endforeach; ?>

<div class="alert alert-warning mt-3 mb-3" data-persistent="true">
    <h5>Pay the seller directly</h5>
    <p class="mb-2">Seller: <strong><?php echo htmlspecialchars($order['seller_first_name'] . ' ' . $order['seller_last_name']); ?></strong></p>
    <p class="mb-2">Send the total amount to the seller's registered account, then upload your payment proof.</p>
    <?php if (!empty($order['account_number'])): ?>
        <div><strong>Bank / Provider:</strong> <?php echo htmlspecialchars($order['bank_name']); ?></div>
        <div><strong>Registered name:</strong> <?php echo htmlspecialchars($order['account_name']); ?></div>
        <div><strong>Account / Phone:</strong> <?php echo htmlspecialchars($order['account_number']); ?></div>
        <?php if (!empty($order['branch_name'])): ?><div><strong>Branch:</strong> <?php echo htmlspecialchars($order['branch_name']); ?></div><?php endif; ?>
        <?php if (!empty($order['payment_instructions'])): ?><div class="mt-2"><strong>Seller instructions:</strong> <?php echo nl2br(htmlspecialchars($order['payment_instructions'])); ?></div><?php endif; ?>
    <?php else: ?>
        <p class="mb-0"><strong>This seller has not entered a bank or mobile-money account yet.</strong> Please contact the seller or support before paying.</p>
    <?php endif; ?>
</div>

<div class="total-box mt-3">
<div>
    <div>Subtotal: <?=formatCurrency($subtotal)?></div>
    <div>Shipping: <?=formatCurrency($shipping)?></div>
    <div>Tax (<?=TAX_RATE?>%): <?=formatCurrency($tax)?></div>
    <strong>Total to pay</strong>
</div>
<strong><?=formatCurrency($total_due)?></strong>
</div>
</div>

<?php if(!$success && !empty($order['account_number'])): ?>
<form method="POST" enctype="multipart/form-data">
    <label class="fw-bold">Upload Payment Proof (Any file format)</label>
    <input type="file" name="payment_proof" class="form-control mb-3" required>
    <button type="submit" class="btn btn-blue">Upload Payment Proof</button>
</form>
<?php else: ?>
<p class="text-center fw-bold text-primary">
⏳ Please wait while the seller confirms your payment.
</p>
<?php endif; ?>

</div>
</div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
