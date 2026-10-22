<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

/* Handle status update from Admin */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])){
    $order_id = (int)$_POST['order_id'];
    $status = $conn->real_escape_string($_POST['status']);
    $conn->query("UPDATE orders SET order_status='$status' WHERE id=$order_id");
    
    $msg = "<div class='success-msg'><i class='fa-solid fa-check-circle'></i> Order #ORD-{$order_id} status updated to " . ucfirst($status) . ".</div>";
}

/* Fetch ALL orders with deep relationships (Items, Crops, Buyers, and Farmers) */
$query = "
    SELECT o.id AS order_id, o.total_amount, o.payment_status, o.order_status, o.created_at,
           u_buyer.name AS buyer_name, u_buyer.phone AS buyer_phone, u_buyer.email AS buyer_email,
           c.crop_name, c.status AS crop_status, c.market_price AS full_market_price,
           u_farmer.name AS farmer_name, u_farmer.phone AS farmer_phone,
           oi.quantity, oi.price AS paid_price
    FROM orders o
    JOIN users u_buyer ON o.buyer_id = u_buyer.id
    LEFT JOIN order_items oi ON o.id = oi.order_id
    LEFT JOIN crops c ON oi.crop_id = c.id
    LEFT JOIN users u_farmer ON c.farmer_id = u_farmer.id
    ORDER BY o.created_at DESC
";
$result = $conn->query($query);

// Process the raw SQL into structured, grouped Order arrays
$orders_data = [];
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $oid = $row['order_id'];
        
        // If we haven't seen this order yet, initialize its array
        if(!isset($orders_data[$oid])){
            $orders_data[$oid] = [
                'order_id' => $oid,
                'total_amount' => $row['total_amount'],
                'payment_status' => $row['payment_status'],
                'order_status' => $row['order_status'],
                'created_at' => $row['created_at'],
                'buyer_name' => $row['buyer_name'],
                'buyer_phone' => $row['buyer_phone'],
                'buyer_email' => $row['buyer_email'],
                'is_preorder' => false,
                'items' => []
            ];
        }
        
        // Check if ANY item in this order is a contract/pre-book
        if($row['crop_status'] == 'growing' || $row['paid_price'] < $row['full_market_price']){
            $orders_data[$oid]['is_preorder'] = true;
        }
        
        // Add the specific crop item details to this order
        $orders_data[$oid]['items'][] = [
            'crop_name' => $row['crop_name'],
            'quantity' => $row['quantity'],
            'paid_price' => $row['paid_price'],
            'farmer_name' => $row['farmer_name'],
            'farmer_phone' => $row['farmer_phone']
        ];
    }
}

// Separate into tabs
$standard_orders = [];
$preorders = [];
$cancelled_orders = [];

foreach($orders_data as $order){
    if(strtolower($order['order_status']) == 'cancelled'){
        $cancelled_orders[] = $order;
    } elseif($order['is_preorder']){
        $preorders[] = $order;
    } else {
        $standard_orders[] = $order;
    }
}

