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
        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Database Unavailable</title></head><body><h2>Database Unavailable</h2></body></html>';
        exit(1);
    }

    return $pdo;
}

$pdo = getPDO();
