<?php
// =====================================================
// AgriCart — Rental Hub (DB-connected: equipment + equipment_bookings)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

// ── Base path (same logic as header.php) — computed early so image URLs below are correct ──
$_doc_root = str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT']));
$_this_dir = str_replace('\\', '/', realpath(dirname(__DIR__)));
$base_path = rtrim(str_replace($_doc_root, '', $_this_dir), '/');

// ── Fetch equipment with owner/city/rating/review info ──
$equipmentRows = [];
$eqQuery = "
    SELECT e.id, e.name, e.name_mr, e.name_hi, e.type, e.image, e.rent_per_day, e.rent_per_hour, e.rent_per_acre, e.rent_type,
           e.hp, e.engine, e.gears, e.lift_capacity, e.brand, e.model, e.equipment_condition,
           e.operator_available, e.fuel_included, e.transport_available, e.security_deposit,
           e.owner_name, e.owner_phone, e.owner_verified, e.owner_district,
           c.name AS city_name,
           COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.item_type='equipment' AND r.item_id = e.id), 0) AS avg_rating,
           (SELECT COUNT(*) FROM equipment_bookings b WHERE b.equipment_id = e.id AND b.status = 'completed') AS trips_done
    FROM equipment e
    LEFT JOIN cities c ON c.id = e.city_id
    WHERE e.availability = 1 AND (e.approval_status IS NULL OR e.approval_status = 'approved')
    ORDER BY e.id
";
try {
    $eqResult = $conn->query($eqQuery);
    if ($eqResult === false) { throw new \Exception('column missing'); }
} catch (\Throwable $eq_e) {
    // Newer columns (name_hi, brand, rent_type, etc.) don't exist yet on this
    // DB — fall back to the original column set so the page still renders.
    $eqResult = $conn->query("
        SELECT e.id, e.name, e.name_mr, e.type, e.image, e.rent_per_day, e.rent_per_hour,
               e.hp, e.engine, e.gears, e.lift_capacity,
               e.owner_name, e.owner_phone, e.owner_verified,
               c.name AS city_name,
               COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.item_type='equipment' AND r.item_id = e.id), 0) AS avg_rating,
               (SELECT COUNT(*) FROM equipment_bookings b WHERE b.equipment_id = e.id AND b.status = 'completed') AS trips_done
        FROM equipment e
        LEFT JOIN cities c ON c.id = e.city_id
        WHERE e.availability = 1
        ORDER BY e.id
    ");
}
if ($eqResult) {
    while ($row = $eqResult->fetch_assoc()) {
        $equipmentRows[] = $row;
    }
}

$typeMeta = [
    'tractor'      => ['cat' => 'tractors',   'emoji' => '🚜'],
    'power_tiller' => ['cat' => 'tractors',   'emoji' => '🚜'],
    'rotavator'    => ['cat' => 'tractors',   'emoji' => '⚙️'],
    'cultivator'   => ['cat' => 'tractors',   'emoji' => '⚙️'],
    'harvester'    => ['cat' => 'harvesters', 'emoji' => '🌾'],
    'seed_drill'   => ['cat' => 'tractors',   'emoji' => '🌱'],
    'sprayer'      => ['cat' => 'tractors',   'emoji' => '💦'],
    'drone'        => ['cat' => 'drones',     'emoji' => '🛸'],
    'thresher'     => ['cat' => 'harvesters', 'emoji' => '🌾'],
    'pump'         => ['cat' => 'tractors',   'emoji' => '⚙️'], // legacy value, kept for old listings
    'other'        => ['cat' => 'tractors',   'emoji' => '⚙️'],
];

$machineryForJs = [];
foreach ($equipmentRows as $row) {
    $meta = $typeMeta[$row['type']] ?? $typeMeta['other'];

    $reviewTexts = [];
    $rvStmt = $conn->prepare("SELECT comment FROM reviews WHERE item_type='equipment' AND item_id=? AND comment IS NOT NULL AND comment <> '' ORDER BY created_at DESC LIMIT 3");
    $rvStmt->bind_param("i", $row['id']);
    $rvStmt->execute();
    $rvRes = $rvStmt->get_result();
    while ($rv = $rvRes->fetch_assoc()) { $reviewTexts[] = $rv['comment']; }
    if (empty($reviewTexts)) { $reviewTexts = ['अजून review नाही'];}

    $reviewCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM reviews WHERE item_type='equipment' AND item_id=?");
    $reviewCountStmt->bind_param("i", $row['id']);
    $reviewCountStmt->execute();
    $reviewCountRow = $reviewCountStmt->get_result()->fetch_assoc();
    $reviewCount = $reviewCountRow ? (int)$reviewCountRow['cnt'] : 0;

    $machineryForJs[] = [
        'id'          => (int)$row['id'],
        'nameMr'      => $row['name_mr'] ?: $row['name'],
        'nameEn'      => $row['name'],
        'nameHi'      => (!empty($row['name_hi']) ? $row['name_hi'] : ($row['name_mr'] ?: $row['name'])),
        'img'         => $base_path . '/' . ($row['image'] ?: 'assets/images/equipment.png'),
        'imgFallback' => $base_path . '/assets/images/equipment.png',
        'emoji'       => $meta['emoji'],
        'price'       => (float)($row['rent_per_day'] ?? $row['rent_per_hour'] ?? $row['rent_per_acre'] ?? 0),
        'priceHour'   => isset($row['rent_per_hour']) && $row['rent_per_hour'] !== null ? (float)$row['rent_per_hour'] : null,
        'rentType'    => $row['rent_type'] ?? 'day',
        'cat'         => $meta['cat'],
        'unitMr'      => 'दिवस', 'unitEn' => 'day', 'unitHi' => 'दिन',
        'badgeMr'     => null, 'badgeEn' => null, 'badgeHi' => null,
        'owner'       => $row['owner_name'] ?: 'AgriCart Partner',
        'phone'       => $row['owner_phone'] ?: '',
        'verified'    => (bool)$row['owner_verified'],
        'rating'      => round((float)$row['avg_rating'], 1),
        'trips'       => (int)$row['trips_done'],
        'village'     => $row['city_name'] ?: 'Maharashtra',
        'district'    => $row['owner_district'] ?? '',
        'distKm'      => round(2 + (((int)$row['id']) % 10) * 1.3, 1),
        'specs'       => ['hp' => $row['hp'] ?: '-', 'engine' => $row['engine'] ?: '-', 'gears' => $row['gears'] ?: '-', 'lift' => $row['lift_capacity'] ?: '-'],
        // New listing fields — default to safe values if the migration
        // hasn't been run yet, so this never breaks the rental page.
        'brand'       => $row['brand'] ?? '',
        'model'       => $row['model'] ?? '',
        'condition'   => $row['equipment_condition'] ?? 'good',
        'operatorAvailable'  => isset($row['operator_available']) ? (bool)$row['operator_available'] : false,
        'fuelIncluded'       => isset($row['fuel_included']) ? (bool)$row['fuel_included'] : false,
        'transportAvailable' => isset($row['transport_available']) ? (bool)$row['transport_available'] : false,
        'securityDeposit'    => isset($row['security_deposit']) ? (float)$row['security_deposit'] : 0,
        'reviewsMr'   => $reviewTexts, 'reviewsEn' => $reviewTexts, 'reviewsHi' => $reviewTexts,
        'reviewCount' => $reviewCount,
    ];
}

// ── Real booked date ranges — only ACCEPTED bookings (confirmed / on_the_way) block the
//    calendar. A 'pending' request is just a request: it hasn't been accepted by the owner
//    yet, so those dates must stay open/bookable for other users until it's confirmed. ──
$bookedDatesMap = [];
foreach ($equipmentRows as $row) { $bookedDatesMap[(int)$row['id']] = []; }
$bkResult = $conn->query("SELECT equipment_id, from_date, to_date FROM equipment_bookings WHERE status IN ('confirmed','on_the_way')");
if ($bkResult) {
    while ($bk = $bkResult->fetch_assoc()) {
        $start = new DateTime($bk['from_date']);
        $end   = new DateTime($bk['to_date']);
        $end->modify('+1 day');
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $d) {
            $bookedDatesMap[(int)$bk['equipment_id']][] = $d->format('Y-m-d');
        }
    }
}

$machineryJson   = json_encode($machineryForJs, JSON_UNESCAPED_UNICODE);
$bookedDatesJson = json_encode($bookedDatesMap, JSON_UNESCAPED_UNICODE);
$isLoggedIn      = isset($_SESSION['user_id']);

// Saved delivery/pickup address (shared with the marketplace checkout — see
// place_order.php / book_equipment.php, both write to the same users columns).
$savedAddressRental = null;
if ($isLoggedIn) {
    try {
        $sa = $conn->prepare("SELECT saved_address FROM users WHERE id = ? LIMIT 1");
        $sa->bind_param("i", $_SESSION['user_id']);
        $sa->execute();
        $row = $sa->get_result()->fetch_assoc();
        if ($row && !empty($row['saved_address'])) { $savedAddressRental = $row['saved_address']; }
    } catch (\Throwable $eSavedR) {}
}

// ── Real Rental Hub Stats (live counts from DB — no demo numbers) ──
$stat_machines = count($equipmentRows);

$stat_drivers = 0;
try {
    $res = @$conn->query("SELECT COUNT(DISTINCT owner_name) c FROM equipment WHERE owner_verified = 1 AND owner_name IS NOT NULL AND owner_name <> ''");
    if ($res && ($row = $res->fetch_assoc())) { $stat_drivers = (int)$row['c']; }
} catch (\Throwable $e) {
    // column missing — keep 0.
}

$stat_driver_rating  = 0;
$stat_driver_reviews = 0;
try {
    $res = @$conn->query("SELECT AVG(rating) a, COUNT(*) c FROM reviews WHERE item_type='equipment'");
    if ($res && ($row = $res->fetch_assoc())) {
        $stat_driver_rating  = $row['a'] !== null ? round((float)$row['a'], 1) : 0;
        $stat_driver_reviews = (int)$row['c'];
    }
} catch (\Throwable $e) {
    // reviews table missing — keep 0.
}

include __DIR__ . '/../includes/header.php';
?>

<div class="slider-wrap" style="height: 78vh; min-height: 500px;">
   <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/equipment.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="heroBadge">Rental Hub</div>
            <h1 id="heroTitle">Heavy Machinery Rental</h1>
            <p id="heroSub">Rent tractors, drone sprayers, and modern harvesting equipment by the hour or day with verified drivers.</p>
            <div class="hero-search">
                <input type="text" id="searchInput" placeholder="Search tractors, harvesters, drones..." oninput="doSearch()">
                <button onclick="doSearch()"><i class="fa-solid fa-magnifying-glass"></i> <span id="searchBtnText">Search</span></button>
            </div>
        </div>
    </div>

   <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/owner.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide2Badge">Verified Owners</div>
            <h1 id="slide2Title">Verified Equipment Owners</h1>
            <p id="slide2Sub">Rent farm machines from trusted and verified owners with safe booking and transparent pricing.</p>
        </div>
    </div>

   <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/selection.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide3Badge">Smart Selection</div>
            <h1 id="slide3Title">Select the Right Equipment</h1>
            <p id="slide3Sub">Choose tractors, harvesters, drones and other machines based on crop type, field size and farming needs.</p>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/booking.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide4Badge">Easy Booking</div>
            <h1 id="slide4Title">Book Farm Equipment Online</h1>
            <p id="slide4Sub">Quickly book tractors, harvesters, drones and other farm machines with simple steps and trusted service.</p>
        </div>
    </div>

   <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/tracking.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide5Badge">Live Tracking</div>
            <h1 id="slide5Title">Track Your Machinery Booking</h1>
            <p id="slide5Sub">Check real-time equipment location, delivery status and estimated arrival time directly from your dashboard.</p>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/dron.png'); cursor:pointer;" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="slide6Badge">Drone Spraying</div>
            <h1 id="slide6Title">Precision Drone Spraying for Farms</h1>
            <p id="slide6Sub">Spray crops faster with smart drone technology, accurate coverage, reduced chemical waste and easy online booking.</p>
        </div>
    </div>

    <div class="slider-dots" id="sliderDots"></div>
</div>


<style>
/* ── Slider image fix ── */
.slide {
    background-size: cover !important;
    background-position: center center !important;
    background-repeat: no-repeat !important;
    filter: none !important;
    opacity: 0 !important;
    transform: scale(1.06) !important;
    transition: opacity 1.2s cubic-bezier(0.65,0,0.35,1) !important, transform 6s ease-out !important;
    pointer-events: none !important;
}
.slide.active {
    opacity: 1 !important;
    transform: scale(1) !important;
    pointer-events: auto !important;
}

/* Overlay: lighter so image is vivid, not washed out */
.slide-overlay {
    background: linear-gradient(
        to bottom,
        rgba(0, 0, 0, 0.22) 0%,
        rgba(0, 0, 0, 0.45) 60%,
        rgba(0, 0, 0, 0.65) 100%
    ) !important;
}

/* Slide content — using the same centered layout as the homepage (index.php),
   no local override here so it inherits assets/css/style.css's
   .slide-content { top:50%; left:50%; transform:translate(-50%,-50%); text-align:center; } */
.slide-content h1,
.slide-content p,
.slide-content .slide-tag {
    filter: none !important;
    text-shadow: 0 2px 8px rgba(0,0,0,0.55) !important;
}

/* Slider wrap itself — no blur */
.slider-wrap {
    overflow: hidden !important;
    filter: none !important;
}
</style>

