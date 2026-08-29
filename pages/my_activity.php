<?php
// =====================================================
// AgriCart — "My Activity" page
// One place for a logged-in farmer to see everything they've done:
//   - Marketplace Orders   (name of item + Order ID + image + tracking)
//   - Rental Bookings      (equipment name + Booking ID + image + tracking)
//   - Advisory Requests    (question asked to an expert + Request ID + photo + reply)
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($base_path)) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_path = rtrim(dirname($scriptDir), '/');
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = (int)($_SESSION['user_id'] ?? 0);

$defaultProductImage  = 'assets/images/products/default.jpg';
$defaultEquipImage    = 'assets/images/equipment.png';
$defaultAdvisoryImage = 'assets/images/advisory.png';

function myactivity_resolve_img($path, $default, $base_path) {
    $p = !empty($path) ? $path : $default;
    if (preg_match('#^(https?:)?//#i', $p) || strpos($p, '/') === 0) {
        return $p;
    }
    return rtrim($base_path, '/') . '/' . ltrim($p, '/');
}

$ordersForJs   = [];
$rentalsForJs  = [];
$advisoryForJs = [];
$isOwner       = false;
$isSeller      = false;

if ($isLoggedIn) {
    // Does this user own any self-listed rental equipment? If so, show a
    // link to their Owner dashboard where they can manage/cancel bookings.
    $ownCheck = $conn->prepare("SELECT id FROM equipment WHERE owner_user_id = ? LIMIT 1");
    if ($ownCheck) {
        $ownCheck->bind_param("i", $userId);
        $ownCheck->execute();
        $isOwner = (bool)$ownCheck->get_result()->fetch_assoc();
    }

    // Has this user listed any product for sale? If so, show a link to
    // their Seller Dashboard (stock, orders, earnings, reviews, etc.)
    // — visible only to actual sellers, nowhere in the main site nav.
    $sellCheck = $conn->prepare("SELECT id FROM products WHERE added_by_user_id = ? LIMIT 1");
    if ($sellCheck) {
        $sellCheck->bind_param("i", $userId);
        $sellCheck->execute();
        $isSeller = (bool)$sellCheck->get_result()->fetch_assoc();
    }

    // ── Orders (marketplace) ──────────────────────────────────────
    $stmt = $conn->prepare(
        "SELECT id, order_number, total_amount, payment_mode, payment_status, order_status, created_at,
                delivery_name, delivery_mobile, delivery_address
         FROM orders WHERE user_id = ? ORDER BY id DESC"
    );
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($o = $res->fetch_assoc()) {
        $itemStmt = $conn->prepare(
            "SELECT oi.product_name, oi.price, oi.quantity, oi.subtotal, p.image
             FROM order_items oi LEFT JOIN products p ON p.id = oi.product_id
             WHERE oi.order_id = ?"
        );
        $itemStmt->bind_param("i", $o['id']);
        $itemStmt->execute();
        $ir = $itemStmt->get_result();
        $items = [];
        while ($it = $ir->fetch_assoc()) {
            $items[] = [
                'name'     => $it['product_name'],
                'price'    => (float)$it['price'],
                'qty'      => (int)$it['quantity'],
                'subtotal' => (float)$it['subtotal'],
                'img'      => myactivity_resolve_img($it['image'], $defaultProductImage, $base_path),
            ];
        }
        // COD orders don't get an explicit "paid" update from anywhere once
        // delivered — the money is collected in person, not through the
        // payment gateway. So once a COD order reaches "delivered", treat
        // its payment as settled for display purposes, even if the DB
        // column still says 'unpaid' (admin panel doesn't touch it).
        $effectivePaymentStatus = $o['payment_status'];
        if (strtolower($o['payment_mode']) === 'cod' && $o['order_status'] === 'delivered') {
            $effectivePaymentStatus = 'paid';
        }

        $ordersForJs[] = [
            'id'            => (int)$o['id'],
            'orderNumber'   => $o['order_number'],
            'name'          => $items[0]['name'] ?? 'Order',
            'extraCount'    => max(0, count($items) - 1),
            'img'           => $items[0]['img'] ?? myactivity_resolve_img(null, $defaultProductImage, $base_path),
            'total'         => (float)$o['total_amount'],
            'paymentMode'   => strtoupper($o['payment_mode']),
            'paymentStatus' => $effectivePaymentStatus,
            'status'        => $o['order_status'],
            'createdAt'     => $o['created_at'],
            'deliveryName'  => $o['delivery_name'],
            'deliveryMobile'=> $o['delivery_mobile'],
            'deliveryAddress' => $o['delivery_address'],
            'items'         => $items,
        ];
    }

    // ── Rentals (equipment bookings) ──────────────────────────────
    $stmt = $conn->prepare(
        "SELECT eb.id, eb.booking_number, eb.from_date, eb.to_date, eb.total_amount, eb.status, eb.payment_status, eb.total_days, eb.total_hours,
                e.name, e.name_mr, e.image, e.owner_name
         FROM equipment_bookings eb
         JOIN equipment e ON e.id = eb.equipment_id
         WHERE eb.user_id = ? ORDER BY eb.id DESC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($r = $res->fetch_assoc()) {
            $rentalsForJs[] = [
                'id'            => (int)$r['id'],
                'bookingNumber' => $r['booking_number'],
                'nameEn'        => $r['name'],
                'nameMr'        => $r['name_mr'] ?: $r['name'],
                'img'           => myactivity_resolve_img($r['image'], $defaultEquipImage, $base_path),
                'fromDate'      => $r['from_date'],
                'toDate'        => $r['to_date'],
                'total'         => (float)$r['total_amount'],
                'status'        => $r['status'],
                'paymentStatus' => $r['payment_status'] ?: 'pending',
                'totalDays'     => $r['total_days'] ? (int)$r['total_days'] : null,
                'totalHours'    => $r['total_hours'] ? (int)$r['total_hours'] : null,
                'owner'         => $r['owner_name'] ?: 'AgriCart Partner',
            ];
        }
    }

    // ── Advisory requests (expert Q&A) ────────────────────────────
    $stmt = $conn->prepare(
        "SELECT id, request_number, crop, subject, message, image, status, admin_reply, created_at
         FROM advisory_requests WHERE user_id = ? ORDER BY id DESC"
    );
    if ($stmt) {
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($a = $res->fetch_assoc()) {
            $advisoryForJs[] = [
                'id'            => (int)$a['id'],
                'requestNumber' => $a['request_number'],
                'crop'          => $a['crop'],
                'subject'       => $a['subject'],
                'message'       => $a['message'],
                'img'           => !empty($a['image']) ? myactivity_resolve_img($a['image'], $defaultAdvisoryImage, $base_path) : null,
                'status'        => $a['status'],
                'adminReply'    => $a['admin_reply'],
                'createdAt'     => $a['created_at'],
            ];
        }
    }
}

