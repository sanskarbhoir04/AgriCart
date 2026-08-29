<?php
// =====================================================
// AgriCart — "Sell Your Product" (farmer/user self-listing form)
// A logged-in farmer or user can list their own agricultural product for
// sale. The listing goes to Admin for approval first, and AgriCart's
// platform commission is shown up front — same idea as how
// Amazon/Flipkart charge sellers a commission per sale.
//
// Fully translated (English / मराठी / हिंदी) — follows the same
// window.pageLanguageCallback pattern the header's language switcher
// already uses on the rest of the site.
//
// Product Name is entered by the seller in ANY of the three supported
// languages (or Auto Detect). It is translated into all three and saved
// separately — the original text the seller typed is never overwritten.
// Translation is done entirely server-side (includes/agri_translate.php),
// so there is no API key exposed anywhere in the frontend.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/commission_schema.php';

// Resolved dynamically (seller override -> category override -> admin
// default) via includes/commission_schema.php, same as insert_product.php
// which sets the authoritative rate at submit time. This page only needs
// a live preview to show the farmer before they pick a category, so it
// uses the seller-specific rate if they have one, else the global default.
define('AGRI_PRODUCT_COMMISSION_PERCENT', agri_resolve_commission_percent($conn, null, $_SESSION['user_id'] ?? null));
define('AGRI_PRODUCT_MAX_IMAGE_MB', 5);
define('AGRI_PRODUCT_MAX_IMAGES', 5);

$isLoggedIn = isset($_SESSION['user_id']);

// CSRF token for this form — checked again in insert_product.php.
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$prefillName  = $_SESSION['user_name'] ?? '';
$prefillPhone = '';

// Hero background — reuses an existing AgriCart asset (no new image needed).
// Falls back through a couple of likely paths so this never 404s even if
// your project doesn't have agristore.jpg; swap AGRI_HERO_IMAGE_CANDIDATES
// for your own farm/produce photo path if you'd like a different one.
$heroBgCandidates = [
    'assets/images/sell_product_hero_banner.png',
    'assets/images/agristore.jpg',
    'assets/images/products/organic.jpg',
    'assets/images/products/default.jpg',
];
$heroBgImage = $heroBgCandidates[0];
foreach ($heroBgCandidates as $candidate) {
    if (is_file(__DIR__ . '/../' . $candidate)) { $heroBgImage = $candidate; break; }
}
$heroBgImage = (isset($base_path) ? rtrim($base_path, '/') . '/' : '../') . $heroBgImage;

if ($isLoggedIn) {
    try {
        $puStmt = $conn->prepare("SELECT mobile, email FROM users WHERE id = ? LIMIT 1");
        $puStmt->bind_param("i", $_SESSION['user_id']);
        $puStmt->execute();
        $pu = $puStmt->get_result()->fetch_assoc();
        if ($pu) {
            $prefillPhone = $pu['mobile'] ?? '';
        }
    } catch (\Throwable $e) { /* best-effort prefill only */ }
}

include __DIR__ . '/../includes/header.php';
?>
<style>
.sp-hero{background:linear-gradient(135deg,rgba(6,20,9,.94),rgba(28,90,32,.90) 55%,rgba(46,125,50,.82)),url('<?php echo $heroBgImage; ?>') center/cover no-repeat;padding:56px 20px 90px;color:#fff;text-align:center;position:relative;overflow:hidden}
.sp-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 15%,rgba(255,255,255,.14),transparent 42%),radial-gradient(circle at 88% 85%,rgba(255,255,255,.10),transparent 45%)}
.sp-hero::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:44px;background:#f4f8f4;border-radius:50% 50% 0 0 / 100% 100% 0 0}
.sp-hero-inner{position:relative;max-width:760px;margin:0 auto}
.sp-hero-icon{font-size:40px;margin-bottom:10px;display:inline-block;animation:sp-float 3.2s ease-in-out infinite}
@keyframes sp-float{0%,100%{transform:translateY(0)}50%{transform:translateY(-7px)}}
.sp-hero h1{font-size:31px;font-weight:800;margin:0 0 8px;letter-spacing:-.3px}
.sp-hero p{font-size:15px;opacity:.92;margin:0;max-width:560px;margin-inline:auto}
.sp-hero-badges{display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap}
.sp-hero-badge{background:rgba(255,255,255,.16);backdrop-filter:blur(2px);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px}