<script>
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   SLIDER FIX — Self-contained
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
(function(){
  var slides = document.querySelectorAll('.slide');
  var dotsWrap = document.getElementById('sliderDots');
  if (!slides.length || !dotsWrap) return;

  var current = 0;
  var total = slides.length;
  var timer = null;

  // Reset: remove any pre-existing active class
  slides.forEach(function(s){ s.classList.remove('active'); });
  slides[0].classList.add('active');

  // Build dots
  dotsWrap.innerHTML = '';
  for (var i = 0; i < total; i++) {
    var d = document.createElement('span');
    d.className = 'dot' + (i === 0 ? ' active' : '');
    d.setAttribute('data-idx', i);
    (function(idx){ d.addEventListener('click', function(){ goTo(idx); resetTimer(); }); })(i);
    dotsWrap.appendChild(d);
  }

  function goTo(n) {
    slides[current].classList.remove('active');
    dotsWrap.querySelectorAll('.dot')[current].classList.remove('active');
    current = (n + total) % total;
    slides[current].classList.add('active');
    dotsWrap.querySelectorAll('.dot')[current].classList.add('active');
  }

  function next() { goTo(current + 1); }

  function resetTimer() {
    clearInterval(timer);
    timer = setInterval(next, 4500);
  }

  // Touch swipe
  var touchStartX = 0;
  var wrap = document.querySelector('.slider-wrap');
  if (wrap) {
    wrap.addEventListener('touchstart', function(e){ touchStartX = e.changedTouches[0].clientX; }, {passive:true});
    wrap.addEventListener('touchend', function(e){
      var dx = e.changedTouches[0].clientX - touchStartX;
      if (Math.abs(dx) > 50) { goTo(dx < 0 ? current + 1 : current - 1); resetTimer(); }
    }, {passive:true});
    wrap.addEventListener('mouseenter', function(){ clearInterval(timer); });
    wrap.addEventListener('mouseleave', resetTimer);
  }

  // Keyboard
  document.addEventListener('keydown', function(e){
    if (e.key === 'ArrowRight') { goTo(current + 1); resetTimer(); }
    if (e.key === 'ArrowLeft')  { goTo(current - 1); resetTimer(); }
  });

  resetTimer();
})();
</script>

<div class="stats">
    <div class="stat-item"><h3><?php echo number_format($stat_machines); ?>+</h3><p id="st1">Modern Machines</p></div>
    <div class="stat-item"><h3><?php echo number_format($stat_drivers); ?>+</h3><p id="st2">Verified Drivers</p></div>
    <div class="stat-item"><h3 id="statDeliveryTime">Under 1 hr</h3><p id="st3">Quick Delivery</p></div>
    <div class="stat-item"><div class="mini-stars"><?php echo $stat_driver_reviews > 0 ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?></div><h3><?php echo $stat_driver_reviews > 0 ? $stat_driver_rating : 'New'; ?></h3><p id="st4">Driver Rating</p></div>
</div>

<div class="store-layout">
    <aside class="sidebar-col" style="display:flex;flex-direction:column;gap:18px;position:sticky;top:130px;align-self:start">
        <?php if ($isLoggedIn): ?>
        <div class="sidebar" id="myBookingsSection" style="position:static">
            <div class="sidebar-header" style="justify-content:space-between">
                <span style="display:flex;align-items:center;gap:8px"><i class="fa-solid fa-clipboard-list"></i> <span id="myBookingsTitle">माझी Bookings</span></span>
                <span id="myBookingsCount" style="background:rgba(255,255,255,.18);font-size:11px;font-weight:600;padding:3px 9px;border-radius:20px;"></span>
            </div>
            <div id="myBookingsList" style="color:#888;font-size:12.5px;padding:14px 16px;">Loading...</div>
        </div>
        <?php endif; ?>
        <div class="sidebar" style="position:static">
        <div class="sidebar-header"><i class="fa-solid fa-filter"></i> <span id="filterDashTitle">Filter Machinery</span></div>
        <div class="sidebar-section">
            <h4 id="catTitle">EQUIPMENT TYPE</h4>
            <div class="cat-item active" onclick="filterCat('all',this)"><span>🚜</span> <span id="catAll">All Machinery</span></div>
            <div class="cat-item" onclick="filterCat('tractors',this)"><span>🚜</span> <span id="catTractors">Tractors</span></div>
            <div class="cat-item" onclick="filterCat('drones',this)"><span>🛸</span> <span id="catDrones">Drone Sprayers</span></div>
            <div class="cat-item" onclick="filterCat('harvesters',this)"><span>🌾</span> <span id="catHarvesters">Harvesters</span></div>
        </div>
        <div class="sidebar-section" style="margin-top:10px">
            <button onclick="openNearbyModal()" style="width:100%;background:#e8f5e9;color:#1b4332;border:1.5px solid #a5d6a7;border-radius:9px;padding:10px;font-weight:700;font-size:13px;cursor:pointer;display:flex;align-items:center;gap:8px;justify-content:center">
                <i class="fa-solid fa-location-dot"></i> <span id="nearbyBtnText">Nearby Equipment</span>
            </button>
        </div>
        </div>
    </aside>

    <div class="products-area">
        <div class="offer-strip">
            <i class="fa-solid fa-clock"></i>
            <div>
                <strong>⚡ <span id="gridOfferHeading">Early Bird Booking!</span></strong><br>
                <span id="gridOfferSub">Book 3 days in advance and get flat 10% off on total rent!</span>
            </div>
            <div class="offer-code">RENT10</div>
        </div>

        <div class="filter-bar">
            <div class="filter-bar-left"><strong id="resultCount">0</strong> <span id="machFoundText">machines available</span></div>
            <div style="display:flex;align-items:center;gap:10px">
                <span style="font-size:12.5px;color:#666" id="sortByText">Sort by Price:</span>
                <select class="sort-select" onchange="doSort(this.value)">
                    <option value="default">Default</option>
                    <option value="price-low">Rent: Low to High</option>
                    <option value="price-high">Rent: High to Low</option>
                </select>
                <button class="owner-dash-launcher" onclick="openOwnerDashboard()"><i class="fa-solid fa-gauge-high"></i> <span id="ownerDashBtnText">Owner Dashboard</span></button>
            </div>
        </div>

        <div class="products-grid" id="machineryGrid"></div>
    </div>
</div>

<!-- Compare floating bar -->
<div id="compareBar" style="display:none;position:fixed;bottom:0;left:0;right:0;background:#1b4332;color:#fff;z-index:8888;padding:12px 20px;align-items:center;justify-content:space-between;gap:10px;flex-wrap:wrap;">
    <span><i class="fa-solid fa-code-compare"></i> <span id="compareBarCount">2 selected</span></span>
    <div style="display:flex;gap:10px">
        <button onclick="openCompareModal()" style="background:#FFC107;color:#000;border:none;padding:8px 18px;border-radius:8px;font-weight:700;cursor:pointer"><i class="fa-solid fa-scale-balanced"></i> Compare Now</button>
        <button onclick="clearCompare()" style="background:#fff3;color:#fff;border:1px solid #fff5;padding:8px 14px;border-radius:8px;cursor:pointer">Clear</button>
    </div>
</div>

<!-- Razorpay SDK -->
<script src="https://checkout.razorpay.com/v1/checkout.js"></script>

