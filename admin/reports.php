<?php
session_start();
require_once "../backend/db.php";

/*
|--------------------------------------------------------------------------
| Local Discover and Deliver - Admin Reports
|--------------------------------------------------------------------------
| Uses real database records.
| Count values are displayed as integers.
| Currency symbols are displayed only for money fields.
|--------------------------------------------------------------------------
*/

// -------------------- ADMIN ACCESS --------------------

if (
    !isset($_SESSION['user_id']) ||
    !isset($_SESSION['role']) ||
    strtolower($_SESSION['role']) !== 'admin'
) {
    header("Location: ../login_registration.html");
    exit;
}

// -------------------- HELPER FUNCTIONS --------------------

function e($value)
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function tableExists($conn, $table)
{
    $table = str_replace('`', '', $table);
    $sql = "SHOW TABLES LIKE '" .
           $conn->real_escape_string($table) . "'";

    $result = $conn->query($sql);

    return $result && $result->num_rows > 0;
}

function columnExists($conn, $table, $column)
{
    if (!tableExists($conn, $table)) {
        return false;
    }

    $table = str_replace('`', '', $table);
    $column = $conn->real_escape_string($column);

    $result = $conn->query(
        "SHOW COLUMNS FROM `$table` LIKE '$column'"
    );

    return $result && $result->num_rows > 0;
}

function firstColumn($conn, $table, $candidates)
{
    foreach ($candidates as $column) {
        if (columnExists($conn, $table, $column)) {
            return $column;
        }
    }

    return null;
}

