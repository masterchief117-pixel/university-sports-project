SET @requested_sport_type = (
    SELECT COLUMN_TYPE
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'requested_sport_id'
);
SET @migration_sql = IF(
    @requested_sport_type IS NULL,
    'ALTER TABLE users ADD COLUMN requested_sport_id INT UNSIGNED NULL AFTER sport_id',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @requested_sport_is_unsigned = (
    SELECT LOCATE('unsigned', COLUMN_TYPE) > 0
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'requested_sport_id'
);
SET @migration_sql = IF(
    COALESCE(@requested_sport_is_unsigned, 0) = 0,
    'ALTER TABLE users MODIFY requested_sport_id INT UNSIGNED NULL',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @requested_sport_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'requested_sport_id'
      AND SEQ_IN_INDEX = 1
);
SET @migration_sql = IF(
    @requested_sport_index_exists = 0,
    'CREATE INDEX idx_users_requested_sport ON users (requested_sport_id)',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;

SET @requested_sport_foreign_key_exists = (
    SELECT COUNT(*)
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'users'
      AND COLUMN_NAME = 'requested_sport_id'
      AND REFERENCED_TABLE_NAME = 'sports'
      AND REFERENCED_COLUMN_NAME = 'id'
);
SET @migration_sql = IF(
    @requested_sport_foreign_key_exists = 0,
    'ALTER TABLE users
     ADD CONSTRAINT fk_users_requested_sport
     FOREIGN KEY (requested_sport_id) REFERENCES sports (id)
     ON UPDATE CASCADE ON DELETE SET NULL',
    'DO 0'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
