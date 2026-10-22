<?php
session_start();
include("../config/db.php");

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

// ==========================================
// SORTING AND FILTERING LOGIC
// ==========================================
$status_filter = isset($_GET['status']) ? $_GET['status'] : 'all';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

$where_clause = "WHERE buyer_id = $buyer_id";
if ($status_filter !== 'all') {
    $clean_status = $conn->real_escape_string($status_filter);
    $where_clause .= " AND LOWER(order_status) = '$clean_status'";
}

$order_clause = "ORDER BY created_at DESC"; // Default: Newest first
if ($sort_by === 'oldest') $order_clause = "ORDER BY created_at ASC";
if ($sort_by === 'price_high') $order_clause = "ORDER BY total_amount DESC";
if ($sort_by === 'price_low') $order_clause = "ORDER BY total_amount ASC";

/* Fetch filtered/sorted orders */
$orders = $conn->query("SELECT * FROM orders $where_clause $order_clause");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Orders Tracking | Farmogram</title>
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

        /* STRICT FLEXBOX LAYOUT TO PREVENT MOBILE OVERLAP */
        body { margin: 0; overflow: hidden; background-color: var(--bg-light); color: var(--text-main); font-family: 'Inter', sans-serif; transition: 0.3s; }
        .app-layout { display: flex; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { flex-shrink: 0; }
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Filter Bar Styles */
        .filter-bar { display: flex; gap: 20px; margin-bottom: 35px; background: var(--card-bg); padding: 20px 30px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); align-items: center; justify-content: space-between; flex-wrap: wrap; transition: 0.3s; }
        .filter-group { display: flex; gap: 12px; align-items: center; }
        .filter-select { padding: 12px 20px; border: 2px solid var(--border-color); border-radius: 50px; outline: none; font-size: 0.95rem; font-family: inherit; color: var(--text-main); font-weight: 800; cursor: pointer; background: var(--bg-light); transition: 0.3s; }
        .filter-select:hover, .filter-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }

        /* Bento Order Cards */
        .order-card { background: var(--card-bg); padding: 35px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); margin-bottom: 30px; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .order-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .order-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px dashed var(--border-color); padding-bottom: 20px; margin-bottom: 25px; transition: 0.3s; }
        .order-id { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; transition: 0.3s; }
        .order-date { font-size: 0.95rem; color: var(--text-muted); font-weight: 600; margin-top: 6px; transition: 0.3s; }
        .order-price { font-size: 1.8rem; font-weight: 900; color: #10b981; letter-spacing: -0.5px; }
        
        .order-split { display: grid; grid-template-columns: 1.5fr 1fr; gap: 40px; align-items: start; }
        
        .item-row { display: flex; justify-content: space-between; padding: 20px; background: var(--bg-light); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 12px; transition: 0.3s; }
        .item-row:hover { border-color: var(--primary); transform: translateX(4px); }
        .item-name { font-weight: 900; font-size: 1.15rem; text-transform: capitalize; color: var(--text-main); transition: 0.3s; }
        .item-farmer { font-size: 0.9rem; color: var(--text-muted); margin-top: 6px; font-weight: 600; transition: 0.3s; display: flex; align-items: center; gap: 6px; }
        
        /* Swiggy Style Tracking Timeline */
        .tracking-box { background: var(--card-bg); border-left: 2px solid var(--border-color); padding-left: 35px; position: relative; height: 100%; transition: 0.3s; }
        
        .timeline { position: relative; padding-left: 20px; border-left: 2px solid var(--border-color); margin-left: 10px; margin-top: 20px; transition: 0.3s; }
        .timeline-step { position: relative; padding-bottom: 30px; }
        .timeline-step:last-child { padding-bottom: 0; border-left: 0; }
        .timeline-step::before { content: ''; position: absolute; left: -27px; top: 0; width: 12px; height: 12px; border-radius: 50%; background: var(--border-color); border: 2px solid var(--card-bg); transition: 0.3s; box-shadow: 0 0 0 2px var(--border-color); }
        
        .step-title { font-weight: 900; font-size: 1.05rem; color: var(--text-muted); line-height: 1; margin-bottom: 6px; transition: 0.3s; }
        .step-date { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; transition: 0.3s; }

        .timeline-step.done::before { background: var(--primary); box-shadow: 0 0 0 2px var(--primary); border-color: var(--card-bg); }
        .timeline-step.done .step-title { color: var(--text-main); }
        .timeline-step.active::before { background: var(--primary); box-shadow: 0 0 0 6px rgba(16, 185, 129, 0.2); border-color: var(--card-bg); }
        .timeline-step.active .step-title { color: var(--primary); }

        /* Delayed Timeline Styles */
        .timeline-step.delayed::before { background: #ef4444; box-shadow: 0 0 0 6px rgba(239, 68, 68, 0.2); border-color: var(--card-bg); }
        .timeline-step.delayed .step-title { color: #ef4444; }
        .delay-notice { background: rgba(239, 68, 68, 0.1); border-left: 4px solid #ef4444; padding: 15px 20px; border-radius: 0 12px 12px 0; margin-bottom: 25px; font-size: 0.9rem; color: #ef4444; display: flex; gap: 12px; align-items: flex-start; font-weight: 600; border: 1px solid rgba(239, 68, 68, 0.2); border-left-width: 4px; }

        /* Harvest Alert & Action Buttons */
        .harvest-alert { background: rgba(16, 185, 129, 0.1); border: 1px solid rgba(16, 185, 129, 0.3); padding: 20px; border-radius: var(--radius-md); margin-top: 25px; animation: pulse 2s infinite; }
        @keyframes pulse { 0% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(16, 185, 129, 0); } 100% { box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); } }
        
        .balance-box { background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 20px; border-radius: var(--radius-md); margin-top: 25px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .btn-pay-balance { background: #f59e0b; color: #fff; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2); transition: 0.3s; }
        .btn-pay-balance:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(245, 158, 11, 0.3); background: #d97706; }
        
        .action-buttons { display: flex; gap: 15px; margin-top: 25px; justify-content: flex-start; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 20px; transition: 0.3s; flex-wrap: wrap; }
        .btn-outline-sm { padding: 12px 20px; border-radius: 12px; font-size: 0.95rem; border: 2px solid var(--border-color); color: var(--text-main); text-decoration: none; font-weight: 800; transition: var(--transition); background: var(--bg-light); display: inline-flex; align-items: center; gap: 8px; box-shadow: var(--shadow-subtle); }
        .btn-outline-sm:hover { border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
        .btn-review { background: var(--primary); color: #fff; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-weight: 800; font-size: 0.95rem; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); transition: 0.3s; }
        .btn-review:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3); }
        
        .cancelled-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; background: rgba(255,255,255,0.7); z-index: 10; display: flex; align-items: center; justify-content: center; backdrop-filter: blur(3px); }
        .cancelled-stamp { color: #ef4444; font-size: 3.5rem; font-weight: 900; opacity: 0.9; transform: rotate(-15deg); border: 8px solid #ef4444; padding: 15px 30px; border-radius: 20px; text-transform: uppercase; letter-spacing: 2px; }

        /* Sidebar Desktop Logo */
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }

        /* Mobile Topbar Branding */
        .mobile-brand { display: none; align-items: center; gap: 8px; text-decoration: none; }
        .mobile-brand img { width: 30px; height: 30px; object-fit: contain; }
        .mobile-brand span { font-size: 1.3rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        /* Topbar Controls */
        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); flex-shrink: 0; }
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
        
        [data-theme="dark"] .filter-bar, [data-theme="dark"] .order-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .filter-select { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .filter-select:hover, [data-theme="dark"] .filter-select:focus { border-color: var(--primary) !important; }
        
        [data-theme="dark"] .item-row { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .item-row:hover { border-color: var(--primary) !important; background: rgba(16, 185, 129, 0.05) !important; }
        
        [data-theme="dark"] .tracking-box { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .timeline { border-color: var(--border-color) !important; }
        [data-theme="dark"] .timeline-step::before { background: var(--border-color) !important; border-color: var(--sidebar-bg) !important; box-shadow: 0 0 0 2px var(--border-color) !important; }
        [data-theme="dark"] .timeline-step.done::before, [data-theme="dark"] .timeline-step.active::before { border-color: var(--sidebar-bg) !important; }
        [data-theme="dark"] .timeline-step.delayed::before { border-color: var(--sidebar-bg) !important; }
        
        [data-theme="dark"] .btn-outline-sm { background: var(--bg-light) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .btn-outline-sm:hover { background: var(--sidebar-bg) !important; border-color: var(--primary) !important; color: var(--primary) !important; }
        
        [data-theme="dark"] .cancelled-overlay { background: rgba(15, 23, 42, 0.85) !important; }

        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .order-id, [data-theme="dark"] .item-name, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .step-title, [data-theme="dark"] .step-date { color: var(--text-muted); }
        [data-theme="dark"] .timeline-step.done .step-title { color: var(--text-main) !important; }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .order-card, .filter-bar, .item-row, h1, h2, h3, h4, p, span, div, .filter-select { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; }

        /* =========================================================
           RESPONSIVE RULES (TABLET & MOBILE)
        ========================================================= */
        @media (max-width: 1024px) {
            .order-split { grid-template-columns: 1fr; gap: 30px; }
            .tracking-box { border-left: none; border-top: 2px solid var(--border-color); padding-left: 0; padding-top: 30px; }
        }

        @media (max-width: 768px) {
            .app-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
            
            /* Hide Desktop Logo */
            .sidebar-logo { display: none; } 
            
            /* Show Mobile Logo in Topbar */
            .mobile-brand { display: flex; align-items: center; flex: 1; }
            
            /* Topbar Redesign for Mobile */
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; display: flex; align-items: center; }
            .user-profile { display: none; } /* Hide profile on mobile to save space */
            
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; }

            /* PERFECTLY RESPONSIVE GOOGLE TRANSLATE PILL */
            .goog-te-gadget-simple { padding: 6px 12px !important; font-size: 0.8rem !important; margin: 0 !important; }

            /* Sleek Swipeable Bottom App Nav */
            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); border-right: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; scroll-snap-type: x mandatory; gap: 6px; justify-content: flex-start; -webkit-overflow-scrolling: touch; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu li { flex: 0 0 auto; scroll-snap-align: center; }
            
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; transition: 0.3s; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }

            /* 100dvh Scroll Fix */
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 20px 15px; flex: 1; overflow-y: auto; }
            
            .filter-bar { flex-direction: column; align-items: stretch; padding: 20px; border-radius: var(--radius-lg); }
            .filter-group { flex-direction: column; align-items: stretch; gap: 8px; }
            
            .order-card { padding: 25px; border-radius: var(--radius-lg); }
            .order-header { flex-direction: column; align-items: flex-start; gap: 10px; }
            .order-price { font-size: 1.5rem; }
            
            .item-row { flex-direction: column; gap: 15px; }
            .item-row > div:last-child { text-align: left; display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 10px; }
            
            .balance-box { flex-direction: column; align-items: flex-start; }
            .balance-box > div:last-child { width: 100%; display: flex; justify-content: space-between; align-items: center; margin-top: 10px; }
            
            .action-buttons { flex-direction: column; align-items: stretch; }
            .btn-outline-sm, .btn-review { justify-content: center; }
            
            .cancelled-stamp { font-size: 2rem; border-width: 5px; }
        }

        /* EXTRA SMALL SCREEN FIX */
        @media (max-width: 480px) {
            .mobile-brand span { display: block; font-size: 1.1rem; }
            .goog-te-gadget-simple { padding: 4px 8px !important; font-size: 0.75rem !important; }
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
            <li><a href="orders.php" class="active"><i class="fa-solid fa-box-open"></i> <span>Orders</span></a></li>
            <li><a href="my_enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
                        <li><a href="profile.php"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
<li><a href="../auth/logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> <span>Logout</span></a></li>
        </ul>
    </aside>

    <main class="main-wrapper">
        <header class="topbar" style="border: none;">
            <a href="dashboard.php" class="mobile-brand">
                <img src="../assets/images/logo-light.png" alt="Farmogram" class="logo-light">
                <img src="../assets/images/logo-dark.png" alt="Farmogram" class="logo-dark">
                <span>Farmogram</span>
            </a>
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">My Orders & Tracking</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <a href="cart.php" class="theme-btn" style="text-decoration: none;" title="My Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                </a>

                <button class="theme-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <a href="profile.php" class="user-profile" style="border-left: none; display: flex; align-items: center; text-decoration: none; padding-left: 0;">
                    <div style="text-align: right; margin-right: 10px;" class="desktop-only-text">
                        <div style="font-weight: 800; font-size: 0.95rem;" class="logo-text"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">Buyer</div>
                    </div>
                    <?php if(!empty($profile_image) && file_exists("../uploads/profile/" . $profile_image)): ?>
                        <div class="avatar" style="border: 1px solid rgba(59, 130, 246, 0.3); overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><img src="../uploads/profile/<?php echo $profile_image; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"></div>
                    <?php else: ?>
                        <div class="avatar" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); font-weight: 900; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0;"><?php echo $avatar_letter; ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="content-container">
            <?php if(isset($_GET['msg']) && $_GET['msg'] == 'cancelled'): ?>
                <div style="background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 20px; border-radius: var(--radius-md); border: 1px solid rgba(16, 185, 129, 0.2); margin-bottom: 25px; font-weight: 800; display: flex; align-items: center; gap: 10px; font-size: 1.05rem; box-shadow: var(--shadow-subtle);">
                    <i class="fa-solid fa-check-circle" style="font-size: 1.2rem;"></i> Order cancelled successfully. Refunds have been processed.
                </div>
            <?php endif; ?>

            <form method="GET" class="filter-bar">
                <div class="filter-group">
                    <label style="font-weight: 800; color: var(--text-muted);"><i class="fa-solid fa-filter" style="color: var(--primary); margin-right: 5px;"></i> Status Filter:</label>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php if($status_filter == 'all') echo 'selected'; ?>>View All Orders</option>
                        <option value="processing" <?php if($status_filter == 'processing') echo 'selected'; ?>>Processing</option>
                        <option value="shipped" <?php if($status_filter == 'shipped') echo 'selected'; ?>>Shipped / In Transit</option>
                        <option value="delivered" <?php if($status_filter == 'delivered') echo 'selected'; ?>>Delivered</option>
                        <option value="cancelled" <?php if($status_filter == 'cancelled') echo 'selected'; ?>>Cancelled / Refunded</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label style="font-weight: 800; color: var(--text-muted);"><i class="fa-solid fa-sort" style="color: var(--primary); margin-right: 5px;"></i> Sort Timeline:</label>
                    <select name="sort" class="filter-select" onchange="this.form.submit()">
                        <option value="newest" <?php if($sort_by == 'newest') echo 'selected'; ?>>Date: Newest First</option>
                        <option value="oldest" <?php if($sort_by == 'oldest') echo 'selected'; ?>>Date: Oldest First</option>
                        <option value="price_high" <?php if($sort_by == 'price_high') echo 'selected'; ?>>Price: High to Low</option>
                        <option value="price_low" <?php if($sort_by == 'price_low') echo 'selected'; ?>>Price: Low to High</option>
                    </select>
                </div>
            </form>

            <div id="ordersContainer">
                <?php if($orders->num_rows > 0): ?>
                    <?php while($order = $orders->fetch_assoc()): ?>
                        <?php 
                            $order_id = $order['id'];
                            $status_clean = strtolower($order['order_status']);
                            $is_cancelled = ($status_clean == 'cancelled');
                            
                            $items = $conn->query("
                                SELECT order_items.*, crops.crop_name, crops.market_price, crops.status AS crop_status, users.name AS farmer_name 
                                FROM order_items 
                                JOIN crops ON order_items.crop_id = crops.id 
                                JOIN users ON crops.farmer_id = users.id 
                                WHERE order_items.order_id = $order_id
                            ");
                            
                            $total_market_value = 0; $total_amount_paid = 0;
                            $harvest_ready_alert = false;
                            
                            // DELAY CALCULATION LOGIC
                            $created_time = strtotime($order['created_at']);
                            $now = time();
                            
                            $exp_ship = date('M j', strtotime('+2 days', $created_time));
                            $exp_del  = date('M j', strtotime('+5 days', $created_time));

                            $is_delayed = false;
                            $delay_reason = "Logistical constraints or unpredictable weather conditions at the farm.";
                            
                            if(isset($order['delay_reason']) && !empty($order['delay_reason'])) {
                                $is_delayed = true;
                                $delay_reason = $order['delay_reason'];
                            } elseif ($status_clean == 'processing' && $now > strtotime('+2 days', $created_time)) {
                                $is_delayed = true;
                                $delay_reason = "Farmer is experiencing delays in packing your order. Rest assured, it will be shipped soon.";
                            } elseif ($status_clean == 'shipped' && $now > strtotime('+5 days', $created_time)) {
                                $is_delayed = true;
                                $delay_reason = "Delivery partner is facing regional transit delays. Package is on the way.";
                            }

                            // Timeline State Allocation
                            $s_proc = ($status_clean == 'processing' || $status_clean == 'shipped' || $status_clean == 'delivered') ? 'done' : '';
                            $s_ship = ($status_clean == 'shipped' || $status_clean == 'delivered') ? 'done' : '';
                            $s_del  = ($status_clean == 'delivered') ? 'done' : '';
                            
                            if($status_clean == 'processing') $s_proc = $is_delayed ? 'delayed' : 'active';
                            if($status_clean == 'shipped') $s_ship = $is_delayed ? 'delayed' : 'active';
                            if($status_clean == 'delivered') $s_del = 'active';
                        ?>
                        
                        <div class="order-card">
                            <?php if($is_cancelled): ?>
                                <div class="cancelled-overlay"><div class="cancelled-stamp">Refunded</div></div>
                            <?php endif; ?>

                            <div class="order-header">
                                <div>
                                    <div class="order-id">Order #ORD-<?php echo $order_id; ?></div>
                                    <div class="order-date"><i class="fa-regular fa-clock" style="margin-right: 5px;"></i> Placed on <?php echo date('F j, Y, g:i A', $created_time); ?></div>
                                </div>
                                <div class="order-price">₹<?php echo number_format($order['total_amount'], 2); ?></div>
                            </div>

                            <div class="order-split">
                                <div class="left-col">
                                    <!-- DELIVERY DETAILS BLOCK -->
                                    <?php if(!empty($order['delivery_address'])): ?>
                                    <div style="background: var(--bg-light); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 20px; margin-bottom: 25px; transition: 0.3s;">
                                        <h4 style="margin: 0 0 12px 0; color: var(--text-muted); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; font-weight: 800; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-location-dot" style="color: var(--primary);"></i> Delivery Destination</h4>
                                        <div style="font-weight: 900; font-size: 1.1rem; color: var(--text-main); margin-bottom: 4px;"><?php echo htmlspecialchars($order['delivery_name']); ?></div>
                                        <div style="color: var(--text-muted); font-size: 0.95rem; font-weight: 600; line-height: 1.5;">
                                            <?php echo htmlspecialchars($order['delivery_address']); ?><br>
                                            <?php echo htmlspecialchars($order['delivery_city']); ?> - <?php echo htmlspecialchars($order['delivery_pin']); ?><br>
                                            <i class="fa-solid fa-phone" style="font-size: 0.85rem; margin-right: 5px; margin-top: 8px;"></i> <?php echo htmlspecialchars($order['delivery_phone']); ?>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <h4 style="margin: 0 0 15px 0; color: var(--text-muted); text-transform: uppercase; font-size: 0.85rem; letter-spacing: 1px; font-weight: 800;">Items in Shipment</h4>
                                    
                                    <?php while($item = $items->fetch_assoc()): ?>
                                        <?php 
                                            $item_full_value = $item['quantity'] * $item['market_price'];
                                            $item_paid = $item['quantity'] * $item['price'];
                                            $total_market_value += $item_full_value;
                                            $total_amount_paid += $item_paid;
                                            
                                            if($item_paid < $item_full_value && $item['crop_status'] == 'for_sale'){
                                                $harvest_ready_alert = true;
                                            }
                                        ?>
                                        <div class="item-row">
                                            <div>
                                                <div class="item-name"><?php echo htmlspecialchars($item['crop_name']); ?></div>
                                                <div class="item-farmer"><i class="fa-solid fa-user-tractor" style="color: var(--primary);"></i> Supplier: <?php echo htmlspecialchars($item['farmer_name']); ?></div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-weight: 900; font-size: 1.2rem; color: var(--text-main);"><?php echo $item['quantity']; ?> kg</div>
                                                <div style="font-size: 0.9rem; color: var(--text-muted); font-weight: 600;">Paid: ₹<?php echo number_format($item_paid, 2); ?></div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>

                                    <?php 
                                        $pending_balance = $total_market_value - $total_amount_paid;
                                        $check_rev = $conn->query("SELECT id FROM reviews WHERE order_id = $order_id");
                                    ?>

                                    <?php if($harvest_ready_alert && !$is_cancelled): ?>
                                        <div class="harvest-alert">
                                            <strong style="color: #10b981; font-size: 1.15rem; font-weight: 900;"><i class="fa-solid fa-wheat-awn"></i> Harvest Complete!</strong>
                                            <p style="margin: 8px 0 0 0; color: var(--text-main); font-size: 0.95rem; font-weight: 600;">The farmer has harvested your pre-ordered crops. Settle the balance below to initiate shipping.</p>
                                        </div>
                                    <?php endif; ?>

                                    <?php if($pending_balance > 0 && !$is_cancelled): ?>
                                        <div class="balance-box">
                                            <div>
                                                <div style="color: #d97706; font-weight: 900; font-size: 1.1rem;"><i class="fa-solid fa-triangle-exclamation"></i> Contract Balance Due</div>
                                                <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 5px; font-weight: 600;">Pending amount required prior to dispatch.</div>
                                            </div>
                                            <div style="text-align: right;">
                                                <div style="font-size: 1.5rem; font-weight: 900; color: #d97706; margin-bottom: 10px; letter-spacing: -0.5px;">₹ <?php echo number_format($pending_balance, 2); ?></div>
                                                <a href="pay_balance.php?order_id=<?php echo $order_id; ?>" class="btn-pay-balance">Pay Balance <i class="fa-solid fa-arrow-right" style="font-size: 0.8rem;"></i></a>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <div class="action-buttons">
                                        <a href="download_invoice.php?id=<?php echo $order_id; ?>" class="btn-outline-sm"><i class="fa-solid fa-file-pdf" style="color: #ef4444;"></i> Invoice</a>
                                        
                                        <?php if(!$is_cancelled && $status_clean == 'processing'): ?>
                                            <a href="cancel_order.php?id=<?php echo $order_id; ?>" class="btn-outline-sm" style="color: #ef4444; border-color: rgba(239, 68, 68, 0.3);" onclick="return confirm('Are you sure you want to cancel this order?')"><i class="fa-solid fa-ban"></i> Cancel Order</a>
                                        <?php endif; ?>
                                        
                                        <?php if(!$is_cancelled && $status_clean == 'delivered' && $check_rev->num_rows == 0): ?>
                                            <a href="review.php?order_id=<?php echo $order_id; ?>" class="btn-review"><i class="fa-solid fa-star" style="color: #fcd34d;"></i> Leave a Review</a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <?php if(!$is_cancelled): ?>
                                <div class="tracking-box">
                                    <h4 style="margin: 0 0 20px 0; color: var(--text-main); font-size: 1.2rem; font-weight: 900; display: flex; align-items: center; justify-content: space-between; transition: 0.3s;">
                                        <span><i class="fa-solid fa-route" style="color: var(--primary); margin-right: 8px;"></i> Live Tracking</span>
                                        <?php if($is_delayed): ?>
                                            <span style="color: #ef4444; font-size: 0.8rem; background: rgba(239, 68, 68, 0.1); padding: 6px 12px; border-radius: 50px; font-weight: 900; letter-spacing: 0.5px; border: 1px solid rgba(239, 68, 68, 0.2);">DELAYED</span>
                                        <?php endif; ?>
                                    </h4>
                                    
                                    <?php if($is_delayed): ?>
                                    <div class="delay-notice">
                                        <i class="fa-solid fa-triangle-exclamation" style="font-size: 1.3rem; margin-top: 2px;"></i>
                                        <div>
                                            <strong style="font-size: 1rem;">Shipment Notice</strong><br>
                                            <span style="font-size: 0.9rem; opacity: 0.9; display: inline-block; margin-top: 4px;"><?php echo htmlspecialchars($delay_reason); ?></span>
                                        </div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="timeline">
                                        <div class="timeline-step <?php echo $s_proc; ?>">
                                            <div class="step-title">Order Confirmed</div>
                                            <div class="step-date">Farmer is packing</div>
                                        </div>
                                        <div class="timeline-step <?php echo $s_ship; ?>">
                                            <div class="step-title">Shipped</div>
                                            <div class="step-date">Exp. <?php echo $exp_ship; ?></div>
                                        </div>
                                        <div class="timeline-step <?php echo $s_del; ?>">
                                            <div class="step-title">Delivered</div>
                                            <div class="step-date">Exp. <?php echo $exp_del; ?></div>
                                        </div>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color); transition: 0.3s;">
                        <i class="fa-solid fa-box-open" style="font-size: 5rem; color: var(--border-color); margin-bottom: 25px; transition: 0.3s;"></i>
                        <h3 style="font-size: 1.8rem; color: var(--text-main); font-weight: 900; margin: 0 0 10px 0; transition: 0.3s;">No Orders Found</h3>
                        <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 25px; transition: 0.3s;">Try changing your filter settings or visit the marketplace.</p>
                        <a href="marketplace.php" class="btn-review" style="display:inline-flex; padding: 16px 30px; font-size: 1.1rem;"><i class="fa-solid fa-store"></i> Browse Marketplace</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
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

<script src="../assets/js/translate.js"></script>

<style>
/* Utility class to hide the full user name text on mobile to ensure topbar icons have space */
@media (max-width: 768px) {
    .desktop-only-text { display: none; }
}
</style>

</body>
</html>