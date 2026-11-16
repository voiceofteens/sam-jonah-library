
<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';
require_once __DIR__ . '/../config/settings.php';

requireRole('staff');

date_default_timezone_set('Africa/Accra');

$conn->exec("SET time_zone = '+00:00'");

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = '';

if (isset($_GET['success']) && $_GET['success'] === '1') {
    $success = 'Walk-in booking recorded successfully.';
}

// Load active facilities
$stmt = $conn->query("
    SELECT facility_id, facility_name, facility_type, capacity
    FROM facilities
    WHERE is_active = 1
    ORDER BY facility_name
");

$facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    try {
        if (!hash_equals(
            $_SESSION['csrf_token'],
            $_POST['csrf_token'] ?? ''
        )) {
            throw new Exception('Invalid request. Refresh the page and try again.');
        }

        $studentId = trim($_POST['student_id'] ?? '');
        $facilityId = filter_input(
            INPUT_POST,
            'facility_id',
            FILTER_VALIDATE_INT
        );
        $bookingType = $_POST['booking_type'] ?? '';
        $groupSize = filter_input(
            INPUT_POST,
            'group_size',
            FILTER_VALIDATE_INT
        );
        $bookingDate = $_POST['booking_date'] ?? '';
        $startTime = $_POST['start_time'] ?? '';

        if (
            $studentId === '' ||
            !$facilityId ||
            !in_array($bookingType, ['individual', 'group'], true) ||
            $bookingDate === '' ||
            $startTime === ''
        ) {
            throw new Exception('Please complete all required fields.');
        }

        // Walk-in bookings are for today only.
        $today = date('Y-m-d');

        if ($bookingDate !== $today) {
            throw new Exception('Walk-in bookings can only be recorded for today.');
        }

        $dateTime = DateTime::createFromFormat(
            '!Y-m-d H:i',
            $bookingDate . ' ' . $startTime
        );

        if (
            !$dateTime ||
            $dateTime->format('Y-m-d H:i') !==
            $bookingDate . ' ' . $startTime
        ) {
            throw new Exception('Invalid booking date or start time.');
        }

        $start = $dateTime;
        $end = (clone $start)->modify('+' . BOOKING_DURATION . ' minutes');

        if ($start < (new DateTime())->modify('-' . CHECK_IN_GRACE_PERIOD . ' minutes')) {
            throw new Exception('The start time cannot be more than ' . CHECK_IN_GRACE_PERIOD . ' minutes in the past.');
        }

        if ($bookingType === 'individual') {
            $groupSize = 1;
        } else {
            if (!$groupSize || $groupSize < 2) {
                throw new Exception('A group booking must have at least 2 people.');
            }
        }

        // Find the student.
        $studentStmt = $conn->prepare("
            SELECT user_id, full_name
            FROM users
            WHERE student_id = ?
              AND role = 'student'
            LIMIT 1
        ");

        $studentStmt->execute([$studentId]);
        $student = $studentStmt->fetch(PDO::FETCH_ASSOC);

        if (!$student) {
            throw new Exception('No student account was found with that Student ID.');
        }

        $conn->beginTransaction();

        // Lock the facility to prevent simultaneous conflicting bookings.
        $facilityStmt = $conn->prepare("
            SELECT *
            FROM facilities
            WHERE facility_id = ?
              AND is_active = 1
            FOR UPDATE
        ");

        $facilityStmt->execute([$facilityId]);
        $facility = $facilityStmt->fetch(PDO::FETCH_ASSOC);

        if (!$facility) {
            throw new Exception('The selected facility is unavailable.');
        }

        if (
            $facility['facility_type'] === 'seat' &&
            $bookingType === 'group'
        ) {
            throw new Exception('Study seats can only be booked individually.');
        }

        if ($groupSize > (int)$facility['capacity']) {
            throw new Exception('The group size exceeds this facility capacity.');
        }

        // Check the facility's operating hours.
        // MySQL DAYOFWEEK: Sunday = 1, Monday = 2, ..., Saturday = 7.
        $dayOfWeek = (int)$start->format('w') + 1;

        $hoursStmt = $conn->prepare("
            SELECT opening_time, closing_time, is_closed
            FROM operating_hours
            WHERE day_of_week = ?
        ");

        $hoursStmt->execute([$dayOfWeek]);
        $hours = $hoursStmt->fetch(PDO::FETCH_ASSOC);

        if (!$hours || (int)$hours['is_closed'] === 1) {
            throw new Exception('The library is closed on this day.');
        }

        $opening = new DateTime($bookingDate . ' ' . $hours['opening_time']);
        $closing = new DateTime($bookingDate . ' ' . $hours['closing_time']);

        if ($start < $opening || $end > $closing) {
            throw new Exception('The two-hour booking must fit within library opening hours.');
        }

        // Enforce the facility's buffer.
        $bufferMinutes = (int)$facility['buffer_minutes'];

        $bufferedStart = (clone $start)
            ->modify("-{$bufferMinutes} minutes");

        $bufferedEnd = (clone $end)
            ->modify("+{$bufferMinutes} minutes");

        $overlapStmt = $conn->prepare("
            SELECT booking_id
            FROM bookings
            WHERE facility_id = ?
              AND status = 'confirmed'
              AND start_at < ?
              AND end_at > ?
            LIMIT 1
        ");

        $overlapStmt->execute([
            $facilityId,
            $bufferedEnd->format('Y-m-d H:i:s'),
            $bufferedStart->format('Y-m-d H:i:s')
        ]);

        if ($overlapStmt->fetch()) {
            throw new Exception(
                'This facility is already booked during that time or its required buffer period.'
            );
        }

        // Per-student rules: no overlapping bookings and a cap on upcoming bookings.
        $overlap = $conn->prepare("SELECT booking_id FROM bookings WHERE user_id = ? AND status = 'confirmed' AND start_at < ? AND end_at > ? LIMIT 1");
        $overlap->execute([$student['user_id'], $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
        if ($overlap->fetch()) {
            throw new Exception('This student already has a booking that overlaps with that time.');
        }
        $active = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed' AND end_at > NOW()");
        $active->execute([$student['user_id']]);
        if ((int) $active->fetchColumn() >= MAX_ACTIVE_BOOKINGS) {
            throw new Exception('A student can hold at most ' . MAX_ACTIVE_BOOKINGS . ' upcoming bookings.');
        }

        // Record the walk-in booking.
        $insertStmt = $conn->prepare("
            INSERT INTO bookings (
                user_id,
                facility_id,
                booking_type,
                group_size,
                time_method,
                start_at,
                end_at,
                booking_source,
                status
            )
            VALUES (?, ?, ?, ?, 'custom', ?, ?, 'walk_in', 'confirmed')
        ");

        $insertStmt->execute([
            $student['user_id'],
            $facilityId,
            $bookingType,
            $groupSize,
            $start->format('Y-m-d H:i:s'),
            $end->format('Y-m-d H:i:s')
        ]);

        $bookingId = (int)$conn->lastInsertId();

        // Record the audit trail.
        $auditStmt = $conn->prepare("
            INSERT INTO booking_audit_logs (
                booking_id,
                actor_user_id,
                action,
                description
            )
            VALUES (?, ?, 'walk_in_booking_created', ?)
        ");

        $auditStmt->execute([
            $bookingId,
            $_SESSION['user_id'],
            'Staff recorded a walk-in booking for ' .
            $student['full_name'] .
            ' using Student ID ' . $studentId . '.'
        ]);

        // Notify the student.
        $notificationStmt = $conn->prepare("
            INSERT INTO notifications (
                user_id,
                booking_id,
                message,
                notification_type,
                sent_at
            )
            VALUES (?, ?, ?, 'confirmation', NOW())
        ");

        $notificationStmt->execute([
            $student['user_id'],
            $bookingId,
            'Your walk-in booking has been confirmed. Booking reference: #' .
            $bookingId
        ]);

        $conn->commit();

        header("Location: walk-in.php?success=1");
        exit();

    } catch (PDOException $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log('Walk-in booking database error: ' . $e->getMessage());
        $error = 'A database error occurred. Please try again.';

    } catch (Exception $e) {

        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Walk-in Booking | Sam Jonah Library</title>

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
            <a href="walk-in.php" class="active">Walk-in Bookings</a>
            <a href="facilities.php">Manage Facilities</a>
            <a href="notifications.php">Notifications</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">Logout</a>
        </div>

    </aside>

    <main class="main-content">

        <h1>Record Walk-in Booking</h1>

        <p>
            Create a facility booking for a student visiting the library.
        </p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= e($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= e($error) ?>
            </div>
        <?php endif; ?>

        <section class="dashboard-section">

            <form method="POST" action="walk-in.php" class="booking-form">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <label for="student_id">Student ID</label>

                <input
                    type="text"
                    id="student_id"
                    name="student_id"
                    placeholder="Enter student's UCC Student ID"
                    required
                >

                <label for="facility_id">Select Facility</label>

                <select id="facility_id" name="facility_id" required>
                    <option value="">Choose a facility</option>

                    <?php foreach ($facilities as $facility): ?>

                        <option
                            value="<?= (int)$facility['facility_id'] ?>"
                            data-type="<?= e($facility['facility_type']) ?>"
                            data-capacity="<?= (int)$facility['capacity'] ?>"
                        >
                            <?= e($facility['facility_name']) ?>
                            (Capacity: <?= (int)$facility['capacity'] ?>)
                        </option>

                    <?php endforeach; ?>
                </select>

                <label for="booking_type">Booking Type</label>

                <select id="booking_type" name="booking_type" required>
                    <option value="individual">Individual</option>
                    <option value="group">Group</option>
                </select>

                <div id="group_size_container" hidden>

                    <label for="group_size">Group Size</label>

                    <input
                        type="number"
                        id="group_size"
                        name="group_size"
                        min="2"
                        value="2"
                    >

                </div>

                <label for="booking_date">Booking Date</label>

                <input
                    type="date"
                    id="booking_date"
                    name="booking_date"
                    value="<?= date('Y-m-d') ?>"
                    min="<?= date('Y-m-d') ?>"
                    max="<?= date('Y-m-d') ?>"
                    required
                >

                <label for="start_time">Starting Time</label>

                <input
                    type="time"
                    id="start_time"
                    name="start_time"
                    min="09:00"
                    max="19:00"
                    required
                >

                <label for="end_time">Ending Time</label>

                <input
                    type="text"
                    id="end_time"
                    readonly
                    placeholder="Automatically calculated"
                >

                <p class="form-help">
                    Walk-in bookings last exactly two hours.
                    The system automatically calculates the ending time
                    and applies the facility's buffer period.
                </p>

                <button type="submit" class="btn-primary">
                    Confirm Walk-in Booking
                </button>

            </form>

        </section>

    </main>

</div>

<script>
const facilitySelect = document.getElementById('facility_id');
const bookingType = document.getElementById('booking_type');
const groupContainer = document.getElementById('group_size_container');
const groupSize = document.getElementById('group_size');
const startTime = document.getElementById('start_time');
const endTime = document.getElementById('end_time');

function updateBookingForm() {
    const selected = facilitySelect.options[facilitySelect.selectedIndex];
    const facilityType = selected.dataset.type || '';
    const capacity = parseInt(selected.dataset.capacity || '1', 10);

    if (facilityType === 'seat') {
        bookingType.value = 'individual';
        bookingType.disabled = true;
    } else {
        bookingType.disabled = false;
    }

    if (bookingType.value === 'group' && facilityType !== 'seat') {
        groupContainer.hidden = false;
        groupSize.required = true;
        groupSize.max = capacity;

        if (parseInt(groupSize.value || '2', 10) > capacity) {
            groupSize.value = capacity;
        }
    } else {
        groupContainer.hidden = true;
        groupSize.required = false;
    }
}

function updateEndTime() {
    if (!startTime.value) {
        endTime.value = '';
        return;
    }

    const parts = startTime.value.split(':');
    const date = new Date();

    date.setHours(
        parseInt(parts[0], 10),
        parseInt(parts[1], 10),
        0,
        0
    );

    date.setMinutes(date.getMinutes() + 120);

    endTime.value = date.toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit'
    });
}

facilitySelect.addEventListener('change', updateBookingForm);
bookingType.addEventListener('change', updateBookingForm);
startTime.addEventListener('input', updateEndTime);

updateBookingForm();
</script>

</body>
</html>
