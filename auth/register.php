<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/user_accounts.php';

$error = '';
$name = '';
$email = '';
$selectedSportId = '';
$selectedRole = 'player';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $sportInput = $_POST['sport_id'] ?? '';
    $roleInput = $_POST['role'] ?? 'player';
    $tokenInput = $_POST['csrf_token'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($password) ? $password : '';
    $selectedSportId = is_string($sportInput) ? $sportInput : '';
    $selectedRole = in_array($roleInput, ['player', 'coach', 'hos', 'photographer'], true)
        ? $roleInput
        : 'player';
    $submittedToken = is_string($tokenInput) ? $tokenInput : '';

    if (!csrf_token_is_valid($submittedToken)) {
        $error = 'Your form session expired. Please submit the form again.';
    } elseif ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254 || strlen($password) < 10) {
        $error = 'Enter a valid name and email address, and a password of at least 10 characters.';
    } else {
        $sportId = null;
        if (in_array($selectedRole, ['player', 'coach'], true)) {
            $validatedSportId = filter_var($selectedSportId, FILTER_VALIDATE_INT);
            if (!$validatedSportId || $validatedSportId < 1) {
                $error = 'Select a sport for this role.';
            } else {
                $sportQuery = $pdo->prepare('SELECT id FROM sports WHERE id = :id');
                $sportQuery->execute(['id' => $validatedSportId]);
                if (!$sportQuery->fetch()) {
                    $error = 'Select a valid sport.';
                } else {
                    $sportId = (int) $validatedSportId;
                }
            }
        }

        if ($error === '') {
            try {
                create_account($pdo, $name, $email, $password, $selectedRole, $sportId);

                set_flash('Registration successful. You can now log in.', 'success');
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
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-copy">
            <p class="auth-kicker">University Sports Portal</p>
            <h1>Create an account</h1>
            <p>Join the campus athletic community and get connected with your sport, coach, and team moments.</p>
        </div>

        <div class="auth-form-wrap">
            <?php if ($error !== ''): ?>
                <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form class="auth-form" method="post" action="<?= htmlspecialchars(app_url('/auth/register.php'), ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

                <div class="form-row">
                    <label for="name">Full Name</label>
                    <input id="name" name="name" type="text" maxlength="120" autocomplete="name"
                           value="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-row">
                    <label for="email">Email</label>
                    <input id="email" name="email" type="email" maxlength="254" autocomplete="email"
                           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>
                </div>

                <div class="form-row">
                    <label for="password">Password</label>
                    <div class="password-field">
                        <input id="password" name="password" type="password" minlength="10"
                               autocomplete="new-password" required>
                        <button class="password-toggle" type="button" data-password-toggle="password"
                                aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                </div>

                <div class="form-row">
                    <label for="role">Role</label>
                    <select id="role" name="role" data-role-select>
                        <option value="player" <?= $selectedRole === 'player' ? 'selected' : '' ?>>Player</option>
                        <option value="coach" <?= $selectedRole === 'coach' ? 'selected' : '' ?>>Coach</option>
                        <option value="hos" <?= $selectedRole === 'hos' ? 'selected' : '' ?>>Head of Sports</option>
                        <option value="photographer" <?= $selectedRole === 'photographer' ? 'selected' : '' ?>>Photographer</option>
                    </select>
                </div>

                <div class="form-row" id="sport-field" data-sport-field>
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

                <button class="btn auth-submit" type="submit">Register</button>
            </form>
        </div>
    </div>
</div>
<script src="<?= htmlspecialchars(app_url('/assets/js/password-toggle.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<script>
    document.addEventListener('DOMContentLoaded', () => {
        const roleSelect = document.querySelector('[data-role-select]');
        const sportField = document.querySelector('[data-sport-field]');
        const toggleSportField = () => {
            if (!roleSelect || !sportField) {
                return;
            }
            const requiresSport = ['player', 'coach'].includes(roleSelect.value);
            sportField.hidden = !requiresSport;
            const sportInput = sportField.querySelector('select');
            if (sportInput) {
                sportInput.disabled = !requiresSport;
                sportInput.required = requiresSport;
            }
        };
        if (roleSelect) {
            roleSelect.addEventListener('change', toggleSportField);
            toggleSportField();
        }
    });
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
