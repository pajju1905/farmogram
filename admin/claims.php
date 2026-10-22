<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Admin';
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$msg = "";

/* =========================================
   HANDLE MODAL SUBMISSION (APPROVE/REJECT)
========================================= */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['claim_id']) && isset($_POST['action_type'])){
    $claim_id = (int)$_POST['claim_id'];
    $admin_remarks = trim($_POST['admin_remarks']);
    $action_type = $_POST['action_type']; // 'Approve' or 'Reject'
    
    $new_status = ($action_type == 'Approve') ? 'Approved' : 'Rejected';

    $stmt = $conn->prepare("UPDATE crop_claims SET status = ?, admin_remarks = ? WHERE id = ?");
    $stmt->bind_param("ssi", $new_status, $admin_remarks, $claim_id);
    
    if($stmt->execute()) {
        if($new_status == 'Approved') {
            $msg = "<div class='alert-msg success'><i class='fa-solid fa-check-circle'></i> Claim #CLM-$claim_id Approved successfully.</div>";
        } else {
            $msg = "<div class='alert-msg error'><i class='fa-solid fa-ban'></i> Claim #CLM-$claim_id Rejected.</div>";
        }
    } else {
        $msg = "<div class='alert-msg error'><i class='fa-solid fa-triangle-exclamation'></i> Database Error: Could not update status.</div>";
    }
    $stmt->close();
}

