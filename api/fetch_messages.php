<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_api_login();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    json_error('Method not allowed.', 405);
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

$typeInput = $_GET['conversation_type'] ?? '';
if (!is_string($typeInput) || $typeInput === '') {
    json_error('conversation_type is required.', 400);
}

$targetUserId = null;
if (array_key_exists('target_user_id', $_GET)) {
    $validatedTarget = filter_var($_GET['target_user_id'], FILTER_VALIDATE_INT);
    if (!$validatedTarget || $validatedTarget < 1) {
        json_error('target_user_id must be a positive integer.', 400);
    }
    $targetUserId = (int) $validatedTarget;
}

$context = get_chat_context($pdo, $typeInput, $targetUserId);

try {
    $pdo->beginTransaction();
    $conversationId = find_chat_conversation($pdo, $typeInput, $context, false);
    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    json_error('Messages could not be loaded.', 500);
}

$messages = [];
if ($conversationId !== null) {
    require_conversation_access($pdo, $conversationId);

    try {
        $query = $pdo->prepare(
            'SELECT m.id, m.sender_id, m.body, m.created_at, u.name AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = :conversation_id
             ORDER BY m.created_at ASC, m.id ASC'
        );
        $query->execute(['conversation_id' => $conversationId]);
        $messages = $query->fetchAll();
    } catch (PDOException $exception) {
        error_log($exception->getMessage());
        json_error('Messages could not be loaded.', 500);
    }
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'success' => true,
    'conversation_id' => $conversationId,
    'messages' => $messages,
], JSON_THROW_ON_ERROR);
