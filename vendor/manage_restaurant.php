<?php
session_start();
require_once "../backend/db.php";

/* -------------------- LOGIN CHECK -------------------- */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

/* -------------------- VENDOR CHECK -------------------- */
if (strcasecmp(trim($_SESSION["role"] ?? ""), "Vendor") !== 0) {
    header("Location: ../dashboard.php");
    exit();
}

$vendor_id = (int) $_SESSION["user_id"];

$message = "";
$message_type = "";

$allowed_categories = [
    "Fast Food",
    "Vegetarian",
    "Non Veg",
    "Pizza",
    "Cafe",
    "Other"
];

$upload_directory = __DIR__ . "/../backend/uploads/";

/* -------------------- GET RESTAURANT -------------------- */
function getVendorRestaurant($conn, $vendor_id)
{
    $sql = "SELECT *
            FROM restaurants
            WHERE vendor_id = ?
            LIMIT 1";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    mysqli_stmt_bind_param($stmt, "i", $vendor_id);
    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);
    $restaurant = mysqli_fetch_assoc($result);

    mysqli_stmt_close($stmt);

    return $restaurant ?: null;
}

$restaurant = getVendorRestaurant($conn, $vendor_id);

/* -------------------- FORM VALUES -------------------- */
$form_restaurant_name = $restaurant["restaurant_name"] ?? "";
$form_phone = $restaurant["phone"] ?? "";
$form_location = $restaurant["location"] ?? "";
$form_category = $restaurant["category"] ?? "";
$form_description = $restaurant["description"] ?? "";
$form_latitude = $restaurant["latitude"] ?? "";
$form_longitude = $restaurant["longitude"] ?? "";

