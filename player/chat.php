<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('player');
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';

$sportId = isset($_SESSION['sport_id']) ? (int) $_SESSION['sport_id'] : null;
$currentUserId = (int) $_SESSION['user_id'];
$chatContacts = [];
if ($sportId !== null) {
    foreach (get_chat_contacts($pdo, 'player', $sportId) as $player) {
        if ((int) $player['id'] === $currentUserId) {
            continue;
        }
        $player['conversation_type'] = 'player_player';
        $chatContacts[] = $player;
    }
    foreach (get_chat_contacts($pdo, 'coach', $sportId) as $coach) {
        $coach['conversation_type'] = 'player_coach';
        $chatContacts[] = $coach;
    }
}

$requestedTargetInput = $_GET['target_user_id'] ?? null;
$requestedTarget = is_string($requestedTargetInput)
    ? filter_var($requestedTargetInput, FILTER_VALIDATE_INT)
    : false;
$requestedType = $_GET['conversation_type'] ?? '';
$chatType = is_string($requestedType)
    && in_array($requestedType, ['player_player', 'player_coach'], true)
    ? $requestedType
    : 'player_player';
$chatTargetUserId = $requestedTarget && $requestedTarget > 0
    ? (int) $requestedTarget
    : null;
$chatTeamUrl = $sportId === null ? null : app_url('/player/team_chat.php');
$chatTitle = 'Chat';
$pageTitle = 'Chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Chat</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
