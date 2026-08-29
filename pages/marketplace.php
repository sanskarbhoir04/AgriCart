<?php
// =====================================================
// AgriCart — Marketplace (DB-connected: products, orders, order_items)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

// $base_path is normally set by header.php, but this code runs BEFORE
// header.php is included below, so it isn't defined yet at this point.
// Compute a safe fallback here from the current script location (this
// page lives in /<project>/pages/marketplace.php, so its parent folder
// is the project root) — this works regardless of what the project
// folder is named, so it isn't hardcoded to "AgriCart".
if (!isset($base_path)) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_path = rtrim(dirname($scriptDir), '/');
}

// Session-based CSRF token — generated once per session, reused for every
// state-changing POST (place_order.php, submit_review.php, validate_coupon.php).
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

$productRows = [];
// Farmer self-listings carry approval_status ('pending' until an admin
// approves them). Older rows / a not-yet-migrated DB may not have that
// column at all — but "column missing" and "something is actually wrong
// with the query" must NOT be treated the same way. If the column exists,
// we always filter by it. If we can't even confirm whether it exists, we
// fail safe and show nothing rather than risk leaking pending/rejected
// farmer listings (see SQL migration in the accompanying notes).
$hasApprovalCol = false;
try {
    $colCheck = $conn->query("SHOW COLUMNS FROM products LIKE 'approval_status'");
    $hasApprovalCol = ($colCheck && $colCheck->num_rows > 0);
} catch (\Throwable $eCol) {
    error_log('AgriCart marketplace: approval_status column check failed: ' . $eCol->getMessage());
}

// Single query for products + review aggregates (fixes the N+1 query that
// used to run one extra SELECT per product for its rating/review count).
// LIMIT below is a safety cap (spec §19 "avoid loading thousands of
// records at once") — the storefront's category/search filtering runs
// client-side over whatever's loaded, so this is a high ceiling that
// won't affect any realistic catalog size, not a true page size.
$reviewJoin = "LEFT JOIN reviews rv ON rv.item_type = 'product' AND rv.item_id = p.id";
$reviewSelect = "ROUND(AVG(rv.rating), 1) AS avg_rating, COUNT(rv.id) AS review_count";
if ($hasApprovalCol) {
    $productSql = "SELECT p.*, {$reviewSelect}
                    FROM products p {$reviewJoin}
                    WHERE p.is_active = 1 AND (p.approval_status IS NULL OR p.approval_status = 'approved')
                    GROUP BY p.id ORDER BY p.id
                    LIMIT 3000";
} else {
    // No approval workflow on this DB at all yet — every active product is
    // a store product by definition, so nothing pending can leak here.
    $productSql = "SELECT p.*, {$reviewSelect}
                    FROM products p {$reviewJoin}
                    WHERE p.is_active = 1
                    GROUP BY p.id ORDER BY p.id
                    LIMIT 3000";
}

$pResult = false;
try {
    $pResult = $conn->query($productSql);
    if ($pResult === false) { throw new \Exception($conn->error ?: 'unknown query error'); }
} catch (\Throwable $eProd) {
    error_log('AgriCart marketplace: product listing query failed: ' . $eProd->getMessage());
    $pResult = false; // Fail safe: show an empty catalogue rather than an unfiltered one.
}
if ($pResult) {
    while ($row = $pResult->fetch_assoc()) { $productRows[] = $row; }
}

// If a product's `image` column is empty, try to match it to one of the
// real product photos below by name keyword first (so e.g. "Hybrid Tomato
// Seeds" and "Onion Seeds" — both category 'seeds' — get their own correct
// photo instead of sharing one generic seeds.jpg). If no keyword matches,
// fall back to a generic category photo. Photos live in
// assets/images/products/ and paths are prefixed with $base_path so they
// resolve correctly no matter what folder the current page is in.
$productImageByKeyword = [
    'tomato seed'   => 'tomato-seeds.png',
    'onion seed'    => 'onion-seeds.png',
    'urea'          => 'urea-fertilizer.png',
    'dap'           => 'dap-fertilizer.png',
    'neem oil'      => 'neem-oil-pesticide.png',
    'sprayer'       => 'knapsack-sprayer.png',
    'drip irrigation' => 'drip-irrigation-pipe.png',
    'fresh tomato'  => 'fresh-tomatoes.png',
    'jaggery'       => 'organic-jaggery.png',
    'gul'           => 'organic-jaggery.png',
];
$categoryImageFallback = [
    'seeds'      => 'assets/images/products/seeds.jpg',
    'fertilizer' => 'assets/images/products/fertilizer.jpg',
    'pesticides' => 'assets/images/products/pesticides.jpg',
    'tools'      => 'assets/images/products/tools.jpg',
    'irrigation' => 'assets/images/products/irrigation.jpg',
    'feed'       => 'assets/images/products/feed.jpg',
    'organic'    => 'assets/images/products/organic.jpg',
    'cropkits'   => 'assets/images/products/cropkits.jpg',
];
$defaultProductImage = 'assets/images/products/default.jpg';

function resolveProductImage($row, $productImageByKeyword, $categoryImageFallback, $defaultProductImage, $base_path) {
    if (!empty($row['image'])) {
        if (preg_match('#^(https?:)?//#i', $row['image']) || strpos($row['image'], '/') === 0) {
            return $row['image'];
        }
        return rtrim($base_path, '/') . '/' . ltrim($row['image'], '/');
    }
    $haystack = strtolower(($row['name'] ?? '') . ' ' . ($row['name_mr'] ?? ''));
    foreach ($productImageByKeyword as $needle => $file) {
        if (strpos($haystack, $needle) !== false) {
            return rtrim($base_path, '/') . '/assets/images/products/' . $file;
        }
    }
    $fallback = $categoryImageFallback[$row['category']] ?? $defaultProductImage;
    return rtrim($base_path, '/') . '/' . $fallback;
}

$productsForJs = [];
foreach ($productRows as $row) {
    // avg_rating / review_count now come straight from the single JOIN query above.
    $avgRating = !empty($row['avg_rating']) ? round((float)$row['avg_rating'], 1) : 0;
    $reviewCount = (int)($row['review_count'] ?? 0);

    $hasDiscount = $row['discount_price'] !== null && (float)$row['discount_price'] > (float)$row['price'];
    $badge = $hasDiscount ? 'Sale' : null;

    // Falls back to `name` (English) whenever a translated column is
    // missing, empty, or the column doesn't exist yet on this database —
    // a translation problem must never leave a product name blank.
    $nameHiVal = $row['name_hi'] ?? '';
    $productsForJs[] = [
        'id'        => (int)$row['id'],
        'cat'       => $row['category'] ?: 'seeds',
        'nameEn'    => $row['name'],
        'nameMr'    => $row['name_mr'] ?: $row['name'],
        'nameHi'    => $nameHiVal ?: $row['name'],
        'image'     => resolveProductImage($row, $productImageByKeyword, $categoryImageFallback, $defaultProductImage, $base_path),
        'price'     => (float)$row['price'],
        'oldPrice'  => $hasDiscount ? (float)$row['discount_price'] : null,
        'unitEn'    => $row['unit'] ?: '1 pc', 'unitMr' => $row['unit'] ?: '1 pc', 'unitHi' => $row['unit'] ?: '1 pc',
        'stock'     => (int)$row['stock'],
        'badgeEn'   => $badge, 'badgeMr' => $badge ? 'ऑफर' : null, 'badgeHi' => $badge ? 'सेल' : null,
        'rating'    => $avgRating, 'reviews' => $reviewCount,
        'seller'    => $row['farmer_name'] ?: 'AgriCart Logistics',
        'verified'  => $row['source'] === 'store',
        'descEn'    => $row['description'] ?: '', 'descMr' => $row['description'] ?: '', 'descHi' => $row['description'] ?: '',
        // New farmer-listing fields (safe defaults when the migration
        // hasn't been run yet, so this never breaks the marketplace).
        'condition'  => $row['product_condition'] ?? 'new',
        'brand'      => $row['brand'] ?? '',
        'delivery'   => isset($row['delivery_available']) ? (bool)$row['delivery_available'] : true,
        'location'   => trim(($row['seller_village'] ?? '') . (!empty($row['seller_district']) ? ', ' . $row['seller_district'] : '')),
        // Comma-separated crop keywords (see crop_tags migration) used to
        // drive AI suggestions instead of hardcoded product-ID lists.
        // Safe default '' when the column doesn't exist yet.
        'cropTags'   => isset($row['crop_tags']) ? strtolower((string)$row['crop_tags']) : '',
    ];
}

// Encode defensively: strip control characters that break JSON_HEX_* encoding,
// and never let a bad row silently reveal raw PHP errors to the page.
$productsJson = json_encode(
    $productsForJs,
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_INVALID_UTF8_SUBSTITUTE
);
if ($productsJson === false) {
    error_log('AgriCart marketplace: product JSON encode failed: ' . json_last_error_msg());
    $productsJson = '[]';
}

// ── Real Marketplace Stats (live counts from DB — no demo numbers) ──
function agri_market_safe_count($conn, $sql) {
    try {
        $res = @$conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) return (int)$row['c'];
    } catch (\Throwable $e) {
        // Table/column missing — skip, keep null.
    }
    return null;
}
$stat_merchants = agri_market_safe_count($conn, "SELECT COUNT(DISTINCT farmer_name) c FROM products WHERE farmer_name IS NOT NULL AND farmer_name <> ''");
if ($stat_merchants === null) {
    $stat_merchants = agri_market_safe_count($conn, "SELECT COUNT(DISTINCT seller_name) c FROM products WHERE seller_name IS NOT NULL AND seller_name <> ''");
}
if ($stat_merchants === null) { $stat_merchants = 0; }

$stat_platform_rating = 0;
$stat_platform_reviews = 0;
try {
    $res = @$conn->query("SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE item_type='product'");
    if ($res && ($row = $res->fetch_assoc())) {
        $stat_platform_rating  = $row['a'] !== null ? round((float)$row['a'], 1) : 0;
        $stat_platform_reviews = (int)$row['c'];
    }
} catch (\Throwable $e) {
    // reviews table missing — keep 0.
}

// An admin session (from the separate /admin/ site) also counts as being
// logged in here — shown clearly as "Admin", not disguised as a customer.
// This gives admin full browsing access to the storefront without a
// separate farmer/customer login, while `orders`/`cart` history stays tied
// to real customer accounts only (admin has none, by design).
// Admin login (via /admin/) now sets the exact same session keys as the
// normal site login (user_id, user_name, user) — using the same `users`
// table with role='admin' — so this simple check already recognizes admin
// sessions correctly. We just label the name clearly when it's an admin.
$isLoggedIn   = isset($_SESSION['user_id']);
$sessionName  = $_SESSION['user_name'] ?? '';
if ($isLoggedIn && ($_SESSION['user_role'] ?? '') === 'admin') { $sessionName .= ' (Admin)'; }
$sessionMobile= $_SESSION['user'] ?? '';

// Saved delivery address (once a user places an order, place_order.php writes
// it back to their account so it can be prefilled next time — "Change Address"
// in the checkout modal lets them override it for a one-off order).
$savedAddress = null;
if ($isLoggedIn) {
    try {
        $sa = $conn->prepare("SELECT saved_name, saved_mobile, saved_pincode, saved_address FROM users WHERE id = ? LIMIT 1");
        $sa->bind_param("i", $_SESSION['user_id']);
        $sa->execute();
        $row = $sa->get_result()->fetch_assoc();
        if ($row && !empty($row['saved_address'])) { $savedAddress = $row; }
    } catch (\Throwable $eSaved) {
        // saved_* columns don't exist yet on this DB — checkout just starts blank.
    }
}

// Admin management has moved to a separate site (see /admin/), so this
// page no longer needs an admin session flag.

include __DIR__ . '/../includes/header.php';
?>
<link rel="preload" as="image" href="<?php echo $base_path; ?>/assets/images/agristore.jpg">

<div class="slider-wrap" style="height: 78vh; min-height: 500px;">
   <div class="slide" data-img="agristore.jpg" style="cursor:pointer;background:linear-gradient(135deg, var(--primary-dark), var(--primary));background-size:cover;background-position:center;" onclick="window.location='<?php echo $base_path; ?>/pages/marketplace.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="heroBadge">Agri Store</div>
            <h1 id="heroTitle">E-Commerce Marketplace</h1>
            <p id="heroSub">Buy certified seeds, organic fertilizers, and protective pesticides directly from verified merchants.</p>
            <div class="hero-search">
                <input type="text" id="searchInput" placeholder="Search seeds, fertilizers, equipment..." oninput="doSearch()">
                <button onclick="doSearch()"><i class="fa-solid fa-magnifying-glass"></i> <span id="searchBtnText">Search</span></button>
            </div>
        </div>
    </div>

    <div class="slide" data-img="slide-seeds.jpg" style="cursor:pointer;background:linear-gradient(135deg, var(--primary-dark), var(--primary));background-size:cover;background-position:center;" onclick="window.location='<?php echo $base_path; ?>/pages/marketplace.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide2Tag">Certified Seeds</div>
            <h1 id="slide2Title">High-Yield Hybrid Seeds &amp; Fresh Vegetables</h1>
            <p id="slide2Sub">Tomato, Onion, Bhindi, Carrot &amp; more hybrid seeds — sourced from certified nurseries across Maharashtra.</p>
        </div>
    </div>

    <div class="slide" data-img="slide-fertilizer.jpg" style="background:linear-gradient(135deg, var(--primary-dark), var(--primary));background-size:cover;background-position:center;">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide3Tag">Organic Fertilizers</div>
            <h1 id="slide3Title">Boost Your Soil Health Naturally</h1>
            <p id="slide3Sub">Shop Vermicompost, Organic Fertilizers, and Bio Nutrients — trusted quality for better yield.</p>
        </div>
    </div>

    <div class="slide" data-img="slide-soil-test.jpg" style="background:linear-gradient(135deg, var(--primary-dark), var(--primary));background-size:cover;background-position:center;">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide4Tag">Soil Testing</div>
            <h1 id="slide4Title">Free Soil Health Check-Up</h1>
            <p id="slide4Sub">Our agri-experts test your soil on-site and recommend the right fertilizers &amp; seeds for your crop.</p>
        </div>
    </div>

    <div class="slide" data-img="slide-delivery.jpg" style="background:linear-gradient(135deg, var(--primary-dark), var(--primary));background-size:cover;background-position:center;">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide5Tag">Doorstep Delivery</div>
            <h1 id="slide5Title">Fast Delivery Straight to Your Farm</h1>
            <p id="slide5Sub">From warehouse to your field — get seeds, fertilizers and pesticides delivered on time, every time.</p>
        </div>
    </div>

    <div class="slide" data-img="slide-warehouse.jpg" style="background:linear-gradient(135deg, var(--primary-dark), var(--primary));background-size:cover;background-position:center;">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide6Tag">Verified Merchants</div>
            <h1 id="slide6Title">Trusted &amp; Verified Sellers</h1>
            <p id="slide6Sub">Every product is quality-checked and stocked in certified warehouses before it reaches you.</p>
        </div>
    </div>

    <div class="slider-dots" id="sliderDots"></div>
</div>

<?php
// Truthful number formatting: never append "+" to a small, exact count —
// only use it once the real number is large enough that "+" reads as a
// floor rather than a fabricated boost.
function agri_stat_label($n, $plusThreshold = 200) {
    $n = (int)$n;
    return $n >= $plusThreshold ? number_format($n) . '+' : number_format($n);
}
?>
<div class="stats">
    <div class="stat-item"><h3 id="statProductsCount"><?php echo agri_stat_label(count($productRows)); ?></h3><p id="st1">Certified Products</p></div>
    <div class="stat-item"><h3><?php echo agri_stat_label($stat_merchants); ?></h3><p id="st2">Verified Merchants</p></div>
    <div class="stat-item"><h3 id="statFreeLabel">Free</h3><p id="st3">Delivery Coverage</p></div>
    <div class="stat-item"><div class="mini-stars"><?php echo $stat_platform_reviews > 0 ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?></div><h3><?php echo $stat_platform_reviews > 0 ? $stat_platform_rating : 'New'; ?></h3><p id="st4">Platform Rating</p></div>
</div>

<!-- ===================== AI CROP SUGGESTION ===================== -->
<div class="ai-suggest-bar">
    <div class="ai-suggest-inner">
        <div class="ai-suggest-text">
            <i class="fa-solid fa-robot"></i>
            <div>
                <strong id="aiSuggestTitle">AI Product Suggestion</strong><br>
                <span id="aiSuggestSub">Select your crop and get suitable seeds &amp; fertilizers instantly.</span>
            </div>
        </div>
        <div class="ai-suggest-controls">
            <select id="aiCropSelect" onchange="suggestForCrop()">
                <option value="" id="cropOptDefault">-- Select Crop --</option>
                <option value="tomato" id="cropOptTomato">Tomato</option>
                <option value="onion" id="cropOptOnion">Onion</option>
                <option value="chilli" id="cropOptChilli">Chilli</option>
                <option value="bhendi" id="cropOptBhendi">Bhendi (Okra)</option>
                <option value="wheat" id="cropOptWheat">Wheat</option>
                <option value="cotton" id="cropOptCotton">Cotton</option>
                <option value="sugarcane" id="cropOptSugarcane">Sugarcane</option>
                <option value="vegetables" id="cropOptVeg">General Vegetables</option>
            </select>
        </div>
    </div>
    <div id="aiSuggestResults" class="ai-suggest-results"></div>
</div>

<div class="store-layout" id="mktStoreLayout">
    <button type="button" class="mobile-filter-toggle" id="mobileFilterToggleBtn" onclick="toggleMobileFilters()" aria-expanded="false" aria-controls="mobileFilterDrawer">
        <i class="fa-solid fa-filter"></i> <span id="filtersToggleLbl">Filter</span>
    </button>
    <div class="filter-drawer-backdrop" id="filterDrawerBackdrop" onclick="toggleMobileFilters(false)"></div>
    <aside class="sidebar" id="mobileFilterDrawer" role="region" aria-label="Product filters">
        <div class="sidebar-header">
            <i class="fa-solid fa-filter"></i> <span id="filterDashTitle">Filter Dashboard</span>
            <button type="button" class="filter-drawer-close" onclick="toggleMobileFilters(false)" aria-label="Close filters"><i class="fa-solid fa-xmark"></i></button>
        </div>
        <div class="sidebar-section">
            <h4 id="catTitle">CATEGORY</h4>
            <div class="cat-item active" onclick="filterCat('all',this)"><span>🏪</span> <span id="catAll">All Products</span> <span class="cat-count" id="cnt-all">0</span></div>
            <div class="cat-item" onclick="filterCat('seeds',this)"><span>🌱</span> <span id="catSeeds">Seeds</span> <span class="cat-count" id="cnt-seeds">0</span></div>
            <div class="cat-item" onclick="filterCat('fertilizer',this)"><span>🧪</span> <span id="catFert">Fertilizers</span> <span class="cat-count" id="cnt-fertilizer">0</span></div>
            <div class="cat-item" onclick="filterCat('pesticides',this)"><span>🧴</span> <span id="catPest">Pesticides</span> <span class="cat-count" id="cnt-pesticides">0</span></div>
            <div class="cat-item" onclick="filterCat('tools',this)"><span>🛠️</span> <span id="catTools">Farm Tools</span> <span class="cat-count" id="cnt-tools">0</span></div>
            <div class="cat-item" onclick="filterCat('irrigation',this)"><span>💧</span> <span id="catIrr">Irrigation Products</span> <span class="cat-count" id="cnt-irrigation">0</span></div>
            <div class="cat-item" onclick="filterCat('feed',this)"><span>🐄</span> <span id="catFeed">Animal Feed</span> <span class="cat-count" id="cnt-feed">0</span></div>
            <div class="cat-item" onclick="filterCat('organic',this)"><span>🍃</span> <span id="catOrganic">Organic Products</span> <span class="cat-count" id="cnt-organic">0</span></div>
            <div class="cat-item" onclick="filterCat('cropkits',this)"><span>🛡️</span> <span id="catKits">Crop Protection Kits</span> <span class="cat-count" id="cnt-cropkits">0</span></div>
        </div>
        <div class="sidebar-section">
            <h4 id="priceTitle">PRICE RANGE</h4>
            <div class="price-range">
                <input type="range" min="0" max="3000" value="3000" id="priceRange" oninput="updatePrice(this.value)">
                <div class="price-labels"><span>₹0</span><span id="priceVal" style="color:var(--primary);font-weight:600">₹3,000</span></div>
            </div>
        </div>
        <div class="sidebar-section">
            <h4 id="stockTitle">STOCK STATUS</h4>
            <div class="cat-item" onclick="filterStock('all',this)"><span>•</span> <span id="lblAllStock">All</span></div>
            <div class="cat-item" onclick="filterStock('in',this)"><span style="color:var(--primary)">●</span> <span id="lblInStock">In Stock</span></div>
            <div class="cat-item" onclick="filterStock('low',this)"><span style="color:var(--warning)">●</span> <span id="lblLowStock">Low Stock</span></div>
        </div>
        <div class="sidebar-section">
            <h4 id="pinTitle">DELIVERY CHECK</h4>
            <div class="pin-check-box">
                <input type="text" id="pinInput" maxlength="6" placeholder="Enter PIN / District">
                <button onclick="checkPincode()"><i class="fa-solid fa-location-dot"></i></button>
            </div>
            <div id="pinResult" class="pin-result"></div>
        </div>
        <div class="sidebar-section" id="mobileOrderCartQuickAccess" style="display:flex;gap:8px">
            <button class="admin-link-btn" onclick="openOrders()"><i class="fa-solid fa-truck-fast"></i> <span id="myOrdersLbl">My Orders</span></button>
            <button class="admin-link-btn" onclick="openCart()"><i class="fa-solid fa-cart-shopping"></i> <span id="myCartLbl">Cart</span></button>
        </div>
    </aside>

    <div class="products-area">
        <div class="offer-strip" id="offerStrip">
            <i class="fa-solid fa-tags"></i>
            <div class="offer-strip-text" id="offerStripText">
                <strong><span id="gridOfferHeading">Today's Special Sale!</span></strong><br>
                <span id="gridOfferSub">Get 15% Off on Organic NPK Fertilizers this week! Use code AGRI15 at checkout.</span>
            </div>
            <div class="offer-code" id="gridOfferCode">AGRI15</div>
        </div>

        <div class="filter-bar">
            <div class="filter-bar-left"><strong id="resultCount">0</strong> <span id="prodFoundText">products found</span></div>
            <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
                <button class="compare-btn" id="compareBtn" onclick="openCompare()" disabled><i class="fa-solid fa-scale-balanced"></i> <span id="compareLbl">Compare</span> (<span id="compareCount">0</span>/2)</button>
                <button class="compare-btn" onclick="openWishlist()"><i class="fa-solid fa-heart"></i> <span id="wishLbl">Wishlist</span> (<span id="wishCount">0</span>)</button>
                <div style="position:relative">
                    <button class="compare-btn" onclick="toggleNotifBell(event)" title="Notifications"><i class="fa-solid fa-bell"></i> <span id="notifBadge" style="display:none;background:var(--danger,#e74c3c);color:#fff;border-radius:50%;font-size:10.5px;min-width:16px;height:16px;line-height:16px;text-align:center;padding:0 3px;margin-left:2px;display:inline-block">0</span></button>
                    <div id="notifPanel" style="display:none;position:absolute;right:0;top:calc(100% + 6px);width:300px;max-height:340px;overflow-y:auto;background:#fff;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,0.18);z-index:50;padding:8px">
                        <div style="font-weight:600;font-size:13px;padding:4px 6px 8px;border-bottom:1px solid #eee">Notifications</div>
                        <div id="notifList" style="display:flex;flex-direction:column;gap:6px;margin-top:6px"></div>
                    </div>
                </div>
                <span style="font-size:12.5px;color:#666" id="sortByText">Sort by:</span>
                <select class="sort-select" onchange="doSort(this.value)">
                    <option value="default" id="sortOptDefault">Default</option>
                    <option value="price-low" id="sortOptLow">Price: Low to High</option>
                    <option value="price-high" id="sortOptHigh">Price: High to Low</option>
                    <option value="rating" id="sortOptRating">Rating: High to Low</option>
                </select>
            </div>
        </div>

        <div class="products-grid" id="productsGrid"></div>
    </div>
</div>

<!-- ===================== CART DRAWER ===================== -->
<div class="cart-overlay" id="cartOverlay" onclick="closeCart()"></div>
<div class="cart-drawer" id="cartDrawer" role="dialog" aria-modal="true" aria-label="Shopping cart">
    <div class="cart-head">
        <h2><i class="fa-solid fa-cart-shopping"></i> <span id="cartWidgetTitle">My Cart</span></h2>
        <button class="close-drawer" onclick="closeCart()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cart-body" id="cartBody"></div>
    <div class="cart-foot" id="cartFoot" style="display:none">
        <div class="coupon-banner" id="couponBanner"></div>
        <div class="coupon-row">
            <input type="text" id="couponInput" placeholder="Enter coupon code (AGRI15)">
            <button onclick="applyCoupon()" id="couponBtnText">Apply</button>
        </div>
        <div id="couponMsg" class="coupon-msg"></div>
        <div class="cart-summary-row"><span id="cartItemsLabel">Items</span><span id="subTotal">₹0</span></div>
        <div class="cart-summary-row" id="discountRow" style="display:none"><span id="discountLbl">Discount</span><span id="discountAmt" style="color:var(--primary)">-₹0</span></div>
        <div class="cart-summary-row"><span id="cartDelLabel">Delivery</span><span style="color:var(--primary)" id="cartDelStatus">Free</span></div>
        <div class="cart-summary-row total"><span id="cartTotalLabel">Total</span><span id="grandTotal">₹0</span></div>
        <button class="checkout-btn" id="checkoutBtn" onclick="goToCheckout()"><i class="fa-solid fa-credit-card"></i> <span id="placeOrderBtnText">Proceed to Checkout</span></button>
    </div>
