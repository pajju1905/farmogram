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
$msg = "";

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_claim'])) {
    $incident_date = $conn->real_escape_string($_POST['incident_date']);
    $cause = $conn->real_escape_string($_POST['cause']);
    $loss_percent = (int)$_POST['loss_percent'];
    $farm_address = $conn->real_escape_string($_POST['farm_address']);
    $total_acreage = (float)$_POST['total_acreage'];
    $estimated_loss_inr = (int)$_POST['estimated_loss_inr'];
    $bank_account = $conn->real_escape_string($_POST['bank_account']);
    $ifsc_code = strtoupper($conn->real_escape_string($_POST['ifsc_code']));
    
    $target_dir = "../uploads/claims/";
    if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
    
    $proof_image = "";
    $proof_land_doc = "";
    
    // Upload Damage Photo
    if (isset($_FILES['proof_image']) && $_FILES['proof_image']['error'] == 0) {
        $ext1 = strtolower(pathinfo($_FILES["proof_image"]["name"], PATHINFO_EXTENSION));
        $proof_image = "damage_" . time() . "_" . rand(100, 999) . "." . $ext1;
        move_uploaded_file($_FILES["proof_image"]["tmp_name"], $target_dir . $proof_image);
    }
    
    // Upload Land Document
    if (isset($_FILES['proof_land_doc']) && $_FILES['proof_land_doc']['error'] == 0) {
        $ext2 = strtolower(pathinfo($_FILES["proof_land_doc"]["name"], PATHINFO_EXTENSION));
        $proof_land_doc = "land_" . time() . "_" . rand(100, 999) . "." . $ext2;
        move_uploaded_file($_FILES["proof_land_doc"]["tmp_name"], $target_dir . $proof_land_doc);
    }

    if ($proof_image != "" && $proof_land_doc != "") {
        $insert_query = "INSERT INTO crop_claims (farmer_id, incident_date, cause, loss_percent, farm_address, total_acreage, estimated_loss_inr, proof_image, proof_land_doc, bank_account, ifsc_code) 
                         VALUES ('$farmer_id', '$incident_date', '$cause', '$loss_percent', '$farm_address', '$total_acreage', '$estimated_loss_inr', '$proof_image', '$proof_land_doc', '$bank_account', '$ifsc_code')";
        if ($conn->query($insert_query)) {
            $msg = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Comprehensive Claim submitted successfully. Tracking ID: #CLM-" . $conn->insert_id . "</div>";
        } else {
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Database error. Please try again.</div>";
        }
    } else {
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> You must upload BOTH the damage evidence and the land ownership document.</div>";
    }
}

