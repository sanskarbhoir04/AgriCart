<?php 
require_once __DIR__ . '/security.php';
require_once __DIR__ . '/assets.php';
agri_session_start();
// Base path - XAMPP Windows + Linux compatible
$_doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_this_dir = str_replace('\\', '/', realpath(dirname(dirname(__FILE__))));
$base_path  = rtrim(str_replace($_doc_root, '', $_this_dir), '/');
include __DIR__ . "/db.php"; 

$display_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : (isset($_SESSION['user']) ? $_SESSION['user'] : "");
$is_logged_in = ($display_name != "");

// Full profile info saved in the session at login/register, used to
// pre-fill "My Profile" so it shows the farmer's saved details right away.
$profile_mobile  = $_SESSION['user'] ?? '';
$profile_email   = $_SESSION['user_email'] ?? '';
$profile_village = $_SESSION['user_village'] ?? '';
$profile_taluka  = $_SESSION['user_taluka'] ?? '';
$profile_district = $_SESSION['user_district'] ?? '';
$profile_address = trim(implode(', ', array_filter([$profile_village, $profile_taluka, $profile_district])));
$profile_farmer_type = $_SESSION['user_farmer_type'] ?? '';
$profile_crop         = $_SESSION['user_crop'] ?? '';
$profile_role         = ucfirst($_SESSION['user_role'] ?? 'farmer');

// Structured address + photo fields for the "Edit Profile" form. Pulled
// fresh from the DB (not just the session) so fields the session doesn't
// carry — address_line1/2, city, state, pincode, profile_photo — are
// always current, including right after a fresh login.
require_once __DIR__ . '/crop_list.php';
require_once __DIR__ . '/profile_edit_schema.php';
$profile_photo_path = '';
$profile_line1 = $profile_line2 = $profile_city = $profile_state = $profile_pincode = '';
if (isset($_SESSION['user_id'])) {
    agri_profile_edit_bootstrap_schema($conn);
    $pStmt = $conn->prepare("SELECT profile_photo, address_line1, address_line2, city, state, saved_pincode FROM users WHERE id = ? LIMIT 1");
    $pStmt->bind_param("i", $_SESSION['user_id']);
    $pStmt->execute();
    if ($pRow = $pStmt->get_result()->fetch_assoc()) {
        $profile_photo_path = $pRow['profile_photo'] ?? '';
        $profile_line1   = $pRow['address_line1'] ?? '';
        $profile_line2   = $pRow['address_line2'] ?? '';
        $profile_city    = $pRow['city'] ?? '';
        $profile_state   = $pRow['state'] ?? '';
        $profile_pincode = $pRow['saved_pincode'] ?? '';
    }
}

