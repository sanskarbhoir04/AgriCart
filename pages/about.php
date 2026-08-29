<?php
require_once __DIR__ . '/../includes/security.php';
// =====================================================
// AgriCart — About Us Page
// XAMPP: C:\xampp\htdocs\AgriCart\pages\about.php
// =====================================================
agri_session_start();
include __DIR__ . '/../includes/header.php';

// ── Real Districts Served — same real district-coverage figure used on the Krishi Bazaar page ──
$stat_districts_served = 36;
?>

<!-- ================================================
     ABOUT PAGE — MAIN CONTENT
     header.php आणि footer.php च्या मध्ये येतो
     ================================================ -->

<link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;500;600;700&display=swap" rel="stylesheet">

<style>
/* ====================================================
   AgriCart About Page CSS
   Prefix: .abt- — existing CSS शी conflict नाही
   ==================================================== */

.abt-wrap { background:#F8FAF7; font-family:'Poppins','Noto Sans Devanagari',sans-serif; }


/* ── Stats bar: uses the shared global .stats / .stat-item classes (same structure as every other page) ── */

/* ── Image slider (matches homepage slider style) ─── */
.abt-hero-slider{ position:relative; height:560px; overflow:hidden; }
.abt-hslide{
    position:absolute; inset:0; opacity:0; z-index:1;
    background-size:cover; background-position:center;
    display:flex; align-items:center; justify-content:center;
    transition: opacity 1.2s cubic-bezier(0.65,0,0.35,1), transform 6s ease-out;
    transform: scale(1.06);
    cursor:pointer;
    pointer-events:none;
}
.abt-hslide.abt-active{ opacity:1; z-index:2; transform:scale(1); pointer-events:auto; }
.abt-hslide-overlay{
    position:absolute; inset:0;
    background:linear-gradient(to bottom, rgba(0,0,0,0.28) 0%, rgba(5,20,5,0.58) 100%);
}
.abt-hslide-content{
    position:relative; z-index:1; max-width:600px; padding:0 1.5rem; color:#fff;
    text-align:center; margin:0 auto;
    opacity:0; transform:translateY(18px);
    transition: opacity .7s ease .2s, transform .7s ease .2s;
}
.abt-hslide.abt-active .abt-hslide-content{ opacity:1; transform:translateY(0); }
.abt-hslide-tag{
    display:inline-block; background:rgba(76,175,80,0.2); color:#90e070;
    border:1px solid rgba(76,175,80,0.35); font-size:11px; letter-spacing:1.5px;
    padding:4px 14px; border-radius:16px; margin-bottom:14px; text-transform:uppercase; font-weight:700;
}
.abt-hslide-content h2{ font-size:clamp(1.5rem,3.4vw,2.2rem); font-weight:700; line-height:1.25; margin-bottom:10px; }
.abt-hslide-content p{ font-size:0.95rem; color:rgba(255,255,255,0.75); line-height:1.6; margin:0 auto 18px; max-width:460px; }
.abt-hslide-cta{
    display:inline-flex; align-items:center; gap:8px; background:#4CAF50; color:#fff;
    padding:11px 22px; border-radius:26px; font-weight:700; font-size:0.86rem; text-decoration:none;
    transition:background .2s, transform .2s;
}
.abt-hslide-cta:hover{ background:#43A047; transform:translateY(-2px); }
.abt-hslider-dots{ position:absolute; left:0; right:0; bottom:18px; z-index:3; display:flex; justify-content:center; gap:8px; }
.abt-hslider-dot{ width:8px; height:8px; border-radius:50%; background:rgba(255,255,255,0.35); border:none; cursor:pointer; padding:0; transition:0.3s; }
.abt-hslider-dot.abt-active-dot{ background:#4CAF50; width:24px; border-radius:4px; transform:none; }

/* ── Hero slide (first slide — brand intro, now with image) ── */
.abt-hslide.abt-hero-slide{
    background-color:#0f2a16;
    justify-content:center;
    cursor:default;
}
.abt-hslide.abt-hero-slide::before{
    content:'🌾'; position:absolute; font-size:180px; opacity:0.05;
    right:2%; top:-30px; pointer-events:none; line-height:1;
}
.abt-hslide.abt-hero-slide::after{
    content:'🌿'; position:absolute; font-size:140px; opacity:0.045;
    left:-2%; bottom:-40px; pointer-events:none; line-height:1;
}
.abt-hero-slide-content{
    position:relative; z-index:1; text-align:center; max-width:640px; margin:0 auto; padding:0 1.5rem;
    opacity:0; transform:translateY(18px);
    transition: opacity .7s ease .2s, transform .7s ease .2s;
}
.abt-hslide.abt-active .abt-hero-slide-content{ opacity:1; transform:translateY(0); }
.abt-hero-slide-content .abt-badge{
    display:inline-block; background:rgba(76,175,80,0.18); color:#90e070;
    border:1px solid rgba(76,175,80,0.3); font-size:11px; letter-spacing:2px;
    padding:4px 16px; border-radius:20px; margin-bottom:16px; text-transform:uppercase; font-weight:600;
}
.abt-hero-slide-content h1{ font-size:clamp(1.9rem, 4.5vw, 2.9rem); font-weight:700; color:#fff; margin:0 0 14px; line-height:1.25; }
.abt-hero-slide-content h1 span{ color:#4caf50; }
.abt-hero-slide-content p{ color:rgba(255,255,255,0.68); font-size:1.02rem; max-width:600px; margin:0 auto; line-height:1.7; }

@media (max-width:640px){
    .abt-hero-slider{ height:520px; }
    .abt-hslide-content{ padding:0 1.5rem; }
}

/* ── Inner shell ──────────────────────────────────── */
.abt-inner{ max-width:1080px; margin:0 auto; padding:3.2rem 1.2rem; }
.abt-inner.tight{ padding-top:0; }

.abt-sec-head{ margin-bottom:1.8rem; max-width:640px; }
.abt-sec-eyebrow{
    display:flex; align-items:center; gap:10px;
    font-size:12px; font-weight:700; letter-spacing:1.5px;
    color:#2E7D32; text-transform:uppercase; margin-bottom:10px;
}
.abt-sec-eyebrow::before{ content:''; width:22px; height:3px; background:#FFC107; border-radius:2px; }
.abt-sec-head h2{ font-size:clamp(1.5rem,3vw,2rem); font-weight:700; color:#0f2a16; line-height:1.3; }
.abt-sec-head p{ margin-top:10px; color:#5a6b5a; font-size:0.98rem; line-height:1.65; }
.abt-sec-head.center{ margin-left:auto; margin-right:auto; text-align:center; }
.abt-sec-head.center .abt-sec-eyebrow{ justify-content:center; }

/* ── Mission / Vision ─────────────────────────────── */
.abt-mission-grid{ display:grid; grid-template-columns:1.05fr 1fr; gap:2.6rem; align-items:start; }
.abt-mission-copy p{ color:#4a5a4a; font-size:1rem; line-height:1.75; margin-bottom:14px; }
.abt-mission-cards{ display:flex; flex-direction:column; gap:18px; }
.abt-m-card{
    background:#fff; border:1.5px solid #dde8dd; border-left:4px solid #2E7D32;
    border-radius:10px; padding:22px 24px;
}
.abt-m-card.vision{ border-left-color:#FFC107; }
.abt-m-card h3{
    font-size:13px; font-weight:700; text-transform:uppercase; letter-spacing:0.06em;
    color:#2E7D32; margin-bottom:8px;
}
.abt-m-card.vision h3{ color:#b8860b; }
.abt-m-card p{ color:#333; font-size:0.96rem; line-height:1.6; }

/* ── Offer grid (real features) ──────────────────── */
.abt-offer-grid{ display:grid; grid-template-columns:repeat(5,1fr); gap:14px; }
.abt-offer-card{
    background:#fff; border:1.5px solid #dde8dd; border-radius:14px; overflow:hidden;
    text-decoration:none; display:flex; flex-direction:column;
    transition:transform .2s, box-shadow .2s, border-color .2s;
}
.abt-offer-card:hover{ transform:translateY(-5px); box-shadow:0 14px 28px rgba(46,139,61,0.14); border-color:#4CAF50; }
.abt-offer-img{ height:110px; background-size:cover; background-position:center; position:relative; }
.abt-offer-img::after{ content:''; position:absolute; inset:0; background:linear-gradient(180deg, rgba(11,26,20,0) 50%, rgba(11,26,20,0.45) 100%); }
.abt-offer-body{ padding:16px 15px 18px; flex:1; display:flex; flex-direction:column; }
.abt-offer-body h3{ font-size:0.96rem; font-weight:700; color:#0f2a16; margin-bottom:6px; }
.abt-offer-body p{ font-size:0.83rem; color:#5a6b5a; line-height:1.5; flex:1; }
.abt-offer-cta{ margin-top:10px; font-size:0.78rem; font-weight:700; color:#2E7D32; display:flex; align-items:center; gap:5px; }
.abt-offer-card:hover .abt-offer-cta{ color:#1b5e20; }

/* ── Why us ───────────────────────────────────────── */
.abt-why{ background:#f0f9f0; }
.abt-why-grid{ display:grid; grid-template-columns:repeat(4,1fr); gap:26px; }
.abt-why-item .abt-why-icon{
    width:46px; height:46px; border-radius:12px; background:#fff; border:1.5px solid #dde8dd;
    display:flex; align-items:center; justify-content:center; color:#2E7D32; font-size:18px; margin-bottom:14px;
}
.abt-why-item h3{ font-size:1rem; font-weight:700; color:#0f2a16; margin-bottom:6px; }
.abt-why-item p{ font-size:0.9rem; color:#5a6b5a; line-height:1.6; }

/* ── Reveal animation ─────────────────────────────── */
.abt-reveal{ opacity:0; transform:translateY(26px); transition:opacity .7s ease, transform .7s ease; }
.abt-reveal.abt-vis{ opacity:1; transform:translateY(0); }
.abt-reveal-stagger > *{ opacity:0; transform:translateY(20px); transition:opacity .6s ease, transform .6s ease; }
.abt-reveal-stagger.abt-vis > *{ opacity:1; transform:translateY(0); }
.abt-reveal-stagger.abt-vis > *:nth-child(1){ transition-delay:.05s; }
.abt-reveal-stagger.abt-vis > *:nth-child(2){ transition-delay:.13s; }
.abt-reveal-stagger.abt-vis > *:nth-child(3){ transition-delay:.21s; }
.abt-reveal-stagger.abt-vis > *:nth-child(4){ transition-delay:.29s; }
.abt-reveal-stagger.abt-vis > *:nth-child(5){ transition-delay:.37s; }

@media (max-width:980px){
    .abt-mission-grid{ grid-template-columns:1fr; }
    .abt-offer-grid{ grid-template-columns:repeat(3,1fr); }
    .abt-why-grid{ grid-template-columns:repeat(2,1fr); }
}
@media (max-width:640px){
    .abt-offer-grid{ grid-template-columns:repeat(2,1fr); }
    .abt-why-grid{ grid-template-columns:1fr; }
    .abt-stat{ padding:1rem 1.4rem; }
}
</style>

<div class="abt-wrap">

    <!-- IMAGE SLIDER (hero + homepage-style feature slides, merged) -->
    <section class="abt-hero-slider" id="abtHeroSlider">
        <div class="abt-hslide abt-hero-slide abt-active" style="background-image:url('<?php echo $base_path; ?>/assets/images/about.png');">
            <div class="abt-hslide-overlay"></div>
            <div class="abt-hero-slide-content">
                <div class="abt-badge" data-t="badge">About Us</div>
                <h1 data-t="title">Where the <span>harvest</span> meets its home.</h1>
                <p data-t="subtitle">AgriCart connects India's farmers directly with the people who need what they grow — real produce and honest farm inputs, at a fair price, with no middlemen in between.</p>
            </div>
        </div>
        <div class="abt-hslide" style="background-image:url('<?php echo $base_path; ?>/assets/images/agristore.png');" onclick="window.location='<?php echo $base_path; ?>/pages/marketplace.php'">
            <div class="abt-hslide-overlay"></div>
            <div class="abt-hslide-content">
                <div class="abt-hslide-tag" data-t="offer1h">Agri Store</div>
                <h2 data-t="offer1p">Certified seeds, fertilizers and pesticides from verified sellers.</h2>
                <a href="<?php echo $base_path; ?>/pages/marketplace.php" class="abt-hslide-cta" onclick="event.stopPropagation()" data-t="offer1cta">Open Store</a>
            </div>
        </div>
        <div class="abt-hslide" style="background-image:url('<?php echo $base_path; ?>/assets/images/equipment.png');" onclick="window.location='<?php echo $base_path; ?>/pages/rental.php'">
            <div class="abt-hslide-overlay"></div>
            <div class="abt-hslide-content">
                <div class="abt-hslide-tag" data-t="offer2h">Rental Hub</div>
                <h2 data-t="offer2p">Tractors, drone sprayers and harvesting equipment, by the hour or day.</h2>
                <a href="<?php echo $base_path; ?>/pages/rental.php" class="abt-hslide-cta" onclick="event.stopPropagation()" data-t="offer2cta">Rent Now</a>
            </div>
        </div>
        <div class="abt-hslide" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory.png');" onclick="window.location='<?php echo $base_path; ?>/pages/advisory.php'">
            <div class="abt-hslide-overlay"></div>
            <div class="abt-hslide-content">
                <div class="abt-hslide-tag" data-t="offer3h">Crop Advisory</div>
                <h2 data-t="offer3p">AI-powered disease detection and expert crop recommendations.</h2>
                <a href="<?php echo $base_path; ?>/pages/advisory.php" class="abt-hslide-cta" onclick="event.stopPropagation()" data-t="offer3cta">Get Advice</a>
            </div>
        </div>
        <div class="abt-hslide" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar.png');" onclick="window.location='<?php echo $base_path; ?>/pages/krishi_bazaar.php'">
            <div class="abt-hslide-overlay"></div>
            <div class="abt-hslide-content">
                <div class="abt-hslide-tag" data-t="offer4h">Krishi Bazaar</div>
                <h2 data-t="offer4p">Live APMC mandi rates — no middlemen, maximum value for your harvest.</h2>
                <a href="<?php echo $base_path; ?>/pages/krishi_bazaar.php" class="abt-hslide-cta" onclick="event.stopPropagation()" data-t="offer4cta">Check Rates</a>
            </div>
        </div>
        <div class="abt-hslide" style="background-image:url('<?php echo $base_path; ?>/assets/images/agriconnect.png');" onclick="window.location='<?php echo $base_path; ?>/pages/agri-connect.php'">
            <div class="abt-hslide-overlay"></div>
            <div class="abt-hslide-content">
                <div class="abt-hslide-tag" data-t="offer5h">Agri Connect</div>
                <h2 data-t="offer5p">Discuss challenges, share tips and learn from fellow farmers.</h2>
                <a href="<?php echo $base_path; ?>/pages/agri-connect.php" class="abt-hslide-cta" onclick="event.stopPropagation()" data-t="offer5cta">Join Now</a>
            </div>
        </div>
        <div class="abt-hslider-dots" id="abtHSliderDots"></div>
    </section>

    <!-- STATS -->
    <section class="stats">
        <div class="stat-item"><h3>5</h3><p data-t="stat1">Core Services</p></div>
        <div class="stat-item"><h3>0%</h3><p data-t="stat2">Middleman Cut</p></div>
        <div class="stat-item"><h3><?php echo $stat_districts_served; ?>+</h3><p data-t="stat3">Districts Served</p></div>
        <div class="stat-item"><h3>1</h3><p data-t="stat4">Platform, One Login</p></div>
    </section>

    <!-- MISSION / VISION -->
    <section class="abt-inner">
        <div class="abt-mission-grid">
            <div class="abt-mission-copy abt-reveal">
                <div class="abt-sec-eyebrow" data-t="missionLabel">WHY WE EXIST</div>
                <h2 style="font-size:clamp(1.5rem,3vw,2rem); font-weight:700; color:#0f2a16; margin-bottom:16px;" data-t="missionTitle">A fair field for everyone who works it.</h2>
                <p data-t="missionP1">AgriCart began with a simple observation: the farmer who grows the wheat is usually the last person to benefit from its price. Between the field and the table sit layers of agents and traders — each taking a cut, none of them accountable to the person who did the work.</p>
                <p data-t="missionP2">We built AgriCart to shorten that distance — one platform for buying certified seeds and crop-care inputs, renting equipment, getting AI-backed crop advice, checking live mandi rates, and selling produce, all without a middleman in between.</p>
            </div>
            <div class="abt-mission-cards abt-reveal">
                <div class="abt-m-card">
                    <h3 data-t="missionCardLabel">Our Mission</h3>
                    <p data-t="missionCardBody">Every farmer gets a fair price for their harvest, and every buyer gets exactly what they paid for — nothing hidden, nothing skimmed.</p>
                </div>
                <div class="abt-m-card vision">
                    <h3 data-t="visionCardLabel">Our Vision</h3>
                    <p data-t="visionCardBody">A country where the shortest path from field to fork also happens to be the fairest one.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- WHAT WE OFFER -->
    <section class="abt-inner" style="padding-top:0;">
        <div class="abt-sec-head abt-reveal">
            <div class="abt-sec-eyebrow" data-t="offerLabel">WHAT WE OFFER</div>
            <h2 data-t="offerTitle">Everything a farm needs, in one place.</h2>
            <p data-t="offerSub">Five tools, one login — from buying inputs to selling your harvest.</p>
        </div>
        <div class="abt-offer-grid abt-reveal-stagger">
            <a href="<?php echo $base_path; ?>/pages/marketplace.php" class="abt-offer-card">
                <div class="abt-offer-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/agristore.png');"></div>
                <div class="abt-offer-body">
                    <h3 data-t="offer1h">Agri Store</h3>
                    <p data-t="offer1p">Certified seeds, fertilizers and pesticides from verified sellers.</p>
                    <div class="abt-offer-cta"><span data-t="offer1cta">Open Store</span> <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <a href="<?php echo $base_path; ?>/pages/rental.php" class="abt-offer-card">
                <div class="abt-offer-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/equipment.png');"></div>
                <div class="abt-offer-body">
                    <h3 data-t="offer2h">Rental Hub</h3>
                    <p data-t="offer2p">Tractors, drone sprayers and harvesting equipment, by the hour or day.</p>
                    <div class="abt-offer-cta"><span data-t="offer2cta">Rent Now</span> <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <a href="<?php echo $base_path; ?>/pages/advisory.php" class="abt-offer-card">
                <div class="abt-offer-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory.png');"></div>
                <div class="abt-offer-body">
                    <h3 data-t="offer3h">Crop Advisory</h3>
                    <p data-t="offer3p">AI-powered disease detection and expert crop recommendations.</p>
                    <div class="abt-offer-cta"><span data-t="offer3cta">Get Advice</span> <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <a href="<?php echo $base_path; ?>/pages/krishi_bazaar.php" class="abt-offer-card">
                <div class="abt-offer-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar.png');"></div>
                <div class="abt-offer-body">
                    <h3 data-t="offer4h">Krishi Bazaar</h3>
                    <p data-t="offer4p">Live APMC mandi rates — no middlemen, maximum value for your harvest.</p>
                    <div class="abt-offer-cta"><span data-t="offer4cta">Check Rates</span> <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
            <a href="<?php echo $base_path; ?>/pages/agri-connect.php" class="abt-offer-card">
                <div class="abt-offer-img" style="background-image:url('<?php echo $base_path; ?>/assets/images/agriconnect.png');"></div>
                <div class="abt-offer-body">
                    <h3 data-t="offer5h">Agri Connect</h3>
                    <p data-t="offer5p">Discuss challenges, share tips and learn from fellow farmers.</p>
                    <div class="abt-offer-cta"><span data-t="offer5cta">Join Now</span> <i class="fa-solid fa-arrow-right"></i></div>
                </div>
            </a>
        </div>
    </section>

    <!-- WHY US -->
    <section class="abt-why">
        <div class="abt-inner">
            <div class="abt-sec-head abt-reveal">
                <div class="abt-sec-eyebrow" data-t="whyLabel">WHY FARMERS CHOOSE US</div>
                <h2 data-t="whyTitle">Trust, built into every order.</h2>
            </div>
            <div class="abt-why-grid abt-reveal-stagger">
                <div class="abt-why-item">
                    <div class="abt-why-icon"><i class="fa-solid fa-handshake"></i></div>
                    <h3 data-t="why1h">Direct Connect</h3>
                    <p data-t="why1p">No middlemen. Farmers list, buyers order, everyone keeps their fair share.</p>
                </div>
                <div class="abt-why-item">
                    <div class="abt-why-icon"><i class="fa-solid fa-shield-halved"></i></div>
                    <h3 data-t="why2h">Verified Quality</h3>
                    <p data-t="why2p">Every input is checked before listing. Every harvest is inspected before dispatch.</p>
                </div>
                <div class="abt-why-item">
                    <div class="abt-why-icon"><i class="fa-solid fa-tag"></i></div>
                    <h3 data-t="why3h">Transparent Pricing</h3>
                    <p data-t="why3p">The price you see is the price paid — to the farmer, and by the buyer.</p>
                </div>
                <div class="abt-why-item">
                    <div class="abt-why-icon"><i class="fa-solid fa-truck-fast"></i></div>
                    <h3 data-t="why4h">Reliable Delivery</h3>
                    <p data-t="why4p">Cold-chain and last-mile logistics, built specifically for perishable goods.</p>
                </div>
            </div>
        </div>
    </section>


</div>

<script>
(function(){

  /* ── TRANSLATION DICTIONARY (About page) ─────────── */
  var _T = {
    mr: {
      badge:'आमच्याबद्दल', title:'जिथे <span>शेतमाल</span> पोहोचतो त्याच्या हक्काच्या घरी.',
      subtitle:'AgriCart भारतातील शेतकऱ्यांना, त्यांचा माल हवा असणाऱ्या लोकांशी थेट जोडते — खरा शेतमाल आणि प्रामाणिक शेती-साहित्य, योग्य किमतीत, मध्यस्थांशिवाय.',
      stat1:'मुख्य सेवा', stat2:'दलालांचा वाटा', stat3:'सेवा दिलेले जिल्हे', stat4:'एक व्यासपीठ, एक लॉगिन',
      missionLabel:'आम्ही का सुरू केलं', missionTitle:'जो राबतो, त्यालाच खरा वाटा मिळणारं शेत.',
      missionP1:'AgriCart ची सुरुवात एका साध्या पण त्रासदायक निरीक्षणातून झाली — जो शेतकरी गहू पिकवतो, त्यालाच त्याच्या किमतीचा फायदा शेवटी मिळतो. शेत आणि ताटामध्ये अनेक दलाल आणि व्यापारी असतात, प्रत्येकजण आपला वाटा घेतो, पण कष्ट करणाऱ्याला कोणीही जबाबदार नसतं.',
      missionP2:'ही दरी कमी करण्यासाठी आम्ही AgriCart बनवलं — प्रमाणित बियाणे व खते खरेदी, अवजारे भाड्याने घेणे, AI आधारित पीक सल्ला, थेट मंडई भाव, आणि शेतमाल विक्री — हे सर्व एकाच व्यासपीठावर, दलालांशिवाय.',
      missionCardLabel:'आमचं ध्येय', missionCardBody:'प्रत्येक शेतकऱ्याला त्याच्या मालाची योग्य किंमत मिळावी, आणि प्रत्येक खरेदीदाराला त्याने दिलेल्या पैशांचं योग्य मूल्य मिळावं — काहीही लपवलेलं नाही, काहीही कापलेलं नाही.',
      visionCardLabel:'आमचं स्वप्न', visionCardBody:'असा देश जिथे शेत ते ताट हा सर्वात लहान मार्गच सर्वात न्याय्य मार्गही असेल.',
      offerLabel:'आम्ही काय देतो', offerTitle:'शेतीसाठी लागणारं सर्वकाही, एकाच ठिकाणी.', offerSub:'पाच सेवा, एक लॉगिन — साहित्य खरेदीपासून पीक विक्रीपर्यंत.',
      offer1h:'कृषी स्टोअर', offer1p:'विश्वसनीय विक्रेत्यांकडून प्रमाणित बियाणे, खते आणि कीटकनाशके.', offer1cta:'स्टोअर उघडा',
      offer2h:'अवजारे केंद्र', offer2p:'ट्रॅक्टर, ड्रोन फवारणी आणि कापणी यंत्रे, तासाने किंवा दिवसाने.', offer2cta:'आता भाड्याने घ्या',
      offer3h:'पीक सल्ला', offer3p:'AI-आधारित रोग निदान आणि तज्ज्ञांच्या शिफारसी.', offer3cta:'सल्ला मिळवा',
      offer4h:'कृषी बाजार', offer4p:'थेट APMC मंडई भाव — दलाल नाही, तुमच्या मालाला पूर्ण किंमत.', offer4cta:'भाव पहा',
      offer5h:'कृषी संवाद', offer5p:'शेतीच्या अडचणींवर चर्चा करा आणि इतर शेतकऱ्यांकडून शिका.', offer5cta:'सामील व्हा',
      whyLabel:'शेतकरी आम्हाला का निवडतात', whyTitle:'प्रत्येक ऑर्डरमध्ये विश्वास.',
      why1h:'थेट जोड', why1p:'दलाल नाही. शेतकरी माल नोंदवतो, खरेदीदार ऑर्डर करतो, प्रत्येकाला योग्य वाटा मिळतो.',
      why2h:'तपासलेली गुणवत्ता', why2p:'यादीत टाकण्याआधी प्रत्येक वस्तू तपासली जाते. पाठवण्याआधी प्रत्येक माल तपासला जातो.',
      why3h:'पारदर्शक किंमत', why3p:'जी किंमत दिसते, तीच शेतकऱ्याला मिळते आणि खरेदीदार देतो.',
      why4h:'विश्वासार्ह डिलिव्हरी', why4p:'नाशवंत मालासाठी खास बनवलेली कोल्ड-चेन आणि शेवटच्या टप्प्यापर्यंतची लॉजिस्टिक्स व्यवस्था.'
    },
    hi: {
      badge:'हमारे बारे में', title:'जहाँ <span>फ़सल</span> अपने असली घर पहुँचती है.',
      subtitle:'AgriCart भारत के किसानों को उन लोगों से सीधे जोड़ता है जिन्हें उनकी उपज चाहिए — असली उपज और ईमानदार कृषि-सामग्री, उचित दाम पर, बिचौलियों के बिना.',
      stat1:'मुख्य सेवाएँ', stat2:'बिचौलिये का हिस्सा', stat3:'सेवा प्राप्त ज़िले', stat4:'एक मंच, एक लॉगिन',
      missionLabel:'हमने यह क्यों शुरू किया', missionTitle:'मेहनत करने वाले को ही असली हिस्सा मिले, ऐसा खेत.',
      missionP1:'AgriCart की शुरुआत एक सीधी लेकिन परेशान करने वाली बात से हुई — जो किसान गेहूं उगाता है, उसे ही उसकी कीमत का फ़ायदा सबसे आख़िर में मिलता है। खेत और थाली के बीच कई एजेंट और व्यापारी होते हैं, हर कोई अपना हिस्सा लेता है, पर मेहनत करने वाले के प्रति कोई जवाबदेह नहीं होता।',
      missionP2:'इस दूरी को कम करने के लिए हमने AgriCart बनाया — प्रमाणित बीज व खाद ख़रीदना, उपकरण किराए पर लेना, AI आधारित फ़सल सलाह, लाइव मंडी भाव, और उपज बेचना — यह सब एक ही मंच पर, बिना किसी बिचौलिये के।',
      missionCardLabel:'हमारा लक्ष्य', missionCardBody:'हर किसान को उसकी उपज की उचित कीमत मिले, और हर ख़रीदार को अपने पैसों का पूरा मूल्य मिले — कुछ भी छिपा नहीं, कुछ भी कम नहीं।',
      visionCardLabel:'हमारा सपना', visionCardBody:'एक ऐसा देश जहाँ खेत से थाली तक का सबसे छोटा रास्ता ही सबसे निष्पक्ष रास्ता भी हो।',
      offerLabel:'हम क्या देते हैं', offerTitle:'खेती के लिए ज़रूरी हर चीज़, एक ही जगह.', offerSub:'पाँच सेवाएँ, एक लॉगिन — सामग्री ख़रीदने से लेकर फ़सल बेचने तक.',
      offer1h:'एग्री स्टोर', offer1p:'सत्यापित विक्रेताओं से प्रमाणित बीज, खाद और कीटनाशक.', offer1cta:'स्टोर खोलें',
      offer2h:'रेंटल हब', offer2p:'ट्रैक्टर, ड्रोन स्प्रेयर और हार्वेस्टिंग उपकरण, घंटे या दिन के हिसाब से.', offer2cta:'अभी किराए पर लें',
      offer3h:'फ़सल सलाह', offer3p:'AI आधारित रोग पहचान और विशेषज्ञ सुझाव.', offer3cta:'सलाह लें',
      offer4h:'कृषि बाज़ार', offer4p:'लाइव APMC मंडी भाव — कोई बिचौलिया नहीं, आपकी उपज की पूरी कीमत.', offer4cta:'भाव देखें',
      offer5h:'एग्री कनेक्ट', offer5p:'खेती की समस्याओं पर चर्चा करें और दूसरे किसानों से सीखें.', offer5cta:'जुड़ें',
      whyLabel:'किसान हमें क्यों चुनते हैं', whyTitle:'हर ऑर्डर में भरोसा.',
      why1h:'सीधा जुड़ाव', why1p:'कोई बिचौलिया नहीं. किसान सूचीबद्ध करता है, ख़रीदार ऑर्डर करता है, सभी को उचित हिस्सा मिलता है.',
      why2h:'सत्यापित गुणवत्ता', why2p:'सूचीबद्ध होने से पहले हर सामग्री जाँची जाती है. भेजने से पहले हर उपज की जाँच होती है.',
      why3h:'पारदर्शी क़ीमत', why3p:'जो कीमत दिखती है, वही किसान को मिलती है और ख़रीदार देता है.',
      why4h:'भरोसेमंद डिलीवरी', why4p:'जल्दी ख़राब होने वाले सामान के लिए बनाई गई कोल्ड-चेन और लास्ट-माइल लॉजिस्टिक्स.'
    },
    en: {
      badge:'About Us', title:'Where the <span>harvest</span> meets its home.',
      subtitle:"AgriCart connects India's farmers directly with the people who need what they grow — real produce and honest farm inputs, at a fair price, with no middlemen in between.",
      stat1:'Core Services', stat2:'Middleman Cut', stat3:'Districts Served', stat4:'Platform, One Login',
      missionLabel:'WHY WE EXIST', missionTitle:'A fair field for everyone who works it.',
      missionP1:"AgriCart began with a simple observation: the farmer who grows the wheat is usually the last person to benefit from its price. Between the field and the table sit layers of agents and traders — each taking a cut, none of them accountable to the person who did the work.",
      missionP2:"We built AgriCart to shorten that distance — one platform for buying certified seeds and crop-care inputs, renting equipment, getting AI-backed crop advice, checking live mandi rates, and selling produce, all without a middleman in between.",
      missionCardLabel:'Our Mission', missionCardBody:'Every farmer gets a fair price for their harvest, and every buyer gets exactly what they paid for — nothing hidden, nothing skimmed.',
      visionCardLabel:'Our Vision', visionCardBody:'A country where the shortest path from field to fork also happens to be the fairest one.',
      offerLabel:'WHAT WE OFFER', offerTitle:'Everything a farm needs, in one place.', offerSub:'Five tools, one login — from buying inputs to selling your harvest.',
      offer1h:'Agri Store', offer1p:'Certified seeds, fertilizers and pesticides from verified sellers.', offer1cta:'Open Store',
      offer2h:'Rental Hub', offer2p:'Tractors, drone sprayers and harvesting equipment, by the hour or day.', offer2cta:'Rent Now',
      offer3h:'Crop Advisory', offer3p:'AI-powered disease detection and expert crop recommendations.', offer3cta:'Get Advice',
      offer4h:'Krishi Bazaar', offer4p:'Live APMC mandi rates — no middlemen, maximum value for your harvest.', offer4cta:'Check Rates',
      offer5h:'Agri Connect', offer5p:'Discuss challenges, share tips and learn from fellow farmers.', offer5cta:'Join Now',
      whyLabel:'WHY FARMERS CHOOSE US', whyTitle:'Trust, built into every order.',
      why1h:'Direct Connect', why1p:'No middlemen. Farmers list, buyers order, everyone keeps their fair share.',
      why2h:'Verified Quality', why2p:'Every input is checked before listing. Every harvest is inspected before dispatch.',
      why3h:'Transparent Pricing', why3p:'The price you see is the price paid — to the farmer, and by the buyer.',
      why4h:'Reliable Delivery', why4p:'Cold-chain and last-mile logistics, built specifically for perishable goods.'
    }
  };

  var _lang = 'mr';

  window.setLang = function(lang){
    if (!_T[lang]) return;
    _lang = lang;
    var t = _T[lang];
    document.querySelectorAll('[data-t]').forEach(function(el){
      var k = el.getAttribute('data-t');
      if (t[k] !== undefined) el.innerHTML = t[k];
    });
  };

  window.pageLanguageCallback = function(lang){
    if (window.setLang) window.setLang(lang);
  };

  document.addEventListener('DOMContentLoaded', function(){
    var saved = localStorage.getItem('agri_lang') || 'en';
    if (window.setLang) window.setLang(saved);

    /* ── Image slider (homepage-style) ─────────────── */
    var hSlider = document.getElementById('abtHeroSlider');
    var hDotsWrap = document.getElementById('abtHSliderDots');
    if (hSlider) {
      var hSlides = hSlider.querySelectorAll('.abt-hslide');
      var hIndex = 0;
      var hTimer = null;

      hSlides.forEach(function(_, i){
        var dot = document.createElement('button');
        dot.className = 'abt-hslider-dot' + (i === 0 ? ' abt-active-dot' : '');
        dot.setAttribute('aria-label', 'Slide ' + (i + 1));
        dot.addEventListener('click', function(){ goToHSlide(i); resetHAutoplay(); });
        hDotsWrap.appendChild(dot);
      });
      var hDots = hDotsWrap.querySelectorAll('.abt-hslider-dot');

      function goToHSlide(i){
        hIndex = (i + hSlides.length) % hSlides.length;
        hSlides.forEach(function(s, si){ s.classList.toggle('abt-active', si === hIndex); });
        hDots.forEach(function(d, di){ d.classList.toggle('abt-active-dot', di === hIndex); });
      }
      function resetHAutoplay(){
        if (hTimer) clearInterval(hTimer);
        hTimer = setInterval(function(){ goToHSlide(hIndex + 1); }, 4500);
      }
      resetHAutoplay();

      hSlider.addEventListener('mouseenter', function(){ if (hTimer) clearInterval(hTimer); });
      hSlider.addEventListener('mouseleave', function(){ resetHAutoplay(); });
    }

    /* ── Reveal on scroll ─────────────────────────── */
    var revealEls = document.querySelectorAll('.abt-reveal, .abt-reveal-stagger');
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (entry.isIntersecting) {
          entry.target.classList.add('abt-vis');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.15 });
    revealEls.forEach(function(el){ io.observe(el); });
  });

})();
</script>

<?php include __DIR__ . '/krishimitra_widget.php'; ?>

<?php include __DIR__ . '/../includes/footer.php'; ?>