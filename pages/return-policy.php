<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Return Policy Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\return-policy.php
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

/* ── Mini info cards inside sections ──────────────────── */
.lg-mini-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 0.6rem; }
.lg-mini-card { background: #f7faf6; border: 1px solid #eef2ee; border-radius: 12px; padding: 12px 14px; }
.lg-mini-card i { color: #2ecc71; font-size: 13px; margin-right: 6px; }
.lg-mini-card strong { display: block; font-family:'Poppins',sans-serif; font-size: 12.5px; color: #0f2a16; margin-bottom: 2px; }
.lg-mini-card span { font-size: 12.5px; color: #547a54; line-height: 1.5; }
@media (max-width: 560px) { .lg-mini-grid { grid-template-columns: 1fr; } }

.lg-step-list { list-style: none; padding: 0; margin: 0.6rem 0 0; counter-reset: lg-step; }
.lg-step-list li { position: relative; padding-left: 34px; margin-bottom: 12px; counter-increment: lg-step; }
.lg-step-list li::before {
    content: counter(lg-step); position: absolute; left: 0; top: 0; width: 24px; height: 24px; border-radius: 50%;
    background: #2ecc71; color: #0b1a14; font-family:'Poppins',sans-serif; font-weight: 700; font-size: 11.5px;
    display: flex; align-items: center; justify-content: center;
}

.lg-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 12px; font-weight: 700; color: #2E7D32; background: #eef7ee; border-radius: 30px; padding: 5px 12px; margin-bottom: 10px; }
.lg-badge i { font-size: 11px; }

.lg-warn { background: #fff8ec; border: 1px solid #f5e3b8; border-radius: 12px; padding: 12px 14px; margin-top: 0.6rem; display: flex; gap: 10px; align-items: flex-start; }
.lg-warn i { color: #c8860d; font-size: 14px; margin-top: 2px; }
.lg-warn span { font-size: 12.8px; color: #7a5c1e; line-height: 1.6; }

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
        <div class="lg-hero-icon"><i class="fa-solid fa-rotate-left"></i></div>
        <h1 id="rtpTitle">Return Policy</h1>
        <p id="rtpSubtitle">Simple, transparent rules for returning products purchased on the AgriCart marketplace and rental hub.</p>
        <div class="lg-hero-meta">
            <span id="rtpUpdated">Last updated: July 2026</span><span class="lg-dot">•</span><span id="rtpReadTime">5 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="rtpPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-calendar-days"></i>
                <h4 id="rtpG1T">7-day return window</h4>
                <p id="rtpG1D">Most items can be returned within 7 days of delivery.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-box-open"></i>
                <h4 id="rtpG2T">Original condition</h4>
                <p id="rtpG2D">Items must be unused, with packaging and tags intact.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-user-shield"></i>
                <h4 id="rtpG3T">Verified sellers</h4>
                <p id="rtpG3D">Every return is checked and approved by our team.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-truck-fast"></i>
                <h4 id="rtpG4T">Doorstep pickup</h4>
                <p id="rtpG4D">Free pickup is arranged for eligible returns.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="rtpIntro">AgriCart wants every purchase — from farm inputs to rented equipment — to work for you. This Return Policy explains when an item is eligible for return, how long you have, and the steps to follow for a smooth resolution.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-circle-check"></i> <span id="rtpH1">Eligibility for Returns</span></h2></div>
                <ul>
                    <li id="rtpS1L1">The product was purchased through the AgriCart marketplace and delivered by a verified seller or logistics partner.</li>
                    <li id="rtpS1L2">The item is unused, uninstalled, and in the same condition as when it was delivered.</li>
                    <li id="rtpS1L3">The return request is raised within the return window shown on the order's product page.</li>
                    <li id="rtpS1L4">The original invoice, order ID, or delivery confirmation is available for verification.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-calendar-days"></i> <span id="rtpH2">Return Time Period</span></h2></div>
                <p id="rtpP2">Most products can be returned within <strong>7 days</strong> of delivery. Perishable agri-inputs (seeds, saplings, fertilizers) must be reported within <strong>48 hours</strong> of delivery due to their shelf life. The exact window for a specific product is always shown on its listing page.</p>
                <div class="lg-mini-grid">
                    <div class="lg-mini-card"><i class="fa-solid fa-box"></i><strong>General Products</strong><span>7 days from delivery date</span></div>
                    <div class="lg-mini-card"><i class="fa-solid fa-seedling"></i><strong>Perishables & Inputs</strong><span>48 hours from delivery date</span></div>
                </div>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-ban"></i> <span id="rtpH3">Non-Returnable Items</span></h2></div>
                <ul>
                    <li id="rtpS3L1">Opened or used bags of seeds, fertilizers, pesticides, and other consumable agri-inputs.</li>
                    <li id="rtpS3L2">Perishable produce, plants, and saplings once accepted at delivery.</li>
                    <li id="rtpS3L3">Custom-made, personalized, or made-to-order items.</li>
                    <li id="rtpS3L4">Products marked "Non-Returnable" on the listing page, and items purchased during clearance sales.</li>
                    <li id="rtpS3L5">Equipment rentals once the rental period has commenced, except as covered under Section 08.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-clipboard-check"></i> <span id="rtpH4">Product Condition Requirements</span></h2></div>
                <p id="rtpP4">To qualify for return, items must meet the following condition standards:</p>
                <ul>
                    <li id="rtpS4L1">Unused, unwashed, and undamaged, with all original packaging, boxes, and accessories.</li>
                    <li id="rtpS4L2">Original tags, seals, and labels must remain attached and intact.</li>
                    <li id="rtpS4L3">Free from stains, odours, scratches, or wear that did not exist at the time of delivery.</li>
                    <li id="rtpS4L4">Any free gifts, manuals, or bundled accessories included with the order must be returned together with the product.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-list-check"></i> <span id="rtpH5">Return Process</span></h2></div>
                <ol class="lg-step-list">
                    <li id="rtpS5L1">Go to <strong>My Orders</strong> and select "Return Item" against the relevant order.</li>
                    <li id="rtpS5L2">Choose a reason for the return and upload photos of the product if requested.</li>
                    <li id="rtpS5L3">Our team reviews the request, typically within 24–48 hours, and confirms eligibility.</li>
                    <li id="rtpS5L4">Once approved, a pickup is scheduled, or you may be asked to self-ship to the seller's return address.</li>
                    <li id="rtpS5L5">After the item passes quality inspection, your refund or replacement is initiated as per the Refund Policy.</li>
                </ol>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-user-shield"></i> <span id="rtpH6">Seller Verification</span></h2></div>
                <p id="rtpP6">Every return is reviewed against the seller's dispatch records and delivery proof before approval. AgriCart may request additional evidence — such as an unboxing video or photos — to verify the claim. This step protects both farmers selling produce and buyers from fraudulent or mistaken return claims, and helps us hold sellers accountable to quality standards.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-triangle-exclamation"></i> <span id="rtpH7">Damaged or Wrong Product Procedure</span></h2></div>
                <p id="rtpP7">If you receive a damaged, defective, or incorrect item, please report it within <strong>48 hours</strong> of delivery through My Orders, along with clear photos of the product, packaging, and shipping label.</p>
                <div class="lg-warn">
                    <i class="fa-solid fa-circle-info"></i>
                    <span id="rtpWarn7">Damaged or wrong-item claims raised after 48 hours may not be eligible for a free pickup or replacement, so please inspect your order as soon as it arrives.</span>
                </div>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-tractor"></i> <span id="rtpH8">Equipment Rental Return Rules</span></h2></div>
                <ul>
                    <li id="rtpS8L1">Rented equipment must be returned to the designated Rental Hub point on or before the agreed end date and time.</li>
                    <li id="rtpS8L2">Equipment should be returned clean and in the same working condition as at pickup, barring normal wear and tear.</li>
                    <li id="rtpS8L3">Late returns are charged a pro-rated daily fee as shown at the time of booking.</li>
                    <li id="rtpS8L4">Security deposits are refunded after inspection confirms the equipment has no damage beyond normal use.</li>
                    <li id="rtpS8L5">If equipment is found faulty within the first hour of use, report it immediately for a free swap or full rental refund.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-headset"></i> <span id="rtpH9">Contact Information</span></h2></div>
                <p id="rtpP9">For any return-related assistance, reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our helpline at 1800-419-8888. Our team is available Monday–Saturday, 9:00 AM–6:00 PM.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="rtpCtaTitle">Need help with a return?</h3>
                    <p id="rtpCtaSub">Our support team typically replies within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="rtpCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="rtpCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="rtpNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.</p>
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

    var RTP_I18N = {
        en: {
            rtpHome: 'Home', rtpCrumb: 'Return Policy',
            rtpTitle: 'Return Policy',
            rtpSubtitle: 'Simple, transparent rules for returning products purchased on the AgriCart marketplace and rental hub.',
            rtpUpdated: 'Last updated: July 2026', rtpReadTime: '5 min read', rtpPrint: 'Print / Save PDF',
            rtpG1T: '7-day return window', rtpG1D: 'Most items can be returned within 7 days of delivery.',
            rtpG2T: 'Original condition', rtpG2D: 'Items must be unused, with packaging and tags intact.',
            rtpG3T: 'Verified sellers', rtpG3D: 'Every return is checked and approved by our team.',
            rtpG4T: 'Doorstep pickup', rtpG4D: 'Free pickup is arranged for eligible returns.',
            rtpIntro: "AgriCart wants every purchase — from farm inputs to rented equipment — to work for you. This Return Policy explains when an item is eligible for return, how long you have, and the steps to follow for a smooth resolution.",
            rtpH1: 'Eligibility for Returns',
            rtpS1L1: 'The product was purchased through the AgriCart marketplace and delivered by a verified seller or logistics partner.',
            rtpS1L2: 'The item is unused, uninstalled, and in the same condition as when it was delivered.',
            rtpS1L3: "The return request is raised within the return window shown on the order's product page.",
            rtpS1L4: 'The original invoice, order ID, or delivery confirmation is available for verification.',
            rtpH2: 'Return Time Period',
            rtpP2: 'Most products can be returned within 7 days of delivery. Perishable agri-inputs (seeds, saplings, fertilizers) must be reported within 48 hours of delivery due to their shelf life. The exact window for a specific product is always shown on its listing page.',
            rtpH3: 'Non-Returnable Items',
            rtpS3L1: 'Opened or used bags of seeds, fertilizers, pesticides, and other consumable agri-inputs.',
            rtpS3L2: 'Perishable produce, plants, and saplings once accepted at delivery.',
            rtpS3L3: 'Custom-made, personalized, or made-to-order items.',
            rtpS3L4: 'Products marked "Non-Returnable" on the listing page, and items purchased during clearance sales.',
            rtpS3L5: 'Equipment rentals once the rental period has commenced, except as covered under Section 08.',
            rtpH4: 'Product Condition Requirements',
            rtpP4: 'To qualify for return, items must meet the following condition standards:',
            rtpS4L1: 'Unused, unwashed, and undamaged, with all original packaging, boxes, and accessories.',
            rtpS4L2: 'Original tags, seals, and labels must remain attached and intact.',
            rtpS4L3: 'Free from stains, odours, scratches, or wear that did not exist at the time of delivery.',
            rtpS4L4: 'Any free gifts, manuals, or bundled accessories included with the order must be returned together with the product.',
            rtpH5: 'Return Process',
            rtpS5L1: 'Go to My Orders and select "Return Item" against the relevant order.',
            rtpS5L2: 'Choose a reason for the return and upload photos of the product if requested.',
            rtpS5L3: 'Our team reviews the request, typically within 24–48 hours, and confirms eligibility.',
            rtpS5L4: "Once approved, a pickup is scheduled, or you may be asked to self-ship to the seller's return address.",
            rtpS5L5: 'After the item passes quality inspection, your refund or replacement is initiated as per the Refund Policy.',
            rtpH6: 'Seller Verification',
            rtpP6: "Every return is reviewed against the seller's dispatch records and delivery proof before approval. AgriCart may request additional evidence — such as an unboxing video or photos — to verify the claim. This step protects both farmers selling produce and buyers from fraudulent or mistaken return claims, and helps us hold sellers accountable to quality standards.",
            rtpH7: 'Damaged or Wrong Product Procedure',
            rtpP7: 'If you receive a damaged, defective, or incorrect item, please report it within 48 hours of delivery through My Orders, along with clear photos of the product, packaging, and shipping label.',
            rtpWarn7: 'Damaged or wrong-item claims raised after 48 hours may not be eligible for a free pickup or replacement, so please inspect your order as soon as it arrives.',
            rtpH8: 'Equipment Rental Return Rules',
            rtpS8L1: 'Rented equipment must be returned to the designated Rental Hub point on or before the agreed end date and time.',
            rtpS8L2: 'Equipment should be returned clean and in the same working condition as at pickup, barring normal wear and tear.',
            rtpS8L3: 'Late returns are charged a pro-rated daily fee as shown at the time of booking.',
            rtpS8L4: 'Security deposits are refunded after inspection confirms the equipment has no damage beyond normal use.',
            rtpS8L5: 'If equipment is found faulty within the first hour of use, report it immediately for a free swap or full rental refund.',
            rtpH9: 'Contact Information',
            rtpP9: 'For any return-related assistance, reach us at support@agricart.in or call our helpline at 1800-419-8888. Our team is available Monday–Saturday, 9:00 AM–6:00 PM.',
            rtpCtaTitle: 'Need help with a return?', rtpCtaSub: 'Our support team typically replies within a few hours.',
            rtpCtaEmail: 'Email Us', rtpCtaCall: 'Call Helpline',
            rtpNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.'
        },
        hi: {
            rtpHome: 'मुखपृष्ठ', rtpCrumb: 'वापसी नीति',
            rtpTitle: 'वापसी नीति',
            rtpSubtitle: 'एग्रीकार्ट मार्केटप्लेस और रेंटल हब से खरीदे गए उत्पादों को वापस करने के लिए सरल, पारदर्शी नियम।',
            rtpUpdated: 'अंतिम अपडेट: जुलाई 2026', rtpReadTime: '5 मिनट में पढ़ें', rtpPrint: 'प्रिंट / पीडीएफ सेव करें',
            rtpG1T: '7-दिन की वापसी अवधि', rtpG1D: 'अधिकांश वस्तुएं डिलीवरी के 7 दिनों के भीतर वापस की जा सकती हैं।',
            rtpG2T: 'मूल स्थिति में', rtpG2D: 'वस्तु अनुपयोगित होनी चाहिए, पैकेजिंग और टैग सहित।',
            rtpG3T: 'सत्यापित विक्रेता', rtpG3D: 'हर वापसी की हमारी टीम द्वारा जांच और स्वीकृति की जाती है।',
            rtpG4T: 'घर से पिकअप', rtpG4D: 'योग्य वापसी के लिए मुफ्त पिकअप की व्यवस्था की जाती है।',
            rtpIntro: 'एग्रीकार्ट चाहता है कि हर खरीद — खेती के सामान से लेकर किराए के उपकरण तक — आपके लिए सही साबित हो। यह वापसी नीति बताती है कि कोई उत्पाद कब वापसी के योग्य है, आपके पास कितना समय है, और सुचारू समाधान के लिए किन चरणों का पालन करना है।',
            rtpH1: 'वापसी की पात्रता',
            rtpS1L1: 'उत्पाद एग्रीकार्ट मार्केटप्लेस के माध्यम से खरीदा गया हो और किसी सत्यापित विक्रेता या लॉजिस्टिक्स पार्टनर द्वारा डिलीवर किया गया हो।',
            rtpS1L2: 'वस्तु अनुपयोगित, अनइंस्टॉल की हुई और डिलीवरी के समय जैसी ही स्थिति में हो।',
            rtpS1L3: 'ऑर्डर के उत्पाद पेज पर दिखाई गई वापसी अवधि के भीतर वापसी अनुरोध किया गया हो।',
            rtpS1L4: 'सत्यापन के लिए मूल इनवॉइस, ऑर्डर आईडी या डिलीवरी पुष्टि उपलब्ध हो।',
            rtpH2: 'वापसी की समय-सीमा',
            rtpP2: 'अधिकांश उत्पाद डिलीवरी के 7 दिनों के भीतर वापस किए जा सकते हैं। नाशवान कृषि-सामग्री (बीज, पौध, उर्वरक) की शेल्फ लाइफ के कारण इनकी शिकायत डिलीवरी के 48 घंटों के भीतर करनी होगी। किसी विशेष उत्पाद की सटीक समय-सीमा हमेशा उसके लिस्टिंग पेज पर दिखाई जाती है।',
            rtpH3: 'गैर-वापसी योग्य वस्तुएं',
            rtpS3L1: 'बीज, उर्वरक, कीटनाशक और अन्य उपभोग्य कृषि-सामग्री के खुले या उपयोग किए गए पैकेट।',
            rtpS3L2: 'डिलीवरी पर स्वीकार किए जाने के बाद नाशवान उपज, पौधे और पौध।',
            rtpS3L3: 'कस्टम-निर्मित, व्यक्तिगत या ऑर्डर पर बनाई गई वस्तुएं।',
            rtpS3L4: 'लिस्टिंग पेज पर "गैर-वापसी योग्य" चिह्नित उत्पाद, और क्लीयरेंस सेल के दौरान खरीदी गई वस्तुएं।',
            rtpS3L5: 'रेंटल अवधि शुरू होने के बाद उपकरण रेंटल, सेक्शन 08 में बताए गए मामलों को छोड़कर।',
            rtpH4: 'उत्पाद की स्थिति संबंधी आवश्यकताएं',
            rtpP4: 'वापसी के योग्य होने के लिए, वस्तुओं को निम्न मानकों को पूरा करना होगा:',
            rtpS4L1: 'अनुपयोगित, बिना धुली और बिना क्षतिग्रस्त, सभी मूल पैकेजिंग, बॉक्स और सामान सहित।',
            rtpS4L2: 'मूल टैग, सील और लेबल जुड़े और अक्षुण्ण होने चाहिए।',
            rtpS4L3: 'दाग, गंध, खरोंच या ऐसी टूट-फूट से मुक्त जो डिलीवरी के समय मौजूद नहीं थी।',
            rtpS4L4: 'ऑर्डर के साथ शामिल कोई भी मुफ्त उपहार, मैनुअल या बंडल किया गया सामान उत्पाद के साथ वापस किया जाना चाहिए।',
            rtpH5: 'वापसी प्रक्रिया',
            rtpS5L1: 'माय ऑर्डर्स में जाएं और संबंधित ऑर्डर पर "आइटम वापस करें" चुनें।',
            rtpS5L2: 'वापसी का कारण चुनें और अनुरोध किए जाने पर उत्पाद की फोटो अपलोड करें।',
            rtpS5L3: 'हमारी टीम आमतौर पर 24–48 घंटों के भीतर अनुरोध की समीक्षा करती है और पात्रता की पुष्टि करती है।',
            rtpS5L4: 'स्वीकृति के बाद, पिकअप शेड्यूल किया जाता है, या आपको विक्रेता के वापसी पते पर स्वयं भेजने के लिए कहा जा सकता है।',
            rtpS5L5: 'वस्तु के गुणवत्ता निरीक्षण में पास होने के बाद, धनवापसी नीति के अनुसार आपकी रिफंड या रिप्लेसमेंट शुरू की जाती है।',
            rtpH6: 'विक्रेता सत्यापन',
            rtpP6: 'हर वापसी को स्वीकृति से पहले विक्रेता के डिस्पैच रिकॉर्ड और डिलीवरी प्रमाण के विरुद्ध जांचा जाता है। एग्रीकार्ट दावे की पुष्टि के लिए अतिरिक्त प्रमाण — जैसे अनबॉक्सिंग वीडियो या फोटो — मांग सकता है। यह कदम किसानों और खरीदारों दोनों को धोखाधड़ी या गलत वापसी दावों से बचाता है, और विक्रेताओं को गुणवत्ता मानकों के प्रति जवाबदेह बनाए रखने में मदद करता है।',
            rtpH7: 'क्षतिग्रस्त या गलत उत्पाद की प्रक्रिया',
            rtpP7: 'यदि आपको क्षतिग्रस्त, दोषपूर्ण या गलत वस्तु मिलती है, तो कृपया डिलीवरी के 48 घंटों के भीतर माय ऑर्डर्स के माध्यम से इसकी शिकायत करें, साथ ही उत्पाद, पैकेजिंग और शिपिंग लेबल की स्पष्ट फोटो भी दें।',
            rtpWarn7: '48 घंटों के बाद उठाए गए क्षतिग्रस्त या गलत-वस्तु के दावे मुफ्त पिकअप या रिप्लेसमेंट के लिए योग्य नहीं हो सकते, इसलिए कृपया ऑर्डर आने पर तुरंत जांच लें।',
            rtpH8: 'उपकरण रेंटल वापसी नियम',
            rtpS8L1: 'किराए के उपकरण को सहमत अंतिम तारीख और समय पर या उससे पहले निर्दिष्ट रेंटल हब पॉइंट पर वापस करना होगा।',
            rtpS8L2: 'सामान्य टूट-फूट को छोड़कर, उपकरण को साफ और पिकअप के समय जैसी ही कार्यशील स्थिति में वापस किया जाना चाहिए।',
            rtpS8L3: 'देर से वापसी पर बुकिंग के समय दिखाए गए अनुसार प्रो-रेटेड दैनिक शुल्क लिया जाता है।',
            rtpS8L4: 'सुरक्षा जमा राशि निरीक्षण के बाद वापस की जाती है, जब यह पुष्टि हो जाती है कि उपकरण को सामान्य उपयोग से अधिक नुकसान नहीं हुआ है।',
            rtpS8L5: 'यदि उपयोग के पहले घंटे के भीतर उपकरण खराब पाया जाता है, तो मुफ्त बदलाव या पूर्ण रेंटल रिफंड के लिए तुरंत सूचित करें।',
            rtpH9: 'संपर्क जानकारी',
            rtpP9: 'वापसी संबंधी किसी भी सहायता के लिए, हमसे support@agricart.in पर संपर्क करें या हमारी हेल्पलाइन 1800-419-8888 पर कॉल करें। हमारी टीम सोमवार–शनिवार, सुबह 9:00–शाम 6:00 बजे तक उपलब्ध है।',
            rtpCtaTitle: 'वापसी में मदद चाहिए?', rtpCtaSub: 'हमारी सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            rtpCtaEmail: 'ईमेल करें', rtpCtaCall: 'हेल्पलाइन कॉल करें',
            rtpNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक नीति के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            rtpHome: 'मुखपृष्ठ', rtpCrumb: 'परतावा धोरण',
            rtpTitle: 'परतावा धोरण',
            rtpSubtitle: 'अ‍ॅग्रीकार्ट मार्केटप्लेस आणि रेंटल हबवरून खरेदी केलेली उत्पादने परत करण्यासाठी सोपे, पारदर्शक नियम.',
            rtpUpdated: 'शेवटचे अद्ययावत: जुलै 2026', rtpReadTime: '5 मिनिटांत वाचा', rtpPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            rtpG1T: '7-दिवसांची परतावा मुदत', rtpG1D: 'बहुतांश वस्तू डिलिव्हरीच्या 7 दिवसांत परत करता येतात.',
            rtpG2T: 'मूळ स्थितीत', rtpG2D: 'वस्तू न वापरलेली असावी, पॅकेजिंग व टॅगसह.',
            rtpG3T: 'सत्यापित विक्रेते', rtpG3D: 'प्रत्येक परतावा आमच्या टीमकडून तपासला व मंजूर केला जातो.',
            rtpG4T: 'घरपोच पिकअप', rtpG4D: 'पात्र परताव्यासाठी मोफत पिकअपची व्यवस्था केली जाते.',
            rtpIntro: 'अ‍ॅग्रीकार्टला वाटते की प्रत्येक खरेदी — शेती सामग्रीपासून भाड्याच्या उपकरणांपर्यंत — तुमच्यासाठी योग्य ठरावी. हे परतावा धोरण उत्पादन कधी परत करण्यायोग्य आहे, तुमच्याकडे किती वेळ आहे आणि सुरळीत निराकरणासाठी कोणत्या पायऱ्या पाळाव्यात हे स्पष्ट करते.',
            rtpH1: 'परताव्याची पात्रता',
            rtpS1L1: 'उत्पादन अ‍ॅग्रीकार्ट मार्केटप्लेसद्वारे खरेदी केलेले असावे आणि सत्यापित विक्रेता किंवा लॉजिस्टिक्स पार्टनरद्वारे डिलिव्हर केलेले असावे.',
            rtpS1L2: 'वस्तू न वापरलेली, इन्स्टॉल न केलेली आणि डिलिव्हरीच्या वेळी होती तशाच स्थितीत असावी.',
            rtpS1L3: 'ऑर्डरच्या उत्पादन पानावर दाखवलेल्या परतावा कालावधीत परतावा विनंती केली गेली असावी.',
            rtpS1L4: 'पडताळणीसाठी मूळ इनव्हॉइस, ऑर्डर आयडी किंवा डिलिव्हरी पुष्टीकरण उपलब्ध असावे.',
            rtpH2: 'परताव्याची मुदत',
            rtpP2: 'बहुतांश उत्पादने डिलिव्हरीच्या 7 दिवसांत परत करता येतात. नाशवंत कृषी-सामग्री (बियाणे, रोपे, खते) यांच्या शेल्फ लाइफमुळे डिलिव्हरीच्या 48 तासांत तक्रार नोंदवावी लागते. एखाद्या विशिष्ट उत्पादनाची नेमकी मुदत नेहमी त्याच्या लिस्टिंग पानावर दाखवलेली असते.',
            rtpH3: 'परत न करता येणाऱ्या वस्तू',
            rtpS3L1: 'बियाणे, खते, कीटकनाशके आणि इतर उपभोग्य कृषी-सामग्रीच्या उघडलेल्या किंवा वापरलेल्या पिशव्या.',
            rtpS3L2: 'डिलिव्हरीच्या वेळी स्वीकारल्यानंतर नाशवंत उत्पादन, झाडे आणि रोपे.',
            rtpS3L3: 'सानुकूल-निर्मित, वैयक्तिकृत किंवा ऑर्डरनुसार बनवलेल्या वस्तू.',
            rtpS3L4: 'लिस्टिंग पानावर "परत न करता येणारे" असे चिन्हांकित उत्पादने, आणि क्लिअरन्स सेल दरम्यान खरेदी केलेल्या वस्तू.',
            rtpS3L5: 'भाडे कालावधी सुरू झाल्यानंतर उपकरण भाडे, विभाग 08 मध्ये नमूद केलेले प्रकरण वगळता.',
            rtpH4: 'उत्पादनाच्या स्थितीबाबत आवश्यकता',
            rtpP4: 'परताव्यासाठी पात्र होण्यासाठी, वस्तूंनी खालील स्थिती मानके पूर्ण करणे आवश्यक आहे:',
            rtpS4L1: 'न वापरलेली, न धुतलेली आणि खराब न झालेली, सर्व मूळ पॅकेजिंग, बॉक्स आणि सामानासह.',
            rtpS4L2: 'मूळ टॅग, सील आणि लेबल जोडलेले आणि अखंड असावेत.',
            rtpS4L3: 'डाग, वास, ओरखडे किंवा डिलिव्हरीच्या वेळी नसलेली झीज यापासून मुक्त.',
            rtpS4L4: 'ऑर्डरसोबत समाविष्ट कोणतीही मोफत भेट, मॅन्युअल किंवा बंडल केलेले सामान उत्पादनासोबत परत करणे आवश्यक आहे.',
            rtpH5: 'परतावा प्रक्रिया',
            rtpS5L1: 'माय ऑर्डर्समध्ये जा आणि संबंधित ऑर्डरवर "आयटम परत करा" निवडा.',
            rtpS5L2: 'परताव्याचे कारण निवडा आणि विनंती केल्यास उत्पादनाचे फोटो अपलोड करा.',
            rtpS5L3: 'आमची टीम साधारणपणे 24–48 तासांत विनंतीचे पुनरावलोकन करते आणि पात्रता निश्चित करते.',
            rtpS5L4: 'मंजुरीनंतर, पिकअप शेड्यूल केला जातो, किंवा तुम्हाला विक्रेत्याच्या परतावा पत्त्यावर स्वतः पाठवण्यास सांगितले जाऊ शकते.',
            rtpS5L5: 'वस्तू गुणवत्ता तपासणीत उत्तीर्ण झाल्यानंतर, परतावा धोरणानुसार तुमचा परतावा किंवा बदली सुरू केली जाते.',
            rtpH6: 'विक्रेता पडताळणी',
            rtpP6: 'मंजुरीपूर्वी प्रत्येक परतावा विक्रेत्याच्या डिस्पॅच रेकॉर्ड आणि डिलिव्हरी पुराव्याविरुद्ध तपासला जातो. दाव्याची पडताळणी करण्यासाठी अ‍ॅग्रीकार्ट अतिरिक्त पुरावा — जसे की अनबॉक्सिंग व्हिडिओ किंवा फोटो — मागू शकते. हे पाऊल शेतकरी आणि खरेदीदार दोघांनाही फसव्या किंवा चुकीच्या परतावा दाव्यांपासून संरक्षण देते, आणि विक्रेत्यांना गुणवत्ता मानकांसाठी जबाबदार धरण्यास मदत करते.',
            rtpH7: 'खराब किंवा चुकीच्या उत्पादनाची प्रक्रिया',
            rtpP7: 'तुम्हाला खराब, दोषपूर्ण किंवा चुकीची वस्तू मिळाल्यास, कृपया डिलिव्हरीच्या 48 तासांत माय ऑर्डर्सद्वारे तक्रार नोंदवा, उत्पादन, पॅकेजिंग आणि शिपिंग लेबलचे स्पष्ट फोटो सोबत द्या.',
            rtpWarn7: '48 तासांनंतर नोंदवलेले खराब किंवा चुकीच्या-वस्तूचे दावे मोफत पिकअप किंवा बदलीसाठी पात्र नसू शकतात, त्यामुळे कृपया ऑर्डर आल्यावर लगेच तपासा.',
            rtpH8: 'उपकरण भाडे परतावा नियम',
            rtpS8L1: 'भाड्याचे उपकरण मान्य केलेल्या अंतिम तारखेला किंवा त्यापूर्वी नियुक्त रेंटल हब पॉइंटवर परत करणे आवश्यक आहे.',
            rtpS8L2: 'सामान्य झीज-तोड वगळता, उपकरण स्वच्छ आणि पिकअपच्या वेळी होती तशाच कार्यक्षम स्थितीत परत करावे.',
            rtpS8L3: 'उशिरा परताव्यासाठी बुकिंगच्या वेळी दाखवलेले प्रो-रेटेड दैनिक शुल्क आकारले जाते.',
            rtpS8L4: 'तपासणीनंतर सुरक्षा ठेव परत केली जाते, जेव्हा उपकरणाचे सामान्य वापरापेक्षा जास्त नुकसान झालेले नाही याची खात्री होते.',
            rtpS8L5: 'वापराच्या पहिल्या तासात उपकरण सदोष आढळल्यास, मोफत बदली किंवा पूर्ण भाडे परताव्यासाठी लगेच कळवा.',
            rtpH9: 'संपर्क माहिती',
            rtpP9: 'परताव्याशी संबंधित कोणत्याही मदतीसाठी, आमच्याशी support@agricart.in वर संपर्क साधा किंवा आमच्या हेल्पलाइन 1800-419-8888 वर कॉल करा. आमची टीम सोमवार–शनिवार, सकाळी 9:00–संध्याकाळी 6:00 पर्यंत उपलब्ध आहे.',
            rtpCtaTitle: 'परताव्यासाठी मदत हवी?', rtpCtaSub: 'आमची सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            rtpCtaEmail: 'ईमेल करा', rtpCtaCall: 'हेल्पलाइनला कॉल करा',
            rtpNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत धोरण म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applyRTPLang(lang) {
        var dict = RTP_I18N[lang] || RTP_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applyRTPLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applyRTPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applyRTPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
