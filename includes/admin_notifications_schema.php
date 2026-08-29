<?php
/**
 * Admin Notification Center (spec §14) — a real, persistent, admin-facing
 * notification feed. This is separate from (and doesn't touch) the
 * existing buyer-facing `user_notifications` and seller-facing
 * `seller_notifications` tables — those already work and are untouched.
 *
 * Design choice: ONE shared read-state per notification (not per-admin).
 * For a small admin team this is the simplest correct model — "someone on
 * the team has seen this" — and avoids a second per-admin-read-tracking
 * table. If AgriCart's admin team grows large enough that per-admin read
 * state becomes worth the complexity, that's a follow-up, not a blocker.
 *
 * Self-registering + idempotent, same pattern as commission_schema.php /
 * companies_schema.php: safe to require on every admin page load.
 */

if (!function_exists('admin_notif_bootstrap_schema')) {
    function admin_notif_bootstrap_schema(mysqli $conn): void
    {
        static $done = false;
        if ($done) { return; }
        $done = true;

        try {
            $conn->query("
                CREATE TABLE IF NOT EXISTS admin_notifications (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    type VARCHAR(40) NOT NULL,
                    title VARCHAR(200) NOT NULL,
                    message VARCHAR(500) NULL,
                    link VARCHAR(255) NULL,
                    is_read TINYINT(1) NOT NULL DEFAULT 0,
                    read_at TIMESTAMP NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_created (created_at),
                    INDEX idx_unread (is_read, created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
        } catch (\Throwable $e) {
            $done = false;
        }
    }
}

if (!function_exists('agri_notify_admin')) {
    /**
     * Fire-and-forget: create one admin notification. Never throws — a
     * broken notifications table must never block the order/registration/
     * request flow that triggered it, same guarantee
     * includes/seller_functions.php's seller-notification helper gives.
     *
     * $type is a short machine key (e.g. 'new_order', 'gst_verification',
     * 'payout_request') matching the icon map in the bell UI — see
     * admin/includes/team_layout_top.php.
     */
    function agri_notify_admin(mysqli $conn, string $type, string $title, ?string $message = null, ?string $link = null): void
    {
        try {
            admin_notif_bootstrap_schema($conn);
            $stmt = $conn->prepare("INSERT INTO admin_notifications (type, title, message, link) VALUES (?,?,?,?)");
            $stmt->bind_param('ssss', $type, $title, $message, $link);
            $stmt->execute();
        } catch (\Throwable $e) {
            // best-effort — swallow
        }
    }
}
