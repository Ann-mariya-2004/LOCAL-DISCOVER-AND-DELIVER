<?php
session_start();
require_once "../backend/db.php";

/* Delivery History - Local Discover and Deliver */

if (!isset($_SESSION["user_id"]) || ($_SESSION["role"] ?? "") !== "Delivery Partner") {
    header("Location: ../login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* Search filters */
$search = trim($_GET["search"] ?? "");
$status_filter = trim($_GET["status"] ?? "");

/* Get delivery partner information */
$partner_name = "Delivery Partner";

$user_stmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
$user_stmt->bind_param("i", $user_id);
$user_stmt->execute();
$user_data = $user_stmt->get_result()->fetch_assoc();
$user_stmt->close();

if (!empty($user_data["name"])) {
    $partner_name = $user_data["name"];
}

/* Fetch completed delivery history */
$sql = "
    SELECT
        fo.id,
        fo.food_name,
        fo.quantity,
        fo.total,
        fo.delivery_fee,
        fo.order_date,
        fo.delivery_status,
        r.restaurant_name
    FROM food_orders fo
    LEFT JOIN restaurants r ON r.id = fo.restaurant_id
    WHERE fo.delivery_partner_id = ?
      AND LOWER(COALESCE(fo.delivery_status, '')) IN ('completed', 'delivered')
";

$params = [$user_id];
$types = "i";

/* Search by order ID, food name, or restaurant */
if ($search !== "") {
    $sql .= " AND (
        CAST(fo.id AS CHAR) LIKE ?
        OR COALESCE(fo.food_name, '') LIKE ?
        OR COALESCE(r.restaurant_name, '') LIKE ?
    )";

    $search_value = "%" . $search . "%";

    $params[] = $search_value;
    $params[] = $search_value;
    $params[] = $search_value;
    $types .= "sss";
}

/* Optional status filter */
if ($status_filter !== "") {
    $sql .= " AND LOWER(COALESCE(fo.delivery_status, '')) = ?";
    $params[] = strtolower($status_filter);
    $types .= "s";
}

$sql .= " ORDER BY fo.order_date DESC, fo.id DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$result = $stmt->get_result();

$history = [];

while ($row = $result->fetch_assoc()) {
    $history[] = $row;
}

$stmt->close();

/* Summary statistics */
$total_completed = count($history);
$total_earnings = 0;

foreach ($history as $item) {
    $total_earnings += (float)($item["delivery_fee"] ?? 0);
}

$current_page = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | Delivery History</title>

<style>
:root {
    --primary: #0f766e;
    --secondary: #14b8a6;
    --background: #f4f8f8;
    --text: #183b39;
    --muted: #718481;
    --border: #e3ecea;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: var(--background);
    color: var(--text);
    font-size: 13px;
}

.layout {
    display: flex;
    min-height: 100vh;
}

/* Compact sidebar */
.sidebar {
    width: 205px;
    flex-shrink: 0;
    background: #fff;
    border-right: 1px solid var(--border);
    padding: 16px 10px;
}

.brand {
    padding: 0 10px 15px;
    border-bottom: 1px solid var(--border);
    margin-bottom: 12px;
}

.brand h2 {
    font-size: 20px;
    color: var(--primary);
}

.brand p {
    margin-top: 4px;
    font-size: 11px;
    color: var(--muted);
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 9px;
    padding: 10px;
    margin-bottom: 3px;
    border-radius: 7px;
    text-decoration: none;
    color: #526763;
    font-size: 12px;
    transition: .2s;
}

.sidebar a:hover,
.sidebar a.active {
    background: #e7f7f4;
    color: var(--primary);
    font-weight: 600;
}

.sidebar-icon {
    width: 20px;
    text-align: center;
}

/* Compact main area */
.main {
    flex: 1;
    min-width: 0;
    padding: 20px;
}

.topbar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
}

.topbar h1 {
    font-size: 22px;
    margin-bottom: 4px;
}

.topbar p {
    font-size: 12px;
    color: var(--muted);
}

.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
    padding: 8px 12px;
    border: none;
    border-radius: 6px;
    background: var(--primary);
    color: #fff;
    text-decoration: none;
    font-size: 12px;
    cursor: pointer;
}

