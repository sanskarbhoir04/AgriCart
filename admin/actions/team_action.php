<?php
// =====================================================================
// admin/actions/team_action.php — Add / Edit / Change Role / Activate /
// Deactivate / Suspend / Reset Password / Remove Access for Admin Panel
// team members. Every branch re-checks permission on the server; never
// trust the frontend. Every query uses prepared statements.
// =====================================================================
require_once __DIR__ . '/../../includes/db.php';
agri_session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../includes/permissions.php';
require_once __DIR__ . '/../includes/team_roles_schema.php';
team_roles_bootstrap_schema($conn);

$response = ['success' => false];

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Not authorized.']);
    exit;
}
csrf_require('json');

$action = $_POST['action'] ?? '';

// ---------------------------------------------------------------------
function agri_valid_mobile(string $m): bool { return (bool)preg_match('/^[0-9]{10}$/', $m); }
function agri_strong_password(string $p): bool { return strlen($p) >= 8 && preg_match('/[A-Za-z]/', $p) && preg_match('/[0-9]/', $p); }
function agri_valid_username(string $u): bool { return (bool)preg_match('/^[A-Za-z0-9_.@]{3,30}$/', $u); }
// ---------------------------------------------------------------------

if ($action === 'search_users') {
    requirePermission('team.create');

    $q = trim($_POST['q'] ?? '');
    if (strlen($q) < 2) { echo json_encode(['success' => true, 'users' => []]); exit; }

    $like = '%' . $q . '%';
    $stmt = $conn->prepare(
        "SELECT u.id, u.full_name, u.mobile, u.email, u.role,
                (SELECT id FROM admin_team_members m WHERE m.user_id = u.id) AS existing_member_id
           FROM users u
          WHERE (u.full_name LIKE ? OR u.mobile LIKE ? OR u.email LIKE ? OR u.username LIKE ?)
          ORDER BY u.full_name
          LIMIT 20"
    );
    $stmt->bind_param('ssss', $like, $like, $like, $like);
    $stmt->execute();
    $users = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    echo json_encode(['success' => true, 'users' => $users]);
    exit;
}

