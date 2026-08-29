<?php
// =====================================================================
// pages/owner_bookings.php — "My Equipment Bookings" (Owner dashboard)
// Shown to a logged-in farmer who has self-listed equipment for rent
// (equipment.owner_user_id = their user id). Lists every booking made
// on their equipment and lets them Cancel a booking (accept/reject of
// new requests stays with Admin, as before — this page only adds the
// Cancel option for the owner).
// =====================================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

if (!isset($base_path)) {
    $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    $base_path = rtrim(dirname($scriptDir), '/');
}

$isLoggedIn = isset($_SESSION['user_id']);
$userId     = (int)($_SESSION['user_id'] ?? 0);
$defaultEquipImage = 'assets/images/equipment.png';

function ob_resolve_img($path, $default, $base_path) {
    $p = !empty($path) ? $path : $default;
    if (preg_match('#^(https?:)?//#i', $p) || strpos($p, '/') === 0) { return $p; }
    return rtrim($base_path, '/') . '/' . ltrim($p, '/');
}

$hasEquipment = false;
$bookingsForJs = [];

if ($isLoggedIn) {
    $eqCheck = $conn->prepare("SELECT id FROM equipment WHERE owner_user_id = ? LIMIT 1");
    if ($eqCheck) {
        $eqCheck->bind_param("i", $userId);
        $eqCheck->execute();
        $hasEquipment = (bool)$eqCheck->get_result()->fetch_assoc();
    }

    if ($hasEquipment) {
        $stmt = $conn->prepare(
            "SELECT eb.id, eb.booking_number, eb.from_date, eb.to_date, eb.total_amount, eb.status,
                    eb.payment_status, eb.total_days, eb.total_hours, eb.contact_name, eb.contact_mobile,
                    e.name, e.name_mr, e.image
             FROM equipment_bookings eb
             JOIN equipment e ON e.id = eb.equipment_id
             WHERE e.owner_user_id = ?
             ORDER BY eb.id DESC"
        );
        if ($stmt) {
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $res = $stmt->get_result();
            while ($r = $res->fetch_assoc()) {
                $bookingsForJs[] = [
                    'id'            => (int)$r['id'],
                    'bookingNumber' => $r['booking_number'],
                    'nameEn'        => $r['name'],
                    'nameMr'        => $r['name_mr'] ?: $r['name'],
                    'img'           => ob_resolve_img($r['image'], $defaultEquipImage, $base_path),
                    'fromDate'      => $r['from_date'],
                    'toDate'        => $r['to_date'],
                    'total'         => (float)$r['total_amount'],
                    'status'        => $r['status'],
                    'paymentStatus' => $r['payment_status'] ?: 'pending',
                    'totalDays'     => $r['total_days'] ? (int)$r['total_days'] : null,
                    'totalHours'    => $r['total_hours'] ? (int)$r['total_hours'] : null,
                    'renterName'    => $r['contact_name'],
                    'renterMobile'  => $r['contact_mobile'],
                ];
            }
        }
    }
}

$bookingsJson = json_encode($bookingsForJs, JSON_UNESCAPED_UNICODE);

include __DIR__ . '/../includes/header.php';
?>
<div class="ob-hero">
    <div class="ob-hero-inner">
        <h1><i class="fa-solid fa-tractor"></i> माझी Equipment Bookings</h1>
        <p>तुम्ही भाड्याने दिलेल्या equipment वरील सर्व bookings — इथून तुम्ही booking cancel करू शकता.</p>
    </div>
</div>

<div class="ob-wrap">
<?php if (!$isLoggedIn): ?>
    <div class="ob-empty-card">
        <i class="fa-solid fa-lock"></i>
        <p>तुमची equipment bookings बघण्यासाठी login करा.</p>
        <a href="<?php echo $base_path; ?>/pages/login.php" class="ob-btn-primary">Login करा</a>
    </div>
