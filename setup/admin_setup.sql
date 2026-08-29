-- =====================================================================
-- admin_setup.sql — run this ONCE in phpMyAdmin (or `mysql < admin_setup.sql`)
-- Creates a small, dedicated table for the marketplace Admin Panel login.
-- This is separate from your main `users` table on purpose, so it works
-- no matter how your site's regular farmer/seller login is built.
-- =====================================================================

CREATE TABLE IF NOT EXISTS admin_users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,   -- stores a hashed password, never plain text
    role VARCHAR(20) NOT NULL DEFAULT 'admin',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- After running this, open generate_admin_password.php in your browser
-- to create your actual admin username + password (it will give you the
-- exact INSERT statement to run next).
