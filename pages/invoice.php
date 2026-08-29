<?php
// =====================================================================
// pages/invoice.php — AgriCart "Product Invoice" page.
//
// URL:  invoice.php?order_id=1842
//
// WHO CAN OPEN IT
//   - The buyer who placed the order (session user_id = orders.<buyer col>)
//   - Any seller who has at least one item in that order
//     (products.added_by_user_id = session user_id)
//   - An admin (adjust agi_is_admin() below to match your admin session flag)
//
// SCHEMA SAFETY
//   This file never assumes exact column names beyond what dashboard.js /
//   seller_api.php already rely on (via agri_seller_columns()). Every extra
//   column it needs (email, city, state, pincode, GSTIN, payment method,
//   transaction id, discounts, tax, round off, grand total...) is looked up
//   with agi_pick_col() against the live schema first. If a column truly
//   doesn't exist anywhere, the page falls back to a clearly-labelled
//   sample value instead of breaking — per the "use realistic sample data
//   only when DB data is unavailable" requirement.
//
// Does NOT touch includes/header.php, includes/footer.php, includes/db.php,
// or the login system — it only *uses* them, exactly like dashboard.php.
// =====================================================================

require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/../includes/gstin_schema.php';
// A product's seller may be either (a) a real logged-in seller account
// (products.added_by_user_id -> users) or (b) a Company/Demo-Seller
// business profile that Admin manages under Admin -> Companies
// (products.seller_id/farmer_name -> sellers), with no login account at
// all. Both must be able to appear correctly on the buyer invoice —
// see admin/includes/companies_schema.php for the seller_id/farmer_name
// matching helpers this page reuses below instead of re-implementing.
require_once __DIR__ . '/../admin/includes/companies_schema.php';
if (function_exists('companies_bootstrap_schema')) companies_bootstrap_schema($conn);
if (function_exists('gstin_bootstrap_schema')) gstin_bootstrap_schema($conn);
$agiContact = agri_company_contact($conn);

