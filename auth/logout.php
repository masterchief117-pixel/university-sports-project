<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

if (!csrf_token_is_valid(is_string($_POST['csrf_token'] ?? null) ? $_POST['csrf_token'] : '')) {
    http_response_code(403);
    exit('Invalid request token.');
}

require_once __DIR__ . '/../config/db.php';
revoke_remembered_login($pdo);

$_SESSION = [];
session_regenerate_id(true);
setcookie(session_name(), '', [
    'expires' => time() - 3600,
    'path' => '/',
    'secure' => isset($_SERVER['HTTPS'])
        && $_SERVER['HTTPS'] !== ''
        && strtolower((string) $_SERVER['HTTPS']) !== 'off',
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_destroy();
header('Location: ' . app_url('/auth/login.php'));
exit;
