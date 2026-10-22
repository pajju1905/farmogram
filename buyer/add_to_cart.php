<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer'){
    header("Location: ../auth/login.php");
    exit();
}

$crop_id = isset($_POST['crop_id']) ? (int)$_POST['crop_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;
$booking_type = isset($_POST['booking_type']) ? $_POST['booking_type'] : 'full';

$result = $conn->query("
    SELECT crops.* 
    FROM crops 
    JOIN users ON crops.farmer_id = users.id 
    WHERE crops.id = $crop_id AND users.verification_status = 'verified'
");
if (!$result || $result->num_rows == 0) {
    die("Crop not found or farmer is not verified.");
}

$product = $result->fetch_assoc();

if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

// Calculate the price based on if it's an advance booking or full purchase
$final_price = $product['market_price'];
$cart_name = $product['crop_name'];

if ($booking_type == 'advance') {
    // 20% advance
    $final_price = $product['market_price'] * 0.20;
    $cart_name = $product['crop_name'] . " (Pre-Book Advance)";
}

// Add or update item in session cart
if(isset($_SESSION['cart'][$crop_id])){
    $_SESSION['cart'][$crop_id]['quantity'] += $quantity;
} else {
    $_SESSION['cart'][$crop_id] = [
        "name" => $cart_name,
        "price" => $final_price,
        "quantity" => $quantity,
        "type" => $booking_type,
        "full_price" => $product['market_price']
    ];
}

// ==========================================
// NEW: SYNC CART UPDATES TO DATABASE
// ==========================================
if(isset($_SESSION['user_id'])) {
    $uid = $_SESSION['user_id'];
    $cart_json = $conn->real_escape_string(json_encode($_SESSION['cart']));
    $conn->query("UPDATE users SET cart_data = '$cart_json' WHERE id = $uid");
}

header("Location: cart.php");
exit();
?>