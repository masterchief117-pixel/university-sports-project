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
    CURLOPT_HEADER => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_COOKIEFILE => '',
]);

$initialResponse = curl_exec($curl);
$curlError = curl_error($curl);
$initialResponse = is_string($initialResponse) ? $initialResponse : '';
$initialHeaderSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
$initialHeaders = substr($initialResponse, 0, $initialHeaderSize);
$initialBody = substr($initialResponse, $initialHeaderSize);
$hasInitialSessionCookie = preg_match('/^Set-Cookie:\s*PHPSESSID=[^;\r\n]+/im', $initialHeaders) === 1;
$csrfToken = '';
if (preg_match(
    '/<input type="hidden" name="csrf_token" value="([a-f0-9]{64})">/',
    $initialBody,
    $csrfMatches
)) {
    $csrfToken = $csrfMatches[1];
}

$csrfRejected = false;
if ($initialResponse !== '' && $csrfToken !== '') {
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query([
            'email' => 'hos@uni.edu',
            'password' => 'password123',
            'csrf_token' => 'invalid',
        ]),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $invalidResponse = curl_exec($curl);
    $invalidResponse = is_string($invalidResponse) ? $invalidResponse : '';
    $invalidHeaderSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $invalidHeaders = substr($invalidResponse, 0, $invalidHeaderSize);
    $invalidBody = substr($invalidResponse, $invalidHeaderSize);
    $csrfRejected = curl_getinfo($curl, CURLINFO_HTTP_CODE) === 200
        && strpos($invalidBody, 'Your form session expired.') !== false
        && preg_match('/^Location:/im', $invalidHeaders) !== 1;

    curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
        'email' => 'hos@uni.edu',
        'password' => 'password123',
        'csrf_token' => $csrfToken,
    ]));
}

$response = $initialResponse !== '' && $csrfToken !== ''
    ? curl_exec($curl)
    : false;
$response = is_string($response) ? $response : '';
$responseHeaderSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
$responseHeaders = substr($response, 0, $responseHeaderSize);
$responseBody = substr($response, $responseHeaderSize);
$httpStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
$curlError = $curlError !== '' ? $curlError : curl_error($curl);
curl_close($curl);

$hasSessionCookie = $hasInitialSessionCookie
    || preg_match('/^Set-Cookie:\s*PHPSESSID=[^;\r\n]+/im', $responseHeaders) === 1;
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

$wrongRoleRedirect = false;
if ($response !== '' && $hasExpectedRedirect) {
    curl_setopt_array($curl, [
        CURLOPT_URL => 'http://localhost/university_sports/coach/dashboard.php',
        CURLOPT_HTTPGET => true,
        CURLOPT_POSTFIELDS => null,
        CURLOPT_HTTPHEADER => [],
    ]);
    $wrongRoleResponse = curl_exec($curl);
    $wrongRoleResponse = is_string($wrongRoleResponse) ? $wrongRoleResponse : '';
    $wrongRoleHeaderSize = (int) curl_getinfo($curl, CURLINFO_HEADER_SIZE);
    $wrongRoleHeaders = substr($wrongRoleResponse, 0, $wrongRoleHeaderSize);
    $wrongRoleStatus = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    $wrongRoleLocation = '';
    if (preg_match('/^Location:\s*(.+)$/im', $wrongRoleHeaders, $matches)) {
        $wrongRoleLocation = trim($matches[1]);
    }

    $wrongRoleParts = parse_url($wrongRoleLocation);
    $wrongRolePath = is_array($wrongRoleParts) ? ($wrongRoleParts['path'] ?? '') : '';
    if ($wrongRolePath !== '' && substr($wrongRolePath, 0, 1) !== '/') {
        $wrongRolePath = dirname('/university_sports/coach/dashboard.php') . '/' . $wrongRolePath;
    }
    $expectedOwnDashboard = $normalizePath('/university_sports/hos/dashboard.php');
    $wrongRoleSameHost = !isset($wrongRoleParts['host'])
        || strtolower($wrongRoleParts['host']) === strtolower((string) parse_url($loginUrl, PHP_URL_HOST));
    $wrongRoleRedirect = $wrongRoleStatus >= 300
        && $wrongRoleStatus < 400
        && $wrongRoleSameHost
        && $normalizePath($wrongRolePath) === $expectedOwnDashboard;
}

$passed = $response !== ''
    && $hasSessionCookie
    && $csrfRejected
    && $hasExpectedRedirect
    && $wrongRoleRedirect
    && $httpStatus >= 300
    && $httpStatus < 400;

$details = [
    'HTTP status: ' . $httpStatus,
    'PHPSESSID cookie: ' . ($hasSessionCookie ? 'present' : 'missing'),
    'Missing/invalid CSRF rejected: ' . ($csrfRejected ? 'yes' : 'no'),
    'Location header: ' . ($location !== '' ? $location : '(missing)'),
    'Expected Head of Sports dashboard redirect: ' . $expectedPath,
    'Actual redirect path: ' . ($locationPath !== '' ? $actualPath : '(missing)'),
    'Wrong-role dashboard redirects to own dashboard: ' . ($wrongRoleRedirect ? 'yes' : 'no'),
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
