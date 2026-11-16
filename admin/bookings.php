<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
    if (verifyCsrfToken($_POST['csrf_token'] ?? '') && $id) {
        $conn->beginTransaction();
        $u = $conn->prepare("UPDATE bookings SET status = 'cancelled' WHERE booking_id = ? AND status = 'confirmed' AND check_in_at IS NULL");
        $u->execute([$id]);
        if ($u->rowCount()) {
            $conn->prepare("INSERT INTO booking_audit_logs (booking_id, actor_user_id, action, description) VALUES (?, ?, 'booking_cancelled', 'Cancelled by administrator.')")
                 ->execute([$id, $_SESSION['user_id']]);
            $conn->prepare("INSERT INTO notifications (user_id, booking_id, message, notification_type, sent_at) SELECT user_id, booking_id, CONCAT('Booking #', booking_id, ' was cancelled by the library administration.'), 'cancellation', NOW() FROM bookings WHERE booking_id = ?")
                 ->execute([$id]);
        }
        $conn->commit();
    }
    header('Location: bookings.php?status=' . urlencode($_GET['status'] ?? 'all'));
    exit();
}

$allowedStatuses = [
    'all',
    'confirmed',
    'cancelled',
    'completed',
    'no_show'
];

$status = $_GET['status'] ?? 'all';

if (!in_array($status, $allowedStatuses, true)) {
    $status = 'all';
}

$sql = "
    SELECT b.booking_id, b.booking_type, b.group_size,
           b.start_at, b.end_at, b.status,
           b.booking_source, b.check_in_at, b.check_out_at,
           u.full_name, u.student_id,
           f.facility_name, f.facility_code
    FROM bookings b
    JOIN users u ON b.user_id = u.user_id
    JOIN facilities f ON b.facility_id = f.facility_id
";

if ($status !== 'all') {
    $sql .= " WHERE b.status = :status";
}

$sql .= " ORDER BY b.start_at DESC";

$stmt = $conn->prepare($sql);

if ($status !== 'all') {
    $stmt->execute(['status' => $status]);
} else {
    $stmt->execute();
}

$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings | Sam Jonah Library</title>
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
                <a href="users.php">Manage Accounts</a>
                <a href="facilities.php">Manage Facilities</a>
                <a href="operating-hours.php">Operating Hours</a>
                <a href="bookings.php" class="active">All Bookings</a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php">Sign Out</a>
            </div>
        </aside>

        <main class="main-content">

            <h1>All Bookings</h1>
            <p>View booking records across all library facilities.</p>

            <form method="GET" class="filter-form">

                <label for="status">Booking Status</label>

                <select name="status" id="status">
                    <?php foreach ($allowedStatuses as $option): ?>
                        <option value="<?= htmlspecialchars($option) ?>"
                            <?= $status === $option ? 'selected' : '' ?>>
                            <?= htmlspecialchars(ucwords(str_replace('_', ' ', $option))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary">
                    Filter Bookings
                </button>

            </form>

            <div class="table-container">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Student</th>
                            <th>Facility</th>
                            <th>Booking Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Source</th>
                            <th>Check-in</th>
                            <th>Check-out</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php if (empty($bookings)): ?>

                            <tr>
                                <td colspan="10">No bookings found.</td>
                            </tr>

                        <?php else: ?>

                            <?php foreach ($bookings as $booking): ?>

                                <tr>
                                    <td>
                                        <?= (int) $booking['booking_id'] ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars($booking['full_name']) ?>
                                        <br>
                                        <small>
                                            <?= htmlspecialchars($booking['student_id'] ?? '') ?>
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
                                            ucwords(str_replace('_', ' ', $booking['booking_type']))
                                        ) ?>
                                        (<?= (int) $booking['group_size'] ?>)
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
                                        <?= htmlspecialchars(
                                            ucwords(str_replace('_', ' ', $booking['booking_source']))
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= $booking['check_in_at']
                                            ? date('g:i A', strtotime($booking['check_in_at']))
                                            : 'Not checked in' ?>
                                    </td>

                                    <td>
                                        <?= $booking['check_out_at']
                                            ? date('g:i A', strtotime($booking['check_out_at']))
                                            : 'Not checked out' ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            ucwords(str_replace('_', ' ', $booking['status']))
                                        ) ?>
                                    </td>
                                    <td>
                                        <?php if ($booking['status'] === 'confirmed' && $booking['check_in_at'] === null): ?>
                                            <form method="POST" onsubmit="return confirm('Cancel this booking?');">
                                                <?= csrfField() ?>
                                                <input type="hidden" name="booking_id" value="<?= (int) $booking['booking_id'] ?>">
                                                <button type="submit" class="btn-danger">Cancel</button>
                                            </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </main>

    </div>

</body>

</html>