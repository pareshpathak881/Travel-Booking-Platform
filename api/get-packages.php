<?php
declare(strict_types=1);

/**
 * IndiaYatra — Get Packages API
 * 
 * Fetches filtered packages with live price drift and availability simulations.
 */

header("Content-Type: application/json; charset=UTF-8");

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../config/mock-api.php';

$vertical  = sanitizeInput($_GET['vertical_type'] ?? 'package');
$search    = sanitizeInput($_GET['search'] ?? '');
$maxPrice  = isset($_GET['max_price']) ? (float)$_GET['max_price'] : 1000000.00;
$zones     = isset($_GET['zones']) ? explode(',', $_GET['zones']) : [];
$grades    = isset($_GET['grades']) ? explode(',', $_GET['grades']) : [];
$airlines  = isset($_GET['airlines']) ? explode(',', $_GET['airlines']) : [];
$roomTiers = isset($_GET['room_tiers']) ? explode(',', $_GET['room_tiers']) : [];

// Strip empty strings from arrays
$zones     = array_filter($zones, fn($v) => $v !== '');
$grades    = array_filter($grades, fn($v) => $v !== '');
$airlines  = array_filter($airlines, fn($v) => $v !== '');
$roomTiers = array_filter($roomTiers, fn($v) => $v !== '');

$sql = "SELECT * FROM packages WHERE vertical_type = :vertical";
$params = [':vertical' => $vertical];

if ($search !== '') {
    $sql .= " AND (title LIKE :search OR airline_name LIKE :search OR state LIKE :search OR description LIKE :search)";
    $params[':search'] = "%{$search}%";
}

if ($maxPrice > 0) {
    $sql .= " AND base_price <= :max_price";
    $params[':max_price'] = $maxPrice;
}

if (!empty($zones)) {
    $inZones = [];
    foreach (array_values($zones) as $idx => $z) {
        $key = ":zone_" . $idx;
        $inZones[] = $key;
        $params[$key] = $z;
    }
    $sql .= " AND zone IN (" . implode(',', $inZones) . ")";
}

if (!empty($grades)) {
    $inGrades = [];
    foreach (array_values($grades) as $idx => $g) {
        $key = ":grade_" . $idx;
        $inGrades[] = $key;
        $params[$key] = $g;
    }
    $sql .= " AND letter_grade IN (" . implode(',', $inGrades) . ")";
}

if ($vertical === 'flight' && !empty($airlines)) {
    $inAirlines = [];
    foreach (array_values($airlines) as $idx => $a) {
        $key = ":airline_" . $idx;
        $inAirlines[] = $key;
        $params[$key] = $a;
    }
    $sql .= " AND airline_name IN (" . implode(',', $inAirlines) . ")";
}

if ($vertical === 'hotel' && !empty($roomTiers)) {
    $inTiers = [];
    foreach (array_values($roomTiers) as $idx => $t) {
        $key = ":tier_" . $idx;
        $inTiers[] = $key;
        $params[$key] = $t;
    }
    $sql .= " AND room_tier IN (" . implode(',', $inTiers) . ")";
}

$sql .= " ORDER BY base_price ASC";

try {
    $db = getPDO();
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $packages = $stmt->fetchAll();

    // Hydrate each package with mock live data (price fluctuation + live availability step-down)
    foreach ($packages as &$pkg) {
        $adjustments = MockTravelApiEngine::getLiveAdjustments(
            (int)$pkg['package_id'], 
            (float)$pkg['base_price'], 
            (int)$pkg['availability']
        );
        $pkg['live_price']        = $adjustments['live_price'];
        $pkg['live_availability'] = $adjustments['live_availability'];
        $pkg['drift_percent']     = $adjustments['drift_percent'];
    }

    echo json_encode(["status" => "success", "packages" => $packages]);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
}
