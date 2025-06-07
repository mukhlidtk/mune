<?php
// Sample database configuration file
// Use environment variables or change the values below to match your database

// Database credentials
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_USER', getenv('DB_USER') ?: 'db_user');
define('DB_PASS', getenv('DB_PASS') ?: 'db_password');
define('DB_NAME', getenv('DB_NAME') ?: 'db_name');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die('Connection failed: ' . $conn->connect_error);
}

// Use UTF-8 encoding
$conn->set_charset('utf8mb4');
?>
