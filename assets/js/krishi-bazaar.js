/* ================================================================
   KRISHI BAZAAR - krishi-bazaar.js
   Full master DB + dynamic rendering + language switch + price alerts
   ================================================================ */
console.log('%c[KB-JS] Loaded FIXED version v2 — custom dropdown + scroll fix active', 'background:#1b5e20;color:#fff;padding:4px 8px;border-radius:4px;font-weight:bold;');

/* ──────────────────────────────────────────────
   1. CROP IMAGE ENGINE
────────────────────────────────────────────── */
const CROP_EMOJI = {
  'Onion':'🧅','Tomato':'🍅','Potato':'🥔','Soybean':'🌱','Wheat':'🌾',
  'Rice':'🍚','Sugarcane':'🎋','Cotton':'🌿','Grapes':'🍇','Mango':'🥭',
  'Pomegranate':'❤️','Banana':'🍌','Chilli':'🌶️','Turmeric':'🟡',
  'Garlic':'🧄','Ginger':'🟤','Groundnut':'🥜','Maize':'🌽','Bajra':'🌾',
  'Jowar':'🌾','Tur Dal':'🟤','Urad Dal':'⚪','Moong Dal':'🟢','Coriander':'🌿',
  'Fenugreek':'🟡','Capsicum':'🫑','Brinjal':'🍆','Cauliflower':'🥦',
  'Cabbage':'🥬','Ladyfinger':'🟢','Peas':'🟢','Spinach':'🌿',
  'Watermelon':'🍉','Papaya':'🍈','Guava':'🍏','Orange':'🍊',
  'Lemon':'🍋','Sweet Potato':'🟠','Carrot':'🥕','Radish':'⚪',
  'Beetroot':'🔴','Drumstick':'🟢','Curry Leaf':'🌿','Coconut':'🥥',
  'Cashew':'🟤','Sunflower':'🌻','Sesame':'⚪','Linseed':'🟤',
  'Peanut':'🥜','Tur':'🟤'
};
function getCropImage(name) {
  return CROP_EMOJI[name] || '🌾';
}

// Real product photos for crop cards/tables. Falls back to the emoji
// (via onerror) if a photo is missing, so nothing ever shows broken.
const CROP_PHOTO_FILE = {
  'Onion':'onion.png','Tomato':'tomato.png','Potato':'potato.png','Soybean':'soybean.png',
  'Wheat':'wheat.png','Rice':'rice.png','Sugarcane':'sugarcane.png','Cotton':'cotton.png',
  'Grapes':'grapes.png','Mango':'mango.png','Pomegranate':'pomegranate.png','Banana':'banana.png',
  'Chilli':'chilli.png','Turmeric':'turmeric.png','Garlic':'garlic.png','Ginger':'ginger.png',
  'Groundnut':'groundnut.png','Maize':'maize.png','Bajra':'bajra.png','Jowar':'jowar.png',
  'Tur Dal':'tur-dal.png','Urad Dal':'urad-dal.png','Moong Dal':'moong-dal.png','Coriander':'coriander.png',
  'Fenugreek':'fenugreek.png','Capsicum':'capsicum.png','Brinjal':'brinjal.png','Cauliflower':'cauliflower.png',
  'Cabbage':'cabbage.png','Ladyfinger':'ladyfinger.png','Peas':'peas.png','Spinach':'spinach.png',
  'Watermelon':'watermelon.png','Papaya':'papaya.png','Guava':'guava.png','Orange':'orange.png',
  'Lemon':'lemon.png','Sweet Potato':'sweet-potato.png','Carrot':'carrot.png','Radish':'radish.png',
  'Beetroot':'beetroot.png','Drumstick':'drumstick.png','Curry Leaf':'curry-leaf.png','Coconut':'coconut.png',
  'Cashew':'cashew.png','Sunflower':'sunflower.png','Sesame':'sesame.png','Linseed':'linseed.png',
  'Peanut':'peanut.png','Tur':'tur.png'
};
function getCropImageTag(name, className) {
  const file = CROP_PHOTO_FILE[name];
  const emojiFallback = CROP_EMOJI[name] || '🌾';
  if (!file) return emojiFallback;
  const base = (typeof KB_BASE !== 'undefined') ? KB_BASE : '';
  return `<img class="${className || ''}" src="${base}/assets/images/crops/${file}" alt="${name}" loading="lazy" onerror="this.outerHTML='${emojiFallback}'">`;
}

/* ──────────────────────────────────────────────
   1b. CUSTOM DROPDOWN ENGINE
   Native <select> popups can't (a) reliably be forced
   to open downward, and (b) show real images inside
   options. This replaces every <select> on the page with
   a small custom dropdown that always opens downward and
   can show a real crop photo (with emoji fallback) next
   to each option, while keeping the original <select>'s
   value/onchange working exactly as before.
────────────────────────────────────────────── */
function kbCropIconHtml(value) {
  if (!value) return '';
  const file = CROP_PHOTO_FILE[value];
  const emoji = CROP_EMOJI[value] || '🌾';
  const base = (typeof KB_BASE !== 'undefined') ? KB_BASE : '';
  const imgStyle = 'width:20px;height:20px;min-width:20px;max-width:20px;max-height:20px;object-fit:cover;border-radius:4px;flex-shrink:0;display:block;';
  const emojiStyle = 'font-size:16px;flex-shrink:0;line-height:1;';
  if (file) {
    return `<img class="kb-cs-icon" style="${imgStyle}" src="${base}/assets/images/crops/${file}" alt="" loading="lazy" onerror="this.outerHTML='<span class=&quot;kb-cs-emoji&quot; style=&quot;${emojiStyle}&quot;>${emoji}</span>'">`;
  }
  return `<span class="kb-cs-emoji" style="${emojiStyle}">${emoji}</span>`;
}

function kbBuildCustomSelect(select, options = {}) {
  const wrapper = document.createElement('div');
  wrapper.className = 'kb-custom-select';
  wrapper.style.cssText = 'position:relative;display:inline-block;width:100%;';

  const trigger = document.createElement('button');
  trigger.type = 'button';
  trigger.className = 'kb-custom-select__trigger';
  trigger.style.cssText = 'display:flex;align-items:center;justify-content:space-between;gap:10px;width:100%;max-height:44px;padding:10px 14px;border:2px solid #4CAF50;border-radius:10px;background:#fff;color:#1a3c1a;cursor:pointer;font-size:14px;font-family:inherit;text-align:left;overflow:hidden;box-sizing:border-box;';

  const label = document.createElement('span');
  label.className = 'kb-custom-select__label';
  label.style.cssText = 'display:flex;align-items:center;gap:8px;max-height:22px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;';

  const arrow = document.createElement('span');
  arrow.className = 'kb-custom-select__arrow';
  arrow.style.cssText = 'flex-shrink:0;font-size:11px;color:#4CAF50;transition:transform .2s ease;';
  arrow.textContent = '▾';

  trigger.appendChild(label);
  trigger.appendChild(arrow);

  const panel = document.createElement('div');
  panel.className = 'kb-custom-select__panel';
  panel.style.cssText = 'position:fixed;background:#fff;border:1.5px solid #d8e6d4;border-radius:10px;box-shadow:0 14px 32px rgba(0,0,0,.18);max-height:280px;overflow-y:auto;z-index:20000;padding:6px;display:none;';

  wrapper.appendChild(trigger);
  wrapper.appendChild(panel);

  select.style.display = 'none';
  select.insertAdjacentElement('afterend', wrapper);

  wrapper._kbGetIcon = options.getIcon || null;

  function buildOptions() {
    panel.innerHTML = '';
    Array.from(select.options).forEach(opt => {
      const item = document.createElement('div');
      const isActive = opt.value === select.value;
      item.className = 'kb-custom-select__option' + (isActive ? ' active' : '');
      item.style.cssText = `display:flex;align-items:center;gap:8px;padding:9px 10px;border-radius:8px;cursor:pointer;font-size:13.5px;white-space:nowrap;${isActive ? 'background:#e6f4e3;color:#1b5e20;font-weight:600;' : 'color:#333;'}`;
      item.addEventListener('mouseenter', () => { if (!isActive) item.style.background = '#f0f9ee'; });
      item.addEventListener('mouseleave', () => { if (!isActive) item.style.background = ''; });
      const iconHtml = wrapper._kbGetIcon ? wrapper._kbGetIcon(opt.value) : '';
      item.innerHTML = `${iconHtml}<span>${opt.textContent}</span>`;
      item.addEventListener('click', () => {
        select.value = opt.value;
        select.dispatchEvent(new Event('change', { bubbles: true }));
        closePanel();
        syncLabel();
      });
      panel.appendChild(item);
    });
  }

  function syncLabel() {
    const opt = select.options[select.selectedIndex];
    const iconHtml = (wrapper._kbGetIcon && opt) ? wrapper._kbGetIcon(opt.value) : '';
    label.innerHTML = `${iconHtml}<span>${opt ? opt.textContent : ''}</span>`;
  }

  function positionPanel() {
    const r = trigger.getBoundingClientRect();
    panel.style.left = r.left + 'px';
    panel.style.top = (r.bottom + 6) + 'px';
    panel.style.width = Math.max(r.width, 180) + 'px';
  }

  function openPanel() {
    document.querySelectorAll('.kb-custom-select.open').forEach(w => {
      if (w !== wrapper) {
        w.classList.remove('open');
        if (w._kbPanelEl) w._kbPanelEl.style.display = 'none';
      }
    });
    buildOptions();
    positionPanel();
    wrapper.classList.add('open');
    panel.style.display = 'block';
    arrow.style.transform = 'rotate(180deg)';
    window.addEventListener('scroll', positionPanel, true);
    window.addEventListener('resize', positionPanel);
  }
  function closePanel() {
    wrapper.classList.remove('open');
    panel.style.display = 'none';
    arrow.style.transform = 'rotate(0deg)';
    window.removeEventListener('scroll', positionPanel, true);
    window.removeEventListener('resize', positionPanel);
  }
  wrapper._kbPanelEl = panel;

  trigger.addEventListener('click', (e) => {
    e.stopPropagation();
    wrapper.classList.contains('open') ? closePanel() : openPanel();
  });
  document.addEventListener('click', (e) => {
    if (!wrapper.contains(e.target)) closePanel();
  });

  wrapper._kbSync = () => { buildOptions(); syncLabel(); };
  select.dataset.kbEnhanced = '1';
  syncLabel();
}

