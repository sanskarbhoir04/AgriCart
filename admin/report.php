<?php
// =====================================================================
// admin/report.php — Enterprise Reports & Analytics module.
//
// Read-only reporting surface over the existing AgriCart schema: does
// not write to any table (except the one-time, idempotent permission
// row registered by reports_bootstrap_permission()), so it cannot
// break existing functionality no matter what state the data is in.
//
// Every stat/query below is wrapped through the rpt_*() helpers in
// includes/reports_schema.php, which swallow missing-table / missing-
// column errors and fall back to 0 / [] — the same defensive style
// admin/index.php already uses (see agri_connect_bootstrap_schema).
// So on an install that hasn't run every optional setup/*.sql yet,
// individual cards/charts just show 0 or "no data" instead of a
// fatal error.
//
// Gated by the 'reports.view' permission (Super Admin always passes).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/reports_schema.php';
reports_bootstrap_permission($conn);
requirePermission('reports.view');

// ---------------------------------------------------------------------
// Filter / query-string parsing (all whitelisted or type-cast — never
// interpolated raw into SQL; anything used in a WHERE clause goes
// through a prepared statement further down).
// ---------------------------------------------------------------------
$allowedTabs = ['overview', 'sales', 'products', 'rentals', 'orders', 'users', 'payments', 'reviews', 'activity'];
$tab = $_GET['tab'] ?? 'overview';
if (!in_array($tab, $allowedTabs, true)) { $tab = 'overview'; }

$allowedRanges = ['daily', 'weekly', 'monthly', 'yearly', 'custom'];
$range = $_GET['range'] ?? 'monthly';
if (!in_array($range, $allowedRanges, true)) { $range = 'monthly'; }
$fromDate = $_GET['from'] ?? '';
$toDate   = $_GET['to'] ?? '';
[$rangeStart, $rangeEnd] = rpt_date_bounds($range, $fromDate, $toDate);

$fCategory      = trim($_GET['category'] ?? '');
$fSeller        = trim($_GET['seller'] ?? '');
$fOrderStatus   = trim($_GET['order_status'] ?? '');
$fPaymentStatus = trim($_GET['payment_status'] ?? '');
$fRating        = trim($_GET['rating'] ?? '');
$fCity          = trim($_GET['city'] ?? '');
$fDistrict      = trim($_GET['district'] ?? '');
$fSearch        = trim($_GET['q'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$offset  = ($page - 1) * $perPage;

$csrfTok = function_exists('csrf_token') ? csrf_token() : '';

// ---------------------------------------------------------------------
// Filter option lists (used to populate the <select> dropdowns).
// ---------------------------------------------------------------------
$categoryOptions = array_column(rpt_rows($conn, "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC"), 'category');
$sellerOptions   = array_column(rpt_rows($conn, "SELECT DISTINCT farmer_name FROM products WHERE farmer_name IS NOT NULL AND farmer_name <> '' ORDER BY farmer_name ASC"), 'farmer_name');
$districtOptions = array_column(rpt_rows($conn, "SELECT DISTINCT district FROM users WHERE district IS NOT NULL AND district <> '' ORDER BY district ASC"), 'district');
$cityOptions     = array_column(rpt_rows($conn, "SELECT DISTINCT name FROM cities ORDER BY name ASC"), 'name');

// =======================================================================
// OVERVIEW — top-level KPI cards (19 cards per spec) + recent activity
// feed + a handful of summary charts.
// =======================================================================
$kpi = [];

$kpi['total_users']    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users");
$kpi['total_sellers']  = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'seller'");
$kpi['total_buyers']   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE role NOT IN ('seller','admin')");
$kpi['total_products'] = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1");
$kpi['total_rentals']  = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment_bookings");
$kpi['total_categories'] = (int)rpt_scalar($conn, "SELECT COUNT(DISTINCT category) FROM products WHERE category IS NOT NULL AND category <> ''");

$kpi['total_orders']      = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders");
$kpi['pending_orders']    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");
$kpi['processing_orders'] = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'processing'");
$kpi['delivered_orders']  = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'");
$kpi['cancelled_orders']  = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'cancelled'");
$kpi['returned_orders']   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'returned'");

$kpi['total_revenue'] = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE order_status NOT IN ('cancelled','returned')");
$kpi['rental_revenue'] = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(total_amount),0) FROM equipment_bookings WHERE status NOT IN ('cancelled')");

$kpi['seller_withdrawals']    = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(amount),0) FROM payouts WHERE status = 'completed'");
$kpi['pending_withdrawals']   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM payouts WHERE status IN ('pending','processing')");
$kpi['completed_withdrawals'] = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM payouts WHERE status = 'completed'");

$kpi['total_reviews'] = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM reviews");
$kpi['avg_rating']    = (float)rpt_scalar($conn, "SELECT COALESCE(AVG(rating),0) FROM reviews");

// Month-over-month trend for the handful of cards where "trending up/down" is meaningful.
$trendSql = "SELECT
    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN 1 ELSE 0 END) AS this_cnt,
    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m') THEN 1 ELSE 0 END) AS last_cnt,
    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') AND order_status NOT IN ('cancelled','returned') THEN COALESCE(final_amount,total_amount) ELSE 0 END) AS this_rev,
    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m') AND order_status NOT IN ('cancelled','returned') THEN COALESCE(final_amount,total_amount) ELSE 0 END) AS last_rev
    FROM orders";
$trendRow = rpt_rows($conn, $trendSql);
$trendRow = $trendRow[0] ?? ['this_cnt'=>0,'last_cnt'=>0,'this_rev'=>0,'last_rev'=>0];
$ordersTrend  = rpt_trend($trendRow['this_cnt'] ?? 0, $trendRow['last_cnt'] ?? 0);
$revenueTrend = rpt_trend($trendRow['this_rev'] ?? 0, $trendRow['last_rev'] ?? 0);

$usersTrendRow = rpt_rows($conn, "SELECT
    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') THEN 1 ELSE 0 END) AS this_cnt,
    SUM(CASE WHEN DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE() - INTERVAL 1 MONTH,'%Y-%m') THEN 1 ELSE 0 END) AS last_cnt
    FROM users");
$usersTrendRow = $usersTrendRow[0] ?? ['this_cnt'=>0,'last_cnt'=>0];
$usersTrend = rpt_trend($usersTrendRow['this_cnt'] ?? 0, $usersTrendRow['last_cnt'] ?? 0);

// ---- Recent activity feed (last 6 of each source, merged + sorted) ----
$activityFeed = [];
foreach (rpt_rows($conn, "SELECT o.id, o.order_number, o.created_at, o.total_amount, u.full_name FROM orders o LEFT JOIN users u ON u.id = o.user_id ORDER BY o.id DESC LIMIT 6") as $r) {
    $activityFeed[] = ['type' => 'order', 'icon' => 'fa-truck-fast', 'time' => $r['created_at'] ?? null,
        'text' => 'New order ' . ($r['order_number'] ?? ('#' . $r['id'])) . ' by ' . ($r['full_name'] ?? 'a customer') . ' — ' . rpt_money($r['total_amount'] ?? 0)];
}
foreach (rpt_rows($conn, "SELECT id, name, farmer_name, created_at FROM products ORDER BY id DESC LIMIT 6") as $r) {
    $activityFeed[] = ['type' => 'product', 'icon' => 'fa-cart-shopping', 'time' => $r['created_at'] ?? null,
        'text' => 'Product added: ' . ($r['name'] ?? '') . ' by ' . ($r['farmer_name'] ?? 'a seller')];
}
foreach (rpt_rows($conn, "SELECT eb.id, eb.booking_number, eb.created_at, e.name AS eq_name FROM equipment_bookings eb LEFT JOIN equipment e ON e.id = eb.equipment_id ORDER BY eb.id DESC LIMIT 6") as $r) {
    $activityFeed[] = ['type' => 'rental', 'icon' => 'fa-tractor', 'time' => $r['created_at'] ?? null,
        'text' => 'Equipment rental ' . ($r['booking_number'] ?? ('#' . $r['id'])) . ' for ' . ($r['eq_name'] ?? 'equipment')];
}
foreach (rpt_rows($conn, "SELECT id, full_name, created_at FROM users WHERE role = 'seller' ORDER BY id DESC LIMIT 6") as $r) {
    $activityFeed[] = ['type' => 'seller', 'icon' => 'fa-store', 'time' => $r['created_at'] ?? null,
        'text' => 'New seller registered: ' . ($r['full_name'] ?? '')];
}
foreach (rpt_rows($conn, "SELECT p.id, p.amount, p.requested_at, u.full_name FROM payouts p LEFT JOIN users u ON u.id = p.seller_id ORDER BY p.id DESC LIMIT 6") as $r) {
    $activityFeed[] = ['type' => 'withdrawal', 'icon' => 'fa-hand-holding-dollar', 'time' => $r['requested_at'] ?? null,
        'text' => 'Withdrawal request ' . rpt_money($r['amount'] ?? 0) . ' by ' . ($r['full_name'] ?? 'a seller')];
}
foreach (rpt_rows($conn, "SELECT r.id, r.rating, r.created_at, u.full_name FROM reviews r LEFT JOIN users u ON u.id = r.user_id ORDER BY r.id DESC LIMIT 6") as $r) {
    $activityFeed[] = ['type' => 'review', 'icon' => 'fa-star', 'time' => $r['created_at'] ?? null,
        'text' => 'New ' . (int)($r['rating'] ?? 0) . '★ review by ' . ($r['full_name'] ?? 'a customer')];
}
usort($activityFeed, function ($a, $b) { return strtotime($b['time'] ?? '1970-01-01') <=> strtotime($a['time'] ?? '1970-01-01'); });
$activityFeed = array_slice($activityFeed, 0, 15);

