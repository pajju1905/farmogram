<?php
session_start();
include("../config/db.php");
include("../config/push_engine.php");
include("../config/razorpay_keys.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer'){
    header("Location: ../auth/login.php");
    exit();
}

if($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['razorpay_payment_id']) || !isset($_POST['razorpay_order_id']) || !isset($_POST['razorpay_signature'])){
    die("Invalid Security Request.");
}

$success = true;
$error = "Payment Failed";

$buyer_id = $_SESSION['user_id'];
$razorpay_payment_id = $_POST['razorpay_payment_id'];
$razorpay_order_id = $_POST['razorpay_order_id'];
$razorpay_signature = $_POST['razorpay_signature'];

// Razorpay HMAC SHA256 Verification Verification
if (strpos($razorpay_order_id, 'order_mock_') === 0) {
    // Simulated order, auto-approve signature
    $generated_signature = $razorpay_signature;
} else {
    $generated_signature = hash_hmac('sha256', $razorpay_order_id . "|" . $razorpay_payment_id, $razorpay_key_secret);
}

if ($generated_signature !== $razorpay_signature) {
    $success = false;
    $error = "Razorpay Signature Verification Failed (Possible Tampering!)";
}

if ($success === true) {
    
    // 1. Double check stock and seller verification validity before confirming DB
    $total = 0;
    if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0){
        foreach($_SESSION['cart'] as $crop_id => $item){
            $qty_needed = (int)$item['quantity'];
            $check_stock = $conn->query("SELECT c.crop_name, c.quantity, u.verification_status FROM crops c JOIN users u ON c.farmer_id = u.id WHERE c.id = $crop_id");
            if($check_stock && $check_stock->num_rows > 0) {
                $stock_data = $check_stock->fetch_assoc();
                if($stock_data['verification_status'] !== 'verified') {
                    die("Payment failed security gate: The seller for " . htmlspecialchars($stock_data['crop_name']) . " is not verified.");
                }
                if($stock_data['quantity'] < $qty_needed) {
                    die("Payment failed security gate: Insufficient stock for " . htmlspecialchars($stock_data['crop_name']) . ".");
                }
            } else {
                die("Payment failed security gate: One of the crops in your cart is no longer available.");
            }
            $total += $item['price'] * $item['quantity'];
        }
    } else {
        die("Cart is empty but payment was made. Please contact support.");
    }
    
    // 2. Promo Logic Processing
    $promo_code_used = "NULL";
    $discount_value = 0;
    
    if(!empty($_POST['applied_promo_code'])) {
        $p_code = strtoupper($conn->real_escape_string($_POST['applied_promo_code']));
        
        $check_usage = $conn->query("SELECT id FROM orders WHERE buyer_id = $buyer_id AND promo_code = '$p_code' AND order_status != 'cancelled'");
        if($check_usage && $check_usage->num_rows == 0) {
            $check_promo = $conn->query("SELECT discount_percent FROM promo_codes WHERE code = '$p_code' AND status = 'active' AND valid_until > NOW()");
            if($check_promo && $check_promo->num_rows > 0) {
                $percent = $check_promo->fetch_assoc()['discount_percent'];
                $discount_value = round((($total * $percent) / 100), 2);
                $promo_code_used = "'$p_code'";
                $total = $total - $discount_value;
            }
        }
    }

    // 3. Insert Order
    $d_name = isset($_POST['full_name']) ? $conn->real_escape_string($_POST['full_name']) : '';
    $d_address = isset($_POST['address']) ? $conn->real_escape_string($_POST['address']) : '';
    $d_city = isset($_POST['city']) ? $conn->real_escape_string($_POST['city']) : '';
    $d_pin = isset($_POST['pin_code']) ? $conn->real_escape_string($_POST['pin_code']) : '';
    $d_phone = isset($_POST['phone']) ? $conn->real_escape_string($_POST['phone']) : '';

    $insert_order = "INSERT INTO orders (buyer_id, total_amount, discount_applied, promo_code, payment_method, razorpay_order_id, razorpay_payment_id, payment_status, order_status, delivery_name, delivery_address, delivery_city, delivery_pin, delivery_phone) 
                     VALUES ($buyer_id, $total, $discount_value, $promo_code_used, 'razorpay', '$razorpay_order_id', '$razorpay_payment_id', 'paid', 'processing', '$d_name', '$d_address', '$d_city', '$d_pin', '$d_phone')";
    
    if($conn->query($insert_order)){
        $order_id = $conn->insert_id;
        
        foreach($_SESSION['cart'] as $crop_id => $item) {
            $qty = (int)$item['quantity'];
            $price = (float)$item['price'];
            
            // Insert Item
            $conn->query("INSERT INTO order_items (order_id, crop_id, quantity, price) VALUES ($order_id, $crop_id, $qty, $price)");
            
            // Deduct from Farmer's Inventory
            $conn->query("UPDATE crops SET quantity = quantity - $qty WHERE id = $crop_id");

            $farmer_q = $conn->query("SELECT farmer_id, crop_name FROM crops WHERE id = $crop_id");
            if($farmer_q && $farmer_row = $farmer_q->fetch_assoc()) {
                $f_id = $farmer_row['farmer_id'];
                $c_name = $farmer_row['crop_name'];
                $msg = "New Order (#ORD-$order_id) for $qty kg of $c_name!";
                $conn->query("INSERT INTO notifications(user_id, message) VALUES($f_id, '$msg')");
                sendMobilePush($f_id, "New Order Received! 🚀", $msg);
            }
        }

        if(isset($_POST['save_address']) && $_POST['save_address'] == '1') {
            $conn->query("UPDATE users SET saved_address = '$d_address', saved_city = '$d_city', saved_pin = '$d_pin', saved_phone = '$d_phone' WHERE id = $buyer_id");
        }
        
        if(isset($_POST['save_card']) && $_POST['save_card'] == '1') {
            $c_num = isset($_POST['card_number']) ? $conn->real_escape_string($_POST['card_number']) : '';
            $c_exp = isset($_POST['card_expiry']) ? $conn->real_escape_string($_POST['card_expiry']) : '';
            $c_name = isset($_POST['card_name']) ? $conn->real_escape_string($_POST['card_name']) : '';
            $conn->query("UPDATE users SET saved_card_number = '$c_num', saved_card_expiry = '$c_exp', saved_card_name = '$c_name' WHERE id = $buyer_id");
        }

        // Clear Cart & Redirect to success
        unset($_SESSION['cart']);
        header("Location: orders.php?payment=success");
        exit();
    } else {
        die("Database error. Order failed to save although payment might have succeeded. Contact support.");
    }
} else {
    // Payment verified as failed or tampered
    echo "<div style='color: red; text-align: center; font-family: sans-serif; padding: 50px;'><h1>⚠️ Payment Security Error</h1><p>$error</p><a href='checkout.php'>Return to Checkout</a></div>";
}
?>