<script>
const RentalT = {
    en: {
        heroBadge: "Rental Hub",
        heroTitle: "Heavy Machinery Rental",
        heroSub: "Rent tractors, drone sprayers, and modern harvesting equipment by the hour or day with verified drivers.",
        searchPlaceholder: "Search tractors, harvesters, drones...",
        searchBtn: "Search",
        filterDash: "Filter Machinery", catTitle: "EQUIPMENT TYPE",
        catAll: "All Machinery", catTractors: "Tractors", catDrones: "Drone Sprayers", catHarvesters: "Harvesters",
        gridOfferHead: "Early Bird Booking!", gridOfferSub: "Book 3 days in advance and get flat 10% off on total rent!",
        machFound: "machines available", sortBy: "Sort by Price:",
        st1: "Modern Machines", st2: "Verified Drivers", st3: "Quick Delivery", st4: "Driver Rating", deliveryTime: "Under 1 hr",
        driverInc: "Driver Included", emptyGrid: "No Machinery Found.", callConfirm: "Call 1800-419-8888 for Booking Confirmation",
        slide2Badge: "Verified Owners", slide2Title: "Verified Equipment Owners", slide2Sub: "Rent farm machines from trusted and verified owners with safe booking and transparent pricing.",
        slide3Badge: "Smart Selection", slide3Title: "Select the Right Equipment", slide3Sub: "Choose tractors, harvesters, drones and other machines based on crop type, field size and farming needs.",
        slide4Badge: "Easy Booking", slide4Title: "Book Farm Equipment Online", slide4Sub: "Quickly book tractors, harvesters, drones and other farm machines with simple steps and trusted service.",
        slide5Badge: "Live Tracking", slide5Title: "Track Your Machinery Booking", slide5Sub: "Check real-time equipment location, delivery status and estimated arrival time directly from your dashboard.",
        slide6Badge: "Drone Spraying", slide6Title: "Precision Drone Spraying for Farms", slide6Sub: "Spray crops faster with smart drone technology, accurate coverage, reduced chemical waste and easy online booking.",
        verifiedOwner: "Verified Owner", pendingOwner: "Verification Pending", tripsDone: "trips",
        trackBtn: "Track", ownerDashBtn: "Owner Dashboard",
        compareBtn: "Compare", compareClear: "Clear", compareTitle: "Equipment Comparison",
        compareSpec: "Specifications", compareHP: "Power", compareEngine: "Engine", compareGears: "Gears", compareLift: "Lift/Tank",
        comparePrice: "Price/Unit", compareRating: "Rating", compareOwner: "Owner", compareClose: "Close",
        detailsBtn: "View Details", aboutTitle: "About This Equipment", reviewsLbl: "reviews", ownedByLbl: "Owned & offered for rent by",
        whatsappBtn: "WhatsApp",
        reviewTitle: "Customer Reviews", writeReview: "Write a Review", reviewPh: "Your experience...", submitReview: "Submit",
        nearbyTitle: "Nearby Equipment", nearbyKm: "km away", nearbyDetect: "Detect My Location", nearbyDetecting: "Detecting...",
        bookModalTitle: "Smart Booking", chooseDates: "Choose Your Dates", selectStart: "Select start date", selectEnd: "Select end date",
        hoursLabel: "Hours / Units", locationLabel: "Delivery Location", locationPh: "Enter village / town / pincode",
        priceCalcTitle: "Price Calculator", baseRate: "Base Rate", duration: "Duration", subtotal: "Subtotal",
        serviceFee: "Service Fee", totalAmount: "Total Amount", confirmBookBtn: "Pay & Confirm (Razorpay)",
        bookedDay: "Booked", availDay: "Available", legendAvail: "Available", legendBooked: "Already Booked", legendSelected: "Selected",
        bookingSuccess: "Request sent! Waiting for owner to accept your booking.", selectDatesAlert: "Please select start and end dates.", hoursHint: "only applies for a single-day booking",
        trackModalTitle: "Live Tracking", trackStep1: "Request Sent", trackStep2: "Booking Confirmed", trackStep3: "On the Way", trackStep4: "Delivered to Farm",
        fullHistoryLbl: "View full activity (Orders + Rentals + Advisory) →",
        trackEta: "Estimated Arrival", trackDriver: "Driver", trackVehicle: "Vehicle",
        ownerDashTitle: "Owner Dashboard (Demo)", odEarnings: "Total Earnings", odBookings: "Active Bookings", odEquip: "Listed Equipment", odRating: "Avg Rating",
        odMyEquip: "My Equipment", odStatus: "Status", odBookedOn: "Next Booking", odEarn: "Earnings",
        couponPh: "Coupon code", applyCouponBtn: "Apply", couponDiscountLbl: "Coupon Discount", couponApplied: "applied", couponOff: "off", couponInvalid: "Invalid coupon code.", payNowBtn: "Pay Now"
    },
    mr: {
        heroBadge: "अवजारे केंद्र",
        heroTitle: "आधुनिक कृषी अवजारे भाडे",
        heroSub: "ट्रॅक्टर, ड्रोन फवारणी आणि कापणी यंत्रे तासाने किंवा दिवसाने अनुभवी चालकांसह भाड्याने घ्या.",
        searchPlaceholder: "ट्रॅक्टर, कापणी यंत्रे शोध घ्या...",
        searchBtn: "शोधा",
        filterDash: "अवजारे फिल्टर", catTitle: "अवजारांचे प्रकार",
        catAll: "सर्व अवजारे", catTractors: "ट्रॅक्टर", catDrones: "ड्रोन फवारणी", catHarvesters: "कापणी यंत्रे (Harvesters)",
        gridOfferHead: "अगोदर बुकिंग ऑफर!", gridOfferSub: "३ दिवस आधी बुकिंग करा आणि एकूण भाड्यावर थेट १०% सूट मिळवा!",
        machFound: "मशीन्स उपलब्ध आहेत", sortBy: "किंमतीनुसार क्रम:",
        st1: "आधुनिक अवजारे", st2: "प्रमाणित चालक", st3: "जलद वितरण", st4: "चालक रेटिंग", deliveryTime: "१ तासाच्या आत",
        driverInc: "चालकासह समाविष्ट", emptyGrid: "कोणतीही अवजारे सापडली नाहीत.", callConfirm: "बुकिंगसाठी संपर्क करा: 1800-419-8888",
        slide2Badge: "प्रमाणित मालक", slide2Title: "प्रमाणित अवजार मालक", slide2Sub: "विश्वासू आणि प्रमाणित मालकांकडून शेत अवजारे सुरक्षित बुकिंग व पारदर्शक किंमतीसह भाड्याने घ्या.",
        slide3Badge: "स्मार्ट निवड", slide3Title: "योग्य अवजार निवडा", slide3Sub: "पीक प्रकार, शेताचा आकार आणि शेतीच्या गरजांनुसार ट्रॅक्टर, कापणी यंत्रे, ड्रोन निवडा.",
        slide4Badge: "सोपी बुकिंग", slide4Title: "ऑनलाइन शेत अवजारे बुक करा", slide4Sub: "सोप्या चरणांमध्ये ट्रॅक्टर, कापणी यंत्रे, ड्रोन आणि इतर शेत अवजारे झटपट बुक करा.",
        slide5Badge: "लाइव्ह ट्रॅकिंग", slide5Title: "तुमच्या अवजाराची बुकिंग ट्रॅक करा", slide5Sub: "डॅशबोर्डवरून रिअल-टाइम अवजाराचे स्थान, डिलिव्हरी स्थिती आणि अंदाजे आगमन वेळ तपासा.",
        slide6Badge: "ड्रोन फवारणी", slide6Title: "शेतासाठी अचूक ड्रोन फवारणी", slide6Sub: "स्मार्ट ड्रोन तंत्रज्ञानाने पीक जलद फवारा, अचूक कव्हरेज, रासायनिक कचरा कमी आणि सोपी ऑनलाइन बुकिंग.",
        verifiedOwner: "सत्यापित मालक", pendingOwner: "सत्यापन प्रलंबित", tripsDone: "ट्रिप्स",
        trackBtn: "ट्रॅक करा", ownerDashBtn: "मालक डॅशबोर्ड",
        compareBtn: "तुलना करा", compareClear: "साफ करा", compareTitle: "अवजारांची तुलना",
        compareSpec: "वैशिष्ट्ये", compareHP: "शक्ती", compareEngine: "इंजिन", compareGears: "गिअर्स", compareLift: "उचल/टाकी",
        comparePrice: "दर/युनिट", compareRating: "रेटिंग", compareOwner: "मालक", compareClose: "बंद करा",
        detailsBtn: "तपशील पहा", aboutTitle: "या अवजाराबद्दल", reviewsLbl: "अभिप्राय", ownedByLbl: "मालक व भाड्याने देणारे",
        whatsappBtn: "WhatsApp",
        reviewTitle: "ग्राहक अभिप्राय", writeReview: "अभिप्राय द्या", reviewPh: "तुमचा अनुभव...", submitReview: "पाठवा",
        nearbyTitle: "जवळची अवजारे", nearbyKm: "किमी दूर", nearbyDetect: "माझे स्थान शोधा", nearbyDetecting: "शोधत आहे...",
        bookModalTitle: "स्मार्ट बुकिंग", chooseDates: "तुमच्या तारखा निवडा", selectStart: "सुरुवातीची तारीख निवडा", selectEnd: "शेवटची तारीख निवडा",
        hoursLabel: "तास / युनिट्स", locationLabel: "डिलिव्हरी स्थान", locationPh: "गाव / तालुका / पिनकोड टाका",
        priceCalcTitle: "किंमत कॅल्क्युलेटर", baseRate: "बेस दर", duration: "कालावधी", subtotal: "उपएकूण",
        serviceFee: "सेवा शुल्क", totalAmount: "एकूण रक्कम", confirmBookBtn: "Razorpay ने पेमेंट करा",
        bookedDay: "बुक केलेले", availDay: "उपलब्ध", legendAvail: "उपलब्ध", legendBooked: "आधीच बुक केलेले", legendSelected: "निवडलेले",
        bookingSuccess: "Request पाठवली! Owner ने accept केल्यावर booking confirm होईल.", selectDatesAlert: "कृपया सुरुवातीची आणि शेवटची तारीख निवडा.", hoursHint: "फक्त एका दिवसाच्या booking साठी लागू",
        trackModalTitle: "लाइव्ह ट्रॅकिंग", trackStep1: "Request पाठवली", trackStep2: "बुकिंग निश्चित (Owner Accepted)", trackStep3: "वाटेत आहे", trackStep4: "शेतावर पोहोचले",
        fullHistoryLbl: "संपूर्ण अ‍ॅक्टिव्हिटी पहा (ऑर्डर्स + भाडे + सल्ला) →",
        trackEta: "अंदाजे आगमन वेळ", trackDriver: "चालक", trackVehicle: "वाहन",
        ownerDashTitle: "मालक डॅशबोर्ड (डेमो)", odEarnings: "एकूण कमाई", odBookings: "सक्रिय बुकिंग्स", odEquip: "सूचीबद्ध अवजारे", odRating: "सरासरी रेटिंग",
        odMyEquip: "माझी अवजारे", odStatus: "स्थिती", odBookedOn: "पुढील बुकिंग", odEarn: "कमाई",
        couponPh: "कूपन कोड", applyCouponBtn: "लागू करा", couponDiscountLbl: "कूपन सूट", couponApplied: "लागू झाला", couponOff: "सूट", couponInvalid: "चुकीचा कूपन कोड.", payNowBtn: "पेमेंट करा"
    },
    hi: {
        heroBadge: "किराया केंद्र",
        heroTitle: "भारी कृषि उपकरण किराया",
        heroSub: "ट्रैक्टर, ड्रोन स्प्रेयर और आधुनिक कटाई उपकरण घंटे या दिन के हिसाब से अनुभवी चालकों के साथ किराए पर लें.",
        searchPlaceholder: "ट्रैक्टर, हार्वेस्टर, ड्रोन खोजें...",
        searchBtn: "खोजें",
        filterDash: "उपकरण फ़िल्टर", catTitle: "उपकरण प्रकार",
        catAll: "सभी उपकरण", catTractors: "ट्रैक्टर", catDrones: "ड्रोन स्प्रेयर", catHarvesters: "हार्वेस्टर",
        gridOfferHead: "अर्ली बर्ड बुकिंग!", gridOfferSub: "3 दिन पहले बुकिंग करें और कुल किराए पर 10% छूट पाएं!",
        machFound: "मशीनें उपलब्ध", sortBy: "कीमत के अनुसार:",
        st1: "आधुनिक मशीनें", st2: "प्रमाणित चालक", st3: "त्वरित डिलीवरी", st4: "चालक रेटिंग", deliveryTime: "1 घंटे से कम",
        driverInc: "चालक सहित", emptyGrid: "कोई उपकरण नहीं मिला.", callConfirm: "बुकिंग के लिए संपर्क करें: 1800-419-8888",
        slide2Badge: "सत्यापित मालिक", slide2Title: "सत्यापित उपकरण मालिक", slide2Sub: "विश्वसनीय और सत्यापित मालिकों से सुरक्षित बुकिंग और पारदर्शी मूल्य के साथ खेत की मशीनें किराए पर लें.",
        slide3Badge: "स्मार्ट चयन", slide3Title: "सही उपकरण चुनें", slide3Sub: "फसल प्रकार, खेत के आकार और खेती की जरूरतों के अनुसार ट्रैक्टर, हार्वेस्टर, ड्रोन चुनें.",
        slide4Badge: "आसान बुकिंग", slide4Title: "ऑनलाइन खेत उपकरण बुक करें", slide4Sub: "सरल चरणों में ट्रैक्टर, हार्वेस्टर, ड्रोन और अन्य खेत की मशीनें जल्दी से बुक करें.",
        slide5Badge: "लाइव ट्रैकिंग", slide5Title: "अपनी मशीनरी बुकिंग ट्रैक करें", slide5Sub: "डैशबोर्ड से रियल-टाइम उपकरण स्थान, डिलीवरी स्थिति और अनुमानित आगमन समय देखें.",
        slide6Badge: "ड्रोन स्प्रेइंग", slide6Title: "खेतों के लिए सटीक ड्रोन स्प्रेइंग", slide6Sub: "स्मार्ट ड्रोन तकनीक से फसल तेजी से स्प्रे करें, सटीक कवरेज, कम रसायन बर्बादी और आसान ऑनलाइन बुकिंग."
        ,verifiedOwner: "सत्यापित मालिक", pendingOwner: "सत्यापन लंबित", tripsDone: "ट्रिप्स",
        trackBtn: "ट्रैक करें", ownerDashBtn: "मालिक डैशबोर्ड",
        compareBtn: "तुलना करें", compareClear: "साफ करें", compareTitle: "उपकरण तुलना",
        compareSpec: "विशेषताएं", compareHP: "शक्ति", compareEngine: "इंजन", compareGears: "गियर", compareLift: "उठान/टंकी",
        comparePrice: "दर/यूनिट", compareRating: "रेटिंग", compareOwner: "मालिक", compareClose: "बंद करें",
        detailsBtn: "विवरण देखें", aboutTitle: "इस उपकरण के बारे में", reviewsLbl: "समीक्षाएं", ownedByLbl: "मालिक व किराए पर देने वाले",
        whatsappBtn: "WhatsApp",
        reviewTitle: "ग्राहक समीक्षा", writeReview: "समीक्षा लिखें", reviewPh: "आपका अनुभव...", submitReview: "जमा करें",
        nearbyTitle: "पास के उपकरण", nearbyKm: "किमी दूर", nearbyDetect: "मेरा स्थान पहचानें", nearbyDetecting: "पहचान रहे हैं...",
        bookModalTitle: "स्मार्ट बुकिंग", chooseDates: "अपनी तारीखें चुनें", selectStart: "शुरुआती तारीख चुनें", selectEnd: "अंतिम तारीख चुनें",
        hoursLabel: "घंटे / यूनिट", locationLabel: "डिलीवरी स्थान", locationPh: "गाँव / कस्बा / पिनकोड डालें",
        priceCalcTitle: "मूल्य कैलकुलेटर", baseRate: "बेस रेट", duration: "अवधि", subtotal: "उप-योग",
        serviceFee: "सेवा शुल्क", totalAmount: "कुल राशि", confirmBookBtn: "Razorpay से भुगतान करें",
        bookedDay: "बुक्ड", availDay: "उपलब्ध", legendAvail: "उपलब्ध", legendBooked: "पहले से बुक्ड", legendSelected: "चयनित",
        bookingSuccess: "Request भेजी गई! Owner के accept करने पर बुकिंग confirm होगी.", selectDatesAlert: "कृपया शुरुआती और अंतिम तारीख चुनें.", hoursHint: "केवल एक दिन की बुकिंग पर लागू",
        trackModalTitle: "लाइव ट्रैकिंग", trackStep1: "Request भेजी गई", trackStep2: "बुकिंग कन्फर्म (Owner Accepted)", trackStep3: "रास्ते में है", trackStep4: "खेत पर पहुंचा",
        fullHistoryLbl: "पूरी एक्टिविटी देखें (ऑर्डर + किराया + सलाह) →",
        trackEta: "अनुमानित आगमन", trackDriver: "चालक", trackVehicle: "वाहन",
        ownerDashTitle: "मालिक डैशबोर्ड (डेमो)", odEarnings: "कुल कमाई", odBookings: "सक्रिय बुकिंग", odEquip: "सूचीबद्ध उपकरण", odRating: "औसत रेटिंग",
        odMyEquip: "मेरे उपकरण", odStatus: "स्थिति", odBookedOn: "अगली बुकिंग", odEarn: "कमाई",
        couponPh: "कूपन कोड", applyCouponBtn: "लागू करें", couponDiscountLbl: "कूपन छूट", couponApplied: "लागू हुआ", couponOff: "छूट", couponInvalid: "गलत कूपन कोड.", payNowBtn: "भुगतान करें"
    }
};

// ── Real data from the database (equipment table), injected by PHP ──
const MACHINERY = <?php echo $machineryJson; ?>;
const BOOKED_DATES = <?php echo $bookedDatesJson; ?>;
const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const SAVED_ADDRESS = <?php echo $savedAddressRental ? json_encode($savedAddressRental) : 'null'; ?>;

let currentCat = 'all', currentSort = 'default', searchTerm = '';

function pageLanguageCallback(currentLang) {
    const rt = RentalT[currentLang];
    window.lang = currentLang;

    document.getElementById('heroBadge').textContent = rt.heroBadge;
    document.getElementById('heroTitle').textContent = rt.heroTitle;
    document.getElementById('heroSub').textContent = rt.heroSub;
    document.getElementById('searchInput').placeholder = rt.searchPlaceholder;
    document.getElementById('searchBtnText').textContent = rt.searchBtn;
    
    document.getElementById('filterDashTitle').textContent = rt.filterDash;
    document.getElementById('catTitle').textContent = rt.catTitle;
    document.getElementById('catAll').textContent = rt.catAll;
    document.getElementById('catTractors').textContent = rt.catTractors;
    document.getElementById('catDrones').textContent = rt.catDrones;
    document.getElementById('catHarvesters').textContent = rt.catHarvesters;

    // ✅ FIXED: Using unique Grid IDs to prevent central slider crashes
    document.getElementById('gridOfferHeading').textContent = rt.gridOfferHead;
    document.getElementById('gridOfferSub').textContent = rt.gridOfferSub;
    document.getElementById('machFoundText').textContent = rt.machFound;
    document.getElementById('sortByText').textContent = rt.sortBy;

    const mbTitleEl = document.getElementById('myBookingsTitle');
    if (mbTitleEl) mbTitleEl.textContent = currentLang === 'mr' ? 'माझी Bookings' : (currentLang === 'hi' ? 'मेरी Bookings' : 'My Bookings');
    if (typeof renderMyBookingsCards === 'function' && typeof MY_BOOKINGS_CACHE !== 'undefined') renderMyBookingsCards();

    document.getElementById('st1').textContent = rt.st1;
    document.getElementById('st2').textContent = rt.st2;
    document.getElementById('st3').textContent = rt.st3;
    document.getElementById('st4').textContent = rt.st4;
    document.getElementById('statDeliveryTime').textContent = rt.deliveryTime;

    // Slide 2-5 translations
    document.getElementById('slide2Badge').textContent = rt.slide2Badge;
    document.getElementById('slide2Title').textContent = rt.slide2Title;
    document.getElementById('slide2Sub').textContent = rt.slide2Sub;
    document.getElementById('slide3Badge').textContent = rt.slide3Badge;
    document.getElementById('slide3Title').textContent = rt.slide3Title;
    document.getElementById('slide3Sub').textContent = rt.slide3Sub;
    document.getElementById('slide4Badge').textContent = rt.slide4Badge;
    document.getElementById('slide4Title').textContent = rt.slide4Title;
    document.getElementById('slide4Sub').textContent = rt.slide4Sub;
    document.getElementById('slide5Badge').textContent = rt.slide5Badge;
    document.getElementById('slide5Title').textContent = rt.slide5Title;
    document.getElementById('slide5Sub').textContent = rt.slide5Sub;
    document.getElementById('slide6Badge').textContent = rt.slide6Badge;
    document.getElementById('slide6Title').textContent = rt.slide6Title;
    document.getElementById('slide6Sub').textContent = rt.slide6Sub;

    document.getElementById('ownerDashBtnText').textContent = rt.ownerDashBtn;
    const nb = document.getElementById('nearbyBtnText');
    if (nb) nb.textContent = rt.nearbyTitle;

    renderMachinery();
}

