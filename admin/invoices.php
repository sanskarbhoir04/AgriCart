<?php
// =====================================================================
// admin/invoices.php — Centralized Invoices hub (replaces the old
// top-level "Seller Invoices" nav item). Two tabs:
//   ?type=seller (default) — exact same data/columns as the previous
//                             admin/seller_invoices.php page.
//   ?type=buyer             — one row per order (the buyer-facing
//                             invoice), pulled straight from `orders` /
//                             `order_items` / `users` using the same
//                             schema-agnostic column-detection pattern
//                             already used by pages/invoice.php, so it
//                             never assumes a column exists before
//                             checking for it.
//
// admin/seller_invoices.php itself is left in place and fully working
// (nothing here removes it) — this page is just the new, single entry
// point the sidebar and dashboard now link to.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.view');
require_once __DIR__ . '/../includes/seller_functions.php';
require_once __DIR__ . '/includes/action_menu.php';

$type = ($_GET['type'] ?? 'seller') === 'buyer' ? 'buyer' : 'seller';

// ---------------------------------------------------------------------
// Schema-agnostic column helpers (same approach as admin/invoice.php)
// ---------------------------------------------------------------------
function inv_col_exists(mysqli $conn, string $table, string $col): bool {
    static $cache = [];
    $key = $table . '.' . $col;
    if (array_key_exists($key, $cache)) return $cache[$key];
    $t = $conn->real_escape_string($table);
    $c = $conn->real_escape_string($col);
    $res = $conn->query("SHOW COLUMNS FROM `$t` LIKE '$c'");
    return $cache[$key] = ($res && $res->num_rows > 0);
}
function inv_pick_col(mysqli $conn, string $table, array $candidates, ?string $default = null): ?string {
    foreach ($candidates as $c) { if (inv_col_exists($conn, $table, $c)) return $c; }
    return $default;
}

// ---------------------------------------------------------------------
// Top summary cards — dynamically computed, degrade to 0 if a table/
// column isn't present on this install yet (never a fatal error).
// ---------------------------------------------------------------------
$sellerInvoiceCount = 0; $sellerInvoiceValue = 0.0;
try {
    $r = $conn->query("SELECT COUNT(*) cnt, COALESCE(SUM(gross_amount),0) val FROM seller_invoices");
    if ($r) { $row = $r->fetch_assoc(); $sellerInvoiceCount = (int)$row['cnt']; $sellerInvoiceValue = (float)$row['val']; }
} catch (\Throwable $e) {}

$buyerInvoiceCount = 0; $buyerInvoiceValue = 0.0; $paidCount = 0; $pendingCount = 0; $cancelledCount = 0;
try {
    $oStatusCol = inv_pick_col($conn, 'orders', ['order_status', 'status']);
    $payStatusCol = inv_pick_col($conn, 'orders', ['payment_status', 'pay_status']);
    $totalCol = inv_pick_col($conn, 'orders', ['final_amount', 'grand_total', 'order_total', 'total_amount']);
    $selectTotal = $totalCol ? "COALESCE(SUM($totalCol),0)" : "0";
    $r = $conn->query("SELECT COUNT(*) cnt, $selectTotal val FROM orders" . ($oStatusCol ? " WHERE $oStatusCol != 'cancelled'" : ""));
    if ($r) { $row = $r->fetch_assoc(); $buyerInvoiceCount = (int)$row['cnt']; $buyerInvoiceValue = (float)$row['val']; }
    if ($payStatusCol) {
        $pr = $conn->query("SELECT COUNT(*) cnt FROM orders WHERE $payStatusCol = 'paid'");
        if ($pr) { $paidCount = (int)$pr->fetch_assoc()['cnt']; }
        // Matches the exact same condition the Buyer Invoices list filter uses
        // (payment_status = 'pending') so this card's number and the filtered
        // list it links to always agree — no more "card says 7, list says 0".
        $pp = $conn->query("SELECT COUNT(*) cnt FROM orders WHERE $payStatusCol = 'pending'");
        if ($pp) { $pendingCount = (int)$pp->fetch_assoc()['cnt']; }
    }
    if ($oStatusCol) {
        $cc = $conn->query("SELECT COUNT(*) cnt FROM orders WHERE $oStatusCol = 'cancelled'");
        if ($cc) { $cancelledCount = (int)$cc->fetch_assoc()['cnt']; }
    }
} catch (\Throwable $e) {}

$totalInvoices = $sellerInvoiceCount + $buyerInvoiceCount;
$totalInvoiceValue = $sellerInvoiceValue + $buyerInvoiceValue;

$pageTitle = 'Invoices';
$pageSubtitle = 'Track and manage every buyer and seller invoice on the platform.';
$activeTeamTab = 'invoices';
include __DIR__ . '/includes/team_layout_top.php';
?>
<div class="stats-row">
    <a class="stat-card" href="invoices.php"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-receipt"></i></div><div><div class="val"><?php echo $totalInvoices; ?></div><div class="lbl">Total Invoices</div></div></a>
    <a class="stat-card" href="?type=seller"><div class="icn" style="background:#2F4F44"><i class="fa-solid fa-store"></i></div><div><div class="val"><?php echo $sellerInvoiceCount; ?></div><div class="lbl">Seller Invoices</div></div></a>
    <a class="stat-card" href="?type=buyer"><div class="icn" style="background:#3B6FA8"><i class="fa-solid fa-user"></i></div><div><div class="val"><?php echo $buyerInvoiceCount; ?></div><div class="lbl">Buyer Invoices</div></div></a>
    <a class="stat-card" href="?type=buyer&pay_status=paid"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-circle-check"></i></div><div><div class="val"><?php echo $paidCount; ?></div><div class="lbl">Paid</div></div></a>
    <a class="stat-card" href="?type=buyer&pay_status=pending"><div class="icn" style="background:#F9A825"><i class="fa-solid fa-hourglass-half"></i></div><div><div class="val"><?php echo $pendingCount; ?></div><div class="lbl">Pending</div></div></a>
    <a class="stat-card" href="?type=buyer&order_status=cancelled"><div class="icn" style="background:#B71C1C"><i class="fa-solid fa-ban"></i></div><div><div class="val"><?php echo $cancelledCount; ?></div><div class="lbl">Cancelled</div></div></a>
    <a class="stat-card" href="invoices.php"><div class="icn" style="background:#8E5C2E"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="val">₹<?php echo number_format($totalInvoiceValue, 2); ?></div><div class="lbl">Total Invoice Value</div></div></a>