function kbEnhanceAllSelects(root) {
  // IMPORTANT: default scope is #kb-main only — this page's own content.
  // Never touch selects that live in the shared header/footer (e.g. the
  // site-wide language selector #langSelector), which belong to a
  // completely different script (header.php / agri-master.js) and must
  // not be wrapped by this page's custom dropdown.
  const scopeRoot = root || document.getElementById('kb-main') || document;
  scopeRoot.querySelectorAll('select').forEach(sel => {
    if (sel.id === 'langSelector') return; // never touch the header language selector
    if (sel.closest('.kb-custom-select')) return; // safety: never re-wrap
    if (sel.dataset.kbEnhanced === '1') {
      // Already wrapped earlier — just refresh its options/label (e.g. after language switch)
      const wrapper = sel.nextElementSibling;
      if (wrapper && wrapper.classList && wrapper.classList.contains('kb-custom-select') && wrapper._kbSync) {
        wrapper._kbSync();
      }
      return;
    }
    const isCropSelect = ['trendCrop', 'alertCrop'].includes(sel.id);
    kbBuildCustomSelect(sel, isCropSelect ? { getIcon: kbCropIconHtml } : {});
  });
}

/* ──────────────────────────────────────────────
   2. TRANSLATIONS (INCLUDING DB ITEMS)
────────────────────────────────────────────── */
const TRANSLATIONS = {
  en: {
    verified:'✅ Verified', organic:'🌿 Organic', viewDetails:'View Details',
    contact:'📞 Contact', sales:'Sales', rating:'Rating', demandScore:'Demand',
    weeklyGrowth:'Weekly Growth', hotTrend:'🔥 Hot',
    noResults:'No products found. Try adjusting your filters.',
    alertSaved:'✅ Price alert saved!', alertDeleted:'🗑️ Alert deleted',
    enterPrice:'Please enter a target price.',
    perQtl:'/ qtl', qty:'Qty', farmer:'Farmer', location:'Location',
    justNow:'Just now', minsAgo:'mins ago', hoursAgo:'hrs ago',
    avgPrice:'Avg Price', highPrice:'High', lowPrice:'Low'
  },
  mr: {
    verified:'✅ सत्यापित', organic:'🌿 सेंद्रिय', viewDetails:'तपशील पाहा',
    contact:'📞 संपर्क करा', sales:'विक्री', rating:'रेटिंग', demandScore:'मागणी',
    weeklyGrowth:'साप्ताहिक वाढ', hotTrend:'🔥 हॉट',
    noResults:'कोणतेही उत्पादन सापडले नाही. फिल्टर बदला.',
    alertSaved:'✅ भाव अलर्ट सेव्ह झाला!', alertDeleted:'🗑️ अलर्ट हटवला',
    enterPrice:'कृपया लक्ष्य भाव टाका.',
    perQtl:'/ क्विंटल', qty:'प्रमाण', farmer:'शेतकरी', location:'ठिकाण',
    justNow:'आत्ताच', minsAgo:'मि. पूर्वी', hoursAgo:'तास पूर्वी',
    avgPrice:'सरासरी भाव', highPrice:'जास्तीत जास्त', lowPrice:'कमीत कमी',

    'Onion':'कांदा', 'Tomato':'टोमॅटो', 'Potato':'बटाटा', 'Soybean':'सोयाबीन', 'Wheat':'गहू', 
    'Rice':'तांदूळ', 'Sugarcane':'ऊस', 'Cotton':'कापूस', 'Grapes':'द्राक्षे', 'Mango':'आंबा', 
    'Pomegranate':'डाळिंब', 'Banana':'केळी', 'Chilli':'मिरची', 'Turmeric':'हळद', 'Garlic':'लसूण', 
    'Ginger':'आले', 'Groundnut':'भुईमूग', 'Maize':'मका', 'Bajra':'बाजरी', 'Jowar':'ज्वारी', 
    'Tur Dal':'तूर डाळ', 'Urad Dal':'उडीद डाळ', 'Moong Dal':'मूग डाळ', 'Coriander':'कोथिंबीर', 
    'Fenugreek':'मेथी', 'Capsicum':'ढोबळी मिरची', 'Brinjal':'वांगी', 'Cauliflower':'फ्लॉवर', 
    'Cabbage':'कोबी', 'Ladyfinger':'भेंडी', 'Peas':'वाटाणा', 'Spinach':'पालक', 'Watermelon':'कलिंगड', 
    'Papaya':'पपई', 'Guava':'पेरू', 'Orange':'संत्री', 'Lemon':'लिंबू', 'Sweet Potato':'रताळे', 
    'Carrot':'गाजर', 'Radish':'मुळा', 'Beetroot':'बीटरूट', 'Drumstick':'शेवगा', 'Curry Leaf':'कढीपत्ता', 
    'Coconut':'नारळ', 'Cashew':'काजू', 'Sunflower':'सूर्यफूल', 'Sesame':'तीळ', 'Linseed':'जवस', 
    'Peanut':'शेंगदाणा', 'Tur':'तूर',

    'Mumbai':'मुंबई', 'Mumbai Suburban':'मुंबई उपनगर', 'Pune':'पुणे', 'Nashik':'नाशिक', 
    'Nagpur':'नागपूर', 'Kolhapur':'कोल्हापूर', 'Aurangabad':'छत्रपती संभाजीनगर', 
    'Solapur':'सोलापूर', 'Satara':'सातारा', 'Ahmednagar':'अहमदनगर', 
    'Sangli':'सांगली', 'Jalgaon':'जळगाव', 'Amravati':'अमरावती', 'Akola':'अकोला', 
    'Latur':'लातूर', 'Nanded':'नांदेड', 'Palghar':'पालघर', 'Osmanabad':'धाराशिव',
    'Thane':'ठाणे', 'Raigad':'रायगड', 'Ratnagiri':'रत्नागिरी', 'Sindhudurg':'सिंधुदुर्ग',
    'Buldhana':'बुलढाणा', 'Washim':'वाशीम', 'Yavatmal':'यवतमाळ', 'Wardha':'वर्धा',
    'Bhandara':'भंडारा', 'Gondia':'गोंदिया', 'Chandrapur':'चंद्रपूर', 'Gadchiroli':'गडचिरोली',
    'Beed':'बीड', 'Hingoli':'हिंगोली', 'Parbhani':'परभणी', 'Jalna':'जालना',
    'Dhule':'धुळे', 'Nandurbar':'नंदुरबार',

    'Sangamner':'संगमनेर', 'Indapur':'इंदापूर', 'Yeola':'येवला', 'Baramati':'बारामती', 
    'Niphad':'निफाड', 'Karad':'कराड',

    'Ramesh Patil':'रमेश पाटील', 'Suresh Shinde':'सुरेश शिंदे', 'Anil Deshmukh':'अनिल देशमुख', 
    'Pramod Jadhav':'प्रमोद जाधव', 'Vijay Gaikwad':'विजय गायकवाड', 'Santosh More':'संतोष मोरे', 
    'Dilip Kulkarni':'दिलीप कुलकर्णी', 'Mahesh Bhosale':'महेश भोसले',

    'AgriPro Exports':'अ‍ॅग्रीप्रो एक्सपोर्ट्स', 'Fresh Mart Ltd':'फ्रेश मार्ट लि.', 
    'Nashik Traders':'नाशिक ट्रेडर्स', 'MH Agri Co-op':'एम.एच. अ‍ॅग्री को-ऑप', 
    'Spice World':'स्पाईस वर्ल्ड', 'Juice Plus Pvt.':'ज्यूस प्लस प्रा.'
  },
  hi: {
    verified:'✅ सत्यापित', organic:'🌿 जैविक', viewDetails:'विवरण देखें',
    contact:'📞 संपर्क करें', sales:'बिक्री', rating:'रेटिंग', demandScore:'मांग',
    weeklyGrowth:'साप्ताहिक वृद्धि', hotTrend:'🔥 हॉट',
    noResults:'कोई उत्पाद नहीं मिला। अपना फ़िल्टर बदलें।',
    alertSaved:'✅ भाव अलर्ट सेव हो गया!', alertDeleted:'🗑️ अलर्ट हटा दिया गया',
    enterPrice:'कृपया लक्ष्य भाव दर्ज करें।',
    perQtl:'/ क्विंटल', qty:'मात्रा', farmer:'किसान', location:'स्थान',
    justNow:'अभी-अभी', minsAgo:'मिनट पहले', hoursAgo:'घंटे पहले',
    avgPrice:'औसत भाव', highPrice:'उच्चतम भाव', lowPrice:'न्यूनतम भाव',

    'Onion':'प्याज', 'Tomato':'टमाटर', 'Potato':'आलू', 'Soybean':'सोयाबीन', 'Wheat':'गेहूं', 
    'Rice':'चावल', 'Sugarcane':'गन्ना', 'Cotton':'कपास', 'Grapes':'अंगूर', 'Mango':'आम', 
    'Pomegranate':'अनार', 'Banana':'केला', 'Chilli':'मिर्च', 'Turmeric':'हल्दी', 'Garlic':'लहसुन', 
    'Ginger':'अदरक', 'Groundnut':'मूंगफली', 'Maize':'मक्का', 'Bajra':'बाजरा', 'Jowar':'ज्वार', 
    'Tur Dal':'तूर दाल', 'Urad Dal':'उड़द दाल', 'Moong Dal':'मूंग दाल', 'Coriander':'धनिया', 
    'Fenugreek':'मेथी', 'Capsicum':'शिमला मिर्च', 'Brinjal':'बैंगन', 'Cauliflower':'फूलगोभी', 
    'Cabbage':'पत्तागोभी', 'Ladyfinger':'भिंडी', 'Peas':'मटर', 'Spinach':'पालक', 'Watermelon':'तरबूज', 
    'Papaya':'पपीता', 'Guava':'अमरूद', 'Orange':'संतरा', 'Lemon':'नींबू', 'Sweet Potato':'शकरकंद', 
    'Carrot':'गाजर', 'Radish':'मूली', 'Beetroot':'चुकंदर', 'Drumstick':'सहजन', 'Curry Leaf':'कड़ी पत्ता', 
    'Coconut':'नारियल', 'Cashew':'काजू', 'Sunflower':'सूरजमुखी', 'Sesame':'तिल', 'Linseed':'अलसी', 
    'Peanut':'मूंगफली', 'Tur':'तूर',

    'Mumbai':'मुंबई', 'Mumbai Suburban':'मुंबई उपनगर', 'Pune':'पुणे', 'Nashik':'नासिक', 
    'Nagpur':'नागपुर', 'Kolhapur':'कोल्हापुर', 'Aurangabad':'छत्रपति संभाजीनगर', 
    'Solapur':'सोलापुर', 'Satara':'सतारा', 'Ahmednagar':'अहमदनगर', 
    'Sangli':'सांगली', 'Jalgaon':'जलगांव', 'Amravati':'अमरावती', 'Akola':'अकोला', 
    'Latur':'लातूर', 'Nanded':'नांदेड़', 'Palghar':'पालघर', 'Osmanabad':'धाराशिव',
    'Thane':'ठाणे', 'Raigad':'रायगड', 'Ratnagiri':'रत्नागिरि', 'Sindhudurg':'सिंधुदुर्ग',
    'Buldhana':'बुलढाणा', 'Washim':'वाशिम', 'Yavatmal':'यवतमाल', 'Wardha':'वर्धा',
    'Bhandara':'भंडारा', 'Gondia':'गोंदिया', 'Chandrapur':'चंद्रपुर', 'Gadchiroli':'गडचिरोली',
    'Beed':'बीड', 'Hingoli':'हिंगोली', 'Parbhani':'परभणी', 'Jalna':'जालना',
    'Dhule':'धुले', 'Nandurbar':'नंदुरबार',

    'Sangamner':'संगमनेर', 'Indapur':'इंदापुर', 'Yeola':'येवला', 'Baramati':'बारामती', 
    'Niphad':'निफाड़', 'Karad':'कराड',

    'Ramesh Patil':'रमेश पाटिल', 'Suresh Shinde':'सुरेश शिंदे', 'Anil Deshmukh':'अनिल देशमुख', 
    'Pramod Jadhav':'प्रमोद जाधव', 'Vijay Gaikwad':'विजय गायकवाड़', 'Santosh More':'संतोष मोरे', 
    'Dilip Kulkarni':'दिलीप कुलकर्णी', 'Mahesh Bhosale':'महेश भोसले',

    'AgriPro Exports':'एग्रीप्रो एक्सपोर्ट्स', 'Fresh Mart Ltd':'फ्रेश मार्ट लिमिटेड', 
    'Nashik Traders':'नासिक ट्रेडर्स', 'MH Agri Co-op':'एम.एच. एग्री को-ऑप', 
    'Spice World':'स्पाइस वर्ल्ड', 'Juice Plus Pvt.':'जूस प्लस प्रा.'
  }
};
let currentLang = 'en';

