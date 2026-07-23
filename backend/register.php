<?php
include "db.php";

 $name=$_POST['fullName'];
 $email=$_POST['email'];
 $phone =$_POST['phone'];
 $address=$_POST['address'];

 $password=password_hash($_POST['password'],PASSWORD_DEFAULT);

 $sql ="INSERT INTO customers(name,email,phone,address,password)VALUES('$name','$email','$phone','$address','$password')";
 if (mysqli_query($conn, $sql)) {
    echo "Registration Successful!";
} else {
    echo "Registration Failed: " . mysqli_error($conn);
}
?>