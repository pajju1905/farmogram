<?php
session_start();
include("../config/db.php");

// Strictly for Buyers
if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] == 'POST'){
    $buyer_id = $_SESSION['user_id'];
    $crop_id = (int)$_POST['crop_id'];
    $message = $conn->real_escape_string($_POST['message']);

    // 1. Find the exact Farmer who owns this crop (and verify they are verified)
    $crop_res = $conn->query("
        SELECT c.farmer_id 
        FROM crops c 
        JOIN users u ON c.farmer_id = u.id 
        WHERE c.id = $crop_id AND u.verification_status = 'verified'
    ");
    if($crop_res && $crop_res->num_rows > 0) {
        $farmer_id = $crop_res->fetch_assoc()['farmer_id'];

        // 2. Prevent Duplicates: Check if a chat already exists for this exact buyer and crop
        $check_query = $conn->query("SELECT id FROM enquiries WHERE buyer_id = $buyer_id AND crop_id = $crop_id");
        
        if($check_query->num_rows > 0){
            // A chat exists! Grab the old ID so we can just add a new bubble to it.
            $enquiry_id = $check_query->fetch_assoc()['id'];
        } else {
            // First time chatting about this crop. Create a new chat room and ASSIGN the Farmer!
            $conn->query("INSERT INTO enquiries (crop_id, buyer_id, farmer_id) VALUES ($crop_id, $buyer_id, $farmer_id)");
            $enquiry_id = $conn->insert_id;
        }

        // 3. Insert the actual chat bubble (is_read defaults to 0 for the unread badge)
        $conn->query("INSERT INTO enquiry_messages (enquiry_id, sender_id, message, is_read) VALUES ($enquiry_id, $buyer_id, '$message', 0)");

        // 4. Redirect the buyer directly into the chat room!
        header("Location: my_enquiries.php?id=$enquiry_id");
        exit();
    } else {
        die("Error: Crop not found.");
    }
}
?>