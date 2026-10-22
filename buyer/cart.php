<?php
session_start();
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

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Buyer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

// =======================================================
// HANDLE CART QUANTITY UPDATES
// =======================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_qty'])) {
    $update_id = (int)$_POST['crop_id'];
    $new_qty = (int)$_POST['quantity'];
    
    if ($new_qty > 0 && isset($_SESSION['cart'][$update_id])) {
        $_SESSION['cart'][$update_id]['quantity'] = $new_qty;
    } elseif ($new_qty <= 0) {
        unset($_SESSION['cart'][$update_id]); // Remove item if qty is set to 0 or less
    }
    
    // Redirect to refresh the cart totals
    header("Location: cart.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Cart | Farmogram</title>
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

        .cart-grid { display: grid; grid-template-columns: 2fr 1.2fr; gap: 30px; align-items: start; }
        
        /* Bento Cards */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; position: relative; overflow: hidden; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        .cart-header { background: var(--bg-light); padding: 25px 30px; border-bottom: 1px solid var(--border-color); font-size: 1.3rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        
        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 25px 30px; border-bottom: 1px solid var(--border-color); transition: 0.3s; background: var(--card-bg); }
        .cart-item:last-child { border-bottom: none; }
        .cart-item:hover { background: rgba(16, 185, 129, 0.02); padding-left: 35px; }
        
        .item-left { display: flex; align-items: center; gap: 20px; }
        .item-icon-box { width: 70px; height: 70px; background: rgba(16, 185, 129, 0.1); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary); border: 1px solid rgba(16, 185, 129, 0.2); transition: 0.3s; flex-shrink: 0; }
        
        .item-info h3 { font-size: 1.25rem; font-weight: 900; color: var(--text-main); text-transform: capitalize; margin: 0 0 6px 0; display: flex; align-items: center; flex-wrap: wrap; gap: 10px; transition: 0.3s; letter-spacing: -0.5px; }
        
        .badge-contract { font-size: 0.75rem; padding: 6px 12px; background: rgba(245, 158, 11, 0.1); color: #d97706; border-radius: 50px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; border: 1px solid rgba(245, 158, 11, 0.3); }
        
        /* Inline Quantity Updater Styling */
        .qty-form { display: flex; align-items: center; gap: 8px; background: var(--bg-light); border: 1px solid var(--border-color); padding: 6px 10px; border-radius: 12px; transition: 0.3s; }
        .qty-form:focus-within { border-color: var(--primary); box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1); background: var(--card-bg); }
        .qty-input { width: 55px; background: transparent; border: none; font-weight: 900; font-size: 1.05rem; color: var(--text-main); text-align: center; outline: none; font-family: inherit; }
        .btn-update-qty { background: var(--primary); color: #fff; border: none; width: 30px; height: 30px; border-radius: 8px; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); }
        .btn-update-qty:hover { background: var(--primary-hover); transform: scale(1.1); box-shadow: 0 6px 12px rgba(16, 185, 129, 0.3); }

        .item-right { text-align: right; }
        .item-price { font-size: 1.5rem; font-weight: 900; color: #10b981; margin-bottom: 10px; letter-spacing: -0.5px; transition: 0.3s; }
        .btn-remove { background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); color: #ef4444; font-size: 0.85rem; font-weight: 800; padding: 8px 16px; border-radius: 50px; cursor: pointer; transition: 0.3s; display: inline-flex; align-items: center; gap: 6px; text-decoration: none; }
        .btn-remove:hover { background: #ef4444; color: #fff; transform: translateY(-2px); box-shadow: 0 4px 10px rgba(239, 68, 68, 0.2); }

        /* Order Summary Box */
        .order-summary { padding: 35px; position: sticky; top: 0; }
        .summary-row { display: flex; justify-content: space-between; margin-bottom: 20px; color: var(--text-muted); font-size: 1.05rem; font-weight: 600; transition: 0.3s; }
        .summary-total { display: flex; justify-content: space-between; margin-top: 30px; padding-top: 25px; border-top: 2px dashed var(--border-color); font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        
        .btn-checkout { background: var(--primary); color: #fff; border: none; padding: 18px; width: 100%; border-radius: var(--radius-md); font-weight: 900; font-size: 1.1rem; cursor: pointer; transition: 0.3s; margin-top: 30px; display: flex; justify-content: center; align-items: center; gap: 10px; text-decoration: none; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-checkout:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

        .trust-icons { display: flex; justify-content: center; gap: 15px; margin-top: 25px; color: var(--text-muted); font-size: 1.8rem; transition: 0.3s; }
        
        /* Empty State */
        .empty-cart { text-align: center; padding: 80px 20px; border: 2px dashed var(--border-color); box-shadow: none; background: transparent; align-items: center; justify-content: center; }
        .empty-cart:hover { transform: none; border-color: var(--border-color); box-shadow: none; }
        .empty-cart i { font-size: 4rem; color: var(--border-color); margin-bottom: 20px; transition: 0.3s; }
        .empty-cart h2 { margin: 0 0 10px 0; font-size: 2rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .btn-browse { background: var(--primary); color: #fff; padding: 16px 32px; border-radius: 50px; font-weight: 800; text-decoration: none; font-size: 1.05rem; transition: 0.3s; display: inline-flex; align-items: center; gap: 10px; margin-top: 20px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-browse:hover { background: var(--primary-hover); transform: translateY(-2px); }

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
        
        [data-theme="dark"] .cart-header { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .cart-item { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .cart-item:hover { background: rgba(255,255,255,0.02) !important; }

        [data-theme="dark"] .qty-form { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .qty-form:focus-within { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        [data-theme="dark"] .qty-input { color: var(--text-main) !important; }

        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .cart-header, .cart-item, h1, h2, h3, p, span, div, .qty-form { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
        @media (max-width: 1024px) {
            .cart-grid { grid-template-columns: 1fr; gap: 24px; }
            .order-summary { position: relative; top: 0; }
        }

        @media (max-width: 768px) {
            .app-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
            .sidebar-logo { display: none; } 
            .mobile-brand { display: flex; align-items: center; flex: 1; }
            
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; display: flex; align-items: center; }
            .user-profile { display: none; } 
            
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; }
            .goog-te-gadget-simple { padding: 6px 12px !important; font-size: 0.8rem !important; margin: 0 !important; }

            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); border-right: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; scroll-snap-type: x mandatory; gap: 6px; justify-content: flex-start; -webkit-overflow-scrolling: touch; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu li { flex: 0 0 auto; scroll-snap-align: center; }
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; transition: 0.3s; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }

            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 20px 15px; flex: 1; overflow-y: auto; }
            
            .cart-header { padding: 20px; font-size: 1.15rem; }
            .cart-item { flex-direction: column; align-items: flex-start; gap: 15px; padding: 20px; }
            .cart-item:hover { padding-left: 20px; } 
            
            .item-right { width: 100%; display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--border-color); padding-top: 15px; }
            .item-price { margin: 0; font-size: 1.3rem; }
            
            .order-summary { padding: 25px 20px; border-radius: var(--radius-lg); }
            .summary-total { font-size: 1.4rem; margin-top: 20px; padding-top: 20px; }
            .btn-checkout { font-size: 1.05rem; padding: 16px; }
            
            .empty-cart h2 { font-size: 1.6rem; }
            .empty-cart i { font-size: 3.5rem; }
            .btn-browse { padding: 14px 24px; font-size: 1rem; }
        }

        /* EXTRA SMALL SCREEN FIX */
        @media (max-width: 480px) {
            .mobile-brand span { display: block; font-size: 1.1rem; }
            .goog-te-gadget-simple { padding: 4px 8px !important; font-size: 0.75rem !important; }
            
            .item-left { flex-direction: column; align-items: flex-start; gap: 10px; }
            .item-icon-box { width: 55px; height: 55px; font-size: 1.5rem; }
            .item-info h3 { font-size: 1.1rem; }
            .badge-contract { font-size: 0.65rem; }
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
            <li><a href="cart.php" class="active"><i class="fa-solid fa-cart-shopping"></i> <span>Cart</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Shopping Cart</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>

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
            <?php if(!empty($_SESSION['cart'])): ?>
                <div class="cart-grid">
                    
                    <div class="bento-card cart-items-container" style="padding: 0;">
                        <div class="cart-header"><i class="fa-solid fa-basket-shopping" style="color: var(--primary); margin-right: 10px;"></i> Review Your Items</div>
                        
                        <?php 
                        $grandTotal = 0;
                        foreach($_SESSION['cart'] as $id => $item): 
                            $total = $item['price'] * $item['quantity'];
                            $grandTotal += $total;
                            $is_advance = (isset($item['type']) && $item['type'] == 'advance');
                        ?>
                            <div class="cart-item">
                                <div class="item-left">
                                    <div class="item-icon-box"><i class="fa-solid fa-seedling"></i></div>
                                    <div class="item-info">
                                        <h3>
                                            <?php echo htmlspecialchars(str_replace(' (Pre-Book Advance)', '', $item['name'])); ?>
                                            <?php if($is_advance): ?><span class="badge-contract"><i class="fa-solid fa-file-contract"></i> 20% Advance</span><?php endif; ?>
                                        </h3>
                                        
                                        <div style="display: flex; align-items: center; gap: 15px; margin-top: 8px; flex-wrap: wrap;">
                                            <form method="POST" action="" class="qty-form">
                                                <input type="hidden" name="crop_id" value="<?php echo $id; ?>">
                                                <span style="font-size: 0.8rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase;">Qty:</span>
                                                <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" class="qty-input">
                                                <button type="submit" name="update_qty" class="btn-update-qty" title="Update Quantity"><i class="fa-solid fa-check" style="font-size: 0.9rem;"></i></button>
                                            </form>
                                            <div style="font-size: 0.95rem; color: var(--text-muted); font-weight: 600;">@ ₹<?php echo number_format($item['price'], 2); ?>/kg</div>
                                        </div>
                                        
                                        <?php if($is_advance): ?>
                                            <p style="font-size: 0.85rem; color: #f59e0b; margin-top: 6px; font-weight: 700;"><i class="fa-solid fa-circle-info"></i> Balance due upon harvest: ₹<?php echo number_format($item['full_price'], 2); ?>/kg</p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="item-right">
                                    <div class="item-price">₹<?php echo number_format($total, 2); ?></div>
                                    <a href="remove_from_cart.php?id=<?php echo $id; ?>" class="btn-remove" onclick="return confirm('Remove this item from your cart?');"><i class="fa-solid fa-trash-can"></i> Remove</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="bento-card order-summary">
                        <h2 style="font-size: 1.4rem; margin: 0 0 20px 0; color: var(--text-main); font-weight: 900; letter-spacing: -0.5px;" class="logo-text">Order Summary</h2>
                        <div class="summary-row"><span>Cart Subtotal</span> <span style="color: var(--text-main); font-weight: 800;">₹<?php echo number_format($grandTotal, 2); ?></span></div>
                        <div class="summary-row"><span>Platform Fees</span> <span style="color: var(--text-main); font-weight: 800;">₹0.00</span></div>
                        <div class="summary-total"><span>Total Due Today</span> <span>₹<?php echo number_format($grandTotal, 2); ?></span></div>
                        
                        <a href="checkout.php" class="btn-checkout"><i class="fa-solid fa-lock"></i> Proceed to Checkout</a>
                        
                        <div class="trust-icons">
                            <i class="fa-brands fa-cc-visa"></i>
                            <i class="fa-brands fa-cc-mastercard"></i>
                            <i class="fa-brands fa-google-pay"></i>
                            <i class="fa-brands fa-apple-pay"></i>
                        </div>
                        <p style="text-align: center; font-size: 0.8rem; color: var(--text-muted); margin-top: 15px; font-weight: 700;"><i class="fa-solid fa-shield-halved" style="color: #10b981;"></i> 256-bit SSL Encrypted</p>
                    </div>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color); transition: 0.3s;">
                    <i class="fa-solid fa-cart-arrow-down" style="font-size: 5rem; color: var(--border-color); margin-bottom: 25px; transition: 0.3s;"></i>
                    <h3 style="font-size: 1.8rem; color: var(--text-main); font-weight: 900; margin: 0 0 10px 0; transition: 0.3s;">Your cart is empty</h3>
                    <p style="color: var(--text-muted); font-size: 1.1rem; margin-bottom: 25px; transition: 0.3s;">Looks like you haven't added any fresh crops or contracts to your cart yet.</p>
                    <a href="marketplace.php" class="btn-browse" style="display:inline-flex; padding: 16px 30px; font-size: 1.1rem; margin-top: 0;"><i class="fa-solid fa-store"></i> Browse Marketplace</a>
                </div>
            <?php endif; ?>
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