<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'buyer'){
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

$buyer_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Buyer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

$active_enquiry_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Mark messages as read as soon as the buyer opens this chat
if($active_enquiry_id > 0){
    $conn->query("UPDATE enquiry_messages SET is_read = 1 WHERE enquiry_id = $active_enquiry_id AND sender_id != $buyer_id");
}

// HANDLE NEW MESSAGE SUBMISSION & PUSH NOTIFICATION
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['send_msg'])){
    $enq_id = (int)$_POST['enquiry_id'];
    $message = $conn->real_escape_string($_POST['message']);
    
    if(!empty($message)){
        // 1. Save to Database
        $conn->query("INSERT INTO enquiry_messages (enquiry_id, sender_id, message) VALUES ($enq_id, $buyer_id, '$message')");
        
        // 2. TRIGGER REAL MOBILE PUSH NOTIFICATION TO FARMER
        include_once("../config/push_engine.php");
        
        // Find out who the farmer is for this specific chat
        $get_farmer = $conn->query("SELECT farmer_id FROM enquiries WHERE id = $enq_id");
        if($get_farmer && $get_farmer->num_rows > 0) {
            $f_data = $get_farmer->fetch_assoc();
            $target_farmer_id = $f_data['farmer_id'];
            
            // Send the actual pop-up!
            sendMobilePush($target_farmer_id, "New Message on Farmogram 💬", "A buyer just replied to your crop negotiation!");
        }
    }
    header("Location: my_enquiries.php?id=$enq_id");
    exit();
}