function t(key) {
  if(!key) return '';
  return TRANSLATIONS[currentLang][key] || TRANSLATIONS.en[key] || key;
}

/* ──────────────────────────────────────────────
   3. MASTER DATABASE
────────────────────────────────────────────── */
const CITIES = [
  'Mumbai','Mumbai Suburban','Thane','Palghar','Raigad','Ratnagiri','Sindhudurg',
  'Pune','Nashik','Nagpur','Kolhapur','Aurangabad','Solapur','Satara',
  'Ahmednagar','Sangli','Jalgaon','Amravati','Akola','Latur','Nanded',
  'Buldhana','Washim','Yavatmal','Wardha','Bhandara','Gondia','Chandrapur',
  'Gadchiroli','Osmanabad','Beed','Hingoli','Parbhani','Jalna','Dhule','Nandurbar'
];

const DISTRICT_CROPS = {
  'Mumbai':['Onion','Tomato','Potato','Rice','Banana','Coconut'],
  'Mumbai Suburban':['Onion','Tomato','Rice','Banana','Coconut'],
  'Thane':['Rice','Banana','Coconut','Brinjal'],
  'Palghar':['Rice','Banana','Coconut','Chilli','Brinjal'],
  'Raigad':['Rice','Coconut','Cashew','Banana','Mango'],
  'Ratnagiri':['Mango','Cashew','Coconut','Rice'],
  'Sindhudurg':['Cashew','Coconut','Mango','Rice'],
  'Pune':['Onion','Tomato','Potato','Grapes','Wheat','Soybean','Cauliflower'],
  'Nashik':['Onion','Grapes','Tomato','Wheat','Maize','Orange','Pomegranate'],
  'Nagpur':['Orange','Cotton','Soybean','Wheat','Tur Dal','Banana'],
  'Kolhapur':['Sugarcane','Maize','Soybean','Tur Dal','Banana','Ginger','Turmeric'],
  'Aurangabad':['Sugarcane','Cotton','Soybean','Maize','Jowar','Bajra','Grapes'],
  'Solapur':['Pomegranate','Onion','Sugarcane','Jowar','Cotton','Tur Dal'],
  'Satara':['Potato','Tomato','Maize','Soybean','Ginger'],
  'Ahmednagar':['Onion','Sugarcane','Wheat','Maize','Bajra','Grapes'],
  'Sangli':['Grapes','Turmeric','Sugarcane','Onion','Pomegranate','Groundnut'],
  'Jalgaon':['Banana','Cotton','Maize','Wheat','Tur Dal','Groundnut'],
  'Amravati':['Cotton','Soybean','Tur Dal','Wheat','Jowar','Orange'],
  'Akola':['Cotton','Soybean','Wheat','Tur Dal','Jowar'],
  'Latur':['Tur Dal','Soybean','Jowar','Cotton','Onion','Pomegranate'],
  'Nanded':['Soybean','Tur Dal','Jowar','Cotton','Sugarcane','Banana'],
  'Buldhana':['Cotton','Soybean','Wheat','Tur Dal','Jowar','Orange'],
  'Washim':['Cotton','Soybean','Jowar','Tur Dal','Wheat'],
  'Yavatmal':['Cotton','Soybean','Tur Dal','Jowar','Wheat'],
  'Wardha':['Cotton','Soybean','Wheat','Tur Dal','Orange'],
  'Bhandara':['Rice','Wheat','Soybean','Tur Dal'],
  'Gondia':['Rice','Wheat','Soybean','Tur Dal','Tomato'],
  'Chandrapur':['Rice','Cotton','Soybean','Tur Dal','Maize'],
  'Gadchiroli':['Rice','Maize','Tur Dal'],
  'Osmanabad':['Tur Dal','Soybean','Jowar','Cotton','Onion','Pomegranate'],
  'Beed':['Sugarcane','Cotton','Soybean','Onion','Jowar','Tur Dal'],
  'Hingoli':['Soybean','Tur Dal','Cotton','Jowar'],
  'Parbhani':['Cotton','Soybean','Jowar','Tur Dal','Sugarcane'],
  'Jalna':['Maize','Soybean','Cotton','Jowar','Tur Dal'],
  'Dhule':['Maize','Cotton','Wheat','Groundnut','Banana'],
  'Nandurbar':['Maize','Banana','Cotton','Wheat','Rice'],
};

const COMMODITY_MAP = {
  'Onion':'Onion','Tomato':'Tomato','Potato':'Potato',
  'Soyabean':'Soybean','Soybean':'Soybean',
  'Wheat':'Wheat','Rice':'Rice','Paddy':'Rice',
  'Sugarcane':'Sugarcane','Cotton':'Cotton','Cotton(Seed)':'Cotton',
  'Grapes':'Grapes','Mango':'Mango','Mango (Raw-Ripe)':'Mango',
  'Pomegranate':'Pomegranate','Banana':'Banana','Banana - Green':'Banana',
  'Chilly':'Chilli','Chilli':'Chilli','Dry Chillies':'Chilli','Green Chilli':'Chilli',
  'Turmeric':'Turmeric','Garlic':'Garlic','Ginger(Dry)':'Ginger','Ginger(Green)':'Ginger',
  'Groundnut':'Groundnut','Ground Nut Seed':'Groundnut',
  'Maize':'Maize','Bajra':'Bajra','Jowar':'Jowar',
  'Arhar (Tur/Red Gram)(Whole)':'Tur Dal','Arhar (Tur/Red Gram)':'Tur Dal','Tur':'Tur Dal',
  'Urad':'Urad Dal','Urad Dal':'Urad Dal',
  'Moong':'Moong Dal','Moong Dal':'Moong Dal',
  'Coriander':'Coriander','Capsicum':'Capsicum','Brinjal':'Brinjal',
  'Cauliflower':'Cauliflower','Cabbage':'Cabbage',
  'Bhindi(Ladies Finger)':'Ladyfinger','Lady Finger':'Ladyfinger',
  'Peas Wet':'Peas','Water Melon':'Watermelon',
  'Papaya':'Papaya','Guava':'Guava',
  'Orange':'Orange','Mosambi(Sweet Lime)':'Orange',
  'Lemon':'Lemon','Carrot':'Carrot',
  'Coconut':'Coconut','Cashewnuts':'Cashew',
  'Sesamum(Sesame)':'Sesame','Drum Stick':'Drumstick',
  'Spinach':'Spinach','Sweet Potato':'Sweet Potato',
  'Sunflower Seed':'Sunflower',
};

const DISTRICT_MAP = {
  'Mumbai':'Mumbai','Greater Mumbai':'Mumbai',
  'Mumbai Suburban':'Mumbai Suburban','Thane':'Thane',
  'Palghar':'Palghar','Raigad':'Raigad','Ratnagiri':'Ratnagiri',
  'Sindhudurg':'Sindhudurg','Pune':'Pune','Nashik':'Nashik',
  'Nagpur':'Nagpur','Kolhapur':'Kolhapur','Aurangabad':'Aurangabad',
  'Solapur':'Solapur','Satara':'Satara','Ahmednagar':'Ahmednagar',
  'Sangli':'Sangli','Jalgaon':'Jalgaon','Amravati':'Amravati',
  'Akola':'Akola','Latur':'Latur','Nanded':'Nanded',
  'Buldhana':'Buldhana','Washim':'Washim','Yavatmal':'Yavatmal',
  'Wardha':'Wardha','Bhandara':'Bhandara','Gondia':'Gondia',
  'Chandrapur':'Chandrapur','Gadchiroli':'Gadchiroli',
  'Osmanabad':'Osmanabad','Beed':'Beed','Hingoli':'Hingoli',
  'Parbhani':'Parbhani','Jalna':'Jalna','Dhule':'Dhule',
  'Nandurbar':'Nandurbar',
};

