<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

/* Only allow cancellation if the order is still "processing". 
   If the farmer already shipped it, it cannot be cancelled. */
$conn->query("UPDATE orders SET order_status = 'cancelled' WHERE id = $order_id AND buyer_id = $buyer_id AND order_status = 'processing'");

header("Location: orders.php?msg=cancelled");
exit();
?>