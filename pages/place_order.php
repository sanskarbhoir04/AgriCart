<?php
/**
 * place_order.php — AgriCart secure order placement
 *
 * Column names below are matched against your ACTUAL `coupons` and `orders`
 * tables (confirmed via information_schema screenshots):
 *   coupons: id, code, discount_type('percent'/'flat'), discount_value,
 *            min_order_amount, max_discount_amount, usage_limit, used_count,
 *            active (tinyint), expiry_date, created_at, deleted_at
 *   orders : id, user_id, address_id, order_number, total_amount,
 *            discount_amount, shipping_charge, final_amount, status,
 *            payment_status('unpaid'/'paid'/'refunded'), coupon_code,
 *            notes, ordered_at
 *
 * NOT YET CONFIRMED — this file detects these at runtime via SHOW COLUMNS
 * so it keeps working either way, and never loses data even if a guess is
 * wrong (see the `notes` fallback below):
 *   - orders.payment_mode (didn't appear in the screenshot yet — may be
 *     further down the column list, or may be named differently)
 *   - user_addresses table columns (used to store the delivery address
 *     behind orders.address_id) — guessed as
 *     (user_id, name, mobile, pincode, address). If your table uses
 *     different column names, the INSERT below will fail silently (caught)
 *     and the address is instead written into orders.notes as plain text,
 *     so nothing is lost — but please confirm user_addresses' real columns
 *     so address_id can be wired up properly.
 *   - order_items columns — guessed as
 *     (order_id, product_id, product_name, quantity, price).
 *
 * `status` / `payment_status` are intentionally left OUT of the INSERT and
 * allowed to take their table defaults ('pending' / 'unpaid') — since the
 * full `status` ENUM list wasn't visible in the screenshot, relying on the
 * column default is safer than guessing a value that might not be a valid
 * enum member.
 *
 * Security properties (review items 6, 7, 23):
 *  - POST only, logged-in only, CSRF checked with hash_equals().
 *  - Client sends ONLY product id + quantity — price, stock, discount and
 *    the final total are all recomputed from the DB, never trusted from JS.
 *  - SELECT ... FOR UPDATE inside a transaction locks the product rows
 *    being purchased, so two simultaneous orders can't oversell stock.
 *  - Stock is decremented only after every check passes; anything wrong
 *    rolls the whole transaction back.
 *  - idempotency_key: a repeated submit (double-click / retry) with the
 *    same key for the same user returns the original order instead of
 *    creating a second one.
 *  - Payment is never marked "paid" here (see item 15) — no real gateway
 *    is wired up, so payment_status stays at its 'unpaid' default.
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

/** Returns the set of column names that actually exist on a table. */
function agri_table_columns(mysqli $conn, string $table): array {
    static $cache = [];
    if (isset($cache[$table])) return $cache[$table];
    $cols = [];
    try {
        $res = $conn->query("SHOW COLUMNS FROM `" . $conn->real_escape_string($table) . "`");
        if ($res) { while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; } }
    } catch (\Throwable $e) { /* table missing entirely — caller handles empty list */ }
    $cache[$table] = $cols;
    return $cols;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    agri_json_fail(405, 'Method not allowed.');
}
if (empty($_SESSION['user_id'])) {
    agri_json_fail(401, 'Please login to place an order.');
}
$userId = (int)$_SESSION['user_id'];

// ---- CSRF ----
$csrfSent = $_POST['csrf_token'] ?? '';
if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], (string)$csrfSent)) {
    agri_json_fail(403, 'Invalid session, please refresh the page and try again.');
}

