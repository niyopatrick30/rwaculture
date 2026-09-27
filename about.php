<?php
$page_title = 'About Us';
require_once 'config/config.php';
require_once 'includes/functions.php';

include 'includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4">About Rwaculture</h1>
    
    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-card">
                <h3>Our Mission</h3>
                <p>Rwaculture is a multi-vendor e-commerce platform dedicated to promoting and preserving Rwandan cultural heritage through the sale of authentic traditional products and cultural tools.</p>
                
                <h3 class="mt-4">What We Offer</h3>
                <ul>
                    <li>Authentic Rwandan cultural products</li>
                    <li>Traditional tools and artifacts</li>
                    <li>Handcrafted items from local artisans</li>
                    <li>Secure online shopping experience</li>
                    <li>Fast and reliable delivery</li>
                </ul>
                
                <h3 class="mt-4">For Sellers</h3>
                <p>Join our platform to showcase and sell your Rwandan cultural products. We provide a secure marketplace with easy product management and order tracking.</p>
                
                <h3 class="mt-4">Contact Us</h3>
                <p>Email: <?php echo SITE_EMAIL; ?></p>
                <p>Phone: +250 782 207 396</p>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
