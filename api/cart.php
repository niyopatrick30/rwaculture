<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

$action = isset($_GET['action']) ? $_GET['action'] : '';
$user_id = isLoggedIn() ? $_SESSION['user_id'] : null;
$session_id = session_id();
session_write_close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $raw_input = file_get_contents('php://input');
    $data = json_decode($raw_input, true);
    
    // Fallback to POST if JSON decode fails
    if ($data === null && !empty($_POST)) {
        $data = $_POST;
    }
    
    $action = $data['action'] ?? '';
    
    if ($action === 'add') {
        $product_id = (int)($data['product_id'] ?? $_POST['product_id'] ?? 0);
        $quantity = (int)($data['quantity'] ?? $_POST['quantity'] ?? 1);
        
        if ($product_id > 0 && $quantity > 0) {
            // Check product exists and is available
            $stmt = $conn->prepare("SELECT id, stock, price, seller_id FROM products WHERE id = ? AND status = 'active' AND stock > 0");
            $stmt->bind_param("i", $product_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $product = $result->fetch_assoc();
                
                if ($quantity <= $product['stock']) {
                    if ($user_id) {
                        $seller_stmt = $conn->prepare("SELECT DISTINCT p.seller_id FROM cart c JOIN products p ON p.id = c.product_id WHERE c.user_id = ? LIMIT 2");
                        $seller_stmt->bind_param("i", $user_id);
                    } else {
                        $seller_stmt = $conn->prepare("SELECT DISTINCT p.seller_id FROM cart c JOIN products p ON p.id = c.product_id WHERE c.session_id = ? AND c.user_id IS NULL LIMIT 2");
                        $seller_stmt->bind_param("s", $session_id);
                    }
                    $seller_stmt->execute();
                    $seller_result = $seller_stmt->get_result();
                    $existing_seller_ids = [];
                    while ($seller_row = $seller_result->fetch_assoc()) {
                        $existing_seller_ids[] = (int)$seller_row['seller_id'];
                    }
                    $seller_stmt->close();

                    if (!empty($existing_seller_ids) && !in_array((int)$product['seller_id'], $existing_seller_ids, true)) {
                        echo json_encode([
                            'success' => false,
                            'message' => 'Your cart already contains products from another seller. Complete that order first, then shop from this seller.'
                        ]);
                        $stmt->close();
                        exit();
                    }

                    // Check if item already in cart
                    if ($user_id) {
                        $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE user_id = ? AND product_id = ?");
                        $check_stmt->bind_param("ii", $user_id, $product_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                    } else {
                        $check_stmt = $conn->prepare("SELECT id, quantity FROM cart WHERE session_id = ? AND product_id = ? AND user_id IS NULL");
                        $check_stmt->bind_param("si", $session_id, $product_id);
                        $check_stmt->execute();
                        $check_result = $check_stmt->get_result();
                    }
                    
                    if ($check_result->num_rows > 0) {
                        // Update quantity
                        $cart_item = $check_result->fetch_assoc();
                        $new_quantity = $cart_item['quantity'] + $quantity;
                        if ($new_quantity <= $product['stock']) {
                            $update_stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE id = ?");
                            $update_stmt->bind_param("ii", $new_quantity, $cart_item['id']);
                            $update_stmt->execute();
                            $update_stmt->close();
                        }
                    } else {
                        // Add new item
                        $insert_stmt = $conn->prepare("INSERT INTO cart (user_id, session_id, product_id, quantity) VALUES (?, ?, ?, ?)");
                        $insert_stmt->bind_param("isii", $user_id, $session_id, $product_id, $quantity);
                        $insert_stmt->execute();
                        $insert_stmt->close();
                    }
                    $check_stmt->close();
                    
                    echo json_encode(['success' => true, 'message' => 'Product added to cart']);
                } else {
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Product not found']);
            }
            $stmt->close();
        } else {
            echo json_encode(['success' => false, 'message' => 'Invalid product or quantity']);
        }
    } elseif ($action === 'remove') {
        $cart_id = (int)($data['cart_id'] ?? $_POST['cart_id'] ?? 0);
        
        if ($cart_id > 0) {
            if ($user_id) {
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
            } else {
                $stmt = $conn->prepare("DELETE FROM cart WHERE id = ? AND session_id = ? AND user_id IS NULL");
                $stmt->bind_param("is", $cart_id, $session_id);
            }
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        }
    } elseif ($action === 'update') {
        $cart_id = (int)($data['cart_id'] ?? $_POST['cart_id'] ?? 0);
        $quantity = (int)($data['quantity'] ?? $_POST['quantity'] ?? 1);
        
        if ($cart_id > 0 && $quantity > 0) {
            if ($user_id) {
                $stmt = $conn->prepare("UPDATE cart c JOIN products p ON p.id = c.product_id
                                        SET c.quantity = ?
                                        WHERE c.id = ? AND c.user_id = ? AND p.status = 'active' AND p.stock >= ?");
                $stmt->bind_param("iiii", $quantity, $cart_id, $user_id, $quantity);
            } else {
                $stmt = $conn->prepare("UPDATE cart c JOIN products p ON p.id = c.product_id
                                        SET c.quantity = ?
                                        WHERE c.id = ? AND c.session_id = ? AND c.user_id IS NULL AND p.status = 'active' AND p.stock >= ?");
                $stmt->bind_param("iisi", $quantity, $cart_id, $session_id, $quantity);
            }
            $stmt->execute();
            $stmt->close();
            echo json_encode(['success' => true]);
        }
    }
} elseif ($action === 'get_count') {
    if ($user_id) {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE session_id = ? AND user_id IS NULL");
        $stmt->bind_param("s", $session_id);
    }
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $count = $row['total'] ?? 0;
    $stmt->close();
    
    echo json_encode(['count' => (int)$count]);
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>
