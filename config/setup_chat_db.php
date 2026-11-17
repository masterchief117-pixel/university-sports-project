<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/db.php';

$pdo->exec(
    "CREATE TABLE IF NOT EXISTS messages (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        conversation_id BIGINT UNSIGNED NULL,
        sender_id INT UNSIGNED NOT NULL,
        receiver_id INT UNSIGNED NULL,
        message TEXT NULL,
        body TEXT NULL,
        is_read BOOLEAN NOT NULL DEFAULT FALSE,
        created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY idx_messages_conversation_created (conversation_id, created_at, id),
        KEY idx_messages_receiver_read (receiver_id, is_read)
    ) ENGINE=InnoDB"
);

$columns = [
    'receiver_id' => 'INT UNSIGNED NULL',
    'message' => 'TEXT NULL',
    'is_read' => 'BOOLEAN NOT NULL DEFAULT FALSE',
];
$columnQuery = $pdo->prepare(
    'SELECT COUNT(*)
     FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = :table_name
       AND COLUMN_NAME = :column_name'
);

foreach ($columns as $columnName => $definition) {
    $columnQuery->execute([
        'table_name' => 'messages',
        'column_name' => $columnName,
    ]);

    if ((int) $columnQuery->fetchColumn() === 0) {
        $pdo->exec("ALTER TABLE messages ADD COLUMN {$columnName} {$definition}");
    }
}

echo "Chat database is ready.\n";
