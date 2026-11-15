<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_api_login();
require_valid_csrf_token();
require_once __DIR__ . '/../config/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_error('Method not allowed.', 405);
}

$payload = json_decode(file_get_contents('php://input'), true);
if (!is_array($payload)) {
    json_error('A JSON request body is required.', 400);
}

$conversationId = filter_var(
    $payload['conversation_id'] ?? null,
    FILTER_VALIDATE_INT
);
$body = trim((string) ($payload['body'] ?? ''));

if (!$conversationId || $conversationId < 1 || $body === '' || mb_strlen($body) > 4000) {
    json_error('A valid conversation_id and message (up to 4000 characters) are required.', 400);
}

require_conversation_access($pdo, $conversationId);

$insert = $pdo->prepare(
    'INSERT INTO messages (conversation_id, sender_id, body)
     VALUES (:conversation_id, :sender_id, :body)'
);
$insert->execute([
    'conversation_id' => $conversationId,
    'sender_id' => (int) $_SESSION['user_id'],
    'body' => $body,
]);

header('Content-Type: application/json; charset=utf-8');
http_response_code(201);
echo json_encode(['message_id' => (int) $pdo->lastInsertId()], JSON_THROW_ON_ERROR);
