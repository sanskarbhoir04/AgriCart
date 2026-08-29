<?php
/**
 * KrishiMitra - Your Farming Assistant
 * Agricart Website Chatbot
 * Internet-connected via Anthropic Claude API + Web Search
 */

// =====================================================
// CONFIGURATION — set ANTHROPIC_API_KEY in your .env file
// =====================================================
require_once __DIR__ . '/../includes/env.php';
define('ANTHROPIC_API_KEY', env('ANTHROPIC_API_KEY', ''));
define('ANTHROPIC_API_URL', 'https://api.anthropic.com/v1/messages');
define('ANTHROPIC_MODEL',   'claude-sonnet-4-6');
define('ANTHROPIC_VERSION', '2023-06-01');
define('MAX_TOKENS', 1024);

// =====================================================
// API REQUEST HANDLER (AJAX)
// =====================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'chat') {
    header('Content-Type: application/json; charset=utf-8');

    $userMessage  = trim($_POST['message'] ?? '');
    $historyJson  = $_POST['history'] ?? '[]';
    $history      = json_decode($historyJson, true) ?: [];

    if ($userMessage === '') {
        echo json_encode(['error' => 'रिकामा संदेश.']);
        exit;
    }

    // Build messages array (keep last 10 turns for context)
    $messages = array_slice($history, -10);
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    $system = <<<SYSTEM
Tu "KrishiMitra" aahe — Agricart website cha official AI farming assistant. Tu ek expert krishi sahayak aahe.

TUMCHA KAAM:
- Shetkaryanna Marathi madhe practical, simple salla de
- Bazar bhav, hawa-maan, government schemes baddal current info de
- Beej, khadte, keetaknashke, pik vyavsthapan baddal guide kar
- Agricart products (krushi store, avzare, pik salla) madhe help kar
- User jya bhashet vicharato — Marathi/Hindi/English — tya bhashet uttar de

AGRICART MAHITI:
- Helpline: 1800-419-8888
- Mahabij hybrid biyanye uplabdh
- Sendra NPK khatavar 15% soot uplabdh
- Krushi Store, Avzare Kendra, Pik Salla, Krushi Bazar sections ahet