// ---------------------------------------------------------------------
// 1. Auth
// ---------------------------------------------------------------------
// TODO: point this at however your site marks an admin session
// (e.g. $_SESSION['role'] === 'admin', or a separate admin login table).
function agi_is_admin(): bool {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// Admin panel sessions clear $_SESSION['user_id'] and set
// $_SESSION['is_admin'] instead — so an admin must be let in here even
// without a user_id, or every "Invoice" link from the admin panel would
// bounce them to the customer login page.
if (!isset($_SESSION['user_id']) && !agi_is_admin()) {
    $next = 'pages/invoice.php' . (isset($_GET['order_id']) ? ('?order_id=' . (int)$_GET['order_id']) : '');
    header('Location: login.php?next=' . urlencode($next));
    exit();
}
$viewerId = (int)($_SESSION['user_id'] ?? 0);

$orderId = (int)($_GET['order_id'] ?? 0);

function agi_render_message_page(string $title, string $message, int $code = 404): void {
    http_response_code($code);
    include __DIR__ . '/../includes/header.php';
    echo '<div class="agi-msg-page"><div class="agi-msg-card">'
       . '<i class="fa-solid fa-file-circle-exclamation"></i>'
       . '<h2>' . htmlspecialchars($title) . '</h2>'
       . '<p>' . htmlspecialchars($message) . '</p>'
       . '<a class="agi-btn agi-btn-green" href="orders.php"><i class="fa-solid fa-arrow-left"></i> Back to Orders</a>'
       . '</div></div>';
    include __DIR__ . '/../includes/footer.php';
    exit();
}

if ($orderId <= 0) {
    agi_render_message_page('Invalid invoice link', 'No valid order was specified. Please open this invoice from your Orders page.', 400);
}

// ---------------------------------------------------------------------
// 2. Schema-agnostic column helpers
// ---------------------------------------------------------------------
function agi_col_exists(mysqli $conn, string $table, string $col): bool {
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $cache[$key] = ($res && $res->num_rows > 0);
}
function agi_pick_col(mysqli $conn, string $table, array $candidates, ?string $default = null): ?string {
    foreach ($candidates as $c) {
        if (agi_col_exists($conn, $table, $c)) return $c;
    }
    return $default;
}
function agi_table_exists(mysqli $conn, string $table): bool {
    static $cache = [];
    if (array_key_exists($table, $cache)) return $cache[$table];
    $t = $conn->real_escape_string($table);
    $res = $conn->query("SHOW TABLES LIKE '$t'");
    return $cache[$table] = ($res && $res->num_rows > 0);
}

// Reuse the column map the rest of the seller dashboard already relies on.
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

// Extra order-level columns this page needs, none of which are guaranteed
// to exist yet — every one degrades to a sample value below if absent.
$oStatusCol   = agi_pick_col($conn, 'orders', ['order_status', 'status']);
$payMethodCol = agi_pick_col($conn, 'orders', ['payment_mode', 'payment_method', 'pay_method', 'mode_of_payment']);
$payStatusCol = agi_pick_col($conn, 'orders', ['payment_status', 'pay_status']);
$txnCol       = agi_pick_col($conn, 'orders', ['transaction_id', 'txn_id', 'payment_txn_id']);
$payDateCol   = agi_pick_col($conn, 'orders', ['payment_date', 'paid_at']);
$subtotalCol  = agi_pick_col($conn, 'orders', ['subtotal', 'product_subtotal', 'total_amount']);
$couponCodeCol = agi_pick_col($conn, 'orders', ['coupon_code', 'coupon', 'promo_code']);
$discountCol  = agi_pick_col($conn, 'orders', ['product_discount', 'discount_amount', 'discount']);
$couponCol    = agi_pick_col($conn, 'orders', ['coupon_discount', 'coupon_amount', 'coupon_value', 'promo_discount']);
$deliveryCol  = agi_pick_col($conn, 'orders', ['delivery_charge', 'shipping_charge', 'delivery_fee']);
$gstCol       = agi_pick_col($conn, 'orders', ['gst_amount', 'tax_amount', 'tax']);
$roundOffCol  = agi_pick_col($conn, 'orders', ['round_off', 'roundoff']);
$grandTotalCol= agi_pick_col($conn, 'orders', ['final_amount', 'grand_total', 'order_total']);
$paidCol      = agi_pick_col($conn, 'orders', ['amount_paid', 'paid_amount']);
$cityCol      = agi_pick_col($conn, 'orders', ['city']);
$stateCol     = agi_pick_col($conn, 'orders', ['state']);
$pinCol       = agi_pick_col($conn, 'orders', ['pincode', 'pin_code']);

$userEmailCol = agi_pick_col($conn, 'users', ['email']);
$userTalukaCol= agi_pick_col($conn, 'users', ['taluka']);
$userDistrictCol = agi_pick_col($conn, 'users', ['district']);
$userStateCol = agi_pick_col($conn, 'users', ['state']);
$userPinCol   = agi_pick_col($conn, 'users', ['pincode', 'pin_code']);
$userGstinCol = agi_pick_col($conn, 'users', ['gstin', 'gst_number']);

$prodCategoryCol = agi_pick_col($conn, 'products', ['category']);
$prodCategoryIdCol = agi_pick_col($conn, 'products', ['category_id']);
$prodImageCol = agi_pick_col($conn, 'products', ['image', 'image_url', 'photo']);
$prodSkuCol   = agi_pick_col($conn, 'products', ['sku', 'product_code']);
$prodUnitCol  = agi_pick_col($conn, 'products', ['unit', 'package_size']);
$prodNameMrCol = agi_pick_col($conn, 'products', ['name_mr', 'name_marathi']);
$prodNameHiCol = agi_pick_col($conn, 'products', ['name_hi', 'name_hindi']);

$itemDiscountCol = agi_pick_col($conn, 'order_items', ['discount', 'discount_amount']);
$itemGstCol   = agi_pick_col($conn, 'order_items', ['gst_amount', 'tax_amount']);

// ---------------------------------------------------------------------
// 3. Fetch the order + line items
// ---------------------------------------------------------------------
$select = "o.id, o.$buyerCol buyer_id, o.$createdCol created_at";
$select .= $oStatusCol ? ", o.$oStatusCol order_status" : ", NULL order_status";
$select .= $payMethodCol ? ", o.$payMethodCol payment_method" : ", NULL payment_method";
$select .= $payStatusCol ? ", o.$payStatusCol payment_status" : ", NULL payment_status";
$select .= $txnCol ? ", o.$txnCol txn_id" : ", NULL txn_id";
$select .= $payDateCol ? ", o.$payDateCol payment_date" : ", NULL payment_date";
$select .= $subtotalCol ? ", o.$subtotalCol subtotal" : ", NULL subtotal";
$select .= $discountCol ? ", o.$discountCol product_discount" : ", NULL product_discount";
$select .= $couponCol ? ", o.$couponCol coupon_discount" : ", NULL coupon_discount";
$select .= $couponCodeCol ? ", o.$couponCodeCol coupon_code" : ", NULL coupon_code";
$select .= $deliveryCol ? ", o.$deliveryCol delivery_charge" : ", NULL delivery_charge";
$select .= $gstCol ? ", o.$gstCol gst_amount" : ", NULL gst_amount";
$select .= $roundOffCol ? ", o.$roundOffCol round_off" : ", NULL round_off";
$select .= $grandTotalCol ? ", o.$grandTotalCol grand_total" : ", NULL grand_total";
$select .= $paidCol ? ", o.$paidCol amount_paid" : ", NULL amount_paid";
$select .= $addrCol ? ", o.$addrCol delivery_address" : ", NULL delivery_address";
$select .= (!$addrCol && $notesCol) ? ", o.$notesCol delivery_notes" : ", NULL delivery_notes";
$select .= $villageCol ? ", o.$villageCol delivery_village" : ", NULL delivery_village";
$select .= $cityCol ? ", o.$cityCol delivery_city" : ", NULL delivery_city";
$select .= $stateCol ? ", o.$stateCol delivery_state" : ", NULL delivery_state";
$select .= $pinCol ? ", o.$pinCol delivery_pin" : ", NULL delivery_pin";
$select .= ", u.$nameCol buyer_name, u.$mobileCol buyer_mobile";
$select .= $userEmailCol ? ", u.$userEmailCol buyer_email" : ", NULL buyer_email";

$orderSql = "SELECT $select FROM orders o LEFT JOIN users u ON u.id = o.$buyerCol WHERE o.id = ? LIMIT 1";
$stmt = $conn->prepare($orderSql);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$order = $stmt->get_result()->fetch_assoc();

if (!$order) {
    agi_render_message_page('Invoice not found', "We couldn't find an order matching this invoice link.", 404);
}

$isBuyer = ((int)$order['buyer_id'] === $viewerId);

// Line items (joined with product + seller/product-owner info)
$prodSelect = "oi.id item_id, oi.product_id, oi.$qtyCol qty, oi.$priceCol price, oi.item_status";
$prodSelect .= $itemDiscountCol ? ", oi.$itemDiscountCol item_discount" : ", NULL item_discount";
$prodSelect .= $itemGstCol ? ", oi.$itemGstCol item_gst" : ", NULL item_gst";
$prodSelect .= ", p.name product_name, p.added_by_user_id seller_user_id";
$prodSelect .= $prodCategoryCol ? ", p.$prodCategoryCol category" : ", NULL category";
$prodSelect .= $prodImageCol ? ", p.$prodImageCol image" : ", NULL image";
$prodSelect .= $prodSkuCol ? ", p.$prodSkuCol sku" : ", NULL sku";
$prodSelect .= $prodUnitCol ? ", p.$prodUnitCol unit" : ", NULL unit";
$prodSelect .= $prodNameMrCol ? ", p.$prodNameMrCol name_mr" : ", NULL name_mr";
$prodSelect .= $prodNameHiCol ? ", p.$prodNameHiCol name_hi" : ", NULL name_hi";
$prodSelect .= ", su.$nameCol seller_name";

// Company/Demo-Seller business profile (Admin -> Companies), matched the
// same way the Companies module itself links products to a company —
// via products.seller_id when present, else products.farmer_name =
// sellers.name. This is what lets a product whose added_by_user_id has
// no real login (or no name on file) still show its real business
// name/GSTIN/signature/stamp instead of a generic placeholder.
$hasSellersTable = agi_table_exists($conn, 'sellers');
$companySelect = '';
if ($hasSellersTable) {
    $companySelect .= ", cs.id company_id, cs.name company_name";
    $companySelect .= agi_col_exists($conn, 'sellers', 'trade_name') ? ", cs.trade_name company_trade_name" : ", NULL company_trade_name";
    $companySelect .= agi_col_exists($conn, 'sellers', 'gstin') ? ", cs.gstin company_gstin" : ", NULL company_gstin";
    $companySelect .= agi_col_exists($conn, 'sellers', 'registered_address') ? ", cs.registered_address company_address" : ", NULL company_address";
    $companySelect .= agi_col_exists($conn, 'sellers', 'state') ? ", cs.state company_state" : ", NULL company_state";
    $companySelect .= agi_col_exists($conn, 'sellers', 'state_code') ? ", cs.state_code company_state_code" : ", NULL company_state_code";
    $companySelect .= agi_col_exists($conn, 'sellers', 'pincode') ? ", cs.pincode company_pincode" : ", NULL company_pincode";
    $companySelect .= agi_col_exists($conn, 'sellers', 'signature_path') ? ", cs.signature_path company_signature_path" : ", NULL company_signature_path";
    $companySelect .= agi_col_exists($conn, 'sellers', 'stamp_path') ? ", cs.stamp_path company_stamp_path" : ", NULL company_stamp_path";
    $companySelect .= agi_col_exists($conn, 'sellers', 'authorized_signatory_name') ? ", cs.authorized_signatory_name company_signatory_name" : ", NULL company_signatory_name";
    $companySelect .= agi_col_exists($conn, 'sellers', 'signatory_designation') ? ", cs.signatory_designation company_signatory_designation" : ", NULL company_signatory_designation";
} else {
    $companySelect = ", NULL company_id, NULL company_name, NULL company_trade_name, NULL company_gstin, NULL company_address, NULL company_state, NULL company_state_code, NULL company_pincode, NULL company_signature_path, NULL company_stamp_path, NULL company_signatory_name, NULL company_signatory_designation";
}
$prodSelect .= $companySelect;

$companyJoin = '';
if ($hasSellersTable && function_exists('cmp_company_match_joined')) {
    $matchSql = cmp_company_match_joined($conn, 'p', 'cs');
    $companyJoin = "LEFT JOIN sellers cs ON $matchSql";
}

$itemsSql = "SELECT $prodSelect
             FROM order_items oi
             JOIN products p ON p.id = oi.product_id
             LEFT JOIN users su ON su.id = p.added_by_user_id
             $companyJoin
             WHERE oi.order_id = ?
             ORDER BY oi.id ASC";
$stmt = $conn->prepare($itemsSql);
$stmt->bind_param('i', $orderId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

if (!$items) {
    agi_render_message_page('Invoice not found', 'This order has no items on record, so no invoice can be generated.', 404);
}

// Is the viewer a seller on this order?
$viewerIsSellerHere = false;
$sellerIdsInOrder = [];
foreach ($items as $it) {
    $sid = (int)$it['seller_user_id'];
    if ($sid) $sellerIdsInOrder[$sid] = true;
    if ($sid === $viewerId) $viewerIsSellerHere = true;
}

// ---------------------------------------------------------------------
// 4. Authorization
// ---------------------------------------------------------------------
if (!$isBuyer && !$viewerIsSellerHere && !agi_is_admin()) {
    agi_render_message_page('Access denied', "You don't have permission to view this invoice.", 403);
}

// ---------------------------------------------------------------------
// 5. Seller ("Sold By") details for the card — a single-seller order shows
//    that seller's own info in the main "Sold By" card. A mixed-vendor
//    order shows the *actual* name (and GSTIN, if any) of every seller who
//    has an item in the order — never a generic placeholder — in addition
//    to the per-item seller name already shown in the product table.
// ---------------------------------------------------------------------
function agi_fetch_seller_card(mysqli $conn, int $sellerId, string $nameCol, string $mobileCol,
    ?string $userEmailCol, ?string $villageCol, ?string $userTalukaCol, ?string $userDistrictCol,
    ?string $userStateCol, ?string $userPinCol, ?string $userGstinCol): ?array {
    $sSelect = "u.id, u.$nameCol seller_name, u.$mobileCol seller_mobile";
    $sSelect .= $userEmailCol ? ", u.$userEmailCol seller_email" : ", NULL seller_email";
    $sSelect .= $villageCol ? ", u.$villageCol seller_village" : ", NULL seller_village";
    $sSelect .= $userTalukaCol ? ", u.$userTalukaCol seller_taluka" : ", NULL seller_taluka";
    $sSelect .= $userDistrictCol ? ", u.$userDistrictCol seller_district" : ", NULL seller_district";
    $sSelect .= $userStateCol ? ", u.$userStateCol seller_state" : ", NULL seller_state";
    $sSelect .= $userPinCol ? ", u.$userPinCol seller_pin" : ", NULL seller_pin";
    $sSelect .= $userGstinCol ? ", u.$userGstinCol seller_gstin" : ", NULL seller_gstin";
    $stmt = $conn->prepare("SELECT $sSelect FROM users u WHERE u.id = ? LIMIT 1");
    $stmt->bind_param('i', $sellerId);
    $stmt->execute();
    $card = $stmt->get_result()->fetch_assoc();
    if (!$card) return null;

    // Optional business name from seller_payout_profiles, if that table exists.
    if (agi_table_exists($conn, 'seller_payout_profiles') && agi_col_exists($conn, 'seller_payout_profiles', 'business_name')) {
        $stmt = $conn->prepare("SELECT business_name FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
        $stmt->bind_param('i', $sellerId);
        $stmt->execute();
        $bn = $stmt->get_result()->fetch_assoc();
        if (!empty($bn['business_name'])) $card['business_name'] = $bn['business_name'];
    }
    return $card;
}

$sellerCard = null;
$multiSellerCards = [];
if (count($sellerIdsInOrder) === 1) {
    $singleSellerId = array_key_first($sellerIdsInOrder);
    $sellerCard = agi_fetch_seller_card($conn, $singleSellerId, $nameCol, $mobileCol, $userEmailCol,
        $villageCol, $userTalukaCol, $userDistrictCol, $userStateCol, $userPinCol, $userGstinCol);
} elseif (count($sellerIdsInOrder) > 1) {
    foreach (array_keys($sellerIdsInOrder) as $sid) {
        $card = agi_fetch_seller_card($conn, $sid, $nameCol, $mobileCol, $userEmailCol,
            $villageCol, $userTalukaCol, $userDistrictCol, $userStateCol, $userPinCol, $userGstinCol);
        if ($card) $multiSellerCards[] = $card;
    }
}

// Fallback: if the seller lookup above found nothing at all (e.g. a
// product's added_by_user_id is 0/NULL, or points to a user row that no
// longer exists), don't fall straight to the generic "AgriCart
// Marketplace" placeholder — the line-item query already carried each
// item's seller_name (from the su.$nameCol JOIN), so build simple cards
// from those real names instead whenever at least one is present.
$fallbackSellerNames = [];
$fallbackVendors = []; // display name -> company GSTIN/address/signature/stamp, when known
if (!$sellerCard && !$multiSellerCards) {
    foreach ($items as $it) {
        $sn = trim((string)($it['seller_name'] ?? ''));
        $cn = trim((string)($it['company_name'] ?? ''));
        // Prefer the logged-in seller's own name; otherwise this item's
        // Company/Demo-Seller business profile name from Admin -> Companies.
        $displayName = $sn !== '' ? $sn : $cn;
        if ($displayName === '') { continue; }
        if (!in_array($displayName, $fallbackSellerNames, true)) {
            $fallbackSellerNames[] = $displayName;
        }
        if (!isset($fallbackVendors[$displayName]) && $cn !== '') {
            $fallbackVendors[$displayName] = [
                'gstin'            => $it['company_gstin'] ?? null,
                'address'          => $it['company_address'] ?? null,
                'state'            => $it['company_state'] ?? null,
                'state_code'       => $it['company_state_code'] ?? null,
                'pincode'          => $it['company_pincode'] ?? null,
                'signature_path'   => $it['company_signature_path'] ?? null,
                'stamp_path'       => $it['company_stamp_path'] ?? null,
                'signatory_name'   => $it['company_signatory_name'] ?? null,
                'designation'      => $it['company_signatory_designation'] ?? null,
            ];
        }
    }
}

// ---------------------------------------------------------------------
// Authorized Signatory (Buyer Invoice) — always the order's own
// seller(s), NEVER AgriCart (that's only for Seller Invoices — see
// pages/seller-invoice.php / seller/invoice.php). Historically
// protected: agri_buyer_invoice_signatory_block() freezes each
// seller's current signature/stamp/name the first time this order's
// buyer invoice is opened, so a seller editing their profile later
// never changes an invoice a buyer has already seen.
// ---------------------------------------------------------------------
require_once __DIR__ . '/../includes/invoice_signature_schema.php';
if (function_exists('agri_sig_bootstrap_schema')) agri_sig_bootstrap_schema($conn);

$agiSignatoryBlocks = [];
if ($sellerCard && function_exists('agri_buyer_invoice_signatory_block')) {
    $agiSignatoryBlocks[] = agri_buyer_invoice_signatory_block(
        $conn, $orderId, (int)$sellerCard['id'], $sellerCard['business_name'] ?? $sellerCard['seller_name']
    );
} elseif ($multiSellerCards && function_exists('agri_buyer_invoice_signatory_block')) {
    foreach ($multiSellerCards as $msc) {
        $agiSignatoryBlocks[] = agri_buyer_invoice_signatory_block(
            $conn, $orderId, (int)$msc['id'], $msc['business_name'] ?? $msc['seller_name']
        );
    }
} elseif ($fallbackSellerNames) {
    // No resolvable logged-in seller account (e.g. a product's
    // added_by_user_id points to a deleted account, or the product
    // belongs to a Company/Demo-Seller with no login at all) — use that
    // item's Company business profile (Admin -> Companies) for the real
    // signature/stamp/GSTIN whenever one was matched; only truly falls
    // back to name-only when no company profile exists either.
    foreach ($fallbackSellerNames as $sn) {
        $v = $fallbackVendors[$sn] ?? null;
        $agiSignatoryBlocks[] = [
            'for_name' => $sn,
            'signature_path' => $v['signature_path'] ?? null,
            'stamp_path' => $v['stamp_path'] ?? null,
            'signatory_name' => $v['signatory_name'] ?? null,
            'designation' => $v['designation'] ?? null,
            'missing_signature' => empty($v['signature_path'] ?? null),
            'missing_stamp' => empty($v['stamp_path'] ?? null),
        ];
    }
}

// 6. Numbers — prefer real order-level columns; otherwise derive from
//    the line items; only fall back to a sample figure as a last resort.
// ---------------------------------------------------------------------

// Standard GST slabs commonly applied to agri-marketplace product
// categories in India. These are typical rates, not a legal ruling —
// exact rate depends on the product's specific HSN code, so verify
// with your accountant/CA if you need this to be audit-accurate.
// Matched by keyword against products.category so odd casing/wording
// (e.g. "Seeds", "seed", "Vegetable Seeds") still resolves correctly.
function agi_gst_rate_for_category(?string $category): float {
    $c = strtolower(trim((string)$category));
    $map = [
        'seed'      => 0.00,  // seeds for sowing: nil-rated
        'feed'      => 0.00,  // animal/cattle feed: nil-rated
        'fertil'    => 0.05,  // chemical fertilizers
        'pestic'    => 0.18,  // pesticides / insecticides / fungicides
        'insectic'  => 0.18,
        'fungic'    => 0.18,
        'irrigat'   => 0.12,  // drip/sprinkler irrigation equipment
        'tool'      => 0.12,  // agricultural tools/implements
        'implement' => 0.12,
        'equipment' => 0.12,
        'organic'   => 0.05,  // organic inputs (jaggery etc. commonly 5%)
    ];
    foreach ($map as $needle => $rate) {
        if ($c !== '' && strpos($c, $needle) !== false) return $rate;
    }
    return 0.05; // reasonable default slab when category is unknown/uncategorised
}

// Prices are GST-inclusive: whenever a line item's real gst_amount is
// empty/0.00 (not populated at order time), we extract the tax portion
// for display using the government slabs above — never add it on top,
// so the grand total stays exactly what the product prices add up to.
// If gst_amount already has a real non-zero value (e.g. after a backfill),
// that stored figure is used as-is instead of recalculating it.
$gstIsInclusive = true;

// CGST/SGST vs IGST — per line item, same interstate test seller-invoice.php
// already uses (compare the item's seller/company state to the delivery
// state), since a single buyer order can contain items from sellers in
// different states. Falls back to treating a line as interstate (IGST)
// when either state is unknown, which is the safer assumption when data
// is incomplete rather than guessing an intrastate split that may not
// legally apply.
$deliveryStateForGst = trim((string)($order['delivery_state'] ?? ''));

$lineSubtotal = 0.0; $lineDiscount = 0.0; $lineGst = 0.0; $lineCgst = 0.0; $lineSgst = 0.0; $lineIgst = 0.0;
foreach ($items as $idx => $it) {
    $lineAmt = (float)$it['qty'] * (float)$it['price'];
    $lineSubtotal += $lineAmt;
    $lineDiscount += (float)($it['item_discount'] ?? 0);
    $storedGst = (float)($it['item_gst'] ?? 0);
    if ($storedGst > 0) {
        $items[$idx]['item_gst'] = $storedGst;
        $itemGstAmt = $storedGst;
    } else {
        $netLine = $lineAmt - (float)($it['item_discount'] ?? 0);
        $rate = agi_gst_rate_for_category($it['category'] ?? null);
        $extractedGst = $rate > 0 ? round($netLine - ($netLine / (1 + $rate)), 2) : 0.0;
        $items[$idx]['item_gst'] = $extractedGst;
        $items[$idx]['gst_rate'] = $rate;
        $itemGstAmt = $extractedGst;
    }
    $lineGst += $itemGstAmt;

    $itemSellerState = trim((string)($it['company_state'] ?? ''));
    $itemIsIntrastate = ($itemSellerState !== '' && $deliveryStateForGst !== '' && strcasecmp($itemSellerState, $deliveryStateForGst) === 0);
    if ($itemIsIntrastate) {
        $itemCgst = round($itemGstAmt / 2, 2);
        $itemSgst = round($itemGstAmt - $itemCgst, 2);
        $items[$idx]['item_cgst'] = $itemCgst;
        $items[$idx]['item_sgst'] = $itemSgst;
        $items[$idx]['item_igst'] = 0.0;
        $lineCgst += $itemCgst;
        $lineSgst += $itemSgst;
    } else {
        $items[$idx]['item_cgst'] = 0.0;
        $items[$idx]['item_sgst'] = 0.0;
        $items[$idx]['item_igst'] = $itemGstAmt;
        $lineIgst += $itemGstAmt;
    }
}
$grossSubtotal = is_numeric($order['subtotal'] ?? null) ? (float)$order['subtotal'] : $lineSubtotal;
$discount   = is_numeric($order['product_discount'] ?? null) ? (float)$order['product_discount'] : $lineDiscount;
$coupon     = is_numeric($order['coupon_discount'] ?? null) ? (float)$order['coupon_discount'] : 0.0;
// This schema tracks one combined `discount_amount` driven by a coupon
// (see place_order.php) rather than separate product/coupon fields —
// when a coupon code is present, show the discount as the coupon line.
if (!empty($order['coupon_code']) && $coupon <= 0 && $discount > 0) {
    $coupon = $discount;
    $discount = 0.0;
}
$delivery   = is_numeric($order['delivery_charge'] ?? null) ? (float)$order['delivery_charge'] : 0.0;
// orders.gst_amount may exist as a real column but still sit at its
// 0.00 default (not populated at order time) — in that case fall back
// to the sum of the item-level GST we just computed above, so the
// summary row always shows a real figure instead of a stale zero.
$orderGstCol = (float)($order['gst_amount'] ?? 0);
$gst        = $orderGstCol > 0 ? $orderGstCol : $lineGst;
// Keep the CGST/SGST/IGST split proportional to whichever total ended up
// being used above (the real orders.gst_amount column takes priority over
// our extracted-from-items figure when it's populated).
if ($orderGstCol > 0 && $lineGst > 0 && abs($orderGstCol - $lineGst) > 0.01) {
    $gstScale = $orderGstCol / $lineGst;
    $cgst = round($lineCgst * $gstScale, 2);
    $sgst = round($lineSgst * $gstScale, 2);
    $igst = round($gst - $cgst - $sgst, 2); // absorb rounding into IGST
} else {
    $cgst = round($lineCgst, 2);
    $sgst = round($lineSgst, 2);
    $igst = round($gst - $cgst - $sgst, 2);
}
$roundOff   = is_numeric($order['round_off'] ?? null) ? (float)$order['round_off'] : 0.0;
// Prices are GST-inclusive, so the product prices already contain the tax.
// Show "Product Subtotal" as the NET (pre-tax) figure — gross minus the
// extracted GST — so Subtotal + GST visibly adds up to the Grand Total,
// instead of looking like GST was left out of the sum.
$subtotal = $gstIsInclusive ? round($grossSubtotal - $gst, 2) : $grossSubtotal;
$computedGrand = round($subtotal - $discount - $coupon + $delivery + $gst + $roundOff, 2);
$grandTotal = is_numeric($order['grand_total'] ?? null) ? (float)$order['grand_total'] : $computedGrand;

// Payment method / status — resolved BEFORE amountPaid, so a COD order that
// has been delivered is correctly reflected as paid everywhere (badge,
// payment info block, AND the amount-paid/remaining figures), even if the
// payment_status column was never separately updated on delivery.
$rawPaymentMethod = trim((string)($order['payment_method'] ?? ''));
$paymentMethodMap = ['cod' => 'Cash on Delivery', 'online' => 'Online Payment', 'upi' => 'UPI', 'card' => 'Card', 'netbanking' => 'Net Banking'];
$paymentMethod = $paymentMethodMap[strtolower($rawPaymentMethod)] ?? ($rawPaymentMethod ?: 'Cash on Delivery');
$isCod = (strtolower($rawPaymentMethod) === 'cod' || strtolower($paymentMethod) === 'cash on delivery');
$isDelivered = strtolower((string)($order['order_status'] ?? '')) === 'delivered';

// Different payment gateways / older data entry can leave payment_status
// as things other than the exact word "paid" — e.g. UPI/netbanking
// gateways commonly write "success", "successful", "completed",
// "captured", "settled", "confirmed", "done" instead. Treat all of these
// as paid, otherwise a genuinely-paid UPI/netbanking order shows up as
// unpaid just because the stored word doesn't literally match "paid".
function agi_is_paid_status(string $status): bool {
    $paidWords = ['paid', 'success', 'successful', 'completed', 'complete',
                  'captured', 'settled', 'confirmed', 'done', 'received'];
    return in_array($status, $paidWords, true);
}

$rawPaymentStatus = strtolower(trim((string)($order['payment_status'] ?? '')));
if ($isCod) {
    // COD cash is only actually collected on delivery. Once the order is
    // marked delivered, treat payment as paid regardless of whatever stale
    // value is (or isn't) sitting in payment_status.
    $resolvedPaymentStatus = $isDelivered ? 'paid' : ($rawPaymentStatus !== '' ? $rawPaymentStatus : 'pending');
} elseif ($rawPaymentStatus !== '') {
    // Online modes (UPI / netbanking / card): normalise whatever the
    // gateway/DB actually stored to a clean "paid" if it means paid,
    // otherwise keep the real value (e.g. "failed", "pending") as-is.
    $resolvedPaymentStatus = agi_is_paid_status($rawPaymentStatus) ? 'paid' : $rawPaymentStatus;
} else {
    // No payment_status column/value at all for an online-mode order —
    // assume paid, since online orders are only created after the
    // gateway confirms payment.
    $resolvedPaymentStatus = 'paid';
}
$paymentStatus = ucwords(str_replace('_', ' ', $resolvedPaymentStatus));
$orderStatus = $order['order_status'] ? ucwords(str_replace('_', ' ', $order['order_status'])) : 'Confirmed';

$amountPaid = ($resolvedPaymentStatus === 'paid')
    ? $grandTotal
    : (is_numeric($order['amount_paid'] ?? null) ? (float)$order['amount_paid'] : 0.0);
$remaining  = max(0.0, round($grandTotal - $amountPaid, 2));

// Invoice / order display numbers
$invoiceNo = 'AGC-INV-' . date('Y', strtotime($order['created_at'] ?: 'now')) . '-' . str_pad((string)$orderId, 4, '0', STR_PAD_LEFT);
$orderCode = 'AGC-ORD-' . $orderId;
$invoiceDate = date('d F Y');
$orderDate = date('d F Y', strtotime($order['created_at'] ?: 'now'));

$deliveryAddressParts = array_filter([
    $order['delivery_address'] ?? '', $order['delivery_village'] ?? '',
]);
$deliveryLine1 = trim(implode(', ', $deliveryAddressParts));
if ($deliveryLine1 === '' && !empty($order['delivery_notes'])) {
    // notes looks like: "Delivery: name, mobile, PIN xxxxx\naddress text"
    $deliveryLine1 = trim(preg_replace('/^Delivery:\s*[^\n]*\n?/i', '', (string)$order['delivery_notes']));
}
$deliveryLine1 = $deliveryLine1 ?: 'Address on file with AgriCart';
$deliveryCityState = trim(implode(', ', array_filter([$order['delivery_city'] ?? '', $order['delivery_state'] ?? ''])));
$deliveryPin = $order['delivery_pin'] ?? '';

$isTaxInvoice = !empty($sellerCard['seller_gstin'] ?? null);
if (!$isTaxInvoice && $multiSellerCards) {
    foreach ($multiSellerCards as $msc) {
        if (!empty($msc['seller_gstin'])) { $isTaxInvoice = true; break; }
    }
}
if (!$isTaxInvoice && !empty($fallbackVendors)) {
    foreach ($fallbackVendors as $fv) {
        if (!empty($fv['gstin'])) { $isTaxInvoice = true; break; }
    }
}

function agi_e($v, $fallback = '—') {
    $v = trim((string)($v ?? ''));
    return htmlspecialchars($v !== '' ? $v : $fallback);
}
function agi_money($n) {
    return '&#8377;' . number_format((float)$n, 2);
}

// Mirrors resolveProductImage() in pages/marketplace.php so invoice photos
// match the storefront exactly: use the product's own image if it has one,
// else guess by name keyword, else fall back to a category photo.
function agi_resolve_product_image(array $item, string $base_path): string {
    $image = $item['image'] ?? '';
    if (!empty($image)) {
        if (preg_match('#^(https?:)?//#i', $image) || strpos($image, '/') === 0) {
            return $image;
        }
        return rtrim($base_path, '/') . '/' . ltrim($image, '/');
    }
    $keywordMap = [
        'tomato seed' => 'tomato-seeds.png', 'onion seed' => 'onion-seeds.png',
        'urea' => 'urea-fertilizer.png', 'dap' => 'dap-fertilizer.png',
        'neem oil' => 'neem-oil-pesticide.png', 'sprayer' => 'knapsack-sprayer.png',
        'drip irrigation' => 'drip-irrigation-pipe.png', 'fresh tomato' => 'fresh-tomatoes.png',
        'jaggery' => 'organic-jaggery.png', 'gul' => 'organic-jaggery.png',
    ];
    $categoryFallback = [
        'seeds' => 'assets/images/products/seeds.jpg', 'fertilizer' => 'assets/images/products/fertilizer.jpg',
        'pesticides' => 'assets/images/products/pesticides.jpg', 'tools' => 'assets/images/products/tools.jpg',
        'irrigation' => 'assets/images/products/irrigation.jpg', 'feed' => 'assets/images/products/feed.jpg',
        'organic' => 'assets/images/products/organic.jpg', 'cropkits' => 'assets/images/products/cropkits.jpg',
    ];
    $haystack = strtolower(($item['product_name'] ?? '') . ' ' . ($item['name_mr'] ?? ''));
    foreach ($keywordMap as $needle => $file) {
        if (strpos($haystack, $needle) !== false) {
            return rtrim($base_path, '/') . '/assets/images/products/' . $file;
        }
    }
    $fallback = $categoryFallback[strtolower((string)($item['category'] ?? ''))] ?? 'assets/images/products/default.jpg';
    return rtrim($base_path, '/') . '/' . $fallback;
}
?>
<?php include __DIR__ . '/../includes/header.php'; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<style>
/* ===================== AgriCart Invoice — scoped "agi-" styles ===================== */
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

/* ---------- Toolbar ---------- */
.agi-toolbar{max-width:900px;margin:0 auto 16px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;}
.agi-toolbar-actions{display:flex;gap:10px;flex-wrap:wrap;}
.agi-btn{display:inline-flex;align-items:center;gap:8px;padding:10px 18px;border-radius:9px;border:1.5px solid var(--agi-border);
    background:var(--agi-white);color:var(--agi-text);font-weight:600;font-size:13.5px;cursor:pointer;text-decoration:none;
    font-family:inherit;transition:background .15s ease, transform .1s ease, box-shadow .15s ease;}
.agi-btn:hover{transform:translateY(-1px);box-shadow:0 4px 12px rgba(17,43,17,.1);}
.agi-btn-green{background:var(--agi-green);border-color:var(--agi-green);color:#fff;}
.agi-btn-outline{background:var(--agi-white);border-color:var(--agi-dark-green);color:var(--agi-dark-green);}

/* ---------- Invoice sheet (A4) ---------- */
.agi-sheet{max-width:900px;margin:0 auto;background:var(--agi-white);border:1px solid var(--agi-border);
    border-radius:var(--agi-radius);box-shadow:var(--agi-shadow);padding:40px 44px;}
.agi-header-row{display:flex;justify-content:space-between;align-items:flex-start;gap:20px;border-bottom:2px solid var(--agi-light-green);padding-bottom:22px;margin-bottom:24px;flex-wrap:wrap;}
.agi-brand{display:flex;align-items:center;gap:12px;}
.agi-brand-logo-img{height:52px;width:52px;object-fit:contain;border-radius:50%;flex-shrink:0;}
.agi-brand-name{font-family:'Poppins',sans-serif;font-size:26px;font-weight:800;letter-spacing:-0.4px;line-height:1.1;}
.agi-brand-name .agi-agri{color:#0b1a14;}
.agi-brand-name .agi-cart{color:#5A9802;margin-left:1px;}
.agi-tagline{font-size:11.5px;color:var(--agi-muted);letter-spacing:.4px;margin-top:2px;font-weight:600;}
.agi-invoice-meta{text-align:right;}
.agi-invoice-meta h1{font-family:'Poppins',sans-serif;font-size:18px;font-weight:800;color:var(--agi-dark-green);letter-spacing:.5px;margin:0 0 8px;}
.agi-meta-row{font-size:12.5px;color:var(--agi-text);margin-bottom:3px;}
.agi-meta-row b{color:var(--agi-muted);font-weight:600;margin-right:4px;}
.agi-badges{margin-top:8px;display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap;}
.agi-badge{display:inline-block;padding:4px 11px;border-radius:20px;font-size:11px;font-weight:700;letter-spacing:.2px;}
.agi-badge-success{background:var(--agi-light-green);color:var(--agi-green);}
.agi-badge-pending{background:var(--agi-amber-bg);color:var(--agi-amber);}
.agi-badge-danger{background:var(--agi-red-bg);color:var(--agi-red);}

/* ---------- Party cards ---------- */
.agi-parties{display:flex;gap:18px;margin-bottom:26px;}
.agi-card{flex:1;border:1px solid var(--agi-border);border-radius:10px;padding:16px 18px;background:#FAFBFA;}
.agi-card h3{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--agi-green);font-weight:700;margin:0 0 10px;}
.agi-card p{margin:0 0 4px;font-size:13px;line-height:1.55;}
.agi-card p b{font-weight:600;}
.agi-card .agi-muted-line{color:var(--agi-muted);font-size:12px;}

/* ---------- Product table ---------- */
.agi-table-wrap{overflow-x:auto;margin-bottom:22px;border:1px solid var(--agi-border);border-radius:10px;}
table.agi-table{width:100%;border-collapse:collapse;min-width:760px;font-size:12.5px;}
table.agi-table thead th{background:var(--agi-dark-green);color:#fff;text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;white-space:nowrap;}
table.agi-table tbody td{padding:10px 12px;border-top:1px solid var(--agi-border);vertical-align:top;}
table.agi-table tbody tr:nth-child(even){background:#FAFBFA;}
.agi-prod-img{width:44px;height:44px;border-radius:8px;object-fit:cover;border:1px solid var(--agi-border);background:#F3F5F2;}
.agi-prod-name{font-weight:600;}
.agi-prod-sub{color:var(--agi-muted);font-size:11px;margin-top:2px;}
.agi-num{text-align:right;white-space:nowrap;}

/* ---------- Summary ---------- */
.agi-summary-wrap{display:flex;justify-content:flex-end;margin-bottom:26px;}
.agi-summary{width:320px;border:1px solid var(--agi-border);border-radius:10px;overflow:hidden;}
.agi-summary-row{display:flex;justify-content:space-between;padding:9px 16px;font-size:13px;border-bottom:1px solid var(--agi-border);}
.agi-subrow{padding-top:4px;padding-bottom:4px;font-size:11.5px;color:var(--agi-muted);padding-left:28px;}
.agi-summary-row span:first-child{color:var(--agi-muted);}
.agi-summary-row.agi-grand{background:var(--agi-light-green);font-weight:800;font-size:15.5px;color:var(--agi-dark-green);border-bottom:none;padding:14px 16px;}
.agi-summary-row.agi-remaining span:last-child{color:var(--agi-red);font-weight:700;}

/* ---------- Payment info ---------- */
.agi-payment-info{border:1px solid var(--agi-border);border-radius:10px;padding:16px 18px;margin-bottom:26px;background:#FAFBFA;}
.agi-payment-info h3{font-size:12px;text-transform:uppercase;letter-spacing:.6px;color:var(--agi-green);font-weight:700;margin:0 0 10px;}
.agi-payment-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;}
.agi-payment-grid div span{display:block;font-size:11px;color:var(--agi-muted);margin-bottom:2px;}
.agi-payment-grid div b{font-size:13px;}

/* ---------- Notes / support / signature ---------- */
.agi-lower{display:flex;gap:18px;margin-bottom:22px;flex-wrap:wrap;}
.agi-lower .agi-card{flex:1;min-width:220px;}
.agi-lower ul{margin:0;padding-left:18px;font-size:12px;color:var(--agi-muted);line-height:1.7;}
.agi-sign-box{border:1px dashed var(--agi-border);border-radius:8px;height:66px;display:flex;align-items:center;justify-content:center;color:var(--agi-muted);font-size:11px;margin:10px 0 8px;overflow:hidden;padding:4px;}
.agi-sign-box.agi-has-asset{border-style:solid;background:#fff;}
.agi-sign-assets{display:flex;gap:10px;align-items:center;justify-content:center;width:100%;height:100%;}
.agi-sign-assets img{max-height:56px;max-width:48%;object-fit:contain;}
.agi-signatory-name{font-size:11.5px;font-weight:600;margin-top:2px;}
.agi-signatory-designation{font-size:10.5px;color:var(--agi-muted);}
.agi-signatory-block + .agi-signatory-block{margin-top:14px;padding-top:14px;border-top:1px dashed var(--agi-border);}

/* ---------- Footer ---------- */
.agi-footer{text-align:center;border-top:1px solid var(--agi-border);padding-top:18px;}
.agi-footer .agi-thanks{font-family:'Poppins',sans-serif;font-weight:700;color:var(--agi-dark-green);font-size:14px;margin-bottom:4px;}
.agi-footer .agi-computer-gen{font-size:11px;color:var(--agi-muted);margin-bottom:10px;}
.agi-footer-links{font-size:11.5px;}
.agi-footer-links a{color:var(--agi-green);text-decoration:none;margin:0 6px;font-weight:600;}
.agi-footer-links a:hover{text-decoration:underline;}

/* ---------- Responsive ---------- */
@media (max-width: 720px){
    .agi-sheet{padding:22px 16px;}
    .agi-parties{flex-direction:column;}
    .agi-header-row{flex-direction:column;}
    .agi-invoice-meta{text-align:left;width:100%;}
    .agi-badges{justify-content:flex-start;}
    .agi-payment-grid{grid-template-columns:repeat(2,1fr);}
    .agi-lower{flex-direction:column;}
    .agi-summary{width:100%;}
}

/* ---------- Print ---------- */
@media print{
    body *{visibility:hidden;}
    .agi-print-area, .agi-print-area *{visibility:visible;}
    .agi-print-area{position:absolute;left:0;top:0;width:100%;margin:0;box-shadow:none;border:none;padding:14mm;}
    .agi-toolbar, .agi-no-print{display:none !important;}
    @page{size:A4;margin:0;}
    table.agi-table{font-size:11px;}
    .agi-summary, .agi-payment-info, .agi-lower .agi-card, .agi-table-wrap{break-inside:avoid;}
}
</style>

<div class="agi-page">
  <div class="agi-toolbar agi-no-print" style="justify-content:flex-end;">
    <div class="agi-toolbar-actions">
      <a class="agi-btn agi-btn-outline" href="marketplace.php"><i class="fa-solid fa-arrow-left"></i> <span data-agi="backToOrders">Back to Orders</span></a>
      <button class="agi-btn agi-btn-outline" id="agiPrintBtn"><i class="fa-solid fa-print"></i> <span data-agi="printInvoice">Print Invoice</span></button>
      <button class="agi-btn agi-btn-green" id="agiPdfBtn"><i class="fa-solid fa-file-arrow-down"></i> <span data-agi="downloadPdf">Download PDF</span></button>
    </div>
  </div>

  <div class="agi-sheet agi-print-area" id="agiInvoiceSheet">

    <!-- Header -->
    <div class="agi-header-row">
      <div class="agi-brand">
        <img class="agi-brand-logo-img" src="../assets/images/agricart-logo.png?v=<?php echo @filemtime(__DIR__ . '/../assets/images/agricart-logo.png') ?: time(); ?>" alt="AgriCart">
        <div>
          <div class="agi-brand-name"><span class="agi-agri">Agri</span><span class="agi-cart">Cart</span></div>
          <div class="agi-tagline">Fresh &bull; Trusted &bull; Direct</div>
        </div>
      </div>
      <div class="agi-invoice-meta">
        <h1 data-agi="<?php echo $isTaxInvoice ? 'taxInvoice' : 'salesInvoice'; ?>">
          <?php echo $isTaxInvoice ? 'TAX INVOICE' : 'PRODUCT INVOICE'; ?>
        </h1>
        <div class="agi-meta-row"><b data-agi="invoiceNumber">Invoice No:</b><?php echo agi_e($invoiceNo); ?></div>
        <div class="agi-meta-row"><b data-agi="orderId">Order ID:</b><?php echo agi_e($orderCode); ?></div>
        <div class="agi-meta-row"><b data-agi="invoiceDate">Invoice Date:</b><?php echo agi_e($invoiceDate); ?></div>
        <div class="agi-meta-row"><b data-agi="orderDate">Order Date:</b><?php echo agi_e($orderDate); ?></div>
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

    <!-- Bill To / Sold By -->
    <div class="agi-parties">
      <div class="agi-card">
        <h3 data-agi="billTo">Bill To</h3>
        <p><b><?php echo agi_e($order['buyer_name'], 'Customer'); ?></b></p>
        <p><?php echo agi_e($order['buyer_mobile']); ?></p>
        <p><?php echo agi_e($order['buyer_email']); ?></p>
        <p class="agi-muted-line" data-agi="deliveryAddrLabel">Delivery Address</p>
        <p><?php echo agi_e($deliveryLine1); ?></p>
        <p><?php echo agi_e($deliveryCityState); ?><?php echo $deliveryPin ? ' — ' . agi_e($deliveryPin) : ''; ?></p>
      </div>
      <div class="agi-card">
        <h3 data-agi="soldBy">Sold By</h3>
        <?php if ($sellerCard): ?>
          <p><b><?php echo agi_e($sellerCard['business_name'] ?? $sellerCard['seller_name'], 'AgriCart Seller'); ?></b></p>
          <p><?php echo agi_e($sellerCard['seller_mobile']); ?></p>
          <p><?php echo agi_e($sellerCard['seller_email']); ?></p>
          <p><?php echo agi_e(implode(', ', array_filter([$sellerCard['seller_village'] ?? '', $sellerCard['seller_taluka'] ?? '', $sellerCard['seller_district'] ?? ''])), 'Address on file'); ?></p>
          <p><?php echo agi_e($sellerCard['seller_state']); ?><?php echo !empty($sellerCard['seller_pin']) ? ' — ' . agi_e($sellerCard['seller_pin']) : ''; ?></p>
          <?php if (!empty($sellerCard['seller_gstin'])): ?>
            <p><b data-agi="gstin">GSTIN:</b> <?php echo agi_e($sellerCard['seller_gstin']); ?></p>
          <?php endif; ?>
          <p class="agi-muted-line"><span data-agi="sellerId">Seller ID:</span> #<?php echo (int)$sellerCard['id']; ?></p>
        <?php elseif ($multiSellerCards): ?>
          <?php foreach ($multiSellerCards as $i => $msc): ?>
            <p<?php echo $i > 0 ? ' style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--agi-border);"' : ''; ?>>
              <b><?php echo agi_e($msc['business_name'] ?? $msc['seller_name'], 'AgriCart Seller'); ?></b>
              <?php if (!empty($msc['seller_gstin'])): ?>
                <br><span class="agi-muted-line" data-agi="gstin">GSTIN:</span> <?php echo agi_e($msc['seller_gstin']); ?>
              <?php endif; ?>
            </p>
          <?php endforeach; ?>
          <p class="agi-muted-line" data-agi="seePerItem">See the seller listed against each product below.</p>
        <?php elseif ($fallbackSellerNames): ?>
          <?php foreach ($fallbackSellerNames as $i => $sn): $fv = $fallbackVendors[$sn] ?? null; ?>
            <p<?php echo $i > 0 ? ' style="margin-top:10px;padding-top:10px;border-top:1px dashed var(--agi-border);"' : ''; ?>>
              <b><?php echo agi_e($sn); ?></b>
              <?php if (!empty($fv['address']) || !empty($fv['state'])): ?>
                <br><span class="agi-muted-line"><?php echo agi_e(implode(', ', array_filter([$fv['address'] ?? '', $fv['state'] ?? '']))); ?><?php echo !empty($fv['pincode']) ? ' — ' . agi_e($fv['pincode']) : ''; ?></span>
              <?php endif; ?>
              <?php if (!empty($fv['gstin'])): ?>
                <br><span class="agi-muted-line" data-agi="gstin">GSTIN:</span> <?php echo agi_e($fv['gstin']); ?>
              <?php endif; ?>
            </p>
          <?php endforeach; ?>
          <p class="agi-muted-line" data-agi="seePerItem">See the seller listed against each product below.</p>
        <?php else: ?>
          <p><b data-agi="multipleSellers">AgriCart Marketplace (multiple sellers)</b></p>
          <p class="agi-muted-line" data-agi="seePerItem">See the seller listed against each product below.</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Product table -->
    <div class="agi-table-wrap">
      <table class="agi-table">
        <thead>
          <tr>
            <th data-agi="thSr">Sr.</th>
            <th data-agi="thImage">Image</th>
            <th data-agi="thProduct">Product</th>
            <th data-agi="thCategory">Category</th>
            <th data-agi="thQty">Qty</th>
            <th data-agi="thUnitPrice">Unit Price</th>
            <th data-agi="thDiscount">Discount</th>
            <th data-agi="thGst">GST/Tax</th>
            <th data-agi="thTotal">Total</th>
          </tr>
        </thead>
        <tbody>
          <?php $sr = 1; foreach ($items as $it):
              $qty = (float)$it['qty']; $price = (float)$it['price'];
              $itemDiscount = (float)($it['item_discount'] ?? 0);
              $itemGstAmt = (float)($it['item_gst'] ?? 0);
              // Inclusive mode: GST is a breakdown of the price, already
              // counted in qty*price, so it must not be added again.
              $lineTotal = $gstIsInclusive
                  ? round(($qty * $price) - $itemDiscount, 2)
                  : round(($qty * $price) - $itemDiscount + $itemGstAmt, 2);
          ?>
          <tr>
            <td><?php echo $sr++; ?></td>
            <td>
              <?php $agiImgUrl = agi_resolve_product_image($it, $base_path ?? ''); ?>
              <?php if ($agiImgUrl): ?>
                <img class="agi-prod-img" src="<?php echo htmlspecialchars($agiImgUrl); ?>" alt="" onerror="this.style.display='none'">
              <?php else: ?>
                <div class="agi-prod-img" style="display:flex;align-items:center;justify-content:center;color:var(--agi-muted);"><i class="fa-solid fa-seedling"></i></div>
              <?php endif; ?>
            </td>
            <td>
              <div class="agi-prod-name" data-agi-prod-name
                   data-name-en="<?php echo htmlspecialchars($it['product_name'] ?: 'Product'); ?>"
                   data-name-mr="<?php echo htmlspecialchars($it['name_mr'] ?: ''); ?>"
                   data-name-hi="<?php echo htmlspecialchars($it['name_hi'] ?: ''); ?>"
              ><?php echo agi_e(($it['name_mr'] ?? null) ?: $it['product_name'], 'Product'); ?></div>
              <div class="agi-prod-sub">
                <?php echo $it['sku'] ? 'SKU: ' . agi_e($it['sku']) . ' &bull; ' : ''; ?>
                <?php echo $it['unit'] ? agi_e($it['unit']) . ' &bull; ' : ''; ?>
                <span data-agi="soldByShort">Sold by</span>: <?php echo agi_e(trim((string)($it['seller_name'] ?? '')) !== '' ? $it['seller_name'] : ($it['company_name'] ?? null), 'AgriCart Seller'); ?>
              </div>
            </td>
            <td><?php echo agi_e($it['category'], 'General'); ?></td>
            <td><?php echo rtrim(rtrim(number_format($qty, 2), '0'), '.'); ?></td>
            <td class="agi-num"><?php echo agi_money($price); ?></td>
            <td class="agi-num"><?php echo $itemDiscount > 0 ? '&minus;' . agi_money($itemDiscount) : agi_money(0); ?></td>
            <td class="agi-num"><?php echo agi_money($itemGstAmt); ?><?php echo ($gstIsInclusive && !empty($it['gst_rate'])) ? '<div class="agi-prod-sub">' . rtrim(rtrim(number_format($it['gst_rate'] * 100, 2), '0'), '.') . '% incl.</div>' : ''; ?></td>
            <td class="agi-num"><b><?php echo agi_money($lineTotal); ?></b></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Payment summary -->
    <div class="agi-summary-wrap">
      <div class="agi-summary">
        <div class="agi-summary-row"><span data-agi="subtotal">Product Subtotal</span><span><?php echo agi_money($subtotal); ?></span></div>
        <?php if ($discount > 0): ?>
        <div class="agi-summary-row"><span data-agi="productDiscount">Product Discount</span><span>&minus;<?php echo agi_money($discount); ?></span></div>
        <?php endif; ?>
        <?php if ($coupon > 0): ?>
        <div class="agi-summary-row"><span data-agi="couponDiscount"><?php echo $order['coupon_code'] ? 'Coupon Discount (' . agi_e($order['coupon_code']) . ')' : 'Coupon Discount'; ?></span><span>&minus;<?php echo agi_money($coupon); ?></span></div>
        <?php endif; ?>
        <div class="agi-summary-row"><span data-agi="deliveryCharges">Delivery Charges</span><span><?php echo $delivery > 0 ? agi_money($delivery) : '<span data-agi=\'free\'>Free</span>'; ?></span></div>
        <div class="agi-summary-row"><span data-agi="gstTax">GST/Tax</span><span><?php echo agi_money($gst); ?></span></div>
        <?php if ($cgst > 0 || $sgst > 0): ?>
        <div class="agi-summary-row agi-subrow"><span>CGST</span><span><?php echo agi_money($cgst); ?></span></div>
        <div class="agi-summary-row agi-subrow"><span>SGST</span><span><?php echo agi_money($sgst); ?></span></div>
        <?php endif; ?>
        <?php if ($igst > 0): ?>
        <div class="agi-summary-row agi-subrow"><span>IGST</span><span><?php echo agi_money($igst); ?></span></div>
        <?php endif; ?>
        <?php if (abs($roundOff) > 0.001): ?>
        <div class="agi-summary-row"><span data-agi="roundOff">Round Off</span><span><?php echo agi_money($roundOff); ?></span></div>
        <?php endif; ?>
        <div class="agi-summary-row agi-grand"><span data-agi="grandTotal">Grand Total</span><span><?php echo agi_money($grandTotal); ?></span></div>
        <div class="agi-summary-row"><span data-agi="amountPaid">Amount Paid</span><span><?php echo agi_money($amountPaid); ?></span></div>
        <?php if ($remaining > 0): ?>
        <div class="agi-summary-row agi-remaining"><span data-agi="remaining">Remaining Amount</span><span><?php echo agi_money($remaining); ?></span></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Payment info -->
    <div class="agi-payment-info">
      <h3 data-agi="paymentInfo">Payment Information</h3>
      <div class="agi-payment-grid">
        <div><span data-agi="paymentMethod">Payment Method</span><b><?php echo agi_e($paymentMethod); ?></b></div>
        <div><span data-agi="transactionId">Transaction ID</span><b><?php echo agi_e($order['txn_id'], strtoupper($paymentMethod) === 'COD' || strtolower($paymentMethod) === 'cash on delivery' ? '—' : 'N/A'); ?></b></div>
        <div><span data-agi="paymentDate">Payment Date</span><b><?php echo $order['payment_date'] ? agi_e(date('d M Y', strtotime($order['payment_date']))) : agi_e($invoiceDate); ?></b></div>
        <div><span data-agi="paymentStatusLbl">Payment Status</span><b><?php echo agi_e($paymentStatus); ?></b></div>
      </div>
    </div>

    <!-- Notes / Support / Signature -->
    <div class="agi-lower">
      <div class="agi-card">
        <h3 data-agi="notes">Notes</h3>
        <ul>
          <li data-agi="noteReturn">Products are subject to AgriCart's return and refund policy.</li>
          <li data-agi="noteKeep">Keep this invoice for returns, warranty and customer support.</li>
          <li data-agi="noteVary">Product colours or packaging may vary slightly.</li>
        </ul>
      </div>
      <div class="agi-card">
        <h3 data-agi="customerSupport">Customer Support</h3>
        <p><i class="fa-solid fa-phone"></i> <?php echo agi_e($agiContact['phone']); ?></p>
        <p><i class="fa-solid fa-envelope"></i> <?php echo agi_e($agiContact['email']); ?></p>
        <p><i class="fa-solid fa-globe"></i> <?php echo agi_e($agiContact['website']); ?></p>
        <p class="agi-muted-line" data-agi="supportHours">Mon–Sat, 9:00 AM – 7:00 PM</p>
      </div>
      <div class="agi-card">
        <h3 data-agi="authorizedSignatory">Authorized Signatory</h3>
        <?php if ($agiSignatoryBlocks): foreach ($agiSignatoryBlocks as $agiBlock): $agiHasSig = !empty($agiBlock['signature_path']); $agiHasStamp = !empty($agiBlock['stamp_path']); ?>
          <div class="agi-signatory-block">
            <div class="agi-sign-box<?php echo ($agiHasSig || $agiHasStamp) ? ' agi-has-asset' : ''; ?>">
              <?php if ($agiHasSig || $agiHasStamp): ?>
                <div class="agi-sign-assets">
                  <?php if ($agiHasSig): ?><img src="../<?php echo htmlspecialchars($agiBlock['signature_path']); ?>" alt="Signature"><?php endif; ?>
                  <?php if ($agiHasStamp): ?><img src="../<?php echo htmlspecialchars($agiBlock['stamp_path']); ?>" alt="Stamp"><?php endif; ?>
                </div>
              <?php else: ?>
                Digital Signature / Seller Stamp
              <?php endif; ?>
            </div>
            <?php if (!empty($agiBlock['signatory_name'])): ?>
              <p class="agi-signatory-name"><?php echo agi_e($agiBlock['signatory_name']); ?></p>
            <?php endif; ?>
            <?php if (!empty($agiBlock['designation'])): ?>
              <p class="agi-signatory-designation"><?php echo agi_e($agiBlock['designation']); ?></p>
            <?php endif; ?>
            <p class="agi-muted-line" data-agi="forAgricart">For <?php echo agi_e($agiBlock['for_name'], 'Seller'); ?></p>
          </div>
        <?php endforeach; else: ?>
          <div class="agi-sign-box" data-agi="signStampArea">Digital Signature / Seller Stamp</div>
          <p class="agi-muted-line" data-agi="forAgricart">For AgriCart Marketplace</p>
        <?php endif; ?>
      </div>
    </div>

    <!-- Footer -->
    <div class="agi-footer">
      <div class="agi-thanks" data-agi="thankYou">Thank you for shopping with AgriCart.</div>
      <div class="agi-computer-gen" data-agi="computerGenerated">This is a computer-generated invoice and does not require a physical signature.</div>
      <div class="agi-footer-links">
        <a href="return-policy.php" data-agi="returnPolicy">Return Policy</a>|
        <a href="refund-policy.php" data-agi="refundPolicy">Refund Policy</a>|
        <a href="terms.php" data-agi="terms">Terms and Conditions</a>|
        <a href="privacy-policy.php" data-agi="privacy">Privacy Policy</a>
      </div>
    </div>

  </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
  const AGI_INVOICE_NO = <?php echo json_encode($invoiceNo); ?>;
  const AGI_DEFAULT_LANG = <?php echo json_encode($_SESSION['lang'] ?? 'en'); ?>;
</script>
<script src="../assets/js/invoice.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/invoice.js') ?: time(); ?>"></script>
<?php include __DIR__ . '/../includes/footer.php'; ?>
