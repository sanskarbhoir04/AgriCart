<?php
// =====================================================================
// pages/seller-invoice.php — AgriCart "Seller Invoice" page.
//
// URL (seller):  seller-invoice.php?order_id=1842
// URL (admin):   seller-invoice.php?order_id=1842&seller_id=25
//
// WHAT THIS IS
//   A companion to invoice.php (the buyer/product invoice). Where
//   invoice.php shows the *customer* what they bought across every
//   seller in the order, this page shows ONE seller what THEY sold in
//   that order, what AgriCart deducted, and what they're owed. If an
//   order has products from 3 sellers, each seller gets their own
//   invoice from this same page — filtered strictly to their own rows.
//
// WHO CAN OPEN IT
//   - The logged-in seller who owns at least one item in the order
//     (products.added_by_user_id = session user_id). The seller
//     identity is ALWAYS taken from the session — never from the URL.
//   - An admin, who may pass ?seller_id= to inspect any seller's
//     invoice for support/reporting.
//   Nobody else (including the buyer) can open this page — it exposes
//   the seller's GSTIN and payout figures, which are not the buyer's
//   business.
//
// DESIGN
//   Deliberately reuses invoice.php's exact CSS variables, card style,
//   table style, typography and toolbar so the two invoices feel like
//   one product. The differences are additive: the header identity is
//   the SELLER's business (not AgriCart's), and two new sections are
//   appended — "AgriCart Platform Charges" and "Seller Net Earnings" —
//   which never appear on the buyer invoice.
//
// SCHEMA SAFETY
//   Same philosophy as invoice.php: every column this page needs is
//   looked up live via agi_pick_col()/agi_col_exists() against
//   whatever the real schema calls it. Nothing here assumes a column
//   name that might not exist; everything degrades to a clearly
//   labelled fallback instead of showing "undefined" or fataling.
//   Run migrate_seller_invoice.php once to add the handful of columns
//   (business name/logo/address/GSTIN, commission settings, invoice
//   number registry) that a fresh AgriCart install won't have yet —
//   this page still works even before that migration runs.
//
// Does NOT modify invoice.php, includes/header.php, includes/footer.php,
// includes/db.php, or the login system — it only *uses* them.
// =====================================================================

require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/gstin_schema.php';
$agiContact = agri_company_contact($conn);

// ---------------------------------------------------------------------
// 1. Auth
// ---------------------------------------------------------------------
if (!function_exists('agi_is_admin')) {
    function agi_is_admin(): bool {
        return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    }
}

if (!isset($_SESSION['user_id']) && !agi_is_admin()) {
    $next = 'pages/seller-invoice.php' . (isset($_GET['order_id']) ? ('?order_id=' . (int)$_GET['order_id']) : '');
    header('Location: login.php?next=' . urlencode($next));
    exit();
}

$orderId = (int)($_GET['order_id'] ?? 0);

// SECURITY: the seller identity is ALWAYS the session user — a plain
// seller can never supply ?seller_id= to look at someone else's
// invoice. Only an admin session is allowed to pass it explicitly.
if (agi_is_admin()) {
    $sellerId = (int)($_GET['seller_id'] ?? 0);
    $isAdminViewing = true;
} else {
    $sellerId = (int)($_SESSION['user_id'] ?? 0);
    $isAdminViewing = false;
}

// Where "Back to Orders" should go from the seller side.
// TODO: point this at your actual seller-orders route if it differs.
$agiBackHref = 'dashboard.php';

function agis_render_message_page(string $title, string $message, int $code = 404, string $backHref = 'dashboard.php'): void {
    http_response_code($code);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="agi-msg-page"><div class="agi-msg-card">'
       . '<i class="fa-solid fa-file-circle-exclamation"></i>'
       . '<h2>' . htmlspecialchars($title) . '</h2>'
       . '<p>' . htmlspecialchars($message) . '</p>'
       . '<a class="agi-btn agi-btn-green" href="' . htmlspecialchars($backHref) . '"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>'
       . '</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit();
}

if ($orderId <= 0) {
    agis_render_message_page('Invalid invoice link', 'No valid order was specified. Please open this invoice from your Seller Orders page.', 400, $agiBackHref);
}
if ($sellerId <= 0) {
    agis_render_message_page('Invalid invoice link', $isAdminViewing ? 'No seller was specified.' : 'Your seller account could not be identified.', 400, $agiBackHref);
}

// ---------------------------------------------------------------------
// 2. Schema-agnostic column helpers (same contract as invoice.php)
// ---------------------------------------------------------------------
if (!function_exists('agi_col_exists')) {
    function agi_col_exists(mysqli $conn, string $table, string $col): bool {
        static $cache = [];
        $key = $table . '.' . $col;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($col);
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}
if (!function_exists('agi_pick_col')) {
    function agi_pick_col(mysqli $conn, string $table, array $candidates, ?string $default = null): ?string {
        foreach ($candidates as $c) {
            if (agi_col_exists($conn, $table, $c)) return $c;
        }
        return $default;
    }
}
if (!function_exists('agi_table_exists')) {
    function agi_table_exists(mysqli $conn, string $table): bool {
        static $cache = [];
        if (array_key_exists($table, $cache)) return $cache[$table];
        $t = $conn->real_escape_string($table);
        $res = $conn->query("SHOW TABLES LIKE '$t'");
        return $cache[$table] = ($res && $res->num_rows > 0);
    }
}

// Reuse the same column map the seller dashboard already relies on.
$C = function_exists('agri_seller_columns') ? agri_seller_columns($conn) : [];
$buyerCol   = $C['orders_buyer']   ?? agi_pick_col($conn, 'orders', ['user_id', 'buyer_id', 'customer_id'], 'user_id');
$createdCol = $C['orders_created'] ?? agi_pick_col($conn, 'orders', ['ordered_at', 'created_at', 'order_date', 'placed_at'], 'created_at');
$qtyCol     = $C['items_qty']      ?? agi_pick_col($conn, 'order_items', ['quantity', 'qty'], 'quantity');
$priceCol   = $C['items_price']    ?? agi_pick_col($conn, 'order_items', ['price', 'unit_price'], 'price');
$addrCol    = $C['orders_address'] ?? agi_pick_col($conn, 'orders', ['delivery_address', 'address', 'shipping_address']);
$notesCol   = agi_pick_col($conn, 'orders', ['notes']);
$villageCol = $C['orders_village'] ?? agi_pick_col($conn, 'orders', ['village']);
$nameCol    = $C['users_name']     ?? agi_pick_col($conn, 'users', ['full_name', 'name'], 'full_name');
$mobileCol  = $C['users_mobile']   ?? agi_pick_col($conn, 'users', ['mobile', 'phone'], 'mobile');

$oStatusCol   = agi_pick_col($conn, 'orders', ['order_status', 'status']);
$payMethodCol = agi_pick_col($conn, 'orders', ['payment_mode', 'payment_method', 'pay_method', 'mode_of_payment']);
$payStatusCol = agi_pick_col($conn, 'orders', ['payment_status', 'pay_status']);
$txnCol       = agi_pick_col($conn, 'orders', ['transaction_id', 'txn_id', 'payment_txn_id']);
$payDateCol   = agi_pick_col($conn, 'orders', ['payment_date', 'paid_at']);
$couponCodeCol = agi_pick_col($conn, 'orders', ['coupon_code', 'coupon', 'promo_code']);
$discountCol  = agi_pick_col($conn, 'orders', ['product_discount', 'discount_amount', 'discount']);
$couponCol    = agi_pick_col($conn, 'orders', ['coupon_discount', 'coupon_amount', 'coupon_value', 'promo_discount']);
$deliveryCol  = agi_pick_col($conn, 'orders', ['delivery_charge', 'shipping_charge', 'delivery_fee']);
$cityCol      = agi_pick_col($conn, 'orders', ['city']);
$stateCol     = agi_pick_col($conn, 'orders', ['state']);
$pinCol       = agi_pick_col($conn, 'orders', ['pincode', 'pin_code']);

$userEmailCol    = agi_pick_col($conn, 'users', ['email']);
$userVillageCol  = agi_pick_col($conn, 'users', ['village']);
$userTalukaCol   = agi_pick_col($conn, 'users', ['taluka']);
$userDistrictCol = agi_pick_col($conn, 'users', ['district']);
$userCityCol     = agi_pick_col($conn, 'users', ['city']);
$userStateCol    = agi_pick_col($conn, 'users', ['state']);
$userPinCol      = agi_pick_col($conn, 'users', ['pincode', 'pin_code']);
$userGstinCol    = agi_pick_col($conn, 'users', ['gstin', 'gst_number', 'gst_no']);
$bizNameCol      = agi_pick_col($conn, 'users', ['business_name', 'shop_name', 'store_name']);
$bizLogoCol      = agi_pick_col($conn, 'users', ['business_logo', 'shop_logo', 'logo', 'store_logo']);
$bizAddressCol   = agi_pick_col($conn, 'users', ['business_address', 'shop_address', 'address']);
$sellerCommCol   = agi_pick_col($conn, 'users', ['commission_rate', 'seller_commission_rate']);

$prodCategoryCol = agi_pick_col($conn, 'products', ['category']);
$prodImageCol    = agi_pick_col($conn, 'products', ['image', 'image_url', 'photo']);
$prodSkuCol      = agi_pick_col($conn, 'products', ['sku', 'product_code']);
$prodUnitCol     = agi_pick_col($conn, 'products', ['unit', 'package_size']);
$prodCommRateCol = agi_pick_col($conn, 'products', ['commission_rate', 'commission_percent']);

$itemDiscountCol   = agi_pick_col($conn, 'order_items', ['discount', 'discount_amount']);
$itemGstCol        = agi_pick_col($conn, 'order_items', ['gst_amount', 'tax_amount']);
$itemCommAmtCol    = agi_pick_col($conn, 'order_items', ['commission_amount', 'platform_commission']);
$itemCommRateCol   = agi_pick_col($conn, 'order_items', ['commission_rate']);

// ---------------------------------------------------------------------
// 3. Fetch the order (buyer + shipping info — needed for Bill To / Ship
//    To and for deciding CGST+SGST vs IGST)
// ---------------------------------------------------------------------
$select = "o.id, o.$buyerCol buyer_id, o.$createdCol created_at";
$select .= $oStatusCol ? ", o.$oStatusCol order_status" : ", NULL order_status";
$select .= $payMethodCol ? ", o.$payMethodCol payment_method" : ", NULL payment_method";
$select .= $payStatusCol ? ", o.$payStatusCol payment_status" : ", NULL payment_status";
$select .= $txnCol ? ", o.$txnCol txn_id" : ", NULL txn_id";
$select .= $payDateCol ? ", o.$payDateCol payment_date" : ", NULL payment_date";
$select .= $discountCol ? ", o.$discountCol product_discount" : ", NULL product_discount";
$select .= $couponCol ? ", o.$couponCol coupon_discount" : ", NULL coupon_discount";
$select .= $couponCodeCol ? ", o.$couponCodeCol coupon_code" : ", NULL coupon_code";
$select .= $deliveryCol ? ", o.$deliveryCol delivery_charge" : ", NULL delivery_charge";
$select .= $addrCol ? ", o.$addrCol delivery_address" : ", NULL delivery_address";
$select .= (!$addrCol && $notesCol) ? ", o.$notesCol delivery_notes" : ", NULL delivery_notes";
$select .= $villageCol ? ", o.$villageCol delivery_village" : ", NULL delivery_village";
$select .= $cityCol ? ", o.$cityCol delivery_city" : ", NULL delivery_city";
$select .= $stateCol ? ", o.$stateCol delivery_state" : ", NULL delivery_state";
$select .= $pinCol ? ", o.$pinCol delivery_pin" : ", NULL delivery_pin";
$select .= ", u.$nameCol buyer_name, u.$mobileCol buyer_mobile";
$select .= $userEmailCol ? ", u.$userEmailCol buyer_email" : ", NULL buyer_email";
$select .= $userStateCol ? ", u.$userStateCol buyer_reg_state" : ", NULL buyer_reg_state";

$orderSql = "SELECT $select FROM orders o LEFT JOIN users u ON u.id = o.$buyerCol WHERE o.id = ? LIMIT 1";
$stmt = $conn->prepare($orderSql);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    agis_render_message_page('Invoice not found', "We couldn't find an order matching this invoice link.", 404, $agiBackHref);
}

