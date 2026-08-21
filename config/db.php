<?php
declare(strict_types=1);

/**
 * IndiaYatra — Database Configuration
 */

(static function (): void {
    $alreadyConfigured = (getenv("DB_HOST") !== false && getenv("DB_HOST") !== "")
        || isset($_ENV["DB_HOST"])
        || isset($_SERVER["DB_HOST"]);

    if ($alreadyConfigured) {
        return;
    }

    $envFile = dirname(__DIR__) . "/.env";
    if (!is_file($envFile)) {
        return;
    }

    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === "" || str_starts_with($line, "#")) {
            continue;
        }
        [$key, $value] = array_map("trim", explode("=", $line, 2)) + [1 => ""];
        if ($key !== "" && !array_key_exists($key, $_ENV)) {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("$key=$value");
        }
    }
})();

function _env(string $key): string
{
    $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === "" || $value === null) {
        throw new RuntimeException("Required environment variable '$key' is not set.");
    }
    return (string) $value;
}

function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo !== null) {
        return $pdo;
    }

    $host    = _env("DB_HOST");
    $dbname  = _env("DB_NAME");
    $user    = _env("DB_USER");
    $pass    = _env("DB_PASS");
    $charset = $_SERVER["DB_CHARSET"] ?? $_ENV["DB_CHARSET"] ?? getenv("DB_CHARSET") ?: "utf8mb4";
    $port    = $_SERVER["DB_PORT"] ?? $_ENV["DB_PORT"] ?? getenv("DB_PORT") ?: "";
    $portSegment = ($port !== false && $port !== "") ? ";port={$port}" : "";

    $dsn = "mysql:host={$host}{$portSegment};dbname={$dbname};charset={$charset}";

    $foundRowsAttr = defined("Pdo\\Mysql::ATTR_FOUND_ROWS")
        ? Pdo\Mysql::ATTR_FOUND_ROWS
        : PDO::MYSQL_ATTR_FOUND_ROWS;

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
        $foundRowsAttr               => true,
    ];

    try {
        $pdo = new PDO($dsn, $user, $pass, $options);
    } catch (PDOException $e) {
        if (!headers_sent()) {
            http_response_code(503);
        }
        echo "<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Database Unavailable — IndiaYatra</title><style>body{font-family:sans-serif;background:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0}.card{background:#fff;border-radius:1rem;padding:2.5rem;max-width:420px;text-align:center;box-shadow:0 10px 15px -3px rgba(0,0,0,0.1)}.btn{display:inline-block;margin-top:1.25rem;background:#15803d;color:#fff;padding:0.6rem 1.25rem;border-radius:0.5rem;text-decoration:none}</style></head><body><div class="card"><svg width="48" height="48" fill="none" stroke="#ef4444" viewBox="0 0 24 24" style="margin:0 auto 1rem"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg><h2 style="margin:0 0 0.5rem;color:#0f172a">Database Unavailable</h2><p style="color:#64748b;font-size:0.95rem;line-height:1.5">Could not establish connection to the remote MySQL server. Verify credentials and cloud network access.</p><a href="/" class="btn">Try Again</a></div></body></html>";
        exit(1);
    }

    return $pdo;
}

$pdo = getPDO();
