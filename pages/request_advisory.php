<?php
// =====================================================
// AgriCart — Submit a new Expert Advisory request
// (AJAX endpoint, called from my_activity.php)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
header('Content-Type: application/json');

function respond($arr) { echo json_encode($arr); exit; }

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'error' => 'कृपया आधी login करा.']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && function_exists('csrf_require')) {
    csrf_require('json');
}

$userId  = (int)$_SESSION['user_id'];
$crop    = trim($_POST['crop'] ?? '');
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if ($crop === '' || $subject === '' || $message === '') {
    respond(['success' => false, 'error' => 'कृपया crop, subject आणि तुमचा प्रश्न सर्व भरा.']);
}
if (mb_strlen($crop) > 100)    { $crop = mb_substr($crop, 0, 100); }
if (mb_strlen($subject) > 200) { $subject = mb_substr($subject, 0, 200); }
if (mb_strlen($message) > 2000) { $message = mb_substr($message, 0, 2000); }

// ── Optional photo of the crop / problem ──
$imagePath = null;
if (!empty($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $file = $_FILES['image'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        respond(['success' => false, 'error' => 'फोटो अपलोड करताना अडचण आली.']);
    }
    if ($file['size'] > 5 * 1024 * 1024) {
        respond(['success' => false, 'error' => 'फोटो 5MB पेक्षा लहान असावा.']);
    }

    $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $finfo = @finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? @finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) { finfo_close($finfo); }
    $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

    // Fail closed: if finfo couldn't determine a MIME type at all, treat that
    // as a rejection rather than falling back to trusting the filename alone.
    if (!in_array($ext, $allowedExt, true) || !$mime || !in_array($mime, $allowedMime, true)) {
        respond(['success' => false, 'error' => 'फक्त JPG, PNG किंवा WEBP फोटो अपलोड करा.']);
    }

    $uploadDir = __DIR__ . '/../assets/uploads/advisory';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $fileName = 'adv_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $fileName;

    if (@move_uploaded_file($file['tmp_name'], $destPath)) {
        $imagePath = 'assets/uploads/advisory/' . $fileName;
    } else {
        respond(['success' => false, 'error' => 'फोटो सेव्ह करता आले नाही, पुन्हा try करा.']);
    }
}

$requestNumber = 'ADV-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

$stmt = $conn->prepare(
    "INSERT INTO advisory_requests (user_id, request_number, crop, subject, message, image, status)
     VALUES (?, ?, ?, ?, ?, ?, 'pending')"
);
if (!$stmt) {
    respond(['success' => false, 'error' => 'advisory_requests table सापडली नाही. कृपया setup/advisory_requests_setup.sql run करा.']);
}
$stmt->bind_param("isssss", $userId, $requestNumber, $crop, $subject, $message, $imagePath);

if ($stmt->execute()) {
    respond(['success' => true, 'request_number' => $requestNumber]);
} else {
    respond(['success' => false, 'error' => 'Request save करताना अडचण आली, पुन्हा try करा.']);
}
