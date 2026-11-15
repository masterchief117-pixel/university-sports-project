<?php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../config/db.php';

$destinations = [
    'player' => '../player/dashboard.php',
    'coach' => '../coach/dashboard.php',
    'hos' => '../hos/dashboard.php',
];
$error = '';

if (!empty($_SESSION['user_id']) && isset($destinations[$_SESSION['role'] ?? ''])) {
    header('Location: ' . $destinations[$_SESSION['role']]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $emailInput = $_POST['email'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if ($email === '' || $password === '') {
        $error = 'Enter both your email address and password.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } else {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            error_log('Login failed: account lookup or password verification failed.');
            $error = 'Invalid email or password.';
        } elseif (!isset($destinations[$user['role']])) {
            error_log('Login failed: account has an unsupported role.');
            $error = 'Invalid email or password.';
        } else {
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

require_once __DIR__ . '/../includes/auth_check.php';
$pageTitle = 'Log in';
require __DIR__ . '/../includes/header.php';
?>
<h1>Log in</h1>
<?php if (isset($_GET['registered'])): ?>
    <p role="status">Registration successful. You can now log in.</p>
<?php endif; ?>
<?php if ($error !== ''): ?>
    <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
<?php endif; ?>
<form method="post" action="">
    <label for="email">Email</label>
    <input id="email" name="email" type="email" autocomplete="username" required>
    <label for="password">Password</label>
    <input id="password" name="password" type="password" autocomplete="current-password" required>
    <button type="submit">Log in</button>
</form>
<?php require __DIR__ . '/../includes/footer.php'; ?>
