<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('hos');

require_once __DIR__ . '/../config/db.php';
$summaryQuery = $pdo->query(
    "SELECT s.id, s.name,
            COALESCE(SUM(u.role = 'player'), 0) AS player_count,
            COALESCE(SUM(u.role = 'coach'), 0) AS coach_count
     FROM sports s
     LEFT JOIN users u ON u.sport_id = s.id AND u.role IN ('player', 'coach')
     GROUP BY s.id, s.name
     ORDER BY s.name"
);
$sportSummary = $summaryQuery->fetchAll();
$coachesQuery = $pdo->query(
    "SELECT u.id, u.name, u.email, s.name AS sport_name
     FROM users u
     LEFT JOIN sports s ON s.id = u.sport_id
     WHERE u.role = 'coach'
     ORDER BY s.name, u.name, u.id"
);
$coaches = $coachesQuery->fetchAll();
$photographersQuery = $pdo->query(
    "SELECT id, name, email FROM users WHERE role = 'photographer' ORDER BY name, id"
);
$photographers = $photographersQuery->fetchAll();
$transferQuery = $pdo->query(
    "SELECT u.id, u.name, u.email, u.requested_sport_id,
            s.name AS requested_sport_name
     FROM users u
     LEFT JOIN sports s ON s.id = u.requested_sport_id
     WHERE u.role = 'player' AND u.sport_id IS NULL
     ORDER BY u.name, u.id"
);
$transferPlayers = $transferQuery->fetchAll();
$availableSportsQuery = $pdo->query('SELECT id, name FROM sports ORDER BY name');
$availableSports = $availableSportsQuery->fetchAll();
$hosDashboardFlash = $_SESSION['hos_dashboard_flash'] ?? $_SESSION['flash'] ?? '';
$hosDashboardFlashType = $_SESSION['hos_dashboard_flash_type'] ?? $_SESSION['flash_type'] ?? 'success';
if ($hosDashboardFlash !== '') {
    $_SESSION['flash'] = $hosDashboardFlash;
    $_SESSION['flash_type'] = $hosDashboardFlashType;
}
unset($_SESSION['hos_dashboard_flash']);
unset($_SESSION['hos_dashboard_flash_type']);
$hosName = $_SESSION['name'] ?? 'Head of Sports';
$totalPlayers = array_sum(array_map(
    static fn (array $summary): int => (int) $summary['player_count'],
    $sportSummary
));
$sportCount = count($sportSummary);
$coachCount = count($coaches);
$pendingRequestCount = count($transferPlayers);
$bodyClass = 'sports-dashboard';
$pageTitle = 'Head of Sports dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= htmlspecialchars($hosName, ENT_QUOTES, 'UTF-8') ?></h1>
<section class="coach-overview" aria-label="Portal overview">
    <article class="coach-overview-card">
        <span class="coach-overview-label">Sports</span>
        <strong class="coach-overview-value"><?= $sportCount ?></strong>
        <span class="coach-overview-detail">Available sports</span>
    </article>
    <article class="coach-overview-card">
        <span class="coach-overview-label">Players</span>
        <strong class="coach-overview-value"><?= $totalPlayers ?></strong>
        <span class="coach-overview-detail">Assigned to a sport</span>
    </article>
    <article class="coach-overview-card">
        <span class="coach-overview-label">Coaches</span>
        <strong class="coach-overview-value"><?= $coachCount ?></strong>
        <span class="coach-overview-detail">Registered coaches</span>
    </article>
    <article class="coach-overview-card">
        <span class="coach-overview-label">Pending requests</span>
        <strong class="coach-overview-value"><?= $pendingRequestCount ?></strong>
        <span class="coach-overview-detail">Players awaiting placement</span>
    </article>
</section>

