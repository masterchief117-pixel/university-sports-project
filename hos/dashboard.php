<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');
$pageTitle = 'Head of Sports dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Head of Sports dashboard</h1>
<p>Use coach chat to communicate with coaches.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
