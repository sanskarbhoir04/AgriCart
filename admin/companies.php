<?php
// =====================================================================
// admin/companies.php — Companies Management.
//
// This is the admin-side counterpart of the public Companies directory
// (see /companies.php and /company_profile.php at the site root once
// those are wired up). It reuses the existing `sellers` table — see
// includes/companies_schema.php for why no new table was created — so
// every company here is the exact same record the storefront, product
// listings (products.farmer_name), invoices and reports already use.
//
// Gated by the 'companies.view' permission (Super Admin always passes).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
require_once __DIR__ . '/includes/companies_schema.php';
companies_bootstrap_schema($conn);
requirePermission('companies.view');

$sellersTableExists = false;
try { $chk = $conn->query("SELECT 1 FROM sellers LIMIT 1"); $sellersTableExists = (bool)$chk; } catch (\Throwable $e) {}

$companies = [];
$categories = [];
$locations = [];

if ($sellersTableExists) {
    // Backfill, same as the Sellers tab on the dashboard — any farmer_name
    // used on a product but not yet a seller/company record becomes one.
    $conn->query("
        INSERT INTO sellers (name)
        SELECT DISTINCT p.farmer_name FROM products p
        WHERE p.farmer_name IS NOT NULL AND p.farmer_name <> ''
          AND p.farmer_name NOT IN (SELECT name FROM sellers)
    ");

    $search    = trim($_GET['q'] ?? '');
    $category  = trim($_GET['category'] ?? '');
    $location  = trim($_GET['location'] ?? '');
    $verified  = trim($_GET['verified'] ?? '');
    $status    = trim($_GET['status'] ?? 'active');
    $sort      = trim($_GET['sort'] ?? 'name');

    $where = [];
    $types = '';
    $params = [];

    if ($status === 'active')   { $where[] = 's.deleted_at IS NULL'; }
    elseif ($status === 'inactive') { $where[] = 's.deleted_at IS NOT NULL'; }
    // status === 'all' -> no filter

    if ($search !== '') {
        $where[] = 's.name LIKE ?';
        $params[] = '%' . $search . '%';
        $types .= 's';
    }
    if ($category !== '') {
        $where[] = 's.category = ?';
        $params[] = $category;
        $types .= 's';
    }
    if ($location !== '') {
        $where[] = '(s.city = ? OR s.village = ?)';
        $params[] = $location;
        $params[] = $location;
        $types .= 'ss';
    }
    if ($verified === '1') { $where[] = 's.verified = 1'; }
    elseif ($verified === '0') { $where[] = '(s.verified = 0 OR s.verified IS NULL)'; }

    $whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
    $orderSql = $sort === 'products' ? 'ORDER BY product_count DESC, s.name ASC' : 'ORDER BY s.name ASC';

    $prodMatch  = cmp_company_match_joined($conn, 'p', 's');
    $prod2Match = cmp_company_match_joined($conn, 'p2', 's');
    $sql = "SELECT s.*,
                   (SELECT COUNT(*) FROM products p WHERE $prodMatch AND p.is_active = 1) AS product_count,
                   (SELECT COALESCE(SUM(p2.stock),0) FROM products p2 WHERE $prod2Match) AS total_stock
              FROM sellers s $whereSql $orderSql";
    $stmt = $conn->prepare($sql);
    if ($types !== '') { $stmt->bind_param($types, ...$params); }
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) { $companies[] = $row; }

    // Distinct values for the filter dropdowns (from the full unfiltered set).
    $cRes = $conn->query("SELECT DISTINCT category FROM sellers WHERE category IS NOT NULL AND category <> '' ORDER BY category");
    if ($cRes) { while ($r = $cRes->fetch_assoc()) { $categories[] = $r['category']; } }
    $lRes = $conn->query("SELECT DISTINCT city AS loc FROM sellers WHERE city IS NOT NULL AND city <> '' UNION SELECT DISTINCT village AS loc FROM sellers WHERE village IS NOT NULL AND village <> ''");
    if ($lRes) { while ($r = $lRes->fetch_assoc()) { if ($r['loc']) $locations[] = $r['loc']; } }
    $locations = array_values(array_unique($locations));
    sort($locations);
}

