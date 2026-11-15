<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/chat_helpers.php';
$chatContacts = get_chat_contacts($pdo, 'hos');
$requestedTarget = filter_var($_GET['target_user_id'] ?? null, FILTER_VALIDATE_INT);
$chatTargetUserId = $requestedTarget ? (int) $requestedTarget : null;
$chatTitle = 'Head of Sports chat';
$chatType = 'coach_hos';
$pageTitle = 'Head of Sports chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Head of Sports chat</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
