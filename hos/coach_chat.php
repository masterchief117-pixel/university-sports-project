<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');

require_once __DIR__ . '/../config/db.php';
$coachQuery = $pdo->query("SELECT users.id, users.name, sports.name AS sport_name FROM users LEFT JOIN sports ON users.sport_id = sports.id WHERE users.role = 'coach' ORDER BY sports.name ASC");
$chatContacts = $coachQuery->fetchAll(PDO::FETCH_ASSOC);
$requestedTarget = filter_var(
    $_GET['coach_id'] ?? $_GET['target_user_id'] ?? null,
    FILTER_VALIDATE_INT
);
$chatTargetUserId = $requestedTarget ? (int) $requestedTarget : null;
$chatTitle = 'Coach chat';
$chatType = 'coach_hos';
$pageTitle = 'Coach chat';
require __DIR__ . '/../includes/header.php';
?>
<h1>Coach chat</h1>
<?php require __DIR__ . '/../includes/chat_panel.php'; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