if ($action === 'assign_existing') {
    requirePermission('team.create');

    $userId    = (int)($_POST['user_id'] ?? 0);
    $roleIds   = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['role_ids'] ?? [])), fn($v) => $v > 0)));
    $department= trim($_POST['department'] ?? '');
    $status    = in_array($_POST['status'] ?? 'active', ['active','inactive']) ? $_POST['status'] : 'active';
    $startDate = trim($_POST['access_start_date'] ?? '') ?: date('Y-m-d');
    $expiryDate= trim($_POST['access_expiry_date'] ?? '') ?: null;

    if ($userId <= 0 || empty($roleIds)) {
        $response['error'] = 'Please choose a user and at least one role.';
        echo json_encode($response); exit;
    }

    $userStmt = $conn->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $userStmt->bind_param('i', $userId);
    $userStmt->execute();
    $targetUser = $userStmt->get_result()->fetch_assoc();
    if (!$targetUser) { $response['error'] = 'User not found.'; echo json_encode($response); exit; }

    $dupStmt = $conn->prepare("SELECT id FROM admin_team_members WHERE user_id = ?");
    $dupStmt->bind_param('i', $userId);
    $dupStmt->execute();
    if ($dupStmt->get_result()->fetch_assoc()) {
        $response['error'] = 'This user is already an admin team member.';
        echo json_encode($response); exit;
    }

    $roleRows = [];
    $roleCheck = $conn->prepare("SELECT id, role_slug, role_name, is_active FROM admin_roles WHERE id = ?");
    foreach ($roleIds as $rid) {
        $roleCheck->bind_param('i', $rid);
        $roleCheck->execute();
        $row = $roleCheck->get_result()->fetch_assoc();
        if (!$row || !$row['is_active']) {
            $response['error'] = 'One of the selected roles is invalid or inactive.';
            echo json_encode($response); exit;
        }
        $roleRows[] = $row;
    }
    foreach ($roleRows as $row) {
        if ($row['role_slug'] === 'super_admin' && !isSuperAdmin()) {
            requirePermission('__super_admin_only__');
        }
    }
    $roleRow = $roleRows[0];
    $roleId  = $roleRow['id'];
    $roleNames = implode(', ', array_column($roleRows, 'role_name'));

    $conn->begin_transaction();
    try {
        // Remember their current storefront role so "Remove Access" can
        // restore it later, then switch them to 'admin' so admin/auth.php's
        // login gate accepts them.
        $previousRole = $targetUser['role'] ?? null;
        $upd = $conn->prepare("UPDATE users SET role = 'admin' WHERE id = ?");
        $upd->bind_param('i', $userId);
        if (!$upd->execute()) {
            error_log('admin/actions/team_action.php: promote-to-admin failed: ' . $conn->error);
            throw new \Exception('Could not update the user account. Please try again.');
        }

        $assignedBy = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
        $ins = $conn->prepare(
            "INSERT INTO admin_team_members (user_id, role_id, department, status, previous_site_role, assigned_by, access_start_date, access_expiry_date)
             VALUES (?,?,?,?,?,?,?,?)"
        );
        $ins->bind_param('iisssiss', $userId, $roleId, $department, $status, $previousRole, $assignedBy, $startDate, $expiryDate);
        if (!$ins->execute()) {
            error_log('admin/actions/team_action.php: team member insert failed: ' . $conn->error);
            throw new \Exception('Could not create the team member record. Please try again.');
        }
        $memberId = $conn->insert_id;

        $roleIns = $conn->prepare("INSERT IGNORE INTO admin_team_member_roles (member_id, role_id) VALUES (?, ?)");
        foreach ($roleIds as $rid) {
            $roleIns->bind_param('ii', $memberId, $rid);
            $roleIns->execute();
        }

        $conn->commit();

        logAdminActivity('team_member_promoted', 'team', $userId, ['site_role' => $previousRole],
            ['admin_role' => $roleNames],
            'Gave "' . $targetUser['full_name'] . '" Admin Panel access as ' . $roleNames);
        notifySuperAdmin('team_added', 'Existing user promoted to admin', $targetUser['full_name'] . ' was given Admin Panel access as ' . $roleNames . '.');

        $response['success'] = true;
    } catch (\Throwable $e) {
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($action === 'create') {
    requirePermission('team.create');

    $fullName  = trim($_POST['full_name'] ?? '');
    $username  = trim($_POST['username'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $mobile    = trim($_POST['mobile'] ?? '');
    $password  = $_POST['password'] ?? '';
    $confirm   = $_POST['confirm_password'] ?? '';
    $department= trim($_POST['department'] ?? '');
    $roleIds   = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['role_ids'] ?? [])), fn($v) => $v > 0)));
    $status    = in_array($_POST['status'] ?? 'active', ['active','inactive']) ? $_POST['status'] : 'active';
    $startDate = trim($_POST['access_start_date'] ?? '') ?: date('Y-m-d');
    $expiryDate= trim($_POST['access_expiry_date'] ?? '') ?: null;

    if ($fullName === '' || $username === '' || $email === '' || $mobile === '' || empty($roleIds)) {
        $response['error'] = 'Please fill in all required fields and select at least one role.';
        echo json_encode($response); exit;
    }
    if (!agri_valid_username($username)) {
        $response['error'] = 'Username must be 3-30 characters and can only contain letters, numbers, underscore, period, or @.';
        echo json_encode($response); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['error'] = 'Please enter a valid email address.';
        echo json_encode($response); exit;
    }
    if (!agri_valid_mobile($mobile)) {
        $response['error'] = 'Please enter a valid 10-digit mobile number.';
        echo json_encode($response); exit;
    }
    if ($password === '' || $password !== $confirm) {
        $response['error'] = 'Passwords do not match.';
        echo json_encode($response); exit;
    }
    if (!agri_strong_password($password)) {
        $response['error'] = 'Password must be at least 8 characters and include letters and numbers.';
        echo json_encode($response); exit;
    }

    // Validate every selected role, and note the first one as "primary"
    // (used for admin_team_members.role_id / display purposes).
    $roleRows = [];
    $roleCheck = $conn->prepare("SELECT id, role_slug, role_name, is_active FROM admin_roles WHERE id = ?");
    foreach ($roleIds as $rid) {
        $roleCheck->bind_param('i', $rid);
        $roleCheck->execute();
        $row = $roleCheck->get_result()->fetch_assoc();
        if (!$row || !$row['is_active']) {
            $response['error'] = 'One of the selected roles is invalid or inactive.';
            echo json_encode($response); exit;
        }
        $roleRows[] = $row;
    }
    // Only a Super Admin can assign the Super Admin role to someone.
    foreach ($roleRows as $row) {
        if ($row['role_slug'] === 'super_admin' && !isSuperAdmin()) {
            requirePermission('team.assign_role'); // will 403 unless somehow granted
        }
    }
    $roleRow = $roleRows[0]; // primary role, for activity log + INSERT below
    $roleId  = $roleRow['id'];
    $roleNames = implode(', ', array_column($roleRows, 'role_name'));

    // Unique checks.
    $dup = $conn->prepare("SELECT id FROM users WHERE username = ? OR email = ? OR mobile = ? LIMIT 1");
    $dup->bind_param('sss', $username, $email, $mobile);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $response['error'] = 'Username, email, or mobile number is already in use.';
        echo json_encode($response); exit;
    }

    // Profile photo (optional).
    $photoPath = null;
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        // MIME is verified from the actual file bytes (not the client-supplied
        // filename/extension) so a renamed non-image file can't smuggle through
        // — same guarantee as includes/secure_upload.php uses elsewhere.
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($_FILES['profile_photo']['size'] > 3 * 1024 * 1024 || !is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
            $response['error'] = 'Profile photo must be JPG/PNG/WEBP under 3MB.';
            echo json_encode($response); exit;
        }
        $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
        $mime = $finfo ? finfo_file($finfo, $_FILES['profile_photo']['tmp_name']) : null;
        if ($finfo) { finfo_close($finfo); }
        if (!$mime || !isset($allowedMimes[$mime]) || @getimagesize($_FILES['profile_photo']['tmp_name']) === false) {
            $response['error'] = 'Profile photo must be a real JPG/PNG/WEBP image under 3MB.';
            echo json_encode($response); exit;
        }
        $ext = $allowedMimes[$mime];
        $destDir = __DIR__ . '/../../assets/images/team/';
        if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
        $fname = 'team_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destDir . $fname)) {
            $photoPath = 'assets/images/team/' . $fname;
        }
    }

    $conn->begin_transaction();
    try {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $ins = $conn->prepare("INSERT INTO users (full_name, username, email, mobile, password, role, profile_photo) VALUES (?,?,?,?,?, 'admin', ?)");
        $ins->bind_param('ssssss', $fullName, $username, $email, $mobile, $hash, $photoPath);
        if (!$ins->execute()) {
            error_log('admin/actions/team_action.php: new user insert failed: ' . $conn->error);
            throw new \Exception('Could not create the user account. Please try again.');
        }
        $userId = $conn->insert_id;

        $assignedBy = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
        $ins2 = $conn->prepare(
            "INSERT INTO admin_team_members (user_id, role_id, department, status, assigned_by, access_start_date, access_expiry_date)
             VALUES (?,?,?,?,?,?,?)"
        );
        $ins2->bind_param('iisssss', $userId, $roleId, $department, $status, $assignedBy, $startDate, $expiryDate);
        if (!$ins2->execute()) {
            error_log('admin/actions/team_action.php: team member record insert (new user path) failed: ' . $conn->error);
            throw new \Exception('Could not create the team member record. Please try again.');
        }
        $memberId = $conn->insert_id;

        $roleIns = $conn->prepare("INSERT IGNORE INTO admin_team_member_roles (member_id, role_id) VALUES (?, ?)");
        foreach ($roleIds as $rid) {
            $roleIns->bind_param('ii', $memberId, $rid);
            $roleIns->execute();
        }

        $conn->commit();

        logAdminActivity('team_member_created', 'team', $userId, null,
            ['name' => $fullName, 'roles' => $roleNames],
            'Added "' . $fullName . '" as ' . $roleNames);
        notifySuperAdmin('team_added', 'New admin team member added', $fullName . ' was added as ' . $roleNames . '.');

        $response['success'] = true;
        $response['user_id'] = $userId;
    } catch (\Throwable $e) {
        $conn->rollback();
        $response['error'] = $e->getMessage();
    }
    echo json_encode($response);
    exit;
}