function filterCat(cat, element) {
    currentCat = cat;
    document.querySelectorAll('.cat-item').forEach(el => el.classList.remove('active'));
    element.classList.add('active');
    renderMachinery();
}

function doSearch() {
    searchTerm = document.getElementById('searchInput').value.trim();
    renderMachinery();
}

function doSort(val) {
    currentSort = val;
    renderMachinery();
}

function getFilteredAndSorted() {
    let list = MACHINERY.filter(m => {
        const catOk = currentCat === 'all' || m.cat === currentCat;
        const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
        const searchOk = !searchTerm || name.toLowerCase().includes(searchTerm.toLowerCase());
        return catOk && searchOk;
    });

    if (currentSort === 'price-low') list.sort((a,b) => a.price - b.price);
    else if (currentSort === 'price-high') list.sort((a,b) => b.price - a.price);
    return list;
}

function renderMachinery() {
    const list = getFilteredAndSorted();
    document.getElementById('resultCount').textContent = list.length;
    const grid = document.getElementById('machineryGrid');
    const rt = RentalT[window.lang || 'en'];
    
    if(list.length === 0) {
        grid.innerHTML = `<div style="grid-column:1/-1; text-align:center; padding:2rem; color:#666;">${rt.emptyGrid}</div>`;
        return;
    }

    grid.innerHTML = list.map(m => {
        const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
        const unit = (window.lang === 'mr') ? m.unitMr : (window.lang === 'hi') ? m.unitHi : m.unitEn;
        const badge = (window.lang === 'mr') ? m.badgeMr : (window.lang === 'hi') ? m.badgeHi : m.badgeEn;
        const btnText = (window.lang === 'mr') ? 'आता बुक करा' : (window.lang === 'hi') ? 'अभी बुक करें' : 'Book Now';
        
        const ownerBadgeHtml = m.verified
            ? `<span class="owner-verified-badge"><i class="fa-solid fa-shield-halved"></i> ${rt.verifiedOwner}</span>`
            : `<span class="owner-unverified-badge"><i class="fa-solid fa-clock"></i> ${rt.pendingOwner}</span>`;

        return `<div class="product-card" id="card-${m.id}">
            ${badge ? `<div class="product-badge" style="background:#2E7D32">${badge}</div>` : ''}
            <div class="compare-checkbox-wrap" title="${rt.compareBtn}">
                <input type="checkbox" class="compare-chk" id="cmp-${m.id}" onchange="toggleCompare(${m.id}, this.checked)">
                <label for="cmp-${m.id}"><i class="fa-solid fa-code-compare"></i> ${rt.compareBtn}</label>
            </div>
            <div class="product-img-real" style="cursor:pointer" onclick="openDetailsModal(${m.id})">
                <img src="${m.img}" alt="${name}"
                     onerror="this.onerror=null;this.src='${m.imgFallback}'"
                     loading="lazy">
            </div>
            <div class="product-body">
                <div class="product-name" style="cursor:pointer" onclick="openDetailsModal(${m.id})">${name}</div>
                ${(m.brand || m.model) ? `<div class="product-unit" style="color:#666">${[m.brand, m.model].filter(Boolean).join(' · ')}${m.condition ? ' · ' + m.condition.charAt(0).toUpperCase() + m.condition.slice(1) : ''}</div>` : ''}
                <div class="rm-view-details-link" onclick="openDetailsModal(${m.id})"><i class="fa-solid fa-circle-info"></i> ${rt.detailsBtn}</div>
                <div class="owner-row">
                    <span class="owner-name"><i class="fa-solid fa-user"></i> ${m.owner}</span>
                    ${ownerBadgeHtml}
                </div>
                <div class="owner-rating-row">
                    <span><i class="fa-solid fa-star" style="color:#FFC107"></i> ${m.rating}</span>
                    <span style="color:#999">• ${m.trips} ${rt.tripsDone}</span>
                    <span class="nearby-badge"><i class="fa-solid fa-location-dot"></i> ${m.distKm} ${rt.nearbyKm}</span>
                    ${m.operatorAvailable ? `<span class="nearby-badge"><i class="fa-solid fa-user-gear"></i> Operator</span>` : ''}
                </div>
                <div class="product-unit" style="color:#e65100; font-weight:600;"><i class="fa-solid fa-circle-check" style="color:#2E7D32"></i> ${rt.driverInc}</div>
                <div class="price-row" style="margin-top:8px;">
                    <span class="price-now">₹${m.price}</span>
                    <span style="font-size:12px; color:#666; margin-left:2px;">/ ${unit}</span>
                </div>
                <div class="review-stars-mini">${renderStarsMini(m.rating)} <span style="font-size:11px;color:#888">(${m.reviewCount})</span></div>
                <div class="card-btn-row">
                    <button class="add-btn" onclick="openBookingModal(${m.id})">
                        <i class="fa-solid fa-calendar-check"></i> ${btnText}
                    </button>
                    <a class="whatsapp-btn" href="https://wa.me/91${m.phone}?text=${encodeURIComponent('Hi, I want to book ' + name + ' from AgriRental. Please confirm availability.')}" target="_blank">
                        <i class="fa-brands fa-whatsapp"></i> ${rt.whatsappBtn}
                    </a>
                </div>
                <div class="card-btn-row" style="margin-top:6px;">
                    <button class="track-btn" onclick="openTrackingModal(${m.id})">
                        <i class="fa-solid fa-location-crosshairs"></i> ${rt.trackBtn}
                    </button>
                    <button class="review-btn" onclick="openReviewModal(${m.id})">
                        <i class="fa-solid fa-star"></i> ${rt.writeReview}
                    </button>
                </div>
            </div>
        </div>`;
    }).join('');
}

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    window.lang = savedLang;
    pageLanguageCallback(savedLang);
});

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   MY BOOKINGS — shows all of the logged-in user's equipment rental
   bookings (any status) right on this page.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   MY BOOKINGS — shows all of the logged-in user's equipment rental
   bookings (any status) right on this page, as clickable info cards.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
const MY_BOOKINGS_I18N = {
    en: {
        empty: "You haven't booked any equipment yet.", loadError: 'Could not load your bookings.',
        bookingsWord: 'bookings', bookingWord: 'booking', viewDetails: 'Tap for full details',
        viewAllHint: 'Tap to see all bookings', viewAllBtn: 'View all {n} bookings', allBookingsTitle: 'My Bookings',
        status: { pending: 'Pending — waiting for owner', confirmed: 'Confirmed', on_the_way: 'On the Way', completed: 'Completed', cancelled: 'Cancelled' },
        payment: { paid: 'Paid ✓', failed: 'Failed', pending: 'Payment Pending', cod: 'Cash on Delivery' },
        days: 'days', day: 'day', hr: 'hr',
        detailLabels: { status: 'Status', payment: 'Payment', dates: 'Dates', duration: 'Duration', amount: 'Amount', owner: 'Owner', contactName: 'Contact Name', contactMobile: 'Contact Mobile', address: 'Delivery Address', machineNo: 'Machine No.', serialNo: 'Serial No.' },
        track: 'Track', payNow: 'Pay Now',
    },
    mr: {
        empty: 'अजून कोणतंही equipment booking केलेलं नाही.', loadError: 'Bookings load करताना अडचण आली.',
        bookingsWord: 'बुकिंग्स', bookingWord: 'बुकिंग', viewDetails: 'सर्व माहितीसाठी क्लिक करा',
        viewAllHint: 'सर्व बुकिंग्स पाहण्यासाठी क्लिक करा', viewAllBtn: 'सर्व {n} बुकिंग्स पहा', allBookingsTitle: 'माझी सर्व Bookings',
        status: { pending: 'प्रलंबित — owner ची वाट पाहतोय', confirmed: 'निश्चित', on_the_way: 'वाटेत', completed: 'पूर्ण झाले', cancelled: 'रद्द' },
        payment: { paid: 'पेड ✓', failed: 'अयशस्वी', pending: 'पेमेंट प्रलंबित', cod: 'डिलिव्हरीच्या वेळी रोख' },
        days: 'दिवस', day: 'दिवस', hr: 'तास',
        detailLabels: { status: 'स्थिती', payment: 'पेमेंट', dates: 'तारखा', duration: 'कालावधी', amount: 'रक्कम', owner: 'मालक', contactName: 'संपर्क नाव', contactMobile: 'संपर्क मोबाइल', address: 'डिलिव्हरी पत्ता', machineNo: 'मशीन क्र.', serialNo: 'सिरीयल क्र.' },
        track: 'ट्रॅक करा', payNow: 'पेमेंट करा',
    },
    hi: {
        empty: 'अभी तक कोई उपकरण बुक नहीं किया.', loadError: 'Bookings लोड करने में समस्या आई.',
        bookingsWord: 'बुकिंग्स', bookingWord: 'बुकिंग', viewDetails: 'पूरी जानकारी के लिए टैप करें',
        viewAllHint: 'सभी बुकिंग्स देखने के लिए टैप करें', viewAllBtn: 'सभी {n} बुकिंग्स देखें', allBookingsTitle: 'मेरी सभी Bookings',
        status: { pending: 'लंबित — owner का इंतज़ार', confirmed: 'पुष्टि हुई', on_the_way: 'रास्ते में', completed: 'पूर्ण', cancelled: 'रद्द' },
        payment: { paid: 'भुगतान हुआ ✓', failed: 'असफल', pending: 'भुगतान लंबित', cod: 'डिलीवरी पर नकद' },
        days: 'दिन', day: 'दिन', hr: 'घंटे',
        detailLabels: { status: 'स्थिति', payment: 'भुगतान', dates: 'तारीखें', duration: 'अवधि', amount: 'राशि', owner: 'मालिक', contactName: 'संपर्क नाम', contactMobile: 'संपर्क मोबाइल', address: 'डिलीवरी पता', machineNo: 'मशीन नं.', serialNo: 'सीरियल नं.' },
        track: 'ट्रैक करें', payNow: 'भुगतान करें',
    },
};
function mbT(){ return MY_BOOKINGS_I18N[window.lang || 'en'] || MY_BOOKINGS_I18N.en; }

