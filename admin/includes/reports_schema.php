<?php
// =====================================================================
// admin/includes/reports_schema.php — Bootstrap + shared helpers for
// the Reports & Analytics module (report.php / report_export.php).
//
// Follows the same defensive pattern already used across this codebase
// (see admin/index.php's agri_connect_bootstrap_schema call): every
// helper here assumes the surrounding schema MAY be a version or two
// behind, and degrades to a safe default (0 / [] / null) instead of a
// fatal error whenever a table/column isn't there yet.
//
// Include this AFTER admin_guard.php (so $conn + permissions.php are
// already available).
// =====================================================================

if (!function_exists('reports_bootstrap_permission')) {
    /**
     * Idempotently registers the 'reports.view' permission so it shows
     * up in Manage Permissions / Roles for a Super Admin to grant to
     * whichever roles should see the Reports module. This never grants
     * the permission to anyone by itself — Super Admin still has to
     * flip it on per role from the existing Roles & Permissions screen,
     * exactly like every other module permission in this project.
     */
    function reports_bootstrap_permission(mysqli $conn): void
    {
        try {
            $exists = $conn->query("SELECT id FROM admin_permissions WHERE permission_key = 'reports.view' LIMIT 1");
            if ($exists && $exists->num_rows > 0) {
                return;
            }
            $stmt = $conn->prepare(
                "INSERT INTO admin_permissions (permission_key, module_name, action_name) VALUES ('reports.view', 'reports', 'view')"
            );
            if ($stmt) {
                $stmt->execute();
            }
        } catch (\Throwable $e) {
            // admin_permissions table not present in this install yet — the
            // page still works for Super Admin (who always passes hasPermission()).
        }
    }
}

if (!function_exists('rpt_scalar')) {
    /** Runs a no-param SELECT and returns the first column of the first row, or $default on any failure. */
    function rpt_scalar(mysqli $conn, string $sql, $default = 0)
    {
        try {
            $res = $conn->query($sql);
            if (!$res) { return $default; }
            $row = $res->fetch_row();
            return $row && $row[0] !== null ? $row[0] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('rpt_rows')) {
    /** Runs a no-param SELECT and returns all rows as an assoc array, or [] on any failure. */
    function rpt_rows(mysqli $conn, string $sql): array
    {
        try {
            $res = $conn->query($sql);
            if (!$res) { return []; }
            return $res->fetch_all(MYSQLI_ASSOC);
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('rpt_prepared_rows')) {
    /**
     * Prepared-statement version of rpt_rows() for anything built from
     * user-supplied filters (dates, search text, status, ids, ...).
     * $types is the mysqli bind_param type string, $params the values.
     */
    function rpt_prepared_rows(mysqli $conn, string $sql, string $types = '', array $params = []): array
    {
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) { return []; }
            if ($types !== '' && $params) {
                $stmt->bind_param($types, ...$params);
            }
            $stmt->execute();
            $res = $stmt->get_result();
            return $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('rpt_prepared_scalar')) {
    function rpt_prepared_scalar(mysqli $conn, string $sql, string $types, array $params, $default = 0)
    {
        try {
            $stmt = $conn->prepare($sql);
            if (!$stmt) { return $default; }
            $stmt->bind_param($types, ...$params);
            $stmt->execute();
            $row = $stmt->get_result()->fetch_row();
            return $row && $row[0] !== null ? $row[0] : $default;
        } catch (\Throwable $e) {
            return $default;
        }
    }
}

if (!function_exists('rpt_trend')) {
    /** Same month-over-month trend calc used on the main dashboard, reused here for report cards. */
    function rpt_trend($thisVal, $lastVal): ?array
    {
        $thisVal = (float)$thisVal; $lastVal = (float)$lastVal;
        if ($lastVal <= 0) { return null; }
        $pct = round((($thisVal - $lastVal) / $lastVal) * 100, 1);
        return ['pct' => $pct, 'up' => $pct >= 0];
    }
}

if (!function_exists('rpt_money')) {
    function rpt_money($v): string
    {
        return '₹' . number_format((float)$v, 2);
    }
}

if (!function_exists('rpt_date_bounds')) {
    /**
     * Resolves a report 'range' shortcut (today/week/month/year/custom)
     * into [$startDate, $endDate] as Y-m-d strings, inclusive.
     */
    function rpt_date_bounds(string $range, ?string $from, ?string $to): array
    {
        $today = date('Y-m-d');
        switch ($range) {
            case 'daily':
                return [$today, $today];
            case 'weekly':
                return [date('Y-m-d', strtotime('monday this week')), $today];
            case 'yearly':
                return [date('Y-01-01'), $today];
            case 'custom':
                $from = $from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : date('Y-m-d', strtotime('-30 days'));
                $to   = $to && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : $today;
                if ($from > $to) { [$from, $to] = [$to, $from]; }
                return [$from, $to];
            case 'monthly':
            default:
                return [date('Y-m-01'), $today];
        }
    }
}

if (!function_exists('rpt_csrf_safe_filename')) {
    function rpt_csrf_safe_filename(string $name): string
    {
        return preg_replace('/[^A-Za-z0-9_\-]/', '_', $name);
    }
}
