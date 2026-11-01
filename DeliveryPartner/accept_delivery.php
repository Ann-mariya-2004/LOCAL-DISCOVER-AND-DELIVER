<?php
session_start();
require_once "../backend/db.php";

if (!isset($conn) || $conn->connect_error) {
    error_log("LDD: Database connection failed.");
    die("Database connection failed.");
}

/* Verify login and Delivery Partner role */
if (
    !isset($_SESSION["user_id"]) ||
    strcasecmp(trim($_SESSION["role"] ?? ""), "Delivery Partner") !== 0
) {
    header("Location: ../login_registration.html");
    exit;
}

$user_id = (int)$_SESSION["user_id"];

/* Accept POST requests only */
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: available_orders.php");
    exit;
}

$order_id = filter_input(INPUT_POST, "order_id", FILTER_VALIDATE_INT);

if (!$order_id || $order_id < 1) {
    $_SESSION["delivery_message"] = "Invalid order. Please try again.";
    header("Location: available_orders.php");
    exit;
}

try {
    /* Verify Delivery Partner profile */
    $partner_stmt = $conn->prepare(
        "SELECT user_id FROM delivery_partners WHERE user_id = ? LIMIT 1"
    );

    if (!$partner_stmt) {
        throw new Exception("Unable to verify delivery partner.");
    }

    $partner_stmt->bind_param("i", $user_id);
    $partner_stmt->execute();
    $partner_result = $partner_stmt->get_result();
    $is_partner = $partner_result && $partner_result->num_rows === 1;
    $partner_stmt->close();

    if (!$is_partner) {
        $_SESSION["delivery_message"] =
            "Your Delivery Partner profile was not found.";
        header("Location: available_orders.php");
        exit;
    }

    $conn->begin_transaction();

    /*
     * Lock the order row so two delivery partners cannot
     * successfully accept the same order simultaneously.
     */
    $check_sql = "
        SELECT fo.id
        FROM food_orders fo
        WHERE fo.id = ?
          AND fo.delivery_partner_id IS NULL
          AND fo.delivery_status = 'Available'
          AND EXISTS (
              SELECT 1
              FROM payments p
              WHERE p.order_id = fo.id
                AND (
                    p.payment_status = 'Paid'
                    OR (
                        LOWER(TRIM(p.payment_method))
                            IN ('cod', 'cash on delivery')
                        AND p.payment_status = 'Pending'
                    )
                )
          )
        FOR UPDATE
    ";

    $check_stmt = $conn->prepare($check_sql);

    if (!$check_stmt) {
        throw new Exception("Unable to check order availability.");
    }

    $check_stmt->bind_param("i", $order_id);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    $order_available = $check_result && $check_result->num_rows === 1;
    $check_stmt->close();

    if (!$order_available) {
        $conn->rollback();

        $_SESSION["delivery_message"] =
            "This order is no longer available. Please refresh the orders page.";

        header("Location: available_orders.php");
        exit;
    }

    /* Assign order and update delivery status */
    $update_sql = "
        UPDATE food_orders
        SET delivery_partner_id = ?,
            delivery_status = 'Assigned'
        WHERE id = ?
          AND delivery_partner_id IS NULL
          AND delivery_status = 'Available'
    ";

    $update_stmt = $conn->prepare($update_sql);

    if (!$update_stmt) {
        throw new Exception("Unable to prepare order assignment.");
    }

    $update_stmt->bind_param("ii", $user_id, $order_id);

    if (!$update_stmt->execute()) {
        $update_stmt->close();
        throw new Exception("Unable to assign this order.");
    }

    $updated = ($update_stmt->affected_rows === 1);
    $update_stmt->close();

    if (!$updated) {
        $conn->rollback();

        $_SESSION["delivery_message"] =
            "The order could not be assigned. Please refresh and try again.";

        header("Location: available_orders.php");
        exit;
    }

    $conn->commit();

    /* Success: assigned orders should no longer appear as available */
    $_SESSION["delivery_message"] =
        "Order #" . $order_id . " accepted successfully! You can now manage it in My Deliveries.";

    header("Location: my_deliveries.php");
    exit;

} catch (Throwable $e) {
    if ($conn->errno === 0) {
        // No action required here; rollback is attempted below.
    }

    try {
        $conn->rollback();
    } catch (Throwable $rollback_error) {
        error_log("LDD rollback error: " . $rollback_error->getMessage());
    }

    error_log("LDD accept order error: " . $e->getMessage());

    $_SESSION["delivery_message"] =
        "Unable to accept this order right now. Please try again.";

    header("Location: available_orders.php");
    exit;
}
?>