$ordersJson   = json_encode($ordersForJs, JSON_UNESCAPED_UNICODE);
$rentalsJson  = json_encode($rentalsForJs, JSON_UNESCAPED_UNICODE);
$advisoryJson = json_encode($advisoryForJs, JSON_UNESCAPED_UNICODE);

include __DIR__ . '/../includes/header.php';
?>

<div class="ma-hero">
    <div class="ma-hero-inner">
        <h1 id="maTitle"><i class="fa-solid fa-clipboard-list"></i> My Activity</h1>
        <p id="maSub">All your Orders, Rentals and Advisory requests — with name, ID, photo and status, in one place.</p>
    </div>
</div>

<div class="ma-wrap">
<?php if (!$isLoggedIn): ?>
    <div class="ma-login-card">
        <i class="fa-solid fa-lock"></i>
        <p id="maLoginMsg">Please login to see your Orders, Rentals and Advisory requests.</p>
        <a href="<?php echo $base_path; ?>/pages/login.php" class="ma-btn-primary" id="maLoginBtn">Login</a>
    </div>
<?php else: ?>
    <div class="ma-tabs">
        <button type="button" class="ma-tab-btn active" id="maTabBtn-orders" onclick="maShowTab('orders')">
            <i class="fa-solid fa-cart-shopping"></i> <span id="maTabOrders">Orders</span> <span class="ma-count"><?php echo count($ordersForJs); ?></span>
        </button>
        <button type="button" class="ma-tab-btn" id="maTabBtn-rentals" onclick="maShowTab('rentals')">
            <i class="fa-solid fa-tractor"></i> <span id="maTabRentals">Rentals</span> <span class="ma-count"><?php echo count($rentalsForJs); ?></span>
        </button>
        <button type="button" class="ma-tab-btn" id="maTabBtn-advisory" onclick="maShowTab('advisory')">
            <i class="fa-solid fa-user-doctor"></i> <span id="maTabAdvisory">Advisory</span> <span class="ma-count"><?php echo count($advisoryForJs); ?></span>
        </button>
    </div>

    <?php if ($isOwner): ?>
    <a href="owner_bookings.php" class="ma-owner-link"><i class="fa-solid fa-tractor"></i> तुमच्या equipment वरील Bookings मॅनेज करा (Owner) <i class="fa-solid fa-arrow-right"></i></a>
    <?php endif; ?>

    <div class="ma-panel" id="maPanel-orders"></div>
    <div class="ma-panel" id="maPanel-rentals" style="display:none"></div>
    <div class="ma-panel" id="maPanel-advisory" style="display:none">
        <button type="button" class="ma-btn-primary ma-request-btn" onclick="maOpenRequestModal()">
            <i class="fa-solid fa-plus"></i> <span id="maRequestBtnLbl">Request Expert Advice</span>
        </button>
        <div id="maAdvisoryList"></div>
    </div>

    <!-- Request Expert Advice modal -->
    <div class="ma-modal-overlay" id="maRequestModalOverlay" onclick="if(event.target===this) maCloseRequestModal()">
        <div class="ma-modal-box">
            <button class="ma-modal-close" onclick="maCloseRequestModal()"><i class="fa-solid fa-xmark"></i></button>
            <h2 id="maRequestTitle"><i class="fa-solid fa-user-doctor"></i> Request Expert Advice</h2>
            <form id="maRequestForm" onsubmit="maSubmitRequest(event)">
                <label id="maLblCrop">Crop</label>
                <input type="text" id="maCrop" required placeholder="e.g. Cotton, Tomato...">
                <label id="maLblSubject">Subject</label>
                <input type="text" id="maSubject" required placeholder="e.g. Yellow leaves, pest attack...">
                <label id="maLblMessage">Your question</label>
                <textarea id="maMessage" rows="4" required placeholder="तुमचा प्रश्न सविस्तर लिहा..."></textarea>
                <label id="maLblImage">Photo (optional)</label>
                <input type="file" id="maImage" accept="image/png,image/jpeg,image/webp">
                <button type="submit" class="ma-btn-primary" id="maSubmitBtn"><i class="fa-solid fa-paper-plane"></i> Submit</button>
            </form>
        </div>
    </div>

    <!-- Order Details modal -->
    <div class="ma-modal-overlay" id="maOrderModalOverlay" onclick="if(event.target===this) maCloseOrderModal()">
        <div class="ma-modal-box ma-detail-modal">
            <button class="ma-modal-close" onclick="maCloseOrderModal()"><i class="fa-solid fa-xmark"></i></button>
            <div id="maOrderModalBody"></div>
        </div>
    </div>

    <!-- Rental Booking Details modal -->
    <div class="ma-modal-overlay" id="maRentalModalOverlay" onclick="if(event.target===this) maCloseRentalModal()">
        <div class="ma-modal-box ma-detail-modal">
            <button class="ma-modal-close" onclick="maCloseRentalModal()"><i class="fa-solid fa-xmark"></i></button>
            <div id="maRentalModalBody"></div>
        </div>
    </div>
<?php endif; ?>
</div>

<div class="ma-toast" id="maToast"><i class="fa-solid fa-circle-check"></i> <span id="maToastMsg"></span></div>

