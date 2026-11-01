<?php

header('Content-Type: application/json');

require_once "db.php";

/* Send JSON response and stop execution */
function sendResponse($status, $title, $message)
{
    echo json_encode([
        "status" => $status,
        "title" => $title,
        "message" => $message
    ]);
    exit();
}

/* Check request method */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    sendResponse(
        "error",
        "Invalid Request",
        "Invalid request method."
    );
}

/* Get form data */
$name = trim($_POST["fullName"] ?? "");
$email = trim($_POST["email"] ?? "");
$phone = trim($_POST["phone"] ?? "");
$address = trim($_POST["address"] ?? "");
$role = trim($_POST["role"] ?? "");
$password = $_POST["password"] ?? "";

/* Validate required fields */
if (
    $name === "" ||
    $email === "" ||
    $phone === "" ||
    $address === "" ||
    $role === "" ||
    $password === ""
) {
    sendResponse(
        "error",
        "Missing Information",
        "Please fill in all the required fields."
    );
}

/* Validate email */
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    sendResponse(
        "error",
        "Invalid Email",
        "Please enter a valid email address."
    );
}

/* Allow only the registration roles supported by this form */
$allowedRoles = [
    "Customer",
    "Vendor",
    "Delivery Partner"
];

if (!in_array($role, $allowedRoles, true)) {
    sendResponse(
        "error",
        "Invalid Role",
        "Please select a valid registration role."
    );
}

/* Check database connection */
if (!$conn) {
    sendResponse(
        "error",
        "Database Error",
        "Unable to connect to the database."
    );
}

/* Check whether email already exists */
$check = mysqli_prepare(
    $conn,
    "SELECT id FROM users WHERE email = ? LIMIT 1"
);

mysqli_stmt_bind_param($check, "s", $email);
mysqli_stmt_execute($check);

$checkResult = mysqli_stmt_get_result($check);
$emailExists = mysqli_num_rows($checkResult) > 0;

mysqli_stmt_close($check);

if ($emailExists) {
    sendResponse(
        "error",
        "Email Already Registered",
        "An account with this email already exists."
    );
}

/* Hash password */
$hashedPassword = password_hash($password, PASSWORD_DEFAULT);

/*
|--------------------------------------------------------------------------
| Registration transaction
|--------------------------------------------------------------------------
| First insert the user.
| If the role is Delivery Partner, create the corresponding partner record.
| Both operations succeed together, or neither is saved.
*/

mysqli_begin_transaction($conn);

try {

    /* Insert user account */
    $insertUser = mysqli_prepare(
        $conn,
        "INSERT INTO users
         (name, email, phone, address, role, password)
         VALUES (?, ?, ?, ?, ?, ?)"
    );

    mysqli_stmt_bind_param(
        $insertUser,
        "ssssss",
        $name,
        $email,
        $phone,
        $address,
        $role,
        $hashedPassword
    );

    if (!mysqli_stmt_execute($insertUser)) {
        throw new Exception("User registration failed.");
    }

    /* Get the newly created user's ID */
    $userId = mysqli_insert_id($conn);

    mysqli_stmt_close($insertUser);

    /*
     * Create a delivery partner record only when the selected role
     * is Delivery Partner.
     */
    if ($role === "Delivery Partner") {

        $vehicleType = "";
        $vehicleNumber = "";
        $availabilityStatus = "Available";

        $insertPartner = mysqli_prepare(
            $conn,
            "INSERT INTO delivery_partners
             (user_id, vehicle_type, vehicle_number, availability_status)
             VALUES (?, ?, ?, ?)"
        );

        mysqli_stmt_bind_param(
            $insertPartner,
            "isss",
            $userId,
            $vehicleType,
            $vehicleNumber,
            $availabilityStatus
        );

        if (!mysqli_stmt_execute($insertPartner)) {
            throw new Exception("Delivery partner registration failed.");
        }

        mysqli_stmt_close($insertPartner);
    }

    /* Save both records */
    mysqli_commit($conn);

    sendResponse(
        "success",
        "Welcome to LDD!",
        "Your account has been created successfully. Please login to continue."
    );

} catch (Throwable $e) {

    /* Undo both inserts if registration fails */
    mysqli_rollback($conn);

    error_log("LDD registration error: " . $e->getMessage());

    sendResponse(
        "error",
        "Registration Failed",
        "Unable to create your account. Please try again."
    );
}

?>