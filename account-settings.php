<?php
$page_title = 'Account Settings';
require_once 'config/config.php';
require_once 'includes/functions.php';

requireLogin();

$user_id = (int)$_SESSION['user_id'];
$error = '';
$success = '';

if (empty($_SESSION['account_settings_token'])) {
    $_SESSION['account_settings_token'] = bin2hex(random_bytes(32));
}

$stmt = $conn->prepare("SELECT email, phone, password FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    redirectTo('logout.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $csrf_token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['account_settings_token'], $csrf_token)) {
        $error = 'This form expired. Please refresh the page and try again.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($phone) > 30) {
        $error = 'Phone number must be 30 characters or fewer.';
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = 'Your current password is incorrect.';
    } elseif ($new_password !== '' && strlen($new_password) < 8) {
        $error = 'New password must be at least 8 characters.';
    } elseif ($new_password !== $confirm_password) {
        $error = 'New passwords do not match.';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1");
        $stmt->bind_param('si', $email, $user_id);
        $stmt->execute();
        $email_in_use = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if ($email_in_use) {
            $error = 'That email address is already in use.';
        } else {
            if ($new_password !== '') {
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ?, password = ? WHERE id = ?");
                $stmt->bind_param('sssi', $email, $phone, $password_hash, $user_id);
            } else {
                $stmt = $conn->prepare("UPDATE users SET email = ?, phone = ? WHERE id = ?");
                $stmt->bind_param('ssi', $email, $phone, $user_id);
            }

            if ($stmt->execute()) {
                $_SESSION['user_email'] = $email;
                $_SESSION['account_settings_token'] = bin2hex(random_bytes(32));
                logActivity($user_id, 'account_credentials_updated', 'User updated account credentials');
                $success = 'Account credentials updated.';
                $user['email'] = $email;
                $user['phone'] = $phone;
                if ($new_password !== '') {
                    $user['password'] = $password_hash;
                }
            } else {
                $error = 'Unable to update account credentials.';
            }
            $stmt->close();
        }
    }
}

include 'includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Account Settings</h1>
        <a href="index.php" class="btn btn-outline-primary">Back</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-lg-8">
            <div class="dashboard-card">
                <form method="POST" action="">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['account_settings_token']); ?>">

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="phone">Telephone</label>
                        <input class="form-control" type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" maxlength="30">
                    </div>

                    <hr>
                    <h2 class="h5">Change Password</h2>
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current password</label>
                        <input class="form-control" type="password" id="current_password" name="current_password" autocomplete="current-password" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New password <span class="text-muted">(leave blank to keep the current password)</span></label>
                        <input class="form-control" type="password" id="new_password" name="new_password" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm new password</label>
                        <input class="form-control" type="password" id="confirm_password" name="confirm_password" autocomplete="new-password">
                    </div>

                    <button class="btn btn-primary" type="submit">Save Credentials</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
