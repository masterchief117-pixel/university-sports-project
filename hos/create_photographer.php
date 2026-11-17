<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Method not allowed.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !csrf_token_is_valid($submittedToken)) {
    $_SESSION['hos_dashboard_flash'] = 'Your form session expired. Please try again.';
    $_SESSION['hos_dashboard_flash_type'] = 'error';
    header('Location: ' . app_url('/hos/dashboard.php#photographers'), true, 303);
    exit;
}

$nameInput = $_POST['name'] ?? '';
$emailInput = $_POST['email'] ?? '';
$passwordInput = $_POST['password'] ?? '';
$name = is_string($nameInput) ? trim($nameInput) : '';
$email = is_string($emailInput) ? trim($emailInput) : '';
$password = is_string($passwordInput) ? $passwordInput : '';

if ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
    || strlen($email) > 254 || strlen($password) < 12) {
    $_SESSION['hos_dashboard_flash'] =
        'Enter a valid name and email, and choose a password with at least 12 characters.';
    $_SESSION['hos_dashboard_flash_type'] = 'error';
    header('Location: ' . app_url('/hos/dashboard.php#photographers'), true, 303);
    exit;
}

require_once __DIR__ . '/../includes/user_accounts.php';
$pdo = get_database_connection();

try {
    create_photographer_account($pdo, $name, $email, $password);
    $_SESSION['hos_dashboard_flash'] = 'Photographer account created. Share the temporary password securely.';
    $_SESSION['hos_dashboard_flash_type'] = 'success';
} catch (PDOException $exception) {
    if ($exception->getCode() === '23000' && (int) ($exception->errorInfo[1] ?? 0) === 1062) {
        $_SESSION['hos_dashboard_flash'] = 'An account with that email already exists.';
        $_SESSION['hos_dashboard_flash_type'] = 'error';
    } else {
        error_log($exception->getMessage());
        $_SESSION['hos_dashboard_flash'] = 'We could not create that account right now. Please try again.';
        $_SESSION['hos_dashboard_flash_type'] = 'error';
    }
}

header('Location: ' . app_url('/hos/dashboard.php#photographers'), true, 303);
exit;
