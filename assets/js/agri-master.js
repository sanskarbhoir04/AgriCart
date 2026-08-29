// ─── TRANSLATION DICTIONARY ───
const T = {
    en: {
        flash: {
            'index.php': `<i class="fa-solid fa-fire"></i> Welcome to AgriCart! Get 10% off on your first order. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-seedling"></i> New: Mahabeej Hybrid Seeds now in stock! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-store"></i> Explore 500+ organic products. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-cloud-showers-heavy"></i> Monsoon Sale: Extra 5% off on all seeds this week! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-gift"></i> Refer a friend & earn ₹100 AgriCash! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-id-card"></i> New users get a free Soil Health Card! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'marketplace.php': `<i class="fa-solid fa-fire"></i> Flash Offer: 15% off on Organic NPK Fertilizers! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-truck-fast"></i> Free delivery on orders above ₹1,999. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-shield-check"></i> Quality Assured: Certified seeds available. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-gift"></i> Buy 2 Get 1 Free on select pesticides! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-wallet"></i> Get cashback up to ₹200 on UPI payments! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-user-check"></i> 100% Verified Sellers, quality guaranteed. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'rental.php': `<i class="fa-solid fa-tractor"></i> Rental Deal: Rent tractors for just ₹500/hour. First 10 hours at 10% off! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-clock"></i> 24/7 Booking Available. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-helicopter"></i> New: Drone spraying now available in 12 districts! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-layer-group"></i> Combo Offer: Tractor + Rotavator at 12% off! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-shield-halved"></i> Zero deposit on select insured equipment. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'advisory.php': `<i class="fa-solid fa-seedling"></i> Expert Advice: Free crop consultation for Kharif season! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-microscope"></i> AI-based disease diagnosis in 30 seconds. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-flask"></i> Book a free soil-testing camp near you! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-chalkboard-user"></i> Free webinar on pest control this Sunday! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-vial"></i> Get instant fertilizer dosage recommendations. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'krishi_bazaar.php': `<i class="fa-solid fa-chart-line"></i> Live Market: Get best prices for your wheat and cotton today! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-handshake"></i> Direct access to 100+ APMC buyers. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-arrow-trend-up"></i> Onion prices up 8% this week — sell now! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-scale-balanced"></i> Compare rates across 20+ mandis instantly. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-bell"></i> Set price alerts and never miss the best rate! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'agri-connect.php': `<i class="fa-solid fa-comments"></i> Community: Join the discussion and win monthly AgriCart rewards! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> 10,000+ farmers connected. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-trophy"></i> Top contributor of the month wins a ₹1,000 voucher! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-comment-dots"></i> Ask experts, get answers within 24 hours! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-camera"></i> Share your farm photos and inspire others! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'chavdi.php': `<i class="fa-solid fa-comments"></i> Community: Join the discussion and win monthly AgriCart rewards! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> 10,000+ farmers connected. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-trophy"></i> Top contributor of the month wins a ₹1,000 voucher! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-comment-dots"></i> Ask experts, get answers within 24 hours! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-camera"></i> Share your farm photos and inspire others! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'about.php': `<i class="fa-solid fa-award"></i> Proudly empowering Indian farmers with technology since day one! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> 50,000+ farmers trust AgriCart across Maharashtra. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-layer-group"></i> 5 powerful tools, one single login. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-user-check"></i> Farmer-first, transparent by design. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'contact.php': `<i class="fa-solid fa-envelope"></i> 24/7 Farmer Helpline: 1800-419-8888. Call us for any farming assistance! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-message"></i> Live chat support now available 8 AM–10 PM! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-brands fa-whatsapp"></i> WhatsApp support now live! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-stopwatch"></i> Average response time: under 2 hours. <span class="sep" style="margin:0 15px;">|</span> `,
            'default': `<i class="fa-solid fa-fire"></i> Special Offer: 15% off on Organic NPK Fertilizers! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-truck-fast"></i> Free delivery above ₹1,999 <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-box-open"></i> Track your order in real-time from My Orders. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-mobile-screen-button"></i> Explore all AgriCart services in one app. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-star"></i> Trusted by farmers across Maharashtra. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> Helpline: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `
        },
        nav: ['<i class="fa-solid fa-house"></i> Home','<i class="fa-solid fa-circle-info"></i> About Us','<i class="fa-solid fa-cart-shopping"></i> Agri Store','<i class="fa-solid fa-tractor"></i> Rental Hub','<i class="fa-solid fa-seedling"></i> Crop Advisory','<i class="fa-solid fa-chart-line"></i> Krishi Bazaar','<i class="fa-solid fa-users"></i> Agri Connect','<i class="fa-solid fa-envelope"></i> Contact Us'],
        s1h:'Smart Farming Meets E-Commerce', s1p:"India's most trusted digital platform connecting farmers with seeds, tools, and market prices.",
        s2h:'E-Commerce Marketplace', s2p:'Buy certified seeds, organic fertilizers, and pesticides from verified sellers.', s2btn:'Open Agri Store',
        s3h:'Heavy Machinery Rental', s3p:'Rent tractors, drone sprayers, and harvesting equipment by the hour or day.', s3btn:'Rent Equipment',
        s4h:'AI-Powered Crop Advisory', s4p:'Detect plant diseases and get expert recommendations using machine learning.', s4btn:'Get Crop Advisory',
        s5h:'Live APMC Market Rates', s5p:'Track real-time commodity prices. No middlemen. Maximum value for your harvest.', s5btn:'Check Live Rates',
        s6h:'Agri Connect', s6p:'Discuss farming challenges, share tips, and learn from thousands of fellow farmers.', s6btn:'Join Agri Connect',
        s7h:'Expert Help, When You Need It', s7p:'Our agritech support team is ready to assist you — call, chat, or message.', s7btn:'Contact Us',
        st1:'Registered Farmers', st2:'Certified Products', st3:'Verified Merchants', st4:'Platform Rating',
        galLabel:'What We Offer', galTitle:'Everything a Farmer Needs', galSub:'From buying seeds to selling your harvest — manage your entire farm journey in one place.',
        gt1:'Rental Hub', gh1:'Machinery Rental', gp1:'Rent tractors, rotavators, drone sprayers, and more. Pay per hour, no long-term commitment.',
        gt2:'AI Advisory', gh2:'Crop Disease Advisor', gp2:'Upload a photo of your crop and get instant AI-based diagnosis and treatment recommendations.',
        gt3:'Krishi Bazaar', gh3:'Direct Market Access', gp3:'Skip the middleman. Check live mandi rates for wheat, cotton, onion, and 50+ other crops.',
        gb1:'Browse Equipment', gb2:'Get Free Advice', gb3:'View Live Rates',
        wTitle:'Today\'s Weather Forecast', wLoc:'Palghar, Maharashtra', wCond:'Partly Cloudy',
        wHum:'Humidity: <b>70%</b>', wWind:'Wind: <b>12 km/h</b>', wRain:'Rain chance: <b>40%</b>', wVis:'Visibility: <b>8 km</b>',
        wAdvice:'Wind is stable. Best time to spray pesticides is after 2 PM today.',
        schemeTitle:'Government Schemes for Farmers',
        sch1n:'PM-KISAN', sch1d:'₹6,000/year direct income support for landholding farmers, in 3 installments.',
        sch2n:'PM Fasal Bima Yojana', sch2d:'Low-premium crop insurance against natural calamities, pests and disease.',
        sch3n:'Kisan Credit Card (KCC)', sch3d:'Low-interest loans up to ₹3 lakh for crop and farming needs.',
        sch4n:'Soil Health Card', sch4d:'Free soil testing with nutrient and fertilizer recommendations.',
        sch5n:'PM-KUSUM', sch5d:'Subsidy up to 60% on solar irrigation pumps and grid-connected solar power.',
        sch6n:'e-NAM', sch6d:"Sell your produce online across India's mandis — no middlemen.",
        scanTitle:'Leaf Disease Scanner', scanSub:'Tap here to upload a crop photo for instant AI diagnosis',
        testLabel:'Farmer Stories', testTitle:'What Farmers Are Saying', testSub:'Real feedback from real farmers across Maharashtra who use AgriCart every day.',
        t1m:'"AgriCart helped me buy quality Mahabeej seeds directly, without any middlemen. The live tracking for delivery is very accurate."', t1a:'— Ramesh Patil, Nashik',
        t2m:'"I uploaded a photo of my diseased cotton plant and within seconds the AI identified Powdery Mildew and told me exactly what to spray!"', t2a:'— Haribhau Bhoir, Palghar',
        t3m:'"The Krishi Bazaar rates saved me from selling at a loss. I waited 3 days after checking the live mandi price and made ₹8,000 more on my onion crop."', t3a:'— Suresh Wagh, Pune District',
        ftTag:'Empowering Indian Farmers Through Technology', ftCopy:'© 2026 AgriCart. All Rights Reserved. | Made with ❤️ for Indian Farmers',
        botWelcome:'Hello! I\'m AgriBot. Ask me about crop prices, fertilizers, diseases, or government schemes.',
        widLabel:'Live Tools', widTitle:'Farm Smarter with Live Data', widSub:'Real-time weather, government schemes, and crop disease scanning — all in one dashboard.'
    },
    mr: {
        flash: {
            'index.php': `<i class="fa-solid fa-fire"></i> ॲग्रीकार्ट मध्ये स्वागत! पहिल्या खरेदीवर १०% सूट. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-seedling"></i> नवीन: महाबीज हायब्रीड बियाणे स्टॉक मध्ये! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-store"></i> ५०० हून अधिक सेंद्रिय उत्पादने पहा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-cloud-showers-heavy"></i> मान्सून सेल: या आठवड्यात सर्व बियाण्यांवर अतिरिक्त ५% सूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-gift"></i> मित्राला रेफर करा आणि ₹१०० AgriCash मिळवा! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-id-card"></i> नवीन युजर्सला मोफत Soil Health Card! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'marketplace.php': `<i class="fa-solid fa-fire"></i> ऑफर: सेंद्रिय NPK खतांवर १५% सूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-truck-fast"></i> ₹१,९९९ पेक्षा जास्त खरेदीवर मोफत डिलिव्हरी. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-shield-check"></i> खात्रीशीर गुणवत्ता: प्रमाणित बियाणे. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-gift"></i> निवडक कीटकनाशकांवर २ घ्या १ मोफत! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-wallet"></i> UPI पेमेंटवर ₹२०० पर्यंत कॅशबॅक मिळवा! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-user-check"></i> १००% पडताळणी केलेले विक्रेते, हमखास गुणवत्ता. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'rental.php': `<i class="fa-solid fa-tractor"></i> विशेष डील: ट्रॅक्टर भाड्याने घ्या फक्त ₹५००/तास. पहिल्या १० तासांवर १०% सवलत! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-clock"></i> २४/७ बुकिंग सुविधा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-helicopter"></i> नवीन: १२ जिल्ह्यांमध्ये ड्रोन फवारणी आता उपलब्ध! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-layer-group"></i> कॉम्बो ऑफर: ट्रॅक्टर + रोटाव्हेटर १२% सवलतीत! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-shield-halved"></i> निवडक विमा संरक्षित अवजारांवर शून्य डिपॉझिट. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'advisory.php': `<i class="fa-solid fa-seedling"></i> तज्ञांचा सल्ला: खरीप हंगामासाठी मोफत पीक सल्ला! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-microscope"></i> AI द्वारे रोगांचे निदान ३० सेकंदात. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-flask"></i> तुमच्या जवळ मोफत माती परीक्षण शिबिर बुक करा! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-chalkboard-user"></i> या रविवारी कीड नियंत्रणावर मोफत वेबिनार! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-vial"></i> खतांचा योग्य डोस त्वरित जाणून घ्या. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'krishi_bazaar.php': `<i class="fa-solid fa-chart-line"></i> लाईव्ह मार्केट: गहू आणि कापसाला आज सर्वोत्तम भाव मिळवा! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-handshake"></i> १००+ थेट खरेदीदारांशी संपर्क. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-arrow-trend-up"></i> कांद्याचे भाव या आठवड्यात ८% वाढले — आताच विका! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-scale-balanced"></i> २०+ मंडईंचे भाव एकाच ठिकाणी त्वरित पहा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-bell"></i> प्राइस अलर्ट सेट करा, सर्वोत्तम भाव कधीच चुकवू नका! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'agri-connect.php': `<i class="fa-solid fa-comments"></i> समुदाय: चर्चेत सामील व्हा आणि दरमहा ॲग्रीकार्ट बक्षिसे जिंका! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> १०,०००+ शेतकरी जोडले गेले. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-trophy"></i> महिन्याचा सर्वोत्तम सहभागी जिंकतो ₹१,००० व्हाउचर! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-comment-dots"></i> तज्ञांना प्रश्न विचारा, २४ तासांत उत्तर मिळवा! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-camera"></i> तुमच्या शेताचे फोटो शेअर करा आणि इतरांना प्रेरणा द्या! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'chavdi.php': `<i class="fa-solid fa-comments"></i> समुदाय: चर्चेत सामील व्हा आणि दरमहा ॲग्रीकार्ट बक्षिसे जिंका! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> १०,०००+ शेतकरी जोडले गेले. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-trophy"></i> महिन्याचा सर्वोत्तम सहभागी जिंकतो ₹१,००० व्हाउचर! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-comment-dots"></i> तज्ञांना प्रश्न विचारा, २४ तासांत उत्तर मिळवा! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-camera"></i> तुमच्या शेताचे फोटो शेअर करा आणि इतरांना प्रेरणा द्या! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'about.php': `<i class="fa-solid fa-award"></i> पहिल्या दिवसापासून तंत्रज्ञानाद्वारे भारतीय शेतकऱ्यांचे सक्षमीकरण! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> महाराष्ट्रातील ५०,०००+ शेतकऱ्यांचा AgriCart वर विश्वास. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-layer-group"></i> ५ शक्तिशाली सेवा, एकाच लॉगिनमध्ये. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-user-check"></i> शेतकरी-प्रथम, पारदर्शक कार्यपद्धती. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'contact.php': `<i class="fa-solid fa-envelope"></i> २४/७ शेतकरी हेल्पलाइन: 1800-419-8888. आम्ही तुमच्या मदतीसाठी सदैव तत्पर आहोत! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-message"></i> आता Live chat support सकाळी ८ ते रात्री १० उपलब्ध! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-brands fa-whatsapp"></i> आता WhatsApp सपोर्ट देखील उपलब्ध! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-stopwatch"></i> सरासरी प्रतिसाद वेळ: २ तासांपेक्षा कमी. <span class="sep" style="margin:0 15px;">|</span> `,
            'default': `<i class="fa-solid fa-fire"></i> फ्लॅश ऑफर: या आठवड्यात सेंद्रिय NPK खतांवर १५% सूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-box-open"></i> तुमची ऑर्डर My Orders मध्ये रिअल-टाइम ट्रॅक करा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-mobile-screen-button"></i> AgriCart च्या सर्व सेवा एका अ‍ॅपमध्ये पहा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-star"></i> महाराष्ट्रातील शेतकऱ्यांचा विश्वासू पर्याय. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `
        },
        nav: ['<i class="fa-solid fa-house"></i> मुख्य पृष्ठ','<i class="fa-solid fa-circle-info"></i> आमच्याबद्दल','<i class="fa-solid fa-cart-shopping"></i> कृषी स्टोअर','<i class="fa-solid fa-tractor"></i> अवजारे केंद्र','<i class="fa-solid fa-seedling"></i> पीक सल्ला','<i class="fa-solid fa-chart-line"></i> कृषी बाजार','<i class="fa-solid fa-users"></i> कृषी संवाद','<i class="fa-solid fa-envelope"></i> संपर्क साधा'],
        s1h:'स्मार्ट शेती आणि ई-कॉमर्स', s1p:'शेतकऱ्यांना बियाणे, साधने आणि बाजारभावाशी जोडणारे भारतातील विश्वसनीय डिजिटल व्यासपीठ.',
        s2h:'ई-कॉमर्स कृषी बाजारपेठ', s2p:'प्रमाणित बियाणे, सेंद्रिय खते आणि कीटकनाशके थेट विश्वसनीय विक्रेत्यांकडून खरेदी करा.', s2btn:'कृषी स्टोअर उघडा',
        s3h:'आधुनिक कृषी अवजारे भाडे', s3p:'ट्रॅक्टर, ड्रोन फवारणी आणि कापणी यंत्रे तासाने किंवा दिवसाने भाड्याने घ्या.', s3btn:'अवजारे भाड्याने घ्या',
        s4h:'AI-आधारित पीक सल्ला', s4p:'मशीन लर्निंगचा वापर करून पिकांचे रोग ओळखा आणि तज्ज्ञांचा सल्ला मिळवा.', s4btn:'पीक सल्ला मिळवा',
        s5h:'थेट APMC बाजार भाव', s5p:'रिअल-टाइम वस्तू किंमती तपासा. दलाल नाही. तुमच्या पिकाचे पूर्ण मूल्य मिळवा.', s5btn:'थेट बाजार भाव पहा',
        s6h:'कृषी संवाद', s6p:'शेतीच्या समस्यांवर चर्चा करा, tips शेअर करा आणि हजारो शेतकऱ्यांकडून शिका.', s6btn:'कृषी संवाद मध्ये प्रवेश करा',
        s7h:'तज्ज्ञ मदत, जेव्हा आवश्यक असेल', s7p:'आमची कृषी तंत्रज्ञान सहाय्य टीम नेहमी तयार आहे — कॉल करा, चॅट करा किंवा संदेश पाठवा.', s7btn:'आम्हाला संपर्क करा',
        st1:'नोंदणीकृत शेतकरी', st2:'प्रमाणित उत्पादने', st3:'विश्वसनीय व्यापारी', st4:'प्लॅटफॉर्म रेटिंग',
        galLabel:'आमची सेवा', galTitle:'शेतकऱ्याला लागणारे सर्वकाही', galSub:'बियाणे खरेदीपासून ते पीक विक्रीपर्यंत — संपूर्ण शेती प्रवास एकाच ठिकाणी व्यवस्थापित करा.',
        gt1:'अवजारे केंद्र', gh1:'कृषी अवजारे भाडे', gp1:'ट्रॅक्टर, रोटाव्हेटर, ड्रोन फवारणी आणि अधिक. तासाने भाडे, दीर्घकालीन वचनबद्धता नाही.',
        gt2:'AI सल्ला', gh2:'पीक रोग सल्लागार', gp2:'पिकाचा फोटो अपलोड करा आणि त्वरित AI-आधारित निदान आणि उपचाराच्या शिफारसी मिळवा.',
        gt3:'कृषी बाजार', gh3:'थेट बाजार प्रवेश', gp3:'दलाल वगळा. गहू, कापूस, कांदा आणि ५०+ इतर पिकांचे थेट मंडई दर तपासा.',
        gb1:'अवजारे पहा', gb2:'मोफत सल्ला मिळवा', gb3:'थेट दर पहा',
        wTitle:'आजचा हवामान अंदाज', wLoc:'पालघर, Maharashtra', wCond:'अंशतः ढगाळ',
        wHum:'आर्द्रता: <b>७०%</b>', wWind:'वारा: <b>१२ किमी/तास</b>', wRain:'पावसाची शक्यता: <b>४०%</b>', wVis:'दृश्यमानता: <b>८ किमी</b>',
        wAdvice:'वारा स्थिर आहे. आज दुपारी २ वाजल्यानंतर कीटकनाशक फवारणीसाठी उत्तम वेळ.',
        schemeTitle:'शेतकऱ्यांसाठी शासकीय योजना',
        sch1n:'पीएम-किसान', sch1d:'जमीनधारक शेतकऱ्यांना वर्षाला ₹६,००० थेट लाभ, ३ हप्त्यांमध्ये.',
        sch2n:'पीएम फसल विमा योजना', sch2d:'नैसर्गिक आपत्ती, कीड आणि रोगांपासून कमी हप्त्यात पीक विमा.',
        sch3n:'किसान क्रेडिट कार्ड (KCC)', sch3d:'शेतीच्या गरजांसाठी ₹३ लाखांपर्यंत कमी व्याजदरात कर्ज.',
        sch4n:'माती आरोग्य कार्ड', sch4d:'मोफत माती परीक्षण आणि खत शिफारसी.',
        sch5n:'पीएम-कुसुम', sch5d:'सोलर सिंचन पंप आणि ग्रीड-संलग्न सौर वीजेवर ६०% पर्यंत सबसिडी.',
        sch6n:'ई-नाम (e-NAM)', sch6d:'दलालांशिवाय, भारतातील कोणत्याही मंडईत ऑनलाइन माल विका.',
        scanTitle:'पान रोग स्कॅनर', scanSub:'त्वरित AI निदानासाठी पिकाचा फोटो अपलोड करण्यासाठी येथे टॅप करा',
        testLabel:'शेतकरी अनुभव', testTitle:'शेतकरी काय म्हणतात', testSub:'AgriCart वापरणाऱ्या महाराष्ट्रातील खऱ्या शेतकऱ्यांचा खरा अभिप्राय.',
        t1m:'"AgriCart मुळे मला कोणत्याही दलालाशिवाय थेट उत्तम महाबीज बियाणे मिळाले. डिलिव्हरी ट्रॅकिंग अगदी अचूक आहे."', t1a:'— रमेश पाटील, नाशिक',
        t2m:'"माझ्या कापसाच्या रोगग्रस्त झाडाचा फोटो अपलोड केला आणि काही सेकंदात AI ने Powdery Mildew ओळखून काय फवारावे हे सांगितले!"', t2a:'— हरिभाऊ भोईर, पालघर',
        t3m:'"कृषी बाजाराच्या दरांमुळे मी तोट्यात विकणे टाळले. थेट मंडई दर तपासून ३ दिवस थांबलो आणि माझ्या कांद्यावर ₹८,००० जास्त मिळाले."', t3a:'— सुरेश वाघ, पुणे जिल्हा',
        ftTag:'तंत्रज्ञानाद्वारे भारतीय शेतकऱ्यांचे सक्षमीकरण', ftCopy:'© २०२६ AgriCart. सर्व हक्क राखीव. | भारतीय शेतकऱ्यांसाठी ❤️ सोबत बनवले',
        botWelcome:'नमस्कार! मी AgriBot आहे. पिकांचे भाव, खते, रोग किंवा सरकारी योजनांबद्दल विचारा.',
        widLabel:'थेट साधने', widTitle:'थेट माहितीसह हुशार शेती करा', widSub:'रिअल-टाइम हवामान, शासकीय योजना आणि पीक रोग स्कॅनिंग — एकाच डॅशबोर्डमध्ये.'
    },
    hi: {
        flash: {
            'index.php': `<i class="fa-solid fa-fire"></i> AgriCart में आपका स्वागत है! पहली खरीद पर 10% छूट. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-seedling"></i> नया: महाबीज हाइब्रिड बीज अब स्टॉक में! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-store"></i> 500+ जैविक उत्पाद देखें. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-cloud-showers-heavy"></i> मानसून सेल: इस सप्ताह सभी बीजों पर अतिरिक्त 5% छूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-gift"></i> दोस्त को रेफर करें और ₹100 AgriCash पाएं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-id-card"></i> नए यूज़र्स को मुफ्त Soil Health Card! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'marketplace.php': `<i class="fa-solid fa-fire"></i> ऑफर: जैविक NPK खाद पर 15% छूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-truck-fast"></i> ₹1,999 से अधिक खरीद पर मुफ्त डिलीवरी. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-shield-check"></i> गुणवत्ता आश्वासन: प्रमाणित बीज उपलब्ध. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-gift"></i> चुनिंदा कीटनाशकों पर 2 खरीदें 1 मुफ्त पाएं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-wallet"></i> UPI पेमेंट पर ₹200 तक कैशबैक पाएं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-user-check"></i> 100% सत्यापित विक्रेता, गुणवत्ता की गारंटी. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'rental.php': `<i class="fa-solid fa-tractor"></i> किराया डील: ट्रैक्टर सिर्फ ₹500/घंटा. पहले 10 घंटे 10% छूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-clock"></i> 24/7 बुकिंग सुविधा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-helicopter"></i> नया: अब 12 जिलों में ड्रोन स्प्रेइंग उपलब्ध! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-layer-group"></i> कॉम्बो ऑफर: ट्रैक्टर + रोटावेटर 12% छूट में! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-shield-halved"></i> चुनिंदा बीमित उपकरणों पर शून्य डिपॉज़िट. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'advisory.php': `<i class="fa-solid fa-seedling"></i> विशेषज्ञ सलाह: खरीफ सीजन के लिए मुफ्त फसल परामर्श! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-microscope"></i> AI से 30 सेकंड में रोग निदान. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-flask"></i> अपने पास मुफ्त मिट्टी परीक्षण शिविर बुक करें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-chalkboard-user"></i> इस रविवार कीट नियंत्रण पर मुफ्त वेबिनार! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-vial"></i> खाद की सही मात्रा की सिफारिश तुरंत पाएं. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'krishi_bazaar.php': `<i class="fa-solid fa-chart-line"></i> लाइव मार्केट: आज गेहूं और कपास का सबसे अच्छा भाव पाएं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-handshake"></i> 100+ APMC खरीदारों से सीधा संपर्क. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-arrow-trend-up"></i> प्याज के भाव इस सप्ताह 8% बढ़े — अभी बेचें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-scale-balanced"></i> 20+ मंडियों के भाव एक साथ तुरंत देखें. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-bell"></i> प्राइस अलर्ट सेट करें, सबसे अच्छा भाव कभी न चूकें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'agri-connect.php': `<i class="fa-solid fa-comments"></i> समुदाय: चर्चा में जुड़ें और AgriCart पुरस्कार जीतें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> 10,000+ किसान जुड़े हुए. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-trophy"></i> महीने का टॉप योगदानकर्ता जीते ₹1,000 का वाउचर! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-comment-dots"></i> विशेषज्ञों से पूछें, 24 घंटे में जवाब पाएं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-camera"></i> अपने खेत की फोटो शेयर करें और दूसरों को प्रेरित करें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'chavdi.php': `<i class="fa-solid fa-comments"></i> समुदाय: चर्चा में जुड़ें और AgriCart पुरस्कार जीतें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> 10,000+ किसान जुड़े हुए. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-trophy"></i> महीने का टॉप योगदानकर्ता जीते ₹1,000 का वाउचर! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-comment-dots"></i> विशेषज्ञों से पूछें, 24 घंटे में जवाब पाएं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-camera"></i> अपने खेत की फोटो शेयर करें और दूसरों को प्रेरित करें! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'about.php': `<i class="fa-solid fa-award"></i> पहले दिन से तकनीक के जरिए भारतीय किसानों का सशक्तिकरण! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-users"></i> महाराष्ट्र के 50,000+ किसानों का AgriCart पर भरोसा. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-layer-group"></i> 5 शक्तिशाली सेवाएं, एक ही लॉगिन में. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-user-check"></i> किसान-पहले, पारदर्शी कार्यशैली. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `,
            'contact.php': `<i class="fa-solid fa-envelope"></i> 24/7 किसान हेल्पलाइन: 1800-419-8888. हम आपकी मदद के लिए हमेशा तैयार हैं! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-message"></i> अब लाइव चैट सपोर्ट सुबह 8 से रात 10 बजे तक उपलब्ध! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-brands fa-whatsapp"></i> अब WhatsApp सपोर्ट भी उपलब्ध! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-stopwatch"></i> औसत प्रतिक्रिया समय: 2 घंटे से कम. <span class="sep" style="margin:0 15px;">|</span> `,
            'default': `<i class="fa-solid fa-fire"></i> फ्लैश ऑफर: जैविक NPK खाद पर 15% छूट! <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-truck-fast"></i> ₹1,999 से ऊपर मुफ्त डिलीवरी <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-box-open"></i> My Orders में अपना ऑर्डर रियल-टाइम ट्रैक करें. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-mobile-screen-button"></i> AgriCart की सभी सेवाएं एक ही ऐप में देखें. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-star"></i> महाराष्ट्र के किसानों का भरोसेमंद साथी. <span class="sep" style="margin:0 15px;">|</span> <i class="fa-solid fa-phone"></i> हेल्पलाइन: 1800-419-8888 <span class="sep" style="margin:0 15px;">|</span> `
        },
        nav: ['<i class="fa-solid fa-house"></i> होम','<i class="fa-solid fa-circle-info"></i> हमारे बारे में','<i class="fa-solid fa-cart-shopping"></i> एग्री स्टोर','<i class="fa-solid fa-tractor"></i> किराया केंद्र','<i class="fa-solid fa-seedling"></i> फसल सलाह','<i class="fa-solid fa-chart-line"></i> कृषि बाजार','<i class="fa-solid fa-users"></i> एग्री कनेक्ट','<i class="fa-solid fa-envelope"></i> संपर्क करें'],
        s1h:'स्मार्ट खेती और ई-कॉमर्स', s1p:'किसानों को बीज, उपकरण और बाजार भाव से जोड़ने वाला भारत का विश्वसनीय डिजिटल मंच.',
        s2h:'ई-कॉमर्स कृषि बाजार', s2p:'प्रमाणित बीज, जैविक खाद और कीटनाशक सीधे विश्वसनीय विक्रेताओं से खरीदें.', s2btn:'एग्री स्टोर खोलें',
        s3h:'भारी कृषि उपकरण किराया', s3p:'ट्रैक्टर, ड्रोन स्प्रेयर और कटाई उपकरण घंटे या दिन के हिसाब से किराए पर लें.', s3btn:'उपकरण किराए पर लें',
        s4h:'AI-आधारित फसल सलाह', s4p:'मशीन लर्निंग का उपयोग कर पौधों की बीमारी पहचानें और विशेषज्ञ सिफारिशें पाएं.', s4btn:'फसल सलाह पाएं',
        s5h:'लाइव APMC बाजार भाव', s5p:'रियल-टाइम कमोडिटी कीमतें देखें. कोई बिचौलिया नहीं. अपनी फसल का पूरा मूल्य पाएं.', s5btn:'लाइव भाव देखें',
        s6h:'एग्री कनेक्ट', s6p:'खेती की समस्याओं पर चर्चा करें, टिप्स साझा करें और हजारों किसानों से सीखें.', s6btn:'एग्री कनेक्ट में जुड़ें',
        s7h:'विशेषज्ञ मदद, जब जरूरत हो', s7p:'हमारी एग्रीटेक सहायता टीम हमेशा तैयार है — कॉल करें, चैट करें या संदेश भेजें.', s7btn:'संपर्क करें',
        st1:'पंजीकृत किसान', st2:'प्रमाणित उत्पाद', st3:'विश्वसनीय व्यापारी', st4:'प्लेटफॉर्म रेटिंग',
        galLabel:'हमारी सेवाएं', galTitle:'किसान की हर जरूरत', galSub:'बीज खरीदने से लेकर फसल बेचने तक — अपनी पूरी खेती यात्रा एक जगह प्रबंधित करें.',
        gt1:'किराया केंद्र', gh1:'कृषि उपकरण किराया', gp1:'ट्रैक्टर, रोटावेटर, ड्रोन स्प्रेयर और अधिक. प्रति घंटा भुगतान, कोई दीर्घकालिक प्रतिबद्धता नहीं.',
        gt2:'AI सलाह', gh2:'फसल रोग सलाहकार', gp2:'फसल की फोटो अपलोड करें और तुरंत AI-आधारित निदान और उपचार सिफारिशें पाएं.',
        gt3:'कृषि बाजार', gh3:'सीधा बाजार पहुंच', gp3:'बिचौलिया छोड़ें. गेहूं, कपास, प्याज और 50+ अन्य फसलों के सीधे मंडी भाव देखें.',
        gb1:'उपकरण देखें', gb2:'मुफ्त सलाह पाएं', gb3:'लाइव भाव देखें',
        wTitle:'आज का मौसम पूर्वानुमान', wLoc:'पालघर, महाराष्ट्र', wCond:'आंशिक रूप से बादल',
        wHum:'नमी: <b>70%</b>', wWind:'हवा: <b>12 किमी/घंटा</b>', wRain:'बारिश की संभावना: <b>40%</b>', wVis:'दृश्यता: <b>8 किमी</b>',
        wAdvice:'हवा स्थिर है. आज दोपहर 2 बजे के बाद कीटनाशक छिड़काव का सबसे अच्छा समय है.',
        schemeTitle:'किसानों के लिए सरकारी योजनाएं',
        sch1n:'पीएम-किसान', sch1d:'भूमिधारक किसानों को सालाना ₹6,000 सीधा लाभ, 3 किस्तों में.',
        sch2n:'पीएम फसल बीमा योजना', sch2d:'प्राकृतिक आपदा, कीट और रोगों से कम प्रीमियम पर फसल बीमा.',
        sch3n:'किसान क्रेडिट कार्ड (KCC)', sch3d:'खेती की जरूरतों के लिए ₹3 लाख तक कम ब्याज दर पर लोन.',
        sch4n:'मृदा स्वास्थ्य कार्ड', sch4d:'मुफ्त मिट्टी परीक्षण और खाद संबंधी सिफारिशें.',
        sch5n:'पीएम-कुसुम', sch5d:'सोलर सिंचाई पंप और ग्रिड-कनेक्टेड सौर ऊर्जा पर 60% तक सब्सिडी.',
        sch6n:'ई-नाम (e-NAM)', sch6d:'बिचौलियों के बिना, भारत की किसी भी मंडी में ऑनलाइन उपज बेचें.',
        scanTitle:'पत्ती रोग स्कैनर', scanSub:'तुरंत AI निदान के लिए फसल की फोटो अपलोड करने हेतु यहाँ टैप करें',
        testLabel:'किसान की कहानियाँ', testTitle:'किसान क्या कह रहे हैं', testSub:'महाराष्ट्र के वास्तविक किसानों की सच्ची प्रतिक्रिया जो AgriCart रोज इस्तेमाल करते हैं.',
        t1m:'"AgriCart की मदद से मैंने बिना किसी बिचौलिए के सीधे उच्च गुणवत्ता वाले महाबीज बीज खरीदे. डिलीवरी ट्रैकिंग बिल्कुल सटीक है."', t1a:'— रमेश पाटील, नासिक',
        t2m:'"मैंने अपने बीमार कपास के पौधे की फोटो अपलोड की और कुछ सेकंड में AI ने Powdery Mildew पहचान कर बताया कि क्या छिड़कें!"', t2a:'— हरिभाऊ भोईर, पालघर',
        t3m:'"कृषि बाजार के भाव से मैंने घाटे में बेचने से बचा. लाइव मंडी भाव देख 3 दिन रुका और प्याज पर ₹8,000 ज्यादा मिले."', t3a:'— सुरेश वाघ, पुणे जिला',
        ftTag:'तकनीक के जरिए भारतीय किसानों का सशक्तिकरण', ftCopy:'© 2026 AgriCart. सर्वाधिकार सुरक्षित. | भारतीय किसानों के लिए ❤️ के साथ बनाया',
        botWelcome:'नमस्ते! मैं AgriBot हूं. फसल भाव, खाद, रोग या सरकारी योजनाओं के बारे में पूछें.',
        widLabel:'लाइव टूल्स', widTitle:'लाइव डेटा से स्मार्ट खेती करें', widSub:'रियल-टाइम मौसम, सरकारी योजनाएं और फसल रोग स्कैनिंग — एक ही डैशबोर्ड में.'
    }
};

