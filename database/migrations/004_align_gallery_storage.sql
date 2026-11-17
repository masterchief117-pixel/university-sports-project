SET @gallery_name_type = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'galleries'
      AND COLUMN_NAME = 'name'
);
SET @migration_sql = IF(
    @gallery_name_type <> 'varchar(255)',
    'ALTER TABLE galleries MODIFY name VARCHAR(255) NOT NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @cover_photo_type = (
    SELECT DATA_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'galleries'
      AND COLUMN_NAME = 'cover_photo'
);
SET @migration_sql = IF(
    @cover_photo_type <> 'text',
    'ALTER TABLE galleries MODIFY cover_photo TEXT NOT NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @file_path_type = (
    SELECT DATA_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photos'
      AND COLUMN_NAME = 'file_path'
);
SET @migration_sql = IF(
    @file_path_type <> 'text',
    'ALTER TABLE photos MODIFY file_path TEXT NOT NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @caption_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photos'
      AND COLUMN_NAME = 'caption'
);
SET @migration_sql = IF(
    @caption_exists = 0,
    'ALTER TABLE photos ADD COLUMN caption VARCHAR(255) NULL',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @uploaded_at_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photos'
      AND COLUMN_NAME = 'uploaded_at'
);
SET @created_at_exists = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'photos'
      AND COLUMN_NAME = 'created_at'
);
SET @migration_sql = IF(
    @uploaded_at_exists = 0,
    IF(
        @created_at_exists > 0,
        'ALTER TABLE photos CHANGE COLUMN created_at uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP',
        'ALTER TABLE photos ADD COLUMN uploaded_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP'
    ),
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
