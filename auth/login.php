<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';

if (!empty($_SESSION['user_id'])) {
    $destinations = [
        'player' => '/player/dashboard.php',
        'coach' => '/coach/dashboard.php',
        'hos' => '/hos/dashboard.php',
    ];
    header('Location: ' . app_url($destinations[$_SESSION['role']] ?? '/auth/login.php'));
    exit;
}

require_once __DIR__ . '/../config/db.php';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';

    if (!$email || $password === '') {
        $error = 'Enter a valid email address and password.';
    } else {
        $query = $pdo->prepare(
            'SELECT id, name, email, password_hash, role
             FROM users WHERE email = :email LIMIT 1'
        );
        $query->execute(['email' => $email]);
        $user = $query->fetch();

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $error = 'Invalid email or password.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            $destinations = [
                'player' => '/player/dashboard.php',
                'coach' => '/coach/dashboard.php',
                'hos' => '/hos/dashboard.php',
            ];
            header('Location: ' . app_url($destinations[$user['role']]));
            exit;
        }
    }
}

$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<h1>Log in</h1>
<?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="<?= htmlspecialchars(app_url('/auth/login.php'), ENT_QUOTES, 'UTF-8') ?>">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username" required>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Log in</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
