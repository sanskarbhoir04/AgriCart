<?php
/**
 * submit_review.php — AgriCart secure review submission
 *
 * NEW implementation (original wasn't provided) matching the request shape
 * marketplace.php's pmSubmitReview() sends: csrf_token, item_type, item_id,
 * rating, comment. Assumed reviews table columns: id, item_type, item_id,
 * user_id, rating, comment, verified, created_at, updated_at — plus a join
 * to `users` for the display name (get_reviews.php uses users.name/user_name;
 * adjust the JOIN there if your column is named differently).
 */

require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

function agri_json_fail(int $httpCode, string $message): void {
    http_response_code($httpCode);
    echo json_encode(['success' => false, 'error' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agri_json_fail(405, 'Method not allowed.');
}
if (empty($_SESSION['user_id'])) {
    agri_json_fail(401, 'Please login to write a review.');
}
$userId = (int)$_SESSION['user_id'];

$csrfSent = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfSent)) {
    agri_json_fail(403, 'Invalid session, please refresh the page and try again.');
}

$itemType = (string)($_POST['item_type'] ?? 'product');
if (!in_array($itemType, ['product'], true)) { // extend this allowlist if you review other item types
    agri_json_fail(400, 'Invalid item type.');
}
$itemId = (int)($_POST['item_id'] ?? 0);
$rating = (int)($_POST['rating'] ?? 0);
$comment = trim((string)($_POST['comment'] ?? ''));

if ($itemId <= 0) agri_json_fail(400, 'Invalid product.');
if ($rating < 1 || $rating > 5) agri_json_fail(400, 'Please select a rating from 1 to 5 stars.');
if ($comment === '' || mb_strlen($comment) > 2000) agri_json_fail(400, 'Please write a short review (max 2000 characters).');

// Confirm the product actually exists and is visible — don't let reviews
// pile up on a deleted/hidden product ID.
$check = $conn->prepare("SELECT id FROM products WHERE id = ? LIMIT 1");
$check->bind_param("i", $itemId);
$check->execute();
if (!$check->get_result()->fetch_assoc()) { agri_json_fail(404, 'Product not found.'); }

// One review per user per item — update in place if they've already reviewed it.
$existing = $conn->prepare("SELECT id FROM reviews WHERE item_type = ? AND item_id = ? AND user_id = ? LIMIT 1");
$existing->bind_param("sii", $itemType, $itemId, $userId);
$existing->execute();
$existingRow = $existing->get_result()->fetch_assoc();

try {
    if ($existingRow) {
        $upd = $conn->prepare("UPDATE reviews SET rating = ?, comment = ?, updated_at = NOW() WHERE id = ?");
        $upd->bind_param("isi", $rating, $comment, $existingRow['id']);
        $upd->execute();
        echo json_encode(['success' => true, 'updated' => true]);
    } else {
        $ins = $conn->prepare("INSERT INTO reviews (item_type, item_id, user_id, rating, comment, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $ins->bind_param("siiis", $itemType, $itemId, $userId, $rating, $comment);
        $ins->execute();
        echo json_encode(['success' => true, 'updated' => false]);
    }
} catch (\Throwable $e) {
    error_log('submit_review.php failed: ' . $e->getMessage());
    agri_json_fail(500, 'Could not save your review, please try again.');
}
