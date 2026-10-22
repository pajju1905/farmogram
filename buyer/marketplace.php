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

$buyer_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Buyer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

$query = "SELECT crops.*, users.name AS farmer_name, users.badge,
          (SELECT AVG(rating) FROM reviews WHERE farmer_id = users.id) as avg_rating,
          (SELECT COUNT(id) FROM reviews WHERE farmer_id = users.id) as review_count
          FROM crops 
          JOIN users ON crops.farmer_id = users.id 
          WHERE crops.status IN ('for_sale', 'growing') AND crops.quantity > 0 AND users.verification_status = 'verified'
          ORDER BY crops.id DESC";
$result = $conn->query($query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Live Marketplace | Farmogram</title>
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
        .topbar { flex-shrink: 0; }
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; max-width: 1600px; margin: 0 auto; width: 100%; }

        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }
        
        .market-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px; gap: 20px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 250px; position: relative; }
        .search-box i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.1rem; }
        .search-input { width: 100%; padding: 18px 20px 18px 50px; border-radius: 50px; border: 2px solid var(--border-color); background: var(--card-bg); color: var(--text-main); font-size: 1.05rem; font-family: inherit; outline: none; transition: 0.3s; font-weight: 600; box-shadow: var(--shadow-subtle); }
        .search-input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        
        .filter-pills { display: flex; gap: 12px; overflow-x: auto; padding-bottom: 5px; }
        .filter-pills::-webkit-scrollbar { display: none; }
        .filter-btn { padding: 12px 24px; border-radius: 50px; border: 2px solid var(--border-color); background: var(--card-bg); color: var(--text-muted); font-weight: 800; font-size: 0.95rem; cursor: pointer; transition: 0.3s; white-space: nowrap; box-shadow: var(--shadow-subtle); }
        .filter-btn:hover { border-color: var(--primary); color: var(--text-main); transform: translateY(-2px); }
        .filter-btn.active { background: var(--primary); color: #fff; border-color: var(--primary); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }

        .product-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 28px; padding-bottom: 30px; }
        
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); overflow: hidden; border: 1px solid var(--border-color); box-shadow: var(--shadow-subtle); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; position: relative; }
        .bento-card:hover { transform: translateY(-6px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        
        .card-img-wrapper { position: relative; width: 100%; height: 240px; overflow: hidden; border-bottom: 1px solid var(--border-color); display: block; }
        .card-img { width: 100%; height: 100%; object-fit: cover; transition: transform 0.5s; display: block; }
        .bento-card:hover .card-img { transform: scale(1.08); }
        
        .status-badge { position: absolute; top: 15px; right: 15px; font-size: 0.8rem; padding: 8px 16px; border-radius: 50px; font-weight: 900; text-transform: uppercase; letter-spacing: 0.5px; box-shadow: 0 4px 15px rgba(0,0,0,0.15); backdrop-filter: blur(10px); z-index: 2; pointer-events: none; }
        .status-sale { background: rgba(16, 185, 129, 0.9); color: #fff; border: 1px solid rgba(255,255,255,0.2); }
        .status-growing { background: rgba(245, 158, 11, 0.9); color: #fff; border: 1px solid rgba(255,255,255,0.2); }

        /* STOCK BADGE styling */
        .stock-badge { position: absolute; bottom: 15px; left: 15px; font-size: 0.85rem; padding: 8px 14px; border-radius: 12px; font-weight: 900; background: rgba(15, 23, 42, 0.85); color: #fff; box-shadow: 0 4px 15px rgba(0,0,0,0.2); backdrop-filter: blur(5px); z-index: 2; border: 1px solid rgba(255,255,255,0.1); }
        
        .card-body { padding: 25px; display: flex; flex-direction: column; flex: 1; }
        .product-title { font-size: 1.4rem; font-weight: 900; margin: 0 0 8px 0; color: var(--text-main); text-transform: capitalize; letter-spacing: -0.5px; transition: 0.3s; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        
        .farmer-info { font-size: 0.95rem; color: var(--text-muted); font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 6px; }
        .verified-badge { color: #3b82f6; font-size: 1rem; }
        
        .profile-link { font-size: 0.8rem; background: rgba(59, 130, 246, 0.1); color: #3b82f6; padding: 6px 12px; border-radius: 20px; text-decoration: none; font-weight: 800; margin-left: auto; border: 1px solid rgba(59, 130, 246, 0.2); transition: 0.3s; }
        .profile-link:hover { background: #3b82f6; color: #fff; }

        .price-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .price { font-size: 1.6rem; font-weight: 900; color: var(--primary); letter-spacing: -0.5px; }
        .unit { font-size: 0.9rem; color: var(--text-muted); font-weight: 700; }
        
        .rating-box { display: flex; align-items: center; gap: 5px; font-size: 0.95rem; font-weight: 800; color: var(--text-main); background: var(--bg-light); padding: 6px 12px; border-radius: 20px; border: 1px solid var(--border-color); }
        .rating-box i { color: #f59e0b; }

        .qty-control { display: flex; align-items: center; justify-content: space-between; background: var(--bg-light); border: 1px solid var(--border-color); border-radius: var(--radius-md); padding: 8px 15px; margin-bottom: 15px; transition: 0.3s; }
        .qty-input { width: 80px; border: none; background: transparent; font-weight: 900; font-size: 1.1rem; color: var(--text-main); text-align: right; outline: none; font-family: inherit; }

        .card-actions { display: flex; gap: 10px; }
        .btn-view { flex: 1; padding: 14px; text-align: center; background: var(--bg-light); color: var(--text-main); border: 2px solid var(--border-color); border-radius: var(--radius-md); font-weight: 800; text-decoration: none; transition: 0.3s; font-size: 0.95rem; }
        .btn-view:hover { background: var(--card-bg); border-color: var(--primary); color: var(--primary); }
        
        .btn-cart { flex: 2; padding: 14px; background: var(--primary); color: #fff; border: none; border-radius: var(--radius-md); font-size: 0.95rem; font-weight: 800; cursor: pointer; transition: 0.3s; display: flex; align-items: center; justify-content: center; gap: 8px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-cart:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }

        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }
        .mobile-brand { display: none; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        .mobile-brand span { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); flex-shrink: 0; }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        .logo-light { display: block; width: 35px; height: 35px; object-fit: contain; }
        .logo-dark { display: none; width: 35px; height: 35px; object-fit: contain; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }

        /* DARK MODE */
        [data-theme="dark"] { --bg-light: #0f172a !important; --sidebar-bg: #1e293b !important; --text-main: #f8fafc !important; --text-muted: #94a3b8 !important; --border-color: rgba(255,255,255,0.05) !important; --card-bg: #1e293b !important; }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.85) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .bento-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .search-input { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .search-input:focus { border-color: var(--primary) !important; }
        [data-theme="dark"] .filter-btn { background: var(--sidebar-bg); border-color: var(--border-color); color: var(--text-muted); }
        [data-theme="dark"] .filter-btn:hover { border-color: var(--primary); color: var(--text-main); }
        [data-theme="dark"] .filter-btn.active { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; }
        [data-theme="dark"] .rating-box { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .qty-control { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .qty-input { color: var(--text-main) !important; }
        [data-theme="dark"] .btn-view { background: var(--bg-light) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .btn-view:hover { background: var(--sidebar-bg) !important; border-color: var(--primary) !important; color: var(--primary) !important; }
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span { color: var(--text-muted); }
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        @media (max-width: 768px) {
            .app-layout { display: flex; flex-direction: column; height: 100dvh; overflow: hidden; }
            .sidebar-logo { display: none; } 
            .mobile-brand { display: flex; align-items: center; flex: 1; }
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; background: rgba(255, 255, 255, 0.85); backdrop-filter: blur(20px); border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; flex-shrink: 0; }
            .page-title { display: none; } 
            .topbar-actions { gap: 8px; flex-wrap: nowrap; display: flex; align-items: center; }
            .user-profile { display: none; } 
            .theme-btn { width: 38px; height: 38px; font-size: 1rem; }
            .goog-te-gadget-simple { padding: 6px 12px !important; font-size: 0.8rem !important; margin: 0 !important; }
            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); border-right: none; background: rgba(255, 255, 255, 0.9); backdrop-filter: blur(20px); align-items: center; justify-content: center; }
            .nav-menu { display: flex; flex-direction: row; width: 100%; margin: 0; padding: 0 10px; overflow-x: auto; scroll-snap-type: x mandatory; gap: 6px; justify-content: flex-start; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu li { flex: 0 0 auto; scroll-snap-align: center; }
            .nav-menu a { flex-direction: column; padding: 10px 16px; gap: 6px; font-size: 0.75rem; font-weight: 800; border-radius: 20px; justify-content: center; align-items: center; background: transparent; }
            .nav-menu a i { font-size: 1.3rem; margin: 0; }
            .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; box-shadow: inset 0 0 0 1px rgba(16,185,129,0.2); }
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); display: flex; flex-direction: column; }
            .content-container { padding: 20px 15px; flex: 1; overflow-y: auto; }
            .market-toolbar { flex-direction: column; gap: 15px; align-items: stretch; margin-bottom: 20px; }
            .search-box { width: 100%; }
            .filter-btn { padding: 10px 18px; font-size: 0.85rem; }
            .product-grid { grid-template-columns: 1fr; gap: 20px; padding-bottom: 20px; }
            .bento-card { border-radius: var(--radius-lg); }
            .card-img-wrapper { height: 200px; }
        }
        @media (max-width: 480px) {
            .mobile-brand span { display: none; }
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Live Marketplace</div>
            
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
            
            <div class="market-toolbar">
                <div class="search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="searchInput" class="search-input" placeholder="Search crops, farms..." onkeyup="filterMarket()">
                </div>
                
                <div class="filter-pills">
                    <button class="filter-btn active" onclick="setFilter('all', this)">All Produce</button>
                    <button class="filter-btn" onclick="setFilter('for_sale', this)">Ready to Buy</button>
                    <button class="filter-btn" onclick="setFilter('growing', this)">Pre-Book / Growing</button>
                </div>
            </div>

            <?php if($result && $result->num_rows > 0): ?>
                <div class="product-grid" id="productGrid">
                    <?php while($row = $result->fetch_assoc()): ?>
                        <?php
                            $img = !empty($row['image']) ? $row['image'] : 'placeholder.jpg';
                            $rating = $row['avg_rating'] ? number_format($row['avg_rating'], 1) : 'New';
                            $reviews = $row['review_count'];
                            $is_verified = ($row['badge'] == 'verified');
                            $status_class = ($row['status'] == 'for_sale') ? 'status-sale' : 'status-growing';
                            $status_text = ($row['status'] == 'for_sale') ? '<i class="fa-solid fa-tags"></i> Ready' : '<i class="fa-solid fa-seedling"></i> Pre-Book';
                        ?>
                        <div class="bento-card" data-status="<?php echo $row['status']; ?>" data-name="<?php echo strtolower($row['crop_name'] . ' ' . $row['farmer_name']); ?>">
                            
                            <a href="crop_details.php?id=<?php echo $row['id']; ?>" class="card-img-wrapper">
                                <div class="status-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></div>
                                <div class="stock-badge"><i class="fa-solid fa-boxes-stacked" style="color:#10b981;"></i> <?php echo $row['quantity']; ?> kg Available</div>
                                <img src="../uploads/<?php echo $img; ?>" alt="Crop Image" class="card-img">
                            </a>

                            <div class="card-body">
                                <h3 class="product-title"><?php echo htmlspecialchars($row['crop_name']); ?></h3>
                                
                                <div class="farmer-info">
                                    <i class="fa-solid fa-tractor" style="opacity: 0.5;"></i> <?php echo htmlspecialchars($row['farmer_name']); ?>
                                    <?php if($is_verified): ?>
                                        <i class="fa-solid fa-circle-check verified-badge" title="Verified Farmer"></i>
                                    <?php endif; ?>
                                    <a href="farmer_profile.php?id=<?php echo $row['farmer_id']; ?>" class="profile-link">View Profile</a>
                                </div>

                                <div class="price-row">
                                    <div class="price">₹<?php echo number_format($row['market_price']); ?> <span class="unit">/ kg</span></div>
                                    <div class="rating-box">
                                        <i class="fa-solid fa-star"></i> <?php echo $rating; ?> 
                                        <span style="font-size:0.8rem; opacity:0.6; font-weight: 600;">(<?php echo $reviews; ?>)</span>
                                    </div>
                                </div>

                                <form method="POST" action="add_to_cart.php" style="margin-top: auto; display: flex; flex-direction: column;">
                                    <input type="hidden" name="crop_id" value="<?php echo $row['id']; ?>">
                                    <input type="hidden" name="booking_type" value="<?php echo ($row['status'] == 'for_sale') ? 'full' : 'advance'; ?>">
                                    
                                    <div class="qty-control">
                                        <span style="font-size: 0.85rem; font-weight: 800; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Quantity (kg)</span>
                                        <input type="number" name="quantity" min="1" max="<?php echo $row['quantity']; ?>" value="1" class="qty-input" required title="You cannot order more than what is available in stock.">
                                    </div>

                                    <div class="card-actions">
                                        <a href="crop_details.php?id=<?php echo $row['id']; ?>" class="btn-view">Details</a>
                                        <button type="submit" class="btn-cart"><i class="fa-solid fa-cart-plus"></i> Add to Cart</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div style="text-align: center; padding: 80px 20px; background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color); box-shadow: var(--shadow-subtle);">
                    <i class="fa-solid fa-store-slash" style="font-size: 4rem; color: var(--border-color); margin-bottom: 20px;"></i>
                    <h3 style="color: var(--text-main); font-size: 1.6rem; margin-bottom: 10px; font-weight: 900;" class="logo-text">Marketplace is Empty</h3>
                    <p style="color: var(--text-muted); font-size: 1.05rem; font-weight: 500;">Farmers haven't listed any crops yet. Please check back later!</p>
                </div>
            <?php endif; ?>

        </div>
    </main>
</div>

<script>
let currentFilter = 'all';

function setFilter(status, btnElement) {
    currentFilter = status;
    document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
    btnElement.classList.add('active');
    filterMarket();
}

function filterMarket() {
    let searchQuery = document.getElementById("searchInput").value.toLowerCase();
    let cards = document.querySelectorAll(".bento-card");

    cards.forEach(card => {
        let nameData = card.getAttribute('data-name');
        let statusData = card.getAttribute('data-status');
        
        let matchesSearch = nameData.includes(searchQuery);
        let matchesFilter = (currentFilter === 'all' || statusData === currentFilter);

        if (matchesSearch && matchesFilter) {
            card.style.display = "flex";
        } else {
            card.style.display = "none";
        }
    });
}

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

<style>
@media (max-width: 768px) {
    .desktop-only-text { display: none; }
}
</style>

</body>
</html>