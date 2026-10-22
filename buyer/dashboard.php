<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK: Prevents session overlap bugs!
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

/* ===== 1. BASIC STATS ===== */
// Total Spent
$spent_query = $conn->query("SELECT SUM(total_amount) AS total FROM orders WHERE buyer_id = $buyer_id AND order_status != 'cancelled'");
$total_spent = $spent_query->fetch_assoc()['total'] ?? 0;

// Total Orders
$orders_query = $conn->query("SELECT COUNT(id) AS count FROM orders WHERE buyer_id = $buyer_id");
$total_orders = $orders_query->fetch_assoc()['count'] ?? 0;

// Active Contracts (Pre-booked growing crops)
$contracts_query = $conn->query("
    SELECT COUNT(DISTINCT orders.id) AS count 
    FROM order_items 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN orders ON order_items.order_id = orders.id 
    WHERE orders.buyer_id = $buyer_id AND crops.status = 'growing' AND orders.order_status != 'cancelled'
");
$active_contracts = $contracts_query->fetch_assoc()['count'] ?? 0;

/* ===== 2. LATEST ORDER FOR LIVE TRACKER ===== */
$latest_order_query = $conn->query("
    SELECT * FROM orders 
    WHERE buyer_id = $buyer_id AND order_status != 'cancelled' 
    ORDER BY created_at DESC LIMIT 1
");
$latest_order = $latest_order_query->fetch_assoc();

/* ===== 3. RECENT FARMER MESSAGES ===== */
$recent_messages = [];
$msg_query = $conn->query("
    SELECT m.message, m.created_at, m.is_read, u.name AS farmer_name, e.id AS enquiry_id 
    FROM enquiry_messages m 
    JOIN enquiries e ON m.enquiry_id = e.id 
    JOIN users u ON m.sender_id = u.id 
    WHERE e.buyer_id = $buyer_id AND m.sender_id != $buyer_id 
    ORDER BY m.created_at DESC LIMIT 3
");
if($msg_query && $msg_query->num_rows > 0) {
    while($m = $msg_query->fetch_assoc()) { 
        $recent_messages[] = $m; 
    }
}

/* ===== 4. AI FRESH PICKS (RECOMMENDATIONS) ===== */
$fresh_picks = $conn->query("
    SELECT crops.*, users.name AS farmer_name, users.badge 
    FROM crops 
    JOIN users ON crops.farmer_id = users.id 
    WHERE crops.status = 'for_sale' AND users.verification_status = 'verified'
    ORDER BY RAND() LIMIT 3
");
/* ===== 5. NOTIFICATIONS ===== */
$notes = $conn->query("SELECT * FROM notifications WHERE user_id = $buyer_id ORDER BY created_at DESC LIMIT 6");
$unread_query = $conn->query("SELECT COUNT(id) AS unread FROM notifications WHERE user_id = $buyer_id AND is_read = 0");
$note_count = $unread_query->fetch_assoc()['unread'] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Buyer Dashboard | Farmogram</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <?php $server_theme = isset($_SESSION['theme']) ? $_SESSION['theme'] : ''; ?>
    <script>
        (function() {
            let currentUserId = '<?php echo $_SESSION['user_id'] ?? ''; ?>';
            let savedUserId = localStorage.getItem('farmogram_user_id');
            let serverTheme = '<?php echo $server_theme; ?>';
            let savedTheme = localStorage.getItem('farmogram_theme');

            if (currentUserId !== savedUserId || !savedTheme) {
                // New user login on this device OR first time load - server is source of truth
                savedTheme = serverTheme !== '' ? serverTheme : 'light';
                localStorage.setItem('farmogram_theme', savedTheme);
                localStorage.setItem('farmogram_user_id', currentUserId);
            } else {
                // Same user. If local and server mismatch, local is newer (changed on another page)
                if (serverTheme !== '' && savedTheme !== serverTheme) {
                    // Sync local to server in background
                    fetch('../api/update_theme.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'theme=' + savedTheme
                    });
                }
            }
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

        .content-container { padding: 30px 40px; max-width: 1400px; margin: 0 auto; }

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

        /* Welcome Banner */
        .welcome-banner { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; }
        .welcome-banner h1 { font-size: 2.4rem; font-weight: 900; color: var(--text-main); margin-bottom: 8px; letter-spacing: -1px; transition: 0.3s; }
        .welcome-banner p { color: var(--text-muted); font-size: 1.1rem; font-weight: 500; transition: 0.3s; }
        .date-badge { background: var(--bg-light); padding: 12px 24px; border-radius: 50px; font-weight: 800; font-size: 0.95rem; color: var(--primary); box-shadow: var(--shadow-subtle); display: flex; align-items: center; gap: 10px; border: 1px solid var(--border-color); transition: 0.3s; }

        /* Stat Pills */
        .stat-pills { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; margin-bottom: 30px; }
        .stat-pill { background: var(--card-bg); border-radius: var(--radius-xl); padding: 25px; display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-subtle); transition: 0.4s; border: 1px solid var(--border-color); }
        .stat-pill:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: var(--shadow-hover); }
        .stat-icon-wrapper { width: 65px; height: 65px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-pill-info h4 { font-size: 2.2rem; font-weight: 900; color: var(--text-main); line-height: 1; margin-bottom: 6px; transition: 0.3s; letter-spacing: -1px; }
        .stat-pill-info span { font-size: 0.85rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }

        /* Premium Bento Cards */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); padding: 35px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); margin-bottom: 24px; display: flex; flex-direction: column; overflow: hidden; }
        .bento-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

        /* FIXED: Modern Tracker Container */
        .track-steps { display: flex; justify-content: space-between; position: relative; margin-top: 40px; margin-bottom: 10px; padding: 0 10px; }
        /* The grey background line now starts at 15% and spans 70%, keeping it firmly behind the icons and off the edges */
        .track-steps::before { content: ''; position: absolute; top: 22px; left: 15%; width: 70%; height: 8px; background: var(--bg-light); z-index: 1; border-radius: 10px; transition: 0.3s; border: 1px solid var(--border-color); box-sizing: border-box; }
        /* The green progress line matches the start point */
        .track-progress { position: absolute; top: 23px; left: 15%; height: 8px; background: linear-gradient(90deg, #10b981, #059669); z-index: 2; transition: width 1s cubic-bezier(0.4, 0, 0.2, 1); border-radius: 10px; max-width: 70%; }
        
        .step { position: relative; z-index: 3; text-align: center; flex: 1; display: flex; flex-direction: column; align-items: center; }
        .step-icon { width: 54px; height: 54px; border-radius: 50%; background: var(--bg-light); border: 4px solid var(--border-color); display: flex; align-items: center; justify-content: center; margin-bottom: 15px; color: var(--text-muted); font-size: 1.3rem; transition: 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .step.active .step-icon { border-color: var(--primary); background: var(--primary); color: #fff; box-shadow: 0 0 0 8px rgba(16, 185, 129, 0.15); transform: scale(1.1); }
        .step-label { font-weight: 800; font-size: 0.95rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; transition: 0.4s; word-wrap: break-word; text-align: center; }
        .step.active .step-label { color: var(--text-main); }

        /* Dashboard Split Grid */
        .dashboard-split { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }

        /* General Header for Widgets */
        .widget-header { padding-bottom: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; transition: 0.3s; }
        .widget-title { font-size: 1.25rem; font-weight: 900; color: var(--text-main); display: flex; align-items: center; gap: 12px; transition: 0.3s; letter-spacing: -0.5px; }

        /* WhatsApp-Style Chat List */
        .chat-list { display: flex; flex-direction: column; }
        .chat-item { padding: 20px 15px; border-radius: var(--radius-md); border: 1px solid transparent; display: flex; gap: 18px; align-items: center; text-decoration: none; transition: 0.3s; position: relative; margin-bottom: 5px; }
        .chat-item:hover { background: var(--bg-light); border-color: var(--border-color); transform: translateX(5px); }
        .chat-avatar { width: 55px; height: 55px; border-radius: var(--radius-md); background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.4rem; flex-shrink: 0; border: 1px solid rgba(59, 130, 246, 0.3); transition: 0.3s; }
        .unread-dot { width: 12px; height: 12px; background: #ef4444; border-radius: 50%; position: absolute; right: 15px; top: 50%; transform: translateY(-50%); box-shadow: 0 0 0 4px rgba(239, 68, 68, 0.2); }

        /* Premium Fresh Picks */
        .pick-card { display: flex; gap: 20px; padding: 20px 15px; border-radius: var(--radius-md); border: 1px solid transparent; transition: 0.3s; align-items: center; margin-bottom: 5px; }
        .pick-card:hover { background: var(--bg-light); border-color: var(--border-color); transform: translateX(5px); }
        .pick-img { width: 100px; height: 100px; border-radius: var(--radius-md); object-fit: cover; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); transition: 0.3s; }
        .pick-card:hover .pick-img { transform: scale(1.05); }

        /* Sidebar Desktop Logo */
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }

        /* Mobile Topbar Branding */
        .mobile-brand { display: none; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        .mobile-brand span { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

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
        [data-theme="dark"] .bento-card, [data-theme="dark"] .stat-pill, [data-theme="dark"] .date-badge { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .track-steps::before { background: var(--sidebar-bg) !important; }
        [data-theme="dark"] .step-icon { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .chat-item:hover, [data-theme="dark"] .pick-card:hover { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .widget-title, [data-theme="dark"] .step-label.active, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .stat-pill, h1, h2, h3, h4, p, span, div, .date-badge, .track-progress, .step-icon { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .dashboard-split { grid-template-columns: 1fr; }
            .stat-pills { grid-template-columns: repeat(2, 1fr); }
        }

        @media (max-width: 768px) {
            .app-layout { display: block; height: 100dvh; overflow: hidden; }
            
            /* Hide Desktop Logo */
            .sidebar-logo { display: none; } 
            
            /* Show Mobile Logo in Topbar */
            .mobile-brand { display: flex; align-items: center; }
            
            /* Topbar Redesign for Mobile */
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; display: flex; align-items: center; }
            .user-profile { display: none; } /* Hide heavy profile details to make space for Translate! */
            
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
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); overflow-y: auto; overflow-x: hidden; padding-bottom: 25px; }
            .content-container { padding: 25px 20px; }
            
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 15px; }
            .welcome-banner h1 { font-size: 1.8rem; }
            .date-badge { width: 100%; justify-content: center; }

            .stat-pills { grid-template-columns: 1fr; gap: 15px; }
            .bento-card { padding: 20px; border-radius: var(--radius-lg); }
            .stat-pill { padding: 20px; border-radius: var(--radius-lg); }
            
            /* Responsive Tracker Fixes */
            .step-icon { width: 45px; height: 45px; font-size: 1.1rem; }
            .step-label { font-size: 0.75rem; } /* Smaller font to prevent overlap */
            .track-steps::before { top: 18px; height: 6px; }
            .track-progress { top: 19px; height: 6px; }
            
            .pick-card { padding: 15px 5px; flex-direction: column; text-align: center; }
            .pick-img { width: 100%; height: 160px; }
        }

        /* EXTRA SMALL SCREEN FIX (Hide Brand Name to guarantee space for Translate Pill) */
        @media (max-width: 480px) {
            .mobile-brand span { display: none; }
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
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> <span>Overview</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Buyer Command Center</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <div style="position: relative;">
                    <button class="theme-btn" id="notifToggle" title="Notifications" style="position: relative;">
                        <i class="fa-regular fa-bell"></i>
                        <?php if($note_count > 0): ?><span id="notifBadge" style="position: absolute; top: -2px; right: -2px; background: #ef4444; color: #fff; font-size: 0.65rem; font-weight: 900; padding: 4px 6px; border-radius: 12px; box-shadow: 0 2px 5px rgba(239,68,68,0.5);"><?php echo $note_count; ?></span><?php endif; ?>
                    </button>
                    <!-- Dropdown -->
                    <div id="notifDropdown" style="display: none; position: absolute; top: 60px; right: 0; width: 320px; background: var(--sidebar-bg); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: 0 10px 25px rgba(0,0,0,0.15); z-index: 1000; overflow: hidden; max-height: 400px; overflow-y: auto;">
                        <div style="padding: 15px 20px; border-bottom: 1px solid var(--border-color); font-weight: 900; color: var(--text-main); display: flex; justify-content: space-between; align-items: center;">
                            Notifications
                        </div>
                        <ul style="list-style: none; margin: 0; padding: 0;">
                            <?php if($notes && $notes->num_rows > 0): ?>
                                <?php 
                                    $notes->data_seek(0);
                                    while($n = $notes->fetch_assoc()): 
                                ?>
                                    <li style="padding: 15px 20px; border-bottom: 1px solid var(--border-color); font-size: 0.9rem; color: <?php echo $n['is_read'] ? 'var(--text-muted)' : 'var(--text-main)'; ?>; font-weight: <?php echo $n['is_read'] ? '500' : '800'; ?>;">
                                        <?php echo htmlspecialchars($n['message']); ?>
                                        <div style="font-size:0.75rem; color:var(--text-muted); margin-top:5px; font-weight: 600;"><?php echo date('M j, g:i a', strtotime($n['created_at'])); ?></div>
                                    </li>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <li style="padding: 30px 20px; text-align: center; color: var(--text-muted);">No new notifications</li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>

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
            
            <div class="welcome-banner">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! 👋</h1>
                    <p>Track your orders, review contracts, and explore the marketplace.</p>
                </div>
                <div class="date-badge">
                    <i class="fa-regular fa-calendar"></i> <?php echo date('l, F jS'); ?>
                </div>
            </div>

            <div class="stat-pills">
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-pill-info">
                        <h4>₹<?php echo number_format($total_spent); ?></h4>
                        <span>Total Spent</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fa-solid fa-box-open"></i></div>
                    <div class="stat-pill-info">
                        <h4><?php echo $total_orders; ?></h4>
                        <span>Total Orders</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-file-contract"></i></div>
                    <div class="stat-pill-info">
                        <h4><?php echo $active_contracts; ?></h4>
                        <span>Active Pre-Books</span>
                    </div>
                </div>
            </div>

            <?php if($latest_order): ?>
                <?php 
                    $status = strtolower($latest_order['order_status']);
                    $progress_width = '0%';
                    $step1 = 'active'; $step2 = ''; $step3 = '';
                    
                    // We map the 70% width to the CSS calculation logically
                    if($status == 'shipped') {
                        $progress_width = '35%'; // Exact halfway point of the bar
                        $step2 = 'active';
                    } elseif($status == 'delivered') {
                        $progress_width = '70%'; // Reaches the center of the final icon perfectly
                        $step2 = 'active'; $step3 = 'active';
                    }
                ?>
                <div class="bento-card">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 15px;">
                        <div>
                            <h3 style="margin: 0 0 8px 0; font-size: 1.5rem; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-location-crosshairs" style="color: #10b981; margin-right: 8px;"></i> Live Order Tracking</h3>
                            <div style="font-size: 1rem; font-weight: 500;" class="text-muted">Tracking latest active order: <strong class="logo-text" style="color: var(--text-main);">#ORD-<?php echo $latest_order['id']; ?></strong></div>
                        </div>
                        <a href="orders.php" style="background: var(--bg-light); border: 1px solid var(--border-color); padding: 12px 22px; border-radius: 50px; color: var(--text-main); font-weight: 800; text-decoration: none; font-size: 0.95rem; transition: 0.3s; box-shadow: var(--shadow-subtle);"><i class="fa-solid fa-boxes-stacked"></i> View History</a>
                    </div>
                    
                    <div class="track-steps">
                        <div class="track-progress" style="width: <?php echo $progress_width; ?>;"></div>
                        
                        <div class="step <?php echo $step1; ?>">
                            <div class="step-icon"><i class="fa-solid fa-box"></i></div>
                            <div class="step-label">Processing</div>
                        </div>
                        <div class="step <?php echo $step2; ?>">
                            <div class="step-icon"><i class="fa-solid fa-truck-fast"></i></div>
                            <div class="step-label">Shipped</div>
                        </div>
                        <div class="step <?php echo $step3; ?>">
                            <div class="step-icon"><i class="fa-solid fa-house-circle-check"></i></div>
                            <div class="step-label">Delivered</div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <div class="dashboard-split">
                
                <div class="bento-card">
                    <div class="widget-header">
                        <div class="widget-title"><i class="fa-brands fa-whatsapp" style="color: #10b981;"></i> Recent Messages</div>
                        <a href="my_enquiries.php" style="color: var(--primary); font-size: 0.95rem; font-weight: 800; text-decoration: none; background: rgba(16, 185, 129, 0.1); padding: 8px 16px; border-radius: 20px;">Open Inbox <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div class="chat-list">
                        <?php if(count($recent_messages) > 0): ?>
                            <?php foreach($recent_messages as $msg): ?>
                                <a href="my_enquiries.php?id=<?php echo $msg['enquiry_id']; ?>" class="chat-item">
                                    <div class="chat-avatar"><?php echo strtoupper(substr($msg['farmer_name'], 0, 1)); ?></div>
                                    <div style="flex: 1; overflow: hidden;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 5px;">
                                            <strong style="font-size: 1.05rem; text-transform: capitalize;" class="logo-text"><?php echo htmlspecialchars($msg['farmer_name']); ?></strong>
                                            <span style="font-size: 0.85rem; color: #94a3b8; font-weight: 800;"><?php echo date('M j, g:i a', strtotime($msg['created_at'])); ?></span>
                                        </div>
                                        <div style="font-size: 0.95rem; color: <?php echo $msg['is_read'] ? 'var(--text-muted)' : 'var(--text-main)'; ?>; font-weight: <?php echo $msg['is_read'] ? '500' : '800'; ?>; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; transition: 0.3s;">
                                            <?php echo htmlspecialchars($msg['message']); ?>
                                        </div>
                                    </div>
                                    <?php if(!$msg['is_read']): ?>
                                        <div class="unread-dot"></div>
                                    <?php endif; ?>
                                </a>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div style="padding: 50px 10px; text-align: center; color: var(--text-muted);">
                                <i class="fa-regular fa-comments" style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3;"></i>
                                <div style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);" class="logo-text">No recent messages.</div>
                                <div style="font-size: 1rem; margin-top: 5px; font-weight: 500;">Start a negotiation with a farmer from the marketplace!</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bento-card">
                    <div class="widget-header">
                        <div class="widget-title"><i class="fa-solid fa-wand-magic-sparkles" style="color: #f59e0b;"></i> Today's Fresh Picks</div>
                        <a href="marketplace.php" style="color: #f59e0b; font-size: 0.95rem; font-weight: 800; text-decoration: none; background: rgba(245, 158, 11, 0.1); padding: 8px 16px; border-radius: 20px;">View Market <i class="fa-solid fa-arrow-right"></i></a>
                    </div>
                    <div>
                        <?php if($fresh_picks->num_rows > 0): ?>
                            <?php while($pick = $fresh_picks->fetch_assoc()): ?>
                                <div class="pick-card">
                                    <img src="../uploads/<?php echo $pick['image'] ?: 'placeholder.jpg'; ?>" class="pick-img">
                                    <div style="flex: 1;">
                                        <div style="font-weight: 900; font-size: 1.25rem; text-transform: capitalize; margin-bottom: 4px; letter-spacing: -0.5px;" class="logo-text"><?php echo htmlspecialchars($pick['crop_name']); ?></div>
                                        <div style="font-size: 0.95rem; margin-bottom: 12px; font-weight: 600;" class="text-muted">
                                            By <?php echo htmlspecialchars($pick['farmer_name']); ?> 
                                            <?php if($pick['badge'] == 'verified') echo '<i class="fa-solid fa-circle-check" style="color:#3b82f6; margin-left: 4px;" title="Verified Farmer"></i>'; ?>
                                        </div>
                                        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                                            <div style="font-weight: 900; color: var(--primary); font-size: 1.3rem;">₹ <?php echo $pick['market_price']; ?> <span style="font-size:0.85rem; color:var(--text-muted); font-weight:800;">/ kg</span></div>
                                            <a href="crop_details.php?id=<?php echo $pick['id']; ?>" style="background: var(--card-bg); color: var(--text-main); border: 2px solid var(--border-color); padding: 10px 20px; border-radius: 50px; font-size: 0.9rem; font-weight: 800; text-decoration: none; transition: 0.3s; box-shadow: var(--shadow-subtle);">View <i class="fa-solid fa-arrow-right" style="margin-left: 4px; font-size: 0.8rem;"></i></a>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="padding: 50px 10px; text-align: center; color: var(--text-muted);">
                                <i class="fa-solid fa-seedling" style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3;"></i>
                                <div style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);" class="logo-text">No fresh crops available.</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

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

                // Push to DB
                fetch('../api/update_theme.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'theme=' + targetTheme
                });
            });
        }

        const notifToggleBtn = document.getElementById('notifToggle');
        const notifDropdown = document.getElementById('notifDropdown');
        const notifBadge = document.getElementById('notifBadge');
        if(notifToggleBtn && notifDropdown) {
            notifToggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                if(notifDropdown.style.display === 'none' || notifDropdown.style.display === '') {
                    notifDropdown.style.display = 'block';
                    // Mark as read API call
                    if(notifBadge) {
                        fetch('../api/mark_notifications_read.php', { method: 'POST' })
                        .then(() => { notifBadge.style.display = 'none'; });
                    }
                } else {
                    notifDropdown.style.display = 'none';
                }
            });
            document.addEventListener('click', (e) => {
                if(!notifDropdown.contains(e.target) && e.target !== notifToggleBtn) {
                    notifDropdown.style.display = 'none';
                }
            });
        }
    });
</script>

<script src="../assets/js/translate.js"></script>

<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
      appId: "18b169e4-c3f3-4002-81fc-753932dbdbe7", // Replace with your App ID
      notifyButton: { enable: true },
      allowLocalhostAsSecureOrigin: true,
      serviceWorkerPath: "/farmogram/OneSignalSDKWorker.js",
      serviceWorkerParam: { scope: "/farmogram/" }
    });

    <?php if(isset($_SESSION['user_id'])): ?>
        await OneSignal.login("<?php echo $_SESSION['user_id']; ?>");
    <?php endif; ?>
  });
</script>

<style>
/* Utility class to hide the full user name text on mobile to ensure topbar icons have space */
@media (max-width: 768px) {
    .desktop-only-text { display: none; }
}
</style>

</body>
</html>