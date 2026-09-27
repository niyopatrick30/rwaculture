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
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Set charset
$conn->set_charset("utf8mb4");
?>
