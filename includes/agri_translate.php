<?php
// =====================================================================
// includes/agri_translate.php
//
// Product-name translation for AgriCart, in two layers:
//
//   1. PRIMARY: Google Cloud Translate API (accurate, handles any word,
//      not just produce vocabulary). The API key lives in
//      includes/agri_translate_config.php — never in JS, never sent to
//      the browser, never logged.
//   2. FALLBACK: a small offline dictionary (no network, no key). Used
//      automatically whenever the API key is missing, the request times
//      out, the quota is exhausted, or any other error occurs.
//
// Every caller in this file gets a translation back no matter what —
// worst case, the original text is returned unchanged. A translation
// problem must never block a product listing from being saved.
// =====================================================================

if (file_exists(__DIR__ . '/agri_translate_config.php')) {
    include_once __DIR__ . '/agri_translate_config.php';
}
if (!defined('AGRI_GOOGLE_TRANSLATE_API_KEY')) {
    define('AGRI_GOOGLE_TRANSLATE_API_KEY', '');
}

function agri_google_translate_available() {
    return AGRI_GOOGLE_TRANSLATE_API_KEY !== '' && function_exists('curl_init');
}

/**
 * Calls Google Cloud Translate v2 for a single string. Returns the
 * translated string, or null on ANY failure (network, quota, bad
 * response, timeout) so the caller can fall back to the dictionary.
 * $sourceLang may be null to let Google auto-detect.
 */
function agri_google_translate_text($text, $targetLang, $sourceLang = null) {
    if (!agri_google_translate_available() || trim($text) === '') return null;
    try {
        $params = [
            'key'    => AGRI_GOOGLE_TRANSLATE_API_KEY,
            'q'      => $text,
            'target' => $targetLang,
            'format' => 'text',
        ];
        if ($sourceLang) { $params['source'] = $sourceLang; }

        $ch = curl_init('https://translation.googleapis.com/language/translate/v2');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($params),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) return null;
        $data = json_decode($raw, true);
        $translated = $data['data']['translations'][0]['translatedText'] ?? null;
        if (!$translated) return null;
        return html_entity_decode($translated, ENT_QUOTES, 'UTF-8');
    } catch (\Throwable $e) {
        return null; // network/quota/anything — caller falls back to dictionary
    }
}

/**
 * Google's language-detect endpoint, restricted to the three languages
 * AgriCart supports. Returns 'en'|'mr'|'hi' or null on failure (caller
 * falls back to the offline heuristic in agri_detect_language()).
 */
