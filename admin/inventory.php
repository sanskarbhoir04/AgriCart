<?php
// =====================================================================
// admin/inventory.php — Enterprise Inventory Management module.
//
// Single sidebar entry ("Inventory") covering Product Inventory and
// Equipment Inventory in one page via tabs, following the exact same
// standalone-page pattern already established by report.php (own
// ?tab= navigation, team_layout_top/bottom shell, assets/css/report.css
// reused for the kpi-card / rpt-tabs / chart-box styling so the module
// matches the rest of the Admin Panel pixel-for-pixel with zero new CSS
// files needed).
//
// Read-mostly: the only writes on this page are the one-time additive
// schema bootstrap (inventory_bootstrap_schema — new columns/table, never
// touches existing data) and stock actions which all go through
// inventory_action.php via fetch(), never inline here.
//
// Gated by the 'inventory.view' permission (Super Admin always passes).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/reports_schema.php';
require_once __DIR__ . '/includes/inventory_schema.php';
inventory_bootstrap_schema($conn);
requirePermission('inventory.view');

$allowedTabs = ['dashboard', 'products', 'equipment', 'alerts', 'reports', 'history'];
$tab = $_GET['tab'] ?? 'dashboard';
if (!in_array($tab, $allowedTabs, true)) { $tab = 'dashboard'; }

$csrfTok = function_exists('csrf_token') ? csrf_token() : '';

function inv_qs(array $overrides = []) {
    $params = array_merge($_GET, $overrides);
    return '?' . http_build_query($params);
}
function inv_fmt_date($d) { return $d ? date('d M Y, h:i A', strtotime($d)) : '—'; }

function inv_kpi_card(array $c): void {
    $tag = !empty($c['href']) ? 'a' : 'div';
    $hrefAttr = !empty($c['href']) ? ' href="' . htmlspecialchars($c['href']) . '"' : '';
    $clickable = !empty($c['href']) ? ' kpi-clickable' : '';
    echo "<$tag class=\"kpi-card kpi-{$c['color']}{$clickable}\"{$hrefAttr}>";
    echo '<div class="kpi-icon"><i class="fa-solid ' . $c['icon'] . '"></i></div>';
    echo '<div class="kpi-body">';
    echo '<div class="kpi-value">' . (is_numeric($c['value']) ? number_format((float)$c['value']) : $c['value']) . '</div>';
    echo '<div class="kpi-label">' . htmlspecialchars($c['label']) . '</div>';
    if (!empty($c['href'])) { echo '<div class="kpi-goto">View details <i class="fa-solid fa-arrow-right"></i></div>'; }
    echo '</div>';
    if (!empty($c['href'])) { echo '<i class="fa-solid fa-chevron-right kpi-chevron"></i>'; }
    echo "</$tag>";
}

function inv_stat_pill(array $c): void {
    $tag = !empty($c['href']) ? 'a' : 'div';
    $hrefAttr = !empty($c['href']) ? ' href="' . htmlspecialchars($c['href']) . '"' : '';
    $clickable = !empty($c['href']) ? ' inv-stat-clickable' : '';
    echo "<$tag class=\"inv-stat-pill inv-tint-{$c['tint']}{$clickable}\"{$hrefAttr}>";
    echo '<div class="inv-stat-icon"><i class="fa-solid ' . $c['icon'] . '"></i></div>';
    echo '<div>';
    echo '<div class="inv-stat-value">' . (is_numeric($c['value']) ? number_format((float)$c['value']) : $c['value']) . '</div>';
    echo '<div class="inv-stat-label">' . htmlspecialchars($c['label']) . '</div>';
    echo '</div>';
    echo "</$tag>";
}

// ---------------------------------------------------------------------
// Shared lookups used across multiple tabs
// ---------------------------------------------------------------------
$categoryOptions = array_column(rpt_rows($conn, "SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC"), 'category');
$sellerOptions   = array_column(rpt_rows($conn, "SELECT DISTINCT farmer_name FROM products WHERE farmer_name IS NOT NULL AND farmer_name <> '' ORDER BY farmer_name ASC"), 'farmer_name');
$equipTypeOptions = array_column(rpt_rows($conn, "SELECT DISTINCT type FROM equipment WHERE type IS NOT NULL AND type <> '' ORDER BY type ASC"), 'type');
$equipOwnerOptions = array_column(rpt_rows($conn, "SELECT DISTINCT owner_name FROM equipment WHERE owner_name IS NOT NULL AND owner_name <> '' ORDER BY owner_name ASC"), 'owner_name');

// Equipment IDs with a booking that covers "today" — used everywhere we need to know if a unit is currently rented out.
$today = date('Y-m-d');
$activeBookingIds = [];
foreach (rpt_rows($conn, "SELECT DISTINCT equipment_id FROM equipment_bookings WHERE status IN ('confirmed','on_the_way') AND from_date <= '$today' AND to_date >= '$today'") as $r) {
    $activeBookingIds[(int)$r['equipment_id']] = true;
}

