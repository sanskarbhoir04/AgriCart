<style>
footer {
    position: relative;
    border-top: 1px solid rgba(46,204,113,0.15);
}
footer::before {
    content: '';
    position: absolute; top: -1px; left: 0; right: 0; height: 2px;
    background: linear-gradient(90deg, transparent, #2ecc71, #4CAF50, #2ecc71, transparent);
}

.footer-logo { display: flex; align-items: center; gap: 9px; }
.footer-logo-img { height: 48px; width: 48px; object-fit: contain; display: block; border-radius: 50%; }
.footer-logo-txt { font-size: 22px; font-weight: 800; letter-spacing: -0.4px; }
.footer-logo-txt .agri { color: #FFFFFF; }
.footer-logo-txt .cart { color: #5A9802; margin-left: 1px; }

.footer-slogan {
    font-size: 14px; font-weight: 600; font-style: italic; color: #4CAF50;
    margin: 6px 0 4px;
}

.footer-container {
    max-width: 1540px; margin: 0 auto; padding: 50px 32px 0; box-sizing: border-box;
}

.footer-grid { position: relative; display: grid; grid-template-columns: 8fr 5fr 7fr 5fr; column-gap: 44px; }
.footer-grid .footer-col {
    padding: 6px 0; border-radius: 10px;
    transition: background 0.35s ease;
}
.footer-grid .footer-col:hover { background: rgba(46,204,113,0.04); }
.footer-grid .footer-col + .footer-col { border-left: 1px solid rgba(255,255,255,0.06); padding-left: 24px; }
.footer-grid .footer-col:first-child { border-left: 1px solid rgba(255,255,255,0.06); padding-left: 24px; }

.footer-quicklinks h4,
.footer-support h4,
.footer-feedback h4 {
    text-transform: uppercase; letter-spacing: 0.5px; font-size: 15px; font-weight: 700;
    color: #ffffff; display: block; position: relative; margin-bottom: 24px;
}
.footer-quicklinks h4 i { display: none; }
.footer-quicklinks h4::after,
.footer-support h4::after,
.footer-feedback h4::after {
    content: ''; display: block; width: 40px; height: 3px; background: #2ecc71;
    margin-top: 10px; border-radius: 2px;
    transition: width 0.35s ease;
}
.footer-col:hover > h4::after { width: 60px; }
.footer-quicklinks a,
.footer-support a,
.footer-feedback a {
    display: flex; align-items: center; gap: 12px;
    color: rgba(255,255,255,0.80) !important;
    font-size: 12.8px; font-weight: 600;
    margin: 0 0 15px;
    transition: color 0.25s ease, transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.footer-quicklinks a i,
.footer-support a i,
.footer-feedback a i {
    color: inherit; width: 26px; height: 26px; text-align: center; font-size: 13px;
    flex-shrink: 0; display: inline-flex; align-items: center; justify-content: center;
    background: rgba(255,255,255,0.06); border-radius: 7px;
    transition: background 0.25s ease, color 0.25s ease;
}
.footer-quicklinks a:hover,
.footer-support a:hover,
.footer-feedback a:hover { color: #388E3C !important; transform: translateX(4px); }
.footer-quicklinks a:hover i,
.footer-support a:hover i,
.footer-feedback a:hover i { background: rgba(56,142,60,0.16); }
.footer-quicklinks a.active,
.footer-support a.active,
.footer-feedback a.active { color: #ffffff !important; }
.footer-quicklinks a.active i,
.footer-support a.active i,
.footer-feedback a.active i { background: #2ecc71; color: #0b1a14; }
.footer-support a span,
.footer-feedback a span { word-break: break-word; }
.footer-feedback textarea {
    width: 100%; min-height: 70px; resize: vertical;
    background: rgba(255,255,255,0.06); border: 1px solid rgba(255,255,255,0.12);
    border-radius: 8px; color: #ffffff; font-size: 12.8px; padding: 10px;
    margin-bottom: 10px; font-family: inherit;
}
.footer-feedback textarea::placeholder { color: rgba(255,255,255,0.45); }
.footer-feedback .ft-fb-submit {
    display: inline-flex; align-items: center; gap: 8px;
    background: #2ecc71; color: #0b1a14; font-weight: 700; font-size: 12.8px;
    border: none; border-radius: 7px; padding: 9px 16px; cursor: pointer;
    transition: background 0.25s ease, transform 0.2s ease;
}
.footer-feedback .ft-fb-submit:hover { background: #4CAF50; transform: translateY(-2px); }
.footer-feedback .ft-fb-note {
    font-size: 11.5px; color: rgba(255,255,255,0.55); margin-top: 8px; display: none;
}
.footer-feedback .ft-fb-note.show { display: block; }
.ft-fb-rating {
    display: flex; align-items: center; gap: 10px; margin-bottom: 10px;
}
.ft-fb-stars {
    display: flex; gap: 4px; font-size: 15px;
}
.ft-fb-stars i {
    color: rgba(255,255,255,0.25); cursor: pointer; transition: color 0.15s ease, transform 0.15s ease;
    position: relative;
}
.ft-fb-stars i:hover,
.ft-fb-stars i.filled { color: #f5c518; transform: scale(1.1); }
.ft-fb-stars i.half { transform: scale(1.1); }
.ft-fb-stars i.half::after {
    content: "\f005"; font-family: "Font Awesome 6 Free"; font-weight: 900;
    position: absolute; top: 0; left: 0; width: 50%; overflow: hidden;
    color: #f5c518; pointer-events: none;
}
.ft-fb-clear {
    display: none; align-items: center; justify-content: center;
    width: 20px; height: 20px; border-radius: 50%; border: none; cursor: pointer;
    background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); font-size: 10px;
    transition: background 0.15s ease, color 0.15s ease;
}
.ft-fb-clear:hover { background: rgba(255,255,255,0.2); color: #ffffff; }
.ft-fb-clear.show { display: inline-flex; }

.footer-newsletter {
    display: flex; margin-top: 10px; max-width: 280px;
    border: 1px solid rgba(255,255,255,0.15); border-radius: 7px; overflow: hidden;
}
.footer-newsletter input {
    flex: 1; min-width: 0; background: rgba(255,255,255,0.05); border: none;
    color: #ffffff; font-size: 12.5px; padding: 9px 10px; outline: none;
}
.footer-newsletter input::placeholder { color: rgba(255,255,255,0.4); }
.footer-newsletter button {
    background: #2ecc71; color: #0b1a14; border: none; padding: 0 14px;
    cursor: pointer; transition: background 0.25s ease;
}
.footer-newsletter button:hover { background: #4CAF50; }
.ft-nl-note {
    font-size: 11px; color: rgba(255,255,255,0.55); margin-top: 6px; display: none;
}
.ft-nl-note.show { display: block; }

.footer-trust {
    display: flex; gap: 20px; align-items: center; justify-content: flex-end;
    font-size: 28px; line-height: 1; color: rgba(255,255,255,0.45); margin: 0;
}
.footer-trust i {
    display: inline-flex; align-items: center; justify-content: center;
    width: 28px; height: 28px;
    transition: color 0.25s ease;
}
.footer-trust i:hover { color: #2ecc71; }

.footer-socials { gap: 16px; }
.footer-socials a {
    display: inline-flex; align-items: center; justify-content: center;
    font-size: 30px; line-height: 1;
    color: #7a9b7a;
    transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.footer-socials a svg { width: 0.86em; height: 0.86em; fill: currentColor; display: block; }
.footer-socials a:hover { transform: translateY(-4px) scale(1.12); }
.footer-socials a:nth-child(1):hover { color: #1877F2; }
.footer-socials a:nth-child(2):hover { color: #ffffff; }
.footer-socials a:nth-child(3):hover { color: #E1306C; }
.footer-socials a:nth-child(4):hover { color: #FF0000; }
.footer-socials a.wa:hover { color: #25D366; }

.footer-copy {
    position: relative;
}

.footer-socials-mini { gap: 10px; margin-top: 14px; }
.footer-socials-mini a { font-size: 18px; }

.footer-row-2 {
    display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 40px;
    padding: 22px 0; margin-top: 40px;
    border-top: 1px solid rgba(255,255,255,0.08);
}
.footer-row-2-group { display: flex; align-items: center; gap: 8px; flex-wrap: nowrap; }
.footer-row-2-links { display: flex; flex-direction: column; align-items: center; gap: 6px; font-size: 12px; color: rgba(255,255,255,0.5); }
.footer-row-2-links-row { display: flex; align-items: center; }
.footer-row-2-links a { color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.2s ease; }
.footer-row-2-links a:hover { color: #2ecc71; }
.footer-row-2-links .sep { margin: 0 8px; opacity: 0.35; }
.footer-row-2-label { font-size: 12.5px; font-weight: 600; line-height: 1; color: rgba(255,255,255,0.5); white-space: nowrap; }
.footer-row-2 .footer-socials { gap: 14px; }
.footer-row-2 .footer-socials a { font-size: 23px; position: relative; top: 9px; }
.footer-row-2 .footer-trust { font-size: 18px; gap: 14px; }
.footer-row-2 .footer-trust i { width: 20px; height: 20px; }

.footer-bottom-links {
    display: flex; align-items: center; justify-content: flex-start; flex-wrap: wrap; gap: 10px;
    padding: 18px 0;
    border-top: 1px solid rgba(255,255,255,0.08);
    font-size: 12px; color: rgba(255,255,255,0.5);
}
.footer-bottom-links a { color: rgba(255,255,255,0.6); text-decoration: none; transition: color 0.2s ease; }
.footer-bottom-links a:hover { color: #2ecc71; }
.footer-bottom-links .sep { margin: 0 8px; opacity: 0.35; }
</style>


<footer>
    <div class="footer-container">
    <div class="footer-grid">
        <div class="footer-col">
            <div class="footer-logo"><img src="<?php echo $base_path; ?>/assets/images/agricart-logo.png" alt="AgriCart" class="footer-logo-img"><span class="footer-logo-txt"><span class="agri">Agri</span><span class="cart">Cart</span></span></div>
            <div class="footer-slogan" id="ft-slogan">&ldquo;Growing Together, Harvesting Success&rdquo;</div>
            <div class="footer-tagline" id="ft-tag">Empowering Indian Farmers Through Technology</div>
            <h4 id="ft-about-heading">About Us</h4>
            <p id="ft-about">AgriCart connects Indian farmers directly with buyers, offering fair prices, easy access to farming tools, and a trusted platform to grow their business.</p>
            <form id="ft-nl-form" class="footer-newsletter">
                <input type="email" id="ft-nl-input" name="email" placeholder="Tumcha email" required>
                <button type="submit" aria-label="Subscribe"><i class="fa-solid fa-paper-plane"></i></button>
            </form>
            <div class="ft-nl-note" id="ft-nl-note"></div>
        </div>
        <div class="footer-col footer-quicklinks">
            <h4 id="ft-ql-heading"><i class="fa-solid fa-link"></i> <span>Quick Links</span></h4>
            <a href="<?php echo $base_path; ?>/pages/marketplace.php" class="<?php echo ($current_page == 'marketplace.php') ? 'active' : ''; ?>"><i class="fa-solid fa-cart-shopping"></i><span id="ft-ql-store">Agri Store</span></a>
            <a href="<?php echo $base_path; ?>/pages/rental.php" class="<?php echo ($current_page == 'rental.php') ? 'active' : ''; ?>"><i class="fa-solid fa-tractor"></i><span id="ft-ql-rental">Rental Hub</span></a>
            <a href="<?php echo $base_path; ?>/pages/advisory.php" class="<?php echo ($current_page == 'advisory.php') ? 'active' : ''; ?>"><i class="fa-solid fa-seedling"></i><span id="ft-ql-advisory">Crop Advisory</span></a>
            <a href="<?php echo $base_path; ?>/pages/krishi_bazaar.php" class="<?php echo ($current_page == 'krishi_bazaar.php') ? 'active' : ''; ?>"><i class="fa-solid fa-chart-line"></i><span id="ft-ql-bazaar">Krishi Bazaar</span></a>
            <a href="<?php echo $base_path; ?>/pages/agri-connect.php" class="<?php echo ($current_page == 'agri-connect.php') ? 'active' : ''; ?>"><i class="fa-solid fa-users"></i><span id="ft-ql-connect">Agri Connect</span></a>
        </div>
        <div class="footer-col footer-support">
            <h4 id="ft-sup-heading"><span>Contact Info</span></h4>
            <a href="tel:18004198888"><i class="fa-solid fa-phone"></i><span>1800-419-8888</span></a>
            <a href="https://www.google.com/maps/search/?api=1&query=Palghar,+Maharashtra,+India" target="_blank"><i class="fa-solid fa-location-dot"></i><span id="ft-sup-address">Palghar, Maharashtra, India</span></a>
            <a href="#" style="cursor:default;"><i class="fa-solid fa-clock"></i><span id="ft-sup-hours">Mon - Sat: 9:00 AM - 6:00 PM</span></a>
        </div>
        <div class="footer-col footer-feedback">
            <h4 id="ft-fb-heading"><i class="fa-solid fa-comment-dots"></i> <span>Feedback</span></h4>
            <form id="ft-fb-form" data-logged-in="<?php echo !empty($_SESSION['user_id']) ? '1' : '0'; ?>">
                <div class="ft-fb-rating">
                    <div class="ft-fb-stars" id="ft-fb-stars" data-rating="0">
                        <i class="fa-solid fa-star" data-star="1"></i><i class="fa-solid fa-star" data-star="2"></i><i class="fa-solid fa-star" data-star="3"></i><i class="fa-solid fa-star" data-star="4"></i><i class="fa-solid fa-star" data-star="5"></i>
                    </div>
                    <button type="button" class="ft-fb-clear" id="ft-fb-clear" title="Clear rating" aria-label="Clear rating"><i class="fa-solid fa-xmark"></i></button>
                </div>
                <textarea id="ft-fb-textarea" name="feedback" placeholder="Tumcha feedback ithe lihaa..." required></textarea>
                <button type="submit" class="ft-fb-submit"><i class="fa-solid fa-paper-plane"></i><span id="ft-fb-submit-label">Submit</span></button>
                <div class="ft-fb-note" id="ft-fb-note"></div>
            </form>
        </div>
    </div>

    <div class="footer-row-2">
        <div class="footer-row-2-group">
            <span class="footer-row-2-label" id="ft-follow-label">Follow Us:</span>
            <div class="footer-socials">
                <a href="https://www.facebook.com/share/1D9uUkje98/" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-facebook"></i></a>
                <a href="https://x.com/agricartind" target="_blank" rel="noopener noreferrer" aria-label="X (Twitter)"><svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg"><path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/></svg></a>
                <a href="https://www.instagram.com/agricart.in?igsh=MTNzMmthNG14MGJ3Mw==" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-instagram"></i></a>
                <a href="https://www.youtube.com/@AgriCartOfficial" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-youtube"></i></a>
                <a href="https://wa.me/919999999999" target="_blank" class="wa"><i class="fa-brands fa-whatsapp"></i></a>
            </div>
        </div>
        <div class="footer-row-2-group footer-row-2-links">
            <div class="footer-row-2-links-row">
                <a href="<?php echo $base_path; ?>/pages/privacy-policy.php" id="ft-sup-privacy">Privacy Policy</a>
                <span class="sep">|</span>
                <a href="<?php echo $base_path; ?>/pages/terms-conditions.php" id="ft-sup-terms">Terms &amp; Conditions</a>
                <span class="sep">|</span>
                <a href="<?php echo $base_path; ?>/pages/sitemap.php" id="ft-sitemap">Sitemap</a>
            </div>
            <span id="ft-copy">© 2026 AgriCart. All Rights Reserved.</span>
        </div>
        <div class="footer-row-2-group">
            <span class="footer-row-2-label" id="ft-secure-label">Secure Payments:</span>
            <div class="footer-trust" aria-label="Secure payment options">
                <i class="fa-brands fa-google-pay" title="Google Pay"></i>
                <i class="fa-solid fa-qrcode" title="UPI"></i>
                <i class="fa-brands fa-cc-visa" title="Visa"></i>
                <i class="fa-brands fa-cc-mastercard" title="Mastercard"></i>
                <i class="fa-solid fa-shield-halved" title="Secure Payments"></i>
            </div>
        </div>
    </div>
    </div>
</footer>

<script>
(function () {
    "use strict";
    try { document.documentElement.classList.add('agri-anim-ready'); } catch (e) {}

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {
        try { initScrollReveal(); } catch (e) {}
        try { initRipples(); } catch (e) {}
        try { initCounters(); } catch (e) {}
        try { initFeedbackForm(); } catch (e) {}
        try { initNewsletterForm(); } catch (e) {}
    });

    var FOOTER_I18N = {
        en: {
            slogan: '“Growing Together, Harvesting Success”',
            tag: 'Empowering Indian Farmers Through Technology',
            aboutHeading: 'About Us',
            about: 'AgriCart connects Indian farmers directly with buyers, offering fair prices, easy access to farming tools, and a trusted platform to grow their business.',
            copy: '© 2026 AgriCart. All Rights Reserved.',
            qlHeading: 'Quick Links',
            qlHome: 'Home',
            qlAbout: 'About Us',
            qlStore: 'Agri Store',
            qlRental: 'Rental Hub',
            qlAdvisory: 'Crop Advisory',
            qlBazaar: 'Krishi Bazaar',
            qlConnect: 'Agri Connect',
            qlContact: 'Contact Us',
            supHeading: 'Contact Info',
            supAddress: 'Palghar, Maharashtra, India',
            supHours: 'Mon - Sat: 9:00 AM - 6:00 PM',
            followUs: 'Follow Us:',
            securePayments: 'Secure Payments:',
            supPrivacy: 'Privacy Policy',
            supTerms: 'Terms & Conditions',
            sitemap: 'Sitemap',
            fbHeading: 'Feedback',
            fbPlaceholder: 'Write your feedback here...',
            fbSubmit: 'Submit',
            fbThanks: 'Thank you! Your feedback has been submitted.',
            fbLoginRequired: 'Please login to submit feedback.',
            nlPlaceholder: 'Your email',
            nlThanks: 'Subscribed! You will receive updates soon.'
        },
        hi: {
            slogan: '“साथ मिलकर बढ़ें, सफलता पाएं”',
            tag: 'तकनीक के ज़रिए भारतीय किसानों को सशक्त बनाना',
            aboutHeading: 'हमारे बारे में',
            about: 'एग्रीकार्ट भारतीय किसानों को सीधे खरीदारों से जोड़ता है, उचित मूल्य, कृषि उपकरणों तक आसान पहुँच और अपने व्यवसाय को बढ़ाने के लिए एक भरोसेमंद मंच प्रदान करता है।',
            copy: '© 2026 एग्रीकार्ट। सर्वाधिकार सुरक्षित।',
            qlHeading: 'त्वरित लिंक',
            qlHome: 'होम',
            qlAbout: 'हमारे बारे में',
            qlStore: 'एग्री स्टोर',
            qlRental: 'किराया केंद्र',
            qlAdvisory: 'फसल सलाह',
            qlBazaar: 'कृषि बाज़ार',
            qlConnect: 'एग्री कनेक्ट',
            qlContact: 'संपर्क करें',
            supHeading: 'संपर्क जानकारी',
            supAddress: 'पालघर, महाराष्ट्र, भारत',
            supHours: 'सोम - शनि: सुबह 9:00 - शाम 6:00',
            followUs: 'हमें फॉलो करें:',
            securePayments: 'सुरक्षित भुगतान:',
            supPrivacy: 'गोपनीयता नीति',
            supTerms: 'नियम और शर्तें',
            sitemap: 'साइटमैप',
            fbHeading: 'फीडबैक',
            fbPlaceholder: 'अपना फीडबैक यहाँ लिखें...',
            fbSubmit: 'सबमिट करें',
            fbThanks: 'धन्यवाद! आपका फीडबैक सबमिट हो गया है।',
            fbLoginRequired: 'फीडबैक सबमिट करने के लिए कृपया लॉगिन करें।',
            nlPlaceholder: 'आपका ईमेल',
            nlThanks: 'सब्सक्राइब हो गया! जल्द ही अपडेट मिलेंगे।'
        },
        mr: {
            slogan: '“एकत्र वाढूया, यश मिळवूया”',
            tag: 'तंत्रज्ञानाच्या माध्यमातून भारतीय शेतकऱ्यांचे सक्षमीकरण',
            aboutHeading: 'आमच्याबद्दल',
            about: 'अ‍ॅग्रीकार्ट भारतीय शेतकऱ्यांना थेट खरेदीदारांशी जोडते, योग्य दर, शेती उपकरणांपर्यंत सोपी पोहोच आणि त्यांचा व्यवसाय वाढवण्यासाठी विश्वासार्ह व्यासपीठ उपलब्ध करून देते.',
            copy: '© 2026 अ‍ॅग्रीकार्ट. सर्व हक्क राखीव.',
            qlHeading: 'क्विक लिंक्स',
            qlHome: 'मुखपृष्ठ',
            qlAbout: 'आमच्याबद्दल',
            qlStore: 'कृषी स्टोअर',
            qlRental: 'अवजारे केंद्र',
            qlAdvisory: 'पीक सल्ला',
            qlBazaar: 'कृषी बाजार',
            qlConnect: 'कृषी कनेक्ट',
            qlContact: 'संपर्क',
            supHeading: 'संपर्क माहिती',
            supAddress: 'पालघर, महाराष्ट्र, भारत',
            supHours: 'सोम - शनि: सकाळी 9:00 - संध्याकाळी 6:00',
            followUs: 'आम्हाला फॉलो करा:',
            securePayments: 'सुरक्षित पेमेंट्स:',
            supPrivacy: 'गोपनीयता धोरण',
            supTerms: 'अटी व शर्ती',
            sitemap: 'साइटमॅप',
            fbHeading: 'अभिप्राय',
            fbPlaceholder: 'तुमचा अभिप्राय इथे लिहा...',
            fbSubmit: 'सबमिट करा',
            fbThanks: 'धन्यवाद! तुमचा अभिप्राय सबमिट झाला आहे.',
            fbLoginRequired: 'अभिप्राय सबमिट करण्यासाठी कृपया आधी लॉगिन करा.',
            nlPlaceholder: 'तुमचा ईमेल',
            nlThanks: 'सबस्क्राइब झाले! लवकरच अपडेट्स मिळतील.'
        }
    };

    function applyFooterLang(lang) {
        var dict = FOOTER_I18N[lang] || FOOTER_I18N.en;
        var map = {
            'ft-slogan': dict.slogan,
            'ft-tag': dict.tag,
            'ft-about-heading': dict.aboutHeading,
            'ft-about': dict.about,
            'ft-copy': dict.copy,
            'ft-ql-home': dict.qlHome,
            'ft-ql-about': dict.qlAbout,
            'ft-ql-store': dict.qlStore,
            'ft-ql-rental': dict.qlRental,
            'ft-ql-advisory': dict.qlAdvisory,
            'ft-ql-bazaar': dict.qlBazaar,
            'ft-ql-connect': dict.qlConnect,
            'ft-ql-contact': dict.qlContact,
            'ft-sup-contact': dict.qlContact,
            'ft-sup-address': dict.supAddress,
            'ft-sup-hours': dict.supHours,
            'ft-follow-label': dict.followUs,
            'ft-secure-label': dict.securePayments,
            'ft-sup-privacy': dict.supPrivacy,
            'ft-sup-terms': dict.supTerms,
            'ft-sitemap': dict.sitemap,
            'ft-fb-submit-label': dict.fbSubmit
        };
        Object.keys(map).forEach(function (id) {
            var el = document.getElementById(id);
            if (el) el.textContent = map[id];
        });
        var fbTextarea = document.getElementById('ft-fb-textarea');
        if (fbTextarea) fbTextarea.setAttribute('placeholder', dict.fbPlaceholder);
        var nlInput = document.getElementById('ft-nl-input');
        if (nlInput) nlInput.setAttribute('placeholder', dict.nlPlaceholder);
        var headingEl = document.getElementById('ft-ql-heading');
        if (headingEl) {
            var span = headingEl.querySelector('span');
            if (span) span.textContent = dict.qlHeading;
        }
        var supHeadingEl = document.getElementById('ft-sup-heading');
        if (supHeadingEl) {
            var supSpan = supHeadingEl.querySelector('span');
            if (supSpan) supSpan.textContent = dict.supHeading;
        }
        var fbHeadingEl = document.getElementById('ft-fb-heading');
        if (fbHeadingEl) {
            var fbSpan = fbHeadingEl.querySelector('span');
            if (fbSpan) fbSpan.textContent = dict.fbHeading;
        }
    }

    // Chain into the header's switchLanguage() function (defined in
    // includes/header.php, which always loads before this footer partial),
    // so switching the language from the header selector also updates the
    // footer — without touching pageLanguageCallback, whose definition order
    // relative to this footer varies from page to page.
    var prevSwitchLanguage = (typeof window.switchLanguage === 'function') ? window.switchLanguage : null;
    window.switchLanguage = function (lang) {
        if (prevSwitchLanguage) {
            try { prevSwitchLanguage(lang); } catch (e) {}
        }
        applyFooterLang(lang);
    };

    // Apply the saved language (agri_lang, same key used by header.php) as
    // soon as the footer markup is in the DOM.
    ready(function () {
        try { applyFooterLang(localStorage.getItem('agri_lang') || 'en'); } catch (e) {}
        try { markActiveQuickLink(); } catch (e) {}
    });

    // Fallback active-link detector: works purely off the current URL, so
    // the "Crop Advisory" (or any) quick link still highlights correctly
    // even if the PHP $current_page check on the server didn't apply the
    // 'active' class for some reason (caching, include path, etc.).
    function markActiveQuickLink() {
        var links = document.querySelectorAll('.footer-quicklinks a, .footer-support a[href*=".php"]');
        if (!links.length) return;
        var currentFile = (window.location.pathname.split('/').pop() || '').toLowerCase();
        if (!currentFile || currentFile === '' ) currentFile = 'index.php';
        links.forEach(function (link) {
            var linkFile = (link.getAttribute('href') || '').split('/').pop().toLowerCase();
            if (linkFile && linkFile === currentFile) {
                link.classList.add('active');
            }
        });
    }

    function initScrollReveal() {
        if (!('IntersectionObserver' in window)) return;
        var selector = [
            '.product-card', '.gallery-card', '.widget-card', '.test-card',
            '.scheme-item', '.stat-item', '.cat-item', '.sidebar',
            '.offer-strip', '.section-label', '.section-title', '.section-sub',
            '[class*="-card"]', '[class*="-item"]', '[class*="-box"]',
            '[class*="-panel"]', '[class*="-tile"]', '.footer-col'
        ].join(',');

        var seen = new Set();
        var nodes = [];
        document.querySelectorAll(selector).forEach(function (el) {
            if (el.closest('#main-header, #flash-bar, .nav-menu, .cart-drawer, .profile-modal, .auth-modal, .cart-overlay')) return;
            if (seen.has(el)) return;
            seen.add(el);
            nodes.push(el);
        });

        var groups = new Map();
        nodes.forEach(function (el) {
            var p = el.parentElement || document.body;
            if (!groups.has(p)) groups.set(p, []);
            groups.get(p).push(el);
        });
        groups.forEach(function (siblings) {
            siblings.forEach(function (el, i) {
                el.classList.add('agri-reveal');
                el.style.transitionDelay = Math.min(i * 70, 420) + 'ms';
            });
        });

        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('agri-in');
                    obs.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

        nodes.forEach(function (el) { observer.observe(el); });

        setTimeout(function () {
            document.querySelectorAll('.agri-reveal:not(.agri-in)').forEach(function (el) {
                el.classList.add('agri-in');
            });
        }, 4000);
    }

    function initRipples() {
        document.addEventListener('click', function (e) {
            var target = e.target.closest('button, .btn, [class*="btn"], a.gallery-btn, .save-btn, .checkout-btn, .add-btn');
            if (!target || target.disabled) return;
            var rect = target.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'agri-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            if (getComputedStyle(target).position === 'static') target.style.position = 'relative';
            target.classList.add('agri-ripple-wrap');
            target.appendChild(ripple);
            setTimeout(function () { if (ripple.parentNode) ripple.parentNode.removeChild(ripple); }, 650);
        }, true);

        document.addEventListener('click', function (e) {
            var target = e.target.closest('.footer-socials a');
            if (!target) return;
            var rect = target.getBoundingClientRect();
            var size = Math.max(rect.width, rect.height);
            var ripple = document.createElement('span');
            ripple.className = 'agri-ripple';
            ripple.style.width = ripple.style.height = size + 'px';
            ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
            ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
            if (getComputedStyle(target).position === 'static') target.style.position = 'relative';
            target.classList.add('agri-ripple-wrap');
            target.appendChild(ripple);
            setTimeout(function () { if (ripple.parentNode) ripple.parentNode.removeChild(ripple); }, 650);
        }, true);
    }

    function initCounters() {
        if (!('IntersectionObserver' in window)) return;
        var els = document.querySelectorAll('.stat-item h3, .stats h3');
        if (!els.length) return;
        var observer = new IntersectionObserver(function (entries, obs) {
            entries.forEach(function (entry) {
                if (!entry.isIntersecting) return;
                animateCount(entry.target);
                obs.unobserve(entry.target);
            });
        }, { threshold: 0.4 });
        els.forEach(function (el) {
            var raw = el.textContent.trim();
            var match = raw.match(/^([\d,]+)(.*)$/);
            if (!match) return;
            el.classList.add('agri-counted');
            el.setAttribute('data-agri-target', match[1].replace(/,/g, ''));
            el.setAttribute('data-agri-suffix', match[2] || '');
            el.textContent = '0' + (match[2] || '');
            observer.observe(el);
        });
    }

    function animateCount(el) {
        var target = parseInt(el.getAttribute('data-agri-target'), 10);
        var suffix = el.getAttribute('data-agri-suffix') || '';
        if (isNaN(target)) return;
        var duration = 1200, start = null;
        function step(ts) {
            if (start === null) start = ts;
            var progress = Math.min((ts - start) / duration, 1);
            var eased = 1 - Math.pow(1 - progress, 3);
            el.textContent = Math.floor(eased * target).toLocaleString('en-IN') + suffix;
            if (progress < 1) window.requestAnimationFrame(step);
            else el.textContent = target.toLocaleString('en-IN') + suffix;
        }
        window.requestAnimationFrame(step);
    }
    function initFeedbackForm() {
        var form = document.getElementById('ft-fb-form');
        if (!form) return;
        var textarea = document.getElementById('ft-fb-textarea');
        var note = document.getElementById('ft-fb-note');
        var submitBtn = form.querySelector('.ft-fb-submit');
        var starsWrap = document.getElementById('ft-fb-stars');
        var clearBtn = document.getElementById('ft-fb-clear');
        var stars = starsWrap ? starsWrap.querySelectorAll('i[data-star]') : [];

        // Paints the stars for a given value (supports .5 increments) without
        // touching the stored rating — used for both the live selection and
        // the temporary hover/touch preview.
        function paintStars(val) {
            stars.forEach(function (s) {
                var idx = parseInt(s.getAttribute('data-star'), 10);
                s.classList.remove('filled', 'half');
                if (val >= idx) {
                    s.classList.add('filled');
                } else if (val >= idx - 0.5) {
                    s.classList.add('half');
                }
            });
        }

        function updateClearBtn(val) {
            if (clearBtn) clearBtn.classList.toggle('show', val > 0);
        }

        function setRating(val) {
            val = Math.max(0, Math.min(5, Math.round(val * 2) / 2));
            starsWrap.setAttribute('data-rating', val);
            paintStars(val);
            updateClearBtn(val);
        }

        // Works out whether the pointer is over the left half (→ half star)
        // or right half (→ full star) of a given star icon.
        function valueFromEvent(e, starEl) {
            var idx = parseInt(starEl.getAttribute('data-star'), 10);
            var rect = starEl.getBoundingClientRect();
            var point = (e.touches && e.touches[0]) ? e.touches[0] : e;
            var offsetX = point.clientX - rect.left;
            return offsetX < rect.width / 2 ? idx - 0.5 : idx;
        }

        stars.forEach(function (s) {
            s.addEventListener('mousemove', function (e) { paintStars(valueFromEvent(e, s)); });
            s.addEventListener('mouseleave', function () {
                paintStars(parseFloat(starsWrap.getAttribute('data-rating')) || 0);
            });
            s.addEventListener('click', function (e) {
                var val = valueFromEvent(e, s);
                var current = parseFloat(starsWrap.getAttribute('data-rating')) || 0;
                // Clicking the already-selected value again clears the rating.
                setRating(val === current ? 0 : val);
            });
            s.addEventListener('touchstart', function (e) {
                e.preventDefault();
                var val = valueFromEvent(e, s);
                var current = parseFloat(starsWrap.getAttribute('data-rating')) || 0;
                setRating(val === current ? 0 : val);
            }, { passive: false });
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () { setRating(0); });
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var message = (textarea.value || '').trim();
            if (!message) return;

            if (form.getAttribute('data-logged-in') !== '1') {
                showFeedbackNote(true);
                return;
            }

            var rating = starsWrap ? starsWrap.getAttribute('data-rating') : '0';

            if (submitBtn) submitBtn.disabled = true;

            fetch('<?php echo $base_path; ?>/ajax/submit-feedback.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'feedback=' + encodeURIComponent(message) + '&rating=' + encodeURIComponent(rating) + '&page=' + encodeURIComponent(window.location.pathname)
            })
            .then(function () { showFeedbackNote(false); })
            .catch(function () { showFeedbackNote(false); })
            .finally(function () {
                if (submitBtn) submitBtn.disabled = false;
                textarea.value = '';
                if (starsWrap) setRating(0);
            });
        });

        function showFeedbackNote(loginRequired) {
            if (!note) return;
            var lang = (function () { try { return localStorage.getItem('agri_lang') || 'en'; } catch (e) { return 'en'; } })();
            var dict = FOOTER_I18N[lang] || FOOTER_I18N.en;
            note.textContent = loginRequired ? dict.fbLoginRequired : dict.fbThanks;
            note.classList.add('show');
            setTimeout(function () { note.classList.remove('show'); }, 4000);
        }
    }

    function initNewsletterForm() {
        var form = document.getElementById('ft-nl-form');
        if (!form) return;
        var input = document.getElementById('ft-nl-input');
        var note = document.getElementById('ft-nl-note');
        var btn = form.querySelector('button');

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var email = (input.value || '').trim();
            if (!email) return;

            if (btn) btn.disabled = true;

            fetch('<?php echo $base_path; ?>/ajax/subscribe-newsletter.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(function () { showNewsletterNote(); })
            .catch(function () { showNewsletterNote(); })
            .finally(function () {
                if (btn) btn.disabled = false;
                input.value = '';
            });
        });

        function showNewsletterNote() {
            if (!note) return;
            var lang = (function () { try { return localStorage.getItem('agri_lang') || 'en'; } catch (e) { return 'en'; } })();
            var dict = FOOTER_I18N[lang] || FOOTER_I18N.en;
            note.textContent = dict.nlThanks;
            note.classList.add('show');
            setTimeout(function () { note.classList.remove('show'); }, 4000);
        }
    }

})();
</script>
</body>
</html>