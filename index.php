<?php include __DIR__ . '/includes/header.php'; ?>

<div class="slider-wrap">

    <div class="slide active" style="background-image:url('<?php echo $base_path; ?>/assets/images/home.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/marketplace.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s1-tag">AgriCart Portal</div>
            <h1 id="s1-h">Smart Farming<br>Meets E-Commerce</h1>
            <p id="s1-p">India's most trusted digital platform connecting farmers with seeds, tools, and market prices.</p>
            <div class="hero-search" onclick="event.stopPropagation()">
                <input type="text" placeholder="Search seeds, fertilizers, equipment..." id="hero-search-input">
                <button onclick="doSearch()" id="hero-search-btn"><i class="fa-solid fa-magnifying-glass"></i> Search</button>
            </div>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/agristore.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/marketplace.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s2-tag">Agri Store</div>
            <h1 id="s2-h">E-Commerce Marketplace</h1>
            <p id="s2-p">Buy certified seeds, organic fertilizers, and pesticides directly from verified sellers.</p>
            <a href="<?php echo $base_path; ?>/pages/marketplace.php" class="slide-cta" id="s2-btn" onclick="event.stopPropagation()">Open Agri Store</a>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/equipment.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s3-tag">Rental Hub</div>
            <h1 id="s3-h">Heavy Machinery Rental</h1>
            <p id="s3-p">Rent tractors, drone sprayers, and harvesting equipment by the hour or day.</p>
            <a href="<?php echo $base_path; ?>/pages/rental.php" class="slide-cta" id="s3-btn" onclick="event.stopPropagation()">Rent Equipment</a>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/advisory.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s4-tag">Crop Advisory</div>
            <h1 id="s4-h">AI-Powered Crop Advisory</h1>
            <p id="s4-p">Detect plant diseases and get expert recommendations using machine learning.</p>
            <a href="<?php echo $base_path; ?>/pages/advisory.php" class="slide-cta" id="s4-btn" onclick="event.stopPropagation()">Get Crop Advisory</a>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/krishi_bazaar.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s5-tag">Krishi Bazaar</div>
            <h1 id="s5-h">Live APMC Market Rates</h1>
            <p id="s5-p">Track real-time commodity prices. No middlemen. Maximum value for your harvest.</p>
            <a href="<?php echo $base_path; ?>/pages/krishi_bazaar.php" class="slide-cta" id="s5-btn" onclick="event.stopPropagation()">Check Live Rates</a>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/agriconnect.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/agri-connect.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s6-tag">Agri Connect</div>
            <h1 id="s6-h">Farmer Digital Agriconnect</h1>
            <p id="s6-p">Discuss farming challenges, share tips, and learn from thousands of fellow farmers.</p>
            <a href="<?php echo $base_path; ?>/pages/agri-connect.php" class="slide-cta" id="s6-btn" onclick="event.stopPropagation()">Enter the Forum</a>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/contact.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s7-tag">Support</div>
            <h1 id="s7-h">Expert Help, When You Need It</h1>
            <p id="s7-p">Our agritech support team is always ready to assist you — call, chat, or message.</p>
            <a href="<?php echo $base_path; ?>/pages/contact.php" class="slide-cta" id="s7-btn" onclick="event.stopPropagation()">Contact Us</a>
        </div>
    </div>

    <div class="slider-dots" id="sliderDots"></div>
</div>

<?php
// ─── DYNAMIC PLATFORM STATS (live counts from DB, real numbers — no demo floor) ───
// Wrapped in try/catch because mysqli throws exceptions on bad queries (missing
// table/column on some installs) — we never want that to crash the homepage.
$stat_farmers   = 0;
$stat_products  = 0;
$stat_merchants = 0;
$stat_rating    = 0;
$stat_reviews_count = 0;

function agri_safe_count($conn, $sql) {
    try {
        $res = @$conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) return (int)$row['c'];
    } catch (\Throwable $e) {
        // Table/column missing or any DB error — just skip, keep 0.
    }
    return null;
}

if (isset($conn) && $conn instanceof mysqli) {
    $c = agri_safe_count($conn, "SELECT COUNT(*) c FROM users WHERE role='farmer'");
    if ($c !== null) $stat_farmers = $c;

    // "Certified Products" must mean the same thing here as on the
    // marketplace page: only live, admin-approved listings — not pending/
    // rejected/deactivated rows. This mirrors marketplace.php's product
    // query so the two numbers never disagree, and it moves in real time
    // as products are added, removed, approved, or deactivated.
    $hasApprovalCol = false;
    try {
        $colCheck = @$conn->query("SHOW COLUMNS FROM products LIKE 'approval_status'");
        $hasApprovalCol = ($colCheck && $colCheck->num_rows > 0);
    } catch (\Throwable $eCol) {
        // Can't confirm — fall back to the is_active-only count below.
    }
    if ($hasApprovalCol) {
        $c = agri_safe_count($conn, "SELECT COUNT(*) c FROM products WHERE is_active = 1 AND (approval_status IS NULL OR approval_status = 'approved')");
    } else {
        $c = agri_safe_count($conn, "SELECT COUNT(*) c FROM products WHERE is_active = 1");
    }
    if ($c === null) {
        // is_active column missing entirely on this install — fall back to a raw count.
        $c = agri_safe_count($conn, "SELECT COUNT(*) c FROM products");
    }
    if ($c !== null) $stat_products = $c;

    // Merchants = distinct sellers behind those same certified, live products
    // (not every name ever entered in the table, including inactive ones).
    $merchantWhere = $hasApprovalCol
        ? "is_active = 1 AND (approval_status IS NULL OR approval_status = 'approved')"
        : "is_active = 1";
    $c = agri_safe_count($conn, "SELECT COUNT(DISTINCT farmer_name) c FROM products WHERE {$merchantWhere} AND farmer_name IS NOT NULL AND farmer_name <> ''");
    if ($c === null) {
        $c = agri_safe_count($conn, "SELECT COUNT(DISTINCT seller_name) c FROM products WHERE {$merchantWhere} AND seller_name IS NOT NULL AND seller_name <> ''");
    }
    if ($c !== null) $stat_merchants = $c;

    try {
        $res = @$conn->query("SELECT AVG(rating) a, COUNT(*) c FROM reviews");
        if ($res && ($row = $res->fetch_assoc())) {
            if ($row['a'] !== null) $stat_rating = round((float)$row['a'], 1);
            $stat_reviews_count = (int)$row['c'];
        }
    } catch (\Throwable $e) {
        // reviews table missing — keep 0.
    }
}