function countRows($conn, $table)
{
    if (!tableExists($conn, $table)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table`");

    if (!$result) {
        return 0;
    }

    return (int)$result->fetch_assoc()['total'];
}

function formatMoney($amount)
{
    return '₹' . number_format((float)$amount, 2);
}

function formatNumber($number)
{
    return number_format((int)$number);
}

function getSum($conn, $table, $column, $where = '')
{
    if (
        !tableExists($conn, $table) ||
        !columnExists($conn, $table, $column)
    ) {
        return 0;
    }

    $sql = "SELECT COALESCE(SUM(`$column`), 0) AS total
            FROM `$table` $where";

    $result = $conn->query($sql);

    if (!$result) {
        return 0;
    }

    return (float)$result->fetch_assoc()['total'];
}

function getRows($conn, $sql)
{
    $result = $conn->query($sql);

    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

/*
 * Returns a date condition only when the table has a supported date column.
 * Dates are validated before being used in the SQL condition.
 */
function dateCondition($conn, $table, $dateColumn, $from, $to)
{
    if (
        !tableExists($conn, $table) ||
        !columnExists($conn, $table, $dateColumn)
    ) {
        return '';
    }

    $conditions = [];

    if ($from !== '') {
        $safeFrom = $conn->real_escape_string($from);
        $conditions[] = "DATE(`$dateColumn`) >= '$safeFrom'";
    }

    if ($to !== '') {
        $safeTo = $conn->real_escape_string($to);
        $conditions[] = "DATE(`$dateColumn`) <= '$safeTo'";
    }

    return count($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
}

/*
 * Displays database rows as a responsive table.
 * Currency formatting is applied only to known monetary columns.
 */
function renderTable($rows, $emptyMessage = 'No records found.')
{
    if (empty($rows)) {
        echo '<div class="empty">' . e($emptyMessage) . '</div>';
        return;
    }

    // These columns represent monetary amounts, not counts or IDs.
    $moneyColumns = [
        'amount',
        'price',
        'total',
        'total_amount',
        'grand_total',
        'delivery_fee',
        'total_earnings',
        'earnings',
        'paid_amount',
        'pending_amount'
    ];

    echo '<div class="table-wrap">';
    echo '<table><thead><tr>';

    foreach (array_keys($rows[0]) as $column) {
        echo '<th>' . e(ucwords(str_replace('_', ' ', $column))) . '</th>';
    }

    echo '</tr></thead><tbody>';

    foreach ($rows as $row) {
        echo '<tr>';

        foreach ($row as $column => $value) {
            $normalizedColumn = strtolower($column);

            if (
                in_array($normalizedColumn, $moneyColumns, true) &&
                is_numeric($value)
            ) {
                $displayValue = formatMoney($value);
            } elseif (
                is_numeric($value) &&
                preg_match('/(^count$|^total_count$|^copies$|^quantity$)/i', $column)
            ) {
                $displayValue = formatNumber($value);
            } else {
                $displayValue = (string)($value ?? '—');
            }

            echo '<td>' . e($displayValue) . '</td>';
        }

        echo '</tr>';
    }

    echo '</tbody></table></div>';
}

// -------------------- DATE FILTER --------------------

$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';

if ($fromDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
    $fromDate = '';
}

if ($toDate !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
    $toDate = '';
}

if ($fromDate !== '' && $toDate !== '' && $fromDate > $toDate) {
    $temporary = $fromDate;
    $fromDate = $toDate;
    $toDate = $temporary;
}

// -------------------- DATABASE COUNTS --------------------

$totalUsers = countRows($conn, 'users');
$totalRestaurants = countRows($conn, 'restaurants');
$totalFoodOrders = countRows($conn, 'food_orders');
$totalGroceryOrders = countRows($conn, 'grocery_orders');
$totalPrintOrders = countRows($conn, 'print_orders');
$totalDeliveryPartners = countRows($conn, 'delivery_partners');

// -------------------- FOOD REPORT --------------------

$foodDateColumn = firstColumn(
    $conn,
    'food_orders',
    ['order_date', 'created_at']
);

$foodWhere = $foodDateColumn
    ? dateCondition($conn, 'food_orders', $foodDateColumn, $fromDate, $toDate)
    : '';

$foodSalesColumn = firstColumn(
    $conn,
    'food_orders',
    ['total']
);

$foodSales = $foodSalesColumn
    ? getSum($conn, 'food_orders', $foodSalesColumn, $foodWhere)
    : 0;
// -------------------- FOOD ORDERS REPORT --------------------

$foodDateColumn = firstColumn(
    $conn,
    'food_orders',
    ['order_date', 'created_at']
);

$foodWhere = $foodDateColumn
    ? dateCondition(
        $conn,
        'food_orders',
        $foodDateColumn,
        $fromDate,
        $toDate
    )
    : '';

$foodSalesColumn = firstColumn(
    $conn,
    'food_orders',
    ['total']
);

$foodSales = $foodSalesColumn
    ? getSum($conn, 'food_orders', $foodSalesColumn, $foodWhere)
    : 0;

$foodOrders = [];

if (tableExists($conn, 'food_orders')) {

    $hasRestaurantId = columnExists(
        $conn,
        'food_orders',
        'restaurant_id'
    );

    $hasRestaurantTable = tableExists($conn, 'restaurants');

    $hasRestaurantPrimaryKey = columnExists(
        $conn,
        'restaurants',
        'id'
    );

    $restaurantNameColumn = firstColumn(
        $conn,
        'restaurants',
        ['restaurant_name', 'name']
    );

    if (
        $hasRestaurantId &&
        $hasRestaurantTable &&
        $hasRestaurantPrimaryKey &&
        $restaurantNameColumn
    ) {
        // Display the restaurant ID and its matching name.
        $foodOrders = getRows(
            $conn,
            "SELECT
                fo.*,
                r.`$restaurantNameColumn` AS restaurant_name
             FROM food_orders AS fo
             LEFT JOIN restaurants AS r
                ON fo.restaurant_id = r.id
             $foodWhere
             ORDER BY fo.`" .
             ($foodDateColumn ?: 'id') .
             "` DESC
             LIMIT 10"
        );

    } else {

        // Show food orders even if restaurant details are unavailable.
        $foodOrders = getRows(
            $conn,
            "SELECT *
             FROM food_orders
             $foodWhere
             ORDER BY `" .
             ($foodDateColumn ?: 'id') .
             "` DESC
             LIMIT 10"
        );
    }
}
// -------------------- GROCERY REPORT --------------------

$groceryDateColumn = firstColumn(
    $conn,
    'grocery_orders',
    ['created_at', 'order_date']
);