let lang = localStorage.getItem('agri_lang') || 'en';

function switchLanguage(l) {
    lang = l;
    localStorage.setItem('agri_lang', l);
    document.cookie = 'agri_lang=' + l + '; path=/; max-age=31536000; SameSite=Lax';
    document.dispatchEvent(new CustomEvent('agri-lang-changed', {detail: l}));
    const t = T[l];
    
    // ─── 🚀 100% BULLETPROOF CSS MARQUEE LOGIC ───
    // Support both old ID (marqueeSliderTrack) and current ID (marquee-track)
    const track = document.getElementById('marqueeSliderTrack') || document.getElementById('marquee-track');
    if (track) {
        const currentPage = track.getAttribute('data-page') || window.location.pathname.split('/').pop() || 'index.php';
        const currentFlashOffer = t.flash[currentPage] || t.flash['default'];

        // Inject Safe HTML structure with duplicates for perfectly seamless looping
        track.innerHTML = `<div class="flash-item">${currentFlashOffer}</div><div class="flash-item">${currentFlashOffer}</div>`;
    }
    
    // Update Navigation
    const navIds = ['nav-home','nav-about','nav-store','nav-rental','nav-advisory','nav-bazaar','nav-chavdi','nav-contact'];
    navIds.forEach((id,i) => { const el = document.getElementById(id); if(el) el.innerHTML = t.nav[i]; });
    
    // Update IDs — explicit map to fix key mismatch bugs
    const idKeyMap = {
        's1-h':'s1h','s1-p':'s1p',
        's2-h':'s2h','s2-p':'s2p','s2-btn':'s2btn',
        's3-h':'s3h','s3-p':'s3p','s3-btn':'s3btn',
        's4-h':'s4h','s4-p':'s4p','s4-btn':'s4btn',
        's5-h':'s5h','s5-p':'s5p','s5-btn':'s5btn',
        's6-h':'s6h','s6-p':'s6p','s6-btn':'s6btn',
        's7-h':'s7h','s7-p':'s7p','s7-btn':'s7btn',
        'st1':'st1','st2':'st2','st3':'st3','st4':'st4',
        'gal-label':'galLabel','gal-title':'galTitle','gal-sub':'galSub',
        'gt1':'gt1','g-h1':'gh1','g-p1':'gp1','g-b1':'gb1',
        'gt2':'gt2','g-h2':'gh2','g-p2':'gp2','g-b2':'gb2',
        'gt3':'gt3','g-h3':'gh3','g-p3':'gp3','g-b3':'gb3',
        'wid-label':'widLabel','wid-title':'widTitle','wid-sub':'widSub',
        'wd-title':'wTitle','wd-loc':'wLoc','wd-cond':'wCond','wd-advice':'wAdvice',
        'scheme-title':'schemeTitle',
        'sch1-name':'sch1n','sch1-desc':'sch1d',
        'sch2-name':'sch2n','sch2-desc':'sch2d',
        'sch3-name':'sch3n','sch3-desc':'sch3d',
        'sch4-name':'sch4n','sch4-desc':'sch4d',
        'sch5-name':'sch5n','sch5-desc':'sch5d',
        'sch6-name':'sch6n','sch6-desc':'sch6d',
        'scan-title':'scanTitle','scan-sub':'scanSub',
        'test-label':'testLabel','test-title':'testTitle','test-sub':'testSub',
        't1-auth':'t1a','t2-auth':'t2a','t3-auth':'t3a',
        'ft-tag':'ftTag','ft-copy':'ftCopy','bot-welcome':'botWelcome'
    };
    Object.entries(idKeyMap).forEach(([id, key]) => {
        const el = document.getElementById(id);
        if(el && t[key] !== undefined) el.textContent = t[key];
    });

    if(document.getElementById('wd-hum')) document.getElementById('wd-hum').innerHTML = t.wHum;
    if(document.getElementById('wd-wind')) document.getElementById('wd-wind').innerHTML = t.wWind;
    if(document.getElementById('wd-rain')) document.getElementById('wd-rain').innerHTML = t.wRain;
    if(document.getElementById('wd-vis')) document.getElementById('wd-vis').innerHTML = t.wVis;
    if(document.getElementById('t1-msg')) document.getElementById('t1-msg').innerHTML = t.t1m;
    if(document.getElementById('t2-msg')) document.getElementById('t2-msg').innerHTML = t.t2m;
    if(document.getElementById('t3-msg')) document.getElementById('t3-msg').innerHTML = t.t3m;

    if (typeof pageLanguageCallback === "function") { pageLanguageCallback(l); }

    // header.php defines updateHeaderTranslation() which translates the
    // Account dropdown (My Profile / My Activity / Logout / Login /
    // Register) and the "My Profile" modal (labels, placeholders, Guest
    // text). This file defines its own switchLanguage() that runs instead
    // of header.php's, so without this call none of that ever translated.
    if (typeof updateHeaderTranslation === "function") { updateHeaderTranslation(l); }
}

