<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
header('Location: ' . app_url('/auth/login.php'));
exit;
