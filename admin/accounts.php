<?php
// =====================================================================
// admin/accounts.php — Accounts Management: a centralized place for the
// Admin/Super Admin to view, manage, verify, monitor, and control every
// account type on AgriCart (Buyers, Sellers, Companies, Employees).
//
// Reuses the existing `users`, `sellers` (Companies directory) and
// `admin_team_members` tables — see includes/accounts_schema.php for
// why no new "accounts" table was created and what columns were added.
//
// Gated by 'accounts.view' (Super Admin always passes).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/accounts_schema.php';
require_once __DIR__ . '/includes/companies_schema.php';
accounts_bootstrap_schema($conn);
companies_bootstrap_schema($conn);
requirePermission('accounts.view');

$canManage = hasPermission('accounts.manage');
$canVerify = hasPermission('accounts.verify');
$canExport = hasPermission('accounts.export');

/* ------------------------------------------------------------------ *
 * 1. Pull every account into one normalized array: buyers/sellers come
 *    from `users`, companies from `sellers`, employees from
 *    `admin_team_members` joined to `users` + `admin_roles`. Dataset is
 *    admin-scale (hundreds, not millions of rows) so normalizing in PHP
 *    keeps three very different schemas from turning into a fragile
 *    hand-rolled SQL UNION.
 * ------------------------------------------------------------------ */
$accounts = [];

// ---- Buyers & Sellers (users table) ----
$uRes = $conn->query(
    "SELECT id, full_name, name, username, email, mobile, phone, profile_photo, role,
            village, district, saved_pincode, created_at, updated_at, last_login_at, login_method,
            status, status_reason, is_verified, email_verified, mobile_verified, kyc_verified, deleted_at
       FROM users WHERE role <> 'admin'"
);
if ($uRes) {
    while ($u = $uRes->fetch_assoc()) {
        $type = acc_role_type($u['role']);
        [$statusLabel, $statusClass] = acc_status_label($u['status'], $u['deleted_at']);
        $verified = !empty($u['email_verified']) && !empty($u['mobile_verified']);
        $accounts[] = [
            'type'          => $type,
            'raw_id'        => (int)$u['id'],
            'account_id'    => ($type === 'seller' ? 'SLR-' : 'BYR-') . str_pad($u['id'], 4, '0', STR_PAD_LEFT),
            'name'          => $u['full_name'] ?: ($u['name'] ?: $u['username'] ?: ('User #' . $u['id'])),
            'photo'         => $u['profile_photo'],
            'email'         => $u['email'],
            'mobile'        => $u['mobile'] ?: $u['phone'],
            'location'      => trim(implode(', ', array_filter([$u['village'] ?? '', $u['district'] ?? '']))),
            'registered_at' => $u['created_at'],
            'last_login'    => $u['last_login_at'],
            'status_raw'    => $u['status'],
            'status_label'  => $statusLabel,
            'status_class'  => $statusClass,
            'deleted'       => !empty($u['deleted_at']),
            'verified'      => $verified,
            'verify_label'  => $verified ? 'Verified' : 'Pending',
            'reason'        => $u['status_reason'],
        ];
    }
}

