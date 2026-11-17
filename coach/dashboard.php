<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('coach');
require_once __DIR__ . '/../config/db.php';

$sport_id = $_SESSION['sport_id'] ?? null;
$sport_name = $sport_id === null ? 'Not assigned' : 'Unknown Sport';
$players = [];

if ($sport_id !== null) {
    $sport_id = (int) $sport_id;

    // Fetch the sport name
    $stmt = $pdo->prepare('SELECT name FROM sports WHERE id = :sport_id');
    $stmt->execute(['sport_id' => $sport_id]);
    $sport = $stmt->fetch(PDO::FETCH_ASSOC);
    $sport_name = $sport ? $sport['name'] : 'Unknown Sport';

    // Fetch the players
    $stmt = $pdo->prepare(
        "SELECT id, name, email FROM users
         WHERE sport_id = :sport_id AND role = 'player'
         ORDER BY name ASC"
    );
    $stmt->execute(['sport_id' => $sport_id]);
    $players = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
$rosterCount = count($players);
$rosterStatus = $sport_id === null
    ? 'Needs setup'
    : ($rosterCount === 0 ? 'Building' : 'Active');

// Handle flash messages for success/error alerts
$coach_dashboard_flash = $_SESSION['coach_dashboard_flash'] ?? '';
$coach_dashboard_flash_type = $_SESSION['coach_dashboard_flash_type'] ?? 'success';
unset($_SESSION['coach_dashboard_flash']);
unset($_SESSION['coach_dashboard_flash_type']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Coach Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars(app_url('/assets/css/style.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="coach-dashboard" data-base-path="<?= htmlspecialchars(rtrim(app_url('/'), '/'), ENT_QUOTES, 'UTF-8') ?>">
    <header class="site-header coach-header">
        <a class="coach-brand" href="<?= htmlspecialchars(app_url('/'), ENT_QUOTES, 'UTF-8') ?>">
            <span class="coach-brand-mark" aria-hidden="true">US</span>
            <span>University Sports Portal</span>
        </a>
        <nav aria-label="Main navigation">
            <a class="coach-nav-active" href="dashboard.php">Dashboard</a>
            <form class="logout-form" method="post" action="<?= htmlspecialchars(app_url('/auth/logout.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit">Log out</button>
            </form>
        </nav>
    </header>
    <main class="page-content coach-content">
        <section class="coach-hero" aria-labelledby="coach-welcome">
            <div class="coach-hero-copy">
                <p class="coach-eyebrow">Coach workspace</p>
                <h1 id="coach-welcome">Welcome, <?= htmlspecialchars($_SESSION['name'], ENT_QUOTES, 'UTF-8') ?></h1>
                <p class="coach-hero-sport">
                    <span>Your sport</span>
                    <strong><?= htmlspecialchars($sport_name, ENT_QUOTES, 'UTF-8') ?></strong>
                </p>
            </div>
            <div class="coach-hero-mark" aria-hidden="true">TEAM</div>
        </section>

        <section class="coach-overview" aria-label="Team overview">
            <article class="coach-overview-card">
                <span class="coach-overview-label">Roster size</span>
                <strong class="coach-overview-value"><?= $rosterCount ?></strong>
                <span class="coach-overview-detail">
                    <?= $rosterCount === 1 ? 'player currently assigned' : 'players currently assigned' ?>
                </span>
            </article>
            <article class="coach-overview-card">
                <span class="coach-overview-label">Sport</span>
                <strong class="coach-overview-value coach-overview-sport">
                    <?= htmlspecialchars($sport_name, ENT_QUOTES, 'UTF-8') ?>
                </strong>
                <span class="coach-overview-detail">Current team assignment</span>
            </article>
            <article class="coach-overview-card">
                <span class="coach-overview-label">Roster status</span>
                <strong class="coach-overview-value"><?= htmlspecialchars($rosterStatus, ENT_QUOTES, 'UTF-8') ?></strong>
                <span class="coach-overview-detail">
                    <?= $sport_id === null
                        ? 'A sport assignment is needed'
                        : ($rosterCount === 0 ? 'Ready to add players' : 'Your team is ready') ?>
                </span>
            </article>
        </section>

        <section class="coach-message-center" aria-labelledby="message-center-heading">
            <div class="coach-message-center-heading">
                <div>
                    <p class="coach-eyebrow">Stay connected</p>
                    <h2 id="message-center-heading">Message Center</h2>
                </div>
                <p>Choose a conversation to open the chat.</p>
            </div>
            <nav class="coach-message-links" aria-label="Coach messaging options">
                <a class="coach-message-link" href="team_chat.php">
                    <span class="coach-message-icon" aria-hidden="true">TEAM</span>
                    <span class="coach-message-copy">
                        <strong>Team Chat</strong>
                        <small>Talk with your team</small>
                    </span>
                    <span class="coach-message-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="coach-message-link" href="player_chat.php">
                    <span class="coach-message-icon player-message-icon" aria-hidden="true">PLAYER</span>
                    <span class="coach-message-copy">
                        <strong>Message a Player</strong>
                        <small>Start a one-on-one chat</small>
                    </span>
                    <span class="coach-message-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="coach-message-link" href="coaches_board.php">
                    <span class="coach-message-icon board-message-icon" aria-hidden="true">BOARD</span>
                    <span class="coach-message-copy">
                        <strong>Coaches Board</strong>
                        <small>Connect with coaches</small>
                    </span>
                    <span class="coach-message-arrow" aria-hidden="true">&rarr;</span>
                </a>
                <a class="coach-message-link" href="hos_chat.php">
                    <span class="coach-message-icon hos-message-icon" aria-hidden="true">HOS</span>
                    <span class="coach-message-copy">
                        <strong>Head of Sports</strong>
                        <small>Message the HOS</small>
                    </span>
                    <span class="coach-message-arrow" aria-hidden="true">&rarr;</span>
                </a>
            </nav>
        </section>

        <?php if ($coach_dashboard_flash !== ''): ?>
            <p class="coach-notice<?= $coach_dashboard_flash_type === 'error' ? ' coach-notice-error' : '' ?>"
               role="<?= $coach_dashboard_flash_type === 'error' ? 'alert' : 'status' ?>">
                <?= htmlspecialchars($coach_dashboard_flash, ENT_QUOTES, 'UTF-8') ?>
            </p>
        <?php endif; ?>

        <div class="coach-dashboard-grid">
            <?php if ($sport_id !== null): ?>
                <section class="coach-card add-player-card" aria-labelledby="add-player-heading">
                    <div class="coach-section-heading">
                        <span class="coach-section-icon" aria-hidden="true">+</span>
                        <div>
                            <p class="coach-eyebrow">Grow your squad</p>
                            <h2 id="add-player-heading">Add New Player</h2>
                        </div>
                    </div>
                    <p class="coach-card-description">Add a player directly to your <?= htmlspecialchars($sport_name, ENT_QUOTES, 'UTF-8') ?> roster.</p>
                    <form method="post" action="add_player.php">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">

                        <div class="coach-form-field">
                            <label for="new-player-name">Name</label>
                            <input id="new-player-name" name="name" type="text" maxlength="120" placeholder="e.g. Alex Morgan" required>
                        </div>

                        <div class="coach-form-field">
                            <label for="new-player-email">Email</label>
                            <input id="new-player-email" name="email" type="email" maxlength="254" autocomplete="email" placeholder="player@university.edu" required>
                        </div>

                        <div class="coach-form-field">
                            <label for="new-player-password">Password <span>(10+ characters)</span></label>
                            <input id="new-player-password" name="password" type="password" minlength="10" autocomplete="new-password" placeholder="Create a secure password" required>
                        </div>

                        <button class="button button-add-player" type="submit">
                            <span aria-hidden="true">+</span> Add Player
                        </button>
                    </form>
                </section>
            <?php else: ?>
                <section class="coach-card add-player-card">
                    <h2>Add New Player</h2>
                    <p>A sport must be assigned to your coach account before you can add players.</p>
                </section>
            <?php endif; ?>

            <section class="coach-card roster-card dashboard-table-container" data-table-container
                     aria-labelledby="roster-heading">
                <div class="coach-section-heading roster-heading">
                    <span class="coach-section-icon roster-icon" aria-hidden="true">★</span>
                    <div>
                        <p class="coach-eyebrow">Your team</p>
                        <h2 id="roster-heading">Players on your roster</h2>
                    </div>
                    <span class="roster-count-badge"
                          aria-label="<?= count($players) ?> <?= count($players) === 1 ? 'player' : 'players' ?>">
                        <?= count($players) ?>
                    </span>
                </div>
                <?php if ($players === []): ?>
                    <div class="roster-empty-state">
                        <span class="roster-empty-icon" aria-hidden="true">+</span>
                        <p>
                            <?= $sport_id === null
                                ? 'A sport must be assigned to your coach account before a roster can be shown.'
                                : 'Your roster is empty. Add your first player to get the team started.' ?>
                        </p>
                        <?php if ($sport_id !== null): ?>
                            <a class="button button-message" href="#new-player-name">Add your first player</a>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="roster-search">
                        <label for="roster-search-input">Search players</label>
                        <input id="roster-search-input" type="search" autocomplete="off"
                               placeholder="Search by name or email" data-table-search>
                        <p class="roster-search-status" data-table-results aria-live="polite">
                            Showing all <?= count($players) ?> <?= count($players) === 1 ? 'player' : 'players' ?>.
                        </p>
                        <div class="roster-sort-control">
                            <label for="roster-sort-select">Sort roster</label>
                            <select id="roster-sort-select" data-table-sort-select>
                                <option value="0-asc">Player name (A–Z)</option>
                                <option value="0-desc">Player name (Z–A)</option>
                                <option value="1-asc">Email (A–Z)</option>
                                <option value="1-desc">Email (Z–A)</option>
                            </select>
                        </div>
                    </div>
                    <div class="roster-table-wrap">
                        <table class="roster-table" data-dashboard-table
                               data-item-label="player" data-item-label-plural="players">
                            <thead>
                                <tr>
                                    <th scope="col" aria-sort="ascending">
                                        <button class="roster-sort-button" type="button" data-table-sort="0">
                                            Player <span aria-hidden="true" data-sort-indicator>↑</span>
                                        </button>
                                    </th>
                                    <th scope="col" aria-sort="none">
                                        <button class="roster-sort-button" type="button" data-table-sort="1">
                                            Email <span aria-hidden="true" data-sort-indicator>↕</span>
                                        </button>
                                    </th>
                                    <th scope="col">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($players as $player): ?>
                                    <tr data-table-row>
                                        <td class="roster-player-name" data-label="Player" data-search-cell><?= htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Email" data-search-cell><?= htmlspecialchars($player['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                        <td data-label="Actions">
                                            <div class="roster-actions">
                                                <a class="button button-message" href="player_chat.php?player_id=<?= (int) $player['id'] ?>">
                                                    Message
                                                </a>
                                                <form class="inline-form" method="post" action="drop_player.php" onsubmit="return confirm('Remove this player from your sport roster?');">
                                                    <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                                                    <button class="button button-drop" type="submit">Drop</button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <tr data-table-no-results hidden>
                                    <td colspan="3">No players match your search.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <script src="<?= htmlspecialchars(
        app_url('/assets/js/dashboard-tables.js'),
        ENT_QUOTES,
        'UTF-8'
    ) ?>" defer></script>
</body>
</html>