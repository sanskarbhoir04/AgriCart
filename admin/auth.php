<?php
// =====================================================================
// admin/auth.php — Handles BOTH login modes from the same form:
//   - login_type=user  → normal site login, identified by MOBILE NUMBER
//                         (unchanged — this is what every farmer/buyer
//                         already uses across AgriCart). Redirects to
//                         the storefront homepage.
//   - login_type=admin → identified by USERNAME. Requires role='admin'.
//                         Sets full site session PLUS is_admin, so this
//                         one login gives genuine full-site access AND
//                         the admin dashboard.
// Both modes check the SAME `users` table — this is not a separate
// account system. The table needs a `username` column for admin login
// to work (see ALTER TABLE note below).
//
// RBAC UPGRADE: the admin path now also resolves the user's Admin
// Panel role + permission set (admin_team_members / admin_roles /
// admin_role_permissions / admin_user_permissions) and stores the
// resolved permission list in the session so every other admin page
// can call hasPermission() without hitting the database again. It also
// enforces account status/expiry, applies a login-attempt lockout, and
// records every attempt in admin_login_logs.
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/permissions.php';

$password  = $_POST['password'] ?? '';
$loginType = ($_POST['login_type'] ?? 'user') === 'admin' ? 'admin' : 'user';

if ($loginType === 'admin') {
    $identifier = trim($_POST['username'] ?? '');
} else {
    $identifier = trim($_POST['mobile'] ?? '');
}

if ($identifier === '' || $password === '') {
    header('Location: login.php?error=empty&type=' . $loginType);
    exit;
}

if ($loginType === 'admin') {
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR alt_username = ? LIMIT 1");
    $stmt->bind_param("ss", $identifier, $identifier);
} else {
    $stmt = $conn->prepare("SELECT * FROM users WHERE mobile = ? LIMIT 1");
    $stmt->bind_param("s", $identifier);
}
$stmt->execute();
$result = $stmt->get_result();
$user = $result ? $result->fetch_assoc() : null;

// ---------------------------------------------------------------------
// Storefront login — unchanged behaviour.
// ---------------------------------------------------------------------
if ($loginType !== 'admin') {
    if (!$user || !password_verify($password, $user['password'])) {
        header('Location: login.php?error=invalid&type=' . $loginType);
        exit;
    }

    session_regenerate_id(true);
    unset($_SESSION['is_admin'], $_SESSION['admin_id'], $_SESSION['admin_name'], $_SESSION['admin_member_id'],
          $_SESSION['admin_role_id'], $_SESSION['admin_role_slug'], $_SESSION['admin_permissions'],
          $_SESSION['admin_department'], $_SESSION['admin_scope'], $_SESSION['is_super_admin']);

    $_SESSION['user_id']   = $user['id'];
    $_SESSION['user_name'] = $user['full_name'];
    $_SESSION['user']      = $user['mobile'];
    $_SESSION['user_role'] = $user['role'] ?? 'farmer';
    header('Location: ../index.php');
    exit;
}

// ---------------------------------------------------------------------
// Admin Panel login — RBAC-aware.
// ---------------------------------------------------------------------
$ip = $_SERVER['REMOTE_ADDR'] ?? null;
$ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

function agri_admin_log_login(mysqli $conn, ?int $userId, string $status, ?string $ip, ?string $ua): void
{
    $stmt = $conn->prepare("INSERT INTO admin_login_logs (admin_user_id, login_status, ip_address, user_agent, login_time) VALUES (?,?,?,?, NOW())");
    $stmt->bind_param('isss', $userId, $status, $ip, $ua);
    $stmt->execute();
}

$MAX_ATTEMPTS = 5;
$LOCK_MINUTES = 15;

if (!$user || ($user['role'] ?? 'farmer') !== 'admin' || !password_verify($password, $user['password'])) {
    // Track failed attempts against the team-member row if we can find one,
    // so repeated bad guesses against a real admin username trigger a lock.
    if ($user && ($user['role'] ?? '') === 'admin') {
        $m = $conn->prepare("SELECT id, failed_login_count FROM admin_team_members WHERE user_id = ? LIMIT 1");
        $m->bind_param('i', $user['id']);
        $m->execute();
        $member = $m->get_result()->fetch_assoc();
        if ($member) {
            $count = (int)$member['failed_login_count'] + 1;
            if ($count >= $MAX_ATTEMPTS) {
                $upd = $conn->prepare("UPDATE admin_team_members SET failed_login_count = ?, locked_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?");
                $upd->bind_param('iii', $count, $LOCK_MINUTES, $member['id']);
                $upd->execute();
                notifySuperAdmin('login_locked', 'Admin account locked', 'Account "' . $identifier . '" was locked after ' . $count . ' failed login attempts.');
            } else {
                $upd = $conn->prepare("UPDATE admin_team_members SET failed_login_count = ? WHERE id = ?");
                $upd->bind_param('ii', $count, $member['id']);
                $upd->execute();
            }
            if ($count >= 3) {
                notifySuperAdmin('failed_login', 'Repeated failed admin login', 'Account "' . $identifier . '" has had ' . $count . ' failed login attempts.');
            }
        }
    }
    agri_admin_log_login($conn, $user['id'] ?? null, 'failed', $ip, $ua);

    if (!$user || ($user['role'] ?? 'farmer') !== 'admin') {
        header('Location: login.php?error=' . (!$user ? 'invalid' : 'notadmin'));
    } else {
        header('Location: login.php?error=invalid');
    }
    exit;
}