function myBookingStatusBadge(status){
    const colors = {
        pending:    { color: '#e08a00', bg: '#fff6e5' },
        confirmed:  { color: '#2e7d32', bg: '#e8f5e9' },
        on_the_way: { color: '#1565c0', bg: '#e3f0fc' },
        completed:  { color: '#555',    bg: '#f0f0f0' },
        cancelled:  { color: '#d93025', bg: '#fdecea' },
    };
    const c = colors[status] || colors.pending;
    const label = mbT().status[status] || mbT().status.pending;
    return `<span style="background:${c.bg};color:${c.color};font-weight:600;font-size:11.5px;padding:3px 9px;border-radius:20px;white-space:nowrap">${label}</span>`;
}
function myBookingPaymentBadge(payment_status){
    const colors = {
        paid:    { color: '#2e7d32', bg: '#e8f5e9' },
        failed:  { color: '#d93025', bg: '#fdecea' },
        pending: { color: '#e08a00', bg: '#fff6e5' },
        cod:     { color: '#7a5b00', bg: '#fff8e6' },
    };
    const c = colors[payment_status] || colors.pending;
    const label = mbT().payment[payment_status] || mbT().payment.pending;
    return `<span style="background:${c.bg};color:${c.color};font-weight:600;font-size:11.5px;padding:3px 9px;border-radius:20px;white-space:nowrap">${label}</span>`;
}
function myBookingDurationLabel(b){
    const t = mbT();
    const parts = [];
    if (b.total_days)  parts.push(b.total_days + ' ' + (b.total_days > 1 ? t.days : t.day));
    if (b.total_hours) parts.push(b.total_hours + ' ' + t.hr);
    return parts.length ? parts.join(', ') : '—';
}
let MY_BOOKINGS_CACHE = [];
function showBookingDetails(idx){
    const b = MY_BOOKINGS_CACHE[idx];
    if (!b) return;
    const t = mbT();
    const name = (window.lang === 'mr' && b.equipment_name_mr) ? b.equipment_name_mr : (b.equipment_name || '—');
    const row = (label, val) => val ? `<div style="display:flex;justify-content:space-between;gap:14px;padding:9px 0;border-bottom:1px solid #f0f3f0;font-size:13.5px"><span style="color:#777">${label}</span><strong style="text-align:right">${val}</strong></div>` : '';
    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal" style="max-width:420px">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        ${b.equipment_image_url ? `<img src="${b.equipment_image_url}" onerror="this.onerror=null;this.src='${b.equipment_image_fallback || ''}'" style="width:100%;max-height:180px;object-fit:cover;border-radius:10px;margin-bottom:12px">` : ''}
        <div class="rm-head">
            <div><h3 style="margin:0">${name}</h3><span style="color:#888;font-size:12.5px">${b.booking_number || ''}</span></div>
        </div>
        <div style="padding:4px 2px 6px">
            ${row(t.detailLabels.status, myBookingStatusBadge(b.status))}
            ${row(t.detailLabels.payment, myBookingPaymentBadge(b.payment_status))}
            ${row(t.detailLabels.dates, `${b.from_date_fmt || ''} → ${b.to_date_fmt || ''}`)}
            ${row(t.detailLabels.duration, myBookingDurationLabel(b))}
            ${row(t.detailLabels.amount, '₹' + (b.total_amount || 0))}
            ${row(t.detailLabels.owner, b.owner_name || '')}
            ${row(t.detailLabels.contactName, b.contact_name || '')}
            ${row(t.detailLabels.contactMobile, b.contact_mobile || '')}
            ${row(t.detailLabels.address, b.delivery_address || '')}
            ${row(t.detailLabels.machineNo, b.pn || '')}
            ${row(t.detailLabels.serialNo, b.serial_no || '')}
        </div>
        <div style="display:flex;gap:10px;margin-top:14px">
            ${b.equipment_id ? `<button class="rm-confirm-btn" style="flex:1" onclick="closeModal();openTrackingModal(${b.equipment_id})"><i class="fa-solid fa-location-crosshairs"></i> ${t.track}</button>` : ''}
            ${(b.status !== 'pending' && b.status !== 'cancelled' && !['paid','cod','verification_pending'].includes(b.payment_status)) ? `<a href="payment.php?booking_id=${b.id}" class="rm-confirm-btn" style="flex:1;text-decoration:none;display:inline-flex;align-items:center;justify-content:center;background:#2e7d32"><i class="fa-solid fa-indian-rupee-sign"></i> ${t.payNow}</a>` : ''}
        </div>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';
}
function myBookingCardHtml(b, i, onClickFn){
    const t = mbT();
    const name = (window.lang === 'mr' && b.equipment_name_mr) ? b.equipment_name_mr : (b.equipment_name || '—');
    return `
        <div onclick="${onClickFn}(${i})" style="cursor:pointer;background:#fafcfa;border:1.5px solid #e8efe8;border-radius:13px;padding:14px;transition:.15s;" onmouseover="this.style.boxShadow='0 4px 14px rgba(27,67,50,0.1)';this.style.borderColor='#bcd9bc'" onmouseout="this.style.boxShadow='none';this.style.borderColor='#e8efe8'">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:8px;margin-bottom:6px">
                <strong style="font-size:14px;color:#1b4332;line-height:1.3">${name}</strong>
                <i class="fa-solid fa-circle-info" style="color:#9db99d;font-size:13px;margin-top:2px"></i>
            </div>
            <div style="font-size:11.5px;color:#999;margin-bottom:8px">${b.booking_number || ('#' + b.id)}</div>
            <div style="font-size:12.5px;color:#555;margin-bottom:4px"><i class="fa-regular fa-calendar" style="width:14px;color:#8fa88f"></i> ${b.from_date_fmt || ''} → ${b.to_date_fmt || ''}</div>
            <div style="font-size:12.5px;color:#555;margin-bottom:10px"><i class="fa-regular fa-clock" style="width:14px;color:#8fa88f"></i> ${myBookingDurationLabel(b)} &nbsp;·&nbsp; ₹${b.total_amount || 0}</div>
            <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:10px">
                ${myBookingStatusBadge(b.status)}
                ${myBookingPaymentBadge(b.payment_status)}
            </div>
            <div style="display:flex;gap:6px;align-items:center;justify-content:space-between">
                <span style="font-size:11px;color:#9aa89a">${onClickFn === 'showBookingDetails' ? t.viewDetails : (t.viewAllHint || t.viewDetails)}</span>
                ${b.equipment_id ? `<button onclick="event.stopPropagation();openTrackingModal(${b.equipment_id})" style="background:#1b4332;color:#fff;border:none;border-radius:7px;padding:6px 10px;font-size:11.5px;cursor:pointer;white-space:nowrap"><i class="fa-solid fa-location-crosshairs"></i> ${t.track}</button>` : ''}
            </div>
        </div>`;
}
function renderMyBookingsCards(){
    const el = document.getElementById('myBookingsList');
    if (!el) return;
    const t = mbT();
    const bookings = MY_BOOKINGS_CACHE;
    const countEl = document.getElementById('myBookingsCount');
    if (countEl) countEl.textContent = bookings.length ? (bookings.length + ' ' + (bookings.length > 1 ? t.bookingsWord : t.bookingWord)) : '';
    if (!bookings.length) {
        el.innerHTML = `<div style="padding:6px 0">${t.empty}</div>`;
        return;
    }
    // Sidebar शो फक्त सर्वात recent booking — बाकी सर्व पाहण्यासाठी त्यावर क्लिक करा.
    const latestHtml = myBookingCardHtml(bookings[0], 0, 'openAllBookingsModal');
    const moreLink = bookings.length > 1
        ? `<div onclick="openAllBookingsModal()" style="cursor:pointer;text-align:center;font-size:12px;font-weight:600;color:#2e7d32;padding:8px 0 2px">${(t.viewAllBtn || 'View all bookings').replace('{n}', bookings.length)} <i class="fa-solid fa-arrow-right"></i></div>`
        : '';
    el.innerHTML = `
        <div style="display:grid;grid-template-columns:1fr;gap:10px;padding:14px 16px;">
        ${latestHtml}
        ${moreLink}
        </div>`;
}
function openAllBookingsModal(){
    const t = mbT();
    const bookings = MY_BOOKINGS_CACHE;
    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal" style="max-width:480px;max-height:82vh;overflow-y:auto">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="rm-head"><div><h3 style="margin:0">${t.allBookingsTitle || 'माझी सर्व Bookings'}</h3></div></div>
        <div style="display:grid;grid-template-columns:1fr;gap:10px;margin-top:8px">
            ${bookings.map((b, i) => myBookingCardHtml(b, i, 'showBookingDetails')).join('')}
        </div>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';
}
function loadMyBookings(){
    const el = document.getElementById('myBookingsList');
    if (!el) return;
    fetch('get_my_history.php')
        .then(r => r.json())
        .then(data => {
            MY_BOOKINGS_CACHE = (data && data.success && Array.isArray(data.bookings)) ? data.bookings : [];
            renderMyBookingsCards();
        })
        .catch(() => { el.innerHTML = `<div style="color:#d93025">${mbT().loadError}</div>`; });
}
document.addEventListener('DOMContentLoaded', () => {
    if (typeof IS_LOGGED_IN !== 'undefined' && IS_LOGGED_IN) loadMyBookings();
});

