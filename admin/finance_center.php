<?php
// =====================================================================
// admin/finance_center.php — Finance Center (spec §9).
//
// A single, filterable transaction ledger built on top of data the app
// already writes (orders + payouts) — no new tables, purely read-only,
// so it can't break anything else no matter what state the data is in.
// Each row is one of:
//   - an Order Payment  (source: `orders`)
//   - a Seller Payout   (source: `payouts`)
// merged and sorted by date, with the fields the spec asks for:
// Transaction ID, Date, User, Type, Amount, Payment Method, Status,
// Related Order, Related Invoice — plus quick date-range filters
// (Today / Yesterday / This Week / This Month / Last Month / This
// Year / Custom).
//
// Column names vary slightly across AgriCart installs (see
// pages/invoice.php's agi_pick_col pattern) — this page probes for the
// real column the same defensive way instead of assuming one, so it
// works whether or not orders.payment_status / payment_method exist.
//
// Gated by 'finance.view' — the exact same permission admin/invoices.php
// already uses, so anyone who can see Invoices can see the Finance
// Center too, with no new role setup required.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.view');

if (!function_exists('fc_col_exists')) {
    function fc_col_exists(mysqli $conn, string $table, string $col): bool {
        static $cache = [];
        $key = $table . '.' . $col;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($col);
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}
if (!function_exists('fc_pick_col')) {
    function fc_pick_col(mysqli $conn, string $table, array $candidates): ?string {
        foreach ($candidates as $c) { if (fc_col_exists($conn, $table, $c)) return $c; }
        return null;
    }
}
function fc_money($v): string { return '₹' . number_format((float)$v, 2); }

// ---------------------------------------------------------------------
// Date-range presets exactly as listed in the spec.
// ---------------------------------------------------------------------
$range = $_GET['range'] ?? 'this_month';
$allowedRanges = ['today','yesterday','this_week','this_month','last_month','this_year','custom','all'];
if (!in_array($range, $allowedRanges, true)) { $range = 'this_month'; }
$today = date('Y-m-d');
switch ($range) {
    case 'today':      $from = $today; $to = $today; break;
    case 'yesterday':  $from = date('Y-m-d', strtotime('-1 day')); $to = $from; break;
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

// ---------------------------------------------------------------------
// Metadata for the clickable range cards rendered instead of the old
// plain <select> — icon, colour and an at-a-glance date span for each
// preset. Purely derived from $today/$from/$to above, no extra queries.
// ---------------------------------------------------------------------
$rangeMeta = [
    'today'      => ['label' => 'Today',      'icon' => 'fa-calendar-day',   'color' => '#2C5B8F', 'sub' => date('d M Y', strtotime($today))],
    'yesterday'  => ['label' => 'Yesterday',  'icon' => 'fa-calendar-minus', 'color' => '#7C4DFF', 'sub' => date('d M Y', strtotime($today . ' -1 day'))],
    'this_week'  => ['label' => 'This Week',  'icon' => 'fa-calendar-week',  'color' => '#00838F', 'sub' => date('d M', strtotime('monday this week')) . ' – ' . date('d M', strtotime($today))],
    'this_month' => ['label' => 'This Month', 'icon' => 'fa-calendar-days',  'color' => '#2E7D32', 'sub' => date('d M', strtotime(date('Y-m-01'))) . ' – ' . date('d M', strtotime($today))],
    'last_month' => ['label' => 'Last Month', 'icon' => 'fa-calendar-day',   'color' => '#B26A00', 'sub' => date('d M', strtotime('first day of last month')) . ' – ' . date('d M', strtotime('last day of last month'))],
    'this_year'  => ['label' => 'This Year',  'icon' => 'fa-calendar-check', 'color' => '#C2185B', 'sub' => 'Jan – ' . date('M', strtotime($today))],
    'custom'     => ['label' => 'Custom',     'icon' => 'fa-sliders',        'color' => '#5A9802', 'sub' => $range === 'custom' ? (date('d M', strtotime($from)) . ' – ' . date('d M', strtotime($to))) : 'Pick any dates'],
    'all'        => ['label' => 'All Time',   'icon' => 'fa-infinity',       'color' => '#37474F', 'sub' => 'Every record'],
];

$fType   = $_GET['type'] ?? '';       // 'order_payment' | 'seller_payout' | ''
$fStatus = trim($_GET['status'] ?? '');
$fMethod = trim($_GET['method'] ?? ''); // 'cod' | 'online' | ''
$fSearch = trim($_GET['q'] ?? '');

// ---------------------------------------------------------------------
// Probe real column names once (see file header).
// ---------------------------------------------------------------------
$orderAmountCol  = fc_pick_col($conn, 'orders', ['final_amount']) ?: 'total_amount';
$orderPayStatCol = fc_pick_col($conn, 'orders', ['payment_status']);
$orderPayMethCol = fc_pick_col($conn, 'orders', ['payment_mode', 'payment_method', 'pay_method', 'mode_of_payment']);
$userEmailCol    = fc_pick_col($conn, 'users', ['email']);

// ---------------------------------------------------------------------
// Pull order-payment transactions in the selected window.
// ---------------------------------------------------------------------
$transactions = [];
try {
    $selectExtra = ($orderPayStatCol ? ", o.$orderPayStatCol AS pay_status" : ", NULL AS pay_status")
                 . ($orderPayMethCol ? ", o.$orderPayMethCol AS pay_method" : ", NULL AS pay_method")
                 . ($userEmailCol ? ", u.$userEmailCol AS user_email" : ", NULL AS user_email");
    $sql = "SELECT o.id, o.order_number, o.created_at, o.order_status, o.$orderAmountCol AS amount,
                   u.full_name, u.id AS user_id
                   $selectExtra
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            WHERE DATE(o.created_at) BETWEEN ? AND ?
            ORDER BY o.created_at DESC
            LIMIT 500";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $status = $r['pay_status'] ?: (
            $r['order_status'] === 'cancelled' ? 'failed' : (
            $r['order_status'] === 'returned' ? 'refunded' : 'paid'));
        $transactions[] = [
            'txn_id'   => 'ORD-' . ($r['order_number'] ?: $r['id']),
            'date'     => $r['created_at'],
            'user'     => $r['full_name'] ?: ('User #' . $r['user_id']),
            'user_email' => $r['user_email'] ?: '',
            'type'     => 'Order Payment',
            'amount'   => (float)$r['amount'],
            'method'   => $r['pay_method'] ?: '—',
            'status'   => $status,
            'related_order'   => $r['order_number'] ?: ('#' . $r['id']),
            'related_order_id'=> $r['id'],
            'related_invoice' => 'invoice.php?order_id=' . $r['id'],
        ];
    }
} catch (\Throwable $e) {
    // orders table shape differs on this install — that section of the
    // ledger just stays empty instead of a fatal error.
}