<?php elseif (!$hasEquipment): ?>
    <div class="ob-empty-card">
        <i class="fa-solid fa-tractor"></i>
        <p>तुम्ही अजून कोणतेही equipment भाड्याने देण्यासाठी list केलेले नाही.</p>
        <a href="<?php echo $base_path; ?>/pages/rental.php" class="ob-btn-primary">Equipment List करा</a>
    </div>
<?php else: ?>
    <div id="obList"></div>
<?php endif; ?>
</div>

<div class="ob-toast" id="obToast"><i class="fa-solid fa-circle-check"></i> <span id="obToastMsg"></span></div>

<style>
:root{ --ob-primary:#2F4F44; --ob-primary-dark:#213B33; --ob-danger:#9B3B37; --ob-danger-bg:#F5E8E7; --ob-bg-soft:#EEF1EC; --ob-border:#E0E2DD; --ob-muted:#68706B; --ob-text:#26292B; }
.ob-hero{background:linear-gradient(135deg,var(--ob-primary-dark),var(--ob-primary));padding:40px 20px;color:#fff;text-align:center}
.ob-hero-inner{max-width:800px;margin:0 auto}
.ob-hero h1{font-size:24px;margin:0 0 8px;display:flex;align-items:center;justify-content:center;gap:10px}
.ob-hero p{font-size:13.5px;opacity:.9;margin:0}
.ob-wrap{max-width:800px;margin:26px auto 60px;padding:0 16px}
.ob-empty-card{text-align:center;background:#fff;border:1px solid var(--ob-border);border-radius:14px;padding:50px 20px;color:var(--ob-muted)}
.ob-empty-card i{font-size:32px;color:var(--ob-primary);margin-bottom:14px;display:block}
.ob-btn-primary{display:inline-flex;align-items:center;gap:8px;background:var(--ob-primary);color:#fff;border:none;padding:11px 22px;border-radius:9px;font-size:14px;font-weight:600;cursor:pointer;text-decoration:none;margin-top:14px}
.ob-card{background:#fff;border:1px solid var(--ob-border);border-radius:12px;padding:16px;margin-bottom:14px;display:flex;gap:14px}
.ob-card-img{width:70px;height:70px;border-radius:10px;object-fit:cover;flex-shrink:0;background:var(--ob-bg-soft)}
.ob-card-body{flex:1;min-width:0}
.ob-card-top{display:flex;justify-content:space-between;align-items:flex-start;gap:10px;flex-wrap:wrap}
.ob-card-name{font-size:15px;font-weight:700;color:var(--ob-text);margin:0}
.ob-card-id{font-size:12px;color:var(--ob-muted);margin-top:2px}
.ob-card-id strong{color:var(--ob-primary-dark)}
.ob-pill{display:inline-block;padding:4px 10px;border-radius:20px;font-size:11px;font-weight:700;white-space:nowrap}
.ob-pill.st-pending{background:#F3EEE2;color:#8A6D3B}
.ob-pill.st-confirmed{background:#E7EEFB;color:#2E4E8C}
.ob-pill.st-on_the_way{background:#EAF1E9;color:#3E6B3F}
.ob-pill.st-completed{background:#E8F3EA;color:#2F6B3A}
.ob-pill.st-cancelled{background:var(--ob-danger-bg);color:var(--ob-danger)}
.ob-card-meta{font-size:12.5px;color:var(--ob-muted);margin-top:8px;display:flex;gap:14px;flex-wrap:wrap}
.ob-card-meta b{color:var(--ob-text)}
.ob-cancel-btn{margin-top:10px;padding:8px 16px;background:#fff;border:1.5px solid var(--ob-danger);color:var(--ob-danger);border-radius:8px;font-weight:600;font-size:13px;cursor:pointer;display:inline-flex;align-items:center;gap:6px}
.ob-cancel-btn:hover{background:var(--ob-danger-bg)}
.ob-cancel-btn:disabled{opacity:.55;cursor:not-allowed}
.ob-toast{position:fixed;bottom:24px;left:50%;transform:translateX(-50%) translateY(80px);background:var(--ob-primary-dark);color:#fff;padding:12px 20px;border-radius:10px;font-size:13.5px;display:flex;align-items:center;gap:8px;opacity:0;transition:all .3s ease;z-index:2000}
.ob-toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
@media(max-width:600px){ .ob-card{flex-direction:column} .ob-card-img{width:100%;height:140px} }
</style>

<script>
const OB_BOOKINGS = <?php echo $bookingsJson ?: '[]'; ?>;

function obFormatDate(d){
    if(!d) return '';
    const dt = new Date(String(d).replace(' ','T'));
    if (isNaN(dt.getTime())) return d;
    return dt.getDate() + '/' + (dt.getMonth()+1) + '/' + dt.getFullYear();
}
function obStatusLabel(s){
    return {pending:'प्रलंबित',confirmed:'निश्चित',on_the_way:'वाटेत आहे',completed:'पूर्ण',cancelled:'रद्द'}[s] || s;
}
function obShowToast(msg){
    const t = document.getElementById('obToast'), m = document.getElementById('obToastMsg');
    if (t && m){ m.textContent = msg; t.classList.add('show'); setTimeout(()=>t.classList.remove('show'), 2600); }
}
function obRender(){
    const box = document.getElementById('obList');
    if (!box) return;
    if (!OB_BOOKINGS.length){
        box.innerHTML = '<div class="ob-empty-card"><i class="fa-solid fa-calendar-xmark"></i><p>अजून कोणतीही booking आलेली नाही.</p></div>';
        return;
    }
    box.innerHTML = OB_BOOKINGS.map(r => {
        const canCancel = r.status !== 'cancelled' && r.status !== 'completed';
        return `<div class="ob-card">
            <img class="ob-card-img" src="${r.img}" alt="${r.nameEn}">
            <div class="ob-card-body">
                <div class="ob-card-top">
                    <div><p class="ob-card-name">${r.nameEn}</p><div class="ob-card-id">Booking ID: <strong>${r.bookingNumber}</strong></div></div>
                    <span class="ob-pill st-${r.status}">${obStatusLabel(r.status)}</span>
                </div>
                <div class="ob-card-meta">
                    <span>Dates: <b>${obFormatDate(r.fromDate)} → ${obFormatDate(r.toDate)}</b></span>
                    <span>Total: <b>₹${r.total}</b></span>
                    <span>Renter: <b>${r.renterName || '—'}${r.renterMobile ? ' · ' + r.renterMobile : ''}</b></span>
                </div>
                ${canCancel ? `<button type="button" class="ob-cancel-btn" onclick="obCancelBooking(${r.id}, this)"><i class="fa-solid fa-ban"></i> Booking Cancel करा</button>` : ''}
            </div>
        </div>`;
    }).join('');
}

function obCancelBooking(id, btn){
    if (!confirm('ही booking cancel करायची आहे का? Renter ला refund/cancellation notification पाठवले जाईल.')) return;
    btn.disabled = true;
    fetch('owner_booking_action.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'booking_id=' + encodeURIComponent(id) + '&action=cancel' + '&csrf_token=' + encodeURIComponent(document.querySelector('meta[name="csrf-token"]')?.content || '')
    }).then(r => r.json()).then(data => {
        if (data.success){
            const b = OB_BOOKINGS.find(x => x.id === id);
            if (b) b.status = 'cancelled';
            obRender();
            obShowToast('Booking cancel झाली. Renter ला कळवले गेले आहे.');
        } else {
            btn.disabled = false;
            obShowToast(data.error || 'काहीतरी चूक झाली, पुन्हा try करा.');
        }
    }).catch(() => {
        btn.disabled = false;
        obShowToast('Network error, पुन्हा try करा.');
    });
}

document.addEventListener('DOMContentLoaded', obRender);
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