if ($action === 'update') {
    requirePermission('team.edit');

    $memberId  = (int)($_POST['member_id'] ?? 0);
    $fullName  = trim($_POST['full_name'] ?? '');
    $email     = trim($_POST['email'] ?? '');
    $mobile    = trim($_POST['mobile'] ?? '');
    $department= trim($_POST['department'] ?? '');
    $startDate = trim($_POST['access_start_date'] ?? '') ?: null;
    $expiryDate= trim($_POST['access_expiry_date'] ?? '') ?: null;

    if ($memberId <= 0 || $fullName === '' || $email === '' || $mobile === '') {
        $response['error'] = 'Please fill in all required fields.';
        echo json_encode($response); exit;
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !agri_valid_mobile($mobile)) {
        $response['error'] = 'Please enter a valid email and 10-digit mobile number.';
        echo json_encode($response); exit;
    }

    $find = $conn->prepare("SELECT * FROM admin_team_members WHERE id = ?");
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }

    $dup = $conn->prepare("SELECT id FROM users WHERE (email = ? OR mobile = ?) AND id <> ? LIMIT 1");
    $dup->bind_param('ssi', $email, $mobile, $member['user_id']);
    $dup->execute();
    if ($dup->get_result()->fetch_assoc()) {
        $response['error'] = 'Email or mobile number already used by another account.';
        echo json_encode($response); exit;
    }

    $photoPath = null;
    if (!empty($_FILES['profile_photo']['name']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        // Same real-bytes MIME check as the add-member path above (not the
        // client-supplied filename extension).
        $allowedMimes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        if ($_FILES['profile_photo']['size'] <= 3 * 1024 * 1024 && is_uploaded_file($_FILES['profile_photo']['tmp_name'])) {
            $finfo = function_exists('finfo_open') ? finfo_open(FILEINFO_MIME_TYPE) : null;
            $mime = $finfo ? finfo_file($finfo, $_FILES['profile_photo']['tmp_name']) : null;
            if ($finfo) { finfo_close($finfo); }
            if ($mime && isset($allowedMimes[$mime]) && @getimagesize($_FILES['profile_photo']['tmp_name']) !== false) {
                $ext = $allowedMimes[$mime];
                $destDir = __DIR__ . '/../../assets/images/team/';
                if (!is_dir($destDir)) { mkdir($destDir, 0755, true); }
                $fname = 'team_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($_FILES['profile_photo']['tmp_name'], $destDir . $fname)) {
                    $photoPath = 'assets/images/team/' . $fname;
                }
            }
        }
    }

    if ($photoPath) {
        $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, mobile=?, profile_photo=? WHERE id=?");
        $upd->bind_param('ssssi', $fullName, $email, $mobile, $photoPath, $member['user_id']);
    } else {
        $upd = $conn->prepare("UPDATE users SET full_name=?, email=?, mobile=? WHERE id=?");
        $upd->bind_param('sssi', $fullName, $email, $mobile, $member['user_id']);
    }
    $upd->execute();

    $upd2 = $conn->prepare("UPDATE admin_team_members SET department=?, access_start_date=?, access_expiry_date=? WHERE id=?");
    $upd2->bind_param('sssi', $department, $startDate, $expiryDate, $memberId);
    $upd2->execute();

    // Optional: the popup's edit form can also submit role_ids[] in the
    // same request, but only apply it if the caller actually has
    // permission to assign roles — never trust the presence of the
    // field alone.
    if (isset($_POST['role_ids']) && hasPermission('team.assign_role')) {
        $roleIds = array_values(array_unique(array_filter(array_map('intval', (array)$_POST['role_ids']), fn($v) => $v > 0)));
        if (!empty($roleIds)) {
            $validRoles = [];
            $roleCheck = $conn->prepare("SELECT role_slug FROM admin_roles WHERE id = ? AND is_active = 1");
            foreach ($roleIds as $rid) {
                $roleCheck->bind_param('i', $rid);
                $roleCheck->execute();
                $row = $roleCheck->get_result()->fetch_assoc();
                if ($row) {
                    if ($row['role_slug'] === 'super_admin' && !isSuperAdmin()) { continue; }
                    $validRoles[] = $rid;
                }
            }
            if (!empty($validRoles)) {
                team_roles_sync_for_member($conn, $memberId, $validRoles);
            }
        }
    }

    logAdminActivity('team_member_updated', 'team', $member['user_id'], null, ['name' => $fullName], 'Updated team member "' . $fullName . '"');
    $response['success'] = true;
    echo json_encode($response);
    exit;
}

