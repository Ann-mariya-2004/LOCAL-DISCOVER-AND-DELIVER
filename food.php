
<?php
session_start();
require_once __DIR__ . "/backend/db.php";

// Require login before showing restaurant menus.
if (!isset($_SESSION["user_id"])) {
    header("Location: login_registration.html");
    exit();
}

// Read and validate the selected restaurant ID from the URL.
$restaurant_id = filter_input(INPUT_GET, "restaurant_id", FILTER_VALIDATE_INT);

if (!$restaurant_id || $restaurant_id < 1) {
    header("Location: restaurants.php");
    exit();
}

// Fetch the selected restaurant's information securely.
$restaurant_stmt = mysqli_prepare(
    $conn,
    "SELECT id, restaurant_name, location, category, description
     FROM restaurants
     WHERE id = ?
     LIMIT 1"
);

$restaurant = null;

if ($restaurant_stmt) {
    mysqli_stmt_bind_param($restaurant_stmt, "i", $restaurant_id);
    mysqli_stmt_execute($restaurant_stmt);

    $restaurant_result = mysqli_stmt_get_result($restaurant_stmt);
    $restaurant = mysqli_fetch_assoc($restaurant_result);

    mysqli_stmt_close($restaurant_stmt);
}

// Return to the restaurant list if the restaurant does not exist.
if (!$restaurant) {
    header("Location: restaurants.php");
    exit();
}

// Fetch food items belonging only to the selected restaurant.
$food_stmt = mysqli_prepare(
    $conn,
    "SELECT id, name, description, price, image, category
     FROM food_items
     WHERE restaurant_id = ?
     ORDER BY name ASC"
);

$foods = false;

if ($food_stmt) {
    mysqli_stmt_bind_param($food_stmt, "i", $restaurant_id);
    mysqli_stmt_execute($food_stmt);

    $foods = mysqli_stmt_get_result($food_stmt);
}