let LIVE_DISTRICT_RATES = {};

const CROPS_BASE = [
  {id:1,  cropName:'Onion',       category:'Vegetables', price:2400,  previousPrice:2100, demandScore:92, organic:false},
  {id:2,  cropName:'Tomato',      category:'Vegetables', price:1800,  previousPrice:2000, demandScore:88, organic:false},
  {id:3,  cropName:'Potato',      category:'Vegetables', price:1200,  previousPrice:1100, demandScore:85, organic:false},
  {id:4,  cropName:'Soybean',     category:'Oilseeds',   price:5200,  previousPrice:5000, demandScore:78, organic:false},
  {id:5,  cropName:'Wheat',       category:'Grains',     price:2150,  previousPrice:2100, demandScore:90, organic:false},
  {id:6,  cropName:'Rice',        category:'Grains',     price:3200,  previousPrice:3100, demandScore:95, organic:false},
  {id:7,  cropName:'Sugarcane',   category:'Cash Crops', price:3500,  previousPrice:3300, demandScore:82, organic:false},
  {id:8,  cropName:'Cotton',      category:'Cash Crops', price:7200,  previousPrice:6800, demandScore:75, organic:false},
  {id:9,  cropName:'Grapes',      category:'Fruits',     price:4500,  previousPrice:4200, demandScore:87, organic:true},
  {id:10, cropName:'Mango',       category:'Fruits',     price:6000,  previousPrice:5500, demandScore:94, organic:true},
  {id:11, cropName:'Pomegranate', category:'Fruits',     price:8000,  previousPrice:7800, demandScore:88, organic:true},
  {id:12, cropName:'Banana',      category:'Fruits',     price:2000,  previousPrice:1900, demandScore:91, organic:false},
  {id:13, cropName:'Chilli',      category:'Spices',     price:8000,  previousPrice:7200, demandScore:80, organic:false},
  {id:14, cropName:'Turmeric',    category:'Spices',     price:9500,  previousPrice:9000, demandScore:77, organic:true},
  {id:15, cropName:'Garlic',      category:'Spices',     price:6200,  previousPrice:5800, demandScore:84, organic:false},
  {id:16, cropName:'Ginger',      category:'Spices',     price:4800,  previousPrice:4500, demandScore:79, organic:false},
  {id:17, cropName:'Groundnut',   category:'Oilseeds',   price:5500,  previousPrice:5300, demandScore:76, organic:false},
  {id:18, cropName:'Maize',       category:'Grains',     price:1900,  previousPrice:1800, demandScore:83, organic:false},
  {id:19, cropName:'Bajra',       category:'Grains',     price:2200,  previousPrice:2100, demandScore:72, organic:false},
  {id:20, cropName:'Jowar',       category:'Grains',     price:2600,  previousPrice:2400, demandScore:70, organic:false},
  {id:21, cropName:'Tur Dal',     category:'Pulses',     price:12000, previousPrice:11500,demandScore:86, organic:false},
  {id:22, cropName:'Urad Dal',    category:'Pulses',     price:10500, previousPrice:10000,demandScore:82, organic:false},
  {id:23, cropName:'Moong Dal',   category:'Pulses',     price:9800,  previousPrice:9500, demandScore:79, organic:true},
  {id:24, cropName:'Coriander',   category:'Spices',     price:3200,  previousPrice:3000, demandScore:74, organic:true},
  {id:25, cropName:'Capsicum',    category:'Vegetables', price:3400,  previousPrice:3200, demandScore:81, organic:false},
  {id:26, cropName:'Brinjal',     category:'Vegetables', price:1600,  previousPrice:1500, demandScore:68, organic:false},
  {id:27, cropName:'Cauliflower', category:'Vegetables', price:2200,  previousPrice:2000, demandScore:72, organic:false},
  {id:28, cropName:'Cabbage',     category:'Vegetables', price:1100,  previousPrice:1000, demandScore:65, organic:false},
  {id:29, cropName:'Ladyfinger',  category:'Vegetables', price:2800,  previousPrice:2600, demandScore:74, organic:true},
  {id:30, cropName:'Peas',        category:'Vegetables', price:3600,  previousPrice:3400, demandScore:78, organic:false},
  {id:31, cropName:'Watermelon',  category:'Fruits',     price:1400,  previousPrice:1200, demandScore:85, organic:false},
  {id:32, cropName:'Papaya',      category:'Fruits',     price:2200,  previousPrice:2000, demandScore:77, organic:false},
  {id:33, cropName:'Guava',       category:'Fruits',     price:2600,  previousPrice:2400, demandScore:73, organic:false},
  {id:34, cropName:'Orange',      category:'Fruits',     price:4200,  previousPrice:4000, demandScore:89, organic:true},
  {id:35, cropName:'Lemon',       category:'Fruits',     price:3800,  previousPrice:3500, demandScore:80, organic:false},
  {id:36, cropName:'Carrot',      category:'Vegetables', price:1800,  previousPrice:1700, demandScore:76, organic:true},
  {id:37, cropName:'Radish',      category:'Vegetables', price:900,   previousPrice:800,  demandScore:62, organic:false},
  {id:38, cropName:'Beetroot',    category:'Vegetables', price:1600,  previousPrice:1500, demandScore:68, organic:false},
  {id:39, cropName:'Coconut',     category:'Fruits',     price:5000,  previousPrice:4800, demandScore:83, organic:false},
  {id:40, cropName:'Cashew',      category:'Oilseeds',   price:18000, previousPrice:17000,demandScore:87, organic:true},
  {id:41, cropName:'Sunflower',   category:'Oilseeds',   price:6200,  previousPrice:6000, demandScore:74, organic:false},
  {id:42, cropName:'Sesame',      category:'Oilseeds',   price:14000, previousPrice:13500,demandScore:76, organic:true},
  {id:43, cropName:'Fenugreek',   category:'Spices',     price:7800,  previousPrice:7500, demandScore:72, organic:false},
  {id:44, cropName:'Drumstick',   category:'Vegetables', price:3200,  previousPrice:3000, demandScore:69, organic:false},
  {id:45, cropName:'Spinach',     category:'Vegetables', price:1200,  previousPrice:1100, demandScore:71, organic:true},
  {id:46, cropName:'Sweet Potato',category:'Vegetables', price:1400,  previousPrice:1300, demandScore:67, organic:false},
  {id:47, cropName:'Peanut',      category:'Oilseeds',   price:5800,  previousPrice:5600, demandScore:80, organic:false},
  {id:48, cropName:'Tur',         category:'Pulses',     price:11000, previousPrice:10500,demandScore:84, organic:false},
  {id:49, cropName:'Curry Leaf',  category:'Spices',     price:4000,  previousPrice:3800, demandScore:70, organic:true},
  {id:50, cropName:'Linseed',     category:'Oilseeds',   price:7200,  previousPrice:7000, demandScore:68, organic:false},
];

const FARMERS_DB = [
  {name:'Ramesh Patil',    village:'Sangamner',  district:'Ahmednagar', emoji:'👨‍🌾', rating:4.8, sales:1240, verified:true},
  {name:'Suresh Shinde',   village:'Indapur',    district:'Pune',       emoji:'🧑‍🌾', rating:4.6, sales:980,  verified:true},
  {name:'Anil Deshmukh',   village:'Yeola',      district:'Nashik',     emoji:'👨‍🌾', rating:4.9, sales:1560, verified:true},
  {name:'Pramod Jadhav',   village:'Baramati',   district:'Pune',       emoji:'👨‍🌾', rating:4.5, sales:820,  verified:true},
  {name:'Vijay Gaikwad',   village:'Niphad',     district:'Nashik',     emoji:'🧑‍🌾', rating:4.7, sales:1120, verified:true},
  {name:'Santosh More',    village:'Karad',      district:'Satara',     emoji:'👨‍🌾', rating:4.4, sales:760,  verified:false},
  {name:'Dilip Kulkarni',  village:'Palghar',    district:'Palghar',    emoji:'👨‍🌾', rating:4.8, sales:1380, verified:true},
  {name:'Mahesh Bhosale',  village:'Osmanabad',  district:'Latur',      emoji:'🧑‍🌾', rating:4.3, sales:640,  verified:true},
];

const BUYERS_DB = [
  {name:'AgriPro Exports',  crop:'Onion',       qty:'500 qtl', price:'₹2,600/qtl', city:'Mumbai',     emoji:'🏢'},
  {name:'Fresh Mart Ltd',   crop:'Tomato',      qty:'300 qtl', price:'₹2,000/qtl', city:'Pune',       emoji:'🏪'},
  {name:'Nashik Traders',   crop:'Grapes',      qty:'200 qtl', price:'₹5,000/qtl', city:'Nashik',     emoji:'🤝'},
  {name:'MH Agri Co-op',    crop:'Wheat',       qty:'1000 qtl',price:'₹2,200/qtl', city:'Nagpur',     emoji:'🌾'},
  {name:'Spice World',      crop:'Chilli',      qty:'150 qtl', price:'₹8,500/qtl', city:'Kolhapur',   emoji:'🌶️'},
  {name:'Juice Plus Pvt.',  crop:'Pomegranate', qty:'400 qtl', price:'₹8,200/qtl', city:'Solapur',    emoji:'🏭'},
];

let DB = [];
let dbId = 1000;

// ── LIVE API ──
let liveRatesLoaded = false;

