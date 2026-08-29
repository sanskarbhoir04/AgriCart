<?php
// =====================================================================
// admin/seller_payouts.php — Review queue + full history of every
// seller withdrawal request (a row per request in the `payouts`
// table). Visible to Super Admin and to any admin holding the
// 'finance.payout' permission (Finance Manager role, by default —
// see setup/admin_rbac.sql).
//
// Approve -> payouts.status = 'completed' (seller's total_paid grows;
//            the amount was already held from available_balance the
//            moment the seller submitted the request).
// Reject  -> payouts.status = 'rejected' (held amount is refunded
//            back to the seller's available_balance).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.payout');

$statusFilter = trim($_GET['status'] ?? 'pending');
$allowedFilters = ['pending', 'processing', 'completed', 'rejected', 'all'];
if (!in_array($statusFilter, $allowedFilters, true)) { $statusFilter = 'pending'; }

$search = trim($_GET['q'] ?? '');

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($statusFilter !== 'all') {
    $where[] = "p.status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
if ($search !== '') {
    $where[] = "(u.full_name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR spp.business_name LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$joinSql = "FROM payouts p
            LEFT JOIN users u ON u.id = p.seller_id
            LEFT JOIN seller_payout_profiles spp ON spp.user_id = p.seller_id";

$countStmt = $conn->prepare("SELECT COUNT(*) AS c $joinSql $whereSql");
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

// Quick summary counts across the whole table (not just the current filter).
$sumStmt = $conn->query("SELECT
    SUM(status='pending') AS pending_c,
    SUM(status='processing') AS processing_c,
    SUM(CASE WHEN status='pending' OR status='processing' THEN amount ELSE 0 END) AS pending_amount,
    SUM(status='completed') AS completed_c,
    SUM(CASE WHEN status='completed' THEN amount ELSE 0 END) AS completed_amount,
    SUM(status='rejected') AS rejected_c
    FROM payouts");
$summary = $sumStmt ? $sumStmt->fetch_assoc() : [];

$listSql = "SELECT p.id, p.seller_id, p.amount, p.method, p.account_details, p.status, p.requested_at,
                   p.completed_at, p.notes,
                   u.full_name AS seller_name, u.mobile AS seller_mobile, u.email AS seller_email,
                   spp.business_name
            $joinSql
            $whereSql
            ORDER BY p.requested_at DESC
            LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($allTypes, ...$allParams);
$listStmt->execute();
$rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$csrfTok = csrf_token();
$pageTitle = 'Seller Payouts';
$pageSubtitle = 'Manage seller payout requests, payment status and payout verification.';
$activeTeamTab = 'seller_payouts';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="stats-row">
    <div class="stat-card"><div class="icn" style="background:#F9A825"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo (int)($summary['pending_c'] ?? 0) + (int)($summary['processing_c'] ?? 0); ?></div><div class="lbl">Awaiting Review · ₹<?php echo number_format((float)($summary['pending_amount'] ?? 0), 2); ?> held</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-circle-check"></i></div><div><div class="val"><?php echo (int)($summary['completed_c'] ?? 0); ?></div><div class="lbl">Completed · ₹<?php echo number_format((float)($summary['completed_amount'] ?? 0), 2); ?> paid</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#B71C1C"><i class="fa-solid fa-ban"></i></div><div><div class="val"><?php echo (int)($summary['rejected_c'] ?? 0); ?></div><div class="lbl">Rejected</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-list"></i></div><div><div class="val"><?php echo $total; ?></div><div class="lbl">Total Requests</div></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>Seller Withdrawal Requests <span style="color:var(--muted);font-weight:400">(<?php echo $total; ?>)</span></h2>
    </div>
    <div style="padding:0 0 16px;font-size:13px;color:var(--muted);">
        <i class="fa-solid fa-circle-info"></i> Every withdrawal a seller requests appears here. Approving marks it paid; rejecting refunds the held amount back to the seller's available balance.
    </div>

    <form method="get" class="filters">
        <select name="status">
            <?php foreach (['pending' => 'Pending', 'processing' => 'Processing', 'completed' => 'Completed', 'rejected' => 'Rejected', 'all' => 'All'] as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php echo $statusFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" placeholder="Search seller name / mobile / email / business" value="<?php echo htmlspecialchars($search); ?>" style="min-width:260px">
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($search !== '' || $statusFilter !== 'pending'): ?>
        <a href="seller_payouts.php" class="btn outline sm">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-money-bill-transfer"></i>Nothing here right now.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Seller</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Account Details</th>
                    <th>Requested On</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['seller_name'] ?? ('Seller #' . $r['seller_id'])); ?></strong><br>
                            <span style="color:var(--muted);font-size:12px;">
                                <?php echo htmlspecialchars($r['seller_mobile'] ?? $r['seller_email'] ?? ''); ?>
                                <?php echo $r['business_name'] ? ' · ' . htmlspecialchars($r['business_name']) : ''; ?>
                            </span>
                        </td>
                        <td style="font-weight:600;">₹<?php echo number_format((float)$r['amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars(strtoupper($r['method'] ?? '—')); ?></td>
                        <td style="font-size:12px;max-width:220px;"><?php echo htmlspecialchars($r['account_details'] ?? '—'); ?></td>
                        <td style="font-size:12px;white-space:nowrap;"><?php echo $r['requested_at'] ? htmlspecialchars($r['requested_at']) : '—'; ?></td>
                        <td>
                            <?php
                                $tagClass = $r['status'] === 'completed' ? 'active' : ($r['status'] === 'rejected' ? 'rejected' : 'pending');
                            ?>
                            <span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars(ucfirst($r['status'])); ?></span>
                            <?php if ($r['notes']): ?>
                                <div style="font-size:11px;color:var(--muted);margin-top:4px;max-width:200px;"><?php echo htmlspecialchars($r['notes']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($r['status'] === 'pending' || $r['status'] === 'processing'): ?>
                                <div class="action-menu-wrap">
                                    <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <div class="action-menu">
                                        <button class="menu-success" onclick="reviewPayout(<?php echo (int)$r['id']; ?>, 'approve')"><i class="fa-solid fa-check"></i> Approve</button>
                                        <button class="menu-danger" onclick="reviewPayout(<?php echo (int)$r['id']; ?>, 'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px;">Reviewed<?php echo $r['completed_at'] ? (' · ' . htmlspecialchars($r['completed_at'])) : ''; ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                <a href="?status=<?php echo urlencode($statusFilter); ?>&q=<?php echo urlencode($search); ?>&page=<?php echo $p; ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode($csrfTok); ?>;

function reviewPayout(payoutId, decision) {
    let note = '';
    if (decision === 'reject') {
        note = prompt('Optional: reason for rejecting this withdrawal (shown to the seller)') || '';
    } else {
        if (!confirm('Approve this withdrawal and mark it as paid?')) return;
    }
    fetch('payout_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            payout_id: payoutId,
            decision: decision,
            note: note,
            csrf_token: CSRF_TOKEN
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert(data.error || 'Action failed.');
        }
    })
    .catch(() => alert('Network error, please try again.'));
}
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
