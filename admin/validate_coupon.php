<?php
// =====================================================================
// pages/validate_coupon.php — Public endpoint the storefront checkout
// calls to check a coupon code against the real "coupons" table.
// Falls back to the old hardcoded AGRI15 (15%) if the table doesn't
// exist yet, so nothing breaks before the migration SQL is run.
// =====================================================================
header('Content-Type: application/json');
include __DIR__ . '/../includes/db.php';

$code        = strtoupper(trim($_GET['code'] ?? $_POST['code'] ?? ''));
$orderAmount = (float)($_GET['order_amount'] ?? $_POST['order_amount'] ?? 0);

$response = ['success' => false];

if ($code === '') {
    $response['error'] = 'Enter a coupon code.';
    echo json_encode($response);
    exit;
}

$coupon = null;
try {
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
} catch (\Throwable $e) {
    // coupons table not created yet — fall back to the original hardcoded coupon.
    if ($code === 'AGRI15') {
        $coupon = ['id' => 0, 'code' => 'AGRI15', 'discount_type' => 'percent', 'discount_value' => 15, 'min_order_amount' => 0, 'max_discount_amount' => null, 'usage_limit' => null, 'used_count' => 0, 'active' => 1, 'expiry_date' => null];
    }
}

if (!$coupon || !empty($coupon['deleted_at'])) {
    $response['error'] = 'Invalid coupon code.';
    echo json_encode($response);
    exit;
}

if (!$coupon['active']) {
    $response['error'] = 'This coupon is no longer active.';
    echo json_encode($response);
    exit;
}

if (!empty($coupon['expiry_date']) && strtotime($coupon['expiry_date']) < strtotime(date('Y-m-d'))) {
    $response['error'] = 'This coupon has expired.';
    echo json_encode($response);
    exit;
}

if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
    $response['error'] = 'This coupon has reached its usage limit.';
    echo json_encode($response);
    exit;
}

if ($orderAmount < (float)$coupon['min_order_amount']) {
    $response['error'] = 'Minimum order of ₹' . number_format($coupon['min_order_amount'], 0) . ' required for this coupon.';
    echo json_encode($response);
    exit;
}

// Work out the discount amount.
$discount = $coupon['discount_type'] === 'flat'
    ? (float)$coupon['discount_value']
    : round($orderAmount * ((float)$coupon['discount_value'] / 100));

if (!empty($coupon['max_discount_amount']) && $discount > (float)$coupon['max_discount_amount']) {
    $discount = (float)$coupon['max_discount_amount'];
}
if ($discount > $orderAmount) { $discount = $orderAmount; }

echo json_encode([
    'success'        => true,
    'code'           => $coupon['code'],
    'discount_type'  => $coupon['discount_type'],
    'discount_value' => (float)$coupon['discount_value'],
    'discount_amount'=> $discount,
]);
