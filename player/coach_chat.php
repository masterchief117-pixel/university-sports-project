<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('player');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';
$sportId = isset($_SESSION['sport_id']) ? (int) $_SESSION['sport_id'] : null;
$chatContacts = get_chat_contacts($pdo, 'coach', $sportId);
$requestedTarget = filter_var($_GET['target_user_id'] ?? null, FILTER_VALIDATE_INT);
$chatTargetUserId = $requestedTarget ? (int) $requestedTarget : null;
$chatTitle = 'Coach chat';
$chatType = 'player_coach';
$pageTitle = 'Coach chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coach chat</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
