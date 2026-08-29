<?php
// =====================================================
// AgriCart — Report an Agri-Connect forum post
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

$userId = (int)$_SESSION['user_id'];
$postId = (int)($_POST['post_id'] ?? 0);
$reason = trim($_POST['reason'] ?? '');
if (mb_strlen($reason) > 255) { $reason = mb_substr($reason, 0, 255); }

if ($postId <= 0) {
    respond(['success' => false, 'error' => 'चुकीची माहिती.']);
}

$stmt = $conn->prepare("SELECT id FROM community_posts WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $postId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    respond(['success' => false, 'error' => 'हा post सापडला नाही.']);
}

// One report per user per post — repeated clicks just confirm, no duplicate rows.
$stmt = $conn->prepare("SELECT id FROM post_reports WHERE post_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();
if ($stmt->get_result()->fetch_assoc()) {
    respond(['success' => true, 'already_reported' => true]);
}

$ins = $conn->prepare("INSERT INTO post_reports (post_id, user_id, reason) VALUES (?, ?, ?)");
$ins->bind_param("iis", $postId, $userId, $reason);

if ($ins->execute()) {
    respond(['success' => true]);
} else {
    respond(['success' => false, 'error' => 'Report save झाले नाही, पुन्हा try करा.']);
}
