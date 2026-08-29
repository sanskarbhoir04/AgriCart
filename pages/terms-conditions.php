<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Terms & Conditions Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\terms-conditions.php
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
        <div class="lg-hero-icon"><i class="fa-solid fa-file-contract"></i></div>
        <h1 id="tcTitle">Terms &amp; Conditions</h1>
        <p id="tcSubtitle">The rules that govern how you use the AgriCart marketplace, equipment rentals, and advisory services.</p>
        <div class="lg-hero-meta">
            <span id="tcUpdated">Last updated: July 2026</span><span class="lg-dot">•</span><span id="tcReadTime">5 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="tcPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-handshake"></i>
                <h4 id="tcG1T">Fair marketplace</h4>
                <p id="tcG1D">Transparent listings for farmers, sellers, and buyers.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-rotate-left"></i>
                <h4 id="tcG2T">7-day returns</h4>
                <p id="tcG2D">Return most orders within 7 days of delivery.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-tractor"></i>
                <h4 id="tcG3T">Verified rentals</h4>
                <p id="tcG3D">Clear terms on deposits and equipment condition.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-headset"></i>
                <h4 id="tcG4T">24/7 helpline</h4>
                <p id="tcG4D">Real support whenever you have a question.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="tcIntro">Welcome to AgriCart. By accessing or using our website, app, marketplace, equipment rental, or advisory services, you agree to be bound by these Terms &amp; Conditions. Please read them carefully.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-user-check"></i> <span id="tcH1">Using AgriCart</span></h2></div>
                <ul>
                    <li id="tcS1L1">You must provide accurate information when creating an account.</li>
                    <li id="tcS1L2">You are responsible for maintaining the confidentiality of your login credentials.</li>
                    <li id="tcS1L3">You agree to use the platform only for lawful purposes related to farming, agri-commerce, and equipment rental.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-store"></i> <span id="tcH2">Orders &amp; Marketplace</span></h2></div>
                <p id="tcP2">Product listings, pricing, and availability on the Agri Store and Krishi Bazaar are subject to change without prior notice. AgriCart acts as a facilitator connecting farmers, sellers, and buyers, and is not responsible for the quality of goods listed by third-party sellers beyond our stated quality checks.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-tractor"></i> <span id="tcH3">Equipment Rental</span></h2></div>
                <p id="tcP3">Rental Hub bookings are subject to equipment availability, security deposit terms, and the rental duration selected at checkout. Damage to rented equipment beyond normal wear and tear may be charged to the renter.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-seedling"></i> <span id="tcH4">Crop Advisory</span></h2></div>
                <p id="tcP4">Crop Advisory recommendations, including AI-based disease diagnosis, are provided for guidance only and do not replace professional agronomic advice. Final farming decisions remain the responsibility of the user.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-credit-card"></i> <span id="tcH5">Payments &amp; Refunds</span></h2></div>
                <p id="tcP5">Payments made on AgriCart are processed through secure third-party payment gateways. Refunds for failed payments are typically processed within 5–7 working days. Returns are accepted within 7 days of delivery as per our return policy.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-gavel"></i> <span id="tcH6">User Conduct</span></h2></div>
                <ul>
                    <li id="tcS6L1">Do not post false, misleading, or fraudulent listings.</li>
                    <li id="tcS6L2">Do not misuse the Agri Connect community for spam or harassment.</li>
                    <li id="tcS6L3">AgriCart reserves the right to suspend accounts that violate these terms.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-scale-balanced"></i> <span id="tcH7">Limitation of Liability</span></h2></div>
                <p id="tcP7">AgriCart is not liable for indirect losses arising from crop outcomes, weather-related delays, third-party seller disputes, or service interruptions beyond our reasonable control.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-clock-rotate-left"></i> <span id="tcH8">Changes to These Terms</span></h2></div>
                <p id="tcP8">We may revise these Terms &amp; Conditions periodically. Continued use of the platform after changes are posted constitutes acceptance of the updated terms.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">09</span><h2><i class="fa-solid fa-headset"></i> <span id="tcH9">Contact Us</span></h2></div>
                <p id="tcP9">For questions about these terms, reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our helpline at 1800-419-8888.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="tcCtaTitle">Need help understanding these terms?</h3>
                    <p id="tcCtaSub">Our support team typically replies within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="tcCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="tcCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="tcNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official terms.</p>
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

    var TC_I18N = {
        en: {
            tcHome: 'Home', tcCrumb: 'Terms & Conditions',
            tcTitle: 'Terms & Conditions',
            tcSubtitle: 'The rules that govern how you use the AgriCart marketplace, equipment rentals, and advisory services.',
            tcUpdated: 'Last updated: July 2026', tcReadTime: '5 min read', tcPrint: 'Print / Save PDF',
            tcG1T: 'Fair marketplace', tcG1D: 'Transparent listings for farmers, sellers, and buyers.',
            tcG2T: '7-day returns', tcG2D: 'Return most orders within 7 days of delivery.',
            tcG3T: 'Verified rentals', tcG3D: 'Clear terms on deposits and equipment condition.',
            tcG4T: '24/7 helpline', tcG4D: 'Real support whenever you have a question.',
            tcIntro: 'Welcome to AgriCart. By accessing or using our website, app, marketplace, equipment rental, or advisory services, you agree to be bound by these Terms & Conditions. Please read them carefully.',
            tcH1: 'Using AgriCart',
            tcS1L1: 'You must provide accurate information when creating an account.',
            tcS1L2: 'You are responsible for maintaining the confidentiality of your login credentials.',
            tcS1L3: 'You agree to use the platform only for lawful purposes related to farming, agri-commerce, and equipment rental.',
            tcH2: 'Orders & Marketplace',
            tcP2: 'Product listings, pricing, and availability on the Agri Store and Krishi Bazaar are subject to change without prior notice. AgriCart acts as a facilitator connecting farmers, sellers, and buyers, and is not responsible for the quality of goods listed by third-party sellers beyond our stated quality checks.',
            tcH3: 'Equipment Rental',
            tcP3: 'Rental Hub bookings are subject to equipment availability, security deposit terms, and the rental duration selected at checkout. Damage to rented equipment beyond normal wear and tear may be charged to the renter.',
            tcH4: 'Crop Advisory',
            tcP4: 'Crop Advisory recommendations, including AI-based disease diagnosis, are provided for guidance only and do not replace professional agronomic advice. Final farming decisions remain the responsibility of the user.',
            tcH5: 'Payments & Refunds',
            tcP5: 'Payments made on AgriCart are processed through secure third-party payment gateways. Refunds for failed payments are typically processed within 5–7 working days. Returns are accepted within 7 days of delivery as per our return policy.',
            tcH6: 'User Conduct',
            tcS6L1: 'Do not post false, misleading, or fraudulent listings.',
            tcS6L2: 'Do not misuse the Agri Connect community for spam or harassment.',
            tcS6L3: 'AgriCart reserves the right to suspend accounts that violate these terms.',
            tcH7: 'Limitation of Liability',
            tcP7: 'AgriCart is not liable for indirect losses arising from crop outcomes, weather-related delays, third-party seller disputes, or service interruptions beyond our reasonable control.',
            tcH8: 'Changes to These Terms',
            tcP8: 'We may revise these Terms & Conditions periodically. Continued use of the platform after changes are posted constitutes acceptance of the updated terms.',
            tcH9: 'Contact Us',
            tcCtaTitle: 'Need help understanding these terms?', tcCtaSub: 'Our support team typically replies within a few hours.',
            tcCtaEmail: 'Email Us', tcCtaCall: 'Call Helpline',
            tcNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official terms.'
        },
        hi: {
            tcHome: 'मुखपृष्ठ', tcCrumb: 'नियम और शर्तें',
            tcTitle: 'नियम और शर्तें',
            tcSubtitle: 'एग्रीकार्ट मार्केटप्लेस, उपकरण रेंटल और सलाहकार सेवाओं के उपयोग को नियंत्रित करने वाले नियम।',
            tcUpdated: 'अंतिम अपडेट: जुलाई 2026', tcReadTime: '5 मिनट में पढ़ें', tcPrint: 'प्रिंट / पीडीएफ सेव करें',
            tcG1T: 'निष्पक्ष मार्केटप्लेस', tcG1D: 'किसानों, विक्रेताओं और खरीदारों के लिए पारदर्शी लिस्टिंग।',
            tcG2T: '7-दिन की वापसी', tcG2D: 'डिलीवरी के 7 दिनों के भीतर अधिकांश ऑर्डर वापस करें।',
            tcG3T: 'सत्यापित रेंटल', tcG3D: 'डिपॉज़िट और उपकरण स्थिति पर स्पष्ट शर्तें।',
            tcG4T: '24/7 हेल्पलाइन', tcG4D: 'जब भी आपका कोई सवाल हो, असली सहायता उपलब्ध।',
            tcIntro: 'एग्रीकार्ट में आपका स्वागत है। हमारी वेबसाइट, ऐप, मार्केटप्लेस, उपकरण रेंटल या सलाहकार सेवाओं का उपयोग करके, आप इन नियमों और शर्तों से बंधे होने के लिए सहमत होते हैं। कृपया इन्हें ध्यान से पढ़ें।',
            tcH1: 'एग्रीकार्ट का उपयोग',
            tcS1L1: 'खाता बनाते समय आपको सही जानकारी देनी होगी।',
            tcS1L2: 'अपने लॉगिन विवरण की गोपनीयता बनाए रखने की जिम्मेदारी आपकी है।',
            tcS1L3: 'आप सहमत हैं कि प्लेटफ़ॉर्म का उपयोग केवल खेती, कृषि-वाणिज्य और उपकरण रेंटल से जुड़े वैध उद्देश्यों के लिए करेंगे।',
            tcH2: 'ऑर्डर और मार्केटप्लेस',
            tcP2: 'एग्री स्टोर और कृषि बाज़ार पर उत्पाद लिस्टिंग, मूल्य और उपलब्धता बिना पूर्व सूचना के बदल सकती है। एग्रीकार्ट किसानों, विक्रेताओं और खरीदारों को जोड़ने वाला एक माध्यम है, और हमारी बताई गई गुणवत्ता जांच से परे तीसरे पक्ष के विक्रेताओं द्वारा सूचीबद्ध वस्तुओं की गुणवत्ता के लिए जिम्मेदार नहीं है।',
            tcH3: 'उपकरण रेंटल',
            tcP3: 'रेंटल हब बुकिंग उपकरण की उपलब्धता, सुरक्षा जमा शर्तों और चेकआउट पर चुनी गई रेंटल अवधि के अधीन हैं। सामान्य टूट-फूट से अधिक क्षति के लिए किराएदार से शुल्क लिया जा सकता है।',
            tcH4: 'फसल सलाह',
            tcP4: 'फसल सलाह सुझाव, जिसमें AI-आधारित रोग निदान शामिल है, केवल मार्गदर्शन के लिए दिए जाते हैं और पेशेवर कृषि सलाह का विकल्प नहीं हैं। अंतिम खेती निर्णय उपयोगकर्ता की जिम्मेदारी है।',
            tcH5: 'भुगतान और रिफंड',
            tcP5: 'एग्रीकार्ट पर भुगतान सुरक्षित थर्ड-पार्टी पेमेंट गेटवे के माध्यम से प्रोसेस किए जाते हैं। विफल भुगतान का रिफंड आमतौर पर 5–7 कार्य दिवसों में प्रोसेस होता है। हमारी रिटर्न नीति के अनुसार डिलीवरी के 7 दिनों के भीतर रिटर्न स्वीकार किए जाते हैं।',
            tcH6: 'उपयोगकर्ता आचरण',
            tcS6L1: 'गलत, भ्रामक या धोखाधड़ी वाली लिस्टिंग पोस्ट न करें।',
            tcS6L2: 'स्पैम या उत्पीड़न के लिए एग्री कनेक्ट समुदाय का दुरुपयोग न करें।',
            tcS6L3: 'एग्रीकार्ट इन शर्तों का उल्लंघन करने वाले खातों को निलंबित करने का अधिकार सुरक्षित रखता है।',
            tcH7: 'दायित्व की सीमा',
            tcP7: 'फसल परिणामों, मौसम संबंधी देरी, तीसरे पक्ष के विक्रेता विवादों, या हमारे उचित नियंत्रण से बाहर सेवा में रुकावट से उत्पन्न अप्रत्यक्ष नुकसान के लिए एग्रीकार्ट उत्तरदायी नहीं है।',
            tcH8: 'इन शर्तों में बदलाव',
            tcP8: 'हम समय-समय पर इन नियमों और शर्तों में संशोधन कर सकते हैं। बदलाव पोस्ट होने के बाद प्लेटफ़ॉर्म का निरंतर उपयोग अपडेटेड शर्तों की स्वीकृति माना जाएगा।',
            tcH9: 'संपर्क करें',
            tcCtaTitle: 'इन शर्तों को समझने में मदद चाहिए?', tcCtaSub: 'हमारी सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            tcCtaEmail: 'ईमेल करें', tcCtaCall: 'हेल्पलाइन कॉल करें',
            tcNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक शर्तों के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            tcHome: 'मुखपृष्ठ', tcCrumb: 'अटी व शर्ती',
            tcTitle: 'अटी व शर्ती',
            tcSubtitle: 'अ‍ॅग्रीकार्ट मार्केटप्लेस, उपकरण भाडे आणि सल्ला सेवांचा वापर नियंत्रित करणारे नियम.',
            tcUpdated: 'शेवटचे अद्ययावत: जुलै 2026', tcReadTime: '5 मिनिटांत वाचा', tcPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            tcG1T: 'निष्पक्ष मार्केटप्लेस', tcG1D: 'शेतकरी, विक्रेते आणि खरेदीदारांसाठी पारदर्शी लिस्टिंग.',
            tcG2T: '7-दिवसांचा परतावा', tcG2D: 'डिलिव्हरीच्या 7 दिवसांत बहुतांश ऑर्डर परत करा.',
            tcG3T: 'सत्यापित भाडे', tcG3D: 'डिपॉझिट आणि उपकरण स्थितीबाबत स्पष्ट अटी.',
            tcG4T: '24/7 हेल्पलाइन', tcG4D: 'तुमचा प्रश्न असेल तेव्हा खरी मदत उपलब्ध.',
            tcIntro: 'अ‍ॅग्रीकार्टमध्ये आपले स्वागत आहे. आमची वेबसाइट, अ‍ॅप, मार्केटप्लेस, उपकरण भाडे किंवा सल्ला सेवा वापरून, तुम्ही या अटी व शर्तींशी बांधील राहण्यास सहमत आहात. कृपया त्या काळजीपूर्वक वाचा.',
            tcH1: 'अ‍ॅग्रीकार्ट वापरणे',
            tcS1L1: 'खाते तयार करताना तुम्ही अचूक माहिती द्यावी.',
            tcS1L2: 'तुमच्या लॉगिन तपशीलांची गोपनीयता राखण्याची जबाबदारी तुमची आहे.',
            tcS1L3: 'तुम्ही प्लॅटफॉर्मचा वापर फक्त शेती, कृषी-व्यापार आणि उपकरण भाड्याशी संबंधित कायदेशीर कारणांसाठी कराल यास सहमत आहात.',
            tcH2: 'ऑर्डर आणि मार्केटप्लेस',
            tcP2: 'अ‍ॅग्री स्टोअर आणि कृषी बाजारावरील उत्पादन लिस्टिंग, किंमत आणि उपलब्धता पूर्वसूचनेशिवाय बदलू शकते. अ‍ॅग्रीकार्ट शेतकरी, विक्रेते आणि खरेदीदार यांना जोडणारे माध्यम आहे, आणि आमच्या नमूद गुणवत्ता तपासणीच्या पलीकडे तृतीय-पक्ष विक्रेत्यांनी सूचीबद्ध केलेल्या वस्तूंच्या गुणवत्तेसाठी जबाबदार नाही.',
            tcH3: 'उपकरण भाडे',
            tcP3: 'रेंटल हब बुकिंग उपकरणाच्या उपलब्धतेवर, सुरक्षा ठेव अटींवर आणि चेकआउटच्या वेळी निवडलेल्या भाडे कालावधीवर अवलंबून असते. सामान्य झीज-तोडीपेक्षा जास्त नुकसानीसाठी भाडेकरूकडून शुल्क आकारले जाऊ शकते.',
            tcH4: 'पीक सल्ला',
            tcP4: 'AI-आधारित रोग निदानासह पीक सल्ला शिफारसी केवळ मार्गदर्शनासाठी दिल्या जातात आणि व्यावसायिक कृषी सल्ल्याची जागा घेत नाहीत. अंतिम शेती निर्णय वापरकर्त्याची जबाबदारी आहे.',
            tcH5: 'पेमेंट आणि परतावा',
            tcP5: 'अ‍ॅग्रीकार्टवरील पेमेंट सुरक्षित तृतीय-पक्ष पेमेंट गेटवेद्वारे प्रक्रिया केले जातात. अयशस्वी पेमेंटचा परतावा साधारणपणे 5–7 कामकाजी दिवसांत प्रक्रिया केला जातो. आमच्या परतावा धोरणानुसार डिलिव्हरीच्या 7 दिवसांत परतावा स्वीकारला जातो.',
            tcH6: 'वापरकर्ता वर्तन',
            tcS6L1: 'खोटी, दिशाभूल करणारी किंवा फसवी लिस्टिंग पोस्ट करू नका.',
            tcS6L2: 'स्पॅम किंवा छळासाठी अ‍ॅग्री कनेक्ट समुदायाचा गैरवापर करू नका.',
            tcS6L3: 'या अटींचे उल्लंघन करणारी खाती निलंबित करण्याचा अधिकार अ‍ॅग्रीकार्ट राखून ठेवते.',
            tcH7: 'दायित्वाची मर्यादा',
            tcP7: 'पीक परिणाम, हवामान-संबंधित विलंब, तृतीय-पक्ष विक्रेता वाद किंवा आमच्या वाजवी नियंत्रणाबाहेरील सेवा व्यत्ययामुळे उद्भवणाऱ्या अप्रत्यक्ष नुकसानीसाठी अ‍ॅग्रीकार्ट जबाबदार नाही.',
            tcH8: 'या अटींमधील बदल',
            tcP8: 'आम्ही वेळोवेळी या अटी व शर्तींमध्ये सुधारणा करू शकतो. बदल पोस्ट झाल्यानंतर प्लॅटफॉर्मचा सतत वापर अद्ययावत अटींची स्वीकृती मानली जाईल.',
            tcH9: 'संपर्क साधा',
            tcCtaTitle: 'या अटी समजून घेण्यासाठी मदत हवी?', tcCtaSub: 'आमची सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            tcCtaEmail: 'ईमेल करा', tcCtaCall: 'हेल्पलाइनला कॉल करा',
            tcNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत अटी म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applyTCLang(lang) {
        var dict = TC_I18N[lang] || TC_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applyTCLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applyTCLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applyTCLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
