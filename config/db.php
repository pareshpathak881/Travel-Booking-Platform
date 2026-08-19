<?php
declare(strict_types=1);

/**
 * IndiaYatra — Database Configuration
 *
 * Credential resolution order:
 *   1. Process environment (Vercel dashboard / server env vars)
 *   2. Local .env file in the project root (development)
 *
 * Usage: $pdo = getPDO();
 */

// ── Lightweight .env loader (skipped when env vars are already injected) ──
(static function (): void {
    $alreadyConfigured = getenv('DB_HOST') !== false && getenv('DB_HOST') !== '';
    if ($alreadyConfigured) {
        return;
    }

    $envFile = dirname(__DIR__) . '/.env';
    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
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
 * Read a required environment variable from $_ENV or getenv().
 */
function _env(string $key): string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === '') {
        throw new RuntimeException(
            "Required environment variable '$key' is not set. "
            . "For local dev, copy .env.example to .env. "
            . "For Vercel, add it under Project Settings → Environment Variables."
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
    $charset = $_ENV['DB_CHARSET'] ?? getenv('DB_CHARSET') ?: 'utf8mb4';

    $port = $_ENV['DB_PORT'] ?? getenv('DB_PORT') ?: '';
    $portSegment = ($port !== false && $port !== '') ? ";port={$port}" : '';

    $dsn = "mysql:host={$host}{$portSegment};dbname={$dbname};charset={$charset}";

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
    a{display:inline-block;padding:.7rem 1.8rem;background:#2d6a4f;color:#fff;border-radius:.75rem;text-decoration:none;font-weight:600;font-size:.95rem}
  </style>
</head>
<body>
  <div class="card">
    <div class="icon">🔌</div>
    <h1>Database Unavailable</h1>
    <p>We cannot connect to the database right now. On Vercel, verify your cloud DB credentials in Environment Variables. Locally, check your <code>.env</code> file and ensure MySQL is running.</p>
    <a href="javascript:location.reload()">Try Again</a>
  </div>
</body>
</html>';
        exit(1);
    }

    return $pdo;
}

// ── Backward-compatible global $pdo variable ───────────────────────────────
$pdo = getPDO();