UTTAR FORMAT:
- Short ani practical raha (2-4 paragraphs max)
- Relevant emojis vapar (🌾🌱💧☀️)
- Actionable steps de
- Gerekasas Agricart products suggest kar
SYSTEM;

    $body = json_encode([
        'model'    => ANTHROPIC_MODEL,
        'max_tokens' => MAX_TOKENS,
        'system'   => $system,
        'messages' => $messages,
        'tools'    => [
            ['type' => 'web_search_20250305', 'name' => 'web_search']
        ]
    ]);

    if (ANTHROPIC_API_KEY === '') {
        error_log('[AgriCart] krishimitra_chatbox.php: ANTHROPIC_API_KEY is not configured.');
        echo json_encode(['error' => 'Assistant is temporarily unavailable. कृपया नंतर प्रयत्न करा.']);
        exit;
    }

    $ch = curl_init(ANTHROPIC_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $body,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'x-api-key: ' . ANTHROPIC_API_KEY,
            'anthropic-version: ' . ANTHROPIC_VERSION,
            'anthropic-beta: web-search-2025-03-05',
        ],
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);

    $response  = curl_exec($ch);
    $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($curlError || $response === false) {
        error_log('[AgriCart] KrishiMitra chatbox cURL error: ' . $curlError);
        echo json_encode(['error' => 'Server शी connection failed. पुन्हा प्रयत्न करा.']);
        exit;
    }

    $data = json_decode($response, true);

    if ($httpCode !== 200) {
        error_log('[AgriCart] KrishiMitra chatbox API error, HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 500));
        echo json_encode(['error' => 'Assistant is temporarily unavailable. कृपया नंतर प्रयत्न करा.']);
        exit;
    }

    // Extract text from content blocks
    $reply = '';
    foreach (($data['content'] ?? []) as $block) {
        if ($block['type'] === 'text') {
            $reply .= $block['text'];
        }
    }

    echo json_encode(['reply' => trim($reply) ?: 'माफ करा, उत्तर मिळाले नाही.']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="mr">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title>KrishiMitra — Agricart Farming Assistant</title>
<style>
/* ===== RESET ===== */
*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

/* ===== VARIABLES (Agricart dark green theme) ===== */
:root {
  --ag-dark:    #1a2e1a;
  --ag-green:   #2e7d32;
  --ag-bright:  #4caf50;
  --ag-light:   #e8f5e9;
  --ag-accent:  #8bc34a;
  --ag-header:  #0d1f0d;
  --ag-text:    #f1f8e9;
  --ag-muted:   #a5d6a7;
  --ag-border:  rgba(255,255,255,0.12);
  --ag-bubble-user: #2e7d32;
  --ag-bubble-bot:  #1e3a1e;
  --radius:     14px;
  --shadow:     0 8px 32px rgba(0,0,0,0.4);
}

body {
  font-family: 'Segoe UI', 'Noto Sans Devanagari', Arial, sans-serif;
  background: var(--ag-dark);
  color: var(--ag-text);
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 1rem;
}

/* ===== CHAT CONTAINER ===== */
#chat-container {
  width: 100%;
  max-width: 700px;
  display: flex;
  flex-direction: column;
  height: 92vh;
  max-height: 800px;
  background: #111f11;
  border: 1px solid var(--ag-border);
  border-radius: 18px;
  overflow: hidden;
  box-shadow: var(--shadow);
}

/* ===== HEADER ===== */
#chat-header {
  background: linear-gradient(135deg, var(--ag-header) 0%, #162816 100%);
  border-bottom: 1px solid var(--ag-border);
  padding: 14px 18px;
  display: flex;
  align-items: center;
  gap: 13px;
}

.header-logo {
  width: 44px; height: 44px;
  background: var(--ag-green);
  border-radius: 50%;
  display: flex; align-items: center; justify-content: center;
  font-size: 22px;
  flex-shrink: 0;
}

.header-title { font-size: 17px; font-weight: 700; color: #fff; letter-spacing: 0.3px; }
.header-sub   { font-size: 12px; color: var(--ag-muted); display: flex; align-items: center; gap: 5px; margin-top: 2px; }
.online-dot   { width: 8px; height: 8px; background: var(--ag-bright); border-radius: 50%; animation: pulse 2s infinite; }
@keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }

.header-brand { margin-left: auto; font-size: 13px; color: var(--ag-accent); font-weight: 600; }

/* ===== TICKER ===== */
#ticker {
  background: var(--ag-green);
  padding: 6px 0;
  font-size: 12px;
  font-weight: 600;
  color: #fff;
  overflow: hidden;
  white-space: nowrap;
}
#ticker-inner { display: inline-block; animation: ticker 30s linear infinite; }
@keyframes ticker { from{transform:translateX(100%)} to{transform:translateX(-100%)} }

/* ===== MESSAGES ===== */
#messages {
  flex: 1;
  overflow-y: auto;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 12px;
  scrollbar-width: thin;
  scrollbar-color: var(--ag-green) transparent;
}
#messages::-webkit-scrollbar { width: 4px; }
#messages::-webkit-scrollbar-thumb { background: var(--ag-green); border-radius: 4px; }

.msg-row { display: flex; flex-direction: column; max-width: 82%; }
.msg-row.user { align-self: flex-end; align-items: flex-end; }
.msg-row.bot  { align-self: flex-start; align-items: flex-start; }

.bubble {
  padding: 11px 15px;
  border-radius: var(--radius);
  font-size: 14px;
  line-height: 1.6;
  word-break: break-word;
}
.msg-row.user .bubble {
  background: var(--ag-bubble-user);
  color: #fff;
  border-bottom-right-radius: 4px;
}
.msg-row.bot .bubble {
  background: var(--ag-bubble-bot);
  color: var(--ag-text);
  border: 1px solid var(--ag-border);
  border-bottom-left-radius: 4px;
}
.bubble.thinking {
  background: #162816;
  color: var(--ag-muted);
  font-style: italic;
  border: 1px dashed var(--ag-border);
}

.msg-time { font-size: 11px; color: var(--ag-muted); margin-top: 3px; }

