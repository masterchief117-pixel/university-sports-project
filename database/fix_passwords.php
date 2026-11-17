<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This password reset utility is available only from the command line.');
}

echo "Reset every account password? Type RESET ALL PASSWORDS to continue: ";
if (trim((string) fgets(STDIN)) !== 'RESET ALL PASSWORDS') {
    exit("No changes were made.\n");
}

echo "Enter the new password for all accounts: ";
$password = trim((string) fgets(STDIN));
if (strlen($password) < 12) {
    exit("Use a password with at least 12 characters. No changes were made.\n");
}

require_once __DIR__ . '/../config/db.php';

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE users SET password_hash = :hash');
    $update->execute(['hash' => password_hash($password, PASSWORD_DEFAULT)]);
    $pdo->exec('DELETE FROM remember_tokens');
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

echo 'All account passwords were updated and remember tokens revoked.' . PHP_EOL;
