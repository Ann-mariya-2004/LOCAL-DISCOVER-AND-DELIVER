<?php
session_start();
require_once "../backend/db.php";

// Allow access only to logged-in users.
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

// Allow only Vendor accounts to access this page.
if (
    !isset($_SESSION["role"]) ||
    strcasecmp($_SESSION["role"], "Vendor") !== 0
) {
    header("Location: ../dashboard.php");
    exit();
}

// Store the logged-in user's ID as an integer.
$userId = (int) $_SESSION["user_id"];

// Initialize feedback messages.
$message = "";
$error = "";

// Create a CSRF token if one does not already exist.
if (empty($_SESSION["profile_csrf"])) {
    $_SESSION["profile_csrf"] = bin2hex(random_bytes(32));
}

// Escape dynamic values before displaying them in HTML.
function escapeHtml($value)
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}

// Define the business types vendors are allowed to select.
$allowedBusinessTypes = [
    "Restaurant",
    "Grocery",
    "Printing"
];

// Fetch the vendor's existing profile information.
$stmt = $conn->prepare(
    "SELECT id, name, email, profile_image, business_type
     FROM users
     WHERE id = ? AND role = 'Vendor'
     LIMIT 1"
);

// Stop safely if the profile query cannot be prepared.
if (!$stmt) {
    die("Unable to load profile. Please check the database.");
}

// Bind the logged-in user's ID to the query.
$stmt->bind_param("i", $userId);

// Execute the profile query.
$stmt->execute();

// Retrieve the query result.
$result = $stmt->get_result();

// Store the current profile information.
$vendor = $result->fetch_assoc();

// Close the database statement.
$stmt->close();

// Redirect if the vendor account no longer exists.
if (!$vendor) {
    session_destroy();
    header("Location: ../login_registration.html");
    exit();
}

// Initialize form values using the existing database values.
$formName = $vendor["name"] ?? "";
$formEmail = $vendor["email"] ?? "";
$formBusinessType = $vendor["business_type"] ?? "";