// Fetch claims, prioritizing 'Pending Review' first
$claims_query = $conn->query("SELECT c.*, u.name AS farmer_name FROM crop_claims c JOIN users u ON c.farmer_id = u.id ORDER BY CASE WHEN c.status = 'Pending Review' THEN 1 ELSE 2 END, c.created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Disaster Claims Management | Admin | Farmogram</title>
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

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Alerts */
        .alert-msg { padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 25px; font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .alert-msg.success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-msg.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        /* Bento Card Wrapper */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        /* Premium Data Tables */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .data-table { width: 100%; border-collapse: collapse; text-align: left; }
        .data-table th { background: var(--bg-light); padding: 20px 35px; color: var(--text-muted); font-size: 0.85rem; text-transform: uppercase; border-bottom: 2px dashed var(--border-color); font-weight: 900; letter-spacing: 0.5px; white-space: nowrap; transition: 0.3s; }
        .data-table td { padding: 25px 35px; border-bottom: 1px dashed var(--border-color); vertical-align: middle; color: var(--text-main); transition: 0.3s; white-space: nowrap; font-size: 0.95rem; font-weight: 500; }
        .data-table tr:hover td { background: var(--bg-light); }
        .data-table tr:last-child td { border-bottom: none; }
        
        .status-badge { padding: 8px 16px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); display: inline-block; text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge.Approved { background: rgba(16, 185, 129, 0.15); color: #10b981; border-color: rgba(16, 185, 129, 0.3); }
        .status-badge.Rejected { background: rgba(239, 68, 68, 0.15); color: #ef4444; border-color: rgba(239, 68, 68, 0.3); }

        .btn-view { display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: #fff; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-size: 0.95rem; font-weight: 800; cursor: pointer; border: none; transition: 0.3s; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); font-family: inherit; }
        .btn-view:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3); }

        /* PREMIUM MODAL STYLES */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-content { background: var(--card-bg); width: 100%; max-width: 950px; border-radius: var(--radius-xl); padding: 40px; position: relative; max-height: 90vh; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: translateY(20px); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .modal-overlay.show .modal-content { transform: translateY(0); }
        
        .modal-close { position: absolute; top: 25px; right: 25px; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); transition: 0.3s; background: var(--bg-light); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); }
        .modal-close:hover { background: #ef4444; color: #fff; border-color: #ef4444; transform: rotate(90deg); }
        
        .modal-header-title { margin: 0 0 30px 0; border-bottom: 2px dashed var(--border-color); padding-bottom: 20px; font-size: 1.8rem; font-weight: 900; color: var(--text-main); display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; transition: 0.3s; }
        
        .case-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .case-section { background: var(--bg-light); padding: 30px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); transition: 0.3s; }
        .case-section h3 { margin: 0 0 20px 0; font-size: 1.3rem; color: var(--text-main); font-weight: 900; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        
        .case-label { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 5px; transition: 0.3s; letter-spacing: 0.5px; }
        .case-val { font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 20px; transition: 0.3s; }
        
        .evidence-img { width: 100%; height: 220px; object-fit: cover; border-radius: var(--radius-md); border: 1px solid var(--border-color); cursor: pointer; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .evidence-img:hover { transform: scale(1.02); border-color: var(--primary); }
        
        .action-box { background: var(--card-bg); padding: 30px; border: 2px dashed var(--border-color); border-radius: var(--radius-lg); margin-top: 30px; transition: 0.3s; }
        .action-textarea { width: 100%; padding: 18px; border: 2px solid var(--border-color); border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 600; resize: vertical; margin-bottom: 20px; background: var(--bg-light); color: var(--text-main); transition: 0.3s; outline: none; }
        .action-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        
        .action-btn { padding: 18px 25px; border: none; border-radius: var(--radius-md); font-weight: 900; cursor: pointer; color: #fff; font-size: 1.1rem; flex: 1; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.2); }
        
        .processed-alert { display: none; text-align: center; padding: 30px; background: var(--bg-light); border-radius: var(--radius-lg); font-weight: 800; color: var(--text-muted); border: 1px solid var(--border-color); transition: 0.3s; font-size: 1.1rem; margin-top: 20px; }

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
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .data-table th { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .data-table tr:hover td { background: var(--bg-light) !important; }
        
        [data-theme="dark"] .modal-content { background-color: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .case-section, [data-theme="dark"] .action-box, [data-theme="dark"] .processed-alert { background-color: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .action-textarea { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .action-textarea:focus { border-color: var(--primary) !important; background: var(--card-bg) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .modal-header-title, [data-theme="dark"] .case-val, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .case-label, [data-theme="dark"] th { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .data-table th, .data-table td, h1, h2, h3, p, span, div, .modal-content, .case-section, .action-box { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .case-grid { grid-template-columns: 1fr; gap: 20px; }
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
            
            .bento-card { border-radius: var(--radius-lg); }
            .data-table th, .data-table td { padding: 20px 25px; }
            
            .modal-content { padding: 30px 20px; margin: 10px; border-radius: var(--radius-lg); }
            .modal-header-title { font-size: 1.4rem; padding-right: 40px; }
            .modal-close { top: 15px; right: 15px; width: 35px; height: 35px; font-size: 1.2rem; }
            
            .case-section { padding: 20px; border-radius: var(--radius-md); }
            .action-box { padding: 20px; border-radius: var(--radius-md); }
            .action-box form > div:last-child { flex-direction: column; }
            .action-btn { width: 100%; font-size: 1rem; padding: 15px; }
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
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> <span>Overview</span></a></li>
            <li><a href="users.php"><i class="fa-solid fa-users"></i> <span>Manage Users</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-boxes-stacked"></i> <span>All Orders</span></a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> <span>Analytics</span></a></li>
            <li><a href="claims.php" class="active"><i class="fa-solid fa-file-shield"></i> <span>Disaster Claims</span></a></li>
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

            <div class="page-title logo-text" style="font-size: 1.6rem;">Relief Claims Verification</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>

                <button class="theme-btn" id="themeToggle" title="Toggle Theme" style="margin-right: 15px;">
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
            <?php echo $msg; ?>
            
            <div class="bento-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Claim ID</th>
                                <th>Farmer Info</th>
                                <th>Disaster Detail</th>
                                <th>Est. Loss (₹)</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if($claims_query && $claims_query->num_rows > 0): ?>
                                <?php while($row = $claims_query->fetch_assoc()): ?>
                                    <tr>
                                        <td style="font-weight: 900; font-size: 1.1rem;" class="logo-text">#CLM-<?php echo $row['id']; ?></td>
                                        <td>
                                            <div style="font-weight: 800; text-transform: capitalize; font-size: 1.05rem;"><?php echo htmlspecialchars($row['farmer_name']); ?></div>
                                            <div style="font-size: 0.85rem; color: var(--text-muted); font-weight: 600; margin-top: 4px;"><i class="fa-solid fa-layer-group" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($row['total_acreage']); ?> Acres</div>
                                        </td>
                                        <td>
                                            <div style="font-weight: 700; font-size: 1rem;"><?php echo htmlspecialchars($row['cause']); ?></div>
                                            <div style="font-size: 0.9rem; color: #ef4444; font-weight: 800; margin-top: 4px;"><i class="fa-solid fa-chart-simple"></i> Loss: <?php echo htmlspecialchars($row['loss_percent']); ?>%</div>
                                        </td>
                                        <td style="font-weight: 900; color: #10b981; font-size: 1.15rem; letter-spacing: -0.5px;">₹<?php echo number_format($row['estimated_loss_inr']); ?></td>
                                        <td><span class="status-badge <?php echo htmlspecialchars($row['status']); ?>"><?php echo htmlspecialchars($row['status']); ?></span></td>
                                        <td>
                                            <button class="btn-view" onclick='openModal(<?php echo htmlspecialchars(json_encode($row), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                <i class="fa-solid fa-folder-open"></i> Review File
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" style="text-align: center; padding: 60px; color: var(--text-muted); font-weight: 600; font-size: 1.1rem;">No disaster claims found in the database.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="caseModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <h2 class="modal-header-title"><i class="fa-solid fa-file-invoice" style="color: var(--primary);"></i> Official Case File <span id="m_id" style="color: var(--text-muted); font-weight: 600; font-size: 1.2rem; margin-left: auto;"></span></h2>
        
        <div class="case-grid">
            <div class="case-section">
                <h3><i class="fa-solid fa-tractor" style="color: #3b82f6;"></i> Farm & Incident Details</h3>
                <div class="case-label">Farmer Name</div>
                <div class="case-val" id="m_farmer"></div>
                
                <div class="case-label">Incident & Date</div>
                <div class="case-val"><span id="m_cause"></span> <span style="color: var(--text-muted); font-weight: 500; font-size: 0.9rem; margin-left: 5px;">(Date: <span id="m_date"></span>)</span></div>
                
                <div class="case-label">Farm Address & Size</div>
                <div class="case-val"><span id="m_address"></span><br><span style="color: var(--text-muted); font-weight: 600; font-size: 0.95rem; margin-top: 5px; display: inline-block;"><span id="m_acreage"></span> Acres Affected</span></div>
                
                <div class="case-label">Financial Assessment</div>
                <div class="case-val" style="color: #ef4444; font-size: 1.3rem; letter-spacing: -0.5px;">Est. Loss: ₹<span id="m_lossinr"></span> <span style="font-size: 0.9rem; font-weight: 700; color: #f87171;">(<span id="m_loss"></span>% Damage)</span></div>
                
                <div class="case-label">Bank Details (For Direct Transfer)</div>
                <div class="case-val" style="font-family: monospace; font-size: 1.05rem;">A/C: <span id="m_bank"></span><br>IFSC: <span id="m_ifsc"></span></div>
            </div>
            
            <div class="case-section">
                <h3><i class="fa-solid fa-magnifying-glass" style="color: #8b5cf6;"></i> Attached Evidence</h3>
                
                <div class="case-label">1. Photographic Damage Proof</div>
                <a id="link_img1" href="#" target="_blank"><img id="m_img1" class="evidence-img" src="" alt="Proof Image"></a>
                
                <div class="case-label" style="margin-top: 25px;">2. Land Ownership Document (RTC)</div>
                <a id="link_img2" href="#" target="_blank"><img id="m_img2" class="evidence-img" src="" alt="Land Document"></a>
            </div>
        </div>

        <div class="case-section" style="width: 100%; margin-bottom: 30px; margin-top: 0;" id="weatherEvidenceContainer">
            <h3 style="color: var(--primary);"><i class="fa-solid fa-satellite-dish"></i> Smart API Weather Verification</h3>
            <p style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 20px; font-weight: 500;">Cross-referencing farmer's claim date and location against historical global satellite data.</p>
            
            <div id="weatherLoader" style="color: var(--primary); font-weight: 700; font-size: 1.1rem; padding: 20px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-md); text-align: center;"><i class="fa-solid fa-spinner fa-spin" style="margin-right: 10px;"></i> Contacting satellite databases...</div>
            
            <div id="weatherResults" style="display: none; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; align-items: stretch;">
                <div style="background: var(--card-bg); padding: 25px 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); text-align: center; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-subtle);">
                    <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 8px;">Peak Temperature</div>
                    <div style="font-size: 2.2rem; font-weight: 900; color: #f59e0b;" class="logo-text" id="w_temp">--°C</div>
                </div>
                <div style="background: var(--card-bg); padding: 25px 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); text-align: center; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-subtle);">
                    <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 8px;">Total Rainfall</div>
                    <div style="font-size: 2.2rem; font-weight: 900; color: #3b82f6;" class="logo-text" id="w_rain">--mm</div>
                </div>
                <div style="background: var(--card-bg); padding: 25px 20px; border-radius: var(--radius-md); border: 1px solid var(--border-color); text-align: center; display: flex; flex-direction: column; justify-content: center; box-shadow: var(--shadow-subtle);">
                    <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; letter-spacing: 0.5px; margin-bottom: 8px;">System Verdict</div>
                    <div style="font-size: 1.5rem; font-weight: 900; color: var(--text-main);" id="w_verdict">--</div>
                </div>
            </div>
            <div id="weatherError" style="display: none; color: #ef4444; font-weight: 700; padding: 20px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: var(--radius-md); text-align: center;"><i class="fa-solid fa-triangle-exclamation" style="margin-right: 8px;"></i> Could not retrieve historical satellite data for this location/date. Manual review required.</div>
        </div>

        <div class="action-box" id="actionArea" style="margin-top: 0;">
            <form method="POST" action="claims.php">
                <input type="hidden" name="claim_id" id="m_form_id">
                <div style="margin-bottom: 20px;">
                    <label class="case-label" style="display: block; margin-bottom: 10px;">Official Admin Remarks (Visible to Farmer)</label>
                    <textarea name="admin_remarks" class="action-textarea" rows="3" placeholder="Enter notes, verification details, or reasons for rejection..." required></textarea>
                </div>
                <div style="display: flex; gap: 20px;">
                    <button type="submit" name="action_type" value="Approve" class="action-btn" style="background: #10b981; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);" onclick="return confirm('Are you sure you want to approve this claim and disburse funds?');"><i class="fa-solid fa-check"></i> Approve Claim</button>
                    <button type="submit" name="action_type" value="Reject" class="action-btn" style="background: #ef4444; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);" onclick="return confirm('Are you sure you want to reject this claim?');"><i class="fa-solid fa-xmark"></i> Reject Claim</button>
                </div>
            </form>
        </div>
        
        <div id="processedAlert" class="processed-alert">
            <i class="fa-solid fa-lock" style="font-size: 1.8rem; margin-bottom: 12px; color: var(--border-color);"></i><br>
            This case file has already been processed. <br>
            <span style="font-size: 0.95rem; display: block; margin-top: 12px; font-weight: 500;">Admin Remarks: <i id="m_remarks_display" style="color: var(--text-main); font-weight: 800; font-style: normal;"></i></span>
        </div>
    </div>
</div>

<script>
function openModal(data) {
    document.getElementById('m_id').innerText = "#CLM-" + data.id;
    document.getElementById('m_form_id').value = data.id;
    document.getElementById('m_farmer').innerText = data.farmer_name;
    document.getElementById('m_cause').innerText = data.cause;
    document.getElementById('m_date').innerText = data.incident_date;
    document.getElementById('m_address').innerText = data.farm_address;
    document.getElementById('m_acreage').innerText = data.total_acreage;
    document.getElementById('m_loss').innerText = data.loss_percent;
    document.getElementById('m_lossinr').innerText = data.estimated_loss_inr;
    document.getElementById('m_bank').innerText = data.bank_account;
    document.getElementById('m_ifsc').innerText = data.ifsc_code;
    
    // Set Images securely
    let imgPath1 = "../uploads/claims/" + data.proof_image;
    let imgPath2 = "../uploads/claims/" + data.proof_land_doc;
    document.getElementById('m_img1').src = imgPath1;
    document.getElementById('link_img1').href = imgPath1;
    document.getElementById('m_img2').src = imgPath2;
    document.getElementById('link_img2').href = imgPath2;

    // Hide actions if already processed
    if(data.status !== 'Pending Review') {
        document.getElementById('actionArea').style.display = 'none';
        document.getElementById('processedAlert').style.display = 'block';
        document.getElementById('m_remarks_display').innerText = data.admin_remarks || "No remarks provided.";
    } else {
        document.getElementById('actionArea').style.display = 'block';
        document.getElementById('processedAlert').style.display = 'none';
    }

    let modal = document.getElementById('caseModal');
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('show'); }, 10);
    
    // Trigger Weather Verification API
    verifyWeatherEvidence(data.farm_address, data.incident_date, data.cause);
}

// SMART CROP LOSS API INTEGRATION (Geocoding -> Open-Meteo)
async function verifyWeatherEvidence(address, date, claimedCause) {
    document.getElementById('weatherLoader').style.display = 'block';
    document.getElementById('weatherResults').style.display = 'none';
    document.getElementById('weatherError').style.display = 'none';

    try {
        // Step 1: Geocode the textual address to Lat/Lon using free Nominatim API
        const geocodeRes = await fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(address)}`);
        const geocodeData = await geocodeRes.json();
        
        let lat = 20.5937; // Default India center if not found
        let lon = 78.9629;
        
        if (geocodeData && geocodeData.length > 0) {
            lat = geocodeData[0].lat;
            lon = geocodeData[0].lon;
        }

        // Step 2: Fetch Historical Weather for exact Date + Lat/Lon from Open-Meteo API
        const weatherUrl = `https://archive-api.open-meteo.com/v1/archive?latitude=${lat}&longitude=${lon}&start_date=${date}&end_date=${date}&daily=temperature_2m_max,precipitation_sum&timezone=auto`;
        const weatherRes = await fetch(weatherUrl);
        const weatherData = await weatherRes.json();

        if (weatherData && weatherData.daily && weatherData.daily.temperature_2m_max) {
            let maxTemp = weatherData.daily.temperature_2m_max[0];
            let rain = weatherData.daily.precipitation_sum[0];
            
            document.getElementById('w_temp').innerText = (maxTemp !== null ? maxTemp + "°C" : "N/A");
            document.getElementById('w_rain').innerText = (rain !== null ? rain + "mm" : "N/A");
            
            // Generate Smart Verdict based on Claimed Cause
            let verdict = "<span style='color: var(--text-muted);'>Inconclusive</span>";
            if(claimedCause.toLowerCase().includes("rain") || claimedCause.toLowerCase().includes("flood")) {
                if(rain > 20) {
                    verdict = "<span style='color: #10b981;'><i class='fa-solid fa-circle-check'></i> Verified Severe Rain</span>";
                } else {
                    verdict = "<span style='color: #ef4444;'><i class='fa-solid fa-ban'></i> No Rain Detected</span>";
                }
            } else if(claimedCause.toLowerCase().includes("drought") || claimedCause.toLowerCase().includes("dry")) {
                if(rain < 2 && maxTemp > 35) {
                    verdict = "<span style='color: #10b981;'><i class='fa-solid fa-circle-check'></i> Verified Drought</span>";
                } else if(rain > 5) {
                    verdict = "<span style='color: #ef4444;'><i class='fa-solid fa-ban'></i> Rain Detected</span>";
                } else {
                    verdict = "<span style='color: #f59e0b;'><i class='fa-solid fa-circle-exclamation'></i> Partial Match</span>";
                }
            } else {
                verdict = "<span style='color: #8b5cf6;'><i class='fa-solid fa-bug'></i> Cannot purely verify by weather</span>";
            }
            
            document.getElementById('w_verdict').innerHTML = verdict;
            document.getElementById('weatherLoader').style.display = 'none';
            document.getElementById('weatherResults').style.display = 'grid';
        } else {
            throw new Error('Weather data unavailable for this specific date.');
        }
    } catch (e) {
        console.error("API Verification Error: ", e);
        document.getElementById('weatherLoader').style.display = 'none';
        document.getElementById('weatherError').style.display = 'block';
    }
}

function closeModal() {
    let modal = document.getElementById('caseModal');
    modal.classList.remove('show');
    // Wait for transition to finish before hiding
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

// Close modal if clicked outside of content
document.getElementById('caseModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeModal();
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