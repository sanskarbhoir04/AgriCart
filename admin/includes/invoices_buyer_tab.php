<?php
// =====================================================================
// admin/includes/invoices_buyer_tab.php — "Buyer Invoices" tab body on
// the new Invoices hub (admin/invoices.php?type=buyer).
//
// AgriCart doesn't store a separate "buyer_invoices" table — the buyer
// invoice is generated live, per order, by pages/invoice.php (the
// exact same file this admin panel exposes at admin/invoice.php),
// which already does the full GST/discount/multi-seller breakdown and
// has Print + Download PDF built in via assets/js/invoice.js. So this
// tab is a searchable list of orders — one row = one buyer invoice —
// linking straight into that existing, working invoice page rather
// than re-implementing invoice generation a second time.
//
// Every column is looked up with inv_pick_col() (defined in
// invoices.php, included before this file) before being queried, so
// this degrades gracefully instead of erroring on installs where a
// column is named differently or doesn't exist yet.
// =====================================================================

$search = trim($_GET['q'] ?? '');
$payStatusFilter = trim($_GET['pay_status'] ?? '');
$dateFrom = trim($_GET['from'] ?? '');
$dateTo = trim($_GET['to'] ?? '');

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$buyerCol   = inv_pick_col($conn, 'orders', ['user_id', 'buyer_id', 'customer_id'], 'user_id');
$createdCol = inv_pick_col($conn, 'orders', ['ordered_at', 'created_at', 'order_date', 'placed_at'], 'created_at');
$oStatusCol = inv_pick_col($conn, 'orders', ['order_status', 'status']);
$payStatusCol = inv_pick_col($conn, 'orders', ['payment_status', 'pay_status']);
$totalCol   = inv_pick_col($conn, 'orders', ['final_amount', 'grand_total', 'order_total', 'total_amount']);
$nameCol    = inv_pick_col($conn, 'users', ['full_name', 'name'], 'full_name');
$emailCol   = inv_pick_col($conn, 'users', ['email']);
$mobileCol  = inv_pick_col($conn, 'users', ['mobile', 'phone']);

