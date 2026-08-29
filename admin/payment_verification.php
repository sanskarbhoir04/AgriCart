<?php
// =====================================================================
// admin/payment_verification.php — Review queue for rental bookings
// whose payment_status = 'verification_pending' (user submitted a
// transaction ID / screenshot, awaiting admin approval).
//
// Approve -> payment_status = 'paid'
// Reject  -> payment_status = 'failed' (user can resubmit from
//            pages/payment.php)
//
// Demo Payment Verification: AgriCart has no live payment gateway
// wired up (Razorpay is demo-mode only — see pages/rental.php). This
// page is a manual review step standing in for real gateway webhooks.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/payment_verification_schema.php';
payment_verification_bootstrap_schema($conn);
requirePermission('rental_bookings.verify_payment');

$statusFilter = trim($_GET['status'] ?? 'all');
$allowedFilters = ['verification_pending', 'paid', 'failed', 'all'];
if (!in_array($statusFilter, $allowedFilters, true)) { $statusFilter = 'verification_pending'; }

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset  = ($page - 1) * $perPage;

$where = ["eb.transaction_id IS NOT NULL"]; // only rows that ever went through the online-proof flow
$types = '';
$params = [];
if ($statusFilter !== 'all') {
    $where[] = "eb.payment_status = ?";
    $params[] = $statusFilter;
    $types .= 's';
}
$whereSql = 'WHERE ' . implode(' AND ', $where);

$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM equipment_bookings eb $whereSql");
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$listSql = "SELECT eb.id, eb.booking_number, eb.total_amount, eb.payment_status, eb.payment_method,
                   eb.transaction_id, eb.payment_submitted_at, eb.payment_screenshot,
                   eb.admin_verification_note, eb.payment_verified_at,
                   u.full_name AS renter_name, u.mobile AS renter_mobile,
                   e.name AS equipment_name
              FROM equipment_bookings eb
              LEFT JOIN users u ON u.id = eb.user_id
              LEFT JOIN equipment e ON e.id = eb.equipment_id
              $whereSql
              ORDER BY eb.payment_submitted_at DESC
              LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($allTypes, ...$allParams);
$listStmt->execute();
$rows = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Summary counts across every booking that ever went through the online-proof
// flow (not just the current filter) — powers the standard Admin metric cards.
$sumStmt = $conn->query(
    "SELECT
        SUM(payment_status='verification_pending') AS pending_c,
        SUM(payment_status='paid') AS verified_c,
        SUM(payment_status='failed') AS rejected_c,
        SUM(CASE WHEN payment_status='paid' THEN total_amount ELSE 0 END) AS verified_amount,
        COUNT(*) AS total_c
     FROM equipment_bookings WHERE transaction_id IS NOT NULL"
);
$summary = $sumStmt ? $sumStmt->fetch_assoc() : [];

$csrfTok = csrf_token();
$pageTitle = 'Payment Verification';
$pageSubtitle = 'Review transaction proofs submitted by renters and verify or reject payments.';
$activeTeamTab = 'payment_verification';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="stats-row">
    <a class="stat-card" href="?status=all"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-receipt"></i></div><div><div class="val"><?php echo (int)($summary['total_c'] ?? 0); ?></div><div class="lbl">Total Payments</div></div></a>
    <a class="stat-card" href="?status=verification_pending"><div class="icn" style="background:#F9A825"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo (int)($summary['pending_c'] ?? 0); ?></div><div class="lbl">Pending Verification</div></div></a>
    <a class="stat-card" href="?status=paid"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-circle-check"></i></div><div><div class="val"><?php echo (int)($summary['verified_c'] ?? 0); ?></div><div class="lbl">Verified · ₹<?php echo number_format((float)($summary['verified_amount'] ?? 0), 2); ?></div></div></a>
    <a class="stat-card" href="?status=failed"><div class="icn" style="background:#B71C1C"><i class="fa-solid fa-ban"></i></div><div><div class="val"><?php echo (int)($summary['rejected_c'] ?? 0); ?></div><div class="lbl">Rejected</div></div></a>
</div>
<style>
.stat-card{text-decoration:none;color:inherit}
</style>

<div class="card">
    <div class="card-head">
        <h2>Payment Verification <span style="color:var(--muted);font-weight:400">(<?php echo $total; ?>)</span></h2>
    </div>

    <form method="get" class="filters">
        <select name="status">
            <?php foreach (['verification_pending' => 'Awaiting Review', 'paid' => 'Approved (Paid)', 'failed' => 'Rejected', 'all' => 'All'] as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php echo $statusFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
    </form>

    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-file-invoice-dollar"></i>Nothing here right now.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Booking</th>
                    <th>Renter</th>
                    <th>Amount</th>
                    <th>Method</th>
                    <th>Transaction ID</th>
                    <th>Screenshot</th>
                    <th>Submitted</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['booking_number']); ?></strong><br>
                            <span style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($r['equipment_name'] ?? ''); ?></span>
                        </td>
                        <td>
                            <?php echo htmlspecialchars($r['renter_name'] ?? '—'); ?><br>
                            <span style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($r['renter_mobile'] ?? ''); ?></span>
                        </td>
                        <td>₹<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                        <td><?php echo htmlspecialchars($r['payment_method'] ?? '—'); ?></td>
                        <td style="font-family:monospace;"><?php echo htmlspecialchars($r['transaction_id'] ?? '—'); ?></td>
                        <td>
                            <?php if ($r['payment_screenshot']): ?>
                                <a href="../<?php echo htmlspecialchars($r['payment_screenshot']); ?>" target="_blank" rel="noopener">
                                    <img src="../<?php echo htmlspecialchars($r['payment_screenshot']); ?>" alt="Payment screenshot" style="width:48px;height:48px;object-fit:cover;border-radius:6px;border:1px solid var(--border);">
                                </a>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px;">None</span>
                            <?php endif; ?>
                        </td>
                        <td style="font-size:12px;white-space:nowrap;"><?php echo $r['payment_submitted_at'] ? htmlspecialchars($r['payment_submitted_at']) : '—'; ?></td>
                        <td>
                            <?php
                                $tagClass = $r['payment_status'] === 'paid' ? 'active' : ($r['payment_status'] === 'failed' ? 'rejected' : 'pending');
                            ?>
                            <span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars($r['payment_status']); ?></span>
                            <?php if ($r['admin_verification_note']): ?>
                                <div style="font-size:11px;color:var(--muted);margin-top:4px;max-width:180px;"><?php echo htmlspecialchars($r['admin_verification_note']); ?></div>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($r['payment_status'] === 'verification_pending'): ?>
                                <div class="action-menu-wrap">
                                    <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <div class="action-menu">
                                        <button class="menu-success" onclick="reviewPayment(<?php echo (int)$r['id']; ?>, 'approve')"><i class="fa-solid fa-check"></i> Approve</button>
                                        <button class="menu-danger" onclick="reviewPayment(<?php echo (int)$r['id']; ?>, 'reject')"><i class="fa-solid fa-xmark"></i> Reject</button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--muted);font-size:12px;">Reviewed</span>
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
                <a href="?status=<?php echo urlencode($statusFilter); ?>&page=<?php echo $p; ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
            <?php endfor; ?>
        </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode($csrfTok); ?>;

function reviewPayment(bookingId, decision) {
    let note = '';
    if (decision === 'reject') {
        note = prompt('Optional: reason for rejecting this payment (shown to the renter)') || '';
    } else {
        if (!confirm('Approve this payment and mark the booking as Paid?')) return;
    }
    fetch('booking_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            booking_id: bookingId,
            field: 'verify_payment',
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
