<?php
// =====================================================================
// includes/mandi_client.php — shared, secured client for the data.gov.in
// Mandi price feed. Used by both pages/mandi.php (public/customer) and
// admin/mandi.php (admin panel) so the security fixes live in one place.
// =====================================================================

require_once __DIR__ . '/env.php';
require_once __DIR__ . '/security.php';

if (!function_exists('agri_mandi_send_cors_headers')) {
    function agri_mandi_send_cors_headers(): void {
        $allowed = array_filter(array_map('trim', explode(',', env('ALLOWED_ORIGINS', ''))));
        $origin  = $_SERVER['HTTP_ORIGIN'] ?? '';
        if ($origin !== '' && in_array($origin, $allowed, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
        }
        header('Access-Control-Allow-Methods: GET');
        header('Access-Control-Allow-Headers: Content-Type');
    }
}

if (!function_exists('agri_mandi_rate_limit_ok')) {
    // Simple per-session rate limit: 30 requests / minute.
    function agri_mandi_rate_limit_ok(): bool {
        agri_session_start();
        $now = time();
        $_SESSION['mandi_rl'] = array_filter($_SESSION['mandi_rl'] ?? [], fn($t) => $t > $now - 60);
        if (count($_SESSION['mandi_rl']) >= 30) return false;
        $_SESSION['mandi_rl'][] = $now;
        return true;
    }
}

if (!function_exists('agri_mandi_fetch')) {
    /**
     * Fetch + validate mandi records. Always returns a well-formed array;
     * never throws raw API/network errors back to the caller.
     */
    function agri_mandi_fetch(array $query, bool $debug = false): array {
        agri_mandi_send_cors_headers();

        if (!agri_mandi_rate_limit_ok()) {
            http_response_code(429);
            return ['success' => false, 'error' => 'Too many requests. Please slow down and try again shortly.'];
        }

        $apiKey = env('MANDI_API_KEY', '');
        if ($apiKey === '') {
            error_log('[AgriCart] Mandi API: MANDI_API_KEY is not configured.');
            http_response_code(503);
            return ['success' => false, 'error' => 'Mandi price service is not configured.'];
        }

        // ── Input validation ──
        $allowedStates = [
            'Maharashtra','Andhra Pradesh','Karnataka','Gujarat','Madhya Pradesh','Punjab','Haryana',
            'Rajasthan','Uttar Pradesh','Bihar','West Bengal','Tamil Nadu','Telangana','Kerala','Odisha',
            'Chhattisgarh','Jharkhand','Assam','Uttarakhand','Himachal Pradesh','Delhi','Goa',
        ];
        $state = trim((string)($query['state'] ?? 'Maharashtra'));
        if (!in_array($state, $allowedStates, true)) $state = 'Maharashtra';

        $commodity = trim((string)($query['commodity'] ?? ''));
        // Allow letters (incl. basic unicode), spaces, hyphens only — max 60 chars.
        if ($commodity !== '' && (!preg_match('/^[\p{L}\s\-]{1,60}$/u', $commodity))) {
            $commodity = '';
        }

        $limit = (int)($query['limit'] ?? 500);
        $limit = max(1, min($limit, 1000)); // hard cap — never let the caller request unbounded records

        $url = "https://api.data.gov.in/resource/9ef84268-d588-465a-a308-a864a43d0070"
            . "?api-key=" . urlencode($apiKey)
            . "&format=json"
            . "&limit=" . $limit
            . "&filters[state]=" . urlencode($state);
        if ($commodity !== '') {
            $url .= "&filters[commodity]=" . urlencode($commodity);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER     => ['Accept: application/json'],
        ]);
        $response  = curl_exec($ch);
        $httpCode  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            error_log('[AgriCart] Mandi API cURL error: ' . $curlError);
            http_response_code(502);
            return ['success' => false, 'error' => 'Could not reach the mandi price service. Please try again later.'];
        }

        if ($httpCode !== 200) {
            error_log('[AgriCart] Mandi API returned HTTP ' . $httpCode . ': ' . substr((string)$response, 0, 500));
            http_response_code(502);
            return ['success' => false, 'error' => 'Mandi price service returned an error. Please try again later.'];
        }

        $data = json_decode((string)$response, true);
        if (!is_array($data) || !isset($data['records']) || !is_array($data['records'])) {
            error_log('[AgriCart] Mandi API returned unexpected JSON shape.');
            http_response_code(502);
            $out = ['success' => false, 'error' => 'Mandi price service returned unexpected data.'];
            if ($debug) $out['raw'] = substr((string)$response, 0, 1000);
            return $out;
        }

        $records = [];
        foreach ($data['records'] as $r) {
            $records[] = [
                'commodity'   => (string)($r['commodity'] ?? ''),
                'variety'     => (string)($r['variety'] ?? ''),
                'market'      => (string)($r['market'] ?? ''),
                'district'    => (string)($r['district'] ?? ''),
                'state'       => (string)($r['state'] ?? ''),
                'min_price'   => (float)($r['min_price'] ?? 0),
                'max_price'   => (float)($r['max_price'] ?? 0),
                'modal_price' => (float)($r['modal_price'] ?? 0),
                'date'        => (string)($r['arrival_date'] ?? ''),
            ];
        }

        return [
            'success' => true,
            'count'   => count($records),
            'records' => $records,
            'total'   => (int)($data['total'] ?? 0),
        ];
    }
}
