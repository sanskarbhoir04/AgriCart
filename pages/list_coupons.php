<?php
/**
 * list_coupons.php — AgriCart public coupon listing (offer strip + coupon
 * chips banner). Read-only, so no CSRF/auth needed — but still only ever
 * returns coupons that are genuinely usable right now (item 13).
 *
 * Matches confirmed `coupons` schema: code, discount_type, discount_value,
 * min_order_amount, max_discount_amount, usage_limit, used_count, active,
 * expiry_date, deleted_at. (No start_date column — a coupon is usable from
 * creation until expiry_date.)
 */

require_once __DIR__ . '/../includes/security.php';
agri_session_start();
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$coupons = [];
try {
    $sql = "SELECT code, discount_type, discount_value, min_order_amount, max_discount_amount,
                   usage_limit, used_count, expiry_date
            FROM coupons
            WHERE active = 1
              AND deleted_at IS NULL
              AND (expiry_date IS NULL OR expiry_date >= CURDATE())
              AND (usage_limit IS NULL OR used_count < usage_limit)
            ORDER BY id DESC
            LIMIT 20";
    $res = $conn->query($sql);
    if ($res === false) { throw new \Exception($conn->error ?: 'query failed'); }
    while ($row = $res->fetch_assoc()) {
        $coupons[] = [
            'code' => $row['code'],
            'discount_type' => $row['discount_type'],
            'discount_value' => (float)$row['discount_value'],
            'min_order_amount' => (float)($row['min_order_amount'] ?? 0),
        ];
    }
} catch (\Throwable $e) {
    error_log('list_coupons.php failed: ' . $e->getMessage());
    // Fail safe — an empty coupon strip is fine, a broken query response isn't.
    $coupons = [];
}

echo json_encode(['success' => true, 'coupons' => $coupons]);
