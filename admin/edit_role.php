<?php
// =====================================================================
// admin/edit_role.php — Edit a role's name/description and reassign its
// permissions, grouped module-wise. System roles' permissions can only
// be edited by a Super Admin (their slug/name identity is protected).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.change_permissions');

$roleId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT * FROM admin_roles WHERE id = ?");
$stmt->bind_param('i', $roleId);
$stmt->execute();
$role = $stmt->get_result()->fetch_assoc();
if (!$role) { header('Location: roles.php'); exit; }

if ($role['is_system_role'] && !isSuperAdmin()) {
    header('Location: access_denied.php?perm=team.change_permissions');
    exit;
}

$assignedStmt = $conn->prepare("SELECT permission_id FROM admin_role_permissions WHERE role_id = ? AND allowed = 1");
$assignedStmt->bind_param('i', $roleId);
$assignedStmt->execute();
$assigned = array_column($assignedStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'permission_id');
$assigned = array_map('intval', $assigned);

$permissions = $conn->query("SELECT * FROM admin_permissions ORDER BY module_name, action_name")->fetch_all(MYSQLI_ASSOC);
$grouped = [];
foreach ($permissions as $p) { $grouped[$p['module_name']][] = $p; }

// Members assigned to this role.
$memberStmt = $conn->prepare("SELECT u.full_name, u.email, m.status FROM admin_team_members m JOIN users u ON u.id = m.user_id WHERE m.role_id = ? ORDER BY u.full_name");
$memberStmt->bind_param('i', $roleId);
$memberStmt->execute();
$members = $memberStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Edit Role — ' . $role['role_name'];
$activeTeamTab = 'roles';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="card" style="max-width:820px">
    <div class="card-head">
        <h2>Edit Role: <?php echo htmlspecialchars($role['role_name']); ?></h2>
        <?php echo $role['is_system_role'] ? '<span class="tag pending">System Role</span>' : '<span class="tag active">Custom Role</span>'; ?>
    </div>
    <form id="editRoleForm" onsubmit="return submitEditRole(event)">
        <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>Role Name *</label>
                <input type="text" name="role_name" required value="<?php echo htmlspecialchars($role['role_name']); ?>" <?php echo $role['is_system_role'] ? 'readonly' : ''; ?>>
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" rows="2"><?php echo htmlspecialchars($role['description'] ?: ''); ?></textarea>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin:6px 0 12px">
            <h3 style="font-size:14px">Permissions</h3>
            <div style="display:flex;gap:14px">
                <a onclick="setAll(true)" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">Select All</a>
                <a onclick="setAll(false)" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">Clear All</a>
                <a onclick="setViewOnly()" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">View Only</a>
                <a onclick="setAll(true)" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">Full Access</a>
            </div>
        </div>

        <?php foreach ($grouped as $module => $perms): ?>
        <div class="perm-group">
            <h4>
                <?php echo ucwords(str_replace('_',' ',$module)); ?>
                <span class="mini-actions">
                    <a onclick="setGroup('<?php echo $module; ?>', true)">All</a>
                    <a onclick="setGroup('<?php echo $module; ?>', false)">None</a>
                </span>
            </h4>
            <div class="perm-checks">
                <?php foreach ($perms as $p): ?>
                    <label class="perm-check">
                        <input type="checkbox" name="permissions[]" value="<?php echo $p['id']; ?>" data-module="<?php echo $module; ?>" data-action="<?php echo $p['action_name']; ?>"
                            <?php echo in_array((int)$p['id'], $assigned, true) ? 'checked' : ''; ?>
                            <?php echo ($role['role_slug'] === 'super_admin') ? 'disabled checked' : ''; ?>>
                        <?php echo ucwords(str_replace('_',' ',$p['action_name'])); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <?php if ($role['role_slug'] === 'super_admin'): ?>
            <div class="hint" style="margin-bottom:14px">The Super Admin role always has every permission and cannot be restricted.</div>
        <?php endif; ?>

        <div id="formErr" class="err" style="display:none;margin:10px 0"></div>
        <div class="modal-actions" style="justify-content:flex-start;margin-top:20px">
            <?php if ($role['role_slug'] !== 'super_admin'): ?>
            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            <?php endif; ?>
            <a href="roles.php" class="btn outline">Back to Roles</a>
        </div>
    </form>
</div>

<div class="card">
    <div class="card-head"><h2>Members with this Role (<?php echo count($members); ?>)</h2></div>
    <?php if (empty($members)): ?>
        <div class="empty-state"><i class="fa-solid fa-user-slash"></i>No one is currently assigned this role.</div>
    <?php else: ?>
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($members as $mm): ?>
            <tr><td><?php echo htmlspecialchars($mm['full_name']); ?></td><td><?php echo htmlspecialchars($mm['email']); ?></td><td><span class="tag <?php echo $mm['status']; ?>"><?php echo ucfirst($mm['status']); ?></span></td></tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php endif; ?>
</div>

<script>
function setAll(state){ document.querySelectorAll('input[name="permissions[]"]:not([disabled])').forEach(cb => cb.checked = state); }
function setGroup(module, state){ document.querySelectorAll('input[data-module="'+module+'"]:not([disabled])').forEach(cb => cb.checked = state); }
function setViewOnly(){
    document.querySelectorAll('input[name="permissions[]"]:not([disabled])').forEach(cb => { cb.checked = (cb.dataset.action === 'view'); });
}
async function submitEditRole(e){
    e.preventDefault();
    const form = document.getElementById('editRoleForm');
    const fd = new FormData(form);
    fd.append('action', 'update');
    const errBox = document.getElementById('formErr');
    errBox.style.display = 'none';
    const btn = form.querySelector('button[type=submit]');
    if (btn) btn.disabled = true;
    try {
        const res = await fetch('actions/role_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) { showToast('Role updated.'); setTimeout(()=> location.href='roles.php', 800); }
        else { errBox.textContent = data.error || 'Could not update role.'; errBox.style.display = 'block'; }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
    }
    if (btn) btn.disabled = false;
    return false;
}
</script>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
