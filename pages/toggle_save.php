<?php
// =====================================================
// AgriCart — Toggle "Save" (bookmark) on an Agri-Connect forum post
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

if ($postId <= 0) {
    respond(['success' => false, 'error' => 'चुकीची माहिती.']);
}

$stmt = $conn->prepare("SELECT id FROM community_posts WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $postId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    respond(['success' => false, 'error' => 'हा post सापडला नाही.']);
}

$stmt = $conn->prepare("SELECT id FROM post_saves WHERE post_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $del = $conn->prepare("DELETE FROM post_saves WHERE id = ?");
    $del->bind_param("i", $existing['id']);
    $del->execute();
    $saved = false;
} else {
    $ins = $conn->prepare("INSERT INTO post_saves (post_id, user_id) VALUES (?, ?)");
    $ins->bind_param("ii", $postId, $userId);
    $ins->execute();
    $saved = true;
}

respond(['success' => true, 'saved' => $saved]);
