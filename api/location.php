<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/functions.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    $latitude = isset($data['latitude']) ? (float)$data['latitude'] : null;
    $longitude = isset($data['longitude']) ? (float)$data['longitude'] : null;
    $address = isset($data['address']) ? sanitize($data['address']) : '';
    $city = isset($data['city']) ? sanitize($data['city']) : '';
    
    if ($latitude && $longitude) {
        // Store in session for checkout
        $_SESSION['checkout_latitude'] = $latitude;
        $_SESSION['checkout_longitude'] = $longitude;
        $_SESSION['checkout_address'] = $address;
        $_SESSION['checkout_city'] = $city;
        
        echo json_encode([
            'success' => true,
            'message' => 'Location saved',
            'latitude' => $latitude,
            'longitude' => $longitude,
            'address' => $address,
            'city' => $city
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid location data']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>
