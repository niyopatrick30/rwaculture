<?php
/**
 * Setup Script for Rwaculture
 * Run after importing the database to verify configuration and create a missing admin.
 */

require_once 'config/config.php';

echo "<h1>Rwaculture Setup</h1>";

// Check database connection
if ($conn->connect_error) {
    echo "<p style='color: red;'>Database connection failed: " . $conn->connect_error . "</p>";
    exit();
} else {
    echo "<p style='color: green;'>✓ Database connection successful</p>";
}

// Initialize the recovery admin only when no admin account exists.
$stmt = $conn->prepare("SELECT id, email FROM users WHERE role = 'admin' LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $admin = $result->fetch_assoc();
    echo "<p style='color: green;'>✓ Admin account exists (ID: {$admin['id']})</p>";
} else {
    $bootstrap = [
        'email' => getenv('RWACULTURE_ADMIN_EMAIL') ?: '',
        'phone' => getenv('RWACULTURE_ADMIN_PHONE') ?: '',
        'password' => getenv('RWACULTURE_ADMIN_PASSWORD') ?: '',
    ];
    $bootstrap_is_configured = $bootstrap['email'] !== '' && $bootstrap['password'] !== '';
    $bootstrap_file = __DIR__ . '/config/admin-bootstrap.php';

    if (!$bootstrap_is_configured && is_readable($bootstrap_file)) {
        $bootstrap = require $bootstrap_file;
        $bootstrap_is_configured = true;
    }

    if (!$bootstrap_is_configured) {
        echo "<p style='color: red;'>✗ Admin account is missing. Configure RWACULTURE_ADMIN_EMAIL and RWACULTURE_ADMIN_PASSWORD in the hosting environment, then run setup again.</p>";
    } else {
        $valid_bootstrap = is_array($bootstrap)
            && filter_var($bootstrap['email'] ?? '', FILTER_VALIDATE_EMAIL)
            && !empty($bootstrap['password'])
            && strlen($bootstrap['phone'] ?? '') <= 30;

        if (!$valid_bootstrap) {
            echo "<p style='color: red;'>✗ Admin bootstrap credentials are invalid.</p>";
        } else {
            $email = $bootstrap['email'];
            $phone = $bootstrap['phone'];
            $password_hash = password_hash($bootstrap['password'], PASSWORD_DEFAULT);
            $first_name = 'Admin';
            $last_name = 'User';
            $stmt = $conn->prepare("INSERT INTO users (email, password, first_name, last_name, phone, role, status) VALUES (?, ?, ?, ?, ?, 'admin', 'active')");
            $stmt->bind_param('sssss', $email, $password_hash, $first_name, $last_name, $phone);

            if ($stmt->execute()) {
                echo "<p style='color: green;'>✓ Recovery admin account created.</p>";
            } else {
                echo "<p style='color: red;'>✗ Could not create the recovery admin account.</p>";
            }
            $stmt->close();
        }
    }
}
$stmt->close();

// Check directories
$directories = ['images', 'css', 'js', 'api', 'admin', 'seller', 'buyer', 'support', 'config', 'includes'];
foreach ($directories as $dir) {
    if (is_dir($dir)) {
        echo "<p style='color: green;'>✓ Directory '{$dir}' exists</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Directory '{$dir}' missing</p>";
    }
}

// Check images directory is writable
if (is_writable('images')) {
    echo "<p style='color: green;'>✓ Images directory is writable</p>";
} else {
    echo "<p style='color: red;'>✗ Images directory is not writable. Please set permissions.</p>";
}

// Check config files
$config_files = ['config/config.php', 'config/database.php', 'config/maps.php', 'config/momo.php', 'config/admin-bootstrap.php'];
foreach ($config_files as $file) {
    if (file_exists($file)) {
        echo "<p style='color: green;'>✓ Config file '{$file}' exists</p>";
    } else {
        echo "<p style='color: red;'>✗ Config file '{$file}' missing</p>";
    }
}

echo "<hr>";
echo "<p><strong>Setup Complete!</strong></p>";
echo "<p><a href='index.php'>Go to Homepage</a> | <a href='login.php'>Login</a></p>";
?>