// ---- Overview charts: revenue trend (14 pts) + order-status pie + category pie ----
$ovRevenueTrend = array_reverse(rpt_rows($conn, "SELECT DATE(created_at) AS d, SUM(COALESCE(final_amount,total_amount)) AS total
    FROM orders WHERE order_status NOT IN ('cancelled','returned') GROUP BY d ORDER BY d DESC LIMIT 14"));
$ovOrderStatus = rpt_rows($conn, "SELECT order_status, COUNT(*) AS cnt FROM orders GROUP BY order_status");
$ovCategorySplit = rpt_rows($conn, "SELECT category, COUNT(*) AS cnt FROM products WHERE is_active = 1 AND category IS NOT NULL AND category <> '' GROUP BY category ORDER BY cnt DESC LIMIT 8");

// =======================================================================
// SALES REPORT
// =======================================================================
$salesTotals = rpt_prepared_rows($conn,
    "SELECT COUNT(*) AS orders_cnt, COALESCE(SUM(COALESCE(final_amount,total_amount)),0) AS revenue
       FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status NOT IN ('cancelled','returned')",
    'ss', [$rangeStart, $rangeEnd]);
$salesTotals = $salesTotals[0] ?? ['orders_cnt' => 0, 'revenue' => 0];
$salesOrderCount = (int)$salesTotals['orders_cnt'];
$salesRevenue    = (float)$salesTotals['revenue'];
$salesAOV        = $salesOrderCount > 0 ? $salesRevenue / $salesOrderCount : 0;

// Daily/weekly/monthly/yearly quick totals (independent of the selected range, for the summary strip).
$salesDaily   = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE DATE(created_at) = CURDATE() AND order_status NOT IN ('cancelled','returned')");
$salesWeekly  = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1) AND order_status NOT IN ('cancelled','returned')");
$salesMonthly = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m') AND order_status NOT IN ('cancelled','returned')");
$salesYearly  = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE YEAR(created_at) = YEAR(CURDATE()) AND order_status NOT IN ('cancelled','returned')");

// Revenue + order-count series across the selected range, bucketed by day (or by month if the range is long).
$rangeDays = (strtotime($rangeEnd) - strtotime($rangeStart)) / 86400;
$bucketFmt  = $rangeDays > 92 ? '%Y-%m' : '%Y-%m-%d';
$bucketDisp = $rangeDays > 92 ? '%b %Y' : '%d %b';
$salesSeries = rpt_prepared_rows($conn,
    "SELECT DATE_FORMAT(created_at, '$bucketFmt') AS bucket, DATE_FORMAT(created_at, '$bucketDisp') AS label,
            COALESCE(SUM(COALESCE(final_amount,total_amount)),0) AS revenue, COUNT(*) AS orders_cnt
       FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status NOT IN ('cancelled','returned')
       GROUP BY bucket, label ORDER BY bucket ASC",
    'ss', [$rangeStart, $rangeEnd]);

// =======================================================================
// PRODUCT REPORT
// =======================================================================
$prodWhere = ["1=1"]; $prodTypes = ''; $prodParams = [];
if ($fCategory !== '') { $prodWhere[] = "p.category = ?"; $prodTypes .= 's'; $prodParams[] = $fCategory; }
if ($fSeller !== '')   { $prodWhere[] = "p.farmer_name = ?"; $prodTypes .= 's'; $prodParams[] = $fSeller; }
$prodWhereSql = implode(' AND ', $prodWhere);

$bestSelling = rpt_rows($conn, "
    SELECT oi.product_id, oi.product_name, SUM(oi.quantity) AS qty_sold, SUM(oi.quantity * oi.price) AS revenue
      FROM order_items oi
      JOIN orders o ON o.id = oi.order_id
     WHERE o.order_status NOT IN ('cancelled','returned')
     GROUP BY oi.product_id, oi.product_name
     ORDER BY qty_sold DESC LIMIT 10");

// "Most viewed" needs a products.views counter that not every install has tracked yet.
$mostViewed = rpt_rows($conn, "SELECT id, name, views FROM products WHERE is_active = 1 ORDER BY views DESC LIMIT 10");
$viewsTracked = !empty($mostViewed);

$lowStock    = rpt_rows($conn, "SELECT id, name, farmer_name, category, stock FROM products WHERE is_active = 1 AND stock > 0 AND stock <= 10 ORDER BY stock ASC LIMIT 20");
$outOfStock  = rpt_rows($conn, "SELECT id, name, farmer_name, category, stock FROM products WHERE is_active = 1 AND (stock <= 0 OR stock IS NULL) ORDER BY id DESC LIMIT 20");

$categoryReport = rpt_rows($conn, "SELECT category, COUNT(*) AS product_cnt, AVG(price) AS avg_price, SUM(stock) AS total_stock
    FROM products WHERE is_active = 1 AND category IS NOT NULL AND category <> '' GROUP BY category ORDER BY product_cnt DESC");

$productSalesTotal = (int)rpt_prepared_scalar($conn, "SELECT COUNT(DISTINCT oi.product_id) FROM order_items oi JOIN orders o ON o.id = oi.order_id JOIN products p ON p.id = oi.product_id WHERE $prodWhereSql AND o.order_status NOT IN ('cancelled','returned')", $prodTypes, $prodParams);
$productSalesReport = rpt_prepared_rows($conn, "
    SELECT p.id, p.name, p.category, p.farmer_name, COALESCE(SUM(oi.quantity),0) AS qty_sold, COALESCE(SUM(oi.quantity*oi.price),0) AS revenue
      FROM products p
      LEFT JOIN order_items oi ON oi.product_id = p.id
      LEFT JOIN orders o ON o.id = oi.order_id AND o.order_status NOT IN ('cancelled','returned')
     WHERE $prodWhereSql
     GROUP BY p.id, p.name, p.category, p.farmer_name
     ORDER BY qty_sold DESC LIMIT $perPage OFFSET $offset", $prodTypes, $prodParams);

$sellerWiseReport = rpt_rows($conn, "
    SELECT p.farmer_name, COUNT(DISTINCT p.id) AS product_cnt, COALESCE(SUM(oi.quantity),0) AS qty_sold, COALESCE(SUM(oi.quantity*oi.price),0) AS revenue
      FROM products p
      LEFT JOIN order_items oi ON oi.product_id = p.id
      LEFT JOIN orders o ON o.id = oi.order_id AND o.order_status NOT IN ('cancelled','returned')
     WHERE p.farmer_name IS NOT NULL AND p.farmer_name <> ''
     GROUP BY p.farmer_name ORDER BY revenue DESC LIMIT 15");

// =======================================================================
// RENTAL REPORT
// =======================================================================
$rentalTotal     = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment_bookings");
$rentalActive    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment_bookings WHERE status IN ('pending','confirmed','on_the_way')");
$rentalCompleted = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment_bookings WHERE status = 'completed'");
$rentalCancelled = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment_bookings WHERE status = 'cancelled'");
$rentalRevenueTotal = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(total_amount),0) FROM equipment_bookings WHERE status = 'completed'");

// Optional City filter (equipment.city_id -> cities.name).
$rentalCityJoin  = $fCity !== '' ? "JOIN cities c ON c.id = e.city_id" : "";
$rentalCityWhere = $fCity !== '' ? "WHERE c.name = ?" : "";
$mostRentedEquipment = $fCity !== ''
    ? rpt_prepared_rows($conn, "
        SELECT e.id, e.name, COUNT(eb.id) AS booking_cnt, COALESCE(SUM(eb.total_amount),0) AS revenue
          FROM equipment_bookings eb JOIN equipment e ON e.id = eb.equipment_id $rentalCityJoin
         $rentalCityWhere GROUP BY e.id, e.name ORDER BY booking_cnt DESC LIMIT 10", 's', [$fCity])
    : rpt_rows($conn, "
        SELECT e.id, e.name, COUNT(eb.id) AS booking_cnt, COALESCE(SUM(eb.total_amount),0) AS revenue
          FROM equipment_bookings eb JOIN equipment e ON e.id = eb.equipment_id
         GROUP BY e.id, e.name ORDER BY booking_cnt DESC LIMIT 10");

if ($fCity !== '') {
    $equipmentAvailable   = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM equipment e JOIN cities c ON c.id = e.city_id WHERE e.availability = 1 AND c.name = ?", 's', [$fCity]);
    $equipmentUnavailable = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM equipment e JOIN cities c ON c.id = e.city_id WHERE e.availability = 0 AND c.name = ?", 's', [$fCity]);
} else {
    $equipmentAvailable   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment WHERE availability = 1");
    $equipmentUnavailable = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment WHERE availability = 0");
}

$rentalStatusChart = rpt_rows($conn, "SELECT status, COUNT(*) AS cnt FROM equipment_bookings GROUP BY status");

// =======================================================================
// ORDER REPORT (with status / date / search filters + pagination)
// =======================================================================
$ordWhere = ["1=1"]; $ordTypes = ''; $ordParams = [];
if ($fOrderStatus !== '') { $ordWhere[] = "o.order_status = ?"; $ordTypes .= 's'; $ordParams[] = $fOrderStatus; }
if ($fromDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) { $ordWhere[] = "DATE(o.created_at) >= ?"; $ordTypes .= 's'; $ordParams[] = $fromDate; }
if ($toDate !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate))   { $ordWhere[] = "DATE(o.created_at) <= ?"; $ordTypes .= 's'; $ordParams[] = $toDate; }
if ($fSearch !== '') {
    $ordWhere[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ?)";
    $like = '%' . $fSearch . '%';
    $ordTypes .= 'sss'; array_push($ordParams, $like, $like, $like);
}
$ordWhereSql = implode(' AND ', $ordWhere);

$orderReportTotal = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM orders o LEFT JOIN users u ON u.id = o.user_id WHERE $ordWhereSql", $ordTypes, $ordParams);
$orderReportPages = max(1, (int)ceil($orderReportTotal / $perPage));
$orderReportRows = rpt_prepared_rows($conn, "
    SELECT o.id, o.order_number, o.created_at, o.total_amount, o.final_amount, o.order_status, u.full_name, u.mobile
      FROM orders o LEFT JOIN users u ON u.id = o.user_id
     WHERE $ordWhereSql ORDER BY o.id DESC LIMIT $perPage OFFSET $offset", $ordTypes, $ordParams);

$orderStatusCounts = [
    'pending'    => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'"),
    'processing' => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'processing'"),
    'shipped'    => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'shipped'"),
    'delivered'  => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'delivered'"),
    'cancelled'  => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'cancelled'"),
    'returned'   => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'returned'"),
];

// =======================================================================
// USER REPORT
// =======================================================================
$userWhere = ["1=1"]; $userTypes = ''; $userParams = [];
if ($fDistrict !== '') { $userWhere[] = "district = ?"; $userTypes .= 's'; $userParams[] = $fDistrict; }
if ($fSearch !== '')   { $userWhere[] = "(full_name LIKE ? OR mobile LIKE ? OR email LIKE ?)"; $like = '%'.$fSearch.'%'; $userTypes .= 'sss'; array_push($userParams, $like, $like, $like); }
$userWhereSql = implode(' AND ', $userWhere);

$usersTotal      = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users");
$usersNewToday   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()");
$usersNewWeek    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE YEARWEEK(created_at,1) = YEARWEEK(CURDATE(),1)");
$usersNewMonth   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE DATE_FORMAT(created_at,'%Y-%m') = DATE_FORMAT(CURDATE(),'%Y-%m')");
$usersSellersCnt = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE role = 'seller'");
$usersBuyersCnt  = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE role NOT IN ('seller','admin')");

// "Active" = logged in during the last 30 days if we can tell (last_login_at column);
// otherwise fall back to "placed at least one order in the last 30 days" as a proxy.
try {
    $usersActive = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM users WHERE last_login_at >= (NOW() - INTERVAL 30 DAY)", -1);
    if ($usersActive < 0) { throw new \Exception('no column'); }
    $activeUsersBasis = 'last login';
} catch (\Throwable $e) {
    $usersActive = (int)rpt_scalar($conn, "SELECT COUNT(DISTINCT user_id) FROM orders WHERE created_at >= (NOW() - INTERVAL 30 DAY)");
    $activeUsersBasis = 'placed an order';
}

$userRegistrationSeries = array_reverse(rpt_rows($conn, "SELECT DATE_FORMAT(created_at,'%Y-%m') AS ym, DATE_FORMAT(created_at,'%b %Y') AS label, COUNT(*) AS cnt
    FROM users GROUP BY ym ORDER BY ym DESC LIMIT 12"));

$userReportTotal = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM users WHERE $userWhereSql", $userTypes, $userParams);
$userReportPages = max(1, (int)ceil($userReportTotal / $perPage));
$userReportRows = rpt_prepared_rows($conn, "SELECT id, full_name, mobile, email, district, role, created_at FROM users WHERE $userWhereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset", $userTypes, $userParams);

// =======================================================================
// PAYMENT REPORT
// =======================================================================
// Orders in this schema don't consistently expose a payment_status column
// across installs, so "successful/pending/failed" is derived from
// order_status as a safe proxy when the real column isn't there.
$hasOrderPaymentStatus = false;
try {
    $probe = $conn->query("SELECT payment_status FROM orders LIMIT 1");
    $hasOrderPaymentStatus = ($probe !== false);
} catch (\Throwable $e) { $hasOrderPaymentStatus = false; }

if ($hasOrderPaymentStatus) {
    $paymentsTotal      = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders");
    $paymentsSuccessful = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE payment_status = 'paid'");
    $paymentsPending    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE payment_status = 'pending'");
    $paymentsFailed     = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE payment_status = 'failed'");
} else {
    $paymentsTotal      = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders");
    $paymentsSuccessful = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status NOT IN ('cancelled','returned')");
    $paymentsPending    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'pending'");
    $paymentsFailed     = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM orders WHERE order_status = 'cancelled'");
}
$refundAmount = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(COALESCE(final_amount,total_amount)),0) FROM orders WHERE order_status = 'returned'");

$payoutWhere = ["1=1"]; $payoutTypes = ''; $payoutParams = [];
if ($fPaymentStatus !== '') { $payoutWhere[] = "p.status = ?"; $payoutTypes .= 's'; $payoutParams[] = $fPaymentStatus; }
$payoutWhereSql = implode(' AND ', $payoutWhere);
$withdrawalHistoryTotal = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM payouts p WHERE $payoutWhereSql", $payoutTypes, $payoutParams);
$withdrawalHistoryPages = max(1, (int)ceil($withdrawalHistoryTotal / $perPage));
$withdrawalHistory = rpt_prepared_rows($conn, "
    SELECT p.id, p.amount, p.method, p.status, p.requested_at, p.completed_at, u.full_name, u.mobile
      FROM payouts p LEFT JOIN users u ON u.id = p.seller_id
     WHERE $payoutWhereSql ORDER BY p.id DESC LIMIT $perPage OFFSET $offset", $payoutTypes, $payoutParams);

// =======================================================================
// REVIEW REPORT
// =======================================================================
$reviewsTotal   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM reviews");
$reviewsAvg     = (float)rpt_scalar($conn, "SELECT COALESCE(AVG(rating),0) FROM reviews");

// Moderation status is optional — not every reviews table has been upgraded with it yet.
$hasReviewStatus = false;
try { $hasReviewStatus = ($conn->query("SELECT status FROM reviews LIMIT 1") !== false); } catch (\Throwable $e) { $hasReviewStatus = false; }
if ($hasReviewStatus) {
    $reviewsPending  = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM reviews WHERE status = 'pending'");
    $reviewsApproved = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM reviews WHERE status = 'approved'");
    $reviewsRejected = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM reviews WHERE status = 'rejected'");
} else {
    $reviewsPending = $reviewsApproved = $reviewsRejected = null; // signals "not tracked" in the UI
}

$reviewRatingDist = rpt_rows($conn, "SELECT rating, COUNT(*) AS cnt FROM reviews GROUP BY rating ORDER BY rating DESC");

$mostReviewedProducts = rpt_rows($conn, "
    SELECT p.id, p.name, COUNT(r.id) AS review_cnt, COALESCE(AVG(r.rating),0) AS avg_rating
      FROM reviews r JOIN products p ON p.id = r.item_id AND r.item_type = 'product'
     GROUP BY p.id, p.name ORDER BY review_cnt DESC LIMIT 10");

$reviewWhere = ["1=1"]; $reviewTypes = ''; $reviewParams = [];
if ($fRating !== '' && ctype_digit($fRating)) { $reviewWhere[] = "r.rating = ?"; $reviewTypes .= 'i'; $reviewParams[] = (int)$fRating; }
$reviewWhereSql = implode(' AND ', $reviewWhere);
$reviewListTotal = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM reviews r WHERE $reviewWhereSql", $reviewTypes, $reviewParams);
$reviewListPages = max(1, (int)ceil($reviewListTotal / $perPage));
$reviewListRows = rpt_prepared_rows($conn, "
    SELECT r.id, r.rating, r.comment, r.created_at, r.item_type, r.item_id, u.full_name,
           CASE WHEN r.item_type='product' THEN p.name WHEN r.item_type='equipment' THEN e.name ELSE NULL END AS item_name
      FROM reviews r
      LEFT JOIN users u ON u.id = r.user_id
      LEFT JOIN products p ON r.item_type = 'product' AND p.id = r.item_id
      LEFT JOIN equipment e ON r.item_type = 'equipment' AND e.id = r.item_id
     WHERE $reviewWhereSql ORDER BY r.id DESC LIMIT $perPage OFFSET $offset", $reviewTypes, $reviewParams);

$pageTitle     = 'Reports & Analytics';
$activeTeamTab = 'reports';
include __DIR__ . '/includes/team_layout_top.php';
?>
<link rel="stylesheet" href="assets/css/report.css">
<style>
/* ---- Polished stat cards (Sales / Products / Rentals / Orders / Users / Payments / Reviews) ---- */
.stat-strip{ display:grid; grid-template-columns:repeat(auto-fit,minmax(190px,1fr)); gap:14px; margin-bottom:18px; }
.stat-mini{
    position:relative;
    display:flex;
    align-items:center;
    gap:12px;
    padding:16px 16px 16px 14px;
    border-radius:14px;
    border:1px solid var(--border,#e6e9ec);
    background:var(--card-bg,#fff);
    overflow:hidden;
    text-decoration:none;
    color:inherit;
    transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease;
}
.stat-mini::before{
    content:"";
    position:absolute;
    left:0; top:0; bottom:0;
    width:4px;
    background:var(--stat-accent,#16a34a);
    opacity:.85;
}
.stat-mini-icon{
    flex:0 0 auto;
    width:42px; height:42px;
    border-radius:11px;
    display:flex; align-items:center; justify-content:center;
    font-size:16px;
    background:color-mix(in srgb, var(--stat-accent,#16a34a) 14%, transparent);
    color:var(--stat-accent,#16a34a);
}
.stat-mini-body{ min-width:0; flex:1; }
.stat-mini-label{
    font-size:11.5px; font-weight:600; letter-spacing:.03em; text-transform:uppercase;
    color:var(--muted,#6b7280); margin-bottom:3px;
}
.stat-mini-value{ font-size:20px; font-weight:700; line-height:1.15; color:var(--text,#111827); transition:color .18s ease; }

.stat-mini.stat-primary{ --stat-accent:#2563eb; }
.stat-mini.stat-success{ --stat-accent:#16a34a; }
.stat-mini.stat-warning{ --stat-accent:#d97706; }
.stat-mini.stat-danger{  --stat-accent:#dc2626; }
.stat-mini.stat-accent{  --stat-accent:#7c3aed; }
.stat-mini.stat-info{    --stat-accent:#0891b2; }

.stat-mini:hover{
    transform:translateY(-4px);
    box-shadow:0 12px 26px rgba(15,23,42,.10);
    border-color:var(--stat-accent,#16a34a);
}
.stat-mini-clickable{ cursor:pointer; }
.stat-mini-clickable:hover .stat-mini-value{ color:var(--stat-accent,#16a34a); }
.stat-mini-clickable::after{
    content:"\f105";
    font-family:"Font Awesome 6 Free"; font-weight:900;
    position:absolute; right:14px; top:50%;
    transform:translate(-4px,-50%);
    opacity:0; color:var(--stat-accent,#16a34a); font-size:13px;
    transition:opacity .18s ease, transform .18s ease;
}
.stat-mini-clickable:hover::after{ opacity:1; transform:translate(0,-50%); }
</style>

<?php
// Small local helpers for the view layer only (safe to redeclare-guard since this file is only included once).
function rpt_tag_class($status) {
    $status = strtolower((string)$status);
    if (in_array($status, ['delivered','completed','approved','paid','active'], true)) return 'active';
    if (in_array($status, ['cancelled','rejected','failed'], true)) return 'suspended';
    if (in_array($status, ['returned','expired'], true)) return 'expired';
    return 'pending';
}
function rpt_fmt_date($d) { return $d ? date('d M Y, h:i A', strtotime($d)) : '—'; }
function rpt_qs(array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
/**
 * Renders one stat-mini card. Pass $link (array of query overrides, same shape
 * used by rpt_qs()) to make the card a clickable, hover-highlighted <a> — matching
 * the KPI cards on the Overview tab. Omit $link for a purely informational card
 * (still gets the icon + hover lift, just no navigation).
 */
function rpt_stat_card($icon, $label, $value, $color = 'primary', $link = null, $hint = null) {
    $href = $link ? rpt_qs(array_merge(['page' => 1], $link)) : null;
    $tag  = $href ? 'a' : 'div';
    $cls  = 'card stat-mini stat-' . $color . ($href ? ' stat-mini-clickable' : '');
    ob_start(); ?>
    <<?php echo $tag; ?><?php echo $href ? ' href="' . htmlspecialchars($href) . '"' : ''; ?> class="<?php echo $cls; ?>">
        <div class="stat-mini-icon"><i class="fa-solid <?php echo $icon; ?>"></i></div>
        <div class="stat-mini-body">
            <div class="stat-mini-label"><?php echo htmlspecialchars($label); ?></div>
            <div class="stat-mini-value"><?php echo $value; ?></div>
            <?php if ($hint): ?><div class="hint" style="margin-top:3px"><?php echo htmlspecialchars($hint); ?></div><?php endif; ?>
        </div>
    </<?php echo $tag; ?>>
    <?php
    return ob_get_clean();
}
?>

<div class="rpt-toolbar">
    <div class="rpt-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="rptGlobalSearch" placeholder="Search orders, products, users..." value="<?php echo htmlspecialchars($fSearch); ?>">
    </div>
    <div class="rpt-toolbar-actions">
        <button class="btn outline sm" onclick="rptExport('csv')"><i class="fa-solid fa-file-csv"></i> CSV</button>
        <button class="btn outline sm" onclick="rptExport('excel')"><i class="fa-solid fa-file-excel"></i> Excel</button>
        <button class="btn outline sm" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>
        <button class="btn sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    </div>
</div>

<div class="rpt-tabs no-print">
    <?php
    $tabDefs = [
        'overview' => ['icon' => 'fa-gauge-high', 'label' => 'Overview'],
        'sales'    => ['icon' => 'fa-chart-line', 'label' => 'Sales'],
        'products' => ['icon' => 'fa-box', 'label' => 'Products'],
        'rentals'  => ['icon' => 'fa-tractor', 'label' => 'Rentals'],
        'orders'   => ['icon' => 'fa-truck-fast', 'label' => 'Orders'],
        'users'    => ['icon' => 'fa-users', 'label' => 'Users'],
        'payments' => ['icon' => 'fa-money-bill-wave', 'label' => 'Payments'],
        'reviews'  => ['icon' => 'fa-star', 'label' => 'Reviews'],
        'activity' => ['icon' => 'fa-clock-rotate-left', 'label' => 'Activity'],
    ];
    foreach ($tabDefs as $key => $def): ?>
        <a href="<?php echo rpt_qs(['tab' => $key, 'page' => 1]); ?>" class="rpt-tab <?php echo $tab === $key ? 'active' : ''; ?>">
            <i class="fa-solid <?php echo $def['icon']; ?>"></i> <?php echo $def['label']; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php if ($tab === 'overview'): ?>
<div class="rpt-panel">
    <div class="kpi-grid">
        <?php
        $cards = [
            ['icon' => 'fa-users', 'label' => 'Total Users', 'value' => $kpi['total_users'], 'trend' => $usersTrend, 'color' => 'primary', 'link' => ['tab' => 'users']],
            ['icon' => 'fa-store', 'label' => 'Total Sellers', 'value' => $kpi['total_sellers'], 'color' => 'accent', 'link' => ['tab' => 'users']],
            ['icon' => 'fa-user', 'label' => 'Total Buyers', 'value' => $kpi['total_buyers'], 'color' => 'primary', 'link' => ['tab' => 'users']],
            ['icon' => 'fa-box', 'label' => 'Total Products', 'value' => $kpi['total_products'], 'color' => 'success', 'link' => ['tab' => 'products']],
            ['icon' => 'fa-tractor', 'label' => 'Equipment Rentals', 'value' => $kpi['total_rentals'], 'color' => 'accent', 'link' => ['tab' => 'rentals']],
            ['icon' => 'fa-layer-group', 'label' => 'Total Categories', 'value' => $kpi['total_categories'], 'color' => 'primary', 'link' => ['tab' => 'products']],
            ['icon' => 'fa-truck-fast', 'label' => 'Total Orders', 'value' => $kpi['total_orders'], 'trend' => $ordersTrend, 'color' => 'success', 'link' => ['tab' => 'orders']],
            ['icon' => 'fa-hourglass-half', 'label' => 'Pending Orders', 'value' => $kpi['pending_orders'], 'color' => 'warn', 'link' => ['tab' => 'orders', 'order_status' => 'pending']],
            ['icon' => 'fa-spinner', 'label' => 'Processing Orders', 'value' => $kpi['processing_orders'], 'color' => 'warn', 'link' => ['tab' => 'orders', 'order_status' => 'processing']],
            ['icon' => 'fa-circle-check', 'label' => 'Delivered Orders', 'value' => $kpi['delivered_orders'], 'color' => 'success', 'link' => ['tab' => 'orders', 'order_status' => 'delivered']],
            ['icon' => 'fa-circle-xmark', 'label' => 'Cancelled Orders', 'value' => $kpi['cancelled_orders'], 'color' => 'danger', 'link' => ['tab' => 'orders', 'order_status' => 'cancelled']],
            ['icon' => 'fa-rotate-left', 'label' => 'Returned Orders', 'value' => $kpi['returned_orders'], 'color' => 'danger', 'link' => ['tab' => 'orders', 'order_status' => 'returned']],
            ['icon' => 'fa-sack-dollar', 'label' => 'Total Revenue', 'value' => rpt_money($kpi['total_revenue']), 'trend' => $revenueTrend, 'color' => 'success', 'link' => ['tab' => 'sales']],
            ['icon' => 'fa-coins', 'label' => 'Rental Revenue', 'value' => rpt_money($kpi['rental_revenue']), 'color' => 'accent', 'link' => ['tab' => 'rentals']],
            ['icon' => 'fa-hand-holding-dollar', 'label' => 'Seller Withdrawals', 'value' => rpt_money($kpi['seller_withdrawals']), 'color' => 'primary', 'link' => ['tab' => 'payments']],
            ['icon' => 'fa-clock', 'label' => 'Pending Withdrawals', 'value' => $kpi['pending_withdrawals'], 'color' => 'warn', 'link' => ['tab' => 'payments']],
            ['icon' => 'fa-check-double', 'label' => 'Completed Withdrawals', 'value' => $kpi['completed_withdrawals'], 'color' => 'success', 'link' => ['tab' => 'payments']],
            ['icon' => 'fa-comment-dots', 'label' => 'Total Reviews', 'value' => $kpi['total_reviews'], 'color' => 'primary', 'link' => ['tab' => 'reviews']],
            ['icon' => 'fa-star', 'label' => 'Average Rating', 'value' => number_format($kpi['avg_rating'], 1) . ' / 5', 'color' => 'accent', 'link' => ['tab' => 'reviews']],
        ];
        foreach ($cards as $c):
            $cardHref = isset($c['link']) ? rpt_qs(array_merge(['page' => 1], $c['link'])) : null;
            ?>
            <<?php echo $cardHref ? 'a href="' . $cardHref . '"' : 'div'; ?> class="kpi-card kpi-<?php echo $c['color']; ?><?php echo $cardHref ? ' kpi-clickable' : ''; ?>">
                <div class="kpi-icon"><i class="fa-solid <?php echo $c['icon']; ?>"></i></div>
                <div class="kpi-body">
                    <div class="kpi-value"><?php echo is_numeric($c['value']) ? number_format((float)$c['value']) : $c['value']; ?></div>
                    <div class="kpi-label"><?php echo htmlspecialchars($c['label']); ?></div>
                    <?php if (!empty($c['trend'])): ?>
                        <div class="kpi-trend <?php echo $c['trend']['up'] ? 'up' : 'down'; ?>">
                            <i class="fa-solid fa-arrow-<?php echo $c['trend']['up'] ? 'up' : 'down'; ?>"></i>
                            <?php echo abs($c['trend']['pct']); ?>% vs last month
                        </div>
                    <?php endif; ?>
                </div>
            </<?php echo $cardHref ? 'a' : 'div'; ?>>
        <?php endforeach; ?>
    </div>

    <div class="rpt-chart-grid">
        <div class="card rpt-chart-card">
            <div class="card-head"><h2>Revenue Trend (last 14 days)</h2></div>
            <div class="chart-box"><canvas id="chartOvRevenue"></canvas></div>
        </div>
        <div class="card rpt-chart-card">
            <div class="card-head"><h2>Orders by Status</h2></div>
            <div class="chart-box"><canvas id="chartOvOrderStatus"></canvas></div>
        </div>
        <div class="card rpt-chart-card">
            <div class="card-head"><h2>Products by Category</h2></div>
            <div class="chart-box"><canvas id="chartOvCategory"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Recent Activity</h2><a href="<?php echo rpt_qs(['tab'=>'activity']); ?>" class="btn outline sm">View All</a></div>
        <div class="rpt-activity-list">
            <?php if (empty($activityFeed)): ?>
                <div class="empty-state"><i class="fa-solid fa-inbox"></i>No recent activity yet.</div>
            <?php else: foreach (array_slice($activityFeed, 0, 8) as $a): ?>
                <div class="rpt-activity-item">
                    <div class="rpt-activity-icon"><i class="fa-solid <?php echo $a['icon']; ?>"></i></div>
                    <div class="rpt-activity-text">
                        <div><?php echo htmlspecialchars($a['text']); ?></div>
                        <span class="rpt-activity-time"><?php echo rpt_fmt_date($a['time']); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'sales'): ?>
<div class="rpt-panel">
    <form method="get" class="filters">
        <input type="hidden" name="tab" value="sales">
        <select name="range" onchange="this.form.submit()">
            <?php foreach (['daily'=>'Today','weekly'=>'This Week','monthly'=>'This Month','yearly'=>'This Year','custom'=>'Custom Range'] as $val=>$label): ?>
                <option value="<?php echo $val; ?>" <?php echo $range===$val?'selected':''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($range === 'custom'): ?>
            <input type="date" name="from" value="<?php echo htmlspecialchars($fromDate ?: $rangeStart); ?>">
            <input type="date" name="to" value="<?php echo htmlspecialchars($toDate ?: $rangeEnd); ?>">
        <?php endif; ?>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-calendar-day', 'Daily Sales', rpt_money($salesDaily), 'primary', ['tab' => 'sales', 'range' => 'daily']);
        echo rpt_stat_card('fa-calendar-week', 'Weekly Sales', rpt_money($salesWeekly), 'primary', ['tab' => 'sales', 'range' => 'weekly']);
        echo rpt_stat_card('fa-calendar', 'Monthly Sales', rpt_money($salesMonthly), 'primary', ['tab' => 'sales', 'range' => 'monthly']);
        echo rpt_stat_card('fa-calendar-days', 'Yearly Sales', rpt_money($salesYearly), 'primary', ['tab' => 'sales', 'range' => 'yearly']);
        ?>
    </div>

    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-sack-dollar', 'Total Revenue (range)', rpt_money($salesRevenue), 'success');
        echo rpt_stat_card('fa-truck-fast', 'Total Orders (range)', number_format($salesOrderCount), 'info', ['tab' => 'orders', 'from' => $rangeStart, 'to' => $rangeEnd]);
        echo rpt_stat_card('fa-chart-simple', 'Average Order Value', rpt_money($salesAOV), 'accent');
        ?>
    </div>

    <div class="rpt-chart-grid two-col">
        <div class="card rpt-chart-card">
            <div class="card-head"><h2>Revenue Chart</h2></div>
            <div class="chart-box"><canvas id="chartSalesRevenue"></canvas></div>
        </div>
        <div class="card rpt-chart-card">
            <div class="card-head"><h2>Sales Trend (Orders)</h2></div>
            <div class="chart-box"><canvas id="chartSalesTrend"></canvas></div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'products'): ?>
<div class="rpt-panel">
    <form method="get" class="filters">
        <input type="hidden" name="tab" value="products">
        <select name="category">
            <option value="">All Categories</option>
            <?php foreach ($categoryOptions as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $fCategory===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="seller">
            <option value="">All Sellers</option>
            <?php foreach ($sellerOptions as $s): ?>
                <option value="<?php echo htmlspecialchars($s); ?>" <?php echo $fSeller===$s?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Best Selling Products</h2></div>
            <table>
                <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($bestSelling)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-box-open"></i>No sales yet.</div></td></tr><?php endif; ?>
                <?php foreach ($bestSelling as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['product_name'] ?? ('#'.$r['product_id'])); ?></td>
                        <td><?php echo (int)$r['qty_sold']; ?></td><td><?php echo rpt_money($r['revenue']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Product Sales Chart</h2></div>
            <div class="chart-box"><canvas id="chartProductSales"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Most Viewed Products</h2></div>
        <?php if (!$viewsTracked): ?>
            <div class="empty-state"><i class="fa-solid fa-eye-slash"></i>This install doesn't track product view counts yet (no <code>products.views</code> column) — showing best sellers instead is the closest proxy available.</div>
        <?php else: ?>
            <table>
                <thead><tr><th>Product</th><th>Views</th></tr></thead>
                <tbody>
                <?php foreach ($mostViewed as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo (int)$r['views']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Low Stock Products <span class="tag pending">≤ 10 units</span></h2></div>
            <table>
                <thead><tr><th>Product</th><th>Seller</th><th>Stock</th></tr></thead>
                <tbody>
                <?php if (empty($lowStock)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing running low.</div></td></tr><?php endif; ?>
                <?php foreach ($lowStock as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['farmer_name'] ?? '—'); ?></td>
                        <td><span class="tag pending"><?php echo (int)$r['stock']; ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Out of Stock Products</h2></div>
            <table>
                <thead><tr><th>Product</th><th>Seller</th><th>Category</th></tr></thead>
                <tbody>
                <?php if (empty($outOfStock)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing out of stock.</div></td></tr><?php endif; ?>
                <?php foreach ($outOfStock as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['farmer_name'] ?? '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['category'] ?? '—'); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Product Category Report</h2></div>
        <table>
            <thead><tr><th>Category</th><th>Products</th><th>Avg Price</th><th>Total Stock</th></tr></thead>
            <tbody>
            <?php if (empty($categoryReport)): ?><tr><td colspan="4"><div class="empty-state">No categories yet.</div></td></tr><?php endif; ?>
            <?php foreach ($categoryReport as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['category']); ?></td><td><?php echo (int)$r['product_cnt']; ?></td>
                    <td><?php echo rpt_money($r['avg_price']); ?></td><td><?php echo (int)$r['total_stock']; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <div class="card-head"><h2>Product Sales Report</h2></div>
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Product</th><th>Category</th><th>Seller</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php if (empty($productSalesReport)): ?><tr><td colspan="5"><div class="empty-state">No products found.</div></td></tr><?php endif; ?>
            <?php foreach ($productSalesReport as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['category'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['farmer_name'] ?? '—'); ?></td><td><?php echo (int)$r['qty_sold']; ?></td>
                    <td><?php echo rpt_money($r['revenue']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php $pTotalPages = max(1, (int)ceil($productSalesTotal / $perPage)); if ($pTotalPages > 1): ?>
        <div class="pagination">
            <?php for ($p=1;$p<=$pTotalPages;$p++): ?>
                <a href="<?php echo rpt_qs(['page'=>$p]); ?>" class="<?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-head"><h2>Seller-wise Product Report</h2></div>
        <table>
            <thead><tr><th>Seller</th><th>Products</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
            <tbody>
            <?php if (empty($sellerWiseReport)): ?><tr><td colspan="4"><div class="empty-state">No sellers yet.</div></td></tr><?php endif; ?>
            <?php foreach ($sellerWiseReport as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['farmer_name']); ?></td><td><?php echo (int)$r['product_cnt']; ?></td>
                    <td><?php echo (int)$r['qty_sold']; ?></td><td><?php echo rpt_money($r['revenue']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'rentals'): ?>
<div class="rpt-panel">
    <form method="get" class="filters">
        <input type="hidden" name="tab" value="rentals">
        <select name="city">
            <option value="">All Cities</option>
            <?php foreach ($cityOptions as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo $fCity===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-tractor', 'Total Rentals', number_format($rentalTotal), 'primary');
        echo rpt_stat_card('fa-truck-fast', 'Active Rentals', number_format($rentalActive), 'warning');
        echo rpt_stat_card('fa-circle-check', 'Completed', number_format($rentalCompleted), 'success');
        echo rpt_stat_card('fa-circle-xmark', 'Cancelled', number_format($rentalCancelled), 'danger');
        echo rpt_stat_card('fa-coins', 'Rental Revenue', rpt_money($rentalRevenueTotal), 'accent');
        ?>
    </div>

    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Most Rented Equipment</h2></div>
            <table>
                <thead><tr><th>Equipment</th><th>Bookings</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($mostRentedEquipment)): ?><tr><td colspan="3"><div class="empty-state">No rentals yet.</div></td></tr><?php endif; ?>
                <?php foreach ($mostRentedEquipment as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo (int)$r['booking_cnt']; ?></td><td><?php echo rpt_money($r['revenue']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Rental Statistics</h2></div>
            <div class="chart-box"><canvas id="chartRentalStatus"></canvas></div>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Equipment Availability Report</h2></div>
        <div class="stat-strip">
            <?php
            echo rpt_stat_card('fa-circle-check', 'Available', number_format($equipmentAvailable), 'success');
            echo rpt_stat_card('fa-circle-xmark', 'Unavailable', number_format($equipmentUnavailable), 'danger');
            ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'orders'): ?>
<div class="rpt-panel">
    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-hourglass-half', 'Pending', number_format($orderStatusCounts['pending']), 'warning', ['tab' => 'orders', 'order_status' => 'pending']);
        echo rpt_stat_card('fa-spinner', 'Processing', number_format($orderStatusCounts['processing']), 'info', ['tab' => 'orders', 'order_status' => 'processing']);
        echo rpt_stat_card('fa-truck', 'Shipped', number_format($orderStatusCounts['shipped']), 'primary', ['tab' => 'orders', 'order_status' => 'shipped']);
        echo rpt_stat_card('fa-circle-check', 'Delivered', number_format($orderStatusCounts['delivered']), 'success', ['tab' => 'orders', 'order_status' => 'delivered']);
        echo rpt_stat_card('fa-circle-xmark', 'Cancelled', number_format($orderStatusCounts['cancelled']), 'danger', ['tab' => 'orders', 'order_status' => 'cancelled']);
        echo rpt_stat_card('fa-rotate-left', 'Returned', number_format($orderStatusCounts['returned']), 'danger', ['tab' => 'orders', 'order_status' => 'returned']);
        ?>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="tab" value="orders">
        <select name="order_status">
            <option value="">All Statuses</option>
            <?php foreach (['pending','processing','shipped','delivered','cancelled','returned'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $fOrderStatus===$st?'selected':''; ?>><?php echo ucfirst($st); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="from" value="<?php echo htmlspecialchars($fromDate); ?>">
        <input type="date" name="to" value="<?php echo htmlspecialchars($toDate); ?>">
        <input type="text" name="q" placeholder="Search order # / customer / mobile" value="<?php echo htmlspecialchars($fSearch); ?>" style="min-width:240px">
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="card">
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th>Amount</th><th>Status</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($orderReportRows)): ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i>No orders match these filters.</div></td></tr><?php endif; ?>
            <?php foreach ($orderReportRows as $r): ?>
                <tr style="cursor:pointer" onclick="openOrderDetails(<?php echo (int)$r['id']; ?>)">
                    <td><strong><?php echo htmlspecialchars($r['order_number'] ?? ('#'.$r['id'])); ?></strong></td>
                    <td><?php echo htmlspecialchars($r['full_name'] ?? '—'); ?><br><span style="color:var(--muted);font-size:11.5px"><?php echo htmlspecialchars($r['mobile'] ?? ''); ?></span></td>
                    <td><?php echo rpt_fmt_date($r['created_at']); ?></td>
                    <td><?php echo rpt_money($r['final_amount'] ?? $r['total_amount']); ?></td>
                    <td><span class="tag <?php echo rpt_tag_class($r['order_status']); ?>"><?php echo htmlspecialchars(ucfirst($r['order_status'])); ?></span></td>
                    <td onclick="event.stopPropagation()"><a href="invoice.php?order_id=<?php echo (int)$r['id']; ?>" target="_blank" class="btn outline sm"><i class="fa-solid fa-file-invoice"></i> Invoice</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($orderReportPages > 1): ?>
        <div class="pagination">
            <?php for ($p=1;$p<=$orderReportPages;$p++): ?>
                <a href="<?php echo rpt_qs(['page'=>$p]); ?>" class="<?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'users'): ?>
<div class="rpt-panel">
    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-users', 'Total Registered', number_format($usersTotal), 'primary');
        echo rpt_stat_card('fa-user-plus', 'New Today', number_format($usersNewToday), 'info');
        echo rpt_stat_card('fa-calendar-week', 'New This Week', number_format($usersNewWeek), 'info');
        echo rpt_stat_card('fa-calendar', 'New This Month', number_format($usersNewMonth), 'info');
        echo rpt_stat_card('fa-bolt', 'Active Users', number_format($usersActive), 'success', null, 'based on: ' . $activeUsersBasis . ' (30d)');
        echo rpt_stat_card('fa-store', 'Sellers', number_format($usersSellersCnt), 'accent');
        echo rpt_stat_card('fa-user', 'Buyers', number_format($usersBuyersCnt), 'accent');
        ?>
    </div>

    <div class="card rpt-chart-card">
        <div class="card-head"><h2>User Registration Trend (12 months)</h2></div>
        <div class="chart-box"><canvas id="chartUserRegistration"></canvas></div>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="tab" value="users">
        <select name="district">
            <option value="">All Districts</option>
            <?php foreach ($districtOptions as $d): ?>
                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo $fDistrict===$d?'selected':''; ?>><?php echo htmlspecialchars($d); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" placeholder="Search name / mobile / email" value="<?php echo htmlspecialchars($fSearch); ?>" style="min-width:240px">
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="card">
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Name</th><th>Mobile</th><th>District</th><th>Role</th><th>Joined</th></tr></thead>
            <tbody>
            <?php if (empty($userReportRows)): ?><tr><td colspan="5"><div class="empty-state">No users found.</div></td></tr><?php endif; ?>
            <?php foreach ($userReportRows as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['full_name']); ?></td><td><?php echo htmlspecialchars($r['mobile']); ?></td>
                    <td><?php echo htmlspecialchars($r['district'] ?? '—'); ?></td>
                    <td><span class="role-badge"><?php echo htmlspecialchars(ucfirst($r['role'] ?? 'farmer')); ?></span></td>
                    <td><?php echo rpt_fmt_date($r['created_at']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($userReportPages > 1): ?>
        <div class="pagination">
            <?php for ($p=1;$p<=$userReportPages;$p++): ?>
                <a href="<?php echo rpt_qs(['page'=>$p]); ?>" class="<?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'payments'): ?>
<div class="rpt-panel">
    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-money-bill-wave', 'Total Payments', number_format($paymentsTotal), 'primary');
        echo rpt_stat_card('fa-circle-check', 'Successful', number_format($paymentsSuccessful), 'success');
        echo rpt_stat_card('fa-hourglass-half', 'Pending', number_format($paymentsPending), 'warning');
        echo rpt_stat_card('fa-circle-xmark', 'Failed', number_format($paymentsFailed), 'danger');
        echo rpt_stat_card('fa-rotate-left', 'Refund Amount', rpt_money($refundAmount), 'danger');
        ?>
    </div>
    <?php if (!$hasOrderPaymentStatus): ?>
        <div class="hint" style="margin:-8px 0 16px"><i class="fa-solid fa-circle-info"></i> This install's <code>orders</code> table has no dedicated <code>payment_status</code> column yet, so the counts above are derived from <code>order_status</code> as a safe proxy.</div>
    <?php endif; ?>

    <form method="get" class="filters">
        <input type="hidden" name="tab" value="payments">
        <select name="payment_status">
            <option value="">All Withdrawal Statuses</option>
            <?php foreach (['pending'=>'Pending','processing'=>'Processing','completed'=>'Completed','rejected'=>'Rejected'] as $val=>$label): ?>
                <option value="<?php echo $val; ?>" <?php echo $fPaymentStatus===$val?'selected':''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="card">
        <div class="card-head"><h2>Seller Withdrawal History</h2></div>
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Seller</th><th>Amount</th><th>Method</th><th>Requested</th><th>Status</th></tr></thead>
            <tbody>
            <?php if (empty($withdrawalHistory)): ?><tr><td colspan="5"><div class="empty-state">No withdrawal requests yet.</div></td></tr><?php endif; ?>
            <?php foreach ($withdrawalHistory as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['full_name'] ?? ('Seller #'.$r['id'])); ?></td>
                    <td><?php echo rpt_money($r['amount']); ?></td><td><?php echo htmlspecialchars(strtoupper($r['method'] ?? '—')); ?></td>
                    <td><?php echo rpt_fmt_date($r['requested_at']); ?></td>
                    <td><span class="tag <?php echo rpt_tag_class($r['status']); ?>"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($withdrawalHistoryPages > 1): ?>
        <div class="pagination">
            <?php for ($p=1;$p<=$withdrawalHistoryPages;$p++): ?>
                <a href="<?php echo rpt_qs(['page'=>$p]); ?>" class="<?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'reviews'): ?>
<div class="rpt-panel">
    <div class="stat-strip">
        <?php
        echo rpt_stat_card('fa-comment-dots', 'Total Reviews', number_format($reviewsTotal), 'primary');
        echo rpt_stat_card('fa-star', 'Average Rating', number_format($reviewsAvg, 1) . ' / 5', 'accent');
        echo rpt_stat_card('fa-hourglass-half', 'Pending', $reviewsPending === null ? 'N/A' : number_format($reviewsPending), 'warning');
        echo rpt_stat_card('fa-circle-check', 'Approved', $reviewsApproved === null ? 'N/A' : number_format($reviewsApproved), 'success');
        echo rpt_stat_card('fa-circle-xmark', 'Rejected', $reviewsRejected === null ? 'N/A' : number_format($reviewsRejected), 'danger');
        ?>
    </div>
    <?php if (!$hasReviewStatus): ?>
        <div class="hint" style="margin:-8px 0 16px"><i class="fa-solid fa-circle-info"></i> This install's <code>reviews</code> table has no moderation <code>status</code> column yet — every review is shown, none are separated into pending/approved/rejected.</div>
    <?php endif; ?>

    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Most Reviewed Products</h2></div>
            <table>
                <thead><tr><th>Product</th><th>Reviews</th><th>Avg Rating</th></tr></thead>
                <tbody>
                <?php if (empty($mostReviewedProducts)): ?><tr><td colspan="3"><div class="empty-state">No product reviews yet.</div></td></tr><?php endif; ?>
                <?php foreach ($mostReviewedProducts as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo (int)$r['review_cnt']; ?></td><td>★ <?php echo number_format($r['avg_rating'],1); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Rating Distribution</h2></div>
            <div class="chart-box"><canvas id="chartRatingDist"></canvas></div>
        </div>
    </div>

    <form method="get" class="filters">
        <input type="hidden" name="tab" value="reviews">
        <select name="rating">
            <option value="">All Ratings</option>
            <?php for ($s=5;$s>=1;$s--): ?>
                <option value="<?php echo $s; ?>" <?php echo $fRating===(string)$s?'selected':''; ?>><?php echo $s; ?> Star</option>
            <?php endfor; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>

    <div class="card">
        <div style="overflow-x:auto">
        <table>
            <thead><tr><th>Item</th><th>Rating</th><th>Comment</th><th>By</th><th>Date</th></tr></thead>
            <tbody>
            <?php if (empty($reviewListRows)): ?><tr><td colspan="5"><div class="empty-state">No reviews found.</div></td></tr><?php endif; ?>
            <?php foreach ($reviewListRows as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['item_name'] ?? ucfirst($r['item_type'])); ?></td>
                    <td>★ <?php echo (int)$r['rating']; ?></td>
                    <td style="max-width:260px"><?php echo htmlspecialchars($r['comment'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['full_name'] ?? '—'); ?></td>
                    <td><?php echo rpt_fmt_date($r['created_at']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($reviewListPages > 1): ?>
        <div class="pagination">
            <?php for ($p=1;$p<=$reviewListPages;$p++): ?>
                <a href="<?php echo rpt_qs(['page'=>$p]); ?>" class="<?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<?php if ($tab === 'activity'): ?>
<div class="rpt-panel">
    <div class="card">
        <div class="card-head"><h2>Recent Activity — full feed</h2></div>
        <div class="rpt-activity-list">
            <?php if (empty($activityFeed)): ?>
                <div class="empty-state"><i class="fa-solid fa-inbox"></i>No recent activity yet.</div>
            <?php else: foreach ($activityFeed as $a): ?>
                <div class="rpt-activity-item">
                    <div class="rpt-activity-icon"><i class="fa-solid <?php echo $a['icon']; ?>"></i></div>
                    <div class="rpt-activity-text">
                        <div><?php echo htmlspecialchars($a['text']); ?></div>
                        <span class="rpt-activity-time"><?php echo rpt_fmt_date($a['time']); ?></span>
                    </div>
                </div>
            <?php endforeach; endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="assets/vendor/chart.umd.js"></script>
<script>
// Server-computed data handed off to assets/js/report.js for Chart.js rendering.
// Only the chart(s) relevant to the active tab actually get drawn (see report.js),
// so this stays cheap even though every dataset is emitted on every tab load.
window.RPT_DATA = {
    activeTab: <?php echo json_encode($tab); ?>,
    csrfToken: <?php echo json_encode($csrfTok); ?>,
    overview: {
        revenueTrend: <?php echo json_encode(array_map(fn($r) => ['label' => date('d M', strtotime($r['d'])), 'value' => (float)$r['total']], $ovRevenueTrend)); ?>,
        orderStatus: <?php echo json_encode(array_map(fn($r) => ['label' => ucfirst($r['order_status'] ?? 'unknown'), 'value' => (int)$r['cnt']], $ovOrderStatus)); ?>,
        categorySplit: <?php echo json_encode(array_map(fn($r) => ['label' => $r['category'], 'value' => (int)$r['cnt']], $ovCategorySplit)); ?>
    },
    sales: {
        revenue: <?php echo json_encode(array_map(fn($r) => ['label' => $r['label'], 'value' => (float)$r['revenue']], $salesSeries)); ?>,
        orders: <?php echo json_encode(array_map(fn($r) => ['label' => $r['label'], 'value' => (int)$r['orders_cnt']], $salesSeries)); ?>
    },
    products: {
        sales: <?php echo json_encode(array_map(fn($r) => ['label' => $r['product_name'] ?? ('#'.$r['product_id']), 'value' => (int)$r['qty_sold']], $bestSelling)); ?>
    },
    rentals: {
        status: <?php echo json_encode(array_map(fn($r) => ['label' => ucfirst($r['status'] ?? 'unknown'), 'value' => (int)$r['cnt']], $rentalStatusChart)); ?>
    },
    users: {
        registration: <?php echo json_encode(array_map(fn($r) => ['label' => $r['label'], 'value' => (int)$r['cnt']], $userRegistrationSeries)); ?>
    },
    reviews: {
        ratingDist: <?php echo json_encode(array_map(fn($r) => ['label' => $r['rating'].' Star', 'value' => (int)$r['cnt']], $reviewRatingDist)); ?>
    }
};
</script>
<script src="assets/js/report.js"></script>

<!-- Order Details Popup -->
<style>
.order-popup-box{max-width:600px}
.od-row{display:grid;grid-template-columns:150px 1fr;gap:8px;padding:7px 0;border-bottom:1px solid var(--border);font-size:13px}
.od-row:last-child{border-bottom:none}
.od-row dt{color:var(--muted)}
.od-items-table{width:100%;border-collapse:collapse;margin-top:14px;font-size:13px}
.od-items-table th{text-align:left;background:var(--bg-soft);padding:8px 10px;font-size:11px;text-transform:uppercase;color:var(--muted)}
.od-items-table td{padding:8px 10px;border-bottom:1px solid var(--border)}
</style>
<div class="modal-overlay" id="modalOrderDetails">
    <div class="modal-box order-popup-box">
        <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:4px">
            <h3 id="odOrderNumber">&nbsp;</h3>
            <a id="odInvoiceLink" href="#" target="_blank" class="btn sm"><i class="fa-solid fa-file-invoice"></i> Invoice</a>
        </div>
        <div id="odBody">
            <div style="text-align:center;padding:30px;color:var(--muted)">Loading…</div>
        </div>
        <div class="modal-actions">
            <button type="button" class="btn outline" onclick="closeModal('modalOrderDetails')">Close</button>
        </div>
    </div>
</div>
<script>
async function openOrderDetails(orderId){
    document.getElementById('odOrderNumber').textContent = 'Order Details';
    document.getElementById('odInvoiceLink').href = 'invoice.php?order_id=' + orderId;
    document.getElementById('odBody').innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)">Loading…</div>';
    openModal('modalOrderDetails');

    const fd = new FormData();
    fd.append('action', 'get_details');
    fd.append('order_id', orderId);
    try {
        const res = await fetch('order_action.php', {method: 'POST', body: fd});
        const data = await res.json();
        if (!data.success) {
            document.getElementById('odBody').innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)">' + (data.error || 'Could not load order.') + '</div>';
            return;
        }
        const o = data.order;
        document.getElementById('odOrderNumber').textContent = o.order_number || ('#' + o.id);

        const finalAmt = (o.final_amount !== undefined && o.final_amount !== null && o.final_amount !== '') ? Number(o.final_amount) : Number(o.total_amount || 0);
        const esc = (s) => (s === null || s === undefined || s === '') ? null : String(s).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
        const row = (label, val) => val ? `<div class="od-row"><dt>${label}</dt><dd>${val}</dd></div>` : '';

        const accountLine = (o.account_name || o.account_email)
            ? [esc(o.account_name), esc(o.account_email), esc(o.account_mobile)].filter(Boolean).join(' · ')
            : null;

        let html = '<dl>';
        html += row('Order Date', esc(o.created_at));
        html += row('Status', o.order_status ? o.order_status.charAt(0).toUpperCase() + o.order_status.slice(1) : null);
        html += row('Ordered By', accountLine);
        html += row('Delivery Contact', [esc(o.delivery_name), esc(o.delivery_mobile)].filter(Boolean).join(' · ') || null);
        const addrParts = [];
        if (o.delivery_address_full) addrParts.push(esc(o.delivery_address_full));
        if (o.delivery_city_state) addrParts.push(esc(o.delivery_city_state));
        if (o.delivery_pin_full) addrParts.push(esc(o.delivery_pin_full));
        html += row('Delivery Address', addrParts.length ? addrParts.join(', ') : null);
        html += row('Payment Mode', o.payment_mode ? String(o.payment_mode).toUpperCase() : null);
        html += row('Payment / Txn ID', esc(o.payment_id || o.transaction_id));
        html += row('Subtotal', o.total_amount ? '₹' + Number(o.total_amount).toLocaleString() : null);
        html += row('Coupon', esc(o.coupon_code));
        html += row('Discount', o.discount_amount > 0 ? '−₹' + Number(o.discount_amount).toLocaleString() : null);
        html += row('Final Amount', '₹' + finalAmt.toLocaleString());
        html += '</dl>';

        const items = o.items || [];
        if (items.length) {
            html += '<table class="od-items-table"><thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead><tbody>';
            items.forEach(it => {
                html += `<tr><td>${esc(it.product_name) || ''}</td><td>${esc(it.quantity) || ''}</td><td>${it.price ? '₹' + Number(it.price).toLocaleString() : '—'}</td></tr>`;
            });
            html += '</tbody></table>';
        } else {
            html += '<div style="color:var(--muted);font-size:13px;margin-top:10px">No item details found for this order.</div>';
        }

        document.getElementById('odBody').innerHTML = html;
    } catch (err) {
        document.getElementById('odBody').innerHTML = '<div style="text-align:center;padding:30px;color:var(--muted)">Network error — please try again.</div>';
    }
}
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
