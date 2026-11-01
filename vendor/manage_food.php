
<?php
// Start the session to access the logged-in user's information.
session_start();

// Include the database connection file.
require_once "../backend/db.php";

// Check whether the user is logged in as a Vendor.
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Vendor') {

    // Redirect unauthorized users to the login page.
    header("Location: ../login_registration.html");

    // Stop executing the script.
    exit;
}

// Store the logged-in vendor's user ID.
$vendor_id = (int) $_SESSION['user_id'];

// Initialize the message displayed to the user.
$message = "";

// Set the default message style.
$message_type = "success";

// Prepare a query to find the restaurant owned by this vendor.
$stmt = $conn->prepare(
    "SELECT id, restaurant_name
     FROM restaurants
     WHERE vendor_id = ?
     LIMIT 1"
);

// Bind the vendor ID to the SQL query.
$stmt->bind_param("i", $vendor_id);

// Execute the query.
$stmt->execute();

// Fetch the restaurant details.
$restaurant = $stmt->get_result()->fetch_assoc();

// Close the prepared statement.
$stmt->close();

// Check whether the vendor has registered a restaurant.
if (!$restaurant) {

    // Mark that the vendor does not have a restaurant.
    $no_restaurant = true;

} else {

    // Mark that the restaurant exists.
    $no_restaurant = false;

    // Store the restaurant ID for later queries.
    $restaurant_id = (int) $restaurant['id'];
}