// ---------------------------------------------------------------------
// Pull seller-payout transactions in the selected window.
// ---------------------------------------------------------------------
try {
    $payoutEmailSelect = $userEmailCol ? ", u.$userEmailCol AS user_email" : ", NULL AS user_email";
    $stmt = $conn->prepare("
        SELECT p.id, p.amount, p.method, p.status, p.requested_at, u.full_name, u.id AS user_id
               $payoutEmailSelect
        FROM payouts p
        LEFT JOIN users u ON u.id = p.seller_id
        WHERE DATE(p.requested_at) BETWEEN ? AND ?
        ORDER BY p.requested_at DESC
        LIMIT 500
    ");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $transactions[] = [
            'txn_id'   => 'PAYOUT-' . $r['id'],
            'date'     => $r['requested_at'],
            'user'     => $r['full_name'] ?: ('User #' . $r['user_id']),
            'user_email' => $r['user_email'] ?: '',
            'type'     => 'Seller Payout',
            'amount'   => (float)$r['amount'],
            'method'   => $r['method'] ?: '—',
            'status'   => $r['status'],
            'related_order'   => null,
            'related_order_id'=> null,
            'related_invoice' => null,
        ];
    }
} catch (\Throwable $e) {}

// ---------------------------------------------------------------------
// Snapshot of the merged, date-windowed (but not yet type/status/method/
// search filtered) list — used only for the "Quick Finance Insights"
// widgets below, so those stay tied to the selected date range rather
// than to whatever row-level filter the user has applied to the table.
// ---------------------------------------------------------------------
$allTxnsInRange = $transactions;

