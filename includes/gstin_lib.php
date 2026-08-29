<?php
// =====================================================================
// includes/gstin_lib.php — Single source of truth for everything GSTIN
// across AgriCart: format/state-code/checksum validation, the official
// GST state-code table, and CGST/SGST vs IGST determination + splitting.
//
// Every place in the codebase that validates a GSTIN or decides the tax
// type for an invoice MUST go through this file instead of re-inventing
// the regex/logic locally, so the rules stay identical everywhere
// (Seller Registration, Seller Dashboard, Admin -> Companies, Admin ->
// Sellers, Admin -> Company Settings, and Invoice generation).
// =====================================================================

if (!function_exists('gstin_state_codes')) {
    /** Official GST state/UT code -> name table (as per CBIC). */
    function gstin_state_codes(): array
    {
        return [
            '01' => 'Jammu and Kashmir', '02' => 'Himachal Pradesh', '03' => 'Punjab',
            '04' => 'Chandigarh', '05' => 'Uttarakhand', '06' => 'Haryana', '07' => 'Delhi',
            '08' => 'Rajasthan', '09' => 'Uttar Pradesh', '10' => 'Bihar', '11' => 'Sikkim',
            '12' => 'Arunachal Pradesh', '13' => 'Nagaland', '14' => 'Manipur', '15' => 'Mizoram',
            '16' => 'Tripura', '17' => 'Meghalaya', '18' => 'Assam', '19' => 'West Bengal',
            '20' => 'Jharkhand', '21' => 'Odisha', '22' => 'Chhattisgarh', '23' => 'Madhya Pradesh',
            '24' => 'Gujarat', '25' => 'Daman and Diu', '26' => 'Dadra and Nagar Haveli',
            '27' => 'Maharashtra', '28' => 'Andhra Pradesh (Old)', '29' => 'Karnataka',
            '30' => 'Goa', '31' => 'Lakshadweep', '32' => 'Kerala', '33' => 'Tamil Nadu',
            '34' => 'Puducherry', '35' => 'Andaman and Nicobar Islands', '36' => 'Telangana',
            '37' => 'Andhra Pradesh', '38' => 'Ladakh', '97' => 'Other Territory',
        ];
    }
}

if (!function_exists('gstin_state_name_from_code')) {
    function gstin_state_name_from_code(?string $code): ?string
    {
        if (!$code) { return null; }
        $codes = gstin_state_codes();
        return $codes[str_pad($code, 2, '0', STR_PAD_LEFT)] ?? null;
    }
}

if (!function_exists('gstin_state_code_from_name')) {
    /** Reverse lookup, case-insensitive, for auto-filling State Code from a typed State name. */
    function gstin_state_code_from_name(?string $name): ?string
    {
        if (!$name) { return null; }
        $name = trim($name);
        foreach (gstin_state_codes() as $code => $stateName) {
            if (strcasecmp($stateName, $name) === 0) { return $code; }
        }
        return null;
    }
}

if (!function_exists('gstin_checksum_valid')) {
    /**
     * Verifies the 15th character of a GSTIN against the official
     * modulo-36 checksum algorithm. Returns true if the GSTIN doesn't
     * even have the right shape to check (so callers rely on
     * gstin_validate() for the authoritative pass/fail — this is only
     * ever consulted once the format regex has already passed).
     */
    function gstin_checksum_valid(string $gstin): bool
    {
        $chars = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $factor = 2;
        $sum = 0;
        $len = strlen($chars);
        for ($i = 12; $i >= 0; $i--) {
            $codePoint = strpos($chars, $gstin[$i]);
            if ($codePoint === false) { return false; }
            $digit = $factor * $codePoint;
            $digit = intdiv($digit, $len) + ($digit % $len);
            $sum += $digit;
            $factor = ($factor === 2) ? 1 : 2;
        }
        $checkCodePoint = (36 - ($sum % 36)) % 36;
        return $gstin[14] === $chars[$checkCodePoint];
    }
}

