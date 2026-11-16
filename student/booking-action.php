<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('student');

date_default_timezone_set('Africa/Accra');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: my-bookings.php");
    exit();
}

if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    $_SESSION['booking_error'] = "Invalid request. Please try again.";
    header("Location: my-bookings.php");
    exit();
}

$bookingId = filter_var(
    $_POST['booking_id'] ?? null,
    FILTER_VALIDATE_INT
);

$action = $_POST['action'] ?? '';

if (!$bookingId || !in_array($action, ['checkin', 'checkout'], true)) {
    $_SESSION['booking_error'] = "Invalid booking action.";
    header("Location: my-bookings.php");
    exit();
}

try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("
        SELECT booking_id, user_id, status, start_at, end_at,
               check_in_at, check_out_at
        FROM bookings
        WHERE booking_id = ?
          AND user_id = ?
        FOR UPDATE
    ");

    $stmt->execute([
        $bookingId,
        $_SESSION['user_id']
    ]);

    $booking = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$booking) {
        throw new Exception("Booking not found.");
    }

    $now = new DateTime();

    if ($action === 'checkin') {

        if ($booking['status'] !== 'confirmed') {
            throw new Exception("This booking is not available for check-in.");
        }

        if ($booking['check_in_at'] !== null) {
            throw new Exception("You have already checked in.");
        }

        $start = new DateTime($booking['start_at']);
        $graceEnd = clone $start;
        $graceEnd->modify('+15 minutes');

        if ($now < $start) {
            throw new Exception("Check-in opens at your booking start time.");
        }

        if ($now > $graceEnd) {
            throw new Exception("Your 15-minute check-in grace period has expired.");
        }

        if ($now >= new DateTime($booking['end_at'])) {
            throw new Exception("This booking has already ended.");
        }

        $update = $conn->prepare("
            UPDATE bookings
            SET check_in_at = NOW(),
                check_in_method = 'dashboard'
            WHERE booking_id = ?
              AND check_in_at IS NULL
              AND status = 'confirmed'
        ");

        $update->execute([$bookingId]);

        $audit = $conn->prepare("
            INSERT INTO booking_audit_logs
            (booking_id, actor_user_id, action, description)
            VALUES (?, ?, 'check_in', 'Student checked in through the dashboard.')
        ");

        $audit->execute([
            $bookingId,
            $_SESSION['user_id']
        ]);

        $notification = $conn->prepare("
            INSERT INTO notifications
            (user_id, booking_id, message, notification_type, sent_at)
            VALUES (?, ?, 'You have successfully checked in.', 'general', NOW())
        ");

        $notification->execute([
            $_SESSION['user_id'],
            $bookingId
        ]);

        $successMessage = "Check-in successful.";

    } else {

        if ($booking['status'] !== 'confirmed') {
            throw new Exception("This booking cannot be checked out.");
        }

        if ($booking['check_in_at'] === null) {
            throw new Exception("You must check in before checking out.");
        }

        if ($booking['check_out_at'] !== null) {
            throw new Exception("You have already checked out.");
        }

        $update = $conn->prepare("
            UPDATE bookings
            SET check_out_at = NOW(),
                check_out_method = 'dashboard',
                status = 'completed'
            WHERE booking_id = ?
              AND check_in_at IS NOT NULL
              AND check_out_at IS NULL
              AND status = 'confirmed'
        ");

        $update->execute([$bookingId]);

        $audit = $conn->prepare("
            INSERT INTO booking_audit_logs
            (booking_id, actor_user_id, action, description)
            VALUES (?, ?, 'check_out', 'Student checked out through the dashboard.')
        ");

        $audit->execute([
            $bookingId,
            $_SESSION['user_id']
        ]);

        $notification = $conn->prepare("
            INSERT INTO notifications
            (user_id, booking_id, message, notification_type, sent_at)
            VALUES (?, ?, 'Your booking has been completed successfully.', 'general', NOW())
        ");

        $notification->execute([
            $_SESSION['user_id'],
            $bookingId
        ]);

        $successMessage = "Check-out successful. Your booking is completed.";
    }

    $conn->commit();

    $_SESSION['booking_success'] = $successMessage;

} catch (PDOException $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    error_log($e->getMessage());
    $_SESSION['booking_error'] = "A database error occurred. Please try again.";

} catch (Exception $e) {

    if ($conn->inTransaction()) {
        $conn->rollBack();
    }

    $_SESSION['booking_error'] = $e->getMessage();
}

header("Location: my-bookings.php");
exit();