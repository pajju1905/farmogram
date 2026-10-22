<?php
session_start();
include("../config/db.php");
include("../config/push_engine.php");

// STRICT ROLE CHECK
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
$user_name = $_SESSION['name'] ?? 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

// Retrieve current verification status
$user_status_query = $conn->query("SELECT verification_status, verification_remarks, profile_image FROM users WHERE id = $farmer_id");
$user_status_row = $user_status_query->fetch_assoc();
$verification_status = $user_status_row['verification_status'] ?? 'unverified';
$verification_remarks = $user_status_row['verification_remarks'] ?? '';
$profile_image = $user_status_row['profile_image'] ?? '';

/* =========================================================
   AUTO SCANNERS: HARVESTS, DELAYS, & NEW TASK NOTIFICATIONS
========================================================= */
$harvest_check = $conn->query("SELECT * FROM crops WHERE farmer_id = $farmer_id AND status = 'growing' AND DATEDIFF(expected_harvest, CURDATE()) BETWEEN 0 AND 3");
if($harvest_check) {
    while($crop = $harvest_check->fetch_assoc()){
        $message = "Harvest approaching for ".$crop['crop_name'];
        $exists = $conn->query("SELECT * FROM notifications WHERE user_id = $farmer_id AND message = '$message'");
        if($exists->num_rows == 0){
            $conn->query("INSERT INTO notifications(user_id,message) VALUES($farmer_id,'$message')");
            sendMobilePush($farmer_id, "Harvest Alert 🌾", $message);
        }
    }
}

