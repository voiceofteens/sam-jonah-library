<?php

require_once '../includes/authentication.php';
requireRole('student');

require_once '../config/database.php';
require_once '../config/settings.php';

// Get all active facilities
$sql = "SELECT * FROM facilities
        WHERE is_active = 1
        ORDER BY facility_type, facility_name";

$stmt = $conn->query($sql);
$facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">

    <title>Book a Facility | Sam Jonah Library</title>

    <link rel="stylesheet"
        href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">



</head>

<body>

    <div class="dashboard-layout">

        <?php include '../includes/student-nav.php'; ?>

        <main class="main-content">

            <div class="topbar">
                <h1>Book a Facility</h1>
            </div>

            <section class="welcome-section">

                <h2>Make a Reservation</h2>

                <p>
                    Select your preferred library facility
                    and booking time.
                </p>

            </section>

            <section class="booking-form">

                <form action="process-booking.php" method="POST">
                    <?= csrfField() ?>

                    <div class="form-group">

                        <label for="facility_id">
                            Select Facility
                        </label>

                        <select name="facility_id"
                            id="facility_id"
                            required>

                            <option value="">
                                Choose a facility
                            </option>

                            <?php foreach ($facilities as $facility): ?>

                                <option
                                    value="<?= $facility['facility_id'] ?>"
                                    data-type="<?= htmlspecialchars($facility['facility_type']) ?>"
                                    data-capacity="<?= (int)$facility['capacity'] ?>">

                                    <?= htmlspecialchars($facility['facility_name']) ?>
                                    (<?= htmlspecialchars($facility['facility_code']) ?>)

                                </option>

                            <?php endforeach; ?>

                        </select>

                    </div>


                    <div class="form-group">

                        <label for="booking_type">
                            Booking Type
                        </label>

                        <select name="booking_type"
                            id="booking_type"
                            required>

                            <option value="individual">
                                Individual Booking
                            </option>

                            <option value="group">
                                Group Booking
                            </option>

                        </select>

                        <p class="form-note">
                            Study seats only allow individual bookings.
                        </p>

                    </div>


                    <div class="form-group"
                        id="group_size_container"
                        style="display:none;">

                        <label for="group_size">
                            Number of People
                        </label>

                        <input type="number"
                            name="group_size"
                            id="group_size"
                            min="2"
                            value="2">

                        <p class="form-note">
                            The group size must not exceed
                            the facility's capacity.
                        </p>

                    </div>


                    <div class="form-group">

                        <label for="booking_date">
                            Booking Date
                        </label>

                        <input type="date"
                            name="booking_date"
                            id="booking_date"
                            required>

                    </div>

                    <div id="availability" class="form-note" aria-live="polite"></div>


                    <div class="form-group">

                        <label for="start_time">
                            Starting Time
                        </label>

                        <input type="time"
                            name="start_time"
                            id="start_time"
                            required>

                    </div>

                    <div class="form-group">

                        <label for="end_time">
                            Ending Time
                        </label>

                        <input type="time"
                            id="end_time"
                            readonly>

                        <p class="form-note">
                            Your booking automatically lasts 2 hours.
                        </p>

                    </div>


                    <button type="submit"
                        class="booking-button">

                        Continue to Check Availability

                    </button>

                </form>

            </section>

        </main>

    </div>


    <script>
        const facilitySelect =
            document.getElementById('facility_id');

        const bookingType =
            document.getElementById('booking_type');

        const groupContainer =
            document.getElementById('group_size_container');

        const groupSize =
            document.getElementById('group_size');


        function updateBookingOptions() {

            const selectedOption =
                facilitySelect.options[facilitySelect.selectedIndex];

            const facilityType =
                selectedOption.dataset.type;

            const capacity =
                Number(selectedOption.dataset.capacity || 1);

            // Seats cannot have group bookings
            if (facilityType === 'seat') {

                bookingType.value = 'individual';

                bookingType.disabled = true;

                groupContainer.style.display = 'none';

                groupSize.required = false;

            } else {

                bookingType.disabled = false;

                updateGroupOptions();

            }

            groupSize.max = capacity;

        }


        function updateGroupOptions() {

            if (bookingType.value === 'group') {

                groupContainer.style.display = 'block';

                groupSize.required = true;

            } else {

                groupContainer.style.display = 'none';

                groupSize.required = false;

            }

        }


        facilitySelect.addEventListener(
            'change',
            updateBookingOptions
        );

        bookingType.addEventListener(
            'change',
            updateGroupOptions
        );


        // Prevent selecting a past date
        const dateInput =
            document.getElementById('booking_date');

        const today = new Date();

        const year = today.getFullYear();

        const month = String(today.getMonth() + 1).padStart(2, '0');

        const day = String(today.getDate()).padStart(2, '0');

        dateInput.min = `${year}-${month}-${day}`;
        const maxDate = new Date(today.getTime() + <?= (int) ADVANCE_BOOKING_DAYS ?> * 86400000);
        dateInput.max = maxDate.getFullYear() + '-' + String(maxDate.getMonth() + 1).padStart(2, '0') + '-' + String(maxDate.getDate()).padStart(2, '0');



        const startTimeInput =
            document.getElementById('start_time');

        const endTimeInput =
            document.getElementById('end_time');


        startTimeInput.addEventListener('change', function() {

            if (!this.value) {
                endTimeInput.value = '';
                return;
            }

            const [hours, minutes] =
            this.value.split(':').map(Number);

            const startMinutes = hours * 60 + minutes;

            const endMinutes = startMinutes + 120;

            const endHours = Math.floor(endMinutes / 60);

            const remainingMinutes = endMinutes % 60;

            if (endMinutes >= 24 * 60) {
                endTimeInput.value = 'Next day';
                return;
            }

            endTimeInput.value =
                String(endHours).padStart(2, '0') + ':' +
                String(remainingMinutes).padStart(2, '0');

        });

        async function showAvailability() {
            const box = document.getElementById('availability');
            if (!facilitySelect.value || !dateInput.value) { box.textContent = ''; return; }
            try {
                const r = await fetch('availability.php?facility_id=' + encodeURIComponent(facilitySelect.value) + '&date=' + dateInput.value);
                const d = await r.json();
                if (d.hours && Number(d.hours.is_closed)) { box.textContent = 'The library is closed on this day.'; return; }
                const open = d.hours ? 'Open ' + d.hours.opening_time.slice(0, 5) + ' to ' + d.hours.closing_time.slice(0, 5) + '. ' : '';
                box.textContent = open + (d.booked.length
                    ? 'Already booked: ' + d.booked.map(b => b.start + ' - ' + b.end).join(', ')
                    : 'No bookings yet, all times are free.');
            } catch (e) { box.textContent = ''; }
        }
        facilitySelect.addEventListener('change', showAvailability);
        dateInput.addEventListener('change', showAvailability);
    </script>

</body>

</html>