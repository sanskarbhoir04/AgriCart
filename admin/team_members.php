<?php
// =====================================================================
// admin/team_members.php — Team Members list: search, filter by role /
// status / department, paginate, and act on each member (view, edit,
// change role, manage permissions, activate/deactivate/suspend, reset
// password, view activity, remove access).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.view');

$search      = trim($_GET['q'] ?? '');
$roleFilter  = (int)($_GET['role'] ?? 0);
$statusFilter= trim($_GET['status'] ?? '');
$deptFilter  = trim($_GET['department'] ?? '');
$page        = max(1, (int)($_GET['page'] ?? 1));
$perPage     = 15;
$offset      = ($page - 1) * $perPage;

$where  = [];
$types  = '';
$params = [];

if ($search !== '') {
    $where[] = "(u.full_name LIKE ? OR u.username LIKE ? OR u.email LIKE ? OR u.mobile LIKE ?)";
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
    $types .= 'ssss';
}
if ($roleFilter > 0) { $where[] = "m.role_id = ?"; $params[] = $roleFilter; $types .= 'i'; }
if ($statusFilter !== '' && in_array($statusFilter, ['active','inactive','suspended','expired'], true)) {
    $where[] = "m.status = ?"; $params[] = $statusFilter; $types .= 's';
}
if ($deptFilter !== '') { $where[] = "m.department = ?"; $params[] = $deptFilter; $types .= 's'; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$countSql = "SELECT COUNT(*) AS c FROM admin_team_members m JOIN users u ON u.id = m.user_id JOIN admin_roles r ON r.id = m.role_id $whereSql";
$countStmt = $conn->prepare($countSql);
if ($types !== '') { $countStmt->bind_param($types, ...$params); }
$countStmt->execute();
$total = (int)($countStmt->get_result()->fetch_assoc()['c'] ?? 0);
$totalPages = max(1, (int)ceil($total / $perPage));

$listSql = "SELECT m.*, u.full_name, u.username, u.email, u.mobile, u.profile_photo, r.role_name, r.role_slug
            FROM admin_team_members m
            JOIN users u ON u.id = m.user_id
            JOIN admin_roles r ON r.id = m.role_id
            $whereSql
            ORDER BY m.created_at DESC
            LIMIT ? OFFSET ?";
$listStmt = $conn->prepare($listSql);
$allTypes = $types . 'ii';
$allParams = array_merge($params, [$perPage, $offset]);
$listStmt->bind_param($allTypes, ...$allParams);
$listStmt->execute();
$members = $listStmt->get_result()->fetch_all(MYSQLI_ASSOC);

$roles = $conn->query("SELECT id, role_name FROM admin_roles WHERE is_active = 1 ORDER BY is_system_role DESC, role_name ASC")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT DISTINCT department FROM admin_team_members WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetch_all(MYSQLI_ASSOC);
$allRolesForModal = $conn->query("SELECT id, role_name, role_slug FROM admin_roles WHERE is_active = 1 ORDER BY is_system_role DESC, role_name ASC")->fetch_all(MYSQLI_ASSOC);

// All roles currently assigned to each listed member (a member can now
// hold more than one), keyed by member id — used for the Role column
// badges and to pre-check the Change Role popup's checkboxes.
$memberRoles = [];
if (!empty($members)) {
    $memberIds = array_column($members, 'id');
    $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
    $rq = $conn->prepare(
        "SELECT tmr.member_id, r.id AS role_id, r.role_name
           FROM admin_team_member_roles tmr
           JOIN admin_roles r ON r.id = tmr.role_id
          WHERE tmr.member_id IN ($placeholders)
          ORDER BY r.is_system_role DESC, r.role_name ASC"
    );
    $rq->bind_param(str_repeat('i', count($memberIds)), ...$memberIds);
    $rq->execute();
    $rres = $rq->get_result();
    while ($row = $rres->fetch_assoc()) {
        $memberRoles[$row['member_id']][] = ['id' => (int)$row['role_id'], 'role_name' => $row['role_name']];
    }
}

$pageTitle = 'Team Members';
$pageSubtitle = 'Manage every admin with access to this panel.';
$activeTeamTab = 'members';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
/* The "..." actions menu itself (.actions-cell / .actions-menu-btn /
   .actions-menu / .danger-item) is now styled globally by
   assets/css/action-menu.css, loaded from includes/team_layout_top.php —
   no per-page copy needed here any more. */
.role-check-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:6px 14px;border:1px solid var(--border);border-radius:9px;padding:12px 14px}
.role-check-item{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:400;cursor:pointer;padding:3px 0}
.role-check-item input{width:15px;height:15px;cursor:pointer}
.member-popup-box{max-width:560px}
.member-popup-header{display:flex;align-items:center;gap:14px;margin-bottom:16px}
.member-popup-avatar{width:56px;height:56px;border-radius:50%;background:var(--bg-soft);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;overflow:hidden;flex-shrink:0}
.member-popup-avatar img{width:100%;height:100%;object-fit:cover}
.member-popup-view dl{display:grid;grid-template-columns:130px 1fr;row-gap:10px;column-gap:10px;font-size:13.5px;margin:0}
.member-popup-view dt{color:var(--muted)}
.member-popup-view dd{margin:0}
</style>

