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

$msg = "";
/* Handle Badge Update (Only applies to Farmers) */
if(isset($_POST['update_badge'])){
    $uid = intval($_POST['user_id']);
    $badge = $_POST['badge'];
    if($conn->query("UPDATE users SET badge='$badge' WHERE id=$uid")) {
        $msg = "<div class='alert-msg success'><i class='fa-solid fa-check-circle'></i> Badge updated successfully.</div>";
    } else {
        $msg = "<div class='alert-msg error'><i class='fa-solid fa-triangle-exclamation'></i> Failed to update badge.</div>";
    }
}

/* Handle Verification Approval/Rejection */
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['verify_user'])){
    $uid = intval($_POST['user_id']);
    $action = $_POST['action']; // 'approve' or 'reject'
    $remarks = $conn->real_escape_string(trim($_POST['remarks']));
    
    if($action === 'approve') {
        if($conn->query("UPDATE users SET verification_status='verified', badge='verified', verification_remarks=NULL WHERE id=$uid")) {
            $msg = "<div class='alert-msg success'><i class='fa-solid fa-check-circle'></i> Farmer approved and verified successfully.</div>";
        } else {
            $msg = "<div class='alert-msg error'><i class='fa-solid fa-triangle-exclamation'></i> Database Error: Failed to approve farmer.</div>";
        }
    } elseif($action === 'reject') {
        if($conn->query("UPDATE users SET verification_status='rejected', badge='none', verification_remarks='$remarks' WHERE id=$uid")) {
            $msg = "<div class='alert-msg error'><i class='fa-solid fa-ban'></i> Farmer verification request rejected.</div>";
        } else {
            $msg = "<div class='alert-msg error'><i class='fa-solid fa-triangle-exclamation'></i> Database Error: Failed to reject farmer.</div>";
        }
    }
}

