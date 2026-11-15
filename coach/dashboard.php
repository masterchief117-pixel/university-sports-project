<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../config/db.php';

// Verify session and role
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'coach') {
    header("Location: ../auth/login.php");
    exit;
}

$sport_id = $_SESSION['sport_id'] ?? null;
$sport_name = 'Unknown Sport';
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

// Handle flash messages for success/error alerts
$coach_dashboard_flash = $_SESSION['coach_dashboard_flash'] ?? '';
unset($_SESSION['coach_dashboard_flash']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Coach Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;600;700;800&family=Poppins:wght@500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="/university_sports/assets/css/style.css">
</head>
<body class="coach-dashboard" data-base-path="/university_sports">
    <header class="site-header coach-header">
        <a class="coach-brand" href="/university_sports/">
            <span class="coach-brand-mark" aria-hidden="true">US</span>
            <span>University Sports Portal</span>
        </a>
        <nav aria-label="Main navigation">
            <a class="coach-nav-active" href="dashboard.php">Dashboard</a>
            <a href="../auth/logout.php">Log out</a>
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
            <p class="coach-notice" style="color: #059669; background: #d1fae5; padding: 10px; border-radius: 5px; font-weight: bold; margin-bottom: 20px;" role="status">
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

            <section class="coach-card roster-card" aria-labelledby="roster-heading">
                <div class="coach-section-heading roster-heading">
                    <span class="coach-section-icon roster-icon" aria-hidden="true">★</span>
                    <div>
                        <p class="coach-eyebrow">Your team</p>
                        <h2 id="roster-heading">Players on your roster</h2>
                    </div>
                </div>
                <div class="roster-table-wrap">
                    <table class="roster-table">
                        <thead>
                            <tr>
                                <th scope="col">Player</th>
                                <th scope="col">Email</th>
                                <th scope="col">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($players as $player): ?>
                                <tr>
                                    <td class="roster-player-name"><?= htmlspecialchars($player['name'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($player['email'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
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
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</body>
</html>