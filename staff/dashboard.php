
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('staff');

date_default_timezone_set('Africa/Accra');

$today = date('Y-m-d');

// Today's total bookings
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE DATE(start_at) = ?
");
$stmt->execute([$today]);
$totalBookings = (int) $stmt->fetchColumn();

// Currently active bookings
$stmt = $conn->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'confirmed'
      AND check_in_at IS NOT NULL
      AND check_out_at IS NULL
      AND start_at <= NOW()
      AND end_at > NOW()
");
$activeBookings = (int) $stmt->fetchColumn();

// Upcoming confirmed bookings
$stmt = $conn->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'confirmed'
      AND start_at > NOW()
");
$upcomingBookings = (int) $stmt->fetchColumn();

// Today's no-shows
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'no_show'
      AND DATE(start_at) = ?
");
$stmt->execute([$today]);
$noShowBookings = (int) $stmt->fetchColumn();

// Missed checkout records today
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM booking_audit_logs
    WHERE action = 'missed_checkout'
      AND DATE(created_at) = ?
");
$stmt->execute([$today]);
$missedCheckouts = (int) $stmt->fetchColumn();

// Unread staff notifications
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
");
$stmt->execute([$_SESSION['user_id']]);
$unreadNotifications = (int) $stmt->fetchColumn();

// Recent bookings
$stmt = $conn->query("
    SELECT
        b.booking_id,
        b.booking_type,
        b.group_size,
        b.start_at,
        b.end_at,
        b.status,
        b.check_in_at,
        b.check_out_at,
        u.full_name,
        u.student_id,
        f.facility_name
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN facilities f ON b.facility_id = f.facility_id
    ORDER BY b.created_at DESC
    LIMIT 10
");
$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard | Sam Jonah Library</title>
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
            <a href="dashboard.php" class="active">Dashboard</a>
            <a href="manage-bookings.php">Manage Bookings</a>
            <a href="walk-in.php">Walk-in Bookings</a>
            <a href="facilities.php">Manage Facilities</a>
            <a href="notifications.php">Notifications
                <?php if ($unreadNotifications > 0): ?>
                    (<?= $unreadNotifications ?>)
                <?php endif; ?>
            </a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">Logout</a>
        </div>
    </aside>

    <main class="main-content">

        <section class="welcome-section role-welcome staff-welcome">
            <span class="eyebrow">YOUR LIBRARY SHIFT, MADE SIMPLE</span>
            <h2>Welcome back, <?= e($_SESSION['full_name']) ?>!</h2>
            <p>Stay on top of today's reservations, support students and keep library operations moving smoothly.</p>
            <div class="role-welcome-meta">
                <span><?= date('l, j F Y') ?></span>
                <span class="role-welcome-tag">Staff workspace</span>
            </div>
            <div class="role-welcome-art" aria-hidden="true"><span class="art-ring"></span><span class="art-orbit">✦</span><span class="art-symbol">▣</span></div>
        </section>

        <section class="dashboard-cards">

            <div class="dashboard-card">
                <h3>Today's Bookings</h3>
                <p><?= $totalBookings ?></p>
            </div>

            <div class="dashboard-card">
                <h3>Active Bookings</h3>
                <p><?= $activeBookings ?></p>
            </div>

            <div class="dashboard-card">
                <h3>Upcoming Bookings</h3>
                <p><?= $upcomingBookings ?></p>
            </div>

            <div class="dashboard-card">
                <h3>No-Shows Today</h3>
                <p><?= $noShowBookings ?></p>
            </div>

            <div class="dashboard-card">
                <h3>Missed Checkouts Today</h3>
                <p><?= $missedCheckouts ?></p>
            </div>

            <div class="dashboard-card">
                <h3>Unread Notifications</h3>
                <p><?= $unreadNotifications ?></p>
            </div>

        </section>

        <section class="dashboard-section">

            <h2>Recent Bookings</h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Facility</th>
                            <th>Booking Time</th>
                            <th>Attendance</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if (count($recentBookings) > 0): ?>

                        <?php foreach ($recentBookings as $booking): ?>

                            <tr>
                                <td>#<?= e($booking['booking_id']) ?></td>

                                <td><?= e($booking['full_name']) ?></td>

                                <td><?= e($booking['student_id'] ?: 'Walk-in') ?></td>

                                <td><?= e($booking['facility_name']) ?></td>

                                <td>
                                    <?= date('d M, h:i A', strtotime($booking['start_at'])) ?>
                                    -
                                    <?= date('h:i A', strtotime($booking['end_at'])) ?>
                                </td>

                                <td>
                                    <?php if ($booking['check_out_at']): ?>
                                        Checked out
                                    <?php elseif ($booking['check_in_at']): ?>
                                        Checked in
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        Not checked in
                                    <?php else: ?>
                                        Not applicable
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="booking-status status-<?= e($booking['status']) ?>">
                                        <?= e(ucwords(str_replace('_', ' ', $booking['status']))) ?>
                                    </span>
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="7">No bookings have been recorded yet.</td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

            <a href="manage-bookings.php" class="btn-primary">
                View All Bookings
            </a>

        </section>

        <section class="staff-quick-actions">

            <h2>Quick Actions</h2>

            <div class="quick-action-grid">

                <a href="manage-bookings.php" class="quick-action-card">
                    <h3>Manage Bookings</h3>
                    <p>View and manage student reservations.</p>
                </a>

                <a href="walk-in.php" class="quick-action-card">
                    <h3>Record Walk-in</h3>
                    <p>Create a booking for a walk-in student.</p>
                </a>

                <a href="facilities.php" class="quick-action-card">
                    <h3>Facilities</h3>
                    <p>Manage facility availability.</p>
                </a>

                <a href="notifications.php" class="quick-action-card">
                    <h3>Notifications</h3>
                    <p>View staff alerts and notifications.</p>
                </a>

            </div>

        </section>

    </main>

</div>

</body>
</html>