$pageTitle     = 'Companies';
$pageSubtitle  = 'Manage registered companies and their seller accounts.';
$activeTeamTab = 'companies';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.note-box{background:var(--bg-soft);border-left:4px solid var(--primary);padding:12px 16px;border-radius:8px;font-size:12.5px;color:var(--muted);margin-bottom:16px}
.cmp-asset-box{border:1.5px dashed var(--border);border-radius:10px;padding:12px;text-align:center}
.cmp-asset-box img{max-height:60px;max-width:100%;object-fit:contain;margin-bottom:8px}
.cmp-asset-empty{color:var(--muted);font-size:12px;padding:14px 0}
.cmp-asset-actions{display:flex;gap:8px;justify-content:center;margin-top:6px;flex-wrap:wrap}
.cmp-sign-badge{font-size:10.5px;padding:2px 7px;border-radius:20px;font-weight:600}
.cmp-sign-badge.ok{background:var(--success-bg,#e6f4ea);color:var(--success,#1a7f37)}
.cmp-sign-badge.missing{background:var(--danger-bg,#fdecea);color:var(--danger,#c0392b)}
.cmp-toolbar{display:flex;gap:10px;flex-wrap:wrap;align-items:center;margin-bottom:18px}
.cmp-toolbar input[type=text]{max-width:240px}
.cmp-toolbar select{max-width:170px}
.cmp-toolbar select:focus,.cmp-toolbar input:focus{border-color:var(--primary);box-shadow:0 0 0 3px rgba(47,79,68,.12)}
.cmp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:18px}
.cmp-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:18px;box-shadow:0 1px 3px rgba(0,0,0,.03);transition:.18s ease;display:flex;flex-direction:column;gap:10px}
.cmp-card:hover{box-shadow:0 10px 24px rgba(27,47,41,.1);transform:translateY(-3px);border-color:transparent}
.cmp-card-head{display:flex;align-items:center;gap:12px}
.cmp-logo{width:52px;height:52px;border-radius:14px;background:var(--bg-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:19px;flex-shrink:0;overflow:hidden}
.cmp-logo img{width:100%;height:100%;object-fit:cover}
.cmp-name-wrap{min-width:0}
.cmp-name{font-size:14.5px;font-weight:700;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;display:flex;align-items:center;gap:6px}
.cmp-name .fa-circle-check{color:var(--success);font-size:12px}
.cmp-cat{font-size:11.5px;color:var(--muted)}
.cmp-desc{font-size:12.5px;color:var(--muted);line-height:1.5;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;min-height:34px}
.cmp-meta{display:flex;flex-wrap:wrap;gap:8px;font-size:11.5px;color:var(--muted)}
.cmp-meta span{display:flex;align-items:center;gap:5px}
.cmp-meta .tag{font-size:10.5px;padding:3px 9px}
.cmp-card-foot{display:flex;flex-direction:column;gap:8px;margin-top:auto;padding-top:12px;border-top:1px solid var(--border)}
.cmp-card-btns{display:flex;gap:8px}
.cmp-card-btns a{flex:1;text-align:center;font-size:12px;padding:8px 10px}
.cmp-actions{position:relative;display:flex;justify-content:flex-end}
.cmp-actions-btn{background:var(--bg-soft);border:1px solid transparent;width:30px;height:30px;border-radius:8px;cursor:pointer;font-size:13px;color:var(--muted);transition:.15s}
.cmp-actions-btn:hover,.cmp-actions-btn.open{border-color:var(--primary);color:var(--primary);background:#fff}
.cmp-actions-menu{position:absolute;right:0;top:calc(100% + 6px);background:#fff;border:1px solid var(--border);border-radius:12px;box-shadow:0 12px 30px rgba(27,47,41,.16);min-width:180px;padding:6px;z-index:60;display:none}
.cmp-actions-menu.show{display:block}
.cmp-actions-menu button{display:flex;align-items:center;gap:10px;width:100%;text-align:left;padding:9px 10px;border:none;background:none;border-radius:8px;font-size:12.5px;font-weight:600;cursor:pointer;color:var(--text,#1b2f29)}
.cmp-actions-menu button:hover{background:var(--bg-soft)}
.cmp-actions-menu button i{width:14px;text-align:center;color:var(--muted)}
.cmp-actions-menu button.act-verify-on i{color:var(--success)}
.cmp-actions-menu button.act-danger{color:var(--danger,#c0392b)}
.cmp-actions-menu button.act-danger i{color:var(--danger,#c0392b)}
.cmp-actions-menu button.act-success i{color:var(--success)}
.cmp-actions-menu hr{border:none;border-top:1px solid var(--border);margin:4px 2px}
.cmp-add-btn{background:var(--primary)}
#companyDetailModal .modal-box{max-width:640px}
.cmp-detail-head{display:flex;align-items:center;gap:16px;margin-bottom:16px}
.cmp-detail-logo{width:68px;height:68px;border-radius:16px;background:var(--bg-soft);color:var(--primary);display:flex;align-items:center;justify-content:center;font-weight:700;font-size:24px;flex-shrink:0;overflow:hidden}
.cmp-detail-logo img{width:100%;height:100%;object-fit:cover}
.cmp-detail-grid{display:grid;grid-template-columns:1fr 1fr;gap:10px 20px;font-size:12.5px;margin:14px 0}
.cmp-detail-grid div b{display:block;color:var(--muted);font-weight:600;font-size:11px;text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px}
.cmp-products-list{display:flex;flex-direction:column;gap:8px;max-height:280px;overflow-y:auto}
.cmp-prod-row{display:flex;align-items:center;gap:10px;padding:8px;border:1px solid var(--border);border-radius:10px}
.cmp-prod-row img{width:38px;height:38px;border-radius:8px;object-fit:cover;background:var(--bg-soft)}
.cmp-prod-row .nm{font-size:12.5px;font-weight:600}
.cmp-prod-row .cat{font-size:11px;color:var(--muted)}
.cmp-prod-row .pr{margin-left:auto;font-size:12.5px;font-weight:700;color:var(--primary)}
</style>

<div class="card">
    <div class="card-head">
        <h2>Companies (<?php echo count($companies); ?>)</h2>
        <?php if (hasPermission('companies.approve')): ?>
        <button class="btn cmp-add-btn" onclick="openCompanyForm(null)"><i class="fa-solid fa-plus"></i> Add Company</button>
        <?php endif; ?>
    </div>

    <?php if (!$sellersTableExists): ?>
        <div class="note-box">Companies reuses the <code>sellers</code> table. Run <code>add_sellers_coupons.sql</code> once in phpMyAdmin to enable it (mobile, email, village, verified badge, and now description/category/logo).</div>
    <?php else: ?>

    <form class="cmp-toolbar" method="get" id="cmpFilterForm">
        <input type="text" name="q" placeholder="Search company by name..." value="<?php echo htmlspecialchars($_GET['q'] ?? ''); ?>">
        <select name="category" onchange="document.getElementById('cmpFilterForm').submit()">
            <option value="">All categories</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?php echo htmlspecialchars($c); ?>" <?php echo (($_GET['category'] ?? '') === $c) ? 'selected' : ''; ?>><?php echo htmlspecialchars($c); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="location" onchange="document.getElementById('cmpFilterForm').submit()">
            <option value="">All locations</option>
            <?php foreach ($locations as $l): ?>
                <option value="<?php echo htmlspecialchars($l); ?>" <?php echo (($_GET['location'] ?? '') === $l) ? 'selected' : ''; ?>><?php echo htmlspecialchars($l); ?></option>
            <?php endforeach; ?>
        </select>
        <select name="verified" onchange="document.getElementById('cmpFilterForm').submit()">
            <option value="">Verified: Any</option>
            <option value="1" <?php echo (($_GET['verified'] ?? '') === '1') ? 'selected' : ''; ?>>Verified only</option>
            <option value="0" <?php echo (($_GET['verified'] ?? '') === '0') ? 'selected' : ''; ?>>Not verified</option>
        </select>
        <select name="status" onchange="document.getElementById('cmpFilterForm').submit()">
            <option value="active" <?php echo (($_GET['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo (($_GET['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Deactivated</option>
            <option value="all" <?php echo (($_GET['status'] ?? '') === 'all') ? 'selected' : ''; ?>>All</option>
        </select>
        <select name="sort" onchange="document.getElementById('cmpFilterForm').submit()">
            <option value="name" <?php echo (($_GET['sort'] ?? 'name') === 'name') ? 'selected' : ''; ?>>Sort: Name (A–Z)</option>
            <option value="products" <?php echo (($_GET['sort'] ?? '') === 'products') ? 'selected' : ''; ?>>Sort: Most Products</option>
        </select>
        <button type="submit" class="btn sm cmp-add-btn"><i class="fa-solid fa-filter"></i> Apply</button>
        <?php if (!empty(array_filter($_GET))): ?><a href="companies.php" class="btn sm outline">Reset</a><?php endif; ?>
    </form>

    <?php if (empty($companies)): ?>
        <div class="empty-state"><i class="fa-solid fa-building-circle-xmark"></i>No companies match your search/filters.</div>
    <?php else: ?>
    <div class="cmp-grid">
        <?php foreach ($companies as $c):
            $isDeleted = !empty($c['deleted_at']);
            $loc = trim(implode(', ', array_filter([$c['village'] ?? '', $c['city'] ?? ''])));
            $initial = strtoupper(substr($c['name'] ?: '?', 0, 1));
        ?>
        <div class="cmp-card">
            <div class="cmp-card-head">
                <div class="cmp-logo"><?php echo !empty($c['logo']) ? '<img src="' . htmlspecialchars(cmp_img_url($c['logo'])) . '" alt="" onerror="this.parentElement.textContent=' . json_encode($initial) . '">' : $initial; ?></div>
                <div class="cmp-name-wrap">
                    <div class="cmp-name"><?php echo htmlspecialchars($c['name']); ?> <?php if (!empty($c['verified'])): ?><i class="fa-solid fa-circle-check" title="Verified"></i><?php endif; ?></div>
                    <div class="cmp-cat"><?php echo htmlspecialchars($c['category'] ?: 'Uncategorized'); ?><?php if (!empty($c['gstin'])): ?> · GSTIN: <?php echo htmlspecialchars($c['gstin']); ?><?php endif; ?></div>
                </div>
            </div>
            <div class="cmp-desc"><?php echo htmlspecialchars($c['description'] ?: 'No description added yet.'); ?></div>
            <div class="cmp-meta">
                <span><i class="fa-solid fa-location-dot"></i><?php echo htmlspecialchars($loc ?: 'Location not set'); ?></span>
                <span><i class="fa-solid fa-box"></i><?php echo (int)$c['product_count']; ?> products</span>
                <span><i class="fa-solid fa-layer-group"></i><?php echo (int)($c['total_stock'] ?? 0); ?> units</span>
                <span class="tag <?php echo $isDeleted ? 'inactive' : 'active'; ?>"><?php echo $isDeleted ? 'Inactive' : 'Active'; ?></span>
                <span class="cmp-sign-badge <?php echo !empty($c['signature_path']) ? 'ok' : 'missing'; ?>">Signature <?php echo !empty($c['signature_path']) ? '✓' : '✗'; ?></span>
                <span class="cmp-sign-badge <?php echo !empty($c['stamp_path']) ? 'ok' : 'missing'; ?>">Stamp <?php echo !empty($c['stamp_path']) ? '✓' : '✗'; ?></span>
            </div>
            <div class="cmp-card-foot">
                <div class="cmp-card-btns">
                    <a href="company_profile.php?id=<?php echo (int)$c['id']; ?>" class="btn sm outline"><i class="fa-solid fa-eye"></i> View Details</a>
                    <a href="company_products.php?id=<?php echo (int)$c['id']; ?>" class="btn sm cmp-add-btn"><i class="fa-solid fa-boxes-stacked"></i> Manage Products</a>
                </div>
                <div style="display:flex;align-items:center;justify-content:space-between">
                    <span style="font-size:11px;color:var(--muted)">Joined <?php echo cmp_fmt_date($c['created_at'] ?? null); ?></span>
                    <div class="cmp-actions">
                    <?php $cmpMenuId = 'cmpMenu' . (int)$c['id']; ?>
                    <button type="button" class="cmp-actions-btn" id="<?php echo $cmpMenuId; ?>Btn" title="Actions" onclick="cmpToggleMenu(event, '<?php echo $cmpMenuId; ?>')"><i class="fa-solid fa-ellipsis-vertical"></i></button>
                    <div class="cmp-actions-menu" id="<?php echo $cmpMenuId; ?>">
                        <button type="button" onclick="cmpCloseAllMenus(); location.href='company_profile.php?id=<?php echo (int)$c['id']; ?>'"><i class="fa-solid fa-eye"></i> View Details</button>
                        <button type="button" onclick="cmpCloseAllMenus(); location.href='company_products.php?id=<?php echo (int)$c['id']; ?>'"><i class="fa-solid fa-boxes-stacked"></i> Manage Products</button>
                        <?php if (hasPermission('companies.approve')): ?>
                        <button type="button" onclick="cmpCloseAllMenus(); openCompanyForm(<?php echo (int)$c['id']; ?>)"><i class="fa-solid fa-pen"></i> Edit</button>
                        <button type="button" class="<?php echo !empty($c['verified']) ? 'act-verify-on' : ''; ?>" onclick="cmpCloseAllMenus(); toggleVerified(<?php echo (int)$c['id']; ?>, this)"><i class="fa-solid fa-shield-halved"></i> <?php echo !empty($c['verified']) ? 'Unverify' : 'Verify'; ?></button>
                        <?php endif; ?>
                        <?php if (hasPermission('companies.block') || hasPermission('companies.approve')): ?>
                        <hr>
                        <button type="button" class="<?php echo $isDeleted ? 'act-success' : 'act-danger'; ?>" onclick="cmpCloseAllMenus(); toggleCompanyStatus(<?php echo (int)$c['id']; ?>, <?php echo $isDeleted ? 'true' : 'false'; ?>)"><i class="fa-solid <?php echo $isDeleted ? 'fa-toggle-off' : 'fa-toggle-on'; ?>"></i> <?php echo $isDeleted ? 'Activate' : 'Deactivate'; ?></button>
                        <?php endif; ?>
                    </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Add / Edit modal -->
<div class="modal-overlay" id="companyFormModal">
    <div class="modal-box">
        <h3 id="cfTitle">Add Company</h3>
        <p>Company profile shown on the public Companies directory.</p>
        <input type="hidden" id="cfId" value="">
        <div class="form-group"><label>Company Name *</label><input type="text" id="cfName"></div>
        <div class="form-grid">
            <div class="form-group"><label>Category / Business Type</label><input type="text" id="cfCategory" placeholder="e.g. Seeds & Fertilizers"></div>
            <div class="form-group"><label>GST Number (GSTIN)</label><input type="text" id="cfGstin" placeholder="e.g. 27ABCDE1234F1Z5" maxlength="15" style="text-transform:uppercase"></div>
            <div class="form-group"><label>Logo URL</label><input type="text" id="cfLogo" placeholder="/assets/images/uploads/logo.png"></div>
        </div>
        <div class="form-group full"><label>Description</label><textarea id="cfDescription" rows="3" placeholder="Short company description shown on its profile page"></textarea></div>
        <div class="form-grid">
            <div class="form-group"><label>Mobile</label><input type="text" id="cfMobile"></div>
            <div class="form-group"><label>Email</label><input type="text" id="cfEmail"></div>
            <div class="form-group"><label>Village</label><input type="text" id="cfVillage"></div>
            <div class="form-group"><label>City</label><input type="text" id="cfCity"></div>
        </div>
        <div class="form-group"><label><input type="checkbox" id="cfVerified" style="width:auto"> Verified company</label></div>
        <div class="form-group full"><label>Internal Notes</label><textarea id="cfNotes" rows="2"></textarea></div>

        <div id="cfSignStampSection" style="display:none">
            <div class="form-group full" style="border-top:1px solid var(--border);margin-top:6px;padding-top:14px">
                <label style="margin-bottom:8px">Digital Signature &amp; Stamp <span style="font-weight:400;color:var(--muted)">— shown on Buyer Invoices for this company's products</span></label>
                <div class="form-grid">
                    <div class="cmp-asset-box" id="cfSigBox">
                        <div class="cmp-asset-empty">Not uploaded</div>
                        <div class="cmp-asset-actions">
                            <label class="btn sm outline" style="margin:0">Choose Signature<input type="file" id="cfSignatureFile" accept=".png,.jpg,.jpeg,.webp" style="display:none" onchange="cmpAssetPreview(this,'cfSigBox')"></label>
                            <button type="button" class="btn sm outline" id="cfSigRemoveBtn" style="display:none" onclick="cmpAssetRemove('signature')">Remove</button>
                        </div>
                    </div>
                    <div class="cmp-asset-box" id="cfStampBox">
                        <div class="cmp-asset-empty">Not uploaded</div>
                        <div class="cmp-asset-actions">
                            <label class="btn sm outline" style="margin:0">Choose Stamp<input type="file" id="cfStampFile" accept=".png,.jpg,.jpeg,.webp" style="display:none" onchange="cmpAssetPreview(this,'cfStampBox')"></label>
                            <button type="button" class="btn sm outline" id="cfStampRemoveBtn" style="display:none" onclick="cmpAssetRemove('stamp')">Remove</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="form-group full" id="cfSignStampHint" style="display:none;color:var(--muted);font-size:12px">Save the company first — then reopen Edit to attach its signature &amp; stamp.</div>

        <div class="err" id="cfErr" style="display:none"></div>
        <div class="modal-actions">
            <button class="btn outline" onclick="closeModal('companyFormModal')">Cancel</button>
            <button class="btn" onclick="saveCompany()">Save Company</button>
        </div>
    </div>
</div>

<script>
const cmpCanEdit = <?php echo hasPermission('companies.approve') ? 'true' : 'false'; ?>;
const cmpCompanies = <?php echo json_encode($companies); ?>;

function findCompany(id){ return cmpCompanies.find(c => parseInt(c.id) === parseInt(id)); }

function openCompanyForm(id){
    document.getElementById('cfErr').style.display = 'none';
    const c = id ? findCompany(id) : null;
    document.getElementById('cfTitle').textContent = c ? 'Edit Company' : 'Add Company';
    document.getElementById('cfId').value = c ? c.id : '';
    document.getElementById('cfName').value = c ? (c.name || '') : '';
    document.getElementById('cfCategory').value = c ? (c.category || '') : '';
    document.getElementById('cfGstin').value = c ? (c.gstin || '') : '';
    document.getElementById('cfLogo').value = c ? (c.logo || '') : '';
    document.getElementById('cfDescription').value = c ? (c.description || '') : '';
    document.getElementById('cfMobile').value = c ? (c.mobile || '') : '';
    document.getElementById('cfEmail').value = c ? (c.email || '') : '';
    document.getElementById('cfVillage').value = c ? (c.village || '') : '';
    document.getElementById('cfCity').value = c ? (c.city || '') : '';
    document.getElementById('cfVerified').checked = !!(c && parseInt(c.verified) === 1);
    document.getElementById('cfNotes').value = c ? (c.notes || '') : '';

    // Signature/Stamp can only be attached once the company already
    // exists (see company_action.php — a fresh "Add Company" submit
    // ignores files so we don't have to juggle a variable-length INSERT).
    cmpRemoveFlags = { signature: false, stamp: false };
    document.getElementById('cfSignatureFile').value = '';
    document.getElementById('cfStampFile').value = '';
    document.getElementById('cfSignStampSection').style.display = c ? 'block' : 'none';
    document.getElementById('cfSignStampHint').style.display = c ? 'none' : 'block';
    if (c) {
        cmpFillAssetBox('cfSigBox', c.signature_path, 'cfSigRemoveBtn');
        cmpFillAssetBox('cfStampBox', c.stamp_path, 'cfStampRemoveBtn');
    }
    openModal('companyFormModal');
}

let cmpRemoveFlags = { signature: false, stamp: false };

function cmpFillAssetBox(boxId, path, removeBtnId){
    const box = document.getElementById(boxId);
    const empty = box.querySelector('.cmp-asset-empty');
    let img = box.querySelector('img');
    if (path) {
        if (!img) { img = document.createElement('img'); box.insertBefore(img, box.firstChild); }
        img.src = '../' + path.replace(/^\.?\//, '');
        if (empty) empty.style.display = 'none';
        document.getElementById(removeBtnId).style.display = 'inline-block';
    } else {
        if (img) img.remove();
        if (empty) empty.style.display = 'block';
        document.getElementById(removeBtnId).style.display = 'none';
    }
}

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
    };
    reader.readAsDataURL(input.files[0]);
}

function cmpAssetRemove(which){
    if (!confirm('Remove this ' + which + ' on save?')) return;
    cmpRemoveFlags[which] = true;
    const boxId = which === 'signature' ? 'cfSigBox' : 'cfStampBox';
    const fileId = which === 'signature' ? 'cfSignatureFile' : 'cfStampFile';
    document.getElementById(fileId).value = '';
    cmpFillAssetBox(boxId, null, which === 'signature' ? 'cfSigRemoveBtn' : 'cfStampRemoveBtn');
}

function saveCompany(){
    const name = document.getElementById('cfName').value.trim();
    const errEl = document.getElementById('cfErr');
    if (!name) { errEl.textContent = 'Company name is required.'; errEl.style.display = 'block'; return; }
    errEl.style.display = 'none';

    const form = new FormData();
    form.append('action', 'save');
    form.append('id', document.getElementById('cfId').value);
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
    .then(d => {
        if (d.success) { showToast('Company saved.'); closeModal('companyFormModal'); location.reload(); }
        else { errEl.textContent = d.error || 'Save failed.'; errEl.style.display = 'block'; }
    })
    .catch(() => { errEl.textContent = 'Network error — please try again.'; errEl.style.display = 'block'; });
}

function cmpCloseAllMenus(){
    document.querySelectorAll('.cmp-actions-menu.show').forEach(m => m.classList.remove('show'));
    document.querySelectorAll('.cmp-actions-btn.open').forEach(b => b.classList.remove('open'));
}

function cmpToggleMenu(evt, menuId){
    evt.stopPropagation();
    const menu = document.getElementById(menuId);
    const btn = document.getElementById(menuId + 'Btn');
    const wasOpen = menu.classList.contains('show');
    cmpCloseAllMenus();
    if (!wasOpen) { menu.classList.add('show'); btn.classList.add('open'); }
}

document.addEventListener('click', cmpCloseAllMenus);

function toggleVerified(id, btn){
    fetch('company_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'toggle_verified', id }) })
    .then(r => r.json())
    .then(d => {
        if (d.success) { showToast(d.verified ? 'Company verified.' : 'Verification removed.'); setTimeout(() => location.reload(), 500); }
        else { showToast(d.error || 'Update failed.', true); }
    })
    .catch(() => showToast('Network error — please try again.', true));
}

function toggleCompanyStatus(id, isInactive){
    if (!confirm(isInactive ? 'Activate this company?' : 'Deactivate this company? It will be hidden from the public directory.')) return;
    fetch('company_action.php', { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams({ action: 'toggle_status', id }) })
    .then(r => r.json())
    .then(d => {
        if (d.success) { showToast('Company ' + d.status + '.'); setTimeout(() => location.reload(), 500); }
        else { showToast(d.error || 'Update failed.', true); }
    })
    .catch(() => showToast('Network error — please try again.', true));
}


</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
