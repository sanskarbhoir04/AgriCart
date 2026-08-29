<?php
// Standalone AJAX endpoint — returns ONLY the weather widget's inner HTML.
// Called via fetch() from index.php so that refreshing the weather
// does not reload the whole page.

$lat = isset($_GET['wlat']) ? floatval($_GET['wlat']) : null;
$lon = isset($_GET['wlon']) ? floatval($_GET['wlon']) : null;
$lang = $_GET['lang'] ?? $_COOKIE['agri_lang'] ?? 'en';
if (!in_array($lang, ['en','mr','hi'])) $lang = 'en';

if (!$lat || !$lon) {
    ?>
    <div id="wd-error" style="text-align:center;padding:20px;color:#e65100;font-size:13px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?=$lang==='mr'?'स्थान परवानगी नाकारली.':($lang==='hi'?'स्थान अनुमति अस्वीकार.':'Location access denied. Please allow location.')?>
        <br><button onclick="loadWeatherGPS()" style="margin-top:8px;padding:6px 16px;background:#4CAF50;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:13px;"><?=$lang==='mr'?'पुन्हा प्रयत्न करा':($lang==='hi'?'फिर प्रयास करें':'Try Again')?></button>
    </div>
    <?php
    exit;
}

// Fetch weather from Open-Meteo
$url = "https://api.open-meteo.com/v1/forecast?latitude={$lat}&longitude={$lon}&current=temperature_2m,relative_humidity_2m,wind_speed_10m,precipitation_probability,visibility,weather_code&daily=temperature_2m_max,temperature_2m_min,precipitation_probability_max,weather_code&timezone=auto&forecast_days=5";
$ctx = stream_context_create(['http'=>['timeout'=>8]]);
$raw = @file_get_contents($url, false, $ctx);
$wd = $raw ? json_decode($raw, true) : null;

// Reverse geocode (no zoom restriction = full address detail for accurate village/town match;
// namedetails=1 pulls direct name:mr / name:hi tags which are more reliable than locale negotiation)
$geoLangMap = ['en'=>'en','mr'=>'mr,hi,en','hi'=>'hi,en'];
$geoLang = $geoLangMap[$lang] ?? 'en';
$geo_url = "https://nominatim.openstreetmap.org/reverse?lat={$lat}&lon={$lon}&format=json&accept-language={$geoLang}&namedetails=1&addressdetails=1";
$geo_ctx = stream_context_create(['http'=>['timeout'=>6,'header'=>'User-Agent: AgriCart/1.0']]);
$geo_raw = @file_get_contents($geo_url, false, $geo_ctx);
$geo = $geo_raw ? json_decode($geo_raw, true) : null;