function agri_fmt_stat($n) { return number_format($n) . '+'; }
?>
<div class="stats">
    <div class="stat-item">
        <h3><?php echo agri_fmt_stat($stat_farmers); ?></h3>
        <p id="st1">Registered Farmers</p>
    </div>
    <div class="stat-item">
        <h3><?php echo agri_fmt_stat($stat_products); ?></h3>
        <p id="st2">Certified Products</p>
    </div>
    <div class="stat-item">
        <h3><?php echo agri_fmt_stat($stat_merchants); ?></h3>
        <p id="st3">Verified Merchants</p>
    </div>
    <div class="stat-item">
        <div class="mini-stars"><?php echo $stat_reviews_count > 0 ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?></div>
        <h3><?php echo $stat_reviews_count > 0 ? $stat_rating : 'New'; ?></h3>
        <p id="st4">Platform Rating</p>
    </div>
</div>

<section class="gallery-section">
    <div class="gallery-head">
        <div class="section-label" id="gal-label">What We Offer</div>
        <h2 class="section-title" id="gal-title">Everything a Farmer Needs</h2>
        <p class="section-sub" id="gal-sub">From buying seeds to selling your harvest — manage your entire farm journey in one place.</p>
    </div>
    <div class="gallery-grid">

        <div class="gallery-card">
            <div class="gallery-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/agristore.png');">
                <span class="gallery-img-tag" id="gt1">Agri Store</span>
            </div>
            <div class="gallery-body">
                <h4 id="g-h1">Agri Store</h4>
                <p id="g-p1">Buy certified seeds, organic fertilizers, and pesticides directly from verified sellers.</p>
                <a href="<?php echo $base_path; ?>/pages/marketplace.php" class="gallery-btn" id="g-b1">Browse Store</a>
            </div>
        </div>

        <div class="gallery-card">
            <div class="gallery-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/equipment.png');">
                <span class="gallery-img-tag" id="gt2">Equipment Rental</span>
            </div>
            <div class="gallery-body">
                <h4 id="g-h2">Equipment Rental</h4>
                <p id="g-p2">Rent tractors, rotavators, drone sprayers, and more. Pay per hour, no long-term commitment needed.</p>
                <a href="<?php echo $base_path; ?>/pages/rental.php" class="gallery-btn" id="g-b2">Browse Equipment</a>
            </div>
        </div>

        <div class="gallery-card">
            <div class="gallery-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory.png');">
                <span class="gallery-img-tag" id="gt3">Crop Advisory</span>
            </div>
            <div class="gallery-body">
                <h4 id="g-h3">Crop Advisory</h4>
                <p id="g-p3">Upload a photo of your crop and get instant AI-based diagnosis and treatment recommendations.</p>
                <a href="<?php echo $base_path; ?>/pages/advisory.php" class="gallery-btn" id="g-b3">Get Free Advice</a>
            </div>
        </div>

        <div class="gallery-card">
            <div class="gallery-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar.png');">
                <span class="gallery-img-tag" id="gt4">Krishi Bazaar</span>
            </div>
            <div class="gallery-body">
                <h4 id="g-h4">Krishi Bazaar</h4>
                <p id="g-p4">Skip the middleman. Check live mandi rates for wheat, cotton, onion, and 50+ other crops.</p>
                <a href="<?php echo $base_path; ?>/pages/krishi_bazaar.php" class="gallery-btn" id="g-b4">View Live Rates</a>
            </div>
        </div>

        <div class="gallery-card">
            <div class="gallery-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/agriconnect.png');">
                <span class="gallery-img-tag" id="gt5">Agri Connect</span>
            </div>
            <div class="gallery-body">
                <h4 id="g-h5">Agri Connect</h4>
                <p id="g-p5">Discuss farming challenges, share tips, and learn from thousands of fellow farmers.</p>
                <a href="<?php echo $base_path; ?>/pages/agri-connect.php" class="gallery-btn" id="g-b5">Join the Community</a>
            </div>
        </div>

        <div class="gallery-card">
            <div class="gallery-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/contact.png');">
                <span class="gallery-img-tag" id="gt6">Support</span>
            </div>
            <div class="gallery-body">
                <h4 id="g-h6">Contact / Support</h4>
                <p id="g-p6">Our agritech support team is always ready to assist you — call, chat, or message anytime.</p>
                <a href="<?php echo $base_path; ?>/pages/contact.php" class="gallery-btn" id="g-b6">Contact Us</a>
            </div>
        </div>

    </div>
</section>