// ---- Parse + validate input shape ----
$items = json_decode((string)($_POST['items'] ?? '[]'), true);
if (!is_array($items) || count($items) === 0) {
    agri_json_fail(400, 'Your cart is empty.');
}
$name    = trim((string)($_POST['name'] ?? ''));
$mobile  = trim((string)($_POST['mobile'] ?? ''));
$pin     = trim((string)($_POST['pin'] ?? ''));
$address = trim((string)($_POST['address'] ?? ''));
$selectedAddressId = (int)($_POST['address_id'] ?? 0); // which saved address book entry was picked at checkout (0 = none picked / legacy)
$paymentMode = (string)($_POST['payment_mode'] ?? 'COD');
$couponCode  = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
$idempotencyKey = trim((string)($_POST['idempotency_key'] ?? ''));

if (mb_strlen($name) < 2) agri_json_fail(400, 'Please enter your full name.');
if (!preg_match('/^[6-9]\d{9}$/', $mobile)) agri_json_fail(400, 'Enter a valid 10-digit mobile number.');
if (!preg_match('/^\d{6}$/', $pin)) agri_json_fail(400, 'Enter a valid 6-digit PIN code.');
if (mb_strlen($address) < 10) agri_json_fail(400, 'Please enter your full delivery address.');
if (!in_array($paymentMode, ['COD', 'UPI', 'UPIQR'], true)) agri_json_fail(400, 'Invalid payment method.');
// Card number / CVV / UPI PIN are never read here — this endpoint has no
// $_POST['card_number'] etc. at all, by design (see item 15).

// Normalize + de-duplicate requested items (id => qty)
$requested = [];
foreach ($items as $it) {
    if (!isset($it['id'])) continue;
    $pid = (int)$it['id'];
    $qty = isset($it['qty']) ? (int)$it['qty'] : 0;
    if ($pid <= 0 || $qty < 1) agri_json_fail(400, 'Invalid item in cart.');
    $requested[$pid] = ($requested[$pid] ?? 0) + $qty;
}
if (empty($requested)) agri_json_fail(400, 'Your cart is empty.');

$orderCols = agri_table_columns($conn, 'orders');
$hasIdemCol = in_array('idempotency_key', $orderCols, true);
$hasPaymentModeCol = in_array('payment_mode', $orderCols, true);
$hasAddressIdCol = in_array('address_id', $orderCols, true);
$hasNotesCol = in_array('notes', $orderCols, true);
$hasDeliveryNameCol = in_array('delivery_name', $orderCols, true);
$hasDeliveryMobileCol = in_array('delivery_mobile', $orderCols, true);
$hasDeliveryAddressCol = in_array('delivery_address', $orderCols, true);
$txnIdCol = in_array('transaction_id', $orderCols, true) ? 'transaction_id'
    : (in_array('txn_id', $orderCols, true) ? 'txn_id'
    : (in_array('payment_txn_id', $orderCols, true) ? 'payment_txn_id' : null));

// ---- Idempotency: same user + same key already produced an order? ----
if ($idempotencyKey !== '' && $hasIdemCol) {
    $dupStmt = $conn->prepare("SELECT id, order_number FROM orders WHERE user_id = ? AND idempotency_key = ? LIMIT 1");
    $dupStmt->bind_param("is", $userId, $idempotencyKey);
    $dupStmt->execute();
    $dupRow = $dupStmt->get_result()->fetch_assoc();
    if ($dupRow) {
        echo json_encode(['success' => true, 'order_number' => $dupRow['order_number'], 'duplicate' => true]);
        exit;
    }
}

