<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Seller Terms & Conditions Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\seller-terms.php
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
        <div class="lg-hero-icon"><i class="fa-solid fa-store"></i></div>
        <h1 id="stTitle">Seller Terms &amp; Conditions</h1>
        <p id="stSubtitle">The terms and conditions applicable to sellers listing and selling products on the AgriCart marketplace.</p>
        <div class="lg-hero-meta">
            <span id="stUpdated">Last updated: August 2026</span><span class="lg-dot">•</span><span id="stReadTime">6 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="stPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-id-card"></i>
                <h4 id="stG1T">Verified Sellers Only</h4>
                <p id="stG1D">KYC and business verification required to list products.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-leaf"></i>
                <h4 id="stG2T">Quality Assured</h4>
                <p id="stG2D">Products must meet AgriCart's quality and labelling standards.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-indian-rupee-sign"></i>
                <h4 id="stG3T">Transparent Payouts</h4>
                <p id="stG3D">Commission and settlement details are disclosed upfront.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-headset"></i>
                <h4 id="stG4T">Dedicated Seller Support</h4>
                <p id="stG4D">Our helpline assists with listings, orders, and disputes.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="stIntro">These Seller Terms &amp; Conditions ("Seller Terms") apply to all sellers offering agricultural produce, inputs, or equipment for sale on the AgriCart platform ("Seller", "you", "your"). By registering as a Seller, you agree to be bound by these Seller Terms in addition to AgriCart's general Terms &amp; Conditions.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-user-check"></i> <span id="stH1">Seller Eligibility</span></h2></div>
                <p id="stP1">Seller accounts on AgriCart are available to individuals, farmer producer organisations (FPOs), proprietorships, partnerships, and registered companies engaged in the sale of agricultural produce, inputs, or equipment. Sellers must be at least 18 years of age, complete AgriCart's KYC and business verification process, and provide valid documentation including PAN, GSTIN (where applicable), and bank account details. AgriCart reserves the right to reject or revoke seller access where verification cannot be completed or documentation is found to be false or misleading.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-list-check"></i> <span id="stH2">Product Listing Rules</span></h2></div>
                <ul>
                    <li id="stS2L1">Product listings must accurately describe the item, including quantity, unit of measure, variety, grade, and condition, with no misleading claims.</li>
                    <li id="stS2L2">Images uploaded must be genuine representations of the actual product offered and must not infringe any third-party copyright or trademark.</li>
                    <li id="stS2L3">Sellers are solely responsible for ensuring that listed products comply with applicable packaging, labelling, and disclosure requirements under Indian law.</li>
                    <li id="stS2L4">AgriCart reserves the right to edit, suspend, or remove any listing that is inaccurate, non-compliant, or violates these Seller Terms.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-award"></i> <span id="stH3">Product Quality Standards</span></h2></div>
                <p id="stP3">Sellers shall ensure that all products offered for sale meet the quality, freshness, and safety standards applicable to the relevant product category, including compliance with the Food Safety and Standards Act, 2006 (where applicable) and any grading or certification standards specified on the platform. Products found to be substandard, adulterated, expired, or materially different from their listing shall be subject to removal, and repeated quality violations may result in account suspension.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-tag"></i> <span id="stH4">Pricing Responsibilities</span></h2></div>
                <p id="stP4">Sellers are responsible for setting fair, accurate, and non-deceptive prices for their products, inclusive of all applicable taxes unless stated otherwise. Prices displayed at the time of order confirmation shall be binding upon the Seller. AgriCart reserves the right to remove listings found to involve price manipulation, artificial inflation, or predatory undercutting practices that violate applicable competition law.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-boxes-stacked"></i> <span id="stH5">Inventory Management</span></h2></div>
                <p id="stP5">Sellers must maintain accurate and updated inventory counts on the platform to prevent overselling or acceptance of orders for unavailable stock. Where a Seller is unable to fulfil a confirmed order due to inventory discrepancies, the Seller must promptly notify AgriCart and the affected buyer, and shall bear any resulting cancellation charges as determined by AgriCart's policies.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-truck-fast"></i> <span id="stH6">Order Fulfilment</span></h2></div>
                <ul>
                    <li id="stS6L1">Confirmed orders must be packed and dispatched within the timeline specified on the platform for the relevant product category.</li>
                    <li id="stS6L2">Sellers must use packaging appropriate to the nature of the product to prevent spoilage, contamination, or damage during transit.</li>
                    <li id="stS6L3">Sellers must provide accurate dispatch and tracking information where applicable and cooperate with AgriCart's logistics partners for timely delivery.</li>
                    <li id="stS6L4">Repeated delays or failures in order fulfilment may result in performance penalties, reduced listing visibility, or account review.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-rotate-left"></i> <span id="stH7">Returns &amp; Refund Responsibilities</span></h2></div>
                <p id="stP7">Sellers shall honour AgriCart's Return and Refund Policy applicable to buyers, including accepting returns for products that are damaged, defective, expired, or materially different from their listing. Refunds and applicable deductions shall be processed in accordance with AgriCart's settlement cycle. Sellers found to be non-compliant with return obligations may have the disputed amount recovered from future payouts.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-indian-rupee-sign"></i> <span id="stH8">Commission &amp; Payments</span></h2></div>
                <p id="stP8">AgriCart shall charge a commission on each successful sale, at the rate applicable to the relevant product category as communicated to the Seller at the time of onboarding or listing. Payouts, net of commission, applicable taxes, and any deductions, shall be settled to the Seller's registered bank account as per AgriCart's standard settlement cycle. AgriCart reserves the right to withhold payouts pending resolution of disputes, quality complaints, or suspected fraudulent activity.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-ban"></i> <span id="stH9">Prohibited Products</span></h2></div>
                <ul>
                    <li id="stS9L1">Counterfeit, expired, banned, or unregistered agrochemicals, pesticides, and fertilisers.</li>
                    <li id="stS9L2">Products that violate any applicable law, including those requiring licences the Seller does not hold.</li>
                    <li id="stS9L3">Adulterated food produce or items misrepresented as organic, certified, or graded without valid supporting certification.</li>
                    <li id="stS9L4">Any item identified in AgriCart's prohibited and restricted items list, as updated from time to time.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">10</span><h2><i class="fa-solid fa-user-slash"></i> <span id="stH10">Account Suspension &amp; Termination</span></h2></div>
                <p id="stP10">AgriCart reserves the right to suspend or terminate a Seller's account, with or without prior notice, in the event of a breach of these Seller Terms, repeated buyer complaints, failure to maintain quality or fulfilment standards, fraudulent activity, or non-compliance with applicable law. Upon termination, pending orders shall be settled in accordance with AgriCart's policies, and outstanding dues owed to AgriCart shall remain payable by the Seller.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">11</span><h2><i class="fa-solid fa-scale-balanced"></i> <span id="stH11">Legal Compliance</span></h2></div>
                <p id="stP11">Sellers are solely responsible for obtaining and maintaining all licences, registrations, and approvals required to sell their products, including GST registration, FSSAI licence (where applicable), and any agricultural produce marketing regulations applicable in their state. Sellers shall indemnify AgriCart against any claims, penalties, or liabilities arising from their non-compliance with applicable law.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">12</span><h2><i class="fa-solid fa-headset"></i> <span id="stH12">Contact Information</span></h2></div>
                <p id="stP12">For seller onboarding, listing support, payout queries, or any questions relating to these Seller Terms, please reach us at <a href="mailto:seller-support@agricart.in">seller-support@agricart.in</a> or call our seller helpline at <a href="tel:18004197777">1800-419-7777</a>.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">13</span><h2><i class="fa-solid fa-clock-rotate-left"></i> <span id="stH13">Last Updated &amp; Changes to These Terms</span></h2></div>
                <p id="stP13">AgriCart may revise these Seller Terms &amp; Conditions from time to time to reflect changes in operations, commission structures, or applicable law. Material changes will be communicated on this page along with a revised "Last updated" date, and continued use of seller services after such changes constitutes acceptance of the revised terms.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="stCtaTitle">Need help with your seller account?</h3>
                    <p id="stCtaSub">Our seller support team typically responds within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:seller-support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="stCtaEmail">Email Us</span></a>
                    <a href="tel:18004197777" class="ghost"><i class="fa-solid fa-phone"></i> <span id="stCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="stNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official seller agreement.</p>
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

    var ST_I18N = {
        en: {
            stHome: 'Home', stCrumb: 'Seller Terms & Conditions',
            stTitle: 'Seller Terms & Conditions',
            stSubtitle: 'The terms and conditions applicable to sellers listing and selling products on the AgriCart marketplace.',
            stUpdated: 'Last updated: August 2026', stReadTime: '6 min read', stPrint: 'Print / Save PDF',
            stG1T: 'Verified Sellers Only', stG1D: 'KYC and business verification required to list products.',
            stG2T: 'Quality Assured', stG2D: "Products must meet AgriCart's quality and labelling standards.",
            stG3T: 'Transparent Payouts', stG3D: 'Commission and settlement details are disclosed upfront.',
            stG4T: 'Dedicated Seller Support', stG4D: 'Our helpline assists with listings, orders, and disputes.',
            stIntro: 'These Seller Terms & Conditions ("Seller Terms") apply to all sellers offering agricultural produce, inputs, or equipment for sale on the AgriCart platform ("Seller", "you", "your"). By registering as a Seller, you agree to be bound by these Seller Terms in addition to AgriCart\'s general Terms & Conditions.',
            stH1: 'Seller Eligibility',
            stP1: 'Seller accounts on AgriCart are available to individuals, farmer producer organisations (FPOs), proprietorships, partnerships, and registered companies engaged in the sale of agricultural produce, inputs, or equipment. Sellers must be at least 18 years of age, complete AgriCart\'s KYC and business verification process, and provide valid documentation including PAN, GSTIN (where applicable), and bank account details. AgriCart reserves the right to reject or revoke seller access where verification cannot be completed or documentation is found to be false or misleading.',
            stH2: 'Product Listing Rules',
            stS2L1: 'Product listings must accurately describe the item, including quantity, unit of measure, variety, grade, and condition, with no misleading claims.',
            stS2L2: 'Images uploaded must be genuine representations of the actual product offered and must not infringe any third-party copyright or trademark.',
            stS2L3: 'Sellers are solely responsible for ensuring that listed products comply with applicable packaging, labelling, and disclosure requirements under Indian law.',
            stS2L4: 'AgriCart reserves the right to edit, suspend, or remove any listing that is inaccurate, non-compliant, or violates these Seller Terms.',
            stH3: 'Product Quality Standards',
            stP3: 'Sellers shall ensure that all products offered for sale meet the quality, freshness, and safety standards applicable to the relevant product category, including compliance with the Food Safety and Standards Act, 2006 (where applicable) and any grading or certification standards specified on the platform. Products found to be substandard, adulterated, expired, or materially different from their listing shall be subject to removal, and repeated quality violations may result in account suspension.',
            stH4: 'Pricing Responsibilities',
            stP4: 'Sellers are responsible for setting fair, accurate, and non-deceptive prices for their products, inclusive of all applicable taxes unless stated otherwise. Prices displayed at the time of order confirmation shall be binding upon the Seller. AgriCart reserves the right to remove listings found to involve price manipulation, artificial inflation, or predatory undercutting practices that violate applicable competition law.',
            stH5: 'Inventory Management',
            stP5: "Sellers must maintain accurate and updated inventory counts on the platform to prevent overselling or acceptance of orders for unavailable stock. Where a Seller is unable to fulfil a confirmed order due to inventory discrepancies, the Seller must promptly notify AgriCart and the affected buyer, and shall bear any resulting cancellation charges as determined by AgriCart's policies.",
            stH6: 'Order Fulfilment',
            stS6L1: 'Confirmed orders must be packed and dispatched within the timeline specified on the platform for the relevant product category.',
            stS6L2: 'Sellers must use packaging appropriate to the nature of the product to prevent spoilage, contamination, or damage during transit.',
            stS6L3: "Sellers must provide accurate dispatch and tracking information where applicable and cooperate with AgriCart's logistics partners for timely delivery.",
            stS6L4: 'Repeated delays or failures in order fulfilment may result in performance penalties, reduced listing visibility, or account review.',
            stH7: 'Returns & Refund Responsibilities',
            stP7: "Sellers shall honour AgriCart's Return and Refund Policy applicable to buyers, including accepting returns for products that are damaged, defective, expired, or materially different from their listing. Refunds and applicable deductions shall be processed in accordance with AgriCart's settlement cycle. Sellers found to be non-compliant with return obligations may have the disputed amount recovered from future payouts.",
            stH8: 'Commission & Payments',
            stP8: "AgriCart shall charge a commission on each successful sale, at the rate applicable to the relevant product category as communicated to the Seller at the time of onboarding or listing. Payouts, net of commission, applicable taxes, and any deductions, shall be settled to the Seller's registered bank account as per AgriCart's standard settlement cycle. AgriCart reserves the right to withhold payouts pending resolution of disputes, quality complaints, or suspected fraudulent activity.",
            stH9: 'Prohibited Products',
            stS9L1: 'Counterfeit, expired, banned, or unregistered agrochemicals, pesticides, and fertilisers.',
            stS9L2: 'Products that violate any applicable law, including those requiring licences the Seller does not hold.',
            stS9L3: 'Adulterated food produce or items misrepresented as organic, certified, or graded without valid supporting certification.',
            stS9L4: "Any item identified in AgriCart's prohibited and restricted items list, as updated from time to time.",
            stH10: 'Account Suspension & Termination',
            stP10: "AgriCart reserves the right to suspend or terminate a Seller's account, with or without prior notice, in the event of a breach of these Seller Terms, repeated buyer complaints, failure to maintain quality or fulfilment standards, fraudulent activity, or non-compliance with applicable law. Upon termination, pending orders shall be settled in accordance with AgriCart's policies, and outstanding dues owed to AgriCart shall remain payable by the Seller.",
            stH11: 'Legal Compliance',
            stP11: 'Sellers are solely responsible for obtaining and maintaining all licences, registrations, and approvals required to sell their products, including GST registration, FSSAI licence (where applicable), and any agricultural produce marketing regulations applicable in their state. Sellers shall indemnify AgriCart against any claims, penalties, or liabilities arising from their non-compliance with applicable law.',
            stH12: 'Contact Information',
            stH13: 'Last Updated & Changes to These Terms',
            stP13: 'AgriCart may revise these Seller Terms & Conditions from time to time to reflect changes in operations, commission structures, or applicable law. Material changes will be communicated on this page along with a revised "Last updated" date, and continued use of seller services after such changes constitutes acceptance of the revised terms.',
            stCtaTitle: 'Need help with your seller account?', stCtaSub: 'Our seller support team typically responds within a few hours.',
            stCtaEmail: 'Email Us', stCtaCall: 'Call Helpline',
            stNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official seller agreement.'
        },
        hi: {
            stHome: 'मुखपृष्ठ', stCrumb: 'विक्रेता नियम व शर्तें',
            stTitle: 'विक्रेता नियम व शर्तें',
            stSubtitle: 'एग्रीकार्ट मार्केटप्लेस पर उत्पाद बेचने वाले विक्रेताओं पर लागू होने वाले नियम व शर्तें।',
            stUpdated: 'अंतिम अपडेट: अगस्त 2026', stReadTime: '6 मिनट में पढ़ें', stPrint: 'प्रिंट / पीडीएफ सेव करें',
            stG1T: 'केवल सत्यापित विक्रेता', stG1D: 'उत्पाद सूचीबद्ध करने के लिए केवाईसी और व्यवसाय सत्यापन आवश्यक है।',
            stG2T: 'गुणवत्ता सुनिश्चित', stG2D: 'उत्पादों को एग्रीकार्ट के गुणवत्ता और लेबलिंग मानकों को पूरा करना होगा।',
            stG3T: 'पारदर्शी भुगतान', stG3D: 'कमीशन और सेटलमेंट विवरण पहले से स्पष्ट किए जाते हैं।',
            stG4T: 'समर्पित विक्रेता सहायता', stG4D: 'हमारी हेल्पलाइन लिस्टिंग, ऑर्डर और विवादों में सहायता करती है।',
            stIntro: 'ये विक्रेता नियम व शर्तें ("विक्रेता शर्तें") एग्रीकार्ट प्लेटफ़ॉर्म पर कृषि उत्पाद, इनपुट या उपकरण बेचने वाले विक्रेताओं ("विक्रेता", "आप", "आपका") पर लागू होती हैं। विक्रेता के रूप में पंजीकरण करके, आप एग्रीकार्ट की सामान्य नियम व शर्तों के अतिरिक्त इन विक्रेता शर्तों से बाध्य होने के लिए सहमत होते हैं।',
            stH1: 'विक्रेता पात्रता',
            stP1: 'एग्रीकार्ट पर विक्रेता खाते व्यक्तियों, किसान उत्पादक संगठनों (एफपीओ), एकल स्वामित्व, साझेदारी और कृषि उपज, इनपुट या उपकरण की बिक्री में संलग्न पंजीकृत कंपनियों के लिए उपलब्ध हैं। विक्रेता की आयु कम से कम 18 वर्ष होनी चाहिए, उन्हें एग्रीकार्ट की केवाईसी और व्यवसाय सत्यापन प्रक्रिया पूरी करनी होगी, तथा पैन, जीएसटीआईएन (जहाँ लागू हो) और बैंक खाता विवरण सहित वैध दस्तावेज़ प्रस्तुत करने होंगे। जहाँ सत्यापन पूरा नहीं किया जा सकता या दस्तावेज़ झूठे या भ्रामक पाए जाते हैं, वहाँ एग्रीकार्ट को विक्रेता पहुँच अस्वीकार या रद्द करने का अधिकार सुरक्षित है।',
            stH2: 'उत्पाद लिस्टिंग नियम',
            stS2L1: 'उत्पाद लिस्टिंग में मात्रा, माप की इकाई, किस्म, ग्रेड और स्थिति सहित वस्तु का सटीक विवरण होना चाहिए, बिना किसी भ्रामक दावे के।',
            stS2L2: 'अपलोड की गई छवियाँ वास्तविक उत्पाद का सच्चा प्रतिनिधित्व होनी चाहिए और किसी भी तीसरे पक्ष के कॉपीराइट या ट्रेडमार्क का उल्लंघन नहीं करनी चाहिए।',
            stS2L3: 'विक्रेता यह सुनिश्चित करने के लिए पूर्णतः उत्तरदायी है कि सूचीबद्ध उत्पाद भारतीय कानून के तहत लागू पैकेजिंग, लेबलिंग और प्रकटीकरण आवश्यकताओं का पालन करते हैं।',
            stS2L4: 'एग्रीकार्ट को किसी भी अशुद्ध, गैर-अनुपालक या इन विक्रेता शर्तों का उल्लंघन करने वाली लिस्टिंग को संपादित, निलंबित या हटाने का अधिकार सुरक्षित है।',
            stH3: 'उत्पाद गुणवत्ता मानक',
            stP3: 'विक्रेता यह सुनिश्चित करेगा कि बिक्री हेतु प्रस्तुत सभी उत्पाद संबंधित उत्पाद श्रेणी पर लागू गुणवत्ता, ताजगी और सुरक्षा मानकों को पूरा करते हैं, जिसमें खाद्य सुरक्षा एवं मानक अधिनियम, 2006 (जहाँ लागू हो) तथा प्लेटफ़ॉर्म पर निर्दिष्ट किसी भी ग्रेडिंग या प्रमाणन मानक का अनुपालन शामिल है। घटिया, मिलावटी, समयसीमा समाप्त, या अपनी लिस्टिंग से भौतिक रूप से भिन्न पाए जाने वाले उत्पाद हटाए जाने के अधीन होंगे, और बार-बार गुणवत्ता उल्लंघन खाता निलंबन का कारण बन सकता है।',
            stH4: 'मूल्य निर्धारण जिम्मेदारियाँ',
            stP4: 'विक्रेता अपने उत्पादों के लिए उचित, सटीक और भ्रामक-रहित मूल्य निर्धारित करने के लिए उत्तरदायी है, जब तक अन्यथा न बताया जाए, इसमें सभी लागू कर शामिल होंगे। ऑर्डर पुष्टि के समय प्रदर्शित मूल्य विक्रेता के लिए बाध्यकारी होंगे। एग्रीकार्ट को मूल्य में हेरफेर, कृत्रिम वृद्धि, या लागू प्रतिस्पर्धा कानून का उल्लंघन करने वाली शिकारी अंडरकटिंग प्रथाओं से जुड़ी पाई जाने वाली लिस्टिंग को हटाने का अधिकार सुरक्षित है।',
            stH5: 'इन्वेंट्री प्रबंधन',
            stP5: 'ओवरसेलिंग या अनुपलब्ध स्टॉक के लिए ऑर्डर स्वीकार करने से बचने हेतु विक्रेता को प्लेटफ़ॉर्म पर सटीक और अद्यतन इन्वेंट्री गणना बनाए रखनी चाहिए। जहाँ विक्रेता इन्वेंट्री विसंगतियों के कारण पुष्टि किए गए ऑर्डर को पूरा करने में असमर्थ है, वहाँ विक्रेता को तुरंत एग्रीकार्ट और प्रभावित खरीदार को सूचित करना चाहिए, और एग्रीकार्ट की नीतियों के अनुसार निर्धारित किसी भी परिणामी रद्दीकरण शुल्क को वहन करना होगा।',
            stH6: 'ऑर्डर पूर्ति',
            stS6L1: 'पुष्टि किए गए ऑर्डर को संबंधित उत्पाद श्रेणी के लिए प्लेटफ़ॉर्म पर निर्दिष्ट समयसीमा के भीतर पैक और डिस्पैच किया जाना चाहिए।',
            stS6L2: 'विक्रेता को परिवहन के दौरान खराब होने, संदूषण या क्षति को रोकने के लिए उत्पाद की प्रकृति के अनुरूप पैकेजिंग का उपयोग करना चाहिए।',
            stS6L3: 'विक्रेता को जहाँ लागू हो, सटीक डिस्पैच और ट्रैकिंग जानकारी प्रदान करनी चाहिए और समय पर डिलीवरी के लिए एग्रीकार्ट के लॉजिस्टिक्स पार्टनर के साथ सहयोग करना चाहिए।',
            stS6L4: 'ऑर्डर पूर्ति में बार-बार देरी या विफलता के परिणामस्वरूप प्रदर्शन दंड, कम लिस्टिंग दृश्यता, या खाता समीक्षा हो सकती है।',
            stH7: 'रिटर्न और रिफंड जिम्मेदारियाँ',
            stP7: 'विक्रेता खरीदारों पर लागू एग्रीकार्ट की रिटर्न और रिफंड नीति का पालन करेगा, जिसमें क्षतिग्रस्त, दोषपूर्ण, समयसीमा समाप्त, या अपनी लिस्टिंग से भौतिक रूप से भिन्न उत्पादों के लिए रिटर्न स्वीकार करना शामिल है। रिफंड और लागू कटौतियों को एग्रीकार्ट के सेटलमेंट चक्र के अनुसार संसाधित किया जाएगा। रिटर्न दायित्वों का पालन न करने वाले विक्रेताओं की विवादित राशि भविष्य के भुगतान से वसूल की जा सकती है।',
            stH8: 'कमीशन और भुगतान',
            stP8: 'एग्रीकार्ट प्रत्येक सफल बिक्री पर संबंधित उत्पाद श्रेणी पर लागू दर पर कमीशन लेगा, जो ऑनबोर्डिंग या लिस्टिंग के समय विक्रेता को सूचित किया जाएगा। कमीशन, लागू कर और किसी भी कटौती के बाद शुद्ध भुगतान, एग्रीकार्ट के मानक सेटलमेंट चक्र के अनुसार विक्रेता के पंजीकृत बैंक खाते में जमा किया जाएगा। विवादों, गुणवत्ता शिकायतों, या संदिग्ध धोखाधड़ी गतिविधि के समाधान लंबित रहने तक एग्रीकार्ट को भुगतान रोकने का अधिकार सुरक्षित है।',
            stH9: 'प्रतिबंधित उत्पाद',
            stS9L1: 'नकली, समयसीमा समाप्त, प्रतिबंधित, या अपंजीकृत कृषि रसायन, कीटनाशक और उर्वरक।',
            stS9L2: 'किसी भी लागू कानून का उल्लंघन करने वाले उत्पाद, जिसमें ऐसे लाइसेंस की आवश्यकता वाले उत्पाद शामिल हैं जो विक्रेता के पास नहीं हैं।',
            stS9L3: 'मिलावटी खाद्य उपज या मान्य सहायक प्रमाणन के बिना जैविक, प्रमाणित, या ग्रेडेड के रूप में गलत प्रस्तुत की गई वस्तुएं।',
            stS9L4: 'एग्रीकार्ट की प्रतिबंधित और वर्जित वस्तुओं की सूची में पहचानी गई कोई भी वस्तु, जिसे समय-समय पर अद्यतन किया जाता है।',
            stH10: 'खाता निलंबन और समाप्ति',
            stP10: 'इन विक्रेता शर्तों के उल्लंघन, बार-बार खरीदार शिकायतों, गुणवत्ता या पूर्ति मानकों को बनाए रखने में विफलता, धोखाधड़ी गतिविधि, या लागू कानून का पालन न करने की स्थिति में, एग्रीकार्ट को पूर्व सूचना के साथ या बिना, विक्रेता के खाते को निलंबित या समाप्त करने का अधिकार सुरक्षित है। समाप्ति पर, लंबित ऑर्डर एग्रीकार्ट की नीतियों के अनुसार निपटाए जाएंगे, और एग्रीकार्ट को देय बकाया राशि विक्रेता द्वारा देय बनी रहेगी।',
            stH11: 'कानूनी अनुपालन',
            stP11: 'विक्रेता अपने उत्पाद बेचने के लिए आवश्यक सभी लाइसेंस, पंजीकरण और अनुमोदन प्राप्त करने और बनाए रखने के लिए पूर्णतः उत्तरदायी है, जिसमें जीएसटी पंजीकरण, एफएसएसएआई लाइसेंस (जहाँ लागू हो), और उनके राज्य में लागू कृषि उपज विपणन विनियम शामिल हैं। विक्रेता लागू कानून के अपने अनुपालन न करने से उत्पन्न किसी भी दावे, दंड, या देयता के विरुद्ध एग्रीकार्ट को क्षतिपूर्ति देगा।',
            stH12: 'संपर्क जानकारी',
            stH13: 'अंतिम अपडेट और शर्तों में बदलाव',
            stP13: 'एग्रीकार्ट परिचालन, कमीशन संरचना या लागू कानून में परिवर्तन को दर्शाने के लिए समय-समय पर इन विक्रेता नियम व शर्तों में संशोधन कर सकता है। महत्वपूर्ण बदलाव इस पृष्ठ पर संशोधित "अंतिम अपडेट" तारीख के साथ बताए जाएंगे, और ऐसे बदलावों के बाद विक्रेता सेवाओं का निरंतर उपयोग संशोधित शर्तों की स्वीकृति माना जाएगा।',
            stCtaTitle: 'अपने विक्रेता खाते में सहायता चाहिए?', stCtaSub: 'हमारी विक्रेता सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            stCtaEmail: 'ईमेल करें', stCtaCall: 'हेल्पलाइन कॉल करें',
            stNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक विक्रेता एग्रीमेंट के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            stHome: 'मुखपृष्ठ', stCrumb: 'विक्रेता अटी व शर्ती',
            stTitle: 'विक्रेता अटी व शर्ती',
            stSubtitle: 'अ‍ॅग्रीकार्ट मार्केटप्लेसवर उत्पादने विकणाऱ्या विक्रेत्यांना लागू होणाऱ्या अटी व शर्ती.',
            stUpdated: 'शेवटचे अद्ययावत: ऑगस्ट 2026', stReadTime: '6 मिनिटांत वाचा', stPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            stG1T: 'फक्त पडताळणी केलेले विक्रेते', stG1D: 'उत्पादने सूचीबद्ध करण्यासाठी केवायसी आणि व्यवसाय पडताळणी आवश्यक आहे.',
            stG2T: 'गुणवत्ता खात्री', stG2D: 'उत्पादनांनी अ‍ॅग्रीकार्टच्या गुणवत्ता आणि लेबलिंग मानकांची पूर्तता करणे आवश्यक आहे.',
            stG3T: 'पारदर्शक पेमेंट', stG3D: 'कमिशन आणि सेटलमेंट तपशील आधीच स्पष्ट केले जातात.',
            stG4T: 'समर्पित विक्रेता सहाय्य', stG4D: 'आमची हेल्पलाइन लिस्टिंग, ऑर्डर आणि वादांमध्ये मदत करते.',
            stIntro: 'या विक्रेता अटी व शर्ती ("विक्रेता अटी") अ‍ॅग्रीकार्ट प्लॅटफॉर्मवर कृषी उत्पादने, निविष्ठा किंवा उपकरणे विकणाऱ्या विक्रेत्यांना ("विक्रेता", "तुम्ही", "तुमचे") लागू होतात. विक्रेता म्हणून नोंदणी करून, तुम्ही अ‍ॅग्रीकार्टच्या सर्वसाधारण अटी व शर्तींव्यतिरिक्त या विक्रेता अटींनी बांधील राहण्यास सहमती देता.',
            stH1: 'विक्रेता पात्रता',
            stP1: 'अ‍ॅग्रीकार्टवरील विक्रेता खाती व्यक्ती, शेतकरी उत्पादक संस्था (एफपीओ), एकल मालकी, भागीदारी आणि कृषी उत्पादने, निविष्ठा किंवा उपकरणांच्या विक्रीत गुंतलेल्या नोंदणीकृत कंपन्यांसाठी उपलब्ध आहेत. विक्रेत्याचे वय किमान 18 वर्षे असणे आवश्यक आहे, त्यांनी अ‍ॅग्रीकार्टची केवायसी आणि व्यवसाय पडताळणी प्रक्रिया पूर्ण करणे आवश्यक आहे, तसेच पॅन, जीएसटीआयएन (जिथे लागू असेल) आणि बँक खाते तपशीलांसह वैध कागदपत्रे सादर करणे आवश्यक आहे. जिथे पडताळणी पूर्ण होऊ शकत नाही किंवा कागदपत्रे खोटी किंवा दिशाभूल करणारी आढळतात, तिथे विक्रेता प्रवेश नाकारण्याचा किंवा रद्द करण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे.',
            stH2: 'उत्पादन लिस्टिंग नियम',
            stS2L1: 'उत्पादन लिस्टिंगमध्ये प्रमाण, मापन एकक, प्रकार, दर्जा आणि स्थिती यांसह वस्तूचे अचूक वर्णन असणे आवश्यक आहे, कोणताही दिशाभूल करणारा दावा न करता.',
            stS2L2: 'अपलोड केलेली छायाचित्रे प्रत्यक्ष उत्पादनाचे खरे प्रतिनिधित्व असणे आवश्यक आहे आणि कोणत्याही तिसऱ्या पक्षाच्या कॉपीराइट किंवा ट्रेडमार्कचे उल्लंघन करू नये.',
            stS2L3: 'सूचीबद्ध उत्पादने भारतीय कायद्यांतर्गत लागू पॅकेजिंग, लेबलिंग आणि प्रकटीकरण आवश्यकतांचे पालन करतात याची खात्री करण्याची संपूर्ण जबाबदारी विक्रेत्याची आहे.',
            stS2L4: 'अचूक नसलेली, नियमांचे पालन न करणारी किंवा या विक्रेता अटींचे उल्लंघन करणारी कोणतीही लिस्टिंग संपादित, निलंबित किंवा काढून टाकण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे.',
            stH3: 'उत्पादन गुणवत्ता मानके',
            stP3: 'विक्रेत्याने खात्री करावी की विक्रीसाठी सादर केलेली सर्व उत्पादने संबंधित उत्पादन श्रेणीसाठी लागू असलेल्या गुणवत्ता, ताजेपणा आणि सुरक्षा मानकांची पूर्तता करतात, ज्यात अन्न सुरक्षा व मानक कायदा, 2006 (जिथे लागू असेल) आणि प्लॅटफॉर्मवर नमूद केलेल्या कोणत्याही ग्रेडिंग किंवा प्रमाणन मानकांचे पालन समाविष्ट आहे. निकृष्ट, भेसळयुक्त, कालबाह्य, किंवा त्यांच्या लिस्टिंगपेक्षा भौतिकदृष्ट्या वेगळी आढळणारी उत्पादने काढून टाकली जातील, आणि वारंवार गुणवत्ता उल्लंघन झाल्यास खाते निलंबित होऊ शकते.',
            stH4: 'किंमत निश्चितीच्या जबाबदाऱ्या',
            stP4: 'विक्रेता त्यांच्या उत्पादनांसाठी योग्य, अचूक आणि दिशाभूल न करणाऱ्या किंमती निश्चित करण्यास जबाबदार आहे, अन्यथा नमूद नसल्यास त्यात सर्व लागू कर समाविष्ट असतील. ऑर्डर पुष्टीकरणाच्या वेळी दर्शवलेली किंमत विक्रेत्यासाठी बंधनकारक असेल. किंमत हेराफेरी, कृत्रिम वाढ, किंवा लागू स्पर्धा कायद्याचे उल्लंघन करणाऱ्या शिकारी अंडरकटिंग पद्धतींशी संबंधित आढळणारी लिस्टिंग काढून टाकण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे.',
            stH5: 'इन्व्हेंटरी व्यवस्थापन',
            stP5: 'ओव्हरसेलिंग किंवा अनुपलब्ध साठ्यासाठी ऑर्डर स्वीकारणे टाळण्यासाठी विक्रेत्याने प्लॅटफॉर्मवर अचूक आणि अद्ययावत इन्व्हेंटरी गणना राखली पाहिजे. इन्व्हेंटरीतील तफावतीमुळे विक्रेता पुष्टी केलेली ऑर्डर पूर्ण करू शकत नसल्यास, विक्रेत्याने त्वरित अ‍ॅग्रीकार्ट आणि प्रभावित खरेदीदाराला कळवावे, आणि अ‍ॅग्रीकार्टच्या धोरणांनुसार ठरलेले कोणतेही परिणामी रद्दीकरण शुल्क सहन करावे.',
            stH6: 'ऑर्डर पूर्तता',
            stS6L1: 'पुष्टी केलेल्या ऑर्डर संबंधित उत्पादन श्रेणीसाठी प्लॅटफॉर्मवर नमूद केलेल्या कालमर्यादेत पॅक आणि पाठवल्या पाहिजेत.',
            stS6L2: 'वाहतुकीदरम्यान खराब होणे, दूषित होणे किंवा नुकसान टाळण्यासाठी विक्रेत्याने उत्पादनाच्या स्वरूपाला योग्य अशी पॅकेजिंग वापरावी.',
            stS6L3: 'जिथे लागू असेल तिथे विक्रेत्याने अचूक डिस्पॅच आणि ट्रॅकिंग माहिती द्यावी आणि वेळेवर डिलिव्हरीसाठी अ‍ॅग्रीकार्टच्या लॉजिस्टिक्स भागीदारांशी सहकार्य करावे.',
            stS6L4: 'ऑर्डर पूर्ततेत वारंवार होणारा विलंब किंवा अपयश यामुळे कामगिरी दंड, लिस्टिंग दृश्यमानता कमी होणे किंवा खाते पुनरावलोकन होऊ शकते.',
            stH7: 'परतावा आणि रिफंड जबाबदाऱ्या',
            stP7: 'विक्रेता खरेदीदारांना लागू असलेल्या अ‍ॅग्रीकार्टच्या परतावा आणि रिफंड धोरणाचे पालन करेल, ज्यात खराब, दोषपूर्ण, कालबाह्य किंवा त्यांच्या लिस्टिंगपेक्षा भौतिकदृष्ट्या वेगळ्या उत्पादनांसाठी परतावा स्वीकारणे समाविष्ट आहे. रिफंड आणि लागू वजावटी अ‍ॅग्रीकार्टच्या सेटलमेंट चक्रानुसार प्रक्रिया केल्या जातील. परतावा दायित्वांचे पालन न करणाऱ्या विक्रेत्यांची विवादित रक्कम भविष्यातील पेमेंटमधून वसूल केली जाऊ शकते.',
            stH8: 'कमिशन आणि पेमेंट',
            stP8: 'अ‍ॅग्रीकार्ट प्रत्येक यशस्वी विक्रीवर संबंधित उत्पादन श्रेणीला लागू दराने कमिशन आकारेल, जे ऑनबोर्डिंग किंवा लिस्टिंगच्या वेळी विक्रेत्याला कळवले जाईल. कमिशन, लागू कर आणि कोणत्याही वजावटीनंतरचे निव्वळ पेमेंट अ‍ॅग्रीकार्टच्या मानक सेटलमेंट चक्रानुसार विक्रेत्याच्या नोंदणीकृत बँक खात्यात जमा केले जाईल. वाद, गुणवत्ता तक्रारी किंवा संशयास्पद फसवणुकीच्या प्रकरणांचे निराकरण होईपर्यंत पेमेंट रोखण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे.',
            stH9: 'प्रतिबंधित उत्पादने',
            stS9L1: 'बनावट, कालबाह्य, प्रतिबंधित किंवा अनोंदणीकृत कृषी रसायने, कीटकनाशके आणि खते.',
            stS9L2: 'कोणत्याही लागू कायद्याचे उल्लंघन करणारी उत्पादने, ज्यात विक्रेत्याकडे नसलेल्या परवान्याची आवश्यकता असलेली उत्पादने समाविष्ट आहेत.',
            stS9L3: 'भेसळयुक्त अन्न उत्पादने किंवा वैध सहाय्यक प्रमाणपत्राशिवाय सेंद्रिय, प्रमाणित किंवा श्रेणीबद्ध म्हणून चुकीच्या पद्धतीने सादर केलेल्या वस्तू.',
            stS9L4: 'अ‍ॅग्रीकार्टच्या प्रतिबंधित आणि निर्बंधित वस्तूंच्या यादीत नमूद केलेली कोणतीही वस्तू, जी वेळोवेळी अद्ययावत केली जाते.',
            stH10: 'खाते निलंबन आणि समाप्ती',
            stP10: 'या विक्रेता अटींचे उल्लंघन, वारंवार खरेदीदार तक्रारी, गुणवत्ता किंवा पूर्तता मानके राखण्यात अपयश, फसवणूक क्रियाकलाप, किंवा लागू कायद्याचे पालन न केल्यास, अ‍ॅग्रीकार्टला पूर्वसूचनेसह किंवा त्याशिवाय विक्रेत्याचे खाते निलंबित किंवा समाप्त करण्याचा अधिकार आहे. समाप्तीनंतर, प्रलंबित ऑर्डर अ‍ॅग्रीकार्टच्या धोरणांनुसार निकाली काढल्या जातील, आणि अ‍ॅग्रीकार्टला देय असलेली थकबाकी विक्रेत्याकडून देय राहील.',
            stH11: 'कायदेशीर अनुपालन',
            stP11: 'विक्रेता त्यांची उत्पादने विकण्यासाठी आवश्यक असलेले सर्व परवाने, नोंदणी आणि मंजुरी मिळवण्यासाठी आणि राखण्यासाठी संपूर्णपणे जबाबदार आहे, ज्यात जीएसटी नोंदणी, एफएसएसएआय परवाना (जिथे लागू असेल), आणि त्यांच्या राज्यात लागू कृषी उत्पादन विपणन नियम समाविष्ट आहेत. विक्रेता लागू कायद्याचे पालन न केल्यामुळे उद्भवणाऱ्या कोणत्याही दाव्या, दंड किंवा दायित्वाविरुद्ध अ‍ॅग्रीकार्टला नुकसानभरपाई देईल.',
            stH12: 'संपर्क माहिती',
            stH13: 'शेवटचे अद्ययावत व अटींमधील बदल',
            stP13: 'अ‍ॅग्रीकार्ट कार्यपद्धती, कमिशन रचना किंवा लागू कायद्यातील बदल प्रतिबिंबित करण्यासाठी वेळोवेळी या विक्रेता अटी व शर्तींमध्ये सुधारणा करू शकते. महत्त्वाचे बदल या पानावर सुधारित "शेवटचे अद्ययावत" तारखेसह कळवले जातील, आणि असे बदल झाल्यानंतर विक्रेता सेवांचा सतत वापर सुधारित अटींची स्वीकृती मानली जाईल.',
            stCtaTitle: 'तुमच्या विक्रेता खात्यासाठी मदत हवी आहे?', stCtaSub: 'आमची विक्रेता सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            stCtaEmail: 'ईमेल करा', stCtaCall: 'हेल्पलाइनला कॉल करा',
            stNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत विक्रेता करार म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applySTLang(lang) {
        var dict = ST_I18N[lang] || ST_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applySTLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applySTLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applySTLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
