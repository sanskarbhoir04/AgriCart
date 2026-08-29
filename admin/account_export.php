<?php
// =====================================================================
// admin/account_export.php — Export the current Accounts tab/filters to
// CSV. Never includes password hashes or any authentication secret —
// only the same display fields already shown in the Accounts table.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
require_once __DIR__ . '/includes/companies_schema.php';
accounts_bootstrap_schema($conn);
companies_bootstrap_schema($conn);
requirePermission('accounts.export');

$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'buyers', 'sellers', 'companies', 'employees', 'pending', 'suspended', 'deleted'];
if (!in_array($tab, $validTabs, true)) { $tab = 'all'; }

$accounts = [];

$uRes = $conn->query(
    "SELECT id, full_name, name, username, email, mobile, phone, role, village, district,
            created_at, last_login_at, status, deleted_at, email_verified, mobile_verified
       FROM users WHERE role <> 'admin'"
);
if ($uRes) {
    while ($u = $uRes->fetch_assoc()) {
        $type = acc_role_type($u['role']);
        [$statusLabel] = acc_status_label($u['status'], $u['deleted_at']);
        $accounts[] = [
            'Account ID' => ($type === 'seller' ? 'SLR-' : 'BYR-') . str_pad($u['id'], 4, '0', STR_PAD_LEFT),
            'Type' => acc_type_label($type),
            'Name' => $u['full_name'] ?: ($u['name'] ?: $u['username']),
            'Email' => $u['email'],
            'Mobile' => $u['mobile'] ?: $u['phone'],
            'Location' => trim(implode(', ', array_filter([$u['village'] ?? '', $u['district'] ?? '']))),
            'Registered' => $u['created_at'],
            'Last Login' => $u['last_login_at'],
            'Verified' => (!empty($u['email_verified']) && !empty($u['mobile_verified'])) ? 'Yes' : 'No',
            'Status' => $statusLabel,
            '_type' => $type, '_deleted' => !empty($u['deleted_at']),
            '_pending' => empty($u['deleted_at']) && strtolower((string)$u['status']) === 'pending_verification',
            '_suspended' => empty($u['deleted_at']) && in_array(strtolower((string)$u['status']), ['suspended', 'blocked', 'banned'], true),
        ];
    }
}

try {
    $cRes = $conn->query("SELECT * FROM sellers");
    if ($cRes) {
        while ($c = $cRes->fetch_assoc()) {
            [$statusLabel] = acc_status_label($c['account_status'] ?? 'active', $c['deleted_at'] ?? null);
            $accounts[] = [
                'Account ID' => 'CMP-' . str_pad($c['id'], 4, '0', STR_PAD_LEFT),
                'Type' => 'Company',
                'Name' => $c['name'],
                'Email' => $c['email'],
                'Mobile' => $c['mobile'],
                'Location' => trim(implode(', ', array_filter([$c['village'] ?? '', $c['city'] ?? '']))),
                'Registered' => $c['created_at'],
                'Last Login' => '',
                'Verified' => (!empty($c['gst_verified']) && !empty($c['business_verified'])) ? 'Yes' : 'No',
                'Status' => $statusLabel,
                '_type' => 'company', '_deleted' => !empty($c['deleted_at']),
                '_pending' => empty($c['deleted_at']) && !(!empty($c['gst_verified']) && !empty($c['business_verified'])),
                '_suspended' => empty($c['deleted_at']) && in_array(strtolower((string)($c['account_status'] ?? '')), ['suspended', 'blocked'], true),
            ];
        }
    }
} catch (\Throwable $e) { /* sellers table not available */ }

$eRes = $conn->query(
    "SELECT tm.id AS member_id, tm.department, tm.status, tm.assigned_at, tm.last_login,
            u.full_name, u.email, u.mobile, r.role_name
       FROM admin_team_members tm JOIN users u ON u.id = tm.user_id LEFT JOIN admin_roles r ON r.id = tm.role_id"
);
if ($eRes) {
    while ($e = $eRes->fetch_assoc()) {
        [$statusLabel] = acc_status_label($e['status'], null);
        $accounts[] = [
            'Account ID' => 'EMP-' . str_pad($e['member_id'], 4, '0', STR_PAD_LEFT),
            'Type' => 'Employee',
            'Name' => $e['full_name'],
            'Email' => $e['email'],
            'Mobile' => $e['mobile'],
            'Location' => $e['department'],
            'Registered' => $e['assigned_at'],
            'Last Login' => $e['last_login'],
            'Verified' => 'Yes',
            'Status' => $statusLabel,
            '_type' => 'employee', '_deleted' => false, '_pending' => false,
            '_suspended' => in_array(strtolower((string)$e['status']), ['suspended', 'blocked'], true),
        ];
    }
}

switch ($tab) {
    case 'buyers':    $accounts = array_filter($accounts, fn($a) => $a['_type'] === 'buyer'); break;
    case 'sellers':   $accounts = array_filter($accounts, fn($a) => $a['_type'] === 'seller'); break;
    case 'companies': $accounts = array_filter($accounts, fn($a) => $a['_type'] === 'company'); break;
    case 'employees': $accounts = array_filter($accounts, fn($a) => $a['_type'] === 'employee'); break;
    case 'pending':   $accounts = array_filter($accounts, fn($a) => $a['_pending']); break;
    case 'suspended': $accounts = array_filter($accounts, fn($a) => $a['_suspended']); break;
    case 'deleted':   $accounts = array_filter($accounts, fn($a) => $a['_deleted']); break;
    default: break;
}

logAdminActivity('accounts_exported', 'accounts', null, null, null, 'Exported "' . $tab . '" accounts to CSV (' . count($accounts) . ' rows)');

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="agricart-accounts-' . $tab . '-' . date('Y-m-d') . '.csv"');
$out = fopen('php://output', 'w');
$headers = ['Account ID', 'Type', 'Name', 'Email', 'Mobile', 'Location', 'Registered', 'Last Login', 'Verified', 'Status'];
fputcsv($out, $headers);
foreach ($accounts as $a) {
    fputcsv($out, array_map(fn($h) => $a[$h] ?? '', $headers));
}
fclose($out);
exit;
