<?php
// =============================================
// KrishiMitra Widget — Groq API (FREE)
// Set GROQ_API_KEY in your .env file (see .env.example). The key is
// never exposed to the browser — only used server-side below.
// =============================================
require_once __DIR__ . '/../includes/env.php';
require_once __DIR__ . '/../includes/security.php';
$API_KEY = env('GROQ_API_KEY', '');
// =============================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['km_msg'])) {
    header('Content-Type: application/json; charset=utf-8');

    // Rate limiting: max 20 chat requests per minute per session, to
    // protect the (metered) Groq API key from abuse.
    agri_session_start();
    $now = time();
    $_SESSION['km_rl'] = array_filter($_SESSION['km_rl'] ?? [], fn($t) => $t > $now - 60);
    if (count($_SESSION['km_rl']) >= 20) {
        echo json_encode(['reply' => '⚠️ Too many requests. Please wait a minute and try again.']);
        exit;
    }
    $_SESSION['km_rl'][] = $now;

    $msg     = trim($_POST['km_msg']);
    $history = json_decode($_POST['km_history'] ?? '[]', true) ?: [];
    $lang    = trim($_POST['km_lang'] ?? 'mr');

    // Basic message-length validation.
    if (function_exists('mb_strlen') ? mb_strlen($msg) > 1000 : strlen($msg) > 1000) {
        echo json_encode(['reply' => '⚠️ Message too long. Please keep it under 1000 characters.']);
        exit;
    }

    if ($API_KEY === '') {
        error_log('[AgriCart] KrishiMitra widget: GROQ_API_KEY is not configured.');
        echo json_encode(['reply' => "⚠️ Assistant is temporarily unavailable. Please try again later."]);
        exit;
    }

    $messages = [];
    if ($lang === 'en') {
        $system = "You are KrishiMitra — Agricart's AI farming assistant. Always reply in English only. Give practical farming advice. Market prices, weather, government schemes. Helpline: 1800-419-8888. Short answers with emojis. Max 3 paragraphs.";
    } elseif ($lang === 'hi') {
        $system = "Aap KrishiMitra hain — Agricart ke AI farming assistant. Hamesha sirf Hindi mein jawab dein. Practical kheti ki salah dein. Bazar bhav, mausam, sarkari yojnaein. Helpline: 1800-419-8888. Short answers with emojis. Max 3 paragraphs.";
    } else {
        $system = "Tu KrishiMitra aahe — Agricart cha AI farming assistant. Hamesha fakt Marathi madhe uttar de. Practical salla de. Bazar bhav, hawa-maan, government schemes. Helpline: 1800-419-8888. Short answers with emojis. Max 3 paragraphs.";
    }

    $messages[] = ['role' => 'system', 'content' => $system];
    foreach (array_slice($history, -10) as $h) {
        $messages[] = ['role' => $h['role'] === 'assistant' ? 'assistant' : 'user', 'content' => $h['content']];
    }
    $messages[] = ['role' => 'user', 'content' => $msg];

    $body = json_encode([
        // 'llama-3.3-70b-versatile' was decommissioned by Groq on 2026-08-16 (see
        // https://console.groq.com/docs/deprecations) — requests to it now fail with
        // a 400 model_decommissioned error, which is why the widget was showing the
        // generic "Assistant is temporarily unavailable" fallback. Groq's recommended
        // replacement is openai/gpt-oss-120b (or openai/gpt-oss-20b for a smaller/faster model).
        'model'       => 'openai/gpt-oss-120b',
        'messages'    => $messages,
        'max_tokens'  => 1024,
        'temperature' => 0.7,
    ]);

    $ch = curl_init('https://api.groq.com/openai/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $API_KEY,
        ],
    ]);

    $resp = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $cerr = curl_error($ch);
    curl_close($ch);

    if ($cerr) {
        error_log('[AgriCart] KrishiMitra Groq call failed: ' . $cerr);
        echo json_encode(['reply' => '⚠️ Network error reaching the assistant. Please try again.']);
        exit;
    }
    $data = json_decode($resp, true);
    if ($code !== 200 || !is_array($data)) {
        error_log('[AgriCart] KrishiMitra Groq API error, HTTP ' . $code . ': ' . substr((string)$resp, 0, 500));
        echo json_encode(['reply' => '⚠️ Assistant is temporarily unavailable. Please try again shortly.']);
        exit;
    }
    $reply = $data['choices'][0]['message']['content'] ?? ($lang === 'hi' ? 'माफ करें, उत्तर नहीं मिला.' : ($lang === 'en' ? 'Sorry, no response received.' : 'माफ करा, उत्तर मिळाले नाही.'));
    echo json_encode(['reply' => trim($reply)]);
    exit;
}
?>
<style>
/* ══ FAB BUTTON ══ */
#km-fab {
  position: fixed;
  bottom: 28px; right: 28px;
  width: 64px; height: 64px;
  border-radius: 50%;
  background: linear-gradient(135deg, #1b5e20, #2e7d32, #43a047);
  border: 3px solid #ffffff; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  box-shadow: 0 8px 24px rgba(0,0,0,0.35), 0 2px 8px rgba(46,125,50,0.4);
  z-index: 99999;
  animation: km-pulse 2.5s infinite;
  transition: transform .2s;
  overflow: visible;
}
#km-fab:hover { transform: scale(1.12); animation: none; }
@keyframes km-pulse {
  0%   { box-shadow: 0 5px 20px rgba(46,125,50,0.6), 0 0 0 0 rgba(76,175,80,0.45); }
  70%  { box-shadow: 0 5px 20px rgba(46,125,50,0.6), 0 0 0 14px rgba(76,175,80,0); }
  100% { box-shadow: 0 5px 20px rgba(46,125,50,0.6), 0 0 0 0 rgba(76,175,80,0); }
}
/* Farmer SVG icon */
#km-fab .km-farmer { width: 36px; height: 36px; }
#km-fab .km-notif {
  position: absolute; top: -3px; right: -3px;
  width: 16px; height: 16px; background: #ff5722;
  border-radius: 50%; border: 2px solid #fff;
  animation: km-pop 1s infinite;
}
@keyframes km-pop { 0%,100%{transform:scale(1)} 50%{transform:scale(1.4)} }

