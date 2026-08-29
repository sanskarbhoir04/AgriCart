<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Shipping & Delivery Policy Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\shipping-delivery-policy.php
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
        <div class="lg-hero-icon"><i class="fa-solid fa-truck-fast"></i></div>
        <h1 id="sdTitle">Shipping &amp; Delivery Policy</h1>
        <p id="sdSubtitle">How AgriCart processes, ships, and delivers your marketplace orders across India — from confirmation to your doorstep.</p>
        <div class="lg-hero-meta">
            <span id="sdUpdated">Last updated: July 2026</span><span class="lg-dot">•</span><span id="sdReadTime">5 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="sdPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-earth-asia"></i>
                <h4 id="sdG1T">Pan-India delivery</h4>
                <p id="sdG1D">We ship to serviceable pin codes across the country.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-location-crosshairs"></i>
                <h4 id="sdG2T">Real-time tracking</h4>
                <p id="sdG2D">Track every order from dispatch to doorstep.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-box"></i>
                <h4 id="sdG3T">Careful packaging</h4>
                <p id="sdG3D">Produce and equipment packed to reduce transit damage.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-headset"></i>
                <h4 id="sdG4T">Dedicated support</h4>
                <p id="sdG4D">Our helpline assists with delays, damage, or loss.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="sdIntro">AgriCart ("we", "us", "our") operates a marketplace connecting Indian farmers, buyers, and equipment rental partners. This Shipping &amp; Delivery Policy explains how orders placed on our platform are processed, shipped, tracked, and delivered, and sets out the respective responsibilities of AgriCart and its customers in relation to delivery.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-clipboard-check"></i> <span id="sdH1">Order Processing</span></h2></div>
                <ul>
                    <li id="sdS1L1">Orders are processed and handed over to our logistics partners within 1–3 business days of payment confirmation, subject to product availability and seller/mandi confirmation.</li>
                    <li id="sdS1L2">Orders placed after 5:00 PM IST, or on Sundays and public holidays, are processed on the next working business day.</li>
                    <li id="sdS1L3">Bulk orders, custom equipment rentals, and made-to-order agricultural produce may require additional processing time, which will be communicated at the time of booking.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-truck"></i> <span id="sdH2">Shipping Methods</span></h2></div>
                <ul>
                    <li id="sdS2L1">AgriCart ships through a network of authorized courier partners, regional transport agencies, and cold-chain logistics providers, selected based on the nature of the goods ordered.</li>
                    <li id="sdS2L2">Perishable produce, seeds, and dairy items are dispatched through temperature-controlled or expedited logistics channels wherever operationally available.</li>
                    <li id="sdS2L3">Equipment, machinery, and heavy agricultural inputs may be delivered through designated freight carriers or last-mile delivery partners.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-clock"></i> <span id="sdH3">Delivery Time</span></h2></div>
                <ul>
                    <li id="sdS3L1">Estimated delivery timelines range from 2 to 10 business days, depending on the delivery location, product category, and serviceability of the destination pin code.</li>
                    <li id="sdS3L2">Delivery timelines displayed at checkout and in order confirmations are estimates only and do not constitute a guaranteed delivery date.</li>
                    <li id="sdS3L3">Time-sensitive perishable items are prioritized for expedited dispatch wherever operationally feasible.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-indian-rupee-sign"></i> <span id="sdH4">Shipping Charges</span></h2></div>
                <ul>
                    <li id="sdS4L1">Shipping charges are calculated based on order weight, dimensions, product category, delivery distance, and the shipping method selected at checkout.</li>
                    <li id="sdS4L2">AgriCart may offer free or discounted shipping on qualifying orders, promotional campaigns, or subscription plans, as displayed at the time of purchase.</li>
                    <li id="sdS4L3">All applicable shipping charges are disclosed prior to payment and reflected in the final order summary before checkout is completed.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-location-dot"></i> <span id="sdH5">Delivery Locations</span></h2></div>
                <ul>
                    <li id="sdS5L1">We currently deliver across serviceable pin codes throughout India, subject to courier partner coverage and applicable local regulatory permissions.</li>
                    <li id="sdS5L2">Certain remote, restricted, or logistically inaccessible areas may not be serviceable; the checkout system will indicate serviceability at the time of order placement.</li>
                    <li id="sdS5L3">International shipping is not currently supported, unless expressly stated for a specific product listing.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-magnifying-glass-location"></i> <span id="sdH6">Order Tracking</span></h2></div>
                <ul>
                    <li id="sdS6L1">Upon dispatch, customers receive a tracking number and shipment status updates via SMS, email, and/or the AgriCart account dashboard.</li>
                    <li id="sdS6L2">Real-time tracking status can be viewed at any time under "My Orders" in the customer account.</li>
                    <li id="sdS6L3">Customers are encouraged to keep their registered contact number and email address active and up to date to ensure timely delivery notifications.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-triangle-exclamation"></i> <span id="sdH7">Delayed Shipments</span></h2></div>
                <ul>
                    <li id="sdS7L1">While AgriCart endeavors to meet estimated delivery timelines, delays may occur due to adverse weather, transport strikes, regulatory checks, natural calamities, or other unforeseen operational disruptions.</li>
                    <li id="sdS7L2">Customers will be notified of significant delays through the order tracking system or direct communication from our support team.</li>
                    <li id="sdS7L3">AgriCart shall not be held liable for delays arising from circumstances beyond its reasonable control, including events of force majeure.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-circle-xmark"></i> <span id="sdH8">Failed Delivery Attempts</span></h2></div>
                <ul>
                    <li id="sdS8L1">Our logistics partners typically make up to 2–3 delivery attempts before an order is marked as undeliverable.</li>
                    <li id="sdS8L2">Where a delivery attempt fails due to an incorrect address, unavailability of the recipient, or refusal to accept the shipment, additional delivery attempts or re-shipping charges may apply.</li>
                    <li id="sdS8L3">Orders that remain undelivered after repeated attempts may be returned to the originating warehouse, with applicable refunds — net of shipping and handling costs — processed in accordance with our Refund Policy.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-box-open"></i> <span id="sdH9">Damaged or Lost Packages</span></h2></div>
                <ul>
                    <li id="sdS9L1">Customers are requested to inspect packages at the time of delivery and report any visible damage to the delivery agent before accepting the shipment.</li>
                    <li id="sdS9L2">Claims relating to damaged, tampered, or lost packages must be reported to AgriCart support within 48 hours of delivery (or the expected delivery date, in the case of a lost shipment), accompanied by photographic evidence where applicable.</li>
                    <li id="sdS9L3">Verified claims will be resolved through replacement, repair, or refund, in accordance with our Return Policy and Refund Policy.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">10</span><h2><i class="fa-solid fa-user-check"></i> <span id="sdH10">Customer Responsibilities</span></h2></div>
                <ul>
                    <li id="sdS10L1">Customers must provide accurate, complete, and current delivery information, including address, pin code, and contact number, at the time of order placement.</li>
                    <li id="sdS10L2">AgriCart shall not be responsible for delivery failures, misdeliveries, or delays resulting from incorrect, incomplete, or outdated address information supplied by the customer.</li>
                    <li id="sdS10L3">Customers are responsible for being reasonably available to receive deliveries within the estimated delivery window communicated for their order.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">11</span><h2><i class="fa-solid fa-headset"></i> <span id="sdH11">Contact Information</span></h2></div>
                <p id="sdP11">For shipping-related queries, order tracking assistance, or delivery escalations, please reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our helpline at 1800-419-8888.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">12</span><h2><i class="fa-solid fa-clock-rotate-left"></i> <span id="sdH12">Last Updated</span></h2></div>
                <p id="sdP12">This Shipping &amp; Delivery Policy was last updated in July 2026. AgriCart may revise this policy from time to time to reflect changes in our logistics operations or applicable law; significant changes will be communicated on this page with a revised "Last updated" date.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="sdCtaTitle">Have a question about your delivery?</h3>
                    <p id="sdCtaSub">Our support team typically replies within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="sdCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="sdCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="sdNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.</p>
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

    var SD_I18N = {
        en: {
            sdHome: 'Home', sdCrumb: 'Shipping & Delivery Policy',
            sdTitle: 'Shipping & Delivery Policy',
            sdSubtitle: 'How AgriCart processes, ships, and delivers your marketplace orders across India — from confirmation to your doorstep.',
            sdUpdated: 'Last updated: July 2026', sdReadTime: '5 min read', sdPrint: 'Print / Save PDF',
            sdG1T: 'Pan-India delivery', sdG1D: 'We ship to serviceable pin codes across the country.',
            sdG2T: 'Real-time tracking', sdG2D: 'Track every order from dispatch to doorstep.',
            sdG3T: 'Careful packaging', sdG3D: 'Produce and equipment packed to reduce transit damage.',
            sdG4T: 'Dedicated support', sdG4D: 'Our helpline assists with delays, damage, or loss.',
            sdIntro: 'AgriCart ("we", "us", "our") operates a marketplace connecting Indian farmers, buyers, and equipment rental partners. This Shipping & Delivery Policy explains how orders placed on our platform are processed, shipped, tracked, and delivered, and sets out the respective responsibilities of AgriCart and its customers in relation to delivery.',
            sdH1: 'Order Processing',
            sdS1L1: 'Orders are processed and handed over to our logistics partners within 1–3 business days of payment confirmation, subject to product availability and seller/mandi confirmation.',
            sdS1L2: 'Orders placed after 5:00 PM IST, or on Sundays and public holidays, are processed on the next working business day.',
            sdS1L3: 'Bulk orders, custom equipment rentals, and made-to-order agricultural produce may require additional processing time, which will be communicated at the time of booking.',
            sdH2: 'Shipping Methods',
            sdS2L1: 'AgriCart ships through a network of authorized courier partners, regional transport agencies, and cold-chain logistics providers, selected based on the nature of the goods ordered.',
            sdS2L2: 'Perishable produce, seeds, and dairy items are dispatched through temperature-controlled or expedited logistics channels wherever operationally available.',
            sdS2L3: 'Equipment, machinery, and heavy agricultural inputs may be delivered through designated freight carriers or last-mile delivery partners.',
            sdH3: 'Delivery Time',
            sdS3L1: 'Estimated delivery timelines range from 2 to 10 business days, depending on the delivery location, product category, and serviceability of the destination pin code.',
            sdS3L2: 'Delivery timelines displayed at checkout and in order confirmations are estimates only and do not constitute a guaranteed delivery date.',
            sdS3L3: 'Time-sensitive perishable items are prioritized for expedited dispatch wherever operationally feasible.',
            sdH4: 'Shipping Charges',
            sdS4L1: 'Shipping charges are calculated based on order weight, dimensions, product category, delivery distance, and the shipping method selected at checkout.',
            sdS4L2: 'AgriCart may offer free or discounted shipping on qualifying orders, promotional campaigns, or subscription plans, as displayed at the time of purchase.',
            sdS4L3: 'All applicable shipping charges are disclosed prior to payment and reflected in the final order summary before checkout is completed.',
            sdH5: 'Delivery Locations',
            sdS5L1: 'We currently deliver across serviceable pin codes throughout India, subject to courier partner coverage and applicable local regulatory permissions.',
            sdS5L2: 'Certain remote, restricted, or logistically inaccessible areas may not be serviceable; the checkout system will indicate serviceability at the time of order placement.',
            sdS5L3: 'International shipping is not currently supported, unless expressly stated for a specific product listing.',
            sdH6: 'Order Tracking',
            sdS6L1: 'Upon dispatch, customers receive a tracking number and shipment status updates via SMS, email, and/or the AgriCart account dashboard.',
            sdS6L2: 'Real-time tracking status can be viewed at any time under "My Orders" in the customer account.',
            sdS6L3: 'Customers are encouraged to keep their registered contact number and email address active and up to date to ensure timely delivery notifications.',
            sdH7: 'Delayed Shipments',
            sdS7L1: 'While AgriCart endeavors to meet estimated delivery timelines, delays may occur due to adverse weather, transport strikes, regulatory checks, natural calamities, or other unforeseen operational disruptions.',
            sdS7L2: 'Customers will be notified of significant delays through the order tracking system or direct communication from our support team.',
            sdS7L3: 'AgriCart shall not be held liable for delays arising from circumstances beyond its reasonable control, including events of force majeure.',
            sdH8: 'Failed Delivery Attempts',
            sdS8L1: 'Our logistics partners typically make up to 2–3 delivery attempts before an order is marked as undeliverable.',
            sdS8L2: 'Where a delivery attempt fails due to an incorrect address, unavailability of the recipient, or refusal to accept the shipment, additional delivery attempts or re-shipping charges may apply.',
            sdS8L3: 'Orders that remain undelivered after repeated attempts may be returned to the originating warehouse, with applicable refunds — net of shipping and handling costs — processed in accordance with our Refund Policy.',
            sdH9: 'Damaged or Lost Packages',
            sdS9L1: 'Customers are requested to inspect packages at the time of delivery and report any visible damage to the delivery agent before accepting the shipment.',
            sdS9L2: 'Claims relating to damaged, tampered, or lost packages must be reported to AgriCart support within 48 hours of delivery (or the expected delivery date, in the case of a lost shipment), accompanied by photographic evidence where applicable.',
            sdS9L3: 'Verified claims will be resolved through replacement, repair, or refund, in accordance with our Return Policy and Refund Policy.',
            sdH10: 'Customer Responsibilities',
            sdS10L1: 'Customers must provide accurate, complete, and current delivery information, including address, pin code, and contact number, at the time of order placement.',
            sdS10L2: 'AgriCart shall not be responsible for delivery failures, misdeliveries, or delays resulting from incorrect, incomplete, or outdated address information supplied by the customer.',
            sdS10L3: 'Customers are responsible for being reasonably available to receive deliveries within the estimated delivery window communicated for their order.',
            sdH11: 'Contact Information',
            sdP11: 'For shipping-related queries, order tracking assistance, or delivery escalations, please reach us at support@agricart.in or call our helpline at 1800-419-8888.',
            sdH12: 'Last Updated',
            sdP12: 'This Shipping & Delivery Policy was last updated in July 2026. AgriCart may revise this policy from time to time to reflect changes in our logistics operations or applicable law; significant changes will be communicated on this page with a revised "Last updated" date.',
            sdCtaTitle: 'Have a question about your delivery?', sdCtaSub: 'Our support team typically replies within a few hours.',
            sdCtaEmail: 'Email Us', sdCtaCall: 'Call Helpline',
            sdNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.'
        },
        hi: {
            sdHome: 'मुखपृष्ठ', sdCrumb: 'शिपिंग और डिलीवरी नीति',
            sdTitle: 'शिपिंग और डिलीवरी नीति',
            sdSubtitle: 'एग्रीकार्ट आपके मार्केटप्लेस ऑर्डर को पूरे भारत में कैसे प्रोसेस, शिप और डिलीवर करता है — पुष्टि से लेकर आपके दरवाज़े तक।',
            sdUpdated: 'अंतिम अपडेट: जुलाई 2026', sdReadTime: '5 मिनट में पढ़ें', sdPrint: 'प्रिंट / पीडीएफ सेव करें',
            sdG1T: 'पूरे भारत में डिलीवरी', sdG1D: 'हम देशभर के सेवा योग्य पिन कोड पर शिपिंग करते हैं।',
            sdG2T: 'रीयल-टाइम ट्रैकिंग', sdG2D: 'डिस्पैच से लेकर डिलीवरी तक हर ऑर्डर ट्रैक करें।',
            sdG3T: 'सुरक्षित पैकेजिंग', sdG3D: 'ट्रांज़िट में नुकसान कम करने के लिए सामान सावधानी से पैक किया जाता है।',
            sdG4T: 'समर्पित सहायता', sdG4D: 'देरी, नुकसान या हानि में हमारी हेल्पलाइन मदद करती है।',
            sdIntro: 'एग्रीकार्ट ("हम", "हमें", "हमारा") भारतीय किसानों, खरीदारों और उपकरण रेंटल पार्टनर को जोड़ने वाला एक मार्केटप्लेस संचालित करता है। यह शिपिंग और डिलीवरी नीति बताती है कि हमारे प्लेटफ़ॉर्म पर दिए गए ऑर्डर कैसे प्रोसेस, शिप, ट्रैक और डिलीवर किए जाते हैं, और डिलीवरी के संबंध में एग्रीकार्ट तथा ग्राहकों की जिम्मेदारियाँ क्या हैं।',
            sdH1: 'ऑर्डर प्रोसेसिंग',
            sdS1L1: 'भुगतान की पुष्टि के 1–3 कार्य दिवसों के भीतर ऑर्डर प्रोसेस किए जाते हैं और हमारे लॉजिस्टिक्स पार्टनर को सौंपे जाते हैं, जो उत्पाद की उपलब्धता और विक्रेता/मंडी की पुष्टि पर निर्भर है।',
            sdS1L2: 'शाम 5:00 बजे IST के बाद, या रविवार और सार्वजनिक अवकाश पर दिए गए ऑर्डर अगले कार्य दिवस पर प्रोसेस किए जाते हैं।',
            sdS1L3: 'थोक ऑर्डर, कस्टम उपकरण रेंटल, और ऑर्डर पर बनने वाली कृषि उपज के लिए अतिरिक्त प्रोसेसिंग समय लग सकता है, जिसकी जानकारी बुकिंग के समय दी जाएगी।',
            sdH2: 'शिपिंग के तरीके',
            sdS2L1: 'एग्रीकार्ट अधिकृत कूरियर पार्टनर, क्षेत्रीय परिवहन एजेंसियों और कोल्ड-चेन लॉजिस्टिक्स प्रदाताओं के नेटवर्क के माध्यम से शिपिंग करता है, जिसका चयन सामान की प्रकृति के आधार पर किया जाता है।',
            sdS2L2: 'खराब होने वाली उपज, बीज और डेयरी उत्पादों को जहां भी संभव हो, तापमान-नियंत्रित या त्वरित लॉजिस्टिक्स माध्यमों से भेजा जाता है।',
            sdS2L3: 'उपकरण, मशीनरी और भारी कृषि इनपुट निर्दिष्ट फ्रेट कैरियर या लास्ट-माइल डिलीवरी पार्टनर के माध्यम से पहुंचाए जा सकते हैं।',
            sdH3: 'डिलीवरी का समय',
            sdS3L1: 'अनुमानित डिलीवरी समय 2 से 10 कार्य दिवसों तक होता है, जो डिलीवरी स्थान, उत्पाद श्रेणी और गंतव्य पिन कोड की सेवा-योग्यता पर निर्भर करता है।',
            sdS3L2: 'चेकआउट और ऑर्डर पुष्टि में दिखाई गई डिलीवरी समय-सीमाएँ केवल अनुमान हैं और गारंटीशुदा डिलीवरी तारीख नहीं मानी जाएंगी।',
            sdS3L3: 'समय-संवेदनशील खराब होने वाली वस्तुओं को जहां भी परिचालन रूप से संभव हो, त्वरित डिस्पैच के लिए प्राथमिकता दी जाती है।',
            sdH4: 'शिपिंग शुल्क',
            sdS4L1: 'शिपिंग शुल्क की गणना ऑर्डर के वज़न, आकार, उत्पाद श्रेणी, डिलीवरी दूरी और चेकआउट पर चुनी गई शिपिंग विधि के आधार पर की जाती है।',
            sdS4L2: 'एग्रीकार्ट योग्य ऑर्डर, प्रचार अभियानों या सब्सक्रिप्शन प्लान पर मुफ़्त या रियायती शिपिंग प्रदान कर सकता है, जैसा कि खरीद के समय दिखाया जाता है।',
            sdS4L3: 'सभी लागू शिपिंग शुल्क भुगतान से पहले प्रकट किए जाते हैं और चेकआउट पूरा होने से पहले अंतिम ऑर्डर सारांश में दर्शाए जाते हैं।',
            sdH5: 'डिलीवरी स्थान',
            sdS5L1: 'हम वर्तमान में पूरे भारत के सेवा-योग्य पिन कोड पर डिलीवरी करते हैं, जो कूरियर पार्टनर की कवरेज और लागू स्थानीय नियामक अनुमतियों पर निर्भर है।',
            sdS5L2: 'कुछ दूरदराज़, प्रतिबंधित या लॉजिस्टिक रूप से दुर्गम क्षेत्रों में सेवा उपलब्ध नहीं हो सकती; चेकआउट सिस्टम ऑर्डर के समय सेवा-योग्यता दर्शाएगा।',
            sdS5L3: 'अंतरराष्ट्रीय शिपिंग वर्तमान में समर्थित नहीं है, जब तक कि किसी विशेष उत्पाद लिस्टिंग के लिए स्पष्ट रूप से न बताया गया हो।',
            sdH6: 'ऑर्डर ट्रैकिंग',
            sdS6L1: 'डिस्पैच होने पर, ग्राहकों को SMS, ईमेल और/या एग्रीकार्ट खाता डैशबोर्ड के माध्यम से ट्रैकिंग नंबर और शिपमेंट स्टेटस अपडेट प्राप्त होते हैं।',
            sdS6L2: 'रीयल-टाइम ट्रैकिंग स्टेटस को ग्राहक खाते में "माय ऑर्डर्स" के अंतर्गत किसी भी समय देखा जा सकता है।',
            sdS6L3: 'समय पर डिलीवरी सूचनाएं सुनिश्चित करने के लिए ग्राहकों को अपने पंजीकृत संपर्क नंबर और ईमेल पते को सक्रिय और अद्यतन रखने की सलाह दी जाती है।',
            sdH7: 'विलंबित शिपमेंट',
            sdS7L1: 'हालांकि एग्रीकार्ट अनुमानित डिलीवरी समय-सीमाओं को पूरा करने का प्रयास करता है, प्रतिकूल मौसम, परिवहन हड़तालों, नियामक जांच, प्राकृतिक आपदाओं या अन्य अप्रत्याशित परिचालन व्यवधानों के कारण देरी हो सकती है।',
            sdS7L2: 'महत्वपूर्ण देरी की स्थिति में ग्राहकों को ऑर्डर ट्रैकिंग सिस्टम या हमारी सहायता टीम के सीधे संचार के माध्यम से सूचित किया जाएगा।',
            sdS7L3: 'एग्रीकार्ट अपने उचित नियंत्रण से बाहर की परिस्थितियों, जिसमें फोर्स मेज्योर की घटनाएँ शामिल हैं, से उत्पन्न देरी के लिए उत्तरदायी नहीं होगा।',
            sdH8: 'विफल डिलीवरी प्रयास',
            sdS8L1: 'हमारे लॉजिस्टिक्स पार्टनर आमतौर पर किसी ऑर्डर को अ-डिलीवरी योग्य घोषित करने से पहले 2–3 डिलीवरी प्रयास करते हैं।',
            sdS8L2: 'जहां गलत पता, प्राप्तकर्ता की अनुपलब्धता, या शिपमेंट स्वीकार करने से इनकार के कारण डिलीवरी प्रयास विफल होता है, वहां अतिरिक्त डिलीवरी प्रयास या पुनः-शिपिंग शुल्क लागू हो सकते हैं।',
            sdS8L3: 'बार-बार प्रयासों के बाद भी अ-वितरित रहने वाले ऑर्डर मूल गोदाम में लौटाए जा सकते हैं, और शिपिंग व हैंडलिंग लागत घटाकर लागू रिफंड हमारी रिफंड नीति के अनुसार प्रोसेस किए जाएंगे।',
            sdH9: 'क्षतिग्रस्त या खोए हुए पैकेज',
            sdS9L1: 'ग्राहकों से अनुरोध है कि डिलीवरी के समय पैकेज का निरीक्षण करें और शिपमेंट स्वीकार करने से पहले किसी भी दिखाई देने वाले नुकसान की सूचना डिलीवरी एजेंट को दें।',
            sdS9L2: 'क्षतिग्रस्त, छेड़छाड़ किए गए या खोए हुए पैकेज से संबंधित दावे डिलीवरी के 48 घंटों के भीतर (या खोए हुए शिपमेंट की स्थिति में अपेक्षित डिलीवरी तारीख के भीतर) एग्रीकार्ट सहायता को, यथासंभव फोटो प्रमाण के साथ, सूचित किए जाने चाहिए।',
            sdS9L3: 'सत्यापित दावों का समाधान हमारी रिटर्न नीति और रिफंड नीति के अनुसार प्रतिस्थापन, मरम्मत या रिफंड के माध्यम से किया जाएगा।',
            sdH10: 'ग्राहक की जिम्मेदारियाँ',
            sdS10L1: 'ग्राहकों को ऑर्डर देने के समय सटीक, पूर्ण और वर्तमान डिलीवरी जानकारी, जिसमें पता, पिन कोड और संपर्क नंबर शामिल है, प्रदान करनी होगी।',
            sdS10L2: 'ग्राहक द्वारा दी गई गलत, अधूरी या पुरानी पते की जानकारी के कारण होने वाली डिलीवरी विफलताओं, गलत डिलीवरी या देरी के लिए एग्रीकार्ट उत्तरदायी नहीं होगा।',
            sdS10L3: 'ग्राहक अपने ऑर्डर के लिए सूचित अनुमानित डिलीवरी विंडो के भीतर डिलीवरी प्राप्त करने हेतु उचित रूप से उपलब्ध रहने के लिए जिम्मेदार हैं।',
            sdH11: 'संपर्क जानकारी',
            sdP11: 'शिपिंग से संबंधित प्रश्नों, ऑर्डर ट्रैकिंग सहायता, या डिलीवरी एस्कलेशन के लिए, कृपया हमसे support@agricart.in पर संपर्क करें या हमारी हेल्पलाइन 1800-419-8888 पर कॉल करें।',
            sdH12: 'अंतिम अपडेट',
            sdP12: 'यह शिपिंग और डिलीवरी नीति अंतिम बार जुलाई 2026 में अपडेट की गई थी। एग्रीकार्ट अपने लॉजिस्टिक्स संचालन या लागू कानून में बदलाव को दर्शाने के लिए समय-समय पर इस नीति में संशोधन कर सकता है; महत्वपूर्ण बदलाव इस पेज पर संशोधित "अंतिम अपडेट" तारीख के साथ बताए जाएंगे।',
            sdCtaTitle: 'अपनी डिलीवरी को लेकर कोई सवाल है?', sdCtaSub: 'हमारी सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            sdCtaEmail: 'ईमेल करें', sdCtaCall: 'हेल्पलाइन कॉल करें',
            sdNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक नीति के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            sdHome: 'मुखपृष्ठ', sdCrumb: 'शिपिंग आणि डिलिव्हरी धोरण',
            sdTitle: 'शिपिंग आणि डिलिव्हरी धोरण',
            sdSubtitle: 'अ‍ॅग्रीकार्ट तुमचे मार्केटप्लेस ऑर्डर संपूर्ण भारतात कसे प्रक्रिया, शिप आणि डिलिव्हर करते — पुष्टीपासून तुमच्या दारापर्यंत.',
            sdUpdated: 'शेवटचे अद्ययावत: जुलै 2026', sdReadTime: '5 मिनिटांत वाचा', sdPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            sdG1T: 'संपूर्ण भारतात डिलिव्हरी', sdG1D: 'आम्ही देशभरातील सेवा-योग्य पिन कोडवर शिपिंग करतो.',
            sdG2T: 'रिअल-टाइम ट्रॅकिंग', sdG2D: 'डिस्पॅचपासून डिलिव्हरीपर्यंत प्रत्येक ऑर्डर ट्रॅक करा.',
            sdG3T: 'काळजीपूर्वक पॅकेजिंग', sdG3D: 'वाहतुकीदरम्यान नुकसान कमी करण्यासाठी माल काळजीपूर्वक पॅक केला जातो.',
            sdG4T: 'समर्पित सहाय्य', sdG4D: 'विलंब, नुकसान किंवा तोटा यामध्ये आमची हेल्पलाइन मदत करते.',
            sdIntro: 'अ‍ॅग्रीकार्ट ("आम्ही", "आम्हाला", "आमचे") भारतीय शेतकरी, खरेदीदार आणि उपकरण भाडे भागीदारांना जोडणारे मार्केटप्लेस चालवते. हे शिपिंग आणि डिलिव्हरी धोरण स्पष्ट करते की आमच्या प्लॅटफॉर्मवर दिलेले ऑर्डर कसे प्रक्रिया, शिप, ट्रॅक आणि डिलिव्हर केले जातात, आणि डिलिव्हरीच्या संदर्भात अ‍ॅग्रीकार्ट व ग्राहकांच्या जबाबदाऱ्या काय आहेत.',
            sdH1: 'ऑर्डर प्रक्रिया',
            sdS1L1: 'पेमेंट पुष्टीच्या 1–3 कामकाजाच्या दिवसांत ऑर्डर प्रक्रिया करून आमच्या लॉजिस्टिक्स भागीदारांकडे सोपवले जातात, जे उत्पादनाच्या उपलब्धतेवर आणि विक्रेता/मंडई पुष्टीवर अवलंबून असते.',
            sdS1L2: 'संध्याकाळी 5:00 वाजता IST नंतर, किंवा रविवारी व सार्वजनिक सुट्टीच्या दिवशी दिलेले ऑर्डर पुढील कामकाजाच्या दिवशी प्रक्रिया केले जातात.',
            sdS1L3: 'मोठ्या प्रमाणातील ऑर्डर, कस्टम उपकरण भाडे, आणि ऑर्डरनुसार तयार होणाऱ्या कृषी उत्पादनांसाठी अतिरिक्त प्रक्रिया वेळ लागू शकतो, याची माहिती बुकिंगच्या वेळी दिली जाईल.',
            sdH2: 'शिपिंग पद्धती',
            sdS2L1: 'अ‍ॅग्रीकार्ट अधिकृत कुरिअर भागीदार, प्रादेशिक वाहतूक एजन्सी आणि कोल्ड-चेन लॉजिस्टिक्स प्रदात्यांच्या नेटवर्कद्वारे शिपिंग करते, ज्याची निवड मालाच्या स्वरूपानुसार केली जाते.',
            sdS2L2: 'नाशवंत उत्पादन, बियाणे आणि दुग्धजन्य पदार्थ शक्य असेल तिथे तापमान-नियंत्रित किंवा जलद लॉजिस्टिक्स माध्यमातून पाठवले जातात.',
            sdS2L3: 'उपकरणे, यंत्रसामग्री आणि जड कृषी इनपुट निर्दिष्ट फ्रेट वाहक किंवा लास्ट-माइल डिलिव्हरी भागीदारांमार्फत पोहोचवले जाऊ शकतात.',
            sdH3: 'डिलिव्हरीचा कालावधी',
            sdS3L1: 'अंदाजे डिलिव्हरी कालावधी 2 ते 10 कामकाजाच्या दिवसांपर्यंत असतो, जो डिलिव्हरी स्थान, उत्पादन श्रेणी आणि गंतव्य पिन कोडच्या सेवा-योग्यतेवर अवलंबून असतो.',
            sdS3L2: 'चेकआउट आणि ऑर्डर पुष्टीमध्ये दर्शवलेले डिलिव्हरी कालावधी केवळ अंदाज आहेत आणि हमी दिलेली डिलिव्हरी तारीख मानली जाणार नाही.',
            sdS3L3: 'वेळ-संवेदनशील नाशवंत वस्तूंना शक्य असेल तिथे जलद डिस्पॅचसाठी प्राधान्य दिले जाते.',
            sdH4: 'शिपिंग शुल्क',
            sdS4L1: 'शिपिंग शुल्काची गणना ऑर्डरचे वजन, आकार, उत्पादन श्रेणी, डिलिव्हरी अंतर आणि चेकआउटच्या वेळी निवडलेल्या शिपिंग पद्धतीच्या आधारे केली जाते.',
            sdS4L2: 'अ‍ॅग्रीकार्ट पात्र ऑर्डर, प्रचारात्मक मोहिमा किंवा सबस्क्रिप्शन योजनांवर मोफत किंवा सवलतीची शिपिंग देऊ शकते, जसे खरेदीच्या वेळी दर्शवले जाते.',
            sdS4L3: 'सर्व लागू शिपिंग शुल्क पेमेंटपूर्वी उघड केले जातात आणि चेकआउट पूर्ण होण्यापूर्वी अंतिम ऑर्डर सारांशात दर्शवले जातात.',
            sdH5: 'डिलिव्हरी स्थाने',
            sdS5L1: 'आम्ही सध्या संपूर्ण भारतातील सेवा-योग्य पिन कोडवर डिलिव्हरी करतो, जे कुरिअर भागीदाराच्या कव्हरेजवर आणि लागू स्थानिक नियामक परवानग्यांवर अवलंबून आहे.',
            sdS5L2: 'काही दुर्गम, प्रतिबंधित किंवा लॉजिस्टिकदृष्ट्या पोहोचण्यास कठीण भागांत सेवा उपलब्ध नसू शकते; चेकआउट प्रणाली ऑर्डरच्या वेळी सेवा-योग्यता दर्शवेल.',
            sdS5L3: 'आंतरराष्ट्रीय शिपिंग सध्या समर्थित नाही, जोपर्यंत एखाद्या विशिष्ट उत्पादन लिस्टिंगसाठी स्पष्टपणे नमूद केलेले नसेल.',
            sdH6: 'ऑर्डर ट्रॅकिंग',
            sdS6L1: 'डिस्पॅच झाल्यावर, ग्राहकांना SMS, ईमेल आणि/किंवा अ‍ॅग्रीकार्ट खाते डॅशबोर्डद्वारे ट्रॅकिंग नंबर आणि शिपमेंट स्टेटस अपडेट्स मिळतात.',
            sdS6L2: 'रिअल-टाइम ट्रॅकिंग स्टेटस ग्राहक खात्यातील "माय ऑर्डर्स" अंतर्गत कधीही पाहता येते.',
            sdS6L3: 'वेळेवर डिलिव्हरी सूचना मिळण्यासाठी ग्राहकांना त्यांचा नोंदणीकृत संपर्क क्रमांक आणि ईमेल पत्ता सक्रिय व अद्ययावत ठेवण्याचा सल्ला दिला जातो.',
            sdH7: 'विलंबित शिपमेंट्स',
            sdS7L1: 'अ‍ॅग्रीकार्ट अंदाजे डिलिव्हरी कालावधी पूर्ण करण्याचा प्रयत्न करत असले तरी, प्रतिकूल हवामान, वाहतूक संप, नियामक तपासणी, नैसर्गिक आपत्ती किंवा इतर अनपेक्षित परिचालन अडथळ्यांमुळे विलंब होऊ शकतो.',
            sdS7L2: 'महत्त्वपूर्ण विलंबाच्या बाबतीत ग्राहकांना ऑर्डर ट्रॅकिंग प्रणाली किंवा आमच्या सहाय्य टीमकडून थेट संपर्काद्वारे कळवले जाईल.',
            sdS7L3: 'अ‍ॅग्रीकार्ट आपल्या वाजवी नियंत्रणाबाहेरील परिस्थितीमुळे, ज्यात फोर्स मेज्यॉरच्या घटनांचा समावेश आहे, होणाऱ्या विलंबासाठी जबाबदार राहणार नाही.',
            sdH8: 'अयशस्वी डिलिव्हरी प्रयत्न',
            sdS8L1: 'आमचे लॉजिस्टिक्स भागीदार सामान्यतः एखादा ऑर्डर अ-वितरणयोग्य घोषित करण्यापूर्वी 2–3 डिलिव्हरी प्रयत्न करतात.',
            sdS8L2: 'चुकीचा पत्ता, प्राप्तकर्त्याची अनुपलब्धता, किंवा शिपमेंट स्वीकारण्यास नकार यामुळे डिलिव्हरी प्रयत्न अयशस्वी झाल्यास, अतिरिक्त डिलिव्हरी प्रयत्न किंवा पुनः-शिपिंग शुल्क लागू होऊ शकते.',
            sdS8L3: 'वारंवार प्रयत्नांनंतरही अ-वितरित राहिलेले ऑर्डर मूळ गोदामात परत केले जाऊ शकतात, आणि शिपिंग व हाताळणी खर्च वजा करून लागू परतावा आमच्या परतावा धोरणानुसार प्रक्रिया केला जाईल.',
            sdH9: 'खराब झालेले किंवा हरवलेले पॅकेजेस',
            sdS9L1: 'ग्राहकांना विनंती आहे की डिलिव्हरीच्या वेळी पॅकेजची तपासणी करावी आणि शिपमेंट स्वीकारण्यापूर्वी कोणतेही दृश्यमान नुकसान डिलिव्हरी एजंटला कळवावे.',
            sdS9L2: 'खराब झालेले, छेडछाड झालेले किंवा हरवलेले पॅकेज संबंधित दावे डिलिव्हरीच्या 48 तासांच्या आत (किंवा हरवलेल्या शिपमेंटच्या बाबतीत अपेक्षित डिलिव्हरी तारखेच्या आत) अ‍ॅग्रीकार्ट सहाय्याला, शक्य असल्यास छायाचित्र पुराव्यासह, कळवले जाणे आवश्यक आहे.',
            sdS9L3: 'सत्यापित दाव्यांचे निराकरण आमच्या परतावा धोरण आणि रिफंड धोरणानुसार बदली, दुरुस्ती किंवा परताव्याद्वारे केले जाईल.',
            sdH10: 'ग्राहकांच्या जबाबदाऱ्या',
            sdS10L1: 'ग्राहकांनी ऑर्डर देताना अचूक, संपूर्ण आणि सद्यस्थितीतील डिलिव्हरी माहिती, ज्यामध्ये पत्ता, पिन कोड आणि संपर्क क्रमांक समाविष्ट आहे, प्रदान करणे आवश्यक आहे.',
            sdS10L2: 'ग्राहकाने दिलेल्या चुकीच्या, अपूर्ण किंवा जुन्या पत्ता माहितीमुळे होणाऱ्या डिलिव्हरी अपयश, चुकीच्या डिलिव्हरी किंवा विलंबासाठी अ‍ॅग्रीकार्ट जबाबदार राहणार नाही.',
            sdS10L3: 'ग्राहक त्यांच्या ऑर्डरसाठी कळवलेल्या अंदाजे डिलिव्हरी कालावधीत डिलिव्हरी स्वीकारण्यासाठी वाजवीपणे उपलब्ध राहण्यास जबाबदार आहेत.',
            sdH11: 'संपर्क माहिती',
            sdP11: 'शिपिंगशी संबंधित प्रश्न, ऑर्डर ट्रॅकिंग सहाय्य, किंवा डिलिव्हरी एस्कलेशनसाठी, कृपया आमच्याशी support@agricart.in वर संपर्क साधा किंवा आमच्या हेल्पलाइन 1800-419-8888 वर कॉल करा.',
            sdH12: 'शेवटचे अद्ययावत',
            sdP12: 'हे शिपिंग आणि डिलिव्हरी धोरण शेवटचे जुलै 2026 मध्ये अद्ययावत केले गेले. अ‍ॅग्रीकार्ट आपल्या लॉजिस्टिक्स कार्यपद्धती किंवा लागू कायद्यातील बदल प्रतिबिंबित करण्यासाठी वेळोवेळी या धोरणात सुधारणा करू शकते; महत्त्वाचे बदल या पानावर सुधारित "शेवटचे अद्ययावत" तारखेसह कळवले जातील.',
            sdCtaTitle: 'तुमच्या डिलिव्हरीबद्दल काही प्रश्न आहे का?', sdCtaSub: 'आमची सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            sdCtaEmail: 'ईमेल करा', sdCtaCall: 'हेल्पलाइनला कॉल करा',
            sdNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत धोरण म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applySDLang(lang) {
        var dict = SD_I18N[lang] || SD_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applySDLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applySDLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applySDLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
