-- Add profile_picture column to users table when upgrading an existing install.
SET @profile_picture_exists = (
	SELECT COUNT(*)
	FROM INFORMATION_SCHEMA.COLUMNS
	WHERE TABLE_SCHEMA = DATABASE()
	  AND TABLE_NAME = 'users'
	  AND COLUMN_NAME = 'profile_picture'
);
SET @profile_picture_sql = IF(
	@profile_picture_exists = 0,
	'ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL AFTER phone',
	'SELECT 1'
);
PREPARE profile_picture_statement FROM @profile_picture_sql;
EXECUTE profile_picture_statement;
DEALLOCATE PREPARE profile_picture_statement;

-- Create uploads directory structure (run this manually or via PHP)
-- mkdir uploads/profile
