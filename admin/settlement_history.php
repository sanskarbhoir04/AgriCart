<?php
// =====================================================================
// admin/settlement_history.php — Settlement History (Finance Centre
// module). A read-only historical ledger of seller payouts, one row
// per `payouts` row, complementing admin/seller_payouts.php (which is
// the pending-request review *queue*). No new table: a "settlement"
// here is simply a payout that has left pending/processing.
//
// Note on Gross/Commission columns: `payouts.amount` is drawn from the
// seller's pooled available_balance (already net of commission across
// possibly many order items — see includes/seller_functions.php), so a
// single payout does not map 1:1 to one gross/commission figure. Rather
// than invent a number, this page shows the real Net Amount only.
//
// Gated by 'finance.payout' — same permission as Seller Payouts, since
// this is the same underlying data.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.payout');

function sh_money($v): string { return '₹' . number_format((float)$v, 2); }

$statusFilter = trim($_GET['status'] ?? 'all');
$allowedFilters = ['completed', 'rejected', 'all'];
if (!in_array($statusFilter, $allowedFilters, true)) { $statusFilter = 'all'; }

$search = trim($_GET['q'] ?? '');
$method = trim($_GET['method'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = ["p.status IN ('completed','rejected')"];
$types = ''; $params = [];
if ($statusFilter !== 'all') { $where[] = 'p.status = ?'; $types .= 's'; $params[] = $statusFilter; }
if ($method === 'bank' || $method === 'upi') { $where[] = 'p.method = ?'; $types .= 's'; $params[] = $method; }
if ($search !== '') {
    $where[] = "(u.full_name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ?)";
    $like = '%' . $search . '%';
    $types .= 'sss'; array_push($params, $like, $like, $like);
}
$whereSql = 'WHERE ' . implode(' AND ', $where);
$joinSql = "FROM payouts p LEFT JOIN users u ON u.id = p.seller_id";

$settlements = []; $total = 0;
$completedCount = 0; $completedAmount = 0.0; $thisMonthAmount = 0.0; $avgAmount = 0.0;
try {
    $countStmt = $conn->prepare("SELECT COUNT(*) c $joinSql $whereSql");
    if ($types !== '') { $countStmt->bind_param($types, ...$params); }
    $countStmt->execute();
    $total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $sql = "SELECT p.id, p.seller_id, p.amount, p.method, p.account_details, p.status,
                   p.requested_at, p.completed_at, p.notes, u.full_name
            $joinSql $whereSql ORDER BY COALESCE(p.completed_at, p.requested_at) DESC LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) { $settlements[] = $r; }

    $sum = $conn->query("SELECT
        SUM(status='completed') AS c_count,
        COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) AS c_amount,
        COALESCE(SUM(CASE WHEN status='completed' AND DATE_FORMAT(completed_at,'%Y-%m')=DATE_FORMAT(NOW(),'%Y-%m') THEN amount ELSE 0 END),0) AS m_amount
        FROM payouts");
    $srow = $sum ? $sum->fetch_assoc() : null;
    $completedCount  = (int)($srow['c_count'] ?? 0);
    $completedAmount = (float)($srow['c_amount'] ?? 0);
    $thisMonthAmount = (float)($srow['m_amount'] ?? 0);
    $avgAmount       = $completedCount > 0 ? $completedAmount / $completedCount : 0.0;
} catch (\Throwable $e) {}

$totalPages = $totalPages ?? 1;

// Helper to build a stat-card link that swaps the status filter and tags
// which specific card was clicked (view), preserving other active filters
// (method, q), and resetting pagination.
function sh_card_href(string $status, string $view): string {
    $qs = $_GET;
    $qs['status'] = $status;
    $qs['view']   = $view;
    unset($qs['page']);
    return '?' . http_build_query($qs);
}
// Three cards (Amount Settled / This Month / Average) all map to the same
// status=completed filter, so status alone can't tell them apart. `view`
// disambiguates which single card should show as active.
$view = trim($_GET['view'] ?? '');
if ($view === '' || $statusFilter !== ($view === 'total' ? 'all' : 'completed')) {
    $view = $statusFilter === 'all' ? 'total' : ($statusFilter === 'completed' ? 'amount' : '');
}

// CSV export of the full filtered result (not just this page).
if (isset($_GET['export']) && $_GET['export'] === 'csv' && function_exists('hasPermission') && hasPermission('finance.export')) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="settlement_history_' . date('Ymd_His') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['Settlement ID','Seller','Net Amount','Method','Status','Requested Date','Paid Date','Notes']);
    try {
        $allStmt = $conn->prepare("SELECT p.id, p.seller_id, p.amount, p.method, p.status, p.requested_at, p.completed_at, p.notes, u.full_name
                                    $joinSql $whereSql ORDER BY COALESCE(p.completed_at, p.requested_at) DESC");
        if ($types !== '') { $allStmt->bind_param($types, ...$params); }
        $allStmt->execute();
        $allRes = $allStmt->get_result();
        while ($r = $allRes->fetch_assoc()) {
            fputcsv($out, [
                'STL-' . $r['id'],
                $r['full_name'] ?: ('Seller #' . $r['seller_id']),
                $r['amount'],
                strtoupper($r['method']),
                ucfirst($r['status']),
                $r['requested_at'],
                $r['completed_at'] ?: '',
                $r['notes'] ?: '',
            ]);
        }
    } catch (\Throwable $e) {}
    fclose($out);
    exit;
}

$pageTitle     = 'Settlement History';
$pageSubtitle  = 'Full history of completed and rejected seller settlements.';
$activeTeamTab = 'settlement_history';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.sh-filters{display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;margin-bottom:18px;background:#fff;border:1px solid var(--border);border-radius:12px;padding:14px 16px}
.sh-filters-fields{display:flex;flex-wrap:wrap;gap:10px;flex:1;min-width:0}
.sh-field{position:relative;flex:1;min-width:160px;display:flex;align-items:center}
.sh-field-search{flex:1.6;min-width:200px}
.sh-field i{position:absolute;left:12px;font-size:12px;color:#9aa0a6;pointer-events:none}
.sh-filters select,.sh-filters input[type=text]{width:100%;padding:9px 12px 9px 32px;border:1px solid var(--border);border-radius:8px;font-size:13px;background:#fff;color:inherit;transition:border-color .15s ease,box-shadow .15s ease}
.sh-filters select:hover,.sh-filters input[type=text]:hover{border-color:#c3c8ce}
.sh-filters select:focus,.sh-filters input[type=text]:focus{outline:none;border-color:#5A9802;box-shadow:0 0 0 3px rgba(90,152,2,.14)}
.sh-filters-actions{display:flex;gap:8px;flex-shrink:0}
@media (max-width:768px){
  .sh-filters{flex-direction:column;align-items:stretch;padding:12px}
  .sh-filters-fields{flex-direction:column}
  .sh-field{min-width:0}
  .sh-filters-actions{width:100%}
  .sh-filters-actions .btn{flex:1;justify-content:center}
}
.sh-table-card{background:#fff;border-radius:14px;padding:22px 24px 16px;box-shadow:0 1px 3px rgba(0,0,0,.05);border:1px solid var(--border)}
.sh-table-card-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px}
.sh-table-card-head h3{margin:0;font-size:16px;font-weight:700}
.sh-table-card-head .sh-count{color:#8a8f98;font-weight:600}
.sh-table-card .agri-table-wrap table thead th{background:#F5F6F4;font-size:11px;letter-spacing:.4px;text-transform:uppercase;color:#6b7076;font-weight:700;padding:12px 14px;border-bottom:none}
.sh-table-card .agri-table-wrap table thead tr th:first-child{border-top-left-radius:10px;border-bottom-left-radius:10px}
.sh-table-card .agri-table-wrap table thead tr th:last-child{border-top-right-radius:10px;border-bottom-right-radius:10px}
.sh-table-card .agri-table-wrap table tbody td{padding:14px;font-size:13.5px;border-bottom:1px solid var(--border)}
.sh-table-card .agri-table-wrap table tbody tr:last-child td{border-bottom:none}
.sh-table-card .agri-table-wrap table tbody tr:hover td{background:#FAFBF9}
.sh-table-card .sh-id{color:#6b7076;font-weight:600}
.sh-table-card .sh-seller{font-weight:600}
.sh-table-card .sh-muted{color:#6b7076}
.badge-status{display:inline-flex;align-items:center;padding:4px 12px;border-radius:999px;font-size:12px;font-weight:700}
.st-completed{background:#E6F4EA;color:#1E7E34}
.st-rejected{background:#FDECEA;color:#C62828}
.stat-card{transition:transform .15s ease,box-shadow .15s ease,border-color .15s ease}
a.stat-card-link{display:block;text-decoration:none;color:inherit;border-radius:12px}
a.stat-card-link:hover .stat-card{transform:translateY(-2px);box-shadow:0 6px 16px rgba(0,0,0,.08)}
a.stat-card-link .stat-card{cursor:pointer}
a.stat-card-link.is-active .stat-card{border:1.5px solid var(--border);box-shadow:0 0 0 3px rgba(46,125,50,.12)}
@media (max-width:768px){ .agri-table-wrap{overflow-x:auto} .agri-table-wrap table{min-width:820px} }
</style>

<div class="stats-row">
    <a class="stat-card-link <?php echo $view === 'total' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(sh_card_href('all', 'total')); ?>" title="View all settlements">
        <div class="stat-card"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-clock-rotate-left"></i></div><div><div class="val"><?php echo number_format($completedCount); ?></div><div class="lbl">Total Settlements</div></div></div>
    </a>
    <a class="stat-card-link <?php echo $view === 'amount' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(sh_card_href('completed', 'amount')); ?>" title="View completed settlements">
        <div class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="val"><?php echo sh_money($completedAmount); ?></div><div class="lbl">Total Amount Settled</div></div></div>
    </a>
    <a class="stat-card-link <?php echo $view === 'month' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(sh_card_href('completed', 'month')); ?>" title="View completed settlements">
        <div class="stat-card"><div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-calendar-check"></i></div><div><div class="val"><?php echo sh_money($thisMonthAmount); ?></div><div class="lbl">Settled This Month</div></div></div>
    </a>
    <a class="stat-card-link <?php echo $view === 'avg' ? 'is-active' : ''; ?>" href="<?php echo htmlspecialchars(sh_card_href('completed', 'avg')); ?>" title="View completed settlements">
        <div class="stat-card"><div class="icn" style="background:#6A1B9A"><i class="fa-solid fa-scale-balanced"></i></div><div><div class="val"><?php echo sh_money($avgAmount); ?></div><div class="lbl">Average Settlement</div></div></div>
    </a>
</div>

<form method="get" class="sh-filters">
    <div class="sh-filters-fields">
        <div class="sh-field">
            <i class="fa-solid fa-circle-check"></i>
            <select name="status" onchange="this.form.submit()">
                <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All Settled</option>
                <option value="completed" <?php echo $statusFilter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="rejected" <?php echo $statusFilter === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
            </select>
        </div>
        <div class="sh-field">
            <i class="fa-solid fa-wallet"></i>
            <select name="method" onchange="this.form.submit()">
                <option value="">All Methods</option>
                <option value="bank" <?php echo $method === 'bank' ? 'selected' : ''; ?>>Bank Transfer</option>
                <option value="upi" <?php echo $method === 'upi' ? 'selected' : ''; ?>>UPI</option>
            </select>
        </div>
        <div class="sh-field sh-field-search">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="text" name="q" placeholder="Search seller…" value="<?php echo htmlspecialchars($search); ?>">
        </div>
    </div>
    <div class="sh-filters-actions">
        <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
        <?php if (function_exists('hasPermission') && hasPermission('finance.export')): $exportQs = $_GET; $exportQs['export'] = 'csv'; ?>
        <a class="btn outline" href="?<?php echo htmlspecialchars(http_build_query($exportQs)); ?>"><i class="fa-solid fa-download"></i> Export CSV</a>
        <?php endif; ?>
    </div>
</form>

<div class="sh-table-card">
    <div class="sh-table-card-head">
        <h3>Settlements <span class="sh-count">(<?php echo number_format($total); ?>)</span></h3>
    </div>
    <div class="agri-table-wrap">
    <table>
        <thead><tr><th>Settlement ID</th><th>Seller</th><th>Net Amount</th><th>Method</th><th>Status</th><th>Requested Date</th><th>Paid Date</th><th>Notes</th></tr></thead>
        <tbody>
        <?php if (empty($settlements)): ?>
            <tr><td colspan="8" class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No settlements found for this filter.</td></tr>
        <?php else: foreach ($settlements as $s): ?>
            <tr>
                <td class="sh-id">STL-<?php echo (int)$s['id']; ?></td>
                <td class="sh-seller"><?php echo htmlspecialchars($s['full_name'] ?: ('Seller #' . $s['seller_id'])); ?></td>
                <td><?php echo sh_money($s['amount']); ?></td>
                <td><?php echo htmlspecialchars(strtoupper($s['method'])); ?></td>
                <td><span class="badge-status st-<?php echo htmlspecialchars($s['status']); ?>"><?php echo ucfirst($s['status']); ?></span></td>
                <td class="sh-muted"><?php echo htmlspecialchars(date('d M Y, h:i A', strtotime($s['requested_at']))); ?></td>
                <td class="sh-muted"><?php echo $s['completed_at'] ? htmlspecialchars(date('d M Y, h:i A', strtotime($s['completed_at']))) : '—'; ?></td>
                <td class="sh-muted"><?php echo $s['notes'] ? htmlspecialchars($s['notes']) : '—'; ?></td>
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
