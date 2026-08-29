<?php
// =====================================================================
// admin/includes/action_menu.php
// ---------------------------------------------------------------------
// ONE reusable "⋮" Action-menu component for every list/table page in
// the Admin Panel. Renders the trigger button + dropdown using the
// shared assets/css/action-menu.css + assets/js/action-menu.js (both
// loaded once, globally, from includes/team_layout_top.php /
// team_layout_bottom.php) — so every table gets identical styling,
// positioning, animation, and keyboard/ARIA behaviour with none of
// that duplicated per page.
//
// This file is a CONFIGURATION + RENDERING layer only. It decides
// which actions are shown for a given module + record (permission
// checks via hasPermission(), status checks via each action's
// `when` callback) and prints the matching markup. It deliberately
// does NOT reinvent each module's backend call — every action's
// `onclick` simply calls that module's existing, already-working JS
// function (accSetStatus(), teamStatusAction(), submitProductDelete(),
// confirmAction(), etc.) or links straight to the existing page. That
// keeps this component safe to drop into any page without touching
// working CRUD/AJAX logic, and it still enforces RBAC on both ends:
// this file hides actions the admin can't perform, and every target
// endpoint (seller_action.php, product_action.php, ...) independently
// calls requirePermission()/hasPermission() again server-side before
// doing anything — never trust the dropdown alone.
//
// USAGE
// -----
//   require_once __DIR__ . '/includes/action_menu.php';
//   ...
//   <td class="actions-cell">
//       render_action_menu('sellers', $sellerRow); [inside PHP tags]
//   </td>
//
// Each row just needs an 'id' key (or pass $idField) and whatever
// status column the module's config checks (see ACTION_MENU_CONFIG
// below, e.g. 'status' for sellers, 'verified' for companies).
//
// Optional third argument lets a page override/extend a module's
// default action list for one call without touching the shared
// config, e.g. render_action_menu('sellers', $row, ['hide' => ['delete']]).
// =====================================================================

if (!function_exists('hasPermission')) {
    require_once __DIR__ . '/permissions.php';
}

/**
 * Central per-module action catalogue. Every entry:
 *   key      – stable identifier (used for overrides / dedupe)
 *   label    – visible text
 *   icon     – Font Awesome class (matches the icon families already
 *              used across the panel — do not reuse one generic icon
 *              for every action)
 *   perm     – permission key checked via hasPermission(); null = no
 *              extra gate (still requires the page's own
 *              requirePermission() for module.view to be reached at all)
 *   when     – fn(array $record): bool — status-based visibility.
 *              Defaults to always-visible.
 *   danger   – true => rendered in the danger/red style (Delete etc.)
 *   success  – true => rendered in the success/green style (Activate etc.)
 *   href     – fn(array $record): string — for plain links (View, etc.)
 *   onclick  – fn(array $record): string — raw JS for the onclick=""
 *              attribute (should usually call closeAllActionsMenus()
 *              first, matching the existing convention)
 *   divider_before – true => draw a separator above this item (used to
 *              visually group the danger action away from the rest)
 */
/**
 * Returns the config array (see doc-block above). A function rather than
 * a `const` because the entries contain closures (fn() => ...) for the
 * href/onclick/when callbacks — PHP constants must be resolvable at
 * compile time and can't hold closures, so a top-level `const` here
 * throws "Constant expression contains invalid operations". A function
 * with a static local var gives the same "define once, reuse everywhere"
 * behaviour without that restriction.
 */
