<?php
// =====================================================================
// admin/includes/invoices_seller_tab.php — "Seller Invoices" tab body
// on the new Invoices hub (admin/invoices.php?type=seller).
//
// This is the SAME query/columns/logic as the original admin/
// seller_invoices.php (which still exists and still works standalone
// for any old bookmarks/links) — just re-homed as a tab so nothing
// about how seller invoices are generated, calculated, or displayed
// has changed. Requires $conn (from admin_guard.php, already included
// by invoices.php before this file is pulled in).
// =====================================================================

$search = trim($_GET['q'] ?? '');
$settlementFilter = trim($_GET['settlement'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');
$allowedSettlement = ['', 'pending', 'available', 'paid', 'refunded', 'partially_refunded'];
if (!in_array($settlementFilter, $allowedSettlement, true)) { $settlementFilter = ''; }

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $where[] = "(si.invoice_number LIKE ? OR si.order_id = ? OR u.full_name LIKE ? OR u.mobile LIKE ? OR spp.business_name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, (int)$search, $like, $like, $like);
    $types .= 'sisss';
}
if ($dateFrom !== '') { $where[] = "DATE(si.generated_at) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '') { $where[] = "DATE(si.generated_at) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$joinSql = "FROM seller_invoices si
            LEFT JOIN users u ON u.id = si.seller_id
            LEFT JOIN seller_payout_profiles spp ON spp.user_id = si.seller_id";

$rows = []; $allRows = []; $summaryGross = 0.0; $summaryCharges = 0.0; $summaryNet = 0.0; $total = 0; $totalPages = 1;
try {
    $baseSql = "SELECT si.id, si.invoice_number, si.seller_id, si.order_id, si.gross_amount,
                       si.platform_charge_percent, si.platform_charge_amount, si.net_amount, si.generated_at,
                       u.full_name AS seller_name, u.mobile AS seller_mobile, spp.business_name
                $joinSql
                $whereSql
                ORDER BY si.generated_at DESC";
    $stmt = $conn->prepare($baseSql);
    if ($stmt) {
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $allRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (\Throwable $e) { $allRows = []; }

$filteredRows = [];
foreach ($allRows as $r) {
    $r['settlement_status'] = function_exists('agri_seller_invoice_settlement_status')
        ? agri_seller_invoice_settlement_status($conn, (int)$r['seller_id'], (int)$r['order_id'])
        : 'pending';
    if ($settlementFilter !== '' && $r['settlement_status'] !== $settlementFilter) continue;
    $summaryGross += (float)$r['gross_amount'];
    $summaryCharges += (float)$r['platform_charge_amount'];
    $summaryNet += (float)$r['net_amount'];
    $filteredRows[] = $r;
}
$total = count($filteredRows);
$totalPages = max(1, (int)ceil($total / $perPage));
$rows = array_slice($filteredRows, $offset, $perPage);

$settlementLabels = ['pending' => 'Pending', 'available' => 'Processing', 'paid' => 'Settled', 'refunded' => 'Refunded', 'partially_refunded' => 'Partially Refunded'];
?>
<div style="padding:8px 20px 0;font-size:13px;color:var(--muted);">
    <i class="fa-solid fa-circle-info"></i> One row per seller per order. Figures come straight from each seller's invoice — the exact same numbers they see on their own dashboard.
</div>

<div class="form-grid" style="padding:16px 20px 4px;grid-template-columns:repeat(3,1fr)">
    <div class="card" style="margin:0;padding:14px">
        <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase">Gross Product Value (filtered)</div>
        <div style="font-size:20px;font-weight:700">₹<?php echo number_format($summaryGross, 2); ?></div>
    </div>
    <div class="card" style="margin:0;padding:14px">
        <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase">Platform Charges Collected</div>
        <div style="font-size:20px;font-weight:700">₹<?php echo number_format($summaryCharges, 2); ?></div>
    </div>
    <div class="card" style="margin:0;padding:14px">
        <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase">Seller Net Earnings</div>
        <div style="font-size:20px;font-weight:700">₹<?php echo number_format($summaryNet, 2); ?></div>
    </div>
</div>

<form method="get" class="filters" style="padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <input type="hidden" name="type" value="seller">
    <input type="text" name="q" placeholder="Search invoice no. / order id / seller / business" value="<?php echo htmlspecialchars($search); ?>" style="min-width:260px">
    <select name="settlement">
        <option value="">All Settlement Statuses</option>
        <?php foreach ($settlementLabels as $val => $label): ?>
            <option value="<?php echo $val; ?>" <?php echo $settlementFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
        <?php endforeach; ?>
    </select>
    <label style="font-size:12px;color:var(--muted);">From <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>"></label>
    <label style="font-size:12px;color:var(--muted);">To <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>"></label>
    <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
</form>

<div style="overflow-x:auto;">
    <table class="data-table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid #eee;">
                <th style="padding:10px 20px;">Invoice No.</th>
                <th style="padding:10px;">Seller</th>
                <th style="padding:10px;">Order ID</th>
                <th style="padding:10px;">Product Value</th>
                <th style="padding:10px;">Platform Charges</th>
                <th style="padding:10px;">Seller Net</th>
                <th style="padding:10px;">Generated</th>
                <th style="padding:10px;">Settlement</th>
                <th style="padding:10px 20px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="9" style="padding:24px 20px;color:var(--muted);">No seller invoices match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr style="border-bottom:1px solid #f2f2f2;">
                    <td style="padding:10px 20px;font-weight:600;"><?php echo htmlspecialchars($r['invoice_number']); ?></td>
                    <td style="padding:10px;">
                        <strong><?php echo htmlspecialchars($r['business_name'] ?: ($r['seller_name'] ?? ('Seller #' . $r['seller_id']))); ?></strong><br>
                        <span style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($r['seller_mobile'] ?? ''); ?> · Seller #<?php echo (int)$r['seller_id']; ?></span>
                    </td>
                    <td style="padding:10px;">#<?php echo (int)$r['order_id']; ?></td>
                    <td style="padding:10px;">₹<?php echo number_format((float)$r['gross_amount'], 2); ?></td>
                    <td style="padding:10px;">₹<?php echo number_format((float)$r['platform_charge_amount'], 2); ?> (<?php echo rtrim(rtrim(number_format((float)$r['platform_charge_percent'], 2), '0'), '.'); ?>%)</td>
                    <td style="padding:10px;font-weight:700;">₹<?php echo number_format((float)$r['net_amount'], 2); ?></td>
                    <td style="padding:10px;font-size:12px;"><?php echo htmlspecialchars($r['generated_at']); ?></td>
                    <td style="padding:10px;">
                        <?php
                            $tagClass = $r['settlement_status'] === 'paid' ? 'active' : (in_array($r['settlement_status'], ['refunded', 'partially_refunded'], true) ? 'rejected' : 'pending');
                        ?>
                        <span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($settlementLabels[$r['settlement_status']] ?? ucfirst($r['settlement_status'])); ?></span>
                    </td>
                    <td style="padding:10px 20px;white-space:nowrap;">
                        <div class="action-menu-wrap">
                            <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                            <div class="action-menu">
                                <a target="_blank" rel="noopener" href="../seller/invoice.php?order_id=<?php echo (int)$r['order_id']; ?>&seller_id=<?php echo (int)$r['seller_id']; ?>"><i class="fa-solid fa-eye"></i> View / Print / Download</a>
                                <a href="index.php?tab=orders&order_id=<?php echo (int)$r['order_id']; ?>"><i class="fa-solid fa-truck-fast"></i> Order</a>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="padding:14px 20px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?type=seller&q=<?php echo urlencode($search); ?>&settlement=<?php echo urlencode($settlementFilter); ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>&page=<?php echo $p; ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
