<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
require_once __DIR__ . '/../includes/user_accounts.php';

function return_to_coach_dashboard(string $message, string $type = 'success'): void
{
    $_SESSION['coach_dashboard_flash'] = $message;
    $_SESSION['coach_dashboard_flash_type'] = $type;
    header('Location: ' . app_url('/coach/dashboard.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return_to_coach_dashboard('Player could not be removed: submit the roster form to continue.', 'error');
}

$submittedToken = $_POST['csrf_token'] ?? '';
if (!is_string($submittedToken) || !csrf_token_is_valid($submittedToken)) {
    return_to_coach_dashboard('Player could not be removed: the request token was invalid.', 'error');
}

$player_id = filter_var($_POST['player_id'] ?? null, FILTER_VALIDATE_INT);
$sport_id = filter_var($_SESSION['sport_id'] ?? null, FILTER_VALIDATE_INT);
if ($player_id === false || $player_id < 1 || $sport_id === false || $sport_id < 1) {
    return_to_coach_dashboard('Player could not be removed: the player or coach sport is invalid.', 'error');
}

require_once __DIR__ . '/../config/db.php';

try {
    if (!drop_player_from_sport($pdo, (int) $player_id, (int) $sport_id)) {
        return_to_coach_dashboard(
            'Player could not be removed: they are not assigned to your sport.',
            'error'
        );
    }
} catch (PDOException $exception) {
    error_log($exception->getMessage());
    return_to_coach_dashboard('Player could not be removed. Please try again.', 'error');
}

return_to_coach_dashboard('Player removed from your sport roster.');
