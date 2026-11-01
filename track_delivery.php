<?php
// Start the session and connect to the LDD database.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/backend/db.php";

// Only logged-in customers can view their own orders.
if (empty($_SESSION['user_id'])) {
    header("Location: login_registration.html");
    exit;
}

$userId = (int) $_SESSION['user_id'];

// Escape output before displaying database values in HTML.
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Return the current status in a consistent format.
function getOrderStatus($status): string {
    $status = strtolower(trim((string)$status));

    if (strpos($status, 'cancel') !== false ||
        strpos($status, 'reject') !== false) {
        return 'Cancelled';
    }

    if (strpos($status, 'deliver') !== false) {
        return 'Delivered';
    }

    if (strpos($status, 'out for') !== false ||
        strpos($status, 'picked') !== false) {
        return 'Out for Delivery';
    }

    if (strpos($status, 'assign') !== false ||
        strpos($status, 'accept') !== false) {
        return 'Delivery Partner Assigned';
    }

    if (strpos($status, 'confirm') !== false ||
        strpos($status, 'prepar') !== false ||
        strpos($status, 'process') !== false) {
        return 'Order Confirmed';
    }

    return 'Order Placed';
}

// Determine the timeline step reached by the order.
function getStatusStep($status): int {
    switch ($status) {
        case 'Order Confirmed':
            return 1;
        case 'Delivery Partner Assigned':
            return 2;
        case 'Out for Delivery':
            return 3;
        case 'Delivered':
            return 4;
        default:
            return 0;
    }
}

// Collect food and print orders for the logged-in user.
$orders = [];

// Food orders.
$foodSql = "
    SELECT
        id,
        food_name AS item_name,
        quantity,
        total AS amount,
        order_date AS created_at,
        delivery_status,
        delivery_partner_id,
        '' AS address,
        'food' AS order_type
    FROM food_orders
    WHERE user_id = ?
";

$foodStmt = mysqli_prepare($conn, $foodSql);

if ($foodStmt) {
    mysqli_stmt_bind_param($foodStmt, "i", $userId);
    mysqli_stmt_execute($foodStmt);
    $foodResult = mysqli_stmt_get_result($foodStmt);

    while ($row = mysqli_fetch_assoc($foodResult)) {
        $row['display_status'] = getOrderStatus($row['delivery_status'] ?? '');
        $orders[] = $row;
    }

    mysqli_stmt_close($foodStmt);
}

// Print orders.
$printSql = "
    SELECT
        id,
        document_name AS item_name,
        copies AS quantity,
        total_amount AS amount,
        created_at,
        status,
        delivery_status,
        delivery_partner_id,
        address,
        color_type,
        paper_size,
        'print' AS order_type
    FROM print_orders
    WHERE user_id = ?
";

$printStmt = mysqli_prepare($conn, $printSql);

if ($printStmt) {
    mysqli_stmt_bind_param($printStmt, "i", $userId);
    mysqli_stmt_execute($printStmt);
    $printResult = mysqli_stmt_get_result($printStmt);

    while ($row = mysqli_fetch_assoc($printResult)) {
        // Use delivery status when available; otherwise use the order status.
        $status = !empty($row['delivery_status'])
            ? $row['delivery_status']
            : ($row['status'] ?? '');

        $row['display_status'] = getOrderStatus($status);
        $orders[] = $row;
    }

    mysqli_stmt_close($printStmt);
}

// Show the newest orders first.
usort($orders, function ($a, $b) {
    return strtotime($b['created_at'] ?? '') <=> strtotime($a['created_at'] ?? '');
});

