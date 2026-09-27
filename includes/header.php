<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/functions.php';

$unread_count = 0;
if (isLoggedIn()) {
    $unread_count = getUnreadNotificationCount($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . ' - ' : ''; ?>Rwaculture</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>/css/style.css">
</head>
<body>
    <!-- BURNER Banner on Top -->
    <div class="burner-banner">
        <img src="<?php echo SITE_URL; ?>/BURNER.png" alt="Rwaculture Company Ltd" class="img-fluid">
    </div>

    <!-- Main Header -->
    <header class="main-header">
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container-fluid">
                <!-- Logo -->
                <a class="navbar-brand" href="<?php echo SITE_URL; ?>/index.php">
                    <img src="<?php echo SITE_URL; ?>/Rwaculture logoo.png" alt="Rwaculture" class="logo-img">
                    <span class="logo-text">Rwaculture</span>
                </a>
                <!-- Global Search Bar -->
                <?php if (empty($hide_header_search) && empty($move_search_below_header)): ?>
                    <div class="search-container">
                        <form action="<?php echo SITE_URL; ?>/search.php" method="GET" class="search-form">
                            <input type="text" name="q" class="form-control search-input" placeholder="Search products..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                            <button type="submit" class="btn btn-search"><i class="fas fa-search"></i></button>
                        </form>
                    </div>
                <?php endif; ?>

                <!-- Right Side Menu -->
                <div class="header-right">
                    <!-- Cart Icon -->
                    <div class="cart-icon-wrapper">
                        <a href="<?php echo SITE_URL; ?>/cart.php" class="cart-icon">
                            <i class="fas fa-shopping-cart"></i>
                            <span class="cart-badge" id="cartCount" style="display: none;">0</span>
                        </a>
                    </div>
                    
                    <?php if (isLoggedIn()): ?>
                        <!-- Notifications -->
                        <div class="notification-icon-wrapper">
                            <a href="<?php echo SITE_URL; ?>/notifications.php" class="notification-icon">
                                <i class="fas fa-bell"></i>
                                <?php if ($unread_count > 0): ?>
                                    <span class="notification-badge"><?php echo $unread_count; ?></span>
                                <?php endif; ?>
                            </a>
                        </div>

                        <!-- User Menu Dropdown -->
                        <div class="dropdown">
                            <button class="btn btn-menu" type="button" id="userMenu" data-bs-toggle="dropdown">
                                <i class="fas fa-bars"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li class="dropdown-header d-flex align-items-center">
                                    <?php 
                                    $profile_pic = getUserProfilePicture($_SESSION['user_id']);
                                    ?>
                                    <img src="<?php echo $profile_pic; ?>" alt="Profile" class="header-profile-pic" onerror="this.src='<?php echo SITE_URL; ?>/images/default-avatar.png'">
                                    <span class="ms-2"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                                </li>
                                <li><hr class="dropdown-divider"></li>
                                
                                <?php if ($_SESSION['user_role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Dashboard</a></li>
                                <?php elseif ($_SESSION['user_role'] === 'seller'): ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/seller/dashboard.php"><i class="fas fa-store"></i> Seller Dashboard</a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/buyer/dashboard.php"><i class="fas fa-user"></i> My Account</a></li>
                                    <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/buyer/orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a></li>
                                <?php endif; ?>

                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/account-settings.php"><i class="fas fa-user-cog"></i> Account Settings</a></li>
                                
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/support/help.php"><i class="fas fa-question-circle"></i> Help Center</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                            </ul>
                        </div>
                    <?php else: ?>
                        <!-- Guest Menu -->
                        <div class="dropdown">
                            <button class="btn btn-menu" type="button" id="guestMenu" data-bs-toggle="dropdown">
                                <i class="fas fa-bars"></i>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Register</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/cart.php"><i class="fas fa-shopping-cart"></i> Cart</a></li>
                                <li><a class="dropdown-item" href="<?php echo SITE_URL; ?>/support/help.php"><i class="fas fa-question-circle"></i> Help Center</a></li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </nav>
    </header>

    <!-- Global Quick Links (available on all pages) -->
    <div class="global-quick-links">
        <div class="container-fluid">
            <a href="<?php echo SITE_URL; ?>/index.php"><i class="fas fa-home"></i> Home</a>
            <a href="<?php echo SITE_URL; ?>/products.php"><i class="fas fa-box"></i> Products</a>
            <a href="<?php echo SITE_URL; ?>/cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
            <a href="<?php echo SITE_URL; ?>/about.php"><i class="fas fa-info-circle"></i> About</a>
            <?php if (isLoggedIn()): ?>
                <?php if ($_SESSION['user_role'] === 'buyer'): ?>
                    <a href="<?php echo SITE_URL; ?>/buyer/dashboard.php"><i class="fas fa-user"></i> My Account</a>
                    <a href="<?php echo SITE_URL; ?>/buyer/orders.php"><i class="fas fa-shopping-bag"></i> My Orders</a>
                <?php elseif ($_SESSION['user_role'] === 'seller'): ?>
                    <a href="<?php echo SITE_URL; ?>/seller/dashboard.php"><i class="fas fa-store"></i> Seller Dashboard</a>
                    <a href="<?php echo SITE_URL; ?>/seller/products.php"><i class="fas fa-box-open"></i> My Products</a>
                <?php elseif ($_SESSION['user_role'] === 'admin'): ?>
                    <a href="<?php echo SITE_URL; ?>/admin/dashboard.php"><i class="fas fa-tachometer-alt"></i> Admin Panel</a>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?php echo SITE_URL; ?>/login.php"><i class="fas fa-sign-in-alt"></i> Login</a>
                <a href="<?php echo SITE_URL; ?>/register.php"><i class="fas fa-user-plus"></i> Register</a>
            <?php endif; ?>
            <a href="<?php echo SITE_URL; ?>/support/help.php"><i class="fas fa-question-circle"></i> Help</a>
            <a href="<?php echo SITE_URL; ?>/support/tickets.php"><i class="fas fa-ticket-alt"></i> Support</a>
        </div>
    </div>

    <?php if (!empty($move_search_below_header) && empty($hide_header_search)): ?>
        <div class="search-below-header">
            <div class="container-fluid">
                <form action="<?php echo SITE_URL; ?>/search.php" method="GET" class="search-form">
                    <input type="text" name="q" class="form-control search-input" placeholder="Search products..." value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                    <button type="submit" class="btn btn-search"><i class="fas fa-search"></i></button>
                </form>
            </div>
        </div>
    <?php endif; ?>

    <!-- Live Chat Button (Floating) -->
    <div class="chat-button-wrapper">
        <button class="chat-button" id="chatToggle">
            <i class="fas fa-comments"></i>
            <span>Talk To Us</span>
        </button>
    </div>

    <!-- Live Chat Window -->
    <div class="chat-window" id="chatWindow" style="display: none;">
        <div class="chat-header">
            <h5>Talk To Us</h5>
            <button class="btn-close-chat" id="chatClose"><i class="fas fa-times"></i></button>
        </div>
        <?php if (isLoggedIn() && ($_SESSION['user_role'] ?? '') === 'buyer'): ?>
            <div class="px-3 pt-2">
                <label for="chatTarget" class="small text-muted">Chat with</label>
                <select id="chatTarget" class="form-select form-select-sm">
                    <option value="admin">Admin Support</option>
                </select>
            </div>
        <?php endif; ?>
        <div class="chat-messages" id="chatMessages">
            <!-- Messages will be loaded here via AJAX -->
        </div>
        <div class="chat-input-wrapper">
            <input type="text" id="chatMessageInput" class="form-control" placeholder="Type your message...">
            <button class="btn btn-send" id="chatSend"><i class="fas fa-paper-plane"></i></button>
        </div>
    </div>
