<?php
declare(strict_types=1);

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
$hosDashboardFlash = $_SESSION['hos_dashboard_flash'] ?? '';
unset($_SESSION['hos_dashboard_flash']);
$hosName = $_SESSION['name'] ?? 'Head of Sports';
$pageTitle = 'Head of Sports dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h1>Welcome, <?= htmlspecialchars($hosName, ENT_QUOTES, 'UTF-8') ?></h1>
<?php if ($hosDashboardFlash !== ''): ?>
    <p class="transfer-notice" role="status"><?= htmlspecialchars($hosDashboardFlash, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>

<h2>Roster summary by sport</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Sport</th><th>Players</th><th>Coaches</th></tr>
        </thead>
        <tbody>
            <?php foreach ($sportSummary as $summary): ?>
                <tr>
                    <td><?= htmlspecialchars($summary['name'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $summary['player_count'] ?></td>
                    <td><?= (int) $summary['coach_count'] ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<section class="transfer-portal-admin" aria-labelledby="hos-transfer-heading">
    <h2 id="hos-transfer-heading">Transfer Portal (Unassigned Players)</h2>
    <p>Review sport requests and assign unassigned players to a team.</p>
    <?php if ($transferPlayers === []): ?>
        <p>No unassigned players are waiting for placement.</p>
    <?php else: ?>
        <div class="table-wrap transfer-table-wrap">
            <table class="transfer-table">
                <thead>
                    <tr>
                        <th scope="col">Player Name</th>
                        <th scope="col">Email</th>
                        <th scope="col">Requested Sport</th>
                        <th scope="col">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($transferPlayers as $transferPlayer): ?>
                        <tr>
                            <td><?= htmlspecialchars($transferPlayer['name'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($transferPlayer['email'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td><?= htmlspecialchars($transferPlayer['requested_sport_name'] ?? 'No request', ENT_QUOTES, 'UTF-8') ?></td>
                            <td>
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
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<h2>Coaches</h2>
<?php if ($coaches === []): ?>
    <p>No coaches are registered yet.</p>
<?php else: ?>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Name</th><th>Sport</th><th>Action</th></tr>
            </thead>
            <tbody>
                <?php foreach ($coaches as $coach): ?>
                    <tr>
                        <td><?= htmlspecialchars($coach['name'], ENT_QUOTES, 'UTF-8') ?></td>
                        <td><?= htmlspecialchars($coach['sport_name'] ?? 'Unassigned', ENT_QUOTES, 'UTF-8') ?></td>
                        <td>
                            <a class="button" href="<?= htmlspecialchars(
                                app_url('/hos/coach_chat.php?coach_id=' . (int) $coach['id']),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>">Message</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>
