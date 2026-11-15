<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
$pageTitle = 'Coach dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coach dashboard</h1>
<p>Open your team, player, coaches board, or Head of Sports conversations from the navigation.</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
