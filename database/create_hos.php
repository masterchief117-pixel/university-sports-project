<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Initial HOS account creation is available only from the command line.');
}

require_once __DIR__ . '/../config/db.php';

if ((int) $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'hos'")->fetchColumn() > 0) {
    exit("An HOS account already exists. No changes were made.\n");
}

echo 'HOS full name: ';
$name = trim((string) fgets(STDIN));
echo 'HOS email: ';
$email = trim((string) fgets(STDIN));
echo 'HOS password (at least 12 characters): ';
$password = trim((string) fgets(STDIN));

if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 254 || strlen($password) < 12) {
    exit("Enter a valid name, email, and password. No account was created.\n");
}

$insert = $pdo->prepare(
    "INSERT INTO users (name, email, password_hash, role, sport_id)
     VALUES (:name, :email, :password_hash, 'hos', NULL)"
);
$insert->execute([
    'name' => $name,
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

echo 'The initial HOS account was created.' . PHP_EOL;
