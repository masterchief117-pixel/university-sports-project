<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$pageTitle = 'Player chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Player chat</h1>
<p>Coaches may have one-on-one conversations with their players.</p>
<p>Conversation chat UI requires an authorized conversation ID.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
