<?php

// Local (XAMPP) defaults. On Render, set these as environment variables instead.
$host = getenv('DB_HOST') ?: 'localhost';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'sam_jonah_library';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASS') !== false ? getenv('DB_PASS') : '';

// Cloud databases such as TiDB Cloud require an encrypted (SSL) connection.
$options = [];
if (getenv('DB_SSL') === 'true') {
    $options[PDO::MYSQL_ATTR_SSL_CA] = '/etc/ssl/certs/ca-certificates.crt';
    $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
}

try {

    $conn = new PDO(
        "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4",
        $username,
        $password,
        $options
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