/* ===== QUICK BUTTONS ===== */
#quick-btns {
  padding: 8px 14px;
  display: flex;
  flex-wrap: wrap;
  gap: 7px;
  border-top: 1px solid var(--ag-border);
  background: #0e1e0e;
}
.qbtn {
  font-size: 12px;
  padding: 6px 12px;
  border: 1px solid rgba(76,175,80,0.4);
  border-radius: 20px;
  background: rgba(46,125,50,0.18);
  color: var(--ag-accent);
  cursor: pointer;
  transition: all 0.15s;
  font-family: inherit;
}
.qbtn:hover { background: rgba(46,125,50,0.4); border-color: var(--ag-bright); }

/* ===== INPUT ROW ===== */
#input-row {
  display: flex;
  gap: 10px;
  padding: 12px 14px;
  border-top: 1px solid var(--ag-border);
  background: var(--ag-header);
}
#user-input {
  flex: 1;
  background: #1e3a1e;
  border: 1px solid rgba(76,175,80,0.35);
  border-radius: 22px;
  padding: 10px 16px;
  font-size: 14px;
  color: #fff;
  font-family: inherit;
  outline: none;
  transition: border-color 0.2s;
}
#user-input::placeholder { color: var(--ag-muted); }
#user-input:focus { border-color: var(--ag-bright); }
#send-btn {
  width: 42px; height: 42px;
  border-radius: 50%;
  background: var(--ag-green);
  border: none;
  cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: background 0.15s;
  flex-shrink: 0;
}
#send-btn:hover { background: var(--ag-bright); }
#send-btn svg { width: 18px; height: 18px; fill: #fff; }

/* ===== FOOTER ===== */
#chat-footer {
  padding: 8px 14px;
  text-align: center;
  font-size: 11px;
  color: rgba(165,214,167,0.5);
  background: var(--ag-header);
  border-top: 1px solid var(--ag-border);
}

/* ===== RESPONSIVE ===== */
@media (max-width: 480px) {
  #chat-container { height: 100vh; border-radius: 0; max-height: none; }
  body { padding: 0; }
}
</style>
<link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>

<div id="chat-container">

  <!-- HEADER -->
  <div id="chat-header">
    <div class="header-logo">🌾</div>
    <div>
      <div class="header-title">KrishiMitra</div>
      <div class="header-sub">
        <span class="online-dot"></span>
        Internet-connected · सदैव उपलब्ध
      </div>
    </div>
    <div class="header-brand">🌿 Agri Cart</div>
  </div>

  <!-- TICKER -->
  <div id="ticker">
    <span id="ticker-inner">
      🌾 महाबीज हायब्रीड बियाणे उपलब्ध &nbsp;|&nbsp; 🧪 सेंद्रिय NPK खतांवर 15% सूट &nbsp;|&nbsp;
      📞 हेल्पलाइन: 1800-419-8888 &nbsp;|&nbsp; 🚜 कृषी स्टोअर — ऑनलाइन ऑर्डर करा &nbsp;|&nbsp;
      🌧️ पावसाळी पीक सल्ला आता उपलब्ध &nbsp;|&nbsp; 💰 शेतकरी योजना 2026 — आजच अर्ज करा
    </span>
  </div>

  <!-- MESSAGES -->
  <div id="messages">
    <div class="msg-row bot">
      <div class="bubble">
        🙏 <strong>नमस्कार! मी KrishiMitra आहे — Agricart चा AI शेती सहाय्यक.</strong><br><br>
        मला विचारा:<br>
        🌱 बियाणे व खतांचा सल्ला<br>
        📈 आजचे बाजारभाव<br>
        🐛 पिकांवरील रोग व उपाय<br>
        🌧️ हवामान व पीक नियोजन<br>
        🏛️ सरकारी कृषी योजना<br><br>
        कोणत्याही भाषेत विचारा — मराठी, हिंदी, English!
      </div>
      <div class="msg-time">आत्ता</div>
    </div>
  </div>

  <!-- QUICK BUTTONS -->
  <div id="quick-btns">
    <button class="qbtn" onclick="quickAsk('आजचा कांदा, टोमॅटो आणि सोयाबीन बाजारभाव सांग')">🧅 बाजारभाव</button>
    <button class="qbtn" onclick="quickAsk('उसाच्या पिकासाठी खत व्यवस्थापन कसे करावे?')">🌿 ऊस खत</button>
    <button class="qbtn" onclick="quickAsk('टोमॅटोवर पानांवर डाग पडतात — कोणता रोग आणि उपाय?')">🍅 रोग उपाय</button>
    <button class="qbtn" onclick="quickAsk('PM किसान योजना 2026 — कसे अर्ज करावे?')">🏛️ PM किसान</button>
    <button class="qbtn" onclick="quickAsk('पावसाळ्यात कोणते पीक घ्यावे — महाराष्ट्र?')">🌧️ खरीप पीक</button>
    <button class="qbtn" onclick="quickAsk('Agricart वर कोणते products उपलब्ध आहेत?')">🛒 Products</button>
  </div>

  <!-- INPUT -->
  <div id="input-row">
    <input
      type="text"
      id="user-input"
      placeholder="तुमचा प्रश्न टाइप करा..."
      autocomplete="off"
    />
    <button id="send-btn" onclick="sendMsg()" title="पाठवा">
      <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path d="M2 21l21-9L2 3v7l15 2-15 2v7z"/>
      </svg>
    </button>
  </div>

  <!-- FOOTER -->
  <div id="chat-footer">
    Powered by Agricart &nbsp;·&nbsp; 📞 1800-419-8888 &nbsp;·&nbsp; 🌐 Internet Connected
  </div>

