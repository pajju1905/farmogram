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

$farmer_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['name']) ? $_SESSION['name'] : 'Farmer';
$avatar_letter = strtoupper(substr($user_name, 0, 1));

// ==========================================
// 1. HANDLE FORM SUBMISSIONS (Add, Complete, Delete, Autopilot, Notes)
// ==========================================
if($_SERVER['REQUEST_METHOD'] == 'POST'){
    
    // Add Custom Single Task
    if(isset($_POST['add_task'])){
        $crop_id = (int)$_POST['crop_id'];
        $task_type = $conn->real_escape_string($_POST['task_type']);
        $description = $conn->real_escape_string($_POST['description']);
        $due_date = $conn->real_escape_string($_POST['due_date']);
        $repeat_days = (int)$_POST['repeat_days'];
        
        $conn->query("INSERT INTO farm_tasks (farmer_id, crop_id, task_type, task_description, due_date, repeat_days, is_autopilot) VALUES ($farmer_id, $crop_id, '$task_type', '$description', '$due_date', $repeat_days, 0)");
        header("Location: tasks.php?msg=added");
        exit();
    }
    
    // Generate AI Autopilot Tracker Timeline
    if(isset($_POST['generate_autopilot'])){
        $crop_id = (int)$_POST['auto_crop_id'];
        $crop_type = $_POST['auto_template'];
        $start_date = $_POST['auto_start_date'];

        // Updated templates emphasizing continuous routines
        $plan = [];
        if($crop_type == 'wheat') {
            $plan = [
                [0, "Fertilizer", "Base Dose: Apply 50% Urea, DAP & MOP. Note: Start light weekly watering if soil is dry."],
                [21, "Watering", "Crown Root Initiation (CRI): Heavy irrigation needed! Apply 25% Urea. Check moisture every 4-5 days from now on."],
                [45, "Fertilizer", "Late Jointing Stage: Second heavy irrigation & final 25% Urea dose. Maintain baseline weekly watering."],
                [60, "Weeding", "Clear all competing weeds. Do an inspection for yellowing leaves."],
                [85, "Watering", "Flowering & Grain filling: Critical time! Do not let the field go completely dry. Water every 7-10 days."],
                [120, "Harvesting", "Grain is hard and plant is yellow. Stop watering completely for 2 weeks before harvest."]
            ];
        } else if($crop_type == 'tomato') {
            $plan = [
                [0, "Other", "Transplanting seedlings to main field. Note: Tomatoes need consistent watering every 2-3 days minimum!"],
                [15, "Weeding", "First manual weeding and banking of soil. Keep watering schedule tight."],
                [30, "Fertilizer", "Top-dress NPK and tie vines to stakes. Do not let soil dry out entirely between waterings."],
                [45, "Watering", "Flowering Stage: Double down on moisture checks. Dry soil causes blossom end rot!"],
                [65, "Harvesting", "First flush fruit picking. Continue watering strictly every 3 days."],
                [75, "Pesticide", "Preventative spray for fruit borers/rot. Pick ripe fruits weekly."],
                [90, "Harvesting", "Final harvest and field clearing."]
            ];
        } else if($crop_type == 'corn') {
            $plan = [
                [0, "Other", "Sowing seeds at optimal spacing. Keep soil lightly moist during germination."],
                [20, "Weeding", "Remove weeds to prevent nutrient theft. Water roughly every week based on rainfall."],
                [30, "Fertilizer", "Knee-high stage: Apply Urea top-dress. Increase watering frequency slightly."],
                [55, "Watering", "Tasseling (Flowering): Needs heavy, deep watering! Corn drinks huge amounts of water in this phase."],
                [100, "Harvesting", "Harvest when kernels are dented/hard. No water needed toward end."]
            ];
        }

        foreach($plan as $p) {
            $due = date('Y-m-d 08:00:00', strtotime($start_date . " + {$p[0]} days"));
            $type = $p[1];
            $desc = $conn->real_escape_string($p[2]);
            $conn->query("INSERT INTO farm_tasks (farmer_id, crop_id, task_type, task_description, due_date, repeat_days, is_autopilot) VALUES ($farmer_id, $crop_id, '$type', '$desc', '$due', 0, 1)");
        }
        header("Location: tasks.php?msg=autopilot");
        exit();
    }
    
    // Complete Stage
    if(isset($_POST['mark_complete'])){
        $task_id = (int)$_POST['task_id'];
        
        $q = $conn->query("SELECT * FROM farm_tasks WHERE id = $task_id AND farmer_id = $farmer_id");
        if($q->num_rows > 0) {
            $task = $q->fetch_assoc();
            $rep = (int)$task['repeat_days'];
            if($rep > 0) {
                $new_due_date = date('Y-m-d H:i:s', strtotime($task['due_date'] . " + $rep days"));
                if(strtotime($new_due_date) < time()) {
                    $new_due_date = date('Y-m-d 08:00:00', strtotime("+$rep days"));
                }
                
                $c_id = $task['crop_id'];
                $ttype = $conn->real_escape_string($task['task_type']);
                $tdesc = $conn->real_escape_string($task['task_description']);
                $is_auto = (int)$task['is_autopilot'];
                
                $conn->query("INSERT INTO farm_tasks (farmer_id, crop_id, task_type, task_description, due_date, repeat_days, is_autopilot) VALUES ($farmer_id, $c_id, '$ttype', '$tdesc', '$new_due_date', $rep, $is_auto)");
            }
        }
        
        $conn->query("UPDATE farm_tasks SET status = 'completed' WHERE id = $task_id AND farmer_id = $farmer_id");
        header("Location: tasks.php?msg=completed");
        exit();
    }

    // Update Notes / Instructions inline
    if(isset($_POST['update_task_notes'])){
        $task_id = (int)$_POST['task_id'];
        $new_desc = $conn->real_escape_string($_POST['custom_notes']);
        $conn->query("UPDATE farm_tasks SET task_description = '$new_desc' WHERE id = $task_id AND farmer_id = $farmer_id");
        header("Location: tasks.php?msg=notes_saved");
        exit();
    }

    // Delete Task
    if(isset($_POST['delete_task'])){
        $task_id = (int)$_POST['task_id'];
        $conn->query("DELETE FROM farm_tasks WHERE id = $task_id AND farmer_id = $farmer_id");
        header("Location: tasks.php?msg=deleted");
        exit();
    }
    
    // Delete Autopilot Timeline
    if(isset($_POST['delete_autopilot_timeline'])){
        $crop_id = (int)$_POST['crop_id'];
        $conn->query("DELETE FROM farm_tasks WHERE crop_id = $crop_id AND is_autopilot = 1 AND farmer_id = $farmer_id");
        header("Location: tasks.php?msg=autopilot_deleted");
        exit();
    }
}

