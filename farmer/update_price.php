<?php
session_start();
include("../config/db.php");

// Strict Role Check
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer'){
    echo "Unauthorized";
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Get farmer verification status
$status_query = $conn->query("SELECT verification_status FROM users WHERE id = $farmer_id");
$user_status = $status_query->fetch_assoc();
$verification_status = $user_status['verification_status'] ?? 'unverified';

if($verification_status !== 'verified'){
    echo "Unverified Account";
    exit();
}

// Sanitize inputs
$crop_id = isset($_POST['crop_id']) ? (int)$_POST['crop_id'] : 0;
$price = isset($_POST['price']) ? (float)$_POST['price'] : 0.0;

// Ensure crop belongs to this farmer and execute update using prepared statements
$stmt = $conn->prepare("UPDATE crops SET market_price = ? WHERE id = ? AND farmer_id = ?");
$stmt->bind_param("dii", $price, $crop_id, $farmer_id);

if ($stmt->execute()) {
    echo "Updated";
} else {
    echo "Error updating price";
}
$stmt->close();
?>
