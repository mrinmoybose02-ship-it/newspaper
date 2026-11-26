-- ============================================================================
-- Super Admin — store the FULL permission list in users.permissions
-- (so every permission shows in phpMyAdmin > users table > permissions column)
--
-- Why: assigning the "Super Admin" role saved permissions = NULL in the DB.
-- The app treated NULL as "full access", but nothing showed in phpMyAdmin.
-- This SQL writes the complete permission list as JSON, matching exactly what
-- admin_dashboard.php now saves (array_keys of $allPermissions).
--
-- Permission keys (14, current order in the app — Breaking News removed,
-- Visitor Monitor added):
--   dashboard, add_news, manage_news, categories, home_panels, breaking_speed,
--   social, contact, ads, videos, nse_market, visitor_stats, users, password
-- ============================================================================


-- STEP 0 (optional) — Check the current state before changing anything
SELECT id, username, role, is_approved, permissions
FROM users
ORDER BY id;


-- STEP 1 — Main fix: write the full permission list for every Super Admin account
-- Covers:
--   • accounts whose role column is already 'super_admin'
--   • older accounts whose permissions column is NULL (NULL meant Super Admin)
-- Pending (not approved) users are left untouched.
UPDATE users
SET permissions = '["dashboard","add_news","manage_news","categories","home_panels","breaking_speed","social","contact","ads","videos","nse_market","visitor_stats","users","password"]',
    role = 'super_admin'
WHERE role = 'super_admin'
   OR (permissions IS NULL AND is_approved = 1);


-- STEP 2 (optional but recommended) — Remove the old "breaking" key from other
-- users' saved permissions (the Breaking News permission option was removed).
-- Run this on MySQL 5.7+ / MariaDB 10.2+:
UPDATE users
SET permissions = JSON_REMOVE(permissions, JSON_UNQUOTE(JSON_SEARCH(permissions, 'one', 'breaking')))
WHERE permissions IS NOT NULL
  AND JSON_SEARCH(permissions, 'one', 'breaking') IS NOT NULL;

-- If STEP 2 gives an error (older MySQL 5.6 has no JSON functions),
-- run these two lines INSTEAD of it:
-- UPDATE users SET permissions = REPLACE(permissions, '"breaking",', '') WHERE permissions LIKE '%"breaking",%';
-- UPDATE users SET permissions = REPLACE(permissions, ',"breaking"', '')  WHERE permissions LIKE '%,"breaking"%';


-- STEP 3 — Verify: every Super Admin now shows the full list in phpMyAdmin
SELECT id, username, role, permissions
FROM users
ORDER BY id;
