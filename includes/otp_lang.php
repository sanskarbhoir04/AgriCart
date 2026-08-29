<?php
// =====================================================================
// includes/otp_lang.php — loads includes/lang/otp_{en,mr,hi}.php and
// provides a tiny sprintf-aware lookup helper used by register.php,
// otp.php, and otp_mailer.php so every OTP-related message (backend
// validation, JSON responses, and the OTP email body) is translated
// consistently in one place.
// =====================================================================

define('AGRI_OTP_SUPPORTED_LANGS', ['en', 'mr', 'hi']);

if (!function_exists('agri_otp_normalize_lang')) {
    function agri_otp_normalize_lang(?string $lang): string {
        $lang = strtolower(trim((string) $lang));
        return in_array($lang, AGRI_OTP_SUPPORTED_LANGS, true) ? $lang : 'en';
    }
}

if (!function_exists('agri_otp_lang_table')) {
    function agri_otp_lang_table(string $lang): array {
        static $cache = [];
        $lang = agri_otp_normalize_lang($lang);
        if (isset($cache[$lang])) return $cache[$lang];

        $file = __DIR__ . '/lang/otp_' . $lang . '.php';
        $table = is_file($file) ? (include $file) : [];

        // Always merge over English so a missing key in mr/hi never
        // breaks the page — it just silently falls back to English.
        if ($lang !== 'en') {
            $enFile = __DIR__ . '/lang/otp_en.php';
            $en = is_file($enFile) ? (include $enFile) : [];
            $table = array_merge($en, $table);
        }
        $cache[$lang] = $table;
        return $table;
    }
}

if (!function_exists('agri_otp_t')) {
    /**
     * Translate a single OTP-flow string. Extra args are passed through
     * sprintf() (e.g. agri_otp_t('otp_incorrect', $lang, $remaining)).
     */
    function agri_otp_t(string $key, string $lang = 'en', ...$args): string {
        $table = agri_otp_lang_table($lang);
        $str = $table[$key] ?? $key;
        return $args ? vsprintf($str, $args) : $str;
    }
}