// Generate a CSRF token if one does not already exist.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Process form submissions only when a restaurant exists.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$no_restaurant) {

    // Validate the CSRF token to protect against forged requests.
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $message = "Invalid request. Please refresh the page and try again.";
        $message_type = "error";

    } else {

        // Identify the requested action.
        $action = $_POST['action'] ?? '';

        // Handle food item deletion.
        if ($action === 'delete') {

            // Validate the food item ID received from the form.
            $food_id = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);

            // Continue only if the ID is valid.
            if ($food_id) {

                // Delete the item only if it belongs to this vendor's restaurant.
                $stmt = $conn->prepare(
                    "DELETE FROM food_items
                     WHERE id = ? AND restaurant_id = ?"
                );

                // Bind the food ID and restaurant ID.
                $stmt->bind_param("ii", $food_id, $restaurant_id);

                // Execute the deletion query.
                if ($stmt->execute() && $stmt->affected_rows > 0) {

                    // Display a success message.
                    $message = "Food item deleted successfully.";

                } else {

                    // Display an error if the item was not deleted.
                    $message = "Food item not found or could not be deleted.";
                    $message_type = "error";
                }

                // Close the prepared statement.
                $stmt->close();

            } else {

                // Display an error for an invalid food ID.
                $message = "Invalid food item.";
                $message_type = "error";
            }

        // Handle adding or updating a food item.
        } elseif ($action === 'save') {

            // Read the food ID; zero indicates a new food item.
            $food_id = (int) ($_POST['food_id'] ?? 0);

            // Read and trim the food name.
            $name = trim($_POST['name'] ?? '');

            // Read and trim the food description.
            $description = trim($_POST['description'] ?? '');

            // Validate the price as a numeric value.
            $price = filter_var($_POST['price'] ?? '', FILTER_VALIDATE_FLOAT);

            // Read and trim the selected category.
            $category = trim($_POST['category'] ?? '');

            // Define the categories allowed by this form.
            $allowed_categories = ['Veg', 'Non Veg', 'Other'];

            // Validate the submitted food details.
            if (
                $name === '' ||
                strlen($name) > 150 ||
                $price === false ||
                $price <= 0 ||
                !in_array($category, $allowed_categories, true)
            ) {
                $message = "Enter a valid name, price and category.";
                $message_type = "error";

            } else {

                // Initialize the uploaded image filename.
                $image_name = null;

                // Track whether an image upload fails.
                $upload_error = false;

                // Check whether the user selected an image.
                if (
                    isset($_FILES['image']) &&
                    $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE
                ) {

                    // Check whether PHP reported an upload error.
                    if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) {

                        $upload_error = true;
                        $message = "Image upload failed.";

                    // Reject images larger than 5 MB.
                    } elseif ($_FILES['image']['size'] > 5 * 1024 * 1024) {

                        $upload_error = true;
                        $message = "Image size must be 5 MB or less.";

                    } else {

                        // Get the temporary path of the uploaded file.
                        $tmp_path = $_FILES['image']['tmp_name'];

                        // Read the actual image information.
                        $image_info = @getimagesize($tmp_path);

                        // Determine the MIME type of the uploaded image.
                        $mime = $image_info['mime'] ?? '';

                        // Define the supported image formats and extensions.
                        $allowed_mimes = [
                            'image/jpeg' => 'jpg',
                            'image/png'  => 'png',
                            'image/webp' => 'webp'
                        ];

                        // Reject unsupported file types.
                        if (!isset($allowed_mimes[$mime])) {

                            $upload_error = true;
                            $message = "Upload a JPG, PNG or WEBP image.";

                        } else {

                            // Define the directory used to store food images.
                            $upload_dir = "../backend/uploads/";

                            // Create the upload directory if it does not exist.
                            if (!is_dir($upload_dir)) {

                                if (!mkdir($upload_dir, 0755, true)) {
                                    $upload_error = true;
                                    $message = "Could not create the upload folder.";
                                }
                            }

                            // Save the image only if validation succeeded.
                            if (!$upload_error) {

                                // Generate a unique filename for the image.
                                $image_name = 'food_' . bin2hex(random_bytes(12))
                                    . '.' . $allowed_mimes[$mime];

                                // Move the uploaded image into the upload directory.
                                if (!move_uploaded_file(
                                    $tmp_path,
                                    $upload_dir . $image_name
                                )) {
                                    $upload_error = true;
                                    $message = "Image upload failed.";
                                }
                            }
                        }
                    }
                }

                // Save the food details only if image validation succeeded.
                if (!$upload_error) {

                    // Update an existing food item when its ID is greater than zero.
                    if ($food_id > 0) {

                        // Update the image only when a new image was uploaded.
                        if ($image_name !== null) {

                            // Prepare an update query including the new image.
                            $stmt = $conn->prepare(
                                "UPDATE food_items
                                 SET name = ?, description = ?, price = ?,
                                     category = ?, image = ?
                                 WHERE id = ? AND restaurant_id = ?"
                            );

                            // Bind the food details and ownership identifiers.
                            $stmt->bind_param(
                                "ssdssii",
                                $name,
                                $description,
                                $price,
                                $category,
                                $image_name,
                                $food_id,
                                $restaurant_id
                            );

                        } else {

                            // Prepare an update query that keeps the existing image.
                            $stmt = $conn->prepare(
                                "UPDATE food_items
                                 SET name = ?, description = ?, price = ?,
                                     category = ?
                                 WHERE id = ? AND restaurant_id = ?"
                            );

                            // Bind the values using the correct parameter types.
                            // s = string, d = decimal/double, i = integer.
                            $stmt->bind_param(
                                "ssdsii",
                                $name,
                                $description,
                                $price,
                                $category,
                                $food_id,
                                $restaurant_id
                            );
                        }

                        // Execute the update query.
                        if ($stmt->execute()) {

                            // Check whether the query matched a food item.
                            if ($stmt->affected_rows >= 0) {
                                $message = "Food item updated successfully.";
                            }

                        } else {

                            // Display an error if the database update fails.
                            $message = "Could not update the food item.";
                            $message_type = "error";

                            // Delete the newly uploaded image if saving failed.
                            if ($image_name !== null) {
                                @unlink("../backend/uploads/" . $image_name);
                            }
                        }

                        // Close the prepared statement.
                        $stmt->close();

                    } else {

                        // Prepare a query to insert a new food item.
                        $stmt = $conn->prepare(
                            "INSERT INTO food_items
                             (name, description, price, image, category, restaurant_id)
                             VALUES (?, ?, ?, ?, ?, ?)"
                        );

                        // Use an empty string when no image was uploaded.
                        $image_to_save = $image_name ?? '';

                        // Bind the food details to the insert query.
                        $stmt->bind_param(
                            "ssdssi",
                            $name,
                            $description,
                            $price,
                            $image_to_save,
                            $category,
                            $restaurant_id
                        );

                        // Execute the insert query.
                        if ($stmt->execute()) {

                            // Display a success message.
                            $message = "Food item added successfully.";

                        } else {

                            // Display an error if insertion fails.
                            $message = "Could not add the food item.";
                            $message_type = "error";

                            // Remove the newly uploaded image if insertion fails.
                            if ($image_name !== null) {
                                @unlink("../backend/uploads/" . $image_name);
                            }
                        }

                        // Close the prepared statement.
                        $stmt->close();
                    }

                } elseif ($message === '') {

                    // Display a general error if image upload fails.
                    $message = "Image upload failed. Please try again.";
                    $message_type = "error";
                }
            }
        }
    }
}