<section class="dashboard-table-section dashboard-table-container" data-table-container>
    <h2>Roster summary by sport</h2>
    <div class="dashboard-table-tools">
        <div>
            <label for="hos-sports-search">Search sports</label>
            <input id="hos-sports-search" type="search" data-table-search
                   placeholder="Search sport name" autocomplete="off">
        </div>
        <p data-table-results aria-live="polite">
            Showing all <?= $sportCount ?> <?= $sportCount === 1 ? 'sport' : 'sports' ?>.
        </p>
        <div class="dashboard-mobile-sort">
            <label for="hos-sports-sort">Sort sports</label>
            <select id="hos-sports-sort" data-table-sort-select>
                <option value="0-asc">Sport name (A–Z)</option>
                <option value="0-desc">Sport name (Z–A)</option>
                <option value="1-asc">Players (low to high)</option>
                <option value="1-desc">Players (high to low)</option>
                <option value="2-asc">Coaches (low to high)</option>
                <option value="2-desc">Coaches (high to low)</option>
            </select>
        </div>
    </div>
    <div class="table-wrap dashboard-table-wrap">
        <table class="dashboard-data-table" data-dashboard-table
               data-item-label="sport" data-item-label-plural="sports">
            <thead>
                <tr>
                    <th scope="col" aria-sort="ascending">
                        <button class="dashboard-sort-button" type="button" data-table-sort="0">
                            Sport <span aria-hidden="true" data-sort-indicator>↑</span>
                        </button>
                    </th>
                    <th scope="col" aria-sort="none">
                        <button class="dashboard-sort-button" type="button" data-table-sort="1">
                            Players <span aria-hidden="true" data-sort-indicator>↕</span>
                        </button>
                    </th>
                    <th scope="col" aria-sort="none">
                        <button class="dashboard-sort-button" type="button" data-table-sort="2">
                            Coaches <span aria-hidden="true" data-sort-indicator>↕</span>
                        </button>
                    </th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($sportSummary as $summary): ?>
                    <tr data-table-row>
                        <td data-label="Sport" data-search-cell><?= htmlspecialchars($summary['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td data-label="Players" data-search-cell><?= (int) $summary['player_count'] ?></td>
                        <td data-label="Coaches" data-search-cell><?= (int) $summary['coach_count'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <tr data-table-no-results hidden>
                    <td colspan="3">No sports match your search.</td>
                </tr>
            </tbody>
        </table>
    </div>
</section>

<section class="transfer-portal-admin dashboard-table-container" data-table-container
         aria-labelledby="hos-transfer-heading">
    <h2 id="hos-transfer-heading">Transfer Portal - Pending Requests</h2>
    <p>Review sport requests and assign unassigned players to a team.</p>
    <?php if ($transferPlayers === []): ?>
        <p>No unassigned players are waiting for placement.</p>
    <?php else: ?>
        <div class="dashboard-table-tools">
            <div>
                <label for="hos-transfer-search">Search pending players</label>
                <input id="hos-transfer-search" type="search" data-table-search
                       placeholder="Search name, email, or requested sport" autocomplete="off">
            </div>
            <p data-table-results aria-live="polite">
                Showing all <?= $pendingRequestCount ?> <?= $pendingRequestCount === 1 ? 'player' : 'players' ?>.
            </p>
            <div class="dashboard-mobile-sort">
                <label for="hos-transfer-sort">Sort pending players</label>
                <select id="hos-transfer-sort" data-table-sort-select>
                    <option value="0-asc">Name (A–Z)</option>
                    <option value="0-desc">Name (Z–A)</option>
                    <option value="1-asc">Email (A–Z)</option>
                    <option value="1-desc">Email (Z–A)</option>
                    <option value="2-asc">Requested sport (A–Z)</option>
                    <option value="2-desc">Requested sport (Z–A)</option>
                </select>
            </div>
        </div>
        <div class="table-wrap transfer-table-wrap">
            <table class="transfer-table dashboard-data-table" data-dashboard-table
                   data-item-label="player" data-item-label-plural="players">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="ascending">
                            <button class="dashboard-sort-button" type="button" data-table-sort="0">
                                Player Name <span aria-hidden="true" data-sort-indicator>↑</span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button class="dashboard-sort-button" type="button" data-table-sort="1">
                                Email <span aria-hidden="true" data-sort-indicator>↕</span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button class="dashboard-sort-button" type="button" data-table-sort="2">
                                Requested Sport <span aria-hidden="true" data-sort-indicator>↕</span>
                            </button>
                        </th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transferPlayers as $transferPlayer): ?>
                        <tr data-table-row>
                            <td data-label="Player Name" data-search-cell><?= htmlspecialchars($transferPlayer['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Email" data-search-cell><?= htmlspecialchars($transferPlayer['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Requested Sport" data-search-cell><?= htmlspecialchars($transferPlayer['requested_sport_name'] ?? 'No request', ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Action">
                                <form class="transfer-assign-form" method="post"
                                      action="<?= htmlspecialchars(app_url('/hos/assign_player.php'), ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="player_id" value="<?= (int) $transferPlayer['id'] ?>">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
                                    <label class="visually-hidden"
                                           for="assign-sport-<?= (int) $transferPlayer['id'] ?>">
                                        Assign sport for <?= htmlspecialchars($transferPlayer['name'], ENT_QUOTES, 'UTF-8') ?>
                                    </label>
                                    <select id="assign-sport-<?= (int) $transferPlayer['id'] ?>"
                                            name="sport_id" required>
                                        <option value="">Select sport</option>
                                        <?php foreach ($availableSports as $availableSport): ?>
                                            <option value="<?= (int) $availableSport['id'] ?>"
                                                <?= $transferPlayer['requested_sport_id'] !== null
                                                    && (int) $transferPlayer['requested_sport_id'] === (int) $availableSport['id']
                                                    ? 'selected'
                                                    : '' ?>>
                                                <?= htmlspecialchars($availableSport['name'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <button class="button" type="submit">Assign</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-table-no-results hidden>
                        <td colspan="4">No players match your search.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="dashboard-table-section dashboard-table-container" data-table-container>
    <h2>Coaches</h2>
    <?php if ($coaches === []): ?>
        <p>No coaches are registered yet.</p>
    <?php else: ?>
        <div class="dashboard-table-tools">
            <div>
                <label for="hos-coach-search">Search coaches</label>
                <input id="hos-coach-search" type="search" data-table-search
                       placeholder="Search name or sport" autocomplete="off">
            </div>
            <p data-table-results aria-live="polite">
                Showing all <?= $coachCount ?> <?= $coachCount === 1 ? 'coach' : 'coaches' ?>.
            </p>
            <div class="dashboard-mobile-sort">
                <label for="hos-coach-sort">Sort coaches</label>
                <select id="hos-coach-sort" data-table-sort-select>
                    <option value="">Current order</option>
                    <option value="0-asc">Name (A–Z)</option>
                    <option value="0-desc">Name (Z–A)</option>
                    <option value="1-asc">Sport (A–Z)</option>
                    <option value="1-desc">Sport (Z–A)</option>
                </select>
            </div>
        </div>
        <div class="table-wrap dashboard-table-wrap">
            <table class="dashboard-data-table" data-dashboard-table
                   data-item-label="coach" data-item-label-plural="coaches">
                <thead>
                    <tr>
                        <th scope="col" aria-sort="none">
                            <button class="dashboard-sort-button" type="button" data-table-sort="0">
                                Name <span aria-hidden="true" data-sort-indicator>↕</span>
                            </button>
                        </th>
                        <th scope="col" aria-sort="none">
                            <button class="dashboard-sort-button" type="button" data-table-sort="1">
                                Sport <span aria-hidden="true" data-sort-indicator>↕</span>
                            </button>
                        </th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($coaches as $coach): ?>
                        <tr data-table-row>
                            <td data-label="Name" data-search-cell><?= htmlspecialchars($coach['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Sport" data-search-cell><?= htmlspecialchars($coach['sport_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') ?></td>
                            <td data-label="Action">
                                <a class="button" href="<?= htmlspecialchars(
                                    app_url('/hos/coach_chat.php?coach_id=' . (int) $coach['id']),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>">Message</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr data-table-no-results hidden>
                        <td colspan="3">No coaches match your search.</td>
                    </tr>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<section class="dashboard-table-section" id="photographers"
         aria-labelledby="hos-photographers-heading">
    <h2 id="hos-photographers-heading">Photographer accounts</h2>
    <p>Create a secure account for someone managing the public photo galleries.</p>
    <form class="hos-photographer-form" method="post"
          action="<?= htmlspecialchars(app_url('/hos/create_photographer.php'), ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="csrf_token"
               value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
        <label for="photographer-name">Full name</label>
        <input id="photographer-name" name="name" type="text" maxlength="120"
               autocomplete="name" required>
        <label for="photographer-email">Email</label>
        <input id="photographer-email" name="email" type="email" maxlength="254"
               autocomplete="email" required>
        <label for="photographer-password">Temporary password</label>
        <input id="photographer-password" name="password" type="password" minlength="12"
               autocomplete="new-password" required>
        <button type="submit">Create account</button>
    </form>

    <?php if ($photographers === []): ?>
        <p>No photographer accounts yet. Create one when you're ready to share gallery access.</p>
    <?php else: ?>
        <ul class="hos-photographer-list">
            <?php foreach ($photographers as $photographer): ?>
                <li>
                    <strong><?= htmlspecialchars($photographer['name'], ENT_QUOTES, 'UTF-8') ?></strong>
                    <span><?= htmlspecialchars($photographer['email'], ENT_QUOTES, 'UTF-8') ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<script src="<?= htmlspecialchars(
    app_url('/assets/js/dashboard-tables.js'),
    ENT_QUOTES,
    'UTF-8'
) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
