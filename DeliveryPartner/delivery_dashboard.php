<?php
session_start();
require_once "../backend/db.php";

/* 1. LOGIN AND ROLE VALIDATION */

if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$name = $_SESSION["name"] ?? "Delivery Partner";
$role = trim($_SESSION["role"] ?? "");

if (strcasecmp($role, "Delivery Partner") !== 0) {
    header("Location: ../dashboard.php");
    exit();
}

if (!isset($conn) || !$conn) {
    die("Database connection failed. Check backend/db.php.");
}

/* 2. HELPER FUNCTIONS */

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function columnExists($conn, $table, $column) {
    $allowedTables = ["users", "food_orders"];

    if (!in_array($table, $allowedTables, true)) {
        return false;
    }

    $column = mysqli_real_escape_string($conn, $column);

    $result = mysqli_query(
        $conn,
        "SHOW COLUMNS FROM `$table` LIKE '$column'"
    );

    return $result && mysqli_num_rows($result) > 0;
}

function getCount($conn, $sql, $user_id = null) {
    if ($user_id !== null) {
        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            return 0;
        }

        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $count);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        return (int)($count ?? 0);
    }

    $result = mysqli_query($conn, $sql);

    if (!$result) {
        return 0;
    }

    $row = mysqli_fetch_row($result);

    return (int)($row[0] ?? 0);
}

/* 3. PROFILE IMAGE */

$profile_image = "../assets/default-profile.png";

if (columnExists($conn, "users", "profile_image")) {
    $stmt = mysqli_prepare(
        $conn,
        "SELECT profile_image FROM users WHERE id = ? LIMIT 1"
    );

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_bind_result($stmt, $db_image);
        mysqli_stmt_fetch($stmt);
        mysqli_stmt_close($stmt);

        if (!empty($db_image)) {
            $db_image = trim($db_image);

            if (
                preg_match('/^https?:\/\//i', $db_image) ||
                str_starts_with($db_image, "/") ||
                str_starts_with($db_image, "../")
            ) {
                $profile_image = $db_image;
            } else {
                $profile_image = "../" . ltrim($db_image, "/");
            }
        }
    }
}

/* 4. DASHBOARD STATISTICS */

$available_count = 0;
$active_count = 0;
$completed_count = 0;
$total_earnings = 0.00;

$hasPartner = columnExists(
    $conn,
    "food_orders",
    "delivery_partner_id"
);

$hasStatus = columnExists(
    $conn,
    "food_orders",
    "delivery_status"
);

$hasFee = columnExists(
    $conn,
    "food_orders",
    "delivery_fee"
);

$hasFoodName = columnExists(
    $conn,
    "food_orders",
    "food_name"
);

$hasOrderDate = columnExists(
    $conn,
    "food_orders",
    "order_date"
);

/* Count available, paid orders */

if ($hasPartner && $hasStatus) {
    $available_count = getCount(
        $conn,
        "SELECT COUNT(*)
         FROM food_orders fo
         WHERE fo.delivery_partner_id IS NULL
         AND fo.delivery_status = 'Available'
         AND EXISTS (
             SELECT 1
             FROM payments p
             WHERE p.order_id = fo.id
             AND p.payment_status = 'Paid'
         )"
    );

    /* Count active deliveries assigned to this partner */

    $active_count = getCount(
        $conn,
        "SELECT COUNT(*)
         FROM food_orders
         WHERE delivery_partner_id = ?
         AND LOWER(COALESCE(delivery_status, ''))
             NOT IN ('completed', 'delivered', 'cancelled')",
        $user_id
    );

    /* Count completed deliveries */

    $completed_count = getCount(
        $conn,
        "SELECT COUNT(*)
         FROM food_orders
         WHERE delivery_partner_id = ?
         AND LOWER(COALESCE(delivery_status, ''))
             IN ('completed', 'delivered')",
        $user_id
    );

    /* Calculate completed delivery fees */

    if ($hasFee) {
        $stmt = mysqli_prepare(
            $conn,
            "SELECT COALESCE(SUM(delivery_fee), 0)
             FROM food_orders
             WHERE delivery_partner_id = ?
             AND LOWER(COALESCE(delivery_status, ''))
                 IN ('completed', 'delivered')"
        );

        if ($stmt) {
            mysqli_stmt_bind_param($stmt, "i", $user_id);
            mysqli_stmt_execute($stmt);
            mysqli_stmt_bind_result($stmt, $earnings);
            mysqli_stmt_fetch($stmt);

            $total_earnings = (float)($earnings ?? 0);

            mysqli_stmt_close($stmt);
        }
    }
}

