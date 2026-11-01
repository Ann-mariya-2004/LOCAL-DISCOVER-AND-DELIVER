<?php
session_start();
require_once __DIR__ . '/../backend/db.php';

if (!isset($conn) || !($conn instanceof mysqli)) {
    die('Database connection is not available.');
}
$conn->set_charset('utf8mb4');

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login_registration.html');
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role'] ?? ''));

if ($role !== 'vendor') {
    header('Location: ../dashboard.php');
    exit;
}

$message = '';
$message_type = 'success';

function h($value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------------------------------------
   STORE ACTIONS
--------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'save_store') {
        $store_name = trim($_POST['store_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $location = trim($_POST['location'] ?? '');
        $category = trim($_POST['category'] ?? 'Grocery Store');
        $description = trim($_POST['description'] ?? '');
        $delivery_time = trim($_POST['delivery_time'] ?? '30-45 min');

        if ($store_name === '' || $location === '') {
            $message = 'Store name and location are required.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare(
                'SELECT id FROM grocery_stores WHERE vendor_id = ? LIMIT 1'
            );
            $stmt->bind_param('i', $user_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if ($existing) {
                $store_id = (int)$existing['id'];
                $stmt = $conn->prepare(
                    'UPDATE grocery_stores
                     SET store_name = ?, phone = ?, location = ?, category = ?,
                            description = ?, delivery_time = ?, is_active = 1
                     WHERE id = ? AND vendor_id = ?'
                );
                $stmt->bind_param(
                    'ssssssii',
                    $store_name, $phone, $location, $category,
                    $description, $delivery_time, $store_id, $user_id
                );
            } else {
                $stmt = $conn->prepare(
                    'INSERT INTO grocery_stores
                     (vendor_id, store_name, phone, location, category,
                      description, delivery_time, rating, is_active)
                     VALUES (?, ?, ?, ?, ?, ?, ?, 4.5, 1)'
                );
                $stmt->bind_param(
                    'issssss',
                    $user_id, $store_name, $phone, $location,
                    $category, $description, $delivery_time
                );
            }

            if ($stmt->execute()) {
                $message = 'Grocery store saved successfully.';
            } else {
                $message = 'Unable to save grocery store.';
                $message_type = 'error';
            }
            $stmt->close();
        }
         }

    elseif ($action === 'add_item') {
        $store_id = (int)($_POST['store_id'] ?? 0);
        $item_name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unit = trim($_POST['unit'] ?? '1 pc');
        $price = (float)($_POST['price'] ?? 0);
        $stock_qty = max(0, (int)($_POST['stock_qty'] ?? 0));
        $description = trim($_POST['description'] ?? '');

        $check = $conn->prepare(
            'SELECT id FROM grocery_stores WHERE id = ? AND vendor_id = ? LIMIT 1'
        );
        $check->bind_param('ii', $store_id, $user_id);
        $check->execute();
        $valid_store = $check->get_result()->fetch_assoc();
        $check->close();

        if (!$valid_store) {
            $message = 'Invalid grocery store.';
            $message_type = 'error';
        } elseif ($item_name === '' || $category === '' || $price <= 0) {
            $message = 'Item name, category and a valid price are required.';
            $message_type = 'error';
        } else {
            $stmt = $conn->prepare(
                'INSERT INTO grocery_items
                 (store_id, item_name, category, unit, price, stock_qty, description, is_available)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
                  );
            $stmt->bind_param(
                'isssdis',
                $store_id, $item_name, $category, $unit, $price, $stock_qty, $description
            );

            if ($stmt->execute()) {
                $message = 'Grocery item added successfully.';
            } else {
                $message = 'Unable to add grocery item.';
                $message_type = 'error';
            }
            $stmt->close();
        }
    }

    elseif ($action === 'update_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $item_name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $unit = trim($_POST['unit'] ?? '1 pc');
        $price = (float)($_POST['price'] ?? 0);
        $stock_qty = max(0, (int)($_POST['stock_qty'] ?? 0));
        $description = trim($_POST['description'] ?? '');
        $is_available = isset($_POST['is_available']) ? 1 : 0;

        $stmt = $conn->prepare(
            'UPDATE grocery_items gi
             INNER JOIN grocery_stores gs ON gs.id = gi.store_id
             SET gi.item_name = ?, gi.category = ?, gi.unit = ?, gi.price = ?,
                 gi.stock_qty = ?, gi.description = ?, gi.is_available = ?
             WHERE gi.id = ? AND gs.vendor_id = ?'
        );
        $stmt->bind_param(
            'sssdisiii',
            $item_name, $category, $unit, $price, $stock_qty,
            $description, $is_available, $item_id, $user_id
        );

        if ($stmt->execute() && $stmt->affected_rows >= 0) {
            $message = 'Grocery item updated successfully.';
        } else {
            $message = 'Unable to update grocery item.';
            $message_type = 'error';
        }
        $stmt->close();
    }

    elseif ($action === 'delete_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);

        $stmt = $conn->prepare(
            'DELETE gi FROM grocery_items gi
             INNER JOIN grocery_stores gs ON gs.id = gi.store_id
             WHERE gi.id = ? AND gs.vendor_id = ?'
        );
        $stmt->bind_param('ii', $item_id, $user_id);

        if ($stmt->execute()) {
            $message = 'Grocery item deleted successfully.';
        } else {
            $message = 'Unable to delete grocery item.';
            $message_type = 'error';
        }
        $stmt->close();
    }
}

/* ---------------------------------------------------------
   LOAD VENDOR STORE
--------------------------------------------------------- */
$store = null;
$stmt = $conn->prepare(
    'SELECT id, store_name, phone, location, category, description,
            delivery_time, rating, is_active
     FROM grocery_stores
     WHERE vendor_id = ?
     ORDER BY id DESC
      LIMIT 1'
);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$stmt->close();

$items = [];
$edit_item = null;
$edit_id = (int)($_GET['edit_id'] ?? 0);

if ($store && $edit_id > 0) {
    $stmt = $conn->prepare(
        'SELECT id, item_name, category, unit, price, stock_qty, description, is_available
         FROM grocery_items WHERE id = ? AND store_id = ? LIMIT 1'
    );
    $stmt->bind_param('ii', $edit_id, $store['id']);
    $stmt->execute();
    $edit_item = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}

if ($store) {
    $store_id = (int)$store['id'];
    $stmt = $conn->prepare(
        'SELECT id, item_name, category, unit, price, stock_qty,
                description, is_available
         FROM grocery_items
         WHERE store_id = ?
         ORDER BY category ASC, item_name ASC'
         );
    $stmt->bind_param('i', $store_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Manage Grocery | LDD Vendor</title>
<style>
:root{--primary:#0f766e;--dark:#115e59;--accent:#14b8a6;--bg:#f5f8f8;--white:#fff;--text:#17312f;--muted:#657673;--border:#dce7e5;--danger:#b91c1c;--shadow:0 10px 28px rgba(15,65,60,.08)}
*{box-sizing:border-box;margin:0;padding:0}body{font-family:Arial,Helvetica,sans-serif;background:var(--bg);color:var(--text);line-height:1.6}a{text-decoration:none;color:inherit}button,input,select,textarea{font:inherit}.container{width:min(1180px,92%);margin:auto}
.header{background:var(--white);border-bottom:1px solid var(--border);position:sticky;top:0;z-index:20}.header-inner{min-height:72px;display:flex;align-items:center;justify-content:space-between;gap:20px}.logo{font-size:28px;font-weight:800;color:var(--primary)}.logo span{color:var(--accent)}.nav{display:flex;gap:18px;align-items:center;flex-wrap:wrap}.nav a{font-weight:600;color:#3b514f}.nav a:hover{color:var(--primary)}.back{padding:9px 14px;border:1px solid #c8dfdb;border-radius:8px;background:#fff;color:var(--dark)}
.hero{background:linear-gradient(135deg,#e8f8f5,#f7fbfa);padding:42px 0}.hero h1{font-size:36px;color:#123c38}.hero p{color:var(--muted);margin-top:6px}
.message{margin:20px 0;padding:12px 15px;border-radius:9px;font-weight:700}.success{background:#ecfdf5;color:#166534;border:1px solid #bbf7d0}.error{background:#fef2f2;color:#b91c1c;border:1px solid #fecaca}
.section{padding:32px 0}.section-title{margin-bottom:18px}.section-title h2{font-size:25px;color:#123c38}.section-title p{color:var(--muted);font-size:14px}
.card{background:#fff;border:1px solid var(--border);border-radius:15px;box-shadow:var(--shadow);padding:22px}.grid{display:grid;grid-template-columns:1fr 1fr;gap:20px}.field-group{margin-bottom:14px}.field-group label{display:block;margin-bottom:5px;font-weight:700;font-size:13px;color:#48615d}.field{width:100%;padding:11px 12px;border:1px solid var(--border);border-radius:8px;background:#fff;outline:none}.field:focus{border-color:var(--accent);box-shadow:0 0 0 3px rgba(20,184,166,.1)}textarea.field{resize:vertical}.btn{display:inline-flex;align-items:center;justify-content:center;border:0;border-radius:8px;padding:10px 15px;font-weight:700;cursor:pointer}.btn-primary{background:var(--primary);color:#fff}.btn-primary:hover{background:var(--dark)}.btn-danger{background:#fff;color:var(--danger);border:1px solid #fecaca}.btn-small{padding:8px 11px;font-size:13px}
.item-table{width:100%;border-collapse:collapse}.item-table th,.item-table td{text-align:left;padding:12px 10px;border-bottom:1px solid var(--border);vertical-align:middle}.item-table th{font-size:12px;text-transform:uppercase;color:#5a706c;background:#f8fbfa}.item-table td{font-size:14px}.status{font-size:12px;font-weight:800;padding:5px 9px;border-radius:999px;display:inline-block}.status.on{background:#ecfdf5;color:#166534}.status.off{background:#f3f4f6;color:#4b5563}.actions{display:flex;gap:7px;flex-wrap:wrap}.empty{padding:30px;text-align:center;color:var(--muted)}
@media(max-width:850px){.grid{grid-template-columns:1fr}.item-table{display:block;overflow-x:auto;white-space:nowrap}.header-inner{flex-direction:column;padding:13px 0}.nav{justify-content:center}.hero h1{font-size:30px}}
</style>
</head>
<body>
<header class="header">
<div class="container header-inner">
    <a class="logo" href="vendor_dashboard.php">L<span>D</span>D</a>
<nav class="nav">
<a class="back" href="vendor_dashboard.php">← Vendor Dashboard</a>
<a href="../index.html">Home</a>
<a href="../backend/logout.php">Logout</a>
</nav>
</div>
</header>

<section class="hero"><div class="container"><h1>Manage Grocery</h1><p>Create your grocery store and manage the items available to LDD customers.</p></div></section>

<main class="container">
<?php if ($message !== ''): ?>
<div class="message <?= $message_type === 'error' ? 'error' : 'success' ?>"><?= h($message) ?></div>
<?php endif; ?>

<section class="section">
<div class="section-title"><h2>My Grocery Store</h2><p>Store information displayed to customers on the grocery page.</p></div>
<div class="card">
<form method="post">
<input type="hidden" name="action" value="save_store">
<div class="grid">
<div class="field-group"><label>Store Name</label><input class="field" type="text" name="store_name" maxlength="120" required value="<?= h($store['store_name'] ?? '') ?>" placeholder="Example: Fresh Basket Mart"></div>
<div class="field-group"><label>Phone</label><input class="field" type="text" name="phone" maxlength="20" value="<?= h($store['phone'] ?? '') ?>" placeholder="9876543210"></div>
<div class="field-group"><label>Location</label><input class="field" type="text" name="location" maxlength="255" required value="<?= h($store['location'] ?? '') ?>" placeholder="Muvattupuzha"></div>
<div class="field-group"><label>Category</label><select class="field" name="category"><option <?= (($store['category'] ?? '') === 'Supermarket') ? 'selected' : '' ?>>Supermarket</option><option <?= (($store['category'] ?? '') === 'Grocery Store') ? 'selected' : '' ?>>Grocery Store</option><option <?= (($store['category'] ?? '') === 'Mini Mart') ? 'selected' : '' ?>>Mini Mart</option></select></div>
<div class="field-group"><label>Delivery Time</label><select class="field" name="delivery_time"><option <?= (($store['delivery_time'] ?? '') === '20-30 min') ? 'selected' : '' ?>>20-30 min</option><option <?= (($store['delivery_time'] ?? '30-45 min') === '30-45 min') ? 'selected' : '' ?>>30-45 min</option><option <?= (($store['delivery_time'] ?? '') === '40-50 min') ? 'selected' : '' ?>>40-50 min</option></select></div>
<div class="field-group"><label>Description</label><textarea class="field" name="description" rows="3" maxlength="500" placeholder="Short description about your grocery store"><?= h($store['description'] ?? '') ?></textarea></div>
</div>
<button class="btn btn-primary" type="submit"><?= $store ? 'Update Store' : 'Create Store' ?></button>
</form>
</div>
</section>

<section class="section" id="items">
<div class="section-title"><h2>Grocery Items</h2><p>Add products that customers can browse and order.</p></div>

<?php if (!$store): ?>
<div class="card empty">Create your grocery store first. Then you can add grocery items.</div>
<?php else: ?>
<div class="card" style="margin-bottom:20px;">
<form method="post">
<input type="hidden" name="action" value="<?= $edit_item ? 'update_item' : 'add_item' ?>">
<input type="hidden" name="store_id" value="<?= (int)$store['id'] ?>">
<?php if ($edit_item): ?>
<input type="hidden" name="item_id" value="<?= (int)$edit_item['id'] ?>">
<?php endif; ?>
<div class="grid">
<div class="field-group"><label>Item Name</label><input class="field" type="text" name="item_name" maxlength="150" required value="<?= h($edit_item['item_name'] ?? '') ?>" placeholder="Example: Milk"></div>
<div class="field-group"><label>Category</label><input class="field" type="text" name="category" maxlength="80" required value="<?= h($edit_item['category'] ?? '') ?>" placeholder="Example: Dairy"></div>
<div class="field-group"><label>Unit</label><input class="field" type="text" name="unit" maxlength="50" value="<?= h($edit_item['unit'] ?? '1 pc') ?>" required placeholder="1 litre / 1 kg / 1 pack"></div>
<div class="field-group"><label>Price (₹)</label><input class="field" type="number" name="price" min="0.01" step="0.01" required value="<?= h($edit_item['price'] ?? '') ?>" placeholder="58.00"></div>
<div class="field-group"><label>Stock Quantity</label><input class="field" type="number" name="stock_qty" min="0" value="<?= h($edit_item['stock_qty'] ?? '10') ?>" required></div>
<div class="field-group"><label>Description</label><input class="field" type="text" name="description" maxlength="500" value="<?= h($edit_item['description'] ?? '') ?>" placeholder="Short product description"></div>
</div>
<?php if ($edit_item): ?>
    <label style="display:block;margin:0 0 14px;font-size:13px;font-weight:700;color:#48615d;"><input type="checkbox" name="is_available" value="1" <?= (int)$edit_item['is_available'] === 1 ? 'checked' : '' ?>> Available to customers</label>
<button class="btn btn-primary" type="submit">Update Grocery Item</button>
<a class="btn" style="background:#f3f4f6;color:#374151;margin-left:7px;" href="manage_grocery.php">Cancel</a>
<?php else: ?>
<button class="btn btn-primary" type="submit">+ Add Grocery Item</button>
<?php endif; ?>
</form>
</div>

<div class="card">
<?php if (empty($items)): ?>
<div class="empty">No grocery items added yet.</div>
<?php else: ?>
<table class="item-table">
<thead><tr><th>Item</th><th>Category</th><th>Unit</th><th>Price</th><th>Stock</th><th>Status</th><th>Action</th></tr></thead>
<tbody>
<?php foreach ($items as $item): ?>
<tr>
<td><?= h($item['item_name']) ?></td>
<td><?= h($item['category']) ?></td>
<td><?= h($item['unit']) ?></td>
<td>₹<?= number_format((float)$item['price'], 2) ?></td>
<td><?= (int)$item['stock_qty'] ?></td>
<td><span class="status <?= (int)$item['is_available'] === 1 ? 'on' : 'off' ?>"><?= (int)$item['is_available'] === 1 ? 'Available' : 'Hidden' ?></span></td>
<td>
<div class="actions">
<a class="btn btn-small" style="background:#eaf7f5;color:#115e59;" href="manage_grocery.php?edit_id=<?= (int)$item['id'] ?>#items">Edit</a>
<form method="post" onsubmit="return confirm('Delete this grocery item?');">
<input type="hidden" name="action" value="delete_item">
<input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>">
<button class="btn btn-small btn-danger" type="submit">Delete</button>
</form>
</div>
</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
<?php endif; ?>
</div>
<?php endif; ?>
</section>
</main>

<footer style="background:#123f3b;color:#e8f5f3;text-align:center;padding:24px;margin-top:25px;font-size:14px;">Local Discover and Deliver (LDD) · Vendor Grocery Management</footer>
</body>
</html>
