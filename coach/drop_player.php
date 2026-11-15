<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_login();

if (($_SESSION['role'] ?? '') !== 'coach') {
    $roleDestinations = [
        'player' => '/player/dashboard.php',
        'hos' => '/hos/dashboard.php',
    ];
    $destination = $roleDestinations[$_SESSION['role'] ?? ''] ?? '/auth/login.php';
    header('Location: ' . app_url($destination));
    exit;
}

function return_to_coach_dashboard(string $message): void
{
    $_SESSION['coach_dashboard_flash'] = $message;
    header('Location: ' . app_url('/coach/dashboard.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return_to_coach_dashboard('Player could not be removed: submit the roster form to continue.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($expectedToken)
    || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    return_to_coach_dashboard('Player could not be removed: the request token was invalid.');
}

$player_id = filter_var($_POST['player_id'] ?? null, FILTER_VALIDATE_INT);
$sport_id = filter_var($_SESSION['sport_id'] ?? null, FILTER_VALIDATE_INT);
if ($player_id === false || $player_id < 1 || $sport_id === false || $sport_id < 1) {
    return_to_coach_dashboard('Player could not be removed: the player or coach sport is invalid.');
}

require_once __DIR__ . '/../config/db.php';

try {
    $stmt = $pdo->prepare(
        "UPDATE users
         SET sport_id = NULL
         WHERE id = :player_id AND role = 'player' AND sport_id = :sport_id"
    );
    $stmt->execute([
        'player_id' => $player_id,
        'sport_id' => $sport_id,
    ]);

    if ($stmt->rowCount() !== 1) {
        return_to_coach_dashboard('Player could not be removed: they are not assigned to your sport.');
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    return_to_coach_dashboard('Player could not be removed. Please try again.');
}

return_to_coach_dashboard('Player removed from your sport roster.');
