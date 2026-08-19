<?php
declare(strict_types=1);

/**
 * IndiaYatra — Database Configuration
 *
 * Credentials are loaded from a .env file in the project root.
 * A lightweight line-by-line parser is used — no Composer dependency required.
 * Any required variable that is missing causes a loud, early failure instead of
 * silently connecting with null values.
 *
 * Usage: $pdo = getPDO();
 */

// ── Lightweight .env loader ────────────────────────────────────────────────
(static function (): void {
    $envFile = dirname(__DIR__) . '/.env';

    if (!is_file($envFile)) {
        // .env is missing entirely — fail loudly so a misconfigured deploy is
        // caught immediately rather than producing cryptic downstream errors.
        throw new RuntimeException(
            '.env file not found. Copy .env.example to .env and fill in your credentials.'
        );
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and blank lines
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        [$key, $value] = array_map('trim', explode('=', $line, 2)) + [1 => ''];
        if ($key !== '' && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
})();

/**
 * Read a required environment variable, throwing immediately if it is absent
 * or empty so that a misconfigured deploy fails loudly at boot time.
 */
function _env(string $key): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException(
            "Required environment variable '$key' is not set. "
            . "Check your .env file against .env.example."
        );
    }
    return (string) $value;
}

// ── Singleton PDO factory ──────────────────────────────────────────────────
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = _env('DB_HOST');
    $dbname  = _env('DB_NAME');
    $user    = _env('DB_USER');
    $pass    = _env('DB_PASS');
    $charset = _env('DB_CHARSET');

    $dsn = "mysql:host={$host};dbname={$dbname};charset={$charset}";

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        PDO::MYSQL_ATTR_FOUND_ROWS   => true,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        // Show a user-friendly styled error — never expose raw exception traces.
        http_response_code(503);
        echo '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Service Unavailable — IndiaYatra</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    *{box-sizing:border-box;margin:0;padding:0}
    body{font-family:Inter,sans-serif;background:#fef2f2;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:1rem}
    .card{background:#fff;border:1px solid #fecaca;border-radius:1rem;padding:2.5rem 3rem;max-width:480px;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,.08)}
    .icon{font-size:3rem;margin-bottom:1rem}
    h1{font-size:1.5rem;color:#b91c1c;margin-bottom:.5rem}
    p{color:#6b7280;line-height:1.6;margin-bottom:1.5rem}
    a{display:inline-block;padding:.7rem 1.8rem;background:#f97316;color:#fff;border-radius:.75rem;text-decoration:none;font-weight:600;font-size:.95rem}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🔌</div>
    <h1>Database Unavailable</h1>
    <p>We cannot connect to the database right now. Please check your <code>.env</code> file and ensure MySQL is running.</p>
    <a href="javascript:location.reload()">Try Again</a>
  </div>
</body>
</html>';
        exit(1);
    }

    return $pdo;
}

// ── Backward-compatible global $pdo variable ───────────────────────────────
// Existing files that reference $pdo directly will continue to work.
$pdo = getPDO();