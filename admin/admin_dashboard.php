
<?php
session_start();
require_once __DIR__ . "/../backend/db.php";

/* =========================
   ADMIN ACCESS PROTECTION
========================= */
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    strcasecmp(trim($_SESSION["role"]), "Admin") !== 0
) {
    header("Location: ../login_registration.html");
    exit;
}

/* =========================
   HELPER FUNCTIONS
========================= */
function e($value)
{
    return htmlspecialchars((string)($value ?? "-"), ENT_QUOTES, "UTF-8");
}

function getCount($conn, $table)
{
    $allowedTables = [
        "users", "restaurants", "food_items", "restaurant_menu",
        "food_orders", "payments", "print_orders", "feedback",
        "delivery_partners", "services"
    ];

    if (!in_array($table, $allowedTables, true)) {
        return 0;
    }

    $result = mysqli_query($conn, "SELECT COUNT(*) AS total FROM `$table`");

    if (!$result) {
        error_log("Admin count query failed: " . mysqli_error($conn));
        return 0;
    }

    $row = mysqli_fetch_assoc($result);
    return (int)($row["total"] ?? 0);
}

/* =========================
   DASHBOARD MENU
========================= */
$views = [
    "overview"          => "Dashboard Overview",
    "users"             => "All User Accounts",
    "customers"         => "Customers",
    "vendors"           => "Vendors",
    "delivery_users"    => "Delivery Partner Accounts",
    "delivery_partners" => "Delivery Partner Details",
    "restaurants"       => "Restaurants",
    "food_items"        => "Food Items",
    "restaurant_menu"   => "Restaurant Menu",
    "food_orders"       => "Food Orders",
    "payments"          => "Payments",
    "print_orders"      => "Print Orders",
    "feedback"          => "Customer Feedback",
    "services"          => "Services / Grocery"
];

$menuIcons = [
    "overview"          => "DB",
    "users"             => "US",
    "customers"         => "CU",
    "vendors"           => "VE",
    "delivery_users"    => "DP",
    "delivery_partners" => "DT",
    "restaurants"       => "RE",
    "food_items"        => "FO",
    "restaurant_menu"   => "MN",
    "food_orders"       => "OR",
    "payments"          => "PA",
    "print_orders"      => "PR",
    "feedback"          => "FB",
    "services"          => "GR"
];

$view = $_GET["view"] ?? "overview";

if (!array_key_exists($view, $views)) {
    $view = "overview";
}

$adminName = $_SESSION["name"] ?? "Administrator";
$rows = [];
$columns = [];
$loadError = false;

/* =========================
   LOAD SELECTED SECTION
========================= */
if ($view !== "overview") {

    $tableMap = [
        "users"             => "users",
        "customers"         => "users",
        "vendors"           => "users",
        "delivery_users"    => "users",
        "delivery_partners" => "delivery_partners",
        "restaurants"       => "restaurants",
        "food_items"        => "food_items",
        "restaurant_menu"   => "restaurant_menu",
        "food_orders"       => "food_orders",
        "payments"          => "payments",
        "print_orders"      => "print_orders",
        "feedback"          => "feedback",
        "services"          => "services"
    ];

    $table = $tableMap[$view] ?? null;

    if ($table !== null) {

        /* =========================
           CUSTOMER FEEDBACK
           Show feedback with customer details
        ========================= */
        if ($view === "feedback") {

            $sql = "
                SELECT
                    f.id AS feedback_id,
                    u.name AS customer_name,
                    u.email AS customer_email,
                    f.rating,
                    f.message,
                    f.created_at
                FROM feedback f
                LEFT JOIN users u
                    ON f.user_id = u.id
                ORDER BY f.created_at DESC
                LIMIT 200
            ";

            $result = mysqli_query($conn, $sql);

            if ($result) {

                while ($row = mysqli_fetch_assoc($result)) {
                    $rows[] = $row;
                }

            } else {

                error_log(
                    "Admin feedback query failed: " .
                    mysqli_error($conn)
                );

                $loadError = true;
            }

        }

        /* =========================
           FILTER USER ACCOUNTS BY ROLE
        ========================= */
        elseif (
            in_array(
                $view,
                ["customers", "vendors", "delivery_users"],
                true
            )
        ) {

            $roleMap = [
                "customers"      => "customer",
                "vendors"        => "vendor",
                "delivery_users" => "delivery partner"
            ];

            $role = $roleMap[$view];

            $stmt = mysqli_prepare(
                $conn,
                "SELECT * FROM users
                 WHERE LOWER(TRIM(role)) = ?
                 ORDER BY id DESC
                 LIMIT 200"
            );

            if ($stmt) {

                mysqli_stmt_bind_param($stmt, "s", $role);

                if (mysqli_stmt_execute($stmt)) {

                    $result = mysqli_stmt_get_result($stmt);

                    if ($result) {

                        while ($row = mysqli_fetch_assoc($result)) {

                            // Never display password data in the admin table.
                            unset($row["password"]);

                            $rows[] = $row;
                        }

                    } else {

                        $loadError = true;
                    }

                } else {

                    error_log(
                        "Admin user list query failed: " .
                        mysqli_stmt_error($stmt)
                    );

                    $loadError = true;
                }

                mysqli_stmt_close($stmt);

            } else {

                error_log(
                    "Admin user query preparation failed: " .
                    mysqli_error($conn)
                );

                $loadError = true;
            }

        }

        /* =========================
           ALL OTHER ADMIN SECTIONS
        ========================= */
        else {

            $result = mysqli_query(
                $conn,
                "SELECT * FROM `$table` ORDER BY 1 DESC LIMIT 200"
            );

            if ($result) {

                while ($row = mysqli_fetch_assoc($result)) {

                    // Never display password data in the admin table.
                    unset($row["password"]);

                    $rows[] = $row;
                }

            } else {

                error_log(
                    "Admin list query failed: " .
                    mysqli_error($conn)
                );

                $loadError = true;
            }
        }

        /* =========================
           PREPARE TABLE COLUMNS
        ========================= */
        if (!empty($rows)) {
            $columns = array_keys($rows[0]);
        }
    }
}

