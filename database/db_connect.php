<?php
/**
 * CivicPulse — Universal Database Connector
 * Supports:
 *  - Cloud DATABASE_URL / MYSQL_URL (Railway, Render, Heroku, Fly.io)
 *  - Railway native vars (MYSQLHOST, MYSQLUSER, MYSQLPASSWORD, MYSQLDATABASE, MYSQLPORT)
 *  - Standard vars (DB_HOST, DB_USER, DB_PASS, DB_NAME, DB_PORT)
 *  - .env file in project root
 */

if (!function_exists('civicpulse_load_env')) {
    function civicpulse_load_env($env_path) {
        if (file_exists($env_path)) {
            $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value, " \t\n\r\0\x0B\"'");
                    if (getenv($key) === false && !array_key_exists($key, $_ENV)) {
                        putenv("$key=$value");
                        $_ENV[$key] = $value;
                        $_SERVER[$key] = $value;
                    }
                }
            }
        }
    }
}

// Load .env if present
$root_env = dirname(__DIR__) . '/.env';
civicpulse_load_env($root_env);

function civicpulse_get_db_params() {
    $db_url = getenv('DATABASE_URL') ?: (getenv('MYSQL_URL') ?: (getenv('CLEARDB_DATABASE_URL') ?: ''));

    if (!empty($db_url)) {
        $parsed = parse_url($db_url);
        if ($parsed !== false && isset($parsed['host'])) {
            return [
                'host' => $parsed['host'],
                'user' => $parsed['user'] ?? 'root',
                'pass' => $parsed['pass'] ?? '',
                'name' => isset($parsed['path']) ? ltrim($parsed['path'], '/') : 'otp_verification',
                'port' => isset($parsed['port']) ? (int)$parsed['port'] : 3306,
            ];
        }
    }

    $host = getenv('MYSQLHOST') ?: (getenv('DB_HOST') ?: 'localhost');
    $user = getenv('MYSQLUSER') ?: (getenv('DB_USER') ?: 'root');
    $pass = getenv('MYSQLPASSWORD') ?: (getenv('DB_PASS') ?: (getenv('DB_PASSWORD') ?: ''));
    $name = getenv('MYSQLDATABASE') ?: (getenv('DB_NAME') ?: 'otp_verification');
    $port = (int)(getenv('MYSQLPORT') ?: (getenv('DB_PORT') ?: 3306));

    return [
        'host' => $host,
        'user' => $user,
        'pass' => $pass,
        'name' => $name,
        'port' => $port,
    ];
}

$db_params = civicpulse_get_db_params();
$db_host = $db_params['host'];
$db_user = $db_params['user'];
$db_pass = $db_params['pass'];
$db_name = $db_params['name'];
$db_port = $db_params['port'];

$conn = mysqli_init();

if (!$conn) {
    http_response_code(500);
    die("mysqli_init failed");
}

// Enable SSL support for remote cloud databases (e.g. TiDB Cloud, Aiven, PlanetScale, AWS RDS)
$is_remote = ($db_host !== 'localhost' && $db_host !== '127.0.0.1' && $db_host !== 'db');
$connected = false;

if ($is_remote) {
    // Check for standard CA certificates path on Debian/Ubuntu/Docker
    $ca_cert = getenv('MYSQL_SSL_CA') ?: (file_exists('/etc/ssl/certs/ca-certificates.crt') ? '/etc/ssl/certs/ca-certificates.crt' : NULL);
    if ($ca_cert) {
        mysqli_ssl_set($conn, NULL, NULL, $ca_cert, NULL, NULL);
    }
    mysqli_options($conn, MYSQLI_OPT_SSL_VERIFY_SERVER_CERT, false);
    $connected = @mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port, NULL, MYSQLI_CLIENT_SSL);
}

// Fallback to standard connection if remote without SSL flag or local
if (!$connected) {
    $connected = @mysqli_real_connect($conn, $db_host, $db_user, $db_pass, $db_name, $db_port);
}

if (!$connected) {
    http_response_code(500);
    $error_msg = "Database connection failed: " . mysqli_connect_error();
    error_log($error_msg);
    die($error_msg . " (Host: $db_host, Port: $db_port, DB: $db_name)");
}

mysqli_set_charset($conn, 'utf8mb4');
