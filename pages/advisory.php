<?php include __DIR__ . '/../includes/header.php'; ?>

<!-- ━━━ HERO SLIDER ━━━ -->
<div class="slider-wrap">
  <div class="slide active" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory/advisory-hero-leaf-scan.jpg');background-size:cover;background-position:center;"><div class="slide-overlay"></div><div class="slide-content"><div class="slide-tag" id="heroBadge">Crop Advisory</div><h1 id="heroTitle">AI-Powered Crop Doctor</h1><p id="heroSub">Upload a photo of your infected crop leaf to get instant machine-learning diagnostics.</p><a href="javascript:void(0)" onclick="kmScroll('secAI',6)" class="slide-cta" id="s1-btn"><i class="fa-solid fa-microscope"></i> Scan Your Crop</a></div></div>
  <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory/advisory-crop-soil.jpg');background-size:cover;background-position:center;"><div class="slide-overlay"></div><div class="slide-content"><div class="slide-tag" id="s4-tag">Crop &amp; Soil Advisory</div><h1 id="s4-h">Smart Crop &amp; Soil Guidance</h1><p id="s4-p">Get soil health insights and crop recommendations tailored to your field.</p><a href="javascript:void(0)" onclick="kmScroll('secCrop',0)" class="slide-cta" id="s4-btn"><i class="fa-solid fa-seedling"></i> Get Crop Advisory</a></div></div>
  <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory/advisory-fertilizer.jpg');background-size:cover;background-position:center;"><div class="slide-overlay"></div><div class="slide-content"><div class="slide-tag" id="s3-tag">Fertilizer Advisory</div><h1 id="s3-h">Smart Fertilizer Recommendation</h1><p id="s3-p">Know exactly which nutrients and fertilizer dose your soil needs.</p><a href="javascript:void(0)" onclick="kmScroll('secFert',1)" class="slide-cta" id="s3-btn"><i class="fa-solid fa-flask"></i> View Recommendation</a></div></div>
  <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory/advisory-weather-irrigation.jpg');background-size:cover;background-position:center;"><div class="slide-overlay"></div><div class="slide-content"><div class="slide-tag" id="s5-tag">Weather &amp; Irrigation</div><h1 id="s5-h">Weather &amp; Irrigation Monitoring</h1><p id="s5-p">Track weather, soil moisture and irrigation schedules in real time.</p><a href="javascript:void(0)" onclick="kmScroll('secIrr',3)" class="slide-cta" id="s5-btn"><i class="fa-solid fa-droplet"></i> Check Advisory</a></div></div>
  <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/advisory/advisory-pest-disease.jpg');background-size:cover;background-position:center;"><div class="slide-overlay"></div><div class="slide-content"><div class="slide-tag" id="s6-tag">Pest &amp; Disease Alert</div><h1 id="s6-h">Pest &amp; Disease Early Warning</h1><p id="s6-p">Get early alerts on pest attacks and crop diseases with drone-based scanning.</p><a href="javascript:void(0)" onclick="kmScroll('secAI',6)" class="slide-cta" id="s6-btn"><i class="fa-solid fa-triangle-exclamation"></i> View Alerts</a></div></div>
  <div class="slider-dots" id="sliderDots"></div>
</div>

<!-- ━━━ WEATHER STRIP ━━━ -->
<div class="km-wxbar-outer">
<div class="km-wxbar">
  <div class="km-wxi"><i class="fa-solid fa-temperature-three-quarters"></i><div><b id="wxValTemp">--°C</b><small id="wxlTemp">Temperature</small></div></div>
  <div class="km-wxd"></div>
  <div class="km-wxi"><i class="fa-solid fa-droplet"></i><div><b id="wxValHum">--%</b><small id="wxlHum">Humidity</small></div></div>
  <div class="km-wxd"></div>
  <div class="km-wxi"><i class="fa-solid fa-cloud-showers-heavy"></i><div><b id="wxValRain">-- mm</b><small id="wxlRain">Rainfall (7d)</small></div></div>
  <div class="km-wxd"></div>
  <div class="km-wxi"><i class="fa-solid fa-wind"></i><div><b id="wxValWind">-- km/h</b><small id="wxlWind">Wind</small></div></div>
  <div class="km-wxd"></div>
  <div class="km-wxi km-wxalert" id="wxRiskBox"><i class="fa-solid fa-triangle-exclamation"></i><div><b id="wxRisk">Mildew Risk</b><small><span id="wxlRisk">Disease Alert</span> — <span id="wxRiskLevel">--</span></small></div></div>
  <div class="km-wxd"></div>
  <div class="km-wxi"><i class="fa-solid fa-location-dot"></i><div><b id="wxLoc">Detecting…</b><small id="wxlLoc">Region</small></div></div>
</div>
</div>

<!-- ━━━ STICKY SECTION NAV ━━━ -->
<nav class="km-snav" id="kmSnav">
  <button class="km-snavb active" onclick="kmScroll('secCrop',0)"><i class="fa-solid fa-seedling"></i><span id="sn1">Crop &amp; Soil</span></button>
  <button class="km-snavb" onclick="kmScroll('secFert',1)"><i class="fa-solid fa-flask"></i><span id="sn2">Fertilizer</span></button>
  <button class="km-snavb" onclick="kmScroll('secCal',2)"><i class="fa-solid fa-calendar-days"></i><span id="sn3">Calendar</span></button>
  <button class="km-snavb" onclick="kmScroll('secIrr',3)"><i class="fa-solid fa-water"></i><span id="sn4">Irrigation</span></button>
  <button class="km-snavb" onclick="kmScroll('secSow',4)"><i class="fa-solid fa-circle-dot"></i><span id="sn5">Sowing</span></button>
  <button class="km-snavb" onclick="kmScroll('secHarv',5)"><i class="fa-solid fa-wheat-awn"></i><span id="sn6">Harvest</span></button>
  <button class="km-snavb" onclick="kmScroll('secAI',6)"><i class="fa-solid fa-robot"></i><span id="sn7">AI Doctor</span></button>
</nav>

<!-- ━━━ 1. CROP & SOIL ━━━ -->
<section class="km-sec" id="secCrop">
  <div class="km-inner">
    <div class="km-label">Personalised Advisory</div>
    <div class="km-hd"><div class="km-hd-ico"><i class="fa-solid fa-seedling"></i></div><div><h2 id="cropSelTitle">Crop &amp; Soil Selector</h2><p id="cropSelSub">Select your crop and soil type to get personalised advisory</p></div></div>
    <div class="km-selgrid">
      <div class="km-selcard"><label class="km-sellbl" id="lblCrop"><i class="fa-solid fa-leaf"></i> Select Crop</label><div class="km-selwrap"><select id="cropSelect" onchange="onCropChange()"><option value="" id="oCropDef">-- Select Crop --</option><option value="rice" id="oRice">🌾 Rice (Paddy)</option><option value="wheat" id="oWheat">🍞 Wheat</option><option value="cotton" id="oCotton">🌿 Cotton</option><option value="soyabean" id="oSoy">🟢 Soyabean</option><option value="tomato" id="oTomato">🍅 Tomato</option><option value="onion" id="oOnion">🧅 Onion</option><option value="potato" id="oPotato">🥔 Potato</option><option value="chilli" id="oChilli">🌶️ Chilli</option><option value="sugarcane" id="oSugar">🎋 Sugarcane</option><option value="pomegranate" id="oPomeg">🍎 Pomegranate</option></select><i class="fa-solid fa-chevron-down km-sarr"></i></div></div>
      <div class="km-selcard"><label class="km-sellbl" id="lblSoil"><i class="fa-solid fa-mound"></i> Select Soil Type</label><div class="km-selwrap"><select id="soilSelect" onchange="onSoilChange()"><option value="" id="oSoilDef">-- Select Soil --</option><option value="black" id="oBlack">⚫ Black Cotton Soil</option><option value="red" id="oRed">🔴 Red Laterite Soil</option><option value="alluvial" id="oAlluvial">🟡 Alluvial Soil</option><option value="sandy" id="oSandy">🏜️ Sandy Soil</option><option value="loamy" id="oLoamy">🟤 Loamy Soil</option></select><i class="fa-solid fa-chevron-down km-sarr"></i></div></div>
    </div>
    <div id="cropInfoPanel" class="km-cpanel" style="display:none;"></div>
  </div>
</section>

<!-- ━━━ 2. FERTILIZER ━━━ -->
<section class="km-sec km-alt" id="secFert">
  <div class="km-inner">
    <div class="km-label">Nutrient Schedule</div>
    <div class="km-hd"><div class="km-hd-ico"><i class="fa-solid fa-flask"></i></div><div><h2 id="fertTitle">Fertilizer Recommendation</h2><p id="fertSub">Select your crop above to get the fertilizer schedule</p></div></div>
    <div class="km-tblwrap"><table class="km-tbl"><thead><tr><th id="fH1">Growth Stage</th><th id="fH2">Nutrient</th><th id="fH3">Dose / Acre</th><th id="fH4">Timing</th><th id="fH5">Method</th></tr></thead><tbody id="fertBody"><tr><td colspan="5" class="km-tblempty" id="fertEmpty">Select a crop above to see fertilizer recommendations</td></tr></tbody></table></div>
  </div>
</section>

<!-- ━━━ 3. CROP CALENDAR ━━━ -->
<section class="km-sec" id="secCal">
  <div class="km-inner">
    <div class="km-label">Season Planner</div>
    <div class="km-hd"><div class="km-hd-ico"><i class="fa-solid fa-calendar-days"></i></div><div><h2 id="calTitle">Crop Calendar</h2><p id="calSub">Month-wise farming activities at a glance</p></div></div>
    <div class="km-calgrid">
      <div class="km-calcard" data-month="Jun"><div class="km-calh"><span id="calMJun">June</span><i class="fa-solid fa-seedling"></i></div><p id="calJun">Land Preparation &amp; Sowing</p></div>
      <div class="km-calcard" data-month="Jul"><div class="km-calh"><span id="calMJul">July</span><i class="fa-solid fa-flask"></i></div><p id="calJul">1st Fertilizer Application</p></div>
      <div class="km-calcard" data-month="Aug"><div class="km-calh"><span id="calMAug">August</span><i class="fa-solid fa-droplet"></i></div><p id="calAug">Irrigation &amp; Weed Control</p></div>
      <div class="km-calcard" data-month="Sep"><div class="km-calh"><span id="calMSep">September</span><i class="fa-solid fa-bug"></i></div><p id="calSep">Pest &amp; Disease Monitoring</p></div>
      <div class="km-calcard" data-month="Oct"><div class="km-calh"><span id="calMOct">October</span><i class="fa-solid fa-flask"></i></div><p id="calOct">2nd Fertilizer Dose</p></div>
      <div class="km-calcard" data-month="Nov"><div class="km-calh"><span id="calMNov">November</span><i class="fa-solid fa-wheat-awn"></i></div><p id="calNov">Harvest Preparation</p></div>
      <div class="km-calcard" data-month="Dec"><div class="km-calh"><span id="calMDec">December</span><i class="fa-solid fa-tractor"></i></div><p id="calDec">Harvesting</p></div>
      <div class="km-calcard" data-month="Jan"><div class="km-calh"><span id="calMJan">January</span><i class="fa-solid fa-warehouse"></i></div><p id="calJan">Storage &amp; Market</p></div>
    </div>
  </div>
</section>

<!-- ━━━ 4. IRRIGATION ━━━ -->
<section class="km-sec km-alt" id="secIrr">
  <div class="km-inner">
    <div class="km-label">Water Management</div>
    <div class="km-hd"><div class="km-hd-ico"><i class="fa-solid fa-water"></i></div><div><h2 id="irrTitle">Irrigation Guide</h2><p id="irrSub">Optimal irrigation schedule based on crop &amp; soil type</p></div></div>
    <div class="km-infogrid">
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-droplet"></i></span><h4 id="irrT1">Drip Irrigation</h4></div><p id="irrX1">Recommended for Cotton, Tomato, Chilli. Apply 3–4 litres/plant/day during flowering stage.</p></div>
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-water"></i></span><h4 id="irrT2">Flood Irrigation</h4></div><p id="irrX2">Suitable for Rice &amp; Sugarcane. Maintain 5 cm standing water in Kharif season fields.</p></div>
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-clock"></i></span><h4 id="irrT3">Best Timing</h4></div><p id="irrX3">Irrigate 6–8 AM or 5–7 PM to reduce evaporation by 30%. Avoid midday irrigation completely.</p></div>
      <div class="km-infocard km-infowarn"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-triangle-exclamation"></i></span><h4 id="irrT4">Critical Stages</h4></div><p id="irrX4">Never let soil dry during flowering &amp; grain-filling. Water stress here can reduce yield by 40%.</p></div>
    </div>
  </div>
</section>

<!-- ━━━ 5. SOWING ━━━ -->
<section class="km-sec" id="secSow">
  <div class="km-inner">
    <div class="km-label">Getting Started</div>
    <div class="km-hd"><div class="km-hd-ico"><i class="fa-solid fa-circle-dot"></i></div><div><h2 id="sowTitle">Sowing Guide</h2><p id="sowSub">Variety selection, seed treatment &amp; sowing best practices</p></div></div>
    <div class="km-steps">
      <div class="km-step"><div class="km-stepnum">1</div><div class="km-stepbody"><h4 id="sowT1">Seed Selection</h4><p id="sowX1">Choose certified seeds from approved varieties. For Rice: IR-64, Swarna. For Cotton: BT Hybrid varieties. Always check seed germination rate — minimum 85%.</p></div></div>
      <div class="km-step"><div class="km-stepnum">2</div><div class="km-stepbody"><h4 id="sowT2">Seed Treatment</h4><p id="sowX2">Treat seeds with Trichoderma viride (4 g/kg) + Carbendazim (2 g/kg) to prevent soil-borne diseases. Dry in shade for 30 min before sowing.</p></div></div>
      <div class="km-step"><div class="km-stepnum">3</div><div class="km-stepbody"><h4 id="sowT3">Sowing Depth &amp; Spacing</h4><p id="sowX3">Rice: 2–3 cm depth, 20×15 cm spacing. Cotton: 4–5 cm, 90×60 cm. Wheat: 4–6 cm, 22.5 cm row spacing. Follow recommended plant population per hectare.</p></div></div>
      <div class="km-step"><div class="km-stepnum">4</div><div class="km-stepbody"><h4 id="sowT4">Sowing Time</h4><p id="sowX4">Kharif: June 15 – July 15. Rabi: October 15 – November 30. Soil temperature should be 20–30°C for best germination results.</p></div></div>
    </div>
  </div>
</section>

