<?php
// =====================================================================
// admin/index.php — AgriCart Admin Dashboard
// Full site management: products, orders, sellers, reviews.
// Guarded by admin_guard.php — only reachable after a verified admin login.
// =====================================================================
require __DIR__ . '/includes/admin_guard.php';
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
agri_connect_bootstrap_schema($conn);
require_once __DIR__ . '/includes/cod_payment_sync.php';

// ---- Safety-net: correct any pre-existing COD orders that are
// Delivered but were never flipped to Paid (e.g. delivered before this
// sync existed, or changed outside the normal admin flow). Idempotent
// and scoped to Delivered+COD+unsettled rows only — see
// includes/cod_payment_sync.php for the exact matching rules. ----
agri_order_backfill_cod_payment_status($conn);

// ---- Dashboard stats ----
$totalProducts = 0; $totalStockValue = 0;
$pr = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE is_active = 1");
if ($pr) { $totalProducts = (int)$pr->fetch_assoc()['cnt']; }

$totalOrders = 0; $pendingOrders = 0; $revenue = 0;
try {
    $or = $conn->query("SELECT COUNT(*) AS cnt FROM orders");
    if ($or) { $totalOrders = (int)$or->fetch_assoc()['cnt']; }
    $pend = $conn->query("SELECT COUNT(*) AS cnt FROM orders WHERE order_status NOT IN ('delivered','cancelled')");
    if ($pend) { $pendingOrders = (int)$pend->fetch_assoc()['cnt']; }
    $rev = $conn->query("SELECT SUM(COALESCE(final_amount, total_amount)) AS s FROM orders WHERE order_status != 'cancelled'");
    if ($rev) { $revenue = (float)($rev->fetch_assoc()['s'] ?? 0); }
} catch (\Throwable $e) {
    // orders table/columns don't match this schema yet — dashboard still loads, stats just show 0.
}

$totalSellers = 0;
$sl = $conn->query("SELECT COUNT(DISTINCT farmer_name) AS cnt FROM products WHERE is_active = 1");
if ($sl) { $totalSellers = (int)$sl->fetch_assoc()['cnt']; }

// ---- Pending approvals (farmer-submitted products/equipment awaiting review) ----
// If setup/farmer_selling_upgrade.sql hasn't been run yet, approval_status
// won't exist on these tables — in that case just show 0 instead of erroring,
// and $approvalColsMissing flags it so we can warn the admin in the UI.
$pendingProductsCount = 0; $pendingEquipmentCount = 0; $approvalColsMissing = false;
try {
    $ppr = $conn->query("SELECT COUNT(*) AS cnt FROM products WHERE approval_status = 'pending'");
    if ($ppr) { $pendingProductsCount = (int)$ppr->fetch_assoc()['cnt']; }
} catch (\Throwable $e) { $approvalColsMissing = true; }
try {
    $per = $conn->query("SELECT COUNT(*) AS cnt FROM equipment WHERE approval_status = 'pending'");
    if ($per) { $pendingEquipmentCount = (int)$per->fetch_assoc()['cnt']; }
} catch (\Throwable $e) { $approvalColsMissing = true; }
$pendingApprovalsTotal = $pendingProductsCount + $pendingEquipmentCount;

// ---- Companies count (sellers directory — see companies_schema.php) ----
$totalCompanies = 0;
try {
    $cmp = $conn->query("SELECT COUNT(*) AS cnt FROM sellers");
    if ($cmp) { $totalCompanies = (int)$cmp->fetch_assoc()['cnt']; }
} catch (\Throwable $e) {
    // sellers table not present yet — dashboard still loads, card shows 0.
}

// ---- Invoices count (Seller Invoices generated so far — see seller_invoices.php) ----
$totalInvoices = 0;
try {
    $inv = $conn->query("SELECT COUNT(*) AS cnt FROM seller_invoices");
    if ($inv) { $totalInvoices = (int)$inv->fetch_assoc()['cnt']; }
} catch (\Throwable $e) {
    // seller_invoices table not present yet — dashboard still loads, card shows 0.
}

// ---- Business Overview additions: Platform Commission, Pending Payout, Active Buyers, Low Stock ----
// Every figure below is read live from the same tables the rest of the app already
// writes to (order_items.platform_charge_amount, payouts, users, products) — nothing
// here is a static/fake number, per the spec's "no fake data" requirement.
$platformCommission = 0.0;
try {
    $pc = $conn->query("SELECT SUM(platform_charge_amount) AS s FROM order_items");
    if ($pc) { $platformCommission = (float)($pc->fetch_assoc()['s'] ?? 0); }
} catch (\Throwable $e) {}

$pendingPayoutAmount = 0.0; $pendingPayoutCount = 0;
try {
    $pp = $conn->query("SELECT COUNT(*) c, SUM(amount) s FROM payouts WHERE status IN ('pending','processing')");
    if ($pp) { $row = $pp->fetch_assoc(); $pendingPayoutCount = (int)($row['c'] ?? 0); $pendingPayoutAmount = (float)($row['s'] ?? 0); }
} catch (\Throwable $e) {}