// Find a delivery partner for an order.
// The lookup supports delivery_partners.id and delivery_partners.user_id.
function getPartnerDetails($conn, $partnerId): ?array {
    if (empty($partnerId)) {
        return null;
    }

    $sql = "
        SELECT
            dp.user_id,
            dp.vehicle_type,
            dp.vehicle_number,
            u.name,
            u.phone
        FROM delivery_partners dp
        LEFT JOIN users u ON u.id = dp.user_id
        WHERE dp.id = ?
        LIMIT 1
    ";

    $stmt = mysqli_prepare($conn, $sql);

    if (!$stmt) {
        return null;
    }

    $partnerId = (int)$partnerId;
    mysqli_stmt_bind_param($stmt, "i", $partnerId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $partner = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);

    return $partner ?: null;
}

// Create a phone link only for a valid phone value.
function phoneLink($phone): string {
    $number = preg_replace('/[^0-9+]/', '', (string)$phone);
    return $number;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | Track My Orders</title>

<style>
:root {
    --green: #0f766e;
    --green-light: #e6f7f3;
    --text: #172b2b;
    --muted: #718080;
    --border: #e3ecea;
    --page: #f5f8f8;
}

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: var(--page);
    color: var(--text);
}

.page {
    max-width: 1500px;
    margin: 0 auto;
    padding: 26px;
}

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 16px;
    margin-bottom: 22px;
}

.page-header h1 {
    margin: 0 0 7px;
    font-size: 27px;
}

.page-header p {
    margin: 0;
    color: var(--muted);
    font-size: 14px;
}

.back-btn {
    text-decoration: none;
    background: white;
    border: 1px solid var(--border);
    color: var(--green);
    border-radius: 9px;
    padding: 10px 14px;
    font-size: 13px;
    font-weight: bold;
    white-space: nowrap;
}

.order-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 16px;
}

.order-card {
    min-width: 0;
    background: white;
    border: 1px solid var(--border);
    border-radius: 15px;
    padding: 17px;
    box-shadow: 0 4px 15px rgba(22, 57, 54, 0.04);
    display: flex;
    flex-direction: column;
    align-self: start;
}

.card-top {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin-bottom: 12px;
}

.order-icon {
    width: 40px;
    height: 40px;
    display: grid;
    place-items: center;
    background: var(--green-light);
    border-radius: 11px;
    font-size: 21px;
    flex-shrink: 0;
}

.order-title {
    font-size: 14px;
    font-weight: 700;
    margin: 2px 0 5px;
    overflow-wrap: anywhere;
}

