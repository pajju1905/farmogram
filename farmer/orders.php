<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer'){
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

$farmer_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

// Fetch all orders, including expected_harvest to calculate accurate deadlines
$result = $conn->query("
    SELECT orders.id AS order_id,
           orders.order_status,
           orders.created_at,
           orders.delivery_name,
           orders.delivery_address,
           orders.delivery_city,
           orders.delivery_pin,
           orders.delivery_phone,
           users.name AS buyer_name,
           crops.crop_name,
           crops.status AS crop_status,
           crops.expected_harvest,
           crops.market_price AS full_market_price,
           order_items.quantity,
           order_items.price AS paid_price
    FROM order_items
    JOIN crops ON order_items.crop_id = crops.id
    JOIN orders ON order_items.order_id = orders.id
    JOIN users ON orders.buyer_id = users.id
    WHERE crops.farmer_id = $farmer_id
    ORDER BY orders.id DESC
");

$standard_orders = [];
$contract_orders = [];
$cancelled_orders = [];

while($row = $result->fetch_assoc()){
    if($row['order_status'] == 'cancelled'){
        $cancelled_orders[] = $row;
    } elseif($row['crop_status'] == 'growing' || $row['paid_price'] < $row['full_market_price']) {
        $contract_orders[] = $row;
    } else {
        $standard_orders[] = $row;
    }
}

// Render Order Cards with ULTRA-MODERN BENTO UI & DEADLINE LOGIC
function renderOrderCards($orders_array, $is_cancelled_tab = false) {
    if(count($orders_array) == 0) {
        echo '<div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color); box-shadow: var(--shadow-subtle);">
                <i class="fa-solid fa-box-open" style="font-size: 4rem; color: var(--border-color); margin-bottom: 20px;"></i>
                <h3 style="color: var(--text-main); font-size: 1.6rem; margin-bottom: 10px; font-weight: 900;" class="logo-text">No Orders Found</h3>
                <p style="color: var(--text-muted); font-size: 1.05rem; font-weight: 500;">There are currently no orders matching this category.</p>
              </div>';
        return;
    }

    foreach($orders_array as $row) {
        $is_contract = ($row['crop_status'] == 'growing' || $row['paid_price'] < $row['full_market_price']);
        $total_value = $row['full_market_price'] * $row['quantity'];
        $amount_paid = $row['paid_price'] * $row['quantity'];
        $pending_balance = $total_value - $amount_paid;
        
        $status_clean = strtolower($row['order_status']);
        $status_color = '#3b82f6'; 
        if($status_clean == 'shipped') $status_color = '#f59e0b'; 
        if($status_clean == 'delivered') $status_color = '#10b981'; 
        if($is_cancelled_tab) $status_color = '#ef4444'; 

        /* Shipment Deadline Logic */
        $deadline_html = '';
        if($status_clean == 'processing' && !$is_cancelled_tab) {
            if($row['crop_status'] == 'growing') {
                $deadline_html = '<div style="color: #f59e0b; font-size: 0.9rem; font-weight: 800; margin-top: 15px; padding: 12px 18px; background: rgba(245, 158, 11, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(245, 158, 11, 0.2); display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-seedling"></i> Awaiting Harvest (Est: '.date('M j', strtotime($row['expected_harvest'])).')</div>';
            } else {
                $deadline_timestamp = strtotime('+2 days', strtotime($row['created_at']));
                if(time() > $deadline_timestamp) {
                    $deadline_html = '<div style="color: #ef4444; font-size: 0.9rem; font-weight: 800; margin-top: 15px; padding: 12px 18px; background: rgba(239, 68, 68, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(239, 68, 68, 0.3); animation: pulse 2s infinite; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-triangle-exclamation"></i> URGENT: Shipment Delayed!</div>';
                } else {
                    $deadline_html = '<div style="color: #3b82f6; font-size: 0.9rem; font-weight: 800; margin-top: 15px; padding: 12px 18px; background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(59, 130, 246, 0.2); display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-truck-fast"></i> Ship by: '.date('M j, Y', $deadline_timestamp).'</div>';
                }
            }
        }

        echo '<div class="bento-order-card" data-status="'.$status_clean.'" style="border-left-color: '.$status_color.';">';
        
        echo '<div class="card-header">';
        echo '  <div style="display: flex; align-items: center; gap: 15px; flex-wrap: wrap;">
                    <div class="o-id logo-text">#ORD-'.$row['order_id'].'</div>
                    <div class="o-date text-muted"><i class="fa-regular fa-clock"></i> '.date('M j, Y', strtotime($row['created_at'])).'</div>
                </div>';
        if($is_contract) echo '<div class="order-type-badge type-contract"><i class="fa-solid fa-file-contract"></i> Pre-Book Contract</div>';
        else echo '<div class="order-type-badge type-standard"><i class="fa-solid fa-box"></i> Standard Sale</div>';
        echo '</div>';

        echo '<div class="card-body-grid">';
        
        // Col 1: Details
        echo '  <div class="grid-col">
                    <div class="col-label">Order Details</div>
                    <div class="detail-row"><i class="fa-solid fa-seedling icon-muted"></i> <span style="text-transform: capitalize; font-weight: 900; font-size: 1.1rem; color: var(--text-main);">'.htmlspecialchars($row['crop_name']).' ('.$row['quantity'].' kg)</span></div>
                    <div class="detail-row"><i class="fa-solid fa-user-tag icon-muted"></i> <span style="color: var(--text-main); font-weight: 600;">Buyer: '.htmlspecialchars($row['buyer_name']).'</span></div>';
        
        if(!empty($row['delivery_address'])) {
            $full_address_url = urlencode($row['delivery_address'] . ', ' . $row['delivery_city'] . ' ' . $row['delivery_pin']);
            echo '  <div class="detail-row" style="margin-top: 15px; border-top: 1px dashed var(--border-color); padding-top: 15px; align-items: flex-start;">
                        <i class="fa-solid fa-location-dot icon-muted" style="color: var(--primary); margin-top: 4px;"></i>
                        <div style="display: flex; flex-direction: column; width: 100%;">
                            <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-main);">Ship To: '.htmlspecialchars($row['delivery_name']).'</span>
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500; margin-top: 2px;">'.htmlspecialchars($row['delivery_address']).', '.htmlspecialchars($row['delivery_city']).' - '.htmlspecialchars($row['delivery_pin']).'</span>
                            <span style="color: var(--text-muted); font-size: 0.85rem; font-weight: 500; margin-top: 4px; margin-bottom: 10px;"><i class="fa-solid fa-phone" style="font-size:0.75rem;"></i> '.htmlspecialchars($row['delivery_phone']).'</span>
                            <div style="width: 100%; height: 140px; border-radius: 12px; overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle);">
                                <iframe width="100%" height="100%" frameborder="0" scrolling="no" marginheight="0" marginwidth="0" src="https://maps.google.com/maps?q='.$full_address_url.'&output=embed&z=14"></iframe>
                            </div>
                        </div>
                    </div>';
        }
        echo '  </div>';

        // Col 2: Financials
        echo '  <div class="grid-col">
                    <div class="col-label">Financial Breakdown</div>';
        if($is_cancelled_tab) {
            echo '  <div class="detail-row" style="color: #ef4444; font-weight: 800;"><i class="fa-solid fa-rotate-left"></i> Refund Initiated</div>';
            echo '  <div class="detail-row" style="color: var(--text-muted); font-weight: 600;">Amount Returned: ₹ '.number_format($amount_paid, 2).'</div>';
        } else {
            if($is_contract) {
                if($pending_balance <= 0) {
                    echo '<div class="detail-row" style="color: #10b981; font-weight: 800;"><i class="fa-solid fa-check-circle"></i> Paid in Full</div>';
                    echo '<div class="detail-row"><span style="color: var(--text-muted); font-weight: 600; width: 80px; display: inline-block;">Total:</span> <span style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); letter-spacing: -0.5px;">₹ '.number_format($total_value, 2).'</span></div>';
                } else {
                    echo '<div class="detail-row"><span style="color: var(--text-muted); font-weight: 600; width: 80px; display: inline-block;">Advance:</span> <span style="font-weight: 800; color: var(--text-main);">₹ '.number_format($amount_paid, 2).'</span></div>';
                    echo '<div class="detail-row"><span style="color: var(--text-muted); font-weight: 600; width: 80px; display: inline-block;">Pending:</span> <span style="font-weight: 900; color: #f59e0b;">₹ '.number_format($pending_balance, 2).'</span></div>';
                    echo '<div class="detail-row" style="border-top: 1px dashed var(--border-color); padding-top: 10px; margin-top: 10px;"><span style="color: var(--text-muted); font-weight: 600; width: 80px; display: inline-block;">Total:</span> <span style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); letter-spacing: -0.5px;">₹ '.number_format($total_value, 2).'</span></div>';
                }
            } else {
                echo '<div class="detail-row" style="color: #10b981; font-weight: 800;"><i class="fa-solid fa-check-circle"></i> Paid in Full</div>';
                echo '<div class="detail-row"><span style="color: var(--text-muted); font-weight: 600; width: 80px; display: inline-block;">Total:</span> <span style="font-weight: 900; font-size: 1.2rem; color: var(--text-main); letter-spacing: -0.5px;">₹ '.number_format($total_value, 2).'</span></div>';
            }
        }
        echo '  </div>';

        // Col 3: Status & Action
        echo '  <div class="grid-col">
                    <div class="col-label">Fulfillment Status</div>';
        if($is_cancelled_tab) {
            echo '  <div class="order-type-badge" style="background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); padding: 10px 18px; font-size: 0.95rem;"><i class="fa-solid fa-ban"></i> Cancelled</div>';
        } else {
            echo '  <div style="font-weight: 900; font-size: 1.2rem; color: '.$status_color.'; text-transform: capitalize; margin-bottom: 12px; display: flex; align-items: center; gap: 8px;">
                        <i class="fa-solid fa-circle-dot" style="font-size: 0.9rem;"></i> '.$row['order_status'].'
                    </div>';
            
            if($status_clean !== 'delivered') {
                echo '  <form method="POST" action="update_order_status.php" class="update-form">
                            <input type="hidden" name="order_id" value="'.$row['order_id'].'">
                            <select name="status" class="status-select">
                                <option value="processing" '.($status_clean=='processing'?'selected':'').'>Processing</option>
                                <option value="shipped" '.($status_clean=='shipped'?'selected':'').'>Shipped</option>
                                <option value="delivered" '.($status_clean=='delivered'?'selected':'').'>Delivered</option>
                            </select>
                            <button class="btn-update">Update</button>
                        </form>';
            }
            
            // Print the Deadline Reminder right below the status form!
            echo $deadline_html;
        }
        echo '  </div>';
        
        echo '</div>'; // End Body Grid
        echo '</div>'; // End Card
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Sales Orders & Contracts | Farmogram</title>
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
           ULTRA-MODERN GLASS-BENTO UI DESIGN (PC & MOBILE)
        ========================================================= */
        :root {
            --radius-xl: 32px;
            --radius-lg: 24px;
            --radius-md: 16px;
            --shadow-subtle: 0 8px 30px rgba(0,0,0,0.04);
            --shadow-hover: 0 15px 35px rgba(0,0,0,0.08);
            --app-nav-height: 75px;
        }

        .content-container { padding: 30px 40px; max-width: 1600px; margin: 0 auto; }

        /* Custom Translation Widget Styling - Force Pill Shape */
        .goog-te-gadget-simple {
            background: var(--card-bg) !important;
            border: 1px solid var(--border-color) !important;
            border-radius: 50px !important;
            padding: 8px 16px !important;
            font-size: 0.9rem !important;
            font-weight: 800 !important;
            color: var(--text-main) !important;
            display: inline-flex !important;
            align-items: center;
            box-shadow: var(--shadow-subtle);
            transition: 0.3s;
        }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* The New Filter Bar */
        .filter-bar { display: flex; justify-content: space-between; align-items: center; background: var(--card-bg); padding: 22px 28px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 30px; box-shadow: var(--shadow-subtle); transition: 0.3s; flex-wrap: wrap; gap: 15px; }
        .filter-select { padding: 14px 22px; border-radius: var(--radius-md); border: 2px solid var(--border-color); outline: none; font-weight: 800; cursor: pointer; background: var(--bg-light); color: var(--text-main); font-family: inherit; transition: 0.3s; font-size: 1rem; }
        .filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }

        /* Modern Pill Tabs */
        .tabs-container { display: flex; gap: 15px; margin-bottom: 35px; flex-wrap: wrap; }
        .tab-btn { background: var(--card-bg); border: 2px solid var(--border-color); padding: 14px 28px; border-radius: 50px; font-size: 1rem; font-weight: 800; color: var(--text-muted); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        .tab-btn:hover { border-color: var(--primary); color: var(--text-main); transform: translateY(-2px); }
        
        .tab-btn.active { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important; }
        .tab-btn.active i { color: #fff !important; }
        .tab-btn.active span { background: rgba(255, 255, 255, 0.2) !important; color: #fff !important; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Bento Order Cards */
        .bento-order-card { 
            background: var(--card-bg); 
            border-radius: var(--radius-xl); 
            border: 1px solid var(--border-color); 
            border-left-width: 6px; 
            box-shadow: var(--shadow-subtle); 
            margin-bottom: 24px; 
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
            position: relative; 
        }
        .bento-order-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); }
        
        .card-header { background: var(--bg-light); padding: 22px 28px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; transition: 0.3s; border-radius: var(--radius-xl) var(--radius-xl) 0 0; }
        .o-id { font-size: 1.25rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .o-date { font-size: 0.95rem; color: var(--text-muted); font-weight: 700; transition: 0.3s; }
        
        .card-body-grid { display: grid; grid-template-columns: 1.2fr 1fr 1fr; gap: 30px; padding: 30px; }
        
        .grid-col { display: flex; flex-direction: column; }
        .col-label { font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: var(--text-muted); font-weight: 900; margin-bottom: 15px; transition: 0.3s; }
        .detail-row { margin-bottom: 10px; font-size: 1.05rem; color: var(--text-main); display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .icon-muted { color: var(--text-muted); width: 22px; text-align: center; }

        .order-type-badge { padding: 10px 16px; border-radius: 50px; font-size: 0.85rem; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 8px; width: fit-content; line-height: 1; backdrop-filter: blur(4px); }
        .type-contract { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); }
        .type-standard { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); }

        .update-form { display: flex; gap: 12px; align-items: center; background: var(--bg-light); padding: 15px; border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-top: auto; transition: 0.3s; }
        .status-select { flex: 1; padding: 12px 15px; border: 2px solid var(--border-color); border-radius: 12px; outline: none; font-size: 0.95rem; font-weight: 800; font-family: inherit; color: var(--text-main); background: var(--card-bg); cursor: pointer; transition: 0.3s; }
        .status-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        
        .btn-update { background: var(--primary) !important; color: #fff !important; border: none; padding: 14px 22px; border-radius: 12px; font-size: 0.95rem; cursor: pointer; transition: 0.3s; font-weight: 900; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-update:hover { background: var(--primary-hover) !important; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        /* Sidebar Desktop Logo */
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }

        /* Mobile Topbar Branding (Hidden on Desktop) */
        .mobile-brand { display: none; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        .mobile-brand span { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        /* Topbar Controls */
        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        /* Dynamic Logo Switcher */
        .logo-light { display: block; width: 35px; height: 35px; object-fit: contain; }
        .logo-dark { display: none; width: 35px; height: 35px; object-fit: contain; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

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
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.85) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-order-card, [data-theme="dark"] .filter-bar { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Form Inputs & Tabs in Dark Mode */
        [data-theme="dark"] .filter-select, [data-theme="dark"] .status-select { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .filter-select:focus, [data-theme="dark"] .status-select:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        [data-theme="dark"] .update-form, [data-theme="dark"] .card-header { background: rgba(0,0,0,0.15) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .tab-btn { background: var(--sidebar-bg); border-color: var(--border-color); color: var(--text-muted); }
        [data-theme="dark"] .tab-btn:hover { border-color: var(--primary); color: var(--text-main); }
        
        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-order-card, .filter-bar, h1, h2, h3, p, span, div, .filter-select, .status-select { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .card-body-grid { grid-template-columns: 1fr 1fr; gap: 25px; }
            .grid-col:last-child { grid-column: span 2; }
            .update-form { flex-direction: row; }
        }

        @media (max-width: 768px) {
            .app-layout { display: block; height: 100dvh; overflow: hidden; }
            
            /* Hide Desktop Logo */
            .sidebar-logo { display: none; } 
            
            /* Show Mobile Logo in Topbar */
            .mobile-brand { display: flex; flex: 1; }
            
            /* Topbar Redesign for Mobile */
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
            .page-title { display: none; } /* Hide text title to make room for Brand Logo */
            .topbar-actions { gap: 8px; flex-wrap: nowrap; }
            .user-profile .logo-text, .user-profile .text-muted { display: none; } /* Only show Avatar */
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; }
            .avatar { width: 38px; height: 38px; font-size: 1rem; }

            /* Ensure Google Translate scales perfectly on Mobile */
            .goog-te-gadget-simple { padding: 6px 12px !important; font-size: 0.8rem !important; }

            /* Sleek Swipeable Bottom App Nav */
            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); border-right: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; scroll-snap-type: x mandatory; gap: 6px; justify-content: flex-start; -webkit-overflow-scrolling: touch; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu li { flex: 0 0 auto; scroll-snap-align: center; }
            
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; transition: 0.3s; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }

            /* 100dvh Scroll Fix */
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); overflow-y: auto; overflow-x: hidden; padding-bottom: 25px; }
            .content-container { padding: 20px; }
            
            .filter-bar { flex-direction: column; align-items: stretch; padding: 20px; border-radius: var(--radius-lg); }
            .filter-select { width: 100%; }
            .tabs-container { gap: 10px; }
            .tab-btn { flex: 1; justify-content: center; padding: 12px 15px; font-size: 0.9rem; }
            
            .card-body-grid { grid-template-columns: 1fr; padding: 20px; gap: 20px; }
            .grid-col:last-child { grid-column: span 1; }
            .update-form { flex-direction: column; align-items: stretch; padding: 15px; }
            .btn-update { width: 100%; justify-content: center; padding: 14px; font-size: 1rem; }
            .card-header { flex-direction: column; align-items: flex-start; padding: 20px; }
            .order-type-badge { width: 100%; justify-content: center; }
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
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> <span>Home</span></a></li>
            <li><a href="view_crops.php"><i class="fa-solid fa-leaf"></i> <span>Inventory</span></a></li>
            <li><a href="orders.php" class="active"><i class="fa-solid fa-cart-flatbed"></i> <span>Orders</span></a></li>
            <li><a href="enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
            <li><a href="tasks.php"><i class="fa-solid fa-calendar-check"></i> <span>Tasks</span></a></li> 
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="assistant.php"><i class="fa-solid fa-robot"></i> <span>AI Bot</span></a></li>
            <li><a href="schemes.php"><i class="fa-solid fa-landmark-dome"></i> <span>Govt</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Sales & Logistics</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <button class="theme-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <a href="profile.php" class="user-profile" style="border-left: none; display: flex; align-items: center; text-decoration: none; padding-left: 0;">
                    <div style="text-align: right; margin-right: 10px;" class="desktop-only-text">
                        <div style="font-weight: 800; font-size: 0.95rem;" class="logo-text"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">Farmer</div>
                    </div>
                    <?php if(!empty($profile_image) && file_exists("../uploads/profile/" . $profile_image)): ?>
                        <div class="avatar" style="border: 1px solid rgba(16, 185, 129, 0.3); overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><img src="../uploads/profile/<?php echo $profile_image; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"></div>
                    <?php else: ?>
                        <div class="avatar" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 900; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0;"><?php echo $avatar_letter; ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="content-container">
            
            <div class="filter-bar">
                <div style="font-weight: 900; font-size: 1.15rem;" class="logo-text"><i class="fa-solid fa-filter" style="color: var(--primary); margin-right: 8px;"></i> Smart Filter</div>
                <select id="statusFilter" class="filter-select" onchange="filterOrders()">
                    <option value="all">View All Active Orders</option>
                    <option value="processing">Action Required: Processing</option>
                    <option value="shipped">In Transit: Shipped</option>
                    <option value="delivered">Completed: Delivered</option>
                </select>
            </div>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('tab-contract')">
                    <i class="fa-solid fa-file-contract" style="color: #f59e0b;"></i> Pre-Book Contracts 
                    <span style="background: rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;"><?php echo count($contract_orders); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('tab-standard')">
                    <i class="fa-solid fa-box" style="color: #3b82f6;"></i> Standard Sales 
                    <span style="background: rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;"><?php echo count($standard_orders); ?></span>
                </button>
                <button class="tab-btn" onclick="switchTab('tab-cancelled')">
                    <i class="fa-solid fa-ban" style="color: #ef4444;"></i> Cancelled / Refunded 
                    <span style="background: rgba(0,0,0,0.05); padding: 4px 10px; border-radius: 20px; font-size: 0.8rem;"><?php echo count($cancelled_orders); ?></span>
                </button>
            </div>

            <div id="tab-contract" class="tab-content active">
                <?php renderOrderCards($contract_orders); ?>
            </div>

            <div id="tab-standard" class="tab-content">
                <?php renderOrderCards($standard_orders); ?>
            </div>

            <div id="tab-cancelled" class="tab-content">
                <?php renderOrderCards($cancelled_orders, true); ?>
            </div>

        </div>
    </main>
</div>

<script>
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
    
    // Reset the filter dropdown when switching tabs
    document.getElementById('statusFilter').value = 'all';
    filterOrders();
}

// JAVASCRIPT LIVE FILTER ENGINE
function filterOrders() {
    let val = document.getElementById('statusFilter').value;
    let cards = document.querySelectorAll('.bento-order-card');
    
    cards.forEach(card => {
        // Only filter the cards that are inside the CURRENTLY ACTIVE tab!
        if (card.closest('.tab-content.active')) {
            if(val === 'all' || card.getAttribute('data-status') === val) {
                card.style.display = 'block';
            } else {
                card.style.display = 'none';
            }
        }
    });
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
            let targetTheme = document.documentElement.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
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

<script src="../assets/js/translate.js"></script>

</body>
</html>
