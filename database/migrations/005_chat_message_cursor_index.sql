SET @cursor_index_exists = (
    SELECT COUNT(*)
    FROM information_schema.STATISTICS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'messages'
      AND INDEX_NAME = 'idx_messages_conversation_id'
);
SET @migration_sql = IF(
    @cursor_index_exists = 0,
    'CREATE INDEX idx_messages_conversation_id ON messages (conversation_id, id)',
    'SELECT 1'
);
PREPARE migration_statement FROM @migration_sql;
EXECUTE migration_statement;
DEALLOCATE PREPARE migration_statement;
