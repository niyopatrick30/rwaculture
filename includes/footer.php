<!-- Footer -->
<footer class="main-footer">
    <div class="container">
        <div class="row">
            <div class="col-md-3">
                <h5>Rwaculture</h5>
                <p>Rwaculture is a trusted digital platform where Rwandan sellers showcase and sell
                    traditional and cultural products.
                    <br><br>
                    Our mission is to promote local craftsmanship, support small businesses,
                    and preserve Rwanda’s rich cultural heritage.
                </p>
            </div>

            <div class="col-md-3">
                <h5>Quick Links</h5>
                <ul class="footer-links">
                    <li><a href="<?php echo SITE_URL; ?>/index.php">Home</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/products.php">Products</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/about.php">About Us</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/support/help.php">Help Center</a></li>
                    <li><a href="<?php echo SITE_URL; ?>/support/tickets.php">Support Tickets</a></li>
                </ul>
            </div>

            <div class="col-md-3">
                <h5>Location</h5>
                <p><i class="fas fa-map-marker-alt"></i> Nyanza, Near Museum</p>
                <p>Rwanda</p>
                <p class="mt-3"><i class="fas fa-envelope"></i> Email: <?php echo SITE_EMAIL; ?></p>
                <p><i class="fas fa-phone"></i> Phone: +250 782 207 396</p>
            </div>

            <div class="col-md-3">
                <h5>Calendar</h5>
                <div id="footerCalendar" class="footer-calendar"></div>
            </div>
        </div>

        <!-- Social Media -->
        <div class="row mt-5">
            <div class="col text-center">
                <h5>Follow Us</h5>

                <div class="footer-social" style="margin-top:20px;">

                    <!-- Facebook -->
                    <a href="https://www.facebook.com/" target="_blank" class="social-circle">
                        <i class="fab fa-facebook-f"></i>
                    </a>

                    <!-- Instagram -->
                    <a href="https://www.instagram.com/" target="_blank" class="social-circle">
                        <i class="fab fa-instagram"></i>
                    </a>

                    <!-- YouTube -->
                    <a href="https://www.youtube.com/@NiyoPatrick30/" target="_blank" class="social-circle">
                        <i class="fab fa-youtube"></i>
                    </a>

                    <!-- TikTok -->
                    <a href="https://www.tiktok.com/" target="_blank" class="social-circle">
                        <i class="fab fa-tiktok"></i>
                    </a>

                    <!-- X -->
                    <a href="https://x.com/" target="_blank" class="social-circle social-x">
                        X
                    </a>

                    <!-- Snapchat -->
                    <a href="https://www.snapchat.com/" target="_blank" class="social-circle">
                        <i class="fab fa-snapchat-ghost"></i>
                    </a>

                    <!-- LinkedIn -->
                    <a href="https://www.linkedin.com/" target="_blank" class="social-circle">
                        <i class="fab fa-linkedin-in"></i>
                    </a>

                </div>
            </div>
        </div>

        <hr>

        <div class="text-center">
            <p>&copy; <?php echo date('Y'); ?> Rwaculture. All rights reserved.</p>
        </div>
    </div>
</footer>

<!-- SOCIAL ICON STYLES (ADDED ONLY) -->
<style>
.social-circle {
    width: 60px;
    height: 60px;
    margin: 0 16px;
    background: #fff;
    color: #000;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 26px;
    text-decoration: none;
    transition: all 0.35s ease;
}

.social-circle:hover {
    transform: scale(1.15);
    box-shadow: 0 10px 25px rgba(0,0,0,0.25);
}

.social-x {
    font-weight: bold;
}
</style>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
window.RWACULTURE_API_BASE = <?php echo json_encode(SITE_URL . '/api'); ?>;
</script>
<script src="<?php echo SITE_URL; ?>/js/main.js"></script>
<script src="<?php echo SITE_URL; ?>/js/chat.js"></script>
<script src="<?php echo SITE_URL; ?>/js/notifications.js"></script>

<?php if (isset($additional_scripts)): ?>
    <?php foreach ($additional_scripts as $script): ?>
        <script src="<?php echo SITE_URL; ?>/js/<?php echo $script; ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarDiv = document.getElementById('footerCalendar');
    if (calendarDiv) {
        const now = new Date();
        const year = now.getFullYear();
        const month = now.getMonth();
        const today = now.getDate();

        const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
        const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];

        const firstDay = new Date(year, month, 1).getDay();
        const daysInMonth = new Date(year, month + 1, 0).getDate();

        let html = '<h6>' + monthNames[month] + ' ' + year + '</h6>';
        html += '<table><thead><tr>';
        dayNames.forEach(day => html += '<th>' + day + '</th>');
        html += '</tr></thead><tbody><tr>';

        for (let i = 0; i < firstDay; i++) html += '<td></td>';

        for (let day = 1; day <= daysInMonth; day++) {
            if ((firstDay + day - 1) % 7 === 0 && day > 1) html += '</tr><tr>';
            html += '<td class="' + (day === today ? 'today' : '') + '">' + day + '</td>';
        }

        const remaining = 7 - ((firstDay + daysInMonth) % 7);
        if (remaining < 7) for (let i = 0; i < remaining; i++) html += '<td></td>';

        html += '</tr></tbody></table>';
        calendarDiv.innerHTML = html;
    }
});
</script>
</body>
</html>