// Fetch all chats WITH WhatsApp Sorting AND Unread Counts
$contacts = $conn->query("
    SELECT e.id AS enquiry_id, u.name AS farmer_name, c.crop_name, c.image,
           (SELECT MAX(created_at) FROM enquiry_messages WHERE enquiry_id = e.id) as last_msg_time,
           (SELECT COUNT(*) FROM enquiry_messages WHERE enquiry_id = e.id AND sender_id != $buyer_id AND is_read = 0) as unread_count
    FROM enquiries e 
    JOIN users u ON e.farmer_id = u.id 
    JOIN crops c ON e.crop_id = c.id 
    WHERE e.buyer_id = $buyer_id 
    ORDER BY last_msg_time DESC
");

$chat_history = null;
$active_contact_name = "";
$active_crop_name = "";

if($active_enquiry_id > 0){
    $header_res = $conn->query("SELECT u.name AS farmer_name, c.crop_name FROM enquiries e JOIN users u ON e.farmer_id = u.id JOIN crops c ON e.crop_id = c.id WHERE e.id = $active_enquiry_id");
    if($header_res->num_rows > 0){
        $header_data = $header_res->fetch_assoc();
        $active_contact_name = $header_data['farmer_name'];
        $active_crop_name = $header_data['crop_name'];
        $chat_history = $conn->query("SELECT m.*, u.name AS sender_name FROM enquiry_messages m JOIN users u ON m.sender_id = u.id WHERE m.enquiry_id = $active_enquiry_id ORDER BY m.created_at ASC");
    }
}

// HANDLE ACCEPTING THE CUSTOM OFFER
if(isset($_POST['accept_offer'])) {
    $enq_id = (int)$_POST['enquiry_id'];
    $crop_id = (int)$_POST['crop_id'];
    $negotiated_price = (float)$_POST['accept_price'];
    $negotiated_qty = (int)$_POST['accept_qty'];
    
    // 1. Fetch the crop name to put in the cart
    $c_query = $conn->query("SELECT crop_name, image FROM crops WHERE id = $crop_id");
    $crop = $c_query->fetch_assoc();

    // 2. Add to Session Cart using the NEGOTIATED price, not the DB price!
    $_SESSION['cart'][$crop_id] = [
        'crop_id' => $crop_id,
        'name' => $crop['crop_name'] . " (Negotiated Deal)",
        'price' => $negotiated_price,
        'quantity' => $negotiated_qty,
        'image' => $crop['image']
    ];

    // 3. Mark the offer as accepted in the database
    $conn->query("UPDATE enquiries SET offer_status = 'accepted' WHERE id = $enq_id");

    // 4. Redirect them straight to checkout!
    header("Location: checkout.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Messages | Farmogram</title>
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
        .content-container { flex: 1; display: flex; flex-direction: column; padding: 25px 40px; max-width: 1400px; margin: 0 auto; width: 100%; min-height: 0; }

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
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
        .chat-sidebar { width: 380px; background: var(--bg-light); border-right: 1px solid var(--border-color); display: flex; flex-direction: column; transition: 0.3s; }
        .chat-sidebar-header { padding: 25px; border-bottom: 1px solid var(--border-color); font-weight: 900; font-size: 1.3rem; color: var(--text-main); background: var(--card-bg); transition: 0.3s; letter-spacing: -0.5px; }
        .contact-list { overflow-y: auto; flex: 1; }
        
        .contact-item { display: flex; gap: 15px; padding: 20px 25px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: 0.3s; text-decoration: none; color: inherit; align-items: center; }
        .contact-item:hover { background: rgba(59, 130, 246, 0.05); padding-left: 30px; }
        .contact-item.active { background: rgba(59, 130, 246, 0.1); border-left: 4px solid #3b82f6; padding-left: 21px; }
        
        .contact-avatar { width: 55px; height: 55px; border-radius: var(--radius-md); object-fit: cover; border: 2px solid var(--border-color); transition: 0.3s; }
        .contact-info { flex: 1; overflow: hidden; }
        
        .contact-name-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
        .contact-name { font-weight: 800; color: var(--text-main); font-size: 1.1rem; transition: 0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .unread-badge { background: #ef4444; color: #fff; font-size: 0.75rem; font-weight: 800; padding: 4px 10px; border-radius: 20px; box-shadow: 0 4px 10px rgba(239, 68, 68, 0.3); }
        
        .contact-crop { font-size: 0.9rem; color: var(--text-muted); text-transform: capitalize; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 600; transition: 0.3s; }

        /* CHAT WINDOW */
        .chat-window { flex: 1; display: flex; flex-direction: column; background: var(--card-bg); position: relative; transition: 0.3s; min-width: 0; }
        .chat-header { padding: 20px 30px; background: var(--bg-light); border-bottom: 1px solid var(--border-color); display: flex; align-items: center; gap: 20px; z-index: 10; transition: 0.3s; }
        .chat-header-name { font-size: 1.3rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .chat-header-status { font-size: 0.95rem; color: var(--text-muted); text-transform: capitalize; font-weight: 600; transition: 0.3s; }

        /* OFFER BANNER */
        .offer-banner { background: rgba(16, 185, 129, 0.1); padding: 20px 30px; border-bottom: 1px solid rgba(16, 185, 129, 0.2); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; z-index: 5; transition: 0.3s; }
        .offer-banner-title { font-weight: 900; color: #10b981; font-size: 1.1rem; margin-bottom: 5px; }
        .offer-banner-text { font-size: 0.95rem; color: var(--text-main); font-weight: 600; transition: 0.3s; }
        .btn-accept-offer { background: var(--primary) !important; color: #fff !important; border: none; padding: 12px 24px; border-radius: 12px; font-weight: 800; cursor: pointer; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); transition: 0.3s; display: inline-flex; align-items: center; gap: 8px; font-size: 1rem; font-family: inherit; }
        .btn-accept-offer:hover { background: var(--primary-hover) !important; transform: translateY(-2px); }

        /* MESSAGES */
        .chat-messages { flex: 1; padding: 30px; overflow-y: auto; display: flex; flex-direction: column; gap: 15px; z-index: 1; }
        .msg-bubble { max-width: 70%; padding: 15px 20px; font-size: 1rem; line-height: 1.5; position: relative; border-radius: 20px; font-weight: 500; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .msg-time { font-size: 0.75rem; opacity: 0.7; text-align: right; margin-top: 8px; margin-left: 15px; font-weight: 700; }

        .msg-me { align-self: flex-end; background: var(--primary); color: #fff; border-bottom-right-radius: 6px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .msg-other { align-self: flex-start; background: var(--bg-light); color: var(--text-main); border-bottom-left-radius: 6px; border: 1px solid var(--border-color); }

        /* INPUT AREA */
        .chat-input-area { padding: 20px 25px; background: var(--bg-light); display: flex; gap: 15px; align-items: center; z-index: 10; transition: 0.3s; border-top: 1px solid var(--border-color); }
        .chat-input { flex: 1; padding: 16px 24px; border-radius: 50px; border: 2px solid var(--border-color); background: var(--card-bg); color: var(--text-main); outline: none; font-size: 1rem; font-family: inherit; transition: 0.3s; font-weight: 600; }
        .chat-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .btn-send { width: 56px; height: 56px; border-radius: 50%; background: var(--primary); color: #fff; border: none; cursor: pointer; font-size: 1.3rem; transition: 0.3s; display: flex; align-items: center; justify-content: center; flex-shrink: 0; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-send:hover { background: var(--primary-hover); transform: translateY(-2px); }

        /* EMPTY STATE */
        .empty-chat { flex: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; z-index: 1; color: var(--text-muted); background: var(--card-bg); transition: 0.3s; text-align: center; padding: 20px; }

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
        [data-theme="dark"] .chat-layout { box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .chat-header-name, [data-theme="dark"] .contact-name, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Chat Specific Dark Mode Overrides */
        [data-theme="dark"] .contact-item.active { background: rgba(59, 130, 246, 0.15) !important; border-left-color: #3b82f6 !important; }
        [data-theme="dark"] .chat-sidebar-header { background: var(--sidebar-bg) !important; border-bottom-color: var(--border-color) !important; }
        [data-theme="dark"] .msg-other { background: var(--sidebar-bg) !important; border-color: rgba(255,255,255,0.05) !important; }
        [data-theme="dark"] .chat-header { background: rgba(0,0,0,0.2) !important; border-bottom-color: var(--border-color) !important; }
        [data-theme="dark"] .chat-input-area { background: rgba(0,0,0,0.2) !important; border-top-color: var(--border-color) !important; }
        [data-theme="dark"] .chat-input { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .chat-input:focus { border-color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .chat-layout, h1, h2, h3, p, span, div, .chat-input { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        .mobile-back { display: none; }

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

            /* 100dvh EXACT CHAT SIZING */
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 10px; flex: 1; display: flex; flex-direction: column; min-height: 0; }
            
            .chat-layout { flex: 1; border-radius: var(--radius-md); box-shadow: none; min-height: 0; border: none; }

            /* View Swapping Logic */
            .chat-sidebar { display: <?php echo $active_enquiry_id > 0 ? 'none' : 'flex'; ?>; width: 100%; border-right: none; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
            .chat-window { display: <?php echo $active_enquiry_id > 0 ? 'flex' : 'none'; ?>; width: 100%; border-radius: var(--radius-md); border: 1px solid var(--border-color); }
            
            .mobile-back { display: flex !important; align-items: center; justify-content: center; width: 40px; height: 40px; border-radius: 50%; background: var(--bg-light); margin-right: 10px; font-size: 1.1rem; color: var(--text-main); text-decoration: none; border: 1px solid var(--border-color); }
            .chat-header { padding: 12px 15px; }
            .chat-header-name { font-size: 1.1rem; }
            
            .chat-input-area { padding: 12px 15px; border-radius: 0 0 var(--radius-md) var(--radius-md); gap: 10px; }
            .chat-input { padding: 12px 18px; font-size: 0.95rem; }
            .btn-send { width: 45px; height: 45px; font-size: 1.1rem; }
            
            .offer-banner { flex-direction: column; align-items: stretch; text-align: center; padding: 15px; gap: 12px; }
            .btn-accept-offer { justify-content: center; width: 100%; }
            .empty-chat { border-radius: var(--radius-md); }
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
            <li><a href="dashboard.php"><i class="fa-solid fa-house"></i> <span>Overview</span></a></li>
            <li><a href="marketplace.php"><i class="fa-solid fa-store"></i> <span>Market</span></a></li>
            <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> <span>Cart</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-box-open"></i> <span>Orders</span></a></li>
            <li><a href="my_enquiries.php" class="active"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Messages & Negotiations</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <a href="cart.php" class="theme-btn" style="text-decoration: none;" title="My Cart">
                    <i class="fa-solid fa-cart-shopping"></i>
                </a>

                <button class="theme-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <a href="profile.php" class="user-profile" style="border-left: none; display: flex; align-items: center; text-decoration: none; padding-left: 0;">
                    <div style="text-align: right; margin-right: 10px;" class="desktop-only-text">
                        <div style="font-weight: 800; font-size: 0.95rem;" class="logo-text"><?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?></div>
                        <div style="color: var(--text-muted); font-size: 0.75rem; font-weight: 600;">Buyer</div>
                    </div>
                    <?php if(!empty($profile_image) && file_exists("../uploads/profile/" . $profile_image)): ?>
                        <div class="avatar" style="border: 1px solid rgba(59, 130, 246, 0.3); overflow: hidden; padding: 0; display: flex; align-items: center; justify-content: center; flex-shrink: 0;"><img src="../uploads/profile/<?php echo $profile_image; ?>" style="width:100%; height:100%; object-fit:cover; border-radius:50%;"></div>
                    <?php else: ?>
                        <div class="avatar" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); font-weight: 900; font-size: 1.1rem; display: flex; align-items: center; justify-content: center; border-radius: 50%; flex-shrink: 0;"><?php echo $avatar_letter; ?></div>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <div class="content-container">
            <div class="chat-layout">
                
                <div class="chat-sidebar">
                    <div class="chat-sidebar-header"><i class="fa-brands fa-whatsapp" style="color: var(--primary); margin-right: 8px;"></i> Active Chats</div>
                    <div class="contact-list">
                        <?php if($contacts && $contacts->num_rows > 0): ?>
                            <?php while($contact = $contacts->fetch_assoc()): ?>
                                <?php 
                                    $is_active = ($active_enquiry_id == $contact['enquiry_id']) ? 'active' : '';
                                    $img = !empty($contact['image']) ? $contact['image'] : 'placeholder.jpg';
                                ?>
                                <a href="my_enquiries.php?id=<?php echo $contact['enquiry_id']; ?>" class="contact-item <?php echo $is_active; ?>">
                                    <img src="../uploads/<?php echo $img; ?>" class="contact-avatar">
                                    <div class="contact-info">
                                        <div class="contact-name-row">
                                            <div class="contact-name"><?php echo htmlspecialchars($contact['farmer_name']); ?></div>
                                            <?php if($contact['unread_count'] > 0): ?>
                                                <div class="unread-badge"><?php echo $contact['unread_count']; ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="contact-crop"><i class="fa-solid fa-tractor" style="color: var(--primary); margin-right: 4px;"></i> <?php echo htmlspecialchars($contact['crop_name']); ?></div>
                                    </div>
                                </a>
                            <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="chat-window">
                    <?php if($active_enquiry_id > 0): ?>
                        
                        <div class="chat-header">
                            <a href="my_enquiries.php" class="mobile-back"><i class="fa-solid fa-arrow-left"></i></a>
                            <div style="width: 50px; height: 50px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-radius: 16px; display: flex; align-items: center; justify-content: center; font-weight: 900; font-size: 1.4rem; flex-shrink: 0;">
                                <?php echo strtoupper(substr($active_contact_name, 0, 1)); ?>
                            </div>
                            <div style="overflow: hidden;">
                                <div class="chat-header-name" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><?php echo htmlspecialchars($active_contact_name); ?></div>
                                <div class="chat-header-status" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"><i class="fa-solid fa-tag" style="color: var(--primary);"></i> Regarding: <?php echo htmlspecialchars($active_crop_name); ?></div>
                            </div>
                        </div>

                        <?php
                        $offer_check = $conn->query("SELECT crop_id, custom_price, custom_quantity, offer_status FROM enquiries WHERE id = $active_enquiry_id");
                        $offer_data = $offer_check->fetch_assoc();

                        if($offer_data && $offer_data['offer_status'] == 'sent'):
                        ?>
                            <div class="offer-banner">
                                <div>
                                    <div class="offer-banner-title"><i class="fa-solid fa-file-contract"></i> Custom Offer Received!</div>
                                    <div class="offer-banner-text">The farmer offered <strong style="color: var(--primary); font-size: 1.05rem;"><?php echo $offer_data['custom_quantity']; ?> kg</strong> at <strong style="color: var(--primary); font-size: 1.05rem;">₹<?php echo $offer_data['custom_price']; ?> / kg</strong>.</div>
                                </div>
                                <form method="POST" action="">
                                    <input type="hidden" name="enquiry_id" value="<?php echo $active_enquiry_id; ?>">
                                    <input type="hidden" name="crop_id" value="<?php echo $offer_data['crop_id']; ?>">
                                    <input type="hidden" name="accept_price" value="<?php echo $offer_data['custom_price']; ?>">
                                    <input type="hidden" name="accept_qty" value="<?php echo $offer_data['custom_quantity']; ?>">
                                    <button type="submit" name="accept_offer" class="btn-accept-offer">Accept & Checkout <i class="fa-solid fa-cart-arrow-down"></i></button>
                                </form>
                            </div>
                        <?php endif; ?>

                        <div class="chat-messages" id="chatBox">
                            <?php if($chat_history && $chat_history->num_rows > 0): ?>
                                <?php while($msg = $chat_history->fetch_assoc()): ?>
                                    <?php 
                                        $is_me = ($msg['sender_id'] == $buyer_id);
                                        $bubble_class = $is_me ? "msg-me" : "msg-other";
                                    ?>
                                    <div class="msg-bubble <?php echo $bubble_class; ?>">
                                        <?php echo nl2br(htmlspecialchars($msg['message'])); ?>
                                        <div class="msg-time"><?php echo date('g:i A', strtotime($msg['created_at'])); ?></div>
                                    </div>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>

                        <form class="chat-input-area" method="POST" action="">
                            <input type="hidden" name="enquiry_id" value="<?php echo $active_enquiry_id; ?>">
                            <input type="text" name="message" class="chat-input" placeholder="Type your message here..." required autocomplete="off" autofocus>
                            <button type="submit" name="send_msg" class="btn-send"><i class="fa-solid fa-paper-plane"></i></button>
                        </form>
                    <?php else: ?>
                        <div class="empty-chat">
                            <i class="fa-brands fa-whatsapp" style="font-size: 6rem; color: var(--border-color); margin-bottom: 25px;"></i>
                            <h2 style="margin-bottom: 10px; font-size: 2rem;" class="logo-text">Farmogram Comms</h2>
                            <p style="font-size: 1.1rem;">Select a farmer from the sidebar to view messages and negotiate deals.</p>
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