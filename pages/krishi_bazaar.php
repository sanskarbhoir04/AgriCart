<?php
// krishi-bazaar.php - Krishi Bazaar Marketplace Page for AgriCart
// Including site header
include(__DIR__ . '/../includes/header.php');
?>
<!DOCTYPE html>
<html lang="en" id="html-root">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Krishi Bazaar | AgriCart</title>
  <link rel="stylesheet" href="<?php echo $base_path; ?>/assets/css/krishi-bazaar.css"/>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Devanagari:wght@400;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet"/>
</head>
<body>

<div class="kb-ticker">
  <div class="kb-ticker__track" id="tickerTrack">
    <span>🌾 <span data-en="Today's Onion Rate: ₹2,400/qtl" data-mr="आजचा कांद्याचा भाव: ₹2,400/क्विंटल" data-hi="आज का प्याज का भाव: ₹2,400/क्विंटल">Today's Onion Rate: ₹2,400/qtl</span></span>
    <span>🍅 <span data-en="Tomato Rate: ₹1,800/qtl" data-mr="टोमॅटोचा भाव: ₹1,800/क्विंटल" data-hi="टमाटर का भाव: ₹1,800/क्विंटल">Tomato Rate: ₹1,800/qtl</span></span>
    <span>🌱 <span data-en="Soybean Rate: ₹5,200/qtl" data-mr="सोयाबीनचा भाव: ₹5,200/क्विंटल" data-hi="सोयाबीन का भाव: ₹5,200/क्विंटल">Soybean Rate: ₹5,200/qtl</span></span>
    <span>🌾 <span data-en="Wheat Rate: ₹2,150/qtl" data-mr="गव्हाचा भाव: ₹2,150/क्विंटल" data-hi="गेहूं का भाव: ₹2,150/क्विंटल">Wheat Rate: ₹2,150/qtl</span></span>
    <span>🍇 <span data-en="Grapes Rate: ₹4,500/qtl" data-mr="द्राक्षांचा भाव: ₹4,500/क्विंटल" data-hi="अंगूर का भाव: ₹4,500/क्विंटल">Grapes Rate: ₹4,500/qtl</span></span>
    <span>🌶️ <span data-en="Chilli Rate: ₹8,000/qtl" data-mr="मिरचीचा भाव: ₹8,000/क्विंटल" data-hi="मिर्च का भाव: ₹8,000/क्विंटल">Chilli Rate: ₹8,000/qtl</span></span>
    <span>🧅 <span data-en="Garlic Rate: ₹6,200/qtl" data-mr="लसणाचा भाव: ₹6,200/क्विंटल" data-hi="लहसुन का भाव: ₹6,200/क्विंटल">Garlic Rate: ₹6,200/qtl</span></span>
    <span>🥔 <span data-en="Potato Rate: ₹1,200/qtl" data-mr="बटाट्याचा भाव: ₹1,200/क्विंटल" data-hi="आलू का भाव: ₹1,200/क्विंटल">Potato Rate: ₹1,200/qtl</span></span>
  </div>
</div>