/* ── Star helper ── */
function renderStarsMini(rating) {
    let html = '';
    for (let i = 1; i <= 5; i++) {
        html += `<i class="fa-solid fa-star" style="color:${i <= Math.round(rating) ? '#FFC107' : '#ddd'};font-size:11px"></i>`;
    }
    return html;
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   COMPARISON FEATURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
let compareList = [];

function toggleCompare(id, checked) {
    const rt = RentalT[window.lang || 'en'];
    if (checked) {
        if (compareList.length >= 3) {
            alert('Max 3 machines for comparison');
            document.getElementById('cmp-' + id).checked = false;
            return;
        }
        compareList.push(id);
    } else {
        compareList = compareList.filter(x => x !== id);
    }
    const bar = document.getElementById('compareBar');
    if (compareList.length >= 2) {
        bar.style.display = 'flex';
        document.getElementById('compareBarCount').textContent = compareList.length + ' selected';
    } else {
        bar.style.display = 'none';
    }
}

function openCompareModal() {
    const rt = RentalT[window.lang || 'en'];
    const machines = compareList.map(id => findMachine(id));
    const specKeys = ['hp','engine','gears','lift'];
    const specLabels = [rt.compareHP, rt.compareEngine, rt.compareGears, rt.compareLift];

    let headerRow = `<th>${rt.compareSpec}</th>` + machines.map(m => {
        const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
        return `<th><div class="cmp-img-wrap"><img src="${m.img}" onerror="this.src='${m.imgFallback}'" style="width:80px;height:60px;object-fit:cover;border-radius:8px"></div><div>${name}</div></th>`;
    }).join('');

    let rows = specKeys.map((k, i) => {
        return `<tr><td class="cmp-label">${specLabels[i]}</td>${machines.map(m => `<td>${m.specs[k]}</td>`).join('')}</tr>`;
    });
    rows.push(`<tr><td class="cmp-label">${rt.comparePrice}</td>${machines.map(m => `<td><strong>₹${m.price}</strong></td>`).join('')}</tr>`);
    rows.push(`<tr><td class="cmp-label">${rt.compareRating}</td>${machines.map(m => `<td>${renderStarsMini(m.rating)} ${m.rating}</td>`).join('')}</tr>`);
    rows.push(`<tr><td class="cmp-label">${rt.compareOwner}</td>${machines.map(m => `<td>${m.owner}</td>`).join('')}</tr>`);
    rows.push(`<tr><td></td>${machines.map(m => `<td><button class="rm-confirm-btn" style="margin:0;padding:8px;font-size:12px" onclick="closeModal();openBookingModal(${m.id})"><i class="fa-solid fa-calendar-check"></i> Book</button></td>`).join('')}</tr>`);

    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal rm-cmp-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="rm-section-title"><i class="fa-solid fa-code-compare"></i> ${rt.compareTitle}</h3>
        <div style="overflow-x:auto">
          <table class="cmp-table">
            <thead><tr>${headerRow}</tr></thead>
            <tbody>${rows.join('')}</tbody>
          </table>
        </div>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';
}

function clearCompare() {
    compareList = [];
    document.querySelectorAll('.compare-chk').forEach(c => c.checked = false);
    document.getElementById('compareBar').style.display = 'none';
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   REVIEW & RATING MODAL (real DB — shared reviews table)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function openReviewModal(id) {
    const m = findMachine(id);
    const rt = RentalT[window.lang || 'en'];
    const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;

    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="rm-section-title"><i class="fa-solid fa-star"></i> ${rt.reviewTitle} — ${name}</h3>
        <div class="rating-breakdown" id="eqRatingBreakdown"></div>
        <div class="reviews-list" id="eqReviewsList"><p style="color:#999;font-size:13px">Loading...</p></div>
        <hr style="margin:14px 0;border-color:#eee">
        <h4 class="rm-section-title">${rt.writeReview}</h4>
        <div class="star-picker" id="starPicker">
            ${[1,2,3,4,5].map(n => `<i class="fa-solid fa-star star-pick" data-v="${n}" onclick="pickStar(${n})" style="font-size:24px;color:#ddd;cursor:pointer;margin:2px"></i>`).join('')}
        </div>
        <textarea id="reviewText" rows="3" placeholder="${rt.reviewPh}" style="width:100%;margin-top:10px;border:1px solid #ddd;border-radius:8px;padding:8px;font-size:13px;box-sizing:border-box;resize:none"></textarea>
        <button class="rm-confirm-btn" onclick="submitReview(${id})">${rt.submitReview}</button>
      </div>
    </div>`;
    window._selectedStars = 0;
    pickStar(0);
    document.body.style.overflow = 'hidden';
    loadEquipmentReviews(id);
}

function renderRatingBreakdown(breakdown, count) {
    if (!breakdown || !count) return '';
    return [5,4,3,2,1].map(star => {
        const c = breakdown[star] || 0;
        const pct = count > 0 ? Math.round((c / count) * 100) : 0;
        return `<div class="rb-row"><span class="rb-label">${star} ★</span><div class="rb-track"><div class="rb-fill" style="width:${pct}%"></div></div><span class="rb-count">${c}</span></div>`;
    }).join('');
}

function loadEquipmentReviews(id) {
    fetch('get_reviews.php?item_type=equipment&item_id=' + id)
    .then(r => r.json())
    .then(data => {
        const bd = document.getElementById('eqRatingBreakdown');
        if (bd) bd.innerHTML = renderRatingBreakdown(data.breakdown, data.count);

        const el = document.getElementById('eqReviewsList');
        if (!el) return;
        if (data.count === 0) {
            el.innerHTML = '<p style="color:#999;font-size:13px">अजून कोणतंही review नाही — पहिलं तुम्ही द्या!</p>';
        } else {
            el.innerHTML = data.reviews.map(r => `
                <div class="review-item">
                    <div class="review-stars">${renderStarsMini(r.rating)}</div>
                    <div class="review-text">"${(r.comment || '').replace(/</g,'&lt;')}"</div>
                    <div class="review-user"><i class="fa-solid fa-user-check"></i> ${r.name}${r.verified ? ' <span class="review-verified-badge"><i class="fa-solid fa-circle-check"></i> Verified</span>' : ''} · <span style="color:#999">${r.date}</span></div>
                </div>`).join('');
        }

        // If this logged-in user already reviewed this equipment, pre-fill
        // the form so re-submitting updates their review instead of confusing them.
        if (data.my_review) {
            const textEl = document.getElementById('reviewText');
            if (textEl) textEl.value = data.my_review.comment || '';
            pickStar(data.my_review.rating || 0);
        }
    })
    .catch(() => {
        const el = document.getElementById('eqReviewsList');
        if (el) el.innerHTML = '<p style="color:#d93025;font-size:13px">Reviews load करता आले नाही.</p>';
    });
}

function pickStar(n) {
    window._selectedStars = n;
    document.querySelectorAll('.star-pick').forEach(s => {
        s.style.color = parseInt(s.getAttribute('data-v')) <= n ? '#FFC107' : '#ddd';
    });
}

function submitReview(id) {
    const rt = RentalT[window.lang || 'en'];
    if (!IS_LOGGED_IN) {
        alert(rt.loginRequired || 'Review देण्यासाठी login करा.');
        window.location.href = 'login.php';
        return;
    }
    const textEl = document.getElementById('reviewText');
    const text = textEl ? textEl.value.trim() : '';
    if (!text) return;
    if (!window._selectedStars) {
        alert(rt.selectRating || 'कृपया आधी star rating निवडा.');
        return;
    }

    fetch('submit_review.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            item_type: 'equipment', item_id: id,
            rating: window._selectedStars, comment: text
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            if (textEl) textEl.value = '';
            loadEquipmentReviews(id);
        } else {
            alert(data.error || 'Review save झालं नाही.');
        }
    })
    .catch(() => alert('Network error, please try again.'));
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   NEARBY EQUIPMENT (Geolocation)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function openNearbyModal() {
    const rt = RentalT[window.lang || 'en'];
    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="rm-section-title"><i class="fa-solid fa-location-dot"></i> ${rt.nearbyTitle}</h3>
        <button class="rm-confirm-btn" id="detectBtn" onclick="detectLocation()">
            <i class="fa-solid fa-crosshairs"></i> ${rt.nearbyDetect}
        </button>
        <div id="nearbyList" style="margin-top:14px"></div>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';
}

function detectLocation() {
    const rt = RentalT[window.lang || 'en'];
    const btn = document.getElementById('detectBtn');
    btn.textContent = rt.nearbyDetecting;
    btn.disabled = true;

    // Sort by distance and show — demo uses pre-set distKm values
    setTimeout(() => {
        const sorted = [...MACHINERY].sort((a, b) => a.distKm - b.distKm);
        const lang = window.lang || 'en';
        document.getElementById('nearbyList').innerHTML = sorted.map(m => {
            const name = lang === 'mr' ? m.nameMr : lang === 'hi' ? m.nameHi : m.nameEn;
            const unit = lang === 'mr' ? m.unitMr : lang === 'hi' ? m.unitHi : m.unitEn;
            return `<div class="nearby-item">
                <img src="${m.img}" onerror="this.src='${m.imgFallback}'" style="width:52px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0">
                <div style="flex:1">
                    <div style="font-weight:700;font-size:13px">${m.emoji} ${name}</div>
                    <div style="font-size:12px;color:#2E7D32"><i class="fa-solid fa-location-dot"></i> ${m.village} — ${m.distKm} ${rt.nearbyKm}</div>
                    <div style="font-size:12px;color:#666">₹${m.price}/${unit} • ⭐ ${m.rating}</div>
                </div>
                <button class="add-btn" style="padding:7px 10px;font-size:12px;white-space:nowrap" onclick="closeModal();openBookingModal(${m.id})"><i class="fa-solid fa-calendar-check"></i> Book</button>
            </div>`;
        }).join('');
        btn.style.display = 'none';
    }, 1200);
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   RAZORPAY PAYMENT (demo)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function launchRazorpay(amount, machineName, onSuccess) {
    /* In production: replace key_id with your actual Razorpay key */
    if (typeof Razorpay === 'undefined') {
        // Razorpay script not loaded — simulate demo payment
        if (confirm('Demo Mode: Razorpay checkout would open here.\nSimulate successful payment?')) {
            onSuccess();
        }
        return;
    }
    const options = {
        key: 'rzp_test_YOURKEYHERE',
        amount: amount * 100,
        currency: 'INR',
        name: 'AgriRental',
        description: machineName,
        image: '',
        handler: function() { onSuccess(); },
        prefill: { name: '', email: '', contact: '' },
        theme: { color: '#2E7D32' }
    };
    new Razorpay(options).open();
}

let bookingState = { machineId: null, calMonth: new Date(), start: null, end: null, hours: 8, couponCode: null, couponPct: 0 };

function findMachine(id){ return MACHINERY.find(m => m.id === id); }

function openBookingModal(id) {
    const m = findMachine(id);
    if (!m) return;
    const rt = RentalT[window.lang || 'en'];
    bookingState = { machineId: id, calMonth: new Date(), start: null, end: null, hours: 8, couponCode: null, couponPct: 0 };

    const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
    const unit = (window.lang === 'mr') ? m.unitMr : (window.lang === 'hi') ? m.unitHi : m.unitEn;
    const ownerBadge = m.verified
        ? `<span class="owner-verified-badge"><i class="fa-solid fa-shield-halved"></i> ${rt.verifiedOwner}</span>`
        : `<span class="owner-unverified-badge"><i class="fa-solid fa-clock"></i> ${rt.pendingOwner}</span>`;

    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="rm-head">
            <div class="rm-emoji">${m.emoji}</div>
            <div>
                <h3>${name}</h3>
                <div class="owner-row"><span class="owner-name"><i class="fa-solid fa-user"></i> ${m.owner}</span> ${ownerBadge}</div>
                <div class="owner-rating-row"><span><i class="fa-solid fa-star" style="color:#FFC107"></i> ${m.rating}</span> <span style="color:#999">• ${m.trips} ${rt.tripsDone}</span></div>
            </div>
        </div>
        <h4 class="rm-section-title"><i class="fa-solid fa-calendar-days"></i> ${rt.chooseDates}</h4>
        <div class="rm-cal-nav">
            <button onclick="shiftMonth(-1)"><i class="fa-solid fa-chevron-left"></i></button>
            <span id="calMonthLabel"></span>
            <button onclick="shiftMonth(1)"><i class="fa-solid fa-chevron-right"></i></button>
        </div>
        <div class="rm-cal-grid" id="calGrid"></div>
        <div class="rm-cal-legend">
            <span><i class="rm-dot avail"></i> ${rt.legendAvail}</span>
            <span><i class="rm-dot booked"></i> ${rt.legendBooked}</span>
            <span><i class="rm-dot selected"></i> ${rt.legendSelected}</span>
        </div>

        <div class="rm-form-row" id="rmSavedAddrBox" style="${SAVED_ADDRESS ? '' : 'display:none'}">
            <div class="rm-saved-addr">
                <div class="rm-saved-addr-text"><i class="fa-solid fa-location-dot"></i> <span id="rmSavedAddrText">${SAVED_ADDRESS ? SAVED_ADDRESS.replace(/</g,'&lt;') : ''}</span></div>
                <button type="button" class="rm-saved-addr-change" onclick="toggleRentalAddressEdit()">Change</button>
            </div>
        </div>
        <div class="rm-form-row" id="rmAddrFieldRow" style="${SAVED_ADDRESS ? 'display:none' : ''}">
            <label>${rt.locationLabel}</label>
            <input type="text" placeholder="${rt.locationPh}" id="locationInput" value="${SAVED_ADDRESS ? SAVED_ADDRESS.replace(/"/g,'&quot;') : ''}">
        </div>

        <div class="rm-form-row">
            <label>${rt.hoursLabel} <span style="font-weight:400;color:#888">(₹${m.priceHour ? m.priceHour : Math.round(m.price/8)}/hr — ${rt.hoursHint || 'only applies for a single-day booking'})</span></label>
            <input type="number" min="1" max="24" value="8" id="hoursInput" oninput="onHoursChange()">
        </div>

        <div class="rm-price-box">
            <h4 class="rm-section-title"><i class="fa-solid fa-calculator"></i> ${rt.priceCalcTitle}</h4>
            <div class="rm-price-line"><span>${rt.baseRate}</span><span>₹${m.price} / ${unit}</span></div>
            <div class="rm-price-line"><span>${rt.duration}</span><span id="calcDuration">1 ${unit}</span></div>
            <div class="rm-price-line"><span>${rt.subtotal}</span><span id="calcSubtotal">₹${m.price}</span></div>
            <div style="display:flex;gap:6px;margin:10px 0">
                <input type="text" id="couponInput" placeholder="${rt.couponPh || 'Coupon code'}" style="flex:1;padding:8px 10px;border:1.5px solid #d9e4d9;border-radius:8px;font-size:13px;text-transform:uppercase" maxlength="20">
                <button type="button" class="rm-confirm-btn" style="width:auto;margin:0;padding:8px 14px;font-size:12.5px" onclick="applyCoupon()">${rt.applyCouponBtn || 'Apply'}</button>
            </div>
            <div id="couponMsg" style="font-size:12px;margin:-4px 0 6px;min-height:14px"></div>
            <div class="rm-price-line" id="couponDiscountLine" style="display:none;color:#2e7d32"><span>${rt.couponDiscountLbl || 'Coupon Discount'}</span><span id="calcCouponDiscount">-₹0</span></div>
            <div class="rm-price-line"><span>${rt.serviceFee} (5%)</span><span id="calcFee">₹${Math.round(m.price*0.05)}</span></div>
            <div class="rm-price-line rm-total"><span>${rt.totalAmount}</span><span id="calcTotal">₹${Math.round(m.price*1.05)}</span></div>
        </div>

        <button class="rm-confirm-btn" onclick="confirmBooking()"><i class="fa-solid fa-circle-check"></i> ${rt.confirmBookBtn}</button>
      </div>

    </div>`;
    renderCalendar();
    document.body.style.overflow = 'hidden';
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   EQUIPMENT DETAILS MODAL (info + description on click)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function buildAutoDescription(m, rt) {
    const unit = (window.lang === 'mr') ? m.unitMr : (window.lang === 'hi') ? m.unitHi : m.unitEn;
    const parts = [];
    parts.push(`${rt.ownedByLbl} ${m.owner}${m.verified ? ' (' + rt.verifiedOwner + ')' : ''} — ${m.village}.`);
    const specBits = [];
    if (m.specs.hp && m.specs.hp !== '-') specBits.push(`${rt.compareHP}: ${m.specs.hp}`);
    if (m.specs.engine && m.specs.engine !== '-') specBits.push(`${rt.compareEngine}: ${m.specs.engine}`);
    if (m.specs.gears && m.specs.gears !== '-') specBits.push(`${rt.compareGears}: ${m.specs.gears}`);
    if (m.specs.lift && m.specs.lift !== '-') specBits.push(`${rt.compareLift}: ${m.specs.lift}`);
    if (specBits.length) parts.push(specBits.join(' · ') + '.');
    parts.push(`${rt.driverInc}. ₹${m.price} / ${unit}. ${m.trips} ${rt.tripsDone}, ${m.rating} ★ (${m.reviewCount} ${rt.reviewsLbl}).`);
    return parts.join(' ');
}

function openDetailsModal(id) {
    const m = findMachine(id);
    if (!m) return;
    const rt = RentalT[window.lang || 'en'];
    const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
    const unit = (window.lang === 'mr') ? m.unitMr : (window.lang === 'hi') ? m.unitHi : m.unitEn;
    const btnText = (window.lang === 'mr') ? 'आता बुक करा' : (window.lang === 'hi') ? 'अभी बुक करें' : 'Book Now';
    const ownerBadgeHtml = m.verified
        ? `<span class="owner-verified-badge"><i class="fa-solid fa-shield-halved"></i> ${rt.verifiedOwner}</span>`
        : `<span class="owner-unverified-badge"><i class="fa-solid fa-clock"></i> ${rt.pendingOwner}</span>`;

    const specRows = [
        [rt.compareHP, m.specs.hp],
        [rt.compareEngine, m.specs.engine],
        [rt.compareGears, m.specs.gears],
        [rt.compareLift, m.specs.lift],
    ].filter(r => r[1] && r[1] !== '-');

    const reviews = (window.lang === 'mr') ? m.reviewsMr : (window.lang === 'hi') ? m.reviewsHi : m.reviewsEn;

    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal rm-details-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <img src="${m.img}" onerror="this.onerror=null;this.src='${m.imgFallback}'" style="width:100%;max-height:220px;object-fit:cover;border-radius:10px;margin-bottom:12px">
        <div class="rm-head" style="padding-right:0">
          <span class="rm-emoji">${m.emoji}</span>
          <div>
            <h3>${name}</h3>
            <div style="font-size:12.5px;color:#666;display:flex;align-items:center;gap:6px;flex-wrap:wrap"><i class="fa-solid fa-user"></i> ${m.owner} ${ownerBadgeHtml}</div>
          </div>
        </div>
        <div class="owner-rating-row" style="margin:6px 0 12px">
          <span><i class="fa-solid fa-star" style="color:#FFC107"></i> ${m.rating}</span>
          <span style="color:#999">• ${m.trips} ${rt.tripsDone}</span>
          <span class="nearby-badge"><i class="fa-solid fa-location-dot"></i> ${m.village} — ${m.distKm} ${rt.nearbyKm}</span>
        </div>
        <div class="rm-price-box" style="margin-top:0">
          <div class="rm-price-line rm-total"><span>${rt.comparePrice}</span><span>₹${m.price} / ${unit}</span></div>
        </div>
        ${specRows.length ? `
        <h4 class="rm-section-title"><i class="fa-solid fa-gears"></i> ${rt.compareSpec}</h4>
        <div class="rm-specs-grid">
          ${specRows.map(([label,val]) => `<div class="rm-spec-item"><span>${label}</span><strong>${val}</strong></div>`).join('')}
        </div>` : ''}
        <h4 class="rm-section-title"><i class="fa-solid fa-circle-info"></i> ${rt.aboutTitle}</h4>
        <p style="font-size:13px;color:#444;line-height:1.6;margin:0 0 4px">${buildAutoDescription(m, rt)}</p>
        <h4 class="rm-section-title"><i class="fa-solid fa-star"></i> ${rt.reviewTitle} (${m.reviewCount})</h4>
        <div class="reviews-list">${reviews.slice(0,3).map(r=>`<div class="review-item" style="font-size:12.5px;padding:8px 0;border-bottom:1px solid #f0f0f0">${r}</div>`).join('')}</div>
        <div class="card-btn-row" style="margin-top:14px">
          <button class="rm-confirm-btn" style="margin-top:0" onclick="closeModal();openBookingModal(${m.id})"><i class="fa-solid fa-calendar-check"></i> ${btnText}</button>
        </div>
        <a class="whatsapp-btn" style="display:flex;justify-content:center;margin-top:8px" href="https://wa.me/91${m.phone}?text=${encodeURIComponent('Hi, I want to know more about ' + name + ' from AgriRental.')}" target="_blank"><i class="fa-brands fa-whatsapp"></i> ${rt.whatsappBtn}</a>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';
}

function closeModal(){
    document.getElementById('modalRoot').innerHTML = '';
    document.body.style.overflow = '';
}

function shiftMonth(dir){
    bookingState.calMonth.setMonth(bookingState.calMonth.getMonth() + dir);
    renderCalendar();
}

function renderCalendar(){
    const rt = RentalT[window.lang || 'en'];
    const m = findMachine(bookingState.machineId);
    const cal = bookingState.calMonth;
    const year = cal.getFullYear(), month = cal.getMonth();
    const monthNames = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];
    document.getElementById('calMonthLabel').textContent = monthNames[month] + ' ' + year;

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const todayStr = new Date().toISOString().slice(0,10);
    const booked = BOOKED_DATES[m.id] || [];

    let html = '';
    ['S','M','T','W','T','F','S'].forEach(d => html += `<div class="rm-cal-dow">${d}</div>`);
    for (let i = 0; i < firstDay; i++) html += `<div class="rm-cal-cell empty"></div>`;

    for (let d = 1; d <= daysInMonth; d++) {
        const dateStr = new Date(year, month, d).toISOString().slice(0,10);
        const isPast = dateStr < todayStr;
        const isBooked = booked.includes(dateStr);
        const isSelStart = bookingState.start === dateStr;
        const isSelEnd = bookingState.end === dateStr;
        const inRange = bookingState.start && bookingState.end && dateStr > bookingState.start && dateStr < bookingState.end;
        let cls = 'rm-cal-cell';
        if (isPast) cls += ' past';
        else if (isBooked) cls += ' booked';
        else cls += ' avail';
        if (isSelStart || isSelEnd) cls += ' selected';
        if (inRange) cls += ' inrange';
        const clickable = !isPast && !isBooked;
        html += `<div class="${cls}" ${clickable ? `onclick="pickDate('${dateStr}')"` : ''}>${d}</div>`;
    }
    document.getElementById('calGrid').innerHTML = html;
    updatePriceCalc();
}

function pickDate(dateStr){
    if (!bookingState.start || (bookingState.start && bookingState.end)) {
        bookingState.start = dateStr;
        bookingState.end = null;
    } else {
        if (dateStr < bookingState.start) {
            bookingState.end = bookingState.start;
            bookingState.start = dateStr;
        } else {
            bookingState.end = dateStr;
        }
    }
    renderCalendar();
}

function onHoursChange(){
    const hoursInput = document.getElementById('hoursInput');
    if (hoursInput) {
        let v = parseInt(hoursInput.value) || 1;
        v = Math.min(24, Math.max(1, v));
        bookingState.hours = v;
    }
    updatePriceCalc();
}

function updatePriceCalc(){
    const m = findMachine(bookingState.machineId);
    if (!m) return;
    const rt = RentalT[window.lang || 'en'];
    const unit = (window.lang === 'mr') ? m.unitMr : (window.lang === 'hi') ? m.unitHi : m.unitEn;

    let dayMultiplier = 1;
    if (bookingState.start && bookingState.end) {
        const d1 = new Date(bookingState.start), d2 = new Date(bookingState.end);
        dayMultiplier = Math.max(1, Math.round((d2 - d1) / 86400000) + 1);
    }

    // Hourly pricing applies for a single-day booking; uses the admin-set hourly rate
    // if configured, otherwise falls back to (day rate / 8 hrs) as the implied hourly rate.
    const useHourly = dayMultiplier === 1;
    const effHourlyRate = m.priceHour || Math.round(m.price / 8);
    const subtotal = useHourly ? (effHourlyRate * bookingState.hours) : (m.price * dayMultiplier);
    const discount = bookingState.couponPct ? Math.round(subtotal * bookingState.couponPct / 100) : 0;
    const taxableAmt = subtotal - discount;
    const fee = Math.round(taxableAmt * 0.05);
    const total = taxableAmt + fee;

    const durEl = document.getElementById('calcDuration');
    const subEl = document.getElementById('calcSubtotal');
    const feeEl = document.getElementById('calcFee');
    const totEl = document.getElementById('calcTotal');
    const discLine = document.getElementById('couponDiscountLine');
    const discEl = document.getElementById('calcCouponDiscount');
    if (durEl) durEl.textContent = useHourly ? `${bookingState.hours} hr` : `${dayMultiplier} ${unit}`;
    if (subEl) subEl.textContent = `₹${subtotal}`;
    if (discLine) discLine.style.display = discount > 0 ? 'flex' : 'none';
    if (discEl) discEl.textContent = `-₹${discount}`;
    if (feeEl) feeEl.textContent = `₹${fee}`;
    if (totEl) totEl.textContent = `₹${total}`;
}

/* ── Coupon codes (validated again server-side in book_equipment.php) ── */
const RM_COUPONS = { RENT10: 10 };
function applyCoupon(){
    const rt = RentalT[window.lang || 'en'];
    const input = document.getElementById('couponInput');
    const msgEl = document.getElementById('couponMsg');
    if (!input) return;
    const code = input.value.trim().toUpperCase();
    if (!code) return;
    if (RM_COUPONS[code]) {
        bookingState.couponCode = code;
        bookingState.couponPct = RM_COUPONS[code];
        if (msgEl) { msgEl.style.color = '#2e7d32'; msgEl.textContent = `✓ ${code} ${rt.couponApplied || 'applied'} — ${RM_COUPONS[code]}% ${rt.couponOff || 'off'}`; }
    } else {
        bookingState.couponCode = null;
        bookingState.couponPct = 0;
        if (msgEl) { msgEl.style.color = '#d93025'; msgEl.textContent = rt.couponInvalid || 'Invalid coupon code.'; }
    }
    updatePriceCalc();
}

function toggleRentalAddressEdit(){
    document.getElementById('rmSavedAddrBox').style.display = 'none';
    document.getElementById('rmAddrFieldRow').style.display = 'block';
}
function confirmBooking(){
    const rt = RentalT[window.lang || 'en'];
    if (!bookingState.start || !bookingState.end) {
        alert(rt.selectDatesAlert);
        return;
    }
    if (!IS_LOGGED_IN) {
        alert(rt.loginRequired || 'कृपया आधी login करा.');
        window.location.href = 'login.php';
        return;
    }
    const m = findMachine(bookingState.machineId);
    const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
    const totalEl = document.getElementById('calcTotal');
    const totalAmt = totalEl ? parseInt(totalEl.textContent.replace('₹','')) : m.price;
    const locationInput = document.getElementById('locationInput');
    const address = locationInput ? locationInput.value.trim() : '';

    const btn = document.querySelector('.rm-confirm-btn');
    if (btn) { btn.disabled = true; btn.textContent = '...'; }

    fetch('book_equipment.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
            equipment_id: m.id,
            start_date: bookingState.start,
            end_date: bookingState.end,
            delivery_address: address,
            hours: bookingState.hours || 8,
            coupon_code: bookingState.couponCode || '',
            csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            alert(rt.bookingSuccess + '\n\nBooking ID: ' + data.booking_number);
            closeModal();
            location.reload(); // refresh so the newly booked dates show as blocked
        } else {
            alert(data.error || 'Booking failed, please try again.');
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + rt.confirmBookBtn; }
        }
    })
    .catch(() => {
        alert('Network error, please try again.');
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-circle-check"></i> ' + rt.confirmBookBtn; }
    });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   LIVE TRACKING MODAL
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function openTrackingModal(id){
    const m = findMachine(id);
    if (!m) return;
    const rt = RentalT[window.lang || 'en'];
    const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;

    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal rm-track-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <div class="rm-head">
            <div class="rm-emoji">${m.emoji}</div>
            <div><h3>${name}</h3><span class="track-live-badge"><i class="fa-solid fa-satellite-dish"></i> ${rt.trackModalTitle}</span></div>
        </div>
        <div id="trackContent" style="text-align:center;padding:24px;color:#666;">Loading...</div>
        <a href="my_activity.php" style="display:block;text-align:center;margin-top:6px;font-size:12.5px;color:var(--primary,#2F4F44);font-weight:600;text-decoration:none">${rt.fullHistoryLbl || 'View full activity (Orders + Rentals + Advisory) →'}</a>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';

    if (!IS_LOGGED_IN) {
        document.getElementById('trackContent').innerHTML =
            `<p>${rt.loginRequired || 'तुमची booking बघण्यासाठी login करा.'}</p>`;
        return;
    }

    fetch('get_booking_status.php?equipment_id=' + encodeURIComponent(m.id))
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('trackContent');
        if (!el) return;
        if (!data.found) {
            el.innerHTML = `<p><i class="fa-solid fa-circle-info"></i> ${rt.noBooking || 'या equipment साठी तुमची कोणतीही active booking नाही.'}</p>`;
            return;
        }
        const steps = [rt.trackStep1, rt.trackStep2, rt.trackStep3, rt.trackStep4];
        const activeStep = data.step; // 0..3

        let stepsHtml = steps.map((s, i) => `
            <div class="rm-track-step ${i <= activeStep ? 'done' : ''} ${i === activeStep ? 'current' : ''}">
                <div class="rm-track-dot"><i class="fa-solid ${i <= activeStep ? 'fa-check' : 'fa-circle'}"></i></div>
                <div class="rm-track-label">${s}</div>
            </div>`).join('<div class="rm-track-line"></div>');

        const payBadge = data.payment_status === 'paid'
            ? `<span style="color:#2e7d32;font-weight:600">Paid ✓</span>`
            : (data.payment_status === 'failed'
                ? `<span style="color:#d93025;font-weight:600">Payment Rejected — please retry</span>`
                : (data.payment_status === 'verification_pending'
                    ? `<span style="color:#1565c0;font-weight:600">Verification Pending</span>`
                    : (data.payment_status === 'cod'
                        ? `<span style="color:#7a5b00;font-weight:600">Cash on Delivery</span>`
                        : `<span style="color:#e08a00;font-weight:600">Pending</span>`)));

        // Owner ne booking accept keli (step >= 1, i.e. status 'confirmed' ke pudhe) ani
        // payment ajun 'paid'/'verification_pending'/'cod' zala nasel tarach Pay Now button dakhva.
        // ('failed' still shows Pay Now so the user can resubmit proof.)
        const canPay = data.status !== 'pending' && data.status !== 'cancelled'
            && !['paid', 'verification_pending', 'cod'].includes(data.payment_status);
        const payNowHtml = canPay
            ? `<a href="payment.php?booking_id=${encodeURIComponent(data.id)}" class="rm-confirm-btn" style="display:inline-flex;margin-top:14px;text-decoration:none">
                    <i class="fa-solid fa-indian-rupee-sign"></i>&nbsp;${rt.payNowBtn || 'Pay Now'}
               </a>`
            : '';

        el.innerHTML = `
            <div class="rm-track-steps">${stepsHtml}</div>
            <div class="rm-track-info">
                <div><span>Booking ID</span><strong>${data.booking_number}</strong></div>
                <div><span>${rt.trackDriver}</span><strong>${m.owner}</strong></div>
                <div><span>Dates</span><strong>${data.from_date} → ${data.to_date}</strong></div>
                ${data.total_days ? `<div><span>Days</span><strong>${data.total_days} ${data.total_days > 1 ? 'days' : 'day'}</strong></div>` : ''}
                ${data.total_hours ? `<div><span>Hours</span><strong>${data.total_hours} hr</strong></div>` : ''}
                <div><span>Total</span><strong>₹${data.total_amount}</strong></div>
                <div><span>Payment</span><strong>${payBadge}</strong></div>
            </div>
            ${payNowHtml}`;
    })
    .catch(() => {
        const el = document.getElementById('trackContent');
        if (el) el.innerHTML = `<p style="color:#d93025;">Network error, please try again.</p>`;
    });
}

/* ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
   OWNER DASHBOARD (demo)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━ */
function openOwnerDashboard(){
    const rt = RentalT[window.lang || 'en'];
    const myEquip = MACHINERY.slice(0, 4);
    const totalEarnings = myEquip.reduce((s,m) => s + m.price * m.trips * 0.2, 0);
    const avgRating = (myEquip.reduce((s,m) => s + m.rating, 0) / myEquip.length).toFixed(1);

    const rows = myEquip.map(m => {
        const name = (window.lang === 'mr') ? m.nameMr : (window.lang === 'hi') ? m.nameHi : m.nameEn;
        const statusOk = m.verified;
        return `<tr>
            <td>${m.emoji} ${name}</td>
            <td>${statusOk ? `<span class="owner-verified-badge"><i class="fa-solid fa-shield-halved"></i> ${rt.verifiedOwner}</span>` : `<span class="owner-unverified-badge"><i class="fa-solid fa-clock"></i> ${rt.pendingOwner}</span>`}</td>
            <td>${(BOOKED_DATES[m.id] || [])[0] || '-'}</td>
            <td>₹${Math.round(m.price * m.trips * 0.2).toLocaleString()}</td>
        </tr>`;
    }).join('');

    document.getElementById('modalRoot').innerHTML = `
    <div class="rm-overlay" onclick="if(event.target===this) closeModal()">
      <div class="rm-modal rm-dash-modal">
        <button class="rm-close" onclick="closeModal()"><i class="fa-solid fa-xmark"></i></button>
        <h3 class="rm-section-title"><i class="fa-solid fa-gauge-high"></i> ${rt.ownerDashTitle}</h3>
        <div class="rm-dash-stats">
            <div class="rm-dash-stat"><strong>₹${Math.round(totalEarnings).toLocaleString()}</strong><span>${rt.odEarnings}</span></div>
            <div class="rm-dash-stat"><strong>${myEquip.length}</strong><span>${rt.odBookings}</span></div>
            <div class="rm-dash-stat"><strong>${myEquip.length}</strong><span>${rt.odEquip}</span></div>
            <div class="rm-dash-stat"><strong>${avgRating} ★</strong><span>${rt.odRating}</span></div>
        </div>
        <h4 class="rm-section-title">${rt.odMyEquip}</h4>
        <table class="rm-dash-table">
            <thead><tr><th>${rt.odMyEquip}</th><th>${rt.odStatus}</th><th>${rt.odBookedOn}</th><th>${rt.odEarn}</th></tr></thead>
            <tbody>${rows}</tbody>
        </table>
      </div>
    </div>`;
    document.body.style.overflow = 'hidden';
}
</script>