if ($wd && isset($wd['current'])) {
    $c = $wd['current'];
    $temp  = round($c['temperature_2m']);
    $humid = $c['relative_humidity_2m'];
    $wind  = round($c['wind_speed_10m']);
    $rain  = $c['precipitation_probability'];
    $vis   = round(($c['visibility'] ?? 10000) / 1000);
    $code  = $c['weather_code'];

    // Location name
    $city = '';
    if ($geo && isset($geo['address'])) {
        $a = $geo['address'];
        // Use the settlement-level address field (city/town/village/suburb), not the
        // resolved "name" of the point itself — that can be a road or POI name.
        // Farmers care about their actual village, not just the larger town/taluka —
        // so prefer village/hamlet, and show it alongside the town for context.
        $village = $a['village'] ?? $a['hamlet'] ?? $a['suburb'] ?? '';
        $town    = $a['town'] ?? $a['city'] ?? '';
        if ($village && $town && $village !== $town) {
            $city = "{$village}, {$town}";
        } else {
            $city = $village ?: $town ?: ($a['county'] ?? '');
        }
        $state = $a['state'] ?? '';
        $stateMap = ['Maharashtra'=>['hi'=>'महाराष्ट्र','mr'=>'महाराष्ट्र'],'Gujarat'=>['hi'=>'गुजरात','mr'=>'गुजरात'],'Madhya Pradesh'=>['hi'=>'मध्य प्रदेश','mr'=>'मध्य प्रदेश'],'Uttar Pradesh'=>['hi'=>'उत्तर प्रदेश','mr'=>'उत्तर प्रदेश'],'Rajasthan'=>['hi'=>'राजस्थान','mr'=>'राजस्थान'],'Punjab'=>['hi'=>'पंजाब','mr'=>'पंजाब'],'Haryana'=>['hi'=>'हरियाणा','mr'=>'हरियाणा'],'Delhi'=>['hi'=>'दिल्ली','mr'=>'दिल्ली'],'Goa'=>['hi'=>'गोवा','mr'=>'गोवा']];
        $stateT = ($lang !== 'en' && isset($stateMap[$state][$lang])) ? $stateMap[$state][$lang] : $state;
        $locStr = $city ? "{$city}, {$stateT}" : ($stateT ?: number_format($lat,2)."°N");
    } else {
        $locStr = number_format($lat,2)."°N, ".number_format($lon,2)."°E";
    }


    // Weather icon map
    $icons = [0=>'&#9728;',1=>'&#127780;',2=>'&#9925;',3=>'&#9729;',45=>'&#127787;',48=>'&#127787;',51=>'&#127746;',53=>'&#127746;',55=>'&#127783;',61=>'&#127783;',63=>'&#9928;',65=>'&#9928;',71=>'&#127784;',73=>'&#127784;',75=>'&#127784;',80=>'&#127783;',81=>'&#9928;',82=>'&#9928;',95=>'&#9928;',96=>'&#9928;',99=>'&#9928;'];
    $icon = $icons[$code] ?? $icons[intval($code/10)*10] ?? '&#127780;';

    // Condition text
    $condMap = [
        'en'=>[0=>'Clear Sky',1=>'Mainly Clear',2=>'Partly Cloudy',3=>'Overcast',45=>'Foggy',48=>'Icy Fog',51=>'Light Drizzle',53=>'Drizzle',55=>'Heavy Drizzle',61=>'Light Rain',63=>'Rain',65=>'Heavy Rain',71=>'Light Snow',73=>'Snow',75=>'Heavy Snow',80=>'Light Showers',81=>'Showers',82=>'Heavy Showers',95=>'Thunderstorm',96=>'Thunderstorm',99=>'Thunderstorm'],
        'mr'=>[0=>'स्वच्छ आकाश',1=>'मुख्यतः स्वच्छ',2=>'अंशतः ढगाळ',3=>'ढगाळ',45=>'धुके',51=>'हलकी रिमझिम',61=>'हलका पाऊस',63=>'पाऊस',65=>'जड पाऊस',95=>'वादळ'],
        'hi'=>[0=>'साफ आसमान',1=>'मुख्यतः साफ',2=>'आंशिक बादल',3=>'बादल',45=>'कोहरा',61=>'हल्की बारिश',63=>'बारिश',65=>'भारी बारिश',95=>'तूफान']
    ];
    $cmap = $condMap[$lang] ?? $condMap['en'];
    $cond = $cmap[$code] ?? $cmap[intval($code/10)*10] ?? ($lang==='mr'?'अज्ञात':($lang==='hi'?'अज्ञात':'Unknown'));

    // Advice
    $rainy = $code >= 51; $windy = $wind > 20; $highRain = $rain > 60;
    if ($lang === 'mr') {
        if($rainy)     $advice = "🌧️ पाऊस पडतोय. शेतकाम टाळा, फवारणी नंतर करा.";
        elseif($highRain) $advice = "⚠️ पाऊस येण्याची शक्यता जास्त. कापणी व फवारणी थांबवा.";
        elseif($windy)  $advice = "💨 वारा जास्त आहे. फवारणी टाळा.";
        else           $advice = "✅ हवामान चांगले आहे. दुपारी २ नंतर फवारणीसाठी योग्य वेळ.";
    } elseif ($lang === 'hi') {
        if($rainy)     $advice = "🌧️ बारिश हो रही है. खेत में काम न करें, स्प्रे बाद में करें.";
        elseif($highRain) $advice = "⚠️ बारिश की संभावना अधिक. कटाई और स्प्रे रोकें.";
        elseif($windy)  $advice = "💨 तेज हवा है. स्प्रे से बचें.";
        else           $advice = "✅ मौसम अच्छा है. दोपहर 2 बजे बाद कीटनाशक स्प्रे का सही समय.";
    } else {
        if($rainy)     $advice = "🌧️ It is raining. Avoid fieldwork, delay spraying.";
        elseif($highRain) $advice = "⚠️ High chance of rain. Postpone harvesting and spraying.";
        elseif($windy)  $advice = "💨 Windy conditions. Avoid pesticide spraying.";
        else           $advice = "✅ Good weather. Best time to spray pesticides is after 2 PM today.";
    }

    // Date
    $dowNames = [
        'en'=>['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],
        'mr'=>['रवि','सोम','मंगळ','बुध','गुरु','शुक्र','शनि'],
        'hi'=>['रवि','सोम','मंगल','बुध','गुरु','शुक्र','शनि']
    ];
    $monNames = [
        'en'=>['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],
        'mr'=>['जाने','फेब्रु','मार्च','एप्रि','मे','जून','जुलै','ऑग','सप्टें','ऑक्टो','नोव्हें','डिसें'],
        'hi'=>['जन','फर','मार्च','अप्रै','मई','जून','जुला','अग','सित','अक्टू','नव','दिस']
    ];
    $dn = $dowNames[$lang] ?? $dowNames['en'];
    $mn = $monNames[$lang] ?? $monNames['en'];
    $dateStr = $dn[date('w')] . ', ' . date('d') . ' ' . $mn[date('n') - 1] . ' ' . date('Y');

    // 5-day forecast
    $daily = $wd['daily'] ?? null;
    ?>
    <div id="wd-body">
        <div class="weather-main">
            <div>
                <div class="weather-temp"><?=$icon?> <?=$temp?><span style="vertical-align:top;margin-left:2px;">&deg;C</span></div>
                <div class="weather-condition"><?=htmlspecialchars($cond)?></div>
            </div>
            <div style="text-align:right;">
                <div class="weather-location"><i class="fa-solid fa-location-dot" style="color:#4CAF50;font-size:12px;"></i> <?=htmlspecialchars($locStr)?></div>
                <div style="font-size:12px;color:#888;margin-top:4px;"><?=$dateStr?></div>
                <div style="font-size:11px;color:#aaa;margin-top:2px;"><?=$lang==='mr'?'आत्ताच अपडेट केले':($lang==='hi'?'अभी अपडेट किया गया':'Updated just now')?></div>
            </div>
        </div>
        <div class="weather-meta">
            <div class="weather-meta-item"><i class="fa-solid fa-droplet"></i> <?=$lang==='mr'?'आर्द्रता':($lang==='hi'?'नमी':'Humidity')?> <b><?=$humid?>%</b></div>
            <div class="weather-meta-item"><i class="fa-solid fa-wind"></i> <?=$lang==='mr'?'वारा':($lang==='hi'?'हवा':'Wind')?> <b><?=$wind?> km/h</b></div>
            <div class="weather-meta-item"><i class="fa-solid fa-cloud-rain"></i> <?=$lang==='mr'?'पाऊस':($lang==='hi'?'बारिश':'Rain')?> <b><?=$rain?>%</b></div>
            <div class="weather-meta-item"><i class="fa-solid fa-eye"></i> <?=$lang==='mr'?'दृश्यमानता':($lang==='hi'?'दृश्यता':'Visibility')?> <b><?=$vis?> km</b></div>
        </div>
        <div class="weather-advice">
            <i class="fa-solid fa-lightbulb" style="margin-top:2px;"></i>
            <span><?=htmlspecialchars($advice)?></span>
        </div>
        <?php if ($daily && isset($daily['time'])): ?>
        <div class="weather-forecast">
            <?php
            $dayNames = ['en'=>['Sun','Mon','Tue','Wed','Thu','Fri','Sat'],'mr'=>['रवि','सोम','मंगळ','बुध','गुरु','शुक्र','शनि'],'hi'=>['रवि','सोम','मंगल','बुध','गुरु','शुक्र','शनि']];
            $dnames = $dayNames[$lang] ?? $dayNames['en'];
            for ($i = 0; $i < min(5, count($daily['time'])); $i++):
                $dt = strtotime($daily['time'][$i]);
                $dow = $dnames[intdiv(date('N',$dt),1) % 7];
                $fcode = $daily['weather_code'][$i] ?? 0;
                $ficon = $icons[$fcode] ?? $icons[intval($fcode/10)*10] ?? '&#127780;';
                $fmax = round($daily['temperature_2m_max'][$i]);
                $fmin = round($daily['temperature_2m_min'][$i]);
                $frain = $daily['precipitation_probability_max'][$i] ?? 0;
            ?>
            <div class="forecast-day">
                <div class="fc-day"><?=htmlspecialchars($dow)?></div>
                <div class="fc-icon"><?=$ficon?></div>
                <div class="fc-temp"><?=$fmax?>&deg;/<span style="color:#aaa;"><?=$fmin?>&deg;</span></div>
                <div class="fc-rain"><i class="fa-solid fa-droplet" style="font-size:9px;color:#64b5f6;"></i> <?=$frain?>%</div>
            </div>
            <?php endfor; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
} else {
    ?>
    <div id="wd-error" style="text-align:center;padding:20px;color:#e65100;font-size:13px;">
        <i class="fa-solid fa-triangle-exclamation"></i>
        <?=$lang==='mr'?'हवामान माहिती मिळवणे शक्य झाले नाही.':($lang==='hi'?'मौसम जानकारी नहीं मिली.':'Could not fetch weather data.')?>
        <br><small>Please try reloading the page.</small>
    </div>
    <?php
}
