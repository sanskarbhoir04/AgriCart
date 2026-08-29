<?php
// =====================================================
// AgriCart — Create a new Agri-Connect forum post
// Phase 1 upgrade: title, crop, district, optional image.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
agri_connect_bootstrap_schema($conn);
header('Content-Type: application/json');

function respond($arr) { echo json_encode($arr); exit; }

if (!isset($_SESSION['user_id'])) {
    respond(['success' => false, 'error' => 'कृपया आधी login करा.']);
}
agri_csrf_check();

$userId   = (int)$_SESSION['user_id'];
$title    = trim($_POST['title'] ?? '');
$body     = trim($_POST['body'] ?? '');
$category = trim($_POST['category'] ?? 'general');
$crop     = trim($_POST['crop'] ?? '');
$district = trim($_POST['district'] ?? '');

$allowedCategories = ['question', 'crop', 'pest', 'market', 'schemes', 'general'];
if (!in_array($category, $allowedCategories, true)) { $category = 'general'; }

if ($title === '') {
    respond(['success' => false, 'error' => 'कृपया प्रश्नाचे शीर्षक (Title) लिहा.']);
}
if (mb_strlen($title) > 200) {
    respond(['success' => false, 'error' => 'शीर्षक खूप मोठे आहे (200 अक्षरांपर्यंत).']);
}
if ($body === '') {
    respond(['success' => false, 'error' => 'कृपया सविस्तर वर्णन (Description) लिहा.']);
}
if (mb_strlen($body) > 2000) {
    respond(['success' => false, 'error' => 'संदेश खूप मोठा आहे (2000 अक्षरांपर्यंत).']);
}
if (mb_strlen($crop) > 100)     { $crop = mb_substr($crop, 0, 100); }
if (mb_strlen($district) > 100) { $district = mb_substr($district, 0, 100); }

// ── Optional image upload ──
$imagesJson = null;
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

    $uploadDir = __DIR__ . '/../assets/uploads/community';
    if (!is_dir($uploadDir)) {
        @mkdir($uploadDir, 0755, true);
    }
    $fileName = 'post_' . $userId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destPath = $uploadDir . '/' . $fileName;

    if (@move_uploaded_file($file['tmp_name'], $destPath)) {
        $imagesJson = json_encode(['assets/uploads/community/' . $fileName]);
    } else {
        respond(['success' => false, 'error' => 'फोटो सेव्ह करता आले नाही, पुन्हा try करा.']);
    }
}

$stmt = $conn->prepare(
    "INSERT INTO community_posts (user_id, title, body, category, crop, district, images, likes_count, is_approved)
     VALUES (?, ?, ?, ?, ?, ?, ?, 0, 1)"
);
$stmt->bind_param("issssss", $userId, $title, $body, $category, $crop, $district, $imagesJson);

if ($stmt->execute()) {
    respond(['success' => true, 'post_id' => $conn->insert_id]);
} else {
    respond(['success' => false, 'error' => 'Post save करताना अडचण आली, पुन्हा try करा.']);
}