<main class="kb-main" id="kb-main">


  <div class="slider-wrap" style="height: 78vh; min-height: 500px;">
    <div class="slide active" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar/slider-1-hero-farmland.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" data-en="Maharashtra Agricultural Marketplace" data-mr="महाराष्ट्र कृषी बाजारपेठ" data-hi="महाराष्ट्र कृषि बाज़ार">Maharashtra Agricultural Marketplace</div>
            <h1>🌿 <span data-en="Krishi Bazaar" data-mr="कृषी बाजार" data-hi="कृषि बाज़ार">Krishi Bazaar</span></h1>
            <p data-en="Maharashtra's most trusted platform for farmers, buyers & traders — real-time prices, verified listings, direct deals." data-mr="शेतकरी, खरेदीदार आणि व्यापारी यांच्यासाठी महाराष्ट्रातील सर्वात विश्वसनीय प्लॅटफॉर्म." data-hi="किसानों, खरीदारों और व्यापारियों के लिए महाराष्ट्र का सबसे भरोसेमंद प्लेटफॉर्म।">Maharashtra's most trusted platform for farmers, buyers & traders.</p>
            <div class="kb-search-bar" style="margin-top:20px; max-width:600px;">
              <div class="kb-search-bar__icon">🔍</div>
              <input type="text" id="heroSearch" class="kb-search-bar__input" data-placeholder-en="Search crops, city, farmer..." data-placeholder-mr="पिके, शहर, शेतकरी शोधा..." data-placeholder-hi="फसलें, शहर, किसान खोजें..." placeholder="Search crops, city, farmer..."/>
              <button class="kb-search-bar__btn" onclick="triggerSearch()" data-en="Search" data-mr="शोधा" data-hi="खोजें">Search</button>
            </div>
            <div class="kb-hero__actions" style="margin-top:15px;">
              <button class="kb-btn kb-btn--primary" onclick="scrollToSection('kb-listings')" data-en="🛒 Sell Product" data-mr="🛒 उत्पादन विका" data-hi="🛒 उत्पाद बेचें">🛒 Sell Product</button>
              <button class="kb-btn kb-btn--outline" style="color:#1a3c1a;border-color:#fff;background:#fff;box-shadow:0 2px 10px rgba(0,0,0,0.15);transition:background .2s ease,color .2s ease;" onmouseover="this.style.background='#1a3c1a';this.style.color='#fff';" onmouseout="this.style.background='#fff';this.style.color='#1a3c1a';" onclick="scrollToSection('kb-products')" data-en="🌾 Browse Products" data-mr="🌾 उत्पादने पहा" data-hi="🌾 उत्पाद देखें">🌾 Browse Products</button>
              <button class="kb-btn kb-btn--gold" onclick="scrollToSection('kb-market-rates')" data-en="📊 Market Rates" data-mr="📊 बाजारभाव" data-hi="📊 बाज़ार भाव">📊 Market Rates</button>
            </div>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar/slider-2-mandi-prices.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" data-en="Live Market Rates" data-mr="लाइव्ह बाजारभाव" data-hi="लाइव मंडी भाव">Live Market Rates</div>
            <h1 data-en="Real-Time Mandi Prices" data-mr="रिअल-टाइम मंडी भाव" data-hi="रियल-टाइम मंडी भाव">Real-Time Mandi Prices</h1>
            <p data-en="Check today's rates for Onion, Tomato, Wheat, Soybean across Maharashtra mandis." data-mr="महाराष्ट्रातील मंडईंमध्ये कांदा, टोमॅटो, गहू, सोयाबीन यांचे आजचे भाव पहा." data-hi="महाराष्ट्र की मंडियों में प्याज, टमाटर, गेहूं, सोयाबीन के आज के भाव देखें।">Check today's rates for Onion, Tomato, Wheat, Soybean across Maharashtra mandis.</p>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar/slider-3-sell-direct.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" data-en="Verified Farmers" data-mr="सत्यापित शेतकरी" data-hi="सत्यापित किसान">Verified Farmers</div>
            <h1 data-en="Sell Directly to Buyers" data-mr="खरेदीदारांना थेट विका" data-hi="खरीदारों को सीधे बेचें">Sell Directly to Buyers</h1>
            <p data-en="Bypass middlemen — list your produce and connect directly with wholesale buyers." data-mr="मध्यस्थांना टाळा — तुमचे उत्पादन नोंदवा आणि घाऊक खरेदीदारांशी थेट संपर्क साधा." data-hi="बिचौलियों को छोड़ें — अपनी उपज सूचीबद्ध करें और थोक खरीदारों से सीधे जुड़ें।">Bypass middlemen — list your produce and connect directly with wholesale buyers.</p>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar/slider-4-trending-crops.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" data-en="Trending Crops" data-mr="ट्रेंडिंग पिके" data-hi="ट्रेंडिंग फसलें">Trending Crops</div>
            <h1 data-en="High Demand Crops This Season" data-mr="या हंगामातील जास्त मागणी असलेली पिके" data-hi="इस सीज़न में अधिक मांग वाली फसलें">High Demand Crops This Season</h1>
            <p data-en="Garlic, Grapes and Chilli are trending — list now and get the best price!" data-mr="लसूण, द्राक्षे आणि मिरची ट्रेंडमध्ये आहेत — आताच नोंदवा आणि सर्वोत्तम भाव मिळवा!" data-hi="लहसुन, अंगूर और मिर्च ट्रेंड में हैं — अभी सूचीबद्ध करें और सबसे अच्छा भाव पाएं!">Garlic, Grapes and Chilli are trending — list now and get the best price!</p>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo $base_path; ?>/assets/images/krishi-bazaar/slider-5-price-alerts.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" data-en="Price Alerts" data-mr="भाव अलर्ट" data-hi="भाव अलर्ट">Price Alerts</div>
            <h1 data-en="Set Your Target Price Alerts" data-mr="तुमचा लक्ष्य भाव अलर्ट सेट करा" data-hi="अपना लक्षित भाव अलर्ट सेट करें">Set Your Target Price Alerts</h1>
            <p data-en="Get notified instantly when your crop hits your desired market price." data-mr="तुमचे पीक तुमच्या इच्छित बाजारभावाला पोहोचताच त्वरित सूचना मिळवा." data-hi="जब आपकी फसल आपके इच्छित बाज़ार भाव तक पहुंचे तो तुरंत सूचना पाएं।">Get notified instantly when your crop hits your desired market price.</p>
        </div>
    </div>

    <div class="slider-dots" id="sliderDots"></div>
  </div>

  <!-- STATS -->
  <style>
    /* Stats bar: uses the shared global .stats / .stat-item structure and color (same as every other page) */
    /* The page's own .kb-main * { margin:0; padding:0 } reset (in krishi-bazaar.css) was wiping the
       shared .stat-item padding since it sits inside <main class="kb-main">. Restore it here with
       higher specificity so it matches every other page exactly. */
    .kb-main .stats .stat-item { padding: 1.1rem 2.6rem; }
    .kb-main .stats .stat-item h3 { margin-bottom: 4px; }
  </style>
  <section class="stats">
    <div class="stat-item"><h3>36+</h3><p data-en="Districts Covered" data-mr="जिल्हे समाविष्ट" data-hi="ज़िले शामिल">Districts Covered</p></div>
    <div class="stat-item"><h3>43+</h3><p data-en="Commodities Tracked" data-mr="मालाचे प्रकार ट्रॅक केले" data-hi="वस्तुएं ट्रैक की गईं">Commodities Tracked</p></div>
    <div class="stat-item"><h3 id="kbStatLiveRecords">Live</h3><p data-en="Live Price Records" data-mr="लाइव्ह भाव नोंदी" data-hi="लाइव भाव रिकॉर्ड">Live Price Records</p></div>
    <div class="stat-item"><h3 style="font-size:24px">Govt. API</h3><p data-en="Data Source" data-mr="माहितीचा स्रोत" data-hi="डेटा स्रोत">Data Source</p></div>
  </section>


  <section class="kb-filters-section" id="kb-filters">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="Advanced Search & Filters" data-mr="प्रगत शोध आणि फिल्टर" data-hi="उन्नत खोज और फ़िल्टर">Advanced Search & Filters</h2>
        <span class="kb-badge-green" data-en="Live Data" data-mr="लाईव्ह डेटा" data-hi="लाइव डेटा">Live Data</span>
      </div>
      <div class="kb-filters__grid">
        <div class="kb-filter-group">
          <label data-en="Search Crop" data-mr="पीक शोधा" data-hi="फसल खोजें">Search Crop</label>
          <input type="text" id="filterCrop" 
                 data-placeholder-en="e.g. Onion, Wheat..." 
                 data-placeholder-mr="उदा. कांदा, गहू..." 
                 data-placeholder-hi="उदा. प्याज, गेहूं..." 
                 placeholder="e.g. Onion, Wheat..."/>
        </div>
        <div class="kb-filter-group">
          <label data-en="Category" data-mr="वर्ग (Category)" data-hi="श्रेणी (Category)">Category</label>
          <select id="filterCategory">
            <option value="" data-en="All Categories" data-mr="सर्व वर्ग" data-hi="सभी श्रेणियाँ">All Categories</option>
            <option value="Vegetables" data-en="Vegetables" data-mr="भाज्या" data-hi="सब्जियां">Vegetables</option>
            <option value="Fruits" data-en="Fruits" data-mr="फळे" data-hi="फल">Fruits</option>
            <option value="Grains" data-en="Grains" data-mr="धान्य" data-hi="अनाज">Grains</option>
            <option value="Pulses" data-en="Pulses" data-mr="कडधान्य" data-hi="दालें">Pulses</option>
            <option value="Spices" data-en="Spices" data-mr="मसाले" data-hi="मसाले">Spices</option>
            <option value="Oilseeds" data-en="Oilseeds" data-mr="तेलबिया" data-hi="तिलहन">Oilseeds</option>
            <option value="Cash Crops" data-en="Cash Crops" data-mr="नगदी पिके" data-hi="नकदी फसलें">Cash Crops</option>
          </select>
        </div>
        <div class="kb-filter-group">
          <label data-en="City" data-mr="शहर" data-hi="शहर">City</label>
          <select id="filterCity">
            <option value="" data-en="All Cities" data-mr="सर्व शहरे" data-hi="सभी शहर">All Cities</option>
          </select>
        </div>
        <div class="kb-filter-group">
          <label data-en="Min Price (₹/qtl)" data-mr="किमान भाव (₹/क्विंटल)" data-hi="न्यूनतम भाव (₹/क्विंटल)">Min Price (₹/qtl)</label>
          <input type="number" id="filterPriceMin" placeholder="0"/>
        </div>
        <div class="kb-filter-group">
          <label data-en="Max Price (₹/qtl)" data-mr="कमाल भाव (₹/क्विंटल)" data-hi="अधिकतम भाव (₹/क्विंटल)">Max Price (₹/qtl)</label>
          <input type="number" id="filterPriceMax" placeholder="99999"/>
        </div>
        <div class="kb-filter-group">
          <label data-en="Organic Only" data-mr="फक्त सेंद्रिय" data-hi="केवल जैविक">Organic Only</label>
          <div class="kb-toggle-wrap">
            <label class="kb-toggle">
              <input type="checkbox" id="filterOrganic"/>
              <span class="kb-toggle__slider"></span>
            </label>
            <span id="organicLabel" data-en="No" data-mr="नाही" data-hi="नहीं">No</span>
          </div>
        </div>
        <div class="kb-filter-group">
          <label data-en="Sort By" data-mr="क्रमवारी (Sort)" data-hi="क्रमबद्ध करें (Sort)">Sort By</label>
          <select id="filterSort">
            <option value="default" data-en="Default" data-mr="डिफॉल्ट" data-hi="डिफ़ॉल्ट">Default</option>
            <option value="price_asc" data-en="Price: Low to High" data-mr="किंमत: कमी ते जास्त" data-hi="कीमत: कम से ज्यादा">Price: Low to High</option>
            <option value="price_desc" data-en="Price: High to Low" data-mr="किंमत: जास्त ते कमी" data-hi="कीमत: ज्यादा से कम">Price: High to Low</option>
            <option value="demand_desc" data-en="Demand: Highest First" data-mr="मागणी: सर्वाधिक आधी" data-hi="मांग: सबसे अधिक पहले">Demand: Highest First</option>
          </select>
        </div>
        <div class="kb-filter-group kb-filter-group--btn">
          <button class="kb-btn kb-btn--primary kb-full-width" onclick="applyFilters()" data-en="Apply Filters" data-mr="फिल्टर लागू करा" data-hi="फ़िल्टर लागू करें">Apply Filters</button>
          <button class="kb-btn kb-btn--ghost kb-full-width" onclick="resetFilters()" data-en="Reset" data-mr="रीसेट करा" data-hi="रीसेट करें">Reset</button>
        </div>
      </div>
    </div>
  </section>

  <section class="kb-insights" id="kb-insights">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="Today's Market Insights" data-mr="आजची बाजारपेठ माहिती" data-hi="आज की बाज़ार जानकारी">Today's Market Insights</h2>
      </div>
      <div class="kb-insights__grid" id="insightsGrid"></div>
    </div>
  </section>

  <section class="kb-market-section" id="kb-market-rates">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="City-Wise Market Rates" data-mr="शहरानुसार बाजारभाव" data-hi="शहर के अनुसार बाज़ार भाव">City-Wise Market Rates</h2>
        <span class="kb-badge-green" data-en="Live" data-mr="लाईव्ह" data-hi="लाइव">Live</span>
      </div>
      <div class="kb-city-tabs" id="cityTabs" style="overflow:visible;position:relative;z-index:100;"></div>
      <div class="kb-table-wrap">
        <table class="kb-table" id="marketTable">
          <thead>
            <tr>
              <th data-en="Crop" data-mr="पीक" data-hi="फसल">Crop</th>
              <th data-en="Crop Name" data-mr="पिकाचे नाव" data-hi="फसल का नाम">Crop Name</th>
              <th data-en="City" data-mr="शहर" data-hi="शहर">City</th>
              <th data-en="Market Price" data-mr="बाजारभाव" data-hi="बाज़ार भाव">Market Price</th>
              <th data-en="Change" data-mr="बदल" data-hi="बदलाव">Change</th>
              <th data-en="Trend" data-mr="ट्रेंड" data-hi="ट्रेंड">Trend</th>
            </tr>
          </thead>
          <tbody id="marketTableBody"></tbody>
        </table>
      </div>
    </div>
  </section>

  <section class="kb-trend-section" id="kb-trends">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="Price Trend Analytics" data-mr="किंमत ट्रेंड विश्लेषण" data-hi="मूल्य ट्रेंड विश्लेषण">Price Trend Analytics</h2>
      </div>
      <div class="kb-trend__controls">
        <select id="trendCrop" class="kb-trend__select"></select>
        <div class="kb-trend__periods">
          <button class="kb-period-btn active" data-period="7" data-en="7 Days" data-mr="७ दिवस" data-hi="7 दिन">7 Days</button>
          <button class="kb-period-btn" data-period="15" data-en="15 Days" data-mr="१५ दिवस" data-hi="15 दिन">15 Days</button>
          <button class="kb-period-btn" data-period="30" data-en="30 Days" data-mr="३० दिवस" data-hi="30 दिन">30 Days</button>
        </div>
      </div>
      <div class="kb-trend__stats" id="trendStats"></div>
      <div class="kb-chart-wrap">
        <canvas id="trendChart" height="280"></canvas>
      </div>
    </div>
  </section>

  <section class="kb-trending-section" id="kb-trending">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="🔥 Trending Crops" data-mr="🔥 ट्रेंडिंग पिके" data-hi="🔥 ट्रेंडिंग फसलें">🔥 Trending Crops</h2>
      </div>
      <div class="kb-trending__grid" id="trendingGrid"></div>
    </div>
  </section>

  <section class="kb-products-section" id="kb-products">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="Featured Products" data-mr="वैशिष्ट्यपूर्ण उत्पादने" data-hi="विशेष उत्पाद">Featured Products</h2>
        <button class="kb-btn kb-btn--outline kb-btn--sm" onclick="openAlertModal()" data-en="🔔 Set Price Alert" data-mr="🔔 भाव अलर्ट सेट करा" data-hi="🔔 भाव अलर्ट सेट करें">🔔 Set Price Alert</button>
      </div>
      <div class="kb-products__grid" id="productsGrid"></div>
      <div class="kb-load-more-wrap">
        <button class="kb-btn kb-btn--outline" id="loadMoreBtn" onclick="loadMoreProducts()" data-en="Load More Products" data-mr="अधिक उत्पादने पहा" data-hi="और उत्पाद देखें">Load More Products</button>
      </div>
    </div>
  </section>

  <section class="kb-buyers-section" id="kb-buyers">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="Buyer Requirements" data-mr="खरेदीदारांच्या गरजा" data-hi="खरीदार की आवश्यकताएं">Buyer Requirements</h2>
      </div>
      <div class="kb-buyers__grid" id="buyersGrid"></div>
    </div>
  </section>

  <section class="kb-farmers-section" id="kb-farmers">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="✅ Verified Farmers" data-mr="✅ सत्यापित शेतकरी" data-hi="✅ सत्यापित किसान">✅ Verified Farmers</h2>
      </div>
      <div class="kb-farmers__grid" id="farmersGrid"></div>
    </div>
  </section>

  <section class="kb-listings-section" id="kb-listings">
    <div class="kb-container">
      <div class="kb-section-header">
        <h2 data-en="Recent Listings" data-mr="नवीनतम लिस्टिंग" data-hi="नवीनतम लिस्टिंग">Recent Listings</h2>
        <select id="listingSort" onchange="sortListings(this.value)">
          <option value="new" data-en="Newest First" data-mr="सर्वांत नवीन" data-hi="सबसे नया पहले">Newest First</option>
          <option value="price_asc" data-en="Price ↑" data-mr="किंमत ↑" data-hi="कीमत ↑">Price ↑</option>
          <option value="price_desc" data-en="Price ↓" data-mr="किंमत ↓" data-hi="कीमत ↓">Price ↓</option>
        </select>
      </div>
      <div class="kb-listings__grid" id="listingsGrid"></div>
    </div>
  </section>

