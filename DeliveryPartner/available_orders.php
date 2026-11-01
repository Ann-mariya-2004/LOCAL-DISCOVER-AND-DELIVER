<?php
session_start();
require_once "../backend/db.php";

if (
    !isset($_SESSION["user_id"]) ||
    strcasecmp(trim($_SESSION["role"] ?? ""), "Delivery Partner") !== 0
) {
    header("Location: ../login_registration.html");
    exit;
}

function e($value): string {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$notice = $_SESSION["delivery_message"] ?? "";
unset($_SESSION["delivery_message"]);

/* Load eligible Paid and COD Pending orders */
$sql = "
    SELECT
        fo.id,
        fo.food_name,
        fo.quantity,
        fo.total,
        fo.order_date,
        fo.delivery_fee,
        fo.restaurant_id,
        fo.user_id,
        r.restaurant_name AS restaurant_name,
        r.location AS restaurant_location,
        u.name AS customer_name,
        u.phone AS customer_phone,
        u.address AS customer_address
    FROM food_orders fo
    LEFT JOIN restaurants r ON r.id = fo.restaurant_id
    LEFT JOIN users u ON u.id = fo.user_id
    WHERE fo.delivery_partner_id IS NULL
      AND fo.delivery_status = 'Available'
      AND (
          fo.payment_status = 'Paid'
          OR (
              LOWER(TRIM(fo.payment_method)) IN ('cod', 'cash on delivery')
              AND fo.payment_status = 'Pending'
          )
      )
    ORDER BY fo.order_date DESC, fo.id DESC
";

$result = mysqli_query($conn, $sql);
$query_error = !$result;

if ($query_error) {
    error_log("LDD available orders error: " . mysqli_error($conn));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Available Orders | LDD</title>

<style>
*{box-sizing:border-box;margin:0;padding:0}

body{
    font-family:Arial,Helvetica,sans-serif;
    background:#f3f7f6;
    color:#263a35;
    font-size:13px;
}

header{
    background:#0f766e;
    color:#fff;
    padding:11px 3%;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
}

.brand{font-size:19px;font-weight:700}

nav{display:flex;gap:5px;flex-wrap:wrap}

nav a{
    color:#fff;
    text-decoration:none;
    padding:7px 9px;
    border-radius:5px;
    font-size:12px;
}

nav a:hover,nav a.active{background:#ffffff25}

main{
    width:96%;
    max-width:1500px;
    margin:17px auto;
}

.heading{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    margin-bottom:13px;
    flex-wrap:wrap;
}

.heading h1{
    font-size:22px;
    color:#14574f;
    margin-bottom:3px;
}

.heading p{font-size:12px;color:#71807b}

.count{
    background:#d9f3ec;
    color:#11665d;
    padding:7px 10px;
    border-radius:6px;
    font-size:12px;
    font-weight:700;
}

.notice{
    padding:9px 11px;
    border-radius:6px;
    background:#e0f2fe;
    color:#075985;
    margin-bottom:12px;
}

.orders{
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:10px;
    align-items:start;
}

.card{
    min-width:0;
    background:#fff;
    border:1px solid #dfe9e5;
    border-radius:9px;
    padding:11px;
    box-shadow:0 2px 5px #173e3509;
}

.restaurant{
    font-size:14px;
    line-height:1.35;
    font-weight:700;
    color:#14574f;
    overflow-wrap:anywhere;
    margin-bottom:3px;
}

.order-id{
    font-size:11px;
    color:#7a8983;
}

.card-head{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:6px;
    margin-bottom:9px;
}

.badge{
    flex-shrink:0;
    font-size:10px;
    background:#d9f3ec;
    color:#11665d;
    padding:4px 6px;
    border-radius:20px;
    font-weight:700;
}

.food{
    background:#f5f9f7;
    border-radius:6px;
    padding:8px;
    margin-bottom:8px;
}

.food-name{
    font-weight:700;
    font-size:13px;
    overflow-wrap:anywhere;
    margin-bottom:3px;
}

.food-qty{font-size:11px;color:#687a73}

.info{
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:8px;
    margin-bottom:9px;
}

.item{min-width:0}

.label{
    font-size:10px;
    color:#829089;
    margin-bottom:3px;
}

.value{
    font-size:11px;
    line-height:1.4;
    overflow-wrap:anywhere;
}

.location{
    border-top:1px solid #edf1ef;
    padding-top:8px;
    margin-top:2px;
}

.location .item{margin-bottom:7px}

.bottom{
    border-top:1px solid #edf1ef;
    padding-top:9px;
    margin-top:4px;
}

.total-line{
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:6px;
    margin-bottom:3px;
}

.total-label{font-size:11px;color:#71807b}

.total{
    font-size:16px;
    font-weight:700;
    color:#0f766e;
}

.fee{
    font-size:11px;
    color:#65766f;
    margin-bottom:9px;
}

.accept-btn{
    display:block;
    width:100%;
    border:0;
    background:#0f766e;
    color:white;
    border-radius:6px;
    padding:9px 7px;
    font-size:12px;
    font-weight:700;
    cursor:pointer;
}

.accept-btn:hover{background:#115e59}

.empty{
    background:#fff;
    border:1px solid #e1eae7;
    border-radius:9px;
    padding:30px 12px;
    text-align:center;
}

.empty h3{font-size:17px;color:#14574f;margin-bottom:6px}
.empty p{font-size:12px;color:#71807b;line-height:1.5}

.error{
    background:#fee2e2;
    color:#991b1b;
    border-radius:7px;
    padding:12px;
}

/* Responsive layout */
@media(max-width:1150px){
    .orders{grid-template-columns:repeat(3,minmax(0,1fr))}
}

@media(max-width:800px){
    .orders{grid-template-columns:repeat(2,minmax(0,1fr))}
    main{width:94%}
}

@media(max-width:480px){
    .orders{grid-template-columns:1fr}
    header{padding:11px 4%}
    main{width:92%;margin:14px auto}
    .heading h1{font-size:20px}
}
</style>
</head>

<body>
<header>
    <div class="brand">LDD · Delivery Partner</div>
    <nav>
        <a href="delivery_dashboard.php">Dashboard</a>
        <a href="available_orders.php" class="active">Available Orders</a>
        <a href="my_deliveries.php">My Deliveries</a>
        <a href="logout.php">Logout</a>
    </nav>
</header>

<main>
    <div class="heading">
        <div>
            <h1>Available Orders</h1>
            <p>View orders and accept deliveries.</p>
        </div>

        <?php if (!$query_error): ?>
            <div class="count">
                <?= (int)mysqli_num_rows($result) ?> Available Orders
            </div>
        <?php endif; ?>
    </div>

    <?php if (is_array($notice)): ?>
        <div class="notice"><?= e($notice["text"] ?? "") ?></div>
    <?php elseif ($notice !== ""): ?>
        <div class="notice"><?= e($notice) ?></div>
    <?php endif; ?>

    <?php if ($query_error): ?>

        <div class="error">
            Unable to load orders. Please check the database query and column names.
        </div>

    <?php elseif (mysqli_num_rows($result) === 0): ?>

        <div class="empty">
            <h3>No available orders</h3>
            <p>Eligible Paid and COD Pending orders will appear here.</p>
        </div>

    <?php else: ?>

        <div class="orders">

        <?php while ($order = mysqli_fetch_assoc($result)): ?>
            <?php
                $orderId = (int)$order["id"];
                $fee = (float)($order["delivery_fee"] ?? 0);

                $restaurantName = trim((string)($order["restaurant_name"] ?? ""));
                if ($restaurantName === "") {
                    $restaurantName = "Restaurant name unavailable";
                }

                $restaurantLocation = trim((string)($order["restaurant_location"] ?? ""));
                if ($restaurantLocation === "") {
                    $restaurantLocation = "Not provided";
                }
            ?>

            <article class="card">

                <div class="card-head">
                    <div>
                        <div class="restaurant"><?= e($restaurantName) ?></div>
                        <div class="order-id">Order #<?= $orderId ?></div>
                    </div>
                    <span class="badge">Available</span>
                </div>

                <div class="food">
                    <div class="food-name"><?= e($order["food_name"] ?: "Food order") ?></div>
                    <div class="food-qty">Quantity: <?= e($order["quantity"] ?? "—") ?></div>
                </div>

                <div class="info">
                    <div class="item">
                        <div class="label">Customer</div>
                        <div class="value"><?= e($order["customer_name"] ?: "Not available") ?></div>
                    </div>

                    <div class="item">
                        <div class="label">Phone</div>
                        <div class="value"><?= e($order["customer_phone"] ?: "Not provided") ?></div>
                    </div>
                </div>

                <div class="location">
                    <div class="item">
                        <div class="label">Restaurant location</div>
                        <div class="value"><?= e($restaurantLocation) ?></div>
                    </div>

                    <div class="item">
                        <div class="label">Delivery address</div>
                        <div class="value"><?= e($order["customer_address"] ?: "Not provided") ?></div>
                    </div>

                    <div class="item">
                        <div class="label">Order date</div>
                        <div class="value"><?= e($order["order_date"] ?: "Not available") ?></div>
                    </div>
                </div>

                <div class="bottom">
                    <div class="total-line">
                        <span class="total-label">Order total</span>
                        <span class="total">₹<?= number_format((float)$order["total"], 2) ?></span>
                    </div>

                    <div class="fee">
                        Delivery fee:
                        <?= $fee > 0 ? "₹" . number_format($fee, 2) : "Not set" ?>
                    </div>

                    <form action="accept_delivery.php" method="POST"
                          onsubmit="return confirm('Accept order #<?= $orderId ?>?');">
                        <input type="hidden" name="order_id" value="<?= $orderId ?>">
                        <button type="submit" class="accept-btn">Accept Order</button>
                    </form>
                </div>

            </article>

        <?php endwhile; ?>

        </div>
    <?php endif; ?>
</main>
</body>
</html>