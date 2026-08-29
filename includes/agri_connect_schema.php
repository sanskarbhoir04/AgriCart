<?php
// =====================================================================
// includes/agri_connect_schema.php
// Idempotent, self-healing schema setup for the Agri-Connect (Phase 1)
// upgrade — Expert role system, richer posts, saves/reports, expert
// advice. Safe to include on every page load: every change first checks
// whether it's already applied before touching the DB (same pattern
// admin/community_action.php already uses for its `author_name` column).
// =====================================================================

if (!function_exists('agri_column_exists')) {
    function agri_column_exists($conn, $table, $column) {
        $table  = $conn->real_escape_string($table);
        $column = $conn->real_escape_string($column);
        $res = @$conn->query("SHOW COLUMNS FROM `{$table}` LIKE '{$column}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('agri_table_exists')) {
    function agri_table_exists($conn, $table) {
        $table = $conn->real_escape_string($table);
        $res = @$conn->query("SHOW TABLES LIKE '{$table}'");
        return $res && $res->num_rows > 0;
    }
}

if (!function_exists('agri_connect_bootstrap_schema')) {
    function agri_connect_bootstrap_schema($conn) {
        // ── 1. users.role — add buyer / seller / expert alongside farmer / customer / admin ──
        $roleCol = @$conn->query("SHOW COLUMNS FROM users LIKE 'role'");
        if ($roleCol && ($row = $roleCol->fetch_assoc())) {
            if (strpos($row['Type'], "'expert'") === false) {
                @$conn->query(
                    "ALTER TABLE users MODIFY COLUMN role
                     ENUM('farmer','customer','buyer','seller','expert','admin') DEFAULT 'customer'"
                );
            }
        }

        // ── 2. Expert profile fields on users ──
        if (!agri_column_exists($conn, 'users', 'qualification')) {
            @$conn->query("ALTER TABLE users ADD COLUMN qualification VARCHAR(150) DEFAULT NULL AFTER role");
        }
        if (!agri_column_exists($conn, 'users', 'expertise')) {
            @$conn->query("ALTER TABLE users ADD COLUMN expertise VARCHAR(150) DEFAULT NULL AFTER qualification");
        }

        // ── 3. community_posts — crop / district / solved-status ──
        if (!agri_column_exists($conn, 'community_posts', 'crop')) {
            @$conn->query("ALTER TABLE community_posts ADD COLUMN crop VARCHAR(100) DEFAULT NULL AFTER category");
        }
        if (!agri_column_exists($conn, 'community_posts', 'district')) {
            @$conn->query("ALTER TABLE community_posts ADD COLUMN district VARCHAR(100) DEFAULT NULL AFTER crop");
        }
        if (!agri_column_exists($conn, 'community_posts', 'is_solved')) {
            @$conn->query("ALTER TABLE community_posts ADD COLUMN is_solved TINYINT(1) DEFAULT 0 AFTER is_pinned");
        }
        // Widen category enum — keep old values (tip/news) for existing rows, add the new taxonomy.
        $catCol = @$conn->query("SHOW COLUMNS FROM community_posts LIKE 'category'");
        if ($catCol && ($row = $catCol->fetch_assoc())) {
            if (strpos($row['Type'], "'schemes'") === false) {
                @$conn->query(
                    "ALTER TABLE community_posts MODIFY COLUMN category
                     ENUM('question','crop','pest','market','schemes','tip','news','general') DEFAULT 'general'"
                );
            }
        }

        // ── 4. Saves (bookmarks) ──
        if (!agri_table_exists($conn, 'post_saves')) {
            @$conn->query(
                "CREATE TABLE post_saves (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    post_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_post_user (post_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 5. Reports ──
        if (!agri_table_exists($conn, 'post_reports')) {
            @$conn->query(
                "CREATE TABLE post_reports (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    post_id BIGINT UNSIGNED NOT NULL,
                    user_id BIGINT UNSIGNED NOT NULL,
                    reason VARCHAR(255) DEFAULT NULL,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uniq_post_user (post_id, user_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 6. Expert Advice Corner (admin-manageable) ──
        if (!agri_table_exists($conn, 'expert_advice')) {
            @$conn->query(
                "CREATE TABLE expert_advice (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    expert_user_id BIGINT UNSIGNED NOT NULL,
                    crop VARCHAR(100) DEFAULT NULL,
                    advice TEXT NOT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 7. Agricultural News / Market Updates (admin-manageable) ──
        if (!agri_table_exists($conn, 'agri_news')) {
            @$conn->query(
                "CREATE TABLE agri_news (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    category ENUM('market','weather','scheme','crop_advisory','news') DEFAULT 'news',
                    title VARCHAR(255) NOT NULL,
                    description TEXT DEFAULT NULL,
                    source VARCHAR(150) DEFAULT NULL,
                    link VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    published_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 8. Government Schemes (admin-manageable) ──
        if (!agri_table_exists($conn, 'government_schemes')) {
            @$conn->query(
                "CREATE TABLE government_schemes (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    name VARCHAR(200) NOT NULL,
                    eligibility TEXT DEFAULT NULL,
                    benefits TEXT DEFAULT NULL,
                    last_date DATE DEFAULT NULL,
                    official_link VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 9. Upcoming Agricultural Events (admin-manageable) ──
        if (!agri_table_exists($conn, 'agri_events')) {
            @$conn->query(
                "CREATE TABLE agri_events (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    title VARCHAR(200) NOT NULL,
                    location VARCHAR(150) DEFAULT NULL,
                    event_start DATE NOT NULL,
                    event_end DATE DEFAULT NULL,
                    description TEXT DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 10. Farmer Success Stories (admin-manageable) ──
        if (!agri_table_exists($conn, 'success_stories')) {
            @$conn->query(
                "CREATE TABLE success_stories (
                    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
                    farmer_name VARCHAR(150) NOT NULL,
                    district VARCHAR(100) DEFAULT NULL,
                    crop VARCHAR(100) DEFAULT NULL,
                    headline VARCHAR(255) NOT NULL,
                    description TEXT DEFAULT NULL,
                    income_change VARCHAR(50) DEFAULT NULL,
                    photo VARCHAR(255) DEFAULT NULL,
                    is_active TINYINT(1) DEFAULT 1,
                    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        }

        // ── 11. notifications.type — add 'community' for likes/comments/expert replies ──
        $typeCol = @$conn->query("SHOW COLUMNS FROM notifications LIKE 'type'");
        if ($typeCol && ($row = $typeCol->fetch_assoc())) {
            if (strpos($row['Type'], "'community'") === false) {
                @$conn->query(
                    "ALTER TABLE notifications MODIFY COLUMN type
                     ENUM('order','payment','advisory','market','promo','system','community') DEFAULT 'system'"
                );
            }
        }

        // ── 12. Soft-delete support — every admin-manageable table gets a
        // `deleted_at` timestamp. NULL = live/visible everywhere. A value
        // means: hidden from the public site, but still visible (with a
        // Restore button) in the admin panel. Nothing is ever hard-deleted
        // by the admin panel anymore. (products uses its own is_active
        // flag for this already, so it's excluded from this list.)
        $agriSoftDeleteTables = [
            'advisory', 'contact_messages', 'community_posts', 'comments',
            'krishi_bazaar', 'expert_advice', 'equipment', 'feedback',
            'sellers', 'newsletter_subscribers', 'coupons',
            'agri_news', 'government_schemes', 'agri_events', 'success_stories',
        ];
        foreach ($agriSoftDeleteTables as $__t) {
            if (agri_table_exists($conn, $__t) && !agri_column_exists($conn, $__t, 'deleted_at')) {
                @$conn->query("ALTER TABLE `$__t` ADD COLUMN deleted_at DATETIME DEFAULT NULL");
            }
        }
    }
}

// ── Soft-delete helpers, shared by every admin *_action.php file ──
if (!function_exists('agri_soft_delete')) {
    function agri_soft_delete($conn, $table, $id) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $conn->prepare("UPDATE `$table` SET deleted_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
if (!function_exists('agri_restore')) {
    function agri_restore($conn, $table, $id) {
        $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
        $stmt = $conn->prepare("UPDATE `$table` SET deleted_at = NULL WHERE id = ?");
        $stmt->bind_param("i", $id);
        return $stmt->execute();
    }
}
