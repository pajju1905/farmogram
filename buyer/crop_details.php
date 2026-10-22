<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK
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

$user_name = $_SESSION['name'] ?? 'Buyer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

$id = (int)$_GET['id'];
$result = $conn->query("
    SELECT crops.*, users.name AS farmer_name, users.email AS farmer_email, users.badge 
    FROM crops 
    JOIN users ON crops.farmer_id = users.id 
    WHERE crops.id = $id AND users.verification_status = 'verified'
");

if(!$result || $result->num_rows == 0) {
    header("Location: marketplace.php");
    exit();
}

$row = $result->fetch_assoc();
$image_path = !empty($row['image']) ? $row['image'] : 'placeholder.jpg';
$is_verified = ($row['badge'] == 'verified');
$status_class = ($row['status'] == 'for_sale') ? 'status-sale' : 'status-growing';
$status_text = ($row['status'] == 'for_sale') ? '<i class="fa-solid fa-tags"></i> Ready for Dispatch' : '<i class="fa-solid fa-seedling"></i> Pre-Book (Growing)';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?php echo htmlspecialchars($row['crop_name']); ?> | Farmogram</title>
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

        /* Product Details Grid */
        .pdp-grid { display: grid; grid-template-columns: 1fr 1.2fr; gap: 40px; }
        
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); padding: 40px; }
        .bento-card:hover { box-shadow: var(--shadow-hover); }

        /* Image Box */
        .pdp-image-box { position: relative; border-radius: var(--radius-lg); overflow: hidden; height: 100%; min-height: 400px; border: 1px solid var(--border-color); background: var(--bg-light); display: flex; justify-content: center; align-items: center; }
        .pdp-image-box img { width: 100%; height: 100%; object-fit: cover; position: absolute; inset: 0; transition: transform 0.5s; }
        .pdp-image-box:hover img { transform: scale(1.05); }

        .status-badge { position: absolute; top: 20px; left: 20px; font-size: 0.85rem; padding: 10px 18px; border-radius: 50px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); backdrop-filter: blur(10px); -webkit-backdrop-filter: blur(10px); z-index: 2; }
        .status-sale { background: rgba(16, 185, 129, 0.9); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
        .status-growing { background: rgba(245, 158, 11, 0.9); color: #fff; border: 1px solid rgba(255,255,255,0.2); }

        /* Product Info */
        .pdp-title { font-size: 2.8rem; font-weight: 900; color: var(--text-main); margin: 0 0 10px 0; text-transform: capitalize; letter-spacing: -1px; line-height: 1.1; }
        .pdp-price { font-size: 2.2rem; font-weight: 900; color: #10b981; margin-bottom: 25px; display: flex; align-items: baseline; gap: 8px; letter-spacing: -0.5px; }
        .pdp-price span { font-size: 1.1rem; color: var(--text-muted); font-weight: 700; }

        .farmer-profile { display: flex; align-items: center; gap: 15px; padding: 20px; background: var(--bg-light); border-radius: var(--radius-md); border: 1px solid var(--border-color); margin-bottom: 30px; transition: 0.3s; }
        .farmer-profile:hover { border-color: var(--primary); }
        .farmer-avatar { width: 55px; height: 55px; border-radius: var(--radius-md); background: rgba(59, 130, 246, 0.15); color: #3b82f6; display: flex; align-items: center; justify-content: center; font-size: 1.5rem; font-weight: 900; flex-shrink: 0; }
        .farmer-details h4 { margin: 0 0 4px 0; font-size: 1.1rem; color: var(--text-main); display: flex; align-items: center; gap: 6px; }
        .farmer-details p { margin: 0; font-size: 0.9rem; color: var(--text-muted); font-weight: 600; }
        .verified-badge { color: #3b82f6; font-size: 1rem; }

        .harvest-info { display: flex; align-items: center; gap: 15px; margin-bottom: 30px; color: var(--text-main); font-weight: 800; font-size: 1.05rem; }
        .harvest-info i { font-size: 1.5rem; color: #f59e0b; width: 40px; text-align: center; }
        .harvest-info span { color: var(--text-muted); font-weight: 600; display: block; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 4px; }

        /* Action Forms */
        .action-container { background: var(--bg-light); padding: 25px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); margin-bottom: 30px; }
        .qty-control { display: flex; align-items: center; justify-content: space-between; background: var(--card-bg); border: 2px solid var(--border-color); border-radius: var(--radius-md); padding: 12px 20px; margin-bottom: 20px; transition: 0.3s; }
        .qty-control:focus-within { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .qty-input { width: 100px; border: none; background: transparent; font-weight: 900; font-size: 1.2rem; color: var(--text-main); text-align: right; outline: none; font-family: inherit; }
        
        .btn-main { background: var(--primary); color: #fff; border: none; padding: 18px; width: 100%; border-radius: var(--radius-md); font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-main:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

        .enquiry-box { padding-top: 30px; border-top: 2px dashed var(--border-color); }
        .chat-textarea { width: 100%; padding: 20px; border-radius: var(--radius-md); border: 2px solid var(--border-color); outline: none; margin-bottom: 15px; resize: vertical; font-family: inherit; font-size: 1rem; transition: 0.3s; background: var(--bg-light); color: var(--text-main); font-weight: 600; }
        .chat-textarea:focus { border-color: #3b82f6; box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1); background: var(--card-bg); }
        .btn-msg { background: #3b82f6; color: #fff; border: none; padding: 16px; width: 100%; border-radius: var(--radius-md); font-weight: 900; font-size: 1.05rem; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; box-shadow: 0 4px 15px rgba(59, 130, 246, 0.2); }
        .btn-msg:hover { background: #2563eb; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3); }

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
        
        [data-theme="dark"] .farmer-profile, [data-theme="dark"] .action-container { background: var(--bg-light) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .farmer-profile:hover { border-color: var(--primary) !important; }
        
        [data-theme="dark"] .qty-control, [data-theme="dark"] .chat-textarea { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .qty-input { color: var(--text-main) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] h4, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .pdp-title, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .btn-back, .farmer-profile, .action-container, .qty-control, .chat-textarea, h1, h3, p, span, div { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .pdp-grid { grid-template-columns: 1fr; gap: 30px; }
            .pdp-image-box { min-height: 400px; }
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
            
            .pdp-image-box { min-height: 280px; border-radius: var(--radius-md); }
            .pdp-title { font-size: 2rem; margin-top: 20px; }
            .pdp-price { font-size: 1.8rem; }
            
            .action-container { padding: 20px; border-radius: var(--radius-md); }
            .qty-control { padding: 10px 15px; }
            .btn-main { padding: 16px; font-size: 1.05rem; }
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Product Overview</div>
            
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
            <a href="marketplace.php" class="btn-back"><i class="fa-solid fa-arrow-left"></i> Return to Market</a>
            
            <div class="bento-card pdp-grid">
                
                <div class="pdp-image-box">
                    <div class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                    <img src="../uploads/<?php echo $image_path; ?>" alt="<?php echo htmlspecialchars($row['crop_name']); ?>">
                </div>
                
                <div>
                    <h1 class="pdp-title"><?php echo htmlspecialchars($row['crop_name']); ?></h1>
                    <div class="pdp-price">₹<?php echo number_format($row['market_price']); ?> <span>/ kg</span></div>
                    
                    <a href="farmer_profile.php?id=<?php echo $row['farmer_id']; ?>" style="text-decoration: none;">
                        <div class="farmer-profile">
                            <div class="farmer-avatar"><i class="fa-solid fa-user-tractor"></i></div>
                            <div class="farmer-details">
                                <h4>
                                    <?php echo htmlspecialchars($row['farmer_name']); ?>
                                    <?php if($is_verified): ?>
                                        <i class="fa-solid fa-circle-check verified-badge" title="Verified Farmer"></i>
                                    <?php endif; ?>
                                </h4>
                                <p>Click to view farmer's full profile and ratings.</p>
                            </div>
                            <i class="fa-solid fa-chevron-right" style="margin-left: auto; color: var(--text-muted);"></i>
                        </div>
                    </a>

                    <div class="harvest-info">
                        <i class="fa-solid fa-calendar-check"></i>
                        <div>
                            <span>Expected Harvest Date</span>
                            <?php echo date('F j, Y', strtotime($row['expected_harvest'])); ?>
                        </div>
                    </div>

                    <div class="action-container">
                        <form method="POST" action="add_to_cart.php">
                            <input type="hidden" name="crop_id" value="<?php echo $row['id']; ?>">
                            <input type="hidden" name="booking_type" value="<?php echo ($row['status'] == 'for_sale') ? 'full' : 'advance'; ?>">
                            
                            <div class="qty-control">
                                <span style="font-weight: 800; font-size: 0.95rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Quantity (kg)</span>
                                <input type="number" name="quantity" class="qty-input" min="1" value="1" required>
                            </div>
                            
                            <button type="submit" class="btn-main"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
                        </form>
                    </div>

                    <div class="enquiry-box">
                        <h3 style="margin: 0 0 15px 0; font-size: 1.3rem; font-weight: 900; color: var(--text-main);"><i class="fa-regular fa-comments" style="color: #3b82f6; margin-right: 8px;"></i> Negotiate Price</h3>
                        <form method="POST" action="create_enquiry.php">
                            <input type="hidden" name="crop_id" value="<?php echo $row['id']; ?>">
                            <textarea name="message" class="chat-textarea" required rows="3" placeholder="Hello! I'm interested in buying this in bulk. Can we discuss the pricing?"></textarea>
                            <button type="submit" class="btn-msg">Send Direct Message <i class="fa-solid fa-paper-plane"></i></button>
                        </form>
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