.btn:hover {
    background: #0b5f59;
}

.btn-light {
    background: #e7f7f4;
    color: var(--primary);
}

.btn-light:hover {
    background: #d5efea;
}

/* Smaller summary cards */
.stats {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.stat-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 14px;
}

.stat-label {
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 8px;
}

.stat-value {
    font-size: 22px;
    font-weight: 700;
    color: var(--primary);
}

/* Compact report panel */
.panel {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 9px;
    overflow: hidden;
}

.panel-header {
    padding: 13px 16px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.panel-header h2 {
    font-size: 15px;
}

.panel-header p {
    font-size: 11px;
    color: var(--muted);
    margin-top: 4px;
}

/* Compact filters */
.filter-form {
    display: flex;
    align-items: center;
    gap: 9px;
    flex-wrap: wrap;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
}

.filter-form input,
.filter-form select {
    min-width: 140px;
    padding: 8px 10px;
    border: 1px solid #dce7e5;
    border-radius: 6px;
    font-size: 12px;
    outline: none;
    background: #fff;
}

.filter-form input:focus,
.filter-form select:focus {
    border-color: var(--secondary);
}

/* Compact delivery table */
.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

th,
td {
    padding: 10px 12px;
    text-align: left;
    border-bottom: 1px solid #edf2f1;
    font-size: 12px;
}

th {
    background: #f7faf9;
    color: #647975;
    font-size: 10px;
    text-transform: uppercase;
    letter-spacing: .3px;
}

tbody tr:hover {
    background: #fbfdfd;
}

tbody tr:last-child td {
    border-bottom: none;
}

.order-id {
    color: var(--primary);
    font-weight: 700;
}

.restaurant-name {
    font-weight: 600;
}

.subtext {
    display: block;
    color: var(--muted);
    font-size: 10px;
    margin-top: 3px;
}

.status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
    background: #e0f7e9;
    color: #168044;
}

.empty {
    padding: 25px 15px;
    text-align: center;
    color: var(--muted);
}

.empty-icon {
    font-size: 26px;
    margin-bottom: 7px;
}

.empty h3 {
    color: var(--text);
    font-size: 15px;
    margin-bottom: 5px;
}

.empty p {
    font-size: 12px;
}

.footer-note {
    padding: 10px 16px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    font-size: 11px;
}

/* Tablet */
@media (max-width: 1000px) {
    .sidebar {
        width: 185px;
    }

    .main {
        padding: 15px;
    }
}

/* Mobile */
@media (max-width: 700px) {
    .layout {
        display: block;
    }

    .sidebar {
        width: 100%;
        padding: 10px;
        border-right: none;
        border-bottom: 1px solid var(--border);
    }

    .brand {
        padding-bottom: 10px;
        margin-bottom: 8px;
    }

    .sidebar a {
        display: inline-flex;
        padding: 8px;
        margin: 2px;
        font-size: 11px;
    }

    .sidebar-icon {
        width: auto;
    }

    .main {
        padding: 12px;
    }

    .stats {
        gap: 8px;
    }

    .stat-card {
        padding: 12px;
    }

    .stat-value {
        font-size: 19px;
    }

    .filter-form {
        gap: 8px;
    }

    .filter-form input,
    .filter-form select {
        min-width: 0;
        max-width: 100%;
    }
}

/* Print-friendly history */
@media print {
    body {
        background: #fff;
    }

    .sidebar,
    .filter-form,
    .no-print {
        display: none !important;
    }

    .layout {
        display: block;
    }

    .main {
        padding: 0;
    }

    .panel,
    .stat-card {
        border: 1px solid #ccc;
        box-shadow: none;
    }

    .table-wrapper {
        overflow: visible;
    }

    table {
        white-space: normal;
    }

    th,
    td {
        padding: 7px;
        font-size: 10px;
    }
}
</style>
</head>

