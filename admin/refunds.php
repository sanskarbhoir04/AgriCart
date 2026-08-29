<?php
// =====================================================================
// admin/refunds.php — Refunds (Finance Centre module).
//
// AgriCart has no separate refunds table — a refund is an order whose
// order_status has moved to 'returned' (return requested / in progress)
// or 'refunded' (money actually sent back), the same lifecycle already
// enforced by admin/order_action.php. This page is a read-only,
// filterable ledger over that existing lifecycle rather than a second,
// disconnected refund workflow — approving/rejecting a return still
// happens on the Orders page, so status here can never drift out of
// sync with the order itself.
//
// Gated by 'finance.refund' — this key already exists in
// setup/admin_rbac.sql (granted to the Finance Manager role) but had no
// page wired to it until now.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.refund');

if (!function_exists('rf_col_exists')) {
    function rf_col_exists(mysqli $conn, string $table, string $col): bool {
        static $cache = [];
        $key = $table . '.' . $col;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($col);
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}
if (!function_exists('rf_pick_col')) {
    function rf_pick_col(mysqli $conn, string $table, array $candidates): ?string {
        foreach ($candidates as $c) { if (rf_col_exists($conn, $table, $c)) return $c; }
        return null;
    }
}
function rf_money($v): string { return '₹' . number_format((float)$v, 2); }

$range = $_GET['range'] ?? 'this_month';
$allowedRanges = ['today','this_week','this_month','last_month','this_year','custom','all'];
if (!in_array($range, $allowedRanges, true)) { $range = 'this_month'; }
$today = date('Y-m-d');
switch ($range) {
    case 'today':      $from = $today; $to = $today; break;
    case 'this_week':  $from = date('Y-m-d', strtotime('monday this week')); $to = $today; break;
    case 'last_month':
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_year':  $from = date('Y-01-01'); $to = $today; break;
    case 'custom':
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-30 days'));
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        break;
    case 'all':         $from = '2000-01-01'; $to = '2100-01-01'; break;
    case 'this_month':
    default:            $from = date('Y-m-01'); $to = $today; break;
}
$statusFilter = trim($_GET['status'] ?? '');
$search       = trim($_GET['q'] ?? '');

$orderAmountCol = rf_pick_col($conn, 'orders', ['final_amount']) ?: 'total_amount';
$updatedAtCol   = rf_pick_col($conn, 'orders', ['updated_at']);

$where = ["o.order_status IN ('returned','refunded')", "DATE(o.created_at) BETWEEN ? AND ?"];
$types = 'ss'; $params = [$from, $to];
if ($statusFilter === 'returned' || $statusFilter === 'refunded') {
    $where[] = 'o.order_status = ?'; $types .= 's'; $params[] = $statusFilter;
}
if ($search !== '') {
    $where[] = "(o.order_number LIKE ? OR u.full_name LIKE ? OR u.mobile LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sss'; array_push($params, $like, $like, $like);
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$refunds = []; $total = 0;
$totalCount = 0; $pendingCount = 0; $completedCount = 0; $totalAmount = 0.0;
try {
    $updSel = $updatedAtCol ? ", o.$updatedAtCol AS processed_at" : ", NULL AS processed_at";
    $countStmt = $conn->prepare("SELECT COUNT(*) c FROM orders o LEFT JOIN users u ON u.id = o.user_id $whereSql");
    $countStmt->bind_param($types, ...$params);
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);

    $sql = "SELECT o.id, o.order_number, o.created_at, o.order_status, o.$orderAmountCol AS amount,
                   u.full_name, u.id AS user_id $updSel
            FROM orders o LEFT JOIN users u ON u.id = o.user_id
            $whereSql ORDER BY o.created_at DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();
    $res = $stmt->get_result();
    $orderIds = [];
    while ($r = $res->fetch_assoc()) { $refunds[] = $r; $orderIds[] = (int)$r['id']; }

    // Seller(s) per order — an order can have items from more than one
    // seller, so this shows every distinct seller name on that order
    // (same "mixed-vendor" handling as admin/invoice.php).
    $sellersByOrder = [];
    if ($orderIds) {
        $in = implode(',', array_fill(0, count($orderIds), '?'));
        $st2 = $conn->prepare("SELECT oi.order_id, GROUP_CONCAT(DISTINCT su.full_name SEPARATOR ', ') sellers
                                FROM order_items oi
                                JOIN products p ON p.id = oi.product_id
                                LEFT JOIN users su ON su.id = p.added_by_user_id
                                WHERE oi.order_id IN ($in) GROUP BY oi.order_id");
        $st2->bind_param(str_repeat('i', count($orderIds)), ...$orderIds);
        $st2->execute();
        $r2 = $st2->get_result();
        while ($row = $r2->fetch_assoc()) { $sellersByOrder[(int)$row['order_id']] = $row['sellers']; }
    }
    foreach ($refunds as &$rf) { $rf['sellers'] = $sellersByOrder[(int)$rf['id']] ?? '—'; }
    unset($rf);

    // Summary counts over the whole filtered date range (not just this page).
    $sumStmt = $conn->prepare("SELECT
        SUM(o.order_status='returned') AS pending_c,
        SUM(o.order_status='refunded') AS completed_c,
        COUNT(*) AS total_c,
        COALESCE(SUM(o.$orderAmountCol),0) AS total_amt
        FROM orders o LEFT JOIN users u ON u.id = o.user_id $whereSql");
    $sumStmt->bind_param($types, ...$params);
    $sumStmt->execute();
    $srow = $sumStmt->get_result()->fetch_assoc();
    $pendingCount   = (int)($srow['pending_c'] ?? 0);
    $completedCount = (int)($srow['completed_c'] ?? 0);
    $totalCount     = (int)($srow['total_c'] ?? 0);
    $totalAmount    = (float)($srow['total_amt'] ?? 0);
} catch (\Throwable $e) {}

$totalPages = max(1, (int)ceil($total / $perPage));

// Helper to build a stat-card link that swaps the status filter and tags
// which specific card was clicked (view), preserving other active filters
// (range, from, to, q), and resetting pagination.
function rf_card_href(string $status, string $view): string {
    $qs = $_GET;
    $qs['status'] = $status;
    $qs['view']   = $view;
    unset($qs['page']);
    return '?' . http_build_query($qs);
}
// Total Refunds and Refund Amount both map to status='' (all statuses), so
// status alone can't tell them apart. `view` disambiguates which single
// card should show as active.
$view = trim($_GET['view'] ?? '');
$expectedStatus = ['total' => '', 'pending' => 'returned', 'completed' => 'refunded', 'amount' => ''];
if ($view === '' || !isset($expectedStatus[$view]) || $statusFilter !== $expectedStatus[$view]) {
    $view = $statusFilter === 'returned' ? 'pending' : ($statusFilter === 'refunded' ? 'completed' : 'total');
}

// CSV export of the full filtered date-range result (not just this page).
if (isset($_GET['export']) && $_GET['export'] === 'csv' && function_exists('hasPermission') && hasPermission('finance.export')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="refunds_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Refund ID','Order ID','Customer','Seller','Refund Amount','Status','Requested Date','Processed Date']);
    try {
        $allStmt = $conn->prepare("SELECT o.id, o.order_number, o.created_at, o.order_status, o.$orderAmountCol AS amount, u.full_name, u.id AS user_id"
            . ($updatedAtCol ? ", o.$updatedAtCol AS processed_at" : ", NULL AS processed_at")
            . " FROM orders o LEFT JOIN users u ON u.id = o.user_id $whereSql ORDER BY o.created_at DESC");
        $allStmt->bind_param($types, ...$params);
        $allStmt->execute();
        $allRows = $allStmt->get_result()->fetch_all(MYSQLI_ASSOC);

        $exportSellers = [];
        $exportIds = array_column($allRows, 'id');
        if ($exportIds) {
            $in = implode(',', array_fill(0, count($exportIds), '?'));
            $st3 = $conn->prepare("SELECT oi.order_id, GROUP_CONCAT(DISTINCT su.full_name SEPARATOR ', ') sellers
                                    FROM order_items oi JOIN products p ON p.id = oi.product_id
                                    LEFT JOIN users su ON su.id = p.added_by_user_id
                                    WHERE oi.order_id IN ($in) GROUP BY oi.order_id");
            $st3->bind_param(str_repeat('i', count($exportIds)), ...$exportIds);
            $st3->execute();
            $r3 = $st3->get_result();
            while ($row = $r3->fetch_assoc()) { $exportSellers[(int)$row['order_id']] = $row['sellers']; }
        }

        foreach ($allRows as $r) {
            fputcsv($out, [
                'REF-' . ($r['order_number'] ?: $r['id']),
                '#' . ($r['order_number'] ?: $r['id']),
                $r['full_name'] ?: ('User #' . $r['user_id']),
                $exportSellers[(int)$r['id']] ?? '—',
                $r['amount'],
                $r['order_status'] === 'returned' ? 'Pending' : 'Refunded',
                $r['created_at'],
                ($r['order_status'] === 'refunded' && $r['processed_at']) ? $r['processed_at'] : '',
            ]);
        }
    } catch (\Throwable $e) {}
    fclose($out);
    exit;
}

$pageTitle     = 'Refunds';
$pageSubtitle  = 'Track returned and refunded orders across AgriCart.';
$activeTeamTab = 'refunds';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.rf-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:14px 16px}
.rf-filters-fields{display:flex;flex-wrap:wrap;gap:10px;flex:1;min-width:0}
.rf-field{position:relative;flex:1;min-width:150px;display:flex;align-items:center}
.rf-field-search{flex:1.6;min-width:200px}
.rf-field i{position:absolute;left:12px;font-size:12px;color:#9aa0a6;pointer-events:none}
.rf-filters select,.rf-filters input[type=text],.rf-filters input[type=date]{width:100%;padding:9px 12px 9px 32px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff;color:inherit;transition:border-color .15s ease,box-shadow .15s ease}
.rf-filters select:hover,.rf-filters input[type=text]:hover,.rf-filters input[type=date]:hover{border-color:#c3c8ce}
.rf-filters select:focus,.rf-filters input[type=text]:focus,.rf-filters input[type=date]:focus{outline:none;border-color:#5A9802;box-shadow:0 0 0 3px rgba(90,152,2,.14)}
.rf-filters-actions{display:flex;gap:8px;flex-shrink:0}
.badge-status{display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700}
.st-returned,.st-pending{background:#FFF3E0;color:#B26A00}
.st-refunded,.st-completed{background:#EDE7F6;color:#5E35B1}
.stat-card{transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
a.stat-card-link{display:block;text-decoration:none;color:inherit;border-radius:12px}
a.stat-card-link:hover .stat-card{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
a.stat-card-link .stat-card{cursor:pointer}
a.stat-card-link.is-active .stat-card{border:1.5px solid var(--border);box-shadow:0 0 0 3px rgba(46,125,50,.12)}
.rf-table-card{background:#fff;border-radius:14px;padding:22px 24px 16px;box-shadow:0 1px 3px rgba(0,0,0,.05);border:1px solid var(--border)}
.rf-table-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.rf-table-card-head h3{margin:0;font-size:16px;font-weight:700}
.rf-table-card-head .rf-count{color:#8a8f98;font-weight:600}
.rf-table-card .agri-table-wrap table thead th{background:#F5F6F4;font-size:11px;letter-spacing:.4px;text-transform:uppercase;color:#6b7076;font-weight:700;padding:12px 14px;border-bottom:none}
.rf-table-card .agri-table-wrap table thead tr th:first-child{border-top-left-radius:10px;border-bottom-left-radius:10px}
.rf-table-card .agri-table-wrap table thead tr th:last-child{border-top-right-radius:10px;border-bottom-right-radius:10px}
.rf-table-card .agri-table-wrap table tbody td{padding:14px;font-size:13.5px;border-bottom:1px solid var(--border)}
.rf-table-card .agri-table-wrap table tbody tr:last-child td{border-bottom:none}
.rf-table-card .agri-table-wrap table tbody tr:hover td{background:#FAFBF9}
.rf-table-card .rf-id{color:#6b7076;font-weight:600}
.rf-table-card .rf-customer{font-weight:600}
.rf-table-card .rf-muted{color:#6b7076}
.rf-table-card td a{color:#5A9802;margin-right:4px}
@media (max-width:768px){
  .agri-table-wrap{overflow-x:auto} .agri-table-wrap table{min-width:900px}
  .rf-filters{flex-direction:column;align-items:stretch;padding:12px}
  .rf-filters-fields{flex-direction:column}
  .rf-field{min-width:0}
  .rf-filters-actions{width:100%}
  .rf-filters-actions .btn{flex:1;justify-content:center}
}
</style>

<div class="stats-row">
    <a class="stat-card-link <?php echo $view === 'total' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(rf_card_href('', 'total')); ?>" title="View all refunds">
        <div class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-rotate-left"></i></div><div><div class="val"><?php echo number_format($totalCount); ?></div><div class="lbl">Total Refunds</div></div></div>
    </a>
    <a class="stat-card-link <?php echo $view === 'pending' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(rf_card_href('returned', 'pending')); ?>" title="View pending returns">
        <div class="stat-card"><div class="icn" style="background:#B26A00"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo number_format($pendingCount); ?></div><div class="lbl">Pending (Return Requested)</div></div></div>
    </a>
    <a class="stat-card-link <?php echo $view === 'completed' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(rf_card_href('refunded', 'completed')); ?>" title="View completed refunds">
        <div class="stat-card"><div class="icn" style="background:#5E35B1"><i class="fa-solid fa-circle-check"></i></div><div><div class="val"><?php echo number_format($completedCount); ?></div><div class="lbl">Completed (Refunded)</div></div></div>
    </a>
    <a class="stat-card-link <?php echo $view === 'amount' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(rf_card_href('', 'amount')); ?>" title="View all refunds">
        <div class="stat-card"><div class="icn" style="background:#C62828"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="val"><?php echo rf_money($totalAmount); ?></div><div class="lbl">Refund Amount</div></div></div>
    </a>
</div>

<form method="get" class="rf-filters">
    <div class="rf-filters-fields">
        <div class="rf-field">
            <i class="fa-solid fa-calendar-days"></i>
            <select name="range" onchange="this.form.submit()">
                <?php foreach (['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','custom'=>'Custom','all'=>'All Time'] as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php echo $range === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php if ($range === 'custom'): ?>
        <div class="rf-field">
            <i class="fa-solid fa-calendar"></i>
            <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        </div>
        <div class="rf-field">
            <i class="fa-solid fa-calendar"></i>
            <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        </div>
        <?php endif; ?>
        <div class="rf-field">
            <i class="fa-solid fa-circle-check"></i>
            <select name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <option value="returned" <?php echo $statusFilter === 'returned' ? 'selected' : ''; ?>>Pending (Returned)</option>
                <option value="refunded" <?php echo $statusFilter === 'refunded' ? 'selected' : ''; ?>>Completed (Refunded)</option>
            </select>
        </div>
        <div class="rf-field rf-field-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Search order #, customer…" value="<?php echo htmlspecialchars($search); ?>">
        </div>
    </div>
    <div class="rf-filters-actions">
        <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
        <?php if (function_exists('hasPermission') && hasPermission('finance.export')): $exportQs = $_GET; $exportQs['export'] = 'csv'; ?>
        <a class="btn outline" href="?<?php echo htmlspecialchars(http_build_query($exportQs)); ?>"><i class="fa-solid fa-download"></i> Export CSV</a>
        <?php endif; ?>
    </div>
</form>

<div class="rf-table-card">
    <div class="rf-table-card-head">
        <h3>Refunds <span class="rf-count">(<?php echo number_format($total); ?>)</span></h3>
    </div>
    <div class="agri-table-wrap">
    <table>
        <thead><tr><th>Refund ID</th><th>Order ID</th><th>Customer</th><th>Seller</th><th>Refund Amount</th><th>Status</th><th>Requested Date</th><th>Processed Date</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (empty($refunds)): ?>
            <tr><td colspan="9" class="empty-state"><i class="fa-solid fa-rotate-left"></i>No refunds found for this filter.</td></tr>
        <?php else: foreach ($refunds as $rf): ?>
            <tr>
                <td class="rf-id">REF-<?php echo htmlspecialchars($rf['order_number'] ?: $rf['id']); ?></td>
                <td class="rf-muted">#<?php echo htmlspecialchars($rf['order_number'] ?: $rf['id']); ?></td>
                <td class="rf-customer"><?php echo htmlspecialchars($rf['full_name'] ?: ('User #' . $rf['user_id'])); ?></td>
                <td><?php echo htmlspecialchars($rf['sellers']); ?></td>
                <td><?php echo rf_money($rf['amount']); ?></td>
                <td><span class="badge-status st-<?php echo htmlspecialchars($rf['order_status']); ?>"><?php echo $rf['order_status'] === 'returned' ? 'Pending' : 'Refunded'; ?></span></td>
                <td class="rf-muted"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($rf['created_at']))); ?></td>
                <td class="rf-muted"><?php echo $rf['order_status'] === 'refunded' && $rf['processed_at'] ? htmlspecialchars(date('d M Y, h:i A', strtotime($rf['processed_at']))) : '—'; ?></td>
                <td>
                    <a href="invoice.php?order_id=<?php echo (int)$rf['id']; ?>" target="_blank" title="View invoice"><i class="fa-solid fa-file-invoice"></i></a>
                    &nbsp;
                    <a href="index.php?tab=orders" title="Manage in Orders"><i class="fa-solid fa-eye"></i></a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:16px">
    <?php $qsBase = $_GET; unset($qsBase['page']); for ($p = 1; $p <= $totalPages; $p++): $qsBase['page'] = $p; $href = '?' . http_build_query($qsBase); ?>
    <a href="<?php echo htmlspecialchars($href); ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
