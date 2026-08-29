<?php
// =====================================================================
// admin/invoice_settings.php — Admin Panel -> Settings -> Invoice
// Settings -> AgriCart Signature & Stamp.
//
// Lets an authorized Admin/Super Admin configure AgriCart's official
// Digital Signature, Official Stamp, Authorized Signatory Name and
// Designation. Every SELLER INVOICE across the marketplace uses these
// assets as its Authorized Signatory (never a seller's own
// signature/stamp — see pages/seller-invoice.php / seller/invoice.php).
//
// Changing these assets here only affects NEWLY generated Seller
// Invoices from this point forward — already-issued invoices keep the
// signature/stamp that was valid when they were generated (see
// agri_seller_invoice_freeze_agricart_snapshot() in
// includes/seller_functions.php).
// =====================================================================
require_once __DIR__ . '/includes/admin_guard.php';
requirePermission('settings.invoice_manage');
require_once __DIR__ . '/../includes/invoice_signature_schema.php';
agri_sig_bootstrap_schema($conn);

$assets = $conn->query("SELECT * FROM agricart_invoice_assets WHERE id = 1")->fetch_assoc() ?: [];

$pageTitle = 'Invoice Settings';
$pageSubtitle = 'Configure invoice numbering, tax details and templates.';
$activeTeamTab = 'invoice_settings';
include __DIR__ . '/includes/team_layout_top.php';
?>
<style>
.inv-set-card{background:#fff;border:1px solid var(--border);border-radius:14px;padding:22px;max-width:760px}
.inv-set-card h3{font-size:15px;margin-bottom:4px}
.inv-set-sub{color:var(--muted);font-size:12.5px;margin-bottom:18px}
.inv-set-grid{display:grid;grid-template-columns:1fr 1fr;gap:18px;margin-bottom:18px}
.inv-set-row label{display:block;font-size:12.5px;font-weight:600;margin-bottom:6px}
.inv-set-row input[type=text]{width:100%;padding:9px 11px;border:1px solid var(--border);border-radius:8px;font-size:13px}
.inv-asset-box{border:1.5px dashed var(--border);border-radius:10px;padding:14px;text-align:center;position:relative}
.inv-asset-box img{max-height:70px;max-width:100%;object-fit:contain;margin-bottom:8px}
.inv-asset-empty{color:var(--muted);font-size:12px;padding:18px 0}
.inv-asset-actions{display:flex;gap:8px;justify-content:center;margin-top:8px}
.inv-btn{border:none;border-radius:7px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer}
.inv-btn.primary{background:var(--primary);color:#fff}
.inv-btn.outline{background:#fff;border:1px solid var(--border)}
.inv-btn.danger{background:var(--danger-bg);color:var(--danger)}
.inv-save-bar{margin-top:6px;display:flex;align-items:center;gap:12px}
.inv-status-msg{font-size:12.5px}
.inv-status-msg.ok{color:var(--success)}
.inv-status-msg.err{color:var(--danger)}
</style>

<div class="inv-set-card">
  <h3>AgriCart Signature &amp; Stamp</h3>
  <div class="inv-set-sub">Used as the Authorized Signatory on every Seller Invoice generated across the marketplace. Buyer Invoices show the order's own seller instead — this section never affects them.</div>

  <form id="invSetForm" enctype="multipart/form-data">
    <div class="inv-set-grid">
      <div class="inv-set-row">
        <label>Digital Signature</label>
        <div class="inv-asset-box" id="invSigBox">
          <?php if (!empty($assets['signature_path'])): ?>
            <img src="../<?php echo htmlspecialchars($assets['signature_path']); ?>" alt="AgriCart signature">
          <?php else: ?>
            <div class="inv-asset-empty">Not uploaded</div>
          <?php endif; ?>
          <div class="inv-asset-actions">
            <label class="inv-btn outline" style="margin:0">
              Choose File<input type="file" name="signature" accept=".png,.jpg,.jpeg,.webp" style="display:none" onchange="invPreview(this,'invSigBox')">
            </label>
            <?php if (!empty($assets['signature_path'])): ?>
              <button type="button" class="inv-btn danger" onclick="invRemoveAsset('signature')">Remove</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="inv-set-row">
        <label>Official Stamp</label>
        <div class="inv-asset-box" id="invStampBox">
          <?php if (!empty($assets['stamp_path'])): ?>
            <img src="../<?php echo htmlspecialchars($assets['stamp_path']); ?>" alt="AgriCart stamp">
          <?php else: ?>
            <div class="inv-asset-empty">Not uploaded</div>
          <?php endif; ?>
          <div class="inv-asset-actions">
            <label class="inv-btn outline" style="margin:0">
              Choose File<input type="file" name="stamp" accept=".png,.jpg,.jpeg,.webp" style="display:none" onchange="invPreview(this,'invStampBox')">
            </label>
            <?php if (!empty($assets['stamp_path'])): ?>
              <button type="button" class="inv-btn danger" onclick="invRemoveAsset('stamp')">Remove</button>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <div class="inv-set-grid">
      <div class="inv-set-row">
        <label>Authorized Signatory Name</label>
        <input type="text" name="signatory_name" value="<?php echo htmlspecialchars($assets['signatory_name'] ?? ''); ?>" placeholder="e.g. Rohan Deshmukh">
      </div>
      <div class="inv-set-row">
        <label>Designation</label>
        <input type="text" name="designation" value="<?php echo htmlspecialchars($assets['designation'] ?? ''); ?>" placeholder="e.g. Operations Head">
      </div>
    </div>

    <div class="inv-save-bar">
      <button type="submit" class="inv-btn primary">Save Changes</button>
      <span class="inv-status-msg" id="invStatusMsg"></span>
    </div>
  </form>
</div>

<script>
const INV_CSRF = <?php echo json_encode(csrf_token()); ?>;

function invPreview(input, boxId) {
    if (!input.files || !input.files[0]) return;
    const box = document.getElementById(boxId);
    const reader = new FileReader();
    reader.onload = e => {
        let img = box.querySelector('img');
        if (!img) {
            img = document.createElement('img');
            box.insertBefore(img, box.firstChild);
            const empty = box.querySelector('.inv-asset-empty');
            if (empty) empty.remove();
        }
        img.src = e.target.result;
    };
    reader.readAsDataURL(input.files[0]);
}

document.getElementById('invSetForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const msg = document.getElementById('invStatusMsg');
    msg.textContent = 'Saving...';
    msg.className = 'inv-status-msg';

    const form = new FormData(e.target);
    form.append('action', 'save_agricart_assets');
    form.append('csrf_token', INV_CSRF);

    try {
        const res = await fetch('actions/invoice_settings_action.php', { method: 'POST', body: form });
        const data = await res.json();
        if (data.success) {
            msg.textContent = 'Saved.';
            msg.className = 'inv-status-msg ok';
            setTimeout(() => location.reload(), 700);
        } else {
            msg.textContent = data.error || 'Could not save.';
            msg.className = 'inv-status-msg err';
        }
    } catch (err) {
        msg.textContent = 'Network error.';
        msg.className = 'inv-status-msg err';
    }
});

async function invRemoveAsset(which) {
    if (!confirm('Remove this ' + which + '?')) return;
    const form = new FormData();
    form.append('action', 'remove_asset');
    form.append('which', which);
    form.append('csrf_token', INV_CSRF);
    const res = await fetch('actions/invoice_settings_action.php', { method: 'POST', body: form });
    const data = await res.json();
    if (data.success) location.reload();
    else alert(data.error || 'Could not remove.');
}
</script>

<?php include __DIR__ . '/includes/team_layout_bottom.php'; ?>