<body>
<div class="layout">

    <aside class="sidebar">
        <div class="brand">
            <h2>LDD</h2>
            <p>Delivery Partner Panel</p>
        </div>

        <a href="delivery_dashboard.php"
           class="<?= $current_page === 'delivery_dashboard.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🏠</span>
            <span>Dashboard</span>
        </a>

        <a href="available_orders.php"
           class="<?= $current_page === 'available_orders.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📦</span>
            <span>Available Deliveries</span>
        </a>

        <a href="my_deliveries.php"
           class="<?= $current_page === 'my_deliveries.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">🚚</span>
            <span>My Deliveries</span>
        </a>

        <a href="delivery_history.php"
           class="<?= $current_page === 'delivery_history.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📋</span>
            <span>Delivery History</span>
        </a>

        <a href="earnings.php"
           class="<?= $current_page === 'earnings.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">💰</span>
            <span>Earnings</span>
        </a>

        <a href="reports.php"
           class="<?= $current_page === 'reports.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">📊</span>
            <span>Reports</span>
        </a>

        <a href="profile.php"
           class="<?= $current_page === 'profile.php' ? 'active' : '' ?>">
            <span class="sidebar-icon">👤</span>
            <span>My Profile</span>
        </a>

        <a href="logout.php">
            <span class="sidebar-icon">🚪</span>
            <span>Logout</span>
        </a>
    </aside>

    <main class="main">

        <header class="topbar">
            <div>
                <h1>Delivery History</h1>
                <p>Welcome, <?= e($partner_name) ?>. View your completed deliveries.</p>
            </div>

            <button type="button" class="btn no-print" onclick="window.print()">
                🖨️ Print History
            </button>
        </header>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-label">Completed Deliveries</div>
                <div class="stat-value"><?= $total_completed ?></div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Earnings From Listed Deliveries</div>
                <div class="stat-value">₹<?= number_format($total_earnings, 2) ?></div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Completed Delivery Records</h2>
                    <p>Search your completed and delivered orders.</p>
                </div>

                <span class="btn btn-light">
                    <?= count($history) ?> record(s)
                </span>
            </div>

            <form method="GET" class="filter-form no-print">
                <input
                    type="text"
                    name="search"
                    placeholder="Search order, food or restaurant"
                    value="<?= e($search) ?>"
                >

                <select name="status">
                    <option value="">All Completed Statuses</option>
                    <option value="completed" <?= strtolower($status_filter) === 'completed' ? 'selected' : '' ?>>
                        Completed
                    </option>
                    <option value="delivered" <?= strtolower($status_filter) === 'delivered' ? 'selected' : '' ?>>
                        Delivered
                    </option>
                </select>

                <button type="submit" class="btn">Search</button>
                <a href="delivery_history.php" class="btn btn-light">Reset</a>
            </form>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Restaurant</th>
                            <th>Food Item</th>
                            <th>Quantity</th>
                            <th>Delivery Fee</th>
                            <th>Delivery Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (count($history) > 0): ?>
                        <?php foreach ($history as $item): ?>
                            <?php
                                $status = strtolower(trim((string)($item["delivery_status"] ?? "")));
                                $status_label = $status !== "" ? ucfirst($status) : "Completed";
                            ?>
                            <tr>
                                <td class="order-id">#<?= (int)$item["id"] ?></td>

                                <td class="restaurant-name">
                                    <?= e($item["restaurant_name"] ?: "Restaurant") ?>
                                </td>

                                <td>
                                    <?= e($item["food_name"] ?: "Food Order") ?>
                                </td>

                                <td><?= (int)($item["quantity"] ?? 0) ?></td>

                                <td>
                                    ₹<?= number_format((float)($item["delivery_fee"] ?? 0), 2) ?>
                                </td>

                                <td>
                                    <?php
                                    if (!empty($item["order_date"])) {
                                        echo e(date("d M Y, h:i A", strtotime($item["order_date"])));
                                    } else {
                                        echo "—";
                                    }
                                    ?>
                                </td>

                                <td>
                                    <span class="status"><?= e($status_label) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7">
                                <div class="empty">
                                    <div class="empty-icon">📦</div>
                                    <h3>No Delivery History Found</h3>
                                    <p>Completed deliveries will appear here after their status is updated.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-note">
                Delivery records shown here belong to your account only.
            </div>
        </section>

    </main>
</div>
</body>
</html>