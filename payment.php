<?php
session_start();
require_once "backend/db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$order_id = filter_input(INPUT_GET, "order_id", FILTER_VALIDATE_INT);
$type = strtolower(trim($_GET["type"] ?? ""));

if (!$order_id || $order_id <= 0) {
    exit("Invalid order ID.");
}

$order = null;
$title = "";
$amount = 0;
$item_name = "";
$quantity = 1;

// Fetch the order from the correct table and verify ownership.
if ($type === "food") {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id, food_name, quantity, total
         FROM food_orders
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($order) {
        $title = "Food Order";
        $item_name = $order["food_name"];
        $quantity = (int) $order["quantity"];
        $amount = (float) $order["total"];
    }

} elseif ($type === "grocery") {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, customer_id, grand_total
         FROM grocery_orders
         WHERE id = ? AND customer_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($order) {
        $title = "Grocery Order";
        $item_name = "Grocery items";
        $amount = (float) $order["grand_total"];
    }

} elseif ($type === "print") {

    $stmt = mysqli_prepare(
        $conn,
        "SELECT id, user_id, document_name, copies, total_amount, address
         FROM print_orders
         WHERE id = ? AND user_id = ?
         LIMIT 1"
    );

    mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $order = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    if ($order) {
        $title = "Printout Order";
        $item_name = $order["document_name"];
        $quantity = (int) $order["copies"];
        $amount = (float) $order["total_amount"];
    }

} else {
    exit("Invalid order type.");
}

if (!$order) {
    http_response_code(404);
    exit("Order not found, or you do not have permission to access it.");
}

if ($amount <= 0) {
    exit("Invalid order amount.");
}

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | Payment</title>

