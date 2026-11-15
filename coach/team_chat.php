<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$pageTitle = 'Team chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Team chat</h1>
<p>Team messages are available to authorized player and coach members of the same sport.</p>
<p>Conversation chat UI requires an authorized conversation ID.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
