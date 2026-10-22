<?php
session_start();
?>

<link rel="stylesheet" href="../assets/css/main.css">
<link rel="stylesheet" href="../assets/css/buyer.css">

<div class="main-content">
<h1>Your Cart</h1>

<table class="cart-table">
<tr>
<th>Product</th>
<th>Price</th>
<th>Quantity</th>
<th>Total</th>
</tr>

<?php
$total = 0;
if(isset($_SESSION['cart'])){
    foreach($_SESSION['cart'] as $id=>$item){
        $itemTotal = $item['price'] * $item['quantity'];
        $total += $itemTotal;
        echo "<tr>
            <td>{$item['name']}</td>
            <td>{$item['price']}</td>
            <td>{$item['quantity']}</td>
            <td>$itemTotal</td>
        </tr>";
    }
}
?>

<tr>
<td colspan="3"><strong>Total</strong></td>
<td><strong>₹ <?php echo $total; ?></strong></td>
</tr>
</table>

<a href="../buyer/checkout.php" class="btn-primary">Proceed to Checkout</a>

</div>
