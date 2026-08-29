<?php
// =====================================================================
// includes/security.php — shared CSRF protection + secure session
// configuration for the whole AgriCart site (customer, seller, admin).
//
// Include this BEFORE session_start() is called anywhere, ideally as
// the very first include in db.php / admin_guard.php / header.php.
// =====================================================================

require_once __DIR__ . '/env.php';

/**
 * Configure PHP's session cookie params + strict mode. Must run BEFORE
 * session_start(). Safe to call multiple times (checks session_status).
 */
if (!function_exists('agri_configure_session')) {
    function agri_configure_session(): void {
        if (session_status() !== PHP_SESSION_NONE) return;

        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') == 443)
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.cookie_httponly', '1');
        ini_set('session.use_only_cookies', '1');

        $params = [
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isHttps,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        session_set_cookie_params($params);

        session_name('agricart_sid');
    }
}

/**
 * Start the session with secure settings applied, and enforce an
 * inactivity timeout. Call this instead of a bare session_start().
 */
if (!function_exists('agri_session_start')) {
    function agri_session_start(int $inactivityTimeoutSeconds = 1800): void {
        if (session_status() === PHP_SESSION_NONE) {
            agri_configure_session();
            session_start();
        }

        // Inactivity timeout — applies to any session (customer/seller/admin).
        if (!empty($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $inactivityTimeoutSeconds) {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $p = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
            }
            session_destroy();
            session_start();
        }
        $_SESSION['LAST_ACTIVITY'] = time();
    }
}

/** Call right after a successful login / registration / privilege change. */
if (!function_exists('agri_session_regenerate')) {
    function agri_session_regenerate(): void {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }
}

/** Fully destroy the session (use for logout). */
if (!function_exists('agri_session_destroy')) {
    function agri_session_destroy(): void {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// CSRF protection
// ─────────────────────────────────────────────────────────────────────

/** Get (or lazily create) the CSRF token for the current session. */
if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            agri_session_start();
        }
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

/** Echo a ready-to-use hidden <input> for HTML forms. */
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
    }
}

/**
 * Verify a submitted token (form field OR X-CSRF-Token header, for AJAX).
 * Returns bool — caller decides how to respond (JSON vs redirect).
 */
if (!function_exists('csrf_verify')) {
    function csrf_verify(?string $submitted = null): bool {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            agri_session_start();
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($submitted === null) {
            $submitted = $_POST['csrf_token']
                ?? $_SERVER['HTTP_X_CSRF_TOKEN']
                ?? '';
        }
        if ($expected === '' || $submitted === '') return false;
        return hash_equals($expected, $submitted);
    }
}

/**
 * Verify CSRF or stop the request immediately with a proper error
 * response. Use at the top of every state-changing endpoint, right
 * after the admin/auth check.
 *
 * @param string $mode 'json' for AJAX/API endpoints, 'html' for normal form posts.
 */
if (!function_exists('csrf_require')) {
    function csrf_require(string $mode = 'json'): void {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') return; // Only state-changing requests need CSRF.

        if (!csrf_verify()) {
            if ($mode === 'json') {
                if (!headers_sent()) {
                    http_response_code(403);
                    header('Content-Type: application/json');
                }
                echo json_encode(['success' => false, 'error' => 'Invalid or expired security token. Please refresh the page and try again.']);
            } else {
                if (!headers_sent()) http_response_code(403);
                echo '<div style="font-family:sans-serif;padding:40px;text-align:center;color:#9B3B37;">'
                   . 'Invalid or expired security token. Please go back, refresh the page, and try again.</div>';
            }
            exit;
        }
    }
}
