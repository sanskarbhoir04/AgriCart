<?php
// ─── SESSION & DB ───
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
agri_session_start();
require_once __DIR__ . '/../includes/otp_lang.php';
require_once __DIR__ . '/../includes/otp.php';
require_once __DIR__ . '/../includes/otp_ratelimit.php';
include __DIR__ . '/../includes/db.php';

// Already logged in
if (isset($_SESSION['user_name']) || isset($_SESSION['user'])) {
    header("Location: ../index.php"); exit;
}

// ─── LANGUAGE (drives backend messages + the OTP email itself) ───
// Mirrors the header's language selector (see agri-master.js / header.php),
// which is saved to localStorage and echoed back here via a hidden "lang"
// form field (see the <script> block near the bottom of this file).
// Defaults to English on a fresh visit, same as before.
$lang = agri_otp_normalize_lang($_POST['lang'] ?? ($_SESSION['reg_lang'] ?? 'en'));
$_SESSION['reg_lang'] = $lang;

$error   = "";
$success = "";
$step    = isset($_POST['reg_step']) ? (int)$_POST['reg_step'] : 1;
if (isset($_SESSION['google_prefill']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    // keep it available for this one page render, then clear it
    register_shutdown_function(function () { unset($_SESSION['google_prefill']); });
}

// ─── AJAX: RESEND OTP (returns JSON, doesn't render the page) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'resend_otp') {
    header('Content-Type: application/json');
    $ajaxLang = agri_otp_normalize_lang($_POST['lang'] ?? $lang);

    if (!csrf_verify()) {
        echo json_encode(['success' => false, 'message' => agri_otp_t('session_expired', $ajaxLang)]);
        exit;
    }
    $rd = $_SESSION['reg_data'] ?? [];
    if (empty($rd) || empty($rd['email'])) {
        echo json_encode(['success' => false, 'message' => agri_otp_t('resend_restart', $ajaxLang)]);
        exit;
    }

    // Server-side cooldown — the client-side countdown is a UI
    // convenience only, this check is what actually enforces it.
    $lastSent = $_SESSION['reg_otp_last_sent'] ?? 0;
    $wait = AGRI_OTP_RESEND_COOLDOWN - (time() - $lastSent);
    if ($wait > 0) {
        echo json_encode(['success' => false, 'message' => agri_otp_t('resend_wait', $ajaxLang, $wait), 'wait' => $wait]);
        exit;
    }
    if (($_SESSION['reg_otp_resend_count'] ?? 0) >= AGRI_OTP_MAX_RESENDS) {
        echo json_encode(['success' => false, 'message' => agri_otp_t('resend_limit_reached', $ajaxLang), 'disable_button' => true]);
        exit;
    }

    // Server-side rate limiting (per email + per IP), independent of the
    // per-session resend counter above.
    $rateCheck = agri_otp_rate_check($conn, $rd['email']);
    if (!$rateCheck['ok']) {
        $key = $rateCheck['reason'] === 'ip' ? 'rate_limited_ip' : 'rate_limited_email';
        echo json_encode(['success' => false, 'message' => agri_otp_t($key, $ajaxLang), 'disable_button' => true]);
        exit;
    }

    $sendResult = agri_otp_start($rd['mobile'], $rd['email'], $rd['full_name'] ?? '', $ajaxLang);
    if (!$sendResult['ok']) {
        // Do NOT increment the resend count or rate-limit record on a failed send.
        echo json_encode(['success' => false, 'message' => agri_otp_t('otp_send_failed', $ajaxLang)]);
        exit;
    }

    agri_otp_rate_record($conn, $rd['email']);
    $_SESSION['reg_otp_last_sent']     = time();
    $_SESSION['reg_otp_resend_count']  = ($_SESSION['reg_otp_resend_count'] ?? 0) + 1;
    $_SESSION['reg_lang']              = $ajaxLang;

    echo json_encode(['success' => true, 'message' => agri_otp_t('resend_ok', $ajaxLang)]);
    exit;
}

// ─── ACTION: CHANGE EMAIL (returns to Step 1, preserves name/mobile only) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_email') {
    if (!csrf_verify()) {
        $error = agri_otp_t('session_expired', $lang); $step = 1;
    } else {
        $preservedName   = $_SESSION['reg_data']['full_name'] ?? trim($_POST['full_name'] ?? '');
        $preservedMobile = $_SESSION['reg_data']['mobile'] ?? trim($_POST['mobile'] ?? '');
        agri_otp_clear(); // wipes reg_data (incl. the hashed password) + all OTP session state
        $_SESSION['reg_lang'] = $lang;
        // Reuse the Step 1 template's existing $_POST-based prefill logic.
        $_POST['full_name'] = $preservedName;
        $_POST['mobile']    = $preservedMobile;
        $_POST['email']     = '';
        $success = agri_otp_t('change_email_done', $lang);
        $step = 1;
    }
}

