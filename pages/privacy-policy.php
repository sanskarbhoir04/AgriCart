<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — Privacy Policy Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\privacy-policy.php
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
        <div class="lg-hero-icon"><i class="fa-solid fa-shield-halved"></i></div>
        <h1 id="ppTitle">Privacy Policy</h1>
        <p id="ppSubtitle">How AgriCart collects, uses, and protects your information across the marketplace, rentals, and advisory services.</p>
        <div class="lg-hero-meta">
            <span id="ppUpdated">Last updated: July 2026</span><span class="lg-dot">•</span><span id="ppReadTime">4 min read</span>
        </div>
        <a href="javascript:window.print()" class="lg-hero-print"><i class="fa-solid fa-print"></i> <span id="ppPrint">Print / Save PDF</span></a>
    </div>

    <div class="lg-glance">
        <div class="lg-glance-grid">
            <div class="lg-glance-card">
                <i class="fa-solid fa-ban"></i>
                <h4 id="ppG1T">We never sell data</h4>
                <p id="ppG1D">Your information is never sold to third parties.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-lock"></i>
                <h4 id="ppG2T">Secure by default</h4>
                <p id="ppG2D">Industry-standard safeguards protect your account.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-sliders"></i>
                <h4 id="ppG3T">You're in control</h4>
                <p id="ppG3D">Opt out of promotions anytime, keep order updates.</p>
            </div>
            <div class="lg-glance-card">
                <i class="fa-solid fa-trash-can"></i>
                <h4 id="ppG4T">Delete on request</h4>
                <p id="ppG4D">Ask support and we'll remove your account data.</p>
            </div>
        </div>
    </div>

    <div class="lg-body">
        <div class="lg-paper">
        <main>
            <p class="lg-intro" id="ppIntro">AgriCart ("we", "us", "our") operates this website and mobile experience to connect Indian farmers with buyers, equipment rentals, and agricultural services. This Privacy Policy explains what information we collect, how we use it, and the choices you have.</p>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">01</span><h2><i class="fa-solid fa-database"></i> <span id="ppH1">Information We Collect</span></h2></div>
                <ul>
                    <li id="ppS1L1">Account details you provide — name, phone number, email address, and delivery address.</li>
                    <li id="ppS1L2">Order, listing, and rental booking history on the platform.</li>
                    <li id="ppS1L3">Location data (with your permission) to show nearby mandi prices, weather, and rental equipment.</li>
                    <li id="ppS1L4">Device and usage information such as browser type and pages visited, used for improving the site.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">02</span><h2><i class="fa-solid fa-gears"></i> <span id="ppH2">How We Use Your Information</span></h2></div>
                <ul>
                    <li id="ppS2L1">To process orders, rentals, and crop advisory requests.</li>
                    <li id="ppS2L2">To send order updates, offers, and helpline communications.</li>
                    <li id="ppS2L3">To personalize marketplace recommendations and Krishi Bazaar prices for your region.</li>
                    <li id="ppS2L4">To improve platform security and prevent fraudulent activity.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">03</span><h2><i class="fa-solid fa-share-nodes"></i> <span id="ppH3">Sharing of Information</span></h2></div>
                <p id="ppP3">We do not sell your personal information. We may share limited data with delivery partners, payment gateways, and equipment rental partners strictly to fulfil your orders and bookings, and with authorities where required by law.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">04</span><h2><i class="fa-solid fa-lock"></i> <span id="ppH4">Data Security</span></h2></div>
                <p id="ppP4">We use reasonable technical and organizational safeguards to protect your data. However, no method of transmission over the internet is completely secure, and we cannot guarantee absolute security.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">05</span><h2><i class="fa-solid fa-sliders"></i> <span id="ppH5">Your Choices</span></h2></div>
                <ul>
                    <li id="ppS5L1">You can update your profile information anytime from your account settings.</li>
                    <li id="ppS5L2">You may opt out of promotional messages while continuing to receive essential order updates.</li>
                    <li id="ppS5L3">You can request deletion of your account by contacting our support team.</li>
                </ul>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">06</span><h2><i class="fa-solid fa-cookie-bite"></i> <span id="ppH6">Cookies</span></h2></div>
                <p id="ppP6">We use cookies and local storage to keep you signed in, remember your language preference, and understand how the site is used, so we can improve it over time.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">07</span><h2><i class="fa-solid fa-clock-rotate-left"></i> <span id="ppH7">Changes to This Policy</span></h2></div>
                <p id="ppP7">We may update this Privacy Policy from time to time. Significant changes will be communicated on this page with a revised "Last updated" date.</p>
            </section>

            <section class="lg-section">
                <div class="lg-section-head"><span class="lg-num">08</span><h2><i class="fa-solid fa-headset"></i> <span id="ppH8">Contact Us</span></h2></div>
                <p id="ppP8">For any privacy-related questions, reach us at <a href="mailto:support@agricart.in">support@agricart.in</a> or call our helpline at 1800-419-8888.</p>
            </section>

            <div class="lg-cta">
                <div class="lg-cta-text">
                    <h3 id="ppCtaTitle">Have a question about your data?</h3>
                    <p id="ppCtaSub">Our support team typically replies within a few hours.</p>
                </div>
                <div class="lg-cta-actions">
                    <a href="mailto:support@agricart.in" class="primary"><i class="fa-solid fa-envelope"></i> <span id="ppCtaEmail">Email Us</span></a>
                    <a href="tel:18004198888" class="ghost"><i class="fa-solid fa-phone"></i> <span id="ppCtaCall">Call Helpline</span></a>
                </div>
            </div>

            <p class="lg-note" id="ppNote">This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.</p>
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

    var PP_I18N = {
        en: {
            ppHome: 'Home', ppCrumb: 'Privacy Policy',
            ppTitle: 'Privacy Policy',
            ppSubtitle: 'How AgriCart collects, uses, and protects your information across the marketplace, rentals, and advisory services.',
            ppUpdated: 'Last updated: July 2026', ppReadTime: '4 min read', ppPrint: 'Print / Save PDF',
            ppG1T: 'We never sell data', ppG1D: 'Your information is never sold to third parties.',
            ppG2T: 'Secure by default', ppG2D: 'Industry-standard safeguards protect your account.',
            ppG3T: "You're in control", ppG3D: 'Opt out of promotions anytime, keep order updates.',
            ppG4T: 'Delete on request', ppG4D: "Ask support and we'll remove your account data.",
            ppIntro: 'AgriCart ("we", "us", "our") operates this website and mobile experience to connect Indian farmers with buyers, equipment rentals, and agricultural services. This Privacy Policy explains what information we collect, how we use it, and the choices you have.',
            ppH1: 'Information We Collect',
            ppS1L1: 'Account details you provide — name, phone number, email address, and delivery address.',
            ppS1L2: 'Order, listing, and rental booking history on the platform.',
            ppS1L3: 'Location data (with your permission) to show nearby mandi prices, weather, and rental equipment.',
            ppS1L4: 'Device and usage information such as browser type and pages visited, used for improving the site.',
            ppH2: 'How We Use Your Information',
            ppS2L1: 'To process orders, rentals, and crop advisory requests.',
            ppS2L2: 'To send order updates, offers, and helpline communications.',
            ppS2L3: 'To personalize marketplace recommendations and Krishi Bazaar prices for your region.',
            ppS2L4: 'To improve platform security and prevent fraudulent activity.',
            ppH3: 'Sharing of Information',
            ppP3: 'We do not sell your personal information. We may share limited data with delivery partners, payment gateways, and equipment rental partners strictly to fulfil your orders and bookings, and with authorities where required by law.',
            ppH4: 'Data Security',
            ppP4: 'We use reasonable technical and organizational safeguards to protect your data. However, no method of transmission over the internet is completely secure, and we cannot guarantee absolute security.',
            ppH5: 'Your Choices',
            ppS5L1: 'You can update your profile information anytime from your account settings.',
            ppS5L2: 'You may opt out of promotional messages while continuing to receive essential order updates.',
            ppS5L3: 'You can request deletion of your account by contacting our support team.',
            ppH6: 'Cookies',
            ppP6: 'We use cookies and local storage to keep you signed in, remember your language preference, and understand how the site is used, so we can improve it over time.',
            ppH7: 'Changes to This Policy',
            ppP7: 'We may update this Privacy Policy from time to time. Significant changes will be communicated on this page with a revised "Last updated" date.',
            ppH8: 'Contact Us',
            ppCtaTitle: 'Have a question about your data?', ppCtaSub: 'Our support team typically replies within a few hours.',
            ppCtaEmail: 'Email Us', ppCtaCall: 'Call Helpline',
            ppNote: 'This is a general template provided for informational purposes and should be reviewed by a legal professional before being used as your official policy.'
        },
        hi: {
            ppHome: 'मुखपृष्ठ', ppCrumb: 'गोपनीयता नीति',
            ppTitle: 'गोपनीयता नीति',
            ppSubtitle: 'एग्रीकार्ट मार्केटप्लेस, रेंटल और सलाहकार सेवाओं में आपकी जानकारी कैसे एकत्र, उपयोग और सुरक्षित करता है।',
            ppUpdated: 'अंतिम अपडेट: जुलाई 2026', ppReadTime: '4 मिनट में पढ़ें', ppPrint: 'प्रिंट / पीडीएफ सेव करें',
            ppG1T: 'हम डेटा कभी नहीं बेचते', ppG1D: 'आपकी जानकारी कभी भी तीसरे पक्ष को नहीं बेची जाती।',
            ppG2T: 'सुरक्षा हमारी प्राथमिकता', ppG2D: 'उद्योग-मानक सुरक्षा उपाय आपके खाते की रक्षा करते हैं।',
            ppG3T: 'नियंत्रण आपके हाथ में', ppG3D: 'प्रचार संदेश कभी भी बंद करें, ऑर्डर अपडेट जारी रहेंगे।',
            ppG4T: 'अनुरोध पर डिलीट', ppG4D: 'सहायता टीम से संपर्क करें और हम आपका खाता डेटा हटा देंगे।',
            ppIntro: 'एग्रीकार्ट ("हम", "हमें", "हमारा") भारतीय किसानों को खरीदारों, उपकरण किराए और कृषि सेवाओं से जोड़ने के लिए यह वेबसाइट और मोबाइल अनुभव संचालित करता है। यह गोपनीयता नीति बताती है कि हम कौन सी जानकारी एकत्र करते हैं, उसका उपयोग कैसे करते हैं, और आपके पास क्या विकल्प हैं।',
            ppH1: 'हम जो जानकारी एकत्र करते हैं',
            ppS1L1: 'आपके द्वारा दी गई खाता जानकारी — नाम, फ़ोन नंबर, ईमेल पता और डिलीवरी पता।',
            ppS1L2: 'प्लेटफ़ॉर्म पर ऑर्डर, लिस्टिंग और रेंटल बुकिंग का इतिहास।',
            ppS1L3: 'आपकी अनुमति से, नज़दीकी मंडी भाव, मौसम और रेंटल उपकरण दिखाने के लिए स्थान डेटा।',
            ppS1L4: 'साइट सुधारने के लिए ब्राउज़र प्रकार और देखे गए पेज जैसी डिवाइस और उपयोग जानकारी।',
            ppH2: 'हम आपकी जानकारी का उपयोग कैसे करते हैं',
            ppS2L1: 'ऑर्डर, रेंटल और फसल सलाह अनुरोधों को प्रोसेस करने के लिए।',
            ppS2L2: 'ऑर्डर अपडेट, ऑफ़र और हेल्पलाइन संदेश भेजने के लिए।',
            ppS2L3: 'आपके क्षेत्र के लिए मार्केटप्लेस सुझाव और कृषि बाज़ार भाव व्यक्तिगत बनाने के लिए।',
            ppS2L4: 'प्लेटफ़ॉर्म की सुरक्षा बेहतर बनाने और धोखाधड़ी रोकने के लिए।',
            ppH3: 'जानकारी साझा करना',
            ppP3: 'हम आपकी व्यक्तिगत जानकारी नहीं बेचते। हम आपके ऑर्डर और बुकिंग पूरी करने के लिए डिलीवरी पार्टनर, पेमेंट गेटवे और रेंटल पार्टनर के साथ सीमित डेटा साझा कर सकते हैं, और कानून द्वारा आवश्यक होने पर अधिकारियों के साथ।',
            ppH4: 'डेटा सुरक्षा',
            ppP4: 'हम आपके डेटा की सुरक्षा के लिए उचित तकनीकी और संगठनात्मक सुरक्षा उपाय अपनाते हैं। हालांकि, इंटरनेट पर कोई भी तरीका पूरी तरह सुरक्षित नहीं होता, और हम पूर्ण सुरक्षा की गारंटी नहीं दे सकते।',
            ppH5: 'आपके विकल्प',
            ppS5L1: 'आप कभी भी अपनी खाता सेटिंग से प्रोफ़ाइल जानकारी अपडेट कर सकते हैं।',
            ppS5L2: 'आप प्रचार संदेशों से बाहर निकल सकते हैं, जरूरी ऑर्डर अपडेट मिलते रहेंगे।',
            ppS5L3: 'हमारी सहायता टीम से संपर्क करके आप अपने खाते को हटाने का अनुरोध कर सकते हैं।',
            ppH6: 'कुकीज़',
            ppP6: 'हम आपको साइन इन रखने, आपकी भाषा वरीयता याद रखने, और साइट के उपयोग को समझने के लिए कुकीज़ और लोकल स्टोरेज का उपयोग करते हैं, ताकि हम इसे समय के साथ बेहतर बना सकें।',
            ppH7: 'इस नीति में बदलाव',
            ppP7: 'हम समय-समय पर इस गोपनीयता नीति को अपडेट कर सकते हैं। महत्वपूर्ण बदलाव इस पेज पर संशोधित "अंतिम अपडेट" तारीख के साथ बताए जाएंगे।',
            ppH8: 'संपर्क करें',
            ppCtaTitle: 'अपने डेटा को लेकर कोई सवाल है?', ppCtaSub: 'हमारी सहायता टीम आमतौर पर कुछ घंटों में जवाब देती है।',
            ppCtaEmail: 'ईमेल करें', ppCtaCall: 'हेल्पलाइन कॉल करें',
            ppNote: 'यह एक सामान्य टेम्पलेट है जो केवल जानकारी के लिए दिया गया है, इसे आधिकारिक नीति के रूप में उपयोग करने से पहले किसी कानूनी विशेषज्ञ से समीक्षा करवाएं।'
        },
        mr: {
            ppHome: 'मुखपृष्ठ', ppCrumb: 'गोपनीयता धोरण',
            ppTitle: 'गोपनीयता धोरण',
            ppSubtitle: 'अ‍ॅग्रीकार्ट मार्केटप्लेस, भाड्याने आणि सल्ला सेवांमध्ये तुमची माहिती कशी गोळा, वापर आणि सुरक्षित करते.',
            ppUpdated: 'शेवटचे अद्ययावत: जुलै 2026', ppReadTime: '4 मिनिटांत वाचा', ppPrint: 'प्रिंट / पीडीएफ सेव्ह करा',
            ppG1T: 'आम्ही डेटा कधीही विकत नाही', ppG1D: 'तुमची माहिती कधीही तिसऱ्या पक्षाला विकली जात नाही.',
            ppG2T: 'सुरक्षितता प्रथम', ppG2D: 'उद्योग-मानक सुरक्षा उपाय तुमच्या खात्याचे संरक्षण करतात.',
            ppG3T: 'नियंत्रण तुमच्या हातात', ppG3D: 'जाहिराती कधीही बंद करा, ऑर्डर अपडेट्स सुरू राहतील.',
            ppG4T: 'विनंतीनुसार डिलीट', ppG4D: 'सहाय्य टीमशी संपर्क साधा आणि आम्ही तुमचा खाते डेटा काढून टाकू.',
            ppIntro: 'अ‍ॅग्रीकार्ट ("आम्ही", "आम्हाला", "आमचे") भारतीय शेतकऱ्यांना खरेदीदार, उपकरण भाडे आणि कृषी सेवांशी जोडण्यासाठी ही वेबसाइट आणि मोबाइल अनुभव चालवते. हे गोपनीयता धोरण आम्ही कोणती माहिती गोळा करतो, तिचा वापर कसा करतो आणि तुमच्याकडे कोणते पर्याय आहेत हे स्पष्ट करते.',
            ppH1: 'आम्ही गोळा करत असलेली माहिती',
            ppS1L1: 'तुम्ही दिलेली खाते माहिती — नाव, फोन नंबर, ईमेल पत्ता आणि डिलिव्हरी पत्ता.',
            ppS1L2: 'प्लॅटफॉर्मवरील ऑर्डर, लिस्टिंग आणि भाडे बुकिंगचा इतिहास.',
            ppS1L3: 'तुमच्या परवानगीने, जवळपासचे बाजारभाव, हवामान आणि भाडे उपकरणे दाखवण्यासाठी स्थान डेटा.',
            ppS1L4: 'साइट सुधारण्यासाठी ब्राउझर प्रकार आणि पाहिलेली पाने यासारखी डिव्हाइस व वापर माहिती.',
            ppH2: 'आम्ही तुमची माहिती कशी वापरतो',
            ppS2L1: 'ऑर्डर, भाडे आणि पीक सल्ला विनंत्या प्रक्रिया करण्यासाठी.',
            ppS2L2: 'ऑर्डर अपडेट्स, ऑफर्स आणि हेल्पलाइन संदेश पाठवण्यासाठी.',
            ppS2L3: 'तुमच्या भागासाठी मार्केटप्लेस शिफारसी आणि कृषी बाजार भाव वैयक्तिकृत करण्यासाठी.',
            ppS2L4: 'प्लॅटफॉर्म सुरक्षितता सुधारण्यासाठी आणि फसवणूक रोखण्यासाठी.',
            ppH3: 'माहितीची देवाणघेवाण',
            ppP3: 'आम्ही तुमची वैयक्तिक माहिती विकत नाही. तुमच्या ऑर्डर आणि बुकिंग पूर्ण करण्यासाठी आम्ही डिलिव्हरी पार्टनर, पेमेंट गेटवे आणि भाडे पार्टनरसोबत मर्यादित डेटा शेअर करू शकतो, तसेच कायद्याने आवश्यक असेल तेव्हा अधिकाऱ्यांसोबत.',
            ppH4: 'डेटा सुरक्षा',
            ppP4: 'तुमचा डेटा सुरक्षित ठेवण्यासाठी आम्ही योग्य तांत्रिक आणि संस्थात्मक सुरक्षा उपाय वापरतो. मात्र, इंटरनेटवरील कोणतीही पद्धत पूर्णपणे सुरक्षित नसते, आणि आम्ही पूर्ण सुरक्षिततेची हमी देऊ शकत नाही.',
            ppH5: 'तुमचे पर्याय',
            ppS5L1: 'तुम्ही कधीही तुमच्या खाते सेटिंग्जमधून प्रोफाइल माहिती अपडेट करू शकता.',
            ppS5L2: 'तुम्ही जाहिरात संदेशांमधून बाहेर पडू शकता, आवश्यक ऑर्डर अपडेट्स मिळत राहतील.',
            ppS5L3: 'आमच्या सहाय्य टीमशी संपर्क साधून तुम्ही खाते डिलीट करण्याची विनंती करू शकता.',
            ppH6: 'कुकीज',
            ppP6: 'तुम्हाला साइन इन ठेवण्यासाठी, तुमची भाषा प्राधान्ये लक्षात ठेवण्यासाठी आणि साइटचा वापर समजून घेण्यासाठी आम्ही कुकीज आणि लोकल स्टोरेज वापरतो, जेणेकरून आम्ही ती कालांतराने सुधारू शकू.',
            ppH7: 'या धोरणातील बदल',
            ppP7: 'आम्ही वेळोवेळी हे गोपनीयता धोरण अद्ययावत करू शकतो. महत्त्वाचे बदल या पानावर सुधारित "शेवटचे अद्ययावत" तारखेसह कळवले जातील.',
            ppH8: 'संपर्क साधा',
            ppCtaTitle: 'तुमच्या डेटाबद्दल काही प्रश्न आहे का?', ppCtaSub: 'आमची सहाय्य टीम साधारणपणे काही तासांत उत्तर देते.',
            ppCtaEmail: 'ईमेल करा', ppCtaCall: 'हेल्पलाइनला कॉल करा',
            ppNote: 'हा एक सर्वसाधारण टेम्पलेट आहे जो केवळ माहितीसाठी दिला आहे, अधिकृत धोरण म्हणून वापरण्यापूर्वी कायदेशीर तज्ज्ञाकडून पडताळणी करून घ्या.'
        }
    };

    function applyPPLang(lang) {
        var dict = PP_I18N[lang] || PP_I18N.en;
        Object.keys(dict).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = dict[id];
        });
    }

    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) { try { prevSwitchLanguage(lang); } catch (e) {} }
        applyPPLang(lang);
    };

    document.addEventListener('DOMContentLoaded', function () {
        try { applyPPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    });
    if (document.readyState !== 'loading') {
        try { applyPPLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
    }
})();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
