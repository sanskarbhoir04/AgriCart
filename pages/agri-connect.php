<?php
// =====================================================
// AgriCart — Agri Connect Forum (DB-connected)
// Phase 2 upgrade: 3-slide hero w/ real counts, sidebar reorder
// (Expert Advice on top), dynamic news/schemes/events/success-stories,
// real notifications, Load More + Sort + full-DB search, CSRF,
// fixed Mark-Solved permissions, safe (XSS-hardened) comment rendering.
// =====================================================
require_once __DIR__ . '/../includes/security.php';
agri_session_start();
include __DIR__ . '/../includes/db.php';
include __DIR__ . '/../includes/agri_connect_schema.php';
include __DIR__ . '/../includes/agri_connect_functions.php';
agri_connect_bootstrap_schema($conn);

$currentUserId   = $_SESSION['user_id'] ?? null;
$isLoggedIn      = $currentUserId !== null;
$currentUserRole = null;
if ($isLoggedIn) {
    $rStmt = $conn->prepare("SELECT role FROM users WHERE id = ? LIMIT 1");
    $rStmt->bind_param("i", $currentUserId);
    $rStmt->execute();
    $roleRow = $rStmt->get_result()->fetch_assoc();
    $currentUserRole = $roleRow['role'] ?? null;
}
$csrfToken = agri_csrf_token();

// ── Initial page of the Discussion Feed (rest loads via Load More / load_posts.php) ──
$FEED_PAGE_SIZE = 6;
$feedResult = agri_fetch_posts($conn, ['limit' => $FEED_PAGE_SIZE, 'offset' => 0, 'filter' => 'all', 'search' => '', 'sort' => 'latest'], $currentUserId);
$posts   = $feedResult['posts'];
$hasMore = $feedResult['has_more'];

// ── Real Community Statistics (live counts from DB — no demo numbers) ──
function agri_connect_safe_count($conn, $sql) {
    try {
        $res = @$conn->query($sql);
        if ($res && ($row = $res->fetch_assoc())) return (int)$row['c'];
    } catch (\Throwable $e) {
        // Table/column missing — just skip, keep 0.
    }
    return 0;
}
$stat_connected_farmers = agri_connect_safe_count($conn, "SELECT COUNT(*) c FROM users WHERE role IN ('farmer','seller','buyer')");
$stat_discussions       = agri_connect_safe_count($conn, "SELECT COUNT(*) c FROM community_posts WHERE is_approved = 1")
                         + agri_connect_safe_count($conn, "SELECT COUNT(*) c FROM comments WHERE is_approved = 1");
$stat_verified_experts  = agri_connect_safe_count($conn, "SELECT COUNT(*) c FROM users WHERE role IN ('expert','admin')");

$stat_platform_rating  = 0;
$stat_platform_reviews = 0;
try {
    $res = @$conn->query("SELECT AVG(rating) a, COUNT(*) c FROM reviews");
    if ($res && ($row = $res->fetch_assoc())) {
        $stat_platform_rating  = $row['a'] !== null ? round((float)$row['a'], 1) : 0;
        $stat_platform_reviews = (int)$row['c'];
    }
} catch (\Throwable $e) {
    // reviews table missing — keep 0.
}

// ── Today's Expert Advice (real, DB-driven — admin/expert_advice.php manages this) ──
$expertAdvice = null;
$adviceRes = @$conn->query(
    "SELECT ea.crop, ea.advice, ea.created_at, u.full_name, u.qualification, u.expertise, u.id AS expert_id
     FROM expert_advice ea JOIN users u ON u.id = ea.expert_user_id
     WHERE ea.is_active = 1 AND ea.deleted_at IS NULL ORDER BY ea.id DESC LIMIT 1"
);
if ($adviceRes && ($row = $adviceRes->fetch_assoc())) {
    $expertAdvice = $row;
    $expertAdvice['answer_count'] = agri_connect_safe_count(
        $conn, "SELECT COUNT(*) c FROM comments WHERE user_id = " . (int)$row['expert_id'] . " AND is_approved = 1"
    );
}

// ── Trending Topics (real activity: likes + live comment count, not just likes) ──
$trendingPosts = [];
$trendRes = @$conn->query(
    "SELECT p.id, p.title, p.body, p.likes_count,
            (SELECT COUNT(*) FROM comments c WHERE c.post_id = p.id AND c.is_approved = 1) AS comment_count
     FROM community_posts p
     WHERE p.is_approved = 1
       AND (p.likes_count > 0 OR EXISTS (SELECT 1 FROM comments c2 WHERE c2.post_id = p.id AND c2.is_approved = 1))
     ORDER BY (p.likes_count + (SELECT COUNT(*) FROM comments c3 WHERE c3.post_id = p.id AND c3.is_approved = 1)) DESC, p.id DESC
     LIMIT 3"
);
if ($trendRes) { while ($row = $trendRes->fetch_assoc()) { $trendingPosts[] = $row; } }

// ── Real notifications for the logged-in user ──
$notifications = [];
$unreadCount = 0;
if ($isLoggedIn) {
    $nStmt = $conn->prepare("SELECT id, title, message, type, is_read, link, created_at FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 5");
    $nStmt->bind_param("i", $currentUserId);
    $nStmt->execute();
    $notifications = $nStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $unreadCount = agri_connect_safe_count($conn, "SELECT COUNT(*) c FROM notifications WHERE user_id = " . (int)$currentUserId . " AND is_read = 0");
}

// ── Government Schemes — active + not past their deadline ──
$schemes = [];
$schemesRes = @$conn->query(
    "SELECT * FROM government_schemes WHERE is_active = 1 AND deleted_at IS NULL AND (last_date IS NULL OR last_date >= CURDATE()) ORDER BY id DESC LIMIT 4"
);
if ($schemesRes) { while ($row = $schemesRes->fetch_assoc()) { $schemes[] = $row; } }

// Real, currently-active Central/Maharashtra farmer schemes — shown only as a fallback when
// the admin panel hasn't added any scheme entries to `government_schemes` yet, so the section
// never has to show an empty "no schemes" state.
$schemesAreFallback = false;
if (empty($schemes)) {
    $schemesAreFallback = true;
    $schemes = [
        [
            'name' => 'पीएम-किसान सन्मान निधी योजना', 'last_date' => null,
            'official_link' => 'https://pmkisan.gov.in',
            'note' => 'जमीनधारक शेतकरी कुटुंबांना वर्षाला ₹6,000 (₹2,000 च्या 3 हप्त्यांत) DBT द्वारे मिळतात.',
        ],
        [
            'name' => 'नमो शेतकरी महासन्मान निधी योजना (महाराष्ट्र)', 'last_date' => null,
            'official_link' => 'https://pmkisan.gov.in',
            'note' => 'PM-KISAN लाभार्थ्यांना महाराष्ट्र सरकारकडून वर्षाला अतिरिक्त ₹6,000 — एकूण ₹12,000/वर्ष. वेगळी नोंदणी लागत नाही.',
        ],
        [
            'name' => 'प्रधानमंत्री फसल विमा योजना (PMFBY)', 'last_date' => null,
            'official_link' => 'https://pmfby.gov.in',
            'note' => 'दुष्काळ, पूर, कीड यांसारख्या नुकसानीपासून कमी हप्त्यात पीक विमा संरक्षण.',
        ],
        [
            'name' => 'किसान क्रेडिट कार्ड (KCC)', 'last_date' => null,
            'official_link' => 'https://www.jansamarth.in/kisan-credit-card-scheme',
            'note' => 'शेतीसाठी ₹5 लाखांपर्यंत सवलतीच्या व्याजदरात कर्ज सुविधा.',
        ],
    ];
}

// ── Upcoming Events — active + not already past ──
$events = [];
$eventsRes = @$conn->query(
    "SELECT * FROM agri_events WHERE is_active = 1 AND deleted_at IS NULL AND (event_end IS NOT NULL AND event_end >= CURDATE() OR (event_end IS NULL AND event_start >= CURDATE())) ORDER BY event_start ASC LIMIT 3"
);
if ($eventsRes) { while ($row = $eventsRes->fetch_assoc()) { $events[] = $row; } }

// Real, verified upcoming agri events — shown as a fallback only when the admin hasn't added
// any event yet. Dates/links checked against official sources, not invented. Date-filtered so a
// past event automatically stops showing on its own, without needing a manual code edit.
$eventsAreFallback = false;
if (empty($events)) {
    $eventsAreFallback = true;
    $today = date('Y-m-d');
    $candidateEvents = [
        [
            'title' => 'Agrovision 2026 — राष्ट्रीय कृषी प्रदर्शन (नागपूर)',
            'event_start' => '2026-11-27', 'event_end' => '2026-11-30',
            'location' => 'RTMNU Campus Ground, नागपूर', 'link' => 'https://www.agrovisionindia.in',
        ],
        [
            'title' => 'तुमच्या जिल्ह्यातील कृषी विज्ञान केंद्र (KVK) प्रशिक्षण व शेतकरी मेळावे',
            'event_start' => null, 'event_end' => null, 'display_date' => 'तारीख जिल्ह्यानुसार बदलते',
            'location' => 'तुमचा जिल्हा — जवळच्या KVK केंद्रावर तपासा', 'link' => 'https://kvk.icar.gov.in',
        ],
    ];
    foreach ($candidateEvents as $ce) {
        $endCheck = $ce['event_end'] ?: $ce['event_start'];
        if ($endCheck === null || $endCheck >= $today) { $events[] = $ce; }
    }
}

// Live auto-updating feed: pulls current agriculture-related announcements straight from PIB's
// public RSS feed (Government of India — no API key needed). Cached for a few hours so we don't
// hit PIB on every page load. This is what genuinely refreshes itself over time — no admin/manual
// step at all. If the feed can't be reached (blocked outbound requests, feed down, etc.) it's
// simply omitted rather than breaking the page.
function agri_connect_fetch_live_pib_updates() {
    $cacheFile = sys_get_temp_dir() . '/agri_connect_pib_cache.json';
    $cacheTtl = 6 * 3600;
    if (@file_exists($cacheFile) && (time() - @filemtime($cacheFile)) < $cacheTtl) {
        $cached = json_decode(@file_get_contents($cacheFile), true);
        if (is_array($cached)) return $cached;
    }
    $items = [];
    try {
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
        $xmlRaw = @file_get_contents('https://pib.gov.in/RssMain.aspx?ModId=6&Lang=1&Regid=1', false, $ctx);
        if ($xmlRaw) {
            libxml_use_internal_errors(true);
            $xml = @simplexml_load_string($xmlRaw);
            if ($xml && isset($xml->channel->item)) {
                $keywords = ['agri', 'farmer', 'krishi', 'kisan', 'crop', 'fasal', 'fertiliser', 'fertilizer', 'irrigation', 'monsoon'];
                foreach ($xml->channel->item as $item) {
                    $title = trim((string)$item->title);
                    $titleLower = mb_strtolower($title);
                    foreach ($keywords as $kw) {
                        if (mb_strpos($titleLower, $kw) !== false) {
                            $items[] = ['title' => $title, 'link' => trim((string)$item->link), 'pubDate' => trim((string)$item->pubDate)];
                            break;
                        }
                    }
                    if (count($items) >= 3) break;
                }
            }
        }
    } catch (Throwable $e) { /* fail quietly — the static verified list above still covers the section */ }
    @file_put_contents($cacheFile, json_encode($items));
    return $items;
}
$livePibUpdates = agri_connect_fetch_live_pib_updates();

// ── Agricultural News / Market Updates ──
$newsItems = [];
$newsRes = @$conn->query("SELECT * FROM agri_news WHERE is_active = 1 AND deleted_at IS NULL ORDER BY published_at DESC LIMIT 5");
if ($newsRes) { while ($row = $newsRes->fetch_assoc()) { $newsItems[] = $row; } }
$newsCategoryIcon = ['market' => '📈', 'weather' => '🌦', 'scheme' => '🏛', 'crop_advisory' => '🌱', 'news' => '📰'];

// Real official sources — shown as a fallback only when the admin hasn't posted any news yet,
// so farmers still land on genuine, live government market/advisory portals instead of an empty box.
$newsAreFallback = false;
if (empty($newsItems)) {
    $newsAreFallback = true;
    $newsItems = [
        [
            'category' => 'market', 'link' => 'https://agmarknet.gov.in',
            'title' => 'आजचे बाजारभाव पहा — Agmarknet (केंद्र सरकारचे अधिकृत मंडी दर पोर्टल)',
            'published_at' => date('Y-m-d'), 'source' => 'Agmarknet, कृषी मंत्रालय',
        ],
        [
            'category' => 'market', 'link' => 'https://www.enam.gov.in',
            'title' => 'e-NAM वर तुमचा शेतमाल राज्यभरातील मंडईंना थेट विका',
            'published_at' => date('Y-m-d'), 'source' => 'e-NAM, कृषी मंत्रालय',
        ],
        [
            'category' => 'weather', 'link' => 'https://mausam.imf.gov.in',
            'title' => 'IMD कडून तुमच्या जिल्ह्याचा हवामान अंदाज व पावसाचा इशारा',
            'published_at' => date('Y-m-d'), 'source' => 'भारतीय हवामान विभाग (IMD)',
        ],
        [
            'category' => 'scheme', 'link' => 'https://pib.gov.in/PressReleaseIframePage.aspx?PRID=0&MinistryId=15',
            'title' => 'कृषी मंत्रालयाच्या ताज्या योजना व धोरण घोषणा (PIB)',
            'published_at' => date('Y-m-d'), 'source' => 'Press Information Bureau, कृषी मंत्रालय',
        ],
    ];
}

