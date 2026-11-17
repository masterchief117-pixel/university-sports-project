<?php
declare(strict_types=1);

if (!function_exists('curl_init')) {
    fwrite(STDERR, 'PHP cURL is required for the chat authorization test.' . PHP_EOL);
    exit(1);
}

$baseUrl = 'http://localhost/university_sports';
if (strtolower((string) parse_url($baseUrl, PHP_URL_HOST)) !== 'localhost') {
    fwrite(STDERR, 'The chat authorization test only supports localhost.' . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$userIds = [];
$conversationIds = [];
$curlHandles = [];
$token = bin2hex(random_bytes(8));
$password = bin2hex(random_bytes(16));
$emails = [
    'player' => 'chat-player-' . $token . '@example.invalid',
    'coach' => 'chat-coach-' . $token . '@example.invalid',
    'hos' => 'chat-hos-' . $token . '@example.invalid',
];

function assert_chat_authorization(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function chat_test_request(
    $curl,
    string $url,
    string $method = 'GET',
    ?array $formFields = null,
    ?array $jsonPayload = null,
    ?string $csrfToken = null
): array {
    $responseHeaders = [];
    $headers = [];
    if ($jsonPayload !== null) {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'X-CSRF-Token: ' . (string) $csrfToken;
    }

    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPGET => $method === 'GET',
        CURLOPT_POST => $method === 'POST',
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
            if (stripos($headerLine, 'Location:') === 0) {
                $responseHeaders['Location'] = trim(substr($headerLine, strlen('Location:')));
            }

            return strlen($headerLine);
        },
    ]);

    if ($jsonPayload !== null) {
        curl_setopt(
            $curl,
            CURLOPT_POSTFIELDS,
            json_encode($jsonPayload, JSON_THROW_ON_ERROR)
        );
    } elseif ($formFields !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $formFields);
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    if (!is_string($body)) {
        throw new RuntimeException('Local chat API request failed: ' . $error);
    }

    return [$status, $body, $responseHeaders['Location'] ?? ''];
}

function chat_test_csrf_token(string $html): string
{
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('Could not find a CSRF token for the test account.');
    }

    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
}

function login_chat_test_user(
    string $baseUrl,
    string $role,
    string $email,
    string $password
): array
{
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_COOKIEFILE, '');
    [$status, $loginPage] = chat_test_request($curl, $baseUrl . '/auth/login.php');
    assert_chat_authorization($status === 200, 'The login page should return HTTP 200.');
    $loginToken = chat_test_csrf_token($loginPage);

    [$status, , $location] = chat_test_request(
        $curl,
        $baseUrl . '/auth/login.php',
        'POST',
        [
            'csrf_token' => $loginToken,
            'email' => $email,
            'password' => $password,
        ]
    );
    assert_chat_authorization(
        $status === 302 && strpos($location, 'dashboard.php') !== false,
        'The temporary chat test account should log in successfully.'
    );

    $dashboardPath = [
        'player' => '/player/dashboard.php',
        'coach' => '/coach/dashboard.php',
        'hos' => '/hos/dashboard.php',
    ][$role] ?? null;
    if ($dashboardPath === null) {
        throw new RuntimeException('Unsupported role for the chat authorization test.');
    }

    [$status, $dashboard] = chat_test_request($curl, $baseUrl . $dashboardPath);
    assert_chat_authorization($status === 200, 'The role dashboard should return HTTP 200.');

    return [$curl, chat_test_csrf_token($dashboard)];
}

function decode_chat_response(string $body): array
{
    $response = json_decode($body, true);
    if (!is_array($response)) {
        throw new RuntimeException('The chat API should return a JSON object.');
    }

    return $response;
}

