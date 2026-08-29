<?php
// =====================================================================
// includes/crop_list.php — single canonical "Primary Crop" list.
//
// This used to be hardcoded separately inside pages/register.php. It's
// pulled out here so every place that needs the crop list (registration,
// "Edit Profile") reads from the same source instead of drifting apart
// or inventing new crop values.
// =====================================================================

if (!isset($AGRI_CROPS)) {
    $AGRI_CROPS = [
        'Wheat', 'Rice', 'Sugarcane', 'Cotton', 'Soybean', 'Onion', 'Tomato',
        'Grapes', 'Pomegranate', 'Turmeric', 'Jowar', 'Bajra', 'Tur Dal', 'Chickpea',
    ];
}

/**
 * Returns the crop list as a plain array, always including $selected
 * even if it's a legacy value that's since fallen out of the canonical
 * list, so the searchable "Edit Profile" crop select never silently
 * drops what the user already has saved.
 */
if (!function_exists('agri_crop_options_for_js')) {
    function agri_crop_options_for_js(string $selected = ''): array {
        global $AGRI_CROPS;
        $crops = $AGRI_CROPS;
        if ($selected !== '' && !in_array($selected, $crops, true)) {
            array_unshift($crops, $selected);
        }
        return $crops;
    }
}

/**
 * Renders <option> tags for the crop list, marking $selected as chosen.
 * Always includes the currently selected crop even if it's since fallen
 * out of the canonical list (e.g. legacy free-text values saved before
 * this list existed), so editing a profile never silently drops it.
 */
if (!function_exists('agri_crop_options_html')) {
    function agri_crop_options_html(string $selected = ''): string {
        global $AGRI_CROPS;
        $crops = $AGRI_CROPS;
        if ($selected !== '' && !in_array($selected, $crops, true)) {
            array_unshift($crops, $selected);
        }
        $html = '';
        foreach ($crops as $c) {
            $sel = ($c === $selected) ? ' selected' : '';
            $html .= '<option value="' . htmlspecialchars($c) . '"' . $sel . '>' . htmlspecialchars($c) . '</option>';
        }
        return $html;
    }
}
