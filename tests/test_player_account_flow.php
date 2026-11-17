<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/user_accounts.php';

$pdo = new PDO('sqlite::memory:', null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec(
    'CREATE TABLE users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL,
        sport_id INTEGER NULL,
        requested_sport_id INTEGER NULL
    )'
);

$password = 'test-password-123';
create_player_account($pdo, 'Test Player', 'player@example.invalid', $password, 7);
$playerId = (int) $pdo->lastInsertId();
$playerQuery = $pdo->prepare('SELECT * FROM users WHERE id = :id');
$playerQuery->execute(['id' => $playerId]);
$player = $playerQuery->fetch();

if (!$player
    || $player['role'] !== 'player'
    || (int) $player['sport_id'] !== 7
    || !password_verify($password, $player['password_hash'])) {
    fwrite(STDERR, 'Player account creation did not preserve role, sport, and password hashing.' . PHP_EOL);
    exit(1);
}

if (drop_player_from_sport($pdo, $playerId, 8)) {
    fwrite(STDERR, 'A player assigned to another sport was incorrectly dropped.' . PHP_EOL);
    exit(1);
}

if (assign_unassigned_player($pdo, $playerId, 9)) {
    fwrite(STDERR, 'An already assigned player was incorrectly reassigned.' . PHP_EOL);
    exit(1);
}

if (request_sport_for_unassigned_player($pdo, $playerId, 9)) {
    fwrite(STDERR, 'An already assigned player was incorrectly allowed to request a sport.' . PHP_EOL);
    exit(1);
}

if (!drop_player_from_sport($pdo, $playerId, 7)) {
    fwrite(STDERR, 'The assigned player was not moved to free-agent status.' . PHP_EOL);
    exit(1);
}

if (!request_sport_for_unassigned_player($pdo, $playerId, 9)) {
    fwrite(STDERR, 'The unassigned player could not request a sport.' . PHP_EOL);
    exit(1);
}

$playerQuery->execute(['id' => $playerId]);
$requestedPlayer = $playerQuery->fetch();
if (!$requestedPlayer || (int) $requestedPlayer['requested_sport_id'] !== 9) {
    fwrite(STDERR, 'The requested sport was not stored for the player.' . PHP_EOL);
    exit(1);
}

if (!assign_unassigned_player($pdo, $playerId, 9)) {
    fwrite(STDERR, 'The unassigned player could not be assigned to the requested sport.' . PHP_EOL);
    exit(1);
}

$playerQuery->execute(['id' => $playerId]);
$assignedPlayer = $playerQuery->fetch();
$playerCount = (int) $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
if (!$assignedPlayer
    || (int) $assignedPlayer['sport_id'] !== 9
    || $assignedPlayer['requested_sport_id'] !== null
    || $playerCount !== 1) {
    fwrite(STDERR, 'Assignment did not set the sport and clear the pending request.' . PHP_EOL);
    exit(1);
}

if (!drop_player_from_sport($pdo, $playerId, 9)) {
    fwrite(STDERR, 'The reassigned player could not be dropped from the new sport.' . PHP_EOL);
    exit(1);
}

$playerQuery->execute(['id' => $playerId]);
$droppedPlayer = $playerQuery->fetch();
if (!$droppedPlayer || $droppedPlayer['sport_id'] !== null || $playerCount !== 1) {
    fwrite(STDERR, 'Dropping a player deleted the account or left its sport assignment intact.' . PHP_EOL);
    exit(1);
}

$photographerPassword = 'temporary-password-456';
create_photographer_account(
    $pdo,
    'Test Photographer',
    'photographer@example.invalid',
    $photographerPassword
);
$photographerQuery = $pdo->prepare('SELECT * FROM users WHERE email = :email');
$photographerQuery->execute(['email' => 'photographer@example.invalid']);
$photographer = $photographerQuery->fetch();
if (!$photographer
    || $photographer['role'] !== 'photographer'
    || $photographer['sport_id'] !== null
    || !password_verify($photographerPassword, $photographer['password_hash'])) {
    fwrite(STDERR, 'Photographer account creation did not preserve role or password hashing.' . PHP_EOL);
    exit(1);
}

echo 'Player creation, transfer assignment, and soft-drop data flow passed.' . PHP_EOL;
