<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function app_url(string $path): string
{
    $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/');
    $basePath = rtrim(str_replace('\\', '/', dirname($scriptPath)), '/');

    foreach (['/auth', '/api', '/player', '/coach', '/hos'] as $directory) {
        if ($basePath === $directory) {
            $basePath = '';
            break;
        }

        if (substr($basePath, -strlen($directory)) === $directory) {
            $basePath = substr($basePath, 0, -strlen($directory));
            break;
        }
    }

    return $basePath . '/' . ltrim($path, '/');
}

function require_login(): void
{
    if (empty($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header('Location: ' . app_url('/auth/login.php'));
        exit;
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function require_role(string $role): void
{
    require_login();

    if ($_SESSION['role'] !== $role) {
        http_response_code(403);
        exit('You are not authorized to access this page.');
    }
}

function json_error(string $message, int $status): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $message], JSON_THROW_ON_ERROR);
    exit;
}

function require_api_login(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (empty($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        json_error('Authentication required.', 401);
    }

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}

function require_valid_csrf_token(): void
{
    $providedToken = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    if ($sessionToken === '' || !hash_equals($sessionToken, $providedToken)) {
        json_error('Invalid request token.', 403);
    }
}

function require_conversation_access(PDO $pdo, int $conversationId): void
{
    $userId = (int) $_SESSION['user_id'];
    $role = (string) $_SESSION['role'];

    $conversationQuery = $pdo->prepare(
        'SELECT conversation_type, sport_id FROM conversations WHERE id = :id'
    );
    $conversationQuery->execute(['id' => $conversationId]);
    $conversation = $conversationQuery->fetch();

    if (!$conversation) {
        json_error('Conversation not found.', 404);
    }

    $participantsQuery = $pdo->prepare(
        'SELECT u.id, u.role, u.sport_id
         FROM conversation_participants cp
         JOIN users u ON u.id = cp.user_id
         WHERE cp.conversation_id = :conversation_id
         ORDER BY u.id'
    );
    $participantsQuery->execute(['conversation_id' => $conversationId]);
    $participants = $participantsQuery->fetchAll();

    $isParticipant = false;
    foreach ($participants as $participant) {
        if ((int) $participant['id'] === $userId) {
            $isParticipant = true;
            break;
        }
    }

    if (!$isParticipant) {
        json_error('You are not a member of this conversation.', 403);
    }

    $roles = array_column($participants, 'role');
    $sports = array_unique(array_map(
        static fn (array $participant): ?int => $participant['sport_id'] === null
            ? null
            : (int) $participant['sport_id'],
        $participants
    ));
    $type = $conversation['conversation_type'];
    $valid = false;

    if ($type === 'team') {
        $valid = in_array($role, ['player', 'coach'], true)
            && count($sports) === 1
            && $sports[0] !== null
            && (int) $conversation['sport_id'] === (int) $sports[0]
            && count(array_diff($roles, ['player', 'coach'])) === 0;
    } elseif ($type === 'player_coach') {
        $valid = count($participants) === 2
            && count(array_count_values($roles)) === 2
            && in_array('player', $roles, true)
            && in_array('coach', $roles, true)
            && count($sports) === 1
            && $sports[0] !== null
            && (int) $conversation['sport_id'] === (int) $sports[0]
            && in_array($role, ['player', 'coach'], true);
    } elseif ($type === 'coaches_board') {
        $valid = $role === 'coach'
            && count($participants) >= 1
            && count(array_diff($roles, ['coach'])) === 0;
    } elseif ($type === 'coach_hos') {
        $roleCounts = array_count_values($roles);
        $valid = count($participants) === 2
            && ($roleCounts['coach'] ?? 0) === 1
            && ($roleCounts['hos'] ?? 0) === 1
            && in_array($role, ['coach', 'hos'], true);
    }

    if (!$valid) {
        json_error('This conversation is not permitted for your role.', 403);
    }
}