// ─── STEP 1 SUBMIT: Basic info + OTP send ───
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {

    if ($_POST['action'] === 'send_otp') {
        if (!csrf_verify()) {
            $error = agri_otp_t('session_expired', $lang); $step = 1;
        } else {
        $full_name = trim($_POST['full_name'] ?? '');
        $mobile    = trim($_POST['mobile'] ?? '');
        $email     = trim($_POST['email'] ?? '');
        $password  = trim($_POST['password'] ?? '');
        $cpassword = trim($_POST['cpassword'] ?? '');

        // Email is now REQUIRED — the free verification OTP is sent
        // there instead of via a paid SMS API. The mobile field is
        // still collected and validated, but never OTP-verified.
        if (empty($full_name) || empty($mobile) || empty($email) || empty($password)) {
            $error = agri_otp_t('fill_all_fields', $lang); $step = 1;
        } elseif (!preg_match('/^[6-9]\d{9}$/', $mobile)) {
            $error = agri_otp_t('invalid_mobile', $lang); $step = 1;
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = agri_otp_t('invalid_email', $lang); $step = 1;
        } elseif (strlen($password) < 6) {
            $error = agri_otp_t('password_too_short', $lang); $step = 1;
        } elseif ($password !== $cpassword) {
            $error = agri_otp_t('password_mismatch', $lang); $step = 1;
        } else {
            // Check duplicates — mobile and email separately, so the
            // person gets an accurate, specific message.
            $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ? LIMIT 1");
            $stmt->bind_param("s", $mobile);
            $stmt->execute();
            $mobileTaken = $stmt->get_result()->num_rows > 0;

            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $emailTaken = $stmt->get_result()->num_rows > 0;

            if ($mobileTaken) {
                $error = agri_otp_t('mobile_taken', $lang); $step = 1;
            } elseif ($emailTaken) {
                $error = agri_otp_t('email_taken', $lang); $step = 1;
            } else {
                $rateCheck = agri_otp_rate_check($conn, $email);
                if (!$rateCheck['ok']) {
                    $key = $rateCheck['reason'] === 'ip' ? 'rate_limited_ip' : 'rate_limited_email';
                    $error = agri_otp_t($key, $lang); $step = 1;
                } else {
                    // Hash the password immediately — never keep it in
                    // plaintext, even temporarily in the session.
                    $_SESSION['reg_data'] = [
                        'full_name'     => $full_name,
                        'mobile'        => $mobile,
                        'email'         => $email,
                        'password_hash' => password_hash($password, PASSWORD_BCRYPT),
                    ];
                    $sendResult = agri_otp_start($mobile, $email, $full_name, $lang);
                    if ($sendResult['ok']) {
                        agri_otp_rate_record($conn, $email);
                        $_SESSION['reg_otp_last_sent']    = time();
                        $_SESSION['reg_otp_resend_count'] = 0;
                        $success = agri_otp_t('otp_sent_email', $lang);
                        $step = 2;
                    } else {
                        $error = agri_otp_t('otp_send_failed', $lang); $step = 1;
                    }
                }
            }
        }
        }
    }

    if ($_POST['action'] === 'verify_otp') {
        if (!csrf_verify()) {
            $error = agri_otp_t('session_expired', $lang); $step = 1;
        } elseif (empty($_SESSION['reg_data'])) {
            $error = agri_otp_t('resend_restart', $lang); $step = 1;
        } else {
        $entered = trim(implode('', [
            $_POST['otp1']??'', $_POST['otp2']??'', $_POST['otp3']??'',
            $_POST['otp4']??'', $_POST['otp5']??'', $_POST['otp6']??'',
        ]));
        if (strlen($entered) !== 6 || !ctype_digit($entered)) {
            $error = agri_otp_t('otp_enter_6', $lang); $step = 2;
        } else {
            // Verified only on the PHP backend — never trust a
            // JavaScript-only check for this.
            $result = agri_otp_verify($entered, $_SESSION['reg_data']['email'] ?? '');
            if ($result['ok']) {
                $step = 3; // Go to profile step
                $success = agri_otp_t('otp_verified_ok', $lang);
            } else {
                switch ($result['reason']) {
                    case 'expired':
                        $error = agri_otp_t('otp_expired', $lang); break;
                    case 'too_many_attempts':
                        $error = agri_otp_t('otp_too_many_attempts', $lang); break;
                    case 'no_active_otp':
                        $error = agri_otp_t('otp_no_active', $lang); break;
                    case 'email_mismatch':
                        $error = agri_otp_t('otp_session_mismatch', $lang); break;
                    default:
                        $remaining = max(0, AGRI_OTP_MAX_ATTEMPTS - ($_SESSION['reg_otp_attempts'] ?? 0));
                        $error = agri_otp_t('otp_incorrect', $lang, $remaining);
                }
                $step = 2;
            }
        }
        }
    }

    if ($_POST['action'] === 'complete_register') {
        if (!csrf_verify()) {
            header("Location: register.php"); exit;
        }
        $rd = $_SESSION['reg_data'] ?? [];
        // Hard gate: registration cannot complete without a verified OTP,
        // AND the verified OTP session must still match this exact email
        // (defends against a stale/hijacked session pointing at a
        // different address than the one that was actually verified).
        $otpEmailMatches = !empty($rd['email']) && !empty($_SESSION['reg_otp_email'])
            && hash_equals($_SESSION['reg_otp_email'], $rd['email']);
        if (empty($rd) || ($_SESSION['otp_verified'] ?? false) !== true || !$otpEmailMatches) {
            header("Location: register.php"); exit;
        }

        $full_name   = $rd['full_name'];
        $mobile      = $rd['mobile'];
        $email       = $rd['email'];
        $password    = $rd['password_hash']; // already hashed at step 1
        $farmer_type = trim($_POST['farmer_type'] ?? 'Individual Farmer');
        $district    = trim($_POST['district'] ?? '');
        $taluka      = trim($_POST['taluka'] ?? '');
        $village     = trim($_POST['village'] ?? '');
        $pincode     = trim($_POST['pincode'] ?? '');
        $crop        = trim($_POST['primary_crop'] ?? '');
        $role        = 'farmer';

        // PIN code is what lets us prefill delivery address at checkout,
        // so it's required here even though village/taluka are optional.
        if (!preg_match('/^\d{6}$/', $pincode)) {
            // Hardcoded (not routed through agri_otp_t) since this project's
            // translation table doesn't have an 'invalid_pincode' key yet —
            // add one there later if mr/hi wording is wanted for this message.
            $pincodeErrByLang = [
                'mr' => 'कृपया वैध ६ अंकी पिन कोड टाका.',
                'hi' => 'कृपया मान्य 6 अंकों का पिन कोड डालें.',
                'en' => 'Please enter a valid 6-digit PIN code.',
            ];
            $error = $pincodeErrByLang[$lang] ?? $pincodeErrByLang['en'];
            $step = 3;
        } else {

        // Re-check duplicates right before insert — closes the race
        // window between Step 1's check and this final submit (the
        // unique indexes from setup/otp_email_verification_upgrade.sql
        // are the ultimate backstop, but this gives a friendlier message).
        $stmt = $conn->prepare("SELECT id FROM users WHERE mobile = ? OR email = ? LIMIT 1");
        $stmt->bind_param("ss", $mobile, $email);
        $stmt->execute();
        if ($stmt->get_result()->num_rows > 0) {
            agri_otp_clear();
            header("Location: register.php"); exit;
        }

        // email_verified = 1 because the OTP above was verified against
        // this email address. mobile_verified STAYS 0 — AgriCart sends
        // the OTP to email only and never claims SMS verification.
        $email_verified  = 1;
        $mobile_verified = 0;

        // Insert into users table
        $stmt = $conn->prepare("INSERT INTO users (full_name, mobile, email, password, farmer_type, district, taluka, village, primary_crop, role, email_verified, mobile_verified, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
        $stmt->bind_param("ssssssssssii",
            $full_name, $mobile, $email, $password,
            $farmer_type, $district, $taluka, $village, $crop, $role,
            $email_verified, $mobile_verified);

        if ($stmt->execute()) {
            $newUserId = $conn->insert_id;

            // Notify Admin (spec §14 "New Seller") — best-effort.
            if ($role === 'seller') {
                require_once __DIR__ . '/../includes/admin_notifications_schema.php';
                agri_notify_admin(
                    $conn,
                    'new_seller',
                    'New Seller Registered',
                    $full_name . ' just signed up as a seller.',
                    'companies.php'
                );
            }

            // Build a delivery address string from what we collected on
            // this step, and write it straight into the account so
            // checkout is pre-filled from the very first order — no more
            // re-typing the address every time. This is best-effort: if
            // the saved_* columns or user_addresses table don't exist yet
            // on this DB, registration must still succeed.
            $addressParts = array_filter([$village, $taluka, $district]);
            $composedAddress = implode(', ', $addressParts);
            if ($composedAddress !== '') {
                try {
                    $su = $conn->prepare("UPDATE users SET saved_name = ?, saved_mobile = ?, saved_pincode = ?, saved_address = ? WHERE id = ?");
                    $su->bind_param("ssssi", $full_name, $mobile, $pincode, $composedAddress, $newUserId);
                    $su->execute();
                } catch (\Throwable $eSave) {
                    error_log('AgriCart register: saved_* write-back failed: ' . $eSave->getMessage());
                }
                try {
                    $ia = $conn->prepare("INSERT INTO user_addresses (user_id, label, name, mobile, pincode, address, is_default, created_at) VALUES (?, 'Home', ?, ?, ?, ?, 1, NOW())");
                    $ia->bind_param("issss", $newUserId, $full_name, $mobile, $pincode, $composedAddress);
                    $ia->execute();
                } catch (\Throwable $eAddr) {
                    error_log('AgriCart register: user_addresses insert failed: ' . $eAddr->getMessage());
                }
            }

            agri_otp_clear(); // wipe all temporary reg/OTP session data
            agri_session_regenerate(); // prevent session fixation on the new account

            $_SESSION['user_id']          = $newUserId;
            $_SESSION['user_name']        = $full_name;
            $_SESSION['user']             = $mobile;
            $_SESSION['user_role']        = $role;
            $_SESSION['user_email']       = $email;
            acc_stamp_login($conn, (int)$newUserId, 'registration');
            $_SESSION['user_farmer_type'] = $farmer_type;
            $_SESSION['user_district']    = $district;
            $_SESSION['user_taluka']      = $taluka;
            $_SESSION['user_village']     = $village;
            $_SESSION['user_crop']        = $crop;
            header("Location: ../index.php?registered=1"); exit;
        } else {
            $error = agri_otp_t('register_failed', $lang); $step = 3;
        }
        } // end pincode-valid else
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="color-scheme" content="dark">
<title>Register — AgriCart</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
html, body{ height:100%; }
:root, html{ color-scheme: dark; }
body{ overflow-x:hidden; }
/* ─── AgriCart Register — themed to match Admin Login (dark forest gradient + floating orbs) ─── */
.auth-shell{
    position:relative;
    font-family:'Poppins','Noto Sans Devanagari',sans-serif;
    width:100%;
    min-height:100vh;
    display:flex;
    flex-direction:column;
    align-items:center;
    justify-content:center;
    padding:48px 16px;
    background:
        radial-gradient(circle at 12% 15%, rgba(76,175,80,0.18), transparent 42%),
        radial-gradient(circle at 88% 85%, rgba(255,193,7,0.12), transparent 45%),
        linear-gradient(135deg, rgba(11,31,18,0.32), rgba(20,54,28,0.24) 55%, rgba(26,68,34,0.28)),
        url('../assets/images/login-bg.png');
    background-size:180% 180%, 180% 180%, 180% 180%, cover;
    background-position:center, center, center, center;
    background-repeat:no-repeat;
    background-attachment:fixed;
    animation:bgDrift 14s ease-in-out infinite;
}
@keyframes bgDrift{ 0%,100%{background-position:0% 50%} 50%{background-position:100% 50%} }

/* floating soft particles behind the card */
.bg-orb{ position:fixed; border-radius:50%; filter:blur(2px); pointer-events:none; opacity:.5; z-index:0; }
.bg-orb.o1{ width:14px; height:14px; background:#4CAF50; top:14%; left:12%; animation:orbFloat 7s ease-in-out infinite; }
.bg-orb.o2{ width:9px; height:9px; background:#FFC107; top:75%; left:18%; animation:orbFloat 9s ease-in-out infinite 1.2s; }
.bg-orb.o3{ width:11px; height:11px; background:#4CAF50; top:20%; right:14%; animation:orbFloat 8s ease-in-out infinite .6s; }
.bg-orb.o4{ width:7px; height:7px; background:#fff; top:82%; right:20%; animation:orbFloat 10s ease-in-out infinite 2s; }
@keyframes orbFloat{ 0%,100%{transform:translateY(0) scale(1)} 50%{transform:translateY(-22px) scale(1.15)} }

.auth-card{
    background:rgba(255,255,255,0.10);
    backdrop-filter:blur(14px) saturate(140%);
    -webkit-backdrop-filter:blur(14px) saturate(140%);
    border-radius:24px;box-shadow:0 20px 60px rgba(0,0,0,.25), inset 0 1px 0 rgba(255,255,255,.3);width:100%;max-width:560px;overflow-x:hidden;overflow-y:visible;border:1.5px solid rgba(255,255,255,.5);position:relative;z-index:1;transition:box-shadow .3s ease;
}
.auth-card:hover{box-shadow:0 26px 70px rgba(0,0,0,.32), inset 0 1px 0 rgba(255,255,255,.35);}

/* Card header */
.card-head{background:transparent;padding:32px 36px 20px;color:#fff;position:relative;overflow:hidden;border-bottom:1px solid rgba(255,255,255,.1);}
.card-head::before{content:'';position:absolute;width:200px;height:200px;background:rgba(76,175,80,.09);border-radius:50%;top:-70px;right:-60px;}
.card-head-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;position:relative;z-index:1;}
.auth-logo{display:flex;align-items:center;gap:10px;font-size:25px;font-weight:800;color:#fff;text-decoration:none;letter-spacing:-0.3px;font-family:'Poppins',sans-serif;}
.auth-logo i{color:#5A9802;font-size:25px;}
.auth-logo-text{color:#fff;}
.auth-logo-text span{color:#5A9802;}
.card-head h2{font-family:'Poppins',sans-serif;font-size:24px;font-weight:800;position:relative;z-index:1;margin-bottom:4px;}
.card-head p{font-size:13.5px;opacity:.75;position:relative;z-index:1;}

/* ─── ENTRANCE ANIMATION ─── */
@keyframes authCardIn{from{opacity:0;transform:translateY(18px);}to{opacity:1;transform:translateY(0);}}
.auth-card{animation:authCardIn .5s cubic-bezier(.2,.8,.2,1);}

/* Trust strip */
.trust-strip{display:flex;align-items:center;justify-content:center;gap:18px;flex-wrap:wrap;max-width:560px;margin:18px auto 0;font-size:12px;color:rgba(255,255,255,.65);}
.trust-strip span{display:flex;align-items:center;gap:6px;}
.trust-strip i{color:#4CAF50;}

/* Progress steps */
.prog-bar{display:flex;align-items:center;padding:20px 36px 0;background:transparent;border-bottom:1px solid rgba(255,255,255,.1);}
.prog-step{display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0;}
.prog-dot{width:30px;height:30px;border-radius:50%;background:rgba(255,255,255,.08);color:rgba(255,255,255,.5);font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;border:2px solid rgba(255,255,255,.2);transition:.3s;}
.prog-dot.done{background:linear-gradient(135deg,#AEE24B,#22B573);color:#fff;border-color:#22B573;}
.prog-dot.active{background:#fff;color:#1B2F29;border-color:#4CAF50;box-shadow:0 0 0 4px rgba(76,175,80,.25);}
.prog-lbl{font-size:9px;color:rgba(255,255,255,.5);white-space:nowrap;padding-bottom:12px;}
.prog-lbl.active,.prog-lbl.done{color:#4CAF50;}
.prog-line{flex:1;height:2px;background:rgba(255,255,255,.15);min-width:14px;margin-bottom:16px;transition:.3s;}
.prog-line.done{background:linear-gradient(90deg,#AEE24B,#22B573);}

/* Card body */
.card-body{padding:28px 36px 36px;}
.step-panel{display:none;}
.step-panel.active{display:block;}

/* Alerts */
.alert{padding:12px 16px;border-radius:10px;font-size:13.5px;margin-bottom:18px;display:flex;align-items:flex-start;gap:10px;line-height:1.5;}
.alert.err{background:#F5E8E7;border:1px solid #f0c9c7;color:#9B3B37;}
.alert.ok{background:#E6F4EA;border:1px solid #c8e6c9;color:#1B5E20;}

/* Form elements */
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px;}
.form-group{margin-bottom:16px;}
.form-group label{display:block;font-size:12.5px;font-weight:600;color:rgba(255,255,255,.88);margin-bottom:6px;}
.form-group label .req{color:#4CAF50;margin-left:2px;}
.input-wrap{position:relative;}
.input-wrap .ico{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.6);font-size:14px;pointer-events:none;}
.input-wrap input,.input-wrap select,.input-wrap textarea{width:100%;padding:11px 13px 11px 38px;border:1.5px solid rgba(255,255,255,.5);border-radius:10px;font-family:'Poppins',sans-serif;font-size:14px;color:#fff;background:rgba(255,255,255,.02);outline:none;transition:.2s;appearance:none;color-scheme:dark;}
.input-wrap input::placeholder,.input-wrap textarea::placeholder{color:rgba(255,255,255,.65);}
.input-wrap.no-ico input,.input-wrap.no-ico select{padding-left:13px;}
.input-wrap input:focus,.input-wrap select:focus,.input-wrap textarea:focus{border-color:#4CAF50;background:rgba(255,255,255,.09);box-shadow:0 0 0 3px rgba(76,175,80,.2);}
.input-wrap input:-webkit-autofill,
.input-wrap input:-webkit-autofill:hover,
.input-wrap input:-webkit-autofill:focus{
    -webkit-text-fill-color:#fff;
    -webkit-box-shadow:0 0 0 1000px rgba(20,54,28,0.4) inset;
    transition:background-color 9999s ease-in-out 0s;
}
.input-wrap .eye{position:absolute;right:11px;top:50%;transform:translateY(-50%);background:none;border:none;cursor:pointer;color:rgba(255,255,255,.65);font-size:14px;}
.input-wrap select{background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center;padding-right:32px;}
.input-wrap select option{color:#26292B;}
.field-err{font-size:11.5px;color:#ff8a80;margin-top:4px;display:none;}

/* Custom dropdown — always opens BELOW the field */
.cs-box{position:relative;}
.cs-box select.cs-native-hidden{display:none;}
.cs-trigger{width:100%;padding:11px 13px;border:1.5px solid rgba(255,255,255,.5);border-radius:10px;font-family:'Poppins',sans-serif;font-size:14px;color:#fff;background:rgba(255,255,255,.02);cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:8px;transition:.2s;user-select:none;}
.cs-trigger:focus{outline:none;}
.cs-box.open .cs-trigger,.cs-trigger:focus{border-color:#4CAF50;background:rgba(255,255,255,.09);box-shadow:0 0 0 3px rgba(76,175,80,.2);}
.cs-trigger-text{overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
.cs-trigger-arrow{width:10px;height:10px;flex-shrink:0;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23ffffff' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:center;background-size:contain;transition:transform .2s ease;}
.cs-box.open .cs-trigger-arrow{transform:rotate(180deg);}
.cs-panel{position:absolute;top:calc(100% + 6px);left:0;right:0;background:#fff;border-radius:10px;box-shadow:0 14px 34px rgba(0,0,0,.32);max-height:230px;overflow-y:auto;z-index:80;display:none;border:1px solid rgba(0,0,0,.06);}
.cs-box.open .cs-panel{display:block;animation:csDrop .16s ease;}
@keyframes csDrop{from{opacity:0;transform:translateY(-6px);}to{opacity:1;transform:translateY(0);}}
.cs-option{padding:10px 14px;font-size:13.5px;color:#26292B;cursor:pointer;transition:background .12s,color .12s;}
.cs-option:hover{background:#E6F4EA;color:#1B5E20;}
.cs-option.active{background:#4CAF50;color:#fff;}
.cs-option.cs-placeholder{color:#8a8f92;}
.field-err.show{display:block;}

/* Pwd strength */
.pwd-bar{height:4px;background:rgba(255,255,255,.15);border-radius:4px;margin-top:6px;overflow:hidden;}
.pwd-fill{height:100%;border-radius:4px;width:0;transition:width .4s,background .4s;}
.pwd-lbl{font-size:11px;color:rgba(255,255,255,.6);margin-top:3px;}

/* Radio cards */
.radio-grid{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.r-card{border:1.5px solid rgba(255,255,255,.3);border-radius:10px;padding:11px 12px;cursor:pointer;transition:.2s;display:flex;align-items:center;gap:9px;font-size:13px;color:rgba(255,255,255,.8);background:rgba(255,255,255,.04);}
.r-card:has(input:checked){border-color:#4CAF50;background:rgba(76,175,80,.18);color:#fff;}
.r-card input{accent-color:#4CAF50;}
.r-icon{font-size:18px;}

/* OTP */
.otp-wrap{display:flex;gap:9px;justify-content:center;margin:12px 0 18px;}
.otp-box{width:46px;height:52px;text-align:center;font-size:22px;font-weight:800;font-family:'Poppins',sans-serif;border:1.5px solid rgba(255,255,255,.4);border-radius:10px;outline:none;color:#fff;background:rgba(255,255,255,.06);transition:.2s;}
.otp-box:focus{border-color:#4CAF50;background:rgba(255,255,255,.1);box-shadow:0 0 0 3px rgba(76,175,80,.2);}
.otp-info{text-align:center;font-size:13px;color:rgba(255,255,255,.7);margin-bottom:8px;}
.otp-info b{color:#4CAF50;}
.resend-row{text-align:center;margin-bottom:16px;}
.resend-btn{background:none;border:none;color:#4CAF50;font-size:13px;font-weight:600;cursor:pointer;font-family:'Poppins',sans-serif;}
.resend-btn:disabled{color:rgba(255,255,255,.4);cursor:not-allowed;}

/* Section title */
.sec-title{font-family:'Poppins',sans-serif;font-size:12px;font-weight:700;color:#4CAF50;letter-spacing:.6px;text-transform:uppercase;margin-bottom:14px;margin-top:4px;display:flex;align-items:center;gap:8px;}
.sec-title::after{content:'';flex:1;height:1px;background:rgba(255,255,255,.15);}

/* Checkbox */
.chk-row{display:flex;align-items:flex-start;gap:9px;font-size:13px;color:rgba(255,255,255,.75);cursor:pointer;margin-bottom:12px;line-height:1.5;}
.chk-row input{accent-color:#4CAF50;margin-top:2px;flex-shrink:0;}
.chk-row a{color:#4CAF50;font-weight:600;text-decoration:none;}

/* Buttons */
.btn-main{width:100%;padding:13px;background:linear-gradient(100deg,#AEE24B 0%, #22B573 100%);color:#fff;border:none;border-radius:12px;font-family:'Poppins',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:.25s cubic-bezier(.34,1.56,.64,1);box-shadow:0 10px 24px rgba(34,181,115,.35);}
.btn-main:hover{transform:translateY(-2px);filter:brightness(1.06);box-shadow:0 14px 30px rgba(34,181,115,.45);}
.btn-row{display:flex;gap:12px;margin-top:4px;}
.btn-back{padding:12px 22px;border:1.5px solid rgba(255,255,255,.35);border-radius:12px;background:rgba(255,255,255,.05);color:#fff;font-family:'Poppins',sans-serif;font-size:14px;font-weight:600;cursor:pointer;transition:.2s;white-space:nowrap;}
.btn-back:hover{background:rgba(255,255,255,.12);}
.btn-row .btn-main{flex:1;}

/* Success */
.success-box{text-align:center;padding:8px 0 4px;}
.s-icon{width:72px;height:72px;background:linear-gradient(135deg,#AEE24B,#22B573);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:32px;margin:0 auto 18px;box-shadow:0 8px 24px rgba(34,181,115,.4);animation:popIn .5s cubic-bezier(.34,1.56,.64,1) both;color:#fff;}
@keyframes popIn{from{transform:scale(0);opacity:0}to{transform:scale(1);opacity:1}}
.success-box h3{font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;color:#fff;margin-bottom:8px;}
.success-box p{font-size:13.5px;color:rgba(255,255,255,.75);margin-bottom:20px;line-height:1.6;}
.badge-row{display:flex;gap:8px;flex-wrap:wrap;justify-content:center;margin-bottom:18px;}
.badge{padding:5px 14px;border-radius:100px;font-size:12px;font-weight:700;display:inline-flex;align-items:center;gap:5px;background:rgba(76,175,80,.18);color:#fff;border:1px solid rgba(76,175,80,.4);}

/* Login link */
.login-link{text-align:center;font-size:13.5px;color:rgba(255,255,255,.75);margin-top:18px;}
.login-link a{color:#fff;font-weight:700;text-decoration:none;}

/* Footer */
.page-footer{text-align:center;padding:20px;font-size:12px;color:rgba(255,255,255,.5);}
.page-footer a{color:#4CAF50;text-decoration:none;}

@media(max-width:520px){
  .card-body{padding:20px 20px 28px;}
  .card-head{padding:26px 22px 20px;}
  .form-row{grid-template-columns:1fr;}
  .radio-grid{grid-template-columns:1fr;}
  .otp-box{width:40px;height:46px;font-size:18px;}
  .prog-bar{padding:16px 16px 0;}
}
</style>
<link rel="stylesheet" href="../assets/css/responsive.css">
<link rel="stylesheet" href="../assets/css/otp-verify.css">
<script src="../assets/js/otp-resend.js"></script>
</head>
<body>

<div class="auth-shell">
  <div class="bg-orb o1"></div>
  <div class="bg-orb o2"></div>
  <div class="bg-orb o3"></div>
  <div class="bg-orb o4"></div>
  <div class="auth-card">

    <!-- Card Header -->
    <div class="card-head">
      <div class="card-head-top">
        <a href="../index.php" class="auth-logo"><img src="../assets/images/agricart-logo.png?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/images/agricart-logo.png') ?: time(); ?>" alt="AgriCart" style="width:58px;height:58px;object-fit:contain;border-radius:50%;flex-shrink:0"><span class="auth-logo-text">Agri<span>Cart</span></span></a>
        <div style="background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.3);border-radius:100px;padding:5px 14px;font-size:12px;font-weight:600;color:#fff;" data-t="badge">Free Registration</div>
      </div>
      <h2 data-t="title">Join AgriCart!</h2>
      <p data-t="subtitle">Connect with 50,000+ farmers across Maharashtra</p>
    </div>

    <!-- Progress Bar -->
    <div class="prog-bar">
      <?php
      $steps = [['1','Account','stepAccount'],['2','Verify','stepVerify'],['3','Profile','stepProfile']];
      foreach ($steps as $i => [$n, $lbl, $key]):
        $sn = $i + 1;
        $cls = $sn < $step ? 'done' : ($sn == $step ? 'active' : '');
      ?>
        <div class="prog-step">
          <div class="prog-dot <?= $cls ?>"><?= $sn < $step ? '✓' : $n ?></div>
          <div class="prog-lbl <?= $cls ?>" data-t="<?= $key ?>"><?= $lbl ?></div>
        </div>
        <?php if ($sn < 3): ?>
          <div class="prog-line <?= $sn < $step ? 'done' : '' ?>"></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>

    <div class="card-body">

      <?php if (!empty($error)): ?>
        <div class="alert err"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
      <?php endif; ?>
      <?php if (!empty($success)): ?>
        <div class="alert ok" id="otpAlert"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <!-- ══ STEP 1: Account Info ══ -->
      <div class="step-panel <?= $step == 1 ? 'active' : '' ?>">
        <form method="POST">
          <input type="hidden" name="reg_step" value="1">
          <input type="hidden" name="action" value="send_otp">
          <input type="hidden" name="lang" class="reg-lang-field" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
          <?php echo csrf_field(); ?>

          <div class="sec-title" data-t="secBasic">👤 Basic Information</div>
          <div class="form-row">
            <div class="form-group">
              <label data-t="lblFullName">Full Name <span class="req">*</span></label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-user"></i>
                <input type="text" name="full_name" data-ph="phFullName" placeholder="Your full name"
                  value="<?= htmlspecialchars($_POST['full_name'] ?? $_SESSION['google_prefill']['full_name'] ?? '') ?>" required>
              </div>
            </div>
            <div class="form-group">
              <label data-t="lblMobile">Mobile Number <span class="req">*</span></label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-mobile-screen"></i>
                <input type="tel" name="mobile" data-ph="phMobile" placeholder="10-digit mobile"
                  maxlength="10" oninput="this.value=this.value.replace(/\D/,'')"
                  value="<?= htmlspecialchars($_POST['mobile'] ?? '') ?>" required>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label data-t="lblEmail">Email Address <span class="req">*</span></label>
            <div class="input-wrap">
              <i class="ico fa-solid fa-envelope"></i>
              <input type="email" name="email" placeholder="your@email.com" required
                value="<?= htmlspecialchars($_POST['email'] ?? $_SESSION['google_prefill']['email'] ?? '') ?>">
            </div>
            <div class="field-err show" style="color:rgba(255,255,255,.55);font-size:11px;" data-t="emailHelp">A verification code will be sent to this email — please double-check it.</div>
          </div>

          <div class="sec-title" data-t="secPassword">🔒 Set Password</div>
          <div class="form-row">
            <div class="form-group">
              <label data-t="lblPassword">Password <span class="req">*</span></label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-lock"></i>
                <input type="password" name="password" id="regPwd" class="pwd-field"
                  data-ph="phStrongPassword" placeholder="Strong password" required>
                <button type="button" class="eye" onclick="togglePwd('regPwd',this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <div class="pwd-bar"><div class="pwd-fill" id="pwdFill"></div></div>
              <div class="pwd-lbl" id="pwdLbl"></div>
            </div>
            <div class="form-group">
              <label data-t="lblConfirmPassword">Confirm Password <span class="req">*</span></label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-lock"></i>
                <input type="password" name="cpassword" id="regCpwd"
                  data-ph="phRepeatPassword" placeholder="Repeat password" required>
                <button type="button" class="eye" onclick="togglePwd('regCpwd',this)">
                  <i class="fa-solid fa-eye"></i>
                </button>
              </div>
              <span class="field-err" id="cpwdErr" data-t="errPwdMismatch">Passwords do not match</span>
            </div>
          </div>

          <button type="submit" class="btn-main">
            <i class="fa-solid fa-paper-plane"></i> <span data-t="btnSendOtp">Send OTP</span>
          </button>
        </form>
        <div class="login-link" style="margin-top:16px;">
          <span data-t="haveAccount">Already have an account?</span> <a href="login.php" data-t="loginLink">Login →</a>
        </div>
      </div>

      <!-- ══ STEP 2: OTP Verify (EMAIL — AgriCart never sends OTPs by SMS) ══ -->
      <div class="step-panel <?= $step == 2 ? 'active' : '' ?>" id="step2">

        <div class="otp-sent-banner">
          <i class="fa-solid fa-circle-check"></i>
          <span><?= htmlspecialchars(agri_otp_t('otp_sent_email', $lang), ENT_QUOTES, 'UTF-8') ?></span>
        </div>

        <?php if (env('APP_ENV', 'production') === 'local' && !agri_smtp_configured()): ?>
        <div class="otp-dev-banner">
          <i class="fa-solid fa-flask"></i>
          <span><?= htmlspecialchars(agri_otp_t('dev_mode_notice', $lang), ENT_QUOTES, 'UTF-8') ?></span>
        </div>
        <?php endif; ?>

        <form method="POST">
          <input type="hidden" name="reg_step" value="2">
          <input type="hidden" name="action" value="verify_otp">
          <input type="hidden" name="lang" class="reg-lang-field" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
          <?php echo csrf_field(); ?>

          <div class="sec-title" data-t="secVerify"><?= htmlspecialchars(agri_otp_t('lbl_email_verification', $lang), ENT_QUOTES, 'UTF-8') ?></div>
          <p class="otp-info" style="text-align:center;margin-bottom:14px;">
            <span data-t="otpSentTo"><?= htmlspecialchars(agri_otp_t('lbl_otp_sent_to', $lang), ENT_QUOTES, 'UTF-8') ?></span>
            <span class="otp-email-badge"><i class="fa-solid fa-envelope"></i> <?= htmlspecialchars(agri_mask_email($_SESSION['reg_data']['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
          </p>
          <div class="otp-wrap">
            <input class="otp-box" type="text" name="otp1" maxlength="1" inputmode="numeric" autofocus>
            <input class="otp-box" type="text" name="otp2" maxlength="1" inputmode="numeric">
            <input class="otp-box" type="text" name="otp3" maxlength="1" inputmode="numeric">
            <input class="otp-box" type="text" name="otp4" maxlength="1" inputmode="numeric">
            <input class="otp-box" type="text" name="otp5" maxlength="1" inputmode="numeric">
            <input class="otp-box" type="text" name="otp6" maxlength="1" inputmode="numeric">
          </div>
          <div class="resend-row">
            <button type="button" class="resend-btn" id="resendBtn2" disabled
              data-cooldown="<?= (int) AGRI_OTP_RESEND_COOLDOWN ?>">
              <span data-t="resend"><?= htmlspecialchars(agri_otp_t('lbl_resend_otp', $lang), ENT_QUOTES, 'UTF-8') ?></span> (<span id="resendTimer2">30</span>s)
            </button>
          </div>
          <div id="otpAlert" class="alert ok" style="display:none;justify-content:center;"></div>
          <div class="btn-row">
            <a href="register.php" class="btn-back" data-t="back"><?= htmlspecialchars(agri_otp_t('lbl_back', $lang), ENT_QUOTES, 'UTF-8') ?></a>
            <button type="submit" class="btn-main">
              <i class="fa-solid fa-shield-halved"></i> <span data-t="btnVerifyNext"><?= htmlspecialchars(agri_otp_t('lbl_verify_continue', $lang), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
          </div>
        </form>

        <div class="change-email-row">
          <form method="POST" style="display:inline;">
            <input type="hidden" name="reg_step" value="2">
            <input type="hidden" name="action" value="change_email">
            <input type="hidden" name="lang" class="reg-lang-field" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="full_name" value="<?= htmlspecialchars($_SESSION['reg_data']['full_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="mobile" value="<?= htmlspecialchars($_SESSION['reg_data']['mobile'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
            <?php echo csrf_field(); ?>
            <button type="submit" class="change-email-link">
              <i class="fa-solid fa-pen"></i> <span data-t="changeEmail"><?= htmlspecialchars(agri_otp_t('lbl_change_email', $lang), ENT_QUOTES, 'UTF-8') ?></span>
            </button>
          </form>
        </div>
      </div>

      <!-- ══ STEP 3: Profile Details ══ -->
      <div class="step-panel <?= $step == 3 ? 'active' : '' ?>">
        <form method="POST">
          <input type="hidden" name="reg_step" value="3">
          <input type="hidden" name="action" value="complete_register">
          <input type="hidden" name="lang" class="reg-lang-field" value="<?= htmlspecialchars($lang, ENT_QUOTES, 'UTF-8') ?>">
          <?php echo csrf_field(); ?>

          <div class="sec-title" data-t="secProfile">🧑‍🌾 Farmer Profile</div>
          <div class="form-group">
            <label data-t="lblAccType">Account Type <span class="req">*</span></label>
            <div class="radio-grid">
              <label class="r-card"><input type="radio" name="farmer_type" value="Individual Farmer" checked><span class="r-icon">🧑‍🌾</span> <span data-t="typeFarmer">Individual Farmer</span></label>
              <label class="r-card"><input type="radio" name="farmer_type" value="Trader"><span class="r-icon">🏪</span> <span data-t="typeTrader">Trader</span></label>
              <label class="r-card"><input type="radio" name="farmer_type" value="Buyer"><span class="r-icon">🛒</span> <span data-t="typeBuyer">Buyer</span></label>
              <label class="r-card"><input type="radio" name="farmer_type" value="FPO"><span class="r-icon">🤝</span> <span data-t="typeFpo">FPO</span></label>
            </div>
          </div>

          <div class="sec-title" data-t="secLocation">📍 Location</div>
          <div class="form-row">
            <div class="form-group">
              <label data-t="lblDistrict">District</label>
              <div class="input-wrap no-ico">
                <select name="district">
                  <option value="" data-t="selectDistrict">Select District</option>
                  <?php
                  $districts = ['Pune','Nashik','Ahmednagar','Aurangabad','Solapur','Kolhapur','Satara','Sangli','Nagpur','Amravati','Latur','Nanded','Palghar','Thane','Raigad','Ratnagiri','Sindhudurg','Jalgaon','Dhule','Nandurbar','Buldhana','Akola','Washim','Yavatmal','Wardha','Bhandara','Gondia','Chandrapur','Gadchiroli','Osmanabad','Hingoli','Parbhani','Jalna','Beed','Nandurbar'];
                  foreach ($districts as $d) echo "<option value='$d'>$d</option>";
                  ?>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label data-t="lblTaluka">Taluka</label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-location-dot"></i>
                <input type="text" name="taluka" data-ph="phTaluka" placeholder="Taluka name">
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label data-t="lblVillage">Village / Town</label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-house"></i>
                <input type="text" name="village" data-ph="phVillage" placeholder="Village / Town">
              </div>
            </div>
            <div class="form-group">
              <label data-t="lblPincode">PIN Code <span class="req">*</span></label>
              <div class="input-wrap">
                <i class="ico fa-solid fa-map-pin"></i>
                <input type="text" name="pincode" id="regPincode" data-ph="phPincode" placeholder="6-digit PIN code" maxlength="6" inputmode="numeric" pattern="\d{6}" required>
              </div>
            </div>
          </div>

          <div class="sec-title" data-t="secCrop">🌾 Primary Crop</div>
          <div class="form-group">
            <div class="input-wrap no-ico">
              <select name="primary_crop">
                <option value="" data-t="selectCrop">Select Crop (Optional)</option>
                <?php
                $crops = ['Wheat','Rice','Sugarcane','Cotton','Soybean','Onion','Tomato','Grapes','Pomegranate','Turmeric','Jowar','Bajra','Tur Dal','Chickpea'];
                foreach ($crops as $c) echo "<option value='$c'>$c</option>";
                ?>
              </select>
            </div>
          </div>

          <label class="chk-row">
            <input type="checkbox" required>
            <span data-th="terms">I agree to the <a href="terms-conditions.php" target="_blank">Terms &amp; Conditions</a> and <a href="privacy-policy.php" target="_blank">Privacy Policy</a></span> <span style="color:#ef4444">*</span>
          </label>
          <label class="chk-row">
            <input type="checkbox">
            <span data-t="alerts">I am willing to receive market rates, schemes, and weather alerts</span>
          </label>

          <div class="btn-row" style="margin-top:6px;">
            <button type="button" class="btn-back" onclick="history.back()" data-t="back">← Back</button>
            <button type="submit" class="btn-main">
              🌱 <span data-t="btnCreateAccount">Create Account</span>
            </button>
          </div>
        </form>
      </div>

    </div>
  </div>

  <div class="trust-strip">
    <span><i class="fa-solid fa-shield-halved"></i> <span data-t="trustSecure">SSL Secured</span></span>
    <span><i class="fa-solid fa-users"></i> <span data-t="trustFarmers">Trusted by 50,000+ Farmers</span></span>
    <span><i class="fa-solid fa-headset"></i> <span data-t="trustHelpline">24×7 Helpline: 1800-419-8888</span></span>
  </div>
</div>

<script>
// Custom dropdown (District / Primary Crop) — panel always opens BELOW the field
(function(){
  function buildCustomSelect(select) {
    const wrap = document.createElement('div');
    wrap.className = 'cs-box';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);
    select.classList.add('cs-native-hidden');

    const firstOpt = select.options[0];
    const trigger = document.createElement('div');
    trigger.className = 'cs-trigger';
    trigger.tabIndex = 0;
    trigger.innerHTML = '<span class="cs-trigger-text"' +
      (firstOpt.hasAttribute('data-t') ? ' data-t="' + firstOpt.getAttribute('data-t') + '"' : '') +
      '>' + firstOpt.textContent + '</span><span class="cs-trigger-arrow"></span>';
    wrap.appendChild(trigger);

    const panel = document.createElement('div');
    panel.className = 'cs-panel';
    const triggerText = trigger.querySelector('.cs-trigger-text');

    Array.from(select.options).forEach(opt => {
      const item = document.createElement('div');
      item.className = 'cs-option' + (opt.value === '' ? ' cs-placeholder' : '');
      item.textContent = opt.textContent;
      if (opt.value === select.value) item.classList.add('active');
      item.addEventListener('click', () => {
        select.value = opt.value;
        triggerText.textContent = opt.textContent;
        triggerText.removeAttribute('data-t');
        panel.querySelectorAll('.cs-option').forEach(o => o.classList.remove('active'));
        item.classList.add('active');
        wrap.classList.remove('open');
        select.dispatchEvent(new Event('change'));
      });
      panel.appendChild(item);
    });
    wrap.appendChild(panel);

    function closeAllOthers() {
      document.querySelectorAll('.cs-box.open').forEach(b => { if (b !== wrap) b.classList.remove('open'); });
    }
    trigger.addEventListener('click', () => {
      closeAllOthers();
      wrap.classList.toggle('open');
    });
    trigger.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); trigger.click(); }
      if (e.key === 'Escape') wrap.classList.remove('open');
    });
    document.addEventListener('click', e => {
      if (!wrap.contains(e.target)) wrap.classList.remove('open');
    });
  }

  document.querySelectorAll('select[name="district"], select[name="primary_crop"]').forEach(buildCustomSelect);
})();

// Password toggle
function togglePwd(id, btn) {
  const inp = document.getElementById(id);
  inp.type = inp.type === 'password' ? 'text' : 'password';
  btn.innerHTML = inp.type === 'password'
    ? '<i class="fa-solid fa-eye"></i>'
    : '<i class="fa-solid fa-eye-slash"></i>';
}

// Password strength
const pwdInput = document.getElementById('regPwd');
const pwdFill  = document.getElementById('pwdFill');
const pwdLbl   = document.getElementById('pwdLbl');
if (pwdInput) {
  pwdInput.addEventListener('input', () => {
    const v = pwdInput.value;
    let s = 0;
    if (v.length >= 8) s++;
    if (v.length >= 12) s++;
    if (/[A-Z]/.test(v)) s++;
    if (/[0-9]/.test(v)) s++;
    if (/[^A-Za-z0-9]/.test(v)) s++;
    const levels = [
      {w:'0%',c:'',l:''},
      {w:'20%',c:'#ef4444',l:'Very Weak'},
      {w:'40%',c:'#f97316',l:'Weak'},
      {w:'60%',c:'#eab308',l:'Okay'},
      {w:'80%',c:'#22c55e',l:'Strong'},
      {w:'100%',c:'#16a34a',l:'Very Strong'},
    ];
    const lv = levels[s] || levels[0];
    pwdFill.style.width = lv.w;
    pwdFill.style.background = lv.c;
    pwdLbl.textContent = lv.l;
    pwdLbl.style.color = lv.c;
  });
}

// Confirm password check
const cpwd    = document.getElementById('regCpwd');
const cpwdErr = document.getElementById('cpwdErr');
if (cpwd) {
  cpwd.addEventListener('input', () => {
    if (pwdInput && cpwd.value && pwdInput.value !== cpwd.value) {
      cpwdErr.classList.add('show');
    } else {
      cpwdErr.classList.remove('show');
    }
  });
}

// OTP auto-advance
document.querySelectorAll('.otp-box').forEach((box, i, all) => {
  box.addEventListener('input', () => {
    box.value = box.value.replace(/\D/, '').slice(-1);
    if (box.value && i < all.length - 1) all[i+1].focus();
  });
  box.addEventListener('keydown', e => {
    if (e.key === 'Backspace' && !box.value && i > 0) all[i-1].focus();
  });
  box.addEventListener('paste', e => {
    e.preventDefault();
    const d = (e.clipboardData || window.clipboardData)
      .getData('text').replace(/\D/g,'').slice(0,6);
    d.split('').forEach((ch,j) => { if(all[j]) all[j].value = ch; });
  });
});

// Resend OTP — real server call. The countdown here is only a UI
// convenience; the actual cooldown/limit/rate-limit are all enforced
// server-side in register.php (action=resend_otp), see assets/js/otp-resend.js.
if (window.AgriOtpResend) {
  var resendBtnEl = document.getElementById('resendBtn2');
  AgriOtpResend.init({
    buttonId: 'resendBtn2',
    timerId: 'resendTimer2',
    alertId: 'otpAlert',
    csrfSelector: '#step2 input[name="csrf_token"]',
    cooldownSeconds: resendBtnEl ? (parseInt(resendBtnEl.getAttribute('data-cooldown'), 10) || 30) : 30,
    postUrl: window.location.href,
    extraFields: { lang: (localStorage.getItem('agri_lang') || <?= json_encode($lang) ?>) },
  });
}

// ── AUTH PAGE TRANSLATIONS ──
(function(){
  var _T = {
    mr: {
      badge:'Free Registration', title:'AgriCart वर जोडा!', subtitle:'Maharashtra च्या 50,000+ शेतकऱ्यांशी connect व्हा',
      stepAccount:'Account', stepVerify:'Verify', stepProfile:'Profile',
      secBasic:'👤 Basic Information', lblFullName:'Full Name *', phFullName:'तुमचे पूर्ण नाव',
      lblMobile:'Mobile Number *', phMobile:'10-digit mobile', lblEmail:'Email Address *', emailHelp:'Verification code या ईमेलवर पाठवला जाईल — कृपया तपासा.',
      secPassword:'🔒 Password Set करा', lblPassword:'Password *', phStrongPassword:'Strong password',
      lblConfirmPassword:'Confirm Password *', phRepeatPassword:'Repeat password',
      errPwdMismatch:'Passwords जुळत नाहीत', btnSendOtp:'OTP पाठवा',
      haveAccount:'आधीच account आहे?', loginLink:'Login करा →',
      secVerify:'📧 ईमेल सत्यापन', otpSentTo:'OTP पाठवला:', otpOn:'',
      resend:'Resend OTP', back:'← मागे', btnVerifyNext:'Verify & पुढे जा', changeEmail:'ईमेल पत्ता बदला',
      secProfile:'🧑‍🌾 Farmer Profile', lblAccType:'Account Type *',
      typeFarmer:'Individual Farmer', typeTrader:'Trader', typeBuyer:'Buyer', typeFpo:'FPO',
      secLocation:'📍 Location', lblDistrict:'District', selectDistrict:'Select District',
      lblTaluka:'Taluka', phTaluka:'Taluka नाव', lblVillage:'Village / Town', phVillage:'गाव / शहर',
      lblPincode:'पिन कोड *', phPincode:'6-अंकी पिन कोड',
      secCrop:'🌾 Primary Crop', selectCrop:'Select Crop (Optional)',
      terms:'मी <a href="terms-conditions.php" target="_blank">Terms & Conditions</a> आणि <a href="privacy-policy.php" target="_blank">Privacy Policy</a> ला मान्यता देतो/देते',
      alerts:'Market rates, schemes, आणि weather alerts receive करण्यास तयार आहे',
      btnCreateAccount:'Account बनवा',
      trustSecure:'SSL Secured', trustFarmers:'50,000+ शेतकऱ्यांचा विश्वास', trustHelpline:'24×7 Helpline: 1800-419-8888'
    },
    hi: {
      badge:'मुफ़्त पंजीकरण', title:'AgriCart से जुड़ें!', subtitle:'महाराष्ट्र के 50,000+ किसानों से connect हों',
      stepAccount:'Account', stepVerify:'Verify', stepProfile:'Profile',
      secBasic:'👤 मूल जानकारी', lblFullName:'पूरा नाम *', phFullName:'अपना पूरा नाम',
      lblMobile:'मोबाइल नंबर *', phMobile:'10-अंकों का मोबाइल', lblEmail:'ईमेल पता *', emailHelp:'सत्यापन कोड इस ईमेल पर भेजा जाएगा — कृपया इसे दोबारा जांचें.',
      secPassword:'🔒 Password सेट करें', lblPassword:'Password *', phStrongPassword:'Strong password',
      lblConfirmPassword:'Password की पुष्टि करें *', phRepeatPassword:'Password दोबारा डालें',
      errPwdMismatch:'Passwords मेल नहीं खाते', btnSendOtp:'OTP भेजें',
      haveAccount:'पहले से account है?', loginLink:'Login करें →',
      secVerify:'📧 ईमेल सत्यापन', otpSentTo:'OTP भेजा गया:', otpOn:'',
      resend:'Resend OTP', back:'← वापस', btnVerifyNext:'Verify करें & आगे बढ़ें', changeEmail:'ईमेल पता बदलें',
      secProfile:'🧑‍🌾 किसान प्रोफ़ाइल', lblAccType:'Account Type *',
      typeFarmer:'व्यक्तिगत किसान', typeTrader:'व्यापारी', typeBuyer:'खरीदार', typeFpo:'FPO',
      secLocation:'📍 स्थान', lblDistrict:'जिला', selectDistrict:'जिला चुनें',
      lblTaluka:'तालुका', phTaluka:'तालुका नाम', lblVillage:'गांव / शहर', phVillage:'गांव / शहर',
      lblPincode:'पिन कोड *', phPincode:'6-अंकों का पिन कोड',
      secCrop:'🌾 मुख्य फसल', selectCrop:'फसल चुनें (वैकल्पिक)',
      terms:'मैं <a href="terms-conditions.php" target="_blank">Terms & Conditions</a> और <a href="privacy-policy.php" target="_blank">Privacy Policy</a> को स्वीकार करता/करती हूं',
      alerts:'Market rates, योजनाएं, और weather alerts प्राप्त करने के लिए तैयार हूं',
      btnCreateAccount:'Account बनाएं',
      trustSecure:'SSL सुरक्षित', trustFarmers:'50,000+ किसानों का भरोसा', trustHelpline:'24×7 हेल्पलाइन: 1800-419-8888'
    },
    en: {
      badge:'Free Registration', title:'Join AgriCart!', subtitle:'Connect with 50,000+ farmers across Maharashtra',
      stepAccount:'Account', stepVerify:'Verify', stepProfile:'Profile',
      secBasic:'👤 Basic Information', lblFullName:'Full Name *', phFullName:'Your full name',
      lblMobile:'Mobile Number *', phMobile:'10-digit mobile', lblEmail:'Email Address *', emailHelp:'A verification code will be sent to this email — please double-check it.',
      secPassword:'🔒 Set Password', lblPassword:'Password *', phStrongPassword:'Strong password',
      lblConfirmPassword:'Confirm Password *', phRepeatPassword:'Repeat password',
      errPwdMismatch:'Passwords do not match', btnSendOtp:'Send OTP',
      haveAccount:'Already have an account?', loginLink:'Login →',
      secVerify:'📧 Email Verification', otpSentTo:'OTP sent to:', otpOn:'',
      resend:'Resend OTP', back:'← Back', btnVerifyNext:'Verify & Continue', changeEmail:'Change Email Address',
      secProfile:'🧑‍🌾 Farmer Profile', lblAccType:'Account Type *',
      typeFarmer:'Individual Farmer', typeTrader:'Trader', typeBuyer:'Buyer', typeFpo:'FPO',
      secLocation:'📍 Location', lblDistrict:'District', selectDistrict:'Select District',
      lblTaluka:'Taluka', phTaluka:'Taluka name', lblVillage:'Village / Town', phVillage:'Village / Town',
      lblPincode:'PIN Code *', phPincode:'6-digit PIN code',
      secCrop:'🌾 Primary Crop', selectCrop:'Select Crop (Optional)',
      terms:'I agree to the <a href="terms-conditions.php" target="_blank">Terms & Conditions</a> and <a href="privacy-policy.php" target="_blank">Privacy Policy</a>',
      alerts:'I am willing to receive market rates, schemes, and weather alerts',
      btnCreateAccount:'Create Account',
      trustSecure:'SSL Secured', trustFarmers:'Trusted by 50,000+ Farmers', trustHelpline:'24×7 Helpline: 1800-419-8888'
    }
  };

  function applyLang(lang) {
    var t = _T[lang] || _T.en;
    document.querySelectorAll('[data-t]').forEach(function(el){
      var k = el.getAttribute('data-t');
      if (t[k] !== undefined) el.textContent = t[k];
    });
    document.querySelectorAll('[data-th]').forEach(function(el){
      var k = el.getAttribute('data-th');
      if (t[k] !== undefined) el.innerHTML = t[k];
    });
    document.querySelectorAll('[data-ph]').forEach(function(el){
      var k = el.getAttribute('data-ph');
      if (t[k] !== undefined) el.placeholder = t[k];
    });
  }

  function syncLangFields(lang) {
    document.querySelectorAll('.reg-lang-field').forEach(function(el){ el.value = lang; });
  }

  window.setLang = applyLang;
  window.pageLanguageCallback = function(lang){ applyLang(lang); syncLangFields(lang); };

  document.addEventListener('DOMContentLoaded', function(){
    // Reflect whatever language this request was actually rendered in
    // (defaults to English on a brand-new visit, same as before; carries
    // forward mr/hi across OTP steps once the person picks one via the
    // header language dropdown).
    var initialLang = <?= json_encode($lang) ?>;
    applyLang(initialLang);
    syncLangFields(initialLang);
  });
})();
</script>
</body>
</html>