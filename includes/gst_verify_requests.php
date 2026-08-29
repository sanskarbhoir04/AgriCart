<?php
// =====================================================================
// includes/gst_verify_requests.php — GST verification request queue.
//
// WHY THIS EXISTS: the Seller Dashboard's "Verify GSTIN" button
// (seller/dashboard.js -> sdVerifyGstin()) only ever showed a toast —
// it never told Admin anything, so no request could ever reach the
// Admin panel. Separately, Admin's "Verify" action on a Companies-
// directory record (admin/account_action.php, admin/company_action.php)
// has to *guess* which seller login account it belongs to (business
// name / seller name / email match — see includes/gst_sync.php),
// because `sellers` (Companies directory) and a seller's own `users`
// login account have no hard FK between them in this schema.
//
// This queue removes the guesswork for the seller-initiated path: the
// seller submits the request from their own logged-in session, so
// `seller_user_id` is exact — Admin approving it needs zero matching.
// =====================================================================

if (!function_exists('gst_verify_requests_bootstrap_schema')) {
    function gst_verify_requests_bootstrap_schema(mysqli $conn): void
    {
        try {
            $conn->query(
                "CREATE TABLE IF NOT EXISTS gst_verification_requests (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    seller_user_id INT NOT NULL,
                    business_name VARCHAR(255) NULL,
                    legal_business_name VARCHAR(255) NULL,
                    gstin VARCHAR(20) NULL,
                    status VARCHAR(20) NOT NULL DEFAULT 'pending',
                    requested_at DATETIME NOT NULL,
                    reviewed_at DATETIME NULL,
                    reviewed_by VARCHAR(120) NULL,
                    admin_note VARCHAR(255) NULL,
                    KEY idx_seller (seller_user_id),
                    KEY idx_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) { /* degrade quietly — request submission just won't persist until this runs */ }
    }
}

if (!function_exists('gst_verify_request_submit')) {
    /**
     * Seller submits (or re-submits) a GST verification request. One
     * pending request per seller — re-requesting just refreshes it
     * instead of stacking duplicates.
     */
    function gst_verify_request_submit(mysqli $conn, int $sellerUserId, string $businessName, string $legalBusinessName, string $gstin): array
    {
        if ($gstin === '') {
            return ['success' => false, 'error' => 'Add your GSTIN and save GST Details before requesting verification.'];
        }

        $s = $conn->prepare("SELECT id FROM gst_verification_requests WHERE seller_user_id = ? AND status = 'pending' LIMIT 1");
        $s->bind_param('i', $sellerUserId);
        $s->execute();
        $existing = $s->get_result()->fetch_assoc();

        if ($existing) {
            $u = $conn->prepare("UPDATE gst_verification_requests SET business_name=?, legal_business_name=?, gstin=?, requested_at=NOW() WHERE id=?");
            $u->bind_param('sssi', $businessName, $legalBusinessName, $gstin, $existing['id']);
            $ok = $u->execute();
        } else {
            $i = $conn->prepare(
                "INSERT INTO gst_verification_requests (seller_user_id, business_name, legal_business_name, gstin, status, requested_at)
                 VALUES (?,?,?,?, 'pending', NOW())"
            );
            $i->bind_param('isss', $sellerUserId, $businessName, $legalBusinessName, $gstin);
            $ok = $i->execute();
        }
        if (!$ok) {
            error_log('gst_verify_request_submit: insert/update failed: ' . $conn->error);
        }
        return ['success' => $ok, 'error' => $ok ? null : 'Could not submit your GST verification request right now. Please try again.'];
    }
}

if (!function_exists('gst_verify_request_notify_admin')) {
    /**
     * Called by seller/seller_api.php right after gst_verify_request_submit()
     * succeeds — kept separate so this file's return-value contract above
     * doesn't change, and so the notification never affects whether the
     * seller's submission is reported as successful.
     */
    function gst_verify_request_notify_admin(mysqli $conn, string $sellerName, string $gstin): void
    {
        if (!function_exists('agri_notify_admin')) {
            require_once __DIR__ . '/admin_notifications_schema.php';
        }
        agri_notify_admin(
            $conn,
            'gst_verification',
            'GST Verification Requested',
            $sellerName . ' submitted GSTIN ' . $gstin . ' for verification.',
            'gst_verification_requests.php'
        );
    }
}

if (!function_exists('gst_verify_request_status_for_seller')) {
    /** Most recent request's status for this seller ('pending'/'approved'/'rejected'), or null if none yet. */
    function gst_verify_request_status_for_seller(mysqli $conn, int $sellerUserId): ?string
    {
        $s = $conn->prepare("SELECT status FROM gst_verification_requests WHERE seller_user_id = ? ORDER BY requested_at DESC LIMIT 1");
        $s->bind_param('i', $sellerUserId);
        $s->execute();
        $row = $s->get_result()->fetch_assoc();
        return $row ? $row['status'] : null;
    }
}

if (!function_exists('gst_verify_mark_seller_verified')) {
    /**
     * Marks a seller's own GST profile (seller_payout_profiles) as
     * verified by their exact user_id. Shared by
     * gst_verify_request_review() below AND by the Companies-directory
     * "Verify" flow (includes/gst_sync.php), so every entry point that
     * ends in "this seller's GST is now verified" stays consistent.
     */
    function gst_verify_mark_seller_verified(mysqli $conn, int $sellerUserId, string $verifiedByLabel): bool
    {
        try {
            $stmt = $conn->prepare(
                "UPDATE seller_payout_profiles
                    SET gst_verified_status = 'verified', gst_verified_at = NOW(), gst_verified_by = ?
                  WHERE user_id = ?"
            );
            $stmt->bind_param('si', $verifiedByLabel, $sellerUserId);
            return $stmt->execute();
        } catch (\Throwable $e) {
            // gst_verified_by column not present yet on this install —
            // still sync the core status flag so the Seller Dashboard
            // reflects Verified immediately.
            $stmt = $conn->prepare("UPDATE seller_payout_profiles SET gst_verified_status = 'verified', gst_verified_at = NOW() WHERE user_id = ?");
            $stmt->bind_param('i', $sellerUserId);
            return $stmt->execute();
        }
    }
}

if (!function_exists('gst_verify_request_pending_count')) {
    function gst_verify_request_pending_count(mysqli $conn): int
    {
        $r = $conn->query("SELECT COUNT(*) c FROM gst_verification_requests WHERE status = 'pending'");
        return $r ? (int)($r->fetch_assoc()['c'] ?? 0) : 0;
    }
}

if (!function_exists('gst_verify_requests_list')) {
    /** @return array<int, array> rows joined with the seller's own name/contact for the admin list. */
    function gst_verify_requests_list(mysqli $conn, string $status = 'pending'): array
    {
        $s = $conn->prepare(
            "SELECT r.*, u.full_name AS seller_name, u.email AS seller_email, u.mobile AS seller_mobile
               FROM gst_verification_requests r JOIN users u ON u.id = r.seller_user_id
              WHERE r.status = ?
              ORDER BY r.requested_at ASC"
        );
        $s->bind_param('s', $status);
        $s->execute();
        return $s->get_result()->fetch_all(MYSQLI_ASSOC);
    }
}

if (!function_exists('gst_verify_request_review')) {
    /**
     * Approve/reject a request AND — on approve — sync
     * seller_payout_profiles.gst_verified_status directly by the
     * request's own seller_user_id (exact, no name matching needed).
     */
    function gst_verify_request_review(mysqli $conn, int $requestId, string $decision, string $reviewerLabel, string $note = ''): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['success' => false, 'error' => 'Invalid decision.'];
        }
        $s = $conn->prepare("SELECT * FROM gst_verification_requests WHERE id = ? LIMIT 1");
        $s->bind_param('i', $requestId);
        $s->execute();
        $req = $s->get_result()->fetch_assoc();
        if (!$req) {
            return ['success' => false, 'error' => 'Request not found.'];
        }
        if ($req['status'] !== 'pending') {
            return ['success' => false, 'error' => 'This request has already been reviewed.'];
        }

        $u = $conn->prepare("UPDATE gst_verification_requests SET status=?, reviewed_at=NOW(), reviewed_by=?, admin_note=? WHERE id=?");
        $u->bind_param('sssi', $decision, $reviewerLabel, $note, $requestId);
        $ok = $u->execute();

        if ($ok && $decision === 'approved') {
            gst_verify_mark_seller_verified($conn, (int)$req['seller_user_id'], $reviewerLabel);
        } elseif ($ok && $decision === 'rejected') {
            try {
                $r = $conn->prepare("UPDATE seller_payout_profiles SET gst_verified_status = 'not_verified' WHERE user_id = ?");
                $r->bind_param('i', $req['seller_user_id']);
                $r->execute();
            } catch (\Throwable $e) { /* best-effort */ }
        }

        if ($ok && function_exists('agri_seller_notify')) {
            agri_seller_notify(
                $conn, (int)$req['seller_user_id'],
                $decision === 'approved' ? 'gst_verified' : 'gst_unverified',
                $decision === 'approved' ? 'GST Verified' : 'GST Verification Rejected',
                $decision === 'approved'
                    ? 'Your GST details have been verified by AgriCart.'
                    : ('Your GST verification request was rejected' . ($note ? (': ' . $note) : '.')),
                'seller/dashboard.php#gst'
            );
        }

        return ['success' => $ok, 'error' => $ok ? null : 'Update failed.', 'seller_user_id' => (int)$req['seller_user_id']];
    }
}
