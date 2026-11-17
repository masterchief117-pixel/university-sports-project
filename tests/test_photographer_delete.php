<?php
declare(strict_types=1);

if (!function_exists('curl_init')) {
    fwrite(STDERR, 'PHP cURL is required for the photographer deletion test.' . PHP_EOL);
    exit(1);
}

$baseUrl = 'http://localhost/university_sports';
if (strtolower((string) parse_url($baseUrl, PHP_URL_HOST)) !== 'localhost') {
    fwrite(STDERR, 'The photographer deletion test only supports localhost.' . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$galleryIds = [];
$userId = null;
$testFiles = [];
$testToken = bin2hex(random_bytes(8));
$email = 'photographer-delete-' . $testToken . '@example.invalid';
$password = bin2hex(random_bytes(16));

function request_local_page($curl, string $url, ?array $fields = null): array
{
    $headers = [];
    curl_setopt_array($curl, [
        CURLOPT_URL => $url,
        CURLOPT_POST => $fields !== null,
        CURLOPT_HTTPGET => $fields === null,
        CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$headers): int {
            if (stripos($headerLine, 'Location:') === 0) {
                $headers['Location'] = trim(substr($headerLine, strlen('Location:')));
            }

            return strlen($headerLine);
        },
    ]);
    if ($fields !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, $fields);
    }

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);

    if (!is_string($body)) {
        throw new RuntimeException('Local photographer request failed: ' . $error);
    }

    return [$status, $body, $headers['Location'] ?? ''];
}

function extract_csrf_token(string $html): string
{
    if (!preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $matches)) {
        throw new RuntimeException('Could not find a CSRF token on the photographer page.');
    }

    return html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
}

function create_test_media_file(string $subdirectory, string $filename): string
{
    $directory = __DIR__ . '/../uploads/' . $subdirectory;
    if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) {
        throw new RuntimeException('Could not create the temporary media directory.');
    }

    $path = $directory . '/' . $filename;
    if (file_put_contents($path, 'temporary deletion test image') === false) {
        throw new RuntimeException('Could not create a temporary media file.');
    }

    return $path;
}

function assert_delete_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => false,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_COOKIEFILE => '',
]);

