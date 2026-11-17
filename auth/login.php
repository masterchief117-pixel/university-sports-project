<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$destinations = [
    'player' => '../player/dashboard.php',
    'coach' => '../coach/dashboard.php',
    'hos' => '../hos/dashboard.php',
];
$error = '';
$email = '';
$authFlash = $_SESSION['auth_flash'] ?? '';
unset($_SESSION['auth_flash']);

if (!empty($_SESSION['user_id']) && isset($destinations[$_SESSION['role'] ?? ''])) {
    header('Location: ' . $destinations[$_SESSION['role']]);
    exit;
}

if (empty($_SESSION['user_id']) && isset($_COOKIE[REMEMBER_COOKIE_NAME])
    && restore_remembered_login($pdo)
    && isset($destinations[$_SESSION['role'] ?? ''])) {
    header('Location: ' . $destinations[$_SESSION['role']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $csrfInput = $_POST['csrf_token'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';
    $csrfToken = is_string($csrfInput) ? $csrfInput : '';

    if (!csrf_token_is_valid($csrfToken)) {
        $error = 'Your form session expired. Please submit the form again.';
    } elseif ($email === '' || $password === '') {
        $error = 'Enter both your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare(
            'SELECT id, name, email, password_hash, role, sport_id
             FROM users WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            error_log('Login failed: account lookup or password verification failed.');
            $error = 'Invalid email or password.';
        } elseif (!isset($destinations[$user['role']])) {
            error_log('Login failed: account has an unsupported role.');
            $error = 'Invalid email or password.';
        } else {
            try {
                revoke_remembered_login($pdo);
                $rememberInput = $_POST['remember_me'] ?? '';
                if (is_string($rememberInput) && $rememberInput === '1') {
                    issue_remembered_login($pdo, (int) $user['id']);
                }
            } catch (PDOException $exception) {
                error_log('Remember token setup failed: ' . $exception->getMessage());
                clear_remember_cookie();
                $error = 'Unable to complete login right now. Please try again later.';
            }

            if ($error === '') {
                session_regenerate_id(true);
                $_SESSION['user_id'] = (int) $user['id'];
                $_SESSION['name'] = $user['name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['sport_id'] = $user['sport_id'] === null
                    ? null
                    : (int) $user['sport_id'];
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

                header('Location: ' . $destinations[$user['role']]);
                exit;
            }
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<h1>Log in</h1>
<?php if ($authFlash !== ''): ?>
    <p class="transfer-notice" role="status"><?= htmlspecialchars($authFlash, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">

    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username"
           value="<?= htmlspecialchars($email, ENT_QUOTES, 'UTF-8') ?>" required>

    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button class="password-toggle" type="button" data-password-toggle="password"
            aria-controls="password" aria-pressed="false">Show password</button>

    <label class="remember-me">
        <input name="remember_me" type="checkbox" value="1">
        Remember me for 30 days
    </label>

    <button type="submit">Log in</button>
</form>
<script src="<?= htmlspecialchars(app_url('/assets/js/password-toggle.js'), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