</main>

<div class="kb-modal-overlay" id="alertModal" role="dialog" aria-modal="true" aria-label="Price Alert">
  <div class="kb-modal">
    <button class="kb-modal__close" onclick="closeAlertModal()" aria-label="Close">✕</button>
    <div class="kb-modal__icon">🔔</div>
    <h3 class="kb-modal__title" data-en="Set Price Alert" data-mr="भाव अलर्ट सेट करा" data-hi="भाव अलर्ट सेट करें">Set Price Alert</h3>
    <p class="kb-modal__sub" data-en="Get notified when your target price is reached." data-mr="तुमचा लक्ष्य भाव गाठल्यावर सूचना मिळवा." data-hi="आपका लक्ष्य भाव पहुंचने पर सूचना प्राप्त करें।">Get notified when your target price is reached.</p>
    <div class="kb-modal__form">
      <div class="kb-filter-group">
        <label data-en="Select Crop" data-mr="पीक निवडा" data-hi="फसल चुनें">Select Crop</label>
        <select id="alertCrop"></select>
      </div>
      <div class="kb-filter-group">
        <label data-en="Select City" data-mr="शहर निवडा" data-hi="शहर चुनें">Select City</label>
        <select id="alertCity"></select>
      </div>
      <div class="kb-filter-group">
        <label data-en="Target Price (₹/qtl)" data-mr="लक्ष्य भाव (₹/क्विंटल)" data-hi="लक्ष्य भाव (₹/क्विंटल)">Target Price (₹/qtl)</label>
        <input type="number" id="alertPrice" 
               data-placeholder-en="e.g. 2500" 
               data-placeholder-mr="उदा. 2500" 
               data-placeholder-hi="उदा. 2500" 
               placeholder="e.g. 2500"/>
      </div>
      <button class="kb-btn kb-btn--primary kb-full-width" onclick="saveAlert()" data-en="Save Alert" data-mr="अलर्ट सेव्ह करा" data-hi="अलर्ट सेव करें">Save Alert</button>
    </div>
    <div class="kb-modal__saved" id="savedAlerts">
      <h4 data-en="Your Alerts" data-mr="तुमचे अलर्ट्स" data-hi="आपके अलर्ट्स">Your Alerts</h4>
      <div id="alertsList"></div>
    </div>
  </div>
</div>

<div class="kb-toast" id="kbToast"></div>

<script>const KB_BASE = "<?php echo $base_path; ?>";</script>
<script src="<?php echo $base_path; ?>/assets/js/krishi-bazaar.js"></script>
<?php include __DIR__ . '/krishimitra_widget.php'; ?>

</body>
</html>
<?php
// Including site footer
include(__DIR__ . '/../includes/footer.php');
?>