function agri_google_detect_language($text) {
    if (!agri_google_translate_available() || trim($text) === '') return null;
    try {
        $ch = curl_init('https://translation.googleapis.com/language/translate/v2/detect');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query(['key' => AGRI_GOOGLE_TRANSLATE_API_KEY, 'q' => $text]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $raw = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($raw === false || $httpCode !== 200) return null;
        $data = json_decode($raw, true);
        $lang = $data['data']['detections'][0][0]['language'] ?? null;
        return in_array($lang, ['en', 'mr', 'hi'], true) ? $lang : null;
    } catch (\Throwable $e) {
        return null;
    }
}


// word => [en, mr, hi]   (lowercase English key)
function agri_translation_dictionary() {
    static $dict = null;
    if ($dict !== null) return $dict;
    $dict = [
        'fresh'        => ['fresh', 'ताजे', 'ताज़ा'],
        'organic'      => ['organic', 'सेंद्रिय', 'जैविक'],
        'tomato'       => ['tomato', 'टोमॅटो', 'टमाटर'],
        'tomatoes'     => ['tomatoes', 'टोमॅटो', 'टमाटर'],
        'onion'        => ['onion', 'कांदा', 'प्याज़'],
        'onions'       => ['onions', 'कांदा', 'प्याज़'],
        'potato'       => ['potato', 'बटाटा', 'आलू'],
        'potatoes'     => ['potatoes', 'बटाटे', 'आलू'],
        'brinjal'      => ['brinjal', 'वांगे', 'बैंगन'],
        'okra'         => ['okra', 'भेंडी', 'भिंडी'],
        'ladyfinger'   => ['ladyfinger', 'भेंडी', 'भिंडी'],
        'cauliflower'  => ['cauliflower', 'फ्लॉवर', 'फूलगोभी'],
        'cabbage'      => ['cabbage', 'कोबी', 'पत्ता गोभी'],
        'carrot'       => ['carrot', 'गाजर', 'गाजर'],
        'carrots'      => ['carrots', 'गाजर', 'गाजर'],
        'cucumber'     => ['cucumber', 'काकडी', 'खीरा'],
        'spinach'      => ['spinach', 'पालक', 'पालक'],
        'garlic'       => ['garlic', 'लसूण', 'लहसुन'],
        'ginger'       => ['ginger', 'आले', 'अदरक'],
        'chili'        => ['chili', 'मिरची', 'मिर्च'],
        'chilli'       => ['chilli', 'मिरची', 'मिर्च'],
        'green chilli' => ['green chilli', 'हिरवी मिरची', 'हरी मिर्च'],
        'mango'        => ['mango', 'आंबा', 'आम'],
        'mangoes'      => ['mangoes', 'आंबे', 'आम'],
        'banana'       => ['banana', 'केळे', 'केला'],
        'bananas'      => ['bananas', 'केळी', 'केले'],
        'grapes'       => ['grapes', 'द्राक्षे', 'अंगूर'],
        'pomegranate'  => ['pomegranate', 'डाळिंब', 'अनार'],
        'orange'       => ['orange', 'संत्री', 'संतरा'],
        'apple'        => ['apple', 'सफरचंद', 'सेब'],
        'wheat'        => ['wheat', 'गहू', 'गेहूं'],
        'rice'         => ['rice', 'तांदूळ', 'चावल'],
        'jowar'        => ['jowar', 'ज्वारी', 'ज्वार'],
        'bajra'        => ['bajra', 'बाजरी', 'बाजरा'],
        'maize'        => ['maize', 'मका', 'मक्का'],
        'corn'         => ['corn', 'मका', 'मक्का'],
        'sugarcane'    => ['sugarcane', 'ऊस', 'गन्ना'],
        'jaggery'      => ['jaggery', 'गूळ', 'गुड़'],
        'turmeric'     => ['turmeric', 'हळद', 'हल्दी'],
        'cotton'       => ['cotton', 'कापूस', 'कपास'],
        'soybean'      => ['soybean', 'सोयाबीन', 'सोयाबीन'],
        'groundnut'    => ['groundnut', 'भुईमूग', 'मूंगफली'],
        'seed'         => ['seed', 'बियाणे', 'बीज'],
        'seeds'        => ['seeds', 'बियाणे', 'बीज'],
        'fertilizer'   => ['fertilizer', 'खत', 'खाद'],
        'pesticide'    => ['pesticide', 'कीटकनाशक', 'कीटनाशक'],
        'pesticides'   => ['pesticides', 'कीटकनाशके', 'कीटनाशक'],
        'manure'       => ['manure', 'शेणखत', 'खाद'],
        'compost'      => ['compost', 'कंपोस्ट खत', 'कम्पोस्ट खाद'],
        'milk'         => ['milk', 'दूध', 'दूध'],
        'honey'        => ['honey', 'मध', 'शहद'],
        'egg'          => ['egg', 'अंडे', 'अंडा'],
        'eggs'         => ['eggs', 'अंडी', 'अंडे'],
        'ghee'         => ['ghee', 'तूप', 'घी'],
        'flour'        => ['flour', 'पीठ', 'आटा'],
        'grain'        => ['grain', 'धान्य', 'अनाज'],
        'vegetable'    => ['vegetable', 'भाजी', 'सब्ज़ी'],
        'vegetables'   => ['vegetables', 'भाज्या', 'सब्ज़ियाँ'],
        'fruit'        => ['fruit', 'फळ', 'फल'],
        'fruits'       => ['fruits', 'फळे', 'फल'],

        // --- Equipment / farm-machinery vocabulary (list_equipment.php) ---
        // Equipment-type words kept identical to the labels already shown
        // in the "Equipment Category" dropdown so the preview and the
        // dropdown never disagree.
        'tractor'      => ['tractor', 'ट्रॅक्टर', 'ट्रैक्टर'],
        'power'        => ['power', 'पॉवर', 'पावर'],
        'tiller'       => ['tiller', 'टिलर', 'टिलर'],
        'power tiller' => ['power tiller', 'पॉवर टिलर', 'पावर टिलर'],
        'rotavator'    => ['rotavator', 'रोटावेटर', 'रोटावेटर'],
        'cultivator'   => ['cultivator', 'कल्टिव्हेटर', 'कल्टीवेटर'],
        'harvester'    => ['harvester', 'हार्वेस्टर', 'हार्वेस्टर'],
        'seed drill'   => ['seed drill', 'सीड ड्रिल', 'सीड ड्रिल'],
        'sprayer'      => ['sprayer', 'स्प्रेअर', 'स्प्रेयर'],
        'drone'        => ['drone', 'ड्रोन', 'ड्रोन'],
        'thresher'     => ['thresher', 'थ्रेशर', 'थ्रेशर'],
        'plough'       => ['plough', 'नांगर', 'हल'],
        'plow'         => ['plow', 'नांगर', 'हल'],
        'harrow'       => ['harrow', 'हॅरो', 'हैरो'],
        'trailer'      => ['trailer', 'ट्रेलर', 'ट्रेलर'],
        'baler'        => ['baler', 'बेलर', 'बेलर'],
        'mower'        => ['mower', 'मोवर', 'मोवर'],
        'weeder'       => ['weeder', 'विडर', 'विडर'],
        'leveler'      => ['leveler', 'लेव्हलर', 'लेवलर'],
        'levelers'     => ['levelers', 'लेव्हलर', 'लेवलर'],
        'pump'         => ['pump', 'पंप', 'पंप'],
        'generator'    => ['generator', 'जनरेटर', 'जनरेटर'],
        'engine'       => ['engine', 'इंजिन', 'इंजन'],
        'diesel'       => ['diesel', 'डिझेल', 'डीजल'],
        'petrol'       => ['petrol', 'पेट्रोल', 'पेट्रोल'],
        'machine'      => ['machine', 'मशीन', 'मशीन'],
        'mini'         => ['mini', 'मिनी', 'मिनी'],
        'new'          => ['new', 'नवीन', 'नया'],
        'old'          => ['old', 'जुना', 'पुराना'],
        'model'        => ['model', 'मॉडेल', 'मॉडल'],
        'wheel'        => ['wheel', 'चाक', 'पहिया'],
        'rotary'       => ['rotary', 'रोटरी', 'रोटरी'],
        'disc'         => ['disc', 'डिस्क', 'डिस्क'],
        'cutter'       => ['cutter', 'कटर', 'कटर'],
        'other'        => ['other', 'इतर', 'अन्य'],

        // Common equipment brand names sold/rented in India. Brand names
        // are proper nouns, not "translated" in the linguistic sense —
        // these are their standard Devanagari transliterations, so the
        // name still reads naturally in Marathi/Hindi instead of staying
        // in the Latin script.
        'mahindra'          => ['Mahindra', 'महिंद्रा', 'महिंद्रा'],
        'swaraj'            => ['Swaraj', 'स्वराज', 'स्वराज'],
        'sonalika'          => ['Sonalika', 'सोनालिका', 'सोनालिका'],
        'eicher'            => ['Eicher', 'आयशर', 'आयशर'],
        'kubota'            => ['Kubota', 'कुबोटा', 'कुबोटा'],
        'force'             => ['Force', 'फोर्स', 'फोर्स'],
        'farmtrac'          => ['Farmtrac', 'फार्मट्रॅक', 'फार्मट्रैक'],
        'powertrac'         => ['Powertrac', 'पॉवरट्रॅक', 'पावरट्रैक'],
        'escort'            => ['Escort', 'एस्कॉर्ट', 'एस्कॉर्ट'],
        'escorts'           => ['Escorts', 'एस्कॉर्ट्स', 'एस्कॉर्ट्स'],
        'captain'           => ['Captain', 'कॅप्टन', 'कैप्टन'],
        'preet'             => ['Preet', 'प्रीत', 'प्रीत'],
        'standard'          => ['Standard', 'स्टँडर्ड', 'स्टैंडर्ड'],
        'indofarm'          => ['Indo Farm', 'इंडो फार्म', 'इंडो फार्म'],
        'ace'               => ['ACE', 'एसीई', 'एसीई'],
        'john'              => ['John', 'जॉन', 'जॉन'],
        'deere'             => ['Deere', 'डियर', 'डियर'],
        'holland'           => ['Holland', 'हॉलंड', 'हॉलैंड'],
        'massey'            => ['Massey', 'मॅसी', 'मैसी'],
        'ferguson'          => ['Ferguson', 'फर्ग्युसन', 'फर्गुसन'],
    ];
    return $dict;
}

// Reverse-lookup dictionaries (mr word -> en/hi, hi word -> en/mr),
// built once from the same source table above.
function agri_translation_reverse($fromIndex) {
    static $cache = [];
    if (isset($cache[$fromIndex])) return $cache[$fromIndex];
    $map = [];
    foreach (agri_translation_dictionary() as $row) {
        $key = $row[$fromIndex];
        if ($key !== '') { $map[$key] = $row; }
    }
    $cache[$fromIndex] = $map;
    return $map;
}

/**
 * Very lightweight language detector for the three supported languages.
 * Devanagari script is shared by Marathi and Hindi, so script alone
 * cannot reliably tell them apart — when Devanagari is found we try to
 * match known Marathi-only / Hindi-only words from the dictionary, and
 * fall back to Hindi if neither matches (documented limitation — the
 * "Input Language" dropdown lets the seller disambiguate explicitly).
 */
function agri_detect_language($text) {
    $text = trim((string)$text);
    if ($text === '') return 'en';

    $googleResult = agri_google_detect_language($text);
    if ($googleResult !== null) return $googleResult;

    // --- Offline fallback (Google unavailable/failed) ---
    if (preg_match('/[\x{0900}-\x{097F}]/u', $text)) {
        $lower = mb_strtolower($text, 'UTF-8');
        $mrHits = 0; $hiHits = 0;
        foreach (agri_translation_dictionary() as $row) {
            if ($row[1] !== '' && mb_strpos($lower, $row[1]) !== false) { $mrHits++; }
            if ($row[2] !== '' && mb_strpos($lower, $row[2]) !== false) { $hiHits++; }
        }
        if ($mrHits > $hiHits) return 'mr';
        if ($hiHits > $mrHits) return 'hi';
        return 'hi'; // ambiguous Devanagari default
    }
    return 'en';
}

/**
 * Translate a product name entered in $sourceLang ('en'|'mr'|'hi') into
 * all three languages. Matching is done word-by-word against the
 * dictionary (case-insensitive for English); unmatched words are kept
 * as-is in every language so nothing is ever dropped or blanked out.
 * Returns ['en' => ..., 'mr' => ..., 'hi' => ...] — always non-empty.
 */
function agri_translate_product_name($name, $sourceLang = 'auto') {
    $name = trim((string)$name);
    if ($name === '') return ['en' => '', 'mr' => '', 'hi' => ''];

    if ($sourceLang === 'auto' || !in_array($sourceLang, ['en', 'mr', 'hi'], true)) {
        $sourceLang = agri_detect_language($name);
    }

    $out = ['en' => '', 'mr' => '', 'hi' => ''];
    foreach (['en', 'mr', 'hi'] as $targetLang) {
        if ($targetLang === $sourceLang) { $out[$targetLang] = $name; continue; }

        // --- Layer 1: Google Cloud Translate (accurate, any vocabulary) ---
        $googleResult = agri_google_translate_text($name, $targetLang, $sourceLang);
        if ($googleResult !== null && trim($googleResult) !== '') {
            $out[$targetLang] = $googleResult;
            continue;
        }

        // --- Layer 2: offline dictionary fallback (Google unavailable) ---
        $out[$targetLang] = agri_dictionary_translate_word_by_word($name, $sourceLang, $targetLang);
    }
    return $out;
}

/**
 * Word-by-word dictionary translation used only when Google Cloud
 * Translate is unavailable or fails. Unmatched words are kept as-is so
 * nothing is ever dropped or blanked out.
 */
function agri_dictionary_translate_word_by_word($name, $sourceLang, $targetLang) {
    $srcIndex = ['en' => 0, 'mr' => 1, 'hi' => 2][$sourceLang];
    $tgtIndex = ['en' => 0, 'mr' => 1, 'hi' => 2][$targetLang];

    $lookup = $srcIndex === 0
        ? agri_translation_dictionary()          // keys are English words already
        : agri_translation_reverse($srcIndex);    // keys are Marathi or Hindi words

    $normalize = function ($tok) use ($srcIndex) {
        $key = $srcIndex === 0 ? mb_strtolower($tok, 'UTF-8') : $tok;
        return trim($key, ".,!?()'\"");
    };

    // Split into words + whitespace, keeping the whitespace so spacing is
    // preserved in the output exactly as the owner typed it.
    $tokens = preg_split('/(\s+)/u', trim($name), -1, PREG_SPLIT_DELIM_CAPTURE);
    $words = array_values(array_filter($tokens, fn($t) => trim($t) !== ''));

    $translatedWords = [];
    $i = 0;
    $n = count($words);
    while ($i < $n) {
        // Greedy: try a 2-word phrase first (e.g. "power tiller", "seed
        // drill", "john deere") so multi-word dictionary entries match
        // before falling back to single-word lookups.
        if ($i + 1 < $n) {
            $phraseKey = $normalize($words[$i] . ' ' . $words[$i + 1]);
            if (isset($lookup[$phraseKey])) {
                $translatedWords[] = $lookup[$phraseKey][$tgtIndex];
                $i += 2;
                continue;
            }
        }
        $key = $normalize($words[$i]);
        $translatedWords[] = isset($lookup[$key]) ? $lookup[$key][$tgtIndex] : $words[$i];
        $i++;
    }

    $translated = trim(implode(' ', $translatedWords));
    return $translated !== '' ? $translated : $name;
}
