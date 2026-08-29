<?php
// =====================================================
// AgriCart — "Become a Seller" onboarding form.
// A logged-in user fills in their seller details, accepts the seller
// Terms & Conditions, and picks how they want to sell:
//   - Product Selling (crops/seeds/produce)
//   - Equipment Rental (list a tractor/tools for rent)
//   - or both.
// Details are saved to `users`, the choice + acceptance timestamp to
// seller_payout_profiles.seller_type, and this drives which sections
// the Seller Dashboard shows afterwards.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/seller_functions.php';

$isLoggedIn = isset($_SESSION['user_id']);
$errorMsg = '';

$prefill = ['full_name' => '', 'mobile' => '', 'email' => '', 'village' => '', 'taluka' => '', 'district' => ''];

if ($isLoggedIn) {
    $sellerId = (int)$_SESSION['user_id'];
    $currentType = agri_seller_get_type($conn, $sellerId); // pre-fill if changing later

    $puStmt = $conn->prepare("SELECT full_name, mobile, email, village, taluka, district FROM users WHERE id = ? LIMIT 1");
    $puStmt->bind_param("i", $sellerId);
    $puStmt->execute();
    $puRow = $puStmt->get_result()->fetch_assoc();
    if ($puRow) $prefill = array_merge($prefill, array_map(fn($v) => $v ?? '', $puRow));

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $postedCsrf = $_POST['csrf_token'] ?? '';
        if (empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $postedCsrf)) {
            $errorMsg = 'Session expired, please try again.';
        } else {
            $fullName = trim($_POST['bs_full_name'] ?? '');
            $email    = trim($_POST['bs_email'] ?? '');
            $village  = trim($_POST['bs_village'] ?? '');
            $taluka   = trim($_POST['bs_taluka'] ?? '');
            $district = trim($_POST['bs_district'] ?? '');
            $wantsProduct = isset($_POST['bs_product']);
            $wantsRental  = isset($_POST['bs_rental']);
            $agreedTerms  = isset($_POST['bs_terms']);

            // Keep whatever the user typed on screen if we have to redisplay the form.
            $prefill = array_merge($prefill, [
                'full_name' => $fullName, 'email' => $email,
                'village' => $village, 'taluka' => $taluka, 'district' => $district,
            ]);

            if ($fullName === '') {
                $errorMsg = 'Please enter your full name.';
            } elseif (!$agreedTerms) {
                $errorMsg = 'Please accept the Seller Terms & Conditions to continue.';
            } elseif (!$wantsProduct && !$wantsRental) {
                $errorMsg = 'Please select at least one selling option.';
            } else {
                $type = ($wantsProduct && $wantsRental) ? 'both' : ($wantsProduct ? 'product' : 'rental');

                $upd = $conn->prepare("UPDATE users SET full_name = ?, email = ?, village = ?, taluka = ?, district = ? WHERE id = ?");
                $upd->bind_param("sssssi", $fullName, $email, $village, $taluka, $district, $sellerId);
                $upd->execute();
                // Keep the session + header nav in sync immediately.
                $_SESSION['user_name']     = $fullName;
                $_SESSION['user_email']    = $email;
                $_SESSION['user_village']  = $village;
                $_SESSION['user_taluka']   = $taluka;
                $_SESSION['user_district'] = $district;

                agri_seller_set_type($conn, $sellerId, $type);
                agri_seller_ensure_profile($conn, $sellerId);
                $termsStmt = $conn->prepare("UPDATE seller_payout_profiles SET terms_accepted_at = NOW() WHERE user_id = ?");
                if ($termsStmt) { $termsStmt->bind_param("i", $sellerId); @$termsStmt->execute(); }

                // Send them straight into the right first action.
                if ($type === 'product') {
                    header('Location: sell_product.php');
                } elseif ($type === 'rental') {
                    header('Location: list_equipment.php');
                } else {
                    header('Location: ../seller/dashboard.php');
                }
                exit();
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

include __DIR__ . '/../includes/header.php';
?>
<style>
.bs-hero{background:linear-gradient(135deg,rgba(6,20,9,.94),rgba(28,90,32,.90) 55%,rgba(46,125,50,.82));padding:56px 20px 90px;color:#fff;text-align:center;position:relative;overflow:hidden}
.bs-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 15%,rgba(255,255,255,.14),transparent 42%),radial-gradient(circle at 88% 85%,rgba(255,255,255,.10),transparent 45%)}
.bs-hero::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:44px;background:#f4f8f4;border-radius:50% 50% 0 0 / 100% 100% 0 0}
.bs-hero-inner{position:relative;max-width:680px;margin:0 auto}
.bs-hero-icon{font-size:40px;margin-bottom:10px;display:inline-block;animation:bs-float 3.2s ease-in-out infinite}
@keyframes bs-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.bs-hero h1{font-size:29px;font-weight:800;margin:0 0 8px;letter-spacing:-.3px}
.bs-hero p{font-size:14.5px;opacity:.92;margin:0;max-width:520px;margin-inline:auto}

.bs-page-bg{background:#f4f8f4;padding-bottom:60px}
.bs-wrap{max-width:760px;margin:0 auto;padding:0 16px;position:relative;z-index:2}
.bs-card{background:#fff;border-radius:20px;box-shadow:0 16px 44px rgba(20,60,30,.13);padding:8px;margin-top:-56px}
.bs-card-inner{padding:30px 26px}

.bs-section{margin:26px 0 4px}
.bs-section:first-child{margin-top:6px}
.bs-section-title{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:800;color:#1b3a1e;text-transform:uppercase;letter-spacing:.03em;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #eef4ee}
.bs-section-title i{background:#e8f5e9;color:#2E7D32;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px}

.bs-row{margin-bottom:14px}
.bs-row label{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:#2c2c2c;margin-bottom:7px}
.bs-row label i{color:#2E7D32;font-size:12px;width:14px;text-align:center}
.bs-req{color:#c0392b;font-weight:800}
.bs-row input{
    width:100%;box-sizing:border-box;border:1.5px solid #dfe6df;border-radius:10px;
    padding:11px 13px;font-size:14px;font-family:inherit;transition:border-color .15s ease, box-shadow .15s ease;background:#fafcfa
}
.bs-row input:focus{outline:none;border-color:#2E7D32;box-shadow:0 0 0 3px rgba(46,125,50,.12);background:#fff}
.bs-row input:disabled{background:#f0f2f0;color:#8a9a8a;cursor:not-allowed}
.bs-row .bs-field-hint{font-size:11px;color:#8a9a8a;margin-top:4px}
.bs-grid2{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.bs-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:14px}

.bs-terms-box{background:#f7fbf6;border:1px solid #dfe9df;border-radius:12px;padding:16px 18px;max-height:180px;overflow-y:auto;font-size:12.5px;color:#4a5a4a;line-height:1.65}
.bs-terms-box ul{margin:6px 0 0;padding-left:18px}
.bs-terms-box li{margin-bottom:5px}
.bs-terms-box a{color:#2E7D32;font-weight:700;text-decoration:none}
.bs-terms-box a:hover{text-decoration:underline}
.bs-terms-agree{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#2c2c2c;margin:14px 0 6px;font-weight:600}
.bs-terms-agree input{width:auto;margin-top:3px;accent-color:#2E7D32}

.bs-options{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin:22px 0 26px}
.bs-option{position:relative}
.bs-option input{position:absolute;opacity:0;width:0;height:0}
.bs-option label{
    display:flex;flex-direction:column;align-items:center;text-align:center;gap:10px;
    border:2px solid #dfe6df;border-radius:16px;padding:26px 16px;cursor:pointer;
    background:#fafcfa;transition:all .18s ease;height:100%;box-sizing:border-box;
}
.bs-option label:hover{border-color:#8fc98f;transform:translateY(-2px)}
.bs-option input:checked + label{
    border-color:#2E7D32;background:linear-gradient(180deg,#eef7ee,#f5fbf0);
    box-shadow:0 8px 20px rgba(46,125,50,.18);
}
.bs-option-icon{width:56px;height:56px;border-radius:50%;background:#e8f5e9;color:#2E7D32;
    display:flex;align-items:center;justify-content:center;font-size:24px;transition:transform .18s ease}
.bs-option input:checked + label .bs-option-icon{background:#2E7D32;color:#fff;transform:scale(1.08)}
.bs-option-title{font-size:15.5px;font-weight:800;color:#1b3a1e}
.bs-option-desc{font-size:12.5px;color:#7c8a7a;line-height:1.5}
.bs-option-check{position:absolute;top:12px;right:12px;width:22px;height:22px;border-radius:50%;
    border:2px solid #c8d6c8;background:#fff;display:flex;align-items:center;justify-content:center;
    font-size:11px;color:transparent;transition:all .18s ease}
.bs-option input:checked + label .bs-option-check{background:#2E7D32;border-color:#2E7D32;color:#fff}

.bs-hint{display:flex;align-items:center;gap:9px;background:#eef7ee;border:1px solid #bfe3bf;
    border-radius:12px;padding:12px 16px;font-size:12.5px;color:#245c26;margin-bottom:6px}
.bs-hint i{color:#2E7D32}
.bs-error{font-size:12.5px;color:#c0392b;margin:4px 0 14px;display:<?php echo $errorMsg ? 'block' : 'none'; ?>}

.bs-submit{background:linear-gradient(135deg,#2E7D32,#256428);color:#fff;border:none;border-radius:12px;
    padding:14px 28px;font-weight:700;font-size:15.5px;cursor:pointer;width:100%;margin-top:6px;
    display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 8px 20px rgba(46,125,50,.28);
    transition:transform .12s ease, box-shadow .12s ease}
.bs-submit:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(46,125,50,.34)}
.bs-submit:disabled{opacity:.6;cursor:not-allowed;transform:none}

.bs-login-gate{text-align:center;padding:44px 20px}
.bs-login-gate i{font-size:36px;color:#2E7D32;margin-bottom:14px;display:block}
.bs-login-gate a{background:linear-gradient(135deg,#2E7D32,#256428);color:#fff;padding:13px 30px;
    border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;margin-top:16px;
    box-shadow:0 8px 20px rgba(46,125,50,.25)}
@media (max-width:560px){.bs-options{grid-template-columns:1fr}.bs-grid2,.bs-grid3{grid-template-columns:1fr}.bs-hero h1{font-size:23px}.bs-card-inner{padding:22px 18px}}
</style>

<div class="bs-hero">
  <div class="bs-hero-inner">
    <span class="bs-hero-icon">🚜</span>
    <h1>Become a Seller on AgriCart</h1>
    <p>Choose how you want to sell — list your produce, rent out your equipment, or do both.</p>
  </div>
</div>

<div class="bs-page-bg">
<div class="bs-wrap">
  <div class="bs-card"><div class="bs-card-inner">

    <?php if (!$isLoggedIn): ?>
      <div class="bs-login-gate">
        <i class="fa-solid fa-lock"></i>
        <p>You need to be logged in to become a seller.</p>
        <a href="login.php">Login to Continue</a>
      </div>
    <?php else: ?>

      <div class="bs-hint">
        <i class="fa-solid fa-circle-info"></i>
        <span>Fill in your details, accept the seller terms, and choose how you want to sell.</span>
      </div>
      <div class="bs-error" id="bsError"><?php echo htmlspecialchars($errorMsg); ?></div>

      <form method="POST" action="become_seller.php" id="bsForm">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- ============ SELLER DETAILS ============ -->
        <div class="bs-section">
          <div class="bs-section-title"><i class="fa-solid fa-id-card"></i> Seller Details</div>

          <div class="bs-row">
            <label><i class="fa-solid fa-user"></i> Full Name <span class="bs-req">*</span></label>
            <input type="text" name="bs_full_name" required maxlength="150" value="<?php echo htmlspecialchars($prefill['full_name']); ?>">
          </div>

          <div class="bs-grid2">
            <div class="bs-row">
              <label><i class="fa-solid fa-phone"></i> Mobile Number</label>
              <input type="tel" value="<?php echo htmlspecialchars($prefill['mobile']); ?>" disabled>
              <div class="bs-field-hint">Linked to your login — change it from My Profile.</div>
            </div>
            <div class="bs-row">
              <label><i class="fa-solid fa-envelope"></i> Email</label>
              <input type="email" name="bs_email" maxlength="150" value="<?php echo htmlspecialchars($prefill['email']); ?>">
            </div>
          </div>

          <div class="bs-grid3">
            <div class="bs-row">
              <label><i class="fa-solid fa-house"></i> Village</label>
              <input type="text" name="bs_village" maxlength="100" value="<?php echo htmlspecialchars($prefill['village']); ?>">
            </div>
            <div class="bs-row">
              <label><i class="fa-solid fa-map"></i> Taluka</label>
              <input type="text" name="bs_taluka" maxlength="100" value="<?php echo htmlspecialchars($prefill['taluka']); ?>">
            </div>
            <div class="bs-row">
              <label><i class="fa-solid fa-map-location-dot"></i> District</label>
              <input type="text" name="bs_district" maxlength="100" value="<?php echo htmlspecialchars($prefill['district']); ?>">
            </div>
          </div>
        </div>

        <!-- ============ SELLING TYPE ============ -->
        <div class="bs-section">
          <div class="bs-section-title"><i class="fa-solid fa-store"></i> What Do You Want To Sell?</div>

        <div class="bs-options">
          <div class="bs-option">
            <input type="checkbox" name="bs_product" id="bsProduct" value="1"
              <?php echo in_array($currentType, ['product','both'], true) ? 'checked' : ''; ?>>
            <label for="bsProduct">
              <span class="bs-option-check"><i class="fa-solid fa-check"></i></span>
              <span class="bs-option-icon"><i class="fa-solid fa-basket-shopping"></i></span>
              <span class="bs-option-title">Sell Products</span>
              <span class="bs-option-desc">List crops, seeds, fertilizer or other farm produce for sale.</span>
            </label>
          </div>

          <div class="bs-option">
            <input type="checkbox" name="bs_rental" id="bsRental" value="1"
              <?php echo in_array($currentType, ['rental','both'], true) ? 'checked' : ''; ?>>
            <label for="bsRental">
              <span class="bs-option-check"><i class="fa-solid fa-check"></i></span>
              <span class="bs-option-icon"><i class="fa-solid fa-tractor"></i></span>
              <span class="bs-option-title">Rent Out Equipment</span>
              <span class="bs-option-desc">List your tractor, harvester, drone or tools for other farmers to rent.</span>
            </label>
          </div>
        </div>
        <div class="bs-field-hint" style="margin-bottom:6px">You can select one or both — you can always add the other option later.</div>
        </div>

        <!-- ============ TERMS & CONDITIONS ============ -->
        <div class="bs-section">
          <div class="bs-section-title"><i class="fa-solid fa-file-contract"></i> Seller Terms &amp; Conditions</div>
          <div class="bs-terms-box">
            By becoming a seller on AgriCart, you agree to the following:
            <ul>
              <li>All product and equipment listings are reviewed by the AgriCart admin team before going live.</li>
              <li>A platform commission is deducted from every sale (5% on products) or booking (10% on equipment rentals) — shown upfront when you list.</li>
              <li>You are responsible for the accuracy, quality, and legality of everything you list.</li>
              <li>Payouts are made to the bank/UPI details you add in your Seller Dashboard, on your chosen payout cycle.</li>
              <li>AgriCart may suspend a listing or seller account for fraudulent, misleading, or policy-violating activity.</li>
            </ul>
            This is a summary — read the full <a href="terms-conditions.php" target="_blank" rel="noopener">Terms &amp; Conditions</a> for complete details.
          </div>
          <label class="bs-terms-agree">
            <input type="checkbox" name="bs_terms" id="bsTerms" value="1">
            <span>I have read and agree to AgriCart's Seller Terms &amp; Conditions and commission policy. <span class="bs-req">*</span></span>
          </label>
        </div>

        <button type="submit" class="bs-submit" id="bsSubmit">
          <i class="fa-solid fa-arrow-right"></i> Continue
        </button>
      </form>

    <?php endif; ?>

  </div></div>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const form = document.getElementById('bsForm');
  if (!form) return;
  form.addEventListener('submit', (e) => {
    const name = document.querySelector('input[name="bs_full_name"]');
    const terms = document.getElementById('bsTerms');
    const p = document.getElementById('bsProduct');
    const r = document.getElementById('bsRental');
    const err = document.getElementById('bsError');
    let msg = '';
    if (!name.value.trim()) msg = 'Please enter your full name.';
    else if (!terms.checked) msg = "Please accept the Seller Terms & Conditions to continue.";
    else if (!p.checked && !r.checked) msg = 'Please select at least one selling option.';
    if (msg) {
      e.preventDefault();
      err.textContent = msg;
      err.style.display = 'block';
      err.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  });
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
