<?php
session_start();
include("../config/db.php");

$error = '';
$success = '';

// Check if we arrived here securely from forgot_password.php
if(!isset($_SESSION['reset_user_id']) || !isset($_SESSION['reset_otp'])) {
    header("Location: forgot_password.php");
    exit();
}

$demo_alert = "";
if(isset($_SESSION['demo_otp_alert'])){
    $demo_alert = $_SESSION['demo_otp_alert'];
    unset($_SESSION['demo_otp_alert']);
}

if(isset($_POST['reset_password'])){
    $entered_otp = trim($_POST['otp']);
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];

    if($entered_otp != $_SESSION['reset_otp']) {
        $error = "Invalid OTP. Please check and try again.";
    } elseif($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif(strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters long.";
    } else {
        // Hash new password and update DB
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $user_id = $_SESSION['reset_user_id'];
        
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if($stmt->execute()){
            // Clear reset session variables
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_otp']);
            unset($_SESSION['reset_identifier']);
            
            $_SESSION['success_msg'] = "Password successfully reset! You can now log in.";
            header("Location: login.php");
            exit();
        } else {
            $error = "An error occurred while communicating with the database.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Verify OTP | Farmogram</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <script>
        (function() {
            const savedTheme = localStorage.getItem('farmogram_theme') || 'light';
            document.documentElement.setAttribute('data-theme', savedTheme);
        })();
    </script>

    <style>
        /* =========================================================
           BENTO UI - AUTHENTICATION DESIGN
        ========================================================= */
        :root {
            --primary: #10b981;
            --primary-hover: #059669;
            --bg-light: #f8fafc;
            --sidebar-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --card-bg: #ffffff;
        }

        * { box-sizing: border-box; }
        body { margin: 0; font-family: 'Inter', sans-serif; background: var(--bg-light); display: flex; min-height: 100vh; overflow-x: hidden; transition: background-color 0.3s ease; }
        
        /* Floating Utilities */
        .top-nav-absolute { position: absolute; top: 25px; right: 25px; display: flex; align-items: center; gap: 15px; z-index: 100; }
        
        /* THEME BUTTON */
        .theme-btn { background: var(--card-bg); border: 2px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(0,0,0,0.05); }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

        /* Custom Logo Switcher */
        .brand-logo { display: flex; align-items: center; gap: 12px; margin-bottom: 30px; text-decoration: none; }
        .logo-light { display: block; width: 40px; height: 40px; object-fit: contain; }
        .logo-dark { display: none; width: 40px; height: 40px; object-fit: contain; }
        .logo-text { font-size: 1.8rem; font-weight: 900; color: var(--text-main); transition: 0.3s; letter-spacing: -0.5px; }

        /* Split Screen Design */
        .auth-left { flex: 1.2; background: linear-gradient(rgba(15, 23, 42, 0.8), rgba(239, 68, 68, 0.85)), url('https://images.unsplash.com/photo-1592982537447-6f2c6e0c278c?q=80&w=1000&auto=format&fit=crop') center/cover; display: flex; flex-direction: column; justify-content: center; padding: 60px; color: #fff; position: relative; }
        .auth-left::after { content: ''; position: absolute; right: 0; top: 0; width: 30%; height: 100%; background: linear-gradient(to right, transparent, var(--bg-light)); opacity: 0.1; pointer-events: none; transition: 0.3s; }
        .auth-left h1 { font-size: 4.5rem; margin: 0 0 15px 0; font-weight: 900; letter-spacing: -1.5px; line-height: 1.1; }
        .auth-left p { font-size: 1.25rem; opacity: 0.9; max-width: 450px; line-height: 1.6; font-weight: 500; }
        
        .auth-right { flex: 1; display: flex; align-items: center; justify-content: center; padding: 40px; background: var(--bg-light); transition: 0.3s; }
        .auth-card { width: 100%; max-width: 420px; background: var(--card-bg); padding: 40px; border-radius: 28px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.05); border: 1px solid var(--border-color); transition: 0.3s; }
        
        .auth-card h2 { font-size: 2.2rem; color: var(--text-main); margin: 0 0 8px 0; font-weight: 900; letter-spacing: -0.5px; transition: 0.3s; }
        .auth-card p { color: var(--text-muted); margin-bottom: 35px; font-size: 1.05rem; font-weight: 500; transition: 0.3s; }

        /* Form Inputs */
        .input-group { position: relative; margin-bottom: 20px; }
        .input-group i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 1.15rem; transition: 0.3s; }
        .input-group input { width: 100%; padding: 18px 20px 18px 55px; border: 2px solid var(--border-color); border-radius: 16px; font-size: 1.05rem; font-family: inherit; color: var(--text-main); outline: none; transition: 0.3s; background: var(--bg-light); font-weight: 600; }
        .input-group input:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        .input-group input::placeholder { color: var(--text-muted); opacity: 0.7; font-weight: 500; }

        .btn-submit { width: 100%; background: var(--primary); color: #fff; border: none; padding: 18px; border-radius: 16px; font-size: 1.15rem; font-weight: 900; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 10px; margin-top: 15px; font-family: inherit; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2); }
        .btn-submit:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 10px 25px rgba(16, 185, 129, 0.3); }

        /* Alerts */
        .alert { padding: 18px 20px; border-radius: 12px; margin-bottom: 25px; font-size: 0.95rem; font-weight: 700; display: flex; align-items: center; gap: 10px; }
        .alert-error { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

        /* Link */
        .auth-link { text-align: center; margin-top: 30px; color: var(--text-muted); font-weight: 600; transition: 0.3s; }
        .auth-link a { color: var(--text-main); font-weight: 800; text-decoration: none; border-bottom: 2px solid var(--primary); transition: 0.2s; padding-bottom: 2px; }
        .auth-link a:hover { color: var(--primary); border-color: transparent; }

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
        
        [data-theme="dark"] .auth-right { background: var(--bg-light) !important; }
        [data-theme="dark"] .auth-card { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5) !important; }
        [data-theme="dark"] .theme-btn { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        
        [data-theme="dark"] .input-group input { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .input-group input:focus { border-color: var(--primary) !important; }
        
        [data-theme="dark"] .logo-light { display: none !important; }
        [data-theme="dark"] .logo-dark { display: block !important; }
        
        [data-theme="dark"] h2, [data-theme="dark"] .logo-text { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .auth-link { color: var(--text-muted) !important; }
        [data-theme="dark"] .auth-link a { color: var(--text-main) !important; }
        [data-theme="dark"] .auth-link a:hover { color: var(--primary) !important; }

        /* =========================================================
           RESPONSIVE
        ========================================================= */
        @media (max-width: 900px) {
            body { flex-direction: column; }
            .auth-left { padding: 60px 30px; text-align: center; flex: none; align-items: center; min-height: 40vh; }
            .auth-left::after { display: none; }
            .auth-left h1 { font-size: 3.5rem; }
            .auth-right { padding: 40px 20px; align-items: flex-start; }
            .auth-card { padding: 30px; border-radius: 24px; box-shadow: none; border: none; background: transparent !important; }
            .top-nav-absolute { top: 20px; right: 20px; }
            .brand-logo { justify-content: center; }
        }
    </style>
</head>
<body>

    <div class="top-nav-absolute">
        <button class="theme-btn" id="themeToggle" title="Toggle Theme">
            <i class="fa-solid fa-moon dark-icon"></i>
            <i class="fa-solid fa-sun light-icon" style="display: none;"></i>
        </button>
    </div>

    <div class="auth-left">
        <h1>Verify & Reset.</h1>
        <p>You're almost there. Enter the code sent to your device and choose a strong new password.</p>
    </div>

    <div class="auth-right">
        <div class="auth-card">
            
            <a href="../index.php" class="brand-logo">
                <img src="../assets/images/logo-light.png" alt="Farmogram" class="logo-light">
                <img src="../assets/images/logo-dark.png" alt="Farmogram" class="logo-dark">
                <span class="logo-text">Farmogram</span>
            </a>

            <h2>Set New Password</h2>
            <p>Code sent to <strong style="color: var(--text-main);"><?php echo htmlspecialchars($_SESSION['reset_identifier']); ?></strong>.</p>

            <?php if(!empty($demo_alert)): ?>
                <script>alert("<?php echo addslashes($demo_alert); ?>");</script>
                <div class="alert alert-success" style="background: rgba(16, 185, 129, 0.15); border-color: #10b981; color: #10b981;"><i class="fa-solid fa-unlock-keyhole"></i> <?php echo $demo_alert; ?></div>
            <?php endif; ?>

            <?php if($error): ?>
                <div class="alert alert-error"><i class="fa-solid fa-circle-exclamation"></i> <?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                
                <h4 style="margin: 0 0 10px 0; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">Verification</h4>
                <div class="input-group">
                    <i class="fa-solid fa-hashtag"></i>
                    <input type="text" name="otp" placeholder="6-digit OTP" required autofocus maxlength="6" pattern="\d{6}">
                </div>

                <h4 style="margin: 15px 0 10px 0; font-size: 0.9rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">New Password</h4>
                <div class="input-group">
                    <i class="fa-solid fa-lock"></i>
                    <input type="password" name="new_password" placeholder="Create new password" required>
                </div>
                <div class="input-group">
                    <i class="fa-solid fa-check-double"></i>
                    <input type="password" name="confirm_password" placeholder="Confirm new password" required>
                </div>

                <button type="submit" name="reset_password" class="btn-submit">Confirm Reset <i class="fa-solid fa-shield-check"></i></button>
            </form>

            <div class="auth-link">
                Changed your mind? <a href="login.php">Cancel</a>
            </div>
        </div>
    </div>

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

</body>
</html>
