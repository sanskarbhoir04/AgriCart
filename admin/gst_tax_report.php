<?php
// =====================================================================
// admin/gst_tax_report.php — Tax / GST (Finance Centre module).
//
// A financial GST report, distinct from admin/gst_verification_requests.php
// (the seller-GSTIN *compliance* approval queue, which stays exactly
// where it is — this page doesn't touch that workflow).
//
// AgriCart doesn't store a real gst_amount on orders/order_items on a
// fresh install (see admin/invoice.php's own comment on this) — prices
// are GST-inclusive and the tax portion is extracted at invoice time
// using the same government-slab-by-category table invoice.php uses.
// This report reuses that exact same rate table and formula so the
// numbers shown here always match what a generated invoice shows.
//
// Gated by 'finance.view' — same permission as Finance Center/Invoices.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('finance.view');

function gt_money($v): string { return '₹' . number_format((float)$v, 2); }

// Same slab table as admin/invoice.php::agi_gst_rate_for_category — kept
// in sync manually since these are two independent, defensively-written
// admin pages (see that file's own comment: not a legal ruling, verify
// with your CA for HSN-accurate rates).
function gt_gst_rate_for_category(?string $category): float {
    $c = strtolower(trim((string)$category));
    $map = [
        'seed' => 0.00, 'feed' => 0.00, 'fertil' => 0.05, 'pestic' => 0.18,
        'insectic' => 0.18, 'fungic' => 0.18, 'irrigat' => 0.12, 'tool' => 0.12,
        'implement' => 0.12, 'equipment' => 0.12, 'organic' => 0.05,
    ];
    foreach ($map as $needle => $rate) {
        if ($c !== '' && strpos($c, $needle) !== false) return $rate;
    }
    return 0.05;
}

$range = $_GET['range'] ?? 'this_month';
$allowedRanges = ['today','this_week','this_month','last_month','this_year','custom','all'];
if (!in_array($range, $allowedRanges, true)) { $range = 'this_month'; }
$today = date('Y-m-d');
switch ($range) {
    case 'today':      $from = $today; $to = $today; break;
    case 'this_week':  $from = date('Y-m-d', strtotime('monday this week')); $to = $today; break;
    case 'last_month':
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t', strtotime('last day of last month'));
        break;
    case 'this_year':  $from = date('Y-01-01'); $to = $today; break;
    case 'custom':
        $from = $_GET['from'] ?? '';
        $to   = $_GET['to'] ?? '';
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-30 days'));
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
        if ($from > $to) { [$from, $to] = [$to, $from]; }
        break;
    case 'all':         $from = '2000-01-01'; $to = '2100-01-01'; break;
    case 'this_month':
    default:            $from = date('Y-m-01'); $to = $today; break;
}

// ---------------------------------------------------------------------
// Walk every order item in range, extract GST the same inclusive-tax
// way invoice.php does, and roll it up overall + per seller.
// ---------------------------------------------------------------------
$totalTaxable = 0.0; $totalGst = 0.0; $totalGross = 0.0;
$bySeller = []; // seller_user_id => ['name'=>, 'gstin'=>, 'taxable'=>, 'gst'=>, 'orders'=>set]
try {
    $stmt = $conn->prepare("
        SELECT oi.quantity, oi.price, p.category, p.added_by_user_id AS seller_id,
               su.full_name AS seller_name, o.id AS order_id
        FROM order_items oi
        JOIN orders o ON o.id = oi.order_id
        JOIN products p ON p.id = oi.product_id
        LEFT JOIN users su ON su.id = p.added_by_user_id
        WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.order_status <> 'cancelled'
    ");
    $stmt->bind_param('ss', $from, $to);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $gross = (float)$r['quantity'] * (float)$r['price'];
        $rate  = gt_gst_rate_for_category($r['category']);
        $gst   = $rate > 0 ? round($gross - ($gross / (1 + $rate)), 2) : 0.0;
        $taxable = $gross - $gst;

        $totalGross   += $gross;
        $totalGst     += $gst;
        $totalTaxable += $taxable;

        $sid = (int)($r['seller_id'] ?? 0);
        if (!isset($bySeller[$sid])) {
            $bySeller[$sid] = ['name' => $r['seller_name'] ?: ('Seller #' . $sid), 'taxable' => 0.0, 'gst' => 0.0, 'orders' => []];
        }
        $bySeller[$sid]['taxable'] += $taxable;
        $bySeller[$sid]['gst']     += $gst;
        $bySeller[$sid]['orders'][$r['order_id']] = true;
    }
} catch (\Throwable $e) {}