/* Fetch users and separate them into arrays */
$result = $conn->query("
    SELECT users.*, 
    (SELECT AVG(rating) FROM reviews WHERE farmer_id = users.id) as avg_rating,
    (SELECT COUNT(id) FROM reviews WHERE farmer_id = users.id) as review_count
    FROM users 
    WHERE role != 'admin'
    ORDER BY created_at DESC
");

$farmers = [];
$buyers = [];
$pending_verifications = [];

while($row = $result->fetch_assoc()){
    if($row['role'] == 'farmer') {
        $farmers[] = $row;
        if($row['verification_status'] == 'pending') {
            $pending_verifications[] = $row;
        }
    } elseif ($row['role'] == 'buyer') {
        $buyers[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Users | Admin | Farmogram</title>
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
           BENTO UI - ADMIN MANAGE USERS DESIGN
        ========================================================= */
        * { box-sizing: border-box; }
        
        .content-container { padding: 40px 5%; max-width: 1600px; width: 100%; margin: 0 auto; overflow-x: hidden; }

        /* Bento Card Wrapper */
        .bento-card { background: var(--card-bg); border-radius: 28px; border: 1px solid var(--border-color); box-shadow: 0 10px 40px -10px rgba(0,0,0,0.05); overflow: hidden; transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1); }
        .bento-card:hover { box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08); }

        /* Modern Tabs */
        .tabs-header { display: flex; gap: 20px; border-bottom: 2px solid var(--border-color); padding: 0 35px; background: var(--bg-light); transition: 0.3s; overflow-x: auto; white-space: nowrap; -webkit-overflow-scrolling: touch; }
        .tabs-header::-webkit-scrollbar { display: none; } /* Hide scrollbar for clean look */
        
        .tab-btn { background: transparent; border: none; padding: 25px 15px; font-size: 1.05rem; font-weight: 800; color: var(--text-muted); cursor: pointer; position: relative; transition: var(--transition); border-bottom: 3px solid transparent; margin-bottom: -2px; display: inline-flex; align-items: center; gap: 10px; font-family: inherit; }
        .tab-btn:hover { color: var(--text-main); }
        .tab-btn.active { color: var(--primary); border-bottom-color: var(--primary); }
        
        .count-badge { background: var(--card-bg); padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; border: 1px solid var(--border-color); color: var(--text-main); font-weight: 800; transition: 0.3s; }
        .tab-btn.active .count-badge { background: var(--primary); color: #fff; border-color: var(--primary); }

        .tab-content { display: none; animation: fadeIn 0.4s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(5px); } to { opacity: 1; transform: translateY(0); } }

        /* Premium Data Tables */
        .table-responsive { overflow-x: auto; -webkit-overflow-scrolling: touch; width: 100%; }
        .table-wrapper { width: 100%; border-collapse: collapse; text-align: left; }
        
        .table-wrapper th { padding: 20px 35px; border-bottom: 2px solid var(--border-color); color: var(--text-muted); font-size: 0.85rem; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; background: var(--card-bg); white-space: nowrap; transition: 0.3s; }
        
        .table-wrapper td { padding: 25px 35px; border-bottom: 1px dashed var(--border-color); font-size: 1rem; vertical-align: middle; color: var(--text-main); transition: 0.3s; white-space: nowrap; }
        .table-wrapper tr:last-child td { border-bottom: none; }
        .table-wrapper tr:hover td { background: var(--bg-light); }
        
        .user-details strong { font-size: 1.15rem; font-weight: 900; letter-spacing: -0.5px; transition: 0.3s; }
        .user-details span { font-size: 0.9rem; color: var(--text-muted); margin-top: 4px; display: inline-block; font-weight: 500; transition: 0.3s; }
        
        /* Form & Action Elements */
        .badge-select { padding: 12px 16px; border-radius: 12px; border: 2px solid var(--border-color); font-size: 0.95rem; font-weight: 700; cursor: pointer; outline: none; background: var(--bg-light); color: var(--text-main); font-family: inherit; transition: 0.3s; }
        .badge-select:hover, .badge-select:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        
        .btn-delete { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); padding: 12px 20px; border-radius: 12px; font-size: 0.95rem; font-weight: 800; cursor: pointer; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: var(--transition); }
        .btn-delete:hover { background: #ef4444; color: #fff; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }

        /* Custom Logo Switcher Styles */
        .logo-light { display: block; }
        .logo-dark { display: none; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

        /* THEME BUTTON */
        .theme-btn { background: transparent; border: 2px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); }
        
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 800; color: var(--text-main); transition: color 0.3s ease; }

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
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.3) !important; border: 1px solid var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] th { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* UI Overrides */
        [data-theme="dark"] .tabs-header { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .table-wrapper th { background: var(--sidebar-bg) !important; }
        [data-theme="dark"] .badge-select { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .badge-select:hover, [data-theme="dark"] .badge-select:focus { border-color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .tabs-header, .tab-btn, .table-wrapper th, .table-wrapper td, h1, h2, h3, p, span, div, strong { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease; }

        /* PREMIUM MODAL STYLES */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(15, 23, 42, 0.7); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 2000; justify-content: center; align-items: center; padding: 20px; opacity: 0; transition: opacity 0.3s ease; }
        .modal-overlay.show { display: flex; opacity: 1; }
        .modal-content { background: var(--card-bg); width: 100%; max-width: 950px; border-radius: 28px; padding: 40px; position: relative; max-height: 90vh; overflow-y: auto; border: 1px solid var(--border-color); box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); transform: translateY(20px); transition: transform 0.4s cubic-bezier(0.4, 0, 0.2, 1); }
        .modal-overlay.show .modal-content { transform: translateY(0); }
        
        .modal-close { position: absolute; top: 25px; right: 25px; font-size: 1.5rem; cursor: pointer; color: var(--text-muted); transition: 0.3s; background: var(--bg-light); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; border: 1px solid var(--border-color); }
        .modal-close:hover { background: #ef4444; color: #fff; border-color: #ef4444; transform: rotate(90deg); }
        
        .modal-header-title { margin: 0 0 30px 0; border-bottom: 2px dashed var(--border-color); padding-bottom: 20px; font-size: 1.8rem; font-weight: 900; color: var(--text-main); display: flex; align-items: center; gap: 12px; letter-spacing: -0.5px; transition: 0.3s; }
        
        .case-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; }
        .case-section { background: var(--bg-light); padding: 30px; border-radius: 16px; border: 1px solid var(--border-color); transition: 0.3s; }
        .case-section h3 { margin: 0 0 20px 0; font-size: 1.3rem; color: var(--text-main); font-weight: 900; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        
        .case-label { font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 800; margin-bottom: 5px; transition: 0.3s; letter-spacing: 0.5px; }
        .case-val { font-size: 1.1rem; font-weight: 700; color: var(--text-main); margin-bottom: 20px; transition: 0.3s; }
        
        .evidence-img { width: 100%; height: 220px; object-fit: cover; border-radius: 12px; border: 1px solid var(--border-color); cursor: pointer; transition: 0.3s; box-shadow: 0 8px 30px rgba(0,0,0,0.04); }
        .evidence-img:hover { transform: scale(1.02); border-color: var(--primary); }
        
        .action-box { background: var(--card-bg); padding: 30px; border: 2px dashed var(--border-color); border-radius: 16px; margin-top: 30px; transition: 0.3s; }
        .action-textarea { width: 100%; padding: 18px; border: 2px solid var(--border-color); border-radius: 12px; font-family: inherit; font-size: 1rem; font-weight: 600; resize: vertical; margin-bottom: 20px; background: var(--bg-light); color: var(--text-main); transition: 0.3s; outline: none; }
        .action-textarea:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        
        .action-btn { padding: 18px 25px; border: none; border-radius: 12px; font-weight: 900; cursor: pointer; color: #fff; font-size: 1.1rem; flex: 1; transition: 0.3s; display: inline-flex; align-items: center; justify-content: center; gap: 8px; font-family: inherit; }
        .action-btn:hover { transform: translateY(-2px); box-shadow: 0 8px 15px rgba(0,0,0,0.2); }

        /* Alerts */
        .alert-msg { padding: 18px 20px; border-radius: 16px; margin-bottom: 25px; font-weight: 800; font-size: 1.05rem; display: flex; align-items: center; gap: 10px; box-shadow: 0 8px 30px rgba(0,0,0,0.04); transition: 0.3s; }
        .alert-msg.success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }
        .alert-msg.error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }

        .btn-view { display: inline-flex; align-items: center; gap: 8px; background: var(--primary); color: #fff; padding: 12px 20px; border-radius: 12px; text-decoration: none; font-size: 0.95rem; font-weight: 800; cursor: pointer; border: none; transition: 0.3s; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); font-family: inherit; }
        .btn-view:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 15px rgba(16, 185, 129, 0.3); }

        /* =========================================================
           RESPONSIVE RULES
        ========================================================= */
        @media (max-width: 768px) {
            .content-container { padding: 25px 20px; }
            .bento-card { border-radius: 20px; }
            .tabs-header { padding: 0 20px; }
            .tab-btn { padding: 20px 10px; font-size: 0.95rem; }
            .table-wrapper th, .table-wrapper td { padding: 20px 25px; }
            .user-details strong { font-size: 1.05rem; }
            .badge-select { padding: 10px; font-size: 0.85rem; }
            .btn-delete { padding: 10px 15px; font-size: 0.85rem; }
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
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Overview</a></li>
            <li><a href="users.php" class="active"><i class="fa-solid fa-users"></i> Manage Users</a></li>
            <li><a href="orders.php"><i class="fa-solid fa-boxes-stacked"></i> All Orders</a></li>
            <li><a href="analytics.php"><i class="fa-solid fa-chart-line"></i> Analytics</a></li>
            <li><a href="claims.php"><i class="fa-solid fa-file-shield"></i> Disaster Claims</a></li>
            <li><a href="../auth/logout.php" class="logout-btn"><i class="fa-solid fa-arrow-right-from-bracket"></i> Logout</a></li>
        </ul>
    </aside>

    <main class="main-wrapper">
        <header class="topbar" style="border: none;">
            <div class="page-title logo-text" style="font-size: 1.6rem;">Trust & User Management</div>
            <div class="topbar-actions">
                
                <button class="theme-btn" id="themeToggle" title="Toggle Theme" style="margin-right: 15px;">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <div class="user-profile" style="border-left: none;">
                    <div style="text-align: right; margin-right: 10px;">
                        <div style="font-weight: 700; font-size: 1rem;" class="logo-text"><?php echo htmlspecialchars($user_name); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.8rem;">System Admin</div>
                    </div>
                    <div class="avatar" style="background: rgba(239, 68, 68, 0.15); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3);"><?php echo $avatar_letter; ?></div>
                </div>
            </div>
        </header>

        <div class="content-container">
            <?php if(!empty($msg)) echo $msg; ?>
            <div class="bento-card" style="padding: 0;">
                
                <div class="tabs-header">
                    <button class="tab-btn active" onclick="switchTab('tab-farmers', event)">
                        <i class="fa-solid fa-wheat-awn" style="color: #10b981;"></i> Registered Farmers 
                        <span class="count-badge"><?php echo count($farmers); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('tab-buyers', event)">
                        <i class="fa-solid fa-cart-shopping" style="color: #3b82f6;"></i> Registered Buyers 
                        <span class="count-badge"><?php echo count($buyers); ?></span>
                    </button>
                    <button class="tab-btn" onclick="switchTab('tab-verifications', event)">
                        <i class="fa-solid fa-shield-halved" style="color: #f59e0b;"></i> Verification Requests 
                        <span class="count-badge"><?php echo count($pending_verifications); ?></span>
                    </button>
                </div>

                <div id="tab-farmers" class="tab-content active">
                    <div class="table-responsive">
                        <table class="table-wrapper">
                            <thead>
                                <tr>
                                    <th>Farmer Details</th>
                                    <th>Platform Rating</th>
                                    <th>Assign Quality Badge</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($farmers) > 0): ?>
                                    <?php foreach($farmers as $farmer): ?>
                                    <tr>
                                        <td class="user-details">
                                            <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($farmer['name']); ?></strong><br>
                                            <span><i class="fa-solid fa-envelope" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($farmer['email']); ?></span>
                                        </td>
                                        <td>
                                            <?php if($farmer['review_count'] > 0): ?>
                                                <div style="font-weight: 900; color: var(--text-main); font-size: 1.2rem; letter-spacing: -0.5px;">
                                                    <?php echo round($farmer['avg_rating'], 1); ?> <i class="fa-solid fa-star" style="color: #f59e0b; font-size: 1rem;"></i>
                                                </div>
                                                <div style="font-size: 0.85rem; color: var(--text-muted); margin-top: 4px; font-weight: 600;">
                                                    Based on <?php echo $farmer['review_count']; ?> reviews
                                                </div>
                                            <?php else: ?>
                                                <span style="color: var(--text-muted); font-size: 0.9rem; font-weight: 600;">No reviews yet</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <form method="POST">
                                                <input type="hidden" name="user_id" value="<?php echo $farmer['id']; ?>">
                                                <input type="hidden" name="update_badge" value="1">
                                                <select name="badge" class="badge-select" onchange="this.form.submit()">
                                                    <option value="none" <?php if($farmer['badge']=='none') echo 'selected'; ?>>No Badge Assigned</option>
                                                    <option value="verified" <?php if($farmer['badge']=='verified') echo 'selected'; ?>>🔵 Verified Farm</option>
                                                    <option value="organic" <?php if($farmer['badge']=='organic') echo 'selected'; ?>>🌿 Organic Certified</option>
                                                    <option value="top_rated" <?php if($farmer['badge']=='top_rated') echo 'selected'; ?>>⭐ Top Rated Seller</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td>
                                            <a href="delete_user.php?id=<?php echo $farmer['id']; ?>" class="btn-delete" onclick="return confirm('WARNING: Are you sure you want to delete this Farmer? This cannot be undone.')">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding: 60px; color: var(--text-muted); font-weight: 600; font-size: 1.1rem;">No farmers registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-buyers" class="tab-content">
                    <div class="table-responsive">
                        <table class="table-wrapper">
                            <thead>
                                <tr>
                                    <th>Buyer Details</th>
                                    <th>Account Created</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($buyers) > 0): ?>
                                    <?php foreach($buyers as $buyer): ?>
                                    <tr>
                                        <td class="user-details">
                                            <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($buyer['name']); ?></strong><br>
                                            <span><i class="fa-solid fa-envelope" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($buyer['email']); ?></span>
                                        </td>
                                        <td>
                                            <div style="color: var(--text-main); font-weight: 700;">
                                                <i class="fa-regular fa-calendar" style="color: var(--primary); margin-right: 5px;"></i> <?php echo date('M j, Y', strtotime($buyer['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="delete_user.php?id=<?php echo $buyer['id']; ?>" class="btn-delete" onclick="return confirm('WARNING: Are you sure you want to delete this Buyer? This cannot be undone.')">
                                                <i class="fa-solid fa-trash-can"></i> Delete
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="3" style="text-align:center; padding: 60px; color: var(--text-muted); font-weight: 600; font-size: 1.1rem;">No buyers registered yet.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div id="tab-verifications" class="tab-content">
                    <div class="table-responsive">
                        <table class="table-wrapper">
                            <thead>
                                <tr>
                                    <th>Farmer Details</th>
                                    <th>Submitted At</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if(count($pending_verifications) > 0): ?>
                                    <?php foreach($pending_verifications as $pending): ?>
                                    <tr>
                                        <td class="user-details">
                                            <strong style="text-transform: capitalize;"><?php echo htmlspecialchars($pending['name']); ?></strong><br>
                                            <span><i class="fa-solid fa-envelope" style="margin-right: 4px;"></i> <?php echo htmlspecialchars($pending['email']); ?></span>
                                        </td>
                                        <td>
                                            <div style="color: var(--text-main); font-weight: 700;">
                                                <i class="fa-regular fa-clock" style="color: #f59e0b; margin-right: 5px;"></i> <?php echo date('M j, Y, g:i a', strtotime($pending['verification_submitted_at'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="status-badge" style="background: rgba(245, 158, 11, 0.15); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); padding: 6px 12px; border-radius: 12px; font-size: 0.85rem; font-weight: 800;">PENDING</span>
                                        </td>
                                        <td>
                                            <button class="btn-view" style="background: #f59e0b; box-shadow: 0 4px 10px rgba(245, 158, 11, 0.2);" onclick='openVerifyModal(<?php echo htmlspecialchars(json_encode($pending), ENT_QUOTES, 'UTF-8'); ?>)'>
                                                <i class="fa-solid fa-folder-open"></i> Review Request
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" style="text-align:center; padding: 60px; color: var(--text-muted); font-weight: 600; font-size: 1.1rem;">No pending verification requests.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="verifyModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeVerifyModal()"><i class="fa-solid fa-xmark"></i></button>
        <h2 class="modal-header-title"><i class="fa-solid fa-shield-halved" style="color: #f59e0b;"></i> Verify Farmer Profile <span id="mv_id" style="color: var(--text-muted); font-weight: 600; font-size: 1.2rem; margin-left: auto;"></span></h2>
        
        <div class="case-grid">
            <div class="case-section">
                <h3><i class="fa-solid fa-user-check" style="color: #3b82f6;"></i> Applicant Info</h3>
                <div class="case-label">Farmer Name</div>
                <div class="case-val" id="mv_farmer"></div>
                
                <div class="case-label">Email Address</div>
                <div class="case-val" id="mv_email"></div>
                
                <div class="case-label">Phone Number</div>
                <div class="case-val" id="mv_phone"></div>

                <div class="case-label">Location / Farm Address</div>
                <div class="case-val" id="mv_location"></div>

                <div class="case-label">Submitted On</div>
                <div class="case-val" id="mv_date"></div>
            </div>
            
            <div class="case-section">
                <h3><i class="fa-solid fa-folder-open" style="color: #8b5cf6;"></i> Uploaded Documents</h3>
                
                <div class="case-label">1. Government Identity Card</div>
                <div style="margin-bottom: 20px;">
                    <a id="link_id_doc" href="#" target="_blank" style="text-decoration: none;">
                        <span id="id_doc_pdf_name" style="display:none; font-weight:700; color:var(--primary);"><i class="fa-solid fa-file-pdf"></i> View ID PDF Document</span>
                        <img id="img_id_doc" class="evidence-img" src="" alt="Identity Document">
                    </a>
                </div>
                
                <div class="case-label">2. Land Ownership / Deed Document</div>
                <div>
                    <a id="link_land_doc" href="#" target="_blank" style="text-decoration: none;">
                        <span id="land_doc_pdf_name" style="display:none; font-weight:700; color:var(--primary);"><i class="fa-solid fa-file-pdf"></i> View Land Deed PDF Document</span>
                        <img id="img_land_doc" class="evidence-img" src="" alt="Land Document">
                    </a>
                </div>
            </div>
        </div>

        <div class="action-box" id="verifyActionArea">
            <form method="POST" action="users.php">
                <input type="hidden" name="user_id" id="mv_form_id">
                <input type="hidden" name="verify_user" value="1">
                
                <div style="margin-bottom: 20px;">
                    <label class="case-label" style="display: block; margin-bottom: 10px;">Admin Remarks / Feedback (Visible to Farmer if Rejected)</label>
                    <textarea name="remarks" class="action-textarea" rows="3" placeholder="Explain reasoning for rejection or add verification observations..."></textarea>
                </div>
                <div style="display: flex; gap: 20px;">
                    <button type="submit" name="action" value="approve" class="action-btn" style="background: #10b981; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);" onclick="return confirm('Are you sure you want to APPROVE this farmer? This will grant them crop listing and sale permissions.');"><i class="fa-solid fa-circle-check"></i> Approve & Verify</button>
                    <button type="submit" name="action" value="reject" class="action-btn" style="background: #ef4444; box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);" onclick="return confirm('Are you sure you want to REJECT this verification request?');"><i class="fa-solid fa-circle-xmark"></i> Reject Request</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function switchTab(tabId, event) {
    // Hide all contents
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    // Remove active class from all buttons
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    
    // Show target content and highlight button
    document.getElementById(tabId).classList.add('active');
    event.currentTarget.classList.add('active');
}

function openVerifyModal(data) {
    document.getElementById('mv_id').innerText = "#FMR-" + data.id;
    document.getElementById('mv_form_id').value = data.id;
    document.getElementById('mv_farmer').innerText = data.name;
    document.getElementById('mv_email').innerText = data.email;
    document.getElementById('mv_phone').innerText = data.phone || 'N/A';
    document.getElementById('mv_location').innerText = data.location || 'N/A';
    document.getElementById('mv_date').innerText = data.verification_submitted_at || 'N/A';
    
    // Set Document files
    let idDoc = data.identity_doc;
    let landDoc = data.verification_doc;
    
    let idDocPath = "../uploads/verifications/" + idDoc;
    let landDocPath = "../uploads/verifications/" + landDoc;
    
    // Check if ID Document is a PDF
    if (idDoc && idDoc.toLowerCase().endsWith('.pdf')) {
        document.getElementById('img_id_doc').style.display = 'none';
        document.getElementById('id_doc_pdf_name').style.display = 'block';
    } else {
        document.getElementById('img_id_doc').style.display = 'block';
        document.getElementById('img_id_doc').src = idDoc ? idDocPath : '';
        document.getElementById('id_doc_pdf_name').style.display = 'none';
    }
    document.getElementById('link_id_doc').href = idDoc ? idDocPath : '#';

    // Check if Land Document is a PDF
    if (landDoc && landDoc.toLowerCase().endsWith('.pdf')) {
        document.getElementById('img_land_doc').style.display = 'none';
        document.getElementById('land_doc_pdf_name').style.display = 'block';
    } else {
        document.getElementById('img_land_doc').style.display = 'block';
        document.getElementById('img_land_doc').src = landDoc ? landDocPath : '';
        document.getElementById('land_doc_pdf_name').style.display = 'none';
    }
    document.getElementById('link_land_doc').href = landDoc ? landDocPath : '#';

    let modal = document.getElementById('verifyModal');
    modal.style.display = 'flex';
    setTimeout(() => { modal.classList.add('show'); }, 10);
}

function closeVerifyModal() {
    let modal = document.getElementById('verifyModal');
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

// Close modal if clicked outside of content
document.addEventListener('DOMContentLoaded', () => {
    const vModal = document.getElementById('verifyModal');
    if(vModal) {
        vModal.addEventListener('click', function(e) {
            if (e.target === this) {
                closeVerifyModal();
            }
        });
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

</body>
</html>