$groceryWhere = $groceryDateColumn
    ? dateCondition($conn, 'grocery_orders', $groceryDateColumn, $fromDate, $toDate)
    : '';

$grocerySalesColumn = firstColumn(
    $conn,
    'grocery_orders',
    ['grand_total', 'total_amount']
);

$grocerySales = $grocerySalesColumn
    ? getSum($conn, 'grocery_orders', $grocerySalesColumn, $groceryWhere)
    : 0;

$groceryOrders = [];

if (tableExists($conn, 'grocery_orders')) {
    $groceryOrders = getRows(
        $conn,
        "SELECT * FROM `grocery_orders` $groceryWhere
         ORDER BY `" .
         ($groceryDateColumn ?: 'id') .
         "` DESC LIMIT 10"
    );
}

// -------------------- PRINT REPORT --------------------

$printDateColumn = firstColumn(
    $conn,
    'print_orders',
    ['created_at', 'order_date']
);

$printWhere = $printDateColumn
    ? dateCondition($conn, 'print_orders', $printDateColumn, $fromDate, $toDate)
    : '';

$printSalesColumn = firstColumn(
    $conn,
    'print_orders',
    ['total_amount', 'amount']
);

$printSales = $printSalesColumn
    ? getSum($conn, 'print_orders', $printSalesColumn, $printWhere)
    : 0;

$printOrders = [];

if (tableExists($conn, 'print_orders')) {
    $printOrders = getRows(
        $conn,
        "SELECT * FROM `print_orders` $printWhere
         ORDER BY `" .
         ($printDateColumn ?: 'id') .
         "` DESC LIMIT 10"
    );
}

// -------------------- PAYMENT REPORT --------------------

$paymentDateColumn = firstColumn(
    $conn,
    'payments',
    ['payment_date', 'created_at']
);

$paymentWhere = $paymentDateColumn
    ? dateCondition($conn, 'payments', $paymentDateColumn, $fromDate, $toDate)
    : '';

$paymentAmountColumn = firstColumn(
    $conn,
    'payments',
    ['amount']
);

$totalPaymentsAmount = $paymentAmountColumn
    ? getSum($conn, 'payments', $paymentAmountColumn, $paymentWhere)
    : 0;

$paidAmount = 0;
$pendingAmount = 0;
$paidCount = 0;
$pendingCount = 0;

if (
    tableExists($conn, 'payments') &&
    columnExists($conn, 'payments', 'payment_status')
) {
    $paidWhere = $paymentWhere
        ? $paymentWhere . " AND LOWER(payment_status) = 'paid'"
        : "WHERE LOWER(payment_status) = 'paid'";

    $pendingWhere = $paymentWhere
        ? $paymentWhere . " AND LOWER(payment_status) IN ('pending', 'not confirmed')"
        : "WHERE LOWER(payment_status) IN ('pending', 'not confirmed')";

    if ($paymentAmountColumn) {
        $paidAmount = getSum($conn, 'payments', $paymentAmountColumn, $paidWhere);
        $pendingAmount = getSum($conn, 'payments', $paymentAmountColumn, $pendingWhere);
    }

    $paidCount = countRowsWithWhere($conn, 'payments', $paidWhere);
    $pendingCount = countRowsWithWhere($conn, 'payments', $pendingWhere);
}

function countRowsWithWhere($conn, $table, $where)
{
    if (!tableExists($conn, $table)) {
        return 0;
    }

    $result = $conn->query("SELECT COUNT(*) AS total FROM `$table` $where");

    return $result ? (int)$result->fetch_assoc()['total'] : 0;
}

$payments = [];

if (tableExists($conn, 'payments')) {
    $payments = getRows(
        $conn,
        "SELECT * FROM `payments` $paymentWhere
         ORDER BY `" .
         ($paymentDateColumn ?: 'id') .
         "` DESC LIMIT 10"
    );
}

// -------------------- DELIVERY REPORT --------------------

$deliveryEarnings = getSum(
    $conn,
    'delivery_partners',
    'total_earnings'
);

$deliveryPartners = [];