async function fetchLiveRates() {
  try {
    const res = await fetch('mandi.php?state=Maharashtra&limit=500');
    const data = await res.json();
    if(!data.success || !data.records.length) return;

    liveRatesLoaded = true;
    LIVE_DISTRICT_RATES = {};
    const globalGroup = {};

    data.records.forEach(r => {
      const district = DISTRICT_MAP[r.district] || r.district;
      const crop = COMMODITY_MAP[r.commodity] || r.commodity;
      // District rates
      if(!LIVE_DISTRICT_RATES[district]) LIVE_DISTRICT_RATES[district] = {};
      if(!LIVE_DISTRICT_RATES[district][crop]) LIVE_DISTRICT_RATES[district][crop] = {prices:[],min:[],max:[],markets:[]};
      if(r.modal_price>0) LIVE_DISTRICT_RATES[district][crop].prices.push(r.modal_price);
      if(r.min_price>0) LIVE_DISTRICT_RATES[district][crop].min.push(r.min_price);
      if(r.max_price>0) LIVE_DISTRICT_RATES[district][crop].max.push(r.max_price);
      if(r.market) LIVE_DISTRICT_RATES[district][crop].markets.push(r.market);
      // Global
      if(!globalGroup[crop]) globalGroup[crop] = [];
      if(r.modal_price>0) globalGroup[crop].push(r.modal_price);
    });

    CROPS_BASE.forEach(crop => {
      const g = globalGroup[crop.cropName];
      if(g && g.length) {
        crop.previousPrice = crop.price;
        crop.price = Math.round(g.reduce((a,b)=>a+b,0)/g.length);
        crop.isLive = true;
      }
    });

    initDB();
    renderMarketTable();
    renderInsights();
    renderTrending();
    showLiveBadge();
    const liveStatEl = document.getElementById('kbStatLiveRecords');
    if (liveStatEl) liveStatEl.textContent = data.count + '+';
    console.log('✅ Live rates:', data.count, 'records,', Object.keys(LIVE_DISTRICT_RATES).length, 'districts');
  } catch(e) {
    console.warn('Live fetch failed:', e);
  }
}



function getCropEmoji(name) {
  const m = {'Onion':'🧅','Tomato':'🍅','Potato':'🥔','Grapes':'🍇','Mango':'🥭',
    'Pomegranate':'🍎','Banana':'🍌','Orange':'🍊','Lemon':'🍋','Watermelon':'🍉',
    'Coconut':'🥥','Cashew':'🥜','Cotton':'🌿','Sugarcane':'🎋','Soybean':'🌱',
    'Wheat':'🌾','Rice':'🍚','Maize':'🌽','Jowar':'🌾','Bajra':'🌾',
    'Groundnut':'🥜','Chilli':'🌶️','Turmeric':'🌿','Garlic':'🧄','Ginger':'🫚',
    'Tur Dal':'🫘','Urad Dal':'🫘','Moong Dal':'🫘','Cauliflower':'🥦',
    'Cabbage':'🥬','Brinjal':'🍆','Capsicum':'🫑','Ladyfinger':'🥦','Carrot':'🥕',
  };
  return m[name]||'🌾';
}
function showLiveBadge() {
    const badge = document.querySelector('.kb-badge-green');
    if(badge) {
        badge.textContent = '🟢 Live';
        badge.title = 'Data from data.gov.in APMC';
    }
    // Show last updated time
    const now = new Date();
    const timeStr = now.toLocaleTimeString('en-IN', {hour:'2-digit', minute:'2-digit'});
    const header = document.querySelector('#kb-market-rates .kb-section-header');
    if(header && !document.getElementById('liveUpdateTime')) {
        const span = document.createElement('span');
        span.id = 'liveUpdateTime';
        span.style.cssText = 'font-size:12px;color:#888;margin-left:10px;';
        span.textContent = '⏱ Updated: ' + timeStr;
        header.appendChild(span);
    }
}

function initDB() {
    DB = [];
    dbId = 1000;
    CROPS_BASE.forEach((crop, ci) => {
  CITIES.forEach((city, cj) => {
    const farmer = FARMERS_DB[(ci + cj) % FARMERS_DB.length];
    const priceMod = 0.9 + Math.random() * 0.2;
    const price = Math.round(crop.price * priceMod / 50) * 50;
    const prevPrice = Math.round(crop.previousPrice * priceMod / 50) * 50;
    DB.push({
      id: dbId++,
      cropName: crop.cropName,
      category: crop.category,
      city: city,
      district: farmer.district,
      price: price,
      previousPrice: prevPrice,
      demandScore: crop.demandScore,
      quantityAvailable: 10 + Math.floor(Math.random() * 490),
      farmerName: farmer.name,
      village: farmer.village,
      verified: farmer.verified,
      organic: crop.organic,
      farmerEmoji: farmer.emoji,
      rating: farmer.rating,
      listedAt: Date.now() - Math.floor(Math.random() * 86400000)
    });
  });
});
} // end initDB

initDB(); // initial call with static data

/* ──────────────────────────────────────────────
   4. FILTERED / DISPLAYED STATE
────────────────────────────────────────────── */
let filteredDB = [...DB];
let productsShown = 8;
let selectedCity = 'All';
let currentPeriod = 7;
let currentTrendCrop = 'Onion';

/* ──────────────────────────────────────────────
   5. LANGUAGE ENGINE (Connected to krishimitra widget)
────────────────────────────────────────────── */
window.pageLanguageCallback = function(l) {
  // Use passed language OR localstorage OR 'en'
  currentLang = l || localStorage.getItem('agri_lang') || 'en';

  // Update Static Texts
  document.querySelectorAll('[data-en][data-mr]').forEach(el => {
    el.textContent = el.getAttribute('data-' + currentLang) || el.getAttribute('data-en');
  });

  // Update Placeholders
  document.querySelectorAll('[data-placeholder-en]').forEach(el => {
    el.placeholder = el.getAttribute('data-placeholder-' + currentLang) || el.getAttribute('data-placeholder-en');
  });
  
  // Re-render Dynamics with translation
  populateCityDropdowns(); 
  renderInsights();
  renderCityTabs();
  renderMarketTable();
  renderTrending();
  renderProducts();
  renderBuyers();
  renderFarmers();
  renderListings();
  renderChart(); 
  kbEnhanceAllSelects();
}

/* ──────────────────────────────────────────────
   6. INSIGHTS
────────────────────────────────────────────── */
function renderInsights() {
  const insightsGridEl = document.getElementById('insightsGrid');
  if (!insightsGridEl) return; // Insights widget removed from the page — nothing to render.
  const byDemand = [...DB].sort((a,b) => b.demandScore - a.demandScore);
  const byPrice  = [...DB].sort((a,b) => b.price - a.price);
  const byPriceLow = [...DB].sort((a,b) => a.price - b.price);
  const priceDiffs = DB.map(d => ({...d, diff: d.price - d.previousPrice}));
  const trending = [...priceDiffs].sort((a,b) => Math.abs(b.diff) - Math.abs(a.diff));
  const uniqueListings = new Set(DB.map(d => d.cropName)).size;

  const cards = [
    {icon:'📈', label: currentLang==='mr' ? 'सर्वाधिक भाव' : (currentLang==='hi' ? 'उच्चतम भाव' : 'Highest Price'),    value: `₹${byPrice[0].price.toLocaleString('hi-IN')}`,   sub: t(byPrice[0].cropName)},
    {icon:'📉', label: currentLang==='mr' ? 'सर्वात कमी भाव' : (currentLang==='hi' ? 'न्यूनतम भाव' : 'Lowest Price'),   value: `₹${byPriceLow[0].price.toLocaleString('hi-IN')}`, sub: t(byPriceLow[0].cropName)},
    {icon:'🔥', label: currentLang==='mr' ? 'ट्रेंडिंग पीक' : (currentLang==='hi' ? 'ट्रेंडिंग फसल' : 'Trending Crop'),   value: t(trending[0].cropName),                               sub: `${trending[0].diff > 0 ? '+' : ''}₹${trending[0].diff}`},
    {icon:'<i class="fa-solid fa-star" style="color:#f5a623"></i>', label: currentLang==='mr' ? 'सर्वाधिक मागणी' : (currentLang==='hi' ? 'सर्वाधिक मांग' : 'Most Demanded'),  value: t(byDemand[0].cropName),                               sub: `Score: ${byDemand[0].demandScore}`},
    {icon:'📦', label: currentLang==='mr' ? 'एकूण लिस्टिंग' : (currentLang==='hi' ? 'सक्रिय लिस्टिंग' : 'Active Listings'), value: DB.length,                                         sub: `${uniqueListings} ${currentLang==='mr' ? 'पिके' : (currentLang==='hi' ? 'फसलें' : 'Crops')}`},
  ];

  document.getElementById('insightsGrid').innerHTML = cards.map(c => `
    <div class="kb-insight-card">
      <div class="kb-insight-card__icon">${c.icon}</div>
      <div class="kb-insight-card__label">${c.label}</div>
      <div class="kb-insight-card__value">${c.value}</div>
      <div class="kb-insight-card__sub">${c.sub}</div>
    </div>
  `).join('');
}

/* ──────────────────────────────────────────────
   7. CITY FILTER DROPDOWNS
────────────────────────────────────────────── */
function populateCityDropdowns() {
  ['filterCity','alertCity'].forEach(id => {
    const sel = document.getElementById(id);
    if (!sel) return;
    const currentVal = sel.value; 
    if (id === 'filterCity') sel.innerHTML = `<option value="">${currentLang==='mr' ? 'सर्व शहरे' : (currentLang==='hi' ? 'सभी शहर' : 'All Cities')}</option>`;
    else sel.innerHTML = '';
    CITIES.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c; opt.textContent = t(c);
      sel.appendChild(opt);
    });
    sel.value = currentVal; 
  });
  
  const alertCrop = document.getElementById('alertCrop');
  if (alertCrop) {
    const currentVal = alertCrop.value;
    alertCrop.innerHTML = '';
    [...new Set(DB.map(d => d.cropName))].sort().forEach(name => {
      const opt = document.createElement('option');
      opt.value = name; opt.textContent = t(name);
      alertCrop.appendChild(opt);
    });
    alertCrop.value = currentVal || alertCrop.options[0].value;
  }
  
  const trendCrop = document.getElementById('trendCrop');
  if (trendCrop) {
    trendCrop.innerHTML = '';
    CROPS_BASE.forEach(c => {
      const opt = document.createElement('option');
      opt.value = c.cropName; opt.textContent = t(c.cropName);
      trendCrop.appendChild(opt);
    });
    trendCrop.value = currentTrendCrop;
    trendCrop.onchange = () => { currentTrendCrop = trendCrop.value; renderChart(); };
  }
}

