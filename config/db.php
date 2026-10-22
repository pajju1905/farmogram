<?php
// Local development defaults. Override with environment variables where supported.
$host = getenv('DB_HOST') ?: "localhost";
$user = getenv('DB_USER') ?: "root";
$pass = getenv('DB_PASSWORD') ?: "";
$db = getenv('DB_NAME') ?: "farmogram";

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    error_log("Farmogram database connection failed: " . $conn->connect_error);
    die("Database connection failed. Check your local configuration.");
}
?>