<div class="card">
    <div class="card-head">
        <h2>Team Members <span style="color:var(--muted);font-weight:400">(<?php echo $total; ?>)</span></h2>
        <?php if (hasPermission('team.create')): ?>
        <a href="add_team_member.php" class="btn"><i class="fa-solid fa-user-plus"></i> Add Team Member</a>
        <?php endif; ?>
    </div>

    <form method="get" class="filters">
        <input type="text" name="q" placeholder="Search name, username, email, mobile..." value="<?php echo htmlspecialchars($search); ?>" style="min-width:240px">
        <select name="role">
            <option value="0">All Roles</option>
            <?php foreach ($roles as $r): ?>
                <option value="<?php echo $r['id']; ?>" <?php echo $roleFilter == $r['id'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($r['role_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="status">
            <option value="">All Status</option>
            <?php foreach (['active','inactive','suspended','expired'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $statusFilter === $s ? 'selected' : ''; ?>><?php echo ucfirst($s); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="department">
            <option value="">All Departments</option>
            <?php foreach ($departments as $d): ?>
                <option value="<?php echo htmlspecialchars($d['department']); ?>" <?php echo $deptFilter === $d['department'] ? 'selected' : ''; ?>><?php echo htmlspecialchars($d['department']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn outline sm" type="submit"><i class="fa-solid fa-filter"></i> Filter</button>
        <?php if ($search || $roleFilter || $statusFilter || $deptFilter): ?>
        <a href="team_members.php" class="btn outline sm">Clear</a>
        <?php endif; ?>
    </form>

    <?php if (empty($members)): ?>
        <div class="empty-state"><i class="fa-solid fa-users-slash"></i>No team members match these filters.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead><tr>
            <th></th><th>Name</th><th>Username</th><th>Email / Mobile</th><th>Department</th>
            <th>Role</th><th>Status</th><th>Assigned</th><th>Expiry</th><th>Last Login</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($members as $m): $isSelf = ((int)$m['id'] === (int)($_SESSION['admin_member_id'] ?? 0)); ?>
            <tr class="member-row" style="cursor:pointer" onclick="openMemberPopup(<?php echo (int)$m['id']; ?>)">
                <td>
                    <div class="avatar-circle">
                        <?php if (!empty($m['profile_photo'])): ?>
                            <img src="../<?php echo htmlspecialchars($m['profile_photo']); ?>" onerror="this.parentElement.textContent='<?php echo strtoupper(substr($m['full_name'],0,1)); ?>'">
                        <?php else: echo strtoupper(substr($m['full_name'] ?: '?', 0, 1)); endif; ?>
                    </div>
                </td>
                <td><strong><?php echo htmlspecialchars($m['full_name']); ?></strong><?php if ($isSelf): ?> <span style="color:var(--muted);font-size:11px">(you)</span><?php endif; ?></td>
                <td><?php echo htmlspecialchars($m['username'] ?: '—'); ?></td>
                <td><?php echo htmlspecialchars($m['email']); ?><br><span style="color:var(--muted)"><?php echo htmlspecialchars($m['mobile']); ?></span></td>
                <td><?php echo htmlspecialchars($m['department'] ?: '—'); ?></td>
                <td>
                    <?php $rolesForThisMember = $memberRoles[$m['id']] ?? [['id' => $m['role_id'], 'role_name' => $m['role_name']]]; ?>
                    <?php foreach ($rolesForThisMember as $rr): ?>
                        <span class="role-badge" style="margin:0 3px 3px 0;display:inline-flex"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($rr['role_name']); ?></span>
                    <?php endforeach; ?>
                </td>
                <td><span class="tag <?php echo $m['status']; ?>"><?php echo ucfirst($m['status']); ?></span></td>
                <td><?php echo $m['assigned_at'] ? date('d M Y', strtotime($m['assigned_at'])) : '—'; ?></td>
                <td><?php echo $m['access_expiry_date'] ? date('d M Y', strtotime($m['access_expiry_date'])) : '<span style="color:var(--muted)">No expiry</span>'; ?></td>
                <td><?php echo $m['last_login'] ? date('d M Y, h:i A', strtotime($m['last_login'])) : '<span style="color:var(--muted)">Never</span>'; ?></td>
                <td class="actions-cell">
                    <button type="button" class="actions-menu-btn" title="Actions" onclick="toggleActionsMenu(event, 'am<?php echo (int)$m['id']; ?>')"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="actions-menu" id="am<?php echo (int)$m['id']; ?>">
                        <?php if (hasPermission('activity_logs.view')): ?>
                        <a href="activity_logs.php?user_id=<?php echo $m['user_id']; ?>"><i class="fa-solid fa-clock-rotate-left"></i> View Activity <span style="color:var(--muted)">(added/removed/changed)</span></a>
                        <?php endif; ?>
                        <?php if (hasPermission('team.edit')): ?>
                        <a href="edit_team_member.php?id=<?php echo $m['id']; ?>"><i class="fa-solid fa-pen"></i> Edit Details</a>
                        <?php endif; ?>
                        <?php if (hasPermission('team.assign_role') && !$isSelf): ?>
                        <button onclick='event.stopPropagation();closeAllActionsMenus();openChangeRole(<?php echo (int)$m["id"]; ?>, <?php echo json_encode(array_column($rolesForThisMember, "id")); ?>, "<?php echo htmlspecialchars($m["full_name"], ENT_QUOTES); ?>")'><i class="fa-solid fa-user-pen"></i> Change Role</button>
                        <?php endif; ?>
                        <?php if (hasPermission('team.change_permissions')): ?>
                        <a href="manage_permissions.php?member_id=<?php echo $m['id']; ?>"><i class="fa-solid fa-key"></i> Manage Permissions</a>
                        <?php endif; ?>
                        <?php if (hasPermission('team.edit')): ?>
                        <button onclick='closeAllActionsMenus();openResetPassword(<?php echo (int)$m["id"]; ?>, "<?php echo htmlspecialchars($m["full_name"], ENT_QUOTES); ?>")'><i class="fa-solid fa-lock"></i> Reset Password</button>
                        <?php endif; ?>
                        <?php if (hasPermission('team.disable') && !$isSelf): ?>
                            <div class="divider"></div>
                            <?php if ($m['status'] !== 'active'): ?>
                            <button onclick="closeAllActionsMenus();teamStatusAction(<?php echo $m['id']; ?>,'activate')"><i class="fa-solid fa-toggle-on" style="color:var(--success)"></i> Activate</button>
                            <?php else: ?>
                            <button onclick="closeAllActionsMenus();teamStatusAction(<?php echo $m['id']; ?>,'deactivate')"><i class="fa-solid fa-toggle-off"></i> Deactivate</button>
                            <?php endif; ?>
                            <?php if ($m['status'] !== 'suspended'): ?>
                            <button onclick="closeAllActionsMenus();confirmAction('Suspend this team member?', function(){ teamStatusAction(<?php echo $m['id']; ?>,'suspend') })"><i class="fa-solid fa-ban"></i> Suspend</button>
                            <?php endif; ?>
                        <?php endif; ?>
                        <?php if (hasPermission('team.delete') && !$isSelf): ?>
                            <div class="divider"></div>
                            <button class="danger-item" onclick="closeAllActionsMenus();confirmAction('Remove admin access for this team member? Their activity history will be kept.', function(){ teamRemove(<?php echo $m['id']; ?>) })"><i class="fa-solid fa-user-xmark"></i> Remove Access</button>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>

    <div class="pagination">
        <?php
        $qs = $_GET; unset($qs['page']);
        $baseQs = http_build_query($qs);
        for ($p = 1; $p <= $totalPages; $p++):
            $sep = $baseQs ? '&' : '';
        ?>
            <a href="?<?php echo $baseQs . $sep; ?>page=<?php echo $p; ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
</div>

<!-- Change Role Modal -->
<div class="modal-overlay" id="modalChangeRole">
    <div class="modal-box">
        <h3>Change Role</h3>
        <p id="changeRoleWho"></p>
        <form onsubmit="return submitChangeRole(event)">
            <input type="hidden" id="crMemberId">
            <div class="form-group">
                <label>Roles <span style="color:var(--muted);font-weight:400">(select one or more)</span></label>
                <div class="role-check-grid" id="crRoleGrid">
                    <?php foreach ($allRolesForModal as $r): ?>
                        <label class="role-check-item">
                            <input type="checkbox" class="crRoleCheckbox" value="<?php echo $r['id']; ?>" <?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? 'disabled' : ''; ?>>
                            <?php echo htmlspecialchars($r['role_name']); ?><?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? ' <span style="color:var(--muted)">(Super Admin only)</span>' : ''; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="crErr" class="err" style="display:none;margin-bottom:10px"></div>
            <div class="modal-actions">
                <button type="button" class="btn outline" onclick="closeModal('modalChangeRole')">Cancel</button>
                <button type="submit" class="btn">Save Role</button>
            </div>
        </form>
    </div>
</div>

<!-- Member Details Popup (view + inline edit) -->
<div class="modal-overlay" id="modalMemberPopup">
    <div class="modal-box member-popup-box">
        <div class="member-popup-header">
            <div class="member-popup-avatar" id="mpAvatar">?</div>
            <div>
                <h3 id="mpName" style="margin:0 0 3px">&nbsp;</h3>
                <span class="tag" id="mpStatusTag"></span>
            </div>
            <div style="margin-left:auto;display:flex;gap:8px">
                <?php if (hasPermission('team.edit') || hasPermission('team.assign_role')): ?>
                <button type="button" class="btn sm outline" id="mpEditBtn" onclick="mpToggleEdit(true)"><i class="fa-solid fa-pen"></i> Edit</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- View mode -->
        <div id="mpViewMode" class="member-popup-view">
            <dl>
                <dt>Username</dt><dd id="mpUsername">—</dd>
                <dt>Email</dt><dd id="mpEmail">—</dd>
                <dt>Mobile</dt><dd id="mpMobile">—</dd>
                <dt>Department</dt><dd id="mpDepartment">—</dd>
                <dt>Roles</dt><dd id="mpRoles">—</dd>
                <dt>Assigned</dt><dd id="mpAssigned">—</dd>
                <dt>Access Expiry</dt><dd id="mpExpiry">—</dd>
                <dt>Last Login</dt><dd id="mpLastLogin">—</dd>
            </dl>
        </div>

        <!-- Edit mode -->
        <form id="mpEditForm" style="display:none" onsubmit="return mpSubmitEdit(event)">
            <input type="hidden" id="mpEditMemberId">
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name *</label>
                    <input type="text" id="mpEditFullName" required>
                </div>
                <div class="form-group">
                    <label>Email *</label>
                    <input type="email" id="mpEditEmail" required>
                </div>
                <div class="form-group">
                    <label>Mobile Number *</label>
                    <input type="tel" id="mpEditMobile" required pattern="[0-9]{10}" maxlength="10">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" id="mpEditDepartment">
                </div>
                <div class="form-group">
                    <label>Access Expiry Date</label>
                    <input type="date" id="mpEditExpiry">
                </div>
                <?php if (hasPermission('team.assign_role')): ?>
                <div class="form-group full">
                    <label>Roles <span style="color:var(--muted);font-weight:400">(select one or more)</span></label>
                    <div class="role-check-grid" id="mpRoleGrid">
                        <?php foreach ($allRolesForModal as $r): ?>
                            <label class="role-check-item">
                                <input type="checkbox" class="mpRoleCheckbox" value="<?php echo $r['id']; ?>" <?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($r['role_name']); ?><?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? ' <span style="color:var(--muted)">(Super Admin only)</span>' : ''; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
            <div id="mpEditErr" class="err" style="display:none;margin-bottom:10px"></div>
            <div class="modal-actions">
                <button type="button" class="btn outline" onclick="mpToggleEdit(false)">Cancel</button>
                <button type="submit" class="btn">Save Changes</button>
            </div>
        </form>

        <div class="modal-actions" id="mpViewActions">
            <button type="button" class="btn outline" onclick="closeModal('modalMemberPopup')">Close</button>
        </div>
    </div>
</div>

<!-- Reset Password Modal -->
<div class="modal-overlay" id="modalResetPw">
    <div class="modal-box">
        <h3>Reset Password</h3>
        <p id="resetPwWho"></p>
        <form onsubmit="return submitResetPassword(event)">
            <input type="hidden" id="rpMemberId">
            <div class="form-group">
                <label>New Password</label>
                <input type="password" id="rpNewPassword" required minlength="8">
                <div class="hint">At least 8 characters, letters + numbers.</div>
            </div>
            <div class="form-group">
                <label>Confirm Password</label>
                <input type="password" id="rpConfirmPassword" required minlength="8">
            </div>
            <div class="modal-actions">
                <button type="button" class="btn outline" onclick="closeModal('modalResetPw')">Cancel</button>
                <button type="submit" class="btn">Reset Password</button>
            </div>
        </form>
    </div>
</div>

<!-- Generic Confirm Modal -->
<div class="modal-overlay" id="modalConfirm">
    <div class="modal-box">
        <h3>Please confirm</h3>
        <p id="confirmMsg"></p>
        <div class="modal-actions">
            <button type="button" class="btn outline" onclick="closeModal('modalConfirm')">Cancel</button>
            <button type="button" class="btn danger" id="confirmYesBtn">Yes, continue</button>
        </div>
    </div>
</div>

<script>
// toggleActionsMenu / closeAllActionsMenus / confirmAction now come from
// the shared assets/js/action-menu.js (loaded by team_layout_bottom.php).
// This page's own #modalConfirm below is picked up by confirmAction()
// automatically since it already has id="modalConfirm".

function openChangeRole(memberId, currentRoleIds, name){
    document.getElementById('crMemberId').value = memberId;
    document.getElementById('crErr').style.display = 'none';
    const ids = (currentRoleIds || []).map(String);
    document.querySelectorAll('.crRoleCheckbox').forEach(function(cb){
        cb.checked = !cb.disabled && ids.includes(cb.value);
    });
    document.getElementById('changeRoleWho').textContent = 'Assign role(s) to ' + name + '.';
    openModal('modalChangeRole');
}
async function submitChangeRole(e){
    e.preventDefault();
    const errBox = document.getElementById('crErr');
    errBox.style.display = 'none';
    const roleIds = Array.from(document.querySelectorAll('.crRoleCheckbox:checked')).map(cb => cb.value);
    if (!roleIds.length) {
        errBox.textContent = 'Please select at least one role.';
        errBox.style.display = 'block';
        return false;
    }
    const fd = new FormData();
    fd.append('action','change_role');
    fd.append('member_id', document.getElementById('crMemberId').value);
    roleIds.forEach(id => fd.append('role_ids[]', id));
    try {
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            showToast('Role updated.');
            closeModal('modalChangeRole');
            setTimeout(()=>location.reload(), 700);
        } else {
            errBox.textContent = data.error || 'Could not update role.';
            errBox.style.display = 'block';
        }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
    }
    return false;
}

// ---------------------------------------------------------------------
// Member Details popup: click any team member row to view their info;
// "Edit" switches the same popup into an editable form.
// ---------------------------------------------------------------------
let mpCurrentMember = null;

async function openMemberPopup(memberId){
    mpToggleEdit(false);
    document.getElementById('mpName').textContent = 'Loading...';
    document.getElementById('mpUsername').textContent = '—';
    document.getElementById('mpEmail').textContent = '—';
    document.getElementById('mpMobile').textContent = '—';
    document.getElementById('mpDepartment').textContent = '—';
    document.getElementById('mpRoles').textContent = '—';
    document.getElementById('mpAssigned').textContent = '—';
    document.getElementById('mpExpiry').textContent = '—';
    document.getElementById('mpLastLogin').textContent = '—';
    document.getElementById('mpStatusTag').textContent = '';
    document.getElementById('mpAvatar').innerHTML = '?';
    openModal('modalMemberPopup');

    const fd = new FormData();
    fd.append('action', 'get_member');
    fd.append('member_id', memberId);
    try {
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (!data.success) { showToast(data.error || 'Could not load team member.', true); closeModal('modalMemberPopup'); return; }
        mpCurrentMember = data.member;
        const m = data.member;

        document.getElementById('mpName').textContent = m.full_name;
        document.getElementById('mpUsername').textContent = m.username || '—';
        document.getElementById('mpEmail').textContent = m.email || '—';
        document.getElementById('mpMobile').textContent = m.mobile || '—';
        document.getElementById('mpDepartment').textContent = m.department || '—';
        document.getElementById('mpRoles').textContent = (m.role_names && m.role_names.length) ? m.role_names.join(', ') : '—';
        document.getElementById('mpAssigned').textContent = m.assigned_at ? m.assigned_at.substring(0,10) : '—';
        document.getElementById('mpExpiry').textContent = m.access_expiry_date ? m.access_expiry_date.substring(0,10) : 'No expiry';
        document.getElementById('mpLastLogin').textContent = m.last_login ? m.last_login : 'Never';

        const tag = document.getElementById('mpStatusTag');
        tag.textContent = m.status.charAt(0).toUpperCase() + m.status.slice(1);
        tag.className = 'tag ' + m.status;

        const avatar = document.getElementById('mpAvatar');
        avatar.innerHTML = m.profile_photo
            ? '<img src="../' + m.profile_photo + '">'
            : (m.full_name ? m.full_name.charAt(0).toUpperCase() : '?');
    } catch (err) {
        showToast('Network error — please try again.', true);
        closeModal('modalMemberPopup');
    }
}

function mpToggleEdit(editing){
    document.getElementById('mpViewMode').style.display = editing ? 'none' : 'block';
    document.getElementById('mpEditForm').style.display = editing ? 'block' : 'none';
    document.getElementById('mpViewActions').style.display = editing ? 'none' : 'flex';
    const editBtn = document.getElementById('mpEditBtn');
    if (editBtn) editBtn.style.display = editing ? 'none' : 'inline-flex';

    if (editing && mpCurrentMember) {
        const m = mpCurrentMember;
        document.getElementById('mpEditMemberId').value = m.id;
        document.getElementById('mpEditFullName').value = m.full_name || '';
        document.getElementById('mpEditEmail').value = m.email || '';
        document.getElementById('mpEditMobile').value = m.mobile || '';
        document.getElementById('mpEditDepartment').value = m.department || '';
        document.getElementById('mpEditExpiry').value = m.access_expiry_date ? m.access_expiry_date.substring(0,10) : '';
        const ids = (m.role_ids || []).map(String);
        document.querySelectorAll('.mpRoleCheckbox').forEach(function(cb){
            cb.checked = !cb.disabled && ids.includes(cb.value);
        });
        document.getElementById('mpEditErr').style.display = 'none';
    }
}

async function mpSubmitEdit(e){
    e.preventDefault();
    const errBox = document.getElementById('mpEditErr');
    errBox.style.display = 'none';

    const fd = new FormData();
    fd.append('action', 'update');
    fd.append('member_id', document.getElementById('mpEditMemberId').value);
    fd.append('full_name', document.getElementById('mpEditFullName').value);
    fd.append('email', document.getElementById('mpEditEmail').value);
    fd.append('mobile', document.getElementById('mpEditMobile').value);
    fd.append('department', document.getElementById('mpEditDepartment').value);
    fd.append('access_expiry_date', document.getElementById('mpEditExpiry').value);
    const roleChecks = document.querySelectorAll('.mpRoleCheckbox');
    if (roleChecks.length) {
        const roleIds = Array.from(roleChecks).filter(cb => cb.checked).map(cb => cb.value);
        if (!roleIds.length) {
            errBox.textContent = 'Please select at least one role.';
            errBox.style.display = 'block';
            return false;
        }
        roleIds.forEach(id => fd.append('role_ids[]', id));
    }

    try {
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            showToast('Team member updated.');
            closeModal('modalMemberPopup');
            setTimeout(()=>location.reload(), 700);
        } else {
            errBox.textContent = data.error || 'Could not update team member.';
            errBox.style.display = 'block';
        }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
    }
    return false;
}

function openResetPassword(memberId, name){
    document.getElementById('rpMemberId').value = memberId;
    document.getElementById('resetPwWho').textContent = 'Set a new password for ' + name + '.';
    openModal('modalResetPw');
}
async function submitResetPassword(e){
    e.preventDefault();
    const p1 = document.getElementById('rpNewPassword').value;
    const p2 = document.getElementById('rpConfirmPassword').value;
    if (p1 !== p2) { showToast('Passwords do not match.', true); return false; }
    const fd = new FormData();
    fd.append('action','reset_password');
    fd.append('member_id', document.getElementById('rpMemberId').value);
    fd.append('new_password', p1);
    fd.append('confirm_password', p2);
    const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) { showToast('Password reset.'); closeModal('modalResetPw'); }
    else { showToast(data.error || 'Could not reset password.', true); }
    return false;
}

async function teamStatusAction(memberId, action){
    const fd = new FormData();
    fd.append('action', action);
    fd.append('member_id', memberId);
    const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) { showToast('Status updated.'); setTimeout(()=>location.reload(), 700); }
    else { showToast(data.error || 'Could not update status.', true); }
}

async function teamRemove(memberId){
    const fd = new FormData();
    fd.append('action', 'remove');
    fd.append('member_id', memberId);
    const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
    const data = await res.json();
    if (data.success) { showToast('Access removed.'); setTimeout(()=>location.reload(), 700); }
    else { showToast(data.error || 'Could not remove access.', true); }
}
</script>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
