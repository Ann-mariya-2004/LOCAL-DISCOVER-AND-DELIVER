<?php
session_start();
require_once __DIR__ . '/backend/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available. Please check backend/db.php.');
}

$conn->set_charset('utf8mb4');

if (!isset($_SESSION['grocery_cart']) || !is_array($_SESSION['grocery_cart'])) {
    $_SESSION['grocery_cart'] = [];
}

if (!isset($_SESSION['grocery_store_id'])) {
    $_SESSION['grocery_store_id'] = 0;
}

$message = '';
$message_type = 'success';

function h($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Convert pack quantity into the actual displayed weight/volume.
 * Examples: 2 x '5 kg' => '2 packs × 5 kg = 10 kg';
 *           3 x '500 g' => '3 packs × 500 g = 1500 g'.
 * For units without a numeric prefix (for example 'pcs'), show item count.
 */
function grocery_quantity_label(int $quantity, string $unit): string
{
    $unit = trim($unit);
    if ($quantity <= 0 || $unit === '') {
        return $quantity . ' × ' . ($unit !== '' ? $unit : 'item');
    }

    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(kg|kgs|g|gm|gms|gram|grams|l|lt|ltr|litre|litres|ml)\s*$/i', $unit, $m)) {
        $packAmount = (float) $m[1];
        $unitName = strtolower($m[2]);
        $total = $packAmount * $quantity;
        $packText = rtrim(rtrim(number_format($packAmount, 3, '.', ''), '0'), '.');
        $totalText = rtrim(rtrim(number_format($total, 3, '.', ''), '0'), '.');
        return $quantity . ' pack' . ($quantity === 1 ? '' : 's') . ' × ' . $packText . ' ' . $unitName . ' = ' . $totalText . ' ' . $unitName;
    }

    if (preg_match('/^([0-9]+(?:\.[0-9]+)?)\s*(.+)$/u', $unit, $m)) {
        $packAmount = (float) $m[1];
        $unitName = trim($m[2]);
        $total = $packAmount * $quantity;
        $packText = rtrim(rtrim(number_format($packAmount, 3, '.', ''), '0'), '.');
        $totalText = rtrim(rtrim(number_format($total, 3, '.', ''), '0'), '.');
        return $quantity . ' pack' . ($quantity === 1 ? '' : 's') . ' × ' . $packText . ' ' . $unitName . ' = ' . $totalText . ' ' . $unitName;
    }

    return $quantity . ' × ' . $unit;
}

