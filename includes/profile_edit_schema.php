<?php
// =====================================================================
// includes/profile_edit_schema.php
// Idempotent, self-healing schema setup for the "Edit Profile" feature.
// Safe to include on every page load: every change first checks whether
// it's already applied before touching the DB (same pattern
// includes/agri_connect_schema.php already uses).
//
// Everything else the Edit Profile form needs (full_name, email,
// mobile, village, district, primary_crop, profile_photo,
// saved_pincode, saved_address) already exists on `users` and is
// reused as-is — this only adds the structured address fields that
// genuinely don't exist yet.
// =====================================================================

require_once __DIR__ . '/agri_connect_schema.php'; // reuses agri_column_exists()

if (!function_exists('agri_profile_edit_bootstrap_schema')) {
    function agri_profile_edit_bootstrap_schema($conn) {
        if (!agri_column_exists($conn, 'users', 'address_line1')) {
            @$conn->query("ALTER TABLE users ADD COLUMN address_line1 VARCHAR(255) DEFAULT NULL AFTER saved_address");
        }
        if (!agri_column_exists($conn, 'users', 'address_line2')) {
            @$conn->query("ALTER TABLE users ADD COLUMN address_line2 VARCHAR(255) DEFAULT NULL AFTER address_line1");
        }
        if (!agri_column_exists($conn, 'users', 'city')) {
            @$conn->query("ALTER TABLE users ADD COLUMN city VARCHAR(100) DEFAULT NULL AFTER address_line2");
        }
        if (!agri_column_exists($conn, 'users', 'state')) {
            @$conn->query("ALTER TABLE users ADD COLUMN state VARCHAR(100) DEFAULT 'Maharashtra' AFTER city");
        }
    }
}
