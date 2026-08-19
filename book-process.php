<?php
declare(strict_types=1);

session_start();

header('Content-Type: application/json');

require_once 'config/db.php';

/*
|--------------------------------------------------------------------------
| Configuration
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Kolkata');

/*
|--------------------------------------------------------------------------
| Helper Functions
|--------------------------------------------------------------------------
*/

function jsonResponse(
    string $status,
    string $message,
    array $extra = [],
    int $code = 200
): void {

    http_response_code($code);

    echo json_encode(array_merge([
        'status' => $status,
        'message' => $message
    ], $extra));

    exit;
}

function isJsonRequest(): bool
{
    return (
        str_contains($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') ||
        str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
    );
}

function redirectBack(string $url): void
{
    header("Location: {$url}");
    exit;
}

function bookingReference(): string
{
    return
        'CV-' .
        date('Y') .
        '-' .
        strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
}

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['user_id'])) {

    if (isJsonRequest()) {

        jsonResponse(
            'error',
            'Please login first.',
            [],
            401
        );

    }

    redirectBack('login.php');

}

$user_id = (int) $_SESSION['user_id'];

/*
|--------------------------------------------------------------------------
| Read Input
|--------------------------------------------------------------------------
*/

$jsonInput = json_decode(
    file_get_contents("php://input"),
    true
);

$data = is_array($jsonInput)
    ? $jsonInput
    : $_POST;

$package_id = (int) ($data['package_id'] ?? 0);

$travel_date = trim(
    $data['travel_date'] ?? ''
);

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

if (!$package_id || !$travel_date) {

    jsonResponse(
        'error',
        'Package and travel date are required.',
        [],
        400
    );

}

$date = DateTime::createFromFormat(
    'Y-m-d',
    $travel_date
);

if (!$date) {

    jsonResponse(
        'error',
        'Invalid travel date.',
        [],
        400
    );

}

$today = new DateTime('today');

if ($date < $today) {

    jsonResponse(
        'error',
        'Travel date cannot be in the past.',
        [],
        400
    );

}

/*
|--------------------------------------------------------------------------
| Booking Transaction
|--------------------------------------------------------------------------
*/

try {

    $pdo->beginTransaction();

    /*
    |---------------------------------------------------------
    | Lock package row
    |---------------------------------------------------------
    */

    $packageQuery = $pdo->prepare("
        SELECT
            package_id,
            title,
            price,
            availability
        FROM packages
        WHERE package_id = ?
        FOR UPDATE
    ");

    $packageQuery->execute([$package_id]);

    $package = $packageQuery->fetch(PDO::FETCH_ASSOC);

    if (!$package) {

        $pdo->rollBack();

        jsonResponse(
            'error',
            'Package not found.',
            [],
            404
        );

    }

    if ((int)$package['availability'] <= 0) {

        $pdo->rollBack();

        jsonResponse(
            'error',
            'This package is sold out.'
        );

    }

    /*
    |---------------------------------------------------------
    | Duplicate Booking Check
    |---------------------------------------------------------
    */

    $duplicate = $pdo->prepare("
        SELECT booking_id
        FROM bookings
        WHERE
            user_id = ?
        AND
            package_id = ?
        AND
            travel_date = ?
        LIMIT 1
    ");

    $duplicate->execute([
        $user_id,
        $package_id,
        $travel_date
    ]);

    if ($duplicate->fetch()) {

        $pdo->rollBack();

        jsonResponse(
            'error',
            'You already booked this package for that date.'
        );

    }

    /*
    |---------------------------------------------------------
    | Booking Reference
    |---------------------------------------------------------
    */

    $reference = bookingReference();

    /*
    |---------------------------------------------------------
    | Insert Booking
    |---------------------------------------------------------
    */

    $insert = $pdo->prepare("
        INSERT INTO bookings
        (
            user_id,
            package_id,
            travel_date,
            booking_reference,
            booking_status,
            created_at
        )
        VALUES
        (
            ?,
            ?,
            ?,
            ?,
            'Confirmed',
            NOW()
        )
    ");

    $insert->execute([

        $user_id,

        $package_id,

        $travel_date,

        $reference

    ]);

    $bookingId = $pdo->lastInsertId();

    /*
    |---------------------------------------------------------
    | Update Availability
    |---------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE packages
        SET availability = availability - 1
        WHERE package_id = ?
    ");

    $update->execute([
        $package_id
    ]);

    /*
    |---------------------------------------------------------
    | Remaining code continues in Part 2...
    |---------------------------------------------------------
    */
        /*
    |--------------------------------------------------------------------------
    | Optional Activity Log
    |--------------------------------------------------------------------------
    */

    try {

        $log = $pdo->prepare("
            INSERT INTO booking_logs
            (
                user_id,
                booking_id,
                action,
                ip_address,
                user_agent,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ");

        $log->execute([

            $user_id,

            $bookingId,

            'Booking Created',

            $_SERVER['REMOTE_ADDR'] ?? 'Unknown',

            $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'

        ]);

    } catch (Exception $ignore) {

        // Ignore logging errors

    }

    /*
    |--------------------------------------------------------------------------
    | Remaining Availability
    |--------------------------------------------------------------------------
    */

    $remaining = max(
        0,
        (int)$package['availability'] - 1
    );

    /*
    |--------------------------------------------------------------------------
    | Commit Transaction
    |--------------------------------------------------------------------------
    */

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Success Response
    |--------------------------------------------------------------------------
    */

    if (isJsonRequest()) {

        jsonResponse(

            'success',

            'Booking confirmed successfully.',

            [

                'booking_id' => $bookingId,

                'booking_reference' => $reference,

                'package_id' => $package_id,

                'package_title' => $package['title'],

                'travel_date' => $travel_date,

                'price' => $package['price'],

                'remaining_slots' => $remaining,

                'booking_status' => 'Confirmed'

            ]

        );

    }

    redirectBack(

        "my-bookings.php?booking=" .

        urlencode($reference)

    );

}

/*
|--------------------------------------------------------------------------
| Error Handling
|--------------------------------------------------------------------------
*/

catch (Throwable $e) {

    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }

    error_log(

        "[BOOKING ERROR] " .

        $e->getMessage()

    );

    if (isJsonRequest()) {

        jsonResponse(

            'error',

            'Something went wrong while processing your booking.',

            [],

            500

        );

    }

    redirectBack(

        "package-detail.php?id=" .

        urlencode((string)$package_id) .

        "&error=booking"

    );

}