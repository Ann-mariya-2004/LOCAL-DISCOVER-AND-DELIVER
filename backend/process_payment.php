<?php
session_start();

require_once __DIR__ . "/db.php";

// Allow logged-in users only
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

// Process POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../dashboard.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

$order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
$order_type = strtolower(trim($_POST["order_type"] ?? ""));
$payment_method = strtolower(trim($_POST["payment_method"] ?? ""));

// Customer details
$customer_name = trim($_POST["customer_name"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$delivery_address = trim($_POST["address"] ?? "");

// Supported order types
$allowed_types = ["food", "grocery", "print"];

if (!$order_id || !in_array($order_type, $allowed_types, true)) {
    showMessage("Invalid order details.", "error");
}

// Normalize payment method
if (in_array($payment_method, ["cod", "cash on delivery"], true)) {
    $payment_method = "COD";
} elseif ($payment_method === "upi") {
    $payment_method = "UPI";
} else {
    showMessage("Please select a valid payment method.", "error");
}

// Validate customer details for COD
if ($payment_method === "COD") {
    if ($customer_name === "" || $phone === "" || $delivery_address === "") {
        showMessage(
            "Please enter your name, phone number and delivery address.",
            "error"
        );
    }

    if (!preg_match('/^[0-9]{10}$/', $phone)) {
        showMessage("Enter a valid 10-digit phone number.", "error");
    }
}

// Fetch order from the correct table and verify ownership
switch ($order_type) {

    case "food":
        $stmt = $conn->prepare(
            "SELECT id, total FROM food_orders
             WHERE id = ? AND user_id = ?
             LIMIT 1"
        );
        break;

    case "grocery":
        $stmt = $conn->prepare(
            "SELECT id, grand_total FROM grocery_orders
             WHERE id = ? AND customer_id = ?
             LIMIT 1"
        );
        break;

    case "print":
        $stmt = $conn->prepare(
            "SELECT id, total_amount FROM print_orders
             WHERE id = ? AND user_id = ?
             LIMIT 1"
        );
        break;
}

if (!$stmt) {
    showMessage("Unable to prepare the order query.", "error");
}

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

if (!$order) {
    showMessage("Order not found or you do not have permission to access it.", "error");
}

// Get amount from the database; never trust an amount sent by the browser.
if ($order_type === "food") {
    $amount = (float) $order["total"];
} elseif ($order_type === "grocery") {
    $amount = (float) $order["grand_total"];
} else {
    $amount = (float) $order["total_amount"];
}

if ($amount <= 0) {
    showMessage("Invalid order amount.", "error");
}

// UPI cannot be marked as paid without a verified payment gateway.
$payment_status = "Pending";

$conn->begin_transaction();

try {

    // Update the relevant order table.
    if ($order_type === "food") {

        $stmt = $conn->prepare(
            "UPDATE food_orders
             SET customer_name = ?,
                 phone = ?,
                 delivery_address = ?,
                 payment_method = ?,
                 payment_status = ?
             WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param(
            "sssssii",
            $customer_name,
            $phone,
            $delivery_address,
            $payment_method,
            $payment_status,
            $order_id,
            $user_id
        );

    } elseif ($order_type === "grocery") {

        $stmt = $conn->prepare(
            "UPDATE grocery_orders
             SET customer_name = ?,
                 phone = ?,
                 delivery_address = ?,
                 payment_method = ?,
                 payment_status = ?
             WHERE id = ? AND customer_id = ?"
        );

        $stmt->bind_param(
            "sssssii",
            $customer_name,
            $phone,
            $delivery_address,
            $payment_method,
            $payment_status,
            $order_id,
            $user_id
        );

    } else {

        // Print orders already contain an address column.
        // Save COD address when supplied; keep the existing address otherwise.
        $stmt = $conn->prepare(
            "UPDATE print_orders
             SET address = CASE WHEN ? <> '' THEN ? ELSE address END,
                 payment_method = ?,
                 payment_status = ?
             WHERE id = ? AND user_id = ?"
        );

        $stmt->bind_param(
            "ssssii",
            $delivery_address,
            $delivery_address,
            $payment_method,
            $payment_status,
            $order_id,
            $user_id
        );
    }

    if (!$stmt || !$stmt->execute()) {
        throw new Exception("Could not update the order.");
    }

    $stmt->close();

    // Record the payment attempt.
    // order_type distinguishes Food #1 from Grocery #1 or Print #1.
    $stmt = $conn->prepare(
        "INSERT INTO payments
         (order_id, user_id, amount, payment_method, payment_status, order_type)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    if (!$stmt) {
        throw new Exception("Could not prepare the payment record.");
    }

    $stmt->bind_param(
        "iidsss",
        $order_id,
        $user_id,
        $amount,
        $payment_method,
        $payment_status,
        $order_type
    );

    if (!$stmt->execute()) {
        throw new Exception("Could not save the payment record.");
    }

    $stmt->close();

    $conn->commit();

    // Redirect to the confirmation page.
   // header(
       // "Location: ../payment_success.php?order_id=" .
       // $order_id . "&type=" . urlencode($order_type)
   // );
   // exit();
   // Redirect to the existing dashboard after successful processing.
header("Location: ../dashboard.php");
exit();

} catch (Throwable $e) {

    $conn->rollback();

    error_log("Payment processing error: " . $e->getMessage());

    showMessage(
        "Unable to process this payment right now. Please check your order and try again.",
        "error"
    );
}


// Display a message and safely return to the previous page.
function showMessage($message, $type = "error")
{
    $safeMessage = json_encode(
        $message,
        JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
    );

    $safeType = json_encode($type);

    echo "<!DOCTYPE html>
    <html lang='en'>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Payment Status | LDD</title>
        <script src='https://cdn.jsdelivr.net/npm/sweetalert2@11'></script>
    </head>
    <body>
        <script>
            Swal.fire({
                title: " . $safeType . " === 'error' ? 'Payment Error' : 'Notice',
                text: " . $safeMessage . ",
                icon: " . $safeType . ",
                confirmButtonText: 'OK'
            }).then(() => {
                window.history.back();
            });
        </script>
    </body>
    </html>";

    exit();
}
?>