// Process profile updates when the form is submitted.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Read the CSRF token submitted with the form.
    $submittedToken = $_POST["csrf_token"] ?? "";

    // Reject requests that do not contain the correct CSRF token.
    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["profile_csrf"], $submittedToken)
    ) {
        $error = "Invalid request. Please refresh and try again.";

    } else {

        // Read and trim the submitted profile fields.
        $name = trim($_POST["name"] ?? "");
        $email = trim($_POST["email"] ?? "");
        $businessType = trim($_POST["business_type"] ?? "");

        // Preserve submitted values if validation fails.
        $formName = $name;
        $formEmail = $email;
        $formBusinessType = $businessType;

        // Validate the vendor's name.
        if ($name === "" || mb_strlen($name) > 100) {

            $error = "Please enter a valid name (maximum 100 characters).";

        // Validate the email address and its maximum length.
        } elseif (
            !filter_var($email, FILTER_VALIDATE_EMAIL) ||
            mb_strlen($email) > 150
        ) {

            $error = "Please enter a valid email address.";

        // Accept only the supported business types.
        } elseif (
            !in_array($businessType, $allowedBusinessTypes, true)
        ) {

            $error = "Please select a valid business type.";

        } else {

            // Check whether another user already uses this email.
            $emailStmt = $conn->prepare(
                "SELECT id
                 FROM users
                 WHERE email = ? AND id != ?
                 LIMIT 1"
            );

            // Report an error if the email query cannot be prepared.
            if (!$emailStmt) {

                $error = "Unable to validate the email address.";

            } else {

                // Bind the email and current user ID to the query.
                $emailStmt->bind_param("si", $email, $userId);

                // Execute the duplicate-email check.
                $emailStmt->execute();

                // Retrieve the matching email record, if any.
                $emailResult = $emailStmt->get_result();

                // Reject email addresses used by another account.
                if ($emailResult->num_rows > 0) {
                    $error = "This email address is already registered.";
                }

                // Close the email validation statement.
                $emailStmt->close();
            }
        }

        // Keep the current profile image unless a new image is uploaded.
        $profileImage = $vendor["profile_image"] ?? "";

        // Track the new image path for database updates and cleanup.
        $uploadedFilePath = "";
        $newImagePath = "";

        // Process the optional image upload only if validation succeeded.
        if (
            $error === "" &&
            isset($_FILES["profile_image"]) &&
            $_FILES["profile_image"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {
            // Store the uploaded file information.
            $file = $_FILES["profile_image"];

            // Reject failed uploads.
            if ($file["error"] !== UPLOAD_ERR_OK) {

                $error = "The image upload failed. Please try again.";

            // Restrict uploaded images to a maximum of 5 MB.
            } elseif ($file["size"] > 5 * 1024 * 1024) {

                $error = "Profile image must be 5 MB or smaller.";

            // Confirm that PHP received a genuine uploaded file.
            } elseif (!is_uploaded_file($file["tmp_name"])) {

                $error = "Invalid uploaded file.";

            } else {

                // Detect the actual image type rather than trusting its filename.
                $imageInfo = getimagesize($file["tmp_name"]);

                // Read the detected MIME type when available.
                $imageMime = $imageInfo["mime"] ?? "";

                // Map permitted image MIME types to safe file extensions.
                $allowedMimeTypes = [
                    "image/jpeg" => "jpg",
                    "image/png"  => "png",
                    "image/webp" => "webp"
                ];

                // Reject files that are not supported image formats.
                if (
                    $imageInfo === false ||
                    !isset($allowedMimeTypes[$imageMime])
                ) {

                    $error = "Upload a JPG, PNG, or WEBP image.";

                } else {

                    // Set the directory used to store vendor profile images.
                    $uploadDirectory = "../backend/uploads/";

                    // Create the upload directory if it does not exist.
                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true)
                    ) {

                        $error = "Unable to create the image upload folder.";

                    // Confirm that the server can write to the directory.
                    } elseif (!is_writable($uploadDirectory)) {

                        $error = "The image upload folder is not writable.";

                    } else {

                        // Choose the safe extension associated with the detected image type.
                        $extension = $allowedMimeTypes[$imageMime];

                        // Generate a unique filename to avoid collisions.
                        $fileName = "vendor_" . $userId . "_" .
                            bin2hex(random_bytes(8)) . "." . $extension;

                        // Build the destination path on the server.
                        $destination = $uploadDirectory . $fileName;

                        // Move the uploaded image into the upload directory.
                        if (
                            move_uploaded_file(
                                $file["tmp_name"],
                                $destination
                            )
                        ) {

                            // Store the relative image path in the database.
                            $newImagePath = "backend/uploads/" . $fileName;

                            // Use the new image path for the profile update.
                            $profileImage = $newImagePath;

                            // Remember the physical path for possible cleanup.
                            $uploadedFilePath = $destination;

                        } else {

                            // Report an error if the image cannot be saved.
                            $error = "Unable to save the uploaded image.";
                        }
                    }
                }
            }
        }

        // Update the database only when all validations succeed.
        if ($error === "") {

            // Update personal details, profile image, and business type together.
            $update = $conn->prepare(
                "UPDATE users
                 SET name = ?,
                     email = ?,
                     profile_image = ?,
                     business_type = ?
                 WHERE id = ? AND role = 'Vendor'"
            );

            // Handle a failed database statement preparation.
            if (!$update) {

                $error = "Unable to prepare the profile update.";

            } else {

                // Bind all four profile values and the vendor's ID.
                $update->bind_param(
                    "ssssi",
                    $name,
                    $email,
                    $profileImage,
                    $businessType,
                    $userId
                );

                // Execute the profile update.
                if ($update->execute()) {

                    // Update the in-memory profile with the saved values.
                    $vendor["name"] = $name;
                    $vendor["email"] = $email;
                    $vendor["profile_image"] = $profileImage;
                    $vendor["business_type"] = $businessType;

                    // Update session values used by other pages.
                    $_SESSION["name"] = $name;
                    $_SESSION["email"] = $email;
                    $_SESSION["business_type"] = $businessType;

                    // Display a success message.
                    $message = "Your profile has been updated successfully.";

                } else {

                    // Report a database update failure.
                    $error = "Unable to update your profile.";
                }

                // Close the update statement.
                $update->close();
            }

            // Remove the newly uploaded image if saving the database record failed.
            if ($error !== "" && $uploadedFilePath !== "") {

                if (is_file($uploadedFilePath)) {
                    unlink($uploadedFilePath);
                }
            }

        } elseif ($uploadedFilePath !== "") {

            // Remove a new image if another validation error occurred.
            if (is_file($uploadedFilePath)) {
                unlink($uploadedFilePath);
            }
        }
    }
}

// Prepare the image URL for display in the browser.
$profileImage = !empty($vendor["profile_image"])
    ? "../" . ltrim($vendor["profile_image"], "/")
    : "../assets/default-profile.png";

