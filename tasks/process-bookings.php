<?php
date_default_timezone_set('Africa/Accra');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('staff');

$today = date('Y-m-d');
$userId = (int) $_SESSION['user_id'];

// Today's confirmed bookings
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE DATE(start_at) = ?
      AND status = 'confirmed'
");
$stmt->execute([$today]);
$todayBookings = (int) $stmt->fetchColumn();

// Walk-in bookings recorded today
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE DATE(created_at) = ?
      AND booking_source = 'walk_in'
");
$stmt->execute([$today]);
$walkInBookings = (int) $stmt->fetchColumn();

// Active facilities
$stmt = $conn->query("
    SELECT COUNT(*)
    FROM facilities
    WHERE is_active = 1
");
$activeFacilities = (int) $stmt->fetchColumn();

// Currently active bookings: checked in and not yet ended
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

// Students currently within their check-in grace period
$stmt = $conn->query("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'confirmed'
      AND check_in_at IS NULL
      AND start_at <= NOW()
      AND start_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$awaitingCheckIn = (int) $stmt->fetchColumn();

// No-shows today
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'no_show'
      AND DATE(start_at) = ?
");
$stmt->execute([$today]);
$noShows = (int) $stmt->fetchColumn();

// Missed checkouts today
// The automation marks these bookings completed without a checkout timestamp.
$stmt = $conn->prepare("
    SELECT COUNT(*)
    FROM bookings
    WHERE status = 'completed'
      AND check_in_at IS NOT NULL
      AND check_out_at IS NULL
      AND DATE(end_at) = ?
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
$stmt->execute([$userId]);
$unreadAlerts = (int) $stmt->fetchColumn();

// Today's booking schedule
$stmt = $conn->prepare("
    SELECT
        b.booking_id,
        b.booking_type,
        b.group_size,
        b.start_at,
        b.end_at,
        b.status,
        b.booking_source,
        b.check_in_at,
        b.check_out_at,
        u.full_name,
        u.student_id,
        f.facility_name,
        f.facility_code
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN facilities f ON b.facility_id = f.facility_id
    WHERE DATE(b.start_at) = ?
    ORDER BY b.start_at ASC
    LIMIT 20
");
$stmt->execute([$today]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Recent unread notifications for this staff account
$stmt = $conn->prepare("
    SELECT
        notification_id,
        booking_id,
        message,
        notification_type,
        sent_at
    FROM notifications
    WHERE user_id = ?
      AND is_read = 0
    ORDER BY sent_at DESC, notification_id DESC
    LIMIT 8
");
$stmt->execute([$userId]);
$alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

function getAttendanceLabel($booking)
{
    if ($booking['status'] === 'cancelled') {
        return 'Cancelled';
    }

    if ($booking['status'] === 'no_show') {
        return 'No-show';
    }

    if ($booking['check_out_at'] !== null) {
        return 'Checked out';
    }

    if (
        $booking['status'] === 'completed' &&
        $booking['check_in_at'] !== null
    ) {
        return 'Missed checkout';
    }

    if ($booking['check_in_at'] !== null) {
        return 'Checked in';
    }

    if (strtotime($booking['start_at']) > time()) {
        return 'Not started';
    }

    return 'Not checked in';
}

function getAttendanceClass($label)
{
    $classes = [
        'Checked in' => 'attendance-success',
        'Checked out' => 'attendance-success',
        'Not started' => 'attendance-neutral',
        'Not checked in' => 'attendance-warning',
        'No-show' => 'attendance-danger',
        'Missed checkout' => 'attendance-danger',
        'Cancelled' => 'attendance-neutral'
    ];

    return $classes[$label] ?? 'attendance-neutral';
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Staff Dashboard | Sam Jonah Library</title>

    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>

<body>

    <div class="dashboard-layout">

        <!-- SIDEBAR -->
        <aside class="sidebar">

            <div class="sidebar-brand">
        <img src="../assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-sidebar">
                <h2>Sam Jonah</h2>
                <p>Staff Portal</p>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="manage-bookings.php">Manage Bookings</a>
                <a href="walk-in.php">Record Walk-in</a>
                <a href="facilities.php">Facility Availability</a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php">Sign Out</a>
            </div>

        </aside>

        <!-- MAIN CONTENT -->
        <main class="main-content">

            <div class="staff-dashboard-header">
                <div>
                    <h1>Staff Dashboard</h1>

                    <p>
                        Welcome,
                        <?= htmlspecialchars($_SESSION['full_name']) ?>.
                    </p>

                    <p class="dashboard-date">
                        <?= date('l, j F Y') ?>
                    </p>
                </div>

                <div class="alert-counter">
                    <span>Unread Alerts</span>
                    <strong><?= $unreadAlerts ?></strong>
                </div>
            </div>

            <!-- OVERVIEW CARDS -->
            <section class="dashboard-cards">

                <div class="dashboard-card">
                    <h3>Today's Confirmed Bookings</h3>
                    <p><?= $todayBookings ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Currently Active</h3>
                    <p><?= $activeBookings ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Upcoming Bookings</h3>
                    <p><?= $upcomingBookings ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Awaiting Check-in</h3>
                    <p><?= $awaitingCheckIn ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Walk-ins Today</h3>
                    <p><?= $walkInBookings ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Active Facilities</h3>
                    <p><?= $activeFacilities ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>No-shows Today</h3>
                    <p><?= $noShows ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Missed Checkouts Today</h3>
                    <p><?= $missedCheckouts ?></p>
                </div>

            </section>

            <!-- QUICK ACTIONS -->
            <section class="dashboard-section">

                <h2>Quick Actions</h2>

                <div class="dashboard-quick-links">

                    <a href="manage-bookings.php" class="primary-button">
                        Manage Bookings
                    </a>

                    <a href="walk-in.php" class="primary-button">
                        Record Walk-in
                    </a>

                    <a href="facilities.php" class="primary-button">
                        Manage Facilities
                    </a>

                </div>

            </section>

            <!-- STAFF ALERTS -->
            <section class="dashboard-section">

                <div class="section-heading">
                    <h2>Staff Alerts</h2>
                    <span><?= $unreadAlerts ?> unread</span>
                </div>

                <?php if (empty($alerts)): ?>

                    <p class="empty-message">
                        You have no unread alerts.
                    </p>

                <?php else: ?>

                    <div class="staff-alert-list">

                        <?php foreach ($alerts as $alert): ?>

                            <div class="staff-alert-item">

                                <div>
                                    <strong>
                                        <?= htmlspecialchars(
                                            ucwords(str_replace(
                                                '_',
                                                ' ',
                                                $alert['notification_type']
                                            ))
                                        ) ?>
                                    </strong>

                                    <p>
                                        <?= htmlspecialchars($alert['message']) ?>
                                    </p>

                                    <small>
                                        <?= $alert['sent_at']
                                            ? date(
                                                'j M Y, g:i A',
                                                strtotime($alert['sent_at'])
                                            )
                                            : 'Time unavailable'
                                        ?>
                                    </small>

                                </div>

                                <?php if ($alert['booking_id']): ?>
                                    <a href="manage-bookings.php">
                                        View bookings
                                    </a>
                                <?php endif; ?>

                            </div>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            </section>

            <!-- TODAY'S BOOKING SCHEDULE -->
            <section class="dashboard-section">

                <h2>Today's Booking Schedule</h2>

                <?php if (empty($bookings)): ?>

                    <p class="empty-message">
                        There are no bookings scheduled for today.
                    </p>

                <?php else: ?>

                    <div class="table-container">

                        <table class="data-table">

                            <thead>
                                <tr>
                                    <th>Reference</th>
                                    <th>Student</th>
                                    <th>Facility</th>
                                    <th>Booking Type</th>
                                    <th>Time</th>
                                    <th>Source</th>
                                    <th>Status</th>
                                    <th>Attendance</th>
                                </tr>
                            </thead>

                            <tbody>

                                <?php foreach ($bookings as $booking): ?>

                                    <?php
                                    $attendance = getAttendanceLabel($booking);
                                    $attendanceClass = getAttendanceClass($attendance);
                                    ?>

                                    <tr>

                                        <td>
                                            #<?= (int) $booking['booking_id'] ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($booking['full_name']) ?>
                                            <br>
                                            <small>
                                                <?= htmlspecialchars(
                                                    $booking['student_id'] ?? ''
                                                ) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($booking['facility_name']) ?>
                                            <br>
                                            <small>
                                                <?= htmlspecialchars($booking['facility_code']) ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                ucwords(str_replace(
                                                    '_',
                                                    ' ',
                                                    $booking['booking_type']
                                                ))
                                            ) ?>
                                            (<?= (int) $booking['group_size'] ?>)
                                        </td>

                                        <td>
                                            <?= date(
                                                'g:i A',
                                                strtotime($booking['start_at'])
                                            ) ?>
                                            -
                                            <?= date(
                                                'g:i A',
                                                strtotime($booking['end_at'])
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                ucwords(str_replace(
                                                    '_',
                                                    ' ',
                                                    $booking['booking_source']
                                                ))
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(
                                                ucwords(str_replace(
                                                    '_',
                                                    ' ',
                                                    $booking['status']
                                                ))
                                            ) ?>
                                        </td>

                                        <td>
                                            <span class="attendance-badge <?= $attendanceClass ?>">
                                                <?= htmlspecialchars($attendance) ?>
                                            </span>
                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                    <a href="manage-bookings.php" class="primary-button">
                        View All Bookings
                    </a>

                <?php endif; ?>

            </section>

        </main>

    </div>

</body>

</html>