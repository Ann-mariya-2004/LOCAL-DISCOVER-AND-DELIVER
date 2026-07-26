<?php

session_start();

include "backend/db.php";

if(!isset($_SESSION["user_id"])){

    header("Location: login_registration.html");
    exit();

}


$name = $_SESSION["name"];
$email = $_SESSION["email"];
$role = $_SESSION["role"];


$user_id = $_SESSION["user_id"];

// Get profile image

$sql = "SELECT profile_image FROM users WHERE id='$user_id'";

$result = mysqli_query($conn,$sql);

$user_image = mysqli_fetch_assoc($result);

$profile_image = $user_image["profile_image"];


?>


<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LDD | Dashboard</title>


<style>

*{
    box-sizing:border-box;
    font-family:Arial, sans-serif;
}


body{

    margin:0;
    min-height:100vh;
    background:linear-gradient(135deg,#0f766e,#14b8a6);
    padding:20px;

}


/* Main Container */

.dashboard-container{

    max-width:1100px;
    margin:auto;
    background:white;
    min-height:600px;
    border-radius:20px;
    overflow:hidden;
    box-shadow:0 12px 30px rgba(0,0,0,0.25);

}



/* Header */


.header{

    background:#115e59;
    color:white;
    padding:25px 35px;
    display:flex;
    justify-content:space-between;
    align-items:center;

}


.logo{

    font-size:28px;
    font-weight:bold;

}


.logo span{

    color:#99f6e4;

}



.logout-btn{

    background:#14b8a6;
    color:white;
    border:none;
    padding:12px 25px;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;

}


.logout-btn:hover{

    background:#0f766e;

}



/* Content */


.content{

    padding:40px;

}



.welcome{

    margin-bottom:30px;

}


.welcome h1{

    color:#134e4a;
    margin-bottom:8px;

}


.welcome p{

    color:#666;

}




/* User Card */


.user-card{

    background:#f0fdfa;
    border-left:5px solid #0f766e;
    padding:20px;
    border-radius:12px;
    margin-bottom:35px;

}


.user-card p{

    margin:8px 0;
    color:#444;

}




/* Services */


.services{

    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:30px;
    margin-top:30px;

}

@media(max-width:800px){


}

.service-card{

    background:#ffffff;
    border-radius:20px;
    padding:35px 25px;
    text-align:center;
    border:none;
    box-shadow:0 8px 25px rgba(0,0,0,0.12);
    transition:0.3s;

}


.service-card:hover{

    transform:translateY(-8px);
    box-shadow:0 15px 35px rgba(0,0,0,0.18);

}



.icon{

    font-size:40px;
    margin-bottom:15px;

}

.icon{

    width:80px;
    height:80px;
    margin:auto;
    background:#ccfbf1;
    border-radius:50%;
    display:flex;
    justify-content:center;
    align-items:center;
    font-size:40px;
    margin-bottom:20px;

}

.service-card h3{

    color:#134e4a;

}



.service-btn{

    margin-top:15px;
    padding:10px 20px;
    background:#0f766e;
    color:white;
    border:none;
    border-radius:8px;
    cursor:pointer;
    font-weight:bold;

}



.service-btn:hover{

    background:#115e59;

}

.header-right{

    display:flex;
    align-items:center;
    gap:20px;

}




.profile-icon:hover{

    background:#0f766e;

}

.profile-icon{

    width:45px;
    height:45px;
    background:#14b8a6;
    border-radius:50%;
    display:flex;
    justify-content:center;
    align-items:center;
    overflow:hidden;
    cursor:pointer;
    font-size:24px;

}


.profile-icon img{

    width:100%;
    height:100%;
    object-fit:cover;

}
.service-btn{

    display:inline-block;
    margin-top:15px;
    padding:10px 20px;
    background:#0f766e;
    color:white;
    border-radius:8px;
    text-decoration:none;
    font-weight:bold;

}

.location-box{

    background:#f0fdfa;
    padding:25px;
    border-radius:15px;
    margin-bottom:30px;

}

.location-box input{

    width:60%;
    padding:12px;
    border:1px solid #ccc;
    border-radius:8px;

}

</style>


</head>


<body>


<div class="dashboard-container">


<div class="header">

<div class="logo">
L<span>DD</span>
</div>


<div class="header-right">


<div class="profile-icon" onclick="openProfile()">


<?php

if(!empty($profile_image)){

?>

<img src="uploads/<?php echo $profile_image; ?>">


<?php

}
else{

?>

👤


<?php

}

?>


</div>


<button class="logout-btn" onclick="logout()">
Logout
</button>


</div>


</div>



<div class="content">


<div class="welcome">

<h1>
Welcome, <?php echo $name; ?> 👋
</h1>

<p>
Local Discover & Deliver - Your local services at one place.
</p>

</div>

<div class="location-box">

<h3>📍 Current Location</h3>

<p id="locationText">Location not detected.</p>

<button onclick="getLocation()" class="service-btn">
Use My Location
</button>

<br><br>

<input type="text" id="searchInput" placeholder="Search restaurant, food or print shop">

<button onclick="searchService()" class="service-btn">
Search
</button>

</div>


<!--<div class="user-card">

<p>
<strong>Email:</strong> <?php echo $email; ?>
</p>


<p>
<strong>Role:</strong> <?php echo $role; ?>
</p>


</div>
-->




<div class="services">


<div class="service-card">

<div class="icon">
🍔
</div>

<h3>
Food Services
</h3>

<p>
Discover nearby food services.
</p>

<a href="restaurants.html" class="service-btn">

Explore

</a>
</div>





<div class="service-card">

<div class="icon">
🛒
</div>

<h3>
Grocery Services
</h3>

<p>
Order daily essentials easily.
</p>

<a href="#" class="service-btn">
Explore
</a>

</div>





<div class="service-card">

<div class="icon">
📄
</div>

<h3>
Printing Services
</h3>

<p>
Upload and print documents.
</p>

<a href="#" class="service-btn">
Explore
</a>

</div>



</div>


</div>


</div>



<script>

function logout(){

    window.location="backend/logout.php";

}
function openProfile(){

    window.location="profile.php";

}

function getLocation(){

    if(navigator.geolocation){

        navigator.geolocation.getCurrentPosition(showPosition);

    }

    else{

        alert("Geolocation is not supported.");

    }

}

function showPosition(position){

    let lat=position.coords.latitude;

    let lon=position.coords.longitude;

    fetch("https://nominatim.openstreetmap.org/reverse?format=json&lat="+lat+"&lon="+lon)

    .then(response=>response.json())

    .then(data=>{

        document.getElementById("locationText").innerHTML=data.display_name;

    });

}

function searchService(){

    let text=document.getElementById("searchInput").value.toLowerCase();

    if(text.includes("food") || text.includes("restaurant")){

        window.location="food.php";

    }

    else if(text.includes("print")){

        window.location="print_order.html";

    }

    else{

        alert("No matching service found.");

    }

}


</script>


</body>

</html>