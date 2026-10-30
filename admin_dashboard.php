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
    'breaking' => ['label' => 'Breaking News', 'icon' => 'fas fa-bolt'],
    'categories' => ['label' => 'Categories', 'icon' => 'fas fa-folder-tree'],
    'home_panels' => ['label' => 'Home Panels', 'icon' => 'fas fa-table-cells'],
    'social' => ['label' => 'Social Media', 'icon' => 'fas fa-share-nodes'],
    'contact' => ['label' => 'Contact Details', 'icon' => 'fas fa-address-book'],
    'ads' => ['label' => 'Advertisements', 'icon' => 'fas fa-rectangle-ad'],
    'videos' => ['label' => 'Videos', 'icon' => 'fas fa-video'],
    'nse_market' => ['label' => 'NSE Market', 'icon' => 'fas fa-chart-line'],
    'users' => ['label' => 'User Management', 'icon' => 'fas fa-users'],
    'password' => ['label' => 'Change Password', 'icon' => 'fas fa-key'],
];

 $allRoles = [
    'super_admin' => ['label' => 'Super Admin', 'icon' => 'fas fa-crown', 'color' => 'var(--purple)', 'description' => 'Full access to everything.', 'permissions' => null],
    'editor' => ['label' => 'Editor', 'icon' => 'fas fa-user-pen', 'color' => 'var(--blue)', 'description' => 'Can add, edit, and manage news articles.', 'permissions' => ['dashboard', 'add_news', 'manage_news', 'password']],
    'publisher' => ['label' => 'Publisher', 'icon' => 'fas fa-newspaper', 'color' => 'var(--green)', 'description' => 'Can manage news, categories, breaking news, and home panels.', 'permissions' => ['dashboard', 'add_news', 'manage_news', 'breaking', 'categories', 'home_panels', 'password']],
    'ad_manager' => ['label' => 'Ad Manager', 'icon' => 'fas fa-rectangle-ad', 'color' => 'var(--gold)', 'description' => 'Can manage advertisements and ad embed codes.', 'permissions' => ['dashboard', 'ads', 'password']],
    'social_manager' => ['label' => 'Social Manager', 'icon' => 'fas fa-share-nodes', 'color' => '#25D366', 'description' => 'Can manage social media, contact details, and videos.', 'permissions' => ['dashboard', 'social', 'contact', 'videos', 'password']],
    'market_viewer' => ['label' => 'Market Viewer', 'icon' => 'fas fa-chart-line', 'color' => '#FF9933', 'description' => 'Can view NSE market data and dashboard only.', 'permissions' => ['dashboard', 'nse_market', 'password']],
    'viewer' => ['label' => 'Viewer', 'icon' => 'fas fa-eye', 'color' => 'var(--muted)', 'description' => 'Read-only access to dashboard only.', 'permissions' => ['dashboard', 'password']],
];