if (tableExists($conn, 'delivery_partners')) {
    $partnerNameColumn = null;

    if (columnExists($conn, 'users', 'name')) {
        $partnerNameColumn = 'u.name';
    }

    if (
        $partnerNameColumn &&
        columnExists($conn, 'delivery_partners', 'user_id')
    ) {
        $deliveryPartners = getRows(
            $conn,
            "SELECT dp.id, u.name AS partner_name,
                    dp.vehicle_type, dp.availability_status,
                    dp.total_deliveries, dp.total_earnings
             FROM delivery_partners dp
             LEFT JOIN users u ON u.id = dp.user_id
             ORDER BY dp.id DESC
             LIMIT 10"
        );
    } else {
        $deliveryPartners = getRows(
            $conn,
            "SELECT * FROM delivery_partners
             ORDER BY id DESC LIMIT 10"
        );
    }
}

// -------------------- ORDER STATUS SUMMARY --------------------

$foodStatusRows = [];

if (
    tableExists($conn, 'food_orders') &&
    columnExists($conn, 'food_orders', 'delivery_status')
) {
    $foodStatusRows = getRows(
        $conn,
        "SELECT delivery_status, COUNT(*) AS count
         FROM food_orders
         GROUP BY delivery_status
         ORDER BY count DESC"
    );
}

$groceryStatusRows = [];

if (
    tableExists($conn, 'grocery_orders') &&
    columnExists($conn, 'grocery_orders', 'order_status')
) {
    $groceryStatusRows = getRows(
        $conn,
        "SELECT order_status, COUNT(*) AS count
         FROM grocery_orders
         GROUP BY order_status
         ORDER BY count DESC"
    );
}

$printStatusRows = [];

if (
    tableExists($conn, 'print_orders') &&
    columnExists($conn, 'print_orders', 'status')
) {
    $printStatusRows = getRows(
        $conn,
        "SELECT status, COUNT(*) AS count
         FROM print_orders
         GROUP BY status
         ORDER BY count DESC"
    );
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LDD | Admin Reports</title>

<style>
:root {
    --primary: #0f766e;
    --primary-light: #14b8a6;
    --background: #f3f7f7;
    --text: #1f2937;
    --muted: #64748b;
    --border: #dfe8e7;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--background);
    color: var(--text);
}

.topbar {
    background: var(--primary);
    color: white;
    padding: 11px 18px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 10px;
}

.brand {
    font-size: 18px;
    font-weight: 800;
}

.brand small {
    display: block;
    font-size: 11px;
    font-weight: normal;
    margin-top: 3px;
}

.top-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 7px;
}

.btn {
    display: inline-block;
    padding: 8px 11px;
    border-radius: 6px;
    border: 0;
    text-decoration: none;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
}

.btn-light {
    background: white;
    color: var(--primary);
}

.btn-dark {
    background: #115e59;
    color: white;
}

.container {
    width: min(1440px, 98%);
    margin: 12px auto;
}

.page-heading {
    margin-bottom: 12px;
}

.page-heading h1 {
    font-size: 22px;
    margin: 0 0 4px;
}

.subtitle {
    font-size: 12px;
    color: var(--muted);
    margin: 0;
}

.stats {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 10px;
    margin-bottom: 13px;
}

.stat-card {
    background: white;
    border: 1px solid var(--border);
    border-top: 3px solid var(--primary-light);
    border-radius: 9px;
    padding: 12px 14px;
    min-width: 0;
}

.stat-label {
    font-size: 10px;
    color: var(--muted);
    font-weight: 700;
}

.stat-value {
    font-size: 21px;
    font-weight: 800;
    margin-top: 5px;
    overflow-wrap: anywhere;
}

.stat-note {
    font-size: 10px;
    color: var(--muted);
    margin-top: 3px;
}

.filter-box,
.panel {
    background: white;
    border: 1px solid var(--border);
    border-radius: 9px;
    padding: 13px 15px;
    margin-bottom: 13px;
    box-shadow: 0 2px 8px rgba(20, 50, 50, .03);
}

