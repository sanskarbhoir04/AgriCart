<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Refund Policy Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\refund-policy.php
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

/* ── Mini info cards & timeline table ─────────────────── */
.lg-mini-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; margin-top: 0.6rem; }
.lg-mini-card { background: #f7faf6; border: 1px solid #eef2ee; border-radius: 12px; padding: 12px 14px; }
.lg-mini-card i { color: #2ecc71; font-size: 13px; margin-right: 6px; }
.lg-mini-card strong { display: block; font-family:'Poppins',sans-serif; font-size: 12.5px; color: #0f2a16; margin-bottom: 2px; }
.lg-mini-card span { font-size: 12.5px; color: #547a54; line-height: 1.5; }
@media (max-width: 560px) { .lg-mini-grid { grid-template-columns: 1fr; } }

.lg-table-wrap { overflow-x: auto; margin-top: 0.8rem; border: 1px solid #eef2ee; border-radius: 12px; }
.lg-table { width: 100%; border-collapse: collapse; font-size: 13px; }
.lg-table th, .lg-table td { text-align: left; padding: 10px 14px; border-bottom: 1px solid #eef2ee; }
.lg-table th { background: #f7faf6; font-family:'Poppins',sans-serif; font-weight: 700; color: #0f2a16; font-size: 12px; text-transform: uppercase; letter-spacing: 0.03em; }
.lg-table td { color: #33452f; }
.lg-table tr:last-child td { border-bottom: none; }

.lg-step-list { list-style: none; padding: 0; margin: 0.6rem 0 0; counter-reset: lg-step; }
.lg-step-list li { position: relative; padding-left: 34px; margin-bottom: 12px; counter-increment: lg-step; }
.lg-step-list li::before {
    content: counter(lg-step); position: absolute; left: 0; top: 0; width: 24px; height: 24px; border-radius: 50%;
    background: #2ecc71; color: #0b1a14; font-family:'Poppins',sans-serif; font-weight: 700; font-size: 11.5px;
    display: flex; align-items: center; justify-content: center;
}

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
        <div class="lg-hero-icon"><i class="fa-solid fa-hand-holding-dollar"></i></div>
        <h1 id="rfpTitle">Refund Policy</h1>
        <p id="rfpSubtitle">How and when AgriCart processes refunds for returns, cancellations, and failed payments.</p>
        <div class="lg-hero-meta">
            <span id="rfpUpdated">Last updated: July 2026</span><span class="lg-dot">•</span><span id="rfpReadTime">5 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="rfpPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-stopwatch"></i>
                <h4 id="rfpG1T">5–7 day refunds</h4>
                <p id="rfpG1D">Most refunds reach your account within 5–7 working days.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-shield-halved"></i>
                <h4 id="rfpG2T">Secure gateway</h4>
                <p id="rfpG2D">Refunds are routed back through the original payment method.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-scale-balanced"></i>
                <h4 id="rfpG3T">Fair & partial refunds</h4>
                <p id="rfpG3D">Partial refunds apply where only part of an order is affected.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-headset"></i>
                <h4 id="rfpG4T">Real support</h4>
                <p id="rfpG4D">Track every refund with help from our helpline team.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="rfpIntro">This Refund Policy explains how AgriCart handles money owed back to you — whether from an approved return, a cancelled order, a failed transaction, or a rental deposit — and how long each step typically takes.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-circle-check"></i> <span id="rfpH1">Refund Eligibility</span></h2></div>
                <ul>
                    <li id="rfpS1L1">A return has been approved and the item has passed quality inspection, as per the Return Policy.</li>
                    <li id="rfpS1L2">An order was cancelled before dispatch, or a rental booking was cancelled within the allowed window.</li>
                    <li id="rfpS1L3">A payment was deducted but the order failed to confirm, or was charged more than once due to a technical error.</li>
                    <li id="rfpS1L4">A seller was unable to fulfil a confirmed order.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-list-check"></i> <span id="rfpH2">Refund Process</span></h2></div>
                <ol class="lg-step-list">
                    <li id="rfpS2L1">Your return, cancellation, or payment issue is verified by our team.</li>
                    <li id="rfpS2L2">Once approved, a refund is initiated from AgriCart to your original payment source.</li>
                    <li id="rfpS2L3">You receive a confirmation email/SMS with the refund amount and reference ID.</li>
                    <li id="rfpS2L4">The amount is credited by your bank or payment provider within the timeline in Section 03.</li>
                </ol>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-stopwatch"></i> <span id="rfpH3">Refund Timeline</span></h2></div>
                <p id="rfpP3">Once a refund is approved on our end, it is initiated within 24–48 hours. The time it takes to reflect in your account depends on your payment method:</p>
                <div class="lg-table-wrap">
                    <table class="lg-table">
                        <thead><tr><th id="rfpTblH1">Payment Method</th><th id="rfpTblH2">Typical Refund Time</th></tr></thead>
                        <tbody>
                            <tr><td id="rfpTblR1">UPI</td><td id="rfpTblR1v">1–3 working days</td></tr>
                            <tr><td id="rfpTblR2">Credit / Debit Card</td><td id="rfpTblR2v">5–7 working days</td></tr>
                            <tr><td id="rfpTblR3">Net Banking</td><td id="rfpTblR3v">3–5 working days</td></tr>
                            <tr><td id="rfpTblR4">Cash on Delivery (COD)</td><td id="rfpTblR4v">Refunded to bank account in 5–7 working days</td></tr>
                            <tr><td id="rfpTblR5">Wallet / AgriCart Credits</td><td id="rfpTblR5v">Instant to 24 hours</td></tr>
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-credit-card"></i> <span id="rfpH4">Payment Method Refund</span></h2></div>
                <p id="rfpP4">Refunds are always issued to the original payment method used for the order — we do not redirect refunds to a different card, account, or UPI ID for security reasons. For Cash on Delivery orders, please share valid bank account details through My Orders so the refund can be processed directly to your bank.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-scale-balanced"></i> <span id="rfpH5">Partial Refund Conditions</span></h2></div>
                <ul>
                    <li id="rfpS5L1">Only some items in a multi-item order are returned or cancelled — the refund covers just those items.</li>
                    <li id="rfpS5L2">A product is returned with missing accessories, manuals, or free gifts — the value of missing parts may be deducted.</li>
                    <li id="rfpS5L3">An equipment rental is returned early — a partial refund may apply as per the rental's cancellation terms.</li>
                    <li id="rfpS5L4">A coupon or discount applied at checkout is proportionally adjusted against the refunded items.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-ban"></i> <span id="rfpH6">Cancelled Orders</span></h2></div>
                <p id="rfpP6">Orders cancelled before they are dispatched are eligible for a full refund. Orders cancelled after dispatch are treated as returns and follow the Return Policy and its associated refund timeline. Rental bookings cancelled outside the free-cancellation window may be subject to a cancellation fee, deducted from the refund.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-triangle-exclamation"></i> <span id="rfpH7">Failed Payments</span></h2></div>
                <p id="rfpP7">If an amount is debited from your account but the order does not confirm on AgriCart, the amount is automatically reversed by your bank or payment gateway, typically within 5–7 working days. If it is not reversed within this period, please contact our support team with the transaction reference ID for manual verification.</p>
                <div class="lg-warn">
                    <i class="fa-solid fa-circle-info"></i>
                    <span id="rfpWarn7">Keep your payment reference or UTR number handy — it helps our team trace and resolve failed-payment refunds faster.</span>
                </div>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-circle-xmark"></i> <span id="rfpH8">Non-Refundable Situations</span></h2></div>
                <ul>
                    <li id="rfpS8L1">Products that do not qualify for return under the Return Policy (see Non-Returnable Items).</li>
                    <li id="rfpS8L2">Change-of-mind requests raised after the applicable return window has closed.</li>
                    <li id="rfpS8L3">Equipment rental charges for days already used before an early return.</li>
                    <li id="rfpS8L4">Convenience or platform fees explicitly marked as non-refundable at checkout.</li>
                    <li id="rfpS8L5">Delivery charges, where the return is due to a change of mind rather than a seller or product error.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-user-shield"></i> <span id="rfpH9">Seller Responsibilities</span></h2></div>
                <p id="rfpP9">Sellers on AgriCart are required to honour approved returns and refunds promptly, keep listings and stock information accurate to avoid cancellations, and cooperate with AgriCart's verification process for damaged or incorrect items. Sellers who repeatedly fail to fulfil confirmed orders or dispute valid refunds may face penalties, suspension, or removal from the marketplace.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">10</span><h2><i class="fa-solid fa-headset"></i> <span id="rfpH10">Contact Support</span></h2></div>
                <p id="rfpP10">For refund status or any payment-related concern, reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our helpline at 1800-419-8888. Please keep your order ID or transaction reference ready for faster assistance.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="rfpCtaTitle">Waiting on a refund?</h3>
                    <p id="rfpCtaSub">Our support team typically replies within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="rfpCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="rfpCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="rfpNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.</p>
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

    var RFP_I18N = {
        en: {
            rfpHome: 'Home', rfpCrumb: 'Refund Policy',
            rfpTitle: 'Refund Policy',
            rfpSubtitle: 'How and when AgriCart processes refunds for returns, cancellations, and failed payments.',
            rfpUpdated: 'Last updated: July 2026', rfpReadTime: '5 min read', rfpPrint: 'Print / Save PDF',
            rfpG1T: '5–7 day refunds', rfpG1D: 'Most refunds reach your account within 5–7 working days.',
            rfpG2T: 'Secure gateway', rfpG2D: 'Refunds are routed back through the original payment method.',
            rfpG3T: 'Fair & partial refunds', rfpG3D: 'Partial refunds apply where only part of an order is affected.',
            rfpG4T: 'Real support', rfpG4D: 'Track every refund with help from our helpline team.',
            rfpIntro: "This Refund Policy explains how AgriCart handles money owed back to you — whether from an approved return, a cancelled order, a failed transaction, or a rental deposit — and how long each step typically takes.",
            rfpH1: 'Refund Eligibility',
            rfpS1L1: 'A return has been approved and the item has passed quality inspection, as per the Return Policy.',
            rfpS1L2: 'An order was cancelled before dispatch, or a rental booking was cancelled within the allowed window.',
            rfpS1L3: 'A payment was deducted but the order failed to confirm, or was charged more than once due to a technical error.',
            rfpS1L4: 'A seller was unable to fulfil a confirmed order.',
            rfpH2: 'Refund Process',
            rfpS2L1: 'Your return, cancellation, or payment issue is verified by our team.',
            rfpS2L2: 'Once approved, a refund is initiated from AgriCart to your original payment source.',
            rfpS2L3: 'You receive a confirmation email/SMS with the refund amount and reference ID.',
            rfpS2L4: 'The amount is credited by your bank or payment provider within the timeline in Section 03.',
            rfpH3: 'Refund Timeline',
            rfpP3: 'Once a refund is approved on our end, it is initiated within 24–48 hours. The time it takes to reflect in your account depends on your payment method:',
            rfpTblH1: 'Payment Method', rfpTblH2: 'Typical Refund Time',
            rfpTblR1: 'UPI', rfpTblR1v: '1–3 working days',
            rfpTblR2: 'Credit / Debit Card', rfpTblR2v: '5–7 working days',
            rfpTblR3: 'Net Banking', rfpTblR3v: '3–5 working days',
            rfpTblR4: 'Cash on Delivery (COD)', rfpTblR4v: 'Refunded to bank account in 5–7 working days',
            rfpTblR5: 'Wallet / AgriCart Credits', rfpTblR5v: 'Instant to 24 hours',
            rfpH4: 'Payment Method Refund',
            rfpP4: 'Refunds are always issued to the original payment method used for the order — we do not redirect refunds to a different card, account, or UPI ID for security reasons. For Cash on Delivery orders, please share valid bank account details through My Orders so the refund can be processed directly to your bank.',
            rfpH5: 'Partial Refund Conditions',
            rfpS5L1: 'Only some items in a multi-item order are returned or cancelled — the refund covers just those items.',
            rfpS5L2: 'A product is returned with missing accessories, manuals, or free gifts — the value of missing parts may be deducted.',
            rfpS5L3: "An equipment rental is returned early — a partial refund may apply as per the rental's cancellation terms.",
            rfpS5L4: 'A coupon or discount applied at checkout is proportionally adjusted against the refunded items.',
            rfpH6: 'Cancelled Orders',
            rfpP6: 'Orders cancelled before they are dispatched are eligible for a full refund. Orders cancelled after dispatch are treated as returns and follow the Return Policy and its associated refund timeline. Rental bookings cancelled outside the free-cancellation window may be subject to a cancellation fee, deducted from the refund.',
            rfpH7: 'Failed Payments',
            rfpP7: 'If an amount is debited from your account but the order does not confirm on AgriCart, the amount is automatically reversed by your bank or payment gateway, typically within 5–7 working days. If it is not reversed within this period, please contact our support team with the transaction reference ID for manual verification.',
            rfpWarn7: 'Keep your payment reference or UTR number handy — it helps our team trace and resolve failed-payment refunds faster.',
            rfpH8: 'Non-Refundable Situations',
            rfpS8L1: 'Products that do not qualify for return under the Return Policy (see Non-Returnable Items).',
            rfpS8L2: 'Change-of-mind requests raised after the applicable return window has closed.',
            rfpS8L3: 'Equipment rental charges for days already used before an early return.',
            rfpS8L4: 'Convenience or platform fees explicitly marked as non-refundable at checkout.',
            rfpS8L5: 'Delivery charges, where the return is due to a change of mind rather than a seller or product error.',
            rfpH9: 'Seller Responsibilities',
            rfpP9: "Sellers on AgriCart are required to honour approved returns and refunds promptly, keep listings and stock information accurate to avoid cancellations, and cooperate with AgriCart's verification process for damaged or incorrect items. Sellers who repeatedly fail to fulfil confirmed orders or dispute valid refunds may face penalties, suspension, or removal from the marketplace.",
            rfpH10: 'Contact Support',
            rfpP10: 'For refund status or any payment-related concern, reach us at support@agricart.in or call our helpline at 1800-419-8888. Please keep your order ID or transaction reference ready for faster assistance.',
            rfpCtaTitle: 'Waiting on a refund?', rfpCtaSub: 'Our support team typically replies within a few hours.',
            rfpCtaEmail: 'Email Us', rfpCtaCall: 'Call Helpline',
            rfpNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.'
        },
        hi: {
            rfpHome: 'मुखपृष्ठ', rfpCrumb: 'धनवापसी नीति',
            rfpTitle: 'धनवापसी नीति',
            rfpSubtitle: 'वापसी, रद्दीकरण और असफल भुगतान के लिए एग्रीकार्ट कैसे और कब धनवापसी प्रक्रिया करता है।',
            rfpUpdated: 'अंतिम अपडेट: जुलाई 2026', rfpReadTime: '5 मिनट में पढ़ें', rfpPrint: 'प्रिंट / पीडीएफ सेव करें',
            rfpG1T: '5–7 दिन में रिफंड', rfpG1D: 'अधिकांश रिफंड 5–7 कार्य दिवसों में आपके खाते में पहुंच जाते हैं।',
            rfpG2T: 'सुरक्षित गेटवे', rfpG2D: 'रिफंड मूल भुगतान माध्यम के माध्यम से ही वापस भेजा जाता है।',
            rfpG3T: 'निष्पक्ष व आंशिक रिफंड', rfpG3D: 'ऑर्डर का केवल एक हिस्सा प्रभावित होने पर आंशिक रिफंड लागू होता है।',
            rfpG4T: 'वास्तविक सहायता', rfpG4D: 'हमारी हेल्पलाइन टीम की मदद से हर रिफंड को ट्रैक करें।',
            rfpIntro: 'यह धनवापसी नीति बताती है कि एग्रीकार्ट आपको बकाया राशि कैसे लौटाता है — चाहे वह स्वीकृत वापसी से हो, रद्द किए गए ऑर्डर से, असफल लेनदेन से, या रेंटल जमा राशि से — और प्रत्येक चरण में आमतौर पर कितना समय लगता है।',
            rfpH1: 'रिफंड पात्रता',
            rfpS1L1: 'वापसी नीति के अनुसार वापसी स्वीकृत हो गई हो और वस्तु गुणवत्ता निरीक्षण में पास हो गई हो।',
            rfpS1L2: 'ऑर्डर डिस्पैच से पहले रद्द किया गया हो, या रेंटल बुकिंग निर्धारित समय-सीमा के भीतर रद्द की गई हो।',
            rfpS1L3: 'भुगतान काट लिया गया लेकिन ऑर्डर कन्फर्म नहीं हुआ, या तकनीकी त्रुटि के कारण एक से अधिक बार शुल्क लिया गया।',
            rfpS1L4: 'विक्रेता किसी पुष्ट ऑर्डर को पूरा करने में असमर्थ रहा हो।',
            rfpH2: 'रिफंड प्रक्रिया',
            rfpS2L1: 'आपकी वापसी, रद्दीकरण या भुगतान संबंधी समस्या की हमारी टीम द्वारा जांच की जाती है।',
            rfpS2L2: 'स्वीकृति के बाद, एग्रीकार्ट से आपके मूल भुगतान स्रोत को रिफंड शुरू किया जाता है।',
            rfpS2L3: 'आपको रिफंड राशि और संदर्भ आईडी के साथ पुष्टिकरण ईमेल/एसएमएस प्राप्त होता है।',
            rfpS2L4: 'राशि सेक्शन 03 में दी गई समय-सीमा के भीतर आपके बैंक या भुगतान प्रदाता द्वारा जमा की जाती है।',
            rfpH3: 'रिफंड की समय-सीमा',
            rfpP3: 'एक बार हमारी ओर से रिफंड स्वीकृत हो जाने पर, यह 24–48 घंटों के भीतर शुरू किया जाता है। आपके खाते में दिखने में लगने वाला समय आपके भुगतान माध्यम पर निर्भर करता है:',
            rfpTblH1: 'भुगतान माध्यम', rfpTblH2: 'सामान्य रिफंड समय',
            rfpTblR1: 'यूपीआई', rfpTblR1v: '1–3 कार्य दिवस',
            rfpTblR2: 'क्रेडिट / डेबिट कार्ड', rfpTblR2v: '5–7 कार्य दिवस',
            rfpTblR3: 'नेट बैंकिंग', rfpTblR3v: '3–5 कार्य दिवस',
            rfpTblR4: 'कैश ऑन डिलीवरी (सीओडी)', rfpTblR4v: '5–7 कार्य दिवसों में बैंक खाते में रिफंड',
            rfpTblR5: 'वॉलेट / एग्रीकार्ट क्रेडिट्स', rfpTblR5v: 'तुरंत से 24 घंटे',
            rfpH4: 'भुगतान माध्यम रिफंड',
            rfpP4: 'रिफंड हमेशा ऑर्डर के लिए इस्तेमाल किए गए मूल भुगतान माध्यम को ही जारी किया जाता है — सुरक्षा कारणों से हम रिफंड किसी अन्य कार्ड, खाते या यूपीआई आईडी पर नहीं भेजते। कैश ऑन डिलीवरी ऑर्डर के लिए, कृपया माय ऑर्डर्स के माध्यम से वैध बैंक खाता विवरण साझा करें ताकि रिफंड सीधे आपके बैंक में भेजा जा सके।',
            rfpH5: 'आंशिक रिफंड की शर्तें',
            rfpS5L1: 'मल्टी-आइटम ऑर्डर में केवल कुछ वस्तुएं वापस या रद्द की गई हों — रिफंड केवल उन्हीं वस्तुओं को कवर करता है।',
            rfpS5L2: 'उत्पाद गायब सामान, मैनुअल या मुफ्त उपहार के साथ वापस किया गया हो — गायब हिस्सों का मूल्य काटा जा सकता है।',
            rfpS5L3: 'उपकरण रेंटल जल्दी वापस किया गया हो — रेंटल की रद्दीकरण शर्तों के अनुसार आंशिक रिफंड लागू हो सकता है।',
            rfpS5L4: 'चेकआउट पर लागू किया गया कूपन या छूट रिफंड की गई वस्तुओं के अनुपात में समायोजित की जाती है।',
            rfpH6: 'रद्द किए गए ऑर्डर',
            rfpP6: 'डिस्पैच से पहले रद्द किए गए ऑर्डर पूर्ण रिफंड के योग्य हैं। डिस्पैच के बाद रद्द किए गए ऑर्डर को वापसी माना जाता है और वापसी नीति व उससे जुड़ी रिफंड समय-सीमा लागू होती है। मुफ्त-रद्दीकरण अवधि के बाहर रद्द की गई रेंटल बुकिंग पर रद्दीकरण शुल्क लग सकता है, जो रिफंड से काटा जाएगा।',
            rfpH7: 'असफल भुगतान',
            rfpP7: 'यदि आपके खाते से राशि कट गई लेकिन एग्रीकार्ट पर ऑर्डर कन्फर्म नहीं हुआ, तो यह राशि आपके बैंक या भुगतान गेटवे द्वारा स्वतः वापस कर दी जाती है, आमतौर पर 5–7 कार्य दिवसों के भीतर। यदि इस अवधि में राशि वापस नहीं होती, तो कृपया लेनदेन संदर्भ आईडी के साथ मैनुअल सत्यापन के लिए हमारी सहायता टीम से संपर्क करें।',
            rfpWarn7: 'अपना भुगतान संदर्भ या यूटीआर नंबर तैयार रखें — इससे हमारी टीम को असफल भुगतान रिफंड जल्दी ढूंढने और हल करने में मदद मिलती है।',
            rfpH8: 'गैर-रिफंड योग्य स्थितियां',
            rfpS8L1: 'ऐसे उत्पाद जो वापसी नीति के तहत वापसी योग्य नहीं हैं (गैर-वापसी योग्य वस्तुएं देखें)।',
            rfpS8L2: 'लागू वापसी अवधि समाप्त होने के बाद उठाए गए मन बदलने के अनुरोध।',
            rfpS8L3: 'जल्दी वापसी से पहले पहले से उपयोग किए गए दिनों के लिए उपकरण रेंटल शुल्क।',
            rfpS8L4: 'चेकआउट पर स्पष्ट रूप से गैर-रिफंड योग्य के रूप में चिह्नित सुविधा या प्लेटफॉर्म शुल्क।',
            rfpS8L5: 'डिलीवरी शुल्क, जहां वापसी विक्रेता या उत्पाद की त्रुटि के बजाय मन बदलने के कारण हो।',
            rfpH9: 'विक्रेता की जिम्मेदारियां',
            rfpP9: 'एग्रीकार्ट पर विक्रेताओं को स्वीकृत वापसी और रिफंड का तुरंत सम्मान करना, रद्दीकरण से बचने के लिए लिस्टिंग व स्टॉक जानकारी सटीक रखना, और क्षतिग्रस्त या गलत वस्तुओं के लिए एग्रीकार्ट की सत्यापन प्रक्रिया में सहयोग करना आवश्यक है। जो विक्रेता बार-बार पुष्ट ऑर्डर पूरा करने में विफल रहते हैं या वैध रिफंड पर विवाद करते हैं, उन्हें दंड, निलंबन या मार्केटप्लेस से हटाए जाने का सामना करना पड़ सकता है।',
            rfpH10: 'सहायता से संपर्क करें',
            rfpP10: 'रिफंड की स्थिति या किसी भी भुगतान संबंधी चिंता के लिए, हमसे support@agricart.in पर संपर्क करें या हमारी हेल्पलाइन 1800-419-8888 पर कॉल करें। तेज़ सहायता के लिए कृपया अपना ऑर्डर आईडी या लेनदेन संदर्भ तैयार रखें।',
            rfpCtaTitle: 'रिफंड का इंतजार है?', rfpCtaSub: 'हमारी सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            rfpCtaEmail: 'ईमेल करें', rfpCtaCall: 'हेल्पलाइन कॉल करें',
            rfpNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक नीति के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            rfpHome: 'मुखपृष्ठ', rfpCrumb: 'रिफंड धोरण',
            rfpTitle: 'रिफंड धोरण',
            rfpSubtitle: 'परतावा, रद्दीकरण आणि अयशस्वी पेमेंटसाठी अ‍ॅग्रीकार्ट कसा आणि केव्हा रिफंड प्रक्रिया करते.',
            rfpUpdated: 'शेवटचे अद्ययावत: जुलै 2026', rfpReadTime: '5 मिनिटांत वाचा', rfpPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            rfpG1T: '5–7 दिवसांत रिफंड', rfpG1D: 'बहुतांश रिफंड 5–7 कामकाजाच्या दिवसांत तुमच्या खात्यात जमा होतात.',
            rfpG2T: 'सुरक्षित गेटवे', rfpG2D: 'रिफंड मूळ पेमेंट पद्धतीद्वारेच परत पाठवला जातो.',
            rfpG3T: 'न्याय्य व आंशिक रिफंड', rfpG3D: 'ऑर्डरचा फक्त काही भाग प्रभावित असल्यास आंशिक रिफंड लागू होतो.',
            rfpG4T: 'खरी मदत', rfpG4D: 'आमच्या हेल्पलाइन टीमच्या मदतीने प्रत्येक रिफंड ट्रॅक करा.',
            rfpIntro: 'हे रिफंड धोरण अ‍ॅग्रीकार्ट तुमची येणे रक्कम कशी परत करते हे स्पष्ट करते — मग ती मंजूर परताव्यामुळे असो, रद्द केलेल्या ऑर्डरमुळे, अयशस्वी व्यवहारामुळे, किंवा भाडे ठेवीमुळे — आणि प्रत्येक टप्प्याला साधारणपणे किती वेळ लागतो.',
            rfpH1: 'रिफंड पात्रता',
            rfpS1L1: 'परतावा धोरणानुसार परतावा मंजूर झालेला असावा आणि वस्तू गुणवत्ता तपासणीत उत्तीर्ण झालेली असावी.',
            rfpS1L2: 'ऑर्डर डिस्पॅचपूर्वी रद्द केला गेला असावा, किंवा भाडे बुकिंग अनुमत मुदतीत रद्द केली गेली असावी.',
            rfpS1L3: 'पेमेंट कापले गेले पण ऑर्डर कन्फर्म झाला नाही, किंवा तांत्रिक त्रुटीमुळे एकापेक्षा जास्त वेळा शुल्क आकारले गेले.',
            rfpS1L4: 'विक्रेता एखादा पुष्ट ऑर्डर पूर्ण करण्यास असमर्थ ठरला असावा.',
            rfpH2: 'रिफंड प्रक्रिया',
            rfpS2L1: 'तुमचा परतावा, रद्दीकरण किंवा पेमेंट समस्या आमच्या टीमकडून पडताळली जाते.',
            rfpS2L2: 'मंजुरीनंतर, अ‍ॅग्रीकार्टकडून तुमच्या मूळ पेमेंट स्रोताला रिफंड सुरू केला जातो.',
            rfpS2L3: 'तुम्हाला रिफंड रक्कम आणि संदर्भ आयडीसह पुष्टीकरण ईमेल/एसएमएस मिळतो.',
            rfpS2L4: 'विभाग 03 मध्ये दिलेल्या कालमर्यादेत रक्कम तुमच्या बँक किंवा पेमेंट प्रदात्याद्वारे जमा केली जाते.',
            rfpH3: 'रिफंड कालमर्यादा',
            rfpP3: 'एकदा आमच्याकडून रिफंड मंजूर झाला की, तो 24–48 तासांत सुरू केला जातो. तुमच्या खात्यात दिसण्यास लागणारा वेळ तुमच्या पेमेंट पद्धतीवर अवलंबून असतो:',
            rfpTblH1: 'पेमेंट पद्धत', rfpTblH2: 'सर्वसाधारण रिफंड वेळ',
            rfpTblR1: 'यूपीआय', rfpTblR1v: '1–3 कामकाजाचे दिवस',
            rfpTblR2: 'क्रेडिट / डेबिट कार्ड', rfpTblR2v: '5–7 कामकाजाचे दिवस',
            rfpTblR3: 'नेट बँकिंग', rfpTblR3v: '3–5 कामकाजाचे दिवस',
            rfpTblR4: 'कॅश ऑन डिलिव्हरी (सीओडी)', rfpTblR4v: '5–7 कामकाजाच्या दिवसांत बँक खात्यात रिफंड',
            rfpTblR5: 'वॉलेट / अ‍ॅग्रीकार्ट क्रेडिट्स', rfpTblR5v: 'तत्काळ ते 24 तास',
            rfpH4: 'पेमेंट पद्धत रिफंड',
            rfpP4: 'रिफंड नेहमी ऑर्डरसाठी वापरलेल्या मूळ पेमेंट पद्धतीलाच जारी केला जातो — सुरक्षिततेच्या कारणास्तव आम्ही रिफंड इतर कार्ड, खाते किंवा यूपीआय आयडीवर वळवत नाही. कॅश ऑन डिलिव्हरी ऑर्डरसाठी, कृपया माय ऑर्डर्सद्वारे वैध बँक खाते तपशील द्या जेणेकरून रिफंड थेट तुमच्या बँकेत प्रक्रिया करता येईल.',
            rfpH5: 'आंशिक रिफंड अटी',
            rfpS5L1: 'मल्टी-आयटम ऑर्डरमध्ये फक्त काही वस्तू परत किंवा रद्द केल्या गेल्या असतील — रिफंड फक्त त्याच वस्तूंसाठी लागू होतो.',
            rfpS5L2: 'उत्पादन गहाळ सामान, मॅन्युअल किंवा मोफत भेटवस्तूंसह परत केले गेले असेल — गहाळ भागांची किंमत वजा केली जाऊ शकते.',
            rfpS5L3: 'उपकरण भाडे लवकर परत केले गेले असेल — भाड्याच्या रद्दीकरण अटींनुसार आंशिक रिफंड लागू होऊ शकतो.',
            rfpS5L4: 'चेकआउटवर लागू केलेले कूपन किंवा सूट रिफंड केलेल्या वस्तूंच्या प्रमाणात समायोजित केली जाते.',
            rfpH6: 'रद्द केलेले ऑर्डर',
            rfpP6: 'डिस्पॅचपूर्वी रद्द केलेले ऑर्डर पूर्ण रिफंडसाठी पात्र आहेत. डिस्पॅचनंतर रद्द केलेले ऑर्डर परतावा मानले जातात आणि परतावा धोरण व त्याच्याशी संबंधित रिफंड कालमर्यादा लागू होते. मोफत-रद्दीकरण मुदतीबाहेर रद्द केलेल्या भाडे बुकिंगवर रद्दीकरण शुल्क आकारले जाऊ शकते, जे रिफंडमधून वजा केले जाईल.',
            rfpH7: 'अयशस्वी पेमेंट',
            rfpP7: 'तुमच्या खात्यातून रक्कम कापली गेली पण अ‍ॅग्रीकार्टवर ऑर्डर कन्फर्म झाला नाही, तर ती रक्कम तुमच्या बँकेकडून किंवा पेमेंट गेटवेकडून आपोआप परत केली जाते, साधारणपणे 5–7 कामकाजाच्या दिवसांत. या कालावधीत ती परत न झाल्यास, कृपया व्यवहार संदर्भ आयडीसह मॅन्युअल पडताळणीसाठी आमच्या सहाय्य टीमशी संपर्क साधा.',
            rfpWarn7: 'तुमचा पेमेंट संदर्भ किंवा यूटीआर क्रमांक तयार ठेवा — यामुळे आमच्या टीमला अयशस्वी पेमेंट रिफंड लवकर शोधून सोडवण्यास मदत होते.',
            rfpH8: 'रिफंड न होणाऱ्या परिस्थिती',
            rfpS8L1: 'परतावा धोरणांतर्गत परत करण्यायोग्य नसलेली उत्पादने (परत न करता येणाऱ्या वस्तू पहा).',
            rfpS8L2: 'लागू परतावा मुदत संपल्यानंतर उपस्थित केलेल्या मन बदलण्याच्या विनंत्या.',
            rfpS8L3: 'लवकर परताव्यापूर्वी आधीच वापरलेल्या दिवसांसाठी उपकरण भाडे शुल्क.',
            rfpS8L4: 'चेकआउटवर स्पष्टपणे रिफंड न होणारे म्हणून चिन्हांकित सुविधा किंवा प्लॅटफॉर्म शुल्क.',
            rfpS8L5: 'डिलिव्हरी शुल्क, जेव्हा परतावा विक्रेता किंवा उत्पादनाच्या चुकीऐवजी मन बदलल्यामुळे असेल.',
            rfpH9: 'विक्रेत्याच्या जबाबदाऱ्या',
            rfpP9: 'अ‍ॅग्रीकार्टवरील विक्रेत्यांनी मंजूर परतावा आणि रिफंड त्वरित पूर्ण करणे, रद्दीकरण टाळण्यासाठी लिस्टिंग व स्टॉक माहिती अचूक ठेवणे, आणि खराब किंवा चुकीच्या वस्तूंसाठी अ‍ॅग्रीकार्टच्या पडताळणी प्रक्रियेत सहकार्य करणे आवश्यक आहे. जे विक्रेते वारंवार पुष्ट ऑर्डर पूर्ण करण्यात अपयशी ठरतात किंवा वैध रिफंडवर वाद घालतात त्यांना दंड, निलंबन किंवा मार्केटप्लेसमधून काढून टाकले जाऊ शकते.',
            rfpH10: 'सहाय्याशी संपर्क साधा',
            rfpP10: 'रिफंड स्थिती किंवा कोणत्याही पेमेंट-संबंधित चिंतेसाठी, आमच्याशी support@agricart.in वर संपर्क साधा किंवा आमच्या हेल्पलाइन 1800-419-8888 वर कॉल करा. जलद मदतीसाठी कृपया तुमचा ऑर्डर आयडी किंवा व्यवहार संदर्भ तयार ठेवा.',
            rfpCtaTitle: 'रिफंडची वाट पाहत आहात?', rfpCtaSub: 'आमची सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            rfpCtaEmail: 'ईमेल करा', rfpCtaCall: 'हेल्पलाइनला कॉल करा',
            rfpNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत धोरण म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applyRFPLang(lang) {
        var dict = RFP_I18N[lang] || RFP_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applyRFPLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applyRFPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applyRFPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
