<?php
$page_title = 'Shopping Cart';
require_once 'config/config.php';
require_once 'includes/functions.php';

$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$session_id = session_id();

// Remove products that sellers have exhausted or disabled.
if ($user_id) {
    $stmt = $conn->prepare("DELETE c FROM cart c JOIN products p ON p.id = c.product_id
                            WHERE c.user_id = ? AND (p.status <> 'active' OR p.stock <= 0)");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("DELETE c FROM cart c JOIN products p ON p.id = c.product_id
                            WHERE c.session_id = ? AND c.user_id IS NULL AND (p.status <> 'active' OR p.stock <= 0)");
    $stmt->bind_param("s", $session_id);
}
$stmt->execute();
$stmt->close();

// Get cart items
if ($user_id) {
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.image, p.stock, p.seller_id,
                                   u.first_name AS seller_first_name, u.last_name AS seller_last_name
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            JOIN users u ON p.seller_id = u.id
                            WHERE c.user_id = ?");
    $stmt->bind_param("i", $user_id);
} else {
    $stmt = $conn->prepare("SELECT c.*, p.name, p.price, p.image, p.stock, p.seller_id,
                                   u.first_name AS seller_first_name, u.last_name AS seller_last_name
                            FROM cart c 
                            JOIN products p ON c.product_id = p.id 
                            JOIN users u ON p.seller_id = u.id
                            WHERE c.session_id = ? AND c.user_id IS NULL");
    $stmt->bind_param("s", $session_id);
}
$stmt->execute();
$result = $stmt->get_result();
$cart_items = [];
$total = 0;
$seller_ids = [];
$seller_names = [];

while ($row = $result->fetch_assoc()) {
    $subtotal = $row['price'] * $row['quantity'];
    $total += $subtotal;
    $row['subtotal'] = $subtotal;
    $cart_items[] = $row;
    $seller_ids[(int)$row['seller_id']] = true;
    $seller_names[(int)$row['seller_id']] = $row['seller_first_name'] . ' ' . $row['seller_last_name'];
}
$stmt->close();

include 'includes/header.php';
?>

<div class="container my-5">
    <h2 class="section-title">Shopping Cart</h2>
    
    <?php if (empty($cart_items)): ?>
        <div class="text-center my-5">
            <p>Your cart is empty.</p>
            <a href="index.php" class="btn btn-primary">Continue Shopping</a>
        </div>
    <?php else: ?>
        <?php if (count($seller_ids) > 1): ?>
            <div class="alert alert-warning">
                <strong>One seller per order:</strong> Your cart contains products from multiple sellers.
                Remove products until only one seller remains, then proceed to payment.
                <div class="small mt-2">Sellers in this cart: <?php echo htmlspecialchars(implode(', ', $seller_names)); ?></div>
            </div>
        <?php endif; ?>
        <div class="row">
            <div class="col-md-8">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <?php if ($item['image']): ?>
                                            <img src="<?php echo SITE_URL; ?>/images/<?php echo htmlspecialchars($item['image']); ?>" 
                                                 alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                 style="width: 60px; height: 60px; object-fit: cover; margin-right: 15px;">
                                        <?php endif; ?>
                                        <div>
                                            <strong><?php echo htmlspecialchars($item['name']); ?></strong>
                                        </div>
                                    </div>
                                </td>
                                <td><?php echo formatCurrency($item['price']); ?></td>
                                <td>
                                    <div class="quantity-controls d-flex align-items-center">
                                        <button class="btn btn-sm btn-outline-secondary quantity-decrease" data-cart-id="<?php echo $item['id']; ?>" data-quantity="<?php echo $item['quantity']; ?>">-</button>
                                        <input type="number" 
                                               class="form-control quantity-input text-center" 
                                               value="<?php echo $item['quantity']; ?>" 
                                               min="1" 
                                               max="<?php echo $item['stock']; ?>"
                                               data-cart-id="<?php echo $item['id']; ?>"
                                               style="width: 70px; margin: 0 5px;">
                                        <button class="btn btn-sm btn-outline-secondary quantity-increase" data-cart-id="<?php echo $item['id']; ?>" data-quantity="<?php echo $item['quantity']; ?>" data-max="<?php echo $item['stock']; ?>">+</button>
                                    </div>
                                </td>
                                <td><?php echo formatCurrency($item['subtotal']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-danger remove-item" data-cart-id="<?php echo $item['id']; ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="col-md-4">
                <div class="dashboard-card">
                    <h3>Order Summary</h3>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Subtotal:</span>
                        <span id="cartSubtotal"><?php echo formatCurrency($total); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Shipping:</span>
                        <span id="cartShipping"><?php echo formatCurrency(SHIPPING_FEE); ?></span>
                    </div>
                    <div class="d-flex justify-content-between mb-3">
                        <span>Tax (<?php echo TAX_RATE; ?>%):</span>
                        <span id="cartTax"><?php echo formatCurrency($total * TAX_RATE / 100); ?></span>
                    </div>
                    <hr>
                    <div class="d-flex justify-content-between mb-3">
                        <strong>Total:</strong>
                        <strong id="cartTotal"><?php echo formatCurrency($total + SHIPPING_FEE + ($total * TAX_RATE / 100)); ?></strong>
                    </div>
                    <?php if (isLoggedIn()): ?>
                        <a href="checkout.php" class="btn btn-primary w-100 <?php echo count($seller_ids) > 1 ? 'disabled' : ''; ?>" id="checkoutBtn" <?php echo count($seller_ids) > 1 ? 'aria-disabled="true" tabindex="-1"' : ''; ?>>Proceed to Checkout</a>
                    <?php else: ?>
                        <a href="login.php?redirect=checkout.php" class="btn btn-primary w-100">Login to Checkout</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const shipping = 2000;
    const taxRate = 0.18;
    
    function updateCartTotals() {
        let subtotal = 0;
        document.querySelectorAll('tbody tr').forEach(row => {
            const quantity = parseInt(row.querySelector('.quantity-input').value);
            const priceText = row.querySelector('td:nth-child(2)').textContent.replace(/[^\d.]/g, '');
            const price = parseFloat(priceText);
            const itemSubtotal = quantity * price;
            subtotal += itemSubtotal;
            row.querySelector('td:nth-child(4)').textContent = 'RWF ' + itemSubtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        });
        
        const tax = subtotal * taxRate;
        const total = subtotal + shipping + tax;
        
        document.getElementById('cartSubtotal').textContent = 'RWF ' + subtotal.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('cartTax').textContent = 'RWF ' + tax.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
        document.getElementById('cartTotal').textContent = 'RWF ' + total.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',');
    }
    
    function updateCartItem(cartId, quantity) {
        return fetch('api/cart.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                action: 'update',
                cart_id: cartId,
                quantity: quantity
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateCartTotals();
                updateCartCount();
                return true;
            }
            return false;
        });
    }
    
    // Quantity decrease
    document.querySelectorAll('.quantity-decrease').forEach(btn => {
        btn.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            const input = this.parentElement.querySelector('.quantity-input');
            let quantity = parseInt(input.value);
            if (quantity > 1) {
                quantity--;
                input.value = quantity;
                updateCartItem(cartId, quantity);
            }
        });
    });
    
    // Quantity increase
    document.querySelectorAll('.quantity-increase').forEach(btn => {
        btn.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            const max = parseInt(this.dataset.max);
            const input = this.parentElement.querySelector('.quantity-input');
            let quantity = parseInt(input.value);
            if (quantity < max) {
                quantity++;
                input.value = quantity;
                updateCartItem(cartId, quantity);
            } else {
                alert('Maximum stock available: ' + max);
            }
        });
    });
    
    // Update quantity on input change
    document.querySelectorAll('.quantity-input').forEach(input => {
        input.addEventListener('change', function() {
            const cartId = this.dataset.cartId;
            const max = parseInt(this.max);
            let quantity = parseInt(this.value);
            
            if (quantity < 1) quantity = 1;
            if (quantity > max) {
                quantity = max;
                alert('Maximum stock available: ' + max);
            }
            
            this.value = quantity;
            updateCartItem(cartId, quantity);
        });
    });
    
    // Remove item
    document.querySelectorAll('.remove-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const cartId = this.dataset.cartId;
            
            if (confirm('Remove this item from cart?')) {
                fetch('api/cart.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'remove',
                        cart_id: cartId
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
    });
    
    // Disable checkout if cart is empty
    const checkoutBtn = document.getElementById('checkoutBtn');
    if (checkoutBtn && document.querySelectorAll('tbody tr').length === 0) {
        checkoutBtn.disabled = true;
        checkoutBtn.classList.add('disabled');
    }
});
</script>

<?php include 'includes/footer.php'; ?>
