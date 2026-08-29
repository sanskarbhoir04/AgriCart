<?php
// admin/logout.php — same full logout as the main site (this is one real
// account, so admin logout also logs you out of the storefront entirely).
require_once __DIR__ . '/../includes/security.php';
agri_session_start();

// RBAC: stamp the logout time on this admin's most recent login row, and
// record the logout as an activity before the session is wiped.
if (!empty($_SESSION['is_admin']) && !empty($_SESSION['admin_user_id'])) {
    include_once __DIR__ . '/../includes/db.php';
    include_once __DIR__ . '/includes/permissions.php';
    logAdminActivity('admin_logout', 'auth', null, null, null,
        ($_SESSION['admin_role_name'] ?? 'Admin') . ' "' . ($_SESSION['admin_name'] ?? '') . '" logged out.');
    $uid = $_SESSION['admin_user_id'];
    $stmt = $conn->prepare(
        "UPDATE admin_login_logs SET logout_time = NOW()
          WHERE admin_user_id = ? AND login_status = 'success' AND logout_time IS NULL
          ORDER BY id DESC LIMIT 1"
    );
    $stmt->bind_param('i', $uid);
    $stmt->execute();
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}
session_destroy();

header('Location: login.php');
exit;
