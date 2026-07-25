<?php

header("Content-Type: application/json");

include "db.php";

if ($_SERVER["REQUEST_METHOD"] != "POST") {
    echo json_encode([
        "status" => "error",
        "message" => "Invalid Request"
    ]);
    exit;
}

$email = mysqli_real_escape_string($conn, trim($_POST["email"]));
$password = $_POST["password"];

// Check user by email
$sql = "SELECT * FROM users WHERE email='$email' LIMIT 1";
$result = mysqli_query($conn, $sql);

if (mysqli_num_rows($result) == 1) {

    $user = mysqli_fetch_assoc($result);

    // Verify hashed password
    if (password_verify($password, $user["password"])) {

        echo json_encode([
            "status" => "success",
            "message" => "Login Successful",
            "role" => $user["role"],
            "name" => $user["name"]
        ]);

    } else {

        echo json_encode([
            "status" => "error",
            "message" => "Incorrect Password"
        ]);

    }

} else {

    echo json_encode([
        "status" => "error",
        "message" => "Email not found"
    ]);

}

mysqli_close($conn);

?>