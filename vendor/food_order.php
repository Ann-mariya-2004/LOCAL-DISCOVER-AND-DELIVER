<?php
session_start();
require_once "../backend/db.php";

// Allow only logged-in vendors
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'Vendor') {
    header("Location: ../login_registration.html");
    exit;
}

$vendorId = (int) $_SESSION['user_id'];
$orders = [];
$errorMessage = "";

// Fetch orders belonging to the vendor's restaurant
$sql = "SELECT
            fo.id,
            fo.user_id,
            fo.food_name,
            fo.quantity,
            fo.price,
            fo.total,
            fo.delivery_status,
            fo.delivery_fee,
            fo.order_date,
            r.restaurant_name,
            u.name AS customer_name
        FROM food_orders fo
        INNER JOIN restaurants r ON r.id = fo.restaurant_id
        LEFT JOIN users u ON u.id = fo.user_id
        WHERE r.vendor_id = ?
        ORDER BY fo.order_date DESC";

$stmt = $conn->prepare($sql);

if ($stmt) {
    $stmt->bind_param("i", $vendorId);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $orders[] = $row;
        }
    } else {
        $errorMessage = "Unable to load orders.";
    }

    $stmt->close();
} else {
    $errorMessage = "Unable to load orders. Please check the database structure.";
}