</div>

<script>
const messagesEl = document.getElementById('messages');
const inputEl    = document.getElementById('user-input');
let chatHistory  = [];

/* ---- helpers ---- */
function timeNow() {
  const d = new Date();
  return d.getHours().toString().padStart(2,'0') + ':' + d.getMinutes().toString().padStart(2,'0');
}

function addMessage(text, role) {
  const row    = document.createElement('div');
  row.className = 'msg-row ' + role;
  const bubble = document.createElement('div');
  bubble.className = 'bubble';
  bubble.innerHTML = text.replace(/\n/g, '<br>');
  const time   = document.createElement('div');
  time.className = 'msg-time';
  time.textContent = timeNow();
  row.appendChild(bubble);
  row.appendChild(time);
  messagesEl.appendChild(row);
  messagesEl.scrollTop = messagesEl.scrollHeight;
  return bubble;
}

function addThinking() {
  const row    = document.createElement('div');
  row.className = 'msg-row bot';
  row.id = 'thinking-row';
  const bubble = document.createElement('div');
  bubble.className = 'bubble thinking';
  bubble.textContent = '⏳ Internet वर शोधतोय, थोडे थांबा...';
  row.appendChild(bubble);
  messagesEl.appendChild(row);
  messagesEl.scrollTop = messagesEl.scrollHeight;
}

function removeThinking() {
  const el = document.getElementById('thinking-row');
  if (el) el.remove();
}

/* ---- send ---- */
async function sendMsg() {
  const msg = inputEl.value.trim();
  if (!msg) return;
  inputEl.value = '';

  addMessage(msg, 'user');
  chatHistory.push({ role: 'user', content: msg });
  addThinking();

  try {
    const formData = new FormData();
    formData.append('action', 'chat');
    formData.append('message', msg);
    formData.append('history', JSON.stringify(chatHistory.slice(-10)));

    const res  = await fetch(window.location.href, { method: 'POST', body: formData });
    const data = await res.json();
    removeThinking();

    if (data.error) {
      addMessage('⚠️ ' + data.error, 'bot');
    } else {
      addMessage(data.reply, 'bot');
      chatHistory.push({ role: 'assistant', content: data.reply });
    }
  } catch (e) {
    removeThinking();
    addMessage('⚠️ Connection error. Internet तपासा आणि पुन्हा प्रयत्न करा.', 'bot');
  }
}

function quickAsk(q) {
  inputEl.value = q;
  sendMsg();
}

/* ---- Enter key ---- */
inputEl.addEventListener('keydown', e => {
  if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); sendMsg(); }
});
</script>
</body>
</html>