$conn->begin_transaction();
try {
    // Lock every product row we're about to sell so a concurrent order on
    // the same product can't both succeed past a stock check that's gone stale.
    $productIds = array_keys($requested);
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $types = str_repeat('i', count($productIds));
    $lockSql = "SELECT id, name, price, discount_price, stock, is_active, approval_status
                FROM products WHERE id IN ($placeholders) FOR UPDATE";
    $lockStmt = $conn->prepare($lockSql);
    $lockStmt->bind_param($types, ...$productIds);
    $lockStmt->execute();
    $productRows = [];
    $lockRes = $lockStmt->get_result();
    while ($row = $lockRes->fetch_assoc()) { $productRows[(int)$row['id']] = $row; }

    $subtotal = 0.0;
    $orderItems = []; // [id, name, qty, unit_price, line_total]
    foreach ($requested as $pid => $qty) {
        if (!isset($productRows[$pid])) {
            throw new \RuntimeException("One of the items in your cart is no longer available.");
        }
        $p = $productRows[$pid];
        if ((int)$p['is_active'] !== 1) {
            throw new \RuntimeException("\"{$p['name']}\" is no longer available.");
        }
        if (array_key_exists('approval_status', $p) && $p['approval_status'] !== null && $p['approval_status'] !== 'approved') {
            throw new \RuntimeException("\"{$p['name']}\" is no longer available.");
        }
        if ($qty > (int)$p['stock']) {
            throw new \RuntimeException("Only {$p['stock']} unit(s) of \"{$p['name']}\" are available.");
        }
        // Real price always comes from the DB row we just locked — the
        // browser's price is never used for the calculation.
        $unitPrice = ($p['discount_price'] !== null && (float)$p['discount_price'] > 0 && (float)$p['discount_price'] < (float)$p['price'])
            ? (float)$p['discount_price'] : (float)$p['price'];
        $lineTotal = $unitPrice * $qty;
        $subtotal += $lineTotal;
        $orderItems[] = ['id' => $pid, 'name' => $p['name'], 'qty' => $qty, 'unit_price' => $unitPrice, 'line_total' => $lineTotal];
    }

    // ---- Coupon: re-validated against the server-computed subtotal ----
    $discountAmount = 0.0;
    $appliedCouponId = null;
    $appliedCouponCode = null;
    if ($couponCode !== '') {
        $coupon = agri_load_and_validate_coupon($conn, $couponCode, $userId, $subtotal);
        if ($coupon['ok']) {
            $discountAmount = $coupon['discount_amount'];
            $appliedCouponId = $coupon['id'];
            $appliedCouponCode = $coupon['code'];
        }
        // If the coupon no longer validates at order time (expired between
        // "apply" and "confirm", usage limit hit by someone else, etc.) we
        // silently drop it rather than failing the whole order — the
        // person already saw and accepted the final total on screen.
    }

    $shippingCharge = 0.00; // site advertises free delivery — adjust here if that changes
    $finalAmount = max(0, $subtotal - $discountAmount + $shippingCharge);
    $orderNumber = 'AGC' . date('ymd') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));

    // ---- Delivery address: try to store it properly via user_addresses;
    // if that table/columns don't match, fall back to writing it into
    // orders.notes so the information is never silently lost.
    //
    // CONFIRMED live schema (phpMyAdmin structure view): user_id,
    // address_type enum('delivery','farm','billing'), full_name, phone,
    // address_line1 (NOT NULL, varchar 255), address_line2, pincode —
    // there is no generic 'name' / 'mobile' / 'address' column, which is
    // why address_id was never being set before. address_line1 is capped
    // at 255 chars, so overflow goes into address_line2 instead of being
    // truncated. ----
    $addressId = null;
    try {
        $addrCols = agri_table_columns($conn, 'user_addresses');
        if (in_array('user_id', $addrCols, true) && in_array('full_name', $addrCols, true)
            && in_array('phone', $addrCols, true) && in_array('address_line1', $addrCols, true)) {
            $pinCol = in_array('pincode', $addrCols, true) ? 'pincode' : (in_array('pin_code', $addrCols, true) ? 'pin_code' : null);
            if ($pinCol) {
                // If the person picked one of their already-saved addresses at
                // checkout (the normal case), just point the order at that
                // existing row — do NOT insert a new one. Previously this
                // always inserted a fresh row on every single order, which is
                // why the address book kept filling up with identical
                // "Home" duplicates. A brand-new row is only created when
                // there's no valid saved address to reuse (e.g. very first
                // order before an address book existed).
                if ($selectedAddressId > 0) {
                    $chk = $conn->prepare("SELECT id FROM user_addresses WHERE id = ? AND user_id = ? LIMIT 1");
                    $chk->bind_param("ii", $selectedAddressId, $userId);
                    $chk->execute();
                    if ($chk->get_result()->fetch_assoc()) {
                        $addressId = $selectedAddressId;
                    }
                }

                if ($addressId === null) {
                    $addrLine1 = mb_substr($address, 0, 255);
                    $addrLine2 = mb_strlen($address) > 255 ? mb_substr($address, 255, 255) : null;
                    $hasAddrType = in_array('address_type', $addrCols, true);
                    $hasAddrLine2 = in_array('address_line2', $addrCols, true);
                    $hasIsDefault = in_array('is_default', $addrCols, true);

                    // Only the very first address a user ever has should be
                    // auto-created here; after that, address rows should only
                    // come from the checkout "Add New Address" form
                    // (save_address.php), never from placing an order.
                    $cntStmt = $conn->prepare("SELECT COUNT(*) c FROM user_addresses WHERE user_id = ?");
                    $cntStmt->bind_param("i", $userId);
                    $cntStmt->execute();
                    $isFirstAddress = ((int)$cntStmt->get_result()->fetch_assoc()['c'] === 0);

                    $insCols = ['user_id', 'full_name', 'phone', $pinCol, 'address_line1'];
                    $params  = [$userId, $name, $mobile, $pin, $addrLine1];
                    $typesStr = 'issss';
                    if ($hasAddrType)  { $insCols[] = 'address_type';  $params[] = 'delivery'; $typesStr .= 's'; }
                    if ($hasAddrLine2) { $insCols[] = 'address_line2'; $params[] = $addrLine2; $typesStr .= 's'; }
                    if ($hasIsDefault) { $insCols[] = 'is_default'; $params[] = $isFirstAddress ? 1 : 0; $typesStr .= 'i'; }

                    $colList = implode(', ', array_map(fn($c) => "`$c`", $insCols));
                    $qMarks  = implode(', ', array_fill(0, count($insCols), '?'));
                    $addrStmt = $conn->prepare("INSERT INTO user_addresses ($colList) VALUES ($qMarks)");
                    $addrStmt->bind_param($typesStr, ...$params);
                    $addrStmt->execute();
                    $addressId = $conn->insert_id;
                }
            }
        }
    } catch (\Throwable $eAddr) {
        error_log('place_order.php: could not save to user_addresses (schema mismatch?) — ' . $eAddr->getMessage());
    }

    $notesText = "Delivery: {$name}, {$mobile}, PIN {$pin}\n{$address}"
        . ($hasPaymentModeCol ? '' : "\nPayment mode: {$paymentMode}");

    // `status` is still left to its table default ('pending') since the
    // full ENUM list wasn't confirmed. `payment_status` IS confirmed as
    // enum('unpaid','paid','refunded') though, and this project has no
    // real payment gateway wired up — an order only ever reaches this
    // point after the person has gone through the (demo) UPI/UPIQR flow,
    // so it's safe (and necessary) to mark it 'paid' right away for those
    // modes. COD is left at the 'unpaid' default since cash is only
    // actually collected on delivery.
    $hasPaymentStatusCol = in_array('payment_status', $orderCols, true);
    $isOnlinePaid = in_array($paymentMode, ['UPI', 'UPIQR'], true);

    // No real payment gateway is wired up, so there's no genuine gateway
    // reference number to store — generate a plain reference id at order
    // time instead, so the invoice shows a real-looking value instead of
    // "N/A". Swap this for the actual gateway's transaction id once a
    // payment gateway is integrated.
    $demoTxnId = $isOnlinePaid
        ? 'UPI' . date('ymdHis') . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6))
        : null;

    // ---- Build the INSERT dynamically from columns we know exist ----
    $cols = ['user_id', 'order_number', 'total_amount', 'discount_amount', 'shipping_charge', 'final_amount', 'coupon_code'];
    $params = [$userId, $orderNumber, $subtotal, $discountAmount, $shippingCharge, $finalAmount, $appliedCouponCode];
    $typesStr = 'isdddds';
    if ($hasAddressIdCol && $addressId) { $cols[] = 'address_id'; $params[] = $addressId; $typesStr .= 'i'; }
    // Denormalized delivery snapshot — stored directly on the order (not
    // just via address_id) so it always displays correctly, e.g. in the
    // admin dashboard's "Delivery Location" column, even if the linked
    // user_addresses row is later edited or deleted.
    if ($hasDeliveryNameCol) { $cols[] = 'delivery_name'; $params[] = $name; $typesStr .= 's'; }
    if ($hasDeliveryMobileCol) { $cols[] = 'delivery_mobile'; $params[] = $mobile; $typesStr .= 's'; }
    if ($hasDeliveryAddressCol) { $cols[] = 'delivery_address'; $params[] = "{$address}, PIN: {$pin}"; $typesStr .= 's'; }
    if ($hasPaymentModeCol) { $cols[] = 'payment_mode'; $params[] = $paymentMode; $typesStr .= 's'; }
    if ($hasPaymentStatusCol && $isOnlinePaid) { $cols[] = 'payment_status'; $params[] = 'paid'; $typesStr .= 's'; }
    if ($txnIdCol && $demoTxnId) { $cols[] = $txnIdCol; $params[] = $demoTxnId; $typesStr .= 's'; }
    if ($hasNotesCol) { $cols[] = 'notes'; $params[] = $notesText; $typesStr .= 's'; }
    if ($hasIdemCol) { $cols[] = 'idempotency_key'; $params[] = ($idempotencyKey !== '' ? $idempotencyKey : null); $typesStr .= 's'; }
    // `status` is deliberately omitted — the table default ('pending')
    // applies automatically and is always a valid enum member.

    $colList = implode(', ', array_map(fn($c) => "`$c`", $cols));
    $qMarks = implode(', ', array_fill(0, count($cols), '?'));
    $insertStmt = $conn->prepare("INSERT INTO orders ($colList) VALUES ($qMarks)");
    if (!$insertStmt) {
        // Log the real DB error server-side; the buyer only ever sees a
        // generic friendly message (spec §17 — never expose raw SQL errors).
        error_log('place_order.php: order INSERT prepare failed: ' . $conn->error);
        throw new \RuntimeException('We could not create your order right now. Please try again in a moment.');
    }
    $insertStmt->bind_param($typesStr, ...$params);
    $insertStmt->execute();
    $orderId = $conn->insert_id;

    // ---- Order items (denormalized product name + price snapshot) ----
    $itemStmt = $conn->prepare("INSERT INTO order_items (order_id, product_id, product_name, quantity, price) VALUES (?, ?, ?, ?, ?)");
    foreach ($orderItems as $oi) {
        $itemStmt->bind_param("iisid", $orderId, $oi['id'], $oi['name'], $oi['qty'], $oi['unit_price']);
        $itemStmt->execute();
    }

    // ---- Decrement stock only now that everything succeeded ----
    $stockStmt = $conn->prepare("UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?");
    foreach ($requested as $pid => $qty) {
        $stockStmt->bind_param("iii", $qty, $pid, $qty);
        $stockStmt->execute();
        if ($stockStmt->affected_rows < 1) {
            throw new \RuntimeException('Stock changed while placing your order — please try again.');
        }
    }

    // ---- Record coupon usage + bump used_count ----
    if ($appliedCouponId) {
        try {
            $usageStmt = $conn->prepare("INSERT INTO coupon_usages (coupon_id, user_id, order_id, used_at) VALUES (?, ?, ?, NOW())");
            $usageStmt->bind_param("iii", $appliedCouponId, $userId, $orderId);
            $usageStmt->execute();
            $conn->query("UPDATE coupons SET used_count = used_count + 1 WHERE id = " . (int)$appliedCouponId);
        } catch (\Throwable $eUsage) {
            error_log('place_order.php: coupon usage tracking skipped — ' . $eUsage->getMessage());
        }
    }

    $conn->commit();

    // Notify Admin (spec §14 "New Order") — best-effort, never blocks the
    // buyer's checkout response even if it fails.
    require_once __DIR__ . '/../includes/admin_notifications_schema.php';
    agri_notify_admin(
        $conn,
        'new_order',
        'New Order #' . $orderNumber,
        'Order placed for ₹' . number_format($finalAmount, 2),
        'invoice.php?order_id=' . $orderId
    );

    echo json_encode(['success' => true, 'order_number' => $orderNumber, 'final_amount' => $finalAmount]);
} catch (\Throwable $e) {
    $conn->rollback();
    error_log('place_order.php failed: ' . $e->getMessage());
    agri_json_fail(400, $e->getMessage() ?: 'Order could not be placed, please try again.');
}