.item-name {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.amount {
    font-size: 23px;
    font-weight: 800;
    margin: 4px 0 10px;
}

.date {
    font-size: 11px;
    color: var(--muted);
    margin-bottom: 12px;
}

.status-badge {
    display: inline-flex;
    align-self: flex-start;
    align-items: center;
    gap: 6px;
    border-radius: 20px;
    padding: 7px 10px;
    font-size: 11px;
    line-height: 1.3;
    font-weight: 700;
    margin-bottom: 12px;
}

.status-placed {
    color: #a65c00;
    background: #fff2d9;
}

.status-confirmed,
.status-assigned {
    color: #1858a8;
    background: #e8f1ff;
}

.status-out {
    color: #075985;
    background: #e0f2fe;
}

.status-delivered {
    color: #167344;
    background: #e2f8eb;
}

.status-cancelled {
    color: #b42318;
    background: #feeceb;
}

.address {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
    margin: 0 0 14px;
    overflow-wrap: anywhere;
}

.details-btn {
    width: 100%;
    margin-top: auto;
    padding: 10px 12px;
    border: 0;
    border-radius: 9px;
    background: var(--green);
    color: white;
    cursor: pointer;
    font-size: 12px;
    font-weight: 700;
}

.details-btn:hover {
    background: #115e59;
}

.details-panel {
    display: none;
    border-top: 1px solid var(--border);
    margin-top: 15px;
    padding-top: 15px;
}

.details-panel.open {
    display: block;
}

.details-heading {
    font-size: 12px;
    font-weight: 800;
    margin: 0 0 13px;
}

.timeline {
    margin: 0 0 17px 4px;
}

.timeline-step {
    display: flex;
    align-items: flex-start;
    gap: 9px;
    position: relative;
    min-height: 31px;
    font-size: 11px;
    color: #99a6a5;
}

.timeline-step:not(:last-child)::before {
    content: "";
    position: absolute;
    left: 5px;
    top: 12px;
    height: 21px;
    border-left: 2px solid #e0e8e7;
}

.timeline-step.done {
    color: var(--green);
    font-weight: 700;
}

.timeline-step.done:not(:last-child)::before {
    border-left-color: #70c7b9;
}

.timeline-dot {
    position: relative;
    z-index: 1;
    width: 12px;
    height: 12px;
    flex-shrink: 0;
    margin-top: 1px;
    border: 2px solid #cbd5d4;
    background: white;
    border-radius: 50%;
}

.timeline-step.done .timeline-dot {
    border-color: var(--green);
    background: var(--green);
}

.partner-box {
    background: #f7faf9;
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 12px;
    font-size: 12px;
    line-height: 1.6;
    overflow-wrap: anywhere;
}

.partner-heading {
    font-weight: 800;
    margin-bottom: 6px;
}

.call-btn {
    display: inline-block;
    margin-top: 8px;
    padding: 7px 10px;
    background: #dcfce7;
    color: #166534;
    border-radius: 7px;
    text-decoration: none;
    font-size: 11px;
    font-weight: 700;
}

.empty-state {
    background: white;
    border: 1px dashed #cbd8d6;
    border-radius: 14px;
    padding: 45px 20px;
    text-align: center;
    color: var(--muted);
}

.empty-state h2 {
    color: var(--text);
    margin-top: 0;
}

@media (max-width: 1100px) {
    .order-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 600px) {
    .page {
        padding: 15px;
    }

    .page-header {
        align-items: flex-start;
    }

    .page-header h1 {
        font-size: 22px;
    }

    .order-grid {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .order-card {
        padding: 15px;
    }
}
</style>
</head>

<body>
<main class="page">

    <header class="page-header">
        <div>
            <h1>Track My Orders</h1>
            <p>Follow your order progress and contact your delivery partner.</p>
        </div>

        <a class="back-btn" href="dashboard.php">← Dashboard</a>
    </header>

    <?php if (empty($orders)): ?>

        <section class="empty-state">
            <h2>No orders yet</h2>
            <p>Your food and print orders will appear here.</p>
        </section>

    <?php else: ?>

        <section class="order-grid">

        <?php foreach ($orders as $index => $order): ?>
            <?php
                $type = $order['order_type'] ?? 'food';
                $status = $order['display_status'] ?? 'Order Placed';
                $step = getStatusStep($status);

                $icon = ($type === 'print') ? '🖨️' : '🍔';
                $typeLabel = ($type === 'print') ? 'Print Order' : 'Food Order';
                $orderId = (int)($order['id'] ?? 0);
                $amount = (float)($order['amount'] ?? 0);
                $quantity = (int)($order['quantity'] ?? 1);
                $createdAt = $order['created_at'] ?? '';

                $itemName = $order['item_name'] ?? 'Order item';

                if ($type === 'print') {
                    $itemDescription = $itemName . ' · ' . $quantity . ' copies';

                    if (!empty($order['paper_size'])) {
                        $itemDescription .= ' · ' . $order['paper_size'];
                    }
                    if (!empty($order['color_type'])) {
                        $itemDescription .= ' · ' . $order['color_type'];
                    }
                } else {
                    $itemDescription = $itemName . ' · Qty ' . $quantity;
                }

                $statusClass = 'status-placed';

                if ($status === 'Order Confirmed') {
                    $statusClass = 'status-confirmed';
                } elseif ($status === 'Delivery Partner Assigned') {
                    $statusClass = 'status-assigned';
                } elseif ($status === 'Out for Delivery') {
                    $statusClass = 'status-out';
                } elseif ($status === 'Delivered') {
                    $statusClass = 'status-delivered';
                } elseif ($status === 'Cancelled') {
                    $statusClass = 'status-cancelled';
                }

                $address = $order['address'] ?? '';
                $partner = getPartnerDetails($conn, $order['delivery_partner_id'] ?? null);
                $panelId = 'order-details-' . $type . '-' . $orderId . '-' . $index;
            ?>

            <article class="order-card">

                <div class="card-top">
                    <div class="order-icon"><?= $icon ?></div>
                    <div>
                        <div class="order-title">
                            <?= e($typeLabel) ?> #<?= $orderId ?>
                        </div>
                        <div class="item-name"><?= e($itemDescription) ?></div>
                    </div>
                </div>

                <div class="amount">₹<?= number_format($amount, 2) ?></div>

                <div class="date">
                    <?= $createdAt ? e(date('d M Y, g:i A', strtotime($createdAt))) : 'Date unavailable' ?>
                </div>

                <div class="status-badge <?= e($statusClass) ?>">
                    ● <?= e($status) ?>
                </div>

                <?php if ($address !== ''): ?>
                    <p class="address">📍 <?= e($address) ?></p>
                <?php endif; ?>

                <button
                    class="details-btn"
                    type="button"
                    aria-expanded="false"
                    aria-controls="<?= e($panelId) ?>"
                    onclick="toggleDetails(this, '<?= e($panelId) ?>')">
                    View Details ＋
                </button>

                <div class="details-panel" id="<?= e($panelId) ?>">

                    <h3 class="details-heading">ORDER TRACKING</h3>

                    <?php if ($status === 'Cancelled'): ?>

                        <div class="partner-box">
                            This order has been cancelled. Please check the order status with the administrator if you need help.
                        </div>

                    <?php else: ?>

                        <div class="timeline">
                            <?php
                            $steps = [
                                'Order Placed',
                                'Order Confirmed',
                                'Delivery Partner Assigned',
                                'Out for Delivery',
                                'Delivered'
                            ];

                            foreach ($steps as $stepIndex => $stepLabel):
                                $isDone = ($stepIndex <= $step);
                            ?>
                                <div class="timeline-step <?= $isDone ? 'done' : '' ?>">
                                    <span class="timeline-dot"></span>
                                    <span><?= e($stepLabel) ?></span>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="partner-box">
                            <div class="partner-heading">🚚 DELIVERY PARTNER</div>

                            <?php if ($partner): ?>

                                <strong><?= e($partner['name'] ?? 'Delivery partner') ?></strong><br>

                                <?php if (!empty($partner['vehicle_type'])): ?>
                                    <?= e($partner['vehicle_type']) ?>
                                <?php endif; ?>

                                <?php if (!empty($partner['vehicle_number'])): ?>
                                    <?= e($partner['vehicle_number']) ?>
                                <?php endif; ?>

                                <?php
                                    $phone = phoneLink($partner['phone'] ?? '');
                                ?>

                                <?php if ($phone !== ''): ?>
                                    <br>
                                    <a class="call-btn" href="tel:<?= e($phone) ?>">☎ Call</a>
                                <?php else: ?>
                                    <br><span>Phone number not available.</span>
                                <?php endif; ?>

                            <?php else: ?>

                                Delivery partner has not been assigned yet.

                            <?php endif; ?>
                        </div>

                    <?php endif; ?>

                </div>

            </article>

        <?php endforeach; ?>

        </section>

    <?php endif; ?>

</main>

<script>
// Expand or collapse one order's tracking details.
function toggleDetails(button, panelId) {
    const panel = document.getElementById(panelId);

    if (!panel) {
        return;
    }

    const isOpen = panel.classList.toggle('open');

    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    button.textContent = isOpen ? 'Hide Details −' : 'View Details ＋';
}
</script>

</body>
</html>