<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_api_login();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_error('Method not allowed.', 405);
}

$conversationId = filter_input(INPUT_GET, 'conversation_id', FILTER_VALIDATE_INT);
if (!$conversationId || $conversationId < 1) {
    json_error('A valid conversation_id is required.', 400);
}

require_conversation_access($pdo, $conversationId);

$query = $pdo->prepare(
    'SELECT m.id, m.body, m.created_at, u.name AS sender_name
     FROM messages m
     JOIN users u ON u.id = m.sender_id
     WHERE m.conversation_id = :conversation_id
     ORDER BY m.created_at ASC, m.id ASC
     LIMIT 200'
);
$query->execute(['conversation_id' => $conversationId]);

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['messages' => $query->fetchAll()], JSON_THROW_ON_ERROR);