/* =========================
   DASHBOARD STATISTICS
========================= */
$cards = [
    ["Registered Users", getCount($conn, "users"), "users", "US"],
    ["Restaurants", getCount($conn, "restaurants"), "restaurants", "RE"],
    ["Food Items", getCount($conn, "food_items"), "food_items", "FO"],
    ["Food Orders", getCount($conn, "food_orders"), "food_orders", "OR"],
    ["Payments", getCount($conn, "payments"), "payments", "PA"],
    ["Print Orders", getCount($conn, "print_orders"), "print_orders", "PR"],
    ["Delivery Partners", getCount($conn, "delivery_partners"), "delivery_partners", "DP"],
    ["Feedback Entries", getCount($conn, "feedback"), "feedback", "FB"],
    ["Services / Grocery", getCount($conn, "services"), "services", "GR"]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>LDD | Admin Dashboard</title>

<style>
:root {
    --primary: #0f766e;
    --dark: #115e59;
    --light: #ccfbf1;
    --background: #f1f5f9;
    --text: #1e293b;
    --muted: #64748b;
    --border: #e2e8f0;
    --white: #ffffff;
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

/* TOP BAR */
.topbar {
    min-height: 66px;
    padding: 10px 22px;
    background: var(--dark);
    color: var(--white);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    flex-wrap: wrap;
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
}

.brand-logo {
    width: 38px;
    height: 38px;
    display: grid;
    place-items: center;
    border-radius: 9px;
    background: var(--white);
    color: var(--dark);
    font-size: 15px;
    font-weight: bold;
}

.brand h1 {
    font-size: 19px;
    margin: 0;
}

.brand p {
    margin: 3px 0 0;
    color: #ccfbf1;
    font-size: 11px;
}

.top-actions {
    display: flex;
    align-items: center;
    gap: 9px;
}

.admin-badge {
    padding: 8px 10px;
    border: 1px solid #5eead4;
    border-radius: 6px;
    font-size: 12px;
}

.logout {
    display: inline-block;
    padding: 8px 12px;
    background: var(--white);
    color: var(--dark);
    text-decoration: none;
    font-size: 12px;
    font-weight: bold;
    border-radius: 6px;
}

.logout:hover {
    background: var(--light);
}

/* PAGE LAYOUT */
.layout {
    display: grid;
    grid-template-columns: 220px minmax(0, 1fr);
    min-height: calc(100vh - 66px);
}

.sidebar {
    padding: 13px 10px;
    background: var(--white);
    border-right: 1px solid var(--border);
}

.menu-heading {
    padding: 0 10px;
    margin: 5px 0 9px;
    color: var(--muted);
    font-size: 10px;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.sidebar a {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 3px;
    padding: 9px 10px;
    border-radius: 7px;
    color: #475569;
    text-decoration: none;
    font-size: 12px;
    transition: background .15s;
}

.sidebar a:hover,
.sidebar a.active {
    background: var(--light);
    color: var(--dark);
    font-weight: bold;
}

.menu-icon {
    width: 24px;
    flex-shrink: 0;
    text-align: center;
    font-size: 10px;
    font-weight: bold;
}

/* REPORTS MENU */
.sidebar a.reports-link {
    margin-top: 8px;
    border-top: 1px solid var(--border);
    padding-top: 12px;
}

.sidebar a.reports-link.active {
    background: var(--light);
    color: var(--dark);
    font-weight: bold;
}

/* MAIN CONTENT */
main {
    min-width: 0;
    padding: 20px 22px;
}

.page-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 15px;
}

.page-heading h2 {
    margin: 0;
    color: #134e4a;
    font-size: 22px;
}

.page-heading p {
    margin: 5px 0 0;
    color: var(--muted);
    line-height: 1.4;
    font-size: 12px;
}

.today-label {
    background: var(--light);
    color: var(--dark);
    border-radius: 6px;
    padding: 8px 10px;
    font-size: 10px;
    font-weight: bold;
    white-space: nowrap;
}

.section-label {
    margin: 0 0 10px;
    color: #334155;
    font-size: 14px;
    font-weight: bold;
}

/* STATISTICS CARDS */
.cards {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 10px;
}

.stat-card {
    min-width: 0;
    display: block;
    padding: 13px 14px;
    border-radius: 9px;
    background: var(--white);
    border: 1px solid var(--border);
    text-decoration: none;
    color: inherit;
    box-shadow: 0 2px 5px rgba(15, 23, 42, .025);
    transition: border-color .15s, box-shadow .15s;
}

.stat-card:hover {
    border-color: #5eead4;
    box-shadow: 0 4px 10px rgba(15, 23, 42, .06);
}

.stat-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 7px;
}

.stat-title {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.35;
}

.stat-icon {
    width: 29px;
    height: 29px;
    flex-shrink: 0;
    display: grid;
    place-items: center;
    background: var(--light);
    color: var(--dark);
    border-radius: 7px;
    font-size: 9px;
    font-weight: bold;
}

.stat-number {
    margin-top: 9px;
    font-size: 25px;
    font-weight: bold;
    color: var(--dark);
}

.stat-link {
    margin-top: 5px;
    font-size: 11px;
    color: var(--primary);
}

/* TABLE SECTIONS */
.table-panel {
    margin-top: 4px;
    padding: 16px;
    border-radius: 9px;
    background: var(--white);
    border: 1px solid var(--border);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
}

.table-header h3 {
    margin: 0;
    color: #134e4a;
    font-size: 16px;
}

.record-count {
    color: var(--muted);
    font-size: 11px;
}

.search-box {
    width: 100%;
    max-width: 300px;
    margin-top: 12px;
    padding: 9px 11px;
    border: 1px solid #cbd5e1;
    border-radius: 6px;
    outline-color: var(--primary);
    font-size: 12px;
}

.table-wrap {
    margin-top: 12px;
    overflow: auto;
    max-height: 600px;
    border: 1px solid var(--border);
    border-radius: 7px;
}

table {
    width: 100%;
    border-collapse: collapse;
    font-size: 11px;
}

th,
td {
    min-width: 95px;
    padding: 9px 10px;
    border-bottom: 1px solid var(--border);
    text-align: left;
    vertical-align: top;
    overflow-wrap: anywhere;
    max-width: 250px;
}

th {
    position: sticky;
    top: 0;
    z-index: 1;
    background: #f0fdfa;
    color: var(--dark);
    white-space: nowrap;
}

tbody tr:hover {
    background: #f8fafc;
}

.empty-state {
    padding: 24px 10px;
    text-align: center;
    color: var(--muted);
    line-height: 1.6;
    font-size: 13px;
}

.error-state {
    padding: 14px;
    border-radius: 7px;
    background: #fff7ed;
    color: #9a3412;
    line-height: 1.6;
    font-size: 12px;
}

/* FOOTER */
.footer {
    margin-top: 18px;
    padding: 12px 0 0;
    border-top: 1px solid var(--border);
    color: var(--muted);
    font-size: 11px;
    text-align: center;
}

/* RESPONSIVE */
@media (max-width: 1100px) {
    .layout {
        grid-template-columns: 195px minmax(0, 1fr);
    }

    main {
        padding: 17px;
    }

    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 760px) {
    .layout {
        grid-template-columns: 1fr;
    }

    .sidebar {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 3px;
        border-right: 0;
        border-bottom: 1px solid var(--border);
    }

    .menu-heading {
        grid-column: 1 / -1;
    }

    .sidebar a.reports-link {
        margin-top: 0;
        border-top: 0;
        padding-top: 9px;
    }

    main {
        padding: 15px 12px;
    }

    .cards {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 480px) {
    .topbar {
        padding: 12px;
    }

    .brand h1 {
        font-size: 16px;
    }

    .brand p {
        font-size: 10px;
    }

    .admin-badge {
        display: none;
    }

    .page-heading h2 {
        font-size: 19px;
    }

    .cards {
        gap: 8px;
    }

    .stat-card {
        padding: 11px;
    }

    .stat-number {
        font-size: 22px;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">
        <div class="brand-logo">LDD</div>
        <div>
            <h1>Admin Dashboard</h1>
            <p>Local Discover &amp; Deliver · Administration</p>
        </div>
    </div>

    <div class="top-actions">
        <span class="admin-badge">
            Administrator: <?= e($adminName) ?>
        </span>

        <a class="logout" href="../backend/logout.php">Logout</a>
    </div>
</header>

<div class="layout">

    <aside class="sidebar">
        <div class="menu-heading">Main Menu</div>

        <?php foreach ($views as $key => $label): ?>
            <a
                class="<?= $view === $key ? 'active' : '' ?>"
                href="admin_dashboard.php?view=<?= e($key) ?>"
            >
                <span class="menu-icon">
                    <?= e($menuIcons[$key] ?? "") ?>
                </span>
                <span><?= e($label) ?></span>
            </a>
        <?php endforeach; ?>

        <!-- REPORTS OPTION -->
        <a
            class="reports-link"
            href="reports.php"
        >
            <span class="menu-icon">RP</span>
            <span>Reports</span>
        </a>
    </aside>

    <main>

        <div class="page-heading">
            <div>
                <h2><?= e($views[$view]) ?></h2>
                <p>
                    Monitor accounts, orders, payments and services
                    from one administration panel.
                </p>
            </div>

            <span class="today-label">LDD ADMINISTRATION</span>
        </div>

        <?php if ($view === "overview"): ?>

            <div class="section-label">System Overview</div>

            <section class="cards">
                <?php foreach ($cards as $card): ?>
                    <a
                        class="stat-card"
                        href="admin_dashboard.php?view=<?= e($card[2]) ?>"
                    >
                        <div class="stat-top">
                            <span class="stat-title">
                                <?= e($card[0]) ?>
                            </span>

                            <span class="stat-icon">
                                <?= e($card[3]) ?>
                            </span>
                        </div>

                        <div class="stat-number">
                            <?= (int)$card[1] ?>
                        </div>

                        <div class="stat-link">
                            View records &rarr;
                        </div>
                    </a>
                <?php endforeach; ?>
            </section>

        <?php else: ?>

            <section class="table-panel">

                <div class="table-header">
                    <h3><?= e($views[$view]) ?></h3>

                    <span class="record-count">
                        Showing up to 200 records
                    </span>
                </div>

                <?php if ($loadError): ?>

                    <div class="error-state" style="margin-top:14px">
                        <strong>Unable to load this section.</strong><br>
                        Please check the table name and database
                        connection in phpMyAdmin. The detailed error
                        is saved in the PHP error log.
                    </div>

                <?php elseif (empty($rows)): ?>

                    <div class="empty-state">
                        <strong>No records found.</strong><br>
                        This table may be empty, or no accounts may
                        match this category yet.
                    </div>

                <?php else: ?>

                    <div class="record-count" style="margin-top:10px">
                        Records displayed: <?= count($rows) ?>
                    </div>

                    <input
                        type="search"
                        class="search-box"
                        id="tableSearch"
                        placeholder="Search visible records..."
                        aria-label="Search visible records"
                    >

                    <div class="table-wrap">
                        <table id="recordsTable">
                            <thead>
                                <tr>
                                    <?php foreach ($columns as $column): ?>
                                        <th>
                                            <?= e(
                                                ucwords(
                                                    str_replace("_", " ", $column)
                                                )
                                            ) ?>
                                        </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <?php foreach ($columns as $column): ?>
                                            <td>
                                                <?= e($row[$column] ?? "-") ?>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                <?php endif; ?>

            </section>

        <?php endif; ?>

        <footer class="footer">
            Local Discover &amp; Deliver · Admin Panel
        </footer>

    </main>

</div>

<script>
const searchInput = document.getElementById("tableSearch");

if (searchInput) {
    searchInput.addEventListener("input", function () {
        const query = this.value.toLowerCase();
        const rows = document.querySelectorAll("#recordsTable tbody tr");

        rows.forEach(function (row) {
            row.style.display = row.innerText.toLowerCase().includes(query)
                ? ""
                : "none";
        });
    });
}
</script>

</body>
</html>