<style>
* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}
body {
    margin: 0;
    background: #f5f7f8;
    color: #222;
}
header {
    padding: 16px 7%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
    background: #0f766e;
}
.logo {
    font-size: 24px;
    font-weight: bold;
}
.logo span {
    color: #facc15;
}
nav a {
    margin-left: 18px;
    color: white;
    text-decoration: none;
}
.hero {
    padding: 30px 15px;
    text-align: center;
    color: white;
    background: linear-gradient(135deg, #134e4a, #0f766e);
}
.container {
    max-width: 950px;
    margin: 30px auto;
    padding: 0 18px;
    display: grid;
    grid-template-columns: 1.5fr 1fr;
    gap: 22px;
}
.box {
    padding: 24px;
    background: white;
    border-radius: 10px;
    box-shadow: 0 3px 12px rgba(0,0,0,.08);
}
h2 {
    color: #115e59;
    font-size: 21px;
}
.method {
    display: flex;
    gap: 12px;
    align-items: center;
    padding: 15px;
    margin: 12px 0;
    border: 1px solid #ddd;
    border-radius: 7px;
    cursor: pointer;
}
.method.selected {
    border-color: #0f766e;
    background: #f0fdfa;
}
.method input {
    width: auto;
    accent-color: #0f766e;
}
label.field-label {
    display: block;
    margin: 13px 0 6px;
    font-weight: bold;
    font-size: 14px;
}
input[type="text"],
input[type="tel"],
textarea {
    width: 100%;
    padding: 12px;
    border: 1px solid #ccc;
    border-radius: 6px;
    font-size: 14px;
}
.payment-details {
    display: none;
    margin: 15px 0;
}
.payment-details.active {
    display: block;
}
button {
    width: 100%;
    padding: 14px;
    margin-top: 18px;
    color: white;
    background: #0f766e;
    border: none;
    border-radius: 6px;
    font-size: 16px;
    font-weight: bold;
    cursor: pointer;
}
button:hover {
    background: #115e59;
}
.summary-row {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin: 16px 0;
}
.total {
    padding-top: 16px;
    border-top: 1px solid #ddd;
    color: #0f766e;
    font-size: 21px;
    font-weight: bold;
}
.note {
    margin-top: 18px;
    padding: 12px;
    background: #ecfdf5;
    border-radius: 6px;
    color: #166534;
    font-size: 13px;
    line-height: 1.5;
}
footer {
    margin-top: 30px;
    padding: 18px;
    text-align: center;
    color: white;
    background: #134e4a;
}
@media(max-width:700px) {
    .container {
        grid-template-columns: 1fr;
    }
    header {
        flex-direction: column;
        gap: 12px;
    }
}
</style>
</head>

<body>

<header>
    <div class="logo">L<span>D</span>D</div>
    <nav>
        <a href="dashboard.php">Home</a>
        <a href="dashboard.php">Dashboard</a>
    </nav>
</header>

<section class="hero">
    <h1>Secure Checkout</h1>
    <p>Review your order and select a payment method.</p>
</section>

<main class="container">

<section class="box">
    <h2>Payment Method</h2>

    <form id="paymentForm"
      action="/LOCAL-DISCOVER-AND-DELIVER/backend/process_payment.php" method="POST">
        <input type="hidden" name="order_id"
               value="<?= (int)$order_id ?>">

        <input type="hidden" name="order_type"
               value="<?= e($type) ?>">

        <!-- The server must calculate and verify the amount again. -->
        <input type="hidden" name="payment_method"
               id="payment_method" value="cod">

        <label class="method selected">
            <input type="radio" name="method_choice"
                   value="cod" checked>
            <span>💵</span>
            <span>
                <strong>Cash on Delivery</strong><br>
                <small>Pay when your order arrives</small>
            </span>
        </label>

        <label class="method">
            <input type="radio" name="method_choice" value="upi">
            <span>📱</span>
            <span>
                <strong>UPI Payment</strong><br>
                <small>Payment gateway required for verification</small>
            </span>
        </label>

        <div class="payment-details active" id="codDetails">
            <h3>Delivery Details</h3>

            <label class="field-label" for="customer_name">
                Customer Name
            </label>
            <input type="text" id="customer_name"
                   name="customer_name" maxlength="100"
                   autocomplete="name" required>

            <label class="field-label" for="phone">
                Phone Number
            </label>
            <input type="tel" id="phone" name="phone"
                   maxlength="15" autocomplete="tel" required>

            <label class="field-label" for="address">
                Delivery Address
            </label>
            <textarea id="address" name="address"
                      rows="3" maxlength="500" required><?=
                $type === "print" ? e($order["address"]) : ""
            ?></textarea>
        </div>

        <div class="payment-details" id="upiDetails">
            <p>
                UPI payments require a configured payment gateway
                to verify that the transaction has succeeded.
            </p>
            <p>
                Do not enter your UPI PIN or banking password on this page.
            </p>
        </div>

        <button type="submit" id="submitButton">
            Place Order — ₹<?= number_format($amount, 2) ?>
        </button>

    </form>

    <div class="note">
        Your order amount is retrieved from the database.
        UPI payment will not be marked as paid unless the payment
        is verified through a payment gateway.
    </div>
</section>

<aside class="box">
    <h2>Order Summary</h2>

    <div class="summary-row">
        <span>Order Type</span>
        <strong><?= e($title) ?></strong>
    </div>

    <div class="summary-row">
        <span>Order ID</span>
        <strong>#<?= (int)$order_id ?></strong>
    </div>

    <div class="summary-row">
        <span><?= e($item_name) ?></span>
        <strong><?= $type === "grocery"
            ? ""
            : "× " . (int)$quantity ?></strong>
    </div>

    <div class="summary-row total">
        <span>Total</span>
        <span>₹<?= number_format($amount, 2) ?></span>
    </div>
</aside>

</main>

<footer>
    © 2026 Local Discover and Deliver
</footer>

<script>
const radios = document.querySelectorAll('input[name="method_choice"]');
const methodInput = document.getElementById("payment_method");
const codDetails = document.getElementById("codDetails");
const upiDetails = document.getElementById("upiDetails");
const customerName = document.getElementById("customer_name");
const phone = document.getElementById("phone");
const address = document.getElementById("address");
const submitButton = document.getElementById("submitButton");

function updateMethod() {
    const selected = document.querySelector(
        'input[name="method_choice"]:checked'
    ).value;

    methodInput.value = selected;

    codDetails.classList.toggle("active", selected === "cod");
    upiDetails.classList.toggle("active", selected === "upi");

    customerName.required = selected === "cod";
    phone.required = selected === "cod";
    address.required = selected === "cod";

    submitButton.textContent = selected === "cod"
        ? "Place Order — ₹<?= number_format($amount, 2) ?>"
        : "Continue to UPI Payment";
        
    document.querySelectorAll(".method").forEach(label => {
        label.classList.toggle(
            "selected",
            label.querySelector("input").checked
        );
    });
}

radios.forEach(radio => {
    radio.addEventListener("change", updateMethod);
});

updateMethod();
</script>

</body>
</html>