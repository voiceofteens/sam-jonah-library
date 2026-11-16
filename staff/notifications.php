
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('staff');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $error = 'Invalid request. Please refresh the page and try again.';
    } else {

        $action = $_POST['action'] ?? '';

        if ($action === 'mark_read') {

            $notificationId = filter_input(
                INPUT_POST,
                'notification_id',
                FILTER_VALIDATE_INT
            );

            if ($notificationId) {
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

        } elseif ($action === 'mark_all_read') {

            $stmt = $conn->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE user_id = ?
                  AND is_read = 0
            ");

            $stmt->execute([$_SESSION['user_id']]);

        }

        header("Location: notifications.php");
        exit();
    }
}

// Get staff notifications
$stmt = $conn->prepare("
    SELECT
        notification_id,
        booking_id,
        message,
        notification_type,
        sent_at,
        scheduled_at,
        is_read
    FROM notifications
    WHERE user_id = ?
    ORDER BY notification_id DESC
    LIMIT 100
");

$stmt->execute([$_SESSION['user_id']]);
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count unread notifications
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");

$stmt->execute([$_SESSION['user_id']]);
$unreadCount = (int)$stmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Notifications | Sam Jonah Library</title>

    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>

<body>

<div class="dashboard-layout">

    <aside class="sidebar">

        <div class="sidebar-brand">
        <img src="../assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-sidebar">
            <h2>Sam Jonah Library</h2>
            <p>Staff Portal</p>
        </div>

        <nav class="sidebar-nav">
            <a href="dashboard.php">Dashboard</a>
            <a href="manage-bookings.php">Manage Bookings</a>
            <a href="walk-in.php">Walk-in Bookings</a>
            <a href="facilities.php">Manage Facilities</a>
            <a href="notifications.php" class="active">Notifications</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">Logout</a>
        </div>

    </aside>

    <main class="main-content">

        <h1>Staff Notifications</h1>

        <p>
            Booking alerts and important library updates.
        </p>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <section class="dashboard-section">

            <h2>Unread Notifications: <?= $unreadCount ?></h2>

            <?php if ($unreadCount > 0): ?>

                <form method="POST" action="notifications.php"
                      class="mark-all-form">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <input
                        type="hidden"
                        name="action"
                        value="mark_all_read"
                    >

                    <button type="submit" class="btn-primary">
                        Mark All as Read
                    </button>

                </form>

            <?php endif; ?>

            <div class="staff-notification-list">

                <?php if ($notifications): ?>

                    <?php foreach ($notifications as $notification): ?>

                        <article class="notification-item
                            <?= !$notification['is_read'] ? 'unread-notification' : '' ?>">

                            <div class="notification-heading">

                                <strong>
                                    <?= e(ucwords(str_replace(
                                        '_',
                                        ' ',
                                        $notification['notification_type']
                                    ))) ?>
                                </strong>

                                <span>
                                    <?= $notification['sent_at']
                                        ? date('d M Y, h:i A', strtotime($notification['sent_at']))
                                        : 'Not sent yet' ?>
                                </span>

                            </div>

                            <p><?= e($notification['message']) ?></p>

                            <?php if ($notification['booking_id']): ?>
                                <p class="form-help">
                                    Booking reference:
                                    #<?= (int)$notification['booking_id'] ?>
                                </p>
                            <?php endif; ?>

                            <?php if (!$notification['is_read']): ?>

                                <form method="POST" action="notifications.php">

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="mark_read"
                                    >

                                    <input
                                        type="hidden"
                                        name="notification_id"
                                        value="<?= (int)$notification['notification_id'] ?>"
                                    >

                                    <button type="submit" class="btn-primary">
                                        Mark as Read
                                    </button>

                                </form>

                            <?php else: ?>

                                <span class="notification-read-label">
                                    Read
                                </span>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                <?php else: ?>

                    <p>You don't have any notifications yet.</p>

                <?php endif; ?>

            </div>

        </section>

    </main>

</div>

</body>
</html>
