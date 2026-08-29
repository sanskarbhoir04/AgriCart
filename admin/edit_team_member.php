<?php
// =====================================================================
// admin/edit_team_member.php — Edit an existing team member's profile
// fields (name, email, mobile, department, photo, access dates).
// Role changes happen via the Change Role modal on team_members.php,
// not here — keeps this form focused and the audit trail explicit.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('team.edit');

$memberId = (int)($_GET['id'] ?? 0);
$stmt = $conn->prepare("SELECT m.*, u.full_name, u.username, u.email, u.mobile, u.profile_photo, r.role_name
                          FROM admin_team_members m
                          JOIN users u ON u.id = m.user_id
                          JOIN admin_roles r ON r.id = m.role_id
                         WHERE m.id = ?");
$stmt->bind_param('i', $memberId);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();

if (!$member) {
    header('Location: team_members.php');
    exit;
}

$pageTitle = 'Edit Team Member';
$activeTeamTab = 'members';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="card" style="max-width:760px">
    <div class="card-head">
        <h2>Edit Team Member</h2>
        <span class="role-badge"><i class="fa-solid fa-user-shield"></i> <?php echo htmlspecialchars($member['role_name']); ?></span>
    </div>
    <form id="editTeamForm" onsubmit="return submitEditTeam(event)" enctype="multipart/form-data">
        <input type="hidden" name="member_id" value="<?php echo $member['id']; ?>">
        <div class="form-grid">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="full_name" required value="<?php echo htmlspecialchars($member['full_name']); ?>">
            </div>
            <div class="form-group">
                <label>Username</label>
                <input type="text" value="<?php echo htmlspecialchars($member['username'] ?: ''); ?>" disabled>
            </div>
            <div class="form-group">
                <label>Email *</label>
                <input type="email" name="email" required value="<?php echo htmlspecialchars($member['email']); ?>">
            </div>
            <div class="form-group">
                <label>Mobile Number *</label>
                <input type="tel" name="mobile" required pattern="[0-9]{10}" maxlength="10" value="<?php echo htmlspecialchars($member['mobile']); ?>">
            </div>
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($member['department'] ?: ''); ?>">
            </div>
            <div class="form-group">
                <label>Status</label>
                <input type="text" value="<?php echo ucfirst($member['status']); ?>" disabled>
                <div class="hint">Change status from the Team Members list.</div>
            </div>
            <div class="form-group">
                <label>Access Start Date</label>
                <input type="date" name="access_start_date" value="<?php echo htmlspecialchars($member['access_start_date'] ?: ''); ?>">
            </div>
            <div class="form-group">
                <label>Access Expiry Date</label>
                <input type="date" name="access_expiry_date" value="<?php echo htmlspecialchars($member['access_expiry_date'] ?: ''); ?>">
            </div>
            <div class="form-group full">
                <label>Profile Photo</label>
                <?php if (!empty($member['profile_photo'])): ?>
                    <div style="margin-bottom:8px"><img src="../<?php echo htmlspecialchars($member['profile_photo']); ?>" style="width:56px;height:56px;border-radius:50%;object-fit:cover"></div>
                <?php endif; ?>
                <input type="file" name="profile_photo" accept="image/png,image/jpeg,image/webp">
                <div class="hint">Leave empty to keep the current photo.</div>
            </div>
        </div>
        <div id="formErr" class="err" style="display:none;margin-bottom:14px"></div>
        <div class="modal-actions" style="justify-content:flex-start">
            <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Save Changes</button>
            <a href="team_members.php" class="btn outline">Cancel</a>
        </div>
    </form>
</div>

<script>
async function submitEditTeam(e){
    e.preventDefault();
    const form = document.getElementById('editTeamForm');
    const fd = new FormData(form);
    fd.append('action', 'update');
    const errBox = document.getElementById('formErr');
    errBox.style.display = 'none';
    const submitBtn = form.querySelector('button[type=submit]');
    submitBtn.disabled = true;
    try {
        const res = await fetch('actions/team_action.php', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            showToast('Team member updated.');
            setTimeout(()=> location.href = 'team_members.php', 800);
        } else {
            errBox.textContent = data.error || 'Could not update team member.';
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