// Set a fallback name if the vendor name is empty.
$vendorName = $vendor["name"] ?: "Vendor";

// Read the currently selected business type.
$businessType = $vendor["business_type"] ?? "";

// Show a fallback label when no business type has been saved yet.
$businessLabel = $businessType !== ""
    ? $businessType
    : "Business Type Not Set";
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Define the page character encoding. -->
    <meta charset="UTF-8">

    <!-- Make the page responsive on mobile devices. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Set the browser tab title. -->
    <title>Edit Profile | Local Discover and Deliver</title>

    <style>
        /* Define the colors used throughout the page. */
        :root {
            --primary: #0f766e;
            --primary-light: #14b8a6;
            --background: #f4f7f8;
            --white: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --danger: #dc2626;
            --shadow: 0 5px 18px rgba(15, 23, 42, 0.05);
        }

        /* Apply consistent sizing and remove default spacing. */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        /* Set the default page font and background. */
        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        /* Remove underlines from navigation links. */
        a {
            text-decoration: none;
        }

        /* Create the fixed desktop sidebar. */
        .sidebar {
            position: fixed;
            inset: 0 auto 0 0;
            width: 210px;
            padding: 20px 14px;
            background: var(--white);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 10;
        }

        /* Align the logo and brand name. */
        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 4px 7px 24px;
        }

        /* Style the brand icon. */
        .brand-icon {
            width: 39px;
            height: 39px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: var(--primary);
            color: white;
            font-weight: 700;
            font-size: 20px;
        }

        /* Style the brand title. */
        .brand h2 {
            font-size: 14px;
            line-height: 1.4;
        }

        /* Highlight the second part of the brand name. */
        .brand span {
            color: var(--primary);
            font-size: 13px;
        }

        /* Style the sidebar section label. */
        .menu-label {
            padding: 10px;
            color: #94a3b8;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1px;
        }

        /* Arrange sidebar links vertically. */
        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        /* Style each sidebar navigation link. */
        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px;
            border-radius: 8px;
            color: #475569;
            font-size: 13px;
            transition: background 0.2s, color 0.2s;
        }

        /* Highlight the current page and hovered links. */
        .nav-menu a:hover,
        .nav-menu a.active {
            background: #e6f7f4;
            color: var(--primary);
            font-weight: 700;
        }

        /* Keep navigation icons aligned. */
        .nav-icon {
            width: 20px;
            text-align: center;
            font-size: 16px;
        }

        /* Position the logout link at the bottom of the sidebar. */
        .sidebar-bottom {
            margin-top: auto;
            border-top: 1px solid var(--border);
            padding-top: 12px;
        }

        /* Style the logout link. */
        .sidebar-bottom a {
            display: block;
            padding: 11px;
            color: var(--danger);
            border-radius: 8px;
            font-size: 13px;
        }

        /* Add a background when the logout link is hovered. */
        .sidebar-bottom a:hover {
            background: #fef2f2;
        }

        /* Position the main content beside the sidebar. */
        .main {
            margin-left: 210px;
            min-height: 100vh;
        }

        /* Create the top navigation bar. */
        .topbar {
            min-height: 70px;
            padding: 12px 26px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        /* Style the small topbar label. */
        .topbar-label {
            margin-bottom: 4px;
            color: var(--muted);
            font-size: 10px;
            letter-spacing: 0.7px;
        }

        /* Style the topbar heading. */
        .topbar h3 {
            font-size: 16px;
        }

        /* Align the vendor image and account details. */
        .user-info {
            display: flex;
            align-items: center;
            gap: 9px;
        }

        /* Style the small profile image in the topbar. */
        .user-info img {
            width: 39px;
            height: 39px;
            object-fit: cover;
            border: 2px solid #d1fae5;
            border-radius: 50%;
        }

        /* Style the vendor name in the topbar. */
        .user-name {
            font-size: 12px;
            font-weight: 700;
        }

        /* Style the vendor role label. */
        .user-role {
            margin-top: 3px;
            color: var(--muted);
            font-size: 11px;
        }

        /* Limit content width and reduce unnecessary whitespace. */
        .content {
            max-width: 1100px;
            margin: 0 auto;
            padding: 24px 26px;
        }

        /* Align the page heading and dashboard button. */
        .page-heading {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 12px;
            margin-bottom: 19px;
        }

        /* Style the main page heading. */
        .page-heading h1 {
            margin-bottom: 6px;
            font-size: 23px;
        }

        /* Style the description below the heading. */
        .page-heading p {
            color: var(--muted);
            font-size: 13px;
            line-height: 1.5;
        }

        /* Style the back-to-dashboard button. */
        .back-link {
            display: inline-block;
            padding: 10px 13px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: white;
            color: var(--text);
            font-size: 12px;
            font-weight: 700;
        }

        /* Highlight the dashboard button on hover. */
        .back-link:hover {
            border-color: var(--primary-light);
            color: var(--primary);
        }

        /* Style success and error notifications. */
        .alert {
            padding: 12px 14px;
            margin-bottom: 16px;
            border-radius: 8px;
            font-size: 13px;
            line-height: 1.5;
        }

        /* Use green for successful updates. */
        .alert.success {
            background: #dcfce7;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        /* Use red for validation and update errors. */
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        /* Place the profile preview and edit form side by side. */
        .profile-layout {
            display: grid;
            grid-template-columns: 270px minmax(0, 1fr);
            gap: 18px;
            align-items: start;
        }

        /* Give both cards a consistent appearance. */
        .profile-card,
        .form-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: var(--shadow);
        }

        /* Center the profile preview information. */
        .profile-card {
            padding: 24px 18px;
            text-align: center;
        }

        /* Style the large profile preview image. */
        .profile-picture {
            width: 108px;
            height: 108px;
            margin: 0 auto 14px;
            object-fit: cover;
            border: 4px solid #d1fae5;
            border-radius: 50%;
            background: #f0fdfa;
        }

        /* Style the name in the profile preview. */
        .profile-card h2 {
            margin-bottom: 6px;
            font-size: 17px;
            overflow-wrap: anywhere;
        }

        /* Style the email in the profile preview. */
        .profile-card .email {
            margin-bottom: 13px;
            color: var(--muted);
            font-size: 12px;
            overflow-wrap: anywhere;
        }

        /* Display the selected business type as a badge. */
        .business-badge {
            display: inline-block;
            padding: 7px 11px;
            border-radius: 20px;
            background: #e6f7f4;
            color: var(--primary);
            font-size: 11px;
            font-weight: 700;
        }

        /* Style the profile photo guidance section. */
        .profile-hint {
            margin-top: 19px;
            padding-top: 15px;
            border-top: 1px solid var(--border);
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
            text-align: left;
        }

        /* Add spacing inside the form card. */
        .form-card {
            padding: 23px;
        }

        /* Separate the form title from its fields. */
        .form-heading {
            margin-bottom: 19px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--border);
        }

        /* Style the form title. */
        .form-heading h2 {
            margin-bottom: 5px;
            font-size: 17px;
        }

        /* Style the form description. */
        .form-heading p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.5;
        }

        /* Add consistent spacing between form fields. */
        .form-group {
            margin-bottom: 17px;
        }

        /* Style each form label. */
        .form-group label {
            display: block;
            margin-bottom: 7px;
            font-size: 12px;
            font-weight: 700;
        }

        /* Style text inputs, email inputs, and the business type dropdown. */
        .form-group input,
        .form-group select {
            width: 100%;
            min-height: 42px;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            outline: none;
            background: white;
            color: var(--text);
            font: inherit;
            font-size: 13px;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        /* Highlight the field currently being edited. */
        .form-group input:focus,
        .form-group select:focus {
            border-color: var(--primary-light);
            box-shadow: 0 0 0 3px rgba(20, 184, 166, 0.12);
        }

        /* Give the file input a subtle background. */
        .form-group input[type="file"] {
            padding: 8px;
            background: #f8fafc;
        }

        /* Style explanatory text below form fields. */
        .field-help {
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.5;
        }

        /* Align the form buttons and allow wrapping on small screens. */
        .form-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 22px;
        }

        /* Apply common button styles. */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 10px 16px;
            border: 0;
            border-radius: 8px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 700;
            transition: background 0.2s;
        }

        /* Style the primary Save Changes button. */
        .btn-primary {
            background: var(--primary);
            color: white;
        }

        /* Darken the primary button on hover. */
        .btn-primary:hover {
            background: #115e59;
        }

        /* Style the secondary Cancel button. */
        .btn-secondary {
            border: 1px solid var(--border);
            background: white;
            color: var(--text);
        }

        /* Add a subtle hover background to the secondary button. */
        .btn-secondary:hover {
            background: #f8fafc;
        }

        /* Style the copyright footer. */
        .footer {
            padding: 24px 0 6px;
            color: var(--muted);
            font-size: 11px;
            text-align: center;
        }

        /* Adjust the layout for medium-sized screens. */
        @media (max-width: 850px) {
            .profile-layout {
                grid-template-columns: 220px minmax(0, 1fr);
                gap: 13px;
            }

            .content {
                padding: 21px 17px;
            }

            .form-card {
                padding: 18px;
            }
        }

        /* Stack navigation and profile cards on mobile screens. */
        @media (max-width: 650px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 12px;
                border-right: 0;
                border-bottom: 1px solid var(--border);
            }

            .brand {
                padding: 3px 7px 12px;
            }

            .menu-label,
            .sidebar-bottom {
                display: none;
            }

            .nav-menu {
                flex-direction: row;
                flex-wrap: wrap;
            }

            .nav-menu a {
                padding: 9px;
                font-size: 11px;
            }

            .nav-icon {
                display: none;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                padding: 12px 15px;
            }

            .content {
                padding: 19px 13px;
            }

            .page-heading h1 {
                font-size: 21px;
            }

            .profile-layout {
                grid-template-columns: 1fr;
            }

            .profile-card {
                padding: 20px;
            }

            .profile-hint {
                text-align: center;
            }
        }
    </style>

