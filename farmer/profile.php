<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK
if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'farmer'){
    header("Location: ../auth/login.php");
    exit();
}

$farmer_id = $_SESSION['user_id'];
$msg = "";

// Handle Profile Updates
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])){
    $name = mysqli_real_escape_string($conn, trim($_POST['name']));
    $email = mysqli_real_escape_string($conn, trim($_POST['email']));
    $phone = mysqli_real_escape_string($conn, trim($_POST['phone']));
    $location = mysqli_real_escape_string($conn, trim($_POST['location']));
    $bio = mysqli_real_escape_string($conn, trim($_POST['bio']));

    $error = false;

    // Validation
    if(empty($name) || empty($email) || empty($phone) || empty($location)){
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Please fill in all required fields.</div>";
        $error = true;
    }

    // Email/Phone uniqueness check
    if(!$error){
        $unique_check = $conn->query("SELECT id FROM users WHERE (email = '$email' OR phone = '$phone') AND id != $farmer_id");
        if($unique_check && $unique_check->num_rows > 0){
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Email or Phone Number is already in use by another user.</div>";
            $error = true;
        }
    }

    // Profile Image Upload
    $image_filename = null;
    if(!$error && isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0){
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        $ext = strtolower(pathinfo($_FILES['profile_image']['name'], PATHINFO_EXTENSION));
        $size = $_FILES['profile_image']['size'];

        if(in_array($ext, $allowed_exts)){
            if($size <= 2097152){ // 2MB limit
                $image_filename = "profile_" . $farmer_id . "_" . time() . "." . $ext;
                $target_path = "../uploads/profile/" . $image_filename;

                if(move_uploaded_file($_FILES['profile_image']['tmp_name'], $target_path)){
                    // Get current profile image to delete it later
                    $curr_img_q = $conn->query("SELECT profile_image FROM users WHERE id = $farmer_id");
                    if($curr_img_q && $row = $curr_img_q->fetch_assoc()){
                        if(!empty($row['profile_image']) && file_exists("../uploads/profile/" . $row['profile_image'])){
                            unlink("../uploads/profile/" . $row['profile_image']);
                        }
                    }
                } else {
                    $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Failed to upload profile image.</div>";
                    $error = true;
                }
            } else {
                $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Profile image exceeds the 2MB size limit.</div>";
                $error = true;
            }
        } else {
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Invalid image format. Only JPG, PNG, and GIF are allowed.</div>";
            $error = true;
        }
    }

    if(!$error){
        if($image_filename !== null){
            $update_query = "UPDATE users SET name = '$name', email = '$email', phone = '$phone', location = '$location', bio = '$bio', profile_image = '$image_filename' WHERE id = $farmer_id";
        } else {
            $update_query = "UPDATE users SET name = '$name', email = '$email', phone = '$phone', location = '$location', bio = '$bio' WHERE id = $farmer_id";
        }

        if($conn->query($update_query)){
            $_SESSION['name'] = $name; // Keep session synced
            $msg = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Profile details updated successfully!</div>";
        } else {
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Database error: " . $conn->error . "</div>";
        }
    }
}

// Handle Password Updates
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_password'])){
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    $error = false;

    if(empty($current_pass) || empty($new_pass) || empty($confirm_pass)){
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Please fill in all password fields.</div>";
        $error = true;
    }

    if(!$error && $new_pass !== $confirm_pass){
        $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> New Password and Confirm Password do not match.</div>";
        $error = true;
    }

    if(!$error){
        $pass_query = $conn->query("SELECT password FROM users WHERE id = $farmer_id");
        if($pass_query && $row = $pass_query->fetch_assoc()){
            if(password_verify($current_pass, $row['password'])){
                $hashed_pass = password_hash($new_pass, PASSWORD_DEFAULT);
                if($conn->query("UPDATE users SET password = '$hashed_pass' WHERE id = $farmer_id")){
                    $msg = "<div class='alert-success'><i class='fa-solid fa-circle-check'></i> Password updated successfully!</div>";
                } else {
                    $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Database error updating password.</div>";
                }
            } else {
                $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Incorrect current password.</div>";
            }
        } else {
            $msg = "<div class='alert-error'><i class='fa-solid fa-triangle-exclamation'></i> Failed to retrieve user details.</div>";
        }
    }
}

