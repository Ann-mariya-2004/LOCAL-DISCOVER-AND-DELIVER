<?php

session_start();

include "db.php";


if(!isset($_SESSION["user_id"])){

    header("Location: ../login_registration.html");
    exit();

}


$user_id = $_SESSION["user_id"];


// Get form data

$name = mysqli_real_escape_string($conn, $_POST["name"]);
$phone = mysqli_real_escape_string($conn, $_POST["phone"]);
$address = mysqli_real_escape_string($conn, $_POST["address"]);
$location = mysqli_real_escape_string($conn, $_POST["location"]);



$image_name = "";


// Check profile image uploaded or not

if(isset($_FILES["profile_image"]) && $_FILES["profile_image"]["name"] != ""){


    $image_name = $_FILES["profile_image"]["name"];

    $tmp_name = $_FILES["profile_image"]["tmp_name"];


    $upload_folder = "../uploads/";


    // Unique image name

    $new_image_name = time() . "_" . $image_name;


    $upload_path = $upload_folder . $new_image_name;



    move_uploaded_file($tmp_name, $upload_path);



    // Update with image

    $sql = "UPDATE users SET

            name='$name',
            phone='$phone',
            address='$address',
            location='$location',
            profile_image='$new_image_name'

            WHERE id='$user_id'";



}
else{


    // Update without image


    $sql = "UPDATE users SET

            name='$name',
            phone='$phone',
            address='$address',
            location='$location'

            WHERE id='$user_id'";


}



if(mysqli_query($conn,$sql)){


    // Update session name also

    $_SESSION["name"] = $name;


    echo "

    <script>

    alert('Profile Updated Successfully');

    window.location='../profile.php';

    </script>

    ";



}
else{


    echo "

    <script>

    alert('Profile Update Failed');

    window.history.back();

    </script>

    ";


}



mysqli_close($conn);


?>