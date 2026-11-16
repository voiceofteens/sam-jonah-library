<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$navUnread = 0;

if (isset($conn, $_SESSION['user_id'])) {
    try {
        $navStmt = $conn->prepare("
            SELECT COUNT(*)
            FROM notifications
            WHERE user_id = ?
              AND is_read = 0
        ");
        $navStmt->execute([$_SESSION['user_id']]);
        $navUnread = (int) $navStmt->fetchColumn();
    } catch (PDOException $e) {
        $navUnread = 0;
    }
}
?>

<aside class="sidebar">
    <div class="sidebar-brand">
        <img src="../assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-sidebar">
        <h2>Sam Jonah Library</h2>
        <p>Staff Portal</p>
    </div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
            Dashboard
        </a>
        <a href="manage-bookings.php" class="<?= $currentPage === 'manage-bookings.php' ? 'active' : '' ?>">
            Manage Bookings
        </a>
        <a href="walk-in.php" class="<?= $currentPage === 'walk-in.php' ? 'active' : '' ?>">
            Walk-in Bookings
        </a>
        <a href="facilities.php" class="<?= $currentPage === 'facilities.php' ? 'active' : '' ?>">
            Manage Facilities
        </a>
        <a href="notifications.php" class="<?= $currentPage === 'notifications.php' ? 'active' : '' ?>">
            Notifications<?= $navUnread > 0 ? ' (' . $navUnread . ')' : '' ?>
        </a>
    </nav>

    <div class="sidebar-footer">
        <a href="../logout.php">Sign Out</a>
    </div>
</aside>
