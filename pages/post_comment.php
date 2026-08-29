<?php
// =====================================================
// AgriCart — Add a comment/reply to an Agri-Connect post
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
$body   = trim($_POST['body'] ?? '');

if ($postId <= 0 || $body === '') {
    respond(['success' => false, 'error' => 'चुकीची माहिती.']);
}
if (mb_strlen($body) > 1000) {
    respond(['success' => false, 'error' => 'उत्तर खूप मोठे आहे (1000 अक्षरांपर्यंत).']);
}

// verify post exists (and get owner, for the notification below)
$stmt = $conn->prepare("SELECT id, user_id, title FROM community_posts WHERE id = ? AND deleted_at IS NULL LIMIT 1");
$stmt->bind_param("i", $postId);
$stmt->execute();
$post = $stmt->get_result()->fetch_assoc();
if (!$post) {
    respond(['success' => false, 'error' => 'हा post सापडला नाही.']);
}

$stmt = $conn->prepare(
    "INSERT INTO comments (post_id, user_id, body, is_approved) VALUES (?, ?, ?, 1)"
);
$stmt->bind_param("iis", $postId, $userId, $body);

if ($stmt->execute()) {
    $nameStmt = $conn->prepare("SELECT full_name FROM users WHERE id = ? LIMIT 1");
    $nameStmt->bind_param("i", $userId);
    $nameStmt->execute();
    $u = $nameStmt->get_result()->fetch_assoc();
    $name = $u['full_name'] ?? 'Farmer';

    // Notify the post owner (unless they replied to their own post)
    if ((int)$post['user_id'] !== $userId) {
        $title = $post['title'] ?: 'तुमचा प्रश्न';
        agri_notify_user(
            $conn, $post['user_id'],
            $name . ' यांनी तुमच्या पोस्टवर उत्तर दिले',
            $title,
            'pages/agri-connect.php#post-' . $postId,
            'community'
        );
    }

    // Raw (unescaped) values — the client builds the DOM safely with textContent,
    // it does not innerHTML this response.
    respond(['success' => true, 'name' => $name, 'body' => $body]);
} else {
    respond(['success' => false, 'error' => 'Reply save करताना अडचण आली, पुन्हा try करा.']);
}
