<?php
// =====================================================================
// includes/assets.php — cache-busting helper.
// Replaces the old inline "echo time()" query-string pattern (which busted
// the browser cache on EVERY request, defeating caching entirely) with a
// version based on the file's actual last-modified time, so browsers
// only re-download an asset when it actually changes.
// =====================================================================

if (!function_exists('agri_asset_v')) {
    /**
     * @param string $webPath   Path as used in the <link>/<img> tag, e.g. "/assets/css/style.css"
     * @param string $diskRoot  Absolute path to the project root (so we can stat the real file)
     */
    function agri_asset_v(string $webPath, string $diskRoot): string {
        // Strip a leading "/" and any query string before resolving on disk.
        $relative = ltrim(parse_url($webPath, PHP_URL_PATH) ?: $webPath, '/');
        $fullPath = rtrim($diskRoot, '/') . '/' . $relative;

        $version = is_file($fullPath) ? filemtime($fullPath) : time(); // fallback keeps things working even if the file can't be found
        return $webPath . (str_contains($webPath, '?') ? '&' : '?') . 'v=' . $version;
    }
}
