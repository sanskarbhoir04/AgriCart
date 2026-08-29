<?php
// =====================================================================
// admin/company_profile.php — Full single-page profile for one company
// (a `sellers` row). Replaces the old popup/modal detail view: clicking
// a company card on companies.php now lands here.
//
// Shows, in one place:
//   - Logo, name, verified/active status, category, description, contact
//   - Every product this company has listed (stock, price, status)
//   - Payment / payout history, IF this company can be matched to a
//     registered seller login account (see "Linking to a seller login"
//     below) — the `sellers` directory row and a `users` (role=seller)
//     account are two separate concepts in this schema, so the match is
//     best-effort by name/business-name/email, not a hard foreign key.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/companies_schema.php';
require_once __DIR__ . '/includes/inventory_schema.php';
companies_bootstrap_schema($conn);
if (function_exists('inventory_bootstrap_schema')) { inventory_bootstrap_schema($conn); }
requirePermission('companies.view');

$id = (int)($_GET['id'] ?? 0);
$company = null;
if ($id > 0) {
    $stmt = $conn->prepare("SELECT * FROM sellers WHERE id = ?");
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
}

$pageTitle     = $company ? $company['name'] : 'Company not found';
$activeTeamTab = 'companies';
include __DIR__ . '/includes/team_layout_top.php';

if (!$company) {
    ?>
    <div class="card">
        <div class="empty-state">
            <i class="fa-solid fa-building-circle-xmark"></i>
            This company doesn't exist or may have been removed.
            <div style="margin-top:14px"><a href="companies.php" class="btn sm outline">Back to Companies</a></div>
        </div>
    </div>
    <?php
    include __DIR__ . '/includes/team_layout_bottom.php';
    exit;
}

// ---- Products belonging to this company — matched by the real
// products.seller_id FK when available, with a defensive fallback to the
// legacy farmer_name text match if that column isn't there yet on this
// server (see cmp_company_match() in includes/companies_schema.php). ----
$products = [];
$match = cmp_company_match($conn, $id, $company['name']);
$pStmt = $conn->prepare(
    "SELECT id, name, sku, category, price, discount_price, unit, stock, low_stock_threshold, image, is_active, approval_status,
            description, brand, product_condition, delivery_available, delivery_estimate, created_at
       FROM products WHERE {$match['sql']} ORDER BY id DESC"
);
$pStmt->bind_param($match['types'], ...$match['params']);
$pStmt->execute();
$pRes = $pStmt->get_result();
while ($row = $pRes->fetch_assoc()) { $products[] = $row; }

$statusFn = function ($p) {
    $stock = (int)$p['stock'];
    $threshold = (int)($p['low_stock_threshold'] ?? 10);
    return function_exists('inv_product_status') ? inv_product_status($stock, $threshold) : ['label' => $stock <= 0 ? 'Out of Stock' : 'In Stock', 'class' => $stock <= 0 ? 'suspended' : 'active'];
};

$totalProducts   = count($products);
$totalStockUnits = array_sum(array_map(fn($p) => (int)$p['stock'], $products));
$stockValue       = array_sum(array_map(fn($p) => (int)$p['stock'] * (float)($p['discount_price'] ?: $p['price']), $products));
$activeProducts  = count(array_filter($products, fn($p) => $statusFn($p)['label'] === 'In Stock'));
$lowStock        = count(array_filter($products, fn($p) => $statusFn($p)['label'] === 'Low Stock'));
$outOfStock      = count(array_filter($products, fn($p) => (int)$p['stock'] <= 0));
$pendingApproval = count(array_filter($products, fn($p) => ($p['approval_status'] ?? '') === 'pending'));

// ---- Total units sold per product, for the products table (best-effort
// — degrades quietly if order_items isn't set up on this install). ----
$soldMap = [];
if (!empty($products)) {
    try {
        $ids = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $soldStmt = $conn->prepare(
            "SELECT oi.product_id, SUM(oi.quantity) AS qty
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.order_status NOT IN ('cancelled','returned') AND oi.product_id IN ($placeholders)
              GROUP BY oi.product_id"
        );
        $soldStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $soldStmt->execute();
        $sRes = $soldStmt->get_result();
        while ($row = $sRes->fetch_assoc()) { $soldMap[(int)$row['product_id']] = (int)$row['qty']; }
    } catch (\Throwable $e) { /* Total Sold just shows 0 */ }
}

