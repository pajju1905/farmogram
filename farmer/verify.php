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
$msg = "";

// Query current verification status
$status_query = $conn->query("SELECT verification_status, verification_remarks, identity_doc, verification_doc FROM users WHERE id = $farmer_id");
$user_status = $status_query->fetch_assoc();
$status = $user_status['verification_status'] ?? 'unverified';
$remarks = $user_status['verification_remarks'] ?? '';
$identity_doc = $user_status['identity_doc'] ?? '';
$verification_doc = $user_status['verification_doc'] ?? '';

if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_verification'])){
    if($status == 'verified' || $status == 'pending'){
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Verification already pending or completed.</div>";
    } else {
        $target_dir = "../uploads/verifications/";
        if (!is_dir($target_dir)) { mkdir($target_dir, 0777, true); }

        $id_path = "";
        $land_path = "";
        $upload_ok = true;

        // Upload Identity Document
        if(isset($_FILES['identity_doc']) && $_FILES['identity_doc']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES["identity_doc"]["name"], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $id_path = "id_" . $farmer_id . "_" . time() . "." . $ext;
                if(!move_uploaded_file($_FILES["identity_doc"]["tmp_name"], $target_dir . $id_path)) {
                    $upload_ok = false;
                    $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Failed to upload Identity Document.</div>";
                }
            } else {
                $upload_ok = false;
                $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Invalid Identity Document format. Only JPG, PNG, PDF are allowed.</div>";
            }
        } else {
            $upload_ok = false;
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Identity Document is required.</div>";
        }

        // Upload Land Document
        if($upload_ok && isset($_FILES['land_doc']) && $_FILES['land_doc']['error'] == 0){
            $ext = strtolower(pathinfo($_FILES["land_doc"]["name"], PATHINFO_EXTENSION));
            if(in_array($ext, ['jpg', 'jpeg', 'png', 'pdf'])) {
                $land_path = "land_" . $farmer_id . "_" . time() . "." . $ext;
                if(!move_uploaded_file($_FILES["land_doc"]["tmp_name"], $target_dir . $land_path)) {
                    $upload_ok = false;
                    $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Failed to upload Land Ownership Document.</div>";
                }
            } else {
                $upload_ok = false;
                $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Invalid Land Document format. Only JPG, PNG, PDF are allowed.</div>";
            }
        } else {
            $upload_ok = false;
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Land Document is required.</div>";
        }

        if($upload_ok){
            $query = "UPDATE users SET 
                      verification_status = 'pending', 
                      identity_doc = '$id_path', 
                      verification_doc = '$land_path', 
                      verification_submitted_at = NOW(),
                      verification_remarks = NULL 
                      WHERE id = $farmer_id";
            if($conn->query($query)){
                $status = 'pending';
                $msg = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Verification documents submitted successfully! Our team will review them shortly.</div>";
            } else {
                $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Database update failed: " . $conn->error . "</div>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verify Account | Farmogram</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('farmogram_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        :root {
            --radius-xl: 32px;
            --radius-lg: 24px;
            --radius-md: 16px;
            --shadow-subtle: 0 8px 30px rgba(0,0,0,0.04);
            --shadow-hover: 0 15px 35px rgba(0,0,0,0.08);
            --app-nav-height: 75px;
        }

        body { margin: 0; overflow: hidden; background-color: var(--bg-light); color: var(--text-main); font-family: 'Inter', sans-serif; transition: 0.3s; }
        .app-layout { display: flex; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1200px; margin: 0 auto; width: 100%; }

        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 25px; border: 1px solid rgba(16, 185, 129, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 25px; border: 1px solid rgba(239, 68, 68, 0.2); font-weight: 700; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }

        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--card-bg); border: 2px solid var(--border-color); color: var(--text-main); padding: 12px 20px; border-radius: 50px; text-decoration: none; font-weight: 800; margin-bottom: 25px; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); transform: translateX(-4px); }

        .form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start; }
        @media (max-width: 1024px) {
            .form-grid { grid-template-columns: 1fr; gap: 24px; }
        }

        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 40px; position: relative; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        .section-title { font-size: 1.4rem; font-weight: 900; color: var(--text-main); margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; border-bottom: 1px dashed var(--border-color); padding-bottom: 15px; }

        .file-upload-box { border: 2px dashed var(--border-color); padding: 40px 20px; text-align: center; border-radius: var(--radius-lg); background: var(--bg-light); cursor: pointer; transition: 0.3s; display: flex; flex-direction: column; justify-content: center; align-items: center; margin-bottom: 20px; }
        .file-upload-box:hover { border-color: var(--primary); background: rgba(16, 185, 129, 0.05); transform: translateY(-2px); }
        .file-upload-icon { font-size: 3rem; color: var(--primary); margin-bottom: 15px; transition: 0.3s; }
        .file-upload-box:hover .file-upload-icon { transform: scale(1.1); }
        .file-upload-text { font-weight: 900; font-size: 1.2rem; color: var(--text-main); letter-spacing: -0.5px; margin-bottom: 5px; }
        .file-upload-sub { font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }

        .btn-submit { background: var(--primary); color: #fff; border: none; padding: 18px; width: 100%; border-radius: var(--radius-md); font-weight: 900; font-size: 1.15rem; cursor: pointer; transition: 0.3s; margin-top: 15px; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); font-family: inherit; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

        .status-badge { display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border-radius: 50px; font-weight: 800; font-size: 0.95rem; text-transform: uppercase; margin-bottom: 20px; }
        .status-badge.unverified { background: rgba(100, 116, 139, 0.1); color: #64748b; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status-badge.verified { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-badge.rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        /* Sidebar Logo */
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }

        /* Mobile Topbar Branding (Hidden on Desktop) */
        .mobile-brand { display: none; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        .mobile-brand span { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        /* Topbar actions */
        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        .logo-light { display: block; width: 35px; height: 35px; object-fit: contain; }
        .logo-dark { display: none; width: 35px; height: 35px; object-fit: contain; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

        /* Dark mode overrides */
        [data-theme="dark"] {
            --bg-light: #0f172a !important;
            --sidebar-bg: #1e293b !important;
            --text-main: #f8fafc !important;
            --text-muted: #94a3b8 !important;
            --border-color: rgba(255,255,255,0.05) !important;
            --card-bg: #1e293b !important;
        }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.8) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        body, .sidebar, .topbar, .bento-card, h1, h2, h3, p, span, div { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        @media (max-width: 768px) {
            .app-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
            .sidebar-logo { display: none; } 
            .mobile-brand { display: flex; align-items: center; gap: 8px; text-decoration: none; }
            .mobile-brand span { font-size: 1.3rem; font-weight: 900; color: var(--text-main); }
            .topbar { padding: 12px 15px; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; }
            .desktop-only-text { display: none !important; }
            .avatar { width: 38px; height: 38px; font-size: 1rem; }
            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; border-top: 1px solid var(--border-color); background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; gap: 6px; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; }
            .nav-menu a i { font-size: 1.3rem; }
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 20px 15px; flex: 1; overflow-y: auto; }
            .bento-card { padding: 25px 20px; }
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Farmer Verification</div>
            
            <div class="topbar-actions">
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
            
            <a href="dashboard.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

            <div class="bento-card" style="margin-bottom: 30px;">
                <h2 class="section-title"><i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> Verification Status</h2>
                
                <div>
                    <?php if($status == 'verified'): ?>
                        <div class="status-badge verified"><i class="fa-solid fa-circle-check"></i> Account Verified</div>
                        <p style="font-size: 1.1rem; line-height: 1.6; font-weight: 500;">
                            Congratulations! Your account is fully verified. You have full access to list crops and make sales in the Farmogram marketplace.
                        </p>
                    <?php elseif($status == 'pending'): ?>
                        <div class="status-badge pending"><i class="fa-solid fa-clock"></i> Pending Review</div>
                        <p style="font-size: 1.1rem; line-height: 1.6; font-weight: 500;">
                            Your verification documents have been received and are currently being reviewed by our administration team. This typically takes 24-48 hours. Thank you for your patience!
                        </p>
                    <?php elseif($status == 'rejected'): ?>
                        <div class="status-badge rejected"><i class="fa-solid fa-circle-xmark"></i> Verification Rejected</div>
                        <p style="font-size: 1.1rem; line-height: 1.6; font-weight: 500; color: #ef4444; margin-bottom: 15px;">
                            <strong>Reason:</strong> <?php echo htmlspecialchars($remarks); ?>
                        </p>
                        <p style="font-size: 1rem; line-height: 1.6; font-weight: 500; color: var(--text-muted);">
                            Please review the reason for rejection above and upload correct/clearer documents below to submit another review request.
                        </p>
                    <?php else: ?>
                        <div class="status-badge unverified"><i class="fa-solid fa-circle-minus"></i> Unverified Account</div>
                        <p style="font-size: 1.1rem; line-height: 1.6; font-weight: 500;">
                            To ensure the trust and safety of our buyers, all farmers must complete verification before listing crops. Please upload your Identity Document and Land Ownership Proof below.
                        </p>
                    <?php endif; ?>
                </div>
            </div>

            <?php if($status == 'unverified' || $status == 'rejected'): ?>
                <form method="POST" action="" enctype="multipart/form-data" class="form-grid">
                    
                    <div class="bento-card">
                        <h2 class="section-title"><i class="fa-solid fa-id-card" style="color: var(--primary);"></i> 1. Government Identity Document</h2>
                        <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 600; margin-bottom: 25px;">
                            Please upload a scanned image or PDF copy of your Govt ID (e.g., Aadhaar Card, PAN Card, Voter ID, or Driver's License). Make sure the text and photo are clearly legible.
                        </p>
                        
                        <div class="form-group">
                            <label class="file-upload-box">
                                <i class="fa-solid fa-cloud-arrow-up file-upload-icon"></i>
                                <div class="file-upload-text">Upload Identity Document</div>
                                <div class="file-upload-sub">JPG, PNG, PDF format (Max 3MB)</div>
                                <input type="file" name="identity_doc" id="idInput" accept="image/jpeg, image/png, application/pdf" style="display: none;" required>
                                <div id="idName" style="margin-top: 15px; font-size: 0.95rem; font-weight: 800; color: var(--primary);"></div>
                            </label>
                        </div>
                    </div>

                    <div class="bento-card">
                        <h2 class="section-title"><i class="fa-solid fa-file-invoice" style="color: #3b82f6;"></i> 2. Land Ownership Certificate</h2>
                        <p style="color: var(--text-muted); font-size: 0.95rem; font-weight: 600; margin-bottom: 25px;">
                            Please upload proof of land ownership or cultivation rights. This can be your Land deed (Patta/Chitta), Possession Certificate, tax receipts, or a lease agreement.
                        </p>
                        
                        <div class="form-group">
                            <label class="file-upload-box">
                                <i class="fa-solid fa-cloud-arrow-up file-upload-icon" style="color: #3b82f6;"></i>
                                <div class="file-upload-text">Upload Land Document</div>
                                <div class="file-upload-sub">JPG, PNG, PDF format (Max 3MB)</div>
                                <input type="file" name="land_doc" id="landInput" accept="image/jpeg, image/png, application/pdf" style="display: none;" required>
                                <div id="landName" style="margin-top: 15px; font-size: 0.95rem; font-weight: 800; color: #3b82f6;"></div>
                            </label>
                        </div>

                        <button type="submit" name="submit_verification" class="btn-submit"><i class="fa-solid fa-paper-plane"></i> Submit for Admin Review</button>
                    </div>

                </form>
            <?php endif; ?>
        </div>
    </main>
</div>

<script>
// Display Selected File Names
const idInput = document.getElementById('idInput');
if(idInput) {
    idInput.addEventListener('change', function(e) {
        if(this.files && this.files.length > 0) {
            document.getElementById('idName').innerHTML = '<i class="fa-solid fa-check-double"></i> Selected: ' + this.files[0].name;
        }
    });
}

const landInput = document.getElementById('landInput');
if(landInput) {
    landInput.addEventListener('change', function(e) {
        if(this.files && this.files.length > 0) {
            document.getElementById('landName').innerHTML = '<i class="fa-solid fa-check-double"></i> Selected: ' + this.files[0].name;
        }
    });
}

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

</body>
</html>
