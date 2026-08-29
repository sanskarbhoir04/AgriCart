<?php
// =====================================================
// AgriCart — Contact Us Page (final — contact-only sections)
// Location: /pages/contact.php
// Sections: curved/cloud hero -> About/Support -> 4 contact-info cards
//           -> form + working hours + map.
// Header/Footer, global nav, $base_path system, agri_lang system and the
// database connection file are all UNCHANGED — only this page's own markup,
// styles, PHP logic and inline script were touched.
// Trilingual EN/HI/MR wired into the site's existing agri_lang system.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

$SITE_HOTLINE = '1800-419-8888';
$SITE_ADDRESS = 'St. John High School Road, Palghar, Maharashtra – 401404';
$SITE_EMAIL   = 'support@agricart.in';
$SITE_SUPPORT = '1800-123-4567';
$MAP_EMBED_SRC = 'https://www.google.com/maps?q=' . urlencode('St John Palghar, Maharashtra 401404') . '&output=embed';

// ---- Contact-page stats strip (real numbers where available; safe fallback
// to 0 / 'New' on any DB error so the page never breaks) ----
$stat_resolved      = 0;
$stat_rating        = 0;
$stat_reviews_count = 0;
if (isset($conn) && $conn instanceof mysqli) {
    try {
        $res = @$conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status <> 'new'");
        if ($res && ($row = $res->fetch_assoc())) { $stat_resolved = (int)$row['c']; }
    } catch (\Throwable $e) { /* table missing — keep 0 */ }

    try {
        $res = @$conn->query("SELECT AVG(rating) a, COUNT(*) c FROM reviews");
        if ($res && ($row = $res->fetch_assoc())) {
            if ($row['a'] !== null) { $stat_rating = round((float)$row['a'], 1); }
            $stat_reviews_count = (int)$row['c'];
        }
    } catch (\Throwable $e) { /* table missing — keep 0 */ }
}
function cu_fmt_stat($n) { return number_format($n) . '+'; }

/**
 * Normalise + validate an Indian mobile number.
 * Accepts optional +91 / 91 / 0 prefixes, strips formatting characters,
 * and returns the clean 10-digit number (starting 6-9) or null if invalid.
 */
function cu_normalize_indian_mobile(string $raw): ?string {
    $digits = preg_replace('/[^0-9]/', '', $raw);
    if ($digits === null) { return null; }
    if (strlen($digits) === 12 && substr($digits, 0, 2) === '91') {
        $digits = substr($digits, 2);
    } elseif (strlen($digits) === 11 && substr($digits, 0, 1) === '0') {
        $digits = substr($digits, 1);
    }
    if (preg_match('/^[6-9][0-9]{9}$/', $digits)) {
        return $digits;
    }
    return null;
}

$ALLOWED_SUBJECTS   = ['order', 'rental', 'advisory', 'other'];
$RATE_LIMIT_SECONDS = 45;

// ---- CSRF token (kept stable across GET reloads, rotated after a
// successful submit) ----
if (empty($_SESSION['cu_csrf_token'])) {
    $_SESSION['cu_csrf_token'] = bin2hex(random_bytes(32));
}

$formSuccess    = false;
$formErrorCode  = ''; // one of: session_expired, duplicate_submit, rate_limited, invalid_fields, save_failed
$ticketNo       = '';
$old            = ['name' => '', 'email' => '', 'phone' => '', 'subject' => '', 'message' => ''];
$fieldErrors    = []; // e.g. ['name' => true, 'email' => true] — which fields to highlight

$selfPath = strtok($_SERVER['PHP_SELF'], '?');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cu_submit'])) {

    // Capture + trim the raw values first so every error branch below can
    // redisplay exactly what the visitor typed.
    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = trim((string)($_POST['email'] ?? ''));
    $phone   = trim((string)($_POST['phone'] ?? ''));
    $subject = trim((string)($_POST['subject'] ?? ''));
    $message = trim((string)($_POST['message'] ?? ''));

    $name    = mb_substr($name, 0, 100);
    $email   = mb_substr($email, 0, 150);
    $phone   = mb_substr($phone, 0, 20);
    $message = mb_substr($message, 0, 2000);

    $old = compact('name', 'email', 'phone', 'subject', 'message');

    // ---- CSRF check ----
    $postedToken = (string)($_POST['csrf_token'] ?? '');
    if ($postedToken === '' || !hash_equals($_SESSION['cu_csrf_token'], $postedToken)) {
        $_SESSION['cu_form_error_code'] = 'session_expired';
        header('Location: ' . $selfPath . '#cu-form-section');
        exit;
    }

    // ---- Honeypot spam trap: real users never fill this hidden field ----
    $honeypot = trim((string)($_POST['cu_website'] ?? ''));
    if ($honeypot !== '') {
        // Silently drop the submission — no DB write, no error shown, just
        // redirect as if nothing happened so bots gain no feedback signal.
        header('Location: ' . $selfPath . '#cu-form-section');
        exit;
    }

    // ---- Repeated-submission guard (one-time form token) ----
    $formToken = (string)($_POST['cu_form_token'] ?? '');
    if ($formToken === '' || (!empty($_SESSION['cu_last_form_token']) && hash_equals($_SESSION['cu_last_form_token'], $formToken))) {
        $_SESSION['cu_form_error_code'] = 'duplicate_submit';
        $_SESSION['cu_old'] = $old;
        header('Location: ' . $selfPath . '#cu-form-section');
        exit;
    }

    // ---- Rate limiting: one successful submission per session every
    // $RATE_LIMIT_SECONDS, on top of (not instead of) CSRF/honeypot/duplicate
    // protection ----
    if (!empty($_SESSION['cu_last_submit_time']) && (time() - (int)$_SESSION['cu_last_submit_time']) < $RATE_LIMIT_SECONDS) {
        $_SESSION['cu_form_error_code'] = 'rate_limited';
        $_SESSION['cu_old'] = $old;
        header('Location: ' . $selfPath . '#cu-form-section');
        exit;
    }

    // ---- Field validation ----
    if ($name === '' || mb_strlen($name) < 2) {
        $fieldErrors['name'] = true;
    }

    if ($email === '' || mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $fieldErrors['email'] = true;
    }

    $normalizedPhone = null;
    if ($phone !== '') {
        $normalizedPhone = cu_normalize_indian_mobile($phone);
        if ($normalizedPhone === null) {
            $fieldErrors['phone'] = true;
        }
    }

    if (!in_array($subject, $ALLOWED_SUBJECTS, true)) {
        $fieldErrors['subject'] = true;
    }

    if ($message === '' || mb_strlen($message) < 10) {
        $fieldErrors['message'] = true;
    }

    if (!empty($fieldErrors)) {
        $_SESSION['cu_form_error_code'] = 'invalid_fields';
        $_SESSION['cu_field_errors']    = $fieldErrors;
        $_SESSION['cu_old']             = $old;
        header('Location: ' . $selfPath . '#cu-form-section');
        exit;
    }

    $phoneToSave = $normalizedPhone ?? '';

    // Cryptographically strong ticket number: date + random bytes.
    $ticketNo = 'AGC-' . date('Y') . '-' . strtoupper(bin2hex(random_bytes(4)));

    try {
        $stmt = $conn->prepare(
            "INSERT INTO contact_messages (ticket_number, name, email, phone, subject, message, status, created_at)
             VALUES (?, ?, ?, ?, ?, ?, 'new', NOW())"
        );
        $stmt->bind_param("ssssss", $ticketNo, $name, $email, $phoneToSave, $subject, $message);
        $stmt->execute();

        // ---- Post/Redirect/Get: stash the success flash in session,
        // rotate CSRF + form tokens, record the rate-limit timestamp, then
        // redirect on GET ----
        $_SESSION['cu_form_success']      = true;
        $_SESSION['cu_ticket_no']         = $ticketNo;
        $_SESSION['cu_last_form_token']   = $formToken;
        $_SESSION['cu_last_submit_time']  = time();
        $_SESSION['cu_csrf_token']        = bin2hex(random_bytes(32));
        unset($_SESSION['cu_old'], $_SESSION['cu_form_error_code'], $_SESSION['cu_field_errors']);

        header('Location: ' . $selfPath . '?sent=1#cu-form-section');
        exit;
    } catch (\Throwable $e) {
        $_SESSION['cu_form_error_code'] = 'save_failed';
        $_SESSION['cu_old']             = $old;
        header('Location: ' . $selfPath . '#cu-form-section');
        exit;
    }
}

// ---- Read flash state after the redirect (PRG) ----
if (isset($_GET['sent']) && !empty($_SESSION['cu_form_success'])) {
    $formSuccess = true;
    $ticketNo    = (string)($_SESSION['cu_ticket_no'] ?? '');
    unset($_SESSION['cu_form_success'], $_SESSION['cu_ticket_no']);
} elseif (!empty($_SESSION['cu_form_error_code'])) {
    $formErrorCode = (string)$_SESSION['cu_form_error_code'];
    $old           = $_SESSION['cu_old'] ?? $old;
    $fieldErrors   = $_SESSION['cu_field_errors'] ?? [];
    unset($_SESSION['cu_form_error_code'], $_SESSION['cu_old'], $_SESSION['cu_field_errors']);
}

$csrfToken   = $_SESSION['cu_csrf_token'];
$cuFormToken = bin2hex(random_bytes(16));

