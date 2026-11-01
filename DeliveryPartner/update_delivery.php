<?php
/**
 * Local Discover and Deliver (LDD)
 * Update Delivery Status
 *
 * Handles delivery status updates securely and redirects
 * the delivery partner to the appropriate page.
 */

session_start();
require_once "../backend/db.php";

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please try again later.");
}

// Verify login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"] ?? "";

// Verify Delivery Partner role
if (strcasecmp(trim($role), "Delivery Partner") !== 0) {
    header("Location: ../dashboard.php");
    exit;
}

// Allow POST requests only
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_deliveries.php");
    exit;
}

// Validate order ID
$order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);
$new_status = trim($_POST["delivery_status"] ?? "");

// Allowed status transitions
$allowed_transitions = [
    "Assigned"  => ["Picked Up"],
    "Picked Up" => ["On the Way"],
    "On the Way" => ["Delivered"]
];

// Store a message and destination for the next page
function setDeliveryMessage(string $type, string $text, string $redirect): void {
    $_SESSION["delivery_message"] = [
        "type" => $type,
        "text" => $text,
        "redirect" => $redirect
    ];

    header("Location: " . $redirect);
    exit;
}

// Validate order ID
if (!$order_id || $order_id < 1) {
    setDeliveryMessage(
        "error",
        "Invalid order selected.",
        "my_deliveries.php"
    );
}

// Validate requested status
if (!in_array($new_status, ["Picked Up", "On the Way", "Delivered"], true)) {
    setDeliveryMessage(
        "error",
        "Invalid delivery status selected.",
        "my_deliveries.php"
    );
}

// Fetch the order assigned to this partner
$stmt = $conn->prepare(
    "SELECT delivery_status
     FROM food_orders
     WHERE id = ?
       AND delivery_partner_id = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log("Delivery status query error: " . $conn->error);

    setDeliveryMessage(
        "error",
        "Unable to update delivery status. Please try again.",
        "my_deliveries.php"
    );
}

$stmt->bind_param("ii", $order_id, $user_id);
$stmt->execute();

$result = $stmt->get_result();
$order = $result->fetch_assoc();
$stmt->close();

// Verify order ownership
if (!$order) {
    setDeliveryMessage(
        "error",
        "Order not found or it is not assigned to your account.",
        "my_deliveries.php"
    );
}

$current_status = trim($order["delivery_status"] ?? "Assigned");

// Check allowed transition
if (
    !isset($allowed_transitions[$current_status]) ||
    !in_array($new_status, $allowed_transitions[$current_status], true)
) {
    setDeliveryMessage(
        "error",
        "This status change is not allowed. Please follow the delivery steps in order.",
        "my_deliveries.php"
    );
}

// Update the status only if the current status has not changed
$update = $conn->prepare(
    "UPDATE food_orders
     SET delivery_status = ?
     WHERE id = ?
       AND delivery_partner_id = ?
       AND delivery_status = ?"
);

if (!$update) {
    error_log("Delivery status update error: " . $conn->error);

    setDeliveryMessage(
        "error",
        "Unable to update delivery status.",
        "my_deliveries.php"
    );
}

$update->bind_param(
    "siis",
    $new_status,
    $order_id,
    $user_id,
    $current_status
);

$success = $update->execute() && $update->affected_rows === 1;
$update->close();

if (!$success) {
    setDeliveryMessage(
        "error",
        "The delivery status could not be changed. Please refresh and try again.",
        "my_deliveries.php"
    );
}

// Select destination and message based on updated status
if ($new_status === "Picked Up") {
    setDeliveryMessage(
        "success",
        "Order #" . $order_id . " has been picked up successfully!",
        "my_deliveries.php"
    );
}

if ($new_status === "On the Way") {
    setDeliveryMessage(
        "success",
        "Order #" . $order_id . " is now on the way!",
        "my_deliveries.php"
    );
}

if ($new_status === "Delivered") {
    setDeliveryMessage(
        "success",
        "Order #" . $order_id . " has been delivered successfully!",
        "delivery_history.php"
    );
}

// Fallback
header("Location: my_deliveries.php");
exit;
?>
