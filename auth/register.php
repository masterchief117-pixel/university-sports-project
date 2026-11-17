<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_accounts.php';

$error = '';
$name = '';
$email = '';
$selectedSportId = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $sportInput = $_POST['sport_id'] ?? '';
    $tokenInput = $_POST['csrf_token'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($password) ? $password : '';
    $selectedSportId = is_string($sportInput) ? $sportInput : '';
    $submittedToken = is_string($tokenInput) ? $tokenInput : '';

    if (!csrf_token_is_valid($submittedToken)) {
        $error = 'Your form session expired. Please submit the form again.';
    } elseif ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254 || strlen($password) < 10) {
        $error = 'Enter a valid name and email address, and a password of at least 10 characters.';
    } else {
        $validatedSportId = filter_var($selectedSportId, FILTER_VALIDATE_INT);

        if (!$validatedSportId || $validatedSportId < 1) {
            $error = 'Select a sport.';
        } else {
            $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :id');
            $sportQuery->execute(['id' => $validatedSportId]);

            if (!$sportQuery->fetch()) {
                $error = 'Select a valid sport.';
            } else {
                $sportId = $validatedSportId;
            }
        }

        if ($error === '') {
            try {
                create_player_account($pdo, $name, $email, $password, (int) $sportId);

                $_SESSION['auth_flash'] = 'Registration successful. You can now log in.';
                header('Location: ' . app_url('/auth/login.php'));
                exit;
            } catch (PDOException $exception) {
                if ($exception->getCode() !== '23000'
                    || (int) ($exception->errorInfo[1] ?? 0) !== 1062) {
                    throw $exception;
                }

                $error = 'An account with that email already exists.';
            }
        }
    }
}

$sports = $pdo->query('SELECT id, name FROM sports ORDER BY name')->fetchAll();
$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>
<h1>Create an account</h1>
<?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

    <label for="name">Full Name</label>
    <input id="name" name="name" type="text" maxlength="120" autocomplete="name"
           value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>

    <label for="email">Email</label>
    <input id="email" name="email" type="email" maxlength="254" autocomplete="email"
           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>

    <label for="password">Password (at least 10 characters)</label>
    <input id="password" name="password" type="password" minlength="10"
           autocomplete="new-password" required>
    <button class="password-toggle" type="button" data-password-toggle="password"
            aria-controls="password" aria-pressed="false">Show password</button>

    <div id="sport-field">
        <label for="sport_id">Sport</label>
        <select id="sport_id" name="sport_id">
            <option value="">Select a sport</option>
            <?php foreach ($sports as $sport): ?>
                <option value="<?= (int) $sport['id'] ?>"
                    <?= (string) $sport['id'] === (string) $selectedSportId ? 'selected' : '' ?>>
                    <?= htmlspecialchars($sport['name'], ENT_QUOTES, 'UTF-8') ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <button type="submit">Register</button>
</form>
<script src="<?= htmlspecialchars(app_url('/assets/js/password-toggle.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
