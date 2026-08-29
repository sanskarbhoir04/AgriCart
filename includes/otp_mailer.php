<?php
// =====================================================================
// includes/otp_mailer.php — sends the AgriCart registration OTP by
// EMAIL only (no paid SMS API), using PHPMailer + Gmail SMTP.
//
// Credentials always come from .env (never hardcoded, never echoed).
// In local development, when SMTP isn't configured, the OTP is written
// only to the PHP error log (never shown on the page, never returned
// in any AJAX/JSON response) — see agri_otp_dev_log_only() below.
// =====================================================================

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/otp_lang.php';
require_once __DIR__ . '/lib/PHPMailer/Exception.php';
require_once __DIR__ . '/lib/PHPMailer/PHPMailer.php';
require_once __DIR__ . '/lib/PHPMailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

if (!function_exists('agri_mask_email')) {
    /** sa****@gmail.com — used only in log lines, never shown to the browser. */
    function agri_mask_email(string $email): string {
        $at = strpos($email, '@');
        if ($at === false || $at < 1) return '***';
        $local = substr($email, 0, $at);
        $domain = substr($email, $at); // includes leading @
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));
        return $visible . str_repeat('*', 4) . $domain;
    }
}

if (!function_exists('agri_smtp_configured')) {
    function agri_smtp_configured(): bool {
        return (string) env('MAIL_USERNAME', '') !== '' && (string) env('MAIL_PASSWORD', '') !== '';
    }
}

if (!function_exists('agri_otp_dev_log_only')) {
    /**
     * Local-only fallback: never echoes/returns the OTP, only writes to
     * the PHP server error log, and only includes the raw digits when
     * both APP_ENV=local AND OTP_DEV_LOG=true. Otherwise it logs a
     * masked, OTP-free line so developers still see that a "send"
     * happened.
     */
    function agri_otp_dev_log_only(string $email, string $otp): void {
        $masked = agri_mask_email($email);
        $isLocal = env('APP_ENV', 'production') === 'local';
        $devLog  = filter_var(env('OTP_DEV_LOG', false), FILTER_VALIDATE_BOOLEAN);

        if ($isLocal && $devLog) {
            error_log("[AgriCart][LOCAL EMAIL OTP] OTP for {$masked}: {$otp} (DEV ONLY — SMTP not configured, not actually emailed)");
        } else {
            error_log("[AgriCart][LOCAL EMAIL OTP] OTP generated for {$masked} (dev log disabled — set OTP_DEV_LOG=true locally to see the code)");
        }
    }
}

if (!function_exists('agri_otp_expiry_label')) {
    /**
     * Human-readable OTP validity window, driven by the REAL expiry
     * constant (AGRI_OTP_EXPIRY_SECONDS in otp.php) — never hardcoded —
     * so the email always truthfully matches what the backend enforces.
     */
    function agri_otp_expiry_label(string $lang): string {
        $seconds = defined('AGRI_OTP_EXPIRY_SECONDS') ? AGRI_OTP_EXPIRY_SECONDS : 300;
        $minutes = max(1, (int) round($seconds / 60));
        return agri_otp_t('email_minutes', $lang, $minutes);
    }
}

if (!function_exists('agri_otp_email_html')) {
    /**
     * Builds the premium OTP verification email (see
     * includes/templates/otp_email.html) by substituting {{PLACEHOLDER}}
     * tokens. All dynamic values are HTML-escaped before substitution —
     * the template itself contains no PHP, so it's safe to hand to a
     * designer/marketer to restyle without touching any code.
     */
    function agri_otp_email_html(string $name, string $otp, string $lang, string $logoCid = ''): string {
        $esc = fn(string $s) => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        $template = file_get_contents(__DIR__ . '/templates/otp_email.html');
        if ($template === false) {
            // Extremely defensive fallback — should never happen in a
            // normal deployment, but better a plain-looking email than
            // a fatal error mid-registration.
            $template = '<p>{{USER_NAME}}, your OTP is {{OTP}} (valid for {{OTP_EXPIRY}}).</p>';
        }

        $websiteUrl = env('WEBSITE_URL', 'https://agricart.in');
        $websiteLabel = preg_replace('#^https?://#', '', rtrim($websiteUrl, '/'));

        $tokens = [
            '{{LANG_ATTR}}'        => $esc($lang),
            '{{LOGO_CID}}'         => $esc($logoCid),
            '{{HEADING}}'          => $esc(agri_otp_t('email_heading', $lang)),
            '{{USER_NAME}}'        => $esc($name !== '' ? $name : 'there'),
            '{{INTRO_MSG}}'        => $esc(agri_otp_t('email_intro2', $lang)),
            '{{OTP}}'              => $esc($otp),
            '{{OTP_EXPIRY}}'       => $esc(agri_otp_expiry_label($lang)),
            '{{NOTE_EXPIRY}}'      => $esc(agri_otp_t('email_note_expiry', $lang, agri_otp_expiry_label($lang))),
            '{{NOTE_SHARE}}'       => $esc(agri_otp_t('email_note_share', $lang)),
            '{{NOTE_IGNORE}}'      => $esc(agri_otp_t('email_note_ignore', $lang)),
            '{{FOOTER_TAGLINE}}'   => $esc(agri_otp_t('email_footer_tagline', $lang)),
            '{{CURRENT_YEAR}}'     => $esc(date('Y')),
            '{{SUPPORT_EMAIL}}'    => $esc(env('SUPPORT_EMAIL', 'support@agricart.in')),
            '{{WEBSITE_URL}}'      => $esc($websiteUrl),
            '{{WEBSITE_URL_LABEL}}' => $esc($websiteLabel),
        ];

        return strtr($template, $tokens);
    }
}

