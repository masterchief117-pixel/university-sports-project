<?php
declare(strict_types=1);

error_reporting(E_ALL);

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

$afterMessageId = null;
if (array_key_exists('after_id', $_GET)) {
    $validatedAfterId = filter_var($_GET['after_id'], FILTER_VALIDATE_INT);
    if ($validatedAfterId === false || $validatedAfterId < 0) {
        json_error('after_id must be a non-negative integer.', 400);
    }
    $afterMessageId = (int) $validatedAfterId;
}

$beforeMessageId = null;
if (array_key_exists('before_id', $_GET)) {
    $validatedBeforeId = filter_var($_GET['before_id'], FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1],
    ]);
    if ($validatedBeforeId === false || $validatedBeforeId === null) {
        json_error('before_id must be a positive integer.', 400);
    }
    $beforeMessageId = (int) $validatedBeforeId;
}
if ($afterMessageId !== null && $beforeMessageId !== null) {
    json_error('Use only one message cursor at a time.', 400);
}

$messageLimit = 100;
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
$hasOlderMessages = false;
if ($conversationId !== null) {
    require_conversation_access($pdo, $conversationId);

    try {
        $cursorClause = '';
        $order = 'DESC';
        if ($afterMessageId !== null) {
            $cursorClause = ' AND m.id > :cursor';
            $order = 'ASC';
        } elseif ($beforeMessageId !== null) {
            $cursorClause = ' AND m.id < :cursor';
        }

        $query = $pdo->prepare(
            'SELECT m.id, m.sender_id, m.body, m.created_at, u.name AS sender_name
             FROM messages m
             JOIN users u ON u.id = m.sender_id
             WHERE m.conversation_id = :conversation_id' . $cursorClause . '
             ORDER BY m.id ' . $order . '
             LIMIT ' . ($messageLimit + 1)
        );
        $query->bindValue(':conversation_id', $conversationId, PDO::PARAM_INT);
        if ($afterMessageId !== null) {
            $query->bindValue(':cursor', $afterMessageId, PDO::PARAM_INT);
        } elseif ($beforeMessageId !== null) {
            $query->bindValue(':cursor', $beforeMessageId, PDO::PARAM_INT);
        }
        $query->execute();
        $messages = $query->fetchAll();

        $hasMore = count($messages) > $messageLimit;
        if ($hasMore) {
            array_pop($messages);
            $hasOlderMessages = $afterMessageId === null;
        }
        if ($afterMessageId === null) {
            $messages = array_reverse($messages);
        }
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
    'has_older' => $hasOlderMessages,
], JSON_THROW_ON_ERROR);
