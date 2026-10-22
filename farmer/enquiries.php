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

// Check if a specific chat is selected
$active_enquiry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Mark messages as read as soon as the farmer opens this chat
if($active_enquiry_id > 0){
    $conn->query("UPDATE enquiry_messages SET is_read = 1 WHERE enquiry_id = $active_enquiry_id AND sender_id != $farmer_id");
}

// HANDLE NEW MESSAGE SUBMISSION
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])){
    $enq_id = (int)$_POST['enquiry_id'];
    $message = $conn->real_escape_string($_POST['message']);
    if(!empty($message)){
        $conn->query("INSERT INTO enquiry_messages (enquiry_id, sender_id, message) VALUES ($enq_id, $farmer_id, '$message')");
    }
    header("Location: enquiries.php?id=$enq_id");
    exit();
}

// Fetch all chats WITH WhatsApp Sorting (Most recent message at top) AND Unread Counts
$contacts = $conn->query("
    SELECT e.id AS enquiry_id, u.name AS buyer_name, c.crop_name, c.image,
           (SELECT MAX(created_at) FROM enquiry_messages WHERE enquiry_id = e.id) as last_msg_time,
           (SELECT COUNT(*) FROM enquiry_messages WHERE enquiry_id = e.id AND sender_id != $farmer_id AND is_read = 0) as unread_count
    FROM enquiries e 
    JOIN users u ON e.buyer_id = u.id 
    JOIN crops c ON e.crop_id = c.id 
    WHERE e.farmer_id = $farmer_id 
    ORDER BY last_msg_time DESC
");

$chat_history = null;
$active_contact_name = "";
$active_crop_name = "";

if($active_enquiry_id > 0){
    $header_res = $conn->query("SELECT u.name AS buyer_name, c.crop_name FROM enquiries e JOIN users u ON e.buyer_id = u.id JOIN crops c ON e.crop_id = c.id WHERE e.id = $active_enquiry_id");
    if($header_res->num_rows > 0){
        $header_data = $header_res->fetch_assoc();
        $active_contact_name = $header_data['buyer_name'];
        $active_crop_name = $header_data['crop_name'];
        $chat_history = $conn->query("SELECT m.*, u.name AS sender_name FROM enquiry_messages m JOIN users u ON m.sender_id = u.id WHERE m.enquiry_id = $active_enquiry_id ORDER BY m.created_at ASC");
    }
}

// HANDLE SENDING CUSTOM OFFER
if(isset($_POST['send_offer'])) {
    $enq_id = (int)$_POST['enquiry_id'];
    $price = (float)$_POST['custom_price'];
    $qty = (int)$_POST['custom_quantity'];
    
    // Update the enquiry table with the new offer
    $conn->query("UPDATE enquiries SET custom_price = $price, custom_quantity = $qty, offer_status = 'sent' WHERE id = $enq_id");
    
    // Auto-send a message to the chat
    $conn->query("INSERT INTO enquiry_messages (enquiry_id, sender_id, message) VALUES ($enq_id, $farmer_id, 'I have sent a custom offer for $qty kg at ₹$price/kg. Please accept it above to proceed to checkout.')");
    
    header("Location: enquiries.php?id=$enq_id");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Messages & Negotiations | Farmogram</title>
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
        .content-container { flex: 1; display: flex; flex-direction: column; padding: 25px 40px; max-width: 1600px; margin: 0 auto; width: 100%; min-height: 0; }

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

        /* CHAT LAYOUT */
        .chat-layout { 
            flex: 1;
            display: flex; 
            min-height: 0; /* Critical for flex scrolling */
            background: var(--card-bg); 
            border-radius: var(--radius-xl); 
            border: 1px solid var(--border-color); 
            box-shadow: var(--shadow-subtle); 
            overflow: hidden; 
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        /* SIDEBAR */
        .chat-sidebar { width: 350px; background: var(--card-bg); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; transition: 0.3s; }
        .chat-sidebar-header { padding: 25px 20px; border-bottom: 1px solid var(--border-color); font-weight: 900; font-size: 1.25rem; color: var(--text-main); background: var(--bg-light); transition: 0.3s; letter-spacing: -0.5px; }
        .contact-list { overflow-y: auto; flex: 1; }
        
        .contact-item { display: flex; gap: 15px; padding: 18px 20px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: 0.2s; text-decoration: none; color: inherit; align-items: center; }
        .contact-item:hover { background: rgba(16, 185, 129, 0.05); }
        .contact-item.active { background: rgba(16, 185, 129, 0.1); border-left: 4px solid var(--primary); padding-left: 16px; }
        
        .contact-avatar { width: 50px; height: 50px; border-radius: var(--radius-md); object-fit: cover; border: 2px solid var(--border-color); transition: 0.3s; }
        .contact-info { flex: 1; overflow: hidden; }
        
        .contact-name-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .contact-name { font-weight: 900; color: var(--text-main); font-size: 1.05rem; transition: 0.3s; }
        .unread-badge { background: #ef4444; color: #fff; font-size: 0.75rem; font-weight: 800; padding: 2px 8px; border-radius: 20px; box-shadow: 0 2px 5px rgba(239,68,68,0.3); }
        
        .contact-crop { font-size: 0.85rem; color: var(--text-muted); text-transform: capitalize; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; transition: 0.3s; }

        /* CHAT WINDOW */
        .chat-window { flex: 1; display: flex; flex-direction: column; background: var(--bg-light); position: relative; transition: 0.3s; min-width: 0; }
        .chat-header { padding: 18px 25px; background: var(--card-bg); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 15px; z-index: 10; transition: 0.3s; }
        .chat-header-name { font-size: 1.25rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .chat-header-status { font-size: 0.85rem; color: var(--text-muted); font-weight: 700; transition: 0.3s; display: flex; align-items: center; gap: 5px; }

        /* MESSAGES */
        .chat-messages { flex: 1; padding: 25px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; z-index: 1; }
        .msg-bubble { max-width: 70%; padding: 14px 20px; font-size: 1rem; line-height: 1.5; position: relative; border-radius: 20px; font-weight: 500; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .msg-time { font-size: 0.75rem; opacity: 0.8; text-align: right; margin-top: 8px; font-weight: 700; }

        .msg-me { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 6px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25); }
        .msg-other { align-self: flex-start; background: var(--card-bg); color: var(--text-main); border-bottom-left-radius: 6px; border: 1px solid var(--border-color); }

        /* CUSTOM OFFER BOX */
        .offer-box { background: rgba(59, 130, 246, 0.05); padding: 20px 25px; border-top: 1px solid var(--border-color); border-bottom: 1px solid var(--border-color); transition: 0.3s; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 15px; }
        .offer-input { flex: 1; min-width: 120px; padding: 12px 18px; border-radius: var(--radius-md); border: 2px solid var(--border-color); outline: none; font-family: inherit; font-size: 0.95rem; font-weight: 700; background: var(--card-bg); color: var(--text-main); transition: 0.3s; }
        .offer-input:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); }
        .btn-offer { background: #3b82f6; color: #fff; border: none; padding: 14px 24px; border-radius: var(--radius-md); cursor: pointer; font-weight: 800; font-size: 1rem; transition: 0.3s; display: flex; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(59, 130, 246, 0.2); }
        .btn-offer:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 15px rgba(59, 130, 246, 0.3); }

        /* INPUT AREA */
        .chat-input-area { padding: 20px 25px; background: var(--card-bg); display: flex; gap: 15px; align-items: center; z-index: 10; transition: 0.3s; }
        .chat-input { flex: 1; padding: 16px 20px; border-radius: 50px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); outline: none; font-size: 1rem; font-family: inherit; font-weight: 500; transition: 0.3s; }
        .chat-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        .btn-send { width: 55px; height: 55px; border-radius: 50%; background: var(--primary); color: #fff; border: none; cursor: pointer; font-size: 1.2rem; transition: 0.3s; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); flex-shrink: 0; }
        .btn-send:hover { background: var(--primary-hover); transform: translateY(-2px); }

        /* EMPTY STATE */
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; color: var(--text-muted); background: var(--bg-light); transition: 0.3s; text-align: center; padding: 20px; }

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
        [data-theme="dark"] .chat-layout { box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .chat-header-name, [data-theme="dark"] .contact-name, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Chat Specific Dark Mode Overrides */
        [data-theme="dark"] .contact-item.active { background: rgba(16, 185, 129, 0.15) !important; }
        [data-theme="dark"] .chat-sidebar-header { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important;}
        [data-theme="dark"] .offer-box { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important;}
        [data-theme="dark"] .msg-other { background: var(--sidebar-bg) !important; border-color: rgba(255,255,255,0.05) !important; }
        [data-theme="dark"] .chat-input, [data-theme="dark"] .offer-input { background: var(--bg-light) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .chat-input:focus, [data-theme="dark"] .offer-input:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        
        /* Smooth Transitions */
        body, .sidebar, .topbar, .chat-layout, h1, h2, h3, p, span, div, .chat-input, .offer-input { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        .mobile-back { display: none; }

        @media (max-width: 768px) {
            .app-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
            
            /* Hide Desktop Logo */
            .sidebar-logo { display: none; } 
            
            /* Show Mobile Logo in Topbar */
            .mobile-brand { display: flex; flex: 1; }
            
            /* Topbar Redesign for Mobile */
            .topbar { padding: 10px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
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

            /* 100dvh EXACT CHAT SIZING */
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 10px; flex: 1; display: flex; flex-direction: column; min-height: 0; }
            
            .chat-layout { flex: 1; border-radius: var(--radius-md); box-shadow: none; }

            /* View Swapping Logic */
            .chat-sidebar { display: <?php echo $active_enquiry_id > 0 ? 'none' : 'flex'; ?>; width: 100%; border-right: none; }
            .chat-window { display: <?php echo $active_enquiry_id > 0 ? 'flex' : 'none'; ?>; width: 100%; }
            
            .mobile-back { display: flex; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: var(--bg-light); margin-right: 10px; font-size: 1.1rem; color: var(--text-main); text-decoration: none; border: 1px solid var(--border-color); }
            .chat-header { padding: 12px 15px; }
            .chat-header-name { font-size: 1.1rem; }
            
            .chat-input-area { padding: 12px 15px; border-radius: 0 0 var(--radius-md) var(--radius-md); gap: 10px; }
            .chat-input { padding: 12px 18px; font-size: 0.95rem; }
            .btn-send { width: 45px; height: 45px; font-size: 1.1rem; }
            
            .offer-box { padding: 15px; gap: 10px; }
            .offer-input { width: 100% !important; min-width: 100%; }
            .btn-offer { width: 100%; justify-content: center; padding: 12px; }
            .empty-chat { border-radius: var(--radius-md); }
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
            <li><a href="enquiries.php" class="active"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.5rem;">Messages & Negotiations</div>
            
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
            <div class="chat-layout">
                
                <div class="chat-sidebar">
                    <div class="chat-sidebar-header">
                        <i class="fa-brands fa-whatsapp" style="color: var(--primary); margin-right: 8px;"></i> Active Chats
                    </div>
                    <div class="contact-list">
                        <?php if($contacts && $contacts->num_rows > 0): ?>
                            <?php while($contact = $contacts->fetch_assoc()): ?>
                                <?php 
                                    $is_active = ($active_enquiry_id == $contact['enquiry_id']) ? 'active' : '';
                                    $img = !empty($contact['image']) ? $contact['image'] : 'placeholder.jpg';
                                ?>
                                <a href="enquiries.php?id=<?php echo $contact['enquiry_id']; ?>" class="contact-item <?php echo $is_active; ?>">
                                    <img src="../uploads/<?php echo $img; ?>" class="contact-avatar">
                                    <div class="contact-info">
                                        <div class="contact-name-row">
                                            <div class="contact-name"><?php echo htmlspecialchars($contact['buyer_name']); ?></div>
                                            <?php if($contact['unread_count'] > 0): ?>
                                                <div class="unread-badge"><?php echo $contact['unread_count']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-crop"><i class="fa-solid fa-seedling" style="color: var(--primary); margin-right: 4px;"></i> <?php echo htmlspecialchars($contact['crop_name']); ?></div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div style="padding: 50px 20px; text-align: center; color: var(--text-muted); font-weight: 600; font-size: 1.1rem;">
                                <i class="fa-solid fa-envelope-open" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.3;"></i><br>
                                No messages yet.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chat-window">
                    <?php if($active_enquiry_id > 0): ?>
                        
                        <div class="chat-header">
                            <a href="enquiries.php" class="mobile-back"><i class="fa-solid fa-arrow-left"></i></a>
                            <div style="width: 48px; height: 48px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.4rem;">
                                <?php echo strtoupper(substr($active_contact_name, 0, 1)); ?>
                            </div>
                            <div>
                                <div class="chat-header-name"><?php echo htmlspecialchars($active_contact_name); ?></div>
                                <div class="chat-header-status"><i class="fa-solid fa-tag" style="color: var(--primary);"></i> Regarding: <?php echo htmlspecialchars($active_crop_name); ?></div>
                            </div>
                        </div>

                        <div class="chat-messages" id="chatBox">
                            <?php if($chat_history && $chat_history->num_rows > 0): ?>
                                <?php while($msg = $chat_history->fetch_assoc()): ?>
                                    <?php 
                                        $is_me = ($msg['sender_id'] == $farmer_id);
                                        $bubble_class = $is_me ? "msg-me" : "msg-other";
                                    ?>
                                    <div class="msg-bubble <?php echo $bubble_class; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <div class="msg-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <div class="offer-box">
                            <div style="font-weight: 900; color: #3b82f6; font-size: 1rem; text-transform: uppercase; letter-spacing: 0.5px; display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-handshake"></i> Send Offer</div>
                            <form method="POST" action="" style="display: flex; gap: 10px; align-items: center; flex-wrap: wrap; flex: 1; justify-content: flex-end;">
                                <input type="hidden" name="enquiry_id" value="<?php echo $active_enquiry_id; ?>">
                                <input type="number" name="custom_price" class="offer-input" placeholder="Price / kg (₹)" required style="width: 140px; flex: none;">
                                <input type="number" name="custom_quantity" class="offer-input" placeholder="Quantity (kg)" required style="width: 120px; flex: none;">
                                <button type="submit" name="send_offer" class="btn-offer"><i class="fa-solid fa-paper-plane"></i> Propose</button>
                            </form>
                        </div>

                        <form class="chat-input-area" method="POST" action="">
                            <input type="hidden" name="enquiry_id" value="<?php echo $active_enquiry_id; ?>">
                            <input type="text" name="message" class="chat-input" placeholder="Type a message..." required autocomplete="off" autofocus>
                            <button type="submit" name="send_msg" class="btn-send"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
          
                    <?php else: ?>
                        <div class="empty-chat">
                            <i class="fa-brands fa-whatsapp" style="font-size: 5rem; color: var(--border-color); margin-bottom: 20px;"></i>
                            <h2 style="margin-bottom: 10px; font-size: 1.8rem; font-weight: 900; color: var(--text-main);" class="logo-text">Farmogram Comms</h2>
                            <p style="font-size: 1.1rem; font-weight: 500;">Select a buyer from the sidebar to view messages and negotiate contracts.</p>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </main>
</div>

<script>
    // Auto-scroll chat to bottom perfectly on load
    var chatBox = document.getElementById("chatBox");
    if(chatBox) chatBox.scrollTop = chatBox.scrollHeight;

    // Theme Toggle Logic
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

<script src="../assets/js/translate.js"></script>

</body>
</html>
