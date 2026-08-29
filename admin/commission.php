<?php
// =====================================================================
// admin/commission.php — Commission Management (spec §10).
//
// Replaces the two hardcoded 5%/10% PHP constants that used to live in
// pages/sell_product.php, pages/insert_product.php and
// pages/insert_equipment.php with a real, database-driven system:
//   - one global default (percentage / fixed / percentage+fixed, with
//     min/max caps, an effective-from date, and active/inactive status)
//   - optional category-specific overrides
//   - optional seller-specific overrides
// Resolution order (highest priority first) is documented in
// includes/commission_schema.php: seller -> category -> global default.
//
// Gated by the 'finance.commission' permission (Super Admin always passes).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/../includes/commission_schema.php';
commission_bootstrap_schema($conn);
requirePermission('finance.commission');

$global = null;
try {
    $g = $conn->query("SELECT * FROM commission_settings ORDER BY effective_from DESC, id DESC LIMIT 1");
    $global = $g ? $g->fetch_assoc() : null;
} catch (\Throwable $e) {}

$categoryOverrides = [];
try {
    $c = $conn->query("SELECT * FROM category_commission ORDER BY category ASC");
    if ($c) { while ($row = $c->fetch_assoc()) { $categoryOverrides[] = $row; } }
} catch (\Throwable $e) {}

