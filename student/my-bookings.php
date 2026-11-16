<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('student');

date_default_timezone_set('Africa/Accra');

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = $_SESSION['booking_success'] ?? '';
$error = $_SESSION['booking_error'] ?? '';
$cancelled = isset($_GET['cancelled']);

unset($_SESSION['booking_success'], $_SESSION['booking_error']);

$stmt = $conn->prepare("
    SELECT b.booking_id, b.booking_type, b.group_size,
           b.start_at, b.end_at, b.status,
           b.check_in_at, b.check_out_at,
           f.facility_name, f.facility_code, f.location
    FROM bookings b
    JOIN facilities f ON b.facility_id = f.facility_id
    WHERE b.user_id = ?
    ORDER BY b.start_at DESC
");

$stmt->execute([$_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

function bookingTime($value) {
    return date('d M Y, g:i A', strtotime($value));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings | Sam Jonah Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>
<body>

<div class="dashboard-layout">

    <?php include __DIR__ . '/../includes/student-nav.php'; ?>

    <main class="main-content">

        <h1>My Bookings</h1>
        <p>View and manage your library facility bookings.</p>

        <?php if ($success): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>

        <?php if ($cancelled): ?>
            <div class="alert alert-success">
                Your booking has been cancelled.
            </div>
        <?php endif; ?>

        <?php if (empty($bookings)): ?>

            <div class="dashboard-section">
                <p>You have not made any bookings yet.</p>

                <a href="book-facility.php" class="primary-button">
                    Book a Facility
                </a>
            </div>

        <?php else: ?>

            <div class="booking-list">

                <?php foreach ($bookings as $booking): ?>

                    <?php
                    $now = new DateTime();
                    $start = new DateTime($booking['start_at']);
                    $end = new DateTime($booking['end_at']);

                    $graceEnd = clone $start;
                    $graceEnd->modify('+15 minutes');

                    $canCancel =
                        $booking['status'] === 'confirmed' &&
                        $booking['check_in_at'] === null &&
                        $now < $start;

                    $canCheckIn =
                        $booking['status'] === 'confirmed' &&
                        $booking['check_in_at'] === null &&
                        $now >= $start &&
                        $now <= $graceEnd &&
                        $now < $end;

                    $canCheckOut =
                        $booking['status'] === 'confirmed' &&
                        $booking['check_in_at'] !== null &&
                        $booking['check_out_at'] === null;
                    ?>

                    <div class="booking-card">

                        <div class="booking-card-header">

                            <h2>
                                <?= htmlspecialchars($booking['facility_name']) ?>
                            </h2>

                            <span class="booking-status status-<?= htmlspecialchars($booking['status']) ?>">
                                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $booking['status']))) ?>
                            </span>

                        </div>

                        <p>
                            <strong>Booking Reference:</strong>
                            #<?= (int) $booking['booking_id'] ?>
                        </p>

                        <p>
                            <strong>Facility Code:</strong>
                            <?= htmlspecialchars($booking['facility_code']) ?>
                        </p>

                        <p>
                            <strong>Location:</strong>
                            <?= htmlspecialchars($booking['location'] ?? 'Not specified') ?>
                        </p>

                        <p>
                            <strong>Booking Type:</strong>
                            <?= htmlspecialchars(ucwords($booking['booking_type'])) ?>
                        </p>

                        <p>
                            <strong>Group Size:</strong>
                            <?= (int) $booking['group_size'] ?>
                        </p>

                        <p>
                            <strong>Start:</strong>
                            <?= bookingTime($booking['start_at']) ?>
                        </p>

                        <p>
                            <strong>End:</strong>
                            <?= bookingTime($booking['end_at']) ?>
                        </p>

                        <p>
                            <strong>Check-in:</strong>
                            <?= $booking['check_in_at']
                                ? bookingTime($booking['check_in_at'])
                                : 'Not checked in' ?>
                        </p>

                        <p>
                            <strong>Check-out:</strong>
                            <?= $booking['check_out_at']
                                ? bookingTime($booking['check_out_at'])
                                : 'Not checked out' ?>
                        </p>

                        <?php if ($booking['status'] === 'confirmed'): ?>

                            <div class="booking-actions">

                                <?php if ($canCancel): ?>

                                    <form method="POST" action="cancel-booking.php"
                                          onsubmit="return confirm('Are you sure you want to cancel this booking?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                        <input type="hidden" name="booking_id"
                                               value="<?= (int) $booking['booking_id'] ?>">

                                        <button type="submit" class="btn-danger">
                                            Cancel Booking
                                        </button>

                                    </form>

                                <?php endif; ?>

                                <?php if ($booking['check_in_at'] === null): ?>

                                    <?php if ($canCheckIn): ?>

                                        <form method="POST" action="booking-action.php">
                                            <input type="hidden" name="csrf_token"
                                                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                            <input type="hidden" name="booking_id"
                                                   value="<?= (int) $booking['booking_id'] ?>">

                                            <input type="hidden" name="action" value="checkin">

                                            <button type="submit" class="btn-primary">
                                                Check In
                                            </button>
                                        </form>

                                    <?php elseif ($now < $start): ?>

                                        <button class="btn-disabled" disabled>
                                            Check-in opens at start time
                                        </button>

                                    <?php else: ?>

                                        <button class="btn-disabled" disabled>
                                            Check-in window expired
                                        </button>

                                    <?php endif; ?>

                                <?php elseif ($canCheckOut): ?>

                                    <form method="POST" action="booking-action.php"
                                          onsubmit="return confirm('Confirm that you are checking out?');">

                                        <input type="hidden" name="csrf_token"
                                               value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                                        <input type="hidden" name="booking_id"
                                               value="<?= (int) $booking['booking_id'] ?>">

                                        <input type="hidden" name="action" value="checkout">

                                        <button type="submit" class="btn-primary">
                                            Check Out
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </main>

</div>

</body>
</html>