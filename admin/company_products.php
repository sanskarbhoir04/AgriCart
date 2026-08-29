<?php
// =====================================================================
// admin/company_products.php — "Manage Products" for ONE company.
//
// This is the page company cards / the company profile's "Manage
// Products" button link to. Per the spec: filtering must happen at the
// backend/database level (not just hiding rows in JS), and every action
// must re-verify the product actually belongs to the selected company
// before doing anything — see cmp_product_belongs_to_company() in
// includes/companies_schema.php, used both here and in
// company_product_action.php.
//
// Full Add/Edit/Delete of a product's core fields still lives in the
// existing Products tab (index.php) — that system is already properly
// permissioned and this page deliberately doesn't duplicate it (per the
// original brief: "do not duplicate existing product-detail system").
// What IS handled here, fully company-scoped and backend-verified, is:
// viewing, searching/filtering, quick stock updates, and activate/
// deactivate — the actions an admin actually needs while triaging one
// company's catalogue.
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/companies_schema.php';
require_once __DIR__ . '/includes/inventory_schema.php';
companies_bootstrap_schema($conn);
if (function_exists('inventory_bootstrap_schema')) { inventory_bootstrap_schema($conn); }
requirePermission('companies.view');

$companyId = (int)($_GET['id'] ?? $_GET['company_id'] ?? 0);
$company = null;
if ($companyId > 0) {
    $stmt = $conn->prepare("SELECT * FROM sellers WHERE id = ?");
    $stmt->bind_param('i', $companyId);
    $stmt->execute();
    $company = $stmt->get_result()->fetch_assoc();
}

$pageTitle     = $company ? ('Manage Products — ' . $company['name']) : 'Company not found';
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

// ---- Backend-level filters (SQL WHERE, not client-side hiding) ----
$q      = trim($_GET['q'] ?? '');
$status = trim($_GET['status'] ?? 'all');

$match  = cmp_company_match($conn, $companyId, $company['name'], 'p');
$where  = [$match['sql']];
$types  = $match['types'];
$params = $match['params'];

if ($q !== '') {
    $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
    $types  .= 'ss';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($status === 'active')        { $where[] = 'p.is_active = 1'; }
elseif ($status === 'inactive')  { $where[] = 'p.is_active = 0'; }
elseif ($status === 'instock')   { $where[] = 'p.stock > p.low_stock_threshold'; }
elseif ($status === 'lowstock')  { $where[] = 'p.stock > 0 AND p.stock <= p.low_stock_threshold'; }
elseif ($status === 'outofstock'){ $where[] = 'p.stock <= 0'; }

$whereSql = implode(' AND ', $where);
$stmt = $conn->prepare("SELECT p.* FROM products p WHERE $whereSql ORDER BY p.id DESC");
$stmt->bind_param($types, ...$params);
$stmt->execute();
$products = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// ---- Company-wide totals for the header summary (ignores the filters
// above — these always reflect the whole company, same numbers shown
// on company_profile.php, so the two pages never disagree). ----
$totMatch = cmp_company_match($conn, $companyId, $company['name']);
$totStmt = $conn->prepare(
    "SELECT COUNT(*) AS total_products, COALESCE(SUM(stock),0) AS total_stock,
            COALESCE(SUM(stock*price),0) AS stock_value,
            SUM(CASE WHEN stock <= 0 THEN 1 ELSE 0 END) AS out_of_stock,
            SUM(CASE WHEN stock > 0 AND stock <= low_stock_threshold THEN 1 ELSE 0 END) AS low_stock,
            SUM(CASE WHEN stock > low_stock_threshold THEN 1 ELSE 0 END) AS in_stock
       FROM products WHERE {$totMatch['sql']}"
);
$totStmt->bind_param($totMatch['types'], ...$totMatch['params']);
$totStmt->execute();
$totals = $totStmt->get_result()->fetch_assoc() ?: ['total_products'=>0,'total_stock'=>0,'stock_value'=>0,'out_of_stock'=>0,'low_stock'=>0,'in_stock'=>0];

// ---- Total units sold per product (best-effort — degrades quietly if
// order_items isn't set up on this install yet). ----
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
    } catch (\Throwable $e) { /* order_items not available — Total Sold just shows 0 */ }
}

