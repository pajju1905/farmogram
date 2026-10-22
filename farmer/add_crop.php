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

// Query current verification status
$status_query = $conn->query("SELECT verification_status FROM users WHERE id = $farmer_id");
$user_status = $status_query->fetch_assoc();
$verification_status = $user_status['verification_status'] ?? 'unverified';

if($verification_status !== 'verified'){
    header("Location: dashboard.php");
    exit();
}

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$msg = "";

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_crop'])){
    $crop_name = $conn->real_escape_string($_POST['crop_name']);
    $investment = (float)$_POST['investment'];
    $sowing_date = $conn->real_escape_string($_POST['sowing_date']);
    $expected_harvest = $conn->real_escape_string($_POST['expected_harvest']);
    
    // NEW: Capture Acreage and Initial Stock Quantity
    $acreage = isset($_POST['acreage']) ? (float)$_POST['acreage'] : 0;
    $quantity = isset($_POST['quantity']) ? (float)$_POST['quantity'] : 0;
    
    // Default values for new crops
    $market_price = 0; 
    $status = 'growing'; 
    $predicted_yield = 0;

    // Handle File Upload
    $image_path = 'placeholder.jpg';
    if(isset($_FILES['crop_image']) && $_FILES['crop_image']['error'] == 0){
        $target_dir = "../uploads/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }
        
        $ext = strtolower(pathinfo($_FILES["crop_image"]["name"], PATHINFO_EXTENSION));
        $image_path = "crop_" . time() . "_" . rand(100, 999) . "." . $ext;
        move_uploaded_file($_FILES["crop_image"]["tmp_name"], $target_dir . $image_path);
    }

    $query = "INSERT INTO crops (farmer_id, crop_name, acreage, quantity, investment, market_price, status, predicted_yield, sowing_date, expected_harvest, image) 
              VALUES ('$farmer_id', '$crop_name', '$acreage', '$quantity', '$investment', '$market_price', '$status', '$predicted_yield', '$sowing_date', '$expected_harvest', '$image_path')";
              
    if($conn->query($query)){
        $msg = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Awesome! <strong>" . htmlspecialchars($crop_name) . "</strong> has been successfully added to your digital farm.</div>";
    } else {
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Database Error: " . $conn->error . "</div>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Plant New Crop | Farmogram</title>
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
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1200px; margin: 0 auto; width: 100%; }

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Alerts */
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 25px; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); font-size: 1.05rem; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 25px; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); font-size: 1.05rem; }

        /* Navigation Button */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--card-bg); border: 2px solid var(--border-color); color: var(--text-main); padding: 12px 20px; border-radius: 50px; text-decoration: none; font-weight: 800; margin-bottom: 25px; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); transform: translateX(-4px); }

        /* Form Bento Layout */
        .form-grid { display: grid; grid-template-columns: 1.5fr 1fr; gap: 30px; align-items: start; }
        
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 40px; position: relative; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        .section-title { font-size: 1.4rem; font-weight: 900; color: var(--text-main); margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; border-bottom: 1px dashed var(--border-color); padding-bottom: 15px; }

        /* Form Inputs */
        .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: span 2; }
        .form-label { display: block; font-weight: 800; margin-bottom: 8px; font-size: 0.95rem; color: var(--text-main); transition: 0.3s; }
        .form-input { width: 100%; padding: 16px 20px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); border-radius: var(--radius-md); outline: none; font-size: 1rem; font-family: inherit; font-weight: 600; transition: 0.3s; }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }

        /* File Upload Box */
        .file-upload-box { border: 2px dashed var(--border-color); padding: 40px 20px; text-align: center; border-radius: var(--radius-lg); background: var(--bg-light); cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; justify-content: center; align-items: center; margin-bottom: 20px; }
        .file-upload-box:hover { border-color: var(--primary); background: rgba(16, 185, 129, 0.05); transform: translateY(-2px); }
        .file-upload-icon { font-size: 3rem; color: var(--primary); margin-bottom: 15px; transition: 0.3s; }
        .file-upload-box:hover .file-upload-icon { transform: scale(1.1); }
        .file-upload-text { font-weight: 900; font-size: 1.2rem; color: var(--text-main); letter-spacing: -0.5px; margin-bottom: 5px; }
        .file-upload-sub { font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }

        .btn-submit { background: var(--primary); color: #fff; border: none; padding: 18px; width: 100%; border-radius: var(--radius-md); font-weight: 900; font-size: 1.15rem; cursor: pointer; transition: 0.3s; margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); font-family: inherit; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

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
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .form-input { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .form-input:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        
        [data-theme="dark"] .file-upload-box { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .file-upload-box:hover { background: rgba(16, 185, 129, 0.1) !important; border-color: var(--primary) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .file-upload-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .file-upload-sub { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .file-upload-box, h1, h2, h3, p, span, div, .form-input, .btn-back { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; gap: 24px; }
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
            
            .bento-card { padding: 25px 20px; border-radius: var(--radius-lg); }
            .input-row { grid-template-columns: 1fr; gap: 0; }
            .form-group.full-width { grid-column: span 1; }
            
            .btn-submit { padding: 16px; font-size: 1.05rem; }
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Plant a New Crop</div>
            
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
            
            <a href="view_crops.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Inventory</a>

            <form method="POST" action="" enctype="multipart/form-data" class="form-grid">
                
                <div class="bento-card">
                    <h2 class="section-title"><i class="fa-solid fa-seedling" style="color: var(--primary);"></i> Crop Specifications</h2>
                    
                    <div class="form-group">
                        <label class="form-label">Crop Name</label>
                        <input type="text" name="crop_name" class="form-input" placeholder="e.g., Organic Tomatoes" required>
                    </div>

                    <div class="input-row">
                        <div class="form-group">
                            <label class="form-label">Farm Area (Acres)</label>
                            <input type="number" name="acreage" class="form-input" step="0.01" placeholder="e.g., 2.5" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Initial Stock / Available Qty (kg)</label>
                            <input type="number" name="quantity" class="form-input" step="0.01" placeholder="e.g., 0 if growing" value="0" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-bottom: 0;">
                        <label class="form-label">Total Capital Investment (₹)</label>
                        <input type="number" name="investment" class="form-input" step="0.01" placeholder="e.g., 15000" required>
                    </div>
                </div>

                <div class="bento-card">
                    <h2 class="section-title"><i class="fa-solid fa-calendar-days" style="color: #3b82f6;"></i> Timeline & Media</h2>
                    
                    <div class="input-row">
                        <div class="form-group">
                            <label class="form-label">Sowing Date</label>
                            <input type="date" name="sowing_date" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Expected Harvest</label>
                            <input type="date" name="expected_harvest" class="form-input" required>
                        </div>
                    </div>

                    <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
                        <label class="file-upload-box">
                            <i class="fa-solid fa-cloud-arrow-up file-upload-icon"></i>
                            <div class="file-upload-text">Upload Crop Photo</div>
                            <div class="file-upload-sub">JPG, PNG format (Max 2MB)</div>
                            <input type="file" name="crop_image" id="imgInput" accept="image/jpeg, image/png" style="display: none;" required>
                            <div id="imgName" style="margin-top: 15px; font-size: 0.95rem; font-weight: 800; color: var(--primary);"></div>
                        </label>
                    </div>

                    <button type="submit" name="submit_crop" class="btn-submit"><i class="fa-solid fa-seedling"></i> Plant Crop & Add to System</button>
                </div>

            </form>
        </div>
    </main>
</div>

<script>
// Display Selected File Name
document.getElementById('imgInput').addEventListener('change', function(e) {
    if(this.files && this.files.length > 0) {
        document.getElementById('imgName').innerHTML = '<i class="fa-solid fa-check-double"></i> Selected: ' + this.files[0].name;
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