let activeSlide = 0;
let sliderTimer;

function goSlide(n) {
    const slides = document.querySelectorAll('.slide');
    const dotsWrap = document.getElementById('sliderDots');
    if(!slides.length || !dotsWrap) return;
    slides[activeSlide].classList.remove('active');
    if(dotsWrap.children[activeSlide]) dotsWrap.children[activeSlide].classList.remove('active');
    activeSlide = (n + slides.length) % slides.length;
    slides[activeSlide].classList.add('active');
    if(dotsWrap.children[activeSlide]) dotsWrap.children[activeSlide].classList.add('active');
}
function nextSlide() { goSlide(activeSlide + 1); }
function startSlider() { sliderTimer = setInterval(nextSlide, 2200); }

function initSlider() {
    const slides = document.querySelectorAll('.slide');
    const dotsWrap = document.getElementById('sliderDots');
    const sliderWrap = document.querySelector('.slider-wrap');
    if(!slides.length || !dotsWrap) return;

    // Preload all slide images so no delay
    slides.forEach(slide => {
        const bg = slide.style.backgroundImage;
        const match = bg.match(/url\(['"]?([^'"]+)['"]?\)/);
        if(match && match[1]) {
            const img = new Image();
            img.src = match[1];
        }
    });

    dotsWrap.innerHTML = '';
    slides.forEach((_,i) => {
        const d = document.createElement('div');
        d.className = 'dot' + (i === 0 ? ' active' : '');
        d.onclick = () => { clearInterval(sliderTimer); goSlide(i); startSlider(); };
        dotsWrap.appendChild(d);
    });
    // Show the first slide immediately instead of waiting for the first
    // auto-advance (goSlide/nextSlide only fire after 4s otherwise, leaving
    // the slider blank until then).
    slides[activeSlide].classList.add('active');
    if(sliderWrap) {
        sliderWrap.addEventListener('mouseenter', () => clearInterval(sliderTimer));
        sliderWrap.addEventListener('mouseleave', () => startSlider());
    }
    startSlider();
}

