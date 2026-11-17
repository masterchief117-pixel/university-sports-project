<?php
declare(strict_types=1);

if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This seeder is available only from localhost.');
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET') {
    if (empty($_SESSION['seed_dummy_data_csrf'])) {
        $_SESSION['seed_dummy_data_csrf'] = bin2hex(random_bytes(32));
    }

    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $action = htmlspecialchars($_SERVER['SCRIPT_NAME'] ?? '', ENT_QUOTES, 'UTF-8');
    $csrfToken = htmlspecialchars($_SESSION['seed_dummy_data_csrf'], ENT_QUOTES, 'UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Seed dummy sports users</title>
    </head>
    <body>
        <h1>Seed dummy sports users</h1>
        <p>
            This adds one coach and 5-15 players for each of the 15 sports.
            Every generated account will use the password <code>password123</code>.
        </p>
        <form method="post" action="<?= $action ?>">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <button type="submit">Add dummy users</button>
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
$expectedToken = $_SESSION['seed_dummy_data_csrf'] ?? '';
if (!is_string($submittedToken) || !is_string($expectedToken)
    || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    http_response_code(400);
    exit('Invalid request token. Reload the page and try again.');
}
unset($_SESSION['seed_dummy_data_csrf']);

require_once __DIR__ . '/../config/db.php';

$sportsQuery = $pdo->query(
    'SELECT id, name FROM sports WHERE id BETWEEN 1 AND 15 ORDER BY id'
);
$sports = $sportsQuery->fetchAll();
if (count($sports) !== 15) {
    throw new RuntimeException('Expected sports with IDs 1 through 15.');
}

$passwordHash = password_hash('password123', PASSWORD_DEFAULT);
$runId = bin2hex(random_bytes(8));
$insertUser = $pdo->prepare(
    'INSERT INTO users (name, email, password_hash, role, sport_id)
     VALUES (:name, :email, :password_hash, :role, :sport_id)'
);

$pdo->beginTransaction();

try {
    foreach ($sports as $sport) {
        $sportId = (int) $sport['id'];
        $sportName = $sport['name'];
        $emailSportName = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $sportName));

        $insertUser->execute([
            'name' => 'Coach ' . $sportName,
            'email' => "dummy.coach.{$emailSportName}.{$runId}@example.invalid",
            'password_hash' => $passwordHash,
            'role' => 'coach',
            'sport_id' => $sportId,
        ]);

        $playerCount = random_int(5, 15);
        for ($playerIndex = 1; $playerIndex <= $playerCount; $playerIndex++) {
            $playerNumber = random_int(1000, 999999);
            $insertUser->execute([
                'name' => "Player {$playerNumber} {$sportName}",
                'email' => "dummy.player.{$emailSportName}.{$runId}.{$playerIndex}@example.invalid",
                'password_hash' => $passwordHash,
                'role' => 'player',
                'sport_id' => $sportId,
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
echo 'injection successful!';
