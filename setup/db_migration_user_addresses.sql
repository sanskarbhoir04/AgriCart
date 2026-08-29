-- =====================================================
-- AgriCart — Address Book (multiple delivery addresses)
-- Run this once on your database before using the updated
-- register.php / marketplace.php / get_addresses.php / save_address.php /
-- delete_address.php files.
-- =====================================================

CREATE TABLE IF NOT EXISTS user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(40) NOT NULL DEFAULT 'Home',      -- e.g. Home, Farm, Office
    name VARCHAR(100) NOT NULL,
    mobile VARCHAR(10) NOT NULL,
    pincode VARCHAR(6) NOT NULL,
    address TEXT NOT NULL,
    is_default TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_addresses_user (user_id),
    CONSTRAINT fk_user_addresses_user
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- If your `users` table doesn't already have these columns (checked for in
-- marketplace.php with a try/catch, but good to confirm), add them too —
-- they hold the single "primary" address used to prefill checkout, kept in
-- sync with whichever address is marked is_default=1 in user_addresses.
-- ALTER TABLE users
--   ADD COLUMN saved_name VARCHAR(100) NULL,
--   ADD COLUMN saved_mobile VARCHAR(10) NULL,
--   ADD COLUMN saved_pincode VARCHAR(6) NULL,
--   ADD COLUMN saved_address TEXT NULL;
