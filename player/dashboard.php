<?php
declare(strict_types=1);

error_reporting(E_ALL);

require_once __DIR__ . '/../includes/auth_check.php';
require_role('player');

require_once __DIR__ . '/../config/db.php';
$accountQuery = $pdo->prepare(
    "SELECT sport_id, requested_sport_id FROM users
     WHERE id = :user_id AND role = 'player'
     LIMIT 1"
);
$accountQuery->execute(['user_id' => (int) $_SESSION['user_id']]);
$playerAccount = $accountQuery->fetch(PDO::FETCH_ASSOC);
if (!$playerAccount) {
    http_response_code(401);
    exit('Player account not found. Please log in again.');
}

$sport_id = $playerAccount['sport_id'] === null ? null : (int) $playerAccount['sport_id'];
$requested_sport_id = $playerAccount['requested_sport_id'] === null
    ? null
    : (int) $playerAccount['requested_sport_id'];
$playerStatus = $sport_id === null
    ? ($requested_sport_id === null ? 'No request' : 'Request pending')
    : 'On a team';
$_SESSION['sport_id'] = $sport_id;
$sportName = 'Unassigned';
$coaches = [];
$teammates = [];
$availableSports = [];
$requestedSportName = null;

if ($sport_id !== null) {
    $sportQuery = $pdo->prepare('SELECT name FROM sports WHERE id = :sport_id');
    $sportQuery->execute(['sport_id' => $sport_id]);
    $sportName = $sportQuery->fetchColumn() ?: 'Unassigned';

    $coachesQuery = $pdo->prepare(
        "SELECT users.id, users.name FROM users
         WHERE users.role = 'coach' AND users.sport_id = :sport_id
         ORDER BY users.name, users.id"
    );
    $coachesQuery->execute(['sport_id' => $sport_id]);
    $coaches = $coachesQuery->fetchAll();

    $teammatesQuery = $pdo->prepare(
        "SELECT users.id, users.name FROM users
         WHERE users.role = 'player'
           AND users.sport_id = :sport_id
           AND users.id <> :user_id
         ORDER BY users.name, users.id"
    );
    $teammatesQuery->execute([
        'sport_id' => $sport_id,
        'user_id' => (int) $_SESSION['user_id'],
    ]);
    $teammates = $teammatesQuery->fetchAll();
} else {
    $sportsQuery = $pdo->query('SELECT id, name FROM sports ORDER BY name');
    $availableSports = $sportsQuery->fetchAll(PDO::FETCH_ASSOC);
    foreach ($availableSports as $availableSport) {
        if ($requested_sport_id !== null
            && (int) $availableSport['id'] === $requested_sport_id) {
            $requestedSportName = $availableSport['name'];
            break;
        }
    }
}

$player_dashboard_flash = $_SESSION['player_dashboard_flash'] ?? $_SESSION['flash'] ?? '';
$player_dashboard_flash_type = $_SESSION['player_dashboard_flash_type'] ?? $_SESSION['flash_type'] ?? 'success';
if ($player_dashboard_flash !== '') {
    $_SESSION['flash'] = $player_dashboard_flash;
    $_SESSION['flash_type'] = $player_dashboard_flash_type;
}
unset($_SESSION['player_dashboard_flash']);
unset($_SESSION['player_dashboard_flash_type']);
$playerName = $_SESSION['name'] ?? 'Player';
$bodyClass = 'sports-dashboard';
$pageTitle = 'Player dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8') ?></h1>
<section class="coach-overview" aria-label="Player overview">
    <article class="coach-overview-card">
        <span class="coach-overview-label">Sport</span>
        <strong class="coach-overview-value coach-overview-sport">
            <?= htmlspecialchars($sportName, ENT_QUOTES, 'UTF-8') ?>
        </strong>
        <span class="coach-overview-detail">Current team assignment</span>
    </article>
    <article class="coach-overview-card">
        <span class="coach-overview-label">Coaches</span>
        <strong class="coach-overview-value"><?= count($coaches) ?></strong>
        <span class="coach-overview-detail">Assigned to your sport</span>
    </article>
    <article class="coach-overview-card">
        <span class="coach-overview-label">Teammates</span>
        <strong class="coach-overview-value"><?= count($teammates) ?></strong>
        <span class="coach-overview-detail">Other players on your team</span>
    </article>
    <article class="coach-overview-card">
        <span class="coach-overview-label">Status</span>
        <strong class="coach-overview-value"><?= htmlspecialchars($playerStatus, ENT_QUOTES, 'UTF-8') ?></strong>
        <span class="coach-overview-detail">
            <?= $sport_id === null
                ? 'Your team assignment status'
                : 'You are assigned to a team' ?>
        </span>
    </article>
</section>

