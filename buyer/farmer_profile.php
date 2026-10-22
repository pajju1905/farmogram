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

// Buyer Profile Details for Topbar
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Buyer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

$farmer_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Fetch Farmer Details and Aggregated Stats */
$farmer_query = $conn->query("
    SELECT users.*, 
    (SELECT AVG(rating) FROM reviews WHERE farmer_id = users.id) as avg_rating,
    (SELECT COUNT(id) FROM reviews WHERE farmer_id = users.id) as total_reviews,
    (SELECT SUM(order_items.quantity) FROM order_items JOIN crops ON order_items.crop_id = crops.id JOIN orders ON order_items.order_id = orders.id WHERE crops.farmer_id = users.id AND orders.order_status != 'cancelled') as total_sold
    FROM users WHERE id = $farmer_id AND role = 'farmer' AND verification_status = 'verified'
");

if(!$farmer_query || $farmer_query->num_rows == 0) {
    header("Location: marketplace.php");
    exit();
}
$farmer = $farmer_query->fetch_assoc();

/* Badge Formatting */
$is_verified = ($farmer['badge'] == 'verified');
$avg_rating = $farmer['avg_rating'] ? number_format($farmer['avg_rating'], 1) : 'New';
$total_reviews = $farmer['total_reviews'] ?: 0;
$total_sold = $farmer['total_sold'] ?: 0;

/* Fetch Farmer's Active Crops */
$crops = $conn->query("SELECT * FROM crops WHERE farmer_id = $farmer_id AND status IN ('for_sale', 'growing') ORDER BY id DESC");

/* Fetch Farmer's Reviews */
$reviews = $conn->query("
    SELECT reviews.*, users.name as buyer_name 
    FROM reviews 
    JOIN users ON reviews.buyer_id = users.id 
    WHERE reviews.farmer_id = $farmer_id 
    ORDER BY reviews.created_at DESC
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($farmer['name']); ?>'s Farm | Farmogram</title>
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
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1400px; margin: 0 auto; width: 100%; }

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Navigation Button */
        .btn-back { display: inline-flex; align-items: center; gap: 8px; background: var(--card-bg); border: 2px solid var(--border-color); color: var(--text-main); padding: 12px 20px; border-radius: 50px; text-decoration: none; font-weight: 800; margin-bottom: 25px; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .btn-back:hover { border-color: var(--primary); color: var(--primary); transform: translateX(-4px); }

        /* Bento Profile Banner */
        .profile-banner { background: var(--card-bg); border-radius: var(--radius-xl); padding: 40px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); display: flex; gap: 30px; align-items: center; flex-wrap: wrap; margin-bottom: 35px; transition: 0.3s; position: relative; overflow: hidden; }
        
        /* Decorative Background Graphic */
        .profile-banner::after { content: '\f722'; font-family: 'Font Awesome 6 Free'; font-weight: 900; position: absolute; right: -20px; bottom: -40px; font-size: 15rem; color: var(--primary); opacity: 0.03; pointer-events: none; }
        
        .profile-avatar { width: 120px; height: 120px; background: rgba(16, 185, 129, 0.1); border: 2px solid rgba(16, 185, 129, 0.2); border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 3.5rem; color: var(--primary); flex-shrink: 0; box-shadow: inset 0 0 0 4px rgba(16, 185, 129, 0.05); }
        
        .profile-info { flex: 1; min-width: 250px; }
        .profile-name { font-size: 2.2rem; font-weight: 900; color: var(--text-main); margin: 0 0 5px 0; letter-spacing: -1px; text-transform: capitalize; display: flex; align-items: center; gap: 10px; }
        .verified-tag { font-size: 0.85rem; padding: 6px 12px; border-radius: 50px; font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; display: inline-flex; align-items: center; gap: 5px; }
        .verified-tag.is-verified { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .verified-tag.standard { background: var(--bg-light); color: var(--text-muted); border: 1px solid var(--border-color); }
        
        .profile-location { font-size: 1rem; color: var(--text-muted); margin-top: 10px; font-weight: 600; display: flex; align-items: center; gap: 6px; }

        /* Farmer Stats Grid */
        .farmer-stats { display: flex; gap: 15px; flex-wrap: wrap; }
        .stat-box { background: var(--bg-light); border: 1px solid var(--border-color); padding: 15px 25px; border-radius: var(--radius-md); text-align: center; flex: 1; min-width: 120px; transition: 0.3s; }
        .stat-box:hover { border-color: var(--primary); transform: translateY(-2px); }
        .stat-val { font-size: 1.6rem; font-weight: 900; color: var(--text-main); margin-bottom: 4px; display: flex; align-items: center; justify-content: center; gap: 5px; }
        .stat-lbl { font-size: 0.8rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Modern Pill Tabs */
        .tabs-container { display: flex; gap: 15px; margin-bottom: 25px; flex-wrap: wrap; border-bottom: 2px solid var(--border-color); padding-bottom: 15px; }
        .tab-btn { background: transparent; border: none; padding: 10px 20px; border-radius: 50px; font-size: 1.05rem; font-weight: 800; color: var(--text-muted); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 8px; }
        .tab-btn:hover { color: var(--text-main); background: var(--bg-light); }
        .tab-btn.active { background: var(--primary); color: #fff; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        
        .tab-content { display: none; animation: fadeIn 0.4s ease; }
        .tab-content.active { display: block; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        /* Marketplace Grid */
        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 28px; padding-bottom: 30px; }
        
        /* Premium Bento Product Card */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; position: relative; }
        .bento-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .card-img-wrapper { position: relative; width: 100%; height: 240px; overflow: hidden; border-bottom: 1px solid var(--border-color); display: block; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; display: block; }
        .bento-card:hover .card-img { transform: scale(1.08); }
        
        .status-badge { position: absolute; top: 15px; right: 15px; font-size: 0.8rem; padding: 8px 16px; border-radius: 50px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 2; pointer-events: none; }
        .status-sale { background: rgba(16, 185, 129, 0.9); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
        .status-growing { background: rgba(245, 158, 11, 0.9); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
        
        .card-body { padding: 25px; display: flex; flex-direction: column; flex: 1; }
        .product-title { font-size: 1.4rem; font-weight: 900; margin: 0 0 15px 0; color: var(--text-main); text-transform: capitalize; letter-spacing: -0.5px; transition: 0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .price { font-size: 1.6rem; font-weight: 900; color: var(--primary); letter-spacing: -0.5px; }
        .unit { font-size: 0.9rem; color: var(--text-muted); font-weight: 700; }

        .btn-view { padding: 14px; text-align: center; background: var(--bg-light); color: var(--text-main); border: 2px solid var(--border-color); border-radius: var(--radius-md); font-weight: 800; text-decoration: none; transition: 0.3s; font-size: 0.95rem; display: block; margin-top: auto; }
        .btn-view:hover { background: var(--card-bg); border-color: var(--primary); color: var(--primary); }

        /* Reviews Design */
        .reviews-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 24px; }
        .review-card { background: var(--card-bg); border: 1px solid var(--border-color); padding: 25px; border-radius: var(--radius-lg); box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .review-card:hover { border-color: var(--primary); transform: translateY(-2px); }
        .review-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px dashed var(--border-color); padding-bottom: 15px; }
        .reviewer-name { font-weight: 900; font-size: 1.15rem; color: var(--text-main); text-transform: capitalize; }
        .review-date { font-size: 0.85rem; color: var(--text-muted); font-weight: 600; }
        .review-stars { color: #f59e0b; font-size: 1.1rem; margin-bottom: 12px; }
        .review-comment { font-size: 1.05rem; color: var(--text-muted); font-weight: 500; line-height: 1.6; font-style: italic; }

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
        
        [data-theme="dark"] .profile-banner, [data-theme="dark"] .bento-card, [data-theme="dark"] .review-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .stat-box { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .tab-btn { color: var(--text-muted); }
        [data-theme="dark"] .tab-btn:hover { background: var(--bg-light) !important; color: var(--text-main); }
        [data-theme="dark"] .tab-btn.active { background: var(--primary) !important; color: #fff !important; }

        [data-theme="dark"] .btn-view { background: var(--bg-light) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .btn-view:hover { background: var(--sidebar-bg) !important; border-color: var(--primary) !important; color: var(--primary) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .profile-name, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .profile-banner, .bento-card, .stat-box, .review-card, h1, h2, h3, p, span, div, .btn-view { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .reviews-grid { grid-template-columns: 1fr; }
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
            
            .profile-banner { flex-direction: column; text-align: center; padding: 30px 20px; border-radius: var(--radius-lg); gap: 20px; }
            .profile-avatar { margin: 0 auto; }
            .profile-name { justify-content: center; flex-wrap: wrap; font-size: 1.8rem; }
            .profile-location { justify-content: center; }
            
            .farmer-stats { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; width: 100%; }
            .stat-box { padding: 15px 10px; }
            
            .product-grid { grid-template-columns: 1fr; gap: 20px; padding-bottom: 20px; }
            .bento-card { border-radius: var(--radius-lg); }
            .card-img-wrapper { height: 200px; }

            .tabs-container { justify-content: center; border-bottom: none; }
        }

        /* EXTRA SMALL SCREEN FIX */
        @media (max-width: 480px) {
            .mobile-brand span { display: block; font-size: 1.1rem; }
            .goog-te-gadget-simple { padding: 4px 8px !important; font-size: 0.75rem !important; }
            .farmer-stats { grid-template-columns: 1fr; }
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
            <li><a href="marketplace.php" class="active"><i class="fa-solid fa-store"></i> <span>Market</span></a></li>
            <li><a href="cart.php"><i class="fa-solid fa-cart-shopping"></i> <span>Cart</span></a></li>
            <li><a href="orders.php"><i class="fa-solid fa-box-open"></i> <span>Orders</span></a></li>
            <li><a href="my_enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Farmer Profile</div>
            
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
            <a href="marketplace.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Back to Market</a>

            <div class="profile-banner">
                <div class="profile-avatar"><i class="fa-solid fa-user-tractor"></i></div>
                <div class="profile-info">
                    <h1 class="profile-name">
                        <?php echo htmlspecialchars($farmer['name']); ?>
                        <?php if($is_verified): ?>
                            <span class="verified-tag is-verified"><i class="fa-solid fa-circle-check"></i> Verified Farm</span>
                        <?php else: ?>
                            <span class="verified-tag standard">Standard Farm</span>
                        <?php endif; ?>
                    </h1>
                    <div class="profile-location"><i class="fa-solid fa-location-dot"></i> <?php echo !empty($farmer['location']) ? htmlspecialchars($farmer['location']) : 'Location not provided'; ?></div>
                    <div class="profile-location" style="margin-top: 5px;"><i class="fa-solid fa-envelope"></i> <?php echo htmlspecialchars($farmer['email']); ?></div>
                </div>
                
                <div class="farmer-stats">
                    <div class="stat-box">
                        <div class="stat-val"><i class="fa-solid fa-star" style="color: #f59e0b; font-size: 1.2rem;"></i> <?php echo $avg_rating; ?></div>
                        <div class="stat-lbl">Rating (<?php echo $total_reviews; ?>)</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-val"><i class="fa-solid fa-boxes-packing" style="color: var(--primary); font-size: 1.2rem;"></i> <?php echo $total_sold; ?></div>
                        <div class="stat-lbl">Kg Sold</div>
                    </div>
                </div>
            </div>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('tab-crops', this)"><i class="fa-solid fa-seedling"></i> Active Listings</button>
                <button class="tab-btn" onclick="switchTab('tab-reviews', this)"><i class="fa-regular fa-star"></i> Customer Reviews</button>
            </div>

            <div id="tab-crops" class="tab-content active">
                <?php if($crops && $crops->num_rows > 0): ?>
                    <div class="product-grid">
                        <?php while($crop = $crops->fetch_assoc()): ?>
                            <?php
                                $img = !empty($crop['image']) ? $crop['image'] : 'placeholder.jpg';
                                $status_class = ($crop['status'] == 'for_sale') ? 'status-sale' : 'status-growing';
                                $status_text = ($crop['status'] == 'for_sale') ? '<i class="fa-solid fa-tags"></i> Ready' : '<i class="fa-solid fa-seedling"></i> Pre-Book';
                            ?>
                            <div class="bento-card">
                                <a href="crop_details.php?id=<?php echo $crop['id']; ?>" class="card-img-wrapper">
                                    <div class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                                    <img src="../uploads/<?php echo $img; ?>" class="card-img" alt="<?php echo htmlspecialchars($crop['crop_name']); ?>">
                                </a>
                                <div class="card-body">
                                    <h3 class="product-title"><?php echo htmlspecialchars($crop['crop_name']); ?></h3>
                                    
                                    <div class="price-row" style="margin-top: 10px;">
                                        <div class="price">₹<?php echo number_format($crop['market_price']); ?> <span class="unit">/ kg</span></div>
                                    </div>

                                    <a href="crop_details.php?id=<?php echo $crop['id']; ?>" class="btn-view">View Details</a>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color);">
                        <i class="fa-solid fa-store-slash" style="font-size: 3.5rem; color: var(--border-color); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--text-main); font-size: 1.4rem; margin-bottom: 10px; font-weight: 900;" class="logo-text">No active listings</h3>
                        <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">This farmer currently has no crops available for sale or pre-booking.</p>
                    </div>
                <?php endif; ?>
            </div>

            <div id="tab-reviews" class="tab-content">
                <?php if($reviews && $reviews->num_rows > 0): ?>
                    <div class="reviews-grid">
                        <?php while($rev = $reviews->fetch_assoc()): ?>
                            <div class="review-card">
                                <div class="review-header">
                                    <div class="reviewer-name"><i class="fa-solid fa-user" style="opacity: 0.5; margin-right: 5px;"></i> <?php echo htmlspecialchars($rev['buyer_name']); ?></div>
                                    <div class="review-date"><?php echo date('M j, Y', strtotime($rev['created_at'])); ?></div>
                                </div>
                                <div class="review-stars">
                                    <?php for($i=1; $i<=5; $i++) { echo $i <= $rev['rating'] ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star" style="color: var(--border-color);"></i>'; } ?>
                                </div>
                                <div class="review-comment">"<?php echo htmlspecialchars($rev['comment']); ?>"</div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div style="text-align: center; padding: 60px 20px; background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color);">
                        <i class="fa-regular fa-star" style="font-size: 3.5rem; color: var(--border-color); margin-bottom: 20px;"></i>
                        <h3 style="color: var(--text-main); font-size: 1.4rem; margin-bottom: 10px; font-weight: 900;" class="logo-text">No reviews yet</h3>
                        <p style="color: var(--text-muted); font-size: 1rem; font-weight: 500;">Be the first to buy from this farmer and leave a review!</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
    </main>
</div>

<script>
// Tab Switching Logic
function switchTab(tabId, btnElement) {
    document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabId).classList.add('active');
    btnElement.classList.add('active');
}

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