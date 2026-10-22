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

$order_id = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

// Get the primary farmer from this order
$farmer_query = $conn->query("
    SELECT crops.farmer_id, users.name as farmer_name 
    FROM order_items 
    JOIN crops ON order_items.crop_id = crops.id 
    JOIN users ON crops.farmer_id = users.id
    WHERE order_items.order_id = $order_id LIMIT 1
");

if($farmer_query->num_rows == 0) die("Order data error or order not found.");
$farmer_data = $farmer_query->fetch_assoc();
$farmer_id = $farmer_data['farmer_id'];
$farmer_avatar = strtoupper(substr($farmer_data['farmer_name'], 0, 1));

if(isset($_POST['submit_review'])){
    $rating = (int)$_POST['rating'];
    $comment = $conn->real_escape_string($_POST['comment']);
    
    $conn->query("INSERT INTO reviews (order_id, farmer_id, buyer_id, rating, comment) VALUES ($order_id, $farmer_id, $buyer_id, $rating, '$comment')");
    header("Location: orders.php?msg=reviewed");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Leave a Review | Farmogram</title>
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

        /* STRICT FLEXBOX LAYOUT */
        body { margin: 0; overflow: hidden; background-color: var(--bg-light); color: var(--text-main); font-family: 'Inter', sans-serif; transition: 0.3s; }
        .app-layout { display: flex; height: 100vh; overflow: hidden; }
        .main-wrapper { flex: 1; display: flex; flex-direction: column; height: 100vh; overflow: hidden; }
        .topbar { flex-shrink: 0; }
        
        /* Centering Container for Review Card */
        .content-container { flex: 1; overflow-y: auto; padding: 30px 40px; display: flex; justify-content: center; align-items: center; width: 100%; min-height: 0; }

        /* Custom Translation Widget Styling */
        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* =========================================================
           BENTO REVIEW CARD
        ========================================================= */
        .review-card { 
            background: var(--card-bg); 
            width: 100%; 
            max-width: 550px; 
            border-radius: var(--radius-xl); 
            box-shadow: var(--shadow-subtle); 
            border: 1px solid var(--border-color);
            position: relative;
            text-align: center;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            margin: auto;
        }
        .review-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        .review-header {
            background: var(--bg-light);
            padding: 40px 20px 30px 20px;
            border-radius: var(--radius-xl) var(--radius-xl) 0 0;
            border-bottom: 2px dashed var(--border-color);
            position: relative;
            transition: 0.3s;
        }

        .farmer-avatar {
            width: 85px;
            height: 85px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            font-weight: 900;
            position: absolute;
            bottom: -42.5px;
            left: 50%;
            transform: translateX(-50%);
            border: 6px solid var(--card-bg);
            transition: 0.3s;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.15);
        }

        .review-body { padding: 60px 40px 40px 40px; }

        .review-body h2 { margin: 0 0 10px 0; color: var(--text-main); font-size: 1.8rem; font-weight: 900; letter-spacing: -0.5px; transition: 0.3s; }
        .review-body p { color: var(--text-muted); font-size: 1.05rem; margin-bottom: 35px; font-weight: 500; transition: 0.3s; }
        .review-body strong { color: var(--text-main); font-weight: 800; transition: 0.3s; text-transform: capitalize; }

        /* Interactive Stars */
        .star-rating {
            display: flex;
            justify-content: center;
            gap: 12px;
            flex-direction: row-reverse; /* CSS hover filling trick */
            margin-bottom: 30px;
        }
        
        .star-rating input { display: none; }
        
        .star-rating label {
            color: var(--border-color);
            font-size: 3rem;
            cursor: pointer;
            transition: 0.2s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }

        /* Highlight stars on hover or when checked */
        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input:checked ~ label {
            color: #f59e0b; /* Bright Gold */
            transform: scale(1.15);
        }

        .feedback-textarea {
            width: 100%;
            padding: 20px;
            border-radius: var(--radius-md);
            border: 2px solid var(--border-color);
            margin-bottom: 25px;
            font-family: inherit;
            outline: none;
            font-size: 1rem;
            resize: vertical;
            transition: 0.3s;
            background: var(--bg-light);
            color: var(--text-main);
            font-weight: 500;
        }
        .feedback-textarea:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
            background: var(--card-bg);
        }
        .feedback-textarea::placeholder { color: var(--text-muted); opacity: 0.7; font-weight: 500; }

        .btn-submit {
            background: var(--primary);
            color: #fff;
            border: none;
            padding: 18px;
            width: 100%;
            border-radius: var(--radius-md);
            font-weight: 900;
            font-size: 1.15rem;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }
        .btn-submit:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-cancel {
            display: inline-block;
            margin-top: 20px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: 0.2s;
        }
        .btn-cancel:hover { color: #ef4444; }

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
        [data-theme="dark"] .goog-te-gadget-simple { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .review-card { background-color: var(--sidebar-bg) !important; box-shadow: 0 10px 30px rgba(0,0,0,0.4) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .review-header { background-color: rgba(0,0,0,0.2) !important; border-bottom-color: var(--border-color) !important; }
        [data-theme="dark"] .farmer-avatar { border-color: var(--sidebar-bg) !important; }
        [data-theme="dark"] .star-rating label { color: rgba(255,255,255,0.1); }
        
        [data-theme="dark"] .feedback-textarea { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .feedback-textarea:focus { border-color: var(--primary) !important; background: var(--sidebar-bg) !important; }
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] .btn-cancel { color: var(--text-muted); }
        [data-theme="dark"] .btn-cancel:hover { color: #ef4444; }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .review-card, .review-header, .feedback-textarea, h1, h2, h3, p, span, div, .btn-cancel { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
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
            .content-container { padding: 20px 15px; align-items: flex-start; } /* Allow scrolling if keyboard opens */
            
            .review-card { border-radius: var(--radius-lg); margin-top: 15px; margin-bottom: 25px; }
            .review-header { padding: 30px 20px 25px 20px; border-radius: var(--radius-lg) var(--radius-lg) 0 0; }
            .review-body { padding: 50px 25px 30px 25px; }
            .review-body h2 { font-size: 1.5rem; }
            .star-rating label { font-size: 2.3rem; }
        }

        /* EXTRA SMALL SCREEN FIX */
        @media (max-width: 480px) {
            .mobile-brand span { display: block; font-size: 1.1rem; }
            .goog-te-gadget-simple { padding: 4px 8px !important; font-size: 0.75rem !important; }
            .star-rating label { font-size: 2rem; }
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
            <li><a href="orders.php" class="active"><i class="fa-solid fa-box-open"></i> <span>Orders</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.6rem;">Leave a Review</div>
            
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
            
            <div class="review-card">
                <div class="review-header">
                    <div style="font-size: 0.85rem; text-transform: uppercase; letter-spacing: 1px; font-weight: 800; color: var(--text-muted); margin-bottom: 8px;">Order #ORD-<?php echo $order_id; ?></div>
                    <div style="font-size: 1.3rem; font-weight: 900; color: var(--text-main);"><i class="fa-solid fa-seedling" style="color: var(--primary); margin-right: 5px;"></i> Farmogram Verification</div>
                    <div class="farmer-avatar"><?php echo $farmer_avatar; ?></div>
                </div>

                <div class="review-body">
                    <h2>Rate Your Experience</h2>
                    <p>How was your transaction with <strong><?php echo htmlspecialchars($farmer_data['farmer_name']); ?></strong>?</p>
                    
                    <form method="POST">
                        
                        <div class="star-rating">
                            <input type="radio" name="rating" id="star5" value="5" checked>
                            <label for="star5" title="5 Stars - Excellent"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star4" value="4">
                            <label for="star4" title="4 Stars - Very Good"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star3" value="3">
                            <label for="star3" title="3 Stars - Average"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star2" value="2">
                            <label for="star2" title="2 Stars - Poor"><i class="fa-solid fa-star"></i></label>
                            
                            <input type="radio" name="rating" id="star1" value="1">
                            <label for="star1" title="1 Star - Terrible"><i class="fa-solid fa-star"></i></label>
                        </div>

                        <textarea name="comment" rows="4" class="feedback-textarea" placeholder="Leave a comment about the crop quality, packaging, or delivery..."></textarea>
                        
                        <button type="submit" name="submit_review" class="btn-submit">Submit Rating <i class="fa-solid fa-paper-plane"></i></button>
                        <a href="orders.php" class="btn-cancel">Cancel & Go Back</a>
                    </form>
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