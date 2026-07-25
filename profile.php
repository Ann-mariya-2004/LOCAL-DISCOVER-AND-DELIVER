<?php

session_start();

include "backend/db.php";


if(!isset($_SESSION["user_id"])){

    header("Location: login_registration.html");
    exit();

}


$user_id = $_SESSION["user_id"];


// Fetch user details

$sql = "SELECT * FROM users WHERE id='$user_id'";

$result = mysqli_query($conn, $sql);


$user = mysqli_fetch_assoc($result);


?>


<!DOCTYPE html>
<html>

<head>

<title>LDD | Profile</title>


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


.profile-container{

    max-width:600px;
    margin:auto;
    background:white;
    border-radius:20px;
    padding:40px;
    box-shadow:0 12px 30px rgba(0,0,0,0.25);

}



.profile-img{

    width:120px;
    height:120px;
    border-radius:50%;
    background:#ccfbf1;
    display:flex;
    justify-content:center;
    align-items:center;
    font-size:55px;
    margin:auto;

}



h1{

    text-align:center;
    color:#134e4a;

}



.details{

    margin-top:30px;

}



.details p{

    background:#f0fdfa;
    padding:12px;
    border-radius:8px;
    margin:10px 0;
    color:#444;

}



.edit-btn{

    display:block;
    text-align:center;
    margin-top:25px;
    background:#0f766e;
    color:white;
    padding:12px;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;

}


.edit-btn:hover{

    background:#115e59;

}



.back-btn{

    display:block;
    text-align:center;
    margin-top:15px;
    color:#0f766e;
    text-decoration:none;

}


</style>


</head>


<body>


<div class="profile-container">


<div class="profile-img">

<?php

if(!empty($user["profile_image"])){

    echo "<img src='uploads/".$user["profile_image"]."' width='120' height='120' style='border-radius:50%;'>";

}
else{

    echo "👤";

}

?>

</div>



<h1>
<?php echo $user["name"]; ?>
</h1>



<div class="details">


<p>
<strong>Email:</strong>
<?php echo $user["email"]; ?>
</p>


<p>
<strong>Phone:</strong>
<?php echo $user["phone"] ?? "Not Added"; ?>
</p>


<p>
<strong>Address:</strong>
<?php echo $user["address"] ?? "Not Added"; ?>
</p>


<p>
<strong>Location:</strong>
<?php echo $user["location"] ?? "Not Added"; ?>
</p>


<p>
<strong>Role:</strong>
<?php echo $user["role"]; ?>
</p>



</div>



<a href="edit_profile.php" class="edit-btn">
Edit Profile
</a>



<a href="dashboard.php" class="back-btn">
← Back to Dashboard
</a>



</div>



</body>

</html>