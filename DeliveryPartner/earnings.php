<?php
session_start();
require_once "../backend/db.php";

/* Check Delivery Partner Login */
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "Delivery Partner"
) {
    header("Location: ../login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/* Get Delivery Partner Details */
$partnerQuery = mysqli_prepare(
    $conn,
    "SELECT id FROM delivery_partners WHERE user_id = ? LIMIT 1"
);
mysqli_stmt_bind_param($partnerQuery, "i", $user_id);
mysqli_stmt_execute($partnerQuery);
$partnerResult = mysqli_stmt_get_result($partnerQuery);
$partner = mysqli_fetch_assoc($partnerResult);
mysqli_stmt_close($partnerQuery);

$partner_id = (int) ($partner["id"] ?? 0);

/* Initialize Earnings Summary */
$totalEarnings = 0;
$completedDeliveries = 0;
$totalAssignedDeliveries = 0;
$history = [];

/* Get Earnings and Delivery Statistics */
if ($partner_id > 0) {
    $statsQuery = mysqli_prepare(
        $conn,
        "SELECT
            COALESCE(SUM(
                CASE
                    WHEN LOWER(delivery_status) IN ('completed', 'delivered')
                    THEN COALESCE(delivery_fee, 0)
                    ELSE 0
                END
            ), 0) AS total_earnings,
            SUM(
                CASE
                    WHEN LOWER(delivery_status) IN ('completed', 'delivered')
                    THEN 1 ELSE 0
                END
            ) AS completed_deliveries,
            COUNT(*) AS total_assigned
         FROM food_orders
         WHERE delivery_partner_id = ?"
    );

    mysqli_stmt_bind_param($statsQuery, "i", $partner_id);
    mysqli_stmt_execute($statsQuery);
    $statsResult = mysqli_stmt_get_result($statsQuery);
    $stats = mysqli_fetch_assoc($statsResult);
    mysqli_stmt_close($statsQuery);

    $totalEarnings = (float) ($stats["total_earnings"] ?? 0);
    $completedDeliveries = (int) ($stats["completed_deliveries"] ?? 0);
    $totalAssignedDeliveries = (int) ($stats["total_assigned"] ?? 0);

    /* Fetch Completed Delivery Earnings History */
    $historyQuery = mysqli_prepare(
        $conn,
        "SELECT
            fo.id,
            fo.food_name,
            fo.quantity,
            fo.delivery_fee,
            fo.order_date,
            fo.delivery_status,
            r.restaurant_name,
            r.location
         FROM food_orders fo
         LEFT JOIN restaurants r ON r.id = fo.restaurant_id
         WHERE fo.delivery_partner_id = ?
           AND LOWER(fo.delivery_status) IN ('completed', 'delivered')
         ORDER BY fo.order_date DESC, fo.id DESC"
    );

    mysqli_stmt_bind_param($historyQuery, "i", $partner_id);
    mysqli_stmt_execute($historyQuery);
    $historyResult = mysqli_stmt_get_result($historyQuery);

    while ($row = mysqli_fetch_assoc($historyResult)) {
        $history[] = $row;
    }

    mysqli_stmt_close($historyQuery);
}

function e($value) {
    return htmlspecialchars((string) ($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | My Earnings</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --primary: #0f766e;
    --primary-dark: #115e59;
    --text: #172b2a;
    --muted: #64748b;
    --border: #e2e8f0;
    --white: #ffffff;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f8fa;
    color: var(--text);
    font-size: 14px;
}

.topbar {
    background: white;
    border-bottom: 1px solid var(--border);
    padding: 12px 4%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.brand {
    color: var(--primary);
    font-size: 20px;
    font-weight: 800;
}

.nav-links {
    display: flex;
    gap: 16px;
    align-items: center;
    flex-wrap: wrap;
}

.nav-links a {
    text-decoration: none;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.nav-links a.active,
.nav-links a:hover {
    color: var(--primary);
}

.nav-links .logout {
    background: #fff1f2;
    color: #be123c;
    padding: 8px 11px;
    border-radius: 7px;
}

.container {
    max-width: 1100px;
    margin: 22px auto;
    padding: 0 18px 25px;
}

.page-heading {
    margin-bottom: 17px;
}

.page-heading h1 {
    font-size: 24px;
    margin-bottom: 5px;
}

.page-heading p {
    color: var(--muted);
    font-size: 13px;
}

.earnings-banner {
    background: linear-gradient(120deg, #0f766e, #14b8a6);
    color: white;
    border-radius: 12px;
    padding: 21px 23px;
    margin-bottom: 16px;
}

.banner-label {
    font-size: 13px;
    opacity: .9;
    margin-bottom: 8px;
}

.total-amount {
    font-size: 34px;
    font-weight: 800;
    margin-bottom: 5px;
}

.banner-note {
    font-size: 12px;
    opacity: .9;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 13px;
    margin-bottom: 20px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 17px;
}

.stat-card .label {
    color: var(--muted);
    font-size: 12px;
    margin-bottom: 9px;
}

.stat-card .value {
    font-size: 25px;
    font-weight: 800;
    color: var(--primary);
}

.section-card {
    background: white;
    border: 1px solid var(--border);
    border-radius: 10px;
    overflow: hidden;
}

.section-heading {
    padding: 15px 17px;
    border-bottom: 1px solid var(--border);
}

.section-heading h2 {
    font-size: 16px;
    margin-bottom: 4px;
}

.section-heading p {
    font-size: 12px;
    color: var(--muted);
}

.table-wrap {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 690px;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #edf2f7;
    font-size: 12px;
}

th {
    background: #f8fafc;
    color: #475569;
    font-weight: 700;
}

tbody tr:last-child td {
    border-bottom: none;
}

.order-id {
    color: var(--primary);
    font-weight: 750;
}

.fee {
    color: #15803d;
    font-weight: 750;
}

.status {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 20px;
    background: #dcfce7;
    color: #166534;
    font-size: 11px;
    font-weight: 700;
}

.empty-state {
    padding: 35px 18px;
    text-align: center;
}

.empty-state .icon {
    font-size: 32px;
    margin-bottom: 9px;
}

.empty-state h3 {
    font-size: 15px;
    margin-bottom: 6px;
}

.empty-state p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.6;
}

.footer-note {
    margin-top: 13px;
    font-size: 11px;
    color: var(--muted);
    line-height: 1.5;
}

@media (max-width: 650px) {
    .topbar {
        align-items: flex-start;
        flex-direction: column;
        padding: 12px 16px;
    }

    .nav-links {
        gap: 12px;
    }

    .container {
        margin-top: 16px;
        padding: 0 12px 20px;
    }

    .page-heading h1 {
        font-size: 21px;
    }

    .earnings-banner {
        padding: 18px;
    }

    .total-amount {
        font-size: 29px;
    }

    .stats-grid {
        grid-template-columns: 1fr;
        gap: 9px;
    }

    .stat-card {
        padding: 13px 15px;
    }

    .stat-card .value {
        font-size: 22px;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">LDD <span style="color:#334155;font-size:12px;">Delivery</span></div>

    <nav class="nav-links">
        <a href="delivery_dashboard.php">Dashboard</a>
        <a href="available_orders.php">Available Deliveries</a>
        <a href="my_deliveries.php">My Deliveries</a>
        <a href="earnings.php" class="active">Earnings</a>
        <a href="reports.php">Reports</a>
        <a href="profile.php">My Profile</a>
        <a href="../backend/logout.php" class="logout">Logout</a>
    </nav>
</header>

<main class="container">

    <div class="page-heading">
        <h1>My Earnings</h1>
        <p>Track your completed deliveries and delivery fee earnings.</p>
    </div>

    <section class="earnings-banner">
        <div class="banner-label">Total Delivery Earnings</div>
        <div class="total-amount">₹<?= number_format($totalEarnings, 2) ?></div>
        <div class="banner-note">Calculated from completed deliveries recorded in LDD.</div>
    </section>

    <section class="stats-grid">
        <div class="stat-card">
            <div class="label">Total Earnings</div>
            <div class="value">₹<?= number_format($totalEarnings, 2) ?></div>
        </div>

        <div class="stat-card">
            <div class="label">Completed Deliveries</div>
            <div class="value"><?= number_format($completedDeliveries) ?></div>
        </div>

        <div class="stat-card">
            <div class="label">Total Assigned Deliveries</div>
            <div class="value"><?= number_format($totalAssignedDeliveries) ?></div>
        </div>
    </section>

    <section class="section-card">
        <div class="section-heading">
            <h2>Earnings History</h2>
            <p>Delivery fees from your completed orders.</p>
        </div>

        <?php if (count($history) > 0): ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Restaurant</th>
                            <th>Location</th>
                            <th>Order Details</th>
                            <th>Completed Date</th>
                            <th>Delivery Fee</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $item): ?>
                        <tr>
                            <td class="order-id">#<?= (int) $item["id"] ?></td>
                            <td><?= e($item["restaurant_name"] ?: "Restaurant") ?></td>
                            <td><?= e($item["location"] ?: "Not specified") ?></td>
                            <td>
                                <?= e($item["food_name"] ?: "Food Order") ?>
                                <?php if (!empty($item["quantity"])): ?>
                                    × <?= (int) $item["quantity"] ?>
                                <?php endif; ?>
                            </td>
                            <td><?= e($item["order_date"] ?: "—") ?></td>
                            <td class="fee">₹<?= number_format((float) $item["delivery_fee"], 2) ?></td>
                            <td><span class="status">Completed</span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div class="icon">💰</div>
                <h3>No Earnings History Yet</h3>
                <p>Your completed deliveries and delivery fee earnings will appear here.</p>
            </div>
        <?php endif; ?>
    </section>

    <p class="footer-note">
        Earnings are calculated from the delivery fees stored for completed deliveries.
        This page displays recorded delivery fees; it does not process payouts.
    </p>

</main>
</body>
</html>