/* -------------------- HANDLE FORM -------------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $restaurant_name = trim($_POST["restaurant_name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $category = trim($_POST["category"] ?? "");
    $description = trim($_POST["description"] ?? "");
    $latitude = trim($_POST["latitude"] ?? "");
    $longitude = trim($_POST["longitude"] ?? "");

    /* Preserve submitted values */
    $form_restaurant_name = $restaurant_name;
    $form_phone = $phone;
    $form_location = $location;
    $form_category = $category;
    $form_description = $description;
    $form_latitude = $latitude;
    $form_longitude = $longitude;

    $message = "";
    $message_type = "";

    /* -------------------- VALIDATION -------------------- */
    if (
        $restaurant_name === "" ||
        $phone === "" ||
        $location === "" ||
        $category === ""
    ) {
        $message = "Please fill in all required fields.";
        $message_type = "error";

    } elseif (strlen($restaurant_name) > 150) {
        $message = "Restaurant name must not exceed 150 characters.";
        $message_type = "error";

    } elseif (strlen($phone) > 20 ||
        !preg_match('/^[0-9+\-\s()]{7,20}$/', $phone)) {
        $message = "Please enter a valid phone number.";
        $message_type = "error";

    } elseif (strlen($location) > 255) {
        $message = "Location must not exceed 255 characters.";
        $message_type = "error";

    } elseif (strlen($description) > 1000) {
        $message = "Description must not exceed 1000 characters.";
        $message_type = "error";

    } elseif (!in_array($category, $allowed_categories, true)) {
        $message = "Please select a valid restaurant category.";
        $message_type = "error";
    }

    /* -------------------- LATITUDE VALIDATION -------------------- */
    if ($message_type !== "error" && $latitude !== "") {
        if (!is_numeric($latitude) ||
            (float)$latitude < -90 ||
            (float)$latitude > 90) {

            $message = "Enter a valid latitude between -90 and 90.";
            $message_type = "error";
        }
    }

    /* -------------------- LONGITUDE VALIDATION -------------------- */
    if ($message_type !== "error" && $longitude !== "") {
        if (!is_numeric($longitude) ||
            (float)$longitude < -180 ||
            (float)$longitude > 180) {

            $message = "Enter a valid longitude between -180 and 180.";
            $message_type = "error";
        }
    }

    /* -------------------- IMAGE HANDLING -------------------- */
    $old_image = $restaurant["image"] ?? "";
    $image_name = $old_image;
    $new_uploaded_image = "";

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
                pathinfo(basename($file["name"]), PATHINFO_EXTENSION)
            );

            $allowed_extensions = ["jpg", "jpeg", "png", "webp"];
            $allowed_mimes = ["image/jpeg", "image/png", "image/webp"];

            $image_info = @getimagesize($file["tmp_name"]);
            $mime = $image_info["mime"] ?? "";

            if (
                !in_array($extension, $allowed_extensions, true) ||
                !in_array($mime, $allowed_mimes, true)
            ) {
                $message = "Only valid JPG, JPEG, PNG or WEBP images are allowed.";
                $message_type = "error";

            } else {
                if (
                    !is_dir($upload_directory) &&
                    !mkdir($upload_directory, 0755, true)
                ) {
                    $message = "Unable to create the image upload folder.";
                    $message_type = "error";

                } else {
                    try {
                        $random_name = bin2hex(random_bytes(8));
                    } catch (Exception $e) {
                        $random_name = uniqid("", true);
                    }

                    $new_uploaded_image =
                        "restaurant_" . $vendor_id . "_" .
                        $random_name . "." . $extension;

                    $destination =
                        $upload_directory . $new_uploaded_image;

                    if (move_uploaded_file($file["tmp_name"], $destination)) {
                        $image_name = $new_uploaded_image;
                    } else {
                        $new_uploaded_image = "";
                        $message = "Unable to save the uploaded image.";
                        $message_type = "error";
                    }
                }
            }
        }
    }

    /* -------------------- SAVE RESTAURANT -------------------- */
    if ($message_type !== "error") {

        if ($restaurant) {

            /* Update only this vendor's restaurant */
            $restaurant_id = (int)$restaurant["id"];

            $sql = "UPDATE restaurants
                    SET restaurant_name = ?,
                        phone = ?,
                        location = ?,
                        category = ?,
                        image = ?,
                        latitude = ?,
                        longitude = ?,
                        description = ?
                    WHERE id = ? AND vendor_id = ?";

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                $message = "Database error while preparing restaurant update.";
                $message_type = "error";

            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    "ssssssssii",
                    $restaurant_name,
                    $phone,
                    $location,
                    $category,
                    $image_name,
                    $latitude,
                    $longitude,
                    $description,
                    $restaurant_id,
                    $vendor_id
                );

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Restaurant details updated successfully.";
                    $message_type = "success";
                } else {
                    $message = "Unable to update restaurant details.";
                    $message_type = "error";
                }

                mysqli_stmt_close($stmt);
            }

        } else {

            /* Create a new restaurant */
            $sql = "INSERT INTO restaurants
                    (
                        vendor_id,
                        restaurant_name,
                        phone,
                        location,
                        category,
                        image,
                        latitude,
                        longitude,
                        description
                    )
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

            $stmt = mysqli_prepare($conn, $sql);

            if (!$stmt) {
                $message = "Database error while preparing restaurant creation.";
                $message_type = "error";

            } else {
                mysqli_stmt_bind_param(
                    $stmt,
                    "issssssss",
                    $vendor_id,
                    $restaurant_name,
                    $phone,
                    $location,
                    $category,
                    $image_name,
                    $latitude,
                    $longitude,
                    $description
                );

                if (mysqli_stmt_execute($stmt)) {
                    $message = "Restaurant created successfully.";
                    $message_type = "success";
                } else {
                    $message = "Unable to create restaurant.";
                    $message_type = "error";
                }

                mysqli_stmt_close($stmt);
            }
        }
    }

    /* -------------------- CLEAN UP UPLOAD ON FAILURE -------------------- */
    if (
        $message_type === "error" &&
        $new_uploaded_image !== ""
    ) {
        $new_path = $upload_directory . $new_uploaded_image;

        if (is_file($new_path)) {
            @unlink($new_path);
        }
    }

    /* -------------------- REFRESH AFTER SUCCESS -------------------- */
    if ($message_type === "success") {

        $restaurant = getVendorRestaurant($conn, $vendor_id);

        $form_restaurant_name = $restaurant["restaurant_name"] ?? "";
        $form_phone = $restaurant["phone"] ?? "";
        $form_location = $restaurant["location"] ?? "";
        $form_category = $restaurant["category"] ?? "";
        $form_description = $restaurant["description"] ?? "";
        $form_latitude = $restaurant["latitude"] ?? "";
        $form_longitude = $restaurant["longitude"] ?? "";

        /*
         * Delete the old image only after a successful update.
         * Delete only files created by this restaurant upload system.
         */
        if (
            $new_uploaded_image !== "" &&
            $old_image !== "" &&
            $old_image !== $new_uploaded_image &&
            basename($old_image) === $old_image &&
            strpos($old_image, "restaurant_" . $vendor_id . "_") === 0
        ) {
            $old_path = $upload_directory . $old_image;

            if (is_file($old_path)) {
                @unlink($old_path);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LDD | Manage Restaurant</title>

    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }

        body {
            background: #f6f8f8;
            color: #1f2937;
        }

        header {
            background: #0f766e;
            color: white;
            padding: 16px 6%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
        }

        .logo span {
            color: #facc15;
        }

        .back-btn {
            color: white;
            text-decoration: none;
            font-weight: bold;
            font-size: 14px;
        }

        .container {
            max-width: 850px;
            margin: 28px auto;
            padding: 0 18px;
        }

        .title {
            margin-bottom: 18px;
        }

        .title h1 {
            color: #134e4a;
            font-size: 27px;
            margin-bottom: 6px;
        }

        .title p {
            color: #64748b;
            font-size: 14px;
        }

        .form-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 14px rgba(0,0,0,.06);
        }

        .row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 17px;
        }

        label {
            display: block;
            margin-bottom: 7px;
            font-size: 14px;
            font-weight: bold;
            color: #134e4a;
        }

        input,
        textarea,
        select {
            width: 100%;
            padding: 11px;
            border: 1px solid #d1d5db;
            border-radius: 7px;
            font-size: 14px;
            background: white;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #0f766e;
            box-shadow: 0 0 0 2px rgba(15,118,110,.1);
        }

        textarea {
            min-height: 90px;
            resize: vertical;
        }

        .required {
            color: #dc2626;
        }

        .optional {
            font-size: 12px;
            color: #64748b;
            font-weight: normal;
        }

        .location-btn {
            border: none;
            border-radius: 7px;
            padding: 10px 14px;
            background: #14b8a6;
            color: white;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 10px;
        }

        .location-btn:hover {
            background: #0f766e;
        }

        .location-status {
            font-size: 13px;
            color: #64748b;
            margin-bottom: 17px;
        }

        .submit-btn {
            width: 100%;
            padding: 13px;
            background: #0f766e;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        .submit-btn:hover {
            background: #115e59;
        }

        .message {
            padding: 12px 14px;
            border-radius: 7px;
            margin-bottom: 16px;
            font-size: 14px;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .current-image {
            margin-top: 10px;
        }

        .current-image img {
            width: 120px;
            height: 90px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #ddd;
            margin-top: 7px;
        }

        footer {
            text-align: center;
            padding: 18px;
            margin-top: 35px;
            background: #134e4a;
            color: white;
            font-size: 13px;
        }

        @media (max-width: 600px) {
            header {
                padding: 15px 5%;
            }

            .logo {
                font-size: 21px;
            }

            .back-btn {
                font-size: 12px;
            }

            .row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .form-card {
                padding: 18px;
            }

            .title h1 {
                font-size: 23px;
            }
        }
    </style>
</head>

<body>

<header>
    <div class="logo">L<span>D</span>D</div>

    <a href="vendor_dashboard.php" class="back-btn">
        ← Back to Dashboard
    </a>
</header>

<main class="container">

    <div class="title">
        <h1>
            <?php echo $restaurant ? "Manage Restaurant" : "Create Your Restaurant"; ?>
        </h1>

        <p>Add or update your restaurant information for LDD.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?php echo htmlspecialchars($message_type); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <div class="form-card">

        <form method="POST" enctype="multipart/form-data">

            <div class="form-group">
                <label>
                    Restaurant Name <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="restaurant_name"
                    maxlength="150"
                    placeholder="Enter restaurant name"
                    value="<?php echo htmlspecialchars($form_restaurant_name, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="row">

                <div class="form-group">
                    <label>
                        Phone Number <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        name="phone"
                        maxlength="20"
                        placeholder="Enter phone number"
                        value="<?php echo htmlspecialchars($form_phone, ENT_QUOTES, 'UTF-8'); ?>"
                        required
                    >
                </div>

                <div class="form-group">
                    <label>
                        Category <span class="required">*</span>
                    </label>

                    <select name="category" required>
                        <option value="">Select Category</option>

                        <?php foreach ($allowed_categories as $cat): ?>
                            <option
                                value="<?php echo htmlspecialchars($cat); ?>"
                                <?php echo ($form_category === $cat) ? "selected" : ""; ?>
                            >
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

            </div>

            <div class="form-group">
                <label>
                    Restaurant Location <span class="required">*</span>
                </label>

                <input
                    type="text"
                    name="location"
                    id="location"
                    maxlength="255"
                    placeholder="Example: Muvattupuzha, Ernakulam, Kerala"
                    value="<?php echo htmlspecialchars($form_location, ENT_QUOTES, 'UTF-8'); ?>"
                    required
                >
            </div>

            <div class="form-group">
                <label>Description <span class="optional">(Optional)</span></label>

                <textarea
                    name="description"
                    maxlength="1000"
                    placeholder="Describe your restaurant..."
                ><?php echo htmlspecialchars($form_description, ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>

            <button
                type="button"
                class="location-btn"
                onclick="getLocation()"
            >
                📍 Use My Location
            </button>

            <p id="location-status" class="location-status">
                Use this button to fill in your coordinates.
            </p>

            <div class="row">

                <div class="form-group">
                    <label>Latitude <span class="optional">(Optional)</span></label>

                    <input
                        type="text"
                        name="latitude"
                        id="latitude"
                        placeholder="Latitude"
                        value="<?php echo htmlspecialchars($form_latitude, ENT_QUOTES, 'UTF-8'); ?>"
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label>Longitude <span class="optional">(Optional)</span></label>

                    <input
                        type="text"
                        name="longitude"
                        id="longitude"
                        placeholder="Longitude"
                        value="<?php echo htmlspecialchars($form_longitude, ENT_QUOTES, 'UTF-8'); ?>"
                        readonly
                    >
                </div>

            </div>

            <div class="form-group">
                <label>
                    Restaurant Image
                    <span class="optional">(Optional, maximum 5 MB)</span>
                </label>

                <input
                    type="file"
                    name="image"
                    accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                >

                <?php if (!empty($restaurant["image"])): ?>
                    <div class="current-image">
                        <p>Current Image:</p>

                        <img
                            src="../backend/uploads/<?php echo rawurlencode(basename($restaurant["image"])); ?>"
                            alt="Restaurant Image"
                        >
                    </div>
                <?php endif; ?>
            </div>

            <button type="submit" class="submit-btn">
                <?php echo $restaurant ? "Update Restaurant" : "Create Restaurant"; ?>
            </button>

        </form>

    </div>

</main>

<footer>
    © 2026 Local Discover and Deliver | Vendor Panel
</footer>

<script>
function getLocation() {
    const status = document.getElementById("location-status");
    const latitudeInput = document.getElementById("latitude");
    const longitudeInput = document.getElementById("longitude");

    if (!navigator.geolocation) {
        status.textContent = "Your browser does not support location detection.";
        return;
    }

    status.textContent = "Getting your current location...";

    navigator.geolocation.getCurrentPosition(
        function(position) {
            latitudeInput.value = position.coords.latitude.toFixed(6);
            longitudeInput.value = position.coords.longitude.toFixed(6);

            status.textContent =
                "Coordinates detected. Please check the location before saving.";
        },
        function(error) {
            if (error.code === error.PERMISSION_DENIED) {
                status.textContent =
                    "Location permission denied. Allow location access in your browser.";
            } else if (error.code === error.TIMEOUT) {
                status.textContent =
                    "Location request timed out. Please try again.";
            } else {
                status.textContent =
                    "Unable to detect location. You can enter the address manually.";
            }
        },
        {
            enableHighAccuracy: true,
            timeout: 15000,
            maximumAge: 0
        }
    );
}
</script>

</body>
</html>

<?php
mysqli_close($conn);
?>