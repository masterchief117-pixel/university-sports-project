<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/auth_check.php';
require_once __DIR__ . '/../config/db.php';

$roles = [
    'player' => 'Player',
    'coach' => 'Coach',
    'hos' => 'Head of Sports',
];
$error = '';
$name = '';
$email = '';
$selectedRole = 'player';
$selectedSportId = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nameInput = $_POST['name'] ?? '';
    $emailInput = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $roleInput = $_POST['role'] ?? '';
    $sportInput = $_POST['sport_id'] ?? '';
    $tokenInput = $_POST['csrf_token'] ?? '';
    $name = is_string($nameInput) ? trim($nameInput) : '';
    $email = is_string($emailInput) ? trim($emailInput) : '';
    $password = is_string($password) ? $password : '';
    $selectedRole = is_string($roleInput) ? $roleInput : '';
    $selectedSportId = is_string($sportInput) ? $sportInput : '';
    $submittedToken = is_string($tokenInput) ? $tokenInput : '';

    if (!hash_equals($_SESSION['csrf_token'], $submittedToken)) {
        $error = 'Your form session expired. Please submit the form again.';
    } elseif ($name === '' || strlen($name) > 120 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 254 || strlen($password) < 10
        || !array_key_exists($selectedRole, $roles)) {
        $error = 'Enter a valid name, email, password (at least 10 characters), and role.';
    } else {
        $sportId = null;

        if ($selectedRole !== 'hos') {
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
        }

        if ($error === '') {
            try {
                $insert = $pdo->prepare(
                    'INSERT INTO users (name, email, password_hash, role, sport_id)
                     VALUES (:name, :email, :password_hash, :role, :sport_id)'
                );
                $insert->bindValue(':name', $name, PDO::PARAM_STR);
                $insert->bindValue(':email', $email, PDO::PARAM_STR);
                $insert->bindValue(
                    ':password_hash',
                    password_hash($password, PASSWORD_DEFAULT),
                    PDO::PARAM_STR
                );
                $insert->bindValue(':role', $selectedRole, PDO::PARAM_STR);
                $insert->bindValue(
                    ':sport_id',
                    $sportId,
                    $sportId === null ? PDO::PARAM_NULL : PDO::PARAM_INT
                );
                $insert->execute();

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

    <label for="role">Role</label>
    <select id="role" name="role" required>
        <?php foreach ($roles as $value => $label): ?>
            <option value="<?= htmlspecialchars($value, ENT_QUOTES, 'UTF-8') ?>"
                <?= $selectedRole === $value ? 'selected' : '' ?>>
                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
            </option>
        <?php endforeach; ?>
    </select>

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
<script>
    (() => {
        const roleField = document.getElementById("role");
        const sportField = document.getElementById("sport-field");
        const sportSelect = document.getElementById("sport_id");

        const updateSportField = () => {
            const isHos = roleField.value === "hos";
            sportField.hidden = isHos;
            sportSelect.disabled = isHos;
            sportSelect.required = !isHos;
        };

        roleField.addEventListener("change", updateSportField);
        updateSportField();
    })();
</script>
<?php require __DIR__ . '/../includes/footer.php'; ?>