// Safely escape output
function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$totalRevenue = 0;
foreach ($orders as $order) {
    $totalRevenue += (float)$order['total'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Food Orders | Local Discover & Deliver</title>

<style>
:root {
    --primary: #0f766e;
    --primary-light: #e6f5f3;
    --text: #1e293b;
    --muted: #64748b;
    --border: #e2e8f0;
    --bg: #f5f8fa;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--bg);
    color: var(--text);
    font-size: 13px;
}

.topbar {
    height: 58px;
    padding: 0 24px;
    background: #fff;
    border-bottom: 1px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.brand {
    color: var(--primary);
    font-size: 17px;
    font-weight: 700;
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 10px;
}

.top-actions a {
    color: var(--text);
    text-decoration: none;
    font-size: 12px;
    padding: 8px 11px;
    border: 1px solid var(--border);
    border-radius: 6px;
}

.top-actions a:hover {
    border-color: var(--primary);
    color: var(--primary);
}

main {
    max-width: 1440px;
    margin: 0 auto;
    padding: 20px 24px;
}

.page-head {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    margin-bottom: 16px;
}

.page-head h1 {
    margin: 0 0 4px;
    font-size: 22px;
    letter-spacing: -.4px;
}

.page-head p {
    margin: 0;
    color: var(--muted);
    font-size: 12px;
}

.date-label {
    color: var(--muted);
    font-size: 12px;
    white-space: nowrap;
}

.stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 12px;
    margin-bottom: 16px;
}

.stat-card {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 13px 15px;
    min-height: 77px;
}

.stat-label {
    color: var(--muted);
    font-size: 12px;
}

.stat-value {
    margin-top: 7px;
    font-size: 21px;
    font-weight: 700;
}

.stat-value.teal {
    color: var(--primary);
}

.orders-panel {
    background: #fff;
    border: 1px solid var(--border);
    border-radius: 9px;
    overflow: hidden;
}

.panel-head {
    padding: 12px 15px;
    border-bottom: 1px solid var(--border);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
}

.panel-head h2 {
    margin: 0;
    font-size: 14px;
}

.search {
    width: 235px;
    max-width: 100%;
    padding: 8px 10px;
    border: 1px solid var(--border);
    border-radius: 6px;
    outline: none;
    font-size: 12px;
}

.search:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 2px var(--primary-light);
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    white-space: nowrap;
}

thead th {
    background: #f8fafc;
    color: #64748b;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .25px;
}

th, td {
    text-align: left;
    padding: 12px 13px;
    border-bottom: 1px solid #edf1f5;
}

tbody tr:last-child td {
    border-bottom: none;
}

tbody tr:hover {
    background: #f8fbfb;
}

.order-id {
    color: var(--primary);
    font-weight: 700;
}

.food-name {
    color: var(--text);
    font-weight: 600;
}

.sub-text {
    display: block;
    color: var(--muted);
    font-size: 11px;
    margin-top: 3px;
}

.amount {
    font-weight: 700;
}

.status {
    display: inline-block;
    padding: 5px 8px;
    border-radius: 5px;
    background: #ecfdf5;
    color: #047857;
    font-size: 11px;
    font-weight: 700;
}

.status.pending {
    background: #fff7ed;
    color: #c2410c;
}

.status.cancelled {
    background: #fef2f2;
    color: #b91c1c;
}

.empty, .error {
    padding: 28px 15px;
    text-align: center;
    color: var(--muted);
}

.empty strong {
    display: block;
    color: var(--text);
    font-size: 14px;
    margin-bottom: 5px;
}

.error {
    color: #b91c1c;
}

.panel-foot {
    padding: 10px 15px;
    border-top: 1px solid var(--border);
    color: var(--muted);
    font-size: 11px;
}

footer {
    text-align: center;
    color: #94a3b8;
    padding: 14px;
    font-size: 11px;
}

@media (max-width: 700px) {
    .topbar {
        padding: 0 14px;
        height: 54px;
    }

    .brand {
        font-size: 14px;
    }

    main {
        padding: 16px 12px;
    }

    .page-head {
        align-items: flex-start;
    }

    .page-head h1 {
        font-size: 20px;
    }

    .date-label {
        display: none;
    }

    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px;
    }

    .stat-card {
        padding: 11px;
    }

    .panel-head {
        align-items: flex-start;
        flex-direction: column;
    }

    .search {
        width: 100%;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">LDD <span style="color:#334155;font-weight:500;">| Vendor Panel</span></div>

    <nav class="top-actions">
        <a href="vendor_dashboard.php">← Dashboard</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <section class="page-head">
        <div>
            <h1>Food Orders</h1>
            <p>Manage and review orders for your restaurant.</p>
        </div>
        <div class="date-label"><?= date('d M Y') ?></div>
    </section>

    <section class="stats">
        <div class="stat-card">
            <div class="stat-label">Total Orders</div>
            <div class="stat-value"><?= count($orders) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Total Order Value</div>
            <div class="stat-value teal">₹<?= number_format($totalRevenue, 2) ?></div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Available for Delivery</div>
            <div class="stat-value">
                <?php
                $availableCount = 0;
                foreach ($orders as $order) {
                    if (strtolower((string)$order['delivery_status']) === 'available') {
                        $availableCount++;
                    }
                }
                echo $availableCount;
                ?>
            </div>
        </div>
    </section>

    <section class="orders-panel">
        <div class="panel-head">
            <h2>All Food Orders</h2>
            <input
                type="search"
                id="orderSearch"
                class="search"
                placeholder="Search order, customer or food..."
                aria-label="Search food orders"
            >
        </div>

        <?php if ($errorMessage !== ''): ?>

            <div class="error"><?= e($errorMessage) ?></div>

        <?php elseif (empty($orders)): ?>

            <div class="empty">
                <strong>No food orders found</strong>
                Orders linked to your restaurant will appear here.
            </div>

        <?php else: ?>

        <div class="table-wrap">
            <table id="ordersTable">
                <thead>
                    <tr>
                        <th>Order</th>
                        <th>Customer</th>
                        <th>Food Item</th>
                        <th>Qty</th>
                        <th>Unit Price</th>
                        <th>Total</th>
                        <th>Delivery Fee</th>
                        <th>Status</th>
                        <th>Order Date</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <?php
                    $status = strtolower(trim((string)($order['delivery_status'] ?? 'pending')));
                    $statusClass = '';

                    if (in_array($status, ['pending', 'processing'], true)) {
                        $statusClass = 'pending';
                    } elseif (in_array($status, ['cancelled', 'canceled'], true)) {
                        $statusClass = 'cancelled';
                    }
                    ?>
                    <tr>
                        <td class="order-id">
                            #LDD<?= str_pad((string)$order['id'], 4, '0', STR_PAD_LEFT) ?>
                        </td>

                        <td>
                            <?= e($order['customer_name'] ?: 'Customer #' . $order['user_id']) ?>
                            <span class="sub-text">ID: <?= (int)$order['user_id'] ?></span>
                        </td>

                        <td class="food-name"><?= e($order['food_name']) ?></td>

                        <td><?= (int)$order['quantity'] ?></td>

                        <td>₹<?= number_format((float)$order['price'], 2) ?></td>

                        <td class="amount">₹<?= number_format((float)$order['total'], 2) ?></td>

                        <td>₹<?= number_format((float)$order['delivery_fee'], 2) ?></td>

                        <td>
                            <span class="status <?= e($statusClass) ?>">
                                <?= e(ucwords(str_replace('_', ' ', $status ?: 'pending'))) ?>
                            </span>
                        </td>

                        <td>
                            <?= e(date('d M Y', strtotime($order['order_date']))) ?>
                            <span class="sub-text">
                                <?= e(date('h:i A', strtotime($order['order_date']))) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="panel-foot">
            Showing <span id="visibleCount"><?= count($orders) ?></span>
            of <?= count($orders) ?> orders
        </div>

        <?php endif; ?>
    </section>
</main>

<footer>Local Discover & Deliver &copy; <?= date('Y') ?></footer>

<script>
// Filter orders by the entered search text
const searchInput = document.getElementById('orderSearch');
const table = document.getElementById('ordersTable');
const visibleCount = document.getElementById('visibleCount');

if (searchInput && table) {
    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase().trim();
        let count = 0;

        table.querySelectorAll('tbody tr').forEach(function (row) {
            const matches = row.textContent.toLowerCase().includes(query);
            row.style.display = matches ? '' : 'none';

            if (matches) count++;
        });

        if (visibleCount) visibleCount.textContent = count;
    });
}
</script>

</body>
</html>