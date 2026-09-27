# Database Migration Instructions

## Profile Picture Support

To add profile picture support to your database, run the following SQL command:

```sql
ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER phone;
```

Or run the migration file:
```bash
mysql -u root -p rwaculture_db < database_migration_profile_picture.sql
```

## Create Upload Directory

Create the uploads directory structure:
```bash
mkdir -p uploads/profile
chmod 755 uploads/profile
```

Or create it via PHP (it will be created automatically on first upload).

## Default Avatar

Place a default avatar image at: `images/default-avatar.png` (150x150px recommended)
