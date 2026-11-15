<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('player');
$pageTitle = 'Player dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Player dashboard</h1>
<p>Use team chat to message your team or coach chat to message your coach.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
