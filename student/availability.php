<?php
require_once '../includes/authentication.php';
require_once '../config/database.php';
requireRole('student');
header('Content-Type: application/json');

$facilityId = filter_input(INPUT_GET, 'facility_id', FILTER_VALIDATE_INT);
$date = $_GET['date'] ?? '';
if (!$facilityId || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request']);
    exit;
}
$stmt = $conn->prepare("SELECT start_at, end_at FROM bookings
    WHERE facility_id = ? AND status = 'confirmed'
      AND start_at < ? + INTERVAL 1 DAY AND end_at > ? ORDER BY start_at");
$stmt->execute([$facilityId, $date, $date]);
$slots = array_map(fn($r) => [
    'start' => date('g:i A', strtotime($r['start_at'])),
    'end'   => date('g:i A', strtotime($r['end_at']))
], $stmt->fetchAll());

$h = $conn->prepare("SELECT opening_time, closing_time, is_closed FROM operating_hours WHERE day_of_week = ?");
$h->execute([(int) date('w', strtotime($date)) + 1]);
echo json_encode(['booked' => $slots, 'hours' => $h->fetch() ?: null]);