</head>

<body>

<!-- Display the sidebar navigation. -->
<aside class="sidebar">

    <!-- Display the project brand. -->
    <div class="brand">
        <div class="brand-icon">L</div>

        <div>
            <h2>Local Discover</h2>
            <span>& Deliver</span>
        </div>
    </div>

    <!-- Label the main navigation section. -->
    <div class="menu-label">MAIN MENU</div>

    <!-- Display navigation links available to the vendor. -->
    <nav class="nav-menu">

        <!-- Open the vendor dashboard. -->
        <a href="vendor_dashboard.php">
            <span class="nav-icon">▦</span>
            Dashboard
        </a>

        <!-- Show restaurant-specific links only for restaurant vendors. -->
        <?php if ($businessType === "Restaurant"): ?>

            <!-- Open restaurant management. -->
            <a href="manage_restaurant.php">
                <span class="nav-icon">🏪</span>
                Restaurant
            </a>

            <!-- Open food menu management. -->
            <a href="manage_food.php">
                <span class="nav-icon">🍽️</span>
                Food Menu
            </a>

            <!-- Open food order management. -->
            <a href="food_order.php">
                <span class="nav-icon">📦</span>
                Food Orders
            </a>

        <?php endif; ?>

        <!-- Mark the current page as active. -->
        <a href="edit_profile.php" class="active">
            <span class="nav-icon">👤</span>
            Edit Profile
        </a>

    </nav>

    <!-- Display the logout link at the bottom of the sidebar. -->
    <div class="sidebar-bottom">
        <a href="logout.php">↪ &nbsp; Logout</a>
    </div>

