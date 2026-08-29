<?php
// ─────────────────────────────────────────────
// AgriCart — Database Connection
// Works with local XAMPP + Aiven MySQL
// Credentials are read from .env / Render Environment Variables
// ─────────────────────────────────────────────

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/security.php';

// ─────────────────────────────────────────────
// DATABASE SETTINGS
// ─────────────────────────────────────────────

$host   = env('DB_HOST', 'localhost');
$port   = (int) env('DB_PORT', '3306');
$user   = env('DB_USER', 'root');
$pass   = env('DB_PASS', '');
$dbname = env('DB_NAME', 'agricart');

// ─────────────────────────────────────────────
// CREATE MYSQLI CONNECTION
// ─────────────────────────────────────────────

$conn = mysqli_init();

if (!$conn) {
    error_log('[AgriCart] Failed to initialize mysqli.');
    http_response_code(503);
    die('Sorry, AgriCart is temporarily unavailable. Please try again.');
}

// ─────────────────────────────────────────────
// AIVEN MYSQL SSL
// ─────────────────────────────────────────────
//
// Aiven requires an encrypted MySQL connection.
// MYSQLI_CLIENT_SSL tells mysqli to connect using SSL/TLS.
//
// Do NOT put the Aiven password directly in this file.
// ─────────────────────────────────────────────

mysqli_ssl_set(
    $conn,
    null,
    null,
    null,
    null,
    null
);

// ─────────────────────────────────────────────
// CONNECT TO DATABASE
// ─────────────────────────────────────────────

$connected = mysqli_real_connect(
    $conn,
    $host,
    $user,
    $pass,
    $dbname,
    $port,
    null,
    MYSQLI_CLIENT_SSL
);

// ─────────────────────────────────────────────
// CONNECTION ERROR
// ─────────────────────────────────────────────

if (!$connected) {

    error_log(
        '[AgriCart] DB connection failed: ' .
        mysqli_connect_error()
    );

    http_response_code(503);

    die(
        'Sorry, AgriCart is temporarily unavailable. ' .
        'Please try again in a moment.'
    );
}

// ─────────────────────────────────────────────
// UTF-8
// ─────────────────────────────────────────────

mysqli_set_charset($conn, 'utf8mb4');

// ─────────────────────────────────────────────
// PERFORMANCE INDEXES
// ─────────────────────────────────────────────

$performanceFile = __DIR__ . '/performance_indexes_schema.php';

if (file_exists($performanceFile)) {

    require_once $performanceFile;

    if (function_exists('agri_perf_bootstrap_indexes')) {
        agri_perf_bootstrap_indexes($conn);
    }
}

// ─────────────────────────────────────────────
// SHARED 5-STAR RENDERING
// ─────────────────────────────────────────────

if (!function_exists('agri_star_icons')) {

    function agri_star_icons($rating)
    {
        $r = max(
            0,
            min(
                5,
                round((float)$rating * 2) / 2
            )
        );

        $full = (int) floor($r);

        $half = (
            ($r - $full) == 0.5
        ) ? 1 : 0;

        $empty = 5 - $full - $half;

        $html = '';

        if ($full > 0) {
            $html .= str_repeat(
                '<i class="fa-solid fa-star"></i>',
                $full
            );
        }

        if ($half) {
            $html .=
                '<i class="fa-solid fa-star-half-stroke"></i>';
        }

        if ($empty > 0) {
            $html .= str_repeat(
                '<i class="fa-regular fa-star"></i>',
                $empty
            );
        }

        return $html;
    }
}
?>