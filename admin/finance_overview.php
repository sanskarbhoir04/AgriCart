<?php
// =====================================================================
// admin/finance_overview.php — Finance Overview (Finance Centre landing
// page). Same "Dashboard is the master design" approach as
// finance_center.php: no new tables, purely read-only aggregates over
// data the app already writes (orders, order_items, payouts), with the
// same defensive column-probing pattern used throughout admin/*.php so
// it degrades gracefully instead of fataling on installs missing an
// optional column.
//
// Cards: Total Revenue, Platform Commission, Seller Payouts, Pending
// Settlements, Refund Amount, Net Revenue — plus a Revenue/Commission/
// Payout trend chart and quick links into every other Finance module.
//
// Gated by 'finance.view' — same permission as Finance Center/Invoices.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.view');

if (!function_exists('fo_col_exists')) {
    function fo_col_exists(mysqli $conn, string $table, string $col): bool {
        static $cache = [];
        $key = $table . '.' . $col;
        if (array_key_exists($key, $cache)) return $cache[$key];
        $t = $conn->real_escape_string($table);
        $c = $conn->real_escape_string($col);
        $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
        return $cache[$key] = ($res && $res->num_rows > 0);
    }
}
if (!function_exists('fo_pick_col')) {
    function fo_pick_col(mysqli $conn, string $table, array $candidates): ?string {
        foreach ($candidates as $c) { if (fo_col_exists($conn, $table, $c)) return $c; }
        return null;
    }
}
function fo_money($v): string { return '₹' . number_format((float)$v, 2); }

// ---------------------------------------------------------------------
// Date-range presets — identical set/logic to Finance Center so the two
// pages always agree on what "This Month" etc. means.
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

$orderAmountCol = fo_pick_col($conn, 'orders', ['final_amount']) ?: 'total_amount';
$hasChargeCols  = fo_col_exists($conn, 'order_items', 'platform_charge_amount');

// ---------------------------------------------------------------------
// Total Revenue — GMV of orders placed in range, excluding cancelled.
// ---------------------------------------------------------------------
$totalRevenue = 0.0; $orderCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM($orderAmountCol),0) s FROM orders
                             WHERE DATE(created_at) BETWEEN ? AND ? AND order_status <> 'cancelled'");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $orderCount   = (int)($row['c'] ?? 0);
    $totalRevenue = (float)($row['s'] ?? 0);
} catch (\Throwable $e) {}

// ---------------------------------------------------------------------
// Platform Commission — real recorded per-item charge if the column
// exists on this install; otherwise fall back to the resolved default
// rate applied to Total Revenue, clearly labelled as an estimate.
// ---------------------------------------------------------------------
$platformCommission = 0.0; $commissionIsEstimate = false;
if ($hasChargeCols) {
    try {
        $stmt = $conn->prepare("SELECT COALESCE(SUM(oi.platform_charge_amount),0) s
                                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                                 WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status <> 'cancelled'");
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $platformCommission = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);
    } catch (\Throwable $e) {}
} else {
    $commissionIsEstimate = true;
    try {
        require_once __DIR__ . '/../includes/commission_schema.php';
        commission_bootstrap_schema($conn);
        $rate = agri_resolve_commission_percent($conn);
        $platformCommission = round($totalRevenue * ($rate / 100), 2);
    } catch (\Throwable $e) {}
}

