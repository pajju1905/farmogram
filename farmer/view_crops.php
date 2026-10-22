<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK FOR FARMER
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

// Query current verification status
$status_query = $conn->query("SELECT verification_status, verification_remarks FROM users WHERE id = $farmer_id");
$user_status = $status_query->fetch_assoc();
$verification_status = $user_status['verification_status'] ?? 'unverified';
$verification_remarks = $user_status['verification_remarks'] ?? '';

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$msg = "";

// Gate POST actions for unverified/pending/rejected farmers
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    if($verification_status !== 'verified'){
        if(isset($_POST['enable_prebook']) || isset($_POST['mark_harvested']) || isset($_POST['mark_for_sale']) || isset($_POST['update_crop'])) {
            $msg = "<div class='alert-error'><i class='fa-solid fa-lock'></i> Action locked. Please complete verification first.</div>";
            // Unset these to prevent processing
            unset($_POST['enable_prebook']);
            unset($_POST['mark_harvested']);
            unset($_POST['mark_for_sale']);
            unset($_POST['update_crop']);
        }
    }
}

// Auto-Failsafe: Silently ensure 'quantity' and 'acreage' columns exist for the new inventory features
//@$conn->query("ALTER TABLE crops ADD COLUMN quantity DECIMAL(10,2) DEFAULT 0");
//@$conn->query("ALTER TABLE crops ADD COLUMN acreage DECIMAL(10,2) DEFAULT 0");

/* =========================================
   HANDLE CROP ARCHIVING (SOFT DELETE)
 ========================================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_crop'])){
    $crop_id = (int)$_POST['crop_id'];
    $stmt = $conn->prepare("UPDATE crops SET status = 'archived' WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $crop_id, $farmer_id);
    if($stmt->execute()){
        $msg = "<div class='alert-success'><i class='fa-solid fa-box-archive'></i> Crop successfully removed from marketplace. All historical sales data has been preserved.</div>";
    }
    $stmt->close();
}

/* =========================================
   NEW: HANDLE ENABLE PRE-BOOKING IN MARKET
 ========================================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['enable_prebook'])){
    $crop_id = (int)$_POST['crop_id'];
    $prebook_qty = (float)$_POST['prebook_qty'];
    
    $stmt = $conn->prepare("UPDATE crops SET quantity = ? WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("dii", $prebook_qty, $crop_id, $farmer_id);
    if($stmt->execute()){
        $msg = "<div class='alert-success'><i class='fa-solid fa-bullhorn'></i> Pre-bookings enabled! Buyers can now reserve this crop in the marketplace.</div>";
    }
    $stmt->close();
}

/* =========================================
   HANDLE MARK AS HARVESTED (SMART YIELD)
 ========================================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_harvested'])){
    $crop_id = (int)$_POST['crop_id'];
    $actual_yield = (float)$_POST['actual_yield'];

    // SMART INVENTORY LOGIC: Calculate how much was ALREADY pre-sold to buyers
    $sold_query = $conn->query("
        SELECT SUM(order_items.quantity) as sold 
        FROM order_items 
        JOIN orders ON order_items.order_id = orders.id 
        WHERE order_items.crop_id = $crop_id AND orders.order_status != 'cancelled'
    ");
    $sold_qty = $sold_query->fetch_assoc()['sold'] ?? 0;
    
    // The remaining stock to sell is Actual Yield minus whatever was already pre-booked
    $remaining_stock = max(0, $actual_yield - $sold_qty);

    $stmt = $conn->prepare("UPDATE crops SET status = 'harvested', actual_yield = ?, quantity = ? WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ddii", $actual_yield, $remaining_stock, $crop_id, $farmer_id);
    
    if($stmt->execute()){
        $msg = "<div class='alert-success'><i class='fa-solid fa-check-double'></i> Crop harvested! Live stock updated to ".$remaining_stock." kg (accounting for pre-sales).</div>";
    }
    $stmt->close();
}

/* =========================================
   HANDLE LIST IN MARKETPLACE (FULL SALE)
 ========================================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_for_sale'])){
    $crop_id = (int)$_POST['crop_id'];
    $stmt = $conn->prepare("UPDATE crops SET status = 'for_sale' WHERE id = ? AND farmer_id = ?");
    $stmt->bind_param("ii", $crop_id, $farmer_id);
    if($stmt->execute()){
        $msg = "<div class='alert-success'><i class='fa-solid fa-store'></i> Success! Crop is now live in the public marketplace for immediate purchase.</div>";
    }
    $stmt->close();
}

/* =========================================
   HANDLE CROP UPDATE 
 ========================================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_crop'])){
    $crop_id = (int)$_POST['crop_id'];
    $crop_name = $conn->real_escape_string($_POST['crop_name']);
    $investment = (float)$_POST['investment'];
    $market_price = (float)$_POST['market_price'];
    $sowing_date = $conn->real_escape_string($_POST['sowing_date']);
    $expected_harvest = $conn->real_escape_string($_POST['expected_harvest']);
    $quantity = (float)$_POST['quantity'];
    $acreage = (float)$_POST['acreage'];
    
    $image_update_query = "";
    if(isset($_FILES['crop_image']) && $_FILES['crop_image']['error'] == 0){
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $ext = strtolower(pathinfo($_FILES["crop_image"]["name"], PATHINFO_EXTENSION));
        $image_path = "crop_" . time() . "_" . rand(100, 999) . "." . $ext;
        if(move_uploaded_file($_FILES["crop_image"]["tmp_name"], $target_dir . $image_path)){
            $image_update_query = ", image = '$image_path'";
        }
    }
    
    $update_query = "UPDATE crops SET crop_name = '$crop_name', investment = '$investment', market_price = '$market_price', sowing_date = '$sowing_date', expected_harvest = '$expected_harvest', quantity = '$quantity', acreage = '$acreage'$image_update_query WHERE id = $crop_id AND farmer_id = $farmer_id";
    if($conn->query($update_query)){
        $msg = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Crop details and inventory updated successfully!</div>";
    }
}

/* =======================================================
   FETCH PRESERVED ANALYTICS 
 ======================================================= */
