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
$eqResult = $conn->query("
    SELECT e.id, e.name, e.name_mr, e.type, e.image, e.rent_per_day,
           e.hp, e.engine, e.gears, e.lift_capacity, e.description,
           e.owner_name, e.owner_phone, e.owner_verified,
           c.name AS city_name,
           COALESCE((SELECT AVG(r.rating) FROM reviews r WHERE r.item_type='equipment' AND r.item_id = e.id), 0) AS avg_rating,
           (SELECT COUNT(*) FROM equipment_bookings b WHERE b.equipment_id = e.id AND b.status = 'completed') AS trips_done
    FROM equipment e
    LEFT JOIN cities c ON c.id = e.city_id
    WHERE e.availability = 1
    ORDER BY e.id
");
if ($eqResult) {
    while ($row = $eqResult->fetch_assoc()) {
        $equipmentRows[] = $row;
    }
}

$typeMeta = [
    'tractor'   => ['cat' => 'tractors',   'emoji' => '🚜'],
    'harvester' => ['cat' => 'harvesters', 'emoji' => '🌾'],
    'drone'     => ['cat' => 'drones',     'emoji' => '🛸'],
    'pump'      => ['cat' => 'tractors',   'emoji' => '⚙️'],
    'other'     => ['cat' => 'tractors',   'emoji' => '⚙️'],
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
        'nameHi'      => $row['name_mr'] ?: $row['name'],
        'img'         => $base_path . '/' . ($row['image'] ?: 'assets/images/equipment.png'),
        'imgFallback' => $base_path . '/assets/images/equipment.png',
        'emoji'       => $meta['emoji'],
        'price'       => (float)$row['rent_per_day'],
        'cat'         => $meta['cat'],
        'unitMr'      => 'दिवस', 'unitEn' => 'day', 'unitHi' => 'दिन',
        'badgeMr'     => null, 'badgeEn' => null, 'badgeHi' => null,
        'owner'       => $row['owner_name'] ?: 'AgriCart Partner',
        'phone'       => $row['owner_phone'] ?: '',
        'verified'    => (bool)$row['owner_verified'],
        'rating'      => round((float)$row['avg_rating'], 1),
        'trips'       => (int)$row['trips_done'],
        'village'     => $row['city_name'] ?: 'Maharashtra',
        'distKm'      => round(2 + (((int)$row['id']) % 10) * 1.3, 1),
        'specs'       => ['hp' => $row['hp'] ?: '-', 'engine' => $row['engine'] ?: '-', 'gears' => $row['gears'] ?: '-', 'lift' => $row['lift_capacity'] ?: '-'],
        'desc'        => $row['description'] ?: '',
        'reviewsMr'   => $reviewTexts, 'reviewsEn' => $reviewTexts, 'reviewsHi' => $reviewTexts,
        'reviewCount' => $reviewCount,
    ];
}

// ── Real booked date ranges (blocks calendar dates that are pending/confirmed/on the way) ──
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
    <div class="stat-item"><h3>150+</h3><p id="st1">Modern Machines</p></div>
    <div class="stat-item"><h3>50+</h3><p id="st2">Verified Drivers</p></div>
    <div class="stat-item"><h3>Under 1 hr</h3><p id="st3">Quick Delivery</p></div>
    <div class="stat-item"><h3 style="color:#FFC107">4.9 ★</h3><p id="st4">Driver Rating</p></div>
</div>

<div class="store-layout">
    <aside class="sidebar">
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
        st1: "Modern Machines", st2: "Verified Drivers", st3: "Quick Delivery", st4: "Driver Rating",
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
        bookingSuccess: "Payment successful! Booking confirmed.", selectDatesAlert: "Please select start and end dates.",
        trackModalTitle: "Live Tracking", trackStep1: "Booking Confirmed", trackStep2: "Equipment Dispatched", trackStep3: "On the Way", trackStep4: "Delivered to Farm",
        trackEta: "Estimated Arrival", trackDriver: "Driver", trackVehicle: "Vehicle",
        ownerDashTitle: "Owner Dashboard (Demo)", odEarnings: "Total Earnings", odBookings: "Active Bookings", odEquip: "Listed Equipment", odRating: "Avg Rating",
        odMyEquip: "My Equipment", odStatus: "Status", odBookedOn: "Next Booking", odEarn: "Earnings"
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
        st1: "आधुनिक अवजारे", st2: "प्रमाणित चालक", st3: "जलद वितरण", st4: "चालक रेटिंग",
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
        bookingSuccess: "पेमेंट यशस्वी! बुकिंग निश्चित झाली.", selectDatesAlert: "कृपया सुरुवातीची आणि शेवटची तारीख निवडा.",
        trackModalTitle: "लाइव्ह ट्रॅकिंग", trackStep1: "बुकिंग निश्चित", trackStep2: "अवजार पाठवले", trackStep3: "वाटेत आहे", trackStep4: "शेतावर पोहोचले",
        trackEta: "अंदाजे आगमन वेळ", trackDriver: "चालक", trackVehicle: "वाहन",
        ownerDashTitle: "मालक डॅशबोर्ड (डेमो)", odEarnings: "एकूण कमाई", odBookings: "सक्रिय बुकिंग्स", odEquip: "सूचीबद्ध अवजारे", odRating: "सरासरी रेटिंग",
        odMyEquip: "माझी अवजारे", odStatus: "स्थिती", odBookedOn: "पुढील बुकिंग", odEarn: "कमाई"
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
        st1: "आधुनिक मशीनें", st2: "प्रमाणित चालक", st3: "त्वरित डिलीवरी", st4: "चालक रेटिंग",
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
        bookingSuccess: "भुगतान सफल! बुकिंग की पुष्टि हो गई।", selectDatesAlert: "कृपया शुरुआती और अंतिम तारीख चुनें.",
        trackModalTitle: "लाइव ट्रैकिंग", trackStep1: "बुकिंग पुष्ट", trackStep2: "उपकरण भेजा गया", trackStep3: "रास्ते में है", trackStep4: "खेत पर पहुंचा",
        trackEta: "अनुमानित आगमन", trackDriver: "चालक", trackVehicle: "वाहन",
        ownerDashTitle: "मालिक डैशबोर्ड (डेमो)", odEarnings: "कुल कमाई", odBookings: "सक्रिय बुकिंग", odEquip: "सूचीबद्ध उपकरण", odRating: "औसत रेटिंग",
        odMyEquip: "मेरे उपकरण", odStatus: "स्थिति", odBookedOn: "अगली बुकिंग", odEarn: "कमाई"
    }
};

