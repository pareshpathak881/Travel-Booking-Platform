<?php
session_start();
// Security Check
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

// Generate CSRF token for security
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Flash Message Utility
$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

$editPackage = null;
$errors = [];

// Handle POST Requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Security Validation Failed.");
    }

    $action = $_POST['action'] ?? '';

    // Handle Create/Update
    if ($action === 'create' || $action === 'update') {
        $title = trim($_POST['title'] ?? '');
        $destination = trim($_POST['destination'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $days = intval($_POST['duration_days'] ?? 0);
        $image = trim($_POST['image_url'] ?? '');
        $availability = intval($_POST['availability'] ?? 0);

        // Validation
        if (empty($title)) $errors[] = "Title is required.";
        if ($price <= 0) $errors[] = "Price must be greater than zero.";
        if (empty($description)) $errors[] = "Description is required.";

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO packages (title, destination, description, price, duration_days, image_url, availability) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute([$title, $destination, $description, $price, $days, $image, $availability]);
                $_SESSION['flash_message'] = 'Package created successfully.';
            } else {
                $stmt = $pdo->prepare('UPDATE packages SET title=?, destination=?, description=?, price=?, duration_days=?, image_url=?, availability=? WHERE package_id=?');
                $stmt->execute([$title, $destination, $description, $price, $days, $image, $availability, intval($_POST['package_id'])]);
                $_SESSION['flash_message'] = 'Package updated successfully.';
            }
            header('Location: manage-packages.php');
            exit;
        }
    }

    // Handle Delete
    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM packages WHERE package_id = ?');
        $stmt->execute([intval($_POST['package_id'])]);
        $_SESSION['flash_message'] = 'Package deleted.';
        header('Location: manage-packages.php');
        exit;
    }
}

// Fetch for Edit
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE package_id = ?');
    $stmt->execute([intval($_GET['edit'])]);
    $editPackage = $stmt->fetch();
}

// Fetch all for list
$packages = $pdo->query('SELECT * FROM packages ORDER BY package_id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Packages | CoastalVoyage</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --forest: #2d6a4f; --light-gray: #f4f7f6; --error: #d90429; --white: #ffffff; }
        body { font-family: 'Poppins', sans-serif; background: var(--light-gray); padding: 2rem; color: #333; }
        .container { max-width: 1200px; margin: 0 auto; }
        .layout { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        @media (max-width: 900px) { .layout { grid-template-columns: 1fr; } }
        
        .card { background: var(--white); padding: 2rem; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); }
        .search-input { width: 100%; padding: 0.8rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        
        label { display: block; margin-top: 1rem; font-weight: 500; font-size: 0.9rem; }
        input, textarea { width: 100%; padding: 0.7rem; margin-top: 0.3rem; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        
        .btn-submit { width: 100%; background: var(--forest); color: white; border: none; padding: 1rem; border-radius: 6px; cursor: pointer; margin-top: 1.5rem; font-weight: 600; }
        .alert { background: #d8f3dc; color: var(--forest); padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        .error { background: #ffe3e3; color: var(--error); padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        
        table { width: 100%; border-collapse: collapse; }
        th { text-align: left; padding: 1rem; background: #f8f9fa; border-bottom: 2px solid #eee; }
        td { padding: 1rem; border-bottom: 1px solid #eee; }
        tr:hover { background: #fafafa; }
        .action-link { color: var(--forest); text-decoration: none; font-weight: 600; margin-right: 10px; }
        .del-btn { background: none; border: none; color: var(--error); cursor: pointer; font-weight: 600; }
    </style>
    <link rel="stylesheet" href="../assets/css/design-system.css">
</head>
<body>
    <div class="container">
        <?php if ($message): ?><div class="alert"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="error"><?= implode('<br>', $errors) ?></div><?php endif; ?>

        <div class="layout">
            <section class="card">
                <h3><?= $editPackage ? 'Edit Package' : 'Add New Package' ?></h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="<?= $editPackage ? 'update' : 'create' ?>">
                    <?php if ($editPackage): ?><input type="hidden" name="package_id" value="<?= $editPackage['package_id'] ?>"><?php endif; ?>
                    
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editPackage['title'] ?? '') ?>" required>
                    
                    <label>Destination</label>
                    <input type="text" name="destination" value="<?= htmlspecialchars($editPackage['destination'] ?? '') ?>" required>
                    
                    <label>Description</label>
                    <textarea name="description" rows="3" required><?= htmlspecialchars($editPackage['description'] ?? '') ?></textarea>
                    
                    <label>Price ($)</label>
                    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($editPackage['price'] ?? '') ?>" required>
                    
                    <label>Duration (Days)</label>
                    <input type="number" name="duration_days" value="<?= htmlspecialchars($editPackage['duration_days'] ?? '') ?>" required>
                    
                    <label>Availability</label>
                    <input type="number" name="availability" value="<?= htmlspecialchars($editPackage['availability'] ?? '') ?>" required>
                    
                    <label>Image URL</label>
                    <input type="url" name="image_url" value="<?= htmlspecialchars($editPackage['image_url'] ?? '') ?>">
                    
                    <button type="submit" class="btn-submit"><?= $editPackage ? 'Update Package' : 'Create Package' ?></button>
                    <?php if ($editPackage): ?>
                        <a href="manage-packages.php" style="display:block; text-align:center; margin-top:10px; color:#666;">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="card">
                <input type="text" id="searchInput" class="search-input" placeholder="🔍 Search packages..." onkeyup="filterTable()">
                <table id="pkgTable">
                    <thead><tr><th>Title</th><th>Price</th><th>Avail.</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php if (empty($packages)): ?>
                            <tr><td colspan="4">No packages found.</td></tr>
                        <?php else: ?>
                            <?php foreach ($packages as $pkg): ?>
                            <tr>
                                <td><?= htmlspecialchars($pkg['title']) ?></td>
                                <td>$<?= number_format($pkg['price'], 2) ?></td>
                                <td><?= htmlspecialchars($pkg['availability']) ?></td>
                                <td>
                                    <a href="?edit=<?= $pkg['package_id'] ?>" class="action-link">Edit</a>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Confirm permanent deletion?');">
                                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="package_id" value="<?= $pkg['package_id'] ?>">
                                        <button type="submit" class="del-btn">Delete</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <tr id="noResults" style="display:none;"><td colspan="4">No packages match your search.</td></tr>
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <script>
        function filterTable() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let table = document.getElementById("pkgTable");
            let rows = table.getElementsByTagName("tr");
            let found = false;

            for (let i = 1; i < rows.length - 1; i++) { // Skip header and 'noResults' row
                let text = rows[i].textContent.toLowerCase();
                if (text.includes(input)) {
                    rows[i].style.display = "";
                    found = true;
                } else {
                    rows[i].style.display = "none";
                }
            }
            document.getElementById("noResults").style.display = found ? "none" : "";
        }
    </script>
</body>
</html>