// =======================================================================
// DASHBOARD DATA
// =======================================================================
if ($tab === 'dashboard') {
    $totalProducts   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1");
    $inStockCount    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock > low_stock_threshold");
    $lowStockCount   = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND stock > 0 AND stock <= low_stock_threshold");
    $outOfStockCount = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active = 1 AND (stock <= 0 OR stock IS NULL)");
    $productStockValue = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(stock * price),0) FROM products WHERE is_active = 1");

    $totalEquipment    = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment");
    $availableEquipCount = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment WHERE availability = 1 AND (maintenance_status = 'available' OR maintenance_status IS NULL)");
    $maintenanceEquipCount = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment WHERE maintenance_status = 'maintenance'");
    $outOfServiceEquipCount = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment WHERE availability = 0 OR maintenance_status = 'out_of_service'");
    $rentedEquipCount = count($activeBookingIds);
    $equipmentValue = (float)rpt_scalar($conn, "SELECT COALESCE(SUM(equipment_value),0) FROM equipment");

    $totalInventoryItems = $totalProducts + $totalEquipment;
    $totalInventoryValue = $productStockValue + $equipmentValue;
    $todaysUpdates = (int)rpt_scalar($conn, "SELECT COUNT(*) FROM inventory_stock_history WHERE DATE(created_at) = CURDATE()");
    $recentActivity = rpt_rows($conn, "SELECT * FROM inventory_stock_history ORDER BY id DESC LIMIT 8");

    $movementByDay = rpt_rows($conn, "SELECT DATE(created_at) AS d, COUNT(*) AS c FROM inventory_stock_history WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY) GROUP BY DATE(created_at) ORDER BY d ASC");
    $movementMap = [];
    foreach ($movementByDay as $r) { $movementMap[$r['d']] = (int)$r['c']; }
    $movementLabels = []; $movementValues = [];
    for ($i = 13; $i >= 0; $i--) {
        $d = date('Y-m-d', strtotime("-$i days"));
        $movementLabels[] = date('d M', strtotime($d));
        $movementValues[] = $movementMap[$d] ?? 0;
    }

    $topSelling = rpt_rows($conn, "SELECT oi.product_id AS id, oi.product_name AS name, SUM(oi.quantity) AS qty,
            MAX(p.category) AS category, MAX(p.farmer_name) AS farmer_name, MAX(p.sku) AS sku,
            MAX(p.stock) AS stock, MAX(p.price) AS price, MAX(p.image) AS image, MAX(p.low_stock_threshold) AS low_stock_threshold
        FROM order_items oi JOIN orders o ON o.id = oi.order_id
        LEFT JOIN products p ON p.id = oi.product_id
       WHERE o.order_status NOT IN ('cancelled','returned')
       GROUP BY oi.product_id, oi.product_name ORDER BY qty DESC LIMIT 6");

    $mostRented = rpt_rows($conn, "SELECT e.id AS id, e.name AS name, COUNT(*) AS bookings,
            e.type AS type, e.owner_name AS owner_name, e.availability AS availability,
            e.maintenance_status AS maintenance_status, e.rent_per_day AS rent_per_day,
            e.equipment_value AS equipment_value, e.image AS image, e.pn AS pn, e.serial_no AS serial_no
        FROM equipment_bookings eb JOIN equipment e ON e.id = eb.equipment_id
       GROUP BY e.id, e.name ORDER BY bookings DESC LIMIT 6");
}

// =======================================================================
// PRODUCT INVENTORY DATA
// =======================================================================
if ($tab === 'products') {
    $q = trim($_GET['q'] ?? '');
    $fCategory = trim($_GET['category'] ?? '');
    $fSeller   = trim($_GET['seller'] ?? '');
    $fStatus   = trim($_GET['status'] ?? '');
    $sort      = $_GET['sort'] ?? 'id_desc';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;
    $offset = ($page - 1) * $perPage;

    $showDeleted = ($fStatus === 'deleted');
    $where = [$showDeleted ? "is_active = 0" : "is_active = 1"]; $types = ''; $params = [];
    if ($q !== '')        { $where[] = "(name LIKE ? OR sku LIKE ?)"; $types .= 'ss'; $like = "%$q%"; array_push($params, $like, $like); }
    if ($fCategory !== '') { $where[] = "category = ?"; $types .= 's'; $params[] = $fCategory; }
    if ($fSeller !== '')   { $where[] = "farmer_name = ?"; $types .= 's'; $params[] = $fSeller; }
    if ($fStatus === 'in_stock')  { $where[] = "stock > low_stock_threshold"; }
    if ($fStatus === 'low_stock') { $where[] = "stock > 0 AND stock <= low_stock_threshold"; }
    if ($fStatus === 'out_of_stock') { $where[] = "(stock <= 0 OR stock IS NULL)"; }
    $whereSql = implode(' AND ', $where);

    $sortMap = [
        'id_desc' => 'id DESC', 'name_asc' => 'name ASC', 'stock_asc' => 'stock ASC',
        'stock_desc' => 'stock DESC', 'value_desc' => '(stock*price) DESC',
    ];
    $orderSql = $sortMap[$sort] ?? 'id DESC';

    $totalCount = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM products WHERE $whereSql", $types, $params);
    $products = rpt_prepared_rows($conn, "SELECT id, name, sku, category, farmer_name, image, stock, price, low_stock_threshold, updated_at FROM products WHERE $whereSql ORDER BY $orderSql LIMIT $perPage OFFSET $offset", $types, $params);

    // Reserved (qty in orders not yet delivered/cancelled) + Sold (delivered) per product, one query for the current page.
    $ids = array_column($products, 'id');
    $reservedMap = []; $soldMap = [];
    if (!empty($ids)) {
        $idsIn = implode(',', array_map('intval', $ids));
        foreach (rpt_rows($conn, "SELECT oi.product_id, SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id IN ($idsIn) AND o.order_status IN ('pending','processing','shipped') GROUP BY oi.product_id") as $r) {
            $reservedMap[(int)$r['product_id']] = (int)$r['qty'];
        }
        foreach (rpt_rows($conn, "SELECT oi.product_id, SUM(oi.quantity) AS qty FROM order_items oi JOIN orders o ON o.id = oi.order_id WHERE oi.product_id IN ($idsIn) AND o.order_status = 'delivered' GROUP BY oi.product_id") as $r) {
            $soldMap[(int)$r['product_id']] = (int)$r['qty'];
        }
    }
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
}

// =======================================================================
// EQUIPMENT INVENTORY DATA
// =======================================================================
if ($tab === 'equipment') {
    $q = trim($_GET['q'] ?? '');
    $fType   = trim($_GET['type'] ?? '');
    $fOwner  = trim($_GET['owner'] ?? '');
    $fStatus = trim($_GET['status'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 15;
    $offset = ($page - 1) * $perPage;

    $where = ["1=1"]; $types = ''; $params = [];
    if ($q !== '')      { $where[] = "(name LIKE ? OR pn LIKE ? OR serial_no LIKE ?)"; $types .= 'sss'; $like = "%$q%"; array_push($params, $like, $like, $like); }
    if ($fType !== '')  { $where[] = "type = ?"; $types .= 's'; $params[] = $fType; }
    if ($fOwner !== '') { $where[] = "owner_name = ?"; $types .= 's'; $params[] = $fOwner; }
    if ($fStatus === 'available')      { $where[] = "availability = 1 AND (maintenance_status = 'available' OR maintenance_status IS NULL)"; }
    if ($fStatus === 'rented')         { $where[] = "id IN (SELECT equipment_id FROM equipment_bookings WHERE status IN ('confirmed','on_the_way') AND from_date <= '$today' AND to_date >= '$today')"; }
    if ($fStatus === 'maintenance')    { $where[] = "maintenance_status = 'maintenance'"; }
    if ($fStatus === 'out_of_service') { $where[] = "(availability = 0 OR maintenance_status = 'out_of_service')"; }
    $whereSql = implode(' AND ', $where);

    $totalCount = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM equipment WHERE $whereSql", $types, $params);
    $equipmentRows = rpt_prepared_rows($conn, "SELECT id, name, pn, serial_no, type, owner_name, availability, maintenance_status, rent_per_day, equipment_value, image, updated_at FROM equipment WHERE $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset", $types, $params);

    $ids = array_column($equipmentRows, 'id');
    $totalUnitsMap = []; $rentedUnitsMap = [];
    if (!empty($ids)) {
        $idsIn = implode(',', array_map('intval', $ids));
        foreach (rpt_rows($conn, "SELECT equipment_id, COUNT(*) AS c FROM equipment_bookings WHERE equipment_id IN ($idsIn) AND status IN ('confirmed','on_the_way') AND from_date <= '$today' AND to_date >= '$today' GROUP BY equipment_id") as $r) {
            $rentedUnitsMap[(int)$r['equipment_id']] = (int)$r['c'];
        }
    }
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
}

// =======================================================================
// ALERTS DATA
// =======================================================================
if ($tab === 'alerts') {
    $lowStockProducts = rpt_rows($conn, "SELECT id, name, sku, farmer_name, category, stock, low_stock_threshold FROM products WHERE is_active = 1 AND stock > 0 AND stock <= low_stock_threshold ORDER BY stock ASC LIMIT 50");
    $outOfStockProducts = rpt_rows($conn, "SELECT id, name, sku, farmer_name, category FROM products WHERE is_active = 1 AND (stock <= 0 OR stock IS NULL) ORDER BY id DESC LIMIT 50");
    $unavailableEquipment = rpt_rows($conn, "SELECT id, name, type, owner_name FROM equipment WHERE availability = 0 AND (maintenance_status IS NULL OR maintenance_status <> 'maintenance') ORDER BY id DESC LIMIT 50");
    $maintenanceEquipment = rpt_rows($conn, "SELECT id, name, type, owner_name FROM equipment WHERE maintenance_status = 'maintenance' ORDER BY id DESC LIMIT 50");
}

// =======================================================================
// REPORTS DATA
// =======================================================================
if ($tab === 'reports') {
    $range = $_GET['range'] ?? 'monthly';
    [$rangeStart, $rangeEnd] = rpt_date_bounds($range, $_GET['from'] ?? '', $_GET['to'] ?? '');

    $rptStockValue = rpt_rows($conn, "SELECT category, COUNT(*) AS cnt, SUM(stock) AS total_stock, SUM(stock*price) AS value FROM products WHERE is_active=1 GROUP BY category ORDER BY value DESC");
    $rptLowStock   = rpt_rows($conn, "SELECT name, sku, farmer_name, stock, low_stock_threshold FROM products WHERE is_active=1 AND stock > 0 AND stock <= low_stock_threshold ORDER BY stock ASC LIMIT 25");
    $rptBestSelling = rpt_rows($conn, "SELECT oi.product_name AS name, SUM(oi.quantity) AS qty_sold, SUM(oi.quantity*oi.price) AS revenue FROM order_items oi JOIN orders o ON o.id=oi.order_id WHERE o.order_status NOT IN ('cancelled','returned') AND o.created_at BETWEEN ? AND ? GROUP BY oi.product_name ORDER BY qty_sold DESC LIMIT 15", 'ss', [$rangeStart . ' 00:00:00', $rangeEnd . ' 23:59:59']);

    $rptAvailability = rpt_rows($conn, "SELECT type, COUNT(*) AS total, SUM(CASE WHEN availability=1 AND (maintenance_status='available' OR maintenance_status IS NULL) THEN 1 ELSE 0 END) AS avail FROM equipment GROUP BY type ORDER BY total DESC");
    $rptRentals = rpt_rows($conn, "SELECT eb.status, COUNT(*) AS cnt, COALESCE(SUM(eb.total_amount),0) AS revenue FROM equipment_bookings eb WHERE eb.created_at BETWEEN ? AND ? GROUP BY eb.status", 'ss', [$rangeStart . ' 00:00:00', $rangeEnd . ' 23:59:59']);
    $rptRentals = rpt_prepared_rows($conn, "SELECT status, COUNT(*) AS cnt, COALESCE(SUM(total_amount),0) AS revenue FROM equipment_bookings WHERE created_at BETWEEN ? AND ? GROUP BY status", 'ss', [$rangeStart . ' 00:00:00', $rangeEnd . ' 23:59:59']);
    $rptMaintenance = rpt_rows($conn, "SELECT name, type, owner_name, updated_at FROM equipment WHERE maintenance_status = 'maintenance' ORDER BY updated_at DESC LIMIT 25");
    $rptMostRented = rpt_rows($conn, "SELECT e.name, COUNT(*) AS bookings FROM equipment_bookings eb JOIN equipment e ON e.id=eb.equipment_id GROUP BY e.id, e.name ORDER BY bookings DESC LIMIT 15");

    $rptSummary = [
        'total_items'   => (int)rpt_scalar($conn, "SELECT COUNT(*) FROM products WHERE is_active=1") + (int)rpt_scalar($conn, "SELECT COUNT(*) FROM equipment"),
        'total_value'   => (float)rpt_scalar($conn, "SELECT COALESCE(SUM(stock*price),0) FROM products WHERE is_active=1") + (float)rpt_scalar($conn, "SELECT COALESCE(SUM(equipment_value),0) FROM equipment"),
        'movements'     => (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM inventory_stock_history WHERE created_at BETWEEN ? AND ?", 'ss', [$rangeStart . ' 00:00:00', $rangeEnd . ' 23:59:59']),
    ];
}

// =======================================================================
// STOCK HISTORY DATA
// =======================================================================
if ($tab === 'history') {
    $fType   = trim($_GET['type'] ?? '');
    $fAction = trim($_GET['haction'] ?? '');
    $q       = trim($_GET['q'] ?? '');
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;
    $offset = ($page - 1) * $perPage;

    $where = ["1=1"]; $types = ''; $params = [];
    if ($fType !== '')   { $where[] = "item_type = ?"; $types .= 's'; $params[] = $fType; }
    if ($fAction !== '') { $where[] = "action = ?"; $types .= 's'; $params[] = $fAction; }
    if ($q !== '')       { $where[] = "item_name LIKE ?"; $types .= 's'; $params[] = "%$q%"; }
    $whereSql = implode(' AND ', $where);

    $totalCount = (int)rpt_prepared_scalar($conn, "SELECT COUNT(*) FROM inventory_stock_history WHERE $whereSql", $types, $params);
    $historyRows = rpt_prepared_rows($conn, "SELECT * FROM inventory_stock_history WHERE $whereSql ORDER BY id DESC LIMIT $perPage OFFSET $offset", $types, $params);
    $totalPages = max(1, (int)ceil($totalCount / $perPage));
}

$pageTitle     = 'Inventory Management';
$activeTeamTab = 'inventory';
include __DIR__ . '/includes/team_layout_top.php';
?>
<link rel="stylesheet" href="assets/css/report.css">
<style>
/* ---- Sidebar scroll fix ----
   team_layout_top.php's .sidebar had its own overflow-y:auto (on top of
   .sidebar-nav's own overflow-y:auto) with no scrollbar styling, so the
   browser's default fat gray scrollbar showed up over the dark sidebar.
   index.php avoids this by only letting .sidebar-nav scroll, with a thin
   themed scrollbar. Matching that here so this page looks the same. */
.sidebar{overflow-y:hidden}
.sidebar-nav{overflow-y:auto;overflow-x:hidden}
.sidebar-nav::-webkit-scrollbar{width:5px}
.sidebar-nav::-webkit-scrollbar-thumb{background:rgba(255,255,255,.18);border-radius:10px}
.sidebar-nav::-webkit-scrollbar-track{background:transparent}
.sidebar-nav{scrollbar-width:thin;scrollbar-color:rgba(255,255,255,.18) transparent}

/* ---- Graph widget sizing fix ----
   The chart-box divs had a fixed pixel height but Chart.js keeps its own
   default aspect ratio unless told otherwise, so canvases were fighting
   their container (stretching/squashing instead of filling it cleanly).
   Making the box a proper positioned container + maintainAspectRatio:false
   (set in the script below) fixes that. */
.chart-box{position:relative;width:100%;display:block}
.chart-box canvas{max-width:100%}
.inv-dash-card{display:flex;flex-direction:column}

.inv-stat-note{font-size:11px;color:var(--muted);margin-top:2px}
.inv-modal-grid{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.inv-thumb{width:42px;height:42px;border-radius:8px;object-fit:cover}
.inv-check{width:16px;height:16px}
.inv-bulkbar{display:none;align-items:center;gap:10px;background:var(--bg-soft);border:1px solid var(--border);border-radius:10px;padding:10px 14px;margin-bottom:12px;font-size:13px}
.inv-bulkbar.show{display:flex}

/* ---- Clickable KPI cards (Dashboard shortcuts) ---- */
.kpi-card{position:relative;overflow:hidden}
.kpi-card::before{content:'';position:absolute;left:0;top:0;bottom:0;width:4px;background:transparent;transition:.18s ease}
.kpi-primary::before{background:var(--primary)}
.kpi-accent::before{background:var(--accent)}
.kpi-success::before{background:var(--success)}
.kpi-warn::before{background:var(--warn)}
.kpi-danger::before{background:var(--danger)}
a.kpi-card{text-decoration:none;color:inherit}
.kpi-card.kpi-clickable{cursor:pointer}
.kpi-card.kpi-clickable:hover{transform:translateY(-4px) scale(1.01);box-shadow:0 14px 30px rgba(27,47,41,.14);border-color:transparent}
.kpi-goto{font-size:11px;font-weight:700;color:var(--primary);margin-top:8px;opacity:0;transform:translateX(-4px);transition:.18s ease;display:flex;align-items:center;gap:5px}
.kpi-card.kpi-clickable:hover .kpi-goto{opacity:1;transform:translateX(0)}
.kpi-chevron{position:absolute;right:14px;top:50%;transform:translateY(-50%) translateX(4px);color:var(--border);font-size:13px;opacity:0;transition:.18s ease}
.kpi-card.kpi-clickable:hover .kpi-chevron{opacity:1;transform:translateY(-50%) translateX(0);color:var(--primary)}

.inv-stat-row{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:16px}
.inv-stat-pill{position:relative;flex:1 1 190px;background:#fff;border:1px solid var(--border);border-left:4px solid transparent;border-radius:18px;padding:16px 18px;display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;transition:.18s ease}
.inv-stat-clickable{cursor:pointer}
.inv-stat-clickable:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(27,47,41,.12)}
.inv-stat-clickable::after{content:'\f105';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;right:14px;top:50%;transform:translateY(-50%) translateX(-4px);color:var(--border);font-size:13px;opacity:0;transition:.18s ease}
.inv-stat-clickable:hover::after{opacity:1;transform:translateY(-50%) translateX(0);color:inherit}
.inv-stat-icon{width:46px;height:46px;border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0;transition:transform .18s ease}
.inv-stat-clickable:hover .inv-stat-icon{transform:scale(1.08)}
.inv-tint-primary{border-left-color:var(--primary)}
.inv-tint-primary .inv-stat-icon{background:rgba(47,79,68,.12);color:var(--primary)}
.inv-tint-accent{border-left-color:var(--accent)}
.inv-tint-accent  .inv-stat-icon{background:rgba(169,139,74,.16);color:var(--accent)}
.inv-tint-success{border-left-color:var(--success)}
.inv-tint-success .inv-stat-icon{background:rgba(46,125,50,.14);color:var(--success)}
.inv-tint-warn{border-left-color:#D9822B}
.inv-tint-warn    .inv-stat-icon{background:rgba(217,130,43,.16);color:#C06A16}
.inv-tint-danger{border-left-color:var(--danger)}
.inv-tint-danger  .inv-stat-icon{background:rgba(155,59,55,.14);color:var(--danger)}
.inv-stat-value{font-size:19px;font-weight:800;color:var(--text);line-height:1.2}
.inv-stat-label{font-size:11.5px;color:var(--muted);font-weight:600;margin-top:2px}

.inv-dash-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:16px}
.inv-dash-grid.two{grid-template-columns:repeat(2,1fr)}
.inv-dash-card{background:#fff;border:1px solid var(--border);border-radius:20px;padding:20px;box-shadow:0 2px 10px rgba(27,47,41,.04);transition:.18s ease}
.inv-dash-card:hover{box-shadow:0 10px 26px rgba(27,47,41,.09);border-color:transparent}
.inv-dash-card-head{display:flex;align-items:baseline;justify-content:space-between;margin-bottom:14px;gap:8px}
.inv-dash-card-head h3{font-size:14.5px;font-weight:700;display:flex;align-items:center;gap:8px}
.inv-dash-card-head h3 i{color:var(--accent);font-size:13px}
.inv-dash-card-head .hint{font-size:11px;color:var(--muted);white-space:nowrap}

.inv-pie-wrap{display:flex;flex-direction:column;align-items:center;gap:14px}
.inv-pie-legend{display:flex;flex-direction:column;gap:8px;width:100%}
.inv-pie-legend div{display:flex;align-items:center;gap:8px;font-size:12.5px;color:var(--muted)}
.inv-pie-legend .dot{width:10px;height:10px;border-radius:50%;flex-shrink:0}
.inv-pie-legend strong{margin-left:auto;color:var(--text)}

.inv-rank{width:22px;height:22px;border-radius:50%;background:var(--bg-soft);color:var(--muted);font-size:10.5px;font-weight:800;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.inv-rank-1{background:linear-gradient(135deg,#F5D061,#C9971F);color:#fff}
.inv-rank-2{background:linear-gradient(135deg,#C9D2DA,#8E99A3);color:#fff}
.inv-rank-3{background:linear-gradient(135deg,#D8A579,#A9683C);color:#fff}
.inv-bar-row{text-decoration:none;color:inherit;border-radius:10px;padding:6px 8px;margin:0 -8px 6px;transition:.15s ease;cursor:pointer}
.inv-bar-row:hover{background:var(--bg-soft)}
.inv-bar-row:hover .inv-bar-label{color:var(--primary)}
.inv-bar-row{display:flex;align-items:center;gap:10px;margin-bottom:12px;font-size:12.5px}
.inv-info-modal .modal-box{max-width:420px}
.inv-info-head{display:flex;align-items:center;gap:14px;margin-bottom:14px}
.inv-info-head img{width:64px;height:64px;object-fit:cover;border-radius:10px;background:var(--bg-soft);border:1px solid var(--border)}
.inv-info-head .no-img{width:64px;height:64px;border-radius:10px;background:var(--bg-soft);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:20px}
.inv-info-head h4{margin:0 0 4px;font-size:15px}
.inv-info-head span{font-size:12px;color:var(--muted)}
.inv-info-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 16px;margin-bottom:14px}
.inv-info-grid div{font-size:12.5px}
.inv-info-grid label{display:block;color:var(--muted);font-size:11px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}
.inv-info-grid strong{font-size:13.5px}
.inv-actions{display:grid;grid-template-columns:repeat(2,30px);grid-auto-rows:30px;gap:6px}
.inv-actions .btn,.inv-actions a.btn{width:30px;height:30px;padding:0;display:flex;align-items:center;justify-content:center;font-size:12px;line-height:1}
.inv-check{width:15px;height:15px;accent-color:var(--primary);border-radius:4px;cursor:pointer}
.action-menu-wrap{position:relative !important;display:inline-block}
.kebab-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text);transition:.15s ease}
.kebab-btn:hover{background:var(--bg-soft);border-color:var(--primary)}
.action-menu{position:absolute;top:calc(100% + 6px);right:0;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.14);min-width:200px;padding:6px;z-index:60;display:none}
/* action-menu.js portals this element to <body> and positions it with
   inline left/top computed from getBoundingClientRect() — i.e. viewport-
   relative coordinates. Those coordinates only line up correctly under
   `position: fixed`. Forcing `position: absolute` here (the old fix)
   discarded the viewport coordinate system, so the menu landed wherever
   the document's normal flow put it instead — appearing up near the top
   of the page once you'd scrolled down. `!important` on position/display
   still guards against page CSS fighting the JS, but top/left/right/
   bottom are intentionally left alone so the JS's inline positioning
   (which already accounts for viewport edges) takes effect. */
.action-menu.open{display:block !important;position:fixed !important;width:auto !important;min-width:200px !important;max-width:240px !important}
.action-menu button,.action-menu a{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:none;background:none;text-align:left;font-size:13px;border-radius:8px;cursor:pointer;color:var(--text);text-decoration:none;white-space:nowrap}
.action-menu button:hover,.action-menu a:hover{background:var(--bg-soft)}
.action-menu i{width:16px;text-align:center;color:var(--muted)}
.action-menu hr{border:none;border-top:1px solid var(--border);margin:6px 2px}
.action-menu .menu-danger{color:#c0392b}
.action-menu .menu-danger i{color:#c0392b}
.action-menu .menu-success{color:#1a7f37}
.action-menu .menu-success i{color:#1a7f37}
.inv-row-clickable{cursor:pointer}
.inv-row-clickable:hover{background:var(--bg-soft)}
.badge-ok{color:#1a7f37;font-weight:600}
.badge-warn{color:#b45309;font-weight:600}
.badge-danger{color:#c0392b;font-weight:600}
.inv-bar-label{width:34%;flex-shrink:0;color:var(--text);font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;transition:.15s ease}
.inv-bar-track{flex:1;height:9px;background:var(--bg-soft);border-radius:6px;overflow:hidden}
.inv-bar-fill{height:100%;border-radius:6px;transition:width .5s ease}
.inv-bar-val{width:34px;text-align:right;font-weight:700;color:var(--text);flex-shrink:0}

@media(max-width:900px){
    .inv-dash-grid{grid-template-columns:1fr}
    .inv-dash-grid.two{grid-template-columns:1fr}
}
</style>

<div class="rpt-toolbar">
    <div class="rpt-search">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="invGlobalSearch" placeholder="Search products or equipment..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
    </div>
    <div class="rpt-toolbar-actions">
        <?php if ($tab === 'products' || $tab === 'equipment' || $tab === 'history'): ?>
        <a class="btn outline sm" href="inventory_export.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'csv', 'section' => $tab])); ?>"><i class="fa-solid fa-file-csv"></i> CSV</a>
        <a class="btn outline sm" href="inventory_export.php?<?php echo http_build_query(array_merge($_GET, ['export' => 'excel', 'section' => $tab])); ?>"><i class="fa-solid fa-file-excel"></i> Excel</a>
        <?php endif; ?>
        <button class="btn outline sm" onclick="window.print()"><i class="fa-solid fa-file-pdf"></i> Export PDF</button>
        <button class="btn sm" onclick="window.print()"><i class="fa-solid fa-print"></i> Print</button>
    </div>
</div>

<div class="rpt-tabs no-print">
    <?php
    $tabDefs = [
        'dashboard' => ['icon' => 'fa-gauge-high', 'label' => 'Dashboard'],
        'products'  => ['icon' => 'fa-box', 'label' => 'Product Inventory'],
        'equipment' => ['icon' => 'fa-tractor', 'label' => 'Equipment Inventory'],
        'alerts'    => ['icon' => 'fa-triangle-exclamation', 'label' => 'Low Stock Alerts'],
        'reports'   => ['icon' => 'fa-chart-pie', 'label' => 'Reports'],
        'history'   => ['icon' => 'fa-clock-rotate-left', 'label' => 'Stock History'],
    ];
    foreach ($tabDefs as $key => $def): ?>
        <a href="<?php echo inv_qs(['tab' => $key, 'page' => 1]); ?>" class="rpt-tab <?php echo $tab === $key ? 'active' : ''; ?>">
            <i class="fa-solid <?php echo $def['icon']; ?>"></i> <?php echo $def['label']; ?>
        </a>
    <?php endforeach; ?>
</div>

<?php // ===================================================================== DASHBOARD ===================================================================== ?>
<?php if ($tab === 'dashboard'):
    $maxSell = max(1, ...(array_map('intval', array_column($topSelling, 'qty')) ?: [1]));
    $maxRent = max(1, ...(array_map('intval', array_column($mostRented, 'bookings')) ?: [1]));
    $valueSplitTotal = max(0.01, $productStockValue + $equipmentValue);
?>
<div class="rpt-panel">
    <div class="inv-stat-row">
        <?php foreach ([
            ['icon'=>'fa-layer-group','label'=>'Total Inventory Items','value'=>$totalInventoryItems,'tint'=>'primary','href'=>'#valueSplitCard'],
            ['icon'=>'fa-sack-dollar','label'=>'Total Inventory Value','value'=>inv_money($totalInventoryValue),'tint'=>'success','href'=>'#valueSplitCard'],
            ['icon'=>'fa-box','label'=>'Total Products','value'=>$totalProducts,'tint'=>'accent','href'=>'inventory.php?tab=products'],
            ['icon'=>'fa-tractor','label'=>'Total Equipment','value'=>$totalEquipment,'tint'=>'primary','href'=>'inventory.php?tab=equipment'],
            ['icon'=>'fa-triangle-exclamation','label'=>'Out of Stock','value'=>$outOfStockCount,'tint'=>'danger','href'=>'inventory.php?tab=products&status=out_of_stock'],
        ] as $c): inv_stat_pill($c); endforeach; ?>
    </div>

    <div class="inv-stat-row">
        <?php foreach ([
            ['icon'=>'fa-circle-check','label'=>'In Stock','value'=>$inStockCount,'tint'=>'success','href'=>'inventory.php?tab=products&status=in_stock'],
            ['icon'=>'fa-triangle-exclamation','label'=>'Low Stock','value'=>$lowStockCount,'tint'=>'warn','href'=>'inventory.php?tab=products&status=low_stock'],
            ['icon'=>'fa-circle-check','label'=>'Equipment Available','value'=>$availableEquipCount,'tint'=>'success','href'=>'inventory.php?tab=equipment&status=available'],
            ['icon'=>'fa-truck-fast','label'=>'Currently Rented','value'=>$rentedEquipCount,'tint'=>'accent','href'=>'inventory.php?tab=equipment&status=rented'],
            ['icon'=>'fa-screwdriver-wrench','label'=>'Under Maintenance','value'=>$maintenanceEquipCount,'tint'=>'warn','href'=>'inventory.php?tab=equipment&status=maintenance'],
        ] as $c): inv_stat_pill($c); endforeach; ?>
    </div>

    <div class="inv-dash-grid">
        <div class="inv-dash-card" id="valueSplitCard">
            <div class="inv-dash-card-head">
                <h3><i class="fa-solid fa-chart-pie"></i> Inventory Value Split</h3>
                <span class="hint">Products vs Equipment</span>
            </div>
            <div class="inv-pie-wrap">
                <div class="chart-box" style="height:190px"><canvas id="chartValueSplit"></canvas></div>
                <div class="inv-pie-legend">
                    <div><span class="dot" style="background:var(--primary)"></span> Product Value <strong><?php echo round($productStockValue / $valueSplitTotal * 100); ?>%</strong></div>
                    <div><span class="dot" style="background:var(--accent)"></span> Equipment Value <strong><?php echo round($equipmentValue / $valueSplitTotal * 100); ?>%</strong></div>
                </div>
            </div>
        </div>

        <div class="inv-dash-card">
            <div class="inv-dash-card-head">
                <h3><i class="fa-solid fa-fire"></i> Top Selling Products</h3>
                <span class="hint">By units sold</span>
            </div>
            <?php if (empty($topSelling)): ?><div class="empty-state"><i class="fa-solid fa-chart-line"></i>No sales recorded yet.</div><?php endif; ?>
            <?php foreach ($topSelling as $i => $r): $pct = round(((int)$r['qty']) / $maxSell * 100); ?>
                <div class="inv-bar-row" role="button" tabindex="0"
                     data-info='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>'
                     onclick="openTopProductModal(this)" onkeydown="if(event.key==='Enter')openTopProductModal(this)">
                    <span class="inv-rank inv-rank-<?php echo $i < 3 ? $i+1 : 'n'; ?>"><?php echo $i+1; ?></span>
                    <span class="inv-bar-label" title="<?php echo htmlspecialchars($r['name']); ?>"><?php echo htmlspecialchars($r['name']); ?></span>
                    <div class="inv-bar-track"><div class="inv-bar-fill" style="width:<?php echo $pct; ?>%;background:var(--primary)"></div></div>
                    <span class="inv-bar-val"><?php echo (int)$r['qty']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="inv-dash-card">
            <div class="inv-dash-card-head">
                <h3><i class="fa-solid fa-star"></i> Most Rented Equipment</h3>
                <span class="hint">By bookings</span>
            </div>
            <?php if (empty($mostRented)): ?><div class="empty-state"><i class="fa-solid fa-tractor"></i>No rentals recorded yet.</div><?php endif; ?>
            <?php foreach ($mostRented as $i => $r): $pct = round(((int)$r['bookings']) / $maxRent * 100); ?>
                <div class="inv-bar-row" role="button" tabindex="0"
                     data-info='<?php echo htmlspecialchars(json_encode($r), ENT_QUOTES); ?>'
                     onclick="openRentedEquipModal(this)" onkeydown="if(event.key==='Enter')openRentedEquipModal(this)">
                    <span class="inv-rank inv-rank-<?php echo $i < 3 ? $i+1 : 'n'; ?>"><?php echo $i+1; ?></span>
                    <span class="inv-bar-label" title="<?php echo htmlspecialchars($r['name']); ?>"><?php echo htmlspecialchars($r['name']); ?></span>
                    <div class="inv-bar-track"><div class="inv-bar-fill" style="width:<?php echo $pct; ?>%;background:var(--accent)"></div></div>
                    <span class="inv-bar-val"><?php echo (int)$r['bookings']; ?></span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="inv-dash-grid two">
        <div class="inv-dash-card">
            <div class="inv-dash-card-head"><h3>Product Stock Status</h3></div>
            <div class="chart-box" style="height:200px"><canvas id="chartStockStatus"></canvas></div>
        </div>
        <div class="inv-dash-card">
            <div class="inv-dash-card-head"><h3>Equipment Availability</h3></div>
            <div class="chart-box" style="height:200px"><canvas id="chartEquipAvail"></canvas></div>
        </div>
    </div>

    <div class="inv-dash-card">
        <div class="inv-dash-card-head">
            <h3>Monthly Inventory Movement</h3>
            <span class="hint">Last 14 days</span>
        </div>
        <div class="chart-box" style="height:260px"><canvas id="chartMovement"></canvas></div>
    </div>

    <div class="inv-dash-card" style="margin-top:16px">
        <div class="inv-dash-card-head"><h3>Recent Inventory Activities</h3></div>
        <table>
            <thead><tr><th>Date &amp; Time</th><th>Item</th><th>Type</th><th>Action</th><th>Change</th><th>By</th></tr></thead>
            <tbody>
            <?php if (empty($recentActivity)): ?><tr><td colspan="6"><div class="empty-state"><i class="fa-solid fa-inbox"></i>No inventory activity yet.</div></td></tr><?php endif; ?>
            <?php foreach ($recentActivity as $r): ?>
                <tr>
                    <td><?php echo inv_fmt_date($r['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($r['item_name']); ?></td>
                    <td><span class="tag <?php echo $r['item_type']==='product'?'active':'pending'; ?>"><?php echo ucfirst($r['item_type']); ?></span></td>
                    <td><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$r['action']))); ?></td>
                    <td><?php echo $r['previous_qty'] !== null ? ((int)$r['previous_qty'] . ' → ' . (int)$r['updated_qty']) : '—'; ?></td>
                    <td><?php echo htmlspecialchars($r['updated_by'] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<script src="assets/vendor/chart.umd.js"></script>
<script>
const invPrimary = '#2F4F44', invPrimaryDark = '#1B2F29', invAccent = '#A98B4A', invSuccess = '#2E7D32', invWarn = '#C06A16', invDanger = '#9B3B37';

// Shared defaults so every widget matches the theme and — critically —
// actually fills its .chart-box instead of fighting its own aspect ratio.
Chart.defaults.font.family = "inherit";
Chart.defaults.font.size = 11.5;
Chart.defaults.color = '#7C8577';
const invChartBase = {
    responsive: true,
    maintainAspectRatio: false,
    resizeDelay: 50,
    animation: { duration: 650, easing: 'easeOutQuart' },
};

// Draws a bold total count + small label in the middle of a doughnut —
// makes the widget read at a glance instead of being an empty ring.
const invCenterText = {
    id: 'invCenterText',
    afterDraw(chart) {
        const opts = chart.config.options.invCenterText;
        if (!opts) return;
        const { ctx, chartArea: { left, right, top, bottom } } = chart;
        const cx = (left + right) / 2, cy = (top + bottom) / 2;
        ctx.save();
        ctx.textAlign = 'center';
        ctx.textBaseline = 'middle';
        ctx.fillStyle = '#26292B';
        ctx.font = '800 20px inherit';
        ctx.fillText(opts.value, cx, cy - (opts.label ? 9 : 0));
        if (opts.label) {
            ctx.fillStyle = '#68706B';
            ctx.font = '600 10.5px inherit';
            ctx.fillText(opts.label, cx, cy + 11);
        }
        ctx.restore();
    }
};
Chart.register(invCenterText);

new Chart(document.getElementById('chartValueSplit'), {
    type: 'doughnut',
    plugins: [invCenterText],
    data: { labels: ['Product Value','Equipment Value'], datasets: [{ data: [<?php echo $productStockValue; ?>, <?php echo $equipmentValue; ?>], backgroundColor: [invPrimary, invAccent], borderWidth: 3, borderColor: '#fff', borderRadius: 6, spacing: 3, hoverOffset: 6 }] },
    options: { ...invChartBase, cutout: '72%', invCenterText: { value: '₹' + Math.round(<?php echo $valueSplitTotal; ?>).toLocaleString('en-IN'), label: 'Total Value' }, plugins: { legend: { display: false }, tooltip: { padding: 10, cornerRadius: 8 } } }
});

new Chart(document.getElementById('chartStockStatus'), {
    type: 'doughnut',
    plugins: [invCenterText],
    data: { labels: ['In Stock','Low Stock','Out of Stock'], datasets: [{ data: [<?php echo $inStockCount; ?>, <?php echo $lowStockCount; ?>, <?php echo $outOfStockCount; ?>], backgroundColor: [invSuccess, invWarn, invDanger], borderWidth: 3, borderColor: '#fff', borderRadius: 6, spacing: 3, hoverOffset: 6 }] },
    options: { ...invChartBase, cutout: '68%', invCenterText: { value: String(<?php echo $totalProducts; ?>), label: 'Products' }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 14, usePointStyle: true, pointStyle: 'circle' } }, tooltip: { padding: 10, cornerRadius: 8 } } }
});

new Chart(document.getElementById('chartEquipAvail'), {
    type: 'doughnut',
    plugins: [invCenterText],
    data: { labels: ['Available','Rented','Maintenance','Out of Service'], datasets: [{ data: [<?php echo $availableEquipCount; ?>, <?php echo $rentedEquipCount; ?>, <?php echo $maintenanceEquipCount; ?>, <?php echo $outOfServiceEquipCount; ?>], backgroundColor: [invSuccess, invAccent, invWarn, invDanger], borderWidth: 3, borderColor: '#fff', borderRadius: 6, spacing: 3, hoverOffset: 6 }] },
    options: { ...invChartBase, cutout: '68%', invCenterText: { value: String(<?php echo $totalEquipment; ?>), label: 'Equipment' }, plugins: { legend: { position: 'bottom', labels: { boxWidth: 9, boxHeight: 9, padding: 14, usePointStyle: true, pointStyle: 'circle' } }, tooltip: { padding: 10, cornerRadius: 8 } } }
});

new Chart(document.getElementById('chartMovement'), {
    type: 'line',
    data: {
        labels: <?php echo json_encode($movementLabels); ?>,
        datasets: [{
            label: 'Movements',
            data: <?php echo json_encode($movementValues); ?>,
            borderColor: invPrimary,
            backgroundColor: (ctx) => {
                const { chartArea, ctx: c } = ctx.chart;
                if (!chartArea) return 'rgba(47,79,68,.10)';
                const g = c.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                g.addColorStop(0, 'rgba(47,79,68,.28)');
                g.addColorStop(1, 'rgba(47,79,68,.02)');
                return g;
            },
            fill: true, tension: .35, pointRadius: 0, pointHoverRadius: 5, pointBackgroundColor: invPrimary, pointHoverBorderColor: '#fff', pointHoverBorderWidth: 2, borderWidth: 2.5
        }]
    },
    options: {
        ...invChartBase,
        plugins: { legend: { display: false }, tooltip: { padding: 10, cornerRadius: 8 } },
        scales: {
            x: { grid: { display: false }, ticks: { maxRotation: 0, autoSkip: true, maxTicksLimit: 8 } },
            y: { beginAtZero: true, grid: { color: '#EEF0EB' }, ticks: { precision: 0 } }
        }
    }
});
</script>
<?php endif; ?>

<?php // ===================================================================== PRODUCT INVENTORY ===================================================================== ?>
<?php if ($tab === 'products'): ?>
<div class="rpt-panel">
    <form class="filters" method="get">
        <input type="hidden" name="tab" value="products">
        <input type="text" name="q" placeholder="Search name or SKU..." value="<?php echo htmlspecialchars($q); ?>">
        <select name="category" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($categoryOptions as $c): ?><option value="<?php echo htmlspecialchars($c); ?>" <?php echo $fCategory===$c?'selected':''; ?>><?php echo htmlspecialchars($c); ?></option><?php endforeach; ?>
        </select>
        <select name="seller" onchange="this.form.submit()">
            <option value="">All Sellers</option>
            <?php foreach ($sellerOptions as $s): ?><option value="<?php echo htmlspecialchars($s); ?>" <?php echo $fSeller===$s?'selected':''; ?>><?php echo htmlspecialchars($s); ?></option><?php endforeach; ?>
        </select>
        <select name="status" onchange="this.form.submit()">
            <option value="">All Status</option>
            <option value="in_stock" <?php echo $fStatus==='in_stock'?'selected':''; ?>>In Stock</option>
            <option value="low_stock" <?php echo $fStatus==='low_stock'?'selected':''; ?>>Low Stock</option>
            <option value="out_of_stock" <?php echo $fStatus==='out_of_stock'?'selected':''; ?>>Out of Stock</option>
            <option value="deleted" <?php echo $fStatus==='deleted'?'selected':''; ?>>🗑 Deleted</option>
        </select>
        <select name="sort" onchange="this.form.submit()">
            <option value="id_desc" <?php echo $sort==='id_desc'?'selected':''; ?>>Newest</option>
            <option value="name_asc" <?php echo $sort==='name_asc'?'selected':''; ?>>Name A-Z</option>
            <option value="stock_asc" <?php echo $sort==='stock_asc'?'selected':''; ?>>Stock: Low to High</option>
            <option value="stock_desc" <?php echo $sort==='stock_desc'?'selected':''; ?>>Stock: High to Low</option>
            <option value="value_desc" <?php echo $sort==='value_desc'?'selected':''; ?>>Stock Value: High to Low</option>
        </select>
        <button class="btn sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <div class="card">
        <div class="card-head"><h2>Product Inventory (<?php echo number_format($totalCount); ?>)</h2></div>
        <table>
            <thead><tr>
                <th>Image</th><th>Product</th><th>SKU</th><th>Category</th><th>Seller</th>
                <th>Qty</th><th>Reserved</th><th>Sold</th><th>Unit Price</th><th>Stock Value</th><th>Status</th><th>Last Updated</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($products)): ?><tr><td colspan="13"><div class="empty-state"><i class="fa-solid fa-box-open"></i>No products match these filters.</div></td></tr><?php endif; ?>
            <?php foreach ($products as $p):
                $status = inv_product_status((int)$p['stock'], (int)$p['low_stock_threshold']);
                $stockValue = (float)$p['stock'] * (float)$p['price'];
                $reserved = $reservedMap[(int)$p['id']] ?? 0;
                $sold = $soldMap[(int)$p['id']] ?? 0;
            ?>
                <tr class="inv-row-clickable"
                    data-info='<?php echo htmlspecialchars(json_encode([
                        'name' => $p['name'], 'sku' => $p['sku'], 'category' => $p['category'],
                        'farmer_name' => $p['farmer_name'], 'image' => $p['image'], 'stock' => (int)$p['stock'],
                        'low_stock_threshold' => (int)$p['low_stock_threshold'], 'price' => (float)$p['price'],
                        'reserved' => (int)$reserved, 'sold' => (int)$sold, 'updated_at' => inv_fmt_date($p['updated_at']),
                    ]), ENT_QUOTES); ?>'
                    onclick="if(!event.target.closest('.action-menu-wrap')) openTopProductModal(this)">
                    <td><img class="inv-thumb" src="<?php echo $p['image'] ? '../'.htmlspecialchars($p['image']) : 'assets/images/placeholder.png'; ?>" onerror="this.style.visibility='hidden'"></td>
                    <td><?php echo htmlspecialchars($p['name']); ?></td>
                    <td><?php echo htmlspecialchars($p['sku'] ?: '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['category'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($p['farmer_name'] ?? '—'); ?></td>
                    <td><strong><?php echo (int)$p['stock']; ?></strong></td>
                    <td><?php echo (int)$reserved; ?></td>
                    <td><?php echo (int)$sold; ?></td>
                    <td><?php echo inv_money($p['price']); ?></td>
                    <td><?php echo inv_money($stockValue); ?></td>
                    <td><span class="tag <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span></td>
                    <td style="font-size:11.5px;color:var(--muted)"><?php echo inv_fmt_date($p['updated_at']); ?></td>
                    <td>
                        <div class="action-menu-wrap">
                            <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <div class="action-menu">
                                <button onclick="openStockModal(<?php echo (int)$p['id']; ?>,'<?php echo htmlspecialchars(addslashes($p['name'])); ?>','add_stock')"><i class="fa-solid fa-plus"></i> Add Stock</button>
                                <button onclick="openStockModal(<?php echo (int)$p['id']; ?>,'<?php echo htmlspecialchars(addslashes($p['name'])); ?>','reduce_stock')"><i class="fa-solid fa-minus"></i> Reduce Stock</button>
                                <button onclick="openSettingsModal(<?php echo (int)$p['id']; ?>,'<?php echo htmlspecialchars(addslashes($p['name'])); ?>','<?php echo htmlspecialchars(addslashes($p['sku'] ?? '')); ?>',<?php echo (int)$p['low_stock_threshold']; ?>)"><i class="fa-solid fa-gear"></i> Edit SKU / Threshold</button>
                                <a href="index.php?tab=products" target="_blank"><i class="fa-solid fa-eye"></i> View / Full Edit</a>
                                <hr>
                                <?php if ($showDeleted): ?>
                                <button class="menu-success" onclick="submitProductRestore(<?php echo (int)$p['id']; ?>,'<?php echo htmlspecialchars(addslashes($p['name'])); ?>')"><i class="fa-solid fa-rotate-left"></i> Restore Product</button>
                                <?php else: ?>
                                <button class="menu-danger" onclick="submitProductDelete(<?php echo (int)$p['id']; ?>,'<?php echo htmlspecialchars(addslashes($p['name'])); ?>')"><i class="fa-solid fa-trash"></i> Delete Product</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
                <a href="<?php echo inv_qs(['page'=>$i]); ?>" class="<?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php // ===================================================================== EQUIPMENT INVENTORY ===================================================================== ?>
<?php if ($tab === 'equipment'): ?>
<div class="rpt-panel">
    <form class="filters" method="get">
        <input type="hidden" name="tab" value="equipment">
        <input type="text" name="q" placeholder="Search name / part no / serial..." value="<?php echo htmlspecialchars($q); ?>">
        <select name="type" onchange="this.form.submit()">
            <option value="">All Categories</option>
            <?php foreach ($equipTypeOptions as $t): ?><option value="<?php echo htmlspecialchars($t); ?>" <?php echo $fType===$t?'selected':''; ?>><?php echo htmlspecialchars(ucfirst($t)); ?></option><?php endforeach; ?>
        </select>
        <select name="owner" onchange="this.form.submit()">
            <option value="">All Sellers/Owners</option>
            <?php foreach ($equipOwnerOptions as $o): ?><option value="<?php echo htmlspecialchars($o); ?>" <?php echo $fOwner===$o?'selected':''; ?>><?php echo htmlspecialchars($o); ?></option><?php endforeach; ?>
        </select>
        <select name="status" onchange="this.form.submit()">
            <option value="">All Availability</option>
            <option value="available" <?php echo $fStatus==='available'?'selected':''; ?>>Available</option>
            <option value="rented" <?php echo $fStatus==='rented'?'selected':''; ?>>Currently Rented</option>
            <option value="maintenance" <?php echo $fStatus==='maintenance'?'selected':''; ?>>Maintenance</option>
            <option value="out_of_service" <?php echo $fStatus==='out_of_service'?'selected':''; ?>>Out of Service</option>
        </select>
        <button class="btn sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <div class="card">
        <div class="card-head"><h2>Equipment Inventory (<?php echo number_format($totalCount); ?>)</h2></div>
        <table>
            <thead><tr>
                <th>Image</th><th>Equipment</th><th>Equipment ID</th><th>Category</th><th>Owner/Seller</th>
                <th>Total Units</th><th>Available</th><th>Rented</th><th>Maintenance</th><th>Rental Price</th><th>Value</th><th>Status</th><th>Last Updated</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php if (empty($equipmentRows)): ?><tr><td colspan="14"><div class="empty-state"><i class="fa-solid fa-tractor"></i>No equipment matches these filters.</div></td></tr><?php endif; ?>
            <?php foreach ($equipmentRows as $e):
                $status = inv_equipment_status($e, $activeBookingIds);
                $rented = $rentedUnitsMap[(int)$e['id']] ?? 0;
                $isMaint = ($e['maintenance_status'] ?? '') === 'maintenance';
            ?>
                <tr>
                    <td><img class="inv-thumb" src="<?php echo $e['image'] ? '../'.htmlspecialchars($e['image']) : 'assets/images/placeholder.png'; ?>" onerror="this.style.visibility='hidden'"></td>
                    <td><?php echo htmlspecialchars($e['name']); ?></td>
                    <td>#<?php echo (int)$e['id']; ?><?php echo $e['serial_no'] ? ' / '.htmlspecialchars($e['serial_no']) : ''; ?></td>
                    <td><?php echo htmlspecialchars(ucfirst($e['type'] ?? '—')); ?></td>
                    <td><?php echo htmlspecialchars($e['owner_name'] ?? '—'); ?></td>
                    <td>1</td>
                    <td><?php echo ($status['label']==='Available') ? 1 : 0; ?></td>
                    <td><?php echo (int)$rented; ?></td>
                    <td><?php echo $isMaint ? 1 : 0; ?></td>
                    <td><?php echo inv_money($e['rent_per_day']); ?>/day</td>
                    <td><?php echo $e['equipment_value'] !== null ? inv_money($e['equipment_value']) : '—'; ?></td>
                    <td><span class="tag <?php echo $status['class']; ?>"><?php echo $status['label']; ?></span></td>
                    <td style="font-size:11.5px;color:var(--muted)"><?php echo inv_fmt_date($e['updated_at']); ?></td>
                    <td>
                        <div class="action-menu-wrap">
                            <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <div class="action-menu">
                                <button onclick="openMaintModal(<?php echo (int)$e['id']; ?>,'<?php echo htmlspecialchars(addslashes($e['name'])); ?>','<?php echo $e['maintenance_status'] ?? 'available'; ?>')"><i class="fa-solid fa-screwdriver-wrench"></i> Maintenance</button>
                                <button onclick="openValueModal(<?php echo (int)$e['id']; ?>,'<?php echo htmlspecialchars(addslashes($e['name'])); ?>',<?php echo $e['equipment_value'] !== null ? (float)$e['equipment_value'] : 'null'; ?>)"><i class="fa-solid fa-tag"></i> Set Asset Value</button>
                                <a href="rental.php" target="_blank"><i class="fa-solid fa-calendar-check"></i> Booking Status</a>
                                <a href="index.php?tab=equipment" target="_blank"><i class="fa-solid fa-eye"></i> View / Full Edit</a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
                <a href="<?php echo inv_qs(['page'=>$i]); ?>" class="<?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<?php // ===================================================================== ALERTS ===================================================================== ?>
<?php if ($tab === 'alerts'): ?>
<div class="rpt-panel">
    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Low Stock Products <span class="tag pending"><?php echo count($lowStockProducts); ?></span></h2></div>
            <table>
                <thead><tr><th>Product</th><th>SKU</th><th>Seller</th><th>Stock</th><th>Threshold</th></tr></thead>
                <tbody>
                <?php if (empty($lowStockProducts)): ?><tr><td colspan="5"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing running low.</div></td></tr><?php endif; ?>
                <?php foreach ($lowStockProducts as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['sku'] ?: '—'); ?></td><td><?php echo htmlspecialchars($r['farmer_name'] ?? '—'); ?></td>
                        <td><span class="tag pending"><?php echo (int)$r['stock']; ?></span></td><td><?php echo (int)$r['low_stock_threshold']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Out of Stock Products <span class="tag suspended"><?php echo count($outOfStockProducts); ?></span></h2></div>
            <table>
                <thead><tr><th>Product</th><th>SKU</th><th>Seller</th><th>Category</th></tr></thead>
                <tbody>
                <?php if (empty($outOfStockProducts)): ?><tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing out of stock.</div></td></tr><?php endif; ?>
                <?php foreach ($outOfStockProducts as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['sku'] ?: '—'); ?></td><td><?php echo htmlspecialchars($r['farmer_name'] ?? '—'); ?></td><td><?php echo htmlspecialchars($r['category'] ?? '—'); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Low Available Equipment <span class="tag suspended"><?php echo count($unavailableEquipment); ?></span></h2></div>
            <table>
                <thead><tr><th>Equipment</th><th>Category</th><th>Owner</th></tr></thead>
                <tbody>
                <?php if (empty($unavailableEquipment)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-check"></i>All equipment available.</div></td></tr><?php endif; ?>
                <?php foreach ($unavailableEquipment as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars(ucfirst($r['type'] ?? '—')); ?></td><td><?php echo htmlspecialchars($r['owner_name'] ?? '—'); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Equipment Under Maintenance <span class="tag expired"><?php echo count($maintenanceEquipment); ?></span></h2></div>
            <table>
                <thead><tr><th>Equipment</th><th>Category</th><th>Owner</th></tr></thead>
                <tbody>
                <?php if (empty($maintenanceEquipment)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing under maintenance.</div></td></tr><?php endif; ?>
                <?php foreach ($maintenanceEquipment as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars(ucfirst($r['type'] ?? '—')); ?></td><td><?php echo htmlspecialchars($r['owner_name'] ?? '—'); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<?php // ===================================================================== REPORTS ===================================================================== ?>
<?php if ($tab === 'reports'): ?>
<div class="rpt-panel">
    <form class="filters" method="get">
        <input type="hidden" name="tab" value="reports">
        <select name="range" onchange="this.form.submit()">
            <option value="daily" <?php echo $range==='daily'?'selected':''; ?>>Daily</option>
            <option value="weekly" <?php echo $range==='weekly'?'selected':''; ?>>Weekly</option>
            <option value="monthly" <?php echo $range==='monthly'?'selected':''; ?>>Monthly</option>
            <option value="yearly" <?php echo $range==='yearly'?'selected':''; ?>>Yearly</option>
        </select>
        <span class="hint" style="align-self:center"><?php echo htmlspecialchars($rangeStart); ?> to <?php echo htmlspecialchars($rangeEnd); ?></span>
    </form>

    <div class="kpi-grid">
        <div class="kpi-card kpi-primary"><div class="kpi-icon"><i class="fa-solid fa-layer-group"></i></div><div class="kpi-body"><div class="kpi-value"><?php echo number_format($rptSummary['total_items']); ?></div><div class="kpi-label">Inventory Summary — Total Items</div></div></div>
        <div class="kpi-card kpi-success"><div class="kpi-icon"><i class="fa-solid fa-sack-dollar"></i></div><div class="kpi-body"><div class="kpi-value"><?php echo inv_money($rptSummary['total_value']); ?></div><div class="kpi-label">Total Inventory Value</div></div></div>
        <div class="kpi-card kpi-accent"><div class="kpi-icon"><i class="fa-solid fa-arrows-rotate"></i></div><div class="kpi-body"><div class="kpi-value"><?php echo number_format($rptSummary['movements']); ?></div><div class="kpi-label">Stock Movements (period)</div></div></div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Stock Report — by Category</h2></div>
        <table>
            <thead><tr><th>Category</th><th>Products</th><th>Total Stock</th><th>Inventory Value</th></tr></thead>
            <tbody>
            <?php foreach ($rptStockValue as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['category'] ?? '—'); ?></td><td><?php echo (int)$r['cnt']; ?></td><td><?php echo (int)$r['total_stock']; ?></td><td><?php echo inv_money($r['value']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Low Stock Report</h2></div>
            <table>
                <thead><tr><th>Product</th><th>Seller</th><th>Stock</th></tr></thead>
                <tbody>
                <?php if (empty($rptLowStock)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing running low.</div></td></tr><?php endif; ?>
                <?php foreach ($rptLowStock as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars($r['farmer_name'] ?? '—'); ?></td><td><span class="tag pending"><?php echo (int)$r['stock']; ?></span></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Best Selling Products (period)</h2></div>
            <table>
                <thead><tr><th>Product</th><th>Qty Sold</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php if (empty($rptBestSelling)): ?><tr><td colspan="3"><div class="empty-state"><i class="fa-solid fa-chart-line"></i>No sales in this period.</div></td></tr><?php endif; ?>
                <?php foreach ($rptBestSelling as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo (int)$r['qty_sold']; ?></td><td><?php echo inv_money($r['revenue']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Availability Report — by Equipment Category</h2></div>
        <table>
            <thead><tr><th>Category</th><th>Total</th><th>Available</th></tr></thead>
            <tbody>
            <?php foreach ($rptAvailability as $r): ?>
                <tr><td><?php echo htmlspecialchars(ucfirst($r['type'] ?? '—')); ?></td><td><?php echo (int)$r['total']; ?></td><td><?php echo (int)$r['avail']; ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="rpt-chart-grid two-col">
        <div class="card">
            <div class="card-head"><h2>Rental Report (period)</h2></div>
            <table>
                <thead><tr><th>Status</th><th>Bookings</th><th>Revenue</th></tr></thead>
                <tbody>
                <?php foreach ($rptRentals as $r): ?>
                    <tr><td><?php echo htmlspecialchars(ucfirst($r['status'])); ?></td><td><?php echo (int)$r['cnt']; ?></td><td><?php echo inv_money($r['revenue']); ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="card">
            <div class="card-head"><h2>Most Rented Equipment</h2></div>
            <table>
                <thead><tr><th>Equipment</th><th>Bookings</th></tr></thead>
                <tbody>
                <?php foreach ($rptMostRented as $r): ?>
                    <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo (int)$r['bookings']; ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="card">
        <div class="card-head"><h2>Maintenance Report</h2></div>
        <table>
            <thead><tr><th>Equipment</th><th>Category</th><th>Owner</th><th>Since</th></tr></thead>
            <tbody>
            <?php if (empty($rptMaintenance)): ?><tr><td colspan="4"><div class="empty-state"><i class="fa-solid fa-check"></i>Nothing under maintenance.</div></td></tr><?php endif; ?>
            <?php foreach ($rptMaintenance as $r): ?>
                <tr><td><?php echo htmlspecialchars($r['name']); ?></td><td><?php echo htmlspecialchars(ucfirst($r['type'] ?? '—')); ?></td><td><?php echo htmlspecialchars($r['owner_name'] ?? '—'); ?></td><td><?php echo inv_fmt_date($r['updated_at']); ?></td></tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php // ===================================================================== STOCK HISTORY ===================================================================== ?>
<?php if ($tab === 'history'): ?>
<div class="rpt-panel">
    <form class="filters" method="get">
        <input type="hidden" name="tab" value="history">
        <input type="text" name="q" placeholder="Search item name..." value="<?php echo htmlspecialchars($q); ?>">
        <select name="type" onchange="this.form.submit()">
            <option value="">All Types</option>
            <option value="product" <?php echo $fType==='product'?'selected':''; ?>>Product</option>
            <option value="equipment" <?php echo $fType==='equipment'?'selected':''; ?>>Equipment</option>
        </select>
        <select name="haction" onchange="this.form.submit()">
            <option value="">All Actions</option>
            <?php foreach (['stock_added','stock_reduced','product_sold','product_returned','equipment_booked','equipment_returned','maintenance_started','maintenance_completed','status_changed'] as $a): ?>
                <option value="<?php echo $a; ?>" <?php echo $fAction===$a?'selected':''; ?>><?php echo ucwords(str_replace('_',' ',$a)); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <div class="card">
        <div class="card-head"><h2>Stock History (<?php echo number_format($totalCount); ?>)</h2></div>
        <table>
            <thead><tr><th>Date &amp; Time</th><th>Item Name</th><th>Type</th><th>Action</th><th>Previous</th><th>Updated</th><th>Updated By</th><th>Remarks</th></tr></thead>
            <tbody>
            <?php if (empty($historyRows)): ?><tr><td colspan="8"><div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No history recorded yet.</div></td></tr><?php endif; ?>
            <?php foreach ($historyRows as $r): ?>
                <tr>
                    <td><?php echo inv_fmt_date($r['created_at']); ?></td>
                    <td><?php echo htmlspecialchars($r['item_name']); ?></td>
                    <td><span class="tag <?php echo $r['item_type']==='product'?'active':'pending'; ?>"><?php echo ucfirst($r['item_type']); ?></span></td>
                    <td><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$r['action']))); ?></td>
                    <td><?php echo $r['previous_qty'] !== null ? (int)$r['previous_qty'] : '—'; ?></td>
                    <td><?php echo $r['updated_qty'] !== null ? (int)$r['updated_qty'] : '—'; ?></td>
                    <td><?php echo htmlspecialchars($r['updated_by'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($r['remarks'] ?? '—'); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <div class="pagination">
            <?php for ($i=1;$i<=$totalPages;$i++): ?>
                <a href="<?php echo inv_qs(['page'=>$i]); ?>" class="<?php echo $i===$page?'active':''; ?>"><?php echo $i; ?></a>
            <?php endfor; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ===================================================================== MODALS ===================================================================== -->
<div class="modal-overlay" id="stockModal">
    <div class="modal-box">
        <h3 id="stockModalTitle">Update Stock</h3>
        <p id="stockModalSub"></p>
        <div class="form-group"><label>Quantity</label><input type="number" id="stockQty" min="0" value="0"></div>
        <div class="form-group"><label>Remarks (optional)</label><input type="text" id="stockRemarks" placeholder="e.g. New shipment received"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('stockModal')">Cancel</button>
            <button class="btn" onclick="submitStock()">Save</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="settingsModal">
    <div class="modal-box">
        <h3>Product Settings</h3>
        <p id="settingsModalSub"></p>
        <div class="form-group"><label>SKU</label><input type="text" id="settingsSku"></div>
        <div class="form-group"><label>Low Stock Threshold</label><input type="number" id="settingsThreshold" min="0"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('settingsModal')">Cancel</button>
            <button class="btn" onclick="submitSettings()">Save</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="maintModal">
    <div class="modal-box">
        <h3>Equipment Status</h3>
        <p id="maintModalSub"></p>
        <div class="form-group">
            <label>Maintenance Status</label>
            <select id="maintStatus">
                <option value="available">Available</option>
                <option value="maintenance">Under Maintenance</option>
                <option value="out_of_service">Out of Service</option>
            </select>
        </div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('maintModal')">Cancel</button>
            <button class="btn" onclick="submitMaint()">Save</button>
        </div>
    </div>
</div>

<div class="modal-overlay" id="valueModal">
    <div class="modal-box">
        <h3>Equipment Asset Value</h3>
        <p id="valueModalSub"></p>
        <div class="form-group"><label>Value (₹)</label><input type="number" id="equipValue" min="0" step="0.01"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('valueModal')">Cancel</button>
            <button class="btn" onclick="submitValue()">Save</button>
        </div>
    </div>
</div>

<div class="modal-overlay inv-info-modal" id="topProductModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-fire"></i> Product Details</h3>
        <div class="inv-info-head">
            <img id="tpImg" src="" alt="" style="display:none">
            <div class="no-img" id="tpNoImg"><i class="fa-solid fa-box"></i></div>
            <div>
                <h4 id="tpName"></h4>
                <span id="tpSku"></span>
            </div>
        </div>
        <div class="inv-info-grid">
            <div><label>Category</label><strong id="tpCategory"></strong></div>
            <div><label>Seller</label><strong id="tpSeller"></strong></div>
            <div><label>Units Sold</label><strong id="tpQty"></strong></div>
            <div><label>Current Stock</label><strong id="tpStock"></strong></div>
            <div><label>Reserved</label><strong id="tpReserved"></strong></div>
            <div><label>Price</label><strong id="tpPrice"></strong></div>
            <div><label>Stock Status</label><strong id="tpStatus"></strong></div>
            <div><label>Last Updated</label><strong id="tpUpdated"></strong></div>
        </div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('topProductModal')">Close</button>
            <a class="btn" id="tpViewLink" href="#">View in Product Inventory</a>
        </div>
    </div>
</div>

<div class="modal-overlay inv-info-modal" id="rentedEquipModal">
    <div class="modal-box">
        <h3><i class="fa-solid fa-star"></i> Equipment Details</h3>
        <div class="inv-info-head">
            <img id="reImg" src="" alt="" style="display:none">
            <div class="no-img" id="reNoImg"><i class="fa-solid fa-tractor"></i></div>
            <div>
                <h4 id="reName"></h4>
                <span id="rePn"></span>
            </div>
        </div>
        <div class="inv-info-grid">
            <div><label>Type</label><strong id="reType"></strong></div>
            <div><label>Owner</label><strong id="reOwner"></strong></div>
            <div><label>Total Bookings</label><strong id="reBookings"></strong></div>
            <div><label>Rent / Day</label><strong id="reRent"></strong></div>
            <div><label>Asset Value</label><strong id="reValue"></strong></div>
            <div><label>Status</label><strong id="reStatus"></strong></div>
        </div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('rentedEquipModal')">Close</button>
            <a class="btn" id="reViewLink" href="#">View in Equipment Inventory</a>
        </div>
    </div>
</div>

<div class="toast-wrap" id="toastWrap"></div>

<script>
function toast(msg, isError) {
    const wrap = document.getElementById('toastWrap');
    const t = document.createElement('div');
    t.className = 'toast' + (isError ? ' error' : '');
    t.textContent = msg;
    wrap.appendChild(t);
    setTimeout(() => t.remove(), 3500);
}
function closeModal(id) { document.getElementById(id).classList.remove('open'); }
function openModal(id) { document.getElementById(id).classList.add('open'); }

// toggleActionMenu / closeAllActionsMenus now come from the shared
// assets/js/action-menu.js (loaded by team_layout_bottom.php).

function submitProductDelete(id, name) {
    confirmAction(
        'Delete "' + name + '"? It will be hidden from the active catalog and can be restored later from the Deleted filter.',
        async function () {
            const fd = new FormData();
            fd.append('action', 'soft_delete_product');
            fd.append('id', id);
            const res = await fetch('inventory_action.php', { method: 'POST', body: fd });
            const data = await res.json();
            if (data.success) { toast('Product deleted'); location.reload(); }
            else { toast(data.error || 'Delete failed', true); }
        },
        { title: 'Delete Product?', confirmLabel: 'Delete' }
    );
}
async function submitProductRestore(id, name) {
    const fd = new FormData();
    fd.append('action', 'restore_product');
    fd.append('id', id);
    const res = await fetch('inventory_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { toast('Product restored'); location.reload(); }
    else { toast(data.error || 'Restore failed', true); }
}

function invMoney(v) {
    if (v === null || v === undefined || v === '') return '—';
    return '₹' + Number(v).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}

function openTopProductModal(el) {
    const r = JSON.parse(el.getAttribute('data-info'));
    const img = document.getElementById('tpImg');
    const noImg = document.getElementById('tpNoImg');
    if (r.image) { img.src = '../' + r.image; img.style.display = ''; noImg.style.display = 'none'; }
    else { img.style.display = 'none'; noImg.style.display = ''; }
    img.onerror = function () { img.style.display = 'none'; noImg.style.display = ''; };
    document.getElementById('tpName').textContent = r.name || '—';
    document.getElementById('tpSku').textContent = r.sku ? ('SKU: ' + r.sku) : '';
    document.getElementById('tpCategory').textContent = r.category || '—';
    document.getElementById('tpSeller').textContent = r.farmer_name || '—';
    document.getElementById('tpQty').textContent = r.qty ?? (r.sold ?? '0');
    document.getElementById('tpStock').textContent = r.stock !== null && r.stock !== undefined ? r.stock : '—';
    document.getElementById('tpReserved').textContent = r.reserved !== undefined && r.reserved !== null ? r.reserved : '—';
    document.getElementById('tpPrice').textContent = invMoney(r.price);
    document.getElementById('tpUpdated').textContent = r.updated_at || '—';

    const statusEl = document.getElementById('tpStatus');
    const stock = r.stock !== null ? Number(r.stock) : null;
    const threshold = r.low_stock_threshold !== null ? Number(r.low_stock_threshold) : 0;
    if (stock === null) { statusEl.textContent = '—'; statusEl.className = ''; }
    else if (stock <= 0) { statusEl.textContent = 'Out of Stock'; statusEl.className = 'badge-danger'; }
    else if (stock <= threshold) { statusEl.textContent = 'Low Stock'; statusEl.className = 'badge-warn'; }
    else { statusEl.textContent = 'In Stock'; statusEl.className = 'badge-ok'; }

    document.getElementById('tpViewLink').href = 'inventory.php?tab=products&q=' + encodeURIComponent(r.name || '');
    openModal('topProductModal');
}

function openRentedEquipModal(el) {
    const r = JSON.parse(el.getAttribute('data-info'));
    const img = document.getElementById('reImg');
    const noImg = document.getElementById('reNoImg');
    if (r.image) { img.src = '../' + r.image; img.style.display = ''; noImg.style.display = 'none'; }
    else { img.style.display = 'none'; noImg.style.display = ''; }
    img.onerror = function () { img.style.display = 'none'; noImg.style.display = ''; };
    document.getElementById('reName').textContent = r.name || '—';
    document.getElementById('rePn').textContent = r.pn ? ('Model: ' + r.pn) : (r.serial_no ? ('Serial: ' + r.serial_no) : '');
    document.getElementById('reType').textContent = r.type || '—';
    document.getElementById('reOwner').textContent = r.owner_name || '—';
    document.getElementById('reBookings').textContent = r.bookings ?? '0';
    document.getElementById('reRent').textContent = invMoney(r.rent_per_day) + (r.rent_per_day ? '/day' : '');
    document.getElementById('reValue').textContent = invMoney(r.equipment_value);

    const statusEl = document.getElementById('reStatus');
    if (r.maintenance_status === 'maintenance') { statusEl.textContent = 'Under Maintenance'; statusEl.className = 'badge-warn'; }
    else if (Number(r.availability) === 0) { statusEl.textContent = 'Unavailable'; statusEl.className = 'badge-danger'; }
    else { statusEl.textContent = 'Available'; statusEl.className = 'badge-ok'; }

    document.getElementById('reViewLink').href = 'inventory.php?tab=equipment&q=' + encodeURIComponent(r.name || '');
    openModal('rentedEquipModal');
}

let stockCtx = {};
function openStockModal(id, name, action) {
    stockCtx = { id, action };
    document.getElementById('stockModalTitle').textContent = action === 'add_stock' ? 'Add Stock' : 'Reduce Stock';
    document.getElementById('stockModalSub').textContent = name;
    document.getElementById('stockQty').value = 0;
    document.getElementById('stockRemarks').value = '';
    openModal('stockModal');
}
async function submitStock() {
    const qty = parseInt(document.getElementById('stockQty').value || '0', 10);
    const remarks = document.getElementById('stockRemarks').value;
    const fd = new FormData();
    fd.append('action', stockCtx.action);
    fd.append('id', stockCtx.id);
    fd.append('qty', qty);
    fd.append('remarks', remarks);
    const res = await fetch('inventory_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { toast('Stock updated'); closeModal('stockModal'); location.reload(); }
    else { toast(data.error || 'Update failed', true); }
}

let settingsCtx = {};
function openSettingsModal(id, name, sku, threshold) {
    settingsCtx = { id };
    document.getElementById('settingsModalSub').textContent = name;
    document.getElementById('settingsSku').value = sku || '';
    document.getElementById('settingsThreshold').value = threshold;
    openModal('settingsModal');
}
async function submitSettings() {
    const fd = new FormData();
    fd.append('action', 'update_sku_threshold');
    fd.append('id', settingsCtx.id);
    fd.append('sku', document.getElementById('settingsSku').value);
    fd.append('low_stock_threshold', document.getElementById('settingsThreshold').value);
    const res = await fetch('inventory_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { toast('Settings updated'); closeModal('settingsModal'); location.reload(); }
    else { toast(data.error || 'Update failed', true); }
}

let maintCtx = {};
function openMaintModal(id, name, current) {
    maintCtx = { id };
    document.getElementById('maintModalSub').textContent = name;
    document.getElementById('maintStatus').value = current || 'available';
    openModal('maintModal');
}
async function submitMaint() {
    const fd = new FormData();
    fd.append('action', 'equipment_set_maintenance');
    fd.append('id', maintCtx.id);
    fd.append('status', document.getElementById('maintStatus').value);
    const res = await fetch('inventory_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { toast('Equipment status updated'); closeModal('maintModal'); location.reload(); }
    else { toast(data.error || 'Update failed', true); }
}

let valueCtx = {};
function openValueModal(id, name, current) {
    valueCtx = { id };
    document.getElementById('valueModalSub').textContent = name;
    document.getElementById('equipValue').value = current !== null ? current : '';
    openModal('valueModal');
}
async function submitValue() {
    const fd = new FormData();
    fd.append('action', 'equipment_set_value');
    fd.append('id', valueCtx.id);
    fd.append('equipment_value', document.getElementById('equipValue').value);
    const res = await fetch('inventory_action.php', { method: 'POST', body: fd });
    const data = await res.json();
    if (data.success) { toast('Asset value updated'); closeModal('valueModal'); location.reload(); }
    else { toast(data.error || 'Update failed', true); }
}

// Global search box just re-submits into the current tab's q= filter.
document.getElementById('invGlobalSearch').addEventListener('keydown', function(e) {
    if (e.key === 'Enter') {
        const params = new URLSearchParams(window.location.search);
        params.set('q', this.value);
        params.set('page', 1);
        window.location.search = params.toString();
    }
});
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