$activeBuyersCount = 0;
try {
    $ab = $conn->query("SELECT COUNT(*) c FROM users WHERE role NOT IN ('admin','seller') AND (status IS NULL OR status = 'active') AND deleted_at IS NULL");
    if ($ab) { $activeBuyersCount = (int)($ab->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {
    // status/deleted_at columns not present on this install yet — fall back to a plain count.
    try { $ab = $conn->query("SELECT COUNT(*) c FROM users WHERE role NOT IN ('admin','seller')"); if ($ab) { $activeBuyersCount = (int)($ab->fetch_assoc()['c'] ?? 0); } } catch (\Throwable $e2) {}
}

// Low stock = still sellable but running out (1–5 left), distinct from the
// existing "Out of Stock" (0 left) card above.
$lowStockCount = 0;
try {
    $ls = $conn->query("SELECT COUNT(*) c FROM products WHERE is_active = 1 AND stock BETWEEN 1 AND 5");
    if ($ls) { $lowStockCount = (int)($ls->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}

// ---- Action Required queue: every count here links straight to the module that
// resolves it (spec §4). Each query is best-effort/non-fatal so a missing table on
// an older install just hides that one item instead of breaking the dashboard. ----
$pendingCompanyVerification = 0;
try {
    $cv = $conn->query("SELECT COUNT(*) c FROM sellers WHERE COALESCE(business_verified,0) = 0");
    if ($cv) { $pendingCompanyVerification = (int)($cv->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}

$pendingGstVerification = 0;
try {
    $gv = $conn->query("SELECT COUNT(*) c FROM gst_verification_requests WHERE status = 'pending'");
    if ($gv) { $pendingGstVerification = (int)($gv->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}

$pendingPaymentVerification = 0;
try {
    $pv = $conn->query("SELECT COUNT(*) c FROM equipment_bookings WHERE payment_status = 'verification_pending'");
    if ($pv) { $pendingPaymentVerification = (int)($pv->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}

$pendingRefundRequests = 0;
try {
    $rr = $conn->query("SELECT COUNT(*) c FROM orders WHERE order_status = 'returned'");
    if ($rr) { $pendingRefundRequests = (int)($rr->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}

$pendingComplaints = 0;
try {
    $cx = $conn->query("SELECT COUNT(*) c FROM contact_messages WHERE status = 'new'");
    if ($cx) { $pendingComplaints = (int)($cx->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}

$failedTransactionsCount = 0;
try {
    $ft = $conn->query("SELECT COUNT(*) c FROM orders WHERE payment_status = 'failed'");
    if ($ft) { $failedTransactionsCount = (int)($ft->fetch_assoc()['c'] ?? 0); }
} catch (\Throwable $e) {}


// ---- Month-over-month trend for the Orders / Revenue stat cards ----
$ordersThisMonth = 0; $ordersLastMonth = 0; $revenueThisMonth = 0; $revenueLastMonth = 0;
try {
    $tm = $conn->query("
        SELECT
          SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN 1 ELSE 0 END) AS ord_this,
          SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m') THEN 1 ELSE 0 END) AS ord_last,
          SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') AND order_status != 'cancelled' THEN total_amount ELSE 0 END) AS rev_this,
          SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m') AND order_status != 'cancelled' THEN total_amount ELSE 0 END) AS rev_last
        FROM orders
    ");
    if ($tm && ($row = $tm->fetch_assoc())) {
        $ordersThisMonth   = (int)($row['ord_this'] ?? 0);
        $ordersLastMonth   = (int)($row['ord_last'] ?? 0);
        $revenueThisMonth  = (float)($row['rev_this'] ?? 0);
        $revenueLastMonth  = (float)($row['rev_last'] ?? 0);
    }
} catch (\Throwable $e) {
    // orders table not ready yet — trend subtext just won't show.
}

// Returns null (hide subtext) when there's nothing meaningful to compare against.
function trendVsLastMonth($thisVal, $lastVal): ?array {
    if ($lastVal <= 0) return null;
    $pct = round((($thisVal - $lastVal) / $lastVal) * 100, 1);
    return ['pct' => $pct, 'up' => $pct >= 0];
}
$ordersTrend  = trendVsLastMonth($ordersThisMonth, $ordersLastMonth);
$revenueTrend = trendVsLastMonth($revenueThisMonth, $revenueLastMonth);
$lastMonthLabel = date('M Y', strtotime('first day of last month'));

// ---- Products list (admin sees everything, including soft-deleted ones,
// so they can be restored; the public site is the one that filters is_active) ----
$products = [];
// Safety cap (spec §19 "avoid loading thousands of records at once") — the
// Products tab's search/filter box works client-side over whatever's
// loaded here, so this can't be turned into a true LIMIT/OFFSET without
// also rewriting that JS to fetch pages via AJAX. A high cap is the safe
// middle ground: zero behavior change for any realistic catalog size,
// but no longer an unbounded full-table load if the catalog grows huge.
$pres = $conn->query("SELECT * FROM products ORDER BY id DESC LIMIT 2000");
if ($pres) { while ($row = $pres->fetch_assoc()) { $products[] = $row; } }

// ---- Out of stock count (active products with stock <= 0) ----
$outOfStockCount = 0;
foreach ($products as $__p) {
    if (!empty($__p['is_active']) && (int)($__p['stock'] ?? 0) <= 0) { $outOfStockCount++; }
}
unset($__p);

// ---- Orders list (best-effort; adjust column names if your schema differs) ----
$orders = [];
try {
    // Try to enrich with the registered account's name/email/mobile (in case
    // it differs from the delivery details typed at checkout). Falls back to
    // a plain query below if the `users` join doesn't work on this schema.
    try {
        $ores = $conn->query("SELECT o.*, u.full_name AS account_name, u.email AS account_email, u.mobile AS account_mobile FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 100");
    } catch (\Throwable $eJoin) {
        $ores = false;
    }
    if (!$ores) {
        $ores = $conn->query("SELECT * FROM orders ORDER BY id DESC LIMIT 100");
    }
    if ($ores) { while ($row = $ores->fetch_assoc()) { $orders[] = $row; } }

    // Attach each order's line items (product name, qty, price + image) so the
    // Orders tab / details popup can show exactly what was bought.
    if (!empty($orders)) {
        $orderIds = array_map(static function ($o) { return (int)$o['id']; }, $orders);
        $idsIn = implode(',', $orderIds);
        $itemsByOrder = [];

        // Try the richer query first (price + product_id so we can look up the image).
        $itemsRes = false;
        try {
            $itemsRes = $conn->query("SELECT oi.order_id, oi.product_id, oi.product_name, oi.quantity, oi.price, oi.item_status, oi.seller_id, u.full_name AS seller_name FROM order_items oi LEFT JOIN users u ON u.id = oi.seller_id WHERE oi.order_id IN ($idsIn)");
        } catch (\Throwable $eItems) {
            $itemsRes = false;
        }
        if (!$itemsRes) {
            $itemsRes = $conn->query("SELECT order_id, product_name, quantity FROM order_items WHERE order_id IN ($idsIn)");
        }
        if ($itemsRes) {
            while ($ir = $itemsRes->fetch_assoc()) {
                $itemsByOrder[(int)$ir['order_id']][] = $ir;
            }
        }

        // Best-effort: fetch current product images for any product_id we have,
        // so item thumbnails can show even though order_items itself has no image column.
        $productImages = [];
        try {
            $prodIds = [];
            foreach ($itemsByOrder as $itemList) {
                foreach ($itemList as $it) {
                    if (!empty($it['product_id'])) { $prodIds[(int)$it['product_id']] = true; }
                }
            }
            if (!empty($prodIds)) {
                $prodIdsIn = implode(',', array_keys($prodIds));
                $imgRes = $conn->query("SELECT id, image FROM products WHERE id IN ($prodIdsIn)");
                if ($imgRes) { while ($ir2 = $imgRes->fetch_assoc()) { $productImages[(int)$ir2['id']] = $ir2['image']; } }
            }
        } catch (\Throwable $eImg) {}

        foreach ($orders as &$ordRef) {
            $itemsForOrder = $itemsByOrder[(int)$ordRef['id']] ?? [];
            foreach ($itemsForOrder as &$itmRef) {
                if (!empty($itmRef['product_id']) && isset($productImages[(int)$itmRef['product_id']])) {
                    $itmRef['image'] = $productImages[(int)$itmRef['product_id']];
                }
            }
            unset($itmRef);
            $ordRef['items'] = $itemsForOrder;
        }
        unset($ordRef);
    }
} catch (\Throwable $e) {
    // orders table structure doesn't match yet — table just shows empty.
}

// ---- Sellers (proper sellers table; falls back to product-derived list if table doesn't exist yet) ----
$sellers = [];
$sellersTableExists = false;
try { $chk = $conn->query("SELECT 1 FROM sellers LIMIT 1"); $sellersTableExists = (bool)$chk; } catch (\Throwable $e) {}

if ($sellersTableExists) {
    // One-time backfill: any farmer_name already used on products but not yet a real seller
    // record gets added automatically, so old sellers don't disappear and become editable too.
    $conn->query("
        INSERT INTO sellers (name)
        SELECT DISTINCT p.farmer_name FROM products p
        WHERE p.farmer_name IS NOT NULL AND p.farmer_name <> ''
          AND p.farmer_name NOT IN (SELECT name FROM sellers)
    ");
    $sres = $conn->query("
        SELECT s.*, (SELECT COUNT(*) FROM products p WHERE p.farmer_name = s.name AND p.is_active = 1) AS product_count
        FROM sellers s ORDER BY s.id DESC
    ");
    if ($sres) { while ($row = $sres->fetch_assoc()) { $sellers[] = $row; } }
} else {
    // sellers table not created yet — fall back to the old derived-from-products list.
    $sres = $conn->query("SELECT farmer_name AS name, COUNT(*) AS product_count FROM products WHERE is_active = 1 GROUP BY farmer_name ORDER BY product_count DESC");
    if ($sres) { while ($row = $sres->fetch_assoc()) { $sellers[] = $row; } }
}

// ---- Coupons (falls back to the hardcoded AGRI15 row if the table doesn't exist yet) ----
$coupons = [];
$couponsTableExists = false;
try {
    $cres = $conn->query("SELECT * FROM coupons ORDER BY id DESC");
    if ($cres) { while ($row = $cres->fetch_assoc()) { $coupons[] = $row; } $couponsTableExists = true; }
} catch (\Throwable $e) {
    $coupons = [['id' => 0, 'code' => 'AGRI15', 'discount_type' => 'percent', 'discount_value' => 15, 'min_order_amount' => 0, 'max_discount_amount' => null, 'usage_limit' => null, 'used_count' => 0, 'active' => 1, 'expiry_date' => null]];
}

// ---- Reviews ----
$reviews = [];
try {
    $rres = $conn->query("
        SELECT r.*, u.full_name AS reviewer_name,
               CASE WHEN r.item_type = 'product' THEN p.name WHEN r.item_type = 'equipment' THEN e.name ELSE NULL END AS item_name
        FROM reviews r
        LEFT JOIN users u ON u.id = r.user_id
        LEFT JOIN products p ON r.item_type = 'product' AND p.id = r.item_id
        LEFT JOIN equipment e ON r.item_type = 'equipment' AND e.id = r.item_id
        ORDER BY r.id DESC LIMIT 100
    ");
    if ($rres) { while ($row = $rres->fetch_assoc()) { $reviews[] = $row; } }
} catch (\Throwable $e) {
    // reviews table structure doesn't match yet — table just shows empty.
}

// ---- Equipment Rental ----
$equipment = [];
try {
    $eqr = $conn->query("SELECT e.*, c.name AS city_name FROM equipment e LEFT JOIN cities c ON c.id = e.city_id ORDER BY e.id DESC");
    if ($eqr) { while ($row = $eqr->fetch_assoc()) { $equipment[] = $row; } }
} catch (\Throwable $e) {}

// ---- Equipment currently booked today (active confirmed/on_the_way booking covering today) ----
// Used to show "Not Available" on equipment that is out on rent right now, even if its
// static availability flag is still set to Yes.
$currentlyBookedEquipmentIds = [];
try {
    $todayStr = date('Y-m-d');
    $activeStmt = $conn->prepare("SELECT DISTINCT equipment_id FROM equipment_bookings WHERE status IN ('confirmed','on_the_way') AND from_date <= ? AND to_date >= ?");
    $activeStmt->bind_param("ss", $todayStr, $todayStr);
    $activeStmt->execute();
    $activeRes = $activeStmt->get_result();
    while ($row = $activeRes->fetch_assoc()) { $currentlyBookedEquipmentIds[(int)$row['equipment_id']] = true; }
} catch (\Throwable $e) {}

$bookings = [];
try {
    try {
        $bkr = $conn->query("SELECT b.*, e.name AS equipment_name, e.image AS equipment_image, e.owner_name AS equipment_owner_name, e.owner_phone AS equipment_owner_phone, e.type AS equipment_type, u.full_name AS account_name, u.email AS account_email, u.mobile AS account_mobile FROM equipment_bookings b LEFT JOIN equipment e ON e.id = b.equipment_id LEFT JOIN users u ON u.id = b.user_id ORDER BY b.id DESC LIMIT 100");
    } catch (\Throwable $eJoin) {
        $bkr = false;
    }
    if (!$bkr) {
        $bkr = $conn->query("SELECT b.*, e.name AS equipment_name FROM equipment_bookings b LEFT JOIN equipment e ON e.id = b.equipment_id ORDER BY b.id DESC LIMIT 100");
    }
    if ($bkr) { while ($row = $bkr->fetch_assoc()) { $bookings[] = $row; } }
} catch (\Throwable $e) {}

// ---- Agri-Connect community ----
$communityPosts = [];
try {
    $cpr = $conn->query("SELECT p.*, u.full_name FROM community_posts p LEFT JOIN users u ON u.id = p.user_id ORDER BY p.id DESC LIMIT 100");
    if ($cpr) { while ($row = $cpr->fetch_assoc()) { $communityPosts[] = $row; } }
} catch (\Throwable $e) {}

$communityComments = [];
try {
    $ccr = $conn->query("SELECT c.*, u.full_name, p.title AS post_title FROM comments c LEFT JOIN users u ON u.id = c.user_id LEFT JOIN community_posts p ON p.id = c.post_id ORDER BY c.id DESC LIMIT 100");
    if ($ccr) { while ($row = $ccr->fetch_assoc()) { $communityComments[] = $row; } }
} catch (\Throwable $e) {}

// ---- Contact Messages ----
// ---- Krishi Bazaar (mandi / market crop prices) ----
$bazaarPrices = [];
try {
    $kbr = $conn->query("SELECT * FROM krishi_bazaar ORDER BY price_date DESC, id DESC LIMIT 200");
    if ($kbr) { while ($row = $kbr->fetch_assoc()) { $bazaarPrices[] = $row; } }
} catch (\Throwable $e) {
    // krishi_bazaar table doesn't exist yet — tab just shows empty.
}

// ---- Advisory (farming tips / crop advisory posts) ----
$advisoryPosts = [];
try {
    $adr = $conn->query("SELECT * FROM advisory ORDER BY id DESC LIMIT 200");
    if ($adr) { while ($row = $adr->fetch_assoc()) { $advisoryPosts[] = $row; } }
} catch (\Throwable $e) {
    // advisory table doesn't exist yet — tab just shows empty.
}

$contactMessages = [];
try {
    $cmr = $conn->query("SELECT * FROM contact_messages ORDER BY id DESC LIMIT 150");
    if ($cmr) { while ($row = $cmr->fetch_assoc()) { $contactMessages[] = $row; } }
} catch (\Throwable $e) {}

// ---- Feedback (footer feedback form) ----
$feedbackList = [];
try {
    $fbr = $conn->query("
        SELECT f.*, u.full_name AS submitter_name
        FROM feedback f
        LEFT JOIN users u ON u.id = f.user_id
        ORDER BY f.id DESC LIMIT 150
    ");
    if ($fbr) { while ($row = $fbr->fetch_assoc()) { $feedbackList[] = $row; } }
} catch (\Throwable $e) {
    // feedback table doesn't exist yet — run add_feedback_newsletter.sql
}

// ---- Newsletter subscribers (footer signup) ----
$newsletterSubscribers = [];
try {
    $nlr = $conn->query("SELECT * FROM newsletter_subscribers ORDER BY id DESC LIMIT 300");
    if ($nlr) { while ($row = $nlr->fetch_assoc()) { $newsletterSubscribers[] = $row; } }
} catch (\Throwable $e) {
    // newsletter_subscribers table doesn't exist yet — run add_feedback_newsletter.sql
}

// ---- Users ----
$allUsers = [];
try {
    $ur = $conn->query("SELECT id, full_name, mobile, email, district, role, created_at FROM users ORDER BY id DESC LIMIT 300");
    if ($ur) { while ($row = $ur->fetch_assoc()) { $allUsers[] = $row; } }
} catch (\Throwable $e) {}

// ---- Dashboard widgets: sales trend, category split, recent orders/customers/sellers ----
$monthlySales = [];
try {
    $ms = $conn->query("
        SELECT DATE_FORMAT(created_at,'%d %b') AS mon, DATE(created_at) AS ym, SUM(total_amount) AS total
        FROM orders WHERE order_status != 'cancelled'
        GROUP BY ym ORDER BY ym DESC LIMIT 14
    ");
    if ($ms) { while ($row = $ms->fetch_assoc()) { $monthlySales[] = $row; } }
    $monthlySales = array_reverse($monthlySales);
} catch (\Throwable $e) {
    // orders table not ready yet — chart just shows no data.
}

$dailyOrderCounts = [];
try {
    $doc = $conn->query("
        SELECT DATE(created_at) AS ym, COUNT(*) AS cnt
        FROM orders WHERE order_status != 'cancelled'
        GROUP BY ym ORDER BY ym DESC LIMIT 14
    ");
    if ($doc) { while ($row = $doc->fetch_assoc()) { $dailyOrderCounts[] = $row; } }
    $dailyOrderCounts = array_reverse($dailyOrderCounts);
} catch (\Throwable $e) {
    // orders table not ready yet — sparkline just stays hidden.
}

$categoryBreakdown = [];
$cbq = $conn->query("SELECT category, COUNT(*) AS cnt FROM products WHERE is_active = 1 AND category IS NOT NULL AND category <> '' GROUP BY category ORDER BY cnt DESC LIMIT 6");
if ($cbq) { while ($row = $cbq->fetch_assoc()) { $categoryBreakdown[] = $row; } }

$recentOrdersDash = [];
try {
    $rodq = $conn->query("SELECT o.id, o.order_number, o.created_at, o.total_amount, o.coupon_code, o.discount_amount, o.final_amount, o.order_status, u.full_name FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 20");
    if ($rodq) { while ($row = $rodq->fetch_assoc()) { $recentOrdersDash[] = $row; } }
    if (!empty($recentOrdersDash)) {
        $rodIds = implode(',', array_map(static function ($r) { return (int)$r['id']; }, $recentOrdersDash));
        $rodItemsByOrder = [];
        $rodItemsRes = $conn->query("SELECT order_id, product_name, quantity FROM order_items WHERE order_id IN ($rodIds)");
        if ($rodItemsRes) {
            while ($rir = $rodItemsRes->fetch_assoc()) { $rodItemsByOrder[(int)$rir['order_id']][] = $rir; }
        }
        foreach ($recentOrdersDash as &$rodRef) {
            $rodRef['items'] = $rodItemsByOrder[(int)$rodRef['id']] ?? [];
        }
        unset($rodRef);
    }
} catch (\Throwable $e) {
    $recentOrdersDash = array_slice($orders, 0, 5);
}

$recentCustomers = array_slice($allUsers, 0, 5);

$topSellersDash = $sellers;
usort($topSellersDash, function($a, $b) { return ($b['product_count'] ?? 0) <=> ($a['product_count'] ?? 0); });
$topSellersDash = array_slice($topSellersDash, 0, 5);

$topProductsDash = array_slice($products, 0, 12);

$districtBreakdown = [];
foreach ($allUsers as $u) {
    $d = trim($u['district'] ?? '') ?: 'Unknown';
    $districtBreakdown[$d] = ($districtBreakdown[$d] ?? 0) + 1;
}
arsort($districtBreakdown);
$districtBreakdown = array_slice($districtBreakdown, 0, 5, true);

$adminName = $_SESSION['admin_name'] ?? 'Admin';
$adminRoleName = $_SESSION['admin_role_name'] ?: 'Administrator';

function renderSparkline(array $values, string $color): string {
    $n = count($values);
    if ($n < 2) return '';
    $max = max($values); $min = min($values);
    $range = ($max - $min) ?: 1;
    $w = 200; $h = 34; $step = $w / ($n - 1);
    $pts = [];
    foreach (array_values($values) as $i => $v) {
        $x = round($i * $step, 1);
        $y = round($h - (($v - $min) / $range) * ($h - 6) - 3, 1);
        $pts[] = "$x,$y";
    }
    $polyline = implode(' ', $pts);
    return '<svg class="spark" viewBox="0 0 '.$w.' '.$h.'" preserveAspectRatio="none">'
        .'<polyline points="'.$polyline.'" fill="none" stroke="'.$color.'" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>'
        .'</svg>';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Dashboard — AgriCart</title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<script src="assets/vendor/chart.umd.js"></script>
<!-- Shared reusable Action-menu ("⋮") engine — see admin/includes/action_menu.php,
     assets/js/action-menu.js and assets/css/action-menu.css. Every other admin
     page picks these up via includes/team_layout_top.php / team_layout_bottom.php,
     but index.php is a standalone page (its own <head>/<body>) so it never
     loaded them — that's why every ⋮ button here (Products, Orders, etc.) was
     a dead click, and why the dropdown jumped to the top-left corner once the
     script (but not the matching CSS) was added. -->
<link rel="stylesheet" href="assets/css/action-menu.css?v=<?php echo @filemtime(__DIR__ . '/assets/css/action-menu.css') ?: time(); ?>">
<script src="assets/js/action-menu.js?v=<?php echo @filemtime(__DIR__ . '/assets/js/action-menu.js') ?: time(); ?>"></script>
<style>
:root{
    --primary:#2F4F44; --primary-dark:#1B2F29; --accent:#FFC107; --accent-strong:#C79100;
    --bg-soft:#EEF1EC; --text:#26292B; --muted:#68706B; --border:#E3E7E2;
    --danger:#9B3B37; --danger-bg:#F5E8E7; --warning:#B8860B; --warning-bg:#FBF3E0;
}
*{box-sizing:border-box}
body{margin:0;font-family:'Poppins',sans-serif;background:var(--bg-soft);color:var(--text)}

/* ---- Layout ---- */
.admin-shell{display:flex;min-height:100vh}
.sidebar{
    width:250px;
    background:linear-gradient(180deg,var(--primary-dark),var(--primary));
    color:#fff;flex-shrink:0;display:flex;flex-direction:column;position:sticky;top:0;height:100vh;
}
.sidebar-brand{display:flex;align-items:center;padding:20px 20px 18px;border-bottom:1px solid rgba(255,255,255,0.12)}
.brand-badge{display:inline-flex;align-items:center;gap:11px}
.brand-badge .fern{flex-shrink:0}
.brand-badge .txt{font-size:24px;font-weight:800;letter-spacing:-0.4px}
.brand-badge .txt .agri{color:#fff}
.brand-badge .txt .cart{color:#5A9802;margin-left:1px}
.sidebar-nav{flex:1;min-height:0;padding:16px 12px;overflow-y:auto;overflow-x:hidden}
.sidebar-nav::-webkit-scrollbar{width:5px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,0.18);border-radius:10px}
.sidebar-nav::-webkit-scrollbar-track{background:transparent}
.nav-item{
    display:flex;align-items:center;gap:12px;padding:10px 14px;border-radius:10px;color:rgba(255,255,255,0.82);
    cursor:pointer;font-size:13.5px;margin-bottom:2px;transition:background .2s ease, transform .15s ease, padding-left .2s ease;
    animation:navIn .4s ease both;text-decoration:none;
}
.nav-item:nth-child(1){animation-delay:.03s} .nav-item:nth-child(2){animation-delay:.06s}
.nav-item:nth-child(3){animation-delay:.09s} .nav-item:nth-child(4){animation-delay:.12s}
.nav-item:nth-child(5){animation-delay:.15s} .nav-item:nth-child(6){animation-delay:.18s}
.nav-item:nth-child(7){animation-delay:.21s} .nav-item:nth-child(8){animation-delay:.24s}
@keyframes navIn{ from{opacity:0; transform:translateX(-10px)} to{opacity:1; transform:translateX(0)} }
.nav-item i{width:18px;text-align:center;transition:transform .2s ease}
.nav-item:hover{background:rgba(255,255,255,0.08); padding-left:18px;}
.nav-item:hover i{transform:scale(1.15)}
.nav-item.active{background:rgba(255,255,255,0.16);color:#fff;font-weight:600}
.nav-section-label{font-size:10.5px;text-transform:uppercase;letter-spacing:.08em;color:rgba(255,255,255,.45);padding:14px 14px 6px}
.sidebar-foot{padding:16px;border-top:1px solid rgba(255,255,255,0.12); position:relative;}
.admin-chip{
    display:flex;align-items:center;gap:10px;font-size:12.5px;
    cursor:pointer; padding:8px; border-radius:10px; transition:background .2s ease;
    position:relative;
}
.admin-chip:hover{background:rgba(255,255,255,0.08)}
.admin-chip .av{width:30px;height:30px;border-radius:50%;background:var(--accent);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.admin-chip .chip-info{flex:1}
.admin-chip .chevron{transition:transform .2s ease; opacity:.7; font-size:11px;}
.admin-chip.open .chevron{transform:rotate(180deg)}
.profile-dropdown{
    position:absolute; bottom:calc(100% + 8px); left:16px; right:16px;
    background:#16241f; border:1px solid rgba(255,255,255,0.12); border-radius:10px;
    padding:6px; box-shadow:0 10px 28px rgba(0,0,0,0.4);
    display:none; flex-direction:column; gap:2px;
    z-index:20;
}
.profile-dropdown.open{ display:flex; animation:dropUp .2s cubic-bezier(.22,.8,.36,1) both; }
@keyframes dropUp{ from{opacity:0; transform:translateY(6px)} to{opacity:1; transform:translateY(0)} }
.profile-dropdown a{
    display:flex; align-items:center; gap:10px; padding:9px 10px; border-radius:7px;
    color:rgba(255,255,255,0.85); text-decoration:none; font-size:13px;
    transition:background .15s ease, color .15s ease, padding-left .15s ease;
}
.profile-dropdown a:hover{background:rgba(255,255,255,0.1); color:#fff; padding-left:14px;}
.profile-dropdown a i{width:16px; text-align:center;}
.profile-dropdown a.danger:hover{background:rgba(155,59,55,0.25); color:#f0a09c;}
.profile-dropdown .divider{height:1px; background:rgba(255,255,255,0.1); margin:4px 2px;}
.logout-btn{display:flex;align-items:center;justify-content:center;gap:8px;width:100%;padding:9px;border:1px solid rgba(255,255,255,0.25);background:transparent;color:#fff;border-radius:8px;cursor:pointer;font-size:13px;text-decoration:none;transition:background .2s ease, transform .15s ease}
.logout-btn:hover{background:rgba(255,255,255,0.1); transform:translateY(-1px)}
.logout-btn:active{transform:scale(.97)}

/* Mobile sidebar toggle — hidden on desktop; the sidebar itself already
   has an off-canvas rule at ≤900px (see media query below), it just had
   no button wired up to open/close it. Same "menu-toggle" pattern already
   used in admin/includes/team_layout_top.php, kept consistent here. */
.menu-toggle{display:none;background:none;border:none;font-size:20px;color:var(--primary);cursor:pointer;padding:6px;flex-shrink:0}
.sidebar-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:998}
.sidebar-overlay.show{display:block}
/* Wide dashboard tables (orders, products, sellers, etc.) scroll inside
   themselves instead of breaking the page layout — wrapper div added
   automatically, see the script near the sidebar-scroll script below. */
.agri-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;border-radius:12px}
.agri-table-wrap table{width:100%;border-collapse:collapse}
@media(max-width:600px){.agri-table-wrap table{min-width:480px}.agri-table-wrap th,.agri-table-wrap td{padding:8px 10px;font-size:12.5px}}

.main{
    flex:1;min-width:0;padding:28px 32px;
    background:var(--bg-soft);
}
.topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:24px;flex-wrap:wrap;gap:10px}
.hello-greet{font-size:13px;font-weight:600;color:var(--primary);margin-bottom:2px}
.topbar h1{font-size:19px;font-weight:700;margin:0}
.topbar .sub{color:var(--muted);font-size:13px}
.store-link{font-size:13px;color:var(--primary);text-decoration:none;font-weight:600;display:flex;align-items:center;gap:6px;transition:transform .15s ease, color .15s ease}
.notif-bell-wrap{position:relative}
.gs-search-wrap{position:relative;flex:1;max-width:360px;margin:0 12px}
.gs-search-box{display:flex;align-items:center;gap:8px;background:#fff;border:1px solid var(--border,#ddd);border-radius:10px;padding:8px 12px;font-size:13px;color:var(--muted,#777)}
.gs-search-box input{border:none;outline:none;flex:1;font-size:13px;color:var(--text,#222);background:transparent}
.gs-results{display:none;position:absolute;left:0;top:44px;width:100%;min-width:300px;max-height:420px;overflow-y:auto;background:#fff;border:1px solid var(--border,#ddd);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:200}
.gs-results.open{display:block}
.gs-cat{padding:8px 14px 4px;font-size:10.5px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted,#777);font-weight:700}
.gs-item{display:block;padding:9px 14px;text-decoration:none;color:inherit;font-size:13px;border-bottom:1px solid var(--border,#ddd)}
.gs-item:hover{background:var(--bg-soft,#f3f5f2)}
@media (max-width:900px){ .gs-search-wrap{display:none} }
.notif-bell{position:relative;background:#fff;border:1px solid var(--border,#ddd);border-radius:10px;width:38px;height:38px;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:15px;color:var(--text,#222)}
.notif-bell:hover{background:var(--bg-soft,#f3f5f2)}
.notif-badge{position:absolute;top:-5px;right:-5px;background:#E53935;color:#fff;font-size:10px;font-weight:700;min-width:16px;height:16px;border-radius:8px;display:flex;align-items:center;justify-content:center;padding:0 3px}
.notif-dropdown{display:none;position:absolute;right:0;top:46px;width:340px;max-width:88vw;max-height:420px;overflow-y:auto;background:#fff;border:1px solid var(--border,#ddd);border-radius:12px;box-shadow:0 8px 24px rgba(0,0,0,0.12);z-index:200}
.notif-dropdown.open{display:block}
.notif-dropdown-head{display:flex;align-items:center;justify-content:space-between;padding:12px 14px;border-bottom:1px solid var(--border,#ddd);font-weight:700;font-size:13.5px}
.notif-dropdown-head a{font-size:11.5px;font-weight:600;color:var(--primary);text-decoration:none}
.notif-item{display:block;padding:11px 14px;border-bottom:1px solid var(--border,#ddd);text-decoration:none;color:inherit}
.notif-item:hover{background:var(--bg-soft,#f3f5f2)}
.notif-item.unread{background:#F0F7EE}
.notif-item .t{font-size:12.5px;font-weight:700}
.notif-item .m{font-size:11.5px;color:var(--muted,#777);margin-top:2px}
.notif-item .d{font-size:10.5px;color:var(--muted,#777);margin-top:4px}
@media (max-width:768px){
    .notif-dropdown{position:fixed;right:10px;left:10px;top:64px;width:auto;max-width:none}
}
.store-link:hover{transform:translateX(3px); color:var(--primary-dark)}

/* ---- Stat cards ---- */
.stats-row{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-bottom:22px}
.stat-card{background:#fff;border-radius:16px;padding:18px 20px;box-shadow:0 1px 3px rgba(20,30,25,0.06);display:flex;align-items:center;gap:14px;transition:transform .25s cubic-bezier(.34,1.56,.64,1), box-shadow .25s ease;animation:cardIn .45s ease both;cursor:pointer}
.stat-card:hover{transform:translateY(-5px); box-shadow:0 12px 26px rgba(0,0,0,0.12);}
.stats-row .stat-card:nth-child(1){animation-delay:.05s} .stats-row .stat-card:nth-child(2){animation-delay:.1s}
.stats-row .stat-card:nth-child(3){animation-delay:.15s} .stats-row .stat-card:nth-child(4){animation-delay:.2s}
.stats-row .stat-card:nth-child(5){animation-delay:.25s}
@keyframes cardIn{ from{opacity:0; transform:translateY(14px) scale(.97)} to{opacity:1; transform:translateY(0) scale(1)} }
.stat-card .icn{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;color:#fff;flex-shrink:0;transition:transform .3s cubic-bezier(.34,1.56,.64,1);position:relative}
.stat-card .icn::before{content:'';position:absolute;inset:-8px;border-radius:50%;background:inherit;opacity:.14;z-index:-1}
.stat-card:hover .icn{transform:scale(1.12) rotate(-6deg)}
.stat-card .val{font-size:21px;font-weight:800;line-height:1.1}
.stat-card .lbl{font-size:12px;color:var(--muted)}
.stat-card .trend{display:flex;align-items:center;gap:4px;font-size:11px;font-weight:700;margin-top:3px}
.stat-card .trend.up{color:#2E7D46}
.stat-card .trend.down{color:var(--danger)}
.stat-card .trend span{color:var(--muted);font-weight:500}
.stat-card{position:relative;overflow:hidden}
.stat-card .spark{position:absolute;right:0;bottom:0;left:0;height:26px;opacity:.35;pointer-events:none;z-index:0}
.stat-card>.icn,.stat-card>div{position:relative;z-index:1}

/* ---- Panels ---- */
.panel{display:none;background:#fff;border-radius:14px;box-shadow:0 2px 10px rgba(0,0,0,0.05);padding:22px}
.panel.active{display:block; animation:panelIn .35s ease both;}
@keyframes panelIn{ from{opacity:0; transform:translateY(8px)} to{opacity:1; transform:translateY(0)} }
.panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:18px;flex-wrap:wrap;gap:10px}
.panel-head h2{font-size:16px;margin:0}
.btn-primary{background:var(--primary);color:#fff;border:none;padding:10px 16px;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;gap:8px;transition:background .2s ease, transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease}
.btn-primary:hover{background:var(--primary-dark); transform:translateY(-2px); box-shadow:0 6px 14px rgba(47,79,68,0.3);}
.btn-primary:active{transform:translateY(0) scale(.97)}

table{width:100%;border-collapse:collapse;font-size:13px}
th{background:var(--bg-soft);color:var(--muted);text-align:left;padding:10px 12px;font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.03em}
td{padding:10px 12px;border-bottom:1px solid var(--border);vertical-align:middle}
tr{transition:background .15s ease}
tbody tr:hover{background:var(--bg-soft)}
tr:last-child td{border-bottom:none}
.prod-thumb{width:42px;height:42px;object-fit:cover;border-radius:8px;background:#eee;transition:transform .25s ease;display:block}
tbody tr:hover .prod-thumb{transform:scale(1.12)}
.tag{display:inline-block;padding:3px 9px;border-radius:20px;font-size:11px;font-weight:600;transition:transform .15s ease}
tbody tr:hover .tag{transform:scale(1.05)}
.tag.in{background:var(--bg-soft);color:var(--primary)}
.tag.low{background:var(--warning-bg);color:var(--warning)}
.tag.out{background:var(--danger-bg);color:var(--danger)}
.tag.product{background:var(--bg-soft);color:var(--primary)}
.tag.equipment{background:#FFF6E0;color:#B8860B}
.icon-btn{border:none;background:var(--bg-soft);color:var(--primary-dark);width:30px;height:30px;border-radius:7px;cursor:pointer;margin-right:4px;transition:transform .15s cubic-bezier(.34,1.56,.64,1), background .15s ease, box-shadow .15s ease}
.icon-btn:hover{transform:translateY(-2px) scale(1.08); box-shadow:0 4px 10px rgba(0,0,0,0.15);}
.icon-btn:active{transform:scale(.92)}
.icon-btn.danger{background:var(--danger-bg);color:var(--danger)}
.status-select{padding:6px 8px;border-radius:7px;border:1px solid var(--border);font-size:12px;transition:border-color .15s ease, transform .15s ease}
.status-select:hover{border-color:var(--primary)}
.status-select:focus{outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(47,79,68,0.1)}

/* ---- Modal ---- */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.5);z-index:1000;align-items:center;justify-content:center;padding:20px;animation:overlayIn .2s ease}
.modal-overlay.open{display:flex}
@keyframes overlayIn{ from{opacity:0} to{opacity:1} }
.modal-box{background:#fff;border-radius:14px;max-width:560px;width:100%;padding:26px;max-height:90vh;overflow-y:auto;animation:modalIn .3s cubic-bezier(.22,.8,.36,1) both}
.vd-row{display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid #f0f0f0;font-size:13px}
.vd-row span:first-child{color:#888;font-weight:600;flex:0 0 40%}
.vd-row span:last-child{color:#222;text-align:right;flex:1}
.vd-desc{margin-top:12px;padding:12px;background:#f8faf8;border:1px solid #eee;border-radius:9px;font-size:13px;line-height:1.6;color:#444}
#viewDetailsImageWrap img{width:100%;max-height:220px;object-fit:cover;border-radius:10px}
.vd-items{display:flex;flex-direction:column;gap:10px}
.vd-item-row{display:flex;align-items:center;gap:10px}
.vd-item-row img{width:46px;height:46px;object-fit:cover;border-radius:8px;background:#eee;flex-shrink:0}
.vd-item-info{flex:1}
.vd-item-name{font-weight:600;font-size:13px;color:#222}
.vd-item-meta{font-size:12px;color:#888}
.clickable-name{cursor:pointer}
.clickable-name:hover{text-decoration:underline;color:var(--primary-dark)}
@keyframes modalIn{ from{opacity:0; transform:translateY(16px) scale(.96)} to{opacity:1; transform:translateY(0) scale(1)} }
.modal-box h3{margin-top:0;font-size:16px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:14px}
.form-grid label{font-size:12px;font-weight:600;color:var(--muted);display:block;margin-bottom:4px}
.form-grid input,.form-grid select,.form-grid textarea{width:100%;padding:9px 10px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;transition:border-color .15s ease, box-shadow .15s ease}
.form-grid input:focus,.form-grid select:focus,.form-grid textarea:focus{outline:none; border-color:var(--primary); box-shadow:0 0 0 3px rgba(47,79,68,0.1)}
.form-grid textarea{grid-column:1/-1;resize:vertical}
.form-full{grid-column:1/-1}
.modal-actions{display:flex;justify-content:flex-end;gap:10px;margin-top:18px}
.btn-secondary{background:#f2f2f2;color:#444;border:none;padding:10px 16px;border-radius:9px;cursor:pointer;font-size:13.5px;transition:background .15s ease, transform .15s ease}
.btn-secondary:hover{background:#e6e6e6}
.btn-secondary:active{transform:scale(.97)}

.empty-state{text-align:center;color:var(--muted);padding:40px 0;font-size:13.5px}
.toast{position:fixed;bottom:24px;right:24px;background:var(--primary-dark);color:#fff;padding:12px 18px;border-radius:10px;font-size:13.5px;display:none;align-items:center;gap:8px;z-index:2000;box-shadow:0 8px 20px rgba(0,0,0,0.3)}
.toast.show{display:flex; animation:toastIn .35s cubic-bezier(.34,1.56,.64,1) both;}
@keyframes toastIn{ from{opacity:0; transform:translateY(14px) scale(.95)} to{opacity:1; transform:translateY(0) scale(1)} }
.note-box{background:var(--bg-soft);border-left:4px solid var(--primary);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--muted);margin-bottom:16px}
.subtab-bar{display:flex;gap:8px;margin-bottom:16px;border-bottom:1px solid #eee;padding-bottom:10px}
.subtab-btn{background:none;border:1px solid #ddd;padding:8px 14px;border-radius:8px;font-size:13px;font-weight:600;color:var(--muted);cursor:pointer;transition:all .15s ease}
.subtab-btn:hover{background:var(--bg-soft)}
.subtab-btn.active{background:var(--primary);border-color:var(--primary);color:#fff}
.kb-dropdown{position:relative}
.kb-dropdown-btn{padding:9px 12px;border-radius:8px;border:1px solid #ddd;font-size:13px;min-width:170px;background:#fff;color:#333;cursor:pointer;display:flex;align-items:center;justify-content:space-between;gap:10px;text-align:left}
.kb-dropdown-btn:hover{background:var(--bg-soft)}
.kb-dropdown-menu{position:absolute;top:calc(100% + 4px);left:0;min-width:100%;max-height:240px;overflow-y:auto;background:#fff;border:1px solid #ddd;border-radius:8px;box-shadow:0 10px 24px rgba(0,0,0,.14);z-index:60;display:none}
.kb-dropdown-menu.open{display:block}
.kb-dropdown-item{padding:9px 14px;font-size:13px;cursor:pointer;white-space:nowrap}
.kb-dropdown-item:hover{background:var(--bg-soft)}
.kb-dropdown-item.selected{color:var(--primary);font-weight:600}

/* ---- Dashboard widgets (Mofi-style) ---- */
.dash-grid{display:grid;gap:16px;margin-top:16px}
.dash-grid-top{grid-template-columns:1.7fr 1fr}
.dash-grid-bottom{grid-template-columns:1fr 1fr 1fr}
.dash-card{background:#fff;border-radius:16px;padding:20px 22px;box-shadow:0 1px 3px rgba(20,30,25,0.06);animation:cardIn .45s ease both}
.dash-card-wide{min-width:0}
.dash-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px}
.dash-card-head h3{margin:0;font-size:15px;font-weight:700}
.dash-link{font-size:12px;color:var(--primary);font-weight:600;cursor:pointer;display:flex;align-items:center;gap:5px;transition:transform .15s ease}
.dash-link:hover{transform:translateX(3px)}
.dash-table{width:100%;border-collapse:collapse;font-size:13px}
.dash-table th{background:transparent;text-align:left;color:var(--muted);font-weight:600;font-size:11.5px;text-transform:uppercase;letter-spacing:.3px;padding:0 10px 10px;border-bottom:1px solid var(--border)}
.dash-table td{padding:11px 10px;border-bottom:1px solid var(--border)}
.dash-table tr:last-child td{border-bottom:none}
.dash-list-item{display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--border)}
.dash-list-item:last-child{border-bottom:none;padding-bottom:0}
.dash-list-item:first-child{padding-top:0}
.dash-avatar{width:38px;height:38px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:14px;flex-shrink:0}
.dash-avatar.seller{background:var(--accent-strong);font-size:13px}
.dash-list-info{min-width:0}
.dash-list-name{font-weight:600;font-size:13px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.dash-list-sub{font-size:11.5px;color:var(--muted);margin-top:2px}
.donut-wrap{display:flex;justify-content:center;margin-bottom:14px}
.donut-legend{display:flex;flex-direction:column;gap:8px}
.legend-row{font-size:12.5px;color:var(--muted);display:flex;align-items:center;gap:8px}
.legend-row b{color:var(--text);margin-left:auto}
.legend-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0}
.dash-search{padding:7px 14px;border:1px solid transparent;background:var(--bg-soft);border-radius:20px;font-size:12.5px;font-family:inherit;width:150px;transition:border-color .15s ease, box-shadow .15s ease, background .15s ease}
.dash-search:focus{outline:none;background:#fff;border-color:var(--primary);box-shadow:0 0 0 3px rgba(47,79,68,0.1);width:190px}
.dash-kebab{width:28px;height:28px;border-radius:50%;display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:15px;flex-shrink:0;cursor:default;transition:background .15s ease}
.dash-kebab:hover{background:var(--bg-soft)}
.stat-card{position:relative}
.stat-card .dash-kebab{position:absolute;top:14px;right:14px}
.dist-card-bg{background-image:radial-gradient(var(--border) 1.4px, transparent 1.4px);background-size:14px 14px;border-radius:10px;padding:18px 14px;margin-top:2px}
.dash-table-foot{margin-top:12px;font-size:12px;color:var(--muted);display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.dash-pager{display:flex;align-items:center;gap:6px}
.dash-pager button{border:1px solid var(--border);background:#fff;color:var(--text);font-size:12px;padding:5px 10px;border-radius:6px;cursor:pointer;transition:background .15s ease,color .15s ease}
.dash-pager button:hover:not(:disabled){background:var(--bg-soft)}
.dash-pager button.active{background:var(--primary);border-color:var(--primary);color:#fff;border-radius:50%;width:26px;height:26px;padding:0;display:inline-flex;align-items:center;justify-content:center}
.dash-table input[type=checkbox]{accent-color:var(--primary);width:15px;height:15px;cursor:pointer}
.dash-pager button:disabled{opacity:.4;cursor:not-allowed}
.dist-total{font-size:13px;color:var(--muted);margin-bottom:10px}
.dist-bar{display:flex;width:100%;height:14px;border-radius:20px;overflow:hidden;background:var(--bg-soft)}
.dist-bar span{height:100%}
.spot-img{width:100%;height:170px;object-fit:cover;display:block;background:var(--bg-soft)}
.spot-body{padding:16px 18px 20px}
.spot-cat{font-size:11px;color:var(--accent-strong);font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.spot-name{font-size:16px;font-weight:700;margin-top:4px}
.spot-price{font-size:19px;font-weight:800;color:var(--primary);margin-top:6px}
.spot-seller{font-size:12px;color:var(--muted);margin-top:4px}

@media(max-width:1100px){
    .dash-grid-top{grid-template-columns:1fr}
    .dash-grid-bottom{grid-template-columns:1fr}
}

@media(max-width:900px){
    .sidebar{position:fixed;left:-260px;z-index:999;transition:left .2s ease}
    .sidebar.open{left:0}
    .form-grid{grid-template-columns:1fr}
    .menu-toggle{display:block}
    .topbar h1{font-size:17px}
    .stats-row{grid-template-columns:repeat(auto-fit,minmax(160px,1fr))}
}
@media(max-width:600px){
    .main{padding:18px 16px}
    .stats-row{grid-template-columns:repeat(2,1fr)}
    .form-grid{grid-template-columns:1fr}
    .topbar{gap:12px}
}
/* Shared "kebab" (three-dot) actions menu — replaces rows of separate
   action buttons in table Action/Actions columns (products, rentals,
   mandi, community, tickets, feedback, users, sellers, coupons, etc).
   Same pattern as admin/inventory.php's product table actions.
   NOTE: positioning/box styling now comes entirely from the shared
   assets/css/action-menu.css (linked in <head> below) so it matches
   every other admin page and stays in sync with action-menu.js, which
   portals the dropdown to <body> and positions it with `position:fixed`
   math. Keeping a duplicate `position:absolute` rule here (the old
   version of this block) fought with that JS and threw the menu to the
   top-left corner whenever the page was scrolled — only the .kebab-btn
   trigger button styling (not shared elsewhere) stays local. */
.kebab-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text);transition:.15s ease}
.kebab-btn:hover{background:var(--bg-soft);border-color:var(--primary)}
</style>
<meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
<script>
// Auto-attach the CSRF token to every same-origin fetch() POST/PUT/DELETE/PATCH,
// so existing admin AJAX calls to *_action.php work without per-call edits.
(function () {
  var token = document.querySelector('meta[name="csrf-token"]').content;
  var origFetch = window.fetch;
  window.fetch = function (input, init) {
    init = init || {};
    var method = (init.method || 'GET').toUpperCase();
    if (['POST', 'PUT', 'PATCH', 'DELETE'].includes(method)) {
      init.headers = new Headers(init.headers || {});
      if (!init.headers.has('X-CSRF-Token')) init.headers.set('X-CSRF-Token', token);
      if (init.body instanceof FormData && !init.body.has('csrf_token')) init.body.append('csrf_token', token);
    }
    return origFetch(input, init);
  };
  document.addEventListener('submit', function (e) {
    var form = e.target;
    if (form.tagName === 'FORM' && form.method && form.method.toLowerCase() === 'post' && !form.querySelector('input[name="csrf_token"]')) {
      var inp = document.createElement('input');
      inp.type = 'hidden'; inp.name = 'csrf_token'; inp.value = token;
      form.appendChild(inp);
    }
  }, true);
})();
</script>
</head>
<body>

<div class="admin-shell">
    <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeAdminSidebar()"></div>
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-brand">
            <div class="brand-badge">
                <img src="../assets/images/agricart-logo.png?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/images/agricart-logo.png') ?: time(); ?>" alt="AgriCart" class="fern" style="width:56px;height:56px;object-fit:contain;border-radius:50%;flex-shrink:0">
                <span class="txt"><span class="agri">Agri</span><span class="cart">Cart</span></span>
            </div>
        </div>
        <?php
        // RBAC: figure out which tab should be "active" on page load — the
        // first tab (in sidebar order) that this admin actually has
        // permission to view. Falls back to an empty string (no tab, the
        // "no access" notice inside #tab-dashboard's permission gate — see
        // below — is what they'll see) if somehow none apply.
        $agriTabPerms = [
            'dashboard' => 'dashboard.view', 'products' => 'products.view', 'orders' => 'orders.view',
            'equipment' => 'equipment.view', 'bookings' => 'rental_bookings.view', 'bazaar' => 'bazaar.view',
            'advisory' => 'advisory.view', 'community' => 'community.view', 'messages' => 'support.view',
            'feedback' => 'feedback.manage', 'users' => 'users.view', 'sellers' => 'sellers.view',
            'reviews' => 'reviews.view', 'coupons' => 'coupons.view',
        ];
        $agriFirstTab = '';
        foreach ($agriTabPerms as $agriTabKey => $agriPermKey) {
            if (hasPermission($agriPermKey)) { $agriFirstTab = $agriTabKey; break; }
        }
        // Deep-link support: ?tab=products (used by the grouped sidebar on
        // the other admin pages) opens straight to that section instead of
        // always landing on the first-permitted tab — falls back silently
        // to the existing behavior if the tab is invalid/not permitted.
        $agriRequestedTab = trim($_GET['tab'] ?? '');
        if ($agriRequestedTab !== '' && isset($agriTabPerms[$agriRequestedTab]) && hasPermission($agriTabPerms[$agriRequestedTab])) {
            $agriFirstTab = $agriRequestedTab;
        }
        function agriNavActive($tab, $first) { return $tab === $first ? ' active' : ''; }
        ?>
        <nav class="sidebar-nav" id="sidebarNav">
            <?php $sidebarIsIndex = true; include __DIR__ . '/includes/sidebar_nav.php'; ?>
        </nav>
        <script>
        // Keep the sidebar scrolled to the active menu item instead of
        // resetting to the top (Dashboard) on every navigation.
        (function () {
            function scrollToActive() {
                var nav = document.getElementById('sidebarNav');
                var active = nav && nav.querySelector('.nav-item.active');
                if (!nav || !active) return;
                var target = active.offsetTop - (nav.clientHeight / 2) + (active.clientHeight / 2);
                nav.scrollTop = Math.max(0, target);
            }
            // Run once ASAP, then again after everything (images, fonts) has
            // finished loading and settled, since layout shifts after the
            // first pass were pushing the scroll position back up.
            scrollToActive();
            window.addEventListener('load', function () {
                requestAnimationFrame(function () { requestAnimationFrame(scrollToActive); });
            });
        })();

        // ========== MOBILE SIDEBAR TOGGLE (≤900px) ==========
        // The off-canvas CSS (.sidebar{left:-260px} / .sidebar.open{left:0})
        // already existed but had no button wired up to it — this adds that,
        // plus an overlay and auto-close so it matches the storefront's
        // hamburger-menu behavior.
        function toggleAdminSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var btn = document.getElementById('sidebarToggle');
            if (!sidebar) return;
            var isOpen = sidebar.classList.toggle('open');
            if (overlay) overlay.classList.toggle('show', isOpen);
            document.body.style.overflow = isOpen ? 'hidden' : '';
            if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
        }
        function closeAdminSidebar() {
            var sidebar = document.getElementById('sidebar');
            var overlay = document.getElementById('sidebarOverlay');
            var btn = document.getElementById('sidebarToggle');
            if (sidebar) sidebar.classList.remove('open');
            if (overlay) overlay.classList.remove('show');
            document.body.style.overflow = '';
            if (btn) btn.setAttribute('aria-expanded', 'false');
        }
        // Auto-close after picking a tab on mobile, so the (now-switched)
        // content is immediately visible instead of staying hidden behind
        // the open sidebar.
        document.getElementById('sidebarNav') && document.getElementById('sidebarNav').addEventListener('click', function (e) {
            if (window.innerWidth <= 900 && e.target.closest('.nav-item')) closeAdminSidebar();
        });
        // Reset if the window is resized back up to desktop width while open.
        window.addEventListener('resize', function () {
            if (window.innerWidth > 900) closeAdminSidebar();
        });

        // ========== RESPONSIVE TABLE WRAPPER ==========
        // Wraps every <table> (Orders, Products, Sellers, etc. — 19 tables
        // across this page's tabs, several rendered dynamically when a tab
        // is opened) in a scrollable .agri-table-wrap div, same pattern as
        // the storefront's includes/header.php, so wide tables scroll
        // inside themselves instead of breaking the page on tablets/phones.
        (function () {
            function wrapTable(table) {
                if (table.closest('.agri-table-wrap')) return;
                var parent = table.parentElement;
                if (!parent) return;
                var classes = parent.className ? parent.className.split(/\s+/) : [];
                for (var i = 0; i < classes.length; i++) { if (/wrap|scroll|responsive/i.test(classes[i])) return; }
                var wrap = document.createElement('div');
                wrap.className = 'agri-table-wrap';
                parent.insertBefore(wrap, table);
                wrap.appendChild(table);
            }
            function wrapAll(root) { (root || document).querySelectorAll('table').forEach(wrapTable); }
            wrapAll();
            new MutationObserver(function (mutations) {
                mutations.forEach(function (m) {
                    m.addedNodes.forEach(function (node) {
                        if (node.nodeType !== 1) return;
                        if (node.tagName === 'TABLE') wrapTable(node);
                        else if (node.querySelectorAll) wrapAll(node);
                    });
                });
            }).observe(document.body, { childList: true, subtree: true });
        })();
        </script>
        <div class="sidebar-foot">
            <div class="admin-chip" id="profileTrigger" onclick="toggleProfileMenu()">
                <div class="av"><?php echo strtoupper(substr($adminName,0,1)); ?></div>
                <div class="chip-info"><?php echo htmlspecialchars($adminName); ?><br><span style="opacity:0.7"><?php echo htmlspecialchars($adminRoleName); ?></span></div>
                <i class="fa-solid fa-chevron-down chevron"></i>
            </div>
            <div class="profile-dropdown" id="profileDropdown">
                <a href="#" onclick="editMyName(event)"><i class="fa-solid fa-pen"></i> Edit My Name</a>
                <a href="switch_to_user.php"><i class="fa-solid fa-user"></i> Login as User</a>
                <div class="divider"></div>
                <a href="logout.php" class="danger"><i class="fa-solid fa-right-from-bracket"></i> Logout</a>
            </div>
        </div>
    </aside>

    <main class="main">
        <div class="topbar">
            <div style="display:flex;align-items:center;gap:12px;">
                <button class="menu-toggle" id="sidebarToggle" onclick="toggleAdminSidebar()" aria-label="Toggle menu" aria-expanded="false"><i class="fa-solid fa-bars"></i></button>
                <div>
                    <div class="hello-greet">Hello, <?php echo htmlspecialchars($adminName); ?> 👋</div>
                    <h1>Admin Dashboard</h1>
                    <div class="sub">Full control over AgriCart's catalogue, orders and sellers.</div>
                </div>
            </div>
            <div class="gs-search-wrap">
                <div class="gs-search-box">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="text" id="gsInput" placeholder="Search sellers, orders, buyers, GSTIN…" autocomplete="off" oninput="gsHandleInput()" onfocus="gsHandleInput()">
                </div>
                <div class="gs-results" id="gsResults"></div>
            </div>
            <div class="notif-bell-wrap">
                <button class="notif-bell" id="notifBellBtn" onclick="toggleNotifDropdown()" title="Notifications">
                    <i class="fa-solid fa-bell"></i>
                    <span class="notif-badge" id="notifBadge" style="display:none">0</span>
                </button>
                <div class="notif-dropdown" id="notifDropdown">
                    <div class="notif-dropdown-head">
                        <span>Notifications</span>
                        <a href="#" onclick="markAllNotifsRead(event)">Mark all read</a>
                    </div>
                    <div class="notif-list" id="notifList"><div class="empty-state" style="padding:24px 12px">Loading…</div></div>
                </div>
            </div>
            <a href="../pages/marketplace.php" class="store-link" id="storeLink" target="_blank"><i class="fa-solid fa-arrow-up-right-from-square"></i> <span id="storeLinkText">View Storefront</span></a>
        </div>

        <?php if ($approvalColsMissing): ?>
        <div style="background:#fff3e0;border:1px solid #ffb74d;color:#8a5a00;border-radius:12px;padding:14px 18px;margin-bottom:18px;font-size:13.5px;display:flex;gap:10px;align-items:flex-start">
            <i class="fa-solid fa-triangle-exclamation" style="margin-top:2px"></i>
            <div><strong>Approval columns missing.</strong> Farmer-submitted products/equipment will publish immediately without review until you run <code>setup/farmer_selling_upgrade.sql</code> on this database.</div>
        </div>
        <?php endif; ?>

        <?php
        $agriAnyStatCard = hasPermission('products.view') || hasPermission('orders.view') || hasPermission('finance.view') || hasPermission('sellers.view') || hasPermission('products.approve') || hasPermission('equipment.approve') || hasPermission('companies.view');
        ?>
        <?php if ($agriAnyStatCard): ?>
        <div class="stats-row">
            <?php if (hasPermission('products.view')): ?>
            <div class="stat-card" onclick="goToTab('products')"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-cart-shopping"></i></div><div><div class="val"><?php echo $totalProducts; ?></div><div class="lbl">Active Products</div></div><span class="dash-kebab">&#8942;</span></div>
            <div class="stat-card" onclick="goToOutOfStock()"><div class="icn" style="background:#B71C1C"><i class="fa-solid fa-box-open"></i></div><div><div class="val"><?php echo $outOfStockCount; ?></div><div class="lbl">Out of Stock</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('orders.view')): ?>
            <div class="stat-card" onclick="goToTab('orders')"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-truck-fast"></i></div><div><div class="val"><?php echo $totalOrders; ?></div><div class="lbl">Total Orders</div><?php if ($ordersTrend): ?><div class="trend <?php echo $ordersTrend['up']?'up':'down'; ?>"><i class="fa-solid fa-arrow-<?php echo $ordersTrend['up']?'up':'down'; ?>"></i> <?php echo abs($ordersTrend['pct']); ?>% <span>vs <?php echo $lastMonthLabel; ?></span></div><?php endif; ?></div><span class="dash-kebab">&#8942;</span><?php echo renderSparkline(array_column($dailyOrderCounts, 'cnt') ?: [0,0], '#5A9802'); ?></div>
            <div class="stat-card" onclick="goToTab('orders', true)"><div class="icn" style="background:#F9A825"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo $pendingOrders; ?></div><div class="lbl">Pending Orders</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('finance.view') || hasPermission('orders.view')): ?>
            <div class="stat-card" onclick="goToTab('orders')"><div class="icn" style="background:#c62828"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="val">₹<?php echo number_format($revenue,0); ?></div><div class="lbl">Revenue (delivered+pending)</div><?php if ($revenueTrend): ?><div class="trend <?php echo $revenueTrend['up']?'up':'down'; ?>"><i class="fa-solid fa-arrow-<?php echo $revenueTrend['up']?'up':'down'; ?>"></i> <?php echo abs($revenueTrend['pct']); ?>% <span>vs <?php echo $lastMonthLabel; ?></span></div><?php endif; ?></div><span class="dash-kebab">&#8942;</span><?php echo renderSparkline(array_column($monthlySales, 'total') ?: [0,0], '#c62828'); ?></div>
            <?php endif; ?>
            <?php if (hasPermission('sellers.view')): ?>
            <div class="stat-card" onclick="goToTab('sellers')"><div class="icn" style="background:#1b5e20"><i class="fa-solid fa-store"></i></div><div><div class="val"><?php echo $totalSellers; ?></div><div class="lbl">Sellers</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('products.approve') || hasPermission('equipment.approve')): ?>
            <div class="stat-card" onclick="goToPendingApprovals()"><div class="icn" style="background:#F57C00"><i class="fa-solid fa-clipboard-check"></i></div><div><div class="val"><?php echo $pendingApprovalsTotal; ?></div><div class="lbl">Pending Approvals</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('companies.view')): ?>
            <div class="stat-card" onclick="window.location.href='companies.php'"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-building"></i></div><div><div class="val"><?php echo $totalCompanies; ?></div><div class="lbl">Companies</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('finance.view')): ?>
            <div class="stat-card" onclick="window.location.href='invoices.php'"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><div class="val"><?php echo $totalInvoices; ?></div><div class="lbl">Invoices</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('finance.view')): ?>
            <div class="stat-card" onclick="window.location.href='invoices.php'"><div class="icn" style="background:#00695C"><i class="fa-solid fa-percent"></i></div><div><div class="val">₹<?php echo number_format($platformCommission,0); ?></div><div class="lbl">Platform Commission</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('finance.payout')): ?>
            <div class="stat-card" onclick="window.location.href='seller_payouts.php'"><div class="icn" style="background:#6A1B9A"><i class="fa-solid fa-hand-holding-dollar"></i></div><div><div class="val">₹<?php echo number_format($pendingPayoutAmount,0); ?></div><div class="lbl">Pending Seller Payout</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('accounts.view')): ?>
            <div class="stat-card" onclick="window.location.href='accounts.php'"><div class="icn" style="background:#1565C0"><i class="fa-solid fa-users"></i></div><div><div class="val"><?php echo $activeBuyersCount; ?></div><div class="lbl">Active Buyers</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
            <?php if (hasPermission('inventory.view') || hasPermission('products.view')): ?>
            <div class="stat-card" onclick="window.location.href='inventory.php'"><div class="icn" style="background:#EF6C00"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="val"><?php echo $lowStockCount; ?></div><div class="lbl">Low Stock Products</div></div><span class="dash-kebab">&#8942;</span></div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php
        // ---- Action Required — spec §4: every clickable item redirects to the
        // module that resolves it, and the whole section only renders items the
        // logged-in admin actually has permission to act on. ----
        $agriActionItems = [];
        if (hasPermission('companies.view') && $pendingCompanyVerification > 0) {
            $agriActionItems[] = ['icon' => 'fa-building-shield', 'label' => 'Company Verification Pending', 'count' => $pendingCompanyVerification, 'href' => 'companies.php'];
        }
        if (hasPermission('accounts.verify') && $pendingGstVerification > 0) {
            $agriActionItems[] = ['icon' => 'fa-file-shield', 'label' => 'GST Verification Pending', 'count' => $pendingGstVerification, 'href' => 'gst_verification_requests.php'];
        }
        if (hasPermission('rental_bookings.verify_payment') && $pendingPaymentVerification > 0) {
            $agriActionItems[] = ['icon' => 'fa-money-check-dollar', 'label' => 'Payment Verification Pending', 'count' => $pendingPaymentVerification, 'href' => 'payment_verification.php'];
        }
        if (hasPermission('finance.payout') && $pendingPayoutCount > 0) {
            $agriActionItems[] = ['icon' => 'fa-hand-holding-dollar', 'label' => 'Payout Requests', 'count' => $pendingPayoutCount, 'href' => 'seller_payouts.php'];
        }
        if (hasPermission('finance.view') && $pendingRefundRequests > 0) {
            $agriActionItems[] = ['icon' => 'fa-rotate-left', 'label' => 'Refund Requests', 'count' => $pendingRefundRequests, 'href' => 'finance_center.php?range=all&status=refunded'];
        }
        if (hasPermission('support.view') && $pendingComplaints > 0) {
            $agriActionItems[] = ['icon' => 'fa-envelope-open-text', 'label' => 'Customer Complaints', 'count' => $pendingComplaints, 'href' => "javascript:goToTab('messages')"];
        }
        if ((hasPermission('inventory.view') || hasPermission('products.view')) && $lowStockCount > 0) {
            $agriActionItems[] = ['icon' => 'fa-triangle-exclamation', 'label' => 'Low Inventory', 'count' => $lowStockCount, 'href' => 'inventory.php'];
        }
        if (hasPermission('finance.view') && $failedTransactionsCount > 0) {
            $agriActionItems[] = ['icon' => 'fa-circle-exclamation', 'label' => 'Failed Transactions', 'count' => $failedTransactionsCount, 'href' => 'finance_center.php?range=all&status=failed'];
        }
        $agriCanSeeActionSection = hasPermission('companies.view') || hasPermission('accounts.verify') || hasPermission('rental_bookings.verify_payment')
            || hasPermission('finance.payout') || hasPermission('finance.view') || canViewModule('orders') || hasPermission('support.view') || hasPermission('inventory.view') || hasPermission('products.view');
        ?>
        <?php if ($agriCanSeeActionSection): ?>
        <div style="background:#fff;border-radius:16px;box-shadow:0 1px 3px rgba(20,30,25,0.06);padding:20px 22px;margin-bottom:22px">
            <div class="panel-head" style="margin-bottom:14px"><h2 style="font-size:16px;margin:0"><i class="fa-solid fa-bell" style="color:#F57C00;margin-right:8px"></i>Action Required</h2></div>
            <?php if (empty($agriActionItems)): ?>
            <div class="empty-state"><i class="fa-solid fa-circle-check" style="color:#2E7D32;margin-right:6px"></i>Nothing needs your attention right now — all queues are clear.</div>
            <?php else: ?>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:12px">
                <?php foreach ($agriActionItems as $ai): ?>
                <a href="<?php echo htmlspecialchars($ai['href']); ?>" style="display:flex;align-items:center;gap:12px;padding:14px 16px;border:1px solid var(--border);border-radius:12px;text-decoration:none;color:inherit;transition:.15s" onmouseover="this.style.background='var(--bg-soft)'" onmouseout="this.style.background=''">
                    <div style="width:38px;height:38px;border-radius:50%;background:#FFF3E0;color:#F57C00;display:flex;align-items:center;justify-content:center;flex-shrink:0"><i class="fa-solid <?php echo htmlspecialchars($ai['icon']); ?>"></i></div>
                    <div style="flex:1"><div style="font-weight:700;font-size:15px;line-height:1.2"><?php echo (int)$ai['count']; ?></div><div style="font-size:12.5px;color:var(--muted)"><?php echo htmlspecialchars($ai['label']); ?></div></div>
                    <i class="fa-solid fa-chevron-right" style="color:var(--muted);font-size:12px"></i>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>


        <!-- DASHBOARD TAB -->
        <div class="panel active" id="tab-dashboard">
            <div class="panel-head"><h2>Overview</h2></div>
            <p style="color:var(--muted);font-size:13.5px;line-height:1.7;margin-top:-8px">
                Use the sidebar to manage products, track and update orders, review sellers, and moderate reviews.
                This dashboard has full access to the AgriCart database — changes here reflect immediately on the storefront.
            </p>

            <?php if (!hasPermission('orders.view') && !hasPermission('users.view') && !hasPermission('products.view') && !hasPermission('sellers.view')): ?>
            <div class="empty-state" style="padding:50px 20px">
                <i class="fa-solid fa-gauge-high" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>
                Your role doesn't include dashboard summary widgets. Use the sidebar to open the sections assigned to your role.
            </div>
            <?php else: ?>

            <div class="dash-grid dash-grid-top">
        <?php if (hasPermission('orders.view')): ?>
                <div class="dash-card dash-card-wide">
                    <div class="dash-card-head">
                        <h3>Recent Orders</h3>
                        <div style="display:flex;align-items:center;gap:14px">
                            <input type="text" class="dash-search" placeholder="Search orders..." oninput="dashPagers['dashOrdersBody'].filter(this.value)">
                            <span class="dash-link" onclick="goToTab('orders')">View all <i class="fa-solid fa-arrow-right"></i></span>
                        </div>
                    </div>
                    <div style="overflow-x:auto">
                    <table class="dash-table">
                        <thead><tr><th style="width:36px">#</th><th>Order #</th><th>Customer</th><th>Date</th><th>Product(s)</th><th>Coupon</th><th>Discount</th><th>Final Amount</th><th>Status</th></tr></thead>
                        <tbody id="dashOrdersBody">
                        <?php if (empty($recentOrdersDash)): ?>
                            <tr><td colspan="9"><div class="empty-state">No orders yet.</div></td></tr>
                        <?php else: foreach ($recentOrdersDash as $roIdx => $ro):
                            $roSt = $ro['order_status'] ?? '';
                            $roTagClass = $roSt === 'delivered' ? 'in' : ($roSt === 'cancelled' ? 'out' : 'low');
                            $roItems = $ro['items'] ?? [];
                            $roCoupon = $ro['coupon_code'] ?? null;
                            $roDiscount = (float)($ro['discount_amount'] ?? 0);
                            $roFinal = isset($ro['final_amount']) ? (float)$ro['final_amount'] : (float)($ro['total_amount'] ?? 0);
                        ?>
                            <tr data-row="1">
                                <td><?php echo $roIdx + 1; ?></td>
                                <td><strong><?php echo htmlspecialchars($ro['order_number'] ?? ('#'.$ro['id'])); ?></strong></td>
                                <td><?php echo htmlspecialchars($ro['full_name'] ?? 'Customer #'.$ro['id']); ?></td>
                                <td><?php echo htmlspecialchars($ro['created_at'] ?? ''); ?></td>
                                <td>
                                    <?php if (empty($roItems)): ?>
                                        <span style="color:#999">—</span>
                                    <?php else: foreach ($roItems as $rit): ?>
                                        <div style="white-space:nowrap"><?php echo htmlspecialchars($rit['product_name']); ?> <span style="color:#999">× <?php echo (int)$rit['quantity']; ?></span></div>
                                    <?php endforeach; endif; ?>
                                </td>
                                <td><?php echo $roCoupon ? '<span class="tag in">'.htmlspecialchars($roCoupon).'</span>' : '<span style="color:#999">—</span>'; ?></td>
                                <td><?php echo $roDiscount > 0 ? '−₹'.number_format($roDiscount,0) : '<span style="color:#999">—</span>'; ?></td>
                                <td><strong>₹<?php echo number_format($roFinal, 0); ?></strong></td>
                                <td><span class="tag <?php echo $roTagClass; ?>"><?php echo htmlspecialchars(ucfirst($roSt ?: '—')); ?></span></td>
                            </tr>
                        <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                    </div>
                    <div class="dash-table-foot" id="dashOrdersFoot">
                        <span class="foot-text"></span>
                        <div class="dash-pager" id="dashOrdersPager"></div>
                    </div>
                </div>
        <?php endif; ?>

        <?php if (hasPermission('orders.view')): ?>
                <div class="dash-card">
                    <div class="dash-card-head"><h3>Sales Overview (Last 14 Days)</h3><span class="dash-kebab">&#8942;</span></div>
                    <?php if (empty($monthlySales)): ?>
                        <div class="empty-state">No order history yet.</div>
                    <?php else: ?>
                        <canvas id="salesOverviewChart" height="180"></canvas>
                    <?php endif; ?>
                </div>
        <?php endif; ?>
            </div>

            <div class="dash-grid dash-grid-bottom">
        <?php if (hasPermission('users.view')): ?>
                <div class="dash-card">
                    <div class="dash-card-head">
                        <h3>Recent Customers</h3>
                        <span class="dash-link" onclick="goToTab('users')">View all <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                    <?php if (empty($recentCustomers)): ?>
                        <div class="empty-state">No customers yet.</div>
                    <?php else: foreach ($recentCustomers as $rc): ?>
                        <div class="dash-list-item">
                            <div class="dash-avatar"><?php echo strtoupper(substr($rc['full_name'] ?? '?', 0, 1)); ?></div>
                            <div class="dash-list-info">
                                <div class="dash-list-name"><?php echo htmlspecialchars($rc['full_name'] ?? '—'); ?></div>
                                <div class="dash-list-sub"><?php echo htmlspecialchars($rc['district'] ?? $rc['mobile'] ?? ''); ?></div>
                            </div>
                        </div>
                    <?php endforeach; endif; ?>
                </div>
        <?php endif; ?>

        <?php if (hasPermission('products.view')): ?>
                <div class="dash-card">
                    <div class="dash-card-head"><h3>Products By Category</h3><span class="dash-kebab">&#8942;</span></div>
                    <?php if (empty($categoryBreakdown)): ?>
                        <div class="empty-state">No categories yet.</div>
                    <?php else: ?>
                        <div class="donut-wrap">
                            <canvas id="categoryDonutChart" height="180"></canvas>
                        </div>
                        <div class="donut-legend">
                            <?php
                            $donutColors = ['#2E7D32', '#FFC107', '#5A9802', '#0b5e2a', '#F9A825', '#8BC34A'];
                            foreach ($categoryBreakdown as $i => $cb): ?>
                                <div class="legend-row"><span class="legend-dot" style="background:<?php echo $donutColors[$i % count($donutColors)]; ?>"></span><?php echo htmlspecialchars($cb['category']); ?> <b><?php echo (int)$cb['cnt']; ?></b></div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
        <?php endif; ?>

        <?php if (hasPermission('users.view')): ?>
                <div class="dash-card">
                    <div class="dash-card-head"><h3>Users By District</h3><span class="dash-kebab">&#8942;</span></div>
                    <?php if (empty($districtBreakdown)): ?>
                        <div class="empty-state">No users yet.</div>
                    <?php else:
                        $distTotal = array_sum($districtBreakdown);
                        $distColors = ['#2E7D32', '#FFC107', '#5A9802', '#0b5e2a', '#F9A825'];
                    ?>
                        <div class="dist-total"><?php echo $distTotal; ?> users</div>
                        <div class="dist-card-bg">
                        <div class="dist-bar">
                            <?php foreach ($districtBreakdown as $d => $cnt): $pct = round($cnt / $distTotal * 100, 1); ?>
                                <span style="width:<?php echo $pct; ?>%;background:<?php echo $distColors[array_search($d, array_keys($districtBreakdown)) % count($distColors)]; ?>"></span>
                            <?php endforeach; ?>
                        </div>
                        <div class="donut-legend" style="margin-top:14px">
                            <?php foreach ($districtBreakdown as $d => $cnt): ?>
                                <div class="legend-row"><span class="legend-dot" style="background:<?php echo $distColors[array_search($d, array_keys($districtBreakdown)) % count($distColors)]; ?>"></span><?php echo htmlspecialchars($d); ?> <b><?php echo $cnt; ?></b></div>
                            <?php endforeach; ?>
                        </div>
                        </div>
                    <?php endif; ?>
                </div>
        <?php endif; ?>
            </div>

        <?php if (hasPermission('sellers.view')): ?>
            <div class="dash-card" style="margin-top:18px">
                <div class="dash-card-head">
                    <h3>Sellers</h3>
                    <div style="display:flex;align-items:center;gap:14px">
                        <input type="text" class="dash-search" placeholder="Search sellers..." oninput="dashPagers['dashSellersBody'].filter(this.value)">
                        <span class="dash-link" onclick="goToTab('sellers')">View all <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </div>
                <div style="overflow-x:auto">
                <table class="dash-table">
                    <thead><tr><th>Seller Name</th><th>Products</th><th>Status</th></tr></thead>
                    <tbody id="dashSellersBody">
                    <?php if (empty($topSellersDash)): ?>
                        <tr><td colspan="3"><div class="empty-state">No sellers yet.</div></td></tr>
                    <?php else: foreach ($topSellersDash as $ts): ?>
                        <tr data-row="1">
                            <td><strong><?php echo htmlspecialchars($ts['name'] ?: '—'); ?></strong></td>
                            <td><?php echo (int)($ts['product_count'] ?? 0); ?></td>
                            <td><span class="tag in">Active</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
                <div class="dash-table-foot" id="dashSellersFoot">
                    <span class="foot-text"></span>
                    <div class="dash-pager" id="dashSellersPager"></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (hasPermission('products.view')): ?>
            <div class="dash-card" style="margin-top:18px">
                <div class="dash-card-head">
                    <h3>Top Products</h3>
                    <div style="display:flex;align-items:center;gap:14px">
                        <input type="text" class="dash-search" placeholder="Search products..." oninput="dashPagers['dashProductsBody'].filter(this.value)">
                        <span class="dash-link" onclick="goToTab('products')">View all <i class="fa-solid fa-arrow-right"></i></span>
                    </div>
                </div>
                <div style="overflow-x:auto">
                <table class="dash-table">
                    <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Seller</th><th>Price</th><th>Stock</th></tr></thead>
                    <tbody id="dashProductsBody">
                    <?php if (empty($topProductsDash)): ?>
                        <tr><td colspan="6"><div class="empty-state">No products yet.</div></td></tr>
                    <?php else: foreach ($topProductsDash as $tp):
                        $tpStockTag = $tp['stock'] <= 0 ? 'out' : ($tp['stock'] <= 10 ? 'low' : 'in');
                        $tpStockLabel = $tp['stock'] <= 0 ? 'Out' : ($tp['stock'] <= 10 ? 'Low' : 'In Stock');
                    ?>
                        <tr data-row="1">
                            <td><img class="prod-thumb" src="../<?php echo htmlspecialchars($tp['image']); ?>" onerror="this.src='../assets/images/products/default.jpg'"></td>
                            <td><strong><?php echo htmlspecialchars($tp['name']); ?></strong></td>
                            <td><?php echo htmlspecialchars($tp['category']); ?></td>
                            <td><?php echo htmlspecialchars($tp['farmer_name'] ?: '—'); ?></td>
                            <td>₹<?php echo number_format($tp['price'], 0); ?></td>
                            <td><span class="tag <?php echo $tpStockTag; ?>"><?php echo $tpStockLabel; ?> (<?php echo (int)$tp['stock']; ?>)</span></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
                </div>
                <div class="dash-table-foot" id="dashProductsFoot">
                    <span class="foot-text"></span>
                    <div class="dash-pager" id="dashProductsPager"></div>
                </div>
            </div>
        <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- PRODUCTS TAB -->
        <div class="panel" id="tab-products">
        <?php if (canViewModule('products')): ?>
            <div class="panel-head">
                <h2>Products (<?php echo count($products); ?>)</h2>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <button type="button" class="btn-secondary" id="productsPendingFilterBtn" onclick="toggleProductsFilter()">
                        <i class="fa-solid fa-filter"></i> <span id="productsPendingFilterBtnText">Show Pending Only</span>
                    </button>
                    <button type="button" class="btn-secondary" id="productsStockFilterBtn" onclick="toggleProductsStockFilter()">
                        <i class="fa-solid fa-box-open"></i> <span id="productsStockFilterBtnText">Show Out of Stock Only</span>
                    </button>
                    <button class="btn-primary" onclick="openProductModal()"><i class="fa-solid fa-plus"></i> Add Product</button>
                </div>
            </div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Image</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Seller</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="productsTableBody">
                <?php if (empty($products)): ?>
                    <tr><td colspan="8"><div class="empty-state">No products yet. Click "Add Product" to create your first one.</div></td></tr>
                <?php else: foreach ($products as $p):
                    $stockTag = $p['stock'] <= 0 ? 'out' : ($p['stock'] <= 10 ? 'low' : 'in');
                    $stockLabel = $p['stock'] <= 0 ? 'Out' : ($p['stock'] <= 10 ? 'Low' : 'In Stock');
                    $pApproval = $p['approval_status'] ?? 'approved';
                    $pIsFarmerListing = !empty($p['added_by_user_id']);
                ?>
                    <tr data-pending="<?php echo $pApproval === 'pending' ? '1' : '0'; ?>" data-outofstock="<?php echo ((int)$p['stock']) <= 0 ? '1' : '0'; ?>"<?php echo empty($p['is_active']) ? ' style="opacity:.55;"' : ''; ?>>
                        <td><img class="prod-thumb" src="../<?php echo htmlspecialchars($p['image']); ?>" onerror="this.src='../assets/images/products/default.jpg'" style="cursor:pointer" onclick='viewProductDetails(<?php echo json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'></td>
                        <td><strong class="clickable-name" onclick='viewProductDetails(<?php echo json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><?php echo htmlspecialchars($p['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($p['category']); ?></td>
                        <td>₹<?php echo number_format($p['price'],0); ?><?php if($p['discount_price']): ?><br><span style="text-decoration:line-through;color:#999;font-size:11px">₹<?php echo number_format($p['discount_price'],0); ?></span><?php endif; ?></td>
                        <td><span class="tag <?php echo $stockTag; ?>"><?php echo $stockLabel; ?> (<?php echo (int)$p['stock']; ?>)</span></td>
                        <td>
                            <?php echo htmlspecialchars($p['farmer_name'] ?: '—'); ?>
                            <?php if ($pIsFarmerListing): ?><br><span style="color:#999;font-size:11px">Farmer listing · <?php echo number_format((float)($p['commission_percent'] ?? 0), 2); ?>% commission</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if (empty($p['is_active'])): ?>
                                <span class="tag out">Deleted</span>
                            <?php elseif ($pApproval === 'pending'): ?>
                                <span class="tag low">Pending</span>
                            <?php elseif ($pApproval === 'rejected'): ?>
                                <span class="tag out">Rejected</span>
                            <?php else: ?>
                                <span class="tag in">Approved</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if (empty($p['is_active'])): ?>
                                        <button class="menu-success" onclick="restoreProduct(<?php echo (int)$p['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                        <button onclick='viewProductDetails(<?php echo json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-eye"></i> View Details</button>
                                        <button class="menu-danger" onclick="permanentDeleteProduct(<?php echo (int)$p['id']; ?>, <?php echo json_encode($p['name'] ?? '', JSON_HEX_APOS|JSON_HEX_QUOT); ?>)"><i class="fa-solid fa-trash-can"></i> Permanent Delete</button>
                                    <?php else: ?>
                                        <?php if ($pApproval === 'pending'): ?>
                                            <button class="menu-success" onclick="approveProduct(<?php echo (int)$p['id']; ?>)"><i class="fa-solid fa-check"></i> Approve</button>
                                            <button class="menu-danger" onclick="rejectProduct(<?php echo (int)$p['id']; ?>)"><i class="fa-solid fa-ban"></i> Reject</button>
                                        <?php endif; ?>
                                        <button onclick='viewProductDetails(<?php echo json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-eye"></i> View Details</button>
                                        <button onclick='openProductModal(<?php echo json_encode($p, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                        <button class="menu-danger" onclick="deleteProduct(<?php echo (int)$p['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- ORDERS TAB -->
        <div class="panel" id="tab-orders">
        <?php if (canViewModule('orders')): ?>
            <div class="panel-head">
                <h2>Orders (<?php echo count($orders); ?>)</h2>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <input type="text" class="dash-search" id="ordersSearchBox" placeholder="Search by Order ID..." oninput="filterOrdersTable()">
                    <select class="btn-secondary" id="ordersStatusFilter" onchange="filterOrdersTable()" style="padding:7px 10px">
                        <option value="">All Statuses</option>
                        <option value="placed">Placed</option>
                        <option value="confirmed">Confirmed</option>
                        <option value="packed">Packed</option>
                        <option value="shipped">Shipped</option>
                        <option value="delivered">Delivered</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="returned">Returned</option>
                        <option value="refunded">Refunded</option>
                    </select>
                    <button type="button" class="btn-secondary" id="pendingFilterBtn" onclick="toggleOrdersFilter()">
                        <i class="fa-solid fa-filter"></i> <span id="pendingFilterBtnText">Show Pending Only</span>
                    </button>
                </div>
            </div>
            <div class="note-box">Order status changes here update instantly for the seller dashboard and the customer's order tracking view (single shared database status).</div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Order #</th><th>Date</th><th>Product(s)</th><th>Subtotal</th><th>Coupon</th><th>Discount</th><th>Final Amount</th><th>Delivery Location</th><th>Payment</th><th>Status</th></tr></thead>
                <tbody id="ordersTableBody">
                <?php if (empty($orders)): ?>
                    <tr><td colspan="10"><div class="empty-state">No orders found yet (or the <code>orders</code> table isn't set up).</div></td></tr>
                <?php else: foreach ($orders as $o):
                    $st = $o['order_status'] ?? '';
                    $isPending = !in_array($st, ['delivered','cancelled','returned','refunded'], true);
                    $items = $o['items'] ?? [];
                    $couponCode = $o['coupon_code'] ?? null;
                    $discountAmt = (float)($o['discount_amount'] ?? 0);
                    $finalAmt = isset($o['final_amount']) ? (float)$o['final_amount'] : (float)($o['total_amount'] ?? 0);
                ?>
                    <tr data-pending="<?php echo $isPending ? '1' : '0'; ?>" data-status="<?php echo htmlspecialchars($st); ?>" data-order-number="<?php echo htmlspecialchars(strtolower($o['order_number'] ?? $o['id'])); ?>" style="cursor:pointer" onclick='viewOrderDetails(<?php echo json_encode($o, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'>
                        <td><strong><?php echo htmlspecialchars($o['order_number'] ?? ('#'.$o['id'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($o['created_at'] ?? ''); ?></td>
                        <td>
                            <?php if (empty($items)): ?>
                                <span style="color:#999">—</span>
                            <?php else: foreach ($items as $it): ?>
                                <div style="white-space:nowrap"><?php echo htmlspecialchars($it['product_name']); ?> <span style="color:#999">× <?php echo (int)$it['quantity']; ?></span></div>
                            <?php endforeach; endif; ?>
                        </td>
                        <td>₹<?php echo number_format($o['total_amount'] ?? 0,0); ?></td>
                        <td><?php echo $couponCode ? '<span class="tag in">'.htmlspecialchars($couponCode).'</span>' : '<span style="color:#999">—</span>'; ?></td>
                        <td><?php echo $discountAmt > 0 ? '−₹'.number_format($discountAmt,0) : '<span style="color:#999">—</span>'; ?></td>
                        <td><strong>₹<?php echo number_format($finalAmt,0); ?></strong></td>
                        <td style="max-width:220px;white-space:normal">
                            <div><strong><?php echo htmlspecialchars($o['delivery_name'] ?? ''); ?></strong> <?php if(!empty($o['delivery_mobile'])): ?><span style="color:#999">· <?php echo htmlspecialchars($o['delivery_mobile']); ?></span><?php endif; ?></div>
                            <div style="color:#666;font-size:12px"><?php echo htmlspecialchars($o['delivery_address'] ?? '—'); ?></div>
                        </td>
                        <td><?php echo htmlspecialchars(strtoupper($o['payment_mode'] ?? '')); ?></td>
                        <td onclick="event.stopPropagation()">
                            <select class="status-select" onchange="updateOrderStatus(<?php echo (int)$o['id']; ?>, this.value, this)" data-prev="<?php echo htmlspecialchars($st); ?>">
                                <?php foreach (['placed'=>'Placed','confirmed'=>'Confirmed','packed'=>'Packed','shipped'=>'Shipped','delivered'=>'Delivered','cancelled'=>'Cancelled','returned'=>'Returned','refunded'=>'Refunded'] as $val=>$label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo (($o['order_status'] ?? '')===$val)?'selected':''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>


<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- EQUIPMENT TAB -->
        <div class="panel" id="tab-equipment">
        <?php if (canViewModule('equipment')): ?>
            <div class="panel-head">
                <h2>Equipment (<?php echo count($equipment); ?>)</h2>
                <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                    <button type="button" class="btn-secondary" id="equipmentPendingFilterBtn" onclick="toggleEquipmentFilter()">
                        <i class="fa-solid fa-filter"></i> <span id="equipmentPendingFilterBtnText">Show Pending Only</span>
                    </button>
                    <button class="btn-primary" onclick="openEquipmentModal()"><i class="fa-solid fa-plus"></i> Add Equipment</button>
                </div>
            </div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Image</th><th>Name</th><th>Type</th><th>Rent/Day</th><th>Owner</th><th>City</th><th>Available</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="equipmentTableBody">
                <?php if (empty($equipment)): ?>
                    <tr><td colspan="9"><div class="empty-state">No equipment listed yet. Click "Add Equipment" to create the first one.</div></td></tr>
                <?php else: foreach ($equipment as $e):
                    $eApproval = $e['approval_status'] ?? 'approved';
                    $eIsFarmerListing = !empty($e['owner_user_id']);
                ?>
                    <tr data-pending="<?php echo $eApproval === 'pending' ? '1' : '0'; ?>">
                        <td><img class="prod-thumb" src="../<?php echo htmlspecialchars($e['image'] ?: 'assets/images/equipment.png'); ?>" onerror="this.src='../assets/images/equipment.png'" style="cursor:pointer" onclick='viewEquipmentDetails(<?php echo json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'></td>
                        <td><strong class="clickable-name" onclick='viewEquipmentDetails(<?php echo json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><?php echo htmlspecialchars($e['name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($e['type']); ?></td>
                        <td>₹<?php echo number_format($e['rent_per_day'],0); ?>/day</td>
                        <td>
                            <?php echo htmlspecialchars($e['owner_name'] ?: '—'); ?>
                            <?php if ($eIsFarmerListing): ?><br><span style="color:#999;font-size:11px">Farmer listing · <?php echo number_format((float)($e['commission_percent'] ?? 0), 2); ?>% commission</span><?php endif; ?>
                        </td>
                        <td><?php echo htmlspecialchars($e['city_name'] ?: '—'); ?></td>
                        <td>
                        <?php
                            $eIsBookedNow = isset($currentlyBookedEquipmentIds[(int)$e['id']]);
                            if (!$e['availability']) {
                                echo '<span class="tag out">Removed</span>';
                            } elseif ($eIsBookedNow) {
                                echo '<span class="tag out">Not Available</span>';
                            } else {
                                echo '<span class="tag in">Yes</span>';
                            }
                        ?>
                        </td>
                        <td>
                            <?php if ($eApproval === 'pending'): ?>
                                <span class="tag low">Pending</span>
                            <?php elseif ($eApproval === 'rejected'): ?>
                                <span class="tag out">Rejected</span>
                            <?php else: ?>
                                <span class="tag in">Approved</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($eApproval === 'pending'): ?>
                                        <button class="menu-success" onclick="approveEquipment(<?php echo (int)$e['id']; ?>)"><i class="fa-solid fa-check"></i> Approve</button>
                                        <button class="menu-danger" onclick="rejectEquipment(<?php echo (int)$e['id']; ?>)"><i class="fa-solid fa-ban"></i> Reject</button>
                                    <?php endif; ?>
                                    <button onclick='viewEquipmentDetails(<?php echo json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-eye"></i> View Details</button>
                                    <button onclick='openEquipmentModal(<?php echo json_encode($e, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <?php if (!$e['availability']): ?>
                                        <button class="menu-success" onclick="restoreEquipment(<?php echo (int)$e['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore to website</button>
                                        <button class="menu-danger" onclick="hardDeleteEquipment(<?php echo (int)$e['id']; ?>)"><i class="fa-solid fa-trash-can"></i> Delete Permanently</button>
                                    <?php else: ?>
                                        <button class="menu-danger" onclick="deleteEquipment(<?php echo (int)$e['id']; ?>)"><i class="fa-solid fa-trash"></i> Remove from website</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- BOOKINGS TAB -->
        <div class="panel" id="tab-bookings">
        <?php if (canViewModule('rental_bookings')): ?>
            <div class="panel-head"><h2>Rental Bookings (<?php echo count($bookings); ?>)</h2></div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Booking #</th><th>Equipment</th><th>PN</th><th>Serial No</th><th>Customer</th><th>Dates</th><th>Hours</th><th>Amount</th><th>Status</th><th>Payment</th></tr></thead>
                <tbody>
                <?php if (empty($bookings)): ?>
                    <tr><td colspan="10"><div class="empty-state">No bookings yet (or the <code>equipment_bookings</code> table isn't set up).</div></td></tr>
                <?php else: foreach ($bookings as $b): ?>
                    <tr style="cursor:pointer" onclick='viewBookingDetails(<?php echo json_encode($b, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'>
                        <td><strong><?php echo htmlspecialchars($b['booking_number'] ?? ('#'.$b['id'])); ?></strong></td>
                        <td><?php echo htmlspecialchars($b['equipment_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($b['pn'] ?? '') ?: '<span style="color:#999">—</span>'; ?></td>
                        <td><?php echo htmlspecialchars($b['serial_no'] ?? '') ?: '<span style="color:#999">—</span>'; ?></td>
                        <td><?php echo htmlspecialchars(($b['contact_name'] ?? '').' · '.($b['contact_mobile'] ?? '')); ?></td>
                        <td><?php echo htmlspecialchars(($b['from_date'] ?? '').' → '.($b['to_date'] ?? '')); ?></td>
                        <td><?php echo isset($b['total_hours']) && $b['total_hours'] !== null ? (int)$b['total_hours'].' hr' : '<span style="color:#999">—</span>'; ?></td>
                        <td>₹<?php echo number_format($b['total_amount'] ?? 0,0); ?></td>
                        <td onclick="event.stopPropagation()">
                            <select class="status-select" onchange="updateBookingStatus(<?php echo (int)$b['id']; ?>, this.value)">
                                <?php foreach (['pending','confirmed','on_the_way','completed','cancelled'] as $s): ?>
                                    <option value="<?php echo $s; ?>" <?php echo (($b['status'] ?? '')===$s)?'selected':''; ?>><?php echo ucfirst(str_replace('_',' ',$s)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td onclick="event.stopPropagation()">
                            <select class="status-select" onchange="updateBookingPaymentStatus(<?php echo (int)$b['id']; ?>, this.value)">
                                <?php
                                $bPayStatus = $b['payment_status'] ?? 'pending';
                                if ($bPayStatus === 'cod') { $bPayStatus = 'pending'; } // COD shows as Pending in admin until it's actually paid
                                foreach (['pending'=>'Pending','paid'=>'Paid','failed'=>'Failed'] as $ps => $psLabel): ?>
                                    <option value="<?php echo $ps; ?>" <?php echo ($bPayStatus===$ps)?'selected':''; ?>><?php echo $psLabel; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- KRISHI BAZAAR TAB -->
        <div class="panel" id="tab-bazaar">
        <?php if (canViewModule('bazaar')): ?>
            <div class="panel-head">
                <h2>Krishi Bazaar — Mandi Prices (<?php echo count($bazaarPrices); ?>)</h2>
                <button class="btn-primary" onclick="openBazaarModal()"><i class="fa-solid fa-plus"></i> Add Price Entry</button>
            </div>
            <div class="note-box">Daily mandi/market crop prices shown to farmers on the Krishi Bazaar page. Add one row per crop per market per day. "Live Rates" shows the same government mandi feed the storefront pulls in real time (read-only).</div>

            <div class="subtab-bar">
                <button type="button" class="subtab-btn active" id="bazaarSubtabBtn-manual" onclick="showBazaarSubtab('manual')">Manual Entries</button>
                <button type="button" class="subtab-btn" id="bazaarSubtabBtn-live" onclick="showBazaarSubtab('live')">Live Rates <i class="fa-solid fa-satellite-dish" style="font-size:11px;opacity:.7"></i></button>
            </div>

            <div id="bazaarSubtab-manual">
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Crop</th><th>Market / Mandi</th><th>District</th><th>Min ₹</th><th>Max ₹</th><th>Modal ₹</th><th>Unit</th><th>Date</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($bazaarPrices)): ?>
                    <tr><td colspan="9"><div class="empty-state">No price entries yet (or the <code>krishi_bazaar</code> table isn't set up). Click "Add Price Entry" to create the first one.</div></td></tr>
                <?php else: foreach ($bazaarPrices as $bp): $bpIsDeleted = !empty($bp['deleted_at']); ?>
                    <tr<?php echo $bpIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><strong class="clickable-name" onclick='viewBazaarDetails(<?php echo json_encode($bp, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><?php echo htmlspecialchars($bp['crop_name']); ?></strong><?php if (!empty($bp['crop_name_mr'])): ?><br><span style="color:var(--muted);font-size:12px"><?php echo htmlspecialchars($bp['crop_name_mr']); ?></span><?php endif; ?><?php if ($bpIsDeleted): ?><br><span class="tag out">Deleted</span><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($bp['market_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($bp['district'] ?? '—'); ?></td>
                        <td>₹<?php echo number_format($bp['min_price'] ?? 0,0); ?></td>
                        <td>₹<?php echo number_format($bp['max_price'] ?? 0,0); ?></td>
                        <td>₹<?php echo number_format($bp['modal_price'] ?? 0,0); ?></td>
                        <td><?php echo htmlspecialchars($bp['unit'] ?? 'quintal'); ?></td>
                        <td><?php echo htmlspecialchars($bp['price_date'] ?? ''); ?></td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($bpIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreBazaarPrice(<?php echo (int)$bp['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button onclick='viewBazaarDetails(<?php echo json_encode($bp, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-eye"></i> View Details</button>
                                    <button onclick='openBazaarModal(<?php echo json_encode($bp, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <button class="menu-danger" onclick="deleteBazaarPrice(<?php echo (int)$bp['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
            </div>

            <div id="bazaarSubtab-live" style="display:none">
                <div class="panel-head" style="margin-bottom:12px">
                    <span id="bazaarLiveStatus" style="font-size:12.5px;color:var(--muted)">Loading live rates…</span>
                    <button type="button" class="btn-secondary" onclick="loadBazaarLiveRates(true)"><i class="fa-solid fa-rotate"></i> Refresh</button>
                </div>
                <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:14px">
                    <div class="kb-dropdown" id="districtDropdownWrap">
                        <button type="button" class="kb-dropdown-btn" id="districtDropdownBtn" onclick="toggleBazaarDropdown('district')">
                            <span id="districtDropdownLabel">All Districts</span> <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="kb-dropdown-menu" id="districtDropdownMenu"></div>
                    </div>
                    <div class="kb-dropdown" id="cropDropdownWrap">
                        <button type="button" class="kb-dropdown-btn" id="cropDropdownBtn" onclick="toggleBazaarDropdown('crop')">
                            <span id="cropDropdownLabel">All Crops</span> <i class="fa-solid fa-chevron-down"></i>
                        </button>
                        <div class="kb-dropdown-menu" id="cropDropdownMenu"></div>
                    </div>
                    <button type="button" class="btn-secondary" onclick="clearBazaarLiveFilters()">Clear Filters</button>
                </div>
                <div style="overflow-x:auto">
                <table>
                    <thead><tr><th>Crop</th><th>Market / Mandi</th><th>District</th><th>Min ₹</th><th>Max ₹</th><th>Modal ₹</th><th>Unit</th><th>Source</th></tr></thead>
                    <tbody id="bazaarLiveTableBody">
                        <tr><td colspan="8"><div class="empty-state">Loading…</div></td></tr>
                    </tbody>
                </table>
                </div>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- ADVISORY TAB -->
        <div class="panel" id="tab-advisory">
        <?php if (canViewModule('advisory')): ?>
            <div class="panel-head">
                <h2>Advisory — Farming Tips (<?php echo count($advisoryPosts); ?>)</h2>
                <button class="btn-primary" onclick="openAdvisoryModal()"><i class="fa-solid fa-plus"></i> Add Advisory</button>
            </div>
            <div class="note-box">Crop advisory / farming tips shown to farmers on the Advisory page.</div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Image</th><th>Title</th><th>Crop</th><th>Posted</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($advisoryPosts)): ?>
                    <tr><td colspan="5"><div class="empty-state">No advisory posts yet (or the <code>advisory</code> table isn't set up). Click "Add Advisory" to create the first one.</div></td></tr>
                <?php else: foreach ($advisoryPosts as $ap): $apIsDeleted = !empty($ap['deleted_at']); ?>
                    <tr<?php echo $apIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><img class="prod-thumb" src="../<?php echo htmlspecialchars($ap['image'] ?: 'assets/images/advisory.png'); ?>" onerror="this.src='../assets/images/advisory.png'"></td>
                        <td><strong><?php echo htmlspecialchars($ap['title']); ?></strong><?php if (!empty($ap['title_mr'])): ?><br><span style="color:var(--muted);font-size:12px"><?php echo htmlspecialchars($ap['title_mr']); ?></span><?php endif; ?><?php if ($apIsDeleted): ?><br><span class="tag out">Deleted</span><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($ap['crop'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($ap['created_at'] ?? ''); ?></td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($apIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreAdvisory(<?php echo (int)$ap['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button onclick='openAdvisoryModal(<?php echo json_encode($ap, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <button class="menu-danger" onclick="deleteAdvisory(<?php echo (int)$ap['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- COMMUNITY TAB -->
        <div class="panel" id="tab-community">
        <?php if (canViewModule('community')): ?>
            <div class="panel-head"><h2>Agri-Connect Posts (<?php echo count($communityPosts); ?>)</h2></div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Author</th><th>Category</th><th>Post</th><th>Likes</th><th>Approved</th><th>Pinned</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($communityPosts)): ?>
                    <tr><td colspan="7"><div class="empty-state">No posts yet (or the <code>community_posts</code> table isn't set up).</div></td></tr>
                <?php else: foreach ($communityPosts as $p):
                    $preview = mb_strimwidth(strip_tags($p['body'] ?? ''), 0, 90, '...');
                    $pIsDeleted = !empty($p['deleted_at']);
                ?>
                    <tr<?php echo $pIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><?php echo htmlspecialchars($p['full_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($p['category'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(($p['title'] ?? '') . ' — ' . $preview); ?></td>
                        <td><?php echo (int)($p['likes_count'] ?? 0); ?></td>
                        <td><?php if ($pIsDeleted): ?><span class="tag out">Deleted</span><?php else: ?><span class="tag <?php echo !empty($p['is_approved']) ? 'in' : 'low'; ?>"><?php echo !empty($p['is_approved']) ? 'Yes' : 'No'; ?></span><?php endif; ?></td>
                        <td><span class="tag <?php echo !empty($p['is_pinned']) ? 'in' : 'low'; ?>"><?php echo !empty($p['is_pinned']) ? 'Yes' : 'No'; ?></span></td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($pIsDeleted): ?>
                                    <button class="menu-success" onclick="restorePost(<?php echo (int)$p['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button onclick="togglePost(<?php echo (int)$p['id']; ?>, 'approve')"><i class="fa-solid fa-check"></i> Toggle Approve</button>
                                    <button onclick="togglePost(<?php echo (int)$p['id']; ?>, 'pin')"><i class="fa-solid fa-thumbtack"></i> Toggle Pin</button>
                                    <button class="menu-danger" onclick="deletePost(<?php echo (int)$p['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>

            <div class="panel-head" style="margin-top:26px"><h2>Comments (<?php echo count($communityComments); ?>)</h2>
                <button class="btn-primary" onclick="openAddCommentModal()"><i class="fa-solid fa-plus"></i> Add Comment</button>
            </div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Author</th><th>On Post</th><th>Comment</th><th>Approved</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($communityComments)): ?>
                    <tr><td colspan="5"><div class="empty-state">No comments yet.</div></td></tr>
                <?php else: foreach ($communityComments as $c): $cIsDeleted = !empty($c['deleted_at']); ?>
                    <tr<?php echo $cIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><?php echo htmlspecialchars($c['author_name'] ?? $c['full_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($c['post_title'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars(mb_strimwidth(strip_tags($c['body'] ?? ''), 0, 90, '...')); ?></td>
                        <td><?php if ($cIsDeleted): ?><span class="tag out">Deleted</span><?php else: ?><span class="tag <?php echo !empty($c['is_approved']) ? 'in' : 'low'; ?>"><?php echo !empty($c['is_approved']) ? 'Yes' : 'No'; ?></span><?php endif; ?></td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($cIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreComment(<?php echo (int)$c['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button onclick="toggleComment(<?php echo (int)$c['id']; ?>)"><i class="fa-solid fa-check"></i> Toggle Approve</button>
                                    <button class="menu-danger" onclick="deleteComment(<?php echo (int)$c['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- CONTACT MESSAGES TAB -->
        <div class="panel" id="tab-messages">
        <?php if (hasPermission('support.view')): ?>
            <div class="panel-head"><h2>Contact Messages (<?php echo count($contactMessages); ?>)</h2>
                <button class="btn-primary" onclick="openAddMessageModal()"><i class="fa-solid fa-plus"></i> Add Message</button>
            </div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Ticket #</th><th>Name</th><th>Phone</th><th>Subject</th><th>Message</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($contactMessages)): ?>
                    <tr><td colspan="7"><div class="empty-state">No contact messages yet.</div></td></tr>
                <?php else: foreach ($contactMessages as $m): $mIsDeleted = !empty($m['deleted_at']); ?>
                    <tr<?php echo $mIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><?php echo htmlspecialchars($m['ticket_number']); ?><?php if ($mIsDeleted): ?><br><span class="tag out">Deleted</span><?php endif; ?></td>
                        <td><?php echo htmlspecialchars($m['name']); ?></td>
                        <td><?php echo htmlspecialchars($m['phone']); ?></td>
                        <td><?php echo htmlspecialchars($m['subject']); ?></td>
                        <td style="max-width:280px"><?php echo htmlspecialchars(mb_strimwidth($m['message'], 0, 100, '...')); ?></td>
                        <td>
                            <select class="status-select" onchange="updateMessageStatus(<?php echo (int)$m['id']; ?>, this.value)" <?php echo $mIsDeleted ? 'disabled' : ''; ?>>
                                <?php foreach (['new'=>'New','read'=>'Viewed','replied'=>'Replied','closed'=>'Closed'] as $val=>$label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo (($m['status'] ?? '')===$val)?'selected':''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($mIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreContactMessage(<?php echo (int)$m['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button class="menu-danger" onclick="deleteContactMessage(<?php echo (int)$m['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- FEEDBACK TAB -->
        <div class="panel" id="tab-feedback">
        <?php if (hasPermission('feedback.manage')): ?>
            <div class="panel-head"><h2>Feedback (<?php echo count($feedbackList); ?>)</h2>
                <button class="btn-primary" onclick="openAddFeedbackModal()"><i class="fa-solid fa-plus"></i> Add Feedback</button>
            </div>
            <div class="note-box">Submitted from the Feedback form in the site footer. Run <code>add_feedback_newsletter.sql</code> once in phpMyAdmin if this table shows empty and you expect entries.</div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Date</th><th>Submitted By</th><th>Rating</th><th>Message</th><th>Page</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($feedbackList)): ?>
                    <tr><td colspan="7"><div class="empty-state">No feedback submitted yet.</div></td></tr>
                <?php else: foreach ($feedbackList as $f): $fIsDeleted = !empty($f['deleted_at']); ?>
                    <tr<?php echo $fIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><?php echo htmlspecialchars($f['created_at'] ?? ''); ?><?php if ($fIsDeleted): ?><br><span class="tag out">Deleted</span><?php endif; ?></td>
                        <td><strong><?php echo htmlspecialchars($f['submitter_name'] ?? 'Guest (not logged in)'); ?></strong></td>
                        <td><?php echo !empty($f['rating']) ? '★ ' . (int)$f['rating'] : '—'; ?></td>
                        <td style="max-width:320px"><?php echo htmlspecialchars($f['message']); ?></td>
                        <td><?php echo htmlspecialchars($f['page'] ?? ''); ?></td>
                        <td>
                            <select class="status-select" onchange="updateFeedbackStatus(<?php echo (int)$f['id']; ?>, this.value)" <?php echo $fIsDeleted ? 'disabled' : ''; ?>>
                                <?php foreach (['new'=>'New','read'=>'Viewed','resolved'=>'Resolved'] as $val=>$label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo (($f['status'] ?? '')===$val)?'selected':''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($fIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreFeedback(<?php echo (int)$f['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button class="menu-danger" onclick="deleteFeedback(<?php echo (int)$f['id']; ?>)"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>

            <div class="panel-head" style="margin-top:28px"><h2>Newsletter Subscribers (<?php echo count($newsletterSubscribers); ?>)</h2></div>
            <div style="overflow-x:auto">
            <table style="table-layout:fixed">
                <thead><tr>
                    <th style="width:45%">Email</th>
                    <th style="width:20%">Status</th>
                    <th style="width:25%">Subscribed On</th>
                    <th style="width:10%"></th>
                </tr></thead>
                <tbody>
                <?php if (empty($newsletterSubscribers)): ?>
                    <tr><td colspan="4"><div class="empty-state">No subscribers yet.</div></td></tr>
                <?php else: foreach ($newsletterSubscribers as $n): $nIsDeleted = !empty($n['deleted_at']); ?>
                    <tr<?php echo $nIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?php echo htmlspecialchars($n['email']); ?></td>
                        <td><?php echo $nIsDeleted ? '<span class="tag out">Deleted</span>' : htmlspecialchars(ucfirst($n['status'] ?? 'active')); ?></td>
                        <td><?php echo htmlspecialchars($n['created_at'] ?? ''); ?></td>
                        <td>
                            <?php if ($nIsDeleted): ?>
                            <button class="icon-btn" title="Restore" style="color:#2E7D32" onclick="restoreNewsletterSubscriber(<?php echo (int)$n['id']; ?>)"><i class="fa-solid fa-rotate-left"></i></button>
                            <?php else: ?>
                            <button class="icon-btn danger" title="Remove" onclick="deleteNewsletterSubscriber(<?php echo (int)$n['id']; ?>)"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- USERS TAB -->
        <div class="panel" id="tab-users">
        <?php if (canViewModule('users')): ?>
            <div class="panel-head"><h2>Users (<?php echo count($allUsers); ?>)</h2></div>
            <div class="note-box">Changing a role here takes effect immediately — a user set to "admin" can log into <code>/admin/login.php</code> using their own mobile number and password.</div>
            <div style="overflow-x:auto">
            <table>
                <thead><tr><th>Name</th><th>Mobile</th><th>Email</th><th>District</th><th>Role</th><th>Joined</th><th>Actions</th></tr></thead>
                <tbody>
                <?php if (empty($allUsers)): ?>
                    <tr><td colspan="7"><div class="empty-state">No users found.</div></td></tr>
                <?php else: foreach ($allUsers as $u): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($u['full_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($u['mobile']); ?></td>
                        <td><?php echo htmlspecialchars($u['email'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($u['district'] ?? '—'); ?></td>
                        <td>
                            <select class="status-select" onchange="updateUserRole(<?php echo (int)$u['id']; ?>, this.value)" <?php echo ((int)$u['id'] === (int)($_SESSION['admin_id'] ?? 0)) ? 'disabled title="You cannot change your own role here"' : ''; ?>>
                                <?php foreach (['farmer'=>'Farmer','seller'=>'Seller','admin'=>'Admin'] as $val=>$label): ?>
                                    <option value="<?php echo $val; ?>" <?php echo (($u['role'] ?? 'farmer')===$val)?'selected':''; ?>><?php echo $label; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                        <td><?php echo htmlspecialchars($u['created_at'] ?? ''); ?></td>
                        <td>
                            <?php if ((int)$u['id'] === (int)($_SESSION['admin_id'] ?? 0)): ?>
                                <button class="icon-btn" title="You can't delete your own account" disabled style="opacity:.4;cursor:not-allowed"><i class="fa-solid fa-trash"></i></button>
                            <?php else: ?>
                                <button class="icon-btn danger" title="Delete" onclick="deleteUser(<?php echo (int)$u['id']; ?>, '<?php echo htmlspecialchars(addslashes($u['full_name']), ENT_QUOTES); ?>')"><i class="fa-solid fa-trash"></i></button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
            </div>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- SELLERS TAB -->
        <div class="panel" id="tab-sellers">
        <?php if (canViewModule('sellers')): ?>
            <div class="panel-head">
                <h2>Sellers (<?php echo count($sellers); ?>)</h2>
                <button class="btn-primary" onclick="openSellerModal()"><i class="fa-solid fa-plus"></i> Add Seller</button>
            </div>
            <?php if (!$sellersTableExists): ?>
            <div class="note-box">Showing sellers derived from product listings (read-only). Run <code>add_sellers_coupons.sql</code> once in phpMyAdmin to enable adding proper seller profiles (mobile, email, village, verified badge).</div>
            <?php endif; ?>
            <table>
                <thead><tr><th>Name</th><?php if ($sellersTableExists): ?><th>Mobile</th><th>Village / City</th><th>Verified</th><?php endif; ?><th>Products Listed</th><?php if ($sellersTableExists): ?><th>Actions</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if (empty($sellers)): ?>
                    <tr><td colspan="6"><div class="empty-state">No sellers yet.</div></td></tr>
                <?php else: foreach ($sellers as $s): $sIsDeleted = $sellersTableExists && !empty($s['deleted_at']); ?>
                    <tr<?php echo $sIsDeleted ? ' style="opacity:.55;"' : ''; ?>>
                        <td><strong<?php echo $sellersTableExists ? ' class="clickable-name" onclick=\'viewSellerDetails('.json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT).')\'' : ''; ?>><?php echo htmlspecialchars($s['name'] ?: '—'); ?></strong></td>
                        <?php if ($sellersTableExists): ?>
                        <td><?php echo htmlspecialchars($s['mobile'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars(trim(($s['village'] ?? '') . ($s['village'] && $s['city'] ? ', ' : '') . ($s['city'] ?? '')) ?: '—'); ?></td>
                        <td><?php if ($sIsDeleted): ?><span class="tag out">Deleted</span><?php else: ?><span class="tag <?php echo $s['verified'] ? 'in' : 'out'; ?>"><?php echo $s['verified'] ? 'Yes' : 'No'; ?></span><?php endif; ?></td>
                        <?php endif; ?>
                        <td><?php echo (int)$s['product_count']; ?></td>
                        <?php if ($sellersTableExists): ?>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($sIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreSeller(<?php echo (int)$s['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button onclick='viewSellerDetails(<?php echo json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-eye"></i> View Details</button>
                                    <button onclick='openSellerModal(<?php echo json_encode($s, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <button class="menu-danger" onclick="deleteSeller(<?php echo (int)$s['id']; ?>, '<?php echo htmlspecialchars(addslashes($s['name']), ENT_QUOTES); ?>')"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- REVIEWS TAB -->
        <div class="panel" id="tab-reviews">
        <?php if (canViewModule('reviews')): ?>
            <div class="panel-head"><h2>Reviews (<?php echo count($reviews); ?>)</h2></div>
            <table>
                <thead><tr><th>Customer</th><th>Item</th><th style="text-align:center">Type</th><th style="text-align:center">Rating</th><th>Comment</th><th>Date</th></tr></thead>
                <tbody>
                <?php if (empty($reviews)): ?>
                    <tr><td colspan="6"><div class="empty-state">No reviews found (or the <code>reviews</code> table isn't set up).</div></td></tr>
                <?php else: foreach ($reviews as $r): ?>
                    <tr>
                        <td><strong><?php echo htmlspecialchars($r['reviewer_name'] ?? 'Guest'); ?></strong></td>
                        <td><?php echo htmlspecialchars($r['item_name'] ?? ('#'.($r['item_id'] ?? '—'))); ?></td>
                        <td style="text-align:center"><span class="tag <?php echo ($r['item_type'] ?? '')==='equipment' ? 'equipment' : 'product'; ?>"><?php echo htmlspecialchars(ucfirst($r['item_type'] ?? '—')); ?></span></td>
                        <td style="text-align:center">★ <?php echo htmlspecialchars($r['rating'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['comment'] ?? $r['review_text'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($r['created_at'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>

        <!-- COUPONS TAB -->
        <div class="panel" id="tab-coupons">
        <?php if (canViewModule('coupons')): ?>
            <div class="panel-head">
                <h2>Coupons</h2>
                <button class="btn-primary" onclick="openCouponModal()"><i class="fa-solid fa-plus"></i> Add Coupon</button>
            </div>
            <?php if (!$couponsTableExists): ?>
            <div class="note-box"><strong>AGRI15</strong> is still hardcoded into the storefront checkout. Run <code>add_sellers_coupons.sql</code> once in phpMyAdmin to manage real, multiple coupons from here.</div>
            <?php endif; ?>
            <table>
                <thead><tr><th>Code</th><th>Discount</th><th>Min Order</th><th>Usage</th><th>Expiry</th><th>Status</th><?php if ($couponsTableExists): ?><th>Actions</th><?php endif; ?></tr></thead>
                <tbody>
                <?php if (empty($coupons)): ?>
                    <tr><td colspan="7"><div class="empty-state">No coupons yet.</div></td></tr>
                <?php else: foreach ($coupons as $c): $cIsDeleted = !empty($c['deleted_at']); $cIsExpired = !$cIsDeleted && !empty($c['expiry_date']) && strtotime($c['expiry_date']) < strtotime('today'); ?>
                    <tr<?php echo ($cIsDeleted || $cIsExpired) ? ' style="opacity:.55;"' : ''; ?>>
                        <td><strong><?php echo htmlspecialchars($c['code']); ?></strong></td>
                        <td><?php echo $c['discount_type'] === 'flat' ? ('₹' . number_format($c['discount_value'],0) . ' flat') : (number_format($c['discount_value'],0) . '%'); ?></td>
                        <td><?php echo $c['min_order_amount'] > 0 ? ('₹' . number_format($c['min_order_amount'],0)) : '—'; ?></td>
                        <td><?php echo (int)$c['used_count']; ?><?php echo $c['usage_limit'] ? ' / ' . (int)$c['usage_limit'] : ''; ?></td>
                        <td><?php echo $c['expiry_date'] ? htmlspecialchars($c['expiry_date']) : '—'; ?></td>
                        <td><?php if ($cIsDeleted): ?><span class="tag out">Deleted</span><?php elseif ($cIsExpired): ?><span class="tag low">Expired</span><?php else: ?><span class="tag <?php echo $c['active'] ? 'in' : 'out'; ?>"><?php echo $c['active'] ? 'Active' . (!$couponsTableExists ? ' (hardcoded)' : '') : 'Inactive'; ?></span><?php endif; ?></td>
                        <?php if ($couponsTableExists): ?>
                        <td>
                            <div class="action-menu-wrap">
                                <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                <div class="action-menu">
                                    <?php if ($cIsDeleted): ?>
                                    <button class="menu-success" onclick="restoreCoupon(<?php echo (int)$c['id']; ?>)"><i class="fa-solid fa-rotate-left"></i> Restore</button>
                                    <?php else: ?>
                                    <button onclick='openCouponModal(<?php echo json_encode($c, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)'><i class="fa-solid fa-pen"></i> Edit</button>
                                    <button class="menu-danger" onclick="deleteCoupon(<?php echo (int)$c['id']; ?>, '<?php echo htmlspecialchars(addslashes($c['code']), ENT_QUOTES); ?>')"><i class="fa-solid fa-trash"></i> Delete</button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; endif; ?>
                </tbody>
            </table>
<?php else: ?>
            <div class="empty-state" style="padding:60px 20px"><i class="fa-solid fa-lock" style="font-size:30px;color:var(--border,#ddd);display:block;margin-bottom:10px"></i>You do not have permission to view this section.</div>
            <?php endif; ?>
        </div>
    </main>
</div>

<!-- VIEW DETAILS MODAL (read-only info popup for Products / Equipment / Krishi Bazaar rows) -->
<div class="modal-overlay" id="viewDetailsOverlay">
    <div class="modal-box">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:10px">
            <h3 id="viewDetailsTitle" style="margin:0">Details</h3>
            <button type="button" class="icon-btn" title="Close" onclick="closeViewDetails()"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div id="viewDetailsImageWrap" style="margin:12px 0"></div>
        <div id="viewDetailsBody" style="font-size:13.5px;color:#333"></div>
        <div class="modal-actions">
            <button type="button" class="btn-secondary" onclick="closeViewDetails()">Close</button>
            <button type="button" class="btn-primary" id="viewDetailsEditBtn"><i class="fa-solid fa-pen"></i> Edit</button>
        </div>
    </div>
</div>

<!-- PRODUCT MODAL -->
<div class="modal-overlay" id="productModalOverlay">
    <div class="modal-box">
        <h3 id="productModalTitle">Add Product</h3>
        <form id="productForm">
            <input type="hidden" id="pId" value="">
            <div class="form-grid">
                <div><label>Product Name (English)</label><input type="text" id="pName" required></div>
                <div><label>Product Name (Marathi)</label><input type="text" id="pNameMr"></div>
                <div><label>Product Name (Hindi)</label><input type="text" id="pNameHi"></div>
                <div id="pOriginalNameWrap" style="display:none"><label>Original Name (as typed by seller)</label><input type="text" id="pOriginalName" readonly style="background:#f5f5f5;color:#666"></div>
                <div><label>Category</label>
                    <select id="pCategory">
                        <option value="seeds">Seeds</option>
                        <option value="fertilizer">Fertilizers</option>
                        <option value="pesticides">Pesticides</option>
                        <option value="tools">Farm Tools</option>
                        <option value="irrigation">Irrigation Products</option>
                        <option value="feed">Animal Feed</option>
                        <option value="organic">Organic Products</option>
                        <option value="cropkits">Crop Protection Kits</option>
                    </select>
                </div>
                <div><label>Unit (e.g. 50kg, 1L, 100g)</label><input type="text" id="pUnit" placeholder="1 pc"></div>
                <div><label>Price (₹)</label><input type="number" id="pPrice" required min="0" step="0.01"></div>
                <div><label>Discount Price (₹, optional)</label><input type="number" id="pDiscountPrice" min="0" step="0.01"></div>
                <div><label>Stock Qty</label><input type="number" id="pStock" min="0" value="0"></div>
                <div><label>Seller / Farmer Name</label><input type="text" id="pFarmerName" placeholder="AgriCart Logistics"></div>
                <div><label>Delivery Estimate (e.g. 3-5 days)</label><input type="text" id="pDeliveryEstimate" placeholder="3-5 days"></div>
                <div><label>Brand / Company Name</label><input type="text" id="pBrand"></div>
                <div><label>Condition</label>
                    <select id="pCondition">
                        <option value="new">New</option>
                        <option value="used">Used</option>
                    </select>
                </div>
                <div><label>Delivery Available</label>
                    <select id="pDeliveryAvailable">
                        <option value="1">Yes</option>
                        <option value="0">No</option>
                    </select>
                </div>
                <div><label>Seller Email</label><input type="email" id="pSellerEmail"></div>
                <div><label>Seller Village / City</label><input type="text" id="pSellerVillage"></div>
                <div><label>Seller District</label><input type="text" id="pSellerDistrict"></div>
                <div class="form-full"><label>Seller Full Address</label><input type="text" id="pSellerAddress"></div>
                <div class="form-full"><label>Image path (local, e.g. assets/images/products/seeds.jpg)</label><input type="text" id="pImage" placeholder="assets/images/products/seeds.jpg"></div>
                <div class="form-full" id="pGalleryWrap" style="display:none"><label>Uploaded Photos</label><div id="pGalleryThumbs" style="display:flex;gap:8px;flex-wrap:wrap"></div></div>
                <div class="form-full"><label>Description</label><textarea id="pDesc" rows="3"></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeProductModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Product</button>
            </div>
        </form>
    </div>
</div>

<!-- EQUIPMENT MODAL -->
<div class="modal-overlay" id="equipmentModalOverlay">
    <div class="modal-box">
        <h3 id="equipmentModalTitle">Add Equipment</h3>
        <form id="equipmentForm">
            <input type="hidden" id="eId" value="">
            <div class="form-grid">
                <div><label>Name (English)</label><input type="text" id="eName" required></div>
                <div><label>Name (Marathi)</label><input type="text" id="eNameMr"></div>
                <div><label>Name (Hindi)</label><input type="text" id="eNameHi"></div>
                <div id="eOriginalNameWrap" style="display:none"><label>Original Name (as typed by owner)</label><input type="text" id="eOriginalName" readonly style="background:#f5f5f5;color:#666"></div>
                <div><label>Type</label>
                    <select id="eType">
                        <option value="tractor">Tractor</option>
                        <option value="power_tiller">Power Tiller</option>
                        <option value="rotavator">Rotavator</option>
                        <option value="cultivator">Cultivator</option>
                        <option value="harvester">Harvester</option>
                        <option value="seed_drill">Seed Drill</option>
                        <option value="sprayer">Sprayer</option>
                        <option value="drone">Agricultural Drone</option>
                        <option value="thresher">Thresher</option>
                        <option value="pump">Pump (legacy)</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div><label>Brand</label><input type="text" id="eBrand"></div>
                <div><label>Model</label><input type="text" id="eModel"></div>
                <div><label>Condition</label>
                    <select id="eCondition">
                        <option value="excellent">Excellent</option>
                        <option value="good">Good</option>
                        <option value="average">Average</option>
                    </select>
                </div>
                <div><label>PN (Part Number)</label><input type="text" id="ePn" placeholder="e.g. TR-4520-MH"></div>
                <div><label>Serial No</label><input type="text" id="eSerial" placeholder="e.g. SN20260145"></div>
                <div><label>Rent per Day (₹)</label><input type="number" id="eRent" required min="0" step="0.01"></div>
                <div><label>Security Deposit (₹)</label><input type="number" id="eDeposit" min="0" step="0.01"></div>
                <div><label>HP</label><input type="text" id="eHp" placeholder="e.g. 45 HP"></div>
                <div><label>Engine</label><input type="text" id="eEngine" placeholder="e.g. 3 Cylinder"></div>
                <div><label>Gears</label><input type="text" id="eGears" placeholder="e.g. 8F+2R"></div>
                <div><label>Lift Capacity</label><input type="text" id="eLift" placeholder="e.g. 1500 kg"></div>
                <div><label>Operator Available</label><select id="eOperator"><option value="1">Yes</option><option value="0">No</option></select></div>
                <div><label>Fuel Included</label><select id="eFuel"><option value="1">Yes</option><option value="0">No</option></select></div>
                <div><label>Transport Available</label><select id="eTransport"><option value="1">Yes</option><option value="0">No</option></select></div>
                <div><label>Transport Charge (₹)</label><input type="number" id="eTransportCharge" min="0" step="0.01"></div>
                <div><label>Owner Name</label><input type="text" id="eOwnerName"></div>
                <div><label>Owner Phone</label><input type="text" id="eOwnerPhone" placeholder="10-digit mobile"></div>
                <div><label>Owner Email</label><input type="email" id="eOwnerEmail"></div>
                <div><label>City / Village</label><input type="text" id="eCity" placeholder="e.g. Nashik"></div>
                <div><label>District</label><input type="text" id="eDistrict"></div>
                <div class="form-full"><label>Full Equipment Location / Address</label><input type="text" id="eAddress"></div>
                <div><label>Available for booking?</label>
                    <select id="eAvailability"><option value="1">Yes</option><option value="0">No</option></select>
                </div>
                <div class="form-full"><label>Image path (local, e.g. assets/images/equipment/tractor1.jpg)</label><input type="text" id="eImage" placeholder="assets/images/equipment.png"></div>
                <div class="form-full" id="eGalleryWrap" style="display:none"><label>Uploaded Photos</label><div id="eGalleryThumbs" style="display:flex;gap:8px;flex-wrap:wrap"></div></div>
                <div class="form-full" id="eDocsWrap" style="display:none"><label>Uploaded Documents</label><div id="eDocsList" style="display:flex;flex-direction:column;gap:6px"></div></div>
                <div class="form-full"><label>Rental Rules and Conditions</label><textarea id="eRules" rows="2"></textarea></div>
                <div class="form-full"><label>Description (shown to customers on "View Details")</label><textarea id="eDesc" rows="3" placeholder="e.g. Well-maintained 45 HP tractor, ideal for ploughing and tilling on medium to large farms. Comes with an experienced driver."></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeEquipmentModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Equipment</button>
            </div>
        </form>
    </div>
</div>

<!-- SELLER MODAL -->
<div class="modal-overlay" id="sellerModalOverlay">
    <div class="modal-box">
        <h3 id="sellerModalTitle">Add Seller</h3>
        <form id="sellerForm">
            <input type="hidden" id="slId" value="">
            <div class="form-grid">
                <div class="form-full"><label>Name</label><input type="text" id="slName" required placeholder="e.g. Ramesh Patil"></div>
                <div><label>Mobile</label><input type="text" id="slMobile" placeholder="10-digit mobile"></div>
                <div><label>Email</label><input type="email" id="slEmail" placeholder="optional"></div>
                <div><label>Village</label><input type="text" id="slVillage" placeholder="e.g. Kosbad"></div>
                <div><label>City / Taluka</label><input type="text" id="slCity" placeholder="e.g. Dahanu"></div>
                <div><label>Verified Seller?</label>
                    <select id="slVerified"><option value="0">No</option><option value="1">Yes</option></select>
                </div>
                <div class="form-full"><label>Notes (internal, not shown to customers)</label><textarea id="slNotes" rows="2" placeholder="optional"></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeSellerModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Seller</button>
            </div>
        </form>
    </div>
</div>

<!-- COUPON MODAL -->
<div class="modal-overlay" id="couponModalOverlay">
    <div class="modal-box">
        <h3 id="couponModalTitle">Add Coupon</h3>
        <form id="couponForm">
            <input type="hidden" id="cpId" value="">
            <div class="form-grid">
                <div class="form-full">
                    <label>Coupon Code</label>
                    <div style="display:flex;gap:8px">
                        <input type="text" id="cpCode" required placeholder="e.g. AGRI15" style="text-transform:uppercase;flex:1">
                        <button type="button" class="btn-secondary" onclick="generateCouponCode()" style="white-space:nowrap"><i class="fa-solid fa-dice"></i> Generate</button>
                    </div>
                </div>
                <div><label>Discount Type</label>
                    <select id="cpType"><option value="percent">Percent (%)</option><option value="flat">Flat (₹)</option></select>
                </div>
                <div><label>Discount Value</label><input type="number" id="cpValue" required min="0" step="0.01" placeholder="e.g. 15"></div>
                <div><label>Min Order Amount (₹)</label><input type="number" id="cpMinOrder" min="0" step="0.01" value="0"></div>
                <div><label>Max Discount Cap (₹, optional)</label><input type="number" id="cpMaxDiscount" min="0" step="0.01" placeholder="leave blank = no cap"></div>
                <div><label>Usage Limit (optional)</label><input type="number" id="cpUsageLimit" min="0" step="1" placeholder="leave blank = unlimited"></div>
                <div><label>Expiry Date (optional)</label><input type="date" id="cpExpiry"></div>
                <div><label>Active?</label>
                    <select id="cpActive"><option value="1">Yes</option><option value="0">No</option></select>
                </div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeCouponModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Coupon</button>
            </div>
        </form>
    </div>
</div>

<!-- KRISHI BAZAAR MODAL -->
<div class="modal-overlay" id="bazaarModalOverlay">
    <div class="modal-box">
        <h3 id="bazaarModalTitle">Add Price Entry</h3>
        <form id="bazaarForm">
            <input type="hidden" id="bId" value="">
            <div class="form-grid">
                <div><label>Crop Name (English)</label><input type="text" id="bCropName" required placeholder="e.g. Onion"></div>
                <div><label>Crop Name (Marathi)</label><input type="text" id="bCropNameMr" placeholder="e.g. कांदा"></div>
                <div><label>Market / Mandi Name</label><input type="text" id="bMarketName" required placeholder="e.g. Lasalgaon APMC"></div>
                <div><label>District</label><input type="text" id="bDistrict" placeholder="e.g. Nashik"></div>
                <div><label>Min Price (₹)</label><input type="number" id="bMinPrice" required min="0" step="0.01"></div>
                <div><label>Max Price (₹)</label><input type="number" id="bMaxPrice" required min="0" step="0.01"></div>
                <div><label>Modal Price (₹)</label><input type="number" id="bModalPrice" min="0" step="0.01"></div>
                <div><label>Unit</label>
                    <select id="bUnit">
                        <option value="quintal">Quintal</option>
                        <option value="kg">Kg</option>
                        <option value="ton">Ton</option>
                    </select>
                </div>
                <div><label>Price Date</label><input type="date" id="bPriceDate" required></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeBazaarModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Entry</button>
            </div>
        </form>
    </div>
</div>

<!-- ADVISORY MODAL -->
<div class="modal-overlay" id="advisoryModalOverlay">
    <div class="modal-box">
        <h3 id="advisoryModalTitle">Add Advisory</h3>
        <form id="advisoryForm">
            <input type="hidden" id="aId" value="">
            <div class="form-grid">
                <div><label>Title (English)</label><input type="text" id="aTitle" required></div>
                <div><label>Title (Marathi)</label><input type="text" id="aTitleMr"></div>
                <div><label>Crop</label><input type="text" id="aCrop" placeholder="e.g. Cotton"></div>
                <div><label>Image path (local, e.g. assets/images/advisory/tip1.jpg)</label><input type="text" id="aImage" placeholder="assets/images/advisory.png"></div>
                <div class="form-full"><label>Content (English)</label><textarea id="aContent" rows="4" required></textarea></div>
                <div class="form-full"><label>Content (Marathi)</label><textarea id="aContentMr" rows="4"></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAdvisoryModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Save Advisory</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD COMMENT MODAL -->
<div class="modal-overlay" id="commentModalOverlay">
    <div class="modal-box">
        <h3>Add Comment</h3>
        <form id="commentForm">
            <div class="form-grid">
                <div class="form-full"><label>Post</label>
                    <select id="cPostId" required>
                        <option value="">— Select a post —</option>
                        <?php foreach ($communityPosts as $p): $lbl = mb_strimwidth(strip_tags($p['body'] ?? ''), 0, 60, '...'); ?>
                            <option value="<?php echo (int)$p['id']; ?>"><?php echo htmlspecialchars('#'.$p['id'].' — '.($p['full_name'] ?? '—').' — '.$lbl); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div><label>Author Name (shown on the comment)</label><input type="text" id="cAuthorName" required placeholder="e.g. AgriCart Team"></div>
                <div class="form-full"><label>Comment</label><textarea id="cBody" rows="4" required></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddCommentModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Add Comment</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD FEEDBACK MODAL -->
<div class="modal-overlay" id="feedbackModalOverlay">
    <div class="modal-box">
        <h3>Add Feedback</h3>
        <form id="feedbackAddForm">
            <div class="form-grid">
                <div><label>Rating (1-5, optional)</label><input type="number" id="fRating" min="1" max="5"></div>
                <div><label>Page (optional)</label><input type="text" id="fPage" placeholder="e.g. marketplace.php"></div>
                <div class="form-full"><label>Message</label><textarea id="fMessage" rows="4" required></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddFeedbackModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Add Feedback</button>
            </div>
        </form>
    </div>
</div>

<!-- ADD CONTACT MESSAGE MODAL -->
<div class="modal-overlay" id="messageModalOverlay">
    <div class="modal-box">
        <h3>Add Contact Message</h3>
        <form id="messageAddForm">
            <div class="form-grid">
                <div><label>Name</label><input type="text" id="mName" required></div>
                <div><label>Phone</label><input type="text" id="mPhone" required></div>
                <div><label>Email (optional)</label><input type="email" id="mEmail"></div>
                <div><label>Subject</label><input type="text" id="mSubject" required></div>
                <div class="form-full"><label>Message</label><textarea id="mMessage" rows="4" required></textarea></div>
            </div>
            <div class="modal-actions">
                <button type="button" class="btn-secondary" onclick="closeAddMessageModal()">Cancel</button>
                <button type="submit" class="btn-primary"><i class="fa-solid fa-check"></i> Add Message</button>
            </div>
        </form>
    </div>
</div>

<div class="toast" id="toast"><i class="fa-solid fa-circle-check"></i> <span id="toastMsg"></span></div>

<script>
// ---- Dashboard charts (Chart.js) ----
const salesOverviewData = <?php echo json_encode($monthlySales, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
const categoryDonutData = <?php echo json_encode($categoryBreakdown, JSON_HEX_APOS | JSON_HEX_QUOT); ?>;

// Dashboard mini-table search + pagination — everything runs client-side against
// the rows already rendered by PHP, so no extra requests are made.
const dashPagers = {};
function setupDashPager(bodyId, footId, pagerId, pageSize) {
    const tbody = document.getElementById(bodyId);
    if (!tbody) return;
    const allRows = Array.from(tbody.querySelectorAll('tr[data-row]'));
    let filtered = allRows;
    let page = 1;

    function render() {
        const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));
        if (page > totalPages) page = totalPages;
        allRows.forEach(r => r.style.display = 'none');
        const start = (page - 1) * pageSize;
        filtered.slice(start, start + pageSize).forEach(r => r.style.display = '');

        const foot = document.getElementById(footId);
        if (foot) {
            const shownTo = filtered.length ? Math.min(filtered.length, start + pageSize) : 0;
            foot.querySelector('.foot-text').textContent = filtered.length
                ? `Showing ${start + 1} to ${shownTo} of ${filtered.length} entries`
                : 'No matching results';
        }
        const pager = document.getElementById(pagerId);
        if (pager) {
            let html = `<button type="button" ${page === 1 ? 'disabled' : ''} onclick="dashPagers['${bodyId}'].go(${page - 1})">Previous</button>`;
            for (let p = 1; p <= totalPages; p++) {
                html += `<button type="button" class="${p === page ? 'active' : ''}" onclick="dashPagers['${bodyId}'].go(${p})">${p}</button>`;
            }
            html += `<button type="button" ${page === totalPages ? 'disabled' : ''} onclick="dashPagers['${bodyId}'].go(${page + 1})">Next</button>`;
            pager.innerHTML = html;
        }
    }

    dashPagers[bodyId] = {
        go(p) { page = p; render(); },
        filter(q) {
            const needle = q.trim().toLowerCase();
            filtered = allRows.filter(r => r.textContent.toLowerCase().includes(needle));
            page = 1;
            render();
        }
    };
    render();
}

document.addEventListener('DOMContentLoaded', function () {
    setupDashPager('dashOrdersBody', 'dashOrdersFoot', 'dashOrdersPager', 5);
    setupDashPager('dashSellersBody', 'dashSellersFoot', 'dashSellersPager', 5);
    setupDashPager('dashProductsBody', 'dashProductsFoot', 'dashProductsPager', 6);
});

document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') {
        document.querySelectorAll('#salesOverviewChart, #categoryDonutChart').forEach(function (cv) {
            const msg = document.createElement('div');
            msg.className = 'empty-state';
            msg.textContent = 'Chart library failed to load.';
            cv.replaceWith(msg);
        });
        return;
    }
    const salesCanvas = document.getElementById('salesOverviewChart');
    if (salesCanvas && salesOverviewData.length) {
        const sctx = salesCanvas.getContext('2d');
        const barGradient = sctx.createLinearGradient(0, 0, 0, salesCanvas.height || 220);
        barGradient.addColorStop(0, '#2E7D32');
        barGradient.addColorStop(1, '#A5D6A7');
        new Chart(salesCanvas, {
            type: 'bar',
            data: {
                labels: salesOverviewData.map(r => r.mon),
                datasets: [{
                    label: 'Revenue',
                    data: salesOverviewData.map(r => Number(r.total)),
                    backgroundColor: barGradient,
                    borderRadius: 6,
                    maxBarThickness: 34
                }]
            },
            options: {
                responsive: true,
                plugins: { legend: { display: false }, tooltip: { callbacks: { label: c => '₹' + Number(c.raw).toLocaleString('en-IN') } } },
                scales: {
                    y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } },
                    x: { grid: { display: false } }
                }
            }
        });
    }

    const catCanvas = document.getElementById('categoryDonutChart');
    if (catCanvas && categoryDonutData.length) {
        // Draws each category's name directly on its own slice of the ring.
        const sliceNameLabels = {
            id: 'sliceNameLabels',
            afterDraw(chart) {
                const { ctx } = chart;
                const meta = chart.getDatasetMeta(0);
                ctx.save();
                ctx.font = '600 11px Poppins, sans-serif';
                ctx.fillStyle = '#fff';
                ctx.textAlign = 'center';
                ctx.textBaseline = 'middle';
                meta.data.forEach((arc, i) => {
                    const { startAngle, endAngle, innerRadius, outerRadius, x, y } =
                        arc.getProps(['startAngle', 'endAngle', 'innerRadius', 'outerRadius', 'x', 'y'], true);
                    const angleSpan = endAngle - startAngle;
                    if (angleSpan < 0.28) return;
                    const midAngle = (startAngle + endAngle) / 2;
                    const radius = (innerRadius + outerRadius) / 2;
                    const label = chart.data.labels[i];
                    // Only draw if the label actually fits inside the slice's arc width.
                    const arcWidth = radius * angleSpan;
                    if (ctx.measureText(label).width > arcWidth * 0.95) return;
                    const labelX = x + Math.cos(midAngle) * radius;
                    const labelY = y + Math.sin(midAngle) * radius;
                    ctx.fillText(label, labelX, labelY);
                });
                ctx.restore();
            }
        };

        // Shows the top category's name + count in the center of the ring.
        const centerTopCategory = {
            id: 'centerTopCategory',
            afterDraw(chart) {
                const { ctx, chartArea } = chart;
                const cx = (chartArea.left + chartArea.right) / 2;
                const cy = (chartArea.top + chartArea.bottom) / 2;
                const topLabel = categoryDonutData[0].category;
                const topCount = categoryDonutData[0].cnt;
                ctx.save();
                ctx.textAlign = 'center';
                ctx.fillStyle = '#26292B';
                ctx.font = '700 20px Poppins, sans-serif';
                ctx.fillText(String(topCount), cx, cy - 4);
                ctx.font = '600 12px Poppins, sans-serif';
                ctx.fillStyle = '#68706B';
                ctx.fillText(topLabel.charAt(0).toUpperCase() + topLabel.slice(1), cx, cy + 16);
                ctx.restore();
            }
        };

        new Chart(catCanvas, {
            type: 'doughnut',
            data: {
                labels: categoryDonutData.map(r => r.category),
                datasets: [{
                    data: categoryDonutData.map(r => Number(r.cnt)),
                    backgroundColor: ['#2E7D32', '#FFC107', '#5A9802', '#0b5e2a', '#F9A825', '#8BC34A'],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                cutout: '68%',
                plugins: { legend: { display: false } }
            },
            plugins: [sliceNameLabels, centerTopCategory]
        });
    }
});

// Maps each admin section to the matching page on the live storefront,
// so "View Storefront" always opens the page that's actually relevant
// to whatever you're managing right now.
const STORE_LINKS = {
    dashboard:  { url: '../pages/marketplace.php',   label: 'View Storefront' },
    products:   { url: '../pages/marketplace.php',   label: 'View Marketplace' },
    orders:     { url: '../pages/marketplace.php',   label: 'View Marketplace' },
    equipment:  { url: '../pages/rental.php',  label: 'View Rental Page' },
    bookings:   { url: '../pages/rental.php',  label: 'View Rental Page' },
    bazaar:     { url: '../pages/krishi_bazaar.php', label: 'View Krishi Bazaar' },
    advisory:   { url: '../pages/advisory.php', label: 'View Advisory' },
    community:  { url: '../pages/agri-connect.php', label: 'View Agri-Connect' },
    messages:   { url: '../pages/contact.php',  label: 'View Contact Page' },
    feedback:   { url: '../index.php',  label: 'View Homepage' },
    users:      { url: '../pages/marketplace.php',   label: 'View Marketplace' },
    sellers:    { url: '../pages/marketplace.php',   label: 'View Marketplace' },
    reviews:    { url: '../pages/marketplace.php',   label: 'View Marketplace' },
    coupons:    { url: '../pages/marketplace.php',   label: 'View Marketplace' }
};

function showTab(tab, el){
    document.querySelectorAll('.panel').forEach(p=>p.classList.remove('active'));
    document.querySelectorAll('.nav-item').forEach(n=>n.classList.remove('active'));
    document.getElementById('tab-'+tab).classList.add('active');
    if(el) el.classList.add('active');

    const link = STORE_LINKS[tab] || STORE_LINKS.dashboard;
    document.getElementById('storeLink').setAttribute('href', link.url);
    document.getElementById('storeLinkText').textContent = link.label;
}
document.addEventListener('DOMContentLoaded', function () {
    var initialTab = <?php echo json_encode($agriFirstTab); ?>;
    // A ?tab=xyz in the URL (from a notification or global-search result)
    // wins over the server-picked default, so deep links actually land on
    // the right tab instead of always opening on the dashboard.
    var urlTab = new URLSearchParams(window.location.search).get('tab');
    if (urlTab && document.getElementById('tab-' + urlTab)) { initialTab = urlTab; }
    if (initialTab && initialTab !== 'dashboard' && document.getElementById('tab-' + initialTab)) {
        showTab(initialTab, document.querySelector('.nav-item[data-tab="' + initialTab + '"]'));
    }
    var urlSearch = new URLSearchParams(window.location.search).get('q');
    if (urlSearch) {
        var box = document.getElementById('ordersSearchBox');
        if (box && urlTab === 'orders') { box.value = urlSearch; filterOrdersTable(); }
    }
});

/* ---- Krishi Bazaar: Manual Entries / Live Rates sub-tabs ---- */
let bazaarLiveLoaded = false;
let bazaarLiveRecords = [];
let bazaarLiveFilter = { district: '', crop: '' };

// Same commodity-name normalization the storefront (Krishi Bazaar page) uses,
// so "Soyabean" / "Soybean" etc. from the govt feed count as one crop.
const BAZAAR_COMMODITY_MAP = {
    'Onion':'Onion','Tomato':'Tomato','Potato':'Potato',
    'Soyabean':'Soybean','Soybean':'Soybean',
    'Wheat':'Wheat','Rice':'Rice','Paddy':'Rice',
    'Sugarcane':'Sugarcane','Cotton':'Cotton','Cotton(Seed)':'Cotton',
    'Grapes':'Grapes','Mango':'Mango','Mango (Raw-Ripe)':'Mango',
    'Pomegranate':'Pomegranate','Banana':'Banana','Banana - Green':'Banana',
    'Chilly':'Chilli','Chilli':'Chilli','Dry Chillies':'Chilli','Green Chilli':'Chilli',
    'Turmeric':'Turmeric','Garlic':'Garlic','Ginger(Dry)':'Ginger','Ginger(Green)':'Ginger',
    'Groundnut':'Groundnut','Ground Nut Seed':'Groundnut',
    'Maize':'Maize','Bajra':'Bajra','Jowar':'Jowar',
    'Arhar (Tur/Red Gram)(Whole)':'Tur Dal','Arhar (Tur/Red Gram)':'Tur Dal','Tur':'Tur Dal',
    'Urad':'Urad Dal','Urad Dal':'Urad Dal',
    'Moong':'Moong Dal','Moong Dal':'Moong Dal',
    'Coriander':'Coriander','Capsicum':'Capsicum','Brinjal':'Brinjal',
    'Cauliflower':'Cauliflower','Cabbage':'Cabbage',
    'Bhindi(Ladies Finger)':'Ladyfinger','Lady Finger':'Ladyfinger',
    'Peas Wet':'Peas','Water Melon':'Watermelon',
    'Papaya':'Papaya','Guava':'Guava',
    'Orange':'Orange','Mosambi(Sweet Lime)':'Orange',
    'Lemon':'Lemon','Carrot':'Carrot',
    'Coconut':'Coconut','Cashewnuts':'Cashew',
    'Sesamum(Sesame)':'Sesame','Drum Stick':'Drumstick',
    'Spinach':'Spinach','Sweet Potato':'Sweet Potato',
    'Sunflower Seed':'Sunflower',
};

// The same 50-crop reference list shown on the public Krishi Bazaar page
// (assets/js/krishi-bazaar.js → CROPS_BASE). The govt live feed (data.gov.in)
// often has data for only a handful of crops/markets on a given day — the
// storefront fills the rest in with these reference rates so the page never
// looks empty. We mirror that here so Admin shows the same full crop list;
// rows without a live match are clearly labelled "Reference" (not live).
const BAZAAR_REFERENCE_CROPS = [
    {cropName:'Onion',price:2400},{cropName:'Tomato',price:1800},{cropName:'Potato',price:1200},
    {cropName:'Soybean',price:5200},{cropName:'Wheat',price:2150},{cropName:'Rice',price:3200},
    {cropName:'Sugarcane',price:3500},{cropName:'Cotton',price:7200},{cropName:'Grapes',price:4500},
    {cropName:'Mango',price:6000},{cropName:'Pomegranate',price:8000},{cropName:'Banana',price:2000},
    {cropName:'Chilli',price:8000},{cropName:'Turmeric',price:9500},{cropName:'Garlic',price:6200},
    {cropName:'Ginger',price:4800},{cropName:'Groundnut',price:5500},{cropName:'Maize',price:1900},
    {cropName:'Bajra',price:2200},{cropName:'Jowar',price:2600},{cropName:'Tur Dal',price:12000},
    {cropName:'Urad Dal',price:10500},{cropName:'Moong Dal',price:9800},{cropName:'Coriander',price:3200},
    {cropName:'Capsicum',price:3400},{cropName:'Brinjal',price:1600},{cropName:'Cauliflower',price:2200},
    {cropName:'Cabbage',price:1100},{cropName:'Ladyfinger',price:2800},{cropName:'Peas',price:3600},
    {cropName:'Watermelon',price:1400},{cropName:'Papaya',price:2200},{cropName:'Guava',price:2600},
    {cropName:'Orange',price:4200},{cropName:'Lemon',price:3800},{cropName:'Carrot',price:1800},
    {cropName:'Radish',price:900},{cropName:'Beetroot',price:1600},{cropName:'Coconut',price:5000},
    {cropName:'Cashew',price:18000},{cropName:'Sunflower',price:6200},{cropName:'Sesame',price:14000},
    {cropName:'Fenugreek',price:7800},{cropName:'Drumstick',price:3200},{cropName:'Spinach',price:1200},
    {cropName:'Sweet Potato',price:1400},{cropName:'Peanut',price:5800},{cropName:'Tur',price:11000},
    {cropName:'Curry Leaf',price:4000},{cropName:'Linseed',price:7200},
];

// Merge live govt records with the reference list: live rows keep their real
// market/district/price; any reference crop with no live match today gets a
// clearly-labelled "Reference" row so the table always shows all the crops.
function mergeBazaarLiveWithReference(liveRecords){
    const merged = liveRecords.map(r => ({
        commodity: BAZAAR_COMMODITY_MAP[r.commodity] || r.commodity,
        market: r.market, district: r.district,
        min_price: r.min_price, max_price: r.max_price, modal_price: r.modal_price,
        isLive: true,
    }));
    const liveCropNames = new Set(merged.map(r => r.commodity));
    BAZAAR_REFERENCE_CROPS.forEach(c => {
        if (!liveCropNames.has(c.cropName)) {
            merged.push({
                commodity: c.cropName, market: 'Reference rate', district: '',
                min_price: Math.round(c.price * 0.92), max_price: Math.round(c.price * 1.08),
                modal_price: c.price, isLive: false,
            });
        }
    });
    return merged;
}
function showBazaarSubtab(which){
    document.getElementById('bazaarSubtab-manual').style.display = (which === 'manual') ? '' : 'none';
    document.getElementById('bazaarSubtab-live').style.display = (which === 'live') ? '' : 'none';
    document.getElementById('bazaarSubtabBtn-manual').classList.toggle('active', which === 'manual');
    document.getElementById('bazaarSubtabBtn-live').classList.toggle('active', which === 'live');
    if (which === 'live' && !bazaarLiveLoaded) loadBazaarLiveRates();
}

async function loadBazaarLiveRates(forceRefresh){
    const statusEl = document.getElementById('bazaarLiveStatus');
    const body = document.getElementById('bazaarLiveTableBody');
    if (forceRefresh) bazaarLiveLoaded = false;
    statusEl.textContent = 'Loading live rates…';
    body.innerHTML = '<tr><td colspan="8"><div class="empty-state">Loading…</div></td></tr>';
    try {
        // Same government mandi feed the storefront (Krishi Bazaar page) pulls from.
        const res = await fetch('../pages/mandi.php?state=Maharashtra&limit=500');
        const data = await res.json();
        if (!data.success) {
            const errMsg = data.error ? data.error : 'Live feed returned no data right now.';
            statusEl.textContent = errMsg;
            body.innerHTML = `<tr><td colspan="8"><div class="empty-state">${errMsg}${data.hint ? '<br><small style="color:#999">'+data.hint+'</small>' : ''}</div></td></tr>`;
            bazaarLiveRecords = [];
            populateBazaarLiveFilters();
            return;
        }
        bazaarLiveLoaded = true;
        // Merge in the same reference crop list the storefront uses, so Admin
        // shows the full crop set even on days the govt feed is sparse.
        bazaarLiveRecords = mergeBazaarLiveWithReference(data.records || []);
        const liveCount = bazaarLiveRecords.filter(r => r.isLive).length;
        const refCount  = bazaarLiveRecords.length - liveCount;
        populateBazaarLiveFilters();
        renderBazaarLiveTable();
        statusEl.textContent = `${liveCount} live govt record${liveCount===1?'':'s'} + ${refCount} reference crop${refCount===1?'':'s'} · updated ${new Date().toLocaleTimeString('en-IN')}`;
    } catch (e) {
        statusEl.textContent = 'Could not reach the live rate feed.';
        body.innerHTML = '<tr><td colspan="8"><div class="empty-state">Live rate feed failed to load (check your connection or the mandi.php endpoint).</div></td></tr>';
        bazaarLiveRecords = [];
        console.warn('Live mandi fetch failed:', e);
    }
}

const MAHARASHTRA_DISTRICTS = ['Ahmednagar','Akola','Amravati','Aurangabad','Beed','Bhandara','Buldhana',
'Chandrapur','Dhule','Gadchiroli','Gondia','Hingoli','Jalgaon','Jalna','Kolhapur','Latur',
'Mumbai City','Mumbai Suburban','Nagpur','Nanded','Nandurbar','Nashik','Osmanabad','Palghar',
'Parbhani','Pune','Raigad','Ratnagiri','Sangli','Satara','Sindhudurg','Solapur','Thane',
'Wardha','Washim','Yavatmal'];

function populateBazaarLiveFilters(){
    // District list is always the full Maharashtra list (36 districts), so
    // every district is selectable even if today's live feed has no rows
    // for it yet — plus any district name that shows up in the feed but
    // isn't in our static list (e.g. slightly different spelling).
    const liveDistricts = [...new Set(bazaarLiveRecords.map(r => r.district).filter(Boolean))];
    const districts = [...new Set([...MAHARASHTRA_DISTRICTS, ...liveDistricts])].sort();
    const crops = [...new Set(bazaarLiveRecords.map(r => r.commodity).filter(Boolean))].sort();

    // Keep current selection if it's still valid, otherwise reset to "All"
    if (!districts.includes(bazaarLiveFilter.district)) bazaarLiveFilter.district = '';
    if (!crops.includes(bazaarLiveFilter.crop)) bazaarLiveFilter.crop = '';

    buildBazaarDropdownMenu('district', districts, 'All Districts');
    buildBazaarDropdownMenu('crop', crops, 'All Crops');
}

function buildBazaarDropdownMenu(kind, values, allLabel){
    const menu = document.getElementById(kind + 'DropdownMenu');
    const selected = bazaarLiveFilter[kind];
    let html = `<div class="kb-dropdown-item${selected==='' ? ' selected' : ''}" onclick="selectBazaarFilter('${kind}','')">${allLabel}</div>`;
    html += values.map(v => `<div class="kb-dropdown-item${selected===v ? ' selected' : ''}" onclick="selectBazaarFilter('${kind}', '${v.replace(/'/g, "\\'")}')">${v}</div>`).join('');
    menu.innerHTML = html;
    document.getElementById(kind + 'DropdownLabel').textContent = selected || allLabel;
}

function toggleBazaarDropdown(kind){
    const menu = document.getElementById(kind + 'DropdownMenu');
    const isOpen = menu.classList.contains('open');
    document.querySelectorAll('.kb-dropdown-menu').forEach(m => m.classList.remove('open'));
    if (!isOpen) menu.classList.add('open');
}

function selectBazaarFilter(kind, value){
    bazaarLiveFilter[kind] = value;
    document.getElementById(kind + 'DropdownMenu').classList.remove('open');
    populateBazaarLiveFilters();
    renderBazaarLiveTable();
}

function clearBazaarLiveFilters(){
    bazaarLiveFilter.district = '';
    bazaarLiveFilter.crop = '';
    populateBazaarLiveFilters();
    renderBazaarLiveTable();
}

// Close any open dropdown when clicking outside it
document.addEventListener('click', function(e){
    if (!e.target.closest('.kb-dropdown')) {
        document.querySelectorAll('.kb-dropdown-menu').forEach(m => m.classList.remove('open'));
    }
});

function renderBazaarLiveTable(){
    const body = document.getElementById('bazaarLiveTableBody');
    const district = bazaarLiveFilter.district;
    const crop = bazaarLiveFilter.crop;

    let filtered = bazaarLiveRecords;
    if (district) filtered = filtered.filter(r => r.district === district);
    if (crop) filtered = filtered.filter(r => r.commodity === crop);

    if (!filtered.length) {
        body.innerHTML = '<tr><td colspan="8"><div class="empty-state">No records match this filter.</div></td></tr>';
        return;
    }

    const rows = filtered.slice(0, 200).map(r => `
        <tr>
            <td><strong>${(r.commodity||'—')}</strong></td>
            <td>${r.market||'—'}</td>
            <td>${r.district||'—'}</td>
            <td>₹${Number(r.min_price||0).toLocaleString('en-IN')}</td>
            <td>₹${Number(r.max_price||0).toLocaleString('en-IN')}</td>
            <td>₹${Number(r.modal_price||0).toLocaleString('en-IN')}</td>
            <td>quintal</td>
            <td>${r.isLive ? '<span class="tag in">🟢 Live</span>' : '<span class="tag low">⚪ Reference</span>'}</td>
        </tr>`).join('');
    body.innerHTML = rows;
}

/* Lets stat cards jump straight to the relevant tab. Pending Orders also
   switches the Orders table to show only non-delivered/cancelled rows. */
function goToTab(tab, filterPendingOnly){
    const navItem = document.querySelector('.nav-item[data-tab="'+tab+'"]');
    showTab(tab, navItem);
    if (tab === 'orders') {
        setOrdersFilter(!!filterPendingOnly);
    } else if (tab === 'products') {
        setProductsFilter(!!filterPendingOnly);
    } else if (tab === 'equipment') {
        setEquipmentFilter(!!filterPendingOnly);
    }
}
function applyProductsFilters(){
    document.querySelectorAll('#productsTableBody tr[data-pending]').forEach(row=>{
        const passPending = !window.__productsPendingFilter || row.dataset.pending === '1';
        const passStock = !window.__productsStockFilter || row.dataset.outofstock === '1';
        row.style.display = (passPending && passStock) ? '' : 'none';
    });
}
function setProductsFilter(pendingOnly){
    window.__productsPendingFilter = pendingOnly;
    applyProductsFilters();
    const btn = document.getElementById('productsPendingFilterBtnText');
    if (btn) btn.textContent = pendingOnly ? 'Show All Products' : 'Show Pending Only';
}
function toggleProductsFilter(){
    setProductsFilter(!window.__productsPendingFilter);
}
/* Out of Stock filter — works alongside the Pending filter above; a row
   only shows when it passes both active filters. */
function setProductsStockFilter(outOfStockOnly){
    window.__productsStockFilter = outOfStockOnly;
    applyProductsFilters();
    const btn = document.getElementById('productsStockFilterBtnText');
    if (btn) btn.textContent = outOfStockOnly ? 'Show All Products' : 'Show Out of Stock Only';
}
function toggleProductsStockFilter(){
    setProductsStockFilter(!window.__productsStockFilter);
}
/* The "Out of Stock" dashboard card jumps to the Products tab and filters
   it down to just the out-of-stock rows (clearing the pending filter first
   so the two don't fight each other). */
function goToOutOfStock(){
    const navItem = document.querySelector('.nav-item[data-tab="products"]');
    showTab('products', navItem);
    setProductsFilter(false);
    setProductsStockFilter(true);
}
function setEquipmentFilter(pendingOnly){
    document.querySelectorAll('#equipmentTableBody tr[data-pending]').forEach(row=>{
        row.style.display = (!pendingOnly || row.dataset.pending === '1') ? '' : 'none';
    });
    const btn = document.getElementById('equipmentPendingFilterBtnText');
    if (btn) btn.textContent = pendingOnly ? 'Show All Equipment' : 'Show Pending Only';
    window.__equipmentPendingFilter = pendingOnly;
}
function toggleEquipmentFilter(){
    setEquipmentFilter(!window.__equipmentPendingFilter);
}
/* The Pending Approvals card covers BOTH products and equipment — open
   whichever has pending items first (products takes priority if both do),
   filter that tab to pending-only, and tell the admin about the other one. */
function goToPendingApprovals(){
    const pendingProducts = <?php echo (int)$pendingProductsCount; ?>;
    const pendingEquipment = <?php echo (int)$pendingEquipmentCount; ?>;
    if (pendingProducts === 0 && pendingEquipment === 0) {
        showToast('No pending approvals right now.');
        return;
    }
    if (pendingProducts > 0) {
        goToTab('products', true);
        if (pendingEquipment > 0) showToast(pendingEquipment + ' equipment listing(s) are also pending — check the Equipment tab.');
    } else {
        goToTab('equipment', true);
    }
}
function setOrdersFilter(pendingOnly){
    window.__ordersPendingFilter = pendingOnly;
    const btn = document.getElementById('pendingFilterBtnText');
    if (btn) btn.textContent = pendingOnly ? 'Show All Orders' : 'Show Pending Only';
    filterOrdersTable();
}
function toggleOrdersFilter(){
    setOrdersFilter(!window.__ordersPendingFilter);
}
/* Combines the pending-only toggle, the status dropdown, and the
   search-by-order-id box into one pass over the Orders table rows. */
function filterOrdersTable(){
    const q = (document.getElementById('ordersSearchBox')?.value || '').trim().toLowerCase();
    const statusFilter = document.getElementById('ordersStatusFilter')?.value || '';
    const pendingOnly = !!window.__ordersPendingFilter;
    document.querySelectorAll('#ordersTableBody tr[data-pending]').forEach(row=>{
        let show = true;
        if (pendingOnly && row.dataset.pending !== '1') show = false;
        if (show && statusFilter && row.dataset.status !== statusFilter) show = false;
        if (show && q && !(row.dataset.orderNumber || '').includes(q)) show = false;
        row.style.display = show ? '' : 'none';
    });
}
function showToast(msg){
    const t=document.getElementById('toast'), m=document.getElementById('toastMsg');
    m.textContent=msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'),2400);
}

/* ---- Global Search (spec §16) ---- */
let gsDebounce = null;
function gsHandleInput(){
    clearTimeout(gsDebounce);
    const q = document.getElementById('gsInput').value.trim();
    const box = document.getElementById('gsResults');
    if (q.length < 2) { box.classList.remove('open'); return; }
    gsDebounce = setTimeout(function(){
        fetch('global_search.php?q=' + encodeURIComponent(q))
            .then(r => r.json())
            .then(d => {
                if (!d.success) { box.innerHTML = '<div class="empty-state" style="padding:16px">' + (d.error || 'No matches.') + '</div>'; box.classList.add('open'); return; }
                const cats = Object.keys(d.results);
                if (!cats.length) {
                    box.innerHTML = '<div class="empty-state" style="padding:16px"><i class="fa-solid fa-magnifying-glass"></i> No matches for "' + q.replace(/</g,'&lt;') + '".</div>';
                } else {
                    box.innerHTML = cats.map(function(cat){
                        return '<div class="gs-cat">' + cat + '</div>' + d.results[cat].map(function(item){
                            return '<a href="' + item.url + '" class="gs-item">' + item.label.replace(/</g,'&lt;') + '</a>';
                        }).join('');
                    }).join('');
                }
                box.classList.add('open');
            })
            .catch(() => {});
    }, 250);
}
document.addEventListener('click', function(e){
    const wrap = document.querySelector('.gs-search-wrap');
    if (!wrap) return;
    if (!wrap.contains(e.target)) { document.getElementById('gsResults')?.classList.remove('open'); }
});

/* ---- Notification Center bell (spec §14) ---- */
const NOTIF_ICONS = {
    new_order: 'fa-cart-shopping', new_seller: 'fa-store', seller_verification: 'fa-user-shield',
    gst_verification: 'fa-file-shield', payment_received: 'fa-money-bill-wave', payout_request: 'fa-hand-holding-dollar',
    refund_request: 'fa-rotate-left', low_stock: 'fa-triangle-exclamation', new_complaint: 'fa-envelope-open-text',
    system_alert: 'fa-circle-exclamation'
};
function notifTimeAgo(iso){
    const d = new Date(iso.replace(' ', 'T'));
    const diffMin = Math.max(0, Math.floor((Date.now() - d.getTime()) / 60000));
    if (diffMin < 1) return 'just now';
    if (diffMin < 60) return diffMin + 'm ago';
    const diffHr = Math.floor(diffMin / 60);
    if (diffHr < 24) return diffHr + 'h ago';
    return Math.floor(diffHr / 24) + 'd ago';
}
function loadNotifications(){
    fetch('notifications_action.php?action=list')
        .then(r => r.json())
        .then(d => {
            if (!d.success) return;
            const badge = document.getElementById('notifBadge');
            if (d.unread_count > 0) { badge.style.display = 'flex'; badge.textContent = d.unread_count > 99 ? '99+' : d.unread_count; }
            else { badge.style.display = 'none'; }
            const list = document.getElementById('notifList');
            if (!d.notifications.length) {
                list.innerHTML = '<div class="empty-state" style="padding:24px 12px"><i class="fa-solid fa-bell-slash"></i> No notifications yet.</div>';
                return;
            }
            list.innerHTML = d.notifications.map(function(n){
                const icon = NOTIF_ICONS[n.type] || 'fa-bell';
                const href = n.link || '#';
                return '<a href="' + href.replace(/"/g, '&quot;') + '" class="notif-item' + (n.is_read == 0 ? ' unread' : '') + '" onclick="markNotifRead(' + n.id + ')">' +
                    '<div class="t"><i class="fa-solid ' + icon + '" style="margin-right:6px;color:var(--primary)"></i>' + n.title.replace(/</g,'&lt;') + '</div>' +
                    (n.message ? '<div class="m">' + n.message.replace(/</g,'&lt;') + '</div>' : '') +
                    '<div class="d">' + notifTimeAgo(n.created_at) + '</div></a>';
            }).join('');
        })
        .catch(() => {});
}
function toggleNotifDropdown(){
    const dd = document.getElementById('notifDropdown');
    dd.classList.toggle('open');
    if (dd.classList.contains('open')) { loadNotifications(); }
}
function markNotifRead(id){
    fetch('notifications_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'mark_read', id }) }).catch(() => {});
}
function markAllNotifsRead(e){
    e.preventDefault();
    fetch('notifications_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'mark_all_read' }) })
        .then(() => loadNotifications())
        .catch(() => {});
}
document.addEventListener('click', function(e){
    const wrap = document.querySelector('.notif-bell-wrap');
    if (!wrap) return;
    if (!wrap.contains(e.target)) { document.getElementById('notifDropdown')?.classList.remove('open'); }
});
if (document.getElementById('notifBadge')) {
    loadNotifications();
    setInterval(loadNotifications, 60000);
}

/* ---- Profile dropdown (Login as User / Logout) ---- */
function toggleProfileMenu(){
    document.getElementById('profileTrigger').classList.toggle('open');
    document.getElementById('profileDropdown').classList.toggle('open');
}
function editMyName(e){
    e.preventDefault();
    const current = <?php echo json_encode($adminName); ?>;
    const name = prompt('Enter your name:', current === 'Admin' ? '' : current);
    if (name === null) return;
    const trimmed = name.trim();
    if (!trimmed) { alert('Name cannot be empty.'); return; }
    fetch('update_admin_name.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ full_name: trimmed })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { location.reload(); }
        else { alert(d.error || 'Could not update name.'); }
    })
    .catch(() => alert('Network error — please try again.'));
}
document.addEventListener('click', function(e){
    const trigger = document.getElementById('profileTrigger');
    const dropdown = document.getElementById('profileDropdown');
    if (!trigger.contains(e.target) && !dropdown.contains(e.target)) {
        trigger.classList.remove('open');
        dropdown.classList.remove('open');
    }
});

/* ---- View Details modal (read-only, shown on row/name/image click) ---- */
function esc(v){ return (v===null || v===undefined) ? '' : String(v); }
function vdRow(label, value){
    if (value === null || value === undefined || value === '') return '';
    return `<div class="vd-row"><span>${label}</span><span>${value}</span></div>`;
}
function showViewDetails(title, imageSrc, rowsHtml, descHtml, editFn){
    document.getElementById('viewDetailsTitle').textContent = title;
    const imgWrap = document.getElementById('viewDetailsImageWrap');
    imgWrap.innerHTML = imageSrc ? `<img src="${imageSrc}" onerror="this.style.display='none'">` : '';
    document.getElementById('viewDetailsBody').innerHTML = rowsHtml + (descHtml || '');
    const editBtn = document.getElementById('viewDetailsEditBtn');
    if (editFn) {
        editBtn.style.display = '';
        editBtn.onclick = function(){ closeViewDetails(); editFn(); };
    } else {
        editBtn.style.display = 'none';
    }
    document.getElementById('viewDetailsOverlay').classList.add('open');
}
function closeViewDetails(){ document.getElementById('viewDetailsOverlay').classList.remove('open'); }

function viewProductDetails(p){
    const price = Number(p.price || 0);
    const discountPrice = Number(p.discount_price || 0);
    const hasDiscount = discountPrice > price; // same rule the storefront uses (discount_price = original/MRP, price = what customer pays)
    const priceRows = hasDiscount
        ? vdRow('Selling Price', '₹' + price.toLocaleString()) + vdRow('Original Price (MRP)', '<s style="color:#999">₹' + discountPrice.toLocaleString() + '</s> · ' + Math.round((1 - price/discountPrice)*100) + '% OFF')
        : vdRow('Price', '₹' + price.toLocaleString());
    const langLabel = { en:'English', mr:'Marathi', hi:'Hindi' }[p.original_language] || p.original_language || '';
    const rows = vdRow('Category', esc(p.category))
        + priceRows
        + vdRow('Stock', esc(p.stock) + ' ' + esc(p.unit || ''))
        + vdRow('Brand', esc(p.brand) || '—')
        + vdRow('Condition', p.product_condition === 'used' ? 'Used' : 'New')
        + vdRow('Delivery Available', (p.delivery_available == 1 || p.delivery_available === null) ? 'Yes' : 'No')
        + vdRow('Delivery Estimate', esc(p.delivery_estimate) || '—')
        + vdRow('Original Name (as typed)', p.name_original ? esc(p.name_original) + (langLabel ? ' — ' + langLabel : '') : '—')
        + vdRow('Name (English)', esc(p.name) || '—')
        + vdRow('Name (Marathi)', esc(p.name_mr) || '—')
        + vdRow('Name (Hindi)', esc(p.name_hi) || '—')
        + vdRow('Seller / Farmer', esc(p.farmer_name) || '—')
        + vdRow('Seller Phone', esc(p.farmer_phone) || '—')
        + vdRow('Seller Email', esc(p.seller_email) || '—')
        + vdRow('Village / City', esc(p.seller_village) || '—')
        + vdRow('District', esc(p.seller_district) || '—')
        + vdRow('Full Address', esc(p.seller_address) || '—')
        + vdRow('Source', esc(p.source) || '—');
    let desc = p.description ? `<div class="vd-desc"><strong>Description:</strong><br>${esc(p.description)}</div>` : `<div class="vd-desc" style="color:#999">No description added yet. Click Edit to add one.</div>`;
    showViewDetails(p.name || 'Product', p.image ? ('../' + p.image) : '', rows, desc, function(){ openProductModal(p); });

    // Best-effort: append the full uploaded gallery below the description
    // once it loads (farmer listings can have more than one photo).
    if (p.added_by_user_id) {
        fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_images', id:p.id}) })
            .then(r=>r.json())
            .then(data=>{
                if (data.success && data.images && data.images.length > 1) {
                    const galleryHtml = `<div class="vd-desc"><strong>All Photos (${data.images.length}):</strong><br><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">${data.images.map(img=>`<img src="../${img}" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #eee">`).join('')}</div></div>`;
                    const descEl = document.querySelector('.vd-desc');
                    if (descEl) { descEl.insertAdjacentHTML('afterend', galleryHtml); }
                }
            })
            .catch(()=>{});
    }
}

function viewEquipmentDetails(e){
    const langLabel = { en:'English', mr:'Marathi', hi:'Hindi' }[e.original_language] || e.original_language || '';
    const rows = vdRow('Type', esc(e.type))
        + vdRow('Brand', esc(e.brand) || '—')
        + vdRow('Model', esc(e.model) || '—')
        + vdRow('Condition', esc(e.equipment_condition) || '—')
        + vdRow('PN (Part Number)', esc(e.pn) || '—')
        + vdRow('Serial No', esc(e.serial_no) || '—')
        + vdRow('Rent Type', esc(e.rent_type) || 'day')
        + vdRow('Rent / Day', '₹' + Number(e.rent_per_day||0).toLocaleString())
        + vdRow('Rent / Hour', e.rent_per_hour ? '₹' + Number(e.rent_per_hour).toLocaleString() : '—')
        + vdRow('Rent / Acre', e.rent_per_acre ? '₹' + Number(e.rent_per_acre).toLocaleString() : '—')
        + vdRow('Security Deposit', e.security_deposit ? '₹' + Number(e.security_deposit).toLocaleString() : '—')
        + vdRow('HP', esc(e.hp) || '—')
        + vdRow('Engine', esc(e.engine) || '—')
        + vdRow('Gears', esc(e.gears) || '—')
        + vdRow('Lift Capacity', esc(e.lift_capacity) || '—')
        + vdRow('Operator Available', e.operator_available == 1 ? 'Yes' : 'No')
        + vdRow('Fuel Included', e.fuel_included == 1 ? 'Yes' : 'No')
        + vdRow('Transport Available', e.transport_available == 1 ? 'Yes' : 'No')
        + vdRow('Available From / To', (esc(e.available_from) || '—') + ' → ' + (esc(e.available_to) || '—'))
        + vdRow('Available Days', esc(e.available_days) || '—')
        + vdRow('Booking Notice', esc(e.booking_notice_period) || '—')
        + vdRow('Original Name (as typed)', e.name_original ? esc(e.name_original) + (langLabel ? ' — ' + langLabel : '') : '—')
        + vdRow('Name (English)', esc(e.name) || '—')
        + vdRow('Name (Marathi)', esc(e.name_mr) || '—')
        + vdRow('Name (Hindi)', esc(e.name_hi) || '—')
        + vdRow('Owner Name', esc(e.owner_name) || '—')
        + vdRow('Owner Phone', esc(e.owner_phone) || '—')
        + vdRow('Owner Email', esc(e.owner_email) || '—')
        + vdRow('Owner Verified', e.owner_verified == 1 ? 'Yes' : 'No')
        + vdRow('City / Village', esc(e.city_name) || '—')
        + vdRow('District', esc(e.owner_district) || '—')
        + vdRow('Full Address', esc(e.owner_address) || '—')
        + vdRow('Rental Rules', esc(e.rental_rules) || '—')
        + vdRow('Available', (e.availability == 1 || e.availability === true) ? 'Yes' : 'No');
    const desc = e.description ? `<div class="vd-desc"><strong>Description:</strong><br>${esc(e.description)}</div>` : `<div class="vd-desc" style="color:#999">No description added yet. Click Edit to add one.</div>`;
    showViewDetails(e.name || 'Equipment', e.image ? ('../' + e.image) : '', rows, desc, function(){ openEquipmentModal(e); });

    // Best-effort: append the full photo gallery and any uploaded documents.
    if (e.owner_user_id) {
        fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_images', id:e.id}) })
            .then(r=>r.json())
            .then(data=>{
                if (data.success && data.images && data.images.length > 1) {
                    const galleryHtml = `<div class="vd-desc"><strong>All Photos (${data.images.length}):</strong><br><div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:8px">${data.images.map(img=>`<img src="../${img}" style="width:72px;height:72px;object-fit:cover;border-radius:8px;border:1px solid #eee">`).join('')}</div></div>`;
                    const descEl = document.querySelector('.vd-desc');
                    if (descEl) { descEl.insertAdjacentHTML('afterend', galleryHtml); }
                }
            })
            .catch(()=>{});
        fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_documents', id:e.id}) })
            .then(r=>r.json())
            .then(data=>{
                if (data.success && data.documents && data.documents.length) {
                    const docsHtml = `<div class="vd-desc"><strong>Documents (${data.documents.length}):</strong><br><div style="display:flex;flex-direction:column;gap:6px;margin-top:8px">${data.documents.map(doc=>`<a href="../${doc.doc_path}" target="_blank" style="font-size:12.5px;color:#2E7D32"><i class="fa-solid fa-file"></i> ${esc(doc.doc_name)}</a>`).join('')}</div></div>`;
                    const descEl = document.querySelector('.vd-desc');
                    if (descEl) { descEl.insertAdjacentHTML('afterend', docsHtml); }
                }
            })
            .catch(()=>{});
    }
}

function viewSellerDetails(s){
    const rows = vdRow('Mobile', esc(s.mobile) || '—')
        + vdRow('Email', esc(s.email) || '—')
        + vdRow('Village', esc(s.village) || '—')
        + vdRow('City / Taluka', esc(s.city) || '—')
        + vdRow('Verified Seller', s.verified == 1 ? 'Yes' : 'No')
        + vdRow('Products Listed', esc(s.product_count));
    const desc = s.notes ? `<div class="vd-desc"><strong>Notes:</strong><br>${esc(s.notes)}</div>` : '';
    showViewDetails(s.name || 'Seller', '', rows, desc, function(){ openSellerModal(s); });
}

function viewBazaarDetails(bp){
    const rows = vdRow('Crop (Marathi)', esc(bp.crop_name_mr))
        + vdRow('Market / Mandi', esc(bp.market_name) || '—')
        + vdRow('District', esc(bp.district) || '—')
        + vdRow('Min Price', '₹' + Number(bp.min_price||0).toLocaleString())
        + vdRow('Max Price', '₹' + Number(bp.max_price||0).toLocaleString())
        + vdRow('Modal Price', '₹' + Number(bp.modal_price||0).toLocaleString())
        + vdRow('Unit', esc(bp.unit) || 'quintal')
        + vdRow('Date', esc(bp.price_date));
    showViewDetails(bp.crop_name || 'Crop Price', '', rows, '', function(){ openBazaarModal(bp); });
}

function viewBookingDetails(b){
    const accountLine = (b.account_name || b.account_email)
        ? esc(b.account_name || '—') + (b.account_email ? ' · ' + esc(b.account_email) : '') + (b.account_mobile ? ' · ' + esc(b.account_mobile) : '')
        : null;

    const paymentLabels = { paid: 'Paid ✓', failed: 'Failed', pending: 'Pending', cod: 'Pending' };
    const rows = vdRow('Equipment', esc(b.equipment_name))
        + vdRow('Type', esc(b.equipment_type))
        + vdRow('PN (Part Number)', esc(b.pn))
        + vdRow('Serial No', esc(b.serial_no))
        + vdRow('Rental Dates', esc(b.from_date) + ' → ' + esc(b.to_date))
        + vdRow('Booked By (account)', accountLine)
        + vdRow('Customer Contact', esc(b.contact_name) + (b.contact_mobile ? ' · ' + esc(b.contact_mobile) : ''))
        + vdRow('Delivery / Pickup Address', esc(b.delivery_address))
        + vdRow('Equipment Owner', esc(b.equipment_owner_name) + (b.equipment_owner_phone ? ' · ' + esc(b.equipment_owner_phone) : ''))
        + vdRow('Payment Mode', b.payment_mode ? String(b.payment_mode).toUpperCase() : null)
        + vdRow('Payment Status', paymentLabels[b.payment_status] || 'Pending')
        + vdRow('Total Amount', '₹' + Number(b.total_amount || 0).toLocaleString())
        + vdRow('Status', b.status ? b.status.charAt(0).toUpperCase() + b.status.slice(1).replace('_',' ') : null);

    const desc = b.notes ? `<div class="vd-desc"><strong>Notes:</strong><br>${esc(b.notes)}</div>` : '';
    const img = b.equipment_image ? ('../' + b.equipment_image) : '';
    showViewDetails(b.booking_number || ('#' + b.id), img, rows, desc, null);
}

function viewOrderDetails(o){
    const items = o.items || [];
    const itemsHtml = items.length
        ? `<div class="vd-items">` + items.map(function(it){
            const img = it.image ? ('../' + it.image) : '../assets/images/products/default.jpg';
            const price = it.price ? ('₹' + Number(it.price).toLocaleString()) : '';
            const stBadge = it.item_status ? `<span class="tag ${['delivered','confirmed','packed','shipped'].includes(it.item_status)?'in':(['cancelled','returned','refunded'].includes(it.item_status)?'out':'low')}" style="margin-left:6px">${esc(it.item_status.replace('_',' '))}</span>` : '';
            const sellerLine = it.seller_name ? `<div class="vd-item-meta" style="color:#888">Seller: ${esc(it.seller_name)}</div>` : '';
            return `<div class="vd-item-row">
                        <img src="${img}" onerror="this.src='../assets/images/products/default.jpg'">
                        <div class="vd-item-info">
                            <div class="vd-item-name">${esc(it.product_name)} ${stBadge}</div>
                            <div class="vd-item-meta">Qty: ${esc(it.quantity)} ${price ? '· ' + price + ' each' : ''}</div>
                            ${sellerLine}
                        </div>
                    </div>`;
        }).join('') + `</div>`
        : '<div style="color:#999;font-size:13px">No item details found.</div>';

    const finalAmt = (o.final_amount !== undefined && o.final_amount !== null && o.final_amount !== '') ? Number(o.final_amount) : Number(o.total_amount || 0);
    const accountLine = (o.account_name || o.account_email)
        ? esc(o.account_name || '—') + (o.account_email ? ' · ' + esc(o.account_email) : '') + (o.account_mobile ? ' · ' + esc(o.account_mobile) : '')
        : null;

    // Prefer a real delivery_date column if the DB has one; otherwise show a
    // computed estimate (order date + 5 days) so something useful is always visible.
    let deliveryDateLabel = null;
    if (o.delivery_date) {
        deliveryDateLabel = esc(o.delivery_date);
    } else if (o.order_status === 'delivered') {
        deliveryDateLabel = null; // no timestamp tracked for when it was actually delivered
    } else if (o.created_at) {
        const d = new Date(o.created_at);
        if (!isNaN(d.getTime())) {
            d.setDate(d.getDate() + 5);
            deliveryDateLabel = d.toLocaleDateString('en-IN', { year: 'numeric', month: 'short', day: 'numeric' }) + ' (estimated)';
        }
    }

    const rows = vdRow('Order Date', esc(o.created_at))
        + vdRow('Delivery Date', deliveryDateLabel)
        + vdRow('Ordered By (account)', accountLine)
        + vdRow('Delivery Contact', esc(o.delivery_name) + (o.delivery_mobile ? ' · ' + esc(o.delivery_mobile) : ''))
        + vdRow('Delivery Address', esc(o.delivery_address))
        + vdRow('Pincode', esc(o.delivery_pincode))
        + vdRow('Payment Mode', o.payment_mode ? String(o.payment_mode).toUpperCase() : null)
        + vdRow('Payment Status', esc(o.payment_status))
        + vdRow('Payment / Transaction ID', esc(o.payment_id || o.transaction_id))
        + vdRow('Subtotal', o.total_amount ? '₹' + Number(o.total_amount).toLocaleString() : null)
        + vdRow('Coupon', esc(o.coupon_code))
        + vdRow('Discount', o.discount_amount > 0 ? '−₹' + Number(o.discount_amount).toLocaleString() : null)
        + vdRow('Final Amount', '₹' + finalAmt.toLocaleString())
        + vdRow('Status', o.order_status ? o.order_status.charAt(0).toUpperCase() + o.order_status.slice(1) : null);

    const desc = `<div class="vd-desc"><strong>Items Ordered:</strong><br><br>${itemsHtml}</div>
        <div class="vd-desc" id="vdOrderHistory"><strong>Status Timeline:</strong><br><br><span style="color:#999;font-size:12.5px">Loading...</span></div>`;
    showViewDetails(o.order_number || ('#' + o.id), '', rows, desc, null);

    fetch('order_action.php?action=get_history&order_id=' + encodeURIComponent(o.id))
        .then(r => r.json())
        .then(data => {
            const box = document.getElementById('vdOrderHistory');
            if (!box) return;
            if (!data.success || !data.data || !data.data.length) {
                box.innerHTML = '<strong>Status Timeline:</strong><br><br><span style="color:#999;font-size:12.5px">No history yet.</span>';
                return;
            }
            const rowsH = data.data.map(h => `
                <div style="padding:8px 0;border-bottom:1px solid #f0f0f0">
                    <strong>${esc(h.new_status_label || h.new_status)}</strong>
                    ${h.order_item_id ? '<span style="color:#999;font-size:11px"> (item #' + h.order_item_id + ')</span>' : ''}
                    <div style="font-size:11.5px;color:#888">${esc((h.changed_by_role||'system').replace(/^\w/,c=>c.toUpperCase()))}${h.changed_by_name ? ' · ' + esc(h.changed_by_name) : ''} · ${esc(h.created_at||'')}</div>
                    ${h.reason ? '<div style="font-size:11.5px;color:#888">' + esc(h.reason) + '</div>' : ''}
                </div>`).join('');
            box.innerHTML = '<strong>Status Timeline:</strong>' + rowsH;
        })
        .catch(() => {
            const box = document.getElementById('vdOrderHistory');
            if (box) box.innerHTML = '<strong>Status Timeline:</strong><br><br><span style="color:#999;font-size:12.5px">Could not load history.</span>';
        });
}

/* ---- Product modal ---- */
function openProductModal(product){
    document.getElementById('productForm').reset();
    document.getElementById('pOriginalNameWrap').style.display = 'none';
    document.getElementById('pGalleryWrap').style.display = 'none';
    document.getElementById('pGalleryThumbs').innerHTML = '';
    if(product){
        document.getElementById('productModalTitle').textContent = 'Edit Product';
        document.getElementById('pId').value = product.id;
        document.getElementById('pName').value = product.name || '';
        document.getElementById('pNameMr').value = product.name_mr || '';
        document.getElementById('pNameHi').value = product.name_hi || '';
        document.getElementById('pCategory').value = product.category || 'seeds';
        document.getElementById('pUnit').value = product.unit || '';
        document.getElementById('pPrice').value = product.price || '';
        document.getElementById('pDiscountPrice').value = product.discount_price || '';
        document.getElementById('pStock').value = product.stock || 0;
        document.getElementById('pFarmerName').value = product.farmer_name || '';
        document.getElementById('pDeliveryEstimate').value = product.delivery_estimate || '';
        document.getElementById('pBrand').value = product.brand || '';
        document.getElementById('pCondition').value = product.product_condition || 'new';
        document.getElementById('pDeliveryAvailable').value = (product.delivery_available == 1 || product.delivery_available === null) ? '1' : '0';
        document.getElementById('pSellerEmail').value = product.seller_email || '';
        document.getElementById('pSellerVillage').value = product.seller_village || '';
        document.getElementById('pSellerDistrict').value = product.seller_district || '';
        document.getElementById('pSellerAddress').value = product.seller_address || '';
        document.getElementById('pImage').value = product.image || '';
        document.getElementById('pDesc').value = product.description || '';

        if (product.name_original) {
            document.getElementById('pOriginalNameWrap').style.display = '';
            const langLabel = { en:'English', mr:'Marathi', hi:'Hindi' }[product.original_language] || product.original_language || '';
            document.getElementById('pOriginalName').value = product.name_original + (langLabel ? ' (' + langLabel + ')' : '');
        }

        if (product.added_by_user_id) {
            fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_images', id:product.id}) })
                .then(r=>r.json())
                .then(data=>{
                    if (data.success && data.images && data.images.length){
                        document.getElementById('pGalleryWrap').style.display = '';
                        document.getElementById('pGalleryThumbs').innerHTML = data.images.map(img =>
                            `<img src="../${img}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #eee">`
                        ).join('');
                    }
                })
                .catch(()=>{});
        }
    } else {
        document.getElementById('productModalTitle').textContent = 'Add Product';
        document.getElementById('pId').value = '';
        document.getElementById('pCondition').value = 'new';
        document.getElementById('pDeliveryAvailable').value = '1';
    }
    document.getElementById('productModalOverlay').classList.add('open');
}
function closeProductModal(){ document.getElementById('productModalOverlay').classList.remove('open'); }

document.getElementById('productForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'save',
        id: document.getElementById('pId').value,
        name: document.getElementById('pName').value,
        name_mr: document.getElementById('pNameMr').value,
        name_hi: document.getElementById('pNameHi').value,
        category: document.getElementById('pCategory').value,
        unit: document.getElementById('pUnit').value,
        price: document.getElementById('pPrice').value,
        discount_price: document.getElementById('pDiscountPrice').value,
        stock: document.getElementById('pStock').value,
        farmer_name: document.getElementById('pFarmerName').value,
        delivery_estimate: document.getElementById('pDeliveryEstimate').value,
        brand: document.getElementById('pBrand').value,
        product_condition: document.getElementById('pCondition').value,
        delivery_available: document.getElementById('pDeliveryAvailable').value,
        seller_email: document.getElementById('pSellerEmail').value,
        seller_village: document.getElementById('pSellerVillage').value,
        seller_district: document.getElementById('pSellerDistrict').value,
        seller_address: document.getElementById('pSellerAddress').value,
        image: document.getElementById('pImage').value,
        description: document.getElementById('pDesc').value
    });
    fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Product saved.'); closeProductModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Save failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteProduct(id){
    if(!confirm('Delete this product? It will be hidden from the storefront.')) return;
    fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Product deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreProduct(id){
    fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Product restored — back on the storefront.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function permanentDeleteProduct(id, name){
    closeAllActionsMenus();
    confirmAction(
        'Permanently delete "' + (name || 'this product') + '"? This erases the product completely — unlike Delete, it CANNOT be restored.',
        function(){
            fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'permanent_delete', id}) })
                .then(r=>r.json())
                .then(data=>{
                    if(data.success){ showToast('Product permanently deleted.'); setTimeout(()=>location.reload(), 600); }
                    else { showToast(data.error || 'Permanent delete failed.'); }
                })
                .catch(()=>showToast('Network error.'));
        },
        { title: 'Permanently Delete Product?', confirmLabel: 'Permanently Delete' }
    );
}

function approveProduct(id){
    fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'approve', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Product approved — now live on the Marketplace.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Approve failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function rejectProduct(id){
    if(!confirm('Reject this farmer-submitted product? It will stay hidden from the Marketplace.')) return;
    fetch('product_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'reject', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Product rejected.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Reject failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

const ADMIN_ORDER_STATUS_CONFIRM = {
    cancelled: 'Cancel this order? This will restore stock for any items that can still be cancelled and cannot be undone.',
    returned: 'Mark this order as Returned? Use this once the buyer has sent the item(s) back.',
    refunded: 'Mark this order as Refunded? This should only be done after the refund has actually been processed.',
};
function updateOrderStatus(orderId, status, selectEl){
    const prev = selectEl ? selectEl.dataset.prev : null;
    const msg = ADMIN_ORDER_STATUS_CONFIRM[status];
    if (msg && !confirm(msg)) {
        if (selectEl && prev) selectEl.value = prev;
        return;
    }
    if (selectEl) selectEl.disabled = true;
    fetch('order_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({order_id:orderId, status}) })
        .then(r=>r.json())
        .then(data=>{
            if (selectEl) selectEl.disabled = false;
            if (data.success){
                showToast('Order status updated.');
                const finalStatus = data.order_status || status;
                if (selectEl) {
                    selectEl.value = finalStatus;
                    selectEl.dataset.prev = finalStatus;
                    const row = selectEl.closest('tr');
                    if (row) {
                        row.dataset.status = finalStatus;
                        row.dataset.pending = ['delivered','cancelled','returned','refunded'].includes(finalStatus) ? '0' : '1';
                    }
                }
            } else {
                showToast(data.error || 'Update failed.');
                if (selectEl && prev) selectEl.value = prev;
            }
        })
        .catch(()=>{ if (selectEl) selectEl.disabled = false; showToast('Network error.'); if (selectEl && prev) selectEl.value = prev; });
}

/* ---- Equipment modal ---- */
function openEquipmentModal(eq){
    document.getElementById('equipmentForm').reset();
    if(eq){
        document.getElementById('equipmentModalTitle').textContent = 'Edit Equipment';
        document.getElementById('eId').value = eq.id;
        document.getElementById('eName').value = eq.name || '';
        document.getElementById('eNameMr').value = eq.name_mr || '';
        document.getElementById('eType').value = eq.type || 'tractor';
        document.getElementById('ePn').value = eq.pn || '';
        document.getElementById('eSerial').value = eq.serial_no || '';
        document.getElementById('eRent').value = eq.rent_per_day || '';
        document.getElementById('eHp').value = eq.hp || '';
        document.getElementById('eEngine').value = eq.engine || '';
        document.getElementById('eGears').value = eq.gears || '';
        document.getElementById('eLift').value = eq.lift_capacity || '';
        document.getElementById('eOwnerName').value = eq.owner_name || '';
        document.getElementById('eOwnerPhone').value = eq.owner_phone || '';
        document.getElementById('eCity').value = eq.city_name || '';
        document.getElementById('eAvailability').value = eq.availability ? '1' : '0';
        document.getElementById('eImage').value = eq.image || '';
        document.getElementById('eDesc').value = eq.description || '';

        document.getElementById('eNameHi').value = eq.name_hi || '';
        document.getElementById('eBrand').value = eq.brand || '';
        document.getElementById('eModel').value = eq.model || '';
        document.getElementById('eCondition').value = eq.equipment_condition || 'good';
        document.getElementById('eDeposit').value = eq.security_deposit || '';
        document.getElementById('eOperator').value = (eq.operator_available == 1) ? '1' : '0';
        document.getElementById('eFuel').value = (eq.fuel_included == 1) ? '1' : '0';
        document.getElementById('eTransport').value = (eq.transport_available == 1) ? '1' : '0';
        document.getElementById('eTransportCharge').value = eq.transport_charge || '';
        document.getElementById('eOwnerEmail').value = eq.owner_email || '';
        document.getElementById('eDistrict').value = eq.owner_district || '';
        document.getElementById('eAddress').value = eq.owner_address || '';
        document.getElementById('eRules').value = eq.rental_rules || '';

        document.getElementById('eOriginalNameWrap').style.display = 'none';
        if (eq.name_original) {
            document.getElementById('eOriginalNameWrap').style.display = '';
            const langLabel = { en:'English', mr:'Marathi', hi:'Hindi' }[eq.original_language] || eq.original_language || '';
            document.getElementById('eOriginalName').value = eq.name_original + (langLabel ? ' (' + langLabel + ')' : '');
        }

        document.getElementById('eGalleryWrap').style.display = 'none';
        document.getElementById('eGalleryThumbs').innerHTML = '';
        document.getElementById('eDocsWrap').style.display = 'none';
        document.getElementById('eDocsList').innerHTML = '';
        if (eq.owner_user_id) {
            fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_images', id:eq.id}) })
                .then(r=>r.json())
                .then(data=>{
                    if (data.success && data.images && data.images.length){
                        document.getElementById('eGalleryWrap').style.display = '';
                        document.getElementById('eGalleryThumbs').innerHTML = data.images.map(img =>
                            `<img src="../${img}" style="width:64px;height:64px;object-fit:cover;border-radius:8px;border:1px solid #eee">`
                        ).join('');
                    }
                })
                .catch(()=>{});
            fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'get_documents', id:eq.id}) })
                .then(r=>r.json())
                .then(data=>{
                    if (data.success && data.documents && data.documents.length){
                        document.getElementById('eDocsWrap').style.display = '';
                        document.getElementById('eDocsList').innerHTML = data.documents.map(doc =>
                            `<a href="../${doc.doc_path}" target="_blank" style="font-size:12.5px;color:#2E7D32"><i class="fa-solid fa-file"></i> ${doc.doc_name}</a>`
                        ).join('');
                    }
                })
                .catch(()=>{});
        }
    } else {
        document.getElementById('equipmentModalTitle').textContent = 'Add Equipment';
        document.getElementById('eId').value = '';
        document.getElementById('eOriginalNameWrap').style.display = 'none';
        document.getElementById('eGalleryWrap').style.display = 'none';
        document.getElementById('eDocsWrap').style.display = 'none';
    }
    document.getElementById('equipmentModalOverlay').classList.add('open');
}
function closeEquipmentModal(){ document.getElementById('equipmentModalOverlay').classList.remove('open'); }

document.getElementById('equipmentForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'save',
        id: document.getElementById('eId').value,
        name: document.getElementById('eName').value,
        name_mr: document.getElementById('eNameMr').value,
        name_hi: document.getElementById('eNameHi').value,
        type: document.getElementById('eType').value,
        pn: document.getElementById('ePn').value,
        serial_no: document.getElementById('eSerial').value,
        rent_per_day: document.getElementById('eRent').value,
        hp: document.getElementById('eHp').value,
        engine: document.getElementById('eEngine').value,
        gears: document.getElementById('eGears').value,
        lift_capacity: document.getElementById('eLift').value,
        owner_name: document.getElementById('eOwnerName').value,
        owner_phone: document.getElementById('eOwnerPhone').value,
        city: document.getElementById('eCity').value,
        availability: document.getElementById('eAvailability').value,
        image: document.getElementById('eImage').value,
        description: document.getElementById('eDesc').value,
        brand: document.getElementById('eBrand').value,
        model: document.getElementById('eModel').value,
        equipment_condition: document.getElementById('eCondition').value,
        security_deposit: document.getElementById('eDeposit').value,
        operator_available: document.getElementById('eOperator').value,
        fuel_included: document.getElementById('eFuel').value,
        transport_available: document.getElementById('eTransport').value,
        transport_charge: document.getElementById('eTransportCharge').value,
        owner_email: document.getElementById('eOwnerEmail').value,
        owner_district: document.getElementById('eDistrict').value,
        owner_address: document.getElementById('eAddress').value,
        rental_rules: document.getElementById('eRules').value
    });
    fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Equipment saved.'); closeEquipmentModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Save failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteEquipment(id){
    if(!confirm('Remove this equipment from the website? It will stay in this admin panel (marked "Removed") so you can restore it or permanently delete it later.')) return;
    fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Equipment removed from the website.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreEquipment(id){
    fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Equipment restored to the website.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function hardDeleteEquipment(id){
    if(!confirm('Permanently delete this equipment? This cannot be undone and will remove it from the admin panel as well as the website.')) return;
    fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'hard_delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Equipment permanently deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Permanent delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function approveEquipment(id){
    fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'approve', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Equipment approved — now live on the Rental Hub.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Approve failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function rejectEquipment(id){
    if(!confirm('Reject this farmer-submitted equipment? It will stay hidden from the Rental Hub.')) return;
    fetch('equipment_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'reject', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Equipment rejected.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Reject failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function updateBookingStatus(bookingId, status){
    fetch('booking_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({booking_id:bookingId, status}) })
        .then(r=>r.json())
        .then(data=>{
            showToast(data.success ? 'Booking status updated.' : (data.error || 'Update failed.'));
            if (data.success) setTimeout(()=>location.reload(), 500);
        })
        .catch(()=>showToast('Network error.'));
}

function updateBookingPaymentStatus(bookingId, payment_status){
    fetch('booking_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({booking_id:bookingId, field:'payment_status', payment_status}) })
        .then(r=>r.json())
        .then(data=>{ showToast(data.success ? 'Payment status updated.' : (data.error || 'Update failed.')); })
        .catch(()=>showToast('Network error.'));
}

/* ---- Seller modal ---- */
function openSellerModal(seller){
    document.getElementById('sellerForm').reset();
    document.getElementById('slId').value = seller ? seller.id : '';
    document.getElementById('sellerModalTitle').textContent = seller ? 'Edit Seller' : 'Add Seller';
    document.getElementById('slName').value = seller ? (seller.name || '') : '';
    document.getElementById('slMobile').value = seller ? (seller.mobile || '') : '';
    document.getElementById('slEmail').value = seller ? (seller.email || '') : '';
    document.getElementById('slVillage').value = seller ? (seller.village || '') : '';
    document.getElementById('slCity').value = seller ? (seller.city || '') : '';
    document.getElementById('slVerified').value = seller ? (seller.verified || '0') : '0';
    document.getElementById('slNotes').value = seller ? (seller.notes || '') : '';
    document.getElementById('sellerModalOverlay').classList.add('open');
}
function closeSellerModal(){ document.getElementById('sellerModalOverlay').classList.remove('open'); }

document.getElementById('sellerForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'save',
        id: document.getElementById('slId').value,
        name: document.getElementById('slName').value,
        mobile: document.getElementById('slMobile').value,
        email: document.getElementById('slEmail').value,
        village: document.getElementById('slVillage').value,
        city: document.getElementById('slCity').value,
        verified: document.getElementById('slVerified').value,
        notes: document.getElementById('slNotes').value
    });
    fetch('seller_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Seller saved.'); closeSellerModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Save failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteSeller(id, name){
    if(!confirm('Delete seller "' + name + '"? This does not delete their existing products.')) return;
    fetch('seller_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Seller deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreSeller(id){
    fetch('seller_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Seller restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

/* ---- Coupon modal ---- */
function openCouponModal(coupon){
    document.getElementById('couponForm').reset();
    document.getElementById('cpId').value = coupon ? coupon.id : '';
    document.getElementById('couponModalTitle').textContent = coupon ? 'Edit Coupon' : 'Add Coupon';
    document.getElementById('cpCode').value = coupon ? (coupon.code || '') : '';
    document.getElementById('cpType').value = coupon ? (coupon.discount_type || 'percent') : 'percent';
    document.getElementById('cpValue').value = coupon ? (coupon.discount_value || '') : '';
    document.getElementById('cpMinOrder').value = coupon ? (coupon.min_order_amount || 0) : 0;
    document.getElementById('cpMaxDiscount').value = coupon ? (coupon.max_discount_amount || '') : '';
    document.getElementById('cpUsageLimit').value = coupon ? (coupon.usage_limit || '') : '';
    document.getElementById('cpExpiry').value = coupon ? (coupon.expiry_date || '') : '';
    document.getElementById('cpActive').value = coupon ? String(coupon.active) : '1';
    document.getElementById('couponModalOverlay').classList.add('open');
}
function closeCouponModal(){ document.getElementById('couponModalOverlay').classList.remove('open'); }

function generateCouponCode(){
    fetch('coupon_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'generate'}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ document.getElementById('cpCode').value = data.code; }
            else { showToast(data.error || 'Could not generate a code.'); }
        })
        .catch(()=>showToast('Network error.'));
}

document.getElementById('couponForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'save',
        id: document.getElementById('cpId').value,
        code: document.getElementById('cpCode').value,
        discount_type: document.getElementById('cpType').value,
        discount_value: document.getElementById('cpValue').value,
        min_order_amount: document.getElementById('cpMinOrder').value,
        max_discount_amount: document.getElementById('cpMaxDiscount').value,
        usage_limit: document.getElementById('cpUsageLimit').value,
        expiry_date: document.getElementById('cpExpiry').value,
        active: document.getElementById('cpActive').value
    });
    fetch('coupon_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Coupon saved.'); closeCouponModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Save failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteCoupon(id, code){
    if(!confirm('Delete coupon "' + code + '"?')) return;
    fetch('coupon_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Coupon deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreCoupon(id){
    fetch('coupon_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Coupon restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

/* ---- Krishi Bazaar modal ---- */
function openBazaarModal(entry){
    document.getElementById('bazaarForm').reset();
    if(entry){
        document.getElementById('bazaarModalTitle').textContent = 'Edit Price Entry';
        document.getElementById('bId').value = entry.id;
        document.getElementById('bCropName').value = entry.crop_name || '';
        document.getElementById('bCropNameMr').value = entry.crop_name_mr || '';
        document.getElementById('bMarketName').value = entry.market_name || '';
        document.getElementById('bDistrict').value = entry.district || '';
        document.getElementById('bMinPrice').value = entry.min_price || '';
        document.getElementById('bMaxPrice').value = entry.max_price || '';
        document.getElementById('bModalPrice').value = entry.modal_price || '';
        document.getElementById('bUnit').value = entry.unit || 'quintal';
        document.getElementById('bPriceDate').value = entry.price_date || '';
    } else {
        document.getElementById('bazaarModalTitle').textContent = 'Add Price Entry';
        document.getElementById('bId').value = '';
    }
    document.getElementById('bazaarModalOverlay').classList.add('open');
}
function closeBazaarModal(){ document.getElementById('bazaarModalOverlay').classList.remove('open'); }

document.getElementById('bazaarForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'save',
        id: document.getElementById('bId').value,
        crop_name: document.getElementById('bCropName').value,
        crop_name_mr: document.getElementById('bCropNameMr').value,
        market_name: document.getElementById('bMarketName').value,
        district: document.getElementById('bDistrict').value,
        min_price: document.getElementById('bMinPrice').value,
        max_price: document.getElementById('bMaxPrice').value,
        modal_price: document.getElementById('bModalPrice').value,
        unit: document.getElementById('bUnit').value,
        price_date: document.getElementById('bPriceDate').value
    });
    fetch('krishi_bazaar_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Price entry saved.'); closeBazaarModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Save failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteBazaarPrice(id){
    if(!confirm('Delete this price entry?')) return;
    fetch('krishi_bazaar_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Price entry deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreBazaarPrice(id){
    fetch('krishi_bazaar_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Price entry restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

/* ---- Advisory modal ---- */
function openAdvisoryModal(post){
    document.getElementById('advisoryForm').reset();
    if(post){
        document.getElementById('advisoryModalTitle').textContent = 'Edit Advisory';
        document.getElementById('aId').value = post.id;
        document.getElementById('aTitle').value = post.title || '';
        document.getElementById('aTitleMr').value = post.title_mr || '';
        document.getElementById('aCrop').value = post.crop || '';
        document.getElementById('aImage').value = post.image || '';
        document.getElementById('aContent').value = post.content || '';
        document.getElementById('aContentMr').value = post.content_mr || '';
    } else {
        document.getElementById('advisoryModalTitle').textContent = 'Add Advisory';
        document.getElementById('aId').value = '';
    }
    document.getElementById('advisoryModalOverlay').classList.add('open');
}
function closeAdvisoryModal(){ document.getElementById('advisoryModalOverlay').classList.remove('open'); }

document.getElementById('advisoryForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'save',
        id: document.getElementById('aId').value,
        title: document.getElementById('aTitle').value,
        title_mr: document.getElementById('aTitleMr').value,
        crop: document.getElementById('aCrop').value,
        image: document.getElementById('aImage').value,
        content: document.getElementById('aContent').value,
        content_mr: document.getElementById('aContentMr').value
    });
    fetch('advisory_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Advisory saved.'); closeAdvisoryModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Save failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteAdvisory(id){
    if(!confirm('Delete this advisory post?')) return;
    fetch('advisory_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Advisory deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreAdvisory(id){
    fetch('advisory_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Advisory restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

/* ---- Agri-Connect moderation ---- */
function togglePost(id, field){
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'toggle_post', id, field}) })
        .then(r=>r.json())
        .then(data=>{ if(data.success){ showToast('Updated.'); setTimeout(()=>location.reload(), 500); } else { showToast(data.error || 'Update failed.'); } })
        .catch(()=>showToast('Network error.'));
}
function deletePost(id){
    if(!confirm('Delete this post? It will be hidden from Agri-Connect but you can restore it here anytime.')) return;
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete_post', id}) })
        .then(r=>r.json())
        .then(data=>{ if(data.success){ showToast('Post deleted.'); setTimeout(()=>location.reload(), 500); } else { showToast(data.error || 'Delete failed.'); } })
        .catch(()=>showToast('Network error.'));
}
function restorePost(id){
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore_post', id}) })
        .then(r=>r.json())
        .then(data=>{ if(data.success){ showToast('Post restored.'); setTimeout(()=>location.reload(), 500); } else { showToast(data.error || 'Restore failed.'); } })
        .catch(()=>showToast('Network error.'));
}
function toggleComment(id){
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'toggle_comment', id}) })
        .then(r=>r.json())
        .then(data=>{ if(data.success){ showToast('Updated.'); setTimeout(()=>location.reload(), 500); } else { showToast(data.error || 'Update failed.'); } })
        .catch(()=>showToast('Network error.'));
}
function deleteComment(id){
    if(!confirm('Delete this comment?')) return;
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete_comment', id}) })
        .then(r=>r.json())
        .then(data=>{ if(data.success){ showToast('Comment deleted.'); setTimeout(()=>location.reload(), 500); } else { showToast(data.error || 'Delete failed.'); } })
        .catch(()=>showToast('Network error.'));
}
function restoreComment(id){
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore_comment', id}) })
        .then(r=>r.json())
        .then(data=>{ if(data.success){ showToast('Comment restored.'); setTimeout(()=>location.reload(), 500); } else { showToast(data.error || 'Restore failed.'); } })
        .catch(()=>showToast('Network error.'));
}

/* ---- Add Comment ---- */
function openAddCommentModal(){
    document.getElementById('commentForm').reset();
    document.getElementById('commentModalOverlay').classList.add('open');
}
function closeAddCommentModal(){ document.getElementById('commentModalOverlay').classList.remove('open'); }

document.getElementById('commentForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'add_comment',
        post_id: document.getElementById('cPostId').value,
        author_name: document.getElementById('cAuthorName').value,
        body: document.getElementById('cBody').value
    });
    fetch('community_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Comment added.'); closeAddCommentModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Add failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

/* ---- Contact messages ---- */
function updateMessageStatus(id, status){
    fetch('contact_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'status', id, status}) })
        .then(r=>r.json())
        .then(data=>{ showToast(data.success ? 'Status updated.' : (data.error || 'Update failed.')); })
        .catch(()=>showToast('Network error.'));
}

function deleteContactMessage(id){
    if(!confirm('Delete this contact message?')) return;
    fetch('contact_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Message deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreContactMessage(id){
    fetch('contact_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Message restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function openAddMessageModal(){
    document.getElementById('messageAddForm').reset();
    document.getElementById('messageModalOverlay').classList.add('open');
}
function closeAddMessageModal(){ document.getElementById('messageModalOverlay').classList.remove('open'); }

document.getElementById('messageAddForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'add',
        name: document.getElementById('mName').value,
        phone: document.getElementById('mPhone').value,
        email: document.getElementById('mEmail').value,
        subject: document.getElementById('mSubject').value,
        message: document.getElementById('mMessage').value
    });
    fetch('contact_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Message added.'); closeAddMessageModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Add failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

/* ---- Feedback ---- */
function updateFeedbackStatus(id, status){
    fetch('feedback_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'status', id, status}) })
        .then(r=>r.json())
        .then(data=>{ showToast(data.success ? 'Status updated.' : (data.error || 'Update failed.')); })
        .catch(()=>showToast('Network error.'));
}

function deleteFeedback(id){
    if(!confirm('Delete this feedback entry?')) return;
    fetch('feedback_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Feedback deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreFeedback(id){
    fetch('feedback_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Feedback restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function openAddFeedbackModal(){
    document.getElementById('feedbackAddForm').reset();
    document.getElementById('feedbackModalOverlay').classList.add('open');
}
function closeAddFeedbackModal(){ document.getElementById('feedbackModalOverlay').classList.remove('open'); }

document.getElementById('feedbackAddForm').addEventListener('submit', function(e){
    e.preventDefault();
    const body = new URLSearchParams({
        action: 'add',
        rating: document.getElementById('fRating').value,
        page: document.getElementById('fPage').value,
        message: document.getElementById('fMessage').value
    });
    fetch('feedback_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Feedback added.'); closeAddFeedbackModal(); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Add failed.'); }
        })
        .catch(()=>showToast('Network error.'));
});

function deleteNewsletterSubscriber(id){
    if(!confirm('Remove this subscriber?')) return;
    fetch('newsletter_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Subscriber removed.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

function restoreNewsletterSubscriber(id){
    fetch('newsletter_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'restore', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('Subscriber restored.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Restore failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}

/* ---- Users ---- */
function updateUserRole(id, role){
    if(!confirm('Change this user\'s role to "' + role + '"?')) return;
    fetch('user_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({id, role}) })
        .then(r=>r.json())
        .then(data=>{ showToast(data.success ? 'Role updated.' : (data.error || 'Update failed.')); })
        .catch(()=>showToast('Network error.'));
}

function deleteUser(id, name){
    if(!confirm('Delete user "' + name + '"? This cannot be undone.')) return;
    fetch('user_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body:new URLSearchParams({action:'delete', id}) })
        .then(r=>r.json())
        .then(data=>{
            if(data.success){ showToast('User deleted.'); setTimeout(()=>location.reload(), 600); }
            else { showToast(data.error || 'Delete failed.'); }
        })
        .catch(()=>showToast('Network error.'));
}
</script>
<!-- Global smart form validation: auto-scrolls to + focuses the first
     invalid field on any form on this page (incl. inside modals/tabs).
     See assets/js/form-scroll-validate.js -->
<script src="../assets/js/form-scroll-validate.js?v=<?php echo @filemtime(__DIR__ . '/../assets/js/form-scroll-validate.js') ?: time(); ?>"></script>
</body>
</html>