/* ══ CHAT BOX ══ */
#km-box {
  position: fixed; bottom: 106px; right: 28px;
  width: 370px; height: 500px;
  background: #0f1e0f;
  border: 1px solid rgba(76,175,80,0.22); border-radius: 20px;
  display: none; flex-direction: column; overflow: hidden;
  box-shadow: 0 20px 60px rgba(0,0,0,0.65);
  z-index: 99998;
  font-family: 'Segoe UI','Noto Sans Devanagari',Arial,sans-serif;
}
#km-box.open { display:flex; animation:km-in .3s cubic-bezier(.34,1.56,.64,1); }
@keyframes km-in { from{opacity:0;transform:translateY(24px) scale(.94)} to{opacity:1;transform:translateY(0) scale(1)} }

/* ══ CHATBOX HEADER — wheat icon + KrishiMitra ══ */
#km-hdr {
  background: linear-gradient(135deg, #0a150a, #1a3a1a);
  padding: 12px 15px; display: flex; align-items: center; gap: 11px;
  border-bottom: 1px solid rgba(76,175,80,0.18); flex-shrink: 0;
}
.km-av {
  width: 42px; height: 42px; border-radius: 50%;
  background: linear-gradient(135deg,#2e7d32,#66bb6a);
  display: flex; align-items: center; justify-content: center;
  flex-shrink: 0; box-shadow: 0 3px 10px rgba(46,125,50,0.5);
}
.km-av i { font-size: 20px; color: #fff; }
.km-hdr-name { font-size: 15px; font-weight: 700; color: #fff; }
.km-hdr-sub { font-size: 11px; color: #81c784; display: flex; align-items: center; gap: 4px; margin-top: 2px; }
.km-dot { width:7px;height:7px;border-radius:50%;background:#4caf50;animation:kmbl 2s infinite; }
@keyframes kmbl{0%,100%{opacity:1}50%{opacity:.2}}
#km-close {
  margin-left: auto; background: rgba(255,255,255,0.08);
  border: 1px solid rgba(255,255,255,0.12); color: #a5d6a7;
  font-size: 15px; cursor: pointer; width: 28px; height: 28px;
  border-radius: 50%; display: flex; align-items: center; justify-content: center; transition: all .2s;
}
#km-close:hover { background: rgba(255,80,80,0.25); color: #ff8a80; }

/* ══ MESSAGES ══ */
#km-msgs {
  flex:1; overflow-y:auto; padding:13px;
  display:flex; flex-direction:column; gap:11px;
  scrollbar-width:thin; scrollbar-color:#2e7d32 transparent;
}
#km-msgs::-webkit-scrollbar{width:3px;}
#km-msgs::-webkit-scrollbar-thumb{background:#2e7d32;border-radius:3px;}
.km-row{display:flex;flex-direction:column;max-width:86%;}
.km-row.bot{align-self:flex-start;align-items:flex-start;}
.km-row.usr{align-self:flex-end;align-items:flex-end;}
.km-bbl{padding:10px 14px;border-radius:14px;font-size:13.5px;line-height:1.65;word-break:break-word;animation:km-msg .22s ease;}
@keyframes km-msg{from{opacity:0;transform:translateY(7px)}to{opacity:1;transform:translateY(0)}}
.km-row.bot .km-bbl{background:#1a2e1a;color:#e8f5e9;border:1px solid rgba(76,175,80,0.18);border-bottom-left-radius:4px;}
.km-row.usr .km-bbl{background:linear-gradient(135deg,#2e7d32,#388e3c);color:#fff;border-bottom-right-radius:4px;}
.km-bbl.think{background:#162816;color:#81c784;font-style:italic;border:1px dashed rgba(76,175,80,0.2);display:flex;align-items:center;gap:8px;}
.km-dots span{display:inline-block;width:6px;height:6px;background:#4caf50;border-radius:50%;margin:0 2px;animation:kmdot 1.2s infinite;}
.km-dots span:nth-child(2){animation-delay:.2s}.km-dots span:nth-child(3){animation-delay:.4s}
@keyframes kmdot{0%,80%,100%{transform:scale(.5);opacity:.3}40%{transform:scale(1);opacity:1}}
.km-ts{font-size:10px;color:#558b2f;margin-top:3px;}

/* ══ QUICK BTNS ══ */
#km-qbtns{padding:7px 12px;display:flex;flex-wrap:wrap;gap:6px;border-top:1px solid rgba(76,175,80,0.1);background:#0c1c0c;flex-shrink:0;}
.km-qb{font-size:11.5px;padding:5px 11px;border:1px solid rgba(76,175,80,0.35);border-radius:20px;background:rgba(46,125,50,0.12);color:#a5d6a7;cursor:pointer;font-family:inherit;transition:all .15s;}
.km-qb:hover{background:rgba(46,125,50,0.45);color:#fff;border-color:#4caf50;transform:translateY(-1px);}

/* ══ INPUT ══ */
#km-inrow{display:flex;gap:9px;padding:11px 13px;border-top:1px solid rgba(76,175,80,0.12);background:#0a150a;flex-shrink:0;}
#km-inp{flex:1;background:#1a2e1a;border:1px solid rgba(76,175,80,0.28);border-radius:22px;padding:10px 15px;font-size:13.5px;color:#fff;font-family:inherit;outline:none;transition:border-color .2s;}
#km-inp::placeholder{color:#558b2f;}
#km-inp:focus{border-color:#4caf50;box-shadow:0 0 0 3px rgba(76,175,80,0.1);}
#km-sbtn{width:41px;height:41px;border-radius:50%;background:linear-gradient(135deg,#2e7d32,#66bb6a);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all .18s;}
#km-sbtn:hover{transform:scale(1.1) rotate(-5deg);}
#km-sbtn svg{width:17px;height:17px;fill:#fff;}

/* ══ FOOTER ══ */
#km-ftr{text-align:center;font-size:10px;color:rgba(129,199,132,0.35);padding:6px;background:#0a150a;border-top:1px solid rgba(76,175,80,0.07);flex-shrink:0;}

@media(max-width:440px){
  #km-box{width:calc(100vw - 16px);right:8px;bottom:98px;height:70vh;}
  #km-fab{right:14px;bottom:24px;}
}
</style>

<!-- ══ FAB: Detailed Farmer SVG ══ -->
<button id="km-fab" onclick="kmToggle()" title="KrishiMitra">
  <svg class="km-farmer" viewBox="0 0 64 64" fill="none" xmlns="http://www.w3.org/2000/svg">
    <!-- Hat brim -->
    <ellipse cx="32" cy="22" rx="18" ry="4" fill="rgba(255,255,255,0.95)"/>
    <!-- Hat top -->
    <rect x="22" y="10" width="20" height="13" rx="3" fill="rgba(255,255,255,0.95)"/>
    <!-- Hat band -->
    <rect x="22" y="19" width="20" height="3" rx="1" fill="rgba(76,175,80,0.6)"/>
    <!-- Face -->
    <circle cx="32" cy="33" r="9" fill="rgba(255,255,255,0.95)"/>
    <!-- Eyes -->
    <circle cx="28.5" cy="31" r="1.3" fill="#2e7d32"/>
    <circle cx="35.5" cy="31" r="1.3" fill="#2e7d32"/>
    <!-- Smile -->
    <path d="M28 35.5 Q32 38.5 36 35.5" stroke="#2e7d32" stroke-width="1.5" fill="none" stroke-linecap="round"/>
    <!-- Body/shoulders -->
    <path d="M20 50 Q20 43 32 43 Q44 43 44 50" fill="rgba(255,255,255,0.85)"/>
    <!-- Leaf accent -->
    <path d="M46 28 Q52 22 54 16 Q48 17 44 22 Q42 25 43 28 Z" fill="rgba(255,255,255,0.9)"/>
    <path d="M43 28 Q47 23 54 16" stroke="rgba(76,175,80,0.7)" stroke-width="1" fill="none"/>
  </svg>
  <div class="km-notif"></div>
</button>

<!-- ══ CHAT WINDOW ══ -->
<div id="km-box">
  <div id="km-hdr">
    <div class="km-av">
      <i class="fa-solid fa-wheat-awn"></i>
    </div>
    <div>
      <div class="km-hdr-name">KrishiMitra</div>
      <div class="km-hdr-sub"><span class="km-dot"></span><span id="km-sub-txt">तुमचा शेती सहाय्यक · Online</span></div>
    </div>
    <button id="km-close" onclick="kmToggle()">✕</button>
  </div>

  <div id="km-msgs">
    <div class="km-row bot">
      <div class="km-bbl" id="km-welcome">🙏 <strong>नमस्कार! मी KrishiMitra आहे.</strong><br>बाजारभाव, बियाणे, खते, रोग उपाय, सरकारी योजना — काहीही विचारा!</div>
      <div class="km-ts">आत्ता</div>
    </div>
  </div>

  <div id="km-qbtns">
    <button class="km-qb" id="qb1">🧅 बाजारभाव</button>
    <button class="km-qb" id="qb2">🐛 रोग उपाय</button>
    <button class="km-qb" id="qb3">🏛️ PM किसान</button>
    <button class="km-qb" id="qb4">🌧️ खरीप</button>
  </div>

  <div id="km-inrow">
    <input type="text" id="km-inp" placeholder="प्रश्न टाइप करा..." autocomplete="off"/>
    <button id="km-sbtn" onclick="kmSend()">
      <svg viewBox="0 0 24 24"><path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/></svg>
    </button>
  </div>
  <div id="km-ftr">🌿 Agricart · KrishiMitra · Powered by Groq AI</div>
</div>

<script>
(function(){
  var kmHistory=[], kmOpen=false, kmLang='mr';

  var L={
    mr:{
      sub:'तुमचा शेती सहाय्यक · Online',
      welcome:'🙏 <strong>नमस्कार! मी KrishiMitra आहे.</strong><br>बाजारभाव, बियाणे, खते, रोग उपाय, सरकारी योजना — काहीही विचारा!',
      ph:'प्रश्न टाइप करा...',
      think:'⏳ शोधतोय',
      ftr:'🌿 Agricart · KrishiMitra · Groq AI द्वारे',
      err:'⚠️ Connection error. Internet आणि XAMPP तपासा.',
      qb:['🧅 बाजारभाव','🐛 रोग उपाय','🏛️ PM किसान','🌧️ खरीप'],
      qq:['आजचा कांदा बाजारभाव?','पिकावर रोग उपाय सांग','PM किसान योजना 2026','महाराष्ट्र खरीप पीक सल्ला']
    },
    hi:{
      sub:'आपका कृषि सहायक · Online',
      welcome:'🙏 <strong>नमस्ते! मैं KrishiMitra हूं।</strong><br>बाजार भाव, बीज, खाद, फसल रोग, सरकारी योजनाएं — कुछ भी पूछें!',
      ph:'अपना सवाल टाइप करें...',
      think:'⏳ खोज रहा हूं',
      ftr:'🌿 Agricart · KrishiMitra · Groq AI द्वारा संचालित',
      err:'⚠️ कनेक्शन एरर। Internet और XAMPP जांचें।',
      qb:['🧅 बाजार भाव','🐛 फसल रोग','🏛️ PM किसान','🌧️ खरीफ फसल'],
      qq:['आज प्याज का बाजार भाव?','फसल रोग का उपाय बताओ','PM किसान योजना 2026','महाराष्ट्र खरीफ फसल सलाह']
    },
    en:{
      sub:'Your Farming Assistant · Online',
      welcome:'👋 <strong>Hello! I am KrishiMitra.</strong><br>Ask me about market prices, seeds, fertilizers, crop diseases or government schemes!',
      ph:'Type your question...',
      think:'⏳ Searching',
      ftr:'🌿 Agricart · KrishiMitra · Powered by Groq AI',
      err:'⚠️ Connection error. Check Internet and XAMPP.',
      qb:['🧅 Market Price','🐛 Crop Disease','🏛️ PM Kisan','🌧️ Kharif Crop'],
      qq:["Today's onion market price?",'Crop disease remedy','PM Kisan Scheme 2026','Maharashtra Kharif crop advice']
    }
  };

  function applyLang(lang){
    kmLang=lang;
    var t=L[lang]||L.mr;
    document.getElementById('km-sub-txt').textContent=t.sub;
    document.getElementById('km-welcome').innerHTML=t.welcome;
    document.getElementById('km-inp').placeholder=t.ph;
    document.getElementById('km-ftr').textContent=t.ftr;
    ['qb1','qb2','qb3','qb4'].forEach(function(id,i){
      var b=document.getElementById(id);
      if(b){ b.textContent=t.qb[i]; b.onclick=(function(q){return function(){kmQa(q);};})(t.qq[i]); }
    });
  }

  function initLang(){
    var sel=document.getElementById('langSelector');
    if(sel){
      applyLang(sel.value||'mr');
      // Listen to change event
      sel.addEventListener('change',function(){ applyLang(this.value); });
    } else { applyLang(localStorage.getItem('agri_lang') || 'mr'); }

    // Override switchLanguage function used by agri-master.js
    var origSwitch = window.switchLanguage;
    window.switchLanguage = function(lang){
      applyLang(lang);
      if(typeof origSwitch === 'function') origSwitch(lang);
    };
  }

  document.readyState==='loading'
    ? document.addEventListener('DOMContentLoaded', initLang)
    : initLang();

  window.kmToggle=function(){
    kmOpen=!kmOpen;
    var box=document.getElementById('km-box');
    var notif=document.querySelector('#km-fab .km-notif');
    if(kmOpen){
      box.classList.add('open');
      if(notif) notif.style.display='none';
      setTimeout(function(){ document.getElementById('km-inp').focus(); },300);
    } else { box.classList.remove('open'); }
  };

  function tn(){var d=new Date();return d.getHours().toString().padStart(2,'0')+':'+d.getMinutes().toString().padStart(2,'0');}

  function kmEscapeHtml(s){
    var div=document.createElement('div');
    div.textContent=String(s==null?'':s);
    return div.innerHTML;
  }
  function addMsg(text,role){
    // Security: always escape before inserting — never render raw AI/user
    // text as HTML. Only line breaks are converted after escaping.
    var msgs=document.getElementById('km-msgs');
    var row=document.createElement('div'); row.className='km-row '+role;
    var b=document.createElement('div');   b.className='km-bbl'; b.innerHTML=kmEscapeHtml(text).replace(/\n/g,'<br>');
    var t=document.createElement('div');   t.className='km-ts';  t.textContent=tn();
    row.appendChild(b); row.appendChild(t); msgs.appendChild(row); msgs.scrollTop=msgs.scrollHeight;
  }

  function showThink(){
    var msgs=document.getElementById('km-msgs');
    var row=document.createElement('div'); row.className='km-row bot'; row.id='km-think';
    var b=document.createElement('div');   b.className='km-bbl think';
    b.innerHTML=(L[kmLang]||L.mr).think+' <div class="km-dots"><span></span><span></span><span></span></div>';
    row.appendChild(b); msgs.appendChild(row); msgs.scrollTop=msgs.scrollHeight;
  }
  function hideThink(){var e=document.getElementById('km-think');if(e)e.remove();}

  window.kmSend=async function(){
    var inp=document.getElementById('km-inp');
    var msg=inp.value.trim(); if(!msg) return;
    inp.value='';
    addMsg(msg,'usr');
    kmHistory.push({role:'user',content:msg});
    showThink();
    try{
      var fd=new FormData();
      fd.append('km_msg',msg);
      fd.append('km_history',JSON.stringify(kmHistory.slice(-10)));
      fd.append('km_lang',kmLang);
      var kmBase = (window.location.pathname.indexOf('/pages/') !== -1) ? 'krishimitra_widget.php' : 'pages/krishimitra_widget.php';
      var res=await fetch(kmBase,{method:'POST',body:fd});
      var data=await res.json();
      hideThink();
      addMsg(data.reply||(L[kmLang]||L.mr).err,'bot');
      if(data.reply) kmHistory.push({role:'assistant',content:data.reply});
    }catch(e){
      hideThink();
      addMsg((L[kmLang]||L.mr).err,'bot');
    }
  };

  window.kmQa=function(q){
    if(!kmOpen) kmToggle();
    setTimeout(function(){ document.getElementById('km-inp').value=q; kmSend(); },350);
  };

  document.addEventListener('keydown',function(e){
    if(document.activeElement===document.getElementById('km-inp')&&e.key==='Enter'&&!e.shiftKey){
      e.preventDefault(); kmSend();
    }
  });
})();
</script>
<script>
// NOTE: The homepage / agri-connect weather widget is now fully server-rendered
// via pages/fetch_weather.php (see index.php / agri-connect.php's
// fetchWeatherAjax()), which already returns fully-translated HTML for the
// selected language. This legacy client-side weather updater is kept only as
// a no-op guard (skipped when that widget is present) so it doesn't fight
// with the new translated markup and cause "doesn't translate until refresh" bugs.
async function fetchRealTimeWeather() {
    if (document.getElementById('wd-content')) return; // new AJAX weather widget handles itself
    const lang = localStorage.getItem('agri_lang') || 'en';

    // 1. Set Today's Date dynamically in selected language
    const dateObj = new Date();
    const options = { day: 'numeric', month: 'long', year: 'numeric' };
    let dateStr = '';
    if (lang === 'mr') dateStr = dateObj.toLocaleDateString('mr-IN', options);
    else if (lang === 'hi') dateStr = dateObj.toLocaleDateString('hi-IN', options);
    else dateStr = dateObj.toLocaleDateString('en-IN', options);

    // Update Date in Agri Connect
    if(document.getElementById('wd-date')) document.getElementById('wd-date').innerText = dateStr;
    
    // Update Date in Index page
    const indexDate = document.querySelector('.weather-location')?.nextElementSibling;
    if(indexDate && indexDate.tagName === 'DIV') indexDate.innerText = dateStr;

    // Default text
    const locEl = document.getElementById('wd-loc');
    if(locEl) locEl.innerText = lang === 'mr' ? 'लोकेशन शोधत आहे...' : (lang === 'hi' ? 'लोकेशन खोज रहा है...' : 'Detecting location...');

    if (navigator.geolocation) {
        navigator.geolocation.getCurrentPosition(
            async (position) => {
                await getWeatherAndCity(position.coords.latitude, position.coords.longitude, lang);
            }, 
            async () => {
                await getWeatherAndCity(18.5204, 73.8567, lang, "Pune"); // Fallback
            }
        );
    } else {
        await getWeatherAndCity(18.5204, 73.8567, lang, "Pune");
    }
}

async function getWeatherAndCity(lat, lon, lang, fallbackCity = null) {
    try {
        let cityName = fallbackCity;
        if (!cityName) {
            const geoRes = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lon}`);
            if(geoRes.ok) {
                const geoData = await geoRes.json();
                const addr = geoData.address;
                // Get the most specific location (Village, Taluka/Suburb, Town, City)
                cityName = addr.village || addr.suburb || addr.town || addr.city_district || addr.city || "Unknown";
            } else {
                cityName = "Maharashtra";
            }
        }

        const weatherRes = await fetch(`https://api.open-meteo.com/v1/forecast?latitude=${lat}&longitude=${lon}&current=temperature_2m,relative_humidity_2m,weather_code,wind_speed_10m`);
        if(weatherRes.ok) {
            const data = await weatherRes.json();
            const temp = Math.round(data.current.temperature_2m);
            const humidity = data.current.relative_humidity_2m;
            const wind = data.current.wind_speed_10m;
            const code = data.current.weather_code;

            if(document.getElementById('wd-loc')) document.getElementById('wd-loc').innerText = `${cityName}, India`;
            
            if(document.querySelector('.weather-temp')) document.querySelector('.weather-temp').innerHTML = `${temp}°<span>C</span>`;
            if(document.getElementById('weather-temp')) document.getElementById('weather-temp').innerText = `${temp}°C`;

            if(document.getElementById('wd-hum')) document.getElementById('wd-hum').innerHTML = lang === 'mr' ? `आर्द्रता: <b>${humidity}%</b>` : (lang === 'hi' ? `नमी: <b>${humidity}%</b>` : `Humidity: <b>${humidity}%</b>`);
            if(document.getElementById('wd-wind')) document.getElementById('wd-wind').innerHTML = lang === 'mr' ? `वारा: <b>${wind} किमी/तास</b>` : (lang === 'hi' ? `हवा: <b>${wind} किमी/घंटा</b>` : `Wind: <b>${wind} km/h</b>`);
            if(document.getElementById('weather-hum-val')) document.getElementById('weather-hum-val').innerHTML = lang === 'mr' ? `आर्द्रता: <b>${humidity}%</b>` : (lang === 'hi' ? `नमी: <b>${humidity}%</b>` : `Humidity: <b>${humidity}%</b>`);
            if(document.getElementById('weather-wind-val')) document.getElementById('weather-wind-val').innerHTML = lang === 'mr' ? `वारा: <b>${wind} किमी/तास</b>` : (lang === 'hi' ? `हवा: <b>${wind} किमी/घंटा</b>` : `Wind: <b>${wind} km/h</b>`);

            let iconClass = 'fa-sun'; let condEn = 'Clear Sky'; let condMr = 'स्वच्छ आकाश'; let condHi = 'साफ आसमान';
            if(code > 0 && code <= 3) { iconClass = 'fa-cloud-sun'; condEn = 'Partly Cloudy'; condMr = 'अंशतः ढगाळ'; condHi = 'आंशिक रूप से बादल'; }
            else if(code >= 45 && code <= 48) { iconClass = 'fa-smog'; condEn = 'Foggy'; condMr = 'धुके'; condHi = 'कोहरा'; }
            else if(code >= 51 && code <= 67) { iconClass = 'fa-cloud-showers-heavy'; condEn = 'Raining'; condMr = 'पाऊस पडत आहे'; condHi = 'बारिश हो रही है'; }
            else if(code >= 95) { iconClass = 'fa-cloud-bolt'; condEn = 'Thunderstorm'; condMr = 'वादळी पाऊस'; condHi = 'आंधी तूफान'; }

            if(document.getElementById('wd-cond')) {
                document.getElementById('wd-cond').innerText = lang === 'mr' ? condMr : (lang === 'hi' ? condHi : condEn);
                document.getElementById('wd-cond').classList.remove('weather-loading');
            }
            
            const iconEl = document.querySelector('.weather-main .fa-solid') || document.getElementById('weather-icon');
            if(iconEl) iconEl.className = `fa-solid ${iconClass}`;
        }
    } catch (e) { console.error("Weather error:", e); }
}

// Call on load and also when language changes
window.addEventListener('DOMContentLoaded', fetchRealTimeWeather);
const langSelect = document.getElementById('langSelector');
if(langSelect && !document.getElementById('wd-content')) {
    langSelect.addEventListener('change', () => setTimeout(fetchRealTimeWeather, 100));
}
</script>