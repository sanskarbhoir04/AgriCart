<?php
// =====================================================
// AgriCart — Payment page for an ACCEPTED equipment booking
// Owner ne booking accept (confirm) keli ki, user ithun
// khara amount baghun payment karto. Multiple payment
// options: UPI QR, UPI ID (manual), Cash on Delivery.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';

$bookingId = (int)($_GET['booking_id'] ?? 0);
$userId    = (int)($_SESSION['user_id'] ?? 0);

$booking = null;
$errorMsg = '';

if (!$userId) {
    $errorMsg = 'Payment karnyasathi krupaya adhi login kara.';
} elseif ($bookingId <= 0) {
    $errorMsg = 'Booking sapadli nahi.';
} else {
    $stmt = $conn->prepare(
        "SELECT eb.id, eb.booking_number, eb.total_amount, eb.status, eb.payment_status,
                e.name, e.name_mr
         FROM equipment_bookings eb
         JOIN equipment e ON e.id = eb.equipment_id
         WHERE eb.id = ? AND eb.user_id = ? LIMIT 1"
    );
    $stmt->bind_param("ii", $bookingId, $userId);
    $stmt->execute();
    $booking = $stmt->get_result()->fetch_assoc();

    if (!$booking) {
        $errorMsg = 'Booking sapadli nahi kinva ti tumchi nahi.';
    } elseif ($booking['status'] === 'pending') {
        $errorMsg = 'Owner ne ajun tumchi request accept keleli nahi. Accept zalyavarach payment karta yeil.';
    } elseif ($booking['payment_status'] === 'paid') {
        $errorMsg = 'Ya booking cha payment aadhich zala aahe. Dhanyavad!';
    } elseif ($booking['payment_status'] === 'cod') {
        $errorMsg = 'Tumhi ya booking sathi aadhich "Cash on Delivery" nivadla aahe. Delivery velі owner la cash dya.';
    } elseif ($booking['payment_status'] === 'verification_pending') {
        $errorMsg = 'Tumhi aadhich payment details submit keli aahet. Admin verify karat aahe — thoda thamba.';
    }
}