$where = [];
$types = '';
$params = [];
if ($search !== '') {
    $like = '%' . $search . '%';
    $searchParts = ["o.id = ?"];
    $params[] = (int)$search; $types .= 'i';
    if ($nameCol) { $searchParts[] = "u.$nameCol LIKE ?"; $params[] = $like; $types .= 's'; }
    if ($mobileCol) { $searchParts[] = "u.$mobileCol LIKE ?"; $params[] = $like; $types .= 's'; }
    $where[] = '(' . implode(' OR ', $searchParts) . ')';
}
if ($payStatusFilter !== '' && $payStatusCol) { $where[] = "o.$payStatusCol = ?"; $types .= 's'; $params[] = $payStatusFilter; }
if ($dateFrom !== '' && $createdCol) { $where[] = "DATE(o.$createdCol) >= ?"; $types .= 's'; $params[] = $dateFrom; }
if ($dateTo !== '' && $createdCol) { $where[] = "DATE(o.$createdCol) <= ?"; $types .= 's'; $params[] = $dateTo; }
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$rows = []; $total = 0; $totalPages = 1; $summaryTotal = 0.0;
try {
    $selectTotal = $totalCol ? "o.$totalCol" : "0";
    $selectCreated = $createdCol ? "o.$createdCol" : "NULL";
    $selectStatus = $oStatusCol ? "o.$oStatusCol" : "NULL";
    $selectPayStatus = $payStatusCol ? "o.$payStatusCol" : "NULL";
    $selectEmail = $emailCol ? "u.$emailCol" : "NULL";

    $countSql = "SELECT COUNT(*) cnt FROM orders o LEFT JOIN users u ON u.id = o.$buyerCol $whereSql";
    $cstmt = $conn->prepare($countSql);
    if ($cstmt) {
        if ($types !== '') { $cstmt->bind_param($types, ...$params); }
        $cstmt->execute();
        $total = (int)$cstmt->get_result()->fetch_assoc()['cnt'];
    }
    $totalPages = max(1, (int)ceil($total / $perPage));

    $baseSql = "SELECT o.id AS order_id, $selectTotal AS grand_total, $selectCreated AS created_at,
                       $selectStatus AS order_status, $selectPayStatus AS payment_status,
                       u.$nameCol AS buyer_name, $selectEmail AS buyer_email,
                       (SELECT COUNT(*) FROM order_items oi WHERE oi.order_id = o.id) AS item_count
                FROM orders o LEFT JOIN users u ON u.id = o.$buyerCol
                $whereSql
                ORDER BY o.id DESC
                LIMIT $perPage OFFSET $offset";
    $stmt = $conn->prepare($baseSql);
    if ($stmt) {
        if ($types !== '') { $stmt->bind_param($types, ...$params); }
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    if ($totalCol) {
        $sumSql = "SELECT COALESCE(SUM(o.$totalCol),0) s FROM orders o LEFT JOIN users u ON u.id = o.$buyerCol $whereSql";
        $sstmt = $conn->prepare($sumSql);
        if ($sstmt) {
            if ($types !== '') { $sstmt->bind_param($types, ...$params); }
            $sstmt->execute();
            $summaryTotal = (float)$sstmt->get_result()->fetch_assoc()['s'];
        }
    }
} catch (\Throwable $e) { $rows = []; }
?>
<div style="padding:8px 20px 0;font-size:13px;color:var(--muted);">
    <i class="fa-solid fa-circle-info"></i> One row per order. Opening "View / Print / Download" takes you to the same computer-generated invoice the buyer sees on their own account, with the full GST and item breakdown.
</div>

<div class="form-grid" style="padding:16px 20px 4px;grid-template-columns:repeat(2,1fr)">
    <div class="card" style="margin:0;padding:14px">
        <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase">Order Total (filtered)</div>
        <div style="font-size:20px;font-weight:700">₹<?php echo number_format($summaryTotal, 2); ?></div>
    </div>
    <div class="card" style="margin:0;padding:14px">
        <div style="font-size:11.5px;color:var(--muted);text-transform:uppercase">Orders / Invoices</div>
        <div style="font-size:20px;font-weight:700"><?php echo $total; ?></div>
    </div>
</div>

<form method="get" class="filters" style="padding:14px 20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center;">
    <input type="hidden" name="type" value="buyer">
    <input type="text" name="q" placeholder="Search order id / buyer name / mobile" value="<?php echo htmlspecialchars($search); ?>" style="min-width:260px">
    <?php if ($payStatusCol): ?>
    <select name="pay_status">
        <option value="">All Payment Statuses</option>
        <?php foreach (['pending' => 'Pending', 'paid' => 'Paid', 'failed' => 'Failed', 'refunded' => 'Refunded'] as $val => $label): ?>
            <option value="<?php echo $val; ?>" <?php echo $payStatusFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif; ?>
    <label style="font-size:12px;color:var(--muted);">From <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>"></label>
    <label style="font-size:12px;color:var(--muted);">To <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>"></label>
    <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
</form>

<div style="overflow-x:auto;">
    <table class="data-table" style="width:100%;border-collapse:collapse;">
        <thead>
            <tr style="text-align:left;border-bottom:1px solid #eee;">
                <th style="padding:10px 20px;">Order ID</th>
                <th style="padding:10px;">Buyer</th>
                <th style="padding:10px;">Items</th>
                <th style="padding:10px;">Total</th>
                <th style="padding:10px;">Order Date</th>
                <th style="padding:10px;">Payment</th>
                <th style="padding:10px;">Order Status</th>
                <th style="padding:10px 20px;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$rows): ?>
                <tr><td colspan="8" style="padding:24px 20px;color:var(--muted);">No buyer invoices match these filters.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): ?>
                <tr style="border-bottom:1px solid #f2f2f2;">
                    <td style="padding:10px 20px;font-weight:600;">#<?php echo (int)$r['order_id']; ?></td>
                    <td style="padding:10px;">
                        <strong><?php echo htmlspecialchars($r['buyer_name'] ?: 'Buyer #' . $r['order_id']); ?></strong><br>
                        <span style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($r['buyer_email'] ?? ''); ?></span>
                    </td>
                    <td style="padding:10px;"><?php echo (int)$r['item_count']; ?></td>
                    <td style="padding:10px;font-weight:700;">₹<?php echo number_format((float)$r['grand_total'], 2); ?></td>
                    <td style="padding:10px;font-size:12px;"><?php echo $r['created_at'] ? htmlspecialchars(date('d M Y', strtotime($r['created_at']))) : '—'; ?></td>
                    <td style="padding:10px;">
                        <?php $pay = strtolower((string)($r['payment_status'] ?? '')); ?>
                        <span class="tag <?php echo $pay === 'paid' ? 'active' : ($pay === 'failed' || $pay === 'refunded' ? 'rejected' : 'pending'); ?>"><?php echo htmlspecialchars($r['payment_status'] ?: 'Unknown'); ?></span>
                    </td>
                    <td style="padding:10px;">
                        <?php $st = strtolower((string)($r['order_status'] ?? '')); ?>
                        <span class="tag <?php echo $st === 'delivered' ? 'active' : ($st === 'cancelled' ? 'rejected' : 'pending'); ?>"><?php echo htmlspecialchars($r['order_status'] ?: 'Unknown'); ?></span>
                    </td>
                    <td style="padding:10px 20px;white-space:nowrap;text-align:right;">
                        <?php
                        // Permission-aware, status-aware dropdown — see
                        // includes/action_menu.php. Replaces the old fixed
                        // two-link menu (which showed the same two links to
                        // every admin regardless of what they're allowed to
                        // do) with the shared ⋮ component used panel-wide.
                        render_action_menu('orders', $r, [
                            'id_field' => 'order_id',
                            'label'    => 'order #' . (int)$r['order_id'],
                            // No in-place status-update / refund flow on this
                            // tab yet — hide those two rather than link to
                            // something that isn't built. View Order, View
                            // Invoice, Track, and Print all work today.
                            'hide'     => ['update_status', 'refund'],
                        ]);
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php if ($totalPages > 1): ?>
    <div class="pagination" style="padding:14px 20px;">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="?type=buyer&q=<?php echo urlencode($search); ?>&pay_status=<?php echo urlencode($payStatusFilter); ?>&from=<?php echo urlencode($dateFrom); ?>&to=<?php echo urlencode($dateTo); ?>&page=<?php echo $p; ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
<?php endif; ?>
