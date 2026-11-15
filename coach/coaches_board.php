<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$pageTitle = 'Coaches board';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coaches board</h1>
<p>This group conversation is restricted to coaches.</p>
<p>Conversation chat UI requires an authorized conversation ID.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