// ---- Stock movement history for this company's products — "when did it
// go in stock, when did it go low/out of stock" — read from the existing
// inventory_stock_history audit table (see includes/inventory_schema.php,
// same table Inventory management already writes to). Company-scoped by
// restricting item_id to this company's product IDs. ----
$stockHistory = [];
if (!empty($products) && function_exists('inv_product_status')) {
    try {
        $ids = array_column($products, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $hStmt = $conn->prepare(
            "SELECT h.*, p.name AS current_product_name FROM inventory_stock_history h
               LEFT JOIN products p ON p.id = h.item_id
              WHERE h.item_type = 'product' AND h.item_id IN ($placeholders)
              ORDER BY h.created_at DESC LIMIT 300"
        );
        $hStmt->bind_param(str_repeat('i', count($ids)), ...$ids);
        $hStmt->execute();
        $hRes = $hStmt->get_result();
        while ($row = $hRes->fetch_assoc()) {
            $prev = $row['previous_qty'] !== null ? (int)$row['previous_qty'] : null;
            $new  = $row['updated_qty'] !== null ? (int)$row['updated_qty'] : null;
            // Which stock "zone" this change landed in, so the modal can
            // filter by the same In Stock / Low Stock / Out of Stock the
            // rest of the page uses.
            $productRow = null;
            foreach ($products as $pp) { if ((int)$pp['id'] === (int)$row['item_id']) { $productRow = $pp; break; } }
            $threshold = $productRow ? (int)($productRow['low_stock_threshold'] ?? 10) : 10;
            $zone = $new === null ? '' : inv_product_status($new, $threshold)['label'];
            $stockHistory[] = [
                'product_name' => $row['current_product_name'] ?: $row['item_name'],
                'action'       => $row['action'],
                'previous_qty' => $prev,
                'updated_qty'  => $new,
                'zone'         => $zone,
                'updated_by'   => $row['updated_by'],
                'remarks'      => $row['remarks'],
                'created_at'   => $row['created_at'],
            ];
        }
    } catch (\Throwable $e) { /* inventory_stock_history not available yet — history modal just shows empty */ }
}

// ---- Best-effort link to a registered seller login account, so we can
// show real payment/payout history. Tried in order: business_name on
// their payout profile, then exact seller full_name, then seller email
// (only if this company record has one on file). First hit wins. This
// is a *lookup*, not a stored relationship — nothing is written back. ----
$linkedUser = null;
$payoutProfile = null;
$payouts = [];
try {
    $m1 = $conn->prepare(
        "SELECT u.id, u.full_name, u.email, u.mobile, spp.available_balance, spp.total_paid
           FROM seller_payout_profiles spp JOIN users u ON u.id = spp.user_id
          WHERE spp.business_name = ? LIMIT 1"
    );
    $m1->bind_param('s', $company['name']);
    $m1->execute();
    $linkedUser = $m1->get_result()->fetch_assoc() ?: null;

    if (!$linkedUser) {
        $m2 = $conn->prepare("SELECT id, full_name, email, mobile FROM users WHERE role = 'seller' AND full_name = ? LIMIT 1");
        $m2->bind_param('s', $company['name']);
        $m2->execute();
        $linkedUser = $m2->get_result()->fetch_assoc() ?: null;
    }
    if (!$linkedUser && !empty($company['email'])) {
        $m3 = $conn->prepare("SELECT id, full_name, email, mobile FROM users WHERE role = 'seller' AND email = ? LIMIT 1");
        $m3->bind_param('s', $company['email']);
        $m3->execute();
        $linkedUser = $m3->get_result()->fetch_assoc() ?: null;
    }

    if ($linkedUser) {
        if (!isset($linkedUser['available_balance'])) {
            $ppStmt = $conn->prepare("SELECT available_balance, total_paid FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
            $ppStmt->bind_param('i', $linkedUser['id']);
            $ppStmt->execute();
            $payoutProfile = $ppStmt->get_result()->fetch_assoc() ?: null;
        } else {
            $payoutProfile = ['available_balance' => $linkedUser['available_balance'], 'total_paid' => $linkedUser['total_paid']];
        }

        $poStmt = $conn->prepare("SELECT * FROM payouts WHERE seller_id = ? ORDER BY requested_at DESC LIMIT 50");
        $poStmt->bind_param('i', $linkedUser['id']);
        $poStmt->execute();
        $payouts = $poStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }
} catch (\Throwable $e) {
    // payouts / seller_payout_profiles not set up yet on this install —
    // the page just shows "no linked seller account" below.
    $linkedUser = null;
}

$isDeleted = !empty($company['deleted_at']);
$initial   = strtoupper(substr($company['name'] ?: '?', 0, 1));
$loc       = trim(implode(', ', array_filter([$company['village'] ?? '', $company['city'] ?? ''])));
?>
<style>
.note-box{background:var(--bg-soft);border-left:4px solid var(--primary);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--muted);margin-bottom:16px}

/* ---- Actions dropdown restyle (white card, icon + label rows) ----
   Visual styling only — the actual position/width is now force-set by
   a small script below (right after this block) because the shared
   toggleActionMenu() appears to relocate/resize the .action-menu div
   in a way plain CSS can't reliably override (that's why the earlier
   CSS-only fix didn't stick — a rule scoped to ".action-menu-wrap
   .action-menu" stops matching the moment the element is no longer a
   descendant of its wrapper). */
.kebab-btn{width:34px;height:34px;border-radius:10px;border:1px solid var(--border);background:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;color:var(--text);transition:.15s ease}
.kebab-btn:hover{background:var(--bg-soft);border-color:var(--primary)}
.action-menu{background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.14);padding:6px}
.action-menu button,.action-menu a{display:flex;align-items:center;gap:10px;width:100%;padding:8px 10px;border:none;background:none;text-align:left;font-size:13px;border-radius:8px;cursor:pointer;color:var(--text);text-decoration:none;white-space:nowrap}
.action-menu button:hover,.action-menu a:hover{background:var(--bg-soft)}
.action-menu i{width:16px;text-align:center;color:var(--muted)}
.action-menu .menu-danger{color:#c0392b}
.action-menu .menu-danger i{color:#c0392b}
.action-menu .menu-success{color:#1a7f37}
.action-menu .menu-success i{color:#1a7f37}
.cmp-asset-box{border:1.5px dashed var(--border);border-radius:10px;padding:12px;text-align:center}
.cmp-asset-box img{max-height:60px;max-width:100%;object-fit:contain;margin-bottom:8px}
.cmp-asset-empty{color:var(--muted);font-size:12px;padding:14px 0}
.cmp-asset-actions{display:flex;gap:8px;justify-content:center;margin-top:6px;flex-wrap:wrap}
.cp-head{display:flex;align-items:center;gap:18px;flex-wrap:wrap}
.cp-logo{width:76px;height:76px;border-radius:18px;background:var(--bg-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:28px;flex-shrink:0;overflow:hidden}
.cp-logo img{width:100%;height:100%;object-fit:cover}
.cp-name{font-size:21px;font-weight:700;display:flex;align-items:center;gap:8px}
.cp-name .fa-circle-check{color:var(--success);font-size:15px}
.cp-cat{font-size:13px;color:var(--muted);margin-top:2px}
.cp-actions{margin-left:auto;display:flex;gap:10px;flex-wrap:wrap}
.cp-actions button{border:none;color:#fff;padding:10px 18px;border-radius:9px;font-size:13px;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;gap:7px;transition:.15s;box-shadow:0 3px 8px rgba(0,0,0,.15)}
.cp-actions button:hover{transform:translateY(-2px);filter:brightness(1.07)}
.cp-actions button:active{transform:translateY(0)}
.cp-btn-edit{background:linear-gradient(135deg,#2C5B8F,#3E7FC1)}
.cp-btn-verify{background:linear-gradient(135deg,#B8860B,#E0A62E);color:#3a2c00}
.cp-btn-verify.on{background:linear-gradient(135deg,#1E8E5A,#2FB673);color:#fff}
.cp-btn-danger{background:linear-gradient(135deg,#9B3B37,#C6534E)}
.cp-btn-success{background:linear-gradient(135deg,#1E8E5A,#2FB673)}
.cp-stats{display:flex;gap:14px;flex-wrap:wrap;margin:20px 0 4px}
.cp-stat{position:relative;flex:1 1 190px;background:#fff;border:1px solid var(--border);border-left:4px solid transparent;border-radius:14px;padding:15px 18px;display:flex;align-items:center;gap:14px;text-decoration:none;color:inherit;transition:.18s ease}
.cp-stat .v{font-size:19px;font-weight:800;color:var(--text);line-height:1.2}
.cp-stat .l{font-size:11.5px;color:var(--muted);font-weight:600;margin-top:2px}
.cp-stat-icon{width:44px;height:44px;border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0;transition:transform .18s ease}
.cp-stat-click{cursor:pointer}
.cp-stat-click:hover{transform:translateY(-3px);box-shadow:0 10px 22px rgba(27,47,41,.12);border-left-width:4px}
.cp-stat-click:hover .cp-stat-icon{transform:scale(1.08)}
.cp-stat-click.active{box-shadow:0 0 0 2px currentColor inset;background:var(--bg-soft)}
.cp-stat-click::after{content:'\f1da';font-family:'Font Awesome 6 Free';font-weight:900;position:absolute;top:8px;right:10px;font-size:10px;opacity:.35}
.cp-stat-total{border-left-color:var(--primary);color:var(--primary)}
.cp-stat-total .cp-stat-icon{background:rgba(47,79,68,.12);color:var(--primary)}
.cp-stat-instock{border-left-color:var(--success);color:var(--success)}
.cp-stat-instock .cp-stat-icon{background:rgba(46,125,50,.14);color:var(--success)}
.cp-stat-outofstock{border-left-color:var(--danger);color:var(--danger)}
.cp-stat-outofstock .cp-stat-icon{background:rgba(155,59,55,.14);color:var(--danger)}
.cp-stat-pending{border-left-color:#D9822B;color:#C06A16}
.cp-stat-lowstock{border-left-color:#D9822B;color:#C06A16}
.cp-stat-lowstock .cp-stat-icon{background:rgba(217,130,43,.16);color:#C06A16}
.cp-stat-value{border-left-color:var(--accent);color:var(--accent)}
.cp-stat-value .cp-stat-icon{background:rgba(169,139,74,.16);color:var(--accent)}
.cp-stat-pending .cp-stat-icon{background:rgba(217,130,43,.16);color:#C06A16}
.cp-info-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:14px 24px;margin-top:16px;font-size:13px}
.cp-info-grid div b{display:block;color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:3px}
.cp-prod-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:14px}
.cp-prod-card{border:1px solid var(--border);border-radius:12px;padding:12px;display:flex;flex-direction:column;gap:8px;cursor:pointer;transition:.15s}
.cp-prod-card:hover{box-shadow:0 8px 20px rgba(0,0,0,.09);transform:translateY(-2px);border-color:#c9d6cf}
.cp-prod-img-wrap{width:100%;height:110px;border-radius:8px;background:var(--bg-soft);display:flex;align-items:center;justify-content:center;overflow:hidden;color:#b9c2bd;font-size:30px}
.cp-prod-img-wrap img{width:100%;height:100%;object-fit:cover}
.cp-prod-card .nm{font-size:13px;font-weight:600}
.cp-prod-card .meta{font-size:11.5px;color:var(--muted);display:flex;justify-content:space-between}
.cp-prod-card .pr{font-size:14px;font-weight:700;color:var(--primary)}
#productDetailModal .modal-box{max-width:520px}
.pd-img-wrap{width:100%;height:200px;border-radius:12px;background:var(--bg-soft);display:flex;align-items:center;justify-content:center;overflow:hidden;color:#b9c2bd;font-size:44px;margin-bottom:16px}
.pd-img-wrap img{width:100%;height:100%;object-fit:cover}
.pd-title{font-size:17px;font-weight:700;display:flex;align-items:center;gap:10px;flex-wrap:wrap}
.pd-price{font-size:20px;font-weight:700;color:var(--primary);margin:6px 0 14px}
.pd-price span{font-size:13px;color:var(--muted);text-decoration:line-through;font-weight:500;margin-left:8px}
.pd-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:12.5px;margin-bottom:14px}
.pd-grid div b{display:block;color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}
.pd-desc{font-size:13px;color:var(--text);line-height:1.6;background:var(--bg-soft);padding:12px 14px;border-radius:10px}
</style>

<div class="card">
    <div class="cp-head">
        <div class="cp-logo"><?php echo !empty($company['logo']) ? '<img src="' . htmlspecialchars(cmp_img_url($company['logo'])) . '" alt="" onerror="this.parentElement.textContent=' . json_encode($initial) . '">' : $initial; ?></div>
        <div>
            <div class="cp-name"><?php echo htmlspecialchars($company['name']); ?> <?php if (!empty($company['verified'])): ?><i class="fa-solid fa-circle-check" title="Verified"></i><?php endif; ?></div>
            <div class="cp-cat"><?php echo htmlspecialchars($company['category'] ?: 'Uncategorized'); ?> · <span class="tag <?php echo $isDeleted ? 'inactive' : 'active'; ?>"><?php echo $isDeleted ? 'Inactive' : 'Active'; ?></span></div>
        </div>
        <?php if (hasPermission('companies.approve')): ?>
        <div class="cp-actions">
            <button class="cp-btn-edit" onclick="openCompanyForm()"><i class="fa-solid fa-pen"></i> Edit</button>
            <button class="cp-btn-verify <?php echo !empty($company['verified']) ? 'on' : ''; ?>" onclick="toggleVerified()"><i class="fa-solid fa-shield-halved"></i> <?php echo !empty($company['verified']) ? 'Unverify' : 'Verify'; ?></button>
            <button class="<?php echo $isDeleted ? 'cp-btn-success' : 'cp-btn-danger'; ?>" onclick="toggleCompanyStatus()"><i class="fa-solid <?php echo $isDeleted ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i> <?php echo $isDeleted ? 'Activate' : 'Deactivate'; ?></button>
        </div>
        <?php endif; ?>
    </div>

    <p style="margin-top:16px;color:var(--text);font-size:13.5px;line-height:1.6"><?php echo nl2br(htmlspecialchars($company['description'] ?: 'No description added yet.')); ?></p>

    <div class="cp-info-grid">
        <div><b>Location</b><?php echo htmlspecialchars($loc ?: '—'); ?></div>
        <div><b>Mobile</b><?php echo htmlspecialchars($company['mobile'] ?: '—'); ?></div>
        <div><b>Email</b><?php echo htmlspecialchars($company['email'] ?: '—'); ?></div>
        <div><b>GST Number</b><?php echo htmlspecialchars($company['gstin'] ?: '—'); ?></div>
        <div><b>Joined</b><?php echo cmp_fmt_date($company['created_at'] ?? null); ?></div>
    </div>

    <div class="cp-stats">
        <div class="cp-stat cp-stat-click cp-stat-total active" id="statTotal" onclick="handleStatClick('all', this)">
            <div class="cp-stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div>
            <div><div class="v"><?php echo $totalProducts; ?></div><div class="l">Total Products</div></div>
        </div>
        <div class="cp-stat cp-stat-total">
            <div class="cp-stat-icon"><i class="fa-solid fa-layer-group"></i></div>
            <div><div class="v"><?php echo number_format($totalStockUnits); ?></div><div class="l">Total Stock (Units)</div></div>
        </div>
        <div class="cp-stat cp-stat-click cp-stat-instock" id="statInstock" onclick="handleStatClick('instock', this)">
            <div class="cp-stat-icon"><i class="fa-solid fa-circle-check"></i></div>
            <div><div class="v"><?php echo $activeProducts; ?></div><div class="l">In Stock</div></div>
        </div>
        <div class="cp-stat cp-stat-click cp-stat-lowstock" id="statLowstock" onclick="handleStatClick('lowstock', this)">
            <div class="cp-stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div>
            <div><div class="v"><?php echo $lowStock; ?></div><div class="l">Low Stock</div></div>
        </div>
        <div class="cp-stat cp-stat-click cp-stat-outofstock" id="statOutofstock" onclick="handleStatClick('outofstock', this)">
            <div class="cp-stat-icon"><i class="fa-solid fa-circle-xmark"></i></div>
            <div><div class="v"><?php echo $outOfStock; ?></div><div class="l">Out of Stock</div></div>
        </div>
        <div class="cp-stat cp-stat-value">
            <div class="cp-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
            <div><div class="v">₹<?php echo number_format($stockValue, 0); ?></div><div class="l">Stock Value</div></div>
        </div>
        <div class="cp-stat cp-stat-click cp-stat-pending" id="statPending" onclick="handleStatClick('pending', this)">
            <div class="cp-stat-icon"><i class="fa-solid fa-clock"></i></div>
            <div><div class="v"><?php echo $pendingApproval; ?></div><div class="l">Pending Approval</div></div>
        </div>
    </div>

    <div style="margin-top:16px">
        <a href="company_products.php?id=<?php echo (int)$company['id']; ?>" class="btn cp-btn-edit"><i class="fa-solid fa-boxes-stacked"></i> Manage Products</a>
    </div>
</div>

<div class="card" id="companyProductsCard">
    <div class="card-head"><h2 id="cpProductsHeading">Products by this Company (<?php echo $totalProducts; ?>)</h2></div>
    <?php if (empty($products)): ?>
        <div class="empty-state"><i class="fa-solid fa-box-open"></i>This company has no products listed yet.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table id="cpProdTable">
        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Total Sold</th><th>Added</th><th>Actions</th></tr></thead>
        <tbody id="cpProdGrid">
        <?php foreach ($products as $p):
            $stock = (int)$p['stock'];
            $st = $statusFn($p);
            $price = (float)($p['discount_price'] ?: $p['price']);
            $isPending = ($p['approval_status'] ?? '') === 'pending';
            $isLowStock = $st['label'] === 'Low Stock';
            $isInStock = $st['label'] === 'In Stock';
            $isOutOfStock = $stock <= 0;
            $sold = $soldMap[(int)$p['id']] ?? 0;
            $img = cmp_img_url($p['image'] ?? '');
        ?>
        <tr class="cp-prod-row" data-instock="<?php echo $isInStock ? '1' : '0'; ?>" data-lowstock="<?php echo $isLowStock ? '1' : '0'; ?>" data-outofstock="<?php echo $isOutOfStock ? '1' : '0'; ?>" data-pending="<?php echo $isPending ? '1' : '0'; ?>" onclick="showProductDetail(<?php echo (int)$p['id']; ?>)">
            <td>
                <div style="display:flex;align-items:center;gap:10px">
                    <div class="cp-prod-img-wrap" style="width:42px;height:42px;border-radius:8px">
                        <?php if ($img): ?><img src="<?php echo htmlspecialchars($img); ?>" alt="" onerror="this.src='../assets/images/products/default.jpg'; this.onerror=function(){this.style.display='none'; this.nextElementSibling.style.display='flex'}"><i class="fa-solid fa-box" style="display:none"></i><?php else: ?><i class="fa-solid fa-box"></i><?php endif; ?>
                    </div>
                    <div>
                        <div style="font-weight:600;font-size:13px"><?php echo htmlspecialchars($p['name']); ?></div>
                        <div style="font-size:11px;color:var(--muted)">SKU: <?php echo htmlspecialchars($p['sku'] ?: '—'); ?></div>
                    </div>
                </div>
            </td>
            <td><?php echo htmlspecialchars($p['category'] ?: '—'); ?></td>
            <td>₹<?php echo number_format($price, 0); ?></td>
            <td><?php echo $stock; ?> <?php echo htmlspecialchars($p['unit'] ?? ''); ?></td>
            <td><span class="tag <?php echo $st['class']; ?>"><?php echo $st['label']; ?></span></td>
            <td><?php echo (int)$sold; ?></td>
            <td style="font-size:11.5px;color:var(--muted)"><?php echo cmp_fmt_date($p['created_at'] ?? null); ?></td>
            <td onclick="event.stopPropagation()">
                <div class="action-menu-wrap">
                    <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="action-menu">
                        <a href="company_products.php?id=<?php echo (int)$company['id']; ?>"><i class="fa-solid fa-boxes-stacked"></i> Manage</a>
                    </div>
                </div>
            </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <div class="empty-state" id="cpProdEmptyFiltered" style="display:none"><i class="fa-solid fa-filter-circle-xmark"></i>No products match this filter.</div>
    <?php endif; ?>
</div>

<!-- Product detail popup -->
<div class="modal-overlay" id="productDetailModal">
    <div class="modal-box">
        <div class="pd-img-wrap" id="pdImgWrap"><i class="fa-solid fa-box"></i></div>
        <div class="pd-title"><span id="pdName"></span><span class="tag" id="pdStatus"></span></div>
        <div style="font-size:12px;color:var(--muted);margin-top:2px" id="pdCategory"></div>
        <div class="pd-price" id="pdPrice"></div>
        <div class="pd-grid">
            <div><b>Stock</b><span id="pdStock">—</span></div>
            <div><b>SKU</b><span id="pdSku">—</span></div>
            <div><b>Brand</b><span id="pdBrand">—</span></div>
            <div><b>Condition</b><span id="pdCondition">—</span></div>
            <div><b>Delivery</b><span id="pdDelivery">—</span></div>
            <div><b>Approval Status</b><span id="pdApproval">—</span></div>
            <div><b>Total Sold</b><span id="pdSold">—</span></div>
            <div><b>Product ID</b><span id="pdId">—</span></div>
        </div>
        <div class="pd-desc" id="pdDescWrap" style="display:none"><span id="pdDesc"></span></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('productDetailModal')">Close</button>
            <a href="company_products.php?id=<?php echo (int)$company['id']; ?>" class="btn cp-btn-edit">Manage Products</a>
        </div>
    </div>
</div>

<script>
const cpSoldMap = <?php echo json_encode($soldMap); ?>;
const cpProducts = <?php
    $productsForJs = array_map(function ($p) {
        $p['image'] = cmp_img_url($p['image'] ?? '');
        return $p;
    }, $products);
    echo json_encode($productsForJs);
?>;

function findProduct(id){ return cpProducts.find(p => parseInt(p.id) === parseInt(id)); }

function showProductDetail(id){
    const p = findProduct(id);
    if (!p) return;

    const imgWrap = document.getElementById('pdImgWrap');
    imgWrap.innerHTML = p.image
        ? `<img src="${p.image}" alt="" onerror="this.src='../assets/images/products/default.jpg'; this.onerror=function(){this.parentElement.innerHTML='<i class=\\'fa-solid fa-box\\'></i>'}">`
        : '<i class="fa-solid fa-box"></i>';

    document.getElementById('pdName').textContent = p.name || '';
    document.getElementById('pdCategory').textContent = p.category || 'Uncategorized';

    const stock = parseInt(p.stock || 0);
    const threshold = parseInt(p.low_stock_threshold || 10);
    const statusEl = document.getElementById('pdStatus');
    let statusLabel, statusClass;
    if (stock <= 0) { statusLabel = 'Out of Stock'; statusClass = 'suspended'; }
    else if (stock <= threshold) { statusLabel = 'Low Stock'; statusClass = 'pending'; }
    else { statusLabel = 'In Stock'; statusClass = 'active'; }
    statusEl.textContent = statusLabel;
    statusEl.className = 'tag ' + statusClass;

    const price = parseFloat(p.price || 0);
    const discount = p.discount_price ? parseFloat(p.discount_price) : null;
    document.getElementById('pdPrice').innerHTML = discount
        ? `₹${discount.toFixed(0)} <span>₹${price.toFixed(0)}</span>`
        : `₹${price.toFixed(0)}`;

    document.getElementById('pdStock').textContent = stock + ' ' + (p.unit || '');
    document.getElementById('pdSku').textContent = p.sku || '—';
    document.getElementById('pdBrand').textContent = p.brand || '—';
    document.getElementById('pdCondition').textContent = p.product_condition || '—';
    document.getElementById('pdDelivery').textContent = p.delivery_available == 1 ? (p.delivery_estimate || 'Available') : 'Not available';
    document.getElementById('pdApproval').textContent = p.approval_status ? (p.approval_status.charAt(0).toUpperCase() + p.approval_status.slice(1)) : '—';
    document.getElementById('pdSold').textContent = (cpSoldMap[p.id] || 0);
    document.getElementById('pdId').textContent = '#' + p.id;

    const descWrap = document.getElementById('pdDescWrap');
    if (p.description) { descWrap.style.display = 'block'; document.getElementById('pdDesc').textContent = p.description; }
    else { descWrap.style.display = 'none'; }

    openModal('productDetailModal');
}
</script>

<script>
function filterCompanyProducts(type, el){
    document.querySelectorAll('.cp-stat-click').forEach(s => s.classList.remove('active'));
    el.classList.add('active');
    document.getElementById('companyProductsCard').scrollIntoView({ behavior: 'smooth', block: 'start' });

    const cards = document.querySelectorAll('#cpProdGrid tr');
    let visible = 0;
    cards.forEach(c => {
        const show = type === 'all' || c.dataset[type] === '1';
        c.style.display = show ? '' : 'none';
        if (show) visible++;
    });
    const emptyEl = document.getElementById('cpProdEmptyFiltered');
    if (emptyEl) emptyEl.style.display = visible === 0 ? 'block' : 'none';

    const labels = { all: 'Products by this Company', instock: 'In Stock Products', lowstock: 'Low Stock Products', outofstock: 'Out of Stock Products', pending: 'Pending Approval Products' };
    const heading = document.getElementById('cpProductsHeading');
    if (heading) heading.textContent = (labels[type] || 'Products by this Company') + ' (' + visible + ')';
}

const cpStockHistory = <?php echo json_encode($stockHistory); ?>;

function handleStatClick(type, el){
    filterCompanyProducts(type, el);
    openStockHistoryModal(type);
}

function openStockHistoryModal(type){
    const titles = { all: 'All Stock Activity', instock: 'Stock Activity — Went In Stock', lowstock: 'Stock Activity — Went Low Stock', outofstock: 'Stock Activity — Went Out of Stock', pending: 'Products Pending Approval' };
    document.getElementById('shTitle').textContent = titles[type] || 'Stock Activity';

    if (type === 'pending') {
        const pending = cpProducts.filter(p => (p.approval_status || '') === 'pending');
        const body = document.getElementById('shBody');
        if (!pending.length) { body.innerHTML = '<div class="empty-state"><i class="fa-solid fa-clock"></i>No products pending approval.</div>'; }
        else {
            body.innerHTML = '<table><thead><tr><th>Product</th><th>Category</th><th>Added</th></tr></thead><tbody>' +
                pending.map(p => `<tr><td>${(p.name||'').replace(/</g,'&lt;')}</td><td>${(p.category||'—').replace(/</g,'&lt;')}</td><td>${p.created_at ? new Date(p.created_at).toLocaleDateString() : '—'}</td></tr>`).join('') +
                '</tbody></table>';
        }
        openModal('stockHistoryModal');
        return;
    }

    const zoneMap = { instock: 'In Stock', lowstock: 'Low Stock', outofstock: 'Out of Stock' };
    const entries = type === 'all' ? cpStockHistory : cpStockHistory.filter(h => h.zone === zoneMap[type]);

    const body = document.getElementById('shBody');
    if (!entries.length) {
        body.innerHTML = '<div class="empty-state"><i class="fa-solid fa-clock-rotate-left"></i>No stock activity recorded yet for this filter.<br><span style="font-size:11.5px">Activity is logged going forward whenever stock is updated (e.g. via Manage Products).</span></div>';
    } else {
        body.innerHTML = '<table><thead><tr><th>Date &amp; Time</th><th>Product</th><th>Change</th><th>Resulting Status</th><th>Updated By</th><th>Notes</th></tr></thead><tbody>' +
            entries.map(h => {
                const prev = h.previous_qty !== null ? h.previous_qty : '—';
                const upd = h.updated_qty !== null ? h.updated_qty : '—';
                const arrowColor = h.zone === 'Out of Stock' ? 'var(--danger)' : (h.zone === 'Low Stock' ? '#C06A16' : 'var(--success)');
                const zoneTag = h.zone ? `<span class="tag ${h.zone === 'Out of Stock' ? 'suspended' : (h.zone === 'Low Stock' ? 'pending' : 'active')}">${h.zone}</span>` : '—';
                return `<tr>
                    <td style="font-size:11.5px;color:var(--muted);white-space:nowrap">${h.created_at ? new Date(h.created_at).toLocaleString() : '—'}</td>
                    <td>${(h.product_name||'').replace(/</g,'&lt;')}</td>
                    <td style="color:${arrowColor};font-weight:600">${prev} → ${upd}</td>
                    <td>${zoneTag}</td>
                    <td style="font-size:12px">${(h.updated_by||'—').replace(/</g,'&lt;')}</td>
                    <td style="font-size:11.5px;color:var(--muted)">${(h.remarks||'').replace(/</g,'&lt;')}</td>
                </tr>`;
            }).join('') + '</tbody></table>';
    }
    openModal('stockHistoryModal');
}
</script>

<!-- Stock History modal -->
<div class="modal-overlay" id="stockHistoryModal">
    <div class="modal-box" style="max-width:720px">
        <h3 id="shTitle">Stock Activity</h3>
        <p style="font-size:12px;color:var(--muted);margin-bottom:12px">Full stock movement history for <?php echo htmlspecialchars($company['name']); ?> — every recorded stock change and when it happened.</p>
        <div id="shBody" style="max-height:420px;overflow-y:auto"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('stockHistoryModal')">Close</button>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2>Payment History</h2></div>
    <?php if (!$linkedUser): ?>
        <div class="note-box">
            <i class="fa-solid fa-circle-info"></i>
            This company entry isn't linked to a registered seller login account yet, so payout/payment history isn't available here.
            It's matched automatically by business name / seller name — once a seller account with a matching name or business name exists, their withdrawal history will appear on this page.
        </div>
    <?php else: ?>
        <div class="cp-stats" style="margin-top:0">
            <div class="cp-stat cp-stat-total">
                <div class="cp-stat-icon"><i class="fa-solid fa-wallet"></i></div>
                <div><div class="v">₹<?php echo number_format((float)($payoutProfile['available_balance'] ?? 0), 2); ?></div><div class="l">Available Balance</div></div>
            </div>
            <div class="cp-stat cp-stat-instock">
                <div class="cp-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div>
                <div><div class="v">₹<?php echo number_format((float)($payoutProfile['total_paid'] ?? 0), 2); ?></div><div class="l">Total Paid Out</div></div>
            </div>
            <div class="cp-stat cp-stat-pending">
                <div class="cp-stat-icon"><i class="fa-solid fa-receipt"></i></div>
                <div><div class="v"><?php echo count($payouts); ?></div><div class="l">Withdrawal Requests</div></div>
            </div>
        </div>
        <p style="font-size:11.5px;color:var(--muted);margin:10px 0 14px">Linked seller account: <strong><?php echo htmlspecialchars($linkedUser['full_name'] ?? ''); ?></strong> (<?php echo htmlspecialchars($linkedUser['mobile'] ?? $linkedUser['email'] ?? ''); ?>) — <a href="seller_payouts.php">review/approve withdrawals here</a>.</p>
        <?php if (empty($payouts)): ?>
            <div class="empty-state"><i class="fa-solid fa-receipt"></i>No withdrawal requests from this seller yet.</div>
        <?php else: ?>
        <table>
            <thead><tr><th>Amount</th><th>Method</th><th>Account Details</th><th>Requested</th><th>Status</th><th>Notes</th></tr></thead>
            <tbody>
            <?php foreach ($payouts as $po):
                $tagClass = $po['status'] === 'completed' ? 'active' : ($po['status'] === 'rejected' ? 'rejected' : 'pending');
            ?>
                <tr>
                    <td>₹<?php echo number_format((float)$po['amount'], 2); ?></td>
                    <td><?php echo htmlspecialchars(strtoupper($po['method'] ?? '—')); ?></td>
                    <td style="max-width:200px"><?php echo htmlspecialchars($po['account_details'] ?? '—'); ?></td>
                    <td><?php echo htmlspecialchars($po['requested_at'] ?? '—'); ?></td>
                    <td><span class="tag <?php echo $tagClass; ?>"><?php echo htmlspecialchars(ucfirst($po['status'])); ?></span></td>
                    <td style="max-width:180px;font-size:11.5px;color:var(--muted)"><?php echo htmlspecialchars($po['notes'] ?? ''); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    <?php endif; ?>
</div>

<div style="margin-bottom:20px"><a href="companies.php" class="btn sm outline"><i class="fa-solid fa-arrow-left"></i> Back to Companies</a></div>

<?php if (hasPermission('companies.approve')): ?>
<!-- Edit modal (same fields/behaviour as companies.php) -->
<div class="modal-overlay" id="companyFormModal">
    <div class="modal-box">
        <h3>Edit Company</h3>
        <p>Company profile shown on the public Companies directory.</p>
        <div class="form-group"><label>Company Name *</label><input type="text" id="cfName" value="<?php echo htmlspecialchars($company['name']); ?>"></div>
        <div class="form-grid">
            <div class="form-group"><label>Category / Business Type</label><input type="text" id="cfCategory" value="<?php echo htmlspecialchars($company['category'] ?? ''); ?>"></div>
            <div class="form-group"><label>GST Number (GSTIN)</label><input type="text" id="cfGstin" value="<?php echo htmlspecialchars($company['gstin'] ?? ''); ?>" maxlength="15" style="text-transform:uppercase"></div>
            <div class="form-group"><label>Logo URL</label><input type="text" id="cfLogo" value="<?php echo htmlspecialchars($company['logo'] ?? ''); ?>"></div>
        </div>
        <div class="form-group full"><label>Description</label><textarea id="cfDescription" rows="3"><?php echo htmlspecialchars($company['description'] ?? ''); ?></textarea></div>
        <div class="form-grid">
            <div class="form-group"><label>Mobile</label><input type="text" id="cfMobile" value="<?php echo htmlspecialchars($company['mobile'] ?? ''); ?>"></div>
            <div class="form-group"><label>Email</label><input type="text" id="cfEmail" value="<?php echo htmlspecialchars($company['email'] ?? ''); ?>"></div>
            <div class="form-group"><label>Village</label><input type="text" id="cfVillage" value="<?php echo htmlspecialchars($company['village'] ?? ''); ?>"></div>
            <div class="form-group"><label>City</label><input type="text" id="cfCity" value="<?php echo htmlspecialchars($company['city'] ?? ''); ?>"></div>
        </div>
        <div class="form-group"><label><input type="checkbox" id="cfVerified" style="width:auto" <?php echo !empty($company['verified']) ? 'checked' : ''; ?>> Verified company</label></div>
        <div class="form-group full"><label>Internal Notes</label><textarea id="cfNotes" rows="2"><?php echo htmlspecialchars($company['notes'] ?? ''); ?></textarea></div>

        <div class="form-group full" style="border-top:1px solid var(--border);margin-top:6px;padding-top:14px">
            <label style="margin-bottom:8px">Digital Signature &amp; Stamp <span style="font-weight:400;color:var(--muted)">— shown on Buyer Invoices for this company's products</span></label>
            <div class="form-grid">
                <div class="cmp-asset-box" id="cfSigBox">
                    <?php if (!empty($company['signature_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($company['signature_path']); ?>" alt="Signature">
                    <?php else: ?>
                        <div class="cmp-asset-empty">Not uploaded</div>
                    <?php endif; ?>
                    <div class="cmp-asset-actions">
                        <label class="btn sm outline" style="margin:0">Choose Signature<input type="file" id="cfSignatureFile" accept=".png,.jpg,.jpeg,.webp" style="display:none" onchange="cmpAssetPreview(this,'cfSigBox')"></label>
                        <button type="button" class="btn sm outline" id="cfSigRemoveBtn" style="<?php echo !empty($company['signature_path']) ? '' : 'display:none'; ?>" onclick="cmpAssetRemove('signature')">Remove</button>
                    </div>
                </div>
                <div class="cmp-asset-box" id="cfStampBox">
                    <?php if (!empty($company['stamp_path'])): ?>
                        <img src="../<?php echo htmlspecialchars($company['stamp_path']); ?>" alt="Stamp">
                    <?php else: ?>
                        <div class="cmp-asset-empty">Not uploaded</div>
                    <?php endif; ?>
                    <div class="cmp-asset-actions">
                        <label class="btn sm outline" style="margin:0">Choose Stamp<input type="file" id="cfStampFile" accept=".png,.jpg,.jpeg,.webp" style="display:none" onchange="cmpAssetPreview(this,'cfStampBox')"></label>
                        <button type="button" class="btn sm outline" id="cfStampRemoveBtn" style="<?php echo !empty($company['stamp_path']) ? '' : 'display:none'; ?>" onclick="cmpAssetRemove('stamp')">Remove</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="err" id="cfErr" style="display:none"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('companyFormModal')">Cancel</button>
            <button class="btn" onclick="saveCompany()">Save Company</button>
        </div>
    </div>
</div>

<script>
const cmpId = <?php echo (int)$company['id']; ?>;
let cmpRemoveFlags = { signature: false, stamp: false };

function openCompanyForm(){ openModal('companyFormModal'); }

function cmpAssetPreview(input, boxId){
    if (!input.files || !input.files[0]) return;
    const which = input.id === 'cfSignatureFile' ? 'signature' : 'stamp';
    cmpRemoveFlags[which] = false; // a new file replaces any pending removal
    const box = document.getElementById(boxId);
    const empty = box.querySelector('.cmp-asset-empty');
    const reader = new FileReader();
    reader.onload = e => {
        let img = box.querySelector('img');
        if (!img) { img = document.createElement('img'); box.insertBefore(img, box.firstChild); }
        img.src = e.target.result;
        if (empty) empty.style.display = 'none';
        document.getElementById(which === 'signature' ? 'cfSigRemoveBtn' : 'cfStampRemoveBtn').style.display = 'inline-block';
    };
    reader.readAsDataURL(input.files[0]);
}

function cmpAssetRemove(which){
    if (!confirm('Remove this ' + which + ' on save?')) return;
    cmpRemoveFlags[which] = true;
    const boxId = which === 'signature' ? 'cfSigBox' : 'cfStampBox';
    const fileId = which === 'signature' ? 'cfSignatureFile' : 'cfStampFile';
    document.getElementById(fileId).value = '';
    const box = document.getElementById(boxId);
    const img = box.querySelector('img');
    if (img) img.remove();
    let empty = box.querySelector('.cmp-asset-empty');
    if (!empty) { empty = document.createElement('div'); empty.className = 'cmp-asset-empty'; empty.textContent = 'Not uploaded'; box.insertBefore(empty, box.firstChild); }
    empty.style.display = 'block';
    document.getElementById(which === 'signature' ? 'cfSigRemoveBtn' : 'cfStampRemoveBtn').style.display = 'none';
}

function saveCompany(){
    const name = document.getElementById('cfName').value.trim();
    const errEl = document.getElementById('cfErr');
    if (!name) { errEl.textContent = 'Company name is required.'; errEl.style.display = 'block'; return; }
    errEl.style.display = 'none';

    const form = new FormData();
    form.append('action', 'save');
    form.append('id', cmpId);
    form.append('name', name);
    form.append('category', document.getElementById('cfCategory').value.trim());
    form.append('gstin', document.getElementById('cfGstin').value.trim().toUpperCase());
    form.append('logo', document.getElementById('cfLogo').value.trim());
    form.append('description', document.getElementById('cfDescription').value.trim());
    form.append('mobile', document.getElementById('cfMobile').value.trim());
    form.append('email', document.getElementById('cfEmail').value.trim());
    form.append('village', document.getElementById('cfVillage').value.trim());
    form.append('city', document.getElementById('cfCity').value.trim());
    form.append('verified', document.getElementById('cfVerified').checked ? '1' : '0');
    form.append('notes', document.getElementById('cfNotes').value.trim());

    const sigFile = document.getElementById('cfSignatureFile').files[0];
    const stampFile = document.getElementById('cfStampFile').files[0];
    if (sigFile) form.append('signature', sigFile);
    else if (cmpRemoveFlags.signature) form.append('remove_signature', '1');
    if (stampFile) form.append('stamp', stampFile);
    else if (cmpRemoveFlags.stamp) form.append('remove_stamp', '1');

    fetch('company_action.php', { method: 'POST', body: form })
    .then(r => r.json())
    .then(d => { if (d.success) { showToast('Company saved.'); location.reload(); } else { errEl.textContent = d.error || 'Save failed.'; errEl.style.display = 'block'; } })
    .catch(() => { errEl.textContent = 'Network error — please try again.'; errEl.style.display = 'block'; });
}

function toggleVerified(){
    fetch('company_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'toggle_verified', id: cmpId }) })
    .then(r => r.json())
    .then(d => { if (d.success) { showToast('Updated.'); setTimeout(() => location.reload(), 500); } else { showToast(d.error || 'Update failed.', true); } })
    .catch(() => showToast('Network error — please try again.', true));
}

function toggleCompanyStatus(){
    const isInactive = <?php echo $isDeleted ? 'true' : 'false'; ?>;
    if (!confirm(isInactive ? 'Activate this company?' : 'Deactivate this company? It will be hidden from the public directory.')) return;
    fetch('company_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'toggle_status', id: cmpId }) })
    .then(r => r.json())
    .then(d => { if (d.success) { showToast('Company ' + d.status + '.'); setTimeout(() => location.reload(), 500); } else { showToast(d.error || 'Update failed.', true); } })
    .catch(() => showToast('Network error — please try again.', true));
}
</script>
<?php endif; ?>

<script>
// ---------------------------------------------------------------------
// Kebab dropdown position fix (Products table "Actions" column).
// Deliberately OUTSIDE the hasPermission('companies.approve') block
// above — the products table and its kebab menu render for every admin
// on this page, not just those with that permission, so this needs to
// run unconditionally too.
//
// The shared toggleActionMenu() (from assets/js/action-menu.js) seems
// to relocate/restyle the .action-menu div in a way plain CSS can't
// reliably follow — that's why it was rendering as a full-width block
// pinned to wherever it landed in the DOM instead of a small card
// under the kebab button. This re-anchors it with position:fixed,
// computed fresh from the button's on-screen position every time it's
// opened, so it always lands in the right place no matter what the
// shared script does to it underneath.
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