// Initialize the variable used by the edit form.
$edit_food = null;

// Retrieve food details when an edit request is received.
if (!$no_restaurant && isset($_GET['edit'])) {

    // Validate the food ID supplied in the URL.
    $edit_id = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);

    // Continue only when the ID is valid.
    if ($edit_id) {

        // Retrieve the food item only if it belongs to this restaurant.
        $stmt = $conn->prepare(
            "SELECT id, name, description, price, image, category
             FROM food_items
             WHERE id = ? AND restaurant_id = ?"
        );

        // Bind the food ID and restaurant ID.
        $stmt->bind_param("ii", $edit_id, $restaurant_id);

        // Execute the query.
        $stmt->execute();

        // Fetch the selected food item's details.
        $edit_food = $stmt->get_result()->fetch_assoc();

        // Close the prepared statement.
        $stmt->close();
    }
}

// Initialize the array that stores food items.
$foods = [];

// Retrieve food items only when the vendor has a restaurant.
if (!$no_restaurant) {

    // Read the search term from the URL.
    $search = trim($_GET['search'] ?? '');

    // Add wildcard characters for partial name matching.
    $search_term = '%' . $search . '%';

    // Prepare a query to search this restaurant's food items.
    $stmt = $conn->prepare(
        "SELECT id, name, description, price, image, category
         FROM food_items
         WHERE restaurant_id = ? AND name LIKE ?
         ORDER BY id DESC"
    );

    // Bind the restaurant ID and search term.
    $stmt->bind_param("is", $restaurant_id, $search_term);

    // Execute the search query.
    $stmt->execute();

    // Get the query result.
    $result = $stmt->get_result();

    // Fetch each food item and add it to the array.
    while ($row = $result->fetch_assoc()) {
        $foods[] = $row;
    }

    // Close the prepared statement.
    $stmt->close();
}