// ---------------------------------------------------------------------
// 4. Fetch ONLY this seller's line items in this order — the single
//    most important query on this page. Never remove the
//    p.added_by_user_id = ? filter; that filter IS the access control
//    for "never show another seller's products in this invoice".
// ---------------------------------------------------------------------
$prodSelect = "oi.id item_id, oi.product_id, oi.$qtyCol qty, oi.$priceCol price, oi.item_status";
$prodSelect .= $itemDiscountCol ? ", oi.$itemDiscountCol item_discount" : ", NULL item_discount";
$prodSelect .= $itemGstCol ? ", oi.$itemGstCol item_gst" : ", NULL item_gst";
$prodSelect .= $itemCommAmtCol ? ", oi.$itemCommAmtCol item_commission" : ", NULL item_commission";
$prodSelect .= $itemCommRateCol ? ", oi.$itemCommRateCol item_commission_rate" : ", NULL item_commission_rate";
$prodSelect .= ", p.name product_name, p.added_by_user_id seller_user_id";
$prodSelect .= $prodCategoryCol ? ", p.$prodCategoryCol category" : ", NULL category";
$prodSelect .= $prodImageCol ? ", p.$prodImageCol image" : ", NULL image";
$prodSelect .= $prodSkuCol ? ", p.$prodSkuCol sku" : ", NULL sku";
$prodSelect .= $prodUnitCol ? ", p.$prodUnitCol unit" : ", NULL unit";
$prodSelect .= $prodCommRateCol ? ", p.$prodCommRateCol product_commission_rate" : ", NULL product_commission_rate";

$itemsSql = "SELECT $prodSelect
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ? AND p.added_by_user_id = ?
             ORDER BY oi.id ASC";
