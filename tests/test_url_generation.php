<?php
declare(strict_types=1);

function renderLandingPage(string $scriptName): string
{
    $previousScriptName = $_SERVER['SCRIPT_NAME'] ?? null;
    $_SERVER['SCRIPT_NAME'] = $scriptName;

    ob_start();
    require __DIR__ . '/../index.php';
    $html = ob_get_clean();

    if ($previousScriptName === null) {
        unset($_SERVER['SCRIPT_NAME']);
    } else {
        $_SERVER['SCRIPT_NAME'] = $previousScriptName;
    }

    return $html;
}

$cases = [
    ['\\index.php', '/assets/css/style.css', '/auth/login.php'],
    [
        '\\university_sports\\index.php',
        '/university_sports/assets/css/style.css',
        '/university_sports/auth/login.php',
    ],
    [
        '\\university_sports\\photographer\\dashboard.php',
        '/university_sports/assets/css/style.css',
        '/university_sports/auth/login.php',
    ],
];

foreach ($cases as [$scriptName, $stylesheetPath, $loginPath]) {
    $html = renderLandingPage($scriptName);

    if (preg_match(
        '~href="' . preg_quote($stylesheetPath, '~') . '(?:\?[^"]*)?"~',
        $html
    ) !== 1
        || strpos($html, 'href="' . $loginPath . '"') === false) {
        fwrite(STDERR, 'Landing page URL generation failed for ' . $scriptName . PHP_EOL);
        exit(1);
    }
}

echo 'Landing page URLs passed for root and XAMPP subdirectory deployments.' . PHP_EOL;
