<?php
// =====================================================================
// admin/includes/permissions.php
// Central RBAC permission engine for the AgriCart Admin Panel.
//
// Include this file (it is already pulled in by admin_guard.php, so any
// page that includes admin_guard.php gets all of these functions for
// free) and then use:
//
//   hasPermission('products.edit')
//   requirePermission('products.edit')
//   isSuperAdmin()
//   canViewModule('products')
//   getAdminPermissions($adminTeamMemberId)
//   logAdminActivity('product_edit', 'products', $id, $old, $new)
//
// Resolution order for a permission check:
//   1. Super Admin  -> always true.
//   2. admin_user_permissions (per-user override) with type = 'deny'
//      -> always false, even if the role allows it.
//   3. admin_user_permissions (per-user override) with type = 'allow'
//      -> true, even if the role does not grant it.
//   4. admin_role_permissions for the member's role -> true/false.
//   5. Anything not explicitly granted -> false.
//
// Nothing here ever trusts data sent from the browser — every check
// re-reads the permission set that was loaded into $_SESSION at login
// time (see admin/auth.php), and that session data is itself derived
// only from the database, never from request input.
// =====================================================================

if (!function_exists('agri_admin_db')) {
    /**
     * Returns a mysqli connection, reusing $conn from includes/db.php
     * if it's already been included by the calling page.
     */
    function agri_admin_db(): mysqli
    {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            include_once __DIR__ . '/../../includes/db.php';
            global $conn;
        }
        return $conn;
    }
}

if (!function_exists('isSuperAdmin')) {
    function isSuperAdmin(): bool
    {
        return !empty($_SESSION['is_super_admin']);
    }
}

if (!function_exists('getAdminPermissions')) {
    /**
     * Loads the fully-resolved (role + per-user overrides) permission
     * set for a given admin_team_members.id, as an associative array
     * of permission_key => true. Always reads fresh from the database
     * — used at login time to populate the session, and can be called
     * again any time to force a re-check.
     */
    function getAdminPermissions(int $adminMemberId): array
    {
        $conn = agri_admin_db();
        $perms = [];

        // A team member can now hold more than one role at once — union
        // the permissions of every role assigned to them via
        // admin_team_member_roles. If that table isn't set up yet or the
        // member has no rows there (not backfilled), fall back to their
        // single admin_team_members.role_id so nothing ever breaks.
        if (function_exists('team_roles_table_exists') && team_roles_table_exists($conn)) {
            $stmt = $conn->prepare(
                "SELECT DISTINCT p.permission_key
                   FROM admin_team_member_roles tmr
                   JOIN admin_role_permissions rp ON rp.role_id = tmr.role_id AND rp.allowed = 1
                   JOIN admin_permissions p ON p.id = rp.permission_id
                  WHERE tmr.member_id = ?"
            );
            $stmt->bind_param('i', $adminMemberId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $perms[$row['permission_key']] = true;
            }
        }

        if (empty($perms)) {
            $stmt = $conn->prepare(
                "SELECT p.permission_key
                   FROM admin_team_members m
                   JOIN admin_role_permissions rp ON rp.role_id = m.role_id AND rp.allowed = 1
                   JOIN admin_permissions p ON p.id = rp.permission_id
                  WHERE m.id = ?"
            );
            $stmt->bind_param('i', $adminMemberId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($row = $res->fetch_assoc()) {
                $perms[$row['permission_key']] = true;
            }
        }

        // Per-user overrides — allow first, then deny (deny always wins).
        $stmt = $conn->prepare(
            "SELECT p.permission_key, up.permission_type
               FROM admin_user_permissions up
               JOIN admin_permissions p ON p.id = up.permission_id
              WHERE up.admin_member_id = ?"
        );
        $stmt->bind_param('i', $adminMemberId);
        $stmt->execute();
        $res = $stmt->get_result();
        $denies = [];
        while ($row = $res->fetch_assoc()) {
            if ($row['permission_type'] === 'allow') {
                $perms[$row['permission_key']] = true;
            } else {
                $denies[] = $row['permission_key'];
            }
        }
        foreach ($denies as $key) {
            unset($perms[$key]);
        }

        return $perms;
    }
}

if (!function_exists('hasPermission')) {
    function hasPermission(string $permissionKey): bool
    {
        if (isSuperAdmin()) {
            return true;
        }
        $perms = $_SESSION['admin_permissions'] ?? [];
        return !empty($perms[$permissionKey]);
    }
}

if (!function_exists('canViewModule')) {
    /**
     * True if the logged-in admin has ANY permission whose key starts
     * with "$module." — handy for deciding whether to show a sidebar
     * section or a dashboard card group at all.
     */
    function canViewModule(string $module): bool
    {
        if (isSuperAdmin()) {
            return true;
        }
        if (hasPermission($module . '.view')) {
            return true;
        }
        $perms = $_SESSION['admin_permissions'] ?? [];
        foreach (array_keys($perms) as $key) {
            if (strpos($key, $module . '.') === 0) {
                return true;
            }
        }
        return false;
    }
}

if (!function_exists('admin_base_url')) {
    /** Best-effort relative path back to /admin/ from any current script. */
    function admin_base_url(): string
    {
        $scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/admin/index.php');
        // Actions live in /admin/actions/ — one level deeper than /admin/.
        if (basename($scriptDir) === 'actions') {
            return '../';
        }
        return './';
    }
}

if (!function_exists('requirePermission')) {
    function requirePermission(string $permissionKey): void
    {
        if (hasPermission($permissionKey)) {
            return;
        }

        if (function_exists('logAdminActivity')) {
            logAdminActivity('unauthorized_access_attempt', $permissionKey, null, null,
                'Blocked: missing permission "' . $permissionKey . '"');
        }

        http_response_code(403);

        $headers = headers_list();
        $isJson = false;
        foreach ($headers as $h) {
            if (stripos($h, 'Content-Type: application/json') === 0) { $isJson = true; break; }
        }
        if (!$isJson) {
            $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
            $xhr    = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
            if (stripos($accept, 'application/json') !== false || strtolower($xhr) === 'xmlhttprequest') {
                $isJson = true;
            }
        }

        if ($isJson) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'You do not have permission to perform this action.']);
        } else {
            header('Location: ' . admin_base_url() . 'access_denied.php?perm=' . urlencode($permissionKey));
        }
        exit;
    }
}

