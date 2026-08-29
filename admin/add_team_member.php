<?php
// =====================================================================
// admin/add_team_member.php — Add a new Admin Panel team member.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.create');

$roles = $conn->query("SELECT id, role_name, role_slug, description FROM admin_roles WHERE is_active = 1 ORDER BY is_system_role DESC, role_name ASC")->fetch_all(MYSQLI_ASSOC);
$departments = $conn->query("SELECT DISTINCT department FROM admin_team_members WHERE department IS NOT NULL AND department <> '' ORDER BY department")->fetch_all(MYSQLI_ASSOC);

$pageTitle = 'Add Team Member';
$pageSubtitle = 'Invite a new admin and set their role and permissions.';
$activeTeamTab = 'add';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.role-check-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:6px 14px;border:1px solid var(--border);border-radius:9px;padding:12px 14px}
.role-check-item{display:flex;align-items:center;gap:8px;font-size:13px;font-weight:400;cursor:pointer;padding:3px 0}
.role-check-item input{width:15px;height:15px;cursor:pointer}
</style>
<div class="card" style="max-width:760px">
    <div class="card-head">
        <h2>Add Team Member</h2>
        <div style="display:flex;gap:8px">
            <button type="button" class="btn sm" id="modeNewBtn" onclick="setMode('new')">New Person</button>
            <button type="button" class="btn sm outline" id="modeExistingBtn" onclick="setMode('existing')">Existing Website User</button>
        </div>
    </div>

    <!-- ===================== EXISTING-USER MODE ===================== -->
    <div id="existingUserBlock" style="display:none">
        <p style="color:var(--muted);font-size:13px;margin-bottom:16px">
            Give Admin Panel access to someone who already has a AgriCart account (farmer, seller, buyer, expert, or customer). Search by name, mobile number, email, or username.
        </p>
        <div class="form-group">
            <label>Search User *</label>
            <input type="text" id="userSearchInput" placeholder="Type a name, mobile, email or username..." autocomplete="off">
            <div id="userSearchResults" style="border:1px solid var(--border);border-radius:9px;margin-top:6px;max-height:220px;overflow-y:auto;display:none"></div>
        </div>
        <div id="selectedUserBox" style="display:none;background:var(--bg-soft);border-radius:10px;padding:12px 14px;margin-bottom:16px">
            <strong id="selectedUserName"></strong> <span style="color:var(--muted);font-size:12.5px" id="selectedUserMeta"></span>
            <button type="button" class="btn sm outline" style="float:right" onclick="clearSelectedUser()">Change</button>
        </div>

        <form id="existingUserForm" onsubmit="return submitAssignExisting(event)">
            <input type="hidden" name="user_id" id="existingUserId">
            <div class="form-grid">
                <div class="form-group">
                    <label>Department</label>
                    <input type="text" name="department" list="deptList" placeholder="e.g. Store Operations">
                </div>
                <div class="form-group full">
                    <label>Roles * <span style="color:var(--muted);font-weight:400">(select one or more)</span></label>
                    <div class="role-check-grid">
                        <?php foreach ($roles as $r): ?>
                            <label class="role-check-item">
                                <input type="checkbox" name="role_ids[]" value="<?php echo $r['id']; ?>" <?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? 'disabled' : ''; ?>>
                                <?php echo htmlspecialchars($r['role_name']); ?><?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? ' <span style="color:var(--muted)">(Super Admin only)</span>' : ''; ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <select name="status">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Access Start Date</label>
                    <input type="date" name="access_start_date" value="<?php echo date('Y-m-d'); ?>">
                </div>
                <div class="form-group full">
                    <label>Access Expiry Date</label>
                    <input type="date" name="access_expiry_date">
                    <div class="hint">Leave blank for no expiry (permanent access).</div>
                </div>
            </div>
            <div class="hint" style="margin-bottom:14px">They'll log into the Admin Panel using their existing username/mobile and password — no new password is created. If their admin access is ever removed later, their original account type is automatically restored.</div>
            <div id="existingFormErr" class="err" style="display:none;margin-bottom:14px"></div>
            <div class="modal-actions" style="justify-content:flex-start">
                <button type="submit" class="btn" id="existingSubmitBtn" disabled><i class="fa-solid fa-user-plus"></i> Give Admin Access</button>
                <a href="team_members.php" class="btn outline">Cancel</a>
            </div>
        </form>
    </div>

    <!-- ===================== NEW-PERSON MODE ===================== -->
    <div id="newUserBlock">
    <form id="addTeamForm" onsubmit="return submitAddTeam(event)" enctype="multipart/form-data">
        <div class="form-grid">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required>
            </div>
            <div class="form-group">
                <label>Username *</label>
                <input type="text" name="username" required pattern="[A-Za-z0-9_.@]{3,30}">
                <div class="hint">Used to log into the Admin Panel.</div>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Mobile Number *</label>
                <input type="tel" name="mobile" required pattern="[0-9]{10}" maxlength="10">
            </div>
            <div class="form-group">
                <label>Password *</label>
                <input type="password" name="password" id="pw1" required minlength="8">
                <div class="hint">Minimum 8 characters, letters + numbers.</div>
            </div>
            <div class="form-group">
                <label>Confirm Password *</label>
                <input type="password" name="confirm_password" id="pw2" required minlength="8">
                <div class="err" id="pwErr" style="display:none">Passwords do not match.</div>
            </div>
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" list="deptList" placeholder="e.g. Store Operations">
                <datalist id="deptList">
                    <?php foreach ($departments as $d): ?><option value="<?php echo htmlspecialchars($d['department']); ?>"><?php endforeach; ?>
                </datalist>
            </div>
            <div class="form-group full">
                <label>Roles * <span style="color:var(--muted);font-weight:400">(select one or more)</span></label>
                <div class="role-check-grid">
                    <?php foreach ($roles as $r): ?>
                        <label class="role-check-item">
                            <input type="checkbox" name="role_ids[]" value="<?php echo $r['id']; ?>" <?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? 'disabled' : ''; ?>>
                            <?php echo htmlspecialchars($r['role_name']); ?><?php echo ($r['role_slug']==='super_admin' && !isSuperAdmin()) ? ' <span style="color:var(--muted)">(Super Admin only)</span>' : ''; ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="form-group">
                <label>Account Status</label>
                <select name="status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div class="form-group">
                <label>Access Start Date</label>
                <input type="date" name="access_start_date" value="<?php echo date('Y-m-d'); ?>">
            </div>
            <div class="form-group">
                <label>Access Expiry Date</label>
                <input type="date" name="access_expiry_date">
                <div class="hint">Leave blank for no expiry (permanent access).</div>
            </div>
            <div class="form-group full">
                <label>Profile Photo</label>
                <input type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp">
                <div class="hint">JPG, PNG or WEBP, under 3MB.</div>
            </div>
        </div>
        <div id="formErr" class="err" style="display:none;margin-bottom:14px"></div>
        <div class="modal-actions" style="justify-content:flex-start">
            <button type="submit" class="btn"><i class="fa-solid fa-user-plus"></i> Add Team Member</button>
            <a href="team_members.php" class="btn outline">Cancel</a>
        </div>
    </form>
    </div>
