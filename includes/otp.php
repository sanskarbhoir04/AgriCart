<?php
// =====================================================================
// includes/otp.php — modular OTP helpers shared by the registration
// flow (and reusable later for "Forgot password" OTP, etc).
//
// The OTP is delivered by EMAIL ONLY (agri_otp_deliver() below calls
// agri_send_otp_email() from otp_mailer.php) — no paid SMS API is used
// anywhere in this flow. The mobile number is still collected and
// validated, but it is never marked as OTP-verified.
// =====================================================================

require_once __DIR__ . '/otp_mailer.php';

define('AGRI_OTP_EXPIRY_SECONDS', 300);   // 5 minutes
define('AGRI_OTP_MAX_ATTEMPTS', 5);       // wrong-entry attempts before forcing a fresh OTP
define('AGRI_OTP_RESEND_COOLDOWN', 30);   // seconds between resend requests
define('AGRI_OTP_MAX_RESENDS', 3);        // resends allowed per registration attempt

if (!function_exists('agri_otp_generate')) {
    function agri_otp_generate(): string {
        return (string) random_int(100000, 999999);
    }
}

if (!function_exists('agri_otp_hash')) {
    function agri_otp_hash(string $otp): string {
        return hash('sha256', $otp);
    }
}

if (!function_exists('agri_otp_deliver')) {
    /**
     * Deliver the OTP to the user's EMAIL address (never SMS — AgriCart
     * uses no paid SMS API). $mobile is accepted for signature/context
     * only and is never used for delivery. Never echoes or returns the
     * OTP to the browser; agri_send_otp_email() handles that contract.
     *
     * Returns ['ok' => bool, 'error' => string|null].
     */
    function agri_otp_deliver(string $mobile, string $email, string $otp, string $fullName = '', string $lang = 'en'): array {
        return agri_send_otp_email($email, $fullName, $otp, $lang);
    }
}

/**
 * Start (or restart) an OTP challenge for the given mobile/email and
 * store its state in the session. Returns ['ok' => bool, 'error' => ?string].
 * Delivery always targets the EMAIL address.
 */
if (!function_exists('agri_otp_start')) {
    function agri_otp_start(string $mobile, string $email, string $fullName = '', string $lang = 'en'): array {
        $otp = agri_otp_generate();
        $result = agri_otp_deliver($mobile, $email, $otp, $fullName, $lang);
        if (!$result['ok']) {
            return $result;
        }
        $_SESSION['reg_otp_hash']    = agri_otp_hash($otp);
        $_SESSION['reg_otp_email']   = $email; // OTP session is bound to this exact email
        $_SESSION['reg_otp_expiry']  = time() + AGRI_OTP_EXPIRY_SECONDS;
        $_SESSION['reg_otp_attempts'] = 0;
        $_SESSION['otp_verified']    = false;
        return ['ok' => true, 'error' => null];
    }
}

/** Verify a submitted OTP against the session state, for the given email. */
if (!function_exists('agri_otp_verify')) {
    function agri_otp_verify(string $submitted, string $email = ''): array {
        if (empty($_SESSION['reg_otp_hash']) || empty($_SESSION['reg_otp_expiry'])) {
            return ['ok' => false, 'reason' => 'no_active_otp'];
        }
        if ($email !== '' && !empty($_SESSION['reg_otp_email']) && !hash_equals($_SESSION['reg_otp_email'], $email)) {
            return ['ok' => false, 'reason' => 'email_mismatch'];
        }
        if (time() > $_SESSION['reg_otp_expiry']) {
            return ['ok' => false, 'reason' => 'expired'];
        }
        if (($_SESSION['reg_otp_attempts'] ?? 0) >= AGRI_OTP_MAX_ATTEMPTS) {
            return ['ok' => false, 'reason' => 'too_many_attempts'];
        }
        if (!hash_equals($_SESSION['reg_otp_hash'], agri_otp_hash($submitted))) {
            $_SESSION['reg_otp_attempts'] = ($_SESSION['reg_otp_attempts'] ?? 0) + 1;
            return ['ok' => false, 'reason' => 'incorrect'];
        }
        $_SESSION['otp_verified'] = true;
        return ['ok' => true];
    }
}

/** Clear all temporary OTP/registration session state. */
if (!function_exists('agri_otp_clear')) {
    function agri_otp_clear(): void {
        unset(
            $_SESSION['reg_data'],
            $_SESSION['reg_otp_hash'],
            $_SESSION['reg_otp_email'],
            $_SESSION['reg_otp_expiry'],
            $_SESSION['reg_otp_attempts'],
            $_SESSION['reg_otp_resend_count'],
            $_SESSION['reg_otp_last_sent'],
            $_SESSION['otp_verified'],
            $_SESSION['reg_lang']
        );
    }
}
