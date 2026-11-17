<?php
declare(strict_types=1);

if (!function_exists('curl_init')) {
    fwrite(STDERR, 'PHP cURL is required for the gallery viewer test.' . PHP_EOL);
    exit(1);
}

$baseUrl = 'http://localhost/university_sports';
$baseHost = strtolower((string) parse_url($baseUrl, PHP_URL_HOST));
if (!in_array($baseHost, ['localhost', '127.0.0.1', '::1'], true)) {
    fwrite(STDERR, 'The gallery viewer test only supports localhost.' . PHP_EOL);
    exit(1);
}

require_once __DIR__ . '/../config/db.php';

$galleryIds = [];
$testToken = bin2hex(random_bytes(6));
$galleryName = 'Gallery <test> ' . $testToken;

function fetch_gallery_page(string $url): array
{
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
    ]);

    $body = curl_exec($curl);
    $error = curl_error($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if (!is_string($body)) {
        throw new RuntimeException('Gallery request failed: ' . $error);
    }

    return [$status, $body];
}

function assert_gallery_test(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $sportId = $pdo->query('SELECT id FROM sports ORDER BY id LIMIT 1')->fetchColumn();
    if ($sportId === false) {
        throw new RuntimeException('Add at least one sport before running this test.');
    }

    $insertGallery = $pdo->prepare(
        'INSERT INTO galleries (sport_id, name, cover_photo)
         VALUES (:sport_id, :name, :cover_photo)'
    );
    $insertGallery->execute([
        'sport_id' => (int) $sportId,
        'name' => $galleryName,
        'cover_photo' => 'uploads/galleries/gallery-test.jpg',
    ]);
    $galleryIds[] = (int) $pdo->lastInsertId();

    $insertPhoto = $pdo->prepare(
        'INSERT INTO photos (gallery_id, file_path) VALUES (:gallery_id, :file_path)'
    );
    $insertPhoto->execute([
        'gallery_id' => $galleryIds[0],
        'file_path' => 'https://example.invalid/gallery-test-' . $testToken . '.jpg?a=1&b=2',
    ]);
    $insertPhoto->execute([
        'gallery_id' => $galleryIds[0],
        'file_path' => 'legacy-photo-' . $testToken . '.jpg',
    ]);

    [$status, $body] = fetch_gallery_page(
        $baseUrl . '/view_gallery.php?id=' . $galleryIds[0]
    );
    assert_gallery_test($status === 200, 'A valid gallery should return HTTP 200.');
    assert_gallery_test(
        strpos($body, htmlspecialchars($galleryName, ENT_QUOTES, 'UTF-8')) !== false,
        'The gallery name should be rendered with HTML escaping.'
    );
    assert_gallery_test(
        strpos(
            $body,
            'https://example.invalid/gallery-test-' . $testToken . '.jpg?a=1&amp;b=2'
        ) !== false,
        'External photo URLs should be rendered directly and HTML-escaped.'
    );
    assert_gallery_test(
        strpos($body, '/university_sports/uploads/photos/legacy-photo-' . $testToken . '.jpg') !== false,
        'Legacy local photo filenames should resolve under the local photo directory.'
    );
    assert_gallery_test(
        strpos($body, 'NO MEDIA AVAILABLE YET.') === false,
        'The empty state should not appear when the gallery contains photos.'
    );

    $insertGallery->execute([
        'sport_id' => (int) $sportId,
        'name' => 'Empty gallery ' . $testToken,
        'cover_photo' => 'uploads/galleries/empty-test.jpg',
    ]);
    $galleryIds[] = (int) $pdo->lastInsertId();

    [$status, $body] = fetch_gallery_page(
        $baseUrl . '/view_gallery.php?id=' . $galleryIds[1]
    );
    assert_gallery_test($status === 200, 'A valid empty gallery should return HTTP 200.');
    assert_gallery_test(
        strpos($body, 'NO MEDIA AVAILABLE YET.') !== false,
        'The empty gallery should display the no-media state.'
    );

    [$status, $body] = fetch_gallery_page($baseUrl . '/view_gallery.php?id=0');
    assert_gallery_test($status === 404, 'An invalid gallery ID should return HTTP 404.');
    assert_gallery_test(
        strpos($body, "We couldn't find that gallery") !== false,
        'An invalid gallery ID should display the not-found message.'
    );

    echo 'Gallery rendering, URL handling, empty state, and not-found tests passed.' . PHP_EOL;
} finally {
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
}
