<?php

header('Content-Type: application/json');

include "db.php";

// Check request method
if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        "status" => "error",
        "title" => "Invalid Request",
        "message" => "Invalid request method."
    ]);
    exit();
}

// Get form data
$name = trim($_POST['fullName']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$address = trim($_POST['address']);
$role = trim($_POST['role']);
$password = $_POST['password'];

// Check empty fields
if (
    empty($name) ||
    empty($email) ||
    empty($phone) ||
    empty($address) ||
    empty($role) ||
    empty($password)
) {

    echo json_encode([
        "status" => "error",
        "title" => "Missing Information",
        "message" => "Please fill in all the required fields."
    ]);
    exit();
}

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    echo json_encode([
        "status" => "error",
        "title" => "Invalid Email",
        "message" => "Please enter a valid email address."
    ]);
    exit();
}

// Check duplicate email
$check = mysqli_query($conn, "SELECT id FROM users WHERE email='$email'");

if (mysqli_num_rows($check) > 0) {

    echo json_encode([
        "status" => "error",
        "title" => "Email Already Registered",
        "message" => "An account with this email already exists."
    ]);
    exit();
}

// Hash password
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

// Insert user
$sql = "INSERT INTO users (name, email, phone, address, role, password)
        VALUES ('$name', '$email', '$phone', '$address', '$role', '$hashedPassword')";

if (mysqli_query($conn, $sql)) {

    echo json_encode([
        "status" => "success",
        "title" => "Welcome to LDD!",
        "message" => "Your account has been created successfully. Please login to continue."
    ]);

} else {

    echo json_encode([
        "status" => "error",
        "title" => "Registration Failed",
        "message" => "Unable to create your account. Please try again."
    ]);

}

mysqli_close($conn);

?>