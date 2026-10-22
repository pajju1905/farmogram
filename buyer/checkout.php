<?php
session_start();
include("../config/db.php");
include("../config/push_engine.php");

// STRICT ROLE CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer'){
    header("Location: ../auth/login.php");
    exit();
}

// Fetch user profile image
$profile_image = '';
if (isset($conn)) {
    $profile_img_q = $conn->query("SELECT profile_image FROM users WHERE id = " . $_SESSION['user_id']);
    if($profile_img_q && $profile_img_row = $profile_img_q->fetch_assoc()){
        $profile_image = $profile_img_row['profile_image'] ?? '';
    }
}

$buyer_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Buyer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$msg = "";

$user_data_query = $conn->query("SELECT * FROM users WHERE id = $buyer_id");
$user_data = $user_data_query->fetch_assoc();
$saved_address = $user_data['saved_address'] ?? '';
$saved_city = $user_data['saved_city'] ?? '';
$saved_pin = $user_data['saved_pin'] ?? '';
$saved_phone = $user_data['saved_phone'] ?? '';
$saved_card_number = $user_data['saved_card_number'] ?? '';
$saved_card_expiry = $user_data['saved_card_expiry'] ?? '';
$saved_card_name = $user_data['saved_card_name'] ?? '';

// Calculate Total from Cart
$total = 0;
if(isset($_SESSION['cart']) && count($_SESSION['cart']) > 0){
    foreach($_SESSION['cart'] as $id => $item){
        $total += $item['price'] * $item['quantity'];
    }
} else {
    header("Location: marketplace.php");
    exit();
}

