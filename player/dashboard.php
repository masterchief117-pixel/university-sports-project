<?php
declare(strict_types=1);

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

$player_dashboard_flash = $_SESSION['player_dashboard_flash'] ?? '';
unset($_SESSION['player_dashboard_flash']);
$playerName = $_SESSION['name'] ?? 'Player';
$pageTitle = 'Player dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= htmlspecialchars($playerName, ENT_QUOTES, 'UTF-8') ?></h1>
<?php if ($player_dashboard_flash !== ''): ?>
    <p class="transfer-notice" role="status"><?= htmlspecialchars($player_dashboard_flash, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

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
    <p>Sport: <strong><?= htmlspecialchars($sportName, ENT_QUOTES, 'UTF-8') ?></strong></p>
    <h2>Your coaches</h2>
    <?php if ($coaches === []): ?>
        <p>No coaches are assigned to your sport yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($coaches as $coach): ?>
                <li>
                    <?= htmlspecialchars($coach['name'], ENT_QUOTES, 'UTF-8') ?>
                    <a href="<?= htmlspecialchars(
                        app_url('/player/coach_chat.php?target_user_id=' . (int) $coach['id']),
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>">Message</a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <h2>Your teammates</h2>
    <?php if ($teammates === []): ?>
        <p>No other players are assigned to your sport yet.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($teammates as $teammate): ?>
                <li><?= htmlspecialchars($teammate['name'], ENT_QUOTES, 'UTF-8') ?></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <div class="dashboard-links">
            <a class="button" href="<?= htmlspecialchars(app_url('/player/team_chat.php'), ENT_QUOTES, 'UTF-8') ?>">
                Team Chat
            </a>
            <a class="button" href="<?= htmlspecialchars(app_url('/player/coach_chat.php'), ENT_QUOTES, 'UTF-8') ?>">
                Message Coach
            </a>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
