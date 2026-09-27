# Free Demo Preview

This project can run on a free PHP/MySQL host as a demonstration. Do not use a free personal-use plan for real customers, real payment proofs, or production data.

The Alwaysdata account created for this preview currently reports the database feature as unavailable. For an alternative free demo route using Render and Aiven, see `RENDER_AIVEN_PREVIEW.md`.

## Suggested Host

[Alwaysdata Free](https://www.alwaysdata.com/en/offers/) currently lists a free personal-use plan with PHP, MariaDB/MySQL, 1 GB SSD storage, 256 MB RAM, one-quarter CPU, and three days of backups. The plan is limited and is not a production hosting recommendation. Review the provider's current terms and limits before signing up.

The repository contains about 294 MB of video assets stored with Git LFS, leaving limited space for uploads and database files on a 1 GB account. Use only a small amount of test data and remove unnecessary demo media if storage gets tight.

## Setup Outline

1. Create a free Alwaysdata account and a PHP site/subdomain. Configure its document root to the deployed project directory.
2. Create a MariaDB/MySQL database and database user in the hosting panel.
3. Upload the project files. Use the checked-out files from your computer or an FTP client so the `.mp4` files are uploaded as actual videos. Do not upload `.git/`, `.vscode/`, the local `config/admin-bootstrap.php`, or any files from the local `uploads/` directory.
4. In the host's private environment settings, set `DB_HOST`, `DB_PORT`, `DB_USER`, `DB_PASS`, `DB_NAME`, and `RWACULTURE_SITE_URL` using the values shown by the hosting provider. Do not commit these values.
5. Create/select the provider database in its database manager and import `database.sql` into it. The schema file creates tables in the selected database and does not create or select a local `rwaculture_db` database.
6. Set `RWACULTURE_ADMIN_EMAIL`, `RWACULTURE_ADMIN_PHONE`, and `RWACULTURE_ADMIN_PASSWORD` as private environment variables. Use a unique demo password, not the local XAMPP admin password.
7. Visit `/setup.php` once to create the admin and verify the connection. Then remove the three `RWACULTURE_ADMIN_*` variables and delete or block `setup.php` from public access.
8. Create empty writable folders for `uploads/profile`, `uploads/payments`, `uploads/payment_proofs`, and `uploads/commission_settlements`. Do not copy local payment proofs or customer profile photos.
9. Check the preview using dummy accounts and dummy orders only.

## Configuration

`config/database.php` reads database connection values from the environment and retains XAMPP defaults for local development. `RWACULTURE_SITE_URL` should be the exact public base URL, for example `https://your-site.alwaysdata.net` with no trailing slash.

The free plan has limited CPU, memory, storage, and backup retention. It is suitable only for a lightweight preview; availability and capacity are not guaranteed for business use.