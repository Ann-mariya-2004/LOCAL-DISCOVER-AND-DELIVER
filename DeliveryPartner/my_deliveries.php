<?php
/**
 * Local Discover and Deliver (LDD)
 * My Deliveries - Delivery Partner
 *
 * Displays orders assigned to the logged-in delivery partner.
 */

session_start();
require_once "../backend/db.php";

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed. Please try again later.");
}

// Check login
if (!isset($_SESSION["user_id"])) {
    header("Location: ../login_registration.html");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$role = $_SESSION["role"] ?? "";

// Check Delivery Partner role
if (strcasecmp(trim($role), "Delivery Partner") !== 0) {
    header("Location: ../dashboard.php");
    exit;
}

// HTML escape helper
function e($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

// Read one-time notification messages
$notice = $_SESSION["delivery_message"] ?? null;
unset($_SESSION["delivery_message"]);

// Fetch assigned orders
$sql = "
    SELECT
        fo.id,
        fo.user_id,
        fo.restaurant_id,
        fo.food_name,
        fo.quantity,
        fo.price,
        fo.total,
        fo.order_date,
        fo.delivery_status,
        fo.delivery_fee,

        customer.name AS customer_name,
        customer.phone AS customer_phone,
        customer.address AS customer_address,

        restaurant.restaurant_name AS restaurant_name,
        restaurant.location AS restaurant_location

    FROM food_orders fo

    LEFT JOIN users customer
        ON customer.id = fo.user_id

    LEFT JOIN restaurants restaurant
        ON restaurant.id = fo.restaurant_id

    WHERE fo.delivery_partner_id = ?

    ORDER BY fo.id DESC
";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("My Deliveries query error: " . $conn->error);
    die("Unable to load deliveries right now.");
}

$stmt->bind_param("i", $user_id);
$stmt->execute();

$result = $stmt->get_result();

$orders = [];

while ($row = $result->fetch_assoc()) {
    $orders[] = $row;
}

$stmt->close();

// Status formatting
function statusClass($status): string {
    $status = strtolower(trim((string)$status));

    if (in_array($status, ["delivered", "completed"], true)) {
        return "completed";
    }

    if (in_array($status, ["cancelled", "canceled"], true)) {
        return "cancelled";
    }

    if (in_array($status, ["assigned", "accepted"], true)) {
        return "assigned";
    }

    if (in_array($status, ["picked up", "picked_up"], true)) {
        return "picked";
    }

    if (in_array($status, ["on the way", "on_the_way", "in transit"], true)) {
        return "transit";
    }

    return "pending";
}

function displayStatus($status): string {
    $status = trim((string)($status ?? ""));

    if ($status === "") {
        return "Assigned";
    }

    return ucwords(str_replace("_", " ", $status));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LDD | My Deliveries</title>

    <style>
        :root {
            --primary: #0f766e;
            --secondary: #14b8a6;
            --light: #f0fdfa;
            --background: #f5f7fb;
            --text: #1f2937;
            --muted: #64748b;
            --border: #e2e8f0;
            --white: #ffffff;
        }

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

        .navbar {
            min-height: 66px;
            padding: 14px 5%;
            background: var(--white);
            border-bottom: 1px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            flex-wrap: wrap;
        }

        .brand {
            font-size: 21px;
            font-weight: 800;
            color: var(--primary);
            text-decoration: none;
        }

        .brand span {
            color: var(--secondary);
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .role-label {
            color: var(--muted);
            font-size: 13px;
        }

        .logout {
            color: #b91c1c;
            text-decoration: none;
            border: 1px solid #fecaca;
            padding: 8px 13px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 700;
        }

        .layout {
            display: grid;
            grid-template-columns: 220px minmax(0, 1fr);
            gap: 24px;
            max-width: 1450px;
            margin: 24px auto;
            padding: 0 24px;
        }

        .sidebar {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 18px 12px;
            height: fit-content;
        }

        .sidebar-title {
            padding: 0 10px 14px;
            color: var(--muted);
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .sidebar a {
            display: block;
            padding: 11px 12px;
            margin-bottom: 5px;
            border-radius: 8px;
            color: #475569;
            text-decoration: none;
            font-size: 14px;
        }

        .sidebar a:hover,
        .sidebar a.active {
            color: var(--primary);
            background: var(--light);
            font-weight: 700;
        }

        .main {
            min-width: 0;
        }

        .page-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
        }

        .page-heading h1 {
            font-size: 25px;
            margin-bottom: 6px;
        }

        .page-heading p {
            color: var(--muted);
            font-size: 14px;
        }

        .count-badge {
            background: var(--light);
            color: var(--primary);
            border: 1px solid #ccfbf1;
            border-radius: 10px;
            padding: 10px 14px;
            font-weight: 700;
            font-size: 13px;
        }

        .notice {
            padding: 13px 15px;
            border-radius: 10px;
            margin-bottom: 18px;
            font-size: 14px;
        }

        .notice.success {
            color: #166534;
            background: #dcfce7;
            border: 1px solid #bbf7d0;
        }

        .notice.error {
            color: #991b1b;
            background: #fee2e2;
            border: 1px solid #fecaca;
        }

        .orders {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }

        .order-card {
            background: var(--white);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 19px;
            box-shadow: 0 3px 12px rgba(15, 23, 42, 0.035);
        }

        .card-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }

        .order-number {
            font-size: 16px;
            font-weight: 800;
        }

        .order-date {
            color: var(--muted);
            font-size: 12px;
            margin-top: 5px;
        }

        .status {
            padding: 6px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 800;
            white-space: nowrap;
        }

        .status.assigned {
            color: #1d4ed8;
            background: #dbeafe;
        }

        .status.pending {
            color: #92400e;
            background: #fef3c7;
        }

        .status.picked,
        .status.transit {
            color: #6d28d9;
            background: #ede9fe;
        }

        .status.completed {
            color: #166534;
            background: #dcfce7;
        }

        .status.cancelled {
            color: #991b1b;
            background: #fee2e2;
        }

        .food-box {
            background: #f8fafc;
            padding: 13px;
            border-radius: 10px;
            margin-bottom: 15px;
        }

        .food-name {
            font-size: 15px;
            font-weight: 700;
            margin-bottom: 5px;
        }

        .food-meta {
            font-size: 13px;
            color: var(--muted);
        }

        .details {
            display: grid;
            gap: 13px;
        }

        .detail {
            display: grid;
            grid-template-columns: 23px minmax(0, 1fr);
            gap: 8px;
            align-items: start;
        }

        .detail-icon {
            font-size: 16px;
        }

        .detail-label {
            font-size: 11px;
            color: var(--muted);
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: .4px;
        }

        .detail-value {
            font-size: 13px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .fee-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            margin-top: 17px;
            padding-top: 14px;
            border-top: 1px solid var(--border);
        }

        .fee-label {
            font-size: 13px;
            color: var(--muted);
        }

        .fee-value {
            font-size: 19px;
            font-weight: 800;
            color: var(--primary);
        }

        .empty-state {
            background: var(--white);
            border: 1px dashed #cbd5e1;
            border-radius: 14px;
            padding: 45px 20px;
            text-align: center;
        }

        .empty-icon {
            font-size: 38px;
            margin-bottom: 12px;
        }

        .empty-state h2 {
            font-size: 18px;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: var(--muted);
            font-size: 14px;
            margin-bottom: 18px;
        }

        .primary-button {
            display: inline-block;
            padding: 10px 15px;
            background: var(--primary);
            color: var(--white);
            text-decoration: none;
            border-radius: 8px;
            font-weight: 700;
            font-size: 13px;
        }

        .footer {
            padding: 20px;
            text-align: center;
            color: var(--muted);
            font-size: 12px;
        }

        @media (max-width: 1000px) {
            .orders {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 700px) {
            .layout {
                grid-template-columns: 1fr;
                padding: 0 14px;
                margin-top: 15px;
            }

            .sidebar {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 4px;
            }

            .sidebar-title {
                grid-column: 1 / -1;
            }

            .page-heading h1 {
                font-size: 22px;
            }

            .navbar {
                padding: 13px 16px;
            }
        }
    </style>
</head>

<body>

<header class="navbar">
    <a class="brand" href="delivery_dashboard.php">
        LDD<span>.</span> Delivery
    </a>

    <div class="nav-right">
        <span class="role-label">Delivery Partner</span>
        <a class="logout" href="logout.php">Logout</a>
    </div>
</header>

<div class="layout">

    <aside class="sidebar">
        <div class="sidebar-title">Delivery Menu</div>

        <a href="delivery_dashboard.php">📊 Dashboard</a>
        <a href="available_orders.php">📦 Available Orders</a>
        <a href="my_deliveries.php" class="active">🚚 My Deliveries</a>
        <a href="earnings.php">💰 Earnings</a>
        <a href="reports.php">📊 Reports</a>
        <a href="profile.php">👤 My Profile</a>
    </aside>

    <main class="main">

        <div class="page-heading">
            <div>
                <h1>My Deliveries</h1>
                <p>View and track the orders assigned to you.</p>
            </div>

            <div class="count-badge">
                <?= count($orders) ?> Orders
            </div>
        </div>

        <?php if ($notice && is_array($notice)): ?>
            <div class="notice <?= e($notice["type"] ?? "success") ?>">
                <?= e($notice["text"] ?? "") ?>
            </div>
        <?php endif; ?>

        <?php if (count($orders) > 0): ?>

            <div class="orders">

                <?php foreach ($orders as $order): ?>

                    <?php
                    $status = $order["delivery_status"] ?? "Assigned";
                    $status_class = statusClass($status);
                    ?>

                    <article class="order-card">

                        <div class="card-top">
                            <div>
                                <div class="order-number">
                                    Order #<?= (int)$order["id"] ?>
                                </div>

                                <div class="order-date">
                                    Ordered:
                                    <?= e($order["order_date"] ?: "Not available") ?>
                                </div>
                            </div>

                            <span class="status <?= e($status_class) ?>">
                                <?= e(displayStatus($status)) ?>
                            </span>
                        </div>

                        <div class="food-box">
                            <div class="food-name">
                                <?= e($order["food_name"] ?: "Food Order") ?>
                            </div>

                            <div class="food-meta">
                                Quantity: <?= e($order["quantity"] ?? "—") ?>
                                &nbsp; | &nbsp;
                                Order Total: ₹<?= number_format((float)($order["total"] ?? 0), 2) ?>
                            </div>
                        </div>

                        <div class="details">

                            <div class="detail">
                                <span class="detail-icon">👤</span>

                                <div>
                                    <div class="detail-label">Customer</div>
                                    <div class="detail-value">
                                        <?= e($order["customer_name"] ?: "Not provided") ?>
                                        <?php if (!empty($order["customer_phone"])): ?>
                                            <br><?= e($order["customer_phone"]) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="detail">
                                <span class="detail-icon">📍</span>

                                <div>
                                    <div class="detail-label">Delivery Address</div>
                                    <div class="detail-value">
                                        <?= e($order["customer_address"] ?: "Address not available") ?>
                                    </div>
                                </div>
                            </div>

                            <div class="detail">
                                <span class="detail-icon">🍽️</span>

                                <div>
                                    <div class="detail-label">Restaurant</div>
                                    <div class="detail-value">
                                        <?= e($order["restaurant_name"] ?: "Restaurant details unavailable") ?>

                                        <?php if (!empty($order["restaurant_location"])): ?>
                                            <br><?= e($order["restaurant_location"]) ?>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                        </div>

                        <div class="fee-row">
                            <span class="fee-label">Delivery Fee</span>

                            <span class="fee-value">
                                ₹<?= number_format((float)($order["delivery_fee"] ?? 0), 2) ?>
                            </span>
                        </div>
 
                        <?php if (!in_array(strtolower(trim((string)$status)), ["delivered", "completed", "cancelled", "canceled"], true)): ?>

    <?php
    $next_status = [
        "Assigned" => "Picked Up",
        "Picked Up" => "On the Way",
        "On the Way" => "Delivered"
    ];

    $current_status = trim((string)$status);
    ?>

    <?php if (isset($next_status[$current_status])): ?>
        <form action="update_delivery.php" method="POST" style="margin-top:15px;">
            <input type="hidden" name="order_id" value="<?= (int)$order["id"] ?>">
            <input type="hidden" name="delivery_status" value="<?= e($next_status[$current_status]) ?>">

            <button type="submit" style="
                width:100%;
                padding:11px;
                border:0;
                border-radius:8px;
                background:#0f766e;
                color:white;
                font-size:14px;
                font-weight:700;
                cursor:pointer;
            ">
                <?= e($next_status[$current_status]) ?>
            </button>
        </form>
    <?php endif; ?>

<?php endif; ?>
                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-state">
                <div class="empty-icon">🚚</div>

                <h2>No Assigned Deliveries Yet</h2>

                <p>
                    Orders you accept will appear here.
                    Check available orders to find your next delivery.
                </p>

                <a href="available_orders.php" class="primary-button">
                    View Available Orders
                </a>
            </div>

        <?php endif; ?>

    </main>

</div>

<footer class="footer">
    &copy; <?= date("Y") ?> Local Discover and Deliver. All rights reserved.
</footer>

</body>
</html>