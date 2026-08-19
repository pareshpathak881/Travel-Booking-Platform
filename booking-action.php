<?php
declare(strict_types=1);

/**
 * IndiaYatra — High-Concurrency Secure Transaction Controller
 * 
 * Process bookings via strict transactions, row-level locks, loyalty points update,
 * and badge unlock evaluations. Emits JSON only.
 */

header("Content-Type: application/json; charset=UTF-8");

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/security.php';

// 1. Authenticate user
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(["status" => "error", "message" => "Unauthorized. Please log in first."]);
    exit;
}

$userId = (int) $_SESSION['user_id'];

// 2. CSRF token check
verifyCsrfOrForbidden();

// 3. Retrieve and sanitize inputs
$packageId     = (int) ($_POST['package_id'] ?? 0);
$seatsBooked   = (int) ($_POST['seats_booked'] ?? 0);
$selectedSeats = sanitizeInput($_POST['selected_seats'] ?? '');
$roomTier      = sanitizeInput($_POST['room_tier'] ?? '');

if ($packageId <= 0 || $seatsBooked <= 0) {
    http_response_code(400);
    echo json_encode(["status" => "error", "message" => "Invalid inputs: package ID and seats are required."]);
    exit;
}

try {
    $db = getPDO();
    $db->beginTransaction();

    // 4. Force row-level write lock for inventory concurrency safety
    $stmt = $db->prepare("SELECT * FROM packages WHERE package_id = :id FOR UPDATE");
    $stmt->execute(['id' => $packageId]);
    $package = $stmt->fetch();

    if (!$package) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "Target inventory item not found."]);
        exit;
    }

    $availability = (int) $package['availability'];
    if ($seatsBooked > $availability) {
        $db->rollBack();
        http_response_code(409); // Conflict
        echo json_encode([
            "status" => "error", 
            "message" => "Transaction aborted: Seats or rooms sold out out out! Only {$availability} left."
        ]);
        exit;
    }

    // 5. Fetch active user details for discount and badge metrics
    $uStmt = $db->prepare("SELECT * FROM users WHERE user_id = :id FOR UPDATE");
    $uStmt->execute(['id' => $userId]);
    $user = $uStmt->fetch();

    if (!$user) {
        $db->rollBack();
        http_response_code(404);
        echo json_encode(["status" => "error", "message" => "User account not found."]);
        exit;
    }

    // 6. Deduct available inventory
    $updateInventory = $db->prepare("UPDATE packages SET availability = availability - :booked WHERE package_id = :id");
    $updateInventory->execute(['booked' => $seatsBooked, 'id' => $packageId]);

    // 7. Calculate total payable with modifiers and loyalty levels
    $basePrice = (float) $package['base_price'];
    
    // Apply room tier modifier if it is a hotel
    $tierModifier = 0.00;
    if ($package['vertical_type'] === 'hotel') {
        if ($roomTier === 'Deluxe Suite') {
            $tierModifier = 1500.00;
        } elseif ($roomTier === 'Executive Suite') {
            $tierModifier = 4000.00;
        }
    }
    
    $effectiveBasePrice = $basePrice + $tierModifier;
    $rawSubtotal = $effectiveBasePrice * $seatsBooked;

    // Apply loyalty discount percentage based on tier level
    $discountPercent = 0.00;
    $userLevel = (int) $user['user_level'];
    if ($userLevel === 2) {
        $discountPercent = 0.05;
    } elseif ($userLevel === 3) {
        $discountPercent = 0.10;
    }
    
    $discountAmount = round($rawSubtotal * $discountPercent, 2);
    $baseTotal = $rawSubtotal - $discountAmount;
    
    // Indian GST Split: 2.5% CGST, 2.5% SGST (Total 5% GST)
    $cgst     = round($baseTotal * 0.025, 2);
    $sgst     = round($baseTotal * 0.025, 2);
    $totalGst = $cgst + $sgst;
    
    $finalPayable = $baseTotal + $totalGst;

    // 8. Create booking log entry
    $insBooking = $db->prepare("
        INSERT INTO bookings (user_id, package_id, seats_booked, base_total, total_gst, final_payable, status, selected_seats, room_tier) 
        VALUES (:uid, :pid, :seats, :btotal, :tgst, :payable, 'Confirmed', :seats_lst, :tier)
    ");
    $insBooking->execute([
        'uid'       => $userId,
        'pid'       => $packageId,
        'seats'     => $seatsBooked,
        'btotal'    => $baseTotal,
        'tgst'      => $totalGst,
        'payable'   => $finalPayable,
        'seats_lst' => $package['vertical_type'] === 'flight' ? $selectedSeats : null,
        'tier'      => $package['vertical_type'] === 'hotel' ? $roomTier : null
    ]);
    
    $invoiceId = (int) $db->lastInsertId();

    // 9. Increment Loyalty and User Stats
    // 1 point per 100 INR spent
    $ptsEarned = (int) ($finalPayable / 100);
    $newPoints = (int)$user['loyalty_points'] + $ptsEarned;
    $newBookingsCount = (int)$user['booking_count'] + 1;
    $newTotalSpent = (float)$user['total_spent'] + $finalPayable;

    // Recompute level thresholds: Level 1: 0-1000, Level 2: 1001-3000, Level 3: 3001+
    $newLevel = 1;
    if ($newPoints >= 3001) {
        $newLevel = 3;
    } elseif ($newPoints >= 1001) {
        $newLevel = 2;
    }

    // 10. Achievement Badges evaluation
    $currentBadges = $user['badge_flags'] ? explode(',', $user['badge_flags']) : [];
    $unlockedList = [];

    // Badge 1: Boulder Badge (First booking confirmed)
    if ($newBookingsCount >= 1) {
        $unlockedList[] = 'Boulder';
    }

    // Badge 2: Cascade Badge (Booked across 2 different verticals)
    // Query verticals user has booked so far including current transaction
    $vStmt = $db->prepare("
        SELECT DISTINCT p.vertical_type 
        FROM bookings b 
        JOIN packages p ON b.package_id = p.package_id 
        WHERE b.user_id = :uid AND b.status = 'Confirmed'
    ");
    $vStmt->execute(['uid' => $userId]);
    $bookedVerticals = $vStmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (count(array_unique($bookedVerticals)) >= 2) {
        $unlockedList[] = 'Cascade';
    }

    // Badge 3: Volcano Badge (Elite Tier: booking_count >= 5 or total_spent >= 75000)
    if ($newBookingsCount >= 5 || $newTotalSpent >= 75000.00) {
        $unlockedList[] = 'Volcano';
    }

    // Merge unlocked list with current badges without duplicates
    $updatedBadges = array_unique(array_merge($currentBadges, $unlockedList));
    $badgeFlagsStr = implode(',', $updatedBadges);

    // Save user profile state
    $updUser = $db->prepare("
        UPDATE users 
        SET loyalty_points = :pts, user_level = :lvl, booking_count = :bcount, total_spent = :spent, badge_flags = :badges 
        WHERE user_id = :uid
    ");
    $updUser->execute([
        'pts'    => $newPoints,
        'lvl'    => $newLevel,
        'bcount' => $newBookingsCount,
        'spent'  => $newTotalSpent,
        'badges' => $badgeFlagsStr,
        'uid'    => $userId
    ]);

    // Commit Transaction
    $db->commit();

    // Respond with success payload
    echo json_encode([
        "status"      => "success",
        "invoice_id"  => $invoiceId,
        "new_badges"  => $badgeFlagsStr,
        "new_points"  => $newPoints,
        "new_level"   => $newLevel,
        "final_price" => $finalPayable
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error encountered during checkout: " . $e->getMessage()]);
}
