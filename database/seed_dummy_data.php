<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This development seeder is available only from the command line.');
}

echo "Add dummy accounts with the shared development password? Type SEED DUMMY USERS: ";
if (trim((string) fgets(STDIN)) !== 'SEED DUMMY USERS') {
    exit("No changes were made.\n");
}

require_once __DIR__ . '/../config/db.php';

$sportsQuery = $pdo->query(
    'SELECT id, name FROM sports ORDER BY id'
);
$sports = $sportsQuery->fetchAll();
if ($sports === []) {
    throw new RuntimeException('Add sports before seeding development users.');
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

echo 'Dummy development accounts were added.' . PHP_EOL;