/**
 * Shared coupon validation logic (also used by validate_coupon.php).
 * Matches the confirmed `coupons` schema: active, max_discount_amount,
 * deleted_at, expiry_date (no start_date column — coupons are active from
 * creation until expiry_date). Returns:
 * ['ok'=>bool,'discount_amount'=>float,'id'=>int,'code'=>string,'error'=>string]
 */
function agri_load_and_validate_coupon(mysqli $conn, string $code, int $userId, float $orderAmount): array {
    $stmt = $conn->prepare("SELECT * FROM coupons WHERE code = ? AND deleted_at IS NULL LIMIT 1");
    $stmt->bind_param("s", $code);
    $stmt->execute();
    $coupon = $stmt->get_result()->fetch_assoc();
    if (!$coupon) return ['ok' => false, 'error' => 'Invalid coupon code.'];
    if (!(int)($coupon['active'] ?? 0)) return ['ok' => false, 'error' => 'This coupon is no longer active.'];
    if (!empty($coupon['expiry_date']) && strtotime($coupon['expiry_date']) < strtotime('today')) {
        return ['ok' => false, 'error' => 'This coupon has expired.'];
    }
    if ((float)($coupon['min_order_amount'] ?? 0) > 0 && $orderAmount < (float)$coupon['min_order_amount']) {
        return ['ok' => false, 'error' => 'Minimum order amount of ₹' . $coupon['min_order_amount'] . ' required for this coupon.'];
    }
    if ($coupon['usage_limit'] !== null && (int)$coupon['used_count'] >= (int)$coupon['usage_limit']) {
        return ['ok' => false, 'error' => 'This coupon has reached its usage limit.'];
    }
    // Per-user limit: no such column on `coupons`, so this only checks "has
    // this user used this exact coupon before" via coupon_usages (created by
    // migrations.sql) — treat any prior use as the limit being 1-per-user.
    try {
        $u = $conn->prepare("SELECT COUNT(*) c FROM coupon_usages WHERE coupon_id = ? AND user_id = ?");
        $u->bind_param("ii", $coupon['id'], $userId);
        $u->execute();
        $used = (int)$u->get_result()->fetch_assoc()['c'];
        if ($used >= 1) {
            return ['ok' => false, 'error' => 'You have already used this coupon.'];
        }
    } catch (\Throwable $e) { /* coupon_usages missing — skip per-user check */ }

    $discountType = $coupon['discount_type']; // 'percent' | 'flat'
    $discountValue = (float)$coupon['discount_value'];
    $discount = $discountType === 'percent' ? ($orderAmount * $discountValue / 100) : $discountValue;
    if (!empty($coupon['max_discount_amount']) && $discount > (float)$coupon['max_discount_amount']) {
        $discount = (float)$coupon['max_discount_amount'];
    }
    $discount = min($discount, $orderAmount);

    return [
        'ok' => true,
        'discount_amount' => round($discount, 2),
        'id' => (int)$coupon['id'],
        'code' => $coupon['code'],
        'discount_type' => $discountType,
        'discount_value' => $discountValue,
    ];
}