$amount    = $booking ? number_format((float)$booking['total_amount'], 2) : '0.00';
$equipName = $booking ? ($booking['name'] ?: 'Equipment') : '';
$upiId     = 'agricart@okaxis';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Secure Payment Terminal - AgriCart</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@500;700&display=swap" rel="stylesheet">
    <style>
        @keyframes payFadeInUp { from { opacity: 0; transform: translateY(24px); } to { opacity: 1; transform: translateY(0); } }
        @keyframes payPopIn { from { opacity: 0; transform: scale(.85); } to { opacity: 1; transform: scale(1); } }
        @keyframes payLogoBounce { 0% { transform: translateY(-10px); opacity: 0; } 60% { transform: translateY(2px); opacity: 1; } 100% { transform: translateY(0); } }
        @keyframes payAmountGlow { 0%, 100% { box-shadow: 0 0 0 0 rgba(46,125,50,.25); } 50% { box-shadow: 0 0 0 8px rgba(46,125,50,0); } }
        @keyframes payQrFrame { 0% { box-shadow: 0 0 0 0 rgba(46,125,50,.35); } 100% { box-shadow: 0 0 0 10px rgba(46,125,50,0); } }
        @keyframes payRipple { to { transform: scale(2.8); opacity: 0; } }

        * { box-sizing: border-box; }
        body { background-color: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: 'Poppins', sans-serif; margin:0; padding:16px; }
        .payment-container {
            background: #fff; padding: 34px 34px 30px; border-radius: 20px; text-align: center; max-width: 420px; width:100%;
            border: 1px solid #eef4ee; animation: payFadeInUp .5s cubic-bezier(.22,.8,.36,1) both;
            box-shadow: 0 10px 40px rgba(0,0,0,0.06);
        }
        .pay-logo { color: #2e7d32; font-size: 26px; font-weight: 700; margin-bottom: 12px; animation: payLogoBounce .6s cubic-bezier(.34,1.56,.64,1) .1s both; }
        .booking-ref { font-size:13px; color:#777; margin-bottom:14px; }
        .amount {
            font-size: 30px; color: #1b5e20; font-weight: 700; margin-bottom: 18px; background: #e8f5e9;
            padding: 10px; border-radius: 12px; animation: payFadeInUp .5s ease .2s both, payAmountGlow 2.4s ease-in-out 1s infinite;
        }

        /* ── Tabs ── */
        .pay-tabs { display:flex; gap:6px; background:#f2f5f2; border-radius:12px; padding:4px; margin-bottom:18px; }
        .pay-tab { flex:1; border:none; background:transparent; padding:9px 4px; border-radius:9px; font-size:12px; font-weight:600; color:#555; cursor:pointer; display:flex; flex-direction:column; align-items:center; gap:3px; transition:.2s; }
        .pay-tab i { font-size:15px; }
        .pay-tab.active { background:#2e7d32; color:#fff; box-shadow:0 3px 10px rgba(46,125,50,.3); }
        .pay-panel { display:none; text-align:left; animation: payFadeInUp .35s ease both; }
        .pay-panel.active { display:block; }

        #inst { animation: payFadeInUp .5s ease .15s both; color:#555; text-align:center; margin-top:0; }
        .qr-wrap { display: block; margin: 0 auto 16px; width:200px; border-radius: 14px; animation: payPopIn .5s cubic-bezier(.34,1.56,.64,1) .25s both, payQrFrame 2.2s ease-out 1.2s infinite; }
        .qr-wrap img { width: 200px; display: block; border-radius: 10px; }

        .upi-id-box { display:flex; align-items:center; gap:8px; background:#f4f7f6; border:1.5px dashed #cfe0cf; border-radius:12px; padding:12px 14px; margin-bottom:14px; }
        .upi-id-box code { flex:1; font-size:15px; font-weight:700; color:#1b5e20; word-break:break-all; }
        .copy-btn { border:none; background:#e8f5e9; color:#2e7d32; padding:8px 12px; border-radius:8px; font-size:12px; font-weight:600; cursor:pointer; white-space:nowrap; }
        .copy-btn:hover { background:#d7ecd8; }
        .pay-hint { font-size:12.5px; color:#777; text-align:center; margin:0 0 16px; line-height:1.5; }

        .cod-box { background:#fff8e6; border:1.5px solid #ffe6a8; border-radius:12px; padding:14px; font-size:13px; color:#7a5b00; line-height:1.6; margin-bottom:16px; }

        .demo-banner { background:#eef4ff; border:1.5px solid #cddcf7; color:#264a83; border-radius:12px; padding:10px 12px; font-size:12px; line-height:1.5; margin-bottom:16px; text-align:left; }
        .proof-form { text-align:left; margin-top:4px; }
        .proof-label { display:block; font-size:12px; font-weight:600; color:#444; margin:10px 0 5px; }
        .proof-txn, .proof-file { width:100%; padding:10px 12px; border:1.5px solid #dfe6df; border-radius:10px; font-size:13.5px; box-sizing:border-box; }
        .proof-file { padding:8px 10px; background:#fff; }
        .proof-error { color:#b71c1c; background:#fdecea; border-radius:8px; padding:8px 10px; font-size:12.5px; margin-top:8px; }

        .btn-success {
            position: relative; overflow: hidden; background-color: #2e7d32; color: white; border: none; padding: 15px;
            font-size: 16px; border-radius: 12px; cursor: pointer; width: 100%; font-weight: 600;
            transition: transform .15s cubic-bezier(.34,1.56,.64,1), background-color .25s ease, box-shadow .25s ease;
        }
        .btn-success:hover { background-color: #388e3c; box-shadow: 0 8px 22px rgba(46,125,50,.28); transform: translateY(-2px); }
        .btn-success:active { transform: translateY(0) scale(.97); }
        .btn-success:disabled { opacity:.6; cursor:not-allowed; }
        .btn-cod { background-color:#c98a00; }
        .btn-cod:hover { background-color:#b87a00; }
        .pay-ripple { position: absolute; border-radius: 50%; background: rgba(255,255,255,.5); transform: scale(0); pointer-events: none; animation: payRipple .6s ease-out forwards; }
        .pay-error { color:#b71c1c; background:#fdecea; border-radius:12px; padding:16px; font-size:14px; line-height:1.5; }
        .pay-back-link { display:block; margin-top:16px; color:#2e7d32; font-weight:600; text-decoration:none; font-size:13px; }
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after { animation-duration: .001ms !important; animation-iteration-count: 1 !important; }
        }
    </style>
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<div class="payment-container">
    <div class="pay-logo">🌾 AgriCart Pay</div>
    <?php if ($errorMsg): ?>
        <div class="pay-error"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?></div>
        <a class="pay-back-link" href="my_activity.php">← My Activity var parat jaa</a>
    <?php else: ?>
        <p class="booking-ref" style="text-align:center">Booking ID: <strong><?= htmlspecialchars($booking['booking_number']) ?></strong> — <?= htmlspecialchars($equipName) ?></p>
        <div class="amount">₹<?= $amount ?></div>

        <div class="pay-tabs">
            <button type="button" class="pay-tab active" data-tab="qr" onclick="switchPayTab('qr')"><i class="fa-solid fa-qrcode"></i>QR Scan</button>
            <button type="button" class="pay-tab" data-tab="upi" onclick="switchPayTab('upi')"><i class="fa-solid fa-mobile-screen-button"></i>UPI ID</button>
            <button type="button" class="pay-tab" data-tab="cod" onclick="switchPayTab('cod')"><i class="fa-solid fa-hand-holding-dollar"></i>Cash</button>
        </div>

        <div class="demo-banner"><i class="fa-solid fa-circle-info"></i> Demo Payment Verification — no real payment gateway is connected. Submitted details are reviewed by an admin before the booking is marked paid.</div>

        <!-- QR panel -->
        <div class="pay-panel active" id="panel-qr">
            <p id="inst">Scan QR code using any UPI app to complete payment, then enter the transaction reference below.</p>
            <img class="qr-wrap" src="https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=upi://pay?pa=<?= urlencode($upiId) ?>%26pn=AgriCart%26am=<?= urlencode($amount) ?>%26tn=<?= urlencode($booking['booking_number']) ?>" alt="UPI QR Code">
            <?php include __DIR__ . '/_payment_proof_form.php'; ?>
        </div>

        <!-- UPI ID panel -->
        <div class="pay-panel" id="panel-upi">
            <p class="pay-hint">Konatyahi UPI app (GPay / PhonePe / Paytm) madhe ha UPI ID takun payment pathva, mag khali transaction reference takara.</p>
            <div class="upi-id-box">
                <code id="upiIdText"><?= htmlspecialchars($upiId) ?></code>
                <button type="button" class="copy-btn" onclick="copyUpiId()"><i class="fa-regular fa-copy"></i> Copy</button>
            </div>
            <?php include __DIR__ . '/_payment_proof_form.php'; ?>
        </div>

        <!-- Cash on Delivery panel -->
        <div class="pay-panel" id="panel-cod">
            <div class="cod-box">
                <i class="fa-solid fa-circle-info"></i>
                Equipment deliver zalyavar tumhi thet owner la <strong>₹<?= $amount ?></strong> cash de shakta.
                Konatehi advance online payment karnyachi garaj nahi — pan kripaya delivery वेळी amount tayar theva.
            </div>
            <button type="button" class="btn-success btn-cod" id="btn-pay-cod" onclick="confirmCod(event)">Confirm Cash on Delivery</button>
        </div>

    <?php endif; ?>
</div>
<script>
    let currentLang = localStorage.getItem("agricart_lang") || "en";
    const isMr = currentLang === 'mr';
    const instEl = document.getElementById("inst");
    if (isMr && instEl) instEl.innerText = "पेमेंट पूर्ण करण्यासाठी कोणत्याही UPI app ने QR कोड स्कॅन करा, मग खाली transaction reference टाका.";
    if (isMr) {
        document.querySelectorAll('.proof-label').forEach((el, i) => {
            el.innerText = (i % 2 === 0) ? "Transaction / UTR reference number" : "Payment screenshot (optional)";
        });
        document.querySelectorAll('.proof-form .btn-success').forEach(b => { b.innerText = "पेमेंट तपशील सबमिट करा"; });
    }

    function switchPayTab(tab){
        document.querySelectorAll('.pay-tab').forEach(t => t.classList.toggle('active', t.dataset.tab === tab));
        document.querySelectorAll('.pay-panel').forEach(p => p.classList.toggle('active', p.id === 'panel-' + tab));
    }

    function copyUpiId(){
        const text = document.getElementById('upiIdText').innerText;
        navigator.clipboard?.writeText(text).then(() => {
            const btn = event.currentTarget;
            const old = btn.innerHTML;
            btn.innerHTML = '<i class="fa-solid fa-check"></i> Copied';
            setTimeout(() => { btn.innerHTML = old; }, 1500);
        }).catch(() => { alert('UPI ID: ' + text); });
    }

    const bookingId = <?= json_encode((int)($booking['id'] ?? 0)) ?>;
    let submittedMsg = isMr
        ? "✅ पेमेंट तपशील सबमिट झाले! Admin verify केल्यावर बुकिंग 'paid' दाखवेल."
        : "✅ Payment details submitted! An admin will verify it shortly, then your booking will show as paid.";

    function markPaidOnServer(method){
        return fetch('confirm_payment.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'booking_id=' + encodeURIComponent(bookingId) + '&method=' + encodeURIComponent(method || 'paid') + '&csrf_token=' + encodeURIComponent(<?= json_encode(csrf_token()) ?>)
        }).then(r => r.json());
    }

    function submitPaymentProof(e) {
        const btn = e.currentTarget;
        const form = btn.closest('.proof-form');
        const errBox = form.querySelector('.proof-error');
        const txnInput = form.querySelector('.proof-txn');
        const fileInput = form.querySelector('.proof-file');
        errBox.style.display = 'none';

        const txn = (txnInput.value || '').trim();
        if (txn.length < 4) {
            errBox.textContent = isMr ? 'Krupaya valid transaction/UTR number takara.' : 'Please enter a valid transaction / UTR reference number.';
            errBox.style.display = 'block';
            return;
        }

        btn.disabled = true;
        const fd = new FormData();
        fd.append('booking_id', bookingId);
        fd.append('method', 'paid');
        fd.append('payment_method', 'upi');
        fd.append('transaction_id', txn);
        fd.append('csrf_token', <?= json_encode(csrf_token()) ?>);
        if (fileInput.files && fileInput.files[0]) {
            fd.append('screenshot', fileInput.files[0]);
        }

        fetch('confirm_payment.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(submittedMsg);
                    window.location.href = "my_activity.php";
                } else {
                    errBox.textContent = data.message || (isMr ? 'Adchan ali, punha try kara.' : 'Something went wrong, please try again.');
                    errBox.style.display = 'block';
                    btn.disabled = false;
                }
            })
            .catch(() => {
                errBox.textContent = isMr ? 'Network error, punha try kara.' : 'Network error, please try again.';
                errBox.style.display = 'block';
                btn.disabled = false;
            });
    }

    function confirmCod(e){
        const btn = e.currentTarget;
        btn.disabled = true;
        markPaidOnServer('cod')
        .then(data => {
            setTimeout(() => {
                if (data.success) {
                    alert(isMr ? '✅ नोंद झाली! Delivery वेळी owner ला cash द्या.' : '✅ Noted! Please pay cash to the owner at delivery.');
                    window.location.href = "my_activity.php";
                } else {
                    alert(data.message || (isMr ? 'Adchan ali, punha try kara.' : 'Something went wrong, please try again.'));
                    btn.disabled = false;
                }
            }, 200);
        })
        .catch(() => {
            setTimeout(() => {
                alert(isMr ? 'Network error, punha try kara.' : 'Network error, please try again.');
                btn.disabled = false;
            }, 200);
        });
    }
</script>
</body>
</html>