function getUserRole($permissionsRaw) {
    global $allRoles;
    if ($permissionsRaw === null) return 'super_admin';
    $perms = json_decode($permissionsRaw, true) ?: [];
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
    function handleUpload($inputName, $maxSizeMB = 5, $allowedTypes = null) {
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
        $dir = __DIR__ . '/../uploads/' . $sub . '/' . date('Y/m/') . '/';
        if (!is_dir($dir)) { if (!@mkdir($dir, 0755, true)) return ["error" => "Failed to create directory."]; }
        $fn = ($isAd ? 'ad_' : 'news_') . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $dest = $dir . $fn;
        if (!@move_uploaded_file($file['tmp_name'], $dest) && !@copy($file['tmp_name'], $dest)) return ["error" => "Failed to save file."];
        @chmod($dest, 0644);
        return ["path" => "uploads/" . $sub . '/' . date('Y/m/') . '/' . $fn, "full_path" => $dest, "filename" => $fn, "mime" => $dm, "size" => $file['size']];
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

 $section = $_GET["section"] ?? "dashboard";
 $validSections = ["dashboard", "add", "edit", "manage", "articles", "epaper", "categories", "social", "contact", "ads", "password", "api_subcategories", "nse_market", "api_nse_data", "api_ad_click", "videos", "home_panels", "users", "date_requests", "visitor_stats"];
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
        elseif ($uid > 0 && $uid != $_SESSION['user_id']) { $rolePerms = $allRoles[$roleKey]['permissions']; $permJson = $rolePerms === null ? null : json_encode($rolePerms); $pdo->prepare("UPDATE users SET permissions = ?, role = ? WHERE id = ?")->execute([$permJson, $roleKey, $uid]); $umsg = 'Role changed to "' . $allRoles[$roleKey]['label'] . '" for user ID: ' . $uid; }
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
if (!function_exists('saveSettings')) { function saveSettings($pdo, $data) { $st = $pdo->prepare("INSERT INTO site_settings (setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=?,updated_at=NOW()"); foreach ($data as $k => $val) { $st->execute([$k, $val, $val]); } } }

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
            $ur = handleUpload("news_image");
            if ($ur !== null) { if (isset($ur["error"])) { $addError = $ur["error"]; } else { $addData["image"] = $ur["path"]; } }
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
                    
                    $addSuccess = "Saved successfully." . ($addData["is_breaking"] ? " Breaking News tracker-এও যোগ হয়েছে।" : "");
                    $addData = ["title" => "", "headline" => "", "slug" => "", "content" => "", "image" => "", "tags" => "", "category_id" => "", "subcategories" => [], "author" => $fullName, "status" => "draft", "is_featured" => 0, "is_breaking" => 0, "created_at" => ""];
                    $section = "manage";
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
                        if (!empty($editData["image"]) && file_exists(__DIR__ . '/' . $editData["image"])) @unlink(__DIR__ . '/' . $editData["image"]); 
                        $editData["image"] = $ur["path"]; 
                    } 
                }
                if (empty($editError)) {
                    try {
                        $firstSubId = !empty($editData["subcategories"]) ? (int)$editData["subcategories"][0] : null;
                        
                        if ($currentUserPerms === null) {
                            $editData["created_at"] = trim($_POST["news_created_at"] ?? "");
                            $news_created_at = !empty($editData["created_at"]) ? date('Y-m-d H:i:s', strtotime($editData["created_at"])) : date('Y-m-d H:i:s');
                            $editSuccess = "Updated successfully.";
                        } else {
                            $news_created_at = $er["created_at"];
                            $requested_date = trim($_POST["requested_date"] ?? "");
                            if (!empty($requested_date)) {
                                $req_date_sql = date('Y-m-d H:i:s', strtotime($requested_date));
                                $pdo->prepare("INSERT INTO date_change_requests (news_id, requested_by, requested_date, status) VALUES (?, ?, ?, 'pending')")->execute([$eid, $_SESSION['user_id'], $req_date_sql]);
                                $editSuccess = "News updated. A request to change the Publish Date has been sent to Super Admin.";
                            } else {
                                $editSuccess = "Updated successfully.";
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
            $manageSuccess = "Deleted successfully."; 
        } 
    }
    if (isset($_GET["toggle"]) && isset($_GET["ttoken"])) { if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["ttoken"])) { $pdo->prepare("UPDATE news SET status=IF(status='published','draft','published') WHERE id=?")->execute([(int)$_GET["toggle"]]); $manageSuccess = "Status changed successfully."; } }
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

 $adSuccess = ""; $adError = ""; $adEditData = null; $adFilter = $_GET["ad_filter"] ?? "all"; $adList = []; $adTotal = 0;
