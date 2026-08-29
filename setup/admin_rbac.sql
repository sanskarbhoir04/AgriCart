-- =====================================================================
-- database/admin_rbac.sql
-- AgriCart — Role-Based Access Control (RBAC) migration for the Admin
-- Panel.
--
-- WHAT THIS DOES
--   Adds a full permission system for the Admin Panel ONLY. It never
--   touches the `users` table's existing `role` column (farmer / seller
--   / buyer / customer / expert / admin) — that column keeps meaning
--   exactly what it already means on the storefront. Admin Panel roles
--   (Store Manager, Finance Manager, etc.) live entirely in the new
--   tables below and are linked to a `users` row only through
--   `admin_team_members.user_id`.
--
-- HOW TO RUN
--   mysql -u root agricart < database/admin_rbac.sql
--   -- or paste into phpMyAdmin's SQL tab on the `agricart` database.
--
-- SAFE TO RE-RUN
--   Every CREATE TABLE uses IF NOT EXISTS and every INSERT uses
--   INSERT IGNORE / ON DUPLICATE KEY UPDATE, so running this file twice
--   will not duplicate rows or error out.
-- =====================================================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------------
-- 0. Safety net: make sure `users` has the columns admin login and the
-- Team Management "Add Team Member" form need. These are added only if
-- missing, so this is safe to run against a database that already has
-- them (e.g. if admin/auth.php's username-login was already set up).
-- ---------------------------------------------------------------------
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'username');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN username VARCHAR(60) NULL UNIQUE AFTER full_name', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'alt_username');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN alt_username VARCHAR(60) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'profile_photo');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE users ADD COLUMN profile_photo VARCHAR(255) NULL', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------
-- 1. admin_roles — the list of Admin Panel roles (system + custom)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_roles (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    role_name      VARCHAR(100)    NOT NULL,
    role_slug      VARCHAR(100)    NOT NULL UNIQUE,
    description    VARCHAR(500)    DEFAULT NULL,
    is_system_role TINYINT(1)      NOT NULL DEFAULT 0,
    is_active      TINYINT(1)      NOT NULL DEFAULT 1,
    created_by     INT             DEFAULT NULL,
    created_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP       NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_role_slug (role_slug),
    KEY idx_role_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 2. admin_permissions — every permission key that exists in the system
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_permissions (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    permission_key  VARCHAR(100)  NOT NULL UNIQUE,
    module_name     VARCHAR(100)  NOT NULL,
    action_name     VARCHAR(100)  NOT NULL,
    description     VARCHAR(255)  DEFAULT NULL,
    created_at      TIMESTAMP     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_perm_module (module_name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 3. admin_role_permissions — which permissions each role has
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_role_permissions (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    role_id        INT NOT NULL,
    permission_id  INT NOT NULL,
    allowed        TINYINT(1) NOT NULL DEFAULT 1,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_role_permission (role_id, permission_id),
    CONSTRAINT fk_arp_role FOREIGN KEY (role_id) REFERENCES admin_roles(id) ON DELETE CASCADE,
    CONSTRAINT fk_arp_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 4. admin_team_members — links a `users` row to an Admin Panel role
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_team_members (
    id                  INT PRIMARY KEY AUTO_INCREMENT,
    user_id             INT NOT NULL,
    role_id             INT NOT NULL,
    department          VARCHAR(100) DEFAULT NULL,
    status              ENUM('active','inactive','suspended','expired') NOT NULL DEFAULT 'active',
    scope_type          ENUM('all','state','district','city','own_records') NOT NULL DEFAULT 'all',
    scope_value         VARCHAR(150) DEFAULT NULL,
    previous_site_role  VARCHAR(30) DEFAULT NULL,
    assigned_by         INT DEFAULT NULL,
    assigned_at         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    access_start_date   DATE DEFAULT NULL,
    access_expiry_date  DATE DEFAULT NULL,
    last_login          DATETIME DEFAULT NULL,
    failed_login_count  INT NOT NULL DEFAULT 0,
    locked_until        DATETIME DEFAULT NULL,
    created_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_team_user (user_id),
    KEY idx_team_role (role_id),
    KEY idx_team_status (status),
    CONSTRAINT fk_team_role FOREIGN KEY (role_id) REFERENCES admin_roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 5. admin_user_permissions — per-user allow/deny overrides
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_user_permissions (
    id               INT PRIMARY KEY AUTO_INCREMENT,
    admin_member_id  INT NOT NULL,
    permission_id    INT NOT NULL,
    permission_type  ENUM('allow','deny') NOT NULL DEFAULT 'allow',
    created_at       TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_member_permission (admin_member_id, permission_id),
    CONSTRAINT fk_aup_member FOREIGN KEY (admin_member_id) REFERENCES admin_team_members(id) ON DELETE CASCADE,
    CONSTRAINT fk_aup_permission FOREIGN KEY (permission_id) REFERENCES admin_permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 6. admin_activity_logs — audit trail of admin actions
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_activity_logs (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id  INT DEFAULT NULL,
    action         VARCHAR(100) NOT NULL,
    module         VARCHAR(100) DEFAULT NULL,
    record_id      INT DEFAULT NULL,
    description    VARCHAR(500) DEFAULT NULL,
    old_value      TEXT DEFAULT NULL,
    new_value      TEXT DEFAULT NULL,
    ip_address     VARCHAR(64) DEFAULT NULL,
    user_agent     VARCHAR(255) DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_log_user (admin_user_id),
    KEY idx_log_module (module),
    KEY idx_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 7. admin_login_logs — login / logout history
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_login_logs (
    id             INT PRIMARY KEY AUTO_INCREMENT,
    admin_user_id  INT DEFAULT NULL,
    login_status   ENUM('success','failed','locked','expired','inactive') NOT NULL,
    ip_address     VARCHAR(64) DEFAULT NULL,
    user_agent     VARCHAR(255) DEFAULT NULL,
    login_time     DATETIME DEFAULT NULL,
    logout_time    DATETIME DEFAULT NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_login_user (admin_user_id),
    KEY idx_login_time (login_time)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 8. admin_approval_requests — sensitive-action approval workflow
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_approval_requests (
    id              INT PRIMARY KEY AUTO_INCREMENT,
    requested_by    INT NOT NULL,
    action_type     VARCHAR(100) NOT NULL,
    module          VARCHAR(100) DEFAULT NULL,
    record_id       INT DEFAULT NULL,
    record_details  TEXT DEFAULT NULL,
    reason          VARCHAR(500) DEFAULT NULL,
    status          ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    decided_by      INT DEFAULT NULL,
    decision_note   VARCHAR(500) DEFAULT NULL,
    requested_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    decided_at      DATETIME DEFAULT NULL,
    KEY idx_appr_status (status),
    KEY idx_appr_requester (requested_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------------
-- 9. admin_notifications — Super Admin alert feed
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS admin_notifications (
    id          INT PRIMARY KEY AUTO_INCREMENT,
    type        VARCHAR(60) NOT NULL,
    title       VARCHAR(200) NOT NULL,
    message     VARCHAR(500) DEFAULT NULL,
    is_read     TINYINT(1) NOT NULL DEFAULT 0,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_notif_read (is_read)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- Safety net for installs that already ran an earlier version of this file
-- (before previous_site_role existed in the CREATE TABLE above).
SET @col_exists := (SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'admin_team_members' AND COLUMN_NAME = 'previous_site_role');
SET @sql := IF(@col_exists = 0, 'ALTER TABLE admin_team_members ADD COLUMN previous_site_role VARCHAR(30) NULL AFTER scope_value', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- =====================================================================
-- PREDEFINED ROLES
-- =====================================================================
INSERT INTO admin_roles (role_name, role_slug, description, is_system_role, is_active) VALUES
('Super Admin',              'super_admin',              'Full access to the entire Admin Panel.', 1, 1),
('Store Manager',            'store_manager',             'Manages Products, Sellers, Stock, Reviews and Coupons.', 1, 1),
('Order Manager',            'order_manager',             'Manages Orders, delivery status, cancellations and returns.', 1, 1),
('Rental Manager',           'rental_manager',            'Manages Equipment and Rental Bookings.', 1, 1),
('Krishi Bazaar Manager',    'krishi_bazaar_manager',     'Manages market prices, crops and verified farmers.', 1, 1),
('Advisory Manager',         'advisory_manager',          'Manages crop advisory content and requests.', 1, 1),
('Community Moderator',      'community_moderator',       'Moderates Agri-Connect posts and comments.', 1, 1),
('Customer Support Manager', 'customer_support_manager',  'Handles contact messages, feedback and complaints.', 1, 1),
('Finance Manager',          'finance_manager',           'Manages revenue, payouts, refunds and financial reports.', 1, 1),
('Marketing Manager',        'marketing_manager',         'Manages coupons, offers, banners and notifications.', 1, 1),
('Viewer / Analyst',         'viewer_analyst',            'Read-only access to dashboard and reports.', 1, 1)
ON DUPLICATE KEY UPDATE role_name = VALUES(role_name);

-- =====================================================================
-- PERMISSIONS CATALOG
-- =====================================================================
INSERT INTO admin_permissions (permission_key, module_name, action_name, description) VALUES
-- Dashboard
('dashboard.view',            'dashboard', 'view',            'View the admin dashboard'),
-- Products
('products.view',             'products', 'view',             'View products'),
('products.create',           'products', 'create',           'Add products'),
('products.edit',             'products', 'edit',             'Edit products'),
('products.delete',           'products', 'delete',           'Delete / deactivate products'),
('products.approve',          'products', 'approve',          'Approve or reject product listings'),
('products.export',           'products', 'export',           'Export product data'),
-- Orders
('orders.view',                'orders', 'view',               'View orders'),
('orders.update_status',       'orders', 'update_status',      'Update order status'),
('orders.cancel',              'orders', 'cancel',             'Cancel orders'),
('orders.return',              'orders', 'return',             'Process order returns'),
('orders.refund',              'orders', 'refund',             'Process order refunds'),
('orders.export',              'orders', 'export',             'Export order reports'),
-- Equipment
('equipment.view',             'equipment', 'view',             'View rental equipment'),
('equipment.create',           'equipment', 'create',           'Add rental equipment'),
('equipment.edit',             'equipment', 'edit',             'Edit rental equipment'),
('equipment.delete',           'equipment', 'delete',           'Delete rental equipment'),
('equipment.approve',          'equipment', 'approve',          'Approve rental equipment listings'),
-- Rental bookings
('rental_bookings.view',       'rental_bookings', 'view',        'View rental bookings'),
('rental_bookings.confirm',    'rental_bookings', 'confirm',     'Confirm rental bookings'),
('rental_bookings.cancel',     'rental_bookings', 'cancel',      'Cancel rental bookings'),
('rental_bookings.complete',   'rental_bookings', 'complete',    'Mark rentals completed'),
-- Users
('users.view',                 'users', 'view',                'View website users'),
('users.edit',                 'users', 'edit',                'Edit user details'),
('users.verify',               'users', 'verify',              'Verify user accounts'),
('users.block',                'users', 'block',               'Block user accounts'),
('users.delete',                'users', 'delete',              'Delete user accounts'),
('users.change_role',           'users', 'change_role',         'Change a website user role'),
-- Sellers
('sellers.view',                'sellers', 'view',              'View sellers'),
('sellers.verify',              'sellers', 'verify',            'Verify sellers'),
('sellers.approve',             'sellers', 'approve',           'Approve sellers'),
('sellers.block',               'sellers', 'block',             'Block sellers'),
-- Reviews
('reviews.view',                'reviews', 'view',              'View product reviews'),
('reviews.moderate',            'reviews', 'moderate',          'Moderate / delete product reviews'),
-- Krishi Bazaar
('bazaar.view',                 'bazaar', 'view',               'View Krishi Bazaar data'),
('bazaar.create',               'bazaar', 'create',             'Add market prices'),
('bazaar.edit',                 'bazaar', 'edit',               'Edit market prices'),
('bazaar.delete',               'bazaar', 'delete',             'Delete market price entries'),
('bazaar.approve',              'bazaar', 'approve',            'Approve buyer requirements / farmers'),
-- Advisory
('advisory.view',               'advisory', 'view',             'View advisory content'),
('advisory.create',             'advisory', 'create',           'Create advisory posts'),
('advisory.edit',               'advisory', 'edit',             'Edit advisory posts'),
('advisory.delete',             'advisory', 'delete',           'Delete advisory posts'),
('advisory.approve',            'advisory', 'approve',          'Approve expert advice'),
-- Community / Agri-Connect
('community.view',              'community', 'view',            'View Agri-Connect posts'),
('community.approve',           'community', 'approve',         'Approve posts'),
('community.moderate',          'community', 'moderate',        'Moderate comments'),
('community.delete',            'community', 'delete',          'Delete reported posts / comments'),
-- Support
('support.view',                'support', 'view',              'View contact messages / complaints'),
('support.reply',               'support', 'reply',             'Reply to messages'),
('support.resolve',             'support', 'resolve',           'Mark messages resolved'),
('feedback.manage',             'support', 'feedback_manage',   'Manage feedback entries'),
-- Finance
('finance.view',                'finance', 'view',              'View revenue and financial reports'),
('finance.refund',              'finance', 'refund',            'Process approved refunds'),
('finance.payout',              'finance', 'payout',            'Process approved seller payouts'),
('finance.export',              'finance', 'export',            'Export financial reports'),
-- Coupons / Marketing
('coupons.view',                'coupons', 'view',              'View coupons'),
('coupons.create',              'coupons', 'create',            'Create coupons'),
('coupons.edit',                'coupons', 'edit',              'Edit coupons / offers'),
('coupons.delete',              'coupons', 'delete',            'Delete coupons'),
('notifications.manage',        'marketing', 'notifications_manage', 'Schedule notifications'),
('banners.manage',              'marketing', 'banners_manage',  'Manage homepage banners'),
('newsletter.manage',           'marketing', 'newsletter_manage', 'Manage newsletter subscribers / sends'),
-- Team management
('team.view',                   'team', 'view',                 'View admin team members'),
('team.create',                 'team', 'create',               'Add admin team members'),
('team.edit',                   'team', 'edit',                 'Edit admin team members'),
('team.assign_role',            'team', 'assign_role',          'Assign or change an admin role'),
('team.change_permissions',     'team', 'change_permissions',   'Change role or user permissions'),
('team.disable',                'team', 'disable',              'Activate / deactivate / suspend a team member'),
('team.delete',                 'team', 'delete',               'Remove a team member''s admin access'),
-- Reports / settings
('reports.view',                'reports', 'view',              'View reports'),
('reports.export',              'reports', 'export',            'Export reports'),
('settings.view',               'settings', 'view',             'View system settings'),
('settings.manage',             'settings', 'manage',           'Change system settings'),
('activity_logs.view',          'settings', 'activity_logs_view', 'View admin activity logs')
ON DUPLICATE KEY UPDATE module_name = VALUES(module_name);

-- =====================================================================
-- DEFAULT ROLE → PERMISSION ASSIGNMENTS
-- =====================================================================

-- Super Admin — every permission
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1
FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'super_admin'
ON DUPLICATE KEY UPDATE allowed = 1;

-- Store Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'store_manager' AND p.permission_key IN (
  'dashboard.view','products.view','products.create','products.edit','products.delete','products.approve','products.export',
  'sellers.view','reviews.view','reviews.moderate',
  'coupons.view','coupons.create','coupons.edit','coupons.delete'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Order Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'order_manager' AND p.permission_key IN (
  'dashboard.view','orders.view','orders.update_status','orders.cancel','orders.return','orders.export'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Rental Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'rental_manager' AND p.permission_key IN (
  'dashboard.view','equipment.view','equipment.create','equipment.edit','equipment.delete','equipment.approve',
  'rental_bookings.view','rental_bookings.confirm','rental_bookings.cancel','rental_bookings.complete'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Krishi Bazaar Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'krishi_bazaar_manager' AND p.permission_key IN (
  'dashboard.view','bazaar.view','bazaar.create','bazaar.edit','bazaar.delete','bazaar.approve'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Advisory Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'advisory_manager' AND p.permission_key IN (
  'dashboard.view','advisory.view','advisory.create','advisory.edit','advisory.delete','advisory.approve'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Community Moderator
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'community_moderator' AND p.permission_key IN (
  'dashboard.view','community.view','community.approve','community.moderate','community.delete'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Customer Support Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'customer_support_manager' AND p.permission_key IN (
  'dashboard.view','support.view','support.reply','support.resolve','feedback.manage',
  'orders.view','users.view'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Finance Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'finance_manager' AND p.permission_key IN (
  'dashboard.view','finance.view','finance.refund','finance.payout','finance.export',
  'reports.view','reports.export'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Marketing Manager
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'marketing_manager' AND p.permission_key IN (
  'dashboard.view','coupons.view','coupons.create','coupons.edit','coupons.delete',
  'notifications.manage','banners.manage','newsletter.manage'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- Viewer / Analyst — read-only
INSERT INTO admin_role_permissions (role_id, permission_id, allowed)
SELECT r.id, p.id, 1 FROM admin_roles r JOIN admin_permissions p
WHERE r.role_slug = 'viewer_analyst' AND p.permission_key IN (
  'dashboard.view','reports.view','products.view','orders.view','equipment.view',
  'rental_bookings.view','bazaar.view','advisory.view','community.view','sellers.view','reviews.view'
) ON DUPLICATE KEY UPDATE allowed = 1;

-- =====================================================================
-- OPTIONAL: seed a default Super Admin team-member row for every
-- existing `users` row that already has role = 'admin' but has no
-- admin_team_members row yet. This preserves full access for whoever
-- is already using the Admin Panel today. Safe to run multiple times.
-- (admin/auth.php also does this automatically on next login, so this
-- step is just a belt-and-braces convenience if you'd rather run it
-- once from SQL instead.)
-- =====================================================================
INSERT INTO admin_team_members (user_id, role_id, department, status, assigned_by, access_start_date)
SELECT u.id,
       (SELECT id FROM admin_roles WHERE role_slug = 'super_admin' LIMIT 1),
       'Management',
       'active',
       u.id,
       CURDATE()
FROM users u
WHERE u.role = 'admin'
  AND NOT EXISTS (SELECT 1 FROM admin_team_members atm WHERE atm.user_id = u.id);
