<?php
// =====================================================================
// setup/test_mail.php — quick command-line test for the Gmail SMTP OTP
// sender. Prints the real success/failure reason directly to your
// terminal, instead of digging through the PHP error log.
//
// USAGE (from the AgriCart project root, via XAMPP's php.exe):
//   php setup/test_mail.php your-real-email@example.com
//
// Delete this file (or make sure it's not web-accessible) once you're
// done debugging — it is a CLI-only tool and safely does nothing if
// someone tries to load it in a browser.
// =====================================================================

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    die('This script is CLI-only.');
}

require __DIR__ . '/../includes/otp_mailer.php';

// Route error_log() straight to this terminal for the duration of the
// test, instead of wherever php.ini normally sends it (a log file you'd
// otherwise have to go hunt down).
ini_set('error_log', '');
ini_set('log_errors', '1');
ini_set('error_log', 'php://stdout');

$to = $argv[1] ?? null;
if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php setup/test_mail.php your-real-email@example.com\n");
    exit(1);
}

echo "── AgriCart OTP mail test ──\n";
echo "APP_ENV            : " . env('APP_ENV', '(not set — defaults to production)') . "\n";
echo "MAIL_HOST           : " . env('MAIL_HOST', '(not set)') . "\n";
echo "MAIL_PORT           : " . env('MAIL_PORT', '(not set)') . "\n";
echo "MAIL_USERNAME        : " . (env('MAIL_USERNAME') ? agri_mask_email(env('MAIL_USERNAME')) : '(not set)') . "\n";
echo "MAIL_PASSWORD set?   : " . (env('MAIL_PASSWORD') ? 'yes (' . strlen(env('MAIL_PASSWORD')) . ' chars)' : 'NO — this is almost certainly why sending fails') . "\n";
echo "MAIL_ENCRYPTION     : " . env('MAIL_ENCRYPTION', '(not set, defaults to tls)') . "\n";
echo "smtp_configured()    : " . (agri_smtp_configured() ? 'yes' : 'no — MAIL_USERNAME/MAIL_PASSWORD missing or blank') . "\n";
echo "\nSending a real test OTP to {$to} ...\n";

// Force MAIL_DEBUG on for this one-off CLI run so the SMTP conversation
// (minus AUTH lines) prints straight to the terminal.
putenv('MAIL_DEBUG=true');
$_ENV['MAIL_DEBUG'] = 'true';

$result = agri_send_otp_email($to, 'Test User', '123456', 'en');

echo "\n── Result ──\n";
if ($result['ok']) {
    if (agri_smtp_configured()) {
        echo "✅ SUCCESS — a real email was sent to {$to}. Check that inbox (and Spam folder).\n";
    } else {
        echo "✅ Returned ok=true, but SMTP isn't configured — this only happened because APP_ENV=local,\n";
        echo "   so the OTP went to the PHP error log instead of a real email. Set MAIL_USERNAME/MAIL_PASSWORD\n";
        echo "   in .env if you want to test real delivery.\n";
    }
} else {
    echo "❌ FAILED — error code: {$result['error']}\n";
    echo "   Check the PHP error log printed just above (lines starting with [AgriCart]) for the exact\n";
    echo "   SMTP/PHPMailer error message — that tells you precisely what to fix (auth, connection, etc).\n";
    echo "   See OTP_EMAIL_SETUP.md for the most common fixes.\n";
}
