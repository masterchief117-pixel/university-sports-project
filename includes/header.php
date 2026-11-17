<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$pageTitle = $pageTitle ?? 'University Sports Portal';
$bodyClass = $bodyClass ?? '';
$stylePath = __DIR__ . '/../assets/css/style.css';
$role = $_SESSION['role'] ?? null;
$navigation = [
    'player' => [
        'Dashboard' => app_url('/player/dashboard.php'),
        'Chat' => app_url('/player/chat.php'),
        'Team chat' => app_url('/player/team_chat.php'),
    ],
    'coach' => [
        'Dashboard' => app_url('/coach/dashboard.php'),
        'Chat' => app_url('/coach/chat.php'),
        'Team chat' => app_url('/coach/team_chat.php'),
        'Coaches board' => app_url('/coach/coaches_board.php'),
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
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(
        app_url('/assets/css/style.css') . '?v=' . filemtime($stylePath),
        ENT_QUOTES,
        'UTF-8'
    ) ?>">
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>"
      data-base-path="<?= htmlspecialchars(rtrim(app_url('/'), '/'), ENT_QUOTES, 'UTF-8') ?>">
<header class="site-header">
    <a href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">University Sports Portal</a>
    <?php if ($role !== null && isset($navigation[$role])): ?>
        <nav aria-label="Main navigation">
            <?php foreach ($navigation[$role] as $label => $url): ?>
                <a href="<?= htmlspecialchars($url, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                </a>
            <?php endforeach; ?>
            <form class="logout-form" method="post"
                  action="<?= htmlspecialchars(app_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Log out</button>
            </form>
        </nav>
    <?php else: ?>
        <nav aria-label="Main navigation">
            <a href="<?= htmlspecialchars(app_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>">Log in</a>
            <a href="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">Register</a>
        </nav>
    <?php endif; ?>
</header>
<main class="page-content">