if ($action === 'get_member') {
    requirePermission('team.view');

    $memberId = (int)($_POST['member_id'] ?? $_GET['member_id'] ?? 0);
    $find = $conn->prepare(
        "SELECT m.*, u.full_name, u.username, u.email, u.mobile, u.profile_photo
           FROM admin_team_members m
           JOIN users u ON u.id = m.user_id
          WHERE m.id = ?"
    );
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }

    $roleIds = team_roles_get_for_member($conn, $memberId);
    $roleNames = [];
    if (!empty($roleIds)) {
        $placeholders = implode(',', array_fill(0, count($roleIds), '?'));
        $rs = $conn->prepare("SELECT role_name FROM admin_roles WHERE id IN ($placeholders)");
        $rs->bind_param(str_repeat('i', count($roleIds)), ...$roleIds);
        $rs->execute();
        $res = $rs->get_result();
        while ($row = $res->fetch_assoc()) { $roleNames[] = $row['role_name']; }
    }

    $response['success'] = true;
    $response['member'] = [
        'id'                 => (int)$member['id'],
        'full_name'          => $member['full_name'],
        'username'           => $member['username'],
        'email'              => $member['email'],
        'mobile'             => $member['mobile'],
        'department'         => $member['department'],
        'status'             => $member['status'],
        'profile_photo'      => $member['profile_photo'],
        'assigned_at'        => $member['assigned_at'],
        'access_start_date'  => $member['access_start_date'],
        'access_expiry_date' => $member['access_expiry_date'],
        'last_login'         => $member['last_login'],
        'role_ids'           => $roleIds,
        'role_names'         => $roleNames,
    ];
    echo json_encode($response);
    exit;
}