/* ──────────────────────────────────────────────
   8. CITY TABS + MARKET TABLE
────────────────────────────────────────────── */
function renderCityTabs() {
  const allLabel = currentLang==='mr'?'सर्व जिल्हे':currentLang==='hi'?'सभी जिले':'All Districts';
  const distLabel = currentLang==='mr'?'जिल्हा निवडा':currentLang==='hi'?'जिला चुनें':'Select District';
  const cropLabel = currentLang==='mr'?'पीक शोधा':currentLang==='hi'?'फसल खोजें':'Search Crop';

  document.getElementById('cityTabs').innerHTML = `
    <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;padding:4px 0;">
      
      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:#555;text-transform:uppercase;">${distLabel}</label>
        <select id="kbCityDropdown" onchange="selectCity(this.value)"
          style="padding:10px 14px;border:2px solid #4CAF50;border-radius:10px;font-size:14px;
                 font-family:Poppins,sans-serif;background:#fff;color:#1b3a1a;cursor:pointer;
                 min-width:200px;outline:none;">
          <option value="All">${allLabel}</option>
          ${CITIES.map(city => `<option value="${city}" ${city===selectedCity?'selected':''}>${t(city)||city}</option>`).join('')}
        </select>
      </div>

      <div style="display:flex;flex-direction:column;gap:4px;">
        <label style="font-size:11px;font-weight:600;color:#555;text-transform:uppercase;">${cropLabel}</label>
        <div style="position:relative;">
          <input id="kbCropSearch" type="text"
            placeholder="${currentLang==='hi'?'जैसे: प्याज, टमाटर...':currentLang==='mr'?'उदा: कांदा, टोमॅटो...':'e.g. Onion, Tomato...'}"
            oninput="filterKBSearch(this.value)"
            style="padding:10px 16px 10px 36px;border:2px solid #4CAF50;border-radius:10px;
                   font-size:14px;font-family:Poppins,sans-serif;width:220px;outline:none;">
          <i class="fa-solid fa-magnifying-glass" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:#4CAF50;font-size:13px;"></i>
        </div>
      </div>

      <div id="kbSearchResult" style="font-size:13px;color:#555;align-self:flex-end;padding-bottom:6px;"></div>
    </div>
  `;
  kbEnhanceAllSelects(document.getElementById('cityTabs'));
}

function filterKBSearch(query) {
  const q = query.trim().toLowerCase();
  const resultEl = document.getElementById('kbSearchResult');
  if(!q) { resultEl.innerHTML = ''; selectedCity = 'All'; syncCityDropdownUI(); renderMarketTable(); return; }

  // Search in cities
  const matchCity = CITIES.find(c => 
    c.toLowerCase().includes(q) || 
    (TRANSLATIONS.mr[c]||'').includes(query) || 
    (TRANSLATIONS.hi[c]||'').includes(query)
  );

  // Search in crops
  const matchCrop = Object.keys(TRANSLATIONS.mr).find(k => 
    k.toLowerCase().includes(q) || 
    (TRANSLATIONS.mr[k]||'').includes(query) || 
    (TRANSLATIONS.hi[k]||'').includes(query)
  );

  if(matchCity) {
    // Just update the district value + table — do NOT rebuild the search
    // input itself, or the user's cursor/focus gets kicked out mid-typing.
    selectedCity = matchCity;
    syncCityDropdownUI();
    renderMarketTable();
    resultEl.innerHTML = `<span style="color:#2e7d32">✅ ${t(matchCity)} ${currentLang==='hi'?'चुना गया':currentLang==='mr'?'निवडले':'selected'}</span>`;
  } else if(matchCrop && liveRatesLoaded) {
    // Show crop rates across all districts
    showCropRatesAcrossDistricts(matchCrop, query);
    resultEl.innerHTML = `<span style="color:#1565c0">🌾 ${t(matchCrop)||matchCrop} ${currentLang==='hi'?'के भाव':currentLang==='mr'?'चे भाव':'rates'}</span>`;
  } else {
    resultEl.innerHTML = `<span style="color:#c62828">${currentLang==='hi'?'कोई परिणाम नहीं':currentLang==='mr'?'काही सापडले नाही':'No results found'}</span>`;
  }
}

// Updates just the district <select>'s value + its custom-dropdown label,
// WITHOUT touching the rest of the city-tabs block (keeps the search
// input's focus/cursor intact while the user is typing).
function syncCityDropdownUI() {
  const sel = document.getElementById('kbCityDropdown');
  if (!sel) return;
  sel.value = selectedCity;
  const wrapper = sel.nextElementSibling;
  if (wrapper && wrapper.classList && wrapper.classList.contains('kb-custom-select') && wrapper._kbSync) {
    wrapper._kbSync();
  }
}

function showCropRatesAcrossDistricts(cropName, rawQuery) {
  const tbody = document.getElementById('marketTableBody');
  if(!tbody) return;
  const rows = [];
  CITIES.forEach(district => {
    const dr = LIVE_DISTRICT_RATES[district];
    if(!dr) return;
    // Try to find crop match
    const key = Object.keys(dr).find(k => k.toLowerCase().includes(rawQuery.toLowerCase()));
    if(!key) return;
    const r = dr[key];
    if(!r || !r.prices.length) return;
    const modal = Math.round(r.prices.reduce((a,b)=>a+b,0)/r.prices.length);
    const minP = r.min.length ? Math.round(Math.min(...r.min)) : modal;
    const maxP = r.max.length ? Math.round(Math.max(...r.max)) : modal;
    const market = r.markets[0]||district;
    rows.push(`<tr>
      <td>${getCropEmoji(key)}</td>
      <td><strong>${t(key)||key}</strong></td>
      <td><strong>${t(district)||district}</strong><br><small style="color:#888">${market}</small></td>
      <td><strong style="color:#1b5e20">₹${modal.toLocaleString('en-IN')}</strong>/qtl<br>
      <small style="color:#888">₹${minP.toLocaleString('en-IN')}–₹${maxP.toLocaleString('en-IN')}</small></td>
      <td>—</td>
      <td><span style="color:#2e7d32;font-size:11px;font-weight:700">LIVE</span></td>
    </tr>`);
  });
  if(rows.length) {
    tbody.innerHTML = rows.join('');
  } else {
    tbody.innerHTML = `<tr><td colspan="6" style="text-align:center;padding:20px;color:#888">${currentLang==='hi'?'लाइव डेटा नहीं मिला':currentLang==='mr'?'लाइव डेटा सापडला नाही':'No live data found'}</td></tr>`;
  }
}
function selectCity(city) {
  selectedCity = city;
  renderCityTabs();
  renderMarketTable();
}
function renderMarketTable() {
  // District live rates view
  if(liveRatesLoaded && selectedCity !== 'All' && LIVE_DISTRICT_RATES[selectedCity]) {
    const distRates = LIVE_DISTRICT_RATES[selectedCity];
    // Show ALL crops from API for this district (not just predefined list)
    const allCropsFromAPI = Object.keys(distRates).filter(crop => distRates[crop].prices.length > 0);
    // Also add predefined crops that have static data
    const predefined = DISTRICT_CROPS[selectedCity] || [];
    const allCrops = [...new Set([...allCropsFromAPI, ...predefined])];
    
    const tbody = document.getElementById('marketTableBody');
    if(tbody) {
      const rows = allCrops.map(cropName => {
        const r = distRates[cropName];
        const base = CROPS_BASE.find(c => c.cropName === cropName);
        const prev = base ? base.previousPrice : 0;
        if(!r || !r.prices.length) {
          const price = base ? base.price : 0;
          if(!price) return '';
          return `<tr>
            <td>${getCropEmoji(cropName)}</td>
            <td><strong>${t(cropName)||cropName}</strong></td>
            <td>${t(selectedCity)||selectedCity}</td>
            <td><strong>₹${price.toLocaleString('en-IN')}</strong>/qtl</td>
            <td style="color:#888">—</td>
            <td><span style="color:#888;font-size:11px;">Static</span></td>
          </tr>`;
        }
        const modal = Math.round(r.prices.reduce((a,b)=>a+b,0)/r.prices.length);
        const minP = r.min.length ? Math.round(Math.min(...r.min)) : modal;
        const maxP = r.max.length ? Math.round(Math.max(...r.max)) : modal;
        const change = modal - (prev||modal);
        const pct = prev ? ((change/prev)*100).toFixed(1) : 0;
        const arrow = change>0?'📈':change<0?'📉':'➡️';
        const color = change>0?'#2e7d32':change<0?'#c62828':'#888';
        const market = r.markets[0]||selectedCity;
        return `<tr>
          <td>${getCropEmoji(cropName)}</td>
          <td><strong>${t(cropName)||cropName}</strong><br><small style="color:#888">${market}</small></td>
          <td>${t(selectedCity)||selectedCity}</td>
          <td><strong style="color:#1b5e20">₹${modal.toLocaleString('en-IN')}</strong>/qtl<br>
          <small style="color:#888">₹${minP.toLocaleString('en-IN')}–₹${maxP.toLocaleString('en-IN')}</small></td>
          <td style="color:${color}">${change>=0?'+':''}${change.toLocaleString('en-IN')} (${pct}%)</td>
          <td>${arrow} <span style="color:#2e7d32;font-size:11px;font-weight:700">LIVE</span></td>
        </tr>`;
      }).filter(Boolean);
      tbody.innerHTML = rows.length ? rows.join('') : 
        `<tr><td colspan="6" style="text-align:center;padding:30px;color:#888">
          <i class="fa-solid fa-database"></i> ${currentLang==='hi'?'इस जिले का लाइव डेटा उपलब्ध नहीं':currentLang==='mr'?'या जिल्ह्याचा लाइव डेटा उपलब्ध नाही':'No live data available for this district'}
        </td></tr>`;
      return;
    }
  }

  const cityData = selectedCity === 'All'
    ? DB.filter((d, i, arr) => arr.findIndex(x => x.cropName === d.cropName && x.city === d.city) === i).slice(0, 30)
    : DB.filter(d => d.city === selectedCity);

  const body = document.getElementById('marketTableBody');
  body.innerHTML = cityData.slice(0, 20).map(row => {
    const diff = row.price - row.previousPrice;
    const pct  = ((diff / row.previousPrice) * 100).toFixed(1);
    const trendIcon = diff > 0 ? '▲' : diff < 0 ? '▼' : '●';
    const trendClass = diff > 0 ? 'kb-trend-up' : diff < 0 ? 'kb-trend-dn' : 'kb-trend-neutral';
    const changeClass = diff >= 0 ? 'kb-change-up' : 'kb-change-dn';
    return `
      <tr>
        <td><div class="kb-crop-img">${getCropImageTag(row.cropName, 'kb-crop-photo')}</div></td>
        <td><strong>${t(row.cropName)}</strong></td>
        <td>${t(row.city)}</td>
        <td><span class="kb-price">₹${row.price.toLocaleString('hi-IN')}</span></td>
        <td><span class="${changeClass}">${diff >= 0 ? '+' : ''}${pct}%</span></td>
        <td><span class="${trendClass}">${trendIcon}</span></td>
      </tr>`;
  }).join('');
}