</div>
<style>
.stat-card{text-decoration:none;color:inherit}
.stat-card:visited,.stat-card:link,.stat-card:active,.stat-card:focus{color:inherit;background:#fff;outline:none}

/* ---- Actions dropdown restyle (white card, icon + label rows) ----
   The two tabs on this page use two different ⋮-menu implementations:
   • Seller tab (invoices_seller_tab.php) — the older .action-menu-wrap /
     .kebab-btn / .action-menu markup, same as accounts.php & inventory.php.
   • Buyer tab (invoices_buyer_tab.php)  — the shared render_action_menu()
     component (includes/action_menu.php), which prints .actions-cell /
     .actions-menu-btn / .actions-menu instead.
   Styling both here so whichever tab is open looks the same, and adding
   !important on the open state as a guard against the same stray
   inline-style bug that broke the dropdown position on the Accounts and
   Inventory pages. */

.action-menu-wrap{position:relative !important;display:inline-block}
.action-menu-wrap .kebab-btn,
.actions-cell{position:relative !important}
.actions-cell{display:inline-block}
.kebab-btn,.actions-menu-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text);transition:.15s ease}
.kebab-btn:hover,.actions-menu-btn:hover{background:var(--bg-soft);border-color:var(--primary)}

.action-menu,.actions-menu{position:absolute;top:calc(100% + 6px);right:0;background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.14);min-width:200px;padding:6px;z-index:60;display:none}
.action-menu.open,.actions-menu.open{
    display:block !important;position:absolute !important;top:calc(100% + 6px) !important;
    right:0 !important;left:auto !important;bottom:auto !important;
    width:auto !important;min-width:200px !important;max-width:240px !important;
}
.action-menu button,.action-menu a,
.actions-menu button,.actions-menu a{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:none;background:none;text-align:left;font-size:13px;border-radius:8px;cursor:pointer;color:var(--text);text-decoration:none;white-space:nowrap}
.action-menu button:hover,.action-menu a:hover,
.actions-menu button:hover,.actions-menu a:hover{background:var(--bg-soft)}
.action-menu i,.actions-menu i{width:16px;text-align:center;color:var(--muted)}
.action-menu hr,.actions-menu .divider{border:none;border-top:1px solid var(--border);margin:6px 2px}
.action-menu .menu-danger,.actions-menu .danger-item{color:#c0392b}
.action-menu .menu-danger i,.actions-menu .danger-item i{color:#c0392b}
.action-menu .menu-success,.actions-menu .menu-success{color:#1a7f37}
.action-menu .menu-success i,.actions-menu .menu-success i{color:#1a7f37}
</style>


<div class="card">
    <div style="display:flex;gap:8px;padding:16px 20px 0;border-bottom:1px solid var(--border);">
        <a href="?type=seller" style="padding:10px 18px;border-radius:10px 10px 0 0;font-weight:700;font-size:13.5px;text-decoration:none;
            <?php echo $type === 'seller' ? 'background:var(--primary);color:#fff;' : 'background:transparent;color:var(--muted);'; ?>">
            <i class="fa-solid fa-store"></i> Seller Invoices
        </a>
        <a href="?type=buyer" style="padding:10px 18px;border-radius:10px 10px 0 0;font-weight:700;font-size:13.5px;text-decoration:none;
            <?php echo $type === 'buyer' ? 'background:var(--primary);color:#fff;' : 'background:transparent;color:var(--muted);'; ?>">
            <i class="fa-solid fa-user"></i> Buyer Invoices
        </a>
    </div>

    <?php if ($type === 'seller'): ?>
        <?php include __DIR__ . '/includes/invoices_seller_tab.php'; ?>
    <?php else: ?>
        <?php include __DIR__ . '/includes/invoices_buyer_tab.php'; ?>
    <?php endif; ?>
</div>

<script>
// ---------------------------------------------------------------------
// Kebab dropdown position fix — same as accounts.php / inventory.php /
// company_profile.php / roles.php. Plain CSS (above) styles it right
// but the shared toggle script relocates/resizes the menu div in a way
// CSS can't reliably keep up with, so this re-anchors it with
// position:fixed, computed fresh from the button's on-screen position
// every time it's opened. Handles BOTH menu systems on this page:
//   • Seller tab: .action-menu-wrap > .kebab-btn + .action-menu
//   • Buyer tab:  .actions-cell    > .actions-menu-btn + .actions-menu
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.action-menu-wrap, .actions-cell').forEach(function (wrap) {
        var btn = wrap.querySelector('.kebab-btn, .actions-menu-btn');
        var menu = wrap.querySelector('.action-menu, .actions-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function () {
            // Runs after the page's own toggle handler (that one fires
            // first — it was already attached before this listener).
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
                menu.style.setProperty('min-width', '200px', 'important');
                menu.style.setProperty('max-width', '240px', 'important');
                menu.style.setProperty('z-index', '9999', 'important');
                menu.style.setProperty('margin', '0', 'important');
            }, 0);
        });
    });
});
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
