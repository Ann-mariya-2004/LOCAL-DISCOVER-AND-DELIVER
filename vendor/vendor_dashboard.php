<?php
session_start();
require_once "../backend/db.php";

// Redirect users who are not logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

// Allow only users with the Vendor role.
if (
    !isset($_SESSION["role"]) ||
    strcasecmp($_SESSION["role"], "Vendor") !== 0
) {
    header("Location: ../dashboard.php");
    exit();
}

$userId = (int) $_SESSION["user_id"];
$message = "";
$error = "";

// Create a CSRF token to protect business-type updates.
if (empty($_SESSION["vendor_csrf"])) {
    $_SESSION["vendor_csrf"] = bin2hex(random_bytes(32));
}

// Fetch the current vendor's details.
$stmt = $conn->prepare(
    "SELECT id, name, email, business_type, profile_image
     FROM users
     WHERE id = ? AND role = 'Vendor'
     LIMIT 1"
);

if (!$stmt) {
    die("Unable to prepare the vendor query. Please check the database.");
}

$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$vendor = $result->fetch_assoc();

$stmt->close();

// Stop if the logged-in vendor account cannot be found.
if (!$vendor) {
    session_destroy();
    header("Location: ../login_registration.html");
    exit();
}

// Save the selected business type.
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Validate the security token before processing the form.
    $submittedToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["vendor_csrf"], $submittedToken)
    ) {
        $error = "Invalid request. Please refresh the page and try again.";
    } else {

        // Read and validate the selected business type.
        $businessType = $_POST["business_type"] ?? "";

        $allowedTypes = ["Restaurant", "Grocery", "Printing"];

        if (!in_array($businessType, $allowedTypes, true)) {
            $error = "Please select a valid business type.";
        } else {

            // Update the business type in the vendor's own account.
            $update = $conn->prepare(
                "UPDATE users
                 SET business_type = ?
                 WHERE id = ? AND role = 'Vendor'"
            );

            if (!$update) {
                $error = "Unable to prepare the update query.";
            } else {
                $update->bind_param("si", $businessType, $userId);

                if ($update->execute()) {
                    $vendor["business_type"] = $businessType;
                    $message = "Business type saved successfully.";
                } else {
                    $error = "Unable to save the business type.";
                }

                $update->close();
            }
        }
    }
}

// Escape text before displaying it in HTML.
function escapeHtml($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}

// Read the vendor's display name.
$vendorName = $vendor["name"] ?: "Vendor";

// Read the selected business type.
$businessType = $vendor["business_type"] ?? "";

// Use a default avatar when the vendor has no profile image.
$profileImage = !empty($vendor["profile_image"])
    ? "../" . ltrim($vendor["profile_image"], "/")
    : "../assets/default-profile.png";

// Prepare the restaurant-related information.
$restaurant = null;
$totalFoodItems = 0;
$totalFoodOrders = 0;

// Fetch restaurant information only for Restaurant vendors.
if ($businessType === "Restaurant") {

    // Find the restaurant belonging to this vendor.
    $restaurantStmt = $conn->prepare(
        "SELECT id, restaurant_name
         FROM restaurants
         WHERE vendor_id = ?
         LIMIT 1"
    );

    if ($restaurantStmt) {
        $restaurantStmt->bind_param("i", $userId);
        $restaurantStmt->execute();

        $restaurantResult = $restaurantStmt->get_result();
        $restaurant = $restaurantResult->fetch_assoc();

        $restaurantStmt->close();
    }

    // Count menu items belonging to this vendor's restaurant.
    if ($restaurant) {
        $restaurantId = (int) $restaurant["id"];

        $foodStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM food_items
             WHERE restaurant_id = ?"
        );

        if ($foodStmt) {
            $foodStmt->bind_param("i", $restaurantId);
            $foodStmt->execute();

            $foodResult = $foodStmt->get_result();
            $foodRow = $foodResult->fetch_assoc();

            $totalFoodItems = (int) ($foodRow["total"] ?? 0);

            $foodStmt->close();
        }

        // Count food orders associated with this restaurant.
        $orderStmt = $conn->prepare(
            "SELECT COUNT(*) AS total
             FROM food_orders
             WHERE restaurant_id = ?"
        );

        if ($orderStmt) {
            $orderStmt->bind_param("i", $restaurantId);
            $orderStmt->execute();

            $orderResult = $orderStmt->get_result();
            $orderRow = $orderResult->fetch_assoc();

            $totalFoodOrders = (int) ($orderRow["total"] ?? 0);

            $orderStmt->close();
        }
    }
}

