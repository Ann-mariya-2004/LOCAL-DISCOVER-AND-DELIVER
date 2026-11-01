
<?php
session_start();
require_once __DIR__ . '/backend/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}

$conn->set_charset('utf8mb4');

/* Escape output to help prevent HTML injection */
function h($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* Load restaurant details from the database */
$sql = "SELECT * FROM restaurants ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if (!$result) {
    die('Unable to load restaurants. Please check the database.');
}

$restaurants = [];
while ($row = mysqli_fetch_assoc($result)) {
    $restaurants[] = $row;
}

/* Get the current user's profile details */
$current_user_name = $_SESSION['name'] ?? 'My Profile';
$current_user_image = 'assets/default-profile.png';

if (isset($_SESSION['user_id'])) {
    $user_id = (int)$_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT name, profile_image FROM users WHERE id = ? LIMIT 1");

    if ($stmt) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $user_result = $stmt->get_result();

        if ($user = $user_result->fetch_assoc()) {
            $current_user_name = $user['name'] ?? $current_user_name;

            if (!empty($user['profile_image'])) {
                $current_user_image = $user['profile_image'];
            }
        }

        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | Explore Restaurants</title>

<style>
:root {
    --primary: #0f766e;
    --primary-dark: #134e4a;
    --accent: #14b8a6;
    --soft: #e8f8f5;
    --page: #f5f8f7;
    --white: #ffffff;
    --text: #173d39;
    --muted: #71817e;
    --border: #e1eae7;
    --shadow: 0 4px 16px rgba(17, 64, 57, 0.06);
}

* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
    font-family: Arial, sans-serif;
}

body {
    background: var(--page);
    color: var(--text);
}

a {
    text-decoration: none;
}

/* Header styled to match the Grocery page */
.site-header {
    background: var(--white);
    border-bottom: 1px solid var(--border);
}

.container {
    width: min(1180px, 92%);
    margin: 0 auto;
}

.header-inner {
    min-height: 70px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}

.logo {
    color: var(--primary-dark);
    font-size: 27px;
    font-weight: 900;
    letter-spacing: -1px;
}

.logo span {
    color: #facc15;
}

.page-nav,
.nav,
.account-area {
    display: flex;
    align-items: center;
    gap: 18px;
}

.nav a,
.profile-name {
    color: #3c5350;
    font-size: 14px;
    font-weight: 600;
}

.nav a:hover {
    color: var(--primary);
}

.profile-link {
    display: flex;
    align-items: center;
    gap: 8px;
}

.profile-avatar {
    width: 34px;
    height: 34px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #d7eee9;
}

.logout-btn,
.login-btn {
    padding: 9px 13px;
    border: 1px solid #d4e5e0;
    border-radius: 8px;
    color: var(--primary-dark);
    font-size: 13px;
    font-weight: 700;
}

/* Compact heading and search area */
.search-section {
    background: linear-gradient(110deg, #e8f8f5, #f7fbfa);
    border-bottom: 1px solid #e1eeeb;
    padding: 20px 0;
}

.search-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.eyebrow {
    display: block;
    color: var(--primary);
    font-size: 10px;
    font-weight: 800;
    letter-spacing: 1.2px;
    margin-bottom: 5px;
}

.search-title h1 {
    color: #123c38;
    font-size: clamp(23px, 3vw, 30px);
    line-height: 1.2;
}

.search-title p {
    margin-top: 5px;
    color: var(--muted);
    font-size: 13px;
}

.search-form {
    width: min(430px, 48%);
    display: flex;
    gap: 5px;
    padding: 4px;
    background: white;
    border: 1px solid #dce9e5;
    border-radius: 10px;
}

.search-form input {
    flex: 1;
    min-width: 0;
    padding: 10px 11px;
    border: 0;
    outline: none;
    background: transparent;
    color: var(--text);
    font-size: 13px;
}

.search-form button {
    padding: 0 17px;
    border: 0;
    border-radius: 7px;
    background: var(--primary);
    color: white;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
}

.search-form button:hover {
    background: var(--primary-dark);
}

/* Restaurant listing section */
.section {
    padding: 25px 0 35px;
}

.section-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 15px;
    margin-bottom: 18px;
}

.section-heading h2 {
    color: #123c38;
    font-size: 21px;
}

.section-heading p {
    margin-top: 4px;
    color: var(--muted);
    font-size: 12px;
}

.restaurant-count {
    padding: 8px 12px;
    border-radius: 30px;
    background: #e5f6f2;
    color: var(--primary);
    font-size: 12px;
    font-weight: 800;
    white-space: nowrap;
}

.restaurant-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 17px;
}

.restaurant-card {
    overflow: hidden;
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 14px;
    box-shadow: var(--shadow);
    transition: transform .2s ease, box-shadow .2s ease;
}

