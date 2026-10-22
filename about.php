<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>About Us | Farmogram</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('farmogram_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        /* =========================================================
           ULTRA-MODERN GLASS-BENTO UI - ABOUT PAGE
        ========================================================= */
        :root {
            --primary: #10b981; 
            --primary-hover: #059669;
            --dark: #0f172a; 
            --text-main: #1e293b;
            --text-muted: #64748b;
            --bg-light: #f8fafc;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --radius-xl: 32px;
            --radius-lg: 24px;
            --radius-md: 16px;
            --shadow-subtle: 0 10px 40px -10px rgba(0,0,0,0.08);
            --shadow-hover: 0 20px 40px -10px rgba(0,0,0,0.12);
            --transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        body { background-color: var(--bg-light); color: var(--text-main); line-height: 1.6; overflow-x: hidden; transition: background-color 0.3s ease; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 0 5%; }

        /* Premium Buttons */
        .btn { padding: 16px 32px; border-radius: 50px; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; gap: 10px; transition: var(--transition); cursor: pointer; border: none; font-size: 1.05rem; font-family: inherit; }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3); }
        .btn-primary:hover { background: var(--primary-hover); transform: translateY(-3px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.4); }

        /* Glassmorphic Sticky Navbar */
        .navbar-wrapper { position: sticky; top: 0; z-index: 1000; background: rgba(248, 250, 252, 0.85); backdrop-filter: blur(20px); -webkit-backdrop-filter: blur(20px); border-bottom: 1px solid var(--border); transition: 0.3s; }
        .navbar { display: flex; justify-content: space-between; align-items: center; padding: 15px 0; }
        
        .brand-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; }
        .logo-light { display: block; width: 40px; height: 40px; object-fit: contain; }
        .logo-dark { display: none; width: 40px; height: 40px; object-fit: contain; }
        .logo-text { font-size: 1.8rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }

        .nav-links { display: flex; gap: 40px; }
        .nav-links a { text-decoration: none; color: var(--text-muted); font-weight: 800; font-size: 0.95rem; transition: var(--transition); text-transform: uppercase; letter-spacing: 0.5px; }
        .nav-links a:hover { color: var(--primary); }
        .nav-actions { display: flex; gap: 15px; align-items: center; }

        /* Topbar Controls */
        .theme-btn { background: var(--card-bg); border: 2px solid var(--border); color: var(--text-main); width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 2px solid var(--border) !important; border-radius: 50px !important; padding: 10px 20px !important; font-size: 0.9rem !important; font-weight: 800 !important; cursor: pointer; transition: 0.3s; color: var(--text-main) !important; box-shadow: var(--shadow-subtle); display: inline-flex !important; align-items: center; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* Bento Card Global Class */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border); box-shadow: var(--shadow-subtle); padding: 40px; transition: var(--transition); }
        .bento-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); border-color: var(--primary); }

        /* About Hero */
        .about-hero { padding: 100px 0 60px; text-align: center; max-width: 900px; margin: 0 auto; }
        .about-hero h1 { font-size: 4.5rem; font-weight: 900; line-height: 1.1; letter-spacing: -2px; margin-bottom: 25px; color: var(--text-main); transition: 0.3s; }
        .about-hero h1 span { color: var(--primary); }
        .about-hero p { color: var(--text-muted); font-size: 1.3rem; margin-bottom: 40px; font-weight: 500; transition: 0.3s; line-height: 1.7; }
        
        .hero-banner { width: 100%; height: 500px; object-fit: cover; border-radius: var(--radius-xl); box-shadow: var(--shadow-subtle); border: 1px solid var(--border); margin-bottom: 80px; }

        /* Value Props */
        .values-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; margin-bottom: 100px; }
        .value-card { text-align: left; padding: 50px 40px; position: relative; overflow: hidden; }
        .value-icon { width: 80px; height: 80px; background: rgba(16, 185, 129, 0.1); color: var(--primary); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; margin-bottom: 30px; border: 1px solid rgba(16, 185, 129, 0.2); transition: 0.3s; }
        .value-card:hover .value-icon { transform: scale(1.1) rotate(5deg); background: var(--primary); color: #fff; }
        .value-card h3 { font-size: 1.8rem; font-weight: 900; color: var(--text-main); margin-bottom: 15px; letter-spacing: -0.5px; transition: 0.3s; }
        .value-card p { font-size: 1.1rem; color: var(--text-muted); font-weight: 500; transition: 0.3s; line-height: 1.6; }

        /* Mission Section */
        .mission-section { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; margin-bottom: 100px; }
        .mission-image { width: 100%; border-radius: var(--radius-xl); box-shadow: var(--shadow-subtle); border: 8px solid var(--card-bg); transform: rotate(-2deg); transition: 0.3s; }
        .mission-image:hover { transform: rotate(0deg); border-color: var(--primary); }
        .mission-content h2 { font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; line-height: 1.1; letter-spacing: -1.5px; transition: 0.3s; }
        .mission-content p { color: var(--text-muted); margin-bottom: 30px; font-weight: 500; font-size: 1.2rem; transition: 0.3s; line-height: 1.7; }
        
        .timeline { border-left: 2px dashed var(--border); margin-left: 20px; padding-left: 30px; margin-top: 40px; }
        .timeline-item { position: relative; margin-bottom: 30px; }
        .timeline-item::before { content: ''; position: absolute; left: -40px; top: 5px; width: 18px; height: 18px; background: var(--primary); border-radius: 50%; outline: 4px solid var(--bg-light); }
        .timeline-item h4 { font-size: 1.2rem; font-weight: 800; color: var(--text-main); margin-bottom: 5px; }
        .timeline-item p { font-size: 1rem; color: var(--text-muted); font-weight: 500; margin-bottom: 0; }

        /* Developer Section */
        .developer-section { margin-bottom: 100px; }
        .dev-card { display: flex; align-items: center; gap: 50px; background: linear-gradient(135deg, rgba(16, 185, 129, 0.05) 0%, rgba(15, 23, 42, 0.02) 100%); padding: 60px; border-radius: var(--radius-xl); border: 1px solid var(--border); box-shadow: var(--shadow-subtle); transition: var(--transition); }
        .dev-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); border-color: rgba(16, 185, 129, 0.3); }
        .dev-photo-wrapper { flex-shrink: 0; position: relative; }
        .dev-photo { width: 280px; height: 280px; border-radius: 50%; object-fit: cover; border: 8px solid var(--card-bg); box-shadow: 0 20px 40px -10px rgba(0,0,0,0.15); transition: 0.3s; }
        .dev-photo-wrapper::after { content: ''; position: absolute; bottom: 15px; right: 15px; width: 40px; height: 40px; background: var(--primary); border-radius: 50%; border: 4px solid var(--card-bg); }
        .dev-content h2 { font-size: 3rem; font-weight: 900; color: var(--text-main); margin-bottom: 15px; letter-spacing: -1px; transition: 0.3s; }
        .dev-content h4 { font-size: 1.2rem; color: var(--primary); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px; }
        .dev-content p { color: var(--text-muted); font-size: 1.15rem; line-height: 1.8; font-weight: 500; margin-bottom: 25px; transition: 0.3s; }

        /* Footer */
        .footer { padding: 80px 0 40px; border-top: 1px solid var(--border); transition: 0.3s; background: var(--bg-light); }
        .footer-grid { display: grid; grid-template-columns: 2.5fr 1fr 1fr 1fr; gap: 50px; margin-bottom: 60px; }
        .footer-brand p { color: var(--text-muted); font-size: 1.05rem; font-weight: 500; margin: 25px 0; max-width: 350px; transition: 0.3s; line-height: 1.6; }
        .social-links { display: flex; gap: 15px; }
        .social-links a { color: var(--text-muted); font-size: 1.3rem; transition: var(--transition); background: var(--card-bg); width: 50px; height: 50px; display: flex; align-items: center; justify-content: center; border-radius: 50%; border: 1px solid var(--border); box-shadow: var(--shadow-subtle); }
        .social-links a:hover { color: #fff; background: var(--primary); border-color: var(--primary); transform: translateY(-4px); box-shadow: var(--shadow-hover); }
        
        .footer-col h4 { font-size: 1.15rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; text-transform: uppercase; letter-spacing: 0.5px; transition: 0.3s; }
        .footer-col ul { list-style: none; }
        .footer-col ul li { margin-bottom: 18px; }
        .footer-col ul a { text-decoration: none; color: var(--text-muted); font-size: 1.05rem; font-weight: 600; transition: var(--transition); }
        .footer-col ul a:hover { color: var(--primary); padding-left: 8px; }
        
        .footer-bottom { display: flex; justify-content: space-between; align-items: center; padding-top: 35px; border-top: 1px solid var(--border); font-size: 1rem; font-weight: 600; color: var(--text-muted); transition: 0.3s; }

        /* Dark Mode Overrides */
        [data-theme="dark"] {
            --bg-light: #0f172a !important;
            --card-bg: #1e293b !important;
            --text-main: #f8fafc !important;
            --text-muted: #94a3b8 !important;
            --border: rgba(255,255,255,0.05) !important;
        }
        
        [data-theme="dark"] .navbar-wrapper { background: rgba(15, 23, 42, 0.85) !important; border-bottom-color: var(--border) !important; }
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }
        [data-theme="dark"] .goog-te-gadget-simple { color: #f8fafc !important; }
        [data-theme="dark"] .mission-image { border-color: var(--card-bg) !important; }

        /* Responsive */
        @media (max-width: 1024px) {
            .values-grid { grid-template-columns: 1fr; }
            .mission-section { grid-template-columns: 1fr; text-align: center; }
            .timeline { text-align: left; }
            .about-hero h1 { font-size: 3.5rem; }
            .footer-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-brand { grid-column: span 2; }
            .dev-card { flex-direction: column; text-align: center; padding: 40px 30px; gap: 30px; }
        }
        @media (max-width: 768px) {
            .nav-links { display: none; }
            .about-hero { padding: 60px 0 30px; }
            .footer-grid { grid-template-columns: 1fr; text-align: center; gap: 40px; }
            .footer-brand { grid-column: 1; margin: 0 auto; }
            .social-links { justify-content: center; }
            .footer-bottom { flex-direction: column; gap: 15px; }
        }
    </style>
</head>
<body>

<div class="navbar-wrapper">
    <div class="container">
        <header class="navbar">
            <a href="index.php" class="brand-logo">
                <img src="assets/images/logo-light.png" alt="Farmogram" class="logo-light">
                <img src="assets/images/logo-dark.png" alt="Farmogram" class="logo-dark">
                <span class="logo-text">Farmogram</span>
            </a>
            
            <nav class="nav-links">
                <a href="index.php#how-it-works">Marketplace</a>
                <a href="about.php">About Us</a>
                <a href="auth/login.php">AI Diagnostics</a>
            </nav>
            
            <div class="nav-actions">
                <div id="google_translate_element" title="Change Language"></div>
                
                <button class="theme-btn" id="themeToggle" title="Toggle Theme">
                    <i class="fa-solid fa-moon dark-icon"></i>
                    <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
                </button>

                <a href="auth/login.php" style="text-decoration: none; color: var(--text-main); font-weight: 800; margin-right: 10px; transition: 0.3s;" class="login-link">Login</a>
                <a href="auth/register.php" class="btn btn-primary">Join Free</a>
            </div>
        </header>
    </div>
</div>

<main>
    <section class="about-hero container">
        <h1>Transforming How India <span>Groceries</span></h1>
        <p>At Farmogram, we believe in a world where the people who feed us are paid fairly, and businesses have transparent access to high quality, farm-fresh produce.</p>
    </section>

    <div class="container">
        <img src="https://images.unsplash.com/photo-1595841696677-6489ffa3f66c?q=80&w=1500&auto=format&fit=crop" class="hero-banner" alt="Farm Aerial View">
    </div>

    <section class="container values-grid">
        <div class="bento-card value-card">
            <div class="value-icon"><i class="fa-solid fa-scale-balanced"></i></div>
            <h3>Fair Trade Guaranteed</h3>
            <p>We eliminated the exploitative middleman commission layer. 100% of the negotiated price goes directly from the buyer to the farmer's pocket.</p>
        </div>
        <div class="bento-card value-card">
            <div class="value-icon" style="color: #3b82f6; background: rgba(59, 130, 246, 0.1); border-color: rgba(59, 130, 246, 0.2);"><i class="fa-solid fa-shield-halved"></i></div>
            <h3>Pre-Book Security</h3>
            <p>Farming is unpredictable. We introduced the world's first micro-contracting system. Buyers pay 20% advance, giving farmers early essential capital.</p>
        </div>
        <div class="bento-card value-card">
            <div class="value-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.1); border-color: rgba(245, 158, 11, 0.2);"><i class="fa-solid fa-microchip"></i></div>
            <h3>AI Powered Insight</h3>
            <p>We blend ancient agrarian practices with modern AI diagnostics. Farmers diagnose crop diseases instantly through our ecosystem.</p>
        </div>
    </section>

    <section class="container mission-section">
        <div class="mission-content">
            <h2>The story of Farmogram.</h2>
            <p>What started as a college thesis has now blossomed into a nationwide network. Farmogram was built to solve the systemic supply-chain issues plaguing rural agriculture.</p>
            
            <div class="timeline">
                <div class="timeline-item">
                    <h4>The Observation</h4>
                    <p>We noticed urban restaurants paying heavy premiums for vegetables while the farmers who grew them were struggling with massive debt.</p>
                </div>
                <div class="timeline-item">
                    <h4>The Prototype</h4>
                    <p>We launched a basic secure chat platform to connect five local farmers directly with three wholesale buyers.</p>
                </div>
                <div class="timeline-item">
                    <h4>The Farmogram Ecosystem</h4>
                    <p>Today we are an end-to-end B2B platform with built-in banking integration, Google Maps tracking, and AI-driven growth analysis.</p>
                </div>
            </div>
        </div>
        <div>
            <img src="https://images.unsplash.com/photo-1500937386664-56d1dfef3854?q=80&w=800&auto=format&fit=crop" class="mission-image" alt="Farmers working in the field">
        </div>
    </section>

    <section class="container developer-section">
        <div class="dev-card bento-card">
            <div class="dev-photo-wrapper">
                <!-- Note: The image below is a placeholder. Replace the 'src' attribute with your actual photo path later. -->
                <img src="devloper.jpeg" alt="Developer Photo" class="dev-photo">
            </div>
            <div class="dev-content">
                <h2>Meet the Developer</h2>
                <h4>Architect & Creator</h4>
                <p>"Farmogram isn't just a project for me; it's a personal mission to bridge the painful gap between rural agriculture and modern technology. Growing up, it was clear how much immense hard work farmers put into their crops, only to be drastically underpaid by aggressive middlemen.</p>
                <p>I built Farmogram from the ground up to give power back to the producers. By writing code that connects fields directly to restaurants and households, I want to ensure fair trade and financial security for the very people who feed our nation. This platform is my contribution towards a balanced, sustainable future."</p>
                <div style="font-family: monospace; font-size: 1.1rem; font-weight: 800; color: var(--text-main); margin-top: 10px;">- Code Crafted with Passion</div>
            </div>
        </div>
    </section>
</main>

<footer class="footer">
    <div class="container">
        <div class="footer-grid">
            <div class="footer-brand">
                <a href="index.php" class="brand-logo" style="margin-bottom: 20px;">
                    <img src="assets/images/logo-light.png" alt="Farmogram" class="logo-light">
                    <img src="assets/images/logo-dark.png" alt="Farmogram" class="logo-dark">
                    <span class="logo-text">Farmogram</span>
                </a>
                <p>Bridging the gap between rural agriculture and modern digital supply chains. Fair pricing, guaranteed.</p>
                <div class="social-links">
                    <a href="#"><i class="fa-brands fa-x-twitter"></i></a>
                    <a href="#"><i class="fa-brands fa-instagram"></i></a>
                    <a href="#"><i class="fa-brands fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="footer-col">
                <h4>Platform</h4>
                <ul>
                    <li><a href="index.php#how-it-works">B2B Marketplace</a></li>
                    <li><a href="#">Pre-Book Contracts</a></li>
                    <li><a href="#">Task Scheduler</a></li>
                    <li><a href="auth/login.php">AI Diagnostics</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4>Company</h4>
                <ul>
                    <li><a href="about.php">About Farmogram</a></li>
                    <li><a href="#">Our Mission</a></li>
                    <li><a href="#">Trust & Safety</a></li>
                    <li><a href="#">Press Kit</a></li>
                </ul>
            </div>
            
            <div class="footer-col">
                <h4>Support</h4>
                <ul>
                    <li><a href="#">Farmer Helpline</a></li>
                    <li><a href="#">Buyer Protection</a></li>
                    <li><a href="#">System Status</a></li>
                    <li><a href="#">Documentation</a></li>
                </ul>
            </div>
        </div>
        
        <div class="footer-bottom">
            <div>&copy; <?php echo date("Y"); ?> Farmogram Digital Marketplace. All rights reserved.</div>
            <div style="display: flex; gap: 20px; align-items: center;">
                <span style="display: flex; align-items: center; gap: 8px;"><i class="fa-solid fa-shield-halved" style="color: var(--primary);"></i> End-to-End Encrypted</span>
            </div>
        </div>
    </div>
</footer>

<script type="text/javascript">
function googleTranslateElementInit() {
    new google.translate.TranslateElement({
        pageLanguage: 'en',
        includedLanguages: 'en,hi,kn,te,ta,mr,gu,pa,bn',
        layout: google.translate.TranslateElement.InlineLayout.SIMPLE,
        autoDisplay: false
    }, 'google_translate_element');
}
</script>
<script type="text/javascript" src="https://translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

<script>
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

<style>
@media (max-width: 400px) {
    .login-link { display: none !important; }
}
</style>

</body>
</html>
