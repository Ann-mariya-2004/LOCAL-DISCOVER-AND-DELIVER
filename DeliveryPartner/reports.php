<?php
session_start();
require_once "../backend/db.php";

/* Delivery Partner Reports - Local Discover and Deliver */

if (
    !isset($_SESSION["user_id"]) ||
    strcasecmp(trim($_SESSION["role"] ?? ""), "Delivery Partner") !== 0
) {
    header("Location: ../login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

/* Validate date filters */
$from_date = $_GET["from_date"] ?? "";
$to_date = $_GET["to_date"] ?? "";

if ($from_date !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from_date)) {
    $from_date = "";
}

if ($to_date !== "" && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to_date)) {
    $to_date = "";
}

if ($from_date !== "" && $to_date !== "" && $from_date > $to_date) {
    $temp = $from_date;
    $from_date = $to_date;
    $to_date = $temp;
}

/* Prepare the date filter for order reports */
$date_condition = "";
$params = [$user_id];
$types = "i";

if ($from_date !== "") {
    $date_condition .= " AND DATE(fo.order_date) >= ?";
    $params[] = $from_date;
    $types .= "s";
}

if ($to_date !== "") {
    $date_condition .= " AND DATE(fo.order_date) <= ?";
    $params[] = $to_date;
    $types .= "s";
}

/* Fetch summary statistics for this delivery partner */
$stats_sql = "
    SELECT
        COUNT(*) AS total_deliveries,
        SUM(
            CASE
                WHEN LOWER(COALESCE(fo.delivery_status, '')) IN ('completed', 'delivered')
                THEN 1 ELSE 0
            END
        ) AS completed_deliveries,
        SUM(
            CASE
                WHEN LOWER(COALESCE(fo.delivery_status, '')) IN
                    ('pending', 'assigned', 'accepted', 'picked up', 'on the way', 'in progress')
                THEN 1 ELSE 0
            END
        ) AS active_deliveries,
        COALESCE(
            SUM(
                CASE
                    WHEN LOWER(COALESCE(fo.delivery_status, '')) IN ('completed', 'delivered')
                    THEN COALESCE(fo.delivery_fee, 0)
                    ELSE 0
                END
            ), 0
        ) AS total_earnings
    FROM food_orders fo
    WHERE fo.delivery_partner_id = ?
    $date_condition
";

$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param($types, ...$params);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();
$stats_stmt->close();

$total_deliveries = (int)($stats["total_deliveries"] ?? 0);
$completed_deliveries = (int)($stats["completed_deliveries"] ?? 0);
$active_deliveries = (int)($stats["active_deliveries"] ?? 0);
$total_earnings = (float)($stats["total_earnings"] ?? 0);

/* Fetch delivery report details */
$report_sql = "
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
    $date_condition
    ORDER BY fo.order_date DESC, fo.id DESC
";

$report_stmt = $conn->prepare($report_sql);
$report_stmt->bind_param($types, ...$params);
$report_stmt->execute();
$report_result = $report_stmt->get_result();

$reports = [];

while ($row = $report_result->fetch_assoc()) {
    $reports[] = $row;
}

$report_stmt->close();

$current_page = basename($_SERVER["PHP_SELF"]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | Delivery Reports</title>

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
    color: var(--muted);
    font-size: 12px;
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

/* Smaller statistics cards */
.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
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
    overflow-wrap: anywhere;
}

.stat-note {
    margin-top: 5px;
    color: var(--muted);
    font-size: 10px;
}

/* Compact report panel */
.panel {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 9px;
    overflow: hidden;
    margin-bottom: 12px;
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
    color: var(--muted);
    font-size: 11px;
    margin-top: 4px;
}

/* Compact filters */
.filter-form {
    display: flex;
    align-items: end;
    gap: 9px;
    flex-wrap: wrap;
    padding: 12px 16px;
    border-bottom: 1px solid var(--border);
}

.form-group {
    display: flex;
    flex-direction: column;
    gap: 5px;
}

.form-group label {
    font-size: 11px;
    font-weight: 600;
    color: #526763;
}

.form-group input {
    width: 155px;
    padding: 8px 10px;
    border: 1px solid #dce7e5;
    border-radius: 6px;
    outline: none;
    font-family: inherit;
    font-size: 12px;
}

.form-group input:focus {
    border-color: var(--secondary);
}