</div>

<script>
function setMode(mode){
    const newBtn = document.getElementById('modeNewBtn');
    const exBtn = document.getElementById('modeExistingBtn');
    if (mode === 'existing') {
        document.getElementById('existingUserBlock').style.display = 'block';
        document.getElementById('newUserBlock').style.display = 'none';
        newBtn.classList.add('outline'); newBtn.classList.remove('btn');
        newBtn.className = 'btn sm outline';
        exBtn.className = 'btn sm';
    } else {
        document.getElementById('existingUserBlock').style.display = 'none';
        document.getElementById('newUserBlock').style.display = 'block';
        newBtn.className = 'btn sm';
        exBtn.className = 'btn sm outline';
    }
}

let userSearchTimer = null;
document.getElementById('userSearchInput').addEventListener('input', function(){
    const q = this.value.trim();
    clearTimeout(userSearchTimer);
    const resultsBox = document.getElementById('userSearchResults');
    if (q.length < 2) { resultsBox.style.display = 'none'; resultsBox.innerHTML = ''; return; }
    userSearchTimer = setTimeout(async () => {
        const fd = new FormData();
        fd.append('action', 'search_users');
        fd.append('q', q);
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        resultsBox.innerHTML = '';
        if (data.success && data.users.length) {
            data.users.forEach(u => {
                const row = document.createElement('div');
                row.style.cssText = 'padding:10px 14px;border-bottom:1px solid var(--border);cursor:pointer;font-size:13px';
                const already = !!u.existing_member_id;
                row.innerHTML = '<strong>' + escapeHtml(u.full_name) + '</strong> ' +
                    '<span style="color:var(--muted)">— ' + escapeHtml(u.mobile || u.email || '') + ' · ' + escapeHtml(u.role || 'customer') + '</span>' +
                    (already ? ' <span class="tag pending" style="margin-left:6px">Already a team member</span>' : '');
                if (!already) {
                    row.addEventListener('click', () => selectUser(u));
                    row.addEventListener('mouseenter', () => row.style.background = 'var(--bg-soft)');
                    row.addEventListener('mouseleave', () => row.style.background = '');
                } else {
                    row.style.opacity = '0.55';
                    row.style.cursor = 'not-allowed';
                }
                resultsBox.appendChild(row);
            });
            resultsBox.style.display = 'block';
        } else {
            resultsBox.innerHTML = '<div style="padding:10px 14px;color:var(--muted);font-size:13px">No matching users found.</div>';
            resultsBox.style.display = 'block';
        }
    }, 300);
});