// Fetch Latest Farmer Data
$farmer_query = $conn->query("SELECT * FROM users WHERE id = $farmer_id");
$farmer = $farmer_query->fetch_assoc();

$user_name = $farmer['name'] ?? $_SESSION['name'] ?? 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));
$profile_image = $farmer['profile_image'] ?? '';
$verification_status = $farmer['verification_status'] ?? 'unverified';

// Calculate Pending Alerts/Notifications count
$note_count_query = $conn->query("SELECT COUNT(*) AS count FROM notifications WHERE user_id = $farmer_id AND is_read = 0");
$note_count = $note_count_query ? $note_count_query->fetch_assoc()['count'] : 0;
$notes = $conn->query("SELECT * FROM notifications WHERE user_id = $farmer_id ORDER BY created_at DESC LIMIT 6");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile | Farmogram</title>
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

        .profile-grid { display: grid; grid-template-columns: 350px 1fr; gap: 30px; align-items: start; }
        @media (max-width: 1024px) {
            .profile-grid { grid-template-columns: 1fr; gap: 24px; }
        }

        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 35px; position: relative; margin-bottom: 30px; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        .section-title { font-size: 1.3rem; font-weight: 900; color: var(--text-main); margin-top: 0; margin-bottom: 25px; display: flex; align-items: center; gap: 10px; letter-spacing: -0.5px; border-bottom: 1px dashed var(--border-color); padding-bottom: 15px; }

        .avatar-container { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 15px; margin-bottom: 10px; }
        .profile-avatar-wrapper { position: relative; width: 140px; height: 140px; border-radius: 50%; overflow: hidden; background: var(--bg-light); border: 3px solid var(--border-color); box-shadow: var(--shadow-sm); transition: 0.3s; }
        .profile-avatar-wrapper img { width: 100%; height: 100%; object-fit: cover; }
        .profile-avatar-fallback { width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: rgba(16, 185, 129, 0.1); color: var(--primary); font-size: 4rem; font-weight: 900; }
        
        .upload-overlay { position: absolute; bottom: 0; left: 0; width: 100%; background: rgba(0, 0, 0, 0.6); padding: 8px 0; color: #fff; font-size: 0.75rem; font-weight: 800; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 5px; opacity: 0; }
        .profile-avatar-wrapper:hover .upload-overlay { opacity: 1; }

        .status-badge { display: inline-flex; align-items: center; gap: 6px; padding: 6px 14px; border-radius: 50px; font-weight: 800; font-size: 0.8rem; text-transform: uppercase; margin-top: 5px; text-decoration: none; }
        .status-badge.unverified { background: rgba(100, 116, 139, 0.1); color: #64748b; }
        .status-badge.pending { background: rgba(245, 158, 11, 0.1); color: #f59e0b; }
        .status-badge.verified { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .status-badge.rejected { background: rgba(239, 68, 68, 0.1); color: #ef4444; }

        .form-group { margin-bottom: 22px; }
        .form-label { display: block; font-weight: 800; margin-bottom: 8px; font-size: 0.95rem; color: var(--text-main); }
        .form-input { width: 100%; padding: 14px 18px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); border-radius: var(--radius-md); outline: none; font-size: 1rem; font-family: inherit; font-weight: 600; transition: 0.3s; }
        .form-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        .form-input:disabled { background: var(--border-color); opacity: 0.7; cursor: not-allowed; }

        .btn-submit { background: var(--primary); color: #fff; border: none; padding: 16px 25px; border-radius: var(--radius-md); font-weight: 900; font-size: 1.05rem; cursor: pointer; transition: 0.3s; display: inline-flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); font-family: inherit; width: 100%; }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }
        .btn-dark { background: #111827 !important; color: #ffffff !important; box-shadow: 0 4px 15px rgba(0,0,0,0.15) !important; }
        .btn-dark:hover { background: #1f2937 !important; box-shadow: 0 10px 25px rgba(0,0,0,0.2) !important; }
        [data-theme="dark"] .btn-dark { background: #334155 !important; color: #ffffff !important; }
        [data-theme="dark"] .btn-dark:hover { background: #475569 !important; }

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
        [data-theme="dark"] .form-input { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .form-input:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }

        body, .sidebar, .topbar, .bento-card, h1, h2, h3, p, span, div, .form-input { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

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
            <li><a href="profile.php" class="active"><i class="fa-solid fa-user"></i> <span>Profile</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">My Profile</div>
            
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
                                <?php while($n = $notes->fetch_assoc()): ?>
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

                <a href="profile.php" class="user-profile" style="border-left: none; display: flex; align-items: center; text-decoration: none;">
                    <div style="text-align: right; margin-right: 10px;" class="desktop-only-text">
                        <div style="font-weight: 800; font-size: 0.95rem;" class="logo-text"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">Farmer</div>
                    </div>
                    <?php if(!empty($profile_image) && file_exists("../uploads/profile/" . $profile_image)): ?>
                        <div class="avatar" style="width: 45px; height: 45px; border-radius: 50%; overflow: hidden; border: 1px solid rgba(16, 185, 129, 0.3);"><img src="../uploads/profile/<?php echo $profile_image; ?>" style="width:100%; height:100%; object-fit:cover;"></div>
                    <?php else: ?>
                        <div class="avatar" style="background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); font-weight: 900; font-size: 1.1rem; width: 45px; height: 45px; display: flex; align-items: center; justify-content: center; border-radius: 50%;"><?php echo $avatar_letter; ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="content-container">
            <?php echo $msg; ?>

            <div class="profile-grid">
                <!-- Left Column -->
                <div>
                    <!-- Avatar Bento Card -->
                    <div class="bento-card">
                        <form id="avatarForm" method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">
                            <!-- Preserve text inputs in avatar update if submitted via JS -->
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($farmer['name']); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($farmer['email']); ?>">
                            <input type="hidden" name="phone" value="<?php echo htmlspecialchars($farmer['phone']); ?>">
                            <input type="hidden" name="location" value="<?php echo htmlspecialchars($farmer['location']); ?>">
                            <input type="hidden" name="bio" value="<?php echo htmlspecialchars($farmer['bio'] ?? ''); ?>">

                            <div class="avatar-container">
                                <div class="profile-avatar-wrapper">
                                    <?php if(!empty($profile_image) && file_exists("../uploads/profile/" . $profile_image)): ?>
                                        <img id="avatarPreview" src="../uploads/profile/<?php echo $profile_image; ?>" alt="Avatar">
                                    <?php else: ?>
                                        <div id="avatarFallback" class="profile-avatar-fallback"><?php echo $avatar_letter; ?></div>
                                        <img id="avatarPreview" src="#" alt="Avatar" style="display: none;">
                                    <?php endif; ?>
                                    <label class="upload-overlay" for="profileImageInput">
                                        <i class="fa-solid fa-camera"></i> Change Photo
                                    </label>
                                    <input type="file" id="profileImageInput" name="profile_image" accept="image/*" style="display: none;">
                                </div>

                                <div style="margin-top: 5px;">
                                    <h2 style="margin: 0; font-size: 1.4rem; font-weight: 900; letter-spacing: -0.5px;" class="logo-text"><?php echo htmlspecialchars($farmer['name']); ?></h2>
                                    <p style="margin: 5px 0 10px 0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600;"><?php echo htmlspecialchars($farmer['email']); ?></p>
                                </div>

                                <div>
                                    <?php if($verification_status == 'verified'): ?>
                                        <span class="status-badge verified"><i class="fa-solid fa-shield-check"></i> Verified</span>
                                    <?php elseif($verification_status == 'pending'): ?>
                                        <a href="verify.php" class="status-badge pending"><i class="fa-solid fa-clock"></i> Pending Review</a>
                                    <?php elseif($verification_status == 'rejected'): ?>
                                        <a href="verify.php" class="status-badge rejected"><i class="fa-solid fa-circle-xmark"></i> Rejected - Fix</a>
                                    <?php else: ?>
                                        <a href="verify.php" class="status-badge unverified"><i class="fa-solid fa-shield-slash"></i> Unverified</a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>

                    <!-- Short Info / Stats Card -->
                    <div class="bento-card" style="padding: 25px;">
                        <h3 style="margin: 0 0 15px 0; font-size: 1.1rem; font-weight: 800;" class="logo-text">Overview</h3>
                        <div style="display: flex; flex-direction: column; gap: 12px; font-size: 0.95rem; font-weight: 600;">
                            <div style="display: flex; justify-content: space-between; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                                <span class="text-muted">Member Since</span>
                                <span class="logo-text"><?php echo date('M Y', strtotime($farmer['created_at'])); ?></span>
                            </div>
                            <div style="display: flex; justify-content: space-between;">
                                <span class="text-muted">Verification Status</span>
                                <span style="text-transform: capitalize;" class="logo-text"><?php echo $verification_status; ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <!-- Profile details Card -->
                    <div class="bento-card">
                        <h2 class="section-title"><i class="fa-solid fa-address-card" style="color: var(--primary);"></i> Profile Details</h2>
                        
                        <form method="POST" action="">
                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Full Name *</label>
                                    <input type="text" class="form-input" name="name" value="<?php echo htmlspecialchars($farmer['name']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Phone Number *</label>
                                    <input type="text" class="form-input" name="phone" value="<?php echo htmlspecialchars($farmer['phone']); ?>" required>
                                </div>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">Email Address *</label>
                                    <input type="email" class="form-input" name="email" value="<?php echo htmlspecialchars($farmer['email']); ?>" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Location (State/Region) *</label>
                                    <input type="text" class="form-input" name="location" value="<?php echo htmlspecialchars($farmer['location']); ?>" required>
                                </div>
                            </div>

                            <div class="form-group">
                                <label class="form-label">About / Farm Description</label>
                                <textarea class="form-input" name="bio" rows="4" style="resize: vertical; font-weight: 500;" placeholder="Tell us about your farm, experience, crops grown..."><?php echo htmlspecialchars($farmer['bio'] ?? ''); ?></textarea>
                            </div>

                            <div style="text-align: right; margin-top: 10px;">
                                <button type="submit" name="update_profile" class="btn-submit" style="width: auto; min-width: 200px;">
                                    <i class="fa-solid fa-floppy-disk"></i> Save Profile Details
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- Security Details Card -->
                    <div class="bento-card">
                        <h2 class="section-title"><i class="fa-solid fa-lock" style="color: var(--primary);"></i> Change Password</h2>
                        
                        <form method="POST" action="">
                            <div class="form-group">
                                <label class="form-label">Current Password</label>
                                <input type="password" class="form-input" name="current_password" required>
                            </div>

                            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                                <div class="form-group">
                                    <label class="form-label">New Password</label>
                                    <input type="password" class="form-input" name="new_password" required>
                                </div>
                                <div class="form-group">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-input" name="confirm_password" required>
                                </div>
                            </div>

                            <div style="text-align: right; margin-top: 10px;">
                                <button type="submit" name="update_password" class="btn-submit btn-dark" style="width: auto; min-width: 200px;">
                                    <i class="fa-solid fa-key"></i> Update Password
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
// Image Preview and Auto-Submit Form
const fileInput = document.getElementById('profileImageInput');
const avatarPreview = document.getElementById('avatarPreview');
const avatarFallback = document.getElementById('avatarFallback');
const avatarForm = document.getElementById('avatarForm');

if (fileInput) {
    fileInput.addEventListener('change', function() {
        if (this.files && this.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                if (avatarPreview) {
                    avatarPreview.src = e.target.result;
                    avatarPreview.style.display = 'block';
                }
                if (avatarFallback) {
                    avatarFallback.style.display = 'none';
                }
                
                // Automatically submit the avatar update
                avatarForm.submit();
            }
            reader.readAsDataURL(this.files[0]);
        }
    });
}

// Notification Dropdown logic
const notifToggleBtn = document.getElementById('notifToggle');
const notifDropdown = document.getElementById('notifDropdown');
const notifBadge = document.getElementById('notifBadge');
if(notifToggleBtn && notifDropdown) {
    notifToggleBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        if(notifDropdown.style.display === 'none' || notifDropdown.style.display === '') {
            notifDropdown.style.display = 'block';
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

// Theme persistence and toggling
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

            fetch('../api/update_theme.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'theme=' + targetTheme
            });
        });
    }
});
</script>

<script src="../assets/js/translate.js"></script>

</body>
</html>