if ($action === 'change_role') {
    requirePermission('team.assign_role');

    $memberId = (int)($_POST['member_id'] ?? 0);
    $roleIds  = array_values(array_unique(array_filter(array_map('intval', (array)($_POST['role_ids'] ?? [])), fn($v) => $v > 0)));
    if ($memberId <= 0 || empty($roleIds)) { $response['error'] = 'Please select at least one role.'; echo json_encode($response); exit; }

    $find = $conn->prepare("SELECT m.*, r.role_name AS old_role_name FROM admin_team_members m JOIN admin_roles r ON r.id = m.role_id WHERE m.id = ?");
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }
    $oldRoleIds = team_roles_get_for_member($conn, $memberId);
    $oldRoleNamesList = [];
    if (!empty($oldRoleIds)) {
        $namesStmt = $conn->prepare("SELECT role_name FROM admin_roles WHERE id = ?");
        foreach ($oldRoleIds as $oldRid) {
            $namesStmt->bind_param('i', $oldRid);
            $namesStmt->execute();
            $nameRow = $namesStmt->get_result()->fetch_assoc();
            if ($nameRow) { $oldRoleNamesList[] = $nameRow['role_name']; }
        }
    }
    $oldRoleNames = !empty($oldRoleNamesList) ? implode(', ', $oldRoleNamesList) : $member['old_role_name'];

    $newRoleRows = [];
    $roleCheck = $conn->prepare("SELECT role_slug, role_name FROM admin_roles WHERE id = ? AND is_active = 1");
    foreach ($roleIds as $rid) {
        $roleCheck->bind_param('i', $rid);
        $roleCheck->execute();
        $row = $roleCheck->get_result()->fetch_assoc();
        if (!$row) { $response['error'] = 'One of the selected roles is invalid.'; echo json_encode($response); exit; }
        $newRoleRows[] = $row;
    }
    foreach ($newRoleRows as $row) {
        if ($row['role_slug'] === 'super_admin' && !isSuperAdmin()) {
            requirePermission('__super_admin_only__'); // always 403s for non-super-admins
        }
    }
    $newRoleNames = implode(', ', array_column($newRoleRows, 'role_name'));

    team_roles_sync_for_member($conn, $memberId, $roleIds);

    logAdminActivity('team_role_changed', 'team', $member['user_id'],
        $oldRoleNames, $newRoleNames,
        'Role(s) changed from "' . $oldRoleNames . '" to "' . $newRoleNames . '"');
    notifySuperAdmin('role_changed', 'Team member role changed', 'A team member\'s role(s) changed to ' . $newRoleNames . '.');

    $response['success'] = true;
    echo json_encode($response);
    exit;
}