.sp-page-bg{background:#f4f8f4;padding-bottom:60px}
.sp-wrap{max-width:860px;margin:0 auto;padding:0 16px;position:relative;z-index:2}
.sp-card{background:#fff;border-radius:20px;box-shadow:0 16px 44px rgba(20,60,30,.13);padding:8px;margin-top:-56px}
.sp-card-inner{padding:26px}

.sp-commission{background:linear-gradient(90deg,#eef7ee,#f5fbf0);border:1px solid #bfe3bf;border-radius:14px;padding:16px 18px;font-size:13.5px;color:#245c26;margin-bottom:6px;display:flex;gap:12px;align-items:flex-start}
.sp-commission i{color:#2E7D32;margin-top:2px;font-size:18px}
.sp-commission strong{color:#1b3a1e}

.sp-earn-box{background:linear-gradient(90deg,#fff9ec,#fffdf6);border:1px dashed #e6c766;border-radius:14px;padding:14px 18px;margin:16px 0 22px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.sp-earn-box .sp-earn-lbl{font-size:12.5px;color:#8a6d00;font-weight:600;display:flex;align-items:center;gap:8px}
.sp-earn-box .sp-earn-val{font-size:20px;font-weight:800;color:#1b3a1e}
.sp-earn-box .sp-earn-val small{font-size:12px;font-weight:600;color:#888}

.sp-section{margin:26px 0 4px}
.sp-section-title{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:800;color:#1b3a1e;text-transform:uppercase;letter-spacing:.03em;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #eef4ee}
.sp-section-title i{background:#e8f5e9;color:#2E7D32;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px}

.sp-row{margin-bottom:16px}
.sp-row label{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:#2c2c2c;margin-bottom:7px}
.sp-row label i{color:#2E7D32;font-size:12px;width:14px;text-align:center}
.sp-req{color:#c0392b;font-weight:800}
.sp-row input, .sp-row select, .sp-row textarea{
    width:100%;box-sizing:border-box;border:1.5px solid #dfe6df;border-radius:10px;
    padding:11px 13px;font-size:14px;font-family:inherit;transition:border-color .15s ease, box-shadow .15s ease;background:#fafcfa
}
.sp-row input:focus, .sp-row select:focus, .sp-row textarea:focus{
    outline:none;border-color:#2E7D32;box-shadow:0 0 0 3px rgba(46,125,50,.12);background:#fff
}
.sp-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.sp-hint{font-size:11.5px;color:#8a9a8a;margin-top:5px}
.sp-error{font-size:11.5px;color:#c0392b;margin-top:5px;display:none}
.sp-row.has-error input, .sp-row.has-error select, .sp-row.has-error textarea{border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,.10)}
.sp-row.has-error .sp-error{display:block}

/* Pill radios (Condition / Delivery / Input Language) */
.sp-pillgroup{display:flex;gap:10px;flex-wrap:wrap}
.sp-pill{position:relative}
.sp-pill input{position:absolute;opacity:0;width:0;height:0}
.sp-pill span{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:1.5px solid #dfe6df;border-radius:24px;font-size:13px;font-weight:700;color:#4a4a4a;cursor:pointer;transition:all .15s ease;background:#fafcfa}
.sp-pill input:checked + span{background:#2E7D32;border-color:#2E7D32;color:#fff;box-shadow:0 4px 12px rgba(46,125,50,.28)}
.sp-pill span:hover{border-color:#2E7D32}

/* Image drag-and-drop upload */
.sp-dropzone{border:2px dashed #bfe3bf;border-radius:14px;background:#f7fbf6;padding:26px 16px;text-align:center;cursor:pointer;transition:border-color .15s ease, background .15s ease}
.sp-dropzone:hover, .sp-dropzone.sp-dragover{border-color:#2E7D32;background:#eef7ee}
.sp-dropzone i{font-size:26px;color:#2E7D32;margin-bottom:8px;display:block}
.sp-dropzone-title{font-size:13.5px;font-weight:700;color:#1b3a1e}
.sp-dropzone-sub{font-size:11.5px;color:#8a9a8a;margin-top:4px}
.sp-image-previews{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px;margin-top:14px}
.sp-image-thumb{position:relative;border-radius:10px;overflow:hidden;border:1px solid #eee;height:96px;background:#f2f2f2}
.sp-image-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.sp-image-remove{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.sp-image-remove:hover{background:#c0392b}

/* Translation preview */
.sp-translate-box{background:#f7fbf6;border:1px solid #dfe9df;border-radius:12px;padding:12px 14px;margin-top:10px}
.sp-translate-row{display:flex;align-items:center;gap:10px;font-size:13px;padding:5px 0}
.sp-translate-row .sp-tlang{width:74px;flex-shrink:0;font-weight:700;color:#2E7D32}
.sp-translate-row .sp-tval{color:#333;flex:1}
.sp-translate-loading{font-size:12px;color:#8a9a8a;display:none;align-items:center;gap:6px}
.sp-translate-loading.active{display:flex}
.sp-translate-loading i{animation:sp-spin 0.8s linear infinite}
@keyframes sp-spin{to{transform:rotate(360deg)}}

.sp-terms-row{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#4a4a4a;margin:18px 0 6px}
.sp-terms-row input{width:auto;margin-top:3px}

.sp-submit{background:linear-gradient(135deg,#2E7D32,#256428);color:#fff;border:none;border-radius:12px;padding:14px 28px;font-weight:700;font-size:15.5px;cursor:pointer;width:100%;margin-top:14px;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 8px 20px rgba(46,125,50,.28);transition:transform .12s ease, box-shadow .12s ease}
.sp-submit:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(46,125,50,.34)}
.sp-submit:active{transform:translateY(0)}
.sp-submit:disabled{opacity:.65;cursor:not-allowed;transform:none}

.sp-success-box{text-align:center;padding:50px 20px}
.sp-success-box i{font-size:46px;color:#2E7D32;margin-bottom:16px;display:block}
.sp-success-box p{font-size:15.5px;color:#1b3a1e;font-weight:600;max-width:480px;margin:0 auto}

.sp-login-gate{text-align:center;padding:44px 20px}
.sp-login-gate i{font-size:36px;color:#2E7D32;margin-bottom:14px;display:block}
.sp-login-gate a{background:linear-gradient(135deg,#2E7D32,#256428);color:#fff;padding:13px 30px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;margin-top:16px;box-shadow:0 8px 20px rgba(46,125,50,.25)}
@media (max-width:560px){.sp-grid2{grid-template-columns:1fr}.sp-hero h1{font-size:24px}.sp-card-inner{padding:20px}}
</style>

<div class="sp-hero">
  <div class="sp-hero-inner">
    <span class="sp-hero-icon">🌾</span>
    <h1 id="spTitle">Sell Your Product on AgriCart</h1>
    <p id="spSub">List your crops, seeds, or farm produce directly to buyers across Maharashtra.</p>
    <div class="sp-hero-badges">
      <span class="sp-hero-badge"><i class="fa-solid fa-shield-halved"></i> <span id="spBadgeReview">Admin reviewed</span></span>
      <span class="sp-hero-badge"><i class="fa-solid fa-users"></i> <span id="spBadgeReach">Statewide reach</span></span>
      <span class="sp-hero-badge"><i class="fa-solid fa-bolt"></i> <span id="spBadgeFast">Quick listing</span></span>
    </div>
  </div>
</div>

<div class="sp-page-bg">
<div class="sp-wrap">
  <div class="sp-card"><div class="sp-card-inner">
    <?php if (!$isLoggedIn): ?>
      <div class="sp-login-gate">
        <i class="fa-solid fa-lock"></i>
        <p id="spLoginMsg">You need to be logged in to list a product for sale.</p>
        <a href="login.php" id="spLoginBtn">Login to Continue</a>
      </div>
    <?php else: ?>
      <div class="sp-earn-box">
        <div class="sp-earn-lbl"><i class="fa-solid fa-sack-dollar"></i> <span id="spEarnLbl">You will receive per unit</span></div>
        <div class="sp-earn-val">₹<span id="spEarnVal">0</span> <small id="spEarnHint">(after 5% platform fee)</small></div>
      </div>

      <div id="spSuccessBox" class="sp-success-box" style="display:none">
        <i class="fa-solid fa-circle-check"></i>
        <p id="spSuccessMsg">Your product has been submitted successfully and is waiting for admin approval.</p>
      </div>

      <form method="POST" action="insert_product.php" id="spForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="p_source" value="farmer">
        <input type="hidden" name="add_product" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">

        <!-- ============ PRODUCT DETAILS ============ -->
        <div class="sp-section">
          <div class="sp-section-title"><i class="fa-solid fa-seedling"></i> <span id="spSectionBasic">Product Details</span></div>

          <input type="hidden" name="p_input_language" id="spInputLang" value="auto">

          <div class="sp-row" id="spNameRow">
            <label id="spLblName"><i class="fa-solid fa-tag"></i> <span id="spLblNameText">Product Name</span> <span class="sp-req">*</span></label>
            <input type="text" name="p_name" id="spName" required maxlength="150">
            <div class="sp-error" id="spNameError">Product name is required.</div>
            <div class="sp-translate-box">
              <div class="sp-translate-loading" id="spTranslateLoading"><i class="fa-solid fa-spinner"></i> <span id="spTranslatingText">Translating…</span></div>
              <div class="sp-translate-row"><span class="sp-tlang" id="spTLangEnLabel">English</span><span class="sp-tval" id="spTPreviewEn">—</span></div>
              <div class="sp-translate-row"><span class="sp-tlang" id="spTLangMrLabel">Marathi</span><span class="sp-tval" id="spTPreviewMr">—</span></div>
              <div class="sp-translate-row"><span class="sp-tlang" id="spTLangHiLabel">Hindi</span><span class="sp-tval" id="spTPreviewHi">—</span></div>
            </div>
          </div>

          <div class="sp-row">
            <label id="spLblCategory"><i class="fa-solid fa-layer-group"></i> Category <span class="sp-req">*</span></label>
            <select name="p_category" id="spCategory" required>
              <option value="" id="spOptSelectCat">Select category</option>
              <option value="seeds" id="spOptSeeds">Seeds</option>
              <option value="fertilizer" id="spOptFertilizer">Fertilizer</option>
              <option value="pesticides" id="spOptPesticides">Pesticides</option>
              <option value="tools" id="spOptTools">Tools</option>
              <option value="irrigation" id="spOptIrrigation">Irrigation</option>
              <option value="feed" id="spOptFeed">Feed</option>
              <option value="organic" id="spOptOrganic">Organic Produce</option>
              <option value="cropkits" id="spOptCropkits">Crop Kits</option>
            </select>
          </div>

          <div class="sp-row">
            <label id="spLblBrand"><i class="fa-solid fa-award"></i> Brand or Company Name</label>
            <input type="text" name="p_brand" id="spBrand" maxlength="150">
          </div>

          <div class="sp-row">
            <label id="spLblDesc"><i class="fa-solid fa-align-left"></i> Product Description</label>
            <textarea name="p_description" id="spDesc" rows="3" maxlength="1000"></textarea>
          </div>

          <div class="sp-row">
            <label id="spLblCondition"><i class="fa-solid fa-tags"></i> Product Condition <span class="sp-req">*</span></label>
            <div class="sp-pillgroup">
              <label class="sp-pill"><input type="radio" name="p_condition" value="new" checked><span id="spCondNew">New</span></label>
              <label class="sp-pill"><input type="radio" name="p_condition" value="used"><span id="spCondUsed">Used</span></label>
            </div>
          </div>

          <div class="sp-row">
            <label id="spLblImages"><i class="fa-solid fa-images"></i> Product Images <span class="sp-req">*</span></label>
            <div class="sp-dropzone" id="spDropzone">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <div class="sp-dropzone-title" id="spDropzoneTitle">Drag &amp; drop images here, or click to browse</div>
              <div class="sp-dropzone-sub" id="spDropzoneSub">JPG, JPEG, PNG or WEBP · up to 5 MB each · up to 5 images</div>
              <input type="file" name="p_images[]" id="spImages" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple style="display:none">
            </div>
            <div class="sp-image-previews" id="spImagePreviews"></div>
            <div class="sp-error" id="spImagesError">Please add at least one product image.</div>
          </div>
        </div>

        <!-- ============ PRICE AND STOCK ============ -->
        <div class="sp-section">
          <div class="sp-section-title"><i class="fa-solid fa-indian-rupee-sign"></i> <span id="spSectionPricing">Price and Stock</span></div>
          <div class="sp-grid2">
            <div class="sp-row">
              <label id="spLblPrice"><i class="fa-solid fa-indian-rupee-sign"></i> Product Price (₹) <span class="sp-req">*</span></label>
              <input type="number" name="p_price" id="spPrice" min="1" step="0.01" required oninput="spUpdateEarnings()">
              <div class="sp-error" id="spPriceError">Enter a valid price.</div>
            </div>
            <div class="sp-row">
              <label id="spLblStock"><i class="fa-solid fa-warehouse"></i> Available Quantity <span class="sp-req">*</span></label>
              <input type="number" name="p_stock" id="spStock" min="0" required>
              <div class="sp-error" id="spStockError">Enter a valid quantity.</div>
            </div>
          </div>
          <div class="sp-row">
            <label id="spLblUnit"><i class="fa-solid fa-box"></i> Unit</label>
            <select name="p_unit" id="spUnit">
              <option value="Kg" id="spOptUnitKg">Kg</option>
              <option value="Quintal" id="spOptUnitQuintal">Quintal</option>
              <option value="Litre" id="spOptUnitLitre">Litre</option>
              <option value="Packet" id="spOptUnitPacket">Packet</option>
              <option value="Piece" id="spOptUnitPiece">Piece</option>
              <option value="Dozen" id="spOptUnitDozen">Dozen</option>
            </select>
          </div>
          <div class="sp-row">
            <label id="spLblDelivery"><i class="fa-solid fa-truck"></i> Delivery Available</label>
            <div class="sp-pillgroup">
              <label class="sp-pill"><input type="radio" name="p_delivery" value="yes" checked><span id="spDeliveryYes">Yes</span></label>
              <label class="sp-pill"><input type="radio" name="p_delivery" value="no"><span id="spDeliveryNo">No</span></label>
            </div>
          </div>
        </div>

        <!-- ============ SELLER DETAILS ============ -->
        <div class="sp-section">
          <div class="sp-section-title"><i class="fa-solid fa-user"></i> <span id="spSectionSeller">Seller Details</span></div>
          <div class="sp-grid2">
            <div class="sp-row">
              <label id="spLblFarmer"><i class="fa-solid fa-user"></i> Seller Name <span class="sp-req">*</span></label>
              <input type="text" name="p_farmer" id="spFarmer" required value="<?php echo htmlspecialchars($prefillName); ?>">
            </div>
            <div class="sp-row">
              <label id="spLblPhone"><i class="fa-solid fa-phone"></i> Mobile Number <span class="sp-req">*</span></label>
              <input type="tel" name="p_phone" id="spPhone" required maxlength="10" value="<?php echo htmlspecialchars($prefillPhone); ?>">
              <div class="sp-error" id="spPhoneError">Enter a valid 10-digit mobile number.</div>
            </div>
          </div>
          <div class="sp-row">
            <label id="spLblEmail"><i class="fa-solid fa-envelope"></i> Email Address</label>
            <input type="email" name="p_email" id="spEmail">
            <div class="sp-error" id="spEmailError">Enter a valid email address.</div>
          </div>
          <div class="sp-grid2">
            <div class="sp-row">
              <label id="spLblVillage"><i class="fa-solid fa-location-dot"></i> Village or City <span class="sp-req">*</span></label>
              <input type="text" name="p_village" id="spVillage" required>
            </div>
            <div class="sp-row">
              <label id="spLblDistrict"><i class="fa-solid fa-map"></i> District <span class="sp-req">*</span></label>
              <input type="text" name="p_district" id="spDistrict" required>
            </div>
          </div>
          <div class="sp-row">
            <label id="spLblAddress"><i class="fa-solid fa-house"></i> Full Address <span class="sp-req">*</span></label>
            <textarea name="p_address" id="spAddress" rows="2" required></textarea>
          </div>
        </div>

        <label class="sp-terms-row">
          <input type="checkbox" name="p_terms" id="spTerms" required>
          <span id="spTermsLabel">I agree to AgriCart's Terms and Conditions for listing products.</span>
        </label>
        <div class="sp-error" id="spTermsError">You must accept the Terms and Conditions.</div>

        <button type="submit" class="sp-submit" id="spSubmitButton"><i class="fa-solid fa-upload"></i> <span id="spSubmitBtn">Submit Product</span></button>
      </form>
    <?php endif; ?>
  </div></div>
</div>
</div>

<script>
const AGRI_PRODUCT_COMMISSION_PERCENT = <?php echo AGRI_PRODUCT_COMMISSION_PERCENT; ?>;
const AGRI_PRODUCT_MAX_IMAGE_MB = <?php echo AGRI_PRODUCT_MAX_IMAGE_MB; ?>;
const AGRI_PRODUCT_MAX_IMAGES = <?php echo AGRI_PRODUCT_MAX_IMAGES; ?>;
const AGRI_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;

const SellProductT = {
    en: {
        title:"Sell Your Product on AgriCart",
        sub:"List your crops, seeds, or farm produce directly to buyers across Maharashtra.",
        badgeReview:"Admin reviewed", badgeReach:"Statewide reach", badgeFast:"Quick listing",
        loginMsg:"You need to be logged in to list a product for sale.",
        loginBtn:"Login to Continue",
        earnLbl:"You will receive per unit", earnHint:`(after ${AGRI_PRODUCT_COMMISSION_PERCENT}% platform fee)`,
        sectionBasic:"Product Details", sectionPricing:"Price and Stock", sectionSeller:"Seller Details",
        inputLangLbl:"Input Language", inputLangHint:"You may type the product name in any supported language.",
        optLangAuto:"Auto Detect", optLangEn:"English", optLangMr:"Marathi", optLangHi:"Hindi",
        lblNameText:"Product Name", namePh:"e.g. Fresh Tomatoes", nameRequired:"Product name is required.",
        tLangEn:"English", tLangMr:"Marathi", tLangHi:"Hindi", translating:"Translating…",
        lblCategory:"Category", optSelectCat:"Select category",
        optSeeds:"Seeds", optFertilizer:"Fertilizer", optPesticides:"Pesticides", optTools:"Tools",
        optIrrigation:"Irrigation", optFeed:"Feed", optOrganic:"Organic Produce", optCropkits:"Crop Kits",
        lblBrand:"Brand or Company Name",
        lblDesc:"Product Description", descPh:"Tell buyers about your product — quality, harvest date, etc.",
        lblCondition:"Product Condition", condNew:"New", condUsed:"Used",
        lblImages:"Product Images", dropzoneTitle:"Drag & drop images here, or click to browse",
        dropzoneSub:`JPG, JPEG, PNG or WEBP · up to ${AGRI_PRODUCT_MAX_IMAGE_MB} MB each · up to ${AGRI_PRODUCT_MAX_IMAGES} images`,
        imagesRequired:"Please add at least one product image.",
        imageTooLarge:"is larger than " + AGRI_PRODUCT_MAX_IMAGE_MB + " MB and was skipped.",
        imageBadType:"is not a supported format (JPG, JPEG, PNG, WEBP) and was skipped.",
        imageTooMany:"You can upload up to " + AGRI_PRODUCT_MAX_IMAGES + " images.",
        lblPrice:"Product Price (₹)", priceRequired:"Enter a valid price.",
        lblStock:"Available Quantity", stockRequired:"Enter a valid quantity.",
        lblUnit:"Unit", unitKg:"Kg", unitQuintal:"Quintal", unitLitre:"Litre", unitPacket:"Packet", unitPiece:"Piece", unitDozen:"Dozen",
        lblDelivery:"Delivery Available", deliveryYes:"Yes", deliveryNo:"No",
        lblFarmer:"Seller Name",
        lblPhone:"Mobile Number", phoneRequired:"Enter a valid 10-digit mobile number.",
        lblEmail:"Email Address", emailInvalid:"Enter a valid email address.",
        lblVillage:"Village or City", lblDistrict:"District", lblAddress:"Full Address",
        termsLabel:"I agree to AgriCart's Terms and Conditions for listing products.",
        termsRequired:"You must accept the Terms and Conditions.",
        submitBtn:"Submit Product", submitting:"Submitting…",
        successMsg:"Your product has been submitted successfully and is waiting for admin approval.",
        genericError:"Something went wrong. Please check the form and try again.",
        networkError:"Network error, please try again."
    },
    mr: {
        title:"AgriCart वर तुमचे उत्पादन विका",
        sub:"तुमची पिके, बियाणे किंवा शेतमाल थेट महाराष्ट्रभरातील खरेदीदारांना विका.",
        badgeReview:"अ‍ॅडमिन तपासणी", badgeReach:"राज्यभर पोहोच", badgeFast:"झटपट लिस्टिंग",
        loginMsg:"उत्पादन विक्रीसाठी list करण्यासाठी login करणे आवश्यक आहे.",
        loginBtn:"पुढे जाण्यासाठी Login करा",
        earnLbl:"प्रति युनिट तुम्हाला मिळतील", earnHint:`(${AGRI_PRODUCT_COMMISSION_PERCENT}% प्लॅटफॉर्म फी नंतर)`,
        sectionBasic:"उत्पादनाची माहिती", sectionPricing:"किंमत आणि साठा", sectionSeller:"विक्रेत्याची माहिती",
        inputLangLbl:"इनपुट भाषा", inputLangHint:"तुम्ही उत्पादनाचे नाव कोणत्याही समर्थित भाषेत टाकू शकता.",
        optLangAuto:"आपोआप ओळखा", optLangEn:"इंग्रजी", optLangMr:"मराठी", optLangHi:"हिंदी",
        lblNameText:"उत्पादनाचे नाव", namePh:"उदा. ताजे टोमॅटो", nameRequired:"उत्पादनाचे नाव आवश्यक आहे.",
        tLangEn:"इंग्रजी", tLangMr:"मराठी", tLangHi:"हिंदी", translating:"भाषांतर सुरू आहे…",
        lblCategory:"श्रेणी", optSelectCat:"श्रेणी निवडा",
        optSeeds:"बियाणे", optFertilizer:"खत", optPesticides:"कीटकनाशके", optTools:"अवजारे",
        optIrrigation:"सिंचन", optFeed:"पशुखाद्य", optOrganic:"सेंद्रिय शेतमाल", optCropkits:"पीक किट",
        lblBrand:"ब्रँड किंवा कंपनीचे नाव",
        lblDesc:"उत्पादनाचे वर्णन", descPh:"तुमच्या उत्पादनाबद्दल सांगा — गुणवत्ता, काढणीची तारीख, इ.",
        lblCondition:"उत्पादनाची स्थिती", condNew:"नवीन", condUsed:"वापरलेले",
        lblImages:"उत्पादनाचे फोटो", dropzoneTitle:"फोटो इथे ड्रॅग करा, किंवा क्लिक करून निवडा",
        dropzoneSub:`JPG, JPEG, PNG किंवा WEBP · प्रत्येकी ${AGRI_PRODUCT_MAX_IMAGE_MB} MB पर्यंत · जास्तीत जास्त ${AGRI_PRODUCT_MAX_IMAGES} फोटो`,
        imagesRequired:"कृपया किमान एक उत्पादन फोटो जोडा.",
        imageTooLarge:"" + AGRI_PRODUCT_MAX_IMAGE_MB + " MB पेक्षा मोठा आहे म्हणून वगळले.",
        imageBadType:"समर्थित फॉरमॅट (JPG, JPEG, PNG, WEBP) नाही म्हणून वगळले.",
        imageTooMany:"तुम्ही जास्तीत जास्त " + AGRI_PRODUCT_MAX_IMAGES + " फोटो अपलोड करू शकता.",
        lblPrice:"उत्पादनाची किंमत (₹)", priceRequired:"वैध किंमत टाका.",
        lblStock:"उपलब्ध प्रमाण", stockRequired:"वैध प्रमाण टाका.",
        lblUnit:"एकक", unitKg:"किलो", unitQuintal:"क्विंटल", unitLitre:"लिटर", unitPacket:"पॅकेट", unitPiece:"नग", unitDozen:"डझन",
        lblDelivery:"डिलिव्हरी उपलब्ध", deliveryYes:"होय", deliveryNo:"नाही",
        lblFarmer:"विक्रेत्याचे नाव",
        lblPhone:"मोबाईल नंबर", phoneRequired:"वैध 10-अंकी मोबाईल नंबर टाका.",
        lblEmail:"ईमेल पत्ता", emailInvalid:"वैध ईमेल पत्ता टाका.",
        lblVillage:"गाव किंवा शहर", lblDistrict:"जिल्हा", lblAddress:"संपूर्ण पत्ता",
        termsLabel:"मी AgriCart च्या उत्पादन विक्रीच्या नियम व अटी मान्य करतो/करते.",
        termsRequired:"तुम्हाला नियम व अटी मान्य कराव्या लागतील.",
        submitBtn:"उत्पादन सबमिट करा", submitting:"सबमिट होत आहे…",
        successMsg:"तुमचे उत्पादन यशस्वीरित्या सबमिट झाले आहे आणि अ‍ॅडमिनच्या मंजुरीची वाट पाहत आहे.",
        genericError:"काहीतरी चुकले. कृपया फॉर्म तपासा आणि पुन्हा प्रयत्न करा.",
        networkError:"नेटवर्क एरर, कृपया पुन्हा प्रयत्न करा."
    },
    hi: {
        title:"AgriCart पर अपना उत्पाद बेचें",
        sub:"अपनी फसलें, बीज या खेत की उपज सीधे महाराष्ट्र भर के खरीदारों को बेचें।",
        badgeReview:"एडमिन समीक्षा", badgeReach:"राज्यभर पहुंच", badgeFast:"त्वरित लिस्टिंग",
        loginMsg:"उत्पाद बेचने के लिए लिस्ट करने हेतु login करना ज़रूरी है।",
        loginBtn:"जारी रखने के लिए Login करें",
        earnLbl:"प्रति यूनिट आपको मिलेंगे", earnHint:`(${AGRI_PRODUCT_COMMISSION_PERCENT}% प्लेटफ़ॉर्म शुल्क के बाद)`,
        sectionBasic:"उत्पाद विवरण", sectionPricing:"कीमत और स्टॉक", sectionSeller:"विक्रेता विवरण",
        inputLangLbl:"इनपुट भाषा", inputLangHint:"आप उत्पाद का नाम किसी भी समर्थित भाषा में लिख सकते हैं।",
        optLangAuto:"स्वतः पहचानें", optLangEn:"अंग्रेज़ी", optLangMr:"मराठी", optLangHi:"हिंदी",
        lblNameText:"उत्पाद का नाम", namePh:"जैसे ताज़े टमाटर", nameRequired:"उत्पाद का नाम आवश्यक है।",
        tLangEn:"अंग्रेज़ी", tLangMr:"मराठी", tLangHi:"हिंदी", translating:"अनुवाद हो रहा है…",
        lblCategory:"श्रेणी", optSelectCat:"श्रेणी चुनें",
        optSeeds:"बीज", optFertilizer:"खाद", optPesticides:"कीटनाशक", optTools:"उपकरण",
        optIrrigation:"सिंचाई", optFeed:"पशु आहार", optOrganic:"जैविक उपज", optCropkits:"क्रॉप किट",
        lblBrand:"ब्रांड या कंपनी का नाम",
        lblDesc:"उत्पाद विवरण", descPh:"अपने उत्पाद के बारे में बताएं — गुणवत्ता, कटाई की तारीख, आदि।",
        lblCondition:"उत्पाद की स्थिति", condNew:"नया", condUsed:"इस्तेमाल किया हुआ",
        lblImages:"उत्पाद की तस्वीरें", dropzoneTitle:"तस्वीरें यहां खींचें और छोड़ें, या क्लिक करके चुनें",
        dropzoneSub:`JPG, JPEG, PNG या WEBP · प्रत्येक ${AGRI_PRODUCT_MAX_IMAGE_MB} MB तक · अधिकतम ${AGRI_PRODUCT_MAX_IMAGES} तस्वीरें`,
        imagesRequired:"कृपया कम से कम एक उत्पाद तस्वीर जोड़ें।",
        imageTooLarge:"" + AGRI_PRODUCT_MAX_IMAGE_MB + " MB से बड़ी है इसलिए छोड़ी गई।",
        imageBadType:"समर्थित फॉर्मेट (JPG, JPEG, PNG, WEBP) नहीं है इसलिए छोड़ी गई।",
        imageTooMany:"आप अधिकतम " + AGRI_PRODUCT_MAX_IMAGES + " तस्वीरें अपलोड कर सकते हैं।",
        lblPrice:"उत्पाद की कीमत (₹)", priceRequired:"मान्य कीमत दर्ज करें।",
        lblStock:"उपलब्ध मात्रा", stockRequired:"मान्य मात्रा दर्ज करें।",
        lblUnit:"इकाई", unitKg:"किलो", unitQuintal:"क्विंटल", unitLitre:"लीटर", unitPacket:"पैकेट", unitPiece:"पीस", unitDozen:"दर्जन",
        lblDelivery:"डिलीवरी उपलब्ध", deliveryYes:"हां", deliveryNo:"नहीं",
        lblFarmer:"विक्रेता का नाम",
        lblPhone:"मोबाइल नंबर", phoneRequired:"मान्य 10-अंकों का मोबाइल नंबर दर्ज करें।",
        lblEmail:"ईमेल पता", emailInvalid:"मान्य ईमेल पता दर्ज करें।",
        lblVillage:"गांव या शहर", lblDistrict:"ज़िला", lblAddress:"पूरा पता",
        termsLabel:"मैं उत्पाद सूचीबद्ध करने हेतु AgriCart के नियम और शर्तों से सहमत हूं।",
        termsRequired:"आपको नियम और शर्तें स्वीकार करनी होंगी।",
        submitBtn:"उत्पाद सबमिट करें", submitting:"सबमिट हो रहा है…",
        successMsg:"आपका उत्पाद सफलतापूर्वक सबमिट हो गया है और एडमिन की मंजूरी का इंतज़ार कर रहा है।",
        genericError:"कुछ गलत हो गया। कृपया फॉर्म जांचें और पुनः प्रयास करें।",
        networkError:"नेटवर्क त्रुटि, कृपया पुनः प्रयास करें।"
    }
};

function spSetText(id, val){ const el = document.getElementById(id); if (el) el.textContent = val; }
function spSetHtml(id, val){ const el = document.getElementById(id); if (el) el.innerHTML = val; }
function spSetPh(id, val){ const el = document.getElementById(id); if (el) el.placeholder = val; }

function spUpdateEarnings(){
    const priceEl = document.getElementById('spPrice');
    const price = parseFloat(priceEl ? priceEl.value : 0) || 0;
    const net = price - (price * AGRI_PRODUCT_COMMISSION_PERCENT / 100);
    spSetText('spEarnVal', net > 0 ? net.toFixed(2) : '0');
}

/* ---------------- IMAGE UPLOAD (drag & drop, preview, remove, validation) ---------------- */
let spSelectedFiles = []; // File objects the user has picked, survives add/remove

function spCurrentT(){ return SellProductT[window.lang || 'en']; }

function spRenderImagePreviews(){
    const wrap = document.getElementById('spImagePreviews');
    wrap.innerHTML = '';
    spSelectedFiles.forEach((file, idx) => {
        const url = URL.createObjectURL(file);
        const thumb = document.createElement('div');
        thumb.className = 'sp-image-thumb';
        thumb.innerHTML = `<img src="${url}" alt=""><button type="button" class="sp-image-remove" data-idx="${idx}"><i class="fa-solid fa-xmark"></i></button>`;
        wrap.appendChild(thumb);
    });
    wrap.querySelectorAll('.sp-image-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            spSelectedFiles.splice(parseInt(btn.dataset.idx, 10), 1);
            spRenderImagePreviews();
            spSyncFileInput();
        });
    });
}

function spSyncFileInput(){
    const dt = new DataTransfer();
    spSelectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('spImages').files = dt.files;
}

function spAddFiles(fileList){
    const pt = spCurrentT();
    const incoming = Array.from(fileList || []);
    for (const file of incoming) {
        if (spSelectedFiles.length >= AGRI_PRODUCT_MAX_IMAGES) { showSpToast(pt.imageTooMany); break; }
        if (!AGRI_ALLOWED_IMAGE_TYPES.includes(file.type)) { showSpToast(file.name + ' ' + pt.imageBadType); continue; }
        if (file.size > AGRI_PRODUCT_MAX_IMAGE_MB * 1024 * 1024) { showSpToast(file.name + ' ' + pt.imageTooLarge); continue; }
        spSelectedFiles.push(file);
    }
    spRenderImagePreviews();
    spSyncFileInput();
    spClearFieldError('spImages');
}

function showSpToast(msg){
    // Minimal inline toast; falls back to alert if the site-wide toast isn't present.
    if (typeof showToast === 'function') { showToast(msg); return; }
    console.warn(msg);
}

/* ---------------- LIVE TRANSLATION PREVIEW (debounced) ---------------- */
let spTranslateTimer = null;
function spScheduleTranslatePreview(){
    clearTimeout(spTranslateTimer);
    const name = document.getElementById('spName').value.trim();
    if (!name) {
        spSetText('spTPreviewEn', '—'); spSetText('spTPreviewMr', '—'); spSetText('spTPreviewHi', '—');
        return;
    }
    spTranslateTimer = setTimeout(spRunTranslatePreview, 500);
}
function spRunTranslatePreview(){
    const name = document.getElementById('spName').value.trim();
    if (!name) return;
    const inputLang = document.getElementById('spInputLang').value;
    document.getElementById('spTranslateLoading').classList.add('active');
    fetch('translate_preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ name, input_language: inputLang, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('spTranslateLoading').classList.remove('active');
        if (data && data.success) {
            spSetText('spTPreviewEn', data.en || name);
            spSetText('spTPreviewMr', data.mr || name);
            spSetText('spTPreviewHi', data.hi || name);
        }
    })
    .catch(() => {
        // Translation failure must never block submission — just clear the spinner.
        document.getElementById('spTranslateLoading').classList.remove('active');
    });
}

/* ---------------- VALIDATION ---------------- */
function spClearFieldError(fieldId){
    const row = document.getElementById(fieldId).closest('.sp-row') || document.getElementById(fieldId).closest('label');
    if (row) row.classList.remove('has-error');
}
function spShowFieldError(fieldId){
    const field = document.getElementById(fieldId);
    const row = field.closest('.sp-row') || field.closest('label');
    if (row) row.classList.add('has-error');
}

function spValidateForm(){
    const pt = spCurrentT();
    let ok = true;

    const required = ['spName','spCategory','spPrice','spStock','spFarmer','spPhone','spVillage','spDistrict','spAddress'];
    required.forEach(id => spClearFieldError(id));
    document.getElementById('spImagesError').closest('.sp-row').classList.remove('has-error');
    document.getElementById('spTermsError').style.display = 'none';

    required.forEach(id => {
        const el = document.getElementById(id);
        if (!el.value || !el.value.trim()) { spShowFieldError(id); ok = false; }
    });

    const price = parseFloat(document.getElementById('spPrice').value);
    if (isNaN(price) || price <= 0) { spShowFieldError('spPrice'); ok = false; }

    const stock = parseInt(document.getElementById('spStock').value, 10);
    if (isNaN(stock) || stock < 0) { spShowFieldError('spStock'); ok = false; }

    const phone = document.getElementById('spPhone').value.trim();
    if (!/^[0-9]{10}$/.test(phone)) { spShowFieldError('spPhone'); ok = false; }

    const email = document.getElementById('spEmail').value.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { spShowFieldError('spEmail'); ok = false; }

    if (spSelectedFiles.length === 0) {
        document.getElementById('spImagesError').closest('.sp-row').classList.add('has-error');
        ok = false;
    }

    if (!document.getElementById('spTerms').checked) {
        document.getElementById('spTermsError').style.display = 'block';
        ok = false;
    }

    return ok;
}

/* ---------------- SUBMIT (AJAX so we can show the success message inline) ---------------- */
function spInitForm(){
    const form = document.getElementById('spForm');
    if (!form) return;

    const dropzone = document.getElementById('spDropzone');
    const fileInput = document.getElementById('spImages');
    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', (e) => spAddFiles(e.target.files));
    ['dragenter','dragover'].forEach(evt => dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('sp-dragover'); }));
    ['dragleave','drop'].forEach(evt => dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('sp-dragover'); }));
    dropzone.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files) spAddFiles(e.dataTransfer.files); });

    document.getElementById('spName').addEventListener('input', spScheduleTranslatePreview);

    form.addEventListener('submit', function(e){
        e.preventDefault();
        const pt = spCurrentT();
        if (!spValidateForm()) { return; }

        const btn = document.getElementById('spSubmitButton');
        btn.disabled = true;
        const originalLabel = document.getElementById('spSubmitBtn').textContent;
        document.getElementById('spSubmitBtn').textContent = pt.submitting;

        const formData = new FormData(form);
        formData.append('ajax', '1');

        fetch('insert_product.php', { method: 'POST', body: formData })
            .then(r => r.json().catch(() => ({ success:false, error: pt.genericError })))
            .then(data => {
                if (data.success) {
                    form.style.display = 'none';
                    const box = document.getElementById('spSuccessBox');
                    box.style.display = 'block';
                    spSetText('spSuccessMsg', pt.successMsg);
                } else {
                    showSpToast(data.error || pt.genericError);
                    btn.disabled = false;
                    document.getElementById('spSubmitBtn').textContent = originalLabel;
                }
            })
            .catch(() => {
                showSpToast(pt.networkError);
                btn.disabled = false;
                document.getElementById('spSubmitBtn').textContent = originalLabel;
            });
    });
}

window.pageLanguageCallback = function(lang){
    const pt = SellProductT[lang] || SellProductT.en;
    spSetText('spTitle', pt.title);
    spSetText('spSub', pt.sub);
    spSetText('spBadgeReview', pt.badgeReview);
    spSetText('spBadgeReach', pt.badgeReach);
    spSetText('spBadgeFast', pt.badgeFast);
    spSetText('spLoginMsg', pt.loginMsg);
    spSetText('spLoginBtn', pt.loginBtn);
    spSetText('spEarnLbl', pt.earnLbl);
    spSetText('spEarnHint', pt.earnHint);
    spSetText('spSectionBasic', pt.sectionBasic);
    spSetText('spSectionPricing', pt.sectionPricing);
    spSetText('spSectionSeller', pt.sectionSeller);

    // BUG FIX: the input language must NOT follow the header's UI display
    // language — a seller can type a product name in any script regardless
    // of what language the page labels are shown in (e.g. header set to
    // Hindi while typing an English name like "tomato"). Forcing
    // spInputLang to `lang` here made agri_translate_product_name() assume
    // the wrong source language, so dictionary/API lookups failed and the
    // preview silently echoed the original text back for every language.
    // Leave it on "auto" so the server (agri_detect_language) detects the
    // real language of what was actually typed.

    spSetText('spLblNameText', pt.lblNameText); spSetPh('spName', pt.namePh);
    spSetText('spNameError', pt.nameRequired);
    spSetText('spTLangEnLabel', pt.tLangEn); spSetText('spTLangMrLabel', pt.tLangMr); spSetText('spTLangHiLabel', pt.tLangHi);
    spSetText('spTranslatingText', pt.translating);

    spSetText('spLblCategory', pt.lblCategory);
    spSetText('spOptSelectCat', pt.optSelectCat);
    spSetText('spOptSeeds', pt.optSeeds); spSetText('spOptFertilizer', pt.optFertilizer);
    spSetText('spOptPesticides', pt.optPesticides); spSetText('spOptTools', pt.optTools);
    spSetText('spOptIrrigation', pt.optIrrigation); spSetText('spOptFeed', pt.optFeed);
    spSetText('spOptOrganic', pt.optOrganic); spSetText('spOptCropkits', pt.optCropkits);

    spSetText('spLblBrand', pt.lblBrand);
    spSetText('spLblDesc', pt.lblDesc); spSetPh('spDesc', pt.descPh);
    spSetText('spLblCondition', pt.lblCondition); spSetText('spCondNew', pt.condNew); spSetText('spCondUsed', pt.condUsed);

    spSetText('spLblImages', pt.lblImages);
    spSetText('spDropzoneTitle', pt.dropzoneTitle);
    spSetText('spDropzoneSub', pt.dropzoneSub);
    spSetText('spImagesError', pt.imagesRequired);

    spSetText('spLblPrice', pt.lblPrice); spSetText('spPriceError', pt.priceRequired);
    spSetText('spLblStock', pt.lblStock); spSetText('spStockError', pt.stockRequired);
    spSetText('spLblUnit', pt.lblUnit);
    spSetText('spOptUnitKg', pt.unitKg); spSetText('spOptUnitQuintal', pt.unitQuintal);
    spSetText('spOptUnitLitre', pt.unitLitre); spSetText('spOptUnitPacket', pt.unitPacket);
    spSetText('spOptUnitPiece', pt.unitPiece); spSetText('spOptUnitDozen', pt.unitDozen);
    spSetText('spLblDelivery', pt.lblDelivery); spSetText('spDeliveryYes', pt.deliveryYes); spSetText('spDeliveryNo', pt.deliveryNo);

    spSetText('spLblFarmer', pt.lblFarmer);
    spSetText('spLblPhone', pt.lblPhone); spSetText('spPhoneError', pt.phoneRequired);
    spSetText('spLblEmail', pt.lblEmail); spSetText('spEmailError', pt.emailInvalid);
    spSetText('spLblVillage', pt.lblVillage);
    spSetText('spLblDistrict', pt.lblDistrict);
    spSetText('spLblAddress', pt.lblAddress);

    spSetText('spTermsLabel', pt.termsLabel);
    spSetText('spTermsError', pt.termsRequired);
    spSetText('spSubmitBtn', pt.submitBtn);
    spSetText('spSuccessMsg', pt.successMsg);
};

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    window.lang = savedLang;
    window.pageLanguageCallback(savedLang);
    spUpdateEarnings();
    spInitForm();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