/* ---------------------------------------------------------
   POST ACTIONS
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    /* Add item to cart */
    if ($action === 'add_to_cart') {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        $store_id = (int) ($_POST['store_id'] ?? 0);
        $quantity = max(1, (int) ($_POST['quantity'] ?? 1));

        $stmt = $conn->prepare(
            'SELECT id, store_id, item_name, price, stock_qty
             FROM grocery_items
             WHERE id = ? AND store_id = ? AND is_available = 1
             LIMIT 1'
        );
        $stmt->bind_param('ii', $item_id, $store_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $item = $result->fetch_assoc();
        $stmt->close();

        if (!$item) {
            $message = 'The selected grocery item is not available.';
            $message_type = 'error';
        } elseif ((int) $item['stock_qty'] <= 0) {
            $message = 'This item is currently out of stock.';
            $message_type = 'error';
        } elseif ($_SESSION['grocery_store_id'] !== 0 &&
                  (int) $_SESSION['grocery_store_id'] !== $store_id) {
            $message = 'Your cart already contains items from another store. Clear the cart before choosing a different store.';
            $message_type = 'error';
        } else {
            $_SESSION['grocery_store_id'] = $store_id;

            $old_quantity = isset($_SESSION['grocery_cart'][$item_id])
                ? (int) $_SESSION['grocery_cart'][$item_id]
                : 0;

            $new_quantity = min(
                (int) $item['stock_qty'],
                $old_quantity + $quantity
            );

            $_SESSION['grocery_cart'][$item_id] = $new_quantity;
            $message = $item['item_name'] . ' added to your cart.';
        }
    }

    /* Update cart */
    elseif ($action === 'update_cart') {
        $quantities = $_POST['qty'] ?? [];

        if (is_array($quantities)) {
            foreach ($quantities as $item_id => $quantity) {
                $item_id = (int) $item_id;
                $quantity = (int) $quantity;

                if (!isset($_SESSION['grocery_cart'][$item_id])) {
                    continue;
                }

                if ($quantity <= 0) {
                    unset($_SESSION['grocery_cart'][$item_id]);
                    continue;
                }

                $stmt = $conn->prepare(
                    'SELECT stock_qty
                     FROM grocery_items
                     WHERE id = ? AND is_available = 1
                     LIMIT 1'
                );
                $stmt->bind_param('i', $item_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $stock = $result->fetch_assoc();
                $stmt->close();

                $stock_qty = $stock ? (int) $stock['stock_qty'] : 0;

                if ($stock_qty <= 0) {
                    unset($_SESSION['grocery_cart'][$item_id]);
                } else {
                    $_SESSION['grocery_cart'][$item_id] = min($quantity, $stock_qty);
                }
            }
        }

        if (empty($_SESSION['grocery_cart'])) {
            $_SESSION['grocery_store_id'] = 0;
        }

        $message = 'Cart updated successfully.';
    }

    /* Remove item */
    elseif ($action === 'remove_item') {
        $item_id = (int) ($_POST['item_id'] ?? 0);
        unset($_SESSION['grocery_cart'][$item_id]);

        if (empty($_SESSION['grocery_cart'])) {
            $_SESSION['grocery_store_id'] = 0;
        }

        $message = 'Item removed from your cart.';
    }

    /* Clear cart */
    elseif ($action === 'clear_cart') {
        $_SESSION['grocery_cart'] = [];
        $_SESSION['grocery_store_id'] = 0;
        $message = 'Your grocery cart has been cleared.';
    }

    /* Place grocery order */
    elseif ($action === 'place_order') {
        $customer_id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
        $customer_name = trim($_POST['customer_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $delivery_address = trim($_POST['delivery_address'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? 'Cash on Delivery');
        $store_id = (int) ($_SESSION['grocery_store_id'] ?? 0);

        if ($customer_id <= 0) {
            $message = 'Please log in before placing a grocery order.';
            $message_type = 'error';
        } elseif ($customer_name === '' || $phone === '' || $delivery_address === '') {
            $message = 'Please fill in your name, phone number and delivery address.';
            $message_type = 'error';
        } elseif (!in_array($payment_method, ['Cash on Delivery', 'UPI'], true)) {
            $payment_method = 'Cash on Delivery';
        } elseif ($store_id <= 0 || empty($_SESSION['grocery_cart'])) {
            $message = 'Your grocery cart is empty.';
            $message_type = 'error';
        } else {
            $cart = $_SESSION['grocery_cart'];
            $items_for_order = [];
            $subtotal = 0.00;
            $order_created = false;

            $conn->begin_transaction();

            try {
                foreach ($cart as $item_id => $quantity) {
                    $item_id = (int) $item_id;
                    $quantity = (int) $quantity;

                    if ($quantity <= 0) {
                        continue;
                    }

                    $stmt = $conn->prepare(
                        'SELECT id, store_id, item_name, unit, price, stock_qty
                         FROM grocery_items
                         WHERE id = ? AND store_id = ? AND is_available = 1
                         LIMIT 1
                         FOR UPDATE'
                    );
                    $stmt->bind_param('ii', $item_id, $store_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $item = $result->fetch_assoc();
                    $stmt->close();

                    if (!$item) {
                        throw new Exception('One or more selected items are no longer available.');
                    }

                    $stock_qty = (int) $item['stock_qty'];

                    if ($stock_qty < $quantity) {
                        throw new Exception($item['item_name'] . ' does not have enough stock.');
                    }

                    $price = (float) $item['price'];
                    $line_total = $price * $quantity;
                    $subtotal += $line_total;

                    $items_for_order[] = [
                        'id' => $item_id,
                        'name' => $item['item_name'],
                        'unit' => (string) ($item['unit'] ?? 'item'),
                        'quantity' => $quantity,
                        'price' => $price,
                        'subtotal' => $line_total
                    ];
                }

                if (empty($items_for_order)) {
                    throw new Exception('Your grocery cart is empty.');
                }

                /* Delivery fee: free for orders >= ₹499, otherwise ₹40 */
                $delivery_fee = ($subtotal >= 499) ? 0.00 : 40.00;
                $grand_total = $subtotal + $delivery_fee;

                $order_stmt = $conn->prepare(
                    'INSERT INTO grocery_orders
                    (customer_id, store_id, customer_name, phone, delivery_address,
                     payment_method, total_amount, delivery_fee, grand_total, order_status)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)' 
                );

                $order_status = 'Pending';

                $order_stmt->bind_param(
                    'iissssddds',
                    $customer_id,
                    $store_id,
                    $customer_name,
                    $phone,
                    $delivery_address,
                    $payment_method,
                    $subtotal,
                    $delivery_fee,
                    $grand_total,
                    $order_status
                );

                if (!$order_stmt->execute()) {
                    throw new Exception('Unable to create the grocery order.');
                }

                $order_id = (int) $conn->insert_id;
                $order_stmt->close();

                $order_item_stmt = $conn->prepare(
                    'INSERT INTO grocery_order_items
                    (order_id, item_id, item_name, quantity, price, subtotal)
                    VALUES (?, ?, ?, ?, ?, ?)'
                );

                $stock_stmt = $conn->prepare(
                    'UPDATE grocery_items
                     SET stock_qty = stock_qty - ?
                     WHERE id = ? AND stock_qty >= ?'
                );

                foreach ($items_for_order as $order_item) {
                    $item_id = (int) $order_item['id'];
                    $item_name = $order_item['name'];
                    $quantity = (int) $order_item['quantity'];
                    $price = (float) $order_item['price'];
                    $line_total = (float) $order_item['subtotal'];

                    $order_item_stmt->bind_param(
                        'iisidd',
                        $order_id,
                        $item_id,
                        $item_name,
                        $quantity,
                        $price,
                        $line_total
                    );

                    if (!$order_item_stmt->execute()) {
                        throw new Exception('Unable to save grocery order items.');
                    }

                    $stock_stmt->bind_param('iii', $quantity, $item_id, $quantity);

                    if (!$stock_stmt->execute() || $stock_stmt->affected_rows !== 1) {
                        throw new Exception('Unable to update grocery stock.');
                    }
                }

                $order_item_stmt->close();
                $stock_stmt->close();

                $conn->commit();
                $order_created = true;

                $_SESSION['grocery_cart'] = [];
                $_SESSION['grocery_store_id'] = 0;

                $message = 'Grocery order #LDD' . $order_id . ' placed successfully.';
            } catch (Throwable $e) {
                $conn->rollback();
                $message = 'Order could not be placed: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

/* ---------------------------------------------------------
   LOAD GROCERY STORES
--------------------------------------------------------- */
$stores = [];
$store_result = $conn->query(
    'SELECT id, store_name, phone, location, category, description,
            delivery_time, rating, image
     FROM grocery_stores
     WHERE is_active = 1
     ORDER BY rating DESC, store_name ASC'
);

if ($store_result) {
    while ($row = $store_result->fetch_assoc()) {
        $stores[] = $row;
    }
}

$selected_store_id = isset($_GET['store_id'])
    ? (int) $_GET['store_id']
    : (int) ($_SESSION['grocery_store_id'] ?? 0);

if ($selected_store_id <= 0 && !empty($stores)) {
    $selected_store_id = (int) $stores[0]['id'];
}

/* ---------------------------------------------------------
   LOAD CATEGORIES
--------------------------------------------------------- */
$categories = [];
$category_result = $conn->query(
    'SELECT DISTINCT category
     FROM grocery_items
     WHERE is_available = 1
     ORDER BY category ASC'
);

if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row['category'];
    }
}

/* ---------------------------------------------------------
   LOAD SELECTED STORE ITEMS
--------------------------------------------------------- */
$search = trim($_GET['search'] ?? '');
$category = trim($_GET['category'] ?? '');
$items = [];

if ($selected_store_id > 0) {
    $sql =
        'SELECT id, store_id, item_name, category, unit, price,
                stock_qty, image, description
         FROM grocery_items
         WHERE store_id = ? AND is_available = 1';

    if ($search !== '') {
        $sql .= ' AND (item_name LIKE ? OR category LIKE ? OR description LIKE ?)';
    }

    if ($category !== '') {
        $sql .= ' AND category = ?';
    }

    $sql .= ' ORDER BY category ASC, item_name ASC';

    $stmt = $conn->prepare($sql);

    if ($search !== '' && $category !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('issss', $selected_store_id, $like, $like, $like, $category);
    } elseif ($search !== '') {
        $like = '%' . $search . '%';
        $stmt->bind_param('isss', $selected_store_id, $like, $like, $like);
    } elseif ($category !== '') {
        $stmt->bind_param('is', $selected_store_id, $category);
    } else {
        $stmt->bind_param('i', $selected_store_id);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $stmt->close();
}

/* ---------------------------------------------------------
   LOAD CART ITEMS
--------------------------------------------------------- */
$cart_items = [];
$cart_subtotal = 0.00;
$cart_count = 0;

if (!empty($_SESSION['grocery_cart'])) {
    foreach ($_SESSION['grocery_cart'] as $item_id => $quantity) {
        $item_id = (int) $item_id;
        $quantity = (int) $quantity;

        if ($quantity <= 0) {
            continue;
        }

        $stmt = $conn->prepare(
            'SELECT id, item_name, category, unit, price, stock_qty, image
             FROM grocery_items
             WHERE id = ? AND is_available = 1
             LIMIT 1'
        );
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();

        if (!$row) {
            unset($_SESSION['grocery_cart'][$item_id]);
            continue;
        }

        if ($quantity > (int) $row['stock_qty']) {
            $quantity = (int) $row['stock_qty'];
            $_SESSION['grocery_cart'][$item_id] = $quantity;
        }

        if ($quantity <= 0) {
            unset($_SESSION['grocery_cart'][$item_id]);
            continue;
        }

        $row['cart_qty'] = $quantity;
        $row['line_total'] = (float) $row['price'] * $quantity;

        $cart_items[] = $row;
        $cart_subtotal += (float) $row['line_total'];
        $cart_count += $quantity;
    }
}

if (empty($_SESSION['grocery_cart'])) {
    $_SESSION['grocery_store_id'] = 0;
}

$cart_delivery_fee = ($cart_subtotal > 0 && $cart_subtotal < 499) ? 40.00 : 0.00;
$cart_grand_total = $cart_subtotal + $cart_delivery_fee;

$selected_store = null;
foreach ($stores as $store) {
    if ((int) $store['id'] === $selected_store_id) {
        $selected_store = $store;
        break;
    }
}

$category_icons = [
    'Dairy' => '🥛',
    'Dairy & Eggs' => '🥚',
    'Staples' => '🍚',
    'Vegetables' => '🥕',
    'Fruits' => '🍎',
    'Bakery' => '🍞',
    'Cooking Essentials' => '🫗',
    'Beverages' => '🍵',
    'Snacks' => '🍪',
    'Household' => '🧺'
];

/* Use item-specific icons where a category icon would be misleading. */
$item_icons = [
    'banana' => '🍌', 'apple' => '🍎', 'orange' => '🍊',
    'mango' => '🥭', 'grape' => '🍇', 'watermelon' => '🍉',
    'pineapple' => '🍍', 'strawberry' => '🍓', 'lemon' => '🍋',
    'bread' => '🍞', 'milk' => '🥛', 'egg' => '🥚',
    'rice' => '🍚', 'tomato' => '🍅', 'potato' => '🥔',
    'carrot' => '🥕', 'onion' => '🧅', 'cucumber' => '🥒'
];

/* ---------------------------------------------------------
   CURRENT CUSTOMER DETAILS FOR HEADER
--------------------------------------------------------- */

$current_user_name = 'My Account';
$current_user_image = 'assets/default-profile.png';

if (isset($_SESSION['user_id'])) {
    $current_user_id = (int) $_SESSION['user_id'];

    $user_columns = [];
    $column_result = $conn->query("SHOW COLUMNS FROM users");

    if ($column_result) {
        while ($column = $column_result->fetch_assoc()) {
            $user_columns[] = $column['Field'];
        }
    }

    /* Find the name column used by the existing users table */
    $name_column = null;

    foreach (['name', 'full_name', 'username'] as $candidate) {
        if (in_array($candidate, $user_columns, true)) {
            $name_column = $candidate;
            break;
        }
    }

    /* Find the profile image column used by the existing users table */
    $image_column = null;

    foreach (['profile_image', 'profile_photo', 'profile_pic', 'image'] as $candidate) {
        if (in_array($candidate, $user_columns, true)) {
            $image_column = $candidate;
            break;
        }
    }

    if ($name_column !== null && $image_column !== null) {
        $safe_name_column = '`' . str_replace('`', '', $name_column) . '`';
        $safe_image_column = '`' . str_replace('`', '', $image_column) . '`';

        $user_stmt = $conn->prepare(
            "SELECT $safe_name_column AS user_name,
                    $safe_image_column AS user_image
             FROM users
             WHERE id = ?
             LIMIT 1"
        );

        if ($user_stmt) {
            $user_stmt->bind_param('i', $current_user_id);
            $user_stmt->execute();

            $user_result = $user_stmt->get_result();
            $user_row = $user_result->fetch_assoc();

            if ($user_row) {
                if (!empty($user_row['user_name'])) {
                    $current_user_name = $user_row['user_name'];
                }

                if (!empty($user_row['user_image'])) {
                    $current_user_image = $user_row['user_image'];
                }
            }

            $user_stmt->close();
        }
    }
}

/* Make profile-image path work with both stored relative paths and filenames */
if ($current_user_image === '') {
    $current_user_image = 'assets/default-profile.png';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Local Discover and Deliver grocery delivery page.">
    <title>LDD | Grocery Delivery</title>

    <style>
        :root{--primary:#0f766e;--primary-dark:#115e59;--accent:#14b8a6;--background:#f3f7f7;--white:#fff;--text:#17312f;--muted:#647572;--border:#dce7e5;--soft:#eaf7f5;--success-bg:#ecfdf5;--success-text:#166534;--error-bg:#fef2f2;--error-text:#b91c1c;--shadow:0 5px 18px rgba(15,65,60,.06)}
        *{box-sizing:border-box;margin:0;padding:0}html{scroll-behavior:smooth;scroll-padding-top:90px}body{font-family:Inter,Arial,Helvetica,sans-serif;background:var(--background);color:var(--text);line-height:1.45}a{text-decoration:none;color:inherit}button,input,select,textarea{font:inherit}button{cursor:pointer}.container{width:min(1440px,96%);margin:auto}
        .site-header{position:sticky;top:0;z-index:50;background:rgba(255,255,255,.97);border-bottom:1px solid var(--border);backdrop-filter:blur(12px)}.header-inner{min-height:64px;display:flex;align-items:center;justify-content:space-between;gap:14px}.logo{color:var(--primary);font-size:25px;font-weight:900;letter-spacing:-1px;flex-shrink:0}.logo span{color:var(--accent)}.page-nav{display:flex;align-items:center;justify-content:flex-end;gap:12px;min-width:0}.nav{display:flex;align-items:center;gap:16px;flex-wrap:wrap}.nav a{color:#3c5350;font-size:13px;font-weight:700}.nav a:hover{color:var(--primary)}.cart-top{padding:8px 12px;background:var(--primary);color:#fff!important;border-radius:9px}.back-btn,.outline-btn,.primary-btn,.danger-btn,.logout-btn{display:inline-flex;align-items:center;justify-content:center;gap:6px;min-height:36px;padding:7px 11px;border-radius:8px;font-size:13px;font-weight:750;border:1px solid transparent;white-space:nowrap}.back-btn{border-color:var(--border);background:#fff;color:var(--primary-dark)}.account-area{display:flex;align-items:center;gap:7px}.profile-link{display:flex;align-items:center;gap:7px;padding:3px 8px 3px 3px;border:1px solid var(--border);border-radius:25px;background:#fff}.profile-avatar{width:31px;height:31px;border-radius:50%;object-fit:cover;display:block;border:2px solid #d7efea;background:var(--soft)}.profile-name{max-width:105px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;color:#35514d;font-size:12px;font-weight:700}.logout-btn{border-color:#fecaca;background:#fff;color:var(--error-text)}
        .hero{padding:14px 0 13px;text-align:left;background:radial-gradient(circle at 85% 15%,rgba(20,184,166,.14),transparent 28%),linear-gradient(110deg,#e8f8f5,#f7fbfa 65%,#eaf5f3)}.hero-inner{max-width:none;display:grid;grid-template-columns:1fr auto;align-items:center;gap:8px 24px}.eyebrow{display:inline-block;grid-column:1/-1;width:max-content;padding:4px 9px;margin-bottom:0;border:1px solid #b8e5de;border-radius:999px;background:#fff;color:var(--primary-dark);font-size:10px;font-weight:800;letter-spacing:.6px}.hero h1{color:#123c38;font-size:clamp(23px,2.7vw,34px);line-height:1.13;margin:0}.hero h1 span{color:var(--primary)}.hero p{max-width:520px;color:var(--muted);font-size:13px;grid-column:2;grid-row:2}
        .message{width:min(1440px,96%);margin:10px auto 0;padding:10px 13px;border-radius:9px;font-size:13px;font-weight:700}.message.success{background:var(--success-bg);border:1px solid #bbf7d0;color:var(--success-text)}.message.error{background:var(--error-bg);border:1px solid #fecaca;color:var(--error-text)}.section{padding:10px 0}.section-heading{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:10px}.section-heading h2{color:#123c38;font-size:18px}.section-heading p{color:var(--muted);font-size:12px}
        .store-picker-layout{display:grid;grid-template-columns:220px minmax(0,1fr);gap:14px;align-items:center}
        .store-picker-heading{display:block;margin:0;padding:10px 12px;border-left:3px solid var(--accent);background:linear-gradient(110deg,#eaf7f5,rgba(255,255,255,.55));border-radius:0 10px 10px 0}
        .store-picker-heading h2{color:#123c38;font-size:17px;line-height:1.25;margin:0 0 5px}
        .store-picker-heading p{color:var(--muted);font-size:11px;line-height:1.45;margin:0}
        .stores-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:9px;min-width:0}
        .store-card{display:grid;grid-template-columns:34px minmax(0,1fr);column-gap:9px;align-items:center;padding:9px 10px;background:var(--white);border:1px solid var(--border);border-radius:11px;box-shadow:var(--shadow);min-width:0}
        .store-card.active{border-color:var(--accent);background:#f4fffc;box-shadow:0 0 0 1px rgba(20,184,166,.12)}
        .store-icon{width:34px;height:34px;display:grid;place-items:center;grid-row:1/4;border-radius:9px;background:var(--soft);font-size:18px}
        .store-card h3{color:#173d39;font-size:13px;line-height:1.25}
        .store-meta,.store-description{color:var(--muted);font-size:10px}
        .store-description{display:none}
        .store-bottom{display:flex;align-items:center;justify-content:space-between;gap:7px;grid-column:2;margin-top:4px}
        .rating{color:#8a5a00;font-size:11px;font-weight:800}
        .store-bottom .outline-btn{min-height:27px;padding:4px 8px;font-size:10px}
        .catalog-layout{display:grid;grid-template-columns:minmax(0,1fr) 290px;gap:12px;align-items:start}.catalog-main,.cart-box{background:var(--white);border:1px solid var(--border);border-radius:13px;box-shadow:var(--shadow);min-width:0}.catalog-main{padding:12px}.selected-store{display:flex;align-items:center;justify-content:space-between;gap:12px;padding-bottom:8px;margin-bottom:9px;border-bottom:1px solid var(--border)}.selected-store h3{color:#173d39;font-size:18px}.selected-store p{color:var(--muted);font-size:12px}.filters{display:grid;grid-template-columns:minmax(0,1fr) 160px auto;gap:7px;margin-bottom:9px}.field{width:100%;min-width:0;padding:9px 10px;border:1px solid var(--border);border-radius:8px;background:#fff;outline:none;font-size:13px}.field:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(20,184,166,.1)}.items-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px}.item-card{display:grid;grid-template-columns:38px minmax(0,1fr);column-gap:8px;align-items:start;padding:8px;border:1px solid var(--border);border-radius:11px;background:#fff;min-width:0;transition:transform .15s,border-color .15s}.item-card:hover{border-color:#a9dcd4;transform:translateY(-1px)}.item-image{width:38px;height:38px;display:grid;place-items:center;grid-column:1;grid-row:1/5;margin:0;border-radius:9px;background:linear-gradient(135deg,#edf9f6,#f7fbfa);font-size:24px}.item-category{color:var(--primary);font-size:9px;font-weight:800;letter-spacing:.3px;text-transform:uppercase;line-height:1.2}.item-title-row{display:flex;align-items:baseline;flex-wrap:wrap;gap:3px 7px;min-width:0}.item-card h4{color:#173d39;font-size:12px;margin:0;line-height:1.25;min-height:0;max-height:30px;overflow:hidden;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow-wrap:anywhere}.item-description{min-height:0;max-height:28px;overflow:hidden;margin:3px 0 4px;color:var(--muted);font-size:10px;line-height:13px;display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow-wrap:anywhere}.price-row{display:flex;justify-content:flex-start;align-items:baseline;flex-wrap:wrap;gap:3px 9px}.price{color:#123c38;font-size:14px;font-weight:850}.unit{color:var(--muted);font-size:10px}.stock{margin-top:0;color:#52706b;font-size:10px;white-space:nowrap}.add-form{display:flex;align-items:center;gap:5px;margin-top:6px;grid-column:2}.qty{width:52px;min-width:0;padding:7px 4px;text-align:center;border:1px solid var(--border);border-radius:7px;font-size:12px}.primary-btn{background:var(--primary);color:#fff}.primary-btn:hover{background:var(--primary-dark)}.outline-btn{background:#fff;color:var(--primary-dark);border-color:#c8dfdb}.outline-btn:hover{background:var(--soft)}.danger-btn{background:#fff;color:var(--error-text);border-color:#fecaca}.danger-btn:hover{background:#fef2f2}.add-form .primary-btn{flex:1;min-width:0;padding:7px 8px;font-size:11px}
        .cart-box{position:sticky;top:76px;padding:11px;max-height:calc(100vh - 90px);overflow:auto}.cart-box h3{display:flex;align-items:center;gap:5px;color:#173d39;font-size:18px}.cart-count{display:inline-grid;place-items:center;min-width:23px;height:23px;padding:0 6px;border-radius:999px;background:var(--soft);color:var(--primary-dark);font-size:11px}.empty-cart,.no-results{padding:22px 10px;color:var(--muted);text-align:center;font-size:13px}.cart-list{margin:10px 0;border-top:1px solid var(--border)}.cart-item{padding:10px 0;border-bottom:1px solid var(--border)}.cart-item-top{display:flex;justify-content:space-between;gap:8px}.cart-item-name{color:#173d39;font-size:13px;font-weight:750}.cart-item-meta{color:var(--muted);font-size:11px}.cart-item-total{font-size:12px;font-weight:850;white-space:nowrap}.cart-update{display:flex;align-items:center;gap:6px;margin-top:7px}.cart-update .qty{width:55px}.cart-update .outline-btn,.cart-remove .danger-btn{min-height:29px;padding:4px 8px;font-size:11px}.cart-remove{display:inline-flex;margin-left:auto}.cart-summary{padding-top:8px;border-top:1px solid var(--border)}.summary-row{display:flex;justify-content:space-between;gap:10px;margin:6px 0;color:#546865;font-size:12px}.summary-row.total{padding-top:9px;margin-top:9px;border-top:1px solid var(--border);color:#123c38;font-size:16px;font-weight:850}.checkout-box{margin-top:12px;padding-top:12px;border-top:1px solid var(--border)}.checkout-box h4{color:#173d39;font-size:15px;margin-bottom:9px}.checkout-box label{display:block;margin-bottom:4px;color:#46605c;font-size:11px;font-weight:750}.checkout-box .field{margin-bottom:8px}.checkout-box textarea.field{resize:vertical;min-height:60px}.checkout-note{padding:8px 9px;margin:3px 0 9px;border-radius:7px;background:#f0fdfa;color:#22645c;font-size:11px}.checkout-box form .primary-btn{width:100%}.footer{margin-top:12px;padding:15px 0;background:#123f3b;color:#e8f5f3;text-align:center;font-size:12px}.footer p+p{margin-top:3px;color:#c5ddda;font-size:10px}
        /* Professional grocery landing layout: original hero + stores on left, cart on right */
        .hero{padding:20px 0 17px}
        .hero-inner{display:grid;grid-template-columns:minmax(0,1fr) minmax(260px, .85fr);align-items:center;gap:8px 28px}
        .hero .eyebrow{grid-column:1/-1}
        .hero h1{grid-column:1;grid-row:2;font-size:clamp(25px,2.6vw,35px)}
        .hero p{grid-column:2;grid-row:2;max-width:460px;font-size:13px}
        #stores{padding:8px 0 14px!important}
        .store-section-heading{margin-bottom:9px}
        .store-section-heading h2{font-size:19px}
        .store-section-heading p{font-size:12px}
        .store-picker-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:14px;align-items:start}
        .stores-grid{grid-column:1;grid-template-columns:repeat(2,minmax(0,1fr));align-content:start;gap:9px}
        .store-card{box-shadow:0 3px 12px rgba(15,65,60,.045);padding:10px}
        .store-card h3{font-size:13px}
        .store-meta{font-size:10px}
        .store-bottom .outline-btn{font-size:10px;min-height:28px}
        .store-picker-layout .cart-box{grid-column:2;grid-row:1;position:sticky;top:76px;max-height:calc(100vh - 90px);overflow:auto;padding:10px}
        .store-picker-layout .empty-cart{padding:8px 5px;font-size:12px;line-height:1.4}
        .quantity-total{margin:3px 0 7px;color:#475569;font-size:12px}.quantity-total strong{color:#0f766e;font-weight:700}
        .store-picker-layout .cart-box h3{font-size:16px}
        .store-picker-layout .cart-count{min-width:21px;height:21px}
        @media(max-width:1100px){.store-picker-layout{grid-template-columns:minmax(0,1fr) 280px}.stores-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        .catalog-layout{display:block}
        .catalog-main{padding:14px}
        #catalog{padding-top:8px}
        /* Compact store selector directly below the header */
        #stores{padding:5px 0 7px!important}
        .store-picker-layout{display:grid;grid-template-columns:minmax(0,1fr) 320px;gap:10px;align-items:start}
        .stores-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:7px}
        .store-card{grid-template-columns:28px minmax(0,1fr);column-gap:7px;padding:7px 9px;border-radius:9px;box-shadow:none}
        .store-icon{width:28px;height:28px;font-size:16px;border-radius:7px}
        .store-card h3{font-size:12px;line-height:1.2}
        .store-meta{font-size:9px;line-height:1.3}
        .store-bottom{margin-top:2px;gap:5px}
        .rating{font-size:10px}
        .store-bottom .outline-btn{min-height:24px;padding:3px 7px;font-size:9px}
        #catalog{padding-top:4px}
        .message{margin-top:5px;margin-bottom:5px;padding:7px 10px}
                /* Keep product names to at most 2 lines and descriptions to a compact 2-line maximum. */
        .item-card h4{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;overflow-wrap:anywhere;line-height:1.25;min-height:32px;max-height:32px}
        .item-description{display:-webkit-box;-webkit-box-orient:vertical;-webkit-line-clamp:2;overflow:hidden;overflow-wrap:anywhere;line-height:14px;min-height:28px;max-height:28px;margin-bottom:4px}
        @media(max-width:1100px){.header-inner{flex-wrap:wrap;padding:8px 0}.page-nav{flex-wrap:wrap}.items-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.store-picker-layout{grid-template-columns:minmax(0,1fr) 290px}.stores-grid{grid-template-columns:1fr}.hero-inner{grid-template-columns:1fr}.hero p{grid-column:1;grid-row:auto}.hero h1{grid-column:1;grid-row:auto}.hero .eyebrow{grid-column:1}}
        @media(max-width:760px){.container{width:min(100% - 20px,680px)}.header-inner{justify-content:center}.page-nav{justify-content:center;gap:8px}.nav{justify-content:center;gap:9px 12px}.account-area{justify-content:center}.profile-name{max-width:85px}.hero{padding:17px 0}.hero h1{font-size:26px}.hero p{font-size:12px}.section-heading{align-items:flex-start;flex-direction:column;gap:2px}.store-picker-layout{grid-template-columns:1fr}.stores-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.store-picker-layout .cart-box{grid-column:1;grid-row:auto;position:static;max-height:none;overflow:visible}.catalog-layout{display:block}.catalog-main{padding:11px}.filters{grid-template-columns:1fr 1fr}.filters input[type=search]{grid-column:1/-1}.filters button{width:100%}.items-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
        @media(max-width:420px){.stores-grid{grid-template-columns:1fr 1fr}.items-grid{grid-template-columns:1fr 1fr}.item-card{padding:9px}.item-image{height:55px}.add-form{flex-wrap:wrap}.add-form .primary-btn{width:100%;flex-basis:100%}.back-btn{min-height:32px;padding:5px 8px}.nav a{font-size:12px}}
            .store-details-before-cart{margin:8px 0;padding:9px 11px;border:1px solid var(--border);border-radius:10px;background:#fff}
        .store-picker-layout{display:block}
        .store-picker-layout .cart-box{margin-top:10px;max-height:none;overflow:visible}
        .cart-list{max-height:260px;overflow-y:auto;padding-right:3px}

</style>
</head>
<body>

<header class="site-header">
    <div class="container header-inner">

        <a class="logo" href="index.html" aria-label="LDD Home">
            L<span>D</span>D
        </a>

        <div class="page-nav">

            <button
                type="button"
                class="back-btn"
                onclick="goBack()"
            >
                ← Back
            </button>

            <nav class="nav" aria-label="Customer navigation">

                <a href="index.html">Home</a>

                <a href="restaurants.php">Food</a>

                <a href="#stores">Grocery</a>

                <a href="print_order.html">Printout</a>

                <a class="cart-top" href="#cart">
                    🛒 Cart<?= $cart_count > 0 ? ' (' . $cart_count . ')' : '' ?>
                </a>

            </nav>

            <div class="account-area">

                <?php if (isset($_SESSION['user_id'])): ?>

                    <a class="profile-link" href="profile.php">

                        <img
                            class="profile-avatar"
                            src="<?= h($current_user_image) ?>"
                            alt="Profile"
                            onerror="this.onerror=null;this.src='assets/default-profile.png';"
                        >

                        <span class="profile-name">
                            <?= h($current_user_name) ?>
                        </span>

                    </a>

                    <a class="logout-btn" href="backend/logout.php">
                        Logout
                    </a>

                <?php else: ?>

                    <a class="outline-btn" href="login_registration.html">
                        Login
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>
</header>



<?php if ($message !== ''): ?>
    <div class="message <?= $message_type === 'error' ? 'error' : 'success' ?>">
        <?= h($message) ?>
    </div>
<?php endif; ?>

<section class="hero">
    <div class="container hero-inner">
        <span class="eyebrow">LOCAL GROCERY DELIVERY</span>
        <h1>Fresh Essentials.<br><span>Delivered Locally.</span></h1>
        <p>Choose a local grocery store, browse available essentials, add items to your cart, and place your delivery order through LDD.</p>
    </div>
</section>

<section class="section" id="stores" style="padding:9px 0 12px;">
    <div class="container">
        <div class="section-heading store-section-heading">
            <div>
                <h2>Choose a Grocery Store</h2>
                <p>Local grocery stores available through the LDD platform.</p>
            </div>
        </div>
        <div class="store-picker-layout">
            <?php if (empty($stores)): ?>
                <div class="no-results">
                    No grocery stores found. Please import the grocery database SQL first.
                </div>
            <?php else: ?>
                <div class="stores-grid">
                    <?php foreach ($stores as $store): ?>
                        <?php $is_active = ((int) $store['id'] === $selected_store_id); ?>

                        <article class="store-card <?= $is_active ? 'active' : '' ?>">
                            <div class="store-icon">🛒</div>
                            <h3><?= h($store['store_name']) ?></h3>

                            <div class="store-meta">
                                📍 <?= h($store['location']) ?> ·
                                ⏱ <?= h($store['delivery_time']) ?>
                            </div>

                            <p class="store-description">
                                <?= h($store['description']) ?>
                            </p>

                            <div class="store-bottom">
                                <span class="rating">
                                    ★ <?= number_format((float) $store['rating'], 1) ?>
                                </span>

                                <a class="outline-btn"
                                   href="grocery.php?store_id=<?= (int) $store['id'] ?>#catalog">
                                    <?= $is_active ? 'Selected' : 'View Store' ?>
                                </a>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
                            <?php if ($selected_store !== null): ?>
                                <div class="selected-store store-details-before-cart">
                                    <div>
                                        <h3><?= h($selected_store['store_name']) ?></h3>
                                        <p>📍 <?= h($selected_store['location']) ?> · <?= h($selected_store['delivery_time']) ?></p>
                                    </div>
                                    <div class="rating">★ <?= number_format((float) $selected_store['rating'], 1) ?></div>
                                </div>
                            <?php endif; ?>

                            <aside class="cart-box" id="cart">
                <h3>
                    🛒 Your Cart
                    <span class="cart-count"><?= $cart_count ?></span>
                </h3>

                <?php if (empty($cart_items)): ?>
                    <div class="empty-cart">
                        Your cart is empty.<br>
                        Add grocery items from one store to continue.
                    </div>
                <?php else: ?>

                    <div class="cart-list">
                        
<?php foreach ($cart_items as $cart_item): ?>
    <div class="cart-item">
        <div class="cart-item-top">
            <div class="cart-item-info">
                <div class="cart-item-name">
                    <?= h($cart_item['item_name']) ?>
                </div>

                <div class="cart-item-meta">
                    ₹<?= number_format((float) $cart_item['price'], 2) ?>
                    · <?= h(grocery_quantity_label(
                        (int) $cart_item['cart_qty'],
                        (string) $cart_item['unit']
                    )) ?>
                </div>
            </div>

            <div class="cart-item-total">
                ₹<?= number_format((float) $cart_item['line_total'], 2) ?>
            </div>
        </div>

        <div class="cart-controls-row">
            <form method="post" class="cart-update">
                <input type="hidden" name="action" value="update_cart">

                <input
                    class="qty"
                    type="number"
                    name="qty[<?= (int) $cart_item['id'] ?>]"
                    value="<?= (int) $cart_item['cart_qty'] ?>"
                    min="0"
                    max="<?= (int) $cart_item['stock_qty'] ?>"
                    aria-label="Quantity for <?= h($cart_item['item_name']) ?>"
                >

                <button class="outline-btn" type="submit">
                    Update
                </button>
            </form>

            <form
                method="post"
                class="cart-remove"
                onsubmit="return confirm('Remove this item from your cart?');"
            >
                <input type="hidden" name="action" value="remove_item">
                <input type="hidden" name="item_id" value="<?= (int) $cart_item['id'] ?>">

                <button
                    class="danger-btn"
                    type="submit"
                    aria-label="Remove <?= h($cart_item['item_name']) ?>"
                >
                    Remove
                </button>
            </form>
        </div>
    </div>
<?php endforeach; ?>


                    <form method="post" style="margin-bottom: 14px;">
                        <input type="hidden" name="action" value="clear_cart">
                        <button class="danger-btn" type="submit">Clear Cart</button>
                    </form>

                    <div class="cart-summary">
                        <div class="summary-row">
                            <span>Subtotal</span>
                            <strong>₹<?= number_format($cart_subtotal, 2) ?></strong>
                        </div>

                        <div class="summary-row">
                            <span>Delivery Fee</span>
                            <strong>
                                <?= $cart_delivery_fee > 0
                                    ? '₹' . number_format($cart_delivery_fee, 2)
                                    : 'FREE' ?>
                            </strong>
                        </div>

                        <div class="summary-row total">
                            <span>Total</span>
                            <span>₹<?= number_format($cart_grand_total, 2) ?></span>
                        </div>
                    </div>

                    <div class="checkout-box">
                        <h4>Delivery Details</h4>

                        <?php if (!isset($_SESSION['user_id'])): ?>
                            <div class="checkout-note">
                                Please log in before placing your grocery order.
                            </div>

                            <a class="primary-btn"
                               href="login_registration.html"
                               style="width:100%;">
                                Login / Register
                            </a>
                        <?php else: ?>

                            <div class="checkout-note">
                                Free delivery for orders of ₹499 or more.
                            </div>

                            <form method="post">
                                <input type="hidden" name="action" value="place_order">

                                <label for="customer_name">Name</label>
                                <input
                                    class="field"
                                    id="customer_name"
                                    name="customer_name"
                                    type="text"
                                    maxlength="120"
                                    required
                                    placeholder="Enter your name"
                                >

                                <label for="phone">Phone</label>
                                <input
                                    class="field"
                                    id="phone"
                                    name="phone"
                                    type="tel"
                                    maxlength="20"
                                    required
                                    placeholder="Enter phone number"
                                >

                                <label for="delivery_address">Delivery Address</label>
                                <textarea
                                    class="field"
                                    id="delivery_address"
                                    name="delivery_address"
                                    rows="4"
                                    required
                                    placeholder="House / flat, street, locality, town"
                                ></textarea>

                                <label for="payment_method">Payment Method</label>
                                <select class="field" id="payment_method" name="payment_method">
                                    <option value="Cash on Delivery">Cash on Delivery</option>
                                    <option value="UPI">UPI</option>
                                </select>

                                <button class="primary-btn"
                                        type="submit"
                                        style="width:100%;">
                                    Place Grocery Order
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>

                <?php endif; ?>
            </aside>
            <?php endif; ?>
        </div>
    </div>
</section>

<section class="section" id="catalog">
    <div class="container">
        <div class="catalog-layout">

            <main class="catalog-main">
                <?php if ($selected_store !== null): ?>


                    <form method="get" class="filters">
                        <input type="hidden" name="store_id" value="<?= (int) $selected_store_id ?>">

                        <input
                            class="field"
                            type="search"
                            name="search"
                            value="<?= h($search) ?>"
                            placeholder="Search grocery items..."
                        >

                        <select class="field" name="category">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?= h($cat) ?>" <?= $category === $cat ? 'selected' : '' ?>>
                                    <?= h($cat) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <button class="primary-btn" type="submit">Search</button>
                    </form>

                    <?php if (empty($items)): ?>
                        <div class="no-results">
                            No grocery items found in this store for the selected filter.
                        </div>
                    <?php else: ?>
                        <div class="items-grid">
                            <?php foreach ($items as $item): ?>
                                <?php
                                $icon = $category_icons[$item['category']] ?? '🛍️';
                                $item_name_lower = strtolower($item['item_name']);
                                foreach ($item_icons as $keyword => $item_icon) {
                                    if (strpos($item_name_lower, $keyword) !== false) {
                                        $icon = $item_icon;
                                        break;
                                    }
                                }
                                $stock_qty = (int) $item['stock_qty'];

                                // Keep product descriptions short and tidy (about 1-2 lines).
                                $item_description = trim(preg_replace('/\\s+/', ' ', strip_tags((string) ($item['description'] ?? ''))));
                                if ($item_description === '') {
                                    $item_description = 'Quality grocery essential.';
                                }
                                if (mb_strlen($item_description, 'UTF-8') > 72) {
                                    $short_description = mb_substr($item_description, 0, 72, 'UTF-8');
                                    $last_space = mb_strrpos($short_description, ' ', 0, 'UTF-8');
                                    if ($last_space !== false && $last_space > 40) {
                                        $short_description = mb_substr($short_description, 0, $last_space, 'UTF-8');
                                    }
                                    $item_description = rtrim($short_description, " .,;:-") . '…';
                                }
                                ?>

                                <article class="item-card">
                                    <div class="item-image" aria-hidden="true">
                                        <?= $icon ?>
                                    </div>

                                    <div class="item-title-row">
                                        <span class="item-category"><?= h($item['category']) ?></span>
                                        <h4><?= h($item['item_name']) ?></h4>
                                    </div>

                                    <div class="item-description" title="<?= h($item_description) ?>">
                                        <?= h($item_description) ?>
                                    </div>

                                    <div class="price-row">
                                        <span class="price">₹<?= number_format((float) $item['price'], 2) ?></span>
                                        <span class="unit"><?= h($item['unit']) ?></span>
                                        <span class="stock"><?= $stock_qty > 0
                                            ? 'Available: ' . $stock_qty
                                            : 'Out of stock' ?></span>
                                    </div>

                                    <?php if ($stock_qty > 0): ?>
                                        <form method="post" class="add-form">
                                            <input type="hidden" name="action" value="add_to_cart">
                                            <input type="hidden" name="item_id" value="<?= (int) $item['id'] ?>">
                                            <input type="hidden" name="store_id" value="<?= (int) $selected_store_id ?>">

                                            <input
                                                class="qty"
                                                type="number"
                                                name="quantity"
                                                value="1"
                                                min="1"
                                                max="<?= $stock_qty ?>"
                                                aria-label="Quantity for <?= h($item['item_name']) ?>"
                                            >

                                            <button class="primary-btn" type="submit">
                                                Add to Cart
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <button class="outline-btn" type="button" disabled>
                                            Unavailable
                                        </button>
                                    <?php endif; ?>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <div class="no-results">
                        No grocery store is available. Please import the grocery database SQL first.
                    </div>
                <?php endif; ?>
            </main>



        </div>
    </div>
</section>

<footer class="footer">
    <div class="container">
        <p>&copy; 2026 Local Discover and Deliver (LDD). All rights reserved.</p>
        <p>Grocery Delivery Module · MCA Mini Project</p>
    </div>
</footer>

<script>
function goBack() {
    if (document.referrer && document.referrer !== window.location.href) {
        window.history.back();
    } else {
        window.location.href = "dashboard.php";
    }
}
</script>
<style>
    
/* Compact cart layout */
.store-picker-layout .cart-box {
    padding: 9px;
}

.cart-list {
    margin: 7px 0;
    max-height: 260px;
    overflow-y: auto;
    padding-right: 3px;
}

.cart-item {
    padding: 7px 0;
    border-bottom: 1px solid var(--border);
}

.cart-item-top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 7px;
}

.cart-item-info {
    min-width: 0;
    flex: 1;
}

.cart-item-name {
    font-size: 12px;
    line-height: 1.3;
    overflow-wrap: anywhere;
}

.cart-item-meta {
    margin-top: 2px;
    font-size: 10px;
    line-height: 1.4;
    overflow-wrap: anywhere;
}

.cart-item-total {
    font-size: 11px;
    white-space: nowrap;
}

.quantity-total {
    display: none !important;
}

.cart-controls-row {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 5px;
    margin-top: 5px;
}

.cart-update {
    display: flex;
    align-items: center;
    gap: 5px;
    margin: 0;
}

.cart-update .qty {
    width: 45px;
    padding: 4px 3px;
    font-size: 11px;
}

.cart-controls-row .outline-btn,
.cart-controls-row .danger-btn {
    min-height: 26px;
    padding: 3px 7px;
    font-size: 10px;
}

.cart-remove {
    display: flex;
    margin: 0;
}

.cart-summary {
    padding-top: 6px;
}

.summary-row {
    margin: 4px 0;
    font-size: 11px;
}

.summary-row.total {
    margin-top: 6px;
    padding-top: 6px;
    font-size: 14px;
}

.checkout-box {
    margin-top: 8px;
    padding-top: 8px;
}

.checkout-box h4 {
    margin-bottom: 6px;
    font-size: 13px;
}

.checkout-box .field {
    margin-bottom: 6px;
    padding: 7px 8px;
    font-size: 12px;
}

.checkout-box textarea.field {
    min-height: 48px;
}

.checkout-note {
    padding: 6px 7px;
    font-size: 10px;
}

@media (max-width: 760px) {
    .store-picker-layout .cart-box {
        max-height: none;
        overflow: visible;
    }

    .cart-list {
        max-height: 220px;
    }
}

</style>
</body>
</html>
