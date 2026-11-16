<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('student');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $_SESSION['notification_error'] = "Invalid request. Please try again.";
        header("Location: notifications.php");
        exit();
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all') {

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE user_id = ?
        ");

        $stmt->execute([$_SESSION['user_id']]);

    } elseif ($action === 'mark_one') {

        $notificationId = (int) ($_POST['notification_id'] ?? 0);

        $stmt = $conn->prepare("
            UPDATE notifications
            SET is_read = 1
            WHERE notification_id = ?
              AND user_id = ?
        ");

        $stmt->execute([
            $notificationId,
            $_SESSION['user_id']
        ]);
    }

    header("Location: notifications.php");
    exit();
}

$error = $_SESSION['notification_error'] ?? '';
unset($_SESSION['notification_error']);

$stmt = $conn->prepare("
    SELECT notification_id, booking_id, message,
           notification_type, scheduled_at, sent_at, is_read
    FROM notifications
    WHERE user_id = ?
    ORDER BY notification_id DESC
");

$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

$unreadCount = 0;

foreach ($notifications as $notification) {
    if (!$notification['is_read']) {
        $unreadCount++;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications | Sam Jonah Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>
<body>

<div class="dashboard-layout">

    <?php include __DIR__ . '/../includes/student-nav.php'; ?>

    <main class="main-content">

        <h1>Notifications</h1>

        <p>
            You have <?= $unreadCount ?> unread notification(s).
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($unreadCount > 0): ?>

            <form method="POST" class="mark-all-form">

                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <input type="hidden" name="action" value="mark_all">

                <button type="submit" class="btn-primary">
                    Mark All as Read
                </button>

            </form>

        <?php endif; ?>

        <?php if (empty($notifications)): ?>

            <div class="dashboard-section">
                <p>You do not have any notifications yet.</p>
            </div>

        <?php else: ?>

            <?php foreach ($notifications as $notification): ?>

                <div class="notification-item <?= !$notification['is_read'] ? 'unread-notification' : '' ?>">

                    <p>
                        <?= htmlspecialchars($notification['message']) ?>
                    </p>

                    <small>
                        Type:
                        <?= htmlspecialchars(
                            ucwords(str_replace('_', ' ', $notification['notification_type']))
                        ) ?>
                    </small>

                    <small>
                        <?= htmlspecialchars(
                            $notification['sent_at'] ??
                            $notification['scheduled_at'] ??
                            'Date unavailable'
                        ) ?>
                    </small>

                    <p>
                        <?= $notification['is_read'] ? 'Read' : 'Unread' ?>
                    </p>

                    <?php if (!$notification['is_read']): ?>

                        <form method="POST">

                            <input type="hidden" name="csrf_token"
                                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                            <input type="hidden" name="action" value="mark_one">

                            <input type="hidden" name="notification_id"
                                   value="<?= (int) $notification['notification_id'] ?>">

                            <button type="submit" class="btn-primary">
                                Mark as Read
                            </button>

                        </form>

                    <?php endif; ?>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </main>

</div>

</body>
</html>