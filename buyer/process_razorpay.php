<?php
session_start();
include("../config/db.php");
include("../config/razorpay_keys.php");

// STRICT ROLE CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer'){
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST'){
    echo json_encode(['status' => 'error', 'message' => 'Invalid Request']);
    exit();
}

$buyer_id = $_SESSION['user_id'];
$buyer_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Buyer';
$buyer_email = isset($_SESSION['email']) ? $_SESSION['email'] : 'buyer@farmogram.com';
$buyer_phone = isset($_SESSION['phone']) ? $_SESSION['phone'] : '9999999999';

// Calculate Total from Cart
$total = 0;
if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0){
    foreach($_SESSION['cart'] as $id => $item){
        $total += $item['price'] * $item['quantity'];
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Cart is empty.']);
    exit();
}

if(!empty($_POST['promo_code'])) {
    $p_code = strtoupper($conn->real_escape_string($_POST['promo_code']));
    
    // Check if buyer has already used this code
    $check_usage = $conn->query("SELECT id FROM orders WHERE buyer_id = $buyer_id AND promo_code = '$p_code' AND order_status != 'cancelled'");
    if($check_usage && $check_usage->num_rows == 0) {
        $check_promo = $conn->query("SELECT discount_percent FROM promo_codes WHERE code = '$p_code' AND status = 'active' AND valid_until > NOW()");
        if($check_promo && $check_promo->num_rows > 0) {
            $percent = $check_promo->fetch_assoc()['discount_percent'];
            $discount_value = round((($total * $percent) / 100), 2);
            $total = $total - $discount_value;
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'You have already used this promo code!']);
        exit();
    }
}

// 1. Verify Stock for ALL items before generating order
$stock_error = false;
$error_msg = "";

foreach($_SESSION['cart'] as $crop_id => $item) {
    $qty_needed = (int)$item['quantity'];
    $check_stock = $conn->query("SELECT crop_name, quantity FROM crops WHERE id = $crop_id");
    
    if($check_stock && $check_stock->num_rows > 0) {
        $stock_data = $check_stock->fetch_assoc();
        if($stock_data['quantity'] < $qty_needed) {
            $stock_error = true;
            $error_msg = "Sorry! Only " . $stock_data['quantity'] . " kg of " . $stock_data['crop_name'] . " is available.";
            break;
        }
    }
}

if($stock_error) {
    echo json_encode(['status' => 'error', 'message' => $error_msg]);
    exit();
}

// 2. Generate Razorpay Order
$receipt_id = 'farmogram_rcpt_' . rand(1000, 9999) . '_' . time();
$amount_in_paise = round($total * 100);

$api_url = "https://api.razorpay.com/v1/orders";
$data = [
    'receipt'         => $receipt_id,
    'amount'          => $amount_in_paise, // amount in the smallest currency unit
    'currency'        => 'INR',
    'payment_capture' => 1 // auto capture
];

$ch = curl_init($api_url);
curl_setopt($ch, CURLOPT_USERPWD, $razorpay_key_id . ':' . $razorpay_key_secret);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);

// Since XAMPP sometimes has SSL Cert issues with cURL locally, disable SSL verify for test env ONLY
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if($http_code == 200){
    // Order Created Successfully
    $razorpay_order = json_decode($response, true);
    
    echo json_encode([
        'status' => 'success',
        'order_id' => $razorpay_order['id'],
        'amount' => $amount_in_paise,
        'currency' => 'INR',
        'key' => $razorpay_key_id,
        'name' => $buyer_name,
        'email' => $buyer_email,
        'phone' => $buyer_phone,
        'is_mock' => false
    ]);
} else {
    // API Error / Offline Fallback: Generate Mock Razorpay Order for presentation
    $mock_order_id = 'order_mock_' . rand(100000, 999999) . '_' . time();
    echo json_encode([
        'status' => 'success',
        'order_id' => $mock_order_id,
        'amount' => $amount_in_paise,
        'currency' => 'INR',
        'key' => $razorpay_key_id,
        'name' => $buyer_name,
        'email' => $buyer_email,
        'phone' => $buyer_phone,
        'is_mock' => true
    ]);
}
?>
