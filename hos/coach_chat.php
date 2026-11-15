<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');
$pageTitle = 'Coach chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coach chat</h1>
<p>The Head of Sports may have one-on-one conversations with coaches.</p>
<p>Conversation chat UI requires an authorized conversation ID.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