// ---------------------------------------------------------------------
// Apply type/status/search filters, sort, paginate — all in PHP since
// the two sources have already been merged into one flat array above.
// ---------------------------------------------------------------------
if ($fType === 'order_payment')  { $transactions = array_values(array_filter($transactions, fn($t) => $t['type'] === 'Order Payment')); }
if ($fType === 'seller_payout')  { $transactions = array_values(array_filter($transactions, fn($t) => $t['type'] === 'Seller Payout')); }
if ($fStatus === 'failed_rejected') { $transactions = array_values(array_filter($transactions, fn($t) => in_array(strtolower($t['status']), ['failed','rejected'], true))); }
elseif ($fStatus !== '') { $transactions = array_values(array_filter($transactions, fn($t) => strtolower($t['status']) === strtolower($fStatus))); }
if ($fMethod === 'cod')    { $transactions = array_values(array_filter($transactions, fn($t) => strtolower((string)$t['method']) === 'cod')); }
if ($fMethod === 'online') { $transactions = array_values(array_filter($transactions, fn($t) => strtolower((string)$t['method']) !== 'cod' && strtolower((string)$t['method']) !== '' && strtolower((string)$t['method']) !== '—')); }
if ($fSearch !== '') {
    $needle = mb_strtolower($fSearch);
    $transactions = array_values(array_filter($transactions, function ($t) use ($needle) {
        return str_contains(mb_strtolower($t['txn_id']), $needle)
            || str_contains(mb_strtolower($t['user']), $needle)
            || ($t['related_order'] && str_contains(mb_strtolower($t['related_order']), $needle));
    }));
}
usort($transactions, fn($a, $b) => strcmp($b['date'], $a['date']));

$totalCount  = count($transactions);
$totalAmount = array_sum(array_column($transactions, 'amount'));
$codCount = 0; $codAmount = 0.0; $onlineCount = 0; $onlineAmount = 0.0;
$failedCount = 0; $pendingCount = 0;
foreach ($transactions as $t) {
    $m = strtolower((string)$t['method']);
    if ($m === 'cod') { $codCount++; $codAmount += $t['amount']; }
    elseif ($m !== '—' && $m !== '') { $onlineCount++; $onlineAmount += $t['amount']; }
    $s = strtolower((string)$t['status']);
    if (in_array($s, ['failed','rejected'], true)) { $failedCount++; }
    if (in_array($s, ['pending','processing'], true)) { $pendingCount++; }
}

// ---------------------------------------------------------------------
// CSV export — same filtered result set as the table below, gated by
// the existing 'finance.export' permission key (seeded for Finance
// Manager in setup/admin_rbac.sql, previously unused by any page).
// ---------------------------------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv' && function_exists('hasPermission') && hasPermission('finance.export')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="transactions_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Transaction ID','Date','User','Type','Amount','Method','Status','Related Order']);
    foreach ($transactions as $t) {
        fputcsv($out, [$t['txn_id'], $t['date'], $t['user'], $t['type'], $t['amount'], $t['method'], $t['status'], $t['related_order'] ?: '']);
    }
    fclose($out);
    exit;
}

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$pagedTransactions = array_slice($transactions, ($page - 1) * $perPage, $perPage);
$totalPages = max(1, (int)ceil($totalCount / $perPage));