<?php if ($sport_id === null): ?>
    <section class="transfer-portal" aria-labelledby="transfer-portal-heading">
        <p class="transfer-eyebrow">Transfer Portal</p>
        <h2 id="transfer-portal-heading">You are currently unassigned.</h2>
        <p>Request a new sport to join.</p>
        <?php if ($requested_sport_id !== null): ?>
            <p class="transfer-pending">
                Your request for
                <strong><?= htmlspecialchars($requestedSportName ?? 'your selected sport', ENT_QUOTES, 'UTF-8') ?></strong>
                is awaiting review.
            </p>
        <?php endif; ?>
        <form class="transfer-request-form" method="post"
              action="<?= htmlspecialchars(app_url('/player/request_sport.php'), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <label for="requested-sport">Choose a sport</label>
            <select id="requested-sport" name="requested_sport_id" required>
                <option value="">Select a sport</option>
                <?php foreach ($availableSports as $availableSport): ?>
                    <option value="<?= (int) $availableSport['id'] ?>"
                        <?= $requested_sport_id === (int) $availableSport['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($availableSport['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button class="button" type="submit">Request to Join</button>
        </form>
    </section>
<?php else: ?>
    <div class="team-dashboard-grid">
    <section class="coach-card dashboard-table-section" aria-labelledby="player-coaches-heading">
    <h2 id="player-coaches-heading">Your coaches</h2>
    <?php if ($coaches === []): ?>
        <p>No coaches are assigned to your sport yet.</p>
    <?php else: ?>
        <div class="dashboard-table-container" data-table-container>
            <div class="dashboard-table-tools">
                <div>
                    <label for="player-coach-search">Search coaches</label>
                    <input id="player-coach-search" type="search" data-table-search
                           placeholder="Search coach name" autocomplete="off">
                </div>
                <p data-table-results aria-live="polite">
                    Showing all <?= count($coaches) ?> <?= count($coaches) === 1 ? 'coach' : 'coaches' ?>.
                </p>
                <div class="dashboard-mobile-sort">
                    <label for="player-coach-sort">Sort coaches</label>
                    <select id="player-coach-sort" data-table-sort-select>
                        <option value="0-asc">Name (A–Z)</option>
                        <option value="0-desc">Name (Z–A)</option>
                    </select>
                </div>
            </div>
            <div class="table-wrap dashboard-table-wrap">
                <table class="dashboard-data-table" data-dashboard-table
                       data-item-label="coach" data-item-label-plural="coaches">
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="ascending">
                                <button class="dashboard-sort-button" type="button" data-table-sort="0">
                                    Name <span aria-hidden="true" data-sort-indicator>↑</span>
                                </button>
                            </th>
                            <th scope="col">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coaches as $coach): ?>
                            <tr data-table-row>
                                <td data-label="Name" data-search-cell><?= htmlspecialchars($coach['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td data-label="Action">
                                    <a class="button button-message" href="<?= htmlspecialchars(
                                        app_url('/player/coach_chat.php?target_user_id=' . (int) $coach['id']),
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>">Message</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr data-table-no-results hidden>
                            <td colspan="2">No coaches match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    </section>

    <section class="coach-card dashboard-table-section" aria-labelledby="player-teammates-heading">
    <h2 id="player-teammates-heading">Your teammates</h2>
    <?php if ($teammates === []): ?>
        <p>No other players are assigned to your sport yet.</p>
    <?php else: ?>
        <div class="dashboard-table-container" data-table-container>
            <div class="dashboard-table-tools">
                <div>
                    <label for="player-teammate-search">Search teammates</label>
                    <input id="player-teammate-search" type="search" data-table-search
                           placeholder="Search teammate name" autocomplete="off">
                </div>
                <p data-table-results aria-live="polite">
                    Showing all <?= count($teammates) ?> <?= count($teammates) === 1 ? 'teammate' : 'teammates' ?>.
                </p>
                <div class="dashboard-mobile-sort">
                    <label for="player-teammate-sort">Sort teammates</label>
                    <select id="player-teammate-sort" data-table-sort-select>
                        <option value="0-asc">Name (A–Z)</option>
                        <option value="0-desc">Name (Z–A)</option>
                    </select>
                </div>
            </div>
            <div class="table-wrap dashboard-table-wrap">
                <table class="dashboard-data-table" data-dashboard-table
                       data-item-label="teammate" data-item-label-plural="teammates">
                    <thead>
                        <tr>
                            <th scope="col" aria-sort="ascending">
                                <button class="dashboard-sort-button" type="button" data-table-sort="0">
                                    Player <span aria-hidden="true" data-sort-indicator>↑</span>
                                </button>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($teammates as $teammate): ?>
                            <tr data-table-row>
                                <td class="roster-player-name" data-label="Player" data-search-cell>
                                    <?= htmlspecialchars($teammate['name'], ENT_QUOTES, 'UTF-8') ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr data-table-no-results hidden>
                            <td colspan="1">No teammates match your search.</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
    </section>

    <div class="dashboard-links">
            <a class="button" href="<?= htmlspecialchars(app_url('/player/team_chat.php'), ENT_QUOTES, 'UTF-8') ?>">
                Team Chat
            </a>
            <a class="button" href="<?= htmlspecialchars(app_url('/player/coach_chat.php'), ENT_QUOTES, 'UTF-8') ?>">
                Message Coach
            </a>
    </div>
    </div>
<?php endif; ?>
<script src="<?= htmlspecialchars(
    app_url('/assets/js/dashboard-tables.js'),
    ENT_QUOTES,
    'UTF-8'
) ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
