<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('admin');

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
            throw new Exception("Invalid request. Reload the page.");
        }

        $action = $_POST['action'] ?? '';

        if ($action === 'add') {
            $code = strtoupper(trim($_POST['facility_code'] ?? ''));
            $name = trim($_POST['facility_name'] ?? '');
            $type = $_POST['facility_type'] ?? '';
            $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
            $location = trim($_POST['location'] ?? '');
            $buffer = filter_var($_POST['buffer_minutes'] ?? null, FILTER_VALIDATE_INT);

            if (
                $code === '' || $name === '' || $location === '' ||
                $capacity === false || $buffer === false
            ) {
                throw new Exception("Complete all facility fields correctly.");
            }

            if (!in_array($type, ['seat', 'discussion_room', 'common_room'], true)) {
                throw new Exception("Invalid facility type.");
            }

            if ($capacity < 1 || $buffer < 0 || $buffer > 120) {
                throw new Exception("Capacity must be at least 1 and buffer must be between 0 and 120.");
            }

            if ($type === 'seat' && $capacity !== 1) {
                throw new Exception("A study seat must have a capacity of 1.");
            }

            $stmt = $conn->prepare("
                INSERT INTO facilities
                (facility_code, facility_name, facility_type,
                 capacity, location, buffer_minutes, is_active)
                VALUES (?, ?, ?, ?, ?, ?, 1)
            ");

            $stmt->execute([
                $code,
                $name,
                $type,
                $capacity,
                $location,
                $buffer
            ]);

            $message = "Facility added successfully.";
        } elseif ($action === 'update') {
            $facilityId = (int) ($_POST['facility_id'] ?? 0);
            $name = trim($_POST['facility_name'] ?? '');
            $type = $_POST['facility_type'] ?? '';
            $capacity = filter_var($_POST['capacity'] ?? null, FILTER_VALIDATE_INT);
            $location = trim($_POST['location'] ?? '');
            $buffer = filter_var($_POST['buffer_minutes'] ?? null, FILTER_VALIDATE_INT);
            $isActive = isset($_POST['is_active']) ? 1 : 0;

            if (
                $facilityId < 1 || $name === '' || $location === '' ||
                $capacity === false || $buffer === false
            ) {
                throw new Exception("Enter valid facility information.");
            }

            if (!in_array($type, ['seat', 'discussion_room', 'common_room'], true)) {
                throw new Exception("Invalid facility type.");
            }

            if ($capacity < 1 || $buffer < 0 || $buffer > 120) {
                throw new Exception("Capacity must be at least 1 and buffer must be between 0 and 120.");
            }

            if ($type === 'seat' && $capacity !== 1) {
                throw new Exception("A study seat must have a capacity of 1.");
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
                SET facility_name = ?,
                    facility_type = ?,
                    capacity = ?,
                    location = ?,
                    buffer_minutes = ?,
                    is_active = ?
                WHERE facility_id = ?
            ");

            $stmt->execute([
                $name,
                $type,
                $capacity,
                $location,
                $buffer,
                $isActive,
                $facilityId
            ]);

            $message = "Facility updated successfully.";
        } else {
            throw new Exception("Invalid operation.");
        }

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    } catch (PDOException $e) {
        error_log($e->getMessage());
        $error = "A database error occurred. Check that the facility code is unique.";
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$stmt = $conn->query("
    SELECT *
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
    <title>Manage Facilities | Sam Jonah Library</title>
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
                <p>Administrator</p>
            </div>

            <nav class="sidebar-nav">
                <a href="dashboard.php">Dashboard</a>
                <a href="users.php">Manage Accounts</a>
                <a href="facilities.php" class="active">Manage Facilities</a>
                <a href="operating-hours.php">Operating Hours</a>
                <a href="bookings.php">All Bookings</a>
            </nav>

            <div class="sidebar-footer">
                <a href="../logout.php">Sign Out</a>
            </div>
        </aside>

        <main class="main-content">

            <h1>Manage Facilities</h1>
            <p>Add facilities and manage their details and availability.</p>

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

            <section class="dashboard-section">

                <h2>Add New Facility</h2>

                <form method="POST" class="booking-form">

                    <input type="hidden" name="csrf_token"
                        value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                    <input type="hidden" name="action" value="add">

                    <label>Facility Code</label>
                    <input type="text" name="facility_code" required>

                    <label>Facility Name</label>
                    <input type="text" name="facility_name" required>

                    <label>Facility Type</label>
                    <select name="facility_type" id="new_type" required>
                        <option value="seat">Study Seat</option>
                        <option value="discussion_room">Discussion Room</option>
                        <option value="common_room">Common Room</option>
                    </select>

                    <label>Capacity</label>
                    <input type="number" name="capacity" id="new_capacity"
                        min="1" value="1" required>

                    <label>Location</label>
                    <input type="text" name="location" required>

                    <label>Buffer Minutes</label>
                    <input type="number" name="buffer_minutes"
                        min="0" max="120" value="10" required>

                    <button type="submit" class="btn-primary">
                        Add Facility
                    </button>

                </form>

            </section>

            <section class="dashboard-section">

                <h2>Existing Facilities</h2>

                <?php foreach ($facilities as $facility): ?>

                    <form method="POST" class="facility-management-card">

                        <input type="hidden" name="csrf_token"
                            value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                        <input type="hidden" name="action" value="update">

                        <input type="hidden" name="facility_id"
                            value="<?= (int) $facility['facility_id'] ?>">

                        <h3>
                            <?= htmlspecialchars($facility['facility_code']) ?>
                        </h3>

                        <label>Facility Name</label>
                        <input type="text" name="facility_name"
                            value="<?= htmlspecialchars($facility['facility_name']) ?>"
                            required>

                        <label>Facility Type</label>
                        <select name="facility_type" required>
                            <option value="seat" <?= $facility['facility_type'] === 'seat' ? 'selected' : '' ?>>
                                Study Seat
                            </option>
                            <option value="discussion_room" <?= $facility['facility_type'] === 'discussion_room' ? 'selected' : '' ?>>
                                Discussion Room
                            </option>
                            <option value="common_room" <?= $facility['facility_type'] === 'common_room' ? 'selected' : '' ?>>
                                Common Room
                            </option>
                        </select>

                        <label>Capacity</label>
                        <input type="number" name="capacity" min="1"
                            value="<?= (int) $facility['capacity'] ?>" required>

                        <label>Location</label>
                        <input type="text" name="location"
                            value="<?= htmlspecialchars($facility['location'] ?? '') ?>"
                            required>

                        <label>Buffer Minutes</label>
                        <input type="number" name="buffer_minutes"
                            min="0" max="120"
                            value="<?= (int) $facility['buffer_minutes'] ?>"
                            required>

                        <label class="checkbox-label">
                            <input type="checkbox" name="is_active" value="1"
                                <?= $facility['is_active'] ? 'checked' : '' ?>>
                            Active
                        </label>

                        <button type="submit" class="btn-primary">
                            Save Changes
                        </button>

                    </form>

                <?php endforeach; ?>

            </section>

        </main>

    </div>

    <script>
        const newType = document.getElementById('new_type');
        const newCapacity = document.getElementById('new_capacity');

        newType.addEventListener('change', function() {
            if (this.value === 'seat') {
                newCapacity.value = 1;
                newCapacity.readOnly = true;
            } else {
                newCapacity.readOnly = false;
                if (Number(newCapacity.value) < 2) {
                    newCapacity.value = 2;
                }
            }
        });

        newType.dispatchEvent(new Event('change'));
    </script>

</body>

</html>