// ---------------------------------------------------------------------
// Quick Finance Insights — Today's Collection, Top Buyer, Highest Payout
// Seller. Today's Collection is always literally "today" (independent
// of the selected date-range filter, since that's what the label
// promises); the other two are derived in-memory from $allTxnsInRange
// (no extra queries) so they reflect whichever date window is active.
// ---------------------------------------------------------------------
$todaysCollection = 0.0;
try {
    $res2 = $conn->query("SELECT o.$orderAmountCol AS amount, o.order_status" . ($orderPayStatCol ? ", o.$orderPayStatCol AS pay_status" : "") . "
                           FROM orders o WHERE DATE(o.created_at) = CURDATE()");
    if ($res2) {
        while ($r2 = $res2->fetch_assoc()) {
            $st2 = $orderPayStatCol ? ($r2['pay_status'] ?: '') : '';
            if ($st2 === '') { $st2 = ($r2['order_status'] === 'cancelled') ? 'failed' : (($r2['order_status'] === 'returned') ? 'refunded' : 'paid'); }
            if (in_array(strtolower($st2), ['paid', 'completed', 'success'], true)) { $todaysCollection += (float)$r2['amount']; }
        }
    }
} catch (\Throwable $e) {}

$buyerTotals = []; $sellerTotals = [];
foreach ($allTxnsInRange as $t) {
    if ($t['type'] === 'Order Payment' && strtolower($t['status']) !== 'failed') {
        $buyerTotals[$t['user']] = ($buyerTotals[$t['user']] ?? 0) + $t['amount'];
    } elseif ($t['type'] === 'Seller Payout') {
        $sellerTotals[$t['user']] = ($sellerTotals[$t['user']] ?? 0) + $t['amount'];
    }
}
arsort($buyerTotals); arsort($sellerTotals);
$topBuyerName  = array_key_first($buyerTotals);
$topBuyerAmt   = $topBuyerName !== null ? $buyerTotals[$topBuyerName] : 0.0;
$topSellerName = array_key_first($sellerTotals);
$topSellerAmt  = $topSellerName !== null ? $sellerTotals[$topSellerName] : 0.0;

$pageTitle     = 'Transactions';
$pageSubtitle  = 'Track every buyer, seller and platform money movement.';
$pageBreadcrumb = [
    ['label' => 'Finance', 'url' => 'finance_overview.php'],
    ['label' => 'Transactions'],
];
$pageAction    = ['label' => 'View Storefront', 'url' => '../pages/marketplace.php', 'icon' => 'fa-arrow-up-right-from-square', 'newTab' => true];
$activeTeamTab = 'finance_center';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
/* ---- Design tokens (match the Admin Dashboard's own card language) --- */
:root{
    --fc-radius:18px; --fc-input-radius:12px; --fc-btn-radius:12px;
    --fc-shadow:0 10px 30px rgba(16,24,40,.08); --fc-shadow-hover:0 16px 34px rgba(16,24,40,.12);
}

/* ---- Premium filter card ---- */
.filter-card{background:#fff;border-radius:var(--fc-radius);padding:20px 22px;box-shadow:var(--fc-shadow);margin-bottom:20px;transition:box-shadow .25s ease}
.filter-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;margin-bottom:14px}
.filter-search-row{margin-bottom:14px}
.fc-field label{display:block;font-size:10.5px;font-weight:700;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);margin-bottom:6px}
.filter-card select,.filter-card input[type=text],.filter-card input[type=date]{width:100%;padding:10px 13px;border:1.5px solid var(--border);border-radius:var(--fc-input-radius);font-size:13px;background:#fff;font-family:inherit;color:var(--text);transition:border-color .2s ease,box-shadow .2s ease,transform .15s ease}
.filter-card select:hover,.filter-card input:hover{border-color:#c7d0c5}
.filter-card select:focus,.filter-card input:focus{outline:none;border-color:var(--success);box-shadow:0 0 0 3px var(--success-bg)}
.filter-actions{display:flex;justify-content:flex-end;gap:10px;flex-wrap:wrap}
.filter-actions .btn,.filter-actions .btn.outline{border-radius:var(--fc-btn-radius);transition:all .25s ease}
.filter-actions .btn:not(.outline):hover{transform:translateY(-2px);box-shadow:0 8px 18px rgba(47,79,68,.25)}
.filter-actions .btn.outline:hover{transform:translateY(-2px);box-shadow:0 8px 16px rgba(0,0,0,.08)}
@media (max-width:1100px){.filter-grid{grid-template-columns:repeat(2,1fr)}}
@media (max-width:600px){.filter-grid{grid-template-columns:1fr}.filter-actions{justify-content:stretch}.filter-actions .btn,.filter-actions .btn.outline{flex:1;justify-content:center}}

/* ---- Stat cards row — page-specific override so 6 cards wrap evenly
   instead of leaving a lone orphan card in its own row ---- */
.fc-stats-row{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px}
.fc-stats-row .stat-card{display:flex;align-items:center;gap:12px;background:#fff;border-radius:var(--fc-radius);padding:16px 18px;box-shadow:var(--fc-shadow);text-decoration:none;color:inherit;transition:transform .25s ease,box-shadow .25s ease}
.fc-stats-row .stat-card:hover{transform:translateY(-4px);box-shadow:var(--fc-shadow-hover)}
.fc-stats-row .stat-card .icn{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0}
.fc-stats-row .stat-card .val{font-size:17px;font-weight:800;line-height:1.2;white-space:nowrap}
.fc-stats-row .stat-card .lbl{font-size:12px;color:var(--muted);margin-top:2px;white-space:nowrap}
@media (max-width:1100px){.fc-stats-row{grid-template-columns:repeat(2,1fr)}}
@media (max-width:600px){.fc-stats-row{grid-template-columns:1fr}}

/* ---- Quick Finance Insights widgets ---- */
.fc-insights{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;margin-bottom:22px}
.insight-card{background:#fff;border-radius:var(--fc-radius);padding:18px 20px;box-shadow:var(--fc-shadow);display:flex;align-items:center;gap:14px;transition:transform .25s ease,box-shadow .25s ease}
.insight-card:hover{transform:translateY(-4px);box-shadow:var(--fc-shadow-hover)}
.insight-card .icn{width:44px;height:44px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:17px;color:#fff;flex-shrink:0;transition:transform .3s cubic-bezier(.34,1.56,.64,1)}
.insight-card:hover .icn{transform:scale(1.1) rotate(-6deg)}
.insight-card .lbl{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.4px;font-weight:700}
.insight-card .val{font-size:16.5px;font-weight:800;margin-top:3px;line-height:1.2}
.insight-card .sub{font-size:11.5px;color:var(--muted);margin-top:2px}
@media (max-width:900px){.fc-insights{grid-template-columns:1fr}}

/* ---- Premium transactions table ---- */
.agri-table-wrap{background:#fff;border-radius:var(--fc-radius);box-shadow:var(--fc-shadow);padding:4px;overflow:hidden}
.agri-table-scroll{overflow-x:auto}
.agri-table-wrap table{width:100%;border-collapse:collapse;font-size:13px}
.agri-table-wrap th{text-align:left;padding:13px 16px;background:var(--bg-soft);font-size:11px;text-transform:uppercase;letter-spacing:.4px;color:var(--muted);font-weight:700}
.agri-table-wrap thead tr th:first-child{border-radius:14px 0 0 0}
.agri-table-wrap thead tr th:last-child{border-radius:0 14px 0 0}
.agri-table-wrap td{padding:12px 16px;border-bottom:1px solid var(--border);vertical-align:middle}
.agri-table-wrap tbody tr{border-left:3px solid transparent;transition:background .2s ease,border-color .2s ease;cursor:default}
.agri-table-wrap tbody tr:hover{background:#FAFBF8;border-left-color:var(--primary)}
.agri-table-wrap tbody tr:last-child td{border-bottom:none}
.fc-cust{display:flex;align-items:center;gap:10px}
.fc-avatar{width:36px;height:36px;border-radius:50%;background:var(--primary);color:#fff;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:13px;flex-shrink:0}
.fc-avatar.payout{background:#B8860B}
.fc-cust-name{font-weight:600;font-size:13px;white-space:nowrap}
.fc-cust-sub{font-size:11px;color:var(--muted);margin-top:1px}
.fc-txn-id{font-weight:700;font-size:12.5px}
.fc-txn-type{font-size:11px;color:var(--muted);margin-top:1px}
.fc-amount{font-weight:700;font-size:13.5px}
.fc-method{display:inline-flex;align-items:center;gap:6px;font-size:12px;color:var(--text);white-space:nowrap}
.fc-method i{color:var(--muted);font-size:12px}
.fc-view-btn{width:30px;height:30px;border-radius:9px;display:inline-flex;align-items:center;justify-content:center;background:var(--bg-soft);color:var(--primary);transition:background .2s ease,transform .2s ease,color .2s ease}
.fc-view-btn:hover{background:var(--primary);color:#fff;transform:translateY(-2px)}
.badge-status{display:inline-flex;align-items:center;padding:3px 10px;border-radius:20px;font-size:11px;font-weight:600}
.st-paid,.st-completed,.st-success{background:#E6F4EA;color:#1E7E34}
.st-pending,.st-processing{background:#FFF3E0;color:#B26A00}
.st-failed,.st-rejected{background:#FDECEA;color:#C62828}
.st-refunded{background:#E3EEFC;color:#1D5EAB}
@media (max-width:768px){
    .agri-table-wrap table{min-width:820px}
}
</style>

<?php
// Helper for the clickable stat cards below: same query string as now,
// with the given filter keys overridden/cleared, page reset to 1.
function fc_stat_href(array $overrides): string {
    $qs = $_GET;
    unset($qs['page']);
    foreach (['type','status','method'] as $k) { unset($qs[$k]); }
    foreach ($overrides as $k => $v) {
        if ($v === null) { unset($qs[$k]); } else { $qs[$k] = $v; }
    }
    return '?' . http_build_query($qs);
}
?>
<div class="stats-row fc-stats-row">
    <a class="stat-card" href="<?php echo htmlspecialchars(fc_stat_href([])); ?>">
        <div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-receipt"></i></div>
        <div><div class="val"><?php echo number_format($totalCount); ?></div><div class="lbl">Total Transactions</div></div>
    </a>
    <a class="stat-card" href="<?php echo htmlspecialchars(fc_stat_href([])); ?>">
        <div class="icn" style="background:#2E7D32"><i class="fa-solid fa-sack-dollar"></i></div>
        <div><div class="val"><?php echo fc_money($totalAmount); ?></div><div class="lbl">Total Revenue</div></div>
    </a>
    <a class="stat-card" href="<?php echo htmlspecialchars(fc_stat_href(['method' => 'cod'])); ?>">
        <div class="icn" style="background:#5A9802"><i class="fa-solid fa-money-bill-wave"></i></div>
        <div><div class="val"><?php echo number_format($codCount); ?> / <?php echo fc_money($codAmount); ?></div><div class="lbl">COD</div></div>
    </a>
    <a class="stat-card" href="<?php echo htmlspecialchars(fc_stat_href(['method' => 'online'])); ?>">
        <div class="icn" style="background:#00838F"><i class="fa-solid fa-credit-card"></i></div>
        <div><div class="val"><?php echo number_format($onlineCount); ?> / <?php echo fc_money($onlineAmount); ?></div><div class="lbl">Online Payments</div></div>
    </a>
    <a class="stat-card" href="<?php echo htmlspecialchars(fc_stat_href(['status' => 'pending'])); ?>">
        <div class="icn" style="background:#B26A00"><i class="fa-solid fa-hourglass-half"></i></div>
        <div><div class="val"><?php echo number_format($pendingCount); ?></div><div class="lbl">Pending</div></div>
    </a>
    <a class="stat-card" href="<?php echo htmlspecialchars(fc_stat_href(['status' => 'failed_rejected'])); ?>">
        <div class="icn" style="background:#C62828"><i class="fa-solid fa-circle-xmark"></i></div>
        <div><div class="val"><?php echo number_format($failedCount); ?></div><div class="lbl">Failed / Rejected</div></div>
    </a>
</div>

<?php if (!$orderPayStatCol): ?>
<div class="empty-state" style="text-align:left;padding:10px 0 16px;font-size:12px"><i class="fa-solid fa-circle-info"></i> This install's <code>orders</code> table has no <code>payment_status</code> column yet — Order Payment status shown here is derived from <code>order_status</code> as a safe fallback.</div>
<?php endif; ?>

<div class="fc-insights">
    <div class="insight-card">
        <div class="icn" style="background:#2E7D32"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <div>
            <div class="lbl">Today's Collection</div>
            <div class="val"><?php echo fc_money($todaysCollection); ?></div>
            <div class="sub">Paid order payments received today</div>
        </div>
    </div>
    <div class="insight-card">
        <div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-user-tag"></i></div>
        <div>
            <div class="lbl">Top Buyer</div>
            <div class="val"><?php echo $topBuyerName !== null ? htmlspecialchars($topBuyerName) : '—'; ?></div>
            <div class="sub"><?php echo $topBuyerName !== null ? fc_money($topBuyerAmt) . ' in this period' : 'No order payments in this period'; ?></div>
        </div>
    </div>
    <div class="insight-card">
        <div class="icn" style="background:#B8860B"><i class="fa-solid fa-store"></i></div>
        <div>
            <div class="lbl">Highest Revenue Seller</div>
            <div class="val"><?php echo $topSellerName !== null ? htmlspecialchars($topSellerName) : '—'; ?></div>
            <div class="sub"><?php echo $topSellerName !== null ? fc_money($topSellerAmt) . ' paid out this period' : 'No payouts in this period'; ?></div>
        </div>
    </div>
</div>

<form method="get" class="filter-card">
    <div class="filter-grid">
        <div class="fc-field">
            <label for="fcRange">Date Range</label>
            <select id="fcRange" name="range" onchange="this.form.submit()">
                <?php foreach ($rangeMeta as $val => $m): ?>
                <option value="<?php echo $val; ?>" <?php echo $range === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($m['label']); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fc-field">
            <label for="fcType">Transaction Type</label>
            <select id="fcType" name="type" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="order_payment" <?php echo $fType === 'order_payment' ? 'selected' : ''; ?>>Order Payments</option>
                <option value="seller_payout" <?php echo $fType === 'seller_payout' ? 'selected' : ''; ?>>Seller Payouts</option>
            </select>
        </div>
        <div class="fc-field">
            <label for="fcStatus">Status</label>
            <select id="fcStatus" name="status" onchange="this.form.submit()">
                <option value="">All Statuses</option>
                <?php foreach (['paid','pending','processing','completed','failed','rejected','refunded'] as $st): ?>
                <option value="<?php echo $st; ?>" <?php echo $fStatus === $st ? 'selected' : ''; ?>><?php echo ucfirst($st); ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fc-field">
            <label for="fcMethod">Payment Method</label>
            <select id="fcMethod" name="method" onchange="this.form.submit()">
                <option value="">All Methods</option>
                <option value="cod" <?php echo $fMethod === 'cod' ? 'selected' : ''; ?>>COD</option>
                <option value="online" <?php echo $fMethod === 'online' ? 'selected' : ''; ?>>Online</option>
            </select>
        </div>
    </div>
    <?php if ($range === 'custom'): ?>
    <div class="filter-grid" style="grid-template-columns:1fr 1fr">
        <div class="fc-field"><label for="fcFrom">From</label><input id="fcFrom" type="date" name="from" value="<?php echo htmlspecialchars($from); ?>"></div>
        <div class="fc-field"><label for="fcTo">To</label><input id="fcTo" type="date" name="to" value="<?php echo htmlspecialchars($to); ?>"></div>
    </div>
    <?php endif; ?>
    <div class="filter-search-row fc-field">
        <label for="fcSearch">Search Transaction / Order / Buyer</label>
        <input id="fcSearch" type="text" name="q" placeholder="Search transaction, user, order…" value="<?php echo htmlspecialchars($fSearch); ?>">
    </div>
    <div class="filter-actions">
        <a class="btn outline" href="finance_center.php"><i class="fa-solid fa-rotate-left"></i> Reset</a>
        <?php if (function_exists('hasPermission') && hasPermission('finance.export')): $exportQs = $_GET; $exportQs['export'] = 'csv'; ?>
        <a class="btn outline" href="?<?php echo htmlspecialchars(http_build_query($exportQs)); ?>"><i class="fa-solid fa-download"></i> Export CSV</a>
        <?php endif; ?>
        <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </div>
</form>

<div class="agri-table-wrap">
<div class="agri-table-scroll">
<table>
    <thead><tr><th>Transaction</th><th>Customer</th><th>Date &amp; Time</th><th>Amount</th><th>Method</th><th>Status</th><th>Related Order</th><th>Action</th></tr></thead>
    <tbody>
    <?php if (empty($pagedTransactions)): ?>
        <tr><td colspan="8" class="empty-state">No transactions found for this filter.</td></tr>
    <?php else: foreach ($pagedTransactions as $t):
        $isPayout  = ($t['type'] === 'Seller Payout');
        $methodLc  = strtolower((string)$t['method']);
        $methodIcn = $methodLc === 'cod' ? 'fa-money-bill-wave' : ($methodLc === '—' || $methodLc === '' ? 'fa-circle-question' : 'fa-credit-card');
    ?>
        <tr>
            <td>
                <div class="fc-txn-id"><?php echo htmlspecialchars($t['txn_id']); ?></div>
                <div class="fc-txn-type"><?php echo htmlspecialchars($t['type']); ?></div>
            </td>
            <td>
                <div class="fc-cust">
                    <div class="fc-avatar<?php echo $isPayout ? ' payout' : ''; ?>"><?php echo htmlspecialchars(strtoupper(substr($t['user'] ?: '?', 0, 1))); ?></div>
                    <div>
                        <div class="fc-cust-name"><?php echo htmlspecialchars($t['user']); ?></div>
                        <?php if (!empty($t['user_email'])): ?><div class="fc-cust-sub"><?php echo htmlspecialchars($t['user_email']); ?></div><?php endif; ?>
                    </div>
                </div>
            </td>
            <td><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($t['date']))); ?></td>
            <td><span class="fc-amount"><?php echo fc_money($t['amount']); ?></span></td>
            <td><span class="fc-method"><i class="fa-solid <?php echo $methodIcn; ?>"></i> <?php echo htmlspecialchars(strtoupper($t['method'])); ?></span></td>
            <td><span class="badge-status st-<?php echo htmlspecialchars(strtolower($t['status'])); ?>"><?php echo htmlspecialchars(ucfirst($t['status'])); ?></span></td>
            <td><?php echo $t['related_order'] ? htmlspecialchars($t['related_order']) : '—'; ?></td>
            <td><?php if ($t['related_invoice']): ?><a class="fc-view-btn" href="<?php echo htmlspecialchars($t['related_invoice']); ?>" target="_blank" title="View invoice"><i class="fa-solid fa-eye"></i></a><?php else: ?><span style="color:var(--muted)">—</span><?php endif; ?></td>
        </tr>
    <?php endforeach; endif; ?>
    </tbody>
</table>
</div>
</div>

<?php if ($totalPages > 1): ?>
<div class="pagination" style="margin-top:16px;display:flex;gap:6px;flex-wrap:wrap">
    <?php
    $qsBase = $_GET; unset($qsBase['page']);
    for ($p = 1; $p <= $totalPages; $p++):
        $qsBase['page'] = $p;
        $href = '?' . http_build_query($qsBase);
    ?>
    <a href="<?php echo htmlspecialchars($href); ?>" class="<?php echo $p === $page ? 'active' : ''; ?>" style="padding:6px 11px;border:1px solid var(--border);border-radius:6px;text-decoration:none;color:inherit;<?php echo $p === $page ? 'background:var(--primary);color:#fff;border-color:var(--primary)' : ''; ?>"><?php echo $p; ?></a>
    <?php endfor; ?>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
