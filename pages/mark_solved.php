<?php
// =====================================================
// AgriCart — Mark an Agri-Connect post as Solved
// Only the post's own author OR an admin can toggle this.
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

$stmt = $conn->prepare("SELECT user_id, is_solved FROM community_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();

if (!$post) {
    respond(['success' => false, 'error' => 'हा post सापडला नाही.']);
}

$rStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
$rStmt->bind_param("i", $userId);
$rStmt->execute();
$roleRow = $rStmt->get_result()->fetch_assoc();
$isAdmin = ($roleRow && $roleRow['role'] === 'admin');

if ((int)$post['user_id'] !== $userId && !$isAdmin) {
    respond(['success' => false, 'error' => 'फक्त तुमचाच प्रश्न किंवा admin Solved करू शकतो.']);
}

$newVal = $post['is_solved'] ? 0 : 1;
$upd = $conn->prepare("UPDATE community_posts SET is_solved = ? WHERE id = ?");
$upd->bind_param("ii", $newVal, $postId);

if ($upd->execute()) {
    respond(['success' => true, 'is_solved' => (bool)$newVal]);
} else {
    respond(['success' => false, 'error' => 'Update करता आले नाही.']);
}
