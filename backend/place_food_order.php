<?php

session_start();

include "db.php";


if(!isset($_SESSION["user_id"])){

    header("Location: ../login_registration.html");
    exit();

}


$user_id = $_SESSION["user_id"];


$food_name = $_POST["food_name"];

$quantity = $_POST["quantity"];

$price = $_POST["price"];


$total = $quantity * $price;



$sql = "INSERT INTO food_orders

(user_id, food_name, quantity, price, total)

VALUES

('$user_id',
'$food_name',
'$quantity',
'$price',
'$total')";



if(mysqli_query($conn,$sql)){


echo "

<script>

alert('Order Placed Successfully');

window.location='../dashboard.php';

</script>

";


}
else{


echo mysqli_error($conn);


}


?>