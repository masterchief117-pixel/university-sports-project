<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/auth_check.php';
$pdo = get_database_connection();

$sports = $pdo->query('SELECT id, name FROM sports ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
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
        'SELECT g.id, g.name, g.cover_photo, s.name AS sport_name,
                COUNT(p.id) AS photo_count
         FROM galleries g
         JOIN sports s ON s.id = g.sport_id
         LEFT JOIN photos p ON p.gallery_id = g.id
         GROUP BY g.id, g.name, g.cover_photo, s.name
         ORDER BY g.id DESC'
    );
    $galleries = $galleriesQuery->fetchAll(PDO::FETCH_ASSOC);
}

$pageTitle = 'University Sports Portal';
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
    <header class="public-header">
        <a class="public-brand" href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="public-brand-mark" aria-hidden="true">US</span>
            <span>University Sports</span>
        </a>
        <nav aria-label="Account navigation">
            <a class="public-nav-link" href="<?= htmlspecialchars(app_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>">
                Sign in
            </a>
            <a class="btn btn-primary" href="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">
                Join the team
            </a>
        </nav>
    </header>

    <main>
        <section class="public-hero" aria-labelledby="welcome-heading">
            <div class="public-hero-copy">
                <p class="public-eyebrow">One campus. Every team.</p>
                <h1 id="welcome-heading">Your season starts <span>together.</span></h1>
                <p>
                    Find your sport, connect with your team, and relive the moments
                    that make university athletics unforgettable.
                </p>
                <div class="public-hero-actions">
                    <a class="btn btn-primary" href="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">
                        Get started
                    </a>
                    <a class="public-hero-secondary" href="#sports-directory">Explore sports</a>
                </div>
            </div>
            <div class="public-hero-aside" aria-hidden="true">
                <span class="public-hero-number"><?= count($sports) ?></span>
                <span>sports to<br>make your mark</span>
            </div>
        </section>

        <section class="public-section" id="sports-directory" aria-labelledby="sports-heading">
            <div class="public-section-heading">
                <div>
                    <p class="public-eyebrow">Find your game</p>
                    <h2 id="sports-heading">The sports directory</h2>
                </div>
                <p>Pick your sport and find your people.</p>
            </div>
            <?php if ($sports === []): ?>
                <div class="public-empty-state">
                    <h3>Sports are being added</h3>
                    <p>Check back soon to find your team.</p>
                </div>
            <?php else: ?>
                <div class="sports-directory-grid">
                    <?php foreach ($sports as $index => $sport): ?>
                        <a class="sport-directory-link" href="#galleries">
                            <article class="sport-directory-card">
                                <span class="sport-card-number"><?= sprintf('%02d', $index + 1) ?></span>
                                <h3><?= htmlspecialchars($sport['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                <span class="sport-card-mark" aria-hidden="true">&rarr;</span>
                            </article>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="public-section gallery-section" id="galleries" aria-labelledby="galleries-heading">
            <div class="public-section-heading">
                <div>
                    <p class="public-eyebrow">From the sidelines</p>
                    <h2 id="galleries-heading">Moments worth keeping</h2>
                </div>
                <p>Browse the latest team galleries.</p>
            </div>
            <?php if (!$gallerySchemaReady): ?>
                <div class="public-empty-state" role="status">
                    <h3>Photo galleries are getting ready</h3>
                    <p>Gallery albums will appear here once the portal setup is complete.</p>
                </div>
            <?php elseif ($galleries === []): ?>
                <div class="public-empty-state">
                    <h3>No galleries just yet</h3>
                    <p>Once teams start sharing photos, you’ll find their albums here.</p>
                </div>
            <?php else: ?>
                <div class="gallery-folder-grid">
                    <?php foreach ($galleries as $gallery): ?>
                        <?php
                        $coverPath = str_replace('\\', '/', (string) $gallery['cover_photo']);
                        $coverUrl = str_starts_with(strtolower($coverPath), 'http://')
                            || str_starts_with(strtolower($coverPath), 'https://')
                            ? $coverPath
                            : app_url('/' . ltrim($coverPath, '/'));
                        ?>
                        <a class="gallery-folder-card"
                           href="<?= htmlspecialchars(
                               app_url('/view_gallery.php?id=' . (int) $gallery['id']),
                               ENT_QUOTES,
                               'UTF-8'
                           ) ?>">
                            <img src="<?= htmlspecialchars($coverUrl, ENT_QUOTES, 'UTF-8') ?>"
                                 alt=""
                                 loading="lazy">
                            <span class="gallery-folder-overlay">
                                <span class="gallery-folder-sport"><?= htmlspecialchars($gallery['sport_name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <strong><?= htmlspecialchars($gallery['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                                <small><?= (int) $gallery['photo_count'] ?>
                                    <?= (int) $gallery['photo_count'] === 1 ? 'photo' : 'photos' ?></small>
                            </span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>
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
