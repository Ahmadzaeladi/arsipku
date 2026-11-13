-- Add account avatars and workspace logos to existing ArsipKu databases.
SET @profile_column_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `users` ADD COLUMN `avatar_path` VARCHAR(255) NULL AFTER `password_hash`',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_path'
);
PREPARE profile_column_stmt FROM @profile_column_sql;
EXECUTE profile_column_stmt;
DEALLOCATE PREPARE profile_column_stmt;

SET @workspace_logo_sql = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE `workspaces` ADD COLUMN `logo_path` VARCHAR(255) NULL AFTER `type`',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'workspaces' AND COLUMN_NAME = 'logo_path'
);
PREPARE workspace_logo_stmt FROM @workspace_logo_sql;
EXECUTE workspace_logo_stmt;
DEALLOCATE PREPARE workspace_logo_stmt;
