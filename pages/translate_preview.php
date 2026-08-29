<?php
// =====================================================================
// pages/translate_preview.php
// Small JSON endpoint used by sell_product.php's live translation
// preview (debounced on keystroke). Requires login + a valid CSRF
// token, same as insert_product.php. Never trusts the client with
// anything except the text to translate — the actual translation
// happens here on the server via includes/agri_translate.php.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_translate.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'login_required']);
    exit;
}

if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'invalid_csrf']);
    exit;
}

$name = trim($_POST['name'] ?? '');
$inputLang = trim($_POST['input_language'] ?? 'auto');
if (!in_array($inputLang, ['auto', 'en', 'mr', 'hi'], true)) { $inputLang = 'auto'; }

if ($name === '') {
    echo json_encode(['success' => true, 'en' => '', 'mr' => '', 'hi' => '', 'detected' => 'en']);
    exit;
}

// mb_substr guard: keep translation preview requests cheap/bounded.
if (mb_strlen($name, 'UTF-8') > 150) {
    $name = mb_substr($name, 0, 150, 'UTF-8');
}

try {
    $detected = $inputLang === 'auto' ? agri_detect_language($name) : $inputLang;
    $translated = agri_translate_product_name($name, $inputLang);
    echo json_encode([
        'success'  => true,
        'en'       => $translated['en'],
        'mr'       => $translated['mr'],
        'hi'       => $translated['hi'],
        'detected' => $detected,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    // Translation failure must never block the form — fall back to the
    // original text in every language so the preview still shows something.
    echo json_encode([
        'success'  => true,
        'en'       => $name,
        'mr'       => $name,
        'hi'       => $name,
        'detected' => 'en',
    ], JSON_UNESCAPED_UNICODE);
}
