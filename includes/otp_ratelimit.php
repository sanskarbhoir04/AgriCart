<?php
// =====================================================================
// includes/otp_ratelimit.php — server-side OTP send rate limiting.
//
// This does NOT depend on JavaScript countdowns in any way — every
// check here re-verifies against the database on each request, so it
// still applies even if a person bypasses the browser entirely (e.g.
// scripting requests directly).
//
// Requires $conn (mysqli) from includes/db.php and the
// otp_rate_limits table (see setup/otp_email_verification_upgrade.sql).
// =====================================================================

define('AGRI_OTP_MAX_PER_EMAIL_WINDOW', 5);   // max OTP sends per email per window
define('AGRI_OTP_MAX_PER_IP_WINDOW', 10);      // max OTP sends per IP per window
define('AGRI_OTP_RATE_WINDOW_SECONDS', 1800);  // 30 minutes

if (!function_exists('agri_client_ip')) {
    function agri_client_ip(): string {
        // Only trust REMOTE_ADDR by default (X-Forwarded-For is easily
        // spoofed unless you explicitly trust a known reverse proxy).
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return substr($ip, 0, 45);
    }
}

if (!function_exists('agri_otp_rate_check')) {
    /**
     * Returns ['ok' => true] if another OTP send is allowed right now,
     * or ['ok' => false, 'reason' => 'email'|'ip'] if a limit was hit.
     * Does NOT record the attempt — call agri_otp_rate_record() only
     * after the email actually sends successfully.
     */
    function agri_otp_rate_check(mysqli $conn, string $email): array {
        $ip = agri_client_ip();

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM otp_rate_limits WHERE email = ? AND sent_at > (NOW() - INTERVAL ? SECOND)"
        );
        $window = AGRI_OTP_RATE_WINDOW_SECONDS;
        $stmt->bind_param("si", $email, $window);
        $stmt->execute();
        $emailCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        if ($emailCount >= AGRI_OTP_MAX_PER_EMAIL_WINDOW) {
            return ['ok' => false, 'reason' => 'email'];
        }

        $stmt = $conn->prepare(
            "SELECT COUNT(*) AS c FROM otp_rate_limits WHERE ip_address = ? AND sent_at > (NOW() - INTERVAL ? SECOND)"
        );
        $stmt->bind_param("si", $ip, $window);
        $stmt->execute();
        $ipCount = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
        if ($ipCount >= AGRI_OTP_MAX_PER_IP_WINDOW) {
            return ['ok' => false, 'reason' => 'ip'];
        }

        return ['ok' => true];
    }
}

if (!function_exists('agri_otp_rate_record')) {
    /** Record a successful OTP send for rate-limiting purposes. */
    function agri_otp_rate_record(mysqli $conn, string $email): void {
        $ip = agri_client_ip();
        $stmt = $conn->prepare("INSERT INTO otp_rate_limits (email, ip_address) VALUES (?, ?)");
        $stmt->bind_param("ss", $email, $ip);
        $stmt->execute();
    }
}
