<?php
// =====================================================================
// admin/login_history.php — Browse admin_login_logs (successful,
// failed, locked, expired, inactive login attempts) with pagination.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('activity_logs.view');

$statusFilter = trim($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = 25;
$offset  = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];
if ($statusFilter !== '' && in_array($statusFilter, ['success','failed','locked','expired','inactive'], true)) {
    $where[] = "l.login_status = ?"; $params[] = $statusFilter; $types .= 's';
}
$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM admin_login_logs l $whereSql");
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$listStmt = $conn->prepare(
    "SELECT l.*, u.full_name AS admin_full_name, u.username
       FROM admin_login_logs l
       LEFT JOIN users u ON u.id = l.admin_user_id
       $whereSql
       ORDER BY l.id DESC
       LIMIT ? OFFSET ?"
);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($allTypes, ...$allParams);
$listStmt->execute();
$logs = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$statusTagMap = ['success' => 'active', 'failed' => 'rejected', 'locked' => 'suspended', 'expired' => 'expired', 'inactive' => 'inactive'];

$pageTitle = 'Login History';
$pageSubtitle = 'Track every admin login attempt across the Admin Panel.';
$activeTeamTab = 'logins';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="card">
    <div class="card-head"><h2>Login History <span style="color:var(--muted);font-weight:400">(<?php echo $total; ?>)</span></h2></div>

    <form method="get" class="filters">
        <select name="status">
            <option value="">All Statuses</option>
            <?php foreach (['success','failed','locked','expired','inactive'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $statusFilter===$s?'selected':''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($statusFilter): ?><a href="login_history.php" class="btn outline sm">Clear</a><?php endif; ?>
    </form>

    <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fa-solid fa-right-to-bracket"></i>No login attempts recorded yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead><tr><th>Login Time</th><th>Admin</th><th>Status</th><th>IP Address</th><th>Device / Browser</th><th>Logout Time</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td style="white-space:nowrap"><?php echo $l['login_time'] ? date('d M Y, h:i A', strtotime($l['login_time'])) : date('d M Y, h:i A', strtotime($l['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($l['admin_full_name'] ?: ($l['username'] ?? 'Unknown')); ?></td>
                <td><span class="tag <?php echo $statusTagMap[$l['login_status']] ?? ''; ?>"><?php echo ucfirst($l['login_status']); ?></span></td>
                <td><?php echo htmlspecialchars($l['ip_address'] ?: '—'); ?></td>
                <td style="max-width:280px;font-size:12px;color:var(--muted)"><?php echo htmlspecialchars($l['user_agent'] ?: '—'); ?></td>
                <td><?php echo $l['logout_time'] ? date('d M Y, h:i A', strtotime($l['logout_time'])) : '<span style="color:var(--muted)">—</span>'; ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="pagination">
        <?php $qs = $_GET; unset($qs['page']); $baseQs = http_build_query($qs); for ($p = 1; $p <= $totalPages; $p++): $sep = $baseQs ? '&' : ''; ?>
            <a href="?<?php echo $baseQs.$sep; ?>page=<?php echo $p; ?>" class="<?php echo $p===$page?'active':''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
