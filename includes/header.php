<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$pageTitle = $pageTitle ?? 'University Sports Portal';
$role = $_SESSION['role'] ?? null;
$navigation = [
    'player' => [
        'Dashboard' => app_url('/player/dashboard.php'),
        'Team chat' => app_url('/player/team_chat.php'),
        'Coach chat' => app_url('/player/coach_chat.php'),
    ],
    'coach' => [
        'Dashboard' => app_url('/coach/dashboard.php'),
        'Team chat' => app_url('/coach/team_chat.php'),
        'Player chat' => app_url('/coach/player_chat.php'),
        'Coaches board' => app_url('/coach/coaches_board.php'),
        'Head of Sports chat' => app_url('/coach/hos_chat.php'),
    ],
    'hos' => [
        'Dashboard' => app_url('/hos/dashboard.php'),
        'Coach chat' => app_url('/hos/coach_chat.php'),
    ],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body data-base-path="<?= htmlspecialchars(rtrim(app_url('/'), '/'), ENT_QUOTES, 'UTF-8') ?>">
<header class="site-header">
    <a href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">University Sports Portal</a>
    <?php if ($role !== null && isset($navigation[$role])): ?>
        <nav aria-label="Main navigation">
            <?php foreach ($navigation[$role] as $label => $url): ?>
                <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
            <a href="<?= htmlspecialchars(app_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>">Log out</a>
        </nav>
    <?php else: ?>
        <nav aria-label="Main navigation">
            <a href="<?= htmlspecialchars(app_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>">Log in</a>
            <a href="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">Register</a>
        </nav>
    <?php endif; ?>
</header>
<main class="page-content">