/* 5. FETCH LATEST ASSIGNED DELIVERIES */

$recent_deliveries = [];

if ($hasPartner && $hasStatus && $hasFoodName) {
    $dateColumn = $hasOrderDate ? "order_date" : "id";

    $sql = "
        SELECT id, food_name, delivery_status, `$dateColumn` AS display_date
        FROM food_orders
        WHERE delivery_partner_id = ?
        ORDER BY id DESC
        LIMIT 3
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if ($stmt) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        mysqli_stmt_execute($stmt);

        $result = mysqli_stmt_get_result($stmt);

        if ($result) {
            while ($row = mysqli_fetch_assoc($result)) {
                $recent_deliveries[] = $row;
            }
        }

        mysqli_stmt_close($stmt);
    }
}

/* 6. CURRENT PAGE */

$current_page = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Delivery Partner Dashboard | LDD</title>

<style>
:root {
    --primary: #0f766e;
    --accent: #14b8a6;
    --light: #ccfbf1;
    --background: #f5f7f7;
    --text: #1f2937;
    --muted: #6b7280;
    --border: #e5e7eb;
    --white: #ffffff;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
    font-family: Arial, Helvetica, sans-serif;
}

body {
    background: var(--background);
    color: var(--text);
}

/* TOP NAVIGATION */

.navbar {
    height: 64px;
    background: var(--primary);
    color: white;
    position: fixed;
    inset: 0 0 auto 0;
    z-index: 1000;
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    box-shadow: 0 2px 8px rgba(0,0,0,.10);
}

.logo {
    font-size: 20px;
    font-weight: 700;
}

.nav-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-name {
    font-size: 13px;
    font-weight: 600;
}

.profile-img {
    width: 37px;
    height: 37px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid white;
}

.logout {
    background: white;
    color: var(--primary);
    padding: 9px 13px;
    border-radius: 6px;
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
}

.logout:hover {
    background: var(--light);
}

/* SIDEBAR */

.sidebar {
    width: 220px;
    position: fixed;
    top: 64px;
    bottom: 0;
    left: 0;
    background: white;
    padding: 20px 13px;
    box-shadow: 2px 0 8px rgba(0,0,0,.04);
    overflow-y: auto;
}

.sidebar h3 {
    color: var(--primary);
    font-size: 16px;
    margin: 0 0 17px 10px;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 11px;
    padding: 12px 11px;
    margin-bottom: 5px;
    border-radius: 7px;
    color: #4b5563;
    text-decoration: none;
    font-size: 13px;
    transition: background .2s;
}

.sidebar a:hover,
.sidebar a.active {
    background: var(--light);
    color: var(--primary);
}

.sidebar a.active {
    font-weight: bold;
}

.sidebar-icon {
    width: 23px;
    flex-shrink: 0;
    text-align: center;
    font-size: 17px;
}

/* MAIN AREA */

.main {
    margin-left: 220px;
    padding: 82px 24px 22px;
}

.welcome {
    background: linear-gradient(120deg, var(--primary), var(--accent));
    color: white;
    padding: 19px 23px;
    border-radius: 10px;
    margin-bottom: 15px;
}

.welcome h1 {
    font-size: 23px;
    margin-bottom: 6px;
}

.welcome p {
    font-size: 13px;
    line-height: 1.6;
}

/* STATISTICS */

.cards {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 15px;
}

.card {
    display: flex;
    align-items: center;
    gap: 11px;
    min-width: 0;
    padding: 14px;
    background: white;
    border: 1px solid #edf0f0;
    border-radius: 10px;
    box-shadow: 0 2px 7px rgba(0,0,0,.035);
}

.card-icon {
    width: 40px;
    height: 40px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 9px;
    background: var(--light);
    color: var(--primary);
    font-size: 19px;
}

.card-info {
    min-width: 0;
}

.card-info h3 {
    font-size: 20px;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.card-info p {
    font-size: 11px;
    line-height: 1.5;
    color: var(--muted);
}

/* DASHBOARD CONTENT */

.content-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(0, 1fr);
    gap: 15px;
    align-items: start;
}

.section {
    min-width: 0;
    background: white;
    border: 1px solid #edf0f0;
    border-radius: 10px;
    padding: 17px;
    box-shadow: 0 2px 7px rgba(0,0,0,.035);
}

.section-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 13px;
}

.section-header h2 {
    color: var(--primary);
    font-size: 16px;
}

.view-all {
    color: var(--primary);
    font-size: 12px;
    font-weight: bold;
    text-decoration: none;
    white-space: nowrap;
}

.view-all:hover {
    text-decoration: underline;
}

/* AVAILABLE DELIVERIES */