if (in_array($action, ['activate', 'deactivate', 'suspend'], true)) {
    requirePermission('team.disable');

    $memberId = (int)($_POST['member_id'] ?? 0);
    $statusMap = ['activate' => 'active', 'deactivate' => 'inactive', 'suspend' => 'suspended'];
    $newStatus = $statusMap[$action];

    $find = $conn->prepare("SELECT m.*, r.role_slug FROM admin_team_members m JOIN admin_roles r ON r.id = m.role_id WHERE m.id = ?");
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }

    if ($member['role_slug'] === 'super_admin' && $newStatus !== 'active' && !isSuperAdmin()) {
        requirePermission('__super_admin_only__');
    }

    $upd = $conn->prepare("UPDATE admin_team_members SET status = ?, failed_login_count = 0, locked_until = NULL WHERE id = ?");
    $upd->bind_param('si', $newStatus, $memberId);
    $upd->execute();

    logAdminActivity('team_member_' . $action . 'd', 'team', $member['user_id'], $member['status'], $newStatus,
        'Account status changed to "' . $newStatus . '"');
    if ($action === 'suspend') {
        notifySuperAdmin('account_disabled', 'Admin account suspended', 'A team member account was suspended.');
    }

    $response['success'] = true;
    echo json_encode($response);
    exit;
}

if ($action === 'reset_password') {
    requirePermission('team.edit');

    $memberId    = (int)($_POST['member_id'] ?? 0);
    $newPassword = $_POST['new_password'] ?? '';
    $confirm     = $_POST['confirm_password'] ?? '';

    if ($newPassword === '' || $newPassword !== $confirm) {
        $response['error'] = 'Passwords do not match.'; echo json_encode($response); exit;
    }
    if (!agri_strong_password($newPassword)) {
        $response['error'] = 'Password must be at least 8 characters and include letters and numbers.';
        echo json_encode($response); exit;
    }

    $find = $conn->prepare("SELECT user_id FROM admin_team_members WHERE id = ?");
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }

    $hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $upd = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $upd->bind_param('si', $hash, $member['user_id']);
    $upd->execute();

    logAdminActivity('team_member_password_reset', 'team', $member['user_id'], null, null, 'Password was reset by an administrator.');
    $response['success'] = true;
    echo json_encode($response);
    exit;
}

if ($action === 'remove') {
    requirePermission('team.delete');

    $memberId = (int)($_POST['member_id'] ?? 0);
    $find = $conn->prepare("SELECT m.*, r.role_slug, u.full_name FROM admin_team_members m JOIN admin_roles r ON r.id = m.role_id JOIN users u ON u.id = m.user_id WHERE m.id = ?");
    $find->bind_param('i', $memberId);
    $find->execute();
    $member = $find->get_result()->fetch_assoc();
    if (!$member) { $response['error'] = 'Team member not found.'; echo json_encode($response); exit; }

    if ($member['role_slug'] === 'super_admin' && !isSuperAdmin()) {
        requirePermission('__super_admin_only__');
    }
    if ((int)($_SESSION['admin_member_id'] ?? 0) === $memberId) {
        $response['error'] = 'You cannot remove your own admin access.';
        echo json_encode($response); exit;
    }

    // Keep activity history: only mark inactive rather than hard-deleting
    // the team-member row (deleting it would orphan admin_activity_logs
    // references and lose their history, which the spec requires we keep).
    $upd = $conn->prepare("UPDATE admin_team_members SET status = 'inactive' WHERE id = ?");
    $upd->bind_param('i', $memberId);
    $upd->execute();

    // If this person was promoted from an existing storefront account
    // (farmer/seller/buyer/expert/customer), give that role back to them
    // now that their admin access is gone, so they aren't stuck as
    // role='admin' with no way to use the storefront as themselves.
    if (!empty($member['previous_site_role'])) {
        $restoreRole = $member['previous_site_role'];
        $updRole = $conn->prepare("UPDATE users SET role = ? WHERE id = ?");
        $updRole->bind_param('si', $restoreRole, $member['user_id']);
        $updRole->execute();
    }

    logAdminActivity('team_member_removed', 'team', $member['user_id'], null, null,
        'Removed Admin Panel access for "' . $member['full_name'] . '"' . (!empty($member['previous_site_role']) ? ' (restored their "' . $member['previous_site_role'] . '" account)' : ''));
    notifySuperAdmin('team_removed', 'Team member access removed', $member['full_name'] . '\'s admin access was removed.');

    $response['success'] = true;
    echo json_encode($response);
    exit;
}

$response['error'] = 'Unknown action.';
echo json_encode($response);
