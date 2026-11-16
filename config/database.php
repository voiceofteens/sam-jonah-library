
<?php

$host = "localhost";
$dbname = "sam_jonah_library";
$username = "root";
$password = "";

try {

    $conn = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $conn->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $conn->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

    $conn->setAttribute(
        PDO::ATTR_EMULATE_PREPARES,
        false
    );

    // Accra is UTC+0 with no DST; keep MySQL NOW() aligned with PHP time.
    $conn->exec("SET time_zone = '+00:00'");

} catch (PDOException $e) {

    error_log($e->getMessage());

    die("Unable to connect to the library database.");

}

?>