// Escape values before displaying database content in HTML.
function e($value): string {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

// Find food images in the existing upload directories.
function foodImagePath($image): string {
    $image = basename((string)$image);

    if ($image === "") {
        return "";
    }

    $folders = [
        __DIR__ . "/uploads/",
        __DIR__ . "/backend/uploads/"
    ];

    foreach ($folders as $folder) {
        if (is_file($folder . $image)) {
            $relativePath = str_replace(
                __DIR__ . DIRECTORY_SEPARATOR,
                "",
                $folder
            );

            $relativePath = str_replace(
                DIRECTORY_SEPARATOR,
                "/",
                $relativePath
            );

            return $relativePath . rawurlencode($image);
        }
    }

    return "";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($restaurant["restaurant_name"]) ?> | LDD Menu</title>

<style>
:root {
    --teal: #0f766e;
    --deep: #134e4a;
    --page: #f3f8f7;
    --muted: #64748b;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--page);
    color: #18312f;
}

a {
    text-decoration: none;
}

header {
    padding: 14px clamp(18px, 5vw, 70px);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 18px;
    background: var(--deep);
    color: white;
}

.logo {
    font-size: 25px;
    font-weight: 800;
    color: white;
}

.logo span {
    color: #facc15;
}

nav {
    display: flex;
    gap: 18px;
    flex-wrap: wrap;
}

nav a {
    color: #ecfdf5;
    font-size: 14px;
    font-weight: 600;
}

.hero {
    padding: 25px 20px;
    background: linear-gradient(135deg, #134e4a, #0f766e);
    color: white;
}

.hero-inner {
    width: min(1120px, 100%);
    margin: auto;
}

.back {
    display: inline-block;
    margin-bottom: 12px;
    color: #ccfbf1;
    font-size: 14px;
    font-weight: 700;
}

.hero h1 {
    margin: 0 0 8px;
    font-size: clamp(26px, 4vw, 35px);
}

.hero p {
    margin: 5px 0;
    color: #d9fffa;
    line-height: 1.5;
}

.pill {
    display: inline-block;
    margin-top: 8px;
    padding: 5px 10px;
    border-radius: 99px;
    background: #ffffff20;
    font-size: 12px;
    font-weight: 700;
}

main {
    width: min(1120px, 100%);
    margin: auto;
    padding: 25px 20px 40px;
}

.section-head {
    margin-bottom: 17px;
}

.section-head h2 {
    margin: 0;
    color: var(--deep);
    font-size: 23px;
}

.section-head p {
    margin: 5px 0 0;
    color: var(--muted);
    font-size: 13px;
}

.food-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
}

.food-card {
    overflow: hidden;
    display: flex;
    flex-direction: column;
    background: white;
    border: 1px solid #e0eeeb;
    border-radius: 15px;
    box-shadow: 0 5px 18px #0f766e0b;
}

.food-image,
.food-placeholder {
    width: 100%;
    height: 160px;
    object-fit: cover;
    background: linear-gradient(135deg, #ccfbf1, #ecfdf5);
}

.food-placeholder {
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 50px;
}

.food-details {
    display: flex;
    flex: 1;
    flex-direction: column;
    padding: 16px;
}

.food-details h3 {
    margin: 0 0 8px;
    color: var(--deep);
    font-size: 18px;
}

.description {
    flex: 1;
    margin: 0 0 12px;
    color: var(--muted);
    font-size: 13px;
    line-height: 1.5;
}

.category {
    align-self: flex-start;
    margin-bottom: 9px;
    padding: 4px 8px;
    background: #f0fdfa;
    color: var(--teal);
    border-radius: 99px;
    font-size: 11px;
    font-weight: 700;
}

.price {
    margin: 0 0 13px;
    color: var(--teal);
    font-size: 19px;
    font-weight: 800;
}

.order-btn {
    display: block;
    margin-top: auto;
    padding: 11px;
    text-align: center;
    border-radius: 9px;
    background: var(--teal);
    color: white;
    font-size: 14px;
    font-weight: 700;
}

.order-btn:hover {
    background: var(--deep);
}

.empty {
    grid-column: 1 / -1;
    padding: 32px 18px;
    text-align: center;
    background: white;
    border: 1px dashed #b7d9d2;
    border-radius: 14px;
    color: var(--muted);
}

.empty a {
    display: inline-block;
    margin-top: 12px;
    color: var(--teal);
    font-weight: 700;
}

footer {
    padding: 17px;
    text-align: center;
    background: #103f3b;
    color: #d1fae5;
    font-size: 12px;
}

@media (max-width: 900px) {
    .food-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    header {
        align-items: flex-start;
        flex-direction: column;
    }

    .food-grid {
        grid-template-columns: 1fr;
    }

    .food-image,
    .food-placeholder {
        height: 190px;
    }

    main {
        padding: 22px 14px 32px;
    }
}
</style>
</head>

<body>

<header>
    <a class="logo" href="dashboard.php">L<span>D</span>D</a>

    <nav aria-label="Main navigation">
        <a href="dashboard.php">Dashboard</a>
        <a href="restaurants.php">All Restaurants</a>
        <a href="grocery.php">Grocery</a>
        <a href="profile.php">Profile</a>
    </nav>
</header>

<section class="hero">
    <div class="hero-inner">

        <a class="back" href="restaurants.php">
            ← Back to restaurants
        </a>

        <h1><?= e($restaurant["restaurant_name"]) ?></h1>

        <p>
            📍 <?= e($restaurant["location"] ?: "Location not provided") ?>
        </p>

        <?php if (!empty($restaurant["description"])): ?>
            <p><?= e($restaurant["description"]) ?></p>
        <?php endif; ?>

        <?php if (!empty($restaurant["category"])): ?>
            <span class="pill">
                <?= e($restaurant["category"]) ?>
            </span>
        <?php endif; ?>

    </div>
</section>

<main>

    <div class="section-head">
        <h2>Restaurant Menu</h2>

        <p>
            Choose your favourite item from
            <?= e($restaurant["restaurant_name"]) ?>.
        </p>
    </div>

    <div class="food-grid">

        <?php if (!$foods): ?>

            <div class="empty">
                The menu could not be loaded.
                Please check your database and try again.
            </div>

        <?php elseif (mysqli_num_rows($foods) > 0): ?>

            <?php while ($food = mysqli_fetch_assoc($foods)): ?>

                <?php
                // Resolve the uploaded image for this food item.
                $imagePath = foodImagePath($food["image"] ?? "");
                ?>

                <article class="food-card">

                    <?php if ($imagePath !== ""): ?>

                        <img
                            class="food-image"
                            src="<?= e($imagePath) ?>"
                            alt="<?= e($food["name"]) ?>"
                            loading="lazy"
                        >

                    <?php else: ?>

                        <div class="food-placeholder" aria-hidden="true">
                            🍽️
                        </div>

                    <?php endif; ?>

                    <div class="food-details">

                        <h3><?= e($food["name"]) ?></h3>

                        <?php if (!empty($food["category"])): ?>
                            <span class="category">
                                <?= e($food["category"]) ?>
                            </span>
                        <?php endif; ?>

                        <p class="description">
                            <?= e(
                                $food["description"]
                                ?: "Freshly prepared for you."
                            ) ?>
                        </p>

                        <p class="price">
                            ₹<?= number_format((float)$food["price"], 2) ?>
                        </p>

                        <!-- Keep the existing food ordering page and selected item ID. -->
                        <a
                            class="order-btn"
                            href="food_order.php?id=<?= (int)$food["id"] ?>"
                        >
                            Order Now →
                        </a>

                    </div>
                </article>

            <?php endwhile; ?>

        <?php else: ?>

            <div class="empty">
                This restaurant has not added any food items yet.
                <br>
                <a href="restaurants.php">
                    Explore other restaurants →
                </a>
            </div>

        <?php endif; ?>

    </div>

</main>

<footer>
    © 2026 Local Discover &amp; Deliver
</footer>

<?php
// Close the prepared statement after displaying the menu.
if ($food_stmt) {
    mysqli_stmt_close($food_stmt);
}
?>

</body>
</html>
