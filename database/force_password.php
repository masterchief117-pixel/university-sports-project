<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This password reset utility is available only from the command line.');
}

echo 'New password for hos@uni.edu: ';
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) {
    exit("Use a password with at least 12 characters. No changes were made.\n");
}

require_once __DIR__ . '/../config/db.php';

$pdo->beginTransaction();
try {
    $update = $pdo->prepare(
        'UPDATE users SET password_hash = :hash WHERE email = :email AND role = :role'
    );
    $update->execute([
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'email' => 'hos@uni.edu',
        'role' => 'hos',
    ]);

    if ($update->rowCount() !== 1) {
        throw new RuntimeException('HOS account hos@uni.edu was not found or updated.');
    }

    $revokeTokens = $pdo->prepare(
        'DELETE FROM remember_tokens WHERE user_id = (SELECT id FROM users WHERE email = :email)'
    );
    $revokeTokens->execute(['email' => 'hos@uni.edu']);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo 'The HOS password was updated and remember tokens revoked.' . PHP_EOL;
