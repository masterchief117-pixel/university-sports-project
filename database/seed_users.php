<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This destructive development seeder is available only from the command line.');
}
if (($argv[1] ?? '') !== '--reset-test-users') {
    exit("This clears all user accounts. Re-run with --reset-test-users only on a development database.\n");
}

require_once __DIR__ . '/../config/db.php';

$pdo->exec('TRUNCATE TABLE remember_tokens');
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
