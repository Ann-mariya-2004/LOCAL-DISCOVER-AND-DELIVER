<?php
session_start();

require_once __DIR__ . "/db.php";

mysqli_set_charset($conn, "utf8mb4");

// --------------------------------------------------
// 1. Check login and request method
// --------------------------------------------------

if (!isset($_SESSION["user_id"])) {
    header("Location: ../print_order.html?error=login");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: ../print_order.html");
    exit;
}

$userId = (int) $_SESSION["user_id"];

// --------------------------------------------------
// 2. Read the fields used by your actual HTML form
// --------------------------------------------------

$copies = filter_var(
    $_POST["copies"] ?? null,
    FILTER_VALIDATE_INT
);

$colorInput = trim($_POST["color_type"] ?? "");
$address = trim($_POST["address"] ?? "");
$paperSize = trim($_POST["paper_size"] ?? "A4");
$instructions = trim($_POST["instructions"] ?? "");

// HTML sends "Black & White", not "B&W".
$normalizedColor = strtolower($colorInput);

if (in_array($normalizedColor, [
    "black & white",
    "black and white",
    "b&w",
    "bw",
    "black_white"
], true)) {
    $colorType = "B&W";
} elseif (in_array($normalizedColor, [
    "colour",
    "color"
], true)) {
    $colorType = "Colour";
} else {
    $colorType = "";
}

// --------------------------------------------------
// 3. Validate the submitted data
// --------------------------------------------------

if (
    $copies === false ||
    $copies === null ||
    $copies < 1 ||
    $copies > 500 ||
    $colorType === "" ||
    !in_array($paperSize, ["A4", "A3", "Letter"], true) ||
    mb_strlen($address) < 5 ||
    mb_strlen($address) > 1000 ||
    mb_strlen($instructions) > 2000
) {
    header("Location: ../print_order.html?error=invalid");
    exit;
}

// --------------------------------------------------
// 4. Validate the uploaded document
// --------------------------------------------------

if (
    !isset($_FILES["document"]) ||
    $_FILES["document"]["error"] !== UPLOAD_ERR_OK
) {
    header("Location: ../print_order.html?error=upload");
    exit;
}

$file = $_FILES["document"];

if ($file["size"] <= 0 || $file["size"] > 10 * 1024 * 1024) {
    header("Location: ../print_order.html?error=upload");
    exit;
}

$originalName = basename($file["name"]);
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

$allowedExtensions = [
    "pdf", "doc", "docx", "jpg", "jpeg", "png"
];

if (!in_array($extension, $allowedExtensions, true)) {
    header("Location: ../print_order.html?error=upload");
    exit;
}

// Verify the uploaded file's MIME type.
if (!function_exists("finfo_open")) {
    error_log("PHP fileinfo extension is unavailable.");
    header("Location: ../print_order.html?error=upload");
    exit;
}

$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime = finfo_file($finfo, $file["tmp_name"]);
finfo_close($finfo);

$allowedMimeTypes = [
    "pdf"  => ["application/pdf"],
    "doc"  => ["application/msword", "application/CDFV2"],
    "docx" => [
        "application/vnd.openxmlformats-officedocument.wordprocessingml.document",
        "application/zip"
    ],
    "jpg"  => ["image/jpeg"],
    "jpeg" => ["image/jpeg"],
    "png"  => ["image/png"]
];

if (!in_array($mime, $allowedMimeTypes[$extension], true)) {
    header("Location: ../print_order.html?error=upload");
    exit;
}

// --------------------------------------------------
// 5. Calculate price on the server
// --------------------------------------------------

$pricePerCopy = ($colorType === "Colour") ? 5.00 : 2.00;
$printingCost = $copies * $pricePerCopy;
$deliveryCharge = 20.00;
$totalAmount = $printingCost + $deliveryCharge;

// Never trust the total_amount sent by the browser.
// The amount is calculated independently above.

// --------------------------------------------------
// 6. Check the existing database table columns
// --------------------------------------------------

$tableResult = mysqli_query($conn, "SHOW COLUMNS FROM print_orders");

if (!$tableResult) {
    error_log("Unable to inspect print_orders table: " . mysqli_error($conn));
    http_response_code(500);
    exit("Unable to access the print order database table.");
}

$existingColumns = [];

while ($column = mysqli_fetch_assoc($tableResult)) {
    $existingColumns[] = $column["Field"];
}

mysqli_free_result($tableResult);

// Match fields against the actual table structure.
// Alternative names are supported where possible.
$columnMap = [
    "user_id" => ["user_id"],
    "document_name" => ["document_name"],
    "document_path" => ["document_path", "file_path", "file_url"],
    "copies" => ["copies", "quantity"],
    "color_type" => ["color_type", "colour_type"],
    "paper_size" => ["paper_size"],
    "address" => ["delivery_address", "address"],
    "instructions" => ["instructions", "special_instructions"],
    "total_amount" => ["total_amount", "total_price", "amount"],
    "status" => ["status", "order_status"],
    "payment_status" => ["payment_status"],
    "created_at" => ["created_at", "order_date"]
];

