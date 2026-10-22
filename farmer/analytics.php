<?php
session_start();
include("../config/db.php");

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
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

/* =========================================
   1. FETCH TOP LEVEL STATS
========================================= */
$stats_query = $conn->query("SELECT COUNT(id) as total_crops, SUM(investment) as total_investment FROM crops WHERE farmer_id = $farmer_id");
$stats = $stats_query->fetch_assoc();

$rev_query = $conn->query("
    SELECT SUM(order_items.quantity * order_items.price) AS total_revenue 
    FROM order_items 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN orders ON order_items.order_id = orders.id 
    WHERE crops.farmer_id = $farmer_id AND orders.order_status != 'cancelled'
");
$revenue = $rev_query->fetch_assoc()['total_revenue'] ?? 0;
$profit = $revenue - ($stats['total_investment'] ?? 0);

/* =========================================
   2. MONTHLY REVENUE CHART DATA
========================================= */
$monthly = $conn->query("
    SELECT DATE_FORMAT(orders.created_at, '%b') AS month, SUM(order_items.quantity * order_items.price) AS revenue
    FROM order_items 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN orders ON order_items.order_id = orders.id
    WHERE crops.farmer_id = $farmer_id AND orders.order_status != 'cancelled'
    GROUP BY MONTH(orders.created_at) 
    ORDER BY MONTH(orders.created_at)
");
$months = []; $revenues = [];
while($row = $monthly->fetch_assoc()){ 
    $months[] = $row['month']; 
    $revenues[] = $row['revenue']; 
}

/* =========================================
   3. CROP YIELD DISTRIBUTION (Doughnut)
========================================= */
$yield_query = $conn->query("SELECT crop_name, SUM(COALESCE(actual_yield, predicted_yield, 0)) as total_yield FROM crops WHERE farmer_id = $farmer_id GROUP BY crop_name");
$crop_names = []; $crop_yields = [];
while($row = $yield_query->fetch_assoc()){
    $crop_names[] = ucfirst($row['crop_name']);
    $crop_yields[] = $row['total_yield'] > 0 ? $row['total_yield'] : 0;
}

/* =========================================
   4. ORDER STATUS BREAKDOWN (Pie)
========================================= */
$status_query = $conn->query("
    SELECT orders.order_status, COUNT(DISTINCT orders.id) as count 
    FROM orders 
    JOIN order_items ON orders.id = order_items.order_id 
    JOIN crops ON order_items.crop_id = crops.id 
    WHERE crops.farmer_id = $farmer_id 
    GROUP BY orders.order_status
");
$statuses = []; $status_counts = [];
while($row = $status_query->fetch_assoc()){
    $statuses[] = ucfirst($row['order_status']);
    $status_counts[] = $row['count'];
}

/* =========================================
   5. INVESTMENT VS REVENUE PER CROP
========================================= */
$roi_query = $conn->query("
    SELECT crops.crop_name, SUM(crops.investment) as total_investment, 
    IFNULL(SUM(order_items.quantity * order_items.price), 0) as crop_revenue
    FROM crops 
    LEFT JOIN order_items ON crops.id = order_items.crop_id
    LEFT JOIN orders ON order_items.order_id = orders.id AND orders.order_status != 'cancelled'
    WHERE crops.farmer_id = $farmer_id 
    GROUP BY crops.crop_name
");
$roi_labels = []; $investments = []; $returns = [];
while($row = $roi_query->fetch_assoc()){
    $roi_labels[] = ucfirst($row['crop_name']);
    $investments[] = $row['total_investment'];
    $returns[] = $row['crop_revenue'];
}

/* =========================================
   6. SALES VOLUME BY CROP (Bar)
========================================= */
$volume_query = $conn->query("
    SELECT crops.crop_name, IFNULL(SUM(order_items.quantity), 0) as total_volume
    FROM crops
    LEFT JOIN order_items ON crops.id = order_items.crop_id
    LEFT JOIN orders ON order_items.order_id = orders.id AND orders.order_status != 'cancelled'
    WHERE crops.farmer_id = $farmer_id
    GROUP BY crops.crop_name
    ORDER BY total_volume DESC
");
$vol_crops = []; $vol_data = [];
while($row = $volume_query->fetch_assoc()){
    $vol_crops[] = ucfirst($row['crop_name']);
    $vol_data[] = $row['total_volume'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Advanced Analytics | Farmogram</title>
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

        .content-container { padding: 30px 40px; max-width: 1400px; margin: 0 auto; }

        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        .stat-pills { display: grid; grid-template-columns: repeat(4, 1fr); gap: 24px; margin-bottom: 40px; }
        .stat-pill { background: var(--card-bg); border-radius: var(--radius-xl); padding: 25px; display: flex; align-items: center; gap: 20px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); transition: 0.4s; }
        .stat-pill:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        .stat-icon-wrapper { width: 65px; height: 65px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        .stat-pill-info h4 { font-size: 2rem; font-weight: 900; color: var(--text-main); line-height: 1.1; margin-bottom: 6px; transition: 0.3s; letter-spacing: -1px; }
        .stat-pill-info span { font-size: 0.85rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; }

        .profit-positive { color: #10b981 !important; }
        .profit-negative { color: #ef4444 !important; }

        /* THE NEW UN-CONGESTED STRUCTURE */
        .chart-section { width: 100%; margin-bottom: 35px; }
        .split-row { display: grid; grid-template-columns: 1fr 1fr; gap: 35px; width: 100%; margin-bottom: 35px; }

        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); padding: 35px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; width: 100%; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }
        .bento-header { font-size: 1.35rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; gap: 12px; transition: 0.3s; letter-spacing: -0.5px; }
        
        .chart-wrapper { position: relative; width: 100%; }
        .chart-tall { height: 420px; }
        .chart-medium { height: 350px; }

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

        /* DARK MODE OVERRIDES */
        [data-theme="dark"] { --bg-light: #0f172a !important; --sidebar-bg: #1e293b !important; --text-main: #f8fafc !important; --text-muted: #94a3b8 !important; --border-color: rgba(255,255,255,0.05) !important; --card-bg: #1e293b !important; }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.85) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-card, [data-theme="dark"] .stat-pill { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .bento-header, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }
        
        body, .sidebar, .topbar, .bento-card, .stat-pill, h1, h2, h3, h4, p, span, div { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        @media (max-width: 1024px) {
            .stat-pills { grid-template-columns: 1fr 1fr; }
            .split-row { grid-template-columns: 1fr; }
        }

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
            
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; transition: 0.3s; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }

            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); overflow-y: auto; overflow-x: hidden; padding-bottom: 25px; }
            .content-container { padding: 25px 20px; }
            
            .stat-pills { grid-template-columns: 1fr; gap: 15px; }
            .bento-card { padding: 20px; border-radius: var(--radius-lg); }
            .stat-pill { padding: 20px; border-radius: var(--radius-lg); }
            .chart-tall, .chart-medium { height: 350px; }
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
            <li><a href="tasks.php"><i class="fa-solid fa-calendar-check"></i> <span>Tasks</span></a></li> 
            <li><a href="orders.php"><i class="fa-solid fa-cart-flatbed"></i> <span>Orders</span></a></li>
            <li><a href="enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
            <li><a href="analytics.php" class="active"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Farm Business Analytics</div>
            
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
            
            <div class="stat-pills">
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b;"><i class="fa-solid fa-seedling"></i></div>
                    <div class="stat-pill-info">
                        <h4>₹<?php echo number_format($stats['total_investment'] ?? 0); ?></h4>
                        <span>Total Invested</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;"><i class="fa-solid fa-wallet"></i></div>
                    <div class="stat-pill-info">
                        <h4>₹<?php echo number_format($revenue); ?></h4>
                        <span>Gross Revenue</span>
                    </div>
                </div>
                <div class="stat-pill" style="border-left: 4px solid <?php echo $profit >= 0 ? '#10b981' : '#ef4444'; ?>;">
                    <div class="stat-icon-wrapper" style="background: <?php echo $profit >= 0 ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)'; ?>; color: <?php echo $profit >= 0 ? '#10b981' : '#ef4444'; ?>;">
                        <i class="fa-solid <?php echo $profit >= 0 ? 'fa-arrow-trend-up' : 'fa-arrow-trend-down'; ?>"></i>
                    </div>
                    <div class="stat-pill-info">
                        <h4 class="<?php echo $profit >= 0 ? 'profit-positive' : 'profit-negative'; ?>">₹<?php echo number_format($profit); ?></h4>
                        <span>Net Profit/Loss</span>
                    </div>
                </div>
                <div class="stat-pill">
                    <div class="stat-icon-wrapper" style="background: rgba(139, 92, 246, 0.15); color: #8b5cf6;"><i class="fa-solid fa-boxes-stacked"></i></div>
                    <div class="stat-pill-info">
                        <h4><?php echo $stats['total_crops'] ?? 0; ?></h4>
                        <span>Active Crops</span>
                    </div>
                </div>
            </div>

            <div class="chart-section bento-card">
                <div class="bento-header">
                    <i class="fa-solid fa-chart-line" style="color: #10b981;"></i> Monthly Revenue Growth (₹)
                </div>
                <div class="chart-wrapper chart-medium">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>

            <div class="chart-section bento-card">
                <div class="bento-header">
                    <i class="fa-solid fa-scale-balanced" style="color: #8b5cf6;"></i> Investment vs. Gross Return by Crop (₹)
                </div>
                <div class="chart-wrapper chart-tall">
                    <canvas id="roiChart"></canvas>
                </div>
            </div>

            <div class="chart-section bento-card">
                <div class="bento-header">
                    <i class="fa-solid fa-truck-fast" style="color: #3b82f6;"></i> Total Sales Volume by Crop (kg)
                </div>
                <div class="chart-wrapper chart-tall">
                    <canvas id="salesVolumeChart"></canvas>
                </div>
            </div>

            <div class="split-row">
                <div class="bento-card" style="align-items: center;">
                    <div class="bento-header" style="justify-content: center; width: 100%;">
                        <i class="fa-solid fa-chart-pie" style="color: #f59e0b;"></i> Yield Distribution (kg)
                    </div>
                    <div class="chart-wrapper chart-medium" style="max-width: 500px;">
                        <canvas id="yieldChart"></canvas>
                    </div>
                </div>

                <div class="bento-card" style="align-items: center;">
                    <div class="bento-header" style="justify-content: center; width: 100%;">
                        <i class="fa-solid fa-box-open" style="color: #ef4444;"></i> Order Status Breakdown
                    </div>
                    <div class="chart-wrapper chart-medium" style="max-width: 500px;">
                        <canvas id="statusChart"></canvas>
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
            });
        }
    });
</script>

<script>
// Global Chart Settings
Chart.defaults.font.family = "'Inter', sans-serif";
Chart.defaults.color = "#94a3b8"; 
Chart.defaults.plugins.tooltip.padding = 12;
Chart.defaults.plugins.tooltip.cornerRadius = 12;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(15, 23, 42, 0.95)';
Chart.defaults.plugins.tooltip.titleFont = { size: 14, weight: 'bold' };
Chart.defaults.plugins.tooltip.bodyFont = { size: 13 };

const gridColor = 'rgba(148, 163, 184, 0.15)'; 

// 1. Monthly Revenue Chart (Line Chart with Gradient Fill)
const ctxRev = document.getElementById('revenueChart').getContext('2d');
let gradient = ctxRev.createLinearGradient(0, 0, 0, 400);
gradient.addColorStop(0, 'rgba(16, 185, 129, 0.6)'); 
gradient.addColorStop(1, 'rgba(16, 185, 129, 0.0)');

new Chart(ctxRev, {
    type: 'line',
    data: {
        labels: <?php echo json_encode($months); ?>,
        datasets: [{
            label: 'Revenue (₹)',
            data: <?php echo json_encode($revenues); ?>,
            borderColor: '#10b981',
            backgroundColor: gradient,
            borderWidth: 4,
            tension: 0.4, 
            fill: true,
            pointBackgroundColor: '#10b981',
            pointBorderColor: '#fff',
            pointBorderWidth: 2,
            pointRadius: 6,
            pointHoverRadius: 9
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, border: { display: false } },
            x: { grid: { display: false, drawBorder: false }, border: { display: false } }
        }
    }
});

// 2. Investment vs Return (Vertical Bar)
new Chart(document.getElementById('roiChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($roi_labels); ?>,
        datasets: [
            {
                label: 'Total Investment (₹)',
                data: <?php echo json_encode($investments); ?>,
                backgroundColor: 'rgba(239, 68, 68, 0.85)',
                hoverBackgroundColor: 'rgba(239, 68, 68, 1)',
                borderRadius: 6,
                maxBarThickness: 50
            },
            {
                label: 'Gross Return (₹)',
                data: <?php echo json_encode($returns); ?>,
                backgroundColor: 'rgba(139, 92, 246, 0.85)',
                hoverBackgroundColor: 'rgba(139, 92, 246, 1)',
                borderRadius: 6,
                maxBarThickness: 50
            }
        ]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { position: 'top', labels: { usePointStyle: true, padding: 20, font: { weight: '800' } } } },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor }, border: { display: false } },
            x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 12 } } }
        }
    }
});

