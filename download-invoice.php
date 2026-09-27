<?php
$page_title = 'Download Invoice';

require_once 'config/config.php';
require_once 'includes/functions.php';

requireBuyer(); // Ensure the user is logged in as buyer

// COMPANY COORDINATES (example)
$company_lat = -1.9441; // Kigali city center latitude
$company_lon = 30.0619; // Kigali city center longitude
$average_speed_kmh = 40; // average delivery speed in km/h

// Get order ID from GET
$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;
$user_id = $_SESSION['user_id'];

if (!$order_id) {
    header('Location: orders.php');
    exit();
}

// Get order info from orders table
$stmt = $conn->prepare("
    SELECT o.*, 
           u.first_name AS seller_first, u.last_name AS seller_last, u.email AS seller_email,
           b.first_name AS buyer_first, b.last_name AS buyer_last, b.email AS buyer_email, b.phone AS buyer_phone
    FROM orders o
    JOIN users u ON o.seller_id = u.id
    JOIN users b ON o.buyer_id = b.id
    WHERE o.id = ? AND o.buyer_id = ?
");
if (!$stmt) {
    die("SQL Error (Order Info): " . $conn->error);
}
$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$order) {
    echo "Order not found.";
    exit();
}

// Calculate ETA if lat/lon exists
$eta_hours = '';
if (!empty($order['buyer_latitude']) && !empty($order['buyer_longitude'])) {
    $latFrom = deg2rad($company_lat);
    $lonFrom = deg2rad($company_lon);
    $latTo = deg2rad($order['buyer_latitude']);
    $lonTo = deg2rad($order['buyer_longitude']);

    $earthRadius = 6371; // km
    $latDelta = $latTo - $latFrom;
    $lonDelta = $lonTo - $lonFrom;

    $angle = 2 * asin(sqrt(pow(sin($latDelta/2),2) + cos($latFrom)*cos($latTo)*pow(sin($lonDelta/2),2)));
    $distance_km = $angle * $earthRadius;

    $eta_hours = round($distance_km / $average_speed_kmh, 1);
}

