<?php
session_start();
if (empty($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../login.php');
    exit;
}
require_once __DIR__ . '/../config/db.php';

if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$message = $_SESSION['flash_message'] ?? '';
unset($_SESSION['flash_message']);

$editPackage = null;
$errors = [];

// Handle POST Logic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) die("Security failure.");

    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $data = [
            'title' => trim($_POST['title']),
            'dest'  => trim($_POST['destination']),
            'desc'  => trim($_POST['description']),
            'price' => floatval($_POST['price']),
            'days'  => intval($_POST['duration_days']),
            'img'   => trim($_POST['image_url']),
            'avail' => intval($_POST['availability'])
        ];

        // Validation
        if ($data['price'] <= 0) $errors[] = "Price must be greater than zero.";
        if (empty($data['title'])) $errors[] = "Title is required.";

        if (empty($errors)) {
            if ($action === 'create') {
                $stmt = $pdo->prepare('INSERT INTO packages (title, destination, description, price, duration_days, image_url, availability) VALUES (?,?,?,?,?,?,?)');
                $stmt->execute(array_values($data));
                $_SESSION['flash_message'] = 'Package created successfully.';
            } else {
                $stmt = $pdo->prepare('UPDATE packages SET title=?, destination=?, description=?, price=?, duration_days=?, image_url=?, availability=? WHERE package_id=?');
                $stmt->execute([...array_values($data), intval($_POST['package_id'])]);
                $_SESSION['flash_message'] = 'Package updated successfully.';
            }
            header('Location: manage-packages.php');
            exit;
        }
    }

    if ($action === 'delete') {
        $stmt = $pdo->prepare('DELETE FROM packages WHERE package_id = ?');
        $stmt->execute([intval($_POST['package_id'])]);
        $_SESSION['flash_message'] = 'Package deleted.';
        header('Location: manage-packages.php');
        exit;
    }
}

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('SELECT * FROM packages WHERE package_id = ?');
    $stmt->execute([intval($_GET['edit'])]);
    $editPackage = $stmt->fetch();
}

$packages = $pdo->query('SELECT * FROM packages ORDER BY package_id DESC')->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Packages | Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">
    <style>
        :root { --forest-green: #2d6a4f; }
        body { font-family: 'Poppins', sans-serif; background: #f4f7f6; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        .grid { display: grid; grid-template-columns: 350px 1fr; gap: 2rem; }
        .card { background: white; padding: 1.5rem; border-radius: 12px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); }
        .search-box { width: 100%; padding: 0.8rem; margin-bottom: 1rem; border: 1px solid #ddd; border-radius: 6px; }
        input, textarea { width: 100%; padding: 0.6rem; margin-top: 0.3rem; border: 1px solid #ddd; border-radius: 6px; box-sizing: border-box; }
        button { width: 100%; background: var(--forest-green); color: white; border: none; padding: 0.8rem; border-radius: 6px; cursor: pointer; margin-top: 1rem; }
        .btn-cancel { background: #6c757d; }
        .error { color: #d90429; background: #ffe3e3; padding: 1rem; border-radius: 6px; margin-bottom: 1rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 1rem; text-align: left; border-bottom: 1px solid #eee; }
    </style>
    <link rel="stylesheet" href="../assets/css/design-system.css">
</head>
<body>
    <div class="container">
        <?php if ($message): ?><div class="alert" style="background:#d8f3dc; padding:1rem; margin-bottom:1rem;"><?= htmlspecialchars($message) ?></div><?php endif; ?>
        <?php if ($errors): ?><div class="error"><?= implode('<br>', $errors) ?></div><?php endif; ?>

        <div class="grid">
            <section class="card">
                <h3><?= $editPackage ? 'Edit Package' : 'Add Package' ?></h3>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="<?= $editPackage ? 'update' : 'create' ?>">
                    <?php if ($editPackage): ?>
                        <input type="hidden" name="package_id" value="<?= $editPackage['package_id'] ?>">
                    <?php endif; ?>
                    
                    <label>Title</label>
                    <input type="text" name="title" value="<?= htmlspecialchars($editPackage['title'] ?? '') ?>" required>
                    <label>Destination</label>
                    <input type="text" name="destination" value="<?= htmlspecialchars($editPackage['destination'] ?? '') ?>" required>
                    <label>Price ($)</label>
                    <input type="number" step="0.01" name="price" value="<?= htmlspecialchars($editPackage['price'] ?? '') ?>" required>
                    <label>Duration (Days)</label>
                    <input type="number" name="duration_days" value="<?= htmlspecialchars($editPackage['duration_days'] ?? '') ?>" required>
                    <label>Availability</label>
                    <input type="number" name="availability" value="<?= htmlspecialchars($editPackage['availability'] ?? '') ?>" required>
                    <label>Image URL</label>
                    <input type="url" name="image_url" value="<?= htmlspecialchars($editPackage['image_url'] ?? '') ?>">
                    
                    <button type="submit"><?= $editPackage ? 'Update Package' : 'Create Package' ?></button>
                    <?php if ($editPackage): ?>
                        <a href="manage-packages.php" style="display:block; text-align:center; margin-top:10px; color:#666;">Cancel Edit</a>
                    <?php endif; ?>
                </form>
            </section>

            <section class="card">
                <input type="text" id="searchInput" class="search-box" placeholder="Search packages by name or destination..." onkeyup="filterTable()">
                <table id="pkgTable">
                    <thead><tr><th>Title</th><th>Price</th><th>Availability</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach ($packages as $pkg): ?>
                        <tr>
                            <td class="pkg-name"><?= htmlspecialchars($pkg['title']) ?></td>
                            <td>$<?= number_format($pkg['price'], 2) ?></td>
                            <td><?= $pkg['availability'] ?></td>
                            <td>
                                <a href="?edit=<?= $pkg['package_id'] ?>">Edit</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this?');">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="package_id" value="<?= $pkg['package_id'] ?>">
                                    <button type="submit" style="background:none; color:red; width:auto; border:none; cursor:pointer; padding:0 5px;">Delete</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        </div>
    </div>

    <script>
        function filterTable() {
            let input = document.getElementById("searchInput").value.toLowerCase();
            let rows = document.querySelectorAll("#pkgTable tbody tr");
            rows.forEach(row => {
                let text = row.textContent.toLowerCase();
                row.style.display = text.includes(input) ? "" : "none";
            });
        }
    </script>
</body>
</html>