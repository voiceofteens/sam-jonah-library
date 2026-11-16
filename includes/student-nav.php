<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">

    <div class="sidebar-brand">
        <img src="../assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-sidebar">
        <h2>Sam Jonah</h2>
        <p>Library Booking Portal</p>
    </div>

    <nav class="sidebar-nav">

        <a href="dashboard.php"
           class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            Dashboard
        </a>

        <a href="book-facility.php"
           class="<?= $currentPage === 'book-facility.php' ? 'active' : '' ?>">
            Book a Facility
        </a>

        <a href="my-bookings.php"
           class="<?= $currentPage === 'my-bookings.php' ? 'active' : '' ?>">
            My Bookings
        </a>

        <a href="notifications.php"
           class="<?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
            Notifications
        </a>

        <a href="profile.php"
           class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
            My Profile
        </a>

    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php">Sign Out</a>
    </div>

</aside>