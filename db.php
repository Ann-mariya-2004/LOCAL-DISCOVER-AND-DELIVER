<?php

$servername="localhost";
$username="root";
$password="";
$database="local_discover_deliver";

$conn=mysqli_connect($servername,$username,$password,$database);

if(!$conn){
    die("Connection Failed : ".mysqli_connect_error());
}

?>