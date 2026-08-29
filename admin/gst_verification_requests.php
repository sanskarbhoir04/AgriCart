<?php
// =====================================================================
// admin/gst_verification_requests.php — Review queue for GST
// verification requests sellers submit from their own Seller Dashboard
// ("Verify GSTIN" button -> seller/seller_api.php case
// 'gst_verify_request'). Each row carries the seller's own exact
// `seller_user_id`, so approving/rejecting here never needs to guess
// which account it belongs to (unlike the Companies-directory verify
// flow — see includes/gst_sync.php for why that one does).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/gst_verify_requests.php';
gst_verify_requests_bootstrap_schema($conn);
requirePermission('accounts.verify');

$statusFilter = trim($_GET['status'] ?? 'pending');
$allowedFilters = ['pending', 'approved', 'rejected'];
if (!in_array($statusFilter, $allowedFilters, true)) { $statusFilter = 'pending'; }

$rows = gst_verify_requests_list($conn, $statusFilter);
$pendingCount = gst_verify_request_pending_count($conn);

$csrfTok = csrf_token();
$pageTitle = 'GST Verification Requests';
$pageSubtitle = 'Review GST verification requests submitted by sellers from their own dashboard.';
$activeTeamTab = 'gst_verification_requests';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="stats-row">
    <div class="stat-card"><div class="icn" style="background:#F9A825"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo $pendingCount; ?></div><div class="lbl">Pending Review</div></div></div>
</div>

<div class="card">
    <div class="card-head">
        <h2>GST Verification Requests <span style="color:var(--muted);font-weight:400">(<?php echo count($rows); ?>)</span></h2>
    </div>
    <div style="padding:0 0 16px;font-size:13px;color:var(--muted);">
        <i class="fa-solid fa-circle-info"></i> Approving marks this seller's own GST profile as Verified immediately — no name-matching involved, this uses their exact account.
    </div>

    <form method="get" class="filters">
        <select name="status" onchange="this.form.submit()">
            <?php foreach (['pending' => 'Pending', 'approved' => 'Approved', 'rejected' => 'Rejected'] as $val => $label): ?>
                <option value="<?php echo $val; ?>" <?php echo $statusFilter === $val ? 'selected' : ''; ?>><?php echo htmlspecialchars($label); ?></option>
            <?php endforeach; ?>
        </select>
    </form>

    <?php if (!$rows): ?>
        <div class="empty-state"><i class="fa-solid fa-file-invoice"></i>Nothing here right now.</div>
    <?php else: ?>
    <div style="overflow-x:auto;">
        <table>
            <thead>
                <tr>
                    <th>Seller</th>
                    <th>Business Name</th>
                    <th>GSTIN</th>
                    <th>Requested On</th>
                    <?php if ($statusFilter !== 'pending'): ?><th>Reviewed</th><?php endif; ?>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($r['seller_name'] ?? ('Seller #' . $r['seller_user_id'])); ?></strong><br>
                            <span style="color:var(--muted);font-size:12px;"><?php echo htmlspecialchars($r['seller_mobile'] ?? $r['seller_email'] ?? ''); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($r['legal_business_name'] ?: $r['business_name'] ?: '—'); ?></td>
                        <td><?php echo htmlspecialchars($r['gstin'] ?: '—'); ?></td>
                        <td style="font-size:12px;white-space:nowrap;"><?php echo htmlspecialchars($r['requested_at']); ?></td>
                        <?php if ($statusFilter !== 'pending'): ?>
                        <td style="font-size:12px;">
                            <?php echo htmlspecialchars($r['reviewed_by'] ?? '—'); ?><br>
                            <span style="color:var(--muted);"><?php echo htmlspecialchars($r['reviewed_at'] ?? ''); ?></span>
                            <?php if ($r['admin_note']): ?><div style="color:var(--muted);"><?php echo htmlspecialchars($r['admin_note']); ?></div><?php endif; ?>
                        </td>
                        <?php endif; ?>
                        <td style="white-space:nowrap;">
                            <?php if ($statusFilter === 'pending'): ?>
                                <div class="action-menu-wrap">
                                    <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                                    <div class="action-menu">
                                        <button class="menu-success" onclick="reviewGstRequest(<?php echo (int)$r['id']; ?>, 'approved')"><i class="fa-solid fa-check"></i> Approve</button>
                                        <button class="menu-danger" onclick="reviewGstRequest(<?php echo (int)$r['id']; ?>, 'rejected')"><i class="fa-solid fa-xmark"></i> Reject</button>
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
    <?php endif; ?>
</div>

<script>
const CSRF_TOKEN = <?php echo json_encode($csrfTok); ?>;

function reviewGstRequest(requestId, decision) {
    let note = '';
    if (decision === 'rejected') {
        note = prompt('Optional: reason for rejecting this GST verification (shown to the seller)') || '';
    } else {
        if (!confirm('Verify this seller\'s GST details?')) return;
    }
    fetch('gst_verify_request_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            request_id: requestId,
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
