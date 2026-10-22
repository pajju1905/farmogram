<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id'])){
    header("Location: ../auth/login.php");
    exit();
}

$buyer_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : (isset($_POST['order_id']) ? intval($_POST['order_id']) : 0);

/* Verify Order */
$order_query = $conn->query("SELECT * FROM orders WHERE id = $order_id AND buyer_id = $buyer_id");
if($order_query->num_rows == 0) die("Order not found.");

/* Calculate Pending Balance */
$items = $conn->query("
    SELECT order_items.*, crops.market_price 
    FROM order_items 
    JOIN crops ON order_items.crop_id = crops.id 
    WHERE order_items.order_id = $order_id
");

$pending_balance = 0;
while($item = $items->fetch_assoc()){
    $item_full_value = $item['quantity'] * $item['market_price'];
    $item_paid = $item['quantity'] * $item['price'];
    $pending_balance += ($item_full_value - $item_paid);
}

// If already paid, send them back
if($pending_balance <= 0) {
    header("Location: orders.php");
    exit();
}

/* Handle Payment Submission */
if(isset($_POST['confirm_payment'])){
    $items->data_seek(0); // Reset result pointer
    
    // Update each item's price to the FULL market price to clear the contract debt
    while($item = $items->fetch_assoc()){
        if($item['price'] < $item['market_price']) {
            $conn->query("UPDATE order_items SET price = {$item['market_price']} WHERE id = {$item['id']}");
        }
    }
    
    // Add the balance to the master order total
    $conn->query("UPDATE orders SET total_amount = total_amount + $pending_balance WHERE id = $order_id");
    
    // Redirect with success
    header("Location: orders.php?msg=balance_paid");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settle Contract Balance | Farmogram</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: var(--bg-light); display: flex; align-items: center; justify-content: center; min-height: 100vh; }
        .checkout-box { background: #fff; width: 100%; max-width: 500px; border-radius: var(--radius-lg); box-shadow: var(--shadow-lg); overflow: hidden; border: 1px solid var(--border-color); }
        .checkout-header { background: var(--dark-bg); color: #fff; padding: 25px; text-align: center; }
        .checkout-header h2 { margin: 0; font-size: 1.5rem; display: flex; align-items: center; justify-content: center; gap: 10px; }
        .checkout-body { padding: 30px; }
        
        .amount-display { text-align: center; margin-bottom: 30px; padding: 20px; background: rgba(245, 158, 11, 0.1); border: 1px dashed #f59e0b; border-radius: var(--radius-md); }
        .amount-display span { display: block; color: #b45309; font-size: 0.85rem; font-weight: 600; text-transform: uppercase; margin-bottom: 5px; }
        .amount-display strong { font-size: 2.5rem; font-weight: 800; color: #d97706; }
        
        .secure-badge { display: flex; align-items: center; justify-content: center; gap: 8px; color: #10b981; font-size: 0.85rem; font-weight: 600; margin-bottom: 25px; }
        
        .btn-confirm { width: 100%; padding: 16px; background: var(--primary); color: var(--dark-bg); font-weight: 700; font-size: 1.1rem; border: none; border-radius: var(--radius-md); cursor: pointer; transition: var(--transition); display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-confirm:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 20px rgba(0,230,118,0.2); }
        
        .cancel-link { display: block; text-align: center; margin-top: 20px; color: var(--text-muted); text-decoration: none; font-size: 0.9rem; font-weight: 500; transition: var(--transition); }
        .cancel-link:hover { color: var(--dark-bg); }
    </style>
</head>
<body>

<div class="checkout-box">
    <div class="checkout-header">
        <h2><i class="fa-solid fa-file-contract"></i> Settle Contract</h2>
        <p style="color: #9ca3af; font-size: 0.85rem; margin-top: 5px;">Order #<?php echo $order_id; ?></p>
    </div>
    
    <div class="checkout-body">
        <div class="amount-display">
            <span>Remaining Balance Due</span>
            <strong>₹ <?php echo number_format($pending_balance, 2); ?></strong>
        </div>
        
        <div class="secure-badge">
            <i class="fa-solid fa-lock"></i> 256-bit Encrypted Payment Gateway
        </div>
        
        <form method="POST">
            <input type="hidden" name="order_id" value="<?php echo $order_id; ?>">
            <button type="submit" name="confirm_payment" class="btn-confirm">
                Confirm & Pay ₹ <?php echo number_format($pending_balance, 2); ?>
            </button>
        </form>
        
        <a href="orders.php" class="cancel-link">Cancel and return to Orders</a>
    </div>
</div>

</body>
</html>