// Correct username + password beyond this point. Now resolve (or
// auto-provision) their Admin Panel team-member / role / permission
// record before letting them in.
$m = $conn->prepare("SELECT * FROM admin_team_members WHERE user_id = ? LIMIT 1");
$m->bind_param('i', $user['id']);
$m->execute();
$member = $m->get_result()->fetch_assoc();

if (!$member) {
    // First-time login for a pre-existing `users.role = 'admin'` account
    // that predates the RBAC system — auto-provision as Super Admin so
    // nobody who already had full access loses it.
    $roleStmt = $conn->prepare("SELECT id FROM admin_roles WHERE role_slug = 'super_admin' LIMIT 1");
    $roleStmt->execute();
    $superRole = $roleStmt->get_result()->fetch_assoc();

    if ($superRole) {
        $ins = $conn->prepare(
            "INSERT INTO admin_team_members (user_id, role_id, department, status, assigned_by, access_start_date)
             VALUES (?, ?, 'Management', 'active', ?, CURDATE())"
        );
        $ins->bind_param('iii', $user['id'], $superRole['id'], $user['id']);
        $ins->execute();

        $m2 = $conn->prepare("SELECT * FROM admin_team_members WHERE user_id = ? LIMIT 1");
        $m2->bind_param('i', $user['id']);
        $m2->execute();
        $member = $m2->get_result()->fetch_assoc();
    }
}

if (!$member) {
    // RBAC tables not migrated yet (database/admin_rbac.sql not run) —
    // fail safe rather than letting someone in with undefined permissions.
    agri_admin_log_login($conn, $user['id'], 'failed', $ip, $ua);
    header('Location: login.php?error=rbac_not_configured');
    exit;
}

// Account status checks.
if ($member['status'] === 'suspended') {
    agri_admin_log_login($conn, $user['id'], 'locked', $ip, $ua);
    header('Location: login.php?error=suspended');
    exit;
}
if ($member['status'] === 'inactive') {
    agri_admin_log_login($conn, $user['id'], 'inactive', $ip, $ua);
    header('Location: login.php?error=inactive');
    exit;
}
if (!empty($member['locked_until']) && strtotime($member['locked_until']) > time()) {
    agri_admin_log_login($conn, $user['id'], 'locked', $ip, $ua);
    header('Location: login.php?error=locked');
    exit;
}
if (!empty($member['access_start_date']) && $member['access_start_date'] > date('Y-m-d')) {
    agri_admin_log_login($conn, $user['id'], 'inactive', $ip, $ua);
    header('Location: login.php?error=not_started');
    exit;
}
if (!empty($member['access_expiry_date']) && $member['access_expiry_date'] < date('Y-m-d')) {
    $upd = $conn->prepare("UPDATE admin_team_members SET status = 'expired' WHERE id = ?");
    $upd->bind_param('i', $member['id']);
    $upd->execute();
    agri_admin_log_login($conn, $user['id'], 'expired', $ip, $ua);
    header('Location: login.php?error=expired');
    exit;
}

// Resolve role + permissions.
$roleStmt = $conn->prepare("SELECT * FROM admin_roles WHERE id = ? LIMIT 1");
$roleStmt->bind_param('i', $member['role_id']);
$roleStmt->execute();
$role = $roleStmt->get_result()->fetch_assoc();

$isSuperAdmin = $role && $role['role_slug'] === 'super_admin';
$permissions  = $isSuperAdmin ? [] : getAdminPermissions((int)$member['id']);

// Success — reset failed attempts, clear lock, stamp last login.
$upd = $conn->prepare("UPDATE admin_team_members SET failed_login_count = 0, locked_until = NULL, last_login = NOW() WHERE id = ?");
$upd->bind_param('i', $member['id']);
$upd->execute();

session_regenerate_id(true);

// Admin session only — clear out any storefront/customer session that
// might already exist in this browser so it can't leak in.
unset($_SESSION['user_id'], $_SESSION['user_name'], $_SESSION['user'], $_SESSION['user_role']);

$_SESSION['admin_id']          = $user['id'];               // kept for backward compatibility with existing pages
$_SESSION['admin_user_id']     = $user['id'];
$_SESSION['admin_name']        = $user['full_name'];
$_SESSION['is_admin']          = true;
$_SESSION['admin_member_id']   = (int)$member['id'];
$_SESSION['admin_role_id']     = (int)$member['role_id'];
$_SESSION['admin_role_slug']   = $role['role_slug'] ?? '';
$_SESSION['admin_role_name']   = $role['role_name'] ?? '';
$_SESSION['admin_permissions'] = $permissions;
$_SESSION['admin_department']  = $member['department'] ?? null;
$_SESSION['admin_scope']       = ['type' => $member['scope_type'] ?? 'all', 'value' => $member['scope_value'] ?? null];
$_SESSION['is_super_admin']    = $isSuperAdmin;

agri_admin_log_login($conn, $user['id'], 'success', $ip, $ua);
logAdminActivity('admin_login', 'auth', null, null, null, ($role['role_name'] ?? 'Admin') . ' "' . $user['full_name'] . '" logged in.');

header('Location: index.php');
exit;
