<?php
/**
 * get_reviews.php — AgriCart read-only review listing for a product.
 * NEW implementation (original wasn't provided). Matches marketplace.php's
 * pmLoadReviews(), which GETs ?item_type=product&item_id=<id> and expects
 * { breakdown: {1..5: count}, count, reviews: [{name, verified, rating,
 * comment}], my_review: {rating, comment}|null }.
 *
 * Reviewer display name: tries users.name, falls back to users.user_name —
 * verify which column your `users` table actually uses and trim the other
 * branch if only one exists.
 */

require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$itemType = (string)($_GET['item_type'] ?? 'product');
$itemId = (int)($_GET['item_id'] ?? 0);
if ($itemId <= 0) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid product id.']);
    exit;
}

$breakdown = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
$reviews = [];
$count = 0;
$myReview = null;

try {
    // Reviewer name: coalesce across a couple of likely column names so this
    // works whether your users table uses `name` or `user_name`.
    $sql = "SELECT r.rating, r.comment, r.user_id, r.created_at,
                   COALESCE(u.name, u.user_name, 'AgriCart Customer') AS reviewer_name
            FROM reviews r
            LEFT JOIN users u ON u.id = r.user_id
            WHERE r.item_type = ? AND r.item_id = ?
            ORDER BY r.created_at DESC
            LIMIT 100";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $itemType, $itemId);
    $stmt->execute();
    $res = $stmt->get_result();
    $currentUserId = $_SESSION['user_id'] ?? null;
    while ($row = $res->fetch_assoc()) {
        $rating = max(1, min(5, (int)$row['rating']));
        $breakdown[$rating]++;
        $count++;
        $reviews[] = [
            'name' => $row['reviewer_name'],
            'rating' => $rating,
            'comment' => $row['comment'],
            // "Verified buyer" = this user has an order containing this
            // product. Best-effort — if the orders/order_items join fails
            // (different column names), we just omit the badge rather than
            // erroring the whole review list out.
            'verified' => agri_is_verified_buyer($conn, (int)$row['user_id'], $itemId),
        ];
        if ($currentUserId !== null && (int)$row['user_id'] === (int)$currentUserId) {
            $myReview = ['rating' => $rating, 'comment' => $row['comment']];
        }
    }
} catch (\Throwable $e) {
    error_log('get_reviews.php failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Could not load reviews.']);
    exit;
}

function agri_is_verified_buyer(mysqli $conn, int $userId, int $productId): bool {
    static $cache = [];
    $key = $userId . ':' . $productId;
    if (isset($cache[$key])) return $cache[$key];
    try {
        $stmt = $conn->prepare("SELECT 1 FROM orders o
                                 JOIN order_items oi ON oi.order_id = o.id
                                 WHERE o.user_id = ? AND oi.product_id = ? LIMIT 1");
        $stmt->bind_param("ii", $userId, $productId);
        $stmt->execute();
        $cache[$key] = (bool)$stmt->get_result()->fetch_assoc();
    } catch (\Throwable $e) {
        $cache[$key] = false;
    }
    return $cache[$key];
}

echo json_encode([
    'success' => true,
    'breakdown' => $breakdown,
    'count' => $count,
    'reviews' => $reviews,
    'my_review' => $myReview,
]);
