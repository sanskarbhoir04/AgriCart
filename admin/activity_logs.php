<?php
// =====================================================================
// admin/activity_logs.php — Browse the admin_activity_logs audit trail.
// Filterable by admin, module, action/description text, and date range.
// Supports an optional ?user_id= to jump straight to one team member's
// history (used by the "View Activity" link on team_members.php).
// Shows the actual old -> new value for each change, not just a
// one-line description, so a super admin can see exactly what was
// added, removed, or updated.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('activity_logs.view');

if (!function_exists('formatLogValue')) {
    function formatLogValue($v)
    {
        if ($v === null || $v === '') return null;
        $decoded = json_decode($v, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            $parts = [];
            foreach ($decoded as $k => $val) {
                if (is_array($val)) $val = implode(', ', $val);
                if (is_bool($val)) $val = $val ? 'Yes' : 'No';
                $parts[] = (is_string($k) ? (ucwords(str_replace('_', ' ', $k)) . ': ') : '') . $val;
            }
            return implode(' · ', $parts);
        }
        return (string)$v;
    }
}

$userFilter   = (int)($_GET['user_id'] ?? 0);
$moduleFilter = trim($_GET['module'] ?? '');
$search       = trim($_GET['q'] ?? '');
$dateFrom     = trim($_GET['from'] ?? '');
$dateTo       = trim($_GET['to'] ?? '');
$page         = max(1, (int)($_GET['page'] ?? 1));
$perPage      = 25;
$offset       = ($page - 1) * $perPage;

$where = [];
$types = '';
$params = [];

if ($userFilter > 0) { $where[] = "l.admin_user_id = ?"; $params[] = $userFilter; $types .= 'i'; }
if ($moduleFilter !== '') { $where[] = "l.module = ?"; $params[] = $moduleFilter; $types .= 's'; }
if ($search !== '') { $where[] = "(l.description LIKE ? OR l.action LIKE ?)"; $like = '%'.$search.'%'; array_push($params, $like, $like); $types .= 'ss'; }
if ($dateFrom !== '') { $where[] = "l.created_at >= ?"; $params[] = $dateFrom . ' 00:00:00'; $types .= 's'; }
if ($dateTo !== '') { $where[] = "l.created_at <= ?"; $params[] = $dateTo . ' 23:59:59'; $types .= 's'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countStmt = $conn->prepare("SELECT COUNT(*) AS c FROM admin_activity_logs l $whereSql");
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$listStmt = $conn->prepare(
    "SELECT l.*, u.full_name AS admin_full_name
       FROM admin_activity_logs l
       LEFT JOIN users u ON u.id = l.admin_user_id
       $whereSql
       ORDER BY l.created_at DESC
       LIMIT ? OFFSET ?"
);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($allTypes, ...$allParams);
$listStmt->execute();
$logs = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$modules = $conn->query("SELECT DISTINCT module FROM admin_activity_logs WHERE module IS NOT NULL AND module <> '' ORDER BY module")->fetch_all(MYSQLI_ASSOC);

// Every admin who has at least one logged action — this is the "which
// admin" dropdown. Pulled from the logs themselves (not just current
// team members) so history for a removed admin is still filterable.
$adminOptions = $conn->query(
    "SELECT DISTINCT l.admin_user_id AS id, u.full_name
       FROM admin_activity_logs l
       LEFT JOIN users u ON u.id = l.admin_user_id
      WHERE l.admin_user_id IS NOT NULL
      ORDER BY u.full_name"
)->fetch_all(MYSQLI_ASSOC);

$selectedAdminName = null;
if ($userFilter > 0) {
    foreach ($adminOptions as $a) {
        if ((int)$a['id'] === $userFilter) { $selectedAdminName = $a['full_name']; break; }
    }
}

$pageTitle = 'Activity Logs';
$pageSubtitle = 'Monitor and review important actions performed across the Admin Panel.';
$activeTeamTab = 'activity';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.change-old{color:var(--muted);text-decoration:line-through;font-size:11.5px;word-break:break-word}
.change-new{font-size:12px;font-weight:600;word-break:break-word}
.change-new i{color:var(--muted);font-size:10px;margin-right:4px}
</style>
<div class="card">
    <div class="card-head">
        <h2>Admin Activity Logs <span style="color:var(--muted);font-weight:400">(<?php echo $total; ?>)</span></h2>
    </div>

    <?php if ($selectedAdminName): ?>
    <div style="background:var(--bg-soft);border-radius:10px;padding:10px 14px;margin-bottom:14px;font-size:13px;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <span>Showing everything <strong><?php echo htmlspecialchars($selectedAdminName); ?></strong> has added, updated, or removed.</span>
        <a href="activity_logs.php" class="btn outline sm">Show All Admins</a>
    </div>
    <?php endif; ?>

    <form method="get" class="filters">
        <select name="user_id">
            <option value="">All Admins</option>
            <?php foreach ($adminOptions as $a): ?>
                <option value="<?php echo (int)$a['id']; ?>" <?php echo $userFilter===(int)$a['id']?'selected':''; ?>><?php echo htmlspecialchars($a['full_name'] ?: 'Unknown'); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="module">
            <option value="">All Modules</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?php echo htmlspecialchars($m['module']); ?>" <?php echo $moduleFilter===$m['module']?'selected':''; ?>><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$m['module']))); ?></option>
            <?php endforeach; ?>
        </select>
        <input type="text" name="q" placeholder="Search action or description..." value="<?php echo htmlspecialchars($search); ?>" style="min-width:220px">
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted)">From
            <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>" style="width:auto">
        </label>
        <label style="display:flex;align-items:center;gap:6px;font-size:12px;color:var(--muted)">To
            <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>" style="width:auto">
        </label>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-magnifying-glass"></i> Apply Filters</button>
        <?php if ($search || $moduleFilter || $userFilter || $dateFrom || $dateTo): ?><a href="activity_logs.php" class="btn outline sm">Clear</a><?php endif; ?>
    </form>

    <?php if (empty($logs)): ?>
        <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No activity recorded yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead><tr><th>When</th><th>Admin</th><th>Action</th><th>Module</th><th>What Changed</th><th>Description</th><th>IP</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <?php $oldF = formatLogValue($l['old_value']); $newF = formatLogValue($l['new_value']); ?>
            <tr>
                <td style="white-space:nowrap"><?php echo date('d M Y, h:i A', strtotime($l['created_at'])); ?></td>
                <td><?php echo htmlspecialchars($l['admin_full_name'] ?: 'System'); ?></td>
                <td><span class="tag pending"><?php echo htmlspecialchars(ucwords(str_replace('_',' ',$l['action']))); ?></span></td>
                <td><?php echo htmlspecialchars($l['module'] ? ucwords(str_replace('_',' ',$l['module'])) : '—'); ?></td>
                <td style="max-width:220px">
                    <?php if ($oldF === null && $newF === null): ?>
                        <span style="color:var(--muted)">—</span>
                    <?php else: ?>
                        <?php if ($oldF !== null): ?><div class="change-old"><?php echo htmlspecialchars($oldF); ?></div><?php endif; ?>
                        <?php if ($newF !== null): ?><div class="change-new"><i class="fa-solid fa-arrow-right"></i><?php echo htmlspecialchars($newF); ?></div><?php endif; ?>
                    <?php endif; ?>
                </td>
                <td style="max-width:300px"><?php echo htmlspecialchars($l['description'] ?: '—'); ?></td>
                <td style="color:var(--muted);font-size:12px"><?php echo htmlspecialchars($l['ip_address'] ?: '—'); ?></td>
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