// Escape output to prevent HTML injection.
function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <!-- Set the character encoding for the webpage. -->
    <meta charset="UTF-8">

    <!-- Make the page responsive on mobile devices. -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Set the browser tab title. -->
    <title>Manage Food | Local Discover & Deliver</title>

    <style>
        /* Include padding and borders within element dimensions. */
        * {
            box-sizing: border-box;
        }

        /* Set the default page appearance. */
        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f3f7f7;
            color: #253238;
        }

        /* Style the top navigation bar. */
        header {
            background: #0f766e;
            color: white;
            padding: 9px 3%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* Remove the default margin from the header title. */
        header h2 {
            margin: 0;
            font-size: 18px;
        }

        /* Style the navigation link. */
        header a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        /* Center the main content and limit its width. */
        .container {
            max-width: 1040px;
            margin: 12px auto;
            padding: 0 12px;
        }

        /* Align the page title and dashboard button. */
        .top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 12px;
        }

        /* Style the main page heading. */
        .top h1 {
            margin: 0;
            font-size: 20px;
        }

        /* Display the restaurant name below the heading. */
        .restaurant {
            color: #64748b;
            margin-top: 6px;
        }

        /* Define the common button style. */
        .btn {
            display: inline-block;
            border: 0;
            border-radius: 7px;
            padding: 8px 11px;
            background: #0f766e;
            color: white;
            text-decoration: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }

        /* Change the button color when the pointer hovers over it. */
        .btn:hover {
            background: #115e59;
        }

        /* Set a grey background for secondary buttons. */
        .btn.gray {
            background: #64748b;
        }

        /* Set a red background for delete buttons. */
        .btn.red {
            background: #dc2626;
        }

        /* Style each content section as a card. */
        .card {
            background: white;
            border-radius: 10px;
            padding: 12px;
            margin-bottom: 10px;
            box-shadow: 0 3px 12px #0000000b;
        }

        /* Style the headings inside cards. */
        .card h2 {
            margin: 0 0 10px;
            font-size: 17px;
        }

        /* Display form fields in two columns. */
        .grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        /* Style form labels. */
        label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        /* Apply consistent styles to form controls. */
        input,
        textarea,
        select {
            width: 100%;
            padding: 8px 9px;
            border: 1px solid #cbd5e1;
            border-radius: 7px;
            font: inherit;
        }

        /* Allow the description field to be resized vertically. */
        textarea {
            resize: vertical;
            min-height: 52px;
        }

        /* Make selected fields occupy the entire form width. */
        .full {
            grid-column: 1 / -1;
        }

        /* Arrange the form action buttons. */
        .actions {
            display: flex;
            gap: 9px;
            flex-wrap: wrap;
            margin-top: 9px;
        }

        /* Style success messages. */
        .alert {
            padding: 12px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #dcfce7;
            color: #166534;
        }

        /* Style error messages. */
        .alert.error {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Arrange the search field and its buttons. */
        .search {
            display: flex;
            gap: 9px;
            max-width: 480px;
            margin-bottom: 10px;
        }

        /* Allow the search input to shrink on small screens. */
        .search input {
            flex: 1;
            min-width: 0;
        }

        /* Enable horizontal scrolling for wide tables. */
        .table-wrap {
            overflow-x: auto;
        }

        /* Set the table layout. */
        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 680px;
        }

        /* Style table cells. */
        th,
        td {
            text-align: left;
            padding: 8px 9px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
            vertical-align: middle;
        }

        /* Highlight the table heading row. */
        th {
            background: #f0fdfa;
            color: #115e59;
        }

        /* Make food names bold. */
        .food-name {
            font-weight: bold;
        }

        /* Display food categories as rounded labels. */
        .category {
            display: inline-block;
            background: #ccfbf1;
            color: #115e59;
            padding: 5px 8px;
            border-radius: 20px;
            font-size: 12px;
        }

        /* Center the message shown when no items are available. */
        .empty {
            text-align: center;
            padding: 28px;
            color: #64748b;
        }

        /* Arrange the Edit and Delete buttons. */
        .row-actions {
            display: flex;
            gap: 7px;
            align-items: center;
            flex-wrap: wrap;
        }

        /* Remove extra margins from forms inside table actions. */
        .row-actions form {
            margin: 0;
        }

        .image-preview-wrap {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-top: 8px;
            flex-wrap: wrap;
        }

        .image-preview {
            width: 72px;
            height: 58px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid #dbe5e5;
            background: #f8fafc;
        }

        .image-preview.hidden {
            display: none;
        }

        .food-thumb {
            width: 44px;
            height: 38px;
            object-fit: cover;
            border-radius: 7px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            display: block;
        }

        .muted {
            color: #64748b;
            font-size: 12px;
        }

        .description-cell {
            max-width: 250px;
            overflow-wrap: anywhere;
        }

        /* Style the footer. */
        footer {
            text-align: center;
            padding: 12px;
            color: #64748b;
            font-size: 13px;
        }

        /* Adjust the layout for mobile screens. */
        @media (max-width: 600px) {

            /* Display form fields in a single column. */
            .grid {
                grid-template-columns: 1fr;
            }

            /* Reset the full-width field setting on mobile. */
            .full {
                grid-column: auto;
            }

            /* Reduce the outer spacing on mobile. */
            .container {
                margin: 10px auto;
            }

            /* Reduce card padding on mobile. */
            .card {
                padding: 11px;
            }

            /* Reduce the heading size on mobile. */
            .top h1 {
                font-size: 20px;
            }
        }
    </style>

</head>

