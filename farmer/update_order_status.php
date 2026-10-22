<?php
session_start();
include("../config/db.php");
include_once("../config/push_engine.php"); // Load the Push Engine

// STRICT ROLE CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer'){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];

// Get farmer verification status
$status_query = $conn->query("SELECT verification_status FROM users WHERE id = $farmer_id");
$user_status = $status_query->fetch_assoc();
$verification_status = $user_status['verification_status'] ?? 'unverified';

if($verification_status !== 'verified'){
    header("Location: orders.php?error=unverified");
    exit();
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
$status = isset($_POST['status']) ? trim($_POST['status']) : '';

// Validate status parameter
if (!in_array($status, ['processing', 'shipped', 'delivered', 'cancelled'])) {
    header("Location: orders.php?error=invalid_status");
    exit();
}

// Verify that this farmer actually has a crop in this order
$check_stmt = $conn->prepare("
    SELECT 1 FROM order_items oi
    JOIN crops c ON oi.crop_id = c.id
    WHERE oi.order_id = ? AND c.farmer_id = ?
");
$check_stmt->bind_param("ii", $order_id, $farmer_id);
$check_stmt->execute();
$check_result = $check_stmt->get_result();

if ($check_result->num_rows === 0) {
    // Unauthorized access to this order
    $check_stmt->close();
    header("Location: orders.php?error=unauthorized_order");
    exit();
}
$check_stmt->close();

/* Update order status in the database using prepared statement */
$update_stmt = $conn->prepare("UPDATE orders SET order_status = ? WHERE id = ?");
$update_stmt->bind_param("si", $status, $order_id);
$update_stmt->execute();
$update_stmt->close();

// ==============================================================
// 🔔 PUSH NOTIFICATION: NOTIFY BUYER OF SHIPPING STATUS
// ==============================================================
if($status == 'shipped' || $status == 'delivered') {
    // Find out which buyer owns this order
    $get_buyer = $conn->query("SELECT buyer_id FROM orders WHERE id = $order_id");
    
    if($get_buyer && $get_buyer->num_rows > 0) {
        $b_data = $get_buyer->fetch_assoc();
        $target_buyer_id = $b_data['buyer_id'];
        
        // Customize the message based on the status
        $msg_title = ($status == 'shipped') ? "🚚 Order Shipped!" : "✅ Order Delivered!";
        $msg_body = "Your order #ORD-$order_id has been marked as $status by the farmer.";
        
        // Trigger the pop-up on the Buyer's device!
        sendMobilePush($target_buyer_id, $msg_title, $msg_body);
        
        $notif_stmt = $conn->prepare("INSERT INTO notifications(user_id, message) VALUES(?, ?)");
        $notif_stmt->bind_param("is", $target_buyer_id, $msg_body);
        $notif_stmt->execute();
        $notif_stmt->close();
    }
}
// ==============================================================

header("Location: orders.php?msg=status_updated");
exit();
?>
