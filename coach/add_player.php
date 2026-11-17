<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_accounts.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$tokenInput = $_POST['csrf_token'] ?? '';
$csrfToken = is_string($tokenInput) ? $tokenInput : '';
if (!csrf_token_is_valid($csrfToken)) {
    http_response_code(403);
    exit('Invalid request token.');
}

$nameInput = $_POST['name'] ?? '';
$emailInput = $_POST['email'] ?? '';
$passwordInput = $_POST['password'] ?? '';
$name = is_string($nameInput) ? trim($nameInput) : '';
$email = is_string($emailInput) ? trim($emailInput) : '';
$password = is_string($passwordInput) ? $passwordInput : '';
$sportId = $_SESSION['sport_id'] ?? null;

if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 254 || strlen($password) < 10 || $sportId === null) {
    $_SESSION['coach_dashboard_flash'] = 'Enter valid player details and try again.';
    $_SESSION['coach_dashboard_flash_type'] = 'error';
    header('Location: ' . app_url('/coach/dashboard.php'));
    exit;
}

try {
    create_player_account($pdo, $name, $email, $password, (int) $sportId);
} catch (PDOException $exception) {
    if ($exception->getCode() !== '23000'
        || (int) ($exception->errorInfo[1] ?? 0) !== 1062) {
        throw $exception;
    }

    $_SESSION['coach_dashboard_flash'] = 'An account with that email already exists.';
    $_SESSION['coach_dashboard_flash_type'] = 'error';
    header('Location: ' . app_url('/coach/dashboard.php'));
    exit;
}

$_SESSION['coach_dashboard_flash'] = 'Player added successfully!';
$_SESSION['coach_dashboard_flash_type'] = 'success';
header('Location: ' . app_url('/coach/dashboard.php'));
exit;
