
<?php

date_default_timezone_set('Africa/Accra');

require_once '../includes/authentication.php';
require_once '../config/settings.php';
require_once '../includes/mailer.php';
requireRole('student');

require_once '../config/database.php';


// Render a polished booking result page. Successful bookings show the saved details;
// failed attempts show a clear message and a safe route back to booking.
function showMessage($message, $success = false, array $booking = [])
{
    $heading = $success ? 'Booking confirmed!' : 'Booking not completed';
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    $reference = htmlspecialchars((string)($booking['reference'] ?? ''), ENT_QUOTES, 'UTF-8');
    $facility = htmlspecialchars((string)($booking['facility'] ?? ''), ENT_QUOTES, 'UTF-8');
    $type = htmlspecialchars(ucfirst((string)($booking['type'] ?? '')), ENT_QUOTES, 'UTF-8');
    $date = htmlspecialchars((string)($booking['date'] ?? ''), ENT_QUOTES, 'UTF-8');
    $time = htmlspecialchars((string)($booking['time'] ?? ''), ENT_QUOTES, 'UTF-8');

    echo '<!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>' . $heading . ' | Sam Jonah Library</title>
        <link rel="stylesheet" href="../assets/css/style.css">
        <link rel="stylesheet" href="../assets/css/theme.css">
        <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
    </head>
    <body class="booking-result-body">
        <main class="booking-result-wrap">
            <section class="booking-result-card ' . ($success ? 'is-success' : 'is-error') . '">
                <div class="booking-result-symbol">' . ($success ? '&#10003;' : '!') . '</div>
                <p class="booking-result-eyebrow">' . ($success ? 'SAM JONAH LIBRARY · RESERVATION' : 'PLEASE TRY AGAIN') . '</p>
                <h1>' . $heading . '</h1>
                <p class="booking-result-message">' . $safeMessage . '</p>';

    if ($success) {
        echo '<div class="booking-confirmation-details">
                <div class="booking-reference">
                    <span>BOOKING REFERENCE</span>
                    <strong>#' . $reference . '</strong>
                </div>
                <div class="booking-detail-grid">
                    <div><span>Facility</span><strong>' . $facility . '</strong></div>
                    <div><span>Booking type</span><strong>' . $type . '</strong></div>
                    <div><span>Date</span><strong>' . $date . '</strong></div>
                    <div><span>Time</span><strong>' . $time . '</strong></div>
                </div>
                <div class="booking-confirmed-status"><span></span> Confirmed</div>
              </div>';
    }

    echo '<div class="booking-result-actions">
                <a class="booking-result-primary" href="my-bookings.php">' . ($success ? 'View My Bookings' : 'Try Booking Again') . '</a>
                <a class="booking-result-secondary" href="dashboard.php">Student Dashboard</a>
            </div>
            </section>
            <p class="booking-result-footer">Sam Jonah Library · University of Cape Coast</p>
        </main>
    </body>
    </html>';
    exit;
}


// Only accept form submissions
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: book-facility.php');
    exit;
}


if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    showMessage('Invalid request. Please go back and try again.');
}

// Get form information
$userId = $_SESSION['user_id'];

$facilityId = filter_input(
    INPUT_POST,
    'facility_id',
    FILTER_VALIDATE_INT
);

$bookingType = $_POST['booking_type'] ?? 'individual';

$bookingDate = $_POST['booking_date'] ?? '';

$startTime = $_POST['start_time'] ?? '';

$groupSize = filter_input(
    INPUT_POST,
    'group_size',
    FILTER_VALIDATE_INT
);


// Basic validation
if (!$facilityId || $facilityId < 1) {
    showMessage('Please select a valid facility.');
}

if (!in_array($bookingType, ['individual', 'group'], true)) {
    showMessage('Invalid booking type.');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $bookingDate)) {
    showMessage('Please select a valid booking date.');
}

if (!preg_match('/^\d{2}:\d{2}$/', $startTime)) {
    showMessage('Please enter a valid starting time.');
}


// Calculate booking start and end
$start = DateTime::createFromFormat(
    '!Y-m-d H:i',
    $bookingDate . ' ' . $startTime
);

$errors = DateTime::getLastErrors();

if (
    !$start ||
    ($errors !== false &&
        ($errors['warning_count'] > 0 || $errors['error_count'] > 0)) ||
    $start->format('Y-m-d H:i') !== $bookingDate . ' ' . $startTime
) {
    showMessage('The selected date or starting time is invalid.');
}

$end = clone $start;
$end->modify('+' . BOOKING_DURATION . ' minutes');


// Check advance booking limit
$today = new DateTime('today');

$lastBookingDate = (clone $today)->modify('+' . ADVANCE_BOOKING_DAYS . ' days');

if ($start < new DateTime()) {
    showMessage('You cannot book a time that has already passed.');
}

if (
    $start->format('Y-m-d') < $today->format('Y-m-d') ||
    $start->format('Y-m-d') > $lastBookingDate->format('Y-m-d')
) {
    showMessage('Bookings are allowed only within the next 7 days.');
}


// Determine group size
if ($bookingType === 'individual') {
    $groupSize = 1;
} else {
    if (!$groupSize || $groupSize < 2) {
        showMessage('A group booking must have at least 2 people.');
    }
}


