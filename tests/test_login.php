<?php
declare(strict_types=1);

function printLoginTestResult(bool $passed, array $details, bool $isCli): void
{
    $message = $passed
        ? '✅ LOGIN TEST PASSED: Authentication and Routing are working!'
        : '❌ LOGIN TEST FAILED';

    if ($isCli) {
        echo $passed
            ? "\033[1;32m{$message}\033[0m" . PHP_EOL
            : "\033[1;31m{$message}\033[0m" . PHP_EOL;
        foreach ($details as $detail) {
            echo $detail . PHP_EOL;
        }
        return;
    }

    http_response_code($passed ? 200 : 500);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    $color = $passed ? '#176b35' : '#b42318';
    echo '<!DOCTYPE html><html lang="en"><meta charset="utf-8"><title>Login test</title>';
    echo '<body style="font-family: sans-serif; margin: 2rem">';
    echo '<h1 style="color: ' . $color . '">' . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '</h1>';
    echo '<ul>';
    foreach ($details as $detail) {
        echo '<li>' . htmlspecialchars($detail, ENT_QUOTES, 'UTF-8') . '</li>';
    }
    echo '</ul></body></html>';
}

$isCli = PHP_SAPI === 'cli';
if (!$isCli && !in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) {
    http_response_code(403);
    exit('This test is available only from localhost.');
}

if (!function_exists('curl_init')) {
    printLoginTestResult(false, ['PHP cURL extension is not enabled.'], $isCli);
    exit($isCli ? 1 : 0);
}

$loginUrl = 'http://localhost/university_sports/auth/login.php';
$curl = curl_init($loginUrl);
curl_setopt_array($curl, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query([
        'email' => 'hos@uni.edu',
        'password' => 'password123',
    ]),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/x-www-form-urlencoded',
    ],
    CURLOPT_HEADER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
]);

$response = curl_exec($curl);
$curlError = curl_error($curl);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$headerSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
curl_close($curl);

$response = is_string($response) ? $response : '';
$responseHeaders = substr($response, 0, $headerSize);
$responseBody = substr($response, $headerSize);
$hasSessionCookie = preg_match('/^Set-Cookie:\s*PHPSESSID=[^;\r\n]+/im', $responseHeaders) === 1;
$location = '';

if (preg_match('/^Location:\s*(.+)$/im', $responseHeaders, $matches)) {
    $location = trim($matches[1]);
}

$normalizePath = static function (string $path): string {
    $segments = [];
    foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
        if ($segment === '' || $segment === '.') {
            continue;
        }
        if ($segment === '..') {
            array_pop($segments);
            continue;
        }
        $segments[] = $segment;
    }

    return '/' . implode('/', $segments);
};

$loginPath = (string) parse_url($loginUrl, PHP_URL_PATH);
$expectedPath = $normalizePath(dirname($loginPath) . '/../hos/dashboard.php');
$locationParts = parse_url($location);
$locationPath = is_array($locationParts) ? ($locationParts['path'] ?? '') : '';

if ($locationPath !== '' && substr($locationPath, 0, 1) !== '/') {
    $locationPath = dirname($loginPath) . '/' . $locationPath;
}
$actualPath = $normalizePath($locationPath);
$sameHost = !isset($locationParts['host'])
    || strtolower($locationParts['host']) === strtolower((string) parse_url($loginUrl, PHP_URL_HOST));
$hasExpectedRedirect = $location !== ''
    && $sameHost
    && $actualPath === $expectedPath;
$passed = $response !== ''
    && $hasSessionCookie
    && $hasExpectedRedirect
    && $httpStatus >= 300
    && $httpStatus < 400;

$details = [
    'HTTP status: ' . $httpStatus,
    'PHPSESSID cookie: ' . ($hasSessionCookie ? 'present' : 'missing'),
    'Location header: ' . ($location !== '' ? $location : '(missing)'),
    'Expected redirect (resolved from ../hos/dashboard.php): ' . $expectedPath,
    'Actual redirect path: ' . ($locationPath !== '' ? $actualPath : '(missing)'),
];

if ($curlError !== '') {
    $details[] = 'cURL error: ' . $curlError;
}
if (!$passed) {
    $details[] = 'Response headers: ' . str_replace(["\r", "\n"], [' ', ' | '], trim($responseHeaders));
    if ($responseBody !== '') {
        $details[] = 'Response body (first 2000 bytes): ' . substr($responseBody, 0, 2000);
    }
}

printLoginTestResult($passed, $details, $isCli);
exit($isCli ? ($passed ? 0 : 1) : 0);