/* ──────────────────────────────────────────────
   9. PRICE TREND CHART
────────────────────────────────────────────── */
function generatePriceHistory(baseCrop, days) {
  const base = CROPS_BASE.find(c => c.cropName === baseCrop);
  const basePrice = base ? base.price : 3000;
  const prices = [];
  let p = basePrice * 0.85;
  for (let i = 0; i < days; i++) {
    p = p + (Math.random() - 0.48) * basePrice * 0.03;
    p = Math.max(basePrice * 0.7, Math.min(basePrice * 1.3, p));
    prices.push(Math.round(p));
  }
  return prices;
}

function renderChart() {
  const canvas = document.getElementById('trendChart');
  if (!canvas) return;
  const prices = generatePriceHistory(currentTrendCrop, currentPeriod);
  const avg = Math.round(prices.reduce((a,b) => a+b, 0) / prices.length);
  const high = Math.max(...prices);
  const low  = Math.min(...prices);

  document.getElementById('trendStats').innerHTML = [
    {label: t('avgPrice'), val: `₹${avg.toLocaleString('hi-IN')}`},
    {label: t('highPrice'), val: `₹${high.toLocaleString('hi-IN')}`},
    {label: t('lowPrice'), val: `₹${low.toLocaleString('hi-IN')}`},
  ].map(s => `
    <div class="kb-trend-stat">
      <div class="kb-trend-stat__label">${s.label}</div>
      <div class="kb-trend-stat__val">${s.val}</div>
    </div>`).join('');

  const ctx = canvas.getContext('2d');
  const W = canvas.parentElement.clientWidth - 48;
  const H = 280;
  canvas.width = W; canvas.height = H;

  const pad = { top:30, right:20, bottom:40, left:70 };
  const cW = W - pad.left - pad.right;
  const cH = H - pad.top - pad.bottom;
  const minP = low  * 0.97;
  const maxP = high * 1.03;
  const range = maxP - minP;

  const xPos = i => pad.left + (i / (prices.length - 1)) * cW;
  const yPos = v => pad.top + cH - ((v - minP) / range) * cH;

  ctx.clearRect(0, 0, W, H);
  ctx.strokeStyle = '#e8f0e8'; ctx.lineWidth = 1;
  for (let g = 0; g <= 5; g++) {
    const y = pad.top + (g / 5) * cH;
    ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(pad.left + cW, y); ctx.stroke();
    const val = Math.round(maxP - (g / 5) * range);
    ctx.fillStyle = '#9aa0ab'; ctx.font = '11px Inter,sans-serif'; ctx.textAlign = 'right';
    ctx.fillText('₹' + val.toLocaleString('hi-IN'), pad.left - 8, y + 4);
  }

  const grad = ctx.createLinearGradient(0, pad.top, 0, pad.top + cH);
  grad.addColorStop(0, 'rgba(76,175,80,.35)');
  grad.addColorStop(1, 'rgba(76,175,80,.02)');
  ctx.beginPath();
  prices.forEach((p, i) => { i === 0 ? ctx.moveTo(xPos(i), yPos(p)) : ctx.lineTo(xPos(i), yPos(p)); });
  ctx.lineTo(xPos(prices.length-1), pad.top + cH);
  ctx.lineTo(xPos(0), pad.top + cH);
  ctx.closePath();
  ctx.fillStyle = grad; ctx.fill();

  ctx.beginPath(); ctx.strokeStyle = '#2d6a2d'; ctx.lineWidth = 2.5; ctx.lineJoin = 'round';
  prices.forEach((p, i) => { i === 0 ? ctx.moveTo(xPos(i), yPos(p)) : ctx.lineTo(xPos(i), yPos(p)); });
  ctx.stroke();

  prices.forEach((p, i) => {
    ctx.beginPath(); ctx.arc(xPos(i), yPos(p), 3.5, 0, Math.PI * 2);
    ctx.fillStyle = '#4CAF50'; ctx.fill();
    ctx.strokeStyle = '#fff'; ctx.lineWidth = 1.5; ctx.stroke();
  });

  ctx.fillStyle = '#9aa0ab'; ctx.font = '10px Inter,sans-serif'; ctx.textAlign = 'center';
  const step = Math.max(1, Math.floor(prices.length / 7));
  for (let i = 0; i < prices.length; i += step) {
    ctx.fillText(`D${i+1}`, xPos(i), H - 12);
  }

  ctx.beginPath(); ctx.strokeStyle = '#f5a623'; ctx.lineWidth = 1.5;
  ctx.setLineDash([5,4]); ctx.moveTo(pad.left, yPos(avg)); ctx.lineTo(pad.left + cW, yPos(avg));
  ctx.stroke(); ctx.setLineDash([]);
}

document.querySelectorAll('.kb-period-btn').forEach(btn => {
  btn.addEventListener('click', function() {
    document.querySelectorAll('.kb-period-btn').forEach(b => b.classList.remove('active'));
    this.classList.add('active');
    currentPeriod = parseInt(this.dataset.period);
    renderChart();
  });
});

/* ──────────────────────────────────────────────
   10. TRENDING CROPS
────────────────────────────────────────────── */
function renderTrending() {
  const sorted = [...CROPS_BASE].sort((a,b) => b.demandScore - a.demandScore).slice(0, 8);
  document.getElementById('trendingGrid').innerHTML = sorted.map(c => {
    const diff = c.price - c.previousPrice;
    const growthPct = ((diff / c.previousPrice) * 100).toFixed(1);
    return `
    <div class="kb-trending-card">
      <div class="kb-trending-card__emoji">${getCropImageTag(c.cropName, 'kb-crop-photo')}</div>
      <div class="kb-trending-card__name">${t(c.cropName)}</div>
      <div class="kb-trending-card__demand">${t('demandScore')}: ${c.demandScore}/100</div>
      <div class="kb-trending-card__growth">${t('weeklyGrowth')}: ${growthPct > 0 ? '+' : ''}${growthPct}%</div>
      ${c.demandScore > 88 ? `<div class="kb-trending-card__badge">${t('hotTrend')}</div>` : ''}
    </div>`;
  }).join('');
}

/* ──────────────────────────────────────────────
   11. PRODUCT CARDS
────────────────────────────────────────────── */
function renderProducts() {
  const grid = document.getElementById('productsGrid');
  const toShow = filteredDB.slice(0, productsShown);
  if (toShow.length === 0) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:#9aa0ab;font-size:1.05rem;">${t('noResults')}</div>`;
    return;
  }
  grid.innerHTML = toShow.map(item => `
    <div class="kb-product-card">
      <div class="kb-product-card__img">
        ${getCropImageTag(item.cropName, 'kb-crop-photo')}
        ${item.verified ? `<div class="kb-product-card__verified">${t('verified')}</div>` : ''}
        ${item.organic ? `<div class="kb-product-card__organic">${t('organic')}</div>` : ''}
      </div>
      <div class="kb-product-card__body">
        <div class="kb-product-card__name">${t(item.cropName)}</div>
        <div class="kb-product-card__qty">${t('qty')}: ${item.quantityAvailable} qtl</div>
        <div class="kb-product-card__price">₹${item.price.toLocaleString('hi-IN')}<span> ${t('perQtl')}</span></div>
        <div class="kb-product-card__meta">
          <strong>${t('farmer')}:</strong> ${item.farmerEmoji} ${t(item.farmerName)}<br/>
          <strong>${t('location')}:</strong> 📍 ${t(item.village)}, ${t(item.city)}
        </div>
        <div class="kb-product-card__actions">
          <button class="kb-btn kb-btn--primary" onclick="showToast('${t(item.cropName)} - ${t('viewDetails')}')">${t('viewDetails')}</button>
          <button class="kb-wa-btn" title="WhatsApp" onclick="waContact('${item.farmerName}','${item.cropName}','${item.price}')">💬</button>
        </div>
      </div>
    </div>
  `).join('');
  document.getElementById('loadMoreBtn').style.display = filteredDB.length > productsShown ? 'inline-flex' : 'none';
}

function loadMoreProducts() {
  productsShown += 8;
  renderProducts();
}

/* ──────────────────────────────────────────────
   12. BUYERS
────────────────────────────────────────────── */
function renderBuyers() {
  document.getElementById('buyersGrid').innerHTML = BUYERS_DB.map(b => `
    <div class="kb-buyer-card">
      <div class="kb-buyer-card__header">
        <div class="kb-buyer-card__avatar">${b.emoji}</div>
        <div>
          <div class="kb-buyer-card__name">${t(b.name)}</div>
          <div class="kb-buyer-card__loc">📍 ${t(b.city)}</div>
        </div>
      </div>
      <div class="kb-buyer-card__detail"><strong>${currentLang==='mr' ? 'हवे पीक' : (currentLang==='hi' ? 'आवश्यक फसल' : 'Crop Required')}:</strong> ${getCropImage(b.crop)} ${t(b.crop)}</div>
      <div class="kb-buyer-card__detail"><strong>${currentLang==='mr' ? 'प्रमाण' : (currentLang==='hi' ? 'आवश्यक मात्रा' : 'Qty Needed')}:</strong> ${b.qty}</div>
      <div class="kb-buyer-card__price">${b.price}</div>
      <button class="kb-buyer-card__btn" onclick="showToast('${currentLang==='mr' ? 'संपर्क विनंती पाठवली!' : (currentLang==='hi' ? 'संपर्क अनुरोध भेजा गया!' : 'Contact request sent!')}')">${t('contact')}</button>
    </div>
  `).join('');
}