<style>
:root{
    --ma-primary:#2F4F44;
    --ma-primary-dark:#213B33;
    --ma-accent:#A98B4A;
    --ma-warning:#8A6D3B;
    --ma-warning-bg:#F3EEE2;
    --ma-danger:#9B3B37;
    --ma-danger-bg:#F5E8E7;
    --ma-bg-soft:#EEF1EC;
    --ma-text:#26292B;
    --ma-muted:#68706B;
    --ma-border:#E0E2DD;
}
.ma-hero{background:linear-gradient(135deg,var(--ma-primary-dark),var(--ma-primary));padding:46px 20px;color:#fff;text-align:center}
.ma-hero-inner{max-width:800px;margin:0 auto}
.ma-hero h1{font-size:28px;margin:0 0 8px;display:flex;align-items:center;justify-content:center;gap:10px}
.ma-hero p{font-size:14px;opacity:.9;margin:0}
.ma-wrap{max-width:900px;margin:26px auto 60px;padding:0 16px}
.ma-login-card{text-align:center;background:#fff;border:1px solid var(--ma-border);border-radius:14px;padding:50px 20px;color:var(--ma-muted)}
.ma-login-card i{font-size:34px;color:var(--ma-primary);margin-bottom:14px;display:block}
.ma-btn-primary{display:inline-flex;align-items:center;gap:8px;background:var(--ma-primary);color:#fff;border:none;padding:11px 22px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:14px;transition:background .15s ease}
.ma-btn-primary:hover{background:var(--ma-primary-dark)}
.ma-tabs{display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap}
.ma-tab-btn{flex:1;min-width:140px;background:#fff;border:1px solid var(--ma-border);color:var(--ma-muted);padding:12px 14px;border-radius:10px;font-size:13.5px;font-weight:600;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:8px;transition:all .15s ease}
.ma-tab-btn.active{background:var(--ma-primary);border-color:var(--ma-primary);color:#fff}
.ma-tab-btn:hover:not(.active){background:var(--ma-bg-soft)}
.ma-count{background:rgba(255,255,255,0.25);border-radius:20px;padding:1px 8px;font-size:11.5px}
.ma-owner-link{display:flex;align-items:center;justify-content:center;gap:8px;background:var(--ma-bg-soft);border:1px dashed var(--ma-accent);color:var(--ma-primary-dark);padding:11px 14px;border-radius:10px;font-size:13px;font-weight:600;text-decoration:none;margin-bottom:20px}
.ma-owner-link:hover{background:#e6e9e2}
.ma-cancel-btn{margin-top:10px;padding:8px 16px;background:#fff;border:1.5px solid var(--ma-danger);color:var(--ma-danger);border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.ma-cancel-btn:hover{background:var(--ma-danger-bg)}
.ma-cancel-btn:disabled{opacity:.55;cursor:not-allowed}
.ma-tab-btn:not(.active) .ma-count{background:var(--ma-bg-soft);color:var(--ma-primary)}
.ma-empty{text-align:center;color:var(--ma-muted);padding:50px 10px;font-size:13.5px}
.ma-empty i{font-size:30px;display:block;margin-bottom:10px;opacity:.6}

.ma-card{background:#fff;border:1px solid var(--ma-border);border-radius:12px;padding:16px;margin-bottom:14px;display:flex;gap:14px;transition:box-shadow .15s ease, transform .15s ease}
.ma-card-clickable{cursor:pointer}
.ma-card-clickable:hover{box-shadow:0 6px 18px rgba(40,60,50,.12);transform:translateY(-2px)}
.ma-click-hint{margin-top:10px;font-size:11px;color:var(--ma-accent);display:flex;align-items:center;gap:6px;opacity:.85}
.ma-card-img{width:74px;height:74px;border-radius:10px;object-fit:cover;flex-shrink:0;background:var(--ma-bg-soft)}
.ma-card-body{flex:1;min-width:0}
.ma-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
.ma-card-name{font-size:15px;font-weight:700;color:var(--ma-text);margin:0}
.ma-card-extra{font-size:12px;color:var(--ma-muted);font-weight:400}
.ma-card-id{font-size:12px;color:var(--ma-muted);margin-top:2px}
.ma-card-id strong{color:var(--ma-primary-dark)}
.ma-pill{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.ma-pill.st-placed,.ma-pill.st-pending{background:var(--ma-warning-bg);color:var(--ma-warning)}
.ma-pill.st-packed,.ma-pill.st-confirmed{background:#E7EEFB;color:#2E4E8C}
.ma-pill.st-shipped,.ma-pill.st-on_the_way{background:#EAF1E9;color:#3E6B3F}
.ma-pill.st-delivered,.ma-pill.st-completed,.ma-pill.st-answered{background:#E8F3EA;color:#2F6B3A}
.ma-pill.st-cancelled,.ma-pill.st-closed{background:var(--ma-danger-bg);color:var(--ma-danger)}
.ma-card-meta{font-size:12.5px;color:var(--ma-muted);margin-top:8px;display:flex;gap:14px;flex-wrap:wrap}
.ma-card-meta b{color:var(--ma-text)}
.ma-track-line{display:flex;justify-content:space-between;position:relative;margin:16px 0 4px}
.ma-track-line::before{content:'';position:absolute;top:10px;left:12.5%;right:12.5%;height:3px;background:#eee;z-index:0}
.ma-track-line-fill{position:absolute;top:10px;left:12.5%;height:3px;background:var(--ma-primary);z-index:0;width:0%;transition:width .5s ease}
.ma-track-step{flex:1;text-align:center;font-size:10px;color:#999;position:relative;z-index:1}
.ma-track-step .dot{width:20px;height:20px;border-radius:50%;background:#eee;color:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto 5px;font-size:10px;transition:background .3s ease, box-shadow .3s ease}
.ma-track-step.done .dot{background:var(--ma-primary)}
.ma-track-step.done{color:var(--ma-primary);font-weight:600}
.ma-track-step.current .dot{box-shadow:0 0 0 4px rgba(47,79,68,.18)}
.ma-reply-box{margin-top:10px;background:var(--ma-bg-soft);border-radius:8px;padding:10px 12px;font-size:12.5px;color:var(--ma-text)}
.ma-reply-box b{color:var(--ma-primary-dark);display:block;margin-bottom:3px}
.ma-msg-box{margin-top:8px;font-size:12.5px;color:var(--ma-muted);background:#fafafa;border-radius:8px;padding:8px 10px}

.ma-request-btn{width:100%;justify-content:center;margin-bottom:18px}
.ma-modal-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1600;align-items:flex-start;justify-content:center;padding:30px 14px;overflow-y:auto}
.ma-modal-overlay.open{display:flex}
.ma-modal-box{background:#fff;border-radius:14px;max-width:520px;width:100%;padding:26px;position:relative}
.ma-modal-close{position:absolute;top:14px;right:14px;background:#f2f2f2;border:none;width:32px;height:32px;border-radius:50%;cursor:pointer}
.ma-modal-box h2{font-size:18px;margin:0 0 16px;display:flex;align-items:center;gap:8px;color:var(--ma-primary-dark)}
#maRequestForm label{display:block;font-size:12.5px;font-weight:600;color:var(--ma-muted);margin:12px 0 5px}
#maRequestForm input[type="text"],#maRequestForm textarea,#maRequestForm input[type="file"]{width:100%;padding:10px;border:1px solid var(--ma-border);border-radius:8px;font-size:13.5px;font-family:inherit}
#maRequestForm textarea{resize:vertical}
#maRequestForm .ma-btn-primary{width:100%;justify-content:center;margin-top:18px}

.ma-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:var(--ma-primary-dark);color:#fff;padding:12px 20px;border-radius:10px;font-size:13.5px;display:flex;align-items:center;gap:8px;opacity:0;transition:all .3s ease;z-index:2000}
.ma-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}

.ma-detail-modal{max-width:560px}
.ma-detail-head{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;margin-bottom:14px;padding-right:38px}
.ma-detail-id{font-size:12.5px;color:var(--ma-muted);margin-top:3px}
.ma-detail-id strong{color:var(--ma-primary-dark)}
.ma-detail-section{border-top:1px solid var(--ma-border);padding-top:12px;margin-top:12px}
.ma-detail-section h4{font-size:12.5px;text-transform:uppercase;letter-spacing:.03em;color:var(--ma-muted);margin:0 0 10px;display:flex;align-items:center;gap:6px}
.ma-detail-item{display:flex;align-items:center;gap:10px;margin-bottom:10px}
.ma-detail-item img{width:46px;height:46px;border-radius:8px;object-fit:cover;background:var(--ma-bg-soft);flex-shrink:0}
.ma-detail-item-name{font-size:13px;font-weight:600;color:var(--ma-text)}
.ma-detail-item-meta{font-size:11.5px;color:var(--ma-muted)}
.ma-detail-item-sub{margin-left:auto;font-weight:700;font-size:13px;color:var(--ma-primary-dark)}
.ma-detail-row{display:flex;justify-content:space-between;font-size:13px;color:var(--ma-text);padding:5px 0}
.ma-detail-row b{color:var(--ma-primary-dark)}
.ma-detail-total{border-top:1px dashed var(--ma-border);margin-top:6px;padding-top:10px;font-size:15px;font-weight:700;display:flex;justify-content:space-between;color:var(--ma-primary-dark)}
.ma-detail-address{font-size:13px;color:var(--ma-text);background:var(--ma-bg-soft);border-radius:8px;padding:10px 12px;line-height:1.5}

@media(max-width:600px){
  .ma-card{flex-direction:column}
  .ma-card-img{width:100%;height:150px}
}
</style>

<script>
const MA_IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const MA_ORDERS   = <?php echo $ordersJson ?: '[]'; ?>;
const MA_RENTALS  = <?php echo $rentalsJson ?: '[]'; ?>;
const MA_ADVISORY = <?php echo $advisoryJson ?: '[]'; ?>;

const MyActivityT = {
    en: {
        pageTitle:"My Activity", pageSub:"All your Orders, Rentals and Advisory requests — with name, ID, photo and status, in one place.",
        tabOrders:"Orders", tabRentals:"Rentals", tabAdvisory:"Advisory",
        loginMsg:"Please login to see your Orders, Rentals and Advisory requests.", loginBtn:"Login",
        noOrders:"You haven't placed any orders yet.", noRentals:"You haven't booked any equipment yet.", noAdvisory:"You haven't asked an expert anything yet.",
        orderIdLbl:"Order ID", bookingIdLbl:"Booking ID", requestIdLbl:"Request ID",
        totalLbl:"Total", paymentLbl:"Payment", ownerLbl:"Owner", datesLbl:"Dates", placedOnLbl:"Placed on", submittedOnLbl:"Submitted on",
        alsoLbl:"more item(s)", yourQuestionLbl:"Your question", expertReplyLbl:"Expert's reply", awaitingReplyLbl:"Waiting for expert's reply…",
        stPlaced:"Placed", stPacked:"Packed", stShipped:"Shipped", stDelivered:"Delivered", stCancelled:"Cancelled",
        stPending:"Pending", stConfirmed:"Confirmed", stOnTheWay:"On the way", stCompleted:"Completed",
        stAnswered:"Answered", stClosed:"Closed",
        requestBtnLbl:"Request Expert Advice", requestTitle:"Request Expert Advice",
        lblCrop:"Crop", lblSubject:"Subject", lblMessage:"Your question", lblImage:"Photo (optional)",
        cropPh:"e.g. Cotton, Tomato...", subjectPh:"e.g. Yellow leaves, pest attack...", messagePh:"Describe your question in detail...",
        submitBtn:"Submit", submitting:"Submitting...", submitSuccess:"Request submitted!", submitError:"Something went wrong, please try again.",
        fillAll:"Please fill crop, subject and your question.",
        months:["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"],
        clickHint:"Tap for full details",
        orderDetailsTitle:"Order Details", rentalDetailsTitle:"Booking Details",
        itemsLbl:"Items", qtyLbl:"Qty", priceLbl:"Price", subtotalLbl:"Subtotal",
        deliverToLbl:"Delivering to", contactLbl:"Contact", closeLbl:"Close",
        durationLbl:"Duration", payNowLbl:"Pay Now", equipmentLbl:"Equipment",
        cancelBtnLbl:"Cancel Booking", cancelConfirm:"Are you sure you want to cancel this booking?",
        cancelSuccess:"Booking cancelled.", cancelSuccessPaid:"Booking cancelled. Your amount will be refunded within 7 days.",
        cancelError:"Something went wrong, please try again."
    },
    mr: {
        pageTitle:"माझी ऍक्टिव्हिटी", pageSub:"तुमच्या सर्व Orders, Rentals आणि Advisory requests — नाव, ID, फोटो आणि स्टेटससह, एकाच ठिकाणी.",
        tabOrders:"ऑर्डर्स", tabRentals:"भाड्याने घेतलेली अवजारे", tabAdvisory:"सल्ला (Advisory)",
        loginMsg:"तुमचे Orders, Rentals आणि Advisory requests बघण्यासाठी login करा.", loginBtn:"Login करा",
        noOrders:"तुम्ही अजून कोणतीही ऑर्डर केलेली नाही.", noRentals:"तुम्ही अजून कोणतेही अवजार भाड्याने घेतलेले नाही.", noAdvisory:"तुम्ही अजून तज्ज्ञांना काहीही विचारलेले नाही.",
        orderIdLbl:"ऑर्डर आयडी", bookingIdLbl:"बुकिंग आयडी", requestIdLbl:"रिक्वेस्ट आयडी",
        totalLbl:"एकूण", paymentLbl:"पेमेंट", ownerLbl:"मालक", datesLbl:"तारखा", placedOnLbl:"ऑर्डर केली", submittedOnLbl:"सबमिट केले",
        alsoLbl:"अजून वस्तू", yourQuestionLbl:"तुमचा प्रश्न", expertReplyLbl:"तज्ज्ञांचे उत्तर", awaitingReplyLbl:"तज्ज्ञांच्या उत्तराची वाट पाहत आहोत…",
        stPlaced:"ऑर्डर झाली", stPacked:"पॅक झाली", stShipped:"पाठवली", stDelivered:"डिलिव्हर झाली", stCancelled:"रद्द",
        stPending:"प्रलंबित", stConfirmed:"निश्चित", stOnTheWay:"वाटेत आहे", stCompleted:"पूर्ण",
        stAnswered:"उत्तर दिले", stClosed:"बंद",
        requestBtnLbl:"तज्ज्ञांचा सल्ला मागवा", requestTitle:"तज्ज्ञांचा सल्ला मागवा",
        lblCrop:"पीक", lblSubject:"विषय", lblMessage:"तुमचा प्रश्न", lblImage:"फोटो (ऐच्छिक)",
        cropPh:"उदा. कापूस, टोमॅटो...", subjectPh:"उदा. पाने पिवळी पडणे, कीड लागणे...", messagePh:"तुमचा प्रश्न सविस्तर लिहा...",
        submitBtn:"सबमिट करा", submitting:"सबमिट होत आहे...", submitSuccess:"रिक्वेस्ट सबमिट झाली!", submitError:"काहीतरी चूक झाली, पुन्हा प्रयत्न करा.",
        fillAll:"कृपया पीक, विषय आणि तुमचा प्रश्न सर्व भरा.",
        months:["जाने","फेब्रु","मार्च","एप्रिल","मे","जून","जुलै","ऑग","सप्टें","ऑक्टो","नोव्हें","डिसें"],
        clickHint:"संपूर्ण माहितीसाठी टॅप करा",
        orderDetailsTitle:"ऑर्डर तपशील", rentalDetailsTitle:"बुकिंग तपशील",
        itemsLbl:"वस्तू", qtyLbl:"प्रमाण", priceLbl:"किंमत", subtotalLbl:"उप-एकूण",
        deliverToLbl:"डिलिव्हरी पत्ता", contactLbl:"संपर्क", closeLbl:"बंद करा",
        durationLbl:"कालावधी", payNowLbl:"आता पेमेंट करा", equipmentLbl:"अवजार",
        cancelBtnLbl:"Booking रद्द करा", cancelConfirm:"तुम्हाला ही booking रद्द करायची आहे का?",
        cancelSuccess:"Booking रद्द झाली.", cancelSuccessPaid:"Booking रद्द झाली. तुमचे पैसे 7 दिवसांत परत केले जातील.",
        cancelError:"काहीतरी चूक झाली, पुन्हा प्रयत्न करा."
    },
    hi: {
        pageTitle:"मेरी एक्टिविटी", pageSub:"आपके सभी Orders, Rentals और Advisory requests — नाम, ID, फोटो और स्टेटस के साथ, एक ही जगह पर.",
        tabOrders:"ऑर्डर्स", tabRentals:"किराए के उपकरण", tabAdvisory:"सलाह (Advisory)",
        loginMsg:"अपने Orders, Rentals और Advisory requests देखने के लिए login करें.", loginBtn:"Login करें",
        noOrders:"आपने अभी तक कोई ऑर्डर नहीं दिया है.", noRentals:"आपने अभी तक कोई उपकरण किराए पर नहीं लिया है.", noAdvisory:"आपने अभी तक विशेषज्ञ से कुछ नहीं पूछा है.",
        orderIdLbl:"ऑर्डर आईडी", bookingIdLbl:"बुकिंग आईडी", requestIdLbl:"रिक्वेस्ट आईडी",
        totalLbl:"कुल", paymentLbl:"भुगतान", ownerLbl:"मालिक", datesLbl:"तारीखें", placedOnLbl:"ऑर्डर की गई", submittedOnLbl:"सबमिट की गई",
        alsoLbl:"और वस्तुएं", yourQuestionLbl:"आपका सवाल", expertReplyLbl:"विशेषज्ञ का जवाब", awaitingReplyLbl:"विशेषज्ञ के जवाब का इंतज़ार है…",
        stPlaced:"ऑर्डर हुई", stPacked:"पैक हुई", stShipped:"भेजी गई", stDelivered:"डिलीवर हुई", stCancelled:"रद्द",
        stPending:"लंबित", stConfirmed:"पुष्टि", stOnTheWay:"रास्ते में है", stCompleted:"पूर्ण",
        stAnswered:"जवाब मिला", stClosed:"बंद",
        requestBtnLbl:"विशेषज्ञ की सलाह लें", requestTitle:"विशेषज्ञ की सलाह लें",
        lblCrop:"फसल", lblSubject:"विषय", lblMessage:"आपका सवाल", lblImage:"फोटो (वैकल्पिक)",
        cropPh:"जैसे कपास, टमाटर...", subjectPh:"जैसे पत्तियाँ पीली होना, कीट लगना...", messagePh:"अपना सवाल विस्तार से लिखें...",
        submitBtn:"सबमिट करें", submitting:"सबमिट हो रहा है...", submitSuccess:"रिक्वेस्ट सबमिट हुई!", submitError:"कुछ गड़बड़ हुई, फिर से कोशिश करें.",
        fillAll:"कृपया फसल, विषय और अपना सवाल सब भरें.",
        months:["जन","फर","मार्च","अप्रैल","मई","जून","जुलाई","अग","सित","अक्टू","नव","दिस"],
        clickHint:"पूरी जानकारी के लिए टैप करें",
        orderDetailsTitle:"ऑर्डर विवरण", rentalDetailsTitle:"बुकिंग विवरण",
        itemsLbl:"वस्तुएं", qtyLbl:"मात्रा", priceLbl:"कीमत", subtotalLbl:"उप-योग",
        deliverToLbl:"डिलीवरी पता", contactLbl:"संपर्क", closeLbl:"बंद करें",
        durationLbl:"अवधि", payNowLbl:"अभी भुगतान करें", equipmentLbl:"उपकरण",
        cancelBtnLbl:"Booking रद्द करें", cancelConfirm:"क्या आप वाकई ये booking रद्द करना चाहते हैं?",
        cancelSuccess:"Booking रद्द हो गई.", cancelSuccessPaid:"Booking रद्द हो गई. आपकी राशि 7 दिनों में वापस कर दी जाएगी.",
        cancelError:"कुछ गड़बड़ हुई, फिर से कोशिश करें."
    }
};

function maSetText(id, val){ const el = document.getElementById(id); if (el) el.textContent = val; }

// Localized date formatting — turns a raw MySQL datetime into "23 जुलै 2026"
// (or "23 Jul 2026" / "23 जुलाई 2026") using the month names for the
// currently selected language, instead of always showing English months.
function maFormatDate(dateStr, pt, withTime){
    if (!dateStr) return '';
    const d = new Date(String(dateStr).replace(' ', 'T'));
    if (isNaN(d.getTime())) return dateStr;
    let out = `${d.getDate()} ${pt.months[d.getMonth()]} ${d.getFullYear()}`;
    if (withTime) {
        const hh = String(d.getHours()).padStart(2, '0');
        const mm = String(d.getMinutes()).padStart(2, '0');
        out += `, ${hh}:${mm}`;
    }
    return out;
}

function maOrderStepLabel(pt, s){
    return {placed:pt.stPlaced, packed:pt.stPacked, shipped:pt.stShipped, delivered:pt.stDelivered, cancelled:pt.stCancelled}[s] || s;
}
function maRentalStatusLabel(pt, s){
    return {pending:pt.stPending, confirmed:pt.stConfirmed, on_the_way:pt.stOnTheWay, completed:pt.stCompleted, cancelled:pt.stCancelled}[s] || s;
}
function maAdvisoryStatusLabel(pt, s){
    return {pending:pt.stPending, answered:pt.stAnswered, closed:pt.stClosed}[s] || s;
}

const MA_ORDER_STEPS = ['placed','packed','shipped','delivered'];

function maRenderOrders(pt){
    const box = document.getElementById('maPanel-orders');
    if (!box) return;
    if (!MA_ORDERS.length){ box.innerHTML = `<div class="ma-empty"><i class="fa-solid fa-box-open"></i>${pt.noOrders}</div>`; return; }
    box.innerHTML = MA_ORDERS.map(o => {
        const status = String(o.status || '').trim().toLowerCase();
        const stepIdx = Math.max(0, MA_ORDER_STEPS.indexOf(status));
        const cancelled = status === 'cancelled';
        const fillPct = cancelled ? 0 : (stepIdx / (MA_ORDER_STEPS.length - 1)) * 75;
        const track = cancelled ? '' : `
            <div class="ma-track-line">
                <div class="ma-track-line-fill" style="width:${fillPct}%"></div>
                ${MA_ORDER_STEPS.map((s,i)=>`<div class="ma-track-step ${i<=stepIdx?'done':''} ${i===stepIdx?'current':''}"><div class="dot"><i class="fa-solid fa-${['box','boxes-packing','truck','circle-check'][i]}"></i></div>${maOrderStepLabel(pt,s)}</div>`).join('')}
            </div>`;
        const extra = o.extraCount > 0 ? `<span class="ma-card-extra"> +${o.extraCount} ${pt.alsoLbl}</span>` : '';
        return `<div class="ma-card ma-card-clickable" onclick="maOpenOrderModal(${o.id})">
            <img class="ma-card-img" src="${o.img}" alt="${o.name}">
            <div class="ma-card-body">
                <div class="ma-card-top">
                    <div><p class="ma-card-name">${o.name}${extra}</p><div class="ma-card-id">${pt.orderIdLbl}: <strong>${o.orderNumber}</strong></div></div>
                    <span class="ma-pill st-${o.status}">${maOrderStepLabel(pt,o.status)}</span>
                </div>
                <div class="ma-card-meta"><span>${pt.totalLbl}: <b>₹${o.total}</b></span><span>${pt.paymentLbl}: <b>${o.paymentMode}</b></span><span>${pt.placedOnLbl}: <b>${maFormatDate(o.createdAt, pt, true)}</b></span></div>
                ${track}
                <div class="ma-click-hint"><i class="fa-solid fa-hand-pointer"></i> ${pt.clickHint}</div>
            </div>
        </div>`;
    }).join('');
}

const MA_RENTAL_STEPS = ['pending','confirmed','on_the_way','completed'];
function maRenderRentals(pt, lang){
    const box = document.getElementById('maPanel-rentals');
    if (!box) return;
    if (!MA_RENTALS.length){ box.innerHTML = `<div class="ma-empty"><i class="fa-solid fa-tractor"></i>${pt.noRentals}</div>`; return; }
    box.innerHTML = MA_RENTALS.map(r => {
        const name = lang === 'mr' ? r.nameMr : r.nameEn;
        const stepIdx = Math.max(0, MA_RENTAL_STEPS.indexOf(r.status));
        const cancelled = r.status === 'cancelled';
        const fillPct = cancelled ? 0 : (stepIdx / (MA_RENTAL_STEPS.length - 1)) * 75;
        const track = cancelled ? '' : `
            <div class="ma-track-line">
                <div class="ma-track-line-fill" style="width:${fillPct}%"></div>
                ${MA_RENTAL_STEPS.map((s,i)=>`<div class="ma-track-step ${i<=stepIdx?'done':''}"><div class="dot"><i class="fa-solid fa-${['circle-check','box','truck','flag-checkered'][i]}"></i></div>${maRentalStatusLabel(pt,s)}</div>`).join('')}
            </div>`;
        return `<div class="ma-card ma-card-clickable" onclick="maOpenRentalModal(${r.id})">
            <img class="ma-card-img" src="${r.img}" alt="${name}">
            <div class="ma-card-body">
                <div class="ma-card-top">
                    <div><p class="ma-card-name">${name}</p><div class="ma-card-id">${pt.bookingIdLbl}: <strong>${r.bookingNumber}</strong></div></div>
                    <span class="ma-pill st-${r.status}">${maRentalStatusLabel(pt,r.status)}</span>
                </div>
                <div class="ma-card-meta"><span>${pt.datesLbl}: <b>${maFormatDate(r.fromDate,pt)} → ${maFormatDate(r.toDate,pt)}</b></span>${(r.totalDays||r.totalHours) ? `<span>${pt.durationLbl}: <b>${[r.totalDays?(r.totalDays+(r.totalDays>1?' days':' day')):null, r.totalHours?(r.totalHours+' hr'):null].filter(Boolean).join(', ')}</b></span>` : ''}<span>${pt.totalLbl}: <b>₹${r.total}</b></span><span>${pt.ownerLbl}: <b>${r.owner}</b></span></div>
                ${track}
                ${(!cancelled && r.status !== 'pending' && r.paymentStatus !== 'paid' && r.paymentStatus !== 'cod') ? `<a href="payment.php?booking_id=${r.id}" class="ma-pay-btn" onclick="event.stopPropagation()" style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:8px 16px;background:#2e7d32;color:#fff;border-radius:8px;font-weight:600;font-size:13px;text-decoration:none"><i class="fa-solid fa-indian-rupee-sign"></i> ${pt.payNowLbl}</a>` : ''}
                ${r.paymentStatus === 'cod' ? `<span style="display:inline-flex;align-items:center;gap:6px;margin-top:10px;padding:6px 14px;background:#fff8e6;color:#7a5b00;border-radius:8px;font-weight:600;font-size:12.5px"><i class="fa-solid fa-hand-holding-dollar"></i> Cash on Delivery</span>` : ''}
                ${(r.status !== 'cancelled' && r.status !== 'completed') ? `<button type="button" class="ma-cancel-btn" onclick="event.stopPropagation(); maCancelBooking(${r.id}, this)"><i class="fa-solid fa-ban"></i> ${pt.cancelBtnLbl}</button>` : ''}
                <div class="ma-click-hint"><i class="fa-solid fa-hand-pointer"></i> ${pt.clickHint}</div>
            </div>
        </div>`;
    }).join('');
}

function maRenderAdvisory(pt){
    const box = document.getElementById('maAdvisoryList');
    if (!box) return;
    if (!MA_ADVISORY.length){ box.innerHTML = `<div class="ma-empty"><i class="fa-solid fa-user-doctor"></i>${pt.noAdvisory}</div>`; return; }
    box.innerHTML = MA_ADVISORY.map(a => {
        const img = a.img ? `<img class="ma-card-img" src="${a.img}" alt="${a.subject}">` : `<div class="ma-card-img" style="display:flex;align-items:center;justify-content:center;color:var(--ma-muted)"><i class="fa-solid fa-seedling" style="font-size:24px"></i></div>`;
        const reply = a.status === 'answered' && a.adminReply
            ? `<div class="ma-reply-box"><b>${pt.expertReplyLbl}</b>${a.adminReply}</div>`
            : (a.status === 'pending' ? `<div class="ma-reply-box" style="opacity:.75"><i class="fa-solid fa-hourglass-half"></i> ${pt.awaitingReplyLbl}</div>` : '');
        return `<div class="ma-card">
            ${img}
            <div class="ma-card-body">
                <div class="ma-card-top">
                    <div><p class="ma-card-name">${a.crop} — ${a.subject}</p><div class="ma-card-id">${pt.requestIdLbl}: <strong>${a.requestNumber}</strong></div></div>
                    <span class="ma-pill st-${a.status}">${maAdvisoryStatusLabel(pt,a.status)}</span>
                </div>
                <div class="ma-card-meta"><span>${pt.submittedOnLbl}: <b>${maFormatDate(a.createdAt, pt, true)}</b></span></div>
                <div class="ma-msg-box"><b style="color:var(--ma-text)">${pt.yourQuestionLbl}:</b> ${a.message}</div>
                ${reply}
            </div>
        </div>`;
    }).join('');
}

function maShowTab(which){
    ['orders','rentals','advisory'].forEach(t => {
        const panel = document.getElementById('maPanel-' + t);
        const btn = document.getElementById('maTabBtn-' + t);
        if (panel) panel.style.display = (t === which) ? '' : 'none';
        if (btn) btn.classList.toggle('active', t === which);
    });
}

function maOpenRequestModal(){ document.getElementById('maRequestModalOverlay').classList.add('open'); }
function maCloseRequestModal(){ document.getElementById('maRequestModalOverlay').classList.remove('open'); }

function maOpenOrderModal(id){
    const pt = MyActivityT[window.lang || 'en'];
    const o = MA_ORDERS.find(x => x.id === id);
    if (!o) return;
    const itemsHtml = (o.items || []).map(it => `
        <div class="ma-detail-item">
            <img src="${it.img}" alt="${it.name}">
            <div>
                <div class="ma-detail-item-name">${it.name}</div>
                <div class="ma-detail-item-meta">${pt.qtyLbl}: ${it.qty} × ₹${it.price}</div>
            </div>
            <div class="ma-detail-item-sub">₹${it.subtotal}</div>
        </div>`).join('');

    document.getElementById('maOrderModalBody').innerHTML = `
        <div class="ma-detail-head">
            <div>
                <h2 style="margin:0;font-size:18px;color:var(--ma-primary-dark)"><i class="fa-solid fa-box"></i> ${pt.orderDetailsTitle}</h2>
                <div class="ma-detail-id">${pt.orderIdLbl}: <strong>${o.orderNumber}</strong></div>
            </div>
            <span class="ma-pill st-${o.status}">${maOrderStepLabel(pt, o.status)}</span>
        </div>

        <div class="ma-detail-section">
            <h4><i class="fa-solid fa-basket-shopping"></i> ${pt.itemsLbl}</h4>
            ${itemsHtml}
            <div class="ma-detail-total"><span>${pt.totalLbl}</span><span>₹${o.total}</span></div>
        </div>

        <div class="ma-detail-section">
            <h4><i class="fa-solid fa-credit-card"></i> ${pt.paymentLbl}</h4>
            <div class="ma-detail-row"><span>${pt.paymentLbl}</span><b>${o.paymentMode} · ${o.paymentStatus}</b></div>
            <div class="ma-detail-row"><span>${pt.placedOnLbl}</span><b>${maFormatDate(o.createdAt, pt, true)}</b></div>
        </div>

        ${o.deliveryAddress ? `
        <div class="ma-detail-section">
            <h4><i class="fa-solid fa-location-dot"></i> ${pt.deliverToLbl}</h4>
            <div class="ma-detail-address">
                <strong>${o.deliveryName || ''}</strong>${o.deliveryMobile ? ' · ' + o.deliveryMobile : ''}<br>
                ${o.deliveryAddress}
            </div>
        </div>` : ''}
    `;
    document.getElementById('maOrderModalOverlay').classList.add('open');
}
function maCloseOrderModal(){ document.getElementById('maOrderModalOverlay').classList.remove('open'); }

function maOpenRentalModal(id){
    const pt = MyActivityT[window.lang || 'en'];
    const r = MA_RENTALS.find(x => x.id === id);
    if (!r) return;
    const name = (window.lang === 'mr') ? r.nameMr : r.nameEn;
    const durationParts = [
        r.totalDays ? (r.totalDays + (r.totalDays > 1 ? ' days' : ' day')) : null,
        r.totalHours ? (r.totalHours + ' hr') : null
    ].filter(Boolean).join(', ');

    document.getElementById('maRentalModalBody').innerHTML = `
        <div class="ma-detail-head">
            <div>
                <h2 style="margin:0;font-size:18px;color:var(--ma-primary-dark)"><i class="fa-solid fa-tractor"></i> ${pt.rentalDetailsTitle}</h2>
                <div class="ma-detail-id">${pt.bookingIdLbl}: <strong>${r.bookingNumber}</strong></div>
            </div>
            <span class="ma-pill st-${r.status}">${maRentalStatusLabel(pt, r.status)}</span>
        </div>

        <div class="ma-detail-section">
            <h4><i class="fa-solid fa-wrench"></i> ${pt.equipmentLbl}</h4>
            <div class="ma-detail-item">
                <img src="${r.img}" alt="${name}">
                <div>
                    <div class="ma-detail-item-name">${name}</div>
                    <div class="ma-detail-item-meta">${pt.ownerLbl}: ${r.owner}</div>
                </div>
            </div>
        </div>

        <div class="ma-detail-section">
            <h4><i class="fa-solid fa-calendar-days"></i> ${pt.datesLbl}</h4>
            <div class="ma-detail-row"><span>${pt.datesLbl}</span><b>${maFormatDate(r.fromDate,pt)} → ${maFormatDate(r.toDate,pt)}</b></div>
            ${durationParts ? `<div class="ma-detail-row"><span>${pt.durationLbl}</span><b>${durationParts}</b></div>` : ''}
            <div class="ma-detail-total"><span>${pt.totalLbl}</span><span>₹${r.total}</span></div>
        </div>

        ${(r.status !== 'cancelled' && r.status !== 'pending' && r.paymentStatus !== 'paid' && r.paymentStatus !== 'cod') ? `
        <a href="payment.php?booking_id=${r.id}" style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:14px;padding:11px;background:#2e7d32;color:#fff;border-radius:9px;font-weight:700;font-size:14px;text-decoration:none"><i class="fa-solid fa-indian-rupee-sign"></i> ${pt.payNowLbl}</a>` : ''}
        ${(r.status !== 'cancelled' && r.status !== 'completed') ? `
        <button type="button" class="ma-cancel-btn" style="width:100%;justify-content:center;margin-top:10px" onclick="maCancelBooking(${r.id}, this)"><i class="fa-solid fa-ban"></i> ${pt.cancelBtnLbl}</button>` : ''}
    `;
    document.getElementById('maRentalModalOverlay').classList.add('open');
}
function maCloseRentalModal(){ document.getElementById('maRentalModalOverlay').classList.remove('open'); }

function maShowToast(msg){
    const t = document.getElementById('maToast');
    const m = document.getElementById('maToastMsg');
    if (t && m){ m.textContent = msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 2400); }
}

function maCancelBooking(id, btn){
    const pt = MyActivityT[window.lang || 'en'];
    if (!confirm(pt.cancelConfirm)) return;
    if (btn) btn.disabled = true;

    fetch('cancel_booking.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'booking_id=' + encodeURIComponent(id) + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || '')
    }).then(r => r.json()).then(data => {
        if (data.success){
            const r = MA_RENTALS.find(x => x.id === id);
            if (r) r.status = 'cancelled';
            maRenderRentals(pt, window.lang || 'en');
            maCloseRentalModal();
            maShowToast(data.was_paid ? pt.cancelSuccessPaid : pt.cancelSuccess);
        } else {
            if (btn) btn.disabled = false;
            maShowToast(data.error || pt.cancelError);
        }
    }).catch(() => {
        if (btn) btn.disabled = false;
        maShowToast(pt.cancelError);
    });
}

function maSubmitRequest(e){
    e.preventDefault();
    const pt = MyActivityT[window.lang || 'en'];
    const crop = document.getElementById('maCrop').value.trim();
    const subject = document.getElementById('maSubject').value.trim();
    const message = document.getElementById('maMessage').value.trim();
    const imageFile = document.getElementById('maImage').files[0];
    if (!crop || !subject || !message){ maShowToast(pt.fillAll); return; }

    const fd = new FormData();
    fd.append('crop', crop);
    fd.append('subject', subject);
    fd.append('message', message);
    fd.append('csrf_token', document.querySelector('meta[name="csrf-token"]')?.content || '');
    if (imageFile) fd.append('image', imageFile);

    const btn = document.getElementById('maSubmitBtn');
    if (btn){ btn.disabled = true; btn.textContent = pt.submitting; }

    fetch('request_advisory.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (btn){ btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> ' + pt.submitBtn; }
            if (data.success){
                maShowToast(pt.submitSuccess + ' (' + data.request_number + ')');
                maCloseRequestModal();
                document.getElementById('maRequestForm').reset();
                MA_ADVISORY.unshift({
                    id: 0, requestNumber: data.request_number, crop, subject, message,
                    img: imageFile ? URL.createObjectURL(imageFile) : null,
                    status: 'pending', adminReply: null, createdAt: 'now'
                });
                maRenderAdvisory(pt);
                document.querySelector('#maTabBtn-advisory .ma-count').textContent = MA_ADVISORY.length;
            } else {
                maShowToast(data.error || pt.submitError);
            }
        })
        .catch(() => {
            if (btn){ btn.disabled = false; btn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> ' + pt.submitBtn; }
            maShowToast(pt.submitError);
        });
}

window.pageLanguageCallback = function(lang){
    window.lang = lang;
    const pt = MyActivityT[lang] || MyActivityT.en;
    maSetText('maTitle', '');
    document.getElementById('maTitle').innerHTML = '<i class="fa-solid fa-clipboard-list"></i> ' + pt.pageTitle;
    maSetText('maSub', pt.pageSub);
    maSetText('maLoginMsg', pt.loginMsg);
    maSetText('maLoginBtn', pt.loginBtn);
    maSetText('maTabOrders', pt.tabOrders);
    maSetText('maTabRentals', pt.tabRentals);
    maSetText('maTabAdvisory', pt.tabAdvisory);
    maSetText('maRequestBtnLbl', pt.requestBtnLbl);
    document.getElementById('maRequestTitle') && (document.getElementById('maRequestTitle').innerHTML = '<i class="fa-solid fa-user-doctor"></i> ' + pt.requestTitle);
    maSetText('maLblCrop', pt.lblCrop);
    maSetText('maLblSubject', pt.lblSubject);
    maSetText('maLblMessage', pt.lblMessage);
    maSetText('maLblImage', pt.lblImage);
    const cropEl = document.getElementById('maCrop'); if (cropEl) cropEl.placeholder = pt.cropPh;
    const subjEl = document.getElementById('maSubject'); if (subjEl) subjEl.placeholder = pt.subjectPh;
    const msgEl = document.getElementById('maMessage'); if (msgEl) msgEl.placeholder = pt.messagePh;
    const submitBtn = document.getElementById('maSubmitBtn'); if (submitBtn) submitBtn.innerHTML = '<i class="fa-solid fa-paper-plane"></i> ' + pt.submitBtn;

    if (MA_IS_LOGGED_IN){
        maRenderOrders(pt);
        maRenderRentals(pt, lang);
        maRenderAdvisory(pt);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    const savedLang = localStorage.getItem('agri_lang') || 'en';
    window.lang = savedLang;
    window.pageLanguageCallback(savedLang);

    // If we just arrived here from sell_product.php / insert_equipment.php,
    // confirm the submission and remind the farmer what AgriCart's cut is.
    const params = new URLSearchParams(window.location.search);
    const listed = params.get('listed');
    const commission = params.get('commission');
    if (listed === 'product') {
        maShowToast('Product submitted for review! Platform commission: ' + (commission || '5') + '% per sale once approved.');
    } else if (listed === 'equipment') {
        maShowToast('Equipment submitted for review! Platform commission: ' + (commission || '10') + '% per booking once approved.');
    }
});
</script>

<?php
include __DIR__ . '/krishimitra_widget.php';
include __DIR__ . '/../includes/footer.php';
?>
