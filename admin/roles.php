<?php
// =====================================================================
// admin/roles.php — View all roles (system + custom), with member
// counts, and actions: create, edit, duplicate, activate/deactivate.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.change_permissions');

$roles = $conn->query(
    "SELECT r.*, COUNT(m.id) AS member_count
       FROM admin_roles r
       LEFT JOIN admin_team_members m ON m.role_id = r.id AND m.status <> 'inactive'
      GROUP BY r.id
      ORDER BY r.is_system_role DESC, r.role_name ASC"
)->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Roles';
$pageSubtitle = 'Define admin roles and the permissions each one carries.';
$activeTeamTab = 'roles';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
/* ---- Actions dropdown restyle (white card, icon + label rows) ----
   Visual styling only — position/width is force-set by the script
   below, because the shared toggleActionMenu() relocates/resizes the
   .action-menu div in a way plain CSS can't reliably follow (same fix
   as accounts.php, inventory.php, invoices.php, company_profile.php). */
.kebab-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text);transition:.15s ease}
.kebab-btn:hover{background:var(--bg-soft);border-color:var(--primary)}
.action-menu{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.14);padding:6px}
.action-menu button,.action-menu a{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:none;background:none;text-align:left;font-size:13px;border-radius:8px;cursor:pointer;color:var(--text);text-decoration:none;white-space:nowrap}
.action-menu button:hover,.action-menu a:hover{background:var(--bg-soft)}
.action-menu i{width:16px;text-align:center;color:var(--muted)}
.action-menu .menu-danger{color:#c0392b}
.action-menu .menu-danger i{color:#c0392b}
.action-menu .menu-success{color:#1a7f37}
.action-menu .menu-success i{color:#1a7f37}
</style>
<div class="card">
    <div class="card-head">
        <h2>Admin Roles</h2>
        <a href="create_role.php" class="btn"><i class="fa-solid fa-plus"></i> Create Custom Role</a>
    </div>
    <div style="overflow-x:auto">
    <table>
        <thead><tr><th>Role</th><th>Description</th><th>Type</th><th>Members</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($roles as $r): ?>
            <tr>
                <td><span class="role-badge"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($r['role_name']); ?></span></td>
                <td style="max-width:320px;color:var(--muted)"><?php echo htmlspecialchars($r['description'] ?: '—'); ?></td>
                <td><?php echo $r['is_system_role'] ? '<span class="tag pending">System</span>' : '<span class="tag active">Custom</span>'; ?></td>
                <td><a href="team_members.php?role=<?php echo $r['id']; ?>"><?php echo (int)$r['member_count']; ?></a></td>
                <td><span class="tag <?php echo $r['is_active'] ? 'active' : 'inactive'; ?>"><?php echo $r['is_active'] ? 'Active' : 'Inactive'; ?></span></td>
                <td>
                    <div class="action-menu-wrap">
                        <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        <div class="action-menu">
                            <a href="edit_role.php?id=<?php echo $r['id']; ?>"><i class="fa-solid fa-pen"></i> Edit Permissions</a>
                            <button onclick="duplicateRole(<?php echo $r['id']; ?>)"><i class="fa-solid fa-copy"></i> Duplicate</button>
                            <?php if ($r['role_slug'] !== 'super_admin'): ?>
                            <button onclick="toggleRole(<?php echo $r['id']; ?>)">
                                <i class="fa-solid <?php echo $r['is_active'] ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i> <?php echo $r['is_active'] ? 'Deactivate' : 'Activate'; ?>
                            </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
async function duplicateRole(roleId){
    const fd = new FormData();
    fd.append('action', 'duplicate');
    fd.append('role_id', roleId);
    const res = await fetch('actions/role_action.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) { showToast('Role duplicated.'); setTimeout(()=>location.reload(), 700); }
    else { showToast(data.error || 'Could not duplicate role.', true); }
}
async function toggleRole(roleId){
    const fd = new FormData();
    fd.append('action', 'toggle_active');
    fd.append('role_id', roleId);
    const res = await fetch('actions/role_action.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) { showToast('Role status updated.'); setTimeout(()=>location.reload(), 700); }
    else { showToast(data.error || 'Could not update role.', true); }
}

// ---------------------------------------------------------------------
// Kebab dropdown position fix — same as accounts.php / inventory.php /
// company_profile.php. The shared toggleActionMenu() seems to relocate
// or resize the .action-menu div in a way plain CSS can't reliably
// follow, so this re-anchors it with position:fixed, computed fresh
// from the button's on-screen position every time it's opened.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.action-menu-wrap').forEach(function (wrap) {
        var btn = wrap.querySelector('.kebab-btn');
        var menu = wrap.querySelector('.action-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function () {
            // Runs after the existing onclick="toggleActionMenu(...)".
            setTimeout(function () {
                var open = menu.style.display === 'block'
                    || menu.classList.contains('open')
                    || menu.classList.contains('show')
                    || getComputedStyle(menu).display !== 'none';
                if (!open) return;
                var r = btn.getBoundingClientRect();
                menu.style.setProperty('position', 'fixed', 'important');
                menu.style.setProperty('top', (r.bottom + 6) + 'px', 'important');
                menu.style.setProperty('left', 'auto', 'important');
                menu.style.setProperty('right', (window.innerWidth - r.right) + 'px', 'important');
                menu.style.setProperty('bottom', 'auto', 'important');
                menu.style.setProperty('width', 'auto', 'important');
                menu.style.setProperty('min-width', '200px', 'important');
                menu.style.setProperty('max-width', '240px', 'important');
                menu.style.setProperty('z-index', '9999', 'important');
                menu.style.setProperty('margin', '0', 'important');
            }, 0);
        });
    });
});
</script>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