</aside>

<!-- Display the main page content. -->
<main class="main">

    <!-- Display the topbar and logged-in vendor details. -->
    <header class="topbar">

        <div>
            <div class="topbar-label">LOCAL DISCOVER & DELIVER</div>
            <h3>Vendor Portal</h3>
        </div>

        <!-- Show the vendor's profile image and name. -->
        <div class="user-info">

            <img
                src="<?php echo escapeHtml($profileImage); ?>"
                alt="Vendor profile image"
                onerror="this.onerror=null;this.src='../assets/default-profile.png';"
            >

            <div>
                <div class="user-name">
                    <?php echo escapeHtml($vendorName); ?>
                </div>

                <div class="user-role">Business Vendor</div>
            </div>

        </div>

    </header>

    <!-- Start the main content section. -->
    <section class="content">

        <!-- Display the page heading and dashboard navigation. -->
        <div class="page-heading">

            <div>
                <h1>Edit Profile</h1>
                <p>Update your account details, profile photo, and business type.</p>
            </div>

            <!-- Return to the vendor dashboard. -->
            <a href="vendor_dashboard.php" class="back-link">
                ← Back to Dashboard
            </a>

        </div>

        <!-- Display the success message after a successful update. -->
        <?php if ($message !== ""): ?>
            <div class="alert success">
                <?php echo escapeHtml($message); ?>
            </div>
        <?php endif; ?>

        <!-- Display validation or database errors. -->
        <?php if ($error !== ""): ?>
            <div class="alert error">
                <?php echo escapeHtml($error); ?>
            </div>
        <?php endif; ?>

        <!-- Arrange the profile preview and edit form side by side. -->
        <div class="profile-layout">

            <!-- Display the vendor's current profile information. -->
            <aside class="profile-card">

                <!-- Show the current profile image. -->
                <img
                    class="profile-picture"
                    src="<?php echo escapeHtml($profileImage); ?>"
                    alt="Current profile photo"
                    onerror="this.onerror=null;this.src='../assets/default-profile.png';"
                >

                <!-- Show the vendor's current name. -->
                <h2><?php echo escapeHtml($vendorName); ?></h2>

                <!-- Show the vendor's current email address. -->
                <div class="email">
                    <?php echo escapeHtml($vendor["email"]); ?>
                </div>

                <!-- Show the currently saved business type. -->
                <span class="business-badge">
                    <?php echo escapeHtml($businessLabel); ?>
                </span>

                <!-- Explain the profile photo upload requirements. -->
                <div class="profile-hint">
                    <strong>Profile tip</strong><br>
                    Use a clear JPG, PNG, or WEBP image.
                    The maximum allowed image size is 5 MB.
                </div>

            </aside>

            <!-- Display the form used to update the vendor's details. -->
            <section class="form-card">

                <!-- Introduce the editable profile fields. -->
                <div class="form-heading">
                    <h2>Personal & Business Information</h2>
                    <p>Change the details associated with your vendor account.</p>
                </div>

                <!-- Submit profile updates to this same PHP page. -->
                <form
                    method="POST"
                    action=""
                    enctype="multipart/form-data"
                >

                    <!-- Include the CSRF token to protect the form submission. -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escapeHtml($_SESSION["profile_csrf"]); ?>"
                    >

                    <!-- Allow the vendor to update their name. -->
                    <div class="form-group">

                        <label for="name">Full Name</label>

                        <input
                            type="text"
                            id="name"
                            name="name"
                            maxlength="100"
                            value="<?php echo escapeHtml($formName); ?>"
                            autocomplete="name"
                            required
                        >

                    </div>

                    <!-- Allow the vendor to update their email address. -->
                    <div class="form-group">

                        <label for="email">Email Address</label>

                        <input
                            type="email"
                            id="email"
                            name="email"
                            maxlength="150"
                            value="<?php echo escapeHtml($formEmail); ?>"
                            autocomplete="email"
                            required
                        >

                        <div class="field-help">
                            This email address must not already belong to another account.
                        </div>

                    </div>

                    <!-- Allow the vendor to change their business type. -->
                    <div class="form-group">

                        <label for="business_type">Business Type</label>

                        <select
                            id="business_type"
                            name="business_type"
                            required
                        >

                            <!-- Select Restaurant when it is the current business type. -->
                            <option
                                value="Restaurant"
                                <?php echo $formBusinessType === "Restaurant" ? "selected" : ""; ?>
                            >
                                Restaurant
                            </option>

                            <!-- Select Grocery when it is the current business type. -->
                            <option
                                value="Grocery"
                                <?php echo $formBusinessType === "Grocery" ? "selected" : ""; ?>
                            >
                                Grocery
                            </option>

                            <!-- Select Printing when it is the current business type. -->
                            <option
                                value="Printing"
                                <?php echo $formBusinessType === "Printing" ? "selected" : ""; ?>
                            >
                                Printing
                            </option>

                        </select>

                        <!-- Explain the effect of changing the business type. -->
                        <div class="field-help">
                            Choose the business category that matches your service.
                            Your selection will be saved to your vendor account.
                        </div>

                    </div>

                    <!-- Allow the vendor to upload an optional profile photo. -->
                    <div class="form-group">

                        <label for="profile_image">Profile Photo</label>

                        <input
                            type="file"
                            id="profile_image"
                            name="profile_image"
                            accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                        >

                        <div class="field-help">
                            Optional. Accepted formats: JPG, PNG and WEBP.
                            Maximum size: 5 MB.
                        </div>

                    </div>

                    <!-- Display the form action buttons. -->
                    <div class="form-actions">

                        <!-- Submit all profile changes. -->
                        <button type="submit" class="btn btn-primary">
                            Save Changes
                        </button>

                        <!-- Return to the dashboard without submitting the form. -->
                        <a
                            href="vendor_dashboard.php"
                            class="btn btn-secondary"
                        >
                            Cancel
                        </a>

                    </div>

                </form>

            </section>

        </div>

        <!-- Display the project copyright information. -->
        <div class="footer">
            © <?php echo date("Y"); ?>
            Local Discover and Deliver. All rights reserved.
        </div>

    </section>

</main>

</body>
</html>