<!-- ━━━ 6. HARVEST ━━━ -->
<section class="km-sec km-alt" id="secHarv">
  <div class="km-inner">
    <div class="km-label">Final Stage</div>
    <div class="km-hd"><div class="km-hd-ico"><i class="fa-solid fa-wheat-awn"></i></div><div><h2 id="harvTitle">Harvest Guide</h2><p id="harvSub">Maturity indicators, harvesting methods &amp; post-harvest tips</p></div></div>
    <div class="km-infogrid">
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-bullseye"></i></span><h4 id="harvT1">Maturity Indicators</h4></div><p id="harvX1">Rice: 80% grains turn golden, 25–30% moisture. Cotton: 60–70% boll opening. Wheat: golden yellow, hard grain stage.</p></div>
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-tractor"></i></span><h4 id="harvT2">Harvesting Method</h4></div><p id="harvX2">Use combine harvester for Wheat &amp; Rice. Manual picking for Cotton (3–4 pickings). Harvest in dry weather to reduce post-harvest losses.</p></div>
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-warehouse"></i></span><h4 id="harvT3">Post-Harvest Storage</h4></div><p id="harvX3">Dry grains to 12–14% moisture. Use hermetic bags or silos. Apply aluminium phosphide fumigation to prevent pest damage in storage.</p></div>
      <div class="km-infocard"><div class="km-infotop"><span class="km-infoico"><i class="fa-solid fa-sack-dollar"></i></span><h4 id="harvT4">Market Timing</h4></div><p id="harvX4">Check MSP (Minimum Support Price) before selling. Register on e-NAM portal for best price. Avoid distress selling immediately after harvest.</p></div>
    </div>
  </div>
</section>

<!-- ━━━ 7. AI SCANNER + DISEASE CARDS ━━━ -->
<div class="km-ai-feature" id="secAI">
  <div class="km-inner">
    <div class="km-label km-label-light">Featured Tool</div>
    <div class="km-hd km-hd-light"><div class="km-hd-ico km-hd-ico-dark"><i class="fa-solid fa-robot"></i></div><div><h2 id="scanDashTitle">AI Crop Scanner</h2><p id="aiSub">Upload a leaf photo for an instant simulated diagnosis</p></div></div>

    <div class="store-layout">
      <aside class="sidebar km-ai-sidebar">
        <div class="km-uploadzone" onclick="triggerLeafUpload()">
          <i class="fa-solid fa-cloud-arrow-up km-uploadico"></i>
          <h4 id="uploadTitle">Upload Leaf Image</h4>
          <p id="uploadSub">Supports JPG, PNG formats</p>
          <input type="file" id="leafFileInput" accept="image/*" style="display:none;" onchange="analyzeLeafImage()">
        </div>
        <div id="leafPreviewWrap" class="km-previewwrap" style="display:none;"><img id="leafPreviewImg" alt="Uploaded crop leaf photo"></div>
        <div id="scanAnalysisStatus" class="km-scanstatus" style="display:none;"></div>

        <!-- Government Schemes -->
        <div class="sidebar-section">
          <div class="km-sbhd"><i class="fa-solid fa-landmark"></i> <span id="govTitle">Government Schemes</span></div>
          <div class="km-schemes">
            <a href="https://pmfby.gov.in/" target="_blank" class="km-scheme"><i class="fa-solid fa-shield-halved"></i><span id="sch1">PM Fasal Bima Yojana</span><i class="fa-solid fa-arrow-up-right-from-square km-sext"></i></a>
            <a href="https://pmkisan.gov.in/" target="_blank" class="km-scheme"><i class="fa-solid fa-indian-rupee-sign"></i><span id="sch2">PM-KISAN Scheme</span><i class="fa-solid fa-arrow-up-right-from-square km-sext"></i></a>
            <a href="https://www.india.gov.in/spotlight/soil-health-card-scheme" target="_blank" class="km-scheme"><i class="fa-solid fa-earth-asia"></i><span id="sch3">Soil Health Card</span><i class="fa-solid fa-arrow-up-right-from-square km-sext"></i></a>
            <a href="https://enam.gov.in/" target="_blank" class="km-scheme"><i class="fa-solid fa-shop"></i><span id="sch4">e-NAM Market Portal</span><i class="fa-solid fa-arrow-up-right-from-square km-sext"></i></a>
            <a href="https://mkisan.gov.in/" target="_blank" class="km-scheme"><i class="fa-solid fa-mobile-screen"></i><span id="sch5">mKisan SMS Portal</span><i class="fa-solid fa-arrow-up-right-from-square km-sext"></i></a>
          </div>
        </div>

        <!-- Expert Tips -->
        <div class="sidebar-section">
          <div class="km-sbhd"><i class="fa-solid fa-user-doctor"></i> <span id="expertTitle">Expert Advice</span></div>
          <div class="km-tipbox">
            <div class="km-tip active" id="tip1"><strong id="tip1lbl">💡 Tip of the Day:</strong><br><span id="tip1txt">Always rotate crops every 2–3 years to prevent soil nutrient depletion and reduce pest buildup.</span></div>
            <div class="km-tip" id="tip2"><strong id="tip2lbl">💧 Water Tip:</strong><br><span id="tip2txt">Mulching around plants reduces water requirement by up to 50% and suppresses weed growth naturally.</span></div>
            <div class="km-tip" id="tip3"><strong id="tip3lbl">🔬 Soil Tip:</strong><br><span id="tip3txt">Get your soil tested before Kharif season. pH 6.5–7.5 is ideal for most Maharashtra crops.</span></div>
          </div>
          <div class="km-tipnav">
            <button class="km-tnav" onclick="prevTip()">&#8249;</button>
            <div class="km-tdots"><span class="km-tdot active"></span><span class="km-tdot"></span><span class="km-tdot"></span></div>
            <button class="km-tnav" onclick="nextTip()">&#8250;</button>
          </div>
          <button class="km-callbtn" onclick="callExpert()"><i class="fa-solid fa-phone-volume"></i> <span id="callLbl">Call Kisan Helpline</span></button>
        </div>

        <!-- Download -->
        <div class="sidebar-section">
          <button class="km-dlbtn" onclick="downloadReport(this)"><i class="fa-solid fa-file-arrow-down"></i> <span id="dlTxt">Download Report PDF</span></button>
        </div>
      </aside>

      <div class="products-area">
        <div class="offer-strip">
          <i class="fa-solid fa-triangle-exclamation"></i>
          <div><strong><span id="gridOfferHeading">High Alert: Palghar Region</span></strong><br><span id="gridOfferSub">Humid weather conditions are increasing the risk of Powdery Mildew in Cotton crops this week. Take preventive measures!</span></div>
        </div>
        <div class="filter-bar"><div class="filter-bar-left"><strong id="guideCount">6</strong> <span id="guidesAvailableText">Expert Treatment Handbooks Available</span></div></div>
        <div class="products-grid" id="advisoryGuidesGrid">

          <div class="product-card km-disease-card" onclick="openDiseaseModal(1)" tabindex="0" role="button"><div class="product-badge" id="d1-badge" data-type="fungal" style="background:#e65100;">Fungal</div><div class="km-diseaseico"><img src="<?php echo $base_path; ?>/assets/images/advisory/disease-powdery-mildew.jpg" alt="Powdery Mildew on leaf" loading="lazy"></div><div class="product-body"><div class="product-name" id="d1-name">Powdery Mildew</div><div class="product-unit" id="d1-crop">Target Crops: Cotton, Tomato</div><div class="km-rem"><span class="km-reml" id="rl1">Organic Remedy</span><span class="km-remv" id="d1-rem">Spray 1% Neem Oil or Elemental Sulfur</span></div><div class="km-dis"><div class="km-disr"><i class="fa-solid fa-magnifying-glass"></i><span id="d1sym">Symptoms: White powder on leaves, stunted growth, leaf curl.</span></div><div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span id="d1prev">Prevention: Avoid dense planting. Spray sulfur before monsoon.</span></div></div><button class="add-btn" onclick="event.stopPropagation();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text">Consult AI Doctor</span></button></div></div>

          <div class="product-card km-disease-card" onclick="openDiseaseModal(2)" tabindex="0" role="button"><div class="product-badge" id="d2-badge" data-type="bacterial" style="background:#d84315;">Bacterial</div><div class="km-diseaseico"><img src="<?php echo $base_path; ?>/assets/images/advisory/disease-bacterial-blight.jpg" alt="Bacterial Blight on plant" loading="lazy"></div><div class="product-body"><div class="product-name" id="d2-name">Bacterial Blight</div><div class="product-unit" id="d2-crop">Target Crops: Rice, Pomegranate</div><div class="km-rem"><span class="km-reml" id="rl2">Organic Remedy</span><span class="km-remv" id="d2-rem">Copper Oxychloride (2g/Litre of water)</span></div><div class="km-dis"><div class="km-disr"><i class="fa-solid fa-magnifying-glass"></i><span id="d2sym">Symptoms: Water-soaked edges turning brown, wilting of young shoots.</span></div><div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span id="d2prev">Prevention: Resistant varieties. Avoid excess nitrogen. Drain fields in rain.</span></div></div><button class="add-btn" onclick="event.stopPropagation();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text">Consult AI Doctor</span></button></div></div>

          <div class="product-card km-disease-card" onclick="openDiseaseModal(3)" tabindex="0" role="button"><div class="product-badge" id="d3-badge" data-type="viral" style="background:#7B1FA2;">Viral</div><div class="km-diseaseico"><img src="<?php echo $base_path; ?>/assets/images/advisory/disease-yellow-mosaic.jpg" alt="Yellow Mosaic Virus on leaf" loading="lazy"></div><div class="product-body"><div class="product-name" id="d3-name">Yellow Mosaic Virus</div><div class="product-unit" id="d3-crop">Target Crops: Chilli, Soyabean</div><div class="km-rem"><span class="km-reml" id="rl3">Organic Remedy</span><span class="km-remv" id="d3-rem">Control Whiteflies using Imidacloprid sticky traps</span></div><div class="km-dis"><div class="km-disr"><i class="fa-solid fa-magnifying-glass"></i><span id="d3sym">Symptoms: Yellow-green mosaic pattern on leaves, stunted plant growth.</span></div><div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span id="d3prev">Prevention: Remove infected plants immediately. Spray Imidacloprid 17.8SL.</span></div></div><button class="add-btn" onclick="event.stopPropagation();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text">Consult AI Doctor</span></button></div></div>

          <div class="product-card km-disease-card" onclick="openDiseaseModal(4)" tabindex="0" role="button"><div class="product-badge" id="d4-badge" data-type="fungal" style="background:#E65100;">Fungal</div><div class="km-diseaseico"><img src="<?php echo $base_path; ?>/assets/images/advisory/disease-late-blight.jpg" alt="Late Blight on potato plant" loading="lazy"></div><div class="product-body"><div class="product-name" id="d4-name">Late Blight</div><div class="product-unit" id="d4-crop">Target Crops: Potato, Tomato</div><div class="km-rem"><span class="km-reml" id="rl4">Organic Remedy</span><span class="km-remv" id="d4-rem">Mancozeb 75WP (2.5g/Litre)</span></div><div class="km-dis"><div class="km-disr"><i class="fa-solid fa-magnifying-glass"></i><span id="d4sym">Symptoms: Black-brown leaf edges. Spreads fast in cold &amp; humid weather.</span></div><div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span id="d4prev">Prevention: Avoid overhead irrigation. Space plants 60 cm apart.</span></div></div><button class="add-btn" onclick="event.stopPropagation();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text">Consult AI Doctor</span></button></div></div>

          <div class="product-card km-disease-card" onclick="openDiseaseModal(5)" tabindex="0" role="button"><div class="product-badge" id="d5-badge" data-type="pest" style="background:#b71c1c;">Pest</div><div class="km-diseaseico"><img src="<?php echo $base_path; ?>/assets/images/advisory/disease-stem-borer.jpg" alt="Stem Borer damage on crop" loading="lazy"></div><div class="product-body"><div class="product-name" id="d5-name">Stem Borer</div><div class="product-unit" id="d5-crop">Target Crops: Wheat, Rice, Sugarcane</div><div class="km-rem"><span class="km-reml" id="rl5">Organic Remedy</span><span class="km-remv" id="d5-rem">Chlorpyrifos 2% granules or Coragen spray</span></div><div class="km-dis"><div class="km-disr"><i class="fa-solid fa-magnifying-glass"></i><span id="d5sym">Symptoms: Holes in stem, yellowing leaves. Monitor from 30 DAS.</span></div><div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span id="d5prev">Prevention: Pheromone traps. Remove and burn infected stems.</span></div></div><button class="add-btn" onclick="event.stopPropagation();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text">Consult AI Doctor</span></button></div></div>

          <div class="product-card km-disease-card" onclick="openDiseaseModal(6)" tabindex="0" role="button"><div class="product-badge" id="d6-badge" data-type="fungal" style="background:#E65100;">Fungal</div><div class="km-diseaseico"><img src="<?php echo $base_path; ?>/assets/images/advisory/disease-root-rot.jpg" alt="Root Rot on onion crop" loading="lazy"></div><div class="product-body"><div class="product-name" id="d6-name">Root Rot</div><div class="product-unit" id="d6-crop">Target Crops: Soyabean, Cotton, Onion</div><div class="km-rem"><span class="km-reml" id="rl6">Organic Remedy</span><span class="km-remv" id="d6-rem">Trichoderma viride seed treatment</span></div><div class="km-dis"><div class="km-disr"><i class="fa-solid fa-magnifying-glass"></i><span id="d6sym">Symptoms: Sudden wilting, black rotted roots. Avoid overwatering.</span></div><div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span id="d6prev">Prevention: Improve field drainage. Deep plowing with lime reduces pathogens.</span></div></div><button class="add-btn" onclick="event.stopPropagation();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text">Consult AI Doctor</span></button></div></div>

        </div>

        <!-- ━━━ DISEASE DETAIL MODAL ━━━ -->
        <div class="km-modal-overlay" id="diseaseModal" onclick="if(event.target===this)closeDiseaseModal()">
          <div class="km-modal-box" role="dialog" aria-modal="true">
            <button class="km-modal-close" onclick="closeDiseaseModal()" aria-label="Close"><i class="fa-solid fa-xmark"></i></button>
            <div class="km-modal-img"><img id="dmImg" src="" alt=""><span class="km-modal-badge" id="dmBadge"></span></div>
            <div class="km-modal-content">
              <h3 id="dmName"></h3>
              <p class="km-modal-crop" id="dmCrop"></p>
              <div class="km-modal-rem" id="dmRemBox"><span class="km-reml" id="dmRemLbl"></span><span class="km-remv" id="dmRem"></span></div>
              <div class="km-modal-row"><i class="fa-solid fa-magnifying-glass"></i><span id="dmSym"></span></div>
              <div class="km-modal-row"><i class="fa-solid fa-shield-virus"></i><span id="dmPrev"></span></div>
              <button class="add-btn km-modal-cta" onclick="closeDiseaseModal();triggerLeafUpload()"><i class="fa-solid fa-microscope"></i> <span class="btn-text" id="dmCta">Consult AI Doctor</span></button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<style>