$stmt = $conn->prepare($itemsSql);
$stmt->bind_param('ii', $orderId, $sellerId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ---------------------------------------------------------------------
// 5. Authorization
//    - A seller with zero matching rows gets exactly the same "not
//      found" message whether the order doesn't exist, belongs to
//      another seller entirely, or their session isn't a seller at
//      all — this deliberately avoids confirming/denying which case
//      it is to someone probing ?order_id=.
//    - An admin with zero matching rows for the seller_id they passed
//      gets a slightly more specific message since they're trusted.
// ---------------------------------------------------------------------
if (!$items) {
    if ($isAdminViewing) {
        agis_render_message_page('Invoice not found', 'This seller has no products on record in this order.', 404, $agiBackHref);
    }
    agis_render_message_page('Invoice not found', "We couldn't find any of your products on this order.", 404, $agiBackHref);
}

// ---------------------------------------------------------------------
// 6. Order-wide subtotal (ALL sellers) — used only to compute this
//    seller's proportional share of order-level coupon discount and
//    delivery charge. This never fetches or displays any other
//    seller's product rows, only an aggregate total.
// ---------------------------------------------------------------------
$orderWideSql = "SELECT COALESCE(SUM(oi.$qtyCol * oi.$priceCol), 0) AS order_subtotal
                  FROM order_items oi WHERE oi.order_id = ?";
$stmt = $conn->prepare($orderWideSql);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$orderWide = $stmt->get_result()->fetch_assoc();
$orderWideSubtotal = (float)($orderWide['order_subtotal'] ?? 0);

// ---------------------------------------------------------------------
// 7. Seller business profile (header identity + GSTIN)
// ---------------------------------------------------------------------
function agis_fetch_seller_profile(mysqli $conn, int $sellerId, array $cols): ?array {
    [$nameCol, $mobileCol, $userEmailCol, $userVillageCol, $userTalukaCol, $userDistrictCol,
     $userCityCol, $userStateCol, $userPinCol, $userGstinCol, $bizNameCol, $bizLogoCol,
     $bizAddressCol, $sellerCommCol] = $cols;

    $sSelect = "u.id, u.$nameCol seller_name, u.$mobileCol seller_mobile";
    $sSelect .= $userEmailCol ? ", u.$userEmailCol seller_email" : ", NULL seller_email";
    $sSelect .= $userVillageCol ? ", u.$userVillageCol seller_village" : ", NULL seller_village";
    $sSelect .= $userTalukaCol ? ", u.$userTalukaCol seller_taluka" : ", NULL seller_taluka";
    $sSelect .= $userDistrictCol ? ", u.$userDistrictCol seller_district" : ", NULL seller_district";
    $sSelect .= $userCityCol ? ", u.$userCityCol seller_city" : ", NULL seller_city";
    $sSelect .= $userStateCol ? ", u.$userStateCol seller_state" : ", NULL seller_state";
    $sSelect .= $userPinCol ? ", u.$userPinCol seller_pin" : ", NULL seller_pin";
    $sSelect .= $userGstinCol ? ", u.$userGstinCol seller_gstin" : ", NULL seller_gstin";
    $sSelect .= $bizNameCol ? ", u.$bizNameCol business_name" : ", NULL business_name";
    $sSelect .= $bizLogoCol ? ", u.$bizLogoCol business_logo" : ", NULL business_logo";
    $sSelect .= $bizAddressCol ? ", u.$bizAddressCol business_address" : ", NULL business_address";
    $sSelect .= $sellerCommCol ? ", u.$sellerCommCol seller_commission_rate" : ", NULL seller_commission_rate";

    $stmt = $conn->prepare("SELECT $sSelect FROM users u WHERE u.id = ? LIMIT 1");
    $stmt->bind_param('i', $sellerId);
    $stmt->execute();
    $profile = $stmt->get_result()->fetch_assoc();
    if (!$profile) return null;

    // seller_payout_profiles, if present, is treated as the more
    // authoritative source for business identity — same convention
    // invoice.php already uses for the buyer-facing "Sold By" card.
    if (agi_table_exists($conn, 'seller_payout_profiles')) {
        $ppCols = [];
        foreach (['business_name', 'business_logo', 'business_address', 'gstin', 'commission_rate'] as $c) {
            if (agi_col_exists($conn, 'seller_payout_profiles', $c)) $ppCols[] = $c;
        }
        if ($ppCols) {
            $stmt = $conn->prepare("SELECT " . implode(',', $ppCols) . " FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
            $stmt->bind_param('i', $sellerId);
            $stmt->execute();
            $pp = $stmt->get_result()->fetch_assoc();
            if ($pp) {
                if (!empty($pp['business_name'])) $profile['business_name'] = $pp['business_name'];
                if (!empty($pp['business_logo'])) $profile['business_logo'] = $pp['business_logo'];
                if (!empty($pp['business_address'])) $profile['business_address'] = $pp['business_address'];
                if (!empty($pp['gstin'])) $profile['seller_gstin'] = $pp['gstin'];
                if (isset($pp['commission_rate']) && $pp['commission_rate'] !== null) $profile['seller_commission_rate'] = $pp['commission_rate'];
            }
        }
    }
    return $profile;
}

$sellerProfile = agis_fetch_seller_profile($conn, $sellerId, [
    $nameCol, $mobileCol, $userEmailCol, $userVillageCol, $userTalukaCol, $userDistrictCol,
    $userCityCol, $userStateCol, $userPinCol, $userGstinCol, $bizNameCol, $bizLogoCol,
    $bizAddressCol, $sellerCommCol,
]);
if (!$sellerProfile) {
    agis_render_message_page('Seller not found', 'This seller account could not be loaded.', 404, $agiBackHref);
}

// ---------------------------------------------------------------------
// 7b. Historical Invoice Protection — once a seller_invoices row exists
//     for this (seller, order) pair, the identity fields frozen on it
//     at generation time take priority over the live lookup above, so
//     a later change to the seller's GSTIN/company name/address/
//     contact can never rewrite an invoice that's already been issued.
//     Rows generated before setup/seller_invoice_snapshot_upgrade.sql
//     was run have NULL snapshot columns and simply keep the live
//     profile fetched above.
// ---------------------------------------------------------------------
$agiAgricartSignatory = null; // filled below — always AgriCart on a Seller Invoice
try {
    $snapStmt = $conn->prepare(
        "SELECT seller_name_snapshot, business_name_snapshot, business_address_snapshot,
                gstin_snapshot, seller_mobile_snapshot, seller_email_snapshot,
                agricart_signature_snapshot, agricart_stamp_snapshot,
                agricart_signatory_name_snapshot, agricart_designation_snapshot
         FROM seller_invoices WHERE seller_id = ? AND order_id = ? LIMIT 1"
    );
    $snapStmt->bind_param('ii', $sellerId, $orderId);
    $snapStmt->execute();
    $snapRow = $snapStmt->get_result()->fetch_assoc();
    if ($snapRow) {
        if (!empty($snapRow['seller_name_snapshot'])) $sellerProfile['seller_name'] = $snapRow['seller_name_snapshot'];
        if (!empty($snapRow['business_name_snapshot'])) $sellerProfile['business_name'] = $snapRow['business_name_snapshot'];
        if (!empty($snapRow['business_address_snapshot'])) $sellerProfile['business_address'] = $snapRow['business_address_snapshot'];
        if (!empty($snapRow['gstin_snapshot'])) $sellerProfile['seller_gstin'] = $snapRow['gstin_snapshot'];
        if (!empty($snapRow['seller_mobile_snapshot'])) $sellerProfile['seller_mobile'] = $snapRow['seller_mobile_snapshot'];
        if (!empty($snapRow['seller_email_snapshot'])) $sellerProfile['seller_email'] = $snapRow['seller_email_snapshot'];
    }
    // Authorized Signatory on a SELLER INVOICE is always AgriCart —
    // never this seller's own signature/stamp (see includes/
    // invoice_signature_schema.php). Prefer the frozen snapshot on the
    // seller_invoices row; fall back to AgriCart's live current assets
    // only when no row/snapshot exists yet (e.g. a preview before the
    // first sale registers, or pre-upgrade rows with NULL snapshots).
    require_once __DIR__ . '/../includes/invoice_signature_schema.php';
    if (function_exists('agri_sig_bootstrap_schema')) agri_sig_bootstrap_schema($conn);
    $agiAgricartSignatory = [
        'signature_path' => $snapRow['agricart_signature_snapshot'] ?? null,
        'stamp_path' => $snapRow['agricart_stamp_snapshot'] ?? null,
        'signatory_name' => $snapRow['agricart_signatory_name_snapshot'] ?? null,
        'designation' => $snapRow['agricart_designation_snapshot'] ?? null,
    ];
    if (empty($agiAgricartSignatory['signature_path']) && empty($agiAgricartSignatory['stamp_path']) && empty($agiAgricartSignatory['signatory_name'])) {
        $liveAssets = function_exists('agri_agricart_invoice_assets') ? agri_agricart_invoice_assets($conn) : null;
        if ($liveAssets) $agiAgricartSignatory = $liveAssets;
    }
} catch (\Throwable $e) {
    // seller_invoices table/snapshot columns not present on this
    // install yet — fall back silently to the live profile above.
}
if (!$agiAgricartSignatory) {
    require_once __DIR__ . '/../includes/invoice_signature_schema.php';
    if (function_exists('agri_sig_bootstrap_schema')) agri_sig_bootstrap_schema($conn);
    $agiAgricartSignatory = function_exists('agri_agricart_invoice_assets') ? agri_agricart_invoice_assets($conn) : [
        'signature_path' => null, 'stamp_path' => null, 'signatory_name' => null, 'designation' => null,
    ];
}

// ---------------------------------------------------------------------
// 8. Platform commission settings (defaults, overridable per product
//    or per seller)
// ---------------------------------------------------------------------
$platformCommissionRate = 10.00; // sensible default if nothing is configured yet
$platformGstOnCommissionRate = 18.00; // standard GST slab on platform/marketplace services
if (agi_table_exists($conn, 'platform_commission_settings')) {
    $res = $conn->query("SELECT commission_rate, gst_on_commission_rate FROM platform_commission_settings ORDER BY id DESC LIMIT 1");
    $row = $res ? $res->fetch_assoc() : null;
    if ($row) {
        if (is_numeric($row['commission_rate'] ?? null)) $platformCommissionRate = (float)$row['commission_rate'];
        if (is_numeric($row['gst_on_commission_rate'] ?? null)) $platformGstOnCommissionRate = (float)$row['gst_on_commission_rate'];
    }
}
// Per-seller override beats the platform default.
if (is_numeric($sellerProfile['seller_commission_rate'] ?? null)) {
    $platformCommissionRate = (float)$sellerProfile['seller_commission_rate'];
}

// ---------------------------------------------------------------------
// 9. If AgriCart already has a seller-earnings ledger for this
//    seller+order, prefer ITS numbers for the payout section so the
//    invoice can never disagree with the Seller Dashboard/withdrawal
//    figures. Only used when found; otherwise we compute below.
// ---------------------------------------------------------------------
$ledgerCommission = null; $ledgerGstOnCommission = null; $ledgerOtherDeductions = null; $ledgerNetPayable = null;
foreach (['seller_earnings', 'seller_ledger', 'seller_transactions'] as $ledgerTable) {
    if (!agi_table_exists($conn, $ledgerTable)) continue;
    $sellerFk = agi_pick_col($conn, $ledgerTable, ['seller_id', 'user_id']);
    $orderFk  = agi_pick_col($conn, $ledgerTable, ['order_id']);
    if (!$sellerFk || !$orderFk) continue;
    $commCol  = agi_pick_col($conn, $ledgerTable, ['commission_amount', 'platform_commission']);
    $gstCommCol = agi_pick_col($conn, $ledgerTable, ['gst_on_commission', 'commission_gst']);
    $otherCol = agi_pick_col($conn, $ledgerTable, ['other_deductions', 'adjustment']);
    $netCol   = agi_pick_col($conn, $ledgerTable, ['net_amount', 'earning_amount', 'net_payable', 'seller_net']);
    if (!$netCol) continue;

    $cols = [$netCol];
    if ($commCol) $cols[] = $commCol;
    if ($gstCommCol) $cols[] = $gstCommCol;
    if ($otherCol) $cols[] = $otherCol;
    $stmt = $conn->prepare("SELECT " . implode(',', $cols) . " FROM `$ledgerTable` WHERE `$sellerFk` = ? AND `$orderFk` = ? LIMIT 1");
    $stmt->bind_param('ii', $sellerId, $orderId);
    $stmt->execute();
    $ledgerRow = $stmt->get_result()->fetch_assoc();
    if ($ledgerRow) {
        $ledgerNetPayable = is_numeric($ledgerRow[$netCol] ?? null) ? (float)$ledgerRow[$netCol] : null;
        if ($commCol && is_numeric($ledgerRow[$commCol] ?? null)) $ledgerCommission = (float)$ledgerRow[$commCol];
        if ($gstCommCol && is_numeric($ledgerRow[$gstCommCol] ?? null)) $ledgerGstOnCommission = (float)$ledgerRow[$gstCommCol];
        if ($otherCol && is_numeric($ledgerRow[$otherCol] ?? null)) $ledgerOtherDeductions = (float)$ledgerRow[$otherCol];
        break;
    }
}

// ---------------------------------------------------------------------
// 10. GST rate table — identical slabs to invoice.php, so a product's
//     tax rate is always shown the same way on both invoices.
// ---------------------------------------------------------------------
if (!function_exists('agi_gst_rate_for_category')) {
    function agi_gst_rate_for_category(?string $category): float {
        $c = strtolower(trim((string)$category));
        $map = [
            'seed' => 0.00, 'feed' => 0.00, 'fertil' => 0.05, 'pestic' => 0.18,
            'insectic' => 0.18, 'fungic' => 0.18, 'irrigat' => 0.12, 'tool' => 0.12,
            'implement' => 0.12, 'equipment' => 0.12, 'organic' => 0.05,
        ];
        foreach ($map as $needle => $rate) {
            if ($c !== '' && strpos($c, $needle) !== false) return $rate;
        }
        return 0.05;
    }
}

// Prices are GST-inclusive across AgriCart (same assumption invoice.php
// makes) — so the tax is extracted out of qty*price, never added on top.
$gstIsInclusive = true;

// ---------------------------------------------------------------------
// 11. Per-item math — taxable value, GST, and commission for THIS
//     seller's items only.
// ---------------------------------------------------------------------
$lineSubtotalGross = 0.0; // qty*price, GST-inclusive
$lineDiscount = 0.0;
$lineGst = 0.0;
$lineCommission = 0.0;

foreach ($items as $idx => $it) {
    $qty = (float)$it['qty'];
    $price = (float)$it['price'];
    $lineAmt = $qty * $price;
    $lineSubtotalGross += $lineAmt;

    $itemDiscount = (float)($it['item_discount'] ?? 0);
    $lineDiscount += $itemDiscount;

    $netLine = $lineAmt - $itemDiscount; // GST-inclusive net-of-discount value
    $storedGst = (float)($it['item_gst'] ?? 0);
    if ($storedGst > 0) {
        $items[$idx]['item_gst'] = $storedGst;
        $rate = $lineAmt > 0 ? round($storedGst / max($netLine - $storedGst, 0.01), 4) : 0.0;
        $items[$idx]['gst_rate'] = $rate;
    } else {
        $rate = agi_gst_rate_for_category($it['category'] ?? null);
        $storedGst = $rate > 0 ? round($netLine - ($netLine / (1 + $rate)), 2) : 0.0;
        $items[$idx]['item_gst'] = $storedGst;
        $items[$idx]['gst_rate'] = $rate;
    }
    $lineGst += $storedGst;

    $taxableValue = round($netLine - $storedGst, 2);
    $items[$idx]['taxable_value'] = $taxableValue;
    $items[$idx]['final_amount'] = round($netLine, 2); // inclusive price already contains GST

    // Per-item commission: stored value wins; else product-level rate;
    // else the seller/platform rate resolved above — applied to the
    // taxable (pre-GST) value, which is standard marketplace practice.
    $storedComm = (float)($it['item_commission'] ?? 0);
    if ($storedComm > 0) {
        $items[$idx]['item_commission'] = $storedComm;
    } else {
        $itemRate = is_numeric($it['item_commission_rate'] ?? null) ? (float)$it['item_commission_rate']
            : (is_numeric($it['product_commission_rate'] ?? null) ? (float)$it['product_commission_rate'] : $platformCommissionRate);
        $storedComm = round($taxableValue * ($itemRate / 100), 2);
        $items[$idx]['item_commission'] = $storedComm;
        $items[$idx]['item_commission_rate'] = $itemRate;
    }
    $lineCommission += $storedComm;
}

$sellerTaxableSubtotal = round($lineSubtotalGross - $lineDiscount - $lineGst, 2); // net of tax & discount
$sellerGst = round($lineGst, 2);

// ---------------------------------------------------------------------
// 12. Proportional share of order-level coupon discount & delivery
//     charge — only meaningful when the order has more than one
//     seller; with a single seller the ratio is 1.0.
// ---------------------------------------------------------------------
$shareRatio = $orderWideSubtotal > 0 ? min(1.0, $lineSubtotalGross / $orderWideSubtotal) : 1.0;

$orderCouponDiscount = is_numeric($order['coupon_discount'] ?? null) ? (float)$order['coupon_discount'] : 0.0;
$orderProductDiscount = is_numeric($order['product_discount'] ?? null) ? (float)$order['product_discount'] : 0.0;
if (!empty($order['coupon_code']) && $orderCouponDiscount <= 0 && $orderProductDiscount > 0) {
    $orderCouponDiscount = $orderProductDiscount;
}
$sellerCouponShare = round($orderCouponDiscount * $shareRatio, 2);

$orderDeliveryCharge = is_numeric($order['delivery_charge'] ?? null) ? (float)$order['delivery_charge'] : 0.0;
$sellerDeliveryShare = round($orderDeliveryCharge * $shareRatio, 2);

// ---------------------------------------------------------------------
// 13. Gross order value (customer-facing product value for THIS
//     seller's items) and the platform charges / net earnings split.
//     Delivery is intentionally excluded from "Gross Order Value" —
//     it is logistics pass-through, not seller product revenue — but
//     is still shown separately for transparency.
// ---------------------------------------------------------------------
$grossOrderValue = round($sellerTaxableSubtotal - $sellerCouponShare + $sellerGst, 2);

if ($ledgerNetPayable !== null) {
    // Trust the existing earnings ledger over a fresh recompute, so
    // this invoice can never contradict the Seller Dashboard.
    $platformCommission = $ledgerCommission ?? round($lineCommission, 2);
    $platformGstOnCommission = $ledgerGstOnCommission ?? round($platformCommission * ($platformGstOnCommissionRate / 100), 2);
    $otherDeductions = $ledgerOtherDeductions ?? 0.0;
    $sellerNetPayable = $ledgerNetPayable;
} else {
    $platformCommission = round($lineCommission, 2);
    $platformGstOnCommission = round($platformCommission * ($platformGstOnCommissionRate / 100), 2);
    $otherDeductions = 0.0;
    $sellerNetPayable = round($grossOrderValue - $platformCommission - $platformGstOnCommission - $otherDeductions, 2);
}

// ---------------------------------------------------------------------
// 14. Intra-state (CGST+SGST) vs inter-state (IGST) — compare the
//     seller's registered state to the order's shipping state (falling
//     back to the buyer's registered state if shipping state is blank).
// ---------------------------------------------------------------------
$sellerState = trim((string)($sellerProfile['seller_state'] ?? ''));
$shipState = trim((string)($order['delivery_state'] ?? '')) ?: trim((string)($order['buyer_reg_state'] ?? ''));
$isInterState = ($sellerState !== '' && $shipState !== '' && strcasecmp($sellerState, $shipState) !== 0);

if ($isInterState) {
    $igst = $sellerGst;
    $cgst = 0.0; $sgst = 0.0;
} else {
    $cgst = round($sellerGst / 2, 2);
    $sgst = round($sellerGst - $cgst, 2);
    $igst = 0.0;
}

// ---------------------------------------------------------------------
// 15. Payment method / status (same normalisation as invoice.php, so
//     the two invoices never disagree on whether an order is "paid")
// ---------------------------------------------------------------------
$rawPaymentMethod = trim((string)($order['payment_method'] ?? ''));
$paymentMethodMap = ['cod' => 'Cash on Delivery', 'online' => 'Online Payment', 'upi' => 'UPI', 'card' => 'Card', 'netbanking' => 'Net Banking'];
$paymentMethod = $paymentMethodMap[strtolower($rawPaymentMethod)] ?? ($rawPaymentMethod ?: 'Cash on Delivery');
$isCod = (strtolower($rawPaymentMethod) === 'cod' || strtolower($paymentMethod) === 'cash on delivery');
$isDelivered = strtolower((string)($order['order_status'] ?? '')) === 'delivered';

if (!function_exists('agi_is_paid_status')) {
    function agi_is_paid_status(string $status): bool {
        $paidWords = ['paid', 'success', 'successful', 'completed', 'complete', 'captured', 'settled', 'confirmed', 'done', 'received'];
        return in_array($status, $paidWords, true);
    }
}
$rawPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
if ($isCod) {
    $resolvedPaymentStatus = $isDelivered ? 'paid' : ($rawPaymentStatus !== '' ? $rawPaymentStatus : 'pending');
} elseif ($rawPaymentStatus !== '') {
    $resolvedPaymentStatus = agi_is_paid_status($rawPaymentStatus) ? 'paid' : $rawPaymentStatus;
} else {
    $resolvedPaymentStatus = 'paid';
}
$paymentStatus = ucwords(str_replace('_', ' ', $resolvedPaymentStatus));
$orderStatus = $order['order_status'] ? ucwords(str_replace('_', ' ', $order['order_status'])) : 'Confirmed';

// ---------------------------------------------------------------------
// 16. Unique, stable invoice number — one per (seller, order), never
//     regenerated on repeat views. Self-heals the registry table if
//     migrate_seller_invoice.php hasn't been run yet.
// ---------------------------------------------------------------------
function agis_get_invoice_number(mysqli $conn, int $sellerId, int $orderId, string $createdAt): string {
    if (!agi_table_exists($conn, 'seller_invoice_numbers')) {
        $conn->query("CREATE TABLE IF NOT EXISTS `seller_invoice_numbers` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY,
            `seller_id` INT NOT NULL,
            `order_id` INT NOT NULL,
            `invoice_no` VARCHAR(40) NOT NULL,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY `uniq_seller_order` (`seller_id`, `order_id`),
            UNIQUE KEY `uniq_invoice_no` (`invoice_no`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
    $stmt = $conn->prepare("SELECT invoice_no FROM seller_invoice_numbers WHERE seller_id = ? AND order_id = ? LIMIT 1");
    $stmt->bind_param('ii', $sellerId, $orderId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    if ($row && !empty($row['invoice_no'])) return $row['invoice_no'];

    $year = date('Y', strtotime($createdAt ?: 'now'));
    $res = $conn->query("SELECT COUNT(*) c FROM seller_invoice_numbers WHERE invoice_no LIKE 'SELL-$year-%'");
    $seq = (int)(($res->fetch_assoc()['c'] ?? 0)) + 1;
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $candidate = 'SELL-' . $year . '-' . str_pad((string)($seq + $attempt), 7, '0', STR_PAD_LEFT);
        $ins = $conn->prepare("INSERT IGNORE INTO seller_invoice_numbers (seller_id, order_id, invoice_no) VALUES (?, ?, ?)");
        $ins->bind_param('iis', $sellerId, $orderId, $candidate);
        $ins->execute();
        if ($ins->affected_rows > 0) return $candidate;
    }
    // Extremely unlikely fallback: still unique per seller+order, just
    // not sequential, so the page never breaks.
    return 'SELL-' . $year . '-' . str_pad((string)$orderId, 5, '0', STR_PAD_LEFT) . '-' . str_pad((string)$sellerId, 3, '0', STR_PAD_LEFT);
}
$invoiceNo = agis_get_invoice_number($conn, $sellerId, $orderId, (string)$order['created_at']);
$orderCode = 'AGC-ORD-' . $orderId;
$invoiceDate = date('d F Y');
$orderDate = date('d F Y', strtotime($order['created_at'] ?: 'now'));

// ---------------------------------------------------------------------
// 17. Address assembly + small render helpers
// ---------------------------------------------------------------------
if (!function_exists('agi_e')) {
    function agi_e($v, $fallback = '—') {
        $v = trim((string)($v ?? ''));
        return htmlspecialchars($v !== '' ? $v : $fallback);
    }
}
if (!function_exists('agi_money')) {
    function agi_money($n) {
        return '&#8377;' . number_format((float)$n, 2);
    }
}
function agis_line_or_hide($label, $value): string {
    $value = trim((string)($value ?? ''));
    if ($value === '') return '';
    return '<p>' . ($label ? '<span class="agi-muted-line">' . htmlspecialchars($label) . '</span> ' : '') . htmlspecialchars($value) . '</p>';
}

$deliveryAddressParts = array_filter([$order['delivery_address'] ?? '', $order['delivery_village'] ?? '']);
$deliveryLine1 = trim(implode(', ', $deliveryAddressParts));
if ($deliveryLine1 === '' && !empty($order['delivery_notes'])) {
    $deliveryLine1 = trim(preg_replace('/^Delivery:\s*[^\n]*\n?/i', '', (string)$order['delivery_notes']));
}
$deliveryLine1 = $deliveryLine1 ?: 'Address on file with AgriCart';
$deliveryCityState = trim(implode(', ', array_filter([$order['delivery_city'] ?? '', $order['delivery_state'] ?? ''])));
$deliveryPin = $order['delivery_pin'] ?? '';

$sellerAddressParts = array_filter([
    $sellerProfile['business_address'] ?? '',
    $sellerProfile['seller_village'] ?? '', $sellerProfile['seller_taluka'] ?? '', $sellerProfile['seller_district'] ?? '',
]);
$sellerAddressLine = trim(implode(', ', $sellerAddressParts));
$sellerCityState = trim(implode(', ', array_filter([$sellerProfile['seller_city'] ?? '', $sellerProfile['seller_state'] ?? ''])));
$sellerDisplayName = trim((string)($sellerProfile['business_name'] ?? '')) ?: trim((string)($sellerProfile['seller_name'] ?? '')) ?: 'AgriCart Seller';
$sellerHasGstin = !empty($sellerProfile['seller_gstin']);
$isTaxInvoice = $sellerHasGstin;

// Seller logo — the seller's own uploaded logo takes priority; falls
// back to the AgriCart mark (never breaks the header layout either way).
$sellerLogoUrl = trim((string)($sellerProfile['business_logo'] ?? ''));
if ($sellerLogoUrl !== '' && !preg_match('#^(https?:)?//#i', $sellerLogoUrl) && strpos($sellerLogoUrl, '/') !== 0) {
    $sellerLogoUrl = '../' . ltrim($sellerLogoUrl, '/');
}
$fallbackLogo = '../assets/images/agricart-logo.png';

?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ===================== AgriCart Seller Invoice — scoped "agi-" styles =====================
   Reuses invoice.php's exact palette/typography/card system; only adds
   the platform-charges and net-earnings block styles at the bottom. */
:root{
    --agi-dark-green:#112b11;
    --agi-green:#2E7D32;
    --agi-light-green:#E8F5E9;
    --agi-white:#FFFFFF;
    --agi-border:#E5E7EB;
    --agi-text:#1F2937;
    --agi-muted:#6B7280;
    --agi-amber:#B45309;
    --agi-amber-bg:#FFF7ED;
    --agi-red:#B91C1C;
    --agi-red-bg:#FEF2F2;
    --agi-radius:12px;
    --agi-shadow:0 6px 24px rgba(17,43,17,.08);
}
.agi-page{font-family:'Inter','Poppins',sans-serif;color:var(--agi-text);background:#F3F5F2;padding:28px 16px 60px;}
.agi-page *{box-sizing:border-box;}

.agi-toolbar{max-width:900px;margin:0 auto 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.agi-toolbar-actions{display:flex;gap:10px;flex-wrap:wrap;}
.agi-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:9px;border:1.5px solid var(--agi-border);
    background:var(--agi-white);color:var(--agi-text);font-weight:600;font-size:13.5px;cursor:pointer;text-decoration:none;
    font-family:inherit;transition:background .15s ease, transform .1s ease, box-shadow .15s ease;}
.agi-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(17,43,17,.1);}
.agi-btn-green{background:var(--agi-green);border-color:var(--agi-green);color:#fff;}
.agi-btn-outline{background:var(--agi-white);border-color:var(--agi-dark-green);color:var(--agi-dark-green);}

.agi-sheet{max-width:900px;margin:0 auto;background:var(--agi-white);border:1px solid var(--agi-border);
    border-radius:var(--agi-radius);box-shadow:var(--agi-shadow);padding:40px 44px;}
.agi-header-row{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid var(--agi-light-green);padding-bottom:22px;margin-bottom:24px;flex-wrap:wrap;}
.agi-brand{display:flex;align-items:center;gap:12px;}
.agi-brand-logo-img{height:52px;width:52px;object-fit:contain;border-radius:50%;flex-shrink:0;border:1px solid var(--agi-border);background:#fff;}
.agi-brand-name{font-family:'Poppins',sans-serif;font-size:22px;font-weight:800;letter-spacing:-0.3px;line-height:1.15;color:#0b1a14;}
.agi-brand-name .agi-agri{color:#0b1a14 !important;}
.agi-brand-name .agi-cart{color:#5A9802 !important;margin-left:1px;}
/* Defensive: hide a site-wide "page loading" progress bar, if your
   theme injects one, so it never gets stuck visible on a printed or
   exported invoice. Safe no-op if no such element exists. */
#nprogress, .pace, .pace .pace-progress, .page-loader, .site-loader, .loading-bar, .topbar-loader { display: none !important; }
.agi-tagline{font-size:11.5px;color:var(--agi-muted);letter-spacing:.3px;margin-top:2px;font-weight:600;}
.agi-powered-by{font-size:10.5px;color:var(--agi-muted);margin-top:3px;}
.agi-powered-by b{color:var(--agi-green);}
.agi-invoice-meta{text-align:right;}
.agi-invoice-meta h1{font-family:'Poppins',sans-serif;font-size:18px;font-weight:800;color:var(--agi-dark-green);letter-spacing:.5px;margin:0 0 8px;}
.agi-meta-row{font-size:12.5px;color:var(--agi-text);margin-bottom:3px;}
.agi-meta-row b{color:var(--agi-muted);font-weight:600;margin-right:4px;}
.agi-badges{margin-top:8px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
.agi-badge{display:inline-block;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.2px;}
.agi-badge-success{background:var(--agi-light-green);color:var(--agi-green);}
.agi-badge-pending{background:var(--agi-amber-bg);color:var(--agi-amber);}
.agi-badge-danger{background:var(--agi-red-bg);color:var(--agi-red);}

.agi-parties{display:flex;gap:18px;margin-bottom:26px;}
.agi-card{flex:1;border:1px solid var(--agi-border);border-radius:10px;padding:16px 18px;background:#FAFBFA;}
.agi-card h3{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--agi-green);font-weight:700;margin:0 0 10px;}
.agi-card p{margin:0 0 4px;font-size:13px;line-height:1.55;}
.agi-card p b{font-weight:600;}
.agi-card .agi-muted-line{color:var(--agi-muted);font-size:12px;}
.agi-gstin-pill{display:inline-block;margin-top:6px;padding:4px 10px;border-radius:6px;background:var(--agi-light-green);color:var(--agi-dark-green);font-size:12px;font-weight:700;letter-spacing:.3px;}
.agi-gstin-pill.agi-unregistered{background:var(--agi-amber-bg);color:var(--agi-amber);}

.agi-table-wrap{overflow-x:auto;margin-bottom:22px;border:1px solid var(--agi-border);border-radius:10px;}
table.agi-table{width:100%;border-collapse:collapse;min-width:980px;font-size:12px;}
table.agi-table thead th{background:var(--agi-dark-green);color:#fff;text-align:left;padding:9px 10px;font-weight:600;font-size:10.5px;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;}
table.agi-table tbody td{padding:9px 10px;border-top:1px solid var(--agi-border);vertical-align:top;white-space:nowrap;}
table.agi-table tbody tr:nth-child(even){background:#FAFBFA;}
.agi-prod-name{font-weight:600;white-space:normal;}
.agi-prod-sub{color:var(--agi-muted);font-size:10.5px;margin-top:2px;white-space:normal;}
.agi-num{text-align:right;}

.agi-summary-wrap{display:flex;justify-content:flex-end;margin-bottom:22px;}
.agi-summary{width:340px;border:1px solid var(--agi-border);border-radius:10px;overflow:hidden;}
.agi-summary-row{display:flex;justify-content:space-between;padding:9px 16px;font-size:13px;border-bottom:1px solid var(--agi-border);}
.agi-summary-row span:first-child{color:var(--agi-muted);}
.agi-summary-row.agi-subrow span:first-child{padding-left:14px;font-size:12px;}
.agi-summary-row.agi-grand{background:var(--agi-light-green);font-weight:800;font-size:15px;color:var(--agi-dark-green);border-bottom:none;padding:14px 16px;}

/* ---------- Platform charges + Seller net earnings (new) ---------- */
.agi-settle-wrap{display:flex;gap:18px;margin-bottom:26px;flex-wrap:wrap;}
.agi-settle-card{flex:1;min-width:280px;border-radius:10px;overflow:hidden;border:1px solid var(--agi-border);}
.agi-settle-head{padding:12px 16px;font-family:'Poppins',sans-serif;font-weight:700;font-size:12.5px;text-transform:uppercase;letter-spacing:.5px;}
.agi-settle-charges .agi-settle-head{background:var(--agi-amber-bg);color:var(--agi-amber);}
.agi-settle-earnings .agi-settle-head{background:var(--agi-light-green);color:var(--agi-dark-green);}
.agi-settle-body{padding:6px 16px 4px;}
.agi-settle-row{display:flex;justify-content:space-between;padding:8px 0;font-size:13px;border-bottom:1px solid var(--agi-border);}
.agi-settle-row:last-child{border-bottom:none;}
.agi-settle-row span:first-child{color:var(--agi-muted);}
.agi-settle-total{display:flex;justify-content:space-between;padding:14px 16px;font-weight:800;font-size:16px;}
.agi-settle-charges .agi-settle-total{background:#FFF1E0;color:var(--agi-amber);}
.agi-settle-earnings .agi-settle-total{background:var(--agi-green);color:#fff;}

.agi-payment-info{border:1px solid var(--agi-border);border-radius:10px;padding:16px 18px;margin-bottom:26px;background:#FAFBFA;}
.agi-payment-info h3{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--agi-green);font-weight:700;margin:0 0 10px;}
.agi-payment-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
.agi-payment-grid div span{display:block;font-size:11px;color:var(--agi-muted);margin-bottom:2px;}
.agi-payment-grid div b{font-size:13px;}

.agi-lower{display:flex;gap:18px;margin-bottom:22px;flex-wrap:wrap;}
.agi-lower .agi-card{flex:1;min-width:220px;}
.agi-lower ul{margin:0;padding-left:18px;font-size:12px;color:var(--agi-muted);line-height:1.7;}
.agi-sign-box{border:1px dashed var(--agi-border);border-radius:8px;height:66px;display:flex;align-items:center;justify-content:center;color:var(--agi-muted);font-size:11px;margin:10px 0 8px;overflow:hidden;padding:4px;}
.agi-sign-box.agi-has-asset{border-style:solid;background:#fff;}
.agi-sign-assets{display:flex;gap:10px;align-items:center;justify-content:center;width:100%;height:100%;}
.agi-sign-assets img{max-height:56px;max-width:48%;object-fit:contain;}
.agi-signatory-name{font-size:11.5px;font-weight:600;margin-top:2px;}
.agi-signatory-designation{font-size:10.5px;color:var(--agi-muted);}

.agi-footer{text-align:center;border-top:1px solid var(--agi-border);padding-top:18px;}
.agi-footer .agi-thanks{font-family:'Poppins',sans-serif;font-weight:700;color:var(--agi-dark-green);font-size:14px;margin-bottom:4px;}
.agi-footer .agi-computer-gen{font-size:11px;color:var(--agi-muted);margin-bottom:10px;}
.agi-footer-links{font-size:11.5px;}
.agi-footer-links a{color:var(--agi-green);text-decoration:none;margin:0 6px;font-weight:600;}
.agi-footer-links a:hover{text-decoration:underline;}

@media (max-width: 720px){
    .agi-sheet{padding:22px 16px;}
    .agi-parties{flex-direction:column;}
    .agi-header-row{flex-direction:column;}
    .agi-invoice-meta{text-align:left;width:100%;}
    .agi-badges{justify-content:flex-start;}
    .agi-payment-grid{grid-template-columns:repeat(2,1fr);}
    .agi-lower{flex-direction:column;}
    .agi-settle-wrap{flex-direction:column;}
    .agi-summary{width:100%;}
}
@media print{
    body *{visibility:hidden;}
    .agi-print-area, .agi-print-area *{visibility:visible;}
    .agi-print-area{position:absolute;left:0;top:0;width:100%;margin:0;box-shadow:none;border:none;padding:14mm;}
    .agi-toolbar, .agi-no-print{display:none !important;}
    @page{size:A4;margin:0;}
    table.agi-table{font-size:10px;}
    .agi-summary, .agi-settle-wrap, .agi-payment-info, .agi-lower .agi-card, .agi-table-wrap{break-inside:avoid;}
}
</style>

<div class="agi-page">
  <div class="agi-toolbar agi-no-print" style="justify-content:flex-end;">
    <div class="agi-toolbar-actions">
      <a class="agi-btn agi-btn-outline" href="<?php echo htmlspecialchars($agiBackHref); ?>"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>
      <button class="agi-btn agi-btn-outline" id="agiPrintBtn"><i class="fa-solid fa-print"></i> Print Invoice</button>
      <button class="agi-btn agi-btn-green" id="agiPdfBtn"><i class="fa-solid fa-file-arrow-down"></i> Download Invoice</button>
    </div>
  </div>

  <div class="agi-sheet agi-print-area" id="agiInvoiceSheet">

    <!-- Header — SELLER is the invoice issuer, AgriCart is "powered by" -->
    <div class="agi-header-row">
      <div class="agi-brand">
        <?php if ($sellerLogoUrl): ?>
          <img class="agi-brand-logo-img" src="<?php echo htmlspecialchars($sellerLogoUrl); ?>" alt="" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($fallbackLogo); ?>';">
        <?php else: ?>
          <img class="agi-brand-logo-img" src="<?php echo htmlspecialchars($fallbackLogo); ?>" alt="">
        <?php endif; ?>
        <div>
          <div class="agi-brand-name">
            <?php if (stripos(trim($sellerDisplayName), 'AgriCart') === 0):
                $agiRest = trim(substr(trim($sellerDisplayName), 8)); ?>
              <span class="agi-agri">Agri</span><span class="agi-cart">Cart</span><?php echo $agiRest !== '' ? ' ' . agi_e($agiRest) : ''; ?>
            <?php else: ?>
              <?php echo agi_e($sellerDisplayName); ?>
            <?php endif; ?>
          </div>
          <div class="agi-tagline">Seller Invoice</div>
          <div class="agi-powered-by">Platform: <b>AgriCart</b></div>
        </div>
      </div>
      <div class="agi-invoice-meta">
        <h1><?php echo $isTaxInvoice ? 'TAX INVOICE' : 'SELLER INVOICE'; ?></h1>
        <div class="agi-meta-row"><b>Invoice No:</b><?php echo agi_e($invoiceNo); ?></div>
        <div class="agi-meta-row"><b>Order ID:</b><?php echo agi_e($orderCode); ?></div>
        <div class="agi-meta-row"><b>Invoice Date:</b><?php echo agi_e($invoiceDate); ?></div>
        <div class="agi-meta-row"><b>Order Date:</b><?php echo agi_e($orderDate); ?></div>
        <div class="agi-meta-row"><b>Seller ID:</b>#<?php echo (int)$sellerId; ?></div>
        <div class="agi-badges">
          <span class="agi-badge <?php echo strtolower($paymentStatus) === 'paid' ? 'agi-badge-success' : (strtolower($paymentStatus) === 'failed' ? 'agi-badge-danger' : 'agi-badge-pending'); ?>">
            <?php echo agi_e($paymentStatus); ?>
          </span>
          <span class="agi-badge <?php echo in_array(strtolower($orderStatus), ['delivered','completed']) ? 'agi-badge-success' : (in_array(strtolower($orderStatus), ['cancelled','returned']) ? 'agi-badge-danger' : 'agi-badge-pending'); ?>">
            <?php echo agi_e($orderStatus); ?>
          </span>
        </div>
      </div>
    </div>

    <!-- Seller (issuer) details / Bill To (buyer) -->
    <div class="agi-parties">
      <div class="agi-card">
        <h3>Seller Details</h3>
        <p><b><?php echo agi_e($sellerDisplayName); ?></b></p>
        <?php if (!empty($sellerProfile['business_name']) && $sellerProfile['business_name'] !== $sellerProfile['seller_name']): ?>
          <p class="agi-muted-line"><?php echo agi_e($sellerProfile['seller_name']); ?></p>
        <?php endif; ?>
        <?php echo agis_line_or_hide('', $sellerAddressLine !== 'Address on file with AgriCart' ? $sellerAddressLine : ''); ?>
        <?php echo agis_line_or_hide('', $sellerCityState . ($sellerProfile['seller_pin'] ? ' — ' . $sellerProfile['seller_pin'] : '')); ?>
        <?php echo agis_line_or_hide('Phone:', $sellerProfile['seller_mobile'] ?? ''); ?>
        <?php echo agis_line_or_hide('Email:', $sellerProfile['seller_email'] ?? ''); ?>
        <?php if ($sellerHasGstin): ?>
          <div class="agi-gstin-pill">GSTIN: <?php echo agi_e($sellerProfile['seller_gstin']); ?></div>
        <?php else: ?>
          <div class="agi-gstin-pill agi-unregistered">GSTIN: Not Registered</div>
        <?php endif; ?>
      </div>
      <div class="agi-card">
        <h3>Bill To (Customer)</h3>
        <p><b><?php echo agi_e($order['buyer_name'], 'Customer'); ?></b></p>
        <?php echo agis_line_or_hide('', $order['buyer_mobile'] ?? ''); ?>
        <?php echo agis_line_or_hide('', $order['buyer_email'] ?? ''); ?>
        <p class="agi-muted-line">Ship To</p>
        <p><?php echo agi_e($deliveryLine1); ?></p>
        <?php echo agis_line_or_hide('', $deliveryCityState . ($deliveryPin ? ' — ' . $deliveryPin : '')); ?>
      </div>
    </div>

    <!-- Product table — ONLY this seller's items -->
    <div class="agi-table-wrap">
      <table class="agi-table">
        <thead>
          <tr>
            <th>Sr.</th>
            <th>Product Name</th>
            <th>SKU</th>
            <th>Category</th>
            <th>Qty</th>
            <th>Unit</th>
            <th>Unit Price</th>
            <th>Discount</th>
            <th>Taxable Value</th>
            <th>GST %</th>
            <th>GST Amt</th>
            <th>Final Amount</th>
          </tr>
        </thead>
        <tbody>
          <?php $sr = 1; foreach ($items as $it):
              $qty = (float)$it['qty']; $price = (float)$it['price'];
              $itemDiscount = (float)($it['item_discount'] ?? 0);
              $itemGstAmt = (float)($it['item_gst'] ?? 0);
              $gstRatePct = round(((float)($it['gst_rate'] ?? 0)) * 100, 2);
          ?>
          <tr>
            <td><?php echo $sr++; ?></td>
            <td>
              <div class="agi-prod-name"><?php echo agi_e($it['product_name'], 'Product'); ?></div>
              <div class="agi-prod-sub">Product ID: <?php echo (int)$it['product_id']; ?></div>
            </td>
            <td><?php echo agi_e($it['sku']); ?></td>
            <td><?php echo agi_e($it['category'], 'General'); ?></td>
            <td><?php echo rtrim(rtrim(number_format($qty, 2), '0'), '.'); ?></td>
            <td><?php echo agi_e($it['unit']); ?></td>
            <td class="agi-num"><?php echo agi_money($price); ?></td>
            <td class="agi-num"><?php echo $itemDiscount > 0 ? '&minus;' . agi_money($itemDiscount) : agi_money(0); ?></td>
            <td class="agi-num"><?php echo agi_money($it['taxable_value']); ?></td>
            <td class="agi-num"><?php echo $gstRatePct > 0 ? rtrim(rtrim(number_format($gstRatePct, 2), '0'), '.') . '%' : 'Nil'; ?></td>
            <td class="agi-num"><?php echo agi_money($itemGstAmt); ?></td>
            <td class="agi-num"><b><?php echo agi_money($it['final_amount']); ?></b></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Seller-specific financial summary -->
    <div class="agi-summary-wrap">
      <div class="agi-summary">
        <div class="agi-summary-row"><span>Product Subtotal</span><span><?php echo agi_money($sellerTaxableSubtotal + $lineDiscount); ?></span></div>
        <?php if ($lineDiscount > 0): ?>
        <div class="agi-summary-row"><span>Product Discount</span><span>&minus;<?php echo agi_money($lineDiscount); ?></span></div>
        <?php endif; ?>
        <?php if ($sellerCouponShare > 0): ?>
        <div class="agi-summary-row"><span><?php echo $order['coupon_code'] ? 'Coupon Discount (' . agi_e($order['coupon_code']) . ')' : 'Coupon Discount (your share)'; ?></span><span>&minus;<?php echo agi_money($sellerCouponShare); ?></span></div>
        <?php endif; ?>
        <div class="agi-summary-row"><span>Taxable Value</span><span><?php echo agi_money($sellerTaxableSubtotal - $sellerCouponShare); ?></span></div>
        <?php if ($isInterState): ?>
        <div class="agi-summary-row agi-subrow"><span>IGST</span><span><?php echo agi_money($igst); ?></span></div>
        <?php else: ?>
        <div class="agi-summary-row agi-subrow"><span>CGST</span><span><?php echo agi_money($cgst); ?></span></div>
        <div class="agi-summary-row agi-subrow"><span>SGST</span><span><?php echo agi_money($sgst); ?></span></div>
        <?php endif; ?>
        <div class="agi-summary-row"><span>Total GST</span><span><?php echo agi_money($sellerGst); ?></span></div>
        <div class="agi-summary-row"><span>Delivery Charge (info only)</span><span><?php echo $sellerDeliveryShare > 0 ? agi_money($sellerDeliveryShare) : 'Free'; ?></span></div>
        <div class="agi-summary-row agi-grand"><span>Gross Order Value</span><span><?php echo agi_money($grossOrderValue); ?></span></div>
      </div>
    </div>

    <!-- Platform charges + Seller net earnings -->
    <div class="agi-settle-wrap">
      <div class="agi-settle-card agi-settle-charges">
        <div class="agi-settle-head"><i class="fa-solid fa-store"></i> AgriCart Platform Charges</div>
        <div class="agi-settle-body">
          <div class="agi-settle-row"><span>Platform Commission (<?php echo rtrim(rtrim(number_format($platformCommissionRate, 2), '0'), '.'); ?>%)</span><span>&minus;<?php echo agi_money($platformCommission); ?></span></div>
          <div class="agi-settle-row"><span>GST on Commission (<?php echo rtrim(rtrim(number_format($platformGstOnCommissionRate, 2), '0'), '.'); ?>%)</span><span>&minus;<?php echo agi_money($platformGstOnCommission); ?></span></div>
          <?php if ($otherDeductions > 0): ?>
          <div class="agi-settle-row"><span>Other Deductions</span><span>&minus;<?php echo agi_money($otherDeductions); ?></span></div>
          <?php endif; ?>
        </div>
        <div class="agi-settle-total"><span>Total Platform Deduction</span><span>&minus;<?php echo agi_money($platformCommission + $platformGstOnCommission + $otherDeductions); ?></span></div>
      </div>
      <div class="agi-settle-card agi-settle-earnings">
        <div class="agi-settle-head"><i class="fa-solid fa-sack-dollar"></i> Seller Net Earnings</div>
        <div class="agi-settle-body">
          <div class="agi-settle-row"><span>Gross Order Value</span><span><?php echo agi_money($grossOrderValue); ?></span></div>
          <div class="agi-settle-row"><span>Total Platform Deduction</span><span>&minus;<?php echo agi_money($platformCommission + $platformGstOnCommission + $otherDeductions); ?></span></div>
        </div>
        <div class="agi-settle-total"><span>Net Payable</span><span><?php echo agi_money($sellerNetPayable); ?></span></div>
      </div>
    </div>
    <?php if ($ledgerNetPayable !== null): ?>
    <p class="agi-muted-line" style="margin:-14px 0 22px;font-size:11px;">Figures above are sourced from your Seller Earnings ledger for this order, so they always match your Dashboard and Withdrawal history.</p>
    <?php endif; ?>

    <!-- Payment info -->
    <div class="agi-payment-info">
      <h3>Payment Information</h3>
      <div class="agi-payment-grid">
        <div><span>Payment Method</span><b><?php echo agi_e($paymentMethod); ?></b></div>
        <div><span>Transaction ID</span><b><?php echo agi_e($order['txn_id'], $isCod ? '—' : 'N/A'); ?></b></div>
        <div><span>Payment Date</span><b><?php echo $order['payment_date'] ? agi_e(date('d M Y', strtotime($order['payment_date']))) : agi_e($invoiceDate); ?></b></div>
        <div><span>Payment Status</span><b><?php echo agi_e($paymentStatus); ?></b></div>
      </div>
    </div>

    <!-- Notes / Support / Signature -->
    <div class="agi-lower">
      <div class="agi-card">
        <h3>Notes</h3>
        <ul>
          <li>This invoice reflects only your products in this order.</li>
          <li>Net Payable will be settled per AgriCart's standard payout cycle.</li>
          <li>Keep this invoice for your GST and accounting records.</li>
        </ul>
      </div>
      <div class="agi-card">
        <h3>Seller Support</h3>
        <p><i class="fa-solid fa-phone"></i> <?php echo agi_e($agiContact['phone']); ?></p>
        <p><i class="fa-solid fa-envelope"></i> sellers@agricart.in</p>
        <p><i class="fa-solid fa-globe"></i> <?php echo agi_e($agiContact['website']); ?></p>
        <p class="agi-muted-line">Mon–Sat, 9:00 AM – 7:00 PM</p>
      </div>
      <div class="agi-card">
        <h3>Authorized Signatory</h3>
        <?php $agiHasSig = !empty($agiAgricartSignatory['signature_path']); $agiHasStamp = !empty($agiAgricartSignatory['stamp_path']); ?>
        <div class="agi-sign-box<?php echo ($agiHasSig || $agiHasStamp) ? ' agi-has-asset' : ''; ?>">
          <?php if ($agiHasSig || $agiHasStamp): ?>
            <div class="agi-sign-assets">
              <?php if ($agiHasSig): ?><img src="../<?php echo htmlspecialchars($agiAgricartSignatory['signature_path']); ?>" alt="Signature"><?php endif; ?>
              <?php if ($agiHasStamp): ?><img src="../<?php echo htmlspecialchars($agiAgricartSignatory['stamp_path']); ?>" alt="Stamp"><?php endif; ?>
            </div>
          <?php else: ?>
            Digital Signature / Seller Stamp
          <?php endif; ?>
        </div>
        <?php if (!empty($agiAgricartSignatory['signatory_name'])): ?>
          <p class="agi-signatory-name"><?php echo agi_e($agiAgricartSignatory['signatory_name']); ?></p>
        <?php endif; ?>
        <?php if (!empty($agiAgricartSignatory['designation'])): ?>
          <p class="agi-signatory-designation"><?php echo agi_e($agiAgricartSignatory['designation']); ?></p>
        <?php endif; ?>
        <p class="agi-muted-line">For AgriCart</p>
      </div>
    </div>

    <!-- Footer -->
    <div class="agi-footer">
      <div class="agi-thanks">Seller Invoice — <?php echo agi_e($sellerDisplayName); ?></div>
      <div class="agi-computer-gen">This is a computer-generated invoice and does not require a physical signature. Powered by AgriCart Marketplace.</div>
      <div class="agi-footer-links">
        <a href="return-policy.php">Return Policy</a>|
        <a href="terms.php">Terms and Conditions</a>|
        <a href="privacy-policy.php">Privacy Policy</a>
      </div>
    </div>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
(function () {
  const invoiceNo = <?php echo json_encode($invoiceNo); ?>;
  const sheet = document.getElementById('agiInvoiceSheet');

  document.getElementById('agiPrintBtn')?.addEventListener('click', function () {
    window.print();
  });

  document.getElementById('agiPdfBtn')?.addEventListener('click', async function () {
    const btn = this;
    const original = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Preparing...';
    try {
      const canvas = await html2canvas(sheet, { scale: 2, useCORS: true, backgroundColor: '#ffffff' });
      const imgData = canvas.toDataURL('image/png');
      const { jsPDF } = window.jspdf;
      const pdf = new jsPDF('p', 'mm', 'a4');
      const pageWidth = pdf.internal.pageSize.getWidth();
      const pageHeight = pdf.internal.pageSize.getHeight();
      const imgWidth = pageWidth;
      const imgHeight = (canvas.height * imgWidth) / canvas.width;
      let heightLeft = imgHeight, position = 0;
      pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
      heightLeft -= pageHeight;
      while (heightLeft > 0) {
        position = heightLeft - imgHeight;
        pdf.addPage();
        pdf.addImage(imgData, 'PNG', 0, position, imgWidth, imgHeight);
        heightLeft -= pageHeight;
      }
      pdf.save(invoiceNo + '.pdf');
    } catch (e) {
      console.error('PDF generation failed', e);
      alert('Could not generate the PDF. You can still use Print Invoice.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = original;
    }
  });
})();
</script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
