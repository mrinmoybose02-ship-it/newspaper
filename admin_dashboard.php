<?php
date_default_timezone_set('Asia/Kolkata');

// 1. Calendar File ti Include Korlam
require_once __DIR__ . '/bengali_calendar.php';

// 2. Data Collection
 $todayBengali = BengaliCalendar::toBengaliDate();
 $activeFestival = BengaliFestivalCalendar::getActiveFestival();
 $allActiveFestivals = BengaliFestivalCalendar::getActiveFestivals(); // Sob active festival er list

// ========================================== ★ v4.1 Date-Safety Fix (Admin) ==========================================
// সঠিক পঞ্জিকা-তারিখ (index.php v4.1-এর সাথে হুবহু ম্যাচ): অষ্টমী ১৯ / নবমী ২০ / দশমী ২১ অক্টোবর ২০২৬।
// bengali_calendar.php পুরনো কপি থাকলেও দুর্গাপুজোর এই ৬ দিন কখনো ভুল দেখাবে না — এই ম্যাপের অগ্রাধিকার সবার উপরে।
$ADMIN_FEST_OVERRIDES = [
    '2026-10-16' => ['name' => 'শুভ দুর্গাপুজো',   'name_en' => 'Durga Puja',     'logo' => 'durgapujo.png',      'start' => '2026-10-16', 'end' => '2026-10-16', 'priority' => 1000],
    '2026-10-17' => ['name' => 'শুভ মহাষষ্ঠী',    'name_en' => 'Maha Shashthi',  'logo' => 'maha_shashthi.png',  'start' => '2026-10-17', 'end' => '2026-10-17', 'priority' => 1000],
    '2026-10-18' => ['name' => 'শুভ মহাসপ্তমী',   'name_en' => 'Maha Saptami',   'logo' => 'maha_saptami.png',   'start' => '2026-10-18', 'end' => '2026-10-18', 'priority' => 1000],
    '2026-10-19' => ['name' => 'শুভ মহা অষ্টমী',  'name_en' => 'Maha Ashtami',   'logo' => 'maha_ashtami.png',   'start' => '2026-10-19', 'end' => '2026-10-19', 'priority' => 1000],
    '2026-10-20' => ['name' => 'শুভ মহানবমী',     'name_en' => 'Maha Navami',    'logo' => 'maha_navami.png',    'start' => '2026-10-20', 'end' => '2026-10-20', 'priority' => 1000],
    '2026-10-21' => ['name' => 'শুভ বিজয়া দশমী', 'name_en' => 'Bijoya Dashami', 'logo' => 'bijoya_dashami.png', 'start' => '2026-10-21', 'end' => '2026-10-21', 'priority' => 1000],
];
$__adminToday = (new DateTime('today', new DateTimeZone('Asia/Kolkata')))->format('Y-m-d');
if (isset($ADMIN_FEST_OVERRIDES[$__adminToday])) {
    $__ov = $ADMIN_FEST_OVERRIDES[$__adminToday];
    $__ovEn = $__ov['name_en'];
    // override দিনটির ডুপ্লিকেট/দুর্গা এন্ট্রি লিস্ট থেকে বাদ
    $allActiveFestivals = array_merge([$__ov], array_values(array_filter((array)$allActiveFestivals, function ($f) use ($__ovEn) {
        $en = (string)($f['name_en'] ?? '');
        return stripos($en, $__ovEn) === false && stripos($en, 'durga') === false;
    })));
    $activeFestival = $__ov;
}

// ========================================== ★ v5 Live Image Preview (Admin) ==========================================
// ফোল্ডারে নতুন ছবি আপলোড করলে সেটাই অ্যাডমিনেও দেখাবে:
//  • একই ফাইলের একাধিক কপি (uploads/festivals ও festivals) থাকলে নতুন (mtime) কপি জেতে
//  • ভিন্ন নামের নতুন ভ্যারিয়েন্টও অটো-ডিটেক্ট (janmashtami বাদ)
//  • সব ছবির URL-এ ক্যাশ-বাস্টার (?v=mtime) — রিপ্লেস করলেই নতুন ছবি দেখায়
if (!function_exists('adminFestResolve')) {
    function adminFestResolve($rel) {
        $rel = trim((string)$rel);
        if ($rel === '' || filter_var($rel, FILTER_VALIDATE_URL)) return $rel;
        $bases = array_values(array_unique(['uploads/festivals', 'festivals', 'uploads/festival', 'festival']));
        $winner = ''; $winnerM = -1;
        foreach ($bases as $b) {
            $cand = $b . '/' . $rel;
            if (is_file(__DIR__ . '/' . $cand)) {
                $m = @filemtime(__DIR__ . '/' . $cand) ?: 0;
                if ($m > $winnerM) { $winnerM = $m; $winner = $cand; }
            }
        }
        if ($winner === '') $winner = is_file(__DIR__ . '/' . $rel) ? $rel : '';
        if ($winner === '') return '';
        $stem = preg_replace('/\.(png|jpe?g|gif|webp)$/i', '', basename($rel));
        if ($stem !== 'durgapujo') {
            $kw = preg_replace('/^(maha_|durga_|bijoya_)/i', '', $stem);
            $sub = dirname($rel) === '.' ? '' : '/' . dirname($rel); // ব্যানার হলে banners/ সাব-ফোল্ডারেও খোঁজা হয়
            foreach ($bases as $b) {
                if (!is_dir(__DIR__ . '/' . $b . $sub)) continue;
                foreach ((glob(__DIR__ . '/' . $b . $sub . '/*' . $kw . '*') ?: []) as $f) {
                    $bn = basename($f);
                    if (!is_file($f)) continue;
                    if (stripos($bn, 'janmashtami') !== false) continue;
                    if (!preg_match('/\.(png|jpe?g|gif|webp)$/i', $bn)) continue;
                    $m = @filemtime($f) ?: 0;
                    if ($m > $winnerM) { $winnerM = $m; $winner = $b . $sub . '/' . $bn; }
                }
            }
        }
        return $winner;
    }
}
if (!function_exists('adminFestUrl')) {
    function adminFestUrl($p) {
        $p = trim((string)$p);
        if ($p === '' || preg_match('~^https?://~i', $p)) return $p;
        $m = @filemtime(__DIR__ . '/' . ltrim($p, '/'));
        return $m ? $p . '?v=' . $m : $p;
    }
}
// ========================================== Live Image Preview Ends ==========================================
// ========================================== Bengali Calendar Class Ends ==========================================

if (!isset($_SESSION["user_id"])) { header("Location: ?page=admin_login"); exit; }

try {
    $userCheckStmt = $pdo->prepare("SELECT is_approved, permissions, role FROM users WHERE id = ?");
    $userCheckStmt->execute([$_SESSION["user_id"]]);
    $currentUserData = $userCheckStmt->fetch(PDO::FETCH_ASSOC);
    if (!$currentUserData || empty($currentUserData['is_approved'])) {
        session_unset(); session_destroy();
        header("Location: ?page=admin_login&error=not_approved"); exit;
    }
} catch (Exception $e) {
    session_unset(); session_destroy();
    header("Location: ?page=admin_login&error=db_error"); exit;
}

 $currentUserPerms = [];
try {
    $rawPerms = $currentUserData['permissions'] ?? null;
    if ($rawPerms === null) { $currentUserPerms = null; }
    else { $currentUserPerms = json_decode($rawPerms, true) ?: []; }
} catch (Exception $e) { $currentUserPerms = []; }

function hasPerm($perm) {
    global $currentUserPerms;
    if ($currentUserPerms === null) return true;
    return in_array($perm, $currentUserPerms);
}

 $allPermissions = [
    'dashboard' => ['label' => 'Dashboard', 'icon' => 'fas fa-gauge-high'],
    'add_news' => ['label' => 'Add News', 'icon' => 'fas fa-plus-circle'],
    'manage_news' => ['label' => 'Manage News', 'icon' => 'fas fa-newspaper'],
    'categories' => ['label' => 'Categories', 'icon' => 'fas fa-folder-tree'],
    'home_panels' => ['label' => 'Home Panels', 'icon' => 'fas fa-table-cells'],
    'breaking_speed' => ['label' => 'Breaking Speed', 'icon' => 'fas fa-gauge-high'],
    'social' => ['label' => 'Social Media', 'icon' => 'fas fa-share-nodes'],
    'contact' => ['label' => 'Contact Details', 'icon' => 'fas fa-address-book'],
    'ads' => ['label' => 'Advertisements', 'icon' => 'fas fa-rectangle-ad'],
    'videos' => ['label' => 'Videos', 'icon' => 'fas fa-video'],
    'nse_market' => ['label' => 'NSE Market', 'icon' => 'fas fa-chart-line'],
    'visitor_stats' => ['label' => 'Visitor Monitor', 'icon' => 'fas fa-chart-line'],
    'users' => ['label' => 'User Management', 'icon' => 'fas fa-users'],
    'password' => ['label' => 'Change Password', 'icon' => 'fas fa-key'],
];

// ★ Super Admin stored in DB: when a user's saved permission list covers EVERY available
//   permission, treat them as Super Admin too. (permissions = NULL still means Super Admin,
//   so accounts created before this change keep working unchanged.)
if (is_array($currentUserPerms) && !array_diff(array_keys($allPermissions), $currentUserPerms)) { $currentUserPerms = null; }

 $allRoles = [
    'super_admin' => ['label' => 'Super Admin', 'icon' => 'fas fa-crown', 'color' => 'var(--purple)', 'description' => 'Full access to everything.', 'permissions' => null],
    'editor' => ['label' => 'Editor', 'icon' => 'fas fa-user-pen', 'color' => 'var(--blue)', 'description' => 'Can add, edit, and manage news articles.', 'permissions' => ['dashboard', 'add_news', 'manage_news', 'password']],
    'publisher' => ['label' => 'Publisher', 'icon' => 'fas fa-newspaper', 'color' => 'var(--green)', 'description' => 'Can manage news, categories, and home panels.', 'permissions' => ['dashboard', 'add_news', 'manage_news', 'categories', 'home_panels', 'breaking_speed', 'password']],
    'ad_manager' => ['label' => 'Ad Manager', 'icon' => 'fas fa-rectangle-ad', 'color' => 'var(--gold)', 'description' => 'Can manage advertisements and ad embed codes.', 'permissions' => ['dashboard', 'ads', 'password']],
    'social_manager' => ['label' => 'Social Manager', 'icon' => 'fas fa-share-nodes', 'color' => '#25D366', 'description' => 'Can manage social media, contact details, and videos.', 'permissions' => ['dashboard', 'social', 'contact', 'videos', 'password']],
    'market_viewer' => ['label' => 'Market Viewer', 'icon' => 'fas fa-chart-line', 'color' => '#FF9933', 'description' => 'Can view NSE market data and dashboard only.', 'permissions' => ['dashboard', 'nse_market', 'password']],
    'viewer' => ['label' => 'Viewer', 'icon' => 'fas fa-eye', 'color' => 'var(--muted)', 'description' => 'Read-only access to dashboard only.', 'permissions' => ['dashboard', 'password']],
];

function getUserRole($permissionsRaw) {
    global $allRoles, $allPermissions;
    if ($permissionsRaw === null) return 'super_admin';
    $perms = json_decode($permissionsRaw, true) ?: [];
    // ★ Full permission list stored in DB = Super Admin (matches isSuperAdmin check below)
    if (is_array($perms) && !array_diff(array_keys($allPermissions), $perms)) return 'super_admin';
    foreach ($allRoles as $roleKey => $roleInfo) {
        if ($roleInfo['permissions'] === null) continue;
        if (count($perms) === count($roleInfo['permissions']) && empty(array_diff($perms, $roleInfo['permissions']))) return $roleKey;
    }
    return 'custom';
}

function roleColor($color) {
    $map = ['var(--purple)' => 'rgba(107,33,168,.1)', 'var(--blue)' => 'rgba(30,90,140,.1)', 'var(--green)' => 'rgba(45,80,22,.1)', 'var(--gold)' => 'rgba(212,160,23,.1)', 'var(--muted)' => 'rgba(107,97,85,.1)', '#25D366' => 'rgba(37,211,102,.1)', '#FF9933' => 'rgba(255,153,51,.1)'];
    return $map[$color] ?? 'rgba(107,33,168,.1)';
}

if (!function_exists('handleUpload')) {
    // ★ $preserveName (4th arg): when true the ORIGINAL file name is kept (e.g. "logo.png" stays "logo.png").
    //   Logo uploads (site brand + festival) now use the dedicated handleLogoUploadKeepName() below.
    //   Kept for backward compatibility — all other uploads keep the unique generated name.
    function handleUpload($inputName, $maxSizeMB = 5, $allowedTypes = null, $preserveName = false) {
        if ($allowedTypes === null) $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!isset($_FILES[$inputName])) return null;
        $file = $_FILES[$inputName];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $m = [1 => 'Server limit exceeded.', 2 => 'Form limit exceeded.', 3 => 'Partial upload.', 4 => 'No file selected.', 6 => 'Missing temp folder.', 7 => 'Failed to write to disk.', 8 => 'Extension blocked.'];
            return ["error" => $m[$file['error']] ?? 'Error code: ' . $file['error']];
        }
        if ($file['size'] > $maxSizeMB * 1024 * 1024) return ["error" => "Maximum {$maxSizeMB}MB. Yours: " . round($file['size'] / 1024 / 1024, 2) . "MB"];
        if ($file['size'] === 0) return ["error" => "File is empty."];
        $dm = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($dm, $allowedTypes)) return ["error" => "Type (" . $dm . ") is not allowed. Please use JPG, PNG, GIF, WebP."];
        $em = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        $ext = $em[$dm] ?? 'jpg';
        if ($dm !== 'image/svg+xml' && !@getimagesize($file['tmp_name'])) return ["error" => "Not a valid image."];
        $isAd = (strpos($inputName, 'ad_') === 0) || (strpos($inputName, 'festival_') === 0);
        $sub = $isAd ? 'ads' : 'news';
        $subDir = date('Y/m/') . '/';
        // Default naming (unchanged for news/ad/festival uploads): unique generated file name.
        $fn = ($isAd ? 'ad_' : 'news_') . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        // ★ Keep-original-name mode (site logo): e.g. "My Logo.PNG" is saved as "My Logo.PNG" — never renamed.
        //   Only unsafe characters are stripped; if a file with the same name already exists it is REPLACED.
        //   Saved directly in uploads/<sub>/ (no year/month sub-folder) so the logo path always stays the same.
        if ($preserveName) {
            $origBase = basename(str_replace('\\', '/', (string)($file['name'] ?? '')));
            $safe = trim((string)preg_replace('~[\\/:*?"<>|\x00-\x1F]~', '', $origBase));
            $__pi = ($safe !== '') ? pathinfo($safe) : [];
            $__stem = isset($__pi['filename']) ? trim((string)$__pi['filename']) : '';
            $__oExt = isset($__pi['extension']) ? strtolower((string)$__pi['extension']) : '';
            if ($__stem !== '' && $__stem !== '.' && $__stem !== '..') {
                if (mb_strlen($__stem) > 100) { $__stem = mb_substr($__stem, 0, 100); }
                $fn = ($__oExt !== '' && in_array($__oExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) ? $__stem . '.' . $__pi['extension'] : $__stem . '.' . $ext;
                $subDir = '';
            }
        }
        $dir = __DIR__ . '/../uploads/' . $sub . '/' . $subDir;
        if (!is_dir($dir)) { if (!@mkdir($dir, 0755, true)) return ["error" => "Failed to create directory."]; }
        $dest = $dir . $fn;
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) return ["error" => "Failed to save file."];
        @chmod($dest, 0644);
        return ["path" => "uploads/" . $sub . '/' . $subDir . $fn, "full_path" => $dest, "filename" => $fn, "mime" => $dm, "size" => $file['size']];
    }
}

// ★ v5: shared candidate list of upload folders, BEST-first. The project root is the folder one
//   level ABOVE this admin file (on XAMPP: C:\xampp\htdocs\Newspaper), so the site logo must save to
//   C:\xampp\htdocs\Newspaper\uploads\logo — NOT to C:\xampp\htdocs\uploads\logo. DOCUMENT_ROOT
//   (C:\xampp\htdocs) is only the WEB root; the site can live in a sub-folder of it, so it is now the
//   LAST choice (v3/v4 put it first — that saved the logo OUTSIDE the Newspaper project on XAMPP).
//   InfinityFree stays safe too: any candidate that would land OUTSIDE the web root (invisible files)
//   is skipped, and DOCUMENT_ROOT/uploads remains the fallback for htdocs-rooted installs.
if (!function_exists('logoUploadDirCandidates')) {
    function logoUploadDirCandidates($sub = 'logo') {
        $__c = [];
        $__dr = (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') ? rtrim(str_replace('\\', '/', $_SERVER['DOCUMENT_ROOT']), '/') : '';
        $__self = str_replace('\\', '/', __DIR__);
        $__par = dirname($__self);
        $__inside = function ($__p) use ($__dr) { return ($__dr !== '' && strpos($__p . '/', $__dr . '/') === 0); };
        // 1) project root (one level above the admin file) — C:\xampp\htdocs\Newspaper\uploads\<sub> on XAMPP.
        //    Accepted only when web-accessible (inside DOCUMENT_ROOT) so InfinityFree never saves invisible files.
        if ($__dr === '' || ($__inside($__par) && $__par !== $__dr)) { $__c[] = $__par . '/uploads/' . $sub . '/'; }
        // 2) this folder's own uploads/ — covers the admin file sitting directly in the project root.
        if ($__dr === '' || $__inside($__self)) { $__c[] = $__self . '/uploads/' . $sub . '/'; }
        // 3) web root uploads/ — fallback (InfinityFree when the admin file sits directly in htdocs).
        if ($__dr !== '') { $__c[] = $__dr . '/uploads/' . $sub . '/'; }
        // 4) + 5) unconditional last resorts — the real write test still decides.
        $__c[] = $__self . '/uploads/' . $sub . '/';
        $__c[] = $__par . '/uploads/' . $sub . '/';
        $__seen = []; $__u = [];
        foreach ($__c as $__d) { $__r = @realpath($__d); if ($__r === false) { $__r = $__d; } if (!isset($__seen[$__r])) { $__seen[$__r] = 1; $__u[] = $__d; } }
        return $__u;
    }
}

// ★ v6: DUPLICATE LOGO CHECK — decides whether a freshly uploaded logo (md5 of the temp file)
//   already exists. Compared, in order: (a) the site_logo_hash stored at the last successful
//   upload (original bytes — survives the auto-resize rewrite of the saved file), (b) the
//   currently-set logo file on disk, and (c) EVERY file already inside uploads/logo/ (catches
//   the same image being uploaded again under a different name). Returns a short description of
//   the match, or false when the upload is a genuinely new image.
if (!function_exists('logoUploadFindDuplicate')) {
    function logoUploadFindDuplicate($hash, $pdo, $currentLogoRel) {
        if ($hash === false || $hash === null || (string)$hash === '') return false;
        // (a) stored hash of the current logo's ORIGINAL bytes
        try {
            $st = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key='site_logo_hash'");
            if ($st->execute() !== false) {
                $h = $st->fetchColumn();
                if ($h !== false && $h !== null && (string)$h !== '' && (string)$h === (string)$hash) return 'the current site logo';
            }
        } catch (Exception $e) { /* site_settings missing — skip this check */ }
        // (b) the currently-set logo file itself
        $rel = ltrim((string)$currentLogoRel, '/');
        if ($rel !== '' && !preg_match('~^https?://~i', $rel)) {
            foreach ([__DIR__ . '/' . $rel, __DIR__ . '/../' . $rel, (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '' ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $rel : '')] as $p) {
                if ($p !== '' && @is_file($p) && @md5_file($p) === (string)$hash) return 'the current site logo file';
            }
        }
        // (c) every file already inside the logo folder(s) — same image, different filename
        foreach (logoUploadDirCandidates('logo') as $dir) {
            if (!is_dir($dir)) continue;
            foreach ((glob($dir . '*') ?: []) as $f) {
                if (!@is_file($f)) continue;
                $__bn = basename($f);
                if (strpos($__bn, 'wtest_') === 0 || $__bn === 'logo_debug.txt') continue;
                if (@md5_file($f) === (string)$hash) return '"' . $__bn . '" (already in uploads/logo)';
            }
        }
        return false;
    }
}

// ★ Dedicated LOGO uploader — ALWAYS keeps the ORIGINAL file name ("Sonbad Sonkolon.png" stays exactly that).
//   Deliberately NOT reusing handleUpload(): on many setups another included file (index.php / common.php)
//   defines handleUpload() FIRST, so this file's version is skipped by the function_exists guard and any
//   extra $preserveName flag is silently ignored — the logo would get renamed again. This copy is self-contained.
if (!function_exists('handleLogoUploadKeepName')) {
    function handleLogoUploadKeepName($inputName, $maxSizeMB = 5, $allowedTypes = null) {
        if ($allowedTypes === null) $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        if (!isset($_FILES[$inputName])) return null;
        $file = $_FILES[$inputName];
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $m = [1 => 'Server limit exceeded.', 2 => 'Form limit exceeded.', 3 => 'Partial upload.', 4 => 'No file selected.', 6 => 'Missing temp folder.', 7 => 'Failed to write to disk.', 8 => 'Extension blocked.'];
            return ["error" => $m[$file['error']] ?? 'Error code: ' . $file['error']];
        }
        if ($file['size'] > $maxSizeMB * 1024 * 1024) return ["error" => "Maximum {$maxSizeMB}MB. Yours: " . round($file['size'] / 1024 / 1024, 2) . "MB"];
        if ($file['size'] === 0) return ["error" => "File is empty."];
        $dm = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        if (!in_array($dm, $allowedTypes)) return ["error" => "Type (" . $dm . ") is not allowed. Please use JPG, PNG, GIF, WebP."];
        $em = ['image/jpeg' => 'jpg', 'image/jpg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp', 'image/svg+xml' => 'svg'];
        $ext = $em[$dm] ?? 'jpg';
        if ($dm !== 'image/svg+xml' && !@getimagesize($file['tmp_name'])) return ["error" => "Not a valid image."];
        // Original name is kept as-is — only unsafe characters are stripped, NEVER renamed.
        $origBase = basename(str_replace('\\', '/', (string)($file['name'] ?? '')));
        $safe = trim((string)preg_replace('~[\\/:*?"<>|\x00-\x1F]~', '', $origBase));
        $__pi = ($safe !== '') ? pathinfo($safe) : [];
        $__stem = isset($__pi['filename']) ? trim((string)$__pi['filename']) : '';
        $__oExt = isset($__pi['extension']) ? strtolower((string)$__pi['extension']) : '';
        if ($__stem !== '' && $__stem !== '.' && $__stem !== '..') {
            if (mb_strlen($__stem) > 100) { $__stem = mb_substr($__stem, 0, 100); }
            $fn = ($__oExt !== '' && in_array($__oExt, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'], true)) ? $__stem . '.' . $__pi['extension'] : $__stem . '.' . $ext;
        } else {
            // No usable original name (very rare) → timestamped fallback, never happens for a normal file.
            $fn = 'logo_' . time() . '_' . bin2hex(random_bytes(2)) . '.' . $ext;
        }
        // ★ v4: the site brand logo gets its OWN dedicated folder (uploads/logo/) — no other feature
        //   (news images / ads / festival logo) can ever overwrite, move or share the logo file.
        if ($inputName === 'site_logo_file') { $sub = 'logo'; }
        else { $isAd = (strpos($inputName, 'ad_') === 0) || (strpos($inputName, 'festival_') === 0); $sub = $isAd ? 'ads' : 'news'; }
        // Saved directly in uploads/<sub>/ — no year/month sub-folder, so the logo path always stays the same.
        // ★ v5: MULTIPLE upload locations, PROJECT ROOT first (see logoUploadDirCandidates above).
        //   XAMPP: saves into C:\xampp\htdocs\Newspaper\uploads\logo (the project's own uploads folder).
        //   The real write test below still falls back to the next location if the first is not writable.
        $__uniq = logoUploadDirCandidates($sub);
        $dir = ''; $dirErr = '';
        foreach ($__uniq as $__cd) {
            if (!is_dir($__cd)) { if (!@mkdir($__cd, 0755, true)) { @chmod(dirname($__cd), 0777); if (!@mkdir($__cd, 0777, true)) { $dirErr .= ' | CANNOT CREATE: ' . $__cd; continue; } } }
            $__tw = @file_put_contents($__cd . uniqid('wtest_'), 't');
            if ($__tw === false) { @chmod($__cd, 0777); $__tw = @file_put_contents($__cd . uniqid('wtest_'), 't'); }
            if ($__tw !== false) { foreach ((glob($__cd . 'wtest_*') ?: []) as $__wf) { @unlink($__wf); } $dir = $__cd; break; }
            $dirErr .= ' | NOT WRITABLE: ' . $__cd;
        }
        if ($dir === '') { return ["error" => "No writable uploads folder found! Tried:" . $dirErr . " — open File Manager, set the uploads folder permission to 755 or 777, then retry."]; }
        $dest = $dir . $fn;
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) { return ["error" => "Failed to save file into: " . $dir . " — set that folder's permission to 755 or 777, then retry."]; }
        @chmod($dest, 0644);
        return ["path" => "uploads/" . $sub . '/' . $fn, "full_path" => $dest, "filename" => $fn, "mime" => $dm, "size" => $file['size'], "dir_used" => $dir];
    }
}

// ★ Auto-resize an uploaded image to fit within $maxDim on its longest side.
//   Used for the site (sidebar brand) logo so big uploads don't waste disk or break layout.
//   Overwrites the file in place. Returns true on success, false if GD missing / not an image / already small.
if (!function_exists('resizeImageFit')) {
    function resizeImageFit($srcPath, $maxDim = 256, $quality = 92) {
        if (!function_exists('gd_info')) return false;
        $info = @getimagesize($srcPath);
        if (!$info) return false;                       // SVG or unreadable → skip (vector, no resize needed)
        $mime = $info['mime'] ?? '';
        $w = (int)($info[0] ?? 0); $h = (int)($info[1] ?? 0);
        if ($w <= 0 || $h <= 0) return false;
        if ($w <= $maxDim && $h <= $maxDim) return true; // already small enough — nothing to do
        $scale = min($maxDim / $w, $maxDim / $h);
        $nw = max(1, (int)round($w * $scale));
        $nh = max(1, (int)round($h * $scale));
        switch ($mime) {
            case 'image/jpeg': case 'image/jpg': $src = @imagecreatefromjpeg($srcPath); break;
            case 'image/png':  $src = @imagecreatefrompng($srcPath);  break;
            case 'image/gif':  $src = @imagecreatefromgif($srcPath);  break;
            case 'image/webp': $src = @imagecreatefromwebp($srcPath); break;
            default: return false;
        }
        if (!$src) return false;
        $dst = imagecreatetruecolor($nw, $nh);
        if ($mime === 'image/png' || $mime === 'image/gif' || $mime === 'image/webp') {
            imagealphablending($dst, false);
            imagesavealpha($dst, true);
            $trans = imagecolorallocatealpha($dst, 0, 0, 0, 127);
            imagefilledrectangle($dst, 0, 0, $nw, $nh, $trans);
        }
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = false;
        switch ($mime) {
            case 'image/jpeg': case 'image/jpg': $ok = imagejpeg($dst, $srcPath, $quality); break;
            case 'image/png':  $ok = imagepng($dst, $srcPath, 6); break;
            case 'image/gif':  $ok = imagegif($dst, $srcPath);   break;
            case 'image/webp': $ok = imagewebp($dst, $srcPath, $quality); break;
        }
        imagedestroy($src);
        imagedestroy($dst);
        @chmod($srcPath, 0644);
        return $ok;
    }
}

if (!function_exists('sendWebSocketNotification')) {
    function sendWebSocketNotification($news_id, $news_title) {
        $data = json_encode(['id' => $news_id, 'title' => $news_title]);
        $ch = curl_init('http://localhost:8081'); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($data)
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        return $response;
    }
}

// Create Date Change Requests Table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS date_change_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        news_id INT NOT NULL,
        requested_by INT NOT NULL,
        requested_date DATETIME NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// ★ Self-heal site_settings table (site logo / contact / festival settings live here).
//   1) Table missing → create it.  2) Old table without updated_at column → add it.
//   3) Old table WITHOUT a UNIQUE key on setting_key → duplicate rows pile up and every save
//      LOOKS lost (the old row keeps winning). Dedupe (keep newest) + add UNIQUE index.
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (
        setting_key VARCHAR(191) NOT NULL PRIMARY KEY,
        setting_value LONGTEXT NULL,
        updated_at DATETIME DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE site_settings ADD COLUMN updated_at DATETIME DEFAULT NULL");
} catch (Exception $e) {}
try {
    $pdo->exec("ALTER TABLE site_settings ADD UNIQUE KEY uk_site_settings_key (setting_key)");
} catch (Exception $e) {}
try {
    $__hasId = false;
    foreach ($pdo->query("SHOW COLUMNS FROM site_settings")->fetchAll(PDO::FETCH_ASSOC) as $__c) { if (strtolower((string)$__c['Field']) === 'id') { $__hasId = true; break; } }
    if ($__hasId) {
        $pdo->exec("DELETE s1 FROM site_settings s1 INNER JOIN site_settings s2 ON s1.setting_key = s2.setting_key AND s1.id < s2.id");
        try { $pdo->exec("ALTER TABLE site_settings ADD UNIQUE KEY uk_site_settings_key (setting_key)"); } catch (Exception $e) {}
    }
} catch (Exception $e) {}

// ★ Create Password Change Requests Table (admin approval workflow)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_change_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        new_password VARCHAR(255) NOT NULL,
        requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        status VARCHAR(20) DEFAULT 'pending',
        INDEX idx_pcr_status (status),
        INDEX idx_pcr_user (user_id)
    )");
} catch (Exception $e) {}

 $section = $_GET["section"] ?? "dashboard";
 $validSections = ["dashboard", "add", "edit", "manage", "articles", "epaper", "categories", "social", "contact", "watermark", "ads", "password", "api_subcategories", "nse_market", "api_nse_data", "api_ad_click", "videos", "home_panels", "breaking_speed", "users", "date_requests", "visitor_stats", "password_requests"];
if (!in_array($section, $validSections)) $section = "dashboard";

// Handle Date Change Request Approval/Rejection
if ($section === "date_requests" && $currentUserPerms === null) {
    if (isset($_GET['approve']) && isset($_GET['token'])) {
        if (hash_equals($_SESSION['csrf_token'] ?? "", $_GET['token'])) {
            $rid = (int)$_GET['approve'];
            $reqStmt = $pdo->prepare("SELECT * FROM date_change_requests WHERE id=? AND status='pending'");
            $reqStmt->execute([$rid]);
            $reqData = $reqStmt->fetch(PDO::FETCH_ASSOC);
            if ($reqData) {
                $pdo->prepare("UPDATE news SET created_at=? WHERE id=?")->execute([$reqData['requested_date'], $reqData['news_id']]);
                $pdo->prepare("UPDATE date_change_requests SET status='approved' WHERE id=?")->execute([$rid]);
                $umsg = "Date change request approved and applied successfully.";
            }
        }
    }
    if (isset($_GET['reject']) && isset($_GET['token'])) {
        if (hash_equals($_SESSION['csrf_token'] ?? "", $_GET['token'])) {
            $pdo->prepare("UPDATE date_change_requests SET status='rejected' WHERE id=?")->execute([(int)$_GET['reject']]);
            $umsg = "Date change request rejected.";
        }
    }
}

// ★ Handle Password Change Request Approve/Reject (Super Admin only)
if ($section === "password_requests" && $currentUserPerms === null) {
    if (isset($_GET['approve']) && isset($_GET['token'])) {
        if (hash_equals($_SESSION['csrf_token'] ?? "", $_GET['token'])) {
            $rid = (int)$_GET['approve'];
            $prStmt = $pdo->prepare("SELECT * FROM password_change_requests WHERE id=? AND status='pending'");
            $prStmt->execute([$rid]);
            $prData = $prStmt->fetch(PDO::FETCH_ASSOC);
            if ($prData) {
                $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$prData['new_password'], $prData['user_id']]);
                $pdo->prepare("UPDATE password_change_requests SET status='approved' WHERE id=?")->execute([$rid]);
                $umsg = "Password change request approved and applied successfully.";
            }
        }
    }
    if (isset($_GET['reject']) && isset($_GET['token'])) {
        if (hash_equals($_SESSION['csrf_token'] ?? "", $_GET['token'])) {
            $pdo->prepare("UPDATE password_change_requests SET status='rejected' WHERE id=?")->execute([(int)$_GET['reject']]);
            $umsg = "Password change request rejected.";
        }
    }
}

if ($section === "api_subcategories") {
    header('Content-Type: application/json; charset=utf-8'); @session_write_close();
    $cid = (int)($_GET["category_id"] ?? 0);
    if ($cid > 0) { try { $s = $pdo->prepare("SELECT id, name FROM subcategories WHERE category_id = ? ORDER BY name ASC"); $s->execute([$cid]); echo json_encode($s->fetchAll(PDO::FETCH_ASSOC)); } catch (Exception $e) { echo json_encode([]); } } else { echo json_encode([]); }
    exit;
}
if ($section === "api_ad_click") {
    header('Content-Type: application/json; charset=utf-8'); @session_write_close();
    $aid = (int)($_GET["ad_id"] ?? 0);
    if ($aid > 0) { try { $pdo->prepare("UPDATE advertisements SET clicks = clicks + 1 WHERE id = ? AND is_active = 1")->execute([$aid]); $r = $pdo->prepare("SELECT link_url FROM advertisements WHERE id = ? AND is_active = 1"); $r->execute([$aid]); $d = $r->fetch(); echo json_encode(["success" => true, "url" => $d["link_url"] ?? ""]); } catch (Exception $e) { echo json_encode(["success" => false]); } } else { echo json_encode(["success" => false]); }
    exit;
}
if ($section === "api_nse_data") {
    header('Content-Type: application/json; charset=utf-8'); @session_write_close();
    $cd = __DIR__ . '/../cache'; if (!is_dir($cd)) @mkdir($cd, 0755, true);
    $cf = $cd . '/nse_market.json'; $ck = $cd . '/nse_cookies.txt';
    $ct = 5;
    if (file_exists($cf) && (time() - filemtime($cf)) < $ct) { $c = json_decode(file_get_contents($cf), true); if ($c) { $c['_source'] = 'cache'; echo json_encode($c); exit; } }
    $data = ['indices' => [], 'gainers' => [], 'losers' => [], 'watchlist' => [], 'market_status' => 'closed', 'last_updated' => '', 'error' => ''];
    $ti = ['NIFTY 50', 'NIFTY BANK', 'NIFTY IT', 'NIFTY PHARMA', 'NIFTY NEXT 50', 'NIFTY MIDCAP 50', 'NIFTY AUTO', 'NIFTY METAL', 'NIFTY FMCG', 'NIFTY MEDIA', 'NIFTY REALTY', 'NIFTY ENERGY', 'NIFTY FIN SERVICE', 'NIFTY PSU BANK'];
    $ws = ['RELIANCE', 'TCS', 'INFY', 'HDFCBANK', 'ICICIBANK', 'HINDUNILVR', 'ITC', 'SBIN', 'BHARTIARTL', 'KOTAKBANK', 'LT', 'AXISBANK', 'BAJFINANCE', 'ASIANPAINT', 'MARUTI', 'TATAMOTORS', 'WIPRO', 'HCLTECH', 'SUNPHARMA', 'TITAN', 'ULTRACEMCO', 'NESTLEIND', 'POWERGRID', 'NTPC', 'ONGC', 'ADANIENT', 'ADANIPORTS', 'COALINDIA', 'JSWSTEEL', 'TATASTEEL'];
    $ua = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    if (!function_exists('nse_curl')) { function nse_curl($url, $ck, $ua, $headers = []) { $ch = curl_init($url); curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => $ck, CURLOPT_COOKIEJAR => $ck, CURLOPT_USERAGENT => $ua, CURLOPT_SSL_VERIFYPEER => false, CURLOPT_SSL_VERIFYHOST => false, CURLOPT_TIMEOUT => 15, CURLOPT_FOLLOWLOCATION => true, CURLOPT_HEADER => false, CURLOPT_ENCODING => "", CURLOPT_HTTPHEADER => $headers]); $res = @curl_exec($ch); curl_close($ch); return $res; } }
    nse_curl('https://www.nseindia.com', $ck, $ua, ['Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8', 'Accept-Language: en-US,en;q=0.5', 'Connection: keep-alive']);
    usleep(1000000);
    $api_headers = ['Accept: application/json, text/plain, */*', 'Accept-Language: en-US,en;q=0.9', 'Referer: https://www.nseindia.com/market-data/live-equity-market', 'X-Requested-With: XMLHttpRequest', 'Connection: keep-alive'];
    $ir = nse_curl('https://www.nseindia.com/api/allIndices', $ck, $ua, $api_headers);
    if ($ir) { $ij = json_decode($ir, true); if (isset($ij['data']) && is_array($ij['data'])) { foreach ($ij['data'] as $it) { $n = $it['indexSymbol'] ?? $it['name'] ?? ''; if (in_array($n, $ti) || in_array($it['name'] ?? '', $ti)) { $l = floatval($it['last'] ?? 0); $p = floatval($it['previousClose'] ?? 0); $c = isset($it['change']) ? floatval($it['change']) : round($l - $p, 2); $cp = isset($it['percentChange']) ? floatval($it['percentChange']) : ($p > 0 ? round(($c / $p) * 100, 2) : 0); $data['indices'][] = ['symbol' => $n, 'name' => $it['name'] ?? $n, 'last' => $l, 'change' => $c, 'changePercent' => $cp, 'high' => floatval($it['high'] ?? 0), 'low' => floatval($it['low'] ?? 0), 'open' => floatval($it['open'] ?? 0), 'previousClose' => $p]; } } } }
    usleep(500000);
    $sr = nse_curl('https://www.nseindia.com/api/equity-stockIndices?index=NIFTY%2050', $ck, $ua, $api_headers);
    $as = [];
    if ($sr) { $sj = json_decode($sr, true); if (isset($sj['data']) && is_array($sj['data'])) { foreach ($sj['data'] as $s) { if (!isset($s['companyName']) || empty($s['companyName']) || $s['symbol'] === 'NIFTY 50' || $s['symbol'] === 'NIFTY BANK') continue; $l = floatval($s['lastPrice'] ?? 0); $p = floatval($s['previousClose'] ?? 0); $c = isset($s['change']) ? floatval($s['change']) : round($l - $p, 2); $cp = isset($s['pChange']) ? floatval($s['pChange']) : ($p > 0 ? round(($c / $p) * 100, 2) : 0); $as[] = ['symbol' => $s['symbol'] ?? '', 'name' => $s['companyName'] ?? '', 'last' => $l, 'change' => $c, 'changePercent' => $cp, 'high' => floatval($s['dayHigh'] ?? 0), 'low' => floatval($s['dayLow'] ?? 0), 'open' => floatval($s['open'] ?? 0), 'previousClose' => $p, 'volume' => intval($s['totalTradedVolume'] ?? 0), 'value' => round(floatval($s['totalTradedValue'] ?? 0) / 10000000, 2)]; } usort($as, function ($a, $b) { return $b['changePercent'] <=> $a['changePercent']; }); $data['gainers'] = array_slice($as, 0, 10); $data['losers'] = array_slice(array_reverse($as), 0, 10); } }
    foreach ($ws as $sym) { $fd = null; foreach ($as as $st) { if ($st['symbol'] === $sym) { $fd = $st; break; } } if ($fd) { $data['watchlist'][] = $fd; continue; } usleep(200000); $qr = nse_curl('https://www.nseindia.com/api/quote-equity?symbol=' . urlencode($sym), $ck, $ua, $api_headers); if ($qr) { $qj = json_decode($qr, true); if (isset($qj['priceInfo'])) { $pi = $qj['priceInfo']; $l = floatval($pi['lastPrice'] ?? 0); $p = floatval($pi['previousClose'] ?? 0); $c = isset($pi['change']) ? floatval($pi['change']) : round($l - $p, 2); $cp = isset($pi['pChange']) ? floatval($pi['pChange']) : ($p > 0 ? round(($c / $p) * 100, 2) : 0); $data['watchlist'][] = ['symbol' => $sym, 'name' => $qj['info']['companyName'] ?? $sym, 'last' => $l, 'change' => $c, 'changePercent' => $cp, 'high' => floatval($pi['intraDayHighLow']['max'] ?? 0), 'low' => floatval($pi['intraDayHighLow']['min'] ?? 0), 'open' => floatval($pi['open'] ?? 0), 'previousClose' => $p, 'volume' => intval($pi['totalTradedVolume'] ?? 0), 'value' => 0]; } } }
    if (empty($as) && !empty($data['watchlist'])) { $tempAs = $data['watchlist']; usort($tempAs, function ($a, $b) { return $b['changePercent'] <=> $a['changePercent']; }); $data['gainers'] = array_slice($tempAs, 0, 10); $data['losers'] = array_slice(array_reverse($tempAs), 0, 10); }
    
    $nw = new DateTime('now', new DateTimeZone('Asia/Kolkata')); 
    $h = (int)$nw->format('G'); $mi = (int)$nw->format('i'); $d = (int)$nw->format('N'); $tv = $h * 60 + $mi;
    
    $nseHolidays = [
        '2024-01-26', '2024-03-08', '2024-03-25', '2024-03-29', '2024-04-11', '2024-04-17', '2024-05-01', '2024-05-20', '2024-06-17', '2024-08-15', '2024-10-02', '2024-11-01', '2024-11-15', '2024-12-25',
        '2025-02-26', '2025-03-14', '2025-03-31', '2025-04-10', '2025-04-14', '2025-04-18', '2025-05-01', '2025-08-15', '2025-08-27', '2025-10-02', '2025-10-21', '2025-10-31', '2025-11-05', '2025-11-26', '2025-12-25',
        '2026-01-26', '2026-02-15', '2026-03-04', '2026-03-20', '2026-03-30', '2026-04-02', '2026-04-03', '2026-05-01', '2026-09-14', '2026-09-21', '2026-10-02', '2026-10-10', '2026-10-29', '2026-11-04', '2026-11-15', '2026-12-25',
        '2027-01-26', '2027-02-04', '2027-03-24', '2027-03-30', '2027-04-01', '2027-04-23', '2027-04-26', '2027-05-01', '2027-09-04', '2027-10-02', '2027-10-19', '2027-10-25', '2027-11-07', '2027-11-12', '2027-12-25',
        '2028-01-26', '2028-02-22', '2028-03-12', '2028-03-30', '2028-04-08', '2028-04-14', '2028-05-01', '2028-08-15', '2028-09-22', '2028-10-02', '2028-10-08', '2028-10-19', '2028-11-11', '2028-12-25'
    ];
    
    $currentDateStr = $nw->format('Y-m-d');
    $isHoliday = in_array($currentDateStr, $nseHolidays);
    $isTradingHours = ($d >= 1 && $d <= 5 && $tv >= 555 && $tv <= 930 && !$isHoliday);
    $data['market_status'] = $isTradingHours ? 'open' : 'closed';
    
    $data['last_updated'] = $nw->format('d M Y, h:i:s A'); $data['fetched_at'] = time();
    if (file_exists($cf)) { $cachedData = json_decode(file_get_contents($cf), true); if ($cachedData) { if (empty($data['gainers']) && !empty($cachedData['gainers'])) $data['gainers'] = $cachedData['gainers']; if (empty($data['losers']) && !empty($cachedData['losers'])) $data['losers'] = $cachedData['losers']; if (empty($data['indices']) && !empty($cachedData['indices'])) $data['indices'] = $cachedData['indices']; if (empty($data['watchlist']) && !empty($cachedData['watchlist'])) $data['watchlist'] = $cachedData['watchlist']; } }
    if (!empty($data['gainers']) || !empty($data['losers']) || !empty($data['indices']) || !empty($data['watchlist'])) { @file_put_contents($cf, json_encode($data), LOCK_EX); } else { $data['error'] = 'Failed to fetch data from NSE. Please try again later.'; }
    echo json_encode($data); exit;
}

if (!isset($_SESSION['user_id'])) { header('Location: ?page=admin_login'); exit; }
try {
    $recheckStmt = $pdo->prepare("SELECT is_approved FROM users WHERE id = ?");
    $recheckStmt->execute([$_SESSION['user_id']]);
    $recheckData = $recheckStmt->fetch(PDO::FETCH_ASSOC);
    if (!$recheckData || empty($recheckData['is_approved'])) { session_unset(); session_destroy(); header("Location: ?page=admin_login&error=not_approved"); exit; }
} catch (Exception $e) { session_unset(); session_destroy(); header("Location: ?page=admin_login&error=db_error"); exit; }

 $umsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['user_action'])) {
    $action = $_POST['user_action'];
    if ($action === 'update_password') {
        $uid = (int)($_POST['target_user_id'] ?? 0); $newPass = trim($_POST['new_password'] ?? '');
        if ($uid > 0 && !empty($newPass)) { $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$newPass, $uid]); $umsg = "Password updated for user ID: {$uid}"; }
        else { $umsg = "Please provide a valid password."; }
    }
    if ($action === 'delete_user') {
        $uid = (int)($_POST['user_delete_id'] ?? 0);
        if ($uid > 0 && $uid != $_SESSION['user_id']) { $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$uid]); $umsg = 'User deleted successfully.'; }
        else { $umsg = 'You cannot delete your own account.'; }
    }
    if ($action === 'approve_user') {
        $uid = (int)($_POST['target_user_id'] ?? 0);
        if ($uid > 0) { $pdo->prepare("UPDATE users SET is_approved = 1, approved_at = NOW(), approved_by = ? WHERE id = ?")->execute([$_SESSION['user_id'], $uid]); $umsg = 'User approved. They can now login.'; }
    }
    if ($action === 'unapprove_user') {
        $uid = (int)($_POST['target_user_id'] ?? 0);
        if ($uid > 0 && $uid != $_SESSION['user_id']) { $pdo->prepare("UPDATE users SET is_approved = 0, approved_at = NULL, approved_by = NULL WHERE id = ?")->execute([$uid]); $umsg = 'User access revoked. They cannot login.'; }
        else { $umsg = 'You cannot revoke your own access.'; }
    }
    if ($action === 'update_permissions') {
        $uid = (int)($_POST['target_user_id'] ?? 0); $perms = $_POST['permissions'] ?? [];
        if (!is_array($perms)) $perms = [];
        if ($currentUserPerms !== null) { $umsg = 'Only Super Admin can change user permissions.'; }
        elseif ($uid > 0 && $uid != $_SESSION['user_id']) { $permJson = json_encode(array_values($perms)); $pdo->prepare("UPDATE users SET permissions = ? WHERE id = ?")->execute([$permJson, $uid]); $umsg = 'Permissions updated for user ID: ' . $uid . ' (role unchanged)'; }
        else { $umsg = 'You cannot change your own permissions.'; }
    }
    if ($action === 'update_role') {
        $uid = (int)($_POST['target_user_id'] ?? 0); $roleKey = $_POST['role'] ?? '';
        if ($currentUserPerms !== null) { $umsg = 'Only Super Admin can change user roles.'; }
        elseif (empty($roleKey) || $roleKey === 'custom') { $umsg = 'Select a role or use manual checkboxes below.'; }
        elseif (!isset($allRoles[$roleKey])) { $umsg = 'Invalid role selected.'; }
        elseif ($uid > 0 && $uid != $_SESSION['user_id']) { $rolePerms = $allRoles[$roleKey]['permissions']; // ★ Super Admin now stores the FULL permission list in the DB (was NULL) so every permission shows there; NULL from older accounts still means Super Admin
            $permJson = $rolePerms === null ? json_encode(array_keys($allPermissions)) : json_encode($rolePerms); $pdo->prepare("UPDATE users SET permissions = ?, role = ? WHERE id = ?")->execute([$permJson, $roleKey, $uid]); $umsg = 'Role changed to "' . $allRoles[$roleKey]['label'] . '" for user ID: ' . $uid; }
        else { $umsg = 'You cannot change your own role.'; }
    }
}

 $allUsers = [];
try { $allUsers = $pdo->query("SELECT * FROM users ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC); } catch (PDOException $e) {}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_ads'])) {
    foreach (['ad_bottom_1_embed', 'ad_bottom_2_embed'] as $key) { $value = $_POST[$key] ?? ''; $stmt = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)"); $stmt->execute([$key, $value]); }
    echo "<script>alert('Advertisement embed codes updated successfully!');</script>";
}
 $adKeys = ['ad_bottom_1_embed', 'ad_bottom_2_embed']; $currentAds = [];
try { $in = implode(",", array_fill(0, count($adKeys), "?")); $st = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($in)"); $st->execute($adKeys); $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR); foreach ($adKeys as $k) { $currentAds[$k] = $rows[$k] ?? ""; } } catch (Exception $e) { foreach ($adKeys as $k) { $currentAds[$k] = ""; } }

 $fullName = $_SESSION["full_name"] ?? $_SESSION["username"] ?? "Admin";
 $perPage = 15; $currentPage = max(1, (int)($_GET["p"] ?? 1));
 $totalNews = 0; $publishedNews = 0; $draftNews = 0; $featuredNews = 0;
 $totalCats = 0; $totalBreaking = 0; $totalAds = 0; $activeAds = 0;
 $totalClicks = 0; $totalImpressions = 0; $recentNews = []; $catStats = [];

try { $totalNews = (int)$pdo->query("SELECT COUNT(*) FROM news")->fetchColumn(); } catch (Exception $e) {}
try { $publishedNews = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status='published'")->fetchColumn(); } catch (Exception $e) {}
try { $draftNews = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE status='draft'")->fetchColumn(); } catch (Exception $e) {}
try { $featuredNews = (int)$pdo->query("SELECT COUNT(*) FROM news WHERE is_featured=1")->fetchColumn(); } catch (Exception $e) {}
try { $totalCats = (int)$pdo->query("SELECT COUNT(*) FROM categories")->fetchColumn(); } catch (Exception $e) {}
try { $totalBreaking = (int)$pdo->query("SELECT COUNT(*) FROM breaking_news")->fetchColumn(); } catch (Exception $e) {}
try { $totalAds = (int)$pdo->query("SELECT COUNT(*) FROM advertisements")->fetchColumn(); } catch (Exception $e) {}
try { $activeAds = (int)$pdo->query("SELECT COUNT(*) FROM advertisements WHERE is_active=1")->fetchColumn(); } catch (Exception $e) {}
try { $ast = $pdo->query("SELECT COALESCE(SUM(clicks),0) as tc, COALESCE(SUM(impressions),0) as ti FROM advertisements")->fetch(); $totalClicks = (int)$ast["tc"]; $totalImpressions = (int)$ast["ti"]; } catch (Exception $e) {}

try { $recentNews = $pdo->query("SELECT n.id,n.title,n.status,n.is_featured,n.created_at,n.updated_at,c.name as category_name,GROUP_CONCAT(DISTINCT sc.name SEPARATOR ', ') as subcategory_name FROM news n LEFT JOIN categories c ON n.category_id=c.id LEFT JOIN news_subcategories ns ON n.id=ns.news_id LEFT JOIN subcategories sc ON ns.subcategory_id=sc.id GROUP BY n.id ORDER BY n.created_at DESC LIMIT 8")->fetchAll(); } catch (Exception $e) { try { $recentNews = $pdo->query("SELECT n.id,n.title,n.status,n.is_featured,n.created_at,n.updated_at,c.name as category_name FROM news n LEFT JOIN categories c ON n.category_id=c.id ORDER BY n.created_at DESC LIMIT 8")->fetchAll(); } catch (Exception $e2) { $recentNews = []; } }
try { $catStats = $pdo->query("SELECT c.name, COUNT(n.id) as cnt FROM categories c LEFT JOIN news n ON c.id=n.category_id GROUP BY c.id ORDER BY cnt DESC LIMIT 10")->fetchAll(); } catch (Exception $e) { $catStats = []; }

 $allSubcategories = [];
try { $subRows = $pdo->query("SELECT s.*, c.name as category_name FROM subcategories s LEFT JOIN categories c ON s.category_id=c.id ORDER BY s.category_id, s.name ASC")->fetchAll(); foreach ($subRows as $sr) { $allSubcategories[$sr["category_id"]][] = $sr; } } catch (Exception $e) {}

if (!function_exists('getNewsSubcategoryIds')) { function getNewsSubcategoryIds($pdo, $nid) { try { $s = $pdo->prepare("SELECT subcategory_id FROM news_subcategories WHERE news_id=?"); $s->execute([$nid]); return array_column($s->fetchAll(), 'subcategory_id'); } catch (Exception $e) { return []; } } }
if (!function_exists('saveNewsSubcategories')) { function saveNewsSubcategories($pdo, $nid, $sids) { $pdo->prepare("DELETE FROM news_subcategories WHERE news_id=?")->execute([$nid]); if (!is_array($sids)) $sids = []; if (!empty($sids)) { $st = $pdo->prepare("INSERT IGNORE INTO news_subcategories (news_id,subcategory_id) VALUES (?,?)"); foreach ($sids as $sid) { $sid = (int)$sid; if ($sid > 0) $st->execute([$nid, $sid]); } } } }
if (!function_exists('loadSettings')) { function loadSettings($pdo, $keys) { $v = []; try { $in = implode(",", array_fill(0, count($keys), "?")); $st = $pdo->prepare("SELECT setting_key,setting_value FROM site_settings WHERE setting_key IN ($in)"); $st->execute($keys); $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR); foreach ($keys as $k) { $v[$k] = $rows[$k] ?? ""; } } catch (Exception $e) { foreach ($keys as $k) { $v[$k] = ""; } } return $v; } }
if (!function_exists('saveSettings')) {
    function saveSettings($pdo, $data) {
        try {
            $st = $pdo->prepare("INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()");
            foreach ($data as $k => $val) { $st->execute([$k, $val, $val]); }
        } catch (Exception $e) {
            // ★ Fallback for older servers whose site_settings table has no updated_at column — save anyway.
            $st = $pdo->prepare("INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)");
            foreach ($data as $k => $val) { $st->execute([$k, $val]); }
        }
    }
}

// ========================================== ★ Watermark Setting (brand your own news images + restore originals) ==========================================
// নিজের ছবিতে নিজের ওয়াটারমার্ক বসানো/সরানোর টুল। ওয়াটারমার্ক বসানোর আগে প্রতিটি ছবির
// অরিজিনাল কপি '<file>.orig' নামে ব্যাকআপ রাখা হয় — তাই Remove মানে অরিজিনাল restore (১০০% reversible)।
if (!function_exists('wmDefaults')) { function wmDefaults() { return [
    'wm_enabled' => '0', 'wm_apply_new' => '1', 'wm_type' => 'text',
    'wm_image' => '', 'wm_text' => 'Songbad Songolon', 'wm_font_size' => '24',
    'wm_color' => '#FFFFFF', 'wm_opacity' => '35', 'wm_position' => 'bottom-right',
    'wm_margin' => '16', 'wm_scale' => '25',
]; } }
if (!function_exists('wmGetSettings')) { function wmGetSettings($pdo) { $d = wmDefaults(); $v = loadSettings($pdo, array_keys($d)); foreach ($d as $k => $def) { if (!isset($v[$k]) || $v[$k] === '' || $v[$k] === null) $v[$k] = $def; } return $v; } }
if (!function_exists('wmResolvePath')) { function wmResolvePath($rel) { $rel = ltrim((string)$rel, '/'); if ($rel === '' || preg_match('~^https?://~i', $rel)) return ''; foreach ([__DIR__ . '/../' . $rel, __DIR__ . '/' . $rel] as $__p) { if (is_file($__p)) return $__p; } return ''; } }
if (!function_exists('wmImgLoad')) { function wmImgLoad($path) { $info = @getimagesize($path); if (!$info) return null; switch ($info['mime'] ?? '') { case 'image/jpeg': case 'image/jpg': return @imagecreatefromjpeg($path); case 'image/png': return @imagecreatefrompng($path); case 'image/gif': return @imagecreatefromgif($path); case 'image/webp': return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null; } return null; } }
if (!function_exists('wmImgSave')) { function wmImgSave($im, $path) { $info = @getimagesize($path); $mime = $info['mime'] ?? 'image/jpeg'; if ($mime === 'image/png') { imagesavealpha($im, true); return imagepng($im, $path, 6); } if ($mime === 'image/gif') { return imagegif($im, $path); } if ($mime === 'image/webp') { if (!function_exists('imagewebp')) return false; return imagewebp($im, $path, 90); } return imagejpeg($im, $path, 90); } }
if (!function_exists('wmFindFont')) { function wmFindFont() { static $f = null; if ($f !== null) return $f; $cands = []; $dirs = ['/usr/share/fonts/truetype/', '/usr/share/fonts/', '/usr/share/fonts/TTF/', __DIR__ . '/fonts/']; foreach ($dirs as $d) { if (!is_dir($d)) continue; foreach ((array)glob($d . '*.ttf') as $c) $cands[] = $c; foreach ((array)glob($d . '*/*.ttf') as $c) $cands[] = $c; foreach ((array)glob($d . '*.otf') as $c) $cands[] = $c; } foreach ($cands as $c) { if (stripos(basename($c), 'bengali') !== false) { $f = $c; return $f; } } foreach ($cands as $c) { $bn = basename($c); if (stripos($bn, 'bold') !== false && (stripos($bn, 'dejavu') !== false || stripos($bn, 'noto') !== false || stripos($bn, 'liberation') !== false || stripos($bn, 'freefont') !== false)) { $f = $c; return $f; } } foreach ($cands as $c) { $bn = basename($c); if (stripos($bn, 'dejavusans') !== false || stripos($bn, 'notosans') !== false || stripos($bn, 'liberation') !== false) { $f = $c; return $f; } } $f = $cands[0] ?? ''; return $f; } }
if (!function_exists('wmScaleAlpha')) { function wmScaleAlpha($im, $op) { $w = imagesx($im); $h = imagesy($im); imagealphablending($im, false); imagesavealpha($im, true); for ($y = 0; $y < $h; $y++) { for ($x = 0; $x < $w; $x++) { $c = imagecolorat($im, $x, $y); $a = ($c >> 24) & 0x7F; $a2 = (int)round(127 - (127 - $a) * $op); imagesetpixel($im, $x, $y, imagecolorallocatealpha($im, ($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF, $a2)); } } } }
if (!function_exists('wmPrepareStamp')) { function wmPrepareStamp($s, $tw, $th) {
    $op = max(0, min(100, (int)$s['wm_opacity'])) / 100; if ($op <= 0) return null;
    if (($s['wm_type'] ?? '') === 'image' && !empty($s['wm_image'])) {
        $p = wmResolvePath($s['wm_image']); if ($p === '') return null;
        $src = wmImgLoad($p); if (!$src) return null;
        $__ti = imagecolortransparent($src);
        if ($__ti >= 0) { // palette (GIF) transparency → convert to real alpha, otherwise resampling renders black
            imagealphablending($src, false); imagesavealpha($src, true);
            $__sw = imagesx($src); $__sh = imagesy($src);
            $__tc = imagecolorsforindex($src, $__ti);
            for ($yy = 0; $yy < $__sh; $yy++) { for ($xx = 0; $xx < $__sw; $xx++) { if (imagecolorat($src, $xx, $yy) === $__ti) { imagesetpixel($src, $xx, $yy, imagecolorallocatealpha($src, $__tc['red'], $__tc['green'], $__tc['blue'], 127)); } } }
        }
        $sw = imagesx($src); $sh = imagesy($src);
        $ww = (int)round($tw * max(5, min(60, (int)$s['wm_scale'])) / 100);
        $ww = max(16, min(800, $ww)); $wh = max(8, (int)round($sh * $ww / max(1, $sw)));
        $st = imagecreatetruecolor($ww, $wh);
        imagealphablending($st, false); imagesavealpha($st, true);
        imagefilledrectangle($st, 0, 0, $ww, $wh, imagecolorallocatealpha($st, 0, 0, 0, 127));
        imagecopyresampled($st, $src, 0, 0, 0, 0, $ww, $wh, $sw, $sh);
        imagedestroy($src);
        wmScaleAlpha($st, $op);
        return ['res' => $st, 'w' => $ww, 'h' => $wh];
    }
    $txt = trim((string)($s['wm_text'] ?? '')); if ($txt === '') return null;
    $fs = max(8, min(96, (int)$s['wm_font_size']));
    $hex = preg_replace('~[^0-9a-fA-F]~', '', (string)$s['wm_color']); if (strlen($hex) !== 6) $hex = 'FFFFFF';
    $r = hexdec(substr($hex, 0, 2)); $g = hexdec(substr($hex, 2, 2)); $b = hexdec(substr($hex, 4, 2));
    $alpha = (int)round(127 * (1 - $op));
    $font = wmFindFont();
    if ($font !== '' && function_exists('imagettftext')) {
        $box = imagettfbbox($fs, 0, $font, $txt);
        $cw = (int)abs($box[4] - $box[0]) + 10; $ch = (int)abs($box[1] - $box[5]) + 12;
        if ($cw < 10 || $ch < 10) return null;
        $st = imagecreatetruecolor($cw, $ch);
        imagealphablending($st, false); imagesavealpha($st, true);
        imagefilledrectangle($st, 0, 0, $cw, $ch, imagecolorallocatealpha($st, 0, 0, 0, 127));
        imagealphablending($st, true);
        imagettftext($st, $fs, 0, 5, (int)abs($box[5]) + 6, imagecolorallocatealpha($st, $r, $g, $b, $alpha), $font, $txt);
        imagealphablending($st, false);
        return ['res' => $st, 'w' => $cw, 'h' => $ch];
    }
    if (!function_exists('imagestring')) return null;
    $n = $fs <= 12 ? 1 : ($fs <= 16 ? 2 : ($fs <= 22 ? 3 : ($fs <= 30 ? 4 : 5)));
    $cw = imagefontwidth($n) * max(1, strlen($txt)) + 8; $ch = imagefontheight($n) + 8;
    $st = imagecreatetruecolor((int)$cw, (int)$ch);
    imagealphablending($st, false); imagesavealpha($st, true);
    imagefilledrectangle($st, 0, 0, (int)$cw, (int)$ch, imagecolorallocatealpha($st, 0, 0, 0, 127));
    imagealphablending($st, true);
    imagestring($st, $n, 4, 4, $txt, imagecolorallocatealpha($st, $r, $g, $b, $alpha));
    imagealphablending($st, false);
    return ['res' => $st, 'w' => (int)$cw, 'h' => (int)$ch];
} }
if (!function_exists('wmStampPosition')) { function wmStampPosition($pos, $tw, $th, $ww, $wh, $m) { $m = max(0, (int)$m); $pos = (string)$pos; if (strpos($pos, 'left') !== false) { $x = $m; } elseif (strpos($pos, 'right') !== false) { $x = $tw - $ww - $m; } else { $x = (int)round(($tw - $ww) / 2); } if (strpos($pos, 'top') !== false) { $y = $m; } elseif (strpos($pos, 'bottom') !== false) { $y = $th - $wh - $m; } else { $y = (int)round(($th - $wh) / 2); } return [max(0, $x), max(0, $y)]; } }
if (!function_exists('wmApplyToImage')) { function wmApplyToImage($absPath, $s) {
    if (empty($s['wm_enabled']) || $s['wm_enabled'] !== '1') return ['skip' => 'Watermark disabled'];
    if (!function_exists('gd_info')) return ['err' => 'GD image library not available'];
    $absPath = (string)$absPath; if ($absPath === '' || !is_file($absPath)) return ['skip' => 'file missing'];
    $info = @getimagesize($absPath); if (!$info) return ['skip' => 'not a raster image'];
    $mime = $info['mime'] ?? '';
    if ($mime === 'image/gif') return ['skip' => 'GIF (animation preserved)'];
    if ($mime === 'image/svg+xml') return ['skip' => 'SVG vector'];
    $im = wmImgLoad($absPath); if (!$im) return ['err' => 'could not decode image'];
    $bak = $absPath . '.orig';
    if (!is_file($bak) && !@copy($absPath, $bak)) { imagedestroy($im); return ['err' => 'could not create original backup']; }
    $stamp = wmPrepareStamp($s, imagesx($im), imagesy($im));
    if (!$stamp) { imagedestroy($im); return ['err' => 'watermark content missing (logo/text not configured)']; }
    list($x, $y) = wmStampPosition($s['wm_position'], imagesx($im), imagesy($im), $stamp['w'], $stamp['h'], $s['wm_margin']);
    imagealphablending($im, true); imagesavealpha($im, true);
    imagecopy($im, $stamp['res'], $x, $y, 0, 0, $stamp['w'], $stamp['h']);
    $ok = wmImgSave($im, $absPath);
    imagedestroy($im); imagedestroy($stamp['res']);
    @chmod($absPath, 0644);
    return $ok ? ['ok' => true] : ['err' => 'could not save image'];
} }
if (!function_exists('wmRestoreOriginal')) { function wmRestoreOriginal($absPath) { $bak = $absPath . '.orig'; if (!is_file($bak)) return ['skip' => 'no original backup']; if (!@copy($bak, $absPath)) return ['err' => 'restore failed']; @unlink($bak); @chmod($absPath, 0644); return ['ok' => true]; } }
$wmVals = wmGetSettings($pdo);

// ★ Watermark feature rollout flag — when false, the sidebar section shows "Under Construction"
//   and the engine stays fully dormant (no auto-stamping, no bulk actions, no preview).
//   Set to true to activate the complete Watermark Setting UI.
$WM_FEATURE_LIVE = false;

// ★ Watermark live preview — streams a watermarked sample (works on a temp copy, never touches real files)
if ($section === "watermark" && $WM_FEATURE_LIVE && isset($_GET["preview"]) && hasPerm('categories')) {
    $__wmS = wmGetSettings($pdo);
    $__srcAbs = '';
    try { $__wmRows = $pdo->query("SELECT image FROM news WHERE image<>'' ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { $__wmRows = []; }
    foreach ((array)$__wmRows as $__im) { $__p = wmResolvePath((string)$__im); if ($__p !== '') { $__srcAbs = $__p; break; } }
    $__tmp = tempnam(sys_get_temp_dir(), 'wmprv');
    if ($__srcAbs !== '') { @copy($__srcAbs, $__tmp); }
    if (!is_file($__tmp) || !@getimagesize($__tmp)) { $__cv = imagecreatetruecolor(640, 400); imagefilledrectangle($__cv, 0, 0, 639, 399, imagecolorallocate($__cv, 41, 44, 51)); imagejpeg($__cv, $__tmp, 90); imagedestroy($__cv); }
    $__res = wmApplyToImage($__tmp, $__wmS);
    $__pm = @getimagesize($__tmp)['mime'] ?? 'image/jpeg';
    header('Content-Type: ' . $__pm);
    header('Cache-Control: no-store, must-revalidate');
    header('Pragma: no-cache');
    readfile($__tmp);
    @unlink($__tmp); @unlink($__tmp . '.orig');
    exit;
}

 $adPositions = [
    "header_banner" => ["label" => "Header Banner", "icon" => "fas fa-window-maximize", "color" => "#C41E3A"],
    "content_top" => ["label" => "Top of Content", "icon" => "fas fa-arrow-up", "color" => "#1E5A8C"],
    "after_1st_para" => ["label" => "After 1st Paragraph", "icon" => "fas fa-paragraph", "color" => "#6B21A8"],
    "after_2nd_para" => ["label" => "After 2nd Paragraph", "icon" => "fas fa-paragraph", "color" => "#7C3AED"],
    "after_3rd_para" => ["label" => "After 3rd Paragraph", "icon" => "fas fa-paragraph", "color" => "#8B5CF6"],
    "content_middle" => ["label" => "Middle of Content", "icon" => "fas fa-arrows-up-down", "color" => "#D4A017"],
    "content_bottom" => ["label" => "Bottom of Content", "icon" => "fas fa-arrow-down", "color" => "#2D5016"],
    "sidebar_top" => ["label" => "Top of Sidebar", "icon" => "fas fa-arrow-up", "color" => "#0891B2"],
    "sidebar_middle" => ["label" => "Middle of Sidebar", "icon" => "fas fa-grip-lines", "color" => "#0D9488"],
    "footer_banner" => ["label" => "Footer Banner", "icon" => "fas fa-arrow-down", "color" => "#475569"],
    "popup" => ["label" => "Popup", "icon" => "fas fa-external-link-alt", "color" => "#DC2626"]
];

try { $pdo->exec("ALTER TABLE news ADD COLUMN subcategory_id INT DEFAULT NULL AFTER category_id"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE news ADD COLUMN updated_at DATETIME DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE news ADD COLUMN headline VARCHAR(255) DEFAULT NULL AFTER title"); } catch (PDOException $e) {}
// ========================================== Breaking News from Add News Section ==========================================
// Add is_breaking column to news table - checkbox in Add News form toggles breaking tracker on frontend
try { $pdo->exec("ALTER TABLE news ADD COLUMN is_breaking TINYINT(1) NOT NULL DEFAULT 0 AFTER is_featured"); } catch (PDOException $e) {}
// Add news_id column to breaking_news table - links breaking entries to their source news for auto sync
try { $pdo->exec("ALTER TABLE breaking_news ADD COLUMN news_id INT DEFAULT NULL"); } catch (PDOException $e) {}
try { $pdo->exec("ALTER TABLE breaking_news ADD INDEX idx_brk_news_id (news_id)"); } catch (PDOException $e) {}

 $categories = [];
try { $categories = $pdo->query("SELECT id, name FROM categories ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}

 $addError = ""; $addSuccess = ""; $addData = ["title" => "", "headline" => "", "slug" => "", "content" => "", "image" => "", "tags" => "", "category_id" => "", "subcategories" => [], "author" => $fullName, "status" => "draft", "is_featured" => 0, "is_breaking" => 0, "created_at" => ""];

if ($section === "add" && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCSRF()) { $addError = "Token mismatch."; }
    else {
        $addData["title"] = trim($_POST["title"] ?? "");
        $addData["headline"] = trim($_POST["headline"] ?? "");
        $addData["content"] = $_POST["content"] ?? "";
        $addData["tags"] = trim($_POST["tags"] ?? "");
        $addData["category_id"] = $_POST["category_id"] ?? "";
        $addData["subcategories"] = $_POST["subcategories"] ?? [];
        if (!is_array($addData["subcategories"])) $addData["subcategories"] = explode(",", $addData["subcategories"]);
        $addData["author"] = trim($_POST["author"] ?? $fullName);
        $addData["status"] = ($_POST["status"] === "published") ? "published" : "draft";
        $addData["is_featured"] = isset($_POST["is_featured"]) ? 1 : 0;
        $addData["is_breaking"] = isset($_POST["is_breaking"]) ? 1 : 0;
        $addData["created_at"] = trim($_POST["news_created_at"] ?? "");
        
        $addData["slug"] = generateSlug($addData["title"]);
        $addData["slug"] = makeUniqueSlug($pdo, $addData["slug"]);

        if (empty($addData["title"])) { $addError = "Please provide a title."; }
        elseif (empty($addData["content"])) { $addError = "Please write a description."; }
        else {
            // ★ FIX (duplicate news on refresh): server-side duplicate guard.
            // If the exact same title + content was already saved within the last 60 seconds,
            // block the insert (protects against double-click on Save and any other resubmission).
            try {
                $__dup = $pdo->prepare("SELECT id FROM news WHERE title = ? AND MD5(content) = MD5(?) AND updated_at >= (NOW() - INTERVAL 60 SECOND) LIMIT 1");
                $__dup->execute([$addData["title"], $addData["content"]]);
                if ($__dup->fetch()) $addError = "This exact news (same title & content) was saved within the last minute — duplicate submission blocked. If this is intentional, slightly change the title and try again.";
            } catch (PDOException $__dupErr) {}
            $ur = (empty($addError)) ? handleUpload("news_image") : null;
            if ($ur !== null) { if (isset($ur["error"])) { $addError = $ur["error"]; } else { $addData["image"] = $ur["path"]; } }
            // ★ Watermark: stamp the site watermark on this freshly uploaded news image (original kept as .orig backup, removal-friendly)
            if ($WM_FEATURE_LIVE && $ur !== null && empty($addError) && $wmVals['wm_enabled'] === '1' && $wmVals['wm_apply_new'] === '1') { wmApplyToImage($ur["full_path"], $wmVals); }
            if (empty($addError)) {
                try {
                    $firstSubId = !empty($addData["subcategories"]) ? (int)$addData["subcategories"][0] : null;
                    $news_created_at = !empty($addData["created_at"]) ? date('Y-m-d H:i:s', strtotime($addData["created_at"])) : date('Y-m-d H:i:s');
                    $pdo->prepare("INSERT INTO news (title, headline, slug, content, image, tags, category_id, subcategory_id, author, status, is_featured, is_breaking, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                        ->execute([$addData["title"], $addData["headline"], $addData["slug"], $addData["content"], $addData["image"], $addData["tags"], !empty($addData["category_id"]) ? (int)$addData["category_id"] : null, $firstSubId, $addData["author"], $addData["status"], $addData["is_featured"], $addData["is_breaking"], $news_created_at, date('Y-m-d H:i:s')]);
                    $nid = $pdo->lastInsertId();
                    saveNewsSubcategories($pdo, $nid, $addData["subcategories"]);
                    
                    // Breaking News Sync (Add): if checkbox is on, insert news title into breaking_news table so frontend ticker shows it instantly
                    if ($addData["is_breaking"]) {
                        try {
                            $pdo->prepare("INSERT INTO breaking_news (text, news_id, created_at) VALUES (?, ?, ?)")
                                ->execute([$addData["title"], $nid, $news_created_at]);
                        } catch (PDOException $bErr) {}
                    }
                    
                    if ($addData["status"] === "published") {
                        sendWebSocketNotification($nid, $addData["title"]);
                    }
                    
                    // ★ FIX (duplicate news on refresh): Post -> Redirect -> GET (PRG).
                    // Previously this only set $section="manage" and re-rendered the manage list
                    // inside the same POST response, so pressing F5 made the browser resend the
                    // POST and insert the same news again. Redirecting to a clean GET URL ends
                    // the POST request, so refreshing can never re-insert the news.
                    header("Location: ?page=admin_dashboard&section=manage&msg=" . ($addData["is_breaking"] ? "added_breaking" : "added"));
                    exit;
                } catch (PDOException $e) { $addError = "Failed: " . $e->getMessage(); }
            }
        }
    }
}

 $editError = ""; $editSuccess = ""; $editData = null;
if ($section === "edit") {
    $eid = (int)($_GET["id"] ?? 0); if ($eid < 1) { header("Location: ?page=admin_dashboard&section=manage"); exit; }
    $er = $pdo->prepare("SELECT * FROM news WHERE id=?"); $er->execute([$eid]); $er = $er->fetch();
    if (!$er) { header("Location: ?page=admin_dashboard&section=manage"); exit; }
    $esi = getNewsSubcategoryIds($pdo, $eid);
    // ★ FIX (duplicate news on refresh): PRG flash — after a successful update the POST handler
    // redirects back here with ?msg=..., so the success message survives the redirect but the
    // POST request itself is gone (refreshing the page can no longer re-submit the update).
    $__editMsg = $_GET["msg"] ?? "";
    if ($__editMsg === "updated") $editSuccess = "Updated successfully.";
    elseif ($__editMsg === "updated_req") $editSuccess = "News updated. A request to change the Publish Date has been sent to Super Admin.";
    // Pre-check breaking news state for edit form: combine news.is_breaking flag with breaking_news table lookup
    $__brkCheck = (int)($er["is_breaking"] ?? 0);
    if ($__brkCheck === 0) {
        try { $__brkRow = $pdo->prepare("SELECT id FROM breaking_news WHERE news_id=? LIMIT 1"); $__brkRow->execute([$eid]); if ($__brkRow->fetch()) $__brkCheck = 1; } catch (Exception $e) {}
    }
    $editData = ["id" => $er["id"], "title" => $er["title"], "headline" => $er["headline"] ?? "", "slug" => $er["slug"], "content" => $er["content"], "image" => $er["image"] ?? "", "tags" => $er["tags"] ?? "", "category_id" => $er["category_id"] ?? "", "subcategories" => $esi, "author" => $er["author"] ?? $fullName, "status" => $er["status"], "is_featured" => $er["is_featured"] ?? 0, "is_breaking" => $__brkCheck, "created_at" => $er["created_at"] ?? ""];
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!verifyCSRF()) { $editError = "Token mismatch."; }
        else {
            $editData["title"] = trim($_POST["title"] ?? "");
            $editData["headline"] = trim($_POST["headline"] ?? "");
            $editData["content"] = $_POST["content"] ?? "";
            $editData["tags"] = trim($_POST["tags"] ?? "");
            $editData["category_id"] = $_POST["category_id"] ?? "";
            $editData["subcategories"] = $_POST["subcategories"] ?? [];
            if (!is_array($editData["subcategories"])) $editData["subcategories"] = explode(",", $editData["subcategories"]);
            $editData["author"] = trim($_POST["author"] ?? $fullName);
            $editData["status"] = ($_POST["status"] === "published") ? "published" : "draft";
            $editData["is_featured"] = isset($_POST["is_featured"]) ? 1 : 0;
            $editData["is_breaking"] = isset($_POST["is_breaking"]) ? 1 : 0;
            
            if (empty($editData["slug"])) $editData["slug"] = generateSlug($editData["title"]);
            $editData["slug"] = makeUniqueSlug($pdo, $editData["slug"], $eid);
            
            if (empty($editData["title"])) { 
                $editError = "Please provide a title."; 
            } elseif (empty($editData["content"])) { 
                $editError = "Please write a description."; 
            } else {
                $ur = handleUpload("news_image");
                if ($ur !== null) { 
                    if (isset($ur["error"])) { 
                        $editError = $ur["error"]; 
                    } else { 
                        if (!empty($editData["image"])) { if (file_exists(__DIR__ . '/' . $editData["image"])) @unlink(__DIR__ . '/' . $editData["image"]); } 
                        $editData["image"] = $ur["path"];
                        // ★ Watermark: stamp on the replaced news image too (original kept as .orig backup)
                        if ($WM_FEATURE_LIVE && $wmVals['wm_enabled'] === '1' && $wmVals['wm_apply_new'] === '1') { wmApplyToImage($ur["full_path"], $wmVals); }
                    } 
                }
                if (empty($editError)) {
                    try {
                        $firstSubId = !empty($editData["subcategories"]) ? (int)$editData["subcategories"][0] : null;
                        $__editFlash = "updated";
                        
                        if ($currentUserPerms === null) {
                            $editData["created_at"] = trim($_POST["news_created_at"] ?? "");
                            $news_created_at = !empty($editData["created_at"]) ? date('Y-m-d H:i:s', strtotime($editData["created_at"])) : date('Y-m-d H:i:s');
                        } else {
                            $news_created_at = $er["created_at"];
                            $requested_date = trim($_POST["requested_date"] ?? "");
                            if (!empty($requested_date)) {
                                $req_date_sql = date('Y-m-d H:i:s', strtotime($requested_date));
                                $pdo->prepare("INSERT INTO date_change_requests (news_id, requested_by, requested_date, status) VALUES (?, ?, ?, 'pending')")->execute([$eid, $_SESSION['user_id'], $req_date_sql]);
                                $__editFlash = "updated_req";
                            }
                        }
                        
                        $pdo->prepare("UPDATE news SET title=?, headline=?, slug=?, content=?, image=?, tags=?, category_id=?, subcategory_id=?, author=?, status=?, is_featured=?, is_breaking=?, created_at=?, updated_at=? WHERE id=?")
                            ->execute([$editData["title"], $editData["headline"], $editData["slug"], $editData["content"], $editData["image"], $editData["tags"], !empty($editData["category_id"]) ? (int)$editData["category_id"] : null, $firstSubId, $editData["author"], $editData["status"], $editData["is_featured"], $editData["is_breaking"], $news_created_at, date('Y-m-d H:i:s'), $eid]);
                        saveNewsSubcategories($pdo, $eid, $editData["subcategories"]);
                        
                        // Breaking News Sync (Edit): keep breaking_news table in sync with checkbox state and title changes
                        try {
                            $__brkExist = $pdo->prepare("SELECT id FROM breaking_news WHERE news_id=? LIMIT 1");
                            $__brkExist->execute([$eid]);
                            $__brkExistRow = $__brkExist->fetch();
                            if ($editData["is_breaking"]) {
                                if ($__brkExistRow) {
                                    // Already exists - update text in case title changed so frontend shows new text
                                    $pdo->prepare("UPDATE breaking_news SET text=?, created_at=? WHERE news_id=?")
                                        ->execute([$editData["title"], $news_created_at, $eid]);
                                } else {
                                    // Newly checked - insert into breaking_news
                                    $pdo->prepare("INSERT INTO breaking_news (text, news_id, created_at) VALUES (?, ?, ?)")
                                        ->execute([$editData["title"], $eid, $news_created_at]);
                                }
                            } else {
                                if ($__brkExistRow) {
                                    // Checkbox unchecked - remove from breaking_news
                                    $pdo->prepare("DELETE FROM breaking_news WHERE news_id=?")->execute([$eid]);
                                }
                            }
                        } catch (PDOException $bErr2) {}
                        
                        if ($editData["status"] === "published") {
                            sendWebSocketNotification($eid, $editData["title"]);
                        }
                        
                        // ★ FIX (duplicate news on refresh): PRG redirect after a successful update.
                        // The browser lands on a clean GET URL, so pressing F5 just re-loads the
                        // edit form instead of resubmitting the update POST.
                        header("Location: ?page=admin_dashboard&section=edit&id=" . $eid . "&msg=" . $__editFlash);
                        exit;
                    } catch (PDOException $e) { 
                        $editError = "Failed: " . $e->getMessage(); 
                    }
                }
            }
        }
    }
}

 $manageSuccess = ""; $manageFilter = $_GET["filter"] ?? "all"; $manageSearch = trim($_GET["sq"] ?? ""); $manageNews = []; $totalManageResults = 0; $totalPages = 1;
if ($section === "manage") {
    // ★ FIX (duplicate news on refresh): PRG flash messages — the add/edit/delete/toggle handlers
    // redirect here with ?msg=..., so success messages show after the redirect but no action
    // can ever be re-executed by refreshing the page.
    switch ($_GET["msg"] ?? "") {
        case "added": $manageSuccess = "Saved successfully."; break;
        case "added_breaking": $manageSuccess = "Saved successfully. Breaking News tracker-এও যোগ হয়েছে।"; break;
        case "deleted": $manageSuccess = "Deleted successfully."; break;
        case "status": $manageSuccess = "Status changed successfully."; break;
    }
    if (isset($_GET["delete"]) && isset($_GET["dtoken"])) { 
        if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["dtoken"])) { 
            $di = (int)$_GET["delete"]; 
            $pdo->prepare("DELETE FROM news_subcategories WHERE news_id=?")->execute([$di]); 
            // Delete linked breaking_news entry when news is deleted (auto cleanup)
            try { $pdo->prepare("DELETE FROM breaking_news WHERE news_id=?")->execute([$di]); } catch (PDOException $bDelErr) {}
            $dr = $pdo->prepare("SELECT image FROM news WHERE id=?"); 
            $dr->execute([$di]); 
            $dw = $dr->fetch(); 
            if ($dw && !empty($dw["image"]) && file_exists(__DIR__ . '/' . $dw["image"])) @unlink(__DIR__ . '/' . $dw["image"]); 
            $pdo->prepare("DELETE FROM news WHERE id=?")->execute([$di]); 
            // ★ FIX (duplicate on refresh): redirect to a clean URL so F5 cannot re-run the delete
            header("Location: ?page=admin_dashboard&section=manage&" . http_build_query(["filter" => $manageFilter, "sq" => $manageSearch, "p" => $currentPage, "msg" => "deleted"]));
            exit;
        } 
    }
    if (isset($_GET["toggle"]) && isset($_GET["ttoken"])) { if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["ttoken"])) { $pdo->prepare("UPDATE news SET status=IF(status='published','draft','published') WHERE id=?")->execute([(int)$_GET["toggle"]]); // ★ FIX (duplicate on refresh): redirect so F5 cannot re-toggle the status
            header("Location: ?page=admin_dashboard&section=manage&" . http_build_query(["filter" => $manageFilter, "sq" => $manageSearch, "p" => $currentPage, "msg" => "status"]));
            exit; } }
    $cs = "SELECT COUNT(*) FROM news n WHERE 1=1"; $mp = [];
    if ($manageFilter === "published") $cs .= " AND n.status='published'"; elseif ($manageFilter === "draft") $cs .= " AND n.status='draft'"; elseif ($manageFilter === "featured") $cs .= " AND n.is_featured=1";
    if ($manageSearch) { $cs .= " AND (n.title LIKE ? OR n.content LIKE ?)"; $mp[] = "%$manageSearch%"; $mp[] = "%$manageSearch%"; }
    $cst = $pdo->prepare($cs); $cst->execute($mp); $totalManageResults = (int)$cst->fetchColumn(); $totalPages = max(1, ceil($totalManageResults / $perPage)); if ($currentPage > $totalPages) $currentPage = $totalPages; $offset = ($currentPage - 1) * $perPage;
    $ms = "SELECT n.*,c.name as category_name,GROUP_CONCAT(DISTINCT sc.name SEPARATOR ', ') as subcategory_name FROM news n LEFT JOIN categories c ON n.category_id=c.id LEFT JOIN news_subcategories ns ON n.id=ns.news_id LEFT JOIN subcategories sc ON ns.subcategory_id=sc.id WHERE 1=1";
    if ($manageFilter === "published") $ms .= " AND n.status='published'"; elseif ($manageFilter === "draft") $ms .= " AND n.status='draft'"; elseif ($manageFilter === "featured") $ms .= " AND n.is_featured=1";
    if ($manageSearch) $ms .= " AND (n.title LIKE ? OR n.content LIKE ?)"; $ms .= " GROUP BY n.id ORDER BY COALESCE(n.updated_at, n.created_at) DESC LIMIT $perPage OFFSET $offset"; $st = $pdo->prepare($ms); $st->execute($mp); $manageNews = $st->fetchAll();
}

 $brkSuccess = ""; $brkError = ""; $brkList = [];
try { $brkList = $pdo->query("SELECT * FROM breaking_news ORDER BY created_at DESC")->fetchAll(); } catch (Exception $e) {}
 // Breaking News section removed - now controlled via Add News checkbox (is_breaking column on news table)

 $catSuccess = ""; $catError = ""; $catList = [];
try { $catList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(); } catch (Exception $e) {}
 $catEditData = null; $subEditData = null;
if (isset($_GET["edit_cat"])) { $eci = (int)$_GET["edit_cat"]; $er = $pdo->prepare("SELECT * FROM categories WHERE id=?"); $er->execute([$eci]); $catEditData = $er->fetch(); }
if (isset($_GET["edit_sub"])) { $esi = (int)$_GET["edit_sub"]; $er = $pdo->prepare("SELECT * FROM subcategories WHERE id=?"); $er->execute([$esi]); $subEditData = $er->fetch(); }

if ($section === "categories" && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCSRF()) {
        $catError = "Token mismatch.";
    } else {
        $ca = $_POST["cat_action"] ?? "";
        if ($ca === "add") {
            $cn = trim($_POST["cat_name"] ?? "");
            if (empty($cn)) { $catError = "Please provide a name."; } else {
                $ck = $pdo->prepare("SELECT id FROM categories WHERE name=?"); $ck->execute([$cn]);
                if ($ck->fetch()) { $catError = "Already exists."; } else {
                    $sl = generateSlug($cn); $sl = makeUniqueCatSlug($pdo, $sl);
                    $pdo->prepare("INSERT INTO categories (name,slug) VALUES (?,?)")->execute([$cn, $sl]);
                    $catSuccess = "Added successfully."; $catList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
                }
            }
        }
        if ($ca === "delete") {
            $ci = (int)($_POST["cat_id"] ?? 0);
            $si = $pdo->prepare("SELECT id FROM subcategories WHERE category_id=?"); $si->execute([$ci]);
            $sia = array_column($si->fetchAll(), 'id');
            if (!empty($sia)) {
                $ph = implode(",", array_fill(0, count($sia), "?"));
                $pdo->prepare("DELETE FROM news_subcategories WHERE subcategory_id IN ($ph)")->execute($sia);
                $pdo->prepare("UPDATE news SET subcategory_id=NULL WHERE subcategory_id IN ($ph)")->execute($sia);
            }
            $pdo->prepare("DELETE FROM subcategories WHERE category_id=?")->execute([$ci]);
            $pdo->prepare("UPDATE news SET category_id=NULL WHERE category_id=?")->execute([$ci]);
            $pdo->prepare("DELETE FROM categories WHERE id=?")->execute([$ci]);
            $catSuccess = "Category deleted successfully. Associated news moved to uncategorized.";
            $catList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
        }
        if ($ca === "add_sub") {
            $sci = (int)($_POST["sub_category_id"] ?? 0); $sn = trim($_POST["sub_name"] ?? "");
            if ($sci < 1) { $catError = "Please select a category."; } elseif (empty($sn)) { $catError = "Please provide a name."; } else {
                $ck = $pdo->prepare("SELECT id FROM subcategories WHERE category_id=? AND name=?"); $ck->execute([$sci, $sn]);
                if ($ck->fetch()) { $catError = "Already exists."; } else {
                    $sl = generateSlug($sn); $ck2 = $pdo->prepare("SELECT id FROM subcategories WHERE slug=?"); $ck2->execute([$sl]); $c = 1;
                    while ($ck2->fetch()) { $sl = generateSlug($sn) . '-' . $c++; $ck2->execute([$sl]); }
                    $pdo->prepare("INSERT INTO subcategories (category_id,name,slug) VALUES (?,?,?)")->execute([$sci, $sn, $sl]);
                    $catSuccess = "Subcategory added successfully."; $catList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
                    $srs = $pdo->query("SELECT s.* FROM subcategories s ORDER BY s.category_id,s.name ASC")->fetchAll();
                    $allSubcategories = []; foreach ($srs as $s) { $allSubcategories[$s["category_id"]][] = $s; }
                }
            }
        }
        if ($ca === "delete_sub") {
            $sid = (int)($_POST["sub_id"] ?? 0);
            $pdo->prepare("DELETE FROM news_subcategories WHERE subcategory_id=?")->execute([$sid]);
            $pdo->prepare("UPDATE news SET subcategory_id=NULL WHERE subcategory_id=?")->execute([$sid]);
            $pdo->prepare("DELETE FROM subcategories WHERE id=?")->execute([$sid]);
            $catSuccess = "Subcategory deleted successfully.";
            $catList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();
            $srs = $pdo->query("SELECT s.* FROM subcategories s ORDER BY s.category_id,s.name ASC")->fetchAll();
            $allSubcategories = []; foreach ($srs as $s) { $allSubcategories[$s["category_id"]][] = $s; }
        }
        if ($ca === "edit_cat") {
            $ci = (int)($_POST["cat_id"] ?? 0); $cn = trim($_POST["cat_name"] ?? ""); $cs = trim($_POST["cat_slug"] ?? "");
            if ($ci < 1) { $catError = "Invalid category."; } elseif (empty($cn)) { $catError = "Please provide a name."; } else {
                $ck = $pdo->prepare("SELECT id FROM categories WHERE name=? AND id != ?"); $ck->execute([$cn, $ci]);
                if ($ck->fetch()) { $catError = "Category name already exists."; } else {
                    if (empty($cs)) $cs = generateSlug($cn);
                    $ck2 = $pdo->prepare("SELECT id FROM categories WHERE slug=? AND id != ?"); $ck2->execute([$cs, $ci]);
                    $c = 1; $orig_slug = $cs;
                    while ($ck2->fetch()) { $cs = $orig_slug . '-' . $c++; $ck2->execute([$cs, $ci]); }
                    $pdo->prepare("UPDATE categories SET name=?, slug=? WHERE id=?")->execute([$cn, $cs, $ci]);
                    $catSuccess = "Category updated successfully."; $catList = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll(); $catEditData = null;
                }
            }
        }
        if ($ca === "edit_sub") {
            $si = (int)($_POST["sub_id"] ?? 0); $sci = (int)($_POST["sub_category_id"] ?? 0); $sn = trim($_POST["sub_name"] ?? ""); $ss = trim($_POST["sub_slug"] ?? "");
            if ($si < 1 || $sci < 1) { $catError = "Invalid selection."; } elseif (empty($sn)) { $catError = "Please provide a name."; } else {
                $ck = $pdo->prepare("SELECT id FROM subcategories WHERE category_id=? AND name=? AND id != ?"); $ck->execute([$sci, $sn, $si]);
                if ($ck->fetch()) { $catError = "Subcategory name already exists in this category."; } else {
                    if (empty($ss)) $ss = generateSlug($sn);
                    $ck2 = $pdo->prepare("SELECT id FROM subcategories WHERE slug=? AND id != ?"); $ck2->execute([$ss, $si]);
                    $c = 1; $orig_slug = $ss;
                    while ($ck2->fetch()) { $ss = $orig_slug . '-' . $c++; $ck2->execute([$ss, $si]); }
                    $pdo->prepare("UPDATE subcategories SET category_id=?, name=?, slug=? WHERE id=?")->execute([$sci, $sn, $ss, $si]);
                    $catSuccess = "Subcategory updated successfully.";
                    $srs = $pdo->query("SELECT s.* FROM subcategories s ORDER BY s.category_id,s.name ASC")->fetchAll();
                    $allSubcategories = []; foreach ($srs as $s) { $allSubcategories[$s["category_id"]][] = $s; } $subEditData = null;
                }
            }
        }
    }
}

 $socialSuccess = ""; $socialError = "";
 $socialFields = [
    "social_facebook" => ["label" => "Facebook", "icon" => "fa-brands fa-facebook-f", "type" => "url", "placeholder" => "https://facebook.com/yourpage"],
    "social_twitter" => ["label" => "Twitter / X", "icon" => "fa-brands fa-x-twitter", "type" => "url", "placeholder" => "https://x.com/yourhandle"],
    "social_youtube" => ["label" => "YouTube", "icon" => "fa-brands fa-youtube", "type" => "url", "placeholder" => "https://youtube.com/@yourchannel"],
    "social_instagram" => ["label" => "Instagram", "icon" => "fa-brands fa-instagram", "type" => "url", "placeholder" => "https://instagram.com/yourprofile"],
    "social_whatsapp" => ["label" => "WhatsApp", "icon" => "fa-brands fa-whatsapp", "type" => "text", "placeholder" => "+8801XXXXXXXXX"],
    "social_telegram" => ["label" => "Telegram", "icon" => "fa-brands fa-telegram", "type" => "url", "placeholder" => "https://t.me/yourchannel"],
    "social_linkedin" => ["label" => "LinkedIn", "icon" => "fa-brands fa-linkedin-in", "type" => "url", "placeholder" => "https://linkedin.com/company/yourpage"],
    "social_tiktok" => ["label" => "TikTok", "icon" => "fa-brands fa-tiktok", "type" => "url", "placeholder" => "https://tiktok.com/@yourhandle"]
];
 $socialCodeFields = [
    "social_facebook_embed" => ["label" => "Facebook Page Plugin", "icon" => "fa-code", "placeholder" => '<div class="fb-page"...>', "rows" => 5],
    "social_facebook_pixel" => ["label" => "Facebook Pixel", "icon" => "fa-chart-line", "placeholder" => '<!-- Facebook Pixel -->', "rows" => 5],
    "social_twitter_widget" => ["label" => "Twitter Widget", "icon" => "fa-code", "placeholder" => '<a class="twitter-timeline"...>', "rows" => 5],
    "social_google_analytics" => ["label" => "Google Analytics", "icon" => "fa-chart-bar", "placeholder" => '<!-- Google Analytics -->', "rows" => 5]
];
 $platformColors = [
    "social_facebook" => "#1877F2", "social_twitter" => "#000", "social_youtube" => "#FF0000",
    "social_instagram" => "#E4405F", "social_whatsapp" => "#25D366", "social_telegram" => "#0088cc",
    "social_linkedin" => "#0A66C2", "social_tiktok" => "#000"
];
 $socialValues = loadSettings($pdo, array_merge(array_keys($socialFields), array_keys($socialCodeFields)));
if ($section === "social" && $_SERVER["REQUEST_METHOD"] === "POST") { if (!verifyCSRF()) { $socialError = "Token mismatch."; } else { try { $sd = []; foreach (array_merge($socialFields, $socialCodeFields) as $k => $c) { $sd[$k] = $_POST[$k] ?? ""; $socialValues[$k] = $sd[$k]; } saveSettings($pdo, $sd); $socialSuccess = "Saved successfully."; } catch (PDOException $e) { $socialError = "Failed: " . $e->getMessage(); } } }

 $contactSuccess = ""; $contactError = "";
 $contactFields = [
    "contact_phone" => ["label" => "Phone (Primary)", "icon" => "fas fa-phone", "type" => "tel", "placeholder" => "+91 33 2245 6789", "group" => "phone"],
    "contact_phone_2" => ["label" => "Phone 2", "icon" => "fas fa-phone-flip", "type" => "tel", "placeholder" => "+91 XXXX XXXXXX", "group" => "phone"],
    "contact_phone_3" => ["label" => "Phone 3", "icon" => "fas fa-mobile-screen", "type" => "tel", "placeholder" => "+91 XXXX XXXXXX", "group" => "phone"],
    "contact_email_1" => ["label" => "Email (Primary)", "icon" => "fas fa-envelope", "type" => "email", "placeholder" => "info@example.in", "group" => "email"],
    "contact_email_2" => ["label" => "Email 2", "icon" => "fas fa-at", "type" => "email", "placeholder" => "news@example.in", "group" => "email"],
    "contact_email_3" => ["label" => "Email 3", "icon" => "fas fa-envelope-open", "type" => "email", "placeholder" => "editor@example.in", "group" => "email"],
    "contact_address" => ["label" => "Address", "icon" => "fas fa-location-dot", "type" => "text", "placeholder" => "Enter address", "rows" => 3, "group" => "address"],
    "contact_whatsapp" => ["label" => "WhatsApp", "icon" => "fa-brands fa-whatsapp", "type" => "text", "placeholder" => "+91XXXXXXXXXX", "group" => "messaging"],
    "contact_telegram" => ["label" => "Telegram", "icon" => "fa-brands fa-telegram", "type" => "text", "placeholder" => "@username", "group" => "messaging"],
    "contact_office_hours" => ["label" => "Office Hours", "icon" => "fas fa-clock", "type" => "text", "placeholder" => "9 AM - 11 PM", "group" => "office"]
];
 $contactCodeFields = [
    "contact_map_embed" => ["label" => "Google Map Embed", "icon" => "fas fa-map", "placeholder" => '<iframe src="https://www.google.com/maps/embed?..."></iframe>', "rows" => 5],
    "contact_extra_info" => ["label" => "Extra Info (HTML)", "icon" => "fas fa-info-circle", "placeholder" => "Extra info...", "rows" => 6]
];
 $contactGroupLabels = [
    "phone" => ["label" => "Phone Numbers", "icon" => "fas fa-phone-volume", "color" => "var(--green)"],
    "email" => ["label" => "Email", "icon" => "fas fa-envelope-open-text", "color" => "var(--blue)"],
    "address" => ["label" => "Address", "icon" => "fas fa-map-location-dot", "color" => "var(--accent)"],
    "messaging" => ["label" => "Messaging", "icon" => "fas fa-comments", "color" => "#25D366"],
    "office" => ["label" => "Office Info", "icon" => "fas fa-building", "color" => "var(--purple)"]
];
 $contactValues = loadSettings($pdo, array_merge(array_keys($contactFields), array_keys($contactCodeFields)));
if ($section === "contact" && $_SERVER["REQUEST_METHOD"] === "POST") { if (!verifyCSRF()) { $contactError = "Token mismatch."; } else { try { $sd = []; foreach (array_merge($contactFields, $contactCodeFields) as $k => $c) { $sd[$k] = $_POST[$k] ?? ""; $contactValues[$k] = $sd[$k]; } saveSettings($pdo, $sd); $contactSuccess = "Saved successfully."; } catch (PDOException $e) { $contactError = "Failed: " . $e->getMessage(); } } }

// ★ Site (sidebar brand) logo upload / delete handler — runs on EVERY page load so the sidebar logo always resolves.
$siteLogoVals = loadSettings($pdo, ['site_logo_img']);
$currentSiteLogo = $siteLogoVals['site_logo_img'] ?? '';
$siteLogoMsg = '';

// ★ v4: keep the site logo in its OWN dedicated folder (uploads/logo/) — never shared with news/ads/festival files.
//   If an older setting still points to uploads/news/... or uploads/ads/..., that file is COPIED into
//   uploads/logo/ and the setting is rewritten. Silent no-op once the path is already uploads/logo/...
if (!function_exists('migrateSiteLogoToOwnFolder')) {
    function migrateSiteLogoToOwnFolder($pdo, &$currentSiteLogo) {
        $__rel = ltrim((string)$currentSiteLogo, '/');
        if ($__rel === '' || preg_match('~^https?://~i', $__rel)) return;
        if (strpos($__rel, 'uploads/logo/') === 0) {
            // ★ v5: already uploads/logo/... — but an older run (v3/v4 on XAMPP) may have dropped the
            //   physical file into DOCUMENT_ROOT/uploads/logo instead of the project's own uploads/logo.
            //   If the project-root copy exists we are done; otherwise fall through and copy it over.
            $__c0 = logoUploadDirCandidates('logo');
            if (!empty($__c0) && is_file($__c0[0] . basename($__rel))) return;
        }
        $__src = '';
        foreach ([(isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '' ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $__rel : ''), __DIR__ . '/' . $__rel, __DIR__ . '/../' . $__rel] as $__p) {
            if ($__p !== '' && is_file($__p)) { $__src = $__p; break; }
        }
        if ($__src === '') { // same file NAME inside the old news/ads logo folders?
            $__nm = basename($__rel);
            foreach (['uploads/news/', 'uploads/ads/'] as $__s) {
                foreach ([(isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '' ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $__s . $__nm : ''), __DIR__ . '/../' . $__s . $__nm, __DIR__ . '/' . $__s . $__nm] as $__p) {
                    if ($__p !== '' && is_file($__p)) { $__src = $__p; break 2; }
                }
            }
        }
        if ($__src === '') return; // file physically missing — leave the setting untouched
        $__dd = '';
        foreach (logoUploadDirCandidates('logo') as $__c) { // ★ v5 project root first
            if (!is_dir($__c)) { if (!@mkdir($__c, 0755, true)) continue; }
            if (!is_writable($__c)) continue;
            $__dd = $__c; break;
        }
        if ($__dd === '') return;
        $__fn = basename($__src);
        $__newRel = 'uploads/logo/' . $__fn;
        if (!is_file($__dd . $__fn)) { if (!@copy($__src, $__dd . $__fn)) return; }
        if ((string)$currentSiteLogo === $__newRel) return;
        // DELETE + INSERT (works even when the table has duplicate setting_key rows)
        try {
            $__mg = $pdo->prepare("DELETE FROM site_settings WHERE setting_key=?");
            $__mg->execute(['site_logo_img']);
            $__mi = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())");
            if ($__mi->execute(['site_logo_img', $__newRel]) === false) {
                $__mi2 = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)");
                if ($__mi2->execute(['site_logo_img', $__newRel]) === false) return;
            }
            $currentSiteLogo = $__newRel;
        } catch (Exception $__e2) { /* keep old path — the upload handler will still work */ }
    }
}
migrateSiteLogoToOwnFolder($pdo, $currentSiteLogo);

if (isset($_GET["delete_site_logo"]) && isset($_GET["dtoken"])) {
    if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["dtoken"])) {
        $__oldrel = ltrim((string)$currentSiteLogo, '/'); if ($__oldrel !== '' && !preg_match('~^https?://~i', $__oldrel)) { foreach ([__DIR__ . '/../' . $__oldrel, __DIR__ . '/' . $__oldrel, (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $__oldrel : '')] as $__of) { if ($__of !== '' && is_file($__of)) { @unlink($__of); break; } } }
        try { saveSettings($pdo, ['site_logo_img' => '']); } catch (Exception $__e) {}
        try { saveSettings($pdo, ['site_logo_hash' => '']); } catch (Exception $__eh6) {} // ★ v6 clear stored duplicate-check hash
        try { // ★ v4: DELETE + INSERT so deletion also wins over duplicate setting_key rows
            $__d4 = $pdo->prepare("DELETE FROM site_settings WHERE setting_key=?");
            $__d4->execute(['site_logo_img']);
            $__i4 = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)");
            $__i4->execute(['site_logo_img', '']);
        } catch (Exception $__e4) {}
        $currentSiteLogo = '';
        $siteLogoMsg = 'deleted';
    }
}
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['site_logo_action']) && $_POST['site_logo_action'] === 'upload') {
    // ★ v6 debug trail — every logo upload attempt appends one line to logo_debug.txt (next to this file),
    //   so failures on the server become visible without guessing. Delete logo_debug.txt any time.
    $__lf = is_writable(__DIR__) ? __DIR__ . '/logo_debug.txt' : (is_writable(__DIR__ . '/..') ? __DIR__ . '/../logo_debug.txt' : sys_get_temp_dir() . '/logo_debug.txt');
    $__fi = $_FILES['site_logo_file'] ?? null;
    @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v6 ATTEMPT | admin_dir=" . __DIR__ . " | file=" . ($__fi ? 'yes' : 'NOT received') . " | name=" . (string)($__fi['name'] ?? '') . " | size=" . (string)($__fi['size'] ?? '') . " | upload_err=" . (string)($__fi['error'] ?? '') . PHP_EOL, FILE_APPEND | LOCK_EX);
    if (!verifyCSRF()) { $siteLogoMsg = 'token'; @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v6 CSRF token FAILED" . PHP_EOL, FILE_APPEND | LOCK_EX); }
    else {
        // ★ v6: DUPLICATE CHECK — runs BEFORE anything is saved. md5 of the freshly uploaded
        //   temp file is compared against the current logo (stored hash + file on disk) and
        //   every file already inside uploads/logo/. A match is rejected; nothing is written.
        $__upHash = (isset($_FILES['site_logo_file']) && is_uploaded_file($_FILES['site_logo_file']['tmp_name'])) ? @md5_file($_FILES['site_logo_file']['tmp_name']) : false;
        $__dup = logoUploadFindDuplicate($__upHash, $pdo, $currentSiteLogo);
        if ($__dup !== false) {
            $siteLogoMsg = 'err:Duplicate logo NOT saved — this exact image already exists as ' . $__dup . ' (একই লোগো আগেই সেভ আছে). Upload a different file.';
            @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v6 DUPLICATE BLOCKED: md5=" . $__upHash . " matches " . $__dup . PHP_EOL, FILE_APPEND | LOCK_EX);
        }
        else {
        $ur = handleLogoUploadKeepName('site_logo_file', 5);   // ★ dedicated uploader — original filename kept (v6: 5MB)
        if ($ur === null) { @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v6 NO FILE received (\$_FILES empty — form/enctype or post_max_size problem)" . PHP_EOL, FILE_APPEND | LOCK_EX); }
        if ($ur !== null) {
            if (isset($ur["error"])) { $siteLogoMsg = 'err:' . $ur["error"]; @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v6 ERROR: " . $ur["error"] . PHP_EOL, FILE_APPEND | LOCK_EX); }
            else {
                $__oldrel = ltrim((string)$currentSiteLogo, '/');
                if ($__oldrel !== '' && !preg_match('~^https?://~i', $__oldrel) && $__oldrel !== (string)$ur["path"]) {
                    foreach ([__DIR__ . '/../' . $__oldrel, __DIR__ . '/' . $__oldrel, (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $__oldrel : '')] as $__of) { if ($__of !== '' && is_file($__of)) { @unlink($__of); break; } }
                }
                // Auto-resize big uploads down to a small icon (max 256px longest side). Safe no-op if GD is missing or image is already small.
                if (!empty($ur["full_path"])) { @resizeImageFit($ur["full_path"], 256); }
                if (empty($ur["full_path"]) || !is_file($ur["full_path"])) {
                    $siteLogoMsg = 'err:File could not be written to the uploads folder — check that uploads/ is writable (permission 755 or 777).';
                } else {
                    // ★ v4: BULLETPROOF database save — 3 strategies + verification, every step logged.
                    //   Handles: missing table, missing updated_at column, DUPLICATE setting_key rows
                    //   (upserts pile new rows while reads keep returning the OLD value — the #1 reason
                    //   a save "looks lost"), and silent-PDO mode (execute() returns false, no exception).
                    $__path = (string)$ur["path"];
                    $__dbErr = '';
                    $__dbVerify = function () use ($pdo) {
                        try { $v = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key='site_logo_img'"); if ($v->execute() === false) return '~exec-failed~'; $__r = $v->fetchColumn(); return ($__r === false || $__r === null) ? '' : (string)$__r; } catch (Exception $__e) { return '~err:' . $__e->getMessage() . '~'; }
                    };
                    // Attempt 1 — normal saveSettings() (has its own updated_at fallback)
                    try {
                        saveSettings($pdo, ['site_logo_img' => $__path]);
                        if ((string)$__dbVerify() !== $__path) { $__dbErr = 'attempt-1 value mismatch (duplicate rows?)'; }
                    } catch (Exception $__e1) { $__dbErr = 'attempt-1: ' . $__e1->getMessage(); }
                    if ((string)$__dbVerify() !== $__path) {
                        @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v4 DB " . $__dbErr . " — trying DELETE+INSERT" . PHP_EOL, FILE_APPEND | LOCK_EX);
                        // Attempt 2 — remove ALL rows for this key (incl. duplicates), then plain INSERT
                        try {
                            $__d2 = $pdo->prepare("DELETE FROM site_settings WHERE setting_key=?");
                            if ($__d2->execute(['site_logo_img']) === false) throw new Exception('DELETE failed (check DB privileges)');
                            $__i2 = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (?,?,NOW())");
                            if ($__i2->execute(['site_logo_img', $__path]) === false) {
                                $__i2b = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)");
                                if ($__i2b->execute(['site_logo_img', $__path]) === false) throw new Exception('INSERT failed (check DB privileges)');
                            }
                            $__dbErr = '';
                        } catch (Exception $__e2) {
                            $__dbErr = 'attempt-2: ' . $__e2->getMessage();
                            @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v4 DB DELETE+INSERT FAILED: " . $__e2->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
                            // Attempt 3 — (re)create the table, then INSERT
                            try {
                                $pdo->exec("CREATE TABLE IF NOT EXISTS site_settings (setting_key VARCHAR(191) NOT NULL PRIMARY KEY, setting_value LONGTEXT NULL, updated_at DATETIME DEFAULT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
                                $__d3 = $pdo->prepare("DELETE FROM site_settings WHERE setting_key=?");
                                if ($__d3->execute(['site_logo_img']) === false) throw new Exception('DELETE after CREATE failed');
                                $__i3 = $pdo->prepare("INSERT INTO site_settings (setting_key, setting_value) VALUES (?,?)");
                                if ($__i3->execute(['site_logo_img', $__path]) === false) throw new Exception('INSERT after CREATE failed');
                                $__dbErr = '';
                            } catch (Exception $__e3) {
                                $__dbErr = 'attempt-3: ' . $__e3->getMessage();
                                @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v4 DB CREATE+INSERT FAILED: " . $__e3->getMessage() . PHP_EOL, FILE_APPEND | LOCK_EX);
                            }
                        }
                    } else { $__dbErr = ''; }
                    $__chk = (string)$__dbVerify();
                    if ($__chk === $__path) {
                        $currentSiteLogo = $__path;
                        // ★ v6: remember the ORIGINAL upload's md5 (before auto-resize) so future
                        //   duplicate uploads are detected even though the saved file was resized.
                        try { saveSettings($pdo, ['site_logo_hash' => (string)$__upHash]); } catch (Exception $__eh6) {}
                        $siteLogoMsg = 'saved:' . $__path . ' | folder: ' . (isset($ur["dir_used"]) ? $ur["dir_used"] : '?') . ' | DB: OK';
                        @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v4 SAVED + DB VERIFIED: " . $__path . " -> " . (isset($ur["dir_used"]) ? $ur["dir_used"] : '?') . PHP_EOL, FILE_APPEND | LOCK_EX);
                    } else {
                        $siteLogoMsg = 'err:File saved on server, but DATABASE save failed — ' . ($__dbErr !== '' ? $__dbErr : 'stored value is "' . $__chk . '"');
                        @file_put_contents($__lf, date('Y-m-d H:i:s') . " | v4 DB VERIFY FAILED: stored='" . $__chk . "' expected='" . $__path . "' err=" . $__dbErr . PHP_EOL, FILE_APPEND | LOCK_EX);
                    }
                }
            }
        }
        }
    }
}

 $adSuccess = ""; $adError = ""; $adEditData = null; $adFilter = $_GET["ad_filter"] ?? "all"; $adList = []; $adTotal = 0;
if ($section === "ads") {
    if (isset($_GET["ad_delete"]) && isset($_GET["ad_dtoken"])) { if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["ad_dtoken"])) { $di = (int)$_GET["ad_delete"]; $dr = $pdo->prepare("SELECT image FROM advertisements WHERE id=?"); $dr->execute([$di]); $dw = $dr->fetch(); if ($dw && !empty($dw["image"]) && file_exists(__DIR__ . '/' . $dw["image"])) @unlink(__DIR__ . '/' . $dw["image"]); $pdo->prepare("DELETE FROM advertisements WHERE id=?")->execute([$di]); $adSuccess = "Deleted successfully."; } }
    if (isset($_GET["ad_toggle"]) && isset($_GET["ad_ttoken"])) { if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["ad_ttoken"])) { $pdo->prepare("UPDATE advertisements SET is_active=IF(is_active=1,0,1) WHERE id=?")->execute([(int)$_GET["ad_toggle"]]); $adSuccess = "Status changed successfully."; } }
    if (isset($_GET["ad_edit"])) { $er = $pdo->prepare("SELECT * FROM advertisements WHERE id=?"); $er->execute([(int)$_GET["ad_edit"]]); $er = $er->fetch(); if ($er) { $adEditData = $er; } }

    if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['update_ads']) && !isset($_POST['update_flash_ads']) && !isset($_POST['user_action']) && !isset($_POST['save_festival_logo']) && !isset($_POST['site_logo_action'])) {
        if (!verifyCSRF()) { $adError = "Token mismatch."; }
        else {
            $at = trim($_POST["ad_title"] ?? ""); $ap = $_POST["ad_position"] ?? "content_middle"; $aty = $_POST["ad_type"] ?? "image"; $al = trim($_POST["ad_link_url"] ?? ""); $acd = $_POST["ad_code"] ?? ""; $aso = (int)($_POST["ad_sort_order"] ?? 0); $asd = !empty($_POST["ad_start_date"]) ? $_POST["ad_start_date"] : null; $aed = !empty($_POST["ad_end_date"]) ? $_POST["ad_end_date"] : null; $ami = !empty($_POST["ad_max_impressions"]) ? (int)$_POST["ad_max_impressions"] : null;
            if (empty($at)) { $adError = "Please provide a name."; }
            else {
                $ur = handleUpload("ad_image"); $ai = null; if ($ur !== null) { if (isset($ur["error"])) { $adError = $ur["error"]; } else { $ai = $ur["path"]; } }
                if (empty($adError)) {
                    try {
                        if (isset($_POST["ad_id"]) && (int)$_POST["ad_id"] > 0) {
                            $aid = (int)$_POST["ad_id"];
                            if ($ai) {
                                $oi = $pdo->prepare("SELECT image FROM advertisements WHERE id=?"); $oi->execute([$aid]); $o = $oi->fetch();
                                if ($o && !empty($o["image"]) && file_exists(__DIR__ . '/' . $o["image"])) @unlink(__DIR__ . '/' . $o["image"]);
                                $pdo->prepare("UPDATE advertisements SET title=?,position=?,ad_type=?,image=?,link_url=?,ad_code=?,sort_order=?,start_date=?,end_date=?,max_impressions=? WHERE id=?")
                                    ->execute([$at, $ap, $aty, $ai, $al, $acd, $aso, $asd, $aed, $ami, $aid]);
                            } else {
                                $pdo->prepare("UPDATE advertisements SET title=?,position=?,ad_type=?,link_url=?,ad_code=?,sort_order=?,start_date=?,end_date=?,max_impressions=? WHERE id=?")
                                    ->execute([$at, $ap, $aty, $al, $acd, $aso, $asd, $aed, $ami, $aid]);
                            }
                            $adSuccess = "Updated successfully."; $adEditData = null;
                        } else {
                            $pdo->prepare("INSERT INTO advertisements (title,position,ad_type,image,link_url,ad_code,sort_order,start_date,end_date,max_impressions) VALUES (?,?,?,?,?,?,?,?,?,?)")
                                ->execute([$at, $ap, $aty, $ai, $al, $acd, $aso, $asd, $aed, $ami]);
                            $adSuccess = "New ad added successfully.";
                        }
                    } catch (PDOException $e) { $adError = "Failed: " . $e->getMessage(); }
                }
            }
        }
    }
    $asql = "SELECT a.* FROM advertisements a WHERE 1=1";
    if ($adFilter === "active") $asql .= " AND a.is_active=1"; elseif ($adFilter === "inactive") $asql .= " AND a.is_active=0"; elseif ($adFilter === "image") $asql .= " AND a.ad_type='image'"; elseif ($adFilter === "code") $asql .= " AND a.ad_type='code'";
    $asql .= " ORDER BY a.sort_order ASC, a.id DESC"; 
    try { $adList = $pdo->query($asql)->fetchAll(); } catch (Exception $e) { $adList = []; }
    $adTotal = count($adList);
}

// ========================================== ★ Flash Ads (Backend - Embed Codes) ==========================================
// 3 separate Flash Ad embed code slots + 3 link URLs.
// Stored in the existing `site_settings` table (same pattern as ad_bottom_1_embed).
// NO separate `flash_ads` table — keeps the system simple and the existing Advertisement section untouched.
 $flashAdSuccess = ""; $flashAdError = "";
 $flashAdKeys = [
    'flash_ad_embed_1', 'flash_ad_link_1',
    'flash_ad_embed_2', 'flash_ad_link_2',
    'flash_ad_embed_3', 'flash_ad_link_3',
 ];
 $flashAdValues = loadSettings($pdo, $flashAdKeys);
 if ($section === "ads" && $_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['update_flash_ads'])) {
    if (!verifyCSRF()) {
        $flashAdError = "Token mismatch.";
    } else {
        try {
            $sd = [];
            foreach ($flashAdKeys as $k) {
                $sd[$k] = $_POST[$k] ?? "";
                $flashAdValues[$k] = $sd[$k];
            }
            saveSettings($pdo, $sd);
            $flashAdSuccess = "Flash Ad embed codes & links saved successfully!";
        } catch (PDOException $e) {
            $flashAdError = "Failed: " . $e->getMessage();
        }
    }
 }
// ========================================== Flash Ads Backend Ends ==========================================

 $passSuccess = ""; $passError = "";
if ($section === "password" && $_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['user_action'])) {
    if (!verifyCSRF()) { $passError = "Token mismatch."; }
    else {
        $cp = $_POST["current_password"] ?? ""; 
        $np = $_POST["new_password"] ?? ""; 
        $cfp = $_POST["confirm_password"] ?? "";
        if (empty($cp) || empty($np) || empty($cfp)) { $passError = "Please fill all fields."; }
        elseif ($np !== $cfp) { $passError = "Passwords do not match."; }
        elseif (strlen($np) < 4) { $passError = "At least 4 characters."; }
        else {
            $u = $pdo->prepare("SELECT password FROM users WHERE id=?");
            $u->execute([$_SESSION["user_id"]]);
            $ur = $u->fetch();
            if ($ur && $cp === $ur["password"]) {
                if ($currentUserPerms === null) {
                    // ★ Super Admin — change directly (no approval needed)
                    $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$np, $_SESSION["user_id"]]);
                    $passSuccess = "Password changed successfully.";
                } else {
                    // ★ Other users — submit as pending request to Super Admin
                    try {
                        // Remove any old pending request from this user (only keep latest)
                        $pdo->prepare("DELETE FROM password_change_requests WHERE user_id=? AND status='pending'")->execute([$_SESSION["user_id"]]);
                        // Insert new pending request
                        $pdo->prepare("INSERT INTO password_change_requests (user_id, new_password, requested_at, status) VALUES (?, ?, NOW(), 'pending')")->execute([$_SESSION["user_id"], $np]);
                        $passSuccess = "✓ Password change request submitted! An administrator will review and approve it shortly. Your current password will continue to work until approval.";
                    } catch (Exception $e) {
                        $passError = "Failed to submit request. Please try again.";
                    }
                }
            } else { $passError = "Current password is wrong."; }
        }
    }
}

 $vidSuccess = ""; $vidError = ""; $videos = [];

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS videos (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        code TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}
try { $pdo->exec("ALTER TABLE videos ADD COLUMN code TEXT NOT NULL"); } catch (Exception $e) {}

try { $videos = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC); } catch (Exception $e) { $videos = []; }

if ($section === "videos" && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCSRF()) { $vidError = "Token mismatch."; } else { 
        $va = $_POST["vid_action"] ?? ""; 
        if ($va === "add") { 
            $vt = trim($_POST["vid_title"] ?? ""); $vc = $_POST["vid_code"] ?? ""; 
            if (empty($vt) || empty($vc)) { $vidError = "Please provide title and embed code."; } else { 
                try {
                    $pdo->prepare("INSERT INTO videos (title, code) VALUES (?, ?)")->execute([$vt, $vc]);
                    $vidSuccess = "Video added successfully."; 
                    $videos = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
                } catch (PDOException $e) {
                    $vidError = "Failed to add video: " . $e->getMessage();
                }
            } 
        } 
        if ($va === "delete") { 
            $vid_id = (int)($_POST["vid_id"] ?? 0); 
            $pdo->prepare("DELETE FROM videos WHERE id = ?")->execute([$vid_id]); 
            $vidSuccess = "Video deleted successfully."; 
            $videos = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        } 
    } 
}

 $homeSuccess = ""; $homeError = ""; $homeSettings = loadSettings($pdo, ['home_cat_panels']); $homeCatPanels = json_decode($homeSettings['home_cat_panels'] ?? '[]', true); if (!is_array($homeCatPanels)) $homeCatPanels = [];
if ($section === "home_panels" && $_SERVER["REQUEST_METHOD"] === "POST") { if (!verifyCSRF()) { $homeError = "Token mismatch."; } else { $selected = $_POST['home_cats'] ?? []; if (!is_array($selected)) $selected = []; saveSettings($pdo, ['home_cat_panels' => json_encode($selected)]); $homeCatPanels = $selected; $homeSuccess = "Home panels updated successfully."; } }

// ====== Breaking News Speed Controller ======
$brkSpeedSuccess = ""; $brkSpeedError = "";
$brkSpeedSettings = loadSettings($pdo, ['breaking_news_speed']);
$breakingNewsSpeed = (int)($brkSpeedSettings['breaking_news_speed'] ?? 45);
if ($breakingNewsSpeed < 10) $breakingNewsSpeed = 10;
if ($breakingNewsSpeed > 180) $breakingNewsSpeed = 180;
if ($section === "breaking_speed" && $_SERVER["REQUEST_METHOD"] === "POST") {
    if (!verifyCSRF()) { $brkSpeedError = "Token mismatch."; }
    else {
        $newSpeed = (int)($_POST['breaking_news_speed'] ?? 45);
        if ($newSpeed < 10 || $newSpeed > 180) {
            $brkSpeedError = "স্পিড ১০ থেকে ১৮০ সেকেন্ডের মধ্যে হতে হবে।";
        } else {
            saveSettings($pdo, ['breaking_news_speed' => (string)$newSpeed]);
            $breakingNewsSpeed = $newSpeed;
            $brkSpeedSuccess = "ব্রেকিং নিউজ স্পিড সফলভাবে আপডেট হয়েছে — " . $newSpeed . " সেকেন্ড।";
        }
    }
}

// ====== ★ Watermark Setting — save config / logo upload / bulk apply / bulk remove ======
$wmSuccess = ""; $wmError = "";
if ($section === "watermark" && $WM_FEATURE_LIVE && hasPerm('categories')) {
    if (isset($_GET["delete_wm"]) && isset($_GET["dtoken"])) {
        $__wp = wmResolvePath($wmVals['wm_image']);
        if ($__wp !== '') { @unlink($__wp); }
        saveSettings($pdo, ['wm_image' => '']);
        $wmVals['wm_image'] = '';
        $wmSuccess = "Watermark logo deleted.";
    }
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        if (!verifyCSRF()) { $wmError = "Token mismatch."; }
        else {
            $act = $_POST['wm_action'] ?? 'save';
            if ($act === 'upload_wm') {
                $ur = handleUpload('wm_image_file', 2, ['image/png', 'image/jpeg', 'image/jpg', 'image/webp', 'image/gif']);
                if ($ur !== null) {
                    if (isset($ur["error"])) { $wmError = $ur["error"]; }
                    else {
                        resizeImageFit($ur["full_path"], 512, 92);
                        $__old = wmResolvePath($wmVals['wm_image']);
                        if ($__old !== '' && $__old !== $ur["full_path"]) { @unlink($__old); }
                        saveSettings($pdo, ['wm_image' => $ur["path"], 'wm_type' => 'image']);
                        $wmVals = wmGetSettings($pdo);
                        $wmSuccess = "Watermark logo uploaded and saved (type switched to Logo).";
                    }
                } else { $wmError = "No file received."; }
            } elseif ($act === 'apply_all') {
                @set_time_limit(0);
                try { $__imgs = $pdo->query("SELECT image FROM news WHERE image<>'' AND image IS NOT NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { $__imgs = []; }
                $__ok = 0; $__skip = 0; $__fail = 0;
                foreach ((array)$__imgs as $__im) {
                    $__p = wmResolvePath((string)$__im);
                    if ($__p === '') { $__skip++; continue; }
                    if (is_file($__p . '.orig')) { wmRestoreOriginal($__p); } // re-apply always starts from the pristine original
                    $__r = wmApplyToImage($__p, $wmVals);
                    if (!empty($__r['ok'])) { $__ok++; } elseif (!empty($__r['err'])) { $__fail++; } else { $__skip++; }
                }
                $wmSuccess = "Watermark applied to {$__ok} image(s) — {$__skip} skipped, {$__fail} failed. Skips are GIF/SVG (animations & vectors are never modified) or missing files.";
            } elseif ($act === 'remove_all') {
                @set_time_limit(0);
                try { $__imgs = $pdo->query("SELECT image FROM news WHERE image<>'' AND image IS NOT NULL ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { $__imgs = []; }
                $__ok = 0; $__none = 0; $__fail = 0;
                foreach ((array)$__imgs as $__im) {
                    $__p = wmResolvePath((string)$__im);
                    if ($__p === '') { $__none++; continue; }
                    $__r = wmRestoreOriginal($__p);
                    if (!empty($__r['ok'])) { $__ok++; } elseif (!empty($__r['err'])) { $__fail++; } else { $__none++; }
                }
                $wmSuccess = "Original restored for {$__ok} image(s) — watermark fully removed. {$__none} image(s) had no stored original (never watermarked by this tool), {$__fail} failed.";
            } else {
                $__pos = (string)($_POST['wm_position'] ?? 'bottom-right');
                $__allowedPos = ['top-left','top-center','top-right','middle-left','center','middle-right','bottom-left','bottom-center','bottom-right'];
                if (!in_array($__pos, $__allowedPos, true)) $__pos = 'bottom-right';
                $__wmText = (string)($_POST['wm_text'] ?? '');
                $__wmText = function_exists('mb_substr') ? mb_substr(trim($__wmText), 0, 60) : substr(trim($__wmText), 0, 120);
                $__sd = [
                    'wm_enabled' => isset($_POST['wm_enabled']) ? '1' : '0',
                    'wm_apply_new' => isset($_POST['wm_apply_new']) ? '1' : '0',
                    'wm_type' => ($_POST['wm_type'] ?? 'text') === 'image' ? 'image' : 'text',
                    'wm_text' => $__wmText,
                    'wm_font_size' => (string)max(8, min(96, (int)($_POST['wm_font_size'] ?? 24))),
                    'wm_color' => preg_match('~^#[0-9a-fA-F]{6}$~', (string)($_POST['wm_color'] ?? '')) ? $_POST['wm_color'] : '#FFFFFF',
                    'wm_opacity' => (string)max(5, min(100, (int)($_POST['wm_opacity'] ?? 35))),
                    'wm_position' => $__pos,
                    'wm_margin' => (string)max(0, min(200, (int)($_POST['wm_margin'] ?? 16))),
                    'wm_scale' => (string)max(5, min(60, (int)($_POST['wm_scale'] ?? 25))),
                ];
                if ($__sd['wm_type'] === 'image' && empty($wmVals['wm_image'])) { $wmError = "Upload a watermark logo first, or switch type to Text."; }
                else { saveSettings($pdo, $__sd); $wmVals = wmGetSettings($pdo); $wmSuccess = "Watermark settings saved successfully."; }
            }
        }
    }
}

if (!function_exists('secLink')) { function secLink($s, $i, $l, $b = "") { global $section; $a = ($section === $s) ? " active" : ""; $bg = $b ? "<span class=\"badge\">$b</span>" : ""; return "<a href=\"?page=admin_dashboard&section=$s\" class=\"$a\"><i class=\"fas $i\"></i> $l$bg</a>"; } }

 $pendingUsersCount = 0;
try { $pendingUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0")->fetchColumn(); } catch (Exception $e) {}

 $pendingDateRequestsCount = 0;
try { $pendingDateRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM date_change_requests WHERE status='pending'")->fetchColumn(); } catch (Exception $e) {}

// ★ Count pending password change requests (for popup notification on dashboard)
$pendingPasswordRequestsCount = 0;
try { $pendingPasswordRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM password_change_requests WHERE status='pending'")->fetchColumn(); } catch (Exception $e) {}

 $currentRoleKey = !empty($currentUserData['role']) ? $currentUserData['role'] : getUserRole($currentUserData['permissions'] ?? null);
 $currentUserRoleLabel = $allRoles[$currentRoleKey]['label'] ?? 'Custom';

 $defaultCategories = ['প্রধান খবর','জাতীয়','রাজনীতি','আন্তর্জাতিক','অর্থনীতি','খেলাধুলা','বিনোদন','শিক্ষা','প্রযুক্তি','স্বাস্থ্য','বিশেষ সংবাদ','লাইফস্টাইল','ধর্ম','সংস্কৃতি','মতামত','ক্রাইম','পরিবেশ','কৃষি','ভ্রমণ','চাকরি'];
 $panelDefaults = [
    'জাতীয়' => ['icon' => 'fa-flag', 'color' => '#1565C0'],
    'আন্তর্জাতিক' => ['icon' => 'fa-globe', 'color' => '#2E7D32'],
    'খেলাধুলা' => ['icon' => 'fa-futbol', 'color' => '#E65100'],
    'বিনোদন' => ['icon' => 'fa-film', 'color' => '#7B1FA2'],
    'প্রযুক্তি' => ['icon' => 'fa-microchip', 'color' => '#1976D2'],
    'স্বাস্থ্য' => ['icon' => 'fa-heart-pulse', 'color' => '#388E3C'],
    'রাজনীতি' => ['icon' => 'fa-landmark', 'color' => '#5E35B1'],
    'অর্থনীতি' => ['icon' => 'fa-chart-line', 'color' => '#00838F'],
    'শিক্ষা' => ['icon' => 'fa-graduation-cap', 'color' => '#F57F17'],
    'লাইফস্টাইল' => ['icon' => 'fa-spa', 'color' => '#00897B'],
    'পরিবেশ' => ['icon' => 'fa-leaf', 'color' => '#558B2F'],
    'কৃষি' => ['icon' => 'fa-seedling', 'color' => '#8BC34A'],
];
 $navCatIcons = ['প্রধান খবর'=>'fa-fire','জাতীয়'=>'fa-flag','রাজনীতি'=>'fa-landmark','আন্তর্জাতিক'=>'fa-globe','অর্থনীতি'=>'fa-chart-line','খেলাধুলা'=>'fa-futbol','বিনোদন'=>'fa-film','শিক্ষা'=>'fa-graduation-cap','প্রযুক্তি'=>'fa-microchip','স্বাস্থ্য'=>'fa-heart-pulse','বিশেষ সংবাদ'=>'fa-star','লাইফস্টাইল'=>'fa-spa','ধর্ম'=>'fa-mosque','সংস্কৃতি'=>'fa-masks-theater','মতামত'=>'fa-comment-dots','ক্রাইম'=>'fa-gavel','পরিবেশ'=>'fa-leaf','কৃষি'=>'fa-seedling','ভ্রমণ'=>'fa-plane','চাকরি'=>'fa-briefcase'];
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Panel</title>
<meta name="robots" content="noindex, nofollow">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<?php if ($section === "add" || $section === "edit"): ?>
<script src="https://cdn.ckeditor.com/4.22.1/standard-all/ckeditor.js"></script>
<?php endif; ?>
<style>
:root{--bg:#F5F0E8;--fg:#1A1A1A;--muted:#6B6155;--accent:#C41E3A;--accent-dark:#8B1528;--accent-light:rgba(196,30,58,.08);--card:#FFF;--border:#D4C9B8;--green:#2D5016;--blue:#1E5A8C;--gold:#D4A017;--purple:#6B21A8;--sb-bg:#1A1A1A;--sb-fg:#ccc;--sb-hover:rgba(255,255,255,.08)}
*,*::before,*::after{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--fg);line-height:1.7;min-height:100vh}
a{color:inherit;text-decoration:none}button{cursor:pointer;font-family:inherit;border:none;background:none}input,select,textarea{font-family:inherit}
.admin-layout{display:grid;grid-template-columns:250px 1fr;min-height:100vh}
.admin-sidebar{background:var(--sb-bg);color:var(--sb-fg);position:sticky;top:0;height:100vh;overflow-y:auto;scrollbar-width:4px;scrollbar-color:#333 transparent}
.admin-sidebar::-webkit-scrollbar{width:4px}.admin-sidebar::-webkit-scrollbar-thumb{background:#333;border-radius:2px}
.sb-header{padding:18px;border-bottom:1px solid #2a2a2a;display:flex;align-items:center;gap:10px}
.sb-logo{width:34px;height:34px;background:var(--accent);border-radius:6px;display:flex;align-items:center;justify-content:center;color:#fff;font-weight:900;font-size:14px;flex-shrink:0}
.sb-brand{font-family:'Noto Serif Bengali',serif;font-size:14px;font-weight:700;color:#fff;line-height:1.2}
.sb-brand small{font-size:9px;color:#777;font-weight:400;letter-spacing:1px;text-transform:uppercase;display:block}
/* ★ Sidebar brand logo (upload / delete aware) */
.sb-logo-wrap{position:relative;flex-shrink:0}
.sb-logo{position:relative;overflow:hidden;cursor:pointer}
.sb-logo input[type=file]{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer;z-index:3}
.sb-logo-edit{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(0,0,0,.55);color:#fff;font-size:11px;opacity:0;transition:opacity .15s;z-index:2}
.sb-logo:hover .sb-logo-edit,.sb-logo:focus-within .sb-logo-edit{opacity:1}
.sb-logo img{width:100%;height:100%;object-fit:contain;display:block}
.sb-logo.sb-logo-has-img{background:#fff}
.sb-logo-del{position:absolute;top:-6px;right:-6px;width:16px;height:16px;background:#ef4444;color:#fff;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:9px;line-height:1;z-index:4;box-shadow:0 0 0 2px var(--sb-bg)}
.sb-logo-del:hover{background:#dc2626}
.sb-logo-toast{position:fixed;top:14px;right:14px;z-index:9999;padding:10px 14px;border-radius:6px;font-size:12.5px;font-weight:600;color:#fff;box-shadow:0 6px 20px rgba(0,0,0,.25);animation:sbToastIn .2s ease;display:flex;align-items:center;gap:8px}
.sb-logo-toast.ok{background:#16a34a}.sb-logo-toast.err{background:#dc2626}.sb-logo-toast.warn{background:#d97706}
@keyframes sbToastIn{from{opacity:0;transform:translateY(-6px)}to{opacity:1;transform:translateY(0)}}
.sb-user{padding:14px 18px;border-bottom:1px solid #2a2a2a;display:flex;align-items:center;gap:10px}
.sb-avatar{width:34px;height:34px;background:var(--accent);border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:13px;font-weight:700;flex-shrink:0}
.sb-user-info{font-size:12px;color:#fff;font-weight:600}.sb-user-info small{color:#777;font-weight:400;display:block;font-size:10px}
.sb-nav{padding:10px 0}
.sb-nav-label{font-size:9px;color:#555;text-transform:uppercase;letter-spacing:1.5px;padding:14px 18px 5px;font-weight:600}
.sb-nav a{display:flex;align-items:center;gap:11px;padding:9px 18px;font-size:12.5px;font-weight:500;color:#999;transition:all .2s;border-left:3px solid transparent;margin:1px 0}
.sb-nav a:hover{background:var(--sb-hover);color:#fff;border-left-color:var(--accent)}
.sb-nav a.active{background:var(--sb-hover);color:var(--gold);border-left-color:var(--gold)}
.sb-nav a i{width:18px;text-align:center;font-size:13px}
.sb-nav a .badge{margin-left:auto;background:var(--accent);color:#fff;font-size:9px;padding:1px 7px;border-radius:10px;font-weight:600}
.sb-nav a .badge.pending{background:#eab308;color:#000}
.sb-nav-divider{border-top:1px solid #2a2a2a;margin:7px 18px}
.admin-main{padding:22px;overflow-y:auto}
.admin-topbar{display:flex;align-items:center;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:10px}
.admin-topbar h1{font-family:'Noto Serif Bengali',serif;font-size:20px;font-weight:700}
.topbar-actions{display:flex;align-items:center;gap:8px}
.topbar-btn{display:inline-flex;align-items:center;gap:7px;padding:8px 16px;border-radius:6px;font-size:12.5px;font-weight:600;transition:all .2s}
.topbar-btn-primary{background:var(--accent);color:#fff}.topbar-btn-primary:hover{background:var(--accent-dark);transform:translateY(-1px);box-shadow:0 4px 12px rgba(196,30,58,.3)}
.topbar-btn-outline{border:1.5px solid var(--border);color:var(--fg);background:var(--card)}.topbar-btn-outline:hover{border-color:var(--accent);color:var(--accent)}

.bn-date-widget { background: var(--accent-light); padding: 6px 14px; border-radius: 8px; text-align: right; border: 1px solid var(--accent); color: var(--accent-dark); font-family: 'Noto Serif Bengali', serif; line-height:1.4; }
.bn-date-widget .formatted-date { font-size: 14px; font-weight: 700; }
.bn-date-widget .season { font-size: 11px; color: var(--gold); font-weight: 600; }

.stats-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:14px;margin-bottom:24px}
.stat-card{background:var(--card);border:1px solid var(--border);border-radius:10px;padding:14px;display:flex;align-items:center;gap:14px;transition:all .3s;position:relative;overflow:hidden}
.stat-card:hover{transform:translateY(-2px);box-shadow:0 6px 20px rgba(0,0,0,.08)}
a.stat-card{color:inherit;text-decoration:none;cursor:pointer}
a.stat-card:hover .stat-label{color:var(--blue)}
.stat-card::after{content:'';position:absolute;top:0;left:0;width:4px;height:100%}
.sc-red::after{background:var(--accent)}.sc-green::after{background:var(--green)}.sc-blue::after{background:var(--blue)}.sc-gold::after{background:var(--gold)}.sc-purple::after{background:var(--purple)}
.stat-icon{width:40px;height:40px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:17px;flex-shrink:0}
.sc-red .stat-icon{background:rgba(196,30,58,.08);color:var(--accent)}.sc-green .stat-icon{background:rgba(45,80,22,.08);color:var(--green)}.sc-blue .stat-icon{background:rgba(30,90,140,.08);color:var(--blue)}.sc-gold .stat-icon{background:rgba(212,160,23,.08);color:var(--gold)}.sc-purple .stat-icon{background:rgba(107,33,168,.08);color:var(--purple)}
.stat-num{font-size:22px;font-weight:800;line-height:1.2}.stat-label{font-size:10px;color:var(--muted);font-weight:500}
.panel{background:var(--card);border:1px solid var(--border);border-radius:10px;margin-bottom:22px;overflow:hidden}
.panel-header{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px}
.panel-title{font-size:14px;font-weight:700;display:flex;align-items:center;gap:7px}.panel-title i{color:var(--accent);font-size:13px}
.panel-body{padding:18px}
.data-table{width:100%;border-collapse:collapse}
.data-table th{background:var(--bg);padding:9px 12px;font-size:11px;font-weight:600;color:var(--muted);text-align:left;text-transform:uppercase;letter-spacing:.5px;border-bottom:1px solid var(--border);white-space:nowrap}
.data-table td{padding:10px 12px;font-size:12.5px;border-bottom:1px solid rgba(212,201,184,.4);vertical-align:middle}
.data-table tr:last-child td{border-bottom:none}.data-table tr:hover td{background:rgba(196,30,58,.02)}
.status-badge{display:inline-flex;align-items:center;gap:4px;padding:2px 9px;border-radius:20px;font-size:10.5px;font-weight:600}
.status-published{background:rgba(45,80,22,.08);color:var(--green)}.status-draft{background:rgba(212,160,23,.08);color:var(--gold)}
.status-active{background:rgba(45,80,22,.08);color:var(--green)}.status-inactive{background:rgba(220,38,38,.08);color:#dc2626}
.status-pending{background:rgba(212,160,23,.12);color:#b8860b}
.status-badge i{font-size:7px}
.tbl-title{font-weight:600;max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tbl-actions{display:flex;gap:4px}
.tbl-btn{width:28px;height:28px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:11px;transition:all .2s;color:var(--muted)}
.tbl-btn:hover{background:var(--accent-light);color:var(--accent)}.tbl-btn-delete:hover{background:rgba(220,38,38,.08);color:#dc2626}.tbl-btn-toggle:hover{background:rgba(45,80,22,.08);color:var(--green)}.tbl-btn-edit:hover{background:rgba(30,90,140,.08);color:var(--blue)}.tbl-btn-approve:hover{background:rgba(45,80,22,.12);color:var(--green)}.tbl-btn-revoke:hover{background:rgba(212,160,23,.12);color:#b8860b}
.sub-badge{display:inline-block;font-size:10px;color:var(--purple);font-weight:600;background:rgba(107,33,168,.08);padding:1px 7px;border-radius:10px;margin-left:4px}
.form-group{margin-bottom:14px}.form-label{display:block;font-size:12.5px;font-weight:600;margin-bottom:4px}
.form-input{padding:9px 13px;border:2px solid var(--border);border-radius:8px;font-size:13.5px;color:var(--fg);background:var(--bg);outline:none;transition:all .25s;width:100%}
.form-input:focus{border-color:var(--accent);box-shadow:0 0 0 3px var(--accent-light);background:var(--card)}
.form-input:disabled{background:#eee;color:#999;cursor:not-allowed}
.form-select{padding:9px 13px;border:2px solid var(--border);border-radius:8px;font-size:13.5px;color:var(--fg);background:var(--bg);outline:none;width:100%;cursor:pointer;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%236B6155' d='M6 8L1 3h10z'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 12px center}
.btn{display:inline-flex;align-items:center;gap:7px;padding:8px 18px;border-radius:8px;font-size:13px;font-weight:600;transition:all .25s;border:none;cursor:pointer}
.btn-primary{background:var(--accent);color:#fff}.btn-primary:hover{background:var(--accent-dark);transform:translateY(-1px);box-shadow:0 4px 12px rgba(196,30,58,.3)}
.btn-sm{padding:5px 12px;font-size:11.5px;border-radius:6px}
.btn-outline{background:var(--card);color:var(--fg);border:1.5px solid var(--border)}.btn-outline:hover{border-color:var(--accent);color:var(--accent)}
.btn-gold{background:var(--gold);color:#fff}.btn-gold:hover{background:#b8860b}
.btn-purple{background:var(--purple);color:#fff}.btn-purple:hover{background:#581c87}
.filter-tabs{display:flex;gap:5px;flex-wrap:wrap}
.filter-tab{padding:6px 14px;border-radius:20px;font-size:11.5px;font-weight:600;border:1.5px solid var(--border);color:var(--muted);transition:all .2s;cursor:pointer;background:var(--card)}
.filter-tab:hover{border-color:var(--accent);color:var(--accent)}.filter-tab.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.img-upload-area{border:2px dashed var(--border);border-radius:10px;padding:24px;text-align:center;cursor:pointer;transition:all .3s;position:relative;background:var(--bg)}
.img-upload-area:hover{border-color:var(--accent);background:var(--accent-light)}
.img-upload-area.has-image{padding:10px;border-style:solid;border-color:var(--green);background:rgba(45,80,22,.04)}
.img-upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;z-index:2}
.img-upload-icon{font-size:32px;color:var(--muted);margin-bottom:8px}
.img-upload-text{font-size:13px;color:var(--muted);font-weight:500}
.img-upload-hint{font-size:10.5px;color:var(--muted);margin-top:4px}
.img-preview{max-width:100%;max-height:180px;border-radius:8px;margin-top:8px;object-fit:contain;display:block;margin-left:auto;margin-right:auto}
.radio-group{display:flex;gap:12px;flex-wrap:wrap}
.radio-item{display:flex;align-items:center;gap:7px;cursor:pointer;padding:7px 16px;border:2px solid var(--border);border-radius:8px;transition:all .2s}
.radio-item:hover{border-color:var(--accent)}.radio-item.selected{border-color:var(--accent);background:var(--accent-light)}
.radio-item label{font-size:12.5px;font-weight:600;cursor:pointer;white-space:nowrap}
.switch{position:relative;width:44px;height:24px;flex-shrink:0;display:inline-block}
.switch input{opacity:0;width:0;height:0;position:absolute}
.switch-slider{position:absolute;inset:0;background:var(--border);border-radius:24px;transition:all .3s;cursor:pointer}
.switch-slider::before{content:'';position:absolute;width:18px;height:18px;left:3px;bottom:3px;background:#fff;border-radius:50%;transition:all .3s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.switch input:checked+.switch-slider{background:var(--accent)}.switch input:checked+.switch-slider::before{transform:translateX(20px)}
.cat-bar-item{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.cat-bar-item:last-child{margin-bottom:0}
.cat-bar-label{width:90px;font-size:11.5px;font-weight:600;text-align:right;flex-shrink:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cat-bar-track{flex:1;height:22px;background:var(--bg);border-radius:6px;overflow:hidden}
.cat-bar-fill{height:100%;background:linear-gradient(90deg,var(--accent),var(--gold));border-radius:6px;transition:width .8s cubic-bezier(.4,0,.2,1);display:flex;align-items:center;justify-content:flex-end;padding-right:7px}
.cat-bar-fill span{font-size:9.5px;font-weight:700;color:#fff;white-space:nowrap}
.alert{padding:12px 16px;border-radius:8px;font-size:12.5px;font-weight:500;margin-bottom:18px;display:flex;align-items:center;gap:9px;animation:alertSlide .4s ease}
.alert-error{background:rgba(220,38,38,.08);color:#dc2626;border:1px solid rgba(220,38,38,.2)}
.alert-success{background:rgba(45,80,22,.08);color:var(--green);border:1px solid rgba(45,80,22,.2)}
.alert-warning{background:rgba(212,160,23,.1);color:#b8860b;border:1px solid rgba(212,160,23,.3)}
@keyframes alertSlide{from{opacity:0;transform:translateY(-8px)}to{opacity:1;transform:translateY(0)}}
.quick-action{display:flex;align-items:center;gap:9px;padding:9px 13px;background:var(--bg);border-radius:8px;font-size:12.5px;font-weight:600;transition:all .2s}
.quick-action:hover{background:var(--accent-light);color:var(--accent);transform:translateX(3px)}
.form-actions-bar{display:flex;align-items:center;justify-content:space-between;padding:16px 18px;border-top:1px solid var(--border);background:var(--bg);border-radius:0 0 10px 10px;flex-wrap:wrap;gap:10px}
.pagination-wrap{display:flex;align-items:center;justify-content:center;gap:12px;padding:16px 18px;border-top:1px solid var(--border);flex-wrap:wrap}
.pagination-info{font-size:12px;color:var(--muted);font-weight:500}
.pagination-btns{display:flex;gap:4px}
.pagination-btn{width:32px;height:32px;border-radius:6px;display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:600;border:1.5px solid var(--border);color:var(--muted);transition:all .2s;background:var(--card)}
.pagination-btn:hover{border-color:var(--accent);color:var(--accent)}
.pagination-btn.active{background:var(--accent);color:#fff;border-color:var(--accent)}
.password-form{max-width:460px}
.pass-strength{height:4px;border-radius:2px;background:var(--border);margin-top:6px;overflow:hidden}
.pass-strength-bar{height:100%;border-radius:2px;transition:all .3s}
.form-row{display:grid;grid-template-columns:1fr 1fr;gap:14px}
.form-hint{font-size:10.5px;color:var(--muted);margin-top:3px}
.ad-pos-chip{display:inline-flex;align-items:center;gap:5px;padding:4px 10px;border-radius:16px;font-size:11px;font-weight:600;border:1px solid}
.empty-state{text-align:center;padding:40px 20px;color:var(--muted)}
.empty-state i{font-size:40px;margin-bottom:12px;opacity:.4}
.empty-state p{font-size:13px}
.img-thumb{width:40px;height:28px;object-fit:cover;border-radius:4px;border:1px solid var(--border)}
.mobile-toggle{display:none;position:fixed;top:12px;left:12px;z-index:9998;width:40px;height:40px;border-radius:8px;background:var(--accent);color:#fff;align-items:center;justify-content:center;font-size:16px;box-shadow:0 4px 12px rgba(0,0,0,.2)}
.sb-overlay{display:none;position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:9997}
.sb-overlay.show{display:block}
.cat-panel-row{display:grid;grid-template-columns:1fr 1fr;gap:22px;margin-bottom:22px}
.home-cat-grid{display:grid;grid-template-columns:1fr 1fr;gap:15px}
.home-cat-item{display:flex;align-items:center;gap:12px;padding:15px;border:1px solid var(--border);border-radius:8px;background:var(--bg);transition:all .2s}
.home-cat-item:hover{border-color:var(--accent)}
.perm-chip{display:inline-flex;align-items:center;gap:5px;padding:6px 12px;background:var(--card);border:1.5px solid var(--border);border-radius:6px;font-size:11.5px;font-weight:600;cursor:pointer;transition:all .2s}
.perm-chip:hover{border-color:var(--purple)}
.perm-chip input{width:16px;height:16px;accent-color:var(--accent)}
@media(max-width:768px){
    .admin-layout{grid-template-columns:1fr}
    .admin-sidebar{position:fixed;left:-260px;top:0;z-index:9999;transition:left .3s;width:260px}
    .admin-sidebar.open{left:0}
    .mobile-toggle{display:flex!important}
    .form-row{grid-template-columns:1fr}
    .stats-grid{grid-template-columns:repeat(2,1fr)}
    .cat-panel-row{grid-template-columns:1fr}
    .home-cat-grid{grid-template-columns:1fr}
}
.nse-india-theme .nse-ticker{background:#000080;color:#fff;border-radius:10px;padding:14px 18px;margin-bottom:22px;overflow:hidden;box-shadow:0 4px 15px rgba(0,0,0,0.2);border:1px solid #FF9933;}
.nse-india-theme .nse-ticker-title{font-size:11px;color:#FF9933;font-weight:700;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;display:flex;align-items:center;gap:6px}
.nse-india-theme .nse-indices{display:flex;gap:16px;flex-wrap:wrap}
.nse-india-theme .nse-idx-item{font-size:12px}
.nse-india-theme .nse-idx-name{color:#eee;font-size:10px}
.nse-india-theme .nse-idx-val{font-weight:700;font-size:14px}
.nse-india-theme .nse-idx-up{color:#22c55e}
.nse-india-theme .nse-idx-down{color:#ef4444}
.nse-india-theme .panel{background:#fff;border:1px solid #FF9933;border-radius:10px;margin-bottom:22px;overflow:hidden;box-shadow:0 4px 10px rgba(0,0,0,0.05);}
.nse-india-theme .panel-header{background:linear-gradient(90deg,#FF9933 0%,#FFFFFF 50%,#138808 100%);padding:10px 18px;border-bottom:2px solid #000080;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
.nse-india-theme .panel-title{font-size:14px;font-weight:800;display:flex;align-items:center;gap:7px;color:#000080;text-shadow:0 1px 2px rgba(255,255,255,0.5);}
.nse-india-theme .panel-title i{color:#000080;}
.nse-india-theme .panel-body{padding:0;background:#fff;}
.nse-india-theme .data-table{width:100%;border-collapse:collapse;}
.nse-india-theme .data-table th{background:#f8f9fa;color:#000080;padding:10px 12px;font-size:11px;font-weight:700;text-align:left;text-transform:uppercase;letter-spacing:.5px;border-bottom:2px solid #138807;}
.nse-india-theme .data-table td{padding:10px 12px;font-size:13px;border-bottom:1px solid #eee;color:#333;vertical-align:middle;}
.nse-india-theme .data-table tr:hover td{background:#fff9f2;}
.nse-status-live { color: #22c55e; font-weight: 700; animation: blinkText 1s infinite; }
.nse-status-closed { color: #ef4444; font-weight: 700; }
.nse-signal-anim { display: inline-block; animation: signalMove 1s infinite ease-in-out; }
@keyframes blinkText { 0% { opacity: 1; } 50% { opacity: 0.2; } 100% { opacity: 1; } }
@keyframes signalMove { 0%, 100% { transform: translateY(0); } 50% { transform: translateY(-4px); } }
.tags-wrapper { display: flex; flex-wrap: wrap; gap: 8px; padding: 10px; border: 2px solid var(--border); border-radius: 8px; background: var(--bg); min-height: 48px; align-items: center; transition: all 0.25s; cursor: text; }
.tags-wrapper:focus-within { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-light); background: var(--card); }
.tag-pill { display: inline-flex; align-items: center; gap: 6px; background: linear-gradient(135deg, var(--accent), var(--accent-dark)); color: #fff; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; box-shadow: 0 2px 5px rgba(196,30,58,.3); animation: tagPop 0.3s ease; }
@keyframes tagPop { from { transform: scale(0.8); opacity: 0; } to { transform: scale(1); opacity: 1; } }
.tag-pill .remove-tag { cursor: pointer; opacity: 0.8; transition: opacity 0.2s; font-size: 10px; display: flex; align-items: center; }
.tag-pill .remove-tag:hover { opacity: 1; transform: scale(1.2); }
.tag-input-field { border: none; background: transparent; outline: none; flex: 1; min-width: 120px; font-size: 13.5px; color: var(--fg); padding: 5px 0; font-family: inherit; }
.tag-input-field::placeholder { color: var(--muted); font-weight: 400; }

.festival-badge { display: inline-block; background: var(--accent); color: #fff; padding: 6px 14px; border-radius: 6px; font-weight: 700; font-size: 14px; box-shadow: 0 2px 5px rgba(196,30,58,.3); }
/* ★ e-Paper menu item styling */
.sb-nav a[href*="section=epaper"]{position:relative}
.sb-nav a[href*="section=epaper"] i{color:#FF9933 !important}
.sb-nav a[href*="section=epaper"].active i{color:var(--gold) !important}
.sb-nav a[href*="section=epaper"]::before{content:"NEW";position:absolute;right:14px;top:50%;transform:translateY(-50%);background:#dc2626;color:#fff;font-size:8px;font-weight:700;padding:1px 5px;border-radius:8px;letter-spacing:.5px}
.sb-nav a[href*="section=epaper"].active::before{display:none}
</style>
</head>
<body>
<button class="mobile-toggle" onclick="document.querySelector('.admin-sidebar').classList.toggle('open');document.querySelector('.sb-overlay').classList.toggle('show')"><i class="fas fa-bars"></i></button>
<div class="sb-overlay" onclick="document.querySelector('.admin-sidebar').classList.remove('open');this.classList.remove('show')"></div>
<div class="admin-layout">
<aside class="admin-sidebar">
<div class="sb-header"><div class="sb-logo-wrap"><form method="POST" enctype="multipart/form-data" id="sbLogoForm" style="margin:0;padding:0;display:block"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="site_logo_action" value="upload"><?php
// Resolve the logo file's real location and build a browser URL that actually loads.
// handleUpload() saves under __DIR__.'/../uploads/' (one level ABOVE the admin file),
// while festival-style images live under __DIR__.'/uploads/' (inside the project).
// So we detect which one exists and emit 'uploads/...' or '../uploads/...' accordingly.
$__siteLogoOK = false;
$__siteLogoUrl = '';
if (!empty($currentSiteLogo)) {
    if (preg_match('~^https?://~i', $currentSiteLogo)) {
        $__siteLogoOK = true;
        $__siteLogoUrl = $currentSiteLogo;
    } else {
        $p = ltrim($currentSiteLogo, '/');
        if (is_file(__DIR__ . '/' . $p)) {
            $__siteLogoOK = true;
            $__fm = @filemtime(__DIR__ . '/' . $p);
            $__siteLogoUrl = $p . ($__fm ? '?v=' . $__fm : '');
        } elseif (is_file(__DIR__ . '/../' . $p)) {
            // ★ v5: project-root copy preferred (C:\xampp\htdocs\Newspaper\uploads\logo on XAMPP)
            $__siteLogoOK = true;
            $__fm = @filemtime(__DIR__ . '/../' . $p);
            $__siteLogoUrl = '../' . $p . ($__fm ? '?v=' . $__fm : '');
        } elseif (!empty($_SERVER['DOCUMENT_ROOT']) && is_file(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $p)) {
            $__siteLogoOK = true;
            $__fm = @filemtime(rtrim($_SERVER['DOCUMENT_ROOT'], '/') . '/' . $p);
            $__siteLogoUrl = '/' . $p . ($__fm ? '?v=' . $__fm : '');
        }
    }
}
?>
<label class="sb-logo<?= $__siteLogoOK ? ' sb-logo-has-img' : '' ?>" title="<?= $__siteLogoOK ? 'Click to replace logo (v6 OK)' : 'Click to upload logo (v6 OK)' ?>"><?php if ($__siteLogoOK): ?><img src="<?= htmlspecialchars($__siteLogoUrl) ?>" alt="logo"><?php else: ?>A<?php endif; ?><input type="file" name="site_logo_file" accept="image/jpeg,image/png,image/gif,image/webp,image/svg+xml" onchange="document.getElementById('sbLogoForm').submit()"><span class="sb-logo-edit"><i class="fas fa-camera"></i></span></label></form><?php if ($__siteLogoOK): ?><a href="?page=admin_dashboard&delete_site_logo=1&dtoken=<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>" class="sb-logo-del" title="Delete logo — revert to default A" onclick="return confirm('Delete the site logo? The default A will be shown again.')"><i class="fas fa-times"></i></a><?php endif; ?></div><div class="sb-brand">News Portal<small>Admin Panel</small></div></div>
<div class="sb-user"><div class="sb-avatar"><?= mb_substr($fullName,0,1) ?></div><div class="sb-user-info"><?= htmlspecialchars($fullName) ?><small><?= htmlspecialchars($currentUserRoleLabel) ?></small></div></div>
<nav class="sb-nav">
<?php if (hasPerm('dashboard')): ?>
<div class="sb-nav-label">Main</div>
<?= secLink("dashboard","fa-gauge-high","Dashboard") ?>
<?php endif; ?>
<?php if (hasPerm('add_news')): ?>
<?= secLink("add","fa-plus-circle","Add News") ?>
<?php endif; ?>
<?php if (hasPerm('manage_news')): ?>
<?= secLink("manage","fa-newspaper","Manage News",$totalNews) ?>
<?php endif; ?>
<?php if (hasPerm('categories') || hasPerm('home_panels') || hasPerm('social') || hasPerm('contact')): ?>
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Configuration</div>
<?php endif; ?>
<?php if (hasPerm('categories')): ?>
<?= secLink("categories","fa-folder-tree","Categories / Subcategories",$totalCats) ?>
<?php endif; ?>
<?php if (hasPerm('home_panels')): ?>
<?= secLink("home_panels","fa-table-cells","Home Page Panels") ?>
<?php endif; ?>
<?php if (hasPerm('breaking_speed')): ?>
<?= secLink("breaking_speed","fa-gauge-high","Breaking News Speed") ?>
<?php endif; ?>
<?php if (hasPerm('social')): ?>
<?= secLink("social","fa-share-nodes","Social Media") ?>
<?php endif; ?>
<?php if (hasPerm('contact')): ?>
<?= secLink("contact","fa-address-book","Contact Details") ?>
<?php endif; ?>
<?php if (hasPerm('categories')): ?>
<?= secLink("watermark","fa-stamp","Watermark Setting") ?>
<?php endif; ?>
<?php if (hasPerm('ads')): ?>
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Monetization</div>
<?= secLink("ads","fa-rectangle-ad","Advertisements",$activeAds."/".$totalAds) ?>
<?php endif; ?>

<!-- ★ Visitor Stats Monitoring — sidebar menu item (after Advertisements); shown only with 'visitor_stats' permission -->
<?php if (hasPerm('visitor_stats')): ?>
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Analytics</div>
<?= secLink("visitor_stats","fa-chart-line","Visitor Monitor") ?>
<?php endif; ?>

<?php if (hasPerm('videos') || hasPerm('nse_market') || hasPerm('users') || hasPerm('password') || $currentUserPerms === null): ?>
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Others</div>
<?php endif; ?>
<?php if (hasPerm('manage_news')): ?>
<a href="?page=admin_dashboard&section=articles" class="<?= $section === 'articles' ? 'active' : '' ?>"><i class="fas fa-file-lines" style="width:18px;text-align:center"></i> Articles</a>
<?php endif; ?>
<?php if (hasPerm('manage_news')): ?>
<a href="?page=admin_dashboard&section=epaper" class="<?= $section === 'epaper' ? 'active' : '' ?>"><i class="far fa-newspaper" style="width:18px;text-align:center"></i> ই-পেপার ম্যানেজমেন্ট</a>
<?php endif; ?>
<?php if (hasPerm('videos')): ?>
<?= secLink("videos","fa-video","Videos") ?>
<?php endif; ?>
<?php if (hasPerm('nse_market')): ?>
<?= secLink("nse_market","fa-chart-line","NSE Market") ?>
<?php endif; ?>
<?php if (hasPerm('users')): ?>
<?= secLink("users","fa-users","User Management",$pendingUsersCount > 0 ? '<span class="badge pending">'.$pendingUsersCount.' Pending</span>' : '') ?>
<?php endif; ?>
<?php if ($currentUserPerms === null): // Super Admin Only Link ?>
<?= secLink("date_requests","fa-calendar-check","Date Requests", $pendingDateRequestsCount > 0 ? '<span class="badge pending">'.$pendingDateRequestsCount.' Pending</span>' : '') ?>
<?= secLink("password_requests","fa-key","Password Requests", $pendingPasswordRequestsCount > 0 ? '<span class="badge pending">'.$pendingPasswordRequestsCount.' Pending</span>' : '') ?>
<?php endif; ?>
<?php if (hasPerm('password')): ?>
<?= secLink("password","fa-key","Change Password") ?>
<?php endif; ?>
<div class="sb-nav-divider"></div>
<a href="https://songbadsongolon.infinityfree.me/?" target="_blank" style="display:flex;align-items:center;gap:11px;padding:9px 18px;font-size:12.5px;font-weight:500;color:#22c55e;border-left:3px solid transparent;margin:1px 0"><i class="fas fa-globe" style="width:18px;text-align:center"></i> Newspaper Portal</a>
<div class="sb-nav-divider"></div>
<a href="?page=admin_login&action=logout" style="display:flex;align-items:center;gap:11px;padding:9px 18px;font-size:12.5px;font-weight:500;color:#ef4444;border-left:3px solid transparent;margin:1px 0"><i class="fas fa-right-from-bracket" style="width:18px;text-align:center"></i> Logout</a>
</nav>
</aside>
<main class="admin-main">
<?php if (!empty($siteLogoMsg)):
    $__m = $siteLogoMsg;
    if (strpos($__m, 'saved:') === 0): $__cls = 'ok'; $__ico = 'fa-circle-check'; $__txt = 'Logo saved — filename kept: ' . htmlspecialchars(substr($__m, 6)) . ' [v6]';
    elseif ($__m === 'saved'): $__cls = 'ok'; $__ico = 'fa-circle-check'; $__txt = 'Logo saved — filename kept: ' . htmlspecialchars(basename((string)$currentSiteLogo)) . ' [v6]';
    elseif ($__m === 'deleted'): $__cls = 'ok';   $__ico = 'fa-trash-can';     $__txt = 'Logo deleted. Default A is shown again.';
    elseif ($__m === 'token'):  $__cls = 'err';  $__ico = 'fa-triangle-exclamation'; $__txt = 'Token mismatch — please retry.';
    elseif (strpos($__m, 'err:') === 0): $__cls = 'err'; $__ico = 'fa-triangle-exclamation'; $__txt = 'Logo upload failed: ' . htmlspecialchars(substr($__m, 4)) . ' [v6]';
    else: $__cls = 'warn'; $__ico = 'fa-circle-info'; $__txt = htmlspecialchars($__m);
    endif;
?>
<div class="sb-logo-toast <?= $__cls ?>" id="sbLogoToast"><i class="fas <?= $__ico ?>"></i><span><?= $__txt ?></span></div>
<script>setTimeout(function(){var t=document.getElementById('sbLogoToast');if(t){t.style.transition='opacity .3s';t.style.opacity='0';setTimeout(function(){t.remove()},320)}},3500);</script>
<?php endif; ?>

<?php if ($section === "dashboard" && hasPerm('dashboard')): ?>
<div class="admin-topbar"> 
    <h1><i class="fas fa-gauge-high" style="color:var(--accent)"></i> আডমিন ড্যাশবোর্ড</h1> 
    <div style="display:flex; gap:12px; align-items:center;">
        <!-- Bengali Date Widget Included Here -->
        <div class="bn-date-widget">
            <div class="formatted-date"><?php echo $todayBengali['formatted_full']; ?></div>
            <div class="season">ঋতু: <?php echo $todayBengali['season_bn']; ?></div>
        </div>
        <div class="topbar-actions">
            <a href="https://songbadsongolon.infinityfree.me/?" target="_blank" class="topbar-btn topbar-btn-outline"><i class="fas fa-globe"></i> Newspaper Portal</a>
            <?php if (hasPerm('add_news')): ?><a href="?page=admin_dashboard&section=add" class="topbar-btn topbar-btn-primary"><i class="fas fa-plus"></i> Add News</a><?php endif; ?>
        </div>
    </div>
</div>

<?php if ($manageSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $manageSuccess ?></div><?php endif; ?>
<?php if ($pendingUsersCount > 0 && hasPerm('users')): ?>
<div class="alert alert-warning"><i class="fas fa-user-clock"></i> You have <strong><?= $pendingUsersCount ?></strong> user(s) pending approval. <a href="?page=admin_dashboard&section=users" style="text-decoration:underline;font-weight:700">Review now →</a></div>
<?php endif; ?>
<?php if ($pendingDateRequestsCount > 0 && $currentUserPerms === null): ?>
<div class="alert alert-warning"><i class="fas fa-calendar-day"></i> You have <strong><?= $pendingDateRequestsCount ?></strong> date change request(s) pending. <a href="?page=admin_dashboard&section=date_requests" style="text-decoration:underline;font-weight:700">Review now →</a></div>
<?php endif; ?>
<?php if ($pendingPasswordRequestsCount > 0 && $currentUserPerms === null): ?>
<div class="alert alert-warning" style="border-left:4px solid var(--purple);background:rgba(107,33,168,.08);border-color:rgba(107,33,168,.2);color:var(--purple);">
    <i class="fas fa-key"></i> You have <strong><?= $pendingPasswordRequestsCount ?></strong> password change request(s) pending approval. 
    <a href="?page=admin_dashboard&section=password_requests" style="text-decoration:underline;font-weight:700">Review now →</a>
</div>
<?php endif; ?>

<!-- ★ Popup alert: shows once per session when password change requests are pending -->
<?php if ($section === "dashboard" && $pendingPasswordRequestsCount > 0 && $currentUserPerms === null && empty($_SESSION['pw_popup_dismissed'])): ?>
<?php $_SESSION['pw_popup_dismissed'] = true; // only auto-popup once per session ?>
<script>
(function(){
    var count = <?= (int)$pendingPasswordRequestsCount ?>;
    var go = confirm(
        '🔑 Password Change Approval Required!\n\n' +
        'You have ' + count + ' password change request' + (count > 1 ? 's' : '') + ' waiting for your approval.\n\n' +
        'Click OK to review them now,\nor Cancel to stay on the dashboard.'
    );
    if (go) {
        window.location.href = '?page=admin_dashboard&section=password_requests';
    }
})();
</script>
<?php endif; ?>

<!-- Festival Notification Card Included Here -->
<div class="panel" style="border-left: 5px solid var(--accent);">
    <div class="panel-header">
        <div class="panel-title"><i class="fas fa-calendar-day"></i> আজকের উৎসব ও ইভেন্ট</div>
    </div>
    <div class="panel-body">
        <?php if ($activeFestival): ?>
            <p style="font-size: 16px; margin-bottom: 12px; color:var(--fg);">আজ একটি বিশেষ দিন: </p>
            <span class="festival-badge">
                <?php echo $activeFestival['name']; ?> 🎉
            </span>
            
            <?php if(count($allActiveFestivals) > 1): ?>
                <div style="margin-top: 15px; font-size: 13px; color: var(--muted);">
                    <strong style="color:var(--fg);">আরও চলমান ইভেন্ট:</strong>
                    <ul style="margin-top:5px; padding-left:20px;">
                        <?php 
                        for($i=1; $i < count($allActiveFestivals); $i++): 
                        ?>
                            <li><?php echo $allActiveFestivals[$i]['name']; ?></li>
                        <?php endfor; ?>
                    </ul>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <div class="empty-state" style="padding:20px; text-align:left;">
                <i class="fas fa-calendar-times" style="font-size:24px; color:var(--muted); opacity:0.4;"></i>
                <p style="font-size:13px; display:inline; margin-left:10px;">আজ কোনো নির্দিষ্ট উৎসব বা ইভেন্ট নেই।</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php
// ★ Clickable stat cards — each card opens its matching section (with the right filter pre-applied).
//   Cards are links only when the current user has the permission; otherwise plain display.
$__statCards = [
    ['sc-red',    'fa-newspaper',     $totalNews,                    'Total News',  '?page=admin_dashboard&section=manage',                          'manage_news',    'Click to open Manage News'],
    ['sc-green',  'fa-circle-check',  $publishedNews,                'Published',   '?page=admin_dashboard&section=manage&filter=published',         'manage_news',    'Click to show published news'],
    ['sc-gold',   'fa-file-pen',      $draftNews,                    'Drafts',      '?page=admin_dashboard&section=manage&filter=draft',             'manage_news',    'Click to show draft news'],
    ['sc-purple', 'fa-star',          $featuredNews,                 'Featured',    '?page=admin_dashboard&section=manage&filter=featured',          'manage_news',    'Click to show featured news'],
    ['sc-blue',   'fa-folder-tree',   $totalCats,                    'Categories',  '?page=admin_dashboard&section=categories',                      'categories',     'Click to manage categories'],
    ['sc-red',    'fa-bolt',          $totalBreaking,                'Breaking',    '?page=admin_dashboard&section=breaking_speed',                  'breaking_speed', 'Click to open Breaking News settings'],
    ['sc-gold',   'fa-rectangle-ad',  $activeAds,                    'Active Ads',  '?page=admin_dashboard&section=ads&ad_filter=active',            'ads',            'Click to show active advertisements'],
    ['sc-green',  'fa-mouse-pointer', number_format($totalClicks),   'Total Clicks','?page=admin_dashboard&section=ads',                             'ads',            'Click to open advertisements report'],
];
?>
<div class="stats-grid">
<?php foreach ($__statCards as $__sc): ?>
<?php if (hasPerm($__sc[5])): ?><a href="<?= $__sc[4] ?>" class="stat-card <?= $__sc[0] ?>" title="<?= $__sc[6] ?>"><?php else: ?><div class="stat-card <?= $__sc[0] ?>" style="cursor:default"><?php endif; ?><div class="stat-icon"><i class="fas <?= $__sc[1] ?>"></i></div><div><div class="stat-num"><?= $__sc[2] ?></div><div class="stat-label"><?= $__sc[3] ?></div></div><?php if (hasPerm($__sc[5])): ?></a><?php else: ?></div><?php endif; ?>
<?php endforeach; ?>
</div>
<div style="display:grid;grid-template-columns:2fr 1fr;gap:22px">
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-clock-rotate-left"></i> Recent News</div><?php if (hasPerm('manage_news')): ?><a href="?page=admin_dashboard&section=manage" class="btn btn-sm btn-outline">View All</a><?php endif; ?></div><div class="panel-body" style="padding:0"><table class="data-table"><thead><tr><th>Title</th><th>Category</th><th>Status</th><th>Date</th></tr></thead><tbody>
<?php foreach ($recentNews as $rn): ?>
<tr><td><div class="tbl-title"><?= htmlspecialchars($rn["title"]) ?></div></td><td><?= htmlspecialchars($rn["category_name"] ?? "—") ?><?php if (!empty($rn["subcategory_name"])): ?><span class="sub-badge"><?= htmlspecialchars(mb_substr($rn["subcategory_name"],0,20)) ?></span><?php endif; ?></td><td><span class="status-badge status-<?= $rn["status"] ?>"><i class="fas fa-circle"></i> <?= $rn["status"] === "published" ? "Published" : "Draft" ?></span></td><td style="font-size:11px;color:var(--muted);white-space:nowrap"><?= date("d M Y, h:i A", strtotime($rn["updated_at"] ?? $rn["created_at"])) ?></td></tr>
<?php endforeach; ?>
<?php if (empty($recentNews)): ?><tr><td colspan="4"><div class="empty-state"><i class="fas fa-inbox"></i><p>No recent news found in the last 30 days</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-chart-bar"></i> By Category</div></div><div class="panel-body">
<?php if (!empty($catStats)): $mx = max(array_column($catStats, "cnt")); foreach ($catStats as $cs): $pct = $mx > 0 ? round(($cs["cnt"] / $mx) * 100) : 0; ?>
<div class="cat-bar-item"><div class="cat-bar-label"><?= htmlspecialchars($cs["name"]) ?></div><div class="cat-bar-track"><div class="cat-bar-fill" style="width:<?= $pct ?>%"><span><?= $cs["cnt"] ?></span></div></div></div>
<?php endforeach; else: ?><div class="empty-state" style="padding:20px"><p>No data</p></div><?php endif; ?>
</div></div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-bolt"></i> Quick Actions</div></div><div class="panel-body" style="display:flex;flex-direction:column;gap:8px">
<?php if (hasPerm('add_news')): ?><a href="?page=admin_dashboard&section=add" class="quick-action"><i class="fas fa-plus"></i> Write New News</a><?php endif; ?>
<?php /* Breaking News quick action removed - now managed via Add News checkbox */ ?>
<?php if (hasPerm('categories')): ?><a href="?page=admin_dashboard&section=categories" class="quick-action"><i class="fas fa-folder-tree"></i> Manage Categories</a><?php endif; ?>
<?php if (hasPerm('home_panels')): ?><a href="?page=admin_dashboard&section=home_panels" class="quick-action"><i class="fas fa-table-cells"></i> Home Page Panels</a><?php endif; ?>
<?php if (hasPerm('videos')): ?><a href="?page=admin_dashboard&section=videos" class="quick-action"><i class="fas fa-video"></i> Manage Videos</a><?php endif; ?>
<?php if (hasPerm('ads')): ?><a href="?page=admin_dashboard&section=ads" class="quick-action"><i class="fas fa-rectangle-ad"></i> Manage Advertisements</a><?php endif; ?>
<?php if (hasPerm('users')): ?><a href="?page=admin_dashboard&section=users" class="quick-action"><i class="fas fa-users"></i> Manage Users & Roles</a><?php endif; ?>
<?php if (hasPerm('nse_market')): ?><a href="?page=admin_dashboard&section=nse_market" class="quick-action"><i class="fas fa-chart-line"></i> View NSE Market</a><?php endif; ?>
</div></div>

</div></div>

<?php elseif ($section === "date_requests" && $currentUserPerms === null): // Super Admin Date Requests View ?>
<div class="admin-topbar"><h1><i class="fas fa-calendar-check" style="color:var(--purple)"></i> Date Change Requests</h1></div>
<?php if (!empty($umsg)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($umsg) ?></div><?php endif; ?>
<div class="panel">
    <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i> Pending Requests</div></div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead><tr><th>#</th><th>News Title</th><th>Current Date</th><th>Requested Date</th><th>Requested By</th><th>Action</th></tr></thead>
            <tbody>
            <?php 
            try {
                $reqList = $pdo->query("SELECT r.*, n.title as news_title, u.username as requester_name FROM date_change_requests r JOIN news n ON r.news_id=n.id JOIN users u ON r.requested_by=u.id WHERE r.status='pending' ORDER BY r.created_at DESC")->fetchAll();
            } catch (Exception $e) { $reqList = []; }
            foreach ($reqList as $i => $req): 
            ?>
                <tr>
                    <td style="color:var(--muted);font-size:11px"><?= $i + 1 ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($req["news_title"]) ?></td>
                    <td style="font-size:11px;color:var(--muted)"><?= date("d M Y, h:i A", strtotime($pdo->query("SELECT created_at FROM news WHERE id={$req['news_id']}")->fetchColumn())) ?></td>
                    <td style="font-size:11px;color:var(--accent);font-weight:600"><?= date("d M Y, h:i A", strtotime($req["requested_date"])) ?></td>
                    <td><?= htmlspecialchars($req["requester_name"]) ?></td>
                    <td>
                        <div class="tbl-actions">
                            <a href="?page=admin_dashboard&section=date_requests&approve=<?= $req['id'] ?>&token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" class="tbl-btn tbl-btn-approve" title="Approve" style="color:var(--green)"><i class="fas fa-check"></i></a>
                            <a href="?page=admin_dashboard&section=date_requests&reject=<?= $req['id'] ?>&token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" class="tbl-btn tbl-btn-delete" title="Reject" style="color:#dc2626"><i class="fas fa-times"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($reqList)): ?><tr><td colspan="6"><div class="empty-state"><p>No pending date change requests.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($section === "password_requests" && $currentUserPerms === null): // ★ Super Admin Password Change Requests View ?>
<div class="admin-topbar"><h1><i class="fas fa-key" style="color:var(--purple)"></i> Password Change Requests</h1></div>
<?php if (!empty($umsg)): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($umsg) ?></div><?php endif; ?>

<!-- Info banner -->
<div class="panel" style="border-left:5px solid var(--purple);">
    <div class="panel-body" style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;">
        <i class="fas fa-circle-info" style="color:var(--purple);font-size:18px;flex-shrink:0;margin-top:2px;"></i>
        <div style="font-size:12.5px;color:var(--fg);line-height:1.7;">
            <strong style="color:var(--purple);">How this works:</strong><br>
            When non-super-admin users change their password from "Change Password" section, the request is saved here instead of applying directly. Approve to apply the new password, or reject to discard. The user's current password continues to work until approval.
        </div>
    </div>
</div>

<div class="panel">
    <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i> Pending Password Requests</div></div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead><tr><th>#</th><th>Username</th><th>Full Name</th><th>Requested At</th><th>Action</th></tr></thead>
            <tbody>
            <?php
            try {
                $passReqList = $pdo->query("SELECT r.*, u.username, u.full_name FROM password_change_requests r JOIN users u ON r.user_id=u.id WHERE r.status='pending' ORDER BY r.requested_at DESC")->fetchAll();
            } catch (Exception $e) { $passReqList = []; }
            foreach ($passReqList as $i => $preq):
            ?>
                <tr>
                    <td style="color:var(--muted);font-size:11px"><?= $i + 1 ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($preq["username"]) ?></td>
                    <td><?= htmlspecialchars($preq["full_name"] ?? 'N/A') ?></td>
                    <td style="font-size:11px;color:var(--muted);white-space:nowrap"><?= date("d M Y, h:i A", strtotime($preq["requested_at"])) ?></td>
                    <td>
                        <div class="tbl-actions">
                            <a href="?page=admin_dashboard&section=password_requests&approve=<?= $preq['id'] ?>&token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" class="tbl-btn tbl-btn-approve" title="Approve — apply new password" style="color:var(--green)" onclick="return confirm('Approve this password change request? The user\\'s new password will be applied immediately.')"><i class="fas fa-check"></i></a>
                            <a href="?page=admin_dashboard&section=password_requests&reject=<?= $preq['id'] ?>&token=<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>" class="tbl-btn tbl-btn-delete" title="Reject — discard request" style="color:#dc2626" onclick="return confirm('Reject this password change request? The user will keep their current password.')"><i class="fas fa-times"></i></a>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($passReqList)): ?><tr><td colspan="5"><div class="empty-state"><i class="fas fa-check-circle"></i><p>No pending password change requests.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- History panel (recent decisions) -->
<div class="panel">
    <div class="panel-header"><div class="panel-title"><i class="fas fa-clock-rotate-left"></i> Recent History (Last 10)</div></div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead><tr><th>Username</th><th>Status</th><th>Requested At</th></tr></thead>
            <tbody>
            <?php
            try {
                $passHistory = $pdo->query("SELECT r.*, u.username FROM password_change_requests r JOIN users u ON r.user_id=u.id ORDER BY r.requested_at DESC LIMIT 10")->fetchAll();
            } catch (Exception $e) { $passHistory = []; }
            foreach ($passHistory as $ph):
                $stCls = $ph['status'] === 'approved' ? 'status-active' : ($ph['status'] === 'rejected' ? 'status-inactive' : 'status-pending');
            ?>
                <tr>
                    <td style="font-weight:600"><?= htmlspecialchars($ph['username']) ?></td>
                    <td><span class="status-badge <?= $stCls ?>"><i class="fas fa-circle"></i> <?= ucfirst($ph['status']) ?></span></td>
                    <td style="font-size:11px;color:var(--muted)"><?= date("d M Y, h:i A", strtotime($ph['requested_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($passHistory)): ?><tr><td colspan="3"><div class="empty-state"><p>No history yet.</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif (($section === "add" && hasPerm('add_news')) || ($section === "edit" && hasPerm('manage_news'))): ?>
<?php
 $fD = ($section === "edit" && $editData) ? $editData : $addData;
 $fE = ($section === "edit") ? $editError : $addError;
 $fS = ($section === "edit") ? $editSuccess : $addSuccess;
 $fA = ($section === "edit") ? "?page=admin_dashboard&section=edit&id=".$fD["id"] : "?page=admin_dashboard&section=add";
 $pT = ($section === "edit") ? "Edit News" : "Add News";
?>
<div class="admin-topbar"><h1><i class="fas fa-<?= $section === 'edit' ? 'pen' : 'plus-circle' ?>" style="color:var(--accent)"></i> <?= $pT ?></h1><div class="topbar-actions"><?php if (hasPerm('manage_news')): ?><a href="?page=admin_dashboard&section=manage" class="topbar-btn topbar-btn-outline"><i class="fas fa-arrow-left"></i> Back to List</a><?php endif; ?></div></div>
<?php if ($fE): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($fE) ?></div><?php endif; ?>
<?php if ($fS): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($fS) ?></div><?php endif; ?>
<form method="POST" action="<?= $fA ?>" enctype="multipart/form-data">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-image"></i> Feature Image</div></div><div class="panel-body">
<div class="img-upload-area <?= !empty($fD["image"]) ? 'has-image' : '' ?>" id="newsImgArea">
<input type="file" name="news_image" id="news_image" accept="image/jpeg,image/png,image/gif/image/webp">
<?php if (!empty($fD["image"])): ?>
<img src="<?= htmlspecialchars($fD["image"]) ?>" class="img-preview" id="newsImgPreview" alt="">
<div style="margin-top:6px;font-size:11px;color:var(--green);font-weight:600"><i class="fas fa-check-circle"></i> Image exists — selecting a new file will replace it</div>
<?php else: ?>
<div class="img-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
<div class="img-upload-text">Click to select image</div>
<div class="img-upload-hint">JPG, PNG, GIF, WebP — Maximum 5MB</div>
<img src="" class="img-preview" id="newsImgPreview" style="display:none" alt="">
<?php endif; ?>
</div></div></div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-pen-nib"></i> News Details</div></div><div class="panel-body">
<div class="form-group"><label class="form-label">Title <span style="color:var(--accent)">*</span></label><input type="text" name="title" class="form-input" value="<?= htmlspecialchars($fD["title"]) ?>" placeholder="News title" required></div>
<div class="form-row">
    <div class="form-group">
        <label class="form-label">Single Post Headline (Optional)</label>
        <input type="text" name="headline" class="form-input" value="<?= htmlspecialchars($fD["headline"] ?? '') ?>" placeholder="Custom headline for single post view">
        <div class="form-hint">Leave empty to use the main Title as headline.</div>
    </div>
    <div class="form-group">
        <label class="form-label">Author</label>
        <input type="text" name="author" class="form-input" value="<?= htmlspecialchars($fD["author"]) ?>">
    </div>
</div>

<?php if ($currentUserPerms === null): // Super Admin ?>
<div class="form-group">
    <label class="form-label">Publish Date & Time</label>
    <input type="datetime-local" name="news_created_at" class="form-input" value="<?= !empty($fD["created_at"]) ? date('Y-m-d\TH:i', strtotime($fD["created_at"])) : date('Y-m-d\TH:i') ?>">
    <div class="form-hint">Super Admin can directly change the publish date.</div>
</div>
<?php else: // Other Users ?>
<div class="form-group">
    <label class="form-label">Current Publish Date</label>
    <input type="datetime-local" class="form-input" value="<?= !empty($fD["created_at"]) ? date('Y-m-d\TH:i', strtotime($fD["created_at"])) : date('Y-m-d\TH:i') ?>" disabled>
    <div style="margin-top:8px; padding:10px; background:var(--bg); border-radius:8px; border:1px dashed var(--border);">
        <label class="form-label" style="font-size:12px; color:var(--purple);">Request Date Change (Requires Admin Approval)</label>
        <input type="datetime-local" name="requested_date" class="form-input" style="margin-bottom:8px;">
        <small class="form-hint">If you want to change the publish date, select the new date here. It will be sent to Super Admin for approval. Leave blank to keep current date.</small>
    </div>
</div>
<?php endif; ?>

<div class="form-row"><div class="form-group"><label class="form-label">Category</label><select name="category_id" class="form-select" id="catSelect"><option value="">— Select —</option><?php foreach ($categories as $c): ?><option value="<?= $c["id"] ?>" <?= ($fD["category_id"] == $c["id"]) ? 'selected' : '' ?>><?= htmlspecialchars($c["name"]) ?></option><?php endforeach; ?></select></div><div class="form-group"><label class="form-label">Subcategories</label><select name="subcategories[]" class="form-select" id="subCatSelect" multiple style="min-height:42px"><option value="">— Select Category First —</option></select><div class="form-hint">Ctrl+Click to select multiple</div></div></div>

<!-- YouTube Video Embed Input (Outside CKEditor) -->
<div class="form-group" style="background: var(--bg); padding: 14px; border-radius: 10px; border: 2px dashed var(--border);">
    <label class="form-label" style="color: var(--accent);"><i class="fab fa-youtube" style="color:#FF0000;"></i> Insert YouTube Video</label>
    <div style="display:flex; gap:10px; align-items:center;">
        <input type="text" id="ytUrlInput" class="form-input" placeholder="YouTube ভিডিও লিংক এখানে দিন (যেমন: https://youtu.be/...)" style="flex:1;">
        <button type="button" class="btn btn-primary" onclick="insertYoutubeToEditor()">
            <i class="fas fa-arrow-down-to-bracket"></i> Embed Video
        </button>
    </div>
    <div class="form-hint" style="margin-top:6px;">টেক্সটের যেকোনো জায়গায় কার্সর রেখে এই বাটনে ক্লিক করুন।</div>
</div>

<!-- Facebook/Twitter/Instagram Video Link / Embed Code Input (Outside CKEditor) -->
<div class="form-group" style="background: var(--bg); padding: 14px; border-radius: 10px; border: 2px dashed var(--border);">
    <label class="form-label" style="color: var(--accent);"><i class="fas fa-people-arrows"></i> Insert Facebook / Twitter / Instagram Video</label>
    <div style="display:flex; gap:10px; align-items:center;">
        <input type="text" id="socialVideoUrlInput" class="form-input" placeholder="ভিডিও লিংক অথবা Embed Code দিন (Instagram / Facebook / Twitter)" style="flex:1;">
        <button type="button" class="btn btn-primary" onclick="insertSocialVideoToEditor()">
            <i class="fas fa-arrow-down-to-bracket"></i> Embed Video
        </button>
    </div>
    <div class="form-hint" style="margin-top:6px;">টেক্সটের যেকোনো জায়গায় কার্সর রেখে এই বাটনে ক্লিক করুন। ভিডিও লিংক অথবা Instagram/Facebook/Twitter এর পুরো Embed Code — যেকোনোটি দিলেই কাজ করবে।</div>
</div>

<!-- Custom Image Alignment Toolbar (Outside CKEditor) -->
<div id="imgAlignToolbar" class="form-group" style="display: none; background: var(--bg); padding: 12px 14px; border-radius: 10px; border: 2px solid var(--accent); margin-bottom: 14px; align-items: center; gap: 6px; flex-wrap: wrap;">
    <span style="font-size: 13px; font-weight: 700; margin-right: 10px; color: var(--accent);"><i class="fas fa-image"></i> Picture Tools:</span>
    
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('left')" title="Align Left"><i class="fas fa-align-left"></i></button>
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('center')" title="Align Center"><i class="fas fa-align-center"></i></button>
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('right')" title="Align Right"><i class="fas fa-align-right"></i></button>
    
    <div style="width: 1px; height: 24px; background: var(--border); margin: 0 5px;"></div>
    
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('top')" title="Align Top"><i class="fas fa-arrow-up"></i></button>
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('middle')" title="Align Middle"><i class="fas fa-arrows-up-down"></i></button>
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('bottom')" title="Align Bottom"><i class="fas fa-arrow-down"></i></button>
    
    <div style="width: 1px; height: 24px; background: var(--border); margin: 0 5px;"></div>
    
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('zoomin')" title="Zoom In"><i class="fas fa-magnifying-glass-plus"></i></button>
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('zoomout')" title="Zoom Out"><i class="fas fa-magnifying-glass-minus"></i></button>
    
    <div style="width: 1px; height: 24px; background: var(--border); margin: 0 5px;"></div>
    
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('drag')" title="Toggle Drag Mode"><i class="fas fa-up-down-left-right"></i></button>
    
    <button type="button" class="btn btn-sm btn-outline" onclick="alignImg('none')" title="Clear Formatting"><i class="fas fa-xmark"></i></button>
</div>

<div class="form-group"><label class="form-label">Content <span style="color:var(--accent)">*</span></label><textarea name="content" id="editor1" rows="12" class="form-input" style="height:400px" required><?= htmlspecialchars($fD["content"]) ?></textarea></div>

<div class="form-group" id="igEmbedPanelWrap" style="display:none">
    <label class="form-label">Video Embed Settings <span style="font-size:10px; color:var(--muted); font-weight:400">(choose a video below, or double-click one in the editor)</span></label>
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;background:#f8f9fb;border:1px solid #e2e6ee;border-radius:8px;padding:10px 12px">
        <span style="font-size:12px;font-weight:600;color:#555">Video:</span>
        <select id="igEmbedSelect" style="padding:3px 6px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;font-size:12px;max-width:200px"><option value="">— Select video —</option></select>
        <span style="color:#d5dbe6">|</span>
        <span style="font-size:12px;font-weight:600;color:#555">Zoom:</span>
        <button type="button" id="igZoomOut" style="padding:3px 12px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;cursor:pointer;font-size:14px;font-weight:700;line-height:1.4">&minus;</button>
        <span id="igZoomVal" style="font-size:12px;font-weight:700;color:var(--accent);min-width:72px;text-align:center">Auto</span>
        <button type="button" id="igZoomIn" style="padding:3px 12px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;cursor:pointer;font-size:14px;font-weight:700;line-height:1.4">+</button>
        <span style="color:#d5dbe6">|</span>
        <span style="font-size:12px;font-weight:600;color:#555">Align:</span>
        <button type="button" id="igAl_left" style="padding:3px 10px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;cursor:pointer;font-size:12px">Left</button>
        <button type="button" id="igAl_center" style="padding:3px 10px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;cursor:pointer;font-size:12px">Center</button>
        <button type="button" id="igAl_right" style="padding:3px 10px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;cursor:pointer;font-size:12px">Right</button>
        <span style="color:#d5dbe6">|</span>
        <button type="button" id="igEmbedReset" style="padding:3px 10px;border:1px solid #cfd6e4;background:#fff;border-radius:6px;cursor:pointer;font-size:12px">Reset</button>
        <span id="igEmbedHint" style="font-size:11px;color:var(--muted)">Auto zoom fits the column; manual zoom is capped to the column width.</span>
    </div>
    <!-- igEmbedPanelEnd -->
</div>

<div class="form-group">
    <label class="form-label">Tags <span style="font-size:10px; color:var(--muted); font-weight:400">(Type and press Enter)</span></label>
    <div class="tags-wrapper" id="tagsWrapper" onclick="document.getElementById('tagInputField').focus()">
        <span id="tagPillsContainer"></span>
        <input type="text" id="tagInputField" class="tag-input-field" placeholder="Add a tag and press Enter...">
        <input type="hidden" name="tags" id="hiddenTagsInput" value="<?= htmlspecialchars($fD["tags"]) ?>">
    </div>
</div>

<div class="form-row" style="align-items:center"><div class="form-group" style="margin-bottom:0"><label class="form-label">Status</label><div class="radio-group"><div class="radio-item <?= $fD["status"] === 'draft' ? 'selected' : '' ?>"><input type="radio" name="status" value="draft" <?= $fD["status"] === 'draft' ? 'checked' : '' ?> onchange="this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Draft</label></div><div class="radio-item <?= $fD["status"] === 'published' ? 'selected' : '' ?>"><input type="radio" name="status" value="published" <?= $fD["status"] === 'published' ? 'checked' : '' ?> onchange="this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Published</label></div></div></div><div class="form-group" style="margin-bottom:0"><label class="form-label">Featured</label><label class="switch"><input type="checkbox" name="is_featured" value="1" <?= $fD["is_featured"] ? 'checked' : '' ?>><span class="switch-slider"></span></label></div></div>
<div class="form-group" style="margin-bottom:0;background:linear-gradient(135deg,rgba(196,30,58,.06),rgba(212,160,23,.06));padding:14px;border:2px solid var(--accent);border-radius:10px;margin-top:14px">
    <div style="display:flex;align-items:center;gap:14px;flex-wrap:wrap">
        <label class="form-label" style="color:var(--accent);font-size:14px;margin:0;flex:1"><i class="fas fa-bolt" style="color:var(--gold);margin-right:6px"></i> Breaking News Tracker</label>
        <label class="switch" title="Mark as Breaking News"><input type="checkbox" name="is_breaking" value="1" <?= $fD["is_breaking"] ? 'checked' : '' ?>><span class="switch-slider"></span></label>
        <span style="font-size:12px;font-weight:600;color:<?= $fD["is_breaking"] ? 'var(--accent)' : 'var(--muted)' ?>" id="brkStatusLabel"><i class="fas fa-<?= $fD["is_breaking"] ? 'bolt' : 'minus-circle' ?>"></i> <?= $fD["is_breaking"] ? 'Breaking News ON' : 'Breaking News OFF' ?></span>
    </div>
</div>
<script>
(function(){
    var cb = document.querySelector('input[name="is_breaking"]');
    if(!cb) return;
    var lbl = document.getElementById('brkStatusLabel');
    function upd(){
        if(lbl){
            lbl.innerHTML = cb.checked ? '<i class="fas fa-bolt"></i> Breaking News ON' : '<i class="fas fa-minus-circle"></i> Breaking News OFF';
            lbl.style.color = cb.checked ? 'var(--accent)' : 'var(--muted)';
        }
    }
    cb.addEventListener('change', upd);
})();
</script>
</div><div class="form-actions-bar"><?php if (hasPerm('manage_news')): ?><a href="?page=admin_dashboard&section=manage" class="btn btn-outline"><i class="fas fa-times"></i> Cancel</a><?php endif; ?><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> <?= $section === 'edit' ? 'Update' : 'Save' ?></button></div></div></form>
<script>
var cs = document.getElementById('catSelect'), ss = document.getElementById('subCatSelect'), sel = <?= json_encode(array_map('intval', $fD["subcategories"] ?? [])) ?>;
if (cs && ss) {
    cs.onchange = function() {
        var v = this.value;
        ss.innerHTML = '<option>Loading...</option>';
        if (!v) { ss.innerHTML = '<option>— Select Category —</option>'; return; }
        fetch('?page=admin_dashboard&section=api_subcategories&category_id=' + v).then(function(r) { return r.json(); }).then(function(d) {
            ss.innerHTML = '';
            if (!d.length) { ss.innerHTML = '<option>No Subcategories</option>'; return; }
            d.forEach(function(s) { 
                var o = document.createElement('option'); 
                o.value = s.id; 
                o.textContent = s.name; 
                if (sel.indexOf(parseInt(s.id)) !== -1) o.selected = true; 
                ss.appendChild(o); 
            });
        }).catch(function() { ss.innerHTML = '<option>Load Failed</option>'; });
    };
    if (cs.value) cs.dispatchEvent(new Event('change'));
}
var fi = document.getElementById('news_image'), pr = document.getElementById('newsImgPreview'), ar = document.getElementById('newsImgArea');
if (fi) fi.onchange = function() { if (this.files && this.files[0]) { var r = new FileReader(); r.onload = function(e) { if (pr) { pr.src = e.target.result; pr.style.display = 'block'; } if (ar) ar.classList.add('has-image'); }; r.readAsDataURL(this.files[0]); } };

// CKEditor Initialization
if (typeof CKEDITOR !== 'undefined') {
    CKEDITOR.replace('editor1', {
        height: 400,
        // ===== Added 18 new plugins for a much richer toolbar =====
        //   emoji            -> emoji panel picker (type :smile)
        //   uploadimage      -> drag-and-drop image upload (inline base64)
        //   copyformatting   -> paint-format brush (copy styles between elements)
        //   codesnippet      -> syntax-highlighted code blocks (JS/PHP/Python/etc.)
        //   mathjax          -> LaTeX math formulas (inline + display)
        //   magicline        -> magic red line for cursor placement in tricky spots
        //   autogrow         -> editor grows automatically with content (400-900px)
        //   tableresize      -> drag table column borders to resize
        //   tableselection   -> multi-cell selection in tables
        //   tabletools       -> row/column/cell operations toolbar
        //   sourcedialog     -> Source editor as a popup dialog (alternative)
        //   colordialog      -> full color picker dialog (any RGB/HEX)
        //   a11yhelp         -> accessibility help dialog (Alt+0)
        //   liststyle        -> list properties (Roman, Alpha, custom start number)
        //   placeholder      -> insert named placeholder token
        //   templates        -> pre-defined content templates
        //   preview,print    -> preview & print current content
        //   pastefromword    -> cleaned paste from Microsoft Word
        //   NOTE: the 'iframe' plugin is deliberately NOT loaded — its dialog round-trip rewrites every
        //   inserted <iframe>: it strips the style attribute (embeds lose their width/height -> tiny
        //   players) and adds sandbox="" (blocks the player JS, so embedded videos cannot play).
        //   Without the plugin, iframes inserted via the URL inputs stay exactly as generated.
        extraPlugins: 'justify,colorbutton,font,emoji,uploadimage,copyformatting,codesnippet,mathjax,magicline,autogrow,tableresize,tableselection,tabletools,sourcedialog,colordialog,a11yhelp,liststyle,placeholder,templates,preview,print,pastefromword',
        // ACF fully disabled: admin-trusted content must be saved exactly as inserted.
        // (CKEditor's default content filter was silently stripping style properties — width:100%,
        //  max-width, margin, border, position... — from embedded <iframe>/<div> markup, shrinking
        //  Instagram/YouTube/Facebook embeds even after the iframe plugin was removed.)
        allowedContent: true,
        // Only remove Save (handled by form submit button) and NewPage (would wipe content)
        removeButtons: 'Save,NewPage',
        toolbar: [
            { name: 'document', items: ['Source','-','Sourcedialog','-','Templates','DocProps','Preview','Print'] },
            { name: 'clipboard', items: ['Cut','Copy','Paste','PasteText','PasteFromWord','-','Undo','Redo','-','CopyFormatting'] },
            { name: 'editing', items: ['Find','Replace','-','SelectAll','-','SpellChecker','Scayt','-','A11yHelp'] },
            { name: 'forms', items: ['Form','Checkbox','Radio','TextField','Textarea','Select','Button','ImageButton','HiddenField'] },
            '/',
            { name: 'basicstyles', items: ['Bold','Italic','Underline','Strike','Subscript','Superscript','-','CopyFormatting','-','RemoveFormat'] },
            { name: 'paragraph', items: ['NumberedList','BulletedList','-','Outdent','Indent','-','Blockquote','CreateDiv','-','JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock','-','BidiLtr','BidiRtl','Language'] },
            { name: 'links', items: ['Link','Unlink','Anchor'] },
            { name: 'insert', items: ['Image','Flash','Table','HorizontalRule','Smiley','EmojiPanel','SpecialChar','PageBreak','CodeSnippet','Mathjax','Placeholder'] },
            '/',
            { name: 'styles', items: ['Styles','Format','Font','FontSize'] },
            { name: 'colors', items: ['TextColor','BGColor','Colordialog'] },
            { name: 'tools', items: ['Maximize','ShowBlocks','-','About'] }
        ],
        // ===== Plugin-specific configuration =====
        // Emoji: trigger picker after 2+ chars following a colon (e.g. :smi)
        emoji_minChars: 2,
        // MathJax: load MathJax 2.7 from CDN for LaTeX rendering in editor & on page
        mathJaxLib: 'https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.9/MathJax.js?config=TeX-AMS_HTML',
        mathJaxClass: 'math-tex',
        // Code Snippet: dark syntax highlighting theme (matches VS Code Monokai)
        codeSnippet_theme: 'monokai_sublime',
        // Auto-grow: editor expands from 400px up to 900px as content grows
        autoGrow_onStartup: true,
        autoGrow_minHeight: 400,
        autoGrow_maxHeight: 900,
        // Image upload via drag-drop (uploadimage plugin) - embeds as base64 by default
        uploadimage_supportedTypes: /(png|jpe?g|gif|webp)$/i,
        // Copy Formatting: Ctrl+Shift+C copy / Ctrl+Shift+V paste styles
        copyFormatting_allowRules: true,
        copyFormatting_disallowRules: 'script;style;*[id,onclick,onmouseover]',
        // Source dialog indentation
        sourceAreaTabSize: 4,
        // Allow toolbar collapse on small screens
        toolbarCanCollapse: true,
        toolbarStartupExpanded: true,
        // Hide rarely-used advanced tabs to keep dialogs simple
        removeDialogTabs: 'image:advanced;link:target;link:advanced'
    });

    // ========================================== ★ FIXED Picture Toolbar v11 (PARENT BLOCK APPROACH) ==========================================
    // v11: Center alignment — image এর parent paragraph খুঁজে সেটার উপর text-align:center set করি
    // কোনো wrap/unwrap নয়, কোনো insertHtml নয় — সবচেয়ে নির্ভরযোগ্য পদ্ধতি
    // ==================================================================================================================

    window.__selectedImg = null;

    function __bindImgSelectionListener(editor) {
        if (!editor || editor.__imgListenerBound) return;
        editor.__imgListenerBound = true;

        editor.on('selectionChange', function(e) {
            var ed = e.editor;
            var sel = ed.getSelection();
            var selectedElement = sel ? sel.getSelectedElement() : null;
            var img = null;
            if (selectedElement && selectedElement.is && selectedElement.is('img')) {
                img = selectedElement;
            } else if (selectedElement && selectedElement.findOne) {
                try { img = selectedElement.findOne('img'); } catch (e) {}
            }
            if (!img) {
                try {
                    var ranges = sel.getRanges();
                    if (ranges && ranges.length > 0) {
                        var node = ranges[0].startContainer;
                        if (node && node.type == CKEDITOR.NODE_TEXT && node.getParent()) {
                            node = node.getParent();
                        }
                        for (var i = 0; i < 6 && node; i++) {
                            if (node.type == CKEDITOR.NODE_ELEMENT) {
                                if (node.is && node.is('img')) { img = node; break; }
                                try {
                                    var found = node.findOne ? node.findOne('img') : null;
                                    if (found) { img = found; break; }
                                } catch (e) {}
                            }
                            node = node.getParent ? node.getParent() : null;
                        }
                    }
                } catch (err) {}
            }
            var toolbar = document.getElementById('imgAlignToolbar');
            if (!toolbar) return;
            if (img) {
                window.__selectedImg = img;
                toolbar.style.display = 'flex';
            } else {
                if (!window.__selectedImg) {
                    toolbar.style.display = 'none';
                }
            }
        });

        var editable = editor.editable();
        if (editable) {
            editable.on('click', function(ev) {
                setTimeout(function() {
                    var sel = editor.getSelection();
                    var selectedElement = sel ? sel.getSelectedElement() : null;
                    var img = null;
                    if (selectedElement && selectedElement.is && selectedElement.is('img')) {
                        img = selectedElement;
                    } else if (selectedElement && selectedElement.findOne) {
                        try { img = selectedElement.findOne('img'); } catch (e) {}
                    }
                    var toolbar = document.getElementById('imgAlignToolbar');
                    if (!toolbar) return;
                    if (img) {
                        window.__selectedImg = img;
                        toolbar.style.display = 'flex';
                    } else {
                        window.__selectedImg = null;
                        toolbar.style.display = 'none';
                    }
                }, 50);
            });
        }
    }

    __bindImgSelectionListener(CKEDITOR.instances.editor1);
    CKEDITOR.instances.editor1.on('instanceReady', function() {
        __bindImgSelectionListener(CKEDITOR.instances.editor1);
    });
}

function __findImgFromCurrentSelection(editor) {
    var sel = editor.getSelection();
    if (!sel) return null;
    var selectedElement = sel.getSelectedElement();
    if (selectedElement && selectedElement.is && selectedElement.is('img')) {
        return selectedElement;
    }
    if (selectedElement && selectedElement.findOne) {
        try {
            var inner = selectedElement.findOne('img');
            if (inner) return inner;
        } catch (e) {}
    }
    try {
        var ranges = sel.getRanges();
        if (ranges && ranges.length > 0) {
            var node = ranges[0].startContainer;
            if (node && node.type == CKEDITOR.NODE_TEXT && node.getParent()) {
                node = node.getParent();
            }
            for (var i = 0; i < 6 && node; i++) {
                if (node.type == CKEDITOR.NODE_ELEMENT) {
                    if (node.is && node.is('img')) return node;
                    try {
                        var found = node.findOne ? node.findOne('img') : null;
                        if (found) return found;
                    } catch (e) {}
                }
                node = node.getParent ? node.getParent() : null;
            }
        }
    } catch (err) {}
    return null;
}

// ★ v11: image এর parent block (p/div/h1-h6) খুঁজে দেয়
function __findParentBlock(img) {
    try {
        var node = img.getParent();
        for (var i = 0; i < 8 && node; i++) {
            if (node.type == CKEDITOR.NODE_ELEMENT) {
                var name = node.getName ? node.getName() : '';
                if (name === 'p' || name === 'div' || name === 'h1' || name === 'h2' ||
                    name === 'h3' || name === 'h4' || name === 'h5' || name === 'h6' ||
                    name === 'blockquote' || name === 'li' || name === 'td' || name === 'figure') {
                    return node;
                }
            }
            node = node.getParent ? node.getParent() : null;
        }
    } catch (e) {}
    return null;
}

// ★ Function to manipulate selected image inside CKEditor (FIXED v11)
function alignImg(type) {
    if (typeof CKEDITOR === 'undefined' || !CKEDITOR.instances.editor1) {
        alert('Editor is not loaded yet. Please wait and try again.');
        return;
    }
    var editor = CKEDITOR.instances.editor1;

    var img = window.__selectedImg;
    if (img) {
        try {
            var p = img.getParent ? img.getParent() : null;
            if (!p || !img.is || !img.is('img')) {
                img = null;
                window.__selectedImg = null;
            }
        } catch (e) {
            img = null;
            window.__selectedImg = null;
        }
    }
    if (!img) {
        img = __findImgFromCurrentSelection(editor);
    }

    if (!img) {
        alert('Please click on an image inside the editor first.');
        return;
    }

    editor.focus();

    try {
        // ★ v11 CENTER: parent block এ text-align:center set করি
        if (type === 'center') {
            // Image এর alignment styles পরিষ্কার করি
            img.removeStyle('float');
            img.removeStyle('margin-left');
            img.removeStyle('margin-right');
            img.removeStyle('margin-top');
            img.removeStyle('margin-bottom');
            img.removeStyle('vertical-align');
            img.removeStyle('clear');
            img.removeAttribute('align');
            // Image কে inline-block করি যাতে parent এর text-align কাজ করে
            img.setStyle('display', 'inline-block');
            // Parent block খুঁজি
            var parentBlock = __findParentBlock(img);
            if (parentBlock) {
                // ★ parent block এ text-align:center set করি
                parentBlock.setStyle('text-align', 'center');
            } else {
                // Parent না পেলে image কে centered paragraph এ wrap করার চেষ্টা করি
                try {
                    var imgHtml = img.getOuterHtml();
                    editor.getSelection().selectElement(img);
                    editor.insertHtml('<p style="text-align:center;">' + imgHtml + '</p>');
                } catch (e) {}
            }
        }
        // ★ LEFT: parent text-align clear করি, image এ float:left
        else if (type === 'left') {
            img.removeStyle('float');
            img.removeStyle('display');
            img.removeStyle('margin-left');
            img.removeStyle('margin-right');
            img.removeStyle('margin-top');
            img.removeStyle('margin-bottom');
            img.removeStyle('vertical-align');
            img.removeStyle('clear');
            img.removeAttribute('align');
            // Parent এর text-align clear করি (যাতে center থেকে left হয়)
            var pb1 = __findParentBlock(img);
            if (pb1) pb1.removeStyle('text-align');
            // float left set করি
            img.setStyle('float', 'left');
            img.setAttribute('align', 'left');
            img.setStyle('margin-right', '15px');
            img.setStyle('margin-bottom', '10px');
        }
        // ★ RIGHT: parent text-align clear, image এ float:right
        else if (type === 'right') {
            img.removeStyle('float');
            img.removeStyle('display');
            img.removeStyle('margin-left');
            img.removeStyle('margin-right');
            img.removeStyle('margin-top');
            img.removeStyle('margin-bottom');
            img.removeStyle('vertical-align');
            img.removeStyle('clear');
            img.removeAttribute('align');
            var pb2 = __findParentBlock(img);
            if (pb2) pb2.removeStyle('text-align');
            img.setStyle('float', 'right');
            img.setAttribute('align', 'right');
            img.setStyle('margin-left', '15px');
            img.setStyle('margin-bottom', '10px');
        }
        // ★ VERTICAL ALIGNMENT: top/middle/bottom/none
        else if (type === 'top' || type === 'middle' || type === 'bottom' || type === 'none') {
            img.removeStyle('float');
            img.removeStyle('display');
            img.removeStyle('margin-left');
            img.removeStyle('margin-right');
            img.removeStyle('margin-top');
            img.removeStyle('margin-bottom');
            img.removeStyle('vertical-align');
            img.removeStyle('clear');
            img.removeAttribute('align');
            img.removeAttribute('valign');
            // Parent এর text-align clear করি
            var pb3 = __findParentBlock(img);
            if (pb3) pb3.removeStyle('text-align');
            // ★ inline-block required for vertical-align to work
            img.setStyle('display', 'inline-block');
            if (type === 'top') {
                img.setStyle('vertical-align', 'top');
                img.setAttribute('valign', 'top');
            } else if (type === 'middle') {
                img.setStyle('vertical-align', 'middle');
                img.setAttribute('valign', 'middle');
            } else if (type === 'bottom') {
                img.setStyle('vertical-align', 'bottom');
                img.setAttribute('valign', 'bottom');
            }
        }
        // ★ ZOOM: in/out
        else if (type === 'zoomin' || type === 'zoomout') {
            var currentWidth = img.getStyle('width') || img.getAttribute('width');
            if (!currentWidth) {
                currentWidth = (img.$ && img.$.width) ? img.$.width :
                               (img.$ && img.$.naturalWidth) ? img.$.naturalWidth : 300;
            }
            var numWidth = parseInt(currentWidth, 10);
            if (!isNaN(numWidth)) {
                if (type === 'zoomin') {
                    numWidth = Math.round(numWidth * 1.1);
                } else {
                    numWidth = Math.max(50, Math.round(numWidth * 0.9));
                }
                img.setStyle('width', numWidth + 'px');
                img.setStyle('height', 'auto');
                img.removeAttribute('width');
                img.removeAttribute('height');
            }
        }
        // ★ DRAG MODE: True toggle
        else if (type === 'drag') {
            var isCurrentlyDraggable = img.getAttribute('draggable') === 'true';
            if (isCurrentlyDraggable) {
                img.removeAttribute('draggable');
                img.removeStyle('outline');
                img.removeStyle('cursor');
                img.removeClass('cke_draggable_img');
                try { editor.getSelection().selectElement(img); } catch (e) {}
                alert('✓ Drag mode DISABLED');
            } else {
                editor.getSelection().selectElement(img);
                img.setAttribute('draggable', 'true');
                img.setStyle('outline', '2px dashed #C41E3A');
                img.setStyle('cursor', 'move');
                img.addClass('cke_draggable_img');
                img.on('dragstart', function(ev) {
                    try {
                        if (ev.data && ev.data.$ && ev.data.$.dataTransfer) {
                            ev.data.$.dataTransfer.setData('text/html', img.getOuterHtml());
                            ev.data.$.dataTransfer.effectAllowed = 'move';
                        }
                    } catch (e) {}
                });
                alert('✓ Drag mode ENABLED!\n\nছবির উপর মাউসের বাম বোতাম চেপে ধরে রেখে যেকোনো জায়গায় নিয়ে যান।\nআবার Drag বাটনে চাপলে disable হবে।');
            }
        }
        else {
            return;
        }

        try {
            editor.getSelection().selectElement(img);
        } catch (e) {}
        window.__selectedImg = img;
        try { editor.fire('selectionChange'); } catch (e) {}
    } catch (err) {
        alert('Picture tool failed: ' + (err && err.message ? err.message : 'unknown error'));
    }
}

// Function to insert YouTube video into CKEditor at cursor position
function insertYoutubeToEditor() {
    var url = document.getElementById('ytUrlInput').value;
    if (!url) { alert('Please enter a YouTube URL.'); return; }
    var match = url.match(/(?:youtube\.com\/(?:[^\/]+\/.+\/|(?:v|e(?:mbed)?)\/|.*[?&]v=)|youtu\.be\/)([^"&?\/\s]{11})/);
    if (match && match[1]) {
        var videoId = match[1];
        var html = '<div style="position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 15px 0;">' +
                   '<iframe src="https://www.youtube.com/embed/' + videoId + '" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;" allowfullscreen></iframe>' +
                   '</div><p>&nbsp;</p>';
        if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.editor1) {
            CKEDITOR.instances.editor1.insertHtml(html);
            document.getElementById('ytUrlInput').value = '';
        } else { alert('Editor is not loaded yet!'); }
    } else { alert('Invalid YouTube URL. Please enter a valid link.'); }
}

// Function to insert Facebook/Twitter/Instagram video into CKEditor at cursor position
// Accepts BOTH a plain video URL and a full embed code (Instagram blockquote, FB plugin iframe, Twitter blockquote).
// The frontend script that ships INSIDE every Instagram embed (auto-fit + zoom support).
// Kept as a shared constant: the payload uses it on insert, and the editor-side repair
// rewrites the script of embeds saved by OLDER builds (their sc() predates data-zoom /
// data-align support) so old articles pick up the new behaviour just by opening and
// re-saving them here.
var IG_EMBED_SCRIPT = '<script>(function(){if(window.__igFit)return;window.__igFit=1;function ok(o){try{var h=String(o||"").split("/")[2];return /(^|\\.)instagram\\.com$/.test(h)}catch(e){return false}}function sc(f){var wrap=f.parentNode;if(!wrap||!wrap.getAttribute||wrap.getAttribute("data-ig-wrap")!=="1")return;var p=wrap.parentNode,pw=p?(p.clientWidth||p.offsetWidth||538):538;if(!pw)pw=538;var z=parseFloat(wrap.getAttribute("data-zoom"));var s=(z>=0.5&&z<=2)?Math.min(z,pw/538):Math.max(0.5,Math.min(1.4,pw/538));var al=wrap.getAttribute("data-align");wrap.style.marginLeft=(al==="left")?"0":"auto";wrap.style.marginRight=(al==="right")?"0":"auto";wrap.style.maxWidth=(z>=0.5&&z<=2)?"none":"";var h=parseFloat(f.style.height)||672;if(s>1.001||s<0.999){f.style.transform="scale("+s+")";f.style.maxWidth="none";wrap.style.width=Math.round(538*s)+"px";wrap.style.height=Math.round(h*s)+"px"}else{f.style.transform="";f.style.maxWidth="";wrap.style.width="";wrap.style.height=""}}window.addEventListener("message",function(ev){if(!ok(ev.origin))return;var d=ev.data;if(typeof d==="string"){try{d=JSON.parse(d)}catch(e){return}}if(!d||typeof d!=="object"||!d.details||typeof d.details!=="object")return;var fs=document.querySelectorAll("iframe[data-ig-auto]");for(var i=0;i<fs.length;i++){var f=fs[i],w=null;try{w=f.contentWindow}catch(e){}if(w!==ev.source)continue;var h=(typeof d.details.height==="number"&&d.details.height>0)?d.details.height:null;if(!h&&d.details.styles&&d.details.styles.length){for(var j=0;j<d.details.styles.length;j++){if(d.details.styles[j][0]==="height"){h=parseFloat(d.details.styles[j][1])||null;break}}}if(h&&h>200&&h<2500){f.style.height=Math.round(h)+"px";sc(f)}break}});var fi=document.querySelectorAll("iframe[data-ig-auto]");for(var k=0;k<fi.length;k++){sc(fi[k])}})();<\/script>';

function insertSocialVideoToEditor() {
    var raw = document.getElementById('socialVideoUrlInput').value.trim();
    if (!raw) { alert('Please enter a Facebook, Twitter or Instagram video URL or embed code.'); return; }

    // ★ Embed-code support: if the pasted input is an HTML snippet (embed code) instead of a plain URL,
    //   extract the real video permalink from it, then continue through the normal URL flow below.
    var url = raw;
    if (raw.indexOf('<') !== -1) {
        var ex = raw.match(/data-instgrm-permalink=["']([^"']+)["']/i)
              || raw.match(/href=["']([^"']*(?:instagram\.com|instagr\.am|facebook\.com|fb\.watch|twitter\.com|x\.com)\/[^"']*)["']/i)
              || raw.match(/plugins\/video\.php\?[^"'\s]*?href=((?:https?%3[aA]%2[fF]%2[fF]|https?:\/\/)[^"'\s&]+)/i)
              || raw.match(/src=["']([^"']*(?:instagram\.com|instagr\.am|facebook\.com|fb\.watch|twitter\.com|x\.com)\/[^"']*)["']/i);
        if (ex && ex[1] && ex[1].indexOf('embed.js') === -1) {
            url = ex[1].replace(/&amp;/g, '&');
            try { if (/^https?%3[aA]%2[fF]%2[fF]/.test(url)) url = decodeURIComponent(url); } catch (e) { /* keep as-is */ }
            if (url.indexOf('//') === 0) url = 'https:' + url;
        } else {
            alert('Embed code থেকে ভিডিও লিংক পাওয়া যায়নি। আসল ভিডিও লিংক অথবা সঠিক embed code দিন।');
            return;
        }
    }

    var html = '';
    var isFB = url.indexOf('facebook.com') !== -1 || url.indexOf('fb.watch') !== -1;
    var isTwitter = url.indexOf('twitter.com') !== -1 || url.indexOf('x.com') !== -1;
    var isInstagram = url.indexOf('instagram.com') !== -1 || url.indexOf('instagr.am') !== -1;
    
    if (isFB) {
        var encodedUrl = encodeURIComponent(url);
        html = '<div style="position: relative; max-width: 100%; margin: 15px 0;">' +
               '<iframe src="https://www.facebook.com/plugins/video.php?height=400&href=' + encodedUrl + '&show_text=false&width=560" style="border:none;overflow:hidden;width:100%;height:400px;max-width:560px;display:block;margin:auto;" scrolling="no" frameborder="0" allowfullscreen></iframe>' +
               '</div><p>&nbsp;</p>';
    } else if (isTwitter) {
        var match = url.match(/\/status\/(\d+)/);
        if (match && match[1]) {
            var tweetId = match[1];
            html = '<div style="position: relative; max-width: 100%; margin: 15px auto; text-align: center;">' +
                   '<iframe src="https://platform.twitter.com/embed/Tweet.html?id=' + tweetId + '" style="border:none;overflow:hidden;width:100%;max-width:550px;height:400px;display:block;margin:auto;" scrolling="no" frameborder="0" allowfullscreen></iframe>' +
                   '</div><p>&nbsp;</p>';
        } else {
            alert('Invalid Twitter URL. Make sure it includes /status/ID');
            return;
        }
    } else if (isInstagram) {
        var igMatch = url.match(/(?:instagram\.com|instagr\.am)\/(?:[A-Za-z0-9_.]+\/)?(p|reels?|tv)\/([A-Za-z0-9_-]+)/);
        if (igMatch && igMatch[2]) {
            var igType = igMatch[1] === 'reels' ? 'reel' : igMatch[1];
            // ★ Auto-fit + auto-ZOOM embed: a tiny script ships inside the embed and listens to the IG embed
            //   page's own postMessage ({"type":"MEASURE","details":{"height":N}}) and resizes the iframe to
            //   Instagram's natural height. Instagram's /embed page only renders at <=538px CSS width (wider
            //   iframes go blank), and at 538px it letterboxes the video to ~380px — so the wrapper scales
            //   the whole card up to the article column width (max 1.4x) via CSS transform: the video plays
            //   big, and on narrow/mobile columns it scales down responsively. 672px is only the initial
            //   height until the embed reports its real size.
            html = '<div data-ig-wrap="1" style="position: relative; max-width: 760px; margin: 15px auto;">' +
                   '<iframe src="https://www.instagram.com/' + igType + '/' + igMatch[2] + '/embed" data-ig-auto="1" style="border:none;overflow:hidden;width:538px;max-width:100%;height:672px;display:block;margin:0 auto;transform-origin:top left;" scrolling="no" frameborder="0" allowfullscreen></iframe>' +
                   IG_EMBED_SCRIPT +
                   '</div><p>&nbsp;</p>';
        } else {
            alert('Invalid Instagram URL. Make sure it includes /p/CODE, /reel/CODE or /tv/CODE');
            return;
        }
    } else {
        alert('Please provide a valid Facebook, Twitter or Instagram video link.');
        return;
    }
    
    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.editor1) {
        CKEDITOR.instances.editor1.insertHtml(html);
        document.getElementById('socialVideoUrlInput').value = '';
    } else { alert('Editor is not loaded yet!'); }
}

// ★ Instagram embed auto-fit (editor side): mirror of the listener that ships inside each
//   Instagram embed. The IG embed page posts its real size via postMessage —
//   {"type":"MEASURE","details":{"height":N}} and {"type":"MOUNTED","details":{"styles":[["height","Npx"]]}} —
//   so the iframe is resized to the true content height while editing (video always big, no black bars).
window.__igOkOrigin = function(o) { try { var h = String(o || '').split('/')[2]; return /(^|\.)instagram\.com$/.test(h); } catch (e) { return false; } };
// Scale the embed card to the article column width (Instagram's /embed page only renders
// at <=538px CSS width and letterboxes the video to ~380px there — transform zoom makes
// the video play big on wide columns and shrinks responsively on mobile). A manual zoom
// (wrapper data-zoom, 0.5-2.0) overrides the auto fit; data-align sets left/center/right.
window.__igScaleFrame = function(f) {
    var wrap = f.parentNode;
    if (!wrap || !wrap.getAttribute || wrap.getAttribute('data-ig-wrap') !== '1') return;
    var p = wrap.parentNode, pw = p ? (p.clientWidth || p.offsetWidth || 538) : 538;
    if (!pw) pw = 538;
    var z = parseFloat(wrap.getAttribute('data-zoom'));
    var manual = (z >= 0.5 && z <= 2);
    var s = manual ? Math.min(z, pw / 538) : Math.max(0.5, Math.min(1.4, pw / 538));
    var al = wrap.getAttribute('data-align');
    wrap.style.marginLeft = (al === 'left') ? '0' : 'auto';
    wrap.style.marginRight = (al === 'right') ? '0' : 'auto';
    wrap.style.maxWidth = manual ? 'none' : '';
    var h = parseFloat(f.style.height) || 672;
    if (s > 1.001 || s < 0.999) {
        f.style.transform = 'scale(' + s + ')';
        f.style.maxWidth = 'none';
        wrap.style.width = Math.round(538 * s) + 'px';
        wrap.style.height = Math.round(h * s) + 'px';
    } else {
        f.style.transform = '';
        f.style.maxWidth = '';
        wrap.style.width = '';
        wrap.style.height = '';
    }
};
window.__igFitFrame = function(f, d) {
    var h = (typeof d.details.height === 'number' && d.details.height > 0) ? d.details.height : null;
    if (h === null && d.details.styles && d.details.styles.length) {
        for (var j = 0; j < d.details.styles.length; j++) { if (d.details.styles[j][0] === 'height') { h = parseFloat(d.details.styles[j][1]) || null; break; } }
    }
    if (h && h > 200 && h < 2500) { f.style.height = Math.round(h) + 'px'; }
    window.__igScaleFrame(f);
};
// Self-repair for embeds saved by the OLD buggy editor build: the iframe widget used to add
// sandbox="" (blocks the player JS -> no play button, no playback) and strip the style
// attribute (embed collapses to a ~300x150 default box). Opening an old article in this
// editor now restores the frames automatically; just saving the article keeps the fix.
// Editor-only CSS: let mouse events pass through the (cross-origin) video iframe so clicks /
// double-clicks on the video reach the editor document and open the settings panel. Applied
// via a stylesheet (never inline) so it is NOT serialized into the saved article HTML — on
// the frontend the video stays fully playable.
window.__igEditorCss = function(doc) {
    if (!doc || !doc.head || doc.getElementById('igEditorCss')) return;
    var st = doc.createElement('style');
    st.id = 'igEditorCss';
    st.textContent = 'iframe[data-ig-auto]{pointer-events:none !important}div[data-ig-wrap]{cursor:pointer}';
    doc.head.appendChild(st);
};
// Upgrade the script block that ships inside embeds saved by OLDER builds so its sc()
// honours data-zoom/data-align. CKEditor keeps <script> content as protected source (no
// real script element exists in the editing DOM), so the rewrite is done at the DATA level:
// getData -> replace the old embed script with the current IG_EMBED_SCRIPT -> setData,
// then re-run repair/scale on the rebuilt DOM.
window.__igUpgradeScripts = function(editor) {
    try {
        var can = String(window.IG_EMBED_SCRIPT || '');
        if (!can || !editor || !editor.getData) return;
        var data = editor.getData();
        if (data.indexOf('window.__igFit') === -1) return;
        var re = /<script[^>]*>\s*\(function\(\)\{if\(window\.__igFit\)[\s\S]*?<\/script>/;
        var m = data.match(re);
        if (!m || m[0].indexOf('data-zoom') !== -1) return;
        editor.setData(data.replace(re, function() { return can; }));
        setTimeout(function() {
            try {
                var d = editor.document.$;
                window.__igRepairDoc(d); window.__igFitDoc(d); window.__igScaleAll(d); window.__igPanelRefreshList(d);
            } catch (e) {}
        }, 300);
    } catch (e) {}
};
window.__igRepairDoc = function(doc) {
    if (!doc || !doc.querySelectorAll) return;
    window.__igEditorCss(doc);
    var fs = doc.querySelectorAll('iframe');
    for (var i = 0; i < fs.length; i++) {
        var f = fs[i];
        var src = String(f.getAttribute('src') || '');
        var host = ''; try { host = src.split('/')[2] || ''; } catch (e) {}
        var needReload = false;
        if (f.hasAttribute('sandbox')) { f.removeAttribute('sandbox'); needReload = true; }
        var st = String(f.getAttribute('style') || '');
        var isIg = /(^|\.)instagram\.com$/.test(host) && src.indexOf('/embed') !== -1;
        var isYt = /youtube(-nocookie)?\.com\/embed|youtu\.be/.test(src);
        var isFb = /facebook\.com\/plugins|fb\.watch/.test(src);
        if (isIg) {
            var wrap = f.parentNode;
            if (wrap && wrap.nodeName === 'DIV' && wrap.getAttribute('data-ig-wrap') !== '1') {
                wrap.setAttribute('data-ig-wrap', '1');
                wrap.setAttribute('style', 'position: relative; max-width: 760px; margin: 15px auto;');
            }
            // canonical embed style; keep the currently fitted height so a re-open does not reload-loop
            var curH = parseFloat(f.style.height) || 672;
            var sigSq = String(f.getAttribute('style') || '').replace(/\s+/g, '');
            if (sigSq.indexOf('width:538px') === -1 || sigSq.indexOf('transform-origin') === -1) {
                f.setAttribute('style', 'border:none;overflow:hidden;width:538px;max-width:100%;height:' + Math.round(curH) + 'px;display:block;margin:0 auto;transform-origin:top left;');
                needReload = true;
            }
            if (!f.getAttribute('data-ig-auto')) { f.setAttribute('data-ig-auto', '1'); needReload = true; }
        } else if (isYt) {
            var w = f.parentNode;
            if (w && w.nodeName === 'DIV' && String(w.getAttribute('style') || '').indexOf('padding-bottom') === -1) { w.setAttribute('style', 'position: relative; padding-bottom: 56.25%; height: 0; overflow: hidden; max-width: 100%; margin: 15px 0;'); }
            if (st.indexOf('width') === -1) { f.setAttribute('style', 'position: absolute; top: 0; left: 0; width: 100%; height: 100%; border: 0;' + st); }
        } else if (isFb) {
            if (st.indexOf('max-width') === -1) { f.setAttribute('style', 'border:none;overflow:hidden;width:100%;height:400px;max-width:560px;display:block;margin:auto;' + st); needReload = true; }
        }
        if (needReload) { try { f.src = f.src; } catch (e) {} }
    }
};
window.__igFitDoc = function(doc) {
    if (!doc || !doc.defaultView || doc.defaultView.__igFit) return;
    doc.defaultView.__igFit = 1;
    doc.defaultView.addEventListener('message', function(ev) {
        if (!window.__igOkOrigin(ev.origin)) return;
        var d = ev.data;
        if (typeof d === 'string') { try { d = JSON.parse(d); } catch (e) { return; } }
        if (!d || typeof d !== 'object' || !d.details || typeof d.details !== 'object') return;
        var fs = doc.querySelectorAll('iframe[data-ig-auto]');
        for (var i = 0; i < fs.length; i++) { var w = null; try { w = fs[i].contentWindow; } catch (e) {} if (w === ev.source) { window.__igFitFrame(fs[i], d); break; } }
    });
};
// ---- Video embed settings panel (zoom + alignment) ----
// The panel lives in the admin form under the content editor. Clicking an embed inside the
// editor selects it (cross-origin iframes report focusin with the iframe as target), then
// the buttons write data-zoom (0.5-2.0) / data-align (left|center|right) on the wrapper —
// both saved with the article and honoured by the frontend scale script.
window.__igPanelState = { iframe: null, wrap: null };
window.__igIsEmbed = function(f) {
    if (!f || f.tagName !== 'IFRAME') return false;
    var src = String(f.getAttribute('src') || '');
    return /instagram\.com/.test(src) && src.indexOf('/embed') !== -1 && f.hasAttribute('data-ig-auto');
};
window.__igPanelCurScale = function() {
    var m = String((window.__igPanelState.iframe || {}).style ? window.__igPanelState.iframe.style.transform || '' : '').match(/scale\(([0-9.]+)\)/);
    return m ? parseFloat(m[1]) : 1;
};
window.__igPanelRefresh = function() {
    var st = window.__igPanelState, v = document.getElementById('igZoomVal');
    if (!st.iframe || !v) return;
    var z = parseFloat(st.wrap.getAttribute('data-zoom'));
    v.textContent = (z >= 0.5 && z <= 2) ? Math.round(z * 100) + '%' : 'Auto (' + Math.round(window.__igPanelCurScale() * 100) + '%)';
    var al = st.wrap.getAttribute('data-align') || 'center';
    ['left', 'center', 'right'].forEach(function(a) {
        var b = document.getElementById('igAl_' + a);
        if (b) { b.style.background = (a === al) ? 'var(--accent)' : '#fff'; b.style.color = (a === al) ? '#fff' : '#333'; }
    });
};
window.__igPanelSelect = function(f) {
    if (!window.__igIsEmbed(f)) return;
    window.__igPanelState = { iframe: f, wrap: f.parentNode };
    window.__igPanelDoc = f.ownerDocument;
    var w = document.getElementById('igEmbedPanelWrap');
    if (w) w.style.display = '';
    window.__igPanelRefresh();
    try {
        var d = window.__igPanelDoc, sel = document.getElementById('igEmbedSelect');
        if (d && sel) {
            var fs = d.querySelectorAll('iframe[data-ig-auto]');
            for (var j = 0; j < fs.length; j++) { if (fs[j] === f) { sel.value = String(j); break; } }
        }
    } catch (e) {}
};
window.__igPanelZoom = function(dir) {
    var st = window.__igPanelState; if (!st.iframe) return;
    var z = parseFloat(st.wrap.getAttribute('data-zoom'));
    if (!(z >= 0.5 && z <= 2)) z = window.__igPanelCurScale() || 1;
    z = Math.round((z + dir * 0.1) * 10) / 10;
    if (z < 0.5) z = 0.5; if (z > 2) z = 2;
    st.wrap.setAttribute('data-zoom', String(z));
    window.__igScaleFrame(st.iframe);
    window.__igPanelRefresh();
};
window.__igPanelAlign = function(al) {
    var st = window.__igPanelState; if (!st.iframe) return;
    st.wrap.setAttribute('data-align', al);
    window.__igScaleFrame(st.iframe);
    window.__igPanelRefresh();
};
window.__igPanelReset = function() {
    var st = window.__igPanelState; if (!st.iframe) return;
    st.wrap.removeAttribute('data-zoom');
    st.wrap.removeAttribute('data-align');
    window.__igScaleFrame(st.iframe);
    window.__igPanelRefresh();
};
window.__igPanelFromNode = function(doc, node) {
    var n = node;
    while (n && n !== doc.body) {
        if (n.getAttribute && n.getAttribute('data-ig-wrap') === '1') {
            var f = n.querySelector('iframe[data-ig-auto]');
            if (f) window.__igPanelSelect(f);
            return;
        }
        n = n.parentNode;
    }
};
// Element-level hooking: attach mousedown/dblclick DIRECTLY on each embed wrapper. Doc-level
// listeners can be silently wiped when CKEditor re-opens the editing document (setData
// round-trips), so the watch re-hooks unhooked wrappers — idempotent via an element flag.
window.__igPanelSelectFrom = function(w) {
    if (!w || !w.getAttribute || w.getAttribute('data-ig-wrap') !== '1') return;
    var f = w.querySelector('iframe[data-ig-auto]');
    if (f) window.__igPanelSelect(f);
};
window.__igPanelHookElements = function(doc) {
    if (!doc || !doc.querySelectorAll) return;
    var ws = doc.querySelectorAll('[data-ig-wrap]');
    for (var i = 0; i < ws.length; i++) {
        var w = ws[i];
        if (w.__igPanelHooked) continue;
        w.__igPanelHooked = 1;
        w.addEventListener('mousedown', function(ev) { window.__igPanelSelectFrom(ev.currentTarget); });
        w.addEventListener('dblclick', function(ev) { window.__igPanelSelectFrom(ev.currentTarget); });
    }
};
window.__igPanelBind = function(doc) {
    if (!doc || doc.__igPanelBound) return;
    doc.__igPanelBound = 1;
    doc.addEventListener('focusin', function(ev) { var t = ev.target; if (t && t.tagName === 'IFRAME') window.__igPanelSelect(t); });
    doc.addEventListener('mousedown', function(ev) { window.__igPanelFromNode(doc, ev.target); });
    doc.addEventListener('dblclick', function(ev) { window.__igPanelFromNode(doc, ev.target); });
    window.__igPanelHookElements(doc);
};
window.__igPanelMaybeShow = function(doc) {
    if (doc && doc.querySelector && doc.querySelector('iframe[data-ig-auto]')) {
        var w = document.getElementById('igEmbedPanelWrap');
        if (w) w.style.display = '';
    }
};
// Populate the panel's video dropdown (the always-working selection path — it needs no
// click detection inside the cross-origin embed, so it works in every environment).
window.__igPanelRefreshList = function(doc) {
    var sel = document.getElementById('igEmbedSelect');
    if (!sel || !doc || !doc.querySelectorAll) return;
    window.__igPanelDoc = doc;
    var fs = doc.querySelectorAll('iframe[data-ig-auto]');
    var cur = window.__igPanelState.iframe;
    var opts = '<option value="">— Select video —</option>';
    for (var i = 0; i < fs.length; i++) {
        var m = String(fs[i].getAttribute('src') || '').match(/\/(p|reel|tv)\/([A-Za-z0-9_-]+)/);
        opts += '<option value="' + i + '">Video ' + (i + 1) + (m ? ' (' + m[2] + ')' : '') + '</option>';
    }
    sel.innerHTML = opts;
    if (cur) { for (var j = 0; j < fs.length; j++) { if (fs[j] === cur) { sel.value = String(j); break; } } }
};
(function() {
    function on(id, fn) { var b = document.getElementById(id); if (b) b.addEventListener('click', function(e) { e.preventDefault(); fn(); }); }
    on('igZoomIn', function() { window.__igPanelZoom(1); });
    on('igZoomOut', function() { window.__igPanelZoom(-1); });
    on('igAl_left', function() { window.__igPanelAlign('left'); });
    on('igAl_center', function() { window.__igPanelAlign('center'); });
    on('igAl_right', function() { window.__igPanelAlign('right'); });
    on('igEmbedReset', function() { window.__igPanelReset(); });
    var sel = document.getElementById('igEmbedSelect');
    if (sel) sel.addEventListener('change', function() {
        var d = window.__igPanelDoc;
        if (!sel.value || !d || !d.querySelectorAll) return;
        var fs = d.querySelectorAll('iframe[data-ig-auto]');
        var f = fs[parseInt(sel.value, 10)];
        if (f) window.__igPanelSelect(f);
    });
})();
if (typeof CKEDITOR !== 'undefined') {
    window.__igScaleAll = function(doc) { var fs = doc.querySelectorAll('iframe[data-ig-auto]'); for (var i = 0; i < fs.length; i++) { window.__igScaleFrame(fs[i]); } };
    CKEDITOR.on('instanceReady', function(ev) {
        try {
            var __igDoc = ev.editor.document.$;
            window.__igUpgradeScripts(ev.editor);
            window.__igRepairDoc(__igDoc); window.__igFitDoc(__igDoc); window.__igScaleAll(__igDoc);
            window.__igPanelBind(__igDoc); window.__igPanelMaybeShow(__igDoc); window.__igPanelRefreshList(__igDoc);
            ev.editor.on('doubleclick', function(e) { try { window.__igPanelFromNode(ev.editor.document.$, e.data.getTarget().$); } catch (x) {} });
            ev.editor.on('afterInsertHtml', function() { try { var d2 = ev.editor.document.$; window.__igRepairDoc(d2); window.__igScaleAll(d2); window.__igPanelMaybeShow(d2); window.__igPanelRefreshList(d2); } catch (x) {} });
        } catch (e) {}
    });
    try { var __igEd = CKEDITOR.instances.editor1; if (__igEd) { __igEd.on('contentDom', function() { try { var __igDoc = __igEd.document.$; window.__igRepairDoc(__igDoc); window.__igFitDoc(__igDoc); window.__igScaleAll(__igDoc); window.__igPanelBind(__igDoc); window.__igPanelMaybeShow(__igDoc); window.__igPanelRefreshList(__igDoc); } catch (e) {} }); } } catch (e) {}
    // Self-healing watch: CKEditor may swap the editing document object (setData round-trips,
    // source-mode toggles) WITHOUT firing contentDom — the panel listeners bound at startup
    // would then be dead. Every 1.5s, if the current doc is not bound yet, re-run repair +
    // scale + panel binding on it. All steps are idempotent, so this is cheap and safe.
    window.__igWatchTimer = setInterval(function() {
        try {
            var ed = CKEDITOR.instances.editor1;
            if (!ed || !ed.document || !ed.document.$) return;
            var d = ed.document.$;
            window.__igPanelHookElements(d);
            if (d.__igPanelBound) return;
            window.__igRepairDoc(d); window.__igFitDoc(d); window.__igScaleAll(d);
            window.__igPanelBind(d); window.__igPanelMaybeShow(d); window.__igPanelRefreshList(d);
        } catch (e) {}
    }, 1500);
}

// Modern Tag Input Logic
var tagsArray = <?= json_encode(array_filter(array_map('trim', explode(',', $fD["tags"] ?? '')))) ?>;
var tagPillsContainer = document.getElementById('tagPillsContainer');
var tagInputField = document.getElementById('tagInputField');
var hiddenTagsInput = document.getElementById('hiddenTagsInput');

function renderTagPills() {
    if (!tagPillsContainer) return;
    tagPillsContainer.innerHTML = '';
    tagsArray.forEach(function(tag, index) {
        var pill = document.createElement('span');
        pill.className = 'tag-pill';
        pill.innerHTML = '<span>'+tag+'</span> <i class="fas fa-times remove-tag" onclick="removeTag('+index+')"></i>';
        tagPillsContainer.appendChild(pill);
    });
    if (hiddenTagsInput) hiddenTagsInput.value = tagsArray.join(', ');
}

window.removeTag = function(index) {
    tagsArray.splice(index, 1);
    renderTagPills();
}

window.handleTagKeydown = function(event) {
    if (event.key === 'Enter' || event.key === ',') {
        event.preventDefault();
        var val = tagInputField.value.trim().replace(/,/g, '');
        if (val && !tagsArray.includes(val)) {
            tagsArray.push(val);
            renderTagPills();
            tagInputField.value = '';
        } else if (val && tagsArray.includes(val)) {
            tagInputField.value = '';
        }
    } else if (event.key === 'Backspace' && tagInputField.value === '') {
        tagsArray.pop();
        renderTagPills();
    }
}

if (tagInputField) {
    tagInputField.addEventListener('keydown', handleTagKeydown);
}
if (tagPillsContainer) {
    renderTagPills();
}
</script>

<?php elseif ($section === "manage" && hasPerm('manage_news')): ?>
<div class="admin-topbar"><h1><i class="fas fa-newspaper" style="color:var(--accent)"></i> Manage News</h1><div class="topbar-actions"><?php if (hasPerm('add_news')): ?><a href="?page=admin_dashboard&section=add" class="topbar-btn topbar-btn-primary"><i class="fas fa-plus"></i> Add News</a><?php endif; ?></div></div>
<?php if ($manageSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= $manageSuccess ?></div><?php endif; ?>
<div class="panel"><div class="panel-header">
<div class="filter-tabs"><?php foreach (["all"=>"All","published"=>"Published","draft"=>"Draft","featured"=>"Featured"] as $f => $l): ?><a href="?page=admin_dashboard&section=manage&filter=<?= $f ?>" class="filter-tab <?= $manageFilter === $f ? 'active' : '' ?>"><?= $l ?></a><?php endforeach; ?></div>
<form method="GET" style="display:flex;gap:6px"><input type="hidden" name="page" value="admin_dashboard"><input type="hidden" name="section" value="manage"><input type="hidden" name="filter" value="<?= $manageFilter ?>"><input type="text" name="sq" class="form-input" style="width:200px;padding:6px 10px;font-size:12px" placeholder="Search..." value="<?= htmlspecialchars($manageSearch) ?>"><button type="submit" class="btn btn-sm btn-outline"><i class="fas fa-search"></i></button></form>
</div><div class="panel-body" style="padding:0"><table class="data-table"><thead><tr><th>ID</th><th>Title</th><th>Category</th><th>Status</th><th>Publish Date & Time</th><th>Last Updated</th><th>Action</th></tr></thead><tbody>
<?php foreach ($manageNews as $mn): ?>
<tr><td style="color:var(--muted);font-size:11px"><?= $mn["id"] ?></td><td><div style="display:flex;align-items:center;gap:8px"><?php if (!empty($mn["image"])): ?><img src="<?= htmlspecialchars($mn["image"]) ?>" class="img-thumb" alt=""><?php endif; ?><div class="tbl-title"><?= htmlspecialchars($mn["title"]) ?></div></div></td><td style="font-size:12px"><?= htmlspecialchars($mn["category_name"] ?? "—") ?></td><td><span class="status-badge status-<?= $mn["status"] ?>"><i class="fas fa-circle"></i> <?= $mn["status"] === "published" ? "Published" : "Draft" ?></span></td><td style="font-size:11px;color:var(--muted);white-space:nowrap"><?= !empty($mn["created_at"]) ? date("d M Y, h:i A", strtotime($mn["created_at"])) : '—' ?></td><td style="font-size:11px;color:var(--blue);white-space:nowrap"><?= !empty($mn["updated_at"]) ? date("d M Y, h:i A", strtotime($mn["updated_at"])) : '—' ?></td><td><div class="tbl-actions"><a href="?page=admin_dashboard&section=edit&id=<?= $mn["id"] ?>" class="tbl-btn tbl-btn-edit"><i class="fas fa-pen"></i></a><a href="?page=admin_dashboard&section=manage&toggle=<?= $mn["id"] ?>&ttoken=<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>&filter=<?= $manageFilter ?>&p=<?= $currentPage ?>" class="tbl-btn tbl-btn-toggle"><i class="fas fa-<?= $mn["status"] === 'published' ? 'eye-slash' : 'eye' ?>"></i></a><a href="?page=admin_dashboard&section=manage&delete=<?= $mn["id"] ?>&dtoken=<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>&filter=<?= $manageFilter ?>&p=<?= $currentPage ?>" class="tbl-btn tbl-btn-delete" onclick="return confirm('Are you sure you want to delete?')"><i class="fas fa-trash"></i></a></div></td></tr>
<?php endforeach; ?>
<?php if (empty($manageNews)): ?><tr><td colspan="7"><div class="empty-state"><i class="fas fa-inbox"></i><p>No news found</p></div></td></tr><?php endif; ?>
</tbody></table></div>
<?php if ($totalPages > 1): ?>
<div class="pagination-wrap"><div class="pagination-info"><?= $totalManageResults ?> items — Page <?= $currentPage ?>/<?= $totalPages ?></div><div class="pagination-btns">
<?php if ($currentPage > 1): ?><a href="?page=admin_dashboard&section=manage&filter=<?= $manageFilter ?>&p=<?= $currentPage - 1 ?>" class="pagination-btn"><i class="fas fa-chevron-left"></i></a><?php endif; ?>
<?php for ($p = max(1, $currentPage - 2); $p <= min($totalPages, $currentPage + 2); $p++): ?><a href="?page=admin_dashboard&section=manage&filter=<?= $manageFilter ?>&p=<?= $p ?>" class="pagination-btn <?= $p === $currentPage ? 'active' : '' ?>"><?= $p ?></a><?php endfor; ?>
<?php if ($currentPage < $totalPages): ?><a href="?page=admin_dashboard&section=manage&filter=<?= $manageFilter ?>&p=<?= $currentPage + 1 ?>" class="pagination-btn"><i class="fas fa-chevron-right"></i></a><?php endif; ?>
</div></div><?php endif; ?></div>

<?php elseif ($section === "categories" && hasPerm('categories')): ?>
<div class="admin-topbar"><h1><i class="fas fa-folder-tree" style="color:var(--blue)"></i> Categories & Subcategories</h1></div>
<?php if ($catError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($catError) ?></div><?php endif; ?>
<?php if ($catSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($catSuccess) ?></div><?php endif; ?>
<div class="cat-panel-row">
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-<?= $catEditData ? 'pen' : 'plus-circle' ?>"></i> <?= $catEditData ? 'Edit Category' : 'Add Category' ?></div><?php if ($catEditData): ?><a href="?page=admin_dashboard&section=categories" class="btn btn-sm btn-outline"><i class="fas fa-times"></i> Cancel</a><?php endif; ?></div><div class="panel-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="cat_action" value="<?= $catEditData ? 'edit_cat' : 'add' ?>"><?php if ($catEditData): ?><input type="hidden" name="cat_id" value="<?= $catEditData['id'] ?>"><?php endif; ?><div style="display:flex;gap:10px;flex-wrap:wrap"><input type="text" name="cat_name" class="form-input" placeholder="Category name" style="flex:1;min-width:150px" value="<?= htmlspecialchars($catEditData['name'] ?? '') ?>" required><?php if ($catEditData): ?><input type="text" name="cat_slug" class="form-input" placeholder="Slug" style="flex:1;min-width:150px" value="<?= htmlspecialchars($catEditData['slug'] ?? '') ?>"><?php endif; ?><button type="submit" class="btn btn-primary"><i class="fas fa-<?= $catEditData ? 'save' : 'plus' ?>"></i> <?= $catEditData ? 'Update' : 'Add' ?></button></div></form></div></div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-<?= $subEditData ? 'pen' : 'plus-circle' ?>"></i> <?= $subEditData ? 'Edit Subcategory' : 'Add Subcategory' ?></div><?php if ($subEditData): ?><a href="?page=admin_dashboard&section=categories" class="btn btn-sm btn-outline"><i class="fas fa-times"></i> Cancel</a><?php endif; ?></div><div class="panel-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="cat_action" value="<?= $subEditData ? 'edit_sub' : 'add_sub' ?>"><?php if ($subEditData): ?><input type="hidden" name="sub_id" value="<?= $subEditData['id'] ?>"><?php endif; ?><div style="display:flex;gap:10px;flex-wrap:wrap"><select name="sub_category_id" class="form-select" style="width:180px" required><option value="">Category</option><?php foreach ($categories as $c): ?><option value="<?= $c["id"] ?>" <?= ($subEditData && $subEditData['category_id'] == $c['id']) ? 'selected' : '' ?>><?= htmlspecialchars($c["name"]) ?></option><?php endforeach; ?></select><input type="text" name="sub_name" class="form-input" placeholder="Subcategory name" style="flex:1;min-width:120px" value="<?= htmlspecialchars($subEditData['name'] ?? '') ?>" required><?php if ($subEditData): ?><input type="text" name="sub_slug" class="form-input" placeholder="Slug" style="flex:1;min-width:120px" value="<?= htmlspecialchars($subEditData['slug'] ?? '') ?>"><?php endif; ?><button type="submit" class="btn btn-purple"><i class="fas fa-<?= $subEditData ? 'save' : 'plus' ?>"></i> <?= $subEditData ? 'Update' : 'Add' ?></button></div></form></div></div>
</div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i> Existing Categories</div></div><div class="panel-body" style="padding:0"><table class="data-table"><thead><tr><th>Category</th><th>Slug</th><th>Subcategories</th><th>Action</th></tr></thead><tbody>
<?php foreach ($catList as $cat): $subs = $allSubcategories[$cat["id"]] ?? []; ?>
<tr><td style="font-weight:600"><?= htmlspecialchars($cat["name"]) ?></td><td style="font-size:11px;color:var(--muted)"><?= htmlspecialchars($cat["slug"]) ?></td><td><?php foreach ($subs as $sub): ?><span style="display:inline-flex;align-items:center;gap:4px;background:rgba(107,33,168,.06);padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;color:var(--purple);margin:2px"><?= htmlspecialchars($sub["name"]) ?><a href="?page=admin_dashboard&section=categories&edit_sub=<?= $sub["id"] ?>" style="color:var(--blue);margin-left:4px"><i class="fas fa-pen" style="font-size:9px"></i></a><form method="POST" style="display:inline" onsubmit="return confirm('Delete subcategory?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="cat_action" value="delete_sub"><input type="hidden" name="sub_id" value="<?= $sub["id"] ?>"><button type="submit" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:10px;padding:0"><i class="fas fa-xmark"></i></button></form></span><?php endforeach; ?><?php if (empty($subs)) echo '—'; ?></td><td><div class="tbl-actions"><a href="?page=admin_dashboard&section=categories&edit_cat=<?= $cat["id"] ?>" class="tbl-btn tbl-btn-edit"><i class="fas fa-pen"></i></a><form method="POST" style="display:inline" onsubmit="return confirm('Delete category?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="cat_action" value="delete"><input type="hidden" name="cat_id" value="<?= $cat["id"] ?>"><button type="submit" class="tbl-btn tbl-btn-delete"><i class="fas fa-trash"></i></button></form></div></td></tr>
<?php endforeach; ?>
</tbody></table></div></div>

<?php elseif ($section === "home_panels" && hasPerm('home_panels')): ?>
<div class="admin-topbar"><h1><i class="fas fa-table-cells" style="color:var(--green)"></i> Home Page Panels</h1></div>
<?php if ($homeError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($homeError) ?></div><?php endif; ?>
<?php if ($homeSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($homeSuccess) ?></div><?php endif; ?>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i> Select Categories for Homepage Panels</div></div><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><div class="panel-body"><p style="margin-bottom:15px;color:var(--muted);font-size:13px;">Toggle the switches to select which categories will be displayed as panels on the frontend homepage layout.</p><div class="home-cat-grid"><?php foreach ($catList as $cat): ?><div class="home-cat-item"><label class="switch"><input type="checkbox" name="home_cats[]" value="<?= $cat['id'] ?>" <?= in_array($cat['id'], $homeCatPanels) ? 'checked' : '' ?>><span class="switch-slider"></span></label><div><div style="font-weight:600;font-size:14px"><?= htmlspecialchars($cat['name']) ?></div><div style="font-size:10px;color:var(--muted)">Slug: <?= htmlspecialchars($cat['slug']) ?></div></div></div><?php endforeach; ?>
<div class="home-cat-item"><label class="switch"><input type="checkbox" name="home_cats[]" value="videos_section" <?= in_array("videos_section", $homeCatPanels) ? 'checked' : '' ?>><span class="switch-slider"></span></label><div><div style="font-weight:600;font-size:14px">ভিডিও সংবাদ</div><div style="font-size:10px;color:var(--muted)">Type: video_section</div></div></div>
<div class="home-cat-item"><label class="switch"><input type="checkbox" name="home_cats[]" value="latest_news_section" <?= in_array("latest_news_section", $homeCatPanels) ? 'checked' : '' ?>><span class="switch-slider"></span></label><div><div style="font-weight:600;font-size:14px">সর্বশেষ সংবাদ</div><div style="font-size:10px;color:var(--muted)">Type: latest_news_section</div></div></div>
<?php if(empty($catList)): ?><div class="empty-state"><i class="fas fa-folder-open"></i><p>No categories found. Please add categories first.</p></div><?php endif; ?></div></div><div class="form-actions-bar"><div></div><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Panels</button></div></form></div>

<?php elseif ($section === "breaking_speed" && hasPerm('breaking_speed')): ?>
<div class="admin-topbar"><h1><i class="fas fa-gauge-high" style="color:var(--accent)"></i> Breaking News Speed Controller</h1></div>
<?php if ($brkSpeedError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($brkSpeedError) ?></div><?php endif; ?>
<?php if ($brkSpeedSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($brkSpeedSuccess) ?></div><?php endif; ?>

<!-- Info Panel -->
<div class="panel" style="border-left:5px solid var(--accent)">
    <div class="panel-body" style="display:flex;align-items:flex-start;gap:14px;padding:16px 18px;">
        <i class="fas fa-circle-info" style="color:var(--accent);font-size:22px;flex-shrink:0;margin-top:2px;"></i>
        <div style="font-size:13px;color:var(--fg);line-height:1.7;">
            <strong style="color:var(--accent);">এই সেটিং কী করে?</strong><br>
            ওয়েবসাইটের হোমপেজে উপরে যে ব্রেকিং নিউজ বারটি স্ক্রল করে চলে, তার <strong>স্পিড</strong> এখান থেকে কন্ট্রোল করা যায়।
            ছোট মান (যেমন <code>20s</code>) = দ্রুত স্ক্রল, বড় মান (যেমন <code>80s</code>) = ধীর স্ক্রল।
            বাংলা টেক্সট পড়তে সহজ হওয়ার জন্য <strong>৩৫–৫০ সেকেন্ড</strong> আদর্শ।
        </div>
    </div>
</div>

<!-- Speed Control Form -->
<div class="panel">
    <div class="panel-header">
        <div class="panel-title"><i class="fas fa-sliders"></i> স্পিড সেট করুন</div>
        <span style="font-size:11px;color:var(--muted);background:var(--bg);padding:4px 12px;border-radius:14px;border:1px solid var(--border);">
            বর্তমান: <strong style="color:var(--accent);" id="currentSpeedBadge"><?= $breakingNewsSpeed ?>s</strong>
        </span>
    </div>
    <form method="POST" id="brkSpeedForm">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
        <div class="panel-body">

            <!-- Range Slider -->
            <div class="form-group">
                <label class="form-label" style="display:flex;align-items:center;justify-content:space-between;">
                    <span><i class="fas fa-gauge-high" style="color:var(--accent);margin-right:6px;"></i> স্ক্রল স্পিড (সেকেন্ড)</span>
                    <span style="font-size:24px;font-weight:800;color:var(--accent);font-family:monospace;">
                        <span id="speedDisplay"><?= $breakingNewsSpeed ?></span>s
                    </span>
                </label>
                <input type="range" name="breaking_news_speed" id="speedRange" min="10" max="180" value="<?= $breakingNewsSpeed ?>" 
                       style="width:100%;height:8px;border-radius:4px;background:linear-gradient(90deg,#dc2626 0%,#eab308 25%,#22c55e 50%,#eab308 75%,#dc2626 100%);outline:none;appearance:none;-webkit-appearance:none;cursor:pointer;">
                <div style="display:flex;justify-content:space-between;font-size:10px;color:var(--muted);margin-top:4px;font-weight:600;">
                    <span>১০s (খুব দ্রুত)</span>
                    <span>৪৫s (আদর্শ)</span>
                    <span>১৮০s (খুব ধীর)</span>
                </div>
                <input type="number" name="breaking_news_speed_num" id="speedNum" min="10" max="180" value="<?= $breakingNewsSpeed ?>" 
                       class="form-input" style="margin-top:12px;max-width:160px;text-align:center;font-weight:700;font-size:16px;color:var(--accent);">
                <div class="form-hint">অথবা সরাসরি সংখ্যা লিখুন (১০ থেকে ১৮০)।</div>
            </div>

            <!-- Preset Buttons -->
            <div class="form-group">
                <label class="form-label"><i class="fas fa-bolt" style="color:var(--gold);margin-right:6px;"></i> কুইক প্রিসেট</label>
                <div style="display:flex;flex-wrap:wrap;gap:8px;">
                    <button type="button" class="btn btn-sm btn-outline brk-preset" data-speed="20" style="padding:8px 14px;">
                        <i class="fas fa-forward-fast"></i> ২০s — খুব দ্রুত
                    </button>
                    <button type="button" class="btn btn-sm btn-outline brk-preset" data-speed="30" style="padding:8px 14px;">
                        <i class="fas fa-forward"></i> ৩০s — দ্রুত
                    </button>
                    <button type="button" class="btn btn-sm btn-outline brk-preset" data-speed="45" style="padding:8px 14px;">
                        <i class="fas fa-gauge"></i> ৪৫s — আদর্শ
                    </button>
                    <button type="button" class="btn btn-sm btn-outline brk-preset" data-speed="60" style="padding:8px 14px;">
                        <i class="fas fa-gauge-simple"></i> ৬০s — একটু ধীর
                    </button>
                    <button type="button" class="btn btn-sm btn-outline brk-preset" data-speed="90" style="padding:8px 14px;">
                        <i class="fas fa-hourglass-half"></i> ৯০s — ধীর
                    </button>
                    <button type="button" class="btn btn-sm btn-outline brk-preset" data-speed="120" style="padding:8px 14px;">
                        <i class="fas fa-hourglass"></i> ১২০s — খুব ধীর
                    </button>
                </div>
            </div>

            <!-- Live Preview -->
            <div class="form-group" style="margin-bottom:0;">
                <label class="form-label"><i class="fas fa-eye" style="color:var(--green);margin-right:6px;"></i> লাইভ প্রিভিউ</label>
                <div style="background:#1a1a1a;border-radius:8px;padding:12px;border:1px solid var(--border);">
                    <style>
                        .brk-prev-bar{background:#7F0000;color:#fff;overflow:hidden;white-space:nowrap;height:38px;display:flex;align-items:center;position:relative;border-radius:4px}
                        .brk-prev-bar::before{content:'';position:absolute;left:0;top:0;bottom:0;width:120px;background:linear-gradient(to right,#7F0000 0%,#7F0000 60%,rgba(127,0,0,0.6) 80%,transparent 100%);z-index:3;pointer-events:none}
                        .brk-prev-bar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:40px;background:linear-gradient(to left,#7F0000 0%,transparent 100%);z-index:3;pointer-events:none}
                        .brk-prev-label{background:#D4950A;color:#000;padding:0 14px;font-weight:700;font-size:12px;height:100%;display:flex;align-items:center;justify-content:center;flex-shrink:0;letter-spacing:.5px;position:relative;z-index:5;box-shadow:6px 0 12px rgba(127,0,0,0.8)}
                        .brk-prev-track{display:flex;align-items:center;height:100%;width:max-content;animation:brkPrevScroll <?= $breakingNewsSpeed ?>s linear infinite;padding-left:20px;will-change:transform;position:relative;z-index:1}
                        .brk-prev-track span{padding:0 30px;font-size:13px;font-weight:500;white-space:nowrap;color:#fff}
                        @keyframes brkPrevScroll{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
                        .brk-prev-bar:hover .brk-prev-track{animation-play-state:paused}
                        /* ★ Preset selected state — persistent highlight */
                        .brk-preset.selected{background:var(--accent) !important;color:#fff !important;border-color:var(--accent) !important;box-shadow:0 4px 12px rgba(196,30,58,.3);transform:translateY(-1px)}
                        .brk-preset.selected i{color:#fff !important}
                        .brk-preset{transition:all .2s ease}
                        .brk-preset:hover:not(.selected){border-color:var(--accent);color:var(--accent);transform:translateY(-1px)}
                    </style>
                    <div class="brk-prev-bar">
                        <div class="brk-prev-label"><i class="fas fa-bolt"></i> ব্রেকিং</div>
                        <div class="brk-prev-track" id="brkPrevTrack">
                            <span>ভারতের অর্থনীতি বিশ্বের পঞ্চম বৃহত্তম হতে চলেছে</span>
                            <span>পশ্চিমবঙ্গে আজ ভীষণ বৃষ্টির পূর্বাভাস</span>
                            <span>শেয়ার বাজারে সেন্সেক্স ৫০০ পয়েন্ট লাফ</span>
                            <span>আন্তর্জাতিক ক্রিকেটে ভারতের জয়রথ অব্যাহত</span>
                        </div>
                    </div>
                    <div style="text-align:center;margin-top:8px;font-size:11px;color:#aaa;">
                        <i class="fas fa-info-circle"></i> এই প্রিভিউটি বর্তমান স্পিডে চলছে — স্লাইডার বদলালে প্রিভিউও বদলাবে
                    </div>
                </div>
            </div>

        </div>
        <div class="form-actions-bar">
            <div style="font-size:12px;color:var(--muted);">
                <i class="fas fa-lightbulb" style="color:var(--gold);"></i>
                পরিবর্তন সেভ করলে ওয়েবসাইটের হোমপেজে সাথে সাথেই কাজ করবে (Ctrl+F5 করতে হতে পারে)।
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> স্পিড সেভ করুন
            </button>
        </div>
    </form>
</div>

<!-- Speed Guide Panel -->
<div class="panel" style="border-color:var(--gold);">
    <div class="panel-header" style="background:linear-gradient(90deg,#D4A017,#b8860b);border-bottom:2px solid var(--accent-dark);">
        <div class="panel-title" style="color:#fff;"><i class="fas fa-circle-question"></i> কোন স্পিড বেছে নেবেন?</div>
    </div>
    <div class="panel-body" style="padding:0;">
        <table class="data-table">
            <thead>
                <tr><th>স্পিড</th><th>ব্যবহার</th><th>সাফল্য</th></tr>
            </thead>
            <tbody>
                <tr><td style="font-weight:700;color:#dc2626;">২০–৩০s</td><td>খুব দ্রুত স্ক্রল — সংক্ষিপ্ত ব্রেকিং নিউজের জন্য</td><td><span class="status-badge status-pending"><i class="fas fa-circle"></i> পড়তে কষ্ট হতে পারে</span></td></tr>
                <tr><td style="font-weight:700;color:var(--gold);">৩৫–৫০s</td><td><strong>আদর্শ</strong> — বাংলা টেক্সট পড়ার জন্য সবচেয়ে ভালো</td><td><span class="status-badge status-active"><i class="fas fa-circle"></i> সুপারিশকৃত</span></td></tr>
                <tr><td style="font-weight:700;color:var(--blue);">৬০–৯০s</td><td>একটু ধীর — দীর্ঘ ব্রেকিং নিউজ আইটেমের জন্য</td><td><span class="status-badge status-active"><i class="fas fa-circle"></i> ভালো</span></td></tr>
                <tr><td style="font-weight:700;color:var(--green);">১২০s+</td><td>খুব ধীর — শুধুমাত্র বিশেষ ক্ষেত্রে</td><td><span class="status-badge status-pending"><i class="fas fa-circle"></i> একঘেয়ে লাগতে পারে</span></td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
(function(){
    var slider = document.getElementById('speedRange');
    var numInput = document.getElementById('speedNum');
    var display = document.getElementById('speedDisplay');
    var badge = document.getElementById('currentSpeedBadge');
    var track = document.getElementById('brkPrevTrack');
    var form = document.getElementById('brkSpeedForm');
    if (!slider || !numInput) return;

    function updateAll(val) {
        val = parseInt(val, 10);
        if (isNaN(val)) val = 45;
        if (val < 10) val = 10;
        if (val > 180) val = 180;
        slider.value = val;
        numInput.value = val;
        if (display) display.textContent = val;
        if (badge) badge.textContent = val + 's';
        // ★ Live preview animation duration update
        if (track) {
            track.style.animationDuration = val + 's';
            // Restart animation so new speed applies immediately
            track.style.animation = 'none';
            // force reflow
            void track.offsetWidth;
            track.style.animation = "brkPrevScroll " + val + "s linear infinite";
        }
        // ★ Sync preset highlight — remove from all, add to matching one (if any)
        var presets = document.querySelectorAll('.brk-preset');
        presets.forEach(function(p){
            var ps = parseInt(p.getAttribute('data-speed'), 10);
            if (ps === val) p.classList.add('selected');
            else p.classList.remove('selected');
        });
    }

    slider.addEventListener('input', function(){ updateAll(this.value); });
    numInput.addEventListener('input', function(){ updateAll(this.value); });
    numInput.addEventListener('change', function(){ updateAll(this.value); });

    // ★ Preset click — persistent selection (no more 800ms flash)
    document.querySelectorAll('.brk-preset').forEach(function(btn){
        btn.addEventListener('click', function(){
            var s = parseInt(this.getAttribute('data-speed'), 10);
            updateAll(s);  // updateAll() handles the .selected class automatically
        });
    });

    // ★ On page load: highlight preset matching current speed
    updateAll(slider.value);

    // Form submit: ensure both range and number have same value (use number if user typed)
    form.addEventListener('submit', function(e){
        // The hidden input "breaking_news_speed" is the slider — sync it to current value
        var v = parseInt(numInput.value, 10);
        if (isNaN(v) || v < 10 || v > 180) {
            e.preventDefault();
            alert('স্পিড ১০ থেকে ১৮০ সেকেন্ডের মধ্যে হতে হবে।');
            return false;
        }
        slider.value = v;
    });
})();
</script>

<?php elseif ($section === "social" && hasPerm('social')): ?>
<div class="admin-topbar"><h1><i class="fas fa-share-nodes" style="color:var(--blue)"></i> Social Media</h1></div>
<?php if ($socialError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($socialError) ?></div><?php endif; ?>
<?php if ($socialSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($socialSuccess) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-globe"></i> Links</div></div><div class="panel-body"><?php foreach ($socialFields as $k => $c): ?><div class="form-group"><label class="form-label"><i class="<?= $c["icon"] ?>" style="color:<?= $platformColors[$k] ?? 'var(--muted)' ?>;margin-right:6px"></i><?= $c["label"] ?></label><input type="<?= $c["type"] ?>" name="<?= $k ?>" class="form-input" value="<?= htmlspecialchars($socialValues[$k] ?? "") ?>" placeholder="<?= $c["placeholder"] ?>"></div><?php endforeach; ?></div></div><div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-code"></i> Embeds</div></div><div class="panel-body"><?php foreach ($socialCodeFields as $k => $c): ?><div class="form-group"><label class="form-label"><i class="<?= $c["icon"] ?>" style="color:var(--purple);margin-right:6px"></i><?= $c["label"] ?></label><textarea name="<?= $k ?>" class="form-input" rows="<?= $c["rows"] ?? 4 ?>" placeholder="<?= htmlspecialchars($c["placeholder"]) ?>"><?= htmlspecialchars($socialValues[$k] ?? "") ?></textarea></div><?php endforeach; ?></div><div class="form-actions-bar"><div></div><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div></div></form>

<?php elseif ($section === "contact" && hasPerm('contact')): ?>
<div class="admin-topbar"><h1><i class="fas fa-address-book" style="color:var(--green)"></i> Contact Details</h1></div>
<?php if ($contactError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($contactError) ?></div><?php endif; ?>
<?php if ($contactSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($contactSuccess) ?></div><?php endif; ?>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><?php foreach ($contactGroupLabels as $gK => $gC): $gF = array_filter($contactFields, function($f) use ($gK) { return ($f["group"] ?? "") === $gK; }); if (empty($gF)) continue; ?><div class="panel"><div class="panel-header"><div class="panel-title"><i class="<?= $gC["icon"] ?>" style="color:<?= $gC["color"] ?>"></i> <?= $gC["label"] ?></div></div><div class="panel-body"><?php foreach ($gF as $k => $c): ?><div class="form-group"><label class="form-label"><i class="<?= $c["icon"] ?>" style="color:var(--muted);margin-right:6px"></i><?= $c["label"] ?></label><?php if (isset($c["rows"])): ?><textarea name="<?= $k ?>" class="form-input" rows="<?= $c["rows"] ?>"><?= htmlspecialchars($contactValues[$k] ?? "") ?></textarea><?php else: ?><input type="<?= $c["type"] ?? "text" ?>" name="<?= $k ?>" class="form-input" value="<?= htmlspecialchars($contactValues[$k] ?? "") ?>" placeholder="<?= htmlspecialchars($c["placeholder"]) ?>"><?php endif; ?></div><?php endforeach; ?></div></div><?php endforeach; ?><div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-code" style="color:var(--purple)"></i> Embeds</div></div><div class="panel-body"><?php foreach ($contactCodeFields as $k => $c): ?><div class="form-group"><label class="form-label"><i class="<?= $c["icon"] ?>" style="color:var(--purple);margin-right:6px"></i><?= $c["label"] ?></label><textarea name="<?= $k ?>" class="form-input" rows="<?= $c["rows"] ?? 4 ?>"><?= htmlspecialchars($contactValues[$k] ?? "") ?></textarea></div><?php endforeach; ?></div><div class="form-actions-bar"><div></div><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save</button></div></div></form>

<?php elseif ($section === "watermark" && hasPerm('categories')): ?>
<?php if (!$WM_FEATURE_LIVE): ?>
<div class="admin-topbar"><h1><i class="fas fa-stamp" style="color:var(--gold)"></i> Watermark Setting</h1></div>
<div class="panel">
<div class="panel-body" style="text-align:center;padding:64px 20px">
<div style="font-size:54px;color:var(--gold);margin-bottom:16px"><i class="fas fa-helmet-safety"></i></div>
<h2 style="margin:0 0 10px;font-size:20px;font-weight:700;color:#fff">Under Construction</h2>
<p style="color:var(--muted);font-size:13px;max-width:480px;margin:0 auto;line-height:1.7">The Watermark Setting tools are being prepared and will be available soon. Please check back later.</p>
<p style="color:var(--muted);font-size:12.5px;max-width:480px;margin:10px auto 0;line-height:1.7">ওয়াটারমার্ক সেটিং সেকশনটি খুব শীঘ্রই আসছে — চালু হলে এখান থেকেই সব নিউজ ইমেজে ওয়াটারমার্ক বসানো ও সরানো যাবে।</p>
<div style="margin-top:24px;display:inline-flex;align-items:center;gap:8px;background:rgba(212,160,23,.08);border:1px solid rgba(212,160,23,.35);color:var(--gold);font-size:11.5px;font-weight:600;padding:7px 16px;border-radius:20px"><i class="fas fa-clock"></i> Coming Soon</div>
</div>
</div>
<?php else: // ——— Full Watermark UI (dormant until $WM_FEATURE_LIVE = true) ——— ?>
<?php
// Counts for the bulk tools panel
$__wmTotal = 0; $__wmBacked = 0;
try { $__wmRows = $pdo->query("SELECT image FROM news WHERE image<>'' AND image IS NOT NULL")->fetchAll(PDO::FETCH_COLUMN); } catch (Exception $e) { $__wmRows = []; }
foreach ((array)$__wmRows as $__im) { $__p = wmResolvePath((string)$__im); if ($__p === '') continue; $__wmTotal++; if (is_file($__p . '.orig')) $__wmBacked++; }
$__wmLogoUrl = ($wmVals['wm_image'] !== '' && !preg_match('~^https?://~i', $wmVals['wm_image'])) ? '../' . ltrim($wmVals['wm_image'], '/') : $wmVals['wm_image'];
$__wmPositions = ['top-left' => '↖', 'top-center' => '↑', 'top-right' => '↗', 'middle-left' => '←', 'center' => '●', 'middle-right' => '→', 'bottom-left' => '↙', 'bottom-center' => '↓', 'bottom-right' => '↘'];
?>
<style>
.wmpos-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:6px;max-width:240px}
.wmpos{position:relative;display:flex;align-items:center;justify-content:center;background:var(--sb-hover);border:1px solid #333;border-radius:6px;height:34px;cursor:pointer;color:var(--muted);font-size:13px}
.wmpos.on{background:var(--accent);border-color:var(--gold);color:#fff;font-weight:700}
.wmpos input{position:absolute;inset:0;width:100%;height:100%;opacity:0;cursor:pointer}
.wm-mini{font-size:11.5px;color:var(--muted);line-height:1.6}
.wm-preview-box{background:repeating-conic-gradient(#2b2b2b 0 25%,#1f1f1f 0 50%) 0 0/20px 20px;border:1px solid #333;border-radius:8px;padding:10px;text-align:center;min-height:120px}
.wm-preview-box img{max-width:100%;max-height:280px;border-radius:6px;display:inline-block}
</style>
<div class="admin-topbar"><h1><i class="fas fa-stamp" style="color:var(--gold)"></i> Watermark Setting</h1></div>
<?php if ($wmError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($wmError) ?></div><?php endif; ?>
<?php if ($wmSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($wmSuccess) ?></div><?php endif; ?>

<div class="panel">
<div class="panel-header"><div class="panel-title"><i class="fas fa-sliders" style="color:var(--gold)"></i> Watermark Configuration</div></div>
<form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="wm_action" value="save">
<div class="panel-body">
<div class="form-row">
<div class="form-group" style="margin-bottom:0"><label class="form-label">Enable Watermark</label><label class="switch"><input type="checkbox" name="wm_enabled" value="1" <?= $wmVals['wm_enabled'] === '1' ? 'checked' : '' ?> onchange="wmRefreshPreview()"><span class="switch-slider"></span></label></div>
<div class="form-group" style="margin-bottom:0"><label class="form-label">Auto-apply to New Uploads</label><label class="switch"><input type="checkbox" name="wm_apply_new" value="1" <?= $wmVals['wm_apply_new'] === '1' ? 'checked' : '' ?>><span class="switch-slider"></span></label></div>
</div>
<div class="form-group"><label class="form-label">Watermark Type</label>
<div class="radio-group">
<div class="radio-item <?= $wmVals['wm_type'] !== 'image' ? 'selected' : '' ?>"><input type="radio" name="wm_type" value="text" <?= $wmVals['wm_type'] !== 'image' ? 'checked' : '' ?> onchange="wmTypeToggle('text');this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Text</label></div>
<div class="radio-item <?= $wmVals['wm_type'] === 'image' ? 'selected' : '' ?>"><input type="radio" name="wm_type" value="image" <?= $wmVals['wm_type'] === 'image' ? 'checked' : '' ?> onchange="wmTypeToggle('image');this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Logo Image</label></div>
</div>
</div>
<div id="wmTextSec" style="display:<?= $wmVals['wm_type'] === 'image' ? 'none' : 'block' ?>">
<div class="form-row">
<div class="form-group" style="flex:2"><label class="form-label">Watermark Text</label><input type="text" name="wm_text" class="form-input" value="<?= htmlspecialchars($wmVals['wm_text']) ?>" maxlength="60" oninput="wmRefreshPreview()"></div>
<div class="form-group"><label class="form-label">Font Size (px)</label><input type="number" name="wm_font_size" class="form-input" value="<?= htmlspecialchars($wmVals['wm_font_size']) ?>" min="8" max="96" onchange="wmRefreshPreview()"></div>
<div class="form-group"><label class="form-label">Text Color</label><input type="color" name="wm_color" value="<?= htmlspecialchars($wmVals['wm_color']) ?>" style="width:100%;height:38px;background:var(--sb-hover);border:1px solid #333;border-radius:6px;cursor:pointer" onchange="wmRefreshPreview()"></div>
</div>
</div>
<div id="wmImgSec" style="display:<?= $wmVals['wm_type'] === 'image' ? 'block' : 'none' ?>">
<div class="form-row">
<div class="form-group"><label class="form-label">Logo Size (% of image width)</label><input type="number" name="wm_scale" class="form-input" value="<?= htmlspecialchars($wmVals['wm_scale']) ?>" min="5" max="60" onchange="wmRefreshPreview()"></div>
</div>
</div>
<div class="form-row">
<div class="form-group"><label class="form-label">Opacity: <span id="wmOpLbl"><?= htmlspecialchars($wmVals['wm_opacity']) ?>%</span></label><input type="range" name="wm_opacity" min="5" max="100" value="<?= htmlspecialchars($wmVals['wm_opacity']) ?>" style="width:220px" oninput="document.getElementById('wmOpLbl').textContent=this.value+'%';wmRefreshPreview()"></div>
<div class="form-group"><label class="form-label">Margin (px)</label><input type="number" name="wm_margin" class="form-input" value="<?= htmlspecialchars($wmVals['wm_margin']) ?>" min="0" max="200" onchange="wmRefreshPreview()"></div>
</div>
<div class="form-group"><label class="form-label">Position</label>
<div class="wmpos-grid">
<?php foreach ($__wmPositions as $__pp => $__ico): ?>
<label class="wmpos <?= $wmVals['wm_position'] === $__pp ? 'on' : '' ?>"><input type="radio" name="wm_position" value="<?= $__pp ?>" <?= $wmVals['wm_position'] === $__pp ? 'checked' : '' ?> onchange="document.querySelectorAll('.wmpos').forEach(l=>l.classList.remove('on'));this.closest('.wmpos').classList.add('on');wmRefreshPreview()"><span><?= $__ico ?></span></label>
<?php endforeach; ?>
</div>
</div>
<div class="form-actions-bar"><div></div><button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> Save Settings</button></div>
</div>
</form>
</div>

<div class="panel">
<div class="panel-header"><div class="panel-title"><i class="fas fa-image" style="color:var(--gold)"></i> Watermark Logo</div></div>
<div class="panel-body">
<form method="POST" enctype="multipart/form-data" id="wmLogoForm" style="margin:0">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="wm_action" value="upload_wm">
<div class="img-upload-area <?= $wmVals['wm_image'] !== '' ? 'has-image' : '' ?>" style="max-width:340px">
<input type="file" name="wm_image_file" accept="image/png,image/jpeg,image/webp,image/gif" onchange="document.getElementById('wmLogoForm').submit()">
<?php if ($wmVals['wm_image'] !== ''): ?>
<img src="<?= htmlspecialchars($__wmLogoUrl) ?>" class="img-preview" alt="watermark logo" style="max-width:200px;max-height:120px">
<div style="margin-top:6px;font-size:11px;color:var(--green);font-weight:600"><i class="fas fa-check-circle"></i> Logo set — click to replace</div>
<?php else: ?>
<div class="img-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
<div class="img-upload-text">Upload watermark logo</div>
<div class="img-upload-hint">PNG with transparency recommended — auto-resized to 512px</div>
<?php endif; ?>
</div>
</form>
<?php if ($wmVals['wm_image'] !== '' && !preg_match('~^https?://~i', $wmVals['wm_image'])): ?>
<a href="?page=admin_dashboard&section=watermark&delete_wm=1&dtoken=<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>" class="btn btn-sm btn-outline" style="margin-top:10px;color:#ef4444" onclick="return confirm('Delete the watermark logo?')"><i class="fas fa-trash"></i> Delete Logo</a>
<?php endif; ?>
<div class="wm-mini" style="margin-top:10px">বাংলা লেখার ওয়াটারমার্ক দরকার হলে টেক্সটের বদলে লোগো ইমেজ (PNG) ব্যবহার করুন — সব সার্ভারে বাংলা ফন্ট থাকে না।</div>
</div>
</div>

<div class="panel">
<div class="panel-header"><div class="panel-title"><i class="fas fa-eye" style="color:var(--gold)"></i> Live Preview</div><div class="topbar-actions"><button type="button" class="btn btn-sm btn-outline" onclick="wmRefreshPreview()"><i class="fas fa-rotate"></i> Refresh</button></div></div>
<div class="panel-body">
<div class="wm-preview-box"><img id="wmPreview" src="?page=admin_dashboard&section=watermark&preview=1&t=<?= time() ?>" alt="watermark preview"></div>
<div class="wm-mini" style="margin-top:8px">Preview applies your current settings to your latest news image on a temp copy — nothing is modified. Click Save Settings first if you changed them.</div>
</div>
</div>

<div class="panel">
<div class="panel-header"><div class="panel-title"><i class="fas fa-wand-magic-sparkles" style="color:var(--gold)"></i> Apply / Remove on Existing News Images</div></div>
<div class="panel-body">
<div class="wm-mini">Total news images found: <b><?= $__wmTotal ?></b> &nbsp;·&nbsp; Currently watermarked (original backed up): <b><?= $__wmBacked ?></b></div>
<div style="display:flex;gap:10px;margin-top:12px;flex-wrap:wrap">
<form method="POST" onsubmit="return confirm('Apply watermark to ALL news images? GIF/SVG files are skipped. Large libraries may take a while.')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="wm_action" value="apply_all"><button type="submit" class="btn btn-primary"><i class="fas fa-stamp"></i> Apply Watermark to All</button></form>
<form method="POST" onsubmit="return confirm('Remove watermark from ALL news images by restoring the stored originals? Images never watermarked by this tool are skipped.')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="wm_action" value="remove_all"><button type="submit" class="btn btn-outline" style="color:#ef4444"><i class="fas fa-eraser"></i> Remove Watermark (Restore Originals)</button></form>
</div>
<div class="wm-mini" style="margin-top:12px">নিরাপত্তা: ওয়াটারমার্ক বসানোর আগে প্রতিটি ছবির অরিজিনাল <b>.orig</b> ব্যাকআপ হিসেবে জমা থাকে — Remove চাপলে সেই অরিজিনাল ফিরে আসে, তাই কোয়ালিটির কোনো স্থায়ী ক্ষতি হয় না।</div>
</div>
</div>

<script>
function wmTypeToggle(t){document.getElementById('wmTextSec').style.display=t==='text'?'block':'none';document.getElementById('wmImgSec').style.display=t==='image'?'block':'none';}
var wmPt=null;
function wmRefreshPreview(){clearTimeout(wmPt);wmPt=setTimeout(function(){var i=document.getElementById('wmPreview');if(i)i.src='?page=admin_dashboard&section=watermark&preview=1&t='+Date.now();},350);}
</script>
<?php endif; ?>

<?php elseif ($section === "ads" && hasPerm('ads')): ?>
<div class="admin-topbar"><h1><i class="fas fa-rectangle-ad" style="color:var(--gold)"></i> Advertisements</h1><div class="topbar-actions"><?php if ($adEditData): ?><a href="?page=admin_dashboard&section=ads" class="topbar-btn topbar-btn-outline"><i class="fas fa-times"></i> Cancel</a><?php endif; ?></div></div>
<?php if ($adError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($adError) ?></div><?php endif; ?>
<?php if ($adSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($adSuccess) ?></div><?php endif; ?>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-code"></i> Advertisement Embed Codes</div></div><form method="POST"><input type="hidden" name="update_ads" value="1"><div class="panel-body"><div class="form-group"><label class="form-label">Bottom Ad Embed 1 (HTML/Scripts allowed)</label><textarea name="ad_bottom_1_embed" class="form-input" rows="5" placeholder='<iframe src="..."></iframe>'><?= htmlspecialchars($currentAds['ad_bottom_1_embed'] ?? '') ?></textarea></div><div class="form-group"><label class="form-label">Bottom Ad Embed 2 (HTML/Scripts allowed)</label><textarea name="ad_bottom_2_embed" class="form-input" rows="5" placeholder='<iframe src="..."></iframe>'><?= htmlspecialchars($currentAds['ad_bottom_2_embed'] ?? '') ?></textarea></div><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Embed Codes</button></div></form></div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-<?= $adEditData ? 'pen' : 'plus-circle' ?>"></i> <?= $adEditData ? 'Edit' : 'New Advertisement' ?></div></div><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><?php if ($adEditData): ?><input type="hidden" name="ad_id" value="<?= $adEditData["id"] ?>"><?php endif; ?><div class="panel-body"><div class="form-row"><div class="form-group"><label class="form-label">Name *</label><input type="text" name="ad_title" class="form-input" value="<?= htmlspecialchars($adEditData["title"] ?? "") ?>" required></div><div class="form-group"><label class="form-label">Position</label><select name="ad_position" class="form-select"><?php foreach ($adPositions as $pk => $pc): ?><option value="<?= $pk ?>" <?= ($adEditData["position"] ?? "") === $pk ? 'selected' : '' ?>><?= $pc["label"] ?></option><?php endforeach; ?></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Type</label><div class="radio-group"><div class="radio-item <?= ($adEditData["ad_type"] ?? "image") === "image" ? 'selected' : '' ?>"><input type="radio" name="ad_type" value="image" <?= ($adEditData["ad_type"] ?? "image") === "image" ? 'checked' : '' ?> onchange="document.getElementById('adImgSec').style.display='block';document.getElementById('adCodeSec').style.display='none';this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Image</label></div><div class="radio-item <?= ($adEditData["ad_type"] ?? "") === "code" ? 'selected' : '' ?>"><input type="radio" name="ad_type" value="code" <?= ($adEditData["ad_type"] ?? "") === "code" ? 'checked' : '' ?> onchange="document.getElementById('adImgSec').style.display='none';document.getElementById('adCodeSec').style.display='block';this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Code</label></div></div></div><div class="form-group"><label class="form-label">Sort</label><input type="number" name="ad_sort_order" class="form-input" value="<?= $adEditData["sort_order"] ?? 0 ?>" min="0"></div></div><div id="adImgSec" style="display:<?= ($adEditData["ad_type"] ?? "image") === "image" ? 'block' : 'none' ?>"><div class="form-group"><label class="form-label">Link URL</label><input type="url" name="ad_link_url" class="form-input" value="<?= htmlspecialchars($adEditData["link_url"] ?? "") ?>"></div><div class="form-group"><label class="form-label">Image</label><div class="img-upload-area <?= !empty($adEditData["image"]) ? 'has-image' : '' ?>" id="adImgArea"><input type="file" name="ad_image" id="ad_image" accept="image/jpeg,image/png/image/gif/image/webp"><?php if (!empty($adEditData["image"])): ?><img src="<?= htmlspecialchars($adEditData["image"]) ?>" class="img-preview" id="adImgPreview" alt=""><div style="margin-top:6px;font-size:11px;color:var(--green);font-weight:600"><i class="fas fa-check-circle"></i> Image exists</div><?php else: ?><div class="img-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div><div class="img-upload-text">Select image</div><div class="img-upload-hint">JPG, PNG, GIF, WebP — 5MB</div><img src="" class="img-preview" id="adImgPreview" style="display:none" alt=""><?php endif; ?></div></div></div><div id="adCodeSec" style="display:<?= ($adEditData["ad_type"] ?? "") === "code" ? 'block' : 'none' ?>"><div class="form-group"><label class="form-label">HTML Code</label><textarea name="ad_code" class="form-input" rows="8"><?= htmlspecialchars($adEditData["ad_code"] ?? "") ?></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input type="date" name="ad_start_date" class="form-input" value="<?= $adEditData["start_date"] ?? "" ?>"></div><div class="form-group"><label class="form-label">End Date</label><input type="date" name="ad_end_date" class="form-input" value="<?= $adEditData["end_date"] ?? "" ?>"></div></div><div class="form-group" style="max-width:200px"><label class="form-label">Max Impressions</label><input type="number" name="ad_max_impressions" class="form-input" value="<?= $adEditData["max_impressions"] ?? "" ?>" min="0" placeholder="Unlimited"></div></div><div class="form-actions-bar"><div></div><button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> <?= $adEditData ? 'Update' : 'Add' ?></button></div></form></div>
<!-- ========================================== ★ Flash Ads Embed Codes Panel ========================================== -->
<!-- 3 embed code slots + 3 link URLs, rendered on index.php homepage as a dismissible popup. -->
<!-- Stored in `site_settings` table — completely separate from existing Advertisement section. -->
<div class="panel" style="border:2px solid var(--accent);">
    <div class="panel-header" style="background:linear-gradient(90deg, var(--accent), var(--accent-dark));">
        <div class="panel-title" style="color:#fff;"><i class="fas fa-bolt"></i> Flash Ads Embed Codes</div>
    </div>
    <form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="update_flash_ads" value="1">
        <div class="panel-body">
            <?php if ($flashAdError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($flashAdError) ?></div><?php endif; ?>
            <?php if ($flashAdSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($flashAdSuccess) ?></div><?php endif; ?>
            <div style="background:var(--accent-light); border-left:4px solid var(--accent); padding:12px 15px; border-radius:6px; margin-bottom:18px; font-size:12.5px; color:var(--fg);"><i class="fas fa-circle-info" style="color:var(--accent);"></i> <strong>Flash Ads</strong> হলো homepage-এ popup-style ad — ভিজিটর close বাটনে চাপলে সেটা আর দেখাবে না (24 hours) । নিচে 3টি embed code + 3টি link URL দিন (HTML/scripts allowed) । Existing Advertisement section-এ কোনো পরিবর্তন হয়নি।</div>

            <div class="form-group"><label class="form-label"><i class="fas fa-bolt" style="color:var(--gold);"></i> Flash Ad Embed Code 1 <span style="font-size:10px;color:var(--muted);font-weight:400">(HTML/Scripts allowed)</span></label><textarea name="flash_ad_embed_1" id="flash_ad_embed_1" class="form-input" rows="5" placeholder='<iframe src="https://example.com/ad1" width="600" height="400"></iframe>  অথবা  <a href="..."><img src="..."></a>'><?= htmlspecialchars($flashAdValues['flash_ad_embed_1'] ?? '') ?></textarea></div>
            <div class="form-group" style="max-width:600px;"><label class="form-label"><i class="fas fa-link" style="color:var(--blue);"></i> Link URL 1 <span style="font-size:10px;color:var(--muted);font-weight:400">(visitor clicks → goes here; leave empty if embed has its own link)</span></label><input type="url" name="flash_ad_link_1" class="form-input" value="<?= htmlspecialchars($flashAdValues['flash_ad_link_1'] ?? '') ?>" placeholder="https://example.com/offer-1"></div>

            <hr style="border:none; border-top:1px dashed var(--border); margin:20px 0;">

            <div class="form-group"><label class="form-label"><i class="fas fa-bolt" style="color:var(--gold);"></i> Flash Ad Embed Code 2 <span style="font-size:10px;color:var(--muted);font-weight:400">(HTML/Scripts allowed)</span></label><textarea name="flash_ad_embed_2" id="flash_ad_embed_2" class="form-input" rows="5" placeholder='<iframe src="https://example.com/ad2" width="600" height="400"></iframe>'><?= htmlspecialchars($flashAdValues['flash_ad_embed_2'] ?? '') ?></textarea></div>
            <div class="form-group" style="max-width:600px;"><label class="form-label"><i class="fas fa-link" style="color:var(--blue);"></i> Link URL 2</label><input type="url" name="flash_ad_link_2" class="form-input" value="<?= htmlspecialchars($flashAdValues['flash_ad_link_2'] ?? '') ?>" placeholder="https://example.com/offer-2"></div>

            <hr style="border:none; border-top:1px dashed var(--border); margin:20px 0;">

            <div class="form-group"><label class="form-label"><i class="fas fa-bolt" style="color:var(--gold);"></i> Flash Ad Embed Code 3 <span style="font-size:10px;color:var(--muted);font-weight:400">(HTML/Scripts allowed)</span></label><textarea name="flash_ad_embed_3" id="flash_ad_embed_3" class="form-input" rows="5" placeholder='<iframe src="https://example.com/ad3" width="600" height="400"></iframe>'><?= htmlspecialchars($flashAdValues['flash_ad_embed_3'] ?? '') ?></textarea></div>
            <div class="form-group" style="max-width:600px;"><label class="form-label"><i class="fas fa-link" style="color:var(--blue);"></i> Link URL 3</label><input type="url" name="flash_ad_link_3" class="form-input" value="<?= htmlspecialchars($flashAdValues['flash_ad_link_3'] ?? '') ?>" placeholder="https://example.com/offer-3"></div>

            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Flash Ads</button>
        </div>
    </form>
    <div class="panel-body" style="border-top:2px dashed var(--border); padding-top:20px;">
        <?php
        // Compute totals for the 3 fixed slots
        $__flashAdRows = [];
        $__flashAdActiveCount = 0;
        for ($__i = 1; $__i <= 3; $__i++) {
            $__e = trim((string)($flashAdValues['flash_ad_embed_' . $__i] ?? ''));
            $__l = trim((string)($flashAdValues['flash_ad_link_' . $__i] ?? ''));
            if ($__e !== '') {
                $__flashAdRows[] = ['slot' => $__i, 'embed' => $__e, 'link' => $__l];
                $__flashAdActiveCount++;
            }
        }
        $__flashAdTotal = count($__flashAdRows);
        ?>
        <div style="display:flex; align-items:center; justify-content:space-between; margin-bottom:12px; flex-wrap:wrap; gap:8px;">
            <div class="panel-title" style="font-size:14px;"><i class="fas fa-list" style="color:var(--accent);"></i> Existing Flash Ads <span style="font-size:11px; color:var(--muted); margin-left:6px; font-weight:400;">(<?= $__flashAdTotal ?> total — <?= $__flashAdActiveCount ?> active)</span></div>
        </div>
        <table class="data-table"><thead><tr><th>Preview</th><th>Title</th><th>Type</th><th>Schedule</th><th>Re-show</th><th>Status</th><th>Clicks</th><th>Impr.</th><th>Action</th></tr></thead><tbody>
        <?php foreach ($__flashAdRows as $__fa): ?>
            <tr>
                <td style="max-width:80px;"><div style="background:#fff; border:1px solid var(--border); border-radius:4px; padding:3px; width:70px; height:50px; overflow:hidden; display:flex; align-items:center; justify-content:center;"><?= $__fa['embed'] /* raw HTML — admin-trusted */ ?></div></td>
                <td style="font-weight:600">Flash Ad <?= $__fa['slot'] ?><?php if (!empty($__fa['link'])): ?><br><a href="<?= htmlspecialchars($__fa['link']) ?>" target="_blank" rel="noopener" style="font-size:10px; color:var(--blue); font-weight:400; text-decoration:underline;"><?= htmlspecialchars(mb_strimwidth($__fa['link'], 0, 40, '...')) ?></a><?php endif; ?></td>
                <td style="font-size:12px;"><i class="fas fa-code" style="color:var(--purple)"></i> Code</td>
                <td style="font-size:10px; color:var(--muted);">Always</td>
                <td style="font-size:11px; color:var(--gold); font-weight:600;">24h</td>
                <td><span class="status-badge status-active"><i class="fas fa-circle"></i> Active</span></td>
                <td style="font-weight:600; color:var(--muted);">—</td>
                <td style="font-weight:600; color:var(--muted);">—</td>
                <td><div class="tbl-actions"><a href="#flash_ad_embed_<?= $__fa['slot'] ?>" class="tbl-btn tbl-btn-edit" title="Edit this Flash Ad" onclick="setTimeout(function(){var el=document.getElementById('flash_ad_embed_<?= $__fa['slot'] ?>');if(el){el.focus();el.scrollIntoView({behavior:'smooth',block:'center'});}},100);"><i class="fas fa-pen"></i></a></div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($__flashAdRows)): ?>
            <tr><td colspan="9"><div class="empty-state"><i class="fas fa-bolt" style="font-size:40px; color:var(--accent); opacity:.5;"></i><p>No Flash Ads yet. Add one above to show on homepage.</p></div></td></tr>
        <?php endif; ?>
        </tbody></table>
    </div>
</div>
<!-- ========================================== Flash Ads Panel Ends ========================================== -->

<div class="panel"><div class="panel-header"><div class="filter-tabs"><?php foreach (["all"=>"All (".$adTotal.")","active"=>"Active","inactive"=>"Inactive","image"=>"Image","code"=>"Code"] as $f => $l): ?><a href="?page=admin_dashboard&section=ads&ad_filter=<?= $f ?>" class="filter-tab <?= $adFilter === $f ? 'active' : '' ?>"><?= $l ?></a><?php endforeach; ?></div></div><div class="panel-body" style="padding:0"><table class="data-table"><thead><tr><th>Name</th><th>Position</th><th>Type</th><th>Status</th><th>Clicks</th><th>Impressions</th><th>Action</th></tr></thead><tbody>
<?php foreach ($adList as $ad): $pc = $adPositions[$ad["position"]] ?? ["label"=>$ad["position"],"color"=>"#666","icon"=>"fas fa-circle"]; ?>
<tr><td style="font-weight:600"><?= htmlspecialchars($ad["title"]) ?></td><td><span class="ad-pos-chip" style="border-color:<?= $pc["color"] ?>;color:<?= $pc["color"] ?>"><i class="<?= $pc["icon"] ?>"></i> <?= $pc["label"] ?></span></td><td style="font-size:12px"><?= $ad["ad_type"] === 'image' ? '<i class="fas fa-image" style="color:var(--blue)"></i> Image' : '<i class="fas fa-code" style="color:var(--purple)"></i> Code' ?></td><td><span class="status-badge status-<?= $ad["is_active"] ? 'active' : 'inactive' ?>"><i class="fas fa-circle"></i> <?= $ad["is_active"] ? 'Active' : 'Inactive' ?></span></td><td style="font-weight:600"><?= number_format($ad["clicks"] ?? 0) ?></td><td style="font-weight:600"><?= number_format($ad["impressions"] ?? 0) ?></td><td><div class="tbl-actions"><a href="?page=admin_dashboard&section=ads&ad_edit=<?= $ad["id"] ?>" class="tbl-btn tbl-btn-edit"><i class="fas fa-pen"></i></a><a href="?page=admin_dashboard&section=ads&ad_toggle=<?= $ad["id"] ?>&ad_ttoken=<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>&ad_filter=<?= $adFilter ?>" class="tbl-btn tbl-btn-toggle"><i class="fas fa-<?= $ad["is_active"] ? 'eye-slash' : 'eye' ?>"></i></a><a href="?page=admin_dashboard&section=ads&ad_delete=<?= $ad["id"] ?>&ad_dtoken=<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>&ad_filter=<?= $adFilter ?>" class="tbl-btn tbl-btn-delete" onclick="return confirm('Delete?')"><i class="fas fa-trash"></i></a></div></td></tr>
<?php endforeach; ?>
<?php if (empty($adList)): ?><tr><td colspan="7"><div class="empty-state"><p>No ads found</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>
<script>
var afi = document.getElementById('ad_image'), apr = document.getElementById('adImgPreview'), aar = document.getElementById('adImgArea');
if (afi) afi.onchange = function() { if (this.files && this.files[0]) { var r = new FileReader(); r.onload = function(e) { if (apr) { apr.src = e.target.result; apr.style.display = 'block'; } if (aar) aar.classList.add('has-image'); }; r.readAsDataURL(this.files[0]); } };

var fli = document.getElementById('festival_logo_file'), flpr = document.getElementById('festLogoImgPreview'), flar = document.getElementById('festLogoImgArea');
if (fli) fli.onchange = function() { if (this.files && this.files[0]) { var r = new FileReader(); r.onload = function(e) { if (flpr) { flpr.src = e.target.result; flpr.style.display = 'block'; } if (flar) flar.classList.add('has-image'); }; r.readAsDataURL(this.files[0]); } };
</script>

<!-- Festival Logo Settings Section Start -->
<div class="panel" style="border-color: var(--purple);">
    <div class="panel-header" style="background: var(--purple); border-bottom: 2px solid var(--accent-dark);">
        <div class="panel-title" style="color: #fff;"><i class="fas fa-image"></i> Festival Logo Settings</div>
    </div>
    <div class="panel-body">
        <p style="font-size: 13px; color: var(--muted); margin-bottom: 15px;">
            <i class="fas fa-circle-info" style="color: var(--purple);"></i> 
            উৎসবের দিন (রাত ১২:০০ AM) থেকে লোগোটি স্বয়ংক্রিয়ভাবে ওয়েবসাইটে দেখানো শুরু হবে। নিচের ফিল্ডগুলো ব্যবহার করে আপনি শুধুমাত্র কাস্টম লোগো বা টেক্সট আপলোড করতে পারেন। <strong>একবার সেভ করলে এটি সেটিংসে সেভ হয়ে থাকবে, বারবার সেভ করার প্রয়োজন নেই।</strong>
        </p>
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_festival_logo'])) {
            
            // Handle Reset to Default
            if (isset($_POST['reset_festival_logo'])) {
                saveSettings($pdo, [
                    'festival_logo_img' => '',
                    'festival_logo_text' => ''
                ]);
                echo "<div class='alert alert-success' style='margin-bottom: 15px;'><i class='fas fa-check-circle'></i> Custom settings cleared. Default event logos will be used.</div>";
            } else {
                $festival_logo_img = trim($_POST['festival_logo_img'] ?? '');
                $festival_logo_text = trim($_POST['festival_logo_text'] ?? '');
                
                // If new file is uploaded, overwrite the URL path
                if (isset($_FILES['festival_logo_file']) && $_FILES['festival_logo_file']['error'] === UPLOAD_ERR_OK) {
                    $ur = handleLogoUploadKeepName('festival_logo_file');   // ★ original filename kept
                    if ($ur !== null) {
                        if (isset($ur["error"])) {
                            echo "<div class='alert alert-error' style='margin-bottom: 15px;'><i class='fas fa-exclamation-triangle'></i> Upload Error: " . $ur["error"] . "</div>";
                        } else {
                            $festival_logo_img = $ur["path"];
                        }
                    }
                }
                
                try {
                    saveSettings($pdo, [
                        'festival_logo_img' => $festival_logo_img,
                        'festival_logo_text' => $festival_logo_text
                    ]);
                    echo "<div class='alert alert-success' style='margin-bottom: 15px;'><i class='fas fa-check-circle'></i> Custom Festival Logo settings saved successfully! এটি স্থায়ীভাবে সেভ হয়ে গেছে।</div>";
                } catch (Exception $e) {
                    echo "<div class='alert alert-error' style='margin-bottom: 15px;'><i class='fas fa-exclamation-triangle'></i> Error saving settings: " . $e->getMessage() . "</div>";
                }
            }
        }

        // Load saved settings to show in the form
        $festivalVals = loadSettings($pdo, ['festival_logo_img', 'festival_logo_text']);
        $current_logo_img = $festivalVals['festival_logo_img'] ?? '';
        $current_logo_text = $festivalVals['festival_logo_text'] ?? '';

        // ★ v5 সতর্কতা: কাস্টম লোগো সেভ থাকলে সেটিই সাইটের অটো দিনের-লোগোর বদলে দেখায় —
        // এজন্যই ফোল্ডারে নতুন ছবি আপলোড করার পরও সাইটে পুরোনো ছবিই দেখাত।
        if (!empty($current_logo_img)) {
            echo "<div class='alert alert-warning' style='margin-bottom:15px'><i class='fas fa-triangle-exclamation'></i> <b>কাস্টম লোগো চালু আছে!</b> সেটিংসে সেভ করা ছবিটি (<code>" . htmlspecialchars($current_logo_img) . "</code>) অটো দিনের-লোগোর বদলে সাইটে দেখানো হচ্ছে। ফোল্ডারে নতুন ছবি আপলোড করলেও তা দেখাবে না — অটো লোগোতে ফিরতে নিচের <b>Reset to Default</b> বাটনে চাপুন।</div>";
        }
        
        // Check if there's an active festival today (v4.1: উপরে Date-Safety override থাকলে সেটাই থাকবে)
        if (!isset($activeFestival)) { $activeFestival = BengaliFestivalCalendar::getActiveFestival(); }
        ?>

        <?php if ($activeFestival): ?>
        <?php
        // ★ v5: আজকের অটো লোগো/ব্যানার সরাসরি ফোল্ডার থেকে রেজলভ করে প্রিভিউ দেখানো হয়
        $__autoLogo   = adminFestResolve($activeFestival['logo']);
        $__autoBanner = adminFestResolve('banners/' . $activeFestival['logo']);
        ?>
        <div class="alert alert-success" style="margin-bottom: 15px;">
            <i class="fas fa-calendar-check"></i> <strong>Currently Active Event:</strong> <?= htmlspecialchars($activeFestival['name']) ?> <br>
            <small>Default Image: <code><?= htmlspecialchars($__autoLogo !== '' ? $__autoLogo : 'festivals/' . $activeFestival['logo']) ?></code>
            <?php if ($__autoLogo !== ''): ?>
                 <span style="color:#2e7d32;font-weight:600"><i class="fas fa-check-circle"></i> ফাইল পাওয়া গেছে</span>
            <?php else: ?>
                 <span style="color:#c62828;font-weight:600"><i class="fas fa-exclamation-triangle"></i> ফাইল খুঁজে পাওয়া যায়নি</span>
            <?php endif; ?></small>
            <?php if ($__autoLogo !== ''): ?>
            <div style="margin-top:10px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;background:#fff;border:1px solid #e0e0e0;border-radius:8px;padding:10px">
                <img src="<?= htmlspecialchars(adminFestUrl($__autoLogo)) ?>" alt="" style="max-width:110px;max-height:110px;border-radius:6px">
                <?php if ($__autoBanner !== ''): ?>
                <img src="<?= htmlspecialchars(adminFestUrl($__autoBanner)) ?>" alt="" style="max-height:55px;border-radius:6px">
                <?php endif; ?>
                <small style="color:#555;max-width:330px">↑ আজকের অটো লোগো + গ্রিটিং ব্যানার (সবচেয়ে নতুন ফাইল)। ফোল্ডারে নতুন ছবি আপলোড করলে এখানে এবং সাইটে সাথে সাথেই বদলে যাবে — নতুন করে কিছু সেভ করতে হবে না।</small>
            </div>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="alert alert-warning" style="margin-bottom: 15px;">
            <i class="fas fa-info-circle"></i> বর্তমানে কোনো ইভেন্ট অ্যাক্টিভ নেই। ক্যালেন্ডার অনুযায়ী নির্দিষ্ট তারিখে অটোমেটিক চালু হবে।
        </div>
        <?php endif; ?>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="festival_logo_file">Upload Custom Festival Logo Image:</label>
                <div class="img-upload-area <?= !empty($current_logo_img) ? 'has-image' : '' ?>" id="festLogoImgArea" style="max-width: 300px; padding: 15px;">
                    <input type="file" name="festival_logo_file" id="festival_logo_file" accept="image/jpeg,image/png,image/gif/image/webp" style="position: absolute; inset: 0; opacity: 0; cursor: pointer; width: 100%; height: 100%; z-index: 2;">
                    <?php if (!empty($current_logo_img)): ?>
                        <img src="<?= htmlspecialchars(adminFestUrl($current_logo_img)) ?>" class="img-preview" id="festLogoImgPreview" alt="" style="max-height: 100px;">
                        <div style="margin-top:6px;font-size:11px;color:var(--green);font-weight:600"><i class="fas fa-check-circle"></i> Saved Image exists</div>
                    <?php else: ?>
                        <div class="img-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div>
                        <div class="img-upload-text">Click to select image</div>
                        <div class="img-upload-hint">JPG, PNG, GIF, WebP — 5MB</div>
                        <img src="" class="img-preview" id="festLogoImgPreview" style="display:none" alt="">
                    <?php endif; ?>
                </div>
                <small class="form-hint">নতুন ছবি আপলোড করলে পুরোনোটির পরিবর্তে এটি সেভ হবে। না চাইলে ফাঁকা রাখুন।</small>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="festival_logo_img">Saved Custom Logo Image URL:</label>
                <input type="text" class="form-input" id="festival_logo_img" name="festival_logo_img" 
                       value="<?php echo htmlspecialchars($current_logo_img); ?>" 
                       placeholder="https://example.com/festival-logo.png">
                <small class="form-hint">এখানে আগে সেভ করা URL দেখাচ্ছে। পরিবর্তন না করলে এটি এভাবেই সেভ হয়ে থাকবে।</small>
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label class="form-label" for="festival_logo_text">Saved Festival Logo Text (Optional):</label>
                <input type="text" class="form-input" id="festival_logo_text" name="festival_logo_text" 
                       value="<?php echo htmlspecialchars($current_logo_text); ?>" 
                       placeholder="শুভেচ্ছা">
                <small class="form-hint">এখানে আগে সেভ করা টেক্সট দেখাচ্ছে।</small>
            </div>

            <div style="display:flex; gap:10px; align-items:center;">
                <button type="submit" name="save_festival_logo" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Changes
                </button>
                <button type="submit" name="reset_festival_logo" value="1" class="btn btn-outline" onclick="return confirm('Are you sure you want to clear custom settings and use default event logos?')">
                    <i class="fas fa-rotate-left"></i> Reset to Default
                </button>
            </div>
        </form>
    </div>
</div>
<!-- Festival Logo Settings Section End -->

<script>
// Script for Custom Festival Image Upload Preview
var fli = document.getElementById('festival_logo_file'), flpr = document.getElementById('festLogoImgPreview'), flar = document.getElementById('festLogoImgArea');
if (fli) fli.onchange = function() { 
    if (this.files && this.files[0]) { 
        var r = new FileReader(); 
        r.onload = function(e) { 
            if (flpr) { flpr.src = e.target.result; flpr.style.display = 'block'; } 
            if (flar) flar.classList.add('has-image'); 
        }; 
        r.readAsDataURL(this.files[0]); 
    } 
};
</script>

<?php elseif ($section === "videos" && hasPerm('videos')): ?>
<div class="admin-topbar"><h1><i class="fas fa-video" style="color:var(--purple)"></i> Videos Management</h1></div>
<?php if ($vidError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($vidError) ?></div><?php endif; ?>
<?php if ($vidSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($vidSuccess) ?></div><?php endif; ?>
<div class="panel"><div class="panel-body"><form method="POST"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="vid_action" value="add"><div class="form-group"><label class="form-label">Video Title</label><input type="text" name="vid_title" class="form-input" placeholder="Enter video title" required></div><div class="form-group"><label class="form-label">Embed Code (YouTube/IFrame)</label><textarea name="vid_code" class="form-input" rows="5" placeholder='<iframe src="..."></iframe>' required></textarea></div><button type="submit" class="btn btn-primary"><i class="fas fa-plus"></i> Add Video</button></form></div></div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i> Existing Videos</div></div><div class="panel-body" style="padding:0"><table class="data-table"><thead><tr><th>#</th><th>Title</th><th>Embed Code</th><th>Date</th><th>Action</th></tr></thead><tbody>
<?php foreach ($videos as $i => $vid): ?>
<tr><td style="color:var(--muted);font-size:11px"><?= $i + 1 ?></td><td style="font-weight:600"><?= htmlspecialchars($vid["title"]) ?></td><td style="font-size:11px;color:var(--blue);max-width:350px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis" title="<?= htmlspecialchars($vid["code"] ?? "") ?>"><?= htmlspecialchars($vid["code"] ?? "") ?></td><td style="font-size:11px;color:var(--muted)"><?= date("d M Y", strtotime($vid["created_at"])) ?></td><td><form method="POST" onsubmit="return confirm('Delete this video?')" style="display:inline"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="vid_action" value="delete"><input type="hidden" name="vid_id" value="<?= $vid["id"] ?>"><button type="submit" class="tbl-btn tbl-btn-delete"><i class="fas fa-trash"></i></button></form></td></tr>
<?php endforeach; ?>
<?php if (empty($videos)): ?><tr><td colspan="5"><div class="empty-state"><p>No videos found</p></div></td></tr><?php endif; ?>
</tbody></table></div></div>

<?php elseif ($section === "users" && hasPerm('users')): ?>
<div class="admin-topbar"><h1><i class="fas fa-users" style="color:var(--blue)"></i> User Management</h1></div>
<?php if ($umsg): ?>
    <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($umsg) ?></div>
<?php endif; ?>
<?php if ($pendingUsersCount > 0): ?>
<div class="alert alert-warning"><i class="fas fa-user-clock"></i> <strong><?= $pendingUsersCount ?></strong> user(s) are pending approval.</div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><div class="panel-title"><i class="fas fa-list"></i> Existing Users</div></div>
    <div class="panel-body" style="padding:0">
        <table class="data-table">
            <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th><th>Set Password</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($allUsers as $usr):
                $userRole = !empty($usr['role']) ? $usr['role'] : getUserRole($usr['permissions'] ?? null);
                $roleInfo = $allRoles[$userRole] ?? ['label'=>'Custom','icon'=>'fas fa-user-gear','color'=>'var(--gold)'];
            ?>
                <tr>
                    <td style="color:var(--muted);font-size:11px"><?= $usr['id'] ?></td>
                    <td style="font-weight:600"><?= htmlspecialchars($usr['username']) ?></td>
                    <td><?= htmlspecialchars($usr['full_name'] ?? 'N/A') ?></td>
                    <td><span class="status-badge" style="background:<?= roleColor($roleInfo['color']) ?>;color:<?= $roleInfo['color'] ?>"><i class="<?= $roleInfo['icon'] ?>"></i> <?= $roleInfo['label'] ?></span></td>
                    <td>
                        <?php if (!empty($usr['is_approved'])): ?><span class="status-badge status-active"><i class="fas fa-circle"></i> Approved</span>
                        <?php else: ?><span class="status-badge status-pending"><i class="fas fa-circle"></i> Pending</span><?php endif; ?>
                    </td>
                    <td>
                        <form method="POST" style="display:flex;gap:5px;align-items:center">
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
                            <input type="hidden" name="user_action" value="update_password">
                            <input type="hidden" name="target_user_id" value="<?= $usr['id'] ?>">
                            <input type="text" name="new_password" class="form-input" style="padding:5px 8px;font-size:12px;width:120px" placeholder="New password" required>
                            <button type="submit" class="btn btn-sm btn-outline"><i class="fas fa-key"></i> Set</button>
                        </form>
                    </td>
                    <td>
                        <div class="tbl-actions" style="gap:4px">
                            <?php if (empty($usr['is_approved'])): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Approve?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="user_action" value="approve_user"><input type="hidden" name="target_user_id" value="<?= $usr['id'] ?>"><button type="submit" class="tbl-btn tbl-btn-approve" title="Approve" style="color:var(--green)"><i class="fas fa-check"></i></button></form>
                            <?php elseif ($usr['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Revoke?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="user_action" value="unapprove_user"><input type="hidden" name="target_user_id" value="<?= $usr['id'] ?>"><button type="submit" class="tbl-btn tbl-btn-revoke" title="Revoke" style="color:#b8860b"><i class="fas fa-user-lock"></i></button></form>
                            <?php endif; ?>
                            <?php if ($usr['id'] != $_SESSION['user_id']): ?>
                                <form method="POST" style="display:inline" onsubmit="return confirm('Delete?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><input type="hidden" name="user_action" value="delete_user"><input type="hidden" name="user_delete_id" value="<?= $usr['id'] ?>"><button type="submit" class="tbl-btn tbl-btn-delete" title="Delete"><i class="fas fa-trash"></i></button></form>
                            <?php else: ?>
                                <span style="font-size:10px;color:var(--muted);padding:4px 8px;background:var(--bg);border-radius:6px">You</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($allUsers)): ?><tr><td colspan="7"><div class="empty-state"><p>No users found</p></div></td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
 $usersJsData = []; $firstUserId = 0;
foreach ($allUsers as $usr) {
    $uRole = !empty($usr['role']) ? $usr['role'] : getUserRole($usr['permissions'] ?? null);
    $uPermsRaw = $usr['permissions'] ?? null;
    $uPermsDecoded = json_decode($uPermsRaw ?? '', true) ?: [];
    // ★ Super Admin = NULL permissions, role 'super_admin', OR a stored permission list covering every permission
    $uIsSuper = ($uPermsRaw === null) || ($uRole === 'super_admin') || (is_array($uPermsDecoded) && !array_diff(array_keys($allPermissions), $uPermsDecoded));
    $uPerms = $uIsSuper ? array_keys($allPermissions) : $uPermsDecoded;
    $isSelf = ($usr['id'] == $_SESSION['user_id']);
    // ★ Include ALL users (admin + super admins too), but mark them as 'locked'
    //   so the JS can disable the form when they are selected (display-only).
    $usersJsData[$usr['id']] = [
        'role'        => $uRole,
        'permissions' => $uPerms,
        'locked'      => ($isSelf || $uIsSuper),
        'is_self'     => $isSelf,
        'is_super'    => $uIsSuper,
    ];
    if (!$firstUserId && !$isSelf && !$uIsSuper) $firstUserId = $usr['id'];
}
?>
<?php if ($currentUserPerms === null && !empty($usersJsData)): ?>
<div class="panel">
    <div class="panel-header"><div class="panel-title"><i class="fas fa-shield-halved"></i> Permission Manager</div><span style="font-size:10px;color:var(--muted)">Super Admin only</span></div>
    <div class="panel-body">
        <div class="form-group">
            <label class="form-label"><i class="fas fa-user-gear" style="color:var(--purple)"></i> Select User to Manage</label>
            <select id="permUserSelect" class="form-select" style="max-width:350px" onchange="loadUserPerms(this.value)">
                <option value="">— Select User —</option>
                <?php foreach ($allUsers as $usr):
                    $uRole = !empty($usr['role']) ? $usr['role'] : getUserRole($usr['permissions'] ?? null);
                    $isSelf = ($usr['id'] == $_SESSION['user_id']);
                    $isSuper = ($uRole === 'super_admin') || (($usr['permissions'] ?? 'null') === null);
                    $suffix = $isSelf ? ' — You (Super Admin)' : ($isSuper ? ' — Super Admin' : '');
                ?>
                <option value="<?= $usr['id'] ?>" <?= ($isSelf || $isSuper) ? 'data-locked="1"' : '' ?>>
                    <?= htmlspecialchars($usr['username']) ?> (<?= htmlspecialchars($usr['full_name'] ?? 'N/A') ?>)<?= $suffix ?>
                </option>
                <?php endforeach; ?>
            </select>
            <div id="permLockedNotice" style="display:none;margin-top:8px;padding:8px 12px;background:rgba(212,160,23,.1);border:1px solid var(--gold);border-radius:6px;font-size:11.5px;color:#b8860b;font-weight:600;">
                <i class="fas fa-lock"></i> <span id="permLockedText">This user is a Super Admin and cannot be modified.</span>
            </div>
        </div>
        <form method="POST" id="roleForm" style="margin-bottom:18px;padding-bottom:18px;border-bottom:1px solid var(--border)">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
            <input type="hidden" name="user_action" value="update_role">
            <input type="hidden" name="target_user_id" id="roleTargetUser" value="">
            <div class="form-row" style="align-items:center">
                <div class="form-group" style="margin-bottom:0">
                    <label class="form-label"><i class="fas fa-id-badge" style="color:var(--purple)"></i> Role</label>
                    <select name="role" id="roleSelect" class="form-select" style="width:200px" onchange="if(this.value && this.value !== 'custom'){document.getElementById('roleTargetUser').value=document.getElementById('permUserSelect').value;this.form.submit();}">
                        <option value="">— Select Role —</option>
                        <?php foreach ($allRoles as $roleKey => $roleInfo): ?>
                        <option value="<?= $roleKey ?>"><?= $roleInfo['label'] ?></option>
                        <?php endforeach; ?>
                        <option value="custom">Custom (Manual)</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom:0;display:flex;align-items:flex-end"><span style="font-size:10px;color:var(--muted)">Auto-saves on change</span></div>
            </div>
        </form>
        <form method="POST" id="permForm">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
            <input type="hidden" name="user_action" value="update_permissions">
            <input type="hidden" name="target_user_id" id="permTargetUser" value="">
            <div style="font-size:12px;font-weight:700;margin-bottom:10px;color:var(--fg)"><i class="fas fa-check-square" style="color:var(--purple)"></i> Manual Permissions</div>
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px">
                <?php foreach ($allPermissions as $permKey => $permInfo): ?>
                <label class="perm-chip" style="min-width:140px">
                    <input type="checkbox" name="permissions[]" value="<?= $permKey ?>" id="perm_<?= $permKey ?>">
                    <i class="<?= $permInfo['icon'] ?>" style="color:var(--muted);font-size:11px"></i>
                    <?= $permInfo['label'] ?>
                </label>
                <?php endforeach; ?>
            </div>
            <button type="submit" class="btn btn-sm btn-purple" onclick="document.getElementById('permTargetUser').value=document.getElementById('permUserSelect').value">
                <i class="fas fa-save"></i> Save Permissions
            </button>
        </form>
    </div>
</div>
<?php elseif ($currentUserPerms !== null): ?>
<div class="panel"><div class="panel-body" style="text-align:center;padding:40px 20px">
    <i class="fas fa-lock" style="font-size:48px;color:var(--muted);opacity:.3;margin-bottom:12px"></i>
    <p style="font-size:13px;color:var(--muted)">Only Super Admin can manage permissions.</p>
</div></div>
<?php endif; ?>

<div class="panel">
    <div class="panel-header"><div class="panel-title"><i class="fas fa-info-circle"></i> Role & Permission System</div></div>
    <div class="panel-body" style="font-size:13px;color:var(--muted);line-height:1.8">
        <p><i class="fas fa-crown" style="color:var(--purple)"></i> <strong>Super Admin</strong> — Full access to every section, including Visitor Monitor. All permissions are stored in the database. Only Super Admin can manage permissions.</p>
        <p><i class="fas fa-id-badge" style="color:var(--blue)"></i> <strong>Quick Role Assign</strong> — Select user → Select role → Auto-saves (permissions saved to DB).</p>
        <p><i class="fas fa-shield-halved" style="color:var(--purple)"></i> <strong>Manual Permissions</strong> — Select user → Check/uncheck → Save. Role stays unchanged.</p>
        <p><i class="fas fa-user-pen" style="color:var(--blue)"></i> <strong>Editor</strong> — Dashboard + Add/Manage News only.</p>
        <p><i class="fas fa-newspaper" style="color:var(--green)"></i> <strong>Publisher</strong> — News + Categories + Home Panels.</p>
        <p><i class="fas fa-rectangle-ad" style="color:var(--gold)"></i> <strong>Ad Manager</strong> — Dashboard + Advertisements only.</p>
        <p><i class="fas fa-share-nodes" style="color:#25D366"></i> <strong>Social Manager</strong> — Social + Contact + Videos.</p>
        <p><i class="fas fa-chart-line" style="color:#FF9933"></i> <strong>Market Viewer</strong> — Dashboard + NSE Market only.</p>
        <p><i class="fas fa-eye" style="color:var(--muted)"></i> <strong>Viewer</strong> — Dashboard only (read-only).</p>
        <p><i class="fas fa-chart-line" style="color:#FF9933"></i> <strong>Visitor Monitor</strong> — Not tied to any role; grant it to any user via Manual Permissions.</p>
        <p><i class="fas fa-bolt" style="color:var(--muted)"></i> <strong>Breaking News permission removed</strong> — Breaking news is managed inside Add News, so its separate permission option is gone.</p>
    </div>
</div>

<script>
var usersData = <?= json_encode($usersJsData) ?>;
function loadUserPerms(userId) {
    var uid = parseInt(userId);
    var roleSelect = document.getElementById('roleSelect');
    var permCbs = document.querySelectorAll('input[name="permissions[]"]');
    var notice = document.getElementById('permLockedNotice');
    var noticeText = document.getElementById('permLockedText');
    var saveBtn = document.querySelector('#permForm button[type="submit"]');

    if (!uid || !usersData[uid]) {
        if (roleSelect) { roleSelect.value = ''; roleSelect.disabled = false; }
        permCbs.forEach(function(cb) { cb.checked = false; cb.disabled = false; });
        if (notice) notice.style.display = 'none';
        if (saveBtn) saveBtn.disabled = false;
        return;
    }
    var data = usersData[uid];
    var locked = !!data.locked;

    // Populate values
    if (roleSelect) roleSelect.value = data.role;
    permCbs.forEach(function(cb) { cb.checked = data.permissions.indexOf(cb.value) !== -1; });
    document.getElementById('roleTargetUser').value = uid;
    document.getElementById('permTargetUser').value = uid;

    // ★ Lock down the form for self / super admins (display only)
    if (roleSelect) roleSelect.disabled = locked;
    permCbs.forEach(function(cb) { cb.disabled = locked; });
    if (saveBtn) saveBtn.disabled = locked;

    if (locked) {
        if (notice && noticeText) {
            noticeText.innerHTML = data.is_self
                ? 'This is your own account (Super Admin). Self-modification is disabled to prevent lockout.'
                : 'This user is a Super Admin with full access. Super Admin permissions cannot be changed.';
            notice.style.display = 'block';
        }
    } else {
        if (notice) notice.style.display = 'none';
    }
}
<?php if ($firstUserId): ?>
document.addEventListener('DOMContentLoaded', function() {
    var select = document.getElementById('permUserSelect');
    if (select && select.options.length > 1) { select.selectedIndex = 1; loadUserPerms(select.value); }
});
<?php endif; ?>
</script>

<?php elseif ($section === "nse_market" && hasPerm('nse_market')): ?>
<div class="nse-india-theme">
    <div class="admin-topbar"><h1><i class="fas fa-chart-line" style="color:#FF9933"></i> NSE India — Live Market</h1></div>
    <div class="nse-ticker" id="nseTicker">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;padding-bottom:10px;border-bottom:1px solid rgba(255,255,255,0.2);flex-wrap:wrap;gap:10px;">
            <div class="nse-ticker-title" style="margin-bottom:0"><i class="fas fa-signal"></i> NSE Market Status</div>
            <div style="display:flex;gap:15px;align-items:center;flex-wrap:wrap;justify-content:flex-end;">
                <div id="nseTimeDate" style="font-size:13px;color:#fff;font-weight:600;line-height:1.4;text-align:right;background:rgba(255,255,255,0.1);padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,0.2);"><i class="far fa-calendar-alt" style="color:#FF9933;margin-right:5px;"></i> -- --- ----<br><i class="far fa-clock" style="color:#138808;margin-right:5px;"></i> --:--:-- (IST)</div>
                <div id="nseRefreshTimer" style="font-size:11px;color:#138808;font-weight:600;min-width:140px;text-align:right;"><i class="fas fa-hourglass-half"></i> Waiting...</div>
            </div>
        </div>
        <div id="nseContent" style="min-height:60px;display:flex;align-items:center;color:#eee"><i class="fas fa-spinner fa-spin" style="margin-right:8px"></i> Please wait...</div>
    </div>
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:22px">
        <div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-arrow-trend-up"></i> Top Gainers</div></div><div class="panel-body" style="padding:0"><table class="data-table" id="nseGainers"><thead><tr><th>Symbol</th><th>Price</th><th>Change</th></tr></thead><tbody><tr><td colspan="3" style="text-align:center;color:var(--muted);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody></table></div></div>
        <div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-arrow-trend-down"></i> Top Losers</div></div><div class="panel-body" style="padding:0"><table class="data-table" id="nseLosers"><thead><tr><th>Symbol</th><th>Price</th><th>Change</th></tr></thead><tbody><tr><td colspan="3" style="text-align:center;color:var(--muted);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody></table></div></div>
    </div>
    <div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-eye"></i> Live Share Market Company List (Watchlist)</div></div><div class="panel-body" style="padding:0"><table class="data-table" id="nseWatchlist"><thead><tr><th>Company</th><th>Price</th><th>Change</th><th>High</th><th>Low</th><th>Volume</th></tr></thead><tbody><tr><td colspan="6" style="text-align:center;color:var(--muted);padding:20px"><i class="fas fa-spinner fa-spin"></i> Loading...</td></tr></tbody></table></div></div>
</div>
<script>
function updateNSEClock(){var now=new Date();var utc=now.getTime()+(now.getTimezoneOffset()*60000);var istDate=new Date(utc+5.5*3600000);var day=String(istDate.getDate()).padStart(2,'0');var mn=["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];var month=mn[istDate.getMonth()];var year=istDate.getFullYear();var dateStr=day+' '+month+' '+year;var hours=istDate.getHours();var ampm=hours>=12?'PM':'AM';hours=hours%12;hours=hours?hours:12;var minutes=String(istDate.getMinutes()).padStart(2,'0');var seconds=String(istDate.getSeconds()).padStart(2,'0');var timeStr=String(hours).padStart(2,'0')+':'+minutes+':'+seconds+' '+ampm;var el=document.getElementById('nseTimeDate');if(el)el.innerHTML='<i class="far fa-calendar-alt" style="color:#FF9933;margin-right:5px;"></i> '+dateStr+'<br><i class="far fa-clock" style="color:#138808;margin-right:5px;"></i> '+timeStr+' (IST)';}
setInterval(updateNSEClock,1000);updateNSEClock();
var isNSELoading=false,nextRefreshTime=0,countdownInterval=null;
function scheduleNextRefresh(delay){nextRefreshTime=Date.now()+delay;if(countdownInterval)clearInterval(countdownInterval);countdownInterval=setInterval(function(){var t=document.getElementById('nseRefreshTimer');if(!t)return;if(isNSELoading){t.innerHTML='<i class="fas fa-sync-alt fa-spin"></i> Fetching data from NSE...';return;}var r=Math.max(0,(nextRefreshTime-Date.now())/1000);if(r>0){t.innerHTML='<i class="fas fa-clock"></i> Next auto-refresh in '+r.toFixed(1)+'s';}else{t.innerHTML='<i class="fas fa-sync-alt fa-spin"></i> Refreshing...';}},100);}
function loadNSE(){if(isNSELoading)return;isNSELoading=true;var t=document.getElementById('nseRefreshTimer');if(t)t.innerHTML='<i class="fas fa-sync-alt fa-spin"></i> Fetching data from NSE...';fetch('?page=admin_dashboard&section=api_nse_data').then(function(r){return r.json();}).then(function(data){var src=data._source||'live';var sc=data.market_status==='open'?'#22c55e':'#ef4444';var st=data.market_status==='open'?'<span class="nse-status-live">LIVE</span>':'<span class="nse-status-closed">CLOSED</span>';var signalIcon=data.market_status==='open'?'<i class="fas fa-signal nse-signal-anim"></i>':'<i class="fas fa-signal"></i>';var ih='';if(data.indices&&data.indices.length>0){data.indices.forEach(function(idx){var cls=idx.change>=0?'nse-idx-up':'nse-idx-down';var ar=idx.change>=0?'&#9650;':'&#9660;';ih+='<div class="nse-idx-item"><div class="nse-idx-name">'+idx.symbol+'</div><div class="nse-idx-val '+cls+'">'+idx.last.toFixed(2)+' <span style="font-size:11px">'+ar+' '+Math.abs(idx.change).toFixed(2)+' ('+Math.abs(idx.changePercent).toFixed(2)+'%)</span></div></div>';});}document.getElementById('nseContent').innerHTML='<div class="nse-ticker-title" style="margin-bottom:10px">'+signalIcon+' Market Status &mdash; '+st+' <span style="color:#555;font-size:9px">['+src+']</span></div><div class="nse-indices">'+ih+'</div>';if(data.error)document.getElementById('nseContent').innerHTML+='<div style="color:#ef4444;font-size:12px;margin-top:8px"><i class="fas fa-exclamation-triangle"></i> '+data.error+'</div>';function ft(id,rows,cols){var tb=document.querySelector('#'+id+' tbody');if(!rows||!rows.length){var eh='<tr><td colspan="'+cols+'" style="text-align:center;color:var(--muted);padding:20px">No data</td></tr>';if(tb.innerHTML!==eh)tb.innerHTML=eh;return;}var nh=rows.map(function(r){var cls=(r.change||0)>=0?'nse-idx-up':'nse-idx-down';var sg=(r.change||0)>=0?'+':'';var sh='';if(cols===6){sh='<td style="font-weight:600"><div>'+(r.name||r.symbol)+'</div><small style="color:#888;font-weight:400">'+r.symbol+'</small></td>';}else{sh='<td style="font-weight:600>'+r.symbol+'</td>';}var h=sh+'<td style="font-weight:700>'+r.last.toFixed(2)+'</td><td class="'+cls+'" style="font-weight:600">'+sg+(r.change||0).toFixed(2)+' ('+sg+(r.changePercent||0).toFixed(2)+'%)</td>';if(cols>3)h+='<td>'+(r.high||0).toFixed(2)+'</td><td>'+(r.low||0).toFixed(2)+'</td><td>'+Number(r.volume||0).toLocaleString()+'</td>';return '<tr>'+h+'</tr>';}).join('');if(tb.innerHTML!==nh)tb.innerHTML=nh;}ft('nseGainers',data.gainers,3);ft('nseLosers',data.losers,3);ft('nseWatchlist',data.watchlist,6);isNSELoading=false;scheduleNextRefresh(500);}).catch(function(err){document.getElementById('nseContent').innerHTML='<div style="color:#ef4444"><i class="fas fa-exclamation-triangle"></i> Data load failed: '+err.message+'</div>';isNSELoading=false;scheduleNextRefresh(500);});}
loadNSE();
</script>

<?php elseif ($section === "password" && hasPerm('password')): ?>
<div class="admin-topbar"><h1><i class="fas fa-key" style="color:var(--purple)"></i> Change Password</h1></div>
<?php if ($passError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($passError) ?></div><?php endif; ?>
<?php if ($passSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($passSuccess) ?></div><?php endif; ?>

<?php if ($currentUserPerms !== null): // ★ Info banner for non-super-admin users ?>
<div class="panel" style="border-left:5px solid var(--gold);">
    <div class="panel-body" style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;">
        <i class="fas fa-circle-info" style="color:var(--gold);font-size:18px;flex-shrink:0;margin-top:2px;"></i>
        <div style="font-size:12.5px;color:var(--fg);line-height:1.7;">
            <strong style="color:var(--gold);">Approval required:</strong> Your password change request will be sent to a Super Admin for approval. Your <em>current</em> password will continue to work until the request is approved.
        </div>
    </div>
</div>
<?php else: ?>
<div class="panel" style="border-left:5px solid var(--green);">
    <div class="panel-body" style="display:flex;align-items:flex-start;gap:12px;padding:14px 18px;">
        <i class="fas fa-circle-info" style="color:var(--green);font-size:18px;flex-shrink:0;margin-top:2px;"></i>
        <div style="font-size:12.5px;color:var(--fg);line-height:1.7;">
            <strong style="color:var(--green);">Super Admin:</strong> Your password will be changed immediately — no approval required.
        </div>
    </div>
</div>
<?php endif; ?>

<div class="panel"><div class="panel-body"><form method="POST" class="password-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
<div class="form-group"><label class="form-label">Current Password *</label><input type="text" name="current_password" class="form-input" required></div>
<div class="form-group"><label class="form-label">New Password *</label><input type="text" name="new_password" class="form-input" id="newPass" required oninput="cps(this.value)"><div class="pass-strength"><div class="pass-strength-bar" id="psB" style="width:0;background:var(--border)"></div></div><div class="form-hint" id="psT">At least 4 characters</div></div>
<div class="form-group"><label class="form-label">Confirm *</label><input type="text" name="confirm_password" class="form-input" required></div>
<button type="submit" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-save"></i> <?= $currentUserPerms === null ? 'Change Password' : 'Submit Request' ?></button>
</form></div></div>
<script>
function cps(v){var b=document.getElementById('psB'),t=document.getElementById('psT'),s=0;if(v.length>=4)s++;if(v.length>=8)s++;if(/[A-Z]/.test(v))s++;if(/[0-9]/.test(v))s++;if(/[^A-Za-z0-9]/.test(v))s++;var l=[{w:'0%',c:'var(--border)',x:'At least 4 characters'},{w:'20%',c:'#dc2626',x:'Very Weak'},{w:'40%',c:'#f97316',x:'Weak'},{w:'60%',c:'#eab308',x:'Medium'},{w:'80%',c:'#22c55e',x:'Good'},{w:'100%',c:'#16a34a',x:'Strong'}];var r=l[s];b.style.width=r.w;b.style.background=r.c;t.textContent=r.x;t.style.color=r.c;}
</script>

<?php elseif ($section === "articles" && hasPerm('manage_news')): ?>
<div class="admin-topbar"><h1><i class="fas fa-file-lines" style="color:var(--accent)"></i> Articles</h1><div class="topbar-actions"><a href="?page=admin_dashboard&section=manage" class="topbar-btn topbar-btn-outline"><i class="fas fa-newspaper"></i> Go to Manage News</a></div></div>

<!-- ★ Under Construction Section with Image -->
<div class="panel" style="border:2px dashed var(--accent); background:linear-gradient(135deg, rgba(255,193,7,0.05), rgba(196,30,58,0.05));">
    <div class="panel-body" style="text-align:center; padding:50px 25px;">
        <!-- Inline SVG Illustration (self-contained, no external file) -->
        <svg xmlns="http://www.w3.org/2000/svg" width="320" height="260" viewBox="0 0 320 260" style="margin:0 auto 25px; display:block; max-width:100%; height:auto;">
            <defs>
                <linearGradient id="hatGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#FFD54F"/>
                    <stop offset="100%" style="stop-color:#FF8F00"/>
                </linearGradient>
                <linearGradient id="bgGrad" x1="0%" y1="0%" x2="0%" y2="100%">
                    <stop offset="0%" style="stop-color:#FFF8E1"/>
                    <stop offset="100%" style="stop-color:#FFECB3"/>
                </linearGradient>
                <pattern id="stripes" patternUnits="userSpaceOnUse" width="24" height="12" patternTransform="rotate(45)">
                    <rect width="12" height="12" fill="#FFC107"/>
                    <rect x="12" width="12" height="12" fill="#1A1A1A"/>
                </pattern>
            </defs>
            <!-- Background circle -->
            <circle cx="160" cy="125" r="120" fill="url(#bgGrad)" stroke="#FFC107" stroke-width="3"/>
            <!-- Hard hat -->
            <path d="M 95 150 Q 95 105 160 105 Q 225 105 225 150 L 235 165 L 85 165 Z" fill="url(#hatGrad)" stroke="#E65100" stroke-width="2"/>
            <ellipse cx="160" cy="165" rx="75" ry="10" fill="#FF8F00" stroke="#E65100" stroke-width="1.5"/>
            <!-- Hat top button -->
            <rect x="153" y="92" width="14" height="20" fill="#FF8F00" stroke="#E65100" stroke-width="1.5" rx="2"/>
            <!-- Hat ridge line -->
            <path d="M 160 105 L 160 165" stroke="#E65100" stroke-width="2" opacity="0.4"/>
            <!-- Tools crossed (wrench + hammer) -->
            <g transform="translate(160, 200)">
                <!-- Wrench -->
                <g transform="rotate(-25)">
                    <rect x="-55" y="-4" width="55" height="8" fill="#546E7A" rx="2"/>
                    <path d="M -55 -8 L -70 -8 L -75 0 L -70 8 L -55 8 L -50 4 L -50 -4 Z" fill="#37474F"/>
                </g>
                <!-- Hammer -->
                <g transform="rotate(25)">
                    <rect x="0" y="-4" width="55" height="8" fill="#8B4513" rx="2"/>
                    <rect x="50" y="-12" width="20" height="24" fill="#546E7A" rx="2"/>
                    <rect x="50" y="-2" width="20" height="4" fill="#37474F"/>
                </g>
            </g>
            <!-- Warning stripes at bottom -->
            <rect x="20" y="230" width="280" height="18" fill="url(#stripes)" rx="3"/>
            <!-- Sparkle stars -->
            <text x="55" y="55" font-size="20" fill="#FFA000">✦</text>
            <text x="260" y="65" font-size="16" fill="#FFA000">✦</text>
            <text x="270" y="120" font-size="14" fill="#FFA000">✦</text>
            <text x="45" y="180" font-size="12" fill="#FFA000">✦</text>
        </svg>
        
        <h2 style="font-family:'Noto Serif Bengali',serif; font-size:30px; color:var(--accent); margin-bottom:12px; font-weight:800; letter-spacing:1px;">
            <i class="fas fa-tools" style="color:var(--gold); margin-right:8px;"></i>Under Construction<i class="fas fa-tools" style="color:var(--gold); margin-left:8px;"></i>
        </h2>
        <p style="font-size:20px; color:var(--fg); margin-bottom:8px; font-weight:700; font-family:'Noto Serif Bengali',serif;">
            এই সেকশনটি নির্মাণাধীন রয়েছে
        </p>
        <p style="font-size:14px; color:var(--muted); margin-bottom:25px; max-width:500px; margin-left:auto; margin-right:auto; line-height:1.6;">
            Articles section is currently under development. Our team is working hard to bring you a better experience. Please check back soon!
        </p>
        <div style="display:inline-flex; align-items:center; gap:10px; background:var(--accent-light); padding:12px 24px; border-radius:10px; border:1.5px solid var(--accent); margin-bottom:25px;">
            <i class="fas fa-clock" style="color:var(--accent); font-size:18px;"></i>
            <span style="color:var(--accent-dark); font-weight:700; font-size:15px; font-family:'Noto Serif Bengali',serif;">শীঘ্রই আসছে</span>
        </div>
        <div style="margin-top:15px;">
            <a href="?page=admin_dashboard&section=dashboard" class="btn btn-primary" style="margin-right:8px;"><i class="fas fa-home"></i> Back to Dashboard</a>
            <a href="?page=admin_dashboard&section=manage" class="btn btn-outline"><i class="fas fa-newspaper"></i> Manage News</a>
        </div>
    </div>
</div>

<?php elseif ($section === "epaper" && hasPerm('manage_news')): ?>
<div class="admin-topbar"><h1><i class="far fa-newspaper" style="color:#B71C1C"></i> ই-পেপার ম্যানেজমেন্ট</h1><div class="topbar-actions"><a href="?page=admin_epaper" target="_blank" class="topbar-btn topbar-btn-primary"><i class="fas fa-external-link-alt"></i> ফুল স্ক্রিন খুলুন</a></div></div>

<!-- ★ e-Paper Management Section -->
<div class="panel" style="border:2px solid #B71C1C; overflow:hidden;">
    <div class="panel-header" style="background:linear-gradient(90deg,#B71C1C 0%,#8B1528 100%);">
        <div class="panel-title" style="color:#fff;"><i class="far fa-newspaper"></i> ই-পেপার ম্যানেজমেন্ট প্যানেল</div>
        <span style="font-size:11px; color:#fff; opacity:.85; background:rgba(255,255,255,.15); padding:3px 10px; border-radius:12px;">
            <i class="fas fa-circle" style="font-size:7px; color:#FFD54F; margin-right:4px;"></i>এম্বেডেড ভিউ
        </span>
    </div>
    <div class="panel-body" style="padding:0; background:#f5f0e8;">
        <!-- Quick Action Cards -->
        <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:12px; padding:14px 18px; background:#fff; border-bottom:2px solid #B71C1C;">
            <a href="?page=admin_epaper&action=upload" target="_blank" style="display:flex; align-items:center; gap:10px; padding:12px; background:linear-gradient(135deg,#B71C1C,#8B1528); color:#fff; border-radius:8px; text-decoration:none; transition:all .2s;">
                <i class="fas fa-cloud-arrow-up" style="font-size:20px;"></i>
                <div><div style="font-weight:700; font-size:13px;">নতুন ই-পেপার আপলোড</div><div style="font-size:10px; opacity:.85;">PDF ফাইল আপলোড করুন</div></div>
            </a>
            <a href="?page=admin_epaper" target="_blank" style="display:flex; align-items:center; gap:10px; padding:12px; background:#fff; color:#B71C1C; border:1.5px solid #B71C1C; border-radius:8px; text-decoration:none; transition:all .2s;">
                <i class="fas fa-list" style="font-size:20px;"></i>
                <div><div style="font-weight:700; font-size:13px;">সব ই-পেপার দেখুন</div><div style="font-size:10px; color:#666;">ম্যানেজ ও ডিলিট</div></div>
            </a>
            <a href="?page=admin_epaper&action=settings" target="_blank" style="display:flex; align-items:center; gap:10px; padding:12px; background:#fff; color:#666; border:1.5px solid #ddd; border-radius:8px; text-decoration:none; transition:all .2s;">
                <i class="fas fa-gear" style="font-size:20px;"></i>
                <div><div style="font-weight:700; font-size:13px;">সেটিংস</div><div style="font-size:10px; color:#666;">কনফিগারেশন</div></div>
            </a>
        </div>

        <!-- Embedded iframe to admin_epaper.php -->
        <div style="position:relative; padding:14px 18px;">
            <div style="background:#fff; border:1.5px solid #ddd; border-radius:8px; overflow:hidden; box-shadow:0 4px 12px rgba(0,0,0,.05);">
                <div style="background:#f5f0e8; padding:8px 14px; border-bottom:1px solid #ddd; display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:12px; color:#666; font-weight:600;"><i class="fas fa-window-maximize" style="margin-right:5px; color:#B71C1C;"></i>ই-পেপার ম্যানেজমেন্ট ইন্টারফেস</span>
                    <a href="?page=admin_epaper" target="_blank" style="font-size:11px; color:#B71C1C; text-decoration:none; font-weight:600;"><i class="fas fa-external-link-alt" style="margin-right:3px;"></i>নতুন ট্যাবে খুলুন</a>
                </div>
                <iframe src="?page=admin_epaper" style="width:100%; height:600px; border:0; display:block;" loading="lazy" title="e-Paper Management"></iframe>
            </div>
            <p style="font-size:11px; color:#999; margin-top:10px; text-align:center;">
                <i class="fas fa-circle-info"></i> উপরের প্যানেলে সরাসরি ই-পেপার ম্যানেজ করতে পারবেন, অথবা নতুন ট্যাবে খুলেও কাজ করতে পারবেন।
            </p>
        </div>
    </div>
</div>

<!-- Fallback notice if admin_epaper.php not found -->
<div class="panel" style="border:1.5px dashed #ddd;">
    <div class="panel-body" style="padding:14px 18px; background:#fffbeb;">
        <p style="font-size:12px; color:#666; margin:0;">
            <i class="fas fa-triangle-exclamation" style="color:#f59e0b; margin-right:5px;"></i>
            <strong>নোট:</strong> যদি উপরের ইন্টারফেস লোড না হয়, তাহলে <code style="background:#fef3c7; padding:1px 6px; border-radius:3px;">admin_epaper.php</code> ফাইলটি আপনার সার্ভারে আছে কিনা যাচাই করুন।
            <a href="?page=admin_epaper" target="_blank" style="color:#B71C1C; font-weight:600; margin-left:6px;">সরাসরি খুলুন →</a>
        </p>
    </div>
</div>

<?php elseif ($section === "visitor_stats" && hasPerm('visitor_stats')): ?>
<!-- ★ Visitor Stats Monitoring Section — full page view (requires 'visitor_stats' permission) -->
<?php
// ★ v3.58: Bulletproof wrapping — errors are caught and displayed, never white page
error_reporting(E_ALL);
ini_set('display_errors', '1');

try {

// Set timezone (same as main site)
date_default_timezone_set('Asia/Kolkata');
try { $pdo->exec("SET time_zone = '+05:30';"); } catch (Exception $e) {}

// Bengali digit conversion helper (guarded — safe if already defined)
if (!function_exists('toBn')) {
    $bnD = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    $enD = ['0','1','2','3','4','5','6','7','8','9'];
    function toBn($n) {
        global $bnD, $enD;
        return str_replace($enD, $bnD, (string)$n);
    }
} else {
    $bnD = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
    $enD = ['0','1','2','3','4','5','6','7','8','9'];
}

// ★ v3.53: Ensure visit_log table exists (created here as safety net
//    in case admin is accessed without index_mo.php running first)
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS visit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        visitor_hash VARCHAR(64) NOT NULL,
        visit_date DATE NOT NULL,
        visit_count INT DEFAULT 1,
        UNIQUE KEY uk_hash_date (visitor_hash, visit_date)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// ── Fetch summary stats ──
$totalVisitors = 0;
$todayVisitors = 0;
$weekVisitors  = 0;
try {
    $totalVisitors = (int)$pdo->query("SELECT COUNT(*) FROM visitor_stats")->fetchColumn();
    $todayVisitors = (int)$pdo->query("SELECT COUNT(*) FROM visitor_stats WHERE DATE(last_activity) = CURDATE()")->fetchColumn();
    $weekVisitors  = (int)$pdo->query("SELECT COUNT(*) FROM visitor_stats WHERE last_activity >= (NOW() - INTERVAL 7 DAY)")->fetchColumn();
} catch (Exception $e) {}

// ── Fetch daily visitor graph (last 7 days) ──
// ★ v3.55: Separate try-catch for each source — one failure doesn't block others.
// ★ v3.56: Use FETCH_ASSOC instead of FETCH_KEY_PAIR for reliable date matching.
$bnDays = ['Sunday'=>'রবি','Monday'=>'সোম','Tuesday'=>'মঙ্গল','Wednesday'=>'বুধ','Thursday'=>'বৃহ','Friday'=>'শুক্র','Saturday'=>'শনি'];
$dailyData = [];
$rows = [];
$debugGraphSource = 'none';

// Source 1: visit_log
try {
    $stmt = $pdo->query("SELECT visit_date as d, SUM(visit_count) as c
                         FROM visit_log
                         WHERE visit_date >= (CURDATE() - INTERVAL 6 DAY)
                         GROUP BY visit_date
                         ORDER BY d ASC");
    $tempRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($tempRows as $r) {
        $rows[$r['d']] = (int)$r['c'];
    }
    if (!empty($rows)) $debugGraphSource = 'visit_log';
} catch (Exception $e) { $rows = []; }

// Source 2: visitor_stats.last_activity (if visit_log empty or failed)
if (empty($rows)) {
    try {
        $stmt = $pdo->query("SELECT DATE(last_activity) as d, COUNT(*) as c
                             FROM visitor_stats
                             WHERE last_activity >= (NOW() - INTERVAL 6 DAY)
                             GROUP BY DATE(last_activity)
                             ORDER BY d ASC");
        $tempRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tempRows as $r) {
            $rows[$r['d']] = (int)$r['c'];
        }
        if (!empty($rows)) $debugGraphSource = 'visitor_stats.last_activity';
    } catch (Exception $e) { $rows = []; }
}

// Source 3: visitor_stats.first_visit (final fallback — shows all-time data)
if (empty($rows)) {
    try {
        $stmt = $pdo->query("SELECT DATE(first_visit) as d, COUNT(*) as c
                             FROM visitor_stats
                             GROUP BY DATE(first_visit)
                             ORDER BY d DESC
                             LIMIT 7");
        $tempRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($tempRows as $r) {
            $rows[$r['d']] = (int)$r['c'];
        }
        if (!empty($rows)) $debugGraphSource = 'visitor_stats.first_visit';
    } catch (Exception $e) { $rows = []; }
}

// Source 4: last resort — just count ALL visitors and put on today's bar
if (empty($rows) && $totalVisitors > 0) {
    $todayDate = date('Y-m-d');
    $rows[$todayDate] = $totalVisitors;
    $debugGraphSource = 'total_count_fallback';
}

// Fill in all 7 days (including days with 0 visitors)
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $dayName = $bnDays[date('l', strtotime($date))];
    $count = isset($rows[$date]) ? (int)$rows[$date] : 0;
    $dailyData[] = ['date' => $date, 'day' => $dayName, 'count' => $count];
}

// Check if graph has ANY non-zero data
$graphHasData = false;
foreach ($dailyData as $d) {
    if ($d['count'] > 0) { $graphHasData = true; break; }
}

// ── Fetch OS breakdown ── (separate try-catch)
$osData = [];
try {
    $stmt = $pdo->query("SELECT os, COUNT(*) as c FROM visitor_stats WHERE os != '' GROUP BY os ORDER BY c DESC");
    $osData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Fetch browser breakdown ── (separate try-catch)
$browserData = [];
try {
    $stmt = $pdo->query("SELECT browser, COUNT(*) as c FROM visitor_stats WHERE browser != '' GROUP BY browser ORDER BY c DESC");
    $browserData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ── Fetch recent visitors ── (separate try-catch)
$recentVisitors = [];
try {
    $stmt = $pdo->query("SELECT ip_partial, os, browser, visit_count, last_activity, first_visit
                         FROM visitor_stats
                         ORDER BY last_activity DESC
                         LIMIT 15");
    $recentVisitors = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// ★ v3.55: Per-component demo fallback — each component independently falls
//    back to demo data if its query returned nothing. This ensures the graph
//    always shows bars even if one specific query failed.
$isDemoMode = false;

// Graph: if all 7 days are zero, inject demo graph data
if (!$graphHasData) {
    $isDemoMode = true;
    $demoCounts = [12, 8, 15, 20, 5, 18, 23];
    $dailyData = [];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-{$i} days"));
        $dayName = $bnDays[date('l', strtotime($date))];
        $dailyData[] = ['date' => $date, 'day' => $dayName, 'count' => $demoCounts[6 - $i]];
    }
}

// OS: if empty, inject demo
if (empty($osData)) {
    $isDemoMode = true;
    $osData = [
        ['os' => 'Android', 'c' => 68],
        ['os' => 'Windows', 'c' => 45],
        ['os' => 'iOS', 'c' => 28],
        ['os' => 'macOS', 'c' => 10],
        ['os' => 'Linux', 'c' => 5],
    ];
}

// Browser: if empty, inject demo
if (empty($browserData)) {
    $isDemoMode = true;
    $browserData = [
        ['browser' => 'Chrome', 'c' => 89],
        ['browser' => 'Safari', 'c' => 28],
        ['browser' => 'Firefox', 'c' => 15],
        ['browser' => 'Edge', 'c' => 12],
        ['browser' => 'Other', 'c' => 12],
    ];
}

// Recent visitors: if empty, inject demo
if (empty($recentVisitors)) {
    $isDemoMode = true;
    $recentVisitors = [
        ['ip_partial' => '192.168.1.*',  'os' => 'Android', 'browser' => 'Chrome',  'visit_count' => 5, 'last_activity' => date('Y-m-d H:i:s', strtotime('-2 minutes'))],
        ['ip_partial' => '10.0.0.*',     'os' => 'Windows', 'browser' => 'Edge',    'visit_count' => 3, 'last_activity' => date('Y-m-d H:i:s', strtotime('-15 minutes'))],
        ['ip_partial' => '172.16.0.*',   'os' => 'iOS',     'browser' => 'Safari',  'visit_count' => 8, 'last_activity' => date('Y-m-d H:i:s', strtotime('-32 minutes'))],
        ['ip_partial' => '203.192.*.*',  'os' => 'Android', 'browser' => 'Chrome',  'visit_count' => 2, 'last_activity' => date('Y-m-d H:i:s', strtotime('-1 hour'))],
        ['ip_partial' => '157.240.*.*',  'os' => 'macOS',   'browser' => 'Safari',  'visit_count' => 1, 'last_activity' => date('Y-m-d H:i:s', strtotime('-2 hours'))],
        ['ip_partial' => '31.13.*.*',    'os' => 'Windows', 'browser' => 'Firefox', 'visit_count' => 4, 'last_activity' => date('Y-m-d H:i:s', strtotime('-3 hours'))],
        ['ip_partial' => '151.101.*.*',  'os' => 'Linux',   'browser' => 'Chrome',  'visit_count' => 6, 'last_activity' => date('Y-m-d H:i:s', strtotime('-5 hours'))],
        ['ip_partial' => '104.16.*.*',   'os' => 'Android', 'browser' => 'Chrome',  'visit_count' => 3, 'last_activity' => date('Y-m-d H:i:s', strtotime('-8 hours'))],
    ];
}

// Summary: if totalVisitors is 0, inject demo values
if ($totalVisitors == 0) {
    $isDemoMode = true;
    $totalVisitors = 156;
    $todayVisitors = 23;
    $weekVisitors  = 89;
}

// Recalculate totals after possible demo data injection
$osTotal = array_sum(array_column($osData, 'c') ?: [1]);
$browserTotal = array_sum(array_column($browserData, 'c') ?: [1]);
$maxDaily = max(array_column($dailyData, 'count') ?: [1]);
if ($maxDaily < 5) $maxDaily = 5;

// OS icons (★ v3.59: added mobile-specific icons)
$osIcons = [
    'Windows' => 'fab fa-windows',
    'Android' => 'fab fa-android',
    'iOS' => 'fab fa-apple',
    'macOS' => 'fab fa-apple',
    'Linux' => 'fab fa-linux',
    'Other' => 'fas fa-mobile-screen',
];
// Browser icons (★ v3.59: added Samsung, Opera, UC, Mi, WebView)
$browserIcons = [
    'Chrome' => 'fab fa-chrome',
    'Firefox' => 'fab fa-firefox-browser',
    'Safari' => 'fab fa-safari',
    'Edge' => 'fab fa-edge',
    'Samsung' => 'fas fa-tv',
    'Opera' => 'fab fa-opera',
    'UC Browser' => 'fas fa-globe',
    'Mi Browser' => 'fas fa-globe',
    'WebView' => 'fas fa-mobile-screen',
    'Other' => 'fas fa-globe',
];
?>
<!-- ====== Visitor Stats Panel ====== -->
<style>
.vsp-panel {
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 2px 8px rgba(0,0,0,.08);
    overflow: hidden;
    margin-bottom: 20px;
    font-family: 'Hind Siliguri', sans-serif;
}
.vsp-header {
    background: linear-gradient(135deg, #B71C1C 0%, #7F0000 100%);
    color: #fff;
    padding: 12px 16px;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    font-weight: 700;
}
.vsp-header i { color: #D4950A; }
.vsp-body { padding: 14px; }

/* Summary cards */
.vsp-summary {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
}
.vsp-card {
    background: #f8f9fa;
    border-radius: 8px;
    padding: 10px 6px;
    text-align: center;
    border: 1px solid #e9ecef;
}
.vsp-card .num {
    font-size: 20px;
    font-weight: 700;
    color: #B71C1C;
    line-height: 1.2;
}
.vsp-card .lbl {
    font-size: 10px;
    color: #666;
    margin-top: 2px;
}

/* Daily graph */
.vsp-section-title {
    font-size: 12px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
    border-left: 3px solid #B71C1C;
    padding-left: 6px;
}
.vsp-graph {
    display: flex;
    align-items: flex-end;
    gap: 4px;
    height: 80px;
    padding: 0 4px;
    border-bottom: 1px solid #e9ecef;
    margin-bottom: 16px;
}
.vsp-bar {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: flex-end;
    height: 100%;
    position: relative;
}
.vsp-bar-fill {
    width: 100%;
    background: linear-gradient(180deg, #B71C1C 0%, #7F0000 100%);
    border-radius: 3px 3px 0 0;
    min-height: 3px !important;
    transition: height .3s;
    position: relative;
    display: block !important;
}
.vsp-bar-fill::after {
    content: attr(data-count);
    position: absolute;
    top: -16px;
    left: 50%;
    transform: translateX(-50%);
    font-size: 9px;
    color: #B71C1C;
    font-weight: 700;
    white-space: nowrap;
}
.vsp-bar-label {
    font-size: 9px;
    color: #666;
    margin-top: 4px;
}

/* OS / Browser breakdown */
.vsp-breakdown { margin-bottom: 16px; }
.vsp-br-row {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 6px;
    font-size: 11px;
}
.vsp-br-icon {
    width: 20px;
    text-align: center;
    color: #B71C1C;
    font-size: 12px;
    flex-shrink: 0;
}
.vsp-br-name {
    width: 60px;
    color: #333;
    flex-shrink: 0;
}
.vsp-br-bar {
    flex: 1;
    height: 14px;
    background: #f0f0f0;
    border-radius: 7px;
    overflow: hidden;
}
.vsp-br-fill {
    height: 100%;
    background: linear-gradient(90deg, #B71C1C 0%, #D4950A 100%);
    border-radius: 7px;
    transition: width .3s;
}
.vsp-br-count {
    width: 35px;
    text-align: right;
    color: #666;
    font-weight: 600;
    flex-shrink: 0;
}

/* Recent visitors table */
.vsp-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 10px;
}
.vsp-table th {
    background: #f8f9fa;
    padding: 6px 4px;
    text-align: left;
    font-weight: 600;
    color: #555;
    border-bottom: 2px solid #B71C1C;
    font-size: 9px;
    text-transform: uppercase;
}
.vsp-table td {
    padding: 6px 4px;
    border-bottom: 1px solid #f0f0f0;
    color: #333;
}
.vsp-table tr:hover td { background: #fff8f8; }
.vsp-table .ip { font-family: monospace; font-size: 10px; color: #B71C1C; }
.vsp-table .os-badge {
    display: inline-flex;
    align-items: center;
    gap: 3px;
    background: #f0f0f0;
    padding: 1px 6px;
    border-radius: 8px;
    font-size: 9px;
}
.vsp-table .visits {
    text-align: center;
    font-weight: 700;
    color: #B71C1C;
}
.vsp-table .time { color: #999; font-size: 9px; }
</style>

<div class="vsp-panel">
    <div class="vsp-header">
        <i class="fas fa-chart-line"></i>
        ভিজিটর পরিসংখ্যান
    </div>
    <div class="vsp-body">

        <?php if (!empty($isDemoMode)): ?>
        <div style="background:#fff3cd;border:1px solid #ffc107;color:#856404;padding:8px 12px;border-radius:6px;margin-bottom:14px;font-size:11px;font-weight:600;text-align:center;">
            <i class="fas fa-info-circle"></i> ডেমো মোড — এখনও কোনো বাস্তব ভিজিটর ডেটা নেই। ভিজিটর আসলে এখানে বাস্তব ডেটা দেখাবে।
        </div>
        <?php endif; ?>

        <!-- Summary Cards -->
        <div class="vsp-summary">
            <div class="vsp-card">
                <div class="num"><?= toBn(number_format($totalVisitors)) ?></div>
                <div class="lbl">মোট</div>
            </div>
            <div class="vsp-card">
                <div class="num"><?= toBn($todayVisitors) ?></div>
                <div class="lbl">আজ</div>
            </div>
            <div class="vsp-card">
                <div class="num"><?= toBn($weekVisitors) ?></div>
                <div class="lbl">৭ দিন</div>
            </div>
        </div>

        <!-- Daily Graph -->
        <div class="vsp-section-title">গত ৭ দিনের নতুন ভিজিটর
            <span style="float:right;font-size:9px;color:#999;font-weight:400;">source: <?= htmlspecialchars($debugGraphSource) ?> | max: <?= $maxDaily ?></span>
        </div>
        <div class="vsp-graph">
            <?php foreach ($dailyData as $d):
                $barH = $d['count'] > 0 ? max(8, round(($d['count'] / $maxDaily) * 75)) : 3;
            ?>
                <div class="vsp-bar">
                    <div class="vsp-bar-fill" data-count="<?= toBn($d['count']) ?>" style="height:<?= $barH ?>px"></div>
                    <div class="vsp-bar-label"><?= $d['day'] ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <!-- Debug: raw data -->
        <div style="font-size:8px;color:#bbb;margin-bottom:12px;text-align:center;">
            <?php foreach ($dailyData as $d): ?><?= $d['day'] ?>=<?= $d['count'] ?> | <?php endforeach; ?>
        </div>

        <!-- OS Breakdown -->
        <?php if (!empty($osData)): ?>
        <div class="vsp-section-title">OS অনুযায়ী</div>
        <div class="vsp-breakdown">
            <?php foreach ($osData as $os): ?>
                <?php $pct = $osTotal > 0 ? round(($os['c'] / $osTotal) * 100) : 0; ?>
                <div class="vsp-br-row">
                    <div class="vsp-br-icon"><i class="<?= $osIcons[$os['os']] ?? 'fas fa-question-circle' ?>"></i></div>
                    <div class="vsp-br-name"><?= htmlspecialchars($os['os']) ?></div>
                    <div class="vsp-br-bar"><div class="vsp-br-fill" style="width: <?= $pct ?>%"></div></div>
                    <div class="vsp-br-count"><?= toBn($os['c']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Browser Breakdown -->
        <?php if (!empty($browserData)): ?>
        <div class="vsp-section-title">Browser অনুযায়ী</div>
        <div class="vsp-breakdown">
            <?php foreach ($browserData as $br): ?>
                <?php $pct = $browserTotal > 0 ? round(($br['c'] / $browserTotal) * 100) : 0; ?>
                <div class="vsp-br-row">
                    <div class="vsp-br-icon"><i class="<?= $browserIcons[$br['browser']] ?? 'fas fa-globe' ?>"></i></div>
                    <div class="vsp-br-name"><?= htmlspecialchars($br['browser']) ?></div>
                    <div class="vsp-br-bar"><div class="vsp-br-fill" style="width: <?= $pct ?>%"></div></div>
                    <div class="vsp-br-count"><?= toBn($br['c']) ?></div>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Recent Visitors Table -->
        <?php if (!empty($recentVisitors)): ?>
        <div class="vsp-section-title">সাম্প্রতিক ভিজিটর</div>
        <table class="vsp-table">
            <thead>
                <tr>
                    <th>IP</th>
                    <th>OS</th>
                    <th>Browser</th>
                    <th style="text-align:center">ভিজিট</th>
                    <th>সময়</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentVisitors as $v): ?>
                    <?php
                    // Time ago
                    $timeAgo = '';
                    $ts = strtotime($v['last_activity']);
                    if ($ts) {
                        $diff = time() - $ts;
                        if ($diff < 60) $timeAgo = 'এইমাত্র';
                        elseif ($diff < 3600) $timeAgo = floor($diff/60) . ' মিনিট';
                        elseif ($diff < 86400) $timeAgo = floor($diff/3600) . ' ঘণ্টা';
                        else $timeAgo = date('d/m', $ts);
                        $timeAgo = toBn($timeAgo);
                    }
                    ?>
                    <tr>
                        <td class="ip"><?= htmlspecialchars($v['ip_partial'] ?: '—') ?></td>
                        <td><span class="os-badge"><i class="<?= $osIcons[$v['os']] ?? 'fas fa-question-circle' ?>"></i> <?= htmlspecialchars($v['os'] ?: '?') ?></span></td>
                        <td><i class="<?= $browserIcons[$v['browser']] ?? 'fas fa-globe' ?>" style="color:#666"></i> <?= htmlspecialchars($v['browser'] ?: '?') ?></td>
                        <td class="visits"><?= toBn($v['visit_count']) ?></td>
                        <td class="time"><?= $timeAgo ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p style="text-align:center;color:#999;font-size:12px;padding:20px;">এখনও কোনো ভিজিটর ডেটা নেই।</p>
        <?php endif; ?>

    </div>
</div>

<?php
} catch (Exception $e) {
    // ★ v3.58: Show error instead of white page
    echo '<div style="background:#fff;border:1px solid #C41E3A;border-radius:10px;padding:20px;margin:20px;">';
    echo '<h2 style="color:#C41E3A;font-size:16px;margin-bottom:10px;"><i class="fas fa-exclamation-triangle"></i> Visitor Monitor Error</h2>';
    echo '<p style="font-size:13px;color:#333;margin-bottom:10px;">Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p style="font-size:11px;color:#999;">File: ' . htmlspecialchars($e->getFile()) . ' | Line: ' . $e->getLine() . '</p>';
    echo '<p style="font-size:12px;color:#666;margin-top:15px;">এই error টি আমাকে দিন — আমি fix করে দেব।</p>';
    echo '</div>';
}
?>

<?php else: ?>
<div class="admin-topbar"><h1><i class="fas fa-ban" style="color:var(--accent)"></i> Access Denied</h1></div>
<div class="panel"><div class="panel-body" style="text-align:center;padding:60px 20px">
    <i class="fas fa-lock" style="font-size:60px;color:var(--muted);opacity:.3;margin-bottom:16px"></i>
    <h2 style="font-size:18px;font-weight:700;color:var(--fg);margin-bottom:8px">You don't have permission to access this section</h2>
    <p style="font-size:13px;color:var(--muted)">Please contact an administrator to grant access.</p>
    <a href="?page=admin_dashboard&section=dashboard" class="btn btn-primary" style="margin-top:16px"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
</div></div>
<?php endif; ?>

</main>
</div>
</body>
</html>