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

/* =========================================
   1. FETCH GLOBAL KPIs
========================================= */
$total_gmv = $conn->query("SELECT SUM(total_amount) AS total FROM orders WHERE order_status != 'cancelled'")->fetch_assoc()['total'] ?? 0;
$total_orders = $conn->query("SELECT COUNT(id) AS count FROM orders")->fetch_assoc()['count'] ?? 0;
$total_users = $conn->query("SELECT COUNT(id) AS count FROM users WHERE role != 'admin'")->fetch_assoc()['count'] ?? 0;
$total_crops = $conn->query("SELECT COUNT(id) AS count FROM crops")->fetch_assoc()['count'] ?? 0;

/* =========================================
   2. MONTHLY GMV TREND (Line Chart)
========================================= */
$monthly_q = $conn->query("
    SELECT DATE_FORMAT(created_at, '%b') AS month, SUM(total_amount) AS revenue 
    FROM orders 
    WHERE order_status != 'cancelled' 
    GROUP BY MONTH(created_at) 
    ORDER BY MONTH(created_at)
");
$months = []; $gmv_data = [];
while($row = $monthly_q->fetch_assoc()){ 
    $months[] = $row['month']; 
    $gmv_data[] = $row['revenue']; 
}

/* =========================================
   3. GLOBAL ORDER STATUS (Doughnut Chart)
========================================= */
$status_q = $conn->query("SELECT order_status, COUNT(id) as count FROM orders GROUP BY order_status");
$statuses = []; $status_counts = [];
while($row = $status_q->fetch_assoc()){
    $statuses[] = ucfirst($row['order_status']);
    $status_counts[] = $row['count'];
}

/* =========================================
   4. TOP PERFORMING FARMERS (Bar Chart)
========================================= */
$top_farmers_q = $conn->query("
    SELECT users.name, SUM(order_items.quantity * order_items.price) as revenue
    FROM order_items
    JOIN crops ON order_items.crop_id = crops.id
    JOIN orders ON order_items.order_id = orders.id
    JOIN users ON crops.farmer_id = users.id
    WHERE orders.order_status != 'cancelled'
    GROUP BY users.id
    ORDER BY revenue DESC
    LIMIT 5
");
$farmer_names = []; $farmer_revenues = [];
while($row = $top_farmers_q->fetch_assoc()){
    $farmer_names[] = ucfirst($row['name']);
    $farmer_revenues[] = $row['revenue'];
}

/* =========================================
   5. MOST POPULAR CROPS BY VOLUME (Bar Chart)
========================================= */
$top_crops_q = $conn->query("
    SELECT crops.crop_name, SUM(order_items.quantity) as volume
    FROM order_items
    JOIN crops ON order_items.crop_id = crops.id
    JOIN orders ON order_items.order_id = orders.id
    WHERE orders.order_status != 'cancelled'
    GROUP BY crops.crop_name
    ORDER BY volume DESC
    LIMIT 5
");
$crop_names = []; $crop_volumes = [];
while($row = $top_crops_q->fetch_assoc()){
    $crop_names[] = ucfirst($row['crop_name']);
    $crop_volumes[] = $row['volume'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Global Analytics | Admin | Farmogram</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
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

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* KPI Banner */
        .summary-banner { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 35px; }
        .bento-kpi { background: var(--card-bg); padding: 30px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-subtle); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .bento-kpi:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .icon-wrapper { width: 70px; height: 70px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 2rem; flex-shrink: 0; transition: 0.3s; }
        .summary-info .val { font-size: 2.2rem; font-weight: 900; color: var(--text-main); margin-bottom: 5px; line-height: 1; letter-spacing: -0.5px; transition: 0.3s; }
        .summary-info .lbl { font-size: 0.85rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s; }
        
        /* Charts Grid */
        .analytics-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 30px; padding-bottom: 30px; }
        .bento-chart { background: var(--card-bg); padding: 35px; border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .bento-chart:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .chart-header { font-size: 1.3rem; font-weight: 900; color: var(--text-main); margin-bottom: 30px; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; transition: 0.3s; }
        .chart-container { position: relative; height: 350px; width: 100%; flex: 1; }

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
        [data-theme="dark"] .bento-kpi, [data-theme="dark"] .bento-chart { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .val, [data-theme="dark"] .chart-header, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .lbl { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-kpi, .bento-chart, h1, h2, h3, h4, p, span, div { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1200px) {
            .summary-banner { grid-template-columns: repeat(2, 1fr); }
            .analytics-grid { grid-template-columns: 1fr; }
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
            
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; margin-right: 0 !important; }

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
            
            .summary-banner { grid-template-columns: 1fr; gap: 15px; margin-bottom: 20px; }
            .bento-kpi { padding: 20px; border-radius: var(--radius-lg); }
            .icon-wrapper { width: 60px; height: 60px; font-size: 1.5rem; }
            .val { font-size: 1.8rem !important; }
            
            .analytics-grid { gap: 20px; padding-bottom: 20px; }
            .bento-chart { padding: 20px; border-radius: var(--radius-lg); }
            .chart-header { font-size: 1.15rem; margin-bottom: 20px; }
            .chart-container { height: 280px; }
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
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> <span>Overview</span></a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> <span>Manage Users</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-boxes-stacked"></i> <span>All Orders</span></a></li>
            <li><a href="analytics.php" class="active"><i class="fa-solid fa-chart-line"></i> <span>Analytics</span></a></li>
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

            <div class="page-title logo-text" style="font-size: 1.6rem;">Global Analytics</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>

                <button class="theme-btn" id="themeToggle" title="Toggle Theme" style="margin-right: 10px;">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <div class="user-profile" style="border-left: none; padding-left: 0; display: flex; align-items: center;">
                    <div style="text-align: right; margin-right: 10px;" class="desktop-only-text">
                        <div style="font-weight: 800; font-size: 0.95rem;" class="logo-text"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">System Admin</div>
                    </div>
                    <div class="avatar" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); font-weight: 900; font-size: 1.1rem; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0;"><?php echo $avatar_letter; ?></div>
                </div>
            </div>
        </header>

        <div class="content-container">
            
            <div class="summary-banner">
                <div class="bento-kpi">
                    <div class="icon-wrapper" style="background: rgba(16, 185, 129, 0.15); color: #10b981;"><i class="fa-solid fa-money-bill-trend-up"></i></div>
                    <div class="summary-info">
                        <div class="val">₹ <?php echo number_format($total_gmv); ?></div>
                        <div class="lbl">Global GMV</div>
                    </div>
                </div>
                <div class="bento-kpi">
                    <div class="icon-wrapper" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fa-solid fa-box-open"></i></div>
                    <div class="summary-info">
                        <div class="val"><?php echo number_format($total_orders); ?></div>
                        <div class="lbl">Total Orders</div>
                    </div>
                </div>
                <div class="bento-kpi">
                    <div class="icon-wrapper" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;"><i class="fa-solid fa-users"></i></div>
                    <div class="summary-info">
                        <div class="val"><?php echo number_format($total_users); ?></div>
                        <div class="lbl">Registered Users</div>
                    </div>
                </div>
                <div class="bento-kpi">
                    <div class="icon-wrapper" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-seedling"></i></div>
                    <div class="summary-info">
                        <div class="val"><?php echo number_format($total_crops); ?></div>
                        <div class="lbl">Crops Listed</div>
                    </div>
                </div>
            </div>

            <div class="analytics-grid">
                
                <div class="bento-chart">
                    <div class="chart-header">
                        <i class="fa-solid fa-money-bill-trend-up" style="color: #10b981;"></i> GMV Growth Trend
                    </div>
                    <div class="chart-container">
                        <canvas id="gmvChart"></canvas>
                    </div>
                </div>

                <div class="bento-chart">
                    <div class="chart-header">
                        <i class="fa-solid fa-truck-fast" style="color: #3b82f6;"></i> Global Fulfillment Health
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>

                <div class="bento-chart">
                    <div class="chart-header">
                        <i class="fa-solid fa-medal" style="color: #f59e0b;"></i> Top Performing Farmers
                    </div>
                    <div class="chart-container">
                        <canvas id="farmersChart"></canvas>
                    </div>
                </div>

                <div class="bento-chart">
                    <div class="chart-header">
                        <i class="fa-solid fa-basket-shopping" style="color: #8b5cf6;"></i> Most Popular Crops (Volume)
                    </div>
                    <div class="chart-container">
                        <canvas id="cropsChart"></canvas>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
// Chart Defaults optimized for both Light & Dark themes
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = "#94a3b8"; 
const gridColor = "rgba(148, 163, 184, 0.15)"; // Neutral translucent grid

Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 12;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';

// 1. GMV Chart (Smooth Line with Gradient Fill)
const ctxGMV = document.getElementById('gmvChart').getContext('2d');
let gradient = ctxGMV.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(16, 185, 129, 0.5)'); 
gradient.addColorStop(1, 'rgba(16, 185, 129, 0)');

new Chart(ctxGMV, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'GMV (₹)',
            data: <?php echo json_encode($gmv_data); ?>,
            borderColor: '#10b981',
            backgroundColor: gradient,
            borderWidth: 4,
            tension: 0.4, 
            fill: true,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 5,
            pointHoverRadius: 8
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false } }
        }
    }
});

// 2. Order Status (Doughnut)
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($statuses); ?>,
        datasets: [{
            data: <?php echo json_encode($status_counts); ?>,
            backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444'], 
            borderWidth: 0,
            hoverOffset: 10
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        cutout: '75%',
        plugins: { legend: { position: 'right', labels: { usePointStyle: true, padding: 25, font: { weight: '800' } } } }
    }
});