if ($section === "ads") {
    if (isset($_GET["ad_delete"]) && isset($_GET["ad_dtoken"])) { if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["ad_dtoken"])) { $di = (int)$_GET["ad_delete"]; $dr = $pdo->prepare("SELECT image FROM advertisements WHERE id=?"); $dr->execute([$di]); $dw = $dr->fetch(); if ($dw && !empty($dw["image"]) && file_exists(__DIR__ . '/' . $dw["image"])) @unlink(__DIR__ . '/' . $dw["image"]); $pdo->prepare("DELETE FROM advertisements WHERE id=?")->execute([$di]); $adSuccess = "Deleted successfully."; } }
    if (isset($_GET["ad_toggle"]) && isset($_GET["ad_ttoken"])) { if (hash_equals($_SESSION["csrf_token"] ?? "", $_GET["ad_ttoken"])) { $pdo->prepare("UPDATE advertisements SET is_active=IF(is_active=1,0,1) WHERE id=?")->execute([(int)$_GET["ad_toggle"]]); $adSuccess = "Status changed successfully."; } }
    if (isset($_GET["ad_edit"])) { $er = $pdo->prepare("SELECT * FROM advertisements WHERE id=?"); $er->execute([(int)$_GET["ad_edit"]]); $er = $er->fetch(); if ($er) { $adEditData = $er; } }
    if ($_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['update_ads']) && !isset($_POST['user_action']) && !isset($_POST['save_festival_logo'])) {
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

 $passSuccess = ""; $passError = "";
if ($section === "password" && $_SERVER["REQUEST_METHOD"] === "POST" && !isset($_POST['user_action'])) {
    if (!verifyCSRF()) { $passError = "Token mismatch."; }
    else { $cp = $_POST["current_password"] ?? ""; $np = $_POST["new_password"] ?? ""; $cfp = $_POST["confirm_password"] ?? ""; if (empty($cp) || empty($np) || empty($cfp)) { $passError = "Please fill all fields."; } elseif ($np !== $cfp) { $passError = "Passwords do not match."; } elseif (strlen($np) < 4) { $passError = "At least 4 characters."; } else { $u = $pdo->prepare("SELECT password FROM users WHERE id=?"); $u->execute([$_SESSION["user_id"]]); $ur = $u->fetch(); if ($ur && $cp === $ur["password"]) { $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$np, $_SESSION["user_id"]]); $passSuccess = "Password changed successfully."; } else { $passError = "Current password is wrong."; } } }
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

if (!function_exists('secLink')) { function secLink($s, $i, $l, $b = "") { global $section; $a = ($section === $s) ? " active" : ""; $bg = $b ? "<span class=\"badge\">$b</span>" : ""; return "<a href=\"?page=admin_dashboard&section=$s\" class=\"$a\"><i class=\"fas $i\"></i> $l$bg</a>"; } }

 $pendingUsersCount = 0;
