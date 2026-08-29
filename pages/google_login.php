<?php
// =====================================================================
// pages/google_login.php — Handles the Google Identity Services credential
// posted from login.php's "Continue with Google" button.
//
// SECURITY: The credential is now verified with Google's tokeninfo
// endpoint (https://oauth2.googleapis.com/tokeninfo) instead of just
// being base64-decoded. This checks the token's signature, audience,
// issuer, and expiry with Google itself before we ever trust it.
//
// For higher-volume production use, Google recommends verifying
// locally against Google's public JWKS using the official client
// library instead of calling tokeninfo on every login:
//   composer require google/apiclient
//   $payload = (new Google_Client(['client_id' => GOOGLE_CLIENT_ID]))
//                  ->verifyIdToken($credential);
// The tokeninfo approach below is fully secure (Google still validates
// the signature) but is rate-limited by Google for very high traffic.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
agri_session_start();
require_once __DIR__ . '/../includes/env.php';
include __DIR__ . '/../includes/db.php';

$credential = $_POST['credential'] ?? '';
if (!$credential || !is_string($credential) || strlen($credential) > 8000) {
    header('Location: login.php?error=google');
    exit;
}

$clientId = env('GOOGLE_CLIENT_ID', '');
if ($clientId === '') {
    error_log('[AgriCart] google_login.php: GOOGLE_CLIENT_ID is not configured — refusing to log in.');
    header('Location: login.php?error=google');
    exit;
}

// ── Verify the token WITH Google (checks signature, expiry, issuer) ──
$ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential));
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 8,
    CURLOPT_TIMEOUT        => 12,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$response  = curl_exec($ch);
$httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError || $httpCode !== 200) {
    error_log('[AgriCart] Google token verification failed: HTTP ' . $httpCode . ' ' . $curlError);
    header('Location: login.php?error=google');
    exit;
}

$payload = json_decode((string)$response, true);
if (!is_array($payload)) {
    header('Location: login.php?error=google');
    exit;
}

// ── Validate every claim ourselves too — never trust on HTTP 200 alone ──
$aud           = $payload['aud'] ?? '';
$iss           = $payload['iss'] ?? '';
$exp           = (int)($payload['exp'] ?? 0);
$emailVerified = ($payload['email_verified'] ?? 'false') === 'true' || ($payload['email_verified'] ?? false) === true;
$email         = $payload['email'] ?? '';
$name          = $payload['name'] ?? ($payload['given_name'] ?? 'Google User');

$validIssuer = in_array($iss, ['accounts.google.com', 'https://accounts.google.com'], true);

if (
    $aud === '' || $aud !== $clientId ||       // token wasn't issued for THIS app
    !$validIssuer ||                            // wrong issuer
    $exp === 0 || $exp < time() ||               // expired
    !$emailVerified ||                           // Google itself hasn't verified this email
    $email === ''
) {
    error_log('[AgriCart] Google token failed local validation (aud/iss/exp/email_verified mismatch).');
    header('Location: login.php?error=google');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
$stmt->bind_param("s", $email);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if ($user) {
    // Existing account → log in. Regenerate the session ID first to
    // prevent session fixation.
    agri_session_regenerate();
    unset($_SESSION['is_admin'], $_SESSION['admin_id'], $_SESSION['admin_member_id']); // never let a storefront login carry admin state
    $_SESSION['user_id']          = $user['id'];
    $_SESSION['user_name']        = $user['full_name'];
    $_SESSION['user']             = $user['mobile'];
    $_SESSION['user_role']        = $user['role'] ?? 'farmer';
    $_SESSION['user_email']       = $user['email'] ?? '';
    acc_stamp_login($conn, (int)$user['id'], 'google');
    $_SESSION['user_farmer_type'] = $user['farmer_type'] ?? '';
    $_SESSION['user_district']    = $user['district'] ?? '';
    $_SESSION['user_taluka']      = $user['taluka'] ?? '';
    $_SESSION['user_village']     = $user['village'] ?? '';
    $_SESSION['user_crop']        = $user['primary_crop'] ?? '';
    header('Location: ../index.php');
    exit;
} else {
    // No account with this email yet — send them to Register,
    // pre-filled, so they can add mobile number + password
    // (users table requires a unique mobile, which Google doesn't give us).
    $_SESSION['google_prefill'] = [
        'full_name' => is_string($name) ? substr($name, 0, 150) : 'Google User',
        'email'     => $email,
    ];
    header('Location: register.php?via=google');
    exit;
}
