<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id'])){
    die("Unauthorized Access");
}

$buyer_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

/* Fetch Order Info */
$order_query = $conn->query("
    SELECT orders.*, users.name, users.email, users.location 
    FROM orders 
    JOIN users ON orders.buyer_id = users.id 
    WHERE orders.id = $order_id AND orders.buyer_id = $buyer_id
");

if($order_query->num_rows == 0){
    die("Order not found or access denied.");
}
$order = $order_query->fetch_assoc();

/* Fetch Items */
$items = $conn->query("
    SELECT order_items.*, crops.crop_name, users.name AS farmer_name 
    FROM order_items 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN users ON crops.farmer_id = users.id 
    WHERE order_id = $order_id
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice #INV-<?php echo $order_id; ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #e5e7eb; padding: 40px; }
        .invoice-box { max-width: 800px; margin: auto; padding: 40px; border: 1px solid #eee; box-shadow: 0 10px 30px rgba(0,0,0,0.1); font-size: 14px; line-height: 24px; color: #333; background: #fff; border-radius: 10px; }
        .header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 40px; border-bottom: 2px solid #00e676; padding-bottom: 20px; }
        .header h1 { color: #00e676; margin: 0; font-size: 28px; font-weight: 800; }
        .details-grid { display: flex; justify-content: space-between; margin-bottom: 40px; }
        .invoice-table { width: 100%; border-collapse: collapse; margin-bottom: 40px; }
        .invoice-table th { background: #f9fafb; border-bottom: 2px solid #e5e7eb; padding: 12px; text-align: left; font-weight: 600; color: #1f2937; }
        .invoice-table td { padding: 12px; border-bottom: 1px solid #e5e7eb; }
        .total-row td { font-weight: 800; font-size: 18px; color: #111827; background: #f9fafb; border-bottom: none; }
        .footer { text-align: center; color: #6b7280; font-size: 12px; border-top: 1px solid #e5e7eb; padding-top: 20px; }
        
        /* Hide everything else when printing */
        @media print {
            body { background: white; padding: 0; }
            .invoice-box { box-shadow: none; border: none; max-width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">

<div class="invoice-box">
    <div class="header">
        <div>
            <h1>Farmogram</h1>
            <p style="color: #6b7280; margin: 5px 0 0 0;">Smart Agro Connect Platform<br>hello@farmogram.ai</p>
        </div>
        <div style="text-align: right;">
            <h2 style="margin: 0; color: #111827;">INVOICE</h2>
            <p style="margin: 5px 0 0 0;"><strong>#INV-<?php echo $order_id; ?></strong><br>Date: <?php echo date('M j, Y', strtotime($order['created_at'])); ?></p>
        </div>
    </div>

    <div class="details-grid">
        <div>
            <div style="color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 12px; margin-bottom: 5px;">Billed To</div>
            <strong style="font-size: 16px; color: #111827;"><?php echo htmlspecialchars($order['name']); ?></strong><br>
            <?php echo htmlspecialchars($order['email']); ?><br>
            Location: <?php echo htmlspecialchars($order['location']); ?>
        </div>
        <div style="text-align: right;">
            <div style="color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: 12px; margin-bottom: 5px;">Payment Status</div>
            <span style="background: #dcfce3; color: #166534; padding: 5px 12px; border-radius: 20px; font-weight: 600; font-size: 12px; display: inline-block;"><?php echo strtoupper($order['payment_status']); ?></span>
        </div>
    </div>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Crop Description</th>
                <th>Supplier (Farmer)</th>
                <th>Quantity</th>
                <th>Price/kg</th>
                <th style="text-align: right;">Line Total</th>
            </tr>
        </thead>
        <tbody>
            <?php while($item = $items->fetch_assoc()): ?>
            <?php $item_total = $item['quantity'] * $item['price']; ?>
            <tr>
                <td style="text-transform: capitalize; font-weight: 600;"><?php echo htmlspecialchars($item['crop_name']); ?></td>
                <td><?php echo htmlspecialchars($item['farmer_name']); ?></td>
                <td><?php echo $item['quantity']; ?> kg</td>
                <td>₹ <?php echo number_format($item['price'], 2); ?></td>
                <td style="text-align: right; font-weight: 600;">₹ <?php echo number_format($item_total, 2); ?></td>
            </tr>
            <?php endwhile; ?>
            
            <tr class="total-row">
                <td colspan="4" style="text-align: right;">Grand Total:</td>
                <td style="text-align: right; color: #00e676;">₹ <?php echo number_format($order['total_amount'], 2); ?></td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Thank you for supporting sustainable agriculture and local farmers.<br>
        This is a computer-generated document. No signature is required.
    </div>
</div>

</body>
</html>