<section class="widget-section">
    <div class="section-label" id="wid-label">Live Tools</div>
    <h2 class="section-title" id="wid-title">Farm Smarter with Live Data</h2>
    <p class="section-sub" id="wid-sub">Real-time weather, government schemes, and crop disease scanning — all in one dashboard.</p>

    <div class="widget-grid">

        <div class="widget-card" id="wd-card">
            <h3><i class="fa-solid fa-cloud-sun" style="color:#4CAF50;"></i> <span id="wd-title">
                <?php
                $lang = $_COOKIE['agri_lang'] ?? 'en';
                $titles = ['en'=>"Today's Weather Forecast",'mr'=>'आजचे हवामान','hi'=>'आज का मौसम पूर्वानुमान'];
                echo htmlspecialchars($titles[$lang] ?? $titles['en']);
                ?>
            </span></h3>

            <?php
            $lang = $_GET['lang'] ?? $_COOKIE['agri_lang'] ?? 'en';
            if (!in_array($lang, ['en','mr','hi'])) $lang = 'en';
            ?>
            <div id="wd-content">
                <div id="wd-loading" style="text-align:center;padding:30px;color:#888;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x" style="color:#4CAF50;margin-bottom:10px;"></i><br>
                    <span id="wd-loading-txt"><?=$lang==='mr'?'स्थान शोधत आहे...':($lang==='hi'?'स्थान सर्च किया जा रहा है...':'Detecting your location...')?></span>
                </div>
                <div id="wd-error" style="display:none;text-align:center;padding:10px;color:#e65100;font-size:13px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <span id="wd-err-txt"><?=$lang==='mr'?'स्थान परवानगी नाकारली.':($lang==='hi'?'स्थान अनुमति अस्वीकार.':'Location access denied. Please allow location.')?></span>
                    <br><button onclick="loadWeatherGPS()" style="margin-top:8px;padding:6px 16px;background:#4CAF50;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;"><?=$lang==='mr'?'पुन्हा प्रयत्न करा':($lang==='hi'?'फिर प्रयास करें':'Try Again')?></button>
                </div>
            </div>
        </div>

        <div class="widget-card">
            <h3><i class="fa-solid fa-landmark" style="color:#4CAF50;"></i> <span id="scheme-title">Government Schemes for Farmers</span></h3>
            <div class="scheme-list" id="scheme-list">
                <a href="https://pmkisan.gov.in" target="_blank" rel="noopener" class="scheme-item">
                    <div class="scheme-ic"><i class="fa-solid fa-hand-holding-dollar"></i></div>
                    <div class="scheme-info">
                        <h4 id="sch1-name">PM-KISAN</h4>
                        <p id="sch1-desc">₹6,000/year direct income support for landholding farmers, in 3 installments.</p>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square scheme-go"></i>
                </a>
                <a href="https://pmfby.gov.in" target="_blank" rel="noopener" class="scheme-item">
                    <div class="scheme-ic"><i class="fa-solid fa-shield-halved"></i></div>
                    <div class="scheme-info">
                        <h4 id="sch2-name">PM Fasal Bima Yojana</h4>
                        <p id="sch2-desc">Low-premium crop insurance against natural calamities, pests and disease.</p>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square scheme-go"></i>
                </a>
                <a href="https://www.myscheme.gov.in/schemes/kcc" target="_blank" rel="noopener" class="scheme-item">
                    <div class="scheme-ic"><i class="fa-solid fa-credit-card"></i></div>
                    <div class="scheme-info">
                        <h4 id="sch3-name">Kisan Credit Card (KCC)</h4>
                        <p id="sch3-desc">Low-interest loans up to ₹3 lakh for crop and farming needs.</p>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square scheme-go"></i>
                </a>
                <a href="https://soilhealth.dac.gov.in" target="_blank" rel="noopener" class="scheme-item">
                    <div class="scheme-ic"><i class="fa-solid fa-vial"></i></div>
                    <div class="scheme-info">
                        <h4 id="sch4-name">Soil Health Card</h4>
                        <p id="sch4-desc">Free soil testing with nutrient and fertilizer recommendations.</p>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square scheme-go"></i>
                </a>
                <a href="https://pmkusum.mnre.gov.in" target="_blank" rel="noopener" class="scheme-item">
                    <div class="scheme-ic"><i class="fa-solid fa-solar-panel"></i></div>
                    <div class="scheme-info">
                        <h4 id="sch5-name">PM-KUSUM</h4>
                        <p id="sch5-desc">Subsidy up to 60% on solar irrigation pumps and grid-connected solar power.</p>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square scheme-go"></i>
                </a>
                <a href="https://enam.gov.in" target="_blank" rel="noopener" class="scheme-item">
                    <div class="scheme-ic"><i class="fa-solid fa-store"></i></div>
                    <div class="scheme-info">
                        <h4 id="sch6-name">e-NAM</h4>
                        <p id="sch6-desc">Sell your produce online across India's mandis — no middlemen.</p>
                    </div>
                    <i class="fa-solid fa-arrow-up-right-from-square scheme-go"></i>
                </a>
            </div>
            <div class="scan-box" onclick="openLeafScanner()">
                <i class="fa-solid fa-camera"></i>
                <h4 id="scan-title">Leaf Disease Scanner</h4>
                <p id="scan-sub">Tap here to upload a crop photo for instant AI diagnosis</p>
            </div>
        </div>

    </div>
</section>

