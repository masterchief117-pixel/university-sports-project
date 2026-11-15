<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$chatTitle = 'Team chat';
$chatType = 'team';
$chatContacts = null;
$chatTargetUserId = null;
$pageTitle = 'Team chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Team chat</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
