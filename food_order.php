<?php
session_start();
require_once __DIR__ . "/backend/db.php";

// Require login before placing a food order.
if (!isset($_SESSION["user_id"])) {
    header("Location: login_registration.html");
    exit();
}

// Validate the selected food item ID.
$food_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$food_id || $food_id < 1) {
    header("Location: restaurants.php");
    exit();
}

// Fetch the food item and its restaurant ID.
$stmt = mysqli_prepare(
    $conn,
    "SELECT
        fi.id,
        fi.restaurant_id,
        fi.name,
        fi.description,
        fi.price,
        fi.image,
        r.restaurant_name
     FROM food_items AS fi
     INNER JOIN restaurants AS r
        ON r.id = fi.restaurant_id
     WHERE fi.id = ?
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to load food details. Please try again.");
}

mysqli_stmt_bind_param($stmt, "i", $food_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$food = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

// Redirect if the food item or its restaurant does not exist.
if (!$food) {
    header("Location: restaurants.php");
    exit();
}

// Escape output before displaying it in HTML.
function e($value): string {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Resolve the food image from existing upload folders.
function getFoodImage($image): string {
    $image = basename((string)$image);

    if ($image === "") {
        return "";
    }

    $folders = [
        __DIR__ . "/uploads/",
        __DIR__ . "/backend/uploads/"
    ];

    foreach ($folders as $folder) {
        if (is_file($folder . $image)) {
            $relative = str_replace(
                __DIR__ . DIRECTORY_SEPARATOR,
                "",
                $folder
            );

            $relative = str_replace(
                DIRECTORY_SEPARATOR,
                "/",
                $relative
            );

            return $relative . rawurlencode($image);
        }
    }

    return "";
}

$imagePath = getFoodImage($food["image"] ?? "");
$price = (float)$food["price"];
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Food Order | LDD</title>

<style>
* {
    box-sizing: border-box;
}

body {
    margin: 0;
    min-height: 100vh;
    padding: 25px 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: Arial, Helvetica, sans-serif;
    background: linear-gradient(135deg, #ecfdf5, #ccfbf1, #f0fdfa);
    color: #18312f;
}

.order-box {
    width: 100%;
    max-width: 470px;
    padding: 28px;
    background: #ffffff;
    border: 1px solid #d1fae5;
    border-radius: 18px;
    box-shadow: 0 15px 40px rgba(15, 118, 110, 0.12);
}

.back-link {
    display: inline-block;
    margin-bottom: 18px;
    color: #0f766e;
    text-decoration: none;
    font-size: 13px;
    font-weight: 700;
}

.food-image,
.image-placeholder {
    display: block;
    width: 100%;
    height: 205px;
    margin-bottom: 20px;
    border-radius: 13px;
    object-fit: cover;
    background: #f0fdfa;
}

.image-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 60px;
}

.restaurant-name {
    margin: 0 0 8px;
    color: #0f766e;
    font-size: 13px;
    font-weight: 700;
}

h1 {
    margin: 0 0 10px;
    color: #134e4a;
    font-size: 25px;
}

.description {
    margin: 0 0 18px;
    color: #64748b;
    font-size: 14px;
    line-height: 1.6;
}

.price-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 14px;
    margin-bottom: 20px;
    background: #f0fdfa;
    border: 1px solid #ccfbf1;
    border-radius: 11px;
}

.price-label {
    color: #64748b;
    font-size: 13px;
}

.price-value {
    color: #0f766e;
    font-size: 21px;
    font-weight: 800;
}

label {
    display: block;
    margin-bottom: 8px;
    color: #334155;
    font-size: 13px;
    font-weight: 700;
}

input[type="number"] {
    width: 100%;
    padding: 12px;
    margin-bottom: 18px;
    border: 1px solid #cbd5e1;
    border-radius: 9px;
    font-size: 15px;
}

input:focus {
    outline: 2px solid #99f6e4;
    border-color: #0f766e;
}

.total-box {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    padding: 15px 0;
    margin-bottom: 15px;
    border-top: 1px solid #e2e8f0;
    color: #134e4a;
    font-weight: 700;
}

.total-amount {
    color: #0f766e;
    font-size: 23px;
}

button {
    width: 100%;
    padding: 14px;
    border: none;
    border-radius: 10px;
    background: #0f766e;
    color: #ffffff;
    font-size: 15px;
    font-weight: 700;
    cursor: pointer;
}

button:hover {
    background: #115e59;
}

.note {
    margin: 13px 0 0;
    color: #64748b;
    text-align: center;
    font-size: 12px;
}

@media (max-width: 480px) {
    .order-box {
        padding: 22px 18px;
    }
}
</style>
</head>

<body>

<div class="order-box">

    <a class="back-link"
       href="restaurants.php">
        ← Back to restaurants
    </a>

    <?php if ($imagePath !== ""): ?>

        <img
            class="food-image"
            src="<?= e($imagePath) ?>"
            alt="<?= e($food["name"]) ?>"
        >

    <?php else: ?>

        <div class="image-placeholder" aria-hidden="true">
            🍽️
        </div>

    <?php endif; ?>

    <p class="restaurant-name">
        <?= e($food["restaurant_name"]) ?>
    </p>

    <h1><?= e($food["name"]) ?></h1>

    <p class="description">
        <?= e($food["description"] ?: "Freshly prepared for you.") ?>
    </p>

    <div class="price-box">
        <span class="price-label">Price per item</span>

        <span class="price-value">
            ₹<?= number_format($price, 2) ?>
        </span>
    </div>

    <form action="backend/place_food_order.php" method="POST">

        <!-- Pass the selected food and its actual restaurant to the backend. -->
        <input type="hidden"
               name="food_id"
               value="<?= (int)$food["id"] ?>">

        <input type="hidden"
               name="restaurant_id"
               value="<?= (int)$food["restaurant_id"] ?>">

        <label for="quantity">Quantity</label>

        <input
            type="number"
            id="quantity"
            name="quantity"
            value="1"
            min="1"
            max="50"
            required
        >

        <div class="total-box">
            <span>Total food price</span>

            <span class="total-amount" id="total">
                ₹<?= number_format($price, 2) ?>
            </span>
        </div>

        <button type="submit">
            Place Order →
        </button>

        <p class="note">
            Delivery charges, if applicable, will be handled by the order system.
        </p>

    </form>

</div>

<script>
const unitPrice = <?= json_encode($price) ?>;
const quantityInput = document.getElementById("quantity");
const totalDisplay = document.getElementById("total");

function calculateTotal() {
    let quantity = parseInt(quantityInput.value, 10);

    if (!Number.isFinite(quantity) || quantity < 1) {
        quantity = 1;
    }

    totalDisplay.textContent = "₹" +
        (unitPrice * quantity).toLocaleString("en-IN", {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
}

quantityInput.addEventListener("input", calculateTotal);
</script>

</body>
</html>
