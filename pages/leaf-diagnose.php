<?php
/**
 * leaf-diagnose.php
 * ─────────────────────────────────────────────────────────────
 * Real AI leaf-disease diagnosis backend for the "AI Crop Scanner"
 * on advisory.php. Replaces the old fake setTimeout() simulation.
 *
 * Powered by Plant.id / Kindwise Health Assessment API (plant.health).
 * Docs: https://documenter.getpostman.com/view/24599534/2s93z5A4v2
 *
 * SETUP (one-time):
 * 1. Get a free/paid API key: http://admin.kindwise.com/
 * 2. Set it as an environment variable on your server — do NOT
 *    hardcode it in this file. Examples:
 *      - Apache (.htaccess or vhost): SetEnv PLANT_ID_API_KEY "your-key-here"
 *      - Nginx + PHP-FPM (pool conf): env[PLANT_ID_API_KEY] = your-key-here
 *      - cPanel: Software > Setup Node/PHP App > Environment Variables,
 *        or add to php.ini: env[PLANT_ID_API_KEY]=your-key-here
 * 3. Place this file wherever "<?php echo $base_path; ?>/leaf-diagnose.php"
 *    resolves to on your site (advisory.php's KM_LEAF_API_URL points here).
 *    If your folder structure differs, either move this file or edit the
 *    KM_LEAF_API_URL constant near the top of advisory.php's <script>.
 * ─────────────────────────────────────────────────────────────
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../includes/env.php';

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'method_not_allowed', 'message' => 'Use POST.']);
    exit;
}

$apiKey = env('PLANT_ID_API_KEY');
if (!$apiKey) {
    http_response_code(500);
    echo json_encode([
        'error' => 'server_config',
        'message' => 'PLANT_ID_API_KEY environment variable is not set on the server.',
    ]);
    exit;
}

// Basic upload validation
if (!isset($_FILES['leaf_image']) || $_FILES['leaf_image']['error'] !== UPLOAD_ERR_OK) {
    http_response_code(400);
    echo json_encode(['error' => 'no_image', 'message' => 'No leaf image was uploaded.']);
    exit;
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp'];
$mime = mime_content_type($_FILES['leaf_image']['tmp_name']);
if (!in_array($mime, $allowedMimes, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'bad_file_type', 'message' => 'Only JPG, PNG or WEBP images are allowed.']);
    exit;
}

// 8 MB max upload guard
if ($_FILES['leaf_image']['size'] > 8 * 1024 * 1024) {
    http_response_code(400);
    echo json_encode(['error' => 'file_too_large', 'message' => 'Image must be under 8 MB.']);
    exit;
}

$imageData = file_get_contents($_FILES['leaf_image']['tmp_name']);
$base64Image = base64_encode($imageData);

// Language: Plant.id natively supports 'en' and 'hi' (no Marathi yet) — falls back to English otherwise
$lang = (isset($_POST['lang']) && $_POST['lang'] === 'hi') ? 'hi' : 'en';

$payload = [
    'images' => [$base64Image],
    'health' => 'only',      // we only need the disease/health assessment (1 credit), not species ID
    'similar_images' => false,
];

$details = 'local_name,description,treatment,common_names';
$url = 'https://api.plant.id/v3/identification?details=' . urlencode($details) . '&language=' . urlencode($lang);

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'Api-Key: ' . $apiKey,
    ],
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlErr = curl_error($ch);
curl_close($ch);

if ($curlErr) {
    http_response_code(502);
    echo json_encode(['error' => 'network', 'message' => $curlErr]);
    exit;
}

$data = json_decode($response, true);

if ($httpCode !== 200 && $httpCode !== 201) {
    http_response_code($httpCode ?: 502);
    echo json_encode([
        'error' => 'api_error',
        'message' => $data['message'] ?? ('Plant.id API returned HTTP ' . $httpCode),
    ]);
    exit;
}

$isPlant = $data['result']['is_plant']['binary'] ?? null;
$isPlantProb = $data['result']['is_plant']['probability'] ?? null;
$isHealthy = $data['result']['is_healthy']['binary'] ?? null;
$healthyProb = $data['result']['is_healthy']['probability'] ?? null;

$suggestions = $data['result']['disease']['suggestions'] ?? [];

// Sort by probability, keep only genuinely harmful suggestions, cap at 3
usort($suggestions, fn($a, $b) => ($b['probability'] ?? 0) <=> ($a['probability'] ?? 0));

$diseases = [];
foreach ($suggestions as $s) {
    if (($s['is_harmful'] ?? true) === false) continue; // skip non-harmful classes (e.g. natural leaf spots)
    $details = $s['details'] ?? [];
    $treatment = $details['treatment'] ?? [];
    $diseases[] = [
        'name' => $details['local_name'] ?? ($details['common_names'][0] ?? $s['name']),
        'scientific_name' => $s['name'],
        'probability' => round((($s['probability'] ?? 0)) * 100),
        'description' => $details['description'] ?? null,
        'treatment_biological' => $treatment['biological'] ?? null,
        'treatment_chemical' => $treatment['chemical'] ?? null,
        'prevention' => $treatment['prevention'] ?? null,
    ];
    if (count($diseases) >= 3) break;
}

echo json_encode([
    'success' => true,
    'is_plant' => $isPlant,
    'is_plant_probability' => $isPlantProb,
    'is_healthy' => $isHealthy,
    'is_healthy_probability' => $healthyProb,
    'diseases' => $diseases,
]);
