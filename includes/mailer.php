<?php
require_once __DIR__ . '/../config/settings.php';

// Safe wrapper: never breaks a booking if mail is not configured.
function sendEmail($to, $subject, $body) {
    if (!MAIL_ENABLED || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $headers = "From: " . MAIL_FROM . "\r\nContent-Type: text/plain; charset=UTF-8";
    $sent = @mail($to, str_replace(["\r", "\n"], ' ', $subject), $body, $headers);
    if (!$sent) {
        error_log("Email to $to failed.");
    }
    return $sent;
}
