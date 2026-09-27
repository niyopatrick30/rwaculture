<?php
// Main Configuration File
session_start();

// Site Configuration
define('SITE_NAME', 'Rwaculture');
$site_url = getenv('RWACULTURE_SITE_URL');
if (!$site_url) {
	$site_protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	$site_host = $_SERVER['HTTP_HOST'] ?? 'localhost';
	$site_path = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
	$site_path = $site_path === '.' ? '' : $site_path;
	$site_path = preg_replace('#/(admin|api|buyer|seller|support)$#', '', $site_path);
	$site_path = $site_path === '/' ? '' : rtrim($site_path, '/');
	$site_url = $site_protocol . '://' . $site_host . $site_path;
}
define('SITE_URL', rtrim($site_url, '/'));
define('SITE_EMAIL', 'info@rwaculture.com');

// Commission Rate (Platform commission percentage)
define('COMMISSION_RATE', 10.00); // 10%
define('SHIPPING_FEE', 2000.00);
define('TAX_RATE', 18.00);

// Delivery Settings
define('SAME_CITY_DELIVERY_HOURS', 24); // Same day or next day
define('DIFFERENT_CITY_DELIVERY_DAYS', 3); // 2-5 days average

// Pagination
define('ITEMS_PER_PAGE', 12);

// File Upload Settings
define('UPLOAD_DIR', 'images/');
define('MAX_FILE_SIZE', 10485760); // 10MB

// Timezone
date_default_timezone_set('Africa/Kigali');

// Include database connection
require_once __DIR__ . '/database.php';
?>
