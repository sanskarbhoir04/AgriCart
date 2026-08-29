<?php
/**
 * AgriCart - Authentication Modal Component
 * Include this file in your main layout/header
 * Usage: <?php include __DIR__ . '/auth-modal.php'; ?>
 *
 * Also add in your <head>:
 *   <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/auth.css">
 *
 * Also add before </body>:
 *   <script src="<?php echo $base_path; ?>/assets/js/auth.js"></script>
 *
 * Trigger the modal anywhere with:
 *   <button data-auth-open="login">Login</button>
 *   <button data-auth-open="register">Register</button>
 *   OR via JS: openAuthModal('login')
 */
?>

<!-- ========================================
     AUTH OVERLAY & MODAL
     ======================================== -->
<div class="auth-overlay" id="authOverlay" role="dialog" aria-modal="true" aria-label="AgriCart Login / Register">
  <div class="auth-modal" id="authModal">

    <!-- Close Button -->
    <button class="auth-close" id="authClose" aria-label="Close">✕</button>

    <!-- ======================================
         LEFT PANEL - Branding
         ====================================== -->
    <div class="auth-left">
      <!-- Logo -->
      <div class="auth-brand-logo">
        <div class="auth-brand-icon"><img src="../assets/images/agricart-logo.png?v=<?php echo @filemtime(dirname(__DIR__) . '/assets/images/agricart-logo.png') ?: time(); ?>" alt="AgriCart" style="width:62px;height:62px;object-fit:contain;border-radius:50%;flex-shrink:0"></div>
        <span class="auth-brand-name">Agri<span>Cart</span></span>
      </div>

      <!-- Hero -->
      <div class="auth-left-hero">
        <span class="hero-emoji">🚜</span>
        <h2>Maharashtra's Digital Agri Marketplace</h2>
        <p>शेतकऱ्यांना थेट बाजाराशी जोडणारे व्यासपीठ.<br>Connecting farmers directly to buyers & experts.</p>
      </div>

      <!-- Stats -->
      <div class="auth-left-stats">
        <div class="auth-stat">
          <div class="num">50K+</div>
          <div class="lbl">Farmers</div>
        </div>
        <div class="auth-stat">
          <div class="num">358</div>
          <div class="lbl">Talukas</div>
        </div>
        <div class="auth-stat">
          <div class="num">200+</div>
          <div class="lbl">Crops</div>
        </div>
      </div>
    </div><!-- /auth-left -->

    <!-- ======================================
         RIGHT PANEL - Login + Register forms
         ====================================== -->
    <div class="auth-right">

      <!-- Tab Switcher -->
      <div class="auth-tabs">
        <button class="auth-tab active" data-tab="login">Sign In</button>
        <button class="auth-tab" data-tab="register">Register</button>
      </div>

      <!-- ====================================
           LOGIN PANEL
           ==================================== -->
      <div class="auth-panel active" data-panel="login">
        <h2 class="auth-panel-title">Welcome Back 👋</h2>
        <p class="auth-panel-sub">Sign in to your AgriCart account</p>

        <!-- Login method switcher -->
        <div class="login-method-tabs">
          <button class="login-method-btn active" data-method="mobile">📱 Mobile</button>
          <button class="login-method-btn" data-method="email">✉️ Email</button>
          <button class="login-method-btn" data-method="otp">🔑 OTP Login</button>
        </div>

        <!-- Mobile + Password -->
        <div class="login-method-panel active" data-method-panel="mobile">
          <div class="form-group">
            <label>Mobile Number <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">📱</span>
              <input type="tel" id="loginMobile" placeholder="Enter 10-digit mobile number" maxlength="10">
            </div>
          </div>
          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="loginPassword" placeholder="Enter your password">
              <button type="button" class="pwd-toggle" tabindex="-1">👁</button>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:18px;">
            <label class="checkbox-row">
              <input type="checkbox"> Remember me
            </label>
            <a class="forgot-link" onclick="showForgotPassword()">Forgot Password?</a>
          </div>
          <button class="btn-primary" id="loginSubmitBtn">Sign In to AgriCart</button>
        </div>

        <!-- Email + Password -->
        <div class="login-method-panel" data-method-panel="email" style="display:none;">
          <div class="form-group">
            <label>Email Address <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">✉️</span>
              <input type="email" id="loginEmail" placeholder="Enter your email address">
            </div>
          </div>
          <div class="form-group">
            <label>Password <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">🔒</span>
              <input type="password" id="loginEmailPassword" placeholder="Enter your password">
              <button type="button" class="pwd-toggle" tabindex="-1">👁</button>
            </div>
          </div>
          <div style="text-align:right;margin-bottom:18px;">
            <a class="forgot-link" onclick="showForgotPassword()">Forgot Password?</a>
          </div>
          <button class="btn-primary" onclick="document.getElementById('loginSubmitBtn').click()">Sign In to AgriCart</button>
        </div>

        <!-- OTP Login -->
        <div class="login-method-panel" data-method-panel="otp" style="display:none;">
          <div class="form-group">
            <label>Mobile Number <span class="req">*</span></label>
            <div class="input-wrap">
              <span class="input-icon">📱</span>
              <input type="tel" id="otpLoginMobile" placeholder="Enter 10-digit mobile number" maxlength="10">
            </div>
          </div>
          <button class="btn-secondary" onclick="startResendCountdown('resendLoginOtp'); document.querySelector('.login-otp-section').style.display='block';" style="margin-bottom:14px;">
            Send OTP
          </button>
          <div class="login-otp-section" style="display:none;">
            <p style="font-size:13px;color:var(--text-soft);text-align:center;margin-bottom:8px;">Enter 6-digit OTP sent to your mobile</p>
            <div class="otp-row">
              <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
              <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
              <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
              <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
              <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
              <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
            </div>
            <div style="text-align:center;margin-bottom:16px;">
              <button id="resendLoginOtp" class="forgot-link" onclick="startResendCountdown('resendLoginOtp')">Resend OTP</button>
            </div>
            <button class="btn-primary" onclick="document.getElementById('loginSubmitBtn').click()">Verify & Sign In</button>
          </div>
        </div>

        <!-- Forgot Password Section (hidden) -->
        <div class="forgot-password-section" style="display:none;">
          <div style="display:flex;align-items:center;gap:10px;margin-bottom:20px;">
            <button onclick="hideForgotPassword()" style="background:none;border:none;cursor:pointer;font-size:18px;color:var(--text-soft);">←</button>
            <div>
              <div style="font-family:'Poppins',sans-serif;font-weight:700;font-size:16px;color:var(--text-dark);">Reset Password</div>
              <div style="font-size:12.5px;color:var(--text-soft);">Enter your registered mobile number</div>
            </div>
          </div>
          <div class="form-group">
            <label>Registered Mobile Number</label>
            <div class="input-wrap">
              <span class="input-icon">📱</span>
              <input type="tel" placeholder="Enter 10-digit mobile number" maxlength="10">
            </div>
          </div>
          <button class="btn-primary" onclick="alert('OTP sent to your mobile number!'); hideForgotPassword();">Send Reset OTP</button>
        </div>

        <div class="auth-divider">or</div>
        <p style="text-align:center;font-size:13px;color:var(--text-soft);">
          New to AgriCart? <a style="color:var(--green-700);font-weight:600;cursor:pointer;" onclick="document.querySelector('[data-tab=register]').click()">Create Account →</a>
        </p>
      </div><!-- /login panel -->

      <!-- ====================================
           REGISTER PANEL
           ==================================== -->
      <div class="auth-panel" data-panel="register">
        <h2 class="auth-panel-title">Create Account 🌱</h2>
        <p class="auth-panel-sub" style="display:flex;justify-content:space-between;">
          <span>Join AgriCart — Maharashtra's Farmer Network</span>
          <span id="stepCounter" style="font-weight:600;color:var(--green-700);">Step 1 of 10</span>
        </p>

        <!-- Progress Steps -->
        <div class="reg-progress">
          <?php
          $step_labels = ['Account','Verify','Location','Profile','Land','Crops','Machinery','Market','Community','Finish'];
          foreach ($step_labels as $i => $label):
            $n = $i + 1;
          ?>
            <div class="reg-step-dot <?= $n === 1 ? 'active' : '' ?>" data-step="<?= $n ?>">
              <div class="dot"><?= $n ?></div>
              <div class="step-lbl"><?= $label ?></div>
            </div>
            <?php if ($n < 10): ?>
              <div class="reg-step-line"></div>
            <?php endif; ?>
          <?php endforeach; ?>
        </div>

        <!-- ======== STEP 1: Account Setup ======== -->
        <div class="reg-step-content active" data-step="1">
          <div class="form-section-title">👤 Basic Account Information</div>
          <div class="form-row">
            <div class="form-group">
              <label>Full Name <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">👤</span>
                <input type="text" id="regFullName" placeholder="Your full name" required>
              </div>
              <span class="field-error"></span>
            </div>
            <div class="form-group">
              <label>Username <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">@</span>
                <input type="text" id="regUsername" placeholder="Choose a username" required>
              </div>
              <span class="field-error"></span>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Mobile Number <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">📱</span>
                <input type="tel" id="regMobile" placeholder="10-digit mobile" maxlength="10" required>
              </div>
              <span class="field-error"></span>
            </div>
            <div class="form-group">
              <label>Email Address</label>
              <div class="input-wrap">
                <span class="input-icon">✉️</span>
                <input type="email" id="regEmail" placeholder="your@email.com">
              </div>
              <span class="field-error"></span>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Password <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🔒</span>
                <input type="password" id="regPassword" class="pwd-field" placeholder="Create strong password" required>
                <button type="button" class="pwd-toggle" tabindex="-1">👁</button>
              </div>
              <div class="pwd-strength-bar"><div class="pwd-strength-fill"></div></div>
              <div class="pwd-strength-label"></div>
              <span class="field-error"></span>
            </div>
            <div class="form-group">
              <label>Confirm Password <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🔐</span>
                <input type="password" id="regConfirmPassword" placeholder="Re-enter password" required>
                <button type="button" class="pwd-toggle" tabindex="-1">👁</button>
              </div>
              <span class="field-error"></span>
            </div>
          </div>
          <div class="form-group">
            <label>Profile Photo</label>
            <div class="photo-upload-box">
              <input type="file" accept="image/*">
              <div class="upload-icon">📷</div>
              <p>Upload Profile Photo</p>
              <span>JPG, PNG up to 2MB</span>
            </div>
          </div>
          <div class="btn-row" style="margin-top:16px;">
            <button class="btn-primary btn-reg-next">Next: Verification →</button>
          </div>
        </div>

        <!-- ======== STEP 2: Identity Verification ======== -->
        <div class="reg-step-content" data-step="2">
          <div class="form-section-title">📲 Mobile OTP Verification</div>
          <p style="font-size:13px;color:var(--text-soft);margin-bottom:12px;">An OTP has been sent to your mobile number</p>
          <div class="otp-row otp-section" data-resend-btn="resendMobileOtp">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
            <input class="otp-input" type="text" maxlength="1" inputmode="numeric">
          </div>
          <div style="text-align:center;margin-bottom:20px;">
            <button id="resendMobileOtp" class="forgot-link">Resend OTP (30s)</button>
          </div>

          <div class="form-section-title">🪪 Government ID</div>
          <div class="form-row">
            <div class="form-group">
              <label>ID Type <span class="req">*</span></label>
              <div class="input-wrap no-icon">
                <select required>
                  <option value="">Select ID Type</option>
                  <option>Aadhaar Card</option>
                  <option>PAN Card</option>
                  <option>Voter ID</option>
                  <option>Driving License</option>
                  <option>Passport</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>ID Number <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🆔</span>
                <input type="text" placeholder="Enter ID number" required>
              </div>
              <span class="field-error"></span>
            </div>
          </div>
          <div class="form-group">
            <label>Upload ID Document</label>
            <div class="photo-upload-box">
              <input type="file" accept="image/*,.pdf">
              <div class="upload-icon">📄</div>
              <p>Upload Front Side</p>
              <span>JPG, PNG, PDF up to 5MB</span>
            </div>
          </div>
          <div class="btn-row">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Location →</button>
          </div>
        </div>

        <!-- ======== STEP 3: Location Details ======== -->
        <div class="reg-step-content" data-step="3">
          <div class="form-section-title">📍 Location Details</div>
          <div class="form-row">
            <div class="form-group">
              <label>State <span class="req">*</span></label>
              <div class="input-wrap no-icon">
                <select required>
                  <option value="">Select State</option>
                  <option selected>Maharashtra</option>
                  <option>Karnataka</option>
                  <option>Gujarat</option>
                  <option>Madhya Pradesh</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>District <span class="req">*</span></label>
              <div class="input-wrap no-icon">
                <select required>
                  <option value="">Select District</option>
                  <option>Pune</option><option>Nashik</option><option>Ahmednagar</option>
                  <option>Aurangabad</option><option>Solapur</option><option>Kolhapur</option>
                  <option>Satara</option><option>Sangli</option><option>Nagpur</option>
                  <option>Amravati</option><option>Latur</option><option>Nanded</option>
                </select>
              </div>
              <span class="field-error"></span>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Taluka <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">🏘</span>
                <input type="text" placeholder="Enter taluka name" required>
              </div>
              <span class="field-error"></span>
            </div>
            <div class="form-group">
              <label>Village / Town</label>
              <div class="input-wrap">
                <span class="input-icon">🏡</span>
                <input type="text" placeholder="Enter village/town name">
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>PIN Code <span class="req">*</span></label>
              <div class="input-wrap">
                <span class="input-icon">📮</span>
                <input type="text" placeholder="6-digit PIN code" maxlength="6" required>
              </div>
              <span class="field-error"></span>
            </div>
            <div class="form-group">
              <label>Auto-detect Location</label>
              <button type="button" onclick="alert('Location detection would use browser geolocation API')" 
                style="margin-top:2px;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;background:var(--green-50);cursor:pointer;font-size:13px;color:var(--green-700);font-weight:600;width:100%;display:flex;align-items:center;gap:8px;justify-content:center;">
                📍 Detect My Location
              </button>
            </div>
          </div>
          <div class="btn-row">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Farmer Profile →</button>
          </div>
        </div>

        <!-- ======== STEP 4: Farmer Profile ======== -->
        <div class="reg-step-content" data-step="4">
          <div class="form-section-title">🧑‍🌾 Farmer Profile</div>
          <div class="form-group">
            <label>Account Type <span class="req">*</span></label>
            <div class="radio-grid">
              <?php
              $farmer_types = [
                ['🧑‍🌾','Individual Farmer'],
                ['🏪','Trader'],
                ['🛒','Buyer'],
                ['🤝','FPO'],
                ['👨‍🔬','Agricultural Expert'],
              ];
              foreach ($farmer_types as [$icon, $label]):
              ?>
              <label class="radio-card">
                <input type="radio" name="farmerType" value="<?= htmlspecialchars($label) ?>">
                <span class="rc-icon"><?= $icon ?></span>
                <?= htmlspecialchars($label) ?>
              </label>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Farming Experience</label>
              <div class="input-wrap no-icon">
                <select>
                  <option value="">Select experience</option>
                  <option>0-2 years (Beginner)</option>
                  <option>3-5 years</option>
                  <option>6-10 years</option>
                  <option>11-20 years</option>
                  <option>20+ years (Expert)</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Education Level</label>
              <div class="input-wrap no-icon">
                <select>
                  <option value="">Select education</option>
                  <option>No Formal Education</option>
                  <option>Primary (1st-7th)</option>
                  <option>Secondary (8th-10th)</option>
                  <option>HSC (12th)</option>
                  <option>Diploma</option>
                  <option>Graduate</option>
                  <option>Post Graduate</option>
                  <option>Agricultural Degree</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Farming Method <span class="req">*</span></label>
            <div class="radio-grid">
              <label class="radio-card"><input type="radio" name="farmingMethod" value="organic"> 🌿 Organic Farming</label>
              <label class="radio-card"><input type="radio" name="farmingMethod" value="natural"> 🌱 Natural Farming</label>
              <label class="radio-card"><input type="radio" name="farmingMethod" value="conventional"> 🚜 Conventional</label>
              <label class="radio-card"><input type="radio" name="farmingMethod" value="mixed"> 🔄 Mixed Method</label>
            </div>
          </div>
          <div class="btn-row">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Land Info →</button>
          </div>
        </div>

        <!-- ======== STEP 5: Land Information ======== -->
        <div class="reg-step-content" data-step="5">
          <div class="form-section-title">🗺 Land Information</div>
          <div class="form-row trio">
            <div class="form-group">
              <label>Total Land (Acres)</label>
              <div class="input-wrap">
                <span class="input-icon">📐</span>
                <input type="number" placeholder="e.g. 5.5" step="0.5" min="0">
              </div>
            </div>
            <div class="form-group">
              <label>Irrigated (Acres)</label>
              <div class="input-wrap">
                <span class="input-icon">💧</span>
                <input type="number" placeholder="e.g. 3" step="0.5" min="0">
              </div>
            </div>
            <div class="form-group">
              <label>Non-Irrigated (Acres)</label>
              <div class="input-wrap">
                <span class="input-icon">🌾</span>
                <input type="number" placeholder="e.g. 2.5" step="0.5" min="0">
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Soil Type</label>
              <div class="input-wrap no-icon">
                <select>
                  <option value="">Select soil type</option>
                  <option>Black Cotton Soil (Regur)</option>
                  <option>Red Soil</option>
                  <option>Alluvial Soil</option>
                  <option>Laterite Soil</option>
                  <option>Sandy Loam</option>
                  <option>Clay Soil</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label>Primary Water Source</label>
              <div class="input-wrap no-icon">
                <select>
                  <option value="">Select water source</option>
                  <option>Well / Borewell</option>
                  <option>Canal Irrigation</option>
                  <option>River</option>
                  <option>Dam / Reservoir</option>
                  <option>Rain-fed Only</option>
                  <option>Drip Irrigation</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-group">
            <label>Farm Photos</label>
            <div class="photo-upload-box">
              <input type="file" accept="image/*" multiple>
              <div class="upload-icon">🏞</div>
              <p>Upload Farm / Field Photos</p>
              <span>Up to 5 photos, JPG/PNG, max 3MB each</span>
            </div>
          </div>
          <div class="btn-row">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Crops →</button>
          </div>
        </div>

        <!-- ======== STEP 6: Crop Information ======== -->
        <div class="reg-step-content" data-step="6">
          <div class="form-section-title">🌽 Crop Information</div>
          <div class="form-row">
            <div class="form-group">
              <label>Primary Crop <span class="req">*</span></label>
              <div class="input-wrap no-icon">
                <select required>
                  <option value="">Select primary crop</option>
                  <option>Wheat (गहू)</option><option>Rice (तांदूळ)</option>
                  <option>Sugarcane (ऊस)</option><option>Cotton (कापूस)</option>
                  <option>Soybean (सोयाबीन)</option><option>Onion (कांदा)</option>
                  <option>Tomato (टोमेटो)</option><option>Grapes (द्राक्षे)</option>
                  <option>Pomegranate (डाळिंब)</option><option>Turmeric (हळद)</option>
                  <option>Jowar (ज्वारी)</option><option>Bajra (बाजरी)</option>
                  <option>Tur Dal (तूर)</option><option>Chickpea (हरभरा)</option>
                </select>
              </div>
              <span class="field-error"></span>
            </div>
            <div class="form-group">
              <label>Secondary Crop</label>
              <div class="input-wrap no-icon">
                <select>
                  <option value="">Select secondary crop</option>
                  <option>Wheat (गहू)</option><option>Rice (तांदूळ)</option>
                  <option>Vegetables</option><option>Pulses</option>
                  <option>Oilseeds</option><option>Spices</option>
                </select>
              </div>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Annual Production (Quintals)</label>
              <div class="input-wrap">
                <span class="input-icon">⚖️</span>
                <input type="number" placeholder="e.g. 50" min="0">
              </div>
            </div>
            <div class="form-group">
              <label>Main Harvest Season</label>
              <div class="input-wrap no-icon">
                <select>
                  <option value="">Select season</option>
                  <option>Kharif (June – October)</option>
                  <option>Rabi (November – April)</option>
                  <option>Zaid (March – June)</option>
                  <option>Year Round</option>
                </select>
              </div>
            </div>
          </div>
          <div class="btn-row">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Machinery →</button>
          </div>
        </div>

        <!-- ======== STEP 7: Machinery & Equipment ======== -->
        <div class="reg-step-content" data-step="7">
          <div class="form-section-title">🚜 Machinery & Equipment</div>
          <p style="font-size:13px;color:var(--text-soft);margin-bottom:14px;">Select the equipment you own. You can offer these for rental to other farmers.</p>
          <?php
          $equipment = [
            ['🚜','Tractor','tractor'],
            ['🌾','Harvester / Combine','harvester'],
            ['🔄','Rotavator','rotavator'],
            ['💦','Sprayer (Manual/Power)','sprayer'],
            ['🌱','Seed Drill / Planter','seeder'],
            ['🚛','Transport Vehicle','vehicle'],
            ['💧','Drip Irrigation Setup','drip'],
            ['⚡','Solar Pump','solar'],
          ];
          ?>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
            <?php foreach ($equipment as [$icon, $name, $val]): ?>
            <label style="display:flex;align-items:center;gap:10px;padding:12px;border:1.5px solid var(--border);border-radius:8px;cursor:pointer;font-size:13px;color:var(--text-mid);transition:var(--transition);" 
              onclick="this.style.borderColor=this.querySelector('input').checked?'var(--border)':'var(--green-600)'; this.style.background=this.querySelector('input').checked?'':'var(--green-50)';">
              <input type="checkbox" name="equipment[]" value="<?= $val ?>" style="accent-color:var(--green-600);">
              <span style="font-size:20px;"><?= $icon ?></span>
              <?= htmlspecialchars($name) ?>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">💰</span> Available for Rental</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="btn-row" style="margin-top:18px;">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Marketplace →</button>
          </div>
        </div>

        <!-- ======== STEP 8: Marketplace Preferences ======== -->
        <div class="reg-step-content" data-step="8">
          <div class="form-section-title">🛒 Marketplace Preferences</div>
          <div class="form-group">
            <label>I want to use AgriCart as <span class="req">*</span></label>
            <div class="radio-grid">
              <label class="radio-card"><input type="radio" name="marketRole" value="seller"> 📦 Seller (Sell my produce)</label>
              <label class="radio-card"><input type="radio" name="marketRole" value="buyer"> 🛒 Buyer (Buy produce)</label>
              <label class="radio-card"><input type="radio" name="marketRole" value="both"> 🔄 Both Buyer & Seller</label>
              <label class="radio-card"><input type="radio" name="marketRole" value="services"> 🛠 Service Provider</label>
            </div>
          </div>
          <div class="form-section-title" style="margin-top:16px;">Interested Categories</div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
            <?php
            $cats = ['🌾 Grains & Cereals','🥦 Vegetables','🍎 Fruits','🌿 Spices','🧴 Oils & Oilseeds','🐄 Dairy Products','🌱 Seeds','🧪 Fertilizers & Pesticides'];
            foreach ($cats as $cat):
            ?>
            <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:var(--text-mid);cursor:pointer;">
              <input type="checkbox" style="accent-color:var(--green-600);"> <?= $cat ?>
            </label>
            <?php endforeach; ?>
          </div>
          <div class="form-section-title" style="margin-top:18px;">Notifications</div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">📊</span> Market Rate Alerts</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">💸</span> Price Drop Alerts</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">🔔</span> New Buyer/Seller Alerts</div>
            <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
          </div>
          <div class="btn-row" style="margin-top:18px;">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Community →</button>
          </div>
        </div>

        <!-- ======== STEP 9: Community Preferences ======== -->
        <div class="reg-step-content" data-step="9">
          <div class="form-section-title">🤝 Community & Alerts</div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">🏘</span> Join My District Community</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">🌾</span> Join Crop-Specific Group</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">👨‍🔬</span> Expert Advisory Subscription</div>
            <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">⛈</span> Weather Alerts (SMS + App)</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">🏛</span> Government Scheme Notifications</div>
            <label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label>
          </div>
          <div class="notif-row">
            <div class="notif-info"><span class="notif-icon">📰</span> Weekly AgriNews Digest</div>
            <label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label>
          </div>
          <div class="btn-row" style="margin-top:20px;">
            <button class="btn-secondary btn-reg-prev">← Back</button>
            <button class="btn-primary btn-reg-next">Next: Final Step →</button>
          </div>
        </div>

        <!-- ======== STEP 10: Final Submission ======== -->
        <div class="reg-step-content" data-step="10">
          <div class="step-form-body">
            <div class="form-section-title">✅ Almost Done!</div>
            <p style="font-size:13.5px;color:var(--text-soft);margin-bottom:20px;">Please review and accept our policies to complete registration.</p>

            <div style="background:var(--green-50);border:1px solid var(--border-mid);border-radius:12px;padding:16px;margin-bottom:16px;">
              <div style="display:flex;gap:10px;align-items:flex-start;margin-bottom:12px;">
                <span style="font-size:20px;">📋</span>
                <div>
                  <div style="font-size:13.5px;font-weight:600;color:var(--text-dark);margin-bottom:4px;">Registration Summary</div>
                  <div style="font-size:12.5px;color:var(--text-soft);">Your information has been captured across all steps. You can update these details anytime from your profile.</div>
                </div>
              </div>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
                <?php
                $summary_items = [
                  ['✅','Account Created','Steps 1-2'],
                  ['✅','Location Set','Step 3'],
                  ['✅','Farmer Profile','Step 4'],
                  ['✅','Land Details','Step 5'],
                  ['✅','Crop Info','Step 6'],
                  ['✅','Equipment','Steps 7-9'],
                ];
                foreach ($summary_items as [$icon, $title, $sub]):
                ?>
                <div style="font-size:12.5px;color:var(--green-700);display:flex;align-items:center;gap:6px;"><?= "$icon $title" ?></div>
                <?php endforeach; ?>
              </div>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="checkbox-row">
                <input type="checkbox" id="agreeTerms" required>
                I agree to AgriCart's <a href="#" onclick="event.preventDefault()">Terms & Conditions</a> and <a href="#" onclick="event.preventDefault()">Privacy Policy</a>
              </label>
            </div>
            <div class="form-group" style="margin-bottom:20px;">
              <label class="checkbox-row">
                <input type="checkbox" id="agreeMarketing">
                I agree to receive agri-related updates, market rates, and scheme notifications
              </label>
            </div>

            <div class="btn-row">
              <button class="btn-secondary btn-reg-prev">← Back</button>
              <button class="btn-primary" id="finalSubmitBtn">🌱 Create My Account</button>
            </div>
          </div>
        </div>

      </div><!-- /register panel -->

    </div><!-- /auth-right -->
  </div><!-- /auth-modal -->
</div><!-- /auth-overlay -->