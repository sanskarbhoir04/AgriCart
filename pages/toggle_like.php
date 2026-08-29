<?php
// =====================================================
// AgriCart — Toggle like on an Agri-Connect forum post
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
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

// verify post exists
$stmt = $conn->prepare("SELECT id FROM community_posts WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $postId);
$stmt->execute();
if (!$stmt->get_result()->fetch_assoc()) {
    respond(['success' => false, 'error' => 'हा post सापडला नाही.']);
}

// Already liked? -> unlike. Else -> like.
$stmt = $conn->prepare("SELECT id FROM post_likes WHERE post_id = ? AND user_id = ? LIMIT 1");
$stmt->bind_param("ii", $postId, $userId);
$stmt->execute();
$existing = $stmt->get_result()->fetch_assoc();

if ($existing) {
    $del = $conn->prepare("DELETE FROM post_likes WHERE id = ?");
    $del->bind_param("i", $existing['id']);
    $del->execute();
    $conn->query("UPDATE community_posts SET likes_count = GREATEST(0, likes_count - 1) WHERE id = " . (int)$postId);
    $liked = false;
} else {
    $ins = $conn->prepare("INSERT INTO post_likes (post_id, user_id) VALUES (?, ?)");
    $ins->bind_param("ii", $postId, $userId);
    $ins->execute();
    $conn->query("UPDATE community_posts SET likes_count = likes_count + 1 WHERE id = " . (int)$postId);
    $liked = true;
}

$countRow = $conn->query("SELECT likes_count FROM community_posts WHERE id = " . (int)$postId)->fetch_assoc();

respond(['success' => true, 'liked' => $liked, 'likes_count' => (int)$countRow['likes_count']]);
