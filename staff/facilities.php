<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('staff');

$message = '';
$error = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (
            !isset($_POST['csrf_token']) ||
            !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
        ) {
            throw new Exception("Invalid request. Reload the page and try again.");
        }

        $facilityId = (int) ($_POST['facility_id'] ?? 0);
        $capacity = filter_var(
            $_POST['capacity'] ?? null,
            FILTER_VALIDATE_INT
        );
        $buffer = filter_var(
            $_POST['buffer_minutes'] ?? null,
            FILTER_VALIDATE_INT
        );
        $isActive = isset($_POST['is_active']) ? 1 : 0;

        if ($facilityId < 1 || $capacity === false || $buffer === false) {
            throw new Exception("Enter valid facility settings.");
        }

        if ($capacity < 1 || $buffer < 0 || $buffer > 120) {
            throw new Exception("Capacity must be at least 1 and buffer must be between 0 and 120 minutes.");
        }

        if (!$isActive) {
            $upcoming = $conn->prepare("SELECT COUNT(*) FROM bookings WHERE facility_id = ? AND status = 'confirmed' AND end_at > NOW()");
            $upcoming->execute([$facilityId]);
            if ((int) $upcoming->fetchColumn() > 0) {
                throw new Exception('This facility has upcoming confirmed bookings. Cancel or complete them before deactivating it.');
            }
        }

        $stmt = $conn->prepare("
            UPDATE facilities
            SET capacity = ?,
                buffer_minutes = ?,
                is_active = ?
            WHERE facility_id = ?
        ");

        $stmt->execute([
            $capacity,
            $buffer,
            $isActive,
            $facilityId
        ]);

        $message = "Facility settings updated successfully.";

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "A database error occurred while updating the facility.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $conn->query("
    SELECT facility_id, facility_code, facility_name,
           facility_type, capacity, location,
           buffer_minutes, is_active
    FROM facilities
    ORDER BY facility_name
");

$facilities = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facility Availability | Sam Jonah Library</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/student.css">
    <link rel="stylesheet" href="../assets/css/theme.css">
    <link rel="icon" type="image/png" href="../assets/images/ucc-crest.png">
</head>

<body>

    <div class="dashboard-layout">

        <aside class="sidebar">
            <div class="sidebar-brand">
        <img src="../assets/images/ucc-crest.png" alt="University of Cape Coast crest" class="crest crest-sidebar">
                <h2>Sam Jonah</h2>
                <p>Staff Portal</p>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="manage-bookings.php">Manage Bookings</a>
                <a href="walk-in.php">Record Walk-in</a>
                <a href="facilities.php" class="active">Facility Availability</a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php">Sign Out</a>
            </div>
        </aside>

        <main class="main-content">

            <h1>Facility Availability</h1>
            <p>Manage facility capacity, buffer time and active status.</p>

            <?php if ($message): ?>
                <div class="alert alert-success">
                    <?= htmlspecialchars($message) ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <div class="facility-management-list">

                <?php foreach ($facilities as $facility): ?>

                    <form method="POST" class="facility-management-card">

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <input
                            type="hidden"
                            name="facility_id"
                            value="<?= (int) $facility['facility_id'] ?>">

                        <h2>
                            <?= htmlspecialchars($facility['facility_name']) ?>
                        </h2>

                        <p>
                            Code:
                            <?= htmlspecialchars($facility['facility_code']) ?>
                        </p>

                        <p>
                            Type:
                            <?= htmlspecialchars(
                                ucwords(str_replace('_', ' ', $facility['facility_type']))
                            ) ?>
                        </p>

                        <p>
                            Location:
                            <?= htmlspecialchars($facility['location'] ?? 'Not specified') ?>
                        </p>

                        <label>Capacity</label>
                        <input
                            type="number"
                            name="capacity"
                            min="1"
                            value="<?= (int) $facility['capacity'] ?>"
                            required>

                        <label>Buffer (minutes)</label>
                        <input
                            type="number"
                            name="buffer_minutes"
                            min="0"
                            max="120"
                            value="<?= (int) $facility['buffer_minutes'] ?>"
                            required>

                        <label class="checkbox-label">
                            <input
                                type="checkbox"
                                name="is_active"
                                value="1"
                                <?= $facility['is_active'] ? 'checked' : '' ?>>
                            Facility is active and available for booking
                        </label>

                        <button type="submit" class="btn-primary">
                            Save Facility Settings
                        </button>

                    </form>

                <?php endforeach; ?>

            </div>

        </main>

    </div>

</body>

</html>