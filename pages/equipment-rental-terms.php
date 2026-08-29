<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Equipment Rental Terms & Conditions Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\equipment-rental-terms.php
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
        <div class="lg-hero-icon"><i class="fa-solid fa-tractor"></i></div>
        <h1 id="rtTitle">Equipment Rental Terms &amp; Conditions</h1>
        <p id="rtSubtitle">The terms governing rental of agricultural equipment, machinery, and tools through the AgriCart platform.</p>
        <div class="lg-hero-meta">
            <span id="rtUpdated">Last updated: August 2026</span><span class="lg-dot">•</span><span id="rtReadTime">6 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="rtPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-id-card"></i>
                <h4 id="rtG1T">Valid ID Required</h4>
                <p id="rtG1D">Government-issued ID and address proof mandatory for every rental.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-shield-halved"></i>
                <h4 id="rtG2T">Refundable Deposit</h4>
                <p id="rtG2D">Security deposit is fully refundable after satisfactory equipment return.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-truck-fast"></i>
                <h4 id="rtG3T">Doorstep Delivery</h4>
                <p id="rtG3D">Equipment delivered and collected from your registered farm address.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-headset"></i>
                <h4 id="rtG4T">24x7 Rental Support</h4>
                <p id="rtG4D">Our helpline assists with breakdowns, extensions, and queries.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="rtIntro">These Equipment Rental Terms &amp; Conditions ("Rental Terms") govern the rental of tractors, implements, tools, and other agricultural machinery ("Equipment") listed on the AgriCart platform by farmers, agri-entrepreneurs, and registered rental partners ("Renter", "you", "your"). By submitting a booking request, you agree to be bound by these Rental Terms in addition to AgriCart's general Terms &amp; Conditions.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-id-card"></i> <span id="rtH1">Rental Eligibility</span></h2></div>
                <p id="rtP1">Equipment rental services are available only to registered AgriCart users who are at least 18 years of age and have completed identity verification. Renters must provide a valid government-issued photo identification (Aadhaar, Voter ID, Driving Licence, or Passport) and proof of address at the time of booking. AgriCart reserves the right to refuse or cancel a rental booking where eligibility documents are incomplete, expired, or found to be fraudulent.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-calendar-check"></i> <span id="rtH2">Booking Process</span></h2></div>
                <ul>
                    <li id="rtS2L1">Bookings may be made through the AgriCart app or website by selecting the desired equipment, rental duration, and delivery location.</li>
                    <li id="rtS2L2">A booking is confirmed only upon successful payment of the applicable advance amount and issuance of a booking confirmation notice.</li>
                    <li id="rtS2L3">Renters are advised to verify equipment specifications, attachments, and operational condition mentioned in the listing prior to confirming a booking.</li>
                    <li id="rtS2L4">AgriCart reserves the right to reassign an equivalent equipment unit in case of unavailability, subject to prior intimation to the Renter.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-indian-rupee-sign"></i> <span id="rtH3">Rental Charges</span></h2></div>
                <p id="rtP3">Rental charges are calculated on a per-day, per-week, or per-acre basis as displayed at the time of booking and are inclusive of applicable taxes unless stated otherwise. Charges for fuel, operator (where applicable), transportation beyond the serviceable radius, and any additional attachments shall be borne separately by the Renter. AgriCart reserves the right to revise rental tariffs periodically, and such revisions shall apply prospectively to new bookings only.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-vault"></i> <span id="rtH4">Security Deposit</span></h2></div>
                <p id="rtP4">A refundable security deposit, as specified at the time of booking, shall be collected prior to equipment handover. The deposit shall be adjusted against any outstanding dues, damages, or penalties identified during the return inspection and the balance, if any, shall be refunded to the Renter's original payment method within 7–10 business days of equipment return.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-clock"></i> <span id="rtH5">Rental Duration</span></h2></div>
                <p id="rtP5">The rental period commences from the time of equipment handover at the designated location and concludes upon its return and acceptance by AgriCart or its authorised rental partner. Renters may request an extension of the rental period, subject to equipment availability and payment of prorated charges, by notifying AgriCart at least 12 hours prior to the scheduled return time.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-magnifying-glass"></i> <span id="rtH6">Equipment Inspection</span></h2></div>
                <ul>
                    <li id="rtS6L1">A joint inspection of the equipment shall be conducted at the time of handover, and any pre-existing wear, damage, or defects shall be documented and shared with the Renter.</li>
                    <li id="rtS6L2">Renters are required to inspect the equipment for operational readiness before accepting handover and report discrepancies immediately.</li>
                    <li id="rtS6L3">A similar inspection shall be conducted upon return of the equipment to assess its condition against the handover record.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-user-check"></i> <span id="rtH7">Customer Responsibilities</span></h2></div>
                <ul>
                    <li id="rtS7L1">Operate the Equipment strictly for the agricultural purpose for which it is intended and in accordance with the manufacturer's guidelines.</li>
                    <li id="rtS7L2">Ensure that the Equipment is operated only by individuals who are duly competent, trained, and, where legally required, hold a valid licence.</li>
                    <li id="rtS7L3">Take reasonable care to protect the Equipment from theft, misuse, or damage while in the Renter's custody.</li>
                    <li id="rtS7L4">Promptly notify AgriCart of any malfunction, accident, or damage occurring during the rental period.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-gears"></i> <span id="rtH8">Equipment Usage Rules</span></h2></div>
                <p id="rtP8">The Equipment shall not be sub-let, transferred, or used by any third party without the prior written consent of AgriCart. The Equipment shall not be used beyond its rated capacity, modified, or used for any unlawful purpose. Use of the Equipment outside the geographical area specified at the time of booking is strictly prohibited unless expressly authorised.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-triangle-exclamation"></i> <span id="rtH9">Damage &amp; Loss Policy</span></h2></div>
                <p id="rtP9">The Renter shall be liable for any damage, breakdown, or loss of the Equipment arising from negligence, misuse, or unauthorised operation during the rental period, and the cost of repair or replacement, as assessed by AgriCart or its authorised technician, shall be recovered from the security deposit and, where the deposit is insufficient, from the Renter directly. Normal wear and tear consistent with proper use shall not be charged to the Renter.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">10</span><h2><i class="fa-solid fa-hourglass-half"></i> <span id="rtH10">Late Return Charges</span></h2></div>
                <p id="rtP10">Equipment not returned within the agreed rental period, without a prior approved extension, shall attract late return charges calculated on a pro-rata basis at the applicable daily rental rate, in addition to any other charges accrued. Continued failure to return the Equipment may result in recovery proceedings and forfeiture of the security deposit.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">11</span><h2><i class="fa-solid fa-ban"></i> <span id="rtH11">Cancellation Rules</span></h2></div>
                <ul>
                    <li id="rtS11L1">Bookings cancelled at least 24 hours prior to the scheduled handover time shall be eligible for a full refund of the advance amount paid.</li>
                    <li id="rtS11L2">Cancellations made within 24 hours of the scheduled handover time shall attract a cancellation fee as displayed at the time of booking.</li>
                    <li id="rtS11L3">AgriCart reserves the right to cancel a booking due to equipment unavailability, force majeure, or verification failure, in which case the full advance amount shall be refunded.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">12</span><h2><i class="fa-solid fa-rotate-left"></i> <span id="rtH12">Refund Rules</span></h2></div>
                <p id="rtP12">Eligible refunds, including security deposit balances and cancellation refunds, shall be processed to the Renter's original payment method within 7–10 business days of the refund being approved. Refunds may be withheld, in part or full, where outstanding dues, damages, or violations of these Rental Terms are identified during the return inspection.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">13</span><h2><i class="fa-solid fa-hard-hat"></i> <span id="rtH13">Safety Guidelines</span></h2></div>
                <ul>
                    <li id="rtS13L1">Wear appropriate personal protective equipment and follow all safety instructions provided at the time of handover.</li>
                    <li id="rtS13L2">Do not operate the Equipment under the influence of alcohol, fatigue, or any impairing substance.</li>
                    <li id="rtS13L3">Keep bystanders, children, and animals at a safe distance while the Equipment is in operation.</li>
                    <li id="rtS13L4">Immediately switch off and secure the Equipment in the event of any malfunction, and contact AgriCart's helpline for assistance.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">14</span><h2><i class="fa-solid fa-headset"></i> <span id="rtH14">Contact Information</span></h2></div>
                <p id="rtP14">For rental bookings, extensions, breakdown assistance, or any queries relating to these Rental Terms, please reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our rental helpline at <a href="tel:18004198888">1800-419-8888</a>.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">15</span><h2><i class="fa-solid fa-clock-rotate-left"></i> <span id="rtH15">Last Updated &amp; Changes to These Terms</span></h2></div>
                <p id="rtP15">AgriCart may revise these Equipment Rental Terms &amp; Conditions from time to time to reflect changes in operations, pricing, or applicable law. Material changes will be communicated on this page along with a revised "Last updated" date. Continued use of the rental service after such changes constitutes acceptance of the revised terms.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="rtCtaTitle">Need help with a rental booking?</h3>
                    <p id="rtCtaSub">Our rental support team typically responds within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="rtCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="rtCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="rtNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official rental agreement.</p>
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

    var ERT_I18N = {
        en: {
            rtHome: 'Home', rtCrumb: 'Equipment Rental Terms & Conditions',
            rtTitle: 'Equipment Rental Terms & Conditions',
            rtSubtitle: 'The terms governing rental of agricultural equipment, machinery, and tools through the AgriCart platform.',
            rtUpdated: 'Last updated: August 2026', rtReadTime: '6 min read', rtPrint: 'Print / Save PDF',
            rtG1T: 'Valid ID Required', rtG1D: 'Government-issued ID and address proof mandatory for every rental.',
            rtG2T: 'Refundable Deposit', rtG2D: 'Security deposit is fully refundable after satisfactory equipment return.',
            rtG3T: 'Doorstep Delivery', rtG3D: 'Equipment delivered and collected from your registered farm address.',
            rtG4T: '24x7 Rental Support', rtG4D: 'Our helpline assists with breakdowns, extensions, and queries.',
            rtIntro: 'These Equipment Rental Terms & Conditions ("Rental Terms") govern the rental of tractors, implements, tools, and other agricultural machinery ("Equipment") listed on the AgriCart platform by farmers, agri-entrepreneurs, and registered rental partners ("Renter", "you", "your"). By submitting a booking request, you agree to be bound by these Rental Terms in addition to AgriCart\'s general Terms & Conditions.',
            rtH1: 'Rental Eligibility',
            rtP1: 'Equipment rental services are available only to registered AgriCart users who are at least 18 years of age and have completed identity verification. Renters must provide a valid government-issued photo identification (Aadhaar, Voter ID, Driving Licence, or Passport) and proof of address at the time of booking. AgriCart reserves the right to refuse or cancel a rental booking where eligibility documents are incomplete, expired, or found to be fraudulent.',
            rtH2: 'Booking Process',
            rtS2L1: 'Bookings may be made through the AgriCart app or website by selecting the desired equipment, rental duration, and delivery location.',
            rtS2L2: 'A booking is confirmed only upon successful payment of the applicable advance amount and issuance of a booking confirmation notice.',
            rtS2L3: 'Renters are advised to verify equipment specifications, attachments, and operational condition mentioned in the listing prior to confirming a booking.',
            rtS2L4: 'AgriCart reserves the right to reassign an equivalent equipment unit in case of unavailability, subject to prior intimation to the Renter.',
            rtH3: 'Rental Charges',
            rtP3: 'Rental charges are calculated on a per-day, per-week, or per-acre basis as displayed at the time of booking and are inclusive of applicable taxes unless stated otherwise. Charges for fuel, operator (where applicable), transportation beyond the serviceable radius, and any additional attachments shall be borne separately by the Renter. AgriCart reserves the right to revise rental tariffs periodically, and such revisions shall apply prospectively to new bookings only.',
            rtH4: 'Security Deposit',
            rtP4: "A refundable security deposit, as specified at the time of booking, shall be collected prior to equipment handover. The deposit shall be adjusted against any outstanding dues, damages, or penalties identified during the return inspection and the balance, if any, shall be refunded to the Renter's original payment method within 7–10 business days of equipment return.",
            rtH5: 'Rental Duration',
            rtP5: 'The rental period commences from the time of equipment handover at the designated location and concludes upon its return and acceptance by AgriCart or its authorised rental partner. Renters may request an extension of the rental period, subject to equipment availability and payment of prorated charges, by notifying AgriCart at least 12 hours prior to the scheduled return time.',
            rtH6: 'Equipment Inspection',
            rtS6L1: 'A joint inspection of the equipment shall be conducted at the time of handover, and any pre-existing wear, damage, or defects shall be documented and shared with the Renter.',
            rtS6L2: 'Renters are required to inspect the equipment for operational readiness before accepting handover and report discrepancies immediately.',
            rtS6L3: 'A similar inspection shall be conducted upon return of the equipment to assess its condition against the handover record.',
            rtH7: 'Customer Responsibilities',
            rtS7L1: "Operate the Equipment strictly for the agricultural purpose for which it is intended and in accordance with the manufacturer's guidelines.",
            rtS7L2: 'Ensure that the Equipment is operated only by individuals who are duly competent, trained, and, where legally required, hold a valid licence.',
            rtS7L3: "Take reasonable care to protect the Equipment from theft, misuse, or damage while in the Renter's custody.",
            rtS7L4: 'Promptly notify AgriCart of any malfunction, accident, or damage occurring during the rental period.',
            rtH8: 'Equipment Usage Rules',
            rtP8: 'The Equipment shall not be sub-let, transferred, or used by any third party without the prior written consent of AgriCart. The Equipment shall not be used beyond its rated capacity, modified, or used for any unlawful purpose. Use of the Equipment outside the geographical area specified at the time of booking is strictly prohibited unless expressly authorised.',
            rtH9: 'Damage & Loss Policy',
            rtP9: 'The Renter shall be liable for any damage, breakdown, or loss of the Equipment arising from negligence, misuse, or unauthorised operation during the rental period, and the cost of repair or replacement, as assessed by AgriCart or its authorised technician, shall be recovered from the security deposit and, where the deposit is insufficient, from the Renter directly. Normal wear and tear consistent with proper use shall not be charged to the Renter.',
            rtH10: 'Late Return Charges',
            rtP10: 'Equipment not returned within the agreed rental period, without a prior approved extension, shall attract late return charges calculated on a pro-rata basis at the applicable daily rental rate, in addition to any other charges accrued. Continued failure to return the Equipment may result in recovery proceedings and forfeiture of the security deposit.',
            rtH11: 'Cancellation Rules',
            rtS11L1: 'Bookings cancelled at least 24 hours prior to the scheduled handover time shall be eligible for a full refund of the advance amount paid.',
            rtS11L2: 'Cancellations made within 24 hours of the scheduled handover time shall attract a cancellation fee as displayed at the time of booking.',
            rtS11L3: 'AgriCart reserves the right to cancel a booking due to equipment unavailability, force majeure, or verification failure, in which case the full advance amount shall be refunded.',
            rtH12: 'Refund Rules',
            rtP12: "Eligible refunds, including security deposit balances and cancellation refunds, shall be processed to the Renter's original payment method within 7–10 business days of the refund being approved. Refunds may be withheld, in part or full, where outstanding dues, damages, or violations of these Rental Terms are identified during the return inspection.",
            rtH13: 'Safety Guidelines',
            rtS13L1: 'Wear appropriate personal protective equipment and follow all safety instructions provided at the time of handover.',
            rtS13L2: 'Do not operate the Equipment under the influence of alcohol, fatigue, or any impairing substance.',
            rtS13L3: 'Keep bystanders, children, and animals at a safe distance while the Equipment is in operation.',
            rtS13L4: "Immediately switch off and secure the Equipment in the event of any malfunction, and contact AgriCart's helpline for assistance.",
            rtH14: 'Contact Information',
            rtH15: 'Last Updated & Changes to These Terms',
            rtP15: 'AgriCart may revise these Equipment Rental Terms & Conditions from time to time to reflect changes in operations, pricing, or applicable law. Material changes will be communicated on this page along with a revised "Last updated" date. Continued use of the rental service after such changes constitutes acceptance of the revised terms.',
            rtCtaTitle: 'Need help with a rental booking?', rtCtaSub: 'Our rental support team typically responds within a few hours.',
            rtCtaEmail: 'Email Us', rtCtaCall: 'Call Helpline',
            rtNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official rental agreement.'
        },
        hi: {
            rtHome: 'मुखपृष्ठ', rtCrumb: 'उपकरण किराया नियम व शर्तें',
            rtTitle: 'उपकरण किराया नियम व शर्तें',
            rtSubtitle: 'एग्रीकार्ट प्लेटफ़ॉर्म के माध्यम से कृषि उपकरण, मशीनरी और औजारों के किराए को नियंत्रित करने वाले नियम।',
            rtUpdated: 'अंतिम अपडेट: अगस्त 2026', rtReadTime: '6 मिनट में पढ़ें', rtPrint: 'प्रिंट / पीडीएफ सेव करें',
            rtG1T: 'वैध पहचान पत्र आवश्यक', rtG1D: 'हर बुकिंग के लिए सरकारी पहचान पत्र और पता प्रमाण अनिवार्य है।',
            rtG2T: 'वापसी योग्य जमा राशि', rtG2D: 'उपकरण संतोषजनक स्थिति में लौटाने पर सुरक्षा जमा पूरी तरह वापस की जाती है।',
            rtG3T: 'घर-द्वार डिलीवरी', rtG3D: 'उपकरण आपके पंजीकृत खेत के पते पर पहुँचाया और वापस लिया जाता है।',
            rtG4T: '24x7 रेंटल सहायता', rtG4D: 'हमारी हेल्पलाइन खराबी, अवधि विस्तार और प्रश्नों में सहायता करती है।',
            rtIntro: 'ये उपकरण किराया नियम व शर्तें ("रेंटल शर्तें") एग्रीकार्ट प्लेटफ़ॉर्म पर सूचीबद्ध ट्रैक्टर, उपकरण, औजार और अन्य कृषि मशीनरी ("उपकरण") के किराए को नियंत्रित करती हैं, जो किसानों, कृषि-उद्यमियों और पंजीकृत रेंटल पार्टनर ("किरायेदार", "आप", "आपका") द्वारा लिया जाता है। बुकिंग अनुरोध सबमिट करके, आप एग्रीकार्ट की सामान्य नियम व शर्तों के अतिरिक्त इन रेंटल शर्तों से बाध्य होने के लिए सहमत होते हैं।',
            rtH1: 'किराया पात्रता',
            rtP1: 'उपकरण किराया सेवा केवल उन पंजीकृत एग्रीकार्ट उपयोगकर्ताओं के लिए उपलब्ध है जिनकी आयु कम से कम 18 वर्ष है और जिन्होंने पहचान सत्यापन पूरा कर लिया है। किरायेदार को बुकिंग के समय एक वैध सरकारी फोटो पहचान पत्र (आधार, मतदाता पहचान पत्र, ड्राइविंग लाइसेंस या पासपोर्ट) और पता प्रमाण प्रस्तुत करना आवश्यक है। एग्रीकार्ट को उन स्थितियों में बुकिंग अस्वीकार या रद्द करने का अधिकार सुरक्षित है जहाँ पात्रता दस्तावेज़ अधूरे, समाप्त हो चुके, या धोखाधड़ीपूर्ण पाए जाते हैं।',
            rtH2: 'बुकिंग प्रक्रिया',
            rtS2L1: 'बुकिंग एग्रीकार्ट ऐप या वेबसाइट के माध्यम से वांछित उपकरण, किराया अवधि और डिलीवरी स्थान चुनकर की जा सकती है।',
            rtS2L2: 'बुकिंग की पुष्टि केवल लागू अग्रिम राशि के सफल भुगतान और बुकिंग पुष्टिकरण जारी होने पर ही मानी जाती है।',
            rtS2L3: 'किरायेदार को सलाह दी जाती है कि बुकिंग की पुष्टि से पहले लिस्टिंग में उल्लिखित उपकरण विनिर्देश, अनुलग्नक और परिचालन स्थिति की जाँच कर लें।',
            rtS2L4: 'अनुपलब्धता की स्थिति में, एग्रीकार्ट को किरायेदार को पूर्व सूचना देकर समकक्ष उपकरण इकाई प्रदान करने का अधिकार सुरक्षित है।',
            rtH3: 'किराया शुल्क',
            rtP3: 'किराया शुल्क की गणना बुकिंग के समय प्रदर्शित प्रति-दिन, प्रति-सप्ताह या प्रति-एकड़ आधार पर की जाती है और जब तक अन्यथा न बताया जाए, इसमें लागू कर शामिल होते हैं। ईंधन, ऑपरेटर (जहाँ लागू हो), सेवा क्षेत्र से बाहर परिवहन, और किसी भी अतिरिक्त अनुलग्नक का शुल्क किरायेदार द्वारा अलग से वहन किया जाएगा। एग्रीकार्ट को समय-समय पर किराया दरों में संशोधन करने का अधिकार सुरक्षित है, और ऐसे संशोधन केवल नई बुकिंग पर ही लागू होंगे।',
            rtH4: 'सुरक्षा जमा राशि',
            rtP4: 'बुकिंग के समय निर्दिष्ट एक वापसी योग्य सुरक्षा जमा राशि उपकरण सौंपे जाने से पहले एकत्र की जाएगी। यह जमा राशि वापसी निरीक्षण के दौरान पहचाने गए किसी भी बकाया राशि, क्षति या दंड के विरुद्ध समायोजित की जाएगी, और शेष राशि, यदि कोई हो, उपकरण वापसी के 7–10 कार्य दिवसों के भीतर किरायेदार के मूल भुगतान माध्यम में वापस कर दी जाएगी।',
            rtH5: 'किराया अवधि',
            rtP5: 'किराया अवधि निर्दिष्ट स्थान पर उपकरण सौंपे जाने के समय से आरंभ होती है और एग्रीकार्ट या उसके अधिकृत रेंटल पार्टनर द्वारा इसकी वापसी और स्वीकृति पर समाप्त होती है। किरायेदार निर्धारित वापसी समय से कम से कम 12 घंटे पहले एग्रीकार्ट को सूचित करके, उपकरण की उपलब्धता और आनुपातिक शुल्क के भुगतान के अधीन, किराया अवधि बढ़ाने का अनुरोध कर सकता है।',
            rtH6: 'उपकरण निरीक्षण',
            rtS6L1: 'उपकरण सौंपे जाने के समय एक संयुक्त निरीक्षण किया जाएगा, और किसी भी पूर्व-विद्यमान घिसावट, क्षति या दोष को दर्ज कर किरायेदार के साथ साझा किया जाएगा।',
            rtS6L2: 'किरायेदार को उपकरण स्वीकार करने से पहले उसकी परिचालन तैयारी की जाँच करनी होगी और किसी भी विसंगति की तुरंत सूचना देनी होगी।',
            rtS6L3: 'उपकरण वापसी पर सौंपे जाने के समय के रिकॉर्ड की तुलना में उसकी स्थिति का आकलन करने के लिए इसी प्रकार का निरीक्षण किया जाएगा।',
            rtH7: 'ग्राहक की जिम्मेदारियाँ',
            rtS7L1: 'उपकरण का उपयोग केवल उसी कृषि प्रयोजन हेतु करें जिसके लिए वह अभिप्रेत है, और निर्माता के दिशानिर्देशों के अनुसार करें।',
            rtS7L2: 'सुनिश्चित करें कि उपकरण का संचालन केवल उन व्यक्तियों द्वारा किया जाए जो उचित रूप से सक्षम, प्रशिक्षित हों और जहाँ कानूनी रूप से आवश्यक हो, वैध लाइसेंस रखते हों।',
            rtS7L3: 'किरायेदार की अभिरक्षा में रहते हुए उपकरण को चोरी, दुरुपयोग या क्षति से बचाने के लिए उचित सावधानी बरतें।',
            rtS7L4: 'किराया अवधि के दौरान होने वाली किसी भी खराबी, दुर्घटना या क्षति की एग्रीकार्ट को तुरंत सूचना दें।',
            rtH8: 'उपकरण उपयोग नियम',
            rtP8: 'एग्रीकार्ट की पूर्व लिखित सहमति के बिना उपकरण को किसी तीसरे पक्ष को उप-किराए पर, स्थानांतरित या उसके द्वारा उपयोग नहीं किया जाएगा। उपकरण का उपयोग उसकी निर्धारित क्षमता से अधिक, संशोधित रूप में, या किसी गैरकानूनी उद्देश्य के लिए नहीं किया जाएगा। बुकिंग के समय निर्दिष्ट भौगोलिक क्षेत्र के बाहर उपकरण का उपयोग तब तक सख्त वर्जित है जब तक कि स्पष्ट रूप से अधिकृत न किया गया हो।',
            rtH9: 'क्षति और हानि नीति',
            rtP9: 'किराया अवधि के दौरान लापरवाही, दुरुपयोग या अनधिकृत संचालन से उत्पन्न उपकरण की किसी भी क्षति, खराबी या हानि के लिए किरायेदार उत्तरदायी होगा, और एग्रीकार्ट या उसके अधिकृत तकनीशियन द्वारा आकलित मरम्मत या प्रतिस्थापन की लागत सुरक्षा जमा राशि से वसूल की जाएगी, और जहाँ जमा राशि अपर्याप्त हो, वहाँ सीधे किरायेदार से वसूल की जाएगी। सामान्य उपयोग के अनुरूप घिसावट के लिए किरायेदार से शुल्क नहीं लिया जाएगा।',
            rtH10: 'विलंबित वापसी शुल्क',
            rtP10: 'सहमत किराया अवधि के भीतर, बिना पूर्व-अनुमोदित विस्तार के, वापस न किए गए उपकरण पर लागू दैनिक किराया दर पर आनुपातिक आधार पर गणना किए गए विलंबित वापसी शुल्क लगेंगे, साथ ही अर्जित अन्य शुल्क भी। उपकरण वापस न किए जाने की निरंतरता पर वसूली कार्यवाही और सुरक्षा जमा राशि की जब्ती हो सकती है।',
            rtH11: 'रद्दीकरण नियम',
            rtS11L1: 'निर्धारित सौंपे जाने के समय से कम से कम 24 घंटे पहले रद्द की गई बुकिंग भुगतान की गई अग्रिम राशि की पूर्ण वापसी के लिए पात्र होगी।',
            rtS11L2: 'निर्धारित सौंपे जाने के समय से 24 घंटे के भीतर की गई रद्दीकरण पर बुकिंग के समय प्रदर्शित रद्दीकरण शुल्क लगेगा।',
            rtS11L3: 'उपकरण अनुपलब्धता, अप्रत्याशित घटना (फोर्स मेज्योर), या सत्यापन विफलता के कारण एग्रीकार्ट को बुकिंग रद्द करने का अधिकार सुरक्षित है, ऐसी स्थिति में पूर्ण अग्रिम राशि वापस की जाएगी।',
            rtH12: 'रिफंड नियम',
            rtP12: 'पात्र रिफंड, जिसमें सुरक्षा जमा शेष राशि और रद्दीकरण रिफंड शामिल हैं, रिफंड स्वीकृत होने के 7–10 कार्य दिवसों के भीतर किरायेदार के मूल भुगतान माध्यम में प्रोसेस किए जाएंगे। जहाँ वापसी निरीक्षण के दौरान बकाया राशि, क्षति या इन रेंटल शर्तों के उल्लंघन की पहचान होती है, वहाँ रिफंड आंशिक या पूर्ण रूप से रोका जा सकता है।',
            rtH13: 'सुरक्षा दिशानिर्देश',
            rtS13L1: 'सौंपे जाने के समय दिए गए उपयुक्त व्यक्तिगत सुरक्षा उपकरण पहनें और सभी सुरक्षा निर्देशों का पालन करें।',
            rtS13L2: 'शराब, थकान या किसी अन्य प्रभावकारी पदार्थ के प्रभाव में उपकरण का संचालन न करें।',
            rtS13L3: 'उपकरण के संचालन के दौरान दर्शकों, बच्चों और पशुओं को सुरक्षित दूरी पर रखें।',
            rtS13L4: 'किसी भी खराबी की स्थिति में तुरंत उपकरण बंद करें, उसे सुरक्षित करें, और सहायता के लिए एग्रीकार्ट की हेल्पलाइन से संपर्क करें।',
            rtH14: 'संपर्क जानकारी',
            rtH15: 'अंतिम अपडेट और शर्तों में बदलाव',
            rtP15: 'एग्रीकार्ट परिचालन, मूल्य निर्धारण या लागू कानून में परिवर्तन को दर्शाने के लिए समय-समय पर इन उपकरण किराया नियम व शर्तों में संशोधन कर सकता है। महत्वपूर्ण बदलाव इस पृष्ठ पर संशोधित "अंतिम अपडेट" तारीख के साथ बताए जाएंगे। ऐसे बदलावों के बाद रेंटल सेवा का निरंतर उपयोग संशोधित शर्तों की स्वीकृति माना जाएगा।',
            rtCtaTitle: 'रेंटल बुकिंग में सहायता चाहिए?', rtCtaSub: 'हमारी रेंटल सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            rtCtaEmail: 'ईमेल करें', rtCtaCall: 'हेल्पलाइन कॉल करें',
            rtNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक रेंटल एग्रीमेंट के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            rtHome: 'मुखपृष्ठ', rtCrumb: 'उपकरण भाडे अटी व शर्ती',
            rtTitle: 'उपकरण भाडे अटी व शर्ती',
            rtSubtitle: 'अ‍ॅग्रीकार्ट प्लॅटफॉर्मद्वारे कृषी उपकरणे, यंत्रसामग्री आणि अवजारांच्या भाड्याला नियंत्रित करणाऱ्या अटी.',
            rtUpdated: 'शेवटचे अद्ययावत: ऑगस्ट 2026', rtReadTime: '6 मिनिटांत वाचा', rtPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            rtG1T: 'वैध ओळखपत्र आवश्यक', rtG1D: 'प्रत्येक बुकिंगसाठी सरकारी ओळखपत्र आणि पत्ता पुरावा अनिवार्य आहे.',
            rtG2T: 'परतफेड करण्यायोग्य ठेव', rtG2D: 'उपकरण समाधानकारक स्थितीत परत केल्यावर सुरक्षा ठेव पूर्णपणे परत केली जाते.',
            rtG3T: 'घरपोच सेवा', rtG3D: 'उपकरण तुमच्या नोंदणीकृत शेताच्या पत्त्यावर पोहोचवले व परत घेतले जाते.',
            rtG4T: '24x7 भाडे सहाय्य', rtG4D: 'आमची हेल्पलाइन बिघाड, मुदतवाढ आणि प्रश्नांसाठी मदत करते.',
            rtIntro: 'ही उपकरण भाडे अटी व शर्ती ("भाडे अटी") अ‍ॅग्रीकार्ट प्लॅटफॉर्मवर सूचीबद्ध ट्रॅक्टर, अवजारे, साधने आणि इतर कृषी यंत्रसामग्री ("उपकरण") यांच्या भाड्याला नियंत्रित करतात, जे शेतकरी, कृषी-उद्योजक आणि नोंदणीकृत भाडे भागीदार ("भाडेकरू", "तुम्ही", "तुमचे") घेतात. बुकिंग विनंती सबमिट करून, तुम्ही अ‍ॅग्रीकार्टच्या सर्वसाधारण अटी व शर्तींव्यतिरिक्त या भाडे अटींनी बांधील राहण्यास सहमती देता.',
            rtH1: 'भाडे पात्रता',
            rtP1: 'उपकरण भाडे सेवा फक्त अशा नोंदणीकृत अ‍ॅग्रीकार्ट वापरकर्त्यांसाठी उपलब्ध आहे ज्यांचे वय किमान 18 वर्षे आहे आणि ज्यांनी ओळख पडताळणी पूर्ण केली आहे. भाडेकरूने बुकिंगच्या वेळी वैध सरकारी छायाचित्र ओळखपत्र (आधार, मतदार ओळखपत्र, वाहन चालक परवाना किंवा पासपोर्ट) आणि पत्ता पुरावा सादर करणे आवश्यक आहे. पात्रता कागदपत्रे अपूर्ण, कालबाह्य किंवा फसवी आढळल्यास बुकिंग नाकारण्याचा किंवा रद्द करण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे.',
            rtH2: 'बुकिंग प्रक्रिया',
            rtS2L1: 'बुकिंग अ‍ॅग्रीकार्ट अ‍ॅप किंवा वेबसाइटद्वारे इच्छित उपकरण, भाडे कालावधी आणि डिलिव्हरी स्थान निवडून करता येते.',
            rtS2L2: 'लागू आगाऊ रकमेच्या यशस्वी पेमेंटनंतर आणि बुकिंग पुष्टीकरण जारी झाल्यावरच बुकिंग पक्की मानली जाते.',
            rtS2L3: 'बुकिंग निश्चित करण्यापूर्वी भाडेकरूंनी लिस्टिंगमध्ये नमूद केलेली उपकरण वैशिष्ट्ये, जोडणी आणि कार्यक्षम स्थिती तपासावी असा सल्ला दिला जातो.',
            rtS2L4: 'अनुपलब्धतेच्या स्थितीत, भाडेकरूला पूर्वसूचना देऊन समतुल्य उपकरण देण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे.',
            rtH3: 'भाडे शुल्क',
            rtP3: 'भाडे शुल्काची गणना बुकिंगच्या वेळी दर्शवलेल्या प्रति-दिवस, प्रति-आठवडा किंवा प्रति-एकर आधारावर केली जाते आणि अन्यथा नमूद नसल्यास त्यात लागू कर समाविष्ट असतात. इंधन, ऑपरेटर (जिथे लागू असेल), सेवा क्षेत्राबाहेरील वाहतूक आणि कोणतेही अतिरिक्त जोडणी यांचा खर्च भाडेकरूने स्वतंत्रपणे उचलावा. अ‍ॅग्रीकार्टला वेळोवेळी भाडे दरात सुधारणा करण्याचा अधिकार आहे, आणि अशा सुधारणा फक्त नवीन बुकिंगसाठीच लागू होतील.',
            rtH4: 'सुरक्षा ठेव',
            rtP4: 'बुकिंगच्या वेळी नमूद केलेली परतफेड करण्यायोग्य सुरक्षा ठेव उपकरण हस्तांतरणापूर्वी घेतली जाईल. परतीच्या तपासणीदरम्यान आढळलेली कोणतीही थकबाकी, नुकसान किंवा दंड या ठेवीतून वजा केला जाईल, आणि उरलेली रक्कम, असल्यास, उपकरण परत केल्यानंतर 7–10 कामकाजी दिवसांत भाडेकरूच्या मूळ पेमेंट माध्यमात परत केली जाईल.',
            rtH5: 'भाडे कालावधी',
            rtP5: 'भाडे कालावधी निर्दिष्ट ठिकाणी उपकरण हस्तांतरणाच्या वेळेपासून सुरू होतो आणि अ‍ॅग्रीकार्ट किंवा त्याच्या अधिकृत भाडे भागीदाराकडून त्याचा परतावा व स्वीकृती झाल्यावर संपतो. भाडेकरू ठरलेल्या परतीच्या वेळेपूर्वी किमान 12 तास आधी अ‍ॅग्रीकार्टला कळवून, उपकरणाच्या उपलब्धतेच्या आणि प्रमाणशीर शुल्काच्या अधीन राहून भाडे कालावधी वाढवण्याची विनंती करू शकतो.',
            rtH6: 'उपकरण तपासणी',
            rtS6L1: 'हस्तांतरणाच्या वेळी संयुक्त तपासणी केली जाईल आणि कोणतीही पूर्व-विद्यमान झीज, नुकसान किंवा दोष नोंदवून भाडेकरूसोबत शेअर केला जाईल.',
            rtS6L2: 'भाडेकरूने उपकरण स्वीकारण्यापूर्वी त्याची कार्यक्षम तयारी तपासावी आणि कोणतीही तफावत त्वरित कळवावी.',
            rtS6L3: 'उपकरण परत केल्यावर हस्तांतरणाच्या वेळच्या नोंदीशी तुलना करून त्याच्या स्थितीचे मूल्यांकन करण्यासाठी अशीच तपासणी केली जाईल.',
            rtH7: 'ग्राहकाच्या जबाबदाऱ्या',
            rtS7L1: 'उपकरण फक्त त्यासाठी अभिप्रेत असलेल्या कृषी कारणासाठी आणि उत्पादकाच्या मार्गदर्शक तत्त्वांनुसार वापरा.',
            rtS7L2: 'उपकरणाचे संचालन फक्त योग्यरित्या सक्षम, प्रशिक्षित आणि कायद्याने आवश्यक असल्यास वैध परवाना असलेल्या व्यक्तींकडूनच केले जाईल याची खात्री करा.',
            rtS7L3: 'भाडेकरूच्या ताब्यात असताना उपकरणाला चोरी, गैरवापर किंवा नुकसानापासून वाचवण्यासाठी योग्य ती काळजी घ्या.',
            rtS7L4: 'भाडे कालावधीत घडणाऱ्या कोणत्याही बिघाड, अपघात किंवा नुकसानाची अ‍ॅग्रीकार्टला त्वरित माहिती द्या.',
            rtH8: 'उपकरण वापर नियम',
            rtP8: 'अ‍ॅग्रीकार्टच्या पूर्व लेखी संमतीशिवाय उपकरण कोणत्याही तिसऱ्या पक्षाला उप-भाड्याने, हस्तांतरित किंवा त्याच्याकडून वापरले जाणार नाही. उपकरणाचा वापर त्याच्या निर्धारित क्षमतेपेक्षा जास्त, सुधारित स्वरूपात किंवा कोणत्याही बेकायदेशीर हेतूसाठी केला जाणार नाही. बुकिंगच्या वेळी नमूद भौगोलिक क्षेत्राबाहेर उपकरणाचा वापर स्पष्टपणे अधिकृत केल्याशिवाय सक्त निषिद्ध आहे.',
            rtH9: 'नुकसान व हानी धोरण',
            rtP9: 'भाडे कालावधीत निष्काळजीपणा, गैरवापर किंवा अनधिकृत संचालनामुळे उपकरणाचे कोणतेही नुकसान, बिघाड किंवा हानी झाल्यास भाडेकरू जबाबदार असेल, आणि अ‍ॅग्रीकार्ट किंवा त्याच्या अधिकृत तंत्रज्ञाने ठरवलेली दुरुस्ती किंवा बदलीची किंमत सुरक्षा ठेवीतून वसूल केली जाईल, आणि ठेव अपुरी असल्यास थेट भाडेकरूकडून वसूल केली जाईल. योग्य वापरामुळे होणाऱ्या सामान्य झिजेसाठी भाडेकरूकडून शुल्क आकारले जाणार नाही.',
            rtH10: 'उशिरा परतीचे शुल्क',
            rtP10: 'मान्य भाडे कालावधीत, पूर्व-मंजूर मुदतवाढीशिवाय, परत न केलेल्या उपकरणावर लागू दैनिक भाडे दरानुसार प्रमाणशीर आधारावर उशिरा परतीचे शुल्क आकारले जाईल, तसेच इतर जमा झालेले शुल्कही लागू होईल. उपकरण सतत परत न केल्यास वसुली कारवाई आणि सुरक्षा ठेव जप्त होऊ शकते.',
            rtH11: 'रद्दीकरण नियम',
            rtS11L1: 'ठरलेल्या हस्तांतरण वेळेपूर्वी किमान 24 तास आधी रद्द केलेली बुकिंग भरलेल्या आगाऊ रकमेच्या पूर्ण परताव्यासाठी पात्र असेल.',
            rtS11L2: 'ठरलेल्या हस्तांतरण वेळेच्या 24 तासांच्या आत केलेल्या रद्दीकरणावर बुकिंगच्या वेळी दर्शवलेले रद्दीकरण शुल्क आकारले जाईल.',
            rtS11L3: 'उपकरण अनुपलब्धता, अपरिहार्य परिस्थिती (फोर्स मेजर) किंवा पडताळणी अपयशामुळे बुकिंग रद्द करण्याचा अधिकार अ‍ॅग्रीकार्टकडे राखीव आहे, अशा स्थितीत संपूर्ण आगाऊ रक्कम परत केली जाईल.',
            rtH12: 'परतावा नियम',
            rtP12: 'पात्र परतावे, ज्यात सुरक्षा ठेवीची शिल्लक रक्कम आणि रद्दीकरण परतावा समाविष्ट आहे, परतावा मंजूर झाल्यानंतर 7–10 कामकाजी दिवसांत भाडेकरूच्या मूळ पेमेंट माध्यमात प्रक्रिया केली जाईल. परतीच्या तपासणीदरम्यान थकबाकी, नुकसान किंवा या भाडे अटींचे उल्लंघन आढळल्यास परतावा अंशतः किंवा पूर्णपणे रोखला जाऊ शकतो.',
            rtH13: 'सुरक्षा मार्गदर्शक तत्त्वे',
            rtS13L1: 'हस्तांतरणाच्या वेळी दिलेली योग्य वैयक्तिक सुरक्षा उपकरणे परिधान करा आणि सर्व सुरक्षा सूचनांचे पालन करा.',
            rtS13L2: 'दारू, थकवा किंवा इतर कोणत्याही परिणामकारक पदार्थाच्या प्रभावाखाली उपकरण चालवू नका.',
            rtS13L3: 'उपकरण चालू असताना प्रेक्षक, लहान मुले आणि जनावरे यांना सुरक्षित अंतरावर ठेवा.',
            rtS13L4: 'कोणताही बिघाड झाल्यास त्वरित उपकरण बंद करून सुरक्षित करा आणि मदतीसाठी अ‍ॅग्रीकार्टच्या हेल्पलाइनशी संपर्क साधा.',
            rtH14: 'संपर्क माहिती',
            rtH15: 'शेवटचे अद्ययावत व अटींमधील बदल',
            rtP15: 'अ‍ॅग्रीकार्ट कार्यपद्धती, किंमत किंवा लागू कायद्यातील बदल प्रतिबिंबित करण्यासाठी वेळोवेळी या उपकरण भाडे अटी व शर्तींमध्ये सुधारणा करू शकते. महत्त्वाचे बदल या पानावर सुधारित "शेवटचे अद्ययावत" तारखेसह कळवले जातील. असे बदल झाल्यानंतर भाडे सेवेचा सतत वापर सुधारित अटींची स्वीकृती मानली जाईल.',
            rtCtaTitle: 'भाडे बुकिंगसाठी मदत हवी आहे?', rtCtaSub: 'आमची भाडे सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            rtCtaEmail: 'ईमेल करा', rtCtaCall: 'हेल्पलाइनला कॉल करा',
            rtNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत भाडे करार म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applyERTLang(lang) {
        var dict = ERT_I18N[lang] || ERT_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applyERTLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applyERTLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applyERTLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
