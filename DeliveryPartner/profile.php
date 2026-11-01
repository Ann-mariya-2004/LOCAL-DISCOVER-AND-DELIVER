<?php
session_start();
require_once "../backend/db.php";

/* Check Delivery Partner Login */
if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "Delivery Partner"
) {
    header("Location: ../login_registration.html");
    exit();
}

$user_id = (int) $_SESSION["user_id"];
$message = "";
$message_type = "";

/* Fetch Delivery Partner Profile */
$sql = "SELECT
            u.id,
            u.name,
            u.email,
            u.phone,
            u.address,
            u.location,
            u.profile_image,
            dp.vehicle_type,
            dp.vehicle_number,
            dp.availability_status
        FROM users u
        LEFT JOIN delivery_partners dp ON dp.user_id = u.id
        WHERE u.id = ?
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);
$profile = mysqli_fetch_assoc($result);
mysqli_stmt_close($stmt);

if (!$profile) {
    die("Profile not found. Please contact the administrator.");
}

/* Update Profile */
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $phone = trim($_POST["phone"] ?? "");
    $address = trim($_POST["address"] ?? "");
    $location = trim($_POST["location"] ?? "");
    $vehicle_type = trim($_POST["vehicle_type"] ?? "");
    $vehicle_number = strtoupper(trim($_POST["vehicle_number"] ?? ""));

    if (
        $name === "" ||
        $phone === "" ||
        $address === "" ||
        $vehicle_type === "" ||
        $vehicle_number === ""
    ) {
        $message = "Please fill in all required fields.";
        $message_type = "error";
    } elseif (!preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
        $message = "Please enter a valid phone number.";
        $message_type = "error";
    } else {
        mysqli_begin_transaction($conn);

        try {
            $updateUser = mysqli_prepare(
                $conn,
                "UPDATE users
                 SET name = ?, phone = ?, address = ?, location = ?
                 WHERE id = ? AND role = 'Delivery Partner'"
            );

            mysqli_stmt_bind_param(
                $updateUser,
                "ssssi",
                $name,
                $phone,
                $address,
                $location,
                $user_id
            );

            if (!mysqli_stmt_execute($updateUser)) {
                throw new Exception("Unable to update personal details.");
            }

            mysqli_stmt_close($updateUser);

            /* Check whether the partner record exists */
            $check = mysqli_prepare(
                $conn,
                "SELECT id FROM delivery_partners WHERE user_id = ? LIMIT 1"
            );

            mysqli_stmt_bind_param($check, "i", $user_id);
            mysqli_stmt_execute($check);
            $checkResult = mysqli_stmt_get_result($check);
            $partner = mysqli_fetch_assoc($checkResult);
            mysqli_stmt_close($check);

            if ($partner) {
                $updateVehicle = mysqli_prepare(
                    $conn,
                    "UPDATE delivery_partners
                     SET vehicle_type = ?, vehicle_number = ?
                     WHERE user_id = ?"
                );

                mysqli_stmt_bind_param(
                    $updateVehicle,
                    "ssi",
                    $vehicle_type,
                    $vehicle_number,
                    $user_id
                );

                if (!mysqli_stmt_execute($updateVehicle)) {
                    throw new Exception("Unable to update vehicle details.");
                }

                mysqli_stmt_close($updateVehicle);
            } else {
                $status = "Available";

                $insertPartner = mysqli_prepare(
                    $conn,
                    "INSERT INTO delivery_partners
                     (user_id, vehicle_type, vehicle_number, availability_status)
                     VALUES (?, ?, ?, ?)"
                );

                mysqli_stmt_bind_param(
                    $insertPartner,
                    "isss",
                    $user_id,
                    $vehicle_type,
                    $vehicle_number,
                    $status
                );

                if (!mysqli_stmt_execute($insertPartner)) {
                    throw new Exception("Unable to create delivery partner profile.");
                }

                mysqli_stmt_close($insertPartner);
            }

            mysqli_commit($conn);

            header("Location: profile.php?updated=1");
            exit();

        } catch (Throwable $e) {
            mysqli_rollback($conn);
            $message = "Profile update failed. Please check your database and try again.";
            $message_type = "error";
        }
    }

    /* Preserve entered values if validation fails */
    $profile["name"] = $name;
    $profile["phone"] = $phone;
    $profile["address"] = $address;
    $profile["location"] = $location;
    $profile["vehicle_type"] = $vehicle_type;
    $profile["vehicle_number"] = $vehicle_number;
}

if (isset($_GET["updated"]) && $_GET["updated"] === "1") {
    $message = "Profile updated successfully!";
    $message_type = "success";

    /* Refresh displayed values after update */
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $profile = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
}