document.addEventListener('DOMContentLoaded', initSlider);

function toggleDropdown() { document.getElementById('profileDropdown').classList.toggle('show'); }
document.addEventListener('click', e => {
    if (document.querySelector('.user-profile-wrap') && !document.querySelector('.user-profile-wrap').contains(e.target)) {
        if(document.getElementById('profileDropdown')) document.getElementById('profileDropdown').classList.remove('show');
    }
});
function openModal() {
    document.getElementById('profileModal').classList.add('open');
    document.getElementById('profileDropdown').classList.remove('show');
    // Always reopen on the view (read-only) side, not mid-edit.
    cancelEditMode();
}
function closeModal() { document.getElementById('profileModal').classList.remove('open'); }
if(document.getElementById('profileModal')) {
    document.getElementById('profileModal').addEventListener('click', e => { if (e.target === document.getElementById('profileModal')) closeModal(); });
}

// Default (English) fallback strings — overwritten by header.php's
// updateHeaderTranslation() as soon as it runs, and kept in sync with the
// language the user has selected via window.AGRI_PROFILE_T.
function profileT() {
    return window.AGRI_PROFILE_T || {
        saveBtn: 'Save Changes', saving: 'Saving...',
        changePwdToggle: 'Change Password', changePwdToggleClose: 'Cancel Password Change',
        errName: 'Please enter your name.', errMobile: 'Please enter a valid 10-digit mobile number.',
        errEmail: 'Please enter a valid email address.', errPwdShort: 'New password must be at least 6 characters.',
        errPwdMismatch: 'New password and confirm password do not match.',
        errPwdRequired: "Please enter your current password to change your password.",
        errSaveFailed: 'Could not save profile. Please try again.', errNetwork: 'Network error. Please try again.',
        successMsg: 'Profile updated successfully!'
    };
}