<section class="testimonials-section">
    <div class="testimonials-head">
        <div class="section-label" id="test-label">Farmer Stories</div>
        <h2 class="section-title" id="test-title">What Farmers Are Saying</h2>
        <p class="section-sub" id="test-sub">Real feedback from real farmers across Maharashtra who use AgriCart every day.</p>
    </div>
    <div class="testimonial-grid">
        <div class="test-card">
            <div class="test-stars">
                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
            </div>
            <p id="t1-msg">"AgriCart helped me buy quality Mahabeej seeds directly, without any middlemen. The live tracking for delivery is very accurate."</p>
            <h5 id="t1-auth">— Ramesh Patil, Nashik</h5>
        </div>
        <div class="test-card">
            <div class="test-stars">
                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star-half-stroke"></i>
            </div>
            <p id="t2-msg">"I uploaded a photo of my diseased cotton plant and within seconds the AI identified Powdery Mildew and told me exactly what to spray!"</p>
            <h5 id="t2-auth">— Haribhau Bhoir, Palghar</h5>
        </div>
        <div class="test-card">
            <div class="test-stars">
                <i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i><i class="fa-solid fa-star"></i>
            </div>
            <p id="t3-msg">"The Krishi Bazaar rates saved me from selling at a loss. I waited 3 days after checking the live mandi price and made ₹8,000 more on my onion crop."</p>
            <h5 id="t3-auth">— Suresh Wagh, Pune District</h5>
        </div>
    </div>
</section>


<?php include __DIR__ . '/includes/footer.php'; ?>

<?php include_once __DIR__ . '/pages/krishimitra_widget.php'; ?>

<script>
// Weather is loaded/refreshed via AJAX so only the widget updates,
// never the whole page.
let _wdLastLat = null, _wdLastLon = null;

async function fetchWeatherAjax(lat, lon, lang) {
    const content = document.getElementById('wd-content');
    const loadingEl = document.getElementById('wd-loading');
    const errorEl = document.getElementById('wd-error');
    if (loadingEl) loadingEl.style.display = 'block';
    if (errorEl) errorEl.style.display = 'none';
    const oldBody = document.getElementById('wd-body');
    if (oldBody) oldBody.style.display = 'none';

    try {
        const resp = await fetch('<?php echo $base_path; ?>/pages/fetch_weather.php?wlat=' + lat + '&wlon=' + lon + '&lang=' + lang, { cache: 'no-store' });
        const html = await resp.text();
        content.innerHTML = html;
        _wdLastLat = lat; _wdLastLon = lon;
    } catch (e) {
        const l2 = document.getElementById('wd-loading');
        const e2 = document.getElementById('wd-error');
        if (l2) l2.style.display = 'none';
        if (e2) e2.style.display = 'block';
    }
}