/* Compact table */
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

.status {
    display: inline-block;
    padding: 4px 8px;
    border-radius: 20px;
    background: #fff4dc;
    color: #986500;
    font-size: 10px;
    font-weight: 600;
    text-transform: capitalize;
}

.status.completed,
.status.delivered {
    background: #e0f7e9;
    color: #168044;
}

.status.cancelled,
.status.rejected {
    background: #fee8e7;
    color: #bb3434;
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
    font-size: 15px;
    color: var(--text);
    margin-bottom: 5px;
}

.empty p {
    font-size: 12px;
}

.footer-note {
    padding: 10px 16px;
    border-top: 1px solid var(--border);
    font-size: 11px;
    color: var(--muted);
}

/* Tablet */
@media (max-width: 1050px) {
    .sidebar {
        width: 185px;
    }

    .main {
        padding: 15px;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
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

    .form-group input {
        width: 100%;
    }

    .filter-form {
        gap: 8px;
    }
}

/* Print report without navigation */
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
        box-shadow: none;
        border: 1px solid #ccc;
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
                <h1>Delivery Reports</h1>
                <p>Track your delivery performance and earnings.</p>
            </div>

            <button type="button" class="btn no-print" onclick="window.print()">
                🖨️ Print Report
            </button>
        </header>

        <section class="stats">
            <div class="stat-card">
                <div class="stat-label">Total Assigned Deliveries</div>
                <div class="stat-value"><?= $total_deliveries ?></div>
                <div class="stat-note">Within selected date range</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Completed Deliveries</div>
                <div class="stat-value"><?= $completed_deliveries ?></div>
                <div class="stat-note">Completed or delivered orders</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Active Deliveries</div>
                <div class="stat-value"><?= $active_deliveries ?></div>
                <div class="stat-note">Orders currently in progress</div>
            </div>

            <div class="stat-card">
                <div class="stat-label">Delivery Earnings</div>
                <div class="stat-value">₹<?= number_format($total_earnings, 2) ?></div>
                <div class="stat-note">Fees from completed deliveries</div>
            </div>
        </section>

        <section class="panel">
            <div class="panel-header">
                <div>
                    <h2>Delivery Report Details</h2>
                    <p>Filter records by order date and print the report.</p>
                </div>

                <span class="btn btn-light">
                    <?= count($reports) ?> record(s)
                </span>
            </div>

            <form method="GET" class="filter-form no-print">
                <div class="form-group">
                    <label for="from_date">From Date</label>
                    <input
                        type="date"
                        id="from_date"
                        name="from_date"
                        value="<?= e($from_date) ?>"
                    >
                </div>

                <div class="form-group">
                    <label for="to_date">To Date</label>
                    <input
                        type="date"
                        id="to_date"
                        name="to_date"
                        value="<?= e($to_date) ?>"
                    >
                </div>

                <button type="submit" class="btn">Apply Filter</button>
                <a href="reports.php" class="btn btn-light">Reset</a>
            </form>

            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Restaurant</th>
                            <th>Food Item</th>
                            <th>Quantity</th>
                            <th>Order Amount</th>
                            <th>Delivery Fee</th>
                            <th>Order Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                    <?php if (count($reports) > 0): ?>
                        <?php foreach ($reports as $item): ?>
                            <?php
                            $status = strtolower(trim((string)($item["delivery_status"] ?? "")));
                            $status_label = $status !== "" ? ucfirst($status) : "Unknown";
                            ?>
                            <tr>
                                <td class="order-id">#<?= (int)$item["id"] ?></td>

                                <td>
                                    <?= e($item["restaurant_name"] ?: "Restaurant") ?>
                                </td>

                                <td><?= e($item["food_name"] ?: "Food Order") ?></td>

                                <td><?= (int)($item["quantity"] ?? 0) ?></td>

                                <td>
                                    ₹<?= number_format((float)($item["total"] ?? 0), 2) ?>
                                </td>

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
                                    <span class="status <?= e($status) ?>">
                                        <?= e($status_label) ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty">
                                    <div class="empty-icon">📊</div>
                                    <h3>No Reports Found</h3>
                                    <p>Assigned deliveries will appear here when records match your account and selected dates.</p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="footer-note">
                This report displays deliveries assigned to your account only.
            </div>
        </section>

    </main>
</div>
</body>
</html>