$data = [
    "user_id" => $userId,
    "document_name" => $originalName,
    "document_path" => null,
    "copies" => $copies,
    "color_type" => $colorType,
    "paper_size" => $paperSize,
    "address" => $address,
    "instructions" => $instructions,
    "total_amount" => $totalAmount,
    "status" => "Pending",
    "payment_status" => "Pending",
    "created_at" => date("Y-m-d H:i:s")
];

$insertData = [];

foreach ($columnMap as $key => $possibleNames) {
    foreach ($possibleNames as $possibleName) {
        if (in_array($possibleName, $existingColumns, true)) {
            $insertData[$possibleName] = $data[$key];
            break;
        }
    }
}

// These fields must exist to save a valid order.
$requiredFields = [
    "user_id",
    "document_name",
    "copies",
    "color_type",
    "paper_size"
];

foreach ($requiredFields as $requiredField) {
    if (!isset($insertData[$requiredField])) {
        error_log("Missing required print_orders column: " . $requiredField);
        http_response_code(500);
        exit(
            "The print_orders table is missing a required column: " .
            htmlspecialchars($requiredField, ENT_QUOTES, "UTF-8") .
            ". Please check the table structure."
        );
    }
}

// Address and total amount must be stored too.
if (
    !isset($insertData["delivery_address"]) &&
    !isset($insertData["address"])
) {
    http_response_code(500);
    exit("Please add an address column to the print_orders table.");
}

if (
    !isset($insertData["total_amount"]) &&
    !isset($insertData["total_price"]) &&
    !isset($insertData["amount"])
) {
    http_response_code(500);
    exit("Please add a total_amount column to the print_orders table.");
}

// --------------------------------------------------
// 7. Prepare a private document upload directory
// --------------------------------------------------

$uploadDirectory = __DIR__ . "/uploads/print_documents/";

if (
    !is_dir($uploadDirectory) &&
    !mkdir($uploadDirectory, 0755, true)
) {
    http_response_code(500);
    exit("Unable to prepare document storage.");
}

// Use a random server-side filename.
$storedFilename = bin2hex(random_bytes(16)) . "." . $extension;
$absolutePath = $uploadDirectory . $storedFilename;
$relativePath = "backend/uploads/print_documents/" . $storedFilename;

// Save the relative file path only if the table has a matching column.
if (isset($insertData["document_path"])) {
    $insertData["document_path"] = $relativePath;
} elseif (isset($insertData["file_path"])) {
    $insertData["file_path"] = $relativePath;
} elseif (isset($insertData["file_url"])) {
    $insertData["file_url"] = $relativePath;
}

// --------------------------------------------------
// 8. Upload document and save the order
// --------------------------------------------------

if (!move_uploaded_file($file["tmp_name"], $absolutePath)) {
    header("Location: ../print_order.html?error=upload");
    exit;
}

$columnNames = array_keys($insertData);

// Column names originate only from the database's actual column list.
$quotedColumns = array_map(
    function ($columnName) {
        return "`" . str_replace("`", "``", $columnName) . "`";
    },
    $columnNames
);

$placeholders = implode(
    ", ",
    array_fill(0, count($columnNames), "?")
);

$sql = "INSERT INTO `print_orders` (" .
       implode(", ", $quotedColumns) .
       ") VALUES (" . $placeholders . ")";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    unlink($absolutePath);
    error_log("Print order prepare failed: " . mysqli_error($conn));
    http_response_code(500);
    exit("Unable to prepare the print order.");
}

$values = array_values($insertData);

// Bind all values as strings; MySQL converts them according to column types.
$types = str_repeat("s", count($values));

$bindArguments = [$types];

foreach ($values as $key => $value) {
    $bindArguments[] = &$values[$key];
}

call_user_func_array(
    "mysqli_stmt_bind_param",
    array_merge([$stmt], $bindArguments)
);

if (!mysqli_stmt_execute($stmt)) {
    unlink($absolutePath);
    error_log("Print order insert failed: " . mysqli_stmt_error($stmt));
    mysqli_stmt_close($stmt);
    mysqli_close($conn);

    header("Location: ../print_order.html?error=save");
    exit;
}

$orderId = mysqli_insert_id($conn);

mysqli_stmt_close($stmt);
mysqli_close($conn);

// --------------------------------------------------
// 9. Redirect to the existing success popup
// --------------------------------------------------

header(
    "Location: ../print_order.html?success=1&order_id=" .
    urlencode((string) $orderId)
);
exit;