$claims_history = $conn->query("SELECT * FROM crop_claims WHERE farmer_id = '$farmer_id' ORDER BY created_at DESC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Schemes & Relief | Farmogram</title>
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

        /* Modern Pill Tabs */
        .tabs-container { display: flex; gap: 15px; margin-bottom: 30px; flex-wrap: wrap; }
        .tab-btn { background: var(--card-bg); border: 2px solid var(--border-color); padding: 14px 28px; border-radius: 50px; font-size: 1rem; font-weight: 800; color: var(--text-muted); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        .tab-btn:hover { border-color: var(--primary); color: var(--text-main); transform: translateY(-2px); }
        .tab-btn.active { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Bento Card Wrapper */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); padding: 35px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .bento-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

        /* Schemes Grid */
        .schemes-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(350px, 1fr)); gap: 24px; }
        
        .btn-apply { display: inline-block; background: rgba(16, 185, 129, 0.1); color: var(--primary); border: 1px solid rgba(16, 185, 129, 0.2); padding: 14px 20px; border-radius: var(--radius-md); text-decoration: none; font-weight: 800; text-align: center; transition: 0.3s; margin-top: auto; box-shadow: var(--shadow-subtle); }
        .btn-apply:hover { background: var(--primary); color: #fff; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3); border-color: var(--primary); }

        /* Form Layout */
        .relief-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; }
        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .form-group { margin-bottom: 20px; }
        .form-label { display: block; font-weight: 800; margin-bottom: 8px; color: var(--text-main); font-size: 0.95rem; transition: 0.3s; }
        .form-input, .form-select { width: 100%; padding: 16px 20px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); border-radius: var(--radius-md); outline: none; font-size: 1rem; font-weight: 600; font-family: inherit; transition: 0.3s; }
        .form-input:focus, .form-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        
        .file-upload-box { border: 2px dashed var(--border-color); padding: 30px 20px; text-align: center; border-radius: var(--radius-md); background: var(--bg-light); cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; justify-content: center; align-items: center; height: 100%; }
        .file-upload-box:hover { border-color: var(--primary); background: rgba(16, 185, 129, 0.05); transform: translateY(-2px); }
        
        .btn-submit { background: #ef4444; color: #fff; border: none; padding: 18px; width: 100%; border-radius: var(--radius-md); font-size: 1.1rem; font-weight: 900; cursor: pointer; transition: 0.3s; margin-top: 15px; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2); display: flex; align-items: center; justify-content: center; gap: 10px; }
        .btn-submit:hover { background: #dc2626; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(239, 68, 68, 0.3); }

        /* Alerts */
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 30px; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 30px; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        
        /* Claim History Items */
        .claim-item { background: var(--bg-light); padding: 22px; border: 1px solid var(--border-color); border-radius: var(--radius-md); margin-bottom: 15px; border-left: 5px solid #ef4444; transition: 0.3s; }
        .claim-item:hover { border-color: var(--primary); background: var(--card-bg); transform: translateX(5px); box-shadow: var(--shadow-subtle); }
        .status-badge { padding: 6px 14px; border-radius: 50px; font-size: 0.85rem; font-weight: 900; background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.2); text-transform: uppercase; letter-spacing: 0.5px; }
        .status-badge.Approved { background: rgba(16, 185, 129, 0.1); color: #10b981; border-color: rgba(16, 185, 129, 0.2); }
        .status-badge.Rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; border-color: rgba(239, 68, 68, 0.2); }
        .admin-remark { background: var(--card-bg); padding: 15px; border-radius: 12px; font-size: 0.95rem; color: var(--text-main); margin-top: 15px; border-left: 4px solid var(--text-muted); border-top: 1px solid var(--border-color); border-right: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); font-weight: 500; }

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
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color); }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .form-label, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted) !important; }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }
        
        /* Schemes Specific Dark Mode */
        [data-theme="dark"] .tab-btn { background: var(--sidebar-bg); border-color: var(--border-color); color: var(--text-muted); }
        [data-theme="dark"] .tab-btn:hover { border-color: var(--primary); color: var(--text-main); }
        [data-theme="dark"] .tab-btn.active { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important; }
        
        [data-theme="dark"] .form-input, [data-theme="dark"] .form-select { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .form-input:focus, [data-theme="dark"] .form-select:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        
        [data-theme="dark"] .file-upload-box { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .file-upload-box:hover { background: rgba(16, 185, 129, 0.1) !important; border-color: var(--primary) !important; }
        
        [data-theme="dark"] .claim-item { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .claim-item:hover { background: var(--sidebar-bg) !important; border-color: var(--primary) !important; }
        [data-theme="dark"] .admin-remark { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .claim-item, h1, h2, h3, h4, p, span, div, .form-input, .form-select, .file-upload-box { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .relief-grid { grid-template-columns: 1fr; }
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
            
            .tabs-container { gap: 10px; flex-wrap: nowrap; overflow-x: auto; padding-bottom: 5px; }
            .tabs-container::-webkit-scrollbar { display: none; }
            .tab-btn { flex: 0 0 auto; padding: 10px 18px; font-size: 0.9rem; }
            
            .bento-card { padding: 25px 20px; border-radius: var(--radius-lg); }
            .form-grid { grid-template-columns: 1fr; gap: 15px; }
            .schemes-grid { grid-template-columns: 1fr; gap: 20px; }
            
            .btn-apply { text-align: center; display: block; }
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
            <li><a href="orders.php"><i class="fa-solid fa-cart-flatbed"></i> <span>Orders</span></a></li>
            <li><a href="enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
            <li><a href="tasks.php"><i class="fa-solid fa-calendar-check"></i> <span>Tasks</span></a></li> 
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="assistant.php"><i class="fa-solid fa-robot"></i> <span>AI Bot</span></a></li>
            <li><a href="schemes.php" class="active"><i class="fa-solid fa-landmark-dome"></i> <span>Govt</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.5rem;">Schemes & Crop Relief</div>
            
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
            <?php echo $msg; ?>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('tab-schemes')"><i class="fa-solid fa-landmark"></i> Government Schemes</button>
                <button class="tab-btn" onclick="switchTab('tab-relief')"><i class="fa-solid fa-file-invoice"></i> Crop Loss Relief (Claim)</button>
            </div>

            <div id="tab-schemes" class="tab-content active">
                <div class="schemes-grid">
                    
                    <div class="bento-card">
                        <h3 style="margin-top:0; font-size: 1.4rem; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-money-bill-wheat" style="color: var(--primary); margin-right: 8px;"></i> PM-KISAN Samman Nidhi</h3>
                        <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 25px; line-height: 1.6; font-weight: 500;">Income support of ₹6,000 per year directly to bank accounts in three equal installments.</p>
                        <a href="https://pmkisan.gov.in/" target="_blank" class="btn-apply"><i class="fa-solid fa-arrow-up-right-from-square"></i> Official Portal</a>
                    </div>

                    <div class="bento-card">
                        <h3 style="margin-top:0; font-size: 1.4rem; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-umbrella" style="color: var(--primary); margin-right: 8px;"></i> PM Fasal Bima Yojana</h3>
                        <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 25px; line-height: 1.6; font-weight: 500;">Comprehensive insurance cover against crop failures due to natural calamities.</p>
                        <a href="https://pmfby.gov.in/" target="_blank" class="btn-apply"><i class="fa-solid fa-arrow-up-right-from-square"></i> Official Portal</a>
                    </div>

                    <div class="bento-card">
                        <h3 style="margin-top:0; font-size: 1.4rem; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-credit-card" style="color: var(--primary); margin-right: 8px;"></i> Kisan Credit Card (KCC)</h3>
                        <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 25px; line-height: 1.6; font-weight: 500;">Provides farmers with timely access to credit for agricultural expenses. Offers flexible repayment options and low-interest rates.</p>
                        <a href="https://sbi.co.in/web/agri-rural/agriculture-banking/crop-loan/kisan-credit-card" target="_blank" class="btn-apply"><i class="fa-solid fa-building-columns"></i> Apply via SBI</a>
                    </div>

                    <div class="bento-card">
                        <h3 style="margin-top:0; font-size: 1.4rem; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-leaf" style="color: var(--primary); margin-right: 8px;"></i> Paramparagat Krishi Vikas Yojana</h3>
                        <p style="color: var(--text-muted); font-size: 1rem; margin-bottom: 25px; line-height: 1.6; font-weight: 500;">Aims to promote organic farming. Farmers are given financial assistance of ₹50,000 per hectare for three years to cover organic inputs.</p>
                        <a href="https://pgsindia-ncof.gov.in/pkvy/index.aspx" target="_blank" class="btn-apply"><i class="fa-solid fa-arrow-up-right-from-square"></i> Official Portal</a>
                    </div>

                </div>
            </div>

            <div id="tab-relief" class="tab-content">
                <div class="relief-grid">
                    
                    <div class="bento-card">
                        <h2 style="margin-top:0; color: #ef4444; font-size: 1.8rem; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-file-invoice"></i> Official Claim Application</h2>
                        <p style="color: var(--text-muted); font-size: 1.05rem; margin-bottom: 35px; font-weight: 500;">Please provide accurate financial details and upload official land documents to initiate the audit process.</p>
                        
                        <form method="POST" action="" enctype="multipart/form-data">
                            
                            <h4 style="border-bottom: 1px dashed var(--border-color); padding-bottom: 10px; margin-bottom: 20px; font-size: 1.15rem; color: var(--primary);" class="logo-text">1. Incident Details</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Date of Disaster</label>
                                    <input type="date" name="incident_date" class="form-input" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Cause of Loss</label>
                                    <select name="cause" class="form-select" required>
                                        <option value="" disabled selected>Select cause...</option>
                                        <option value="Heavy Rainfall / Flood">Heavy Rainfall / Flooding</option>
                                        <option value="Drought / No Rain">Severe Drought</option>
                                        <option value="Pest Attack / Disease">Pest Attack / Disease</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Farm Village/Location</label>
                                    <input type="text" name="farm_address" class="form-input" placeholder="Enter full farm address" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Total Acreage Affected</label>
                                    <input type="number" name="total_acreage" class="form-input" step="0.1" placeholder="e.g. 5.5" required>
                                </div>
                            </div>

                            <h4 style="border-bottom: 1px dashed var(--border-color); padding-bottom: 10px; margin-bottom: 20px; margin-top: 15px; font-size: 1.15rem; color: var(--primary);" class="logo-text">2. Financial Details</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="form-label">Est. Financial Loss (₹)</label>
                                    <input type="number" name="estimated_loss_inr" class="form-input" placeholder="e.g. 50000" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Physical Loss Percentage (%)</label>
                                    <input type="number" name="loss_percent" class="form-input" min="10" max="100" placeholder="e.g. 75" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Bank Account Number</label>
                                    <input type="text" name="bank_account" class="form-input" placeholder="For fund transfer" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Bank IFSC Code</label>
                                    <input type="text" name="ifsc_code" class="form-input" placeholder="e.g. SBIN0001234" required>
                                </div>
                            </div>

                            <h4 style="border-bottom: 1px dashed var(--border-color); padding-bottom: 10px; margin-bottom: 20px; margin-top: 15px; font-size: 1.15rem; color: var(--primary);" class="logo-text">3. Required Evidence</h4>
                            <div class="form-grid">
                                <div class="form-group">
                                    <label class="file-upload-box">
                                        <i class="fa-solid fa-camera" style="font-size: 2.5rem; color: #ef4444; margin-bottom: 12px; transition: 0.3s;"></i>
                                        <div style="font-weight: 800; font-size: 1.1rem;" class="logo-text">Crop Damage Photo</div>
                                        <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 5px; font-weight: 600;">JPG or PNG only</div>
                                        <input type="file" name="proof_image" accept="image/jpeg, image/png" style="display: none;" required id="imgInput">
                                        <div id="imgName" style="margin-top: 15px; font-size: 0.9rem; font-weight: 800; color: var(--primary);"></div>
                                    </label>
                                </div>
                                <div class="form-group">
                                    <label class="file-upload-box">
                                        <i class="fa-solid fa-file-lines" style="font-size: 2.5rem; color: #3b82f6; margin-bottom: 12px; transition: 0.3s;"></i>
                                        <div style="font-weight: 800; font-size: 1.1rem;" class="logo-text">Land Document (RTC)</div>
                                        <div style="font-size: 0.9rem; color: var(--text-muted); margin-top: 5px; font-weight: 600;">PDF, JPG, or PNG</div>
                                        <input type="file" name="proof_land_doc" accept="image/jpeg, image/png, application/pdf" style="display: none;" required id="docInput">
                                        <div id="docName" style="margin-top: 15px; font-size: 0.9rem; font-weight: 800; color: var(--primary);"></div>
                                    </label>
                                </div>
                            </div>

                            <button type="submit" name="submit_claim" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Submit Application for Audit</button>
                        </form>
                    </div>

                    <div class="bento-card">
                        <h3 style="margin-top:0; font-size: 1.4rem; margin-bottom: 25px; letter-spacing: -0.5px;" class="logo-text"><i class="fa-solid fa-folder-open" style="color: var(--primary); margin-right: 8px;"></i> Claim History</h3>
                        
                        <?php if($claims_history && $claims_history->num_rows > 0): ?>
                            <div style="max-height: 800px; overflow-y: auto; padding-right: 10px; display: flex; flex-direction: column; gap: 15px;">
                                <?php while($claim = $claims_history->fetch_assoc()): ?>
                                    <div class="claim-item">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; flex-wrap: wrap; gap: 10px;">
                                            <div style="font-weight: 900; font-size: 1.2rem; letter-spacing: -0.5px;" class="logo-text">#CLM-<?php echo $claim['id']; ?></div>
                                            <div class="status-badge <?php echo $claim['status']; ?>"><?php echo $claim['status']; ?></div>
                                        </div>
                                        <div style="font-size: 0.95rem; color: var(--text-muted); margin-bottom: 8px; font-weight: 600;"><i class="fa-regular fa-calendar" style="margin-right: 5px;"></i> <?php echo date('M j, Y', strtotime($claim['created_at'])); ?></div>
                                        <div style="font-size: 1.05rem; font-weight: 800; margin-bottom: 8px; color: var(--text-main);"><i class="fa-solid fa-cloud-bolt" style="color: #ef4444; width: 20px;"></i> <?php echo htmlspecialchars($claim['cause']); ?></div>
                                        <div style="font-size: 1.05rem; font-weight: 900; color: var(--primary); margin-top: 10px;"><i class="fa-solid fa-indian-rupee-sign"></i> Est. Loss: ₹<?php echo number_format($claim['estimated_loss_inr']); ?></div>
                                        
                                        <?php if(!empty($claim['admin_remarks'])): ?>
                                            <div class="admin-remark"><strong><i class="fa-solid fa-comment-dots" style="color: var(--primary);"></i> Admin Note:</strong> <?php echo htmlspecialchars($claim['admin_remarks']); ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div style="text-align: center; color: var(--text-muted); padding: 80px 20px; background: var(--bg-light); border-radius: var(--radius-md); border: 2px dashed var(--border-color);">
                                <i class="fa-solid fa-box-open" style="font-size: 3.5rem; margin-bottom: 20px; opacity: 0.3;"></i>
                                <div style="font-weight: 800; font-size: 1.2rem; color: var(--text-main);" class="logo-text">No claims submitted.</div>
                                <div style="font-size: 0.95rem; margin-top: 5px; font-weight: 500;">Your submitted relief applications will appear here.</div>
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

        </div>
    </main>
</div>

<script>
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

// TAB SWITCHING LOGIC
function switchTab(tabId) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

// FILE UPLOAD NAME DISPLAY LOGIC
document.getElementById('imgInput').addEventListener('change', function(e) {
    if(this.files.length > 0) document.getElementById('imgName').innerHTML = '<i class="fa-solid fa-check-double"></i> ' + this.files[0].name;
});
document.getElementById('docInput').addEventListener('change', function(e) {
    if(this.files.length > 0) document.getElementById('docName').innerHTML = '<i class="fa-solid fa-check-double"></i> ' + this.files[0].name;
});
</script>

<script src="../assets/js/translate.js"></script>

</body>
</html>