// ---- "Open Now / Closed Now" — computed in IST regardless of the
// server's own timezone setting, so it is always correct for Indian
// visitors. Mon-Sat 9:00-18:00 IST is open; Sunday is always closed.
// (Public holidays can't be detected automatically without a holiday
// calendar, so they are documented but not computed here.) ----
try {
    $istNow        = new DateTime('now', new DateTimeZone('Asia/Kolkata'));
    $dayOfWeek     = (int)$istNow->format('N'); // 1 = Monday ... 7 = Sunday
    $minutesNow    = ((int)$istNow->format('G')) * 60 + (int)$istNow->format('i');
    $isOpenNow     = ($dayOfWeek >= 1 && $dayOfWeek <= 6) && ($minutesNow >= (9 * 60) && $minutesNow < (18 * 60));
} catch (\Throwable $e) {
    $isOpenNow = false;
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* Prefix: .cu- — global style.css शी clash नाही. Palette साईटच्याच. */

/* ============== HERO — matches homepage slider style ============== */

/* ============== ABOUT / SUPPORT SECTION ============== */
.cu-about-section { max-width: 1200px; margin: 0 auto; padding: 4.5rem 6% 1rem; background: #F8FAF7; overflow-x: hidden; }
.cu-about-grid { display: grid; grid-template-columns: 0.9fr 1.1fr; gap: 3rem; align-items: center; }
@media (max-width: 900px) { .cu-about-grid { grid-template-columns: 1fr; } .cu-about-collage { margin-bottom: 1rem; } }
.cu-about-collage { position: relative; height: 440px; }
.cu-about-collage img { position: absolute; border-radius: 20px; object-fit: cover; box-shadow: 0 20px 45px rgba(0,0,0,0.14); }
.cu-about-img1 { width: 58%; height: 82%; left: 0; top: 0; z-index: 1; }
.cu-about-img2 { width: 42%; height: 48%; right: 0; top: 4%; z-index: 2; border: 6px solid #F8FAF7; }
.cu-about-img3 { width: 40%; height: 44%; left: 20%; bottom: 0; z-index: 3; border: 6px solid #F8FAF7; }
.cu-about-badge { position: absolute; left: 47%; bottom: 22%; z-index: 4; width: 78px; height: 78px; border-radius: 50%; background: #2E7D32; color: #fff; display: flex; align-items: center; justify-content: center; text-align: center; font-size: 11px; font-weight: 700; line-height: 1.3; box-shadow: 0 12px 30px rgba(46,125,50,0.4); border: 4px solid #F8FAF7; }
@media (max-width: 600px) {
    .cu-about-collage { height: 320px; }
    .cu-about-badge { width: 62px; height: 62px; font-size: 9.5px; }
}
.cu-about-copy h2 { font-size: clamp(1.6rem,3vw,2.1rem); font-weight: 800; color: #0b1a14; margin: 10px 0 14px; line-height: 1.3; }
.cu-about-copy p { color: #5a6b5a; font-size: 0.98rem; line-height: 1.7; margin-bottom: 1.6rem; }
.cu-about-checklist { display: grid; grid-template-columns: 1fr 1fr; gap: 10px 24px; background: #fff; border: 1px solid #eef3ee; border-radius: 14px; padding: 18px 20px; margin-bottom: 1.8rem; }
@media (max-width: 420px) { .cu-about-checklist { grid-template-columns: 1fr; } }
.cu-about-checklist div { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #1c2e1c; font-weight: 600; }
.cu-about-checklist i { color: #2E7D32; font-size: 11px; }

/* ============== PHOTO CARDS BAND ==============
   Desktop (>900px): .cu-cards-photo-wrap is absolutely positioned so the
   card row sits half on the hero photo, half on the white background below
   (a true "overlap" effect). .cu-cards-photo-bottom is a dedicated white
   spacer that reserves room for the lower half of the cards.
   Tablet/mobile (<=900px): the wrap drops into normal document flow
   (position: static) directly below the head — no overlap, no absolute
   positioning, and the white spacer collapses to near-zero since it is no
   longer needed to "catch" an overlapping card. */
.cu-cards-photo-head {
    position: relative; z-index: 1; overflow: visible; max-width: none;
    min-height: 300px; display: flex; flex-direction: column; align-items: center; justify-content: flex-start; text-align: center;
    padding: 34px 24px 0;
    background:
        linear-gradient(180deg, rgba(6,16,10,0.6) 0%, rgba(6,16,10,0.4) 45%, rgba(6,16,10,0.6) 100%),
        url('<?php echo $base_path; ?>/assets/images/contact-field-bg.jpg') center 58% / cover no-repeat;
}
.cu-cards-photo-icon { width: 46px; height: 46px; border-radius: 50%; background: rgba(255,255,255,0.12); border: 1px solid rgba(255,255,255,0.35); display: flex; align-items: center; justify-content: center; color: #a5d6a7; font-size: 17px; margin: 0 auto 8px; position: relative; z-index: 2; }
.cu-cards-photo-head .section-label { color: #a5d6a7; position: relative; z-index: 2; margin-bottom: 4px; }
.cu-cards-photo-head .section-title { color: #fff; margin-bottom: 0; position: relative; z-index: 2; }

.cu-cards-photo-wrap {
    position: absolute; left: 0; right: 0; margin-left: auto; margin-right: auto; bottom: -122px;
    z-index: 10;
    width: min(1000px, calc(100% - 60px));
    margin-top: 0;
    background: transparent; box-shadow: none;
}
.cu-cards-photo-bottom { position: relative; background: #F8FAF7; height: 123px; }

/* ---- Tablet: normal flow, two cards per row, no overlap ---- */
@media (max-width: 900px) {
    .cu-cards-photo-head { min-height: 240px; padding-bottom: 28px; }
    .cu-cards-photo-wrap {
        position: static; width: 100%; max-width: 640px;
        margin: 24px auto 0; padding: 0 20px; bottom: auto;
    }
    .cu-cards-grid { grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
    .cu-cards-photo-bottom { height: 32px; }
}
/* ---- Mobile: normal flow, one card per row, no overlap ---- */
@media (max-width: 560px) {
    .cu-cards-photo-head { min-height: 220px; }
    .cu-cards-photo-wrap { padding: 0 16px; }
    .cu-cards-grid { grid-template-columns: 1fr; gap: 14px; }
    .cu-cards-photo-bottom { height: 20px; }
}

.cu-cards-grid { position: relative; z-index: 10; width: 100%; display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 16px; align-items: stretch; text-align: left; }

/* ---- Premium card ----
   height:auto + min-height (instead of a fixed height) lets each card grow
   to fit long Marathi/Hindi copy, long emails, or the full postal address
   without clipping. CSS Grid's default row-stretch behaviour still keeps
   every card in the same row equal height on desktop. */
.cu-info-card {
    position: relative; display: flex; flex-direction: column;
    width: 100%; min-width: 0; height: auto; min-height: 230px;
    padding: 22px 20px;
    text-decoration: none; color: inherit;
    background:
        radial-gradient(circle at 100% 100%, rgba(76,175,80,0.10), transparent 60%),
        linear-gradient(145deg, #ffffff 0%, #fbfdfb 100%);
    border: 1px solid rgba(46,125,50,0.12); border-radius: 16px;
    overflow: hidden; isolation: isolate;
    box-shadow: 0 24px 55px rgba(15,45,25,0.13), 0 8px 20px rgba(15,45,25,0.07);
    transition: transform .35s ease, box-shadow .35s ease, border-color .35s ease;
}
.cu-info-card::before {
    content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 4px;
    background: linear-gradient(90deg, #2E7D32, #66BB6A);
    transform: scaleX(0); transform-origin: left; transition: transform .4s ease; z-index: 1;
}
.cu-info-card::after {
    content: ''; position: absolute; left: 24px; right: 24px; bottom: 0; height: 3px; border-radius: 3px 3px 0 0;
    background: linear-gradient(90deg, #66BB6A, #2E7D32);
    transform: scaleX(0); transform-origin: left; transition: transform .45s ease .05s;
}
.cu-info-card:hover, .cu-info-card:focus-visible { transform: translateY(-10px); border-color: rgba(46,125,50,.28); box-shadow: 0 34px 70px rgba(15,45,25,.18), 0 12px 26px rgba(15,45,25,.09); }
.cu-info-card:hover::before, .cu-info-card:hover::after, .cu-info-card:focus-visible::before, .cu-info-card:focus-visible::after { transform: scaleX(1); }
.cu-info-card:focus-visible { outline: 3px solid #a5d6a7; outline-offset: 3px; }

.cu-card-circle {
    width: 50px; height: 50px; border-radius: 50%; color: #fff;
    background: linear-gradient(145deg, #43A047, #237A32);
    border: 3px solid rgba(232,245,233,.95);
    display: flex; align-items: center; justify-content: center; font-size: 20px; margin-bottom: 16px;
    position: relative; z-index: 1; flex-shrink: 0;
    box-shadow: 0 12px 25px rgba(46,125,50,.25), inset 0 1px 0 rgba(255,255,255,.25);
    transition: transform .35s ease, box-shadow .35s ease;
}
.cu-info-card:hover .cu-card-circle { transform: translateY(-4px) rotate(-4deg) scale(1.05); box-shadow: 0 17px 32px rgba(46,125,50,.32), inset 0 1px 0 rgba(255,255,255,.25); }

.cu-info-card h4 { font-size: 16px; font-weight: 750; color: #102019; margin-bottom: 8px; line-height: 1.3; position: relative; z-index: 1; overflow-wrap: anywhere; }
.cu-info-card p { font-size: 12px; color: #718174; line-height: 1.5; margin-bottom: 0; overflow-wrap: anywhere; word-break: break-word; position: relative; z-index: 1; }
.cu-contact-detail { color: #1c2e1c; font-weight: 700; }
.cu-readmore { display: inline-flex; align-items: center; gap: 8px; width: fit-content; margin-top: auto; padding-top: 14px; font-size: 12px; font-weight: 700; color: #287D32; text-decoration: none; position: relative; z-index: 1; }
.cu-readmore-btn {
    display: inline-flex; align-items: center; justify-content: center; width: 27px; height: 27px; border-radius: 50%; flex-shrink: 0;
    background: linear-gradient(145deg, #379842, #237A32); color: #fff; font-size: 11px;
    box-shadow: 0 7px 16px rgba(46,125,50,.22);
    transition: transform .3s ease, box-shadow .3s ease;
}
.cu-info-card:hover .cu-readmore-btn { transform: translateX(5px) scale(1.05); box-shadow: 0 10px 20px rgba(46,125,50,.3); }

/* ============== FORM + HOURS + MAP ============== */
.cu-section { max-width: 1200px; margin: 0 auto; padding: 3.5rem 6% 5rem; background: #F8FAF7; overflow-x: hidden; }
.cu-grid { display: grid; grid-template-columns: 1.05fr 0.95fr; gap: 2.5rem; align-items: start; margin-bottom: 1.5rem; }
@media (max-width: 900px) { .cu-grid { grid-template-columns: 1fr; } }

.cu-card { background: #fff; border: 1px solid #eef3ee; border-radius: 18px; padding: 2.2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.04); transition: box-shadow .3s ease; }
@media (max-width: 480px) { .cu-card { padding: 1.5rem; } }
.cu-card:hover { box-shadow: 0 16px 50px rgba(0,0,0,0.07); }
.cu-card-head { display: flex; align-items: center; gap: 14px; margin-bottom: 1.8rem; }
.cu-card-ic { width: 46px; height: 46px; flex-shrink: 0; border-radius: 12px; background: #2E7D32; display: flex; align-items: center; justify-content: center; color: #fff; font-size: 18px; }
.cu-card-head h2 { font-size: 18px; font-weight: 800; color: #0b1a14; line-height: 1.3; }
.cu-card-head span { display: block; font-size: 12.5px; font-weight: 400; color: #8a978a; margin-top: 2px; }

.cu-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; }
@media (max-width: 600px) { .cu-row { grid-template-columns: 1fr; } }
.cu-field-wrap { position: relative; margin-bottom: 14px; }
.cu-row .cu-field-wrap { margin-bottom: 0; }
.cu-label { display: block; font-size: 11px; font-weight: 700; letter-spacing: 0.4px; text-transform: uppercase; color: #4b5d4b; margin-bottom: 6px; }
.cu-field-inner { position: relative; }
.cu-field-inner > i { position: absolute; left: 15px; top: 15px; color: #9db09d; font-size: 13px; pointer-events: none; }
.cu-field-inner > i.fa-chevron-down { left: auto; right: 15px; }
.cu-field { width: 100%; padding: 13px 16px 13px 38px; border: 1px solid #e5efe5; border-radius: 10px; background: #fff; font-size: 13px; font-family: 'Poppins', sans-serif; color: #1c2e1c; outline: none; transition: border-color 0.2s, box-shadow .2s; }
.cu-field::placeholder { color: #9db09d; }
.cu-field:focus { border-color: #4CAF50; box-shadow: 0 0 0 4px rgba(76,175,80,.15); }
.cu-field:focus-visible { outline: 2px solid #2E7D32; outline-offset: 1px; }
.cu-field.cu-field-invalid { border-color: #d9534f; box-shadow: 0 0 0 3px rgba(217,83,79,.12); }
textarea.cu-field { resize: vertical; min-height: 120px; padding-top: 14px; }
select.cu-field { appearance: none; -webkit-appearance: none; padding-right: 34px; color: #9db09d; cursor: pointer; }
select.cu-field.has-value { color: #1c2e1c; }
/* Inline field error: text-based (not colour-only) so it doesn't rely on
   colour alone to communicate the problem — a screen reader announces it
   via aria-describedby regardless of colour perception. */
.cu-field-error { display: none; font-size: 11.5px; font-weight: 600; color: #b3261e; margin-top: 6px; line-height: 1.4; }
.cu-field-error.show { display: block; }
.cu-field-error::before { content: "⚠ "; }
.cu-field-meta { display: flex; align-items: flex-start; justify-content: space-between; gap: 10px; margin-top: 6px; }
.cu-field-meta .cu-field-error { margin-top: 0; flex: 1; }
.cu-char-counter { font-size: 11px; color: #8a978a; white-space: nowrap; flex-shrink: 0; }
.cu-char-counter.limit { color: #b3261e; font-weight: 700; }
.cu-privacy-note { margin: 12px 0 0; font-size: 11.5px; color: #7c8c7c; line-height: 1.55; }
.cu-alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 16px; }
.cu-alert.ok { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
.cu-alert.ok strong { font-size: 14px; }
.cu-alert.err { background: #fdecea; color: #a33; border: 1px solid #f5c6c6; }

/* Honeypot: visually hidden, off-screen, unreachable via Tab — real users
   never see or interact with it; simple spam bots that auto-fill every
   field will trip it. */
.cu-hp { position: absolute; left: -9999px; top: -9999px; width: 1px; height: 1px; overflow: hidden; }

.cu-btn {
    display: inline-flex; align-items: center; gap: 8px; background: #2E7D32; color: #fff;
    border: none; padding: 13px 28px; border-radius: 10px; font-weight: 700; font-size: 13.5px;
    cursor: pointer; transition: 0.25s; font-family: 'Poppins', sans-serif; text-decoration: none;
    box-shadow: 0 8px 20px rgba(46,125,50,0.25);
}
.cu-btn:hover { background: #1b5e20; transform: translateY(-3px); box-shadow: 0 14px 28px rgba(46,125,50,0.35); color: #fff; }
.cu-btn:focus-visible { outline: 3px solid #a5d6a7; outline-offset: 3px; }
.cu-btn:disabled { opacity: 0.7; cursor: not-allowed; transform: none; }
@media (max-width: 480px) { .cu-btn[type="submit"] { width: 100%; justify-content: center; } }

.cu-panel { background: #fff; border: 1px solid #e5efe5; border-radius: 18px; padding: 2rem 2.2rem; box-shadow: 0 10px 40px rgba(0,0,0,0.05); transition: box-shadow .3s ease; }
@media (max-width: 480px) { .cu-panel { padding: 1.5rem; } }
.cu-panel:hover { box-shadow: 0 16px 50px rgba(0,0,0,0.08); }
.cu-panel h3 { font-size: 19px; font-weight: 800; color: #0b1a14; margin-bottom: 4px; }
.cu-panel .cu-underline { width: 40px; height: 3px; background: #2E7D32; border-radius: 2px; margin-bottom: 1.4rem; }
.cu-hours-badge { display: inline-flex; align-items: center; gap: 6px; padding: 5px 13px; border-radius: 20px; font-size: 12px; font-weight: 700; margin: 10px 0 1.2rem; }
.cu-hours-badge.is-open { background: #e8f5e9; color: #1b5e20; border: 1px solid #c8e6c9; }
.cu-hours-badge.is-closed { background: #fbeceb; color: #8a3a35; border: 1px solid #f0d0ce; }
.cu-hours-row { display: flex; align-items: center; justify-content: space-between; padding: 11px 0; border-bottom: 1px solid #f0f4ef; font-size: 13.5px; gap: 10px; flex-wrap: wrap; }
.cu-hours-row:last-of-type { border-bottom: none; }
.cu-hours-row .cu-day { display: flex; align-items: center; gap: 10px; color: #1c2e1c; font-weight: 600; }
.cu-hours-row .cu-day i { color: #2E7D32; width: 16px; }
.cu-hours-row .cu-time { color: #6b7d6b; }
.cu-hours-row.closed .cu-time { color: #c0524b; font-weight: 600; }
.cu-help-box { margin-top: 1.4rem; background: #e8f5e9; border-radius: 12px; padding: 14px 16px; display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap; }
.cu-help-box .cu-help-text-wrap { display: flex; align-items: center; gap: 10px; font-size: 12.5px; color: #1b5e20; }
.cu-help-box .cu-help-text-wrap i { font-size: 18px; }
.cu-help-box .cu-help-btn { background: #2E7D32; color: #fff; border: none; padding: 9px 16px; border-radius: 8px; font-size: 12px; font-weight: 700; white-space: nowrap; text-decoration: none; transition: .25s; }
.cu-help-box .cu-help-btn:hover { background: #1b5e20; transform: translateY(-2px); }
.cu-help-box .cu-help-btn:focus-visible { outline: 3px solid #1b5e20; outline-offset: 2px; }
.cu-map-panel { padding: 0; overflow: hidden; margin-bottom: 0; }
.cu-map-panel .cu-map-head { padding: 2rem 2.2rem 1rem; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px; }
@media (max-width: 480px) { .cu-map-panel .cu-map-head { padding: 1.5rem 1.5rem 1rem; } }
.cu-map-frame { width: 100%; height: 300px; border: 0; display: block; background: #e8f0e8; }
@media (max-width: 600px) { .cu-map-frame { height: 220px; } }
.cu-map-open { display: inline-flex; align-items: center; gap: 6px; font-size: 12.5px; font-weight: 700; color: #2E7D32; text-decoration: none; }
.cu-map-open:hover { text-decoration: underline; }
.cu-map-open:focus-visible { outline: 2px solid #2E7D32; outline-offset: 2px; }

/* ============== REDUCED MOTION ============== */
@media (prefers-reduced-motion: reduce) {
    .cu-info-card, .cu-info-card::before, .cu-info-card::after, .cu-card-circle, .cu-readmore-btn,
    .cu-btn, .cu-help-box .cu-help-btn, .cu-panel, .cu-card, .cu-field,
    .agri-reveal {
        transition: none !important;
        animation: none !important;
    }
    .cu-info-card:hover, .cu-info-card:focus-visible, .cu-btn:hover,
    .cu-help-box .cu-help-btn:hover, .cu-info-card:hover .cu-card-circle, .cu-info-card:hover .cu-readmore-btn {
        transform: none !important;
    }
}
</style>

<!-- ============== HERO SLIDER — matches homepage slider exactly ============== -->
<div class="slider-wrap">

    <!-- Slide 1: General Contact Support -->
    <div class="slide active" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact/agricart-support-agent.jpg');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="cu-badge">GET IN TOUCH</div>
            <h1 id="cu-hero-title">Contact AgriCart</h1>
            <p id="cu-hero-sub">Questions about orders, equipment rental, or crop advisory? Our team is one message away.</p>
            <a href="#cu-form-section" class="slide-cta" id="cu-hero-btn-primary">Send A Message</a>
        </div>
    </div>

    <!-- Slide 2: Orders & Equipment Rental Support -->
    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact/agricart-delivery-van.jpg');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="cs2-tag">Orders &amp; Rental</div>
            <h1 id="cs2-h">Need Help With Orders Or Equipment Rental?</h1>
            <p id="cs2-p">Track an existing order, get delivery updates, or manage equipment rental bookings and pickups — our team can help with all of it.</p>
            <a href="#cu-form-section" class="slide-cta" id="cs2-btn">Send A Message</a>
        </div>
    </div>

    <!-- Slide 3: Crop Advisory Support -->
    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact/agricart-farmer-basket.jpg');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="cs3-tag">Crop Advisory</div>
            <h1 id="cs3-h">Need Advice For Your Crop?</h1>
            <p id="cs3-p">Get guidance on crop care, pest control and mandi prices from our agri experts.</p>
            <a href="#cu-form-section" class="slide-cta" id="cs3-btn">Ask Our Experts</a>
        </div>
    </div>

    <!-- Slide 4: Quality & Fulfillment -->
    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact/agricart-warehouse-packing.jpg');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="cs4-tag">Quality &amp; Fulfillment</div>
            <h1 id="cs4-h">Curious How We Pack Your Order?</h1>
            <p id="cs4-p">Every order is hygienically sorted, checked and packed at our facility — fresh, safe and reliable, every time.</p>
            <a href="#cu-form-section" class="slide-cta" id="cs4-btn">Ask About Our Process</a>
        </div>
    </div>

    <!-- Slide 5: Feedback & Support -->
    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact/agricart-farm-field-sunset.jpg');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="cs5-tag">Feedback &amp; Support</div>
            <h1 id="cs5-h">Have Feedback Or A Complaint?</h1>
            <p id="cs5-p">Tell us what's working and what's not — we read every message and act on it fast.</p>
            <a href="#cu-form-section" class="slide-cta" id="cs5-btn">Share Feedback</a>
        </div>
    </div>

    <div class="slider-dots" id="sliderDots"></div>
</div>

<!-- ============== CONTACT STATS STRIP (reuses homepage's .stats CSS) ============== -->
<div class="stats">
    <div class="stat-item">
        <h3><?php echo cu_fmt_stat($stat_resolved); ?></h3>
        <p id="cu-stat1-lbl">Queries Resolved</p>
    </div>
    <div class="stat-item">
        <h3 id="cu-stat2-val">&lt; 24 Hrs</h3>
        <p id="cu-stat2-lbl">Avg Reply Time</p>
    </div>
    <div class="stat-item">
        <h3 id="cu-stat3-val">6 Days</h3>
        <p id="cu-stat3-lbl">Support Availability</p>
    </div>
    <div class="stat-item">
        <div class="mini-stars"><?php echo $stat_reviews_count > 0 ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?></div>
        <h3 id="cu-stat4-val" data-empty="<?php echo $stat_reviews_count > 0 ? '0' : '1'; ?>"><?php echo $stat_reviews_count > 0 ? htmlspecialchars((string)$stat_rating) : 'New'; ?></h3>
        <p id="cu-stat4-lbl">Platform Rating</p>
    </div>
</div>


<!-- ============== ABOUT / SUPPORT SECTION ============== -->
<div class="cu-about-section">
    <div class="cu-about-grid">
        <div class="cu-about-collage agri-reveal agri-reveal-left">
            <img class="cu-about-img1" src="<?php echo $base_path; ?>/assets/images/contact.png" alt="Support team" loading="lazy" decoding="async">
            <img class="cu-about-img2" src="<?php echo $base_path; ?>/assets/images/advisory.png" alt="Crop advisory call" loading="lazy" decoding="async">
            <img class="cu-about-img3" src="<?php echo $base_path; ?>/assets/images/agristore.png" alt="Store assistance" loading="lazy" decoding="async">
            <div class="cu-about-badge" aria-hidden="true"><i class="fa-solid fa-headset"></i></div>
        </div>
        <div class="cu-about-copy agri-reveal agri-reveal-right">
            <span class="section-label" id="cu-about-label">About Support</span>
            <h2 id="cu-about-title">We'll Help You Get The Right Answer, Fast</h2>
            <p id="cu-about-desc">Whether it's an order, a rental, a crop question, or a mandi price — our team is trained across every part of AgriCart, so you don't get bounced around. Reach us your way and we'll take it from there.</p>
            <div class="cu-about-checklist">
                <div><i class="fa-solid fa-check" aria-hidden="true"></i> <span id="cu-about-c1">Phone &amp; Email Support</span></div>
                <div><i class="fa-solid fa-check" aria-hidden="true"></i> <span id="cu-about-c2">Order &amp; Rental Help</span></div>
                <div><i class="fa-solid fa-check" aria-hidden="true"></i> <span id="cu-about-c3">Crop Advisory Queries</span></div>
                <div><i class="fa-solid fa-check" aria-hidden="true"></i> <span id="cu-about-c4">Store Visit By Appointment</span></div>
                <div><i class="fa-solid fa-check" aria-hidden="true"></i> <span id="cu-about-c5">Mandi Rate Questions</span></div>
                <div><i class="fa-solid fa-check" aria-hidden="true"></i> <span id="cu-about-c6">Reply Within 24 Hours</span></div>
            </div>
            <a href="#cu-form-section" class="cu-btn" id="cu-about-btn">Contact Us</a>
        </div>
    </div>
</div>


<!-- ============== CONTACT INFO CARDS ============== -->
<div class="cu-cards-photo-head">
    <div class="cu-cards-photo-icon" aria-hidden="true"><i class="fa-solid fa-leaf"></i></div>
    <span class="section-label" id="cu-cards-label">Get In Touch</span>
    <h2 class="section-title" id="cu-cards-title">The Best Way To Reach Us</h2>

    <div class="cu-cards-photo-wrap">
    <div class="cu-cards-grid">
        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $SITE_HOTLINE)); ?>" class="cu-info-card agri-reveal" aria-label="Call AgriCart at <?php echo htmlspecialchars($SITE_HOTLINE); ?>">
            <div class="cu-card-circle" aria-hidden="true"><i class="fa-solid fa-phone"></i></div>
            <h4 id="cu-card1-title">Phone</h4>
            <p><span class="cu-contact-detail"><?php echo htmlspecialchars($SITE_HOTLINE); ?></span> — <span id="cu-card1-desc">Mon to Sat, 9AM to 6PM</span></p>
            <span class="cu-readmore"><span id="cu-card1-link">Call Now</span><span class="cu-readmore-btn" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
        </a>
        <a href="mailto:<?php echo htmlspecialchars($SITE_EMAIL); ?>" class="cu-info-card agri-reveal" aria-label="Email AgriCart at <?php echo htmlspecialchars($SITE_EMAIL); ?>">
            <div class="cu-card-circle" aria-hidden="true"><i class="fa-solid fa-envelope"></i></div>
            <h4 id="cu-card2-title">Email</h4>
            <p><span class="cu-contact-detail"><?php echo htmlspecialchars($SITE_EMAIL); ?></span> — <span id="cu-card2-desc">we reply within 24 hours</span></p>
            <span class="cu-readmore"><span id="cu-card2-link">Send Email</span><span class="cu-readmore-btn" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
        </a>
        <a href="#cu-map-panel" class="cu-info-card agri-reveal" aria-label="View AgriCart address on the map: <?php echo htmlspecialchars($SITE_ADDRESS); ?>">
            <div class="cu-card-circle" aria-hidden="true"><i class="fa-solid fa-location-dot"></i></div>
            <h4 id="cu-card3-title">Address</h4>
            <p><span class="cu-contact-detail"><?php echo htmlspecialchars($SITE_ADDRESS); ?></span></p>
            <span class="cu-readmore"><span id="cu-card3-link">View Map</span><span class="cu-readmore-btn" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
        </a>
        <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $SITE_SUPPORT)); ?>" class="cu-info-card agri-reveal" aria-label="Call AgriCart customer support at <?php echo htmlspecialchars($SITE_SUPPORT); ?>">
            <div class="cu-card-circle" aria-hidden="true"><i class="fa-solid fa-headset"></i></div>
            <h4 id="cu-card4-title">Customer Support</h4>
            <p><span class="cu-contact-detail"><?php echo htmlspecialchars($SITE_SUPPORT); ?></span> — <span id="cu-card4-desc">Mon to Sat, 9AM to 6PM</span></p>
            <span class="cu-readmore"><span id="cu-card4-link">Get Help</span><span class="cu-readmore-btn" aria-hidden="true"><i class="fa-solid fa-arrow-right"></i></span></span>
        </a>
    </div>
    </div>
</div>
<div class="cu-cards-photo-bottom"></div>

<!-- ============== FORM + WORKING HOURS + MAP ============== -->
<div class="cu-section" id="cu-form-section">
    <div class="cu-grid">
        <div class="cu-card agri-reveal agri-reveal-left">
            <div class="cu-card-head">
                <div class="cu-card-ic" aria-hidden="true"><i class="fa-solid fa-envelope"></i></div>
                <h2 id="cu-form-title">Send Us A Message<span id="cu-form-sub">We usually reply within a day</span></h2>
            </div>

            <?php if ($formSuccess): ?>
                <div class="cu-alert ok" role="alert" aria-live="polite" id="cu-form-message">
                    <span id="cu-alert-success-text"></span> <strong id="cu-ticket-id"><?php echo htmlspecialchars($ticketNo); ?></strong>
                </div>
            <?php elseif ($formErrorCode !== ''): ?>
                <div class="cu-alert err" role="alert" aria-live="polite" id="cu-form-message" data-error-code="<?php echo htmlspecialchars($formErrorCode); ?>">
                    <span id="cu-alert-error-text"></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo htmlspecialchars($selfPath); ?>#cu-form-section" id="cu-contact-form" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
                <input type="hidden" name="cu_form_token" value="<?php echo htmlspecialchars($cuFormToken); ?>">

                <!-- Honeypot: hidden from real users, left empty by them -->
                <div class="cu-hp" aria-hidden="true">
                    <label for="cu_website">Leave this field empty</label>
                    <input type="text" id="cu_website" name="cu_website" tabindex="-1" autocomplete="off" value="">
                </div>

                <div class="cu-row">
                    <div class="cu-field-wrap">
                        <label class="cu-label" for="cu-input-name" id="cu-label-name">Your Name *</label>
                        <div class="cu-field-inner">
                            <i class="fa-solid fa-user" aria-hidden="true"></i>
                            <input type="text" name="name" id="cu-input-name"
                                class="cu-field<?php echo isset($fieldErrors['name']) ? ' cu-field-invalid' : ''; ?>"
                                placeholder="Your Name *" autocomplete="name" maxlength="100" required
                                aria-invalid="<?php echo isset($fieldErrors['name']) ? 'true' : 'false'; ?>"
                                aria-describedby="err-name"
                                value="<?php echo htmlspecialchars($old['name']); ?>">
                        </div>
                        <span class="cu-field-error<?php echo isset($fieldErrors['name']) ? ' show' : ''; ?>" id="err-name" data-error-key="errName" role="alert"></span>
                    </div>
                    <div class="cu-field-wrap">
                        <label class="cu-label" for="cu-input-email" id="cu-label-email">Email *</label>
                        <div class="cu-field-inner">
                            <i class="fa-solid fa-envelope" aria-hidden="true"></i>
                            <input type="email" name="email" id="cu-input-email"
                                class="cu-field<?php echo isset($fieldErrors['email']) ? ' cu-field-invalid' : ''; ?>"
                                placeholder="Email *" autocomplete="email" maxlength="150" required
                                aria-invalid="<?php echo isset($fieldErrors['email']) ? 'true' : 'false'; ?>"
                                aria-describedby="err-email"
                                value="<?php echo htmlspecialchars($old['email']); ?>">
                        </div>
                        <span class="cu-field-error<?php echo isset($fieldErrors['email']) ? ' show' : ''; ?>" id="err-email" data-error-key="errEmail" role="alert"></span>
                    </div>
                </div>
                <div class="cu-row">
                    <div class="cu-field-wrap">
                        <label class="cu-label" for="cu-input-phone" id="cu-label-phone">Mobile Number</label>
                        <div class="cu-field-inner">
                            <i class="fa-solid fa-phone" aria-hidden="true"></i>
                            <input type="tel" name="phone" id="cu-input-phone"
                                class="cu-field<?php echo isset($fieldErrors['phone']) ? ' cu-field-invalid' : ''; ?>"
                                placeholder="Mobile Number" autocomplete="tel" inputmode="tel" maxlength="20"
                                aria-invalid="<?php echo isset($fieldErrors['phone']) ? 'true' : 'false'; ?>"
                                aria-describedby="err-phone"
                                value="<?php echo htmlspecialchars($old['phone']); ?>">
                        </div>
                        <span class="cu-field-error<?php echo isset($fieldErrors['phone']) ? ' show' : ''; ?>" id="err-phone" data-error-key="errPhone" role="alert"></span>
                    </div>
                    <div class="cu-field-wrap">
                        <label class="cu-label" for="cu-subject" id="cu-label-subject">Subject *</label>
                        <div class="cu-field-inner">
                            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
                            <select name="subject" id="cu-subject"
                                class="cu-field<?php echo $old['subject'] !== '' ? ' has-value' : ''; ?><?php echo isset($fieldErrors['subject']) ? ' cu-field-invalid' : ''; ?>"
                                required
                                aria-invalid="<?php echo isset($fieldErrors['subject']) ? 'true' : 'false'; ?>"
                                aria-describedby="err-subject"
                                onchange="this.classList.toggle('has-value', this.value !== '')">
                                <option value="" <?php echo $old['subject'] === '' ? 'selected' : ''; ?>>Select Subject</option>
                                <option value="order" <?php echo $old['subject'] === 'order' ? 'selected' : ''; ?>>Order Related</option>
                                <option value="rental" <?php echo $old['subject'] === 'rental' ? 'selected' : ''; ?>>Rental Related</option>
                                <option value="advisory" <?php echo $old['subject'] === 'advisory' ? 'selected' : ''; ?>>Crop Advisory</option>
                                <option value="other" <?php echo $old['subject'] === 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <span class="cu-field-error<?php echo isset($fieldErrors['subject']) ? ' show' : ''; ?>" id="err-subject" data-error-key="errSubject" role="alert"></span>
                    </div>
                </div>
                <div class="cu-field-wrap">
                    <label class="cu-label" for="cu-input-message" id="cu-label-message">Your Message *</label>
                    <div class="cu-field-inner">
                        <i class="fa-solid fa-pen" aria-hidden="true"></i>
                        <textarea name="message" id="cu-input-message"
                            class="cu-field<?php echo isset($fieldErrors['message']) ? ' cu-field-invalid' : ''; ?>"
                            placeholder="Write your message... *" autocomplete="off" maxlength="2000" rows="5" required
                            aria-invalid="<?php echo isset($fieldErrors['message']) ? 'true' : 'false'; ?>"
                            aria-describedby="err-message cu-char-counter"><?php echo htmlspecialchars($old['message']); ?></textarea>
                    </div>
                    <div class="cu-field-meta">
                        <span class="cu-field-error<?php echo isset($fieldErrors['message']) ? ' show' : ''; ?>" id="err-message" data-error-key="errMessage" role="alert"></span>
                        <span class="cu-char-counter" id="cu-char-counter" aria-live="polite"><?php echo mb_strlen($old['message']); ?> / 2000</span>
                    </div>
                </div>
                <button type="submit" name="cu_submit" value="1" class="cu-btn" id="cu-submit-btn">
                    <i class="fa-solid fa-paper-plane" aria-hidden="true"></i> <span id="cu-submit-text">Send Message</span>
                </button>
                <p class="cu-privacy-note" id="cu-privacy-note">Your details are used only to respond to your query and are never shared with anyone else.</p>
            </form>
        </div>

        <div class="agri-reveal agri-reveal-right">
            <div class="cu-panel">
                <h3 id="cu-hours-title">Working Hours</h3>
                <span class="cu-hours-badge <?php echo $isOpenNow ? 'is-open' : 'is-closed'; ?>" id="cu-hours-badge" data-status="<?php echo $isOpenNow ? 'open' : 'closed'; ?>">
                    <i class="fa-solid <?php echo $isOpenNow ? 'fa-circle-check' : 'fa-circle-xmark'; ?>" aria-hidden="true"></i>
                    <span id="cu-hours-badge-text"><?php echo $isOpenNow ? 'Open Now' : 'Closed Now'; ?></span>
                </span>
                <div class="cu-underline"></div>
                <div class="cu-hours-row"><div class="cu-day"><i class="fa-regular fa-clock" aria-hidden="true"></i> <span id="cu-day-1">Monday - Saturday</span></div><div class="cu-time" id="cu-day-1-time">9:00 AM - 6:00 PM</div></div>
                <div class="cu-hours-row closed"><div class="cu-day"><i class="fa-regular fa-calendar-xmark" aria-hidden="true"></i> <span id="cu-day-2">Sunday</span></div><div class="cu-time" id="cu-day-2-status">Closed</div></div>
                <div class="cu-hours-row closed"><div class="cu-day"><i class="fa-regular fa-calendar-days" aria-hidden="true"></i> <span id="cu-day-3">Public Holidays</span></div><div class="cu-time" id="cu-day-3-status">Closed</div></div>
                <div class="cu-help-box">
                    <div class="cu-help-text-wrap"><i class="fa-solid fa-headset" aria-hidden="true"></i><span id="cu-help-text">Need urgent help?<br>Talk to our support team directly.</span></div>
                    <a href="tel:<?php echo htmlspecialchars(preg_replace('/[^0-9+]/', '', $SITE_SUPPORT)); ?>" class="cu-help-btn" id="cu-help-btn" aria-label="Call customer support at <?php echo htmlspecialchars($SITE_SUPPORT); ?>"><i class="fa-solid fa-phone" aria-hidden="true"></i> Contact Support</a>
                </div>
            </div>
        </div>
    </div>

    <div class="cu-panel cu-map-panel agri-reveal agri-reveal-zoom" id="cu-map-panel">
        <div class="cu-map-head">
            <div><h3 id="cu-map-title">Our Location</h3><div class="cu-underline"></div></div>
            <a href="https://www.google.com/maps?q=<?php echo urlencode($SITE_ADDRESS); ?>" target="_blank" rel="noopener" class="cu-map-open" id="cu-map-open-link"><i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> Open in Google Maps</a>
        </div>
        <iframe class="cu-map-frame" src="<?php echo htmlspecialchars($MAP_EMBED_SRC); ?>" loading="lazy" referrerpolicy="no-referrer-when-downgrade" title="Map showing AgriCart's location at <?php echo htmlspecialchars($SITE_ADDRESS); ?>" allowfullscreen></iframe>
    </div>
</div>

<script>
const ContactT = {
    en: {
        badge: "GET IN TOUCH", heroTitle: "Contact AgriCart",
        heroSub: "Questions about orders, equipment rental, or crop advisory? Our team is one message away.",
        heroBtnPrimary: "Send A Message", heroBtnOutline: "Call Us Now",
        crumbHome: "Home", crumbCurrent: "Contact Us",
        cardsLabel: "Get In Touch", cardsTitle: "The Best Way To Reach Us",
        card1Title: "Phone", card1Desc: "Mon to Sat, 9AM to 6PM", card1Link: "Call Now",
        card2Title: "Email", card2Desc: "we reply within 24 hours", card2Link: "Send Email",
        card3Title: "Address", card3Link: "View Map",
        card4Title: "Customer Support", card4Desc: "Mon to Sat, 9AM to 6PM", card4Link: "Get Help",
        formTitle: "Send Us A Message", formSub: "We usually reply within a day",
        phName: "Your Name *", phEmail: "Email *", phPhone: "Mobile Number", phSubject: "Subject *", phMessage: "Write your message... *",
        subjDefault: "Select Subject", subjOrder: "Order Related", subjRental: "Rental Related", subjAdvisory: "Crop Advisory", subjOther: "Other",
        submitBtn: "Send Message", submitBtnSending: "Sending…",
        hoursTitle: "Working Hours", day1: "Monday - Saturday", day1Time: "9:00 AM - 6:00 PM", day2: "Sunday", day3: "Public Holidays", dayClosed: "Closed",
        openNowBadge: "Open Now", closedNowBadge: "Closed Now",
        helpText: "Need urgent help?<br>Talk to our support team directly.", helpBtn: "Contact Support", mapTitle: "Our Location", mapOpen: "Open in Google Maps",
        aboutLabel: "About Support", aboutTitle: "We'll Help You Get The Right Answer, Fast",
        aboutDesc: "Whether it's an order, a rental, a crop question, or a mandi price — our team is trained across every part of AgriCart, so you don't get bounced around. Reach us your way and we'll take it from there.",
        aboutC1: "Phone & Email Support", aboutC2: "Order & Rental Help", aboutC3: "Crop Advisory Queries",
        aboutC4: "Store Visit By Appointment", aboutC5: "Mandi Rate Questions", aboutC6: "Reply Within 24 Hours", aboutBtn: "Contact Us",
        s2Tag: "Orders & Rental", s2H: "Need Help With Orders Or Equipment Rental?", s2P: "Track an existing order, get delivery updates, or manage equipment rental bookings and pickups — our team can help with all of it.", s2Btn: "Send A Message",
        s3Tag: "Crop Advisory", s3H: "Need Advice For Your Crop?", s3P: "Get guidance on crop care, pest control and mandi prices from our agri experts.", s3Btn: "Ask Our Experts",
        s4Tag: "Quality & Fulfillment", s4H: "Curious How We Pack Your Order?", s4P: "Every order is hygienically sorted, checked and packed at our facility — fresh, safe and reliable, every time.", s4Btn: "Ask About Our Process",
        s5Tag: "Feedback & Support", s5H: "Have Feedback Or A Complaint?", s5P: "Tell us what's working and what's not — we read every message and act on it fast.", s5Btn: "Share Feedback",
        stat1Lbl: "Queries Resolved", stat2Val: "< 24 Hrs", stat2Lbl: "Avg Reply Time", stat3Val: "6 Days", stat3Lbl: "Support Availability", stat4Lbl: "Platform Rating", stat4New: "New",
        alertSuccess: "✅ Message sent! Ticket ID:",
        errName: "Please enter your name (at least 2 characters).",
        errEmail: "Please enter a valid email address.",
        errPhone: "Please enter a valid Indian mobile number.",
        errSubject: "Please select a subject.",
        errMessage: "Please write a message (at least 10 characters).",
        errSessionExpired: "Your session expired. Please try again.",
        errDuplicateSubmit: "It looks like this message was already sent.",
        errRateLimited: "You're submitting too quickly. Please wait a moment and try again.",
        errInvalidFields: "Please check the highlighted fields and try again.",
        errSaveFailed: "Could not save your message. Please try again.",
        privacyNote: "Your details are used only to respond to your query and are never shared with anyone else."
    },
    hi: {
        badge: "संपर्क करें", heroTitle: "AgriCart से संपर्क करें",
        heroSub: "ऑर्डर, किराये के उपकरण, या फसल सलाह से जुड़े सवाल हैं? हमारी टीम एक मैसेज दूर है।",
        heroBtnPrimary: "मैसेज भेजें", heroBtnOutline: "अभी कॉल करें",
        crumbHome: "होम", crumbCurrent: "संपर्क करें",
        cardsLabel: "संपर्क करें", cardsTitle: "हमसे संपर्क करने का सबसे अच्छा तरीका",
        card1Title: "फोन", card1Desc: "सोम से शनि, सुबह 9 से शाम 6", card1Link: "अभी कॉल करें",
        card2Title: "ईमेल", card2Desc: "हम 24 घंटे में जवाब देते हैं", card2Link: "ईमेल भेजें",
        card3Title: "पता", card3Link: "मैप देखें",
        card4Title: "ग्राहक सहायता", card4Desc: "सोम से शनि, सुबह 9 से शाम 6", card4Link: "मदद पाएं",
        formTitle: "हमें मैसेज भेजें", formSub: "हम आमतौर पर एक दिन में जवाब देते हैं",
        phName: "आपका नाम *", phEmail: "ईमेल *", phPhone: "मोबाइल नंबर", phSubject: "विषय *", phMessage: "अपना संदेश लिखें... *",
        subjDefault: "विषय चुनें", subjOrder: "ऑर्डर संबंधी", subjRental: "रेंटल संबंधी", subjAdvisory: "फसल सलाह", subjOther: "अन्य",
        submitBtn: "संदेश भेजें", submitBtnSending: "भेजा जा रहा है…",
        hoursTitle: "कार्य समय", day1: "सोमवार - शनिवार", day1Time: "सुबह 9:00 - शाम 6:00", day2: "रविवार", day3: "सार्वजनिक अवकाश", dayClosed: "बंद",
        openNowBadge: "अभी खुला है", closedNowBadge: "अभी बंद है",
        helpText: "तुरंत मदद चाहिए?<br>सीधे हमारी सपोर्ट टीम से बात करें।", helpBtn: "सपोर्ट से संपर्क करें", mapTitle: "हमारा पता", mapOpen: "गूगल मैप्स में खोलें",
        aboutLabel: "सपोर्ट के बारे में", aboutTitle: "हम आपको सही जवाब जल्दी दिलाएंगे",
        aboutDesc: "ऑर्डर हो, रेंटल हो, फ़सल से जुड़ा सवाल हो या मंडी भाव — हमारी टीम AgriCart के हर हिस्से में प्रशिक्षित है, ताकि आपको इधर-उधर न भटकना पड़े। अपने तरीके से संपर्क करें, बाकी हम संभाल लेंगे।",
        aboutC1: "फोन और ईमेल सपोर्ट", aboutC2: "ऑर्डर और रेंटल सहायता", aboutC3: "फ़सल सलाह से जुड़े सवाल",
        aboutC4: "अपॉइंटमेंट पर स्टोर विज़िट", aboutC5: "मंडी भाव से जुड़े सवाल", aboutC6: "24 घंटे में जवाब", aboutBtn: "संपर्क करें",
        s2Tag: "ऑर्डर और रेंटल", s2H: "ऑर्डर या रेंटल उपकरण में मदद चाहिए?", s2P: "मौजूदा ऑर्डर ट्रैक करें, डिलीवरी अपडेट पाएं, या रेंटल बुकिंग और पिकअप मैनेज करें — हमारी टीम हर चीज़ में मदद करेगी।", s2Btn: "मैसेज भेजें",
        s3Tag: "फसल सलाह", s3H: "अपनी फसल को लेकर सलाह चाहिए?", s3P: "फसल की देखभाल, कीट नियंत्रण और मंडी भाव पर हमारी एग्री टीम से सलाह लें।", s3Btn: "एक्सपर्ट से पूछें",
        s4Tag: "गुणवत्ता और पैकिंग", s4H: "जानना चाहते हैं हम आपका ऑर्डर कैसे पैक करते हैं?", s4P: "हर ऑर्डर हमारी फैसिलिटी में स्वच्छता से छांटा, जांचा और पैक किया जाता है — हर बार ताज़ा, सुरक्षित और भरोसेमंद।", s4Btn: "प्रक्रिया के बारे में पूछें",
        s5Tag: "फीडबैक और सहायता", s5H: "कोई फीडबैक या शिकायत है?", s5P: "बताएं क्या अच्छा चल रहा है और क्या नहीं — हम हर मैसेज पढ़ते हैं और जल्दी कार्रवाई करते हैं।", s5Btn: "फीडबैक भेजें",
        stat1Lbl: "सुलझाए गए प्रश्न", stat2Val: "< 24 घंटे", stat2Lbl: "औसत जवाब समय", stat3Val: "6 दिन", stat3Lbl: "सहायता उपलब्धता", stat4Lbl: "प्लेटफ़ॉर्म रेटिंग", stat4New: "नया",
        alertSuccess: "✅ मैसेज भेजा गया! टिकट ID:",
        errName: "कृपया अपना नाम दर्ज करें (कम से कम 2 अक्षर)।",
        errEmail: "कृपया एक मान्य ईमेल पता दर्ज करें।",
        errPhone: "कृपया एक मान्य भारतीय मोबाइल नंबर दर्ज करें।",
        errSubject: "कृपया एक विषय चुनें।",
        errMessage: "कृपया संदेश लिखें (कम से कम 10 अक्षर)।",
        errSessionExpired: "आपका सत्र समाप्त हो गया। कृपया पुनः प्रयास करें।",
        errDuplicateSubmit: "लगता है यह संदेश पहले ही भेजा जा चुका है।",
        errRateLimited: "आप बहुत जल्दी सबमिट कर रहे हैं। कृपया थोड़ी देर रुककर पुनः प्रयास करें।",
        errInvalidFields: "कृपया हाइलाइट किए गए फ़ील्ड जांचें और पुनः प्रयास करें।",
        errSaveFailed: "संदेश सहेजने में समस्या हुई। कृपया पुनः प्रयास करें।",
        privacyNote: "आपकी जानकारी केवल आपके प्रश्न का उत्तर देने के लिए उपयोग की जाती है और किसी के साथ साझा नहीं की जाती।"
    },
    mr: {
        badge: "संपर्क साधा", heroTitle: "AgriCart शी संपर्क साधा",
        heroSub: "ऑर्डर, भाड्याने उपकरणे किंवा पीक सल्ल्याबद्दल प्रश्न आहेत? आमची टीम एका मेसेजच्या अंतरावर आहे.",
        heroBtnPrimary: "मेसेज पाठवा", heroBtnOutline: "आत्ता कॉल करा",
        crumbHome: "होम", crumbCurrent: "संपर्क करा",
        cardsLabel: "संपर्क साधा", cardsTitle: "आमच्याशी संपर्क साधण्याचा सर्वोत्तम मार्ग",
        card1Title: "फोन", card1Desc: "सोम ते शनि, सकाळी 9 ते संध्याकाळी 6", card1Link: "आत्ता कॉल करा",
        card2Title: "ईमेल", card2Desc: "आम्ही 24 तासांत उत्तर देतो", card2Link: "ईमेल पाठवा",
        card3Title: "पत्ता", card3Link: "नकाशा पहा",
        card4Title: "ग्राहक सहाय्य", card4Desc: "सोम ते शनि, सकाळी 9 ते संध्याकाळी 6", card4Link: "मदत मिळवा",
        formTitle: "आम्हाला मेसेज पाठवा", formSub: "आम्ही साधारण एका दिवसात उत्तर देतो",
        phName: "तुमचे नाव *", phEmail: "ईमेल *", phPhone: "मोबाईल नंबर", phSubject: "विषय *", phMessage: "तुमचा संदेश लिहा... *",
        subjDefault: "विषय निवडा", subjOrder: "ऑर्डरबद्दल", subjRental: "भाड्याच्या उपकरणांबद्दल", subjAdvisory: "पीक सल्ला", subjOther: "इतर",
        submitBtn: "संदेश पाठवा", submitBtnSending: "पाठवत आहे…",
        hoursTitle: "कामाच्या वेळा", day1: "सोमवार - शनिवार", day1Time: "सकाळी 9:00 - संध्याकाळी 6:00", day2: "रविवार", day3: "सार्वजनिक सुट्टी", dayClosed: "बंद",
        openNowBadge: "आत्ता सुरू आहे", closedNowBadge: "आत्ता बंद आहे",
        helpText: "तातडीची मदत हवी आहे?<br>आमच्या सपोर्ट टीमशी थेट संपर्क साधा.", helpBtn: "सपोर्टशी संपर्क साधा", mapTitle: "आमचे ठिकाण", mapOpen: "गूगल मॅप्समध्ये उघडा",
        aboutLabel: "सपोर्टबद्दल", aboutTitle: "आम्ही तुम्हाला योग्य उत्तर लवकर मिळवून देऊ",
        aboutDesc: "ऑर्डर असो, भाड्याचं उपकरण असो, पीकाबद्दल प्रश्न असो किंवा मंडई भाव — आमची टीम AgriCart च्या प्रत्येक भागात प्रशिक्षित आहे, त्यामुळे तुम्हाला इकडे-तिकडे फिरावं लागणार नाही. तुमच्या पद्धतीने संपर्क साधा, बाकी आम्ही बघतो.",
        aboutC1: "फोन आणि ईमेल सपोर्ट", aboutC2: "ऑर्डर आणि भाडे मदत", aboutC3: "पीक सल्ल्याविषयी प्रश्न",
        aboutC4: "अपॉइंटमेंटने स्टोअर भेट", aboutC5: "मंडई भावाविषयी प्रश्न", aboutC6: "24 तासांत उत्तर", aboutBtn: "संपर्क साधा",
        s2Tag: "ऑर्डर आणि भाडे", s2H: "ऑर्डर किंवा भाड्याच्या उपकरणांसाठी मदत हवी आहे?", s2P: "सध्याची ऑर्डर ट्रॅक करा, डिलिव्हरी अपडेट मिळवा किंवा भाड्याच्या उपकरणांचे बुकिंग आणि पिकअप व्यवस्थापित करा — आमची टीम सर्व गोष्टींत मदत करेल.", s2Btn: "मेसेज पाठवा",
        s3Tag: "पीक सल्ला", s3H: "तुमच्या पिकाबद्दल सल्ला हवा आहे?", s3P: "पीक निगा, कीड नियंत्रण आणि मंडई भावाबद्दल आमच्या तज्ज्ञ टीमकडून सल्ला घ्या.", s3Btn: "तज्ज्ञांना विचारा",
        s4Tag: "गुणवत्ता आणि पॅकिंग", s4H: "तुमची ऑर्डर कशी पॅक होते हे जाणून घ्यायचंय?", s4P: "प्रत्येक ऑर्डर आमच्या फॅसिलिटीमध्ये स्वच्छतेने वेगळी केली, तपासली आणि पॅक केली जाते — दरवेळी ताजी, सुरक्षित आणि विश्वासार्ह.", s4Btn: "प्रक्रियेबद्दल विचारा",
        s5Tag: "फीडबॅक आणि सहाय्य", s5H: "काही फीडबॅक किंवा तक्रार आहे?", s5P: "काय चांगलं चालू आहे आणि काय नाही ते आम्हाला सांगा — आम्ही प्रत्येक मेसेज वाचतो आणि लवकर कार्यवाही करतो.", s5Btn: "फीडबॅक पाठवा",
        stat1Lbl: "सोडवलेले प्रश्न", stat2Val: "< 24 तास", stat2Lbl: "सरासरी प्रतिसाद वेळ", stat3Val: "6 दिवस", stat3Lbl: "सहाय्य उपलब्धता", stat4Lbl: "प्लॅटफॉर्म रेटिंग", stat4New: "नवीन",
        alertSuccess: "✅ संदेश पाठवला! तिकीट आयडी:",
        errName: "कृपया तुमचे नाव टाका (किमान 2 अक्षरे).",
        errEmail: "कृपया वैध ईमेल पत्ता टाका.",
        errPhone: "कृपया वैध भारतीय मोबाईल नंबर टाका.",
        errSubject: "कृपया विषय निवडा.",
        errMessage: "कृपया संदेश लिहा (किमान 10 अक्षरे).",
        errSessionExpired: "तुमचे सत्र संपले आहे. कृपया पुन्हा प्रयत्न करा.",
        errDuplicateSubmit: "हा संदेश आधीच पाठवला गेला आहे असे दिसते.",
        errRateLimited: "तुम्ही खूप लवकर सबमिट करत आहात. कृपया थोडा वेळ थांबून पुन्हा प्रयत्न करा.",
        errInvalidFields: "कृपया हायलाइट केलेले फील्ड तपासा आणि पुन्हा प्रयत्न करा.",
        errSaveFailed: "संदेश सेव्ह करताना अडचण आली. कृपया पुन्हा प्रयत्न करा.",
        privacyNote: "तुमची माहिती फक्त तुमच्या प्रश्नाचे उत्तर देण्यासाठी वापरली जाते आणि इतर कोणाशीही शेअर केली जात नाही."
    }
};

/** Maps a PHP-side error code to the matching translation key. */
const CU_ERROR_CODE_KEY = {
    session_expired:   'errSessionExpired',
    duplicate_submit:  'errDuplicateSubmit',
    rate_limited:      'errRateLimited',
    invalid_fields:    'errInvalidFields',
    save_failed:       'errSaveFailed'
};

/** Apply the translations for the given language to every id'd element on the page. */
function applyContactTranslations(lang) {
    const t = ContactT[lang] || ContactT.en;
    const setText = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.textContent = val; };
    const setHTML = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.innerHTML = val; };
    const setPh   = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.placeholder = val; };

    setText('cu-badge', t.badge);
    setText('cu-hero-title', t.heroTitle);
    setText('cu-hero-sub', t.heroSub);
    const heroPrimary = document.getElementById('cu-hero-btn-primary');
    if (heroPrimary) heroPrimary.textContent = t.heroBtnPrimary;
    setText('cu-crumb-home', t.crumbHome);
    setText('cu-crumb-current', t.crumbCurrent);

    setText('cu-cards-label', t.cardsLabel);
    setText('cu-cards-title', t.cardsTitle);
    // NOTE: only the descriptive text nodes are translated — the phone
    // number, email address and postal address themselves live in
    // .cu-contact-detail spans that this function never touches.
    setText('cu-card1-title', t.card1Title); setText('cu-card1-desc', t.card1Desc); setText('cu-card1-link', t.card1Link);
    setText('cu-card2-title', t.card2Title); setText('cu-card2-desc', t.card2Desc); setText('cu-card2-link', t.card2Link);
    setText('cu-card3-title', t.card3Title); setText('cu-card3-link', t.card3Link);
    setText('cu-card4-title', t.card4Title); setText('cu-card4-desc', t.card4Desc); setText('cu-card4-link', t.card4Link);

    setText('cu-form-sub', t.formSub);
    const formTitleEl = document.getElementById('cu-form-title');
    if (formTitleEl && formTitleEl.childNodes[0]) { formTitleEl.childNodes[0].nodeValue = t.formTitle; }
    setText('cu-label-name', t.phName); setPh('cu-input-name', t.phName);
    setText('cu-label-email', t.phEmail); setPh('cu-input-email', t.phEmail);
    setText('cu-label-phone', t.phPhone); setPh('cu-input-phone', t.phPhone);
    setText('cu-label-subject', t.phSubject);
    setText('cu-label-message', t.phMessage); setPh('cu-input-message', t.phMessage);

    const subjSel = document.getElementById('cu-subject');
    if (subjSel && subjSel.options.length >= 5) {
        subjSel.options[0].textContent = t.subjDefault;
        subjSel.options[1].textContent = t.subjOrder;
        subjSel.options[2].textContent = t.subjRental;
        subjSel.options[3].textContent = t.subjAdvisory;
        subjSel.options[4].textContent = t.subjOther;
    }
    // Don't stomp on "Sending…" if a submit is currently in flight.
    const submitBtn = document.getElementById('cu-submit-btn');
    if (!submitBtn || !submitBtn.disabled) { setText('cu-submit-text', t.submitBtn); }
    setText('cu-privacy-note', t.privacyNote);

    setText('cu-hours-title', t.hoursTitle);
    setText('cu-day-1', t.day1); setText('cu-day-1-time', t.day1Time);
    setText('cu-day-2', t.day2); setText('cu-day-3', t.day3);
    setText('cu-day-2-status', t.dayClosed); setText('cu-day-3-status', t.dayClosed);
    const badgeEl = document.getElementById('cu-hours-badge');
    const badgeTextEl = document.getElementById('cu-hours-badge-text');
    if (badgeEl && badgeTextEl) {
        badgeTextEl.textContent = badgeEl.getAttribute('data-status') === 'open' ? t.openNowBadge : t.closedNowBadge;
    }
    setHTML('cu-help-text', t.helpText);
    const helpBtn = document.getElementById('cu-help-btn');
    if (helpBtn) helpBtn.innerHTML = '<i class="fa-solid fa-phone" aria-hidden="true"></i> ' + t.helpBtn;
    setText('cu-map-title', t.mapTitle);
    const mapOpenEl = document.getElementById('cu-map-open-link');
    if (mapOpenEl) mapOpenEl.innerHTML = '<i class="fa-solid fa-up-right-from-square" aria-hidden="true"></i> ' + t.mapOpen;

    setText('cu-about-label', t.aboutLabel);
    setText('cu-about-title', t.aboutTitle);
    setText('cu-about-desc', t.aboutDesc);
    setText('cu-about-c1', t.aboutC1); setText('cu-about-c2', t.aboutC2); setText('cu-about-c3', t.aboutC3);
    setText('cu-about-c4', t.aboutC4); setText('cu-about-c5', t.aboutC5); setText('cu-about-c6', t.aboutC6);
    setText('cu-about-btn', t.aboutBtn);

    setText('cs2-tag', t.s2Tag); setText('cs2-h', t.s2H); setText('cs2-p', t.s2P); setText('cs2-btn', t.s2Btn);
    setText('cs3-tag', t.s3Tag); setText('cs3-h', t.s3H); setText('cs3-p', t.s3P); setText('cs3-btn', t.s3Btn);
    setText('cs4-tag', t.s4Tag); setText('cs4-h', t.s4H); setText('cs4-p', t.s4P); setText('cs4-btn', t.s4Btn);
    setText('cs5-tag', t.s5Tag); setText('cs5-h', t.s5H); setText('cs5-p', t.s5P); setText('cs5-btn', t.s5Btn);

    // Stats strip
    setText('cu-stat1-lbl', t.stat1Lbl);
    setText('cu-stat2-val', t.stat2Val); setText('cu-stat2-lbl', t.stat2Lbl);
    setText('cu-stat3-val', t.stat3Val); setText('cu-stat3-lbl', t.stat3Lbl);
    setText('cu-stat4-lbl', t.stat4Lbl);
    const stat4Val = document.getElementById('cu-stat4-val');
    if (stat4Val && stat4Val.getAttribute('data-empty') === '1') { stat4Val.textContent = t.stat4New; }

    // Success alert (only present when the server rendered one).
    setText('cu-alert-success-text', t.alertSuccess);

    // Server-side form-level error alert (only present when the server
    // rendered one) — shows ONLY the message for the currently selected
    // language, never all three languages at once.
    const errAlertBox = document.getElementById('cu-form-message');
    if (errAlertBox && errAlertBox.hasAttribute('data-error-code')) {
        const code = errAlertBox.getAttribute('data-error-code');
        const key = CU_ERROR_CODE_KEY[code];
        if (key) { setText('cu-alert-error-text', t[key]); }
    }

    // Server-flagged (or currently live-invalid) per-field error messages —
    // refreshed on every language switch so they stay in the right language.
    document.querySelectorAll('.cu-field-error[data-error-key]').forEach(function (span) {
        const key = span.getAttribute('data-error-key');
        if (t[key] !== undefined) { span.textContent = t[key]; }
    });
}

window.pageLanguageCallback = applyContactTranslations;

/* ============== Field-level validation ============== */
const CU_FIELD_CONFIG = {
    name:    { errKey: 'errName' },
    email:   { errKey: 'errEmail' },
    phone:   { errKey: 'errPhone' },
    subject: { errKey: 'errSubject' },
    message: { errKey: 'errMessage' }
};

function cuIsValidEmail(value) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(value);
}

/** Mirrors the server-side normaliser: accepts +91 / 91 / 0 prefixes. */
function cuIsValidIndianMobile(raw) {
    let digits = raw.replace(/[^0-9]/g, '');
    if (digits.length === 12 && digits.indexOf('91') === 0) { digits = digits.slice(2); }
    else if (digits.length === 11 && digits.indexOf('0') === 0) { digits = digits.slice(1); }
    return /^[6-9][0-9]{9}$/.test(digits);
}

function cuFieldIsValid(name, value) {
    const v = (value || '').trim();
    switch (name) {
        case 'name':    return v.length >= 2;
        case 'email':   return v !== '' && cuIsValidEmail(v);
        case 'phone':   return v === '' || cuIsValidIndianMobile(v); // optional
        case 'subject': return v !== '';
        case 'message': return v.length >= 10;
        default:        return true;
    }
}

/** Validates one field, toggling its invalid state + inline translated error. Returns true if valid. */
function validateContactField(input) {
    const cfg = CU_FIELD_CONFIG[input.name];
    if (!cfg) return true;
    const lang = localStorage.getItem('agri_lang') || 'en';
    const t = ContactT[lang] || ContactT.en;
    const valid = cuFieldIsValid(input.name, input.value);
    const errorSpan = document.getElementById('err-' + input.name);

    if (valid) {
        input.classList.remove('cu-field-invalid');
        input.setAttribute('aria-invalid', 'false');
        if (errorSpan) { errorSpan.classList.remove('show'); }
    } else {
        input.classList.add('cu-field-invalid');
        input.setAttribute('aria-invalid', 'true');
        if (errorSpan) {
            errorSpan.textContent = t[cfg.errKey] || '';
            errorSpan.classList.add('show');
        }
    }
    return valid;
}

/** Wires up live validation, the character counter, and submit-button loading state. */
function initContactForm() {
    const form = document.getElementById('cu-contact-form');
    const btn = document.getElementById('cu-submit-btn');
    const btnText = document.getElementById('cu-submit-text');
    const messageInput = document.getElementById('cu-input-message');
    const charCounter = document.getElementById('cu-char-counter');
    if (!form || !btn || !btnText) return;

    // Validate on blur (first feedback), and re-validate on input only once
    // a field is already flagged invalid so the error clears the moment the
    // visitor corrects it (per requirement: don't nag while first typing).
    Object.keys(CU_FIELD_CONFIG).forEach(function (name) {
        const input = form.elements[name];
        if (!input) return;
        input.addEventListener('blur', function () { validateContactField(input); });
        input.addEventListener('input', function () {
            if (input.classList.contains('cu-field-invalid')) { validateContactField(input); }
        });
        if (input.tagName === 'SELECT') {
            input.addEventListener('change', function () { validateContactField(input); });
        }
    });

    // Live "0 / 2000" character counter for the message field.
    if (messageInput && charCounter) {
        const updateCounter = function () {
            const len = messageInput.value.length;
            charCounter.textContent = len + ' / 2000';
            charCounter.classList.toggle('limit', len >= 1900);
        };
        messageInput.addEventListener('input', updateCounter);
        updateCounter();
    }

    form.addEventListener('submit', function (e) {
        let firstInvalid = null;
        Object.keys(CU_FIELD_CONFIG).forEach(function (name) {
            const input = form.elements[name];
            if (!input) return;
            const ok = validateContactField(input);
            if (!ok && !firstInvalid) { firstInvalid = input; }
        });

        const lang = localStorage.getItem('agri_lang') || 'en';
        const t = ContactT[lang] || ContactT.en;

        if (firstInvalid) {
            // Prevent submission and restore the button in case it was
            // already mid-submit (guards against a stray double state).
            e.preventDefault();
            btn.disabled = false;
            btn.removeAttribute('aria-busy');
            btnText.textContent = t.submitBtn;
            firstInvalid.focus();
            if (typeof firstInvalid.scrollIntoView === 'function') {
                firstInvalid.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
            return;
        }

        if (btn.disabled) { e.preventDefault(); return; } // guards double-click
        btn.disabled = true;
        btn.setAttribute('aria-busy', 'true');
        btnText.textContent = t.submitBtnSending;
        // Safety net: if the page hasn't navigated away in 15s (e.g. a
        // network hiccup), restore the button so the user can retry.
        setTimeout(function () {
            if (btn.disabled) {
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                btnText.textContent = t.submitBtn;
            }
        }, 15000);
    });

    // If the server round-trip flagged specific fields as invalid, move
    // keyboard focus to the first one so the visitor can fix it right away.
    const serverInvalid = form.querySelector('.cu-field-invalid');
    if (serverInvalid) { serverInvalid.focus(); }
}

/** Smooth-scrolls the viewport to the form + message alert after a failed/successful submission. */
function scrollToContactForm() {
    const target = document.getElementById('cu-form-message') || document.getElementById('cu-form-section');
    if (target) {
        target.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    applyContactTranslations(savedLang);
    initContactForm();
    <?php if ($formErrorCode !== '' || $formSuccess): ?>
    scrollToContactForm();
    <?php endif; ?>
});
</script>

<?php
include __DIR__ . '/../includes/footer.php';
?>
