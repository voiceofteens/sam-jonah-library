
<?php

date_default_timezone_set('Africa/Accra');

define('SITE_NAME', 'Sam Jonah Library');

define('BOOKING_DURATION', 120);

define('DEFAULT_BUFFER', 10);

define('ADVANCE_BOOKING_DAYS', 7);

define('CHECK_IN_GRACE_PERIOD', 15);

define('LIBRARY_OPENING_TIME', '09:00');

define('LIBRARY_CLOSING_TIME', '21:00');

define('CHECK_IN_REMINDER_MINUTES', 15);

define('ENDING_REMINDER_MINUTES', 15);

?>
<?php
// Set to true once XAMPP's sendmail/SMTP is configured (php.ini + sendmail.ini).
define('MAIL_ENABLED', false);
define('MAIL_FROM', 'noreply@samjonahlibrary.local');
define('MAX_ACTIVE_BOOKINGS', 3); // upcoming confirmed bookings allowed per student
