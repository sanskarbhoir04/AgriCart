<?php
// =====================================================================
// admin/create_role.php — Super Admin creates a custom Admin Panel
// role and assigns its permissions, grouped module-wise.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.change_permissions');

$permissions = $conn->query("SELECT * FROM admin_permissions ORDER BY module_name, action_name")->fetch_all(MYSQLI_ASSOC);
$grouped = [];
foreach ($permissions as $p) { $grouped[$p['module_name']][] = $p; }

$pageTitle = 'Create Custom Role';
$activeTeamTab = 'roles';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="card" style="max-width:820px">
    <div class="card-head"><h2>Create Custom Role</h2></div>
    <form id="createRoleForm" onsubmit="return submitCreateRole(event)">
        <div class="form-grid">
            <div class="form-group">
                <label>Role Name *</label>
                <input type="text" name="role_name" required placeholder="e.g. Regional Sales Manager">
            </div>
            <div class="form-group full">
                <label>Description</label>
                <textarea name="description" rows="2" placeholder="What does this role do?"></textarea>
            </div>
        </div>

        <div style="display:flex;justify-content:space-between;align-items:center;margin:6px 0 12px">
            <h3 style="font-size:14px">Permissions</h3>
            <div style="display:flex;gap:14px">
                <a onclick="setAll(true)" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">Select All</a>
                <a onclick="setAll(false)" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">Clear All</a>
                <a onclick="setViewOnly()" style="font-size:12px;color:var(--primary);font-weight:600;cursor:pointer">View Only</a>
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
                        <input type="checkbox" name="permissions[]" value="<?php echo $p['id']; ?>" data-module="<?php echo $module; ?>" data-action="<?php echo $p['action_name']; ?>">
                        <?php echo ucwords(str_replace('_',' ',$p['action_name'])); ?>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <div id="formErr" class="err" style="display:none;margin:10px 0"></div>
        <div class="modal-actions" style="justify-content:flex-start;margin-top:20px">
            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Create Role</button>
            <a href="roles.php" class="btn outline">Cancel</a>
        </div>
    </form>
</div>

<script>
function setAll(state){ document.querySelectorAll('input[name="permissions[]"]').forEach(cb => cb.checked = state); }
function setGroup(module, state){ document.querySelectorAll('input[data-module="'+module+'"]').forEach(cb => cb.checked = state); }
function setViewOnly(){
    document.querySelectorAll('input[name="permissions[]"]').forEach(cb => {
        cb.checked = (cb.dataset.action === 'view');
    });
}
async function submitCreateRole(e){
    e.preventDefault();
    const form = document.getElementById('createRoleForm');
    const fd = new FormData(form);
    fd.append('action', 'create');
    const errBox = document.getElementById('formErr');
    errBox.style.display = 'none';
    const btn = form.querySelector('button[type=submit]');
    btn.disabled = true;
    try {
        const res = await fetch('actions/role_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            showToast('Role created.');
            setTimeout(()=> location.href = 'roles.php', 800);
        } else {
            errBox.textContent = data.error || 'Could not create role.';
            errBox.style.display = 'block';
        }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
    }
    btn.disabled = false;
    return false;
}
</script>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