</div>

<!-- ===================== WISHLIST DRAWER ===================== -->
<div class="cart-overlay" id="wishOverlay" onclick="closeWishlist()"></div>
<div class="cart-drawer" id="wishDrawer" role="dialog" aria-modal="true" aria-label="Wishlist">
    <div class="cart-head">
        <h2><i class="fa-solid fa-heart"></i> <span id="wishDrawerTitle">Wishlist</span></h2>
        <button class="close-drawer" onclick="closeWishlist()"><i class="fa-solid fa-xmark"></i></button>
    </div>
    <div class="cart-body" id="wishBody"></div>
</div>

<!-- ===================== PRODUCT DETAIL MODAL ===================== -->
<div class="modal-overlay" id="productModalOverlay" onclick="closeProductModal(event)" role="dialog" aria-modal="true" aria-labelledby="pmProductName">
  <div class="modal-box product-modal-box" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeProductModal()"><i class="fa-solid fa-xmark"></i></button>
    <div id="productModalContent"></div>
  </div>
</div>

<!-- ===================== COMPARE MODAL ===================== -->
<div class="modal-overlay" id="compareModalOverlay" onclick="closeCompare(event)" role="dialog" aria-modal="true" aria-label="Compare products">
  <div class="modal-box compare-modal-box" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeCompare()"><i class="fa-solid fa-xmark"></i></button>
    <h2><i class="fa-solid fa-scale-balanced"></i> <span id="compareModalTitle">Compare Products</span></h2>
    <div id="compareContent"></div>
  </div>
</div>

<!-- ===================== CHECKOUT MODAL ===================== -->
<div class="modal-overlay" id="checkoutModalOverlay" onclick="closeCheckout(event)" role="dialog" aria-modal="true" aria-label="Checkout">
  <div class="modal-box checkout-modal-box" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeCheckout()"><i class="fa-solid fa-xmark"></i></button>
    <h2><i class="fa-solid fa-credit-card"></i> <span id="checkoutTitle">Checkout</span></h2>
    <div class="checkout-grid">
        <div class="checkout-form">
            <h4 id="ckAddrTitle">Delivery Address</h4>

            <!-- Address book: every saved address (Home / Farm / Office...) shows here
                 as a selectable card. Picking one just copies its values into the
                 hidden #ckName/#ckMobile/#ckPin/#ckAddress fields below, so confirmOrder()
                 doesn't need to know anything changed. -->
            <div id="ckAddressList"></div>
            <button type="button" class="saved-addr-change" id="ckAddNewAddrBtn" onclick="showAddAddressForm()">
                <i class="fa-solid fa-plus"></i> <span id="ckAddNewAddrLbl">Add New Address</span>
            </button>

            <div id="ckAddrFields" style="display:none">
                <input type="text" id="ckAddrLabel" placeholder="Label (e.g. Home, Farm, Office)">
                <input type="text" id="ckName" placeholder="Full Name">
                <input type="text" id="ckMobile" placeholder="Mobile Number" maxlength="10">
                <input type="text" id="ckPin" placeholder="PIN Code" maxlength="6">
                <textarea id="ckAddress" placeholder="Full Address (Village, Taluka, District)" rows="3"></textarea>
                <label style="display:flex;align-items:center;gap:6px;font-size:12.5px;margin:2px 0 10px;color:var(--muted,#68706B)">
                    <input type="checkbox" id="ckSetDefault" style="width:auto;margin:0"> <span id="ckSetDefaultLbl">Set as default address</span>
                </label>
                <div style="display:flex;gap:8px;margin-bottom:10px">
                    <button type="button" class="saved-addr-change" onclick="saveAddressToBook()"><span id="ckSaveAddrLbl">Save Address</span></button>
                    <button type="button" class="saved-addr-change" id="ckCancelAddrBtn" onclick="cancelAddAddress()" style="display:none"><span id="ckCancelAddrLbl">Cancel</span></button>
                </div>
            </div>

            <h4 id="ckPayTitle">Payment Method</h4>
            <!-- This project has no real payment gateway integrated yet, so we
                 never collect card number / CVV / UPI PIN / netbanking
                 credentials here (item 15/16 in the security review). Only
                 Cash on Delivery and two clearly-labeled DEMO UPI flows are
                 offered; for production, integrate a real hosted checkout
                 (e.g. Razorpay) and let it handle all card/UPI data. -->
            <select id="payMethodSelect" class="pay-method-select" onchange="onPayMethodChange()">
                <option value="COD" id="ckPayCOD">Cash on Delivery (COD)</option>
                <option value="UPI" id="ckPayUPI">UPI (Demo — Google Pay / PhonePe / Paytm)</option>
                <option value="UPIQR" id="ckPayUPIQR">Scan &amp; Pay (Demo UPI QR Code)</option>
            </select>

            <div id="upiIdBox" class="pay-extra-box" style="display:none">
                <p style="font-size:11.5px;color:#888;margin:0 0 6px" id="upiDemoNote">Demo payment — no real charge will be made.</p>
                <input type="text" id="ckUpiId" placeholder="Enter UPI ID (e.g. name@bank)">
            </div>
            <div id="upiQrBox" class="pay-extra-box upi-qr-box" style="display:none">
                <img id="upiQrImg" alt="Demo UPI QR code for this order" width="150" height="150" loading="lazy" decoding="async" onerror="this.style.display='none'; document.getElementById('upiQrFallback').style.display='block';">
                <p id="upiQrFallback" style="display:none;font-size:12px;color:#888">QR code could not load — choose Cash on Delivery instead, or try again.</p>
                <p class="upi-qr-note" id="upiQrNote"></p>
            </div>
        </div>
        <div class="checkout-summary">
            <h4 id="ckSummaryTitle">Order Summary</h4>
            <div id="checkoutItems"></div>
            <div class="cart-summary-row"><span id="ckSubtotalLbl">Subtotal</span><span id="ckSubtotal">₹0</span></div>
            <div class="cart-summary-row" id="ckDiscountRow" style="display:none"><span id="ckDiscountLbl">Discount</span><span id="ckDiscount" style="color:var(--primary)">-₹0</span></div>
            <div class="cart-summary-row"><span id="ckDeliveryLbl">Delivery</span><span style="color:var(--primary)" id="ckDeliveryFree">Free</span></div>
            <div class="cart-summary-row total"><span id="ckTotalLbl">Total</span><span id="ckTotal">₹0</span></div>
            <button class="checkout-btn" onclick="confirmOrder()"><i class="fa-solid fa-check"></i> <span id="ckConfirmBtn">Confirm Order</span></button>
        </div>
    </div>
  </div>
</div>

<!-- ===================== ORDER TRACKING / HISTORY MODAL ===================== -->
<div class="modal-overlay" id="ordersModalOverlay" onclick="closeOrders(event)" role="dialog" aria-modal="true" aria-label="My Orders">
  <div class="modal-box orders-modal-box" onclick="event.stopPropagation()">
    <button class="modal-close" onclick="closeOrders()"><i class="fa-solid fa-xmark"></i></button>
    <h2><i class="fa-solid fa-truck-fast"></i> <span id="ordersModalTitle">My Orders</span></h2>
    <div id="ordersContent"></div>
  </div>
</div>


<div class="cart-toast" id="toast"><i class="fa-solid fa-circle-check"></i> <span id="toastMsg"></span></div>

