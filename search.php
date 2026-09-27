<?php
// Redirect to products page with search parameter
$search = isset($_GET['q']) ? $_GET['q'] : '';
header('Location: products.php?search=' . urlencode($search));
exit();
?>
