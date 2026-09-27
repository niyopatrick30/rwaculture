# Rwaculture - Multi-Vendor E-Commerce Platform

A fully functional, production-ready multi-vendor e-commerce website for selling Rwandan cultural tools and traditional products.

## Features

- **Multi-Vendor Marketplace**: Sellers can list and manage products
- **Live Location Tracking**: Browser Geolocation API for delivery
- **Map Integration**: OpenStreetMap for location visualization
- **Delivery ETA**: Calculated based on buyer and seller locations
- **MTN Mobile Money Payment**: Integration with MTN MoMo Rwanda
- **Order Tracking**: Real-time order status updates
- **Customer Support**: Help center, support tickets, and live chat
- **Notification System**: Real-time notifications with AJAX polling
- **Admin Dashboard**: Complete admin panel with analytics
- **Seller Dashboard**: Product and order management
- **Buyer Dashboard**: Order history and tracking

## Tech Stack

- **Backend**: PHP (MySQLi)
- **Frontend**: HTML, Bootstrap 5
- **Styling**: Internal CSS (Black & White branding)
- **JavaScript**: Vanilla JS + AJAX
- **Database**: MySQL
- **Maps**: OpenStreetMap (Leaflet.js)
- **Payment**: MTN Mobile Money (MoMo Rwanda)

## Installation

### Prerequisites

- XAMPP (or similar) with PHP 7.4+ and MySQL
- Web server (Apache)
- Modern web browser

### Setup Steps

1. **Clone/Download the project** to your XAMPP htdocs folder:
   ```
   C:\xampp\htdocs\Rwaculture
   ```

2. **Create the database**:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import `database.sql` to create the database and tables
   - Or run the SQL file manually

3. **Configure database connection**:
   - Edit `config/database.php`
   - Update database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'rwaculture_db');
     ```

4. **Set up file permissions**:
   - Ensure `images/` directory is writable for product uploads

5. **Configure Maps** (Optional):
   - Edit `config/maps.php` if you want to use Google Maps
   - By default, OpenStreetMap is used (no API key needed)

6. **Configure MTN MoMo** (For production):
   - Edit `config/momo.php`
   - Add your MTN MoMo API credentials:
     ```php
     define('MOMO_API_USER', 'YOUR_API_USER');
     define('MOMO_API_KEY', 'YOUR_API_KEY');
     define('MOMO_SUBSCRIPTION_KEY', 'YOUR_SUBSCRIPTION_KEY');
     ```

7. **Access the website**:
   - Open browser: `http://localhost/Rwaculture`

## Admin Account Recovery

Registration only offers buyer and seller roles. After importing the database, run `setup.php`; it creates an admin from the local-only `config/admin-bootstrap.php` file only when no admin exists. It never resets an existing admin's credentials.

Back up `config/admin-bootstrap.php` separately from the database. It is excluded from Git and blocked from direct web access. After signing in, use **Account Settings** to change email, phone, or password; keep the bootstrap file as the recovery credential for a fresh database restore.

## Project Structure

```
Rwaculture/
├── admin/              # Admin dashboard pages
├── api/                # API endpoints (AJAX)
├── assets/             # Additional assets
├── buyer/              # Buyer dashboard pages
├── config/             # Configuration files
│   ├── config.php      # Main config
│   ├── database.php    # Database connection
│   ├── maps.php        # Maps configuration
│   └── momo.php        # MTN MoMo configuration
├── css/                # Stylesheets
│   └── style.css       # Main stylesheet
├── images/             # Product images
├── includes/           # Shared includes
│   ├── functions.php   # Helper functions
│   ├── header.php      # Site header
│   └── footer.php      # Site footer
├── js/                 # JavaScript files
│   ├── main.js         # Main JS
│   ├── chat.js         # Live chat
│   ├── notifications.js # Notifications
│   └── location.js     # Location & maps
├── seller/             # Seller dashboard pages
├── support/            # Support system pages
├── BURNER.png          # Top banner (must be on top)
├── Rwaculture logo.png # Site logo
├── database.sql        # Database schema
├── index.php           # Homepage
├── login.php           # Login page
├── register.php        # Registration page
├── cart.php            # Shopping cart
├── checkout.php        # Checkout with location
├── payment.php         # Payment page
└── README.md           # This file
```

## Key Features Explained

### Live Location & Delivery

- Buyers are asked for location permission at checkout
- Location is captured using Browser Geolocation API
- Reverse geocoding gets city/address
- ETA is calculated based on distance
- Map visualization using OpenStreetMap

### Payment Integration

- MTN Mobile Money (MoMo) integration
- Phone number: 0782207396 (for testing)
- Payment status tracking
- Order confirmation after payment

### Notification System

- Real-time notifications via AJAX polling
- Notification bell with badge count
- Triggers on:
  - Order placed
  - Payment confirmed
  - Order status changes
  - Support tickets
  - Chat messages

### Customer Support

- **Help Center**: FAQs and guides
- **Support Tickets**: Create and track tickets
- **Live Chat**: "Talk To Us" floating button
- Admin can respond to all support requests

## User Roles

1. **Admin**: Full system access, analytics, user management
2. **Seller**: Product management, order tracking, delivery updates
3. **Buyer**: Browse, purchase, track orders, support

## Development Notes

- All code uses MySQLi (no PDO)
- Internal CSS for styling (black/white theme)
- Vanilla JavaScript (no frameworks)
- AJAX for real-time updates
- Session-based authentication
- Prepared statements for SQL security

## Security Features

- Password hashing with `password_hash()`
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)
- Session management
- Role-based access control

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Geolocation API support required for location features
- JavaScript must be enabled

## Support

For issues or questions:
- Email: info@rwaculture.com
- Use the "Talk To Us" chat feature on the website
- Create a support ticket

## License

Proprietary - All rights reserved

---

**Built with ❤️ for Rwandan Cultural Heritage**