.filter-box form {
    display: flex;
    align-items: end;
    flex-wrap: wrap;
    gap: 9px;
}

.form-field {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 155px;
}

.form-field label {
    font-size: 11px;
    font-weight: 700;
}

.form-field input {
    padding: 8px;
    border: 1px solid #cbd8d7;
    border-radius: 6px;
    font-size: 12px;
}

.section-title {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    margin-bottom: 9px;
}

.section-title h2 {
    margin: 0;
    font-size: 16px;
}

.badge {
    background: #e0f2f1;
    color: #0f766e;
    border-radius: 20px;
    padding: 4px 7px;
    font-size: 10px;
    font-weight: 700;
}

.two-columns {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.table-wrap {
    width: 100%;
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
    white-space: nowrap;
}

th {
    text-align: left;
    background: #edf7f6;
    color: #315351;
    padding: 9px 8px;
    font-size: 10px;
    text-transform: uppercase;
}

td {
    padding: 8px;
    border-bottom: 1px solid #edf1f0;
}

tbody tr:hover {
    background: #f7fbfa;
}

.empty {
    text-align: center;
    padding: 16px;
    color: var(--muted);
    font-size: 12px;
}

.footer {
    text-align: center;
    padding: 9px;
    color: var(--muted);
    font-size: 10px;
}

@media (max-width: 950px) {
    .stats {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .two-columns {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 520px) {
    .container {
        width: 94%;
    }

    .stat-card {
        padding: 10px;
    }

    .stat-value {
        font-size: 18px;
    }

    .filter-box,
    .panel {
        padding: 10px;
    }

    .form-field {
        min-width: 100%;
    }
}

@media print {
    body {
        background: white;
    }

    .topbar {
        background: white !important;
        color: black !important;
        border-bottom: 1px solid #333;
    }

    .top-actions,
    .filter-box,
    .no-print {
        display: none !important;
    }

    .container {
        width: 100%;
        margin: 5px 0;
    }

    .panel,
    .stat-card {
        box-shadow: none;
        break-inside: avoid;
    }

    .table-wrap {
        overflow: visible;
    }

    table {
        font-size: 9px;
        white-space: normal;
    }

    th, td {
        padding: 5px;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        Local Discover &amp; Deliver
        <small>Admin Reports &amp; Analytics</small>
    </div>

    <div class="top-actions">
        <a class="btn btn-light" href="admin_dashboard.php">Dashboard</a>
        <button class="btn btn-dark" onclick="window.print()">Print / Save PDF</button>
    </div>
</header>

<main class="container">

    <section class="page-heading">
        <h1>Reports Dashboard</h1>
        <p class="subtitle">
            Database reports, order activity, payment summary and delivery overview.
        </p>
    </section>

    <!-- Date Filter -->
    <section class="filter-box no-print">
        <form method="GET">
            <div class="form-field">
                <label for="from">From Date</label>
                <input type="date" id="from" name="from"
                       value="<?= e($fromDate) ?>">
            </div>

            <div class="form-field">
                <label for="to">To Date</label>
                <input type="date" id="to" name="to"
                       value="<?= e($toDate) ?>">
            </div>

            <button type="submit" class="btn btn-dark">Apply Filter</button>
            <a href="reports.php" class="btn btn-light">Reset</a>
        </form>
    </section>

    <!-- Overview Cards: counts are plain numbers, not currency -->
    <section class="stats">

        <div class="stat-card">
            <div class="stat-label">Total Users</div>
            <div class="stat-value"><?= formatNumber($totalUsers) ?></div>
            <div class="stat-note">Registered users</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Restaurants</div>
            <div class="stat-value"><?= formatNumber($totalRestaurants) ?></div>
            <div class="stat-note">Registered restaurants</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Food Orders</div>
            <div class="stat-value"><?= formatNumber($totalFoodOrders) ?></div>
            <div class="stat-note">Orders in database</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Grocery Orders</div>
            <div class="stat-value"><?= formatNumber($totalGroceryOrders) ?></div>
            <div class="stat-note">Orders in database</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Print Orders</div>
            <div class="stat-value"><?= formatNumber($totalPrintOrders) ?></div>
            <div class="stat-note">Print requests in database</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Delivery Partners</div>
            <div class="stat-value"><?= formatNumber($totalDeliveryPartners) ?></div>
            <div class="stat-note">Registered delivery partners</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Food Order Total</div>
            <div class="stat-value"><?= formatMoney($foodSales) ?></div>
            <div class="stat-note">Sum of food_orders.total</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Grocery Order Total</div>
            <div class="stat-value"><?= formatMoney($grocerySales) ?></div>
            <div class="stat-note">Sum of the available order total column</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Print Order Total</div>
            <div class="stat-value"><?= formatMoney($printSales) ?></div>
            <div class="stat-note">Sum of print order amounts</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Payment Records Amount</div>
            <div class="stat-value"><?= formatMoney($totalPaymentsAmount) ?></div>
            <div class="stat-note">Sum of payment records</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Paid Amount</div>
            <div class="stat-value"><?= formatMoney($paidAmount) ?></div>
            <div class="stat-note"><?= formatNumber($paidCount) ?> paid payment records</div>
        </div>

        <div class="stat-card">
            <div class="stat-label">Pending Amount</div>
            <div class="stat-value"><?= formatMoney($pendingAmount) ?></div>
            <div class="stat-note"><?= formatNumber($pendingCount) ?> pending payment records</div>
        </div>

    </section>

    <!-- Food Orders -->
    <section class="panel">
        <div class="section-title">
            <h2>Food Orders</h2>
            <span class="badge"><?= formatNumber(count($foodOrders)) ?> recent records</span>
        </div>

        <?php renderTable($foodOrders, 'No food orders found.'); ?>
    </section>

    <!-- Grocery Orders -->
    <section class="panel">
        <div class="section-title">
            <h2>Grocery Orders</h2>
            <span class="badge"><?= formatNumber(count($groceryOrders)) ?> recent records</span>
        </div>

        <?php renderTable($groceryOrders, 'No grocery orders found.'); ?>
    </section>

    <!-- Print Orders -->
    <section class="panel">
        <div class="section-title">
            <h2>Print &amp; Photocopy Orders</h2>
            <span class="badge"><?= formatNumber(count($printOrders)) ?> recent records</span>
        </div>

        <?php renderTable($printOrders, 'No print orders found.'); ?>
    </section>

    <!-- Payment Records -->
    <section class="panel">
        <div class="section-title">
            <h2>Payment Records</h2>
            <span class="badge"><?= formatNumber(count($payments)) ?> recent records</span>
        </div>

        <?php renderTable($payments, 'No payment records found.'); ?>
    </section>

    <!-- Status Reports -->
    <div class="two-columns">

        <section class="panel">
            <div class="section-title">
                <h2>Food Order Status</h2>
            </div>

            <?php renderTable($foodStatusRows, 'No food order status data available.'); ?>
        </section>

        <section class="panel">
            <div class="section-title">
                <h2>Grocery Order Status</h2>
            </div>

            <?php renderTable($groceryStatusRows, 'No grocery order status data available.'); ?>
        </section>

    </div>

    <section class="panel">
        <div class="section-title">
            <h2>Print Order Status</h2>
        </div>

        <?php renderTable($printStatusRows, 'No print order status data available.'); ?>
    </section>

    <!-- Delivery Partner Overview -->
    <section class="panel">
        <div class="section-title">
            <h2>Delivery Partner Overview</h2>
            <span class="badge">
                <?= formatNumber(count($deliveryPartners)) ?> recent records
            </span>
        </div>

        <div class="stat-card" style="margin-bottom:12px;">
            <div class="stat-label">Recorded Delivery Partner Earnings</div>
            <div class="stat-value"><?= formatMoney($deliveryEarnings) ?></div>
            <div class="stat-note">Sum of delivery_partners.total_earnings</div>
        </div>

        <?php renderTable($deliveryPartners, 'No delivery partner records found.'); ?>
    </section>

</main>

<footer class="footer">
    Local Discover &amp; Deliver &copy; <?= date('Y') ?> |
    Admin Reports
</footer>

</body>
</html>