<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('admin');

$statistics = [];

$queries = [
    'students' => "SELECT COUNT(*) FROM users WHERE role = 'student'",
    'staff' => "SELECT COUNT(*) FROM users WHERE role = 'staff'",
    'facilities' => "SELECT COUNT(*) FROM facilities WHERE is_active = 1",
    'bookings' => "SELECT COUNT(*) FROM bookings",
    'confirmed' => "SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'",
    'cancelled' => "SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'"
];

foreach ($queries as $key => $sql) {
    $statistics[$key] = (int) $conn->query($sql)->fetchColumn();
}

$stmt = $conn->query("
    SELECT b.booking_id, b.start_at, b.end_at,
           b.status, b.booking_source,
           u.full_name, u.student_id,
           f.facility_name
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN facilities f ON b.facility_id = f.facility_id
    ORDER BY b.created_at DESC
    LIMIT 10
");

$recentBookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard | Sam Jonah Library</title>
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
                <a href="dashboard.php" class="active">Dashboard</a>
                <a href="users.php">Manage Accounts</a>
                <a href="facilities.php">Manage Facilities</a>
                <a href="operating-hours.php">Operating Hours</a>
                <a href="bookings.php">All Bookings</a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php">Sign Out</a>
            </div>
        </aside>

        <main class="main-content">

            <section class="welcome-section role-welcome admin-welcome">
                <span class="eyebrow">LIBRARY OPERATIONS, AT A GLANCE</span>
                <h2>Welcome back, <?= htmlspecialchars($_SESSION['full_name']) ?>!</h2>
                <p>Oversee the Sam Jonah Library, manage facilities and accounts, and keep every booking running smoothly.</p>
                <div class="role-welcome-meta">
                    <span><?= date('l, j F Y') ?></span>
                    <span class="role-welcome-tag">Administrator workspace</span>
                </div>
                <div class="role-welcome-art" aria-hidden="true"><span class="art-ring"></span><span class="art-orbit">✦</span><span class="art-symbol">▤</span></div>
            </section>

            <div class="dashboard-cards">

                <div class="dashboard-card">
                    <h3>Registered Students</h3>
                    <p><?= $statistics['students'] ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Staff Accounts</h3>
                    <p><?= $statistics['staff'] ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Active Facilities</h3>
                    <p><?= $statistics['facilities'] ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Total Bookings</h3>
                    <p><?= $statistics['bookings'] ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Confirmed Bookings</h3>
                    <p><?= $statistics['confirmed'] ?></p>
                </div>

                <div class="dashboard-card">
                    <h3>Cancelled Bookings</h3>
                    <p><?= $statistics['cancelled'] ?></p>
                </div>

            </div>

            <section class="dashboard-section">

                <h2>Recently Created Bookings</h2>

                <div class="table-container">

                    <table class="data-table">

                        <thead>
                            <tr>
                                <th>Reference</th>
                                <th>Student</th>
                                <th>Facility</th>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Source</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php if (empty($recentBookings)): ?>

                                <tr>
                                    <td colspan="7">No bookings have been recorded.</td>
                                </tr>

                            <?php else: ?>

                                <?php foreach ($recentBookings as $booking): ?>

                                    <tr>
                                        <td><?= (int) $booking['booking_id'] ?></td>

                                        <td>
                                            <?= htmlspecialchars($booking['full_name']) ?>
                                            <br>
                                            <small>
                                                <?= htmlspecialchars($booking['student_id'] ?? '') ?>
                                            </small>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars($booking['facility_name']) ?>
                                        </td>

                                        <td>
                                            <?= date('d M Y', strtotime($booking['start_at'])) ?>
                                        </td>

                                        <td>
                                            <?= date('g:i A', strtotime($booking['start_at'])) ?>
                                            -
                                            <?= date('g:i A', strtotime($booking['end_at'])) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $booking['booking_source']))) ?>
                                        </td>

                                        <td>
                                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $booking['status']))) ?>
                                        </td>
                                    </tr>

                                <?php endforeach; ?>

                            <?php endif; ?>

                        </tbody>

                    </table>

                </div>

            </section>

        </main>

    </div>

</body>

</html>