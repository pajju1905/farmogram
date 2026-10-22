<?php
session_start();

// Need DB connection to sync removal with database
include("../config/db.php");

// Check if an ID was passed in the URL
if(isset($_GET['id'])) {
    $id = $_GET['id'];
    
    // If the item exists in the cart session, remove it
    if(isset($_SESSION['cart'][$id])) {
        unset($_SESSION['cart'][$id]);
        
        // ==========================================
        // NEW: SYNC REMOVAL TO DATABASE
        // ==========================================
        if(isset($_SESSION['user_id'])) {
            $uid = $_SESSION['user_id'];
            $cart_json = $conn->real_escape_string(json_encode($_SESSION['cart']));
            $conn->query("UPDATE users SET cart_data = '$cart_json' WHERE id = $uid");
        }
    }
}

// Redirect back to the cart page seamlessly
header("Location: cart.php");
exit();
?>