// Function to render the beautiful Admin Wide-Cards
function renderAdminCards($orders_array) {
    if(count($orders_array) == 0){
        echo '<div class="empty-state">
                <i class="fa-solid fa-box-open"></i>
                <h3>No Orders Found</h3>
                <p>No transactions match this category currently.</p>
              </div>';
        return;
    }

    foreach($orders_array as $order){
        $status_clean = strtolower($order['order_status']);
        $status_color = '#3b82f6'; // Processing (Blue)
        if($status_clean == 'shipped') $status_color = '#f59e0b'; // Orange
        if($status_clean == 'delivered') $status_color = '#10b981'; // Green
        if($status_clean == 'cancelled') $status_color = '#ef4444'; // Red

        // Hex to RGBA conversion for the pill background
        list($r, $g, $b) = sscanf($status_color, "#%02x%02x%02x");
        $pill_bg = "rgba($r, $g, $b, 0.15)";
        $pill_border = "rgba($r, $g, $b, 0.3)";

        echo '<div class="admin-order-card" style="border-left-color: '.$status_color.';">';
        
        // Header
        echo '<div class="card-header">';
        echo '  <div class="header-left">
                    <div class="o-id">#ORD-'.$order['order_id'].'</div>
                    <div class="o-date"><i class="fa-regular fa-clock"></i> '.date('M j, Y - g:i A', strtotime($order['created_at'])).'</div>
                </div>';
        echo '  <div class="status-pill" style="background: '.$pill_bg.'; color: '.$status_color.'; border: 1px solid '.$pill_border.';">
                    <i class="fa-solid fa-circle-dot" style="font-size: 0.6rem;"></i> '.ucfirst($status_clean).'
                </div>';
        echo '</div>';

        // Body Grid
        echo '<div class="card-body-grid">';

        // Col 1: Buyer Info & Financials
        echo '  <div class="grid-col">
                    <div class="col-label"><i class="fa-solid fa-user-tag"></i> Buyer Details & Billing</div>
                    <div class="detail-row buyer-name">'.htmlspecialchars($order['buyer_name']).'</div>
                    <div class="detail-row text-muted"><i class="fa-solid fa-phone icon-muted"></i> '.htmlspecialchars($order['buyer_phone'] ?? 'N/A').'</div>
                    <div class="detail-row text-muted"><i class="fa-solid fa-envelope icon-muted"></i> '.htmlspecialchars($order['buyer_email'] ?? 'N/A').'</div>
                    
                    <div class="financial-box">
                        <div class="detail-row">Total Value: <span class="price-val">₹ '.number_format($order['total_amount'], 2).'</span></div>
                        <div class="detail-row">Payment: <span class="pay-status" style="color: '.($order['payment_status']=='paid' ? '#10b981' : '#f59e0b').';">'.htmlspecialchars($order['payment_status']).'</span></div>
                    </div>
                </div>';

        // Col 2: Fulfillment & Logistics
        echo '  <div class="grid-col middle-col">
                    <div class="col-label"><i class="fa-solid fa-truck-fast"></i> Logistics & Suppliers</div>';
        foreach($order['items'] as $item){
            echo '  <div class="order-item-box">
                        <div class="item-name">'.$item['quantity'].'kg '.htmlspecialchars($item['crop_name']).'</div>
                        <div class="item-bottom">
                            <span><i class="fa-solid fa-user-tractor" style="color: var(--primary);"></i> '.htmlspecialchars($item['farmer_name']).'</span>
                            <span class="item-price">₹ '.number_format($item['paid_price'] * $item['quantity'], 2).'</span>
                        </div>
                    </div>';
        }
        echo '  </div>';

        // Col 3: Admin Controls
        echo '  <div class="grid-col">
                    <div class="col-label"><i class="fa-solid fa-sliders"></i> Admin Overrides</div>
                    <p class="admin-desc">Update the global shipment status to instantly notify both the buyer and the farmer.</p>
                    
                    <form method="POST" action="" class="admin-update-form">
                        <input type="hidden" name="order_id" value="'.$order['order_id'].'">
                        <select name="status" class="status-select">
                            <option value="processing" '.($status_clean=='processing'?'selected':'').'>Processing</option>
                            <option value="shipped" '.($status_clean=='shipped'?'selected':'').'>Shipped / In Transit</option>
                            <option value="delivered" '.($status_clean=='delivered'?'selected':'').'>Delivered</option>
                            <option value="cancelled" '.($status_clean=='cancelled'?'selected':'').'>Cancelled / Refund</option>
                        </select>
                        <button type="submit" name="update_status" class="btn-update">Force Update</button>
                    </form>
                </div>';

        echo '</div>'; // End Grid
        echo '</div>'; // End Card
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Global Logistics | Admin | Farmogram</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('farmogram_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        /* =========================================================
           BENTO UI - ADMIN ORDERS DESIGN
        ========================================================= */
        * { box-sizing: border-box; }
        
        .content-container { padding: 40px 5%; max-width: 1600px; width: 100%; margin: 0 auto; overflow-x: hidden; }

        .success-msg { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 20px; border-radius: 16px; border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 25px; font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 10px; }

        /* Modern Tabs */
        .tabs-container { display: flex; gap: 15px; margin-bottom: 35px; flex-wrap: wrap; }
        .tab-btn { background: var(--card-bg); border: 2px solid var(--border-color); padding: 14px 25px; border-radius: 30px; font-size: 1rem; font-weight: 800; color: var(--text-muted); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; box-shadow: 0 4px 10px rgba(0,0,0,0.02); font-family: inherit; }
        .tab-btn:hover { border-color: var(--primary); color: var(--text-main); transform: translateY(-2px); }
        .tab-btn.active { background: var(--primary); border-color: var(--primary); color: #fff; box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
        .tab-badge { background: var(--bg-light); color: var(--text-main); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; border: 1px solid var(--border-color); transition: 0.3s; }
        .tab-btn.active .tab-badge { background: rgba(255,255,255,0.2); color: #fff; border-color: rgba(255,255,255,0.3); }

        .tab-content { display: none; animation: fadeIn 0.4s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Admin Wide Cards */
        .admin-order-card { background: var(--card-bg); border-radius: 28px; border: 1px solid var(--border-color); border-left-width: 6px; box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05); margin-bottom: 30px; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); overflow: hidden; }
        .admin-order-card:hover { box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08); transform: translateY(-3px); }
        
        .card-header { background: var(--bg-light); padding: 25px 35px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; transition: 0.3s; }
        .header-left { display: flex; gap: 20px; align-items: center; flex-wrap: wrap; }
        .o-id { font-size: 1.3rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; transition: 0.3s; }
        .o-date { font-size: 0.95rem; color: var(--text-muted); font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 6px; }
        .status-pill { padding: 8px 18px; border-radius: 20px; font-size: 0.85rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 8px; }

        .card-body-grid { display: grid; grid-template-columns: 1fr 1.2fr 1fr; gap: 30px; padding: 35px; transition: 0.3s; }
        
        .grid-col { display: flex; flex-direction: column; }
        .middle-col { border-left: 2px dashed var(--border-color); border-right: 2px dashed var(--border-color); padding: 0 30px; transition: 0.3s; }
        .col-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 800; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; transition: 0.3s; }
        
        .detail-row { margin-bottom: 10px; font-size: 1rem; color: var(--text-main); display: flex; align-items: center; gap: 12px; transition: 0.3s; }
        .buyer-name { font-weight: 900; font-size: 1.2rem; text-transform: capitalize; margin-bottom: 15px; }
        .text-muted { color: var(--text-muted); font-weight: 500; }
        .icon-muted { color: var(--primary); width: 20px; text-align: center; opacity: 0.8; }
        
        .financial-box { margin-top: 20px; padding-top: 20px; border-top: 1px dashed var(--border-color); transition: 0.3s; }
        .price-val { font-weight: 900; color: #10b981; font-size: 1.4rem; margin-left: auto; letter-spacing: -0.5px; }
        .pay-status { font-weight: 800; text-transform: uppercase; margin-left: auto; font-size: 0.9rem; letter-spacing: 0.5px; }

        /* Items Box */
        .order-item-box { background: var(--bg-light); border: 1px solid var(--border-color); padding: 18px 20px; border-radius: 16px; margin-bottom: 12px; transition: 0.3s; }
        .order-item-box:hover { border-color: var(--primary); }
        .item-name { font-weight: 800; color: var(--text-main); font-size: 1.1rem; text-transform: capitalize; margin-bottom: 8px; transition: 0.3s; }
        .item-bottom { font-size: 0.9rem; color: var(--text-muted); display: flex; justify-content: space-between; align-items: center; font-weight: 600; transition: 0.3s; }
        .item-price { font-weight: 800; color: var(--text-main); font-size: 1rem; }

        /* Admin Controls Form */
        .admin-desc { font-size: 0.95rem; color: var(--text-muted); margin-bottom: 20px; line-height: 1.5; font-weight: 500; transition: 0.3s; }
        .admin-update-form { display: flex; flex-direction: column; gap: 15px; margin-top: auto; }
        .status-select { padding: 15px 20px; border: 2px solid var(--border-color); border-radius: 12px; outline: none; font-size: 1rem; font-weight: 800; color: var(--text-main); background: var(--bg-light); cursor: pointer; transition: 0.3s; font-family: inherit; }
        .status-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .btn-update { background: var(--primary); color: #fff; border: none; padding: 16px; border-radius: 12px; font-size: 1rem; cursor: pointer; transition: 0.3s; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); font-family: inherit; }
        .btn-update:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        /* Empty State */
        .empty-state { text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: 28px; border: 2px dashed var(--border-color); transition: 0.3s; }
        .empty-state i { font-size: 5rem; color: var(--border-color); margin-bottom: 25px; transition: 0.3s; }
        .empty-state h3 { color: var(--text-main); font-size: 1.8rem; margin: 0 0 10px 0; font-weight: 900; transition: 0.3s; }
        .empty-state p { color: var(--text-muted); font-size: 1.1rem; margin: 0; font-weight: 500; transition: 0.3s; }

        /* Custom Logo Switcher Styles */
        .logo-light { display: block; }
        .logo-dark { display: none; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

        /* THEME BUTTON */
        .theme-btn { background: transparent; border: 2px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 800; color: var(--text-main); transition: color 0.3s ease; }

        /* =========================================================
           DARK MODE OVERRIDES
        ========================================================= */
        [data-theme="dark"] {
            --bg-light: #0f172a !important;
            --sidebar-bg: #1e293b !important;
            --text-main: #f8fafc !important;
            --text-muted: #94a3b8 !important;
            --border-color: rgba(255,255,255,0.05) !important;
            --card-bg: #1e293b !important;
        }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .admin-order-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important; border: 1px solid var(--border-color) !important; border-left-width: 6px !important; }
        [data-theme="dark"] .card-header, [data-theme="dark"] .order-item-box { background-color: var(--bg-light) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .tab-btn { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .tab-btn:hover { border-color: var(--primary) !important; }
        [data-theme="dark"] .tab-btn.active { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; }
        
        [data-theme="dark"] .status-select { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .status-select:focus { border-color: var(--primary) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .o-id, [data-theme="dark"] .buyer-name, [data-theme="dark"] .item-name { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .col-label { color: var(--text-muted); }
        [data-theme="dark"] .empty-state { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .admin-order-card, .card-header, .order-item-box, h1, h2, h3, p, span, div, .status-select { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; }

        /* =========================================================
           RESPONSIVE RULES (TABLET & MOBILE)
        ========================================================= */
        @media (max-width: 1200px) {
            .card-body-grid { grid-template-columns: 1fr 1fr; gap: 30px; }
            .middle-col { border-right: none; padding-right: 0; }
            .grid-col:last-child { grid-column: span 2; border-top: 2px dashed var(--border-color); padding-top: 30px; margin-top: 10px; }
            .admin-update-form { flex-direction: row; align-items: stretch; }
            .status-select { flex: 1; }
            .btn-update { flex: 1; }
        }

        @media (max-width: 768px) {
            .content-container { padding: 25px 20px; }
            .tabs-container { gap: 10px; }
            .tab-btn { padding: 12px 20px; font-size: 0.9rem; flex: 1; justify-content: center; }
            
            .admin-order-card { border-radius: 20px; }
            .card-header { padding: 20px; flex-direction: column; align-items: flex-start; }
            .header-left { flex-direction: column; align-items: flex-start; gap: 5px; }
            
            .card-body-grid { grid-template-columns: 1fr; padding: 20px; gap: 25px; }
            .middle-col { border-left: none; padding-left: 0; padding-top: 25px; border-top: 2px dashed var(--border-color); }
            .grid-col:last-child { grid-column: 1; }
            
            .admin-update-form { flex-direction: column; }
            .price-val { font-size: 1.2rem; }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <aside class="sidebar">
        <a href="dashboard.php" class="sidebar-logo">
            <img src="../assets/images/logo-light.png" alt="Farmogram" class="logo-light" style="width: 35px; height: 35px; object-fit: contain;">
    
            <img src="../assets/images/logo-dark.png" alt="Farmogram" class="logo-dark" style="width: 35px; height: 35px; object-fit: contain;">
    
            <span class="logo-text">Farmogram</span>
        </a>

        <ul class="nav-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Overview</a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> Manage Users</a></li>
            <li><a href="orders.php" class="active"><i class="fa-solid fa-boxes-stacked"></i> All Orders</a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
            <li><a href="claims.php"><i class="fa-solid fa-file-shield"></i> Disaster Claims</a></li>
            <li><a href="../auth/logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-wrapper">
        <header class="topbar" style="border: none;">
            <div class="page-title logo-text" style="font-size: 1.6rem;">Global Logistics</div>
            <div class="topbar-actions">
                
                <button class="theme-btn" id="themeToggle" title="Toggle Theme" style="margin-right: 15px;">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <div class="user-profile" style="border-left: none;">
                    <div style="text-align: right; margin-right: 10px;">
                        <div style="font-weight: 700; font-size: 1rem;" class="logo-text"><?php echo htmlspecialchars($user_name); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.8rem;">System Admin</div>
                    </div>
                    <div class="avatar" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);"><?php echo $avatar_letter; ?></div>
                </div>
            </div>
        </header>

        <div class="content-container">
            <?php if(isset($msg)) echo $msg; ?>
            
            <p style="color: var(--text-muted); margin-bottom: 30px; font-size: 1.05rem; font-weight: 500;">Monitor global fulfillment pipelines and override transaction statuses.</p>
            
            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('tab-standard', event)">
                    <i class="fa-solid fa-box" style="color: #3b82f6;"></i> Standard Sales 
                    <span class="tab-badge"><?php echo count($standard_orders); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('tab-preorder', event)">
                    <i class="fa-solid fa-file-contract" style="color: #f59e0b;"></i> Pre-Book Contracts 
                    <span class="tab-badge"><?php echo count($preorders); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('tab-cancelled', event)">
                    <i class="fa-solid fa-ban" style="color: #ef4444;"></i> Cancelled Orders 
                    <span class="tab-badge"><?php echo count($cancelled_orders); ?></span>
                </button>
            </div>

            <div id="tab-standard" class="tab-content active">
                <?php renderAdminCards($standard_orders); ?>
            </div>

            <div id="tab-preorder" class="tab-content">
                <?php renderAdminCards($preorders); ?>
            </div>

            <div id="tab-cancelled" class="tab-content">
                <?php renderAdminCards($cancelled_orders); ?>
            </div>

        </div>
    </main>
</div>

<script>
function switchTab(tabId, event) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

// THEME TOGGLE SCRIPT
document.addEventListener('DOMContentLoaded', () => {
    const themeToggleBtn = document.getElementById('themeToggle');
    const darkIcon = document.querySelector('.dark-icon');
    const lightIcon = document.querySelector('.light-icon');

    if(themeToggleBtn) {
        // Initial check
        if(document.documentElement.getAttribute('data-theme') === 'dark') {
            darkIcon.style.display = 'none'; lightIcon.style.display = 'inline-block';
        } else {
            darkIcon.style.display = 'inline-block'; lightIcon.style.display = 'none';
        }

        // Click listener
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

</body>
</html>