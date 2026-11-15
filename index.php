<?php
declare(strict_types=1);

$scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/index.php');
$basePath = rtrim(dirname($scriptPath), '/.');
$basePath = $basePath === '' ? '' : '/' . ltrim($basePath, '/');
$stylesheetUrl = $basePath . '/assets/css/style.css';
$loginUrl = $basePath . '/auth/login.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#102f59">
    <title>University Sports Portal</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($stylesheetUrl, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="landing-page">
    <main class="landing-shell">
        <section class="landing-content" aria-labelledby="landing-title">
            <p class="landing-eyebrow">One team. Every sport.</p>
            <h1 id="landing-title">Welcome to the University Sports Portal</h1>
            <p class="landing-description">
                A shared space that connects players, coaches, and the Head of Sports
                to keep university athletics moving forward.
            </p>
            <a class="landing-login" href="<?= htmlspecialchars($loginUrl, ENT_QUOTES, 'UTF-8') ?>">
                Login
            </a>
        </section>
    </main>
</body>
</html>