try {
    $sportId = $pdo->query('SELECT id FROM sports ORDER BY id LIMIT 1')->fetchColumn();
    if ($sportId === false) {
        throw new RuntimeException('Add at least one sport before running this test.');
    }

    $createUser = $pdo->prepare(
        "INSERT INTO users (name, email, password_hash, role, sport_id)
         VALUES (:name, :email, :password_hash, 'photographer', NULL)"
    );
    $createUser->execute([
        'name' => 'Temporary Deletion Test',
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $userId = (int) $pdo->lastInsertId();

    $localPhotoName = 'delete-test-' . $testToken . '.jpg';
    $individualPhotoPath = create_test_media_file('photos', $localPhotoName);
    $testFiles[] = $individualPhotoPath;

    $createGallery = $pdo->prepare(
        'INSERT INTO galleries (sport_id, name, cover_photo)
         VALUES (:sport_id, :name, :cover_photo)'
    );
    $createGallery->execute([
        'sport_id' => (int) $sportId,
        'name' => 'Individual photo deletion ' . $testToken,
        'cover_photo' => 'https://example.invalid/cover.jpg',
    ]);
    $individualGalleryId = (int) $pdo->lastInsertId();
    $galleryIds[] = $individualGalleryId;

    $createPhoto = $pdo->prepare(
        'INSERT INTO photos (gallery_id, file_path) VALUES (:gallery_id, :file_path)'
    );
    $createPhoto->execute([
        'gallery_id' => $individualGalleryId,
        'file_path' => 'uploads/photos/' . $localPhotoName,
    ]);
    $individualPhotoId = (int) $pdo->lastInsertId();
    $createPhoto->execute([
        'gallery_id' => $individualGalleryId,
        'file_path' => 'https://example.invalid/keep.jpg',
    ]);
    $externalPhotoId = (int) $pdo->lastInsertId();

    [$status, $loginPage] = request_local_page($curl, $baseUrl . '/auth/login.php');
    assert_delete_test($status === 200, 'The login page should return HTTP 200.');
    $loginToken = extract_csrf_token($loginPage);
    [$status, , $location] = request_local_page($curl, $baseUrl . '/auth/login.php', [
        'csrf_token' => $loginToken,
        'email' => $email,
        'password' => $password,
    ]);
    assert_delete_test(
        $status === 302 && strpos($location, '/photographer/dashboard.php') !== false,
        'The temporary photographer account should log in successfully.'
    );

    [$status, $dashboardPage] = request_local_page(
        $curl,
        $baseUrl . '/photographer/dashboard.php'
    );
    assert_delete_test($status === 200, 'The photographer dashboard should return HTTP 200.');
    assert_delete_test(
        strpos($dashboardPage, 'Manage Existing Galleries') !== false
            && strpos($dashboardPage, 'Delete Gallery') !== false
            && strpos($dashboardPage, 'Manage Photos') !== false,
        'The dashboard should show gallery management and deletion controls.'
    );

    [$status, $managePage] = request_local_page(
        $curl,
        $baseUrl . '/photographer/manage_media.php?gallery_id=' . $individualGalleryId
    );
    assert_delete_test($status === 200, 'The photo manager should open for a valid gallery.');
    assert_delete_test(
        strpos($managePage, 'Delete Photo') !== false,
        'The photo manager should show a delete control for each photo.'
    );
    $csrfToken = extract_csrf_token($managePage);
    [$status] = request_local_page(
        $curl,
        $baseUrl . '/photographer/delete_handler.php',
        [
            'csrf_token' => 'invalid-token',
            'action' => 'delete_photo',
            'photo_id' => $individualPhotoId,
        ]
    );
    assert_delete_test(
        $status === 403 && is_file($individualPhotoPath),
        'A deletion request with an invalid CSRF token should be rejected without deleting its file.'
    );
    [$status, , $location] = request_local_page(
        $curl,
        $baseUrl . '/photographer/delete_handler.php',
        [
            'csrf_token' => $csrfToken,
            'action' => 'delete_photo',
            'photo_id' => $individualPhotoId,
        ]
    );
    assert_delete_test(
        $status === 303 && strpos($location, 'manage_media.php?gallery_id=' . $individualGalleryId) !== false,
        'Deleting a photo should return to its gallery manager.'
    );
    [$status, $afterDeletePage] = request_local_page(
        $curl,
        $baseUrl . '/photographer/manage_media.php?gallery_id=' . $individualGalleryId
    );
    assert_delete_test($status === 200, 'The gallery manager should reload after photo deletion.');
    assert_delete_test(
        strpos($afterDeletePage, 'Photo deleted.') !== false,
        'The photo deletion should report success.'
    );
    clearstatcache(true, $individualPhotoPath);
    assert_delete_test(!is_file($individualPhotoPath), 'The local photo file should be removed.');
    $photoCount = $pdo->prepare('SELECT COUNT(*) FROM photos WHERE id = :id');
    $photoCount->execute(['id' => $individualPhotoId]);
    assert_delete_test((int) $photoCount->fetchColumn() === 0, 'The photo row should be removed.');
    $photoCount->execute(['id' => $externalPhotoId]);
    assert_delete_test((int) $photoCount->fetchColumn() === 1, 'Unselected external photos should remain.');

    $coverName = 'delete-test-cover-' . $testToken . '.jpg';
    $galleryPhotoName = 'delete-test-gallery-' . $testToken . '.jpg';
    $coverPath = create_test_media_file('galleries', $coverName);
    $galleryPhotoPath = create_test_media_file('photos', $galleryPhotoName);
    $testFiles[] = $coverPath;
    $testFiles[] = $galleryPhotoPath;

    $createGallery->execute([
        'sport_id' => (int) $sportId,
        'name' => 'Gallery deletion ' . $testToken,
        'cover_photo' => 'uploads/galleries/' . $coverName,
    ]);
    $deleteGalleryId = (int) $pdo->lastInsertId();
    $galleryIds[] = $deleteGalleryId;
    $createPhoto->execute([
        'gallery_id' => $deleteGalleryId,
        'file_path' => 'uploads/photos/' . $galleryPhotoName,
    ]);
    $createPhoto->execute([
        'gallery_id' => $deleteGalleryId,
        'file_path' => 'https://example.invalid/gallery-photo.jpg',
    ]);

    [$status, $managePage] = request_local_page(
        $curl,
        $baseUrl . '/photographer/manage_media.php?gallery_id=' . $deleteGalleryId
    );
    assert_delete_test($status === 200, 'The gallery manager should open before deletion.');
    $csrfToken = extract_csrf_token($managePage);
    [$status, , $location] = request_local_page(
        $curl,
        $baseUrl . '/photographer/delete_handler.php',
        [
            'csrf_token' => $csrfToken,
            'action' => 'delete_gallery',
            'gallery_id' => $deleteGalleryId,
        ]
    );
    assert_delete_test(
        $status === 303 && strpos($location, '/photographer/dashboard.php') !== false,
        'Deleting a gallery should return to the photographer dashboard.'
    );
    clearstatcache(true, $coverPath);
    clearstatcache(true, $galleryPhotoPath);
    assert_delete_test(!is_file($coverPath), 'The local gallery cover should be removed.');
    assert_delete_test(!is_file($galleryPhotoPath), 'The gallery photo file should be removed.');
    $galleryCount = $pdo->prepare('SELECT COUNT(*) FROM galleries WHERE id = :id');
    $galleryCount->execute(['id' => $deleteGalleryId]);
    assert_delete_test((int) $galleryCount->fetchColumn() === 0, 'The gallery row should be removed.');
    $galleryPhotoCount = $pdo->prepare('SELECT COUNT(*) FROM photos WHERE gallery_id = :id');
    $galleryPhotoCount->execute(['id' => $deleteGalleryId]);
    assert_delete_test((int) $galleryPhotoCount->fetchColumn() === 0, 'Gallery photo rows should cascade.');

    echo 'Photographer photo and gallery deletion tests passed.' . PHP_EOL;
} catch (Throwable $exception) {
    fwrite(STDERR, $exception->getMessage() . PHP_EOL);
    $exitCode = 1;
} finally {
    if (isset($pdo) && $pdo instanceof PDO) {
        if ($galleryIds !== []) {
            $placeholders = implode(', ', array_fill(0, count($galleryIds), '?'));
            $deletePhotos = $pdo->prepare(
                "DELETE FROM photos WHERE gallery_id IN ({$placeholders})"
            );
            $deletePhotos->execute($galleryIds);
            $deleteGalleries = $pdo->prepare(
                "DELETE FROM galleries WHERE id IN ({$placeholders})"
            );
            $deleteGalleries->execute($galleryIds);
        }
        if ($userId !== null) {
            $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $deleteUser->execute(['id' => $userId]);
        }
    }
    foreach ($testFiles as $testFile) {
        clearstatcache(true, $testFile);
        if (is_file($testFile)) {
            unlink($testFile);
        }
    }
    curl_close($curl);
}

if (isset($exitCode)) {
    exit($exitCode);
}