$sellerOverrides = [];
try {
    $s = $conn->query("
        SELECT sc.*, s.name AS full_name, s.email, s.mobile
        FROM seller_commission sc
        JOIN sellers s ON s.id = sc.user_id
        ORDER BY sc.updated_at DESC
    ");
    if ($s) { while ($row = $s->fetch_assoc()) { $sellerOverrides[] = $row; } }
} catch (\Throwable $e) {}

// For the "add seller override" dropdown — the real Sellers directory
// (admin/seller_action.php / the Sellers tab), not `users`. Sellers in
// AgriCart are name/mobile directory records and don't need a login
// account, so filtering users by role='seller' left this dropdown empty.
$sellerUsers = [];
try {
    $su = $conn->query("SELECT id, name, mobile, email FROM sellers WHERE deleted_at IS NULL ORDER BY name ASC");
    if ($su) { while ($row = $su->fetch_assoc()) { $sellerUsers[] = $row; } }
} catch (\Throwable $e) {}

// Known product categories already in use, so the category-override
// dropdown offers real values instead of a free-text field that could
// typo-mismatch what insert_product.php actually stores.
$knownCategories = [];
try {
    $kc = $conn->query("SELECT DISTINCT category FROM products WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
    if ($kc) { while ($row = $kc->fetch_assoc()) { $knownCategories[] = $row['category']; } }
} catch (\Throwable $e) {}
if (!in_array('equipment_rental', $knownCategories, true)) { $knownCategories[] = 'equipment_rental'; }

$pageTitle     = 'Commission & Charges';
$pageSubtitle  = 'Manage platform commission, seller charges, and commission rules for AgriCart.';
$activeTeamTab = 'commission';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
/* Only page-specific tweaks live here — cards, stat-cards, tags, buttons,
   inputs and tables all reuse the shared dashboard component styles from
   team_layout_top.php, same as Seller Payouts / Payment Verification. */
.comm-form-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:14px;margin-bottom:14px}
.comm-form-grid label{display:block;font-size:12.5px;font-weight:600;color:var(--text);margin-bottom:6px}
.icon-btn{border:none;background:var(--bg-soft);color:var(--primary-dark);width:30px;height:30px;border-radius:7px;cursor:pointer;transition:transform .15s cubic-bezier(.34,1.56,.64,1), background .15s ease, box-shadow .15s ease}
.icon-btn:hover{transform:translateY(-2px) scale(1.08);box-shadow:0 4px 10px rgba(0,0,0,0.15)}
.icon-btn:active{transform:scale(.92)}
@media (max-width:768px){
    .comm-form-grid{grid-template-columns:1fr 1fr}
    .agri-table-wrap table{min-width:640px}
}
</style>

<div class="stats-row">
    <div class="stat-card"><div class="icn" style="background:#5A9802"><i class="fa-solid fa-globe"></i></div><div><div class="val"><?php echo number_format((float)($global['default_percent'] ?? 0), 2); ?>%</div><div class="lbl">Global Default Rate</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#2C5B8F"><i class="fa-solid fa-tags"></i></div><div><div class="val"><?php echo count($categoryOverrides); ?></div><div class="lbl">Category Overrides</div></div></div>
    <div class="stat-card"><div class="icn" style="background:#6A1B9A"><i class="fa-solid fa-user-tag"></i></div><div><div class="val"><?php echo count($sellerOverrides); ?></div><div class="lbl">Seller Overrides</div></div></div>
    <div class="stat-card"><div class="icn" style="background:<?php echo ($global['status'] ?? 'active') === 'active' ? '#2E7D32' : '#B71C1C'; ?>"><i class="fa-solid <?php echo ($global['status'] ?? 'active') === 'active' ? 'fa-circle-check' : 'fa-circle-pause'; ?>"></i></div><div><div class="val"><?php echo ucfirst($global['status'] ?? 'active'); ?></div><div class="lbl">Global Status</div></div></div>
</div>

<div class="card">
    <div class="card-head"><h2><i class="fa-solid fa-globe" style="color:var(--primary);margin-right:6px"></i>Global Default</h2></div>
    <p class="hint" style="margin:-10px 0 16px">Applies to every listing with no seller- or category-specific override.</p>
    <form id="globalCommissionForm" onsubmit="return saveGlobalCommission(event)">
        <div class="comm-form-grid">
            <div>
                <label>Commission Type</label>
                <select name="commission_type" id="gc_type">
                    <option value="percentage" <?php echo ($global['commission_type'] ?? '') === 'percentage' ? 'selected' : ''; ?>>Percentage</option>
                    <option value="fixed" <?php echo ($global['commission_type'] ?? '') === 'fixed' ? 'selected' : ''; ?>>Fixed Amount</option>
                    <option value="percentage_plus_fixed" <?php echo ($global['commission_type'] ?? '') === 'percentage_plus_fixed' ? 'selected' : ''; ?>>Percentage + Fixed</option>
                </select>
            </div>
            <div>
                <label>Default Commission (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="default_percent" id="gc_percent" value="<?php echo htmlspecialchars((string)($global['default_percent'] ?? 5.00)); ?>">
            </div>
            <div>
                <label>Fixed Amount (Rs)</label>
                <input type="number" step="0.01" min="0" name="default_fixed_amount" id="gc_fixed" value="<?php echo htmlspecialchars((string)($global['default_fixed_amount'] ?? 0)); ?>">
            </div>
            <div>
                <label>Minimum Commission (Rs)</label>
                <input type="number" step="0.01" min="0" name="min_commission" id="gc_min" value="<?php echo htmlspecialchars((string)($global['min_commission'] ?? 0)); ?>">
            </div>
            <div>
                <label>Maximum Commission (Rs)</label>
                <input type="number" step="0.01" min="0" name="max_commission" id="gc_max" value="<?php echo htmlspecialchars((string)($global['max_commission'] ?? '')); ?>" placeholder="No cap">
            </div>
            <div>
                <label>Effective From</label>
                <input type="date" name="effective_from" id="gc_eff" value="<?php echo htmlspecialchars($global['effective_from'] ?? date('Y-m-d')); ?>">
            </div>
            <div>
                <label>Status</label>
                <select name="status" id="gc_status">
                    <option value="active" <?php echo ($global['status'] ?? '') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($global['status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn"><i class="fa-solid fa-floppy-disk"></i> Save Global Default</button>
    </form>
</div>

<div class="card">
    <div class="card-head"><h2><i class="fa-solid fa-tags" style="color:var(--primary);margin-right:6px"></i>Category-Specific Commission</h2></div>
    <p class="hint" style="margin:-10px 0 16px">Overrides the global default for every listing in that category (e.g. "equipment_rental").</p>
    <form onsubmit="return addCategoryCommission(event)">
        <div class="comm-form-grid" style="grid-template-columns:2fr 1fr 1fr auto">
            <div>
                <label>Category</label>
                <select name="category" id="cat_category" required>
                    <option value="">Select category&hellip;</option>
                    <?php foreach ($knownCategories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div>
                <label>Commission (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="commission_percent" id="cat_percent" required>
            </div>
            <div>
                <label>Status</label>
                <select name="status" id="cat_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end">
                <button type="submit" class="btn" style="white-space:nowrap"><i class="fa-solid fa-plus"></i> Add / Update</button>
            </div>
        </div>
    </form>
    <div class="agri-table-wrap">
    <table>
        <thead><tr><th>Category</th><th>Commission</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody id="categoryTableBody">
        <?php if (empty($categoryOverrides)): ?>
            <tr><td colspan="5" class="empty-state">No category-specific overrides yet &mdash; the global default applies to all categories.</td></tr>
        <?php else: foreach ($categoryOverrides as $co): ?>
            <tr id="cat-row-<?php echo (int)$co['id']; ?>">
                <td><?php echo htmlspecialchars($co['category']); ?></td>
                <td><?php echo number_format((float)$co['commission_percent'], 2); ?>%</td>
                <td><span class="tag <?php echo $co['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($co['status']); ?></span></td>
                <td><?php echo htmlspecialchars(date('d M Y', strtotime($co['updated_at']))); ?></td>
                <td><button class="icon-btn" onclick="deleteCategoryCommission(<?php echo (int)$co['id']; ?>)" title="Remove override"><i class="fa-solid fa-trash"></i></button></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<div class="card">
    <div class="card-head"><h2><i class="fa-solid fa-user-tag" style="color:var(--primary);margin-right:6px"></i>Seller-Specific Commission</h2></div>
    <p class="hint" style="margin:-10px 0 16px">Highest priority &mdash; overrides both category and global default for that one seller's listings.</p>
    <form onsubmit="return addSellerCommission(event)">
        <div class="comm-form-grid" style="grid-template-columns:2fr 1fr 1fr auto">
            <div>
                <label>Seller</label>
                <select name="user_id" id="seller_user_id" required>
                    <option value="">Select seller&hellip;</option>
                    <?php if (empty($sellerUsers)): ?>
                    <option value="" disabled>No sellers yet &mdash; add one from the Sellers tab first</option>
                    <?php else: foreach ($sellerUsers as $su): ?>
                    <option value="<?php echo (int)$su['id']; ?>"><?php echo htmlspecialchars($su['name'] ?: $su['email'] ?: ('Seller #' . $su['id'])); ?> <?php echo $su['mobile'] ? '(' . htmlspecialchars($su['mobile']) . ')' : ''; ?></option>
                    <?php endforeach; endif; ?>
                </select>
            </div>
            <div>
                <label>Commission (%)</label>
                <input type="number" step="0.01" min="0" max="100" name="commission_percent" id="seller_percent" required>
            </div>
            <div>
                <label>Status</label>
                <select name="status" id="seller_status">
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                </select>
            </div>
            <div style="display:flex;align-items:flex-end">
                <button type="submit" class="btn" style="white-space:nowrap"><i class="fa-solid fa-plus"></i> Add / Update</button>
            </div>
        </div>
    </form>
    <div class="agri-table-wrap">
    <table>
        <thead><tr><th>Seller</th><th>Contact</th><th>Commission</th><th>Status</th><th>Updated</th><th></th></tr></thead>
        <tbody id="sellerTableBody">
        <?php if (empty($sellerOverrides)): ?>
            <tr><td colspan="6" class="empty-state">No seller-specific overrides yet.</td></tr>
        <?php else: foreach ($sellerOverrides as $so): ?>
            <tr id="seller-row-<?php echo (int)$so['id']; ?>">
                <td><?php echo htmlspecialchars($so['full_name'] ?: ('Seller #' . $so['user_id'])); ?></td>
                <td><?php echo htmlspecialchars($so['mobile'] ?: $so['email'] ?: '&mdash;'); ?></td>
                <td><?php echo number_format((float)$so['commission_percent'], 2); ?>%</td>
                <td><span class="tag <?php echo $so['status'] === 'active' ? 'active' : 'inactive'; ?>"><?php echo ucfirst($so['status']); ?></span></td>
                <td><?php echo htmlspecialchars(date('d M Y', strtotime($so['updated_at']))); ?></td>
                <td><button class="icon-btn" onclick="deleteSellerCommission(<?php echo (int)$so['id']; ?>)" title="Remove override"><i class="fa-solid fa-trash"></i></button></td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody>
    </table>
    </div>
</div>

<script>
function saveGlobalCommission(ev){
    ev.preventDefault();
    const btn = ev.target.querySelector('button[type=submit]');
    btn.disabled = true; const origLabel = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    const body = new URLSearchParams({
        action: 'save_global',
        commission_type: document.getElementById('gc_type').value,
        default_percent: document.getElementById('gc_percent').value,
        default_fixed_amount: document.getElementById('gc_fixed').value,
        min_commission: document.getElementById('gc_min').value,
        max_commission: document.getElementById('gc_max').value,
        effective_from: document.getElementById('gc_eff').value,
        status: document.getElementById('gc_status').value
    });
    fetch('commission_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r => r.json())
        .then(d => { if (d.success) { showToast('Global commission updated.'); } else { showToast(d.error || 'Save failed.', true); } })
        .catch(() => showToast('Network error — please try again.', true))
        .finally(() => { btn.disabled = false; btn.innerHTML = origLabel; });
    return false;
}
function addCategoryCommission(ev){
    ev.preventDefault();
    const btn = ev.target.querySelector('button[type=submit]');
    btn.disabled = true; const origLabel = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    const body = new URLSearchParams({
        action: 'save_category',
        category: document.getElementById('cat_category').value,
        commission_percent: document.getElementById('cat_percent').value,
        status: document.getElementById('cat_status').value
    });
    fetch('commission_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r => r.json())
        .then(d => { if (d.success) { showToast('Category commission saved.'); setTimeout(() => location.reload(), 500); } else { showToast(d.error || 'Save failed.', true); btn.disabled = false; btn.innerHTML = origLabel; } })
        .catch(() => { showToast('Network error — please try again.', true); btn.disabled = false; btn.innerHTML = origLabel; });
    return false;
}
function deleteCategoryCommission(id){
    if (!confirm('Remove this category override? Listings in this category will fall back to the global default.')) return;
    fetch('commission_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'delete_category', id }) })
        .then(r => r.json())
        .then(d => { if (d.success) { document.getElementById('cat-row-' + id)?.remove(); showToast('Override removed.'); } else { showToast(d.error || 'Delete failed.', true); } })
        .catch(() => showToast('Network error — please try again.', true));
}
function addSellerCommission(ev){
    ev.preventDefault();
    const btn = ev.target.querySelector('button[type=submit]');
    btn.disabled = true; const origLabel = btn.innerHTML; btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Saving…';
    const body = new URLSearchParams({
        action: 'save_seller',
        user_id: document.getElementById('seller_user_id').value,
        commission_percent: document.getElementById('seller_percent').value,
        status: document.getElementById('seller_status').value
    });
    fetch('commission_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body })
        .then(r => r.json())
        .then(d => { if (d.success) { showToast('Seller commission saved.'); setTimeout(() => location.reload(), 500); } else { showToast(d.error || 'Save failed.', true); btn.disabled = false; btn.innerHTML = origLabel; } })
        .catch(() => { showToast('Network error — please try again.', true); btn.disabled = false; btn.innerHTML = origLabel; });
    return false;
}
function deleteSellerCommission(id){
    if (!confirm('Remove this seller override? Their listings will fall back to the category/global rate.')) return;
    fetch('commission_action.php', { method:'POST', headers:{'Content-Type':'application/x-www-form-urlencoded'}, body: new URLSearchParams({ action:'delete_seller', id }) })
        .then(r => r.json())
        .then(d => { if (d.success) { document.getElementById('seller-row-' + id)?.remove(); showToast('Override removed.'); } else { showToast(d.error || 'Delete failed.', true); } })
        .catch(() => showToast('Network error — please try again.', true));
}
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