try {
    $sportId = $pdo->query('SELECT id FROM sports ORDER BY id LIMIT 1')->fetchColumn();
    if ($sportId === false) {
        throw new RuntimeException('Add at least one sport before running this test.');
    }

    $insertUser = $pdo->prepare(
        'INSERT INTO users (name, email, password_hash, role, sport_id)
         VALUES (:name, :email, :password_hash, :role, :sport_id)'
    );
    foreach (['player', 'coach', 'hos'] as $role) {
        $insertUser->execute([
            'name' => 'Temporary ' . strtoupper($role) . ' Chat Test',
            'email' => $emails[$role],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
            'sport_id' => $role === 'hos' ? null : (int) $sportId,
        ]);
        $userIds[$role] = (int) $pdo->lastInsertId();
    }

    [$playerCurl, $playerToken] = login_chat_test_user(
        $baseUrl,
        'player',
        $emails['player'],
        $password
    );
    $curlHandles[] = $playerCurl;
    [$hosCurl, $hosToken] = login_chat_test_user(
        $baseUrl,
        'hos',
        $emails['hos'],
        $password
    );
    $curlHandles[] = $hosCurl;

    $allowedBody = 'allowed-chat-' . $token;
    [$status, $body] = chat_test_request(
        $playerCurl,
        $baseUrl . '/api/send_message.php',
        'POST',
        null,
        [
            'conversation_type' => 'player_coach',
            'target_user_id' => $userIds['coach'],
            'body' => $allowedBody,
        ],
        $playerToken
    );
    assert_chat_authorization($status === 201, 'A player should be able to message their coach.');
    $allowedResponse = decode_chat_response($body);
    $conversationIds[] = (int) $allowedResponse['conversation_id'];

    $fetchUrl = $baseUrl . '/api/fetch_messages.php?' . http_build_query([
        'conversation_type' => 'player_coach',
        'target_user_id' => $userIds['coach'],
    ]);
    [$status, $body] = chat_test_request($playerCurl, $fetchUrl);
    assert_chat_authorization($status === 200, 'A player should be able to fetch their coach chat.');
    $fetchResponse = decode_chat_response($body);
    assert_chat_authorization(
        count($fetchResponse['messages'] ?? []) === 1
            && ($fetchResponse['messages'][0]['body'] ?? '') === $allowedBody,
        'The allowed coach chat should return its saved message.'
    );

    [$status] = chat_test_request(
        $playerCurl,
        $baseUrl . '/api/send_message.php',
        'POST',
        null,
        [
            'conversation_type' => 'player_coach',
            'target_user_id' => $userIds['hos'],
            'body' => 'forbidden-player-hos-' . $token,
        ],
        $playerToken
    );
    assert_chat_authorization($status === 403, 'A player must not send a coach chat to the HOS.');

    [$status] = chat_test_request(
        $playerCurl,
        $baseUrl . '/api/send_message.php',
        'POST',
        null,
        [
            'conversation_type' => 'coach_hos',
            'target_user_id' => $userIds['hos'],
            'body' => 'forbidden-player-hos-type-' . $token,
        ],
        $playerToken
    );
    assert_chat_authorization($status === 403, 'A player must not use the coach-HOS chat type.');

    [$status] = chat_test_request(
        $playerCurl,
        $baseUrl . '/api/fetch_messages.php?' . http_build_query([
            'conversation_type' => 'coach_hos',
            'target_user_id' => $userIds['hos'],
        ])
    );
    assert_chat_authorization($status === 403, 'A player must not fetch a coach-HOS conversation.');

    [$status] = chat_test_request(
        $hosCurl,
        $baseUrl . '/api/send_message.php',
        'POST',
        null,
        [
            'conversation_type' => 'coach_hos',
            'target_user_id' => $userIds['player'],
            'body' => 'forbidden-hos-player-' . $token,
        ],
        $hosToken
    );
    assert_chat_authorization($status === 403, 'The HOS must not send a coach chat to a player.');

    [$status] = chat_test_request(
        $hosCurl,
        $baseUrl . '/api/fetch_messages.php?' . http_build_query([
            'conversation_type' => 'coach_hos',
            'target_user_id' => $userIds['player'],
        ])
    );
    assert_chat_authorization($status === 403, 'The HOS must not fetch a coach chat with a player.');

    [$status] = chat_test_request(
        $playerCurl,
        $baseUrl . '/api/send_message.php',
        'POST',
        null,
        [
            'conversation_type' => 'player_coach',
            'target_user_id' => $userIds['coach'],
            'body' => 'csrf-rejected-' . $token,
        ],
        'invalid-token'
    );
    assert_chat_authorization($status === 403, 'Chat message submissions must require a valid CSRF token.');

    $sentCount = $pdo->prepare('SELECT COUNT(*) FROM messages WHERE body = :body');
    $sentCount->execute(['body' => $allowedBody]);
    assert_chat_authorization((int) $sentCount->fetchColumn() === 1, 'The allowed message should be persisted.');

    echo 'Chat role authorization and player-HOS restrictions passed.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if (isset($pdo) && $pdo instanceof PDO) {
        if ($conversationIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($conversationIds), '?'));
            $deleteConversations = $pdo->prepare(
                "DELETE FROM conversations WHERE id IN ({$placeholders})"
            );
            $deleteConversations->execute($conversationIds);
        }
        foreach ($userIds as $userId) {
            $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $deleteUser->execute(['id' => $userId]);
        }
    }
    foreach ($curlHandles as $curl) {
        curl_close($curl);
    }
}

if (isset($exitCode)) {
    exit($exitCode);
}
