<?php
session_start();
include("../config/db.php");

// STRICT ROLE CHECK
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

$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>AI Diagnostics | Farmogram</title>
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
        .content-container { flex: 1; display: flex; flex-direction: column; padding: 25px 40px; max-width: 1000px; margin: 0 auto; width: 100%; min-height: 0; }

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

        /* Bento Card Wrapper */
        .bento-card { background: var(--card-bg); border-radius: var(--radius-xl); padding: 35px; box-shadow: var(--shadow-subtle); border: 1px solid var(--border-color); transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; flex: 1; min-height: 0; overflow-y: auto; }
        .bento-card:hover { box-shadow: var(--shadow-hover); border-color: var(--primary); }

        /* Chat Styles */
        .chat-box { flex: 1; overflow-y: auto; padding: 25px; display: flex; flex-direction: column; gap: 17px; border: 1px solid var(--border-color); border-radius: var(--radius-lg); background: var(--bg-light); margin-bottom: 20px; transition: 0.3s; min-height: 0; }
        
        .msg { max-width: 80%; padding: 14px 20px; border-radius: 20px; font-size: 0.95rem; line-height: 1.6; word-wrap: break-word; font-weight: 500; transition: 0.3s; box-shadow: var(--shadow-subtle); }
        .msg.bot { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); align-self: flex-start; border-bottom-left-radius: 4px; }
        .msg.user { background: var(--primary); color: #fff; align-self: flex-end; border-bottom-right-radius: 4px; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.25); }
        
        .loading-dots i { font-size: 6px; margin: 0 2px; animation: blink 1.4s infinite reverse; }
        .loading-dots i:nth-child(2) { animation-delay: 0.2s; }
        .loading-dots i:nth-child(3) { animation-delay: 0.4s; }
        @keyframes blink { 0% { opacity: 0.2; } 20% { opacity: 1; } 100% { opacity: 0.2; } }

        .chat-input-area { display: flex; gap: 15px; align-items: center; background: var(--bg-light); padding: 15px; border-radius: var(--radius-lg); border: 1px solid var(--border-color); transition: 0.3s; flex-shrink: 0; }
        .chat-input-area input[type="text"] { flex: 1; padding: 15px 20px; border: none; border-radius: var(--radius-md); background: var(--card-bg); color: var(--text-main); outline: none; font-size: 1rem; font-family: inherit; transition: 0.3s; font-weight: 600; border: 2px solid transparent; }
        .chat-input-area input[type="text"]:focus { border-color: var(--primary); box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1); }
        
        .action-btn { background: var(--primary); color: #fff; border: none; width: 54px; height: 54px; border-radius: var(--radius-md); cursor: pointer; display: flex; align-items: center; justify-content: center; transition: 0.3s; font-size: 1.2rem; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); flex-shrink: 0; }
        .action-btn:hover:not(:disabled) { background: var(--primary-hover); transform: translateY(-2px); }
        .action-btn.cam { background: var(--card-bg); color: var(--text-main); border: 2px solid var(--border-color); box-shadow: var(--shadow-subtle); }
        .action-btn.cam:hover:not(:disabled) { border-color: var(--primary); color: var(--primary); }
        .action-btn:disabled { opacity: 0.5; cursor: not-allowed; transform: none; }

        /* Sidebar Desktop Logo */
        .sidebar-logo { display: flex; align-items: center; gap: 12px; text-decoration: none; padding: 0 10px; margin-bottom: 40px; }
        .logo-text { font-size: 1.6rem; font-weight: 900; color: var(--text-main); transition: color 0.3s ease; letter-spacing: -0.5px; }

        /* Mobile Topbar Branding */
        .mobile-brand { display: none; align-items: center; gap: 10px; text-decoration: none; }
        .mobile-brand img { width: 32px; height: 32px; object-fit: contain; }
        .mobile-brand span { font-size: 1.4rem; font-weight: 900; color: var(--text-main); letter-spacing: -0.5px; }

        /* Topbar Controls */
        .theme-btn { background: var(--card-bg); border: 1px solid var(--border-color); color: var(--text-main); width: 45px; height: 45px; border-radius: 50%; display: flex; align-items: center; justify-content: center; cursor: pointer; transition: 0.3s ease; font-size: 1.2rem; box-shadow: var(--shadow-subtle); }
        .theme-btn:hover { background: var(--primary); color: #fff; border-color: var(--primary); transform: translateY(-2px); }

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
        
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] .logo-text, [data-theme="dark"] .mobile-brand span { color: var(--text-main) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] span, [data-theme="dark"] div { color: var(--text-muted); }
        
        [data-theme="dark"] .nav-menu a { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a:hover, [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }
        
        /* Assistant Specific Dark Mode */
        [data-theme="dark"] .chat-box { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .msg.bot { background: var(--sidebar-bg) !important; border-color: var(--border-color) !important; color: var(--text-main) !important; }
        [data-theme="dark"] .chat-input-area { background: rgba(0,0,0,0.2) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .chat-input-area input { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border: 1px solid var(--border-color) !important; }
        [data-theme="dark"] .action-btn.cam { background: var(--sidebar-bg) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .action-btn.cam:hover { background: rgba(255,255,255,0.05) !important; }

        /* Smooth Transitions */
        body, .sidebar, .topbar, .bento-card, .chat-box, .msg, .chat-input-area, h1, h2, h3, p, span, div { transition: background-color 0.3s ease, color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease; }

        /* =========================================================
           MOBILE SUPREMACY - TRUE APP NAVIGATION & BRANDING
        ========================================================= */
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
            .content-container { padding: 15px; flex: 1; display: flex; flex-direction: column; min-height: 0; }

            .bento-card { padding: 20px; border-radius: var(--radius-lg); }
            
            /* Chat Resizing for Mobile */
            .chat-box { padding: 15px; gap: 12px; margin-bottom: 15px; }
            .chat-input-area { padding: 10px; gap: 10px; }
            .chat-input-area input[type="text"] { padding: 12px 15px; font-size: 0.95rem; }
            .action-btn { width: 45px; height: 45px; font-size: 1.1rem; }
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
            <li><a href="enquiries.php"><i class="fa-solid fa-envelope"></i> <span>Messages</span></a></li>
            <li><a href="tasks.php"><i class="fa-solid fa-calendar-check"></i> <span>Tasks</span></a></li> 
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="assistant.php" class="active"><i class="fa-solid fa-robot"></i> <span>AI Bot</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.5rem;">Smart AI Laboratory</div>
            
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

            <div id="tab-chat" class="bento-card">
                <div style="display: flex; align-items: center; gap: 15px; margin-bottom: 20px; flex-shrink: 0;">
                    <div style="width: 55px; height: 55px; background: rgba(16, 185, 129, 0.15); border-radius: var(--radius-md); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: var(--primary);">
                        <i class="fa-solid fa-robot"></i>
                    </div>
                    <div>
                        <h2 style="margin:0; font-size: 1.5rem; letter-spacing: -0.5px;" class="logo-text">Farmogram Copilot</h2>
                        <p style="color: var(--text-muted); margin: 0; font-size: 0.95rem; font-weight: 600;">Ask questions or attach field photos for instant agronomy advice.</p>
                    </div>
                </div>
                
                <div class="chat-box" id="chatBox">
                    <div class="msg bot">Hello <?php echo htmlspecialchars(explode(' ', $user_name)[0]); ?>! I am your advanced AI agricultural expert powered by Google Gemini. Ask me any farming-related question or use the camera icon below to upload a photo of your field or crops for instant analysis!</div>
                </div>
                
                <div id="imagePreviewWrapper" style="display: none; padding: 10px 15px; background: var(--card-bg); border: 1px solid var(--border-color); border-bottom: none; border-top-left-radius: var(--radius-lg); border-top-right-radius: var(--radius-lg); flex-shrink: 0;">
                    <div style="position: relative; display: inline-block;">
                        <img id="imagePreview" src="" style="height: 70px; border-radius: var(--radius-md); border: 2px solid var(--primary); object-fit: cover;">
                        <button id="removeImageBtn" style="position: absolute; top: -5px; right: -5px; background: #ef4444; color: white; border: none; border-radius: 50%; width: 22px; height: 22px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 12px; box-shadow: 0 2px 5px rgba(0,0,0,0.2);" title="Remove Image"><i class="fa-solid fa-times"></i></button>
                    </div>
                    <span style="font-size: 0.85rem; color: var(--primary); margin-left: 10px; font-weight: 600; display: inline-block; vertical-align: top; margin-top: 5px;">Image Attached. Type a question and send!</span>
                </div>

                <div class="chat-input-area" id="chatInputArea">
                    <input type="file" id="chatImageInput" accept="image/*" style="display: none;">
                    
                    <button class="action-btn cam" title="Upload Image to Chat" onclick="document.getElementById('chatImageInput').click();">
                        <i class="fa-solid fa-paperclip"></i>
                    </button>
                    
                    <button class="action-btn cam" id="micBtn" title="Speak your message">
                        <i class="fa-solid fa-microphone"></i>
                    </button>
                    
                    <input type="text" id="chatInput" placeholder="Type your message here..." autocomplete="off">
                    
                    <button class="action-btn" id="sendBtn">
                        <i class="fa-solid fa-paper-plane"></i>
                    </button>
                </div>
            </div>

        </div>
    </main>
</div>

<script>
// ==========================================
// UPGRADED GEMINI AI CHATBOT LOGIC
// ==========================================
const chatBox = document.getElementById("chatBox");
const messageInput = document.getElementById("chatInput");
const sendButton = document.getElementById("sendBtn");
const chatImageInput = document.getElementById("chatImageInput");

const imagePreviewWrapper = document.getElementById("imagePreviewWrapper");
const imagePreview = document.getElementById("imagePreview");
const removeImageBtn = document.getElementById("removeImageBtn");
const chatInputArea = document.getElementById("chatInputArea");

let receiving = false;
let selectedImageFile = null;
let selectedImageDataUrl = null;

function parseBotReply(text) {
    let html = text.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    html = html.replace(/\n/g, '<br>');
    return html;
}

function createMessageElement(text, alignment, imageSrc = null) {
    const msgElement = document.createElement("div");
    msgElement.className = "msg " + (alignment === "right" ? "user" : "bot");
    
    let htmlContent = "";
    if (imageSrc) {
        htmlContent += `<img src="${imageSrc}" style="max-width: 100%; max-height: 220px; border-radius: var(--radius-md); margin-bottom: 12px; border: 2px solid rgba(255,255,255,0.2); display: block; object-fit: cover;"><br>`;
    }
    if (text) {
        htmlContent += text;
    }
    
    msgElement.innerHTML = htmlContent;
    return msgElement;
}

async function connectGemini(messageText, imageFile = null) {
    receiving = true;
    sendButton.disabled = true;
    sendButton.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i>';

    const botMessageElement = createMessageElement("", "left");
    chatBox.appendChild(botMessageElement);
    
    botMessageElement.innerHTML = '<span class="loading-dots" style="color: var(--primary);"><i class="fa-solid fa-circle"></i><i class="fa-solid fa-circle"></i><i class="fa-solid fa-circle"></i></span>&nbsp; <strong>Analyzing...</strong>';
    chatBox.scrollTop = chatBox.scrollHeight;

    const formData = new FormData();
    if (messageText) formData.append("message", messageText);
    if (imageFile) formData.append("image", imageFile);

    try {
        const response = await fetch('../api/gemini_assistant.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (response.ok && data.reply) {
            botMessageElement.innerHTML = parseBotReply(data.reply);
        } else {
            botMessageElement.innerHTML = `<span style="color: #ef4444; font-weight: 600;">[Error: ${data.error || 'Failed to get response'}]</span>`;
            console.error("Gemini Error:", data);
        }
    } catch (error) {
        botMessageElement.innerHTML = `<span style="color: #ef4444; font-weight: 600;">[Network Error connecting to Gemini API.]</span>`;
        console.error("Fetch Error:", error);
    }

    receiving = false;
    sendButton.disabled = false;
    sendButton.innerHTML = '<i class="fa-solid fa-paper-plane"></i>';
    chatBox.scrollTop = chatBox.scrollHeight;
    messageInput.focus();
}

// Sending Logic (Handles both staged images and text)
function handleSendMessage() {
    if (receiving) return;

    let userText = messageInput.value.trim();
    
    if (!userText && !selectedImageFile) return;

    if (selectedImageFile && !userText) {
        userText = "Please analyze this uploaded image and tell me about the crop.";
    }

    const userMsg = createMessageElement(userText, "right", selectedImageDataUrl);
    chatBox.appendChild(userMsg);
    chatBox.scrollTop = chatBox.scrollHeight;

    connectGemini(userText, selectedImageFile);

    // Reset UI
    messageInput.value = "";
    clearImagePreview();
}

sendButton.addEventListener("click", handleSendMessage);

messageInput.addEventListener("keydown", (event) => {
    if (event.key === "Enter" && !event.shiftKey) {
        event.preventDefault();
        handleSendMessage();
    }
});

// Staging the Image for Preview instead of Auto-Sending
chatImageInput.addEventListener("change", () => {
    if (chatImageInput.files.length > 0) {
        selectedImageFile = chatImageInput.files[0];
        
        const reader = new FileReader();
        reader.onload = function(e) {
            selectedImageDataUrl = e.target.result;
            imagePreview.src = selectedImageDataUrl;
            imagePreviewWrapper.style.display = 'block';
            chatInputArea.style.borderTopLeftRadius = '0';
            chatInputArea.style.borderTopRightRadius = '0';
            messageInput.focus();
        };
        reader.readAsDataURL(selectedImageFile);
    }
});

function clearImagePreview() {
    selectedImageFile = null;
    selectedImageDataUrl = null;
    chatImageInput.value = "";
    imagePreviewWrapper.style.display = 'none';
    chatInputArea.style.borderTopLeftRadius = 'var(--radius-lg)';
    chatInputArea.style.borderTopRightRadius = 'var(--radius-lg)';
}

removeImageBtn.addEventListener("click", clearImagePreview);

// ==========================================
// SPEECH RECOGNITION (VOICE-TO-TEXT) ROBUST FIX
// ==========================================
const micBtn = document.getElementById("micBtn");
const SpeechRecognition = window.SpeechRecognition || window.webkitSpeechRecognition;

if (SpeechRecognition) {
    const recognition = new SpeechRecognition();
    recognition.continuous = true; 
    recognition.interimResults = true; 
    recognition.lang = 'en-US'; 

    let isRecording = false;

    micBtn.addEventListener("click", () => {
        if (!isRecording) {
            try {
                recognition.start();
            } catch(e) { 
                console.error(e); 
                alert("Microphone is already widely bound or blocked by browser.");
            }
        } else {
            recognition.stop();
        }
    });

    recognition.onstart = () => {
        isRecording = true;
        micBtn.innerHTML = '<i class="fa-solid fa-microphone-lines fa-fade"></i>';
        micBtn.style.background = '#ef4444';
        micBtn.style.color = '#fff';
        micBtn.style.borderColor = '#ef4444';
        
        messageInput.placeholder = "Listening... Speak now!";
    };

    let finalTranscript = '';
    
    recognition.onresult = (event) => {
        let interimTranscript = '';
        
        for (let i = event.resultIndex; i < event.results.length; ++i) {
            if (event.results[i].isFinal) {
                finalTranscript += event.results[i][0].transcript + ' ';
            } else {
                interimTranscript += event.results[i][0].transcript;
            }
        }
        
        // Combine current input with spoken text
        messageInput.value = finalTranscript + interimTranscript;
    };

    recognition.onend = () => {
        isRecording = false;
        micBtn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
        micBtn.style.background = 'var(--card-bg)';
        micBtn.style.color = 'var(--text-main)';
        micBtn.style.borderColor = 'var(--border-color)';
        messageInput.placeholder = "Type your message here...";
        
        if (messageInput.value.trim() !== "") {
            messageInput.focus();
        }
        finalTranscript = ''; // Reset for next time
    };

    recognition.onerror = (event) => {
        console.error("Speech Recognition Error:", event.error);
        if(event.error === 'not-allowed') {
            alert("Microphone access denied! Either your browser blocks it because Farmogram is not running on HTTPS, or you denied the permission popup.");
        } else if (event.error === 'network') {
            alert("Network error occurred during speech recognition. Are you offline?");
        }
        
        isRecording = false;
        micBtn.innerHTML = '<i class="fa-solid fa-microphone-slash"></i>';
        setTimeout(() => {
            micBtn.innerHTML = '<i class="fa-solid fa-microphone"></i>';
            micBtn.style.background = 'var(--card-bg)';
            micBtn.style.color = 'var(--text-main)';
            micBtn.style.borderColor = 'var(--border-color)';
            messageInput.placeholder = "Type your message here...";
        }, 2500);
    };
} else {
    micBtn.style.display = 'none'; 
}

// THEME TOGGLE SCRIPT
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
