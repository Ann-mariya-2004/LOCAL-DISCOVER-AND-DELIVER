<?php
session_start();

require_once "db.php";

// Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

// Accept only POST requests.
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../restaurants.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$food_id = filter_input(INPUT_POST, "food_id", FILTER_VALIDATE_INT);
$quantity = filter_input(INPUT_POST, "quantity", FILTER_VALIDATE_INT);

// Validate the submitted food ID and quantity.
if (!$food_id || !$quantity || $quantity < 1 || $quantity > 50) {
    exit("Invalid order details. Please select a food item and valid quantity.");
}

// Get the food details from the database.
$stmt = mysqli_prepare(
    $conn,
    "SELECT id, restaurant_id, name, price
     FROM food_items
     WHERE id = ?"
);

if (!$stmt) {
    error_log("Food order prepare error: " . mysqli_error($conn));
    exit("Unable to process the order right now. Please try again.");
}

mysqli_stmt_bind_param($stmt, "i", $food_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$food = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Check whether the food item exists.
if (!$food) {
    exit("Food item not found. Please go back and select another item.");
}

if ($food["price"] === null || !is_numeric($food["price"]) || (float)$food["price"] < 0) {
    exit("Invalid food price. Please contact the vendor.");
}

$restaurant_id = $food["restaurant_id"];
$food_name = $food["name"];
$price = (float) $food["price"];
$total = round($price * $quantity, 2);

// Save the order in the food_orders table.
$stmt = mysqli_prepare(
    $conn,
    "INSERT INTO food_orders
        (user_id, restaurant_id, food_name, quantity, price, total,
         delivery_status, payment_status)
     VALUES (?, ?, ?, ?, ?, ?, 'Available', 'Pending')"
);

if (!$stmt) {
    error_log("Food order insert prepare error: " . mysqli_error($conn));
    exit("Unable to create the order. Please try again.");
}

mysqli_stmt_bind_param(
    $stmt,
    "iisidd",
    $user_id,
    $restaurant_id,
    $food_name,
    $quantity,
    $price,
    $total
);

if (!mysqli_stmt_execute($stmt)) {
    error_log("Food order insert error: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    exit("Order could not be saved. Please try again.");
}

$order_id = mysqli_insert_id($conn);
mysqli_stmt_close($stmt);

// Continue to the payment page for this order.
header("Location: ../payment.php?order_id=" . $order_id . "&type=food");
exit();
?>