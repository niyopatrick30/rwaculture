<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
requireLogin();

$order_id = (int)($_GET['order_id'] ?? 0);

$stmt = $conn->prepare("
SELECT o.*, u.first_name, u.last_name, pp.file_path, pp.id AS proof_id
FROM orders o
JOIN users u ON o.buyer_id = u.id
JOIN payment_proofs pp ON pp.order_id = o.id
WHERE o.id = ? AND o.seller_id = ?
");
$stmt->bind_param("ii",$order_id,$_SESSION['user_id']);
$stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
$stmt->close();

if(!$data) die('Unauthorized');

if($_SERVER['REQUEST_METHOD']==='POST'){
    if($_POST['action']=='confirm'){
        $status='confirmed';
        $msg="Your payment has been confirmed. Your order is now confirmed.";
    } else {
        $status='rejected';
        $msg="Your payment was rejected. Please contact the seller.";
    }

    // Update proof
    $stmt=$conn->prepare("UPDATE payment_proofs SET status=?, reviewed_at=NOW() WHERE id=?");
    $stmt->bind_param("si",$status,$data['proof_id']);
    $stmt->execute(); $stmt->close();

    // Notify buyer
    $title = "Payment Review";
    $link = "order-confirmation.php?order_id=".$order_id;

    $stmt=$conn->prepare("
        INSERT INTO notifications (user_id,type,title,message,link)
        VALUES (?,?,?,?,?)
    ");
    $stmt->bind_param("issss",$data['buyer_id'],'payment',$title,$msg,$link);
    $stmt->execute(); $stmt->close();
}
?>

<h2>Payment Review</h2>

<p><strong>Buyer:</strong> <?=$data['first_name'].' '.$data['last_name']?></p>

<p>
<a href="../uploads/payment_proofs/<?=$data['file_path']?>" target="_blank">
View Payment Proof
</a>
</p>

<form method="POST">
<button name="action" value="confirm" class="btn btn-primary">Confirm</button>
<button name="action" value="reject" class="btn btn-primary">Reject</button>
</form>
