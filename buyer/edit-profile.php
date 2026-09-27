<?php
$page_title = 'Edit Profile';
require_once '../config/config.php';
require_once '../includes/functions.php';

requireBuyer();

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT id, email, first_name, last_name, phone, profile_picture FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    header('Location: dashboard.php');
    exit();
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $first_name = sanitize($_POST['first_name'] ?? '');
    $last_name = sanitize($_POST['last_name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($first_name) || empty($last_name)) {
        $error = 'First name and last name are required.';
    } elseif (!empty($new_password) && $new_password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        if (!empty($new_password)) {
            $hashed = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ?, password = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $first_name, $last_name, $phone, $hashed, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET first_name = ?, last_name = ?, phone = ? WHERE id = ?");
            $stmt->bind_param("sssi", $first_name, $last_name, $phone, $user_id);
        }

        if ($stmt->execute()) {
            $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
            logActivity($user_id, 'profile_updated', 'User updated profile');
            $success = 'Profile updated successfully.';
        } else {
            $error = 'Failed to update profile.';
        }
        $stmt->close();

        // Refresh data
        $stmt = $conn->prepare("SELECT id, email, first_name, last_name, phone, profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

include '../includes/header.php';
?>

<div class="container my-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="mb-0">Edit Profile</h1>
        <a href="dashboard.php" class="btn btn-outline-primary">Back to My Account</a>
    </div>

    <?php if ($error): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>
    <?php if ($success): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-4 mb-4">
            <div class="dashboard-card text-center">
                <h5>Profile Picture</h5>
                <div class="profile-picture-container mb-3">
                    <img id="profilePreview" 
                         src="<?php 
                            if (!empty($user['profile_picture']) && file_exists('../uploads/profile/' . $user['profile_picture'])) {
                                echo SITE_URL . '/uploads/profile/' . htmlspecialchars($user['profile_picture']);
                            } else {
                                echo SITE_URL . '/images/default-avatar.png';
                            }
                         ?>" 
                         alt="Profile Picture" 
                         class="profile-picture-preview"
                         onerror="this.src='<?php echo SITE_URL; ?>/images/default-avatar.png'">
                </div>
                <form id="profilePictureForm" enctype="multipart/form-data">
                    <input type="file" id="profilePictureInput" name="profile_picture" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp,image/bmp" style="display: none;">
                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('profilePictureInput').click()">
                        <i class="fas fa-camera"></i> Change Picture
                    </button>
                </form>
                <div id="uploadStatus" class="mt-2"></div>
            </div>
        </div>
        <div class="col-md-8">
            <div class="dashboard-card">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="first_name">First Name</label>
                            <input class="form-control" id="first_name" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="last_name">Last Name</label>
                            <input class="form-control" id="last_name" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="07XXXXXXXX">
                    </div>

                    <hr>
                    <h5>Change Password (optional)</h5>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" autocomplete="new-password">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" autocomplete="new-password">
                    </div>

                    <button class="btn btn-primary" type="submit">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
.profile-picture-preview {
    width: 150px;
    height: 150px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary-black);
    margin: 0 auto;
    display: block;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const profilePictureInput = document.getElementById('profilePictureInput');
    const profilePreview = document.getElementById('profilePreview');
    const uploadStatus = document.getElementById('uploadStatus');
    
    profilePictureInput.addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        
        // Validate file type - support multiple formats
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/bmp'];
        if (!allowedTypes.includes(file.type)) {
            uploadStatus.innerHTML = '<div class="alert alert-danger">Only JPG, JPEG, PNG, GIF, WEBP, and BMP files are allowed</div>';
            return;
        }
        
        // Validate file size (10MB)
        if (file.size > 10 * 1024 * 1024) {
            uploadStatus.innerHTML = '<div class="alert alert-danger">File size must be less than 10MB</div>';
            return;
        }
        
        // Preview image
        const reader = new FileReader();
        reader.onload = function(e) {
            profilePreview.src = e.target.result;
        };
        reader.readAsDataURL(file);
        
        // Upload file
        const formData = new FormData();
        formData.append('profile_picture', file);
        
        uploadStatus.innerHTML = '<div class="alert alert-info"><i class="fas fa-spinner fa-spin"></i> Uploading...</div>';
        
        fetch('../api/upload-profile-picture.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                uploadStatus.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> Profile picture updated successfully!</div>';
                profilePreview.src = data.url;
                setTimeout(() => {
                    uploadStatus.innerHTML = '';
                }, 3000);
            } else {
                uploadStatus.innerHTML = '<div class="alert alert-danger">' + data.message + '</div>';
            }
        })
        .catch(error => {
            uploadStatus.innerHTML = '<div class="alert alert-danger">An error occurred during upload</div>';
        });
    });
});
</script>

<?php include '../includes/footer.php'; ?>

