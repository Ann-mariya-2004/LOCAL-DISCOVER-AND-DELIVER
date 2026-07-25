<?php

session_start();

include "backend/db.php";


if(!isset($_SESSION["user_id"])){

    header("Location: login_registration.html");
    exit();

}


$user_id = $_SESSION["user_id"];


// Fetch current user details

$sql = "SELECT * FROM users WHERE id='$user_id'";

$result = mysqli_query($conn, $sql);

$user = mysqli_fetch_assoc($result);


?>


<!DOCTYPE html>
<html>

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LDD | Edit Profile</title>


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



.profile-box{

    max-width:600px;
    margin:auto;
    background:white;
    padding:40px;
    border-radius:20px;
    box-shadow:0 12px 30px rgba(0,0,0,0.25);

}



h2{

    text-align:center;
    color:#134e4a;
    margin-bottom:30px;

}



.profile-preview{

    text-align:center;
    margin-bottom:25px;

}



.profile-preview img{

    width:120px;
    height:120px;
    border-radius:50%;
    object-fit:cover;
    border:4px solid #0f766e;

}



.default-image{

    width:120px;
    height:120px;
    margin:auto;
    border-radius:50%;
    background:#ccfbf1;
    display:flex;
    justify-content:center;
    align-items:center;
    font-size:55px;

}



label{

    color:#134e4a;
    font-weight:bold;

}



input, textarea{

    width:100%;
    padding:13px;
    margin:8px 0 18px;
    border:1px solid #ccc;
    border-radius:8px;
    font-size:14px;
    outline-color:#0f766e;

}



textarea{

    resize:none;
    height:100px;

}



.save-btn{

    width:100%;
    padding:13px;
    background:#0f766e;
    color:white;
    border:none;
    border-radius:8px;
    font-size:16px;
    font-weight:bold;
    cursor:pointer;

}



.save-btn:hover{

    background:#115e59;

}



.back-btn{

    display:block;
    text-align:center;
    margin-top:20px;
    color:#0f766e;
    text-decoration:none;

}



</style>


</head>


<body>


<div class="profile-box">


<h2>
Edit Profile
</h2>



<div class="profile-preview">


<?php

if(!empty($user["profile_image"])){

?>

<img src="uploads/<?php echo $user['profile_image']; ?>">


<?php

}
else{

?>

<div class="default-image">
👤
</div>


<?php

}

?>


</div>





<form action="backend/update_profile.php" method="POST" enctype="multipart/form-data">


<label>
Profile Photo
</label>

<input 
type="file"
name="profile_image"
accept="image/*">





<label>
Name
</label>

<input 
type="text"
name="name"
value="<?php echo $user['name']; ?>"
required>





<label>
Phone
</label>

<input 
type="text"
name="phone"
value="<?php echo $user['phone']; ?>"

required>





<label>
Address
</label>

<textarea 
name="address"
required><?php echo $user['address']; ?></textarea>





<label>
Location
</label>

<input 
type="text"
name="location"
value="<?php echo $user['location']; ?>"

required>





<button type="submit" class="save-btn">

Save Changes

</button>



</form>



<a href="profile.php" class="back-btn">

← Back to Profile

</a>



</div>



</body>

</html>