# Installation Guide - Rwaculture

## Quick Start (5 Minutes)

### Step 1: Database Setup
1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click "New" to create a database (or use existing)
3. Select the database
4. Click "Import" tab
5. Choose `database.sql` file
6. Click "Go"

If the database already exists, import `database_migration_direct_payments_commissions.sql` once to add seller payment accounts, the admin receiving account, and commission settlement records.

### Step 2: Verify Configuration
For local XAMPP, the defaults in `config/database.php` use `127.0.0.1`, `root`, an empty password, and `rwaculture_db`. For hosted installs, set `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, and `DB_NAME` as environment variables in the hosting control panel. Never commit live database credentials.

### Step 3: Set Permissions
- Make sure `images/` folder is writable
- On Windows: Right-click folder → Properties → Security → Allow write

### Step 4: Initialize the Admin Account
- For local XAMPP, keep your local `config/admin-bootstrap.php` file in a secure backup.
- For hosted installs, set `RWACULTURE_ADMIN_EMAIL`, `RWACULTURE_ADMIN_PHONE`, and `RWACULTURE_ADMIN_PASSWORD` as private environment variables.
- Visit: `http://localhost/Rwaculture/setup.php`
- Setup creates the recovery admin only if no admin account exists. It never resets an existing admin password.

### Step 5: Access Website
- Homepage: `http://localhost/Rwaculture`
- Admin Login: `http://localhost/Rwaculture/login.php`

### Access From Another Device on the Same Wi-Fi
1. Start Apache and MySQL in XAMPP on the computer hosting the platform.
2. Find the host computer's Wi-Fi IPv4 address (for example, `10.14.56.6`).
3. On the other device, open `http://10.14.56.6/Rwaculture` using that address.
4. Keep both devices on the same Wi-Fi network. Windows Firewall must allow Apache on the current network.

The application builds its links from the address used by the browser, so localhost and LAN access both work. Browser geolocation generally requires HTTPS; on a plain LAN HTTP address, location features may be blocked by the browser even though the rest of the platform works.

## Admin Account Recovery

Registration only offers buyer and seller roles. For a fresh database restore, import `database.sql` and visit `setup.php` to recreate the admin from the local-only `config/admin-bootstrap.php` file. Back up this file separately from the database; setup will not reset credentials when an admin already exists.

## Free Demo Hosting

See `FREE_PREVIEW.md` for the Alwaysdata free-tier demo path. That plan is labeled for personal needs only; do not use it for real customers, payments, or production data. The environment-variable settings above let the hosted app connect to a database created by the provider. After initializing the demo admin, remove the admin bootstrap environment variables from the host and delete or restrict `setup.php`.

## Testing the System

1. **Login as Admin**
   - Go to `/login.php`
   - Use admin credentials
   - Access admin dashboard

2. **Register as Seller**
   - Go to `/register.php`
   - Select "Sell Products"
   - Add products from seller dashboard

3. **Register as Buyer**
   - Go to `/register.php`
   - Select "Buy Products"
   - Browse and add to cart
   - Test checkout with location

4. **Test Delivery Address**
   - Add items to cart
   - Go to checkout
   - Enter the full delivery address and city or district manually
   - Verify the address is shown with the order details

5. **Test Payment**
   - Complete checkout
   - Pay the seller using the bank or mobile-money details shown on the payment page
   - Upload the payment proof; the seller reviews the buyer payment
   - Sellers open **Commission Settlement**, pay the admin's configured account, and upload proof
   - Admins open **Commission Management**, verify the amount shown on the proof, and confirm or reject it

## Troubleshooting

### Database Connection Error
- Check MySQL is running in XAMPP
- Verify credentials in `config/database.php`
- Ensure database exists

### Images Not Uploading
- Check `images/` folder permissions
- Verify folder exists
- Check PHP upload settings in `php.ini`

### Delivery Address
- Customers enter their full delivery address and city or district manually during checkout.

### Payment Not Working
- In development, payment is simulated
- For production, configure MTN MoMo API in `config/momo.php`
- Add real API credentials

## Production Deployment

1. **Update Config Files**
   - `config/database.php` - Production database
   - `config/momo.php` - Real MTN MoMo credentials
   - `config/config.php` - Update SITE_URL

2. **Security**
   - Change default admin password
   - Use strong database passwords
   - Enable HTTPS
   - Review `.htaccess` settings

3. **Performance**
   - Enable PHP opcache
   - Optimize database indexes
   - Use CDN for static assets

## Support

For issues:
- Check `README.md` for detailed documentation
- Review error logs in XAMPP
- Use "Talk To Us" chat feature
- Create support ticket

---

**Ready to go!** 🚀
