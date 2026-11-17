<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

$sportId = isset($_SESSION['sport_id']) ? (int) $_SESSION['sport_id'] : null;
$chatContacts = [];
foreach (get_chat_contacts($pdo, 'player', $sportId) as $player) {
    $player['conversation_type'] = 'player_coach';
    $chatContacts[] = $player;
}
foreach (get_chat_contacts($pdo, 'hos') as $hos) {
    $hos['conversation_type'] = 'coach_hos';
    $chatContacts[] = $hos;
}

$requestedTargetInput = $_GET['target_user_id'] ?? null;
$requestedTarget = is_string($requestedTargetInput)
    ? filter_var($requestedTargetInput, FILTER_VALIDATE_INT)
    : false;
$requestedType = $_GET['conversation_type'] ?? '';
$chatType = is_string($requestedType)
    && in_array($requestedType, ['player_coach', 'coach_hos'], true)
    ? $requestedType
    : 'player_coach';
$chatTargetUserId = $requestedTarget && $requestedTarget > 0
    ? (int) $requestedTarget
    : null;
$chatTeamUrl = $sportId === null ? null : app_url('/coach/team_chat.php');
$chatTitle = 'Chat';
$pageTitle = 'Chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Chat</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