// Choose a dashboard heading and description.
$businessHeading = "Business Dashboard";
$businessDescription = "Manage your business from one place.";

if ($businessType === "Restaurant") {
    $businessHeading = "Restaurant Dashboard";
    $businessDescription = "Manage your restaurant, food menu and orders.";
} elseif ($businessType === "Grocery") {
    $businessHeading = "Grocery Dashboard";
    $businessDescription = "Manage your grocery business.";
} elseif ($businessType === "Printing") {
    $businessHeading = "Printing Dashboard";
    $businessDescription = "Manage your printing business.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <!-- Make the dashboard responsive on mobile and desktop screens. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Vendor Dashboard | Local Discover and Deliver</title>

    <style>
        /* Define the shared colors used throughout the dashboard. */
        :root {
            --primary: #0f766e;
            --primary-light: #14b8a6;
            --background: #f4f7f8;
            --white: #ffffff;
            --text: #1e293b;
            --muted: #64748b;
            --border: #e2e8f0;
            --shadow: 0 8px 24px rgba(15, 23, 42, 0.05);
        }

        /* Apply consistent sizing and typography. */
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, Helvetica, sans-serif;
            background: var(--background);
            color: var(--text);
        }

        /* Keep the sidebar fixed on larger screens. */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            width: 220px;
            height: 100vh;
            padding: 18px 13px;
            background: var(--white);
            border-right: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            z-index: 10;
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 3px 7px 18px;
        }

        .brand-icon {
            width: 43px;
            height: 43px;
            display: grid;
            place-items: center;
            background: var(--primary);
            color: white;
            border-radius: 12px;
            font-size: 21px;
        }

        .brand h2 {
            font-size: 17px;
            line-height: 1.3;
        }

        .brand span {
            color: var(--primary);
        }

        .menu-label {
            color: #94a3b8;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 1px;
            padding: 8px 10px;
        }

        .nav-menu {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .nav-menu a {
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 9px 10px;
            border-radius: 9px;
            text-decoration: none;
            color: #475569;
            font-size: 14px;
            transition: 0.2s;
        }

        .nav-menu a:hover,
        .nav-menu a.active {
            background: #e6f7f4;
            color: var(--primary);
            font-weight: 600;
        }

        .nav-icon {
            width: 22px;
            text-align: center;
            font-size: 17px;
        }

        .sidebar-bottom {
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid var(--border);
        }

        .sidebar-bottom a {
            display: block;
            padding: 9px 10px;
            text-decoration: none;
            color: #dc2626;
            font-size: 14px;
            border-radius: 8px;
        }

        .sidebar-bottom a:hover {
            background: #fef2f2;
        }

        /* Offset the main content to make room for the sidebar. */
        .main {
            margin-left: 220px;
            min-height: 100vh;
        }

        /* Keep the top bar compact and easy to scan. */
        .topbar {
            height: 68px;
            padding: 0 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 16px;
            background: var(--white);
            border-bottom: 1px solid var(--border);
        }

        .topbar-label {
            color: var(--muted);
            font-size: 12px;
            margin-bottom: 5px;
        }

        .topbar h3 {
            font-size: 17px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .user-info img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #d1fae5;
        }

        .user-name {
            font-size: 13px;
            font-weight: 700;
        }

        .user-role {
            color: var(--muted);
            font-size: 12px;
            margin-top: 4px;
        }

        /* Keep the main content width readable. */
        .content {
            padding: 22px 24px;
            max-width: 1450px;
            margin: 0 auto;
        }

        .welcome {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 18px;
            margin-bottom: 18px;
        }

        .welcome h1 {
            font-size: 23px;
            margin-bottom: 8px;
        }

        .welcome p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.5;
        }

        .business-badge {
            display: inline-block;
            padding: 7px 11px;
            background: #d1fae5;
            color: #065f46;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
        }

        /* Display success and error messages clearly. */
        .alert {
            padding: 10px 12px;
            margin-bottom: 14px;
            border-radius: 9px;
            font-size: 13px;
        }

        .alert.success {
            background: #dcfce7;
            color: #166534;
        }

        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Style the initial business selection panel. */
        .setup-card {
            max-width: 760px;
            padding: 22px;
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 15px;
            box-shadow: var(--shadow);
        }

        .setup-card h2 {
            font-size: 20px;
            margin-bottom: 9px;
        }

        .setup-card > p {
            color: var(--muted);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .business-options {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 13px;
            margin-bottom: 10px;
        }

        .business-option input {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }

        .business-option label {
            display: block;
            padding: 19px 12px;
            text-align: center;
            border: 1px solid var(--border);
            border-radius: 12px;
            cursor: pointer;
            transition: 0.2s;
        }

        .business-option label:hover {
            border-color: var(--primary-light);
            background: #f0fdfa;
        }

        .business-option input:checked + label {
            border: 2px solid var(--primary);
            background: #e6f7f4;
        }

        .business-option .emoji {
            display: block;
            font-size: 29px;
            margin-bottom: 10px;
        }

        .business-option strong {
            display: block;
            font-size: 13px;
        }

        .business-option small {
            display: block;
            margin-top: 6px;
            color: var(--muted);
            font-size: 11px;
            line-height: 1.4;
        }

        .primary-btn {
            display: inline-block;
            border: 0;
            border-radius: 9px;
            background: var(--primary);
            color: white;
            padding: 12px 18px;
            font-size: 13px;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
            transition: 0.2s;
        }

        .primary-btn:hover {
            background: #115e59;
        }

        /* Arrange overview statistics in a responsive grid. */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }

        .stat-card {
            background: var(--white);
            padding: 16px;
            border: 1px solid var(--border);
            border-radius: 13px;
            box-shadow: var(--shadow);
        }

        .stat-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            margin-bottom: 12px;
        }

        .stat-label {
            color: var(--muted);
            font-size: 13px;
        }

        .stat-icon {
            width: 34px;
            height: 34px;
            display: grid;
            place-items: center;
            background: #e6f7f4;
            border-radius: 10px;
            font-size: 19px;
        }

        .stat-number {
            font-size: 27px;
            font-weight: 700;
        }

        .stat-note {
            margin-top: 7px;
            color: var(--muted);
            font-size: 12px;
        }

        /* Separate the business tools from the statistics. */
        .section-heading {
            margin-bottom: 10px;
        }

        .section-heading h2 {
            font-size: 18px;
            margin-bottom: 6px;
        }

        .section-heading p {
            font-size: 13px;
            color: var(--muted);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .feature-card {
            display: block;
            padding: 16px;
            border-radius: 13px;
            background: var(--white);
            border: 1px solid var(--border);
            box-shadow: var(--shadow);
            text-decoration: none;
            color: var(--text);
            transition: 0.2s;
        }

        a.feature-card:hover {
            transform: translateY(-3px);
            border-color: var(--primary-light);
            box-shadow: 0 12px 28px rgba(15, 118, 110, 0.09);
        }

        .feature-icon {
            width: 38px;
            height: 38px;
            display: grid;
            place-items: center;
            background: #e6f7f4;
            color: var(--primary);
            border-radius: 11px;
            font-size: 21px;
            margin-bottom: 10px;
        }

        .feature-card h3 {
            font-size: 15px;
            margin-bottom: 8px;
        }

        .feature-card p {
            color: var(--muted);
            font-size: 12px;
            line-height: 1.6;
        }

        .feature-link {
            display: inline-block;
            margin-top: 10px;
            color: var(--primary);
            font-size: 12px;
            font-weight: 700;
        }

        .feature-card.disabled {
            background: #f8fafc;
            box-shadow: none;
        }

        .status-label {
            display: inline-block;
            margin-top: 14px;
            padding: 5px 8px;
            background: #fef3c7;
            color: #92400e;
            border-radius: 5px;
            font-size: 10px;
            font-weight: 700;
        }

        .restaurant-notice {
            padding: 17px;
            margin-bottom: 23px;
            background: #ecfdf5;
            border: 1px solid #bbf7d0;
            border-radius: 11px;
            font-size: 13px;
            line-height: 1.6;
        }

        .restaurant-notice strong {
            color: #166534;
        }

        .footer {
            padding: 18px 0 4px;
            text-align: center;
            color: var(--muted);
            font-size: 11px;
        }

        /* Adapt the dashboard layout for tablets and smaller screens. */
        @media (max-width: 1050px) {
            .sidebar {
                width: 190px;
            }

            .main {
                margin-left: 190px;
            }

            .stats-grid,
            .features-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .content {
                padding: 20px 18px;
            }

            .topbar {
                padding: 0 18px;
            }
        }

        /* Use a compact top layout on mobile devices. */
        @media (max-width: 700px) {
            .sidebar {
                position: relative;
                width: 100%;
                height: auto;
                padding: 13px;
                border-right: none;
                border-bottom: 1px solid var(--border);
            }

            .brand {
                padding: 5px 8px 13px;
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
                font-size: 12px;
            }

            .nav-icon {
                display: none;
            }

            .main {
                margin-left: 0;
            }

            .topbar {
                height: auto;
                min-height: 70px;
                padding: 13px 17px;
            }

            .content {
                padding: 16px 12px;
            }

            .welcome {
                align-items: flex-start;
                flex-direction: column;
            }

            .welcome h1 {
                font-size: 22px;
            }

            .stats-grid,
            .features-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .business-options {
                grid-template-columns: 1fr;
            }

            .setup-card {
                padding: 20px;
            }

            .user-name {
                max-width: 120px;
                overflow-wrap: anywhere;
            }
        }

        @media (max-width: 420px) {
            .stats-grid,
            .features-grid {
                grid-template-columns: 1fr;
            }

            .topbar-label {
                font-size: 10px;
            }

            .user-info img {
                width: 36px;
                height: 36px;
            }
        }
    </style>
</head>

<body>

<!-- Display navigation links that are relevant to the selected business. -->
<aside class="sidebar">

    <div class="brand">
        <div class="brand-icon">L</div>

        <div>
            <h2>Local Discover</h2>
            <span>& Deliver</span>
        </div>
    </div>

    <div class="menu-label">MAIN MENU</div>

    <nav class="nav-menu">

        <a href="vendor_dashboard.php" class="active">
            <span class="nav-icon">▦</span>
            Dashboard
        </a>

        <?php if ($businessType === "Restaurant"): ?>

            <a href="manage_restaurant.php">
                <span class="nav-icon">🏪</span>
                Restaurant
            </a>

            <a href="manage_food.php">
                <span class="nav-icon">🍽️</span>
                Food Menu
            </a>

            <a href="food_order.php">
                <span class="nav-icon">📦</span>
                Food Orders
            </a>

        <?php elseif ($businessType === "Grocery"): ?>

            <!-- Grocery links will be added when vendor grocery modules are implemented. -->
            <a href="#business-tools">
                <span class="nav-icon">🛒</span>
                Grocery Overview
            </a>

        <?php elseif ($businessType === "Printing"): ?>

            <!-- Printing links will be added when vendor printing modules are implemented. -->
            <a href="#business-tools">
                <span class="nav-icon">🖨️</span>
                Printing Overview
            </a>

        <?php endif; ?>

        <a href="edit_profile.php">
            <span class="nav-icon">👤</span>
            Edit Profile
        </a>

    </nav>

    <div class="sidebar-bottom">
        <!-- Use the existing vendor logout endpoint. -->
        <a href="logout.php">↪ &nbsp; Logout</a>
    </div>

</aside>

<!-- Main dashboard content begins here. -->
<main class="main">

    <header class="topbar">

        <div>
            <div class="topbar-label">LOCAL DISCOVER & DELIVER</div>
            <h3>Vendor Portal</h3>
        </div>

        <div class="user-info">

            <!-- Display the saved profile image when available. -->
            <img
                src="<?php echo escapeHtml($profileImage); ?>"
                alt="Vendor profile"
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

    <section class="content">

        <!-- Show the appropriate heading for the selected business. -->
        <div class="welcome">

            <div>
                <h1>
                    Welcome, <?php echo escapeHtml($vendorName); ?>!
                </h1>

                <p>
                    <?php echo escapeHtml($businessDescription); ?>
                </p>
            </div>

            <?php if ($businessType !== ""): ?>
                <div class="business-badge">
                    <?php echo escapeHtml($businessType); ?> Account
                </div>
            <?php endif; ?>

        </div>

        <!-- Show validation and database messages. -->
        <?php if ($message !== ""): ?>
            <div class="alert success">
                <?php echo escapeHtml($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error !== ""): ?>
            <div class="alert error">
                <?php echo escapeHtml($error); ?>
            </div>
        <?php endif; ?>


        <?php if (
            !in_array(
                $businessType,
                ["Restaurant", "Grocery", "Printing"],
                true
            )
        ): ?>

            <!-- Ask new vendors to choose their business type. -->
            <div class="setup-card">

                <h2>Set Up Your Business</h2>

                <p>
                    Choose the type of business you manage.
                    Your dashboard will display features relevant
                    to your selection.
                </p>

                <form method="POST" action="">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?php echo escapeHtml($_SESSION["vendor_csrf"]); ?>"
                    >

                    <div class="business-options">

                        <div class="business-option">
                            <input
                                type="radio"
                                name="business_type"
                                id="restaurant"
                                value="Restaurant"
                                required
                            >

                            <label for="restaurant">
                                <span class="emoji">🍽️</span>
                                <strong>Restaurant</strong>
                                <small>Food menu and food orders</small>
                            </label>
                        </div>

                        <div class="business-option">
                            <input
                                type="radio"
                                name="business_type"
                                id="grocery"
                                value="Grocery"
                                required
                            >

                            <label for="grocery">
                                <span class="emoji">🛒</span>
                                <strong>Grocery Store</strong>
                                <small>Grocery products and orders</small>
                            </label>
                        </div>

                        <div class="business-option">
                            <input
                                type="radio"
                                name="business_type"
                                id="printing"
                                value="Printing"
                                required
                            >

                            <label for="printing">
                                <span class="emoji">🖨️</span>
                                <strong>Printing Shop</strong>
                                <small>Printing services and orders</small>
                            </label>
                        </div>

                    </div>

                    <button type="submit" class="primary-btn">
                        Save Business Type →
                    </button>

                </form>

            </div>


        <?php else: ?>

            <?php if ($businessType === "Restaurant"): ?>

                <!-- Display restaurant-specific statistics only. -->
                <div class="stats-grid">

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-label">Restaurant Status</div>
                            <div class="stat-icon">🏪</div>
                        </div>

                        <div class="stat-number">
                            <?php echo $restaurant ? "Active" : "Setup"; ?>
                        </div>

                        <div class="stat-note">
                            <?php
                            echo $restaurant
                                ? escapeHtml($restaurant["restaurant_name"])
                                : "Add your restaurant details";
                            ?>
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-label">Food Menu Items</div>
                            <div class="stat-icon">🍽️</div>
                        </div>

                        <div class="stat-number">
                            <?php echo $totalFoodItems; ?>
                        </div>

                        <div class="stat-note">
                            Items in your restaurant menu
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-top">
                            <div class="stat-label">Total Food Orders</div>
                            <div class="stat-icon">📦</div>
                        </div>

                        <div class="stat-number">
                            <?php echo $totalFoodOrders; ?>
                        </div>

                        <div class="stat-note">
                            Orders associated with your restaurant
                        </div>
                    </div>

                </div>

                <?php if (!$restaurant): ?>

                    <div class="restaurant-notice">
                        <strong>Complete your restaurant setup.</strong>
                        <br>
                        Add your restaurant details before managing
                        your food menu and orders.
                        <br><br>

                        <a
                            href="manage_restaurant.php"
                            class="primary-btn"
                        >
                            Set Up Restaurant
                        </a>
                    </div>

                <?php endif; ?>

                <!-- Display only restaurant-related management tools. -->
                <div class="section-heading" id="business-tools">
                    <h2>Restaurant Management</h2>
                    <p>Manage your restaurant operations from here.</p>
                </div>

                <div class="features-grid">

                    <a
                        href="manage_restaurant.php"
                        class="feature-card"
                    >
                        <div class="feature-icon">🏪</div>
                        <h3>Manage Restaurant</h3>
                        <p>
                            Update your restaurant name, location,
                            contact details and other information.
                        </p>
                        <span class="feature-link">
                            Open Restaurant →
                        </span>
                    </a>

                    <a
                        href="manage_food.php"
                        class="feature-card"
                    >
                        <div class="feature-icon">🍽️</div>
                        <h3>Manage Food Menu</h3>
                        <p>
                            Add new food items, update prices,
                            manage descriptions and remove items.
                        </p>
                        <span class="feature-link">
                            Manage Menu →
                        </span>
                    </a>

                    <a
                        href="food_order.php"
                        class="feature-card"
                    >
                        <div class="feature-icon">📦</div>
                        <h3>Food Orders</h3>
                        <p>
                            Open your food order management page
                            to review and manage customer orders.
                        </p>
                        <span class="feature-link">
                            View Orders →
                        </span>
                    </a>

                    <a
                        href="edit_profile.php"
                        class="feature-card"
                    >
                        <div class="feature-icon">👤</div>
                        <h3>Business Profile</h3>
                        <p>
                            Update your vendor profile and account
                            information.
                        </p>
                        <span class="feature-link">
                            Edit Profile →
                        </span>
                    </a>

                </div>


            <?php elseif ($businessType === "Grocery"): ?>

                <!-- Keep grocery vendors focused on grocery operations. -->
                <div class="section-heading" id="business-tools">
                    <h2>Grocery Management</h2>
                    <p>
                        Your dashboard is configured for grocery operations.
                    </p>
                </div>

                <div class="features-grid">

                    <div class="feature-card disabled">
                        <div class="feature-icon">🏪</div>
                        <h3>Manage Grocery Store</h3>
                        <p>
                            The vendor store-management module must be
                            implemented before this feature can be used.
                        </p>
                        <span class="status-label">MODULE PENDING</span>
                    </div>

                    <div class="feature-card disabled">
                        <div class="feature-icon">🛒</div>
                        <h3>Manage Grocery Items</h3>
                        <p>
                            A vendor-specific grocery inventory page
                            is required for adding and editing products.
                        </p>
                        <span class="status-label">MODULE PENDING</span>
                    </div>

                    <div class="feature-card disabled">
                        <div class="feature-icon">📦</div>
                        <h3>Grocery Orders</h3>
                        <p>
                            A vendor grocery-order management page
                            must be connected to the order database.
                        </p>
                        <span class="status-label">MODULE PENDING</span>
                    </div>

                </div>


            <?php elseif ($businessType === "Printing"): ?>

                <!-- Keep printing vendors focused on printing services. -->
                <div class="section-heading" id="business-tools">
                    <h2>Printing Shop Management</h2>
                    <p>
                        Your dashboard is configured for printing services.
                    </p>
                </div>

                <div class="features-grid">

                    <div class="feature-card disabled">
                        <div class="feature-icon">🖨️</div>
                        <h3>Manage Printing Services</h3>
                        <p>
                            A vendor-specific printing service management
                            page is required to add and update services.
                        </p>
                        <span class="status-label">MODULE PENDING</span>
                    </div>

                    <div class="feature-card disabled">
                        <div class="feature-icon">📄</div>
                        <h3>Printing Orders</h3>
                        <p>
                            A printing-order management page must be
                            connected before orders can be processed here.
                        </p>
                        <span class="status-label">MODULE PENDING</span>
                    </div>

                    <div class="feature-card disabled">
                        <div class="feature-icon">👤</div>
                        <h3>Business Profile</h3>
                        <p>
                            Update your vendor account information.
                        </p>
                        <a
                            href="edit_profile.php"
                            class="feature-link"
                        >
                            Edit Profile →
                        </a>
                    </div>

                </div>

            <?php endif; ?>

        <?php endif; ?>

        <div class="footer">
            © <?php echo date("Y"); ?>
            Local Discover and Deliver. All rights reserved.
        </div>

    </section>

</main>

</body>
</html>