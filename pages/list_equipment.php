<?php
// =====================================================
// AgriCart — "List Your Equipment for Rent" (owner self-listing form)
// A logged-in farmer/owner can list their own tractor/harvester/drone/etc.
// for other farmers to rent. Goes to Admin for approval first, and
// AgriCart's platform commission on every booking is shown up front.
//
// Fully translated (English / मराठी / हिंदी) — follows the same
// window.pageLanguageCallback pattern the header's language switcher
// already uses on the rest of the site.
//
// Equipment Name is entered by the owner in ANY of the three supported
// languages. It is translated into all three and saved separately — the
// original text the owner typed is never overwritten. Translation is
// server-side only (includes/agri_translate.php), no API key in the
// frontend. The input language is taken automatically from whichever
// site language is currently selected in the header (no separate picker).
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

define('AGRI_EQUIPMENT_COMMISSION_PERCENT', 10.00);
define('AGRI_EQUIPMENT_MAX_IMAGE_MB', 5);
define('AGRI_EQUIPMENT_MAX_IMAGES', 6);
define('AGRI_EQUIPMENT_MAX_DOC_MB', 8);
define('AGRI_EQUIPMENT_MAX_DOCS', 4);

$isLoggedIn = isset($_SESSION['user_id']);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$prefillName  = $_SESSION['user_name'] ?? '';
$prefillPhone = '';
if ($isLoggedIn) {
    try {
        $puStmt = $conn->prepare("SELECT mobile FROM users WHERE id = ? LIMIT 1");
        $puStmt->bind_param("i", $_SESSION['user_id']);
        $puStmt->execute();
        $pu = $puStmt->get_result()->fetch_assoc();
        if ($pu) { $prefillPhone = $pu['mobile'] ?? ''; }
    } catch (\Throwable $e) { /* best-effort prefill only */ }
}

// Hero background — reuses an existing AgriCart asset (no new image needed).
$heroBgCandidates = [
    'assets/images/equipment.png',
    'assets/images/agristore.jpg',
    'assets/images/products/tools.jpg',
];
$heroBgImage = $heroBgCandidates[0];
foreach ($heroBgCandidates as $candidate) {
    if (is_file(__DIR__ . '/../' . $candidate)) { $heroBgImage = $candidate; break; }
}
$heroBgImage = (isset($base_path) ? rtrim($base_path, '/') . '/' : '../') . $heroBgImage;

