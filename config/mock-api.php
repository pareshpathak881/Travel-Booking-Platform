<?php
declare(strict_types=1);

/**
 * IndiaYatra — Third-Party Mock API Aggregator Engine (Amadeus/Sabre simulator)
 * 
 * Verifies developer API keys and provides live price drift and availability countdowns.
 */

class MockTravelApiEngine
{
    private const SECURE_KEY = 'IY_PROD_SECURE_KEY_2026';

    /**
     * Validate the given developer API key.
     */
    public static function validateKey(?string $key): bool
    {
        return $key === self::SECURE_KEY;
    }

    /**
     * Dynamically drift pricing and availability based on timestamps.
     * Price drifts by +/- 5% based on the current hour of the day.
     * Availability steps down dynamically depending on the current minute to simulate high concurrency.
     */
    public static function getLiveAdjustments(int $packageId, float $basePrice, int $baseAvailability): array
    {
        // Price drift formula: sin-based fluctuation based on hour of the day and package ID
        $currentHour = (int) date('G'); // 0-23
        $driftPercent = sin(($currentHour + $packageId) * 0.5) * 0.05; // drifts smoothly between -5% and +5%
        $livePrice = round($basePrice * (1.0 + $driftPercent), 2);

        // Availability simulation: steps down dynamically based on the current minute
        $currentMinute = (int) date('i'); // 0-59
        $stepDown = ($currentMinute + $packageId) % 4; // deduct 0, 1, 2, or 3 seats
        $liveAvailability = max(0, $baseAvailability - $stepDown);

        return [
            'live_price'        => $livePrice,
            'live_availability' => $liveAvailability,
            'drift_percent'     => round($driftPercent * 100, 2)
        ];
    }
}

// Allow direct HTTP requests to verify the endpoint
if (basename($_SERVER['SCRIPT_FILENAME']) === 'mock-api.php') {
    header("Content-Type: application/json; charset=UTF-8");

    // Fetch key from various parameters or HTTP Headers
    $apiKey = $_GET['api_key'] ?? $_POST['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;

    if (!MockTravelApiEngine::validateKey($apiKey)) {
        http_response_code(401);
        echo json_encode(["error" => "Unauthorized API Key Validation Failed"]);
        exit;
    }

    $packageId        = (int) ($_GET['package_id'] ?? 1);
    $basePrice        = (float) ($_GET['base_price'] ?? 1000.00);
    $baseAvailability = (int) ($_GET['base_availability'] ?? 50);

    $adjustments = MockTravelApiEngine::getLiveAdjustments($packageId, $basePrice, $baseAvailability);
    
    echo json_encode(array_merge([
        "status"            => "success",
        "package_id"        => $packageId,
        "base_price"        => $basePrice,
        "base_availability" => $baseAvailability
    ], $adjustments));
    exit;
}
