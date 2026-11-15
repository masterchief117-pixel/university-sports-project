<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('player');
$pageTitle = 'Coach chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coach chat</h1>
<p>Players may have a one-on-one conversation with their coach.</p>
<p>Conversation chat UI requires an authorized conversation ID.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