function loadWeatherGPS() {
    const loadingEl = document.getElementById('wd-loading');
    const errorEl = document.getElementById('wd-error');
    if (loadingEl) loadingEl.style.display = 'block';
    if (errorEl) errorEl.style.display = 'none';
    navigator.geolocation.getCurrentPosition(
        p => { fetchWeatherAjax(p.coords.latitude, p.coords.longitude, localStorage.getItem('agri_lang') || 'en'); },
        () => {
            const l2 = document.getElementById('wd-loading');
            const e2 = document.getElementById('wd-error');
            if (l2) l2.style.display = 'none';
            if (e2) e2.style.display = 'block';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}
// Always fetch weather on page load via AJAX (no full reload, no GET params needed).
loadWeatherGPS();

// Reliable weather refresh hook — called directly from applyHomeTranslations()
// below, so it works no matter which switchLanguage implementation happens to
// be active (agri-master.js redefines switchLanguage and doesn't always fire
// the 'agri-lang-changed' event reliably).
window.AGRI_refreshWeatherForLang = function(lang) {
    if (_wdLastLat !== null && _wdLastLon !== null) {
        fetchWeatherAjax(_wdLastLat, _wdLastLon, lang);
    } else {
        // Location wasn't ready yet when the user switched language (still loading,
        // or the first geolocation attempt hadn't resolved) — try again now instead
        // of leaving the widget untranslated until a full page refresh.
        loadWeatherGPS();
    }
};

// Language बदलल्यावर फक्त weather widget refresh — संपूर्ण पान नाही
let _weatherLangReady = false;
setTimeout(() => { _weatherLangReady = true; }, 500); // page load event ignore करा

document.addEventListener('agri-lang-changed', function(e) {
    if (!_weatherLangReady) return; // page load वर ignore
    window.AGRI_refreshWeatherForLang(e.detail);
});
</script>

<script>
// ========== HOMEPAGE FULL TRANSLATION ==========
const HomeT = {
    en: {
        's1-tag':'AgriCart Portal', 's1-h':'Smart Farming<br>Meets E-Commerce',
        's1-p':"India's most trusted digital platform connecting farmers with seeds, tools, and market prices.",
        '_heroPlaceholder':'Search seeds, fertilizers, equipment...', '_heroBtn':'Search',

        's2-tag':'Agri Store', 's2-h':'E-Commerce Marketplace',
        's2-p':'Buy certified seeds, organic fertilizers, and pesticides directly from verified sellers.', 's2-btn':'Open Agri Store',

        's3-tag':'Rental Hub', 's3-h':'Heavy Machinery Rental',
        's3-p':'Rent tractors, drone sprayers, and harvesting equipment by the hour or day.', 's3-btn':'Rent Equipment',

        's4-tag':'Crop Advisory', 's4-h':'AI-Powered Crop Advisory',
        's4-p':'Detect plant diseases and get expert recommendations using machine learning.', 's4-btn':'Get Crop Advisory',

        's5-tag':'Krishi Bazaar', 's5-h':'Live APMC Market Rates',
        's5-p':'Track real-time commodity prices. No middlemen. Maximum value for your harvest.', 's5-btn':'Check Live Rates',

        's6-tag':'Agri Connect', 's6-h':'Shetkari Digital Agriconnect',
        's6-p':'Discuss farming challenges, share tips, and learn from thousands of fellow farmers.', 's6-btn':'Enter the Forum',

        's7-tag':'Support', 's7-h':'Expert Help, When You Need It',
        's7-p':'Our agritech support team is always ready to assist you — call, chat, or message.', 's7-btn':'Contact Us',

        'st1':'Registered Farmers', 'st2':'Certified Products', 'st3':'Verified Merchants', 'st4':'Platform Rating',

        'gal-label':'What We Offer', 'gal-title':'Everything a Farmer Needs',
        'gal-sub':'From buying seeds to selling your harvest — manage your entire farm journey in one place.',

        'gt1':'Agri Store', 'g-h1':'Agri Store', 'g-p1':'Buy certified seeds, organic fertilizers, and pesticides directly from verified sellers.', 'g-b1':'Browse Store',
        'gt2':'Equipment Rental', 'g-h2':'Equipment Rental', 'g-p2':'Rent tractors, rotavators, drone sprayers, and more. Pay per hour, no long-term commitment needed.', 'g-b2':'Browse Equipment',
        'gt3':'Crop Advisory', 'g-h3':'Crop Advisory', 'g-p3':'Upload a photo of your crop and get instant AI-based diagnosis and treatment recommendations.', 'g-b3':'Get Free Advice',
        'gt4':'Krishi Bazaar', 'g-h4':'Krishi Bazaar', 'g-p4':'Skip the middleman. Check live mandi rates for wheat, cotton, onion, and 50+ other crops.', 'g-b4':'View Live Rates',
        'gt5':'Agri Connect', 'g-h5':'Agri Connect', 'g-p5':'Discuss farming challenges, share tips, and learn from thousands of fellow farmers.', 'g-b5':'Join the Community',
        'gt6':'Support', 'g-h6':'Contact / Support', 'g-p6':'Our agritech support team is always ready to assist you — call, chat, or message anytime.', 'g-b6':'Contact Us',

        'wid-label':'Live Tools', 'wid-title':'Farm Smarter with Live Data',
        'wid-sub':'Real-time weather, government schemes, and crop disease scanning — all in one dashboard.',

        'scheme-title':'Government Schemes for Farmers',
        'sch1-name':'PM-KISAN', 'sch1-desc':'₹6,000/year direct income support for landholding farmers, in 3 installments.',
        'sch2-name':'PM Fasal Bima Yojana', 'sch2-desc':'Low-premium crop insurance against natural calamities, pests and disease.',
        'sch3-name':'Kisan Credit Card (KCC)', 'sch3-desc':'Low-interest loans up to ₹3 lakh for crop and farming needs.',
        'sch4-name':'Soil Health Card', 'sch4-desc':'Free soil testing with nutrient and fertilizer recommendations.',
        'sch5-name':'PM-KUSUM', 'sch5-desc':'Subsidy up to 60% on solar irrigation pumps and grid-connected solar power.',
        'sch6-name':'e-NAM', 'sch6-desc':"Sell your produce online across India's mandis — no middlemen.",

        'scan-title':'Leaf Disease Scanner', 'scan-sub':'Tap here to upload a crop photo for instant AI diagnosis',

        'test-label':'Farmer Stories', 'test-title':'What Farmers Are Saying',
        'test-sub':'Real feedback from real farmers across Maharashtra who use AgriCart every day.',
        't1-msg':'"AgriCart helped me buy quality Mahabeej seeds directly, without any middlemen. The live tracking for delivery is very accurate."', 't1-auth':'— Ramesh Patil, Nashik',
        't2-msg':'"I uploaded a photo of my diseased cotton plant and within seconds the AI identified Powdery Mildew and told me exactly what to spray!"', 't2-auth':'— Haribhau Bhoir, Palghar',
        't3-msg':'"The Krishi Bazaar rates saved me from selling at a loss. I waited 3 days after checking the live mandi price and made ₹8,000 more on my onion crop."', 't3-auth':'— Suresh Wagh, Pune District',

        'ft-tag':'Empowering Indian Farmers Through Technology',
        'ft-copy':'© 2026 AgriCart. All Rights Reserved. | Made with ❤️ for Indian Farmers'
    },
    mr: {
        's1-tag':'अ‍ॅग्रीकार्ट पोर्टल', 's1-h':'स्मार्ट शेती आणि<br>ई-कॉमर्स',
        's1-p':'शेतकऱ्यांना बियाणे, साधने आणि बाजारभावाशी जोडणारे भारतातील सर्वात विश्वासार्ह डिजिटल व्यासपीठ.',
        '_heroPlaceholder':'बियाणे, खते, अवजारे शोधा...', '_heroBtn':'शोधा',

        's2-tag':'कृषी स्टोअर', 's2-h':'ई-कॉमर्स मार्केटप्लेस',
        's2-p':'सत्यापित विक्रेत्यांकडून प्रमाणित बियाणे, सेंद्रिय खते आणि कीटकनाशके थेट खरेदी करा.', 's2-btn':'कृषी स्टोअर उघडा',

        's3-tag':'अवजारे केंद्र', 's3-h':'जड यंत्रसामग्री भाड्याने',
        's3-p':'ट्रॅक्टर, ड्रोन फवारणी यंत्रे आणि कापणी यंत्रे तासाने किंवा दिवसाने भाड्याने घ्या.', 's3-btn':'अवजारे भाड्याने घ्या',

        's4-tag':'पीक सल्ला', 's4-h':'AI-आधारित पीक सल्ला',
        's4-p':'यंत्रशिक्षण वापरून पिकांचे रोग ओळखा आणि तज्ज्ञांच्या शिफारसी मिळवा.', 's4-btn':'पीक सल्ला घ्या',

        's5-tag':'कृषी बाजार', 's5-h':'थेट APMC बाजारभाव',
        's5-p':'रिअल-टाइम बाजारभाव पहा. दलाल नाही. तुमच्या मालाला जास्तीत जास्त किंमत.', 's5-btn':'लाइव्ह भाव पहा',

        's6-tag':'कृषी कनेक्ट', 's6-h':'शेतकरी डिजिटल अ‍ॅग्रीकनेक्ट',
        's6-p':'शेतीच्या अडचणींवर चर्चा करा, टिप्स शेअर करा आणि हजारो शेतकऱ्यांकडून शिका.', 's6-btn':'चर्चेत सामील व्हा',

        's7-tag':'सहाय्य', 's7-h':'गरज असेल तेव्हा तज्ज्ञांची मदत',
        's7-p':'आमची अ‍ॅग्रीटेक सपोर्ट टीम कॉल, चॅट किंवा मेसेजद्वारे तुमच्या मदतीसाठी नेहमी तयार आहे.', 's7-btn':'संपर्क साधा',

        'st1':'नोंदणीकृत शेतकरी', 'st2':'प्रमाणित उत्पादने', 'st3':'सत्यापित व्यापारी', 'st4':'प्लॅटफॉर्म रेटिंग',

        'gal-label':'आम्ही काय देतो', 'gal-title':'शेतकऱ्याला लागणारं सर्वकाही',
        'gal-sub':'बियाणे खरेदीपासून तुमचा माल विकण्यापर्यंत — तुमचा संपूर्ण शेती प्रवास एकाच ठिकाणी सांभाळा.',

        'gt1':'कृषी स्टोअर', 'g-h1':'कृषी स्टोअर', 'g-p1':'सत्यापित विक्रेत्यांकडून प्रमाणित बियाणे, सेंद्रिय खते आणि कीटकनाशके थेट खरेदी करा.', 'g-b1':'स्टोअर पहा',
        'gt2':'अवजारे भाडे', 'g-h2':'अवजारे भाडे', 'g-p2':'ट्रॅक्टर, रोटाव्हेटर, ड्रोन फवारणी यंत्रे आणि बरंच काही. तासाने भाडे, दीर्घकालीन बांधिलकी नाही.', 'g-b2':'अवजारे पहा',
        'gt3':'पीक सल्ला', 'g-h3':'पीक सल्ला', 'g-p3':'तुमच्या पिकाचा फोटो अपलोड करा आणि त्वरित AI-आधारित निदान व उपचार शिफारसी मिळवा.', 'g-b3':'मोफत सल्ला घ्या',
        'gt4':'कृषी बाजार', 'g-h4':'कृषी बाजार', 'g-p4':'दलाल टाळा. गहू, कापूस, कांदा आणि 50+ इतर पिकांचे लाइव्ह मंडई भाव पहा.', 'g-b4':'लाइव्ह भाव पहा',
        'gt5':'कृषी कनेक्ट', 'g-h5':'कृषी कनेक्ट', 'g-p5':'शेतीच्या अडचणींवर चर्चा करा, टिप्स शेअर करा आणि हजारो शेतकऱ्यांकडून शिका.', 'g-b5':'समुदायात सामील व्हा',
        'gt6':'सहाय्य', 'g-h6':'संपर्क / सहाय्य', 'g-p6':'आमची अ‍ॅग्रीटेक सपोर्ट टीम कॉल, चॅट किंवा मेसेजद्वारे कधीही तुमच्या मदतीसाठी तयार आहे.', 'g-b6':'संपर्क साधा',

        'wid-label':'लाइव्ह साधने', 'wid-title':'लाइव्ह डेटासह हुशारीने शेती करा',
        'wid-sub':'रिअल-टाइम हवामान, सरकारी योजना आणि पीक रोग स्कॅनिंग — सर्व एकाच डॅशबोर्डवर.',

        'scheme-title':'शेतकऱ्यांसाठी सरकारी योजना',
        'sch1-name':'पीएम-किसान', 'sch1-desc':'जमीनधारक शेतकऱ्यांसाठी वर्षाला ₹6,000 थेट उत्पन्न सहाय्य, 3 हप्त्यांमध्ये.',
        'sch2-name':'पीएम फसल विमा योजना', 'sch2-desc':'नैसर्गिक आपत्ती, कीड आणि रोगांविरुद्ध कमी हप्त्याचा पीक विमा.',
        'sch3-name':'किसान क्रेडिट कार्ड (KCC)', 'sch3-desc':'पीक आणि शेतीच्या गरजांसाठी ₹3 लाखांपर्यंत कमी व्याजाचे कर्ज.',
        'sch4-name':'माती आरोग्य कार्ड', 'sch4-desc':'पोषक व खत शिफारसींसह मोफत माती तपासणी.',
        'sch5-name':'पीएम-कुसुम', 'sch5-desc':'सौर सिंचन पंप आणि ग्रिड-जोडलेल्या सौर ऊर्जेवर 60% पर्यंत अनुदान.',
        'sch6-name':'ई-नाम', 'sch6-desc':'भारतातील मंडईंमध्ये तुमचा माल ऑनलाइन विका — दलाल नाही.',

        'scan-title':'पान रोग स्कॅनर', 'scan-sub':'त्वरित AI निदानासाठी पिकाचा फोटो अपलोड करण्यासाठी इथे टॅप करा',

        'test-label':'शेतकऱ्यांच्या गोष्टी', 'test-title':'शेतकरी काय म्हणतात',
        'test-sub':'महाराष्ट्रातील खऱ्या शेतकऱ्यांचा अभिप्राय, जे दररोज AgriCart वापरतात.',
        't1-msg':'"AgriCart मुळे मला दलालांशिवाय थेट दर्जेदार महाबीज बियाणे खरेदी करता आले. डिलिव्हरीचे लाइव्ह ट्रॅकिंग खूप अचूक आहे."', 't1-auth':'— रमेश पाटील, नाशिक',
        't2-msg':'"मी माझ्या रोगट कापूस झाडाचा फोटो अपलोड केला आणि काही सेकंदातच AI ने पावडरी मिल्ड्यू ओळखून नेमकं काय फवारायचं ते सांगितलं!"', 't2-auth':'— हरिभाऊ भोईर, पालघर',
        't3-msg':'"कृषी बाजाराच्या भावांमुळे मला तोट्यात विक्री करण्यापासून वाचवलं. लाइव्ह मंडई भाव पाहिल्यानंतर 3 दिवस थांबलो आणि कांद्याच्या पिकावर ₹8,000 जास्त कमावले."', 't3-auth':'— सुरेश वाघ, पुणे जिल्हा',

        'ft-tag':'तंत्रज्ञानाद्वारे भारतीय शेतकऱ्यांचे सक्षमीकरण',
        'ft-copy':'© 2026 AgriCart. सर्व हक्क राखीव. | भारतीय शेतकऱ्यांसाठी ❤️ ने बनवले'
    },
    hi: {
        's1-tag':'एग्रीकार्ट पोर्टल', 's1-h':'स्मार्ट खेती और<br>ई-कॉमर्स',
        's1-p':'किसानों को बीज, उपकरण और बाज़ार भाव से जोड़ने वाला भारत का सबसे भरोसेमंद डिजिटल मंच.',
        '_heroPlaceholder':'बीज, खाद, उपकरण खोजें...', '_heroBtn':'खोजें',

        's2-tag':'एग्री स्टोर', 's2-h':'ई-कॉमर्स मार्केटप्लेस',
        's2-p':'सत्यापित विक्रेताओं से प्रमाणित बीज, जैविक खाद और कीटनाशक सीधे खरीदें.', 's2-btn':'एग्री स्टोर खोलें',

        's3-tag':'रेंटल हब', 's3-h':'भारी मशीनरी किराए पर',
        's3-p':'ट्रैक्टर, ड्रोन स्प्रेयर और हार्वेस्टिंग उपकरण घंटे या दिन के हिसाब से किराए पर लें.', 's3-btn':'उपकरण किराए पर लें',

        's4-tag':'फ़सल सलाह', 's4-h':'AI-आधारित फ़सल सलाह',
        's4-p':'मशीन लर्निंग का उपयोग करके पौधों की बीमारियाँ पहचानें और विशेषज्ञ सुझाव पाएं.', 's4-btn':'फ़सल सलाह लें',

        's5-tag':'कृषि बाज़ार', 's5-h':'लाइव APMC बाज़ार भाव',
        's5-p':'रीयल-टाइम भाव देखें. कोई बिचौलिया नहीं. आपकी उपज की पूरी कीमत.', 's5-btn':'लाइव भाव देखें',

        's6-tag':'एग्री कनेक्ट', 's6-h':'शेतकरी डिजिटल एग्रीकनेक्ट',
        's6-p':'खेती की समस्याओं पर चर्चा करें, सुझाव साझा करें और हज़ारों किसानों से सीखें.', 's6-btn':'चर्चा में शामिल हों',

        's7-tag':'सहायता', 's7-h':'जब भी ज़रूरत हो, विशेषज्ञ मदद',
        's7-p':'हमारी एग्रीटेक सपोर्ट टीम कॉल, चैट या मैसेज के ज़रिए हमेशा आपकी मदद के लिए तैयार है.', 's7-btn':'संपर्क करें',

        'st1':'पंजीकृत किसान', 'st2':'प्रमाणित उत्पाद', 'st3':'सत्यापित व्यापारी', 'st4':'प्लेटफ़ॉर्म रेटिंग',

        'gal-label':'हम क्या देते हैं', 'gal-title':'किसान के लिए हर ज़रूरी चीज़',
        'gal-sub':'बीज खरीदने से लेकर अपनी उपज बेचने तक — अपनी पूरी खेती यात्रा एक ही जगह प्रबंधित करें.',

        'gt1':'एग्री स्टोर', 'g-h1':'एग्री स्टोर', 'g-p1':'सत्यापित विक्रेताओं से प्रमाणित बीज, जैविक खाद और कीटनाशक सीधे खरीदें.', 'g-b1':'स्टोर देखें',
        'gt2':'उपकरण किराया', 'g-h2':'उपकरण किराया', 'g-p2':'ट्रैक्टर, रोटावेटर, ड्रोन स्प्रेयर और भी बहुत कुछ. घंटे के हिसाब से किराया, कोई लंबी प्रतिबद्धता नहीं.', 'g-b2':'उपकरण देखें',
        'gt3':'फ़सल सलाह', 'g-h3':'फ़सल सलाह', 'g-p3':'अपनी फ़सल की फोटो अपलोड करें और तुरंत AI-आधारित निदान व उपचार सुझाव पाएं.', 'g-b3':'मुफ़्त सलाह लें',
        'gt4':'कृषि बाज़ार', 'g-h4':'कृषि बाज़ार', 'g-p4':'बिचौलिये को छोड़ें. गेहूं, कपास, प्याज़ और 50+ अन्य फ़सलों के लाइव मंडी भाव देखें.', 'g-b4':'लाइव भाव देखें',
        'gt5':'एग्री कनेक्ट', 'g-h5':'एग्री कनेक्ट', 'g-p5':'खेती की समस्याओं पर चर्चा करें, सुझाव साझा करें और हज़ारों किसानों से सीखें.', 'g-b5':'समुदाय से जुड़ें',
        'gt6':'सहायता', 'g-h6':'संपर्क / सहायता', 'g-p6':'हमारी एग्रीटेक सपोर्ट टीम कॉल, चैट या मैसेज के ज़रिए कभी भी आपकी मदद के लिए तैयार है.', 'g-b6':'संपर्क करें',

        'wid-label':'लाइव टूल्स', 'wid-title':'लाइव डेटा के साथ स्मार्ट खेती',
        'wid-sub':'रीयल-टाइम मौसम, सरकारी योजनाएँ और फ़सल रोग स्कैनिंग — सब एक ही डैशबोर्ड में.',

        'scheme-title':'किसानों के लिए सरकारी योजनाएँ',
        'sch1-name':'पीएम-किसान', 'sch1-desc':'भूमिधारक किसानों के लिए ₹6,000/वर्ष प्रत्यक्ष आय सहायता, 3 किस्तों में.',
        'sch2-name':'पीएम फ़सल बीमा योजना', 'sch2-desc':'प्राकृतिक आपदाओं, कीट और रोगों के विरुद्ध कम प्रीमियम वाला फ़सल बीमा.',
        'sch3-name':'किसान क्रेडिट कार्ड (KCC)', 'sch3-desc':'फ़सल और खेती की ज़रूरतों के लिए ₹3 लाख तक का कम ब्याज़ ऋण.',
        'sch4-name':'मृदा स्वास्थ्य कार्ड', 'sch4-desc':'पोषक तत्व और खाद सुझावों के साथ मुफ़्त मिट्टी परीक्षण.',
        'sch5-name':'पीएम-कुसुम', 'sch5-desc':'सोलर सिंचाई पंप और ग्रिड से जुड़ी सौर ऊर्जा पर 60% तक सब्सिडी.',
        'sch6-name':'ई-नाम', 'sch6-desc':'भारत की मंडियों में अपनी उपज ऑनलाइन बेचें — कोई बिचौलिया नहीं.',

        'scan-title':'पत्ती रोग स्कैनर', 'scan-sub':'तुरंत AI निदान के लिए फ़सल की फोटो अपलोड करने हेतु यहाँ टैप करें',

        'test-label':'किसानों की कहानियाँ', 'test-title':'किसान क्या कह रहे हैं',
        'test-sub':'महाराष्ट्र के असली किसानों की प्रतिक्रिया, जो रोज़ AgriCart का उपयोग करते हैं.',
        't1-msg':'"AgriCart की वजह से मैं बिना किसी बिचौलिये के सीधे अच्छी क्वालिटी के महाबीज बीज खरीद पाया. डिलीवरी की लाइव ट्रैकिंग बहुत सटीक है."', 't1-auth':'— रमेश पाटील, नासिक',
        't2-msg':'"मैंने अपने बीमार कपास के पौधे की फोटो अपलोड की और कुछ ही सेकंड में AI ने पाउडरी मिल्ड्यू पहचान कर बता दिया कि क्या छिड़कना है!"', 't2-auth':'— हरिभाऊ भोईर, पालघर',
        't3-msg':'"कृषि बाज़ार के भाव ने मुझे घाटे में बिक्री से बचाया. लाइव मंडी भाव देखने के बाद मैं 3 दिन रुका और अपनी प्याज़ की फ़सल पर ₹8,000 ज़्यादा कमाए."', 't3-auth':'— सुरेश वाघ, पुणे जिला',

        'ft-tag':'तकनीक के ज़रिए भारतीय किसानों का सशक्तिकरण',
        'ft-copy':'© 2026 AgriCart. सर्वाधिकार सुरक्षित. | भारतीय किसानों के लिए ❤️ से बनाया गया'
    }
};

function applyHomeTranslations(lang) {
    const t = HomeT[lang] || HomeT.en;
    Object.keys(t).forEach(key => {
        if (key.startsWith('_')) return;
        const el = document.getElementById(key);
        if (el) el.innerHTML = t[key];
    });

    const heroInput = document.getElementById('hero-search-input');
    if (heroInput && t['_heroPlaceholder']) heroInput.placeholder = t['_heroPlaceholder'];

    const heroBtn = document.getElementById('hero-search-btn');
    if (heroBtn && t['_heroBtn']) heroBtn.innerHTML = '<i class="fa-solid fa-magnifying-glass"></i> ' + t['_heroBtn'];

    // Refresh the weather widget in the new language too (reliable path,
    // independent of which switchLanguage implementation is active).
    if (typeof window.AGRI_refreshWeatherForLang === 'function') {
        window.AGRI_refreshWeatherForLang(lang);
    }
}

window.pageLanguageCallback = function(lang) {
    applyHomeTranslations(lang);
};

// Apply immediately on load using the saved language (header.php already calls
// pageLanguageCallback on load too, but this guarantees it even if load order shifts)
document.addEventListener('DOMContentLoaded', function() {
    applyHomeTranslations(localStorage.getItem('agri_lang') || 'en');
});
</script>

</body>
</html>