<style>
:root{
    --primary:#2F4F44;         /* deep slate green — formal, not neon */
    --primary-dark:#213B33;
    --accent:#A98B4A;          /* muted gold accent, replaces bright amber */
    --accent-strong:#8C5A3B;   /* muted terracotta, replaces bright orange badge */
    --warning:#8A6D3B;
    --warning-bg:#F3EEE2;
    --danger:#9B3B37;
    --danger-bg:#F5E8E7;
    --bg-soft:#EEF1EC;
    --text:#26292B;
    --muted:#68706B;
    --border:#E0E2DD;
}
/* ===== Floating Cart Button — bigger, on-brand, with hover preview ===== */
.floating-cart-wrap{ position:fixed; bottom:112px; right:24px; z-index:998; }
.floating-cart-btn{
    position:relative; width:60px; height:60px; border-radius:50%;
    background:linear-gradient(145deg, var(--primary), var(--primary-dark));
    color:#fff; border:none;
    display:flex; align-items:center; justify-content:center;
    font-size:22px; cursor:pointer;
    box-shadow:0 8px 20px rgba(0,0,0,0.28), 0 0 0 3px rgba(255,255,255,0.55) inset;
    transition:transform .18s ease, box-shadow .18s ease;
}
.floating-cart-btn:hover{ transform:translateY(-3px) scale(1.07); box-shadow:0 10px 24px rgba(0,0,0,0.32), 0 0 0 3px rgba(255,255,255,0.65) inset; }
.floating-cart-btn:active{ transform:translateY(-1px) scale(0.97); }
.floating-cart-badge{
    position:absolute; top:-6px; right:-6px;
    background:var(--danger); color:#fff; font-size:11.5px; font-weight:700;
    min-width:22px; height:22px; padding:0 5px; border-radius:11px;
    display:none; align-items:center; justify-content:center;
    border:2px solid #fff; line-height:1;
}
.floating-cart-badge.pop{ animation:cartBadgePop .35s ease; }
@keyframes cartBadgePop{
    0%{ transform:scale(1); }
    35%{ transform:scale(1.45); }
    100%{ transform:scale(1); }
}
/* Hover mini-preview (Amazon/Flipkart style quick look) */
.floating-cart-preview{
    position:absolute; bottom:72px; right:0;
    width:240px; background:#fff; border-radius:12px;
    box-shadow:0 10px 28px rgba(0,0,0,0.22); border:1px solid var(--border);
    padding:12px; opacity:0; visibility:hidden; transform:translateY(8px);
    transition:opacity .15s ease, transform .15s ease, visibility .15s;
}
.floating-cart-wrap:hover .floating-cart-preview{ opacity:1; visibility:visible; transform:translateY(0); }
.fcp-row{ display:flex; align-items:center; gap:8px; margin-bottom:8px; }
.fcp-row img{ width:32px; height:32px; border-radius:6px; object-fit:cover; flex-shrink:0; }
.fcp-name{ font-size:12px; color:var(--text); flex:1; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.fcp-qty{ font-size:11px; color:var(--muted); flex-shrink:0; }
.fcp-qty-controls{ display:flex; align-items:center; gap:4px; flex-shrink:0; }
.fcp-qty-btn{ width:20px; height:20px; border:1px solid var(--border); background:#fff; border-radius:5px; font-size:13px; line-height:1; cursor:pointer; color:var(--primary); padding:0; }
.fcp-qty-btn:hover{ background:var(--bg-soft); }
.fcp-remove-btn{ width:24px; height:24px; border:none; background:none; color:#c62828; cursor:pointer; flex-shrink:0; font-size:12px; }
.fcp-remove-btn:hover{ color:#a31515; }
.fcp-more{ font-size:11.5px; color:var(--muted); margin-bottom:8px; }
.fcp-total{ display:flex; justify-content:space-between; font-size:13px; font-weight:700; border-top:1px solid var(--border); padding-top:8px; margin-bottom:8px; }
.fcp-view-btn{ width:100%; background:var(--primary); color:#fff; border:none; border-radius:8px; padding:8px; font-size:12.5px; font-weight:600; cursor:pointer; }
.fcp-view-btn:hover{ background:var(--primary-dark); }
.fcp-empty{ font-size:12px; color:var(--muted); text-align:center; padding:8px 0; }
@media (max-width:600px){
    /* Position/size for .floating-cart-wrap / .floating-cart-btn below
       768px is fully owned by the "single vertical floating-action
       stack" !important block further down (calc()-based, driven by
       --km-bottom/--km-height/--fab-*), so it isn't repeated here. */
    .floating-cart-preview{ display:none; } /* no hover on touch devices — tap opens the full drawer instead */
    .pc-btn-row{ flex-direction:column; }
}

/* ===== New feature styles, professional/formal theme ===== */
.ai-suggest-bar{max-width:1300px;margin:32px auto 20px;background:#fff;border:1px solid var(--border);border-left:4px solid var(--primary);border-radius:10px;padding:16px 22px;color:var(--text);box-shadow:0 2px 8px rgba(0,0,0,0.04)}
.ai-suggest-inner{display:flex;align-items:center;justify-content:space-between;gap:16px;flex-wrap:wrap}
.ai-suggest-text{display:flex;align-items:center;gap:14px;font-size:14px}
.ai-suggest-text i{font-size:20px;color:var(--primary);background:var(--bg-soft);width:42px;height:42px;border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0}
.ai-suggest-text strong{color:var(--primary-dark)}
.ai-suggest-text span{color:var(--muted)}
.ai-suggest-controls select{padding:9px 14px;border-radius:8px;border:1px solid var(--border);font-size:14px;min-width:200px;background:#fff;color:var(--text)}
.ai-suggest-results{display:flex;gap:12px;flex-wrap:wrap;margin-top:14px}
.ai-suggest-card{background:var(--bg-soft);color:var(--text);border-radius:10px;padding:8px 10px;display:flex;gap:8px;align-items:center;font-size:12.5px;max-width:220px;border:1px solid var(--border)}
.ai-suggest-card img{width:42px;height:42px;border-radius:6px;object-fit:cover}
@media(max-width:600px){
  .ai-suggest-bar{padding:14px 16px;margin:22px auto 14px}
  .ai-suggest-inner{flex-direction:column;align-items:stretch;gap:14px}
  .ai-suggest-text{align-items:flex-start}
  .ai-suggest-controls{width:100%}
  .ai-suggest-controls select{width:100%;min-width:0}
}


.admin-link-btn{width:100%;flex:1;padding:10px;border:1px solid var(--primary);color:var(--primary);background:#fff;border-radius:8px;font-size:13px;margin-bottom:8px;cursor:pointer;display:flex;align-items:center;gap:6px;justify-content:center;white-space:nowrap}
@media (max-width:400px){ .admin-link-btn{ font-size:12px; padding:9px 6px; } }
.admin-link-btn:hover{background:var(--bg-soft)}
.pin-check-box{display:flex;gap:6px}
.pin-check-box input{flex:1;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:13px}
.pin-check-box button{background:var(--primary);color:#fff;border:none;border-radius:8px;padding:0 12px;cursor:pointer}
.pin-result{margin-top:8px;font-size:12.5px;font-weight:600}
.pin-result.ok{color:var(--primary)}
.pin-result.bad{color:var(--danger)}

.compare-btn{background:#fff;border:1px solid var(--primary);color:var(--primary);padding:7px 12px;border-radius:8px;font-size:12.5px;cursor:pointer}
.compare-btn:disabled{opacity:0.5;cursor:not-allowed}
.compare-btn:hover:not(:disabled){background:var(--bg-soft)}

.product-card{position:relative}
.wish-heart{position:absolute;top:10px;right:10px;background:#fff;width:32px;height:32px;border-radius:50%;display:flex;align-items:center;justify-content:center;box-shadow:0 2px 6px rgba(0,0,0,0.15);cursor:pointer;z-index:3;font-size:14px;color:#bbb}
.wish-heart.active{color:var(--danger)}
.compare-check{position:absolute;top:10px;left:10px;z-index:3;background:#fff;border-radius:6px;padding:3px 6px;font-size:11px;display:flex;align-items:center;gap:4px;box-shadow:0 2px 6px rgba(0,0,0,0.12)}
.stock-tag{position:absolute;bottom:8px;left:8px;font-size:10.5px;padding:3px 8px;border-radius:20px;font-weight:600;z-index:2}
.stock-tag.in{background:var(--bg-soft);color:var(--primary)}
.stock-tag.low{background:var(--warning-bg);color:var(--warning)}
.stock-tag.out{background:var(--danger-bg);color:var(--danger)}
.product-img-real{width:100%;height:150px;object-fit:cover;border-radius:10px 10px 0 0;cursor:pointer}
.rating-row{display:flex;align-items:center;gap:5px;font-size:12px;color:#666;margin:3px 0}
.rating-row .stars{color:var(--accent)}
.seller-line{font-size:11px;color:#888;display:flex;align-items:center;gap:4px;margin-bottom:4px;min-height:15px}
.verified-badge{color:var(--primary);font-size:10px}
.discount-pct{color:var(--danger);font-size:11.5px;font-weight:700;background:var(--danger-bg);padding:2px 6px;border-radius:5px}
.qty-selector{display:flex;align-items:center;gap:8px;margin:8px 0}
.qty-selector button{width:26px;height:26px;border-radius:6px;border:1px solid #ddd;background:#f7f7f7;cursor:pointer;font-weight:700}
.qty-selector span{min-width:20px;text-align:center;font-weight:600}

/* Modal base */
.modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.55);z-index:1600;align-items:flex-start;justify-content:center;padding:30px 14px;overflow-y:auto}
.modal-overlay.open{display:flex}
.modal-box{background:#fff;border-radius:14px;max-width:780px;width:100%;padding:26px;position:relative;margin-bottom:30px}
.modal-close{position:absolute;top:14px;right:14px;background:#f2f2f2;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer}

.product-modal-box{max-width:760px}
.pm-layout{display:grid;grid-template-columns:1fr 1.1fr;gap:24px}
.pm-layout img{width:100%;border-radius:12px;object-fit:cover;max-height:300px}
.pm-price{font-size:24px;color:var(--primary);font-weight:700}
.pm-old{text-decoration:line-through;color:#999;font-size:15px;margin-left:8px}
.pm-desc{font-size:13.5px;color:#555;line-height:1.6;margin:10px 0}
.pm-info-row{display:flex;gap:16px;font-size:12.5px;color:#666;margin:8px 0;flex-wrap:wrap}
.pm-reviews{margin-top:16px;border-top:1px solid #eee;padding-top:12px}
.review-item{background:#fafafa;border-radius:8px;padding:10px;margin-bottom:8px;font-size:12.5px}
.review-head{display:flex;justify-content:space-between;font-weight:600;margin-bottom:4px}
.reviews-list{display:flex;flex-direction:column;gap:8px;max-height:220px;overflow-y:auto}
.rating-breakdown{margin-bottom:14px}
.rb-row{display:flex;align-items:center;gap:8px;font-size:11.5px;color:#666;margin-bottom:3px}
.rb-row .rb-label{width:32px;flex-shrink:0}
.rb-row .rb-track{flex:1;background:#eee;border-radius:6px;height:7px;overflow:hidden}
.rb-row .rb-fill{background:#FFC107;height:100%}
.rb-row .rb-count{width:22px;text-align:right;flex-shrink:0;color:#999}
.star-picker{margin:6px 0}

.compare-modal-box table{width:100%;border-collapse:collapse;margin-top:10px}
.compare-modal-box td,.compare-modal-box th{border:1px solid #eee;padding:9px;font-size:12.5px;text-align:left}
.compare-modal-box th{background:var(--bg-soft)}
.compare-modal-box img{width:60px;height:60px;object-fit:cover;border-radius:8px}

.checkout-modal-box{max-width:820px}
.checkout-grid{display:grid;grid-template-columns:1.2fr 1fr;gap:22px;margin-top:14px}
.checkout-form input,.checkout-form textarea{width:100%;padding:10px;margin-bottom:10px;border:1px solid #ddd;border-radius:8px;font-size:13px}
.checkout-form h4{margin:10px 0 8px}
.saved-addr-box{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;background:#f4faf5;border:1px solid #d9ecdb;border-radius:10px;padding:12px 14px;margin-bottom:12px}
.saved-addr-text{font-size:13px;line-height:1.6;color:#333}
.saved-addr-change{flex-shrink:0;background:none;border:1px solid var(--primary,#4CAF50);color:var(--primary,#4CAF50);border-radius:8px;padding:6px 12px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap;display:inline-flex;align-items:center;gap:6px;margin-bottom:12px}
.saved-addr-change:hover{background:var(--primary,#4CAF50);color:#fff}
/* Address book — list of saved delivery addresses in checkout */
.addr-card{display:flex;align-items:flex-start;gap:10px;background:#fafafa;border:1.5px solid var(--border,#E0E2DD);border-radius:10px;padding:12px 14px;margin-bottom:10px;cursor:pointer;transition:border-color .15s ease,background .15s ease}
.addr-card:hover{border-color:var(--primary,#4CAF50)}
.addr-card.selected{border-color:var(--primary,#4CAF50);background:#f4faf5}
.addr-card input[type="radio"]{margin-top:3px;accent-color:var(--primary,#4CAF50);width:15px;height:15px;flex-shrink:0}
.addr-card-body{flex:1;font-size:13px;line-height:1.6;color:#333}
.addr-card-label{display:inline-block;font-size:10.5px;font-weight:700;color:var(--primary,#4CAF50);background:#e8f5e9;border-radius:20px;padding:2px 9px;margin-bottom:4px}
.addr-card-actions{display:flex;gap:6px;flex-shrink:0}
.addr-card-actions button{background:none;border:none;color:#888;cursor:pointer;font-size:13px;padding:4px}
.addr-card-actions button:hover{color:var(--primary,#4CAF50)}
.addr-card-actions .addr-delete-btn:hover{color:#c62828}
.pay-option{display:flex;align-items:center;gap:8px;border:1px solid var(--border);border-radius:8px;padding:10px;margin-bottom:8px;cursor:pointer;font-size:13px;transition:border-color .15s ease,background .15s ease}
.pay-method-select{width:100%;padding:11px 14px;border:1.5px solid var(--border);border-radius:8px;font-size:13.5px;background:#fff;color:var(--text);margin-bottom:12px;cursor:pointer}
.pay-method-select:focus{outline:none;border-color:var(--primary)}
.buy-now-btn{width:100%;background:var(--primary);border:1.5px solid var(--primary);color:#fff;padding:9px;border-radius:9px;font-size:12.5px;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:6px;transition:0.25s;font-family:'Poppins',sans-serif}
.buy-now-btn:hover{background:var(--primary-dark);border-color:var(--primary-dark)}
.pc-btn-row .add-btn, .pc-btn-row .buy-now-btn{margin-top:0}
.pay-option input[type="radio"]{accent-color:var(--primary);width:15px;height:15px}
.pay-option:has(input:checked){border-color:var(--primary);background:var(--bg-soft)}
.pay-option:hover{border-color:var(--primary);background:var(--bg-soft)}
.pay-option i{width:16px;text-align:center;color:var(--primary)}
.pay-extra-box{margin:-2px 0 12px 4px;padding:12px;border:1px dashed var(--border);border-radius:8px;background:var(--bg-soft)}
.pay-extra-box input,.pay-extra-box select{width:100%;padding:9px;margin-bottom:8px;border:1px solid var(--border);border-radius:8px;font-size:13px}
.pay-extra-box input:last-child,.pay-extra-box select:last-child{margin-bottom:0}
.upi-qr-box{display:flex;align-items:center;gap:14px;text-align:left}
.upi-qr-box img{border-radius:8px;border:1px solid var(--border);background:#fff;flex-shrink:0}
.upi-qr-note{font-size:12.5px;color:var(--muted);margin:0}
.checkout-summary{background:#fafafa;border-radius:10px;padding:14px}
.ck-item-row{display:flex;justify-content:space-between;font-size:12.5px;margin-bottom:6px;color:#444}

.coupon-banner{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.coupon-banner:empty{margin-bottom:0}
.coupon-chip{display:flex;align-items:center;gap:6px;background:#f0f9f0;border:1px dashed var(--primary);border-radius:20px;padding:4px 10px;font-size:11px;cursor:pointer;transition:background .15s}
.coupon-chip:hover{background:#e2f3e2}
.coupon-chip .code{font-weight:700;color:var(--primary)}
.coupon-chip .desc{color:#666}
.coupon-row{display:flex;gap:6px;margin-bottom:4px}
.coupon-row input{flex:1;padding:8px 10px;border:1px solid #ddd;border-radius:8px;font-size:12.5px}
.coupon-row button{background:var(--accent);border:none;border-radius:8px;padding:0 14px;font-weight:600;cursor:pointer}
.coupon-msg{font-size:11.5px;margin-bottom:8px;min-height:14px}
.coupon-msg.ok{color:var(--primary)}
.coupon-msg.bad{color:var(--danger)}

.order-card{border:1px solid #eee;border-radius:10px;padding:14px;margin-bottom:14px;transition:box-shadow .2s ease, transform .2s ease}
.order-card:hover{box-shadow:0 4px 14px rgba(0,0,0,0.06);transform:translateY(-1px)}
.order-head{display:flex;justify-content:space-between;font-size:13px;font-weight:600;margin-bottom:10px}
.order-delete-btn{background:none;border:none;color:#aaa;cursor:pointer;font-size:13px;padding:4px 6px;border-radius:6px;transition:color .15s ease, background .15s ease}
.order-delete-btn:hover{color:#d93025;background:#fdecea}
.track-line{display:flex;justify-content:space-between;position:relative;margin:18px 0}
.track-step{flex:1;text-align:center;font-size:10.5px;color:#999;position:relative;z-index:1}
/* Connector is attached per-step (sized to that step's own flex box) instead
   of one absolutely-positioned line spanning the whole row by percentage.
   That old approach used percentages of the tracker's *visible* width, which
   drifted out of sync with the dots whenever the row scrolled/overflowed on
   narrow screens (see .track-line{overflow-x:auto} below) — the line could
   run on past the last visible dot. Anchoring each segment to its own step
   keeps it correctly aligned no matter the width or scroll position. */
.track-step:not(:first-child)::before{content:'';position:absolute;top:11px;right:50%;width:100%;height:3px;background:#eee;z-index:0}
.track-step.done:not(:first-child)::before{background:var(--primary);transition:background .3s ease}
.track-step .dot{width:22px;height:22px;border-radius:50%;background:#eee;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 6px;font-size:11px;transition:background .3s ease, box-shadow .3s ease;position:relative;z-index:1}
.track-step.done .dot{background:var(--primary)}
.track-step.done{color:var(--primary);font-weight:600}
.track-step.current .dot{box-shadow:0 0 0 4px rgba(47,79,68,.18)}


@media(max-width:760px){
  .pm-layout,.checkout-grid{grid-template-columns:1fr}
}

/* ===== Overrides to align header/global classes with the professional palette ===== */
.offer-strip{background:linear-gradient(135deg,var(--primary-dark),var(--primary)) !important}
.offer-code{background:rgba(255,255,255,0.15) !important;border:1px solid rgba(255,255,255,0.4) !important;cursor:pointer}
.offer-strip-text{transition:opacity .3s ease, transform .3s ease}
.offer-strip-text.offer-fade{opacity:0;transform:translateX(-12px)}

/* ===== Make every product card the same size/shape regardless of text length
   (product name, seller name, unit text, stock message, or Add/Order button
   text wrapping in Marathi/Hindi) =====
   Every card is forced into the same fixed-height "slots" (image, name,
   seller, rating, unit, price, quantity, stock message) in the exact same
   order, so no matter how short or long any one product's content is, the
   price row / qty selector / stock line / buttons always land at the same
   vertical position across every card in the row — and the bottom action
   buttons are pinned to the card's bottom edge via margin-top:auto. */
.products-grid{align-items:stretch !important}
.product-card{display:flex !important;flex-direction:column !important;height:100% !important;box-sizing:border-box !important}
.product-body{display:flex !important;flex-direction:column !important;flex:1 !important;box-sizing:border-box !important}

/* 2. Product name — reserved 2-line height, longer titles ellipsis instead
   of pushing everything below them downward. */
.product-name{
    display:-webkit-box !important;line-clamp:2 !important;-webkit-line-clamp:2 !important;
    -webkit-box-orient:vertical !important;overflow:hidden !important;text-overflow:ellipsis !important;
    min-height:34px !important;max-height:34px !important;line-height:17px !important;
    white-space:normal !important;word-break:break-word !important;
}

/* 3. Seller / location lines — each pinned to one fixed-height line; a long
   seller or company name is ellipsised rather than wrapping and pushing the
   rating/price rows down. */
.seller-line{
    min-height:16px !important;max-height:16px !important;line-height:16px !important;
    overflow:hidden !important;white-space:nowrap !important;text-overflow:ellipsis !important;
    display:flex !important;align-items:center !important;
}

/* 4. Rating row — fixed height so it sits at the same position on every card. */
.rating-row{min-height:17px !important;max-height:17px !important;overflow:hidden !important}

/* 5. Unit / quantity text — fixed height even when short ("kg", "1 pc"). */
.product-unit{
    min-height:16px !important;max-height:16px !important;line-height:16px !important;
    font-size:11.5px !important;color:var(--muted,#888) !important;margin:2px 0 !important;
    overflow:hidden !important;white-space:nowrap !important;text-overflow:ellipsis !important;
}

/* 6. Price row — current price, old price and discount badge always stay
   on one line, vertically centered, never wrapping. */
.price-row{
    display:flex !important;flex-wrap:nowrap !important;align-items:center !important;
    gap:6px !important;min-height:22px !important;overflow:hidden !important;
}
.price-row .price-now{white-space:nowrap !important;flex-shrink:0 !important}
.price-row .price-old{white-space:nowrap !important;flex-shrink:0 !important}
.price-row .discount-pct{white-space:nowrap !important;flex-shrink:0 !important}

/* 7. Quantity selector — same fixed slot on every card. */
.qty-selector{min-height:26px !important;margin:8px 0 !important}

/* 8. Stock availability message — always renders (see renderProducts()),
   even when empty, so the buttons below never shift up/down depending on
   whether a product happens to be low in stock. */
.stock-msg-line{
    display:block !important;min-height:16px !important;max-height:16px !important;
    line-height:16px !important;font-size:11.5px !important;color:#e08a00 !important;
    margin-top:2px !important;overflow:hidden !important;white-space:nowrap !important;text-overflow:ellipsis !important;
}

/* 9. Bottom action buttons — always pinned to the bottom of the card, same
   height/width ratio/alignment on every card regardless of what's above. */
.pc-btn-row{margin-top:auto !important;padding-top:6px !important;display:flex !important;gap:6px !important}
.pc-btn-row .add-btn,.pc-btn-row .buy-now-btn{
    flex:1 1 50% !important;font-size:11px !important;padding:8px 4px !important;line-height:1.2 !important;
    min-height:36px !important;max-height:36px !important;display:flex !important;align-items:center !important;
    justify-content:center !important;text-align:center !important;white-space:normal !important;
}
.sidebar-header{background:var(--primary-dark) !important}
.cat-item.active{background:var(--bg-soft) !important;color:var(--primary-dark) !important}
.cat-count{background:var(--bg-soft) !important;color:var(--primary-dark) !important}
.checkout-btn{background:var(--primary) !important;border-color:var(--primary) !important}
.checkout-btn:hover{background:var(--primary-dark) !important}

/* Buttons that used to be plain <div>s are now real <button> elements for
   accessibility (item 21) — reset their default browser chrome so they look
   the same as before. */
.wish-heart,.remove-btn,.qty-selector button,.qty-btn{border:none}
.wish-heart:focus-visible,.remove-btn:focus-visible,button:focus-visible,input:focus-visible,select:focus-visible,textarea:focus-visible,a:focus-visible{
    outline:3px solid var(--primary,#2e7d32) !important;outline-offset:2px;
}
.product-name{font-family:inherit;font-size:inherit;color:inherit}

/* Real star ratings (renderStars()) */
.star-rating i{color:#FFC107;font-size:13px}
.star-rating i.fa-regular{color:#ddd}

/* Mobile "Filters" toggle button — hidden on desktop, shown as a floating
   pill button on small screens; opens the sidebar as a slide-in drawer. */
.mobile-filter-toggle{display:none}
.filter-drawer-close{display:none}
.filter-drawer-backdrop{display:none;position:fixed;inset:0;background:rgba(0,0,0,0.45);z-index:1680}
.filter-drawer-backdrop.open{display:block}

/* =====================================================================
   MOBILE FILTER DASHBOARD FIX (<=992px, matches .store-layout's own
   single-column collapse breakpoint so there's no gap between where the
   grid stacks and where this drawer behaviour switches on)
   -------------------------------------------------------------------
   Scoped strictly to the filter sidebar / toggle button / backdrop.
   Desktop/tablet layout above this breakpoint is untouched. IDs +
   !important are used only to GUARANTEE the closed-by-default,
   open-on-tap drawer behaviour even if an external stylesheet
   (style.css / responsive.css, loaded via header.php and not part of
   this file) ships a conflicting/forced-open rule for .sidebar or
   .store-layout — never to force anything open.
   ===================================================================== */
@media(max-width:992px){
    #mktStoreLayout{ display:block !important; width:100% !important; }

    /* ---- Floating "Filter" button — fixed, premium pill, right-center ---- */
    #mobileFilterToggleBtn{
        display:flex !important; align-items:center !important; justify-content:center !important;
        gap:7px !important;
        position:fixed !important;
        top:50% !important; right:14px !important; left:auto !important; bottom:auto !important;
        transform:translateY(-50%) !important;
        width:auto !important; min-width:48px !important; min-height:48px !important;
        padding:12px 16px !important;
        background:linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
        color:#fff !important; border:none !important;
        border-radius:26px !important;
        font-size:13.5px !important; font-weight:700 !important;
        box-shadow:0 8px 20px rgba(0,0,0,0.28), 0 0 0 3px rgba(255,255,255,0.5) inset !important;
        z-index:900 !important;
        cursor:pointer !important;
        margin:0 !important;
    }
    #mobileFilterToggleBtn:active{ transform:translateY(-50%) scale(0.96) !important; }

    /* ---- Filter Dashboard drawer: closed off-screen by default, no
       reserved layout space (position:fixed removes it from flow) ---- */
    #mobileFilterDrawer{
        position:fixed !important;
        top:0 !important; right:0 !important; left:auto !important; bottom:0 !important;
        width:min(90vw, 380px) !important; max-width:min(90vw, 380px) !important;
        height:100vh !important; height:100dvh !important; max-height:100vh !important; max-height:100dvh !important;
        margin:0 !important;
        transform:translateX(100%) !important;
        transition:transform .3s ease !important;
        overflow-y:auto !important; -webkit-overflow-scrolling:touch;
        z-index:1690 !important;
        box-shadow:-6px 0 24px rgba(0,0,0,0.25) !important;
        background:#fff !important;
        visibility:visible !important;
    }
    #mobileFilterDrawer.open{ transform:translateX(0) !important; }

    /* Sticky header inside the drawer so it stays visible while the
       filter list scrolls */
    #mobileFilterDrawer .sidebar-header{
        position:sticky !important; top:0 !important; z-index:2 !important;
        display:flex !important; align-items:center !important; gap:8px !important;
    }
    #mobileFilterDrawer .filter-drawer-close{
        display:flex !important; align-items:center !important; justify-content:center !important;
        margin-left:auto !important; background:rgba(255,255,255,0.15) !important; border:none !important;
        color:#fff !important; font-size:17px !important; line-height:1 !important; cursor:pointer !important;
        width:36px !important; height:36px !important; min-width:36px !important; min-height:36px !important;
        border-radius:50% !important; z-index:5 !important; padding:0 !important;
    }

    /* Backdrop covers the full viewport, sits below the drawer */
    #filterDrawerBackdrop{
        top:0 !important; inset:0 !important; z-index:1680 !important;
    }
}

@media(max-width:768px){
    .hero-slider,.slider-container{height:60vh !important;min-height:380px !important}
    .slide-content h1,#slide6Title{font-size:26px !important;line-height:1.25 !important}
    .search-box,.hero-search{width:100% !important;max-width:100% !important}

    .filter-bar{flex-wrap:wrap;gap:10px}
    .filter-bar > div{flex-wrap:wrap}
    .compare-btn{font-size:11.5px;padding:6px 9px}

    .checkout-grid{grid-template-columns:1fr !important}
    .modal-box{width:94vw !important;max-width:94vw !important;max-height:92vh !important}
    .track-line{flex-wrap:nowrap;overflow-x:auto}
    .track-step{min-width:64px}

    .floating-cart-wrap,.krishimitra-widget,#krishiMitraBtn{max-width:56px}
    #notifPanel{width:88vw !important;right:-8px !important}
}
@media(max-width:400px){
    .pc-btn-row{flex-direction:column}
    .modal-box{padding:14px !important}
    .coupon-input-row{flex-direction:column}
    .upi-qr-box{flex-direction:column;align-items:center}
}

/* =====================================================================
   MOBILE FLOATING FILTER WIDGET (<=768px) — restyled to match the
   KrishiMitra circular launcher: a compact 56px round button stacked
   above KrishiMitra on the right edge, with the Filter Dashboard opening
   as a small anchored panel to its LEFT (not a full-width right drawer).
   Placed after the general @media(max-width:768px) block above so it
   wins the cascade for this narrower range; the 769–991px tablet range
   keeps the pill-button/right-drawer behaviour from the 992px block
   above untouched. Desktop (>768px) is completely unaffected.
   ===================================================================== */
@media(max-width:768px){
    /* ---------------------------------------------------------------
       Single vertical floating-action stack: Filter / Cart / KrishiMitra
       -------------------------------------------------------------------
       Positions are computed with calc() from CSS variables instead of
       guessed fixed px values, so nothing can drift out of sync or
       overlap:
         --km-bottom / --km-height : KrishiMitra's own actual bottom
             offset + height. KrishiMitra lives in a separate include
             (krishimitra_widget.php) we don't control, so these start
             as sane fallbacks and get set precisely once by a small
             JS measurement pass further down the page — KrishiMitra
             itself is never moved or resized, only *read*.
         --fab-gap / --fab-size / --fab-right : the shared 12px gap,
             56px circle size, and 18px right offset used by Filter
             and Cart (and forced onto KrishiMitra's own right offset
             so all three share one center-X column).
       Filter's bottom shifts from "directly above KrishiMitra" to
       "directly above Cart" purely via the body.fab-cart-visible
       class — no per-pixel JS repositioning of Filter/Cart needed. ---- */
    :root{
        --fab-gap: 12px;
        --fab-size: 56px;
        --fab-right: 18px;
        --km-bottom: 20px; /* fallback until measured */
        --km-height: 56px; /* fallback until measured */
    }

    /* ---- Circular Filter launcher — same size/shape as Cart, stacked
       directly above KrishiMitra (or above Cart once logged in) ---- */
    #mobileFilterToggleBtn{
        display:flex !important; align-items:center !important; justify-content:center !important;
        position:fixed !important;
        top:auto !important; left:auto !important;
        right:var(--fab-right) !important;
        bottom:calc(var(--km-bottom) + var(--km-height) + var(--fab-gap)) !important;
        transform:none !important;
        width:var(--fab-size) !important; height:var(--fab-size) !important;
        min-width:var(--fab-size) !important; min-height:var(--fab-size) !important;
        max-width:var(--fab-size) !important; max-height:var(--fab-size) !important;
        box-sizing:border-box !important;
        padding:0 !important; margin:0 !important;
        border-radius:50% !important;
        background:linear-gradient(145deg, var(--primary), var(--primary-dark)) !important;
        color:#fff !important; border:none !important;
        font-size:20px !important;
        box-shadow:0 8px 20px rgba(0,0,0,0.28), 0 0 0 3px rgba(255,255,255,0.55) inset !important;
        z-index:1695 !important;
        cursor:pointer !important;
        transition:bottom .2s ease, transform .18s ease !important;
    }
    /* Cart is visible (logged in, mobile) → Filter moves up one slot
       to sit directly above Cart instead of directly above KrishiMitra. */
    body.fab-cart-visible #mobileFilterToggleBtn{
        bottom:calc(var(--km-bottom) + var(--km-height) + var(--fab-gap) + var(--fab-size) + var(--fab-gap)) !important;
    }
    #mobileFilterToggleBtn:active{ transform:scale(0.94) !important; }
    /* Icon only on mobile — the "Filter" text label is hidden, matching
       KrishiMitra's icon-only launcher */
    #mobileFilterToggleBtn #filtersToggleLbl{ display:none !important; }

    /* ---- Filter Dashboard: small anchored panel beside the button,
       opens toward the LEFT instead of sliding in from the screen edge.
       Tracks Filter's own bottom via the same calc()/class pairing so
       it always opens right next to the button, wherever that is. ---- */
    #mobileFilterDrawer{
        position:fixed !important;
        top:auto !important; left:auto !important;
        bottom:calc(var(--km-bottom) + var(--km-height) + var(--fab-gap) - 7px) !important;
        right:calc(var(--fab-right) + var(--fab-size) + 8px) !important;
        /* Shrinks on narrow phones so it can never spill past the left
           edge of the viewport: reserves button-width + gap on the
           right plus a breathing margin on the left. */
        width:min(82vw, 360px, calc(100vw - 110px)) !important;
        max-width:360px !important;
        height:auto !important; max-height:75vh !important;
        margin:0 !important; border-radius:16px !important;
        transform:translateX(30px) !important;
        opacity:0 !important; visibility:hidden !important; pointer-events:none !important;
        transition:bottom .2s ease, transform .35s ease-out, opacity .35s ease-out, visibility .35s !important;
        overflow-y:auto !important; -webkit-overflow-scrolling:touch;
        z-index:1690 !important;
        box-shadow:0 14px 36px rgba(0,0,0,0.28) !important;
        background:#fff !important;
    }
    body.fab-cart-visible #mobileFilterDrawer{
        bottom:calc(var(--km-bottom) + var(--km-height) + var(--fab-gap) + var(--fab-size) + var(--fab-gap) - 7px) !important;
    }
    #mobileFilterDrawer.open{
        transform:translateX(0) !important; opacity:1 !important;
        visibility:visible !important; pointer-events:auto !important;
    }
    #mobileFilterDrawer .sidebar-header{
        position:sticky !important; top:0 !important; z-index:2 !important;
        border-radius:16px 16px 0 0 !important;
        display:flex !important; align-items:center !important; gap:8px !important;
    }
    #mobileFilterDrawer .filter-drawer-close{
        display:flex !important; align-items:center !important; justify-content:center !important;
        margin-left:auto !important; background:rgba(255,255,255,0.15) !important; border:none !important;
        color:#fff !important; font-size:16px !important; line-height:1 !important; cursor:pointer !important;
        width:34px !important; height:34px !important; min-width:34px !important; min-height:34px !important;
        border-radius:50% !important; z-index:5 !important; padding:0 !important;
    }

    /* Overlay still covers the full page and closes the panel on tap,
       but sits below both floating buttons so Filter/KrishiMitra stay
       clickable while it's showing. */
    #filterDrawerBackdrop{ z-index:1680 !important; background:rgba(0,0,0,0.4) !important; }

    /* ---- Cart wrap: same right column, sits directly above KrishiMitra ---- */
    #floatingCartWrap{
        position:fixed !important;
        top:auto !important; left:auto !important;
        right:var(--fab-right) !important;
        bottom:calc(var(--km-bottom) + var(--km-height) + var(--fab-gap)) !important;
        z-index:998 !important;
    }
    /* Match the Cart button's size to Filter/KrishiMitra exactly — same
       width/height/border-radius/padding/box-sizing so all three read
       as one consistent set of circles. */
    .floating-cart-btn{
        width:var(--fab-size) !important; height:var(--fab-size) !important;
        min-width:var(--fab-size) !important; min-height:var(--fab-size) !important;
        max-width:var(--fab-size) !important; max-height:var(--fab-size) !important;
        box-sizing:border-box !important;
        border-radius:50% !important;
        padding:0 !important;
        font-size:20px !important;
    }

    /* Best-effort: keep the KrishiMitra launcher clickable above the
       Filter overlay too. Its full chat-window styling lives in
       krishimitra_widget.php, which isn't part of this file, so only
       the button's stacking order can be guaranteed from here. */
    #krishiMitraBtn{ z-index:1695 !important; }
}

/* =====================================================================
   MOBILE CART / ORDER DRAWER FIX (<=768px)
   -------------------------------------------------------------------
   Scoped strictly to max-width:768px and to the Cart/Wishlist drawer +
   Orders modal only. Nothing here touches desktop/tablet layout,
   colors, product markup, or any other panel. The !important flags
   exist only to GUARANTEE the closed-by-default / open-on-click drawer
   behaviour on mobile even if an external stylesheet (style.css /
   responsive.css, loaded via header.php and not part of this file)
   ships a conflicting rule — they are used purely to enforce the
   correct hidden/shown state, never to force anything open.
   ===================================================================== */
@media(max-width:768px){
    /* Never allow an off-canvas panel to cause horizontal scrolling */
    html, body{ overflow-x:hidden !important; }

    /* ---- Cart & Wishlist: slide-in drawers from the right ---- */
    #cartDrawer, #wishDrawer{
        position:fixed !important;
        top:0 !important; right:0 !important; left:auto !important; bottom:0 !important;
        width:86vw !important; max-width:380px !important;
        height:100vh !important; height:100dvh !important;
        margin:0 !important; border-radius:0 !important;
        background:#fff !important;
        display:flex !important; flex-direction:column !important;
        overflow-y:auto !important; -webkit-overflow-scrolling:touch;
        transform:translateX(100%) !important;
        transition:transform .3s ease !important;
        z-index:1700 !important;
        box-shadow:-6px 0 24px rgba(0,0,0,0.25) !important;
        visibility:visible !important;
    }
    #cartDrawer.open, #wishDrawer.open{
        transform:translateX(0) !important;
    }

    /* ---- Backdrop behind the cart/wishlist drawer ---- */
    #cartOverlay, #wishOverlay{
        position:fixed !important; inset:0 !important;
        background:rgba(0,0,0,0.5) !important;
        z-index:1650 !important;
        opacity:0 !important; visibility:hidden !important; pointer-events:none !important;
        transition:opacity .3s ease !important;
        display:block !important;
    }
    #cartOverlay.open, #wishOverlay.open{
        opacity:1 !important; visibility:visible !important; pointer-events:auto !important;
    }

    /* ---- Orders history: same slide-in drawer treatment ---- */
    #ordersModalOverlay{
        justify-content:flex-end !important;
        align-items:stretch !important;
        padding:0 !important;
        display:flex !important;
        opacity:0 !important; visibility:hidden !important; pointer-events:none !important;
        transition:opacity .3s ease !important;
    }
    #ordersModalOverlay.open{
        opacity:1 !important; visibility:visible !important; pointer-events:auto !important;
    }
    #ordersModalOverlay .orders-modal-box{
        width:86vw !important; max-width:380px !important;
        height:100vh !important; height:100dvh !important;
        max-height:100vh !important; max-height:100dvh !important;
        margin:0 !important; border-radius:0 !important;
        overflow-y:auto !important; -webkit-overflow-scrolling:touch;
        transform:translateX(100%) !important;
        transition:transform .3s ease !important;
    }
    #ordersModalOverlay.open .orders-modal-box{
        transform:translateX(0) !important;
    }

    /* ---- Close buttons: clear 44x44px touch target, always tappable ---- */
    #cartDrawer .close-drawer, #wishDrawer .close-drawer,
    #ordersModalOverlay .modal-close{
        width:44px !important; height:44px !important;
        min-width:44px !important; min-height:44px !important;
        display:flex !important; align-items:center !important; justify-content:center !important;
        font-size:18px !important;
        z-index:5 !important;
        position:relative !important;
    }
    #ordersModalOverlay .modal-close{
        position:absolute !important; top:10px !important; right:10px !important;
    }

    /* ---- Floating cart trigger: keep it a proper 44px+ touch target
       and clear of the drawer's own z-index stack ---- */
    #floatingCartBtn{ width:50px !important; height:50px !important; z-index:998 !important; }
}

/* Reliable body-scroll lock while a mobile drawer/modal is open, in case
   the shared stylesheet's .no-scroll rule is missing or overridden. */
body.no-scroll{ overflow:hidden !important; height:100% !important; }
</style>

<script>
/* ---------------- SECURITY / SHARED UI HELPERS ----------------
   escapeHTML: every piece of text that came from the database or an API
   response (product names, seller names, reviews, order numbers, coupon
   codes, saved address fields, etc.) must go through this before being
   placed into innerHTML — plain string interpolation into a template
   literal is an XSS hole. */
function escapeHTML(value) {
    return String(value ?? '')
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;')
        .replaceAll('"', '&quot;')
        .replaceAll("'", '&#039;');
}

// Only allow http(s) URLs or same-site relative paths as <img src> — blocks
// "javascript:", "data:text/html", and similar attribute-injection tricks
// that could otherwise slip in through a bad/tampered product image field.
const FALLBACK_PRODUCT_IMAGE = "<?php echo rtrim($base_path, '/'); ?>/assets/images/products/default.jpg";
function isSafeImageUrl(url) {
    if (!url || typeof url !== 'string') return false;
    const trimmed = url.trim();
    if (/^https?:\/\//i.test(trimmed)) return true;
    if (trimmed.startsWith('/') || trimmed.startsWith('./') || trimmed.startsWith('../')) return true;
    if (!trimmed.includes(':')) return true; // plain relative path, no scheme at all
    return false; // anything with another scheme (javascript:, data:, vbscript:...) is rejected
}
function safeImgSrc(url) { return isSafeImageUrl(url) ? escapeHTML(url) : FALLBACK_PRODUCT_IMAGE; }
// Standard <img> attributes for every product photo: lazy-loaded, async
// decoded, and falls back to a themed placeholder instead of a broken-image
// icon if the URL 404s. `alt` must always be passed in already-escaped.
function productImgAttrs(url, altEscaped) {
    return `src="${safeImgSrc(url)}" alt="${altEscaped}" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='${FALLBACK_PRODUCT_IMAGE}';"`;
}

// Renders an accessible 5-star rating display (filled / half / empty) from
// a numeric rating like 4.3 — used on product cards, the product modal,
// compare table, and review summaries.
function renderStars(rating) {
    const r = Math.max(0, Math.min(5, Number(rating) || 0));
    const full = Math.floor(r);
    const half = (r - full) >= 0.5 ? 1 : 0;
    const empty = 5 - full - half;
    const icons = '<i class="fa-solid fa-star"></i>'.repeat(full)
        + '<i class="fa-solid fa-star-half-stroke"></i>'.repeat(half)
        + '<i class="fa-regular fa-star"></i>'.repeat(empty);
    return `<span class="star-rating" role="img" aria-label="Rated ${r.toFixed(1)} out of 5">${icons}</span>`;
}

// Shared fetch helper: checks response.ok, parses JSON safely, times out
// via AbortController instead of hanging forever, and always returns a
// { ok, data, error } shape so callers don't need their own try/catch.
async function fetchJSON(url, options = {}, timeoutMs = 15000) {
    const controller = new AbortController();
    const timer = setTimeout(() => controller.abort(), timeoutMs);
    try {
        const res = await fetch(url, { ...options, signal: controller.signal });
        clearTimeout(timer);
        let data;
        try { data = await res.json(); }
        catch (eParse) { return { ok: false, data: null, error: 'invalid_json' }; }
        if (!res.ok) return { ok: false, data, error: 'http_' + res.status };
        return { ok: true, data, error: null };
    } catch (eFetch) {
        clearTimeout(timer);
        if (eFetch.name === 'AbortError') return { ok: false, data: null, error: 'timeout' };
        return { ok: false, data: null, error: 'network' };
    }
}

/* ---------------- RESILIENT SLIDER IMAGE LOADER ----------------
   Tries several possible base paths for each slide image so the slider
   works even if $base_path is misconfigured, then falls back to a
   themed gradient (never leaves a blank slide). */
(function(){
    const PHP_BASE = "<?php echo $base_path; ?>";
    const candidates = (file) => [
        PHP_BASE + "/assets/images/" + file,
        "/AgriCart/assets/images/" + file,
        "../assets/images/" + file,
        "assets/images/" + file
    ];
    // Loads all candidate paths for a slide IN PARALLEL (instead of one-by-one)
    // so a failed/slow first path doesn't delay showing the image — whichever
    // valid path resolves is used, preferring the earliest one in the list if
    // more than one succeeds. A themed gradient is shown immediately so the
    // slide is never blank/black while images are loading.
    function loadSlide(slide, file){
        const list = candidates(file);
        let resolvedIndex = null;
        let remaining = list.length;
        list.forEach((url, i) => {
            const img = new Image();
            img.onload = () => {
                if (resolvedIndex === null || i < resolvedIndex) {
                    resolvedIndex = i;
                    slide.style.backgroundImage = "url('" + url + "')";
                }
            };
            img.onerror = () => {
                remaining--;
                if (remaining === 0 && resolvedIndex === null) {
                    console.warn("Slider image not found for", file, "- tried:", list);
                }
            };
            img.src = url;
        });
    }
    document.addEventListener('DOMContentLoaded', function(){
        document.querySelectorAll('.slide[data-img]').forEach(slide => {
            loadSlide(slide, slide.getAttribute('data-img'));
        });
    });
})();

const StoreT = {
    en: {
        heroBadge: "Agri Store", heroTitle: "E-Commerce Marketplace",
        heroSub: "Buy certified seeds, organic fertilizers, and protective pesticides directly from verified merchants.",
        slide2Tag: "Certified Seeds", slide2Title: "High-Yield Hybrid Seeds & Fresh Vegetables",
        slide2Sub: "Tomato, Onion, Bhindi, Carrot & more hybrid seeds — sourced from certified nurseries across Maharashtra.",
        slide3Tag: "Organic Fertilizers", slide3Title: "Boost Your Soil Health Naturally",
        slide3Sub: "Shop Vermicompost, Organic Fertilizers, and Bio Nutrients — trusted quality for better yield.",
        slide4Tag: "Soil Testing", slide4Title: "Free Soil Health Check-Up",
        slide4Sub: "Our agri-experts test your soil on-site and recommend the right fertilizers & seeds for your crop.",
        slide5Tag: "Doorstep Delivery", slide5Title: "Fast Delivery Straight to Your Farm",
        slide5Sub: "From warehouse to your field — get seeds, fertilizers and pesticides delivered on time, every time.",
        slide6Tag: "Verified Merchants", slide6Title: "Trusted & Verified Sellers",
        slide6Sub: "Every product is quality-checked and stocked in certified warehouses before it reaches you.",
        searchPlaceholder: "Search seeds, fertilizers, equipment...", searchBtn: "Search",
        filterDash: "Filter Dashboard", filtersToggleLbl: "Filter", catTitle: "CATEGORY", priceTitle: "PRICE RANGE",
        catAll: "All Products", catSeeds: "Seeds", catFert: "Fertilizers", catPest: "Pesticides",
        catTools: "Farm Tools", catIrr: "Irrigation Products", catFeed: "Animal Feed",
        catOrganic: "Organic Products", catKits: "Crop Protection Kits",
        stockTitle: "STOCK STATUS", lblAllStock: "All", lblInStock: "In Stock", lblLowStock: "Low Stock",
        pinTitle: "DELIVERY CHECK", pinPlaceholder: "Enter PIN / District",
        pinOk: "✅ Free delivery available in your area (2-4 days).", pinBad: "",
        myOrdersLbl: "My Orders", myCartLbl: "Cart",
        gridOfferHead: "Today's Special Sale!", gridOfferSub: "Get 15% Off on Organic NPK Fertilizers this week! Use code AGRI15 at checkout.",
        statFreeLabel: "Free", offerOffSuffix: "OFF!", offerUseCode: "Use code {code} at checkout.", offerMinOrder: "Valid on orders above ₹{min}.",
        prodFound: "products found", sortBy: "Sort by:",
        sortOptDefault: "Default", sortOptLow: "Price: Low to High", sortOptHigh: "Price: High to Low", sortOptRating: "Rating: High to Low",
        compareLbl: "Compare", wishLbl: "Wishlist", compareLimitMsg: "You can compare only 2 products at a time.",
        cartTitle: "My Cart", cartItems: "Items", cartDel: "Delivery", cartDelFree: "Free", cartTotal: "Total", placeOrder: "Proceed to Checkout",
        emptyCart: "Your cart is empty.", orderSuccess: "Order placed successfully!",
        addedStatus: "Added", addBtnText: "Add to Cart", removeBtnText: "Remove", toastAdd: "Product added to cart!", toastRemove: "Product removed from cart.", buyNowText: "Buy Now",
        couponPlaceholder: "Enter coupon code (AGRI15)", couponBtn: "Apply", discountLbl: "Discount",
        couponOk: "✅ Coupon applied! 15% off.", couponBad: "❌ Invalid coupon code.",
        wishDrawerTitle: "Wishlist", wishEmpty: "No items in wishlist.", removeFromWish: "Remove from Wishlist", saveForLater: "Save for Later",
        wishReminderToast: "An item has been in your wishlist for 15 days!", wishReminderLine: "In your wishlist for {days} days — take another look!", notifEmpty: "No notifications yet.", dismissBtn: "Dismiss",
        compareModalTitle: "Compare Products", cmpImage: "Image", cmpName: "Name", cmpPrice: "Price", cmpUnit: "Unit",
        cmpRating: "Rating", cmpStock: "Stock", cmpSeller: "Seller", cmpCategory: "Category",
        reviewsLbl: "reviews", farmerReviews: "Farmer Reviews", verifiedSeller: "Verified Seller", verifiedBuyer: "Verified Buyer", unitsLbl: "units",
        cropOptDefault: "-- Select Crop --", cropOptTomato: "Tomato", cropOptOnion: "Onion", cropOptChilli: "Chilli",
        cropOptBhendi: "Bhendi (Okra)", cropOptWheat: "Wheat", cropOptCotton: "Cotton", cropOptSugarcane: "Sugarcane", cropOptVeg: "General Vegetables",
        aiAddToCart: "Add to cart", aiNoSuggestions: "No suggestions found.",
        checkoutTitle: "Checkout", ckAddrTitle: "Delivery Address", ckNamePh: "Full Name", ckMobilePh: "Mobile Number", ckPinPh: "PIN Code",
        ckAddressPh: "Full Address (Village, Taluka, District)", ckPayTitle: "Payment Method",
        ckPayCOD: "Cash on Delivery (COD)", ckPayUPI: "UPI (Google Pay / PhonePe / Paytm)", ckPayUPIQR: "Scan & Pay (UPI QR Code)", ckPayCard: "Debit / Credit Card", ckPayNetbanking: "Net Banking", ckPayWallet: "Wallet (Paytm / Amazon Pay / Mobikwik)",
        ckEnterUpi: "Please enter a valid UPI ID.",
        ckUpiIdPh: "Enter UPI ID (e.g. name@bank)", ckCardNumberPh: "Card Number",
        upiQrNote: "Scan this QR using any UPI app to pay {amt}, then tap Confirm Order.",
        ckSummaryTitle: "Order Summary", ckSubtotalLbl: "Subtotal", ckDiscountLbl: "Discount", ckDeliveryLbl: "Delivery", ckTotalLbl: "Total",
        ckConfirmBtn: "Confirm Order", ckFillFields: "Please fill all address fields.", ckInvalidMobile: "Enter a valid 10-digit mobile number.",
        ordersModalTitle: "My Orders", noOrders: "No orders yet.", orderTotalLbl: "Total", orderDiscountLbl: "Discount", removeOrderLbl: "Remove from history", viewInvoiceLbl: "View Invoice", confirmRemoveOrder: "Remove this order from your history? This only removes it from your view — it stays on record with the seller.",
        trackPlaced: "Order Placed", trackPacked: "Packed", trackShipped: "Shipped", trackOutForDelivery: "Out for Delivery", trackDelivered: "Delivered",
        cancelledOrder: "Cancelled", returnedOrder: "Returned", failedOrder: "Failed", paymentPending: "Payment: Pending", demoPayment: "Demo Payment (no real charge)",
        loadingText: "Loading...", networkError: "Network error, please try again.", submittingReview: "Submitting…", reviewUpdated: "Review updated.", reviewSubmitted: "Review submitted.",
        writeReviewLbl: "Write a Review", selectRating: "Please select a star rating first.",
        maxStockReached: "Maximum stock reached ({stock} available)", outOfStockMsg: "This product is out of stock.", onlyXUnitsAvailable: "Only {stock} units available", cartQtyAdjusted: "Cart quantity updated because stock changed.",
        pinInvalid: "Enter a valid 6-digit PIN code or district.", pinAvailable: "Delivery is available in your area.", pinUnavailable: "Delivery is currently unavailable in this area.",
        loginRequired: "Please login to continue.", loginRequiredCart: "Please login to add items to your cart.",
        increaseQtyLabel: "Increase quantity", decreaseQtyLabel: "Decrease quantity", compareCheckboxLabel: "Compare this product", compareLabelShort: "Compare", changeAddressLbl: "Change Address", cancelEditLbl: "Cancel",
        processingOrder: "Processing…", aiSuggestTitle: "You might also need", aiSuggestSubtitle: "Based on what's in your cart",
        noProductsFound: "No Products Found."
    },
    mr: {
        heroBadge: "कृषी स्टोअर", heroTitle: "ई-कॉमर्स कृषी बाजारपेठ",
        heroSub: "प्रमाणित बियाणे, सेंद्रिय खते आणि कीटकनाशके थेट विश्वसनीय विक्रेत्यांकडून खरेदी करा.",
        slide2Tag: "प्रमाणित बियाणे", slide2Title: "उच्च उत्पन्न देणारे हायब्रीड बियाणे व ताज्या भाज्या",
        slide2Sub: "टोमॅटो, कांदा, भेंडी, गाजर आणि इतर हायब्रीड बियाणे — महाराष्ट्रातील प्रमाणित रोपवाटिकांमधून थेट.",
        slide3Tag: "सेंद्रिय खते", slide3Title: "तुमच्या जमिनीचे आरोग्य नैसर्गिकरित्या वाढवा",
        slide3Sub: "व्हर्मीकंपोस्ट, सेंद्रिय खते आणि जैविक पोषक घटक खरेदी करा — विश्वासार्ह गुणवत्ता, अधिक उत्पादनासाठी.",
        slide4Tag: "माती परीक्षण", slide4Title: "मोफत माती आरोग्य तपासणी",
        slide4Sub: "आमचे कृषी तज्ज्ञ प्रत्यक्ष जागेवर मातीची तपासणी करून तुमच्या पिकासाठी योग्य खते व बियाणे सुचवतात.",
        slide5Tag: "घरपोच डिलिव्हरी", slide5Title: "थेट तुमच्या शेतापर्यंत जलद डिलिव्हरी",
        slide5Sub: "गोदामापासून तुमच्या शेतापर्यंत — बियाणे, खते आणि कीटकनाशके वेळेवर पोहोचवले जातात.",
        slide6Tag: "विश्वसनीय विक्रेते", slide6Title: "विश्वसनीय व सत्यापित विक्रेते",
        slide6Sub: "प्रत्येक उत्पादन तुमच्यापर्यंत पोहोचण्यापूर्वी प्रमाणित गोदामांमध्ये गुणवत्ता तपासले जाते.",
        searchPlaceholder: "बियाणे, खते, उपकरणे शोधा...", searchBtn: "शोधा",
        filterDash: "फिल्टर डॅशबोर्ड", filtersToggleLbl: "फिल्टर्स", catTitle: "श्रेणी", priceTitle: "किंमत श्रेणी",
        catAll: "सर्व उत्पादने", catSeeds: "बियाणे", catFert: "खते", catPest: "कीटकनाशके",
        catTools: "शेती अवजारे", catIrr: "सिंचन उत्पादने", catFeed: "पशुखाद्य",
        catOrganic: "सेंद्रिय उत्पादने", catKits: "पीक संरक्षण संच",
        stockTitle: "स्टॉक स्थिती", lblAllStock: "सर्व", lblInStock: "स्टॉक उपलब्ध", lblLowStock: "मर्यादित स्टॉक",
        pinTitle: "डिलिव्हरी तपासणी", pinPlaceholder: "पिन कोड / जिल्हा टाका",
        pinOk: "✅ तुमच्या भागात मोफत डिलिव्हरी उपलब्ध आहे (2-4 दिवस).", pinBad: "",
        myOrdersLbl: "माझ्या ऑर्डर्स", myCartLbl: "कार्ट",
        gridOfferHead: "आजची विशेष ऑफर!", gridOfferSub: "या आठवड्यात सर्व सेंद्रिय खतांवर १५% सूट मिळवा! AGRI15 कोड वापरा.",
        statFreeLabel: "मोफत", offerOffSuffix: "सूट!", offerUseCode: "चेकआउटवेळी {code} कोड वापरा.", offerMinOrder: "₹{min} पेक्षा जास्त ऑर्डरवर लागू.",
        prodFound: "उत्पादने सापडली", sortBy: "क्रमवार:",
        sortOptDefault: "डिफॉल्ट", sortOptLow: "किंमत: कमी ते जास्त", sortOptHigh: "किंमत: जास्त ते कमी", sortOptRating: "रेटिंग: जास्त ते कमी",
        compareLbl: "तुलना", wishLbl: "विशलिस्ट", compareLimitMsg: "एकाच वेळी फक्त 2 उत्पादनांची तुलना करता येते.",
        cartTitle: "माझी कार्ट", cartItems: "एकूण वस्तू", cartDel: "डिलिव्हरी", cartDelFree: "मोफत", cartTotal: "एकूण रक्कम", placeOrder: "चेकआउटकडे जा",
        emptyCart: "तुमची कार्ट रिकामी आहे.", orderSuccess: "ऑर्डर यशस्वीरित्या नोंदवली गेली!",
        addedStatus: "जोडले", addBtnText: "कार्टमध्ये जोडा", removeBtnText: "काढा", toastAdd: "उत्पादन कार्टमध्ये जोडले गेले!", toastRemove: "उत्पादन कार्टमधून काढले गेले.", buyNowText: "आत्ता ऑर्डर करा",
        couponPlaceholder: "कूपन कोड टाका (AGRI15)", couponBtn: "लागू करा", discountLbl: "सूट",
        couponOk: "✅ कूपन लागू झाले! १५% सूट.", couponBad: "❌ चुकीचा कूपन कोड.",
        wishDrawerTitle: "विशलिस्ट", wishEmpty: "विशलिस्टमध्ये काहीही नाही.", removeFromWish: "विशलिस्टमधून काढा", saveForLater: "नंतरसाठी जतन करा",
        compareModalTitle: "उत्पादने तुलना करा", cmpImage: "फोटो", cmpName: "नाव", cmpPrice: "किंमत", cmpUnit: "एकक",
        cmpRating: "रेटिंग", cmpStock: "स्टॉक", cmpSeller: "विक्रेता", cmpCategory: "श्रेणी",
        reviewsLbl: "रिव्ह्यू", farmerReviews: "शेतकरी रिव्ह्यू", verifiedSeller: "सत्यापित विक्रेता", verifiedBuyer: "सत्यापित खरेदीदार", unitsLbl: "नग",
        cropOptDefault: "-- पीक निवडा --", cropOptTomato: "टोमॅटो", cropOptOnion: "कांदा", cropOptChilli: "मिरची",
        cropOptBhendi: "भेंडी", cropOptWheat: "गहू", cropOptCotton: "कापूस", cropOptSugarcane: "ऊस", cropOptVeg: "सर्वसाधारण भाजीपाला",
        aiAddToCart: "कार्टमध्ये जोडा", aiNoSuggestions: "सुचवण्यासारखे काही सापडले नाही.",
        checkoutTitle: "चेकआउट", ckAddrTitle: "डिलिव्हरी पत्ता", ckNamePh: "पूर्ण नाव", ckMobilePh: "मोबाईल नंबर", ckPinPh: "पिन कोड",
        ckAddressPh: "संपूर्ण पत्ता (गाव, तालुका, जिल्हा)", ckPayTitle: "पेमेंट पद्धत",
        ckPayCOD: "कॅश ऑन डिलिव्हरी (COD)", ckPayUPI: "UPI (Google Pay / PhonePe / Paytm)", ckPayUPIQR: "स्कॅन करून पे करा (UPI QR कोड)", ckPayCard: "डेबिट / क्रेडिट कार्ड", ckPayNetbanking: "नेट बँकिंग", ckPayWallet: "वॉलेट (Paytm / Amazon Pay / Mobikwik)",
        ckEnterUpi: "कृपया वैध UPI आयडी टाका.",
        ckUpiIdPh: "UPI आयडी टाका (उदा. name@bank)", ckCardNumberPh: "कार्ड नंबर",
        upiQrNote: "कोणत्याही UPI ॲपने हा QR स्कॅन करून {amt} भरा, नंतर ऑर्डर निश्चित करा वर टॅप करा.",
        ckSummaryTitle: "ऑर्डर सारांश", ckSubtotalLbl: "उपएकूण", ckDiscountLbl: "सूट", ckDeliveryLbl: "डिलिव्हरी", ckTotalLbl: "एकूण",
        ckConfirmBtn: "ऑर्डर निश्चित करा", ckFillFields: "कृपया सर्व पत्ता माहिती भरा.", ckInvalidMobile: "वैध 10-अंकी मोबाईल नंबर टाका.",
        ordersModalTitle: "माझ्या ऑर्डर्स", noOrders: "अद्याप कोणतीही ऑर्डर नाही.", orderTotalLbl: "एकूण", orderDiscountLbl: "सूट", removeOrderLbl: "इतिहासातून काढा", viewInvoiceLbl: "बीजक पहा", confirmRemoveOrder: "ही ऑर्डर तुमच्या इतिहासातून काढायची आहे का? हे फक्त तुमच्या व्ह्यूमधून काढते — विक्रेत्याकडे रेकॉर्ड राहतो.",
        trackPlaced: "ऑर्डर नोंदवली", trackPacked: "पॅक केले", trackShipped: "पाठवले", trackOutForDelivery: "डिलिव्हरीसाठी निघाले", trackDelivered: "डिलिव्हर झाले",
        cancelledOrder: "रद्द केली", returnedOrder: "परत केली", failedOrder: "अयशस्वी", paymentPending: "पेमेंट: प्रलंबित", demoPayment: "डेमो पेमेंट (खरी रक्कम कापली जाणार नाही)",
        loadingText: "लोड होत आहे...", networkError: "नेटवर्क एरर, पुन्हा प्रयत्न करा.", submittingReview: "सबमिट करत आहे…", reviewUpdated: "रिव्ह्यू अपडेट झाला.", reviewSubmitted: "रिव्ह्यू सबमिट झाला.",
        writeReviewLbl: "रिव्ह्यू लिहा", selectRating: "कृपया आधी स्टार रेटिंग निवडा.", notifEmpty: "अद्याप कोणतीही सूचना नाही.", dismissBtn: "काढून टाका",
        wishReminderToast: "तुमच्या विशलिस्टमधील एक वस्तू 15 दिवसांपासून तशीच आहे!", wishReminderLine: "{days} दिवसांपासून विशलिस्टमध्ये आहे — पुन्हा पाहा!",
        maxStockReached: "जास्तीत जास्त स्टॉक झाला ({stock} उपलब्ध)", outOfStockMsg: "हे उत्पादन सध्या स्टॉकमध्ये नाही.", onlyXUnitsAvailable: "फक्त {stock} युनिट्स उपलब्ध", cartQtyAdjusted: "स्टॉक बदलल्यामुळे कार्ट क्वांटिटी अपडेट केली.",
        pinInvalid: "वैध 6-अंकी पिन कोड किंवा जिल्हा टाका.", pinAvailable: "तुमच्या भागात डिलिव्हरी उपलब्ध आहे.", pinUnavailable: "या भागात सध्या डिलिव्हरी उपलब्ध नाही.",
        loginRequired: "पुढे जाण्यासाठी कृपया login करा.", loginRequiredCart: "कार्टमध्ये वस्तू टाकण्यासाठी कृपया login करा.",
        increaseQtyLabel: "प्रमाण वाढवा", decreaseQtyLabel: "प्रमाण कमी करा", compareCheckboxLabel: "हे उत्पादन तुलना करा", compareLabelShort: "तुलना", changeAddressLbl: "पत्ता बदला", cancelEditLbl: "रद्द करा",
        processingOrder: "प्रक्रिया सुरू आहे…", aiSuggestTitle: "तुम्हाला हे देखील लागू शकते", aiSuggestSubtitle: "तुमच्या कार्टवर आधारित",
        noProductsFound: "कोणतेही उत्पादन सापडले नाही."
    },
    hi: {
        heroBadge: "एग्री स्टोर", heroTitle: "ई-कॉमर्स कृषि बाजार",
        heroSub: "प्रमाणित बीज, जैविक खाद और कीटनाशक सीधे विश्वसनीय विक्रेताओं से खरीदें.",
        slide2Tag: "प्रमाणित बीज", slide2Title: "उच्च उपज देने वाले हाइब्रिड बीज व ताज़ी सब्जियां",
        slide2Sub: "टमाटर, प्याज, भिंडी, गाजर और अन्य हाइब्रिड बीज — महाराष्ट्र की प्रमाणित नर्सरियों से सीधे।",
        slide3Tag: "जैविक खाद", slide3Title: "अपनी मिट्टी का स्वास्थ्य प्राकृतिक रूप से बढ़ाएं",
        slide3Sub: "वर्मीकम्पोस्ट, जैविक खाद और बायो न्यूट्रिएंट्स खरीदें — विश्वसनीय गुणवत्ता, बेहतर उपज के लिए।",
        slide4Tag: "मिट्टी परीक्षण", slide4Title: "मुफ्त मिट्टी स्वास्थ्य जांच",
        slide4Sub: "हमारे कृषि विशेषज्ञ मौके पर मिट्टी की जांच कर आपकी फसल के लिए सही खाद व बीज सुझाते हैं।",
        slide5Tag: "घर-द्वार डिलीवरी", slide5Title: "सीधे आपके खेत तक तेज़ डिलीवरी",
        slide5Sub: "गोदाम से आपके खेत तक — बीज, खाद और कीटनाशक समय पर पहुंचाए जाते हैं।",
        slide6Tag: "सत्यापित विक्रेता", slide6Title: "विश्वसनीय व सत्यापित विक्रेता",
        slide6Sub: "हर उत्पाद आप तक पहुंचने से पहले प्रमाणित गोदामों में गुणवत्ता जांचा जाता है।",
        searchPlaceholder: "बीज, खाद, उपकरण खोजें...", searchBtn: "खोजें",
        filterDash: "फ़िल्टर डैशबोर्ड", filtersToggleLbl: "फ़िल्टर", catTitle: "श्रेणी", priceTitle: "मूल्य सीमा",
        catAll: "सभी उत्पाद", catSeeds: "बीज", catFert: "खाद", catPest: "कीटनाशक",
        catTools: "कृषि औजार", catIrr: "सिंचाई उत्पाद", catFeed: "पशु आहार",
        catOrganic: "जैविक उत्पाद", catKits: "फसल सुरक्षा किट",
        stockTitle: "स्टॉक स्थिति", lblAllStock: "सभी", lblInStock: "स्टॉक उपलब्ध", lblLowStock: "सीमित स्टॉक",
        pinTitle: "डिलीवरी जाँच", pinPlaceholder: "पिन कोड / जिला दर्ज करें",
        pinOk: "✅ आपके क्षेत्र में मुफ्त डिलीवरी उपलब्ध है (2-4 दिन).", pinBad: "",
        myOrdersLbl: "मेरे ऑर्डर", myCartLbl: "कार्ट",
        gridOfferHead: "आज की विशेष छूट!", gridOfferSub: "इस सप्ताह जैविक NPK खाद पर 15% छूट पाएं! कोड AGRI15 इस्तेमाल करें.",
        statFreeLabel: "मुफ्त", offerOffSuffix: "छूट!", offerUseCode: "चेकआउट पर {code} कोड इस्तेमाल करें.", offerMinOrder: "₹{min} से अधिक के ऑर्डर पर मान्य.",
        prodFound: "उत्पाद मिले", sortBy: "क्रमबद्ध करें:",
        sortOptDefault: "डिफ़ॉल्ट", sortOptLow: "मूल्य: कम से अधिक", sortOptHigh: "मूल्य: अधिक से कम", sortOptRating: "रेटिंग: अधिक से कम",
        compareLbl: "तुलना", wishLbl: "विशलिस्ट", compareLimitMsg: "आप एक बार में केवल 2 उत्पादों की तुलना कर सकते हैं.",
        cartTitle: "मेरी कार्ट", cartItems: "कुल वस्तुएं", cartDel: "डिलीवरी", cartDelFree: "मुफ्त", cartTotal: "कुल राशि", placeOrder: "चेकआउट करें",
        emptyCart: "आपकी कार्ट खाली है.", orderSuccess: "ऑर्डर सफलतापूर्वक दर्ज हो गया!",
        addedStatus: "जोड़ा", addBtnText: "कार्ट में जोड़ें", removeBtnText: "हटाएं", toastAdd: "उत्पाद कार्ट में जोड़ा गया!", toastRemove: "उत्पाद कार्ट से हटाया गया.", buyNowText: "अभी ऑर्डर करें",
        couponPlaceholder: "कूपन कोड दर्ज करें (AGRI15)", couponBtn: "लागू करें", discountLbl: "छूट",
        couponOk: "✅ कूपन लागू हुआ! 15% छूट.", couponBad: "❌ अमान्य कूपन कोड.",
        wishDrawerTitle: "विशलिस्ट", wishEmpty: "विशलिस्ट में कोई वस्तु नहीं है.", removeFromWish: "विशलिस्ट से हटाएं", saveForLater: "बाद के लिए सहेजें",
        compareModalTitle: "उत्पादों की तुलना करें", cmpImage: "फोटो", cmpName: "नाम", cmpPrice: "मूल्य", cmpUnit: "इकाई",
        cmpRating: "रेटिंग", cmpStock: "स्टॉक", cmpSeller: "विक्रेता", cmpCategory: "श्रेणी",
        reviewsLbl: "समीक्षाएं", farmerReviews: "किसान समीक्षाएं", verifiedSeller: "सत्यापित विक्रेता", verifiedBuyer: "सत्यापित खरीदार", unitsLbl: "इकाइयां",
        cropOptDefault: "-- फसल चुनें --", cropOptTomato: "टमाटर", cropOptOnion: "प्याज", cropOptChilli: "मिर्च",
        cropOptBhendi: "भिंडी", cropOptWheat: "गेहूं", cropOptCotton: "कपास", cropOptSugarcane: "गन्ना", cropOptVeg: "सामान्य सब्जियां",
        aiAddToCart: "कार्ट में जोड़ें", aiNoSuggestions: "कोई सुझाव नहीं मिला.",
        checkoutTitle: "चेकआउट", ckAddrTitle: "डिलीवरी पता", ckNamePh: "पूरा नाम", ckMobilePh: "मोबाइल नंबर", ckPinPh: "पिन कोड",
        ckAddressPh: "पूरा पता (गांव, तालुका, जिला)", ckPayTitle: "भुगतान विधि",
        ckPayCOD: "कैश ऑन डिलीवरी (COD)", ckPayUPI: "UPI (Google Pay / PhonePe / Paytm)", ckPayUPIQR: "स्कैन करके भुगतान करें (UPI QR कोड)", ckPayCard: "डेबिट / क्रेडिट कार्ड", ckPayNetbanking: "नेट बैंकिंग", ckPayWallet: "वॉलेट (Paytm / Amazon Pay / Mobikwik)",
        ckEnterUpi: "कृपया मान्य UPI आईडी दर्ज करें.",
        ckUpiIdPh: "UPI आईडी दर्ज करें (जैसे name@bank)", ckCardNumberPh: "कार्ड नंबर",
        upiQrNote: "किसी भी UPI ऐप से यह QR स्कैन करके {amt} भुगतान करें, फिर ऑर्डर की पुष्टि करें पर टैप करें.",
        ckSummaryTitle: "ऑर्डर सारांश", ckSubtotalLbl: "उप-योग", ckDiscountLbl: "छूट", ckDeliveryLbl: "डिलीवरी", ckTotalLbl: "कुल",
        ckConfirmBtn: "ऑर्डर की पुष्टि करें", ckFillFields: "कृपया सभी पता फ़ील्ड भरें.", ckInvalidMobile: "मान्य 10-अंकीय मोबाइल नंबर दर्ज करें.",
        ordersModalTitle: "मेरे ऑर्डर", noOrders: "अभी तक कोई ऑर्डर नहीं.", orderTotalLbl: "कुल", orderDiscountLbl: "छूट", removeOrderLbl: "इतिहास से हटाएं", viewInvoiceLbl: "इनवॉइस देखें", confirmRemoveOrder: "इस ऑर्डर को अपने इतिहास से हटाना है? यह केवल आपके व्यू से हटेगा — विक्रेता के पास रिकॉर्ड रहेगा।",
        trackPlaced: "ऑर्डर दर्ज हुआ", trackPacked: "पैक किया गया", trackShipped: "भेजा गया", trackOutForDelivery: "डिलीवरी के लिए निकला", trackDelivered: "डिलीवर हो गया",
        cancelledOrder: "रद्द", returnedOrder: "वापस की गई", failedOrder: "विफल", paymentPending: "भुगतान: लंबित", demoPayment: "डेमो भुगतान (कोई वास्तविक कटौती नहीं)",
        loadingText: "लोड हो रहा है...", networkError: "नेटवर्क त्रुटि, कृपया पुनः प्रयास करें.", submittingReview: "सबमिट हो रहा है…", reviewUpdated: "समीक्षा अपडेट की गई.", reviewSubmitted: "समीक्षा सबमिट की गई.",
        writeReviewLbl: "समीक्षा लिखें", selectRating: "कृपया पहले स्टार रेटिंग चुनें.", notifEmpty: "अभी तक कोई सूचना नहीं.", dismissBtn: "हटाएं",
        wishReminderToast: "आपकी विशलिस्ट में एक उत्पाद 15 दिनों से वैसे ही पड़ा है!", wishReminderLine: "{days} दिनों से विशलिस्ट में है — फिर से देखें!",
        maxStockReached: "अधिकतम स्टॉक पहुँच गया ({stock} उपलब्ध)", outOfStockMsg: "यह उत्पाद स्टॉक में नहीं है.", onlyXUnitsAvailable: "केवल {stock} यूनिट उपलब्ध", cartQtyAdjusted: "स्टॉक बदलने के कारण कार्ट मात्रा अपडेट की गई.",
        pinInvalid: "वैध 6-अंकीय पिन कोड या ज़िला दर्ज करें.", pinAvailable: "आपके क्षेत्र में डिलीवरी उपलब्ध है.", pinUnavailable: "इस क्षेत्र में फ़िलहाल डिलीवरी उपलब्ध नहीं है.",
        loginRequired: "जारी रखने के लिए कृपया login करें.", loginRequiredCart: "कार्ट में सामान जोड़ने के लिए कृपया login करें.",
        increaseQtyLabel: "मात्रा बढ़ाएं", decreaseQtyLabel: "मात्रा घटाएं", compareCheckboxLabel: "इस उत्पाद की तुलना करें", compareLabelShort: "तुलना", changeAddressLbl: "पता बदलें", cancelEditLbl: "रद्द करें",
        processingOrder: "प्रोसेसिंग हो रही है…", aiSuggestTitle: "आपको यह भी चाहिए हो सकता है", aiSuggestSubtitle: "आपकी कार्ट के आधार पर",
        noProductsFound: "कोई उत्पाद नहीं मिला."
    }
};

/* =====================================================================
   PRODUCT CATALOGUE
   Loaded from MySQL (see the single JOIN query above — products + review
   aggregates in one pass). Cart/wishlist are cached client-side in
   localStorage (per-user, see STORAGE_USER_KEY below) purely for a snappy
   UI; the server is always the source of truth for price, stock and order
   totals (place_order.php recalculates everything and never trusts the
   browser — see security notes).
   ===================================================================== */
// Real products from the database (products table), injected by PHP
const BASE_PRODUCTS = <?php echo $productsJson; ?>;

const CROP_SUGGESTIONS = {
    tomato:[1,5,7,8], onion:[3,5,6], chilli:[4,8,16], bhendi:[2,5,7],
    wheat:[5,6,12], cotton:[6,9,12,16], sugarcane:[5,6,12], vegetables:[7,8,12,16]
};

const SERVICEABLE_DISTRICTS = ["nashik","pune","nagpur","aurangabad","kolhapur","satara","sangli","solapur","ahmednagar","jalgaon","amravati","akola","latur","nanded","thane","422","411","440","431","416","415"];

const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const CSRF_TOKEN = <?php echo json_encode($csrfToken); ?>;
const SAVED_ADDRESS = <?php echo $savedAddress ? json_encode([
    'name' => $savedAddress['saved_name'],
    'mobile' => $savedAddress['saved_mobile'],
    'pin' => $savedAddress['saved_pincode'],
    'address' => $savedAddress['saved_address'],
]) : 'null'; ?>;

// Cart/wishlist localStorage keys are namespaced per logged-in user (or a
// shared "guest" bucket) so one browser profile can't leak/mix carts
// between different accounts on the same device.
const STORAGE_USER_KEY = <?php echo json_encode($isLoggedIn ? ('u' . (int)$_SESSION['user_id']) : 'guest'); ?>;
const K_CART = 'agri_cart_' + STORAGE_USER_KEY;
const K_WISH = 'agri_wishlist_' + STORAGE_USER_KEY;
const K_WISH_DATES = 'agri_wishlist_dates_' + STORAGE_USER_KEY;
const K_WISH_NOTIF = 'agri_wish_notifications_' + STORAGE_USER_KEY;

// One-time merge: if this user just logged in and had items sitting in the
// anonymous "guest" cart/wishlist on this device, fold them into their
// account cart (quantities added together) instead of losing them.
(function mergeGuestCartOnLogin(){
    if (!IS_LOGGED_IN) return;
    const mergedFlag = 'agri_guest_merged_' + STORAGE_USER_KEY;
    if (localStorage.getItem(mergedFlag)) return;
    try {
        const guestCart = JSON.parse(localStorage.getItem('agri_cart_guest') || '{}');
        const guestWish = JSON.parse(localStorage.getItem('agri_wishlist_guest') || '[]');
        const guestWishDates = JSON.parse(localStorage.getItem('agri_wishlist_dates_guest') || '{}');
        if (Object.keys(guestCart).length) {
            const userCart = JSON.parse(localStorage.getItem(K_CART) || '{}');
            Object.keys(guestCart).forEach(id => {
                if (userCart[id]) userCart[id].qty = (userCart[id].qty || 0) + (guestCart[id].qty || 0);
                else userCart[id] = guestCart[id];
            });
            localStorage.setItem(K_CART, JSON.stringify(userCart));
        }
        if (guestWish.length) {
            const userWish = JSON.parse(localStorage.getItem(K_WISH) || '[]');
            const userWishDates = JSON.parse(localStorage.getItem(K_WISH_DATES) || '{}');
            guestWish.forEach(id => { if (!userWish.includes(id)) userWish.push(id); if (!userWishDates[id]) userWishDates[id] = guestWishDates[id] || Date.now(); });
            localStorage.setItem(K_WISH, JSON.stringify(userWish));
            localStorage.setItem(K_WISH_DATES, JSON.stringify(userWishDates));
        }
        localStorage.removeItem('agri_cart_guest');
        localStorage.removeItem('agri_wishlist_guest');
        localStorage.removeItem('agri_wishlist_dates_guest');
        localStorage.setItem(mergedFlag, '1');
    } catch (eMerge) { /* corrupt guest data — ignore, nothing to merge */ }
})();

let cart = JSON.parse(localStorage.getItem(K_CART) || '{}');
let wishlist = JSON.parse(localStorage.getItem(K_WISH) || '[]');
// Timestamp (ms) each product was added to the wishlist — used to trigger the
// "still in your wishlist after 15 days" reminder notification.
let wishlistDates = JSON.parse(localStorage.getItem(K_WISH_DATES) || '{}');
// Reminders already raised, so the same product doesn't re-notify on every reload.
let wishNotifications = JSON.parse(localStorage.getItem(K_WISH_NOTIF) || '[]');
let compareList = [];
let appliedCoupon = null;
let appliedCouponData = null;
let currentCat = 'all', currentStock = 'all', currentSort = 'default', maxPrice = 3000, searchTerm = '';
let pendingOrderTotal = 0, pendingOrderDiscount = 0;

function PRODUCTS_ALL(){ return BASE_PRODUCTS; }

// Clean up any stale cart/wishlist entries pointing to products that no longer
// exist (e.g. removed from the DB) — these used to silently crash checkout.
// Also clamps any cached quantity down to the product's current DB stock,
// in case stock dropped since it was added to the cart.
(function cleanStaleCartEntries(){
    let changed = false;
    Object.keys(cart).forEach(id=>{
        const p = PRODUCTS_ALL().find(pr=>pr.id==id);
        if(!p){ delete cart[id]; changed = true; return; }
        if (p.stock <= 0) { delete cart[id]; changed = true; return; }
        if (cart[id].qty > p.stock) { cart[id].qty = p.stock; changed = true; }
    });
    const cleanWish = wishlist.filter(id=>PRODUCTS_ALL().find(pr=>pr.id==id));
    if(cleanWish.length !== wishlist.length){ wishlist = cleanWish; changed = true; }
    // Backfill a missing addedAt date for anything already wishlisted before
    // this feature existed, so old items don't instantly count as "15 days old".
    let datesChanged = false;
    wishlist.forEach(id=>{ if(!wishlistDates[id]){ wishlistDates[id] = Date.now(); datesChanged = true; } });
    Object.keys(wishlistDates).forEach(id=>{ if(!wishlist.includes(Number(id)) && !wishlist.includes(id)){ delete wishlistDates[id]; datesChanged = true; } });
    if(changed){
        localStorage.setItem(K_CART, JSON.stringify(cart));
        localStorage.setItem(K_WISH, JSON.stringify(wishlist));
    }
    if(datesChanged){ localStorage.setItem(K_WISH_DATES, JSON.stringify(wishlistDates)); }
})();

function saveState(){
    localStorage.setItem(K_CART, JSON.stringify(cart));
    localStorage.setItem(K_WISH, JSON.stringify(wishlist));
    localStorage.setItem(K_WISH_DATES, JSON.stringify(wishlistDates));
}

function setText(id, val){ const el = document.getElementById(id); if(el) el.textContent = val; }
function setPH(id, val){ const el = document.getElementById(id); if(el) el.placeholder = val; }

function pageLanguageCallback(currentLang) {
    const pt = StoreT[currentLang];
    window.lang = currentLang;

    setText('heroBadge', pt.heroBadge); setText('heroTitle', pt.heroTitle); setText('heroSub', pt.heroSub);
    setText('slide2Tag', pt.slide2Tag); setText('slide2Title', pt.slide2Title); setText('slide2Sub', pt.slide2Sub);
    setText('slide3Tag', pt.slide3Tag); setText('slide3Title', pt.slide3Title); setText('slide3Sub', pt.slide3Sub);
    setText('slide4Tag', pt.slide4Tag); setText('slide4Title', pt.slide4Title); setText('slide4Sub', pt.slide4Sub);
    setText('slide5Tag', pt.slide5Tag); setText('slide5Title', pt.slide5Title); setText('slide5Sub', pt.slide5Sub);
    setText('slide6Tag', pt.slide6Tag); setText('slide6Title', pt.slide6Title); setText('slide6Sub', pt.slide6Sub);
    setPH('searchInput', pt.searchPlaceholder); setText('searchBtnText', pt.searchBtn);

    setText('filterDashTitle', pt.filterDash); setText('filtersToggleLbl', pt.filtersToggleLbl); setText('catTitle', pt.catTitle); setText('priceTitle', pt.priceTitle);
    setText('catAll', pt.catAll); setText('catSeeds', pt.catSeeds); setText('catFert', pt.catFert);
    setText('catPest', pt.catPest); setText('catTools', pt.catTools); setText('catIrr', pt.catIrr);
    setText('catFeed', pt.catFeed); setText('catOrganic', pt.catOrganic); setText('catKits', pt.catKits);

    setText('stockTitle', pt.stockTitle); setText('lblAllStock', pt.lblAllStock);
    setText('lblInStock', pt.lblInStock); setText('lblLowStock', pt.lblLowStock);
    setText('pinTitle', pt.pinTitle); setPH('pinInput', pt.pinPlaceholder);
    setText('myOrdersLbl', pt.myOrdersLbl);
    setText('myCartLbl', pt.myCartLbl);

    setText('gridOfferHeading', pt.gridOfferHead); setText('gridOfferSub', pt.gridOfferSub);
    setText('statFreeLabel', pt.statFreeLabel);
    setText('prodFoundText', pt.prodFound); setText('sortByText', pt.sortBy);
    setText('compareLbl', pt.compareLbl); setText('wishLbl', pt.wishLbl);
    setText('sortOptDefault', pt.sortOptDefault); setText('sortOptLow', pt.sortOptLow);
    setText('sortOptHigh', pt.sortOptHigh); setText('sortOptRating', pt.sortOptRating);

    setText('cartWidgetTitle', pt.cartTitle); setText('cartItemsLabel', pt.cartItems);
    setText('cartDelLabel', pt.cartDel); setText('cartDelStatus', pt.cartDelFree);
    setText('cartTotalLabel', pt.cartTotal); setText('placeOrderBtnText', pt.placeOrder);
    setPH('couponInput', pt.couponPlaceholder); setText('couponBtnText', pt.couponBtn);
    setText('discountLbl', pt.discountLbl);

    setText('wishDrawerTitle', pt.wishDrawerTitle);
    setText('compareModalTitle', pt.compareModalTitle);

    setText('cropOptDefault', pt.cropOptDefault); setText('cropOptTomato', pt.cropOptTomato);
    setText('cropOptOnion', pt.cropOptOnion); setText('cropOptChilli', pt.cropOptChilli);
    setText('cropOptBhendi', pt.cropOptBhendi); setText('cropOptWheat', pt.cropOptWheat);
    setText('cropOptCotton', pt.cropOptCotton); setText('cropOptSugarcane', pt.cropOptSugarcane);
    setText('cropOptVeg', pt.cropOptVeg);
    setText('aiSuggestTitle', currentLang==='mr' ? 'AI उत्पादन सूचना' : currentLang==='hi' ? 'AI उत्पाद सुझाव' : 'AI Product Suggestion');
    setText('aiSuggestSub', currentLang==='mr' ? 'तुमचे पीक निवडा आणि लगेच योग्य बियाणे व खते मिळवा.' : currentLang==='hi' ? 'अपनी फसल चुनें और तुरंत उपयुक्त बीज व खाद पाएं.' : 'Select your crop and get suitable seeds & fertilizers instantly.');

    setText('checkoutTitle', pt.checkoutTitle); setText('ckAddrTitle', pt.ckAddrTitle);
    setPH('ckName', pt.ckNamePh); setPH('ckMobile', pt.ckMobilePh); setPH('ckPin', pt.ckPinPh);
    setPH('ckAddress', pt.ckAddressPh); setText('ckPayTitle', pt.ckPayTitle);
    setText('ckPayCOD', pt.ckPayCOD); setText('ckPayUPI', pt.ckPayUPI); setText('ckPayUPIQR', pt.ckPayUPIQR);
    setText('ckPayCard', pt.ckPayCard); setText('ckPayNetbanking', pt.ckPayNetbanking); setText('ckPayWallet', pt.ckPayWallet);
    setPH('ckUpiId', pt.ckUpiIdPh); setPH('ckCardNumber', pt.ckCardNumberPh);
    setText('ckSummaryTitle', pt.ckSummaryTitle); setText('ckSubtotalLbl', pt.ckSubtotalLbl);
    setText('ckDiscountLbl', pt.ckDiscountLbl); setText('ckDeliveryLbl', pt.ckDeliveryLbl);
    setText('ckDeliveryFree', pt.cartDelFree); setText('ckTotalLbl', pt.ckTotalLbl); setText('ckConfirmBtn', pt.ckConfirmBtn);

    setText('ordersModalTitle', pt.ordersModalTitle);



    updateCounts();
    renderProducts();
    renderCartBody();
    updateCartBadge();
    updateWishBadge();
    loadCouponsBanner();
    refreshOfferStrip();
}

function localName(p){ return (window.lang==='mr')?p.nameMr:(window.lang==='hi')?p.nameHi:p.nameEn; }
function localUnit(p){ return (window.lang==='mr')?p.unitMr:(window.lang==='hi')?p.unitHi:p.unitEn; }
function localBadge(p){ return (window.lang==='mr')?p.badgeMr:(window.lang==='hi')?p.badgeHi:p.badgeEn; }
function localDesc(p){ return (window.lang==='mr')?p.descMr:(window.lang==='hi')?p.descHi:p.descEn; }

function stockStatus(p){
    if(p.stock<=0) return 'out';
    if(p.stock<=10) return 'low';
    return 'in';
}
function stockLabel(st){
    const map = { in:{en:'In Stock',mr:'स्टॉक उपलब्ध',hi:'स्टॉक उपलब्ध'}, low:{en:'Low Stock',mr:'मर्यादित स्टॉक',hi:'सीमित स्टॉक'}, out:{en:'Out of Stock',mr:'स्टॉक संपला',hi:'स्टॉक समाप्त'} };
    return map[st][window.lang || 'en'];
}

function updateCounts(){
    const all = PRODUCTS_ALL();
    const cats = ['all','seeds','fertilizer','pesticides','tools','irrigation','feed','organic','cropkits'];
    cats.forEach(c=>{
        const el = document.getElementById('cnt-'+c);
        if(el) el.textContent = c==='all' ? all.length : all.filter(p=>p.cat===c).length;
    });
    const sc = document.getElementById('statProductsCount');
    if(sc) sc.textContent = all.length >= 200 ? (all.length + '+') : String(all.length);
}

function filterCat(cat, element) {
    currentCat = cat;
    document.querySelectorAll('.sidebar .cat-item').forEach(el => el.classList.remove('active'));
    if(element) element.classList.add('active');
    renderProducts();
}
function filterStock(st, element){
    currentStock = st;
    document.querySelectorAll('.sidebar-section .cat-item').forEach(el=>{ if(el.onclick && el.onclick.toString().includes('filterStock')) el.classList.remove('active'); });
    if(element) element.classList.add('active');
    renderProducts();
}
function updatePrice(val) {
    maxPrice = parseInt(val);
    document.getElementById('priceVal').textContent = '₹' + maxPrice.toLocaleString();
    renderProducts();
}
function doSearch() {
    searchTerm = document.getElementById('searchInput').value.trim();
    renderProducts();
}
function doSort(val) { currentSort = val; renderProducts(); }

function getFilteredAndSorted() {
    let list = PRODUCTS_ALL().filter(p => {
        const catOk = currentCat === 'all' || p.cat === currentCat;
        const priceOk = p.price <= maxPrice;
        const stOk = currentStock==='all' || stockStatus(p)===currentStock;
        const name = localName(p);
        const searchOk = !searchTerm || name.toLowerCase().includes(searchTerm.toLowerCase());
        return catOk && priceOk && stOk && searchOk;
    });
    if (currentSort === 'price-low') list.sort((a,b) => a.price - b.price);
    else if (currentSort === 'price-high') list.sort((a,b) => b.price - a.price);
    else if (currentSort === 'rating') list.sort((a,b) => b.rating - a.rating);
    return list;
}

function cardQty(id){ return window._cardQty && window._cardQty[id] ? window._cardQty[id] : 1; }
function setCardQty(id, val){
    window._cardQty = window._cardQty || {};
    const p = PRODUCTS_ALL().find(pr=>pr.id==id);
    const max = p ? Math.max(1, p.stock) : 999;
    const clamped = Math.max(1, Math.min(val, max));
    if (p && val > p.stock) { showToast((StoreT[window.lang||'en'].maxStockReached || 'Maximum stock reached ({stock} available)').replace('{stock}', p.stock)); }
    window._cardQty[id] = clamped;
    const el = document.getElementById('qtyVal-'+id);
    if(el) el.textContent = clamped;
}
/* Product already in cart -> +/- adjusts the real cart quantity (and removes
   the item once it hits 0). Not yet in cart -> +/- only changes the staged
   quantity that will be used the next time "Add to Cart" is pressed.
   Both paths are clamped to the product's current DB stock. */
function incDecQty(id, delta){
    const p = PRODUCTS_ALL().find(pr=>pr.id==id);
    if (p && p.stock <= 0) { showToast(StoreT[window.lang||'en'].outOfStockMsg || 'This product is out of stock.'); return; }
    if (cart[id] && cart[id].qty > 0) {
        changeQty(id, delta);
    } else {
        setCardQty(id, cardQty(id) + delta);
    }
}

function renderProducts() {
    const list = getFilteredAndSorted();
    document.getElementById('resultCount').textContent = list.length;
    const grid = document.getElementById('productsGrid');

    if(list.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:2rem; color:#666;">${StoreT[window.lang||'en'].noProductsFound}</div>`;
        return;
    }
    const pt = StoreT[window.lang || 'en'];

    grid.innerHTML = list.map(p => {
        const name = escapeHTML(localName(p)), unit = escapeHTML(localUnit(p)), badge = escapeHTML(localBadge(p) || '');
        const seller = escapeHTML(p.seller || ''), location = escapeHTML(p.location || '');
        const inCart = cart[p.id] && cart[p.id].qty > 0;
        const st = stockStatus(p);
        const isWished = wishlist.includes(p.id);
        const isCompared = compareList.includes(p.id);
        const qty = inCart ? cart[p.id].qty : cardQty(p.id);
        const wishAriaLabel = isWished ? (pt.removeFromWish || 'Remove from Wishlist') : (pt.saveForLater || 'Add to Wishlist');
        return `<div class="product-card">
            ${badge ? `<div class="product-badge">${badge}</div>` : ''}
            <label class="compare-check"><input type="checkbox" ${isCompared?'checked':''} onchange="toggleCompare(${p.id})" aria-label="${pt.compareCheckboxLabel || 'Compare this product'}"> ${pt.compareLabelShort || 'Compare'}</label>
            <button type="button" class="wish-heart ${isWished?'active':''}" onclick="toggleWishlist(${p.id})" aria-label="${wishAriaLabel}" aria-pressed="${isWished}"><i class="fa-solid fa-heart"></i></button>
            <div class="product-img-wrap" style="position:relative">
                <img class="product-img-real" ${productImgAttrs(p.image, name)} onclick="openProductModal(${p.id})" style="cursor:pointer">
                <span class="stock-tag ${st}">${escapeHTML(stockLabel(st))}</span>
            </div>
            <div class="product-body">
                <button type="button" class="product-name" style="cursor:pointer;background:none;border:none;text-align:left;padding:0" onclick="openProductModal(${p.id})">${name}</button>
                <div class="seller-line"><i class="fa-solid fa-store"></i> ${seller} ${p.verified?'<span class="verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>':''}</div>
                <div class="seller-line" style="${location ? '' : 'visibility:hidden'}"><i class="fa-solid fa-location-dot"></i> ${location || '\u00A0'} ${(location && p.delivery) ? '· <i class="fa-solid fa-truck"></i> ' + escapeHTML(pt.deliveryAvailableText || 'Delivery available') : ''}</div>
                <div class="rating-row">${renderStars(p.rating)} ${p.rating} (${p.reviews})</div>
                <div class="product-unit">${unit}</div>
                <div class="price-row">
                    <span class="price-now">₹${p.price}</span>
                    ${p.oldPrice && p.oldPrice > p.price ? `<span class="price-old">₹${p.oldPrice}</span> <span class="discount-pct">${Math.round((1 - p.price / p.oldPrice) * 100)}% OFF</span>` : ''}
                </div>
                <div class="qty-selector">
                    <button type="button" onclick="incDecQty(${p.id}, -1)" aria-label="${pt.decreaseQtyLabel || 'Decrease quantity'}">-</button>
                    <span id="qtyVal-${p.id}" aria-live="polite">${qty}</span>
                    <button type="button" onclick="incDecQty(${p.id}, 1)" aria-label="${pt.increaseQtyLabel || 'Increase quantity'}">+</button>
                </div>
                <div class="stock-msg-line">${st==='low' ? (pt.onlyXUnitsAvailable || 'Only {stock} units available').replace('{stock}', p.stock) : '\u00A0'}</div>
                <div class="pc-btn-row" style="display:flex;gap:6px">
                    <button type="button" class="add-btn" ${(st==='out' && !inCart)?'disabled style="opacity:0.5;cursor:not-allowed"':(inCart?'style="background:#fff;color:var(--primary);border:1.5px solid var(--primary)"':'')} onclick="toggleCartItem(${p.id}, cardQty(${p.id}))">
                        <i class="fa-solid ${inCart?'fa-trash':'fa-cart-plus'}"></i> ${inCart ? pt.removeBtnText : pt.addBtnText}
                    </button>
                    <button type="button" class="buy-now-btn" ${st==='out'?'disabled style="opacity:0.5;cursor:not-allowed"':''} onclick="buyNow(${p.id}, cardQty(${p.id}))">
                        <i class="fa-solid fa-bolt"></i> ${pt.buyNowText}
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');
}

/* ---------------- CART ---------------- */
// Cart is login-gated: a guest should never see what's inside someone's
// cart, and shouldn't be able to build one anonymously either — so every
// path that adds to the cart checks login first and sends guests to login.
function requireLoginForCart(){
    if (IS_LOGGED_IN) return true;
    const pt = StoreT[window.lang || 'en'];
    showToast(pt.loginRequiredCart || pt.loginRequired || 'Please login to add items to your cart.');
    window.location.href = 'login.php';
    return false;
}
function toggleCartItem(id, qty) {
    if (cart[id] && cart[id].qty > 0) {
        removeItem(id);
    } else {
        addToCart(id, qty);
    }
}
function addToCart(id, qty) {
    if (!requireLoginForCart()) return;
    qty = qty || 1;
    const p = PRODUCTS_ALL().find(x=>x.id==id);
    const pt = StoreT[window.lang || 'en'];
    if(!p) return;
    if (stockStatus(p)==='out') { showToast(pt.outOfStockMsg || 'This product is out of stock.'); return; }
    if (!cart[id]) cart[id] = { qty: 0 };
    const requested = cart[id].qty + qty;
    cart[id].qty = Math.min(requested, p.stock);
    if (requested > p.stock) { showToast((pt.maxStockReached || 'Maximum stock reached ({stock} available)').replace('{stock}', p.stock)); }
    saveState();
    updateCartBadge();
    renderProducts();
    renderCartBody();
    showToast(pt.toastAdd);
}
/* Buy Now — add this single product to cart (if not already there) and jump straight to checkout */
function buyNow(id, qty) {
    if (!requireLoginForCart()) return;
    qty = qty || 1;
    const p = PRODUCTS_ALL().find(x=>x.id==id);
    const pt = StoreT[window.lang || 'en'];
    if(!p) return;
    if (stockStatus(p)==='out') { showToast(pt.outOfStockMsg || 'This product is out of stock.'); return; }
    if (!cart[id]) cart[id] = { qty: 0 };
    cart[id].qty = Math.min(Math.max(cart[id].qty, qty), p.stock);
    saveState();
    updateCartBadge();
    renderProducts();
    renderCartBody();
    goToCheckout();
}
// Widget is hidden entirely for guests (never reveal cart contents without
// login) and hidden while the cart is empty — it only appears once
// something is actually in it.
function updateCartBadge() {
    const total = IS_LOGGED_IN ? Object.values(cart).reduce((s,i) => s + i.qty, 0) : 0;
    const badge = document.getElementById('cartBadge');
    if(badge) {
        const prev = badge.textContent;
        badge.textContent = total;
        badge.style.display = total > 0 ? 'flex' : 'none';
        if(String(total) !== prev && total > 0){
            badge.classList.remove('pop'); void badge.offsetWidth; badge.classList.add('pop');
        }
    }
    const wrap = document.getElementById('floatingCartWrap');
    if (wrap) {
        // Mobile floating stack (Filter / Cart / KrishiMitra): the Cart
        // icon should appear as soon as the user is logged in, so Filter
        // has a reason to shift up and give it a slot — independent of
        // whether the cart currently has items. Desktop keeps the
        // original "only show once something is in the cart" behaviour.
        const isMobileStack = window.innerWidth <= 768;
        const showFloatingCart = isMobileStack ? IS_LOGGED_IN : (IS_LOGGED_IN && total > 0);
        wrap.style.display = showFloatingCart ? '' : 'none';
    }
    renderFloatingCartPreview();
    if (window.updateFabStack) window.updateFabStack();
}
function renderFloatingCartPreview(){
    const box = document.getElementById('floatingCartPreview');
    if(!box || !IS_LOGGED_IN) { if(box) box.innerHTML = ''; return; }
    const pt = StoreT[window.lang || 'en'];
    const ids = Object.keys(cart).filter(id => cart[id].qty > 0);
    if(ids.length === 0){
        box.innerHTML = `<div class="fcp-empty">${pt.emptyCart}</div>`;
        return;
    }
    const shown = ids.slice(-3).reverse();
    let html = shown.map(id => {
        const p = PRODUCTS_ALL().find(pr => pr.id == id);
        if(!p) return '';
        const name = escapeHTML(localName(p));
        return `<div class="fcp-row">
            <img ${productImgAttrs(p.image, name)}>
            <span class="fcp-name">${name}</span>
            <span class="fcp-qty-controls" onclick="event.stopPropagation()">
                <button type="button" class="fcp-qty-btn" onclick="changeQty(${p.id}, -1)" aria-label="${pt.decreaseQtyLabel || 'Decrease quantity'}">−</button>
                <span class="fcp-qty" aria-live="polite">${cart[id].qty}</span>
                <button type="button" class="fcp-qty-btn" onclick="changeQty(${p.id}, 1)" aria-label="${pt.increaseQtyLabel || 'Increase quantity'}">+</button>
            </span>
            <button type="button" class="fcp-remove-btn" onclick="event.stopPropagation(); removeItem(${p.id})" aria-label="${pt.removeBtnText || 'Remove'}"><i class="fa-solid fa-trash"></i></button>
        </div>`;
    }).join('');
    if(ids.length > shown.length){
        html += `<div class="fcp-more">+${ids.length - shown.length} more item(s)</div>`;
    }
    const t = cartTotals();
    html += `<div class="fcp-total"><span>${pt.cartTotal}</span><span>₹${t.grand}</span></div>`;
    html += `<button class="fcp-view-btn" onclick="openCart()">${pt.myCartLbl}</button>`;
    box.innerHTML = html;
}
function cartTotals(){
    let totalAmt = 0;
    Object.keys(cart).forEach(id=>{
        if(cart[id].qty>0){ const p = PRODUCTS_ALL().find(pr=>pr.id==id); if(p) totalAmt += p.price * cart[id].qty; }
    });
    let discount = 0;
    if(appliedCouponData) discount = appliedCouponData.discount_amount;
    return { totalAmt, discount, grand: totalAmt - discount };
}
function renderCartBody() {
    const body = document.getElementById('cartBody');
    const foot = document.getElementById('cartFoot');
    let html = '';
    const pt = StoreT[window.lang || 'en'];

    Object.keys(cart).forEach(id => {
        if(cart[id].qty > 0) {
            const p = PRODUCTS_ALL().find(prod => prod.id == id);
            if(!p) return;
            const name = escapeHTML(localName(p));
            html += `<div class="cart-item-row">
                <img ${productImgAttrs(p.image, name)} style="width:46px;height:46px;border-radius:8px;object-fit:cover">
                <div class="cart-item-details">
                    <div class="cart-item-name">${name}</div>
                    <div class="cart-item-price">₹${p.price}</div>
                    <div class="qty-row">
                        <button type="button" class="qty-btn" onclick="changeQty(${p.id}, -1)" aria-label="${pt.decreaseQtyLabel || 'Decrease quantity'}">-</button>
                        <span class="qty-val" aria-live="polite">${cart[p.id].qty}</span>
                        <button type="button" class="qty-btn" onclick="changeQty(${p.id}, 1)" aria-label="${pt.increaseQtyLabel || 'Increase quantity'}">+</button>
                    </div>
                </div>
                <button type="button" class="remove-btn" onclick="removeItem(${p.id})" aria-label="${pt.removeBtnText || 'Remove'}"><i class="fa-solid fa-trash"></i></button>
            </div>`;
        }
    });

    if(html === '') {
        body.innerHTML = `<div class="cart-empty" style="text-align:center; padding:2rem; color:#666;"><i class="fa-solid fa-basket-shopping" style="font-size:44px; color:#ccc; display:block; margin-bottom:10px;"></i>${pt.emptyCart}</div>`;
        if(foot) foot.style.display = 'none';
    } else {
        body.innerHTML = html;
        const t = cartTotals();
        document.getElementById('subTotal').textContent = '₹' + t.totalAmt;
        document.getElementById('grandTotal').textContent = '₹' + t.grand;
        const dr = document.getElementById('discountRow');
        if(t.discount>0){ dr.style.display='flex'; document.getElementById('discountAmt').textContent = '-₹'+t.discount; }
        else dr.style.display='none';
        if(foot) foot.style.display = 'block';
    }
}
function changeQty(id, delta) {
    if (!cart[id]) return;
    const p = PRODUCTS_ALL().find(pr=>pr.id==id);
    const max = p ? p.stock : 999;
    const next = cart[id].qty + delta;
    if (delta > 0 && next > max) {
        showToast((StoreT[window.lang||'en'].maxStockReached || 'Maximum stock reached ({stock} available)').replace('{stock}', max));
        cart[id].qty = max;
    } else {
        cart[id].qty = next;
    }
    if(cart[id].qty <= 0) delete cart[id];
    saveState(); updateCartBadge(); renderProducts(); renderCartBody();
}
function removeItem(id) { delete cart[id]; saveState(); updateCartBadge(); renderProducts(); renderCartBody(); showToast(StoreT[window.lang||'en'].toastRemove); }
function openCart() {
    if (!requireLoginForCart()) return;
    closeWishlist(); closeOrders(); if (typeof toggleMobileFilters === 'function') toggleMobileFilters(false);
    document.getElementById('cartDrawer').classList.add('open'); document.getElementById('cartOverlay').classList.add('open');
    lockBodyScrollForPanel();
}
function closeCart() { document.getElementById('cartDrawer').classList.remove('open'); document.getElementById('cartOverlay').classList.remove('open'); unlockBodyScrollForPanel(); }

let offerSlides = [];
let offerSlideIndex = 0;
let offerSlideTimer = null;

function formatOfferAmount(c){
    return c.discount_type === 'flat' ? ('₹' + c.discount_value) : (c.discount_value + '%');
}

function renderOfferSlide(i){
    const c = offerSlides[i];
    if(!c) return;
    const pt = StoreT[window.lang||'en'];
    const textEl = document.getElementById('offerStripText');
    if(!textEl) return;
    textEl.classList.add('offer-fade');
    setTimeout(() => {
        document.getElementById('gridOfferHeading').textContent = formatOfferAmount(c) + ' ' + pt.offerOffSuffix;
        let sub = pt.offerUseCode.replace('{code}', c.code);
        if (c.min_order_amount > 0) sub += ' ' + pt.offerMinOrder.replace('{min}', c.min_order_amount);
        document.getElementById('gridOfferSub').textContent = sub;
        const codeEl = document.getElementById('gridOfferCode');
        codeEl.textContent = c.code;
        codeEl.onclick = function(){
            const input = document.getElementById('couponInput');
            if(input){ input.value = c.code; openCart(); }
        };
        textEl.classList.remove('offer-fade');
    }, 280);
}

// Coupons are fetched once and cached in memory — both the offer strip and
// the cart coupon banner reuse the same in-flight/cached promise instead of
// each firing their own request to list_coupons.php.
let couponsCache = null;
let couponsCachePromise = null;
async function getCoupons(){
    if (couponsCache) return couponsCache;
    if (couponsCachePromise) return couponsCachePromise;
    couponsCachePromise = (async () => {
        const { ok, data } = await fetchJSON('list_coupons.php');
        const now = Date.now();
        let coupons = (ok && data && data.success && Array.isArray(data.coupons)) ? data.coupons : [];
        // Belt-and-suspenders client-side filter — the backend is the source of
        // truth, but this hides anything that slipped through as inactive,
        // outside its date window, or already at its usage limit.
        coupons = coupons.filter(c => {
            if (c.is_active === 0 || c.is_active === false) return false;
            if (c.start_date && new Date(c.start_date).getTime() > now) return false;
            if (c.expiry_date && new Date(c.expiry_date).getTime() < now) return false;
            if (c.usage_limit != null && c.used_count != null && Number(c.used_count) >= Number(c.usage_limit)) return false;
            return true;
        });
        couponsCache = coupons;
        return coupons;
    })();
    return couponsCachePromise;
}

function startOfferRotation(){
    if(offerSlideTimer) clearInterval(offerSlideTimer);
    if(offerSlides.length <= 1) return;
    offerSlideTimer = setInterval(() => {
        offerSlideIndex = (offerSlideIndex + 1) % offerSlides.length;
        renderOfferSlide(offerSlideIndex);
    }, 4000);
}

async function loadOfferStrip(){
    const strip = document.getElementById('offerStrip');
    if(!strip) return;
    const coupons = await getCoupons();
    if(coupons.length){
        offerSlides = coupons;
        offerSlideIndex = 0;
        strip.style.display = '';
        renderOfferSlide(0);
        startOfferRotation();
    } else {
        strip.style.display = 'none';
    }
}

function refreshOfferStrip(){
    if(offerSlides.length){ renderOfferSlide(offerSlideIndex); }
    else { loadOfferStrip(); }
}

async function loadCouponsBanner(){
    const banner = document.getElementById('couponBanner');
    if(!banner) return;
    const coupons = await getCoupons();
    if(!coupons.length){ banner.innerHTML = ''; return; }
    banner.innerHTML = coupons.map(c => {
        const code = escapeHTML(c.code);
        const off = c.discount_type === 'flat' ? ('₹' + Number(c.discount_value) + ' off') : (Number(c.discount_value) + '% off');
        const min = c.min_order_amount > 0 ? (' on ₹' + Number(c.min_order_amount) + '+') : '';
        return `<div class="coupon-chip" onclick="document.getElementById('couponInput').value='${code}'; applyCoupon();">
            <span class="code">${code}</span><span class="desc">${escapeHTML(off + min)}</span>
        </div>`;
    }).join('');
}

async function applyCoupon(){
    const code = document.getElementById('couponInput').value.trim().toUpperCase();
    const msg = document.getElementById('couponMsg');
    const pt = StoreT[window.lang||'en'];
    if(!code){ return; }

    // The server recalculates the order amount itself from the actual cart
    // item IDs/quantities — the client-computed total below is only used to
    // pre-fill the UI while the request is in flight, never trusted for the
    // real discount math.
    const items = Object.keys(cart).filter(id=>cart[id].qty>0).map(id=>({ id: Number(id), qty: cart[id].qty }));
    msg.textContent = '…'; msg.className = 'coupon-msg';
    const { ok, data } = await fetchJSON('validate_coupon.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN, code, items: JSON.stringify(items) })
    });
    if(ok && data && data.success){
        appliedCoupon = data.code;
        appliedCouponData = data;
        const off = data.discount_type === 'flat' ? ('₹' + data.discount_value) : (data.discount_value + '%');
        msg.textContent = '✅ ' + escapeHTML(data.code) + ' applied — ' + escapeHTML(off) + ' off (−₹' + Number(data.discount_amount) + ').';
        msg.className = 'coupon-msg ok';
    } else {
        appliedCoupon = null;
        appliedCouponData = null;
        msg.textContent = (data && data.error) || pt.couponBad; msg.className = 'coupon-msg bad';
    }
    renderCartBody();
}

/* ---------------- WISHLIST ---------------- */
function toggleWishlist(id){
    const i = wishlist.indexOf(id);
    if(i>=0){
        wishlist.splice(i,1);
        delete wishlistDates[id];
        // Item is gone from the wishlist, so its pending 15-day reminder (if any) no longer applies.
        wishNotifications = wishNotifications.filter(n=>n.id!=id);
        localStorage.setItem(K_WISH_NOTIF, JSON.stringify(wishNotifications));
    } else {
        wishlist.push(id);
        wishlistDates[id] = Date.now();
    }
    saveState(); updateWishBadge(); renderProducts(); renderWishBody(); renderNotifBell();
}
function updateWishBadge(){ const el = document.getElementById('wishCount'); if(el) el.textContent = wishlist.length; }
function renderWishBody(){
    const body = document.getElementById('wishBody');
    const pt = StoreT[window.lang||'en'];
    if(wishlist.length===0){ body.innerHTML = `<div style="text-align:center;padding:2rem;color:#666"><i class="fa-solid fa-heart-crack" style="font-size:40px;color:#ccc;display:block;margin-bottom:10px"></i>${pt.wishEmpty}</div>`; return; }
    body.innerHTML = wishlist.map(id=>{
        const p = PRODUCTS_ALL().find(pr=>pr.id==id); if(!p) return '';
        const wname = escapeHTML(localName(p));
        return `<div class="cart-item-row">
            <img ${productImgAttrs(p.image, wname)} style="width:46px;height:46px;border-radius:8px;object-fit:cover">
            <div class="cart-item-details">
                <div class="cart-item-name">${wname}</div>
                <div class="cart-item-price">₹${p.price}</div>
                <button type="button" class="add-btn" style="padding:6px;font-size:11px;margin-top:6px" onclick="addToCart(${p.id},1)">${pt.addBtnText}</button>
            </div>
            <button type="button" class="remove-btn" onclick="toggleWishlist(${p.id})" aria-label="${pt.removeFromWish || 'Remove from Wishlist'}"><i class="fa-solid fa-trash"></i></button>
        </div>`;
    }).join('');
}
function openWishlist(){ closeCart(); closeOrders(); if (typeof toggleMobileFilters === 'function') toggleMobileFilters(false); renderWishBody(); document.getElementById('wishDrawer').classList.add('open'); document.getElementById('wishOverlay').classList.add('open'); lockBodyScrollForPanel(); }
function closeWishlist(){ document.getElementById('wishDrawer').classList.remove('open'); document.getElementById('wishOverlay').classList.remove('open'); unlockBodyScrollForPanel(); }

/* ---------------- WISHLIST 15-DAY REMINDER NOTIFICATIONS ---------------- */
const WISHLIST_REMINDER_DAYS = 15;
const WISHLIST_REMINDER_MS = WISHLIST_REMINDER_DAYS * 24 * 60 * 60 * 1000;

// Runs once on page load (and can be re-run any time): any wishlist item that
// has been sitting there for 15+ days and hasn't already raised a reminder
// gets one added to the notifications list + a toast so the user notices it now.
function checkWishlistReminders(){
    const now = Date.now();
    let raisedNew = false;
    wishlist.forEach(id=>{
        const addedAt = wishlistDates[id];
        if(!addedAt) return;
        if(now - addedAt < WISHLIST_REMINDER_MS) return;
        const already = wishNotifications.find(n=>n.id==id);
        if(already) return;
        const p = PRODUCTS_ALL().find(pr=>pr.id==id);
        wishNotifications.unshift({
            id,
            name: p ? localName(p) : ('#'+id),
            addedAt,
            notifiedAt: now,
            read: false
        });
        raisedNew = true;
    });
    if(raisedNew){
        localStorage.setItem(K_WISH_NOTIF, JSON.stringify(wishNotifications));
        const pt = StoreT[window.lang || 'en'];
        showToast(pt.wishReminderToast || 'Tumchya wishlist madla ek product 15 divsanpasun tasach ahe!');
    }
    renderNotifBell();
}

function renderNotifBell(){
    const badge = document.getElementById('notifBadge');
    if(!badge) return;
    const unread = wishNotifications.filter(n=>!n.read).length;
    badge.textContent = unread;
    badge.style.display = unread > 0 ? 'inline-block' : 'none';

    const list = document.getElementById('notifList');
    if(!list) return;
    const pt = StoreT[window.lang || 'en'];
    if(wishNotifications.length === 0){
        list.innerHTML = `<div style="color:#999;font-size:12.5px;text-align:center;padding:14px 4px">${pt.notifEmpty || 'No notifications yet.'}</div>`;
        return;
    }
    list.innerHTML = wishNotifications.map(n=>{
        const days = Math.floor((n.notifiedAt - n.addedAt) / 86400000);
        return `<div style="display:flex;gap:8px;align-items:flex-start;padding:8px;border-radius:8px;background:${n.read?'#fff':'#f2f8f2'};border:1px solid #eee">
            <i class="fa-solid fa-heart-circle-exclamation" style="color:var(--primary);margin-top:2px"></i>
            <div style="flex:1">
                <div style="font-size:12.5px;font-weight:600">${escapeHTML(n.name)}</div>
                <div style="font-size:11.5px;color:#666;margin-top:2px">${(pt.wishReminderLine || '{days} din pasun wishlist madhe ahe — ghenyacha vichar kara!').replace('{days}', days)}</div>
                <div style="margin-top:6px;display:flex;gap:6px">
                    <button style="font-size:11px;padding:4px 8px;background:var(--primary);color:#fff;border:none;border-radius:6px;cursor:pointer" onclick="addToCart(${n.id},1); markNotifRead('${n.id}')">${pt.addBtnText || 'Add to Cart'}</button>
                    <button style="font-size:11px;padding:4px 8px;background:#fff;color:#666;border:1px solid #ddd;border-radius:6px;cursor:pointer" onclick="dismissNotif('${n.id}')">${pt.dismissBtn || 'Dismiss'}</button>
                </div>
            </div>
        </div>`;
    }).join('');
}

function markNotifRead(id){
    const n = wishNotifications.find(x=>x.id==id);
    if(n) n.read = true;
    localStorage.setItem(K_WISH_NOTIF, JSON.stringify(wishNotifications));
    renderNotifBell();
}
function dismissNotif(id){
    wishNotifications = wishNotifications.filter(n=>n.id!=id);
    localStorage.setItem(K_WISH_NOTIF, JSON.stringify(wishNotifications));
    renderNotifBell();
}
function toggleNotifBell(e){
    if(e) e.stopPropagation();
    const panel = document.getElementById('notifPanel');
    if(!panel) return;
    const opening = panel.style.display !== 'block';
    panel.style.display = opening ? 'block' : 'none';
    if(opening){
        wishNotifications.forEach(n=>n.read = true);
        localStorage.setItem(K_WISH_NOTIF, JSON.stringify(wishNotifications));
        renderNotifBell();
    }
}
document.addEventListener('click', (e)=>{
    const panel = document.getElementById('notifPanel');
    if(panel && panel.style.display === 'block' && !panel.contains(e.target) && e.target.id !== 'notifBadge'){
        panel.style.display = 'none';
    }
});

/* ---------------- PRODUCT DETAIL MODAL ---------------- */
function openProductModal(id){
    const p = PRODUCTS_ALL().find(pr=>pr.id==id); if(!p) return;
    const st = stockStatus(p);
    const pt = StoreT[window.lang||'en'];
    const inCart = cart[p.id] && cart[p.id].qty > 0;
    const name = escapeHTML(localName(p));
    const seller = escapeHTML(p.seller || '');
    document.getElementById('productModalContent').innerHTML = `
    <div class="pm-layout">
        <div>
            <img ${productImgAttrs(p.image, name)}>
            <div class="qty-selector" style="margin-top:14px">
                <button type="button" onclick="incDecQty(${p.id}, -1); openProductModal(${p.id})" aria-label="${pt.decreaseQtyLabel || 'Decrease quantity'}">-</button>
                <span aria-live="polite">${inCart ? cart[p.id].qty : cardQty(p.id)}</span>
                <button type="button" onclick="incDecQty(${p.id}, 1); openProductModal(${p.id})" aria-label="${pt.increaseQtyLabel || 'Increase quantity'}">+</button>
            </div>
            <button type="button" class="add-btn" ${(st==='out' && !inCart)?'disabled style="opacity:0.5"':''} onclick="toggleCartItem(${p.id}, cardQty(${p.id})); openProductModal(${p.id})" style="margin-top:8px"><i class="fa-solid ${inCart?'fa-trash':'fa-cart-plus'}"></i> ${inCart ? pt.removeBtnText : pt.addBtnText}</button>
            <button type="button" class="add-btn" style="margin-top:8px;background:#fff;color:var(--primary);border:1px solid var(--primary)" onclick="toggleWishlist(${p.id}); openProductModal(${p.id})" aria-pressed="${wishlist.includes(p.id)}"><i class="fa-solid fa-heart"></i> ${wishlist.includes(p.id)?pt.removeFromWish:pt.saveForLater}</button>
        </div>
        <div>
            <h2 id="pmProductName" style="margin-bottom:4px">${name}</h2>
            <div class="rating-row">${renderStars(p.rating)} ${p.rating} · <span id="pmReviewCount">${p.reviews}</span> ${pt.reviewsLbl}</div>
            <div class="pm-price">₹${p.price} ${p.oldPrice && p.oldPrice > p.price ? `<span class="pm-old">₹${p.oldPrice}</span> <span class="discount-pct">${Math.round((1 - p.price / p.oldPrice) * 100)}% OFF</span>` : ''}</div>
            <span class="stock-tag ${st}" style="position:static;display:inline-block;margin:8px 0">${escapeHTML(stockLabel(st))} ${st!=='out'?'('+p.stock+' '+pt.unitsLbl+')':''}</span>
            <p class="pm-desc">${escapeHTML(localDesc(p))}</p>
            <div class="pm-info-row">
                <span><i class="fa-solid fa-store"></i> ${seller} ${p.verified?'<span class="verified-badge"><i class="fa-solid fa-circle-check"></i> '+escapeHTML(pt.verifiedSeller)+'</span>':''}</span>
                <span><i class="fa-solid fa-box"></i> ${escapeHTML(localUnit(p))}</span>
                <span><i class="fa-solid fa-tag"></i> ${escapeHTML(p.cat || '')}</span>
            </div>
            <div class="pm-reviews">
                <h4>${pt.farmerReviews}</h4>
                <div class="rating-breakdown" id="pmRatingBreakdown"></div>
                <div class="reviews-list" id="pmReviewsList"><p style="color:#999;font-size:13px">${pt.loadingText || 'Loading...'}</p></div>
                <hr style="margin:14px 0;border-color:#eee">
                <h4>${pt.writeReviewLbl || 'Write a Review'}</h4>
                <div class="star-picker" id="pmStarPicker" role="radiogroup" aria-label="${pt.selectRating || 'Select a star rating'}">
                    ${[1,2,3,4,5].map(n => `<i class="fa-solid fa-star star-pick" data-v="${n}" role="radio" aria-checked="false" tabindex="0" onclick="pmPickStar(${n})" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();pmPickStar(${n});}" style="font-size:22px;color:#ddd;cursor:pointer;margin:2px"></i>`).join('')}
                </div>
                <label for="pmReviewText" class="sr-only" style="position:absolute;left:-9999px">${pt.writeReviewLbl || 'Write a Review'}</label>
                <textarea id="pmReviewText" rows="3" placeholder="${escapeHTML(pt.reviewPh || 'Your experience...')}" style="width:100%;margin-top:10px;border:1px solid #ddd;border-radius:8px;padding:8px;font-size:13px;box-sizing:border-box;resize:none"></textarea>
                <button type="button" id="pmSubmitReviewBtn" class="add-btn" style="margin-top:8px" onclick="pmSubmitReview(${p.id})">${pt.submitReviewLbl || 'Submit'}</button>
            </div>
        </div>
    </div>`;
    document.getElementById('productModalOverlay').classList.add('open');
    window._pmSelectedStars = 0;
    pmPickStar(0);
    pmLoadReviews(p.id);
}
function closeProductModal(e){ if(e && e.target.id!=='productModalOverlay') return; document.getElementById('productModalOverlay').classList.remove('open'); }

/* ---- Real reviews for the product modal (shared reviews table) ---- */
function pmRenderBreakdown(breakdown, count){
    if (!breakdown || !count) return '';
    return [5,4,3,2,1].map(star => {
        const c = breakdown[star] || 0;
        const pct = count > 0 ? Math.round((c / count) * 100) : 0;
        return `<div class="rb-row"><span class="rb-label">${star} ★</span><div class="rb-track"><div class="rb-fill" style="width:${pct}%"></div></div><span class="rb-count">${c}</span></div>`;
    }).join('');
}
function pmLoadReviews(id){
    fetch('get_reviews.php?item_type=product&item_id=' + id)
    .then(r => r.json())
    .then(data => {
        const bd = document.getElementById('pmRatingBreakdown');
        if (bd) bd.innerHTML = pmRenderBreakdown(data.breakdown, data.count);
        const cnt = document.getElementById('pmReviewCount');
        if (cnt) cnt.textContent = data.count;
        const el = document.getElementById('pmReviewsList');
        if (!el) return;
        if (data.count === 0) {
            el.innerHTML = '<p style="color:#999;font-size:13px">No reviews yet — be the first to review this product!</p>';
        } else {
            const pt = StoreT[window.lang||'en'];
            // Every field here comes from the DB via an API response, so all of
            // it — name and comment — is escaped. A review containing raw HTML
            // or a <script> tag must render as inert plain text, never markup.
            el.innerHTML = data.reviews.map(r => {
                const safeName = escapeHTML(r.name || 'Anonymous');
                const safeComment = escapeHTML(r.comment || '');
                const rating = Math.max(0, Math.min(5, parseInt(r.rating) || 0));
                return `<div class="review-item">
                    <div class="review-head"><span>${safeName}${r.verified ? ' <span class="verified-badge"><i class="fa-solid fa-circle-check"></i> '+escapeHTML(pt.verifiedBuyer||'Verified Buyer')+'</span>' : ''}</span><span class="stars">${'★'.repeat(rating)}${'☆'.repeat(5-rating)}</span></div>
                    ${safeComment}
                </div>`;
            }).join('');
        }
        if (data.my_review) {
            const textEl = document.getElementById('pmReviewText');
            if (textEl) textEl.value = data.my_review.comment || '';
            pmPickStar(data.my_review.rating || 0);
        }
    })
    .catch(() => {
        const el = document.getElementById('pmReviewsList');
        if (el) el.innerHTML = '<p style="color:#d93025;font-size:13px">Could not load reviews.</p>';
    });
}
function pmPickStar(n){
    window._pmSelectedStars = n;
    document.querySelectorAll('#pmStarPicker .star-pick').forEach(s => {
        s.style.color = parseInt(s.getAttribute('data-v')) <= n ? '#FFC107' : '#ddd';
    });
}
async function pmSubmitReview(id){
    const pt = StoreT[window.lang||'en'];
    if (!IS_LOGGED_IN) {
        showToast(pt.loginRequired || 'Please login to write a review.');
        window.location.href = 'login.php';
        return;
    }
    const textEl = document.getElementById('pmReviewText');
    const text = textEl ? textEl.value.trim() : '';
    if (!text) return;
    if (!window._pmSelectedStars) {
        showToast(pt.selectRating || 'Please select a star rating first.');
        return;
    }
    const btn = document.getElementById('pmSubmitReviewBtn');
    if (btn) { btn.disabled = true; btn.dataset.origText = btn.textContent; btn.textContent = pt.submittingReview || 'Submitting…'; }
    const { ok, data } = await fetchJSON('submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN, item_type: 'product', item_id: id, rating: window._pmSelectedStars, comment: text })
    });
    if (btn) { btn.disabled = false; btn.textContent = btn.dataset.origText || (pt.submitReviewBtn || 'Submit Review'); }
    if (ok && data && data.success) {
        showToast(data.updated ? (pt.reviewUpdated || 'Review updated.') : (pt.reviewSubmitted || 'Review submitted.'));
        pmLoadReviews(id);
    } else {
        showToast((data && data.error) || pt.networkError || 'Could not save review.');
    }
}

/* ---------------- COMPARE ---------------- */
function toggleCompare(id){
    const i = compareList.indexOf(id);
    if(i>=0) compareList.splice(i,1);
    else { if(compareList.length>=2){ showToast(StoreT[window.lang||'en'].compareLimitMsg); renderProducts(); return; } compareList.push(id); }
    document.getElementById('compareCount').textContent = compareList.length;
    document.getElementById('compareBtn').disabled = compareList.length !== 2;
    renderProducts();
}
function openCompare(){
    if(compareList.length!==2) return;
    const pt = StoreT[window.lang||'en'];
    const [a,b] = compareList.map(id=>PRODUCTS_ALL().find(p=>p.id==id));
    const nameA = escapeHTML(localName(a)), nameB = escapeHTML(localName(b));
    const rows = [
        [pt.cmpImage, `<img ${productImgAttrs(a.image, nameA)} style="width:60px;height:60px;object-fit:cover;border-radius:8px">`, `<img ${productImgAttrs(b.image, nameB)} style="width:60px;height:60px;object-fit:cover;border-radius:8px">`],
        [pt.cmpName, nameA, nameB],
        [pt.cmpPrice, '₹'+a.price, '₹'+b.price],
        [pt.cmpUnit, escapeHTML(localUnit(a)), escapeHTML(localUnit(b))],
        [pt.cmpRating, renderStars(a.rating)+' '+a.rating+' ('+a.reviews+')', renderStars(b.rating)+' '+b.rating+' ('+b.reviews+')'],
        [pt.cmpStock, escapeHTML(stockLabel(stockStatus(a))), escapeHTML(stockLabel(stockStatus(b)))],
        [pt.cmpSeller, escapeHTML(a.seller || '') + (a.verified?' ✅':''), escapeHTML(b.seller || '') + (b.verified?' ✅':'')],
        [pt.cmpCategory, escapeHTML(a.cat || ''), escapeHTML(b.cat || '')],
    ];
    document.getElementById('compareContent').innerHTML = `<table>${rows.map(r=>`<tr><th>${escapeHTML(r[0]||'')}</th><td>${r[1]}</td><td>${r[2]}</td></tr>`).join('')}</table>`;
    document.getElementById('compareModalOverlay').classList.add('open');
}
function closeCompare(e){ if(e && e.target.id!=='compareModalOverlay') return; document.getElementById('compareModalOverlay').classList.remove('open'); }

/* ---------------- AI SUGGESTION ---------------- */
function suggestForCrop(){
    const crop = document.getElementById('aiCropSelect').value;
    const box = document.getElementById('aiSuggestResults');
    const pt = StoreT[window.lang||'en'];
    if(!crop){ box.innerHTML=''; return; }

    // Products are already filtered server-side to active + approved, and
    // out-of-stock items are excluded here so we never recommend something
    // the person can't actually buy.
    const eligible = PRODUCTS_ALL().filter(p => p.stock > 0);

    // Prefer real crop_tags / category / name / description matching over
    // the old hardcoded ID lists. cropTags is a comma-separated string like
    // "tomato,vegetables".
    let items = eligible.filter(p => {
        const tags = (p.cropTags || '').split(',').map(t=>t.trim()).filter(Boolean);
        if (tags.includes(crop)) return true;
        const haystack = ((p.category||'') + ' ' + (p.name||'') + ' ' + (p.description||'')).toLowerCase();
        return haystack.includes(crop.toLowerCase());
    });

    // Backward-compatible fallback: only used if nothing matched via tags —
    // keeps the feature working on a DB that hasn't run the crop_tags
    // migration yet.
    if (items.length === 0) {
        const ids = CROP_SUGGESTIONS[crop] || [];
        items = ids.map(id=>eligible.find(p=>p.id==id)).filter(Boolean);
    }
    items = items.slice(0, 4);

    box.innerHTML = items.map(p=>{
        const name = escapeHTML(localName(p));
        return `<div class="ai-suggest-card"><img ${productImgAttrs(p.image, name)}><div><strong>${name}</strong><br>₹${p.price} · <button type="button" onclick="addToCart(${p.id},1)" style="color:var(--primary);background:none;border:none;padding:0;cursor:pointer;text-decoration:underline;font-size:inherit">${pt.aiAddToCart}</button></div></div>`;
    }).join('') || `<div style="color:#fff;font-size:12.5px">${pt.aiNoSuggestions}</div>`;
}

/* ---------------- PINCODE CHECK ---------------- */
function checkPincode(){
    const raw = document.getElementById('pinInput').value.trim();
    const res = document.getElementById('pinResult');
    const pt = StoreT[window.lang||'en'];
    if(!raw){ res.textContent=''; res.className = 'pin-result'; return; }

    const normalized = raw.toLowerCase();
    const isSixDigitPin = /^\d{6}$/.test(raw);
    const isKnownDistrictName = SERVICEABLE_DISTRICTS.some(d => /^[a-z]/.test(d) && normalized === d);
    const matchesServiceablePrefix = SERVICEABLE_DISTRICTS.some(d => /^\d/.test(d) && raw.startsWith(d));

    if (!isSixDigitPin && !isKnownDistrictName) {
        res.textContent = pt.pinInvalid || 'Enter a valid 6-digit PIN code or district.';
        res.className = 'pin-result bad';
        return;
    }
    const deliverable = isKnownDistrictName || matchesServiceablePrefix;
    if (deliverable) {
        res.textContent = pt.pinAvailable || pt.pinOk || 'Delivery is available in your area.';
        res.className = 'pin-result ok';
    } else {
        res.textContent = pt.pinUnavailable || 'Delivery is currently unavailable in this area.';
        res.className = 'pin-result bad';
    }
}

/* ---------------- CHECKOUT ---------------- */
function goToCheckout(){
    // If the checkout modal is already open, a stray second click on the
    // "Checkout" button (it can remain clickable behind the modal) must
    // NOT re-run this function — otherwise loadAddressBook() below fires
    // again mid-edit and silently hides whatever address form the person
    // is currently filling in, making it look like the form "closed on
    // its own" for no reason.
    const alreadyOpen = document.getElementById('checkoutModalOverlay').classList.contains('open');
    if (alreadyOpen) return;

    let t;
    try { t = cartTotals(); } catch(err) { console.error('cartTotals error:', err); t = {totalAmt:0, discount:0, grand:0}; }
    const pt = StoreT[window.lang||'en'];
    if(t.totalAmt===0){ showToast(pt.emptyCart); return; }
    pendingOrderTotal = t.grand; pendingOrderDiscount = t.discount;

    // Open the modal first — guaranteed to show even if content-population below hits an issue
    closeCart();
    document.getElementById('checkoutModalOverlay').classList.add('open');

    try {
        document.getElementById('checkoutItems').innerHTML = Object.keys(cart).filter(id=>cart[id].qty>0).map(id=>{
            const p = PRODUCTS_ALL().find(pr=>pr.id==id);
            if(!p) return '';
            return `<div class="ck-item-row"><span>${escapeHTML(localName(p))} × ${cart[id].qty}</span><span>₹${p.price*cart[id].qty}</span></div>`;
        }).join('');
        document.getElementById('ckSubtotal').textContent = '₹'+t.totalAmt;
        const dr = document.getElementById('ckDiscountRow');
        if(t.discount>0){ dr.style.display='flex'; document.getElementById('ckDiscount').textContent='-₹'+t.discount; } else dr.style.display='none';
        document.getElementById('ckTotal').textContent = '₹'+t.grand;
        const sel = document.getElementById('payMethodSelect');
        if(sel) sel.value = 'COD';
        onPayMethodChange();
    } catch(err) {
        console.error('goToCheckout populate error:', err);
    }

    loadAddressBook();
}

/* ---------------- ADDRESS BOOK (multiple delivery addresses) ----------------
   Each saved address is a card in #ckAddressList. Picking one just copies its
   fields into #ckName/#ckMobile/#ckPin/#ckAddress — confirmOrder() reads those
   same four fields exactly as before, so place_order.php needs no changes. */
let addressBook = [];
let selectedAddressId = null;
let editingAddressId = null;

async function loadAddressBook(){
    const listEl = document.getElementById('ckAddressList');
    if (!IS_LOGGED_IN) { listEl.innerHTML = ''; showAddAddressForm(); return; }
    listEl.innerHTML = '<p style="font-size:12px;color:#888;padding:6px 0">Loading saved addresses…</p>';
    try {
        const res = await fetch('get_addresses.php');
        const data = await res.json();
        addressBook = (data && data.success && Array.isArray(data.addresses)) ? data.addresses : [];
    } catch (err) {
        console.error('loadAddressBook error:', err);
        addressBook = [];
    }

    // Legacy fallback: DB has no user_addresses rows yet but the older
    // single saved_* address exists (e.g. from before this feature, or an
    // account whose migration hasn't run) — show it as one selectable card
    // instead of leaving the list empty.
    if (addressBook.length === 0 && typeof SAVED_ADDRESS !== 'undefined' && SAVED_ADDRESS) {
        addressBook = [{
            id: 'legacy', label: 'Saved', name: SAVED_ADDRESS.name, mobile: SAVED_ADDRESS.mobile,
            pincode: SAVED_ADDRESS.pin, address: SAVED_ADDRESS.address, is_default: true
        }];
    }

    if (addressBook.length === 0) {
        renderAddressList();
        showAddAddressForm();
        return;
    }

    const def = addressBook.find(a => a.is_default) || addressBook[0];
    selectAddress(def.id, /*fromLoad=*/true);
}

function renderAddressList(){
    const listEl = document.getElementById('ckAddressList');
    if (!addressBook.length) { listEl.innerHTML = ''; return; }
    listEl.innerHTML = addressBook.map(a => {
        const checked = (a.id === selectedAddressId) ? 'checked' : '';
        const selectedCls = (a.id === selectedAddressId) ? 'selected' : '';
        const editBtn = (a.id !== 'legacy')
            ? `<button type="button" title="Edit" onclick="event.stopPropagation(); editAddress('${a.id}')"><i class="fa-solid fa-pen"></i></button>`
            : '';
        const delBtn = (a.id !== 'legacy')
            ? `<button type="button" class="addr-delete-btn" title="Delete" onclick="event.stopPropagation(); deleteAddress('${a.id}')"><i class="fa-solid fa-trash-can"></i></button>`
            : '';
        return `<div class="addr-card ${selectedCls}" onclick="selectAddress('${a.id}')">
            <input type="radio" name="ckAddrRadio" ${checked} onclick="selectAddress('${a.id}')">
            <div class="addr-card-body">
                <span class="addr-card-label">${escapeHTML(a.label || 'Address')}</span><br>
                <strong>${escapeHTML(a.name)}</strong> · ${escapeHTML(a.mobile)}<br>
                ${escapeHTML(a.address)} — ${escapeHTML(a.pincode)}
            </div>
            <div class="addr-card-actions">${editBtn}${delBtn}</div>
        </div>`;
    }).join('');
}

function selectAddress(id, fromLoad){
    selectedAddressId = id;
    const a = addressBook.find(x => x.id === id);
    if (a) {
        document.getElementById('ckName').value = a.name;
        document.getElementById('ckMobile').value = a.mobile;
        document.getElementById('ckPin').value = a.pincode;
        document.getElementById('ckAddress').value = a.address;
    }
    document.getElementById('ckAddrFields').style.display = 'none';
    document.getElementById('ckAddNewAddrBtn').style.display = 'inline-flex';
    renderAddressList();
}

function showAddAddressForm(editId){
    editingAddressId = editId || null;
    const a = editId ? addressBook.find(x => x.id === editId) : null;
    document.getElementById('ckAddrLabel').value = a ? (a.label || '') : '';
    document.getElementById('ckName').value = a ? a.name : '';
    document.getElementById('ckMobile').value = a ? a.mobile : '';
    document.getElementById('ckPin').value = a ? a.pincode : '';
    document.getElementById('ckAddress').value = a ? a.address : '';
    document.getElementById('ckSetDefault').checked = a ? !!a.is_default : (addressBook.length === 0);
    document.getElementById('ckAddrFields').style.display = 'block';
    document.getElementById('ckAddNewAddrBtn').style.display = 'none';
    document.getElementById('ckCancelAddrBtn').style.display = addressBook.length ? 'inline-flex' : 'none';
}

function editAddress(id){ showAddAddressForm(id); }

function cancelAddAddress(){
    editingAddressId = null;
    document.getElementById('ckAddrFields').style.display = 'none';
    document.getElementById('ckAddNewAddrBtn').style.display = 'inline-flex';
    if (selectedAddressId != null) { selectAddress(selectedAddressId); } else { renderAddressList(); }
}

async function saveAddressToBook(){
    const pt = StoreT[window.lang||'en'];
    const label = document.getElementById('ckAddrLabel').value.trim() || 'Home';
    const name = document.getElementById('ckName').value.trim();
    const mobile = document.getElementById('ckMobile').value.trim();
    const pin = document.getElementById('ckPin').value.trim();
    const addr = document.getElementById('ckAddress').value.trim();
    const setDefault = document.getElementById('ckSetDefault').checked;

    if (name.length < 2) { showToast(pt.ckFillFields); return; }
    if (!/^[6-9]\d{9}$/.test(mobile)) { showToast(pt.ckInvalidMobile); return; }
    if (!/^\d{6}$/.test(pin)) { showToast(pt.pinInvalid || pt.ckFillFields); return; }
    if (addr.length < 10) { showToast(pt.ckFillFields); return; }
    if (!IS_LOGGED_IN) { showToast(pt.loginRequired || 'कृपया आधी login करा.'); return; }

    const { ok, data } = await fetchJSON('save_address.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            csrf_token: CSRF_TOKEN,
            address_id: (editingAddressId && editingAddressId !== 'legacy') ? editingAddressId : '',
            label, name, mobile, pincode: pin, address: addr,
            is_default: setDefault ? '1' : ''
        })
    });

    if (ok && data && data.success) {
        editingAddressId = null;
        document.getElementById('ckAddrFields').style.display = 'none';
        document.getElementById('ckAddNewAddrBtn').style.display = 'inline-flex';
        await loadAddressBook();
        if (data.id) selectAddress(data.id);
        showToast(pt.addressSaved || 'Address saved.');
    } else {
        showToast((data && data.error) || pt.networkError || 'Could not save address, please try again.');
    }
}

async function deleteAddress(id){
    const pt = StoreT[window.lang||'en'];
    if (id === 'legacy') return; // legacy single-address fallback isn't a real row to delete
    if (!confirm(pt.confirmDeleteAddr || 'Delete this saved address?')) return;
    const { ok, data } = await fetchJSON('delete_address.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ csrf_token: CSRF_TOKEN, address_id: id })
    });
    if (ok && data && data.success) {
        await loadAddressBook();
    } else {
        showToast((data && data.error) || pt.networkError || 'Could not delete address, please try again.');
    }
}

function closeCheckout(e){ if(e && e.target.id!=='checkoutModalOverlay') return; document.getElementById('checkoutModalOverlay').classList.remove('open'); }

/* ---------------- PAYMENT METHOD UI ---------------- */
function onPayMethodChange(){
    const sel = document.getElementById('payMethodSelect');
    const method = sel ? sel.value : 'COD';
    const boxes = { UPI:'upiIdBox', UPIQR:'upiQrBox' };
    Object.values(boxes).forEach(id => { const el = document.getElementById(id); if(el) el.style.display = 'none'; });
    if (boxes[method]) {
        const el = document.getElementById(boxes[method]);
        if (el) el.style.display = method === 'UPIQR' ? 'flex' : 'block';
    }
    if (method === 'UPIQR') {
        const pt = StoreT[window.lang||'en'];
        const amt = pendingOrderTotal || 0;
        // Demo QR only — no real payment gateway is wired up in this project.
        // Production should generate this server-side from the payment
        // gateway (e.g. Razorpay order + hosted QR) and verify the payment
        // signature before ever marking an order as paid.
        const upiLink = `upi://pay?pa=agricart@upi&pn=AgriCart&am=${amt}&cu=INR&tn=DEMO`;
        const qrImg = document.getElementById('upiQrImg');
        qrImg.src = 'https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=' + encodeURIComponent(upiLink);
        qrImg.alt = (pt.demoPayment || 'Demo Payment') + ' — UPI QR';
        document.getElementById('upiQrFallback').style.display = 'none';
        document.getElementById('upiQrImg').style.display = '';
        document.getElementById('upiQrNote').textContent = (pt.demoPayment || 'Demo Payment') + ' — ' + pt.upiQrNote.replace('{amt}', '₹' + amt);
    }
}

async function confirmOrder(){
    const pt = StoreT[window.lang || 'en'];
    if (!IS_LOGGED_IN) {
        showToast(pt.loginRequired || 'कृपया आधी login करा.');
        window.location.href = 'login.php';
        return;
    }
    const name = document.getElementById('ckName').value.trim();
    const mobile = document.getElementById('ckMobile').value.trim();
    const pin = document.getElementById('ckPin').value.trim();
    const addr = document.getElementById('ckAddress').value.trim();
    const pay = (document.getElementById('payMethodSelect') || {}).value || 'COD';

    // Client-side checks are just fast UX feedback — place_order.php repeats
    // every one of these on the server and is the actual source of truth.
    if (name.length < 2) { showToast(pt.ckFillFields); return; }
    if (!/^[6-9]\d{9}$/.test(mobile)) { showToast(pt.ckInvalidMobile); return; }
    if (!/^\d{6}$/.test(pin)) { showToast(pt.pinInvalid || pt.ckFillFields); return; }
    if (addr.length < 10) { showToast(pt.ckFillFields); return; }
    if (!['COD','UPI','UPIQR'].includes(pay)) { showToast(pt.ckFillFields); return; }
    if (pay === 'UPI' && !document.getElementById('ckUpiId').value.trim()) { showToast(pt.ckEnterUpi); return; }

    const items = Object.keys(cart).filter(id=>cart[id].qty>0).map(id=>{
        const p = PRODUCTS_ALL().find(pr=>pr.id==id);
        return p ? { id:p.id, qty:cart[id].qty } : null;
    }).filter(Boolean);
    if (items.length === 0) { showToast(pt.emptyCart); return; }

    const btn = document.querySelector('#checkoutModalOverlay .checkout-btn');
    const btnLabel = document.getElementById('ckConfirmBtn');
    if (btn) {
        if (btn.disabled) return; // already submitting — ignore repeat clicks
        btn.disabled = true;
        if (btnLabel) { btnLabel.dataset.origText = btnLabel.textContent; btnLabel.textContent = pt.processingOrder || 'Processing…'; }
    }

    // A fresh random token per submit attempt lets the server reject a
    // second identical request (double click / network retry / back button)
    // as a duplicate instead of creating two orders.
    const idempotencyKey = (window.crypto && crypto.randomUUID) ? crypto.randomUUID()
        : 'idem-' + Date.now() + '-' + Math.random().toString(36).slice(2);

    const { ok, data, error } = await fetchJSON('place_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            csrf_token: CSRF_TOKEN,
            idempotency_key: idempotencyKey,
            items: JSON.stringify(items),
            name, mobile, pin, address: addr, payment_mode: pay,
            // Which saved address book entry this is, so the server reuses
            // it instead of creating a duplicate row on every order.
            address_id: (selectedAddressId && selectedAddressId !== 'legacy') ? selectedAddressId : '',
            coupon_code: appliedCoupon || ''
        })
    });

    if (ok && data && data.success) {
        // Cart is only cleared once the server has confirmed the order —
        // never optimistically, so a failed/duplicate submit can't silently
        // lose items from the user's cart.
        cart = {}; appliedCoupon = null; appliedCouponData = null;
        saveState(); updateCartBadge(); renderProducts(); renderCartBody();
        closeCheckout();
        showToast(pt.orderSuccess + ' (' + escapeHTML(data.order_number || '') + ')');
        openOrders();
    } else {
        if (btn) { btn.disabled = false; }
        if (btnLabel) { btnLabel.textContent = (btnLabel.dataset.origText) || pt.ckConfirmBtn || 'Confirm Order'; }
        if (error === 'timeout') showToast(pt.networkError || 'Request timed out, please try again.');
        else if (error === 'network' || error === 'invalid_json') showToast(pt.networkError || 'Network error, please try again.');
        else showToast((data && data.error) || 'Order failed, please try again.');
        return;
    }
    if (btn) { btn.disabled = false; }
    if (btnLabel) { btnLabel.textContent = (btnLabel.dataset.origText) || pt.ckConfirmBtn || 'Confirm Order'; }
}

/* ---------------- ORDER TRACKING / HISTORY (real DB data) ---------------- */
const TRACK_STEPS = ['placed','packed','shipped','delivered'];
// The exact full `status` ENUM on the `orders` table wasn't fully visible
// during setup (confirmed values seen so far: 'pending','confirmed',
// 'processing','shipped', + more cut off in the DB screenshot) — so rather
// than assume every possible value maps onto our 4-step UI, we only draw
// the step tracker for statuses we can confidently place, and show anything
// else as a plain, honest badge with its raw label. Update STATUS_STEP_MAP
// once the complete enum list is confirmed.
const STATUS_STEP_MAP = {
    pending: 0, placed: 0,
    confirmed: 1, processing: 1, packed: 1,
    shipped: 2, dispatched: 2, out_for_delivery: 2,
    delivered: 3, completed: 3,
};
const TERMINAL_STATUSES = ['cancelled','returned','failed','rejected'];
function trackStepLabel(s, pt){
    if (s === 'placed') return pt.trackPlaced || 'Order Placed';
    if (s === 'packed') return pt.trackPacked || 'Packed';
    if (s === 'shipped') return pt.trackShipped || 'Shipped';
    return pt.trackDelivered || 'Delivered';
}
function terminalStatusBadge(status, pt){
    const map = {
        cancelled: { label: pt.cancelledOrder || 'Cancelled', color: 'var(--danger,#d93025)' },
        returned:  { label: pt.returnedOrder  || 'Returned',  color: '#b8860b' },
        failed:    { label: pt.failedOrder    || 'Failed',    color: 'var(--danger,#d93025)' },
        rejected:  { label: pt.cancelledOrder || 'Rejected',  color: 'var(--danger,#d93025)' },
    };
    const m = map[status] || { label: escapeHTML(status || ''), color: '#888' };
    return `<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;color:#fff;background:${m.color}">${m.label}</span>`;
}
// Neutral badge for a status we genuinely don't have a mapping for yet —
// shows the raw DB value (Title Cased) rather than guessing a wrong stage.
function unknownStatusBadge(status){
    const label = String(status || '').replace(/_/g, ' ').replace(/\b\w/g, c => c.toUpperCase());
    return `<span style="display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:700;color:#555;background:#eee">${escapeHTML(label)}</span>`;
}
function orderTrackingBlock(status, pt){
    const s = String(status || '').trim().toLowerCase();
    if (TERMINAL_STATUSES.includes(s)) {
        return `<div style="margin-top:10px">${terminalStatusBadge(s, pt)}</div>`;
    }
    if (!(s in STATUS_STEP_MAP)) {
        return `<div style="margin-top:10px">${unknownStatusBadge(s)}</div>`;
    }
    const stepIdx = STATUS_STEP_MAP[s];
    // Each step colors its own incoming connector line (see .track-step::before
    // in the CSS) once it's reached, so the "done" portion of the line always
    // lines up with the dots themselves — including under horizontal scroll —
    // instead of one shared line-fill sized by percentage of the row.
    return `<div class="track-line">
                ${TRACK_STEPS.map((st,i)=>`<div class="track-step ${i<=stepIdx?'done':''} ${i===stepIdx?'current':''}"><div class="dot"><i class="fa-solid fa-${['box','truck','motorcycle','circle-check'][i]}"></i></div>${trackStepLabel(st,pt)}</div>`).join('')}
            </div>`;
}
async function openOrders(){
    const box = document.getElementById('ordersContent');
    const pt = StoreT[window.lang||'en'];
    closeCart(); closeWishlist(); if (typeof toggleMobileFilters === 'function') toggleMobileFilters(false);
    document.getElementById('ordersModalOverlay').classList.add('open');
    lockBodyScrollForPanel();

    if (!IS_LOGGED_IN) {
        box.innerHTML = `<p style="color:#666;text-align:center;padding:2rem">${pt.loginRequired || 'Orders बघण्यासाठी login करा.'}</p>`;
        return;
    }
    box.innerHTML = `<p style="color:#999;text-align:center;padding:2rem">${pt.loadingText || 'Loading...'}</p>`;

    const { ok, data } = await fetchJSON('get_my_orders.php');
    if (!ok || !data) { box.innerHTML = `<p style="color:var(--danger);text-align:center;padding:2rem">${pt.networkError || 'Network error, please try again.'}</p>`; return; }

    const list = data.orders || [];
    if (list.length === 0) { box.innerHTML = `<p style="color:#666;text-align:center;padding:2rem">${pt.noOrders}</p>`; return; }
    box.innerHTML = list.map(o=>{
        const discountAmt = Number(o.discount_amount || 0);
        const finalAmt = o.final_amount != null ? Number(o.final_amount) : Number(o.total_amount);
        const orderNumber = escapeHTML(o.order_number || '');
        const paymentMode = escapeHTML((o.payment_mode || '').toUpperCase());
        const couponCode = escapeHTML(o.coupon_code || '');
        const itemsLine = (o.items || []).map(i => escapeHTML(i.product_name) + ' × ' + (parseInt(i.quantity) || 0)).join(', ');
        const couponLine = (couponCode && discountAmt > 0)
            ? `<div style="font-size:12px;color:var(--primary);margin-top:2px">🏷️ ${couponCode} — ${pt.orderDiscountLbl}: −₹${discountAmt}</div>`
            : '';
        // Backend payment_status is 'unpaid' / 'paid' / 'refunded' — treat
        // anything other than 'paid' as "not yet paid" for display purposes.
        const paymentStatusLine = (o.payment_status && o.payment_status !== 'paid')
            ? `<div style="font-size:11.5px;color:#888;margin-top:2px">${pt.paymentPending || 'Payment: Pending'}</div>` : '';
        // Status accent colour for the card's left border — quick visual
        // scan of what's active vs. done vs. cancelled.
        const accentColor = o.order_status === 'delivered' ? '#2e7d32'
            : o.order_status === 'cancelled' ? '#d93025'
            : 'var(--primary,#2e7d32)';
        const canDelete = ['delivered', 'cancelled'].includes(o.order_status);
        const deleteBtn = canDelete
            ? `<button type="button" class="order-delete-btn" title="${pt.removeOrderLbl || 'Remove from history'}" onclick="deleteOrderCard(event, ${o.id})"><i class="fa-solid fa-trash-can"></i></button>`
            : '';
        const invoiceBtn = `<a href="invoice.php?order_id=${o.id}" target="_blank" rel="noopener" class="order-invoice-btn" title="${pt.viewInvoiceLbl || 'View Invoice'}" style="display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:14px;background:#E8F5E9;color:#2E7D32;font-size:11.5px;font-weight:700;text-decoration:none"><i class="fa-solid fa-file-invoice"></i> ${pt.viewInvoiceLbl || 'Invoice'}</a>`;
        return `<div class="order-card" id="order-card-${o.id}" style="border-left:3px solid ${accentColor}">
            <div class="order-head"><span>${orderNumber}</span><span style="display:flex;align-items:center;gap:10px">${escapeHTML(o.created_at || '')}${invoiceBtn}${deleteBtn}</span></div>
            <div style="font-size:12.5px;color:#666">${itemsLine}</div>
            ${couponLine}
            <div style="font-size:13px;font-weight:600;margin-top:6px">${pt.orderTotalLbl}: ₹${finalAmt} · ${paymentMode}</div>
            ${paymentStatusLine}
            ${orderTrackingBlock(o.order_status, pt)}
        </div>`;
    }).join('');
}
function deleteOrderCard(e, orderId){
    e.stopPropagation();
    const pt = StoreT[window.lang||'en'];
    if (!confirm(pt.confirmRemoveOrder || 'Remove this order from your history? This only removes it from your view.')) return;
    const card = document.getElementById('order-card-' + orderId);
    if (card) { card.style.opacity = '0.5'; card.style.pointerEvents = 'none'; }
    fetch('delete_order.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'order_id=' + encodeURIComponent(orderId) + '&csrf_token=' + encodeURIComponent(CSRF_TOKEN)
    })
    .then(r => r.json())
    .then(data => {
        if (data.success && card) {
            card.style.transition = 'opacity .25s ease, transform .25s ease, max-height .3s ease, margin .3s ease, padding .3s ease';
            card.style.maxHeight = card.offsetHeight + 'px';
            requestAnimationFrame(() => {
                card.style.opacity = '0';
                card.style.transform = 'translateX(12px)';
                card.style.maxHeight = '0px';
                card.style.marginBottom = '0px';
                card.style.paddingTop = '0px';
                card.style.paddingBottom = '0px';
                card.style.overflow = 'hidden';
            });
            setTimeout(() => {
                card.remove();
                const box = document.getElementById('ordersContent');
                if (box && !box.querySelector('.order-card')) {
                    box.innerHTML = `<p style="color:#666;text-align:center;padding:2rem">${pt.noOrders}</p>`;
                }
            }, 300);
        } else {
            if (card) { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; }
            showToast(data.error || (pt.networkError || 'Something went wrong, please try again.'));
        }
    })
    .catch(() => {
        if (card) { card.style.opacity = '1'; card.style.pointerEvents = 'auto'; }
        showToast(pt.networkError || 'Network error, please try again.');
    });
}
function closeOrders(e){ if(e && e.target.id!=='ordersModalOverlay') return; document.getElementById('ordersModalOverlay').classList.remove('open'); unlockBodyScrollForPanel(); }

/* ---------------- TOAST ---------------- */
function showToast(msg) {
    const t = document.getElementById('toast');
    const m = document.getElementById('toastMsg');
    if(t && m) { m.textContent = msg; t.setAttribute('aria-live', 'polite'); t.classList.add('show'); setTimeout(() => t.classList.remove('show'), 2200); }
}

/* ---------------- CART / WISHLIST / ORDERS: shared drawer helpers ----------------
   Keeps at most one of Cart, Wishlist, Orders open at a time (opening one
   closes the others), and locks page scroll behind whichever is open —
   without touching the nav drawer's own lock/unlock in header.php, so the
   scroll lock here only lifts once *this group* is fully closed. */
function lockBodyScrollForPanel(){ document.body.classList.add('no-scroll'); }
function unlockBodyScrollForPanel(){
    const stillOpen = ['cartDrawer','wishDrawer','ordersModalOverlay','mobileFilterDrawer'].some(id => {
        const el = document.getElementById(id);
        return el && el.classList.contains('open');
    });
    if (!stillOpen) document.body.classList.remove('no-scroll');
}

/* ---------------- MOBILE FILTER DRAWER ---------------- */
function toggleMobileFilters(forceState){
    const drawer = document.getElementById('mobileFilterDrawer');
    const backdrop = document.getElementById('filterDrawerBackdrop');
    const btn = document.getElementById('mobileFilterToggleBtn');
    if (!drawer) return;
    const opening = (typeof forceState === 'boolean') ? forceState : !drawer.classList.contains('open');
    // Keep only ever one mobile panel open at a time: opening Filter closes
    // Cart/Wishlist/Orders (and, best-effort, the KrishiMitra chat — see
    // the widget-detection hook near the floating cart script below).
    if (opening) {
        if (typeof closeCart === 'function') closeCart();
        if (typeof closeWishlist === 'function') closeWishlist();
        if (typeof closeOrders === 'function') closeOrders();
        if (typeof window.closeKrishiMitraForFilter === 'function') window.closeKrishiMitraForFilter();
    }
    drawer.classList.toggle('open', opening);
    if (backdrop) backdrop.classList.toggle('open', opening);
    if (btn) btn.setAttribute('aria-expanded', String(opening));
    if (opening) lockBodyScrollForPanel(); else unlockBodyScrollForPanel();
}
/* Defensive reset: if the drawer was left open (e.g. tested via the
   "Filters" button) and the browser restores this page from its
   back/forward cache — which replays the DOM exactly as it was left,
   without re-running the normal page-load flow — the drawer would
   otherwise still show 'open' even though nothing on the page was
   actually clicked this time round. pageshow (unlike DOMContentLoaded)
   fires on that bfcache restore too, so this guarantees a closed drawer
   on every page view. */
window.addEventListener('pageshow', () => { toggleMobileFilters(false); closeCart(); closeWishlist(); closeOrders(); });

/* ---------------- KEYBOARD / ACCESSIBILITY ----------------
   Escape closes whatever modal/drawer/panel is currently open, so keyboard
   users are never trapped inside a dialog. */
function closeAnyOpenOverlay(){
    let closedSomething = false;
    document.querySelectorAll('.modal-overlay.open').forEach(el => { el.classList.remove('open'); closedSomething = true; });
    document.querySelectorAll('.cart-drawer.open').forEach(el => { el.classList.remove('open'); closedSomething = true; });
    const cartOverlayEl = document.getElementById('cartOverlay');
    if (cartOverlayEl && cartOverlayEl.classList.contains('open')) { cartOverlayEl.classList.remove('open'); closedSomething = true; }
    const wishOverlayEl = document.getElementById('wishOverlay');
    if (wishOverlayEl && wishOverlayEl.classList.contains('open')) { wishOverlayEl.classList.remove('open'); closedSomething = true; }
    const notifPanelEl = document.getElementById('notifPanel');
    if (notifPanelEl && notifPanelEl.style.display === 'block') { notifPanelEl.style.display = 'none'; closedSomething = true; }
    const filterDrawerEl = document.getElementById('mobileFilterDrawer');
    if (filterDrawerEl && filterDrawerEl.classList.contains('open')) { filterDrawerEl.classList.remove('open'); closedSomething = true; }
    const filterBackdropEl = document.getElementById('filterDrawerBackdrop');
    if (filterBackdropEl && filterBackdropEl.classList.contains('open')) { filterBackdropEl.classList.remove('open'); closedSomething = true; }
    const filterToggleBtnEl = document.getElementById('mobileFilterToggleBtn');
    if (filterToggleBtnEl) filterToggleBtnEl.setAttribute('aria-expanded', 'false');
    if (closedSomething) unlockBodyScrollForPanel();
    return closedSomething;
}
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' || e.key === 'Esc') { closeAnyOpenOverlay(); }
});

document.addEventListener('DOMContentLoaded', () => {
    const params = new URLSearchParams(window.location.search);
    const searchQ = params.get('search');
    if(searchQ) { document.getElementById('searchInput').value = searchQ; searchTerm = searchQ; }
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    window.lang = savedLang;
    pageLanguageCallback(savedLang);
    checkWishlistReminders();
});
</script>
<!-- Floating Cart button — sits right above the Krishimitra chat icon.
     Opens the same full cart drawer (add/remove/qty/checkout) used elsewhere
     on this page, so there's only ever one cart, never two separate carts.
     Hovering shows a quick preview (like Amazon/Flipkart's mini cart). -->
<div class="floating-cart-wrap" id="floatingCartWrap" style="display:none">
    <div class="floating-cart-preview" id="floatingCartPreview"></div>
    <button class="floating-cart-btn" id="floatingCartBtn" onclick="openCart()" title="Cart">
        <i class="fa-solid fa-cart-shopping"></i>
        <span class="floating-cart-badge" id="cartBadge">0</span>
    </button>
</div>

<?php include __DIR__ . '/krishimitra_widget.php'; ?>

<script>
/* ---------------------------------------------------------------------
   Mobile floating-action stack: Filter / Cart / KrishiMitra
   -----------------------------------------------------------------------
   Filter and Cart are positioned entirely by CSS (calc() off the
   --km-bottom / --km-height / --fab-* variables defined in the
   "single vertical floating-action stack" stylesheet block), so their
   position is always internally consistent and never drifts out of
   sync with each other. This script has exactly two jobs:

   1. Measure KrishiMitra's *real* rendered bottom offset and height
      (it lives in krishimitra_widget.php, a separate include we don't
      control) and publish them as --km-bottom / --km-height so Cart
      and Filter stack correctly above it — KrishiMitra itself is only
      ever read, never moved or resized.
   2. Force KrishiMitra's own `right` offset to match Filter/Cart's
      (mobile only) so all three share one center-X column, and toggle
      a body class that tells the CSS whether Cart is part of the
      stack right now (logged in) or not (logged out).

   A previous version of this recalculated Filter/Cart's own bottom/
   right in JS on every DOM mutation across the whole page, which could
   drift out of step with itself and cause jitter/overlap — that's
   gone now; the buttons' own position is pure CSS. */
(function(){
    function findWidgetBtn(){
        return document.getElementById('krishiMitraBtn')
            || document.querySelector('.krishimitra-widget button, .krishimitra-widget .km-launcher, #krishimitraWidget button, [id*="rishimitra" i] button, [class*="rishimitra" i]');
    }

    let measureAttempts = 0;
    const MAX_MEASURE_ATTEMPTS = 30; // ~6s of 200ms polling, for widgets that mount asynchronously

    function updateFabCartVisibility(){
        // Mirrors the mobile Cart-visibility rule in updateCartBadge():
        // on mobile the Cart slot is part of the stack whenever the user
        // is logged in, independent of cart contents.
        const isMobile = window.innerWidth <= 768;
        document.body.classList.toggle('fab-cart-visible', isMobile && IS_LOGGED_IN);
    }

    function measureKrishiMitra(){
        const isMobile = window.innerWidth <= 768;
        const widgetBtn = findWidgetBtn();

        if (!widgetBtn) {
            if (isMobile && measureAttempts < MAX_MEASURE_ATTEMPTS) {
                measureAttempts++;
                setTimeout(measureKrishiMitra, 200);
            }
            return;
        }

        if (!isMobile) {
            // Never touch KrishiMitra's own positioning on desktop/tablet.
            widgetBtn.style.removeProperty('right');
            widgetBtn.style.removeProperty('left');
            return;
        }

        // Position-only override so KrishiMitra shares Filter/Cart's
        // right edge and center-X column — its size/design/behaviour
        // is untouched.
        widgetBtn.style.setProperty('right', 'var(--fab-right, 18px)', 'important');
        widgetBtn.style.setProperty('left', 'auto', 'important');

        const rect = widgetBtn.getBoundingClientRect();
        if (rect.width === 0 && rect.height === 0) {
            if (measureAttempts < MAX_MEASURE_ATTEMPTS) {
                measureAttempts++;
                setTimeout(measureKrishiMitra, 200);
            }
            return; // not rendered/visible yet — keep the CSS fallback values
        }
        const kmBottom = Math.max(0, window.innerHeight - rect.bottom);
        document.documentElement.style.setProperty('--km-bottom', kmBottom + 'px');
        document.documentElement.style.setProperty('--km-height', rect.height + 'px');
    }

    window.updateFabStack = function updateFabStack(){
        updateFabCartVisibility();
        measureAttempts = 0;
        measureKrishiMitra();
    };

    window.addEventListener('load', function(){
        window.updateFabStack();
        // A couple of delayed follow-ups in case the widget mounts async.
        setTimeout(window.updateFabStack, 800);
        setTimeout(window.updateFabStack, 2000);
    });
    window.addEventListener('resize', function(){
        if (typeof updateCartBadge === 'function') updateCartBadge(); // also re-checks Cart show/hide
        window.updateFabStack();
    });

    // Watch specifically for the KrishiMitra widget's own container
    // (not the whole page) so a late-mounting launcher button is
    // caught without triggering a reposition storm from unrelated DOM
    // churn elsewhere on the page.
    const kmContainer =
        document.querySelector('.krishimitra-widget, #krishimitraWidget') || document.body;
    const mo = new MutationObserver(function(){ window.updateFabStack(); });
    mo.observe(kmContainer, { attributes: true, childList: true, subtree: true, attributeFilter: ['style', 'class'] });

    /* ---- Best-effort mutual exclusion with the KrishiMitra widget ----
       krishimitra_widget.php is a separate include not present in this
       file, so its actual open/close function and DOM structure aren't
       visible here. As a best-effort integration (until that file can
       be updated directly):
       - clicking the detected KrishiMitra launcher closes the Filter
         panel, since that click is what typically opens its chat window;
       - window.closeKrishiMitraForFilter is exposed as a hook so the
         widget's own script can register a real close handler, e.g.:
             window.closeKrishiMitraForFilter = function(){ closeMyChatWindow(); };
       True guaranteed mutual exclusion (KrishiMitra never covering the
       Filter panel, chat opening left, etc.) needs a small edit inside
       krishimitra_widget.php itself. */
    let kmClickHookAttached = false;
    function attachKrishiMitraClickHook(){
        if (kmClickHookAttached) return;
        const widgetBtn = findWidgetBtn();
        if (!widgetBtn) return;
        widgetBtn.addEventListener('click', function(){
            if (typeof toggleMobileFilters === 'function') toggleMobileFilters(false);
        });
        kmClickHookAttached = true;
    }
    window.addEventListener('load', function(){
        attachKrishiMitraClickHook();
        setTimeout(attachKrishiMitraClickHook, 500);
        setTimeout(attachKrishiMitraClickHook, 1500);
    });
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>