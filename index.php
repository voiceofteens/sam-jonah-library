
<?php

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/settings.php';

$sql = "SELECT
            facility_type,
            COUNT(*) AS total
        FROM facilities
        WHERE is_active = 1
        GROUP BY facility_type";

$stmt = $conn->query($sql);

$counts = [
    'seat' => 0,
    'discussion_room' => 0,
    'common_room' => 0
];

while ($row = $stmt->fetch()) {

    $counts[$row['facility_type']] =
        (int) $row['total'];

}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Sam Jonah Library</title>

    <link rel="stylesheet"
          href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/theme.css">
    <link rel="icon" type="image/png" href="assets/images/ucc-crest.png">

</head>

<body>

<header class="navbar">

    <div class="logo">
        <img src="assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-nav">
        Sam Jonah Library
    </div>

    <nav>

        <a href="index.php">Home</a>

        <a href="login.php">Login</a>

    </nav>

</header>

<section class="hero">

    <h1>Welcome to Sam Jonah Library</h1>

    <p>
        Book your library facilities conveniently
        and manage your reservations online.
    </p>

    <a href="login.php" class="primary-button">
        Get Started
    </a>

</section>

<section class="facilities">

    <h2>Our Facilities</h2>

    <div class="facility-grid">

        <div class="facility-card">

            <h3>Study Seats</h3>

            <p>
                <?= $counts['seat'] ?> available
                registered seats
            </p>

        </div>

        <div class="facility-card">

            <h3>Discussion Rooms</h3>

            <p>
                <?= $counts['discussion_room'] ?>
                registered rooms
            </p>

        </div>

        <div class="facility-card">

            <h3>Common Rooms</h3>

            <p>
                <?= $counts['common_room'] ?>
                registered rooms
            </p>

        </div>

    </div>

</section>

<footer>

    <p>
        &copy; <?= date('Y') ?>
        Sam Jonah Library, University of Cape Coast.
    </p>

    <p class="motto">Veritas Nobis Lumen</p>

</footer>

</body>
</html>
