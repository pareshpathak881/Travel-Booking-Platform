<?php
declare(strict_types=1);

/**
 * IndiaYatra — Toggle Wishlist API Endpoint
 * 
 * POST parameters:
 *   package_id  int
 *   csrf_token  string
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

// Ensure user is logged in
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in first."]);
    exit;
}

// Verify CSRF
verifyCsrfOrForbidden();

$userId    = (int) $_SESSION['user_id'];
$packageId = (int) ($_POST['package_id'] ?? 0);

if ($packageId <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid package ID."]);
    exit;
}

try {
    $db = getPDO();
    
    // Check if already wishlisted
    $stmt = $db->prepare("SELECT wishlist_id FROM wishlists WHERE user_id = :uid AND package_id = :pid");
    $stmt->execute(['uid' => $userId, 'pid' => $packageId]);
    $wish = $stmt->fetch();
    
    if ($wish) {
        // Remove from wishlist
        $dStmt = $db->prepare("DELETE FROM wishlists WHERE wishlist_id = :wid");
        $dStmt->execute(['wid' => $wish['wishlist_id']]);
        $action = 'removed';
    } else {
        // Add to wishlist
        $iStmt = $db->prepare("INSERT INTO wishlists (user_id, package_id) VALUES (:uid, :pid)");
        $iStmt->execute(['uid' => $userId, 'pid' => $packageId]);
        $action = 'added';
    }
    
    // Get updated wishlist for user to return
    $wStmt = $db->prepare("SELECT package_id FROM wishlists WHERE user_id = :uid");
    $wStmt->execute(['uid' => $userId]);
    $updatedWishlist = $wStmt->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        "status"    => "success",
        "action"    => $action,
        "wishlist"  => $updatedWishlist
    ]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
