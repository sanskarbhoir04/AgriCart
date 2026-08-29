<?php
// ─── DATABASE CONFIGURATION ───
// Credentials now come from .env (see .env.example) instead of being
// hardcoded here. If .env is missing, this falls back to the same
// local XAMPP defaults as before so existing setups keep working.
require_once __DIR__ . '/env.php';
require_once __DIR__ . '/security.php';

$host   = env('DB_HOST', 'localhost');
$user   = env('DB_USER', 'root');
$pass   = env('DB_PASS', '');
$dbname = env('DB_NAME', 'agricart');

// Create Database Connection (mysqli Object-Oriented method)
$conn = new mysqli($host, $user, $pass, $dbname);

// Check Connection Status (Error Catching)
if ($conn->connect_error) {
    error_log('[AgriCart] DB connection failed: ' . $conn->connect_error);
    http_response_code(503);
    die('Sorry, AgriCart is temporarily unavailable. Please try again in a moment.');
}

// Set Charset to UTF-8 to support pure Marathi/Hindi strings inside the database smoothly
$conn->set_charset("utf8mb4");

// One-time DB performance pass (spec §19) — adds missing indexes on the
// columns filtered/joined most often across the app. After the first
// successful run this costs exactly one lightweight SELECT per request;
// see includes/performance_indexes_schema.php for the safety details.
require_once __DIR__ . '/performance_indexes_schema.php';
agri_perf_bootstrap_indexes($conn);

// Renders a real 5-star row (full / half / empty) for a given rating — shared across every
// page's stats bar so the star rendering logic and markup are identical everywhere.
if (!function_exists('agri_star_icons')) {
    function agri_star_icons($rating) {
        $r = max(0, min(5, round($rating * 2) / 2));
        $full = (int)floor($r);
        $half = (($r - $full) == 0.5) ? 1 : 0;
        $empty = 5 - $full - $half;
        $html = str_repeat('<i class="fa-solid fa-star"></i>', $full);
        if ($half) $html .= '<i class="fa-solid fa-star-half-stroke"></i>';
        $html .= str_repeat('<i class="fa-regular fa-star"></i>', $empty);
        return $html;
    }
}
?>