$isDeleted = !empty($company['deleted_at']);
$initial   = strtoupper(substr($company['name'] ?: '?', 0, 1));
?>
<style>
.note-box{background:var(--bg-soft);border-left:4px solid var(--primary);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--muted);margin-bottom:16px}
.mp-head{display:flex;align-items:center;gap:16px;flex-wrap:wrap;margin-bottom:18px}
.mp-logo{width:56px;height:56px;border-radius:14px;background:var(--bg-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:20px;flex-shrink:0;overflow:hidden}
.mp-logo img{width:100%;height:100%;object-fit:cover}
.mp-eyebrow{font-size:11px;color:var(--muted);text-transform:uppercase;letter-spacing:.04em;font-weight:600}
.mp-title{font-size:19px;font-weight:700}
.cp-stats{display:flex;gap:14px;flex-wrap:wrap;margin-bottom:18px}
.cp-stat{position:relative;flex:1 1 150px;background:#fff;border:1px solid var(--border);border-left:4px solid transparent;border-radius:14px;padding:14px 16px;display:flex;align-items:center;gap:12px}
.cp-stat .v{font-size:18px;font-weight:800;color:var(--text);line-height:1.2}
.cp-stat .l{font-size:11px;color:var(--muted);font-weight:600;margin-top:2px}
.cp-stat-icon{width:38px;height:38px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.cp-stat-total{border-left-color:var(--primary)}.cp-stat-total .cp-stat-icon{background:rgba(47,79,68,.12);color:var(--primary)}
.cp-stat-instock{border-left-color:var(--success)}.cp-stat-instock .cp-stat-icon{background:rgba(46,125,50,.14);color:var(--success)}
.cp-stat-lowstock{border-left-color:#D9822B}.cp-stat-lowstock .cp-stat-icon{background:rgba(217,130,43,.16);color:#C06A16}
.cp-stat-outofstock{border-left-color:var(--danger)}.cp-stat-outofstock .cp-stat-icon{background:rgba(155,59,55,.14);color:var(--danger)}
.cp-stat-value{border-left-color:var(--accent)}.cp-stat-value .cp-stat-icon{background:rgba(169,139,74,.16);color:var(--accent)}
.mp-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:16px}
.mp-toolbar input[type=text]{max-width:240px}
.mp-toolbar select{max-width:170px}
.mp-thumb-wrap{width:42px;height:42px;border-radius:8px;background:var(--bg-soft);display:flex;align-items:center;justify-content:center;overflow:hidden;color:#b9c2bd;flex-shrink:0}
.mp-thumb-wrap img{width:100%;height:100%;object-fit:cover}
.mp-sku{font-size:11px;color:var(--muted)}
</style>

<div class="mp-head">
    <div class="mp-logo"><?php echo !empty($company['logo']) ? '<img src="' . htmlspecialchars(cmp_img_url($company['logo'])) . '" alt="" onerror="this.parentElement.textContent=' . json_encode($initial) . '">' : $initial; ?></div>
    <div>
        <div class="mp-eyebrow">Manage Products · Company</div>
        <div class="mp-title"><?php echo htmlspecialchars($company['name']); ?> <?php if (!empty($company['verified'])): ?><i class="fa-solid fa-circle-check" style="color:var(--success);font-size:14px"></i><?php endif; ?> <?php if ($isDeleted): ?><span class="tag inactive">Inactive</span><?php endif; ?></div>
    </div>
    <div style="margin-left:auto;display:flex;gap:8px">
        <a href="company_profile.php?id=<?php echo (int)$company['id']; ?>" class="btn sm outline"><i class="fa-solid fa-arrow-left"></i> Company Profile</a>
        <a href="index.php?tab=products&seller=<?php echo urlencode($company['name']); ?>" class="btn sm outline"><i class="fa-solid fa-plus"></i> Add / Full Edit</a>
    </div>
</div>

<div class="cp-stats">
    <div class="cp-stat cp-stat-total"><div class="cp-stat-icon"><i class="fa-solid fa-boxes-stacked"></i></div><div><div class="v"><?php echo (int)$totals['total_products']; ?></div><div class="l">Total Products</div></div></div>
    <div class="cp-stat cp-stat-total"><div class="cp-stat-icon"><i class="fa-solid fa-layer-group"></i></div><div><div class="v"><?php echo number_format((int)$totals['total_stock']); ?></div><div class="l">Total Stock (Units)</div></div></div>
    <div class="cp-stat cp-stat-instock"><div class="cp-stat-icon"><i class="fa-solid fa-circle-check"></i></div><div><div class="v"><?php echo (int)$totals['in_stock']; ?></div><div class="l">In Stock</div></div></div>
    <div class="cp-stat cp-stat-lowstock"><div class="cp-stat-icon"><i class="fa-solid fa-triangle-exclamation"></i></div><div><div class="v"><?php echo (int)$totals['low_stock']; ?></div><div class="l">Low Stock</div></div></div>
    <div class="cp-stat cp-stat-outofstock"><div class="cp-stat-icon"><i class="fa-solid fa-circle-xmark"></i></div><div><div class="v"><?php echo (int)$totals['out_of_stock']; ?></div><div class="l">Out of Stock</div></div></div>
    <div class="cp-stat cp-stat-value"><div class="cp-stat-icon"><i class="fa-solid fa-sack-dollar"></i></div><div><div class="v">₹<?php echo number_format((float)$totals['stock_value'], 0); ?></div><div class="l">Stock Value</div></div></div>
</div>

<div class="card">
    <div class="card-head"><h2>Products (<?php echo count($products); ?>)</h2></div>

    <form class="mp-toolbar" method="get">
        <input type="hidden" name="id" value="<?php echo (int)$companyId; ?>">
        <input type="text" name="q" placeholder="Search name or SKU..." value="<?php echo htmlspecialchars($q); ?>">
        <select name="status" onchange="this.form.submit()">
            <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All Products</option>
            <option value="instock" <?php echo $status === 'instock' ? 'selected' : ''; ?>>In Stock</option>
            <option value="lowstock" <?php echo $status === 'lowstock' ? 'selected' : ''; ?>>Low Stock</option>
            <option value="outofstock" <?php echo $status === 'outofstock' ? 'selected' : ''; ?>>Out of Stock</option>
            <option value="active" <?php echo $status === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        <button type="submit" class="btn sm cmp-add-btn"><i class="fa-solid fa-filter"></i> Apply</button>
        <?php if ($q !== '' || $status !== 'all'): ?><a href="company_products.php?id=<?php echo (int)$companyId; ?>" class="btn sm outline">Reset</a><?php endif; ?>
    </form>

    <?php if (empty($products)): ?>
        <div class="empty-state"><i class="fa-solid fa-box-open"></i>No products match this search/filter for <?php echo htmlspecialchars($company['name']); ?>.</div>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
        <thead><tr><th>Product</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th>Total Sold</th><th>Added</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($products as $p):
            $stock = (int)$p['stock'];
            $threshold = (int)($p['low_stock_threshold'] ?? 10);
            $st = function_exists('inv_product_status') ? inv_product_status($stock, $threshold) : ['label' => $stock <= 0 ? 'Out of Stock' : 'In Stock', 'class' => $stock <= 0 ? 'suspended' : 'active'];
            $price = (float)($p['discount_price'] ?: $p['price']);
            $sold = $soldMap[(int)$p['id']] ?? 0;
            $img = cmp_img_url($p['image'] ?? '');
        ?>
            <tr>
                <td>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div class="mp-thumb-wrap">
                            <?php if ($img): ?><img src="<?php echo htmlspecialchars($img); ?>" alt="" onerror="this.parentElement.innerHTML='<i class=&quot;fa-solid fa-box&quot;></i>'"><?php else: ?><i class="fa-solid fa-box"></i><?php endif; ?>
                        </div>
                        <div>
                            <div style="font-weight:600;font-size:13px"><?php echo htmlspecialchars($p['name']); ?></div>
                            <div class="mp-sku">SKU: <?php echo htmlspecialchars($p['sku'] ?: '—'); ?></div>
                        </div>
                    </div>
                </td>
                <td><?php echo htmlspecialchars($p['category'] ?: '—'); ?></td>
                <td>₹<?php echo number_format($price, 0); ?></td>
                <td><?php echo $stock; ?> <?php echo htmlspecialchars($p['unit'] ?? ''); ?></td>
                <td><span class="tag <?php echo $st['class']; ?>"><?php echo $st['label']; ?></span> <?php echo empty($p['is_active']) ? '<span class="tag inactive">Inactive</span>' : ''; ?></td>
                <td><?php echo (int)$sold; ?></td>
                <td style="font-size:11.5px;color:var(--muted)"><?php echo cmp_fmt_date($p['created_at'] ?? null); ?></td>
                <td>
                    <?php if (hasPermission('companies.approve')): ?>
                    <div class="action-menu-wrap">
                        <button type="button" class="kebab-btn" title="Actions" onclick="toggleActionMenu(event,this)"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                        <div class="action-menu">
                            <button onclick="openStockModal(<?php echo (int)$p['id']; ?>, <?php echo $stock; ?>, '<?php echo htmlspecialchars($p['name'], ENT_QUOTES); ?>')"><i class="fa-solid fa-cubes-stacked"></i> Update Stock</button>
                            <button onclick="toggleProductStatus(<?php echo (int)$p['id']; ?>, this)"><i class="fa-solid <?php echo empty($p['is_active']) ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i> <?php echo empty($p['is_active']) ? 'Activate' : 'Deactivate'; ?></button>
                        </div>
                    </div>
                    <?php else: ?>—<?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    </div>
    <?php endif; ?>
</div>

<?php if (hasPermission('companies.approve')): ?>
<div class="modal-overlay" id="stockModal">
    <div class="modal-box">
        <h3>Update Stock</h3>
        <p id="stockProductName"></p>
        <input type="hidden" id="stockProductId">
        <div class="form-group"><label>New Stock Quantity</label><input type="number" id="stockValue" min="0"></div>
        <div class="err" id="stockErr" style="display:none"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('stockModal')">Cancel</button>
            <button class="btn" onclick="saveStock()">Save</button>
        </div>
    </div>
</div>

<script>
const cmpCompanyId = <?php echo (int)$companyId; ?>;

function openStockModal(productId, currentStock, name){
    document.getElementById('stockErr').style.display = 'none';
    document.getElementById('stockProductId').value = productId;
    document.getElementById('stockProductName').textContent = name;
    document.getElementById('stockValue').value = currentStock;
    openModal('stockModal');
}

function saveStock(){
    const productId = document.getElementById('stockProductId').value;
    const stock = document.getElementById('stockValue').value;
    const errEl = document.getElementById('stockErr');
    if (stock === '' || parseInt(stock) < 0) { errEl.textContent = 'Enter a valid stock quantity.'; errEl.style.display = 'block'; return; }

    fetch('company_product_action.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'update_stock', company_id: cmpCompanyId, product_id: productId, stock })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { showToast('Stock updated.'); closeModal('stockModal'); location.reload(); } else { errEl.textContent = d.error || 'Update failed.'; errEl.style.display = 'block'; } })
    .catch(() => { errEl.textContent = 'Network error — please try again.'; errEl.style.display = 'block'; });
}

function toggleProductStatus(productId, btn){
    fetch('company_product_action.php', {
        method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ action: 'toggle_status', company_id: cmpCompanyId, product_id: productId })
    })
    .then(r => r.json())
    .then(d => { if (d.success) { showToast('Status updated.'); setTimeout(() => location.reload(), 400); } else { showToast(d.error || 'Update failed.', true); } })
    .catch(() => showToast('Network error — please try again.', true));
}
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
