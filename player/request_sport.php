<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

require_once __DIR__ . '/../includes/auth_check.php';
require_role('player');

function return_to_player_dashboard(string $message, string $type = 'success'): void
{
    $_SESSION['player_dashboard_flash'] = $message;
    $_SESSION['player_dashboard_flash_type'] = $type;
    header('Location: ' . app_url('/player/dashboard.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return_to_player_dashboard('Submit the transfer portal form to request a sport.', 'error');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($expectedToken)
    || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    return_to_player_dashboard(
        'Transfer request could not be sent: the request token was invalid.',
        'error'
    );
}

$requestedSportInput = $_POST['requested_sport_id'] ?? null;
$requestedSportId = is_string($requestedSportInput)
    ? filter_var($requestedSportInput, FILTER_VALIDATE_INT)
    : false;
if ($requestedSportId === false || $requestedSportId < 1) {
    return_to_player_dashboard('Select a valid sport for your transfer request.', 'error');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_accounts.php';

try {
    $pdo->beginTransaction();

    $playerQuery = $pdo->prepare(
        "SELECT id, sport_id FROM users
         WHERE id = :user_id AND role = 'player'
         FOR UPDATE"
    );
    $playerQuery->execute(['user_id' => (int) $_SESSION['user_id']]);
    $player = $playerQuery->fetch(PDO::FETCH_ASSOC);
    if (!$player || $player['sport_id'] !== null) {
        $pdo->rollBack();
        return_to_player_dashboard(
            'Transfer requests are only available to unassigned players.',
            'error'
        );
    }

    $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :sport_id');
    $sportQuery->execute(['sport_id' => $requestedSportId]);
    if ($sportQuery->fetchColumn() === false) {
        $pdo->rollBack();
        return_to_player_dashboard('Select a valid sport for your transfer request.', 'error');
    }

    if (!request_sport_for_unassigned_player(
        $pdo,
        (int) $_SESSION['user_id'],
        $requestedSportId
    )) {
        $pdo->rollBack();
        return_to_player_dashboard(
            'Transfer requests are only available to unassigned players.',
            'error'
        );
    }

    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    return_to_player_dashboard('Transfer request could not be sent. Please try again.', 'error');
}

return_to_player_dashboard('Transfer request sent to Head of Sports!');