<body>

    <!-- Display the website name and dashboard navigation link. -->
    <header>
        <h2>Local Discover & Deliver</h2>
        <a href="vendor_dashboard.php">← Vendor Dashboard</a>
    </header>

    <!-- Wrap the main page content in a centered container. -->
    <main class="container">

        <?php if ($no_restaurant): ?>

            <!-- Ask the vendor to register a restaurant before adding food. -->
            <div class="card">
                <h2>Restaurant Not Found</h2>

                <p>
                    Please add your restaurant details before managing food items.
                </p>

                <a class="btn" href="manage_restaurant.php">
                    Add Restaurant
                </a>
            </div>

        <?php else: ?>

            <!-- Display the page title and the vendor's restaurant name. -->
            <div class="top">

                <div>
                    <h1>Manage Food Items</h1>

                    <div class="restaurant">
                        Restaurant: <?= e($restaurant['restaurant_name']) ?>
                    </div>
                </div>

                <!-- Provide a shortcut back to the vendor dashboard. -->
                <a class="btn" href="vendor_dashboard.php">
                    ← Dashboard
                </a>

            </div>

            <!-- Display a success or error message when available. -->
            <?php if ($message !== ''): ?>

                <div class="alert <?= $message_type === 'error' ? 'error' : '' ?>">
                    <?= e($message) ?>
                </div>

            <?php endif; ?>

            <!-- Display the form used to add or edit food items. -->
            <section class="card" id="food-form">

                <!-- Change the form heading depending on the selected action. -->
                <h2>
                    <?= $edit_food ? 'Edit Food Item' : 'Add New Food Item' ?>
                </h2>

                <!-- Submit food details and an optional image to this page. -->
                <form method="POST" enctype="multipart/form-data">

                    <!-- Send the security token with the form submission. -->
                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <!-- Tell the PHP script to save the food item. -->
                    <input type="hidden" name="action" value="save">

                    <!-- Store the food ID; zero means a new item. -->
                    <input
                        type="hidden"
                        name="food_id"
                        value="<?= e($edit_food['id'] ?? 0) ?>"
                    >

                    <!-- Arrange the food input fields in a grid. -->
                    <div class="grid">

                        <!-- Collect the food name. -->
                        <div>
                            <label for="name">Food Name *</label>

                            <input
                                id="name"
                                name="name"
                                type="text"
                                maxlength="150"
                                value="<?= e($edit_food['name'] ?? '') ?>"
                                required
                            >
                        </div>

                        <!-- Collect the food price. -->
                        <div>
                            <label for="price">Price (₹) *</label>

                            <input
                                id="price"
                                name="price"
                                type="number"
                                min="0.01"
                                step="0.01"
                                value="<?= e($edit_food['price'] ?? '') ?>"
                                required
                            >
                        </div>

                        <!-- Allow the vendor to select a food category. -->
                        <div>
                            <label for="category">Category *</label>

                            <select id="category" name="category" required>

                                <option value="">Select category</option>

                                <?php foreach (['Veg', 'Non Veg', 'Other'] as $cat): ?>

                                    <option
                                        value="<?= e($cat) ?>"
                                        <?= (($edit_food['category'] ?? '') === $cat)
                                            ? 'selected'
                                            : '' ?>
                                    >
                                        <?= e($cat) ?>
                                    </option>

                                <?php endforeach; ?>

                            </select>
                        </div>

                        <!-- Allow the vendor to upload a food image. -->
                        <div>
                            <label for="image">
                                Food Image (JPG, PNG, WEBP; max 5 MB)
                            </label>

                            <input
                                id="image"
                                name="image"
                                type="file"
                                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                            >

                            <!-- Show the current image while editing. -->
                            <?php if (!empty($edit_food['image'])): ?>
                                <div class="image-preview-wrap">
                                    <img
                                        class="image-preview"
                                        id="currentImagePreview"
                                        src="../backend/uploads/<?= e($edit_food['image']) ?>"
                                        alt="Current food image"
                                    >
                                    <small class="muted">Current image. Choose another image to replace it.</small>
                                </div>
                            <?php endif; ?>

                            <!-- Preview a newly selected image before uploading. -->
                            <div class="image-preview-wrap">
                                <img
                                    id="imagePreview"
                                    class="image-preview hidden"
                                    src=""
                                    alt="Selected food image preview"
                                >
                                <small id="imagePreviewText" class="muted">Image preview will appear here.</small>
                            </div>
                        </div>

                        <!-- Collect an optional food description. -->
                        <div class="full">
                            <label for="description">Description</label>

                            <textarea
                                id="description"
                                name="description"
                                maxlength="2000"
                            ><?= e($edit_food['description'] ?? '') ?></textarea>
                        </div>

                    </div>

                    <!-- Display the appropriate form submission buttons. -->
                    <div class="actions">

                        <button class="btn" type="submit">
                            <?= $edit_food ? 'Update Food Item' : 'Add Food Item' ?>
                        </button>

                        <!-- Allow the vendor to cancel editing. -->
                        <?php if ($edit_food): ?>

                            <a class="btn gray" href="manage_food.php">
                                Cancel Edit
                            </a>

                        <?php endif; ?>

                    </div>

                </form>

            </section>

            <!-- Display the food items belonging to this restaurant. -->
            <section class="card">

                <!-- Show the number of items currently displayed. -->
                <h2>Your Food Items (<?= count($foods) ?>)</h2>

                <!-- Provide a form for searching by food name. -->
                <form class="search" method="GET">

                    <input
                        type="text"
                        name="search"
                        placeholder="Search food name..."
                        value="<?= e($_GET['search'] ?? '') ?>"
                    >

                    <button class="btn" type="submit">Search</button>

                    <!-- Clear the current search filter. -->
                    <?php if (!empty($_GET['search'])): ?>

                        <a class="btn gray" href="manage_food.php">
                            Clear
                        </a>

                    <?php endif; ?>

                </form>

                <!-- Show a message if no food items match the search. -->
                <?php if (empty($foods)): ?>

                    <div class="empty">
                        No food items found. Add your first food item above.
                    </div>

                <?php else: ?>

                    <!-- Make the table scrollable on smaller screens. -->
                    <div class="table-wrap">

                        <table>

                            <!-- Define the food table headings. -->
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Food Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Description</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>

                            <!-- Display the food items returned by the database. -->
                            <tbody>

                                <?php foreach ($foods as $food): ?>

                                    <tr>

                                        <!-- Display the food image thumbnail when available. -->
                                        <td>
                                            <?php if (!empty($food['image'])): ?>
                                                <img
                                                    class="food-thumb"
                                                    src="../backend/uploads/<?= e($food['image']) ?>"
                                                    alt="<?= e($food['name']) ?>"
                                                    loading="lazy"
                                                >
                                            <?php else: ?>
                                                <span class="muted">No image</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Display the food name. -->
                                        <td class="food-name">
                                            <?= e($food['name']) ?>
                                        </td>

                                        <!-- Display the food category. -->
                                        <td>
                                            <span class="category">
                                                <?= e($food['category']) ?>
                                            </span>
                                        </td>

                                        <!-- Display the price in Indian rupees. -->
                                        <td>
                                            ₹<?= number_format((float) $food['price'], 2) ?>
                                        </td>

                                        <!-- Display the food description. -->
                                        <td class="description-cell">
                                            <?= e($food['description']) !== '' ? e($food['description']) : '<span class="muted">No description</span>' ?>
                                        </td>

                                        <!-- Provide Edit and Delete actions. -->
                                        <td>

                                            <div class="row-actions">

                                                <!-- Open the selected food item in edit mode. -->
                                                <a
                                                    class="btn"
                                                    href="manage_food.php?edit=<?= (int) $food['id'] ?>#food-form"
                                                >
                                                    Edit
                                                </a>

                                                <!-- Submit a protected request to delete the item. -->
                                                <form
                                                    method="POST"
                                                    onsubmit="return confirm('Delete this food item?');"
                                                >

                                                    <!-- Include the security token. -->
                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                                    >

                                                    <!-- Identify the requested action. -->
                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete"
                                                    >

                                                    <!-- Identify the food item to delete. -->
                                                    <input
                                                        type="hidden"
                                                        name="food_id"
                                                        value="<?= (int) $food['id'] ?>"
                                                    >

                                                    <!-- Ask for confirmation before deletion. -->
                                                    <button class="btn red" type="submit">
                                                        Delete
                                                    </button>

                                                </form>

                                            </div>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

    </main>

    <!-- Display the website copyright information. -->
    <footer>
        © <?= date('Y') ?> Local Discover & Deliver
    </footer>


    <script>
        // Preview the selected food image before submitting the form.
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');
        const imagePreviewText = document.getElementById('imagePreviewText');

        if (imageInput && imagePreview && imagePreviewText) {
            imageInput.addEventListener('change', function () {
                const file = this.files && this.files[0];

                if (!file) {
                    imagePreview.src = '';
                    imagePreview.classList.add('hidden');
                    imagePreviewText.textContent = 'Image preview will appear here.';
                    return;
                }

                const allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

                if (!allowedTypes.includes(file.type)) {
                    imageInput.value = '';
                    imagePreview.src = '';
                    imagePreview.classList.add('hidden');
                    imagePreviewText.textContent = 'Please choose a JPG, PNG or WEBP image.';
                    return;
                }

                if (file.size > 5 * 1024 * 1024) {
                    imageInput.value = '';
                    imagePreview.src = '';
                    imagePreview.classList.add('hidden');
                    imagePreviewText.textContent = 'Image must be 5 MB or smaller.';
                    return;
                }

                const reader = new FileReader();
                reader.onload = function (event) {
                    imagePreview.src = event.target.result;
                    imagePreview.classList.remove('hidden');
                    imagePreviewText.textContent = file.name;
                };
                reader.readAsDataURL(file);
            });
        }
    </script>

</body>
</html>