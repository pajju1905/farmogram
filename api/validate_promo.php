<?php
session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['code']) && isset($_SESSION['user_id'])) {
    $code = strtoupper(trim($_POST['code']));
    $buyer_id = $_SESSION['user_id'];
    
    // Check if buyer has already used this code
    $check_usage = $conn->query("SELECT id FROM orders WHERE buyer_id = $buyer_id AND promo_code = '$code' AND order_status != 'cancelled'");
    if($check_usage && $check_usage->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'You have already used this promo code!']);
        exit();
    }
    
    $stmt = $conn->prepare("SELECT discount_percent FROM promo_codes WHERE code = ? AND status = 'active' AND valid_until > NOW()");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        echo json_encode(['status' => 'success', 'discount_percent' => $row['discount_percent']]);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Invalid or expired promo code']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No code provided']);
}
?>
