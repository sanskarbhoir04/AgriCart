<?php
/**
 * validate_coupon.php — AgriCart secure coupon validation
 *
 * Matches your confirmed `coupons` schema:
 *   id, code, discount_type('percent'/'flat'), discount_value,
 *   min_order_amount, max_discount_amount, usage_limit, used_count,
 *   active (tinyint), expiry_date, created_at, deleted_at
 * (no start_date column, no per_user_limit column — per-user reuse is
 * checked against the `coupon_usages` table from migrations.sql instead.)
 *
 * marketplace.php's applyCoupon() POSTs { csrf_token, code, items: JSON[{id,qty}] }.
 * The order amount is always recomputed here from the DB — never trusted
 * from the browser (item 14).
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
    agri_json_fail(401, 'Please login to use a coupon.');
}
$userId = (int)$_SESSION['user_id'];

$csrfSent = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfSent)) {
    agri_json_fail(403, 'Invalid session, please refresh the page and try again.');
}

$code = strtoupper(trim((string)($_POST['code'] ?? '')));
if ($code === '') agri_json_fail(400, 'Please enter a coupon code.');

$items = json_decode((string)($_POST['items'] ?? '[]'), true);
if (!is_array($items) || count($items) === 0) {
    agri_json_fail(400, 'Your cart is empty.');
}

// Recompute the order amount from the DB — never trust a total sent by the browser.
$requested = [];
foreach ($items as $it) {
    if (!isset($it['id'])) continue;
    $pid = (int)$it['id'];
    $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
    if ($pid > 0 && $qty > 0) { $requested[$pid] = ($requested[$pid] ?? 0) + $qty; }
}
if (empty($requested)) agri_json_fail(400, 'Your cart is empty.');

$productIds = array_keys($requested);
$placeholders = implode(',', array_fill(0, count($productIds), '?'));
$types = str_repeat('i', count($productIds));
$stmt = $conn->prepare("SELECT id, price, discount_price, is_active FROM products WHERE id IN ($placeholders)");
$stmt->bind_param($types, ...$productIds);
$stmt->execute();
$res = $stmt->get_result();
$rowsById = [];
while ($row = $res->fetch_assoc()) { $rowsById[(int)$row['id']] = $row; }

$orderAmount = 0.0;
foreach ($requested as $pid => $qty) {
    if (!isset($rowsById[$pid]) || (int)$rowsById[$pid]['is_active'] !== 1) continue; // skip unavailable items silently, same as checkout will
    $p = $rowsById[$pid];
    $unitPrice = ($p['discount_price'] !== null && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['price'])
        ? (float)$p['discount_price'] : (float)$p['price'];
    $orderAmount += $unitPrice * $qty;
}

$couponStmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND deleted_at IS NULL LIMIT 1");
$couponStmt->bind_param("s", $code);
$couponStmt->execute();
$coupon = $couponStmt->get_result()->fetch_assoc();

if (!$coupon) agri_json_fail(200, 'Invalid coupon code.');
if (!(int)($coupon['active'] ?? 0)) agri_json_fail(200, 'This coupon is no longer active.');
if (!empty($coupon['expiry_date']) && strtotime($coupon['expiry_date']) < strtotime('today')) {
    agri_json_fail(200, 'This coupon has expired.');
}
if ((float)($coupon['min_order_amount'] ?? 0) > 0 && $orderAmount < (float)$coupon['min_order_amount']) {
    agri_json_fail(200, 'Minimum order amount of ₹' . $coupon['min_order_amount'] . ' required for this coupon.');
}
if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
    agri_json_fail(200, 'This coupon has reached its usage limit.');
}
// Per-user reuse check via coupon_usages (created by migrations.sql) — treated
// as a 1-per-user limit since `coupons` has no dedicated per_user_limit column.
try {
    $u = $conn->prepare("SELECT COUNT(*) c FROM coupon_usages WHERE coupon_id = ? AND user_id = ?");
    $u->bind_param("ii", $coupon['id'], $userId);
    $u->execute();
    $used = (int)$u->get_result()->fetch_assoc()['c'];
    if ($used >= 1) {
        agri_json_fail(200, 'You have already used this coupon.');
    }
} catch (\Throwable $e) { /* coupon_usages missing — skip per-user check */ }

$discountType = $coupon['discount_type']; // 'percent' | 'flat'
$discountValue = (float)$coupon['discount_value'];
$discount = $discountType === 'percent' ? ($orderAmount * $discountValue / 100) : $discountValue;
if (!empty($coupon['max_discount_amount']) && $discount > (float)$coupon['max_discount_amount']) {
    $discount = (float)$coupon['max_discount_amount'];
}
$discount = round(min($discount, $orderAmount), 2);

echo json_encode([
    'success' => true,
    'code' => $coupon['code'],
    'discount_type' => $discountType,
    'discount_value' => $discountValue,
    'discount_amount' => $discount,
    'order_amount' => round($orderAmount, 2),
]);