// Real GSTIN + verification status per seller from the Sellers directory.
$sellerGstin = [];
try {
    $g = $conn->query("SELECT id, name, gstin, gst_verified FROM sellers WHERE deleted_at IS NULL");
    if ($g) { while ($row = $g->fetch_assoc()) { $sellerGstin[$row['name']] = $row; } }
} catch (\Throwable $e) {}
$registeredSellerCount = 0;
foreach ($sellerGstin as $sg) { if (!empty($sg['gstin'])) { $registeredSellerCount++; } }

uasort($bySeller, fn($a, $b) => $b['gst'] <=> $a['gst']);

$pageTitle     = 'Tax / GST';
$pageSubtitle  = 'GST collected across AgriCart orders, by seller and overall.';
$activeTeamTab = 'gst_tax_report';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.gt-filters{display:flex;flex-wrap:wrap;gap:10px;margin-bottom:18px;align-items:center}
.gt-filters select,.gt-filters input[type=date]{padding:8px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px}
@media (max-width:768px){ .agri-table-wrap{overflow-x:auto} .agri-table-wrap table{min-width:640px} }
</style>

<form method="get" class="gt-filters">
    <select name="range" onchange="this.form.submit()">
        <?php foreach (['today'=>'Today','this_week'=>'This Week','this_month'=>'This Month','last_month'=>'Last Month','this_year'=>'This Year','custom'=>'Custom','all'=>'All Time'] as $val => $label): ?>
        <option value="<?php echo $val; ?>" <?php echo $range === $val ? 'selected' : ''; ?>><?php echo $label; ?></option>
        <?php endforeach; ?>
    </select>
    <?php if ($range === 'custom'): ?>
    <input type="date" name="from" value="<?php echo htmlspecialchars($from); ?>">
    <input type="date" name="to" value="<?php echo htmlspecialchars($to); ?>">
    <?php endif; ?>
    <button class="btn" type="submit"><i class="fa-solid fa-filter"></i> Apply</button>
</form>

<div class="stats-row">
    <div class="stat-card"><div class="icn" style="background:#2E7D32"><i class="fa-solid fa-file-invoice"></i></div><div><div class="val"><?php echo gt_money($totalGst); ?></div><div class="lbl">GST Collected</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-indian-rupee-sign"></i></div><div><div class="val"><?php echo gt_money($totalTaxable); ?></div><div class="lbl">Taxable Value</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-cart-shopping"></i></div><div><div class="val"><?php echo gt_money($totalGross); ?></div><div class="lbl">Gross Order Value</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#6A1B9A"><i class="fa-solid fa-file-shield"></i></div><div><div class="val"><?php echo number_format($registeredSellerCount); ?></div><div class="lbl">GST-Registered Sellers</div></div></div>
</div>

<div class="empty-state" style="text-align:left;padding:0 0 16px;font-size:11.5px"><i class="fa-solid fa-circle-info"></i> GST is extracted from GST-inclusive prices using the same category rate table as the invoice generator — not a legal ruling; exact rate depends on each product's HSN code. Seller compliance/verification lives on the <a href="gst_verification_requests.php">GST Verification</a> page.</div>

<div class="card">
    <div class="card-head"><h2>GST Collected by Seller</h2></div>
    <div class="agri-table-wrap">
    <table>
        <thead><tr><th>Seller</th><th>GSTIN</th><th>Verification</th><th>Orders</th><th>Taxable Value</th><th>GST Collected</th></tr></thead>
        <tbody>
        <?php if (empty($bySeller)): ?>
            <tr><td colspan="6" class="empty-state">No GST activity for this filter.</td></tr>
        <?php else: foreach ($bySeller as $sid => $s):
            $dirRow = $sellerGstin[$s['name']] ?? null;
            $gstin = $dirRow['gstin'] ?? null;
            $verified = !empty($dirRow['gst_verified']);
        ?>
            <tr>
                <td><?php echo htmlspecialchars($s['name']); ?></td>
                <td><?php echo $gstin ? htmlspecialchars($gstin) : '—'; ?></td>
                <td><?php if ($gstin): ?><span class="tag <?php echo $verified ? 'active' : 'pending'; ?>"><?php echo $verified ? 'Verified' : 'Pending'; ?></span><?php else: ?><span class="tag inactive">Not Registered</span><?php endif; ?></td>
                <td><?php echo count($s['orders']); ?></td>
                <td><?php echo gt_money($s['taxable']); ?></td>
                <td><?php echo gt_money($s['gst']); ?></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
