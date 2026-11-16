<?php
// Run once from the XAMPP shell:  php database/create-admin.php "Full Name" email password
if (PHP_SAPI !== 'cli') { http_response_code(403); exit('CLI only.'); }
if ($argc < 4 || strlen($argv[3]) < 8) { exit("Usage: php create-admin.php \"Full Name\" email password(8+ chars)\n"); }
require __DIR__ . '/../config/database.php';
$conn->prepare("INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, 'admin')")
     ->execute([$argv[1], $argv[2], password_hash($argv[3], PASSWORD_DEFAULT)]);
echo "Admin created.\n";