$stats_query = $conn->query("
    SELECT 
        SUM(oi.quantity * oi.price) AS lifetime_revenue,
        SUM(oi.quantity) AS lifetime_volume,
        COUNT(DISTINCT oi.order_id) AS total_orders
    FROM order_items oi
    JOIN crops c ON oi.crop_id = c.id
    JOIN orders o ON oi.order_id = o.id
    WHERE c.farmer_id = $farmer_id AND o.order_status != 'cancelled'
");
$stats = $stats_query->fetch_assoc();
$lifetime_revenue = $stats['lifetime_revenue'] ?? 0;
$lifetime_volume = $stats['lifetime_volume'] ?? 0;
$total_orders_count = $stats['total_orders'] ?? 0;

$active_listings = $conn->query("SELECT COUNT(id) AS count FROM crops WHERE farmer_id = $farmer_id AND status != 'archived'")->fetch_assoc()['count'] ?? 0;

$query = "SELECT * FROM crops WHERE farmer_id = $farmer_id AND status != 'archived' ORDER BY id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Crops | Farmogram</title>
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

        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Stats Banner */
        .stats-banner { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 35px; }
        .stat-card { background: var(--card-bg); padding: 25px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-subtle); transition: 0.4s; }
        .stat-card:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: var(--shadow-hover); }
        .icon-box { width: 65px; height: 65px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-info .val { font-size: 2rem; font-weight: 900; color: var(--text-main); line-height: 1; margin-bottom: 6px; transition: 0.3s; letter-spacing: -1px; }
        .stat-info .lbl { font-size: 0.85rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 25px; }
        
        .bento-crop-card { background: var(--card-bg); border-radius: var(--radius-xl); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; position: relative; }
        .bento-crop-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .crop-img-wrapper { position: relative; width: 100%; height: 220px; overflow: hidden; border-bottom: 1px solid var(--border-color); }
        .crop-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; }
        .bento-crop-card:hover .crop-img { transform: scale(1.05); }
        
        .badge-status { position: absolute; top: 15px; right: 15px; font-size: 0.75rem; padding: 8px 14px; border-radius: 20px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 10px rgba(0,0,0,0.15); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); }
        .status-growing { background: rgba(245, 158, 11, 0.95); color: #fff; }
        .status-harvested { background: rgba(59, 130, 246, 0.95); color: #fff; }
        .status-sale { background: rgba(16, 185, 129, 0.95); color: #fff; }

        /* Available Stock Badge overlays */
        .stock-badge { position: absolute; bottom: 15px; left: 15px; font-size: 0.85rem; padding: 8px 14px; border-radius: 12px; font-weight: 900; color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.2); backdrop-filter: blur(5px); z-index: 2; border: 1px solid rgba(255,255,255,0.1); }
        .stock-prebook { background: rgba(59, 130, 246, 0.85); }
        .stock-live { background: rgba(15, 23, 42, 0.85); }

        .card-body { padding: 25px 24px; display: flex; flex-direction: column; flex: 1; }
        .crop-title { font-size: 1.5rem; font-weight: 900; margin: 0 0 15px 0; color: var(--text-main); text-transform: capitalize; letter-spacing: -0.5px; transition: 0.3s;}
        
        .stat-row { display: flex; justify-content: space-between; font-size: 0.95rem; margin-bottom: 10px; color: var(--text-muted); border-bottom: 1px dashed var(--border-color); padding-bottom: 8px; transition: 0.3s; }
        .stat-row strong { color: var(--text-main); font-weight: 800; }
        
        .progress-wrapper { background: var(--bg-light); border-radius: 20px; height: 12px; overflow: hidden; margin: 15px 0 15px 0; box-shadow: inset 0 1px 2px rgba(0,0,0,0.05); border: 1px solid var(--border-color); }
        .progress-fill { background: linear-gradient(90deg, #10b981, #059669); height: 100%; border-radius: 20px; transition: width 1s ease; }
        
        .card-actions { margin-top: auto; padding-top: 15px; display: flex; gap: 10px; flex-direction: column; }
        
        .btn-sm { padding: 12px 15px; font-size: 0.9rem; text-align: center; border-radius: var(--radius-md); border: none; cursor: pointer; font-weight: 800; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px; text-decoration: none; box-shadow: var(--shadow-subtle); font-family: inherit; }
        
        .btn-live { background: rgba(59, 130, 246, 0.1) !important; color: #3b82f6 !important; border: 1px solid rgba(59, 130, 246, 0.2) !important; }
        .btn-live:hover { background: #3b82f6 !important; color: #fff !important; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.3); }

        .btn-action-main { background: var(--primary) !important; color: #fff !important; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); }
        .btn-action-main:hover { background: var(--primary-hover) !important; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3); }
        
        .btn-action-blue { background: #3b82f6 !important; color: #fff !important; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); }
        .btn-action-blue:hover { background: #2563eb !important; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3); }

        .btn-edit { background: var(--card-bg) !important; color: var(--text-main) !important; border: 1px solid var(--border-color) !important; }
        .btn-edit:hover { background: var(--bg-light) !important; border-color: var(--primary) !important; color: var(--primary) !important; }

        .btn-delete { background: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important; border: 1px solid rgba(239, 68, 68, 0.2) !important; }
        .btn-delete:hover { background: #ef4444 !important; color: #fff !important; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }

        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 15px 20px; border-radius: var(--radius-md); margin-bottom: 25px; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 15px 20px; border-radius: var(--radius-md); margin-bottom: 25px; font-weight: 700; border: 1px solid rgba(239, 68, 68, 0.2); display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }

        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }

        .mobile-brand { display: none; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        .mobile-brand span { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        .logo-light { display: block; width: 35px; height: 35px; object-fit: contain; }
        .logo-dark { display: none; width: 35px; height: 35px; object-fit: contain; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

        /* BENTO MODAL STYLES */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.8); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; }
        .modal-content { background: var(--card-bg); width: 100%; max-width: 600px; border-radius: var(--radius-xl); padding: 35px; position: relative; box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5); border: 1px solid var(--border-color); transition: 0.3s; }
        .modal-close { position: absolute; top: 25px; right: 25px; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); transition: 0.2s; width: 40px; height: 40px; background: var(--bg-light); border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); }
        .modal-close:hover { color: #ef4444; background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.2); }
        
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 800; margin-bottom: 8px; font-size: 0.95rem; color: var(--text-main); }
        .form-input { width: 100%; padding: 14px 18px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); border-radius: var(--radius-md); outline: none; font-size: 1rem; font-family: inherit; font-weight: 600; transition: 0.3s; }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        .btn-update { background: var(--primary); color: #fff; border: none; padding: 16px; width: 100%; border-radius: var(--radius-md); font-weight: 800; font-size: 1.05rem; cursor: pointer; margin-top: 15px; transition: 0.3s; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); display: flex; justify-content: center; align-items: center; gap: 10px; }
        .btn-update:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        /* DARK MODE OVERRIDES */
        [data-theme="dark"] { --bg-light: #0f172a !important; --sidebar-bg: #1e293b !important; --text-main: #f8fafc !important; --text-muted: #94a3b8 !important; --border-color: rgba(255,255,255,0.05) !important; --card-bg: #1e293b !important; }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.8) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-crop-card, [data-theme="dark"] .modal-content, [data-theme="dark"] .stat-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .form-label, [data-theme="dark"] .val, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .lbl { color: var(--text-muted); }
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }
        [data-theme="dark"] .form-input { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .form-input:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        
        body, .sidebar, .topbar, .bento-crop-card, .modal-content, .stat-card, h1, h2, h3, p, span, div, .form-input { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        @media (max-width: 1024px) { .stats-banner { grid-template-columns: repeat(2, 1fr); } }

        @media (max-width: 768px) {
            .app-layout { display: block; height: 100dvh; overflow: hidden; }
            .sidebar-logo { display: none; } 
            .mobile-brand { display: flex; flex: 1; }
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; }
            .user-profile .logo-text, .user-profile .text-muted { display: none; } 
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; }
            .avatar { width: 38px; height: 38px; font-size: 1rem; }
            .goog-te-gadget-simple { padding: 6px 12px !important; font-size: 0.8rem !important; }

            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); border-right: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; scroll-snap-type: x mandatory; gap: 6px; justify-content: flex-start; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu li { flex: 0 0 auto; scroll-snap-align: center; }
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }

            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); overflow-y: auto; overflow-x: hidden; padding-bottom: 25px; }
            .content-container { padding: 25px 20px; }
            .stats-banner { grid-template-columns: 1fr; gap: 15px; }
            .product-grid { grid-template-columns: 1fr; }
            .stat-card { padding: 20px; border-radius: var(--radius-lg); }

            .modal-content { padding: 25px 20px; margin: 15px; max-height: 85vh; overflow-y: auto; }
            .modal-content h2 { font-size: 1.4rem; padding-right: 30px; }
            .modal-close { top: 15px; right: 15px; width: 32px; height: 32px; font-size: 1.2rem; }
            .form-group { margin-bottom: 15px; }
            .form-input { padding: 12px 15px; }
            div[style*="grid-template-columns: 1fr 1fr"] { grid-template-columns: 1fr !important; gap: 15px !important; }
        }

        /* Premium Lock Screen Styles */
        .lock-container {
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 60px 20px;
            min-height: 500px;
        }
        .lock-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-xl);
            padding: 50px 40px;
            max-width: 550px;
            width: 100%;
            text-align: center;
            box-shadow: var(--shadow-hover);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 25px;
            position: relative;
            overflow: hidden;
        }
        .lock-card::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.05) 0%, transparent 70%);
            pointer-events: none;
        }
        .lock-icon-wrapper {
            width: 90px;
            height: 90px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3rem;
            margin-bottom: 10px;
            box-shadow: var(--shadow-subtle);
        }
        .lock-icon-unverified {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        .lock-icon-pending {
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            border: 1px solid rgba(245, 158, 11, 0.2);
            animation: pulse-hourglass 2s infinite ease-in-out;
        }
        .lock-icon-rejected {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        @keyframes pulse-hourglass {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.08) rotate(180deg); }
        }
        .lock-title {
            font-size: 2rem;
            font-weight: 900;
            color: var(--text-main);
            margin: 0;
            letter-spacing: -0.5px;
        }
        .lock-desc {
            font-size: 1.05rem;
            color: var(--text-muted);
            line-height: 1.6;
            margin: 0;
            font-weight: 500;
        }
        .lock-remarks {
            background: rgba(239, 68, 68, 0.05);
            border: 1px solid rgba(239, 68, 68, 0.1);
            color: #ef4444;
            padding: 15px 20px;
            border-radius: var(--radius-md);
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.5;
            width: 100%;
            text-align: left;
            margin-top: 5px;
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
            <li><a href="view_crops.php" class="active"><i class="fa-solid fa-leaf"></i> <span>Inventory</span></a></li>
            <li><a href="tasks.php"><i class="fa-solid fa-calendar-check"></i> <span>Tasks</span></a></li> 
            <li><a href="orders.php"><i class="fa-solid fa-cart-flatbed"></i> <span>Orders</span></a></li>
            <li><a href="enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.5rem;">Digital Farm Inventory</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <?php if($verification_status === 'verified'): ?>
                    <a href="add_crop.php" class="btn-action-main" style="padding: 10px 20px; border-radius: 50px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; transition: 0.2s; border: none;">
                        <i class="fa-solid fa-plus"></i> Plant New
                    </a>
                <?php else: ?>
                    <a href="verify.php" class="btn-action-main" style="padding: 10px 20px; border-radius: 50px; font-weight: 800; text-decoration: none; display: flex; align-items: center; gap: 8px; font-size: 0.95rem; transition: 0.2s; border: none; background: #64748b !important;">
                        <i class="fa-solid fa-shield-halved"></i> Verify to Plant
                    </a>
                <?php endif; ?>

                <a href="enquiries.php" class="theme-btn" style="text-decoration: none;" title="Messages">
                    <i class="fa-solid fa-envelope"></i>
                </a>

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
            <?php echo $msg; ?>
            
            <?php if($verification_status === 'verified'): ?>
                <!-- Stats Banner -->
                <div class="stats-banner">
                    <div class="stat-card">
                        <div class="icon-box" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fa-solid fa-sack-dollar"></i></div>
                        <div class="stat-info">
                            <div class="val">₹<?php echo number_format($lifetime_revenue); ?></div>
                            <div class="lbl">Lifetime Net Worth</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon-box" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fa-solid fa-boxes-stacked"></i></div>
                        <div class="stat-info">
                            <div class="val"><?php echo number_format($lifetime_volume); ?> <span style="font-size: 1rem;">kg</span></div>
                            <div class="lbl">Total Volume Sold</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon-box" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-truck-fast"></i></div>
                        <div class="stat-info">
                            <div class="val"><?php echo $total_orders_count; ?></div>
                            <div class="lbl">Completed Orders</div>
                        </div>
                    </div>
                    <div class="stat-card">
                        <div class="icon-box" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;"><i class="fa-solid fa-seedling"></i></div>
                        <div class="stat-info">
                            <div class="val"><?php echo $active_listings; ?></div>
                            <div class="lbl">Active Listings</div>
                        </div>
                    </div>
                </div>

                <?php if($result && $result->num_rows > 0): ?>
                    <div class="product-grid">
                        <?php while($row = $result->fetch_assoc()): ?>
                            <?php
                                $today = date("Y-m-d");
                                $harvest = $row['expected_harvest'];
                                $daysLeft = (strtotime($harvest) - strtotime($today)) / (60*60*24);
                                
                                $daysSinceSowing = (strtotime($today) - strtotime($row['sowing_date'])) / (60*60*24);
                                $totalDays = (strtotime($row['expected_harvest']) - strtotime($row['sowing_date'])) / (60*60*24);
                                $progress = 0;
                                if($totalDays > 0){ $progress = min(100, ($daysSinceSowing / $totalDays) * 100); }

                                $predictedYield = ($row['investment'] * 0.05) + ($daysSinceSowing * 0.8);
                                $conn->query("UPDATE crops SET predicted_yield = $predictedYield WHERE id=".$row['id']);
                                
                                $image_path = !empty($row['image']) ? $row['image'] : 'placeholder.jpg';
                            ?>

                            <div class="bento-crop-card">
                                <div class="crop-img-wrapper">
                                    <img src="../uploads/<?php echo $image_path; ?>" class="crop-img" alt="<?php echo htmlspecialchars($row['crop_name']); ?>">
                                    <?php if($row['status'] == 'growing'): ?>
                                        <div class="badge-status status-growing"><i class="fa-solid fa-seedling"></i> Growing</div>
                                    <?php elseif($row['status'] == 'harvested'): ?>
                                        <div class="badge-status status-harvested"><i class="fa-solid fa-check-double"></i> Harvested</div>
                                    <?php elseif($row['status'] == 'for_sale'): ?>
                                        <div class="badge-status status-sale"><i class="fa-solid fa-tags"></i> For Sale</div>
                                    <?php endif; ?>

                                    <?php if($row['status'] == 'growing' && $row['quantity'] > 0): ?>
                                        <div class="stock-badge stock-prebook"><i class="fa-solid fa-file-contract"></i> <?php echo $row['quantity']; ?> kg Pre-Book Stock</div>
                                    <?php elseif($row['status'] != 'growing'): ?>
                                        <div class="stock-badge stock-live"><i class="fa-solid fa-boxes-stacked" style="color:#10b981;"></i> <?php echo $row['quantity']; ?> kg in Stock</div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="card-body">
                                    <h3 class="crop-title"><?php echo htmlspecialchars($row['crop_name']); ?></h3>

                                    <div class="stat-row"><span>Area Grown</span> <strong style="color: #3b82f6;"><?php echo isset($row['acreage']) ? $row['acreage'] : '0'; ?> Acres</strong></div>
                                    <div class="stat-row"><span>Investment</span> <strong>₹<?php echo number_format($row['investment']); ?></strong></div>
                                    <div class="stat-row"><span>Market Price</span> <strong>₹<?php echo number_format($row['market_price']); ?> / kg</strong></div>
                                    
                                    <?php if($row['status'] == 'harvested' || $row['status'] == 'for_sale'): ?>
                                        <div class="stat-row"><span>Actual Harvest Yield</span> <strong style="color: #10b981;"><i class="fa-solid fa-check-double"></i> <?php echo round($row['actual_yield']); ?> kg</strong></div>
                                    <?php else: ?>
                                        <div class="stat-row"><span>Predicted Yield</span> <strong style="color: var(--primary);"><i class="fa-solid fa-wand-magic-sparkles"></i> <?php echo round($predictedYield); ?> kg</strong></div>
                                    <?php endif; ?>

                                    <div>
                                        <div style="display: flex; justify-content: space-between; font-size: 0.85rem; margin-top: 15px; font-weight: 800;" class="logo-text">
                                            <span>Growth Progress</span>
                                            <span style="color: var(--primary);"><?php echo round($progress); ?>%</span>
                                        </div>
                                        <div class="progress-wrapper">
                                            <div class="progress-fill" style="width: <?php echo round($progress); ?>%;"></div>
                                        </div>
                                    </div>

                                    <div class="card-actions">
                                        <button onclick="updatePrice(<?php echo $row['id']; ?>,'<?php echo htmlspecialchars($row['crop_name']); ?>')" class="btn-sm btn-live"><i class="fa-solid fa-rotate-right"></i> Sync Live Price</button>

                                        <?php if($row['status'] == 'growing'): ?>
                                            <?php if($daysLeft > 0): ?>
                                                <div style="text-align: center; font-size: 0.95rem; font-weight: 800; color: #d97706; padding: 12px; background: rgba(245, 158, 11, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(245, 158, 11, 0.2); margin-top: 5px;">
                                                    <i class="fa-solid fa-hourglass-half"></i> <?php echo floor($daysLeft); ?> Days to Harvest
                                                </div>
                                                
                                                <?php if($row['quantity'] <= 0): ?>
                                                    <button onclick="openPrebookModal(<?php echo $row['id']; ?>, <?php echo round($predictedYield); ?>)" class="btn-sm btn-action-blue" style="width: 100%; margin-top: 5px;"><i class="fa-solid fa-bullhorn"></i> Enable Pre-Bookings</button>
                                                <?php else: ?>
                                                    <div style="text-align: center; font-size: 0.95rem; font-weight: 800; color: #3b82f6; padding: 12px; background: rgba(59, 130, 246, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(59, 130, 246, 0.2); margin-top: 5px;">
                                                        <i class="fa-solid fa-file-contract"></i> Pre-Booking Active
                                                    </div>
                                                <?php endif; ?>
                                                
                                            <?php else: ?>
                                                <button onclick="openHarvestModal(<?php echo $row['id']; ?>, <?php echo round($predictedYield); ?>)" class="btn-sm btn-action-main" style="width: 100%;"><i class="fa-solid fa-check"></i> Mark as Harvested</button>
                                            <?php endif; ?>
                                        <?php elseif($row['status'] == 'harvested'): ?>
                                            <form method="POST" action="">
                                                <input type="hidden" name="crop_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="mark_for_sale" class="btn-sm btn-action-blue" style="width: 100%;"><i class="fa-solid fa-store"></i> List in Marketplace</button>
                                            </form>
                                        <?php elseif($row['status'] == 'for_sale'): ?>
                                            <div style="text-align: center; font-size: 0.95rem; font-weight: 800; color: #10b981; padding: 12px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.2);">
                                                <i class="fa-solid fa-circle-check"></i> Active in Market
                                            </div>
                                        <?php endif; ?>

                                        <div style="display: flex; gap: 10px; margin-top: 5px;">
                                            <button onclick='openEditModal(<?php echo json_encode($row); ?>)' class="btn-sm btn-edit" style="flex:1;"><i class="fa-solid fa-pen-to-square"></i> Edit Details</button>
                                            <form method="POST" style="flex: 1; display: flex;" onsubmit="return confirm('Are you sure you want to remove this crop from the marketplace? Your past sales history will be safely preserved.');">
                                                <input type="hidden" name="crop_id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_crop" class="btn-sm btn-delete" style="width: 100%;"><i class="fa-solid fa-box-archive"></i> Archive</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; background: var(--card-bg); padding: 80px 20px; border-radius: var(--radius-xl); border: 2px dashed var(--border-color); box-shadow: var(--shadow-subtle);">
                        <i class="fa-solid fa-seedling" style="font-size: 4rem; color: var(--border-color); margin-bottom: 20px;"></i>
                        <h2 style="margin-bottom: 10px; font-size: 1.8rem;" class="logo-text">Your field is empty!</h2>
                        <p style="color: var(--text-muted); margin-bottom: 25px; font-size: 1.1rem; font-weight: 500;">Start tracking your crops to unlock AI yield predictions and market sync.</p>
                        <a href="add_crop.php" class="btn-sm btn-action-main" style="display: inline-flex; padding: 16px 32px; font-size: 1.1rem; border-radius: 50px;"><i class="fa-solid fa-plus"></i> Plant Your First Crop</a>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <!-- Lock Screen Container -->
                <div class="lock-container">
                    <div class="lock-card">
                        <?php if($verification_status === 'pending'): ?>
                            <div class="lock-icon-wrapper lock-icon-pending">
                                <i class="fa-solid fa-hourglass-half"></i>
                            </div>
                            <h2 class="lock-title">Verification Pending</h2>
                            <p class="lock-desc">
                                Your account is currently under review by our administration. 
                                We are verifying your documents to ensure marketplace integrity. 
                                Please check back in 24-48 hours.
                            </p>
                        <?php elseif($verification_status === 'rejected'): ?>
                            <div class="lock-icon-wrapper lock-icon-rejected">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <h2 class="lock-title">Verification Rejected</h2>
                            <p class="lock-desc">
                                Unfortunately, your verification request has been rejected. 
                                Review the administrator remarks below for details.
                            </p>
                            <?php if(!empty($verification_remarks)): ?>
                                <div class="lock-remarks">
                                    <strong>Admin Remarks:</strong><br>
                                    <?php echo htmlspecialchars($verification_remarks); ?>
                                </div>
                            <?php endif; ?>
                            <a href="verify.php" class="btn-action-main" style="padding: 14px 28px; border-radius: 50px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa-solid fa-rotate-right"></i> Resubmit Verification</a>
                        <?php else: ?>
                            <div class="lock-icon-wrapper lock-icon-unverified">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <h2 class="lock-title">Farm Inventory Locked</h2>
                            <p class="lock-desc">
                                To prevent fraudulence, you must verify your identity and farmer credentials before you can manage crops or list them in the marketplace.
                            </p>
                            <a href="verify.php" class="btn-action-main" style="padding: 14px 28px; border-radius: 50px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem;"><i class="fa-solid fa-shield-halved"></i> Start Verification Process</a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>
</div>

<div class="modal-overlay" id="prebookModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-close" onclick="closeModal('prebookModal')"><i class="fa-solid fa-xmark"></i></div>
        <h2 style="margin-top:0; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px;" class="logo-text">
            <i class="fa-solid fa-bullhorn" style="color: #3b82f6;"></i> Enable Pre-Bookings
        </h2>
        
        <p style="color: var(--text-muted); font-weight: 500; margin-bottom: 20px; font-size: 0.95rem;">
            Your predicted yield is <strong style="color: #3b82f6;" id="prebook_predicted_display">0</strong> kg. How much of this do you want to allocate for advance pre-booking in the marketplace?
        </p>

        <form method="POST" action="">
            <input type="hidden" name="crop_id" id="prebook_crop_id">
            
            <div class="form-group">
                <label class="form-label">Pre-Book Allocation (kg)</label>
                <input type="number" name="prebook_qty" id="prebook_qty_input" class="form-input" step="0.01" required>
            </div>
            
            <button type="submit" name="enable_prebook" class="btn-update" style="background: #3b82f6; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2);"><i class="fa-solid fa-bullhorn"></i> List for Pre-Booking</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="editModal">
    <div class="modal-content">
        <div class="modal-close" onclick="closeModal('editModal')"><i class="fa-solid fa-xmark"></i></div>
        <h2 style="margin-top:0; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px;" class="logo-text">
            <i class="fa-solid fa-pen-to-square" style="color: var(--primary);"></i> Edit Crop & Inventory
        </h2>
        
        <form method="POST" action="" enctype="multipart/form-data">
            <input type="hidden" name="crop_id" id="edit_crop_id">
            
            <div class="form-group">
                <label class="form-label">Crop Name</label>
                <input type="text" name="crop_name" id="edit_crop_name" class="form-input" required>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Farm Area (Acres)</label>
                    <input type="number" name="acreage" id="edit_acreage" class="form-input" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Available Stock (kg)</label>
                    <input type="number" name="quantity" id="edit_quantity" class="form-input" step="0.01" required>
                </div>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Investment (₹)</label>
                    <input type="number" name="investment" id="edit_investment" class="form-input" step="0.01" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Market Price (₹)</label>
                    <input type="number" name="market_price" id="edit_market_price" class="form-input" step="0.01" required>
                </div>
            </div>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                <div class="form-group">
                    <label class="form-label">Sowing Date</label>
                    <input type="date" name="sowing_date" id="edit_sowing_date" class="form-input" required>
                </div>
                <div class="form-group">
                    <label class="form-label">Expected Harvest</label>
                    <input type="date" name="expected_harvest" id="edit_expected_harvest" class="form-input" required>
                </div>
            </div>
            
            <div class="form-group" style="margin-top: 10px;">
                <label class="form-label">Update Crop Photo (Optional)</label>
                <input type="file" name="crop_image" accept="image/jpeg, image/png" class="form-input" style="padding: 10px;">
            </div>
            
            <button type="submit" name="update_crop" class="btn-update"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="harvestModal">
    <div class="modal-content" style="max-width: 450px;">
        <div class="modal-close" onclick="closeModal('harvestModal')"><i class="fa-solid fa-xmark"></i></div>
        <h2 style="margin-top:0; border-bottom: 1px solid var(--border-color); padding-bottom: 15px; margin-bottom: 25px;" class="logo-text">
            <i class="fa-solid fa-wheat-awn" style="color: var(--primary);"></i> Record Harvest Yield
        </h2>
        
        <p style="color: var(--text-muted); font-weight: 500; margin-bottom: 20px; font-size: 0.95rem;">
            The AI predicted a yield of <strong style="color: var(--primary);" id="harvest_predicted_display">0</strong> kg. Enter the actual amount harvested. This will intelligently deduct any pre-bookings and update your live inventory stock!
        </p>

        <form method="POST" action="">
            <input type="hidden" name="crop_id" id="harvest_crop_id">
            
            <div class="form-group">
                <label class="form-label">Actual Total Yield (kg)</label>
                <input type="number" name="actual_yield" id="harvest_actual_yield" class="form-input" step="0.01" required>
            </div>
            
            <button type="submit" name="mark_harvested" class="btn-update"><i class="fa-solid fa-check-double"></i> Confirm Harvest & Update Stock</button>
        </form>
    </div>
</div>

<script>
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

<script>
function updatePrice(cropId, cropName){
    fetch('../api/market_price.php?crop=' + cropName)
    .then(res => res.json())
    .then(data => {
        let livePrice = data.price;
        fetch('update_price.php', {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: 'crop_id=' + cropId + '&price=' + livePrice
        })
        .then(res => res.text())
        .then(response => {
            alert("Success! Live Market Price updated to ₹" + livePrice);
            location.reload();
        });
    });
}

function openEditModal(crop) {
    document.getElementById('edit_crop_id').value = crop.id;
    document.getElementById('edit_crop_name').value = crop.crop_name;
    document.getElementById('edit_investment').value = crop.investment;
    document.getElementById('edit_market_price').value = crop.market_price;
    document.getElementById('edit_sowing_date').value = crop.sowing_date ? crop.sowing_date.split(' ')[0] : ''; 
    document.getElementById('edit_expected_harvest').value = crop.expected_harvest ? crop.expected_harvest.split(' ')[0] : '';
    
    document.getElementById('edit_acreage').value = crop.acreage ? crop.acreage : 0;
    document.getElementById('edit_quantity').value = crop.quantity ? crop.quantity : 0;

    document.getElementById('editModal').style.display = 'flex';
}

function openHarvestModal(cropId, predictedYield) {
    document.getElementById('harvest_crop_id').value = cropId;
    document.getElementById('harvest_predicted_display').innerText = predictedYield;
    document.getElementById('harvest_actual_yield').value = predictedYield; 
    document.getElementById('harvestModal').style.display = 'flex';
}

function openPrebookModal(cropId, predictedYield) {
    document.getElementById('prebook_crop_id').value = cropId;
    document.getElementById('prebook_predicted_display').innerText = predictedYield;
    // Suggest 50% of predicted yield for pre-booking
    document.getElementById('prebook_qty_input').value = Math.floor(predictedYield * 0.5); 
    document.getElementById('prebookModal').style.display = 'flex';
}

function closeModal(modalId) {
    document.getElementById(modalId).style.display = 'none';
}

window.onclick = function(event) {
    let editModal = document.getElementById('editModal');
    let harvestModal = document.getElementById('harvestModal');
    let prebookModal = document.getElementById('prebookModal');
    
    if (event.target == editModal) closeModal('editModal');
    if (event.target == harvestModal) closeModal('harvestModal');
    if (event.target == prebookModal) closeModal('prebookModal');
}
</script>

<script src="../assets/js/translate.js"></script>

</body>
</html>
