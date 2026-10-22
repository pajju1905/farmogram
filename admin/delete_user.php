<?php
session_start();
include("../config/db.php");

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin'){
    header("Location: ../auth/login.php");
    exit();
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

/* Prevent deleting admin */
$conn->query("DELETE FROM users WHERE id = $id AND role != 'admin'");

header("Location: users.php");
exit();
?>
