<?php
session_start();
include("../config/db.php");

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['user_id'])) {
    $theme = $_POST['theme'] === 'dark' ? 'dark' : 'light';
    $user_id = $_SESSION['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET theme_preference = ? WHERE id = ?");
    $stmt->bind_param("si", $theme, $user_id);
    
    if ($stmt->execute()) {
        $_SESSION['theme'] = $theme;
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error']);
    }
}
?>
