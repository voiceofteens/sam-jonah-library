<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('admin');

$message = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Please reload the page.");
        }

        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($fullName === '' || $email === '' || $password === '') {
            throw new Exception("Complete all required fields.");
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Enter a valid email address.");
        }

        if (strlen($password) < 8) {
            throw new Exception("The password must contain at least 8 characters.");
        }

        $check = $conn->prepare("
            SELECT user_id
            FROM users
            WHERE email = ?
            LIMIT 1
        ");
        $check->execute([$email]);

        if ($check->fetch()) {
            throw new Exception("An account with this email already exists.");
        }

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $insert = $conn->prepare("
            INSERT INTO users
            (full_name, email, password_hash, role)
            VALUES (?, ?, ?, 'staff')
        ");

        $insert->execute([
            $fullName,
            $email,
            $passwordHash
        ]);

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $message = "Staff account created successfully.";
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "A database error occurred while creating the account.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $conn->query("
    SELECT user_id, full_name, student_id, email, role, created_at
    FROM users
    ORDER BY role, full_name
");

$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Accounts | Sam Jonah Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>

<body>

    <div class="dashboard-layout">

        <aside class="sidebar">
            <div class="sidebar-brand">
        <img src="../assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-sidebar">
                <h2>Sam Jonah</h2>
                <p>Administrator</p>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php" class="active">Manage Accounts</a>
                <a href="facilities.php">Manage Facilities</a>
                <a href="operating-hours.php">Operating Hours</a>
                <a href="bookings.php">All Bookings</a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php">Sign Out</a>
            </div>
        </aside>

        <main class="main-content">

            <h1>Manage Accounts</h1>
            <p>Create staff accounts and view registered system users.</p>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <section class="dashboard-section">

                <h2>Create Staff Account</h2>

                <form method="POST" class="booking-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <label for="full_name">Staff Full Name</label>
                    <input
                        type="text"
                        name="full_name"
                        id="full_name"
                        required>

                    <label for="email">Staff Email</label>
                    <input
                        type="email"
                        name="email"
                        id="email"
                        required>

                    <label for="password">Temporary Password</label>
                    <input
                        type="password"
                        name="password"
                        id="password"
                        minlength="8"
                        required>

                    <button type="submit" class="btn-primary">
                        Create Staff Account
                    </button>

                </form>

            </section>

            <section class="dashboard-section">

                <h2>Registered Accounts</h2>

                <div class="table-container">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Account ID</th>
                                <th>Full Name</th>
                                <th>Student ID</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Date Created</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($users as $user): ?>

                                <tr>
                                    <td><?= (int) $user['user_id'] ?></td>

                                    <td>
                                        <?= htmlspecialchars($user['full_name']) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user['student_id'] ?? 'N/A') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user['email'] ?? 'N/A') ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(ucfirst($user['role'])) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($user['created_at']) ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

</body>

</html>