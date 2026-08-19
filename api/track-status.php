<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=UTF-8');

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/security.php';

$trackerCode = strtoupper(trim($_GET['code'] ?? ''));

if ($trackerCode === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Tracker code is required.']);
    exit;
}

try {
    $db = getPDO();

    $stmt = $db->prepare("
        SELECT schedule_id, transit_type, carrier_name, origin_city, destination_city,
               departure_time, arrival_time, duration_mins, running_days, pnr_tracker_code, fare_price
        FROM schedules
        WHERE pnr_tracker_code = :code
        LIMIT 1
    ");
    $stmt->execute(['code' => $trackerCode]);
    $schedule = $stmt->fetch();

    if (!$schedule) {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'No transit found for the provided code.']);
        exit;
    }

    $now = new DateTimeImmutable('now', new DateTimeZone('Asia/Kolkata'));
    $departure = DateTimeImmutable::createFromFormat('H:i:s', $schedule['departure_time']);
    $arrival = DateTimeImmutable::createFromFormat('H:i:s', $schedule['arrival_time']);

    $progressPercent = 0;
    $currentStatus = 'Scheduled';
    $statusColor = 'blue';
    $gateInfo = 'Gate not assigned yet';
    $baggageInfo = 'Baggage allowance: 15kg check-in + 7kg cabin';
    $currentLocation = $schedule['origin_city'];

    if ($now >= $departure && $now <= $arrival) {
        $progressPercent = 50;
        $currentStatus = '🟢 On Time';
        $statusColor = 'green';
        $gateInfo = $schedule['transit_type'] === 'flight' ? 'Gate A12 - Boarding Open' : 'Platform 4 - Ready to Board';
        $baggageInfo = $schedule['transit_type'] === 'flight' ? 'Baggage Belt 7 (Carousel A)' : 'Baggage will be delivered at destination';
        $currentLocation = 'En route';
    } elseif ($now > $arrival) {
        $progressPercent = 100;
        $currentStatus = '✅ Arrived';
        $statusColor = 'emerald';
        $gateInfo = $schedule['transit_type'] === 'flight' ? 'Arrived at Terminal 2' : 'Arrived at Destination Station';
        $baggageInfo = $schedule['transit_type'] === 'flight' ? 'Baggage Belt 7 - All bags delivered' : 'Collect baggage from Platform 1';
        $currentLocation = $schedule['destination_city'];
    } else {
        $progressPercent = 0;
        $currentStatus = '⏳ Not Departed';
        $statusColor = 'slate';
        $gateInfo = $schedule['transit_type'] === 'flight' ? 'Check-in opens 2hrs before departure' : 'Platform 3 - Departs in ' . $departure->diff($now)->format('%hh %im');
        $baggageInfo = $schedule['transit_type'] === 'flight' ? 'Web check-in available 48hrs before' : 'No baggage restrictions for sleeper buses';
        $currentLocation = $schedule['origin_city'];
    }

    $randomDelay = random_int(0, 4);
    if ($randomDelay === 1 && $progressPercent > 0 && $progressPercent < 100) {
        $currentStatus = '🟡 Delayed 15m';
        $statusColor = 'yellow';
    }

    echo json_encode([
        'status' => 'success',
        'tracker_code' => $trackerCode,
        'schedule' => [
            'transit_type' => $schedule['transit_type'],
            'carrier_name' => $schedule['carrier_name'],
            'origin_city' => $schedule['origin_city'],
            'destination_city' => $schedule['destination_city'],
            'departure_time' => $schedule['departure_time'],
            'arrival_time' => $schedule['arrival_time'],
            'duration_mins' => (int)$schedule['duration_mins'],
            'running_days' => $schedule['running_days'],
            'fare_price' => (float)$schedule['fare_price'],
        ],
        'live_status' => [
            'current_status' => $currentStatus,
            'status_color' => $statusColor,
            'progress_percent' => $progressPercent,
            'current_location' => $currentLocation,
            'gate_info' => $gateInfo,
            'baggage_info' => $baggageInfo,
            'last_updated' => $now->format('Y-m-d H:i:s'),
        ]
    ]);

} catch (PDOException $e) {
    error_log('[IndiaYatra][track-status] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Database error while tracking status.']);
}
