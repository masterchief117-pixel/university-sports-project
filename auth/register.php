<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $sportId = filter_input(INPUT_POST, 'sport_id', FILTER_VALIDATE_INT);

    if ($name === '' || !$email || strlen($password) < 10 || !$sportId) {
        $error = 'Provide your name, a valid email, a sport, and a password of at least 10 characters.';
    } else {
        $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :id');
        $sportQuery->execute(['id' => $sportId]);

        if (!$sportQuery->fetch()) {
            $error = 'Select a valid sport.';
        } else {
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, sport_id)
                     VALUES (:name, :email, :password_hash, \'player\', :sport_id)'
                );
                $insert->execute([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                    'sport_id' => $sportId,
                ]);
                header('Location: ' . app_url('/auth/login.php?registered=1'));
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() === '23000') {
                    $error = 'An account with that email already exists.';
                } else {
                    throw $exception;
                }
            }
        }
    }
}

$sports = $pdo->query('SELECT id, name FROM sports ORDER BY name')->fetchAll();
$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>
<h1>Player registration</h1>
<p>New self-service accounts are player accounts. Coach and Head of Sports accounts must be provisioned by an administrator.</p>
<?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">
    <label for="name">Name</label>
    <input id="name" name="name" autocomplete="name" required>
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="email" required>
    <label for="password">Password (at least 10 characters)</label>
    <input id="password" name="password" type="password" minlength="10" autocomplete="new-password" required>
    <label for="sport_id">Sport</label>
    <select id="sport_id" name="sport_id" required>
        <option value="">Select a sport</option>
        <?php foreach ($sports as $sport): ?>
            <option value="<?= (int) $sport['id'] ?>">
                <?= htmlspecialchars($sport['name'], ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>
    <button type="submit">Create player account</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