.available-box {
    text-align: center;
    padding: 20px 13px;
    background: #f9fdfc;
    border: 1px dashed #b8dcd7;
    border-radius: 8px;
}

.available-icon {
    font-size: 29px;
    margin-bottom: 8px;
}

.available-box h3 {
    font-size: 15px;
    margin-bottom: 7px;
}

.available-box p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
    margin-bottom: 13px;
}

.primary-btn {
    display: inline-block;
    padding: 10px 14px;
    background: var(--primary);
    color: white;
    text-decoration: none;
    border-radius: 6px;
    font-size: 12px;
    font-weight: bold;
}

.primary-btn:hover {
    background: #115e59;
}

/* RECENT DELIVERIES */

.delivery-list {
    display: grid;
    gap: 9px;
}

.delivery-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    padding: 11px;
    border: 1px solid var(--border);
    border-radius: 8px;
}

.delivery-details {
    min-width: 0;
}

.delivery-details strong {
    display: block;
    font-size: 13px;
    margin-bottom: 5px;
    overflow-wrap: anywhere;
}

.delivery-details span {
    color: var(--muted);
    font-size: 11px;
}

.status {
    display: inline-block;
    flex-shrink: 0;
    padding: 5px 8px;
    border-radius: 20px;
    background: #fef3c7;
    color: #92400e;
    font-size: 10px;
    font-weight: bold;
    text-align: center;
}

.status.delivered,
.status.completed {
    background: #d1fae5;
    color: #065f46;
}

.status.picked-up,
.status.on-the-way {
    background: #dbeafe;
    color: #1d4ed8;
}

.no-data {
    padding: 23px 12px;
    text-align: center;
    background: #f9fafb;
    border-radius: 8px;
    color: var(--muted);
    font-size: 12px;
    line-height: 1.7;
}

/* TABLET */

@media (max-width: 1050px) {
    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .content-grid {
        grid-template-columns: 1fr;
    }
}

/* MOBILE */

@media (max-width: 650px) {
    .navbar {
        padding: 0 11px;
    }

    .logo {
        font-size: 14px;
    }

    .nav-right {
        gap: 7px;
    }

    .user-name {
        display: none;
    }

    .profile-img {
        width: 33px;
        height: 33px;
    }

    .logout {
        padding: 8px 9px;
        font-size: 11px;
    }

    .sidebar {
        width: 59px;
        padding: 17px 6px;
    }

    .sidebar h3,
    .sidebar-text {
        display: none;
    }

    .sidebar a {
        justify-content: center;
        padding: 12px 4px;
    }

    .sidebar-icon {
        font-size: 18px;
    }

    .main {
        margin-left: 59px;
        padding: 78px 10px 18px;
    }

    .welcome {
        padding: 16px;
    }

    .welcome h1 {
        font-size: 19px;
    }

    .welcome p {
        font-size: 12px;
    }

    .cards {
        gap: 8px;
    }

    .card {
        gap: 7px;
        padding: 10px 8px;
    }

    .card-icon {
        width: 32px;
        height: 32px;
        font-size: 16px;
    }

    .card-info h3 {
        font-size: 17px;
    }

    .card-info p {
        font-size: 10px;
    }

    .section {
        padding: 13px;
    }
}

@media (max-width: 380px) {
    .cards {
        grid-template-columns: 1fr;
    }
}
</style>
</head>

<body>

<!-- TOP NAVIGATION -->
<header class="navbar">
    <div class="logo">Local Discover &amp; Deliver</div>

    <div class="nav-right">
        <span class="user-name"><?= e($name) ?></span>

        <img
            src="<?= e($profile_image) ?>"
            alt="Profile"
            class="profile-img"
            onerror="this.onerror=null;this.src='../assets/default-profile.png';"
        >

        <a href="../backend/logout.php" class="logout">Logout</a>
    </div>
</header>