function escapeHtml(s){ const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; }

function selectUser(u){
    document.getElementById('existingUserId').value = u.id;
    document.getElementById('selectedUserName').textContent = u.full_name;
    document.getElementById('selectedUserMeta').textContent = (u.mobile || u.email || '') + ' · currently: ' + (u.role || 'customer');
    document.getElementById('selectedUserBox').style.display = 'block';
    document.getElementById('userSearchInput').value = '';
    document.getElementById('userSearchInput').style.display = 'none';
    document.getElementById('userSearchResults').style.display = 'none';
    document.getElementById('existingSubmitBtn').disabled = false;
}
function clearSelectedUser(){
    document.getElementById('existingUserId').value = '';
    document.getElementById('selectedUserBox').style.display = 'none';
    document.getElementById('userSearchInput').style.display = 'block';
    document.getElementById('existingSubmitBtn').disabled = true;
}

async function submitAssignExisting(e){
    e.preventDefault();
    const form = document.getElementById('existingUserForm');
    const fd = new FormData(form);
    fd.append('action', 'assign_existing');
    const errBox = document.getElementById('existingFormErr');
    errBox.style.display = 'none';
    if (!fd.get('user_id')) { errBox.textContent = 'Please search and select a user first.'; errBox.style.display = 'block'; return false; }
    if (!fd.getAll('role_ids[]').length) { errBox.textContent = 'Please select at least one role.'; errBox.style.display = 'block'; return false; }
    const btn = document.getElementById('existingSubmitBtn');
    btn.disabled = true;
    try {
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            showToast('Admin access granted.');
            setTimeout(()=> location.href = 'team_members.php', 800);
        } else {
            errBox.textContent = data.error || 'Could not assign admin access.';
            errBox.style.display = 'block';
            btn.disabled = false;
        }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
        btn.disabled = false;
    }
    return false;
}

document.getElementById('pw2').addEventListener('input', function(){
    const err = document.getElementById('pwErr');
    err.style.display = (this.value && this.value !== document.getElementById('pw1').value) ? 'block' : 'none';
});

async function submitAddTeam(e){
    e.preventDefault();
    const form = document.getElementById('addTeamForm');
    const fd = new FormData(form);
    fd.append('action', 'create');
    const errBox = document.getElementById('formErr');
    errBox.style.display = 'none';

    if (fd.get('password') !== fd.get('confirm_password')) {
        errBox.textContent = 'Passwords do not match.';
        errBox.style.display = 'block';
        return false;
    }
    if (!fd.getAll('role_ids[]').length) {
        errBox.textContent = 'Please select at least one role.';
        errBox.style.display = 'block';
        return false;
    }

    const submitBtn = form.querySelector('button[type=submit]');
    submitBtn.disabled = true;
    try {
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            showToast('Team member added successfully.');
            setTimeout(()=> location.href = 'team_members.php', 800);
        } else {
            errBox.textContent = data.error || 'Could not add team member.';
            errBox.style.display = 'block';
        }
    } catch (err) {
        errBox.textContent = 'Network error — please try again.';
        errBox.style.display = 'block';
    }
    submitBtn.disabled = false;
    return false;
}
</script>
<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