$nameInitial = strtoupper(substr($profile["name"] ?? "D", 0, 1));
$vehicleType = $profile["vehicle_type"] ?? "";
$vehicleNumber = $profile["vehicle_number"] ?? "";
$availability = $profile["availability_status"] ?? "Available";
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>LDD | My Profile</title>

<style>
* {
    box-sizing: border-box;
    margin: 0;
    padding: 0;
}

:root {
    --primary: #0f766e;
    --primary-dark: #115e59;
    --text: #172b2a;
    --muted: #64748b;
    --border: #e2e8f0;
    --white: #ffffff;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f5f8fa;
    color: var(--text);
    font-size: 14px;
}

/* Compact top navigation */
.topbar {
    background: var(--white);
    border-bottom: 1px solid var(--border);
    padding: 11px 4%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.brand {
    color: var(--primary);
    font-size: 20px;
    font-weight: 800;
    white-space: nowrap;
}

.top-links {
    display: flex;
    align-items: center;
    gap: 15px;
    flex-wrap: wrap;
}

.top-links a {
    text-decoration: none;
    color: #475569;
    font-size: 12px;
    font-weight: 600;
}

.top-links a:hover {
    color: var(--primary);
}

.top-links .logout {
    background: #fff1f2;
    color: #be123c;
    padding: 8px 11px;
    border-radius: 7px;
}

/* Main content */
.container {
    max-width: 920px;
    margin: 18px auto;
    padding: 0 16px 22px;
}

.page-heading {
    margin-bottom: 14px;
}

.page-heading h1 {
    font-size: 23px;
    margin-bottom: 4px;
}

.page-heading p {
    color: var(--muted);
    font-size: 12px;
    line-height: 1.5;
}

/* Compact profile banner */
.profile-header {
    background: linear-gradient(120deg, #0f766e, #14b8a6);
    color: white;
    padding: 15px 18px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    gap: 13px;
    margin-bottom: 14px;
}

.avatar {
    width: 53px;
    height: 53px;
    flex-shrink: 0;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    border: 2px solid rgba(255,255,255,.65);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    font-weight: 800;
    overflow: hidden;
}

.avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.profile-header h2 {
    font-size: 18px;
    margin-bottom: 4px;
    overflow-wrap: anywhere;
}

.profile-header p {
    font-size: 12px;
    opacity: .92;
    overflow-wrap: anywhere;
}

.status {
    display: inline-block;
    margin-top: 6px;
    padding: 4px 9px;
    background: rgba(255,255,255,.2);
    border-radius: 20px;
    font-size: 11px;
}

/* Form cards */
.card {
    background: var(--white);
    border: 1px solid var(--border);
    border-radius: 10px;
    padding: 17px 19px;
    margin-bottom: 12px;
    box-shadow: 0 2px 7px rgba(15, 23, 42, .025);
}

.card-title {
    font-size: 15px;
    font-weight: 750;
    margin-bottom: 13px;
    padding-bottom: 9px;
    border-bottom: 1px solid var(--border);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px 15px;
}

.field {
    display: flex;
    flex-direction: column;
    gap: 5px;
    min-width: 0;
}

.field.full {
    grid-column: 1 / -1;
}

label {
    font-size: 12px;
    color: #475569;
    font-weight: 650;
}

input,
textarea,
select {
    width: 100%;
    padding: 9px 10px;
    border: 1px solid #cbd5e1;
    border-radius: 7px;
    background: white;
    color: var(--text);
    font-family: inherit;
    font-size: 13px;
    outline: none;
    transition: border-color .2s, box-shadow .2s;
}

input:focus,
textarea:focus,
select:focus {
    border-color: var(--primary);
    box-shadow: 0 0 0 3px rgba(15,118,110,.08);
}

textarea {
    min-height: 60px;
    resize: vertical;
}

input[readonly] {
    background: #f8fafc;
    color: #64748b;
    cursor: not-allowed;
}

/* Save button */
.actions {
    display: flex;
    justify-content: flex-end;
    margin-top: 14px;
}

button {
    background: var(--primary);
    color: white;
    border: none;
    padding: 10px 17px;
    border-radius: 7px;
    font-family: inherit;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s;
}

button:hover {
    background: var(--primary-dark);
}

.message {
    padding: 10px 12px;
    border-radius: 7px;
    font-size: 13px;
    margin-bottom: 12px;
}

.message.success {
    background: #dcfce7;
    color: #166534;
}

.message.error {
    background: #fee2e2;
    color: #991b1b;
}

.note {
    color: var(--muted);
    font-size: 11px;
    margin-top: 10px;
    line-height: 1.5;
}

/* Mobile responsive */
@media (max-width: 650px) {
    .topbar {
        padding: 11px 14px;
        align-items: flex-start;
        flex-direction: column;
        gap: 10px;
    }

    .top-links {
        gap: 12px;
    }

    .container {
        margin-top: 15px;
        padding: 0 12px 20px;
    }

    .page-heading h1 {
        font-size: 21px;
    }

    .profile-header {
        padding: 13px;
    }

    .card {
        padding: 14px;
    }

    .form-grid {
        grid-template-columns: 1fr;
        gap: 11px;
    }

    .field.full {
        grid-column: auto;
    }

    .actions button {
        width: 100%;
    }
}
</style>
</head>

<body>

<header class="topbar">
    <div class="brand">LDD <span style="color:#334155;font-size:13px;">Delivery</span></div>

    <nav class="top-links">
        <a href="delivery_dashboard.php">Dashboard</a>
        <a href="my_deliveries.php">My Deliveries</a>
        <a href="reports.php">Reports</a>
        <a href="../backend/logout.php" class="logout">Logout</a>
    </nav>
</header>

<main class="container">

    <div class="page-heading">
        <h1>My Profile</h1>
        <p>Manage your personal information and delivery vehicle details.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message <?= htmlspecialchars($message_type) ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <section class="profile-header">
        <div class="avatar">
            <?php if (!empty($profile["profile_image"])): ?>
                <img src="../<?= htmlspecialchars($profile["profile_image"]) ?>" alt="Profile photo">
            <?php else: ?>
                <?= htmlspecialchars($nameInitial) ?>
            <?php endif; ?>
        </div>

        <div>
            <h2><?= htmlspecialchars($profile["name"] ?? "Delivery Partner") ?></h2>
            <p><?= htmlspecialchars($profile["email"] ?? "") ?></p>
            <span class="status">
                ● <?= htmlspecialchars($availability) ?>
            </span>
        </div>
    </section>

    <form method="POST" action="profile.php">

        <section class="card">
            <h3 class="card-title">Personal Information</h3>

            <div class="form-grid">
                <div class="field">
                    <label for="name">Full Name *</label>
                    <input type="text" id="name" name="name"
                           value="<?= htmlspecialchars($profile["name"] ?? "") ?>"
                           maxlength="100" required>
                </div>

                <div class="field">
                    <label for="email">Email Address</label>
                    <input type="email" id="email"
                           value="<?= htmlspecialchars($profile["email"] ?? "") ?>"
                           readonly>
                </div>

                <div class="field">
                    <label for="phone">Phone Number *</label>
                    <input type="tel" id="phone" name="phone"
                           value="<?= htmlspecialchars($profile["phone"] ?? "") ?>"
                           maxlength="20" required>
                </div>

                <div class="field">
                    <label for="location">Location</label>
                    <input type="text" id="location" name="location"
                           value="<?= htmlspecialchars($profile["location"] ?? "") ?>"
                           maxlength="150">
                </div>

                <div class="field full">
                    <label for="address">Address *</label>
                    <textarea id="address" name="address" maxlength="500"
                              required><?= htmlspecialchars($profile["address"] ?? "") ?></textarea>
                </div>
            </div>
        </section>

        <section class="card">
            <h3 class="card-title">Delivery Vehicle Details</h3>

            <div class="form-grid">
                <div class="field">
                    <label for="vehicle_type">Vehicle Type *</label>
                    <select id="vehicle_type" name="vehicle_type" required>
                        <option value="">Select vehicle type</option>
                        <?php
                        $vehicleOptions = ["Bicycle", "Bike", "Scooter", "Car", "Van", "Other"];
                        foreach ($vehicleOptions as $option):
                        ?>
                            <option value="<?= htmlspecialchars($option) ?>"
                                <?= $vehicleType === $option ? "selected" : "" ?>>
                                <?= htmlspecialchars($option) ?>
                            </option>
                        <?php endforeach; ?>
                        <?php if ($vehicleType !== "" && !in_array($vehicleType, $vehicleOptions, true)): ?>
                            <option value="<?= htmlspecialchars($vehicleType) ?>" selected>
                                <?= htmlspecialchars($vehicleType) ?>
                            </option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="field">
                    <label for="vehicle_number">Vehicle Number *</label>
                    <input type="text" id="vehicle_number" name="vehicle_number"
                           value="<?= htmlspecialchars($vehicleNumber) ?>"
                           maxlength="30" required>
                </div>
            </div>

            <p class="note">
                Your email address and availability status are managed separately.
                Changing vehicle details here does not change your availability.
            </p>

            <div class="actions">
                <button type="submit">Save Profile Changes</button>
            </div>
        </section>

    </form>

</main>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>
<script>
Swal.fire({
    icon: 'success',
    title: 'Profile Updated Successfully!',
    text: 'Your profile details have been saved.',
    showConfirmButton: false,
    timer: 2000,
    timerProgressBar: true
}).then(() => {
    window.location.href = 'delivery_dashboard.php';
});
</script>
<?php endif; ?>
</body>
</html>