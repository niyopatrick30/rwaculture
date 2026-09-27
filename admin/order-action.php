<?php
require_once '../config/config.php';
require_once '../includes/functions.php';

// Only admin or seller can perform these actions
requireRole(['admin','seller']);

// Get JSON input
$data = json_decode(file_get_contents('php://input'), true);

if (!$data || !isset($data['order_id'], $data['action'])) {
    echo json_encode(['success'=>false,'message'=>'Invalid request.']);
    exit;
}

$order_id = (int)$data['order_id'];
$action = $data['action']; // 'approve' or 'reject'
$reason = isset($data['reason']) ? trim($data['reason']) : '';

// Fetch order info
$stmt = $conn->prepare("SELECT * FROM orders WHERE id=? LIMIT 1");
$stmt->bind_param("i",$order_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo json_encode(['success'=>false,'message'=>'Order not found.']);
    exit;
}

// Fetch payment proof (if exists)
$stmt = $conn->prepare("SELECT * FROM payment_proofs WHERE order_id=? LIMIT 1");
$stmt->bind_param("i",$order_id);
$stmt->execute();
$payment_proof = $stmt->get_result()->fetch_assoc();
$stmt->close();

try {
    $conn->begin_transaction();

    if ($action === 'approve') {
        // Approve payment proof if exists
        if ($payment_proof && $payment_proof['status']==='pending') {
            $stmt = $conn->prepare("UPDATE payment_proofs SET status='approved' WHERE id=?");
            $stmt->bind_param("i",$payment_proof['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status='payment_confirmed' WHERE id=?");
        $stmt->bind_param("i",$order_id);
        $stmt->execute();
        $stmt->close();

        // Send notification to buyer
        createNotification(
            $order['buyer_id'],
            'order_confirmed',
            'Order Confirmed',
            "Your order #{$order['order_number']} has been confirmed. <br>
             <a href='".SITE_URL."/order-confirmation.php?order_id={$order_id}' class='btn btn-primary btn-sm mt-2'>
             Continue Order</a>"
        );

        $conn->commit();
        echo json_encode(['success'=>true,'message'=>'Order confirmed successfully!']);
        exit;

    } elseif ($action === 'reject') {
        // Reject payment proof if exists
        if ($payment_proof && $payment_proof['status']==='pending') {
            $stmt = $conn->prepare("UPDATE payment_proofs SET status='rejected' WHERE id=?");
            $stmt->bind_param("i",$payment_proof['id']);
            $stmt->execute();
            $stmt->close();
        }

        // Update order status
        $stmt = $conn->prepare("UPDATE orders SET status='order_rejected', rejection_reason=? WHERE id=?");
        $stmt->bind_param("si",$reason,$order_id);
        $stmt->execute();
        $stmt->close();

        // Send notification to buyer
        $message = "Your order #{$order['order_number']} has been rejected.";
        if ($reason) $message .= " Reason: ".htmlspecialchars($reason);

        createNotification(
            $order['buyer_id'],
            'order_rejected',
            'Order Rejected',
            $message
        );

        $conn->commit();
        echo json_encode(['success'=>true,'message'=>'Order rejected successfully!']);
        exit;
    } else {
        throw new Exception('Invalid action.');
    }

} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['success'=>false,'message'=>'Error processing order: '.$e->getMessage()]);
    exit;
}
