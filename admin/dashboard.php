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

/* ===== 1. PLATFORM GLOBAL STATS & COMMISSION ===== */
$user_count = $conn->query("SELECT COUNT(id) as count FROM users WHERE role != 'admin'")->fetch_assoc()['count'] ?? 0;
$order_count = $conn->query("SELECT COUNT(id) as count FROM orders")->fetch_assoc()['count'] ?? 0;

$gmv_query = $conn->query("SELECT SUM(total_amount) as total FROM orders WHERE order_status != 'cancelled'");
$total_gmv = $gmv_query->fetch_assoc()['total'] ?? 0;

// Calculate Platform Commission (e.g., 5% fee on all successful sales)
$platform_earnings = $total_gmv * 0.05; 

/* ===== 2. ACTION CENTER: STALE ORDERS & REFUNDS ===== */
// Fetch orders stuck in processing
$stale_orders = [];
$pending_query = $conn->query("
    SELECT orders.id, orders.created_at, users.name AS farmer_name 
    FROM orders 
    JOIN order_items ON orders.id = order_items.order_id 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN users ON crops.farmer_id = users.id 
    WHERE orders.order_status = 'processing' 
    GROUP BY orders.id 
    ORDER BY orders.created_at ASC
");
while($row = $pending_query->fetch_assoc()) {
    $hours_pending = round((time() - strtotime($row['created_at'])) / 3600);
    if($hours_pending > 48) { // If stuck for more than 48 hours
        $row['hours_stuck'] = $hours_pending;
        $stale_orders[] = $row;
    }
}

// Cancelled orders that might need refunds processed
$refunds_needed = $conn->query("SELECT COUNT(id) as count FROM orders WHERE order_status = 'cancelled'")->fetch_assoc()['count'] ?? 0;

/* ===== 3. LIVE ACTIVITY: RECENT ORDERS ===== */
$recent_orders = $conn->query("
    SELECT orders.id, orders.total_amount, orders.order_status, orders.created_at, users.name AS buyer_name 
    FROM orders 
    JOIN users ON orders.buyer_id = users.id 
    ORDER BY orders.created_at DESC LIMIT 6
");

/* ===== 4. PLATFORM AUDIT TIMELINE (Merging Users & Crops) ===== */
$timeline = [];

// Get newest 3 users
$new_users = $conn->query("SELECT name, role, created_at FROM users WHERE role != 'admin' ORDER BY created_at DESC LIMIT 3");
while($u = $new_users->fetch_assoc()) {
    $timeline[] = [
        'type' => 'user', 
        'icon' => 'fa-user-plus', 
        'color' => '#8b5cf6', 
        'bg' => 'rgba(139, 92, 246, 0.1)',
        'text' => "New " . ucfirst($u['role']) . " registered: <strong>" . htmlspecialchars($u['name']) . "</strong>",
        'time' => strtotime($u['created_at'])
    ];
}

// Get newest 3 crops
$new_crops = $conn->query("
    SELECT crops.crop_name, crops.created_at, users.name as farmer_name 
    FROM crops JOIN users ON crops.farmer_id = users.id 
    ORDER BY crops.created_at DESC LIMIT 3
");
while($c = $new_crops->fetch_assoc()) {
    $timeline[] = [
        'type' => 'crop', 
        'icon' => 'fa-seedling', 
        'color' => '#10b981', 
        'bg' => 'rgba(16, 185, 129, 0.1)',
        'text' => "Farmer " . htmlspecialchars($c['farmer_name']) . " listed a new crop: <strong>" . htmlspecialchars($c['crop_name']) . "</strong>",
        'time' => strtotime($c['created_at'])
    ];
}

// Sort timeline by newest first
usort($timeline, function($a, $b) { return $b['time'] <=> $a['time']; });
$timeline = array_slice($timeline, 0, 5); // Keep top 5 latest events
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Admin Command Center | Farmogram</title>
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
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1600px; margin: 0 auto; width: 100%; }

        /* Custom Translation Widget Styling - Force Pill Shape */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Stat Pills */
        .stats-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 30px; }
        .stat-card { background: var(--card-bg); padding: 30px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); display: flex; flex-direction: column; position: relative; overflow: hidden; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .stat-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .stat-header { display: flex; justify-content: space-between; align-items: center; font-size: 0.95rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 15px; transition: 0.3s; }
        .stat-value { font-size: 2.2rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .stat-icon { width: 55px; height: 55px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.5rem; transition: 0.3s; }
        
        /* Dashboard Split Grid */
        .dashboard-grid { display: grid; grid-template-columns: 1.8fr 1fr; gap: 30px; }
        
        /* Bento Card Wrappers */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); padding: 35px; border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); margin-bottom: 30px; transition: 0.3s; display: flex; flex-direction: column; }
        .bento-card:hover { box-shadow: var(--shadow-hover); }
        .bento-card-header { font-size: 1.3rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; gap: 10px; transition: 0.3s; letter-spacing: -0.5px; }
        
        /* Action Alerts */
        .alert-box { display: flex; gap: 20px; background: rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.3); padding: 20px 25px; border-radius: var(--radius-md); margin-bottom: 15px; align-items: flex-start; transition: 0.3s; }
        .alert-box.danger { background: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); }
        .alert-icon { font-size: 1.8rem; color: #d97706; margin-top: 5px; }
        .alert-box.danger .alert-icon { color: #ef4444; }
        .alert-text strong { color: var(--text-main); display: block; margin-bottom: 5px; font-size: 1.1rem; }
        .alert-text p { margin: 0; font-size: 0.95rem; color: var(--text-muted); font-weight: 500; }

        /* Table Styles */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; }
        .data-table { width: 100%; border-collapse: collapse; text-align: left; }
        .data-table th { padding: 15px 10px; color: var(--text-muted); font-size: 0.9rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px dashed var(--border-color); white-space: nowrap; }
        .data-table td { padding: 18px 10px; font-weight: 600; color: var(--text-main); border-bottom: 1px dashed var(--border-color); white-space: nowrap; transition: 0.3s; }
        
        .status-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; margin-right: 6px; }
        .dot-processing { background: #3b82f6; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
        .dot-shipped { background: #f59e0b; box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2); }
        .dot-delivered { background: #10b981; box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.2); }
        .dot-cancelled { background: #ef4444; box-shadow: 0 0 0 3px rgba(239, 68, 68, 0.2); }
        
        /* Timeline Styles */
        .timeline { position: relative; padding-left: 35px; margin: 0; list-style: none; }
        .timeline::before { content: ''; position: absolute; left: 16px; top: 0; height: 100%; width: 2px; background: var(--border-color); transition: 0.3s; }
        .timeline-item { position: relative; margin-bottom: 30px; }
        .timeline-item:last-child { margin-bottom: 0; }
        .timeline-icon { position: absolute; left: -35px; top: 0; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; z-index: 1; border: 4px solid var(--card-bg); transition: 0.3s; }
        .timeline-content { background: var(--bg-light); padding: 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); font-size: 0.95rem; color: var(--text-main); line-height: 1.5; transition: 0.3s; }
        .timeline-content strong { color: var(--text-main); }
        .timeline-time { font-size: 0.8rem; color: var(--text-muted); font-weight: 700; margin-top: 8px; display: block; }
        
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
        [data-theme="dark"] .stat-card, [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .timeline-content { background-color: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .timeline-icon { border-color: var(--sidebar-bg) !important; }
        [data-theme="dark"] .timeline::before { background: var(--border-color) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .bento-card-header, [data-theme="dark"] .stat-value, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] th { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .stat-card, .alert-box, .timeline-content, h1, h2, h3, h4, p, span, div, td, th { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; }

        /* =========================================================
           RESPONSIVE RULES (TABLET & MOBILE)
        ========================================================= */
        @media (max-width: 1200px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .dashboard-grid { grid-template-columns: 1fr; }
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
            
            .stats-grid { grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; }
            .bento-card { padding: 20px; border-radius: var(--radius-lg); margin-bottom: 20px; }
            .stat-card { padding: 20px; border-radius: var(--radius-lg); }
            
            .alert-box { flex-direction: column; gap: 10px; }
            .alert-icon { font-size: 2rem; }
            .bento-card-header { font-size: 1.2rem; margin-bottom: 20px; }
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
            <img src="../assets/images/logo-light.png" alt="Farmogram" class="logo-light" style="width: 35px; height: 35px; object-fit: contain;">
            <img src="../assets/images/logo-dark.png" alt="Farmogram" class="logo-dark" style="width: 35px; height: 35px; object-fit: contain;">
            <span class="logo-text">Farmogram</span>
        </a>

        <ul class="nav-menu">
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-chart-pie"></i> <span>Overview</span></a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> <span>Manage Users</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-boxes-stacked"></i> <span>All Orders</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> <span>Analytics</span></a></li>
            <li><a href="claims.php"><i class="fa-solid fa-file-shield"></i> <span>Disaster Claims</span></a></li>
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

            <div class="page-title logo-text" style="font-size: 1.6rem;">Admin Command Center</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>

                <button class="theme-btn" id="themeToggle" title="Toggle Theme" style="margin-right: 10px;">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <div class="user-profile" style="border-left: none; padding-left: 0; display: flex; align-items: center;">
                    <div style="text-align: right; margin-right: 10px;" class="desktop-only-text">
                        <div style="font-weight: 800; font-size: 0.95rem;" class="logo-text"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">Administrator</div>
                    </div>
                    <div class="avatar" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 900; font-size: 1.1rem; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0;"><?php echo $avatar_letter; ?></div>
                </div>
            </div>
        </header>

        <div class="content-container">
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header"><span>Platform GMV</span> <div class="stat-icon" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fa-solid fa-money-bill-trend-up"></i></div></div>
                    <div class="stat-value">₹ <?php echo number_format($total_gmv); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><span>Est. Profit (5%)</span> <div class="stat-icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fa-solid fa-vault"></i></div></div>
                    <div class="stat-value" style="color: #3b82f6;">₹ <?php echo number_format($platform_earnings); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><span>Total Orders</span> <div class="stat-icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-boxes-packing"></i></div></div>
                    <div class="stat-value"><?php echo number_format($order_count); ?></div>
                </div>
                <div class="stat-card">
                    <div class="stat-header"><span>Active Users</span> <div class="stat-icon" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;"><i class="fa-solid fa-users"></i></div></div>
                    <div class="stat-value"><?php echo number_format($user_count); ?></div>
                </div>
            </div>

            <div class="dashboard-grid">
                
                <div>
                    <div class="bento-card">
                        <div class="bento-card-header"><i class="fa-solid fa-triangle-exclamation" style="color: #ef4444;"></i> Needs Attention</div>
                        
                        <?php if(count($stale_orders) > 0): ?>
                            <?php foreach($stale_orders as $stale): ?>
                                <div class="alert-box">
                                    <i class="fa-solid fa-clock-rotate-left alert-icon"></i>
                                    <div class="alert-text">
                                        <strong>Order #<?php echo $stale['id']; ?> is severely delayed!</strong>
                                        <p>Farmer <b><?php echo htmlspecialchars($stale['farmer_name']); ?></b> has not shipped this order for <?php echo $stale['hours_stuck']; ?> hours. Please contact them.</p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                        <?php if($refunds_needed > 0): ?>
                            <div class="alert-box danger">
                                <i class="fa-solid fa-money-check-dollar alert-icon"></i>
                                <div class="alert-text">
                                    <strong style="color: #ef4444;">Pending Refunds Detected</strong>
                                    <p>There are <?php echo $refunds_needed; ?> cancelled orders in the system. Ensure buyers have received their refunds via the payment gateway.</p>
                                </div>
                            </div>
                        <?php endif; ?>

                        <?php if(count($stale_orders) == 0 && $refunds_needed == 0): ?>
                            <div style="text-align: center; padding: 30px; color: #10b981;">
                                <i class="fa-solid fa-check-circle" style="font-size: 3rem; margin-bottom: 15px;"></i>
                                <div style="font-weight: 900; font-size: 1.2rem; letter-spacing: -0.5px;" class="logo-text">System is healthy!</div>
                                <div style="font-size: 1rem; color: var(--text-muted); margin-top: 5px; font-weight: 500;">No pending actions required.</div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="bento-card" style="padding: 0; overflow: hidden;">
                        <div class="bento-card-header" style="padding: 30px 35px 25px 35px; border-bottom: 1px solid var(--border-color); margin: 0; background: var(--bg-light);">
                            <span><i class="fa-solid fa-globe" style="color: #3b82f6;"></i> Recent Global Transactions</span>
                        </div>
                        <div style="padding: 0 35px 30px 35px; overflow-x: auto;">
                            <?php if($recent_orders->num_rows > 0): ?>
                                <div class="table-responsive">
                                    <table class="data-table">
                                        <thead>
                                            <tr>
                                                <th>Order ID</th>
                                                <th>Buyer</th>
                                                <th>Amount</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php while($order = $recent_orders->fetch_assoc()): ?>
                                                <?php 
                                                    $status = strtolower($order['order_status']);
                                                    $dot_class = 'dot-' . $status;
                                                ?>
                                                <tr>
                                                    <td style="font-weight: 800;" class="logo-text">#ORD-<?php echo $order['id']; ?></td>
                                                    <td style="text-transform: capitalize;"><?php echo htmlspecialchars($order['buyer_name']); ?></td>
                                                    <td style="font-weight: 900; color: #10b981;">₹<?php echo number_format($order['total_amount']); ?></td>
                                                    <td style="text-transform: capitalize; font-size: 0.9rem;">
                                                        <span class="status-dot <?php echo $dot_class; ?>"></span> <?php echo $order['order_status']; ?>
                                                    </td>
                                                </tr>
                                            <?php endwhile; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div style="text-align: center; margin-top: 25px;">
                                    <a href="orders.php" style="font-size: 0.95rem; font-weight: 800; color: var(--primary); text-decoration: none; padding: 12px 24px; background: rgba(16, 185, 129, 0.1); border-radius: 50px; transition: 0.3s; display: inline-block;"><i class="fa-solid fa-boxes-stacked"></i> Manage All Orders</a>
                                </div>
                            <?php else: ?>
                                <div style="text-align: center; color: var(--text-muted); padding: 40px;">No transactions have occurred yet.</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div>
                    <div class="bento-card" style="background: linear-gradient(135deg, #1e293b, #0f172a); color: #fff; padding: 30px;">
                        <div style="display: flex; align-items: center; justify-content: space-between;">
                            <div>
                                <div style="font-weight: 900; font-size: 1.3rem; margin-bottom: 5px; color: #fff; letter-spacing: -0.5px;"><i class="fa-solid fa-server" style="color: #10b981; margin-right: 8px;"></i> System Status</div>
                                <div style="font-size: 0.95rem; color: #94a3b8; font-weight: 600;">All core services online & secured.</div>
                            </div>
                            <div style="font-size: 2.8rem; color: #10b981;"><i class="fa-solid fa-shield-check"></i></div>
                        </div>
                    </div>

                    <div class="bento-card">
                        <div class="bento-card-header"><i class="fa-solid fa-list-check" style="color: #8b5cf6;"></i> Platform Audit Log</div>
                        <div style="padding-top: 15px;">
                            <?php if(count($timeline) > 0): ?>
                                <ul class="timeline">
                                    <?php foreach($timeline as $event): ?>
                                        <li class="timeline-item">
                                            <div class="timeline-icon" style="color: <?php echo $event['color']; ?>; background: <?php echo $event['bg']; ?>;">
                                                <i class="fa-solid <?php echo $event['icon']; ?>"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <?php echo $event['text']; ?>
                                                <span class="timeline-time"><i class="fa-regular fa-clock"></i> <?php echo date('M j, Y - g:i A', $event['time']); ?></span>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div style="text-align: center; color: var(--text-muted); padding: 20px;">No recent activity.</div>
                            <?php endif; ?>
                        </div>
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