-- ============================================================
--  Site Logo SQL — ROBUST VERSION (kono table structure e-i kaj korbe)
--  Database table: site_settings   |   Setting key: site_logo_img
--
--  NOTE: SQL diye logo set korle file-ta SERVER E physically thakte
--  hobe ei path e:  uploads/news/Sonbad Sonkolon.png
-- ============================================================


-- 1) TABLE STRUCTURE CHECK — ei query ta run kore output dekhun:
--    setting_key er upor PRIMARY/UNIQUE key ache kina (Key column: PRI/UNI dekhabe)
--    ar updated_at column ache kina.
DESCRIBE site_settings;


-- 2) DUPLICATE ROW CHECK — same key er ekadhik row thakle save "harano" dey:
SELECT setting_key, COUNT(*) AS rows_cnt
FROM site_settings
GROUP BY setting_key
HAVING COUNT(*) > 1;


-- 3) LOGO SET / UPDATE — SAFE VERSION (updated_at column lagbe NA,
--    duplicate thakle-o kaj korbe):
UPDATE site_settings
SET setting_value = 'uploads/news/Sonbad Sonkolon.png'
WHERE setting_key = 'site_logo_img';

-- Jodi table khali thake (UPDATE ei 0 row kheye fele), tokhon INSERT run korun:
INSERT INTO site_settings (setting_key, setting_value)
SELECT 'site_logo_img', 'uploads/news/Sonbad Sonkolon.png'
WHERE NOT EXISTS (SELECT 1 FROM site_settings WHERE setting_key = 'site_logo_img');


-- 4) RESULT CHECK — ei value ta dekhabe:
SELECT setting_key, setting_value FROM site_settings WHERE setting_key = 'site_logo_img';


-- 5) LOGO BAD DITE CHAILE (default "A" abar dekhabe):
-- UPDATE site_settings SET setting_value = '' WHERE setting_key = 'site_logo_img';