/* ══════════════════════════════════════════════
   AGRiCART DESIGN SYSTEM — TOKENS
══════════════════════════════════════════════ */
:root{
  --ac-dark:#0F3D3E;
  --ac-primary:#0E7C7B;
  --ac-secondary:#17B4A6;
  --ac-bg-light:#F0FAF9;
  --ac-card:#FFFFFF;
  --ac-border:#CFE9E6;
  --ac-text:#12292A;
  --ac-text-muted:#4A6462;
  --ac-warning:#B45309;
  --ac-warning-bg:#FFF3D6;
  --ac-danger:#B91C1C;
  --ac-danger-bg:#FDECEC;
  --ac-radius:16px;
  --ac-radius-sm:11px;
  --ac-shadow:0 2px 14px rgba(15,61,62,.07);
  --ac-shadow-hover:0 12px 28px rgba(15,61,62,.13);
  --ac-nav-top:70px;
  --ac-maxw:1240px;
}

/* Hero slider styling comes from the shared site stylesheet — same component as the homepage, no page-specific overrides here. */

/* ── Weather bar ── */
.km-wxbar-outer{background:linear-gradient(90deg,var(--ac-dark),var(--ac-primary) 50%,var(--ac-dark));}
.km-wxbar{display:flex;align-items:center;justify-content:center;flex-wrap:nowrap;overflow-x:auto;max-width:var(--ac-maxw);margin:0 auto;padding:14px 20px;scrollbar-width:none;}
.km-wxbar::-webkit-scrollbar{display:none;}
.km-wxi{display:flex;align-items:center;gap:11px;padding:6px 20px;flex-shrink:0;}
.km-wxi i{font-size:18px;color:#9FE0D9;width:22px;text-align:center;}
.km-wxi b{display:block;font-size:.96rem;font-weight:800;color:#fff;line-height:1.15;}
.km-wxi small{display:block;font-size:.66rem;color:rgba(255,255,255,.68);text-transform:uppercase;letter-spacing:.06em;}
.km-wxd{width:1px;height:34px;background:rgba(255,255,255,.18);flex-shrink:0;}
.km-wxalert i{color:var(--ac-warning);}
.km-wxalert b{color:var(--ac-warning)!important;}
@media(max-width:680px){.km-wxbar{justify-content:flex-start;}.km-wxd{display:none;}.km-wxi{padding:5px 14px;}}

/* ── Sticky nav ── */
.km-snav{display:flex;justify-content:center;overflow-x:auto;gap:6px;background:#fff;padding:11px 16px;border-bottom:1px solid var(--ac-border);position:sticky;top:var(--ac-nav-top);z-index:100;box-shadow:0 2px 14px rgba(15,61,62,.06);scrollbar-width:none;}
.km-snav::-webkit-scrollbar{display:none;}
@media(max-width:768px){.km-snav{justify-content:flex-start;}}
.km-snavb{display:flex;align-items:center;gap:7px;white-space:nowrap;padding:9px 18px;border-radius:99px;font-size:.79rem;font-weight:700;color:var(--ac-text-muted);background:transparent;border:1.5px solid transparent;cursor:pointer;font-family:inherit;transition:all .2s ease;}
.km-snavb:hover{background:var(--ac-bg-light);color:var(--ac-primary);border-color:var(--ac-border);}
.km-snavb.active{background:var(--ac-primary);color:#fff;border-color:var(--ac-primary);box-shadow:0 4px 12px rgba(14,124,123,.3);}
.km-snavb i{font-size:12px;}

/* ── Sections ── */
.km-sec{padding:68px 24px;background:var(--ac-card);}
.km-alt{background:var(--ac-bg-light);}
.km-inner{max-width:var(--ac-maxw);margin:0 auto;}
.km-label{font-size:.72rem;font-weight:800;letter-spacing:.12em;text-transform:uppercase;color:var(--ac-secondary);margin-bottom:10px;}
.km-label-light{color:#9FE0D9;}
.km-hd{display:flex;align-items:center;gap:20px;margin-bottom:36px;}
.km-hd-light h2, .km-hd-light p{color:#fff!important;}
.km-hd-ico{width:58px;height:58px;border-radius:16px;font-size:24px;display:flex;align-items:center;justify-content:center;flex-shrink:0;background:linear-gradient(135deg,var(--ac-secondary),var(--ac-primary));color:#fff;box-shadow:0 6px 18px rgba(14,124,123,.28);}
.km-hd-ico-dark{background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.25);box-shadow:none;}
.km-hd h2{font-size:1.85rem;font-weight:900;color:var(--ac-dark);margin:0 0 5px;letter-spacing:-.01em;}
.km-hd p{color:var(--ac-text-muted);font-size:.9rem;margin:0;}
@media(max-width:640px){.km-sec{padding:40px 18px;}.km-hd h2{font-size:1.5rem;}}

/* ── Card system ── */
.km-card-base,.km-selcard,.km-infocard,.km-stepbody,.km-calcard,.km-statcard,.product-card{
  background:var(--ac-card);border:1px solid var(--ac-border);border-radius:var(--ac-radius);
  transition:transform .25s ease,box-shadow .25s ease,border-color .25s ease;
}

/* ── Selector ── */
.km-selgrid{display:grid;grid-template-columns:1fr 1fr;gap:20px;}
@media(max-width:580px){.km-selgrid{grid-template-columns:1fr;}}
.km-selcard{padding:24px;box-shadow:var(--ac-shadow);}
.km-selcard:focus-within{border-color:var(--ac-primary);box-shadow:0 0 0 4px rgba(14,124,123,.12);}
.km-sellbl{display:flex;align-items:center;gap:8px;font-weight:800;color:var(--ac-primary);font-size:.8rem;text-transform:uppercase;letter-spacing:.05em;margin-bottom:13px;}
.km-selwrap{position:relative;}
.km-selwrap select{width:100%;padding:13px 42px 13px 15px;border:1.5px solid var(--ac-border);border-radius:var(--ac-radius-sm);font-size:.93rem;color:var(--ac-text);background:var(--ac-bg-light);outline:none;cursor:pointer;appearance:none;-webkit-appearance:none;transition:border-color .2s,box-shadow .2s;font-family:inherit;}
.km-selwrap select:focus{border-color:var(--ac-primary);box-shadow:0 0 0 3px rgba(14,124,123,.1);}
.km-sarr{position:absolute;right:15px;top:50%;transform:translateY(-50%);color:var(--ac-primary);font-size:11px;pointer-events:none;}

/* ── Result stat cards ── */
.km-cpanel{margin-top:22px;animation:kmFU .35s ease;}
@keyframes kmFU{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.km-statgrid{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;}
@media(max-width:920px){.km-statgrid{grid-template-columns:repeat(3,1fr);}}
@media(max-width:580px){.km-statgrid{grid-template-columns:repeat(2,1fr);}}
.km-statcard{padding:18px 16px;text-align:left;box-shadow:var(--ac-shadow);}
.km-statcard:hover{transform:translateY(-3px);box-shadow:var(--ac-shadow-hover);}
.km-staticon{width:34px;height:34px;border-radius:9px;background:var(--ac-bg-light);color:var(--ac-primary);display:flex;align-items:center;justify-content:center;font-size:14px;margin-bottom:10px;}
.km-statlbl{display:block;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.06em;color:var(--ac-text-muted);margin-bottom:4px;}
.km-statval{display:block;font-size:.92rem;font-weight:800;color:var(--ac-dark);line-height:1.35;}
.km-cirow-soil{background:var(--ac-bg-light);border:1.5px solid var(--ac-border);border-radius:var(--ac-radius-sm);padding:16px 20px;font-size:.9rem;color:var(--ac-text);line-height:1.6;}

/* ── Table ── */
.km-tblwrap{overflow-x:auto;border-radius:var(--ac-radius);box-shadow:var(--ac-shadow);border:1px solid var(--ac-border);}
.km-tbl{width:100%;border-collapse:collapse;min-width:560px;font-size:.88rem;}
.km-tbl thead tr{background:linear-gradient(90deg,var(--ac-dark),var(--ac-primary));}
.km-tbl th{color:#fff;padding:15px 18px;text-align:left;font-weight:800;font-size:.75rem;text-transform:uppercase;letter-spacing:.06em;}
.km-tbl td{padding:14px 18px;border-bottom:1px solid var(--ac-border);color:var(--ac-text);}
.km-tbl tr:last-child td{border-bottom:none;}
.km-tbl tr:nth-child(even) td{background:var(--ac-bg-light);}
.km-tbl tr:hover td{background:#E7F6F5;}
.km-tblempty{text-align:center;color:#aab5aa;padding:36px;font-style:italic;font-size:.88rem;}
.km-chip{display:inline-block;background:var(--ac-bg-light);border:1px solid var(--ac-border);color:var(--ac-primary);font-weight:700;font-size:.78rem;padding:4px 11px;border-radius:99px;}

/* ── Calendar ── */
.km-calgrid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;}
@media(max-width:820px){.km-calgrid{grid-template-columns:repeat(2,1fr);}}
@media(max-width:460px){.km-calgrid{grid-template-columns:1fr;}}
.km-calcard{overflow:hidden;box-shadow:var(--ac-shadow);position:relative;}
.km-calcard:hover{transform:translateY(-4px);box-shadow:var(--ac-shadow-hover);}
.km-calh{display:flex;align-items:center;justify-content:space-between;padding:13px 16px;color:#fff;font-weight:800;font-size:.78rem;text-transform:uppercase;letter-spacing:.06em;background:linear-gradient(135deg,var(--ac-secondary),var(--ac-primary));}
.km-calcard p{margin:0;padding:14px 16px 17px;font-size:.85rem;color:var(--ac-text);line-height:1.55;background:#fff;}
.km-calnow{border-color:var(--ac-primary)!important;box-shadow:0 0 0 3px rgba(14,124,123,.18)!important;}
.km-calnow .km-calh{background:linear-gradient(135deg,var(--ac-dark),var(--ac-primary));}
.km-calnow .km-calh::after{content:'Current';font-size:.62rem;font-weight:800;background:rgba(255,255,255,.22);padding:3px 9px;border-radius:99px;letter-spacing:.04em;}

/* ── Info/Guide cards (irrigation/harvest) ── */
.km-infogrid{display:grid;grid-template-columns:repeat(2,1fr);gap:20px;}
@media(max-width:580px){.km-infogrid{grid-template-columns:1fr;}}
.km-infocard{overflow:hidden;box-shadow:var(--ac-shadow);padding:0;}
.km-infocard:hover{transform:translateY(-3px);box-shadow:var(--ac-shadow-hover);}
.km-infotop{display:flex;align-items:center;gap:15px;padding:20px 22px 14px;}
.km-infoico{width:44px;height:44px;border-radius:12px;background:var(--ac-bg-light);color:var(--ac-primary);font-size:17px;flex-shrink:0;display:flex;align-items:center;justify-content:center;}
.km-infotop h4{font-size:1rem;font-weight:800;color:var(--ac-dark);margin:0;}
.km-infocard p{padding:0 22px 22px;font-size:.88rem;color:var(--ac-text-muted);line-height:1.7;margin:0;}
.km-infowarn{border-color:#EBBE7A;background:var(--ac-warning-bg);}
.km-infowarn .km-infoico{background:#FBE3B8;color:var(--ac-warning);}

/* ── Sowing steps ── */
.km-steps{max-width:840px;margin:0 auto;position:relative;}
.km-steps::before{content:'';position:absolute;left:27px;top:30px;bottom:30px;width:2px;background:linear-gradient(to bottom,var(--ac-primary),var(--ac-border));}
.km-step{display:flex;align-items:flex-start;gap:24px;padding:12px 0;}
.km-stepnum{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--ac-secondary),var(--ac-dark));color:#fff;font-weight:900;font-size:1.2rem;display:flex;align-items:center;justify-content:center;flex-shrink:0;z-index:1;box-shadow:0 4px 16px rgba(14,124,123,.35);}
.km-stepbody{padding:19px 24px;flex:1;box-shadow:var(--ac-shadow);}
.km-step:hover .km-stepbody{box-shadow:var(--ac-shadow-hover);transform:translateY(-2px);}
.km-stepbody h4{font-size:.97rem;font-weight:800;color:var(--ac-dark);margin:0 0 8px;}
.km-stepbody p{font-size:.87rem;color:var(--ac-text-muted);line-height:1.7;margin:0;}
@media(max-width:600px){.km-steps::before{left:23px;}.km-step{gap:16px;}.km-stepnum{width:46px;height:46px;font-size:1rem;}}

/* ── AI feature section ── */
.km-ai-feature{background:linear-gradient(180deg,var(--ac-dark),#081F1F);padding:68px 24px;}
.km-uploadzone{border:2px dashed rgba(255,255,255,.85);background:rgba(255,255,255,.04);border-radius:var(--ac-radius);padding:34px 20px;text-align:center;cursor:pointer;transition:all .25s ease;margin-bottom:16px;}
.km-uploadzone:hover{background:rgba(255,255,255,.08);border-color:var(--ac-secondary);}
.km-uploadico{font-size:40px;color:var(--ac-secondary);}
.km-uploadzone h4{margin:10px 0 4px;color:#fff;font-size:1rem;font-weight:800;}
.km-uploadzone p{margin:0;color:rgba(255,255,255,.55);font-size:.8rem;}
.km-previewwrap{margin-bottom:14px;}
.km-previewwrap img{max-width:100%;border-radius:12px;border:2px solid var(--ac-secondary);}
.km-scanstatus{padding:13px;border-radius:var(--ac-radius-sm);font-weight:700;font-size:.83rem;margin-bottom:6px;}
.km-scanresult{display:flex;flex-direction:column;gap:12px;margin-bottom:16px;}
.km-scanresult-card{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);border-radius:var(--ac-radius-sm);padding:14px 16px;}
.km-scanresult-top{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px;flex-wrap:wrap;}
.km-scanresult-name{font-weight:800;color:#fff;font-size:.95rem;}
.km-scanresult-conf{font-size:.72rem;font-weight:800;color:var(--ac-secondary);background:rgba(255,255,255,.08);padding:3px 10px;border-radius:99px;text-transform:uppercase;letter-spacing:.04em;}
.km-scanresult .km-disr{color:rgba(255,255,255,.78);}
.km-scanresult .km-disr i{color:var(--ac-secondary);}

/* ── Disease card extras ── */
.km-disease-card{overflow:hidden;padding:0;box-shadow:var(--ac-shadow);cursor:pointer;}
.km-disease-card{position:relative;}
.km-disease-card:hover{transform:translateY(-4px);box-shadow:var(--ac-shadow-hover);}
.km-disease-card:focus-visible{outline:2px solid var(--ac-primary);outline-offset:2px;}
.km-disease-card .product-badge{position:absolute!important;top:14px;left:14px;display:inline-block!important;width:auto!important;margin:0!important;border-radius:99px!important;padding:5px 13px!important;font-size:.68rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.25);z-index:2;}
.km-diseaseico{padding:0;position:relative;}
.km-diseaseico img{width:100%;height:150px;object-fit:cover;display:block;}
.km-diseaseico::after{content:'';position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 60%,rgba(0,0,0,.28) 100%);pointer-events:none;}
.km-disease-card .product-body{padding:0 14px 14px;display:flex;flex-direction:column;height:100%;}
.km-disease-card .add-btn{margin-top:auto;}
.km-disease-card .product-name{font-size:.9rem;font-weight:800;color:var(--ac-dark);margin-bottom:2px;margin-top:10px;}
.km-disease-card .product-unit{font-size:.72rem;color:var(--ac-text-muted);margin-bottom:5px;}
.km-rem{background:var(--ac-bg-light);border-left:3px solid var(--ac-primary);border-radius:9px;padding:6px 10px;margin:5px 0;}
.km-reml{display:block;font-size:.64rem;text-transform:uppercase;font-weight:800;color:#7A9492;letter-spacing:.06em;margin-bottom:1px;}
.km-remv{font-size:.8rem;color:var(--ac-dark);font-weight:700;}
.km-dis{display:flex;flex-direction:column;gap:3px;margin:4px 0 8px;}
.km-disr{display:flex;gap:6px;align-items:flex-start;font-size:.73rem;color:var(--ac-text-muted);}
.km-disr i{color:var(--ac-primary);margin-top:3px;flex-shrink:0;font-size:9px;}
.km-disease-card .km-disr span{display:-webkit-box;-webkit-line-clamp:1;-webkit-box-orient:vertical;overflow:hidden;}
.km-disease-card .add-btn{width:100%;background:linear-gradient(135deg,var(--ac-secondary),var(--ac-primary));border:none;border-radius:11px;padding:9px;font-weight:800;color:#fff;cursor:pointer;font-family:inherit;transition:transform .2s,opacity .2s;font-size:.86rem;}
.km-disease-card .add-btn:hover{transform:translateY(-2px);opacity:.94;}

/* ── Disease detail modal ── */
.km-modal-overlay{display:none;position:fixed;inset:0;background:rgba(6,20,20,.62);backdrop-filter:blur(3px);z-index:9999;align-items:center;justify-content:center;padding:20px;}
.km-modal-overlay.active{display:flex;animation:kmModalFade .2s ease;}
@keyframes kmModalFade{from{opacity:0}to{opacity:1}}
.km-modal-box{background:var(--ac-card);border-radius:var(--ac-radius);max-width:480px;width:100%;max-height:88vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,.35);animation:kmModalUp .25s ease;position:relative;}
@keyframes kmModalUp{from{opacity:0;transform:translateY(18px) scale(.98)}to{opacity:1;transform:none}}
.km-modal-close{position:absolute;top:12px;right:12px;width:34px;height:34px;border-radius:50%;background:rgba(0,0,0,.45);color:#fff;border:none;font-size:15px;cursor:pointer;display:flex;align-items:center;justify-content:center;z-index:3;transition:background .18s;}
.km-modal-close:hover{background:rgba(0,0,0,.7);}
.km-modal-img{position:relative;}
.km-modal-img img{width:100%;height:220px;object-fit:cover;display:block;border-radius:var(--ac-radius) var(--ac-radius) 0 0;}
.km-modal-badge{position:absolute;top:14px;left:14px;border-radius:99px;padding:6px 15px;font-size:.72rem;font-weight:800;text-transform:uppercase;letter-spacing:.05em;color:#fff;box-shadow:0 2px 8px rgba(0,0,0,.25);}
.km-modal-content{padding:20px 22px 24px;}
.km-modal-content h3{font-size:1.25rem;font-weight:900;color:var(--ac-dark);margin:0 0 4px;}
.km-modal-crop{font-size:.85rem;color:var(--ac-text-muted);margin:0 0 14px;}
.km-modal-rem{background:var(--ac-bg-light);border-left:3px solid var(--ac-primary);border-radius:9px;padding:10px 14px;margin:0 0 14px;}
.km-modal-row{display:flex;gap:10px;align-items:flex-start;font-size:.87rem;color:var(--ac-text);line-height:1.6;margin-bottom:12px;}
.km-modal-row i{color:var(--ac-primary);margin-top:4px;flex-shrink:0;font-size:13px;}
.km-modal-cta{width:100%;margin-top:6px;padding:13px;font-size:.92rem;border-radius:12px;}
@media(max-width:480px){.km-modal-img img{height:170px;}.km-modal-content{padding:18px;}}

/* ── Sidebar extras ── */
.km-sbhd{display:flex;align-items:center;gap:8px;font-size:.78rem;font-weight:900;color:var(--ac-secondary);text-transform:uppercase;letter-spacing:.06em;margin-bottom:13px;}
.km-schemes{display:flex;flex-direction:column;gap:8px;}
.km-scheme{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.05);border:1.5px solid rgba(255,255,255,.14);border-radius:var(--ac-radius-sm);padding:11px 13px;font-size:.81rem;color:#E4F5F3;text-decoration:none;font-weight:600;transition:all .18s ease;}
.km-scheme:hover{background:rgba(255,255,255,.1);border-color:var(--ac-secondary);transform:translateX(3px);}
.km-scheme i:first-child{font-size:13px;flex-shrink:0;color:var(--ac-secondary);}
.km-scheme span{flex:1;}
.km-sext{font-size:10px;color:rgba(255,255,255,.35);}
.km-tipbox{background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.16);border-radius:var(--ac-radius-sm);padding:15px;font-size:.85rem;color:#F2FBFA;line-height:1.7;min-height:80px;}
.km-tipbox strong{color:#FFFFFF;}
.km-tip{display:none;animation:kmFU .3s ease;}.km-tip.active{display:block;}
.km-tipnav{display:flex;align-items:center;justify-content:space-between;margin-top:11px;}
.km-tnav{background:rgba(255,255,255,.08);border:1.5px solid rgba(255,255,255,.2);border-radius:9px;width:34px;height:34px;cursor:pointer;font-size:1.3rem;color:#fff;font-weight:700;display:flex;align-items:center;justify-content:center;line-height:1;font-family:inherit;transition:background .18s;}
.km-tnav:hover{background:rgba(255,255,255,.18);}
.km-tdots{display:flex;gap:6px;}
.km-tdot{width:7px;height:7px;border-radius:50%;background:rgba(255,255,255,.25);transition:all .25s;}
.km-tdot.active{background:var(--ac-secondary);transform:scale(1.4);}
.km-callbtn{width:100%;margin-top:11px;background:linear-gradient(135deg,var(--ac-secondary),var(--ac-primary));color:#fff;border:none;border-radius:var(--ac-radius-sm);padding:12px;font-size:.87rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;font-family:inherit;transition:opacity .2s,transform .18s;}
.km-callbtn:hover{opacity:.9;transform:translateY(-2px);}
.km-dlbtn{width:100%;background:linear-gradient(135deg,var(--ac-secondary),var(--ac-dark));color:#fff;border:none;border-radius:13px;padding:14px;font-size:.9rem;font-weight:800;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:9px;font-family:inherit;box-shadow:0 6px 18px rgba(0,0,0,.28);transition:opacity .2s,transform .18s;}
.km-dlbtn:hover{opacity:.93;transform:translateY(-2px);}
.km-dlbtn:disabled{opacity:.55;transform:none;cursor:not-allowed;}
.km-ai-sidebar .sidebar-section{border-top:1px solid rgba(255,255,255,.1);}
.km-ai-sidebar{background:transparent!important;box-shadow:none!important;border:none!important;}
.km-ai-sidebar .km-sbhd{color:#7FDDD4;}

/* ── AI feature area light content ── */
.km-ai-feature .offer-strip{background:linear-gradient(135deg,#FBE3B8,#F5C77E);border:1px solid #E0A94A;color:#4A2E00;border-radius:var(--ac-radius);}
.km-ai-feature .offer-strip *{color:#4A2E00!important;}
.km-ai-feature .offer-strip i{color:#B45309!important;}
.km-ai-feature .filter-bar-left{color:var(--ac-dark);}
.km-ai-feature .filter-bar-left *{color:var(--ac-dark)!important;}
.km-ai-feature .filter-bar{display:flex;align-items:center;min-height:52px;padding:0 20px;}
.km-ai-feature .filter-bar-left{display:flex;align-items:center;gap:6px;line-height:1.3;}
.km-ai-feature .products-grid .product-card{background:#fff;}

/* ── Responsive overflow guard ── */
html,body{overflow-x:hidden;}
img{max-width:100%;}
</style>

<script>
/* Backend endpoint for the AI leaf scanner — place leaf-diagnose.php at this path on your server
   (see the file comment in leaf-diagnose.php for setup instructions). Uses the same $base_path
   variable this page already uses for asset URLs, so it should resolve correctly out of the box. */
const KM_LEAF_API_URL = "<?php echo $base_path; ?>/leaf-diagnose.php";

/* ══════════════════════════════════════════════
   COMPLETE TRANSLATIONS — EN / MR / HI
   Every visible string on the page is covered.
══════════════════════════════════════════════ */
const AdvisoryT = {
en:{
  heroBadge:"Crop Advisory",heroTitle:"AI-Powered Crop Doctor",
  heroSub:"Upload a photo of your infected crop leaf to get instant machine-learning diagnostics.",
  s1btn:"Scan Your Crop",
  s4tag:"Crop & Soil Advisory",s4h:"Smart Crop & Soil Guidance",
  s4p:"Get soil health insights and crop recommendations tailored to your field.",s4btn:"Get Crop Advisory",
  s3tag:"Fertilizer Advisory",s3h:"Smart Fertilizer Recommendation",
  s3p:"Know exactly which nutrients and fertilizer dose your soil needs.",s3btn:"View Recommendation",
  s5tag:"Weather & Irrigation",s5h:"Weather & Irrigation Monitoring",
  s5p:"Track weather, soil moisture and irrigation schedules in real time.",s5btn:"Check Advisory",
  s6tag:"Pest & Disease Alert",s6h:"Pest & Disease Early Warning",
  s6p:"Get early alerts on pest attacks and crop diseases with drone-based scanning.",s6btn:"View Alerts",
  wxlTemp:"Temperature",wxlHum:"Humidity",wxlRain:"Rainfall (7d)",wxlWind:"Wind",
  wxRisk:"Mildew Risk",wxlRisk:"Disease Alert",riskHigh:"High",riskModerate:"Moderate",riskLow:"Low",wxLoc:"Palghar, MH",wxlLoc:"Region",
  sn1:"Crop & Soil",sn2:"Fertilizer",sn3:"Calendar",sn4:"Irrigation",sn5:"Sowing",sn6:"Harvest",sn7:"AI Doctor",
  cropSelTitle:"Crop & Soil Selector",cropSelSub:"Select your crop and soil type to get personalised advisory",
  lblCrop:"Select Crop",lblSoil:"Select Soil Type",
  oCropDef:"-- Select Crop --",oSoilDef:"-- Select Soil --",
  oRice:"🌾 Rice (Paddy)",oWheat:"🍞 Wheat",oCotton:"🌿 Cotton",oSoy:"🟢 Soyabean",
  oTomato:"🍅 Tomato",oOnion:"🧅 Onion",oPotato:"🥔 Potato",oChilli:"🌶️ Chilli",oSugar:"🎋 Sugarcane",oPomeg:"🍎 Pomegranate",
  oBlack:"⚫ Black Cotton Soil",oRed:"🔴 Red Laterite Soil",oAlluvial:"🟡 Alluvial Soil",oSandy:"🏜️ Sandy Soil",oLoamy:"🟤 Loamy Soil",
  ciSeason:"Season",ciDur:"Duration",ciYield:"Avg Yield",ciWater:"Water Need",ciSoil:"Best Soil",
  fertTitle:"Fertilizer Recommendation",fertSub:"Select your crop above to get the fertilizer schedule",
  fH1:"Growth Stage",fH2:"Nutrient",fH3:"Dose / Acre",fH4:"Timing",fH5:"Method",
  fertEmpty:"Select a crop above to see fertilizer recommendations",
  calTitle:"Crop Calendar",calSub:"Month-wise farming activities at a glance",
  calMJun:"June",calMJul:"July",calMAug:"August",calMSep:"September",
  calMOct:"October",calMNov:"November",calMDec:"December",calMJan:"January",
  calJun:"Land Preparation & Sowing",calJul:"1st Fertilizer Application",
  calAug:"Irrigation & Weed Control",calSep:"Pest & Disease Monitoring",
  calOct:"2nd Fertilizer Dose",calNov:"Harvest Preparation",calDec:"Harvesting",calJan:"Storage & Market",
  irrTitle:"Irrigation Guide",irrSub:"Optimal irrigation schedule based on crop & soil type",
  irrT1:"Drip Irrigation",irrX1:"Recommended for Cotton, Tomato, Chilli. Apply 3–4 litres/plant/day during flowering stage.",
  irrT2:"Flood Irrigation",irrX2:"Suitable for Rice & Sugarcane. Maintain 5 cm standing water in Kharif season fields.",
  irrT3:"Best Timing",irrX3:"Irrigate 6–8 AM or 5–7 PM to reduce evaporation by 30%. Avoid midday irrigation completely.",
  irrT4:"Critical Stages",irrX4:"Never let soil dry during flowering & grain-filling. Water stress here can reduce yield by 40%.",
  sowTitle:"Sowing Guide",sowSub:"Variety selection, seed treatment & sowing best practices",
  sowT1:"Seed Selection",sowX1:"Choose certified seeds from approved varieties. For Rice: IR-64, Swarna. For Cotton: BT Hybrid varieties. Always check seed germination rate — minimum 85%.",
  sowT2:"Seed Treatment",sowX2:"Treat seeds with Trichoderma viride (4 g/kg) + Carbendazim (2 g/kg) to prevent soil-borne diseases. Dry in shade for 30 min before sowing.",
  sowT3:"Sowing Depth & Spacing",sowX3:"Rice: 2–3 cm depth, 20×15 cm spacing. Cotton: 4–5 cm, 90×60 cm. Wheat: 4–6 cm, 22.5 cm row spacing. Follow recommended plant population per hectare.",
  sowT4:"Sowing Time",sowX4:"Kharif: June 15 – July 15. Rabi: October 15 – November 30. Soil temperature should be 20–30°C for best germination results.",
  harvTitle:"Harvest Guide",harvSub:"Maturity indicators, harvesting methods & post-harvest tips",
  harvT1:"Maturity Indicators",harvX1:"Rice: 80% grains turn golden, 25–30% moisture. Cotton: 60–70% boll opening. Wheat: golden yellow, hard grain stage.",
  harvT2:"Harvesting Method",harvX2:"Use combine harvester for Wheat & Rice. Manual picking for Cotton (3–4 pickings). Harvest in dry weather to reduce post-harvest losses.",
  harvT3:"Post-Harvest Storage",harvX3:"Dry grains to 12–14% moisture. Use hermetic bags or silos. Apply aluminium phosphide fumigation to prevent pest damage in storage.",
  harvT4:"Market Timing",harvX4:"Check MSP (Minimum Support Price) before selling. Register on e-NAM portal for best price. Avoid distress selling immediately after harvest.",
  scanDashTitle:"AI Crop Scanner",aiSub:"Upload a leaf photo for an instant simulated diagnosis",
  uploadTitle:"Upload Leaf Image",uploadSub:"Supports JPG, PNG formats",
  analyzeMsg1:"⏳ AI analyzing leaf photo...",
  scanErrorMsg:"⚠️ Could not analyze the image. Please try again.",
  scanConfigMsg:"⚠️ AI scanner is not configured on the server yet.",
  scanNotPlantMsg:"⚠️ This doesn't look like a plant/leaf photo. Please upload a clear leaf image.",
  scanHealthyMsg:"✅ Good news — this leaf looks healthy! No disease detected.",
  scanFoundMsg:"🔬 Possible issue(s) found — review details below.",
  scanConfidence:"Confidence",scanDescLbl:"About",scanTreatBioLbl:"Organic / Biological Treatment",scanTreatChemLbl:"Chemical Treatment",scanPreventionLbl:"Prevention",
  remLbl:"Organic Remedy",
  badgeFungal:"Fungal",badgeBacterial:"Bacterial",badgeViral:"Viral",badgePest:"Pest",
  dmCta:"Consult AI Doctor",
  d1Name:"Powdery Mildew",d1Crop:"Target Crops: Cotton, Tomato",d1Rem:"Spray 1% Neem Oil or Elemental Sulfur",d1sym:"Symptoms: White powder on leaves, stunted growth, leaf curl.",d1prev:"Prevention: Avoid dense planting. Spray sulfur before monsoon.",
  d2Name:"Bacterial Blight",d2Crop:"Target Crops: Rice, Pomegranate",d2Rem:"Copper Oxychloride (2g/Litre of water)",d2sym:"Symptoms: Water-soaked edges turning brown, wilting of young shoots.",d2prev:"Prevention: Resistant varieties. Avoid excess nitrogen. Drain fields in rain.",
  d3Name:"Yellow Mosaic Virus",d3Crop:"Target Crops: Chilli, Soyabean",d3Rem:"Control Whiteflies using Imidacloprid sticky traps",d3sym:"Symptoms: Yellow-green mosaic pattern on leaves, stunted plant growth.",d3prev:"Prevention: Remove infected plants immediately. Spray Imidacloprid 17.8SL.",
  d4Name:"Late Blight",d4Crop:"Target Crops: Potato, Tomato",d4Rem:"Mancozeb 75WP (2.5g/Litre)",d4sym:"Symptoms: Black-brown leaf edges. Spreads fast in cold & humid weather.",d4prev:"Prevention: Avoid overhead irrigation. Space plants 60 cm apart.",
  d5Name:"Stem Borer",d5Crop:"Target Crops: Wheat, Rice, Sugarcane",d5Rem:"Chlorpyrifos 2% granules or Coragen spray",d5sym:"Symptoms: Holes in stem, yellowing leaves. Monitor from 30 DAS.",d5prev:"Prevention: Pheromone traps. Remove and burn infected stems.",
  d6Name:"Root Rot",d6Crop:"Target Crops: Soyabean, Cotton, Onion",d6Rem:"Trichoderma viride seed treatment",d6sym:"Symptoms: Sudden wilting, black rotted roots. Avoid overwatering.",d6prev:"Prevention: Improve field drainage. Deep plowing with lime reduces pathogens.",
  gridOfferHeading:"High Alert: {loc} Region",gridOfferSub:"Humid weather conditions are increasing the risk of Powdery Mildew in Cotton crops this week. Take preventive measures!",
  guidesAvail:"Expert Treatment Handbooks Available",
  govTitle:"Government Schemes",
  sch1:"PM Fasal Bima Yojana",sch2:"PM-KISAN Scheme",sch3:"Soil Health Card",sch4:"e-NAM Market Portal",sch5:"mKisan SMS Portal",
  expertTitle:"Expert Advice",
  tip1lbl:"💡 Tip of the Day:",tip1txt:"Always rotate crops every 2–3 years to prevent soil nutrient depletion and reduce pest buildup.",
  tip2lbl:"💧 Water Tip:",tip2txt:"Mulching around plants reduces water requirement by up to 50% and suppresses weed growth naturally.",
  tip3lbl:"🔬 Soil Tip:",tip3txt:"Get your soil tested before Kharif season. pH 6.5–7.5 is ideal for most Maharashtra crops.",
  callLbl:"Call Kisan Helpline",dlTxt:"Download Report PDF",btnText:"Consult AI Doctor",
  stBlack:"Black cotton soil retains moisture well. Reduce irrigation frequency. Ideal for Cotton, Soyabean, Wheat.",
  stRed:"Red laterite soil has low water retention. Use organic manure. Suitable for Chilli, Groundnut.",
  stAlluvial:"Alluvial soil is highly fertile. Best for Rice, Wheat, Sugarcane. Ensure good drainage.",
  stSandy:"Sandy soil drains fast. Apply mulch & drip irrigation. Add FYM 5–6 tonnes/acre.",
  stLoamy:"Loamy soil is ideal for most crops. Balanced water retention & drainage."
},
mr:{
  heroBadge:"पीक सल्ला केंद्र",heroTitle:"एआय-आधारित पीक डॉक्टर",
  heroSub:"त्वरित मशिन लर्निंग रोग निदानासाठी तुमच्या बाधित पिकाच्या पानाचा फोटो अपलोड करा.",
  s1btn:"तुमचे पीक स्कॅन करा",
  s4tag:"पीक व माती सल्ला",s4h:"स्मार्ट पीक व माती मार्गदर्शन",
  s4p:"तुमच्या शेतासाठी माती आरोग्य विश्लेषण आणि योग्य पीक शिफारसी मिळवा.",s4btn:"पीक सल्ला मिळवा",
  s3tag:"खत सल्ला",s3h:"स्मार्ट खत शिफारस",
  s3p:"तुमच्या मातीला नेमके कोणते पोषकद्रव्य आणि खताची मात्रा हवी आहे ते जाणून घ्या.",s3btn:"शिफारस पहा",
  s5tag:"हवामान व सिंचन",s5h:"हवामान व सिंचन निरीक्षण",
  s5p:"हवामान, मातीतील ओलावा आणि सिंचन वेळापत्रक रिअल टाइममध्ये तपासा.",s5btn:"सल्ला तपासा",
  s6tag:"कीड व रोग सूचना",s6h:"कीड व रोग लवकर इशारा",
  s6p:"ड्रोन-आधारित स्कॅनिंगद्वारे कीड व रोगांच्या हल्ल्याबाबत लवकर सूचना मिळवा.",s6btn:"इशारे पहा",
  wxlTemp:"तापमान",wxlHum:"आर्द्रता",wxlRain:"पाऊस (७ दिवस)",wxlWind:"वारा",
  wxRisk:"भुरी रोग धोका",wxlRisk:"रोग अलर्ट",riskHigh:"जास्त",riskModerate:"मध्यम",riskLow:"कमी",wxLoc:"पालघर, महा.",wxlLoc:"क्षेत्र",
  sn1:"पीक व माती",sn2:"खत",sn3:"कॅलेंडर",sn4:"सिंचन",sn5:"पेरणी",sn6:"काढणी",sn7:"एआय डॉक्टर",
  cropSelTitle:"पीक आणि माती निवड",cropSelSub:"वैयक्तिक सल्ल्यासाठी तुमचे पीक आणि मातीचा प्रकार निवडा",
  lblCrop:"पीक निवडा",lblSoil:"मातीचा प्रकार निवडा",
  oCropDef:"-- पीक निवडा --",oSoilDef:"-- माती निवडा --",
  oRice:"🌾 भात (तांदूळ)",oWheat:"🍞 गहू",oCotton:"🌿 कापूस",oSoy:"🟢 सोयाबीन",
  oTomato:"🍅 टोमॅटो",oOnion:"🧅 कांदा",oPotato:"🥔 बटाटा",oChilli:"🌶️ मिरची",oSugar:"🎋 ऊस",oPomeg:"🍎 डाळिंब",
  oBlack:"⚫ काळी कापूस माती",oRed:"🔴 लाल लॅटेराइट माती",oAlluvial:"🟡 गाळाची माती",oSandy:"🏜️ वाळूची माती",oLoamy:"🟤 चिकट माती",
  ciSeason:"हंगाम",ciDur:"कालावधी",ciYield:"सरासरी उत्पादन",ciWater:"पाण्याची गरज",ciSoil:"योग्य माती",
  fertTitle:"खत शिफारस",fertSub:"खत वेळापत्रकासाठी वर तुमचे पीक निवडा",
  fH1:"वाढीचा टप्पा",fH2:"पोषक तत्व",fH3:"मात्रा / एकर",fH4:"वेळ",fH5:"पद्धत",
  fertEmpty:"खत शिफारसी पाहण्यासाठी वर पीक निवडा",
  calTitle:"पीक कॅलेंडर",calSub:"महिना-निहाय शेती कामांचे कॅलेंडर",
  calMJun:"जून",calMJul:"जुलै",calMAug:"ऑगस्ट",calMSep:"सप्टेंबर",
  calMOct:"ऑक्टोबर",calMNov:"नोव्हेंबर",calMDec:"डिसेंबर",calMJan:"जानेवारी",
  calJun:"जमीन तयारी आणि पेरणी",calJul:"पहिली खत फवारणी",
  calAug:"सिंचन आणि तण नियंत्रण",calSep:"कीड आणि रोग निरीक्षण",
  calOct:"दुसरी खत मात्रा",calNov:"काढणीची तयारी",calDec:"काढणी",calJan:"साठवण आणि बाजार",
  irrTitle:"सिंचन मार्गदर्शन",irrSub:"पीक आणि मातीच्या प्रकारानुसार इष्टतम सिंचन वेळापत्रक",
  irrT1:"ठिबक सिंचन",irrX1:"कापूस, टोमॅटो, मिरचीसाठी शिफारसीय. फुलोऱ्याच्या अवस्थेत ३–४ लीटर/झाड/दिवस द्या.",
  irrT2:"पूर सिंचन",irrX2:"भात आणि उसासाठी योग्य. खरीप हंगामात शेतात ५ सें.मी. उभे पाणी ठेवा.",
  irrT3:"सर्वोत्तम वेळ",irrX3:"सकाळी ६–८ किंवा संध्याकाळी ५–७ वाजता सिंचन करा. बाष्पीभवन ३०% कमी होते.",
  irrT4:"गंभीर अवस्था",irrX4:"फुलोरा व दाणे भरण्याच्या वेळी माती कोरडी पडू देऊ नका. उत्पादन ४०% कमी होण्याचा धोका.",
  sowTitle:"पेरणी मार्गदर्शन",sowSub:"वाण निवड, बीज प्रक्रिया आणि पेरणीचे उत्तम उपाय",
  sowT1:"बीज निवड",sowX1:"प्रमाणित बियाणे निवडा. भातासाठी: IR-64, स्वर्णा. कापसासाठी: BT हायब्रीड. उगवण क्षमता किमान ८५% असावी.",
  sowT2:"बीज प्रक्रिया",sowX2:"Trichoderma viride (४ ग्रॅ/किलो) + Carbendazim (२ ग्रॅ/किलो) ने बीज प्रक्रिया करा. पेरणीआधी ३० मिनिटे सावलीत वाळवा.",
  sowT3:"पेरणी खोली आणि अंतर",sowX3:"भात: २–३ सें.मी. खोली, २०×१५ सें.मी. अंतर. कापूस: ४–५ सें.मी., ९०×६० सें.मी. गहू: ४–६ सें.मी., ओळीत २२.५ सें.मी.",
  sowT4:"पेरणीची वेळ",sowX4:"खरीप हंगाम: १५ जून – १५ जुलै. रब्बी हंगाम: १५ ऑक्टोबर – ३० नोव्हेंबर. जमिनीचे तापमान २०–३०°C असावे.",
  harvTitle:"काढणी मार्गदर्शन",harvSub:"परिपक्वता निर्देशक, काढणी पद्धती आणि काढणीनंतरच्या टिप्स",
  harvT1:"परिपक्वता निर्देशक",harvX1:"भात: ८०% दाणे सोनेरी, २५–३०% ओलावा. कापूस: ६०–७०% बोंडे उघडणे. गहू: सोनेरी पिवळा, कठीण दाणा.",
  harvT2:"काढणी पद्धत",harvX2:"गहू व भातासाठी कंबाइन हार्वेस्टर वापरा. कापसाची हाताने वेचणी (३–४ वेळा). कोरड्या हवामानात काढणी करा.",
  harvT3:"काढणीनंतर साठवण",harvX3:"धान्य १२–१४% ओलाव्यापर्यंत वाळवा. हर्मेटिक पिशव्या किंवा सायलो वापरा. अॅल्युमिनियम फॉस्फाइड धुरी करा.",
  harvT4:"बाजार वेळ",harvX4:"विक्रीपूर्वी MSP तपासा. e-NAM पोर्टलवर नोंदणी करा. काढणीनंतर लगेच संकटातील विक्री टाळा.",
  scanDashTitle:"एआय क्रॉप स्कॅनर",aiSub:"तात्काळ सिम्युलेटेड निदानासाठी पानाचा फोटो अपलोड करा",
  uploadTitle:"पानाचा फोटो अपलोड करा",uploadSub:"JPG, PNG फाईल्स स्वीकारल्या जातात",
  analyzeMsg1:"⏳ एआय पानाचे विश्लेषण करत आहे...",
  scanErrorMsg:"⚠️ फोटोचे विश्लेषण करता आले नाही. कृपया पुन्हा प्रयत्न करा.",
  scanConfigMsg:"⚠️ एआय स्कॅनर अजून सर्व्हरवर सेट केलेला नाही.",
  scanNotPlantMsg:"⚠️ हा पान/पिकाचा फोटो वाटत नाही. कृपया स्पष्ट पानाचा फोटो अपलोड करा.",
  scanHealthyMsg:"✅ चांगली बातमी — हे पान निरोगी दिसत आहे! कोणताही रोग आढळला नाही.",
  scanFoundMsg:"🔬 संभाव्य समस्या आढळली — खाली तपशील पहा.",
  scanConfidence:"विश्वासार्हता",scanDescLbl:"माहिती",scanTreatBioLbl:"सेंद्रिय उपचार",scanTreatChemLbl:"रासायनिक उपचार",scanPreventionLbl:"प्रतिबंध",
  remLbl:"सेंद्रिय उपाय",
  badgeFungal:"बुरशीजन्य",badgeBacterial:"जिवाणूजन्य",badgeViral:"विषाणूजन्य",badgePest:"कीड",
  dmCta:"एआय डॉक्टरांचा सल्ला घ्या",
  d1Name:"पावडरी मिल्ड्यू (भुरी रोग)",d1Crop:"बाधित पिके: कापूस, टोमॅटो",d1Rem:"१% कडुनिंब तेल किंवा गंधकाची फवारणी करा",d1sym:"लक्षणे: पानांवर पांढरी पावडर, वाढ खुंटणे, पान वळणे.",d1prev:"प्रतिबंध: घनदाट लागवड टाळा. मान्सूनआधी गंधकाची फवारणी करा.",
  d2Name:"बॅक्टेरियल ब्लाईट (करपा)",d2Crop:"बाधित पिके: भात, डाळिंब",d2Rem:"कॉपर ऑक्सीक्लोराईड (२ ग्रॅम/लीटर पाणी)",d2sym:"लक्षणे: पानांच्या कडा तपकिरी पडणे, कोवळे कोंब कोमेजणे.",d2prev:"प्रतिबंध: प्रतिकारक वाण वापरा. नत्र जास्त देऊ नका.",
  d3Name:"येलो मोझॅक व्हायरस",d3Crop:"बाधित पिके: मिरची, सोयाबीन",d3Rem:"पिवळे चिकट सापळे — पांढरी माशी नियंत्रण",d3sym:"लक्षणे: पानांवर पिवळे-हिरवे मोझॅक, रोप खुंटणे.",d3prev:"प्रतिबंध: बाधित झाडे काढा. Imidacloprid 17.8SL फवारा.",
  d4Name:"लेट ब्लाईट",d4Crop:"बाधित पिके: बटाटा, टोमॅटो",d4Rem:"Mancozeb 75WP (२.५ ग्रॅम/लीटर) फवारणी करा",d4sym:"लक्षणे: पानांच्या कडा काळ्या-तपकिरी. थंड व दमट हवामानात वेगाने पसरतो.",d4prev:"प्रतिबंध: वरून पाणी देणे टाळा. झाडांमध्ये ६० सें.मी. अंतर ठेवा.",
  d5Name:"स्टेम बोरर (खोड कीड)",d5Crop:"बाधित पिके: गहू, भात, ऊस",d5Rem:"Chlorpyrifos 2% दाणे किंवा Coragen फवारणी",d5sym:"लक्षणे: खोडात छिद्र, पाने पिवळी पडणे. पेरणीनंतर ३० दिवसांनी निगराणी.",d5prev:"प्रतिबंध: फेरोमोन सापळे लावा. बाधित खोडे जाळून टाका.",
  d6Name:"रूट रॉट (मूळ कुजणे)",d6Crop:"बाधित पिके: सोयाबीन, कापूस, कांदा",d6Rem:"Trichoderma viride ने बीज प्रक्रिया करा",d6sym:"लक्षणे: झाड अचानक कोमेजणे, काळ्या सडलेल्या मुळे.",d6prev:"प्रतिबंध: चांगला निचरा करा. चुन्यासह खोल नांगरणी करा.",
  gridOfferHeading:"हाय अलर्ट: {loc} क्षेत्र",gridOfferSub:"या आठवड्यात दमट हवामानामुळे कापसात Powdery Mildew चा धोका वाढलाय. त्वरित प्रतिबंधात्मक उपाय करा!",
  guidesAvail:"तज्ज्ञ उपचार मार्गदर्शिका उपलब्ध",
  govTitle:"सरकारी योजना",
  sch1:"पीएम फसल बीमा योजना",sch2:"पीएम-किसान योजना",sch3:"माती आरोग्य कार्ड",sch4:"ई-नाम बाजार पोर्टल",sch5:"एमकिसान SMS पोर्टल",
  expertTitle:"तज्ज्ञ सल्ला",
  tip1lbl:"💡 आजचा सल्ला:",tip1txt:"दर २–३ वर्षांनी पीक बदल करा. जमिनीतील पोषण कमी होणे आणि कीड वाढणे टाळता येते.",
  tip2lbl:"💧 पाणी टिप:",tip2txt:"झाडांभोवती मल्चिंग केल्यास पाण्याची गरज ५०% पर्यंत कमी होते आणि तण नियंत्रण होते.",
  tip3lbl:"🔬 माती टिप:",tip3txt:"खरीप हंगामापूर्वी माती परीक्षण करा. pH ६.५–७.५ महाराष्ट्रातील पिकांसाठी आदर्श.",
  callLbl:"किसान हेल्पलाइनला कॉल करा",dlTxt:"PDF अहवाल डाउनलोड करा",btnText:"एआय डॉक्टरांचा सल्ला घ्या",
  stBlack:"काळी कापूस माती ओलावा चांगला टिकवते. सिंचन वारंवारता कमी करा. कापूस, सोयाबीन, गव्हासाठी उत्तम.",
  stRed:"लाल माती पाणी कमी टिकवते. सेंद्रिय खत वापरा. मिरची, भुईमूगासाठी योग्य.",
  stAlluvial:"गाळाची माती अत्यंत सुपीक. भात, गहू, उसासाठी सर्वोत्तम. चांगला निचरा असावा.",
  stSandy:"वाळूची माती लवकर निचरा करते. मल्चिंग व ठिबक सिंचन करा. FYM ५–६ टन/एकर द्या.",
  stLoamy:"चिकट माती बहुतेक पिकांसाठी आदर्श. पाणी टिकवण्याची व निचऱ्याची समतोल क्षमता."
},
hi:{
  heroBadge:"फसल सलाह केंद्र",heroTitle:"AI-आधारित फसल डॉक्टर",
  heroSub:"तत्काल मशीन लर्निंग रोग निदान के लिए अपनी संक्रमित फसल की पत्ती की फोटो अपलोड करें.",
  s1btn:"अपनी फसल स्कैन करें",
  s4tag:"फसल और मिट्टी सलाह",s4h:"स्मार्ट फसल और मिट्टी मार्गदर्शन",
  s4p:"अपने खेत के लिए मिट्टी स्वास्थ्य विश्लेषण और सही फसल सिफारिशें प्राप्त करें.",s4btn:"फसल सलाह प्राप्त करें",
  s3tag:"उर्वरक सलाह",s3h:"स्मार्ट उर्वरक सिफारिश",
  s3p:"जानें आपकी मिट्टी को किन पोषक तत्वों और उर्वरक की कितनी मात्रा की जरूरत है.",s3btn:"सिफारिश देखें",
  s5tag:"मौसम व सिंचाई",s5h:"मौसम व सिंचाई निगरानी",
  s5p:"मौसम, मिट्टी की नमी और सिंचाई कार्यक्रम को रियल टाइम में ट्रैक करें.",s5btn:"सलाह देखें",
  s6tag:"कीट व रोग चेतावनी",s6h:"कीट व रोग की शीघ्र चेतावनी",
  s6p:"ड्रोन-आधारित स्कैनिंग से कीट हमलों और फसल रोगों की शीघ्र चेतावनी प्राप्त करें.",s6btn:"चेतावनियां देखें",
  wxlTemp:"तापमान",wxlHum:"आर्द्रता",wxlRain:"वर्षा (७ दिन)",wxlWind:"हवा",
  wxRisk:"मिल्ड्यू जोखिम",wxlRisk:"रोग अलर्ट",riskHigh:"अधिक",riskModerate:"मध्यम",riskLow:"कम",wxLoc:"पालघर, महा.",wxlLoc:"क्षेत्र",
  sn1:"फसल व मिट्टी",sn2:"उर्वरक",sn3:"कैलेंडर",sn4:"सिंचाई",sn5:"बुवाई",sn6:"कटाई",sn7:"AI डॉक्टर",
  cropSelTitle:"फसल और मिट्टी चयन",cropSelSub:"व्यक्तिगत सलाह के लिए अपनी फसल और मिट्टी का प्रकार चुनें",
  lblCrop:"फसल चुनें",lblSoil:"मिट्टी का प्रकार चुनें",
  oCropDef:"-- फसल चुनें --",oSoilDef:"-- मिट्टी चुनें --",
  oRice:"🌾 धान (चावल)",oWheat:"🍞 गेहूं",oCotton:"🌿 कपास",oSoy:"🟢 सोयाबीन",
  oTomato:"🍅 टमाटर",oOnion:"🧅 प्याज",oPotato:"🥔 आलू",oChilli:"🌶️ मिर्च",oSugar:"🎋 गन्ना",oPomeg:"🍎 अनार",
  oBlack:"⚫ काली कपास मिट्टी",oRed:"🔴 लाल लेटराइट मिट्टी",oAlluvial:"🟡 जलोढ़ मिट्टी",oSandy:"🏜️ बलुई मिट्टी",oLoamy:"🟤 दोमट मिट्टी",
  ciSeason:"मौसम",ciDur:"अवधि",ciYield:"औसत उत्पादन",ciWater:"पानी की जरूरत",ciSoil:"उचित मिट्टी",
  fertTitle:"उर्वरक अनुशंसा",fertSub:"उर्वरक अनुसूची के लिए ऊपर अपनी फसल चुनें",
  fH1:"वृद्धि चरण",fH2:"पोषक तत्व",fH3:"मात्रा / एकड़",fH4:"समय",fH5:"विधि",
  fertEmpty:"उर्वरक अनुशंसाएं देखने के लिए ऊपर फसल चुनें",
  calTitle:"फसल कैलेंडर",calSub:"महीने-वार कृषि गतिविधियों की झलक",
  calMJun:"जून",calMJul:"जुलाई",calMAug:"अगस्त",calMSep:"सितंबर",
  calMOct:"अक्टूबर",calMNov:"नवंबर",calMDec:"दिसंबर",calMJan:"जनवरी",
  calJun:"भूमि तैयारी और बुवाई",calJul:"पहली उर्वरक खुराक",
  calAug:"सिंचाई और खरपतवार नियंत्रण",calSep:"कीट और रोग निगरानी",
  calOct:"दूसरी उर्वरक खुराक",calNov:"कटाई की तैयारी",calDec:"कटाई",calJan:"भंडारण और बाजार",
  irrTitle:"सिंचाई मार्गदर्शिका",irrSub:"फसल और मिट्टी के अनुसार इष्टतम सिंचाई अनुसूची",
  irrT1:"ड्रिप सिंचाई",irrX1:"कपास, टमाटर, मिर्च के लिए अनुशंसित। फूलने के दौरान ३–४ लीटर/पौधा/दिन दें।",
  irrT2:"बाढ़ सिंचाई",irrX2:"धान और गन्ने के लिए उपयुक्त। खरीफ में खेत में ५ सेमी खड़ा पानी बनाए रखें।",
  irrT3:"सर्वोत्तम समय",irrX3:"सुबह ६–८ या शाम ५–७ बजे सिंचाई करें। वाष्पीकरण ३०% तक कम होता है।",
  irrT4:"महत्वपूर्ण अवस्थाएं",irrX4:"फूल आने और दाना भरते समय मिट्टी सूखने न दें। उपज ४०% तक घट सकती है।",
  sowTitle:"बुवाई मार्गदर्शिका",sowSub:"किस्म चयन, बीज उपचार और बुवाई की सर्वोत्तम विधियाँ",
  sowT1:"बीज चयन",sowX1:"अनुमोदित किस्मों के प्रमाणित बीज चुनें। धान: IR-64, स्वर्णा। कपास: BT हाइब्रिड। अंकुरण क्षमता कम से कम 85% होनी चाहिए।",
  sowT2:"बीज उपचार",sowX2:"Trichoderma viride (4 g/kg) + Carbendazim (2 g/kg) से बीज उपचार करें। बुवाई से 30 मिनट पहले छाया में सुखाएं।",
  sowT3:"बुवाई गहराई और दूरी",sowX3:"धान: 2–3 सेमी गहराई, 20×15 सेमी दूरी। कपास: 4–5 सेमी, 90×60 सेमी। गेहूं: 4–6 सेमी, 22.5 सेमी कतार दूरी।",
  sowT4:"बुवाई का समय",sowX4:"खरीफ: 15 जून – 15 जुलाई। रबी: 15 अक्टूबर – 30 नवंबर। मिट्टी का तापमान 20–30°C होना चाहिए।",
  harvTitle:"कटाई मार्गदर्शिका",harvSub:"परिपक्वता संकेतक, कटाई विधियाँ और कटाई के बाद की सलाह",
  harvT1:"परिपक्वता संकेतक",harvX1:"धान: 80% दाने सुनहरे, 25–30% नमी। कपास: 60–70% टिंडे खुलना। गेहूं: सुनहरा पीला, सख्त दाना।",
  harvT2:"कटाई विधि",harvX2:"गेहूं और धान के लिए कंबाइन हार्वेस्टर। कपास की हाथ से चुनाई (3–4 बार)। शुष्क मौसम में काटें।",
  harvT3:"कटाई के बाद भंडारण",harvX3:"अनाज 12–14% नमी तक सुखाएं। हर्मेटिक बैग या साइलो उपयोग करें। एल्युमिनियम फॉस्फाइड धुआं करें।",
  harvT4:"बाजार समय",harvX4:"बेचने से पहले MSP जांचें। e-NAM पोर्टल पर पंजीकरण करें। कटाई के तुरंत बाद संकट-बिक्री से बचें।",
  scanDashTitle:"AI फसल स्कैनर",aiSub:"तुरंत सिम्युलेटेड निदान के लिए पत्ती की फोटो अपलोड करें",
  uploadTitle:"पत्ती की फोटो अपलोड करें",uploadSub:"JPG, PNG फाइलें स्वीकार की जाती हैं",
  analyzeMsg1:"⏳ AI पत्ती की फोटो का विश्लेषण कर रहा है...",
  scanErrorMsg:"⚠️ फोटो का विश्लेषण नहीं हो सका। कृपया दोबारा कोशिश करें।",
  scanConfigMsg:"⚠️ AI स्कैनर अभी सर्वर पर सेट नहीं है।",
  scanNotPlantMsg:"⚠️ यह पत्ती/पौधे की फोटो नहीं लग रही। कृपया साफ पत्ती की फोटो अपलोड करें।",
  scanHealthyMsg:"✅ अच्छी खबर — यह पत्ती स्वस्थ दिख रही है! कोई रोग नहीं मिला।",
  scanFoundMsg:"🔬 संभावित समस्या मिली — नीचे विवरण देखें।",
  scanConfidence:"विश्वसनीयता",scanDescLbl:"जानकारी",scanTreatBioLbl:"जैविक उपचार",scanTreatChemLbl:"रासायनिक उपचार",scanPreventionLbl:"रोकथाम",
  remLbl:"जैविक उपाय",
  badgeFungal:"फफूंदजनित",badgeBacterial:"जीवाणुजनित",badgeViral:"विषाणुजनित",badgePest:"कीट",
  dmCta:"एआय डॉक्टर से सलाह लें",
  d1Name:"Powdery Mildew (भुरी रोग)",d1Crop:"प्रभावित: कपास, टमाटर",d1Rem:"1% नीम तेल या सल्फर का छिड़काव करें",d1sym:"लक्षण: पत्तियों पर सफेद पाउडर, वृद्धि रुकना, पत्ती मुड़ना.",d1prev:"बचाव: घना रोपण न करें। मानसून से पहले सल्फर स्प्रे करें.",
  d2Name:"Bacterial Blight (करपा)",d2Crop:"प्रभावित: चावल, अनार",d2Rem:"Copper Oxychloride (2 ग्राम/लीटर पानी)",d2sym:"लक्षण: पत्ती के किनारे भूरे पड़ना, कोंपलें मुरझाना.",d2prev:"बचाव: प्रतिरोधी किस्में चुनें। अधिक नाइट्रोजन न दें.",
  d3Name:"Yellow Mosaic Virus (पीला मोज़ेक)",d3Crop:"प्रभावित: मिर्च, सोयाबीन",d3Rem:"Imidacloprid चिपचिपे जाल — सफेद मक्खी नियंत्रण",d3sym:"लक्षण: पत्तियों पर पीला-हरा मोज़ेक, पौधा बौना.",d3prev:"बचाव: संक्रमित पौधे हटाएं। Imidacloprid 17.8SL छिड़कें.",
  d4Name:"Late Blight (झुलसा रोग)",d4Crop:"प्रभावित: आलू, टमाटर",d4Rem:"Mancozeb 75WP (2.5 ग्राम/लीटर)",d4sym:"लक्षण: पत्ती किनारे काले-भूरे धब्बे। ठंड व नमी में तेज़ी से फैलता है.",d4prev:"बचाव: ऊपर से पानी न दें। पौधों में 60 सेमी दूरी रखें.",
  d5Name:"Stem Borer (तना छेदक)",d5Crop:"प्रभावित: गेहूं, चावल, गन्ना",d5Rem:"Chlorpyrifos 2% दाने या Coragen स्प्रे",d5sym:"लक्षण: तने में छेद, पत्तियां पीली पड़ना।",d5prev:"बचाव: फेरोमोन ट्रैप लगाएं। संक्रमित तने जलाएं.",
  d6Name:"Root Rot (जड़ सड़न)",d6Crop:"प्रभावित: सोयाबीन, कपास, प्याज",d6Rem:"Trichoderma viride से बीज उपचार",d6sym:"लक्षण: पौधा अचानक मुरझाना, जड़ें काली और सड़ी.",d6prev:"बचाव: जल निकासी सुधारें। चूने के साथ गहरी जुताई करें.",
  gridOfferHeading:"हाई अलर्ट: {loc} क्षेत्र",gridOfferSub:"इस सप्ताह नमी के कारण कपास में Powdery Mildew का खतरा बढ़ा है। तुरंत बचाव करें!",
  guidesAvail:"विशेषज्ञ उपचार मार्गदर्शिकाएं उपलब्ध",
  govTitle:"सरकारी योजनाएं",
  sch1:"पीएम फसल बीमा योजना",sch2:"पीएम-किसान योजना",sch3:"मृदा स्वास्थ्य कार्ड",sch4:"ई-नाम बाजार पोर्टल",sch5:"एमकिसान SMS पोर्टल",
  expertTitle:"विशेषज्ञ सलाह",
  tip1lbl:"💡 आज की सलाह:",tip1txt:"हर 2–3 साल में फसल चक्र अपनाएं। मिट्टी की पोषण कमी और कीट प्रकोप कम होगा।",
  tip2lbl:"💧 पानी टिप:",tip2txt:"पौधों के आसपास मल्चिंग से 50% तक पानी की बचत होती है और खरपतवार नियंत्रण होता है।",
  tip3lbl:"🔬 मिट्टी टिप:",tip3txt:"खरीफ से पहले मिट्टी परीक्षण कराएं। pH 6.5–7.5 महाराष्ट्र की फसलों के लिए आदर्श है।",
  callLbl:"किसान हेल्पलाइन कॉल करें",dlTxt:"PDF रिपोर्ट डाउनलोड करें",btnText:"AI डॉक्टर से सलाह लें",
  stBlack:"काली मिट्टी नमी अच्छी तरह बनाए रखती है। सिंचाई कम करें। कपास, सोयाबीन, गेहूं के लिए उत्तम।",
  stRed:"लाल मिट्टी में जल धारण कम। जैविक खाद डालें। मिर्च, मूंगफली के लिए उपयुक्त।",
  stAlluvial:"जलोढ़ मिट्टी बहुत उपजाऊ। धान, गेहूं, गन्ने के लिए सर्वोत्तम। अच्छी जल निकासी रखें।",
  stSandy:"बलुई मिट्टी जल्दी सूखती है। मल्चिंग व ड्रिप सिंचाई करें। 5–6 टन FYM/एकड़ डालें।",
  stLoamy:"दोमट मिट्टी अधिकतर फसलों के लिए आदर्श। संतुलित जल धारण और जल निकासी।"
}
};

/* Fertilizer data */
const FERT={rice:[["Nursery","N (Urea)","10 kg","Before sowing","Broadcast"],["Transplanting","NPK 12:32:16","50 kg","At transplanting","Basal"],["Tillering","Urea","25 kg","25–30 DAT","Top dress"],["Panicle Init.","Potash (MOP)","20 kg","45 DAT","Side dress"]],wheat:[["Basal","DAP","50 kg","Before sowing","Incorporate"],["CRI Stage","Urea","30 kg","21 DAS","Top dress"],["Jointing","Urea","30 kg","45 DAS","Top dress"],["Ear Head","Potash","15 kg","60 DAS","Foliar spray"]],cotton:[["Basal","NPK 10:26:26","60 kg","At sowing","Furrow"],["Vegetative","Urea","25 kg","30 DAS","Side dress"],["Boll Formation","Urea+Potash","30+20 kg","60 DAS","Fertigation"],["Boll Opening","Potash","15 kg","90 DAS","Foliar spray"]],soyabean:[["Basal","DAP","50 kg","At sowing","Broadcast"],["Vegetative","Urea","10 kg","20 DAS","Top dress"],["Flowering","Potash (SOP)","20 kg","35 DAS","Side dress"],["Pod Fill","Micronutrient Mix","2 kg","50 DAS","Foliar spray"]],tomato:[["Transplanting","NPK 19:19:19","30 kg","At transplant","Drip"],["Vegetative","Urea","20 kg","15 DAT","Drip"],["Flowering","NPK 13:0:45","25 kg","35 DAT","Drip"],["Fruiting","Calcium Nitrate","10 kg","50 DAT","Foliar spray"]],onion:[["Transplanting","DAP","40 kg","At planting","Basal"],["Bulb Init.","Urea","25 kg","30 DAT","Side dress"],["Bulb Dev.","Potash","25 kg","50 DAT","Side dress"],["Maturity","Sulphur","10 kg","65 DAT","Soil appl."]],potato:[["Ridging","NPK 15:15:15","80 kg","At planting","Furrow"],["Haulm","Urea","30 kg","25 DAS","Side dress"],["Tuber Init.","Potash (MOP)","30 kg","40 DAS","Side dress"],["Bulking","Calcium Boron","2 kg","55 DAS","Foliar spray"]],chilli:[["Transplanting","NPK 19:19:19","25 kg","At transplant","Drip"],["Vegetative","Urea","15 kg","20 DAT","Top dress"],["Flowering","Potassium Nitrate","20 kg","35 DAT","Drip"],["Fruiting","Boron+Zinc","1 kg each","50 DAT","Foliar spray"]],sugarcane:[["Planting","Pressmud Compost","4 tonnes","At planting","Furrow"],["Germination","Urea","40 kg","30 DAS","Top dress"],["Grand Growth","Urea+Potash","50+40 kg","90 DAS","Split"],["Maturity","Potash","20 kg","270 DAS","Soil appl."]],pomegranate:[["Establishment","FYM","20 kg/tree","Before plant","Pit fill"],["Vegetative","NPK 13:0:45","1 kg/tree","Apr–May","Drip"],["Flowering","Potassium Nitrate","0.75 kg/tree","Jun–Jul","Drip"],["Fruit Dev.","Calcium Nitrate","0.5 kg/tree","Aug–Sep","Foliar spray"]]};
const CINFO={rice:{s:"Kharif (Jun–Nov)",d:"120–145 days",y:"25–30 q/ac",w:"High",so:"Clay/Alluvial"},wheat:{s:"Rabi (Nov–Apr)",d:"110–125 days",y:"18–22 q/ac",w:"Medium",so:"Loamy/Alluvial"},cotton:{s:"Kharif (Jun–Oct)",d:"150–180 days",y:"8–12 q/ac",w:"Medium",so:"Black Cotton Soil"},soyabean:{s:"Kharif (Jun–Sep)",d:"90–110 days",y:"10–14 q/ac",w:"Low–Med",so:"Black/Loamy"},tomato:{s:"Year-round",d:"90–120 days",y:"80–100 q/ac",w:"High",so:"Sandy Loam"},onion:{s:"Rabi (Oct–Mar)",d:"110–120 days",y:"60–80 q/ac",w:"Medium",so:"Sandy Loam"},potato:{s:"Rabi (Oct–Feb)",d:"90–100 days",y:"80–120 q/ac",w:"Medium",so:"Sandy Loam"},chilli:{s:"Kharif/Rabi",d:"120–150 days",y:"25–35 q/ac",w:"Medium",so:"Red/Loamy"},sugarcane:{s:"Year-round",d:"300–360 days",y:"300–400 q/ac",w:"Very High",so:"Alluvial/Black"},pomegranate:{s:"Perennial",d:"2–3 yr",y:"60–80 q/ac",w:"Low–Med",so:"Well-drained Sandy"}};

/* Apply all translations */
function pageLanguageCallback(lang){
  const t=AdvisoryT[lang]||AdvisoryT.en; window.lang=lang;
  const s=(id,v)=>{const e=document.getElementById(id);if(e&&v!=null)e.textContent=v;};
  const si=(id,v)=>{const e=document.getElementById(id);if(e&&v!=null)e.innerHTML=v;};
  s('heroBadge',t.heroBadge);s('heroTitle',t.heroTitle);s('heroSub',t.heroSub);si('s1-btn','<i class="fa-solid fa-microscope"></i> '+t.s1btn);
  s('s4-tag',t.s4tag);s('s4-h',t.s4h);s('s4-p',t.s4p);si('s4-btn','<i class="fa-solid fa-seedling"></i> '+t.s4btn);
  s('s3-tag',t.s3tag);s('s3-h',t.s3h);s('s3-p',t.s3p);si('s3-btn','<i class="fa-solid fa-flask"></i> '+t.s3btn);
  s('s5-tag',t.s5tag);s('s5-h',t.s5h);s('s5-p',t.s5p);si('s5-btn','<i class="fa-solid fa-droplet"></i> '+t.s5btn);
  s('s6-tag',t.s6tag);s('s6-h',t.s6h);s('s6-p',t.s6p);si('s6-btn','<i class="fa-solid fa-triangle-exclamation"></i> '+t.s6btn);
  s('wxlTemp',t.wxlTemp);s('wxlHum',t.wxlHum);s('wxlRain',t.wxlRain);s('wxlWind',t.wxlWind);
  s('wxRisk',t.wxRisk);s('wxlRisk',t.wxlRisk);s('wxlLoc',t.wxlLoc);
  if(window.currentWeather&&window.currentWeather.riskLevel) applyRiskLevel(window.currentWeather.riskLevel);
  for(let i=1;i<=7;i++)s('sn'+i,t['sn'+i]);
  s('cropSelTitle',t.cropSelTitle);s('cropSelSub',t.cropSelSub);
  s('lblCrop',t.lblCrop);s('lblSoil',t.lblSoil);
  s('oCropDef',t.oCropDef);s('oSoilDef',t.oSoilDef);
  ['Rice','Wheat','Cotton','Soy','Tomato','Onion','Potato','Chilli','Sugar','Pomeg'].forEach(k=>s('o'+k,t['o'+k]));
  ['Black','Red','Alluvial','Sandy','Loamy'].forEach(k=>s('o'+k,t['o'+k]));
  s('fertTitle',t.fertTitle);s('fertSub',t.fertSub);
  for(let i=1;i<=5;i++)s('fH'+i,t['fH'+i]);
  s('fertEmpty',t.fertEmpty);
  s('calTitle',t.calTitle);s('calSub',t.calSub);
  ['Jun','Jul','Aug','Sep','Oct','Nov','Dec','Jan'].forEach(m=>{s('calM'+m,t['calM'+m]);s('cal'+m,t['cal'+m]);});
  s('irrTitle',t.irrTitle);s('irrSub',t.irrSub);
  for(let i=1;i<=4;i++){s('irrT'+i,t['irrT'+i]);s('irrX'+i,t['irrX'+i]);}
  s('sowTitle',t.sowTitle);s('sowSub',t.sowSub);
  for(let i=1;i<=4;i++){s('sowT'+i,t['sowT'+i]);s('sowX'+i,t['sowX'+i]);}
  s('harvTitle',t.harvTitle);s('harvSub',t.harvSub);
  for(let i=1;i<=4;i++){s('harvT'+i,t['harvT'+i]);s('harvX'+i,t['harvX'+i]);}
  s('scanDashTitle',t.scanDashTitle);s('aiSub',t.aiSub);s('uploadTitle',t.uploadTitle);s('uploadSub',t.uploadSub);
  for(let n=1;n<=6;n++){s('rl'+n,t.remLbl);s('d'+n+'-name',t['d'+n+'Name']);s('d'+n+'-crop',t['d'+n+'Crop']);s('d'+n+'-rem',t['d'+n+'Rem']);s('d'+n+'sym',t['d'+n+'sym']);s('d'+n+'prev',t['d'+n+'prev']);
    const bEl=document.getElementById('d'+n+'-badge');if(bEl){const ty=bEl.getAttribute('data-type');const key='badge'+ty.charAt(0).toUpperCase()+ty.slice(1);bEl.textContent=t[key]||bEl.textContent;}}
  s('dmCta',t.dmCta);
  if(document.getElementById('diseaseModal').classList.contains('active')&&window.currentModalDisease)openDiseaseModal(window.currentModalDisease);
  s('gridOfferSub',t.gridOfferSub);refreshLocalizedLocation();applyOfferHeading();
  s('guidesAvailableText',t.guidesAvail);s('guideCount','6');
  s('govTitle',t.govTitle);
  for(let i=1;i<=5;i++)s('sch'+i,t['sch'+i]);
  s('expertTitle',t.expertTitle);
  for(let i=1;i<=3;i++){s('tip'+i+'lbl',t['tip'+i+'lbl']);s('tip'+i+'txt',t['tip'+i+'txt']);}
  s('callLbl',t.callLbl);s('dlTxt',t.dlTxt);
  document.querySelectorAll('.btn-text').forEach(el=>el.textContent=t.btnText);
  const crop=document.getElementById('cropSelect').value;
  renderInfoPanel();
  renderFert(crop||null);
}

function onCropChange(){const c=document.getElementById('cropSelect').value;renderInfoPanel();if(c)renderFert(c);else renderFert(null);}
function onSoilChange(){renderInfoPanel();}
function renderInfoPanel(){
  const crop=document.getElementById('cropSelect').value,soil=document.getElementById('soilSelect').value;
  const p=document.getElementById('cropInfoPanel');
  if(!crop&&!soil){p.style.display='none';p.innerHTML='';return;}
  const t=AdvisoryT[window.lang]||AdvisoryT.en;
  let html='';
  const ci=CINFO[crop];
  if(ci){
    const stats=[
      ['fa-calendar-days',t.ciSeason,ci.s],
      ['fa-clock',t.ciDur,ci.d],
      ['fa-boxes-stacked',t.ciYield,ci.y],
      ['fa-droplet',t.ciWater,ci.w],
      ['fa-mound',t.ciSoil,ci.so]
    ];
    html+='<div class="km-statgrid">'+stats.map(x=>'<div class="km-statcard"><div class="km-staticon"><i class="fa-solid '+x[0]+'"></i></div><span class="km-statlbl">'+x[1]+'</span><span class="km-statval">'+x[2]+'</span></div>').join('')+'</div>';
  }
  if(soil){
    const tips={black:t.stBlack,red:t.stRed,alluvial:t.stAlluvial,sandy:t.stSandy,loamy:t.stLoamy};
    html+='<div class="km-cirow-soil"'+(ci?' style="margin-top:14px;"':'')+'><i class="fa-solid fa-mound" style="color:var(--ac-primary);margin-right:8px;"></i>'+(tips[soil]||'')+'</div>';
  }
  p.innerHTML=html;p.style.display='block';
}
function renderFert(crop){
  const b=document.getElementById('fertBody'),d=crop?FERT[crop]:null,t=AdvisoryT[window.lang]||AdvisoryT.en;
  if(!d){b.innerHTML='<tr><td colspan="5" class="km-tblempty">'+t.fertEmpty+'</td></tr>';return;}
  b.innerHTML=d.map(r=>'<tr><td>'+r[0]+'</td><td>'+r[1]+'</td><td><span class="km-chip">'+r[2]+'</span></td><td><span class="km-chip">'+r[3]+'</span></td><td><span class="km-chip">'+r[4]+'</span></td></tr>').join('');
}
function triggerLeafUpload(){document.getElementById('leafFileInput').click();}

/* ── Disease detail modal ── */
function openDiseaseModal(n){
  window.currentModalDisease=n;
  const card=document.querySelectorAll('.km-disease-card')[n-1];
  if(!card)return;
  const img=card.querySelector('.km-diseaseico img');
  const badge=document.getElementById('d'+n+'-badge');
  document.getElementById('dmImg').src=img.src;
  document.getElementById('dmImg').alt=img.alt;
  document.getElementById('dmBadge').textContent=badge.textContent;
  document.getElementById('dmBadge').style.background=badge.style.background;
  document.getElementById('dmName').textContent=document.getElementById('d'+n+'-name').textContent;
  document.getElementById('dmCrop').textContent=document.getElementById('d'+n+'-crop').textContent;
  document.getElementById('dmRemLbl').textContent=document.getElementById('rl'+n).textContent;
  document.getElementById('dmRem').textContent=document.getElementById('d'+n+'-rem').textContent;
  document.getElementById('dmSym').textContent=document.getElementById('d'+n+'sym').textContent;
  document.getElementById('dmPrev').textContent=document.getElementById('d'+n+'prev').textContent;
  const t=AdvisoryT[window.lang]||AdvisoryT.en;
  document.getElementById('dmCta').textContent=t.dmCta;
  const modal=document.getElementById('diseaseModal');
  modal.classList.add('active');
  document.body.style.overflow='hidden';
}
function closeDiseaseModal(){
  document.getElementById('diseaseModal').classList.remove('active');
  document.body.style.overflow='';
}
document.addEventListener('keydown',function(e){if(e.key==='Escape')closeDiseaseModal();});

function analyzeLeafImage(){
  const f=document.getElementById('leafFileInput').files[0];if(!f)return;
  const rdr=new FileReader();
  rdr.onload=e=>{document.getElementById('leafPreviewImg').src=e.target.result;document.getElementById('leafPreviewWrap').style.display='block';};
  rdr.readAsDataURL(f);

  const sb=document.getElementById('scanAnalysisStatus'),t=AdvisoryT[window.lang]||AdvisoryT.en;
  const resultBox=document.getElementById('scanResultBox');
  if(resultBox)resultBox.remove();

  Object.assign(sb.style,{display:'block',background:'#fff3cd',color:'#856404'});
  sb.textContent=t.analyzeMsg1;

  const apiLang=(window.lang==='hi')?'hi':'en'; // Plant.id supports en/hi natively; mr falls back to en
  const fd=new FormData();
  fd.append('leaf_image',f);
  fd.append('lang',apiLang);

  fetch(KM_LEAF_API_URL,{method:'POST',body:fd})
    .then(r=>r.json().then(data=>({ok:r.ok,data})))
    .then(({ok,data})=>{
      if(!ok||data.error){
        sb.style.background='#f8d7da';sb.style.color='#721c24';
        if(data.error==='server_config') sb.textContent=t.scanConfigMsg;
        else sb.textContent=t.scanErrorMsg;
        return;
      }
      if(data.is_plant===false){
        sb.style.background='#f8d7da';sb.style.color='#721c24';
        sb.textContent=t.scanNotPlantMsg;
        return;
      }
      if(!data.diseases||!data.diseases.length||data.is_healthy===true){
        sb.style.background='#d4edda';sb.style.color='#155724';
        sb.textContent=t.scanHealthyMsg;
        return;
      }
      sb.style.background='#d4edda';sb.style.color='#155724';
      sb.textContent=t.scanFoundMsg;
      renderScanResult(data.diseases,t);
    })
    .catch(()=>{
      sb.style.background='#f8d7da';sb.style.color='#721c24';
      sb.textContent=t.scanErrorMsg;
    });
}

function renderScanResult(diseases,t){
  const wrap=document.getElementById('leafPreviewWrap');
  const box=document.createElement('div');
  box.id='scanResultBox';
  box.className='km-scanresult';
  box.innerHTML=diseases.slice(0,3).map(d=>{
    const rows=[];
    if(d.description) rows.push('<div class="km-disr"><i class="fa-solid fa-circle-info"></i><span><strong>'+t.scanDescLbl+':</strong> '+escapeHtml(d.description)+'</span></div>');
    if(d.treatment_biological) rows.push('<div class="km-disr"><i class="fa-solid fa-leaf"></i><span><strong>'+t.scanTreatBioLbl+':</strong> '+escapeHtml(d.treatment_biological)+'</span></div>');
    if(d.treatment_chemical) rows.push('<div class="km-disr"><i class="fa-solid fa-flask"></i><span><strong>'+t.scanTreatChemLbl+':</strong> '+escapeHtml(d.treatment_chemical)+'</span></div>');
    if(d.prevention) rows.push('<div class="km-disr"><i class="fa-solid fa-shield-virus"></i><span><strong>'+t.scanPreventionLbl+':</strong> '+escapeHtml(d.prevention)+'</span></div>');
    return '<div class="km-scanresult-card"><div class="km-scanresult-top"><span class="km-scanresult-name">'+escapeHtml(d.name)+'</span><span class="km-scanresult-conf">'+t.scanConfidence+': '+d.probability+'%</span></div><div class="km-dis">'+rows.join('')+'</div></div>';
  }).join('');
  wrap.insertAdjacentElement('afterend',box);
}

function escapeHtml(str){
  const div=document.createElement('div');div.textContent=str;return div.innerHTML;
}
let tipIdx=0;
function setTip(n){tipIdx=(n+3)%3;document.querySelectorAll('.km-tip').forEach((e,i)=>e.classList.toggle('active',i===tipIdx));document.querySelectorAll('.km-tdot').forEach((e,i)=>e.classList.toggle('active',i===tipIdx));}
function nextTip(){setTip(tipIdx+1);}function prevTip(){setTip(tipIdx-1);}
function callExpert(){window.open('tel:18001801551','_self');}
function kmScroll(id,idx){
  const el=document.getElementById(id);if(el)el.scrollIntoView({behavior:'smooth',block:'start'});
  document.querySelectorAll('.km-snavb').forEach((b,i)=>b.classList.toggle('active',i===idx));
}
window.addEventListener('scroll',()=>{
  const ids=['secCrop','secFert','secCal','secIrr','secSow','secHarv','secAI'];
  let cur=0;ids.forEach((id,i)=>{const el=document.getElementById(id);if(el&&el.getBoundingClientRect().top<=130)cur=i;});
  document.querySelectorAll('.km-snavb').forEach((b,i)=>b.classList.toggle('active',i===cur));
},{passive:true});
function downloadReport(btn){
  const orig=btn.innerHTML;btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Generating...';btn.disabled=true;
  const crop=document.getElementById('cropSelect').value||'General',soil=document.getElementById('soilSelect').value||'Not Selected',today=new Date().toLocaleDateString('en-IN');
  const rows=(FERT[crop]||[]).map(r=>'  '+r[0].padEnd(20)+'| '+r[1].padEnd(22)+'| '+r[2]).join('\n')||'  Select a crop for data.';
  const w=window.currentWeather||{};
  const wxLine='  Temp: '+(w.temp??'--')+'°C | Humidity: '+(w.humidity??'--')+'% | Wind: '+(w.wind??'--')+' km/h | Disease Risk: Mildew '+(w.riskLevel||'--').toUpperCase();
  const txt=['AGRiCART — CROP ADVISORY REPORT','Generated: '+today+'  |  Lang: '+(window.lang||'en').toUpperCase(),'='.repeat(50),'CROP: '+crop.toUpperCase(),'SOIL: '+soil.toUpperCase(),'','WEATHER',wxLine,'','FERTILIZER SCHEDULE',rows,'','DISEASE ALERTS','  1. Powdery Mildew — Neem Oil 1%','  2. Bacterial Blight — Copper Oxychloride 2g/L','  3. Yellow Mosaic — Imidacloprid traps','','GOVT SCHEMES','  PM Fasal Bima: pmfby.gov.in','  PM-KISAN: pmkisan.gov.in','  e-NAM: enam.gov.in','','HELPLINE: 1800-180-1551 (Free)','='.repeat(50)].join('\n');
  const a=Object.assign(document.createElement('a'),{href:URL.createObjectURL(new Blob([txt],{type:'text/plain'})),download:'AgriCart_Advisory_'+crop+'_'+today.replace(/\//g,'-')+'.txt'});
  a.click();setTimeout(()=>{btn.innerHTML=orig;btn.disabled=false;},1500);
}

/* Live weather — visitor's real location (browser geolocation + free Open-Meteo/BigDataCloud APIs, no key required) */
const KM_FALLBACK_LAT=19.6971, KM_FALLBACK_LON=72.7699, KM_FALLBACK_LOC='Palghar, MH';
function computeRiskLevel(temp,hum){
  if(hum>=75&&temp>=20&&temp<=32) return 'High';
  if(hum>=55&&temp>=18&&temp<=34) return 'Moderate';
  return 'Low';
}
function applyRiskLevel(level){
  const t=AdvisoryT[window.lang]||AdvisoryT.en;
  const key='risk'+level; // riskHigh/riskModerate/riskLow
  const el=document.getElementById('wxRiskLevel');
  if(el) el.textContent=t[key]||level;
  const box=document.getElementById('wxRiskBox');
  if(box) box.classList.toggle('km-wxalert',level!=='Low');
}
function fetchLiveWeather(lat,lon){
  const url='https://api.open-meteo.com/v1/forecast?latitude='+lat+'&longitude='+lon
    +'&current=temperature_2m,relative_humidity_2m,wind_speed_10m'
    +'&daily=precipitation_sum&past_days=7&forecast_days=1&timezone=Asia%2FKolkata';
  fetch(url).then(r=>r.json()).then(d=>{
    const cur=d.current||{};
    const temp=Math.round(cur.temperature_2m);
    const hum=Math.round(cur.relative_humidity_2m);
    const wind=Math.round(cur.wind_speed_10m);
    const rainArr=(d.daily&&d.daily.precipitation_sum)||[];
    const rain=Math.round(rainArr.reduce((a,b)=>a+(b||0),0));
    const level=computeRiskLevel(temp,hum);
    window.currentWeather={temp,humidity:hum,wind,rain,riskLevel:level};
    const setB=(id,v)=>{const e=document.getElementById(id);if(e)e.textContent=v;};
    setB('wxValTemp',temp+'°C');setB('wxValHum',hum+'%');setB('wxValRain',rain+' mm');setB('wxValWind',wind+' km/h');
    applyRiskLevel(level);
  }).catch(()=>{ /* leave placeholders if weather API is unreachable */ });
}
function setLocationLabel(text,cityOnly){
  const e=document.getElementById('wxLoc');
  if(e) e.textContent=text;
  window.currentLocationLabel=text;
  window.currentCityOnly=cityOnly||text;
  applyOfferHeading();
}
function applyOfferHeading(){
  const t=AdvisoryT[window.lang]||AdvisoryT.en;
  const city=window.currentCityOnly||'Palghar';
  const e=document.getElementById('gridOfferHeading');
  if(e && t.gridOfferHeading) e.textContent=t.gridOfferHeading.replace('{loc}',city);
}
function reverseGeocodeAndFetch(lat,lon){
  window.currentGeoCoords={lat,lon};
  refreshLocalizedLocation();
  fetchLiveWeather(lat,lon);
}
function refreshLocalizedLocation(){
  const coords=window.currentGeoCoords;
  if(!coords){ setLocationLabel(KM_FALLBACK_LOC,'Palghar'); return; }
  const lang=window.lang||'en';
  fetch('https://api.bigdatacloud.net/data/reverse-geocode-client?latitude='+coords.lat+'&longitude='+coords.lon+'&localityLanguage='+lang)
    .then(r=>r.json())
    .then(d=>{
      const city=d.city||d.locality||d.principalSubdivision||'';
      const statePart=lang==='en'
        ? (d.principalSubdivisionCode?d.principalSubdivisionCode.split('-').pop():(d.principalSubdivision||''))
        : (d.principalSubdivision||'');
      const label=[city,statePart].filter(Boolean).join(', ')||KM_FALLBACK_LOC;
      setLocationLabel(label,city||'Palghar');
    })
    .catch(()=>setLocationLabel(KM_FALLBACK_LOC,'Palghar'));
}
function initLiveWeatherAndLocation(){
  if(navigator.geolocation){
    navigator.geolocation.getCurrentPosition(
      pos=>reverseGeocodeAndFetch(pos.coords.latitude,pos.coords.longitude),
      ()=>{ setLocationLabel(KM_FALLBACK_LOC,'Palghar'); fetchLiveWeather(KM_FALLBACK_LAT,KM_FALLBACK_LON); },
      {timeout:8000}
    );
  } else {
    setLocationLabel(KM_FALLBACK_LOC,'Palghar'); fetchLiveWeather(KM_FALLBACK_LAT,KM_FALLBACK_LON);
  }
}

document.addEventListener('DOMContentLoaded',()=>{
  const lang=localStorage.getItem('agri_lang')||'en';
  window.lang=lang;pageLanguageCallback(lang);
  const mo=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
  const cur=mo[new Date().getMonth()];
  document.querySelector('.km-calcard[data-month="'+cur+'"]')?.classList.add('km-calnow');
  initLiveWeatherAndLocation();
});</script>

<?php include_once __DIR__ . '/krishimitra_widget.php'; ?>
<?php include __DIR__ . '/../includes/footer.php'; ?>
