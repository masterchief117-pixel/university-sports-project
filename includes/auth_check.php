<?php
declare(strict_types=1);

error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');

    $isSecure = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

const REMEMBER_COOKIE_NAME = 'university_sports_remember';
const REMEMBER_COOKIE_LIFETIME = 2592000;

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

function csrf_token_is_valid(string $submittedToken): bool
{
    $sessionToken = $_SESSION['csrf_token'] ?? '';

    return is_string($sessionToken)
        && $sessionToken !== ''
        && hash_equals($sessionToken, $submittedToken);
}

function set_remember_cookie(string $value, int $expires): void
{
    $isSecure = isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off';
    setcookie(REMEMBER_COOKIE_NAME, $value, [
        'expires' => $expires,
        'path' => app_url('/'),
        'secure' => $isSecure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function remember_cookie_selector(): ?string
{
    $cookie = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';

    if (!is_string($cookie)
        || !preg_match('/\A([a-f0-9]{32}):([a-f0-9]{64})\z/', $cookie, $matches)) {
        return null;
    }

    return $matches[1];
}

function clear_remember_cookie(): void
{
    set_remember_cookie('', time() - 3600);
    unset($_COOKIE[REMEMBER_COOKIE_NAME]);
}

function revoke_remembered_login(PDO $pdo): void
{
    $selector = remember_cookie_selector();
    if ($selector !== null) {
        $delete = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $delete->execute(['selector' => $selector]);
    }

    clear_remember_cookie();
}

function issue_remembered_login(PDO $pdo, int $userId): void
{
    $selector = bin2hex(random_bytes(16));
    $validator = bin2hex(random_bytes(32));
    $insert = $pdo->prepare(
        'INSERT INTO remember_tokens (selector, user_id, token_hash, expires_at)
         VALUES (:selector, :user_id, :token_hash, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY))'
    );
    $insert->execute([
        'selector' => $selector,
        'user_id' => $userId,
        'token_hash' => hash('sha256', $validator),
    ]);

    set_remember_cookie(
        $selector . ':' . $validator,
        time() + REMEMBER_COOKIE_LIFETIME
    );
}

function get_database_connection(): PDO
{
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
        require_once __DIR__ . '/../config/db.php';
        $GLOBALS['pdo'] = $pdo;
    }

    return $GLOBALS['pdo'];
}

function restore_remembered_login(PDO $pdo): bool
{
    $selector = remember_cookie_selector();
    if ($selector === null) {
        if (isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
            clear_remember_cookie();
        }
        return false;
    }

    $cookie = (string) $_COOKIE[REMEMBER_COOKIE_NAME];
    $validator = substr($cookie, 33);
    $lookup = $pdo->prepare(
        'SELECT rt.token_hash, u.id, u.name, u.role, u.sport_id
         FROM remember_tokens rt
         JOIN users u ON u.id = rt.user_id
         WHERE rt.selector = :selector
           AND rt.expires_at > CURRENT_TIMESTAMP
         LIMIT 1'
    );
    $lookup->execute(['selector' => $selector]);
    $user = $lookup->fetch(PDO::FETCH_ASSOC);

    if (!$user || !hash_equals((string) $user['token_hash'], hash('sha256', $validator))
        || !in_array($user['role'], ['player', 'coach', 'hos'], true)) {
        $delete = $pdo->prepare('DELETE FROM remember_tokens WHERE selector = :selector');
        $delete->execute(['selector' => $selector]);
        clear_remember_cookie();
        return false;
    }

    $newValidator = bin2hex(random_bytes(32));
    $rotate = $pdo->prepare(
        'UPDATE remember_tokens
         SET token_hash = :token_hash,
             expires_at = DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 30 DAY)
         WHERE selector = :selector AND token_hash = :old_hash
           AND expires_at > CURRENT_TIMESTAMP'
    );
    $rotate->execute([
        'token_hash' => hash('sha256', $newValidator),
        'selector' => $selector,
        'old_hash' => $user['token_hash'],
    ]);
    if ($rotate->rowCount() !== 1) {
        clear_remember_cookie();
        return false;
    }

    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['name'] = $user['name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['sport_id'] = $user['sport_id'] === null ? null : (int) $user['sport_id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    set_remember_cookie(
        $selector . ':' . $newValidator,
        time() + REMEMBER_COOKIE_LIFETIME
    );

    return true;
}

function require_login(): void
{
    if (empty($_SESSION['user_id']) && isset($_COOKIE[REMEMBER_COOKIE_NAME])) {
        $pdo = get_database_connection();
        restore_remembered_login($pdo);
    }

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

    $pdo = get_database_connection();
    $accountQuery = $pdo->prepare(
        'SELECT name, role, sport_id FROM users WHERE id = :user_id LIMIT 1'
    );
    $accountQuery->execute(['user_id' => (int) $_SESSION['user_id']]);
    $account = $accountQuery->fetch(PDO::FETCH_ASSOC);

    if (!$account) {
        $_SESSION = [];
        session_regenerate_id(true);
        header('Location: ' . app_url('/auth/login.php'));
        exit;
    }

    $_SESSION['name'] = $account['name'];
    $_SESSION['role'] = $account['role'];
    $_SESSION['sport_id'] = $account['sport_id'] === null
        ? null
        : (int) $account['sport_id'];

    if ($account['role'] !== $role) {
        $destinations = [
            'player' => '/player/dashboard.php',
            'coach' => '/coach/dashboard.php',
            'hos' => '/hos/dashboard.php',
        ];
        if (isset($destinations[$account['role']])) {
            header('Location: ' . app_url($destinations[$account['role']]));
            exit;
        }

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

    if (!is_string($providedToken) || !csrf_token_is_valid($providedToken)) {
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
    } elseif ($type === 'player_player') {
        $roleCounts = array_count_values($roles);
        $valid = $role === 'player'
            && count($participants) === 2
            && ($roleCounts['player'] ?? 0) === 2
            && count($sports) === 1
            && $sports[0] !== null
            && (int) $conversation['sport_id'] === (int) $sports[0];
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