if (!function_exists('gstin_validate')) {
    /**
     * Full GSTIN validation: 15 characters, correct alphanumeric
     * structure, a real GST state code, and (best-effort) checksum.
     * Returns ['valid' => bool, 'message' => string, 'state_code' =>
     * ?string, 'state_name' => ?string].
     *
     * The checksum check is informational-only by default (a small
     * number of legitimately issued GSTINs predate/deviate from the
     * public checksum spec) — pass $strict = true to also reject a
     * failed checksum outright.
     */
    function gstin_validate(?string $gstin, bool $strict = false): array
    {
        $gstin = strtoupper(trim((string)$gstin));

        if ($gstin === '') {
            return ['valid' => false, 'message' => 'GSTIN is required.', 'state_code' => null, 'state_name' => null, 'gstin' => $gstin];
        }
        if (strlen($gstin) !== 15) {
            return ['valid' => false, 'message' => 'Invalid GSTIN. Please enter a valid 15-character GSTIN.', 'state_code' => null, 'state_name' => null, 'gstin' => $gstin];
        }
        // 2 digits state code + 10 char PAN + 1 entity code + 'Z' (fixed) + 1 checksum char.
        if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z][1-9A-Z][Z][0-9A-Z]$/', $gstin)) {
            return ['valid' => false, 'message' => 'Invalid GSTIN format. Please enter a valid 15-character GSTIN.', 'state_code' => null, 'state_name' => null, 'gstin' => $gstin];
        }
        $stateCode = substr($gstin, 0, 2);
        $stateName = gstin_state_name_from_code($stateCode);
        if (!$stateName) {
            return ['valid' => false, 'message' => 'Invalid GSTIN — unrecognized state code "' . $stateCode . '".', 'state_code' => $stateCode, 'state_name' => null, 'gstin' => $gstin];
        }
        $checksumOk = gstin_checksum_valid($gstin);
        if (!$checksumOk && $strict) {
            return ['valid' => false, 'message' => 'Invalid GSTIN — checksum verification failed.', 'state_code' => $stateCode, 'state_name' => $stateName, 'gstin' => $gstin];
        }
        return [
            'valid' => true,
            'message' => $checksumOk ? 'Valid GSTIN.' : 'GSTIN format is valid (checksum could not be confirmed).',
            'state_code' => $stateCode,
            'state_name' => $stateName,
            'pan' => substr($gstin, 2, 10),
            'checksum_ok' => $checksumOk,
            'gstin' => $gstin,
        ];
    }
}

if (!function_exists('gstin_pan_valid')) {
    function gstin_pan_valid(?string $pan): bool
    {
        $pan = strtoupper(trim((string)$pan));
        if ($pan === '') { return true; } // PAN is optional unless business rules require it
        return (bool)preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan);
    }
}

if (!function_exists('gstin_extract_pan')) {
    /** PAN is embedded in every GSTIN (characters 3-12) — use it if a separate PAN wasn't captured. */
    function gstin_extract_pan(?string $gstin): ?string
    {
        $gstin = strtoupper(trim((string)$gstin));
        if (strlen($gstin) !== 15) { return null; }
        return substr($gstin, 2, 10);
    }
}

if (!function_exists('gstin_determine_tax_type')) {
    /**
     * The single rule the whole invoicing system follows:
     * Seller State == Buyer State -> CGST + SGST. Otherwise -> IGST.
     * Falls back to CGST_SGST (the common case for a hyper-local
     * marketplace) when either state is unknown, rather than silently
     * mis-taxing an inter-state sale as intra-state without any signal.
     */
    function gstin_determine_tax_type(?string $sellerState, ?string $buyerState): string
    {
        $sellerState = trim((string)$sellerState);
        $buyerState = trim((string)$buyerState);
        if ($sellerState === '' || $buyerState === '') {
            return 'CGST_SGST';
        }
        return (strcasecmp($sellerState, $buyerState) === 0) ? 'CGST_SGST' : 'IGST';
    }
}

if (!function_exists('gstin_split_tax')) {
    /**
     * Splits a total tax amount into ['cgst'=>, 'sgst'=>, 'igst'=>]
     * according to $taxType ('CGST_SGST' or 'IGST'). CGST/SGST are
     * always exactly half each of the total tax amount.
     */
    function gstin_split_tax(float $taxAmount, string $taxType): array
    {
        $taxAmount = round($taxAmount, 2);
        if ($taxType === 'IGST') {
            return ['cgst' => 0.00, 'sgst' => 0.00, 'igst' => $taxAmount];
        }
        $half = round($taxAmount / 2, 2);
        // Guard against a 1-paisa rounding drift so cgst+sgst always == taxAmount exactly.
        $other = round($taxAmount - $half, 2);
        return ['cgst' => $half, 'sgst' => $other, 'igst' => 0.00];
    }
}

if (!function_exists('gstin_status_label')) {
    function gstin_status_label(?string $status): array
    {
        switch (strtolower((string)$status)) {
            case 'registered':        return ['Registered', 'active'];
            case 'composition':       return ['Composition Scheme', 'pending'];
            case 'unregistered':      return ['Unregistered', 'inactive'];
            case 'not_applicable':    return ['Not Applicable', 'inactive'];
            default:                  return ['Not Set', 'inactive'];
        }
    }
}

if (!function_exists('gstin_mask')) {
    /** Partially masks a GSTIN for list views (e.g. "27ABCDE****Z1"). */
    function gstin_mask(?string $gstin): string
    {
        $gstin = trim((string)$gstin);
        if (strlen($gstin) !== 15) { return $gstin; }
        return substr($gstin, 0, 7) . '****' . substr($gstin, 11);
    }
}
