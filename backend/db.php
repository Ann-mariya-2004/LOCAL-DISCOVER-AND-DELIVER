<?php
// Database connection settings
$servername = "localhost";
$username = "root";
$password = "";
$database = "local_discover_deliver";

// Connect to MySQL database
$conn = mysqli_connect($servername, $username, $password, $database);

// Check database connection
if (!$conn) {
    die("Connection Failed: " . mysqli_connect_error());
}

// Set character encoding
mysqli_set_charset($conn, "utf8mb4");
?>