<div id="modalRoot"></div>

<style>
/* Owner badges */
.owner-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-top:4px; font-size:12.5px; }
.owner-name { color:#444; font-weight:600; }
.owner-rating-row { font-size:12px; color:#444; margin-top:2px; display:flex; gap:6px; align-items:center; }
.owner-verified-badge {
    background:#e8f5e9; color:#2E7D32; border:1px solid #a5d6a7;
    padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px;
}
.owner-unverified-badge {
    background:#fff3e0; color:#e65100; border:1px solid #ffcc80;
    padding:2px 8px; border-radius:20px; font-size:11px; font-weight:700; display:inline-flex; align-items:center; gap:4px;
}
.track-btn {
    width:100%; margin-top:6px; background:#fff; color:#2E7D32; border:1.5px solid #2E7D32;
    border-radius:8px; padding:8px; font-weight:700; font-size:13px; cursor:pointer;
}
.track-btn:hover { background:#e8f5e9; }
.owner-dash-launcher {
    background:#1b4332; color:#fff; border:none; border-radius:8px; padding:8px 14px;
    font-size:12.5px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:6px;
}
.owner-dash-launcher:hover { background:#2E7D32; }

/* Modal shell */
.rm-overlay {
    position:fixed; inset:0; background:rgba(0,0,0,0.55); z-index:9999;
    display:flex; align-items:flex-start; justify-content:center; padding:30px 14px; overflow-y:auto;
}
.rm-modal {
    background:#fff; border-radius:14px; max-width:480px; width:100%; padding:22px;
    position:relative; box-shadow:0 20px 50px rgba(0,0,0,0.3);
}
.rm-close {
    position:absolute; top:14px; right:14px; background:#f1f1f1; border:none; width:32px; height:32px;
    border-radius:50%; cursor:pointer; font-size:14px; color:#333;
}
.rm-close:hover { background:#e0e0e0; }
.rm-head { display:flex; gap:12px; align-items:flex-start; margin-bottom:14px; padding-right:30px; }
.rm-emoji { font-size:38px; }
.rm-head h3 { margin:0 0 2px; font-size:17px; }
.rm-section-title { font-size:14px; margin:14px 0 8px; color:#1b4332; display:flex; align-items:center; gap:6px; }

/* Calendar */
.rm-cal-nav { display:flex; align-items:center; justify-content:space-between; margin-bottom:6px; font-weight:700; }
.rm-cal-nav button { background:#f1f1f1; border:none; width:28px; height:28px; border-radius:6px; cursor:pointer; }
.rm-cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:4px; text-align:center; }
.rm-cal-dow { font-size:11px; color:#888; font-weight:700; padding:4px 0; }
.rm-cal-cell { padding:7px 0; font-size:12.5px; border-radius:6px; }
.rm-cal-cell.empty { visibility:hidden; }
.rm-cal-cell.past { color:#ccc; }
.rm-cal-cell.avail { background:#f1f8f2; cursor:pointer; color:#2E7D32; font-weight:600; }
.rm-cal-cell.avail:hover { background:#c8e6c9; }
.rm-cal-cell.booked { background:#ffebee; color:#c62828; text-decoration:line-through; cursor:not-allowed; }
.rm-cal-cell.selected { background:#2E7D32 !important; color:#fff !important; font-weight:700; }
.rm-cal-cell.inrange { background:#a5d6a7; color:#1b4332; }
.rm-cal-legend { display:flex; gap:14px; font-size:11px; color:#555; margin:8px 0 4px; flex-wrap:wrap; }
.rm-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:4px; }
.rm-dot.avail { background:#a5d6a7; }
.rm-dot.booked { background:#ef9a9a; }
.rm-dot.selected { background:#2E7D32; }

/* Form */
.rm-form-row { margin-top:10px; }
.rm-form-row label { display:block; font-size:12.5px; font-weight:700; color:#333; margin-bottom:4px; }
.rm-form-row input { width:100%; padding:9px 10px; border:1px solid #ddd; border-radius:8px; font-size:13px; box-sizing:border-box; }
.rm-saved-addr{display:flex;align-items:flex-start;justify-content:space-between;gap:10px;background:#f4faf5;border:1px solid #d9ecdb;border-radius:10px;padding:10px 12px}
.rm-saved-addr-text{font-size:13px;color:#333;line-height:1.5}
.rm-saved-addr-change{flex-shrink:0;background:none;border:1px solid #4CAF50;color:#4CAF50;border-radius:8px;padding:5px 11px;font-size:12px;font-weight:600;cursor:pointer;white-space:nowrap}
.rm-saved-addr-change:hover{background:#4CAF50;color:#fff}

/* Price calc */
.rm-price-box { background:#f8faf8; border:1px solid #e0e0e0; border-radius:10px; padding:12px 14px; margin-top:14px; }
.rm-price-line { display:flex; justify-content:space-between; font-size:12.5px; padding:3px 0; color:#444; }
.rm-price-line.rm-total { border-top:1px dashed #ccc; margin-top:6px; padding-top:8px; font-weight:800; font-size:14.5px; color:#1b4332; }
.rm-confirm-btn { width:100%; margin-top:16px; background:#2E7D32; color:#fff; border:none; border-radius:9px; padding:12px; font-weight:800; font-size:14.5px; cursor:pointer; }
.rm-confirm-btn:hover { background:#1b5e20; }

/* Equipment details modal */
.rm-view-details-link { display:inline-flex; align-items:center; gap:5px; font-size:12px; font-weight:700; color:#2E7D32; cursor:pointer; margin:2px 0 6px; }
.rm-view-details-link:hover { text-decoration:underline; }
.rm-specs-grid { display:grid; grid-template-columns:1fr 1fr; gap:8px; }
.rm-spec-item { background:#f8faf8; border:1px solid #eee; border-radius:8px; padding:8px 10px; font-size:12px; color:#666; display:flex; flex-direction:column; gap:2px; }
.rm-spec-item strong { font-size:13px; color:#1b4332; }
.rm-details-modal .reviews-list { max-height:160px; overflow-y:auto; }

/* Tracking */
.track-live-badge { display:inline-flex; align-items:center; gap:5px; background:#e3f2fd; color:#1565c0; padding:2px 9px; border-radius:20px; font-size:11px; font-weight:700; margin-top:3px; }
.rm-track-steps { display:flex; align-items:center; margin:18px 0; }
.rm-track-step { display:flex; flex-direction:column; align-items:center; flex:1; text-align:center; }
.rm-track-dot { width:30px; height:30px; border-radius:50%; background:#eee; color:#999; display:flex; align-items:center; justify-content:center; font-size:12px; }
.rm-track-step.done .rm-track-dot { background:#2E7D32; color:#fff; }
.rm-track-step.current .rm-track-dot { background:#FFC107; color:#fff; box-shadow:0 0 0 4px rgba(255,193,7,0.3); }
.rm-track-label { font-size:10.5px; margin-top:5px; color:#444; font-weight:600; }
.rm-track-line { flex:0.6; height:2px; background:#ddd; margin-bottom:18px; }
.rm-track-info { display:flex; flex-direction:column; gap:7px; background:#f8faf8; border-radius:10px; padding:12px 14px; font-size:13px; }
.rm-track-info div { display:flex; justify-content:space-between; }
.rm-track-info span { color:#777; }
.rm-track-map { position:relative; height:60px; margin-top:14px; background:repeating-linear-gradient(90deg,#e8f5e9,#e8f5e9 10px,#dcedc8 10px,#dcedc8 20px); border-radius:10px; overflow:hidden; }
.rm-track-pin { position:absolute; top:50%; left:62%; transform:translate(-50%,-50%); color:#c62828; font-size:20px; animation:pinBounce 1.4s infinite; }
@keyframes pinBounce { 0%,100%{ transform:translate(-50%,-58%);} 50%{ transform:translate(-50%,-46%);} }

/* Owner dashboard */
.rm-dash-modal { max-width:560px; }
.rm-dash-stats { display:grid; grid-template-columns:repeat(2,1fr); gap:10px; margin:10px 0 14px; }
.rm-dash-stat { background:#f1f8f2; border-radius:10px; padding:12px; text-align:center; }
.rm-dash-stat strong { display:block; font-size:18px; color:#1b4332; }
.rm-dash-stat span { font-size:11px; color:#666; }
.rm-dash-table { width:100%; border-collapse:collapse; font-size:12px; margin-top:6px; }
.rm-dash-table th { text-align:left; background:#f1f8f2; padding:8px; color:#1b4332; }
.rm-dash-table td { padding:8px; border-bottom:1px solid #eee; }

/* ── Real product image ── */
.product-img-real { width:100%; height:160px; overflow:hidden; border-radius:10px 10px 0 0; background:#f1f1f1; }
.product-img-real img { width:100%; height:100%; object-fit:cover; transition:transform .3s; }
.product-card:hover .product-img-real img { transform:scale(1.04); }

/* ── Compare checkbox ── */
.compare-checkbox-wrap { position:absolute; top:10px; left:10px; z-index:5; background:rgba(255,255,255,0.92); border-radius:20px; padding:3px 10px; font-size:11.5px; font-weight:700; color:#1b4332; display:flex; align-items:center; gap:5px; cursor:pointer; box-shadow:0 1px 4px rgba(0,0,0,0.12); }
.compare-checkbox-wrap input { accent-color:#2E7D32; }

/* ── Card button rows ── */
.card-btn-row { display:flex; gap:6px; margin-top:7px; }
.card-btn-row .add-btn, .card-btn-row .track-btn, .card-btn-row .review-btn { flex:1; }

/* ── WhatsApp button ── */
.whatsapp-btn { flex:1; display:flex; align-items:center; justify-content:center; gap:5px; background:#25D366; color:#fff; border-radius:8px; padding:8px; font-weight:700; font-size:13px; text-decoration:none; }
.whatsapp-btn:hover { background:#1ebe5d; }

/* ── Review button ── */
.review-btn { background:#fff8e1; color:#e65100; border:1.5px solid #ffcc80; border-radius:8px; padding:8px; font-weight:700; font-size:12.5px; cursor:pointer; }
.review-btn:hover { background:#fff3cd; }

/* ── Nearby badge on card ── */
.nearby-badge { background:#e8f5e9; color:#2E7D32; padding:1px 7px; border-radius:20px; font-size:10.5px; font-weight:700; }

/* ── Review stars mini ── */
.review-stars-mini { margin-top:5px; display:flex; align-items:center; gap:2px; }

/* ── Review modal ── */
.reviews-list { display:flex; flex-direction:column; gap:10px; max-height:200px; overflow-y:auto; }
.review-item { background:#f8faf8; border-radius:10px; padding:10px 12px; }
.review-stars { margin-bottom:3px; }
.review-text { font-size:13px; color:#333; font-style:italic; }
.review-user { font-size:11px; color:#2E7D32; margin-top:3px; font-weight:700; }
.review-verified-badge { color:#1565c0; font-weight:700; margin-left:6px; }
.rating-breakdown { margin-bottom:14px; }
.rb-row { display:flex; align-items:center; gap:8px; font-size:11.5px; color:#666; margin-bottom:3px; }
.rb-row .rb-label { width:32px; flex-shrink:0; }
.rb-row .rb-track { flex:1; background:#eee; border-radius:6px; height:7px; overflow:hidden; }
.rb-row .rb-fill { background:#FFC107; height:100%; }
.rb-row .rb-count { width:22px; text-align:right; flex-shrink:0; color:#999; }

/* ── Nearby modal ── */
.nearby-item { display:flex; align-items:center; gap:10px; padding:10px 0; border-bottom:1px solid #eee; }

/* ── Compare table ── */
.rm-cmp-modal { max-width:640px; }
.cmp-table { width:100%; border-collapse:collapse; font-size:12.5px; }
.cmp-table th { background:#f1f8f2; padding:10px 8px; text-align:center; border-bottom:2px solid #c8e6c9; color:#1b4332; }
.cmp-table td { padding:9px 8px; text-align:center; border-bottom:1px solid #eee; }
.cmp-label { text-align:left; font-weight:700; color:#444; background:#fafafa; }
.cmp-img-wrap { text-align:center; margin-bottom:6px; }

@media (max-width:480px){
    .card-btn-row { flex-direction:column; }
    .product-img-real { height:130px; }
}
</style>
<?php include __DIR__ . '/krishimitra_widget.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>