$delay_check = $conn->query("
    SELECT o.id, c.crop_name 
    FROM order_items oi 
    JOIN orders o ON oi.order_id = o.id 
    JOIN crops c ON oi.crop_id = c.id 
    WHERE c.farmer_id = $farmer_id 
    AND o.order_status = 'processing' 
    AND c.status = 'for_sale' 
    AND o.created_at < NOW() - INTERVAL 2 DAY
");
if($delay_check) {
    while($delayed = $delay_check->fetch_assoc()){
        $msg = "URGENT: Order #ORD-".$delayed['id']." (".$delayed['crop_name'].") is delayed! Please pack and ship immediately.";
        $exists = $conn->query("SELECT * FROM notifications WHERE user_id = $farmer_id AND message = '$msg'");
        if($exists->num_rows == 0){
            $conn->query("INSERT INTO notifications(user_id,message) VALUES($farmer_id,'$msg')");
            sendMobilePush($farmer_id, "Urgent Notice 🚨", $msg);
        }
    }
}

$task_check = $conn->query("
    SELECT t.id, t.task_type, c.crop_name 
    FROM farm_tasks t
    JOIN crops c ON t.crop_id = c.id
    WHERE t.farmer_id = $farmer_id 
    AND t.status = 'pending' 
    AND DATE(t.due_date) <= CURDATE()
");
if($task_check) {
    while($task = $task_check->fetch_assoc()){
        $msg = "🔔 Reminder: Pending " . $task['task_type'] . " task for your " . $task['crop_name'] . "!";
        $exists = $conn->query("SELECT * FROM notifications WHERE user_id = $farmer_id AND message = '$msg'");
        if($exists->num_rows == 0){
            $conn->query("INSERT INTO notifications(user_id,message) VALUES($farmer_id,'$msg')");
            sendMobilePush($farmer_id, "Farm Task Reminder 📌", $msg);
        }
    }
}

/* ===== 1. BASIC STATS ===== */
$totalCrops = $conn->query("SELECT COUNT(id) AS count FROM crops WHERE farmer_id = $farmer_id AND status IN ('growing', 'for_sale')")->fetch_assoc()['count'] ?? 0;

$revenueData = $conn->query("
    SELECT SUM(order_items.quantity * order_items.price) AS total
    FROM order_items JOIN crops ON order_items.crop_id = crops.id JOIN orders ON order_items.order_id = orders.id
    WHERE crops.farmer_id = $farmer_id AND orders.order_status != 'cancelled'
");
$totalRevenue = $revenueData->fetch_assoc()['total'] ?? 0;

$pendingOrdersCount = $conn->query("
    SELECT COUNT(DISTINCT orders.id) AS pending
    FROM order_items JOIN crops ON order_items.crop_id = crops.id JOIN orders ON order_items.order_id = orders.id
    WHERE crops.farmer_id = $farmer_id AND orders.order_status = 'processing'
")->fetch_assoc()['pending'] ?? 0;

$completedOrdersCount = $conn->query("
    SELECT COUNT(DISTINCT orders.id) AS completed
    FROM order_items JOIN crops ON order_items.crop_id = crops.id JOIN orders ON order_items.order_id = orders.id
    WHERE crops.farmer_id = $farmer_id AND orders.order_status = 'delivered'
")->fetch_assoc()['completed'] ?? 0;

/* ===== 2. ACTION REQUIRED ===== */
$pending_orders = $conn->query("
    SELECT orders.id AS order_id, users.name AS buyer_name, crops.crop_name, crops.status AS crop_status, crops.expected_harvest, order_items.quantity, orders.created_at 
    FROM order_items 
    JOIN orders ON order_items.order_id = orders.id 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN users ON orders.buyer_id = users.id 
    WHERE crops.farmer_id = $farmer_id AND orders.order_status = 'processing' 
    ORDER BY orders.created_at ASC LIMIT 4
");

/* ===== 3. UPCOMING HARVESTS ===== */
$upcoming_harvests = $conn->query("
    SELECT crop_name, expected_harvest, DATEDIFF(expected_harvest, CURDATE()) as days_left 
    FROM crops 
    WHERE farmer_id = $farmer_id AND status = 'growing' 
    ORDER BY days_left ASC LIMIT 4
");

/* ===== 4. TODAY'S FARM TASKS ===== */
$today_tasks = $conn->query("
    SELECT t.*, c.crop_name 
    FROM farm_tasks t 
    JOIN crops c ON t.crop_id = c.id 
    WHERE t.farmer_id = $farmer_id 
    AND t.status = 'pending' 
    AND DATE(t.due_date) <= CURDATE()
    ORDER BY t.due_date ASC LIMIT 4
");

function getDashTaskStyle($type) {
    switch($type) {
        case 'Watering': return ['icon' => 'fa-droplet', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.12)'];
        case 'Fertilizer': return ['icon' => 'fa-flask', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.12)'];
        case 'Pesticide': return ['icon' => 'fa-spray-can', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.12)'];
        case 'Weeding': return ['icon' => 'fa-leaf', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.12)'];
        case 'Harvesting': return ['icon' => 'fa-wheat-awn', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.12)'];
        default: return ['icon' => 'fa-thumbtack', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.12)'];
    }
}

/* ===== 5. NOTIFICATIONS, REVIEWS & ENQUIRIES ===== */
$notes = $conn->query("SELECT * FROM notifications WHERE user_id = $farmer_id ORDER BY created_at DESC LIMIT 6");

$recent_enquiries = $conn->query("
    SELECT e.id AS enquiry_id, u.name AS buyer_name, c.crop_name, 
           (SELECT message FROM enquiry_messages WHERE enquiry_id = e.id ORDER BY created_at DESC LIMIT 1) as last_msg,
           (SELECT COUNT(*) FROM enquiry_messages WHERE enquiry_id = e.id AND sender_id != $farmer_id AND is_read = 0) as unread_count
    FROM enquiries e 
    JOIN users u ON e.buyer_id = u.id 
    JOIN crops c ON e.crop_id = c.id 
    WHERE e.farmer_id = $farmer_id 
    ORDER BY (SELECT MAX(created_at) FROM enquiry_messages WHERE enquiry_id = e.id) DESC
    LIMIT 3
");
$unread_query = $conn->query("SELECT COUNT(id) AS unread FROM notifications WHERE user_id = $farmer_id AND is_read = 0");
$note_count = $unread_query->fetch_assoc()['unread'] ?? 0;

$reviews = $conn->query("
    SELECT reviews.*, users.name as buyer_name 
    FROM reviews JOIN users ON reviews.buyer_id = users.id 
    WHERE reviews.farmer_id = $farmer_id ORDER BY created_at DESC LIMIT 4
");

$loc_query = $conn->query("SELECT location FROM users WHERE id = '$farmer_id'");
if($loc_query && $loc_query->num_rows > 0) {
    $loc_row = $loc_query->fetch_assoc();
    $farmer_city = !empty($loc_row['location']) ? $loc_row['location'] : 'Bengaluru';
} else {
    $farmer_city = 'Bengaluru';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farmer Dashboard | Farmogram</title>
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

        /* Welcome Banner */
        .welcome-banner { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 35px; }
        .welcome-banner h1 { font-size: 2.4rem; font-weight: 900; color: var(--text-main); margin-bottom: 8px; letter-spacing: -1px; transition: 0.3s; }
        .welcome-banner p { color: var(--text-muted); font-size: 1.1rem; font-weight: 500; transition: 0.3s; }
        .date-badge { background: var(--bg-light); padding: 12px 24px; border-radius: 50px; font-weight: 800; font-size: 0.95rem; color: var(--primary); box-shadow: var(--shadow-subtle); display: flex; align-items: center; gap: 10px; border: 1px solid var(--border-color); transition: 0.3s; }

        /* Premium Bento Cards */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); padding: 30px; box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); position: relative; overflow: hidden; border: 1px solid var(--border-color); display: flex; flex-direction: column; }
        .bento-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        .bento-header { font-size: 1.25rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; gap: 12px; transition: 0.3s; letter-spacing: -0.5px; }

        /* Stat Pills */
        .stat-pills { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 30px; }
        .stat-pill { background: var(--card-bg); border-radius: var(--radius-xl); padding: 25px; display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-subtle); transition: 0.4s; border: 1px solid var(--border-color); }
        .stat-pill:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: var(--shadow-hover); }
        .stat-icon-wrapper { width: 65px; height: 65px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-pill-info h4 { font-size: 2rem; font-weight: 900; color: var(--text-main); line-height: 1; margin-bottom: 6px; transition: 0.3s; letter-spacing: -1px; }
        .stat-pill-info span { font-size: 0.85rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }

        /* Master Grid */
        .bento-master-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 24px; }
        .col-span-3 { grid-column: span 3; }
        .col-span-2 { grid-column: span 2; }
        .col-span-1 { grid-column: span 1; }

        /* Stunning Weather Card */
        .weather-bento { background: linear-gradient(135deg, #3b82f6, #4f46e5) !important; color: #fff !important; border: none !important; }
        .weather-bento * { color: #fff !important; }
        .weather-icon-bg { position: absolute; right: -30px; top: -20px; font-size: 15rem; opacity: 0.08; transform: rotate(15deg); pointer-events: none; transition: 0.5s; }
        .weather-glass-panel { background: rgba(255,255,255,0.1); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-radius: var(--radius-md); padding: 20px; margin-top: auto; border: 1px solid rgba(255,255,255,0.2); }

        /* Modern List Items */
        .bento-list-item { display: flex; align-items: center; justify-content: space-between; padding: 18px 20px; background: var(--bg-light); border-radius: var(--radius-md); margin-bottom: 12px; transition: 0.3s; border: 1px solid var(--border-color); }
        .bento-list-item:hover { background: rgba(16, 185, 129, 0.03); transform: translateX(4px); border-color: rgba(16, 185, 129, 0.3); }
        
        .bento-btn { background: var(--card-bg) !important; color: var(--primary) !important; padding: 10px 22px; border-radius: 50px; font-size: 0.9rem; font-weight: 800; text-decoration: none; transition: 0.3s; border: 1px solid var(--border-color) !important; display: inline-block; box-shadow: var(--shadow-subtle); }
        .bento-btn:hover { background: var(--primary) !important; color: #fff !important; border-color: var(--primary) !important; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.25); }

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
        [data-theme="dark"] .bento-card, [data-theme="dark"] .stat-pill, [data-theme="dark"] .date-badge { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .bento-list-item { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-btn { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .bento-header, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .stat-pill, .bento-list-item, h1, h2, h3, h4, p, span, div, .date-badge { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .bento-master-grid { grid-template-columns: 1fr 1fr; }
            .col-span-3, .col-span-2 { grid-column: span 2; }
            .col-span-1 { grid-column: span 1; }
            .stat-pills { grid-template-columns: 1fr 1fr; }
            .welcome-banner { flex-direction: column; align-items: flex-start; gap: 15px; }
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
            .content-container { padding: 25px 20px; }
            
            /* Stack Grid completely */
            .bento-master-grid { grid-template-columns: 1fr; gap: 20px; }
            .col-span-3, .col-span-2, .col-span-1 { grid-column: span 1; }
            .stat-pills { grid-template-columns: 1fr; gap: 15px; }
            
            .welcome-banner h1 { font-size: 1.8rem; }
            .date-badge { width: 100%; justify-content: center; }
            
            .bento-card { padding: 20px; border-radius: var(--radius-lg); }
            .stat-pill { padding: 20px; border-radius: var(--radius-lg); }
            
            .bento-list-item { flex-direction: column; align-items: stretch; gap: 15px; padding: 20px; }
            .bento-btn { text-align: center; padding: 12px; font-size: 1rem; }
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
            <li><a href="dashboard.php" class="active"><i class="fa-solid fa-house"></i> <span>Home</span></a></li>
            <li><a href="view_crops.php"><i class="fa-solid fa-leaf"></i> <span>Inventory</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-cart-flatbed"></i> <span>Orders</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Dashboard</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <button class="theme-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

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
                            <?php $notes->data_seek(0); // Reset for the bottom widget ?>
                        </ul>
                    </div>
                </div>
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
            
            <?php if($verification_status !== 'verified'): ?>
                <div class="verification-banner" style="background: <?php 
                    if($verification_status == 'pending') echo 'rgba(245, 158, 11, 0.1); border: 1px solid rgba(245, 158, 11, 0.2); color: #f59e0b;';
                    elseif($verification_status == 'rejected') echo 'rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444;';
                    else echo 'rgba(100, 116, 139, 0.1); border: 1px solid rgba(100, 116, 139, 0.2); color: var(--text-main);';
                ?>; padding: 25px; border-radius: var(--radius-lg); margin-bottom: 30px; display: flex; align-items: center; justify-content: space-between; gap: 20px; box-shadow: var(--shadow-subtle); backdrop-filter: blur(10px);">
                    <div style="display: flex; align-items: center; gap: 15px;">
                        <div style="font-size: 2.2rem; display: flex; align-items: center; justify-content: center; flex-shrink: 0;">
                            <?php 
                                if($verification_status == 'pending') echo '<i class="fa-solid fa-clock-rotate-left"></i>';
                                elseif($verification_status == 'rejected') echo '<i class="fa-solid fa-circle-xmark"></i>';
                                else echo '<i class="fa-solid fa-shield-halved" style="color: #ef4444;"></i>';
                            ?>
                        </div>
                        <div>
                            <h3 style="margin: 0 0 6px 0; font-size: 1.25rem; font-weight: 900; letter-spacing: -0.5px; color: inherit;">
                                <?php 
                                    if($verification_status == 'pending') echo 'Account Verification Pending';
                                    elseif($verification_status == 'rejected') echo 'Verification Rejected';
                                    else echo 'Account Verification Required';
                                ?>
                            </h3>
                            <p style="margin: 0; font-size: 0.95rem; font-weight: 600; opacity: 0.85; line-height: 1.5; color: inherit;">
                                <?php 
                                    if($verification_status == 'pending') echo 'Your verification files are currently under review. Selling/listing functionality is temporarily locked.';
                                    elseif($verification_status == 'rejected') echo 'Reason: ' . htmlspecialchars($verification_remarks) . '. Please update your documentation to try again.';
                                    else echo 'Please complete your profile verification to unlock crop listing and receive secure payments.';
                                ?>
                            </p>
                        </div>
                    </div>
                    <?php if($verification_status == 'unverified' || $verification_status == 'rejected'): ?>
                        <a href="verify.php" class="bento-btn" style="flex-shrink: 0; padding: 12px 24px; border-radius: 50px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-arrow-right"></i> Verify Now
                        </a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="welcome-banner">
                <div>
                    <h1>Welcome back, <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! 👋</h1>
                    <p>Here is what's happening on your farm today.</p>
                </div>
                <div class="date-badge">
                    <i class="fa-regular fa-calendar"></i> <?php echo date('l, F jS'); ?>
                </div>
            </div>

            <div class="stat-pills">
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-pill-info">
                        <h4>₹<?php echo number_format($totalRevenue); ?></h4>
                        <span>Total Revenue</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-boxes-packing"></i></div>
                    <div class="stat-pill-info">
                        <h4><?php echo $pendingOrdersCount; ?></h4>
                        <span>Orders to Ship</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fa-solid fa-seedling"></i></div>
                    <div class="stat-pill-info">
                        <h4><?php echo $totalCrops; ?></h4>
                        <span>Active Crops</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;"><i class="fa-solid fa-box-open"></i></div>
                    <div class="stat-pill-info">
                        <h4><?php echo $completedOrdersCount; ?></h4>
                        <span>Delivered</span>
                    </div>
                </div>
            </div>

            <div class="bento-master-grid">
                
                <div class="bento-card col-span-2">
                    <div class="bento-header" style="color: #f59e0b;"><i class="fa-solid fa-bolt"></i> Action Required: Shipments</div>
                    <div style="max-height: 380px; overflow-y: auto; padding-right: 10px; margin-right: -10px;">
                    <?php if($pending_orders->num_rows > 0): ?>
                        <?php while($po = $pending_orders->fetch_assoc()): ?>
                            <?php 
                                $is_delayed = false;
                                $deadline_text = "";
                                if($po['crop_status'] == 'growing') { $deadline_text = "Awaiting Harvest"; } 
                                else {
                                    $deadline = strtotime('+2 days', strtotime($po['created_at']));
                                    if(time() > $deadline) { $is_delayed = true; $deadline_text = "<span style='color: #ef4444; font-weight: 800;'><i class='fa-solid fa-circle-exclamation'></i> Delayed</span>"; } 
                                    else { $deadline_text = "<span style='color: #f59e0b; font-weight: 800;'><i class='fa-solid fa-clock'></i> Ship by: " . date('M j', $deadline) . "</span>"; }
                                }
                            ?>
                            <div class="bento-list-item" style="<?php echo $is_delayed ? 'border-left: 4px solid #ef4444;' : 'border-left: 4px solid #3b82f6;'; ?>">
                                <div>
                                    <div style="font-weight: 900; font-size: 1.15rem; margin-bottom: 6px;" class="logo-text">#ORD-<?php echo $po['order_id']; ?> &bull; <span style="text-transform: capitalize; color: var(--text-muted); font-weight:800;"><?php echo htmlspecialchars($po['crop_name']); ?> (<?php echo $po['quantity']; ?> kg)</span></div>
                                    <div style="font-size: 0.95rem; font-weight: 600;" class="text-muted"><i class="fa-solid fa-user" style="opacity: 0.5;"></i> <?php echo htmlspecialchars($po['buyer_name']); ?> &nbsp;&bull;&nbsp; <?php echo $deadline_text; ?></div>
                                </div>
                                <a href="orders.php" class="bento-btn"><i class="fa-solid fa-arrow-up-right-from-square"></i> Manage</a>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div style="padding: 50px 10px; text-align: center; color: var(--text-muted);">
                            <i class="fa-solid fa-truck-fast" style="font-size: 4rem; color: var(--border-color); margin-bottom: 20px; display:block; transition: 0.3s;"></i>
                            <div style="font-weight: 900; font-size: 1.4rem; color: var(--text-main);" class="logo-text">Queue Empty</div>
                            <div style="font-size: 1.05rem; margin-top: 8px; font-weight: 500;">You have shipped all your pending orders!</div>
                        </div>
                    <?php endif; ?>
                    </div>
                </div>

                <div class="bento-card col-span-1 weather-bento">
                    <i class="fa-solid fa-satellite-dish weather-icon-bg"></i>
                    
                    <div id="weather-loader" style="text-align: center; padding: 40px 10px; height: 100%; display: flex; flex-direction: column; justify-content: center;">
                        <i class="fa-solid fa-satellite fa-spin" style="font-size: 3rem; margin-bottom: 20px;"></i>
                        <div style="font-weight: 600; font-size: 1.1rem; letter-spacing: 0.5px;">Syncing Satellites...</div>
                    </div>

                    <div id="weather-content" style="display: none; position: relative; z-index: 1; height: 100%; flex-direction: column;">
                        
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <div>
                                <div style="font-size: 0.85rem; font-weight: 900; letter-spacing: 1px; text-transform: uppercase; opacity: 0.85;"><i class="fa-solid fa-location-crosshairs"></i> <span id="w_location">Locating...</span></div>
                                <div style="font-size: 5rem; font-weight: 900; line-height: 1; margin-top: 5px; letter-spacing: -2px;" id="w_temp">--°</div>
                                <div style="font-size: 1.2rem; font-weight: 800; text-transform: capitalize; opacity: 0.95; margin-top: 5px;" id="w_condition">--</div>
                            </div>
                            <div id="w_icon" style="transform: scale(1.4); transform-origin: top right;"></div>
                        </div>

                        <div style="flex: 1; display: flex; align-items: center; padding: 25px 0;">
                            <div style="background: rgba(0,0,0,0.2); border-radius: var(--radius-lg); padding: 22px; width: 100%; border-left: 4px solid #a7f3d0; backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px);">
                                <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; color: #a7f3d0; font-weight: 900; margin-bottom: 8px;"><i class="fa-solid fa-microchip"></i> AI Farm Insight</div>
                                <div id="w_insight" style="font-size: 1.05rem; font-weight: 600; line-height: 1.5; color: #fff; opacity: 0.95;">Analyzing atmospheric conditions...</div>
                            </div>
                        </div>

                        <div class="weather-glass-panel">
                            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px;">
                                <div style="font-size: 1rem; font-weight: 700;"><i class="fa-solid fa-droplet" style="color: #93c5fd; width: 20px;"></i> Humid: <span id="w_hum">--%</span></div>
                                <div style="font-size: 1rem; font-weight: 700;"><i class="fa-solid fa-wind" style="color: #d8b4fe; width: 20px;"></i> Wind: <span id="w_wind">--</span></div>
                            </div>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div style="font-size: 1rem; font-weight: 700;"><i class="fa-solid fa-cloud-showers-water" style="color: #a7f3d0; width: 20px;"></i> Precip: <span id="w_precip">-- mm</span></div>
                                <div style="font-size: 1rem; font-weight: 700;"><i class="fa-solid fa-temperature-arrow-up" style="color: #fca5a5; width: 20px;"></i> Feels: <span id="w_feels">--°</span></div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bento-card col-span-1">
                    <div class="bento-header"><i class="fa-solid fa-calendar-check" style="color: #3b82f6;"></i> Farm Schedule</div>
                    <div style="margin-top: 15px;">
                        <?php if($today_tasks && $today_tasks->num_rows > 0): ?>
                            <?php while($task = $today_tasks->fetch_assoc()): ?>
                                <?php 
                                    $t_style = getDashTaskStyle($task['task_type']);
                                    $is_overdue = strtotime($task['due_date']) < strtotime('today');
                                    if($is_overdue) { $t_style['color'] = '#ef4444'; $t_style['bg'] = 'rgba(239, 68, 68, 0.15)'; }
                                ?>
                                <div style="display: flex; align-items: center; gap: 18px; margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px dashed var(--border-color);">
                                    <div style="background: <?php echo $t_style['bg']; ?>; color: <?php echo $t_style['color']; ?>; width: 55px; height: 55px; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.4rem; flex-shrink: 0; transition: 0.3s;">
                                        <i class="fa-solid <?php echo $t_style['icon']; ?>"></i>
                                    </div>
                                    <div style="flex: 1; overflow: hidden;">
                                        <div style="font-weight: 900; font-size: 1.1rem; margin-bottom: 4px; white-space: nowrap; text-overflow: ellipsis; overflow: hidden;" class="logo-text">
                                            <?php echo htmlspecialchars($task['task_type']); ?>
                                        </div>
                                        <div style="font-size: 0.9rem; font-weight: 700;" class="<?php echo $is_overdue ? '' : 'text-muted'; ?>" style="<?php echo $is_overdue ? 'color: #ef4444;' : ''; ?>">
                                            <span style="text-transform: capitalize;"><?php echo htmlspecialchars($task['crop_name']); ?></span> &bull; <?php echo $is_overdue ? 'Overdue' : date('g:i A', strtotime($task['due_date'])); ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                            <div style="margin-top: 20px; text-align: center;">
                                <a href="tasks.php" class="bento-btn" style="width: 100%; border-radius: var(--radius-md);">Open Task Manager</a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted" style="text-align: center; padding: 40px 0;">
                                <div style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3;"><i class="fa-solid fa-clipboard-check"></i></div>
                                <div style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);">No tasks today</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bento-card col-span-1">
                    <div class="bento-header"><i class="fa-solid fa-stopwatch" style="color: var(--primary);"></i> Harvest Tracker</div>
                    <div style="margin-top: 15px;">
                        <?php if($upcoming_harvests && $upcoming_harvests->num_rows > 0): ?>
                            <?php while($uh = $upcoming_harvests->fetch_assoc()): ?>
                                <?php 
                                    $days = $uh['days_left'];
                                    $b_color = '#10b981'; $b_bg = 'rgba(16, 185, 129, 0.15)';
                                    if($days <= 7 && $days > 2) { $b_color = '#f59e0b'; $b_bg = 'rgba(245, 158, 11, 0.15)'; }
                                    if($days <= 2) { $b_color = '#ef4444'; $b_bg = 'rgba(239, 68, 68, 0.15)'; }
                                    $display_days = $days < 0 ? 'Due' : ($days == 0 ? 'Today' : $days . 'd');
                                ?>
                                <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 18px; padding-bottom: 18px; border-bottom: 1px dashed var(--border-color);">
                                    <div>
                                        <div style="font-weight: 900; font-size: 1.1rem; text-transform: capitalize; margin-bottom: 4px;" class="logo-text"><?php echo htmlspecialchars($uh['crop_name']); ?></div>
                                        <div style="font-size: 0.9rem; font-weight: 600;" class="text-muted"><?php echo date('M j, Y', strtotime($uh['expected_harvest'])); ?></div>
                                    </div>
                                    <div style="background: <?php echo $b_bg; ?>; color: <?php echo $b_color; ?>; padding: 12px 18px; border-radius: 20px; font-weight: 900; font-size: 1rem; border: 1px solid <?php echo $b_color; ?>40;">
                                        <?php echo $display_days; ?>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-muted" style="text-align: center; padding: 40px 0;">
                                <div style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3;"><i class="fa-solid fa-wheat-awn-circle-exclamation"></i></div>
                                <div style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);">No active crops</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bento-card col-span-1">
                    <div class="bento-header"><i class="fa-solid fa-envelope" style="color: #8b5cf6;"></i> Active Messages</div>
                    <div style="margin-top: 15px;">
                        <?php if($recent_enquiries && $recent_enquiries->num_rows > 0): ?>
                            <div style="display: flex; flex-direction: column; gap: 12px;">
                            <?php while($enq = $recent_enquiries->fetch_assoc()): ?>
                                <a href="enquiries.php?id=<?php echo $enq['enquiry_id']; ?>" style="display: flex; gap: 12px; align-items: stretch; text-decoration: none; padding: 16px; background: var(--bg-light); border-radius: var(--radius-md); border: 1px solid var(--border-color); transition: 0.3s;" class="bento-list-item">
                                    <div style="width: 45px; height: 45px; border-radius: 50%; background: rgba(139, 92, 246, 0.15); color: #8b5cf6; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.2rem; flex-shrink: 0;">
                                        <?php echo strtoupper(substr($enq['buyer_name'], 0, 1)); ?>
                                    </div>
                                    <div style="flex: 1; overflow: hidden; display: flex; flex-direction: column; justify-content: center;">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px;">
                                            <strong class="logo-text" style="font-size: 1.05rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($enq['buyer_name']); ?></strong>
                                            <?php if($enq['unread_count'] > 0): ?>
                                                <span style="background: #ef4444; color: #fff; font-size: 0.7rem; font-weight: 900; padding: 2px 8px; border-radius: 20px;">New</span>
                                            <?php endif; ?>
                                        </div>
                                        <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                            <?php echo htmlspecialchars($enq['last_msg'] ?: 'No messages yet'); ?>
                                        </div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                            </div>
                            <div style="margin-top: 15px; text-align: center;">
                                <a href="enquiries.php" class="bento-btn" style="width: 100%; border-radius: var(--radius-md);">View All Enquiries</a>
                            </div>
                        <?php else: ?>
                            <div class="text-muted" style="text-align: center; padding: 40px 0;">
                                <div style="font-size: 3.5rem; margin-bottom: 15px; opacity: 0.3;"><i class="fa-solid fa-comment-slash"></i></div>
                                <div style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);">No active chats</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bento-card col-span-3">
                    <div class="bento-header"><i class="fa-solid fa-star" style="color: #f59e0b;"></i> Customer Feedback & Ratings</div>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 24px; margin-top: 15px;">
                        <?php if($reviews && $reviews->num_rows > 0): ?>
                            <?php while($rev = $reviews->fetch_assoc()): ?>
                                <div style="border: 1px solid var(--border-color); padding: 25px; border-radius: var(--radius-lg); background: var(--bg-light); transition: 0.3s;">
                                    <div style="display: flex; justify-content: space-between; margin-bottom: 12px; align-items: center;">
                                        <strong style="text-transform: capitalize; font-size: 1.15rem; font-weight: 900;" class="logo-text"><?php echo htmlspecialchars($rev['buyer_name']); ?></strong>
                                        <span style="font-size: 0.85rem; font-weight: 700;" class="text-muted"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></span>
                                    </div>
                                    <div style="color: #f59e0b; font-size: 1.1rem; margin-bottom: 15px;">
                                        <?php for($i=1; $i<=5; $i++) { echo $i <= $rev['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; } ?>
                                    </div>
                                    <p style="font-size: 1.05rem; margin: 0; font-style: italic; font-weight: 500; line-height: 1.6;" class="text-muted">"<?php echo htmlspecialchars($rev['comment']); ?>"</p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="text-muted" style="font-size: 1.1rem; grid-column: span 3; text-align: center; padding: 50px; font-weight: 600; background: var(--bg-light); border-radius: var(--radius-xl); border: 2px dashed var(--border-color);">You don't have any reviews yet. Fulfill orders to start receiving feedback!</div>
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

<script>
const W_API_KEY = ''; // Set this in your local environment; do not hardcode API keys. 
const registeredFarmLocation = "<?php echo addslashes($farmer_city); ?>";

async function pullWeatherData(locationQuery) {
    try {
        document.getElementById('weather-content').style.display = 'none';
        document.getElementById('weather-loader').style.display = 'block';

        let url = `https://api.weatherapi.com/v1/current.json?key=${W_API_KEY}&q=${encodeURIComponent(locationQuery)}&aqi=no`;
        let response = await fetch(url);
        let data = await response.json();

        if(data.error) {
            url = `https://api.weatherapi.com/v1/current.json?key=${W_API_KEY}&q=Bengaluru&aqi=no`;
            response = await fetch(url);
            data = await response.json();
        }
        if(data.error) throw new Error("API Error");

        // Format location directly from the API response to get the exact matching region
        let locationDisplay = data.location.name;
        document.getElementById('w_location').innerHTML = locationDisplay;
        
        document.getElementById('w_temp').innerText = Math.round(data.current.temp_c) + "°";
        document.getElementById('w_condition').innerText = data.current.condition.text;
        document.getElementById('w_hum').innerText = data.current.humidity + "%";
        document.getElementById('w_wind').innerText = data.current.wind_kph + " km/h";
        document.getElementById('w_precip').innerText = data.current.precip_mm + " mm";
        document.getElementById('w_feels').innerText = Math.round(data.current.feelslike_c) + "°";
        document.getElementById('w_icon').innerHTML = `<img src="https:${data.current.condition.icon}" style="width: 140px; filter: drop-shadow(0px 15px 25px rgba(0,0,0,0.5));">`;
        
        // Dynamic AI Farm Insight Logic
        let insight = "Atmospheric conditions are optimal for general farming and harvesting operations today.";
        if(data.current.precip_mm > 0.5) {
            insight = "Rain detected in your area. Excellent for soil moisture, but delay any pesticide spraying until it clears.";
        } else if(data.current.wind_kph > 20) {
            insight = "High wind speeds detected. Secure any delicate crops and avoid chemical drift spraying.";
        } else if(data.current.temp_c > 35) {
            insight = "Extreme heat warning. Monitor crop hydration closely and increase irrigation to prevent heat stress.";
        } else if(data.current.humidity > 85) {
            insight = "High humidity detected. Watch out for fungal diseases; ensure adequate spacing for air circulation.";
        }
        document.getElementById('w_insight').innerText = insight;

        document.getElementById('weather-loader').style.display = 'none';
        document.getElementById('weather-content').style.display = 'flex';
    } catch(error) {
        document.getElementById('weather-loader').innerHTML = "Meteorological network error.";
    }
}
pullWeatherData(registeredFarmLocation);
</script>

<script src="../assets/js/translate.js"></script>
<script src="https://cdn.onesignal.com/sdks/web/v16/OneSignalSDK.page.js" defer></script>
<script>
  window.OneSignalDeferred = window.OneSignalDeferred || [];
  OneSignalDeferred.push(async function(OneSignal) {
    await OneSignal.init({
      appId: "18b169e4-c3f3-4002-81fc-753932dbdbe7", 
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

</body>
</html>