// ==========================================
// 2. FETCH DATA FOR THE UI
// ==========================================
$crops = $conn->query("SELECT id, crop_name FROM crops WHERE farmer_id = $farmer_id AND status = 'growing'");
$crops_array = [];
while($c = $crops->fetch_assoc()) {
    $crops_array[] = $c;
}

$tasks_query = $conn->query("
    SELECT t.*, c.crop_name 
    FROM farm_tasks t 
    JOIN crops c ON t.crop_id = c.id 
    WHERE t.farmer_id = $farmer_id AND t.is_autopilot = 0
    ORDER BY t.due_date ASC
");

$overdue = []; $today = []; $upcoming = []; $completed = [];
$current_date = date('Y-m-d');
$current_time = time();

while($t = $tasks_query->fetch_assoc()){
    if($t['status'] == 'completed'){
        $completed[] = $t;
    } else {
        $task_date = date('Y-m-d', strtotime($t['due_date']));
        $task_time = strtotime($t['due_date']);
        
        if($task_time < $current_time && $task_date < $current_date){
            $overdue[] = $t;
        } elseif($task_date == $current_date) {
            $today[] = $t;
        } else {
            $upcoming[] = $t;
        }
    }
}

function getTaskStyling($type) {
    switch($type) {
        case 'Watering': return ['icon' => 'fa-droplet', 'color' => '#3b82f6', 'bg' => 'rgba(59, 130, 246, 0.15)'];
        case 'Fertilizer': return ['icon' => 'fa-flask', 'color' => '#8b5cf6', 'bg' => 'rgba(139, 92, 246, 0.15)'];
        case 'Pesticide': return ['icon' => 'fa-spray-can', 'color' => '#ef4444', 'bg' => 'rgba(239, 68, 68, 0.15)'];
        case 'Weeding': return ['icon' => 'fa-leaf', 'color' => '#10b981', 'bg' => 'rgba(16, 185, 129, 0.15)'];
        case 'Harvesting': return ['icon' => 'fa-wheat-awn', 'color' => '#f59e0b', 'bg' => 'rgba(245, 158, 11, 0.15)'];
        default: return ['icon' => 'fa-thumbtack', 'color' => '#64748b', 'bg' => 'rgba(100, 116, 139, 0.15)'];
    }
}

function getRepeatBadgeHtml($repeat_days) {
    if ($repeat_days == 1) return '<span style="font-size:0.75rem; background:rgba(182, 182, 182, 0.2); border-radius:50px; padding:3px 8px; margin-left:8px;"><i class="fa-solid fa-rotate"></i> Daily</span>';
    if ($repeat_days == 7) return '<span style="font-size:0.75rem; background:rgba(182, 182, 182, 0.2); border-radius:50px; padding:3px 8px; margin-left:8px;"><i class="fa-solid fa-rotate"></i> Weekly</span>';
    if ($repeat_days > 0) return '<span style="font-size:0.75rem; background:rgba(182, 182, 182, 0.2); border-radius:50px; padding:3px 8px; margin-left:8px;"><i class="fa-solid fa-rotate"></i> Every '.$repeat_days.'d</span>';
    return '';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Smart Schedule | Farmogram</title>
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

        .content-container { padding: 30px 40px; max-width: 1600px; margin: 0 auto; }

        .goog-te-gadget-simple { background: var(--card-bg) !important; border: 1px solid var(--border-color) !important; border-radius: 50px !important; padding: 8px 16px !important; font-size: 0.9rem !important; font-weight: 800 !important; color: var(--text-main) !important; display: inline-flex !important; align-items: center; box-shadow: var(--shadow-subtle); transition: 0.3s; }
        .goog-te-gadget-icon { display: none !important; }
        body { top: 0 !important; }
        .skiptranslate iframe { display: none !important; }

        /* TABS UI */
        .tabs-container { display: flex; gap: 15px; margin-bottom: 35px; flex-wrap: wrap; }
        .tab-btn { background: var(--card-bg); border: 2px solid var(--border-color); padding: 14px 28px; border-radius: 50px; font-size: 1rem; font-weight: 800; color: var(--text-muted); cursor: pointer; transition: 0.3s; display: flex; align-items: center; gap: 10px; box-shadow: var(--shadow-subtle); }
        .tab-btn:hover { border-color: var(--primary); color: var(--text-main); transform: translateY(-2px); }
        .tab-btn.active { background: var(--primary) !important; border-color: var(--primary) !important; color: #fff !important; box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3) !important; }
        .tab-btn.active i { color: #fff !important; }
        .tab-content { display: none; }
        .tab-content.active { display: block; animation: fadeIn 0.4s ease-in-out; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }

        .task-section { margin-bottom: 40px; }
        .section-title { font-size: 1.3rem; font-weight: 900; color: var(--text-main); margin-bottom: 25px; display: flex; align-items: center; gap: 12px; padding-bottom: 12px; border-bottom: 2px dashed var(--border-color); transition: 0.3s; letter-spacing: -0.5px; }
        
        .task-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 24px; }
        
        .bento-task-card { background: var(--card-bg); border-radius: var(--radius-xl); border: 1px solid var(--border-color); padding: 28px; box-shadow: var(--shadow-subtle); position: relative; transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1); display: flex; flex-direction: column; }
        .bento-task-card:hover { transform: translateY(-5px); box-shadow: var(--shadow-hover); }
        
        .task-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 18px; }
        .task-icon-box { width: 65px; height: 65px; border-radius: var(--radius-lg); display: flex; align-items: center; justify-content: center; font-size: 1.8rem; flex-shrink: 0; }
        
        .task-time-badge { padding: 8px 14px; border-radius: 50px; font-size: 0.8rem; font-weight: 900; display: inline-flex; align-items: center; gap: 6px; backdrop-filter: blur(4px); text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-overdue { background: rgba(239, 68, 68, 0.1); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.2); }
        .badge-today { background: rgba(245, 158, 11, 0.1); color: #d97706; border: 1px solid rgba(245, 158, 11, 0.3); }
        .badge-upcoming { background: rgba(59, 130, 246, 0.1); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.2); }
        .badge-completed { background: rgba(16, 185, 129, 0.1); color: #10b981; border: 1px solid rgba(16, 185, 129, 0.2); }

        .task-crop { font-size: 0.85rem; color: var(--text-muted); font-weight: 800; text-transform: uppercase; letter-spacing: 1px; margin-bottom: 5px; display: flex; align-items: center; gap: 6px; }
        .task-title { font-size: 1.35rem; font-weight: 900; color: var(--text-main); margin-bottom: 10px; transition: 0.3s; letter-spacing: -0.5px; display: flex; align-items: center; }
        .task-desc { font-size: 1.05rem; color: var(--text-muted); line-height: 1.6; flex: 1; transition: 0.3s; font-weight: 500; }

        .task-actions { display: flex; gap: 12px; margin-top: 25px; border-top: 1px dashed var(--border-color); padding-top: 20px; transition: 0.3s; }
        
        .btn-complete { flex: 1; background: var(--primary) !important; color: #fff !important; border: none; padding: 14px; border-radius: var(--radius-md); font-weight: 900; cursor: pointer; transition: 0.3s; display: flex; justify-content: center; align-items: center; gap: 8px; box-shadow: 0 4px 10px rgba(16, 185, 129, 0.2); font-size: 1rem; }
        .btn-complete:hover { background: var(--primary-hover) !important; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3); }
        .btn-delete { background: rgba(239, 68, 68, 0.1) !important; color: #ef4444 !important; border: 1px solid rgba(239, 68, 68, 0.2) !important; padding: 14px 20px; border-radius: var(--radius-md); cursor: pointer; transition: 0.3s; font-size: 1rem; }
        .btn-delete:hover { background: #ef4444 !important; color: #fff !important; transform: translateY(-2px); box-shadow: 0 4px 15px rgba(239, 68, 68, 0.3); }

        /* TIMELINE TRACKER */
        .timeline-tracker { position: relative; padding-left: 20px; padding-top: 20px; margin-bottom: 10px; }
        .timeline-tracker::before { content: ''; position: absolute; left: 32px; top: 40px; bottom: 30px; width: 3px; background: var(--border-color); border-radius: 5px; z-index: 1; }
        
        .tl-row { display: flex; align-items: flex-start; margin-bottom: 30px; position: relative; z-index: 2; }
        .tl-row:last-child { margin-bottom: 0; }
        
        .tl-status-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; margin-right: 20px; flex-shrink: 0; margin-top: 3px; transition: 0.3s; z-index: 3; }
        .tl-row.past .tl-status-icon { background: #10b981; color: white; box-shadow: 0 0 0 5px rgba(16, 185, 129, 0.15); }
        .tl-row.past .tl-box { opacity: 0.6; }
        .tl-row.current .tl-status-icon { background: #3b82f6; color: white; box-shadow: 0 0 0 5px rgba(59, 130, 246, 0.2); animation: pulse_blue 2s infinite; }
        .tl-row.current .tl-box { border-left: 4px solid #3b82f6; box-shadow: var(--shadow-hover); }
        .tl-row.future .tl-status-icon { background: var(--bg-light); color: var(--text-muted); border: 2px solid var(--border-color); }
        .tl-row.future .tl-box { opacity: 0.9; background: transparent; border: 1px dashed var(--border-color); box-shadow: none; }
        
        .tl-box { background: var(--bg-light); padding: 18px 25px; border-radius: var(--radius-md); border: 1px solid var(--border-color); flex: 1; transition: 0.3s; box-shadow: var(--shadow-subtle); display: flex; flex-direction: column; gap: 15px; }
        .tl-box-header { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; }
        .tl-box-title { font-weight: 900; font-size: 1.15rem; color: var(--text-main); margin-bottom: 5px; }
        .tl-box-date { font-size: 0.8rem; font-weight: 800; color: var(--text-muted); display: inline-flex; align-items: center; gap: 5px; background: var(--card-bg); padding: 4px 10px; border-radius: 5px; border: 1px solid var(--border-color); }
        .tl-row.past .tl-box-date { color: #10b981; }
        .tl-row.current .tl-box-date { color: #ef4444; } 

        .notes-area { width: 100%; border: 1px dashed var(--border-color); background: rgba(0,0,0,0.02); padding: 12px; border-radius: 12px; font-size: 0.95rem; color: var(--text-main); outline: none; resize: vertical; transition: 0.3s; font-family: inherit; font-weight: 500;}
        .notes-area:focus { border-color: var(--primary); background: var(--card-bg); }
        .btn-save-notes { background: transparent; border: 2px solid var(--primary); color: var(--primary); font-size: 0.85rem; font-weight: 800; padding: 6px 15px; border-radius: 50px; cursor: pointer; transition: 0.3s; }
        .btn-save-notes:hover { background: var(--primary); color: #fff; }

        @keyframes pulse_blue { 0% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0.4); } 70% { box-shadow: 0 0 0 10px rgba(59, 130, 246, 0); } 100% { box-shadow: 0 0 0 0 rgba(59, 130, 246, 0); } }

        /* Others */
        .sidebar-logo { display:flex; align-items:center; gap:12px; text-decoration:none; padding:0 10px; margin-bottom:40px; }
        .logo-text { font-size:1.6rem; font-weight:900; color:var(--text-main); letter-spacing:-0.5px; }
        .mobile-brand { display:none; }
        .theme-btn { background:var(--card-bg); border:1px solid var(--border-color); color:var(--text-main); width:45px; height:45px; border-radius:50%; display:flex; align-items:center; justify-content:center; cursor:pointer; font-size:1.2rem; box-shadow:var(--shadow-subtle); }
        .logo-light { display:block; width:35px; height:35px; object-fit:contain; }
        .logo-dark { display:none; width:35px; height:35px; object-fit:contain; }
        [data-theme="dark"] .logo-light { display:none !important; }
        [data-theme="dark"] .logo-dark { display:block !important; }

        /* FORMS & MODALS */
        .modal-overlay { position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(15, 23, 42, 0.8); backdrop-filter:blur(8px); display:none; align-items:center; justify-content:center; padding:20px; z-index:2000; }
        .modal-overlay.active { display:flex; }
        .modal-content { background:var(--card-bg); max-width:550px; width:100%; border-radius:var(--radius-xl); padding:35px; border:1px solid var(--border-color); position:relative; }
        .modal-close { position:absolute; top:25px; right:25px; background:transparent; border:none; font-size:1.5rem; color:var(--text-muted); cursor:pointer; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 800; font-size: 0.95rem; color: var(--text-main); margin-bottom: 8px; }
        .form-control { width: 100%; padding: 14px 18px; border: 2px solid var(--border-color); background: var(--bg-light); color: var(--text-main); border-radius: var(--radius-md); font-family: inherit; font-size: 1rem; font-weight: 600; outline: none; }
        .alert-success { background: rgba(16, 185, 129, 0.1); color: #10b981; padding: 18px 20px; border-radius: var(--radius-md); margin-bottom: 30px; font-weight: 700; border: 1px solid rgba(16, 185, 129, 0.2); display: flex; align-items: center; gap: 12px; }

        /* DARK MODE */
        [data-theme="dark"] { --bg-light: #0f172a !important; --sidebar-bg: #1e293b !important; --text-main: #f8fafc !important; --text-muted: #94a3b8 !important; --border-color: rgba(255,255,255,0.05) !important; --card-bg: #1e293b !important; }
        [data-theme="dark"] body { background-color: var(--bg-light) !important; }
        [data-theme="dark"] .sidebar, [data-theme="dark"] .topbar { background-color: rgba(30, 41, 59, 0.85) !important; }
        [data-theme="dark"] .bento-task-card, [data-theme="dark"] .modal-content, [data-theme="dark"] .tl-box, [data-theme="dark"] .tl-box-date { background-color: var(--sidebar-bg) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] h1, [data-theme="dark"] h2, [data-theme="dark"] h3, [data-theme="dark"] strong, [data-theme="dark"] label, [data-theme="dark"] .logo-text, [data-theme="dark"] .notes-area { color: var(--text-main) !important; }
        [data-theme="dark"] .notes-area { background: rgba(255,255,255,0.02) !important; }
        [data-theme="dark"] p, [data-theme="dark"] .text-muted, [data-theme="dark"] .task-desc { color: var(--text-muted) !important; }
        [data-theme="dark"] .nav-menu a.active { background: rgba(16, 185, 129, 0.1) !important; color: var(--primary) !important; }
        [data-theme="dark"] .form-control { background: var(--bg-light) !important; color: var(--text-main) !important; border-color: var(--border-color) !important; }
        [data-theme="dark"] .timeline-tracker::before { background: var(--border-color) !important; }
        [data-theme="dark"] .tl-row.future .tl-box { background: transparent !important; }

        @media (max-width: 768px) {
            .app-layout { display: block; height: 100dvh; overflow: hidden; }
            .sidebar-logo { display: none; } 
            .mobile-brand { display: flex; flex: 1; }
            .topbar { padding: 12px 15px; position: sticky; top: 0; z-index: 100; display: flex; justify-content: space-between; align-items: center; }
            .page-title { display: none; } 
            .user-profile .logo-text, .user-profile .text-muted { display: none; } 
            .desktop-only { display: none; }
            .sidebar { position: fixed; bottom: 0; left: 0; width: 100%; height: var(--app-nav-height); flex-direction: row; padding: 0 5px; z-index: 1000; border-top: 1px solid var(--border-color); background: var(--card-bg); justify-content: center; }
            .nav-menu { display: flex; flex-direction: row; width: 100%; overflow-x: auto; gap: 6px; }
            .nav-menu::-webkit-scrollbar { display: none; }
            .nav-menu a { flex-direction: column; padding: 10px 16px; font-size: 0.75rem; border-radius: 20px; }
            .main-wrapper { height: calc(100dvh - var(--app-nav-height)); overflow-y: auto; padding-bottom: 25px; }
            .content-container { padding: 25px 20px; }
            .task-grid { grid-template-columns: 1fr; gap: 20px; }
            .timeline-tracker::before { left: 14px; }
            .tl-status-icon { margin-left: -14px; }
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
            <li><a href="tasks.php" class="active"><i class="fa-solid fa-calendar-check"></i> <span>Tasks</span></a></li> 
            <li><a href="analytics.php"><i class="fa-solid fa-chart-pie"></i> <span>Analytics</span></a></li>
            <li><a href="assistant.php"><i class="fa-solid fa-robot"></i> <span>AI Bot</span></a></li>
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
            
            <div class="page-title logo-text" style="font-size: 1.5rem;">Smart Task Manager</div>
            
            <div class="topbar-actions">
                <div id="google_translate_element" class="lang-switcher-container" title="Change Language"></div>
                
                <button onclick="openModal()" class="btn-action-main topbar-btn" style="padding: 10px 20px; border-radius: 50px; margin-top: 0; width: auto; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; border: none;">
                    <i class="fa-solid fa-plus"></i> <span class="desktop-only">Custom Task</span>
                </button>

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
            <?php if(isset($_GET['msg'])): ?>
                <?php if($_GET['msg'] == 'added'): ?>
                    <div class="alert-success"><i class="fa-solid fa-calendar-check"></i> Task configured successfully!</div>
                <?php elseif($_GET['msg'] == 'notes_saved'): ?>
                    <div class="alert-success"><i class="fa-solid fa-pen"></i> Notes and specific instructions saved perfectly on the timeline!</div>
                <?php elseif($_GET['msg'] == 'completed'): ?>
                    <div class="alert-success"><i class="fa-solid fa-check-double"></i> Stage marked as completed!</div>
                <?php elseif($_GET['msg'] == 'autopilot'): ?>
                    <div class="alert-success" style="background: rgba(236, 72, 153, 0.1); color: #ec4899; border-color: rgba(236, 72, 153, 0.2);"><i class="fa-solid fa-rocket"></i> Full Lifecycle generated seamlessly!</div>
                <?php endif; ?>
            <?php endif; ?>

            <div class="tabs-container">
                <button class="tab-btn active" onclick="switchTab('tab-daily')"><i class="fa-solid fa-list-check"></i> Daily Actions</button>
                <button class="tab-btn" onclick="switchTab('tab-timeline')"><i class="fa-solid fa-rocket"></i> Crop Timeline Tracker</button>
            </div>

            <!-- TAB 1 -->
            <div id="tab-daily" class="tab-content active">
                <div class="task-section">
                    <div class="section-title"><i class="fa-solid fa-calendar-day" style="color: #f59e0b;"></i> Today's Schedule</div>
                    <?php if(count($today) == 0 && count($overdue) == 0): ?>
                        <div style="background: var(--card-bg); border-radius: var(--radius-xl); border: 2px dashed var(--border-color); padding: 50px 20px; text-align: center; color: var(--text-muted); transition: 0.3s;">
                            <i class="fa-solid fa-mug-hot" style="font-size: 3.5rem; margin-bottom: 20px; opacity: 0.4;"></i>
                            <div style="font-weight: 900; font-size: 1.4rem; color: var(--text-main);" class="logo-text">All caught up!</div>
                            <div style="font-size: 1.05rem; margin-top: 8px; font-weight: 500;">No manual tasks due right now.</div>
                        </div>
                    <?php endif; ?>
                    
                    <div class="task-grid">
                        <?php foreach(array_merge($overdue, $today) as $t): $style = getTaskStyling($t['task_type']); $is_od = strtotime($t['due_date']) < time(); ?>
                            <div class="bento-task-card" style="border-left: 6px solid <?php echo $is_od ? '#ef4444' : '#f59e0b'; ?>;">
                                <div class="task-header">
                                    <div class="task-icon-box" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;"><i class="fa-solid <?php echo $style['icon']; ?>"></i></div>
                                    <div class="task-time-badge <?php echo $is_od?'badge-overdue':'badge-today'; ?>"><i class="fa-solid fa-clock"></i> <?php echo $is_od ? 'Overdue!' : date('g:i A', strtotime($t['due_date'])); ?></div>
                                </div>
                                <div class="task-crop"><i class="fa-solid fa-seedling"></i> <?php echo htmlspecialchars($t['crop_name']); ?></div>
                                <div class="task-title"><?php echo htmlspecialchars($t['task_type']); ?> <?php echo getRepeatBadgeHtml($t['repeat_days']); ?></div>
                                <div class="task-desc"><?php echo htmlspecialchars($t['task_description']); ?></div>
                                <div class="task-actions">
                                    <form method="POST" style="flex:1;"><input type="hidden" name="task_id" value="<?php echo $t['id']; ?>"><button type="submit" name="mark_complete" class="btn-complete"><i class="fa-solid fa-check"></i> Complete</button></form>
                                    <form method="POST"><input type="hidden" name="task_id" value="<?php echo $t['id']; ?>"><button type="submit" name="delete_task" class="btn-delete"><i class="fa-solid fa-trash-can"></i></button></form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="task-section">
                    <div class="section-title"><i class="fa-solid fa-calendar-week" style="color: #3b82f6;"></i> Upcoming Custom Tasks</div>
                    <div class="task-grid">
                        <?php foreach($upcoming as $t): $style = getTaskStyling($t['task_type']); ?>
                            <div class="bento-task-card" style="border-left: 6px solid #3b82f6;">
                                <div class="task-header">
                                    <div class="task-icon-box" style="background: <?php echo $style['bg']; ?>; color: <?php echo $style['color']; ?>;"><i class="fa-solid <?php echo $style['icon']; ?>"></i></div>
                                    <div class="task-time-badge badge-upcoming"><i class="fa-regular fa-clock"></i> <?php echo date('M j', strtotime($t['due_date'])); ?></div>
                                </div>
                                <div class="task-title"><?php echo htmlspecialchars($t['task_type']); ?> <?php echo getRepeatBadgeHtml($t['repeat_days']); ?></div>
                                <div class="task-desc"><?php echo htmlspecialchars($t['task_description']); ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- TAB 2: TRACKER WITH EDITABLE NOTES -->
            <div id="tab-timeline" class="tab-content">
                <?php foreach($crops_array as $crop): 
                    $cid = $crop['id'];
                    $q = $conn->query("SELECT * FROM farm_tasks WHERE farmer_id = $farmer_id AND crop_id = $cid AND is_autopilot = 1 ORDER BY due_date ASC");
                    $tracker_tasks = [];
                    while($row = $q->fetch_assoc()) { $tracker_tasks[] = $row; }
                ?>
                
                <div class="bento-task-card" style="margin-bottom: 30px; padding: 35px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; border-bottom: 1px solid var(--border-color); padding-bottom: 20px;">
                        <h2 style="margin:0; font-size: 1.6rem; letter-spacing: -0.5px; display:flex; align-items:center; gap:10px;" class="logo-text">
                            <i class="fa-solid fa-seedling" style="color: #10b981;"></i> <?php echo htmlspecialchars($crop['crop_name']); ?> Pipeline
                        </h2>
                        
                        <?php if(count($tracker_tasks) > 0): ?>
                            <form method="POST" onsubmit="return confirm('Reset timeline?');">
                                <input type="hidden" name="crop_id" value="<?php echo $cid; ?>">
                                <button type="submit" name="delete_autopilot_timeline" class="btn-delete" style="padding: 8px 15px; font-size: 0.9rem; border-radius: 50px;"><i class="fa-solid fa-trash"></i> Reset Timeline</button>
                            </form>
                        <?php endif; ?>
                    </div>

                    <?php if(count($tracker_tasks) == 0): ?>
                        <div style="background: rgba(236, 72, 153, 0.05); border: 1px dashed rgba(236, 72, 153, 0.3); border-radius: var(--radius-lg); padding: 30px;">
                            <h3 style="margin-top:0; color: #ec4899; font-size: 1.3rem;"><i class="fa-solid fa-wand-magic-sparkles"></i> AI Autopilot Setup</h3>
                            <p style="color: var(--text-muted); font-size: 1.05rem; margin-bottom: 20px;">Generate a full lifecycle pipeline instantly. These stages act as reminders for continuous care like watering.</p>
                            
                            <form method="POST" action="tasks.php" style="display: flex; gap: 15px; flex-wrap: wrap;">
                                <input type="hidden" name="auto_crop_id" value="<?php echo $cid; ?>">
                                <div style="flex: 1; min-width: 250px;">
                                    <select name="auto_template" class="form-control" required>
                                        <option value="wheat">🌾 Wheat Lifecycle</option>
                                        <option value="tomato">🍅 Tomato Lifecycle</option>
                                        <option value="corn">🌽 Corn Lifecycle</option>
                                    </select>
                                </div>
                                <div style="flex: 1; min-width: 200px;">
                                    <input type="date" name="auto_start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <button type="submit" name="generate_autopilot" class="btn-action-main" style="margin-top: 0; flex: 1;"><i class="fa-solid fa-rocket"></i> Generate</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="timeline-tracker">
                            <?php 
                            $found_current = false;
                            foreach($tracker_tasks as $index => $tt): 
                                $is_completed = ($tt['status'] == 'completed');
                                $t_time = strtotime($tt['due_date']);
                                $t_date = date('Y-m-d', $t_time);
                                
                                $row_class = "future";
                                $icon_inner = '<i class="fa-solid fa-lock" style="font-size:0.7rem;"></i>';
                                
                                if($is_completed) {
                                    $row_class = "past"; $icon_inner = '<i class="fa-solid fa-check"></i>';
                                } else {
                                    if(!$found_current && ($t_time <= time() || $t_date <= date('Y-m-d') || $index == 0)) {
                                        $row_class = "current"; $icon_inner = '<i class="fa-solid fa-spinner fa-spin"></i>';
                                        $found_current = true;
                                    }
                                }
                            ?>
                                <div class="tl-row <?php echo $row_class; ?>">
                                    <div class="tl-status-icon"><?php echo $icon_inner; ?></div>
                                    <div class="tl-box">
                                        <div class="tl-box-header">
                                            <div class="tl-box-title"><?php echo htmlspecialchars($tt['task_type']); ?></div>
                                            <div class="tl-box-date">
                                                <i class="fa-regular fa-calendar"></i> <?php echo date('M j, Y', $t_time); ?>
                                                <?php if($row_class == "current" && $t_date <= date('Y-m-d')): ?> <strong style="color: inherit; margin-left:5px;">Action Required!</strong> <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <!-- FULLY EDITABLE CUSTOM INSTRUCTIONS BLOCK -->
                                        <form method="POST" style="margin-top: 10px; width: 100%;">
                                            <input type="hidden" name="task_id" value="<?php echo $tt['id']; ?>">
                                            <label style="font-size: 0.85rem; font-weight: 800; color: var(--text-muted); display: block; margin-bottom: 5px;"><i class="fa-solid fa-pen-nib"></i> Instructions & Personal Notes:</label>
                                            <textarea name="custom_notes" rows="2" class="notes-area" <?php echo ($is_completed)?'readonly':''; ?>><?php echo htmlspecialchars($tt['task_description']); ?></textarea>
                                            
                                            <div style="display: flex; gap: 10px; margin-top: 10px; flex-wrap: wrap;">
                                                <?php if(!$is_completed): ?>
                                                    <button type="submit" name="update_task_notes" class="btn-save-notes"><i class="fa-solid fa-floppy-disk"></i> Save Notes</button>
                                                <?php endif; ?>
                                                
                                                <?php if($row_class == "current"): ?>
                                                    <button type="submit" name="mark_complete" class="btn-complete" style="padding: 6px 15px; font-size: 0.9rem; flex: none; border-radius: 50px;">
                                                        <i class="fa-solid fa-check"></i> Verify Stage Completed
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        </form>

                                        <?php if($row_class == "past"): ?>
                                            <div style="position: absolute; right: 25px; top: -15px; font-weight: 900; color: #10b981; font-size: 0.9rem; background: rgba(16, 185, 129, 0.1); padding: 5px 15px; border-radius: 50px; border: 1px solid rgba(16, 185, 129, 0.2);">
                                                <i class="fa-solid fa-check-double"></i> Stage Passed
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<!-- NEW TASK MODAL -->
<div class="modal-overlay" id="taskModal">
    <div class="modal-content">
        <button class="modal-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <h2 style="margin-top:0; border-bottom: 1px solid var(--border-color); padding-bottom: 20px; font-size: 1.6rem;" class="logo-text">Add Custom Task</h2>
        
        <form method="POST" action="tasks.php">
            <div class="form-group">
                <label>Target Crop</label>
                <select name="crop_id" class="form-control" required>
                    <option value="">-- Choose crop --</option>
                    <?php foreach($crops_array as $c): ?> <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['crop_name']); ?></option> <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Category</label>
                <select name="task_type" class="form-control" required>
                    <option value="Watering">💧 Irrigation</option>
                    <option value="Fertilizer">🧪 Fertilizer</option>
                    <option value="Pesticide">🛡️ Pesticide</option>
                    <option value="Weeding">🌿 Weeding</option>
                </select>
            </div>
            <div class="form-group">
                <label>Date & Time</label>
                <input type="datetime-local" name="due_date" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Recurrence <span style="font-weight:500; font-size:0.85rem;">(Auto-spawn)</span></label>
                <select name="repeat_days" class="form-control" required>
                    <option value="0">One-time</option>
                    <option value="1">Daily</option>
                    <option value="3">Every 3 Days</option>
                    <option value="7">Weekly</option>
                    <option value="14">Bi-Weekly</option>
                </select>
            </div>
            <div class="form-group">
                <label>Instructions</label>
                <textarea name="description" class="form-control" rows="2" required></textarea>
            </div>
            <button type="submit" name="add_task" class="btn-action-main">Set Timer</button>
        </form>
    </div>
</div>

<script>
    function switchTab(tabId) {
        document.querySelectorAll('.tab-content').forEach(el => el.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
        document.getElementById(tabId).classList.add('active');
        event.currentTarget.classList.add('active');
    }
    function openModal() { document.getElementById('taskModal').classList.add('active'); }
    function closeModal() { document.getElementById('taskModal').classList.remove('active'); }
    window.onclick = function(event) { if (event.target == document.getElementById('taskModal')) closeModal(); }
</script>

<script src="../assets/js/translate.js"></script>

</body>
</html>
