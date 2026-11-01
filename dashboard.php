<?php
session_start();
require_once "backend/db.php";

// Check whether the user is logged in.
if (!isset($_SESSION["user_id"])) {
    header("Location: login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$user_name = $_SESSION["name"] ?? "Customer";
$profile_image = "";

// Get the latest user details from the database.
$stmt = mysqli_prepare(
    $conn,
    "SELECT name, profile_image FROM users WHERE id = ? LIMIT 1"
);

if ($stmt) {
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {
        $user_name = $user["name"] ?: $user_name;
        $profile_image = $user["profile_image"] ?? "";
    }

    mysqli_stmt_close($stmt);
}

// Escape output before displaying it in HTML.
$safe_name = htmlspecialchars($user_name, ENT_QUOTES, "UTF-8");
$safe_profile_image = htmlspecialchars(
    $profile_image,
    ENT_QUOTES,
    "UTF-8"
);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LDD | Customer Dashboard</title>

<style>
:root {
    --primary: #0f766e;
    --secondary: #14b8a6;
    --dark: #134e4a;
    --background: #f3f7f7;
    --white: #ffffff;
    --text: #263b3a;
    --muted: #718381;
    --border: #e2ecea;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

html {
    scroll-behavior: smooth;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--background);
    color: var(--text);
}

/* Fixed left sidebar */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 225px;
    height: 100vh;
    height: 100dvh;
    padding: 24px 15px;
    background: linear-gradient(180deg, #0f766e, #115e59);
    color: white;
    display: flex;
    flex-direction: column;
    z-index: 1000;
    overflow-y: auto;
}

.brand {
    font-size: 25px;
    font-weight: 800;
    letter-spacing: 1px;
    padding: 0 12px;
}

.brand span {
    color: #99f6e4;
}

.brand-subtitle {
    color: #d0f4ee;
    font-size: 11px;
    padding: 6px 12px 25px;
}

.sidebar-heading {
    color: #bce9e3;
    font-size: 10px;
    font-weight: bold;
    letter-spacing: 1.5px;
    padding: 0 12px 10px;
}

.sidebar-nav {
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.sidebar-nav a {
    text-decoration: none;
    color: #e8fffb;
    padding: 12px;
    border-radius: 9px;
    display: flex;
    align-items: center;
    gap: 11px;
    font-size: 13px;
    transition: 0.2s ease;
}

.sidebar-nav a:hover,
.sidebar-nav a.active {
    background: rgba(255, 255, 255, 0.17);
    color: white;
}

.nav-icon {
    width: 22px;
    text-align: center;
    font-size: 17px;
}

.sidebar-bottom {
    margin-top: auto;
    padding-top: 25px;
}

.sidebar-user {
    border-top: 1px solid rgba(255,255,255,0.2);
    padding: 18px 8px 12px;
    display: flex;
    align-items: center;
    gap: 10px;
    overflow: hidden;
}

.sidebar-avatar {
    width: 38px;
    height: 38px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #bce9e3;
    flex-shrink: 0;
}

.avatar-placeholder {
    width: 38px;
    height: 38px;
    flex-shrink: 0;
    border-radius: 50%;
    background: white;
    color: var(--primary);
    display: grid;
    place-items: center;
    font-size: 17px;
    font-weight: bold;
}

.sidebar-user-info {
    min-width: 0;
}

.sidebar-user-info strong {
    display: block;
    font-size: 12px;
    overflow-wrap: anywhere;
}

.sidebar-user-info small {
    display: block;
    color: #c8eeea;
    margin-top: 4px;
    font-size: 11px;
}

/* Main content area */
.main-content {
    margin-left: 225px;
    padding: 24px 30px;
    min-height: 100vh;
    min-width: 0;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 20px;
}

.page-heading h1 {
    font-size: 23px;
    color: var(--dark);
}

.page-heading p {
    margin-top: 5px;
    font-size: 12px;
    color: var(--muted);
}

.topbar-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.location-badge {
    background: white;
    border: 1px solid var(--border);
    padding: 10px 12px;
    border-radius: 9px;
    font-size: 12px;
    color: var(--dark);
    max-width: 230px;
    overflow-wrap: anywhere;
}

.profile-button {
    display: flex;
    align-items: center;
    justify-content: center;
    text-decoration: none;
}

.topbar-avatar {
    width: 39px;
    height: 39px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid #b9e7df;
}

.topbar-placeholder {
    width: 39px;
    height: 39px;
    border-radius: 50%;
    background: #d7f4ee;
    color: var(--primary);
    display: grid;
    place-items: center;
    font-weight: bold;
    font-size: 17px;
}

/* Compact welcome panel */
.welcome-panel {
    background: linear-gradient(115deg, #0f766e, #14b8a6);
    border-radius: 15px;
    color: white;
    padding: 23px 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    margin-bottom: 18px;
    overflow: hidden;
}

.welcome-panel h2 {
    font-size: 23px;
    margin-bottom: 8px;
}

.welcome-panel p {
    font-size: 13px;
    line-height: 1.6;
    color: #e4fffb;
}

.welcome-icon {
    font-size: 48px;
    flex-shrink: 0;
}

/* Search panel */
.search-panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 17px 19px;
    margin-bottom: 24px;
}

.search-panel label {
    display: block;
    font-size: 13px;
    font-weight: bold;
    color: var(--dark);
    margin-bottom: 10px;
}

.search-row {
    display: flex;
    gap: 9px;
}

.search-input-wrap {
    position: relative;
    flex: 1;
    min-width: 0;
}

.search-input {
    width: 100%;
    height: 42px;
    padding: 0 13px;
    border: 1px solid #dce8e6;
    border-radius: 8px;
    outline: none;
    font-size: 13px;
}

.search-input:focus {
    border-color: var(--secondary);
    box-shadow: 0 0 0 3px rgba(20,184,166,0.1);
}

.search-button {
    border: none;
    background: var(--primary);
    color: white;
    border-radius: 8px;
    padding: 0 20px;
    font-weight: bold;
    font-size: 13px;
    cursor: pointer;
}

.search-button:hover {
    background: var(--dark);
}

.suggestions {
    position: absolute;
    top: 46px;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 8px;
    box-shadow: 0 8px 20px rgba(0,0,0,0.08);
    z-index: 100;
    overflow: hidden;
    display: none;
}

.suggestions button {
    display: block;
    width: 100%;
    border: none;
    background: white;
    text-align: left;
    padding: 11px 13px;
    cursor: pointer;
    font-size: 13px;
    color: var(--text);
}

.suggestions button:hover {
    background: #eafaf7;
}

/* Service cards */
.section-heading {
    display: flex;
    align-items: end;
    justify-content: space-between;
    gap: 12px;
    margin-bottom: 14px;
}

.section-heading h2 {
    font-size: 19px;
    color: var(--dark);
}

.section-heading p {
    font-size: 12px;
    color: var(--muted);
    margin-top: 5px;
}

.services {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 15px;
}

.service-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 13px;
    padding: 19px;
    min-width: 0;
    min-height: 170px;
    display: flex;
    flex-direction: column;
    align-items: flex-start;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.service-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 22px rgba(15,118,110,0.09);
}

.service-icon {
    width: 43px;
    height: 43px;
    border-radius: 11px;
    display: grid;
    place-items: center;
    font-size: 23px;
    margin-bottom: 13px;
    background: #e4f8f4;
}

.service-card h3 {
    font-size: 15px;
    color: var(--dark);
    margin-bottom: 7px;
}

.service-card p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
    margin-bottom: 14px;
}

.service-link {
    display: inline-block;
    margin-top: auto;
    text-decoration: none;
    color: var(--primary);
    font-size: 12px;
    font-weight: bold;
}

.service-link:hover {
    color: #0d9488;
}

.footer-note {
    text-align: center;
    color: var(--muted);
    font-size: 11px;
    padding: 25px 0 5px;
}

/* Tablet */
@media (max-width: 1050px) {
    .main-content {
        padding: 22px;
    }

    .services {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

/* Mobile and small screens */
@media (max-width: 650px) {
    .sidebar {
        width: 100%;
        height: auto;
        position: relative;
        padding: 12px;
        overflow: visible;
    }

    .brand {
        font-size: 21px;
        padding: 0 5px;
    }

    .brand-subtitle {
        padding: 4px 5px 12px;
    }

    .sidebar-heading,
    .sidebar-bottom {
        display: none;
    }

    .sidebar-nav {
        flex-direction: row;
        flex-wrap: wrap;
        gap: 5px;
    }

    .sidebar-nav a {
        padding: 9px;
        gap: 6px;
        font-size: 11px;
    }

    .nav-icon {
        font-size: 14px;
        width: auto;
    }

    .main-content {
        margin-left: 0;
        padding: 17px 13px;
    }

    .topbar {
        align-items: flex-start;
    }

    .page-heading h1 {
        font-size: 20px;
    }

    .topbar-actions {
        gap: 6px;
    }

    .location-badge {
        font-size: 10px;
        padding: 8px;
        max-width: 130px;
    }

    .welcome-panel {
        padding: 18px;
    }

    .welcome-panel h2 {
        font-size: 19px;
    }

    .welcome-panel p {
        font-size: 12px;
    }

    .welcome-icon {
        font-size: 35px;
    }

    .search-panel {
        padding: 13px;
    }

    .search-button {
        padding: 0 12px;
    }

    .services {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 10px;
    }

    .service-card {
        min-height: 165px;
        padding: 13px;
    }

    .service-icon {
        width: 37px;
        height: 37px;
        font-size: 20px;
        margin-bottom: 10px;
    }

    .service-card h3 {
        font-size: 13px;
    }

    .service-card p {
        font-size: 11px;
    }
}

@media (max-width: 380px) {
    .services {
        grid-template-columns: 1fr;
    }

    .search-row {
        flex-direction: column;
    }

    .search-button {
        height: 40px;
    }
}

/* Compact dashboard layout */
.main-content {
    padding: 14px 20px;
}

.topbar {
    margin-bottom: 12px;
}

.welcome-panel {
    padding: 14px 20px;
    margin-bottom: 12px;
}

.welcome-panel h2 {
    font-size: 20px;
    margin-bottom: 4px;
}

.welcome-icon {
    font-size: 35px;
}

.search-panel {
    padding: 12px 15px;
    margin-bottom: 16px;
}

.search-panel label {
    margin-bottom: 7px;
}

.search-input {
    height: 38px;
}

.section-heading {
    margin-bottom: 10px;
}

.service-card {
    padding: 13px;
    min-height: 145px;
}

.service-icon {
    width: 36px;
    height: 36px;
    font-size: 20px;
    margin-bottom: 8px;
}

.service-card h3 {
    margin-bottom: 5px;
}

.service-card p {
    margin-bottom: 8px;
}

.footer-note {
    padding: 14px 0 3px;
}

</style>
</head>

<body>

<!-- Fixed sidebar navigation -->
<aside class="sidebar">

    <div class="brand">L<span>DD</span></div>
    <div class="brand-subtitle">Local Discover & Deliver</div>

    <div class="sidebar-heading">MAIN MENU</div>

    <nav class="sidebar-nav">
        <a href="dashboard.php" class="active">
            <span class="nav-icon">⌂</span>
            Dashboard
        </a>

        <a href="restaurants.php">
            <span class="nav-icon">🍽️</span>
            Food & Restaurants
        </a>

        <a href="grocery.php">
            <span class="nav-icon">🛒</span>
            Grocery
        </a>

        <a href="print_order.html">
            <span class="nav-icon">🖨️</span>
            Print & Photocopy
        </a>

        <a href="track_delivery.php">
    <span class="nav-icon">🚚</span>
    Track My Orders
    </a>

        <a href="feedback.php">
            <span class="nav-icon">⭐</span>
            Customer Feedback
        </a>

        <a href="profile.php">
            <span class="nav-icon">👤</span>
            My Profile
        </a>
    </nav>

    <div class="sidebar-bottom">
        <div class="sidebar-user">

            <?php if (!empty($safe_profile_image)): ?>
                <img
                    src="<?php echo $safe_profile_image; ?>"
                    alt="Profile"
                    class="sidebar-avatar"
                >
            <?php else: ?>
                <div class="avatar-placeholder">
                    <?php echo htmlspecialchars(
                        strtoupper(substr($user_name, 0, 1)),
                        ENT_QUOTES,
                        "UTF-8"
                    ); ?>
                </div>
            <?php endif; ?>

            <div class="sidebar-user-info">
                <strong><?php echo $safe_name; ?></strong>
                <small>Customer Account</small>
            </div>
        </div>

        <nav class="sidebar-nav">
            <a href="backend/logout.php">
                <span class="nav-icon">↪</span>
                Logout
            </a>
        </nav>
    </div>

</aside>

<!-- Main dashboard -->
<main class="main-content">

    <!-- Top header -->
    <header class="topbar">

        <div class="page-heading">
            <h1>Customer Dashboard</h1>
            <p>Discover local services in one place.</p>
        </div>

        <div class="topbar-actions">
            <div class="location-badge" id="locationDisplay">
                📍 Detecting location...
            </div>

            <a href="profile.php" class="profile-button" aria-label="My Profile">
                <?php if (!empty($safe_profile_image)): ?>
                    <img
                        src="<?php echo $safe_profile_image; ?>"
                        alt="Profile"
                        class="topbar-avatar"
                    >
                <?php else: ?>
                    <div class="topbar-placeholder">
                        <?php echo htmlspecialchars(
                            strtoupper(substr($user_name, 0, 1)),
                            ENT_QUOTES,
                            "UTF-8"
                        ); ?>
                    </div>
                <?php endif; ?>
            </a>
        </div>

    </header>

    <!-- Welcome panel -->
    <section class="welcome-panel">
        <div>
            <h2>Welcome, <?php echo $safe_name; ?>! 👋</h2>
            <p>
                Find food, groceries and printing services
                near your location.
            </p>
        </div>

        <div class="welcome-icon">🌿</div>
    </section>

    <!-- Search section -->
    <section class="search-panel">

        <label for="serviceSearch">
            Find your favourite local service
        </label>

        <div class="search-row">

            <div class="search-input-wrap">
                <input
                    type="text"
                    id="serviceSearch"
                    class="search-input"
                    placeholder="Search food, restaurants, grocery, printing..."
                    autocomplete="off"
                >

                <div class="suggestions" id="searchSuggestions"></div>
            </div>

            <button
                type="button"
                class="search-button"
                id="searchButton"
            >
                Search
            </button>

        </div>

    </section>

    <!-- Service cards -->
    <section id="servicesSection">

        <div class="section-heading">
            <div>
                <h2>Explore Services</h2>
                <p>Everything you need, just a click away.</p>
            </div>
        </div>

        <div class="services">
    
        <article class="service-card">
    <div class="service-icon">🚚</div>
    <h3>Track My Orders</h3>
    <p>Check your order status and contact your assigned delivery partner.</p>
    <a href="track_delivery.php" class="service-link">
        Track Order →
    </a>
     </article>
            <article class="service-card">
                <div class="service-icon">🍽️</div>
                <h3>Food & Restaurants</h3>
                <p>Explore local restaurants and discover their menus.</p>
                <a href="restaurants.php" class="service-link">
                    Explore Restaurants →
                </a>
            </article>

            <article class="service-card">
                <div class="service-icon">🛒</div>
                <h3>Grocery</h3>
                <p>Shop everyday essentials from local grocery stores.</p>
                <a href="grocery.php" class="service-link">
                    Shop Groceries →
                </a>
            </article>

            <article class="service-card">
                <div class="service-icon">🖨️</div>
                <h3>Print & Photocopy</h3>
                <p>Upload documents and request printing or photocopying.</p>
                <a href="print_order.html" class="service-link">
                    Place Print Order →
                </a>
            </article>

            <article class="service-card">
                <div class="service-icon">⭐</div>
                <h3>Customer Feedback</h3>
                <p>Share your experience and help us improve our services.</p>
                <a href="feedback.php" class="service-link">
                    Give Feedback →
                </a>
            </article>

        </div>

    </section>

    <footer class="footer-note">
        © <?php echo date("Y"); ?> Local Discover & Deliver
    </footer>

</main>

<script>
// Search suggestions for available LDD services.
const searchInput = document.getElementById("serviceSearch");
const suggestionsBox = document.getElementById("searchSuggestions");
const searchButton = document.getElementById("searchButton");

const searchOptions = [
    { label: "Food", keywords: ["food", "restaurant", "restaurants"], url: "food.php" },
    { label: "Grocery", keywords: ["grocery", "groceries", "supermarket"], url: "grocery.php" },
    { label: "Print & Photocopy", keywords: ["print", "printing", "photocopy"], url: "print_order.html" },
    { label: "Customer Feedback", keywords: ["feedback", "review"], url: "feedback.php" }
];

function getMatchingOptions(value) {
    const query = value.trim().toLowerCase();

    if (!query) {
        return [];
    }

    return searchOptions.filter(option =>
        option.label.toLowerCase().includes(query) ||
        option.keywords.some(keyword => keyword.includes(query))
    );
}

function displaySuggestions() {
    const matches = getMatchingOptions(searchInput.value);

    suggestionsBox.innerHTML = "";

    if (matches.length === 0) {
        suggestionsBox.style.display = "none";
        return;
    }

    matches.forEach(option => {
        const button = document.createElement("button");
        button.type = "button";
        button.textContent = option.label;

        button.addEventListener("click", () => {
            window.location.href = option.url;
        });

        suggestionsBox.appendChild(button);
    });

    suggestionsBox.style.display = "block";
}

function performSearch() {
    const query = searchInput.value.trim().toLowerCase();

    if (!query) {
        searchInput.focus();
        return;
    }

    if (query.includes("feedback") || query.includes("review")) {
        window.location.href = "feedback.php";
    } else if (
        query.includes("grocery") ||
        query.includes("groceries") ||
        query.includes("supermarket")
    ) {
        window.location.href = "grocery.php";
    } else if (
        query.includes("print") ||
        query.includes("photocopy")
    ) {
        window.location.href = "print_order.html";
    } else if (
        query.includes("food") ||
        query.includes("restaurant")
    ) {
        window.location.href = "food.php";
    } else if (
        query.includes("service")
    ) {
        document.getElementById("servicesSection").scrollIntoView({
            behavior: "smooth"
        });
    } else {
        displaySuggestions();
    }
}

searchInput.addEventListener("input", displaySuggestions);
searchButton.addEventListener("click", performSearch);

searchInput.addEventListener("keydown", function(event) {
    if (event.key === "Enter") {
        event.preventDefault();
        performSearch();
    }

    if (event.key === "Escape") {
        suggestionsBox.style.display = "none";
    }
});

document.addEventListener("click", function(event) {
    if (!event.target.closest(".search-input-wrap")) {
        suggestionsBox.style.display = "none";
    }
});

// Detect the customer's location and display the nearby area.
const locationDisplay = document.getElementById("locationDisplay");

function showLocationFallback() {
    locationDisplay.textContent = "📍 Location unavailable";
}

if ("geolocation" in navigator) {
    navigator.geolocation.getCurrentPosition(
        async function(position) {
            const latitude = position.coords.latitude;
            const longitude = position.coords.longitude;

            try {
                const response = await fetch(
                    "https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=" +
                    encodeURIComponent(latitude) +
                    "&lon=" +
                    encodeURIComponent(longitude)
                );

                if (!response.ok) {
                    throw new Error("Location lookup failed");
                }

                const data = await response.json();
                const address = data.address || {};

                const area =
                    address.suburb ||
                    address.neighbourhood ||
                    address.village ||
                    address.town ||
                    address.city ||
                    address.county;

                locationDisplay.textContent = area
                    ? "📍 " + area
                    : "📍 Location detected";

            } catch (error) {
                showLocationFallback();
            }
        },
        function() {
            showLocationFallback();
        },
        {
            enableHighAccuracy: false,
            timeout: 10000,
            maximumAge: 300000
        }
    );
} else {
    showLocationFallback();
}
</script>

</body>
</html>