<?php
session_start();
require_once "../backend/db.php";

/* -------------------------------
   CHECK LOGIN
-------------------------------- */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

/* -------------------------------
   CHECK VENDOR ROLE
-------------------------------- */
$role = trim($_SESSION["role"] ?? "");

if (strcasecmp($role, "Vendor") !== 0) {
    header("Location: ../dashboard.php");
    exit();
}

$vendor_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";

/* -------------------------------
   FIND VENDOR'S RESTAURANT
-------------------------------- */
$sql = "SELECT id, restaurant_name
        FROM restaurants
        WHERE vendor_id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);

if (!$stmt) {
    die("Unable to prepare restaurant query.");
}

mysqli_stmt_bind_param($stmt, "i", $vendor_id);
mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);
$restaurant = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);

/* -------------------------------
   FORM DEFAULT VALUES
-------------------------------- */
$name = "";
$description = "";
$price = "";
$category = "Veg";

/* -------------------------------
   HANDLE FORM SUBMISSION
-------------------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $price = trim($_POST["price"] ?? "");
    $category = trim($_POST["category"] ?? "");

    /* -------------------------------
       VALIDATE RESTAURANT
    -------------------------------- */
    if (!$restaurant) {

        $message = "Please create your restaurant before adding food.";
        $message_type = "error";

    } elseif (
        $name === "" ||
        $price === "" ||
        $category === ""
    ) {

        $message = "Please fill in all required fields.";
        $message_type = "error";

    } elseif (strlen($name) > 150) {

        $message = "Food name must not exceed 150 characters.";
        $message_type = "error";

    } elseif (strlen($description) > 1000) {

        $message = "Description must not exceed 1000 characters.";
        $message_type = "error";

    } elseif (!is_numeric($price) || (float)$price <= 0) {

        $message = "Please enter a valid price greater than zero.";
        $message_type = "error";

    } elseif ((float)$price > 99999999) {

        $message = "The entered price is too high.";
        $message_type = "error";

    } elseif (!in_array($category, ["Veg", "Non Veg", "Other"], true)) {

        $message = "Please select a valid food category.";
        $message_type = "error";
    }

    /* -------------------------------
       IMAGE UPLOAD
    -------------------------------- */
    $image_name = "";
    $uploaded_path = "";

    if (
        $message_type !== "error" &&
        isset($_FILES["image"]) &&
        $_FILES["image"]["error"] !== UPLOAD_ERR_NO_FILE
    ) {

        $file = $_FILES["image"];
        $max_size = 5 * 1024 * 1024;

        if ($file["error"] !== UPLOAD_ERR_OK) {

            $message = "Image upload failed. Please try again.";
            $message_type = "error";

        } elseif ($file["size"] > $max_size) {

            $message = "Image size must not exceed 5 MB.";
            $message_type = "error";

        } else {

            $extension = strtolower(
                pathinfo($file["name"], PATHINFO_EXTENSION)
            );

            $allowed_extensions = ["jpg", "jpeg", "png", "webp"];
            $allowed_mimes = ["image/jpeg", "image/png", "image/webp"];

            $image_info = @getimagesize($file["tmp_name"]);

            if (
                !in_array($extension, $allowed_extensions, true) ||
                $image_info === false ||
                !in_array(
                    $image_info["mime"] ?? "",
                    $allowed_mimes,
                    true
                )
            ) {

                $message = "Only valid JPG, PNG and WEBP images are allowed.";
                $message_type = "error";

            } else {

                $upload_directory = "../backend/uploads/";

                if (
                    !is_dir($upload_directory) &&
                    !mkdir($upload_directory, 0755, true)
                ) {

                    $message = "Unable to create image upload directory.";
                    $message_type = "error";

                } else {

                    try {
                        $random_name = bin2hex(random_bytes(12));
                    } catch (Exception $e) {
                        $random_name = uniqid("food_", true);
                    }

                    $image_name = "food_" . $random_name . "." . $extension;
                    $uploaded_path = $upload_directory . $image_name;

                    if (
                        !move_uploaded_file(
                            $file["tmp_name"],
                            $uploaded_path
                        )
                    ) {

                        $message = "Unable to save the uploaded image.";
                        $message_type = "error";
                        $image_name = "";
                        $uploaded_path = "";
                    }
                }
            }
        }
    }

    /* -------------------------------
       INSERT FOOD ITEM
    -------------------------------- */
    if ($message_type !== "error" && $restaurant) {

        $restaurant_id = (int)$restaurant["id"];

        $sql = "INSERT INTO food_items
                (name, description, price, image, category, restaurant_id)
                VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {

            $message = "Unable to prepare food item query.";
            $message_type = "error";

        } else {

            $food_price = (float)$price;

            mysqli_stmt_bind_param(
                $stmt,
                "ssdssi",
                $name,
                $description,
                $food_price,
                $image_name,
                $category,
                $restaurant_id
            );

            if (mysqli_stmt_execute($stmt)) {

                $message = "Food item added successfully!";
                $message_type = "success";

                $name = "";
                $description = "";
                $price = "";
                $category = "Veg";

            } else {

                $message = "Unable to add the food item. Please try again.";
                $message_type = "error";
            }

            mysqli_stmt_close($stmt);
        }

        /* Remove uploaded image if database insert failed. */
        if (
            $message_type === "error" &&
            $uploaded_path !== "" &&
            file_exists($uploaded_path)
        ) {
            unlink($uploaded_path);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LDD | Add Food Item</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f3f7f6;
            color: #1f2937;
        }

        header {
            background: #0f766e;
            color: white;
            padding: 15px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 23px;
            font-weight: bold;
        }

        .logo span {
            color: #facc15;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .back-btn:hover {
            text-decoration: underline;
        }

        .container {
            max-width: 760px;
            margin: 30px auto;
            padding: 0 18px;
        }

        .page-title {
            margin-bottom: 20px;
        }

        .page-title h1 {
            color: #134e4a;
            font-size: 27px;
            margin-bottom: 7px;
        }

        .page-title p {
            color: #64748b;
            font-size: 14px;
            line-height: 1.5;
        }

        .restaurant-label {
            margin-top: 12px;
            color: #0f766e;
            font-weight: bold;
            font-size: 14px;
        }

        .form-card {
            background: white;
            padding: 26px;
            border-radius: 12px;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            color: #134e4a;
            font-size: 14px;
            font-weight: bold;
        }

        .required {
            color: #dc2626;
        }

        input,
        select,
        textarea {
            width: 100%;
            padding: 11px 12px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            background: white;
            color: #1f2937;
            font-size: 14px;
        }

        textarea {
            min-height: 100px;
            resize: vertical;
        }

        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 0 0 0 3px rgba(15, 118, 110, 0.10);
        }

        .hint {
            color: #6b7280;
            font-size: 12px;
            margin-top: 6px;
        }

        .message {
            padding: 12px 14px;
            margin-bottom: 18px;
            border-radius: 7px;
            font-size: 14px;
            line-height: 1.5;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .submit-btn {
            width: 100%;
            padding: 13px;
            background: #0f766e;
            color: white;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #115e59;
        }

        .manage-link {
            display: block;
            margin-top: 14px;
            text-align: center;
            color: #0f766e;
            text-decoration: none;
            font-size: 14px;
            font-weight: bold;
        }

        .manage-link:hover {
            text-decoration: underline;
        }

        .no-restaurant {
            padding: 20px;
            background: #fff7ed;
            color: #9a3412;
            border-radius: 8px;
            line-height: 1.6;
        }

        footer {
            margin-top: 35px;
            padding: 18px;
            background: #134e4a;
            color: white;
            text-align: center;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            header {
                padding: 15px 5%;
            }

            .logo {
                font-size: 20px;
            }

            .back-btn {
                font-size: 12px;
            }

            .page-title h1 {
                font-size: 23px;
            }

            .form-card {
                padding: 19px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">L<span>D</span>D</div>

    <a href="vendor_dashboard.php" class="back-btn">
        ← Vendor Dashboard
    </a>
</header>

<main class="container">

    <div class="page-title">
        <h1>🍽️ Add Food Item</h1>

        <p>
            Add a food item to your restaurant menu.
            Customers can view available items through LDD.
        </p>

        <?php if ($restaurant): ?>
            <div class="restaurant-label">
                Restaurant:
                <?php
                echo htmlspecialchars(
                    $restaurant["restaurant_name"],
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message, ENT_QUOTES, "UTF-8"); ?>
        </div>
    <?php endif; ?>

    <?php if (!$restaurant): ?>

        <div class="no-restaurant">
            <strong>Restaurant not found.</strong><br>
            Please create your restaurant before adding food items.

            <br><br>

            <a href="manage_restaurant.php">
                Create Your Restaurant →
            </a>
        </div>

    <?php else: ?>

        <div class="form-card">

            <form method="POST" enctype="multipart/form-data">

                <div class="form-group">
                    <label for="name">
                        Food Name <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="name"
                        name="name"
                        maxlength="150"
                        placeholder="Example: Chicken Biriyani"
                        value="<?php echo htmlspecialchars($name, ENT_QUOTES, "UTF-8"); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="description">Description</label>

                    <textarea
                        id="description"
                        name="description"
                        maxlength="1000"
                        placeholder="Describe the food item..."
                    ><?php echo htmlspecialchars($description, ENT_QUOTES, "UTF-8"); ?></textarea>
                </div>

                <div class="form-group">
                    <label for="price">
                        Price (₹) <span class="required">*</span>
                    </label>

                    <input
                        type="number"
                        id="price"
                        name="price"
                        min="0.01"
                        max="99999999"
                        step="0.01"
                        placeholder="Example: 180.00"
                        value="<?php echo htmlspecialchars($price, ENT_QUOTES, "UTF-8"); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="category">
                        Category <span class="required">*</span>
                    </label>

                    <select id="category" name="category" required>
                        <option value="Veg" <?php echo $category === "Veg" ? "selected" : ""; ?>>
                            Veg
                        </option>

                        <option value="Non Veg" <?php echo $category === "Non Veg" ? "selected" : ""; ?>>
                            Non Veg
                        </option>

                        <option value="Other" <?php echo $category === "Other" ? "selected" : ""; ?>>
                            Other
                        </option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="image">Food Image</label>

                    <input
                        type="file"
                        id="image"
                        name="image"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                    <p class="hint">
                        Optional. JPG, PNG or WEBP; maximum size 5 MB.
                    </p>
                </div>

                <button type="submit" class="submit-btn">
                    + Add Food Item
                </button>

                <a href="manage_food.php" class="manage-link">
                    View & Manage Food Items →
                </a>

            </form>

        </div>

    <?php endif; ?>

</main>

<footer>
    © 2026 Local Discover and Deliver | Vendor Panel
</footer>

</body>
</html>

<?php
mysqli_close($conn);
?>