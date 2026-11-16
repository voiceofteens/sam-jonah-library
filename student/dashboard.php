<?php

require_once '../includes/authentication.php';

requireRole('student');

require_once '../config/database.php';

// Get the logged-in student's ID
$userId = $_SESSION['user_id'];

// Count upcoming confirmed bookings
$sql = "SELECT COUNT(*)
        FROM bookings
        WHERE user_id = ?
        AND status = 'confirmed'
        AND start_at >= NOW()";

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);

$upcomingCount = $stmt->fetchColumn();


// Count completed bookings
$sql = "SELECT COUNT(*)
        FROM bookings
        WHERE user_id = ?
        AND status = 'completed'";

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);

$completedCount = $stmt->fetchColumn();


// Count unread notifications
$sql = "SELECT COUNT(*)
        FROM notifications
        WHERE user_id = ?
        AND is_read = 0";

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);

$notificationCount = $stmt->fetchColumn();


// Get the student's next five bookings
$sql = "SELECT
            b.start_at,
            b.end_at,
            b.status,
            f.facility_name,
            f.facility_code

        FROM bookings b

        INNER JOIN facilities f
            ON b.facility_id = f.facility_id

        WHERE b.user_id = ?
        AND b.status = 'confirmed'
        AND b.start_at >= NOW()

        ORDER BY b.start_at ASC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->execute([$userId]);

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | Sam Jonah Library</title>
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>
<body>
<div class="dashboard-layout">
    <?php include '../includes/student-nav.php'; ?>

    <main class="main-content">
        <div class="topbar">
            <h1>Student Dashboard</h1>
            <p><?= htmlspecialchars($_SESSION['full_name']) ?></p>
        </div>

        <section class="welcome-section">
            <span class="eyebrow">Your study space, made simple</span>
            <h2>Welcome back, <?= htmlspecialchars(explode(' ', trim($_SESSION['full_name']))[0]) ?>!</h2>
            <p>Plan your next study session, keep track of your reservations and stay up to date.</p>
            <a href="book-facility.php" class="primary-button">Book a facility <span aria-hidden="true">→</span></a>
        </section>

        <section class="summary-grid" aria-label="Booking summary">
            <div class="summary-card">
                <p>Upcoming Bookings</p>
                <h2><?= (int) $upcomingCount ?><span class="card-note">Ready for your next visit</span></h2>
            </div>
            <div class="summary-card">
                <p>Completed Bookings</p>
                <h2><?= (int) $completedCount ?><span class="card-note">Your study sessions so far</span></h2>
            </div>
            <div class="summary-card">
                <p>Unread Notifications</p>
                <h2><?= (int) $notificationCount ?><span class="card-note">Updates waiting for you</span></h2>
            </div>
        </section>

        <div class="dashboard-content-grid">
            <section class="content-panel">
                <div class="panel-heading">
                    <h2>Upcoming bookings</h2>
                    <a href="my-bookings.php">View all →</a>
                </div>

                <?php if (count($bookings) > 0): ?>
                    <div class="booking-list">
                        <?php foreach ($bookings as $booking): ?>
                            <article class="booking-card">
                                <div class="booking-card-header">
                                    <h2><?= htmlspecialchars($booking['facility_name']) ?></h2>
                                    <span class="booking-status status-confirmed">Confirmed</span>
                                </div>
                                <p><strong>Facility code:</strong> <?= htmlspecialchars($booking['facility_code'] ?? '—') ?></p>
                                <p><strong>Date:</strong> <?= date('D, d M Y', strtotime($booking['start_at'])) ?></p>
                                <p><strong>Time:</strong> <?= date('g:i A', strtotime($booking['start_at'])) ?> – <?= date('g:i A', strtotime($booking['end_at'])) ?></p>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-message">
                        <div style="font-size:30px;margin-bottom:8px" aria-hidden="true">▦</div>
                        <strong style="display:block;color:#19184f;margin-bottom:4px">Your schedule is clear</strong>
                        You don't have any upcoming bookings yet. Find a space that works for you.
                        <div style="margin-top:15px"><a href="book-facility.php" class="btn-primary">Find a facility →</a></div>
                    </div>
                <?php endif; ?>
            </section>

            <aside class="content-panel">
                <div class="panel-heading"><h2>Quick actions</h2></div>
                <div class="quick-actions">
                    <a class="quick-action-card" href="book-facility.php"><span><strong>Book a facility</strong><small>Reserve your study space</small></span></a>
                    <a class="quick-action-card" href="my-bookings.php"><span><strong>My bookings</strong><small>Manage your reservations</small></span></a>
                    <a class="quick-action-card" href="notifications.php"><span><strong>Notifications</strong><small><?= (int) $notificationCount ?> unread updates</small></span></a>
                    <a class="quick-action-card" href="profile.php"><span><strong>My profile</strong><small>View your account details</small></span></a>
                </div>
            </aside>
        </div>
    </main>
</div>
</body>
</html>
