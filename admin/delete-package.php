<?php
declare(strict_types=1);
/**
 * IndiaYatra — Admin Delete Package (Worker)
 *
 * Pure PHP — zero HTML output.
 * Requires: admin role + confirm=yes GET param.
 */

// ── Bootstrap ──────────────────────────────────────────────────────────────
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params(['lifetime'=>0,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);
    session_start();
}
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/db.php';

// ── 1. Auth gate ───────────────────────────────────────────────────────────
checkRole('admin');

// ── 2. Validate package ID ────────────────────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: dashboard.php');
    exit;
}

// ── 3. Require double-confirmation param ──────────────────────────────────
if (($_GET['confirm'] ?? '') !== 'yes') {
    // Redirect back to dashboard without performing any action
    header('Location: dashboard.php');
    exit;
}

// ── 4. Perform deletion ───────────────────────────────────────────────────
try {
    $stmt = $pdo->prepare('DELETE FROM packages WHERE package_id = :id');
    $stmt->execute([':id' => $id]);

    if ($stmt->rowCount() === 0) {
        // Package didn't exist — still redirect gracefully
        header('Location: dashboard.php?notice=Package+not+found.');
        exit;
    }

    header('Location: dashboard.php?deleted=1');
    exit;

} catch (PDOException $e) {
    error_log('[IndiaYatra][delete-package] ' . $e->getMessage());
    header('Location: dashboard.php?error=Delete+failed.+Please+try+again.');
    exit;
}
