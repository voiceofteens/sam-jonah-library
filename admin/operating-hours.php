<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/authentication.php';

requireRole('admin');

$message = '';
$error = '';

$days = [
    1 => 'Sunday',
    2 => 'Monday',
    3 => 'Tuesday',
    4 => 'Wednesday',
    5 => 'Thursday',
    6 => 'Friday',
    7 => 'Saturday'
];

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

        $conn->beginTransaction();

        foreach ($days as $dayNumber => $dayName) {
            $isClosed = isset($_POST['closed'][$dayNumber]) ? 1 : 0;

            $opening = $_POST['opening'][$dayNumber] ?? '';
            $closing = $_POST['closing'][$dayNumber] ?? '';

            if (!$isClosed) {
                if (
                    !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $opening) ||
                    !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $closing)
                ) {
                    throw new Exception("Enter valid opening and closing times for {$dayName}.");
                }

                if ($opening >= $closing) {
                    throw new Exception("Closing time must be later than opening time for {$dayName}.");
                }
            } else {
                $opening = null;
                $closing = null;
            }

            $stmt = $conn->prepare("
                INSERT INTO operating_hours
                (day_of_week, opening_time, closing_time, is_closed)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    opening_time = VALUES(opening_time),
                    closing_time = VALUES(closing_time),
                    is_closed = VALUES(is_closed)
            ");

            $stmt->execute([
                $dayNumber,
                $opening,
                $closing,
                $isClosed
            ]);
        }

        $conn->commit();

        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

        $message = "Library operating hours updated successfully.";

    } catch (PDOException $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        error_log($e->getMessage());
        $error = "A database error occurred while saving operating hours.";

    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }

        $error = $e->getMessage();
    }
}

$stmt = $conn->query("
    SELECT day_of_week, opening_time, closing_time, is_closed
    FROM operating_hours
");

$hours = [];

foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $hours[(int) $row['day_of_week']] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operating Hours | Sam Jonah Library</title>
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
            <a href="facilities.php">Manage Facilities</a>
            <a href="operating-hours.php" class="active">Operating Hours</a>
            <a href="bookings.php">All Bookings</a>
        </nav>

        <div class="sidebar-footer">
            <a href="../logout.php">Sign Out</a>
        </div>
    </aside>

    <main class="main-content">

        <h1>Library Operating Hours</h1>
        <p>Set the days and times when the library accepts bookings.</p>

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

        <form method="POST" class="dashboard-section">

            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

            <div class="table-container">

                <table class="data-table">

                    <thead>
                        <tr>
                            <th>Day</th>
                            <th>Opening Time</th>
                            <th>Closing Time</th>
                            <th>Closed</th>
                        </tr>
                    </thead>

                    <tbody>

                        <?php foreach ($days as $number => $dayName): ?>

                            <?php
                            $day = $hours[$number] ?? null;
                            $isClosed = !$day || (int) $day['is_closed'] === 1;
                            ?>

                            <tr>
                                <td><?= htmlspecialchars($dayName) ?></td>

                                <td>
                                    <input
                                        type="time"
                                        name="opening[<?= $number ?>]"
                                        value="<?= $day && $day['opening_time']
                                            ? substr($day['opening_time'], 0, 5)
                                            : '09:00' ?>"
                                        <?= $isClosed ? 'disabled' : '' ?>
                                    >
                                </td>

                                <td>
                                    <input
                                        type="time"
                                        name="closing[<?= $number ?>]"
                                        value="<?= $day && $day['closing_time']
                                            ? substr($day['closing_time'], 0, 5)
                                            : '21:00' ?>"
                                        <?= $isClosed ? 'disabled' : '' ?>
                                    >
                                </td>

                                <td>
                                    <input
                                        type="checkbox"
                                        name="closed[<?= $number ?>]"
                                        value="1"
                                        class="closed-checkbox"
                                        data-day="<?= $number ?>"
                                        <?= $isClosed ? 'checked' : '' ?>
                                    >
                                </td>
                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

            <button type="submit" class="btn-primary">
                Save Operating Hours
            </button>

        </form>

    </main>

</div>

<script>
document.querySelectorAll('.closed-checkbox').forEach(function (checkbox) {
    checkbox.addEventListener('change', function () {
        const day = this.dataset.day;

        document.querySelectorAll(
            'input[name="opening[' + day + ']"], input[name="closing[' + day + ']"]'
        ).forEach(function (field) {
            field.disabled = checkbox.checked;
        });
    });
});
</script>

</body>
</html>
