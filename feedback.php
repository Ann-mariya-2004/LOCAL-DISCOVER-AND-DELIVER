<?php
session_start();
require_once "backend/db.php";

$message_status = "";
$message_type = "";

// Allow only logged-in users to submit feedback
if (!isset($_SESSION["user_id"])) {
    header("Location: login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

// Display success message after redirect
if (isset($_SESSION["feedback_success"])) {
    $message_status = $_SESSION["feedback_success"];
    $message_type = "success";

    unset($_SESSION["feedback_success"]);
}

// Process feedback form
if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $message = trim($_POST["message"] ?? "");
    $rating = filter_input(INPUT_POST, "rating", FILTER_VALIDATE_INT);

    if ($message === "" || strlen($message) > 2000) {

        $message_status = "Please enter feedback (maximum 2000 characters).";
        $message_type = "error";

    } elseif (
        $rating === false ||
        $rating === null ||
        $rating < 1 ||
        $rating > 5
    ) {

        $message_status = "Please select a rating between 1 and 5.";
        $message_type = "error";

    } else {

        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO feedback (user_id, message, rating, created_at)
             VALUES (?, ?, ?, NOW())"
        );

        if ($stmt) {

            mysqli_stmt_bind_param(
                $stmt,
                "isi",
                $user_id,
                $message,
                $rating
            );

            if (mysqli_stmt_execute($stmt)) {

                mysqli_stmt_close($stmt);

                // Store success message temporarily
                $_SESSION["feedback_success"] =
                    "Thank you! Your feedback has been submitted successfully.";

                // Redirect to customer dashboard
                header("Location: dashboard.php");
                exit();

            } else {

                error_log(
                    "Feedback submission failed: " .
                    mysqli_stmt_error($stmt)
                );

                $message_status =
                    "Unable to submit feedback. Please try again.";

                $message_type = "error";
                mysqli_stmt_close($stmt);
            }

        } else {

            error_log(
                "Feedback statement preparation failed: " .
                mysqli_error($conn)
            );

            $message_status =
                "Something went wrong. Please try again.";

            $message_type = "error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>LDD | Customer Feedback</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #f0fdfa;
            color: #1f2937;
        }

        header {
            background: #0f766e;
            color: white;
            padding: 18px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        header h2 {
            margin: 0;
        }

        header a {
            color: white;
            text-decoration: none;
            font-weight: bold;
        }

        .container {
            max-width: 650px;
            margin: 45px auto;
            padding: 20px;
        }

        .feedback-card {
            background: white;
            padding: 35px;
            border-radius: 16px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }

        .feedback-card h1 {
            color: #0f766e;
            margin-top: 0;
        }

        .subtitle {
            color: #64748b;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 22px 0 10px;
            font-weight: bold;
        }

        .stars {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            gap: 8px;
        }

        .stars input {
            position: absolute;
            opacity: 0;
            width: 1px;
            height: 1px;
        }

        .stars label {
            margin: 0;
            font-size: 38px;
            color: #cbd5e1;
            cursor: pointer;
        }

        .stars label:hover,
        .stars label:hover ~ label,
        .stars input:checked ~ label {
            color: #f59e0b;
        }

        .stars input:focus-visible + label {
            outline: 2px solid #0f766e;
            outline-offset: 3px;
        }

        textarea {
            width: 100%;
            min-height: 140px;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            font-family: inherit;
            font-size: 15px;
            resize: vertical;
        }

        textarea:focus {
            outline: 2px solid #14b8a6;
            border-color: #0f766e;
        }

        button {
            width: 100%;
            margin-top: 24px;
            padding: 14px;
            background: #0f766e;
            color: white;
            border: none;
            border-radius: 9px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #115e59;
        }

        .alert {
            padding: 13px;
            margin-bottom: 20px;
            border-radius: 8px;
            line-height: 1.5;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .back-link {
            display: inline-block;
            margin-top: 22px;
            color: #0f766e;
            text-decoration: none;
            font-weight: bold;
        }

        @media (max-width: 600px) {
            .container {
                margin: 20px auto;
            }

            .feedback-card {
                padding: 24px;
            }
        }
    </style>
</head>

<body>

<header>
    <h2>LDD</h2>
    <a href="index.html">Home</a>
</header>

<div class="container">

    <div class="feedback-card">

        <h1>Share Your Feedback ⭐</h1>

        <p class="subtitle">
            Your feedback helps us improve our Local Discover and Deliver
            services. Tell us about your experience!
        </p>

        <?php if ($message_status !== ""): ?>
            <div class="alert <?= htmlspecialchars($message_type) ?>"
                 role="status">
                <?= htmlspecialchars($message_status) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="feedback.php">

            <label>Rate Your Experience</label>

            <div class="stars">
                <input type="radio" id="star5" name="rating" value="5"
                       required>
                <label for="star5" title="5 stars">★</label>

                <input type="radio" id="star4" name="rating" value="4">
                <label for="star4" title="4 stars">★</label>

                <input type="radio" id="star3" name="rating" value="3">
                <label for="star3" title="3 stars">★</label>

                <input type="radio" id="star2" name="rating" value="2">
                <label for="star2" title="2 stars">★</label>

                <input type="radio" id="star1" name="rating" value="1">
                <label for="star1" title="1 star">★</label>
            </div>

            <label for="message">Your Feedback</label>

            <textarea
                id="message"
                name="message"
                placeholder="Tell us about your experience..."
                maxlength="2000"
                required
            ><?= isset($_POST["message"]) ? htmlspecialchars($_POST["message"]) : "" ?></textarea>

            <button type="submit">Submit Feedback</button>

        </form>

        <a class="back-link" href="dashboard.php">
            ← Back to Home
        </a>

    </div>

</div>

</body>
</html>