<!-- SIDEBAR -->
<aside class="sidebar">
    <h3>Delivery Panel</h3>

    <a
        href="delivery_dashboard.php"
        class="<?= $current_page === 'delivery_dashboard.php' ? 'active' : '' ?>"
        title="Dashboard"
    >
        <span class="sidebar-icon">🏠</span>
        <span class="sidebar-text">Dashboard</span>
    </a>

    <a
        href="available_orders.php"
        class="<?= $current_page === 'available_orders.php' ? 'active' : '' ?>"
        title="Available Deliveries"
    >
        <span class="sidebar-icon">📦</span>
        <span class="sidebar-text">Available Deliveries</span>
    </a>

    <a
        href="my_deliveries.php"
        class="<?= $current_page === 'my_deliveries.php' ? 'active' : '' ?>"
        title="My Deliveries"
    >
        <span class="sidebar-icon">🚚</span>
        <span class="sidebar-text">My Deliveries</span>
    </a>

    <a
        href="delivery_history.php"
        class="<?= $current_page === 'delivery_history.php' ? 'active' : '' ?>"
        title="Delivery History"
    >
        <span class="sidebar-icon">📋</span>
        <span class="sidebar-text">Delivery History</span>
    </a>
        <a
        href="earnings.php"
        class="<?= $current_page === 'earnings.php' ? 'active' : '' ?>"
        title="Earnings"
    >
        <span class="sidebar-icon">💰</span>
        <span class="sidebar-text">Earnings</span>
    </a>

    <a
        href="reports.php"
        class="<?= $current_page === 'reports.php' ? 'active' : '' ?>"
        title="Reports"
    >
        <span class="sidebar-icon">📊</span>
        <span class="sidebar-text">Reports</span>
    </a>
    <a
        href="profile.php"
        class="<?= $current_page === 'profile.php' ? 'active' : '' ?>"
        title="My Profile"
    >
        <span class="sidebar-icon">👤</span>
        <span class="sidebar-text">My Profile</span>
    </a>
</aside>

<!-- MAIN DASHBOARD -->
<main class="main">

    <!-- WELCOME -->
    <section class="welcome">
        <h1>Welcome, <?= e($name) ?>!</h1>
        <p>
            Manage your deliveries and track your delivery activity.
        </p>
    </section>

    <!-- STATISTICS -->
    <section class="cards">

        <div class="card">
            <div class="card-icon">📦</div>
            <div class="card-info">
                <h3><?= $available_count ?></h3>
                <p>Available Deliveries</p>
            </div>
        </div>

        <div class="card">
            <div class="card-icon">🚚</div>
            <div class="card-info">
                <h3><?= $active_count ?></h3>
                <p>My Active Deliveries</p>
            </div>
        </div>

        <div class="card">
            <div class="card-icon">✓</div>
            <div class="card-info">
                <h3><?= $completed_count ?></h3>
                <p>Completed Deliveries</p>
            </div>
        </div>

        <div class="card">
            <div class="card-icon">₹</div>
            <div class="card-info">
                <h3>₹<?= number_format($total_earnings, 2) ?></h3>
                <p>Completed Delivery Fees</p>
            </div>
        </div>

    </section>

    <!-- AVAILABLE AND RECENT DELIVERIES -->
    <div class="content-grid">

        <!-- AVAILABLE DELIVERIES -->
        <section class="section">
            <div class="section-header">
                <h2>Available Deliveries</h2>

                <a href="available_orders.php" class="view-all">
                    View All →
                </a>
            </div>

            <div class="available-box">
                <div class="available-icon">📦</div>

                <h3>Ready for Your Next Delivery?</h3>

                <p>
                    View available orders and accept a delivery
                    that is waiting for a partner.
                </p>

                <a href="available_orders.php" class="primary-btn">
                    View Available Orders
                </a>
            </div>
        </section>

        <!-- RECENT DELIVERIES -->
        <section class="section">
            <div class="section-header">
                <h2>My Recent Deliveries</h2>

                <a href="my_deliveries.php" class="view-all">
                    View All →
                </a>
            </div>

            <?php if (!empty($recent_deliveries)): ?>

                <div class="delivery-list">

                    <?php foreach ($recent_deliveries as $delivery): ?>

                        <?php
                        $status = trim(
                            (string)($delivery["delivery_status"] ?? "Assigned")
                        );

                        $status_class = strtolower(
                            str_replace(" ", "-", $status)
                        );
                        ?>

                        <div class="delivery-item">

                            <div class="delivery-details">
                                <strong>
                                    Order #<?= (int)$delivery["id"] ?>
                                    — <?= e($delivery["food_name"]) ?>
                                </strong>

                                <span>
                                    <?php
                                    if (
                                        $hasOrderDate &&
                                        !empty($delivery["display_date"]) &&
                                        strtotime($delivery["display_date"]) !== false
                                    ) {
                                        echo e(
                                            date(
                                                "d M Y",
                                                strtotime($delivery["display_date"])
                                            )
                                        );
                                    } else {
                                        echo "Order details";
                                    }
                                    ?>
                                </span>
                            </div>

                            <span class="status <?= e($status_class) ?>">
                                <?= e($status) ?>
                            </span>

                        </div>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="no-data">
                    <p>No assigned deliveries yet.</p>
                    <p>Accept an available order to see it here.</p>
                </div>

            <?php endif; ?>

        </section>

    </div>

</main>

</body>
</html>