// =======================================================
// ORDER PLACEMENT & STRICT INVENTORY CHECK
// =======================================================
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['checkout_action'])) {
    
    // 1. Verify Stock for ALL items before doing anything
    $stock_error = false;
    $error_msg = "";
    
    foreach($_SESSION['cart'] as $crop_id => $item) {
        $qty_needed = (int)$item['quantity'];
        // Strict check: join with users table to verify the farmer's status is 'verified'
        $check_stock = $conn->query("SELECT c.crop_name, c.quantity, u.verification_status FROM crops c JOIN users u ON c.farmer_id = u.id WHERE c.id = $crop_id");
        
        if($check_stock && $check_stock->num_rows > 0) {
            $stock_data = $check_stock->fetch_assoc();
            if($stock_data['verification_status'] !== 'verified') {
                $stock_error = true;
                $error_msg = "The seller for " . $stock_data['crop_name'] . " is not verified. This crop cannot be purchased.";
                break;
            }
            if($stock_data['quantity'] < $qty_needed) {
                $stock_error = true;
                $error_msg = "Sorry! Only " . $stock_data['quantity'] . " kg of " . $stock_data['crop_name'] . " is available.";
                break;
            }
        } else {
            $stock_error = true;
            $error_msg = "One of the crops in your cart is no longer available.";
            break;
        }
    }

    // 2. If Stock is Good, Process Order & Deduct Stock
    if(!$stock_error) {
        $d_name = isset($_POST['full_name']) ? $conn->real_escape_string($_POST['full_name']) : '';
        $d_address = isset($_POST['address']) ? $conn->real_escape_string($_POST['address']) : '';
        $d_city = isset($_POST['city']) ? $conn->real_escape_string($_POST['city']) : '';
        $d_pin = isset($_POST['pin_code']) ? $conn->real_escape_string($_POST['pin_code']) : '';
        $d_phone = isset($_POST['phone']) ? $conn->real_escape_string($_POST['phone']) : '';

        $payment_method = isset($_POST['payment_method']) ? $conn->real_escape_string($_POST['payment_method']) : 'cod';
        
        $promo_code_used = "NULL";
        $discount_value = 0;
        
        if(!empty($_POST['applied_promo_code'])) {
            $p_code = strtoupper($conn->real_escape_string($_POST['applied_promo_code']));
            
            // Re-verify usage security
            $check_usage = $conn->query("SELECT id FROM orders WHERE buyer_id = $buyer_id AND promo_code = '$p_code' AND order_status != 'cancelled'");
            if($check_usage && $check_usage->num_rows == 0) {
                $check_promo = $conn->query("SELECT discount_percent FROM promo_codes WHERE code = '$p_code' AND status = 'active' AND valid_until > NOW()");
                if($check_promo && $check_promo->num_rows > 0) {
                    $percent = $check_promo->fetch_assoc()['discount_percent'];
                    $discount_value = round((($total * $percent) / 100), 2);
                    $promo_code_used = "'$p_code'";
                    $total = $total - $discount_value;
                }
            } else {
                $msg = "<div class='alert-error'>Promo code already used!</div>";
                $stock_error = true;
            }
        }

        $insert_order = "INSERT INTO orders (buyer_id, total_amount, discount_applied, promo_code, payment_method, order_status, delivery_name, delivery_address, delivery_city, delivery_pin, delivery_phone) 
                         VALUES ($buyer_id, $total, $discount_value, $promo_code_used, '$payment_method', 'processing', '$d_name', '$d_address', '$d_city', '$d_pin', '$d_phone')";
        
        if($conn->query($insert_order)){
            $order_id = $conn->insert_id;
            
            // Save address if checked
            if(isset($_POST['save_address']) && $_POST['save_address'] == '1') {
                $conn->query("UPDATE users SET saved_address = '$d_address', saved_city = '$d_city', saved_pin = '$d_pin', saved_phone = '$d_phone' WHERE id = $buyer_id");
            }
            
            foreach($_SESSION['cart'] as $crop_id => $item) {
                $qty = (int)$item['quantity'];
                $price = (float)$item['price'];
                
                // Insert Item
                $conn->query("INSERT INTO order_items (order_id, crop_id, quantity, price) VALUES ($order_id, $crop_id, $qty, $price)");
                
                // Deduct from Farmer's Inventory
                $conn->query("UPDATE crops SET quantity = quantity - $qty WHERE id = $crop_id");

                // Fetch farmer_id for push notification
                $farmer_q = $conn->query("SELECT farmer_id, crop_name FROM crops WHERE id = $crop_id");
                if($farmer_q && $farmer_row = $farmer_q->fetch_assoc()) {
                    $f_id = $farmer_row['farmer_id'];
                    $c_name = $farmer_row['crop_name'];
                    $msg = "New Order (#ORD-$order_id) for $qty kg of $c_name!";
                    $conn->query("INSERT INTO notifications(user_id, message) VALUES($f_id, '$msg')");
                    sendMobilePush($f_id, "New Order Received! 🚀", $msg);
                }
            }

            // Clear Cart & Redirect
            unset($_SESSION['cart']);
            header("Location: orders.php");
            exit();
        }
    } else {
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> $error_msg Please reduce the quantity in your cart.</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Secure Checkout | Farmogram</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('farmogram_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        :root {
            --radius-xl: 32px;
            --radius-lg: 24px;
            --radius-md: 16px;
            --shadow-subtle: 0 8px 30px rgba(0,0,0,0.04);
            --shadow-hover: 0 15px 35px rgba(0,0,0,0.08);
            --app-nav-height: 75px;
        }

        body { margin: 0; overflow: hidden; background-color: var(--bg-light); color: var(--text-main); font-family: 'Inter', sans-serif; transition: 0.3s; }
        .app-layout { display: flex; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { flex-shrink: 0; }
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1400px; margin: 0 auto; width: 100%; }

        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 25px; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }

        .checkout-grid { display: grid; grid-template-columns: 1.6fr 1fr; gap: 30px; align-items: start; }
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 35px; position: relative; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        .section-title { font-size: 1.4rem; font-weight: 900; color: var(--text-main); margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: span 2; }
        .form-label { display: block; font-weight: 800; margin-bottom: 8px; font-size: 0.95rem; color: var(--text-main); transition: 0.3s; }
        .form-input { width: 100%; padding: 16px 20px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); border-radius: var(--radius-md); outline: none; font-size: 1rem; font-family: inherit; font-weight: 600; transition: 0.3s; }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }

        /* Dynamic Payment Methods */
        .payment-methods { display: flex; flex-direction: column; gap: 15px; margin-bottom: 30px; }
        .pay-method-wrapper { border: 2px solid var(--border-color); border-radius: var(--radius-md); background: var(--bg-light); transition: 0.3s; overflow: hidden; }
        .pay-method-wrapper.selected { border-color: var(--primary); background: rgba(16, 185, 129, 0.05); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.1); }
        
        .pay-method-label { display: flex; align-items: center; gap: 15px; padding: 20px; cursor: pointer; transition: 0.3s; margin: 0; }
        .pay-method-label input[type="radio"] { width: 20px; height: 20px; accent-color: var(--primary); cursor: pointer; }
        .pay-method-icon { font-size: 1.8rem; color: var(--primary); width: 40px; text-align: center; }
        .pay-method-text { font-size: 1.1rem; font-weight: 800; color: var(--text-main); }
        .pay-method-sub { font-size: 0.85rem; color: var(--text-muted); font-weight: 500; display: block; margin-top: 4px; }
        
        .pay-details { display: none; padding: 0 20px 20px 20px; border-top: 1px dashed var(--border-color); margin-top: 5px; padding-top: 20px; }
        .pay-method-wrapper.selected .pay-details { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

        .order-summary { position: sticky; top: 0; }
        .summary-item { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px dashed var(--border-color); }
        .summary-item:last-child { border-bottom: none; }
        .item-name { font-size: 1rem; font-weight: 800; color: var(--text-main); text-transform: capitalize; }
        .item-qty { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .item-price { font-size: 1.1rem; font-weight: 900; color: var(--text-main); }
        
        .totals-row { display: flex; justify-content: space-between; margin-top: 20px; color: var(--text-muted); font-size: 1.05rem; font-weight: 600; }
        .grand-total { display: flex; justify-content: space-between; margin-top: 20px; padding-top: 20px; border-top: 2px solid var(--border-color); font-size: 1.6rem; font-weight: 900; color: #10b981; letter-spacing: -0.5px; }
        
        .btn-confirm { background: var(--primary); color: #fff; border: none; padding: 20px; width: 100%; border-radius: var(--radius-md); font-weight: 900; font-size: 1.2rem; cursor: pointer; transition: 0.3s; margin-top: 30px; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-confirm:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

        .trust-badge { text-align: center; margin-top: 25px; padding: 15px; background: rgba(16, 185, 129, 0.05); border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.1); }
        .trust-badge i { font-size: 2rem; color: #10b981; margin-bottom: 10px; }
        .trust-badge p { margin: 0; font-size: 0.9rem; font-weight: 800; color: var(--text-main); }
        .trust-badge span { font-size: 0.8rem; color: var(--text-muted); font-weight: 600; }

        /* Loader Overlay */
        .loader-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.85); backdrop-filter: blur(10px); z-index: 9999; display: none; flex-direction: column; justify-content: center; align-items: center; color: #fff; opacity: 0; transition: opacity 0.3s; }
        .loader-overlay.active { display: flex; opacity: 1; }
        
        .spinner { width: 70px; height: 70px; border: 5px solid rgba(255,255,255,0.2); border-top-color: #10b981; border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 25px; }
        @keyframes spin { 100% { transform: rotate(360deg); } }
        
        .success-check { display: none; font-size: 5rem; color: #10b981; margin-bottom: 20px; animation: popIn 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275); }
        @keyframes popIn { 0% { transform: scale(0); opacity: 0; } 100% { transform: scale(1); opacity: 1; } }

        .loader-text { font-size: 1.5rem; font-weight: 900; letter-spacing: -0.5px; }
        .loader-sub { font-size: 1rem; color: #94a3b8; margin-top: 10px; font-weight: 500; }

        /* Sidebar Desktop Logo */
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }
        .mobile-brand { display: none; align-items: center; gap: 8px; text-decoration: none; }
        .mobile-brand img { width: 30px; height: 30px; object-fit: contain; }
        .mobile-brand span { font-size: 1.3rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); flex-shrink: 0; }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        .logo-light { display: block; width: 35px; height: 35px; object-fit: contain; }
        .logo-dark { display: none; width: 35px; height: 35px; object-fit: contain; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

        /* DARK MODE OVERRIDES */
        [data-theme="dark"] { --bg-light: #0f172a !important; --sidebar-bg: #1e293b !important; --text-main: #f8fafc !important; --text-muted: #94a3b8 !important; --border-color: rgba(255,255,255,0.05) !important; --card-bg: #1e293b !important; }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.85) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .form-input { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .form-input:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        
        [data-theme="dark"] .pay-method-wrapper { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .pay-method-wrapper.selected { background: rgba(16, 185, 129, 0.1) !important; border-color: var(--primary) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        @media (max-width: 1024px) {
            .checkout-grid { grid-template-columns: 1fr; gap: 24px; }
            .order-summary { position: relative; top: 0; }
        }
        @media (max-width: 768px) {
            .app-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
            .sidebar-logo { display: none; } 
            .mobile-brand { display: flex; align-items: center; flex: 1; }
.topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; display: flex; align-items: center; }
            .user-profile { display: none; }
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; }
            .goog-te-gadget-simple { padding: 6px 12px !important; font-size: 0.8rem !important; margin: 0 !important; }
            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); border-right: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; scroll-snap-type: x mandatory; gap: 6px; justify-content: flex-start; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu li { flex: 0 0 auto; scroll-snap-align: center; }
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 20px 15px; flex: 1; overflow-y: auto; }
            .bento-card { padding: 25px 20px; border-radius: var(--radius-lg); }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .form-group.full-width { grid-column: span 1; }
            .btn-confirm { padding: 16px; font-size: 1.1rem; }
        }
        @media (max-width: 480px) {
            .mobile-brand span { display: block; font-size: 1.1rem; }
            .goog-te-gadget-simple { padding: 4px 8px !important; font-size: 0.75rem !important; }
        }

        /* SANDBOX PORTAL STYLES */
        .sandbox-modal-overlay {
            position: fixed;
            top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(15, 23, 42, 0.4);
            backdrop-filter: blur(16px) saturate(180%);
            -webkit-backdrop-filter: blur(16px) saturate(180%);
            z-index: 10000;
            display: none;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .sandbox-modal-overlay.active {
            display: flex;
            opacity: 1;
        }
        .sandbox-modal {
            background: rgba(255, 255, 255, 0.65);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: var(--radius-xl);
            width: 90%;
            max-width: 520px;
            padding: 30px;
            position: relative;
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.1);
            transform: translateY(20px);
            transition: transform 0.3s ease;
            font-family: 'Inter', sans-serif;
            color: var(--text-main);
        }
        [data-theme="dark"] .sandbox-modal {
            background: rgba(30, 41, 59, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.08);
            box-shadow: 0 25px 60px rgba(0, 0, 0, 0.5);
        }
        .sandbox-modal-overlay.active .sandbox-modal {
            transform: translateY(0);
        }
        .sandbox-close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            font-size: 1.8rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: 0.2s;
        }
        .sandbox-close-btn:hover {
            color: var(--text-main);
        }
        .sandbox-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 15px;
        }
        .sandbox-header-icon {
            font-size: 2rem;
            color: var(--primary);
            background: rgba(16, 185, 129, 0.15);
            padding: 12px;
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sandbox-header h3 {
            margin: 0;
            font-size: 1.3rem;
            font-weight: 800;
        }
        .sandbox-header p {
            margin: 5px 0 0 0;
            font-size: 0.85rem;
            color: var(--text-muted);
        }
        .sandbox-body {
            margin-bottom: 25px;
        }
        .sandbox-card-preview {
            background: linear-gradient(135deg, #10b981 0%, #047857 100%);
            border-radius: var(--radius-md);
            padding: 25px;
            color: #fff;
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        .sandbox-card-preview::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 250px;
            height: 250px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        .sandbox-card-chip {
            width: 45px;
            height: 35px;
            background: #f59e0b;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: inset 0 2px 5px rgba(0,0,0,0.1);
        }
        .sandbox-card-number {
            font-size: 1.4rem;
            font-weight: 700;
            letter-spacing: 2px;
            margin-bottom: 20px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        .sandbox-card-footer {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
        }
        .sandbox-card-name {
            font-size: 0.95rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 280px;
        }
        .sandbox-card-expiry-wrap {
            text-align: right;
            flex-shrink: 0;
        }
        .sandbox-card-exp-lbl {
            font-size: 0.65rem;
            text-transform: uppercase;
            opacity: 0.8;
        }
        .sandbox-card-exp-val {
            font-size: 0.95rem;
            font-weight: 600;
        }
        .sandbox-info-box {
            background: rgba(59, 130, 246, 0.08);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-radius: var(--radius-md);
            padding: 15px;
            font-size: 0.85rem;
            line-height: 1.5;
            color: var(--text-main);
            display: flex;
            gap: 10px;
            align-items: flex-start;
        }
        .sandbox-info-box i {
            color: #3b82f6;
            font-size: 1.2rem;
            margin-top: 2px;
        }
        .sandbox-upi-qr {
            text-align: center;
            margin-bottom: 20px;
        }
        .sandbox-qr-code {
            width: 180px;
            height: 180px;
            background: #fff;
            padding: 10px;
            border-radius: var(--radius-md);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 0 auto 15px auto;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .sandbox-qr-placeholder {
            width: 100%;
            height: 100%;
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: #64748b;
            font-size: 0.75rem;
            font-weight: 600;
            gap: 8px;
        }
        .sandbox-qr-placeholder i {
            font-size: 2.2rem;
            color: var(--primary);
        }
        .sandbox-upi-details {
            text-align: center;
            font-weight: 700;
            font-size: 1.05rem;
        }
        .sandbox-footer {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
            border-top: 1px solid var(--border-color);
            padding-top: 20px;
        }
        .sandbox-btn-cancel {
            background: transparent;
            border: 1px solid var(--border-color);
            color: var(--text-muted);
            padding: 12px 20px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 700;
            transition: 0.2s;
        }
        .sandbox-btn-cancel:hover {
            background: rgba(0,0,0,0.05);
            color: var(--text-main);
        }
        [data-theme="dark"] .sandbox-btn-cancel:hover {
            background: rgba(255,255,255,0.05);
        }
        .sandbox-btn-pay {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 12px 24px;
            border-radius: var(--radius-md);
            cursor: pointer;
            font-weight: 700;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
            transition: 0.2s;
        }
        .sandbox-btn-pay:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
        }
    </style>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-logo">
            <img src="../assets/images/logo-light.png" alt="Farmogram" class="logo-light">
            <img src="../assets/images/logo-dark.png" alt="Farmogram" class="logo-dark">
            <span class="logo-text">Farmogram</span>
        </a>

        <ul class="nav-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> <span>Overview</span></a></li>
            <li><a href="marketplace.php"><i class="fa-solid fa-store"></i> <span>Market</span></a></li>
            <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> <span>Cart</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-box-open"></i> <span>Orders</span></a></li>
            <li><a href="my_enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
                        <li><a href="profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
<li><a href="../auth/logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </aside>

    <main class="main-wrapper">
        <header class="topbar">
            <a href="dashboard.php" class="mobile-brand">
                <img src="../assets/images/logo-light.png" alt="Farmogram" class="logo-light">
                <img src="../assets/images/logo-dark.png" alt="Farmogram" class="logo-dark">
                <span>Farmogram</span>
            </a>
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Secure Checkout</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <a href="cart.php" class="theme-btn" style="text-decoration: none; color: var(--primary); border-color: var(--primary);" title="Back to Cart">
                    <i class="fa-solid fa-arrow-left"></i>
                </a>

                <button class="theme-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>
            </div>
        </header>

        <div class="content-container">
            <?php echo $msg; ?>

            <form id="checkoutForm" method="POST" action="" class="checkout-grid">
                
                <input type="hidden" name="checkout_action" value="1">
                
                <div>
                    <div class="bento-card" style="margin-bottom: 30px;">
                        <h2 class="section-title"><i class="fa-solid fa-truck-fast" style="color: var(--primary);"></i> Delivery Address</h2>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label class="form-label">Full Name</label>
                                <input type="text" name="full_name" class="form-input" value="<?php echo htmlspecialchars($user_name); ?>" required>
                            </div>
                            <div class="form-group full-width">
                                <label class="form-label">Street Address / Village</label>
                                <input type="text" name="address" class="form-input" placeholder="e.g., 123 Farm Road, Sector 4" value="<?php echo htmlspecialchars($saved_address); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">City / District</label>
                                <input type="text" name="city" class="form-input" placeholder="e.g., Bengaluru" value="<?php echo htmlspecialchars($saved_city); ?>" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">PIN / Postal Code</label>
                                <input type="text" name="pin_code" class="form-input" placeholder="e.g., 560001" value="<?php echo htmlspecialchars($saved_pin); ?>" required>
                            </div>
                            <div class="form-group full-width" style="margin-bottom: 0;">
                                <label class="form-label">Contact Number</label>
                                <input type="tel" name="phone" class="form-input" placeholder="+91 XXXXX XXXXX" value="<?php echo htmlspecialchars(empty($saved_phone) ? ($user_data['phone'] ?? '') : $saved_phone); ?>" required>
                            </div>
                        </div>
                        
                        <label style="display: flex; align-items: center; gap: 8px; margin-top: 20px; font-weight: 600; cursor: pointer; color: var(--text-muted); padding: 10px 15px; background: rgba(16, 185, 129, 0.05); border-radius: 8px; border: 1px dashed rgba(16, 185, 129, 0.3);">
                            <input type="checkbox" name="save_address" value="1" <?php if($saved_address != '') echo 'checked'; ?> style="width: 18px; height: 18px; accent-color: var(--primary);">
                            <span>Save this configuration for future deliveries</span>
                        </label>
                    </div>

                    <div class="bento-card">
                        <h2 class="section-title"><i class="fa-solid fa-wallet" style="color: #3b82f6;"></i> Payment Method</h2>
                        
                        <div class="payment-methods">
                            <div class="pay-method-wrapper selected" id="wrap-card">
                                <label class="pay-method-label">
                                    <input type="radio" name="payment_method" value="card" checked onclick="selectPayment('card')">
                                    <div class="pay-method-icon"><i class="fa-brands fa-cc-visa"></i></div>
                                    <div>
                                        <div class="pay-method-text">Credit / Debit Card</div>
                                        <div class="pay-method-sub">Instant & Secure Payment via Gateway</div>
                                    </div>
                                </label>
                                <div class="pay-details" id="details-card">
                                    <input type="text" name="card_number" class="form-input" placeholder="Card Number (xxxx-xxxx-xxxx-xxxx)" value="<?php echo htmlspecialchars($saved_card_number); ?>" required>
                                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-top: 15px;">
                                        <input type="text" name="card_expiry" class="form-input" placeholder="MM/YY" value="<?php echo htmlspecialchars($saved_card_expiry); ?>" required>
                                        <input type="text" name="card_cvv" class="form-input" placeholder="CVV" required>
                                    </div>
                                    <input type="text" name="card_name" class="form-input" placeholder="Name on Card" value="<?php echo htmlspecialchars($saved_card_name); ?>" style="margin-top: 15px;" required>
                                    
                                    <label style="display: flex; align-items: center; gap: 8px; margin-top: 15px; font-weight: 600; cursor: pointer; color: var(--text-muted);">
                                        <input type="checkbox" name="save_card" value="1" <?php if($saved_card_number != '') echo 'checked'; ?> style="width: 16px; height: 16px; accent-color: var(--primary);">
                                        <span>Save card for future purchases</span>
                                    </label>
                                </div>
                            </div>

                            <div class="pay-method-wrapper" id="wrap-upi">
                                <label class="pay-method-label">
                                    <input type="radio" name="payment_method" value="upi" onclick="selectPayment('upi')">
                                    <div class="pay-method-icon"><i class="fa-brands fa-google-pay"></i></div>
                                    <div>
                                        <div class="pay-method-text">UPI / Wallets</div>
                                        <div class="pay-method-sub">Pay via GPay, PhonePe, Paytm</div>
                                    </div>
                                </label>
                                <div class="pay-details" id="details-upi">
                                    <input type="text" class="form-input" placeholder="Enter your UPI ID (e.g., 9876543210@ybl)">
                                </div>
                            </div>

                            <div class="pay-method-wrapper" id="wrap-cod">
                                <label class="pay-method-label">
                                    <input type="radio" name="payment_method" value="cod" onclick="selectPayment('cod')">
                                    <div class="pay-method-icon"><i class="fa-solid fa-hand-holding-dollar" style="color: #10b981;"></i></div>
                                    <div>
                                        <div class="pay-method-text">Cash on Delivery (COD)</div>
                                        <div class="pay-method-sub">Pay directly when your produce arrives</div>
                                    </div>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bento-card order-summary">
                    <h2 style="font-size: 1.4rem; margin: 0 0 20px 0; color: var(--text-main); font-weight: 900; letter-spacing: -0.5px;" class="logo-text">Order Review</h2>
                    
                    <div style="max-height: 300px; overflow-y: auto; padding-right: 10px; margin-bottom: 20px;">
                        <?php foreach($_SESSION['cart'] as $id => $item): 
                            $item_total = $item['price'] * $item['quantity'];
                        ?>
                            <div class="summary-item">
                                <div>
                                    <div class="item-name"><?php echo htmlspecialchars(str_replace(' (Pre-Book Advance)', '', $item['name'])); ?></div>
                                    <div class="item-qty">Qty: <?php echo $item['quantity']; ?> kg</div>
                                </div>
                                <div class="item-price">₹<?php echo number_format($item_total, 2); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="totals-row"><span>Subtotal</span> <span style="color: var(--text-main); font-weight: 800;">₹<span id="subTotalVal"><?php echo number_format($total, 2, '.', ''); ?></span></span></div>
                    
                    <div style="margin: 15px 0; display: flex; gap: 10px;">
                        <input type="text" id="promoInput" placeholder="Promo Code" class="form-input" style="flex: 1; margin: 0; padding: 10px 15px; font-weight: 800; text-transform: uppercase;">
                        <button type="button" class="btn-submit" id="applyPromoBtn" style="width: auto; margin: 0; padding: 10px 20px; font-size: 0.95rem;"><i class="fa-solid fa-tag"></i> Apply</button>
                    </div>
                    <div id="promoMessage" style="font-size: 0.85rem; font-weight: 700; margin-bottom: 15px; display: none;"></div>
                    
                    <div class="totals-row" id="discountRow" style="display: none; color: #10b981; font-weight: 800;"><span>Discount applied</span> <span>-₹<span id="discountVal">0.00</span></span></div>
                    <div class="totals-row"><span>Platform Fee</span> <span style="color: var(--text-main); font-weight: 800;">₹0.00</span></div>
                    <div class="grand-total"><span>Total Due</span> <span>₹<span id="grandTotalVal"><?php echo number_format($total, 2, '.', ''); ?></span></span></div>
                    
                    <input type="hidden" name="applied_promo_code" id="hiddenPromoCode" value="">
                    
                    <button type="submit" class="btn-confirm"><i class="fa-solid fa-lock"></i> Confirm & Pay</button>

                    <div class="trust-badge">
                        <i class="fa-solid fa-shield-check"></i>
                        <p>Farmogram Buyer Protection</p>
                        <span>Your payment is held securely until the crops are verified and delivered.</span>
                    </div>
                </div>

            </form>
        </div>
    </main>
</div>

<div class="loader-overlay" id="paymentOverlay">
    <div class="spinner" id="spinner"></div>
    <i class="fa-solid fa-circle-check success-check" id="successCheck"></i>
    <div class="loader-text" id="loaderText">Processing Secure Payment...</div>
    <div class="loader-sub" id="loaderSub">Please do not close this window or press back.</div>
</div>

<script>
// Dynamic Payment UI Logic
function selectPayment(method) {
    // 1. Reset all wrappers
    document.querySelectorAll('.pay-method-wrapper').forEach(el => {
        el.classList.remove('selected');
        // Remove 'required' from all hidden inputs to prevent form submission blocking
        el.querySelectorAll('.pay-details input').forEach(inp => inp.removeAttribute('required'));
    });
    
    // 2. Activate selected wrapper
    let selectedWrapper = document.getElementById('wrap-' + method);
    if(selectedWrapper) {
        selectedWrapper.classList.add('selected');
        // Add 'required' to visible inputs only
        selectedWrapper.querySelectorAll('.pay-details input').forEach(inp => inp.setAttribute('required', 'true'));
    }
}

// Initialize payment state on load
document.addEventListener('DOMContentLoaded', () => {
    selectPayment('card');
    
    // Promo Code Logic
    const promoBtn = document.getElementById('applyPromoBtn');
    if(promoBtn) {
        promoBtn.addEventListener('click', () => {
            const code = document.getElementById('promoInput').value;
            const msg = document.getElementById('promoMessage');
            const discountRow = document.getElementById('discountRow');
            const discountVal = document.getElementById('discountVal');
            const grandTotal = document.getElementById('grandTotalVal');
            const hiddenPromo = document.getElementById('hiddenPromoCode');
            const subTotal = parseFloat(document.getElementById('subTotalVal').innerText);

            if(!code) return;

            fetch('../api/validate_promo.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'code=' + encodeURIComponent(code)
            }).then(r => r.json()).then(res => {
                msg.style.display = 'block';
                if(res.status === 'success') {
                    msg.style.color = '#10b981';
                    msg.innerHTML = '<i class="fa-solid fa-check-circle"></i> Promo code applied! (' + res.discount_percent + '% off)';
                    
                    let discAmt = (subTotal * res.discount_percent) / 100;
                    let newTotal = subTotal - discAmt;
                    
                    discountRow.style.display = 'flex';
                    discountVal.innerText = discAmt.toFixed(2);
                    grandTotal.innerText = newTotal.toFixed(2);
                    hiddenPromo.value = code.toUpperCase();
                } else {
                    msg.style.color = '#ef4444';
                    msg.innerHTML = '<i class="fa-solid fa-triangle-exclamation"></i> ' + res.message;
                    discountRow.style.display = 'none';
                    grandTotal.innerText = subTotal.toFixed(2);
                    hiddenPromo.value = '';
                }
            });
        });
    }
});

// Intercept form submission for cinematic overlay & Razorpay Integration
document.getElementById('checkoutForm').addEventListener('submit', function(e) {
    e.preventDefault(); 
    
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    const overlay = document.getElementById('paymentOverlay');
    const text = document.getElementById('loaderText');
    const sub = document.getElementById('loaderSub');
    
    if (paymentMethod === 'cod') {
        // Standard Cash on Delivery Flow
        overlay.classList.add('active');
        const spinner = document.getElementById('spinner');
        const check = document.getElementById('successCheck');
        
        setTimeout(() => {
            spinner.style.display = 'none';
            check.style.display = 'block';
            text.innerText = 'Order Confirmed!';
            text.style.color = '#10b981';
            sub.innerText = 'Verifying inventory...';
            
            setTimeout(() => {
                HTMLFormElement.prototype.submit.call(document.getElementById('checkoutForm'));
            }, 1500);
            
        }, 2000);
    } else {
        // Razorpay Digital Flow (Card / UPI)
        overlay.classList.add('active');
        text.innerText = 'Connecting to Secure Server...';
        sub.innerText = 'Generating Razorpay session for ' + paymentMethod.toUpperCase();
        
        // Let's create an AJAX request to process_razorpay.php
        let p_code = encodeURIComponent(document.getElementById('hiddenPromoCode').value);
        fetch('process_razorpay.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'init=1&promo_code=' + p_code
        })
        .then(res => res.json())
        .then(data => {
            overlay.classList.remove('active');
            
            if (data.status === 'success') {
                if (data.is_mock) {
                    // Sandbox Mode! Open our custom modal
                    openSandbox(data);
                } else {
                    // Regular Razorpay Flow
                    var options = {
                        "key": data.key, 
                        "amount": data.amount,
                        "currency": data.currency,
                        "name": "Farmogram",
                        "description": "Secure Crop Purchase",
                        "order_id": data.order_id,
                        "handler": function (response){
                            // Payment Successful! Pass data to verify_payment.php to secure it
                            let form = document.createElement('form');
                            form.method = 'POST';
                            form.action = 'verify_payment.php';
                            
                            let pi = document.createElement('input'); pi.type='hidden'; pi.name='razorpay_payment_id'; pi.value = response.razorpay_payment_id;
                            let oi = document.createElement('input'); oi.type='hidden'; oi.name='razorpay_order_id'; oi.value = response.razorpay_order_id;
                            let s = document.createElement('input'); s.type='hidden'; s.name='razorpay_signature'; s.value = response.razorpay_signature;
                            
                            form.appendChild(pi);
                            form.appendChild(oi);
                            form.appendChild(s);
                            
                            ['full_name', 'address', 'city', 'pin_code', 'phone'].forEach(field => {
                                let el = document.querySelector('input[name="'+field+'"]');
                                if(el) { let hidden = document.createElement('input'); hidden.type = 'hidden'; hidden.name = field; hidden.value = el.value; form.appendChild(hidden); }
                            });
                            
                            let saveAddr = document.querySelector('input[name="save_address"]');
                            if(saveAddr && saveAddr.checked) { let sd = document.createElement('input'); sd.type='hidden'; sd.name='save_address'; sd.value='1'; form.appendChild(sd); }
                            
                            let hiddenPromo = document.getElementById('hiddenPromoCode');
                            if(hiddenPromo && hiddenPromo.value !== '') {
                                let hp = document.createElement('input'); hp.type='hidden'; hp.name='applied_promo_code'; hp.value=hiddenPromo.value; form.appendChild(hp);
                            }
                            
                            if(paymentMethod === 'card') {
                                let saveCard = document.querySelector('input[name="save_card"]');
                                if(saveCard && saveCard.checked) {
                                    let sc = document.createElement('input'); sc.type='hidden'; sc.name='save_card'; sc.value='1'; form.appendChild(sc);
                                    ['card_number', 'card_expiry', 'card_name'].forEach(field => {
                                        let el = document.querySelector('input[name="'+field+'"]');
                                        if(el) { let hidden = document.createElement('input'); hidden.type='hidden'; hidden.name=field; hidden.value=el.value; form.appendChild(hidden); }
                                    });
                                }
                            }
                            
                            document.body.appendChild(form);
                            
                            overlay.classList.add('active');
                            text.innerText = 'Securing Payment...';
                            sub.innerText = 'Verifying with the bank.';
                            form.submit();
                        },
                        "prefill": {
                            "name": data.name,
                            "email": data.email,
                            "contact": data.phone
                        },
                        "theme": {
                            "color": "#10b981"
                        }
                    };
                    var rzp1 = new Razorpay(options);
                    rzp1.on('payment.failed', function (response){
                        alert("Payment Failed: " + response.error.description);
                    });
                    rzp1.open();
                }
            } else {
                alert("Could not initialize payment: " + data.message);
            }
        })
        .catch(err => {
            overlay.classList.remove('active');
            alert("Connection error: " + err);
        });
    }
});

// SANDBOX PAYMENT PORTAL JS
let currentMockData = null;

function openSandbox(data) {
    currentMockData = data;
    const modal = document.getElementById('sandboxModal');
    const body = document.getElementById('sandboxBody');
    const amtSpan = document.getElementById('sandboxAmountText');
    
    // Format Amount
    let amtINR = (data.amount / 100).toFixed(2);
    amtSpan.innerText = amtINR;
    
    const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
    
    if (paymentMethod === 'card') {
        const cardNum = document.querySelector('input[name="card_number"]').value || '•••• •••• •••• ••••';
        const cardExp = document.querySelector('input[name="card_expiry"]').value || 'MM/YY';
        const cardName = document.querySelector('input[name="card_name"]').value || 'CARDHOLDER NAME';
        
        body.innerHTML = `
            <div class="sandbox-card-preview">
                <div class="sandbox-card-chip"></div>
                <div class="sandbox-card-number">${cardNum}</div>
                <div class="sandbox-card-footer">
                    <div class="sandbox-card-name">${cardName}</div>
                    <div class="sandbox-card-expiry-wrap">
                        <div class="sandbox-card-exp-lbl">Expires</div>
                        <div class="sandbox-card-exp-val">${cardExp}</div>
                    </div>
                </div>
            </div>
            <div class="sandbox-info-box">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Demo Sandbox Mode:</strong> Real payment credentials are not processed. You can enter any details. Click the button below to authorize the transaction.
                </div>
            </div>
        `;
    } else {
        const upiId = document.querySelector('#details-upi input').value || 'buyer@farmogram';
        body.innerHTML = `
            <div class="sandbox-upi-qr">
                <div class="sandbox-qr-code">
                    <div class="sandbox-qr-placeholder">
                        <i class="fa-solid fa-qrcode"></i>
                        <span>SCAN TO PAY</span>
                    </div>
                </div>
                <div class="sandbox-upi-details">
                    <span>Paying to: <strong>farmogram@gateway</strong></span>
                    <br>
                    <span style="font-size: 0.9rem; color: var(--text-muted); font-weight: 500;">Your VPA: ${upiId}</span>
                </div>
            </div>
            <div class="sandbox-info-box">
                <i class="fa-solid fa-circle-info"></i>
                <div>
                    <strong>Demo UPI Payment:</strong> Simulating UPI webhook verification. Click the button below to confirm authorization.
                </div>
            </div>
        `;
    }
    
    modal.classList.add('active');
}

function closeSandbox() {
    const modal = document.getElementById('sandboxModal');
    modal.classList.remove('active');
}

function submitSandboxPayment() {
    if (!currentMockData) return;
    
    const payBtn = document.getElementById('sandboxPayBtn');
    payBtn.disabled = true;
    payBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Processing...';
    
    closeSandbox();
    
    const overlay = document.getElementById('paymentOverlay');
    const text = document.getElementById('loaderText');
    const sub = document.getElementById('loaderSub');
    const spinner = document.getElementById('spinner');
    const check = document.getElementById('successCheck');
    
    overlay.classList.add('active');
    text.innerText = 'Authorizing Mock Transaction...';
    sub.innerText = 'Communicating with simulated banking server.';
    
    setTimeout(() => {
        spinner.style.display = 'none';
        check.style.display = 'block';
        text.innerText = 'Payment Authorized!';
        text.style.color = '#10b981';
        sub.innerText = 'Updating database and notifying farmers...';
        
        setTimeout(() => {
            let responsePaymentId = 'pay_mock_' + Math.random().toString(36).substr(2, 9) + '_' + Date.now();
            let responseOrderId = currentMockData.order_id;
            let responseSignature = 'sig_mock_' + Math.random().toString(36).substr(2, 12);
            
            let form = document.createElement('form');
            form.method = 'POST';
            form.action = 'verify_payment.php';
            
            let pi = document.createElement('input'); pi.type='hidden'; pi.name='razorpay_payment_id'; pi.value = responsePaymentId;
            let oi = document.createElement('input'); oi.type='hidden'; oi.name='razorpay_order_id'; oi.value = responseOrderId;
            let s = document.createElement('input'); s.type='hidden'; s.name='razorpay_signature'; s.value = responseSignature;
            
            form.appendChild(pi);
            form.appendChild(oi);
            form.appendChild(s);
            
            ['full_name', 'address', 'city', 'pin_code', 'phone'].forEach(field => {
                let el = document.querySelector('input[name="'+field+'"]');
                if(el) { 
                    let hidden = document.createElement('input'); 
                    hidden.type = 'hidden'; 
                    hidden.name = field; 
                    hidden.value = el.value; 
                    form.appendChild(hidden); 
                }
            });
            
            let saveAddr = document.querySelector('input[name="save_address"]');
            if(saveAddr && saveAddr.checked) { 
                let sd = document.createElement('input'); sd.type='hidden'; sd.name='save_address'; sd.value='1'; 
                form.appendChild(sd); 
            }
            
            let hiddenPromo = document.getElementById('hiddenPromoCode');
            if(hiddenPromo && hiddenPromo.value !== '') {
                let hp = document.createElement('input'); hp.type='hidden'; hp.name='applied_promo_code'; hp.value=hiddenPromo.value; 
                form.appendChild(hp);
            }
            
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
            if(paymentMethod === 'card') {
                let saveCard = document.querySelector('input[name="save_card"]');
                if(saveCard && saveCard.checked) {
                    let sc = document.createElement('input'); sc.type='hidden'; sc.name='save_card'; sc.value='1'; 
                    form.appendChild(sc);
                    ['card_number', 'card_expiry', 'card_name'].forEach(field => {
                        let el = document.querySelector('input[name="'+field+'"]');
                        if(el) { 
                            let hidden = document.createElement('input'); 
                            hidden.type='hidden'; 
                            hidden.name=field; 
                            hidden.value=el.value; 
                            form.appendChild(hidden); 
                        }
                    });
                }
            }
            
            document.body.appendChild(form);
            form.submit();
        }, 1500);
        
    }, 2500);
}

// THEME TOGGLE SCRIPT
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('themeToggle');
    const darkIcon = document.querySelector('.dark-icon');
    const lightIcon = document.querySelector('.light-icon');

    if(themeToggleBtn) {
        if(document.documentElement.getAttribute('data-theme') === 'dark') {
            darkIcon.style.display = 'none'; lightIcon.style.display = 'inline-block';
        } else {
            darkIcon.style.display = 'inline-block'; lightIcon.style.display = 'none';
        }

        themeToggleBtn.addEventListener('click', () => {
            let currentTheme = document.documentElement.getAttribute('data-theme');
            let targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', targetTheme);
            localStorage.setItem('farmogram_theme', targetTheme);
            
            if(targetTheme === 'dark') {
                darkIcon.style.display = 'none'; lightIcon.style.display = 'inline-block';
            } else {
                darkIcon.style.display = 'inline-block'; lightIcon.style.display = 'none';
            }
        });
    }
});
</script>

<script src="https://checkout.razorpay.com/v1/checkout.js"></script>
<script src="../assets/js/translate.js"></script>

<div class="sandbox-modal-overlay" id="sandboxModal">
    <div class="sandbox-modal">
        <button class="sandbox-close-btn" onclick="closeSandbox()">&times;</button>
        <div class="sandbox-header">
            <div class="sandbox-header-icon">
                <i class="fa-solid fa-flask-vial"></i>
            </div>
            <div>
                <h3>Farmogram Sandbox Payment</h3>
                <p>Simulating secure gateway authorization</p>
            </div>
        </div>
        
        <div class="sandbox-body" id="sandboxBody">
            <!-- Simulated Details -->
        </div>
        
        <div class="sandbox-footer">
            <button class="sandbox-btn-cancel" onclick="closeSandbox()">Cancel</button>
            <button class="sandbox-btn-pay" id="sandboxPayBtn" onclick="submitSandboxPayment()">Complete Payment (₹<span id="sandboxAmountText"></span>)</button>
        </div>
    </div>
</div>

</body>
</html>