<?php
// =====================================================================
// admin/includes/sidebar_nav.php — Single source of truth for the admin
// sidebar menu, grouped into sections. Included from BOTH index.php (the
// SPA dashboard) and team_layout_top.php (used by inventory.php,
// team_members.php, payment_verification.php, report.php, etc.) so the
// menu is identical no matter which page you're on.
//
// Expected variables from the including page (all optional):
//   $sidebarIsIndex   bool   true only when included from index.php itself
//                            — SPA items (Products/Orders/...) render as
//                            onclick=showTab(...) instead of a real link.
//   $agriFirstTab     string the currently-active SPA tab (index.php only)
//   $activeTeamTab    string the active item on non-SPA pages, e.g.
//                            'inventory','members','roles','reports', ...
//
// Every item is permission-gated with the same hasPermission() keys the
// rest of the app already uses, so nothing new is exposed to anyone.
// =====================================================================

$sidebarIsIndex = $sidebarIsIndex ?? false;
$agriFirstTab   = $agriFirstTab ?? '';
$activeTeamTab  = $activeTeamTab ?? '';

/**
 * Renders one menu item.
 * $permCheck is either a plain hasPermission() key, or ['module' => 'x']
 * to use canViewModule('x') instead (matches index.php's original logic,
 * which shows a module if the admin has ANY permission under it, not
 * just its own ".view" key).
 */
function agri_sidebar_item(string $icon, string $label, string $key, $permCheck, bool $spa, bool $sidebarIsIndex, string $agriFirstTab, string $activeTeamTab, string $href = ''): void
{
    if (!function_exists('hasPermission')) return;
    if (is_array($permCheck)) {
        if (!function_exists('canViewModule') || !canViewModule($permCheck['module'])) return;
    } else {
        if (!hasPermission($permCheck)) return;
    }

    if ($spa) {
        if ($sidebarIsIndex) {
            $active = $key === $agriFirstTab ? ' active' : '';
            echo '<div class="nav-item' . $active . '" data-tab="' . htmlspecialchars($key) . '" onclick="showTab(\'' . htmlspecialchars($key) . '\',this)"><i class="fa-solid ' . $icon . '"></i> ' . htmlspecialchars($label) . '</div>';
        } else {
            echo '<a href="index.php?tab=' . htmlspecialchars($key) . '" class="nav-item"><i class="fa-solid ' . $icon . '"></i> ' . htmlspecialchars($label) . '</a>';
        }
        return;
    }

    $active = $key === $activeTeamTab ? ' active' : '';
    $link = $href !== '' ? $href : $key . '.php';
    echo '<a href="' . htmlspecialchars($link) . '" class="nav-item' . $active . '"><i class="fa-solid ' . $icon . '"></i> ' . htmlspecialchars($label) . '</a>';
}

$agriCanVerifyPayments = function_exists('hasPermission') && (
    (function_exists('isSuperAdmin') && isSuperAdmin()) || hasPermission('rental_bookings.verify_payment')
);

/**
 * Renders a nav section: buffers the item callback's output and only
 * prints the "$label" heading + items if something actually rendered
 * (i.e. the admin has permission to see at least one item in it).
 * This keeps the sidebar from showing bare section headings like
 * "PRODUCT & MARKETPLACE" with nothing usable underneath for admins
 * who only have access to a subset of the panel.
 */
function agri_sidebar_section(string $label, callable $items): void
{
    ob_start();
    $items();
    $body = ob_get_clean();
    if (trim($body) === '') return;
    echo '<div class="nav-section-label">' . htmlspecialchars($label) . '</div>' . $body;
}
?>
<?php agri_sidebar_section('Dashboard', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-gauge-high', 'Dashboard', 'dashboard', 'dashboard.view', true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
}); ?>

<?php agri_sidebar_section('Accounts', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-address-card', 'Accounts', 'accounts', 'accounts.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'accounts.php');
}); ?>

