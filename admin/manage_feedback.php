<?php
session_start();

require_once "../backend/db.php";

// Allow only Admin users
if (
    !isset($_SESSION["user_id"]) ||
    strtolower($_SESSION["role"] ?? "") !== "admin"
) {
    http_response_code(403);
    exit("Access denied. Admin access only.");
}

// Fetch all customer feedback
$sql = "
    SELECT
        f.id,
        f.user_id,
        f.message,
        f.rating,
        f.created_at,
        u.name AS customer_name,
        u.email AS customer_email
    FROM feedback f
    LEFT JOIN users u ON f.user_id = u.id
    ORDER BY f.created_at DESC
";

$result = mysqli_query($conn, $sql);

if (!$result) {
    exit("Unable to load feedback. Please check the database structure.");
}

$total_feedback = mysqli_num_rows($result);

$total_rating = 0;
$feedback_rows = [];

while ($row = mysqli_fetch_assoc($result)) {
    $feedback_rows[] = $row;
    $total_rating += (int) $row["rating"];
}

$average_rating = $total_feedback > 0
    ? round($total_rating / $total_feedback, 1)
    : 0;

function safe($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>LDD | Manage Feedback</title>

<style>
* {
    box-sizing: border-box;
    font-family: Arial, sans-serif;
}

body {
    margin: 0;
    background: #f0fdfa;
    color: #1f2937;
}

.header {
    background: #115e59;
    color: white;
    padding: 22px 5%;
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.logo {
    font-size: 27px;
    font-weight: bold;
}

.logo span {
    color: #99f6e4;
}

.header a {
    color: white;
    text-decoration: none;
    background: #0f766e;
    padding: 10px 16px;
    border-radius: 8px;
}

.container {
    max-width: 1200px;
    margin: 35px auto;
    padding: 0 20px;
}

.page-title {
    color: #134e4a;
    margin-bottom: 8px;
}

.subtitle {
    color: #64748b;
    margin-bottom: 28px;
    line-height: 1.6;
}

.summary {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 20px;
    margin-bottom: 28px;
}

.summary-card {
    background: white;
    padding: 24px;
    border-radius: 14px;
    border: 1px solid #ccfbf1;
    box-shadow: 0 5px 18px rgba(0,0,0,0.05);
}

.summary-card h3 {
    color: #64748b;
    font-size: 14px;
    margin: 0 0 12px;
}

.summary-card p {
    color: #0f766e;
    font-size: 30px;
    font-weight: bold;
    margin: 0;
}

.feedback-panel {
    background: white;
    border-radius: 15px;
    padding: 24px;
    box-shadow: 0 5px 20px rgba(0,0,0,0.06);
}

.feedback-panel h2 {
    color: #134e4a;
    margin-top: 0;
}

.table-wrapper {
    overflow-x: auto;
}

table {
    width: 100%;
    border-collapse: collapse;
    min-width: 750px;
}

th, td {
    padding: 15px 12px;
    border-bottom: 1px solid #e2e8f0;
    text-align: left;
    vertical-align: top;
}

th {
    background: #f0fdfa;
    color: #115e59;
    font-size: 13px;
}

td {
    font-size: 14px;
    line-height: 1.6;
}

.rating {
    color: #d97706;
    font-weight: bold;
    white-space: nowrap;
}

.feedback-message {
    max-width: 350px;
    white-space: pre-wrap;
    overflow-wrap: anywhere;
}

.empty {
    text-align: center;
    color: #64748b;
    padding: 35px;
}

@media (max-width: 600px) {
    .summary {
        grid-template-columns: 1fr;
    }

    .container {
        margin-top: 22px;
    }

    .feedback-panel {
        padding: 15px;
    }
}
</style>
</head>

<body>

<header class="header">
    <div class="logo">L<span>DD</span> Admin</div>
    <a href="javascript:history.back()">← Back to Dashboard</a>
</header>

<main class="container">

    <h1 class="page-title">Customer Feedback</h1>

    <p class="subtitle">
        Review customer ratings and feedback to understand their
        experience and improve LDD services.
    </p>

    <section class="summary">

        <div class="summary-card">
            <h3>Total Feedback</h3>
            <p><?= $total_feedback ?></p>
        </div>

        <div class="summary-card">
            <h3>Average Rating</h3>
            <p>
                <?= $average_rating ?> / 5
                <span style="font-size:20px;">★</span>
            </p>
        </div>

    </section>

    <section class="feedback-panel">

        <h2>All Customer Reviews</h2>

        <div class="table-wrapper">

            <table>

                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Email</th>
                        <th>Rating</th>
                        <th>Feedback</th>
                        <th>Date</th>
                    </tr>
                </thead>

                <tbody>

                <?php if ($total_feedback > 0): ?>

                    <?php foreach ($feedback_rows as $row): ?>

                        <tr>
                            <td><?= (int)$row["id"] ?></td>

                            <td>
                                <?= safe($row["customer_name"] ?: "Customer") ?>
                            </td>

                            <td>
                                <?= safe($row["customer_email"] ?: "N/A") ?>
                            </td>

                            <td class="rating">
                                <?= (int)$row["rating"] ?> / 5 ★
                            </td>

                            <td class="feedback-message">
                                <?= safe($row["message"]) ?>
                            </td>

                            <td>
                                <?= safe($row["created_at"]) ?>
                            </td>
                        </tr>

                    <?php endforeach; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty">
                            No customer feedback has been submitted yet.
                        </td>
                    </tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </section>

</main>

</body>
</html>