if (!function_exists('logAdminActivity')) {
    function logAdminActivity(string $action, ?string $module = null, ?int $recordId = null, $oldValue = null, $newValue = null, ?string $description = null): void
    {
        try {
            $conn = agri_admin_db();
            $adminUserId = $_SESSION['admin_user_id'] ?? $_SESSION['admin_id'] ?? null;
            $old = is_array($oldValue) || is_object($oldValue) ? json_encode($oldValue) : $oldValue;
            $new = is_array($newValue) || is_object($newValue) ? json_encode($newValue) : $newValue;
            $desc = $description ?? ($action . (($module) ? (' — ' . $module) : ''));
            $ip = $_SERVER['REMOTE_ADDR'] ?? null;
            $ua = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);

            $stmt = $conn->prepare(
                "INSERT INTO admin_activity_logs (admin_user_id, action, module, record_id, description, old_value, new_value, ip_address, user_agent)
                 VALUES (?,?,?,?,?,?,?,?,?)"
            );
            $stmt->bind_param('ississsss', $adminUserId, $action, $module, $recordId, $desc, $old, $new, $ip, $ua);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Never let logging failures break the admin action itself.
        }
    }
}

if (!function_exists('notifySuperAdmin')) {
    function notifySuperAdmin(string $type, string $title, ?string $message = null): void
    {
        try {
            $conn = agri_admin_db();
            $stmt = $conn->prepare("INSERT INTO admin_notifications (type, title, message) VALUES (?,?,?)");
            $stmt->bind_param('sss', $type, $title, $message);
            $stmt->execute();
        } catch (\Throwable $e) {
            // Non-fatal.
        }
    }
}

if (!function_exists('getAdminScope')) {
    /** Returns ['type' => 'all'|'state'|'district'|'city'|'own_records', 'value' => string|null] */
    function getAdminScope(): array
    {
        if (isSuperAdmin()) {
            return ['type' => 'all', 'value' => null];
        }
        return [
            'type'  => $_SESSION['admin_scope']['type'] ?? 'all',
            'value' => $_SESSION['admin_scope']['value'] ?? null,
        ];
    }
}

if (!function_exists('applyScopeToQuery')) {
    /**
     * Appends a scope-restricting WHERE fragment (and returns the bind
     * value to go with it) for queries against tables that have a
     * district/city/state column. Returns [$sql, $bindValue] where
     * $sql is '' and $bindValue is null when no restriction applies.
     * Example:
     *   [$scopeSql, $scopeVal] = applyScopeToQuery('district');
     *   $query = "SELECT * FROM krishi_bazaar WHERE 1=1 $scopeSql";
     *   if ($scopeVal !== null) { $stmt->bind_param('s', $scopeVal); }
     */
    function applyScopeToQuery(string $column): array
    {
        $scope = getAdminScope();
        if ($scope['type'] === 'all' || $scope['type'] === 'own_records' || empty($scope['value'])) {
            return ['', null];
        }
        return [" AND {$column} = ? ", $scope['value']];
    }
}
