<?php
declare(strict_types=1);

$isCli = PHP_SAPI === 'cli';

if (!$isCli) {
    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';
    if (!in_array($remoteAddress, ['127.0.0.1', '::1'], true)) {
        http_response_code(403);
        exit('This seeder is available only from localhost.');
    }

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
        if (empty($_SESSION['seed_users_csrf'])) {
            $_SESSION['seed_users_csrf'] = bin2hex(random_bytes(32));
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
        $action = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '', ENT_QUOTES, 'UTF-8');
        $csrfToken = htmlspecialchars($_SESSION['seed_users_csrf'], ENT_QUOTES, 'UTF-8');
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="utf-8">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Confirm user seeding</title>
        </head>
        <body>
            <h1>Confirm user seeding</h1>
            <p>This will permanently delete all existing users, then create the three test accounts.</p>
            <form method="post" action="<?= $action ?>">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <label for="confirmation">Type TRUNCATE USERS to continue:</label>
                <input id="confirmation" name="confirmation" required>
                <button type="submit">Delete users and seed test accounts</button>
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
    $confirmation = $_POST['confirmation'] ?? '';
    $expectedToken = $_SESSION['seed_users_csrf'] ?? '';

    if (!is_string($submittedToken) || !is_string($expectedToken)
        || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)
        || $confirmation !== 'TRUNCATE USERS') {
        http_response_code(400);
        exit('Confirmation failed. Reload the page and try again.');
    }

    unset($_SESSION['seed_users_csrf']);
}

require_once __DIR__ . '/../config/db.php';

$pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
try {
    $pdo->exec('TRUNCATE TABLE users');
} finally {
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$users = [
    [
        'name' => 'Dr. Sarah Jenkins',
        'email' => 'hos@uni.edu',
        'role' => 'hos',
        'sport' => null,
    ],
    [
        'name' => 'Coach Basketball',
        'email' => 'coach.bb@uni.edu',
        'role' => 'coach',
        'sport' => 'Basketball',
    ],
    [
        'name' => 'Player Basketball',
        'email' => 'p1.bb@uni.edu',
        'role' => 'player',
        'sport' => 'Basketball',
    ],
];

$findSport = $pdo->prepare('SELECT id FROM sports WHERE name = :name LIMIT 1');
$insertUser = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role, sport_id)
     VALUES (:name, :email, :password_hash, :role, :sport_id)'
);

$pdo->beginTransaction();

try {
    foreach ($users as $user) {
        $sportId = null;

        if ($user['sport'] !== null) {
            $findSport->execute(['name' => $user['sport']]);
            $sportId = $findSport->fetchColumn();

            if ($sportId === false) {
                throw new RuntimeException('Sport not found: ' . $user['sport']);
            }

            $sportId = (int) $sportId;
        }

        $insertUser->bindValue(':name', $user['name'], PDO::PARAM_STR);
        $insertUser->bindValue(':email', $user['email'], PDO::PARAM_STR);
        $insertUser->bindValue(
            ':password_hash',
            password_hash('password123', PASSWORD_DEFAULT),
            PDO::PARAM_STR
        );
        $insertUser->bindValue(':role', $user['role'], PDO::PARAM_STR);
        $insertUser->bindValue(
            ':sport_id',
            $sportId,
            $sportId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
        );
        $insertUser->execute();
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

echo 'Users seeded successfully!' . PHP_EOL;
