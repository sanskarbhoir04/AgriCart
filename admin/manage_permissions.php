<?php
// =====================================================================
// admin/manage_permissions.php — Give a Super Admin the ability to
// override one specific team member's permissions without changing
// their role. Three states per permission: Inherit from role (default),
// Allow (force-grant even if role doesn't), Deny (force-block even if
// role does) — Deny always wins.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.change_permissions');

$memberId = (int)($_GET['member_id'] ?? 0);

if ($memberId <= 0) {
    // No specific member chosen yet — show a picker.
    $members = $conn->query(
        "SELECT m.id, u.full_name, u.email, r.role_name
           FROM admin_team_members m
           JOIN users u ON u.id = m.user_id
           JOIN admin_roles r ON r.id = m.role_id
          WHERE m.status <> 'inactive'
          ORDER BY u.full_name"
    )->fetch_all(MYSQLI_ASSOC);

    $pageTitle = 'Permissions';
    $pageSubtitle = 'View and override individual admin permissions beyond their role.';
    $activeTeamTab = 'permissions';
    include __DIR__ . '/includes/team_layout_top.php';
    ?>
    <div class="card">
        <div class="card-head"><h2>Custom Permission Overrides</h2></div>
        <p style="color:var(--muted);font-size:13px;margin-bottom:16px">Select a team member to view or change permission overrides specific to them, on top of their assigned role.</p>
        <table>
            <thead><tr><th>Name</th><th>Email</th><th>Role</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($members as $m): ?>
                <tr>
                    <td><?php echo htmlspecialchars($m['full_name']); ?></td>
                    <td><?php echo htmlspecialchars($m['email']); ?></td>
                    <td><span class="role-badge"><?php echo htmlspecialchars($m['role_name']); ?></span></td>
                    <td><a href="manage_permissions.php?member_id=<?php echo $m['id']; ?>" class="btn sm outline">Manage <i class="fa-solid fa-arrow-right"></i></a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
    include __DIR__ . '/includes/team_layout_bottom.php';
    exit;
}

$stmt = $conn->prepare("SELECT m.*, u.full_name, u.email, r.role_name, r.role_slug FROM admin_team_members m JOIN users u ON u.id = m.user_id JOIN admin_roles r ON r.id = m.role_id WHERE m.id = ?");
$stmt->bind_param('i', $memberId);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) { header('Location: manage_permissions.php'); exit; }

if ($member['role_slug'] === 'super_admin' && !isSuperAdmin()) {
    header('Location: access_denied.php?perm=team.change_permissions');
    exit;
}

// Role's base permissions.
$roleStmt = $conn->prepare("SELECT permission_id FROM admin_role_permissions WHERE role_id = ? AND allowed = 1");
$roleStmt->bind_param('i', $member['role_id']);
$roleStmt->execute();
$rolePerms = array_map('intval', array_column($roleStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'permission_id'));

// Existing overrides.
$ovStmt = $conn->prepare("SELECT permission_id, permission_type FROM admin_user_permissions WHERE admin_member_id = ?");
$ovStmt->bind_param('i', $memberId);
$ovStmt->execute();
$overrides = [];
foreach ($ovStmt->get_result()->fetch_all(MYSQLI_ASSOC) as $row) {
    $overrides[(int)$row['permission_id']] = $row['permission_type'];
}

$permissions = $conn->query("SELECT * FROM admin_permissions ORDER BY module_name, action_name")->fetch_all(MYSQLI_ASSOC);
$grouped = [];
foreach ($permissions as $p) { $grouped[$p['module_name']][] = $p; }

$pageTitle = 'Manage Permissions — ' . $member['full_name'];
$activeTeamTab = 'permissions';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="card" style="max-width:860px">
    <div class="card-head">
        <h2><?php echo htmlspecialchars($member['full_name']); ?></h2>
        <span class="role-badge"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($member['role_name']); ?></span>
    </div>
    <p style="color:var(--muted);font-size:13px;margin-bottom:18px">
        For each permission, choose <strong>Inherit</strong> to use the role's default, <strong>Allow</strong> to grant it to this person specifically, or <strong>Deny</strong> to block it for this person specifically — even if their role would normally allow it. <strong>Deny always wins.</strong>
    </p>

    <form id="permForm" onsubmit="return submitOverrides(event)">
        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
        <?php if ($member['role_slug'] === 'super_admin'): ?>
            <div class="hint" style="margin-bottom:14px">The Super Admin role already has every permission — overrides aren't needed here.</div>
        <?php else: ?>
        <?php foreach ($grouped as $module => $perms): ?>
        <div class="perm-group">
            <h4><?php echo ucwords(str_replace('_',' ',$module)); ?></h4>
            <table style="width:100%">
                <?php foreach ($perms as $p):
                    $pid = (int)$p['id'];
                    $roleHas = in_array($pid, $rolePerms, true);
                    $current = $overrides[$pid] ?? 'inherit';
                ?>
                <tr>
                    <td style="border:none;padding:6px 0;font-size:12.5px"><?php echo ucwords(str_replace('_',' ',$p['action_name'])); ?> <span style="color:var(--muted)">(role default: <?php echo $roleHas ? 'allow' : 'deny'; ?>)</span></td>
                    <td style="border:none;padding:6px 0;text-align:right">
                        <select name="overrides[<?php echo $pid; ?>]" style="width:auto">
                            <option value="inherit" <?php echo $current==='inherit'?'selected':''; ?>>Inherit</option>
                            <option value="allow" <?php echo $current==='allow'?'selected':''; ?>>Allow</option>
                            <option value="deny" <?php echo $current==='deny'?'selected':''; ?>>Deny</option>
                        </select>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <div id="formErr" class="err" style="display:none;margin:10px 0"></div>
        <div class="modal-actions" style="justify-content:flex-start;margin-top:16px">
            <?php if ($member['role_slug'] !== 'super_admin'): ?>
            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Save Overrides</button>
            <?php endif; ?>
            <a href="manage_permissions.php" class="btn outline">Back</a>
        </div>
    </form>
</div>

<script>
async function submitOverrides(e){
    e.preventDefault();
    const form = document.getElementById('permForm');
    const fd = new FormData(form);
    fd.append('action', 'save_overrides');
    const errBox = document.getElementById('formErr');
    errBox.style.display = 'none';
    const btn = form.querySelector('button[type=submit]');
    if (btn) btn.disabled = true;
    try {
        const res = await fetch('actions/permission_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) { showToast('Permission overrides saved.'); }
        else { errBox.textContent = data.error || 'Could not save overrides.'; errBox.style.display = 'block'; }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
    }
    if (btn) btn.disabled = false;
    return false;
}
</script>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