// ── Real data from the database (equipment table), injected by PHP ──
const MACHINERY = <?php echo $machineryJson; ?>;
const BOOKED_DATES = <?php echo $bookedDatesJson; ?>;
const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;

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

    document.getElementById('st1').textContent = rt.st1;
    document.getElementById('st2').textContent = rt.st2;
    document.getElementById('st3').textContent = rt.st3;
    document.getElementById('st4').textContent = rt.st4;

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
                <div class="rm-view-details-link" onclick="openDetailsModal(${m.id})"><i class="fa-solid fa-circle-info"></i> ${rt.detailsBtn}</div>
                <div class="owner-row">
                    <span class="owner-name"><i class="fa-solid fa-user"></i> ${m.owner}</span>
                    ${ownerBadgeHtml}
                </div>
                <div class="owner-rating-row">
                    <span><i class="fa-solid fa-star" style="color:#FFC107"></i> ${m.rating}</span>
                    <span style="color:#999">• ${m.trips} ${rt.tripsDone}</span>
                    <span class="nearby-badge"><i class="fa-solid fa-location-dot"></i> ${m.distKm} ${rt.nearbyKm}</span>
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

function loadEquipmentReviews(id) {
    fetch('get_reviews.php?item_type=equipment&item_id=' + id)
    .then(r => r.json())
    .then(data => {
        const el = document.getElementById('eqReviewsList');
        if (!el) return;
        if (data.count === 0) {
            el.innerHTML = '<p style="color:#999;font-size:13px">अजून कोणतंही review नाही — पहिलं तुम्ही द्या!</p>';
        } else {
            el.innerHTML = data.reviews.map(r => `
                <div class="review-item">
                    <div class="review-stars">${renderStarsMini(r.rating)}</div>
                    <div class="review-text">"${(r.comment || '').replace(/</g,'&lt;')}"</div>
                    <div class="review-user"><i class="fa-solid fa-user-check"></i> ${r.name} · <span style="color:#999">${r.date}</span></div>
                </div>`).join('');
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

let bookingState = { machineId: null, calMonth: new Date(), start: null, end: null, hours: 1 };

function findMachine(id){ return MACHINERY.find(m => m.id === id); }

function openBookingModal(id) {
    const m = findMachine(id);
    if (!m) return;
    const rt = RentalT[window.lang || 'en'];
    bookingState = { machineId: id, calMonth: new Date(), start: null, end: null, hours: 1 };

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

        <div class="rm-form-row">
            <label>${rt.locationLabel}</label>
            <input type="text" placeholder="${rt.locationPh}" id="locationInput">
        </div>

        <div class="rm-price-box">
            <h4 class="rm-section-title"><i class="fa-solid fa-calculator"></i> ${rt.priceCalcTitle}</h4>
            <div class="rm-price-line"><span>${rt.baseRate}</span><span>₹${m.price} / ${unit}</span></div>
            <div class="rm-price-line"><span>${rt.duration}</span><span id="calcDuration">1 ${unit}</span></div>
            <div class="rm-price-line"><span>${rt.subtotal}</span><span id="calcSubtotal">₹${m.price}</span></div>
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
        <p style="font-size:13px;color:#444;line-height:1.6;margin:0 0 4px">${(m.desc && m.desc.trim() !== '') ? m.desc : buildAutoDescription(m, rt)}</p>
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
    const subtotal = m.price * dayMultiplier;
    const fee = Math.round(subtotal * 0.05);
    const total = subtotal + fee;

    const durEl = document.getElementById('calcDuration');
    const subEl = document.getElementById('calcSubtotal');
    const feeEl = document.getElementById('calcFee');
    const totEl = document.getElementById('calcTotal');
    if (durEl) durEl.textContent = `${dayMultiplier} ${unit}`;
    if (subEl) subEl.textContent = `₹${subtotal}`;
    if (feeEl) feeEl.textContent = `₹${fee}`;
    if (totEl) totEl.textContent = `₹${total}`;
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
            delivery_address: address
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

        el.innerHTML = `
            <div class="rm-track-steps">${stepsHtml}</div>
            <div class="rm-track-info">
                <div><span>Booking ID</span><strong>${data.booking_number}</strong></div>
                <div><span>${rt.trackDriver}</span><strong>${m.owner}</strong></div>
                <div><span>Dates</span><strong>${data.from_date} → ${data.to_date}</strong></div>
                <div><span>Total</span><strong>₹${data.total_amount}</strong></div>
            </div>`;
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