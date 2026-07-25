<?php

session_start();

include "backend/db.php";


if(!isset($_SESSION["user_id"])){

    header("Location: login_registration.html");
    exit();

}


if(isset($_GET["id"])){

    $food_id = $_GET["id"];


    $sql = "SELECT * FROM food_items WHERE id='$food_id'";

    $result = mysqli_query($conn,$sql);

    $food = mysqli_fetch_assoc($result);

}


?>


<!DOCTYPE html>
<html>

<head>

<title>LDD | Food Order</title>


<style>

body{

font-family:Arial;
background:linear-gradient(135deg,#0f766e,#14b8a6);
padding:30px;

}


.order-box{

width:450px;
margin:auto;
background:white;
padding:30px;
border-radius:20px;
box-shadow:0 10px 25px rgba(0,0,0,0.2);
text-align:center;

}


.order-box img{

width:200px;
height:200px;
object-fit:cover;
border-radius:15px;

}


input{

width:100%;
padding:12px;
margin:10px 0;

}


button{

background:#0f766e;
color:white;
padding:12px;
width:100%;
border:none;
border-radius:8px;
font-weight:bold;

}


</style>


</head>


<body>


<div class="order-box">


<?php if(!empty($food["image"])) { ?>

<img src="uploads/<?php echo $food['image']; ?>">

<?php } else { ?>

<img src="https://via.placeholder.com/200">

<?php } ?>


<h2>
<?php echo $food['name']; ?>
</h2>


<p>
<?php echo $food['description']; ?>
</p>


<h3>
Total: ₹ <span id="total">
<?php echo $food['price']; ?>
</span>
</h3>


<form action="backend/place_food_order.php" method="POST">

<input type="hidden" name="food_id"
value="<?php echo $food['id']; ?>">

<input type="hidden" name="food_name" 
value="<?php echo $food['name']; ?>">


<input type="hidden" name="price"
value="<?php echo $food['price']; ?>">



<input type="number" 
id="quantity"
name="quantity"
value="1"
min="1"
oninput="calculateTotal()"
required>


<button type="submit">

Place Order

</button>


</form>


</div>
<script>
function calculateTotal(){

    let price = <?php echo $food['price']; ?>;

    let quantity = document.getElementById("quantity").value;

    document.getElementById("total").innerHTML = price * quantity;

}
</script>
</body>

</html>