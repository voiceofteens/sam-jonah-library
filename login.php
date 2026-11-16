<?php
require_once __DIR__ . '/includes/authentication.php';
require_once __DIR__ . '/config/database.php';

const MAX_ATTEMPTS = 5;
const LOCKOUT_SECONDS = 300;

$dashboards = ['student' => 'student/dashboard.php', 'staff' => 'staff/dashboard.php', 'admin' => 'admin/dashboard.php'];
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: " . ($dashboards[$_SESSION['role']] ?? 'index.php'));
    exit();
}

$error = isset($_GET['expired']) ? "Your session expired. Please sign in again." : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';
    $lockedUntil = $_SESSION['locked_until'] ?? 0;

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid request. Please refresh the page and try again.";
    } elseif ($lockedUntil > time()) {
        $error = "Too many failed attempts. Try again in " . ceil(($lockedUntil - time()) / 60) . " minute(s).";
    } elseif ($identifier === '' || $password === '') {
        $error = "Enter your Student ID or email and password.";
    } else {
        $stmt = $conn->prepare("SELECT user_id, student_id, full_name, email, password_hash, role
                                FROM users WHERE student_id = :student_id OR email = :email LIMIT 1");
        $stmt->execute(['student_id' => $identifier, 'email' => $identifier]);
        $user = $stmt->fetch();

        // Always run a hash check so response time doesn't reveal valid accounts.
        $hash = $user['password_hash'] ?? '$2y$10$usesomesillystringforsaltuQ3m9y3Yb6x0wq1sQn4b6Wc1u1O1K';
        if (password_verify($password, $hash) && $user) {
            session_regenerate_id(true);
            $_SESSION = [
                'user_id' => $user['user_id'], 'student_id' => $user['student_id'],
                'full_name' => $user['full_name'], 'email' => $user['email'],
                'role' => $user['role'], 'last_activity' => time()
            ];
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
                     ->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
            }
            header("Location: " . ($dashboards[$user['role']] ?? 'index.php'));
            exit();
        }
        $_SESSION['failed_logins'] = ($_SESSION['failed_logins'] ?? 0) + 1;
        if ($_SESSION['failed_logins'] >= MAX_ATTEMPTS) {
            $_SESSION['locked_until'] = time() + LOCKOUT_SECONDS;
            $_SESSION['failed_logins'] = 0;
        }
        $error = "Incorrect Student ID, email or password.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | Sam Jonah Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="icon" type="image/png" href="assets/images/ucc-crest.png">
</head>
<body>
<div class="auth-container">
    <div class="auth-card">
        <div class="auth-brand">
            <img src="assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-auth">
            <h1>Sam Jonah Library</h1>
        </div>
        <p>Facility Booking Management System</p>
        <p class="motto">Veritas Nobis Lumen &middot; Truth, Our Guide</p>
        <h2>Sign In</h2>
        <?php if ($error !== ''): ?>
            <div class="alert alert-error"><?= escape($error) ?></div>
        <?php endif; ?>
        <form method="POST" action="login.php">
            <?= csrfField() ?>
            <label for="identifier">Student ID or Email</label>
            <input type="text" id="identifier" name="identifier" placeholder="Enter your Student ID or email" required autofocus>
            <label for="password">Password</label>
            <input type="password" id="password" name="password" placeholder="Enter your password" required>
            <button type="submit" class="btn-primary">Sign In</button>
        </form>
        <p class="auth-note">Students and authorised library staff can sign in here.</p>
        <a href="index.php">Back to Home</a>
    </div>
</div>
</body>
</html>
