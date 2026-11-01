
<?php
session_start();

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . "/db.php";

function sendJson($status, $message, $extra = [])
{
    echo json_encode(array_merge([
        "status" => $status,
        "message" => $message
    ], $extra));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    sendJson("error", "Invalid request method.");
}

$email = trim($_POST["email"] ?? "");
$password = $_POST["password"] ?? "";

if ($email === "" || $password === "") {
    sendJson("error", "Email and password are required.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendJson("error", "Please enter a valid email address.");
}

$stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, email, password, role
     FROM users
     WHERE email = ?
     LIMIT 1"
);

if (!$stmt) {
    error_log("Login prepare failed: " . mysqli_error($conn));
    http_response_code(500);
    sendJson("error", "A database error occurred. Please try again.");
}

mysqli_stmt_bind_param($stmt, "s", $email);

if (!mysqli_stmt_execute($stmt)) {
    error_log("Login execute failed: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    http_response_code(500);
    sendJson("error", "A database error occurred. Please try again.");
}

$result = mysqli_stmt_get_result($stmt);
$user = $result ? mysqli_fetch_assoc($result) : null;

mysqli_stmt_close($stmt);

if (!$user || !password_verify($password, $user["password"])) {
    sendJson("error", "Invalid email or password.");
}

$role = strtolower(trim($user["role"]));
$role = preg_replace('/\s+/', ' ', $role);

$allowedRoles = [
    "customer" => "Customer",
    "vendor" => "Vendor",
    "delivery partner" => "Delivery Partner",
     "admin" => "Admin"
];

if (!isset($allowedRoles[$role])) {
    sendJson("error", "This account has an unrecognized role.");
}

session_regenerate_id(true);

$_SESSION["user_id"] = $user["id"];
$_SESSION["name"] = $user["name"];
$_SESSION["email"] = $user["email"];
$_SESSION["role"] = $allowedRoles[$role];

sendJson("success", "Login successful.", [
    "role" => $allowedRoles[$role],
    "name" => $user["name"]
]);