if (!function_exists('agri_send_otp_email')) {
    /**
     * Sends (or, in local dev mode without SMTP, log-only "sends") the
     * OTP email. Returns ['ok' => bool, 'error' => string|null]. The
     * $error string is always safe to log — never SMTP credentials or
     * raw exception text with server paths.
     */
    function agri_send_otp_email(string $toEmail, string $toName, string $otp, string $lang = 'en'): array {
        $lang = agri_otp_normalize_lang($lang);

        if (!agri_smtp_configured()) {
            // No Gmail SMTP configured — only acceptable outside production.
            if (env('APP_ENV', 'production') !== 'local') {
                error_log('[AgriCart] OTP email send skipped: MAIL_USERNAME/MAIL_PASSWORD not set in .env');
                return ['ok' => false, 'error' => 'mail_not_configured'];
            }
            agri_otp_dev_log_only($toEmail, $otp);
            return ['ok' => true, 'error' => null];
        }

        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = env('MAIL_HOST', 'smtp.gmail.com');
            $mail->SMTPAuth   = true;
            $mail->Username   = env('MAIL_USERNAME');
            $mail->Password   = env('MAIL_PASSWORD'); // Gmail App Password, never the account password
            $mail->SMTPSecure = env('MAIL_ENCRYPTION', 'tls') === 'ssl'
                ? PHPMailer::ENCRYPTION_SMTPS
                : PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = (int) env('MAIL_PORT', 587);
            $mail->CharSet    = 'UTF-8';
            $mail->Timeout    = 10;

            // Common XAMPP/Windows-only escape hatch: some local PHP builds
            // ship an outdated/missing CA bundle, which makes the TLS
            // handshake fail with a certificate verification error even
            // though the Gmail credentials are correct. Only ever enable
            // this for local debugging (never in production) by setting
            // MAIL_SMTP_INSECURE=true in .env — see OTP_EMAIL_SETUP.md.
            if (filter_var(env('MAIL_SMTP_INSECURE', false), FILTER_VALIDATE_BOOLEAN) && env('APP_ENV', 'production') === 'local') {
                $mail->SMTPOptions = [
                    'ssl' => [
                        'verify_peer'       => false,
                        'verify_peer_name'  => false,
                        'allow_self_signed' => true,
                    ],
                ];
            }

            // Opt-in verbose SMTP conversation logging for local debugging
            // (MAIL_DEBUG=true in .env, local only). PHPMailer redacts
            // AUTH credential lines by default; we additionally skip any
            // line containing "AUTH" as defense in depth before logging.
            if (filter_var(env('MAIL_DEBUG', false), FILTER_VALIDATE_BOOLEAN) && env('APP_ENV', 'production') === 'local') {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function ($str, $level) {
                    if (stripos($str, 'AUTH') !== false) return;
                    error_log('[AgriCart][SMTP DEBUG] ' . trim($str));
                };
            }

            $mail->setFrom(env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME')), env('MAIL_FROM_NAME', 'AgriCart'));
            $mail->addAddress($toEmail, $toName !== '' ? $toName : '');
            $mail->addReplyTo(env('MAIL_FROM_ADDRESS', env('MAIL_USERNAME')), 'AgriCart Support');

            // Embed the actual AgriCart logo — same graphic used across
            // the site — as a lightweight, pre-resized copy optimized for
            // email (the original assets/images/agricart-logo.png is
            // ~1.3MB at 1254×1254, too heavy to attach to every OTP email).
            $logoCid = '';
            $logoPath = __DIR__ . '/../assets/images/agricart-logo-email.png';
            if (!is_file($logoPath)) {
                // Fallback: original full-size logo if the optimized copy
                // is missing for some reason.
                $logoPath = __DIR__ . '/../assets/images/agricart-logo.png';
            }
            if (is_file($logoPath)) {
                $logoCid = 'agricart_logo';
                $mail->addEmbeddedImage($logoPath, $logoCid, 'agricart-logo.png');
            }

            $mail->isHTML(true);
            $mail->Subject = agri_otp_t('email_subject', $lang); // "Your AgriCart Registration OTP" — required exact subject
            $mail->Body    = agri_otp_email_html($toName, $otp, $lang, $logoCid);
            $expiryLabel = agri_otp_expiry_label($lang);
            $mail->AltBody = 'Hello ' . ($toName !== '' ? $toName : 'there') . ",\n\n"
                . agri_otp_t('email_intro2', $lang) . "\n\n"
                . "OTP: " . $otp . "\n\n"
                . agri_otp_t('email_note_expiry', $lang, $expiryLabel) . "\n"
                . agri_otp_t('email_note_share', $lang) . "\n"
                . agri_otp_t('email_note_ignore', $lang) . "\n\n"
                . "AgriCart — " . agri_otp_t('email_footer_tagline', $lang) . "\n"
                . env('SUPPORT_EMAIL', 'support@agricart.in') . " | " . env('WEBSITE_URL', 'https://agricart.in');

            $mail->send();
            return ['ok' => true, 'error' => null];
        } catch (PHPMailerException $e) {
            // $e->getMessage() / $mail->ErrorInfo never contain the
            // password itself — they're messages like "SMTP Error: Could
            // not authenticate." or "SMTP connect() failed" — safe to log
            // and genuinely useful for diagnosing setup problems.
            error_log('[AgriCart] OTP email send failed for ' . agri_mask_email($toEmail) . ': ' . $mail->ErrorInfo);
            return ['ok' => false, 'error' => 'smtp_error'];
        } catch (\Throwable $e) {
            error_log('[AgriCart] OTP email send failed for ' . agri_mask_email($toEmail) . ': ' . $e->getMessage());
            return ['ok' => false, 'error' => 'unexpected_error'];
        }
    }
}