// ── Farmer Success Stories — purely live/admin-submitted, no hardcoded fallback ──
$successStories = [];
$storiesRes = @$conn->query("SELECT * FROM success_stories WHERE is_active = 1 AND deleted_at IS NULL ORDER BY id DESC LIMIT 3");
if ($storiesRes) { while ($row = $storiesRes->fetch_assoc()) { $successStories[] = $row; } }
$storiesAreFallback = false;

// Static reference lists (shared with register.php so values stay consistent app-wide)
$cropOptions = ['Wheat','Rice','Sugarcane','Cotton','Soybean','Onion','Tomato','Grapes','Pomegranate','Turmeric','Jowar','Bajra','Tur Dal','Chickpea'];
$districtOptions = ['Pune','Nashik','Ahmednagar','Aurangabad','Solapur','Kolhapur','Satara','Sangli','Nagpur','Amravati','Latur','Nanded','Palghar','Thane','Raigad','Ratnagiri','Sindhudurg','Jalgaon','Dhule','Nandurbar','Buldhana','Akola','Washim','Yavatmal','Wardha','Bhandara','Gondia','Chandrapur','Gadchiroli','Osmanabad','Hingoli','Parbhani','Jalna','Beed'];

include __DIR__ . '/../includes/header.php';

$renderCtx = [
    'isLoggedIn' => $isLoggedIn, 'currentUserId' => $currentUserId,
    'currentUserRole' => $currentUserRole, 'basePath' => $base_path,
];
?>