/* ──────────────────────────────────────────────
   13. FARMERS
────────────────────────────────────────────── */
function renderFarmers() {
  document.getElementById('farmersGrid').innerHTML = FARMERS_DB.map(f => `
    <div class="kb-farmer-card">
      <div class="kb-farmer-card__avatar">${f.emoji}</div>
      <div class="kb-farmer-card__name">${t(f.name)}</div>
      <div class="kb-farmer-card__village">📍 ${t(f.village)}, ${t(f.district)}</div>
      <div class="kb-farmer-card__stars">${'★'.repeat(Math.round(f.rating))}${'☆'.repeat(5-Math.round(f.rating))}</div>
      <div class="kb-farmer-card__sales">${t('rating')}: ${f.rating} | ${t('sales')}: ${f.sales} qtl</div>
      ${f.verified ? `<div class="kb-farmer-card__verified">${t('verified')}</div>` : ''}
    </div>
  `).join('');
}

/* ──────────────────────────────────────────────
   14. RECENT LISTINGS
────────────────────────────────────────────── */
let listingsSortMode = 'new';
function renderListings() {
  let data = [...DB];
  if (listingsSortMode === 'new') data.sort((a,b) => b.listedAt - a.listedAt);
  else if (listingsSortMode === 'price_asc') data.sort((a,b) => a.price - b.price);
  else if (listingsSortMode === 'price_desc') data.sort((a,b) => b.price - a.price);

  document.getElementById('listingsGrid').innerHTML = data.slice(0,12).map(item => {
    const ago = getTimeAgo(item.listedAt);
    return `
    <div class="kb-listing-card">
      <div class="kb-listing-card__emoji">${getCropImageTag(item.cropName, 'kb-crop-photo')}</div>
      <div class="kb-listing-card__info">
        <div class="kb-listing-card__name">${t(item.cropName)}</div>
        <div class="kb-listing-card__meta">${t(item.city)} • ${item.quantityAvailable} qtl</div>
        <div class="kb-listing-card__price">₹${item.price.toLocaleString('hi-IN')} ${t('perQtl')}</div>
        <div class="kb-listing-card__time">⏰ ${ago}</div>
      </div>
    </div>`;
  }).join('');
}
function sortListings(val) { listingsSortMode = val; renderListings(); }
function getTimeAgo(ts) {
  const diff = (Date.now() - ts) / 1000;
  if (diff < 60) return t('justNow');
  if (diff < 3600) return `${Math.floor(diff/60)} ${t('minsAgo')}`;
  return `${Math.floor(diff/3600)} ${t('hoursAgo')}`;
}

/* ──────────────────────────────────────────────
   15. FILTERS
────────────────────────────────────────────── */
function applyFilters() {
  const cropText = document.getElementById('filterCrop').value.toLowerCase().trim();
  const category = document.getElementById('filterCategory').value;
  const city     = document.getElementById('filterCity').value;
  const minPrice = parseFloat(document.getElementById('filterPriceMin').value) || 0;
  const maxPrice = parseFloat(document.getElementById('filterPriceMax').value) || Infinity;
  const organic  = document.getElementById('filterOrganic').checked;
  const sortVal  = document.getElementById('filterSort').value;

  filteredDB = DB.filter(d => {
    if (cropText) {
      const enMatch = d.cropName.toLowerCase().includes(cropText);
      const mrMatch = t(d.cropName).toLowerCase().includes(cropText);
      if (!enMatch && !mrMatch) return false;
    }
    if (category && d.category !== category) return false;
    if (city && d.city !== city) return false;
    if (d.price < minPrice || d.price > maxPrice) return false;
    if (organic && !d.organic) return false;
    return true;
  });

  if (sortVal === 'price_asc') filteredDB.sort((a,b) => a.price - b.price);
  else if (sortVal === 'price_desc') filteredDB.sort((a,b) => b.price - a.price);
  else if (sortVal === 'demand_desc') filteredDB.sort((a,b) => b.demandScore - a.demandScore);

  productsShown = 8;
  renderProducts();
  scrollToSection('kb-products');
  showToast(currentLang === 'mr' ? `${filteredDB.length} उत्पादने सापडली` : (currentLang === 'hi' ? `${filteredDB.length} उत्पाद मिले` : `${filteredDB.length} products found`));
}

function resetFilters() {
  document.getElementById('filterCrop').value = '';
  document.getElementById('filterCategory').value = '';
  document.getElementById('filterCity').value = '';
  document.getElementById('filterPriceMin').value = '';
  document.getElementById('filterPriceMax').value = '';
  document.getElementById('filterOrganic').checked = false;
  document.getElementById('filterSort').value = 'default';
  filteredDB = [...DB];
  productsShown = 8;
  renderProducts();
}

function triggerSearch() {
  const val = document.getElementById('heroSearch').value;
  document.getElementById('filterCrop').value = val;
  applyFilters();
}
document.getElementById('heroSearch').addEventListener('keydown', e => {
  if (e.key === 'Enter') triggerSearch();
});

document.getElementById('filterOrganic').addEventListener('change', function() {
  document.getElementById('organicLabel').textContent = this.checked
    ? (currentLang === 'mr' ? 'होय' : (currentLang === 'hi' ? 'हाँ' : 'Yes'))
    : (currentLang === 'mr' ? 'नाही' : (currentLang === 'hi' ? 'नहीं' : 'No'));
});

/* ──────────────────────────────────────────────
   16. PRICE ALERT SYSTEM
────────────────────────────────────────────── */
function openAlertModal() {
  document.getElementById('alertModal').classList.add('open');
  renderSavedAlerts();
}
function closeAlertModal() {
  document.getElementById('alertModal').classList.remove('open');
}
document.getElementById('alertModal').addEventListener('click', function(e) {
  if (e.target === this) closeAlertModal();
});

function saveAlert() {
  const crop  = document.getElementById('alertCrop').value;
  const city  = document.getElementById('alertCity').value;
  const price = parseFloat(document.getElementById('alertPrice').value);
  if (!price || isNaN(price)) { showToast(t('enterPrice')); return; }
  const alerts = JSON.parse(localStorage.getItem('kb_alerts') || '[]');
  alerts.push({ id: Date.now(), crop, city, price });
  localStorage.setItem('kb_alerts', JSON.stringify(alerts));
  document.getElementById('alertPrice').value = '';
  showToast(t('alertSaved'));
  renderSavedAlerts();
}

function deleteAlert(id) {
  const alerts = JSON.parse(localStorage.getItem('kb_alerts') || '[]').filter(a => a.id !== id);
  localStorage.setItem('kb_alerts', JSON.stringify(alerts));
  showToast(t('alertDeleted'));
  renderSavedAlerts();
}

function renderSavedAlerts() {
  const alerts = JSON.parse(localStorage.getItem('kb_alerts') || '[]');
  const el = document.getElementById('alertsList');
  if (!alerts.length) {
    el.innerHTML = `<p style="font-size:.82rem;color:#9aa0ab;">${currentLang==='mr' ? 'कोणतेही अलर्ट नाहीत.' : (currentLang==='hi' ? 'कोई अलर्ट नहीं।' : 'No alerts set.')}</p>`;
    return;
  }
  el.innerHTML = alerts.map(a => `
    <div class="kb-alert-item">
      <span>${getCropImage(a.crop)} <strong>${t(a.crop)}</strong> • ${t(a.city)} • ₹${a.price.toLocaleString('hi-IN')}</span>
      <button class="kb-alert-item__del" onclick="deleteAlert(${a.id})">🗑️</button>
    </div>`).join('');
}

/* ──────────────────────────────────────────────
   17. UTILITIES
────────────────────────────────────────────── */
function scrollToSection(id) {
  const el = document.getElementById(id);
  if (!el) return;

  // Calculate total height of any sticky/fixed elements pinned to the top
  // (ticker, navbar, price bar etc.) so the section doesn't end up hidden behind them.
  let headerOffset = 0;
  document.querySelectorAll('body *').forEach(node => {
    if (node.offsetHeight === 0 && node.offsetWidth === 0) return;
    const style = window.getComputedStyle(node);
    if (style.position === 'fixed' || style.position === 'sticky') {
      const rect = node.getBoundingClientRect();
      if (rect.top <= 5) headerOffset += node.offsetHeight;
    }
  });

  const extraBuffer = 16;
  const targetTop = el.getBoundingClientRect().top + window.pageYOffset - headerOffset - extraBuffer;

  window.scrollTo({ top: Math.max(targetTop, 0), behavior: 'smooth' });
}

let toastTimer;
function showToast(msg) {
  const toast = document.getElementById('kbToast');
  toast.textContent = msg;
  toast.classList.add('show');
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => toast.classList.remove('show'), 3000);
}

function waContact(farmer, crop, price) {
  const msg = encodeURIComponent(
    currentLang === 'mr' 
      ? `नमस्कार ${t(farmer)}, मला तुमच्या ${t(crop)} च्या ₹${price}/क्विं उत्पादनाबद्दल जाणून घ्यायचे आहे.` 
      : (currentLang === 'hi' 
        ? `नमस्ते ${t(farmer)}, मुझे आपके ${t(crop)} (₹${price}/क्विंटल) उत्पाद के बारे में जानकारी चाहिए।` 
        : `Hello ${farmer}, I am interested in your ${crop} listing at ₹${price}/qtl on AgriCart.`)
  );
  window.open(`https://wa.me/?text=${msg}`, '_blank');
}

/* ──────────────────────────────────────────────
   18. INIT (Connected to Global System)
────────────────────────────────────────────── */
function init() {
  // Fetch stored lang and trigger initial render automatically!
  currentLang = localStorage.getItem('agri_lang') || 'en';
  window.pageLanguageCallback(currentLang);

  // Resize chart on window resize
  window.addEventListener('resize', () => { clearTimeout(window._kbResizeTimer); window._kbResizeTimer = setTimeout(renderChart, 200); });
}

document.addEventListener('DOMContentLoaded', init);