try {

    $conn->beginTransaction();


    // Lock the selected facility while checking availability.
    // This helps prevent simultaneous conflicting bookings.
    $sql = "SELECT *
            FROM facilities
            WHERE facility_id = ?
            AND is_active = 1
            FOR UPDATE";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$facilityId]);

    $facility = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$facility) {
        throw new Exception('The selected facility is unavailable.');
    }


    // Study seats only allow individual bookings
    if (
        $facility['facility_type'] === 'seat' &&
        $bookingType !== 'individual'
    ) {
        throw new Exception('Study seats only allow individual bookings.');
    }


    // Check group capacity
    if ($groupSize > (int)$facility['capacity']) {
        throw new Exception('Your group is larger than the facility capacity.');
    }


    // Check operating hours
    // MySQL DAYOFWEEK: Sunday = 1, Monday = 2, ..., Saturday = 7
    $dayOfWeek = (int)$start->format('w') + 1;

    $sql = "SELECT *
            FROM operating_hours
            WHERE day_of_week = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$dayOfWeek]);

    $hours = $stmt->fetch(PDO::FETCH_ASSOC);


    if (!$hours) {
        throw new Exception(
            'Operating hours have not been configured for this day. Please contact library staff.'
        );
    }

    if (
        (int)$hours['is_closed'] === 1 ||
        empty($hours['opening_time']) ||
        empty($hours['closing_time'])
    ) {
        throw new Exception('The library is closed on the selected day.');
    }


    $opening = new DateTime(
        $bookingDate . ' ' . $hours['opening_time']
    );

    $closing = new DateTime(
        $bookingDate . ' ' . $hours['closing_time']
    );


    if ($start < $opening || $end > $closing) {
        throw new Exception(
            'Your 2-hour booking must fit within the library operating hours.'
        );
    }


    // Check existing bookings and the 10-minute buffer
    $bufferMinutes = (int)$facility['buffer_minutes'];

    $bufferedStart = clone $start;
    $bufferedStart->modify("-{$bufferMinutes} minutes");

    $bufferedEnd = clone $end;
    $bufferedEnd->modify("+{$bufferMinutes} minutes");


    $sql = "SELECT booking_id
            FROM bookings
            WHERE facility_id = ?
            AND status = 'confirmed'
            AND start_at < ?
            AND end_at > ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $facilityId,
        $bufferedEnd->format('Y-m-d H:i:s'),
        $bufferedStart->format('Y-m-d H:i:s')
    ]);

    $conflict = $stmt->fetch(PDO::FETCH_ASSOC);


    if ($conflict) {
        throw new Exception(
            'This facility is already booked during that period or its 10-minute buffer. Please choose another starting time.'
        );
    }


    // Per-student rules: no overlapping bookings and a cap on upcoming bookings.
    $overlap = $conn->prepare("SELECT booking_id FROM bookings WHERE user_id = ? AND status = 'confirmed' AND start_at < ? AND end_at > ? LIMIT 1");
    $overlap->execute([$userId, $end->format('Y-m-d H:i:s'), $start->format('Y-m-d H:i:s')]);
    if ($overlap->fetch()) {
        throw new Exception('This student already has a booking that overlaps with that time.');
    }
    $active = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ? AND status = 'confirmed' AND end_at > NOW()");
    $active->execute([$userId]);
    if ((int) $active->fetchColumn() >= MAX_ACTIVE_BOOKINGS) {
        throw new Exception('A student can hold at most ' . MAX_ACTIVE_BOOKINGS . ' upcoming bookings.');
    }

    // Save the confirmed booking
    $sql = "INSERT INTO bookings (
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
            VALUES (?, ?, ?, ?, 'custom', ?, ?, 'online', 'confirmed')";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $userId,
        $facilityId,
        $bookingType,
        $groupSize,
        $start->format('Y-m-d H:i:s'),
        $end->format('Y-m-d H:i:s')
    ]);

    $bookingId = $conn->lastInsertId();


    // Record the booking in the audit log
    $sql = "INSERT INTO booking_audit_logs (
                booking_id,
                actor_user_id,
                action,
                description
            )
            VALUES (?, ?, 'booking_created', ?)";

    $description = 'Online booking created successfully.';

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $bookingId,
        $userId,
        $description
    ]);


    // Create an immediate confirmation notification
    $sql = "INSERT INTO notifications (
                user_id,
                booking_id,
                message,
                notification_type,
                sent_at
            )
            VALUES (?, ?, ?, 'confirmation', NOW())";

    $message = 'Your facility booking has been confirmed.';

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $userId,
        $bookingId,
        $message
    ]);


    $conn->commit();

    // Email is supplementary: a mail-server issue must not make a saved booking
    // appear to have failed after the database transaction has committed.
    try {
        sendEmail($_SESSION['email'] ?? '', 'Booking confirmed #' . $bookingId,
            'Your booking for ' . $facility['facility_name'] . ' on ' . $start->format('d M Y, g:i A') . ' to ' . $end->format('g:i A') . ' is confirmed.');
    } catch (Throwable $mailError) {
        error_log('Booking confirmation email failed: ' . $mailError->getMessage());
    }

    showMessage(
        'Your reservation has been saved successfully. Your booking is now confirmed.',
        true,
        [
            'reference' => $bookingId,
            'facility' => $facility['facility_name'],
            'type' => $bookingType,
            'date' => $start->format('l, d F Y'),
            'time' => $start->format('g:i A') . ' – ' . $end->format('g:i A')
        ]
    );
} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    if ($e instanceof PDOException) {
        error_log($e->getMessage());
        showMessage('A database error occurred. Please try again or contact library staff.');
    }
    showMessage($e->getMessage());
} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    showMessage(
        'A database error occurred. Please try again or contact library staff.'
    );
}

?>