// ---- Companies (sellers directory table) ----
$sellersTableExists = false;
try { $chk = $conn->query("SELECT 1 FROM sellers LIMIT 1"); $sellersTableExists = (bool)$chk; } catch (\Throwable $e) {}
if ($sellersTableExists) {
    $cRes = $conn->query("SELECT * FROM sellers");
    if ($cRes) {
        while ($c = $cRes->fetch_assoc()) {
            [$statusLabel, $statusClass] = acc_status_label($c['account_status'] ?? 'active', $c['deleted_at'] ?? null);
            $verified = !empty($c['gst_verified']) && !empty($c['business_verified']);
            $accounts[] = [
                'type'          => 'company',
                'raw_id'        => (int)$c['id'],
                'account_id'    => 'CMP-' . str_pad($c['id'], 4, '0', STR_PAD_LEFT),
                'name'          => $c['name'],
                'photo'         => $c['logo'] ?? null,
                'email'         => $c['email'],
                'mobile'        => $c['mobile'],
                'location'      => trim(implode(', ', array_filter([$c['village'] ?? '', $c['city'] ?? '']))),
                'registered_at' => $c['created_at'],
                'last_login'    => null,
                'status_raw'    => $c['account_status'] ?? 'active',
                'status_label'  => $statusLabel,
                'status_class'  => $statusClass,
                'deleted'       => !empty($c['deleted_at']),
                'verified'      => $verified,
                'verify_label'  => $verified ? 'Verified' : 'Pending',
                'reason'        => $c['status_reason'] ?? null,
            ];
        }
    }
}

// ---- Employees (admin_team_members + users + admin_roles) ----
$eRes = $conn->query(
    "SELECT tm.id AS member_id, tm.department, tm.status, tm.status_reason, tm.assigned_at, tm.last_login,
            u.id AS user_id, u.full_name, u.email, u.mobile, u.profile_photo,
            r.role_name
       FROM admin_team_members tm
       JOIN users u ON u.id = tm.user_id
       LEFT JOIN admin_roles r ON r.id = tm.role_id"
);
if ($eRes) {
    while ($e = $eRes->fetch_assoc()) {
        [$statusLabel, $statusClass] = acc_status_label($e['status'], null);
        $accounts[] = [
            'type'          => 'employee',
            'raw_id'        => (int)$e['member_id'],
            'account_id'    => 'EMP-' . str_pad($e['member_id'], 4, '0', STR_PAD_LEFT),
            'name'          => $e['full_name'],
            'photo'         => $e['profile_photo'],
            'email'         => $e['email'],
            'mobile'        => $e['mobile'],
            'location'      => $e['department'],
            'registered_at' => $e['assigned_at'],
            'last_login'    => $e['last_login'],
            'status_raw'    => $e['status'],
            'status_label'  => $statusLabel,
            'status_class'  => $statusClass,
            'deleted'       => false,
            'verified'      => true,
            'verify_label'  => $e['role_name'] ?: '—',
            'reason'        => $e['status_reason'],
        ];
    }
}

/* ------------------------------------------------------------------ *
 * 2. Summary cards
 * ------------------------------------------------------------------ */
$summary = ['total' => 0, 'buyers' => 0, 'sellers' => 0, 'companies' => 0, 'employees' => 0,
            'active' => 0, 'pending' => 0, 'suspended' => 0, 'blocked' => 0];
foreach ($accounts as $a) {
    $summary['total']++;
    $summary[$a['type'] . 's'] = ($summary[$a['type'] . 's'] ?? 0) + 1;
    if ($a['deleted']) { continue; }
    if ($a['status_class'] === 'active') { $summary['active']++; }
    elseif ($a['status_class'] === 'pending') { $summary['pending']++; }
    elseif ($a['status_class'] === 'suspended') {
        if (strtolower((string)$a['status_raw']) === 'blocked' || strtolower((string)$a['status_raw']) === 'banned') { $summary['blocked']++; }
        else { $summary['suspended']++; }
    }
}

/* ------------------------------------------------------------------ *
 * 3. Tab selection + filters
 * ------------------------------------------------------------------ */
$tab = $_GET['tab'] ?? 'all';
$validTabs = ['all', 'buyers', 'sellers', 'companies', 'employees', 'pending', 'suspended', 'deleted'];
if (!in_array($tab, $validTabs, true)) { $tab = 'all'; }

