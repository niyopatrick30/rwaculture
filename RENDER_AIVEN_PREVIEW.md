# Render + Aiven Free Demo

This deploy path is for a temporary demo with dummy accounts and data only. Render Free web services sleep when idle, have ephemeral filesystems, and may be suspended for excessive outbound traffic. Aiven Free MySQL currently includes 1 GB storage and can power off after extended inactivity. Do not use this setup for real customers, payment proofs, or production data.

Official plan references: [Render Free limits](https://render.com/docs/free), [Aiven MySQL Free tier](https://aiven.io/docs/products/mysql/concepts/mysql-free-tier).

## 1. Create Aiven MySQL

1. Create an Aiven account and choose **MySQL** with the **Free** plan. Do not start a trial or choose a paid plan.
2. Wait until the service is **Running**.
3. Download its CA certificate from the service's **Overview** page.
4. Record the hostname, port, username, and password from **Quick connect**. Keep these values private.
5. Use the provider's default database name (often `defaultdb`) or create a separate demo database if the free plan allows it.
6. Import `database.sql` into the selected Aiven database. This schema intentionally does not create or select a hard-coded database.

## 2. Deploy on Render

1. Create a Render account and select **New > Web Service** for the public GitHub repository `niyopatrick30/rwaculture`, branch `main`.
2. Choose **Docker** and the **Free** instance plan. Do not add a persistent disk or select a paid plan.
3. Set these environment variables in the Render service settings using Aiven's **Quick connect** values:

   | Variable | Value |
   | --- | --- |
   | `DB_HOST` | Aiven hostname |
   | `DB_PORT` | Aiven port |
   | `DB_USER` | Aiven username |
   | `DB_PASS` | Aiven password |
   | `DB_NAME` | Aiven database name |
   | `DB_SSL_CA` | `/etc/secrets/aiven-ca.pem` |
   | `RWACULTURE_SITE_URL` | The Render URL, with no trailing slash |

4. Under the service's **Secret Files**, upload the downloaded CA certificate as `aiven-ca.pem`. The PHP MySQL connection verifies the server certificate when `DB_SSL_CA` is set.
5. Do not put database credentials, CA contents, or admin passwords in `render.yaml`, Git, or this document.
6. Once the service is deployed and its database environment variables are saved, set `RWACULTURE_ADMIN_EMAIL`, `RWACULTURE_ADMIN_PHONE`, and a unique demo-only `RWACULTURE_ADMIN_PASSWORD` in the Render environment settings.
7. Visit `/setup.php` once to create the demo admin. Then remove the `RWACULTURE_ADMIN_*` variables and delete `setup.php` from the deployed source or redeploy without it.
8. Test using dummy information only.

## Free-Tier Limitations

- Render's free web service sleeps after 15 minutes without traffic and may take about a minute to wake.
- Render's filesystem is ephemeral. Uploaded profile photos, products, and payment proofs disappear on restart, spin-down, or deploy. Large video playback also uses the free bandwidth allotment quickly.
- Aiven Free MySQL is limited to 1 GB of RAM, 1 GB of storage, and 76 simultaneous connections; it may power down after extended inactivity.
- Render may suspend free services that generate unusually high external traffic. This app makes outbound MySQL connections to Aiven, so the combination is for a light demo only.