<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('student');

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT student_id, full_name, email, created_at
    FROM users
    WHERE user_id = ?
    LIMIT 1
");

$stmt->execute([$userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    die("Student account could not be found.");
}

$msg = $err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cur = $_POST['current_password'] ?? '';
    $new = $_POST['new_password'] ?? '';
    $row = $conn->prepare("SELECT password_hash FROM users WHERE user_id = ?");
    $row->execute([$userId]);
    $hash = $row->fetchColumn();
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $err = "Invalid request. Please try again.";
    } elseif (!password_verify($cur, $hash)) {
        $err = "Your current password is incorrect.";
    } elseif (strlen($new) < 8 || $new !== ($_POST['confirm_password'] ?? '')) {
        $err = "The new password must be at least 8 characters and match the confirmation.";
    } else {
        $conn->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?")
             ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
        session_regenerate_id(true);
        $msg = "Password updated successfully.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile | Sam Jonah Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>

<body>

    <div class="dashboard-layout">

        <?php include __DIR__ . '/../includes/student-nav.php'; ?>

        <main class="main-content">

            <div class="welcome-section">
                <h1>My Profile</h1>
                <p>Manage your account details and keep your sign-in information up to date.</p>
            </div>

            <div class="profile-card">

                <h2>Student Information</h2>
                <p class="form-note">Your registered account details are shown below.</p>

                <p>
                    <strong>Full Name:</strong>
                    <?= htmlspecialchars($user['full_name']) ?>
                </p>

                <p>
                    <strong>Student ID:</strong>
                    <?= htmlspecialchars($user['student_id'] ?? 'Not provided') ?>
                </p>

                <p>
                    <strong>Email:</strong>
                    <?= htmlspecialchars($user['email'] ?? 'Not provided') ?>
                </p>

                <p>
                    <strong>Account Created:</strong>
                    <?= htmlspecialchars($user['created_at']) ?>
                </p>

            </div>

            <div class="profile-card password-card">
                <h2>Security &amp; Password</h2>
                <p class="form-note">Choose a strong password of at least 8 characters.</p>
                <?php if ($msg): ?><div class="alert alert-success"><?= escape($msg) ?></div><?php endif; ?>
                <?php if ($err): ?><div class="alert alert-error"><?= escape($err) ?></div><?php endif; ?>
                <form method="POST">
                    <?= csrfField() ?>
                    <p><label>Current password<br><input type="password" name="current_password" required></label></p>
                    <p><label>New password (8+ characters)<br><input type="password" name="new_password" minlength="8" required></label></p>
                    <p><label>Confirm new password<br><input type="password" name="confirm_password" minlength="8" required></label></p>
                    <button type="submit" class="btn-primary">Update Password</button>
                </form>
            </div>

        </main>

    </div>

</body>

</html>