// Get order items
$stmt = $conn->prepare("
    SELECT oi.*, p.name 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
if (!$stmt) {
    die("SQL Error (Order Items): " . $conn->error);
}
$stmt->bind_param("i", $order_id);
$stmt->execute();
$result = $stmt->get_result();
$order_items = [];
while ($row = $result->fetch_assoc()) {
    $order_items[] = $row;
}
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - Order #<?=htmlspecialchars($order['order_number'])?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
    <style>
        body { background: #9fc0e2; padding: 20px; }

        /* Invoice Box with black and white border */
        .invoice-box {
            background: #fffefefb;
            padding: 30px;
            border: 5px solid #000; /* Black border */
            box-shadow: 0 0 10px rgba(0,0,0,.15);
        }

        .company-logo { max-width: 150px; margin-bottom: 10px; }

        /* Banner class */
        .invoice-banner {
            display: block;
            margin: 0 auto 20px auto; /* Center top */
            max-width: 600px;
            height: auto;
        }

        .invoice-header { border-bottom: 1px solid #eee; margin-bottom: 20px; padding-bottom: 10px; }
        .invoice-footer { border-top: 1px solid #eee; margin-top: 20px; padding-top: 10px; font-size: 12px; color: #777; }
        .table th, .table td { vertical-align: middle; }
        #map { height: 200px; margin-top: 10px; }

        @page { size: A4 portrait; margin: 8mm; }

        @media print {
            html, body { width: 100%; margin: 0; padding: 0; background: #fff !important; }
            .container { width: 100% !important; max-width: none !important; margin: 0 !important; padding: 0 !important; }
            .invoice-box { padding: 5mm; border: 1px solid #000; box-shadow: none; background: #fff; }
            .invoice-banner { max-width: 100%; max-height: 18mm; margin-bottom: 4mm; object-fit: contain; }
            .company-logo { max-width: 28mm; max-height: 15mm; margin-bottom: 2mm; }
            .invoice-header { margin-bottom: 3mm; padding-bottom: 2mm; }
            .invoice-box h4 { margin-bottom: 2mm; font-size: 14pt; }
            .invoice-box h5 { margin-bottom: 1mm; font-size: 10pt; }
            .invoice-box p { margin-bottom: 2mm; font-size: 8pt; line-height: 1.25; }
            .invoice-box .table { margin-bottom: 2mm; font-size: 8pt; }
            .invoice-box .table th, .invoice-box .table td { padding: 1.5mm 2mm; }
            .invoice-footer { margin-top: 3mm; padding-top: 2mm; font-size: 8pt; }
            #map, .invoice-actions { display: none !important; }
            tr { break-inside: avoid; page-break-inside: avoid; }
        }
    </style>
</head>
<body>

<div class="container">
    <div class="invoice-box">
        <!-- Top Banner -->
        <img src="BURNER.png" alt="Banner" class="invoice-banner">

        <!-- Header -->
        <div class="row invoice-header">
            <div class="col-6">
                <img src="Rwaculture logoo.png" alt="Rwaculture Logo" class="company-logo">
                <h4>Rwaculture Company Ltd</h4>
                <p>
                    Email: info@rwaculture.com<br>
                    Phone: +250 790181071<br>
                    Website: www.rwaculture.com
                </p>
            </div>

            <div class="col-6 text-end">
                <h4>Invoice</h4>
                <p>
                    Order #: <?=htmlspecialchars($order['order_number'])?><br>
                    Date: <?=date('F j, Y', strtotime($order['created_at']))?><br>
                    Status: <?=ucfirst(str_replace('_',' ',$order['status']))?>
                </p>
            </div>
        </div>

        <!-- Buyer & Seller -->
        <div class="row mb-4">
            <div class="col-6">
                <h5>Buyer:</h5>
                <p>
                    <?=htmlspecialchars($order['buyer_first'].' '.$order['buyer_last'])?><br>
                    <?=htmlspecialchars($order['buyer_email'])?><br>
                    <?=htmlspecialchars($order['buyer_phone'])?><br>
                    <?php if(!empty($order['buyer_address'])) echo htmlspecialchars($order['buyer_address']).'<br>'; ?>
                    <?php if(!empty($order['buyer_city'])) echo htmlspecialchars($order['buyer_city']); ?>
                </p>
            </div>
            <div class="col-6 text-end">
                <h5>Seller:</h5>
                <p>
                    <?=htmlspecialchars($order['seller_first'].' '.$order['seller_last'])?><br>
                    <?=htmlspecialchars($order['seller_email'])?>
                </p>
            </div>
        </div>

        <!-- Items -->
        <div class="row">
            <div class="col-12">
                <table class="table table-bordered">
                    <thead class="table-light">
                        <tr>
                            <th>#</th>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($order_items as $i => $item): ?>
                        <tr>
                            <td><?=($i+1)?></td>
                            <td><?=htmlspecialchars($item['name'])?></td>
                            <td><?=$item['quantity']?></td>
                            <td><?=formatCurrency($item['price'])?></td>
                            <td><?=formatCurrency($item['subtotal'])?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">Total:</th>
                            <th><?=formatCurrency($order['total_amount'])?></th>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- Delivery Info & Map -->
        <div class="row mb-3">
            <div class="col-12">
                <h5>Delivery Information:</h5>
                <p>
                    <?php if(!empty($order['buyer_address'])) echo htmlspecialchars($order['buyer_address']).'<br>'; ?>
                    <?php if(!empty($order['buyer_city'])) echo htmlspecialchars($order['buyer_city']); ?>
                </p>
                <?php if ($eta_hours): ?>
                    <p>Estimated Delivery (based on distance): <?=$eta_hours?> hours</p>
                <?php endif; ?>
                <?php if (!empty($order['buyer_latitude']) && !empty($order['buyer_longitude'])): ?>
                    <div id="map"></div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Footer -->
        <div class="row invoice-footer">
            <div class="col-12 text-center">
                Thank you for your order! &mdash; Rwaculture Company Ltd
            </div>
        </div>

        <!-- Download Button -->
        <div class="row mt-3 invoice-actions">
            <div class="col-12 text-end">
                <button onclick="window.print()" class="btn btn-primary">Download Invoice</button>
            </div>
        </div>
    </div>
</div>

<?php if (!empty($order['buyer_latitude']) && !empty($order['buyer_longitude'])): ?>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var map = L.map('map').setView([<?=$order['buyer_latitude']?>, <?=$order['buyer_longitude']?>], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '© OpenStreetMap'
    }).addTo(map);
    L.marker([<?=$order['buyer_latitude']?>, <?=$order['buyer_longitude']?>])
        .addTo(map)
        .bindPopup('Delivery Location')
        .openPopup();
});
</script>
<?php endif; ?>

</body>
</html>
