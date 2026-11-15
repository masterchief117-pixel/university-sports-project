<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$pageTitle = 'Head of Sports chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Head of Sports chat</h1>
<p>Coaches may have one-on-one conversations with the Head of Sports.</p>
<p>Conversation chat UI requires an authorized conversation ID.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