// ---------------------------------------------------------------------
// Seller Payouts (completed, in range) + Pending Settlements (current
// state, not date-filtered — it's "what's owed right now").
// ---------------------------------------------------------------------
$sellerPayouts = 0.0; $pendingSettlements = 0.0; $pendingSettlementCount = 0;
try {
    $stmt = $conn->prepare("SELECT COALESCE(SUM(amount),0) s FROM payouts
                             WHERE status='completed' AND DATE(COALESCE(completed_at, requested_at)) BETWEEN ? AND ?");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $sellerPayouts = (float)($stmt->get_result()->fetch_assoc()['s'] ?? 0);

    $p = $conn->query("SELECT COUNT(*) c, COALESCE(SUM(amount),0) s FROM payouts WHERE status IN ('pending','processing')");
    $prow = $p ? $p->fetch_assoc() : null;
    $pendingSettlementCount = (int)($prow['c'] ?? 0);
    $pendingSettlements     = (float)($prow['s'] ?? 0);
} catch (\Throwable $e) {}

// ---------------------------------------------------------------------
// Refund Amount — orders returned/refunded in range.
// ---------------------------------------------------------------------
$refundAmount = 0.0; $refundCount = 0;
try {
    $stmt = $conn->prepare("SELECT COUNT(*) c, COALESCE(SUM($orderAmountCol),0) s FROM orders
                             WHERE DATE(created_at) BETWEEN ? AND ? AND order_status IN ('returned','refunded')");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $refundCount  = (int)($row['c'] ?? 0);
    $refundAmount = (float)($row['s'] ?? 0);
} catch (\Throwable $e) {}

$netRevenue = $platformCommission - $refundAmount;

// ---------------------------------------------------------------------
// Trend — daily buckets for ranges up to ~45 days, monthly otherwise.
// ---------------------------------------------------------------------
$dayCount = (strtotime($to) - strtotime($from)) / 86400;
$byMonth  = $dayCount > 45;
$trendLabels = []; $trendRevenue = []; $trendCommission = []; $trendPayouts = []; $trendVolume = [];
try {
    $fmt = $byMonth ? '%Y-%m' : '%Y-%m-%d';
    $stmt = $conn->prepare("SELECT DATE_FORMAT(created_at,'$fmt') bucket, COUNT(*) vol, COALESCE(SUM($orderAmountCol),0) rev
                             FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND order_status <> 'cancelled'
                             GROUP BY bucket ORDER BY bucket ASC");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    $revByBucket = []; $volByBucket = [];
    while ($r = $res->fetch_assoc()) { $revByBucket[$r['bucket']] = (float)$r['rev']; $volByBucket[$r['bucket']] = (int)$r['vol']; }

    $commByBucket = [];
    if ($hasChargeCols) {
        $stmt = $conn->prepare("SELECT DATE_FORMAT(o.created_at,'$fmt') bucket, COALESCE(SUM(oi.platform_charge_amount),0) c
                                 FROM order_items oi JOIN orders o ON o.id = oi.order_id
                                 WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status <> 'cancelled'
                                 GROUP BY bucket");
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res2 = $stmt->get_result();
        while ($r = $res2->fetch_assoc()) { $commByBucket[$r['bucket']] = (float)$r['c']; }
    }

    $payByBucket = [];
    $stmt = $conn->prepare("SELECT DATE_FORMAT(COALESCE(completed_at,requested_at),'$fmt') bucket, COALESCE(SUM(amount),0) p
                             FROM payouts WHERE status='completed' AND DATE(COALESCE(completed_at,requested_at)) BETWEEN ? AND ?
                             GROUP BY bucket");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res3 = $stmt->get_result();
    while ($r = $res3->fetch_assoc()) { $payByBucket[$r['bucket']] = (float)$r['p']; }

    $cursor = $from;
    while ($cursor <= $to) {
        $bucket = $byMonth ? date('Y-m', strtotime($cursor)) : $cursor;
        if (!in_array($bucket, $trendLabels, true)) {
            $trendLabels[]     = $bucket;
            $trendRevenue[]    = round($revByBucket[$bucket] ?? 0, 2);
            $trendCommission[] = round($commByBucket[$bucket] ?? 0, 2);
            $trendPayouts[]    = round($payByBucket[$bucket] ?? 0, 2);
            $trendVolume[]     = (int)($volByBucket[$bucket] ?? 0);
        }
        $cursor = date('Y-m-d', strtotime($cursor . ' +1 day'));
    }
} catch (\Throwable $e) {}

$pageTitle     = 'Finance Overview';
$pageSubtitle  = 'Manage AgriCart revenue, transactions, commissions, seller payouts and financial operations.';
$activeTeamTab = 'finance_overview';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.fo-filter-card{background:#fff;border-radius:16px;padding:14px 18px;box-shadow:0 1px 3px rgba(20,30,25,0.06);margin-bottom:18px}
.fo-filters{display:flex;flex-wrap:wrap;gap:10px;align-items:center}
.fo-filters label{font-size:12.5px;font-weight:600;color:var(--muted);margin-right:-2px}
.fo-filters select{width:auto;min-width:170px;flex:0 0 auto}
.fo-filters select,.fo-filters input[type=date]{padding:9px 12px;border:1px solid var(--border);border-radius:8px;font-size:13px;font-family:inherit;background:#fff;color:var(--text);transition:border-color .15s ease, box-shadow .15s ease}
.fo-filters select:focus,.fo-filters input[type=date]:focus{outline:none;border-color:var(--primary);box-shadow:0 0 0 3px rgba(47,79,68,.12)}
.fo-apply-btn{background:var(--primary);color:#fff;border:none;padding:9px 18px;border-radius:9px;font-size:13.5px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:8px;transition:background .2s ease, transform .15s cubic-bezier(.34,1.56,.64,1), box-shadow .2s ease;font-family:inherit;flex:0 0 auto}
.fo-apply-btn:hover{background:var(--primary-dark,#1B2F29);transform:translateY(-2px);box-shadow:0 6px 14px rgba(47,79,68,.3)}
.fo-apply-btn:active{transform:translateY(0) scale(.97)}
.fo-quicklinks{display:grid;grid-template-columns:repeat(4,1fr);gap:14px;margin-top:6px}
@media (max-width:900px){.fo-quicklinks{grid-template-columns:repeat(2,1fr)}}
@media (max-width:480px){.fo-quicklinks{grid-template-columns:1fr}}
.fo-modcard .val{font-size:14.5px;font-weight:700;line-height:1.3}
.fo-note{font-size:12px;color:var(--muted);margin:-10px 0 18px}
</style>

<div class="fo-filter-card">
    <form method="get" class="fo-filters">
        <label for="foRange">Date Range</label>
        <select name="range" id="foRange" onchange="this.form.submit()">
            <?php foreach (['today'=>'Today','yesterday'=>'Yesterday','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','custom'=>'Custom','all'=>'All Time'] as $val => $label): ?>
            <option value="<?php echo $val; ?>" <?php echo $range === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
            <?php endforeach; ?>
        </select>
        <?php if ($range === 'custom'): ?>
        <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
        <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
        <?php endif; ?>
        <button class="fo-apply-btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
    </form>
</div>

<div class="stats-row">
    <a class="stat-card" href="finance_center.php?range=<?php echo urlencode($range); ?>">
        <div class="icn" style="background:#2E7D32"><i class="fa-solid fa-sack-dollar"></i></div>
        <div><div class="val"><?php echo fo_money($totalRevenue); ?></div><div class="lbl">Total Revenue (<?php echo $orderCount; ?> orders)</div></div>
    </a>
    <a class="stat-card" href="commission.php">
        <div class="icn" style="background:#5A9802"><i class="fa-solid fa-percent"></i></div>
        <div><div class="val"><?php echo fo_money($platformCommission); ?></div><div class="lbl">Platform Commission<?php echo $commissionIsEstimate ? ' (est.)' : ''; ?></div></div>
    </a>
    <a class="stat-card" href="seller_payouts.php">
        <div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <div><div class="val"><?php echo fo_money($sellerPayouts); ?></div><div class="lbl">Seller Payouts</div></div>
    </a>
    <a class="stat-card" href="settlement_history.php">
        <div class="icn" style="background:#B26A00"><i class="fa-solid fa-hourglass-half"></i></div>
        <div><div class="val"><?php echo fo_money($pendingSettlements); ?></div><div class="lbl">Pending Settlements (<?php echo $pendingSettlementCount; ?>)</div></div>
    </a>
    <a class="stat-card" href="refunds.php">
        <div class="icn" style="background:#C62828"><i class="fa-solid fa-rotate-left"></i></div>
        <div><div class="val"><?php echo fo_money($refundAmount); ?></div><div class="lbl">Refund Amount (<?php echo $refundCount; ?>)</div></div>
    </a>
    <div class="stat-card">
        <div class="icn" style="background:<?php echo $netRevenue >= 0 ? '#2E7D32' : '#C62828'; ?>"><i class="fa-solid fa-chart-line"></i></div>
        <div><div class="val"><?php echo fo_money($netRevenue); ?></div><div class="lbl">Net Revenue</div></div>
    </div>
</div>
<div class="fo-note"><i class="fa-solid fa-circle-info"></i> Net Revenue = Platform Commission − Refund Amount for the selected period.<?php echo $commissionIsEstimate ? ' Platform Commission is estimated from the current default commission rate — this install has no per-order commission column recorded yet.' : ''; ?></div>

<div class="card">
    <div class="card-head">
        <h2>Revenue, Commission &amp; Payout Trend</h2>
    </div>
    <canvas id="financeTrendChart" height="90"></canvas>
    <?php if (empty($trendLabels)): ?>
    <div class="empty-state"><i class="fa-solid fa-chart-line"></i>No data in this range yet.</div>
    <?php endif; ?>
</div>

<div class="card">
    <div class="card-head"><h2>Finance Modules</h2></div>
    <div class="fo-quicklinks">
        <a class="stat-card fo-modcard" href="finance_center.php"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-scale-balanced"></i></div><div><div class="val">Transactions</div></div></a>
        <a class="stat-card fo-modcard" href="seller_payouts.php"><div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-hand-holding-dollar"></i></div><div><div class="val">Seller Payouts</div></div></a>
        <a class="stat-card fo-modcard" href="settlement_history.php"><div class="icn" style="background:#B26A00"><i class="fa-solid fa-clock-rotate-left"></i></div><div><div class="val">Settlement History</div></div></a>
        <a class="stat-card fo-modcard" href="commission.php"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-percent"></i></div><div><div class="val">Commission &amp; Charges</div></div></a>
        <a class="stat-card fo-modcard" href="refunds.php"><div class="icn" style="background:#C62828"><i class="fa-solid fa-rotate-left"></i></div><div><div class="val">Refunds</div></div></a>
        <a class="stat-card fo-modcard" href="report.php"><div class="icn" style="background:#6A1B9A"><i class="fa-solid fa-chart-pie"></i></div><div><div class="val">Financial Reports</div></div></a>
        <a class="stat-card fo-modcard" href="invoices.php"><div class="icn" style="background:#00695C"><i class="fa-solid fa-file-invoice-dollar"></i></div><div><div class="val">Invoices</div></div></a>
        <a class="stat-card fo-modcard" href="gst_tax_report.php"><div class="icn" style="background:#1565C0"><i class="fa-solid fa-file-invoice"></i></div><div><div class="val">Tax / GST</div></div></a>
    </div>
</div>

<script>
const financeTrendLabels     = <?php echo json_encode($trendLabels); ?>;
const financeTrendRevenue    = <?php echo json_encode($trendRevenue); ?>;
const financeTrendCommission = <?php echo json_encode($trendCommission); ?>;
const financeTrendPayouts    = <?php echo json_encode($trendPayouts); ?>;
document.addEventListener('DOMContentLoaded', function () {
    if (typeof Chart === 'undefined') return;
    const cv = document.getElementById('financeTrendChart');
    if (!cv || !financeTrendLabels.length) return;
    new Chart(cv, {
        type: 'line',
        data: {
            labels: financeTrendLabels,
            datasets: [
                { label: 'Revenue',    data: financeTrendRevenue,    borderColor: '#2E7D32', backgroundColor: 'rgba(46,125,50,.08)', fill: true, tension: .3 },
                { label: 'Commission', data: financeTrendCommission, borderColor: '#5A9802', backgroundColor: 'rgba(90,152,2,.08)',  fill: true, tension: .3 },
                { label: 'Payouts',    data: financeTrendPayouts,    borderColor: '#2C5B8F', backgroundColor: 'rgba(44,91,143,.08)', fill: true, tension: .3 }
            ]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' }, tooltip: { callbacks: { label: c => c.dataset.label + ': ₹' + Number(c.raw).toLocaleString('en-IN') } } },
            scales: { y: { beginAtZero: true, ticks: { callback: v => '₹' + v.toLocaleString('en-IN') } }, x: { grid: { display: false } } }
        }
    });
});
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
