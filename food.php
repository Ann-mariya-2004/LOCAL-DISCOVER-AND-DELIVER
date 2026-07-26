<?php

session_start();

include "backend/db.php";


if(!isset($_SESSION["user_id"])){

    header("Location: login_registration.html");
    exit();

}


// Get restaurant id

$restaurant_id = isset($_GET["restaurant_id"]) ? $_GET["restaurant_id"] : 0;

// Fetch food items of selected restaurant

$sql = "SELECT * FROM food_items WHERE restaurant_id='$restaurant_id'";

$result = mysqli_query($conn, $sql);

?>


<!DOCTYPE html>
<html>

<head>

<title>LDD | Food Services</title>


<style>

*{
    box-sizing:border-box;
    font-family:Arial,sans-serif;
}


body{

    margin:0;
    min-height:100vh;
    background:linear-gradient(135deg,#0f766e,#14b8a6);
    padding:30px;

}


.container{

    max-width:1100px;
    margin:auto;
    background:white;
    padding:40px;
    border-radius:20px;
    box-shadow:0 12px 30px rgba(0,0,0,0.25);

}


h1{

    text-align:center;
    color:#134e4a;

}


.food-grid{

    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(250px,1fr));
    gap:25px;
    margin-top:30px;

}


.food-card{

    border:1px solid #ddd;
    border-radius:15px;
    padding:20px;
    text-align:center;

}


.food-card img{

    width:150px;
    height:150px;
    border-radius:10px;
    object-fit:cover;

}


.food-card h3{

    color:#134e4a;

}


.price{

    color:#0f766e;
    font-weight:bold;

}


.order-btn{

    background:#0f766e;
    color:white;
    padding:10px 20px;
    border-radius:8px;
    border:none;

}


</style>

</head>


<body>


<div class="container">


<h1>
🍔 Food Services
</h1>



<div class="food-grid">


<?php while($food=mysqli_fetch_assoc($result)){ ?>


<div class="food-card">


<?php if(!empty($food["image"])) { ?>

<img src="uploads/<?php echo $food['image']; ?>">

<?php } else { ?>

<img src="https://via.placeholder.com/150">

<?php } ?>



<h3>
<?php echo $food["name"]; ?>
</h3>


<p>
<?php echo $food["description"]; ?>
</p>


<p class="price">
₹ <?php echo $food["price"]; ?>
</p>




<a href="food_order.php?id=<?php echo $food['id']; ?>" class="order-btn">
Order Now
</a>


</div>


<?php } ?>


</div>


</div>


</body>

</html>