$q          = trim($_GET['q'] ?? '');
$typeFilter = trim($_GET['type'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$verifyFilter = trim($_GET['verify'] ?? '');
$dateFrom   = trim($_GET['from'] ?? '');
$dateTo     = trim($_GET['to'] ?? '');
$sort       = trim($_GET['sort'] ?? 'newest');
$page       = max(1, (int)($_GET['page'] ?? 1));
$perPage    = 12;

$rows = $accounts;

// Tab-level base filter
switch ($tab) {
    case 'buyers':    $rows = array_filter($rows, fn($a) => $a['type'] === 'buyer'); break;
    case 'sellers':   $rows = array_filter($rows, fn($a) => $a['type'] === 'seller'); break;
    case 'companies': $rows = array_filter($rows, fn($a) => $a['type'] === 'company'); break;
    case 'employees': $rows = array_filter($rows, fn($a) => $a['type'] === 'employee'); break;
    case 'pending':   $rows = array_filter($rows, fn($a) => !$a['deleted'] && $a['status_class'] === 'pending'); break;
    case 'suspended': $rows = array_filter($rows, fn($a) => !$a['deleted'] && $a['status_class'] === 'suspended'); break;
    case 'deleted':   $rows = array_filter($rows, fn($a) => $a['deleted']); break;
    default: break; // all
}

if ($typeFilter !== '') { $rows = array_filter($rows, fn($a) => $a['type'] === $typeFilter); }
if ($statusFilter !== '') { $rows = array_filter($rows, fn($a) => strtolower((string)$a['status_raw']) === strtolower($statusFilter) || ($statusFilter === 'deactivated' && $a['deleted'])); }
if ($verifyFilter === 'verified') { $rows = array_filter($rows, fn($a) => $a['verified']); }
elseif ($verifyFilter === 'pending') { $rows = array_filter($rows, fn($a) => !$a['verified']); }
if ($dateFrom !== '') { $rows = array_filter($rows, fn($a) => $a['registered_at'] && $a['registered_at'] >= $dateFrom . ' 00:00:00'); }
if ($dateTo !== '') { $rows = array_filter($rows, fn($a) => $a['registered_at'] && $a['registered_at'] <= $dateTo . ' 23:59:59'); }

if ($q !== '') {
    $needle = mb_strtolower($q);
    $rows = array_filter($rows, function ($a) use ($needle) {
        foreach (['account_id', 'name', 'email', 'mobile'] as $f) {
            if ($a[$f] && mb_strpos(mb_strtolower((string)$a[$f]), $needle) !== false) { return true; }
        }
        return false;
    });
}

$rows = array_values($rows);
usort($rows, function ($a, $b) use ($sort) {
    switch ($sort) {
        case 'oldest': return strcmp((string)$a['registered_at'], (string)$b['registered_at']);
        case 'name':   return strcasecmp((string)$a['name'], (string)$b['name']);
        default:       return strcmp((string)$b['registered_at'], (string)$a['registered_at']); // newest
    }
});

$total = count($rows);
$totalPages = max(1, (int)ceil($total / $perPage));
$page = min($page, $totalPages);
$pageRows = array_slice($rows, ($page - 1) * $perPage, $perPage);

/* ------------------------------------------------------------------ *
 * 4. Recent account activity (from the shared audit log)
 * ------------------------------------------------------------------ */
$recentActivity = [];
$actRes = $conn->query(
    "SELECT l.*, u.full_name AS admin_full_name FROM admin_activity_logs l
     LEFT JOIN admin_team_members tm ON tm.id = l.admin_user_id
     LEFT JOIN users u ON u.id = COALESCE(tm.user_id, l.admin_user_id)
     WHERE l.action LIKE '%account%' OR l.action LIKE '%seller%' OR l.action LIKE '%user_%' OR l.action LIKE '%company%' OR l.action LIKE '%team_member%'
     ORDER BY l.created_at DESC LIMIT 8"
);
if ($actRes) { while ($r = $actRes->fetch_assoc()) { $recentActivity[] = $r; } }

$pageTitle     = 'Accounts';
$pageSubtitle  = "Manage and monitor all financial accounts across the AgriCart platform.";
$activeTeamTab = 'accounts';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
/* Stat cards use the shared .stat-card component from
   includes/team_layout_top.php — the same round colored-icon card as
   the main Dashboard (single source of truth). Only page-specific
   layout below. */
.acc-tabs{display:flex;gap:4px;flex-wrap:wrap;border-bottom:1.5px solid var(--border);margin-bottom:18px;padding-bottom:0}
.acc-tabs a{padding:10px 16px;font-size:13px;font-weight:600;color:var(--muted);border-bottom:2.5px solid transparent;margin-bottom:-1.5px}
.acc-tabs a.active{color:var(--primary);border-color:var(--primary)}
.acc-tabs a .cnt{background:var(--bg-soft);color:var(--primary);border-radius:20px;padding:1px 8px;font-size:11px;margin-left:6px}
.acc-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.acc-toolbar input[type=text]{max-width:230px}
.acc-toolbar select,.acc-toolbar input[type=date]{max-width:160px}
.acc-name-cell{display:flex;align-items:center;gap:10px}
.acc-name-cell b{font-weight:600;font-size:13px}
.acc-name-cell .sub{font-size:11px;color:var(--muted)}
.acc-type-pill{font-size:10.5px;font-weight:700;padding:3px 9px;border-radius:20px;background:var(--bg-soft);color:var(--primary);text-transform:uppercase;letter-spacing:.02em}
.acc-actions-cell{display:flex;gap:6px;flex-wrap:wrap}
.acc-actions-cell button,.acc-actions-cell a.btn{background:var(--bg-soft);border:1px solid transparent;padding:6px 9px;border-radius:8px;cursor:pointer;font-size:11.5px;color:var(--muted)}
.acc-actions-cell button:hover,.acc-actions-cell a.btn:hover{border-color:var(--primary);color:var(--primary)}
.acc-actions-cell .danger-act:hover{border-color:var(--danger);color:var(--danger)}
.acc-actions-cell .good-act:hover{border-color:var(--success);color:var(--success)}
.acc-activity{display:flex;flex-direction:column;gap:10px}
.acc-activity-row{display:flex;gap:10px;font-size:12.5px;padding-bottom:10px;border-bottom:1px solid var(--border)}
.acc-activity-row:last-child{border-bottom:none;padding-bottom:0}
.acc-activity-row i{color:var(--primary);margin-top:2px}
.acc-activity-row .when{color:var(--muted);font-size:11px}
#accStatusModal .modal-box{max-width:420px}

/* ---- Actions dropdown restyle: clean white card, icon + label rows ---- */
.action-menu-wrap{position:relative;display:inline-block}
.action-menu-wrap .kebab-btn{width:32px;height:32px;display:flex;align-items:center;justify-content:center;background:#fff;border:1px solid var(--border);border-radius:8px;color:var(--muted);cursor:pointer;transition:border-color .15s,color .15s}
.action-menu-wrap .kebab-btn:hover{border-color:var(--primary);color:var(--primary)}
.action-menu-wrap .action-menu{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 28px rgba(0,0,0,.14);padding:6px;min-width:180px}
.action-menu-wrap .action-menu a,
.action-menu-wrap .action-menu button{display:flex;align-items:center;gap:10px;width:100%;background:none;border:none;padding:10px 12px;border-radius:8px;font-size:13.5px;font-weight:500;color:#20291f;text-align:left;text-decoration:none;cursor:pointer;white-space:nowrap}
.action-menu-wrap .action-menu a i,
.action-menu-wrap .action-menu button i{width:16px;text-align:center;color:var(--muted);font-size:13px}
.action-menu-wrap .action-menu a:hover,
.action-menu-wrap .action-menu button:hover{background:var(--bg-soft)}
.action-menu-wrap .action-menu .menu-danger{color:#d32f2f}
.action-menu-wrap .action-menu .menu-danger i{color:#d32f2f}
.action-menu-wrap .action-menu .menu-success{color:#2E7D32}
.action-menu-wrap .action-menu .menu-success i{color:#2E7D32}
</style>

<div class="stats-row">
    <a href="accounts.php?tab=all" class="stat-card"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-users"></i></div><div><div class="val"><?php echo $summary['total']; ?></div><div class="lbl">Total Accounts</div></div></a>
    <a href="accounts.php?tab=buyers" class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-cart-shopping"></i></div><div><div class="val"><?php echo $summary['buyers'] ?? 0; ?></div><div class="lbl">Total Buyers</div></div></a>
    <a href="accounts.php?tab=sellers" class="stat-card"><div class="icn" style="background:#1b5e20"><i class="fa-solid fa-store"></i></div><div><div class="val"><?php echo $summary['sellers'] ?? 0; ?></div><div class="lbl">Total Sellers</div></div></a>
    <a href="accounts.php?tab=companies" class="stat-card"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-building"></i></div><div><div class="val"><?php echo $summary['companies'] ?? 0; ?></div><div class="lbl">Total Companies</div></div></a>
    <a href="accounts.php?tab=employees" class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-id-badge"></i></div><div><div class="val"><?php echo $summary['employees'] ?? 0; ?></div><div class="lbl">Total Employees</div></div></a>
    <div class="stat-card"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-circle-check"></i></div><div><div class="val"><?php echo $summary['active']; ?></div><div class="lbl">Active Accounts</div></div></div>
    <a href="accounts.php?tab=pending" class="stat-card"><div class="icn" style="background:#F9A825"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo $summary['pending']; ?></div><div class="lbl">Pending Verification</div></div></a>
    <a href="accounts.php?tab=suspended" class="stat-card"><div class="icn" style="background:#F57C00"><i class="fa-solid fa-pause"></i></div><div><div class="val"><?php echo $summary['suspended']; ?></div><div class="lbl">Suspended</div></div></a>
    <a href="accounts.php?tab=suspended" class="stat-card"><div class="icn" style="background:#B71C1C"><i class="fa-solid fa-ban"></i></div><div><div class="val"><?php echo $summary['blocked']; ?></div><div class="lbl">Blocked</div></div></a>
</div>

<div class="card" style="padding:0;overflow:hidden">
    <div style="padding:18px 20px 0">
        <div class="acc-tabs">
            <?php
            $tabDefs = [
                'all'       => ['All Accounts', $summary['total']],
                'buyers'    => ['Buyers', $summary['buyers'] ?? 0],
                'sellers'   => ['Sellers', $summary['sellers'] ?? 0],
                'companies' => ['Companies', $summary['companies'] ?? 0],
                'employees' => ['Employees', $summary['employees'] ?? 0],
                'pending'   => ['Pending Verification', $summary['pending']],
                'suspended' => ['Suspended / Blocked', $summary['suspended'] + $summary['blocked']],
                'deleted'   => ['Deactivated', count(array_filter($accounts, fn($a) => $a['deleted']))],
            ];
            foreach ($tabDefs as $key => [$label, $cnt]):
            ?>
            <a href="accounts.php?tab=<?php echo $key; ?>" class="<?php echo $tab === $key ? 'active' : ''; ?>"><?php echo $label; ?> <span class="cnt"><?php echo $cnt; ?></span></a>
            <?php endforeach; ?>
        </div>
    </div>

    <div style="padding:20px">
    <div class="card-head">
        <h2><?php echo $tabDefs[$tab][0]; ?> (<?php echo $total; ?>)</h2>
        <?php if ($canExport): ?>
        <a class="btn outline sm" href="account_export.php?<?php echo http_build_query(array_merge($_GET, ['tab' => $tab])); ?>"><i class="fa-solid fa-file-export"></i> Export CSV</a>
        <?php endif; ?>
    </div>

    <form class="acc-toolbar" method="get" id="accFilterForm">
        <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
        <input type="text" name="q" placeholder="Search name, email, mobile, account ID..." value="<?php echo htmlspecialchars($q); ?>">
        <?php if ($tab === 'all' || $tab === 'pending' || $tab === 'suspended' || $tab === 'deleted'): ?>
        <select name="type" onchange="document.getElementById('accFilterForm').submit()">
            <option value="">All Types</option>
            <?php foreach (['buyer'=>'Buyer','seller'=>'Seller','company'=>'Company','employee'=>'Employee'] as $tv=>$tl): ?>
            <option value="<?php echo $tv; ?>" <?php echo $typeFilter === $tv ? 'selected' : ''; ?>><?php echo $tl; ?></option>
            <?php endforeach; ?>
        </select>
        <?php endif; ?>
        <select name="status" onchange="document.getElementById('accFilterForm').submit()">
            <option value="">Any Status</option>
            <?php foreach (['active'=>'Active','pending_verification'=>'Pending Verification','suspended'=>'Suspended','blocked'=>'Blocked','deactivated'=>'Deactivated'] as $sv=>$sl): ?>
            <option value="<?php echo $sv; ?>" <?php echo $statusFilter === $sv ? 'selected' : ''; ?>><?php echo $sl; ?></option>
            <?php endforeach; ?>
        </select>
        <select name="verify" onchange="document.getElementById('accFilterForm').submit()">
            <option value="">Verification: Any</option>
            <option value="verified" <?php echo $verifyFilter === 'verified' ? 'selected' : ''; ?>>Verified</option>
            <option value="pending" <?php echo $verifyFilter === 'pending' ? 'selected' : ''; ?>>Pending</option>
        </select>
        <input type="date" name="from" value="<?php echo htmlspecialchars($dateFrom); ?>" title="Registered from">
        <input type="date" name="to" value="<?php echo htmlspecialchars($dateTo); ?>" title="Registered to">
        <select name="sort" onchange="document.getElementById('accFilterForm').submit()">
            <option value="newest" <?php echo $sort === 'newest' ? 'selected' : ''; ?>>Sort: Newest</option>
            <option value="oldest" <?php echo $sort === 'oldest' ? 'selected' : ''; ?>>Sort: Oldest</option>
            <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Sort: Name (A–Z)</option>
        </select>
        <button type="submit" class="btn sm"><i class="fa-solid fa-filter"></i> Apply</button>
        <?php if ($q !== '' || $typeFilter !== '' || $statusFilter !== '' || $verifyFilter !== '' || $dateFrom !== '' || $dateTo !== ''): ?>
        <a href="accounts.php?tab=<?php echo $tab; ?>" class="btn sm outline">Reset</a>
        <?php endif; ?>
    </form>

    <?php if (empty($pageRows)): ?>
        <div class="empty-state"><i class="fa-solid fa-user-slash"></i>No accounts match your search/filters.</div>
    <?php else: ?>
    <table>
        <thead><tr>
            <th>Account</th><th>Type</th><th>Contact</th><th>Location</th>
            <th>Registered</th><th>Last Login</th><th>Verification</th><th>Status</th><th>Actions</th>
        </tr></thead>
        <tbody>
        <?php foreach ($pageRows as $a): $initial = strtoupper(substr($a['name'] ?: '?', 0, 1)); ?>
            <tr>
                <td>
                    <div class="acc-name-cell">
                        <div class="avatar-circle"><?php echo !empty($a['photo']) ? '<img src="' . htmlspecialchars(acc_img_url($a['photo'])) . '" onerror="this.parentElement.textContent=' . json_encode($initial) . '">' : $initial; ?></div>
                        <div><b><?php echo htmlspecialchars($a['name'] ?: '—'); ?></b><div class="sub"><?php echo htmlspecialchars($a['account_id']); ?></div></div>
                    </div>
                </td>
                <td><span class="acc-type-pill"><?php echo acc_type_label($a['type']); ?></span></td>
                <td><?php echo htmlspecialchars($a['email'] ?: '—'); ?><br><span style="color:var(--muted);font-size:11.5px"><?php echo htmlspecialchars($a['mobile'] ?: '—'); ?></span></td>
                <td><?php echo htmlspecialchars($a['location'] ?: '—'); ?></td>
                <td><?php echo acc_fmt_date($a['registered_at']); ?></td>
                <td><?php echo $a['last_login'] ? acc_fmt_date($a['last_login'], 'd M Y, h:i A') : '—'; ?></td>
                <td><span class="tag <?php echo $a['verified'] ? 'active' : 'pending'; ?>"><?php echo htmlspecialchars($a['verify_label']); ?></span></td>
                <td><span class="tag <?php echo $a['status_class']; ?>"><?php echo htmlspecialchars($a['status_label']); ?></span></td>
                <td>
                    <div class="action-menu-wrap">
                        <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        <div class="action-menu">
                            <a href="account_details.php?type=<?php echo $a['type']; ?>&id=<?php echo $a['raw_id']; ?>"><i class="fa-solid fa-eye"></i> View</a>
                            <?php if ($canManage): ?>
                                <?php if ($a['deleted']): ?>
                                    <button class="menu-success" onclick="accSetStatus('<?php echo $a['type']; ?>',<?php echo $a['raw_id']; ?>,'active')"><i class="fa-solid fa-toggle-on"></i> Activate</button>
                                <?php else: ?>
                                    <?php if ($a['status_class'] !== 'active'): ?>
                                    <button class="menu-success" onclick="accSetStatus('<?php echo $a['type']; ?>',<?php echo $a['raw_id']; ?>,'active')"><i class="fa-solid fa-check"></i> Activate</button>
                                    <?php else: ?>
                                    <button class="menu-danger" onclick="accSetStatus('<?php echo $a['type']; ?>',<?php echo $a['raw_id']; ?>,'suspended')"><i class="fa-solid fa-pause"></i> Suspend</button>
                                    <?php endif; ?>
                                    <button class="menu-danger" onclick="accSetStatus('<?php echo $a['type']; ?>',<?php echo $a['raw_id']; ?>,'blocked')"><i class="fa-solid fa-ban"></i> Block</button>
                                    <?php if ($a['type'] !== 'employee'): ?>
                                    <button class="menu-danger" onclick="accSetStatus('<?php echo $a['type']; ?>',<?php echo $a['raw_id']; ?>,'deactivated')"><i class="fa-solid fa-trash-can"></i> Deactivate</button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                            <?php if ($canVerify && !$a['verified'] && $a['type'] !== 'employee'): ?>
                            <button class="menu-success" onclick="accVerify('<?php echo $a['type']; ?>',<?php echo $a['raw_id']; ?>)"><i class="fa-solid fa-shield-halved"></i> Verify</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalPages > 1): ?>
    <div class="pagination">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
            <a href="accounts.php?<?php echo http_build_query(array_merge($_GET, ['page' => $p])); ?>" class="<?php echo $p === $page ? 'active' : ''; ?>"><?php echo $p; ?></a>
        <?php endfor; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
    </div>
</div>

<?php if ($tab === 'all'): ?>
<div class="card">
    <div class="card-head"><h2>Recent Account Activity</h2><a href="activity_logs.php" class="btn outline sm">View Full Log</a></div>
    <?php if (empty($recentActivity)): ?>
        <div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No recent account activity yet.</div>
    <?php else: ?>
    <div class="acc-activity">
        <?php foreach ($recentActivity as $r): ?>
        <div class="acc-activity-row">
            <i class="fa-solid fa-circle-info"></i>
            <div>
                <div><b><?php echo htmlspecialchars($r['admin_full_name'] ?? 'Admin'); ?></b> — <?php echo htmlspecialchars($r['description'] ?: $r['action']); ?></div>
                <div class="when"><?php echo acc_fmt_date($r['created_at'], 'd M Y, h:i A'); ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- Status change modal (Suspend / Block / Deactivate reason capture — spec section 9) -->
<div class="modal-overlay" id="accStatusModal">
    <div class="modal-box">
        <h3 id="accStatusTitle">Change Account Status</h3>
        <p>Provide a reason — it will be recorded in the activity log.</p>
        <div class="form-group full"><label>Reason</label><textarea id="accStatusReason" rows="3" placeholder="e.g. Repeated policy violation, fraudulent activity, customer request..."></textarea></div>
        <div class="err" id="accStatusErr" style="display:none"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('accStatusModal')">Cancel</button>
            <button class="btn danger" id="accStatusConfirmBtn" onclick="accConfirmStatus()">Confirm</button>
        </div>
    </div>
</div>

<script>
let accPending = null;
const accLabels = { active: 'Activate', suspended: 'Suspend', blocked: 'Block', deactivated: 'Deactivate' };

function accSetStatus(type, id, status) {
    accPending = { type, id, status };
    document.getElementById('accStatusTitle').textContent = (accLabels[status] || 'Change status of') + ' this account?';
    document.getElementById('accStatusReason').value = '';
    document.getElementById('accStatusErr').style.display = 'none';
    // Activation doesn't need a reason — confirm immediately with a lightweight prompt instead of the modal.
    if (status === 'active') {
        if (!confirm('Activate this account?')) { accPending = null; return; }
        accSubmitStatus('');
        return;
    }
    openModal('accStatusModal');
}

function accConfirmStatus() {
    const reason = document.getElementById('accStatusReason').value.trim();
    if (!reason) {
        const err = document.getElementById('accStatusErr');
        err.textContent = 'Please provide a reason.';
        err.style.display = 'block';
        return;
    }
    accSubmitStatus(reason);
    closeModal('accStatusModal');
}

function accSubmitStatus(reason) {
    if (!accPending) return;
    const { type, id, status } = accPending;
    fetch('account_action.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'set_status', type, id, status, reason })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { showToast('Account status updated.'); setTimeout(() => location.reload(), 500); }
        else { showToast(d.error || 'Update failed.', true); }
    })
    .catch(() => showToast('Network error — please try again.', true));
    accPending = null;
}

function accVerify(type, id) {
    if (!confirm('Mark this account as verified?')) return;
    fetch('account_action.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'verify', type, id })
    })
    .then(r => r.json())
    .then(d => {
        if (d.success) { showToast('Account verified.'); setTimeout(() => location.reload(), 500); }
        else { showToast(d.error || 'Update failed.', true); }
    })
    .catch(() => showToast('Network error — please try again.', true));
}

// ---------------------------------------------------------------------
// Kebab dropdown position fix — same as inventory.php / invoices.php /
// company_profile.php / roles.php. The shared toggleActionMenu()
// relocates/resizes the .action-menu div in a way plain CSS can't
// reliably follow (that's why it was showing as a full-width block
// pinned to wherever it landed in the DOM). This re-anchors it with
// position:fixed, computed fresh from the button's on-screen position
// every time it's opened.
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.action-menu-wrap').forEach(function (wrap) {
        var btn = wrap.querySelector('.kebab-btn');
        var menu = wrap.querySelector('.action-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function () {
            // Runs after the page's own onclick="toggleActionMenu(...)"
            // (that one fires first — it was already on the element
            // before this listener was attached).
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
                menu.style.setProperty('min-width', '180px', 'important');
                menu.style.setProperty('max-width', '240px', 'important');
                menu.style.setProperty('z-index', '9999', 'important');
                menu.style.setProperty('margin', '0', 'important');
            }, 0);
        });
    });
});
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
