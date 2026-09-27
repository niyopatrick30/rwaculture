<?php
$page_title = 'Help Center';
require_once '../config/config.php';
require_once '../includes/functions.php';

include '../includes/header.php';
?>

<div class="container my-5">
    <h1 class="mb-4">Help Center</h1>
    
    <div class="row">
        <div class="col-md-8">
            <div class="dashboard-card mb-4">
                <h3>Frequently Asked Questions (FAQs)</h3>
                
                <div class="accordion" id="faqAccordion">
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                How do I place an order?
                            </button>
                        </h2>
                        <div id="faq1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Browse our products, add items to your cart, and proceed to checkout but make sure that you selected products of the same company. You'll need to provide your location for delivery and complete payment via MTN Mobile Money.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                What payment methods do you accept?
                            </button>
                        </h2>
                        <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                We currently accept MTN Mobile Money (MoMo) payments. You'll receive a prompt on your phone to confirm the payment.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq3">
                                How long does delivery take?
                            </button>
                        </h2>
                        <div id="faq3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Delivery time depends on your location. Same city deliveries typically take 12-36 hours, while different cities take 2-5 days. You'll see the estimated delivery time at checkout.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq4">
                                Can I track my order?
                            </button>
                        </h2>
                        <div id="faq4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Yes! Once your order is placed, you can track its status in your account dashboard. You'll receive notifications when the order status changes.
                            </div>
                        </div>
                    </div>
                    
                    <div class="accordion-item">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq5">
                                How do I become a seller?
                            </button>
                        </h2>
                        <div id="faq5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                            <div class="accordion-body">
                                Register for an account and select "Sell Products" as your role. Once approved, you can start listing your Rwandan cultural products on the platform.
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-card">
                <h3>Still Need Help?</h3>
                <p>If you can't find the answer you're looking for, please contact us:</p>
                <ul>
                    <li>Use the "Talk To Us" chat button (bottom right) for live support</li>
                    <li>Create a support ticket <a href="tickets.php">here</a></li>
                    <li>Email us at <?php echo SITE_EMAIL; ?></li>
                </ul>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="dashboard-card">
                <h3>Quick Actions</h3>
                <a href="tickets.php" class="btn btn-primary w-100 mb-2">Create Support Ticket</a>
                <a href="../buyer/orders.php" class="btn btn-outline-primary w-100 mb-2">View My Orders</a>
                <a href="../index.php" class="btn btn-outline-primary w-100">Continue Shopping</a>
            </div>
        </div>
    </div>
</div>

<?php include '../includes/footer.php'; ?>
