<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
$pdo = get_database_connection();

$galleryId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
if ($galleryId === false || $galleryId === null) {
    http_response_code(404);
}

$schemaQuery = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name IN (:galleries, :photos)'
);
$schemaQuery->execute([
    'galleries' => 'galleries',
    'photos' => 'photos',
]);
$gallerySchemaReady = (int) $schemaQuery->fetchColumn() === 2;
$gallery = null;
$photos = [];

if ($gallerySchemaReady && is_int($galleryId)) {
    $galleryQuery = $pdo->prepare(
        'SELECT g.id, g.name, g.cover_photo, s.name AS sport_name
         FROM galleries g
         JOIN sports s ON s.id = g.sport_id
         WHERE g.id = :id'
    );
    $galleryQuery->execute(['id' => $galleryId]);
    $gallery = $galleryQuery->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($gallery === null) {
        http_response_code(404);
    } else {
        $photosQuery = $pdo->prepare(
            'SELECT file_path FROM photos WHERE gallery_id = :gallery_id ORDER BY id DESC'
        );
        $photosQuery->execute(['gallery_id' => $galleryId]);
        $photos = $photosQuery->fetchAll(PDO::FETCH_ASSOC);
    }
}

$pageTitle = $gallery === null
    ? 'Gallery not found'
    : (string) $gallery['name'] . ' | University Sports';
$headerTitle = $gallery !== null
    ? (string) $gallery['name']
    : (!$gallerySchemaReady ? 'Photo galleries' : 'Gallery not found');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0F172A">
    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(
        app_url('/assets/css/style.css') . '?v=' . filemtime(__DIR__ . '/assets/css/style.css'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>">
</head>
<body class="public-home">
    <header class="gallery-viewer-header">
        <div class="gallery-viewer-header-inner">
            <div class="gallery-viewer-toolbar">
                <a class="public-brand" href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">
                    <span class="public-brand-mark" aria-hidden="true">US</span>
                    <span>University Sports</span>
                </a>
                <a class="gallery-directory-link"
                   href="<?= htmlspecialchars(app_url('/index.php#galleries'), ENT_QUOTES, 'UTF-8') ?>">
                    Back to directory
                </a>
            </div>
            <div class="gallery-viewer-title">
                <?php if ($gallery !== null): ?>
                    <p class="gallery-viewer-sport"><?= htmlspecialchars($gallery['sport_name'], ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>
                <h1><?= htmlspecialchars($headerTitle, ENT_QUOTES, 'UTF-8') ?></h1>
            </div>
        </div>
    </header>

    <main class="gallery-viewer">
        <?php if (!$gallerySchemaReady): ?>
            <section class="public-empty-state" role="status">
                <h1>Photo galleries are getting ready</h1>
                <p>Gallery albums will appear here once the portal setup is complete.</p>
            </section>
        <?php elseif ($gallery === null): ?>
            <section class="public-empty-state" role="status">
                <h1>We couldn't find that gallery</h1>
                <p>It may have moved. Head back home to explore the latest albums.</p>
            </section>
        <?php else: ?>
            <header class="gallery-viewer-heading">
                <p><?= count($photos) ?> <?= count($photos) === 1 ? 'photo' : 'photos' ?> in this collection</p>
            </header>

            <?php if ($photos === []): ?>
                <section class="public-empty-state gallery-empty-state" role="status">
                    <h2>NO MEDIA AVAILABLE YET.</h2>
                </section>
            <?php else: ?>
                <div class="gallery-photo-masonry">
                    <?php foreach ($photos as $index => $photo): ?>
                        <?php
                        $photoPath = str_replace('\\', '/', (string) $photo['file_path']);
                        $isExternalPhoto = str_starts_with(strtolower($photoPath), 'http://')
                            || str_starts_with(strtolower($photoPath), 'https://');
                        if ($isExternalPhoto) {
                            $photoUrl = $photoPath;
                        } else {
                            $localPhotoPath = ltrim($photoPath, '/');
                            if (!str_starts_with($localPhotoPath, 'uploads/')) {
                                $localPhotoPath = 'uploads/photos/' . $localPhotoPath;
                            }
                            $photoUrl = app_url('/' . $localPhotoPath);
                        }
                        ?>
                        <figure class="gallery-photo">
                            <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 alt="<?= htmlspecialchars($gallery['name'] . ' photo ' . ($index + 1), ENT_QUOTES, 'UTF-8') ?>"
                                 loading="lazy">
                        </figure>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>

    <footer class="public-footer">
        <a class="public-brand" href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="public-brand-mark" aria-hidden="true">US</span>
            <span>University Sports</span>
        </a>
        <p>&copy; <?= date('Y') ?> University Sports Portal</p>
    </footer>
</body>
</html>
