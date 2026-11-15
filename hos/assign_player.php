<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');

function return_to_hos_dashboard(string $message): void
{
    $_SESSION['hos_dashboard_flash'] = $message;
    header('Location: ' . app_url('/hos/dashboard.php'));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    return_to_hos_dashboard('Submit the transfer portal form to assign a player.');
}

$submittedToken = $_POST['csrf_token'] ?? '';
$expectedToken = $_SESSION['csrf_token'] ?? '';
if (!is_string($submittedToken) || !is_string($expectedToken)
    || $expectedToken === '' || !hash_equals($expectedToken, $submittedToken)) {
    return_to_hos_dashboard('Player assignment failed: the request token was invalid.');
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
    return_to_hos_dashboard('Select a valid player and sport.');
}

require_once __DIR__ . '/../config/db.php';

try {
    $pdo->beginTransaction();

    $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :sport_id');
    $sportQuery->execute(['sport_id' => $sportId]);
    if ($sportQuery->fetchColumn() === false) {
        $pdo->rollBack();
        return_to_hos_dashboard('Select a valid sport.');
    }

    $playerQuery = $pdo->prepare(
        "SELECT id FROM users
         WHERE id = :player_id AND role = 'player' AND sport_id IS NULL
         FOR UPDATE"
    );
    $playerQuery->execute(['player_id' => $playerId]);
    if ($playerQuery->fetchColumn() === false) {
        $pdo->rollBack();
        return_to_hos_dashboard('Player is no longer unassigned and could not be placed.');
    }

    $update = $pdo->prepare(
        "UPDATE users
         SET sport_id = :sport_id, requested_sport_id = NULL
         WHERE id = :player_id AND role = 'player' AND sport_id IS NULL"
    );
    $update->execute([
        'sport_id' => $sportId,
        'player_id' => $playerId,
    ]);

    if ($update->rowCount() !== 1) {
        $pdo->rollBack();
        return_to_hos_dashboard('Player assignment failed. Please refresh and try again.');
    }

    $pdo->commit();
} catch (PDOException $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($exception->getMessage());
    return_to_hos_dashboard('Player assignment failed. Please try again.');
}

return_to_hos_dashboard('Player assigned successfully.');
