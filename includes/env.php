<?php
// =====================================================================
// includes/env.php — minimal .env loader (no Composer dependency).
//
// Loads AgriCart/.env once per request and exposes values via env().
// Real secrets live only in .env (which must NEVER be committed / sent
// to the browser). .env.example ships with placeholders only so the
// project still runs after a fresh clone.
//
// Usage:
//   require_once __DIR__ . '/env.php';
//   $key = env('GROQ_API_KEY', '');
// =====================================================================

if (!function_exists('agri_load_env')) {
    function agri_load_env(string $path): void {
        static $loaded = false;
        if ($loaded) return;
        $loaded = true;

        if (!is_file($path) || !is_readable($path)) {
            return; // Fall back silently to real environment variables / getenv().
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') continue;
            if (strpos($line, '=') === false) continue;

            [$name, $value] = explode('=', $line, 2);
            $name  = trim($name);
            $value = trim($value);

            // Strip matching surrounding quotes.
            if (strlen($value) >= 2) {
                $first = $value[0];
                $last  = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            if ($name === '') continue;

            // Don't overwrite variables already set at the OS/server level.
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
            }
            if (!isset($_ENV[$name]))    $_ENV[$name] = $value;
            if (!isset($_SERVER[$name])) $_SERVER[$name] = $value;
        }
    }
}

if (!function_exists('env')) {
    /**
     * Read a config value: real environment variable first, then .env,
     * then the provided default. Never echoes/logs the value itself.
     */
    function env(string $key, $default = null) {
        $value = getenv($key);
        if ($value === false) $value = $_ENV[$key] ?? false;
        if ($value === false) return $default;
        // Support a few common literal tokens.
        $lower = strtolower($value);
        if ($lower === 'true')  return true;
        if ($lower === 'false') return false;
        if ($lower === 'null' || $value === '') return $default;
        return $value;
    }
}

agri_load_env(__DIR__ . '/../.env');

if (!function_exists('agri_configure_error_handling')) {
    /**
     * Central error-display policy, applied once per request as soon as
     * env.php loads (db.php/security.php/header.php all require this early).
     * APP_ENV=local (the default) keeps errors on screen for development,
     * exactly like before. Anything else (staging/production) hides raw
     * PHP/SQL errors from visitors while still logging them server-side —
     * closing the "users must never see stack traces" gap without changing
     * behaviour for the local setup this project ships with.
     */
    function agri_configure_error_handling(): void {
        static $done = false;
        if ($done) return;
        $done = true;

        $isLocal = strtolower((string) env('APP_ENV', 'local')) === 'local';

        error_reporting(E_ALL);
        ini_set('log_errors', '1');
        ini_set('display_errors', $isLocal ? '1' : '0');
        ini_set('display_startup_errors', $isLocal ? '1' : '0');
    }
}
agri_configure_error_handling();