<!-- Page Specific Styles for Agri Connect (Harvest Ledger Dashboard) -->
<style>
    @import url('https://fonts.googleapis.com/css2?family=Mukta:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&display=swap');

    :root {
        --page-bg: #F7F2E4;       /* warm wheat-paper background */
        --card-bg: #FFFDF7;
        --card-border: #E4D8BC;   /* soil-tinted border */
        --card-shadow: 0 10px 24px rgba(60, 42, 15, 0.08);
        --forest: #1B4332;        /* deep forest — headings, ink */
        --leaf: #2F8F4E;          /* primary action green */
        --leaf-deep: #1F6B39;     /* hover/active green */
        --marigold: #D98E1E;      /* harvest accent — highlights, unread */
        --marigold-deep: #B8740F;
        --clay: #8B5E3C;          /* soil brown — secondary accents */
        --primary-green: var(--leaf);   /* kept for compatibility with inline var() usage elsewhere on this page */
        --primary-dark: var(--forest);  /* kept for compatibility with inline var() usage elsewhere on this page */
    }

    /* Furrowed-field texture — faint plough-row lines across the wheat-paper background */
    /* Scoped to .agc-wrap (not body) so this page's font/background never bleeds into the
       shared site header — same scoping approach as about.php's .abt-wrap. */
    .agc-wrap {
        background-color: var(--page-bg);
        background-image: repeating-linear-gradient(115deg, rgba(139,94,60,0.05) 0px, rgba(139,94,60,0.05) 1px, transparent 1px, transparent 34px);
        font-family: 'Mukta', 'Poppins', sans-serif; color: var(--forest);
    }

    .btn-custom {
        background-color: var(--leaf); color: white; border: none;
        padding: 11px 26px; border-radius: 8px; cursor: pointer; font-weight: 700;
        transition: all 0.2s ease; font-family: 'Poppins', sans-serif; letter-spacing: 0.2px;
        box-shadow: inset 0 1px 0 rgba(255,255,255,0.25), 0 3px 0 var(--leaf-deep);
    }
    .btn-custom:hover { background-color: var(--leaf-deep); transform: translateY(1px); box-shadow: inset 0 1px 0 rgba(255,255,255,0.2), 0 2px 0 var(--leaf-deep); }
    .btn-custom:active { transform: translateY(3px); box-shadow: none; }
    .btn-custom:disabled { opacity: 0.65; cursor: not-allowed; transform: none; box-shadow: none; }
    .btn-outline-light { background: transparent; border: 1.5px solid rgba(255,255,255,0.8); color: #fff; padding: 9px 22px; border-radius: 8px; font-weight: 600; cursor: pointer; margin-left: 10px; text-decoration:none; display:inline-block; }
    .btn-outline-light:hover { background: rgba(255,255,255,0.15); }

    /* Layout Grid System */
    .forum-container { max-width: 1400px; margin: 0 auto; padding: 20px 5%; }
    .dashboard-grid { display: grid; grid-template-columns: 2fr 1fr; gap: 30px; margin-top: 40px; position: relative; z-index: 10; align-items: start; }

    /* Cards — each card is fully self-contained (own background, border, radius, shadow) so
       stacked cards never visually merge into one shared block. */
    .forum-container, .dashboard-grid, .main-content, .sidebar { background: transparent; box-shadow: none; }

    .glass-card {
        background: #ffffff;
        border: 1px solid #ead9b5;
        border-left: 5px solid #38a169;
        border-radius: 14px;
        padding: 22px 25px 25px;
        box-shadow: 0 6px 18px rgba(0,0,0,0.08);
        margin-bottom: 28px;
    }
    .glass-card:hover { transform: translateY(-2px); transition: 0.25s ease; }
    .glass-card.no-box { background: transparent; border: none; border-left: none; box-shadow: none; padding: 25px 0; margin-top: 0; }
    .glass-card.no-box:hover { transform: none; }

    .section-title { font-size: 21px; color: var(--forest); margin-bottom: 15px; padding-bottom: 0; display: flex; align-items: center; gap: 10px; font-weight: 800; letter-spacing: -0.2px; }
    .section-title::before { content: ''; width: 14px; height: 14px; background: var(--marigold); flex-shrink: 0; transform: rotate(45deg); border-radius: 2px; box-shadow: 0 0 0 3px rgba(217,142,30,0.18); }

    /* 2. Ask a Question Form */
    .ask-question input[type="text"], .ask-question textarea, .ask-question select {
        width: 100%; padding: 12px 15px; border: 1px solid var(--card-border); border-radius: 8px; background: #FFFDF8;
        margin-bottom: 12px; font-size: 15px; font-family: 'Mukta', sans-serif; box-sizing: border-box; color: var(--forest);
    }
    .ask-question textarea { height: 100px; resize: none; }
    .ask-question input:focus, .ask-question textarea:focus, .ask-question select:focus { outline: none; border-color: var(--leaf); }
    .ask-question .field-row { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
    .ask-question .char-counter { text-align: right; font-size: 12px; color: #9c8f76; margin-top: -8px; margin-bottom: 12px; }
    .ask-question .char-counter.limit-near { color: var(--marigold); font-weight: 600; }
    .ask-question .actions { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; }
    .ask-question .img-upload-row { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
    .ask-question .img-upload-label {
        display: inline-flex; align-items: center; gap: 8px; padding: 8px 16px; border: 1px dashed var(--leaf);
        border-radius: 20px; color: var(--leaf); cursor: pointer; font-size: 13px; font-weight: 600; background: rgba(47,143,78,0.06);
    }
    .ask-question .img-preview-wrap { display: flex; align-items: center; gap: 8px; }
    .ask-question .img-preview-name { font-size: 12px; color: #7a6f5c; }
    .ask-question .img-remove-btn { background: none; border: none; color: #c62828; cursor: pointer; font-size: 13px; display: none; }
    .ask-question .img-size-note { font-size: 11px; color: #9c8f76; margin-top: -8px; margin-bottom: 10px; }
    .ask-question .post-btn-loading { display: none; }
    .ask-question .post-btn.loading .post-btn-loading { display: inline; }
    .ask-question .post-btn.loading .post-btn-idle { display: none; }
    @media (max-width: 700px) { .ask-question .field-row { grid-template-columns: 1fr; } }

    /* 3. Discussion Feed */
    .search-bar { position: relative; margin-bottom: 12px; }
    .search-bar input { width: 100%; padding: 12px 15px 12px 42px; border: 1px solid var(--card-border); border-radius: 25px; font-size: 14px; box-sizing: border-box; font-family: 'Mukta', sans-serif; background: #FFFDF8; }
    .search-bar input:focus { outline: none; border-color: var(--leaf); }
    .search-bar i { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: #9c8f76; }

    .feed-toolbar { display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px; margin-bottom: 12px; }
    .sort-select { padding: 8px 12px; border: 1px solid var(--card-border); border-radius: 8px; font-size: 13px; background: #fff; color: var(--forest); }

    .post-filters { display: flex; gap: 10px; margin-bottom: 20px; overflow-x: auto; padding-bottom: 5px;}
    .filter-badge { padding: 6px 15px; background: #F1EADA; color: var(--forest); border-radius: 20px; font-size: 14px; cursor: pointer; white-space: nowrap; transition: 0.2s; font-weight: 500; }
    .filter-badge.active, .filter-badge:hover { background: var(--leaf); color: white; }

    .post-card { border-bottom: 1px solid var(--card-border); padding-bottom: 15px; margin-bottom: 20px; }
    .post-card:last-child { border-bottom: none; margin-bottom: 0; }
    .no-results-msg { display: none; text-align: center; padding: 2rem; color: #9c8f76; }
    .feed-loading { display:none; text-align:center; padding:1rem; color:#9c8f76; }

    .post-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
    .post-header img { width: 45px; height: 45px; border-radius: 50%; }
    .post-meta h4 { margin: 0; font-size: 16px; color: var(--forest); display: flex; align-items: center; flex-wrap: wrap; gap: 6px; }
    .post-meta small { color: #8a7f6a; font-size: 13px; }
    .badge-tag { background: rgba(47,143,78,0.1); color: var(--leaf-deep); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }
    .badge-expert { background: var(--leaf); color: white; padding: 2px 9px; border-radius: 10px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px; }
    .badge-solved { background: var(--leaf-deep); color: white; padding: 3px 10px; border-radius: 10px; font-size: 11px; font-weight: 700; }
    .badge-crop { background: #FBEBD2; color: var(--clay); padding: 2px 8px; border-radius: 4px; font-size: 12px; font-weight: 600; }

    .post-title { font-size: 18px; font-weight: 700; color: var(--forest); margin-bottom: 6px; line-height: 1.4;}
    .post-body-text { font-size: 15px; color: #4a4436; margin-bottom: 12px; line-height: 1.5; }
    .post-image { width: 100%; max-height: 320px; object-fit: cover; border-radius: 10px; margin-bottom: 15px; }

    .post-actions { display: flex; gap: 16px; border-top: 1px dashed var(--card-border); padding-top: 10px; flex-wrap: wrap; position: relative; }
    .action-btn { background: none; border: none; font-size: 14px; color: #6b6250; cursor: pointer; display: flex; align-items: center; gap: 6px; transition: 0.2s; font-family: 'Mukta', sans-serif;}
    .action-btn:hover { color: var(--leaf); }
    .action-btn.liked { color: #d32f2f; }
    .action-btn.saved { color: var(--marigold); }
    .action-btn.reported { color: #b71c1c; }
    .action-btn.solved-btn { margin-left: auto; }

    .share-menu { position: absolute; bottom: 42px; background: #fff; border: 1px solid var(--card-border); border-radius: 8px; box-shadow: 0 4px 18px rgba(60,42,15,0.14); padding: 6px; z-index: 20; display: none; min-width: 160px; }
    .share-menu.open { display: block; }
    .share-menu button { display: flex; align-items: center; gap: 8px; width: 100%; text-align: left; padding: 8px 10px; background: none; border: none; cursor: pointer; font-size: 13px; color: var(--forest); border-radius: 6px; }
    .share-menu button:hover { background: #F6F0E2; }

    /* Expandable Comments */
    .comments-section { background: #FBF7EC; padding: 15px; border-radius: 8px; margin-top: 15px; display: none; border: 1px solid var(--card-border);}
    .comment-item { display: flex; gap: 10px; margin-bottom: 15px; border-bottom: 1px solid #EFE7D4; padding-bottom: 10px; }
    .comment-item img { width: 35px; height: 35px; border-radius: 50%; }
    .comment-content h5 { margin: 0 0 3px 0; font-size: 14px; color: var(--forest); display: flex; align-items: center; gap: 6px; }
    .comment-content p { margin: 0; font-size: 14px; color: #5a5343; }

    .add-reply { display: flex; gap: 10px; margin-top: 15px; }
    .add-reply input { flex: 1; padding: 10px; border: 1px solid var(--card-border); border-radius: 20px; outline: none; }
    .add-reply button { background: var(--leaf); color: white; border: none; width: 40px; height: 40px; border-radius: 50%; cursor: pointer; }

    .load-more-wrap { text-align: center; margin-top: 10px; }
    .load-more-btn { background: #fff; border: 1.5px solid var(--leaf); color: var(--leaf-deep); padding: 10px 30px; border-radius: 25px; cursor: pointer; font-weight: 600; }
    .load-more-btn:hover { background: rgba(47,143,78,0.08); }
    .load-more-btn:disabled { opacity: 0.6; cursor: not-allowed; }

    /* Sidebar Lists & Widgets */
    .list-items li { padding: 12px 0; border-bottom: 1px dashed var(--card-border); display: flex; gap: 15px; font-size: 15px; color: #4a4436;}
    .list-items li:last-child { border-bottom: none; padding-bottom: 0;}
    .list-items i { color: var(--leaf); margin-top: 4px; }
    .empty-note { color: #9c8f76; font-size: 13.5px; padding: 6px 0; }

    /* Expert Advice Corner (in sidebar now) */
    .expert-meta-row { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 8px; }
    .expert-meta-row span { font-size: 11.5px; color: #6b6250; background: #F6F0E2; padding: 3px 9px; border-radius: 10px; }

    /* Notifications */
    .notif-dot { width: 8px; height: 8px; border-radius: 50%; background: var(--marigold); display: inline-block; margin-left: 6px; }
    .list-items li.notif-item { padding: 14px; border-bottom: none; background: transparent; border: 1px solid var(--card-border); border-radius: 10px; margin-bottom: 10px; }
    .list-items li.notif-item:last-child { margin-bottom: 0; padding-bottom: 14px; }
    .notif-item.unread { background: rgba(217,142,30,0.05); border-color: rgba(217,142,30,0.4); margin-left: 0; padding-left: 14px; }

    /* Trending Topics — separate boxed cards */
    .trend-item { display: flex; gap: 12px; align-items: flex-start; padding: 12px 14px; font-size: 13.5px; background: #FBF7EC; border: 1px solid var(--card-border); border-radius: 10px; margin-bottom: 10px; }
    .trend-item:last-child { margin-bottom: 0; }
    .trend-rank { font-weight: 800; color: var(--marigold); font-size: 15px; }
    .trend-meta { display: flex; align-items: center; flex-wrap: wrap; gap: 4px 14px; margin-top: 4px; }
    .trend-meta span { display: inline-flex; align-items: center; gap: 5px; }

    /* News badges */
    .news-cat-badge { font-size: 10.5px; padding: 2px 8px; border-radius: 8px; background: #E9F3EB; color: var(--leaf-deep); font-weight: 700; margin-right: 6px; }
    .news-meta { font-size: 11.5px; color: #9c8f76; margin-top: 4px; }

    /* Success Story cards (horizontal) */
    .story-scroll { display: flex; gap: 16px; overflow-x: auto; padding-bottom: 6px; }
    .story-card { min-width: 260px; border: 1px solid var(--card-border); border-top: 3px solid var(--marigold); border-radius: 10px; padding: 16px; flex-shrink: 0; background: #FBF7EC; }
    .story-card h4 { margin: 0 0 4px 0; font-size: 15px; color: var(--forest); }
    .story-card .story-tags span { font-size: 12px; color: var(--leaf-deep); margin-right: 8px; }
    .story-card p { font-size: 13px; color: #5a5343; margin: 8px 0; }

    /* 5. Real-Time Weather Widget */
    .weather-widget { background: linear-gradient(135deg, var(--forest), #3E7A3F 60%, var(--marigold)); color: white; border: none; }
    .weather-widget .section-title { color: white; border-color: rgba(255,255,255,0.5); }
    .weather-widget .section-title::before { background: #fff; }
    .weather-widget .weather-temp, .weather-widget .weather-condition, .weather-widget .weather-location { color: #ffffff; }
    .weather-widget .weather-temp span { color: rgba(255,255,255,0.75); }
    .weather-widget .weather-meta { background: rgba(255,255,255,0.14); }
    .weather-widget .weather-meta-item, .weather-widget .weather-meta-item i { color: rgba(255,255,255,0.92); }
    .weather-widget .weather-advice { background: rgba(255,255,255,0.16); color: #ffffff; }
    .weather-widget .forecast-day { background: rgba(255,255,255,0.14); }
    .weather-widget .fc-day, .weather-widget .fc-temp { color: #ffffff; }
    .weather-widget .fc-temp span { color: rgba(255,255,255,0.7) !important; }
    .weather-loading { font-size: 14px; opacity: 0.85; font-style: italic; }

    @media (max-width: 992px) { .dashboard-grid { grid-template-columns: 1fr; } }

    /* Force clear separation between sidebar widget cards (weather, schemes, events, trending,
       notifications) — uses flex + gap so cards never touch, independent of any margin rules
       coming from the shared site stylesheet. */
    .dashboard-grid .sidebar {
        display: flex; flex-direction: column;
        position: sticky; top: 20px; align-self: start;
        max-height: calc(100vh - 40px); overflow-y: auto;
        padding-right: 4px; /* keep scrollbar off the card edge */
    }
    @media (max-width: 992px) { .dashboard-grid .sidebar { position: static; max-height: none; overflow-y: visible; } }
    @media (max-width: 768px) { .ask-question .actions { flex-direction: column; gap: 15px; align-items: stretch; } }
</style>

<!-- MAIN APP BODY -->
<div class="agc-wrap">
<div class="slider-wrap">
    <div class="slide active" style="background-image:url('<?php echo htmlspecialchars($base_path); ?>/assets/images/agriconnect/hero-slide-1.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s1-tag">Farmer Community</div>
            <h1 id="s1-title">Farmer Community Connection</h1>
            <p id="s1-sub">A group of farmers standing in the field, checking updates together on a mobile phone</p>
            <div>
                <button class="btn-custom" id="s1-btn" onclick="document.getElementById('ask-title-input').focus();">Join the Discussion</button>
                <a class="btn-outline-light" id="s1-btn2" href="<?php echo htmlspecialchars($base_path); ?>/pages/krishi_bazaar.php">View Market Rates</a>
            </div>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo htmlspecialchars($base_path); ?>/assets/images/agriconnect/hero-slide-2.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s2-tag">Expert Guidance</div>
            <h1 id="s2-title">Farmer &amp; Agriculture Expert</h1>
            <p id="s2-sub">A farmer discussing his crop with an agronomist / expert</p>
            <div>
                <a class="btn-custom" id="s2-btn" href="#expert-title">Read Expert Advice</a>
                <button class="btn-outline-light" id="s2-btn2" onclick="document.getElementById('ask-title-input').focus();">Ask a Question</button>
            </div>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo htmlspecialchars($base_path); ?>/assets/images/agriconnect/hero-slide-3.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s3-tag">Digital Platform</div>
            <h1 id="s3-title">Digital Discussion Platform</h1>
            <p id="s3-sub">Farmers using the Agri Connect community on their smartphones for messages and discussions</p>
            <div>
                <a class="btn-custom" id="s3-btn" href="#trend-title">Browse Discussions</a>
                <a class="btn-outline-light" id="s3-btn2" href="#trending-title">Trending Topics</a>
            </div>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo htmlspecialchars($base_path); ?>/assets/images/agriconnect/hero-slide-4.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s4-tag">Knowledge Sharing</div>
            <h1 id="s4-title">Knowledge Sharing Meeting</h1>
            <p id="s4-sub">Village farmers sitting together in a circle, sharing their farming experiences and solutions</p>
            <div>
                <a class="btn-custom" id="s4-btn" href="#story-title">Read Success Stories</a>
                <a class="btn-outline-light" id="s4-btn2" href="#news-title">Latest News</a>
            </div>
        </div>
    </div>

    <div class="slide" style="background-image:url('<?php echo htmlspecialchars($base_path); ?>/assets/images/agriconnect/hero-slide-5.png');">
        <div class="slide-overlay"></div>
        <div class="slide-content">
            <div class="slide-tag" id="s5-tag">Farmer to Buyer</div>
            <h1 id="s5-title">Farmer-to-Buyer Connection</h1>
            <p id="s5-sub">A farmer shaking hands with a verified buyer, with crates of fresh produce beside them</p>
            <div>
                <a class="btn-custom" id="s5-btn" href="<?php echo htmlspecialchars($base_path); ?>/pages/marketplace.php">Visit Marketplace</a>
                <a class="btn-outline-light" id="s5-btn2" href="<?php echo htmlspecialchars($base_path); ?>/pages/krishi_bazaar.php">Check Market Rates</a>
            </div>
        </div>
    </div>

    <div class="slider-dots" id="sliderDots"></div>
</div>

<!-- Community Statistics Counter -->
<section class="stats">
    <div class="stat-item"><h3 class="counter" data-target="<?php echo $stat_connected_farmers; ?>">0</h3><p id="st1">Connected Farmers</p></div>
    <div class="stat-item"><h3 class="counter" data-target="<?php echo $stat_discussions; ?>">0</h3><p id="st2">Discussions</p></div>
    <div class="stat-item"><h3 class="counter" data-target="<?php echo $stat_verified_experts; ?>">0</h3><p id="st3">Verified Experts</p></div>
    <div class="stat-item"><div class="mini-stars"><?php echo $stat_platform_reviews > 0 ? '<i class="fa-solid fa-star"></i>' : '<i class="fa-regular fa-star"></i>'; ?></div><h3><?php echo $stat_platform_reviews > 0 ? $stat_platform_rating : 'New'; ?></h3><p id="st4">Platform Rating</p></div>
</section>

<div class="forum-container dashboard-grid">
    <!-- LEFT SIDE: Core Community Features (70%) -->
    <div class="main-content">

        <!-- Ask a Question -->
        <div class="glass-card ask-question">
            <h2 class="section-title" id="ask-title">Ask a Question or Share a Problem</h2>
            <input type="text" id="ask-title-input" placeholder="Question title..." maxlength="200">
            <textarea id="ask-box" placeholder="Write your question here..." maxlength="2000"></textarea>
            <div class="char-counter" id="char-counter">0 / 2000</div>
            <div class="field-row">
                <select id="q-category">
                    <option value="question" id="opt-1">Questions</option>
                    <option value="crop" id="opt-2">Crop Management</option>
                    <option value="pest" id="opt-3">Pest Control</option>
                    <option value="market" id="opt-4">Market Rates</option>
                    <option value="schemes" id="opt-5">Govt Schemes</option>
                </select>
                <select id="q-crop">
                    <option value="" id="opt-crop-0">Select Crop (Optional)</option>
                    <?php foreach ($cropOptions as $c): ?>
                        <option value="<?php echo htmlspecialchars($c); ?>"><?php echo htmlspecialchars($c); ?></option>
                    <?php endforeach; ?>
                </select>
                <select id="q-district">
                    <option value="" id="opt-dist-0">Select District (Optional)</option>
                    <?php foreach ($districtOptions as $d): ?>
                        <option value="<?php echo htmlspecialchars($d); ?>"><?php echo htmlspecialchars($d); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="actions">
                <div>
                    <div class="img-upload-row">
                        <label class="img-upload-label" for="q-image">
                            <i class="fa-solid fa-image"></i> <span id="img-upload-lbl">Add Photo (Optional)</span>
                        </label>
                        <input type="file" id="q-image" accept="image/jpeg,image/png,image/webp" style="display:none;">
                        <div class="img-preview-wrap">
                            <span class="img-preview-name" id="img-preview-name"></span>
                            <button type="button" class="img-remove-btn" id="img-remove-btn" onclick="clearImageSelection()"><i class="fa-solid fa-xmark"></i> <span id="img-remove-lbl">Remove</span></button>
                        </div>
                    </div>
                    <div class="img-size-note" id="img-size-note">Max file size: 5MB (JPG, PNG, WEBP)</div>
                </div>
                <button class="btn-custom post-btn" id="post-btn" onclick="postQuestion()">
                    <span class="post-btn-idle"><i class="fa-solid fa-paper-plane"></i> <span id="post-btn-txt">Post Question</span></span>
                    <span class="post-btn-loading"><i class="fa-solid fa-spinner fa-spin"></i> <span id="post-btn-loading-txt">Posting...</span></span>
                </button>
            </div>
        </div>

        <!-- Discussion Feed (main focus of the page) -->
        <div class="glass-card">
            <h2 class="section-title" id="trend-title">Discussion Feed</h2>

            <div class="search-bar">
                <i class="fa-solid fa-magnifying-glass"></i>
                <input type="text" id="search-input" placeholder="Search crop, disease or farmer question..." oninput="onFeedControlsChanged()">
            </div>

            <div class="feed-toolbar">
                <div class="post-filters" style="margin-bottom:0;">
                    <div class="filter-badge active" id="fl-1" data-filter="all">All</div>
                    <div class="filter-badge" id="fl-2" data-filter="question">Questions</div>
                    <div class="filter-badge" id="fl-3" data-filter="crop">Crop</div>
                    <div class="filter-badge" id="fl-4" data-filter="pest">Pest</div>
                    <div class="filter-badge" id="fl-5" data-filter="market">Market</div>
                    <div class="filter-badge" id="fl-6" data-filter="schemes">Schemes</div>
                    <div class="filter-badge" id="fl-7" data-filter="unanswered">Unanswered</div>
                    <div class="filter-badge" id="fl-8" data-filter="solved">Solved</div>
                </div>
                <select class="sort-select" id="sort-select" onchange="onFeedControlsChanged()">
                    <option value="latest" id="sort-1">Latest</option>
                    <option value="most_liked" id="sort-2">Most Liked</option>
                    <option value="most_discussed" id="sort-3">Most Discussed</option>
                    <option value="unanswered" id="sort-4">Unanswered First</option>
                </select>
            </div>

            <div id="postsFeed">
            <?php if (empty($posts)): ?>
                <p style="color:#999;text-align:center;padding:2rem" id="empty-feed-msg">अजून कोणतीही चर्चा नाही — तुम्हीच पहिला प्रश्न विचारा!</p>
            <?php else: foreach ($posts as $post) { echo agri_render_post_card($post, $renderCtx); } endif; ?>
            </div>
            <p class="no-results-msg" id="no-results-msg">No discussions match your filters — try a different search or category.</p>
            <p class="feed-loading" id="feed-loading"><i class="fa-solid fa-spinner fa-spin"></i> Loading...</p>
            <div class="load-more-wrap" id="load-more-wrap" style="<?php echo $hasMore ? '' : 'display:none;'; ?>">
                <button class="load-more-btn" id="load-more-btn" onclick="loadMoreDiscussions()"><span id="load-more-txt">Load More Discussions</span></button>
            </div>
        </div>

        <!-- Success Stories -->
        <div class="glass-card">
            <h2 class="section-title" id="story-title">Inspirational Farmer Success Stories</h2>
            <?php if ($storiesAreFallback): ?>
                <p id="story-fallback-note" style="font-size:12px;color:#999;margin:-8px 0 14px;">सध्या admin ने यशोगाथा जोडलेल्या नाहीत — त्यामुळे प्रसारमाध्यमांत आधीच प्रसिद्ध झालेल्या काही खऱ्या शेतकऱ्यांच्या यशोगाथा दाखवल्या आहेत.</p>
            <?php endif; ?>
            <?php if (empty($successStories)): ?>
                <p class="empty-note" id="story-empty">अजून कोणतीही यशोगाथा जोडलेली नाही.</p>
            <?php else: ?>
            <div class="story-scroll">
                <?php foreach ($successStories as $s): ?>
                <div class="story-card">
                    <h4 class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($s['farmer_name'] . ($s['district'] ? ' — ' . $s['district'] : ''), ENT_QUOTES); ?>"><?php echo htmlspecialchars($s['farmer_name']); ?><?php echo $s['district'] ? ' — ' . htmlspecialchars($s['district']) : ''; ?></h4>
                    <div class="story-tags">
                        <?php if ($s['crop']): ?><span>🌾 <?php echo htmlspecialchars($s['crop']); ?></span><?php endif; ?>
                        <?php if ($s['income_change']): ?><span>📈 <?php echo htmlspecialchars($s['income_change']); ?></span><?php endif; ?>
                    </div>
                    <p class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($s['headline'], ENT_QUOTES); ?>"><strong><?php echo htmlspecialchars($s['headline']); ?></strong></p>
                    <?php if ($s['description']): ?><p class="i18n-dynamic" data-mr="<?php echo htmlspecialchars(mb_strimwidth($s['description'], 0, 140, '...'), ENT_QUOTES); ?>"><?php echo htmlspecialchars(mb_strimwidth($s['description'], 0, 140, '...')); ?></p><?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Agricultural News & Market Updates -->
        <div class="glass-card">
            <h2 class="section-title" id="news-title">Agricultural News & Mandi Updates</h2>
            <?php if ($newsAreFallback): ?>
                <p id="news-fallback-note" style="font-size:12px;color:#999;margin:-8px 0 14px;">सध्या admin ने बातम्या जोडलेल्या नाहीत — त्यामुळे खालील अधिकृत सरकारी पोर्टल्स दाखवले आहेत.</p>
            <?php endif; ?>
            <?php if (empty($newsItems)): ?>
                <p class="empty-note" id="news-empty">अजून कोणत्याही बातम्या जोडलेल्या नाहीत.</p>
            <?php else: ?>
            <ul class="list-items" style="list-style: none; padding:0;">
                <?php foreach ($newsItems as $n): ?>
                <li>
                    <span><?php echo $newsCategoryIcon[$n['category']] ?? '📰'; ?></span>
                    <div>
                        <span class="news-cat-badge"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $n['category']))); ?></span>
                        <?php if ($n['link']): ?>
                            <a href="<?php echo htmlspecialchars($n['link']); ?>" target="_blank" rel="noopener" class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($n['title'], ENT_QUOTES); ?>" style="color:inherit;text-decoration:none;"><?php echo htmlspecialchars($n['title']); ?></a>
                        <?php else: ?>
                            <span class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($n['title'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($n['title']); ?></span>
                        <?php endif; ?>
                        <div class="news-meta"><?php echo date('d M Y', strtotime($n['published_at'])); ?><?php echo $n['source'] ? ' • ' . htmlspecialchars($n['source']) : ''; ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT SIDE: Sidebar Widgets (30%) — Expert Advice first -->
    <div class="sidebar">

        <!-- Expert Advice Corner (moved to top of sidebar) -->
        <div class="glass-card">
            <h2 class="section-title" id="expert-title">Expert Advice Corner</h2>
            <?php if ($expertAdvice): ?>
            <div style="display: flex; gap: 15px; align-items: flex-start;">
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($expertAdvice['full_name']); ?>&background=2e8b57&color=fff" style="border-radius: 50%; width: 48px; flex-shrink:0;">
                <div>
                    <h4 style="color: var(--primary-dark); font-size: 15.5px; margin-bottom: 4px;">
                        <?php echo htmlspecialchars($expertAdvice['full_name']); ?><?php echo $expertAdvice['qualification'] ? ' (' . htmlspecialchars($expertAdvice['qualification']) . ')' : ''; ?>
                    </h4>
                    <p style="font-size: 13.5px; color: #555;">
                        <?php echo ($expertAdvice['crop'] ? '🌾 ' . htmlspecialchars($expertAdvice['crop']) . ': ' : '') . nl2br(htmlspecialchars($expertAdvice['advice'])); ?>
                    </p>
                    <div class="expert-meta-row">
                        <?php if ($expertAdvice['expertise']): ?><span>👨‍🔬 <?php echo htmlspecialchars($expertAdvice['expertise']); ?></span><?php endif; ?>
                        <span>💬 <?php echo (int)$expertAdvice['answer_count']; ?> <span class="lbl-answers">answers</span></span>
                        <span>📅 <?php echo date('d M Y', strtotime($expertAdvice['created_at'])); ?></span>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <p class="empty-note" id="expert-empty">अजून कोणताही तज्ज्ञ सल्ला उपलब्ध नाही — लवकरच येईल.</p>
            <?php endif; ?>
        </div>

        <!-- Live Weather -->
        <div class="glass-card weather-widget" id="wd-card">
            <h2 class="section-title" id="wd-title">Live Weather Forecast</h2>
            <div id="wd-content">
                <div id="wd-loading" style="text-align:center;padding:20px;">
                    <i class="fa-solid fa-spinner fa-spin fa-2x"></i><br>
                    <span class="weather-loading" id="wd-loading-txt">Detecting your location...</span>
                </div>
                <div id="wd-error" style="display:none;text-align:center;padding:10px;font-size:13px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <span id="wd-err-txt">Location access denied. Please allow location.</span>
                    <br><button onclick="loadWeatherGPS()" style="margin-top:8px;padding:6px 16px;background:rgba(255,255,255,0.2);color:#fff;border:1px solid rgba(255,255,255,0.4);border-radius:6px;cursor:pointer;font-size:13px;">Try Again</button>
                </div>
            </div>
        </div>

        <!-- Trending Topics -->
        <div class="glass-card">
            <h2 class="section-title" id="trending-title">Trending Topics</h2>
            <?php if (empty($trendingPosts)): ?>
                <p class="empty-note" id="trending-empty">अजून कोणतीही चर्चा trending नाही.</p>
            <?php else: ?>
            <?php $rank = 1; foreach ($trendingPosts as $tp): ?>
                <div class="trend-item">
                    <span class="trend-rank">#<?php echo $rank++; ?></span>
                    <div>
                        <div class="trend-title-text" style="font-weight:600;color:#333;"><?php echo htmlspecialchars($tp['title'] ?: mb_strimwidth($tp['body'], 0, 60, '...')); ?></div>
                        <div class="trend-meta" style="color:#999;">
                            <span><i class="fa-solid fa-heart" style="color:#d32f2f;"></i> <?php echo (int)$tp['likes_count']; ?> likes</span>
                            <?php if ((int)$tp['comment_count'] > 0): ?>
                                <span><i class="fa-solid fa-comment" style="color:var(--primary-green);"></i> <?php echo (int)$tp['comment_count']; ?> comments</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Notification Center -->
        <div class="glass-card">
            <h2 class="section-title" id="alert-title">Notification Center<?php echo $unreadCount > 0 ? '<span class="notif-dot"></span>' : ''; ?></h2>
            <?php if (!$isLoggedIn): ?>
                <p class="empty-note" id="notif-login-msg">सूचना पाहण्यासाठी login करा.</p>
            <?php elseif (empty($notifications)): ?>
                <p class="empty-note" id="notif-empty">सध्या कोणत्याही नवीन सूचना नाहीत.</p>
            <?php else: ?>
            <ul class="list-items" style="list-style: none; padding:0;">
                <?php foreach ($notifications as $note): ?>
                <li class="notif-item <?php echo $note['is_read'] ? '' : 'unread'; ?>" style="align-items:flex-start;">
                    <i class="fa-solid fa-bell" style="<?php echo $note['is_read'] ? '' : 'color:var(--marigold);'; ?>"></i>
                    <div style="flex:1">
                        <div class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($note['title'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($note['title']); ?></div>
                        <?php if (!empty($note['message'])): ?>
                            <div class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($note['message'], ENT_QUOTES); ?>" style="font-size:12.5px;color:#666;margin-top:2px;line-height:1.4"><?php echo htmlspecialchars($note['message']); ?></div>
                        <?php endif; ?>
                        <div class="news-meta"><?php echo date('d M, H:i', strtotime($note['created_at'])); ?></div>
                        <?php if (!empty($note['link'])): ?>
                            <a href="<?php echo htmlspecialchars($note['link']); ?>" style="display:inline-flex;align-items:center;gap:6px;margin-top:8px;padding:6px 14px;background:var(--leaf-deep);color:#fff;border-radius:8px;font-size:12.5px;font-weight:600;text-decoration:none">
                                <?php if ($note['type'] === 'booking'): ?><i class="fa-solid fa-indian-rupee-sign"></i> Pay Now<?php else: ?><i class="fa-solid fa-arrow-right"></i> View<?php endif; ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Government Schemes -->
        <div class="glass-card">
            <h2 class="section-title" id="gov-title">Government Schemes & Subsidies</h2>
            <?php if ($schemesAreFallback): ?>
                <p id="gov-fallback-note" style="font-size:12px;color:#999;margin:-8px 0 14px;">सध्या admin ने योजना जोडलेल्या नाहीत — त्यामुळे खालील सध्या सुरू असलेल्या मुख्य केंद्र/राज्य योजना दाखवल्या आहेत.</p>
            <?php endif; ?>
            <?php if (empty($schemes)): ?>
                <p class="empty-note" id="gov-empty">सध्या कोणतीही योजना जोडलेली नाही.</p>
            <?php else: ?>
            <ul class="list-items" style="list-style: none; padding:0;">
                <?php foreach ($schemes as $sch): ?>
                <li>
                    <i class="fa-solid fa-file-contract"></i>
                    <div>
                        <div class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($sch['name'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($sch['name']); ?></div>
                        <?php if (!empty($sch['note'])): ?><div class="news-meta i18n-dynamic" data-mr="<?php echo htmlspecialchars($sch['note'], ENT_QUOTES); ?>" style="margin-top:2px;"><?php echo htmlspecialchars($sch['note']); ?></div><?php endif; ?>
                        <?php if (!empty($sch['last_date'])): ?><div class="news-meta">Last date: <?php echo date('d M Y', strtotime($sch['last_date'])); ?></div><?php endif; ?>
                        <?php if (!empty($sch['official_link'])): ?><a href="<?php echo htmlspecialchars($sch['official_link']); ?>" target="_blank" rel="noopener" style="font-size:12px;color:var(--primary-green);">View Details →</a><?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Upcoming Events -->
        <div class="glass-card">
            <h2 class="section-title" id="event-title">Upcoming Agricultural Events</h2>
            <?php if ($eventsAreFallback): ?>
                <p id="event-fallback-note" style="font-size:12px;color:#999;margin:-8px 0 14px;">सध्या admin ने कार्यक्रम जोडलेले नाहीत — त्यामुळे खालील खऱ्या, पडताळणी केलेल्या कृषी कार्यक्रमांची माहिती दाखवली आहे.</p>
            <?php endif; ?>
            <?php if (empty($events)): ?>
                <p class="empty-note" id="event-empty">सध्या कोणताही कार्यक्रम नियोजित नाही.</p>
            <?php else: foreach ($events as $ev): ?>
            <div style="background: rgba(47,143,78,0.08); padding: 12px; border-radius: 8px; border-left: 3px solid var(--primary-green); margin-bottom:10px;">
                <strong class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($ev['title'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($ev['title']); ?></strong><br>
                <small style="color:#666;">
                    <i class="fa-regular fa-calendar"></i>
                    <?php if (!empty($ev['display_date'])): ?>
                        <span class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($ev['display_date'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($ev['display_date']); ?></span>
                    <?php else: ?>
                        <?php echo date('d M', strtotime($ev['event_start'])); ?><?php echo $ev['event_end'] ? '-' . date('d M Y', strtotime($ev['event_end'])) : ' ' . date('Y', strtotime($ev['event_start'])); ?>
                    <?php endif; ?>
                    <?php if (!empty($ev['location'])): ?> (<span class="i18n-dynamic" data-mr="<?php echo htmlspecialchars($ev['location'], ENT_QUOTES); ?>"><?php echo htmlspecialchars($ev['location']); ?></span>)<?php endif; ?>
                </small>
                <?php if (!empty($ev['link'])): ?><div><a href="<?php echo htmlspecialchars($ev['link']); ?>" target="_blank" rel="noopener" style="font-size:12px;color:var(--primary-green);">View Details →</a></div><?php endif; ?>
            </div>
            <?php endforeach; endif; ?>
            <?php if (!empty($livePibUpdates)): ?>
                <div style="margin-top:14px;padding-top:12px;border-top:1px dashed var(--card-border);">
                    <div id="live-pib-heading" style="font-size:11.5px;font-weight:700;color:var(--marigold-deep);text-transform:uppercase;letter-spacing:0.4px;margin-bottom:8px;">🔴 Live — PIB कडून ताज्या कृषी घोषणा</div>
                    <?php foreach ($livePibUpdates as $pu): ?>
                        <div style="margin-bottom:8px;font-size:13px;">
                            <a href="<?php echo htmlspecialchars($pu['link']); ?>" target="_blank" rel="noopener" style="color:var(--forest);text-decoration:none;"><?php echo htmlspecialchars($pu['title']); ?></a>
                            <?php if (!empty($pu['pubDate'])): ?><div class="news-meta"><?php echo htmlspecialchars(date('d M Y', strtotime($pu['pubDate']))); ?> · PIB, Govt of India</div><?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
</div>

<?php
include __DIR__ . '/../includes/footer.php';
?>

<!-- JavaScript Core Engine -->
<script>
const CSRF_TOKEN = "<?php echo $csrfToken; ?>";
const IS_LOGGED_IN = <?php echo $isLoggedIn ? 'true' : 'false'; ?>;
const BASE_PATH = '<?php echo $base_path; ?>';
const FARMER_COUNT = <?php echo (int)$stat_connected_farmers; ?>;
const EXPERT_COUNT = <?php echo (int)$stat_verified_experts; ?>;

const pageT = {
    en: {
        pageTitle: 'Agri Connect | AgriCart',
        s1Tag: 'Farmer Community', s1Title: 'Farmer Community Connection', s1Sub: 'A group of farmers standing in the field, checking updates together on a mobile phone', s1Btn: 'Join the Discussion', s1Btn2: 'View Market Rates',
        s2Tag: 'Expert Guidance', s2Title: 'Farmer & Agriculture Expert', s2Sub: 'A farmer discussing his crop with an agronomist / expert', s2Btn: 'Read Expert Advice', s2Btn2: 'Ask a Question',
        s3Tag: 'Digital Platform', s3Title: 'Digital Discussion Platform', s3Sub: 'Farmers using the Agri Connect community on their smartphones for messages and discussions', s3Btn: 'Browse Discussions', s3Btn2: 'Trending Topics',
        s4Tag: 'Knowledge Sharing', s4Title: 'Knowledge Sharing Meeting', s4Sub: 'Village farmers sitting together in a circle, sharing their farming experiences and solutions', s4Btn: 'Read Success Stories', s4Btn2: 'Latest News',
        s5Tag: 'Farmer to Buyer', s5Title: 'Farmer-to-Buyer Connection', s5Sub: 'A farmer shaking hands with a verified buyer, with crates of fresh produce beside them', s5Btn: 'Visit Marketplace', s5Btn2: 'Check Market Rates',
        askTitle: 'Ask a Question or Share a Problem', askTitleHolder: 'Question title...', askHolder: 'Write your question here...',
        opts: ['Questions', 'Crop Management', 'Pest Control', 'Market Rates', 'Govt Schemes'],
        cropPlaceholder: 'Select Crop (Optional)', districtPlaceholder: 'Select District (Optional)',
        imgUploadLbl: 'Add Photo (Optional)', imgSizeNote: 'Max file size: 5MB (JPG, PNG, WEBP)', imgRemoveLbl: 'Remove',
        postBtnTxt: 'Post Question', postBtnLoadingTxt: 'Posting...',
        searchHolder: 'Search crop, disease or farmer question...',
        trendTitle: 'Discussion Feed', fl: ['All', 'Questions', 'Crop', 'Pest', 'Market', 'Schemes', 'Unanswered', 'Solved'],
        sortOpts: ['Latest', 'Most Liked', 'Most Discussed', 'Unanswered First'],
        lblLike: 'Like', lblReply: 'Comments', lblShare: 'Share', lblSave: 'Save', lblReport: 'Report',
        lblMarkSolved: 'Mark Solved', lblUnmarkSolved: 'Unmark Solved', lblVerifiedExpert: 'Verified Expert', lblSolved: 'Solved', lblAnswers: 'answers',
        repHolder: 'Write a reply...', noResults: 'No discussions match your filters — try a different search or category.',
        emptyFeed: 'No discussions yet — be the first to ask a question!', loadMoreTxt: 'Load More Discussions', loadingTxt: 'Loading...', noMoreTxt: 'No more discussions',
        expertTitle: 'Expert Advice Corner', expertEmpty: 'No expert advice posted yet — check back soon.',
        newsTitle: 'Agricultural News & Mandi Updates', newsEmpty: 'No news posted yet.',
        newsFallbackNote: "No news has been added by the admin yet — showing official government portals below.",
        storyTitle: 'Inspirational Farmer Success Stories', storyEmpty: 'No success stories added yet.',
        storyFallbackNote: "No success stories have been added by the admin yet — showing a couple of real farmer stories already covered in agricultural press.",
        wdTitle: 'Live Weather Forecast', trendingTitle: 'Trending Topics', trendingEmpty: 'No trending discussions yet.',
        alertTitle: 'Notification Center', notifLoginMsg: 'Login to see your notifications.', notifEmpty: 'No new notifications right now.',
        govTitle: 'Government Schemes & Subsidies', govEmpty: 'No schemes added yet.',
        govFallbackNote: "No schemes have been added by the admin yet — showing the main currently-active Central/Maharashtra farmer schemes below.",
        eventTitle: 'Upcoming Agricultural Events', eventEmpty: 'No events scheduled right now.',
        eventFallbackNote: "No events have been added by the admin yet — showing verified real agricultural events below.",
        s1: 'Connected Farmers', s2: 'Discussions', s3: 'Verified Experts', s4: 'Platform Rating',
        shareWhatsapp: 'Share on WhatsApp', shareCopy: 'Copy Link', shareNative: 'Share...', linkCopied: 'Link copied!',
        liveUpdatesHeading: '🔴 Live — Latest agri announcements from PIB',
        loginToLike: 'Please login to like this.', loginToSave: 'Please login to save this.', loginToReport: 'Please login to report this.',
        loginToReply: 'Please login to reply.', loginToPost: 'Please login to post a question.',
        somethingWrong: 'Something went wrong.', reportReasonPrompt: 'Reason for reporting this post (optional):',
        reportSubmitted: 'Your report has been submitted.', replyFailed: 'Reply could not be saved.',
        imageTooLarge: 'Image must be smaller than 5MB.', postFailed: 'Post could not be saved.'
    },
    mr: {
        pageTitle: 'कृषी संवाद | AgriCart',
        s1Tag: 'शेतकरी समुदाय', s1Title: 'शेतकरी समुदाय जोडणी', s1Sub: 'वेगवेगळ्या शेतकऱ्यांचा समूह शेतात उभा राहून मोबाईलवर माहिती पाहताना', s1Btn: 'चर्चेत सहभागी व्हा', s1Btn2: 'बाजारभाव पहा',
        s2Tag: 'तज्ज्ञ मार्गदर्शन', s2Title: 'शेतकरी आणि कृषी तज्ज्ञ', s2Sub: 'शेतकरी कृषी तज्ज्ञासोबत पिकाबद्दल चर्चा करताना', s2Btn: 'तज्ज्ञांचा सल्ला वाचा', s2Btn2: 'प्रश्न विचारा',
        s3Tag: 'डिजिटल व्यासपीठ', s3Title: 'डिजिटल चर्चा व्यासपीठ', s3Sub: 'शेतकरी स्मार्टफोनवर Agri Connect समुदाय, संदेश आणि चर्चा वापरताना', s3Btn: 'चर्चा पहा', s3Btn2: 'चर्चेतील विषय पहा',
        s4Tag: 'ज्ञान आदानप्रदान', s4Title: 'ज्ञान आदानप्रदान बैठक', s4Sub: 'गावातील शेतकरी गोल बसून शेतीचे अनुभव आणि उपाय शेअर करताना', s4Btn: 'यशोगाथा वाचा', s4Btn2: 'ताज्या बातम्या',
        s5Tag: 'शेतकरी ते खरेदीदार', s5Title: 'शेतकरी-खरेदीदार जोडणी', s5Sub: 'शेतकरी आणि प्रमाणित खरेदीदार हस्तांदोलन करताना, बाजूला ताज्या मालाचे crates', s5Btn: 'कृषी स्टोअरला भेट द्या', s5Btn2: 'बाजारभाव पहा',
        askTitle: 'तुमचा प्रश्न किंवा समस्या मांडा', askTitleHolder: 'प्रश्नाचे शीर्षक...', askHolder: 'तुमचा प्रश्न येथे लिहा...',
        opts: ['प्रश्न', 'पीक व्यवस्थापन', 'रोग व कीड नियंत्रण', 'बाजारभाव', 'सरकारी योजना'],
        cropPlaceholder: 'पीक निवडा (ऐच्छिक)', districtPlaceholder: 'जिल्हा निवडा (ऐच्छिक)',
        imgUploadLbl: 'फोटो जोडा (ऐच्छिक)', imgSizeNote: 'कमाल फाईल साईझ: 5MB (JPG, PNG, WEBP)', imgRemoveLbl: 'काढा',
        postBtnTxt: 'प्रश्न पोस्ट करा', postBtnLoadingTxt: 'पोस्ट होत आहे...',
        searchHolder: 'पीक, रोग किंवा शेतकऱ्याचा प्रश्न शोधा...',
        trendTitle: 'चर्चा मंच (Feed)', fl: ['सर्व', 'प्रश्न', 'पीक', 'कीड', 'बाजारभाव', 'योजना', 'अनुत्तरित', 'सोडवलेले'],
        sortOpts: ['नवीनतम', 'सर्वाधिक आवडलेले', 'सर्वाधिक चर्चिलेले', 'आधी अनुत्तरित'],
        lblLike: 'आवडले', lblReply: 'प्रतिक्रिया', lblShare: 'शेअर करा', lblSave: 'जतन करा', lblReport: 'तक्रार करा',
        lblMarkSolved: 'सोडवले म्हणून चिन्हांकित करा', lblUnmarkSolved: 'चिन्ह काढा', lblVerifiedExpert: 'प्रमाणित तज्ज्ञ', lblSolved: 'सोडवले', lblAnswers: 'उत्तरे',
        repHolder: 'तुमचे उत्तर लिहा...', noResults: 'तुमच्या फिल्टरशी जुळणारी कोणतीही चर्चा नाही — वेगळा शोध किंवा श्रेणी वापरून पहा.',
        emptyFeed: 'अजून कोणतीही चर्चा नाही — तुम्हीच पहिला प्रश्न विचारा!', loadMoreTxt: 'आणखी चर्चा दाखवा', loadingTxt: 'लोड होत आहे...', noMoreTxt: 'आणखी चर्चा नाहीत',
        expertTitle: 'कृषी तज्ज्ञांचा सल्ला', expertEmpty: 'अजून कोणताही तज्ज्ञ सल्ला उपलब्ध नाही — लवकरच येईल.',
        newsTitle: 'कृषी बातम्या आणि बाजारभाव अपडेट्स', newsEmpty: 'अजून कोणत्याही बातम्या जोडलेल्या नाहीत.',
        newsFallbackNote: 'सध्या admin ने बातम्या जोडलेल्या नाहीत — त्यामुळे खालील अधिकृत सरकारी पोर्टल्स दाखवले आहेत.',
        storyTitle: 'शेतकऱ्यांच्या यशोगाथा', storyEmpty: 'अजून कोणतीही यशोगाथा जोडलेली नाही.',
        storyFallbackNote: 'सध्या admin ने यशोगाथा जोडलेल्या नाहीत — त्यामुळे प्रसारमाध्यमांत आधीच प्रसिद्ध झालेल्या काही खऱ्या शेतकऱ्यांच्या यशोगाथा दाखवल्या आहेत.',
        wdTitle: 'थेट हवामान अंदाज', trendingTitle: 'चर्चेतील विषय', trendingEmpty: 'अजून कोणतीही चर्चा trending नाही.',
        alertTitle: 'नवीन सूचना केंद्र', notifLoginMsg: 'सूचना पाहण्यासाठी login करा.', notifEmpty: 'सध्या कोणत्याही नवीन सूचना नाहीत.',
        govTitle: 'सरकारी योजना आणि सबसिडी', govEmpty: 'सध्या कोणतीही योजना जोडलेली नाही.',
        govFallbackNote: 'सध्या admin ने योजना जोडलेल्या नाहीत — त्यामुळे खालील सध्या सुरू असलेल्या मुख्य केंद्र/राज्य योजना दाखवल्या आहेत.',
        eventTitle: 'आगामी कृषी कार्यक्रम', eventEmpty: 'सध्या कोणताही कार्यक्रम नियोजित नाही.',
        eventFallbackNote: 'सध्या admin ने कार्यक्रम जोडलेले नाहीत — त्यामुळे खालील खऱ्या, पडताळणी केलेल्या कृषी कार्यक्रमांची माहिती दाखवली आहे.',
        s1: 'जोडले गेलेले शेतकरी', s2: 'एकूण चर्चा', s3: 'प्रमाणित कृषी तज्ज्ञ', s4: 'प्लॅटफॉर्म रेटिंग',
        shareWhatsapp: 'WhatsApp वर शेअर करा', shareCopy: 'लिंक कॉपी करा', shareNative: 'शेअर करा...', linkCopied: 'लिंक कॉपी झाली!',
        liveUpdatesHeading: '🔴 Live — PIB कडून ताज्या कृषी घोषणा',
        loginToLike: 'Like करण्यासाठी आधी login करा.', loginToSave: 'Save करण्यासाठी आधी login करा.', loginToReport: 'Report करण्यासाठी आधी login करा.',
        loginToReply: 'Reply देण्यासाठी आधी login करा.', loginToPost: 'प्रश्न विचारण्यासाठी आधी login करा.',
        somethingWrong: 'काहीतरी चुकलं.', reportReasonPrompt: 'या पोस्टबद्दल तक्रार करण्याचे कारण (ऐच्छिक):',
        reportSubmitted: 'तुमची तक्रार नोंदवली गेली आहे.', replyFailed: 'Reply save झालं नाही.',
        imageTooLarge: 'फोटो 5MB पेक्षा लहान असावा.', postFailed: 'Post save झालं नाही.'
    },
    hi: {
        pageTitle: 'कृषि संवाद | AgriCart',
        s1Tag: 'किसान समुदाय', s1Title: 'किसान समुदाय जुड़ाव', s1Sub: 'विभिन्न किसानों का समूह खेत में खड़े होकर मोबाइल पर जानकारी देखते हुए', s1Btn: 'चर्चा में शामिल हों', s1Btn2: 'बाजार भाव देखें',
        s2Tag: 'विशेषज्ञ मार्गदर्शन', s2Title: 'किसान और कृषि विशेषज्ञ', s2Sub: 'किसान कृषि विशेषज्ञ के साथ फसल के बारे में चर्चा करते हुए', s2Btn: 'विशेषज्ञ सलाह पढ़ें', s2Btn2: 'प्रश्न पूछें',
        s3Tag: 'डिजिटल प्लेटफॉर्म', s3Title: 'डिजिटल चर्चा प्लेटफॉर्म', s3Sub: 'किसान स्मार्टफोन पर Agri Connect समुदाय, संदेश और चर्चा का उपयोग करते हुए', s3Btn: 'चर्चाएं देखें', s3Btn2: 'ट्रेंडिंग विषय देखें',
        s4Tag: 'ज्ञान आदान-प्रदान', s4Title: 'ज्ञान आदान-प्रदान बैठक', s4Sub: 'गांव के किसान गोल घेरे में बैठकर खेती के अनुभव और उपाय साझा करते हुए', s4Btn: 'सफलता की कहानियाँ पढ़ें', s4Btn2: 'ताज़ा समाचार',
        s5Tag: 'किसान से खरीदार', s5Title: 'किसान-खरीदार जुड़ाव', s5Sub: 'किसान और प्रमाणित खरीदार हाथ मिलाते हुए, बगल में ताज़ी उपज की crates', s5Btn: 'मार्केटप्लेस देखें', s5Btn2: 'बाजार भाव देखें',
        askTitle: 'अपना सवाल पूछें या समस्या साझा करें', askTitleHolder: 'प्रश्न का शीर्षक...', askHolder: 'यहाँ अपना प्रश्न लिखें...',
        opts: ['प्रश्न', 'फसल प्रबंधन', 'कीट नियंत्रण', 'बाजार भाव', 'सरकारी योजनाएं'],
        cropPlaceholder: 'फसल चुनें (वैकल्पिक)', districtPlaceholder: 'जिला चुनें (वैकल्पिक)',
        imgUploadLbl: 'फोटो जोड़ें (वैकल्पिक)', imgSizeNote: 'अधिकतम फाइल साइज़: 5MB (JPG, PNG, WEBP)', imgRemoveLbl: 'हटाएं',
        postBtnTxt: 'प्रश्न पोस्ट करें', postBtnLoadingTxt: 'पोस्ट हो रहा है...',
        searchHolder: 'फसल, रोग या किसान का प्रश्न खोजें...',
        trendTitle: 'चर्चा फीड', fl: ['सभी', 'प्रश्न', 'फसल', 'कीट', 'बाजार', 'योजनाएं', 'अनुत्तरित', 'हल किया गया'],
        sortOpts: ['नवीनतम', 'सबसे पसंदीदा', 'सबसे अधिक चर्चित', 'पहले अनुत्तरित'],
        lblLike: 'पसंद करें', lblReply: 'टिप्पणियाँ', lblShare: 'शेयर करें', lblSave: 'सहेजें', lblReport: 'रिपोर्ट करें',
        lblMarkSolved: 'हल किया गया चिह्नित करें', lblUnmarkSolved: 'चिह्न हटाएं', lblVerifiedExpert: 'प्रमाणित विशेषज्ञ', lblSolved: 'हल किया गया', lblAnswers: 'उत्तर',
        repHolder: 'अपना उत्तर लिखें...', noResults: 'आपके फिल्टर से मेल खाने वाली कोई चर्चा नहीं — कोई अलग खोज या श्रेणी आज़माएं।',
        emptyFeed: 'अभी तक कोई चर्चा नहीं — पहला प्रश्न आप ही पूछें!', loadMoreTxt: 'और चर्चाएं देखें', loadingTxt: 'लोड हो रहा है...', noMoreTxt: 'और चर्चाएं नहीं हैं',
        expertTitle: 'कृषि विशेषज्ञ सलाह', expertEmpty: 'अभी तक कोई विशेषज्ञ सलाह पोस्ट नहीं हुई — जल्द ही देखें।',
        newsTitle: 'कृषि समाचार और मंडी अपडेट', newsEmpty: 'अभी तक कोई समाचार पोस्ट नहीं हुआ।',
        newsFallbackNote: 'अभी तक admin ने कोई समाचार नहीं जोड़ा है — इसलिए नीचे आधिकारिक सरकारी पोर्टल दिखाए गए हैं।',
        storyTitle: 'किसानों की प्रेरणादायक सफलता की कहानियाँ', storyEmpty: 'अभी तक कोई सफलता की कहानी नहीं जोड़ी गई।',
        storyFallbackNote: 'अभी तक admin ने कोई सफलता की कहानी नहीं जोड़ी है — इसलिए कृषि प्रेस में पहले से प्रकाशित कुछ वास्तविक किसानों की कहानियाँ दिखाई गई हैं।',
        wdTitle: 'लाइव मौसम पूर्वानुमान', trendingTitle: 'चर्चा में विषय', trendingEmpty: 'अभी तक कोई चर्चा ट्रेंडिंग में नहीं है।',
        alertTitle: 'सूचना केंद्र', notifLoginMsg: 'अपनी सूचनाएं देखने के लिए login करें।', notifEmpty: 'अभी कोई नई सूचना नहीं है।',
        govTitle: 'सरकारी योजनाएं और सब्सिडी', govEmpty: 'अभी तक कोई योजना नहीं जोड़ी गई।',
        govFallbackNote: 'अभी तक admin ने कोई योजना नहीं जोड़ी है — इसलिए नीचे वर्तमान में सक्रिय मुख्य केंद्र/महाराष्ट्र किसान योजनाएं दिखाई गई हैं।',
        eventTitle: 'आगामी कृषि कार्यक्रम', eventEmpty: 'अभी कोई कार्यक्रम निर्धारित नहीं है।',
        eventFallbackNote: 'अभी तक admin ने कोई कार्यक्रम नहीं जोड़ा है — इसलिए नीचे सत्यापित वास्तविक कृषि कार्यक्रमों की जानकारी दिखाई गई है।',
        s1: 'जुड़े हुए किसान', s2: 'कुल चर्चाएं', s3: 'प्रमाणित कृषि विशेषज्ञ', s4: 'प्लेटफॉर्म रेटिंग',
        shareWhatsapp: 'WhatsApp पर शेयर करें', shareCopy: 'लिंक कॉपी करें', shareNative: 'शेयर करें...', linkCopied: 'लिंक कॉपी हो गई!',
        liveUpdatesHeading: '🔴 Live — PIB से ताज़ा कृषि घोषणाएं',
        loginToLike: 'Like करने के लिए पहले login करें।', loginToSave: 'Save करने के लिए पहले login करें।', loginToReport: 'Report करने के लिए पहले login करें।',
        loginToReply: 'Reply देने के लिए पहले login करें।', loginToPost: 'प्रश्न पूछने के लिए पहले login करें।',
        somethingWrong: 'कुछ गलत हो गया।', reportReasonPrompt: 'इस पोस्ट की रिपोर्ट करने का कारण (वैकल्पिक):',
        reportSubmitted: 'आपकी रिपोर्ट दर्ज कर ली गई है।', replyFailed: 'Reply सेव नहीं हुआ।',
        imageTooLarge: 'फोटो 5MB से छोटी होनी चाहिए।', postFailed: 'Post सेव नहीं हुआ।'
    }
};

let CURRENT_LANG = 'en';

function pageLanguageCallback(l) {
    CURRENT_LANG = l;
    const t = pageT[l] || pageT.en;
    document.title = t.pageTitle;
    if (!document.getElementById('s1-title')) return;

    document.getElementById('s1-tag').textContent = t.s1Tag; document.getElementById('s1-title').textContent = t.s1Title;
    document.getElementById('s1-sub').textContent = t.s1Sub.replace('{farmers}', FARMER_COUNT);
    document.getElementById('s1-btn').textContent = t.s1Btn; document.getElementById('s1-btn2').textContent = t.s1Btn2;
    document.getElementById('s2-tag').textContent = t.s2Tag; document.getElementById('s2-title').textContent = t.s2Title; document.getElementById('s2-sub').textContent = t.s2Sub;
    document.getElementById('s2-btn').textContent = t.s2Btn; document.getElementById('s2-btn2').textContent = t.s2Btn2;
    document.getElementById('s3-tag').textContent = t.s3Tag; document.getElementById('s3-title').textContent = t.s3Title;
    document.getElementById('s3-sub').textContent = t.s3Sub.replace('{experts}', EXPERT_COUNT);
    document.getElementById('s3-btn').textContent = t.s3Btn; document.getElementById('s3-btn2').textContent = t.s3Btn2;
    document.getElementById('s4-tag').textContent = t.s4Tag; document.getElementById('s4-title').textContent = t.s4Title; document.getElementById('s4-sub').textContent = t.s4Sub;
    document.getElementById('s4-btn').textContent = t.s4Btn; document.getElementById('s4-btn2').textContent = t.s4Btn2;
    document.getElementById('s5-tag').textContent = t.s5Tag; document.getElementById('s5-title').textContent = t.s5Title; document.getElementById('s5-sub').textContent = t.s5Sub;
    document.getElementById('s5-btn').textContent = t.s5Btn; document.getElementById('s5-btn2').textContent = t.s5Btn2;

    document.getElementById('ask-title').textContent = t.askTitle;
    document.getElementById('ask-title-input').placeholder = t.askTitleHolder;
    document.getElementById('ask-box').placeholder = t.askHolder;
    for (let i=1; i<=5; i++) { const el = document.getElementById('opt-'+i); if (el) el.textContent = t.opts[i-1]; }
    document.getElementById('opt-crop-0').textContent = t.cropPlaceholder;
    document.getElementById('opt-dist-0').textContent = t.districtPlaceholder;
    document.getElementById('img-upload-lbl').textContent = t.imgUploadLbl;
    document.getElementById('img-size-note').textContent = t.imgSizeNote;
    document.getElementById('img-remove-lbl').textContent = t.imgRemoveLbl;
    document.getElementById('post-btn-txt').textContent = t.postBtnTxt;
    document.getElementById('post-btn-loading-txt').textContent = t.postBtnLoadingTxt;
    document.getElementById('search-input').placeholder = t.searchHolder;

    document.getElementById('trend-title').textContent = t.trendTitle;
    for (let i=1; i<=8; i++) { const el = document.getElementById('fl-'+i); if (el) el.textContent = t.fl[i-1]; }
    for (let i=1; i<=4; i++) { const el = document.getElementById('sort-'+i); if (el) el.textContent = t.sortOpts[i-1]; }
    document.getElementById('no-results-msg').textContent = t.noResults;
    const emptyFeed = document.getElementById('empty-feed-msg'); if (emptyFeed) emptyFeed.textContent = t.emptyFeed;
    document.getElementById('load-more-txt').textContent = t.loadMoreTxt;

    document.querySelectorAll('.lbl-like').forEach(e => e.textContent = t.lblLike);
    document.querySelectorAll('.lbl-reply').forEach(e => e.textContent = t.lblReply);
    document.querySelectorAll('.lbl-share').forEach(e => e.textContent = t.lblShare);
    document.querySelectorAll('.lbl-save').forEach(e => e.textContent = t.lblSave);
    document.querySelectorAll('.lbl-report').forEach(e => e.textContent = t.lblReport);
    document.querySelectorAll('.lbl-verified-expert').forEach(e => e.textContent = t.lblVerifiedExpert);
    document.querySelectorAll('.lbl-solved').forEach(e => e.textContent = t.lblSolved);
    document.querySelectorAll('.lbl-answers').forEach(e => e.textContent = t.lblAnswers);
    document.querySelectorAll('.add-reply input').forEach(e => e.placeholder = t.repHolder);
    document.querySelectorAll('.lbl-mark-solved').forEach(e => {
        const isSolved = e.closest('.post-card')?.dataset.solved === '1';
        e.textContent = isSolved ? t.lblUnmarkSolved : t.lblMarkSolved;
    });

    const expertTitleEl = document.getElementById('expert-title'); if (expertTitleEl) expertTitleEl.textContent = t.expertTitle;
    const expEmpty = document.getElementById('expert-empty'); if (expEmpty) expEmpty.textContent = t.expertEmpty;
    const newsTitleEl = document.getElementById('news-title'); if (newsTitleEl) newsTitleEl.textContent = t.newsTitle;
    const newsEmpty = document.getElementById('news-empty'); if (newsEmpty) newsEmpty.textContent = t.newsEmpty;
    const newsFallbackNote = document.getElementById('news-fallback-note'); if (newsFallbackNote) newsFallbackNote.textContent = t.newsFallbackNote;
    const storyTitleEl = document.getElementById('story-title'); if (storyTitleEl) storyTitleEl.textContent = t.storyTitle;
    const storyEmpty = document.getElementById('story-empty'); if (storyEmpty) storyEmpty.textContent = t.storyEmpty;
    const storyFallbackNote = document.getElementById('story-fallback-note'); if (storyFallbackNote) storyFallbackNote.textContent = t.storyFallbackNote;

    document.getElementById('wd-title').textContent = t.wdTitle;
    // wd-loading-txt / wd-err-txt only exist until the first successful weather load —
    // fetchWeatherAjax() overwrites #wd-content's innerHTML and removes them. Must be
    // null-checked or a language switch after weather has loaded throws here and silently
    // kills every translation below (trending, notifications, gov schemes, events, stats...).
    const wdLoadingTxt = document.getElementById('wd-loading-txt');
    if (wdLoadingTxt) wdLoadingTxt.textContent = l==='mr' ? 'स्थान शोधत आहे...' : (l==='hi' ? 'स्थान सर्च किया जा रहा है...' : 'Detecting your location...');
    const wdErrTxt = document.getElementById('wd-err-txt');
    if (wdErrTxt) wdErrTxt.textContent = l==='mr' ? 'स्थान परवानगी नाकारली.' : (l==='hi' ? 'स्थान अनुमति अस्वीकार.' : 'Location access denied. Please allow location.');

    const trendingTitleEl = document.getElementById('trending-title'); if (trendingTitleEl) trendingTitleEl.textContent = t.trendingTitle;
    const trendEmpty = document.getElementById('trending-empty'); if (trendEmpty) trendEmpty.textContent = t.trendingEmpty;
    const alertTitleEl = document.getElementById('alert-title');
    if (alertTitleEl && alertTitleEl.childNodes[0]) alertTitleEl.childNodes[0].textContent = t.alertTitle + ' ';
    const notifLogin = document.getElementById('notif-login-msg'); if (notifLogin) notifLogin.textContent = t.notifLoginMsg;
    const notifEmpty = document.getElementById('notif-empty'); if (notifEmpty) notifEmpty.textContent = t.notifEmpty;
    const govTitleEl = document.getElementById('gov-title'); if (govTitleEl) govTitleEl.textContent = t.govTitle;
    const govEmpty = document.getElementById('gov-empty'); if (govEmpty) govEmpty.textContent = t.govEmpty;
    const govFallbackNote = document.getElementById('gov-fallback-note'); if (govFallbackNote) govFallbackNote.textContent = t.govFallbackNote;
    const eventTitleEl = document.getElementById('event-title'); if (eventTitleEl) eventTitleEl.textContent = t.eventTitle;
    const eventEmpty = document.getElementById('event-empty'); if (eventEmpty) eventEmpty.textContent = t.eventEmpty;
    const eventFallbackNote = document.getElementById('event-fallback-note'); if (eventFallbackNote) eventFallbackNote.textContent = t.eventFallbackNote;
    const livePibHeading = document.getElementById('live-pib-heading'); if (livePibHeading) livePibHeading.textContent = t.liveUpdatesHeading;

    document.getElementById('st1').textContent = t.s1; document.getElementById('st2').textContent = t.s2;
    document.getElementById('st3').textContent = t.s3; document.getElementById('st4').textContent = t.s4;

    window._AGRI_T = t;
    if (_wdLastLat !== null && _wdLastLon !== null) {
        fetchWeatherAjax(_wdLastLat, _wdLastLon, l);
    } else if (typeof loadWeatherGPS === 'function') {
        // Location wasn't ready yet when language was switched — retry instead
        // of leaving the widget untranslated until a full page refresh.
        loadWeatherGPS();
    }
    translateDynamicContent(l);
    translatePostsFeed(l);
}

// ─── Auto-translate real DB content (notifications etc.) that isn't part of the static label dictionary ───
// Uses MyMemory's free translation API (no key required) and caches results per-element so we don't re-call on every toggle.
// ─── Shared MyMemory call with an English pivot fallback ───
// MyMemory's free tier is well-trained on pairs routed through English (mr|en, en|hi) but is
// noticeably less reliable on the direct mr|hi pair — it can return low-quality matches, an
// error string in place of the translation, or fail outright. So: try the direct pair first;
// if that doesn't look like a real translation, pivot mr -> en -> hi (or hi -> en -> mr) instead
// of just giving up and leaving the original text on screen.
function looksLikeFailedTranslation(text, original) {
    if (!text) return true;
    if (/invalid|please select two distinct|no translation found|quota|monthly usage limit/i.test(text)) return true;
    if (text.trim().toLowerCase() === (original || '').trim().toLowerCase()) return true; // untranslated echo
    return false;
}
async function mymemoryTranslate(text, sourceLang, targetLang) {
    const tryPair = async (q, src, tgt) => {
        try {
            const res = await fetch('https://api.mymemory.translated.net/get?q=' + encodeURIComponent(q) + '&langpair=' + src + '|' + tgt);
            if (!res.ok) return null;
            const data = await res.json();
            return (data && data.responseData && data.responseData.translatedText) || null;
        } catch (e) { return null; }
    };
    const direct = await tryPair(text, sourceLang, targetLang);
    if (!looksLikeFailedTranslation(direct, text)) return direct;

    // Pivot through English for the mr<->hi pair specifically (the one that tends to be weak).
    if ((sourceLang === 'mr' && targetLang === 'hi') || (sourceLang === 'hi' && targetLang === 'mr')) {
        const toEn = await tryPair(text, sourceLang, 'en');
        if (!looksLikeFailedTranslation(toEn, text)) {
            const fromEn = await tryPair(toEn, 'en', targetLang);
            if (!looksLikeFailedTranslation(fromEn, toEn)) return fromEn;
        }
    }
    return direct; // best effort — may be null, caller keeps original text in that case
}

async function translateDynamicContent(l) {
    const nodes = document.querySelectorAll('.i18n-dynamic');
    if (!nodes.length) return;

    // NOTE: this content (notifications, schemes/events/news, whether admin-entered or fallback)
    // is NOT always stored in Marathi in the DB — e.g. some notifications (like equipment
    // rejection) get created in English by other parts of the app. So we can't just assume
    // data-mr is Marathi and restore it as-is when l === 'mr'. Instead, auto-detect the actual
    // source language (same approach as translatePostsFeed/detectPostLang below) and translate
    // in whichever direction is needed.
    nodes.forEach(async (el) => {
        // Cache the text exactly as first rendered (the real original language) before touching it.
        if (!el.dataset.origTxt) { el.dataset.origTxt = el.dataset.mr; }
        const original = el.dataset.origTxt;
        if (!original || !original.trim()) return;

        const sourceLang = detectPostLang(original);
        const targetLang = (l === 'hi') ? 'hi' : (l === 'mr' ? 'mr' : 'en');
        if (sourceLang === targetLang) { el.textContent = original; return; }

        const cacheKey = targetLang + 'Cache';
        if (el.dataset[cacheKey]) { el.textContent = el.dataset[cacheKey]; return; }

        const translated = await mymemoryTranslate(original, sourceLang, targetLang);
        if (translated) {
            el.dataset[cacheKey] = translated;
            el.textContent = translated;
        }
        // If translation genuinely failed, the original text (already on screen) stays visible —
        // better than showing nothing.
    });
}

// ─── Auto-translate farmer-submitted post titles/body (Discussion Feed) ───
// These come straight from the DB in whichever language the farmer typed them in, so unlike the
// admin/fallback content above there's no pre-written data-mr to fall back on — source language
// is auto-detected by the translation API instead, and results are cached per element per language.
function detectPostLang(text) {
    // MyMemory needs a real ISO source code, 'auto' isn't accepted — so guess from the script used.
    // Devanagari block (Marathi/Hindi) → 'mr', otherwise assume English.
    return /[\u0900-\u097F]/.test(text) ? 'mr' : 'en';
}

async function translatePostsFeed(l) {
    // Also covers Trending Topics sidebar titles (.trend-title-text) — these reuse post
    // titles/bodies from the same posts table, so they need the same source-language
    // auto-detect + translate treatment, but they render outside #postsFeed (in the sidebar,
    // server-rendered once on page load, no Load More), so the #postsFeed-only selector was
    // missing them entirely.
    const nodes = document.querySelectorAll('#postsFeed .post-title, #postsFeed .post-body-text, .trend-title-text');
    if (!nodes.length) return;

    nodes.forEach(async (el) => {
        // Cache the text exactly as first rendered (the farmer's original language) before touching it.
        if (!el.dataset.origTxt) { el.dataset.origTxt = el.textContent; }
        const original = el.dataset.origTxt;
        if (!original || !original.trim()) return;

        const sourceLang = detectPostLang(original);
        const targetLang = (l === 'hi') ? 'hi' : (l === 'mr' ? 'mr' : 'en');
        if (sourceLang === targetLang) { el.textContent = original; return; }

        const cacheKey = targetLang + 'Cache';
        if (el.dataset[cacheKey]) { el.textContent = el.dataset[cacheKey]; return; }

        const translated = await mymemoryTranslate(original, sourceLang, targetLang);
        if (translated) {
            el.dataset[cacheKey] = translated;
            el.textContent = translated;
        }
        // If translation genuinely failed, leave the farmer's original text rather than showing an error.
    });
}

// ─── REAL-TIME WEATHER (user's own GPS location, same endpoint as homepage) ───
let _wdLastLat = null, _wdLastLon = null;

// The site persists the chosen language in the "agri_lang" COOKIE (fetch_weather.php
// itself falls back to $_COOKIE['agri_lang']), NOT in localStorage. Read it the same
// way so the weather widget's language always matches the rest of the page.
function getAgriLangCookie() {
    const m = document.cookie.match(/(?:^|;\s*)agri_lang=([^;]+)/);
    return m ? decodeURIComponent(m[1]) : null;
}

async function fetchWeatherAjax(lat, lon, lang) {
    const content = document.getElementById('wd-content');
    const loadingEl = document.getElementById('wd-loading');
    const errorEl = document.getElementById('wd-error');
    if (loadingEl) loadingEl.style.display = 'block';
    if (errorEl) errorEl.style.display = 'none';

    try {
        const resp = await fetch(BASE_PATH + '/pages/fetch_weather.php?wlat=' + lat + '&wlon=' + lon + '&lang=' + lang, { cache: 'no-store' });
        const html = await resp.text();
        content.innerHTML = html;
        _wdLastLat = lat; _wdLastLon = lon;
    } catch (e) {
        const l2 = document.getElementById('wd-loading');
        const e2 = document.getElementById('wd-error');
        if (l2) l2.style.display = 'none';
        if (e2) e2.style.display = 'block';
    }
}

function loadWeatherGPS() {
    const loadingEl = document.getElementById('wd-loading');
    const errorEl = document.getElementById('wd-error');
    if (loadingEl) loadingEl.style.display = 'block';
    if (errorEl) errorEl.style.display = 'none';
    navigator.geolocation.getCurrentPosition(
        p => { fetchWeatherAjax(p.coords.latitude, p.coords.longitude, getAgriLangCookie() || CURRENT_LANG || 'en'); },
        () => {
            const l2 = document.getElementById('wd-loading');
            const e2 = document.getElementById('wd-error');
            if (l2) l2.style.display = 'none';
            if (e2) e2.style.display = 'block';
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
    );
}

// ─── Interaction Logic (Like, Save, Report, Solved, Comments, Share — all CSRF-protected) ───
function postJSON(url, params) {
    params = Object.assign({}, params, { csrf_token: CSRF_TOKEN });
    return fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/x-www-form-urlencoded' }, body: new URLSearchParams(params) }).then(r => r.json());
}

function toggleLike(btn, postId) {
    const t = window._AGRI_T || pageT.en;
    if (!IS_LOGGED_IN) { alert(t.loginToLike); window.location.href = BASE_PATH + '/pages/login.php'; return; }
    btn.disabled = true;
    postJSON(BASE_PATH + '/pages/toggle_like.php', { post_id: postId })
    .then(data => {
        btn.disabled = false;
        if (!data.success) { alert(data.error || t.somethingWrong); return; }
        const icon = btn.querySelector('i');
        btn.querySelector('.count').innerText = data.likes_count;
        if (data.liked) { btn.classList.add('liked'); icon.className = 'fa-solid fa-heart'; }
        else { btn.classList.remove('liked'); icon.className = 'fa-regular fa-heart'; }
    })
    .catch(() => { btn.disabled = false; alert('Network error, please try again.'); });
}

function toggleSave(btn, postId) {
    const t = window._AGRI_T || pageT.en;
    if (!IS_LOGGED_IN) { alert(t.loginToSave); window.location.href = BASE_PATH + '/pages/login.php'; return; }
    btn.disabled = true;
    postJSON(BASE_PATH + '/pages/toggle_save.php', { post_id: postId })
    .then(data => {
        btn.disabled = false;
        if (!data.success) { alert(data.error || t.somethingWrong); return; }
        const icon = btn.querySelector('i');
        if (data.saved) { btn.classList.add('saved'); icon.className = 'fa-solid fa-bookmark'; }
        else { btn.classList.remove('saved'); icon.className = 'fa-regular fa-bookmark'; }
    })
    .catch(() => { btn.disabled = false; alert('Network error, please try again.'); });
}

function reportPost(btn, postId) {
    const t = window._AGRI_T || pageT.en;
    if (!IS_LOGGED_IN) { alert(t.loginToReport); window.location.href = BASE_PATH + '/pages/login.php'; return; }
    if (btn.classList.contains('reported')) return;
    const reason = prompt(t.reportReasonPrompt) || '';
    btn.disabled = true;
    postJSON(BASE_PATH + '/pages/report_post.php', { post_id: postId, reason })
    .then(data => {
        btn.disabled = false;
        if (!data.success) { alert(data.error || t.somethingWrong); return; }
        btn.classList.add('reported');
        alert(t.reportSubmitted);
    })
    .catch(() => { btn.disabled = false; alert('Network error, please try again.'); });
}

function markSolved(btn, postId) {
    const t = window._AGRI_T || pageT.en;
    btn.disabled = true;
    postJSON(BASE_PATH + '/pages/mark_solved.php', { post_id: postId })
    .then(data => {
        btn.disabled = false;
        if (!data.success) { alert(data.error || t.somethingWrong); return; }
        location.reload();
    })
    .catch(() => { btn.disabled = false; alert('Network error, please try again.'); });
}

function toggleComments(id) {
    const sec = document.getElementById(id); sec.style.display = (sec.style.display === 'block') ? 'none' : 'block';
}

// Safe DOM construction — server sends RAW text, we never innerHTML it.
function buildCommentNode(name, body) {
    const item = document.createElement('div'); item.className = 'comment-item';
    const img = document.createElement('img');
    img.src = 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name) + '&background=random';
    const contentDiv = document.createElement('div'); contentDiv.className = 'comment-content';
    const h5 = document.createElement('h5'); h5.textContent = name;
    const p = document.createElement('p');
    body.split('\n').forEach((line, i) => { if (i > 0) p.appendChild(document.createElement('br')); p.appendChild(document.createTextNode(line)); });
    contentDiv.appendChild(h5); contentDiv.appendChild(p);
    item.appendChild(img); item.appendChild(contentDiv);
    return item;
}

function postReply(btn, postId) {
    const t = window._AGRI_T || pageT.en;
    if (!IS_LOGGED_IN) { alert(t.loginToReply); window.location.href = BASE_PATH + '/pages/login.php'; return; }
    const input = btn.previousElementSibling;
    const text = input.value.trim();
    if (text === '') return;
    btn.disabled = true;

    postJSON(BASE_PATH + '/pages/post_comment.php', { post_id: postId, body: text })
    .then(data => {
        btn.disabled = false;
        if (!data.success) { alert(data.error || t.replyFailed); return; }
        const node = buildCommentNode(data.name, data.body);
        btn.parentElement.insertAdjacentElement('beforebegin', node);
        input.value = '';
        const postCard = btn.closest('.post-card');
        const replyCountEl = postCard.querySelector('.lbl-reply').previousElementSibling;
        replyCountEl.innerText = parseInt(replyCountEl.innerText) + 1;
        postCard.dataset.commentsCount = parseInt(postCard.dataset.commentsCount || '0') + 1;
    })
    .catch(() => { btn.disabled = false; alert('Network error, please try again.'); });
}

// ─── Share (WhatsApp / Copy Link / Native Share) ───
function sharePost(btn, postId) {
    document.querySelectorAll('.share-menu').forEach(m => m.remove());
    const t = window._AGRI_T || pageT.en;
    const url = window.location.origin + BASE_PATH + '/pages/agri-connect.php#post-' + postId;

    if (navigator.share) {
        navigator.share({ title: 'AgriCart', url }).catch(() => {});
        return;
    }
    const menu = document.createElement('div'); menu.className = 'share-menu open';
    const waBtn = document.createElement('button');
    waBtn.innerHTML = '<i class="fa-brands fa-whatsapp"></i> ' + t.shareWhatsapp;
    waBtn.onclick = () => { window.open('https://wa.me/?text=' + encodeURIComponent(url), '_blank'); menu.remove(); };
    const copyBtn = document.createElement('button');
    copyBtn.innerHTML = '<i class="fa-solid fa-link"></i> ' + t.shareCopy;
    copyBtn.onclick = () => { navigator.clipboard.writeText(url).then(() => alert(t.linkCopied)); menu.remove(); };
    menu.appendChild(waBtn); menu.appendChild(copyBtn);
    btn.parentElement.appendChild(menu);
    setTimeout(() => {
        document.addEventListener('click', function close(e) { if (!menu.contains(e.target) && e.target !== btn) { menu.remove(); document.removeEventListener('click', close); } });
    }, 10);
}

// ─── Ask Question: title + description + category + crop + district + optional image ───
document.getElementById('ask-box').addEventListener('input', function() {
    const len = this.value.length;
    const counter = document.getElementById('char-counter');
    counter.textContent = len + ' / 2000';
    counter.classList.toggle('limit-near', len > 1800);
});
document.getElementById('q-image').addEventListener('change', function() {
    const name = this.files[0] ? this.files[0].name : '';
    document.getElementById('img-preview-name').textContent = name;
    document.getElementById('img-remove-btn').style.display = name ? 'inline-flex' : 'none';
});
function clearImageSelection() {
    document.getElementById('q-image').value = '';
    document.getElementById('img-preview-name').textContent = '';
    document.getElementById('img-remove-btn').style.display = 'none';
}

// ─── Keep category/crop/district dropdowns opening downward ───
// Native <select> picks its own direction based on space below at click time; nudge the page
// down first (only when needed) so there's always enough room and it never flips upward.
document.querySelectorAll('#q-category, #q-crop, #q-district').forEach(sel => {
    sel.addEventListener('mousedown', function() {
        const rect = this.getBoundingClientRect();
        const spaceBelow = window.innerHeight - rect.bottom;
        if (spaceBelow < 320) {
            window.scrollBy({ top: 320 - spaceBelow, behavior: 'instant' });
        }
    });
});

function postQuestion() {
    const t = window._AGRI_T || pageT.en;
    if (!IS_LOGGED_IN) { alert(t.loginToPost); window.location.href = BASE_PATH + '/pages/login.php'; return; }

    const titleInput = document.getElementById('ask-title-input');
    const box = document.getElementById('ask-box');
    const title = titleInput.value.trim();
    const text = box.value.trim();
    if (title === '') { titleInput.focus(); return; }
    if (text === '') { box.focus(); return; }

    const category = document.getElementById('q-category').value;
    const crop = document.getElementById('q-crop').value;
    const district = document.getElementById('q-district').value;
    const imageFile = document.getElementById('q-image').files[0];
    if (imageFile && imageFile.size > 5 * 1024 * 1024) { alert(t.imageTooLarge); return; }

    const btn = document.getElementById('post-btn');
    btn.disabled = true;
    btn.classList.add('loading');

    const formData = new FormData();
    formData.append('title', title); formData.append('body', text);
    formData.append('category', category); formData.append('crop', crop); formData.append('district', district);
    formData.append('csrf_token', CSRF_TOKEN);
    if (imageFile) formData.append('image', imageFile);

    fetch(BASE_PATH + '/pages/create_post.php', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
        btn.disabled = false; btn.classList.remove('loading');
        if (data.success) { location.reload(); }
        else { alert(data.error || t.postFailed); }
    })
    .catch(() => { btn.disabled = false; btn.classList.remove('loading'); alert('Network error, please try again.'); });
}

// ─── Discussion Feed: server-side filter + sort + search + Load More (Item: full-DB search/pagination) ───
let _feedOffset = 0;
const FEED_PAGE_SIZE = <?php echo (int)$FEED_PAGE_SIZE; ?>;
let _feedDebounce = null;

function currentFeedParams() {
    return {
        filter: document.querySelector('.filter-badge.active')?.dataset.filter || 'all',
        search: document.getElementById('search-input').value.trim(),
        sort: document.getElementById('sort-select').value,
    };
}

function onFeedControlsChanged() {
    clearTimeout(_feedDebounce);
    _feedDebounce = setTimeout(reloadFeedFromStart, 300);
}

function reloadFeedFromStart() {
    _feedOffset = 0;
    const p = currentFeedParams();
    document.getElementById('feed-loading').style.display = 'block';
    document.getElementById('no-results-msg').style.display = 'none';

    fetch(BASE_PATH + '/pages/load_posts.php?limit=' + FEED_PAGE_SIZE + '&offset=0&filter=' + encodeURIComponent(p.filter) + '&search=' + encodeURIComponent(p.search) + '&sort=' + encodeURIComponent(p.sort))
    .then(r => r.json())
    .then(data => {
        document.getElementById('feed-loading').style.display = 'none';
        document.getElementById('postsFeed').innerHTML = data.html;
        _feedOffset = data.count;
        document.getElementById('load-more-wrap').style.display = data.has_more ? 'block' : 'none';
        if (data.count === 0) { document.getElementById('no-results-msg').style.display = 'block'; }
        if (window._AGRI_T) { /* re-apply mark-solved labels for freshly injected cards */
            document.querySelectorAll('.lbl-mark-solved').forEach(e => {
                const isSolved = e.closest('.post-card')?.dataset.solved === '1';
                e.textContent = isSolved ? window._AGRI_T.lblUnmarkSolved : window._AGRI_T.lblMarkSolved;
            });
        }
        translatePostsFeed(CURRENT_LANG);
    })
    .catch(() => { document.getElementById('feed-loading').style.display = 'none'; });
}

function loadMoreDiscussions() {
    const p = currentFeedParams();
    const btn = document.getElementById('load-more-btn');
    btn.disabled = true;
    fetch(BASE_PATH + '/pages/load_posts.php?limit=' + FEED_PAGE_SIZE + '&offset=' + _feedOffset + '&filter=' + encodeURIComponent(p.filter) + '&search=' + encodeURIComponent(p.search) + '&sort=' + encodeURIComponent(p.sort))
    .then(r => r.json())
    .then(data => {
        btn.disabled = false;
        document.getElementById('postsFeed').insertAdjacentHTML('beforeend', data.html);
        _feedOffset += data.count;
        document.getElementById('load-more-wrap').style.display = data.has_more ? 'block' : 'none';
        translatePostsFeed(CURRENT_LANG);
    })
    .catch(() => { btn.disabled = false; });
}

// Filter Badges
document.addEventListener('DOMContentLoaded', () => {
    loadWeatherGPS();

    const counters = document.querySelectorAll('.counter');
    counters.forEach(counter => {
        const updateCount = () => {
            const target = +counter.getAttribute('data-target'); const count = +counter.innerText; const inc = target / 200;
            if (count < target) { counter.innerText = Math.ceil(count + inc); setTimeout(updateCount, 15); }
            else { counter.innerText = target + "+"; }
        };
        updateCount();
    });

    document.querySelectorAll('.filter-badge').forEach(badge => {
        badge.addEventListener('click', function() {
            document.querySelectorAll('.filter-badge').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            reloadFeedFromStart();
        });
    });

    _feedOffset = <?php echo count($posts); ?>;
});
</script>

<?php include_once __DIR__ . '/krishimitra_widget.php'; ?>