try { $pendingUsersCount = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE is_approved = 0")->fetchColumn(); } catch (Exception $e) {}

 $pendingDateRequestsCount = 0;
try { $pendingDateRequestsCount = (int)$pdo->query("SELECT COUNT(*) FROM date_change_requests WHERE status='pending'")->fetchColumn(); } catch (Exception $e) {}

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
<div class="sb-header"><div class="sb-logo">A</div><div class="sb-brand">News Portal<small>Admin Panel</small></div></div>
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
<?php if (hasPerm('breaking') || hasPerm('categories') || hasPerm('home_panels') || hasPerm('social') || hasPerm('contact')): ?>
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Configuration</div>
<?php endif; ?>
<?php if (hasPerm('breaking')): ?>
<?php /* Breaking News section removed - now managed via Add News checkbox */ ?>
<?php endif; ?>
<?php if (hasPerm('categories')): ?>
<?= secLink("categories","fa-folder-tree","Categories / Subcategories",$totalCats) ?>
<?php endif; ?>
<?php if (hasPerm('home_panels')): ?>
<?= secLink("home_panels","fa-table-cells","Home Page Panels") ?>
<?php endif; ?>
<?php if (hasPerm('social')): ?>
<?= secLink("social","fa-share-nodes","Social Media") ?>
<?php endif; ?>
<?php if (hasPerm('contact')): ?>
<?= secLink("contact","fa-address-book","Contact Details") ?>
<?php endif; ?>
<?php if (hasPerm('ads')): ?>
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Monetization</div>
<?= secLink("ads","fa-rectangle-ad","Advertisements",$activeAds."/".$totalAds) ?>
<?php endif; ?>

<!-- ★ Visitor Stats Monitoring — sidebar menu item (after Advertisements) -->
<div class="sb-nav-divider"></div>
<div class="sb-nav-label">Analytics</div>
<?= secLink("visitor_stats","fa-chart-line","Visitor Monitor") ?>

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
            <a href="http://localhost/Newspaper/index.php#" target="_blank" class="topbar-btn topbar-btn-outline"><i class="fas fa-globe"></i> Newspaper Portal</a>
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

<div class="stats-grid">
<div class="stat-card sc-red"><div class="stat-icon"><i class="fas fa-newspaper"></i></div><div><div class="stat-num"><?= $totalNews ?></div><div class="stat-label">Total News</div></div></div>
<div class="stat-card sc-green"><div class="stat-icon"><i class="fas fa-circle-check"></i></div><div><div class="stat-num"><?= $publishedNews ?></div><div class="stat-label">Published</div></div></div>
<div class="stat-card sc-gold"><div class="stat-icon"><i class="fas fa-file-pen"></i></div><div><div class="stat-num"><?= $draftNews ?></div><div class="stat-label">Drafts</div></div></div>
<div class="stat-card sc-purple"><div class="stat-icon"><i class="fas fa-star"></i></div><div><div class="stat-num"><?= $featuredNews ?></div><div class="stat-label">Featured</div></div></div>
<div class="stat-card sc-blue"><div class="stat-icon"><i class="fas fa-folder-tree"></i></div><div><div class="stat-num"><?= $totalCats ?></div><div class="stat-label">Categories</div></div></div>
<div class="stat-card sc-red"><div class="stat-icon"><i class="fas fa-bolt"></i></div><div><div class="stat-num"><?= $totalBreaking ?></div><div class="stat-label">Breaking</div></div></div>
<div class="stat-card sc-gold"><div class="stat-icon"><i class="fas fa-rectangle-ad"></i></div><div><div class="stat-num"><?= $activeAds ?></div><div class="stat-label">Active Ads</div></div></div>
<div class="stat-card sc-green"><div class="stat-icon"><i class="fas fa-mouse-pointer"></i></div><div><div class="stat-num"><?= number_format($totalClicks) ?></div><div class="stat-label">Total Clicks</div></div></div>
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
</div></div></div></div>

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

<!-- Facebook/Twitter Video Link Embed Input (Outside CKEditor) -->
<div class="form-group" style="background: var(--bg); padding: 14px; border-radius: 10px; border: 2px dashed var(--border);">
    <label class="form-label" style="color: var(--accent);"><i class="fas fa-people-arrows"></i> Insert Facebook / Twitter Video</label>
    <div style="display:flex; gap:10px; align-items:center;">
        <input type="text" id="socialVideoUrlInput" class="form-input" placeholder="ভিডিও লিংক দিন (যেমন: https://facebook.com/... বা https://twitter.com/...)" style="flex:1;">
        <button type="button" class="btn btn-primary" onclick="insertSocialVideoToEditor()">
            <i class="fas fa-arrow-down-to-bracket"></i> Embed Video
        </button>
    </div>
    <div class="form-hint" style="margin-top:6px;">টেক্সটের যেকোনো জায়গায় কার্সর রেখে এই বাটনে ক্লিক করুন। (শুধু লিংক দিন, কোড দেওয়ার দরকার নেই)</div>
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
        extraPlugins: 'justify,colorbutton,font,iframe,emoji,uploadimage,copyformatting,codesnippet,mathjax,magicline,autogrow,tableresize,tableselection,tabletools,sourcedialog,colordialog,a11yhelp,liststyle,placeholder,templates,preview,print,pastefromword',
        extraAllowedContent: 'iframe[*]; div[*]; p[*]; span[*]; img[*]; video[*]; source[*]; blockquote[*]; pre[*]; code[*]; figure[*]; figcaption[*]; table[*]; tr[*]; td[*]; th[*]; thead[*]; tbody[*]; span(!math-tex,cke_mathjax); p(oembed-content); iframe[oembed]',
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
            { name: 'insert', items: ['Image','Flash','Table','HorizontalRule','Smiley','EmojiPanel','SpecialChar','PageBreak','Iframe','CodeSnippet','Mathjax','Placeholder'] },
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

// Function to insert Facebook/Twitter video into CKEditor at cursor position
function insertSocialVideoToEditor() {
    var url = document.getElementById('socialVideoUrlInput').value.trim();
    if (!url) { alert('Please enter a Facebook or Twitter video URL.'); return; }
    
    var html = '';
    var isFB = url.indexOf('facebook.com') !== -1 || url.indexOf('fb.watch') !== -1;
    var isTwitter = url.indexOf('twitter.com') !== -1 || url.indexOf('x.com') !== -1;
    
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
    } else {
        alert('Please provide a valid Facebook or Twitter video link.');
        return;
    }
    
    if (typeof CKEDITOR !== 'undefined' && CKEDITOR.instances.editor1) {
        CKEDITOR.instances.editor1.insertHtml(html);
        document.getElementById('socialVideoUrlInput').value = '';
    } else { alert('Editor is not loaded yet!'); }
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

<?php elseif ($section === "ads" && hasPerm('ads')): ?>
<div class="admin-topbar"><h1><i class="fas fa-rectangle-ad" style="color:var(--gold)"></i> Advertisements</h1><div class="topbar-actions"><?php if ($adEditData): ?><a href="?page=admin_dashboard&section=ads" class="topbar-btn topbar-btn-outline"><i class="fas fa-times"></i> Cancel</a><?php endif; ?></div></div>
<?php if ($adError): ?><div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?= htmlspecialchars($adError) ?></div><?php endif; ?>
<?php if ($adSuccess): ?><div class="alert alert-success"><i class="fas fa-check-circle"></i> <?= htmlspecialchars($adSuccess) ?></div><?php endif; ?>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-code"></i> Advertisement Embed Codes</div></div><form method="POST"><input type="hidden" name="update_ads" value="1"><div class="panel-body"><div class="form-group"><label class="form-label">Bottom Ad Embed 1 (HTML/Scripts allowed)</label><textarea name="ad_bottom_1_embed" class="form-input" rows="5" placeholder='<iframe src="..."></iframe>'><?= htmlspecialchars($currentAds['ad_bottom_1_embed'] ?? '') ?></textarea></div><div class="form-group"><label class="form-label">Bottom Ad Embed 2 (HTML/Scripts allowed)</label><textarea name="ad_bottom_2_embed" class="form-input" rows="5" placeholder='<iframe src="..."></iframe>'><?= htmlspecialchars($currentAds['ad_bottom_2_embed'] ?? '') ?></textarea></div><button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Save Embed Codes</button></div></form></div>
<div class="panel"><div class="panel-header"><div class="panel-title"><i class="fas fa-<?= $adEditData ? 'pen' : 'plus-circle' ?>"></i> <?= $adEditData ? 'Edit' : 'New Advertisement' ?></div></div><form method="POST" enctype="multipart/form-data"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>"><?php if ($adEditData): ?><input type="hidden" name="ad_id" value="<?= $adEditData["id"] ?>"><?php endif; ?><div class="panel-body"><div class="form-row"><div class="form-group"><label class="form-label">Name *</label><input type="text" name="ad_title" class="form-input" value="<?= htmlspecialchars($adEditData["title"] ?? "") ?>" required></div><div class="form-group"><label class="form-label">Position</label><select name="ad_position" class="form-select"><?php foreach ($adPositions as $pk => $pc): ?><option value="<?= $pk ?>" <?= ($adEditData["position"] ?? "") === $pk ? 'selected' : '' ?>><?= $pc["label"] ?></option><?php endforeach; ?></select></div></div><div class="form-row"><div class="form-group"><label class="form-label">Type</label><div class="radio-group"><div class="radio-item <?= ($adEditData["ad_type"] ?? "image") === "image" ? 'selected' : '' ?>"><input type="radio" name="ad_type" value="image" <?= ($adEditData["ad_type"] ?? "image") === "image" ? 'checked' : '' ?> onchange="document.getElementById('adImgSec').style.display='block';document.getElementById('adCodeSec').style.display='none';this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Image</label></div><div class="radio-item <?= ($adEditData["ad_type"] ?? "") === "code" ? 'selected' : '' ?>"><input type="radio" name="ad_type" value="code" <?= ($adEditData["ad_type"] ?? "") === "code" ? 'checked' : '' ?> onchange="document.getElementById('adImgSec').style.display='none';document.getElementById('adCodeSec').style.display='block';this.closest('.radio-group').querySelectorAll('.radio-item').forEach(r=>r.classList.remove('selected'));this.closest('.radio-item').classList.add('selected')"><label>Code</label></div></div></div><div class="form-group"><label class="form-label">Sort</label><input type="number" name="ad_sort_order" class="form-input" value="<?= $adEditData["sort_order"] ?? 0 ?>" min="0"></div></div><div id="adImgSec" style="display:<?= ($adEditData["ad_type"] ?? "image") === "image" ? 'block' : 'none' ?>"><div class="form-group"><label class="form-label">Link URL</label><input type="url" name="ad_link_url" class="form-input" value="<?= htmlspecialchars($adEditData["link_url"] ?? "") ?>"></div><div class="form-group"><label class="form-label">Image</label><div class="img-upload-area <?= !empty($adEditData["image"]) ? 'has-image' : '' ?>" id="adImgArea"><input type="file" name="ad_image" id="ad_image" accept="image/jpeg,image/png/image/gif/image/webp"><?php if (!empty($adEditData["image"])): ?><img src="<?= htmlspecialchars($adEditData["image"]) ?>" class="img-preview" id="adImgPreview" alt=""><div style="margin-top:6px;font-size:11px;color:var(--green);font-weight:600"><i class="fas fa-check-circle"></i> Image exists</div><?php else: ?><div class="img-upload-icon"><i class="fas fa-cloud-arrow-up"></i></div><div class="img-upload-text">Select image</div><div class="img-upload-hint">JPG, PNG, GIF, WebP — 5MB</div><img src="" class="img-preview" id="adImgPreview" style="display:none" alt=""><?php endif; ?></div></div></div><div id="adCodeSec" style="display:<?= ($adEditData["ad_type"] ?? "") === "code" ? 'block' : 'none' ?>"><div class="form-group"><label class="form-label">HTML Code</label><textarea name="ad_code" class="form-input" rows="8"><?= htmlspecialchars($adEditData["ad_code"] ?? "") ?></textarea></div></div><div class="form-row"><div class="form-group"><label class="form-label">Start Date</label><input type="date" name="ad_start_date" class="form-input" value="<?= $adEditData["start_date"] ?? "" ?>"></div><div class="form-group"><label class="form-label">End Date</label><input type="date" name="ad_end_date" class="form-input" value="<?= $adEditData["end_date"] ?? "" ?>"></div></div><div class="form-group" style="max-width:200px"><label class="form-label">Max Impressions</label><input type="number" name="ad_max_impressions" class="form-input" value="<?= $adEditData["max_impressions"] ?? "" ?>" min="0" placeholder="Unlimited"></div></div><div class="form-actions-bar"><div></div><button type="submit" class="btn btn-gold"><i class="fas fa-save"></i> <?= $adEditData ? 'Update' : 'Add' ?></button></div></form></div>
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
                    $ur = handleUpload('festival_logo_file');
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
    if ($usr['id'] == $_SESSION['user_id']) continue;
    $uRole = !empty($usr['role']) ? $usr['role'] : getUserRole($usr['permissions'] ?? null);
    if ($uRole === 'super_admin') continue;
    if (!$firstUserId) $firstUserId = $usr['id'];
    $uPermsRaw = $usr['permissions'] ?? null;
    $uIsSuper = ($uPermsRaw === null);
    $uPerms = $uIsSuper ? array_keys($allPermissions) : (json_decode($uPermsRaw, true) ?: []);
    $usersJsData[$usr['id']] = ['role' => $uRole, 'permissions' => $uPerms];
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
                    if ($usr['id'] == $_SESSION['user_id']) continue;
                    $uRole = !empty($usr['role']) ? $usr['role'] : getUserRole($usr['permissions'] ?? null);
                    if ($uRole === 'super_admin') continue;
                ?>
                <option value="<?= $usr['id'] ?>"><?= htmlspecialchars($usr['username']) ?> (<?= htmlspecialchars($usr['full_name'] ?? 'N/A') ?>)</option>
                <?php endforeach; ?>
            </select>
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
        <p><i class="fas fa-crown" style="color:var(--purple)"></i> <strong>Super Admin</strong> — Full access. Only Super Admin can manage permissions.</p>
        <p><i class="fas fa-id-badge" style="color:var(--blue)"></i> <strong>Quick Role Assign</strong> — Select user → Select role → Auto-saves.</p>
        <p><i class="fas fa-shield-halved" style="color:var(--purple)"></i> <strong>Manual Permissions</strong> — Select user → Check/uncheck → Save. Role stays unchanged.</p>
        <p><i class="fas fa-user-pen" style="color:var(--blue)"></i> <strong>Editor</strong> — Dashboard + Add/Manage News only.</p>
        <p><i class="fas fa-newspaper" style="color:var(--green)"></i> <strong>Publisher</strong> — News + Categories + Breaking + Home Panels.</p>
        <p><i class="fas fa-rectangle-ad" style="color:var(--gold)"></i> <strong>Ad Manager</strong> — Dashboard + Advertisements only.</p>
        <p><i class="fas fa-share-nodes" style="color:#25D366"></i> <strong>Social Manager</strong> — Social + Contact + Videos.</p>
        <p><i class="fas fa-chart-line" style="color:#FF9933"></i> <strong>Market Viewer</strong> — Dashboard + NSE Market only.</p>
        <p><i class="fas fa-eye" style="color:var(--muted)"></i> <strong>Viewer</strong> — Dashboard only (read-only).</p>
    </div>
</div>

<script>
var usersData = <?= json_encode($usersJsData) ?>;
function loadUserPerms(userId) {
    var uid = parseInt(userId);
    if (!uid || !usersData[uid]) {
        document.getElementById('roleSelect').value = '';
        document.querySelectorAll('input[name="permissions[]"]').forEach(function(cb) { cb.checked = false; });
        return;
    }
    var data = usersData[uid];
    document.getElementById('roleSelect').value = data.role;
    document.querySelectorAll('input[name="permissions[]"]').forEach(function(cb) { cb.checked = data.permissions.includes(cb.value); });
    document.getElementById('roleTargetUser').value = uid;
    document.getElementById('permTargetUser').value = uid;
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
<div class="panel"><div class="panel-body"><form method="POST" class="password-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION["csrf_token"] ?? "") ?>">
<div class="form-group"><label class="form-label">Current Password *</label><input type="text" name="current_password" class="form-input" required></div>
<div class="form-group"><label class="form-label">New Password *</label><input type="text" name="new_password" class="form-input" id="newPass" required oninput="cps(this.value)"><div class="pass-strength"><div class="pass-strength-bar" id="psB" style="width:0;background:var(--border)"></div></div><div class="form-hint" id="psT">At least 4 characters</div></div>
<div class="form-group"><label class="form-label">Confirm *</label><input type="text" name="confirm_password" class="form-input" required></div>
<button type="submit" class="btn btn-primary" style="margin-top:8px"><i class="fas fa-save"></i> Change</button>
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

<?php elseif ($section === "visitor_stats"): ?>
<!-- ★ Visitor Stats Monitoring Section — full page view -->
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