include __DIR__ . '/../includes/header.php';
?>
<style>
.le-hero{background:linear-gradient(135deg,rgba(50,20,0,.93),rgba(230,81,0,.82) 55%,rgba(255,167,51,.62)),url('<?php echo $heroBgImage; ?>') center/cover no-repeat;padding:56px 20px 90px;color:#fff;text-align:center;position:relative;overflow:hidden}
.le-hero::before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 15%,rgba(255,255,255,.16),transparent 42%),radial-gradient(circle at 88% 85%,rgba(255,255,255,.10),transparent 45%)}
.le-hero::after{content:"";position:absolute;left:0;right:0;bottom:-1px;height:44px;background:#faf5f0;border-radius:50% 50% 0 0 / 100% 100% 0 0}
.le-hero-inner{position:relative;max-width:760px;margin:0 auto}
.le-hero-icon{font-size:40px;margin-bottom:10px;display:inline-block;animation:le-bounce 2.6s ease-in-out infinite}
@keyframes le-bounce{0%,100%{transform:translateY(0) rotate(0deg)}50%{transform:translateY(-7px) rotate(-4deg)}}
.le-hero h1{font-size:31px;font-weight:800;margin:0 0 8px;letter-spacing:-.3px}
.le-hero p{font-size:15px;opacity:.94;margin:0;max-width:560px;margin-inline:auto}
.le-hero-badges{display:flex;gap:10px;justify-content:center;margin-top:18px;flex-wrap:wrap}
.le-hero-badge{background:rgba(255,255,255,.16);backdrop-filter:blur(2px);border:1px solid rgba(255,255,255,.3);border-radius:20px;padding:6px 14px;font-size:12px;font-weight:600;display:flex;align-items:center;gap:6px}

.le-page-bg{background:#faf5f0;padding-bottom:60px}
.le-wrap{max-width:860px;margin:0 auto;padding:0 16px;position:relative;z-index:2}
.le-card{background:#fff;border-radius:20px;box-shadow:0 16px 44px rgba(90,40,0,.13);padding:8px;margin-top:-56px}
.le-card-inner{padding:26px}

.le-earn-box{background:linear-gradient(90deg,#eef7ee,#f5fbf0);border:1px dashed #9fcf9f;border-radius:14px;padding:14px 18px;margin:16px 0 22px;display:flex;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap}
.le-earn-box .le-earn-lbl{font-size:12.5px;color:#256428;font-weight:600;display:flex;align-items:center;gap:8px}
.le-earn-box .le-earn-val{font-size:20px;font-weight:800;color:#1b3a1e}
.le-earn-box .le-earn-val small{font-size:12px;font-weight:600;color:#888}

.le-section{margin:26px 0 4px}
.le-section-title{display:flex;align-items:center;gap:9px;font-size:13.5px;font-weight:800;color:#5c2c00;text-transform:uppercase;letter-spacing:.03em;margin-bottom:16px;padding-bottom:8px;border-bottom:2px solid #f5ebe0}
.le-section-title i{background:#fff1e0;color:#e65100;width:26px;height:26px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:12px}

.le-row{margin-bottom:16px}
.le-row label{display:flex;align-items:center;gap:7px;font-size:13px;font-weight:700;color:#2c2c2c;margin-bottom:7px}
.le-row label i{color:#e65100;font-size:12px;width:14px;text-align:center}
.le-req{color:#c0392b;font-weight:800}
.le-row input, .le-row select, .le-row textarea{
    width:100%;box-sizing:border-box;border:1.5px solid #f0e0d0;border-radius:10px;
    padding:11px 13px;font-size:14px;font-family:inherit;transition:border-color .15s ease, box-shadow .15s ease;background:#fffaf7
}
.le-row input:focus, .le-row select:focus, .le-row textarea:focus{
    outline:none;border-color:#e65100;box-shadow:0 0 0 3px rgba(230,81,0,.12);background:#fff
}
.le-grid2{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.le-grid3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px}
.le-hint{font-size:11.5px;color:#a99;margin-top:5px}
.le-error{font-size:11.5px;color:#c0392b;margin-top:5px;display:none}
.le-row.has-error input, .le-row.has-error select, .le-row.has-error textarea{border-color:#c0392b;box-shadow:0 0 0 3px rgba(192,57,43,.10)}
.le-row.has-error .le-error{display:block}

.le-pillgroup{display:flex;gap:10px;flex-wrap:wrap}
.le-pill{position:relative}
.le-pill input{position:absolute;opacity:0;width:0;height:0}
.le-pill span{display:inline-flex;align-items:center;gap:7px;padding:10px 18px;border:1.5px solid #f0e0d0;border-radius:24px;font-size:13px;font-weight:700;color:#4a4a4a;cursor:pointer;transition:all .15s ease;background:#fffaf7}
.le-pill input:checked + span{background:#e65100;border-color:#e65100;color:#fff;box-shadow:0 4px 12px rgba(230,81,0,.28)}
.le-pill span:hover{border-color:#e65100}

.le-dropzone{border:2px dashed #ffcc99;border-radius:14px;background:#fff8f2;padding:26px 16px;text-align:center;cursor:pointer;transition:border-color .15s ease, background .15s ease}
.le-dropzone:hover, .le-dropzone.le-dragover{border-color:#e65100;background:#fff1e0}
.le-dropzone i{font-size:26px;color:#e65100;margin-bottom:8px;display:block}
.le-dropzone-title{font-size:13.5px;font-weight:700;color:#5c2c00}
.le-dropzone-sub{font-size:11.5px;color:#a99;margin-top:4px}
.le-image-previews{display:grid;grid-template-columns:repeat(auto-fill,minmax(96px,1fr));gap:10px;margin-top:14px}
.le-image-thumb{position:relative;border-radius:10px;overflow:hidden;border:1px solid #eee;height:96px;background:#f2f2f2}
.le-image-thumb img{width:100%;height:100%;object-fit:cover;display:block}
.le-image-remove{position:absolute;top:4px;right:4px;background:rgba(0,0,0,.55);color:#fff;border:none;border-radius:50%;width:22px;height:22px;font-size:12px;cursor:pointer;display:flex;align-items:center;justify-content:center}
.le-image-remove:hover{background:#c0392b}
.le-doc-list{display:flex;flex-direction:column;gap:8px;margin-top:12px}
.le-doc-item{display:flex;align-items:center;justify-content:space-between;background:#fff8f2;border:1px solid #f0e0d0;border-radius:10px;padding:9px 12px;font-size:12.5px;color:#5c2c00}
.le-doc-item button{background:none;border:none;color:#c0392b;cursor:pointer;font-size:14px}

.le-translate-box{background:#fff8f2;border:1px solid #f0e0d0;border-radius:12px;padding:12px 14px;margin-top:10px}
.le-translate-row{display:flex;align-items:center;gap:10px;font-size:13px;padding:5px 0}
.le-translate-row .le-tlang{width:74px;flex-shrink:0;font-weight:700;color:#e65100}
.le-translate-row .le-tval{color:#333;flex:1}
.le-translate-loading{font-size:12px;color:#a99;display:none;align-items:center;gap:6px}
.le-translate-loading.active{display:flex}
.le-translate-loading i{animation:le-spin 0.8s linear infinite}
@keyframes le-spin{to{transform:rotate(360deg)}}

.le-terms-row{display:flex;align-items:flex-start;gap:10px;font-size:13px;color:#4a4a4a;margin:18px 0 6px}
.le-terms-row input{width:auto;margin-top:3px}

.le-submit{background:linear-gradient(135deg,#e65100,#c84800);color:#fff;border:none;border-radius:12px;padding:14px 28px;font-weight:700;font-size:15.5px;cursor:pointer;width:100%;margin-top:14px;display:flex;align-items:center;justify-content:center;gap:9px;box-shadow:0 8px 20px rgba(230,81,0,.28);transition:transform .12s ease, box-shadow .12s ease}
.le-submit:hover{transform:translateY(-2px);box-shadow:0 12px 26px rgba(230,81,0,.34)}
.le-submit:active{transform:translateY(0)}
.le-submit:disabled{opacity:.65;cursor:not-allowed;transform:none}

.le-success-box{text-align:center;padding:50px 20px}
.le-success-box i{font-size:46px;color:#e65100;margin-bottom:16px;display:block}
.le-success-box p{font-size:15.5px;color:#5c2c00;font-weight:600;max-width:480px;margin:0 auto}

.le-login-gate{text-align:center;padding:44px 20px}
.le-login-gate i{font-size:36px;color:#e65100;margin-bottom:14px;display:block}
.le-login-gate a{background:linear-gradient(135deg,#e65100,#c84800);color:#fff;padding:13px 30px;border-radius:10px;text-decoration:none;font-weight:700;display:inline-block;margin-top:16px;box-shadow:0 8px 20px rgba(230,81,0,.25)}
@media (max-width:560px){.le-grid2,.le-grid3{grid-template-columns:1fr}.le-hero h1{font-size:24px}.le-card-inner{padding:20px}}
</style>

<div class="le-hero">
  <div class="le-hero-inner">
    <span class="le-hero-icon">🚜</span>
    <h1 id="leTitle">List Your Equipment for Rent</h1>
    <p id="leSub">Own a tractor, harvester, drone, or other farm equipment lying idle? List it and earn from farmers nearby who need it.</p>
    <div class="le-hero-badges">
      <span class="le-hero-badge"><i class="fa-solid fa-shield-halved"></i> <span id="leBadgeReview">Admin reviewed</span></span>
      <span class="le-hero-badge"><i class="fa-solid fa-users"></i> <span id="leBadgeReach">Statewide reach</span></span>
      <span class="le-hero-badge"><i class="fa-solid fa-bolt"></i> <span id="leBadgeFast">Quick listing</span></span>
    </div>
  </div>
</div>

<div class="le-page-bg">
<div class="le-wrap">
  <div class="le-card"><div class="le-card-inner">
    <?php if (!$isLoggedIn): ?>
      <div class="le-login-gate">
        <i class="fa-solid fa-lock"></i>
        <p id="leLoginMsg">You need to be logged in to list your equipment for rent.</p>
        <a href="login.php" id="leLoginBtn">Login to Continue</a>
      </div>
    <?php else: ?>
      <div class="le-earn-box">
        <div class="le-earn-lbl"><i class="fa-solid fa-sack-dollar"></i> <span id="leEarnLbl">You will receive per booking</span></div>
        <div class="le-earn-val">₹<span id="leEarnVal">0</span> <small id="leEarnHint">(after 10% platform fee)</small></div>
      </div>

      <div id="leSuccessBox" class="le-success-box" style="display:none">
        <i class="fa-solid fa-circle-check"></i>
        <p id="leSuccessMsg">Your equipment has been submitted successfully and is waiting for admin approval.</p>
      </div>

      <form method="POST" action="insert_equipment.php" id="leForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="add_equipment" value="1">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <input type="hidden" name="input_language" id="leInputLang" value="auto">

        <!-- ============ EQUIPMENT DETAILS ============ -->
        <div class="le-section">
          <div class="le-section-title"><i class="fa-solid fa-tractor"></i> <span id="leSectionBasic">Equipment Details</span></div>

          <div class="le-row" id="leNameRow">
            <label id="leLblName"><i class="fa-solid fa-tag"></i> <span id="leLblNameText">Equipment Name</span> <span class="le-req">*</span></label>
            <input type="text" name="name" id="leName" required maxlength="150">
            <div class="le-error" id="leNameError">Equipment name is required.</div>
            <div class="le-translate-box">
              <div class="le-translate-loading" id="leTranslateLoading"><i class="fa-solid fa-spinner"></i> <span id="leTranslatingText">Translating…</span></div>
              <div class="le-translate-row"><span class="le-tlang" id="leTLangEnLabel">English</span><span class="le-tval" id="leTPreviewEn">—</span></div>
              <div class="le-translate-row"><span class="le-tlang" id="leTLangMrLabel">Marathi</span><span class="le-tval" id="leTPreviewMr">—</span></div>
              <div class="le-translate-row"><span class="le-tlang" id="leTLangHiLabel">Hindi</span><span class="le-tval" id="leTPreviewHi">—</span></div>
            </div>
          </div>

          <div class="le-row">
            <label id="leLblType"><i class="fa-solid fa-layer-group"></i> Equipment Category <span class="le-req">*</span></label>
            <select name="type" id="leType" required>
              <option value="tractor" id="leOptTractor">Tractor</option>
              <option value="power_tiller" id="leOptPowerTiller">Power Tiller</option>
              <option value="rotavator" id="leOptRotavator">Rotavator</option>
              <option value="cultivator" id="leOptCultivator">Cultivator</option>
              <option value="harvester" id="leOptHarvester">Harvester</option>
              <option value="seed_drill" id="leOptSeedDrill">Seed Drill</option>
              <option value="sprayer" id="leOptSprayer">Sprayer</option>
              <option value="drone" id="leOptDrone">Agricultural Drone</option>
              <option value="thresher" id="leOptThresher">Thresher</option>
              <option value="other" id="leOptOther">Other</option>
            </select>
          </div>

          <div class="le-grid2">
            <div class="le-row">
              <label id="leLblBrand"><i class="fa-solid fa-award"></i> Brand Name</label>
              <input type="text" name="brand" id="leBrand" maxlength="100">
            </div>
            <div class="le-row">
              <label id="leLblModel"><i class="fa-solid fa-hashtag"></i> Model Name</label>
              <input type="text" name="model" id="leModel" maxlength="100">
            </div>
          </div>
          <div class="le-grid2">
            <div class="le-row">
              <label id="leLblYear"><i class="fa-solid fa-calendar"></i> Manufacturing Year</label>
              <input type="number" name="manufacturing_year" id="leYear" min="1980" max="<?php echo date('Y'); ?>" placeholder="<?php echo date('Y'); ?>">
              <div class="le-error" id="leYearError">Enter a valid year.</div>
            </div>
            <div class="le-row">
              <label id="leLblHp"><i class="fa-solid fa-gauge-high"></i> Horsepower / Capacity</label>
              <input type="text" name="hp" id="leHp" placeholder="e.g. 45 HP">
            </div>
          </div>

          <div class="le-row">
            <label id="leLblCondition"><i class="fa-solid fa-tags"></i> Equipment Condition <span class="le-req">*</span></label>
            <div class="le-pillgroup">
              <label class="le-pill"><input type="radio" name="equipment_condition" value="excellent" checked><span id="leCondExcellent">Excellent</span></label>
              <label class="le-pill"><input type="radio" name="equipment_condition" value="good"><span id="leCondGood">Good</span></label>
              <label class="le-pill"><input type="radio" name="equipment_condition" value="average"><span id="leCondAverage">Average</span></label>
            </div>
          </div>

          <div class="le-row">
            <label id="leLblDesc"><i class="fa-solid fa-align-left"></i> Equipment Description</label>
            <textarea name="description" id="leDesc" rows="3" maxlength="1000"></textarea>
          </div>

          <div class="le-row">
            <label id="leLblImages"><i class="fa-solid fa-images"></i> Equipment Images <span class="le-req">*</span></label>
            <div class="le-dropzone" id="leDropzone">
              <i class="fa-solid fa-cloud-arrow-up"></i>
              <div class="le-dropzone-title" id="leDropzoneTitle">Drag &amp; drop images here, or click to browse</div>
              <div class="le-dropzone-sub" id="leDropzoneSub">JPG, JPEG, PNG or WEBP · up to 5 MB each · up to 6 images</div>
              <input type="file" name="images[]" id="leImages" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple style="display:none">
            </div>
            <div class="le-image-previews" id="leImagePreviews"></div>
            <div class="le-error" id="leImagesError">Please add at least one equipment image.</div>
          </div>
        </div>

        <!-- ============ RENTAL DETAILS ============ -->
        <div class="le-section">
          <div class="le-section-title"><i class="fa-solid fa-indian-rupee-sign"></i> <span id="leSectionPricing">Rental Details</span></div>

          <div class="le-row">
            <label id="leLblRentType"><i class="fa-solid fa-clock"></i> Rental Type <span class="le-req">*</span></label>
            <div class="le-pillgroup">
              <label class="le-pill"><input type="radio" name="rent_type" value="hour" onchange="leUpdateEarnings()"><span id="leRentHour">Per Hour</span></label>
              <label class="le-pill"><input type="radio" name="rent_type" value="day" checked onchange="leUpdateEarnings()"><span id="leRentDay">Per Day</span></label>
              <label class="le-pill"><input type="radio" name="rent_type" value="acre" onchange="leUpdateEarnings()"><span id="leRentAcre">Per Acre</span></label>
            </div>
          </div>
          <div class="le-grid2">
            <div class="le-row">
              <label id="leLblRent"><i class="fa-solid fa-indian-rupee-sign"></i> <span id="leLblRentText">Rental Price (₹)</span> <span class="le-req">*</span></label>
              <input type="number" name="rent_price" id="leRent" min="1" step="0.01" required oninput="leUpdateEarnings()">
              <div class="le-error" id="leRentError">Enter a valid rental price.</div>
            </div>
            <div class="le-row">
              <label id="leLblDeposit"><i class="fa-solid fa-shield-halved"></i> Security Deposit (₹)</label>
              <input type="number" name="security_deposit" id="leDeposit" min="0" step="0.01" value="0">
            </div>
          </div>
          <div class="le-row">
            <label id="leLblMinDuration"><i class="fa-solid fa-hourglass-half"></i> Minimum Rental Duration</label>
            <input type="text" name="min_rental_duration" id="leMinDuration" placeholder="e.g. 2 hours / 1 day">
          </div>

          <div class="le-grid3">
            <div class="le-row">
              <label id="leLblOperator"><i class="fa-solid fa-user-gear"></i> Operator Available</label>
              <div class="le-pillgroup">
                <label class="le-pill"><input type="radio" name="operator_available" value="yes"><span id="leOperatorYes">Yes</span></label>
                <label class="le-pill"><input type="radio" name="operator_available" value="no" checked><span id="leOperatorNo">No</span></label>
              </div>
            </div>
            <div class="le-row">
              <label id="leLblFuel"><i class="fa-solid fa-gas-pump"></i> Fuel Included</label>
              <div class="le-pillgroup">
                <label class="le-pill"><input type="radio" name="fuel_included" value="yes"><span id="leFuelYes">Yes</span></label>
                <label class="le-pill"><input type="radio" name="fuel_included" value="no" checked><span id="leFuelNo">No</span></label>
              </div>
            </div>
            <div class="le-row">
              <label id="leLblTransport"><i class="fa-solid fa-truck"></i> Transport Available</label>
              <div class="le-pillgroup">
                <label class="le-pill"><input type="radio" name="transport_available" value="yes" onchange="leToggleTransportCharge(true)"><span id="leTransportYes">Yes</span></label>
                <label class="le-pill"><input type="radio" name="transport_available" value="no" checked onchange="leToggleTransportCharge(false)"><span id="leTransportNo">No</span></label>
              </div>
            </div>
          </div>
          <div class="le-row" id="leTransportChargeRow" style="display:none">
            <label id="leLblTransportCharge"><i class="fa-solid fa-truck-fast"></i> Additional Transport Charge (₹)</label>
            <input type="number" name="transport_charge" id="leTransportCharge" min="0" step="0.01" value="0">
          </div>
        </div>

        <!-- ============ AVAILABILITY ============ -->
        <div class="le-section">
          <div class="le-section-title"><i class="fa-solid fa-calendar-days"></i> <span id="leSectionAvailability">Availability</span></div>
          <div class="le-grid2">
            <div class="le-row">
              <label id="leLblFrom"><i class="fa-solid fa-calendar"></i> Available From</label>
              <input type="date" name="available_from" id="leAvailFrom">
            </div>
            <div class="le-row">
              <label id="leLblTo"><i class="fa-solid fa-calendar"></i> Available To</label>
              <input type="date" name="available_to" id="leAvailTo">
              <div class="le-error" id="leAvailToError">Available To date must be after Available From.</div>
            </div>
          </div>
          <div class="le-row">
            <label id="leLblDays"><i class="fa-solid fa-calendar-week"></i> Available Days</label>
            <div class="le-pillgroup" id="leDaysGroup">
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Mon" checked><span id="leDayMon">Mon</span></label>
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Tue" checked><span id="leDayTue">Tue</span></label>
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Wed" checked><span id="leDayWed">Wed</span></label>
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Thu" checked><span id="leDayThu">Thu</span></label>
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Fri" checked><span id="leDayFri">Fri</span></label>
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Sat" checked><span id="leDaySat">Sat</span></label>
              <label class="le-pill"><input type="checkbox" name="available_days[]" value="Sun"><span id="leDaySun">Sun</span></label>
            </div>
          </div>
          <div class="le-row">
            <label id="leLblNotice"><i class="fa-solid fa-bell"></i> Booking Notice Period</label>
            <input type="text" name="booking_notice_period" id="leNotice" placeholder="e.g. 24 hours">
          </div>
        </div>

        <!-- ============ OWNER DETAILS ============ -->
        <div class="le-section">
          <div class="le-section-title"><i class="fa-solid fa-user"></i> <span id="leSectionOwner">Owner Details</span></div>
          <div class="le-grid2">
            <div class="le-row">
              <label id="leLblOwnerName"><i class="fa-solid fa-user"></i> Owner Name <span class="le-req">*</span></label>
              <input type="text" name="owner_name" id="leOwnerName" required value="<?php echo htmlspecialchars($prefillName); ?>">
            </div>
            <div class="le-row">
              <label id="leLblPhone"><i class="fa-solid fa-phone"></i> Mobile Number <span class="le-req">*</span></label>
              <input type="tel" name="owner_phone" id="lePhone" required maxlength="10" value="<?php echo htmlspecialchars($prefillPhone); ?>">
              <div class="le-error" id="lePhoneError">Enter a valid 10-digit mobile number.</div>
            </div>
          </div>
          <div class="le-row">
            <label id="leLblEmail"><i class="fa-solid fa-envelope"></i> Email Address</label>
            <input type="email" name="owner_email" id="leEmail">
            <div class="le-error" id="leEmailError">Enter a valid email address.</div>
          </div>
          <div class="le-grid2">
            <div class="le-row">
              <label id="leLblVillage"><i class="fa-solid fa-location-dot"></i> Village or City <span class="le-req">*</span></label>
              <input type="text" name="city" id="leCity" required>
            </div>
            <div class="le-row">
              <label id="leLblDistrict"><i class="fa-solid fa-map"></i> District <span class="le-req">*</span></label>
              <input type="text" name="owner_district" id="leDistrict" required>
            </div>
          </div>
          <div class="le-row">
            <label id="leLblAddress"><i class="fa-solid fa-house"></i> Full Equipment Location <span class="le-req">*</span></label>
            <textarea name="owner_address" id="leAddress" rows="2" required></textarea>
          </div>
        </div>

        <!-- ============ DOCUMENTS AND RULES ============ -->
        <div class="le-section">
          <div class="le-section-title"><i class="fa-solid fa-file-lines"></i> <span id="leSectionDocs">Documents and Rules</span></div>
          <div class="le-row">
            <label id="leLblDocs"><i class="fa-solid fa-file-arrow-up"></i> Equipment Documents (RC, insurance, etc.)</label>
            <div class="le-dropzone" id="leDocDropzone">
              <i class="fa-solid fa-file-circle-plus"></i>
              <div class="le-dropzone-title" id="leDocDropzoneTitle">Drag &amp; drop documents here, or click to browse</div>
              <div class="le-dropzone-sub" id="leDocDropzoneSub">PDF, JPG, JPEG or PNG · up to 8 MB each · up to 4 files</div>
              <input type="file" name="documents[]" id="leDocs" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" multiple style="display:none">
            </div>
            <div class="le-doc-list" id="leDocList"></div>
          </div>
          <div class="le-row">
            <label id="leLblRules"><i class="fa-solid fa-scale-balanced"></i> Rental Rules and Conditions</label>
            <textarea name="rental_rules" id="leRules" rows="3" placeholder="e.g. Fuel to be refilled before return, no night-time use, etc."></textarea>
          </div>
        </div>

        <label class="le-terms-row">
          <input type="checkbox" name="terms" id="leTerms" required>
          <span id="leTermsLabel">I agree to AgriCart's Terms and Conditions for renting out equipment.</span>
        </label>
        <div class="le-error" id="leTermsError">You must accept the Terms and Conditions.</div>

        <button type="submit" class="le-submit" id="leSubmitButton"><i class="fa-solid fa-upload"></i> <span id="leSubmitBtn">Submit Equipment</span></button>
      </form>
    <?php endif; ?>
  </div></div>
</div>
</div>

<script>
const AGRI_EQUIPMENT_COMMISSION_PERCENT = <?php echo AGRI_EQUIPMENT_COMMISSION_PERCENT; ?>;
const AGRI_EQUIPMENT_MAX_IMAGE_MB = <?php echo AGRI_EQUIPMENT_MAX_IMAGE_MB; ?>;
const AGRI_EQUIPMENT_MAX_IMAGES = <?php echo AGRI_EQUIPMENT_MAX_IMAGES; ?>;
const AGRI_EQUIPMENT_MAX_DOC_MB = <?php echo AGRI_EQUIPMENT_MAX_DOC_MB; ?>;
const AGRI_EQUIPMENT_MAX_DOCS = <?php echo AGRI_EQUIPMENT_MAX_DOCS; ?>;
const AGRI_ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
const AGRI_ALLOWED_DOC_TYPES = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;

const ListEquipmentT = {
    en: {
        title:"List Your Equipment for Rent",
        sub:"Own a tractor, harvester, drone, or other farm equipment lying idle? List it and earn from farmers nearby who need it.",
        badgeReview:"Admin reviewed", badgeReach:"Statewide reach", badgeFast:"Quick listing",
        loginMsg:"You need to be logged in to list your equipment for rent.",
        loginBtn:"Login to Continue",
        earnLbl:"You will receive per booking", earnHint:`(after ${AGRI_EQUIPMENT_COMMISSION_PERCENT}% platform fee)`,
        sectionBasic:"Equipment Details", sectionPricing:"Rental Details", sectionAvailability:"Availability",
        sectionOwner:"Owner Details", sectionDocs:"Documents and Rules",
        lblNameText:"Equipment Name", namePh:"e.g. Mahindra Tractor", nameRequired:"Equipment name is required.",
        tLangEn:"English", tLangMr:"Marathi", tLangHi:"Hindi", translating:"Translating…",
        lblType:"Equipment Category",
        optTractor:"Tractor", optPowerTiller:"Power Tiller", optRotavator:"Rotavator", optCultivator:"Cultivator",
        optHarvester:"Harvester", optSeedDrill:"Seed Drill", optSprayer:"Sprayer", optDrone:"Agricultural Drone",
        optThresher:"Thresher", optOther:"Other",
        lblBrand:"Brand Name", lblModel:"Model Name",
        lblYear:"Manufacturing Year", yearInvalid:"Enter a valid year.",
        lblHp:"Horsepower / Capacity",
        lblCondition:"Equipment Condition", condExcellent:"Excellent", condGood:"Good", condAverage:"Average",
        lblDesc:"Equipment Description", descPh:"Condition, implements included, maintenance history, etc.",
        lblImages:"Equipment Images", dropzoneTitle:"Drag & drop images here, or click to browse",
        dropzoneSub:`JPG, JPEG, PNG or WEBP · up to ${AGRI_EQUIPMENT_MAX_IMAGE_MB} MB each · up to ${AGRI_EQUIPMENT_MAX_IMAGES} images`,
        imagesRequired:"Please add at least one equipment image.",
        imageTooLarge:"is larger than " + AGRI_EQUIPMENT_MAX_IMAGE_MB + " MB and was skipped.",
        imageBadType:"is not a supported format (JPG, JPEG, PNG, WEBP) and was skipped.",
        imageTooMany:"You can upload up to " + AGRI_EQUIPMENT_MAX_IMAGES + " images.",
        lblRentType:"Rental Type", rentHour:"Per Hour", rentDay:"Per Day", rentAcre:"Per Acre",
        lblRentText:"Rental Price (₹)", rentRequired:"Enter a valid rental price.",
        lblDeposit:"Security Deposit (₹)", lblMinDuration:"Minimum Rental Duration", minDurationPh:"e.g. 2 hours / 1 day",
        lblOperator:"Operator Available", operatorYes:"Yes", operatorNo:"No",
        lblFuel:"Fuel Included", fuelYes:"Yes", fuelNo:"No",
        lblTransport:"Transport Available", transportYes:"Yes", transportNo:"No",
        lblTransportCharge:"Additional Transport Charge (₹)",
        lblFrom:"Available From", lblTo:"Available To", availToError:"Available To date must be after Available From.",
        lblDays:"Available Days", dayMon:"Mon", dayTue:"Tue", dayWed:"Wed", dayThu:"Thu", dayFri:"Fri", daySat:"Sat", daySun:"Sun",
        lblNotice:"Booking Notice Period", noticePh:"e.g. 24 hours",
        lblOwnerName:"Owner Name",
        lblPhone:"Mobile Number", phoneRequired:"Enter a valid 10-digit mobile number.",
        lblEmail:"Email Address", emailInvalid:"Enter a valid email address.",
        lblVillage:"Village or City", lblDistrict:"District", lblAddress:"Full Equipment Location",
        lblDocs:"Equipment Documents (RC, insurance, etc.)", docDropzoneTitle:"Drag & drop documents here, or click to browse",
        docDropzoneSub:`PDF, JPG, JPEG or PNG · up to ${AGRI_EQUIPMENT_MAX_DOC_MB} MB each · up to ${AGRI_EQUIPMENT_MAX_DOCS} files`,
        docTooLarge:"is larger than " + AGRI_EQUIPMENT_MAX_DOC_MB + " MB and was skipped.",
        docBadType:"is not a supported format (PDF, JPG, JPEG, PNG) and was skipped.",
        docTooMany:"You can upload up to " + AGRI_EQUIPMENT_MAX_DOCS + " documents.",
        lblRules:"Rental Rules and Conditions", rulesPh:"e.g. Fuel to be refilled before return, no night-time use, etc.",
        termsLabel:"I agree to AgriCart's Terms and Conditions for renting out equipment.",
        termsRequired:"You must accept the Terms and Conditions.",
        submitBtn:"Submit Equipment", submitting:"Submitting…",
        successMsg:"Your equipment has been submitted successfully and is waiting for admin approval.",
        genericError:"Something went wrong. Please check the form and try again.",
        networkError:"Network error, please try again."
    },
    mr: {
        title:"तुमचे अवजार भाड्याने द्या",
        sub:"ट्रॅक्टर, हार्वेस्टर, ड्रोन किंवा इतर शेती अवजार रिकामे पडून आहे का? ते list करा आणि आसपासच्या शेतकऱ्यांकडून कमवा.",
        badgeReview:"अ‍ॅडमिन तपासणी", badgeReach:"राज्यभर पोहोच", badgeFast:"झटपट लिस्टिंग",
        loginMsg:"अवजार भाड्याने list करण्यासाठी login करणे आवश्यक आहे.",
        loginBtn:"पुढे जाण्यासाठी Login करा",
        earnLbl:"प्रति बुकिंग तुम्हाला मिळतील", earnHint:`(${AGRI_EQUIPMENT_COMMISSION_PERCENT}% प्लॅटफॉर्म फी नंतर)`,
        sectionBasic:"अवजाराची माहिती", sectionPricing:"भाड्याची माहिती", sectionAvailability:"उपलब्धता",
        sectionOwner:"मालकाची माहिती", sectionDocs:"कागदपत्रे आणि नियम",
        lblNameText:"अवजाराचे नाव", namePh:"उदा. महिंद्रा ट्रॅक्टर", nameRequired:"अवजाराचे नाव आवश्यक आहे.",
        tLangEn:"इंग्रजी", tLangMr:"मराठी", tLangHi:"हिंदी", translating:"भाषांतर सुरू आहे…",
        lblType:"अवजार श्रेणी",
        optTractor:"ट्रॅक्टर", optPowerTiller:"पॉवर टिलर", optRotavator:"रोटावेटर", optCultivator:"कल्टिव्हेटर",
        optHarvester:"हार्वेस्टर", optSeedDrill:"सीड ड्रिल", optSprayer:"स्प्रेअर", optDrone:"कृषी ड्रोन",
        optThresher:"थ्रेशर", optOther:"इतर",
        lblBrand:"ब्रँड नाव", lblModel:"मॉडेल नाव",
        lblYear:"उत्पादन वर्ष", yearInvalid:"वैध वर्ष टाका.",
        lblHp:"हॉर्सपॉवर / क्षमता",
        lblCondition:"अवजाराची स्थिती", condExcellent:"उत्कृष्ट", condGood:"चांगली", condAverage:"सर्वसाधारण",
        lblDesc:"अवजाराचे वर्णन", descPh:"स्थिती, समाविष्ट उपकरणे, देखभाल इतिहास, इ.",
        lblImages:"अवजाराचे फोटो", dropzoneTitle:"फोटो इथे ड्रॅग करा, किंवा क्लिक करून निवडा",
        dropzoneSub:`JPG, JPEG, PNG किंवा WEBP · प्रत्येकी ${AGRI_EQUIPMENT_MAX_IMAGE_MB} MB पर्यंत · जास्तीत जास्त ${AGRI_EQUIPMENT_MAX_IMAGES} फोटो`,
        imagesRequired:"कृपया किमान एक अवजार फोटो जोडा.",
        imageTooLarge:"" + AGRI_EQUIPMENT_MAX_IMAGE_MB + " MB पेक्षा मोठा आहे म्हणून वगळले.",
        imageBadType:"समर्थित फॉरमॅट नाही म्हणून वगळले.",
        imageTooMany:"तुम्ही जास्तीत जास्त " + AGRI_EQUIPMENT_MAX_IMAGES + " फोटो अपलोड करू शकता.",
        lblRentType:"भाडे प्रकार", rentHour:"प्रति तास", rentDay:"प्रति दिवस", rentAcre:"प्रति एकर",
        lblRentText:"भाड्याची किंमत (₹)", rentRequired:"वैध भाडे किंमत टाका.",
        lblDeposit:"सुरक्षा ठेव (₹)", lblMinDuration:"किमान भाडे कालावधी", minDurationPh:"उदा. 2 तास / 1 दिवस",
        lblOperator:"ऑपरेटर उपलब्ध", operatorYes:"होय", operatorNo:"नाही",
        lblFuel:"इंधन समाविष्ट", fuelYes:"होय", fuelNo:"नाही",
        lblTransport:"वाहतूक उपलब्ध", transportYes:"होय", transportNo:"नाही",
        lblTransportCharge:"अतिरिक्त वाहतूक शुल्क (₹)",
        lblFrom:"पासून उपलब्ध", lblTo:"पर्यंत उपलब्ध", availToError:"'पर्यंत' तारीख 'पासून' तारखेनंतर असावी.",
        lblDays:"उपलब्ध दिवस", dayMon:"सोम", dayTue:"मंगळ", dayWed:"बुध", dayThu:"गुरु", dayFri:"शुक्र", daySat:"शनि", daySun:"रवि",
        lblNotice:"बुकिंग सूचना कालावधी", noticePh:"उदा. 24 तास",
        lblOwnerName:"मालकाचे नाव",
        lblPhone:"मोबाईल नंबर", phoneRequired:"वैध 10-अंकी मोबाईल नंबर टाका.",
        lblEmail:"ईमेल पत्ता", emailInvalid:"वैध ईमेल पत्ता टाका.",
        lblVillage:"गाव किंवा शहर", lblDistrict:"जिल्हा", lblAddress:"अवजाराचे संपूर्ण ठिकाण",
        lblDocs:"अवजाराची कागदपत्रे (RC, विमा, इ.)", docDropzoneTitle:"कागदपत्रे इथे ड्रॅग करा, किंवा क्लिक करून निवडा",
        docDropzoneSub:`PDF, JPG, JPEG किंवा PNG · प्रत्येकी ${AGRI_EQUIPMENT_MAX_DOC_MB} MB पर्यंत · जास्तीत जास्त ${AGRI_EQUIPMENT_MAX_DOCS} फाईल्स`,
        docTooLarge:"" + AGRI_EQUIPMENT_MAX_DOC_MB + " MB पेक्षा मोठी आहे म्हणून वगळली.",
        docBadType:"समर्थित फॉरमॅट नाही म्हणून वगळली.",
        docTooMany:"तुम्ही जास्तीत जास्त " + AGRI_EQUIPMENT_MAX_DOCS + " फाईल्स अपलोड करू शकता.",
        lblRules:"भाड्याचे नियम व अटी", rulesPh:"उदा. परत करण्यापूर्वी इंधन भरावे, रात्री वापर नाही, इ.",
        termsLabel:"मी AgriCart च्या अवजार भाड्याने देण्याच्या नियम व अटी मान्य करतो/करते.",
        termsRequired:"तुम्हाला नियम व अटी मान्य कराव्या लागतील.",
        submitBtn:"अवजार सबमिट करा", submitting:"सबमिट होत आहे…",
        successMsg:"तुमचे अवजार यशस्वीरित्या सबमिट झाले आहे आणि अ‍ॅडमिनच्या मंजुरीची वाट पाहत आहे.",
        genericError:"काहीतरी चुकले. कृपया फॉर्म तपासा आणि पुन्हा प्रयत्न करा.",
        networkError:"नेटवर्क एरर, कृपया पुन्हा प्रयत्न करा."
    },
    hi: {
        title:"अपने उपकरण किराए पर दें",
        sub:"ट्रैक्टर, हार्वेस्टर, ड्रोन या अन्य खेती उपकरण खाली पड़ा है? इसे लिस्ट करें और आसपास के किसानों से कमाएं।",
        badgeReview:"एडमिन समीक्षा", badgeReach:"राज्यभर पहुंच", badgeFast:"त्वरित लिस्टिंग",
        loginMsg:"उपकरण किराए पर लिस्ट करने के लिए login करना ज़रूरी है।",
        loginBtn:"जारी रखने के लिए Login करें",
        earnLbl:"प्रति बुकिंग आपको मिलेंगे", earnHint:`(${AGRI_EQUIPMENT_COMMISSION_PERCENT}% प्लेटफ़ॉर्म शुल्क के बाद)`,
        sectionBasic:"उपकरण विवरण", sectionPricing:"किराया विवरण", sectionAvailability:"उपलब्धता",
        sectionOwner:"मालिक विवरण", sectionDocs:"दस्तावेज़ और नियम",
        lblNameText:"उपकरण का नाम", namePh:"जैसे महिंद्रा ट्रैक्टर", nameRequired:"उपकरण का नाम आवश्यक है।",
        tLangEn:"अंग्रेज़ी", tLangMr:"मराठी", tLangHi:"हिंदी", translating:"अनुवाद हो रहा है…",
        lblType:"उपकरण श्रेणी",
        optTractor:"ट्रैक्टर", optPowerTiller:"पावर टिलर", optRotavator:"रोटावेटर", optCultivator:"कल्टीवेटर",
        optHarvester:"हार्वेस्टर", optSeedDrill:"सीड ड्रिल", optSprayer:"स्प्रेयर", optDrone:"कृषि ड्रोन",
        optThresher:"थ्रेशर", optOther:"अन्य",
        lblBrand:"ब्रांड नाम", lblModel:"मॉडल नाम",
        lblYear:"निर्माण वर्ष", yearInvalid:"मान्य वर्ष दर्ज करें।",
        lblHp:"हॉर्सपावर / क्षमता",
        lblCondition:"उपकरण की स्थिति", condExcellent:"उत्कृष्ट", condGood:"अच्छी", condAverage:"सामान्य",
        lblDesc:"उपकरण विवरण", descPh:"स्थिति, शामिल उपकरण, रखरखाव इतिहास, आदि।",
        lblImages:"उपकरण की तस्वीरें", dropzoneTitle:"तस्वीरें यहां खींचें और छोड़ें, या क्लिक करके चुनें",
        dropzoneSub:`JPG, JPEG, PNG या WEBP · प्रत्येक ${AGRI_EQUIPMENT_MAX_IMAGE_MB} MB तक · अधिकतम ${AGRI_EQUIPMENT_MAX_IMAGES} तस्वीरें`,
        imagesRequired:"कृपया कम से कम एक उपकरण तस्वीर जोड़ें।",
        imageTooLarge:"" + AGRI_EQUIPMENT_MAX_IMAGE_MB + " MB से बड़ी है इसलिए छोड़ी गई।",
        imageBadType:"समर्थित फॉर्मेट नहीं है इसलिए छोड़ी गई।",
        imageTooMany:"आप अधिकतम " + AGRI_EQUIPMENT_MAX_IMAGES + " तस्वीरें अपलोड कर सकते हैं।",
        lblRentType:"किराया प्रकार", rentHour:"प्रति घंटा", rentDay:"प्रति दिन", rentAcre:"प्रति एकड़",
        lblRentText:"किराया कीमत (₹)", rentRequired:"मान्य किराया कीमत दर्ज करें।",
        lblDeposit:"सुरक्षा जमा (₹)", lblMinDuration:"न्यूनतम किराया अवधि", minDurationPh:"जैसे 2 घंटे / 1 दिन",
        lblOperator:"ऑपरेटर उपलब्ध", operatorYes:"हां", operatorNo:"नहीं",
        lblFuel:"ईंधन शामिल", fuelYes:"हां", fuelNo:"नहीं",
        lblTransport:"परिवहन उपलब्ध", transportYes:"हां", transportNo:"नहीं",
        lblTransportCharge:"अतिरिक्त परिवहन शुल्क (₹)",
        lblFrom:"से उपलब्ध", lblTo:"तक उपलब्ध", availToError:"'तक' तारीख 'से' तारीख के बाद होनी चाहिए।",
        lblDays:"उपलब्ध दिन", dayMon:"सोम", dayTue:"मंगल", dayWed:"बुध", dayThu:"गुरु", dayFri:"शुक्र", daySat:"शनि", daySun:"रवि",
        lblNotice:"बुकिंग सूचना अवधि", noticePh:"जैसे 24 घंटे",
        lblOwnerName:"मालिक का नाम",
        lblPhone:"मोबाइल नंबर", phoneRequired:"मान्य 10-अंकों का मोबाइल नंबर दर्ज करें।",
        lblEmail:"ईमेल पता", emailInvalid:"मान्य ईमेल पता दर्ज करें।",
        lblVillage:"गांव या शहर", lblDistrict:"ज़िला", lblAddress:"उपकरण का पूरा स्थान",
        lblDocs:"उपकरण दस्तावेज़ (RC, बीमा, आदि)", docDropzoneTitle:"दस्तावेज़ यहां खींचें और छोड़ें, या क्लिक करके चुनें",
        docDropzoneSub:`PDF, JPG, JPEG या PNG · प्रत्येक ${AGRI_EQUIPMENT_MAX_DOC_MB} MB तक · अधिकतम ${AGRI_EQUIPMENT_MAX_DOCS} फ़ाइलें`,
        docTooLarge:"" + AGRI_EQUIPMENT_MAX_DOC_MB + " MB से बड़ी है इसलिए छोड़ी गई।",
        docBadType:"समर्थित फॉर्मेट नहीं है इसलिए छोड़ी गई।",
        docTooMany:"आप अधिकतम " + AGRI_EQUIPMENT_MAX_DOCS + " फ़ाइलें अपलोड कर सकते हैं।",
        lblRules:"किराये के नियम और शर्तें", rulesPh:"जैसे वापसी से पहले ईंधन भरें, रात में उपयोग नहीं, आदि।",
        termsLabel:"मैं उपकरण किराए पर देने हेतु AgriCart के नियम और शर्तों से सहमत हूं।",
        termsRequired:"आपको नियम और शर्तें स्वीकार करनी होंगी।",
        submitBtn:"उपकरण सबमिट करें", submitting:"सबमिट हो रहा है…",
        successMsg:"आपका उपकरण सफलतापूर्वक सबमिट हो गया है और एडमिन की मंजूरी का इंतज़ार कर रहा है।",
        genericError:"कुछ गलत हो गया। कृपया फॉर्म जांचें और पुनः प्रयास करें।",
        networkError:"नेटवर्क त्रुटि, कृपया पुनः प्रयास करें।"
    }
};

function leSetText(id, val){ const el = document.getElementById(id); if (el) el.textContent = val; }
function leSetPh(id, val){ const el = document.getElementById(id); if (el) el.placeholder = val; }
function leCurrentT(){ return ListEquipmentT[window.lang || 'en']; }

function leUpdateEarnings(){
    const rentEl = document.getElementById('leRent');
    const rent = parseFloat(rentEl ? rentEl.value : 0) || 0;
    const net = rent - (rent * AGRI_EQUIPMENT_COMMISSION_PERCENT / 100);
    leSetText('leEarnVal', net > 0 ? net.toFixed(2) : '0');
}

function leToggleTransportCharge(show){
    document.getElementById('leTransportChargeRow').style.display = show ? '' : 'none';
}

/* ---------------- IMAGE UPLOAD ---------------- */
let leSelectedFiles = [];
function leRenderImagePreviews(){
    const wrap = document.getElementById('leImagePreviews');
    wrap.innerHTML = '';
    leSelectedFiles.forEach((file, idx) => {
        const url = URL.createObjectURL(file);
        const thumb = document.createElement('div');
        thumb.className = 'le-image-thumb';
        thumb.innerHTML = `<img src="${url}" alt=""><button type="button" class="le-image-remove" data-idx="${idx}"><i class="fa-solid fa-xmark"></i></button>`;
        wrap.appendChild(thumb);
    });
    wrap.querySelectorAll('.le-image-remove').forEach(btn => {
        btn.addEventListener('click', () => {
            leSelectedFiles.splice(parseInt(btn.dataset.idx, 10), 1);
            leRenderImagePreviews();
            leSyncFileInput();
        });
    });
}
function leSyncFileInput(){
    const dt = new DataTransfer();
    leSelectedFiles.forEach(f => dt.items.add(f));
    document.getElementById('leImages').files = dt.files;
}
function leAddFiles(fileList){
    const pt = leCurrentT();
    const incoming = Array.from(fileList || []);
    for (const file of incoming) {
        if (leSelectedFiles.length >= AGRI_EQUIPMENT_MAX_IMAGES) { showLeToast(pt.imageTooMany); break; }
        if (!AGRI_ALLOWED_IMAGE_TYPES.includes(file.type)) { showLeToast(file.name + ' ' + pt.imageBadType); continue; }
        if (file.size > AGRI_EQUIPMENT_MAX_IMAGE_MB * 1024 * 1024) { showLeToast(file.name + ' ' + pt.imageTooLarge); continue; }
        leSelectedFiles.push(file);
    }
    leRenderImagePreviews();
    leSyncFileInput();
    leClearFieldError('leImages');
}

/* ---------------- DOCUMENT UPLOAD ---------------- */
let leSelectedDocs = [];
function leRenderDocList(){
    const wrap = document.getElementById('leDocList');
    wrap.innerHTML = leSelectedDocs.map((file, idx) =>
        `<div class="le-doc-item"><span><i class="fa-solid fa-file"></i> ${file.name}</span><button type="button" data-idx="${idx}"><i class="fa-solid fa-xmark"></i></button></div>`
    ).join('');
    wrap.querySelectorAll('button[data-idx]').forEach(btn => {
        btn.addEventListener('click', () => {
            leSelectedDocs.splice(parseInt(btn.dataset.idx, 10), 1);
            leRenderDocList();
            leSyncDocInput();
        });
    });
}
function leSyncDocInput(){
    const dt = new DataTransfer();
    leSelectedDocs.forEach(f => dt.items.add(f));
    document.getElementById('leDocs').files = dt.files;
}
function leAddDocs(fileList){
    const pt = leCurrentT();
    const incoming = Array.from(fileList || []);
    for (const file of incoming) {
        if (leSelectedDocs.length >= AGRI_EQUIPMENT_MAX_DOCS) { showLeToast(pt.docTooMany); break; }
        if (!AGRI_ALLOWED_DOC_TYPES.includes(file.type)) { showLeToast(file.name + ' ' + pt.docBadType); continue; }
        if (file.size > AGRI_EQUIPMENT_MAX_DOC_MB * 1024 * 1024) { showLeToast(file.name + ' ' + pt.docTooLarge); continue; }
        leSelectedDocs.push(file);
    }
    leRenderDocList();
    leSyncDocInput();
}

function showLeToast(msg){
    if (typeof showToast === 'function') { showToast(msg); return; }
    console.warn(msg);
}

/* ---------------- LIVE TRANSLATION PREVIEW (debounced, reuses the same
   endpoint the Sell Product form uses) ---------------- */
let leTranslateTimer = null;
function leScheduleTranslatePreview(){
    clearTimeout(leTranslateTimer);
    const name = document.getElementById('leName').value.trim();
    if (!name) {
        leSetText('leTPreviewEn', '—'); leSetText('leTPreviewMr', '—'); leSetText('leTPreviewHi', '—');
        return;
    }
    leTranslateTimer = setTimeout(leRunTranslatePreview, 500);
}
function leRunTranslatePreview(){
    const name = document.getElementById('leName').value.trim();
    if (!name) return;
    const inputLang = document.getElementById('leInputLang').value;
    document.getElementById('leTranslateLoading').classList.add('active');
    fetch('translate_preview.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ name, input_language: inputLang, csrf_token: CSRF_TOKEN })
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('leTranslateLoading').classList.remove('active');
        if (data && data.success) {
            leSetText('leTPreviewEn', data.en || name);
            leSetText('leTPreviewMr', data.mr || name);
            leSetText('leTPreviewHi', data.hi || name);
        }
    })
    .catch(() => { document.getElementById('leTranslateLoading').classList.remove('active'); });
}

/* ---------------- VALIDATION ---------------- */
function leClearFieldError(fieldId){
    const el = document.getElementById(fieldId);
    const row = el.closest('.le-row');
    if (row) row.classList.remove('has-error');
}
function leShowFieldError(fieldId){
    const el = document.getElementById(fieldId);
    const row = el.closest('.le-row');
    if (row) row.classList.add('has-error');
}

function leValidateForm(){
    let ok = true;
    const required = ['leName','leType','leRent','leOwnerName','lePhone','leCity','leDistrict','leAddress'];
    required.forEach(id => leClearFieldError(id));
    document.getElementById('leImagesError').closest('.le-row').classList.remove('has-error');
    document.getElementById('leTermsError').style.display = 'none';
    document.getElementById('leAvailToError').style.display = 'none';
    document.getElementById('leYearError').style.display = 'none';

    required.forEach(id => {
        const el = document.getElementById(id);
        if (!el.value || !el.value.trim()) { leShowFieldError(id); ok = false; }
    });

    const rent = parseFloat(document.getElementById('leRent').value);
    if (isNaN(rent) || rent <= 0) { leShowFieldError('leRent'); ok = false; }

    const phone = document.getElementById('lePhone').value.trim();
    if (!/^[0-9]{10}$/.test(phone)) { leShowFieldError('lePhone'); ok = false; }

    const email = document.getElementById('leEmail').value.trim();
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { leShowFieldError('leEmail'); ok = false; }

    const yearVal = document.getElementById('leYear').value;
    if (yearVal) {
        const year = parseInt(yearVal, 10);
        const currentYear = new Date().getFullYear();
        if (isNaN(year) || year < 1980 || year > currentYear) {
            document.getElementById('leYearError').style.display = 'block';
            ok = false;
        }
    }

    const fromVal = document.getElementById('leAvailFrom').value;
    const toVal = document.getElementById('leAvailTo').value;
    if (fromVal && toVal && toVal < fromVal) {
        document.getElementById('leAvailToError').style.display = 'block';
        ok = false;
    }

    if (leSelectedFiles.length === 0) {
        document.getElementById('leImagesError').closest('.le-row').classList.add('has-error');
        ok = false;
    }

    if (!document.getElementById('leTerms').checked) {
        document.getElementById('leTermsError').style.display = 'block';
        ok = false;
    }

    return ok;
}

/* ---------------- SUBMIT (AJAX) ---------------- */
function leInitForm(){
    const form = document.getElementById('leForm');
    if (!form) return;

    const dropzone = document.getElementById('leDropzone');
    const fileInput = document.getElementById('leImages');
    dropzone.addEventListener('click', () => fileInput.click());
    fileInput.addEventListener('change', (e) => leAddFiles(e.target.files));
    ['dragenter','dragover'].forEach(evt => dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.add('le-dragover'); }));
    ['dragleave','drop'].forEach(evt => dropzone.addEventListener(evt, (e) => { e.preventDefault(); dropzone.classList.remove('le-dragover'); }));
    dropzone.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files) leAddFiles(e.dataTransfer.files); });

    const docDropzone = document.getElementById('leDocDropzone');
    const docInput = document.getElementById('leDocs');
    docDropzone.addEventListener('click', () => docInput.click());
    docInput.addEventListener('change', (e) => leAddDocs(e.target.files));
    ['dragenter','dragover'].forEach(evt => docDropzone.addEventListener(evt, (e) => { e.preventDefault(); docDropzone.classList.add('le-dragover'); }));
    ['dragleave','drop'].forEach(evt => docDropzone.addEventListener(evt, (e) => { e.preventDefault(); docDropzone.classList.remove('le-dragover'); }));
    docDropzone.addEventListener('drop', (e) => { if (e.dataTransfer && e.dataTransfer.files) leAddDocs(e.dataTransfer.files); });

    document.getElementById('leName').addEventListener('input', leScheduleTranslatePreview);

    form.addEventListener('submit', function(e){
        e.preventDefault();
        const pt = leCurrentT();
        if (!leValidateForm()) { return; }

        const btn = document.getElementById('leSubmitButton');
        btn.disabled = true;
        const originalLabel = document.getElementById('leSubmitBtn').textContent;
        document.getElementById('leSubmitBtn').textContent = pt.submitting;

        const formData = new FormData(form);
        formData.append('ajax', '1');

        fetch('insert_equipment.php', { method: 'POST', body: formData })
            .then(r => r.json().catch(() => ({ success:false, error: pt.genericError })))
            .then(data => {
                if (data.success) {
                    form.style.display = 'none';
                    document.getElementById('leSuccessBox').style.display = 'block';
                    leSetText('leSuccessMsg', pt.successMsg);
                } else {
                    showLeToast(data.error || pt.genericError);
                    btn.disabled = false;
                    document.getElementById('leSubmitBtn').textContent = originalLabel;
                }
            })
            .catch(() => {
                showLeToast(pt.networkError);
                btn.disabled = false;
                document.getElementById('leSubmitBtn').textContent = originalLabel;
            });
    });
}

window.pageLanguageCallback = function(lang){
    const pt = ListEquipmentT[lang] || ListEquipmentT.en;

    // BUG FIX: same issue as sell_product.php — the input language must
    // NOT follow the header's UI display language. A seller can type an
    // equipment name in any script regardless of what language the page
    // labels are shown in, so forcing leInputLang to `lang` made the
    // server assume the wrong source language and the translation preview
    // silently echoed the original text back for every language. Leave it
    // on "auto" so agri_detect_language() detects the real input language.

    leSetText('leTitle', pt.title); leSetText('leSub', pt.sub);
    leSetText('leBadgeReview', pt.badgeReview); leSetText('leBadgeReach', pt.badgeReach); leSetText('leBadgeFast', pt.badgeFast);
    leSetText('leLoginMsg', pt.loginMsg); leSetText('leLoginBtn', pt.loginBtn);
    leSetText('leEarnLbl', pt.earnLbl); leSetText('leEarnHint', pt.earnHint);
    leSetText('leSectionBasic', pt.sectionBasic); leSetText('leSectionPricing', pt.sectionPricing);
    leSetText('leSectionAvailability', pt.sectionAvailability); leSetText('leSectionOwner', pt.sectionOwner);
    leSetText('leSectionDocs', pt.sectionDocs);

    leSetText('leLblNameText', pt.lblNameText); leSetPh('leName', pt.namePh); leSetText('leNameError', pt.nameRequired);
    leSetText('leTLangEnLabel', pt.tLangEn); leSetText('leTLangMrLabel', pt.tLangMr); leSetText('leTLangHiLabel', pt.tLangHi);
    leSetText('leTranslatingText', pt.translating);

    leSetText('leLblType', pt.lblType);
    leSetText('leOptTractor', pt.optTractor); leSetText('leOptPowerTiller', pt.optPowerTiller);
    leSetText('leOptRotavator', pt.optRotavator); leSetText('leOptCultivator', pt.optCultivator);
    leSetText('leOptHarvester', pt.optHarvester); leSetText('leOptSeedDrill', pt.optSeedDrill);
    leSetText('leOptSprayer', pt.optSprayer); leSetText('leOptDrone', pt.optDrone);
    leSetText('leOptThresher', pt.optThresher); leSetText('leOptOther', pt.optOther);

    leSetText('leLblBrand', pt.lblBrand); leSetText('leLblModel', pt.lblModel);
    leSetText('leLblYear', pt.lblYear); leSetText('leYearError', pt.yearInvalid);
    leSetText('leLblHp', pt.lblHp);
    leSetText('leLblCondition', pt.lblCondition);
    leSetText('leCondExcellent', pt.condExcellent); leSetText('leCondGood', pt.condGood); leSetText('leCondAverage', pt.condAverage);
    leSetText('leLblDesc', pt.lblDesc); leSetPh('leDesc', pt.descPh);

    leSetText('leLblImages', pt.lblImages);
    leSetText('leDropzoneTitle', pt.dropzoneTitle); leSetText('leDropzoneSub', pt.dropzoneSub);
    leSetText('leImagesError', pt.imagesRequired);

    leSetText('leLblRentType', pt.lblRentType);
    leSetText('leRentHour', pt.rentHour); leSetText('leRentDay', pt.rentDay); leSetText('leRentAcre', pt.rentAcre);
    leSetText('leLblRentText', pt.lblRentText); leSetText('leRentError', pt.rentRequired);
    leSetText('leLblDeposit', pt.lblDeposit);
    leSetText('leLblMinDuration', pt.lblMinDuration); leSetPh('leMinDuration', pt.minDurationPh);
    leSetText('leLblOperator', pt.lblOperator); leSetText('leOperatorYes', pt.operatorYes); leSetText('leOperatorNo', pt.operatorNo);
    leSetText('leLblFuel', pt.lblFuel); leSetText('leFuelYes', pt.fuelYes); leSetText('leFuelNo', pt.fuelNo);
    leSetText('leLblTransport', pt.lblTransport); leSetText('leTransportYes', pt.transportYes); leSetText('leTransportNo', pt.transportNo);
    leSetText('leLblTransportCharge', pt.lblTransportCharge);

    leSetText('leLblFrom', pt.lblFrom); leSetText('leLblTo', pt.lblTo); leSetText('leAvailToError', pt.availToError);
    leSetText('leLblDays', pt.lblDays);
    leSetText('leDayMon', pt.dayMon); leSetText('leDayTue', pt.dayTue); leSetText('leDayWed', pt.dayWed);
    leSetText('leDayThu', pt.dayThu); leSetText('leDayFri', pt.dayFri); leSetText('leDaySat', pt.daySat); leSetText('leDaySun', pt.daySun);
    leSetText('leLblNotice', pt.lblNotice); leSetPh('leNotice', pt.noticePh);

    leSetText('leLblOwnerName', pt.lblOwnerName);
    leSetText('leLblPhone', pt.lblPhone); leSetText('lePhoneError', pt.phoneRequired);
    leSetText('leLblEmail', pt.lblEmail); leSetText('leEmailError', pt.emailInvalid);
    leSetText('leLblVillage', pt.lblVillage); leSetText('leLblDistrict', pt.lblDistrict); leSetText('leLblAddress', pt.lblAddress);

    leSetText('leLblDocs', pt.lblDocs);
    leSetText('leDocDropzoneTitle', pt.docDropzoneTitle); leSetText('leDocDropzoneSub', pt.docDropzoneSub);
    leSetText('leLblRules', pt.lblRules); leSetPh('leRules', pt.rulesPh);

    leSetText('leTermsLabel', pt.termsLabel); leSetText('leTermsError', pt.termsRequired);
    leSetText('leSubmitBtn', pt.submitBtn);
    leSetText('leSuccessMsg', pt.successMsg);
};

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    window.lang = savedLang;
    window.pageLanguageCallback(savedLang);
    leUpdateEarnings();
    leInitForm();
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