function togglePasswordSection() {
    const section = document.getElementById('passwordSection');
    const textEl  = document.getElementById('togglePwdText');
    if (!section) return;
    const t = profileT();
    const opening = section.style.display === 'none';
    section.style.display = opening ? 'block' : 'none';
    if (textEl) textEl.textContent = opening ? t.changePwdToggleClose : t.changePwdToggle;
    if (!opening) {
        ['input-current-password', 'input-new-password', 'input-confirm-password'].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.value = '';
        });
    }
}

// ========== EDIT PROFILE — view/edit mode toggle ==========
let selectedProfilePhotoFile = null;

function enterEditMode() {
    const view = document.getElementById('profileViewMode');
    const edit = document.getElementById('profileEditMode');
    if (!view || !edit) return;
    view.style.display = 'none';
    edit.style.display = 'block';
    showProfileMsg('', false);
}

function cancelEditMode() {
    const view = document.getElementById('profileViewMode');
    const edit = document.getElementById('profileEditMode');
    if (!view || !edit) return;
    view.style.display = 'block';
    edit.style.display = 'none';
    showProfileMsg('', false);
    // Discard any unsaved photo pick / password fields so re-opening
    // Edit Profile always starts clean from what's actually saved.
    selectedProfilePhotoFile = null;
    const fileInput = document.getElementById('input-photo');
    if (fileInput) fileInput.value = '';
    ['input-current-password', 'input-new-password', 'input-confirm-password'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    const pwdSection = document.getElementById('passwordSection');
    if (pwdSection) pwdSection.style.display = 'none';
}

// ---- Profile photo preview ----
(function () {
    const fileInput = document.getElementById('input-photo');
    if (!fileInput) return;
    fileInput.addEventListener('change', () => {
        const file = fileInput.files && fileInput.files[0];
        if (!file) return;
        const allowed = ['image/jpeg', 'image/png', 'image/webp'];
        if (!allowed.includes(file.type)) {
            showProfileMsg('Please choose a JPG, PNG or WebP image.', true);
            fileInput.value = '';
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            showProfileMsg('Image must be smaller than 2MB.', true);
            fileInput.value = '';
            return;
        }
        selectedProfilePhotoFile = file;
        const reader = new FileReader();
        reader.onload = e => {
            const wrap = document.getElementById('photoPreviewWrap');
            if (wrap) wrap.innerHTML = `<img src="${e.target.result}" alt="">`;
        };
        reader.readAsDataURL(file);
    });
})();

// ---- Searchable Primary Crop select ----
(function () {
    const box   = document.getElementById('cropSelectBox');
    const input = document.getElementById('input-crop-search');
    const hidden = document.getElementById('input-crop');
    const panel = document.getElementById('cropSelectPanel');
    if (!box || !input || !hidden || !panel) return;
    const options = Array.from(panel.querySelectorAll('.searchable-select-option'));

    function openPanel() { box.classList.add('open'); }
    function closePanel() { box.classList.remove('open'); }

    input.addEventListener('focus', () => { openPanel(); filterOptions(); });
    input.addEventListener('input', () => { hidden.value = ''; filterOptions(); openPanel(); });
    document.addEventListener('click', e => { if (!box.contains(e.target)) closePanel(); });

    function filterOptions() {
        const q = input.value.trim().toLowerCase();
        options.forEach(opt => {
            const match = opt.dataset.value.toLowerCase().includes(q);
            opt.classList.toggle('hidden', !match);
        });
    }

    options.forEach(opt => {
        opt.addEventListener('click', () => {
            hidden.value = opt.dataset.value;
            input.value = opt.dataset.value;
            closePanel();
        });
    });
})();

function showProfileMsg(text, isError) {
    const box = document.getElementById('profileMsg');
    if (!box) { if (text) alert(text); return; }
    if (!text) { box.style.display = 'none'; return; }
    box.textContent = text;
    box.style.display = 'block';
    box.style.background = isError ? '#fdecea' : '#e8f5e9';
    box.style.color = isError ? '#c62828' : '#2e7d32';
}

function saveProfile() {
    const t = profileT();
    const name     = document.getElementById('input-name').value.trim();
    const email    = document.getElementById('input-email') ? document.getElementById('input-email').value.trim() : '';
    const mobile   = document.getElementById('input-mobile').value.trim();
    const crop     = document.getElementById('input-crop') ? document.getElementById('input-crop').value.trim() : '';
    const line1    = document.getElementById('input-line1') ? document.getElementById('input-line1').value.trim() : '';
    const line2    = document.getElementById('input-line2') ? document.getElementById('input-line2').value.trim() : '';
    const village  = document.getElementById('input-village') ? document.getElementById('input-village').value.trim() : '';
    const city     = document.getElementById('input-city') ? document.getElementById('input-city').value.trim() : '';
    const district = document.getElementById('input-district') ? document.getElementById('input-district').value.trim() : '';
    const state    = document.getElementById('input-state') ? document.getElementById('input-state').value.trim() : '';
    const pincode  = document.getElementById('input-pincode') ? document.getElementById('input-pincode').value.trim() : '';
    const currentPassword = document.getElementById('input-current-password') ? document.getElementById('input-current-password').value : '';
    const newPassword     = document.getElementById('input-new-password') ? document.getElementById('input-new-password').value : '';
    const confirmPassword = document.getElementById('input-confirm-password') ? document.getElementById('input-confirm-password').value : '';

    showProfileMsg('', false);

    if (!name) { showProfileMsg(t.errName, true); return; }
    if (email && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { showProfileMsg(t.errEmail, true); return; }
    if (mobile && !/^[6-9]\d{9}$/.test(mobile)) { showProfileMsg(t.errMobile, true); return; }
    if (pincode && !/^\d{6}$/.test(pincode)) { showProfileMsg(t.errPincode || 'Please enter a valid 6-digit PIN code.', true); return; }

    const wantsPasswordChange = newPassword !== '' || confirmPassword !== '';
    if (wantsPasswordChange) {
        if (!currentPassword) { showProfileMsg(t.errPwdRequired, true); return; }
        if (newPassword.length < 6) { showProfileMsg(t.errPwdShort, true); return; }
        if (newPassword !== confirmPassword) { showProfileMsg(t.errPwdMismatch, true); return; }
    }

    const base = window.AGRI_BASE_PATH || '';
    const btn = document.getElementById('saveProfileBtn');
    if (btn) { btn.disabled = true; btn.textContent = t.saving; }

    const csrfInput = document.querySelector('#profileEditMode input[name="csrf_token"]');
    const formData = new FormData();
    if (csrfInput) formData.set('csrf_token', csrfInput.value);
    formData.set('name', name);
    formData.set('email', email);
    formData.set('mobile', mobile);
    formData.set('primary_crop', crop);
    formData.set('address_line1', line1);
    formData.set('address_line2', line2);
    formData.set('village', village);
    formData.set('city', city);
    formData.set('district', district);
    formData.set('state', state);
    formData.set('pincode', pincode);
    if (selectedProfilePhotoFile) formData.set('profile_photo', selectedProfilePhotoFile);
    if (wantsPasswordChange) {
        formData.set('current_password', currentPassword);
        formData.set('new_password', newPassword);
        formData.set('confirm_password', confirmPassword);
    }

    fetch(base + '/pages/update_profile.php', {
        method: 'POST',
        headers: { 'X-CSRF-Token': csrfInput ? csrfInput.value : '' },
        body: formData
    })
    .then(r => r.json())
    .then(data => {
        // Server returns a message_key (errName, errEmailTaken, etc.) that
        // maps straight onto the profile translation object, so the message
        // always shows in whichever language is currently selected.
        const localized = data.message_key && t[data.message_key] ? t[data.message_key] : (data.message || t.errSaveFailed);
        if (data.success) {
            // Navbar — updates immediately, matches the requirement that a
            // name change is reflected across the site without a reload.
            localStorage.setItem('agri_user_name', data.name);
            const headerUsername = document.getElementById('header-username');
            if (headerUsername) headerUsername.textContent = data.name;
            document.getElementById('modal-name').textContent = data.name;

            // View-mode chips + form fields, so re-opening the modal (or
            // switching back out of edit mode) shows exactly what was saved.
            const setVal = (id, val) => { const el = document.getElementById(id); if (el) el.value = val; };
            const setTxt = (id, val) => { const el = document.getElementById(id); if (el) el.textContent = (val && val !== '') ? val : '—'; };
            setVal('input-mobile', data.mobile || '');
            if (document.getElementById('input-email')) setVal('input-email', data.email || '');
            setTxt('view-email', data.email || '');
            setTxt('view-mobile', data.mobile || '');
            setTxt('view-crop', data.crop || '');
            setTxt('view-address', data.address || '');

            // Profile summary card / navbar avatar — reflect a newly
            // uploaded photo immediately, no page refresh needed.
            if (data.photo) {
                const photoUrl = base + '/' + data.photo;
                const modalAvatar = document.getElementById('modal-avatar');
                if (modalAvatar) modalAvatar.innerHTML = `<img src="${photoUrl}" alt="">`;
                const navAvatar = document.querySelector('.user-profile-wrap .profile-icon, .user-profile-wrap img');
                if (navAvatar && navAvatar.tagName === 'IMG') navAvatar.src = photoUrl;
            }
            selectedProfilePhotoFile = null;

            ['input-current-password', 'input-new-password', 'input-confirm-password'].forEach(id => {
                const el = document.getElementById(id);
                if (el) el.value = '';
            });
            showProfileMsg(t.successMsg, false);
            // Switch back to the view (read-only) side after a beat, without
            // wiping the success message the way a full cancelEditMode() would.
            const fileInput = document.getElementById('input-photo');
            if (fileInput) fileInput.value = '';
            setTimeout(() => {
                const view = document.getElementById('profileViewMode');
                const edit = document.getElementById('profileEditMode');
                if (view) view.style.display = 'block';
                if (edit) edit.style.display = 'none';
            }, 900);
        } else {
            showProfileMsg(localized, true);
        }
    })
    .catch(() => showProfileMsg(t.errNetwork, true))
    .finally(() => { if (btn) { btn.disabled = false; btn.textContent = t.saveBtn; } });
}

// ========== MY HISTORY MODAL (orders + rental bookings) ==========
let historyData = null;
let activeHistoryTab = 'orders';

function openHistory() {
    document.getElementById('profileModal').classList.remove('open');
    const modal = document.getElementById('historyModal');
    if (!modal) return;
    modal.classList.add('open');
    if (!historyData) {
        loadHistory();
    } else {
        renderHistoryTab();
    }
}
function closeHistory() {
    const modal = document.getElementById('historyModal');
    if (modal) modal.classList.remove('open');
}
if (document.getElementById('historyModal')) {
    document.getElementById('historyModal').addEventListener('click', e => {
        if (e.target === document.getElementById('historyModal')) closeHistory();
    });
}

function switchHistoryTab(tab) {
    activeHistoryTab = tab;
    document.getElementById('tab-btn-orders').classList.toggle('active', tab === 'orders');
    document.getElementById('tab-btn-bookings').classList.toggle('active', tab === 'bookings');
    renderHistoryTab();
}

function loadHistory() {
    const base = window.AGRI_BASE_PATH || '';
    const body = document.getElementById('historyBody');
    body.innerHTML = '<div class="history-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading your history...</div>';
    fetch(base + '/pages/get_my_history.php')
        .then(r => r.json())
        .then(data => {
            historyData = data;
            renderHistoryTab();
        })
        .catch(() => {
            body.innerHTML = '<div class="history-empty">Could not load history. Please try again.</div>';
        });
}

function renderHistoryTab() {
    const body = document.getElementById('historyBody');
    if (!historyData) { body.innerHTML = '<div class="history-empty">No data.</div>'; return; }

    if (activeHistoryTab === 'orders') {
        const orders = historyData.orders || [];
        if (!orders.length) {
            body.innerHTML = '<div class="history-empty"><i class="fa-solid fa-box-open" style="font-size:22px;display:block;margin-bottom:8px;opacity:.5;"></i>No orders placed yet.</div>';
            return;
        }
        body.innerHTML = orders.map(o => {
            const items = (o.items || []).map(it => `${it.product_name} × ${it.quantity}`).join(', ') || '—';
            const statusClass = (o.order_status || '').toLowerCase() === 'cancelled' ? 'cancelled' :
                                 (o.order_status || '').toLowerCase() === 'placed' ? 'pending' : '';
            return `<div class="hist-card">
                <div class="hist-head">
                    <span>#${escapeHtml(o.order_number || o.id)}</span>
                    <span class="hist-status ${statusClass}">${escapeHtml(o.order_status || '')}</span>
                </div>
                <div class="hist-date">${escapeHtml(o.created_at || '')}</div>
                <div class="hist-items">${escapeHtml(items)}</div>
                <div class="hist-foot">
                    <span>${escapeHtml((o.payment_mode || '').toUpperCase())} · ${escapeHtml(o.payment_status || '')}</span>
                    <span class="hist-amount">₹${Number(o.total_amount || 0).toLocaleString('en-IN')}</span>
                </div>
            </div>`;
        }).join('');
    } else {
        const bookings = historyData.bookings || [];
        if (!bookings.length) {
            body.innerHTML = '<div class="history-empty"><i class="fa-solid fa-tractor" style="font-size:22px;display:block;margin-bottom:8px;opacity:.5;"></i>No equipment bookings yet.</div>';
            return;
        }
        body.innerHTML = bookings.map(b => {
            const statusClass = (b.status || '').toLowerCase() === 'cancelled' ? 'cancelled' :
                                 (b.status || '').toLowerCase() === 'pending' ? 'pending' : '';
            return `<div class="hist-card">
                <div class="hist-head">
                    <span>${escapeHtml(b.equipment_name || 'Equipment')}</span>
                    <span class="hist-status ${statusClass}">${escapeHtml(b.status || '')}</span>
                </div>
                <div class="hist-date">Booking ${escapeHtml(b.booking_number || ('#' + b.id))}</div>
                <div class="hist-items">${escapeHtml(b.from_date_fmt || '')} → ${escapeHtml(b.to_date_fmt || '')} (${escapeHtml(String(b.total_days || ''))} day${b.total_days == 1 ? '' : 's'})</div>
                <div class="hist-foot">
                    <span>&nbsp;</span>
                    <span class="hist-amount">₹${Number(b.total_amount || 0).toLocaleString('en-IN')}</span>
                </div>
            </div>`;
        }).join('');
    }
}

function escapeHtml(str) {
    return String(str).replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
}

function calcSubsidy() {
    if(!document.getElementById('loanAmount')) return;
    const amt = parseFloat(document.getElementById('loanAmount').value) || 0;
    const sub = Math.round(amt * 0.25);
    const label = lang === 'mr' ? `शासकीय सबसिडी (२५%): <b>₹${sub.toLocaleString('mr-IN')}</b>` : lang === 'hi' ? `राज्य सब्सिडी (25%): <b>₹${sub.toLocaleString('hi-IN')}</b>` : `State Subsidy (25%): <b>₹${sub.toLocaleString('en-IN')}</b>`;
    document.getElementById('calc-result').innerHTML = label;
}

function doSearch() {
    if(!document.getElementById('hero-search-input')) return;
    const q = document.getElementById('hero-search-input').value.trim();
    const base = window.AGRI_BASE_PATH || '';
    if (q) window.location.href = base + '/pages/marketplace.php?search=' + encodeURIComponent(q);
}
if(document.getElementById('hero-search-input')) {
    document.getElementById('hero-search-input').addEventListener('keypress', e => { if (e.key === 'Enter') doSearch(); });
}

function openLeafScanner() {
    window.location.href = 'pages/advisory.php';
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.AGRI_LOGGED_IN === false) {
        // Session says logged out — clear any stale saved profile name
        // so it doesn't leak into the header or a future guest session.
        localStorage.removeItem('agri_user_name');
    }
    // Note: when logged in, the name/mobile/address fields are already
    // filled server-side (from the session/DB) in header.php, so we no
    // longer overwrite them with localStorage here — that could show a
    // stale name left over from a previous user on a shared browser.
    const sel = document.getElementById('langSelector');
    if(sel) {
        sel.value = lang;
        Array.from(sel.options).forEach(o => o.selected = (o.value === lang));
    }
    // setTimeout ensures pageLanguageCallback (defined in page scripts) is ready
    setTimeout(() => switchLanguage(lang), 0);
});