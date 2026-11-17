<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');

function return_to_hos_dashboard(string $message, string $type = 'success'): void
{
    $_SESSION['hos_dashboard_flash'] = $message;
    $_SESSION['hos_dashboard_flash_type'] = $type;
    header('Location: ' . app_url('/hos/dashboard.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return_to_hos_dashboard('Submit the transfer portal form to assign a player.', 'error');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($expectedToken)
    || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    return_to_hos_dashboard('Player assignment failed: the request token was invalid.', 'error');
}

$playerIdInput = $_POST['player_id'] ?? null;
$sportIdInput = $_POST['sport_id'] ?? null;
$playerId = is_string($playerIdInput)
    ? filter_var($playerIdInput, FILTER_VALIDATE_INT)
    : false;
$sportId = is_string($sportIdInput)
    ? filter_var($sportIdInput, FILTER_VALIDATE_INT)
    : false;
if ($playerId === false || $playerId < 1 || $sportId === false || $sportId < 1) {
    return_to_hos_dashboard('Select a valid player and sport.', 'error');
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_accounts.php';

try {
    $pdo->beginTransaction();

    $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :sport_id');
    $sportQuery->execute(['sport_id' => $sportId]);
    if ($sportQuery->fetchColumn() === false) {
        $pdo->rollBack();
        return_to_hos_dashboard('Select a valid sport.', 'error');
    }

    $playerQuery = $pdo->prepare(
        "SELECT id FROM users
         WHERE id = :player_id AND role = 'player' AND sport_id IS NULL
         FOR UPDATE"
    );
    $playerQuery->execute(['player_id' => $playerId]);
    if ($playerQuery->fetchColumn() === false) {
        $pdo->rollBack();
        return_to_hos_dashboard(
            'Player is no longer unassigned and could not be placed.',
            'error'
        );
    }

    if (!assign_unassigned_player($pdo, $playerId, $sportId)) {
        $pdo->rollBack();
        return_to_hos_dashboard('Player assignment failed. Please refresh and try again.', 'error');
    }

    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    return_to_hos_dashboard('Player assignment failed. Please try again.', 'error');
}

return_to_hos_dashboard('Player assigned successfully.');
