<?php
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This password update is available only from localhost.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (empty($_SESSION['fix_passwords_csrf'])) {
        $_SESSION['fix_passwords_csrf'] = bin2hex(random_bytes(32));
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $action = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '', ENT_QUOTES, 'UTF-8');
    $csrfToken = htmlspecialchars($_SESSION['fix_passwords_csrf'], ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Reset all account passwords</title>
    </head>
    <body>
        <h1>Reset all account passwords</h1>
        <p>This will set every user account password to <code>password123</code>.</p>
        <form method="post" action="<?= $action ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <button type="submit">Reset all passwords</button>
        </form>
    </body>
    </html>
    <?php
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: GET, POST');
    http_response_code(405);
    exit('Method not allowed.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['fix_passwords_csrf'] ?? '';
if (!is_string($submittedToken) || !is_string($expectedToken)
    || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    http_response_code(400);
    exit('Invalid request token. Reload the page and try again.');
}
unset($_SESSION['fix_passwords_csrf']);

require_once __DIR__ . '/../config/db.php';

$hash = password_hash('password123', PASSWORD_DEFAULT);
$update = $pdo->prepare('UPDATE users SET password_hash = :hash');
$update->execute(['hash' => $hash]);

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo 'Passwords fixed!';
