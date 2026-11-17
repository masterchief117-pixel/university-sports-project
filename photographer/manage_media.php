<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('photographer');
$pdo = get_database_connection();

$galleryId = filter_input(INPUT_GET, 'gallery_id', FILTER_VALIDATE_INT, [
    'options' => ['min_range' => 1],
]);
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
        'SELECT g.id, g.name, s.name AS sport_name
         FROM galleries g
         JOIN sports s ON s.id = g.sport_id
         WHERE g.id = :id'
    );
    $galleryQuery->execute(['id' => $galleryId]);
    $gallery = $galleryQuery->fetch(PDO::FETCH_ASSOC) ?: null;

    if ($gallery !== null) {
        $photosQuery = $pdo->prepare(
            'SELECT id, file_path FROM photos WHERE gallery_id = :gallery_id ORDER BY id DESC'
        );
        $photosQuery->execute(['gallery_id' => $galleryId]);
        $photos = $photosQuery->fetchAll(PDO::FETCH_ASSOC);
    }
}

if ($gallerySchemaReady && $gallery === null) {
    http_response_code(404);
}

$pageTitle = $gallery === null
    ? ($gallerySchemaReady ? 'Gallery not found' : 'Gallery storage is not ready')
    : 'Manage ' . (string) $gallery['name'];
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
        app_url('/assets/css/style.css') . '?v=' . filemtime(__DIR__ . '/../assets/css/style.css'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>">
</head>
<body class="photographer-dashboard">
    <?php require __DIR__ . '/../includes/toast_notification.php'; ?>
    <div class="photographer-shell">
        <aside class="photographer-sidebar">
            <a class="photographer-brand" href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">
                <span class="public-brand-mark" aria-hidden="true">US</span>
                <span>University Sports</span>
            </a>
            <p class="photographer-sidebar-label">Workspace</p>
            <nav aria-label="Photographer navigation">
                <a href="<?= htmlspecialchars(app_url('/photographer/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">
                    Dashboard
                </a>
                <?php if ($gallery !== null): ?>
                    <a class="photographer-nav-active" href="#photos">Manage photos</a>
                <?php endif; ?>
                <a href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">Public site</a>
            </nav>
            <form class="photographer-logout" method="post"
                  action="<?= htmlspecialchars(app_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Log out</button>
            </form>
        </aside>

        <main class="photographer-main" id="photos">
            <?php if (!$gallerySchemaReady): ?>
                <header class="photographer-page-header">
                    <div>
                        <p class="photographer-eyebrow">Media studio</p>
                        <h1>Gallery storage is not ready</h1>
                        <p>Ask an administrator to finish setting up gallery storage.</p>
                    </div>
                </header>
                <a class="btn photographer-manage-button"
                   href="<?= htmlspecialchars(app_url('/photographer/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">
                    Back to dashboard
                </a>
            <?php elseif ($gallery === null): ?>
                <header class="photographer-page-header">
                    <div>
                        <p class="photographer-eyebrow">Media studio</p>
                        <h1>Gallery not found</h1>
                        <p>This gallery may have been deleted or is no longer available.</p>
                    </div>
                </header>
                <a class="btn photographer-manage-button"
                   href="<?= htmlspecialchars(app_url('/photographer/dashboard.php'), ENT_QUOTES, 'UTF-8') ?>">
                    Back to dashboard
                </a>
            <?php else: ?>
                <header class="photographer-page-header">
                    <div>
                        <p class="photographer-eyebrow">
                            <?= htmlspecialchars($gallery['sport_name'], ENT_QUOTES, 'UTF-8') ?> gallery
                        </p>
                        <h1><?= htmlspecialchars($gallery['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                        <p><?= count($photos) ?> <?= count($photos) === 1 ? 'photo' : 'photos' ?> in this gallery.</p>
                    </div>
                    <a class="btn photographer-manage-button"
                       href="<?= htmlspecialchars(app_url('/photographer/dashboard.php#manage-galleries'), ENT_QUOTES, 'UTF-8') ?>">
                        Back to galleries
                    </a>
                </header>

                <?php if ($photos === []): ?>
                    <section class="photographer-gallery-summary">
                        <p class="photographer-empty-state">This gallery has no photos yet.</p>
                    </section>
                <?php else: ?>
                    <section class="photographer-media-grid" aria-label="Photos in this gallery">
                        <?php foreach ($photos as $photo): ?>
                            <?php
                            $storedPath = str_replace('\\', '/', (string) $photo['file_path']);
                            if (preg_match('~\Ahttps?://~i', $storedPath) === 1) {
                                $photoUrl = $storedPath;
                            } else {
                                $photoUrl = app_url('/uploads/photos/' . basename($storedPath));
                            }
                            ?>
                            <article class="photographer-media-card">
                                <img src="<?= htmlspecialchars($photoUrl, ENT_QUOTES, 'UTF-8') ?>"
                                     alt="<?= htmlspecialchars($gallery['name'] . ' photo', ENT_QUOTES, 'UTF-8') ?>"
                                     loading="lazy">
                                <form method="post"
                                      action="<?= htmlspecialchars(app_url('/photographer/delete_handler.php'), ENT_QUOTES, 'UTF-8') ?>"
                                      onsubmit="return confirm('Are you sure you want to delete this photo forever?');">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="action" value="delete_photo">
                                    <input type="hidden" name="photo_id" value="<?= (int) $photo['id'] ?>">
                                    <button class="btn photographer-delete-button" type="submit">
                                        Delete Photo
                                    </button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
</body>
</html>
