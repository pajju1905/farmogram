<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Farmogram | The Digital Agriculture Ecosystem</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/main.css">
    <?php
        // Dynamic base path: /farmogram for localhost, empty for live site
        $basePath = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? '/farmogram' : '';
    ?>
    <link rel="manifest" href="<?php echo $basePath; ?>/manifest.php">
    <meta name="theme-color" content="#10b981">
    <link rel="apple-touch-icon" href="<?php echo $basePath; ?>/assets/images/logo-light.png">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('farmogram_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        /* =========================================================
           ULTRA-MODERN GLASS-BENTO UI - LANDING PAGE
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
        .btn-white { background: var(--card-bg); color: var(--text-main); border: 2px solid var(--border); box-shadow: var(--shadow-subtle); }
        .btn-white:hover { box-shadow: var(--shadow-hover); transform: translateY(-3px); border-color: var(--primary); color: var(--primary); }
        .btn-dark { background: rgba(255,255,255,0.1); color: #fff; border: 1px solid rgba(255,255,255,0.2); backdrop-filter: blur(10px); }
        .btn-dark:hover { background: rgba(255,255,255,0.2); transform: translateY(-3px); }

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

        /* Cinematic Hero Section */
        .hero { position: relative; padding: 120px 0; overflow: hidden; }
        .hero::before { content: ''; position: absolute; top: -100px; left: 50%; transform: translateX(-50%); width: 1000px; height: 1000px; background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, rgba(250, 250, 250, 0) 70%); z-index: -1; transition: 0.3s; }
        .hero-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 80px; align-items: center; }
        
        .badge { display: inline-flex; align-items: center; gap: 8px; background: rgba(16, 185, 129, 0.1); color: var(--primary); padding: 10px 20px; border-radius: 50px; font-size: 0.85rem; font-weight: 900; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 25px; border: 1px solid rgba(16, 185, 129, 0.2); box-shadow: 0 4px 15px rgba(16, 185, 129, 0.1); }
        .hero h1 { font-size: 5rem; font-weight: 900; line-height: 1.1; letter-spacing: -2px; margin-bottom: 25px; color: var(--text-main); transition: 0.3s; }
        .hero h1 span { color: var(--primary); }
        .hero p { color: var(--text-muted); font-size: 1.2rem; margin-bottom: 40px; max-width: 550px; font-weight: 500; transition: 0.3s; line-height: 1.7; }
        
        .hero-image-box { position: relative; border-radius: 40px; overflow: hidden; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.2); border: 8px solid var(--card-bg); transform: perspective(1000px) rotateY(-5deg) rotateX(2deg); transition: var(--transition); }
        .hero-image-box:hover { transform: perspective(1000px) rotateY(0deg) rotateX(0deg); border-color: var(--primary); }
        .hero-image-box img { width: 100%; display: block; object-fit: cover; height: 500px; }
        
        .floating-stat { position: absolute; bottom: -20px; left: -30px; background: var(--card-bg); padding: 20px 30px; border-radius: var(--radius-lg); display: flex; align-items: center; gap: 20px; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); border: 1px solid var(--border); transform: rotate(-3deg); transition: 0.3s; z-index: 2; }
        .stat-icon { width: 60px; height: 60px; background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 1.8rem; border: 1px solid rgba(245, 158, 11, 0.3); }

        /* Bento Stats Bar */
        .stats-bar { display: grid; grid-template-columns: repeat(3, 1fr); gap: 30px; margin-top: 100px; }
        .stat-card { background: var(--card-bg); padding: 40px; border-radius: var(--radius-xl); display: flex; align-items: center; gap: 25px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border); transition: var(--transition); }
        .stat-card:hover { transform: translateY(-8px); box-shadow: var(--shadow-hover); border-color: var(--primary); }
        .stat-card .icon { background: rgba(16, 185, 129, 0.15); color: var(--primary); width: 75px; height: 75px; border-radius: 24px; display: flex; align-items: center; justify-content: center; font-size: 2.2rem; border: 1px solid rgba(16, 185, 129, 0.2); }
        .stat-card h3 { font-size: 2.5rem; font-weight: 900; color: var(--text-main); line-height: 1; letter-spacing: -1px; transition: 0.3s; margin-bottom: 5px; }
        .stat-card p { font-size: 1rem; font-weight: 800; color: var(--text-muted); transition: 0.3s; text-transform: uppercase; letter-spacing: 0.5px; }

        /* Bento Steps */
        .steps-section { padding: 120px 0; text-align: center; }
        .steps-section h2 { font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 20px; letter-spacing: -1.5px; transition: 0.3s; }
        .steps-section > p { color: var(--text-muted); margin-bottom: 70px; font-weight: 500; font-size: 1.2rem; transition: 0.3s; max-width: 700px; margin-left: auto; margin-right: auto; }
        .steps-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 40px; position: relative; }
        
        .step-icon { width: 100px; height: 100px; background: rgba(16, 185, 129, 0.1); color: var(--primary); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2.8rem; margin: 0 auto 30px; border: 2px solid rgba(16, 185, 129, 0.2); transition: 0.3s; box-shadow: inset 0 0 0 6px var(--card-bg); }
        .bento-card:hover .step-icon { transform: scale(1.1); background: var(--primary); color: #fff; }
        .step-item h3 { font-size: 1.6rem; font-weight: 900; margin-bottom: 15px; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .step-item p { font-size: 1.1rem; color: var(--text-muted); font-weight: 500; transition: 0.3s; line-height: 1.6; }

        /* Testimonials */
        .testimonials { padding: 80px 0 120px 0; display: grid; grid-template-columns: 1fr 2fr; gap: 80px; align-items: center; }
        .testi-left h2 { font-size: 3.5rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; line-height: 1.1; letter-spacing: -1.5px; transition: 0.3s; }
        .testi-left p { color: var(--text-muted); margin-bottom: 40px; font-weight: 500; font-size: 1.2rem; transition: 0.3s;}
        
        .testi-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; }
        .stars { color: #f59e0b; margin-bottom: 25px; font-size: 1.2rem; }
        .testi-quote { font-style: italic; font-weight: 600; color: var(--text-main); font-size: 1.1rem; margin-bottom: 35px; line-height: 1.7; transition: 0.3s; }
        .testi-user { display: flex; align-items: center; gap: 15px; border-top: 1px dashed var(--border); padding-top: 20px; transition: 0.3s; }
        .testi-user h4 { font-size: 1.15rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }
        .testi-user span { font-size: 0.95rem; font-weight: 700; color: var(--text-muted); transition: 0.3s; }

        /* Premium CTA Section */
        .cta-section { padding: 0 0 100px 0; }
        .cta-box { background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%); border-radius: 40px; padding: 100px 50px; text-align: center; position: relative; overflow: hidden; box-shadow: 0 30px 60px -15px rgba(0,0,0,0.4); border: 1px solid rgba(255,255,255,0.05); }
        .cta-box::before { content: ''; position: absolute; top: -50%; left: -10%; width: 50%; height: 200%; background: rgba(255,255,255,0.03); transform: rotate(15deg); pointer-events: none; }
        .cta-box::after { content: ''; position: absolute; top: -50%; right: -10%; width: 50%; height: 200%; background: rgba(16, 185, 129, 0.1); transform: rotate(-15deg); pointer-events: none; }
        
        .cta-box h2 { color: #fff; font-size: 4rem; font-weight: 900; margin-bottom: 25px; position: relative; z-index: 1; letter-spacing: -1.5px; }
        .cta-box p { color: #94a3b8; font-size: 1.25rem; max-width: 700px; margin: 0 auto 40px; position: relative; z-index: 1; font-weight: 500; line-height: 1.6; }
        .cta-actions { display: flex; justify-content: center; gap: 20px; position: relative; z-index: 1; }

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

        /* =========================================================
           DARK MODE OVERRIDES
        ========================================================= */
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
        
        [data-theme="dark"] .hero::before { background: radial-gradient(circle, rgba(16, 185, 129, 0.1) 0%, rgba(15, 23, 42, 0) 70%) !important; }
        [data-theme="dark"] .hero-image-box { border-color: var(--card-bg) !important; }
        [data-theme="dark"] .goog-te-gadget-simple { color: #f8fafc !important; }

        /* =========================================================
           RESPONSIVE RULES
        ========================================================= */
        @media (max-width: 1200px) {
            .hero h1 { font-size: 4rem; }
            .hero-grid { gap: 40px; }
            .testimonials { grid-template-columns: 1fr; gap: 50px; }
            .testi-left { text-align: center; }
            .testi-left p { margin: 0 auto 30px auto; max-width: 600px; }
            .footer-grid { grid-template-columns: repeat(2, 1fr); }
            .footer-brand { grid-column: span 2; }
        }

        @media (max-width: 768px) {
            .navbar { padding: 15px 0; }
            .nav-links { display: none; } /* Hide on small mobile, rely on CTA buttons */
            .hero { padding: 60px 0; }
            .hero-grid { grid-template-columns: 1fr; text-align: center; gap: 60px; }
            .hero h1 { font-size: 3.2rem; }
            .hero p { margin: 0 auto 40px auto; }
            .hero-content .btn { width: 100%; margin-bottom: 15px; }
            .hero-content > div { flex-direction: column; }
            
            .floating-stat { left: 10px; right: 10px; bottom: -30px; justify-content: center; }
            
            .stats-bar { grid-template-columns: 1fr; margin-top: 80px; }
            .steps-section { padding: 80px 0; }
            .steps-section h2 { font-size: 2.8rem; }
            .steps-grid { grid-template-columns: 1fr; gap: 30px; }
            
            .testi-grid { grid-template-columns: 1fr; }
            
            .cta-box { padding: 60px 30px; border-radius: 30px; }
            .cta-box h2 { font-size: 2.5rem; }
            .cta-actions { flex-direction: column; }
            
            .footer-grid { grid-template-columns: 1fr; gap: 40px; }
            .footer-brand { grid-column: 1; text-align: center; }
            .footer-brand p { margin: 20px auto; }
            .social-links { justify-content: center; }
            .footer-bottom { flex-direction: column; gap: 20px; text-align: center; }
        }

        @media (max-width: 480px) {
            .hero h1 { font-size: 2.5rem; }
            .logo-text { display: none; }
            .btn { padding: 10px 16px; font-size: 0.9rem; }
            .login-link { font-size: 0.9rem; margin-right: 5px !important; }
            .theme-btn { width: 35px; height: 35px; font-size: 0.9rem; margin-right: 5px; }
            .goog-te-gadget-simple { padding: 4px 8px !important; font-size: 0.7rem !important; }
        }

        /* =========================================================
           GET THE APP SECTION
        ========================================================= */
        .app-section {
            padding: 120px 0;
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.04) 0%, rgba(59, 130, 246, 0.04) 100%);
            position: relative;
            overflow: hidden;
        }
        .app-section::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }
        .app-section::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--primary), transparent);
        }
        .app-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }
        .app-visual {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 580px;
        }

        /* 3D Phone Mockup */
        .phone-frame {
            position: relative;
            width: 280px;
            height: 570px;
            background: linear-gradient(145deg, #1e293b, #0f172a);
            border-radius: 44px;
            padding: 14px;
            box-shadow:
                0 50px 100px -20px rgba(0, 0, 0, 0.35),
                0 30px 60px -30px rgba(16, 185, 129, 0.25),
                inset 0 1px 0 rgba(255, 255, 255, 0.1),
                inset 0 -1px 0 rgba(0, 0, 0, 0.3);
            transform: perspective(1000px) rotateY(-8deg) rotateX(3deg);
            transition: var(--transition);
            animation: phoneFloat 6s ease-in-out infinite;
        }
        .phone-frame:hover {
            transform: perspective(1000px) rotateY(0deg) rotateX(0deg) scale(1.02);
        }
        .phone-frame::before {
            content: '';
            position: absolute;
            top: 14px;
            left: 50%;
            transform: translateX(-50%);
            width: 90px;
            height: 28px;
            background: #0f172a;
            border-radius: 0 0 18px 18px;
            z-index: 2;
        }
        .phone-frame::after {
            content: '';
            position: absolute;
            top: 22px;
            left: 50%;
            transform: translateX(-50%);
            width: 10px;
            height: 10px;
            background: #1e293b;
            border-radius: 50%;
            z-index: 3;
            box-shadow: 0 0 4px rgba(16, 185, 129, 0.4);
        }
        .phone-screen {
            width: 100%;
            height: 100%;
            border-radius: 32px;
            overflow: hidden;
            background: #fff;
        }
        .phone-screen img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        @keyframes phoneFloat {
            0%, 100% { transform: perspective(1000px) rotateY(-8deg) rotateX(3deg) translateY(0); }
            50% { transform: perspective(1000px) rotateY(-8deg) rotateX(3deg) translateY(-15px); }
        }

        /* Floating Badges Around Phone */
        .app-float {
            position: absolute;
            background: var(--card-bg);
            border: 1px solid var(--border);
            border-radius: 50px;
            padding: 12px 22px;
            display: flex;
            align-items: center;
            gap: 10px;
            font-weight: 800;
            font-size: 0.85rem;
            color: var(--text-main);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            animation: floatBadge 4s ease-in-out infinite;
            white-space: nowrap;
            z-index: 5;
            transition: 0.3s;
        }
        .app-float i { font-size: 1.1rem; }
        .app-float-1 { top: 60px; right: 10px; animation-delay: 0s; }
        .app-float-1 i { color: #3b82f6; }
        .app-float-2 { bottom: 130px; left: 10px; animation-delay: 1.5s; }
        .app-float-2 i { color: #f59e0b; }
        .app-float-3 { bottom: 50px; right: 30px; animation-delay: 3s; }
        .app-float-3 i { color: #10b981; }

        @keyframes floatBadge {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }

        /* Glow ring behind phone */
        .phone-glow {
            position: absolute;
            width: 320px;
            height: 320px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(16, 185, 129, 0.15) 0%, transparent 70%);
            z-index: 0;
            animation: pulseGlow 4s ease-in-out infinite;
        }
        @keyframes pulseGlow {
            0%, 100% { transform: scale(1); opacity: 0.6; }
            50% { transform: scale(1.15); opacity: 1; }
        }

        /* App Content */
        .app-content h2 {
            font-size: 3.5rem;
            font-weight: 900;
            color: var(--text-main);
            margin-bottom: 20px;
            letter-spacing: -1.5px;
            line-height: 1.1;
            transition: 0.3s;
        }
        .app-content h2 span { color: var(--primary); }
        .app-content > p {
            color: var(--text-muted);
            font-size: 1.15rem;
            margin-bottom: 40px;
            font-weight: 500;
            line-height: 1.7;
            max-width: 500px;
            transition: 0.3s;
        }

        /* App Features List */
        .app-features {
            display: flex;
            flex-direction: column;
            gap: 16px;
            margin-bottom: 40px;
        }
        .app-feature {
            display: flex;
            align-items: center;
            gap: 18px;
            padding: 16px 20px;
            background: var(--card-bg);
            border-radius: var(--radius-md);
            border: 1px solid var(--border);
            transition: var(--transition);
        }
        .app-feature:hover {
            border-color: var(--primary);
            transform: translateX(8px);
            box-shadow: var(--shadow-subtle);
        }
        .app-feature-icon {
            width: 50px;
            height: 50px;
            min-width: 50px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--primary);
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
            border: 1px solid rgba(16, 185, 129, 0.2);
        }
        .app-feature h4 {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 3px;
            transition: 0.3s;
        }
        .app-feature p {
            font-size: 0.88rem;
            color: var(--text-muted);
            font-weight: 600;
            transition: 0.3s;
        }

        /* App Buttons */
        .app-buttons {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
        }
        .app-install-btn {
            position: relative;
            overflow: hidden;
        }
        .app-install-btn::after {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: linear-gradient(45deg, transparent 40%, rgba(255,255,255,0.15) 50%, transparent 60%);
            animation: shimmer 3s infinite;
        }
        @keyframes shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        /* Platform Badges */
        .app-platforms {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        .app-platforms span {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
            font-weight: 700;
            color: var(--text-muted);
            transition: 0.3s;
        }
        .app-platforms span i {
            font-size: 1.1rem;
            color: var(--primary);
        }

        /* iOS Instructions Modal */
        .ios-modal-overlay {
            display: none;
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .ios-modal-overlay.active {
            display: flex;
        }
        .ios-modal {
            background: var(--card-bg);
            border-radius: var(--radius-xl);
            padding: 40px;
            max-width: 420px;
            width: 100%;
            box-shadow: 0 25px 60px rgba(0,0,0,0.3);
            border: 1px solid var(--border);
            text-align: center;
            position: relative;
            animation: modalSlideUp 0.4s ease;
        }
        @keyframes modalSlideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .ios-modal h3 {
            font-size: 1.5rem;
            font-weight: 900;
            color: var(--text-main);
            margin-bottom: 10px;
        }
        .ios-modal > p {
            color: var(--text-muted);
            margin-bottom: 30px;
            font-weight: 600;
        }
        .ios-steps { text-align: left; }
        .ios-step {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: rgba(16, 185, 129, 0.05);
            border-radius: 12px;
            margin-bottom: 12px;
            border: 1px solid rgba(16, 185, 129, 0.1);
        }
        .ios-step-num {
            width: 36px;
            height: 36px;
            min-width: 36px;
            background: var(--primary);
            color: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 900;
            font-size: 0.95rem;
        }
        .ios-step span {
            font-weight: 700;
            color: var(--text-main);
            font-size: 0.95rem;
        }
        .ios-step i { color: var(--primary); }
        .ios-modal-close {
            position: absolute;
            top: 15px; right: 20px;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--text-muted);
            cursor: pointer;
            padding: 5px;
            transition: 0.3s;
        }
        .ios-modal-close:hover { color: var(--primary); }

        /* App Section Responsive */
        @media (max-width: 1200px) {
            .app-content h2 { font-size: 2.8rem; }
            .app-grid { gap: 50px; }
        }
        @media (max-width: 768px) {
            .app-section { padding: 80px 0; }
            .app-grid {
                grid-template-columns: 1fr;
                gap: 50px;
            }
            .app-visual {
                min-height: 450px;
                order: -1;
            }
            .phone-frame {
                width: 220px;
                height: 440px;
                transform: perspective(1000px) rotateY(0deg) rotateX(0deg);
            }
            @keyframes phoneFloat {
                0%, 100% { transform: translateY(0); }
                50% { transform: translateY(-12px); }
            }
            .app-content { text-align: center; }
            .app-content h2 { font-size: 2.5rem; }
            .app-content > p { margin: 0 auto 40px auto; }
            .app-features { align-items: center; }
            .app-feature { width: 100%; }
            .app-buttons { justify-content: center; }
            .app-platforms { justify-content: center; }
            .app-float-1 { top: 20px; right: -10px; }
            .app-float-2 { bottom: 100px; left: -10px; }
            .app-float-3 { bottom: 20px; right: 0; }
        }
        @media (max-width: 480px) {
            .app-content h2 { font-size: 2rem; }
            .phone-frame { width: 180px; height: 360px; }
            .app-visual { min-height: 380px; }
            .app-float { padding: 8px 14px; font-size: 0.75rem; }
            .app-buttons { flex-direction: column; }
            .app-buttons .btn { width: 100%; }
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
                <a href="#how-it-works">Marketplace</a>
                <a href="about.php">About Us</a>
                <a href="#get-app" style="color: var(--primary);"><i class="fa-solid fa-mobile-screen-button" style="margin-right: 4px;"></i>Get App</a>
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
    <section class="hero">
        <div class="container hero-grid">
            <div class="hero-content">
                <span class="badge"><i class="fa-solid fa-bolt" style="color: #10b981;"></i> THE DIGITAL AGRICULTURE ECOSYSTEM</span>
                <h1>Direct Market,<br><span>Fair Pricing.</span></h1>
                <p>Empowering farmers and buyers with a direct marketplace. Negotiate instantly, pre-book growing crops, and manage global logistics from a single dashboard.</p>
                <div style="display: flex; gap: 15px;">
                    <a href="auth/register.php" class="btn btn-primary">Create Account <i class="fa-solid fa-arrow-right"></i></a>
                    <a href="#how-it-works" class="btn btn-white"><i class="fa-solid fa-boxes-stacked" style="color: var(--primary);"></i> See Features</a>
                </div>
            </div>
            
            <div style="position: relative;">
                <div class="hero-image-box">
                    <img src="https://images.unsplash.com/photo-1625246333195-78d9c38ad449?q=80&w=1000&auto=format&fit=crop" alt="Smart Farming Marketplace">
                </div>
                <div class="floating-stat">
                    <div class="stat-icon"><i class="fa-solid fa-file-signature"></i></div>
                    <div>
                        <div style="font-size: 0.85rem; color: var(--text-muted); text-transform: uppercase; font-weight: 900; letter-spacing: 0.5px;">Advance Contract Secured</div>
                        <div style="font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px;">₹ 25,000 Paid</div>
                    </div>
                </div>
            </div>
        </div>

        <div class="container stats-bar">
            <div class="bento-card stat-card">
                <div class="icon"><i class="fa-solid fa-handshake-angle"></i></div>
                <div>
                    <h3>0%</h3>
                    <p>Middleman Commission</p>
                </div>
            </div>
            <div class="bento-card stat-card">
                <div class="icon" style="background: rgba(245, 158, 11, 0.15); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2);"><i class="fa-solid fa-money-bill-transfer"></i></div>
                <div>
                    <h3>20%</h3>
                    <p>Advance Pre-Booking</p>
                </div>
            </div>
            <div class="bento-card stat-card">
                <div class="icon" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6; border-color: rgba(59, 130, 246, 0.2);"><i class="fa-solid fa-language"></i></div>
                <div>
                    <h3>9+</h3>
                    <p>Regional Languages</p>
                </div>
            </div>
        </div>
    </section>

    <section id="how-it-works" class="steps-section container">
        <h2>How Farmogram Works</h2>
        <p>A seamless end-to-end B2B marketplace built for the modern agricultural supply chain.</p>
        
        <div class="steps-grid">
            <div class="bento-card step-item">
                <div class="step-icon"><i class="fa-solid fa-seedling"></i></div>
                <h3>List & Pre-Book</h3>
                <p>Farmers list crops before harvest. Buyers secure future supply by paying a 20% advance contract fee directly through the platform.</p>
            </div>
            <div class="bento-card step-item">
                <div class="step-icon" style="color: #3b82f6; background: rgba(59, 130, 246, 0.15); border-color: rgba(59, 130, 246, 0.2);"><i class="fa-brands fa-whatsapp"></i></div>
                <h3>Direct Negotiation</h3>
                <p>Use our real-time, built-in chat system to negotiate prices and bulk quantities directly with verified farmers. No hidden fees.</p>
            </div>
            <div class="bento-card step-item">
                <div class="step-icon" style="color: #f59e0b; background: rgba(245, 158, 11, 0.15); border-color: rgba(245, 158, 11, 0.2);"><i class="fa-solid fa-truck-fast"></i></div>
                <h3>Smart Logistics</h3>
                <p>Manage processing, shipping, and successful delivery through our enterprise tracking system and live order dashboards.</p>
            </div>
        </div>
    </section>

    <section id="testimonials" class="testimonials container">
        <div class="testi-left">
            <h2>Community Success Stories</h2>
            <p>Join thousands of farmers and buyers who have transformed their businesses using Farmogram's transparent marketplace.</p>
        </div>
        
        <div class="testi-grid">
            <div class="bento-card testi-card">
                <div class="stars">
                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                </div>
                <p class="testi-quote">"The 20% pre-booking feature changed everything. I got the capital I needed to buy fertilizer while my tomatoes were still growing in the field."</p>
                <div class="testi-user">
                    <div style="width: 55px; height: 55px; border-radius: 16px; background: rgba(16, 185, 129, 0.15); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.3); display:flex; align-items:center; justify-content:center; font-weight: 900; font-size: 1.4rem;">R</div>
                    <div>
                        <h4>Rajesh Kumar</h4>
                        <span>Verified Farmer <i class="fa-solid fa-circle-check" style="color: #3b82f6; margin-left: 4px;"></i></span>
                    </div>
                </div>
            </div>
            
            <div class="bento-card testi-card">
                <div class="stars">
                    <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
                </div>
                <p class="testi-quote">"Using the built-in chat to negotiate bulk wheat purchases directly with the farmers has saved my restaurant chain thousands of rupees."</p>
                <div class="testi-user">
                    <div style="width: 55px; height: 55px; border-radius: 16px; background: rgba(59, 130, 246, 0.15); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); display:flex; align-items:center; justify-content:center; font-weight: 900; font-size: 1.4rem;">P</div>
                    <div>
                        <h4>Priya Sharma</h4>
                        <span>Retail Buyer</span>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ GET THE APP SECTION ============ -->
    <section id="get-app" class="app-section">
        <div class="container app-grid">
            <div class="app-visual">
                <div class="phone-glow"></div>
                <div class="phone-frame">
                    <div class="phone-screen">
                        <img src="assets/images/app-mockup.png" alt="Farmogram Mobile App">
                    </div>
                </div>
                <div class="app-float app-float-1">
                    <i class="fa-solid fa-wifi"></i>
                    <span>Works Offline</span>
                </div>
                <div class="app-float app-float-2">
                    <i class="fa-solid fa-bolt"></i>
                    <span>Instant Load</span>
                </div>
                <div class="app-float app-float-3">
                    <i class="fa-solid fa-bell"></i>
                    <span>Push Alerts</span>
                </div>
            </div>

            <div class="app-content">
                <span class="badge"><i class="fa-solid fa-mobile-screen-button" style="color: #10b981;"></i> AVAILABLE ON ALL DEVICES</span>
                <h2>Get the <span>Farmogram</span> App</h2>
                <p>Download our app for a faster, smoother experience with offline support, push notifications, and native performance on any device.</p>

                <div class="app-features">
                    <div class="app-feature">
                        <div class="app-feature-icon"><i class="fa-solid fa-gauge-high"></i></div>
                        <div>
                            <h4>Lightning Fast</h4>
                            <p>Loads instantly, even on slow networks</p>
                        </div>
                    </div>
                    <div class="app-feature">
                        <div class="app-feature-icon" style="background: rgba(59, 130, 246, 0.1); color: #3b82f6; border-color: rgba(59, 130, 246, 0.2);">
                            <i class="fa-solid fa-cloud-arrow-down"></i>
                        </div>
                        <div>
                            <h4>Offline Ready</h4>
                            <p>Access your dashboard without internet</p>
                        </div>
                    </div>
                    <div class="app-feature">
                        <div class="app-feature-icon" style="background: rgba(245, 158, 11, 0.1); color: #f59e0b; border-color: rgba(245, 158, 11, 0.2);">
                            <i class="fa-solid fa-bell"></i>
                        </div>
                        <div>
                            <h4>Push Notifications</h4>
                            <p>Get instant alerts on orders & messages</p>
                        </div>
                    </div>
                </div>

                <div class="app-buttons">
                    <button id="hero-install-btn" class="btn btn-primary app-install-btn" style="width: 100%; max-width: 250px; justify-content: center;">
                        <i class="fa-solid fa-download"></i> Download App
                    </button>
                    <!-- Hidden APK link for fallback downloads -->
                    <a href="downloads/farmogram.apk" class="btn btn-white app-apk-btn" id="apk-download-btn" style="display:none;" download></a>
                </div>

                <div class="app-platforms">
                    <span><i class="fa-brands fa-android"></i> Android</span>
                    <span><i class="fa-brands fa-apple"></i> iOS</span>
                    <span><i class="fa-brands fa-windows"></i> Windows</span>
                    <span><i class="fa-brands fa-chrome"></i> Chrome</span>
                </div>
            </div>
        </div>
    </section>

    <!-- iOS Install Instructions Modal -->
    <div class="ios-modal-overlay" id="ios-modal">
        <div class="ios-modal">
            <button class="ios-modal-close" id="ios-modal-close"><i class="fa-solid fa-xmark"></i></button>
            <div style="width: 70px; height: 70px; background: rgba(16, 185, 129, 0.1); border-radius: 20px; display: flex; align-items: center; justify-content: center; font-size: 2rem; color: var(--primary); margin: 0 auto 20px; border: 1px solid rgba(16, 185, 129, 0.2);">
                <i class="fa-brands fa-apple"></i>
            </div>
            <h3>Install on iPhone / iPad</h3>
            <p>Add Farmogram to your home screen for the best experience</p>
            <div class="ios-steps">
                <div class="ios-step">
                    <div class="ios-step-num">1</div>
                    <span>Tap the <i class="fa-solid fa-arrow-up-from-bracket" style="margin: 0 4px;"></i> <b>Share</b> button in Safari</span>
                </div>
                <div class="ios-step">
                    <div class="ios-step-num">2</div>
                    <span>Scroll down and tap <b>"Add to Home Screen"</b></span>
                </div>
                <div class="ios-step">
                    <div class="ios-step-num">3</div>
                    <span>Tap <b>"Add"</b> in the top right corner</span>
                </div>
            </div>
            <button class="btn btn-primary" style="width: 100%; margin-top: 25px;" onclick="document.getElementById('ios-modal').classList.remove('active');">Got It!</button>
        </div>
    </div>

    <section class="cta-section container">
        <div class="cta-box">
            <h2>Ready to Digitize Your Farm?</h2>
            <p>Sign up in under 60 seconds. Sell faster, buy smarter, and track your agricultural logistics with ease on a platform built for modern business.</p>
            <div class="cta-actions">
                <a href="auth/register.php" class="btn btn-primary" style="padding: 18px 40px; font-size: 1.15rem;">Join the Marketplace</a>
                <a href="auth/login.php" class="btn btn-dark" style="padding: 18px 40px; font-size: 1.15rem;">Login to Dashboard</a>
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
                    <li><a href="#">B2B Marketplace</a></li>
                    <li><a href="#">Pre-Book Contracts</a></li>
                    <li><a href="#">Task Scheduler</a></li>
                    <li><a href="#">AI Diagnostics</a></li>
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
        
        // PWA SERVICE WORKER REGISTRATION
        if ('serviceWorker' in navigator) {
            const swPath = (location.hostname === 'localhost' || location.hostname === '127.0.0.1') ? '/farmogram/sw.js' : '/sw.js';
            window.addEventListener('load', function() {
                navigator.serviceWorker.register(swPath).then(function(registration) {
                    console.log('Farmogram PWA established.');
                }, function(err) {
                    console.log('PWA Error: ', err);
                });
            });
        }
    });
</script>

<!-- Install App Banner -->
<div id="pwa-install-banner" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); background: var(--card-bg); border: 2px solid var(--primary); padding: 15px 25px; border-radius: 50px; box-shadow: 0 10px 30px rgba(16, 185, 129, 0.3); z-index: 9999; align-items: center; justify-content: center; gap: 15px; width: 90%; max-width: 400px; transition: all 0.5s ease;">
    <img src="assets/images/logo-light.png" alt="Farmogram" class="logo-light" style="width: 35px; height: 35px; object-fit: contain;">
    <img src="assets/images/logo-dark.png" alt="Farmogram" class="logo-dark" style="width: 35px; height: 35px; object-fit: contain;">
    <div style="flex: 1;">
        <div style="font-weight: 800; color: var(--text-main); font-size: 0.95rem; line-height: 1.2;">Install Farmogram App</div>
        <div style="font-size: 0.8rem; color: var(--text-muted); font-weight: 600;">Fast, offline, and secure</div>
    </div>
    <button id="pwa-install-btn" class="btn btn-primary" style="padding: 8px 16px; font-size: 0.85rem; border-radius: 20px;">Install</button>
    <button id="pwa-close-btn" style="background: none; border: none; color: var(--text-muted); font-size: 1.2rem; cursor: pointer; padding: 0 5px;"><i class="fa-solid fa-xmark"></i></button>
</div>

<script>
    // =========================================================
    //  FARMOGRAM - ENHANCED PWA INSTALL FLOW
    // =========================================================
    let deferredPrompt;
    const installBanner = document.getElementById('pwa-install-banner');
    const installBtn = document.getElementById('pwa-install-btn');
    const closeBtn = document.getElementById('pwa-close-btn');
    const heroInstallBtn = document.getElementById('hero-install-btn');
    const iosModal = document.getElementById('ios-modal');
    const iosModalClose = document.getElementById('ios-modal-close');

    // Platform detection
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
    const isAndroid = /Android/.test(navigator.userAgent);
    const isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone;

    // Shared install trigger function
    function triggerInstall() {
        if (deferredPrompt) {
            if (installBanner) installBanner.style.display = 'none';
            deferredPrompt.prompt();
            deferredPrompt.userChoice.then((choiceResult) => {
                if (choiceResult.outcome === 'accepted') {
                    console.log('Farmogram: User accepted install');
                    updateInstallUI(true);
                }
                deferredPrompt = null;
            });
        } else if (isIOS) {
            // Show iOS-specific instructions
            if (iosModal) iosModal.classList.add('active');
        } else {
            // Fallback: trigger APK download directly
            const apkBtn = document.getElementById('apk-download-btn');
            if (apkBtn) apkBtn.click();
        }
    }

    // Update UI after install
    function updateInstallUI(installed) {
        if (installed) {
            if (heroInstallBtn) {
                heroInstallBtn.innerHTML = '<i class="fa-solid fa-circle-check"></i> App Installed';
                heroInstallBtn.style.background = 'rgba(16, 185, 129, 0.15)';
                heroInstallBtn.style.color = '#10b981';
                heroInstallBtn.style.boxShadow = 'none';
                heroInstallBtn.style.cursor = 'default';
            }
            if (installBanner) installBanner.style.display = 'none';
        }
    }

    // Listen for the browser's install prompt
    window.addEventListener('beforeinstallprompt', (e) => {
        e.preventDefault();
        deferredPrompt = e;
        // Show floating banner
        if (!sessionStorage.getItem('pwa_banner_dismissed') && installBanner) {
            installBanner.style.display = 'flex';
        }
        // Ensure hero button shows install text
        if (heroInstallBtn) {
            heroInstallBtn.innerHTML = '<i class="fa-solid fa-download"></i> Install App';
        }
    });

    // Check if already running as installed app
    if (isStandalone) {
        updateInstallUI(true);
    }

    // iOS-specific: change button text
    if (isIOS && heroInstallBtn && !isStandalone) {
        heroInstallBtn.innerHTML = '<i class="fa-solid fa-plus-square"></i> Add to Home Screen';
    }

    // Floating banner install button
    if (installBtn) {
        installBtn.addEventListener('click', triggerInstall);
    }

    // Hero section install button
    if (heroInstallBtn) {
        heroInstallBtn.addEventListener('click', triggerInstall);
    }

    // Close floating banner
    if (closeBtn) {
        closeBtn.addEventListener('click', () => {
            installBanner.style.display = 'none';
            sessionStorage.setItem('pwa_banner_dismissed', 'true');
        });
    }

    // iOS modal close button
    if (iosModalClose) {
        iosModalClose.addEventListener('click', () => {
            iosModal.classList.remove('active');
        });
    }

    // Close iOS modal on overlay click
    if (iosModal) {
        iosModal.addEventListener('click', (e) => {
            if (e.target === iosModal) iosModal.classList.remove('active');
        });
    }

    // Track successful installs
    window.addEventListener('appinstalled', () => {
        console.log('Farmogram app installed successfully!');
        updateInstallUI(true);
        deferredPrompt = null;
    });

    // Check if expected to be installed
    if (isStandalone) {
        updateInstallUI(true);
    }
</script>

</body>
</html>