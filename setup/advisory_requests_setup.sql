-- =====================================================================
-- advisory_requests_setup.sql — run this ONCE in phpMyAdmin
-- (or `mysql agricart < advisory_requests_setup.sql`)
--
-- Creates the `advisory_requests` table used by:
--   pages/request_advisory.php  (user submits a question to an expert)
--   pages/my_activity.php       ("My Activity" page — Orders / Rentals / Advisory)
--   admin/advisory_request_action.php (admin replies to a request)
--   admin/index.php             (admin "Advisory" tab list)
--
-- This lets a logged-in farmer ask an expert a crop question (with an
-- optional photo of the problem), get a Request ID to track it, and see
-- the admin's reply once answered — the same way "My Orders" works for
-- marketplace orders.
--
-- IMPORTANT: some environments already have an OLD `advisory_requests`
-- table left over from an earlier/unrelated feature, with a completely
-- different set of columns (crop_id, problem_title, problem_desc,
-- images, ai_response, expert_response, status ENUM(...)). None of the
-- current AgriCart code uses those columns, so this script drops that
-- old table first and recreates it with the columns the app actually
-- needs. If you have data in the OLD table that you care about, export
-- it first — this script will permanently delete it.
-- =====================================================================

DROP TABLE IF EXISTS advisory_requests;

CREATE TABLE advisory_requests (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    request_number VARCHAR(40) NOT NULL UNIQUE,
    crop VARCHAR(100) NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    image VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending',   -- pending / answered / closed
    admin_reply TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    answered_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_user (user_id)
);

-- After running this, farmers can submit "Request Expert Advice" from the
-- My Activity page and track / see replies to their advisory requests.
