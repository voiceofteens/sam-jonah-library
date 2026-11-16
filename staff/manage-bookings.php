
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('staff');

date_default_timezone_set('Africa/Accra');

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

// Read search and filter values
$search = trim($_GET['search'] ?? '');
$date = trim($_GET['date'] ?? '');
$status = trim($_GET['status'] ?? '');
$facilityId = trim($_GET['facility'] ?? '');

$allowedStatuses = [
    'confirmed',
    'cancelled',
    'completed',
    'no_show'
];

if (!in_array($status, $allowedStatuses, true)) {
    $status = '';
}

if ($date !== '') {
    $dateObject = DateTime::createFromFormat('!Y-m-d', $date);
    if (!$dateObject || $dateObject->format('Y-m-d') !== $date) {
        $date = '';
    }
}

if ($facilityId !== '' && !ctype_digit($facilityId)) {
    $facilityId = '';
}

// Load facilities for the filter
$facilityStmt = $conn->query("
    SELECT facility_id, facility_name
    FROM facilities
    ORDER BY facility_name
");
$facilities = $facilityStmt->fetchAll(PDO::FETCH_ASSOC);

// Build booking search
$sql = "
    SELECT
        b.booking_id,
        b.booking_type,
        b.group_size,
        b.start_at,
        b.end_at,
        b.booking_source,
        b.status,
        b.check_in_at,
        b.check_out_at,
        b.created_at,
        u.full_name,
        u.student_id,
        u.email,
        f.facility_name,
        f.facility_code
    FROM bookings b
    INNER JOIN users u ON b.user_id = u.user_id
    INNER JOIN facilities f ON b.facility_id = f.facility_id
    WHERE 1 = 1
";

$params = [];

if ($search !== '') {
    $sql .= "
        AND (
            u.full_name LIKE ?
            OR u.student_id LIKE ?
            OR u.email LIKE ?
            OR CAST(b.booking_id AS CHAR) LIKE ?
        )
    ";

    $term = '%' . $search . '%';

    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if ($date !== '') {
    $sql .= " AND DATE(b.start_at) = ?";
    $params[] = $date;
}

if ($status !== '') {
    $sql .= " AND b.status = ?";
    $params[] = $status;
}

if ($facilityId !== '') {
    $sql .= " AND b.facility_id = ?";
    $params[] = (int)$facilityId;
}

$sql .= " ORDER BY b.start_at DESC LIMIT 200";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings | Sam Jonah Library</title>
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
            <a href="manage-bookings.php" class="active">Manage Bookings</a>
            <a href="walk-in.php">Walk-in Bookings</a>
            <a href="facilities.php">Manage Facilities</a>
            <a href="notifications.php">Notifications</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">Logout</a>
        </div>

    </aside>

    <main class="main-content">

        <h1>Manage Bookings</h1>

        <p>Search, filter and monitor library facility reservations.</p>

        <section class="dashboard-section">

            <h2>Search and Filter</h2>

            <form method="GET" action="manage-bookings.php"
                  class="filter-form">

                <input
                    type="text"
                    name="search"
                    placeholder="Student name, ID or booking reference"
                    value="<?= e($search) ?>"
                >

                <input
                    type="date"
                    name="date"
                    value="<?= e($date) ?>"
                >

                <select name="status">
                    <option value="">All statuses</option>

                    <?php foreach ($allowedStatuses as $item): ?>
                        <option value="<?= e($item) ?>"
                            <?= $status === $item ? 'selected' : '' ?>>
                            <?= e(ucwords(str_replace('_', ' ', $item))) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="facility">
                    <option value="">All facilities</option>

                    <?php foreach ($facilities as $facility): ?>
                        <option
                            value="<?= (int)$facility['facility_id'] ?>"
                            <?= $facilityId == $facility['facility_id'] ? 'selected' : '' ?>
                        >
                            <?= e($facility['facility_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="btn-primary">
                    Search
                </button>

                <a href="manage-bookings.php" class="filter-reset">
                    Clear Filters
                </a>

            </form>

            <p class="form-help">
                Showing up to 200 matching bookings. Use the filters
                to narrow your results.
            </p>

        </section>

        <section class="dashboard-section">

            <h2>Booking Records (<?= count($bookings) ?>)</h2>

            <div class="table-container">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Facility</th>
                            <th>Booking Type</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Attendance</th>
                            <th>Status</th>
                            <th>Source</th>
                        </tr>
                    </thead>

                    <tbody>

                    <?php if ($bookings): ?>

                        <?php foreach ($bookings as $booking): ?>

                            <tr>

                                <td>
                                    #<?= (int)$booking['booking_id'] ?>
                                </td>

                                <td>
                                    <?= e($booking['full_name']) ?>
                                </td>

                                <td>
                                    <?= e($booking['student_id'] ?: 'Not recorded') ?>
                                </td>

                                <td>
                                    <?= e($booking['facility_name']) ?>
                                    <br>
                                    <small><?= e($booking['facility_code']) ?></small>
                                </td>

                                <td>
                                    <?= e(ucfirst($booking['booking_type'])) ?>

                                    <?php if ($booking['booking_type'] === 'group'): ?>
                                        <br>
                                        <small>
                                            Group size: <?= (int)$booking['group_size'] ?>
                                        </small>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?= date('d M Y', strtotime($booking['start_at'])) ?>
                                </td>

                                <td>
                                    <?= date('h:i A', strtotime($booking['start_at'])) ?>
                                    -
                                    <?= date('h:i A', strtotime($booking['end_at'])) ?>
                                </td>

                                <td>
                                    <?php if ($booking['check_out_at']): ?>
                                        <span class="attendance-complete">
                                            Checked out
                                        </span>

                                    <?php elseif ($booking['check_in_at']): ?>
                                        <span class="attendance-present">
                                            Checked in
                                        </span>

                                    <?php elseif ($booking['status'] === 'no_show'): ?>
                                        <span class="attendance-absent">
                                            No-show
                                        </span>

                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <span class="attendance-waiting">
                                            Not checked in
                                        </span>

                                    <?php else: ?>
                                        Not applicable
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="booking-status status-<?= e($booking['status']) ?>">
                                        <?= e(ucwords(str_replace('_', ' ', $booking['status']))) ?>
                                    </span>
                                </td>

                                <td>
                                    <?= e(ucwords(str_replace('_', ' ', $booking['booking_source']))) ?>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    <?php else: ?>

                        <tr>
                            <td colspan="10">
                                No bookings match your search.
                            </td>
                        </tr>

                    <?php endif; ?>

                    </tbody>

                </table>

            </div>

        </section>

    </main>

</div>

</body>
</html>