// "Member since" isn't kept in the session, so pull it once from the DB
// (only when logged in) to show on the profile card.
$profile_member_since = '';
$is_seller = false;
$seller_type = null; // 'product' | 'rental' | 'both' | null
if (isset($_SESSION['user_id'])) {
    $mStmt = $conn->prepare("SELECT created_at FROM users WHERE id = ? LIMIT 1");
    $mStmt->bind_param("i", $_SESSION['user_id']);
    $mStmt->execute();
    $mRow = $mStmt->get_result()->fetch_assoc();
    if ($mRow && !empty($mRow['created_at'])) {
        $profile_member_since = date('d M Y', strtotime($mRow['created_at']));
    }

    // Has this user listed any product or equipment? Actual sellers see
    // the "Seller Dashboard" link below — everyone else sees "Become a
    // Seller" instead so they go through the onboarding form first.
    $sellerChk = $conn->prepare(
        "SELECT (SELECT COUNT(*) FROM products WHERE added_by_user_id = ?)
              + (SELECT COUNT(*) FROM equipment WHERE owner_user_id = ?) AS c"
    );
    if ($sellerChk) {
        $sellerChk->bind_param("ii", $_SESSION['user_id'], $_SESSION['user_id']);
        $sellerChk->execute();
        $is_seller = ((int)($sellerChk->get_result()->fetch_assoc()['c'] ?? 0)) > 0;
    }

    // seller_type is what actually drives the dropdown links below — a
    // seller only sees "Sell Your Produce" / "Rent Out Equipment" for
    // the option(s) they picked on the "Become a Seller" form. Anyone
    // with real listings but no seller_type yet (legacy sellers, from
    // before this preference existed) is treated as 'both'.
    $stChk = $conn->prepare("SELECT seller_type FROM seller_payout_profiles WHERE user_id = ? LIMIT 1");
    if ($stChk) {
        $stChk->bind_param("i", $_SESSION['user_id']);
        $stChk->execute();
        $stRow = $stChk->get_result()->fetch_assoc();
        $seller_type = $stRow['seller_type'] ?? null;
        if ($seller_type) {
            $is_seller = true;
        } elseif ($is_seller) {
            $seller_type = 'both';
        }
    }
}
?>
<script>
window.AGRI_LOGGED_IN = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
window.AGRI_BASE_PATH = "<?php echo $base_path; ?>";
</script>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="<?php echo htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8'); ?>">
    <title>AgriCart – A Digital Agriculture Service and E-Commerce Platform</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <link rel="stylesheet" href="<?php echo $base_path . agri_asset_v('/assets/css/style.css', $_this_dir); ?>">
    <link rel="stylesheet" href="<?php echo $base_path . agri_asset_v('/assets/css/auth.css', $_this_dir); ?>">
    <link rel="stylesheet" href="<?php echo $base_path . agri_asset_v('/assets/css/responsive.css', $_this_dir); ?>">

    <style>
        /* 🟢 1. EXACT Flash Bar CSS from Image (Size Reduced) */
        #flash-bar {
            position: fixed !important; 
            top: 0 !important; 
            left: 0 !important; 
            width: 100% !important; 
            height: 28px !important; /* Size reduced from 35px */
            background-color: #1b3a1a !important; 
            z-index: 1100 !important; 
            overflow: hidden !important; 
            display: flex !important; 
            align-items: center !important;
            font-family: 'Poppins', sans-serif !important;
        }

        .marquee-outer {
            width: 100%;
            overflow: hidden;
            display: flex;
            align-items: center;
        }

        @keyframes mqScroll {
            0%   { transform: translateX(100vw); }
            100% { transform: translateX(-100%); }
        }

        .marquee-inner {
            display: inline-flex;
            white-space: nowrap;
            align-items: center;
            will-change: transform;
        }
        .marquee-inner:hover { animation-play-state: paused !important; }

        .marquee-inner span {
            color: #FFD700 !important;
            font-size: 12px !important;
            font-weight: 500 !important;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        .marquee-inner span i {
            color: #FFD700 !important;
            font-size: 12px !important;
        }
        .marquee-inner .separator {
            color: #FFD700 !important;
            margin: 0 15px !important;
            opacity: 0.8;
        }

        /* 🟢 2. FUNCTION KEY ACTIVE COLOR */
        .nav-menu a.active {
            color: #4CAF50 !important; 
            transform: translateY(-4px) !important;
            border-bottom: 3px solid #4CAF50 !important; 
            padding-bottom: 4px !important;
        }
        .nav-menu a:hover { 
            color: #4CAF50 !important; 
        }

        /* 🟢 3. Layout Gap Fix (Adjusted for new flash bar height) */
        #main-header { top: 28px !important; }
        body { padding-top: 98px !important; }

        /* ==================================================================
           🎬 4. GLOBAL ANIMATION LAYER (inlined so it always loads, on every page)
           ================================================================== */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: 0.001ms !important; animation-iteration-count: 1 !important; transition-duration: 0.001ms !important; }
            .agri-reveal { opacity: 1 !important; transform: none !important; }
        }

        @keyframes agriPageFadeIn { from { opacity: 0; } to { opacity: 1; } }
        html.agri-anim-ready body { animation: agriPageFadeIn .55s cubic-bezier(.22,.8,.36,1) both; }

        @keyframes agriNavItemIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes agriFadeInLeft  { from { opacity: 0; transform: translateX(-14px); } to { opacity: 1; transform: translateX(0); } }
        @keyframes agriFadeInRight { from { opacity: 0; transform: translateX(14px); } to { opacity: 1; transform: translateX(0); } }
        .nav-logo   { animation: agriFadeInLeft .5s ease both; }
        .nav-right  { animation: agriFadeInRight .5s ease both; }
        .nav-menu li { opacity: 0; animation: agriNavItemIn .45s cubic-bezier(.22,.8,.36,1) both; }
        .nav-menu li:nth-child(1) { animation-delay: .08s; }
        .nav-menu li:nth-child(2) { animation-delay: .13s; }
        .nav-menu li:nth-child(3) { animation-delay: .18s; }
        .nav-menu li:nth-child(4) { animation-delay: .23s; }
        .nav-menu li:nth-child(5) { animation-delay: .28s; }
        .nav-menu li:nth-child(6) { animation-delay: .33s; }
        .nav-menu li:nth-child(7) { animation-delay: .38s; }
        .nav-menu li:nth-child(8) { animation-delay: .43s; }

        .agri-reveal {
            opacity: 0;
            transform: translateY(34px);
            transition: opacity .7s cubic-bezier(.16,.84,.44,1), transform .7s cubic-bezier(.16,.84,.44,1);
        }
        .agri-reveal.agri-reveal-left  { transform: translateX(-40px); }
        .agri-reveal.agri-reveal-right { transform: translateX(40px); }
        .agri-reveal.agri-reveal-zoom  { transform: scale(.9); }
        .agri-reveal.agri-in { opacity: 1 !important; transform: translate(0, 0) scale(1) !important; }

        button, .btn, [class*="btn"], a.gallery-btn, a.dropdown-link, .save-btn, .checkout-btn, .add-btn {
            transition: transform .16s cubic-bezier(.34,1.56,.64,1), filter .2s ease, box-shadow .2s ease;
        }
        button:active, .btn:active, [class*="btn"]:active { transform: scale(.95); }
        button:hover:not(:disabled), .btn:hover, [class*="btn"]:hover { filter: brightness(1.04); }

        .agri-ripple-wrap { position: relative; overflow: hidden; }
        .agri-ripple {
            position: absolute; border-radius: 50%; background: rgba(255,255,255,.55);
            transform: scale(0); opacity: .9; pointer-events: none;
            animation: agriRippleAnim .6s ease-out forwards;
        }
        @keyframes agriRippleAnim { to { transform: scale(2.6); opacity: 0; } }

        .product-img, .gallery-img, .kb-product-card img, .kb-listing-card img, .kb-farmer-card img, .kb-buyer-card img {
            transition: transform .55s cubic-bezier(.16,.84,.44,1);
        }
        .product-card:hover .product-img,
        .gallery-card:hover .gallery-img,
        .kb-product-card:hover img,
        .kb-listing-card:hover img,
        .kb-farmer-card:hover img,
        .kb-buyer-card:hover img { transform: scale(1.08); }

        @keyframes agriPulseRed   { 0%,100% { box-shadow: 0 0 0 0 rgba(220,38,38,.35); } 50% { box-shadow: 0 0 0 6px rgba(220,38,38,0); } }
        @keyframes agriPulseGreen { 0%,100% { box-shadow: 0 0 0 0 rgba(76,175,80,.4);  } 50% { box-shadow: 0 0 0 7px rgba(76,175,80,0);  } }
        .product-badge, .kb-trending-card__badge { animation: agriPulseRed 2.2s ease-in-out infinite; }
        .hero-badge, .kb-badge, .kb-hero__badge, .slide-tag { animation: agriPulseGreen 2.6s ease-in-out infinite; }
        .dot.active { animation: agriPulseGreen 1.6s ease-in-out infinite; }

        .nav-menu a i { display: inline-block; transition: transform .3s cubic-bezier(.34,1.56,.64,1); }
        .nav-menu a:hover i { transform: translateY(-2px) scale(1.15); }
        @keyframes agriIconFloat {
            0%   { transform: translateY(0) rotate(0); }
            30%  { transform: translateY(-6px) rotate(-8deg); }
            60%  { transform: translateY(1px) rotate(4deg); }
            100% { transform: translateY(0) rotate(0); }
        }
        .footer-socials a:hover i { animation: agriIconFloat .6s ease; display: inline-block; }

        .form-group input, .form-group textarea, .calc-input, .kb-search-bar input, .hero-search input {
            transition: border-color .25s ease, box-shadow .25s ease;
        }
        .form-group input:focus, .form-group textarea:focus, .calc-input:focus, .kb-search-bar input:focus {
            box-shadow: 0 0 0 4px rgba(76,175,80,.15);
        }

        @keyframes agriPopIn { from { opacity: 0; transform: scale(.86) translateY(16px); } to { opacity: 1; transform: scale(1) translateY(0); } }
        .modal-card { animation: agriPopIn .35s cubic-bezier(.34,1.56,.64,1); }

        .agri-counted { display: inline-block; font-variant-numeric: tabular-nums; }

        @keyframes agriFloatSlow { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
        .offer-strip i, .scan-box i { animation: agriFloatSlow 3.2s ease-in-out infinite; }
        .cart-toast.show { animation: agriPopIn .4s cubic-bezier(.34,1.56,.64,1); }
    </style>
</head>
<body>

<script>document.documentElement.classList.add('agri-anim-ready');</script>

<?php 
$current_page = basename($_SERVER['PHP_SELF']); 
?>

<div id="flash-bar">
    <div class="marquee-outer">
        <div class="marquee-inner" id="marquee-track" data-page="<?php echo basename($_SERVER['PHP_SELF']); ?>">
        </div>
    </div>
</div>

<header id="main-header">
    <a href="<?php echo $base_path; ?>/index.php" class="nav-logo">
        <img src="<?php echo $base_path; ?>/assets/images/agricart-logo.png?v=<?php echo file_exists(__DIR__.'/../assets/images/agricart-logo.png') ? filemtime(__DIR__.'/../assets/images/agricart-logo.png') : time(); ?>" alt="AgriCart" class="nav-logo-img">
        <span class="nav-logo-txt"><span class="agri">Agri</span><span class="cart">Cart</span></span>
    </a>

    <button class="hamburger-btn" id="hamburgerBtn" onclick="toggleMobileMenu()" aria-label="Toggle navigation menu" aria-expanded="false">
        <i class="fa-solid fa-bars" id="hamburgerIcon"></i>
    </button>

    <div class="nav-collapse" id="navCollapse">
        <ul class="nav-menu">
            <li>
                <a href="<?php echo $base_path; ?>/index.php" id="nav-home" class="<?php echo ($current_page == 'index.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-house"></i> Home
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/about.php" id="nav-about" class="<?php echo ($current_page == 'about.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-circle-info"></i> About Us
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/marketplace.php" id="nav-store" class="<?php echo ($current_page == 'marketplace.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-cart-shopping"></i> Agri Store
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/rental.php" id="nav-rental" class="<?php echo ($current_page == 'rental.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-tractor"></i> Rental Hub
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/advisory.php" id="nav-advisory" class="<?php echo ($current_page == 'advisory.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-seedling"></i> Crop Advisory
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/krishi_bazaar.php" id="nav-bazaar" class="<?php echo ($current_page == 'krishi_bazaar.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-chart-line"></i> Krishi Bazaar
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/agri-connect.php" id="nav-chavdi" class="<?php echo ($current_page == 'agri-connect.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-users"></i> Agri Connect
                </a>
            </li>
            <li>
                <a href="<?php echo $base_path; ?>/pages/contact.php" id="nav-contact" class="<?php echo ($current_page == 'contact.php') ? 'active' : ''; ?>">
                    <i class="fa-solid fa-envelope"></i> Contact Us
                </a>
            </li>
        </ul>

        <div class="nav-right">
            <select class="lang-select" id="langSelector" onchange="switchLanguage(this.value)">
                <option value="en">English</option>
                <option value="mr">मराठी</option>
                <option value="hi">हिंदी</option>
            </select>

            <div class="user-profile-wrap">
                <div class="user-badge" onclick="toggleDropdown()">
                    <i class="fa-solid fa-circle-user"></i>
                    <span id="header-username"><?php echo $is_logged_in ? htmlspecialchars($display_name) : "Guest"; ?></span>
                    <i class="fa-solid fa-chevron-down" style="font-size:10px; opacity:0.6;"></i>
                </div>
                <div class="profile-dropdown" id="profileDropdown">
                    <div class="dropdown-header">
                        <i class="fa-solid fa-circle-user"></i> <span id="dd-account-label">Account</span>
                    </div>
                    <a href="#" class="dropdown-link" onclick="openModal(); return false;">
                        <i class="fa-solid fa-id-card"></i> <span id="dd-my-profile">My Profile</span>
                    </a>
                    <?php if($is_logged_in): ?>
                        <a href="<?php echo $base_path; ?>/pages/my_activity.php" class="dropdown-link">
                            <i class="fa-solid fa-clipboard-list"></i> <span id="dd-my-activity">My Activity</span>
                        </a>
                        <?php if ($is_seller): ?>
                        <a href="<?php echo $base_path; ?>/seller/dashboard.php" class="dropdown-link">
                            <i class="fa-solid fa-shop"></i> <span id="dd-seller-dashboard">Seller Dashboard</span>
                        </a>
                        <?php else: ?>
                        <a href="<?php echo $base_path; ?>/pages/become_seller.php" class="dropdown-link">
                            <i class="fa-solid fa-shop"></i> <span id="dd-become-seller">Become a Seller</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($is_seller && in_array($seller_type, ['product','both'], true)): ?>
                        <a href="<?php echo $base_path; ?>/pages/sell_product.php" class="dropdown-link">
                            <i class="fa-solid fa-basket-shopping"></i> <span id="dd-sell-product">Sell Your Produce</span>
                        </a>
                        <?php endif; ?>
                        <?php if ($is_seller && in_array($seller_type, ['rental','both'], true)): ?>
                        <a href="<?php echo $base_path; ?>/pages/list_equipment.php" class="dropdown-link">
                            <i class="fa-solid fa-tractor"></i> <span id="dd-list-equipment">Rent Out Equipment</span>
                        </a>
                        <?php endif; ?>
                        <a href="<?php echo $base_path; ?>/pages/logout.php" class="dropdown-logout">
                            <i class="fa-solid fa-right-from-bracket"></i> <span id="dd-logout">Logout</span>
                        </a>
                    <?php else: ?>
                        <a href="<?php echo $base_path; ?>/pages/login.php" class="dropdown-link" style="color:#2E7D32; font-weight:700;">
                            <i class="fa-solid fa-key"></i> <span id="dd-login">Login</span>
                        </a>
                        <a href="<?php echo $base_path; ?>/pages/register.php" class="dropdown-link" style="color:#1a7a3e; font-weight:700;">
                            <i class="fa-solid fa-user-plus"></i> <span id="dd-register">Register</span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</header>

<div class="nav-overlay" id="navOverlay" onclick="closeMobileMenu()"></div>

<div class="profile-modal" id="profileModal">
    <div class="modal-card">
        <div class="modal-top">
            <span class="close-btn" onclick="closeModal()">&times;</span>
            <div class="modal-avatar" id="modal-avatar">
                <?php if ($profile_photo_path !== ''): ?>
                    <img src="<?php echo $base_path . '/' . htmlspecialchars($profile_photo_path); ?>" alt="" id="modal-avatar-img">
                <?php else: ?>
                    <i class="fa-solid fa-user" id="modal-avatar-icon"></i>
                <?php endif; ?>
            </div>
            <h3 id="modal-name"><?php echo $is_logged_in ? htmlspecialchars($display_name) : "Guest User"; ?></h3>
            <p id="modal-role-text"><?php echo $is_logged_in ? htmlspecialchars($profile_role) . ($profile_farmer_type ? ' · ' . htmlspecialchars($profile_farmer_type) : '') : "Your AgriCart Account"; ?></p>
        </div>
        <div class="modal-body">
            <div id="profileMsg" style="display:none; font-size:12.5px; font-weight:600; padding:8px 10px; border-radius:8px; margin-bottom:12px;"></div>

            <?php if ($is_logged_in): ?>
            <!-- ===================== VIEW MODE ===================== -->
            <div id="profileViewMode">
                <div class="profile-info-grid">
                    <div class="info-chip">
                        <i class="fa-solid fa-envelope"></i>
                        <div><span id="info-lbl-email">Email</span><b id="view-email"><?php echo $profile_email !== '' ? htmlspecialchars($profile_email) : '—'; ?></b></div>
                    </div>
                    <div class="info-chip">
                        <i class="fa-solid fa-mobile-screen"></i>
                        <div><span id="info-lbl-mobile-chip">Mobile Number</span><b id="view-mobile"><?php echo $profile_mobile !== '' ? htmlspecialchars($profile_mobile) : '—'; ?></b></div>
                    </div>
                    <div class="info-chip">
                        <i class="fa-solid fa-seedling"></i>
                        <div><span id="info-lbl-crop">Primary Crop</span><b id="view-crop"><?php echo $profile_crop !== '' ? htmlspecialchars($profile_crop) : '—'; ?></b></div>
                    </div>
                    <div class="info-chip">
                        <i class="fa-solid fa-user-tag"></i>
                        <div><span id="info-lbl-acctype">Account Type</span><b><?php echo htmlspecialchars($profile_farmer_type !== '' ? $profile_farmer_type : $profile_role); ?></b></div>
                    </div>
                    <div class="info-chip">
                        <i class="fa-solid fa-calendar-check"></i>
                        <div><span id="info-lbl-membersince">Member Since</span><b><?php echo $profile_member_since !== '' ? htmlspecialchars($profile_member_since) : '—'; ?></b></div>
                    </div>
                    <div class="info-chip info-chip-wide">
                        <i class="fa-solid fa-location-dot"></i>
                        <div><span id="info-lbl-address">Delivery Address</span><b id="view-address"><?php echo $profile_address !== '' ? htmlspecialchars($profile_address) : '—'; ?></b></div>
                    </div>
                </div>
                <button class="edit-profile-btn" id="editProfileBtn" onclick="enterEditMode()"><i class="fa-solid fa-pen"></i> <span id="editProfileBtnText">Edit Profile</span></button>
            </div>

            <!-- ===================== EDIT MODE ===================== -->
            <div id="profileEditMode" style="display:none;">
                <?php echo csrf_field(); ?>

                <div class="photo-edit-row">
                    <div class="photo-edit-preview" id="photoPreviewWrap">
                        <?php if ($profile_photo_path !== ''): ?>
                            <img src="<?php echo $base_path . '/' . htmlspecialchars($profile_photo_path); ?>" alt="" id="photoPreviewImg">
                        <?php else: ?>
                            <i class="fa-solid fa-user" id="photoPreviewIcon"></i>
                        <?php endif; ?>
                    </div>
                    <div>
                        <label class="photo-upload-btn" for="input-photo"><i class="fa-solid fa-camera"></i> <span id="lbl-changephoto">Change Photo</span></label>
                        <input type="file" id="input-photo" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <p class="photo-hint" id="photoHint">JPG, PNG or WebP. Max 2MB.</p>
                    </div>
                </div>

                <div class="form-group">
                    <label id="lbl-fullname">Full Name</label>
                    <input type="text" id="input-name" placeholder="Your full name" value="<?php echo htmlspecialchars($display_name); ?>">
                </div>
                <div class="form-group">
                    <label id="lbl-email">Email Address</label>
                    <input type="email" id="input-email" placeholder="your@email.com" value="<?php echo htmlspecialchars($profile_email); ?>">
                </div>
                <div class="form-group">
                    <label id="lbl-mobile">Mobile Number</label>
                    <input type="text" id="input-mobile" placeholder="10-digit mobile number" maxlength="10" inputmode="numeric" value="<?php echo htmlspecialchars($profile_mobile); ?>">
                </div>

                <div class="form-group">
                    <label id="lbl-crop">Primary Crop</label>
                    <div class="searchable-select" id="cropSelectBox">
                        <input type="text" id="input-crop-search" class="searchable-select-input" autocomplete="off" placeholder="Search crop..." value="<?php echo htmlspecialchars($profile_crop); ?>">
                        <input type="hidden" id="input-crop" value="<?php echo htmlspecialchars($profile_crop); ?>">
                        <div class="searchable-select-panel" id="cropSelectPanel">
                            <?php foreach (agri_crop_options_for_js($profile_crop) as $c): ?>
                                <div class="searchable-select-option" data-value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label id="lbl-acctype-edit">Account Type</label>
                    <div class="readonly-badge"><i class="fa-solid fa-lock"></i> <?php echo htmlspecialchars($profile_farmer_type !== '' ? $profile_farmer_type : $profile_role); ?></div>
                    <p class="field-hint" id="acctypeHint">Account type is managed by AgriCart support and can't be changed here.</p>
                </div>

                <div class="address-section-title" id="lbl-address-section">Delivery Address</div>
                <div class="address-grid">
                    <div class="form-group span-2">
                        <label id="lbl-line1">Address Line 1</label>
                        <input type="text" id="input-line1" placeholder="House no., street" value="<?php echo htmlspecialchars($profile_line1); ?>">
                    </div>
                    <div class="form-group span-2">
                        <label id="lbl-line2">Address Line 2</label>
                        <input type="text" id="input-line2" placeholder="Landmark, area (optional)" value="<?php echo htmlspecialchars($profile_line2); ?>">
                    </div>
                    <div class="form-group">
                        <label id="lbl-village">Village / Area</label>
                        <input type="text" id="input-village" value="<?php echo htmlspecialchars($profile_village); ?>">
                    </div>
                    <div class="form-group">
                        <label id="lbl-city">City</label>
                        <input type="text" id="input-city" value="<?php echo htmlspecialchars($profile_city); ?>">
                    </div>
                    <div class="form-group">
                        <label id="lbl-district">District</label>
                        <input type="text" id="input-district" value="<?php echo htmlspecialchars($profile_district); ?>">
                    </div>
                    <div class="form-group">
                        <label id="lbl-state">State</label>
                        <input type="text" id="input-state" value="<?php echo htmlspecialchars($profile_state !== '' ? $profile_state : 'Maharashtra'); ?>">
                    </div>
                    <div class="form-group span-2">
                        <label id="lbl-pincode">Pincode</label>
                        <input type="text" id="input-pincode" maxlength="6" inputmode="numeric" value="<?php echo htmlspecialchars($profile_pincode); ?>">
                    </div>
                </div>

                <a href="#" id="togglePwdBtn" onclick="togglePasswordSection(); return false;" style="display:inline-flex; align-items:center; gap:6px; font-size:12.5px; font-weight:700; color:#2E7D32; margin-bottom:10px; text-decoration:none;">
                    <i class="fa-solid fa-lock" id="togglePwdIcon"></i> <span id="togglePwdText">Change Password</span>
                </a>

                <div id="passwordSection" style="display:none;">
                    <div class="form-group">
                        <label id="lbl-currentpwd">Current Password</label>
                        <input type="password" id="input-current-password" placeholder="Enter current password" autocomplete="current-password">
                    </div>
                    <div class="form-group">
                        <label id="lbl-newpwd">New Password</label>
                        <input type="password" id="input-new-password" placeholder="At least 6 characters" autocomplete="new-password">
                    </div>
                    <div class="form-group">
                        <label id="lbl-confirmpwd">Confirm New Password</label>
                        <input type="password" id="input-confirm-password" placeholder="Re-enter new password" autocomplete="new-password">
                    </div>
                    <p id="pwdHintText" style="font-size:11px; color:#7c8c7c; margin:-6px 0 14px;">Required only if you're changing your email or password</p>
                </div>

                <div class="edit-btn-row">
                    <button class="cancel-btn" id="cancelProfileBtn" onclick="cancelEditMode()">Cancel</button>
                    <button class="save-btn" id="saveProfileBtn" onclick="saveProfile()">Save Changes</button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ===================== MY HISTORY MODAL (orders + rental bookings) ===================== -->
<div class="history-modal" id="historyModal">
    <div class="history-card">
        <div class="history-top">
            <span class="close-btn" onclick="closeHistory()">&times;</span>
            <h3><i class="fa-solid fa-clock-rotate-left"></i> My History</h3>
            <p>Everything you've ordered and booked on AgriCart</p>
        </div>
        <div class="history-tabs">
            <button class="history-tab active" id="tab-btn-orders" onclick="switchHistoryTab('orders')"><i class="fa-solid fa-cart-shopping"></i> Orders</button>
            <button class="history-tab" id="tab-btn-bookings" onclick="switchHistoryTab('bookings')"><i class="fa-solid fa-tractor"></i> Rentals</button>
        </div>
        <div class="history-body" id="historyBody">
            <div class="history-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading your history...</div>
        </div>
    </div>
</div>

<script>
// ========== HEADER TRANSLATION DATA ==========
const HeaderT = {
    en: {
        navHome: "Home", navStore: "Agri Store", navRental: "Rental Hub",
        navAdvisory: "Crop Advisory", navBazaar: "Krishi Bazaar",
        navConnect: "Agri Connect", navAbout: "About Us", navContact: "Contact Us",
        guestLabel: "Guest",
        profile: {
            account: "Account", myProfile: "My Profile", myActivity: "My Activity", sellerDashboard: "Seller Dashboard",
            sellProduct: "Sell Your Produce", listEquipment: "Rent Out Equipment",
            logout: "Logout", login: "Login", register: "Register",
            guestUser: "Guest User", yourAccount: "Your AgriCart Account",
            infoEmail: "Email", infoCrop: "Primary Crop", infoAccType: "Account Type", infoMemberSince: "Member Since",
            lblFullName: "Full Name", lblEmail: "Email Address", lblMobile: "Mobile Number", lblAddress: "Delivery Address",
            phFullName: "Your full name", phEmail: "your@email.com", phMobile: "+91 XXXXXXXXXX", phAddress: "Village, Taluka, District, PIN...",
            changePwdToggle: "Change Password", changePwdToggleClose: "Cancel Password Change",
            lblCurrentPwd: "Current Password", lblNewPwd: "New Password", lblConfirmPwd: "Confirm New Password",
            phCurrentPwd: "Enter current password", phNewPwd: "At least 6 characters", phConfirmPwd: "Re-enter new password",
            pwdHint: "Enter your current password to confirm this change",
            saveBtn: "Save Changes", saving: "Saving...",
            errName: "Please enter your name.", errMobile: "Please enter a valid 10-digit mobile number.",
            errEmail: "Please enter a valid email address.", errEmailTaken: "This email is already registered with another account.",
            errWrongPassword: "Current password is incorrect.", errPwdShort: "New password must be at least 6 characters.",
            errPwdMismatch: "New password and confirm password do not match.", errPwdRequired: "Please enter your current password to change email or password.",
            errSaveFailed: "Could not save profile. Please try again.", errLoginFirst: "Please login first.",
            errNetwork: "Network error. Please try again.", successMsg: "Profile updated successfully!"
        },
        flash: {
            'index.php': [
                {i:'fa-solid fa-fire', t:'Welcome to AgriCart! Get 10% off on your first order.'},
                {i:'fa-solid fa-seedling', t:'New: Mahabeej Hybrid Seeds now in stock!'},
                {i:'fa-solid fa-store', t:'Explore 500+ organic products.'},
                {i:'fa-solid fa-cloud-showers-heavy', t:'Monsoon Sale: Extra 5% off on all seeds this week!'},
                {i:'fa-solid fa-gift', t:'Refer a friend & earn ₹100 AgriCash!'},
                {i:'fa-solid fa-id-card', t:'New users get a free Soil Health Card!'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'marketplace.php': [
                {i:'fa-solid fa-fire', t:'Flash Offer: 15% off on Organic NPK Fertilizers!'},
                {i:'fa-solid fa-truck-fast', t:'Free delivery on orders above ₹1,999.'},
                {i:'fa-solid fa-shield-check', t:'Quality Assured: Certified seeds available.'},
                {i:'fa-solid fa-gift', t:'Buy 2 Get 1 Free on select pesticides!'},
                {i:'fa-solid fa-wallet', t:'Get cashback up to ₹200 on UPI payments!'},
                {i:'fa-solid fa-user-check', t:'100% Verified Sellers, quality guaranteed.'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'rental.php': [
                {i:'fa-solid fa-tractor', t:'Rental Deal: Rent tractors for just ₹500/hour. First 10 hours at 10% off!'},
                {i:'fa-solid fa-clock', t:'24/7 Booking Available.'},
                {i:'fa-solid fa-helicopter', t:'New: Drone spraying now available in 12 districts!'},
                {i:'fa-solid fa-layer-group', t:'Combo Offer: Tractor + Rotavator at 12% off!'},
                {i:'fa-solid fa-shield-halved', t:'Zero deposit on select insured equipment.'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'advisory.php': [
                {i:'fa-solid fa-seedling', t:'Expert Advice: Free crop consultation for Kharif season!'},
                {i:'fa-solid fa-microscope', t:'AI-based disease diagnosis in 30 seconds.'},
                {i:'fa-solid fa-flask', t:'Book a free soil-testing camp near you!'},
                {i:'fa-solid fa-chalkboard-user', t:'Free webinar on pest control this Sunday!'},
                {i:'fa-solid fa-vial', t:'Get instant fertilizer dosage recommendations.'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'krishi_bazaar.php': [
                {i:'fa-solid fa-chart-line', t:'Live Market: Get best prices for your wheat and cotton today!'},
                {i:'fa-solid fa-handshake', t:'Direct access to 100+ APMC buyers.'},
                {i:'fa-solid fa-arrow-trend-up', t:'Onion prices up 8% this week — sell now!'},
                {i:'fa-solid fa-scale-balanced', t:'Compare rates across 20+ mandis instantly.'},
                {i:'fa-solid fa-bell', t:'Set price alerts and never miss the best rate!'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'agri-connect.php': [
                {i:'fa-solid fa-comments', t:'Community: Join the discussion and win monthly AgriCart rewards!'},
                {i:'fa-solid fa-users', t:'10,000+ farmers connected.'},
                {i:'fa-solid fa-trophy', t:'Top contributor of the month wins a ₹1,000 voucher!'},
                {i:'fa-solid fa-comment-dots', t:'Ask experts, get answers within 24 hours!'},
                {i:'fa-solid fa-camera', t:'Share your farm photos and inspire others!'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'about.php': [
                {i:'fa-solid fa-award', t:'Proudly empowering Indian farmers with technology since day one!'},
                {i:'fa-solid fa-users', t:'50,000+ farmers trust AgriCart across Maharashtra.'},
                {i:'fa-solid fa-layer-group', t:'5 powerful tools, one single login.'},
                {i:'fa-solid fa-user-check', t:'Farmer-first, transparent by design.'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ],
            'contact.php': [
                {i:'fa-solid fa-envelope', t:'24/7 Farmer Helpline: 1800-419-8888. Call us for any farming assistance!'},
                {i:'fa-solid fa-message', t:'Live chat support now available 8 AM–10 PM!'},
                {i:'fa-brands fa-whatsapp', t:'WhatsApp support now live!'},
                {i:'fa-solid fa-stopwatch', t:'Average response time: under 2 hours.'}
            ],
            'default': [
                {i:'fa-solid fa-fire', t:'Special Offer: 15% off on Organic NPK Fertilizers!'},
                {i:'fa-solid fa-truck-fast', t:'Free delivery above ₹1,999.'},
                {i:'fa-solid fa-box-open', t:'Track your order in real-time from My Orders.'},
                {i:'fa-solid fa-mobile-screen-button', t:'Explore all AgriCart services in one app.'},
                {i:'fa-solid fa-star', t:'Trusted by farmers across Maharashtra.'},
                {i:'fa-solid fa-phone', t:'Helpline: 1800-419-8888'}
            ]
        }
    },
    mr: {
        navHome: "मुखपृष्ठ", navStore: "कृषी स्टोअर", navRental: "अवजारे केंद्र",
        navAdvisory: "पीक सल्ला", navBazaar: "कृषी बाजार",
        navConnect: "कृषी कनेक्ट", navAbout: "आमच्याबद्दल", navContact: "संपर्क",
        guestLabel: "पाहुणे",
        profile: {
            account: "खाते", myProfile: "माझी प्रोफाइल", myActivity: "माझे उपक्रम", sellerDashboard: "विक्रेता डॅशबोर्ड",
            sellProduct: "तुमचा शेतमाल विका", listEquipment: "अवजार भाड्याने द्या",
            logout: "लॉगआउट", login: "लॉगिन", register: "नोंदणी करा",
            guestUser: "पाहुणे युजर", yourAccount: "तुमचे AgriCart खाते",
            infoEmail: "ईमेल", infoCrop: "मुख्य पीक", infoAccType: "खाते प्रकार", infoMemberSince: "सदस्य पासून",
            lblFullName: "पूर्ण नाव", lblEmail: "ईमेल पत्ता", lblMobile: "मोबाईल नंबर", lblAddress: "डिलिव्हरी पत्ता",
            phFullName: "तुमचे पूर्ण नाव", phEmail: "your@email.com", phMobile: "+91 XXXXXXXXXX", phAddress: "गाव, तालुका, जिल्हा, पिन...",
            changePwdToggle: "पासवर्ड बदला", changePwdToggleClose: "पासवर्ड बदल रद्द करा",
            lblCurrentPwd: "सध्याचा पासवर्ड", lblNewPwd: "नवीन पासवर्ड", lblConfirmPwd: "नवीन पासवर्ड पुन्हा टाका",
            phCurrentPwd: "सध्याचा पासवर्ड टाका", phNewPwd: "किमान ६ अक्षरे", phConfirmPwd: "नवीन पासवर्ड पुन्हा टाका",
            pwdHint: "हा बदल पक्का करण्यासाठी सध्याचा पासवर्ड टाका",
            saveBtn: "बदल जतन करा", saving: "जतन करत आहे...",
            errName: "कृपया तुमचे नाव टाका.", errMobile: "कृपया वैध १०-अंकी मोबाईल नंबर टाका.",
            errEmail: "कृपया वैध ईमेल पत्ता टाका.", errEmailTaken: "हा ईमेल आधीच दुसऱ्या खात्याशी नोंदणीकृत आहे.",
            errWrongPassword: "सध्याचा पासवर्ड चुकीचा आहे.", errPwdShort: "नवीन पासवर्ड किमान ६ अक्षरांचा असावा.",
            errPwdMismatch: "नवीन पासवर्ड आणि कन्फर्म पासवर्ड जुळत नाहीत.", errPwdRequired: "ईमेल किंवा पासवर्ड बदलण्यासाठी कृपया सध्याचा पासवर्ड टाका.",
            errSaveFailed: "प्रोफाइल जतन करता आली नाही. पुन्हा प्रयत्न करा.", errLoginFirst: "कृपया आधी लॉगिन करा.",
            errNetwork: "नेटवर्क त्रुटी. कृपया पुन्हा प्रयत्न करा.", successMsg: "प्रोफाइल यशस्वीरित्या अद्ययावत झाली!"
        },
        flash: {
            'index.php': [
                {i:'fa-solid fa-fire', t:'ॲग्रीकार्ट मध्ये स्वागत! पहिल्या खरेदीवर १०% सूट.'},
                {i:'fa-solid fa-seedling', t:'नवीन: महाबीज हायब्रीड बियाणे स्टॉक मध्ये!'},
                {i:'fa-solid fa-store', t:'५०० हून अधिक सेंद्रिय उत्पादने पहा.'},
                {i:'fa-solid fa-cloud-showers-heavy', t:'मान्सून सेल: या आठवड्यात सर्व बियाण्यांवर अतिरिक्त ५% सूट!'},
                {i:'fa-solid fa-gift', t:'मित्राला रेफर करा आणि ₹१०० AgriCash मिळवा!'},
                {i:'fa-solid fa-id-card', t:'नवीन युजर्सला मोफत Soil Health Card!'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'marketplace.php': [
                {i:'fa-solid fa-fire', t:'ऑफर: सेंद्रिय NPK खतांवर १५% सूट!'},
                {i:'fa-solid fa-truck-fast', t:'₹१,९९९ पेक्षा जास्त खरेदीवर मोफत डिलिव्हरी.'},
                {i:'fa-solid fa-shield-check', t:'खात्रीशीर गुणवत्ता: प्रमाणित बियाणे.'},
                {i:'fa-solid fa-gift', t:'निवडक कीटकनाशकांवर २ घ्या १ मोफत!'},
                {i:'fa-solid fa-wallet', t:'UPI पेमेंटवर ₹२०० पर्यंत कॅशबॅक मिळवा!'},
                {i:'fa-solid fa-user-check', t:'१००% पडताळणी केलेले विक्रेते, हमखास गुणवत्ता.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'rental.php': [
                {i:'fa-solid fa-tractor', t:'विशेष डील: ट्रॅक्टर भाड्याने घ्या फक्त ₹५००/तास. पहिल्या १० तासांवर १०% सवलत!'},
                {i:'fa-solid fa-clock', t:'२४/७ बुकिंग सुविधा.'},
                {i:'fa-solid fa-helicopter', t:'नवीन: १२ जिल्ह्यांमध्ये ड्रोन फवारणी आता उपलब्ध!'},
                {i:'fa-solid fa-layer-group', t:'कॉम्बो ऑफर: ट्रॅक्टर + रोटाव्हेटर १२% सवलतीत!'},
                {i:'fa-solid fa-shield-halved', t:'निवडक विमा संरक्षित अवजारांवर शून्य डिपॉझिट.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'advisory.php': [
                {i:'fa-solid fa-seedling', t:'तज्ञांचा सल्ला: खरीप हंगामासाठी मोफत पीक सल्ला!'},
                {i:'fa-solid fa-microscope', t:'AI द्वारे रोगांचे निदान ३० सेकंदात.'},
                {i:'fa-solid fa-flask', t:'तुमच्या जवळ मोफत माती परीक्षण शिबिर बुक करा!'},
                {i:'fa-solid fa-chalkboard-user', t:'या रविवारी कीड नियंत्रणावर मोफत वेबिनार!'},
                {i:'fa-solid fa-vial', t:'खतांचा योग्य डोस त्वरित जाणून घ्या.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'krishi_bazaar.php': [
                {i:'fa-solid fa-chart-line', t:'लाईव्ह मार्केट: गहू आणि कापसाला आज सर्वोत्तम भाव मिळवा!'},
                {i:'fa-solid fa-handshake', t:'१००+ थेट खरेदीदारांशी संपर्क.'},
                {i:'fa-solid fa-arrow-trend-up', t:'कांद्याचे भाव या आठवड्यात ८% वाढले — आताच विका!'},
                {i:'fa-solid fa-scale-balanced', t:'२०+ मंडईंचे भाव एकाच ठिकाणी त्वरित पहा.'},
                {i:'fa-solid fa-bell', t:'प्राइस अलर्ट सेट करा, सर्वोत्तम भाव कधीच चुकवू नका!'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'agri-connect.php': [
                {i:'fa-solid fa-comments', t:'समुदाय: चर्चेत सामील व्हा आणि दरमहा ॲग्रीकार्ट बक्षिसे जिंका!'},
                {i:'fa-solid fa-users', t:'१०,०००+ शेतकरी जोडले गेले.'},
                {i:'fa-solid fa-trophy', t:'महिन्याचा सर्वोत्तम सहभागी जिंकतो ₹१,००० व्हाउचर!'},
                {i:'fa-solid fa-comment-dots', t:'तज्ञांना प्रश्न विचारा, २४ तासांत उत्तर मिळवा!'},
                {i:'fa-solid fa-camera', t:'तुमच्या शेताचे फोटो शेअर करा आणि इतरांना प्रेरणा द्या!'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'about.php': [
                {i:'fa-solid fa-award', t:'पहिल्या दिवसापासून तंत्रज्ञानाद्वारे भारतीय शेतकऱ्यांचे सक्षमीकरण!'},
                {i:'fa-solid fa-users', t:'महाराष्ट्रातील ५०,०००+ शेतकऱ्यांचा AgriCart वर विश्वास.'},
                {i:'fa-solid fa-layer-group', t:'५ शक्तिशाली सेवा, एकाच लॉगिनमध्ये.'},
                {i:'fa-solid fa-user-check', t:'शेतकरी-प्रथम, पारदर्शक कार्यपद्धती.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'contact.php': [
                {i:'fa-solid fa-envelope', t:'२४/७ शेतकरी हेल्पलाइन: 1800-419-8888. आम्ही तुमच्या मदतीसाठी सदैव तत्पर आहोत!'},
                {i:'fa-solid fa-message', t:'आता Live chat support सकाळी ८ ते रात्री १० उपलब्ध!'},
                {i:'fa-brands fa-whatsapp', t:'आता WhatsApp सपोर्ट देखील उपलब्ध!'},
                {i:'fa-solid fa-stopwatch', t:'सरासरी प्रतिसाद वेळ: २ तासांपेक्षा कमी.'}
            ],
            'default': [
                {i:'fa-solid fa-fire', t:'फ्लॅश ऑफर: या आठवड्यात सेंद्रिय NPK खतांवर १५% सूट!'},
                {i:'fa-solid fa-box-open', t:'तुमची ऑर्डर My Orders मध्ये रिअल-टाइम ट्रॅक करा.'},
                {i:'fa-solid fa-mobile-screen-button', t:'AgriCart च्या सर्व सेवा एका अ‍ॅपमध्ये पहा.'},
                {i:'fa-solid fa-star', t:'महाराष्ट्रातील शेतकऱ्यांचा विश्वासू पर्याय.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ]
        }
    },
    hi: {
        navHome: "होम", navStore: "एग्री स्टोर", navRental: "किराया केंद्र",
        navAdvisory: "फसल सलाह", navBazaar: "कृषि बाज़ार",
        navConnect: "एग्री कनेक्ट", navAbout: "हमारे बारे में", navContact: "संपर्क करें",
        guestLabel: "अतिथि",
        profile: {
            account: "खाता", myProfile: "मेरी प्रोफ़ाइल", myActivity: "मेरी गतिविधि", sellerDashboard: "विक्रेता डैशबोर्ड",
            sellProduct: "अपनी उपज बेचें", listEquipment: "उपकरण किराए पर दें",
            logout: "लॉगआउट", login: "लॉगिन", register: "रजिस्टर करें",
            guestUser: "गेस्ट यूज़र", yourAccount: "आपका AgriCart खाता",
            infoEmail: "ईमेल", infoCrop: "मुख्य फसल", infoAccType: "खाता प्रकार", infoMemberSince: "सदस्य बने",
            lblFullName: "पूरा नाम", lblEmail: "ईमेल पता", lblMobile: "मोबाइल नंबर", lblAddress: "डिलीवरी पता",
            phFullName: "अपना पूरा नाम", phEmail: "your@email.com", phMobile: "+91 XXXXXXXXXX", phAddress: "गांव, तालुका, जिला, पिन...",
            changePwdToggle: "पासवर्ड बदलें", changePwdToggleClose: "पासवर्ड बदलना रद्द करें",
            lblCurrentPwd: "मौजूदा पासवर्ड", lblNewPwd: "नया पासवर्ड", lblConfirmPwd: "नया पासवर्ड फिर से डालें",
            phCurrentPwd: "मौजूदा पासवर्ड डालें", phNewPwd: "कम से कम 6 अक्षर", phConfirmPwd: "नया पासवर्ड फिर से डालें",
            pwdHint: "इस बदलाव की पुष्टि के लिए मौजूदा पासवर्ड डालें",
            saveBtn: "बदलाव सेव करें", saving: "सेव हो रहा है...",
            errName: "कृपया अपना नाम डालें.", errMobile: "कृपया मान्य 10-अंकों का मोबाइल नंबर डालें.",
            errEmail: "कृपया मान्य ईमेल पता डालें.", errEmailTaken: "यह ईमेल पहले से किसी अन्य खाते से पंजीकृत है.",
            errWrongPassword: "मौजूदा पासवर्ड गलत है.", errPwdShort: "नया पासवर्ड कम से कम 6 अक्षरों का होना चाहिए.",
            errPwdMismatch: "नया पासवर्ड और कन्फर्म पासवर्ड मेल नहीं खाते.", errPwdRequired: "ईमेल या पासवर्ड बदलने के लिए कृपया मौजूदा पासवर्ड डालें.",
            errSaveFailed: "प्रोफ़ाइल सेव नहीं हो सकी. कृपया फिर से प्रयास करें.", errLoginFirst: "कृपया पहले लॉगिन करें.",
            errNetwork: "नेटवर्क त्रुटि. कृपया फिर से प्रयास करें.", successMsg: "प्रोफ़ाइल सफलतापूर्वक अपडेट हुई!"
        },
        flash: {
            'index.php': [
                {i:'fa-solid fa-fire', t:'AgriCart में आपका स्वागत है! पहली खरीद पर 10% छूट.'},
                {i:'fa-solid fa-seedling', t:'नया: महाबीज हाइब्रिड बीज अब स्टॉक में!'},
                {i:'fa-solid fa-store', t:'500+ जैविक उत्पाद देखें.'},
                {i:'fa-solid fa-cloud-showers-heavy', t:'मानसून सेल: इस सप्ताह सभी बीजों पर अतिरिक्त 5% छूट!'},
                {i:'fa-solid fa-gift', t:'दोस्त को रेफर करें और ₹100 AgriCash पाएं!'},
                {i:'fa-solid fa-id-card', t:'नए यूज़र्स को मुफ्त Soil Health Card!'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'marketplace.php': [
                {i:'fa-solid fa-fire', t:'ऑफर: जैविक NPK खाद पर 15% छूट!'},
                {i:'fa-solid fa-truck-fast', t:'₹1,999 से अधिक खरीद पर मुफ्त डिलीवरी.'},
                {i:'fa-solid fa-shield-check', t:'गुणवत्ता आश्वासन: प्रमाणित बीज उपलब्ध.'},
                {i:'fa-solid fa-gift', t:'चुनिंदा कीटनाशकों पर 2 खरीदें 1 मुफ्त पाएं!'},
                {i:'fa-solid fa-wallet', t:'UPI पेमेंट पर ₹200 तक कैशबैक पाएं!'},
                {i:'fa-solid fa-user-check', t:'100% सत्यापित विक्रेता, गुणवत्ता की गारंटी.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'rental.php': [
                {i:'fa-solid fa-tractor', t:'किराया डील: ट्रैक्टर सिर्फ ₹500/घंटा. पहले 10 घंटे 10% छूट!'},
                {i:'fa-solid fa-clock', t:'24/7 बुकिंग सुविधा.'},
                {i:'fa-solid fa-helicopter', t:'नया: अब 12 जिलों में ड्रोन स्प्रेइंग उपलब्ध!'},
                {i:'fa-solid fa-layer-group', t:'कॉम्बो ऑफर: ट्रैक्टर + रोटावेटर 12% छूट में!'},
                {i:'fa-solid fa-shield-halved', t:'चुनिंदा बीमित उपकरणों पर शून्य डिपॉज़िट.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'advisory.php': [
                {i:'fa-solid fa-seedling', t:'विशेषज्ञ सलाह: खरीफ सीजन के लिए मुफ्त फसल परामर्श!'},
                {i:'fa-solid fa-microscope', t:'AI से 30 सेकंड में रोग निदान.'},
                {i:'fa-solid fa-flask', t:'अपने पास मुफ्त मिट्टी परीक्षण शिविर बुक करें!'},
                {i:'fa-solid fa-chalkboard-user', t:'इस रविवार कीट नियंत्रण पर मुफ्त वेबिनार!'},
                {i:'fa-solid fa-vial', t:'खाद की सही मात्रा की सिफारिश तुरंत पाएं.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'krishi_bazaar.php': [
                {i:'fa-solid fa-chart-line', t:'लाइव मार्केट: आज गेहूं और कपास का सबसे अच्छा भाव पाएं!'},
                {i:'fa-solid fa-handshake', t:'100+ APMC खरीदारों से सीधा संपर्क.'},
                {i:'fa-solid fa-arrow-trend-up', t:'प्याज के भाव इस सप्ताह 8% बढ़े — अभी बेचें!'},
                {i:'fa-solid fa-scale-balanced', t:'20+ मंडियों के भाव एक साथ तुरंत देखें.'},
                {i:'fa-solid fa-bell', t:'प्राइस अलर्ट सेट करें, सबसे अच्छा भाव कभी न चूकें!'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'agri-connect.php': [
                {i:'fa-solid fa-comments', t:'समुदाय: चर्चा में जुड़ें और AgriCart पुरस्कार जीतें!'},
                {i:'fa-solid fa-users', t:'10,000+ किसान जुड़े हुए.'},
                {i:'fa-solid fa-trophy', t:'महीने का टॉप योगदानकर्ता जीते ₹1,000 का वाउचर!'},
                {i:'fa-solid fa-comment-dots', t:'विशेषज्ञों से पूछें, 24 घंटे में जवाब पाएं!'},
                {i:'fa-solid fa-camera', t:'अपने खेत की फोटो शेयर करें और दूसरों को प्रेरित करें!'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'about.php': [
                {i:'fa-solid fa-award', t:'पहले दिन से तकनीक के जरिए भारतीय किसानों का सशक्तिकरण!'},
                {i:'fa-solid fa-users', t:'महाराष्ट्र के 50,000+ किसानों का AgriCart पर भरोसा.'},
                {i:'fa-solid fa-layer-group', t:'5 शक्तिशाली सेवाएं, एक ही लॉगिन में.'},
                {i:'fa-solid fa-user-check', t:'किसान-पहले, पारदर्शी कार्यशैली.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ],
            'contact.php': [
                {i:'fa-solid fa-envelope', t:'24/7 किसान हेल्पलाइन: 1800-419-8888. हम आपकी मदद के लिए हमेशा तैयार हैं!'},
                {i:'fa-solid fa-message', t:'अब लाइव चैट सपोर्ट सुबह 8 से रात 10 बजे तक उपलब्ध!'},
                {i:'fa-brands fa-whatsapp', t:'अब WhatsApp सपोर्ट भी उपलब्ध!'},
                {i:'fa-solid fa-stopwatch', t:'औसत प्रतिक्रिया समय: 2 घंटे से कम.'}
            ],
            'default': [
                {i:'fa-solid fa-fire', t:'फ्लैश ऑफर: जैविक NPK खाद पर 15% छूट!'},
                {i:'fa-solid fa-truck-fast', t:'₹1,999 से ऊपर मुफ्त डिलीवरी.'},
                {i:'fa-solid fa-box-open', t:'My Orders में अपना ऑर्डर रियल-टाइम ट्रैक करें.'},
                {i:'fa-solid fa-mobile-screen-button', t:'AgriCart की सभी सेवाएं एक ही ऐप में देखें.'},
                {i:'fa-solid fa-star', t:'महाराष्ट्र के किसानों का भरोसेमंद साथी.'},
                {i:'fa-solid fa-phone', t:'हेल्पलाइन: 1800-419-8888'}
            ]
        }
    }
};

function updateHeaderTranslation(lang) {
    const t = HeaderT[lang] || HeaderT['en'];

    // Nav menu items
    const navHome = document.getElementById('nav-home');
    const navStore = document.getElementById('nav-store');
    const navRental = document.getElementById('nav-rental');
    const navAdvisory = document.getElementById('nav-advisory');
    const navBazaar = document.getElementById('nav-bazaar');
    const navConnect = document.getElementById('nav-chavdi');
    const navAbout = document.getElementById('nav-about');
    const navContact = document.getElementById('nav-contact');

    if (navHome) navHome.innerHTML = `<i class="fa-solid fa-house"></i> ${t.navHome}`;
    if (navStore) navStore.innerHTML = `<i class="fa-solid fa-cart-shopping"></i> ${t.navStore}`;
    if (navRental) navRental.innerHTML = `<i class="fa-solid fa-tractor"></i> ${t.navRental}`;
    if (navAdvisory) navAdvisory.innerHTML = `<i class="fa-solid fa-seedling"></i> ${t.navAdvisory}`;
    if (navBazaar) navBazaar.innerHTML = `<i class="fa-solid fa-chart-line"></i> ${t.navBazaar}`;
    if (navConnect) navConnect.innerHTML = `<i class="fa-solid fa-users"></i> ${t.navConnect}`;
    if (navAbout) navAbout.innerHTML = `<i class="fa-solid fa-circle-info"></i> ${t.navAbout}`;
    if (navContact) navContact.innerHTML = `<i class="fa-solid fa-envelope"></i> ${t.navContact}`;

    // Account dropdown + "My Profile" modal
    const p = t.profile || {};
    window.AGRI_PROFILE_T = p;
    const setText = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.textContent = val; };
    const setPh   = (id, val) => { const el = document.getElementById(id); if (el && val !== undefined) el.placeholder = val; };

    setText('dd-account-label', p.account);
    setText('dd-my-profile', p.myProfile);
    setText('dd-my-activity', p.myActivity);
    setText('dd-seller-dashboard', p.sellerDashboard);
    setText('dd-sell-product', p.sellProduct);
    setText('dd-list-equipment', p.listEquipment);
    setText('dd-logout', p.logout);
    setText('dd-login', p.login);
    setText('dd-register', p.register);

    // Guest label in the header badge (only overwrite when actually a guest)
    const headerUsername = document.getElementById('header-username');
    if (headerUsername && !window.AGRI_LOGGED_IN) headerUsername.textContent = t.guestLabel;

    // Profile modal header
    if (!window.AGRI_LOGGED_IN) {
        setText('modal-name', p.guestUser);
        setText('modal-role-text', p.yourAccount);
    }

    setText('info-lbl-email', p.infoEmail);
    setText('info-lbl-crop', p.infoCrop);
    setText('info-lbl-acctype', p.infoAccType);
    setText('info-lbl-membersince', p.infoMemberSince);

    setText('lbl-fullname', p.lblFullName);
    setText('lbl-email', p.lblEmail);
    setText('lbl-mobile', p.lblMobile);
    setText('lbl-address', p.lblAddress);
    setPh('input-name', p.phFullName);
    setPh('input-email', p.phEmail);
    setPh('input-mobile', p.phMobile);
    setPh('input-address', p.phAddress);

    const pwdSection = document.getElementById('passwordSection');
    const pwdOpen = pwdSection && pwdSection.style.display !== 'none';
    setText('togglePwdText', pwdOpen ? p.changePwdToggleClose : p.changePwdToggle);
    setText('lbl-currentpwd', p.lblCurrentPwd);
    setText('lbl-newpwd', p.lblNewPwd);
    setText('lbl-confirmpwd', p.lblConfirmPwd);
    setPh('input-current-password', p.phCurrentPwd);
    setPh('input-new-password', p.phNewPwd);
    setPh('input-confirm-password', p.phConfirmPwd);
    setText('pwdHintText', p.pwdHint);

    const saveBtn = document.getElementById('saveProfileBtn');
    if (saveBtn && !saveBtn.disabled) saveBtn.textContent = p.saveBtn;

    // Flash bar marquee — rebuild with correct language & current page's offers
    const track = document.getElementById('marquee-track');
    if (track) {
        const currentPage = track.getAttribute('data-page') || 'index.php';
        const items = (t.flash && (t.flash[currentPage] || t.flash['default'])) || [];
        const sep = '<span class="separator">|</span>';
        const msgs = items.map(it => `<span><i class="${it.i}"></i> ${it.t}</span>`);
        // Build content, inject, then measure and animate
        // Single copy — enters from right, exits left, then repeats
        track.innerHTML = msgs.length ? (msgs.join(sep) + sep) : '';
        // Delay restart so agri-master.js switchLanguage doesn't override it
        track.style.animation = 'none';
        track.style.transform = '';
        void track.offsetWidth;
        setTimeout(() => {
            track.style.animation = 'none';
            void track.offsetWidth;
            track.style.animation = 'mqScroll 32s linear infinite';
        }, 30);
    }
}

function switchLanguage(lang) {
    localStorage.setItem('agri_lang', lang);

    // Update selector to match saved lang
    const sel = document.getElementById('langSelector');
    if (sel) {
        sel.value = lang;
        // Force an immediate repaint — some browsers don't visually refresh
        // a custom-styled <select>'s box after a programmatic value change
        // until the next layout event (e.g. a page refresh), leaving the
        // pill looking subtly different until then.
        sel.style.display = 'none';
        void sel.offsetHeight;
        sel.style.display = '';
    }

    // Translate header
    updateHeaderTranslation(lang);

    // Call page-specific translation if available
    if (typeof pageLanguageCallback === 'function') {
        pageLanguageCallback(lang);
    }
}

// On page load — apply saved language
document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    const sel = document.getElementById('langSelector');
    if (sel) sel.value = savedLang;
    updateHeaderTranslation(savedLang);
    // Safety re-sync in case another script races the DOM after load
    setTimeout(() => updateHeaderTranslation(localStorage.getItem('agri_lang') || 'en'), 250);
    // pageLanguageCallback is called by each page's own DOMContentLoaded
});

// ========== MOBILE HAMBURGER NAVIGATION DRAWER ==========
function toggleMobileMenu() {
    const collapse = document.getElementById('navCollapse');
    const overlay  = document.getElementById('navOverlay');
    const btn      = document.getElementById('hamburgerBtn');
    const icon     = document.getElementById('hamburgerIcon');
    if (!collapse) return;
    const isOpen = collapse.classList.toggle('open');
    if (overlay) overlay.classList.toggle('show', isOpen);
    document.body.classList.toggle('no-scroll', isOpen);
    if (btn) btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    if (icon) icon.className = isOpen ? 'fa-solid fa-xmark' : 'fa-solid fa-bars';
}

function closeMobileMenu() {
    const collapse = document.getElementById('navCollapse');
    const overlay  = document.getElementById('navOverlay');
    const btn      = document.getElementById('hamburgerBtn');
    const icon     = document.getElementById('hamburgerIcon');
    if (collapse) collapse.classList.remove('open');
    if (overlay) overlay.classList.remove('show');
    document.body.classList.remove('no-scroll');
    if (btn) btn.setAttribute('aria-expanded', 'false');
    if (icon) icon.className = 'fa-solid fa-bars';
}

// Auto-close the drawer whenever a nav link inside it is tapped
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('#navCollapse .nav-menu a').forEach(a => {
        a.addEventListener('click', closeMobileMenu);
    });
});

// If the viewport is resized back to desktop width while the drawer is open, reset it
window.addEventListener('resize', () => {
    if (window.innerWidth > 992) closeMobileMenu();
});

// ========== RESPONSIVE TABLE WRAPPER (non-invasive) ==========
// Wraps every <table> on the page in a scrollable .agri-table-wrap div so
// wide tables (admin/seller reports, comparison tables, market rate
// tables, etc.) scroll horizontally inside themselves instead of
// breaking the page layout — no per-page markup changes required.
// Tables already inside a recognized wrapper (own page CSS already
// handles them) are left alone to avoid double-wrapping. A
// MutationObserver also catches tables injected later by AJAX (order
// history, rental dashboard tables, etc.) so this works the same
// whether a table is present on load or rendered after a fetch.
(function () {
    // Substring match instead of an exact class list — the codebase uses
    // several existing wrapper naming conventions (agi-table-wrap,
    // kb-table-wrap, lg-table-wrap, sd-table-wrap, km-tblwrap...); matching
    // on "wrap"/"scroll"/"responsive" catches those and any future ones
    // without needing to keep an exact list in sync.
    const SKIP_PATTERN = /wrap|scroll|responsive/i;
    function wrapTable(table) {
        if (table.closest('.agri-table-wrap')) return;
        const parent = table.parentElement;
        if (!parent) return;
        if ([...parent.classList].some(c => SKIP_PATTERN.test(c))) return;
        const wrap = document.createElement('div');
        wrap.className = 'agri-table-wrap';
        parent.insertBefore(wrap, table);
        wrap.appendChild(table);
    }
    function wrapAllTables(root) {
        (root || document).querySelectorAll('table').forEach(wrapTable);
    }
    document.addEventListener('DOMContentLoaded', () => wrapAllTables());
    const tableObserver = new MutationObserver((mutations) => {
        mutations.forEach(m => {
            m.addedNodes.forEach(node => {
                if (node.nodeType !== 1) return;
                if (node.tagName === 'TABLE') wrapTable(node);
                else if (node.querySelectorAll) wrapAllTables(node);
            });
        });
    });
    document.addEventListener('DOMContentLoaded', () => {
        tableObserver.observe(document.body, { childList: true, subtree: true });
    });
})();
</script>

<?php include_once __DIR__ . '/../pages/auth-modal.php'; ?>
<?php
// Cache-busting: append each file's last-modified time as a version query
// param, so browsers always fetch the latest JS after an update instead of
// serving a stale cached copy (a common cause of "I fixed it but it still
// shows the old behavior" during local development).
$authJsPath = __DIR__ . '/../assets/js/auth.js';
$masterJsPath = __DIR__ . '/../assets/js/agri-master.js';
$formScrollJsPath = __DIR__ . '/../assets/js/form-scroll-validate.js';
$authJsVer = file_exists($authJsPath) ? filemtime($authJsPath) : time();
$masterJsVer = file_exists($masterJsPath) ? filemtime($masterJsPath) : time();
$formScrollJsVer = file_exists($formScrollJsPath) ? filemtime($formScrollJsPath) : time();
?>
<script src="<?php echo $base_path; ?>/assets/js/auth.js?v=<?php echo $authJsVer; ?>"></script>
<script src="<?php echo $base_path; ?>/assets/js/agri-master.js?v=<?php echo $masterJsVer; ?>"></script>
<!-- Global smart form validation: auto-scrolls to + focuses the first
     invalid field on any form on the site. See assets/js/form-scroll-validate.js -->
<script src="<?php echo $base_path; ?>/assets/js/form-scroll-validate.js?v=<?php echo $formScrollJsVer; ?>"></script>