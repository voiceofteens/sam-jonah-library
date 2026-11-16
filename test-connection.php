<?php
require_once __DIR__ . '/includes/authentication.php';
requireRole('admin');
require_once __DIR__ . '/config/database.php';

echo "Database connection successful!";
