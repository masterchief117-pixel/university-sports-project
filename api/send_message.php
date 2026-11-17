<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    json_error('Method not allowed.', 405);
}

require_valid_csrf_token();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

try {
    $payload = json_decode(file_get_contents('php://input'), true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $exception) {
    json_error('A valid JSON request body is required.', 400);
}

if (!is_array($payload) || !is_string($payload['conversation_type'] ?? null)
    || !is_string($payload['body'] ?? null)) {
    json_error('conversation_type and body are required.', 400);
}

$conversationType = $payload['conversation_type'];
$body = trim($payload['body']);
$targetUserId = null;

if (array_key_exists('target_user_id', $payload)) {
    $validatedTarget = filter_var($payload['target_user_id'], FILTER_VALIDATE_INT);
    if (!$validatedTarget || $validatedTarget < 1) {
        json_error('target_user_id must be a positive integer.', 400);
    }
    $targetUserId = (int) $validatedTarget;
}

$messageLength = function_exists('mb_strlen') ? mb_strlen($body, 'UTF-8') : strlen($body);
if ($body === '' || $messageLength > 4000) {
    json_error('Message body must contain between 1 and 4000 characters.', 400);
}

$context = get_chat_context($pdo, $conversationType, $targetUserId);

try {
    $pdo->beginTransaction();
    $conversationId = find_chat_conversation($pdo, $conversationType, $context, true);

    if ($conversationId === null) {
        throw new RuntimeException('Conversation could not be created.');
    }

    require_conversation_access($pdo, $conversationId);

    $insert = $pdo->prepare(
        'INSERT INTO messages (conversation_id, sender_id, body)
         VALUES (:conversation_id, :sender_id, :body)'
    );
    $insert->execute([
        'conversation_id' => $conversationId,
        'sender_id' => $context['actor_id'],
        'body' => $body,
    ]);

    $messageId = (int) $pdo->lastInsertId();
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    json_error('The message could not be saved.', 500);
} catch (RuntimeException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    json_error('The conversation could not be created.', 500);
}

header('Content-Type: application/json; charset=utf-8');
http_response_code(201);
echo json_encode([
    'success' => true,
    'conversation_id' => $conversationId,
    'message_id' => $messageId,
], JSON_THROW_ON_ERROR);
