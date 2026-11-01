<?php

session_start();

include "db.php";

/* --------------------------------
   CHECK LOGIN
-------------------------------- */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}


/* --------------------------------
   CHECK VENDOR ROLE
-------------------------------- */

$role = trim($_SESSION["role"] ?? "");

if (strcasecmp($role, "Vendor") !== 0) {
    header("Location: ../dashboard.php");
    exit();
}


$vendor_id = (int) $_SESSION["user_id"];


/* --------------------------------
   CHECK REQUEST
-------------------------------- */

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    die("Invalid request.");
}


/* --------------------------------
   GET FORM DATA
-------------------------------- */

$name = trim($_POST["name"] ?? "");
$description = trim($_POST["description"] ?? "");
$price = trim($_POST["price"] ?? "");
$category = trim($_POST["category"] ?? "");


/* --------------------------------
   VALIDATE DATA
-------------------------------- */

if ($name === "" || $description === "" || $price === "" || $category === "") {
    die("Please fill in all fields.");
}


/* --------------------------------
   FIND VENDOR RESTAURANT
-------------------------------- */

$sql = "SELECT id
        FROM restaurants
        WHERE vendor_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param($stmt, "i", $vendor_id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    die("No restaurant is linked to your vendor account. Please create your restaurant first.");
}

$restaurant = mysqli_fetch_assoc($result);

$restaurant_id = (int) $restaurant["id"];

mysqli_stmt_close($stmt);


/* --------------------------------
   IMAGE UPLOAD
-------------------------------- */

$image_name = "";

if (isset($_FILES["image"]) && $_FILES["image"]["error"] === UPLOAD_ERR_OK) {

    $original_name = basename($_FILES["image"]["name"]);

    $image_name = time() . "_" . $original_name;

    $path = __DIR__ . "/uploads/" . $image_name;

    if (!move_uploaded_file($_FILES["image"]["tmp_name"], $path)) {
        die("Image upload failed.");
    }
}


/* --------------------------------
   INSERT FOOD
-------------------------------- */

$sql = "INSERT INTO food_items
        (name, description, price, image, category, restaurant_id)
        VALUES (?, ?, ?, ?, ?, ?)";

$stmt = mysqli_prepare($conn, $sql);

mysqli_stmt_bind_param(
    $stmt,
    "ssdssi",
    $name,
    $description,
    $price,
    $image_name,
    $category,
    $restaurant_id
);


if (mysqli_stmt_execute($stmt)) {

    echo "
    <script>
        alert('Food Added Successfully');
        window.location='../vendor/vendor_dashboard.php';
    </script>
    ";

} else {

    echo "Database Error: " . mysqli_error($conn);
}


mysqli_stmt_close($stmt);

mysqli_close($conn);

?>