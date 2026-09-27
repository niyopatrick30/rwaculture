<?php
// Database Configuration
$database_host = getenv('DB_HOST');
$database_port = getenv('DB_PORT');
$database_user = getenv('DB_USER');
$database_password = getenv('DB_PASS');
$database_name = getenv('DB_NAME');

define('DB_HOST', $database_host !== false && $database_host !== '' ? $database_host : '127.0.0.1');
define('DB_PORT', $database_port !== false && $database_port !== '' ? (int)$database_port : 3306);
define('DB_USER', $database_user !== false && $database_user !== '' ? $database_user : 'root');
define('DB_PASS', $database_password !== false ? $database_password : '');
define('DB_NAME', $database_name !== false && $database_name !== '' ? $database_name : 'rwaculture_db');

// Create database connection
$conn = mysqli_init();
$ssl_ca = getenv('DB_SSL_CA');
$connection_flags = 0;

if ($ssl_ca !== false && $ssl_ca !== '') {
    if (!is_readable($ssl_ca)) {
        die('Database TLS certificate is not readable.');
    }

    $conn->options(MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, true);
    $conn->ssl_set(null, null, $ssl_ca, null, null);
    $connection_flags = MYSQLI_CLIENT_SSL;
}

$connected = $conn->real_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT, null, $connection_flags);
if (!$connected) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>
