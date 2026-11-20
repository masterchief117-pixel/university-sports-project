<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('photographer');
$pdo = get_database_connection();

$sportsQuery = $pdo->query('SELECT id, name FROM sports ORDER BY name');
$sports = $sportsQuery->fetchAll(PDO::FETCH_ASSOC);
$gallerySchemaQuery = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables
     WHERE table_schema = DATABASE() AND table_name IN (:galleries, :photos)'
);
$gallerySchemaQuery->execute([
    'galleries' => 'galleries',
    'photos' => 'photos',
]);
$gallerySchemaReady = (int) $gallerySchemaQuery->fetchColumn() === 2;
$galleries = [];

if ($gallerySchemaReady) {
    $galleriesQuery = $pdo->query(
        'SELECT g.id, g.name, s.name AS sport_name
         FROM galleries g
         JOIN sports s ON s.id = g.sport_id
         ORDER BY g.name, g.id'
    );
    $galleries = $galleriesQuery->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'Photographer dashboard';
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
                <a class="photographer-nav-active" href="#overview">Overview</a>
                <a href="#create-gallery">Create a gallery</a>
                <a href="#upload-photos">Upload photos</a>
                <a href="#manage-galleries">Manage galleries</a>
                <a href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">Public site</a>
            </nav>
            <form class="photographer-logout" method="post"
                  action="<?= htmlspecialchars(app_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Log out</button>
            </form>
        </aside>

        <main class="photographer-main" id="overview">
            <header class="photographer-page-header">
                <div>
                    <p class="photographer-eyebrow">Media studio</p>
                    <h1>Good to see you, <?= htmlspecialchars($_SESSION['name'] ?? 'Photographer', ENT_QUOTES, 'UTF-8') ?></h1>
                    <p>Build a gallery for every team moment worth remembering.</p>
                </div>
                <span class="photographer-avatar" aria-hidden="true">
                    <?= htmlspecialchars(strtoupper(substr((string) ($_SESSION['name'] ?? 'P'), 0, 1)), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </header>

            <?php if (!$gallerySchemaReady): ?>
                <div class="photographer-setup-notice" role="status">
                    Gallery storage has not been set up yet. Ask an administrator to apply
                    <code>database/migrations/003_photographer_galleries.sql</code> before uploading.
                </div>
            <?php endif; ?>

            <section class="photographer-form-grid" aria-label="Gallery management">
                <article class="card photographer-form-card" id="create-gallery">
                    <div class="photographer-card-heading">
                        <span class="photographer-step">01</span>
                        <div>
                            <p class="photographer-eyebrow">Start a collection</p>
                            <h2>create a gallery</h2>
                        </div>
            </div>
                    <p class="photographer-card-description">
                        Give your photos a home. Choose the sport and pick a cover image.
                    </p>
                    <form method="post"
                          action="<?= htmlspecialchars(app_url('/photographer/upload_handler.php'), ENT_QUOTES, 'UTF-8') ?>"
                          enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="create_gallery">

                        <label for="gallery-name">Gallery name</label>
                        <input id="gallery-name" name="name" type="text" maxlength="150"
                               placeholder="e.g. Homecoming match day" required>

                        <label for="gallery-sport">Sport</label>
                        <select id="gallery-sport" name="sport_id" required>
                            <option value="">Choose a sport</option>
                            <?php foreach ($sports as $sport): ?>
                                <option value="<?= (int) $sport['id'] ?>">
                                    <?= htmlspecialchars($sport['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <label for="gallery-cover">Cover photo</label>
                        <input id="gallery-cover" name="cover_photo" type="file"
                               accept="image/jpeg,image/png,image/webp">
                        <p class="photographer-field-hint">JPG, PNG, or WebP image.</p>
                        <div class="photographer-or-divider" aria-hidden="true"><span>OR</span></div>
                        <label for="gallery-cover-url">Cover photo URL</label>
                        <input id="gallery-cover-url" name="cover_photo_url" type="url"
                               placeholder="Paste cover image URL (https://...)">
                        <p class="photographer-field-hint">
                            Use a public HTTP or HTTPS image URL, up to 255 characters.
                        </p>

                        <button class="btn btn-primary" type="submit"
                                <?= $sports === [] || !$gallerySchemaReady ? 'disabled' : '' ?>>
                            Create gallery
                        </button>
                    </form>
                </article>

                <article class="card photographer-form-card" id="upload-photos">
                    <div class="photographer-card-heading">
                        <span class="photographer-step">02</span>
                        <div>
                            <p class="photographer-eyebrow">Add to the story</p>
                            <h2>Upload photos</h2>
                        </div>
                    </div>
                    <p class="photographer-card-description">
                        Add a batch of images to one of the available galleries.
                    </p>
                    <form method="post"
                          action="<?= htmlspecialchars(app_url('/photographer/upload_handler.php'), ENT_QUOTES, 'UTF-8') ?>"
                          enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token"
                               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="action" value="upload_photos">

                        <label for="photo-gallery">Destination gallery</label>
                        <select id="photo-gallery" name="gallery_id" required>
                            <option value="">Choose a gallery</option>
                            <?php foreach ($galleries as $gallery): ?>
                                <option value="<?= (int) $gallery['id'] ?>">
                                    <?= htmlspecialchars($gallery['name'], ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <?php if ($galleries === []): ?>
                            <p class="photographer-field-hint">
                                Create a gallery first; it will appear here when it is ready.
                            </p>
                        <?php endif; ?>

                        <label for="gallery-photos">Photos</label>
                        <input id="gallery-photos" type="file" name="photos[]" multiple
                               accept="image/jpeg,image/png,image/webp">
                        <p class="photographer-field-hint">Select multiple images to upload them together.</p>
                        <div class="photographer-or-divider" aria-hidden="true"><span>OR</span></div>
                        <label for="photo-urls">Photo URLs</label>
                        <textarea id="photo-urls" name="photo_urls" rows="3"
                                  placeholder="Paste image URLs (one per line)..."></textarea>
                        <p class="photographer-field-hint">
                            Use one public HTTP or HTTPS image URL per line, up to 255 characters each.
                        </p>

                        <button class="btn btn-primary" type="submit"
                                <?= $galleries === [] || !$gallerySchemaReady ? 'disabled' : '' ?>>
                            Upload photos
                        </button>
                    </form>
                </article>
            </section>

            <section class="photographer-gallery-summary" id="manage-galleries"
                     aria-labelledby="your-galleries-heading">
                <div class="photographer-section-heading">
                    <div>
                        <p class="photographer-eyebrow">The library</p>
                        <h2 id="your-galleries-heading">Manage Existing Galleries</h2>
                    </div>
                    <span class="photographer-count"><?= count($galleries) ?></span>
                </div>
                <?php if (!$gallerySchemaReady): ?>
                    <p class="photographer-empty-state">Your galleries will show up here once setup is complete.</p>
                <?php elseif ($galleries === []): ?>
                    <p class="photographer-empty-state">No galleries yet. Create your first collection to get started.</p>
                <?php else: ?>
                    <div class="photographer-media-table-wrap">
                        <table class="photographer-media-table">
                            <thead>
                                <tr>
                                    <th scope="col">Gallery</th>
                                    <th scope="col">Sport</th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($galleries as $gallery): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($gallery['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td><?= htmlspecialchars($gallery['sport_name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td>
                                            <div class="photographer-media-actions">
                                                <a class="btn photographer-manage-button"
                                                   href="<?= htmlspecialchars(
                                                       app_url('/photographer/manage_media.php?gallery_id=' . (int) $gallery['id']),
                                                       ENT_QUOTES,
                                                       'UTF-8'
                                                   ) ?>">Manage Photos</a>
                                                <form method="post"
                                                      action="<?= htmlspecialchars(app_url('/photographer/delete_handler.php'), ENT_QUOTES, 'UTF-8') ?>"
                                                      onsubmit="return confirm('Are you sure? This deletes the folder and all its photos forever.');">
                                                    <input type="hidden" name="csrf_token"
                                                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="delete_gallery">
                                                    <input type="hidden" name="gallery_id"
                                                           value="<?= (int) $gallery['id'] ?>">
                                                    <button class="btn photographer-delete-button" type="submit">
                                                        Delete Gallery
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
