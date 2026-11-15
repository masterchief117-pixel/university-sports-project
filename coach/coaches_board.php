<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$chatTitle = 'Coaches board';
$chatType = 'coaches_board';
$chatContacts = null;
$chatTargetUserId = null;
$pageTitle = 'Coaches board';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coaches board</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