// 3. Sales Volume by Crop (Vertical Bar)
new Chart(document.getElementById('salesVolumeChart'), {
    type: 'bar',
    data: {
        labels: <?php echo json_encode($vol_crops); ?>,
        datasets: [{
            label: 'Total Quantity Sold (kg)',
            data: <?php echo json_encode($vol_data); ?>,
            backgroundColor: 'rgba(59, 130, 246, 0.85)',
            hoverBackgroundColor: 'rgba(59, 130, 246, 1)',
            borderRadius: 6, 
            maxBarThickness: 60
        }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: gridColor, drawBorder: false }, border: { display: false } },
            x: { grid: { display: false, drawBorder: false }, border: { display: false }, ticks: { font: { size: 12 } } }
        }
    }
});

// 4. Crop Yield Distribution (Thin Doughnut)
new Chart(document.getElementById('yieldChart'), {
    type: 'doughnut',
    data: {
        labels: <?php echo json_encode($crop_names); ?>,
        datasets: [{
            data: <?php echo json_encode($crop_yields); ?>,
            backgroundColor: ['#10b981', '#f59e0b', '#3b82f6', '#8b5cf6', '#ef4444', '#14b8a6', '#6366f1', '#ec4899', '#f97316'],
            borderWidth: 2,
            borderColor: 'transparent',
            hoverOffset: 12
        }]
    },
    options: {
        responsive: true, 
        maintainAspectRatio: false,
        cutout: '65%', 
        layout: { padding: 10 },
        plugins: {
            legend: { 
                position: 'bottom', 
                labels: { usePointStyle: true, padding: 20, font: { weight: '800' } } 
            }
        }
    }
});

// 5. Order Status Breakdown (Pie)
new Chart(document.getElementById('statusChart'), {
    type: 'pie',
    data: {
        labels: <?php echo json_encode($statuses); ?>,
        datasets: [{
            data: <?php echo json_encode($status_counts); ?>,
            backgroundColor: ['#3b82f6', '#f59e0b', '#10b981', '#ef4444'], 
            borderWidth: 2,
            borderColor: 'transparent',
            hoverOffset: 12
        }]
    },
    options: {
        responsive: true, 
        maintainAspectRatio: false,
        layout: { padding: 10 },
        plugins: {
            legend: { 
                position: 'bottom', 
                labels: { usePointStyle: true, padding: 20, font: { weight: '800' } } 
            }
        }
    }
});
</script>

<script src="../assets/js/translate.js"></script>

</body>
</html>