.restaurant-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 9px 24px rgba(17, 64, 57, 0.10);
}

.restaurant-image {
    height: 145px;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
    background: var(--soft);
}

.restaurant-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.restaurant-placeholder {
    font-size: 48px;
}

.restaurant-details {
    padding: 16px;
}

.restaurant-details h3 {
    margin-bottom: 8px;
    color: #173d39;
    font-size: 17px;
}

.restaurant-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
    margin-bottom: 10px;
}

.meta-tag {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 6px;
    background: #f0f7f5;
    color: #54716b;
    font-size: 11px;
}

.restaurant-location {
    min-height: 18px;
    margin-bottom: 13px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

.order-btn {
    display: block;
    width: 100%;
    padding: 11px 12px;
    border-radius: 8px;
    background: var(--primary);
    color: white;
    text-align: center;
    font-size: 13px;
    font-weight: 700;
    transition: background .2s ease;
}

.order-btn:hover {
    background: var(--primary-dark);
}

.no-results {
    grid-column: 1 / -1;
    padding: 30px 15px;
    border: 1px dashed #cbded8;
    border-radius: 12px;
    background: white;
    color: var(--muted);
    text-align: center;
    font-size: 14px;
}

footer {
    padding: 17px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    text-align: center;
    font-size: 12px;
}

/* Responsive layout */
@media (max-width: 900px) {
    .restaurant-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .header-inner {
        padding: 12px 0;
        align-items: flex-start;
    }

    .page-nav {
        justify-content: flex-end;
        flex-wrap: wrap;
        gap: 12px;
    }

    .nav {
        gap: 12px;
    }

    .account-area {
        gap: 8px;
    }
}

@media (max-width: 600px) {
    .header-inner,
    .search-inner {
        flex-direction: column;
        align-items: stretch;
    }

    .header-inner {
        gap: 12px;
    }

    .page-nav {
        justify-content: flex-start;
    }

    .nav {
        flex-wrap: wrap;
    }

    .search-section {
        padding: 17px 0;
    }

    .search-inner {
        gap: 13px;
    }

    .search-form {
        width: 100%;
    }

    .section {
        padding-top: 20px;
    }

    .restaurant-grid {
        grid-template-columns: 1fr;
        gap: 13px;
    }

    .restaurant-card {
        display: grid;
        grid-template-columns: 112px minmax(0, 1fr);
    }

    .restaurant-image {
        height: 100%;
        min-height: 150px;
    }

    .restaurant-placeholder {
        font-size: 35px;
    }

    .restaurant-details {
        padding: 12px;
    }

    .restaurant-details h3 {
        font-size: 15px;
    }

    .section-heading h2 {
        font-size: 19px;
    }
}


/* Reduce unnecessary vertical spacing */
.header-inner {
    min-height: 56px;
}

.search-section {
    padding: 12px 0;
}

.search-inner {
    gap: 16px;
}

.search-title h1 {
    font-size: 24px;
}

.search-title p {
    margin-top: 3px;
    font-size: 12px;
}

.eyebrow {
    margin-bottom: 3px;
    font-size: 9px;
}

.search-form {
    max-width: 360px;
}

.search-form input {
    padding: 8px 10px;
}

.search-form button {
    padding: 0 13px;
}

.section {
    padding: 17px 0 24px;
}

.section-heading {
    margin-bottom: 12px;
}

.section-heading h2 {
    font-size: 18px;
}

.section-heading p {
    margin-top: 3px;
    font-size: 11px;
}

.restaurant-grid {
    gap: 12px;
}

.restaurant-image {
    height: 115px;
}

.restaurant-details {
    padding: 12px;
}

.restaurant-details h3 {
    margin-bottom: 6px;
    font-size: 15px;
}

.restaurant-meta {
    margin-bottom: 6px;
}

.restaurant-location {
    margin-bottom: 9px;
    font-size: 11px;
}

.order-btn {
    padding: 9px;
    font-size: 12px;
}

footer {
    padding: 11px;
}

@media (max-width: 600px) {
    .header-inner {
        min-height: auto;
    }

    .search-section {
        padding: 13px 0;
    }

    .search-inner {
        gap: 10px;
    }

    .search-title h1 {
        font-size: 22px;
    }

    .section {
        padding-top: 14px;
    }

    .restaurant-grid {
        gap: 10px;
    }

    .restaurant-card {
        grid-template-columns: 95px minmax(0, 1fr);
    }

    .restaurant-image {
        min-height: 125px;
    }

    .restaurant-details {
        padding: 10px;
    }
}


</style>
</head>

<body>

<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="index.html">L<span>D</span>D</a>

        <div class="page-nav">
            <nav class="nav" aria-label="Customer navigation">
                <a href="dashboard.php">Dashboard</a>
                <a href="restaurants.php">Food</a>
                <a href="grocery.php">Grocery</a>
                <a href="print_order.html">Printout</a>
            </nav>

            <div class="account-area">
                <?php if (isset($_SESSION['user_id'])): ?>
                    <a class="profile-link" href="profile.php">
                        <img
                            class="profile-avatar"
                            src="<?= h($current_user_image) ?>"
                            alt="Profile"
                            onerror="this.onerror=null;this.src='assets/default-profile.png';"
                        >
                        <span class="profile-name"><?= h($current_user_name) ?></span>
                    </a>
                    <a class="logout-btn" href="backend/logout.php">Logout</a>
                <?php else: ?>
                    <a class="login-btn" href="login_registration.html">Login</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</header>

<section class="search-section">
    <div class="container search-inner">
        <div class="search-title">
            <span class="eyebrow">FOOD &amp; DINING</span>
            <h1>Explore Restaurants</h1>
            <p>Discover local favourites near you.</p>
        </div>

        <form class="search-form" id="searchForm">
            <input
                type="search"
                id="restaurantSearch"
                placeholder="Search restaurants or cuisine..."
                aria-label="Search restaurants"
            >
            <button type="submit">Search</button>
        </form>
    </div>
</section>

<main class="container section">
    <div class="section-heading">
        <div>
            <h2>Choose a Restaurant</h2>
            <p>Select a restaurant to explore its menu.</p>
        </div>
        <span class="restaurant-count" id="restaurantCount">
            <?= count($restaurants) ?> restaurants
        </span>
    </div>

    <div class="restaurant-grid" id="restaurantList">
        <?php foreach ($restaurants as $restaurant): ?>
            <?php
                $restaurant_id = (int)($restaurant['id'] ?? 0);
                $restaurant_name = $restaurant['restaurant_name'] ?? 'Restaurant';
                $location = $restaurant['location'] ?? 'Location not provided';
                $category = $restaurant['category'] ?? 'Restaurant';
                $description = $restaurant['description'] ?? '';
                $image = trim((string)($restaurant['image'] ?? ''));

                /* Support common image locations used in this project */
                $image_path = '';
                if ($image !== '') {
                    $image_path = $image;
                }
            ?>

            <article
                class="restaurant-card"
                data-search="<?= h($restaurant_name . ' ' . $location . ' ' . $category . ' ' . $description) ?>"
            >
                <div class="restaurant-image">
                    <?php if ($image_path !== ''): ?>
                        <img
                            src="<?= h($image_path) ?>"
                            alt="<?= h($restaurant_name) ?>"
                            loading="lazy"
                            onerror="this.style.display='none';this.nextElementSibling.style.display='block';"
                        >
                        <span class="restaurant-placeholder" style="display:none;">🍽️</span>
                    <?php else: ?>
                        <span class="restaurant-placeholder">🍽️</span>
                    <?php endif; ?>
                </div>

                <div class="restaurant-details">
                    <h3><?= h($restaurant_name) ?></h3>

                    <div class="restaurant-meta">
                        <span class="meta-tag"><?= h($category) ?></span>
                    </div>

                    <p class="restaurant-location">
                        📍 <?= h($location) ?>
                    </p>

                    <a
                        class="order-btn"
                        href="food.php?restaurant_id=<?= $restaurant_id ?>"
                    >
                        View Menu →
                    </a>
                </div>
            </article>
        <?php endforeach; ?>

        <div class="no-results" id="noResults" style="display:none;">
            No matching restaurants found. Try another search.
        </div>
    </div>
</main>

<footer>
    © <?= date('Y') ?> Local Discover and Deliver
</footer>

<script>
/* Filter restaurant cards by name, location, category, or description */
const searchInput = document.getElementById('restaurantSearch');
const searchForm = document.getElementById('searchForm');
const restaurantCards = Array.from(document.querySelectorAll('.restaurant-card'));
const countLabel = document.getElementById('restaurantCount');
const noResults = document.getElementById('noResults');

function searchRestaurants() {
    const query = searchInput.value.trim().toLowerCase();
    let visibleCount = 0;

    restaurantCards.forEach(card => {
        const searchableText = (card.dataset.search || '').toLowerCase();
        const matches = searchableText.includes(query);

        card.style.display = matches ? '' : 'none';

        if (matches) {
            visibleCount++;
        }
    });

    countLabel.textContent = visibleCount + (visibleCount === 1 ? ' restaurant' : ' restaurants');
    noResults.style.display = visibleCount === 0 ? 'block' : 'none';
}

searchInput.addEventListener('input', searchRestaurants);

searchForm.addEventListener('submit', function(event) {
    event.preventDefault();
    searchRestaurants();
});

/* Return to the previous page when browser history is available */
function goBack() {
    if (window.history.length > 1) {
        window.history.back();
    } else {
        window.location.href = 'dashboard.php';
    }
}
</script>

</body>
</html>
