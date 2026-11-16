
<?php

date_default_timezone_set('Africa/Accra');

require_once __DIR__ . '/../includes/authentication.php';
requireRole('student');

require_once __DIR__ . '/../config/database.php';

// Accept POST requests only
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-bookings.php");
    exit();
}

// Verify the CSRF token
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    die("Invalid request. Please return to My Bookings and try again.");
}

// Validate booking ID
$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);

if (!$bookingId || $bookingId < 1) {
    die("Invalid booking reference.");
}

$userId = $_SESSION['user_id'];

try {

    $conn->beginTransaction();

    // Lock the booking while checking and cancelling it
    $sql = "SELECT booking_id, status, start_at, check_in_at
            FROM bookings
            WHERE booking_id = ?
            AND user_id = ?
            FOR UPDATE";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$bookingId, $userId]);

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    if ($booking['status'] !== 'confirmed') {
        throw new Exception("Only confirmed bookings can be cancelled.");
    }

    if ($booking['check_in_at'] !== null) {
        throw new Exception("You cannot cancel a booking after checking in.");
    }

    if (strtotime($booking['start_at']) <= time()) {
        throw new Exception("You cannot cancel a booking that has already started.");
    }

    // Update the booking status
    $sql = "UPDATE bookings
            SET status = 'cancelled'
            WHERE booking_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->execute([$bookingId]);

    // Record the cancellation in the audit log
    $sql = "INSERT INTO booking_audit_logs
            (booking_id, actor_user_id, action, description)
            VALUES (?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $bookingId,
        $userId,
        'booking_cancelled',
        'Student cancelled the booking.'
    ]);

    // Notify the student
    $sql = "INSERT INTO notifications
            (user_id, booking_id, message, notification_type, sent_at)
            VALUES (?, ?, ?, ?, NOW())";

    $stmt = $conn->prepare($sql);

    $stmt->execute([
        $userId,
        $bookingId,
        'Your booking #' . $bookingId . ' has been cancelled successfully.',
        'cancellation'
    ]);

    $conn->commit();

    // Generate a fresh CSRF token
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

    header("Location: my-bookings.php?cancelled=1");
    exit();
} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($e->getMessage());
    die("A database error occurred. Please try again.");
} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    die(htmlspecialchars($e->getMessage()));
}
?>
