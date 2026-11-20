-- ============================================================
--  SITE LOGO SQL (v4)
--  The site logo lives in its OWN dedicated folder: uploads/logo/
--  (never shared with news images / ads / festival logo files)
--  Current expected file: uploads/logo/Sonbad Sonkolon.png
-- ============================================================

-- 1) Check the current value
SELECT setting_key, setting_value FROM site_settings WHERE setting_key = 'site_logo_img';

-- 2) Duplicate-row check — if this returns cnt > 1, old rows WIN over new saves
--    and every save "looks lost" in the database. The admin panel fixes this
--    automatically on upload, but you can verify here.
SELECT setting_key, COUNT(*) AS cnt FROM site_settings
WHERE setting_key = 'site_logo_img' GROUP BY setting_key;

-- 3) Set / update the logo — BULLETPROOF way (works even with duplicate rows):
DELETE FROM site_settings WHERE setting_key = 'site_logo_img';
INSERT INTO site_settings (setting_key, setting_value, updated_at)
VALUES ('site_logo_img', 'uploads/logo/Sonbad Sonkolon.png', NOW());

--    If your table has NO updated_at column, use this instead:
-- INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_logo_img', 'uploads/logo/Sonbad Sonkolon.png');

-- 4) Clear the logo (reverts to the default "A") — same bulletproof way:
-- DELETE FROM site_settings WHERE setting_key = 'site_logo_img';
-- INSERT INTO site_settings (setting_key, setting_value) VALUES ('site_logo_img', '');

-- NOTE: the file must physically exist at uploads/logo/Sonbad Sonkolon.png
-- (web root -> uploads -> logo). After changing the DB, reload the admin
-- panel — the sidebar logo resolves automatically.