// 3. Top Farmers (Bar)
new Chart(document.getElementById('farmersChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($farmer_names); ?>,
        datasets: [{
            label: 'Total Sales (₹)',
            data: <?php echo json_encode($farmer_revenues); ?>,
            backgroundColor: 'rgba(245, 158, 11, 0.85)',
            hoverBackgroundColor: '#f59e0b',
            borderRadius: 8,
            barPercentage: 0.5
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false } }
        }
    }
});

// 4. Popular Crops (Horizontal Bar)
new Chart(document.getElementById('cropsChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($crop_names); ?>,
        datasets: [{
            label: 'Volume Sold (kg)',
            data: <?php echo json_encode($crop_volumes); ?>,
            backgroundColor: 'rgba(139, 92, 246, 0.85)',
            hoverBackgroundColor: '#8b5cf6',
            borderRadius: 8,
            barPercentage: 0.6
        }]
    },
    options: {
        indexAxis: 'y', 
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: { beginAtZero: true, grid: { color: gridColor }, border: { display: false } },
            y: { grid: { display: false }, border: { display: false } }
        }
    }
});

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

<script src="../assets/js/translate.js"></script>

<style>
/* Utility class to hide the full user name text on mobile to ensure topbar icons have space */
@media (max-width: 768px) {
    .desktop-only-text { display: none; }
}
</style>

</body>
</html>