<?php agri_sidebar_section('Product & Marketplace', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-cart-shopping', 'Products', 'products', ['module' => 'products'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-warehouse', 'Inventory', 'inventory', 'inventory.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'inventory.php');
    agri_sidebar_item('fa-tag', 'Coupons', 'coupons', ['module' => 'coupons'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-star', 'Reviews', 'reviews', ['module' => 'reviews'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
}); ?>

<?php agri_sidebar_section('Equipment Rental', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-tractor', 'Equipment', 'equipment', ['module' => 'equipment'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-calendar-check', 'Rental Bookings', 'bookings', ['module' => 'rental_bookings'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
}); ?>

<?php agri_sidebar_section('Order Management', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab, $agriCanVerifyPayments) {
    agri_sidebar_item('fa-truck-fast', 'Orders', 'orders', ['module' => 'orders'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    if ($agriCanVerifyPayments) {
        $active = $activeTeamTab === 'payment_verification' ? ' active' : '';
        echo '<a href="payment_verification.php" class="nav-item' . $active . '"><i class="fa-solid fa-money-check-dollar"></i> Payment Verification</a>';
    }
}); ?>

<?php agri_sidebar_section('Finance', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-chart-pie', 'Finance Overview', 'finance_overview', 'finance.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'finance_overview.php');
    agri_sidebar_item('fa-scale-balanced', 'Transactions', 'finance_center', 'finance.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'finance_center.php');
    agri_sidebar_item('fa-hand-holding-dollar', 'Seller Payouts', 'seller_payouts', 'finance.payout', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'seller_payouts.php');
    agri_sidebar_item('fa-clock-rotate-left', 'Settlement History', 'settlement_history', 'finance.payout', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'settlement_history.php');
    agri_sidebar_item('fa-percent', 'Commission & Charges', 'commission', 'finance.commission', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'commission.php');
    agri_sidebar_item('fa-rotate-left', 'Refunds', 'refunds', 'finance.refund', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'refunds.php');
    agri_sidebar_item('fa-file-invoice-dollar', 'Invoices', 'invoices', 'finance.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'invoices.php');
    agri_sidebar_item('fa-chart-line', 'Financial Reports', 'reports_finance_shortcut', 'reports.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'report.php');
    agri_sidebar_item('fa-file-shield', 'Tax / GST', 'gst_tax_report', 'finance.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'gst_tax_report.php');
}); ?>

<?php agri_sidebar_section('Agriculture Services', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-chart-line', 'Krishi Bazaar', 'bazaar', ['module' => 'bazaar'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-seedling', 'Advisory', 'advisory', ['module' => 'advisory'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-users', 'Agri-Connect', 'community', ['module' => 'community'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
}); ?>

<?php agri_sidebar_section('Customer Management', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-circle-user', 'Users', 'users', ['module' => 'users'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-store', 'Sellers', 'sellers', ['module' => 'sellers'], true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-building', 'Companies', 'companies', ['module' => 'companies'], false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'companies.php');
    agri_sidebar_item('fa-file-shield', 'GST Verification', 'gst_verification_requests', 'accounts.verify', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'gst_verification_requests.php');
    agri_sidebar_item('fa-envelope', 'Contact Messages', 'messages', 'support.view', true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
    agri_sidebar_item('fa-comment-dots', 'Feedback', 'feedback', 'feedback.manage', true, $sidebarIsIndex, $agriFirstTab, $activeTeamTab);
}); ?>

<?php agri_sidebar_section('Team Management', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-users-gear', 'Team Members', 'members', 'team.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'team_members.php');
    agri_sidebar_item('fa-user-plus', 'Add Team Member', 'add', 'team.create', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'add_team_member.php');
    agri_sidebar_item('fa-user-shield', 'Roles', 'roles', 'team.change_permissions', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'roles.php');
    agri_sidebar_item('fa-key', 'Permissions', 'permissions', 'team.change_permissions', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'manage_permissions.php');
    agri_sidebar_item('fa-clock-rotate-left', 'Activity Logs', 'activity', 'activity_logs.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'activity_logs.php');
    agri_sidebar_item('fa-right-to-bracket', 'Login History', 'logins', 'activity_logs.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'login_history.php');
}); ?>

<?php agri_sidebar_section('Reports', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-chart-pie', 'Reports & Analytics', 'reports', 'reports.view', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'report.php');
}); ?>

<?php agri_sidebar_section('Settings', function() use ($sidebarIsIndex, $agriFirstTab, $activeTeamTab) {
    agri_sidebar_item('fa-file-signature', 'Invoice Settings', 'invoice_settings', 'settings.invoice_manage', false, $sidebarIsIndex, $agriFirstTab, $activeTeamTab, 'invoice_settings.php');
}); ?>
