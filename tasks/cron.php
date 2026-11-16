<?php
// Run every minute via Windows Task Scheduler:
//   C:\xampp\php\php.exe C:\xampp\htdocs\sam-jonah-library\tasks\cron.php
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/settings.php';
require_once __DIR__ . '/../includes/mailer.php';

$grace = (int) CHECK_IN_GRACE_PERIOD;
$notify = $conn->prepare("INSERT INTO notifications (user_id, booking_id, message, notification_type, sent_at) VALUES (?, ?, ?, ?, NOW())");
$audit = $conn->prepare("INSERT INTO booking_audit_logs (booking_id, actor_user_id, action, description) VALUES (?, NULL, ?, ?)");

// 1. Mark bookings with no check-in after the grace period as no-shows.
$rows = $conn->query("SELECT booking_id, user_id FROM bookings
    WHERE status = 'confirmed' AND check_in_at IS NULL
      AND start_at < NOW() - INTERVAL $grace MINUTE")->fetchAll();
foreach ($rows as $r) {
    $conn->beginTransaction();
    $u = $conn->prepare("UPDATE bookings SET status = 'no_show' WHERE booking_id = ? AND status = 'confirmed'");
    $u->execute([$r['booking_id']]);
    if ($u->rowCount()) {
        $audit->execute([$r['booking_id'], 'no_show', 'Marked as no-show automatically.']);
        $notify->execute([$r['user_id'], $r['booking_id'], "Booking #{$r['booking_id']} was marked as a no-show because you did not check in.", 'no_show']);
    }
    $conn->commit();
}

// 2. Reminders (sent once per booking and type).
$jobs = [
    ['check_in_reminder', CHECK_IN_REMINDER_MINUTES, 'start_at', 'check_in_at IS NULL', 'Your booking for %s starts at %s. Remember to check in.'],
    ['ending_reminder',   ENDING_REMINDER_MINUTES,   'end_at',   'check_in_at IS NOT NULL AND b.check_out_at IS NULL', 'Your booking for %s ends at %s.'],
];
foreach ($jobs as [$type, $mins, $col, $cond, $text]) {
    $mins = (int) $mins;
    $q = $conn->prepare("SELECT b.booking_id, b.user_id, b.$col AS t, u.email, f.facility_name
        FROM bookings b JOIN users u ON u.user_id = b.user_id JOIN facilities f ON f.facility_id = b.facility_id
        WHERE b.status = 'confirmed' AND b.$cond
          AND b.$col BETWEEN NOW() AND NOW() + INTERVAL $mins MINUTE
          AND NOT EXISTS (SELECT 1 FROM notifications n WHERE n.booking_id = b.booking_id AND n.notification_type = ?)");
    $q->execute([$type]);
    foreach ($q->fetchAll() as $r) {
        $msg = sprintf($text, $r['facility_name'], date('g:i A', strtotime($r['t'])));
        $notify->execute([$r['user_id'], $r['booking_id'], $msg, $type]);
        sendEmail($r['email'], 'Sam Jonah Library reminder', $msg);
    }
}
echo date('c') . " cron done\n";