function agri_action_menu_config(): array
{
    static $config = null;
    if ($config !== null) return $config;

    $config = [

    // ---- General / fallback (anything not listed below) ----
    'general' => [
        ['key' => 'view',   'label' => 'View',   'icon' => 'fa-solid fa-eye',  'perm' => null,
            'href' => fn($r) => $r['_view_url'] ?? '#'],
        ['key' => 'edit',   'label' => 'Edit',   'icon' => 'fa-solid fa-pen',  'perm' => null,
            'href' => fn($r) => $r['_edit_url'] ?? '#'],
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'fa-solid fa-trash-can', 'perm' => null,
            'danger' => true, 'divider_before' => true,
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Delete this record? This action cannot be undone.',function(){" . ($r['_delete_js'] ?? '') . "},{title:'Delete Record?',confirmLabel:'Delete'})"],
    ],

    // ---- Sellers ----
    'sellers' => [
        ['key' => 'view', 'label' => 'View', 'icon' => 'fa-solid fa-eye', 'perm' => 'sellers.view',
            'href' => fn($r) => 'account_details.php?type=seller&id=' . (int)$r['id']],
        ['key' => 'edit', 'label' => 'Edit', 'icon' => 'fa-solid fa-pen', 'perm' => 'sellers.edit',
            'href' => fn($r) => 'account_details.php?type=seller&id=' . (int)$r['id'] . '&edit=1'],
        ['key' => 'verify', 'label' => 'Verify', 'icon' => 'fa-solid fa-circle-check', 'perm' => 'sellers.verify',
            'success' => true, 'when' => fn($r) => empty($r['verified']),
            'onclick' => fn($r) => "closeAllActionsMenus();accVerify('seller'," . (int)$r['id'] . ")"],
        ['key' => 'suspend', 'label' => 'Suspend', 'icon' => 'fa-solid fa-pause', 'perm' => 'sellers.suspend',
            'when' => fn($r) => ($r['status'] ?? '') === 'active',
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Suspend this seller?',function(){accSetStatus('seller'," . (int)$r['id'] . ",'suspended')})"],
        ['key' => 'activate', 'label' => 'Activate', 'icon' => 'fa-solid fa-play', 'perm' => 'sellers.suspend',
            'success' => true, 'when' => fn($r) => ($r['status'] ?? '') !== 'active',
            'onclick' => fn($r) => "closeAllActionsMenus();accSetStatus('seller'," . (int)$r['id'] . ",'active')"],
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'fa-solid fa-trash-can', 'perm' => 'sellers.delete',
            'danger' => true, 'divider_before' => true,
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Delete this seller account? This action cannot be undone.',function(){accSetStatus('seller'," . (int)$r['id'] . ",'deactivated')},{title:'Delete Seller?',confirmLabel:'Delete'})"],
    ],

    // ---- Users / Team Members ----
    'users' => [
        ['key' => 'view', 'label' => 'View', 'icon' => 'fa-solid fa-eye', 'perm' => 'team.view',
            'href' => fn($r) => 'team_members.php?view=' . (int)$r['id']],
        ['key' => 'edit', 'label' => 'Edit', 'icon' => 'fa-solid fa-pen', 'perm' => 'team.edit',
            'href' => fn($r) => 'edit_team_member.php?id=' . (int)$r['id']],
        ['key' => 'permissions', 'label' => 'Manage Permissions', 'icon' => 'fa-solid fa-key', 'perm' => 'team.change_permissions',
            'href' => fn($r) => 'manage_permissions.php?member_id=' . (int)$r['id']],
        ['key' => 'deactivate', 'label' => 'Deactivate', 'icon' => 'fa-solid fa-toggle-off', 'perm' => 'team.disable',
            'when' => fn($r) => ($r['status'] ?? '') === 'active',
            'onclick' => fn($r) => "closeAllActionsMenus();teamStatusAction(" . (int)$r['id'] . ",'deactivate')"],
        ['key' => 'activate', 'label' => 'Activate', 'icon' => 'fa-solid fa-toggle-on', 'perm' => 'team.disable',
            'success' => true, 'when' => fn($r) => ($r['status'] ?? '') !== 'active',
            'onclick' => fn($r) => "closeAllActionsMenus();teamStatusAction(" . (int)$r['id'] . ",'activate')"],
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'fa-solid fa-user-xmark', 'perm' => 'team.delete',
            'danger' => true, 'divider_before' => true,
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Remove admin access for this team member? Their activity history will be kept.',function(){teamRemove(" . (int)$r['id'] . ")},{title:'Delete Team Member?',confirmLabel:'Delete'})"],
    ],

    // ---- Companies ----
    'companies' => [
        ['key' => 'view', 'label' => 'View Details', 'icon' => 'fa-solid fa-file-lines', 'perm' => 'companies.view',
            'href' => fn($r) => 'company_profile.php?id=' . (int)$r['id']],
        ['key' => 'edit', 'label' => 'Edit', 'icon' => 'fa-solid fa-pen', 'perm' => 'companies.edit',
            'onclick' => fn($r) => "closeAllActionsMenus();openCompanyForm(" . (int)$r['id'] . ")"],
        ['key' => 'manage', 'label' => 'Manage Company', 'icon' => 'fa-solid fa-building', 'perm' => 'companies.edit',
            'href' => fn($r) => 'company_products.php?id=' . (int)$r['id']],
        ['key' => 'verify', 'label' => 'Verify', 'icon' => 'fa-solid fa-circle-check', 'perm' => 'companies.verify',
            'success' => true, 'when' => fn($r) => empty($r['verified']),
            'onclick' => fn($r) => "closeAllActionsMenus();cmpToggleVerified(" . (int)$r['id'] . ")"],
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'fa-solid fa-trash-can', 'perm' => 'companies.delete',
            'danger' => true, 'divider_before' => true,
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Delete this company? This action cannot be undone.',function(){cmpDelete(" . (int)$r['id'] . ")},{title:'Delete Company?',confirmLabel:'Delete'})"],
    ],

    // ---- Products ----
    'products' => [
        ['key' => 'view', 'label' => 'View', 'icon' => 'fa-solid fa-eye', 'perm' => 'products.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openProductView(" . (int)$r['id'] . ")"],
        ['key' => 'edit', 'label' => 'Edit', 'icon' => 'fa-solid fa-pen', 'perm' => 'products.edit',
            'onclick' => fn($r) => "closeAllActionsMenus();openProductForm(" . (int)$r['id'] . ")"],
        ['key' => 'inventory', 'label' => 'View Inventory', 'icon' => 'fa-solid fa-boxes-stacked', 'perm' => 'products.view',
            'href' => fn($r) => 'inventory.php?product_id=' . (int)$r['id']],
        ['key' => 'approve', 'label' => 'Approve', 'icon' => 'fa-solid fa-circle-check', 'perm' => 'products.approve',
            'success' => true, 'when' => fn($r) => ($r['status'] ?? '') === 'pending',
            'onclick' => fn($r) => "closeAllActionsMenus();submitProductStatus(" . (int)$r['id'] . ",'approve')"],
        ['key' => 'reject', 'label' => 'Reject', 'icon' => 'fa-solid fa-ban', 'perm' => 'products.approve',
            'when' => fn($r) => ($r['status'] ?? '') === 'pending',
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Reject this product?',function(){submitProductStatus(" . (int)$r['id'] . ",'reject')})"],
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'fa-solid fa-trash-can', 'perm' => 'products.delete',
            'danger' => true, 'divider_before' => true, 'when' => fn($r) => empty($r['deleted']),
            'onclick' => fn($r) => "closeAllActionsMenus();submitProductDelete(" . (int)$r['id'] . "," . json_encode($r['name'] ?? '') . ")"],
    ],

    // ---- Orders ----
    'orders' => [
        ['key' => 'view_order', 'label' => 'View Order', 'icon' => 'fa-solid fa-eye', 'perm' => 'orders.view',
            'href' => fn($r) => 'index.php?tab=orders&order_id=' . (int)$r['id']],
        ['key' => 'view_invoice', 'label' => 'View Invoice', 'icon' => 'fa-solid fa-file-invoice', 'perm' => 'orders.view',
            'href' => fn($r) => 'invoice.php?order_id=' . (int)$r['id']],
        ['key' => 'track', 'label' => 'Track Order', 'icon' => 'fa-solid fa-truck', 'perm' => 'orders.view',
            'when' => fn($r) => !in_array(strtolower($r['order_status'] ?? ''), ['delivered', 'cancelled'], true),
            'href' => fn($r) => 'index.php?tab=orders&order_id=' . (int)$r['id'] . '&track=1'],
        ['key' => 'update_status', 'label' => 'Update Status', 'icon' => 'fa-solid fa-rotate', 'perm' => 'orders.edit',
            'when' => fn($r) => strtolower($r['order_status'] ?? '') !== 'cancelled',
            'onclick' => fn($r) => "closeAllActionsMenus();openOrderStatusUpdate(" . (int)$r['id'] . ")"],
        ['key' => 'refund', 'label' => 'Refund', 'icon' => 'fa-solid fa-rotate-left', 'perm' => 'orders.refund',
            'when' => fn($r) => strtolower($r['payment_status'] ?? '') === 'paid' && strtolower($r['order_status'] ?? '') !== 'refunded',
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Issue a refund for this order?',function(){submitOrderRefund(" . (int)$r['id'] . ")})"],
        ['key' => 'print', 'label' => 'Print', 'icon' => 'fa-solid fa-print', 'perm' => 'orders.view',
            'href' => fn($r) => 'invoice.php?order_id=' . (int)$r['id'] . '#print'],
    ],

    // ---- Transactions ----
    'transactions' => [
        ['key' => 'view', 'label' => 'View Details', 'icon' => 'fa-solid fa-eye', 'perm' => 'transactions.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openTransactionView(" . (int)$r['id'] . ")"],
        ['key' => 'view_txn', 'label' => 'View Transaction', 'icon' => 'fa-solid fa-file-invoice-dollar', 'perm' => 'transactions.view',
            'href' => fn($r) => 'invoice.php?order_id=' . (int)$r['id']],
        ['key' => 'download', 'label' => 'Download Receipt', 'icon' => 'fa-solid fa-download', 'perm' => 'transactions.view',
            'href' => fn($r) => 'invoice.php?order_id=' . (int)$r['id'] . '&download=1'],
        ['key' => 'print', 'label' => 'Print', 'icon' => 'fa-solid fa-print', 'perm' => 'transactions.view',
            'href' => fn($r) => 'invoice.php?order_id=' . (int)$r['id'] . '#print'],
    ],

    // ---- Seller Payouts ----
    'payouts' => [
        ['key' => 'view', 'label' => 'View Details', 'icon' => 'fa-solid fa-eye', 'perm' => 'payouts.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openPayoutView(" . (int)$r['id'] . ")"],
        ['key' => 'approve', 'label' => 'Approve', 'icon' => 'fa-solid fa-circle-check', 'perm' => 'payouts.approve',
            'success' => true, 'when' => fn($r) => ($r['status'] ?? '') === 'pending',
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Approve this payout?',function(){submitPayoutAction(" . (int)$r['id'] . ",'approve')})"],
        ['key' => 'process', 'label' => 'Process Payout', 'icon' => 'fa-solid fa-wallet', 'perm' => 'payouts.process',
            'when' => fn($r) => ($r['status'] ?? '') === 'approved',
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Process this payout now?',function(){submitPayoutAction(" . (int)$r['id'] . ",'process')})"],
        ['key' => 'settlement', 'label' => 'View Settlement', 'icon' => 'fa-solid fa-file-invoice', 'perm' => 'payouts.view',
            'when' => fn($r) => ($r['status'] ?? '') === 'processed',
            'href' => fn($r) => 'seller_payouts.php?settlement=' . (int)$r['id']],
        ['key' => 'print', 'label' => 'Print', 'icon' => 'fa-solid fa-print', 'perm' => 'payouts.view',
            'onclick' => fn($r) => "closeAllActionsMenus();window.print()"],
    ],

    // ---- Commission & Charges ----
    'commission' => [
        ['key' => 'view', 'label' => 'View Rule', 'icon' => 'fa-solid fa-eye', 'perm' => 'commission.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openCommissionView(" . (int)$r['id'] . ")"],
        ['key' => 'edit', 'label' => 'Edit Rule', 'icon' => 'fa-solid fa-pen', 'perm' => 'commission.edit',
            'onclick' => fn($r) => "closeAllActionsMenus();openCommissionForm(" . (int)$r['id'] . ")"],
        ['key' => 'disable', 'label' => 'Disable', 'icon' => 'fa-solid fa-pause', 'perm' => 'commission.edit',
            'when' => fn($r) => ($r['status'] ?? 'active') === 'active',
            'onclick' => fn($r) => "closeAllActionsMenus();submitCommissionStatus(" . (int)$r['id'] . ",'disabled')"],
        ['key' => 'activate', 'label' => 'Activate', 'icon' => 'fa-solid fa-play', 'perm' => 'commission.edit',
            'success' => true, 'when' => fn($r) => ($r['status'] ?? 'active') !== 'active',
            'onclick' => fn($r) => "closeAllActionsMenus();submitCommissionStatus(" . (int)$r['id'] . ",'active')"],
        ['key' => 'delete', 'label' => 'Delete', 'icon' => 'fa-solid fa-trash-can', 'perm' => 'commission.delete',
            'danger' => true, 'divider_before' => true,
            'onclick' => fn($r) => "closeAllActionsMenus();confirmAction('Delete this commission rule?',function(){deleteCategory(" . (int)$r['id'] . ")},{title:'Delete Rule?',confirmLabel:'Delete'})"],
    ],

    // ---- Reports ----
    'reports' => [
        ['key' => 'view', 'label' => 'View Report', 'icon' => 'fa-solid fa-eye', 'perm' => 'reports.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openOrderDetails(" . (int)$r['id'] . ")"],
        ['key' => 'export', 'label' => 'Export', 'icon' => 'fa-solid fa-file-export', 'perm' => 'reports.export',
            'href' => fn($r) => 'report_export.php?' . http_build_query($r['_export_params'] ?? [])],
        ['key' => 'print', 'label' => 'Print', 'icon' => 'fa-solid fa-print', 'perm' => 'reports.view',
            'onclick' => fn($r) => "closeAllActionsMenus();window.print()"],
        ['key' => 'details', 'label' => 'View Details', 'icon' => 'fa-solid fa-file-lines', 'perm' => 'reports.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openOrderDetails(" . (int)$r['id'] . ")"],
    ],

    // ---- Activity Logs / Login History ----
    'activity_logs' => [
        ['key' => 'view', 'label' => 'View Details', 'icon' => 'fa-solid fa-eye', 'perm' => 'activity_logs.view',
            'onclick' => fn($r) => "closeAllActionsMenus();openActivityLogDetails(" . (int)$r['id'] . ")"],
        ['key' => 'full', 'label' => 'View Full Activity', 'icon' => 'fa-solid fa-clock-rotate-left', 'perm' => 'activity_logs.view',
            'href' => fn($r) => 'activity_logs.php?user_id=' . (int)($r['admin_user_id'] ?? 0)],
        ['key' => 'export', 'label' => 'Export', 'icon' => 'fa-solid fa-file-export', 'perm' => 'activity_logs.export',
            'href' => fn($r) => 'activity_logs.php?export=1'],
    ],
    ];

    return $config;
}

/**
 * Renders the "⋮" trigger + its dropdown for one table row.
 *
 * @param string $module   Key into ACTION_MENU_CONFIG (falls back to 'general').
 * @param array  $record   The row's data. Needs at least an 'id'.
 * @param array  $opts     Optional:
 *                           'id_field' => alternate key to use for the record id
 *                           'label'    => human label for aria-label, e.g. "seller Acme Farms"
 *                           'hide'     => [action keys to force-hide for this call]
 *                           'extra'    => additional ad-hoc action defs (same shape as above)
 *                             appended after the module's own list
 */
function render_action_menu(string $module, array $record, array $opts = []): void
{
    $allConfig = agri_action_menu_config();
    $config = $allConfig[$module] ?? $allConfig['general'];
    if (!empty($opts['extra'])) {
        $config = array_merge($config, $opts['extra']);
    }
    $hide = $opts['hide'] ?? [];

    $idField = $opts['id_field'] ?? 'id';
    $recordId = (int)($record[$idField] ?? 0);
    $menuId = 'am_' . preg_replace('/[^a-zA-Z0-9_]/', '', $module) . '_' . $recordId;

    $visible = [];
    foreach ($config as $action) {
        if (in_array($action['key'], $hide, true)) continue;
        if (!empty($action['perm']) && !hasPermission($action['perm'])) continue;
        if (isset($action['when']) && !$action['when']($record)) continue;
        $visible[] = $action;
    }

    if (empty($visible)) {
        return; // nothing this admin can do to this record — don't show a dead button
    }

    $ariaLabel = 'Actions for ' . ($opts['label'] ?? ($module . ' record'));

    echo '<div class="actions-cell">';
    printf(
        '<button type="button" class="actions-menu-btn" title="Actions" aria-label="%s" onclick="toggleActionsMenu(event, %s)"><i class="fa-solid fa-ellipsis-vertical"></i></button>',
        htmlspecialchars($ariaLabel, ENT_QUOTES),
        json_encode($menuId)
    );
    echo '<div class="actions-menu" id="' . htmlspecialchars($menuId, ENT_QUOTES) . '">';

    foreach ($visible as $action) {
        if (!empty($action['divider_before'])) {
            echo '<div class="divider"></div>';
        }
        $classes = [];
        if (!empty($action['danger'])) $classes[] = 'danger-item';
        if (!empty($action['success'])) $classes[] = 'menu-success';
        $classAttr = $classes ? ' class="' . implode(' ', $classes) . '"' : '';
        $icon = htmlspecialchars($action['icon'] ?? 'fa-solid fa-circle', ENT_QUOTES);
        $label = htmlspecialchars($action['label'], ENT_QUOTES);

        if (isset($action['href'])) {
            $href = htmlspecialchars($action['href']($record), ENT_QUOTES);
            echo "<a href=\"{$href}\"{$classAttr}><i class=\"{$icon}\"></i> {$label}</a>";
        } else {
            $onclick = htmlspecialchars($action['onclick'] ? $action['onclick']($record) : '', ENT_QUOTES);
            echo "<button type=\"button\"{$classAttr} onclick=\"{$onclick}\"><i class=\"{$icon}\"></i> {$label}</button>";
        }
    }

    echo '</div></div>';
}
