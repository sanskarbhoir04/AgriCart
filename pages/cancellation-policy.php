<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Cancellation Policy Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\cancellation-policy.php
// =====================================================
agri_session_start();
include __DIR__ . '/../includes/header.php';
?>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600&family=Poppins:wght@600;700;800&display=swap" rel="stylesheet">

<style>
.lg-wrap { background:#F8FAF7; font-family:'Inter',sans-serif; color:#33452f; }

/* ── Hero band ─────────────────────────────────────── */
.lg-hero {
    background: radial-gradient(circle at 15% 20%, rgba(46,204,113,0.16), transparent 45%), #0f2a16;
    padding: 3.4rem 1.5rem 3rem; text-align: center; position: relative; overflow: hidden;
}
.lg-hero-icon {
    width: 58px; height: 58px; border-radius: 50%; margin: 0 auto 18px;
    background: rgba(46,204,113,0.14); border: 1px solid rgba(46,204,113,0.3);
    display: flex; align-items: center; justify-content: center; font-size: 22px; color: #2ecc71;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.lg-hero-icon:hover { transform: translateY(-4px) scale(1.08); background: #2ecc71; color: #0b1a14; }
.lg-hero h1 { font-family:'Poppins',sans-serif; font-weight: 800; font-size: 2.1rem; color: #fff; margin-bottom: 10px; }
.lg-hero p { font-size: 14.5px; color: rgba(255,255,255,0.65); max-width: 480px; margin: 0 auto; }
.lg-hero-meta { font-size: 12.5px; color: rgba(255,255,255,0.55); margin-top: 18px; }
.lg-hero-meta span.lg-dot { margin: 0 8px; opacity: 0.5; }
.lg-hero-print {
    display: block; margin: 16px auto 0; width: fit-content;
    font-size: 12px; font-weight: 700; color: #2ecc71; background: transparent;
    border: 1px solid rgba(46,204,113,0.4); padding: 7px 16px; border-radius: 8px;
    text-decoration: none; cursor: pointer; transition: background 0.2s ease;
}
.lg-hero-print:hover { background: rgba(46,204,113,0.1); }

/* ── Quick-glance highlight cards ─────────────────────── */
.lg-glance { max-width: 780px; margin: -28px auto 0; padding: 0 1.5rem; position: relative; z-index: 2; }
.lg-glance-grid { display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; }
.lg-glance-card {
    background: #fff; border-radius: 14px; padding: 18px 16px;
    box-shadow: 0 8px 24px rgba(15,42,22,0.08); border: 1px solid #eef2ee;
}
.lg-glance-card i {
    font-size: 16px; color: #2ecc71; margin-bottom: 10px;
    width: 38px; height: 38px; border-radius: 50%; background: rgba(46,204,113,0.1);
    display: flex; align-items: center; justify-content: center;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.lg-glance-card:hover i { transform: translateY(-4px) scale(1.08); background: #2ecc71; color: #0b1a14; }
.lg-glance-card h4 { font-family:'Poppins',sans-serif; font-size: 12.8px; font-weight: 700; color: #0f2a16; margin-bottom: 3px; }
.lg-glance-card p { font-size: 11.8px; color: #7a9b7a; line-height: 1.5; }
@media (max-width: 860px) { .lg-glance-grid { grid-template-columns: repeat(2, 1fr); } }

/* ── Layout ─────────────────────────────────────────── */
.lg-body { max-width: 780px; margin: 0 auto; padding: 2.6rem 1.5rem 4rem; }
.lg-paper {
    background: rgba(255,255,255,0.94); border: 1px solid #eef2ee; border-radius: 20px;
    padding: 2.6rem 2.8rem; box-shadow: 0 18px 40px rgba(15,42,22,0.08);
}
@media (max-width: 600px) { .lg-paper { padding: 2rem 1.4rem; } }

/* ── Content ────────────────────────────────────────── */
.lg-intro { font-size: 15px; line-height: 1.8; color: #33452f; margin-bottom: 2.4rem; padding-bottom: 2.2rem; border-bottom: 1px dashed #dde8dd; }

.lg-section { margin-bottom: 2.2rem; }
.lg-section-head { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.lg-num {
    flex-shrink: 0; width: 30px; height: 30px; border-radius: 9px;
    background: #eef7ee; color: #2E7D32; font-family: 'Poppins',sans-serif; font-weight: 700; font-size: 13px;
    display: flex; align-items: center; justify-content: center;
}
.lg-section h2 { font-family:'Poppins',sans-serif; font-size: 1.08rem; font-weight: 700; color:#0f2a16; display: flex; align-items: center; gap: 8px; }
.lg-section h2 i { font-size: 13px; color: #2ecc71; }
.lg-section p, .lg-section li { font-size: 14px; line-height: 1.75; color:#33452f; }
.lg-section ul { padding-left: 1.2rem; margin: 0.5rem 0 0; }
.lg-section ul li { margin-bottom: 6px; }
.lg-section ul li::marker { color: #2ecc71; }
.lg-section a { color: #2E7D32; font-weight: 600; text-decoration: none; border-bottom: 1px solid rgba(46,125,50,0.3); }
.lg-section a:hover { border-color: #2E7D32; }

.lg-cta {
    margin-top: 2.6rem; background: linear-gradient(135deg,#0f2a16,#163a1f);
    border-radius: 16px; padding: 1.8rem 2rem; display: flex; align-items: center;
    justify-content: space-between; gap: 1.5rem; flex-wrap: wrap;
    box-shadow: 0 14px 32px rgba(15,42,22,0.18);
}
.lg-cta-text h3 { font-family:'Poppins',sans-serif; color:#fff; font-size: 1.05rem; margin-bottom: 4px; }
.lg-cta-text p { color: rgba(255,255,255,0.6); font-size: 13px; }
.lg-cta-actions { display: flex; gap: 10px; flex-wrap: wrap; }
.lg-cta-actions a {
    font-size: 12.8px; font-weight: 700; padding: 10px 18px; border-radius: 30px; text-decoration: none;
    display: inline-flex; align-items: center; gap: 8px; transition: transform 0.25s ease;
}
.lg-cta-actions a:hover { transform: translateY(-3px); }
.lg-cta-actions a.primary { background: #2ecc71; color: #0b1a14; }
.lg-cta-actions a.ghost { background: rgba(255,255,255,0.08); color: #fff; border: 1px solid rgba(255,255,255,0.18); }

.lg-note { margin-top: 1.6rem; font-size: 12.5px; color: #7a9b7a; text-align: center; }

/* ── Back to top ──────────────────────────────────────── */
.lg-top {
    position: fixed; bottom: 26px; right: 26px; z-index: 50;
    width: 44px; height: 44px; border-radius: 50%; background: #0f2a16; color: #2ecc71;
    display: flex; align-items: center; justify-content: center; border: 1px solid rgba(46,204,113,0.3);
    box-shadow: 0 10px 26px rgba(15,42,22,0.28); cursor: pointer; text-decoration: none;
    opacity: 0; transform: translateY(12px) scale(0.9); pointer-events: none;
    transition: opacity 0.25s ease, transform 0.25s ease, background 0.25s ease;
}
.lg-top.is-visible { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }
.lg-top:hover { background: #2ecc71; color: #0b1a14; transform: translateY(-4px) scale(1.08); }

@media print {
    .lg-top, .lg-hero-print, .lg-glance { display: none !important; }
}
</style>

<div class="lg-wrap">
    <div class="lg-hero">
        <div class="lg-hero-icon"><i class="fa-solid fa-calendar-xmark"></i></div>
        <h1 id="cpTitle">Cancellation Policy</h1>
        <p id="cpSubtitle">How AgriCart handles order, seller, and equipment rental cancellations across the marketplace.</p>
        <div class="lg-hero-meta">
            <span id="cpUpdated">Last updated: July 2026</span><span class="lg-dot">•</span><span id="cpReadTime">4 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="cpPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-circle-xmark"></i>
                <h4 id="cpG1T">Cancel with ease</h4>
                <p id="cpG1D">Cancel eligible orders directly from your account.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-rotate-left"></i>
                <h4 id="cpG2T">Fast refunds</h4>
                <p id="cpG2D">Refunds are initiated within 5–7 business days.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-hourglass-half"></i>
                <h4 id="cpG3T">Clear timelines</h4>
                <p id="cpG3D">Cancellation windows are shown on every order.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-headset"></i>
                <h4 id="cpG4T">Dedicated support</h4>
                <p id="cpG4D">Our helpline assists with cancellation queries.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="cpIntro">AgriCart ("we", "us", "our") operates a marketplace connecting Indian farmers, buyers, and equipment rental partners. This Cancellation Policy explains when and how orders, seller listings, and equipment rental bookings placed on our platform may be cancelled, and the resulting entitlements and responsibilities of AgriCart and its customers.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-cart-shopping"></i> <span id="cpH1">Order Cancellation</span></h2></div>
                <ul>
                    <li id="cpS1L1">Customers may cancel an order free of charge at any time before it has been processed or dispatched by AgriCart or its seller partners.</li>
                    <li id="cpS1L2">Once an order has been processed for dispatch, cancellation cannot be guaranteed and will depend on the shipping and logistics status of the order at that time.</li>
                    <li id="cpS1L3">Cancellation requests must be raised through the "My Orders" section of the customer account or by contacting our support team, quoting the relevant order number.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-shop-slash"></i> <span id="cpH2">Seller Cancellation</span></h2></div>
                <ul>
                    <li id="cpS2L1">AgriCart or the seller/mandi partner reserves the right to cancel an order due to unavailability of produce, pricing or listing errors, quality concerns, or inability to fulfil the order within the estimated timeline.</li>
                    <li id="cpS2L2">Where a seller-initiated cancellation occurs, the customer will be notified promptly and the full order value, including any shipping charges paid, will be refunded.</li>
                    <li id="cpS2L3">AgriCart shall not be liable for any indirect loss, expense, or inconvenience arising from a seller-initiated cancellation, beyond the refund of amounts actually paid.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-tractor"></i> <span id="cpH3">Equipment Rental Cancellation</span></h2></div>
                <ul>
                    <li id="cpS3L1">Equipment rental bookings may be cancelled prior to the scheduled pickup or delivery date, subject to the cancellation window specified at the time of booking.</li>
                    <li id="cpS3L2">Cancellations made within 24 hours of the scheduled rental start time may attract a cancellation fee to cover logistics and scheduling costs already incurred by AgriCart or the rental partner.</li>
                    <li id="cpS3L3">Rental bookings cancelled after the equipment has been dispatched, picked up, or put into use are not eligible for cancellation and will instead be governed by the applicable rental terms.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-hourglass-half"></i> <span id="cpH4">Cancellation Timeline</span></h2></div>
                <ul>
                    <li id="cpS4L1">Cancellation requests for marketplace orders are generally accepted until the order status changes to "Shipped" or "Out for Delivery."</li>
                    <li id="cpS4L2">Equipment rental cancellations must ordinarily be raised at least 24 hours prior to the scheduled start time to avoid cancellation charges, unless a different window is specified for that listing.</li>
                    <li id="cpS4L3">The applicable cancellation deadline for each order or booking is reflected in real time under the respective order or booking details in the customer account.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-rotate-left"></i> <span id="cpH5">Refund After Cancellation</span></h2></div>
                <ul>
                    <li id="cpS5L1">Refunds for successfully cancelled orders are initiated within 5–7 business days to the original payment method, or as store credit where offered and preferred by the customer.</li>
                    <li id="cpS5L2">Where a cancellation fee applies, such as for late equipment rental cancellations, the refund amount will reflect the order value net of such applicable charges.</li>
                    <li id="cpS5L3">Refund timelines may vary depending on the customer's bank or payment provider and are further governed by our Refund Policy.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-ban"></i> <span id="cpH6">Non-Cancellable Orders</span></h2></div>
                <ul>
                    <li id="cpS6L1">Certain categories of orders — including custom-cut produce, made-to-order agricultural inputs, perishable items already dispatched, and personalized listings — may be marked non-cancellable at the time of purchase.</li>
                    <li id="cpS6L2">Orders that have already been shipped or delivered, or rental equipment that has already been picked up or put into use, cannot be cancelled and will instead be governed by our Return Policy or Refund Policy, as applicable.</li>
                    <li id="cpS6L3">Where applicable, non-cancellable status is clearly displayed on the relevant product or rental listing prior to checkout.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-user-check"></i> <span id="cpH7">Customer Responsibilities</span></h2></div>
                <ul>
                    <li id="cpS7L1">Customers are responsible for carefully reviewing order and rental details before confirming a purchase or booking, as cancellation eligibility depends on timely action.</li>
                    <li id="cpS7L2">Customers must raise cancellation requests only through the official AgriCart platform or designated support channels, to ensure the request is accurately recorded and processed.</li>
                    <li id="cpS7L3">Repeated, abusive, or fraudulent cancellation requests may result in restrictions on a customer's ability to place future orders or rental bookings.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-headset"></i> <span id="cpH8">Contact Information</span></h2></div>
                <p id="cpP8">For assistance with order or rental cancellations, please reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our helpline at 1800-419-8888.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-clock-rotate-left"></i> <span id="cpH9">Last Updated</span></h2></div>
                <p id="cpP9">This Cancellation Policy was last updated in July 2026. AgriCart may revise this policy from time to time to reflect changes in our marketplace, rental operations, or applicable law; significant changes will be communicated on this page with a revised "Last updated" date.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="cpCtaTitle">Need help cancelling an order?</h3>
                    <p id="cpCtaSub">Our support team typically replies within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="cpCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="cpCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="cpNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.</p>
        </main>
        </div>
    </div>
</div>

<a href="#top" class="lg-top" id="backToTop" aria-label="Back to top"><i class="fa-solid fa-arrow-up"></i></a>

<script>
(function () {
    var backToTop = document.getElementById('backToTop');
    function onScroll() {
        if (backToTop) backToTop.classList.toggle('is-visible', window.scrollY > 500);
    }
    document.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    var CP_I18N = {
        en: {
            cpHome: 'Home', cpCrumb: 'Cancellation Policy',
            cpTitle: 'Cancellation Policy',
            cpSubtitle: 'How AgriCart handles order, seller, and equipment rental cancellations across the marketplace.',
            cpUpdated: 'Last updated: July 2026', cpReadTime: '4 min read', cpPrint: 'Print / Save PDF',
            cpG1T: 'Cancel with ease', cpG1D: 'Cancel eligible orders directly from your account.',
            cpG2T: 'Fast refunds', cpG2D: 'Refunds are initiated within 5–7 business days.',
            cpG3T: 'Clear timelines', cpG3D: 'Cancellation windows are shown on every order.',
            cpG4T: 'Dedicated support', cpG4D: 'Our helpline assists with cancellation queries.',
            cpIntro: 'AgriCart ("we", "us", "our") operates a marketplace connecting Indian farmers, buyers, and equipment rental partners. This Cancellation Policy explains when and how orders, seller listings, and equipment rental bookings placed on our platform may be cancelled, and the resulting entitlements and responsibilities of AgriCart and its customers.',
            cpH1: 'Order Cancellation',
            cpS1L1: 'Customers may cancel an order free of charge at any time before it has been processed or dispatched by AgriCart or its seller partners.',
            cpS1L2: 'Once an order has been processed for dispatch, cancellation cannot be guaranteed and will depend on the shipping and logistics status of the order at that time.',
            cpS1L3: 'Cancellation requests must be raised through the "My Orders" section of the customer account or by contacting our support team, quoting the relevant order number.',
            cpH2: 'Seller Cancellation',
            cpS2L1: 'AgriCart or the seller/mandi partner reserves the right to cancel an order due to unavailability of produce, pricing or listing errors, quality concerns, or inability to fulfil the order within the estimated timeline.',
            cpS2L2: 'Where a seller-initiated cancellation occurs, the customer will be notified promptly and the full order value, including any shipping charges paid, will be refunded.',
            cpS2L3: 'AgriCart shall not be liable for any indirect loss, expense, or inconvenience arising from a seller-initiated cancellation, beyond the refund of amounts actually paid.',
            cpH3: 'Equipment Rental Cancellation',
            cpS3L1: 'Equipment rental bookings may be cancelled prior to the scheduled pickup or delivery date, subject to the cancellation window specified at the time of booking.',
            cpS3L2: 'Cancellations made within 24 hours of the scheduled rental start time may attract a cancellation fee to cover logistics and scheduling costs already incurred by AgriCart or the rental partner.',
            cpS3L3: 'Rental bookings cancelled after the equipment has been dispatched, picked up, or put into use are not eligible for cancellation and will instead be governed by the applicable rental terms.',
            cpH4: 'Cancellation Timeline',
            cpS4L1: 'Cancellation requests for marketplace orders are generally accepted until the order status changes to "Shipped" or "Out for Delivery."',
            cpS4L2: 'Equipment rental cancellations must ordinarily be raised at least 24 hours prior to the scheduled start time to avoid cancellation charges, unless a different window is specified for that listing.',
            cpS4L3: "The applicable cancellation deadline for each order or booking is reflected in real time under the respective order or booking details in the customer account.",
            cpH5: 'Refund After Cancellation',
            cpS5L1: 'Refunds for successfully cancelled orders are initiated within 5–7 business days to the original payment method, or as store credit where offered and preferred by the customer.',
            cpS5L2: 'Where a cancellation fee applies, such as for late equipment rental cancellations, the refund amount will reflect the order value net of such applicable charges.',
            cpS5L3: 'Refund timelines may vary depending on the customer\'s bank or payment provider and are further governed by our Refund Policy.',
            cpH6: 'Non-Cancellable Orders',
            cpS6L1: 'Certain categories of orders — including custom-cut produce, made-to-order agricultural inputs, perishable items already dispatched, and personalized listings — may be marked non-cancellable at the time of purchase.',
            cpS6L2: 'Orders that have already been shipped or delivered, or rental equipment that has already been picked up or put into use, cannot be cancelled and will instead be governed by our Return Policy or Refund Policy, as applicable.',
            cpS6L3: 'Where applicable, non-cancellable status is clearly displayed on the relevant product or rental listing prior to checkout.',
            cpH7: 'Customer Responsibilities',
            cpS7L1: 'Customers are responsible for carefully reviewing order and rental details before confirming a purchase or booking, as cancellation eligibility depends on timely action.',
            cpS7L2: 'Customers must raise cancellation requests only through the official AgriCart platform or designated support channels, to ensure the request is accurately recorded and processed.',
            cpS7L3: "Repeated, abusive, or fraudulent cancellation requests may result in restrictions on a customer's ability to place future orders or rental bookings.",
            cpH8: 'Contact Information',
            cpP8: 'For assistance with order or rental cancellations, please reach us at support@agricart.in or call our helpline at 1800-419-8888.',
            cpH9: 'Last Updated',
            cpP9: 'This Cancellation Policy was last updated in July 2026. AgriCart may revise this policy from time to time to reflect changes in our marketplace, rental operations, or applicable law; significant changes will be communicated on this page with a revised "Last updated" date.',
            cpCtaTitle: 'Need help cancelling an order?', cpCtaSub: 'Our support team typically replies within a few hours.',
            cpCtaEmail: 'Email Us', cpCtaCall: 'Call Helpline',
            cpNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.'
        },
        hi: {
            cpHome: 'मुखपृष्ठ', cpCrumb: 'रद्दीकरण नीति',
            cpTitle: 'रद्दीकरण नीति',
            cpSubtitle: 'एग्रीकार्ट मार्केटप्लेस में ऑर्डर, विक्रेता और उपकरण रेंटल रद्दीकरण को कैसे संभालता है।',
            cpUpdated: 'अंतिम अपडेट: जुलाई 2026', cpReadTime: '4 मिनट में पढ़ें', cpPrint: 'प्रिंट / पीडीएफ सेव करें',
            cpG1T: 'आसानी से रद्द करें', cpG1D: 'अपने खाते से पात्र ऑर्डर सीधे रद्द करें।',
            cpG2T: 'तेज़ रिफंड', cpG2D: 'रिफंड 5–7 कार्य दिवसों के भीतर शुरू किए जाते हैं।',
            cpG3T: 'स्पष्ट समय-सीमाएँ', cpG3D: 'हर ऑर्डर पर रद्दीकरण विंडो दिखाई जाती है।',
            cpG4T: 'समर्पित सहायता', cpG4D: 'रद्दीकरण संबंधी प्रश्नों में हमारी हेल्पलाइन मदद करती है।',
            cpIntro: 'एग्रीकार्ट ("हम", "हमें", "हमारा") भारतीय किसानों, खरीदारों और उपकरण रेंटल पार्टनर को जोड़ने वाला एक मार्केटप्लेस संचालित करता है। यह रद्दीकरण नीति बताती है कि हमारे प्लेटफ़ॉर्म पर दिए गए ऑर्डर, विक्रेता लिस्टिंग और उपकरण रेंटल बुकिंग कब और कैसे रद्द किए जा सकते हैं, और इसके परिणामस्वरूप एग्रीकार्ट तथा ग्राहकों के अधिकार व जिम्मेदारियाँ क्या हैं।',
            cpH1: 'ऑर्डर रद्दीकरण',
            cpS1L1: 'ग्राहक किसी ऑर्डर को एग्रीकार्ट या उसके विक्रेता पार्टनर द्वारा प्रोसेस या डिस्पैच किए जाने से पहले किसी भी समय बिना किसी शुल्क के रद्द कर सकते हैं।',
            cpS1L2: 'एक बार ऑर्डर डिस्पैच के लिए प्रोसेस हो जाने के बाद, रद्दीकरण की गारंटी नहीं दी जा सकती और यह उस समय ऑर्डर की शिपिंग व लॉजिस्टिक्स स्थिति पर निर्भर करेगा।',
            cpS1L3: 'रद्दीकरण अनुरोध ग्राहक खाते के "माय ऑर्डर्स" सेक्शन के माध्यम से या हमारी सहायता टीम से संबंधित ऑर्डर नंबर बताकर संपर्क करके दर्ज किया जाना चाहिए।',
            cpH2: 'विक्रेता द्वारा रद्दीकरण',
            cpS2L1: 'एग्रीकार्ट या विक्रेता/मंडी पार्टनर उपज की अनुपलब्धता, मूल्य या लिस्टिंग त्रुटियों, गुणवत्ता संबंधी चिंताओं, या अनुमानित समय-सीमा के भीतर ऑर्डर पूरा करने में असमर्थता के कारण ऑर्डर रद्द करने का अधिकार सुरक्षित रखता है।',
            cpS2L2: 'विक्रेता द्वारा रद्दीकरण की स्थिति में, ग्राहक को तुरंत सूचित किया जाएगा और भुगतान किए गए शिपिंग शुल्क सहित पूरी ऑर्डर राशि वापस की जाएगी।',
            cpS2L3: 'एग्रीकार्ट भुगतान की गई राशि की वापसी से परे, विक्रेता द्वारा रद्दीकरण से उत्पन्न किसी भी अप्रत्यक्ष हानि, व्यय या असुविधा के लिए उत्तरदायी नहीं होगा।',
            cpH3: 'उपकरण रेंटल रद्दीकरण',
            cpS3L1: 'उपकरण रेंटल बुकिंग को निर्धारित पिकअप या डिलीवरी तारीख से पहले, बुकिंग के समय निर्दिष्ट रद्दीकरण विंडो के अधीन, रद्द किया जा सकता है।',
            cpS3L2: 'निर्धारित रेंटल शुरू होने के समय से 24 घंटे के भीतर की गई रद्दीकरण पर, एग्रीकार्ट या रेंटल पार्टनर द्वारा पहले से किए गए लॉजिस्टिक्स और शेड्यूलिंग खर्च को कवर करने के लिए रद्दीकरण शुल्क लागू हो सकता है।',
            cpS3L3: 'उपकरण डिस्पैच, पिकअप या उपयोग में लाए जाने के बाद रद्द की गई रेंटल बुकिंग रद्दीकरण के लिए पात्र नहीं होगी और इसके बजाय लागू रेंटल शर्तों द्वारा नियंत्रित होगी।',
            cpH4: 'रद्दीकरण की समय-सीमा',
            cpS4L1: 'मार्केटप्लेस ऑर्डर के लिए रद्दीकरण अनुरोध आमतौर पर तब तक स्वीकार किए जाते हैं जब तक ऑर्डर की स्थिति "शिप्ड" या "आउट फॉर डिलीवरी" में न बदल जाए।',
            cpS4L2: 'रद्दीकरण शुल्क से बचने के लिए उपकरण रेंटल रद्दीकरण सामान्यतः निर्धारित शुरुआत समय से कम से कम 24 घंटे पहले दर्ज किया जाना चाहिए, जब तक कि उस लिस्टिंग के लिए कोई अलग विंडो निर्दिष्ट न हो।',
            cpS4L3: 'प्रत्येक ऑर्डर या बुकिंग के लिए लागू रद्दीकरण की अंतिम तारीख ग्राहक खाते में संबंधित ऑर्डर या बुकिंग विवरण के अंतर्गत रीयल टाइम में दर्शाई जाती है।',
            cpH5: 'रद्दीकरण के बाद रिफंड',
            cpS5L1: 'सफलतापूर्वक रद्द किए गए ऑर्डर के लिए रिफंड मूल भुगतान माध्यम में, या जहां उपलब्ध और ग्राहक द्वारा पसंद किया गया हो वहां स्टोर क्रेडिट के रूप में, 5–7 कार्य दिवसों के भीतर शुरू किया जाता है।',
            cpS5L2: 'जहां रद्दीकरण शुल्क लागू होता है, जैसे विलंबित उपकरण रेंटल रद्दीकरण, वहां रिफंड राशि ऐसे लागू शुल्कों को घटाकर ऑर्डर मूल्य दर्शाएगी।',
            cpS5L3: 'रिफंड की समय-सीमा ग्राहक के बैंक या भुगतान प्रदाता के आधार पर भिन्न हो सकती है और आगे हमारी रिफंड नीति द्वारा नियंत्रित होती है।',
            cpH6: 'रद्द न किए जा सकने वाले ऑर्डर',
            cpS6L1: 'कुछ श्रेणियों के ऑर्डर — जिनमें कस्टम-कट उपज, ऑर्डर पर बनने वाले कृषि इनपुट, पहले से डिस्पैच की जा चुकी खराब होने वाली वस्तुएं, और वैयक्तिकृत लिस्टिंग शामिल हैं — खरीद के समय रद्द न किए जा सकने वाले के रूप में चिह्नित हो सकते हैं।',
            cpS6L2: 'जो ऑर्डर पहले ही शिप या डिलीवर किए जा चुके हैं, या जिनका रेंटल उपकरण पहले ही पिकअप या उपयोग में लाया जा चुका है, उन्हें रद्द नहीं किया जा सकता और इसके बजाय, यथास्थिति, हमारी रिटर्न नीति या रिफंड नीति द्वारा नियंत्रित होंगे।',
            cpS6L3: 'जहां लागू हो, चेकआउट से पहले संबंधित उत्पाद या रेंटल लिस्टिंग पर रद्द न किए जा सकने की स्थिति स्पष्ट रूप से दर्शाई जाती है।',
            cpH7: 'ग्राहक की जिम्मेदारियाँ',
            cpS7L1: 'ग्राहक खरीद या बुकिंग की पुष्टि करने से पहले ऑर्डर और रेंटल विवरण को ध्यानपूर्वक जांचने के लिए जिम्मेदार हैं, क्योंकि रद्दीकरण की पात्रता समय पर की गई कार्रवाई पर निर्भर करती है।',
            cpS7L2: 'ग्राहकों को रद्दीकरण अनुरोध केवल आधिकारिक एग्रीकार्ट प्लेटफ़ॉर्म या निर्दिष्ट सहायता चैनलों के माध्यम से दर्ज करना चाहिए, ताकि अनुरोध सटीक रूप से दर्ज और प्रोसेस हो सके।',
            cpS7L3: 'बार-बार, दुरुपयोगपूर्ण, या धोखाधड़ी वाले रद्दीकरण अनुरोधों के परिणामस्वरूप ग्राहक की भविष्य में ऑर्डर या रेंटल बुकिंग करने की क्षमता पर प्रतिबंध लग सकता है।',
            cpH8: 'संपर्क जानकारी',
            cpP8: 'ऑर्डर या रेंटल रद्दीकरण में सहायता के लिए, कृपया हमसे support@agricart.in पर संपर्क करें या हमारी हेल्पलाइन 1800-419-8888 पर कॉल करें।',
            cpH9: 'अंतिम अपडेट',
            cpP9: 'यह रद्दीकरण नीति अंतिम बार जुलाई 2026 में अपडेट की गई थी। एग्रीकार्ट अपने मार्केटप्लेस, रेंटल संचालन या लागू कानून में बदलाव को दर्शाने के लिए समय-समय पर इस नीति में संशोधन कर सकता है; महत्वपूर्ण बदलाव इस पेज पर संशोधित "अंतिम अपडेट" तारीख के साथ बताए जाएंगे।',
            cpCtaTitle: 'ऑर्डर रद्द करने में मदद चाहिए?', cpCtaSub: 'हमारी सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            cpCtaEmail: 'ईमेल करें', cpCtaCall: 'हेल्पलाइन कॉल करें',
            cpNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक नीति के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            cpHome: 'मुखपृष्ठ', cpCrumb: 'रद्दीकरण धोरण',
            cpTitle: 'रद्दीकरण धोरण',
            cpSubtitle: 'अ‍ॅग्रीकार्ट मार्केटप्लेसमध्ये ऑर्डर, विक्रेता आणि उपकरण भाडे रद्दीकरण कसे हाताळले जाते.',
            cpUpdated: 'शेवटचे अद्ययावत: जुलै 2026', cpReadTime: '4 मिनिटांत वाचा', cpPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            cpG1T: 'सहज रद्द करा', cpG1D: 'तुमच्या खात्यातून पात्र ऑर्डर थेट रद्द करा.',
            cpG2T: 'जलद परतावा', cpG2D: 'परतावा 5–7 कामकाजाच्या दिवसांत सुरू केला जातो.',
            cpG3T: 'स्पष्ट कालमर्यादा', cpG3D: 'प्रत्येक ऑर्डरवर रद्दीकरण विंडो दर्शवली जाते.',
            cpG4T: 'समर्पित सहाय्य', cpG4D: 'रद्दीकरणाशी संबंधित प्रश्नांमध्ये आमची हेल्पलाइन मदत करते.',
            cpIntro: 'अ‍ॅग्रीकार्ट ("आम्ही", "आम्हाला", "आमचे") भारतीय शेतकरी, खरेदीदार आणि उपकरण भाडे भागीदारांना जोडणारे मार्केटप्लेस चालवते. हे रद्दीकरण धोरण स्पष्ट करते की आमच्या प्लॅटफॉर्मवर दिलेले ऑर्डर, विक्रेता लिस्टिंग आणि उपकरण भाडे बुकिंग केव्हा आणि कशी रद्द केली जाऊ शकतात, आणि यामुळे अ‍ॅग्रीकार्ट व ग्राहकांचे हक्क व जबाबदाऱ्या काय आहेत.',
            cpH1: 'ऑर्डर रद्दीकरण',
            cpS1L1: 'अ‍ॅग्रीकार्ट किंवा त्याच्या विक्रेता भागीदाराद्वारे ऑर्डर प्रक्रिया किंवा डिस्पॅच होण्यापूर्वी ग्राहक कोणत्याही वेळी विनाशुल्क ऑर्डर रद्द करू शकतात.',
            cpS1L2: 'एकदा ऑर्डर डिस्पॅचसाठी प्रक्रिया झाल्यानंतर, रद्दीकरणाची हमी दिली जाऊ शकत नाही आणि ते त्या वेळी ऑर्डरच्या शिपिंग व लॉजिस्टिक्स स्थितीवर अवलंबून असेल.',
            cpS1L3: 'रद्दीकरण विनंत्या ग्राहक खात्यातील "माय ऑर्डर्स" विभागाद्वारे किंवा संबंधित ऑर्डर क्रमांक नमूद करून आमच्या सहाय्य टीमशी संपर्क साधून नोंदवल्या पाहिजेत.',
            cpH2: 'विक्रेत्याद्वारे रद्दीकरण',
            cpS2L1: 'अ‍ॅग्रीकार्ट किंवा विक्रेता/मंडई भागीदार उत्पादनाची अनुपलब्धता, किंमत किंवा लिस्टिंग त्रुटी, गुणवत्ता संबंधित चिंता, किंवा अंदाजे कालमर्यादेत ऑर्डर पूर्ण करण्यास असमर्थता यामुळे ऑर्डर रद्द करण्याचा अधिकार राखून ठेवतो.',
            cpS2L2: 'विक्रेत्याद्वारे रद्दीकरण झाल्यास, ग्राहकाला तात्काळ कळवले जाईल आणि भरलेल्या शिपिंग शुल्कासह संपूर्ण ऑर्डर रक्कम परत केली जाईल.',
            cpS2L3: 'अ‍ॅग्रीकार्ट भरलेल्या रकमेच्या परताव्याव्यतिरिक्त, विक्रेत्याद्वारे रद्दीकरणामुळे उद्भवणाऱ्या कोणत्याही अप्रत्यक्ष नुकसान, खर्च किंवा गैरसोयीसाठी जबाबदार राहणार नाही.',
            cpH3: 'उपकरण भाडे रद्दीकरण',
            cpS3L1: 'उपकरण भाडे बुकिंग नियोजित पिकअप किंवा डिलिव्हरी तारखेपूर्वी, बुकिंगच्या वेळी नमूद केलेल्या रद्दीकरण विंडोच्या अधीन राहून रद्द केली जाऊ शकते.',
            cpS3L2: 'नियोजित भाडे सुरू होण्याच्या वेळेच्या 24 तासांच्या आत केलेल्या रद्दीकरणावर, अ‍ॅग्रीकार्ट किंवा भाडे भागीदाराने आधीच केलेल्या लॉजिस्टिक्स व वेळापत्रक खर्चाची भरपाई करण्यासाठी रद्दीकरण शुल्क लागू होऊ शकते.',
            cpS3L3: 'उपकरण डिस्पॅच, पिकअप किंवा वापरात आणल्यानंतर रद्द केलेली भाडे बुकिंग रद्दीकरणासाठी पात्र राहणार नाही आणि त्याऐवजी लागू भाडे अटींद्वारे नियंत्रित केली जाईल.',
            cpH4: 'रद्दीकरण कालमर्यादा',
            cpS4L1: 'मार्केटप्लेस ऑर्डरसाठी रद्दीकरण विनंत्या साधारणपणे ऑर्डरची स्थिती "शिप्ड" किंवा "आउट फॉर डिलिव्हरी" मध्ये बदलेपर्यंत स्वीकारल्या जातात.',
            cpS4L2: 'रद्दीकरण शुल्क टाळण्यासाठी उपकरण भाडे रद्दीकरण साधारणपणे नियोजित सुरुवातीच्या वेळेच्या किमान 24 तास आधी नोंदवले जाणे आवश्यक आहे, जोपर्यंत त्या लिस्टिंगसाठी वेगळी विंडो नमूद केलेली नसेल.',
            cpS4L3: 'प्रत्येक ऑर्डर किंवा बुकिंगसाठी लागू रद्दीकरण अंतिम मुदत ग्राहक खात्यातील संबंधित ऑर्डर किंवा बुकिंग तपशीलाखाली रिअल टाइममध्ये दर्शवली जाते.',
            cpH5: 'रद्दीकरणानंतर परतावा',
            cpS5L1: 'यशस्वीरित्या रद्द केलेल्या ऑर्डरसाठी परतावा मूळ पेमेंट पद्धतीत, किंवा उपलब्ध असल्यास व ग्राहकाने प्राधान्य दिल्यास स्टोअर क्रेडिट म्हणून, 5–7 कामकाजाच्या दिवसांत सुरू केला जातो.',
            cpS5L2: 'जिथे रद्दीकरण शुल्क लागू होते, जसे की उशिरा केलेले उपकरण भाडे रद्दीकरण, तिथे परतावा रक्कम अशा लागू शुल्कांची वजावट करून ऑर्डर मूल्य दर्शवेल.',
            cpS5L3: 'परताव्याची कालमर्यादा ग्राहकाच्या बँक किंवा पेमेंट प्रदात्यावर अवलंबून बदलू शकते आणि पुढे आमच्या परतावा धोरणाद्वारे नियंत्रित केली जाते.',
            cpH6: 'रद्द न करता येणारे ऑर्डर',
            cpS6L1: 'काही श्रेणीतील ऑर्डर — ज्यात कस्टम-कट उत्पादन, ऑर्डरनुसार तयार होणारे कृषी इनपुट, आधीच पाठवलेल्या नाशवंत वस्तू, आणि वैयक्तिकृत लिस्टिंग यांचा समावेश आहे — खरेदीच्या वेळी रद्द न करता येणारे म्हणून चिन्हांकित केले जाऊ शकतात.',
            cpS6L2: 'जे ऑर्डर आधीच पाठवले किंवा वितरित केले गेले आहेत, किंवा ज्यांचे भाडे उपकरण आधीच घेतले किंवा वापरात आणले गेले आहे, ते रद्द केले जाऊ शकत नाहीत आणि त्याऐवजी, लागू असल्यास, आमच्या परतावा धोरण किंवा रिफंड धोरणाद्वारे नियंत्रित केले जातील.',
            cpS6L3: 'लागू असल्यास, चेकआउटपूर्वी संबंधित उत्पादन किंवा भाडे लिस्टिंगवर रद्द न करता येण्याची स्थिती स्पष्टपणे दर्शवली जाते.',
            cpH7: 'ग्राहकांच्या जबाबदाऱ्या',
            cpS7L1: 'ग्राहक खरेदी किंवा बुकिंगची पुष्टी करण्यापूर्वी ऑर्डर आणि भाडे तपशील काळजीपूर्वक तपासण्यास जबाबदार आहेत, कारण रद्दीकरण पात्रता वेळेवर केलेल्या कृतीवर अवलंबून असते.',
            cpS7L2: 'ग्राहकांनी रद्दीकरण विनंत्या केवळ अधिकृत अ‍ॅग्रीकार्ट प्लॅटफॉर्म किंवा नियुक्त सहाय्य माध्यमांद्वारेच नोंदवाव्यात, जेणेकरून विनंती अचूकपणे नोंदवली आणि प्रक्रिया केली जाईल.',
            cpS7L3: 'वारंवार, गैरवापर करणाऱ्या किंवा फसव्या रद्दीकरण विनंत्यांमुळे ग्राहकाच्या भविष्यातील ऑर्डर किंवा भाडे बुकिंग करण्याच्या क्षमतेवर निर्बंध येऊ शकतात.',
            cpH8: 'संपर्क माहिती',
            cpP8: 'ऑर्डर किंवा भाडे रद्दीकरणासंदर्भात सहाय्यासाठी, कृपया आमच्याशी support@agricart.in वर संपर्क साधा किंवा आमच्या हेल्पलाइन 1800-419-8888 वर कॉल करा.',
            cpH9: 'शेवटचे अद्ययावत',
            cpP9: 'हे रद्दीकरण धोरण शेवटचे जुलै 2026 मध्ये अद्ययावत केले गेले. अ‍ॅग्रीकार्ट आपल्या मार्केटप्लेस, भाडे कार्यपद्धती किंवा लागू कायद्यातील बदल प्रतिबिंबित करण्यासाठी वेळोवेळी या धोरणात सुधारणा करू शकते; महत्त्वाचे बदल या पानावर सुधारित "शेवटचे अद्ययावत" तारखेसह कळवले जातील.',
            cpCtaTitle: 'ऑर्डर रद्द करण्यासाठी मदत हवी आहे?', cpCtaSub: 'आमची सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            cpCtaEmail: 'ईमेल करा', cpCtaCall: 'हेल्पलाइनला कॉल करा',
            cpNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत धोरण म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applyCPLang(lang) {
        var dict = CP_I18N[lang] || CP_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applyCPLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applyCPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applyCPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
