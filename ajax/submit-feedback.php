<?php
// =====================================================================
// ajax/submit-feedback.php — Stores a feedback submission from the
// footer feedback form (see includes/footer.php). Public endpoint,
// no login required, but attaches user_id when the visitor is logged in.
// =====================================================================
if (session_status() === PHP_SESSION_NONE) { session_start(); }
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$response = ['success' => false];

$message = trim($_POST['feedback'] ?? '');
$rating  = isset($_POST['rating']) ? round(((float)$_POST['rating']) * 2) / 2 : 0; // supports half-star values
$page    = trim($_POST['page'] ?? '');
$userId  = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

if ($message === '') {
    http_response_code(422);
    $response['error'] = 'Feedback message is required.';
    echo json_encode($response);
    exit;
}
if ($rating < 0.5 || $rating > 5) {
    $rating = null; // rating is optional / was cleared by the user
}
if (mb_strlen($message) > 2000) {
    $message = mb_substr($message, 0, 2000);
}
if ($page !== '' && strlen($page) > 255) {
    $page = substr($page, 0, 255);
}

$stmt = $conn->prepare("INSERT INTO feedback (message, rating, page, user_id, status, created_at) VALUES (?, ?, ?, ?, 'new', NOW())");
$stmt->bind_param("sdsi", $message, $rating, $page, $userId);
$response['success'] = $stmt->execute();
if (!$response['success']) {
    $response['error'] = 'Could not save feedback.';
}

echo json_encode($response);
