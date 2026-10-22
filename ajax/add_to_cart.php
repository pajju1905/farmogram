<?php
session_start();
include("../config/db.php");

$product_id = $_POST['product_id'];

$result = $conn->query("SELECT * FROM products WHERE id=$product_id");
$product = $result->fetch_assoc();

if(!isset($_SESSION['cart'])){
    $_SESSION['cart'] = [];
}

if(isset($_SESSION['cart'][$product_id])){
    $_SESSION['cart'][$product_id]['quantity']++;
} else {
    $_SESSION['cart'][$product_id] = [
        "name"=>$product['crop_name'],
        "price"=>$product['price'],
        "quantity"=>1
    ];
}

echo "Product added to cart!";
?>
