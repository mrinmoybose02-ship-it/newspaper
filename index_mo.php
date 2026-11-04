<?php
// =====================================================================
//  index_mo.php — Sangbad Sankalan Android App (v3.25)
//  ★ SELF-CONTAINED — মূল index.php সম্পূর্ণ অপরিবর্তিত
//  ★ BILINGUAL — Bengali ও English দুটোতেই কাজ করে
// =====================================================================

// ★ v3.26: Increase memory & time limits for InfinityFree compatibility
@ini_set('memory_limit', '256M');
@ini_set('max_execution_time', '30');
@ini_set('display_errors', '0');
error_reporting(E_ERROR | E_PARSE);

// ★ v3.46: AJAX endpoint — today's posted news for bell icon popup.
//    Must be BEFORE include index.php to avoid full page render overhead.
//    Returns JSON with today's published news (with thumbnails + time-ago).
if (isset($_GET['ajax_today_news'])) {
    @ini_set('display_errors', '0');
    @error_reporting(0);
    while (ob_get_level() > 0) { @ob_end_clean(); }
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store, max-age=0');

    require_once __DIR__ . '/config.php';
    date_default_timezone_set('Asia/Kolkata');
    try { if (isset($pdo)) { $pdo->exec("SET time_zone = '+05:30';"); } } catch (Exception $e) {}

    // ★ v3.63: Detect language for AJAX response (same logic as index.php)
    $ajaxIsEn = false;
    if (isset($_GET['lang']) && $_GET['lang'] === 'en') $ajaxIsEn = true;
    elseif (isset($_COOKIE['site_lang']) && $_COOKIE['site_lang'] === 'en') $ajaxIsEn = true;

    if (!isset($pdo)) {
        echo json_encode(['status' => 'error', 'count' => 0, 'items' => []]);
        exit;
    }

    $countOnly = isset($_GET['count_only']);

    try {
        if ($countOnly) {
            $cnt = $pdo->query("SELECT COUNT(*) FROM news WHERE status='published' AND created_at <= NOW() AND DATE(created_at) = CURDATE()")->fetchColumn();
            echo json_encode(['status' => 'ok', 'count' => (int)$cnt]);
            exit;
        }

        $stmt = $pdo->prepare("SELECT n.id, n.title, n.slug, n.image, n.created_at, c.name as category_name
                               FROM news n
                               LEFT JOIN categories c ON n.category_id = c.id
                               WHERE n.status = 'published'
                                 AND n.created_at <= NOW()
                                 AND DATE(n.created_at) = CURDATE()
                               ORDER BY n.created_at DESC
                               LIMIT 50");
        $stmt->execute();
        $todayNews = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $placeholderImg = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";
        $bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        $enDigits = ['0','1','2','3','4','5','6','7','8','9'];

        foreach ($todayNews as &$n) {
            // ★ v3.62: Fixed image URL — use newsImage() logic from index.php
            $imgUrl = $placeholderImg;
            if (!empty($n['image'])) {
                $img = trim($n['image']);
                if ($img !== '' && $img !== '0') {
                    if (preg_match('#^https?://#i', $img) || strpos($img, '//') === 0) {
                        $imgUrl = $img;
                    } else {
                        while (strpos($img, 'uploads/uploads/') === 0) { $img = substr($img, 8); }
                        if (strpos($img, 'uploads/') !== 0 && strpos($img, '/') !== 0) {
                            $imgUrl = 'uploads/' . $img;
                        } else {
                            $imgUrl = $img;
                        }
                    }
                }
            }
            $n['image_url'] = $imgUrl;

            // ★ v3.63: Language-aware time labels
            $ts = strtotime($n['created_at']);
            $n['time_ago'] = '';
            if ($ts) {
                $diff = time() - $ts;
                if ($diff < 0) {
                    $n['time_ago'] = date('h:i A', $ts);
                } elseif ($diff < 60) {
                    $n['time_ago'] = $ajaxIsEn ? 'Just now' : 'এইমাত্র';
                } elseif ($diff < 3600) {
                    $m = (int)($diff / 60);
                    $n['time_ago'] = $ajaxIsEn ? $m . ' min ago' : $m . ' মিনিট আগে';
                } elseif ($diff < 86400) {
                    $n['time_ago'] = date('h:i A', $ts);
                } else {
                    $n['time_ago'] = date('d M Y, h:i A', $ts);
                }
            }
        }

        echo json_encode([
            'status' => 'ok',
            'count' => count($todayNews),
            'items' => $todayNews
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'count' => 0, 'items' => []]);
    }
    exit;
}

// ── Step 1: Capture index.php output ──────────────────────────────────
// ★ v3.74: CRITICAL FIX — make index.php render ALL news for the category/search
//    so index_mo.php can paginate them 4-per-page with its own 'mop' param.
//
//    Problem: index.php uses $_GET['p'] for ITS pagination (NEWS_PER_PAGE items).
//    If a category has 20 news but NEWS_PER_PAGE=9, index.php only renders 9 cards
//    on page 1 — so index_mo.php can never show more than 9 cards total.
//
//    Solution: Force index.php to render ALL news in one go by:
//    1) Removing our 'mop' param from $_GET (we use 'mop', not 'p', for our pagination)
//    2) Defining NEWS_PER_PAGE as a huge number BEFORE include (overrides config.php)
//
//    We use 'mop' (mo page) instead of 'p' so index.php's own pagination
//    is bypassed entirely (it sees no 'p' param → defaults to page 1, but
//    NEWS_PER_PAGE is huge so all news fit on "page 1").

// Save our own pagination param (mop), then strip it from $_GET so index.php
// doesn't see it. (index.php only cares about 'p'.)
$moPageNum = isset($_GET['mop']) ? max(1, (int)$_GET['mop']) : 1;
unset($_GET['mop']);

// Also strip 'p' from $_GET so index.php renders page 1 with our huge NEWS_PER_PAGE
// (otherwise index.php would try to render its own page 2, 3, etc. — which don't
// exist when NEWS_PER_PAGE is huge).
unset($_GET['p']);

// Define NEWS_PER_PAGE as a huge number BEFORE index.php / config.php loads it.
// This makes index.php render all news in one page (no internal pagination).
// ★ If NEWS_PER_PAGE is already defined (e.g., by config.php loaded earlier),
//   we can't redefine it — so we define it only if not yet defined.
if (!defined('NEWS_PER_PAGE')) {
    define('NEWS_PER_PAGE', 1000);
}

ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

// ★ v3.45: Save index.php's database-fetched variables for REAL category counts.
//    index.php already fetches $catPostCounts, $catSubcategories, $subPostCounts,
//    $navCatIcons, $categories (Bengali names) from the DB. We save them here
//    because index_mo.php's HTML extraction later overwrites $categories.
$dbCategories     = $categories        ?? [];
$dbCatPostCounts  = $catPostCounts     ?? [];
$dbCatSubcats     = $catSubcategories  ?? [];
$dbSubPostCounts  = $subPostCounts     ?? [];
$dbNavCatIcons    = $navCatIcons       ?? [];
$dbCurrentSub     = $currentSub        ?? 0;

// ★ v3.47: Visitor tracking — count online users + total visitors for footer.
//    Creates visitor_stats table, tracks each visitor by SHA256(IP).
//    ★ v3.52: Now also stores OS, browser, partial IP for admin analytics panel.
$ucText = '';
try {
    if (isset($pdo)) {
        // Create table if not exists (safe — runs once)
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visitor_hash VARCHAR(64) NOT NULL UNIQUE,
            ip_partial VARCHAR(20) DEFAULT '',
            user_agent VARCHAR(500) DEFAULT '',
            os VARCHAR(50) DEFAULT '',
            browser VARCHAR(50) DEFAULT '',
            visit_count INT DEFAULT 1,
            first_visit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // ★ v3.52: Add new columns to existing table (safe — ignores error if exists)
        $newCols = ['ip_partial', 'user_agent', 'os', 'browser', 'visit_count'];
        foreach ($newCols as $col) {
            try {
                $colDefs = [
                    'ip_partial'  => "VARCHAR(20) DEFAULT ''",
                    'user_agent'  => "VARCHAR(500) DEFAULT ''",
                    'os'          => "VARCHAR(50) DEFAULT ''",
                    'browser'     => "VARCHAR(50) DEFAULT ''",
                    'visit_count' => "INT DEFAULT 1",
                ];
                $pdo->exec("ALTER TABLE visitor_stats ADD COLUMN {$col} {$colDefs[$col]}");
            } catch (Exception $e) {}
        }

        // Track current visitor by IP only (1 IP = 1 visitor, regardless of browser/device)
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        // Detect real client IP if behind proxy/CDN (InfinityFree, Cloudflare, nginx, etc.)
        if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $clientIP = trim($ips[0]);
        } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
            $clientIP = $_SERVER['HTTP_X_REAL_IP'];
        } elseif (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $clientIP = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        // ★ v3.52+v3.59: Enhanced OS + browser detection for mobile apps/WebViews
        //    Detects Android (including WebView), iOS (including WKWebView),
        //    Windows, macOS, Linux, plus mobile browsers (Samsung, Mi, Opera, UC).
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $os = 'Other';
        if (preg_match('/windows/i', $ua)) $os = 'Windows';
        elseif (preg_match('/android/i', $ua)) $os = 'Android';
        elseif (preg_match('/iphone|ipad|ipod/i', $ua)) $os = 'iOS';
        elseif (preg_match('/mac os x|macintosh|darwin/i', $ua)) $os = 'macOS';
        elseif (preg_match('/linux/i', $ua)) $os = 'Linux';
        // ★ v3.59: Additional mobile detection (CFNetwork = iOS native app)
        elseif (preg_match('/cfnetwork/i', $ua)) $os = 'iOS';
        // If "Mobile" or "Mobile Safari" but no OS match, guess Android (most common)
        elseif (preg_match('/mobile/i', $ua)) $os = 'Android';

        $browser = 'Other';
        if (preg_match('/edg/i', $ua)) $browser = 'Edge';
        elseif (preg_match('/samsungbrowser/i', $ua)) $browser = 'Samsung';
        elseif (preg_match('/ucbrowser/i', $ua)) $browser = 'UC Browser';
        elseif (preg_match('/opr\/|opera/i', $ua)) $browser = 'Opera';
        elseif (preg_match('/miuibrowser/i', $ua)) $browser = 'Mi Browser';
        elseif (preg_match('/crios/i', $ua)) $browser = 'Chrome'; // Chrome on iOS
        elseif (preg_match('/fxios/i', $ua)) $browser = 'Firefox'; // Firefox on iOS
        elseif (preg_match('/chrome/i', $ua) && !preg_match('/edg/i', $ua)) $browser = 'Chrome';
        elseif (preg_match('/firefox/i', $ua)) $browser = 'Firefox';
        elseif (preg_match('/safari/i', $ua) && !preg_match('/chrome/i', $ua)) $browser = 'Safari';
        // ★ v3.59: WebView detection
        elseif (preg_match('/wv\)/i', $ua)) $browser = 'WebView'; // Android WebView
        elseif (preg_match('/webview/i', $ua)) $browser = 'WebView';

        // Partial IP for privacy (first 3 octets for IPv4)
        $ipPartial = $clientIP;
        if (filter_var($clientIP, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $parts = explode('.', $clientIP);
            if (count($parts) === 4) $ipPartial = $parts[0] . '.' . $parts[1] . '.' . $parts[2] . '.*';
        }

        $visitorHash = hash('sha256', $clientIP);
        // ★ v3.52: Insert with OS/browser/IP info; increment visit_count on return
        $trackStmt = $pdo->prepare("INSERT INTO visitor_stats (visitor_hash, ip_partial, user_agent, os, browser)
                                     VALUES (?, ?, ?, ?, ?)
                                     ON DUPLICATE KEY UPDATE
                                       last_activity = NOW(),
                                       visit_count = visit_count + 1,
                                       ip_partial = VALUES(ip_partial),
                                       os = VALUES(os),
                                       browser = VALUES(browser)");
        $trackStmt->execute([$visitorHash, $ipPartial, mb_substr($ua, 0, 500), $os, $browser]);

        // ★ v3.53: Daily visit log — records each day a visitor visits (for graph)
        $pdo->exec("CREATE TABLE IF NOT EXISTS visit_log (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visitor_hash VARCHAR(64) NOT NULL,
            visit_date DATE NOT NULL,
            visit_count INT DEFAULT 1,
            UNIQUE KEY uk_hash_date (visitor_hash, visit_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        try {
            $vlStmt = $pdo->prepare("INSERT INTO visit_log (visitor_hash, visit_date) VALUES (?, CURDATE())
                                     ON DUPLICATE KEY UPDATE visit_count = visit_count + 1");
            $vlStmt->execute([$visitorHash]);
        } catch (Exception $e) {}

        // Get counts
        // ★ v3.50: Online count removed from display — only total visits shown.
        $totalRaw  = (int)$pdo->query("SELECT COUNT(*) FROM visitor_stats")->fetchColumn();

        // Convert to Bengali digits (with thousand separators)
        $bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        $enDigits = ['0','1','2','3','4','5','6','7','8','9'];
        $ucTotal  = str_replace($enDigits, $bnDigits, number_format($totalRaw));

        // ★ v3.61: Retro counter — tiny black-box digits (English), left of copyright
        $ucTotalEN = number_format($totalRaw);
        $counterHTML = '';
        $chars = str_split($ucTotalEN);
        foreach ($chars as $char) {
            if ($char === ',') {
                $counterHTML .= '<span style="display:inline-flex;align-items:center;justify-content:center;width:3px;height:12px;color:rgba(255,255,255,.5);font-size:8px;font-weight:700;font-family:monospace;">,</span>';
            } else {
                $counterHTML .= '<span style="display:inline-flex;align-items:center;justify-content:center;width:10px;height:12px;background:#000;color:#fff;font-size:8px;font-weight:700;font-family:monospace;border-radius:1px;border:1px solid rgba(255,255,255,.2);line-height:1;margin:0 0.5px;">' . $char . '</span>';
            }
        }
        $ucFooterHTML = '<span style="display:inline-flex;align-items:center;font-size:7px;color:rgba(255,255,255,.6);letter-spacing:.5px;text-transform:uppercase;font-weight:600;margin-right:2px;">'
            . '<span style="width:5px;height:5px;background:#69F0AE;border-radius:50%;display:inline-block;flex-shrink:0;box-shadow:0 0 4px rgba(105,240,174,.7);animation:livePulse 1.5s ease-in-out infinite;margin-right:3px;"></span>'
            . '<span style="margin-right:3px;">Visitor</span>'
            . $counterHTML
            . '</span>';
    }
} catch (Exception $e) {}

// AJAX/exit endpoints — output already flushed, nothing to do
if ($html === '' || $html === null || $html === false) {
    return;
}
// Not HTML — pass through unchanged
if (strpos($html, '<body') === false) {
    echo $html;
    return;
}

// ── Step 2: Detect language — $_GET['lang'] takes PRIORITY over cookie ──
// ★ v2.4 FIX: আগে cookie stale থাকার কারণে ভাষা সঠিক detect হতো না।
//    যখন user ইংরেজি থেকে বাংলায় switch করত, URL = ?lang=bn, কিন্তু
//    cookie তখনও 'en' ছিল, ফলে $isEnglish=true হতো (ভুল)।
//    এখন $_GET['lang'] explicitly চেক করি — যদি set থাকে সেটাই বসবে।
if (isset($_GET['lang']) && in_array($_GET['lang'], ['bn', 'en'], true)) {
    $isEnglish = ($_GET['lang'] === 'en');
} elseif (isset($_COOKIE['site_lang'])) {
    $isEnglish = ($_COOKIE['site_lang'] === 'en');
} else {
    $isEnglish = false;
}

$L = $isEnglish ? [
    'appName'    => 'সংবাদ সংকলন',  // ★ v2.5: brand name always Bengali (never translated)
    'home'       => 'Home',
    'breaking'   => 'BREAKING',
    'allCats'    => 'All Categories',
    'latest'     => 'Latest News',
    'popular'    => 'Popular News',
    'tags'       => 'Popular Tags',
    'search'     => 'Search news...',
    'noResult'   => 'No news found',
    'backHome'   => 'Back to Home',
    'hide'       => 'Hide',
    'show'       => 'Show',
    'listen'     => 'Listen',
    'by'         => 'by',
] : [
    'appName'    => 'সংবাদ সংকলন',
    'home'       => 'হোম',
    'breaking'   => 'ব্রেকিং',
    'allCats'    => 'সব ক্যাটাগরি',
    'latest'     => 'সর্বশেষ সংবাদ',
    'popular'    => 'জনপ্রিয় সংবাদ',
    'tags'       => 'জনপ্রিয় ট্যাগ',
    'search'     => 'খবর খুঁজুন...',
    'noResult'   => 'কোনো সংবাদ পাওয়া যায়নি',
    'backHome'   => 'হোমে ফিরুন',
    'hide'       => 'লুকাও',
    'show'       => 'দেখাও',
    'listen'     => 'শুনুন',
    'by'         => '—',
];

// ── Step 2.5: Define page context variables (BEFORE extraction) ──────
// ★ v2.7.1 FIX: These variables must be defined before article extraction
//    and rendering, otherwise PHP shows "Undefined variable" warnings.
$page        = $_GET['page'] ?? 'home';
$currentCat  = $_GET['cat'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');
$isSinglePage = ($page === 'single');
$langAttr     = $isEnglish ? 'en' : 'bn';

// ★ Language switch button — toggles Bengali ↔ English
$langSwitch      = $isEnglish ? 'bn' : 'en';
$langSwitchLabel = $isEnglish ? 'বাং' : 'EN';

// Build full URL for language switch — preserves current page/cat/search params
$langSwitchUrl = '?lang=' . $langSwitch;
if ($page !== 'home' && !empty($page)) $langSwitchUrl .= '&page=' . urlencode($page);
if (!empty($currentCat))               $langSwitchUrl .= '&cat=' . urlencode($currentCat);
if (!empty($searchQuery))              $langSwitchUrl .= '&q=' . urlencode($searchQuery);
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $langSwitchUrl .= '&id=' . urlencode($_GET['id']);
}
if (isset($_GET['sub']) && !empty($_GET['sub'])) {
    $langSwitchUrl .= '&sub=' . urlencode($_GET['sub']);
}
if (isset($_GET['y']) && !empty($_GET['y'])) {
    $langSwitchUrl .= '&y=' . urlencode($_GET['y']);
}
if (isset($_GET['m']) && !empty($_GET['m'])) {
    $langSwitchUrl .= '&m=' . urlencode($_GET['m']);
}
// ★ v3.74: Preserve our 'mop' pagination page number when switching language
//    (was 'p' in v3.68, but 'p' belongs to index.php — we now use 'mop')
if (isset($_GET['mop']) && (int)$_GET['mop'] > 1) {
    $langSwitchUrl .= '&mop=' . (int)$_GET['mop'];
}
$langSwitchHref = $langSwitchUrl;

// ★ Section title (for home/category/search pages)
// ★ v3.86: If a subcategory is selected (?sub=ID), look up its name from
//    $dbCatSubcats (saved from index.php) and use it as the section title.
//    So clicking "কলকাতা" in the article page → category page title shows "কলকাতা".
$_subName = '';
if (!empty($_GET['sub']) && !empty($dbCatSubcats)) {
    $_subId = (int)$_GET['sub'];
    foreach ($dbCatSubcats as $_catSubs) {
        if (is_array($_catSubs)) {
            foreach ($_catSubs as $_s) {
                if (isset($_s['id']) && (int)$_s['id'] === $_subId) {
                    $_subName = $_s['name'] ?? '';
                    break 2;
                }
            }
        }
    }
}
if ($searchQuery) {
    $sectionTitle = $isEnglish ? 'Search: ' . $searchQuery : 'সার্চ: ' . $searchQuery;
} elseif ($currentCat && $_subName) {
    // Subcategory page — show subcategory name (e.g., "কলকাতা")
    $sectionTitle = $_subName;
} elseif ($currentCat) {
    $sectionTitle = $currentCat;
} elseif ($page === 'home') {
    $sectionTitle = $isEnglish ? 'Latest News' : 'সর্বশেষ সংবাদ';
} else {
    $sectionTitle = $isEnglish ? 'Latest News' : 'সর্বশেষ সংবাদ';
}

// ── Step 3: Extract data from index.php's HTML ────────────────────────
// Featured (first .feat-main)
$featured = ['href' => '', 'img' => '', 'title' => '', 'cat' => ''];
if (preg_match('#<a href="([^"]+)"\s+class="feat-main"[^>]*>\s*<img src="([^"]+)"#u', $html, $m)) {
    $featured['href'] = $m[1];
    $featured['img']  = $m[2];
    if (preg_match('#<span class="cb"[^>]*>([^<]+)</span>#u', $html, $cm)) $featured['cat'] = trim($cm[1]);
    if (preg_match('#<h2[^>]*>([^<]+)</h2>#u', $html, $tm)) $featured['title'] = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
}

// Side featured items (.feat-si)
$sideItems = [];
if (preg_match_all('#<a href="([^"]+)"\s+class="feat-si"[^>]*>\s*<img src="([^"]+)"[^>]*>\s*<div class="pi">\s*<div class="fcs"[^>]*>([^<]+)</div>\s*<h3[^>]*>([^<]+)</h3>#u', $html, $fm)) {
    foreach ($fm[1] as $i => $href) {
        $sideItems[] = ['href' => $href, 'img' => $fm[2][$i], 'cat' => trim($fm[3][$i]), 'title' => html_entity_decode(trim($fm[4][$i]), ENT_QUOTES, 'UTF-8')];
    }
}

// News grid cards (.nc)
$newsCards = [];
// ★ v3.73: Initialize dedup map here so the fallback (and other extractors) can use it safely.
$existingHrefs = [];
if (preg_match_all('#<a href="([^"]+)"\s+class="nc"[^>]*>(.*?)</a>#us', $html, $matches)) {
    foreach ($matches[1] as $idx => $href) {
        $block = $matches[2][$idx];
        $img = $title = $cat = $time = '';
        if (preg_match('#<img[^>]+src="([^"]+)"#u', $block, $im)) $img = $im[1];
        if (preg_match('#<h3[^>]*>([^<]+)</h3>#u', $block, $tm)) $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
        if (preg_match('#<div class="nc-cat"[^>]*>([^<]+)</div>#u', $block, $cm)) $cat = trim($cm[1]);
        if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $block, $tm2)) $time = trim($tm2[1]);
        if ($title && $img) {
            $newsCards[] = ['href' => $href, 'img' => $img, 'title' => $title, 'cat' => $cat, 'time' => $time];
            $existingHrefs[$href] = true;
        }
    }
}

// ★ v3.15: Also extract home panel cards (.cat-panel-card) and merge into newsCards.
// These are category-specific news cards that appear below the main grid on home page.
// User wants them in "সর্বশেষ সংবাদ" (Latest News) section instead of separate panels.
//
// ★ v3.71: REVERTED v3.70 — cat-panel-card merge must happen on ALL pages (home,
//    category, search). On category pages index.php often outputs the category's
//    news as .cat-panel-card (not .nc), so skipping the merge made the category
//    page show no news at all. Order is preserved by index.php's natural output
//    order (.nc first, then .cat-panel-card) and dedup ensures no duplicates.
if (preg_match_all('#<a href="([^"]+)"\s+class="cat-panel-card"[^>]*>(.*?)</a>#us', $html, $cpMatches)) {
    // ★ v3.73: $existingHrefs is now initialized earlier (in .nc block) and persists.
    //    We no longer rebuild it here — just add new entries as we go.
    foreach ($cpMatches[1] as $idx => $href) {
        // Skip duplicates using hash lookup
        if (isset($existingHrefs[$href])) continue;
        $block = $cpMatches[2][$idx];
        $img = $title = $cat = $time = '';
        if (preg_match('#<img[^>]+src="([^"]+)"#u', $block, $im)) $img = $im[1];
        if (preg_match('#<h3[^>]*>([^<]+)</h3>#u', $block, $tm)) $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
        if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $block, $tm2)) {
            $timeRaw = trim($tm2[1]);
            $time = $timeRaw;
            if (preg_match('#<span[^>]*>([^<]+)</span>#u', $block, $cpm)) {
                $cat = trim($cpm[1]);
                $time = trim(str_replace($cat, '', $timeRaw));
            }
        }
        if ($title && $img) {
            $newsCards[] = ['href' => $href, 'img' => $img, 'title' => $title, 'cat' => $cat, 'time' => $time];
            $existingHrefs[$href] = true;
        }
    }
}

// ★ v3.72: FALLBACK — if both .nc and .cat-panel-card extraction returned nothing
//    (which happens on some category pages where index.php uses a different template),
//    try a generic approach: find ALL <a> tags that contain BOTH an <img> and an <h3>.
//    This is the most reliable "news card" pattern regardless of class name.
//    We also extract category badge (.cb or .nc-cat) and time if present.
//
//    ★ v3.73: Always run this fallback on category/search pages — even if some .nc
//    or .cat-panel-card cards were found, the category page may have additional cards
//    in another template that we'd otherwise miss. We dedup by href so no duplicates.
$_earlyCatV73 = $_GET['cat'] ?? '';
$_earlyQV73   = isset($_GET['q']) ? trim($_GET['q']) : '';
$_runFallbackV73 = (empty($newsCards)) || ($_earlyCatV73 !== '') || ($_earlyQV73 !== '');

if ($_runFallbackV73) {
    // Pattern: <a href="..." ...>...<img ...>...<h3 ...>Title</h3>...</a>
    // Must have both img and h3 to qualify as a news card.
    if (preg_match_all('#<a\s+[^>]*href="([^"]+)"[^>]*>(.*?)(?:</a>|$)#us', $html, $anchorMatches)) {
        foreach ($anchorMatches[1] as $aIdx => $aHref) {
            $aBlock = $anchorMatches[2][$aIdx];
            // Must have an <img>
            if (!preg_match('#<img[^>]+src="([^"]+)"#u', $aBlock, $aIm)) continue;
            // Must have an <h3> with non-empty text
            if (!preg_match('#<h3[^>]*>([^<]+)</h3>#u', $aBlock, $aTm)) continue;
            $aTitle = html_entity_decode(trim($aTm[1]), ENT_QUOTES, 'UTF-8');
            if (empty($aTitle)) continue;
            // Skip internal/system links
            if (strpos($aHref, 'javascript:') === 0) continue;
            if (strpos($aHref, '#') === 0) continue;

            $aImg  = $aIm[1];
            $aCat  = '';
            $aTime = '';
            // Try category badge patterns
            if (preg_match('#<div class="nc-cat"[^>]*>([^<]+)</div>#u', $aBlock, $aCm)) {
                $aCat = trim($aCm[1]);
            } elseif (preg_match('#<span class="cb"[^>]*>([^<]+)</span>#u', $aBlock, $aCm2)) {
                $aCat = trim($aCm2[1]);
            } elseif (preg_match('#<div class="fcs"[^>]*>([^<]+)</div>#u', $aBlock, $aCm3)) {
                $aCat = trim($aCm3[1]);
            }
            // Try time
            if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $aBlock, $aTm2)) {
                $aTime = trim($aTm2[1]);
            }

            // Dedup by href
            $dedupKey = $aHref;
            if (isset($existingHrefs[$dedupKey])) continue;
            $existingHrefs[$dedupKey] = true;

            $newsCards[] = [
                'href'  => $aHref,
                'img'   => $aImg,
                'title' => $aTitle,
                'cat'   => $aCat,
                'time'  => $aTime,
            ];
        }
    }
}

// ★ v3.103: Helper functions — detect video files and render video thumbnails.
//    admin_dashboard.php saves uploaded .mp4 files into the news `image` field.
//    Browser <img> tags can't display .mp4 — so we detect video files and use
//    <video> tags instead (which show the first frame as a thumbnail).
/**
 * Check if a URL/path points to a video file (not an image).
 */
function _isVideoFilePath($url) {
    if (empty($url)) return false;
    $urlClean = preg_replace('/[?#].*$/', '', $url);
    return (bool)preg_match('#\.(mp4|webm|ogg|mov|m4v|avi|mkv|flv|wmv|3gp)$#i', $urlClean);
}

/**
 * Render a thumbnail for a news card — uses <video> if the file is a video,
 * <img> otherwise. Adds a play-icon overlay for videos so users know to click.
 * ★ v3.104: Also handles 'video-embed:' prefix (Facebook/Vimeo embeds with no thumbnail).
 */
function _renderCardThumb($imgUrl, $cssClass) {
    if (empty($imgUrl)) {
        // Placeholder SVG
        $placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";
        return '<img src="' . $placeholder . '" alt="" class="' . $cssClass . '" loading="lazy">';
    }
    // ★ v3.104: 'video-embed:' prefix = Facebook/Vimeo/Twitter embed (no thumbnail available)
    //    Show a dark gradient background with a large play icon.
    if (strpos($imgUrl, 'video-embed:') === 0) {
        return '<div style="position:relative;width:100%;aspect-ratio:16/10;overflow:hidden;background:linear-gradient(135deg,#1a1a1a,#2d2d2d);">'
             . '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:50px;height:50px;background:rgba(183,28,28,.9);border-radius:50%;display:flex;align-items:center;justify-content:center;pointer-events:none;box-shadow:0 2px 10px rgba(0,0,0,.4);">'
             . '<i class="fas fa-play" style="color:#fff;font-size:18px;margin-left:3px;"></i>'
             . '</div>'
             . '<div style="position:absolute;bottom:6px;left:8px;color:rgba(255,255,255,.5);font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:1px;">'
             . '<i class="fas fa-video" style="margin-right:3px;"></i>Video'
             . '</div></div>';
    }
    if (_isVideoFilePath($imgUrl)) {
        // ★ Video file → use <video> tag (shows first frame as thumbnail) + play icon overlay
        return '<div style="position:relative;width:100%;aspect-ratio:16/10;overflow:hidden;background:#000;">'
             . '<video src="' . htmlspecialchars($imgUrl) . '" class="' . $cssClass . '" loading="lazy" muted preload="metadata" style="width:100%;height:100%;object-fit:cover;"></video>'
             . '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:40px;height:40px;background:rgba(183,28,28,.85);border-radius:50%;display:flex;align-items:center;justify-content:center;pointer-events:none;">'
             . '<i class="fas fa-play" style="color:#fff;font-size:14px;margin-left:2px;"></i>'
             . '</div></div>';
    }
    // ★ v3.104: For YouTube thumbnails, add a play-icon overlay on top of the image
    if (preg_match('#img\.youtube\.com/vi/#i', $imgUrl) || preg_match('#img\.vimeo\.com#i', $imgUrl)) {
        return '<div style="position:relative;width:100%;aspect-ratio:16/10;overflow:hidden;background:#000;">'
             . '<img src="' . htmlspecialchars($imgUrl) . '" alt="" class="' . $cssClass . '" loading="lazy" style="width:100%;height:100%;object-fit:cover;">'
             . '<div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:44px;height:44px;background:rgba(183,28,28,.85);border-radius:50%;display:flex;align-items:center;justify-content:center;pointer-events:none;box-shadow:0 2px 8px rgba(0,0,0,.4);">'
             . '<i class="fas fa-play" style="color:#fff;font-size:16px;margin-left:2px;"></i>'
             . '</div></div>';
    }
    return '<img src="' . htmlspecialchars($imgUrl) . '" alt="" class="' . $cssClass . '" loading="lazy">';
}

// ★ v3.78: SUPER SIMPLE DB QUERY for category/search pages
//    Replaces complex v3.75-v3.77 logic with a straightforward approach.
//    On category/search pages, query DB directly for latest news (same as home panel).
//    This GUARANTEES the category page shows the same news as the home panel.
$_v378_cat = isset($_GET['cat']) ? $_GET['cat'] : '';
$_v378_q   = isset($_GET['q']) ? trim($_GET['q']) : '';
$_v378_sub = isset($_GET['sub']) ? (int)$_GET['sub'] : 0;
$_v378_need = (($_v378_cat !== '') || ($_v378_q !== ''));

// ★ v3.101 FIX 1b: Resolve Video category name to actual DB name.
//    If the user typed ?cat=Video directly (or clicked an old bookmark),
//    but the DB stores the category as 'ভিডিও', the news query would fail.
//    Here we resolve any Video-related input to the actual category name
//    stored in the categories table, so the query always matches.
if ($_v378_cat !== '' && isset($pdo)) {
    $_v378_catLowerTmp = strtolower($_v378_cat);
    $_v378_isVideoInput = (
        $_v378_catLowerTmp === 'video' || $_v378_catLowerTmp === 'videos' ||
        $_v378_cat === 'ভিডিও' || $_v378_cat === 'ভিডিওসমূহ' ||
        strpos($_v378_catLowerTmp, 'video') !== false ||
        mb_stripos($_v378_cat, 'ভিডিও', 0, 'UTF-8') !== false
    );
    if ($_v378_isVideoInput) {
        try {
            @$pdo->exec("SET NAMES utf8mb4");
            $_v378_resolveSql = "SELECT name FROM categories
                                 WHERE name IN ('Video', 'ভিডিও', 'Videos', 'ভিডিওসমূহ', 'video', 'videos')
                                    OR LOWER(name) LIKE '%video%'
                                    OR name LIKE '%ভিডিও%'
                                 LIMIT 1";
            $_v378_resolvedName = $pdo->query($_v378_resolveSql)->fetchColumn();
            if ($_v378_resolvedName && $_v378_resolvedName !== '' && $_v378_resolvedName !== $_v378_cat) {
                $_v378_cat = $_v378_resolvedName;
            }
        } catch (Exception $_v378_re) {}
    }
}

if ($_v378_need && isset($pdo)) {
    try {
        // Ensure UTF-8 + IST timezone
        @$pdo->exec("SET NAMES utf8mb4");
        @$pdo->exec("SET time_zone = '+05:30'");

        // Build query — IDENTICAL to index.php home panel query (but no LIMIT)
        // ★ v3.89 FIX: Removed correlated subquery — it could break the ENTIRE query
        //    if `subcategories` table name/columns differ. Subcategory is now fetched
        //    separately by the v3.88 batch lookup below (which has its own try-catch).
        //    This ensures the main news query NEVER fails due to subcategory table issues.
        $_v378_sql = "SELECT n.id, n.title, n.image, n.created_at, c.name as cat_name
                      FROM news n
                      LEFT JOIN categories c ON n.category_id = c.id
                      WHERE n.status = 'published'
                        AND n.created_at <= NOW()";
        $_v378_params = array();

        if ($_v378_cat !== '') {
            $_v378_sql .= " AND c.name = ?";
            $_v378_params[] = $_v378_cat;
        }
        if ($_v378_q !== '') {
            $_v378_sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
            $_v378_params[] = "%" . $_v378_q . "%";
            $_v378_params[] = "%" . $_v378_q . "%";
        }
        if ($_v378_sub > 0) {
            $_v378_sql .= " AND n.id IN (SELECT news_id FROM news_subcategories WHERE subcategory_id = ?)";
            $_v378_params[] = $_v378_sub;
        }

        // Date limit: archive = older than 30 days; everything else = last 30 days
        if ($_v378_cat === 'আর্কাইভ') {
            $_v378_sql .= " AND n.created_at < (NOW() - INTERVAL 30 DAY)";
        } else {
            $_v378_sql .= " AND n.created_at >= (NOW() - INTERVAL 30 DAY)";
        }

        // Latest first — same as home panel
        $_v378_sql .= " ORDER BY n.created_at DESC";

        $_v378_stmt = $pdo->prepare($_v378_sql);
        $_v378_stmt->execute($_v378_params);
        $_v378_rows = $_v378_stmt->fetchAll(PDO::FETCH_ASSOC);

        // If exact match returned nothing AND we have a category, try LIKE fallback
        if (empty($_v378_rows) && $_v378_cat !== '') {
            $_v378_sql2 = str_replace("c.name = ?", "c.name LIKE ?", $_v378_sql);
            $_v378_params2 = $_v378_params;
            // Find the category param and wrap it in %
            foreach ($_v378_params2 as &$_v378_p) {
                if ($_v378_p === $_v378_cat) {
                    $_v378_p = "%" . $_v378_cat . "%";
                    break;
                }
            }
            unset($_v378_p);
            $_v378_stmt2 = $pdo->prepare($_v378_sql2);
            $_v378_stmt2->execute($_v378_params2);
            $_v378_rows = $_v378_stmt2->fetchAll(PDO::FETCH_ASSOC);
        }

        // Convert rows to $newsCards format
        $_v378_placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";

        $_v378_newCards = array();
        foreach ($_v378_rows as $_v378_row) {
            // Image URL
            $_v378_img = $_v378_placeholder;
            if (!empty($_v378_row['image'])) {
                $_v378_imgRaw = trim($_v378_row['image']);
                if ($_v378_imgRaw !== '' && $_v378_imgRaw !== '0') {
                    if (preg_match('#^https?://#i', $_v378_imgRaw) || strpos($_v378_imgRaw, '//') === 0) {
                        $_v378_img = $_v378_imgRaw;
                    } else {
                        while (strpos($_v378_imgRaw, 'uploads/uploads/') === 0) {
                            $_v378_imgRaw = substr($_v378_imgRaw, 8);
                        }
                        if (strpos($_v378_imgRaw, 'uploads/') !== 0 && strpos($_v378_imgRaw, '/') !== 0) {
                            $_v378_img = 'uploads/' . $_v378_imgRaw;
                        } else {
                            $_v378_img = $_v378_imgRaw;
                        }
                    }
                }
            }

            // Time ago
            $_v378_time = '';
            if (function_exists('timeAgo')) {
                $_v378_time = timeAgo($_v378_row['created_at']);
            } else {
                $_v378_time = date('d M Y', strtotime($_v378_row['created_at']));
            }

            $_v378_newCards[] = array(
                'href'  => '?page=single&id=' . (int)$_v378_row['id'],
                'img'   => $_v378_img,
                'title' => $_v378_row['title'],
                'cat'   => $_v378_row['cat_name'],
                'sub'   => '',  // ★ v3.89: Populated by batch lookup below
                'time'  => $_v378_time,
            );
        }

        // ★ REPLACE $newsCards with DB results
        if (!empty($_v378_newCards)) {
            $newsCards = $_v378_newCards;
        }
    } catch (Exception $_v378_e) {
        // Silent fail — fall back to HTML-extracted $newsCards
    }
}

// ★ v3.101 FIX 2: Videos table fallback.
//    If the current category is Video-related AND no news items were found
//    from the news table (the $_v378 block above), check if there's a
//    separate `videos` table (where admin_dashboard.php may upload videos).
//    If found, dynamically discover its columns and query it, then convert
//    the results into $newsCards so they render on the Video category page.
//
//    This handles BOTH scenarios:
//      (a) Videos stored as news items with a Video/ভিডিও category → handled
//          by $_v378 block + Fix 1 (actual category name detection).
//      (b) Videos stored in a dedicated `videos` table → handled here.
$_v378_isVideoCat = false;
if ($_v378_cat !== '') {
    $_v378_catLower = strtolower($_v378_cat);
    if ($_v378_catLower === 'video' || $_v378_catLower === 'videos' ||
        $_v378_cat === 'ভিডিও' || $_v378_cat === 'ভিডিওসমূহ' ||
        strpos($_v378_catLower, 'video') !== false ||
        mb_stripos($_v378_cat, 'ভিডিও', 0, 'UTF-8') !== false) {
        $_v378_isVideoCat = true;
    }
}

if ($_v378_isVideoCat && empty($newsCards) && isset($pdo)) {
    try {
        @$pdo->exec("SET NAMES utf8mb4");
        @$_v378_vDb = $pdo->query("SELECT DATABASE()")->fetchColumn();

        if ($_v378_vDb) {
            // Step 1: Discover the videos table (videos, video, video_uploads, etc.)
            $_v378_findVTableSql = "SELECT TABLE_NAME FROM information_schema.TABLES
                                    WHERE TABLE_SCHEMA = " . $pdo->quote($_v378_vDb) . "
                                      AND (TABLE_NAME LIKE '%video%')
                                    ORDER BY
                                      CASE
                                        WHEN TABLE_NAME = 'videos' THEN 1
                                        WHEN TABLE_NAME = 'video' THEN 2
                                        WHEN TABLE_NAME = 'video_uploads' THEN 3
                                        ELSE 4
                                      END,
                                      TABLE_NAME";
            $_v378_vTableRows = $pdo->query($_v378_findVTableSql)->fetchAll(PDO::FETCH_ASSOC);
            $_v378_videosTable = '';
            if (!empty($_v378_vTableRows)) {
                $_v378_videosTable = $_v378_vTableRows[0]['TABLE_NAME'];
            }

            if ($_v378_videosTable !== '') {
                // Step 2: Discover columns of the videos table
                $_v378_vColSql = "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
                                  WHERE TABLE_SCHEMA = " . $pdo->quote($_v378_vDb) . "
                                    AND TABLE_NAME = " . $pdo->quote($_v378_videosTable);
                $_v378_vCols = $pdo->query($_v378_vColSql)->fetchAll(PDO::FETCH_ASSOC);

                $_v378_colId = 'id';
                $_v378_colTitle = '';
                $_v378_colThumb = '';
                $_v378_colVideo = '';
                $_v378_colDate = '';
                $_v378_colDesc = '';
                $_v378_colNewsId = '';
                $_v378_colStatus = '';

                foreach ($_v378_vCols as $_v378_vc) {
                    $_v378_cn = strtolower($_v378_vc['COLUMN_NAME']);
                    // ID column
                    if ($_v378_cn === 'id' || $_v378_cn === 'video_id' || $_v378_cn === 'vid') {
                        $_v378_colId = $_v378_vc['COLUMN_NAME'];
                    }
                    // Title column
                    if (in_array($_v378_cn, ['title', 'name', 'heading', 'video_title', 'caption', 'subject'])) {
                        $_v378_colTitle = $_v378_vc['COLUMN_NAME'];
                    }
                    // Thumbnail / image column
                    if (in_array($_v378_cn, ['thumbnail', 'thumb', 'image', 'img', 'poster', 'cover', 'picture', 'photo', 'thumbnail_url', 'image_url'])) {
                        $_v378_colThumb = $_v378_vc['COLUMN_NAME'];
                    }
                    // Video file / URL / embed code column
                    // ★ v3.104: Added 'code' — admin_dashboard.php stores embed HTML in `code` column
                    if (in_array($_v378_cn, ['video', 'video_url', 'url', 'file', 'file_url', 'video_file', 'source', 'src', 'embed', 'embed_url', 'embed_code', 'code', 'youtube_url', 'link', 'iframe'])) {
                        $_v378_colVideo = $_v378_vc['COLUMN_NAME'];
                    }
                    // Date column
                    if (in_array($_v378_cn, ['created_at', 'uploaded_at', 'date', 'timestamp', 'created', 'upload_date', 'added_at', 'post_date'])) {
                        $_v378_colDate = $_v378_vc['COLUMN_NAME'];
                    }
                    // Description column
                    if (in_array($_v378_cn, ['description', 'desc', 'content', 'details', 'summary', 'body'])) {
                        $_v378_colDesc = $_v378_vc['COLUMN_NAME'];
                    }
                    // News foreign key (if video is linked to a news article)
                    if (in_array($_v378_cn, ['news_id', 'article_id', 'post_id', 'news_link'])) {
                        $_v378_colNewsId = $_v378_vc['COLUMN_NAME'];
                    }
                    // Status column (only treat as status if it's a varchar/enum type)
                    if ($_v378_cn === 'status' && in_array(strtolower($_v378_vc['DATA_TYPE']), ['varchar', 'enum', 'char'])) {
                        $_v378_colStatus = $_v378_vc['COLUMN_NAME'];
                    }
                }

                // Step 3: Build SELECT query — only select columns that exist
                $_v378_vSelectParts = ["`{$_v378_colId}` AS vid"];
                if ($_v378_colTitle !== '') $_v378_vSelectParts[] = "`{$_v378_colTitle}` AS vtitle";
                if ($_v378_colThumb !== '') $_v378_vSelectParts[] = "`{$_v378_colThumb}` AS vthumb";
                if ($_v378_colVideo !== '') $_v378_vSelectParts[] = "`{$_v378_colVideo}` AS vurl";
                if ($_v378_colDate !== '') $_v378_vSelectParts[] = "`{$_v378_colDate}` AS vdate";
                if ($_v378_colDesc !== '') $_v378_vSelectParts[] = "`{$_v378_colDesc}` AS vdesc";
                if ($_v378_colNewsId !== '') $_v378_vSelectParts[] = "`{$_v378_colNewsId}` AS vnewsid";

                $_v378_vSelectSql = "SELECT " . implode(', ', $_v378_vSelectParts) . " FROM `{$_v378_videosTable}`";

                // Add status filter if a status column exists
                $_v378_vParams = [];
                if ($_v378_colStatus !== '') {
                    $_v378_vSelectSql .= " WHERE (`{$_v378_colStatus}` = 'published' OR `{$_v378_colStatus}` IS NULL OR `{$_v378_colStatus}` = '')";
                }

                // Order by date if available, else by id
                if ($_v378_colDate !== '') {
                    $_v378_vSelectSql .= " ORDER BY `vdate` DESC";
                } else {
                    $_v378_vSelectSql .= " ORDER BY vid DESC";
                }
                $_v378_vSelectSql .= " LIMIT 100";

                $_v378_vStmt = $pdo->prepare($_v378_vSelectSql);
                $_v378_vStmt->execute($_v378_vParams);
                $_v378_vRows = $_v378_vStmt->fetchAll(PDO::FETCH_ASSOC);

                // Step 4: Convert video rows to $newsCards format
                $_v378_placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";

                $_v378_videoCatLabel = $isEnglish ? 'Video' : 'ভিডিও';

                foreach ($_v378_vRows as $_v378_vr) {
                    // Thumbnail — try thumbnail column, then extract from embed code
                    // ★ v3.104: admin_dashboard.php's videos table has NO thumbnail column.
                    //    The `code` column contains embed HTML (iframe/URL). So we:
                    //    1. Try the thumbnail column if it exists
                    //    2. Extract YouTube thumbnail from the embed code
                    //    3. Fall back to a play-icon placeholder
                    $_v378_vImg = $_v378_placeholder;
                    if (!empty($_v378_vr['vthumb'])) {
                        $_v378_vImgRaw = trim($_v378_vr['vthumb']);
                        if ($_v378_vImgRaw !== '' && $_v378_vImgRaw !== '0') {
                            if (preg_match('#^https?://#i', $_v378_vImgRaw) || strpos($_v378_vImgRaw, '//') === 0) {
                                $_v378_vImg = $_v378_vImgRaw;
                            } else {
                                while (strpos($_v378_vImgRaw, 'uploads/uploads/') === 0) {
                                    $_v378_vImgRaw = substr($_v378_vImgRaw, 8);
                                }
                                if (strpos($_v378_vImgRaw, 'uploads/') !== 0 && strpos($_v378_vImgRaw, '/') !== 0) {
                                    $_v378_vImg = 'uploads/' . $_v378_vImgRaw;
                                } else {
                                    $_v378_vImg = $_v378_vImgRaw;
                                }
                            }
                        }
                    }

                    // ★ v3.104: If no thumbnail, try to extract one from the embed code
                    //    YouTube embed → https://img.youtube.com/vi/VIDEO_ID/hqdefault.jpg
                    //    Direct video file → the file URL itself (_renderCardThumb will use <video>)
                    //    Other (Facebook/Vimeo iframe) → play-icon placeholder SVG
                    if ($_v378_vImg === $_v378_placeholder && !empty($_v378_vr['vurl'])) {
                        $_v378_embedCode = trim($_v378_vr['vurl']);
                        // YouTube ID extraction (works with embed URLs, watch URLs, youtu.be, iframe src)
                        if (preg_match('#(?:youtube\.com/(?:embed/|watch\?v=|v/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,15})#', $_v378_embedCode, $_v378_ytM)) {
                            $_v378_vImg = 'https://img.youtube.com/vi/' . $_v378_ytM[1] . '/hqdefault.jpg';
                        }
                        // Direct video file → store the URL itself; _renderCardThumb will render <video>
                        elseif (preg_match('#https?://[^\s<"]+\.(mp4|webm|ogg|mov|m4v)#i', $_v378_embedCode, $_v378_fM) ||
                                preg_match('#(uploads/[^\s<"]+\.(mp4|webm|ogg|mov|m4v))#i', $_v378_embedCode, $_v378_fM)) {
                            $_v378_vImg = $_v378_fM[1] ?? $_v378_fM[0];
                        }
                        // Otherwise: it's a Facebook/Vimeo/Twitter iframe embed → use a special
                        // "video-embed" marker so _renderCardThumb shows a play-icon placeholder
                        else {
                            $_v378_vImg = 'video-embed:' . md5($_v378_embedCode);
                        }
                    }

                    // Title — fallback to 'Video #ID' if no title column
                    $_v378_vTitle = !empty($_v378_vr['vtitle']) ? $_v378_vr['vtitle'] : ('Video #' . (int)($_v378_vr['vid'] ?? 0));

                    // Time ago
                    $_v378_vTime = '';
                    if (!empty($_v378_vr['vdate'])) {
                        if (function_exists('timeAgo')) {
                            $_v378_vTime = timeAgo($_v378_vr['vdate']);
                        } else {
                            $_v378_vTime = date('d M Y', strtotime($_v378_vr['vdate']));
                        }
                    }

                    // Link: if there's a news_id foreign key, link to that news article;
                    // otherwise link to single page with the video ID (single-page renderer
                    // will need to look up the videos table if news lookup fails — see below).
                    $_v378_vHref = '';
                    if (!empty($_v378_vr['vnewsid']) && (int)$_v378_vr['vnewsid'] > 0) {
                        $_v378_vHref = '?page=single&id=' . (int)$_v378_vr['vnewsid'];
                    } else {
                        $_v378_vHref = '?page=single&id=' . (int)($_v378_vr['vid'] ?? 0) . '&vid=' . (int)($_v378_vr['vid'] ?? 0);
                    }

                    $newsCards[] = [
                        'href'  => $_v378_vHref,
                        'img'   => $_v378_vImg,
                        'title' => $_v378_vTitle,
                        'cat'   => $_v378_videoCatLabel,
                        'sub'   => '',
                        'time'  => $_v378_vTime,
                    ];
                }
            }
        }
    } catch (Exception $_v378_vE) {
        // Silent fail — fall back to whatever $newsCards currently has
    }
}

// ★ v3.92: DYNAMIC DISCOVERY subcategory lookup.
//    Previous v3.88/v3.89/v3.91 all assumed table names — and FAILED.
//    v3.92 uses SHOW TABLES to FIND the actual subcategory table name,
//    then SHOW COLUMNS to find the name column. 100% adaptive.
//
//    Step 1: Build subcategory_id → name map from $dbCatSubcats (if available)
//    Step 2: If map is empty, dynamically discover the subcategory table
//            and build the map directly from it.
//    Step 3: Find the junction table (news_subcategories or variant)
//    Step 4: Query junction for news→subcategory_id, look up name from map.

// ── Step 1: Build subNameMap from $dbCatSubcats (index.php's variable) ──
$_v392_subNameMap = [];
if (!empty($dbCatSubcats) && is_array($dbCatSubcats)) {
    foreach ($dbCatSubcats as $_v392_catName => $_v392_subs) {
        if (is_array($_v392_subs)) {
            foreach ($_v392_subs as $_v392_sub) {
                if (is_array($_v392_sub) && isset($_v392_sub['id'])) {
                    $_v392_subNameMap[(int)$_v392_sub['id']] = $_v392_sub['name'] ?? '';
                }
            }
        }
    }
}

// ── Step 2: If subNameMap is empty, dynamically discover the subcategory table ──
//    This runs when $dbCatSubcats was empty (index.php didn't populate it).
//    We query information_schema to find a table that looks like a subcategory table.
$_v392_subcatTable = '';   // Discovered subcategory table name
$_v392_subcatIdCol = 'id'; // Primary key column (usually 'id')
$_v392_subcatNameCol = 'name'; // Name column ('name' or 'title' or 'subcategory_name')

if (empty($_v392_subNameMap) && isset($pdo)) {
    try {
        // ★ Find tables that look like subcategory storage
        //    Match: subcategories, sub_categories, subcategory, sub_category,
        //           subcategory_list, etc. — but NOT junction tables (news_subcategories)
        $_v392_dbName = $pdo->query("SELECT DATABASE()")->fetchColumn();
        if ($_v392_dbName) {
            $_v392_findSql = "SELECT TABLE_NAME 
                              FROM information_schema.TABLES 
                              WHERE TABLE_SCHEMA = " . $pdo->quote($_v392_dbName) . "
                                AND (TABLE_NAME LIKE '%subcateg%' OR TABLE_NAME LIKE '%sub_categ%')
                                AND TABLE_NAME NOT LIKE '%news%'
                              ORDER BY 
                                CASE 
                                  WHEN TABLE_NAME = 'subcategories' THEN 1
                                  WHEN TABLE_NAME = 'sub_categories' THEN 2
                                  WHEN TABLE_NAME = 'subcategory' THEN 3
                                  WHEN TABLE_NAME = 'sub_category' THEN 4
                                  ELSE 5
                                END,
                                TABLE_NAME";
            $_v392_tableRows = $pdo->query($_v392_findSql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($_v392_tableRows as $_v392_tr) {
                $_v392_tname = $_v392_tr['TABLE_NAME'];
                // Verify this table has an id column and a name-like column
                $_v392_colSql = "SELECT COLUMN_NAME, COLUMN_TYPE 
                                 FROM information_schema.COLUMNS 
                                 WHERE TABLE_SCHEMA = " . $pdo->quote($_v392_dbName) . "
                                   AND TABLE_NAME = " . $pdo->quote($_v392_tname);
                $_v392_cols = $pdo->query($_v392_colSql)->fetchAll(PDO::FETCH_ASSOC);
                $_v392_hasId = false;
                $_v392_nameCol = '';
                $_v392_idCol = 'id';
                foreach ($_v392_cols as $_v392_c) {
                    $_v392_cn = strtolower($_v392_c['COLUMN_NAME']);
                    if ($_v392_cn === 'id' || $_v392_cn === 'subcategory_id' || $_v392_cn === 'sub_id') {
                        $_v392_hasId = true;
                        $_v392_idCol = $_v392_c['COLUMN_NAME'];
                    }
                    if (in_array($_v392_cn, ['name', 'title', 'subcategory_name', 'sub_name', 'label', 'subcategory'])) {
                        $_v392_nameCol = $_v392_c['COLUMN_NAME'];
                    }
                }
                if ($_v392_hasId && $_v392_nameCol) {
                    $_v392_subcatTable = $_v392_tname;
                    $_v392_subcatIdCol = $_v392_idCol;
                    $_v392_subcatNameCol = $_v392_nameCol;
                    break;
                }
            }
        }
        
        // ★ If we found a subcategory table, load ALL subcategory names into the map
        if (!empty($_v392_subcatTable)) {
            $_v392_loadSql = "SELECT `{$_v392_subcatIdCol}` AS sid, `{$_v392_subcatNameCol}` AS sname FROM `{$_v392_subcatTable}`";
            $_v392_loadRows = $pdo->query($_v392_loadSql)->fetchAll(PDO::FETCH_ASSOC);
            foreach ($_v392_loadRows as $_v392_lr) {
                $_v392_subNameMap[(int)$_v392_lr['sid']] = $_v392_lr['sname'];
            }
        }
    } catch (Exception $_v392_e) {
        // Silent fail — fall back to empty map
    }
}

// ── Step 3: Find the junction table (news_subcategories or variant) ──
$_v392_junctionTable = '';
if (isset($pdo)) {
    try {
        $_v392_dbName2 = $pdo->query("SELECT DATABASE()")->fetchColumn();
        if ($_v392_dbName2) {
            $_v392_findJunction = "SELECT TABLE_NAME 
                                   FROM information_schema.TABLES 
                                   WHERE TABLE_SCHEMA = " . $pdo->quote($_v392_dbName2) . "
                                     AND TABLE_NAME LIKE '%news%sub%'
                                   ORDER BY 
                                     CASE 
                                       WHEN TABLE_NAME = 'news_subcategories' THEN 1
                                       WHEN TABLE_NAME = 'news_sub_categories' THEN 2
                                       ELSE 3
                                     END,
                                     TABLE_NAME";
            $_v392_jRows = $pdo->query($_v392_findJunction)->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($_v392_jRows)) {
                $_v392_junctionTable = $_v392_jRows[0]['TABLE_NAME'];
            }
        }
    } catch (Exception $_v392_e2) {}
}

// ── Step 4: Batch fetch subcategory for ALL news cards ──
//    $ncSubMap = news_id => subcategory_name
$ncSubMap = [];
if (isset($pdo) && !empty($newsCards) && !empty($_v392_subNameMap) && !empty($_v392_junctionTable)) {
    // Collect all news IDs that need subcategory lookup
    $_v392_ids = [];
    foreach ($newsCards as $_v392_nc) {
        if (!empty($_v392_nc['sub'])) continue;  // Already has sub
        if (!empty($_v392_nc['href']) && preg_match('/[?&]id=(\d+)/', $_v392_nc['href'], $_v392_m)) {
            $_v392_ids[] = (int)$_v392_m[1];
        }
    }
    $_v392_ids = array_values(array_unique($_v392_ids));

    if (!empty($_v392_ids)) {
        $_v392_placeholders = implode(',', array_fill(0, count($_v392_ids), '?'));

        // ★ Detect junction table columns (news_id, subcategory_id may have different names)
        $_v392_newsIdCol = 'news_id';
        $_v392_subIdCol = 'subcategory_id';
        try {
            $_v392_dbName3 = $pdo->query("SELECT DATABASE()")->fetchColumn();
            $_v392_jColSql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS 
                              WHERE TABLE_SCHEMA = " . $pdo->quote($_v392_dbName3) . "
                                AND TABLE_NAME = " . $pdo->quote($_v392_junctionTable);
            $_v392_jCols = $pdo->query($_v392_jColSql)->fetchAll(PDO::FETCH_COLUMN);
            foreach ($_v392_jCols as $_v392_jc) {
                $_v392_jcl = strtolower($_v392_jc);
                if (in_array($_v392_jcl, ['news_id', 'news', 'post_id', 'article_id', 'item_id'])) {
                    $_v392_newsIdCol = $_v392_jc;
                }
                if (in_array($_v392_jcl, ['subcategory_id', 'sub_id', 'sub_category_id', 'subcategory'])) {
                    $_v392_subIdCol = $_v392_jc;
                }
            }
        } catch (Exception $_v392_e3) {}

        // Query the junction table
        try {
            $_v392_jSql = "SELECT `{$_v392_newsIdCol}` AS news_id, `{$_v392_subIdCol}` AS subcategory_id 
                           FROM `{$_v392_junctionTable}` 
                           WHERE `{$_v392_newsIdCol}` IN ($_v392_placeholders) 
                           ORDER BY `{$_v392_newsIdCol}` ASC, `{$_v392_subIdCol}` ASC";
            $_v392_stmt = $pdo->prepare($_v392_jSql);
            $_v392_stmt->execute($_v392_ids);
            $_v392_rows = $_v392_stmt->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($_v392_rows)) {
                foreach ($_v392_rows as $_v392_r) {
                    $_v392_nid = (int)$_v392_r['news_id'];
                    $_v392_sid = (int)$_v392_r['subcategory_id'];
                    // Keep only the FIRST subcategory per news
                    if (!isset($ncSubMap[$_v392_nid])) {
                        if (isset($_v392_subNameMap[$_v392_sid])) {
                            $ncSubMap[$_v392_nid] = $_v392_subNameMap[$_v392_sid];
                        }
                    }
                }
            }
        } catch (Exception $_v392_e4) {}

        // Populate 'sub' field on cards that were missing it
        if (!empty($ncSubMap)) {
            foreach ($newsCards as &$_v392_nc_ref) {
                if (empty($_v392_nc_ref['sub']) && !empty($_v392_nc_ref['href'])) {
                    if (preg_match('/[?&]id=(\d+)/', $_v392_nc_ref['href'], $_v392_m2)) {
                        $_v392_nid2 = (int)$_v392_m2[1];
                        if (isset($ncSubMap[$_v392_nid2])) {
                            $_v392_nc_ref['sub'] = $ncSubMap[$_v392_nid2];
                        }
                    }
                }
            }
            unset($_v392_nc_ref);
        }
    }
}

// ★ v3.99: Helper function — Detect duplicate subcategory scenarios.
//    User's instructions (combined v3.95 + v3.96 + v3.99):
//    1. If cat has '/' → subcategory already in red → keep all red, NO gold sub.
//    2. If DB sub is substring of cat (e.g., cat="জাতীয় কলকাতা", sub="কলকাতা") → NO gold sub.
//    3. ★ v3.99: If cat and sub are SAME NAME → STILL show gold sub (user wants it!).
//       Example: cat="বিনোদন", sub="বিনোদন" → show "বিনোদন / বিনোদন" (red + gold).
//    4. Otherwise → gold sub WILL be added.
function _v395_parseCatSub($catField, $dbSub = '') {
    $result = ['cat' => $catField, 'sub' => '', 'subAlreadyInCat' => false];

    if (empty($catField)) return $result;

    // ★ v3.96: If cat field has '/' separator, subcategory is ALREADY in red.
    //    Keep the WHOLE cat field as-is, set subAlreadyInCat=true.
    if (strpos($catField, '/') !== false) {
        $result['cat'] = $catField;
        $result['sub'] = '';
        $result['subAlreadyInCat'] = true;
        return $result;
    }

    // ★ v3.95: Check if DB sub is substring of cat (e.g., cat="জাতীয় কলকাতা", sub="কলকাতা")
    //    Only suppress if sub is CONTAINED in cat (true duplicate), NOT if they're equal.
    //    ★ v3.99: REMOVED the same-name suppression — user wants "বিনোদন / বিনোদন" to show.
    if (!empty($dbSub)) {
        if (mb_stripos($catField, $dbSub, 0, 'UTF-8') !== false) {
            // Sub is substring of cat — but only suppress if cat is LONGER than sub
            // (i.e., cat contains MORE than just the sub name)
            $_v399_catLen = mb_strlen($catField, 'UTF-8');
            $_v399_subLen = mb_strlen($dbSub, 'UTF-8');
            if ($_v399_catLen > $_v399_subLen) {
                // cat is longer → sub is part of a longer cat string → suppress (real duplicate)
                $result['subAlreadyInCat'] = true;
            }
            // If catLen === subLen → cat and sub are the SAME → DON'T suppress (v3.99)
        }
    }

    return $result;
}

// ★ v3.98: Card-level debug info — track per-card subcategory resolution.
//    This helps diagnose WHY sub isn't showing for specific cards.
$_v398_cardDebug = [];

// ★ v3.92: Debug info — helps diagnose what was discovered.
//    Output as HTML comment at the end of the page (invisible to users).
$_v392_debug = [
    'subNameMap_size' => count($_v392_subNameMap),
    'subcatTable' => $_v392_subcatTable ?? '',
    'subcatNameCol' => $_v392_subcatNameCol ?? '',
    'junctionTable' => $_v392_junctionTable ?? '',
    'newsIdCol' => $_v392_newsIdCol ?? 'news_id',
    'subIdCol' => $_v392_subIdCol ?? 'subcategory_id',
    'ncSubMap_size' => count($ncSubMap),
    'dbCatSubcats_empty' => empty($dbCatSubcats) ? 'yes' : 'no',
    'card_debug' => &$_v398_cardDebug,  // ★ v3.98: Per-card debug
];

// ★ v3.92: Helper closure — look up subcategory name by news href.
//    Uses $ncSubMap first, then falls back to single-item junction query
//    + $_v392_subNameMap for name lookup.
$ncGetSub = function($href) use (&$ncSubMap, $pdo, &$_v392_subNameMap, $_v392_junctionTable, $_v392_newsIdCol, $_v392_subIdCol) {
    if (empty($href) || !preg_match('/[?&]id=(\d+)/', $href, $m)) {
        return '';
    }
    $_nid = (int)$m[1];

    // 1. Check batch map first (fastest)
    if (isset($ncSubMap[$_nid])) {
        return $ncSubMap[$_nid];
    }

    // 2. Fallback: single-item junction query + name lookup
    if (isset($pdo) && !empty($_v392_subNameMap) && !empty($_v392_junctionTable)) {
        try {
            $_fb_sql = "SELECT `{$_v392_subIdCol}` AS sid 
                        FROM `{$_v392_junctionTable}` 
                        WHERE `{$_v392_newsIdCol}` = ? 
                        ORDER BY `{$_v392_subIdCol}` ASC 
                        LIMIT 1";
            $_fb_stmt = $pdo->prepare($_fb_sql);
            $_fb_stmt->execute([$_nid]);
            $_fb_row = $_fb_stmt->fetch(PDO::FETCH_ASSOC);
            if ($_fb_row && !empty($_fb_row['sid'])) {
                $_sid = (int)$_fb_row['sid'];
                if (isset($_v392_subNameMap[$_sid])) {
                    $ncSubMap[$_nid] = $_v392_subNameMap[$_sid];
                    return $_v392_subNameMap[$_sid];
                }
            }
        } catch (Exception $_fb_e) {}
    }

    return '';
};


// Categories — try sidebar first, fallback to nav panel (kn-pl-link) if sidebar fails
// Both sources use en_label() so they are language-aware.
$categories = [];

// ★ Source 1: sidebar (.sb-ci items)
if (preg_match_all('#<li class="sb-ci[^"]*"[^>]*>\s*<a href="([^"]+)"[^>]*>\s*<i class="fas ([^"]+)"[^>]*></i>\s*<span[^>]*>([^<]+)</span>#u', $html, $cm)) {
    foreach ($cm[1] as $idx => $href) {
        $categories[] = ['href' => $href, 'icon' => $cm[2][$idx], 'name' => html_entity_decode(trim($cm[3][$idx]), ENT_QUOTES, 'UTF-8')];
    }
}

// ★ Source 2 (fallback): nav panel (.kn-pl-link items) — uses data-pcat + kpi icon + kpn text
// Some templates have empty sidebar but populated panel.
if (empty($categories) && preg_match_all('#<div class="kn-pl-link[^"]*"[^>]*data-pcat="([^"]+)"[^>]*>\s*<div class="kpi"><i class="fas ([^"]+)"></i></div>\s*<a href="([^"]+)"[^>]*class="kpn"[^>]*>([^<]+)</a>#u', $html, $cm)) {
    foreach ($cm[1] as $idx => $catName) {
        $categories[] = ['href' => $cm[3][$idx], 'icon' => $cm[2][$idx], 'name' => html_entity_decode(trim($cm[4][$idx]), ENT_QUOTES, 'UTF-8')];
    }
}

// ★ Source 3 (last resort): just nav inline cat links (.kn-link)
if (empty($categories) && preg_match_all('#<a href="\?cat=([^"]+)"[^>]*class="kn-link"[^>]*>\s*<i class="fas ([^"]+)"[^>]*></i>\s*([^<]+)</a>#u', $html, $cm)) {
    foreach ($cm[1] as $idx => $catNameEncoded) {
        $catName = urldecode($catNameEncoded);
        $categories[] = ['href' => '?cat=' . $catNameEncoded, 'icon' => $cm[2][$idx], 'name' => $catName];
    }
}

// ★ Source 4 (super last resort): hardcoded category list (will not be language-aware,
//    but at least panel won't be empty)
if (empty($categories)) {
    $fallbackCats = $isEnglish ? [
        ['name'=>'Headlines','icon'=>'fa-fire','href'=>'?cat=Headlines'],
        ['name'=>'National','icon'=>'fa-flag','href'=>'?cat=National'],
        ['name'=>'Politics','icon'=>'fa-landmark','href'=>'?cat=Politics'],
        ['name'=>'International','icon'=>'fa-globe','href'=>'?cat=International'],
        ['name'=>'Economy','icon'=>'fa-chart-line','href'=>'?cat=Economy'],
        ['name'=>'Sports','icon'=>'fa-futbol','href'=>'?cat=Sports'],
        ['name'=>'Entertainment','icon'=>'fa-film','href'=>'?cat=Entertainment'],
        ['name'=>'Education','icon'=>'fa-graduation-cap','href'=>'?cat=Education'],
        ['name'=>'Technology','icon'=>'fa-microchip','href'=>'?cat=Technology'],
        ['name'=>'Health','icon'=>'fa-heart-pulse','href'=>'?cat=Health'],
        ['name'=>'Special News','icon'=>'fa-star','href'=>'?cat=Special News'],
        ['name'=>'Lifestyle','icon'=>'fa-spa','href'=>'?cat=Lifestyle'],
        ['name'=>'Religion','icon'=>'fa-mosque','href'=>'?cat=Religion'],
        ['name'=>'Culture','icon'=>'fa-masks-theater','href'=>'?cat=Culture'],
        ['name'=>'Opinion','icon'=>'fa-comment-dots','href'=>'?cat=Opinion'],
        ['name'=>'Crime','icon'=>'fa-gavel','href'=>'?cat=Crime'],
        ['name'=>'Agriculture','icon'=>'fa-seedling','href'=>'?cat=Agriculture'],
        ['name'=>'Travel','icon'=>'fa-plane','href'=>'?cat=Travel'],
        ['name'=>'Jobs','icon'=>'fa-briefcase','href'=>'?cat=Jobs'],
        ['name'=>'Archive','icon'=>'fa-clock-rotate-left','href'=>'?cat=Archive'],
    ] : [
        ['name'=>'প্রধান খবর','icon'=>'fa-fire','href'=>'?cat=' . urlencode('প্রধান খবর')],
        ['name'=>'জাতীয়','icon'=>'fa-flag','href'=>'?cat=' . urlencode('জাতীয়')],
        ['name'=>'রাজনীতি','icon'=>'fa-landmark','href'=>'?cat=' . urlencode('রাজনীতি')],
        ['name'=>'আন্তর্জাতিক','icon'=>'fa-globe','href'=>'?cat=' . urlencode('আন্তর্জাতিক')],
        ['name'=>'অর্থনীতি','icon'=>'fa-chart-line','href'=>'?cat=' . urlencode('অর্থনীতি')],
        ['name'=>'খেলাধুলা','icon'=>'fa-futbol','href'=>'?cat=' . urlencode('খেলাধুলা')],
        ['name'=>'বিনোদন','icon'=>'fa-film','href'=>'?cat=' . urlencode('বিনোদন')],
        ['name'=>'শিক্ষা','icon'=>'fa-graduation-cap','href'=>'?cat=' . urlencode('শিক্ষা')],
        ['name'=>'প্রযুক্তি','icon'=>'fa-microchip','href'=>'?cat=' . urlencode('প্রযুক্তি')],
        ['name'=>'স্বাস্থ্য','icon'=>'fa-heart-pulse','href'=>'?cat=' . urlencode('স্বাস্থ্য')],
        ['name'=>'বিশেষ সংবাদ','icon'=>'fa-star','href'=>'?cat=' . urlencode('বিশেষ সংবাদ')],
        ['name'=>'লাইফস্টাইল','icon'=>'fa-spa','href'=>'?cat=' . urlencode('লাইফস্টাইল')],
        ['name'=>'ধর্ম','icon'=>'fa-mosque','href'=>'?cat=' . urlencode('ধর্ম')],
        ['name'=>'সংস্কৃতি','icon'=>'fa-masks-theater','href'=>'?cat=' . urlencode('সংস্কৃতি')],
        ['name'=>'মতামত','icon'=>'fa-comment-dots','href'=>'?cat=' . urlencode('মতামত')],
        ['name'=>'ক্রাইম','icon'=>'fa-gavel','href'=>'?cat=' . urlencode('ক্রাইম')],
        ['name'=>'কৃষি','icon'=>'fa-seedling','href'=>'?cat=' . urlencode('কৃষি')],
        ['name'=>'ভ্রমণ','icon'=>'fa-plane','href'=>'?cat=' . urlencode('ভ্রমণ')],
        ['name'=>'চাকরি','icon'=>'fa-briefcase','href'=>'?cat=' . urlencode('চাকরি')],
        ['name'=>'আর্কাইভ','icon'=>'fa-clock-rotate-left','href'=>'?cat=' . urlencode('আর্কাইভ')],
    ];
    $categories = $fallbackCats;
}

// Breaking news items
$breakingItems = [];
// ★ v2.4 FIX: আগে শুধু `<span>` খুঁজতাম — ফলে page-এর সব span এসে যেত (ভুল টাইটেল)।
//    এখন শুধু `.breaking-bar` এর ভেতরের spans extract করি — সঠিক ব্রেকিং আইটেম।
if (preg_match('#<div class="breaking-bar"[^>]*>(.*?)</div>\s*(?:<!--|$|<div class="nse-bar"|<header|<div class="container)#us', $html, $bt)) {
    $barContent = $bt[1];
    if (preg_match_all('#<span[^>]*>([^<]+)</span>#u', $barContent, $bm)) {
        foreach ($bm[1] as $b) {
            $t = trim(html_entity_decode($b, ENT_QUOTES, 'UTF-8'));
            // Skip empty or very short strings (likely UI labels, not breaking news)
            if (!empty($t) && mb_strlen($t, 'UTF-8') > 8) $breakingItems[] = $t;
        }
        // Dedupe (index.php duplicates items for marquee — items appear twice)
        $breakingItems = array_values(array_unique($breakingItems));
    }
}

// Popular posts
$popularPosts = [];
if (preg_match_all('#<a href="([^"]+)"\s+class="pop-item"[^>]*>\s*<img src="([^"]+)"[^>]*>\s*<div class="pi-info">\s*<div class="pi-t"[^>]*>([^<]+)</div>\s*<div class="pi-m"[^>]*>([^<]+)</div>#u', $html, $pm)) {
    foreach ($pm[1] as $i => $href) {
        $popularPosts[] = ['href' => $href, 'img' => $pm[2][$i], 'title' => html_entity_decode(trim($pm[3][$i]), ENT_QUOTES, 'UTF-8'), 'time' => trim($pm[4][$i])];
    }
}

// Popular tags
$popularTags = [];
if (preg_match_all('#<a href="([^"]+)"\s+class="tag-pill"[^>]*>\s*<i class="fas fa-hashtag"></i>\s*([^<]+)</a>#u', $html, $tm)) {
    foreach ($tm[1] as $i => $href) {
        $popularTags[] = ['href' => $href, 'name' => trim($tm[2][$i])];
    }
}

// ★ v3.45: Build unified category list using REAL database counts from index.php.
//    $dbCatPostCounts, $dbCatSubcats, $dbSubPostCounts, $dbNavCatIcons are saved
//    from index.php's scope (before extraction overwrites $categories).
//    This gives accurate total counts per category + subcategory counts —
//    no AJAX needed, instant display, 100% accurate.
$catList = [];
foreach ($dbCategories as $cat) {
    $catName = $cat['name'];  // Bengali name from DB
    $catList[] = [
        'name'        => $catName,
        'displayName' => function_exists('en_label') ? en_label($catName) : $catName,
        'href'        => '?cat=' . urlencode($catName),
        'icon'        => $dbNavCatIcons[$catName] ?? 'fa-folder',
        'count'       => (int)($dbCatPostCounts[$catName] ?? 0),
        'subs'        => $dbCatSubcats[$catName] ?? [],
    ];
}

// ★ v3.100: USER REQUEST — "Video" category already exists in index.php.
//    Ensure it ALWAYS appears in the footer hamburger menu (the floating
//    FAB button near the footer that opens the sidebar). Both the home-page
//    sidebar AND the single-article sidebar render from this $catList, so
//    adding it here covers both. Dedup check avoids double entry when
//    index.php's HTML extraction already picked it up.
//
// ★ v3.101 FIX: Videos uploaded from admin_dashboard.php were not showing
//    because the href was hardcoded to '?cat=Video' (English), but the DB
//    category may be stored as 'ভিডিও' (Bengali) or another variant. Now we
//    DETECT the actual category name from the DB and use it for the href,
//    so the category page query matches correctly.
$_videoAlreadyInList = false;
foreach ($catList as $_vclItem) {
    $_vclLower = strtolower(trim($_vclItem['name']));
    if ($_vclLower === 'video' || $_vclItem['name'] === 'ভিডিও' || $_vclLower === 'videos' || $_vclItem['name'] === 'ভিডিওসমূহ') {
        $_videoAlreadyInList = true;
        break;
    }
}
if (!$_videoAlreadyInList) {
    // ★ v3.101: Detect the ACTUAL Video category name from the DB.
    //    Tries exact English + Bengali matches first, then case-insensitive
    //    LIKE matches. This ensures the href uses whatever name the admin
    //    actually created (e.g., 'ভিডিও' or 'Video' or 'Videos').
    $_videoActualCatName = '';
    if (isset($pdo)) {
        try {
            @$pdo->exec("SET NAMES utf8mb4");
            // Exact matches first (fastest, most accurate)
            $_videoProbeSql = "SELECT name FROM categories
                               WHERE name IN ('Video', 'ভিডিও', 'Videos', 'ভিডিওসমূহ', 'video', 'videos')
                               LIMIT 1";
            $_videoProbeRow = $pdo->query($_videoProbeSql)->fetch(PDO::FETCH_ASSOC);
            if ($_videoProbeRow && !empty($_videoProbeRow['name'])) {
                $_videoActualCatName = $_videoProbeRow['name'];
            } else {
                // Case-insensitive / partial match fallback
                $_videoProbeSql2 = "SELECT name FROM categories
                                    WHERE LOWER(name) LIKE '%video%'
                                       OR name LIKE '%ভিডিও%'
                                    LIMIT 1";
                $_videoProbeRow2 = $pdo->query($_videoProbeSql2)->fetch(PDO::FETCH_ASSOC);
                if ($_videoProbeRow2 && !empty($_videoProbeRow2['name'])) {
                    $_videoActualCatName = $_videoProbeRow2['name'];
                }
            }
        } catch (Exception $_ve) {}
    }

    // Use actual DB name if found; otherwise default to 'Video'.
    // The href MUST use the DB name so the category-page query (c.name = ?)
    // matches. The display label stays bilingual for the UI.
    $_videoCatNameForHref = ($_videoActualCatName !== '') ? $_videoActualCatName : 'Video';

    // ★ Try to use the real DB count/subs if index.php exposed them under any common key.
    $_videoDbCount = 0;
    foreach (['Video', 'ভিডিও', 'Videos', 'ভিডিওসমূহ'] as $_videoKeyProbe) {
        if (isset($dbCatPostCounts[$_videoKeyProbe])) {
            $_videoDbCount = (int)$dbCatPostCounts[$_videoKeyProbe];
            break;
        }
    }
    // ★ v3.101: If index.php didn't expose a count, count news directly from DB
    //    using the actual category name we just detected.
    if ($_videoDbCount === 0 && $_videoActualCatName !== '' && isset($pdo)) {
        try {
            $_videoCountStmt = $pdo->prepare("SELECT COUNT(*) FROM news n
                                              LEFT JOIN categories c ON n.category_id = c.id
                                              WHERE n.status = 'published'
                                                AND n.created_at <= NOW()
                                                AND c.name = ?");
            $_videoCountStmt->execute([$_videoActualCatName]);
            $_videoDbCount = (int)$_videoCountStmt->fetchColumn();
        } catch (Exception $_vce) {}
    }
    // ★ v3.105 FIX: Also count videos from the dedicated `videos` table.
    //    admin_dashboard.php uploads videos to a separate `videos` table (id, title,
    //    code, created_at) — these are NOT in the `news` table. So the news count
    //    above returns 0 even though videos exist. We add the videos table count
    //    so the sidebar shows the REAL total (news videos + videos table entries).
    if (isset($pdo)) {
        try {
            // First check if the videos table exists
            $_videoTableExists = $pdo->query("SHOW TABLES LIKE '%video%'")->fetchColumn();
            if ($_videoTableExists) {
                $_videoTableCountStmt = $pdo->query("SELECT COUNT(*) FROM `{$_videoTableExists}`");
                $_videoTableCount = (int)$_videoTableCountStmt->fetchColumn();
                $_videoDbCount += $_videoTableCount;
            }
        } catch (Exception $_vtce) {}
    }
    $_videoDbSubs = [];
    foreach (['Video', 'ভিডিও', 'Videos', 'ভিডিওসমূহ'] as $_videoSubProbe) {
        if (isset($dbCatSubcats[$_videoSubProbe]) && !empty($dbCatSubcats[$_videoSubProbe])) {
            $_videoDbSubs = $dbCatSubcats[$_videoSubProbe];
            break;
        }
    }
    // Bengali label for Video category (matches index.php's bilingual approach)
    $_videoDisplayLabel = $isEnglish ? 'Video' : 'ভিডিও';
    // ★ v3.106: Link to ?page=videos (index.php's dedicated video page), NOT ?cat=Video.
    //    index.php renders videos from the `videos` table at ?page=videos with embedded
    //    iframes in a grid layout. The old ?cat=Video link showed nothing because videos
    //    aren't in the `news` table.
    $catList[] = [
        'name'        => 'videos_page',  // Internal identifier (not a DB category name)
        'displayName' => $_videoDisplayLabel,
        'href'        => '?page=videos',
        'icon'        => 'fa-video',
        'count'       => $_videoDbCount,
        'subs'        => $_videoDbSubs,
    ];
}

// ★ Single article extraction (for new single-page UI)
// Extract title, image, headline, content, meta, tags, related news from index.php's HTML.
$article = [
    'title'    => '',
    'category' => '',
    'catHref'  => '',
    'subCat'   => '',
    'subHref'  => '',
    'image'    => '',
    'headline' => '',
    'date'     => '',
    'author'   => 'সংবাদ সংকলন',
    'tags'     => [],
    'content'  => '',
    'shareUrl' => '',
    'related'  => [],
];

if ($isSinglePage) {
    // Title
    if (preg_match('#<h1 class="news-title"[^>]*>(.*?)</h1>#us', $html, $tm)) {
        $article['title'] = trim(html_entity_decode(strip_tags($tm[1]), ENT_QUOTES, 'UTF-8'));
    }
    // Category badge (first .cb link)
    // ★ v3.83: Regex made flexible — matches class="cb" AND class="cb sub" / class="cb ...".
    //    Previously only exact class="cb" matched, so subcategory badges with extra
    //    classes (e.g. class="cb sub") were never extracted.
    if (preg_match('#<a\s+[^>]*href="([^"]+)"[^>]*class="cb[^"]*"[^>]*>([^<]+)</a>#u', $html, $cm)) {
        $article['catHref'] = $cm[1];
        $article['category'] = trim($cm[2]);
    }
    // Subcategory (second .cb link)
    if (preg_match_all('#<a\s+[^>]*href="([^"]+)"[^>]*class="cb[^"]*"[^>]*>([^<]+)</a>#u', $html, $cmAll)) {
        if (count($cmAll[1]) >= 2) {
            $article['subHref'] = $cmAll[1][1];
            $article['subCat']  = trim($cmAll[2][1]);
        }
    }
    // Headline (lead paragraph)
    if (preg_match('#<div class="single-headline"[^>]*>(.*?)</div>#us', $html, $hm)) {
        $article['headline'] = trim(html_entity_decode(strip_tags($hm[1]), ENT_QUOTES, 'UTF-8'));
    }
    // Image
    if (preg_match('#<div class="single-img"[^>]*>\s*<img[^>]+src="([^"]+)"#u', $html, $im)) {
        $article['image'] = $im[1];
    }
    // Date (in single-meta)
    if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $html, $dm)) {
        $article['date'] = trim($dm[1]);
    }
    // Tags — extract ONLY from single-tags section's tag-pill anchors
    // ★ v3.20: আগে single-meta থেকে tags extract করতাম — কিন্তু সেখানে bn_to_en()
    // ফাংশন tags কে পুরো প্যারাগ্রাফে পরিণত করত (ভুল translation)। এখন শুধু
    // single-tags section এর tag-pill anchor text গুলো extract করি — সঠিক tags।
    // Method 1: From single-tags section — extract tag-pill anchors (most reliable)
    if (preg_match('#<div class="single-tags"[^>]*>(.*?)</div>\s*</article>#us', $html, $stBlock)) {
        $tagsHtml = $stBlock[1];
        if (preg_match_all('#<a[^>]*class="tag-pill"[^>]*>\s*<i class="fas fa-hashtag"></i>\s*([^<]+)</a>#u', $tagsHtml, $tagMatches)) {
            foreach ($tagMatches[1] as $tagText) {
                $tag = trim(html_entity_decode($tagText, ENT_QUOTES, 'UTF-8'));
                // Only accept reasonable tag names (1-50 chars, no full sentences)
                if ($tag && mb_strlen($tag, 'UTF-8') <= 50) {
                    $article['tags'][] = $tag;
                }
            }
        }
    }
    // ★ v3.81: Method 2 (broad fallback) REMOVED — it was scanning the entire
    //    HTML and picking up unrelated tag-pills from the sidebar / popular tags
    //    section, causing wrong tags to appear on articles that have no tags of
    //    their own. Now tags ONLY come from the article's own <div class="single-tags">
    //    section (Method 1 above). If that section is missing or empty, the article
    //    simply has no tags, and the "ট্যাগ" label will show with a blank area below.
    // Clean up tags — remove duplicates, empty values, and trim
    if (!empty($article['tags'])) {
        $article['tags'] = array_values(array_filter(array_map('trim', $article['tags']), function($t) {
            return $t !== '' && $t !== null && mb_strlen($t, 'UTF-8') <= 50;
        }));
        // Remove duplicates (case-insensitive)
        $seen = [];
        $unique = [];
        foreach ($article['tags'] as $t) {
            $key = mb_strtolower($t, 'UTF-8');
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $unique[] = $t;
            }
        }
        $article['tags'] = $unique;
    }
    // Share URL
    if (preg_match('#var shareUrl = "([^"]+)"#', $html, $sum)) {
        $article['shareUrl'] = $sum[1];
    } elseif (preg_match('#u=https?%3A%2F%2F([^&"]+)#', $html, $sum2)) {
        $article['shareUrl'] = 'https://' . urldecode($sum2[1]);
    }
    // Content (everything inside <div class="single-content">)
    // ★ v3.30: Stop content extraction at single-tags — don't include tags in content at all.
    // This prevents tags from leaking into the article body.
    if (preg_match('#<div class="single-content"[^>]*>(.*)#us', $html, $cm)) {
        $restHtml = $cm[1];
        // ★ v3.30: First try to stop at single-tags div (before </article>)
        if (preg_match('#(.*?)<div class="single-tags"#us', $restHtml, $cm3)) {
            $article['content'] = trim($cm3[1]);
        } elseif (preg_match('#(.*?)</article>#us', $restHtml, $cm2)) {
            $article['content'] = trim($cm2[1]);
        } elseif (preg_match('#(.*?)<div class="rel-sec"#us', $restHtml, $cm4)) {
            $article['content'] = trim($cm4[1]);
        } else {
            $article['content'] = $restHtml;
        }
        // Remove trailing </div> tags that belong to single-content wrapper
        // (the content should end with </p> or other content tag, not </div>)
        $article['content'] = preg_replace('#\s*</div>\s*$#u', '', $article['content']);
        // ★ v3.1: Remove ad blocks (rendered by renderAd() inside content)
        // এগুলো article এর মধ্যে inject হওয়া sponsored content — app এ দরকার নেই।
        $article['content'] = preg_replace('#<div class="ad-wrapper"[^>]*>.*?</div>\s*<!--\s*/ad-wrapper\s*-->#us', '', $article['content']);
        $article['content'] = preg_replace('#<div class="ad-wrapper"[^>]*>.*?</div>#us', '', $article['content']);
        $article['content'] = preg_replace('#<div class="ad-block"[^>]*>.*?</div>\s*</div>#us', '', $article['content']);
        $article['content'] = preg_replace('#<div class="ad-label">[^<]*</div>#us', '', $article['content']);

        // ★ v3.18: Tags now extracted separately and shown in a stylish section below content.
        // We DO NOT strip single-tags from content anymore — instead we extract
        // the tags and remove the HTML wrapper, keeping only the tag data.
        // Remove the single-tags DIV wrapper but keep the tag-pill anchors for extraction.
        // (Tags are extracted via $article['tags'] above, so we just remove the HTML rendering.)
        $article['content'] = preg_replace('#<div class="single-tags"[^>]*>.*?</div>\s*</div>\s*$#us', '', $article['content']);
        // Remove any leftover tag-pill HTML from content (they'll be rendered separately below)
        $article['content'] = preg_replace('#<div>\s*<span[^>]*><i class="fas fa-tags"></i>[^<]*</span>.*?</div>\s*$#us', '', $article['content']);

        // ★ v3.3: STRIP all inline "style" attributes from article content.
        // News editors often use WYSIWYG editors that add inline styles like
        // background: linear-gradient(red...); — these create unwanted pink/red
        // callout boxes in our clean app UI. Remove all inline styles, keep
        // semantic content (paragraphs, lists, headings, images, iframes).
        $article['content'] = preg_replace('#\s+style="[^"]*"#iu', '', $article['content']);
        // Also strip class attributes — simpler regex (no callback for performance)
        // Keep news-video-wrapper and twitter-embed-wrapper, strip everything else
        $article['content'] = preg_replace('#<div\s+([^>]*class="news-video-wrapper"[^>]*)>#u', '<div class="news-video-wrapper">', $article['content']);
        $article['content'] = preg_replace('#<div\s+([^>]*class="twitter-embed-wrapper"[^>]*)>#u', '<div class="twitter-embed-wrapper">', $article['content']);
        // Strip all attributes from other divs
        $article['content'] = preg_replace('#<div\s+[^>]*>#u', '<div>', $article['content']);
        // Strip class attributes from p, span, section (keep them as plain elements)
        $article['content'] = preg_replace('#<(p|span|section)\s+[^>]*>#iu', '<$1>', $article['content']);
    }

    // ★ v3.67: Force translation — custom translator that doesn't depend on bn_to_en()
    //    Uses cURL + file_get_contents fallback + same cache as bn_to_en()
    if ($isEnglish && !empty($article['content'])) {
        if (preg_match('/[\x{0980}-\x{09FF}]/u', $article['content'])) {

            // Custom translation function — independent of index.php's bn_to_en()
            $forceTranslate = function($text) {
                $text = trim($text);
                if (empty($text) || !preg_match('/[\x{0980}-\x{09FF}]/u', $text)) return $text;

                // Cache check (same cache dir as bn_to_en)
                $cacheDir = __DIR__ . '/cache_tr';
                if (!is_dir($cacheDir)) @mkdir($cacheDir, 0775, true);
                $cacheKey = 'tr_' . hash('sha256', $text);
                $cacheFile = $cacheDir . '/' . $cacheKey . '.txt';
                if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < 86400 * 30)) {
                    $cached = @file_get_contents($cacheFile);
                    if ($cached !== false && $cached !== '' && !preg_match('/[\x{0980}-\x{09FF}]/u', $cached)) return $cached;
                }

                // Split into chunks (Google limit ~1500 chars)
                $chunks = [];
                if (mb_strlen($text, 'UTF-8') > 1400) {
                    $sentences = preg_split('/(?<=[.!?।॥])\s+/u', $text);
                    $cur = '';
                    foreach ($sentences as $s) {
                        if (mb_strlen($cur . ' ' . $s, 'UTF-8') > 1400) {
                            if ($cur !== '') $chunks[] = $cur;
                            $cur = $s;
                        } else { $cur = $cur === '' ? $s : $cur . ' ' . $s; }
                    }
                    if ($cur !== '') $chunks[] = $cur;
                } else { $chunks[] = $text; }

                $translated = '';
                foreach ($chunks as $chunk) {
                    $url = 'https://translate.google.com/translate_a/single?client=gtx&sl=bn&tl=en&dt=t&q=' . rawurlencode($chunk);
                    $chunkTrans = '';

                    // Method 1: cURL
                    if (function_exists('curl_init') && empty($chunkTrans)) {
                        try {
                            $ch = @curl_init();
                            if ($ch !== false) {
                                @curl_setopt_array($ch, [
                                    CURLOPT_URL => $url,
                                    CURLOPT_RETURNTRANSFER => true,
                                    CURLOPT_SSL_VERIFYPEER => false,
                                    CURLOPT_TIMEOUT => 6,
                                    CURLOPT_CONNECTTIMEOUT => 4,
                                    CURLOPT_FOLLOWLOCATION => true,
                                    CURLOPT_HTTPHEADER => ['User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36', 'Accept: application/json'],
                                ]);
                                $resp = @curl_exec($ch);
                                $code = @curl_getinfo($ch, CURLINFO_HTTP_CODE);
                                @curl_close($ch);
                                if ($code === 200 && !empty($resp)) {
                                    $data = json_decode($resp, true);
                                    if (is_array($data) && isset($data[0])) {
                                        foreach ($data[0] as $pair) {
                                            if (is_array($pair) && isset($pair[0]) && is_string($pair[0])) $chunkTrans .= $pair[0];
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) {}
                    }

                    // Method 2: file_get_contents (fallback if cURL fails)
                    if (empty($chunkTrans) && ini_get('allow_url_fopen')) {
                        try {
                            $ctx = stream_context_create(['http' => [
                                'timeout' => 6,
                                'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64)\r\nAccept: application/json\r\n"
                            ], 'ssl' => ['verify_peer' => false, 'verify_peer_name' => false]]);
                            $resp = @file_get_contents($url, false, $ctx);
                            if (!empty($resp)) {
                                $data = json_decode($resp, true);
                                if (is_array($data) && isset($data[0])) {
                                    foreach ($data[0] as $pair) {
                                        if (is_array($pair) && isset($pair[0]) && is_string($pair[0])) $chunkTrans .= $pair[0];
                                    }
                                }
                            }
                        } catch (Exception $e) {}
                    }

                    $translated .= ($chunkTrans !== '' ? $chunkTrans : $chunk) . ' ';
                }

                $translated = trim($translated);
                if (!empty($translated) && !preg_match('/[\x{0980}-\x{09FF}]/u', $translated)) {
                    @file_put_contents($cacheFile, $translated, LOCK_EX);
                    return $translated;
                }
                return $text; // fallback
            };

            // Translate each text node in HTML (preserve tags)
            $article['content'] = preg_replace_callback('#>([^<]+)<#u', function($m) use ($forceTranslate) {
                $text = trim($m[1]);
                if (empty($text) || !preg_match('/[\x{0980}-\x{09FF}]/u', $text)) {
                    return '>' . $m[1] . '<';
                }
                $result = $forceTranslate($text);
                return '>' . $result . '<';
            }, $article['content']);
        }
    }
    // Related news (.nc cards inside .rel-sec)
    // ★ v3.1 FIX: আগের regex শুধু প্রথম </div> পর্যন্ত নিত — এখন rel-grid এর পুরো content capture করি
    if (preg_match('#<div class="rel-sec"[^>]*>(.*?)(?:</article>|<div class="ad-bottom-sec"|$)#us', $html, $rm)) {
        $relBlock = $rm[1];
        if (preg_match_all('#<a href="([^"]+)"\s+class="nc"[^>]*>(.*?)</a>#us', $relBlock, $relMatches)) {
            foreach ($relMatches[1] as $idx => $relHref) {
                $relBlockItem = $relMatches[2][$idx];
                $relImg = $relTitle = $relCat = $relTime = '';
                if (preg_match('#<img[^>]+src="([^"]+)"#u', $relBlockItem, $rim)) $relImg = $rim[1];
                if (preg_match('#<h3[^>]*>([^<]+)</h3>#u', $relBlockItem, $rtm)) $relTitle = html_entity_decode(trim($rtm[1]), ENT_QUOTES, 'UTF-8');
                if (preg_match('#<div class="nc-cat"[^>]*>([^<]+)</div>#u', $relBlockItem, $rcm)) $relCat = trim($rcm[1]);
                if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $relBlockItem, $rtm2)) $relTime = trim($rtm2[1]);
                if ($relTitle) {
                    $article['related'][] = [
                        'href' => $relHref, 'img' => $relImg, 'title' => $relTitle,
                        'cat' => $relCat, 'time' => $relTime,
                    ];
                }
            }
        }
    }
}

// ★ v3.101 FIX 3: Single-page video fallback.
//    If article extraction failed (empty title) AND the URL has a video ID
//    (either ?vid=X from the category page, or ?id=X that doesn't exist in
//    news), try loading the video from the dedicated `videos` table and
//    populate $article so the normal single-page renderer displays it with
//    an embedded video player.
if ($isSinglePage && empty($article['title']) && isset($pdo)) {
    // Determine which ID to look up: ?vid takes priority, then ?id
    $_vidLookupId = 0;
    if (isset($_GET['vid']) && (int)$_GET['vid'] > 0) {
        $_vidLookupId = (int)$_GET['vid'];
    } elseif (isset($_GET['id']) && (int)$_GET['id'] > 0) {
        $_vidLookupId = (int)$_GET['id'];
    }

    if ($_vidLookupId > 0) {
        try {
            @$pdo->exec("SET NAMES utf8mb4");
            @$_vidDb = $pdo->query("SELECT DATABASE()")->fetchColumn();

            if ($_vidDb) {
                // Discover the videos table
                $_vidFindTableSql = "SELECT TABLE_NAME FROM information_schema.TABLES
                                     WHERE TABLE_SCHEMA = " . $pdo->quote($_vidDb) . "
                                       AND TABLE_NAME LIKE '%video%'
                                     ORDER BY
                                       CASE
                                         WHEN TABLE_NAME = 'videos' THEN 1
                                         WHEN TABLE_NAME = 'video' THEN 2
                                         WHEN TABLE_NAME = 'video_uploads' THEN 3
                                         ELSE 4
                                       END,
                                       TABLE_NAME";
                $_vidTableRows = $pdo->query($_vidFindTableSql)->fetchAll(PDO::FETCH_ASSOC);
                $_vidTable = '';
                if (!empty($_vidTableRows)) {
                    $_vidTable = $_vidTableRows[0]['TABLE_NAME'];
                }

                if ($_vidTable !== '') {
                    // Discover columns
                    $_vidColSql = "SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.COLUMNS
                                   WHERE TABLE_SCHEMA = " . $pdo->quote($_vidDb) . "
                                     AND TABLE_NAME = " . $pdo->quote($_vidTable);
                    $_vidCols = $pdo->query($_vidColSql)->fetchAll(PDO::FETCH_ASSOC);

                    $_vidColId = 'id';
                    $_vidColTitle = '';
                    $_vidColVideo = '';
                    $_vidColThumb = '';
                    $_vidColDate = '';
                    $_vidColDesc = '';
                    $_vidColNewsId = '';

                    foreach ($_vidCols as $_vidc) {
                        $_vidcn = strtolower($_vidc['COLUMN_NAME']);
                        if ($_vidcn === 'id' || $_vidcn === 'video_id' || $_vidcn === 'vid') {
                            $_vidColId = $_vidc['COLUMN_NAME'];
                        }
                        if (in_array($_vidcn, ['title', 'name', 'heading', 'video_title', 'caption', 'subject'])) {
                            $_vidColTitle = $_vidc['COLUMN_NAME'];
                        }
                        // ★ v3.104: Added 'code', 'embed_code', 'iframe' — admin_dashboard.php uses `code` column
                        if (in_array($_vidcn, ['video', 'video_url', 'url', 'file', 'file_url', 'video_file', 'source', 'src', 'embed', 'embed_url', 'embed_code', 'code', 'youtube_url', 'link', 'iframe'])) {
                            $_vidColVideo = $_vidc['COLUMN_NAME'];
                        }
                        if (in_array($_vidcn, ['thumbnail', 'thumb', 'image', 'img', 'poster', 'cover', 'picture', 'photo', 'thumbnail_url', 'image_url'])) {
                            $_vidColThumb = $_vidc['COLUMN_NAME'];
                        }
                        if (in_array($_vidcn, ['created_at', 'uploaded_at', 'date', 'timestamp', 'created', 'upload_date', 'added_at', 'post_date'])) {
                            $_vidColDate = $_vidc['COLUMN_NAME'];
                        }
                        if (in_array($_vidcn, ['description', 'desc', 'content', 'details', 'summary', 'body'])) {
                            $_vidColDesc = $_vidc['COLUMN_NAME'];
                        }
                        if (in_array($_vidcn, ['news_id', 'article_id', 'post_id', 'news_link'])) {
                            $_vidColNewsId = $_vidc['COLUMN_NAME'];
                        }
                    }

                    // Build and run the query
                    $_vidSelParts = ["`{$_vidColId}` AS vid"];
                    if ($_vidColTitle !== '') $_vidSelParts[] = "`{$_vidColTitle}` AS vtitle";
                    if ($_vidColVideo !== '') $_vidSelParts[] = "`{$_vidColVideo}` AS vurl";
                    if ($_vidColThumb !== '') $_vidSelParts[] = "`{$_vidColThumb}` AS vthumb";
                    if ($_vidColDate !== '') $_vidSelParts[] = "`{$_vidColDate}` AS vdate";
                    if ($_vidColDesc !== '') $_vidSelParts[] = "`{$_vidColDesc}` AS vdesc";
                    if ($_vidColNewsId !== '') $_vidSelParts[] = "`{$_vidColNewsId}` AS vnewsid";

                    $_vidSelSql = "SELECT " . implode(', ', $_vidSelParts) . " FROM `{$_vidTable}` WHERE `{$_vidColId}` = ? LIMIT 1";
                    $_vidStmt = $pdo->prepare($_vidSelSql);
                    $_vidStmt->execute([$_vidLookupId]);
                    $_vidRow = $_vidStmt->fetch(PDO::FETCH_ASSOC);

                    if ($_vidRow && !empty($_vidRow['vtitle'])) {
                        // If there's a linked news_id, redirect attention there by loading
                        // the news article instead (so related news, tags, etc. work).
                        if (!empty($_vidRow['vnewsid']) && (int)$_vidRow['vnewsid'] > 0) {
                            // Re-include index.php would be heavy; instead just set the
                            // article fields from the news row directly.
                            $_vidNewsId = (int)$_vidRow['vnewsid'];
                            $_vidNewsStmt = $pdo->prepare("SELECT n.id, n.title, n.image, n.content, n.created_at, c.name as cat_name
                                                           FROM news n LEFT JOIN categories c ON n.category_id = c.id
                                                           WHERE n.id = ? AND n.status = 'published' LIMIT 1");
                            $_vidNewsStmt->execute([$_vidNewsId]);
                            $_vidNewsRow = $_vidNewsStmt->fetch(PDO::FETCH_ASSOC);
                            if ($_vidNewsRow) {
                                $article['title']    = $_vidNewsRow['title'];
                                $article['category'] = $_vidNewsRow['cat_name'] ?? ($isEnglish ? 'Video' : 'ভিডিও');
                                $article['catHref']  = '?cat=' . urlencode($article['category']);
                                $article['image']    = $_vidNewsRow['image'] ?? '';
                                $article['content']  = $_vidNewsRow['content'] ?? '';
                                $article['date']     = function_exists('timeAgo') ? timeAgo($_vidNewsRow['created_at']) : date('d M Y', strtotime($_vidNewsRow['created_at']));
                                $article['author']   = $L['appName'];
                                $article['shareUrl'] = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/?page=single&id=' . $_vidNewsId;
                            }
                        } else {
                            // No linked news article — render the video directly.
                            $article['title']    = $_vidRow['vtitle'];
                            $article['category'] = $isEnglish ? 'Video' : 'ভিডিও';
                            $article['catHref']  = '?cat=' . urlencode($article['category']);

                            // Thumbnail
                            $_vidThumbUrl = '';
                            if (!empty($_vidRow['vthumb'])) {
                                $_vidThumbRaw = trim($_vidRow['vthumb']);
                                if ($_vidThumbRaw !== '' && $_vidThumbRaw !== '0') {
                                    if (preg_match('#^https?://#i', $_vidThumbRaw) || strpos($_vidThumbRaw, '//') === 0) {
                                        $_vidThumbUrl = $_vidThumbRaw;
                                    } else {
                                        while (strpos($_vidThumbRaw, 'uploads/uploads/') === 0) {
                                            $_vidThumbRaw = substr($_vidThumbRaw, 8);
                                        }
                                        if (strpos($_vidThumbRaw, 'uploads/') !== 0 && strpos($_vidThumbRaw, '/') !== 0) {
                                            $_vidThumbUrl = 'uploads/' . $_vidThumbRaw;
                                        } else {
                                            $_vidThumbUrl = $_vidThumbRaw;
                                        }
                                    }
                                }
                            }
                            $article['image'] = $_vidThumbUrl;

                            // Date
                            if (!empty($_vidRow['vdate'])) {
                                $article['date'] = function_exists('timeAgo') ? timeAgo($_vidRow['vdate']) : date('d M Y', strtotime($_vidRow['vdate']));
                            }

                            // Headline (description)
                            if (!empty($_vidRow['vdesc'])) {
                                $article['headline'] = strip_tags($_vidRow['vdesc']);
                            }

                            $article['author'] = $L['appName'];
                            $article['shareUrl'] = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/?page=single&id=' . $_vidLookupId . '&vid=' . $_vidLookupId;

                            // Build video embed as content
                            $_vidVideoUrl = !empty($_vidRow['vurl']) ? trim($_vidRow['vurl']) : '';
                            $_vidEmbedHtml = '';

                            // ★ v3.102 FIX: If no video URL was found in the expected column,
                            //    check if the thumbnail field itself contains a video file
                            //    (admin may have uploaded the .mp4 into the thumbnail/image
                            //    field). Also scan ALL columns for a video-URL-like value.
                            if ($_vidVideoUrl === '') {
                                // Check if thumbnail is actually a video file
                                if (!empty($_vidThumbUrl) && preg_match('#\.(mp4|webm|ogg|mov|m4v)(\?.*)?$#i', $_vidThumbUrl)) {
                                    $_vidVideoUrl = $_vidThumbUrl;
                                } else {
                                    // Scan all columns in this row for a video-URL-like value
                                    foreach ($_vidRow as $_vidScanKey => $_vidScanVal) {
                                        if (in_array($_vidScanKey, ['vid', 'vnewsid'])) continue;
                                        $_vidScanStr = trim((string)$_vidScanVal);
                                        if ($_vidScanStr === '') continue;
                                        // Direct video file
                                        if (preg_match('#\.(mp4|webm|ogg|mov|m4v)(\?.*)?$#i', $_vidScanStr)) {
                                            $_vidVideoUrl = $_vidScanStr;
                                            break;
                                        }
                                        // YouTube/Vimeo/Facebook URL
                                        if (preg_match('#(?:youtube\.com|youtu\.be|vimeo\.com|facebook\.com/.+?/videos)#i', $_vidScanStr)) {
                                            $_vidVideoUrl = $_vidScanStr;
                                            break;
                                        }
                                        // Already an embed HTML
                                        if (preg_match('#^<iframe|<embed|<video#i', $_vidScanStr)) {
                                            $_vidVideoUrl = $_vidScanStr;
                                            break;
                                        }
                                    }
                                }
                            }

                            if ($_vidVideoUrl !== '') {
                                // ★ v3.104: First check if the code contains an <iframe>, <embed>, or <video>
                                //    tag ANYWHERE (admin_dashboard.php stores full embed HTML in `code` column).
                                //    Extract the tag and render it in our video wrapper.
                                if (preg_match('#(<iframe[^>]*>.*?</iframe>|<iframe[^>]*/?>|<video[^>]*>.*?</video>|<video[^>]*/?>|<embed[^>]*>)#is', $_vidVideoUrl, $_vidTagM)) {
                                    $_vidEmbedHtml = '<div class="news-video-wrapper">' . $_vidTagM[1] . '</div>';
                                }
                                // YouTube — convert watch URL to embed
                                elseif (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|v/|shorts/)|youtu\.be/)([A-Za-z0-9_-]{6,15})#', $_vidVideoUrl, $_vidYtM)) {
                                    $_vidYtId = $_vidYtM[1];
                                    $_vidEmbedHtml = '<div class="news-video-wrapper"><iframe src="https://www.youtube.com/embed/' . htmlspecialchars($_vidYtId) . '" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>';
                                }
                                // Vimeo
                                elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $_vidVideoUrl, $_vidVmM)) {
                                    $_vidVmId = $_vidVmM[1];
                                    $_vidEmbedHtml = '<div class="news-video-wrapper"><iframe src="https://player.vimeo.com/video/' . htmlspecialchars($_vidVmId) . '" allowfullscreen></iframe></div>';
                                }
                                // Facebook video (plugin URL or direct video URL)
                                elseif (preg_match('#facebook\.com#i', $_vidVideoUrl)) {
                                    $_vidEmbedHtml = '<div class="news-video-wrapper"><iframe src="https://www.facebook.com/plugins/video.php?href=' . rawurlencode($_vidVideoUrl) . '&show_text=false&width=560&height=400" allowfullscreen scrolling="no" style="border:none;overflow:hidden"></iframe></div>';
                                }
                                // Direct video file (.mp4, .webm, etc.) → use <video> tag
                                elseif (preg_match('#\.(mp4|webm|ogg|mov|m4v)(\?.*)?$#i', $_vidVideoUrl)) {
                                    // ★ v3.102: Use thumbnail as poster only if it's an IMAGE (not a video file)
                                    $_vidPosterAttr = '';
                                    if (!empty($_vidThumbUrl) && !preg_match('#\.(mp4|webm|ogg|mov|m4v)(\?.*)?$#i', $_vidThumbUrl)) {
                                        $_vidPosterAttr = ' poster="' . htmlspecialchars($_vidThumbUrl) . '"';
                                    }
                                    $_vidEmbedHtml = '<div class="news-video-wrapper"><video controls preload="metadata"' . $_vidPosterAttr . '><source src="' . htmlspecialchars($_vidVideoUrl) . '"></video></div>';
                                }
                                // Fallback: plain link
                                else {
                                    $_vidEmbedHtml = '<p><a href="' . htmlspecialchars($_vidVideoUrl) . '" target="_blank" rel="noopener">' . htmlspecialchars($_vidVideoUrl) . '</a></p>';
                                }
                            }

                            // ★ v3.102 FIX: Put VIDEO EMBED FIRST in content (before description).
                            //    The single page shows content in "collapsed" mode — only the
                            //    FIRST child element is visible until user clicks "Read More".
                            //    Previously the description <p> was first, so the video was
                            //    hidden and users only saw text + the thumbnail image above
                            //    (which they reported as "onno kono chobi").
                            //    Now the video embed is FIRST → video player shows immediately.
                            //
                            // ★ v3.102 FIX 2: Clear the thumbnail image when there's a video
                            //    embed, so the <img> block above the content doesn't show a
                            //    static image instead of the video player. The video wrapper
                            //    itself provides the visual.
                            if ($_vidEmbedHtml !== '') {
                                $article['image'] = '';  // No static image — video player replaces it
                                $article['is_video'] = true;  // ★ Flag: don't collapse content for video articles
                            }

                            $_vidContentParts = [];
                            if ($_vidEmbedHtml !== '') {
                                $_vidContentParts[] = $_vidEmbedHtml;  // ★ Video FIRST
                            }
                            if (!empty($_vidRow['vdesc'])) {
                                $_vidContentParts[] = '<p>' . nl2br(htmlspecialchars($_vidRow['vdesc'])) . '</p>';
                            }
                            // If no video embed AND no description, show a placeholder
                            if (empty($_vidContentParts)) {
                                $_vidContentParts[] = '<p style="text-align:center;color:#999;padding:20px;">' . ($isEnglish ? 'Video content not available.' : 'ভিডিও কনটেন্ট পাওয়া যায়নি।') . '</p>';
                            }
                            $article['content'] = implode("\n", $_vidContentParts);
                        }
                    }
                }
            }
        } catch (Exception $_vidE) {
            // Silent fail — the normal "No news found" message will show
        }
    }
}

// ★ v3.103 FIX: Detect video URLs/files in article content and convert to embeds.
//    When admin uploads a video via admin_dashboard.php, the video file path or
//    YouTube link might be stored in the content as plain text (not as an <iframe>
//    or <video> embed). This step scans the content for bare video URLs and
//    converts them to proper video players.
//    Also: if the article `image` field is a video file (.mp4), inject a video
//    player at the TOP of the content so it plays when the user opens the article.
if ($isSinglePage && !empty($article['content'])) {
    // Step 1: Convert bare video URLs in content to embeds
    // Match plain-text video URLs (not already inside <iframe>/<video>/<source>/<a> tags)
    if (preg_match('/[\x{0980}-\x{09FF}]/u', $article['content']) || true) {
        // YouTube URLs as plain text → embed
        $article['content'] = preg_replace_callback(
            '#(?<!["\'>=])(?:https?://)?(?:www\.)?(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([A-Za-z0-9_-]{6,15})#u',
            function($m) {
                return '<div class="news-video-wrapper"><iframe src="https://www.youtube.com/embed/' . $m[1] . '" allowfullscreen allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"></iframe></div>';
            },
            $article['content']
        );
        // Vimeo URLs as plain text → embed
        $article['content'] = preg_replace_callback(
            '#(?<!["\'>=])(?:https?://)?(?:www\.)?vimeo\.com/(?:video/)?(\d{6,15})#u',
            function($m) {
                return '<div class="news-video-wrapper"><iframe src="https://player.vimeo.com/video/' . $m[1] . '" allowfullscreen></iframe></div>';
            },
            $article['content']
        );
        // Direct video file URLs as plain text → <video> tag
        // (matches https://site.com/video.mp4, //site.com/v.mp4, uploads/video.mp4)
        // ★ v3.103b: Narrowed regex — only matches full URLs and uploads/ paths,
        //    NOT random text or HTML attribute values (avoids breaking <img src="...">).
        $article['content'] = preg_replace_callback(
            '#(?<!["\'=>])(?:(?:https?://|//)[^\s<"]+|uploads/[^\s<"]+)\.(mp4|webm|ogg|mov|m4v)(?:\?[^\s<"]*)?(?=["<\s]|$)#iu',
            function($m) {
                $_url = trim($m[0]);
                return '<div class="news-video-wrapper"><video controls preload="metadata"><source src="' . htmlspecialchars($_url) . '"></video></div>';
            },
            $article['content']
        );
    }

    // Step 2: If the article image is a video file AND content doesn't already have
    //    a video embed, inject the video player at the TOP of the content.
    //    (The .art-img div above will ALSO render the video — so to avoid double
    //    players, we clear the image and put the player in content instead.)
    if (!empty($article['image']) && _isVideoFilePath($article['image'])) {
        if (strpos($article['content'], 'news-video-wrapper') === false &&
            strpos($article['content'], '<video') === false &&
            strpos($article['content'], '<iframe') === false) {
            // No video embed in content yet → inject one at the top
            $_injectVideo = '<div class="news-video-wrapper"><video controls preload="metadata" playsinline><source src="' . htmlspecialchars($article['image']) . '"></video></div>';
            $article['content'] = $_injectVideo . $article['content'];
        }
        // The .art-img rendering will also detect this and show a <video> player there.
        // That's fine — the image position player IS the main player.
        // If content also has one, it'll show below too. Acceptable redundancy.
    }
}

if ($isSinglePage) {
    // ★ v2.7: Single article page — render in NEW UI (no longer pass through old HTML)
    // Extracted $article data is used to build a fresh mobile UI.
    $pageTitle = !empty($article['title']) ? $article['title'] . ' — ' . $L['appName'] : $L['appName'];

    $shareUrl    = !empty($article['shareUrl']) ? $article['shareUrl'] : ('https://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . ($_SERVER['REQUEST_URI'] ?? '/'));
    $shareTitle  = $article['title'];
    $shareTitleEnc = urlencode($shareTitle);
    $shareUrlEnc = urlencode($shareUrl);

    $newSingleHTML = <<<HTML
<!DOCTYPE html>
<html lang="{$langAttr}" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=5.0">
<meta name="theme-color" content="#B71C1C">
<title>{$pageTitle}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --red: #B71C1C;
    --red-dark: #7F0000;
    --gold: #D4950A;
    --bg: #F4F1EB;
    --card-bg: #FFFFFF;
    --text: #1A1A1A;
    --muted: #666;
    --border: #E5E0D8;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Hind Siliguri', sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    -webkit-tap-highlight-color: transparent;
}
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }

/* TOP HEADER (same as home) */
.app-header {
    background: var(--red);
    color: #fff;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    overflow: hidden;
}
/* ★ v3.25: Brand name — always single line, no wrapping, no overlap */
.app-header .brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Noto Serif Bengali', serif;
    font-size: 17px;
    font-weight: 700;
    white-space: nowrap;
    flex-shrink: 0;
    z-index: 2;
}
.app-header .brand i { font-size: 18px; color: var(--gold); }
/* Actions — push to right, allow shrinking */
.app-header .actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 1;
    margin-left: auto;
    z-index: 1;
}
.app-header .icon-btn {
    width: 38px;
    height: 38px;
    border: none;
    background: rgba(255,255,255,.12);
    color: #fff;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: background .2s;
    position: relative;
}
.app-header .icon-btn:hover { background: rgba(255,255,255,.25); }
.icon-btn.lang-btn {
    background: rgba(255,255,255,.18);
    font-size: 11px;
    font-weight: 700;
    padding: 0 10px;
    width: auto;
    min-width: 38px;
    border-radius: 19px;
    letter-spacing: .3px;
}
.icon-btn.lang-btn:hover { background: rgba(255,255,255,.3); }

/* ★ v3.28: Date & time — always visible */
.header-datetime {
    display: flex !important;
    flex-direction: column !important;
    align-items: center;
    gap: 0;
    background: rgba(0,0,0,.25);
    padding: 2px 5px;
    border-radius: 3px;
    margin-right: 2px;
    white-space: nowrap;
    line-height: 1.1;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,.1);
}
.header-datetime .hd-date {
    font-weight: 500;
    font-size: 8px;
    color: rgba(255,255,255,.65);
    display: block;
    letter-spacing: 0.2px;
}
.header-datetime .hd-time {
    font-family: monospace;
    color: #80DEEA;
    font-weight: 700;
    font-size: 9px;
    line-height: 1.15;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-datetime .hd-live {
    display: inline-block;
    width: 3px;
    height: 3px;
    background: #69F0AE;
    border-radius: 50%;
    margin-right: 2px;
    animation: hdLivePulse 1.5s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes hdLivePulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .2; }
}
/* ★ v3.28: Date & time — always visible (was hidden on <520px) */
.header-datetime {
    display: flex !important;
}

/* ARTICLE CONTAINER */
.article-wrap {
    background: var(--card-bg);
    min-height: calc(100vh - 60px);
    padding: 0 0 30px;
}

/* BACK BUTTON BAR */
.back-bar {
    background: var(--card-bg);
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    position: sticky;
    top: 58px;
    z-index: 50;
    display: flex;
    align-items: center;
    gap: 10px;
}
.back-bar a {
    display: flex;
    align-items: center;
    gap: 6px;
    color: var(--red);
    font-size: 13px;
    font-weight: 600;
    padding: 6px 12px;
    background: rgba(183,28,28,.08);
    border-radius: 16px;
    transition: background .2s;
}
.back-bar a:hover { background: rgba(183,28,28,.15); }

/* ARTICLE BODY */
.article-body { padding: 16px 14px 0; }

/* Category badges — ★ v3.85: SMALL colored text format (no background).
   জাতীয় (red text) / কলকাতা (gold text). "choto kore" per user request. */
.art-cats { margin-bottom: 10px; font-size: 11px; font-weight: 700; line-height: 1.4; }
.art-cats .art-cat {
    display: inline;
    background: transparent;
    color: var(--red);
    font-size: 11px;
    font-weight: 700;
    padding: 0;
    border-radius: 0;
    letter-spacing: .2px;
    text-transform: none;
    margin-right: 0;
}
.art-cats .art-cat-sep {
    color: #999;
    margin: 0 2px;
    font-weight: 400;
}
.art-cats .art-cat.art-cat-sub-bg {
    background: transparent;
    color: var(--gold);
    border-radius: 0;
}

/* Title */
.art-title {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 22px;
    font-weight: 700;
    line-height: 1.3;
    color: var(--text);
    margin-bottom: 12px;
}

/* Share buttons */
.art-share {
    display: flex;
    align-items: center;
    gap: 8px;
    background: rgba(183,28,28,.04);
    padding: 10px 12px;
    border-radius: 8px;
    margin-bottom: 14px;
    flex-wrap: wrap;
}
.art-share-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--muted);
    margin-right: 4px;
}
.art-share-btn {
    width: 36px;
    height: 36px;
    border-radius: 50%;
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    border: none;
    cursor: pointer;
    transition: transform .2s;
}
.art-share-btn:active { transform: scale(.95); }
.share-fb { background: #1877F2; }
.share-x { background: #000; }
.share-wa { background: #25D366; }
.share-li { background: #0A66C2; }
.share-copy { background: var(--muted); }

/* Meta line */
.art-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    font-size: 12px;
    color: var(--muted);
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border);
    margin-bottom: 14px;
}
.art-meta span { display: flex; align-items: center; gap: 4px; }
.art-meta i { font-size: 11px; }
.art-meta .author { color: var(--red); font-weight: 600; }

/* Headline */
.art-headline {
    background: #f8f9fa;
    border-left: 4px solid var(--red);
    padding: 10px 14px;
    font-size: 14px;
    color: #444;
    line-height: 1.5;
    margin-bottom: 14px;
    border-radius: 0 4px 4px 0;
}

/* ★ v3.7: Summary box REMOVED (user requested) */

/* ★ v3.9: "তথ্য:" Summary Box — placed AFTER title, BEFORE image */
.art-summary {
    margin: 0 0 14px;
    padding: 12px 14px;
    background: linear-gradient(135deg, rgba(183,28,28,.08) 0%, rgba(183,28,28,.15) 100%);
    border: 1px solid rgba(183,28,28,.3);
    border-left: 4px solid var(--red);
    border-radius: 6px;
    box-shadow: 0 2px 8px rgba(183,28,28,.08);
}
.art-summary .summary-label {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 14px;
    font-weight: 700;
    color: var(--red);
    margin-bottom: 6px;
    display: flex;
    align-items: center;
    gap: 6px;
}
.art-summary .summary-label::before {
    content: '\f05a';
    font-family: 'Font Awesome 6 Free';
    font-weight: 900;
    font-size: 13px;
    color: var(--red);
}
.art-summary .summary-text {
    font-size: 14px;
    line-height: 1.7;
    color: #5d4037;
    font-weight: 500;
}

/* Image */
.art-img {
    width: 100%;
    border-radius: 6px;
    margin-bottom: 14px;
    overflow: hidden;
}
.art-img img {
    width: 100%;
    border-radius: 6px;
}

/* Content */
.art-content {
    font-size: 16px;
    line-height: 1.9;
    color: #333;
    overflow-x: hidden;
    text-align: justify;
    text-justify: inter-word;
    transition: max-height .4s ease;
}
/* ★ v3.64: Read More — show only 1st paragraph, hide rest */
.art-content.art-content-collapsed {
    max-height: 9999px;
    overflow: hidden;
    position: relative;
}
/* ২য় প্যারাগ্রাফ থেকে সব hide */
.art-content.art-content-collapsed > *:nth-child(n+2) {
    display: none !important;
}
.art-content.art-content-collapsed::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 0;
    right: 0;
    height: 40px;
    background: linear-gradient(transparent, #fff);
    pointer-events: none;
}
.art-content.art-content-expanded {
    max-height: none;
    overflow: visible;
}
.art-content.art-content-expanded::after {
    display: none;
}
.art-content p { margin-bottom: 14px; }
.art-content h2, .art-content h3, .art-content h4 {
    font-family: 'Noto Serif Bengali', serif;
    margin: 20px 0 10px;
    color: #1a1a1a;
}
.art-content img {
    max-width: 90%;
    height: auto;
    border-radius: 8px;
    margin: 16px auto;
    display: block;
    box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
.art-content iframe {
    max-width: 100%;
    border: none;
    border-radius: 8px;
    margin: 16px 0;
}

/* ★ v3.3: STRIP all inline styles from article content's divs/spans.
   News editors sometimes add red/pink callout boxes via WYSIWYG editor
   with inline styles like background: linear-gradient(red...) — these are
   unwanted in the app's clean reading experience. */
.art-content div,
.art-content div[class],
.art-content div[style],
.art-content span[style],
.art-content p[style],
.art-content section[style] {
    background: transparent !important;
    background-color: transparent !important;
    background-image: none !important;
    border: none !important;
    border-left: none !important;
    border-right: none !important;
    border-top: none !important;
    border-bottom: none !important;
    box-shadow: none !important;
    padding: 0 !important;
    margin: 0 0 14px 0 !important;
    border-radius: 0 !important;
}
/* Re-apply specific styles for known good wrappers (video + twitter) */
.art-content .news-video-wrapper {
    position: relative;
    width: 100%;
    max-width: 700px;
    margin: 20px auto !important;
    aspect-ratio: 16 / 9;
    background: #000 !important;
    border-radius: 8px !important;
    overflow: hidden;
}
.art-content .news-video-wrapper iframe {
    position: absolute;
    top: 0; left: 0;
    width: 100% !important;
    height: 100% !important;
    margin: 0;
}
/* ★ v3.102: Direct <video> tag styling (for uploaded .mp4 files) */
.art-content .news-video-wrapper video {
    position: absolute;
    top: 0; left: 0;
    width: 100% !important;
    height: 100% !important;
    margin: 0;
    object-fit: contain;
    background: #000;
}
.art-content .news-video-wrapper video::-webkit-media-controls {
    width: 100%;
}
/* ★ v3.102: Play button overlay for <video> — ensures user knows to click */
.art-content .news-video-wrapper video[controls] {
    cursor: pointer;
}
.art-content .twitter-embed-wrapper {
    max-width: 550px;
    margin: 16px auto !important;
    background: #fff !important;
    border: 1px solid #e1e8ed !important;
    border-radius: 8px !important;
    overflow: hidden;
}
.art-content .twitter-embed-wrapper iframe {
    width: 100% !important;
    height: 500px !important;
}
/* Headlines that may have come over from article content (single-headline class)
   are also stripped — we don't want any pink/grey callout boxes */
.art-content .single-headline {
    background: transparent !important;
    border-left: none !important;
    padding: 0 !important;
    margin: 0 0 14px 0 !important;
    font-size: inherit !important;
    color: inherit !important;
}

/* ★ v3.41: Text-to-Speech (TTS) button */
.art-tts {
    margin: 16px 0 0;
    display: flex;
    gap: 8px;
    align-items: center;
}
.tts-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: var(--red);
    color: #fff;
    border: none;
    padding: 8px 16px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all .2s ease;
    box-shadow: 0 2px 6px rgba(183,28,28,.2);
    font-family: inherit;
}
.tts-btn:hover {
    background: var(--red-dark);
    box-shadow: 0 3px 10px rgba(183,28,28,.3);
    transform: translateY(-1px);
}
.tts-btn:active { transform: scale(.97); }
.tts-btn i { font-size: 12px; }
.tts-btn.playing {
    background: #00897B;
    animation: ttsPulse 1.5s ease-in-out infinite;
}
.tts-btn.tts-stop {
    background: var(--muted);
}
.tts-btn.tts-stop:hover { background: #555; }
@keyframes ttsPulse {
    0%, 100% { box-shadow: 0 2px 6px rgba(0,137,123,.3); }
    50% { box-shadow: 0 2px 14px rgba(0,137,123,.6); }
}

/* Tags section */
.art-tags {
    margin: 24px 0 0;
    padding: 16px 0 0;
    border-top: 2px dashed rgba(183,28,28,.2);
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
}
.art-tags-label {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 14px;
    font-weight: 700;
    color: var(--red);
    margin-right: 4px;
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(183,28,28,.06);
    padding: 4px 10px;
    border-radius: 4px;
}
.art-tags-label i {
    color: var(--red);
    font-size: 12px;
}
.art-tag-pill {
    background: #fff;
    color: var(--red);
    font-size: 12px;
    font-weight: 600;
    padding: 5px 12px;
    border: 1px solid rgba(183,28,28,.3);
    border-radius: 20px;
    transition: all .25s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
    box-shadow: 0 1px 3px rgba(183,28,28,.08);
}
.art-tag-pill:hover {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
    box-shadow: 0 3px 10px rgba(183,28,28,.25);
    transform: translateY(-1px);
}
.art-tag-pill i {
    font-size: 9px;
    color: var(--gold);
    transition: color .25s ease;
}
.art-tag-pill:hover i {
    color: #fff;
}

/* RELATED NEWS */
.rel-sec { padding: 20px 14px 0; }
.rel-head {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    border-left: 4px solid var(--red);
    padding-left: 10px;
    margin-bottom: 12px;
}
.rel-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.rel-card {
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    cursor: pointer;
    transition: transform .2s;
    display: flex;
    flex-direction: column;
}
.rel-card:active { transform: scale(.98); }
.rel-card img {
    width: 100%;
    aspect-ratio: 16/10;
    object-fit: cover;
    background: #eee;
}
.rel-card .rc-body { padding: 8px 10px 10px; flex: 1; }
.rel-card .rc-cat { font-size: 9px; color: var(--red); font-weight: 700; text-transform: uppercase; margin-bottom: 3px; }
.rel-card .rc-title { font-size: 12.5px; font-weight: 600; line-height: 1.35; display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden; }
.rel-card .rc-time { font-size: 10px; color: var(--muted); margin-top: 4px; }

/* FOOTER */
.app-footer {
    background: var(--red-dark);
    color: rgba(255,255,255,.85);
    padding: 18px 14px 14px;
    text-align: center;
    font-size: 11px;
    margin-top: 30px;
}
.app-footer .ft-brand {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}
.app-footer .ft-social {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin: 10px 0;
}
.app-footer .ft-social a {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 13px;
}

/* ★ v3.48: Retro counter-style visitor count */
.app-footer .ft-counter {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid rgba(255,255,255,.1);
    text-align: center;
}
.app-footer .counter-display {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    margin-bottom: 2px;
}
.app-footer .counter-digit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 11px;
    height: 15px;
    padding: 0 2px;
    background: #000;
    color: #fff;
    font-family: 'Hind Siliguri', 'Courier New', monospace;
    font-size: 9px;
    font-weight: 700;
    border-radius: 1px;
    border: 1px solid rgba(255,255,255,.15);
    box-shadow: 0 1px 1px rgba(0,0,0,.3);
    line-height: 1;
}
.app-footer .counter-sep {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3px;
    color: rgba(255,255,255,.5);
    font-size: 8px;
    font-weight: 700;
}
.app-footer .counter-label {
    font-size: 7px;
    color: rgba(255,255,255,.7);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}
/* ★ v3.51: Green live-dot before count (pulsing "site is live" indicator) */
.app-footer .counter-live-dot {
    display: inline-flex;
    align-items: center;
    width: 5px;
    height: 5px;
    background: #69F0AE;
    border-radius: 50%;
    margin-right: 3px;
    flex-shrink: 0;
    box-shadow: 0 0 4px rgba(105, 240, 174, 0.7);
    animation: livePulse 1.5s ease-in-out infinite;
}
@keyframes livePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

/* SIDEBAR (slide-in) */
.app-sidebar {
    position: fixed;
    top: 0;
    left: -280px;
    bottom: 0;
    width: 280px;
    background: var(--card-bg);
    z-index: 200;
    box-shadow: 4px 0 24px rgba(0,0,0,.2);
    transition: left .3s;
    overflow-y: auto;
}
.app-sidebar.open { left: 0; }
body.sidebar-open::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 150;
}
body.sidebar-open { overflow: hidden; }

/* ★ v3.45: Sidebar category list with counts + subcategories */
.app-sidebar .sb-cat-wrap { position: relative; }
.app-sidebar .sb-cat-wrap.has-subs .sb-cat-item { padding-right: 36px; }
.app-sidebar .sb-cat-item {
    display: flex; align-items: center; gap: 8px; padding: 7px 8px;
    border-radius: 5px; font-size: 13px; color: var(--text);
    transition: background .15s;
}
.app-sidebar .sb-cat-item:hover,
.app-sidebar .sb-cat-item.active { background: rgba(183,28,28,.06); color: var(--red); }
.app-sidebar .sb-cat-item i {
    width: 24px; height: 24px; display: flex; align-items: center; justify-content: center;
    background: rgba(183,28,28,.08); color: var(--red); border-radius: 4px;
    font-size: 11px; flex-shrink: 0;
}
.app-sidebar .sb-cat-name {
    flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.app-sidebar .sb-cat-count {
    margin-left: auto; background: var(--red); color: #fff; font-size: 10px;
    font-weight: 700; padding: 2px 8px; border-radius: 10px; min-width: 22px;
    text-align: center; flex-shrink: 0; line-height: 1.4;
}
.app-sidebar .sb-cat-item:hover .sb-cat-count { background: #fff; color: var(--red); }
.app-sidebar .sb-sub-toggle {
    position: absolute; right: 4px; top: 50%; transform: translateY(-50%);
    width: 26px; height: 26px; background: rgba(183,28,28,.08);
    border: 1px solid rgba(183,28,28,.15); color: var(--red); border-radius: 4px;
    cursor: pointer; font-size: 10px; display: flex; align-items: center;
    justify-content: center; transition: all .2s; z-index: 5;
}
.app-sidebar .sb-sub-toggle:hover { background: rgba(183,28,28,.18); }
.app-sidebar .sb-sub-toggle.open { background: var(--red); color: #fff; border-color: var(--red); }
.app-sidebar .sb-sub-toggle i { transition: transform .3s; }
.app-sidebar .sb-sub-toggle.open i { transform: rotate(180deg); }
.app-sidebar .sb-sub-list {
    display: none; padding: 2px 0 4px 30px; margin: 0 0 2px 8px;
    border-left: 2px solid var(--border);
}
.app-sidebar .sb-sub-list.open { display: block; }
.app-sidebar .sb-sub-item {
    display: flex; align-items: center; gap: 6px; padding: 5px 8px;
    border-radius: 4px; font-size: 11.5px; color: var(--muted); transition: all .15s;
}
.app-sidebar .sb-sub-item:hover,
.app-sidebar .sb-sub-item.active { background: rgba(183,28,28,.06); color: var(--red); }
.app-sidebar .sb-sub-item i { font-size: 6px; opacity: .4; flex-shrink: 0; }
.app-sidebar .sb-sub-name {
    flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
}
.app-sidebar .sb-sub-count {
    font-size: 9px; color: var(--muted); background: var(--bg);
    padding: 1px 5px; border-radius: 6px; flex-shrink: 0;
}

/* RESPONSIVE */
@media (max-width: 380px) {
    .rel-grid { grid-template-columns: 1fr; }
    .art-title { font-size: 19px; }
}
@media (min-width: 768px) {
    .article-wrap, .rel-sec { max-width: 720px; margin: 0 auto; }
    .rel-grid { grid-template-columns: repeat(3, 1fr); }
}
</style>
</head>
<body class="app-view">

<!-- TOP HEADER -->
<header class="app-header">
    <a href="?page=home" class="brand">
        <i class="fas fa-newspaper"></i>
        <span>{$L['appName']}&nbsp;&nbsp;</span>
    </a>
    <div class="actions">
        <!-- ★ v3.22: Date (top) + live time (bottom) with pulsing dot -->
        <div class="header-datetime" id="headerDateTime">
            <span class="hd-date" id="hdDate">--</span>
            <span class="hd-time"><span class="hd-live"></span><span id="hdTime">--:--</span></span>
        </div>
        <a href="{$langSwitchHref}" class="icon-btn lang-btn" id="langSwitchBtn" title="Switch Language">
            {$langSwitchLabel}
        </a>
        <button class="icon-btn" id="searchBtn" aria-label="Search">
            <i class="fas fa-search"></i>
        </button>
        <button class="icon-btn" id="menuBtn" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

HTML;

    // If article data extraction failed, show error
    if (empty($article['title'])) {
        $newSingleHTML .= <<<HTML
<div class="article-wrap">
    <div class="back-bar">
        <a href="?page=home"><i class="fas fa-arrow-left"></i> {$L['backHome']}</a>
    </div>
    <div style="padding: 60px 20px; text-align: center; color: var(--muted);">
        <i class="far fa-newspaper" style="font-size: 48px; color: var(--border); margin-bottom: 12px;"></i>
        <h3 style="font-size: 16px; color: var(--text); margin-bottom: 6px;">{$L['noResult']}</h3>
        <a href="?page=home" style="display: inline-block; margin-top: 12px; padding: 8px 20px; background: var(--red); color: #fff; border-radius: 4px; font-weight: 600;">{$L['backHome']}</a>
    </div>
</div>
HTML;
        $newSingleHTML .= '</body></html>';
        echo $newSingleHTML;
        return;
    }

    // Back button bar
    $backLabel = $isEnglish ? 'Back' : 'পেছনে';
    $newSingleHTML .= <<<HTML
<div class="article-wrap">
    <div class="back-bar">
        <a href="javascript:history.back()"><i class="fas fa-arrow-left"></i> {$backLabel}</a>
    </div>
    <div class="article-body">
HTML;

    // Category badges — ★ v3.85: small colored text format
    //    জাতীয় (red text)  /  কলকাতা (gold text)
    //    Smaller font, no background — "choto kore" per user request.
    $catBadges = '';
    if (!empty($article['category'])) {
        $_catName = $article['category'];
        if ($isEnglish && function_exists('en_label')) {
            $_catName = en_label($_catName);
        }
        if (!empty($article['subCat'])) {
            $_subName = $article['subCat'];
            if ($isEnglish && function_exists('en_label')) {
                $_subName = en_label($_subName);
            }
            // Category — red text
            $catBadges .= '<a href="' . htmlspecialchars($article['catHref']) . '" class="art-cat">' . htmlspecialchars($_catName) . '</a>';
            // Separator — grey "/"
            $catBadges .= '<span class="art-cat-sep">/</span>';
            // Subcategory — gold text
            $_subHref = !empty($article['subHref']) ? $article['subHref'] : ('?cat=' . urlencode($article['category']) . '&sub=' . urlencode($article['subCat']));
            $catBadges .= '<a href="' . htmlspecialchars($_subHref) . '" class="art-cat art-cat-sub-bg">' . htmlspecialchars($_subName) . '</a>';
        } else {
            // Only category — red text
            $catBadges .= '<a href="' . htmlspecialchars($article['catHref']) . '" class="art-cat">' . htmlspecialchars($_catName) . '</a>';
        }
    }
    if ($catBadges) {
        $newSingleHTML .= '<div class="art-cats">' . $catBadges . '</div>';
    }

    // ★ v2.9: Share buttons BEFORE title (user requested)
    $shareLabel = $isEnglish ? 'Share:' : 'শেয়ার করুন:';
    $newSingleHTML .= <<<HTML
<div class="art-share">
    <span class="art-share-label">{$shareLabel}</span>
    <a href="https://www.facebook.com/sharer/sharer.php?u={$shareUrlEnc}" target="_blank" rel="noopener noreferrer" class="art-share-btn share-fb" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
    <a href="https://twitter.com/intent/tweet?text={$shareTitleEnc}&url={$shareUrlEnc}" target="_blank" rel="noopener noreferrer" class="art-share-btn share-x" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
    <a href="https://api.whatsapp.com/send?text={$shareTitleEnc}%20{$shareUrlEnc}" target="_blank" rel="noopener noreferrer" class="art-share-btn share-wa" aria-label="WhatsApp"><i class="fab fa-whatsapp"></i></a>
    <a href="https://www.linkedin.com/sharing/share-offsite/?url={$shareUrlEnc}" target="_blank" rel="noopener noreferrer" class="art-share-btn share-li" aria-label="LinkedIn"><i class="fab fa-linkedin-in"></i></a>
    <button class="art-share-btn share-copy" id="copyLinkBtn" aria-label="Copy Link"><i class="fas fa-link"></i></button>
</div>
HTML;

    // Title
    $newSingleHTML .= '<h1 class="art-title">' . htmlspecialchars($article['title']) . '</h1>';

    // ★ v3.9: "তথ্য:" summary box — AFTER title, BEFORE image (user requested)
    if (!empty($article['headline'])) {
        $infoLabel = $isEnglish ? 'Info:' : 'তথ্য:';
        $newSingleHTML .= <<<HTML
<div class="art-summary">
    <div class="summary-label">{$infoLabel}</div>
    <div class="summary-text">{$article['headline']}</div>
</div>
HTML;
    }

    // Image
    if (!empty($article['image'])) {
        // ★ v3.103: If the image field actually contains a video file (.mp4 etc.),
        //    render a <video> player instead of <img>. admin_dashboard.php saves
        //    uploaded videos into the news `image` field, and <img> can't play them.
        if (_isVideoFilePath($article['image'])) {
            $article['is_video'] = true;  // Also expands content below
            $newSingleHTML .= '<div class="art-img" style="background:#000;border-radius:6px;overflow:hidden;margin-bottom:14px;">';
            $newSingleHTML .= '<video controls preload="metadata" playsinline style="width:100%;display:block;max-height:500px;object-fit:contain;" src="' . htmlspecialchars($article['image']) . '"></video>';
            $newSingleHTML .= '</div>';
        } else {
            $newSingleHTML .= '<div class="art-img"><img src="' . htmlspecialchars($article['image']) . '" alt="' . htmlspecialchars($article['title']) . '" decoding="async" fetchpriority="high"></div>';
        }
    }

    // Meta
    // ★ v3.8: Tags removed from meta line AND no dedicated tags section (user requested)
    $metaHtml = '<div class="art-meta">';
    if (!empty($article['date'])) {
        $metaHtml .= '<span><i class="far fa-clock"></i> ' . htmlspecialchars($article['date']) . '</span>';
    }
    $metaHtml .= '<span><i class="far fa-user"></i> <span class="author">' . $L['appName'] . '</span></span>';
    $metaHtml .= '</div>';
    $newSingleHTML .= $metaHtml;

    // Headline
    // ★ v3.2: Headline no longer shown as separate box (summary removed)

    // Content (HTML — already processed by index.php for video embeds etc.)
    if (!empty($article['content'])) {
        $readMoreLabel = $isEnglish ? 'Read More' : 'আরও পড়ুন';
        $readLessLabel = $isEnglish ? 'Read Less' : 'কম পড়ুন';

        // ★ v3.102: Video articles — show content EXPANDED (not collapsed).
        //    Collapsed mode hides everything after the first child AND adds a
        //    white gradient at the bottom that covers video controls.
        //    For video articles we skip collapsing and the Read More button.
        $_isVideoArticle = !empty($article['is_video']);
        if ($_isVideoArticle) {
            $newSingleHTML .= '<div class="art-content art-content-expanded" id="artContent">' . $article['content'] . '</div>';
        } else {
            $newSingleHTML .= '<div class="art-content art-content-collapsed" id="artContent">' . $article['content'] . '</div>';
            $newSingleHTML .= '<div style="text-align:center;margin:10px 0 0;"><button id="readMoreBtn" data-more="' . $readMoreLabel . '" data-less="' . $readLessLabel . '" onclick="toggleReadMore()" style="background:var(--red);color:#fff;border:none;padding:8px 24px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;"><i class="fas fa-chevron-down" style="margin-right:5px;"></i> ' . $readMoreLabel . '</button></div>';
        }
    }

    // ★ v3.41: Text-to-Speech (TTS) button — Bengali & English
    $ttsListenLabel = $isEnglish ? 'Listen' : 'শুনুন';
    $ttsStopLabel = $isEnglish ? 'Stop' : 'থামান';
    $newSingleHTML .= <<<HTML
<div class="art-tts" id="artTts">
    <button class="tts-btn" id="ttsPlayBtn" onclick="toggleTTS()">
        <i class="fas fa-play" id="ttsIcon"></i>
        <span id="ttsLabel">{$ttsListenLabel}</span>
    </button>
    <button class="tts-btn tts-stop" id="ttsStopBtn" onclick="stopTTS()" style="display:none">
        <i class="fas fa-stop"></i>
        <span>{$ttsStopLabel}</span>
    </button>
</div>
HTML;

    // ★ v3.30: Tags section ("ট্যাগ:") — KEPT (2nd tag section, user wants this)
    // First tag (in content) is removed by stopping content at single-tags div above.
    // This is the separate stylish tags section below content.
    // ★ v3.81: ALWAYS show the "ট্যাগ" label, even when the article has no tags.
    //    When there are no tags, the area after the label is just blank (no pills).
    //    The label stays so users see the section exists.
    $tagsLabel = $isEnglish ? 'Tags' : 'ট্যাগ';
    $newSingleHTML .= '<div class="art-tags">';
    $newSingleHTML .= '<span class="art-tags-label"><i class="fas fa-tags"></i> ' . $tagsLabel . '</span>';
    if (!empty($article['tags'])) {
        foreach ($article['tags'] as $tag) {
            $tag = trim($tag);
            if ($tag) {
                $tagHref = '?q=' . urlencode($tag);
                $newSingleHTML .= '<a href="' . htmlspecialchars($tagHref) . '" class="art-tag-pill"><i class="fas fa-hashtag"></i>' . htmlspecialchars($tag) . '</a>';
            }
        }
    }
    $newSingleHTML .= '</div>';

    $newSingleHTML .= '</div>'; // .article-body
    $newSingleHTML .= '</div>'; // .article-wrap

    // Related news
    if (!empty($article['related'])) {
        $relLabel = $isEnglish ? 'Related News' : 'সম্পর্কিত সংবাদ';
        $newSingleHTML .= '<div class="rel-sec"><div class="rel-head">' . $relLabel . '</div><div class="rel-grid">';
        foreach ($article['related'] as $rel) {
            $relTimeStr = !empty($rel['time']) ? '<div class="rc-time"><i class="far fa-clock"></i> ' . htmlspecialchars($rel['time']) . '</div>' : '';
            $relCatStr  = !empty($rel['cat']) ? '<div class="rc-cat">' . htmlspecialchars($rel['cat']) . '</div>' : '';
            // ★ v3.103: Use video thumbnail if image is .mp4
            $_rel_thumb = _renderCardThumb($rel['img'], '');
            $newSingleHTML .= '<a href="' . $rel['href'] . '" class="rel-card">' . $_rel_thumb . '<div class="rc-body">' . $relCatStr . '<div class="rc-title">' . $rel['title'] . '</div>' . $relTimeStr . '</div></a>';
        }
        $newSingleHTML .= '</div></div>';
    }

    // Footer
    $newSingleHTML .= <<<HTML
<footer class="app-footer">
    <div class="ft-brand">{$L['appName']}</div>
    <div>Truthy &amp; Trusted News Portal</div>
    <div class="ft-social">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
    </div>
    <div style="display:flex;align-items:center;">
        <div style="flex:1;text-align:left;">{$ucFooterHTML}</div>
        <div style="flex:1;text-align:center;">&copy; 2026 {$L['appName']}</div>
        <div style="flex:1;text-align:right;">&nbsp;</div>
    </div>
</footer>
HTML;

    // Sidebar (slide-in — same as home)
    $allCatsLabel = $isEnglish ? 'All Categories' : 'সব ক্যাটাগরি';
    $newSingleHTML .= '<div class="app-sidebar" id="appSidebar">';
    $newSingleHTML .= '<div style="background:var(--red);color:#fff;padding:16px 14px;display:flex;align-items:center;justify-content:space-between;">';
    $newSingleHTML .= '<h3 style="font-family:\'Noto Serif Bengali\',serif;font-size:16px;font-weight:700;">' . $L['appName'] . '</h3>';
    $newSingleHTML .= '<button style="background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px;" id="sbClose">&times;</button>';
    $newSingleHTML .= '</div>';
    $newSingleHTML .= '<div style="padding:12px 14px;border-bottom:1px solid var(--border);">';
    $newSingleHTML .= '<h4 style="font-family:\'Noto Serif Bengali\',serif;font-size:13px;color:var(--red);margin-bottom:8px;font-weight:700;">' . $allCatsLabel . '</h4>';
    // ★ v3.45: Real DB counts + subcategories with counts (from index.php)
    foreach ($catList as $cat) {
        $catName    = $cat['name'];
        $catDisplay = $cat['displayName'];
        $catHref    = $cat['href'];
        $catIcon    = $cat['icon'];
        $catCount   = $cat['count'];
        $catSubs    = $cat['subs'];
        $hasSubs    = !empty($catSubs);
        $isActive   = ($currentCat === $catName && !$dbCurrentSub) ? ' active' : '';
        $countBadge = $catCount > 0 ? '<span class="sb-cat-count">' . (int)$catCount . '</span>' : '';
        $newSingleHTML .= '<div class="sb-cat-wrap' . ($hasSubs ? ' has-subs' : '') . '">';
        $newSingleHTML .= '<a href="' . htmlspecialchars($catHref) . '" class="sb-cat-item' . $isActive . '"><i class="fas ' . htmlspecialchars($catIcon) . '"></i><span class="sb-cat-name">' . htmlspecialchars($catDisplay) . '</span>' . $countBadge . '</a>';
        if ($hasSubs) {
            $newSingleHTML .= '<button class="sb-sub-toggle" data-cat="' . htmlspecialchars($catDisplay) . '" aria-label="Sub-categories"><i class="fas fa-chevron-down"></i></button>';
            $newSingleHTML .= '<div class="sb-sub-list" data-cat="' . htmlspecialchars($catDisplay) . '">';
            foreach ($catSubs as $sub) {
                $subCount  = $dbSubPostCounts[$sub['id']] ?? 0;
                $subBadge  = $subCount > 0 ? '<span class="sb-sub-count">' . (int)$subCount . '</span>' : '';
                $subHref   = '?cat=' . urlencode($catName) . '&sub=' . (int)$sub['id'];
                $subActive = ($dbCurrentSub === (int)$sub['id']) ? ' active' : '';
                $newSingleHTML .= '<a href="' . htmlspecialchars($subHref) . '" class="sb-sub-item' . $subActive . '"><i class="fas fa-circle"></i><span class="sb-sub-name">' . htmlspecialchars($sub['name']) . '</span>' . $subBadge . '</a>';
            }
            $newSingleHTML .= '</div>';
        }
        $newSingleHTML .= '</div>';
    }
    $newSingleHTML .= '</div></div>';

    // JavaScript
    $searchPlaceholder = $L['search'];
    $newSingleHTML .= <<<JS
<script>
(function() {
    'use strict';

    // ★ v3.22: Date & live time updater (IST, English, with seconds)
    // Date stays fixed on top line. Time updates every second on bottom line.
    // IST = UTC + 5:30. getTime() returns UTC ms, we add IST offset.
    function updateDateTime() {
        try {
            var now = new Date();
            var istMs = now.getTime() + (5.5 * 60 * 60 * 1000);
            var ist = new Date(istMs);
            var date = ist.getUTCDate();
            var month = ist.getUTCMonth();
            var year = ist.getUTCFullYear();
            var h = ist.getUTCHours();
            var m = ist.getUTCMinutes();
            var s = ist.getUTCSeconds();
            var ampm = h >= 12 ? 'PM' : 'AM';
            var h12 = h % 12; if (h12 === 0) h12 = 12;
            var enMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            // Date: "29 Oct 2026"
            var dateStr = date + ' ' + enMonths[month] + ' ' + year;
            // Time with seconds: "3:45:23 PM" (seconds make live update visible)
            var timeStr = h12 + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' ' + ampm;
            var hdDate = document.getElementById('hdDate');
            var hdTime = document.getElementById('hdTime');
            if (hdDate) hdDate.textContent = dateStr;
            if (hdTime) hdTime.textContent = timeStr;
        } catch(e) {}
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Search overlay
    var searchBtn = document.getElementById('searchBtn');
    var searchOverlay = document.createElement('div');
    searchOverlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:200;align-items:flex-start;justify-content:center;padding:60px 16px 0;';
    searchOverlay.innerHTML = '<form action="?" method="GET" style="width:100%;max-width:500px;background:#fff;border-radius:8px;overflow:hidden;display:flex;box-shadow:0 8px 30px rgba(0,0,0,.3);"><input type="hidden" name="page" value="home"><input type="text" name="q" placeholder="{$searchPlaceholder}" style="flex:1;border:none;padding:14px 16px;font-size:15px;font-family:inherit;outline:none;"><button type="submit" style="border:none;background:var(--red);color:#fff;padding:0 18px;cursor:pointer;font-size:16px;"><i class="fas fa-search"></i></button></form>';
    document.body.appendChild(searchOverlay);
    if (searchBtn) {
        searchBtn.addEventListener('click', function() {
            searchOverlay.style.display = 'flex';
            var input = searchOverlay.querySelector('input');
            if (input) setTimeout(function() { input.focus(); }, 50);
        });
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === searchOverlay) searchOverlay.style.display = 'none';
        });
    }

    // ★ v3.42: Header hamburger (☰) button — shows "Under Construction" popup
    //    instead of opening the sidebar (user requested).
    var menuBtn = document.getElementById('menuBtn');
    var appSidebar = document.getElementById('appSidebar');
    var sbClose = document.getElementById('sbClose');

    // ★ v3.45: Subcategory expand/collapse toggle in sidebar.
    //    Real DB counts come from PHP (index.php's $catPostCounts / $subPostCounts)
    //    — no AJAX needed. JS only handles the expand/collapse UI.
    document.querySelectorAll('.sb-sub-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var cat = this.getAttribute('data-cat');
            var list = document.querySelector('.sb-sub-list[data-cat="' + cat + '"]');
            if (list) list.classList.toggle('open');
            this.classList.toggle('open');
        });
    });

    function openSidebar() {
        if (appSidebar) appSidebar.classList.add('open');
        document.body.classList.add('sidebar-open');
    }
    function closeSidebar() {
        if (appSidebar) appSidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }

    // ★ v3.42: "Under Construction" popup — bilingual (Bengali / English)
    var isEnUC = (document.documentElement.getAttribute('lang') || 'bn').toLowerCase() === 'en';
    var ucTitle   = isEnUC ? 'Under Construction' : 'নির্মাণাধীন';
    var ucMessage = isEnUC
        ? 'This section is currently under construction. Please check back soon.'
        : 'এই অংশটি বর্তমানে নির্মাণাধীন। অনুগ্রহ করে শীঘ্রই আবার দেখুন।';
    var ucCloseLabel = isEnUC ? 'Close' : 'বন্ধ করুন';

    var ucOverlay = document.createElement('div');
    ucOverlay.id = 'ucOverlay';
    ucOverlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10000;align-items:center;justify-content:center;padding:20px;';
    ucOverlay.innerHTML =
        '<div style="background:#fff;border-radius:14px;max-width:340px;width:100%;padding:30px 24px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.3);border-top:6px solid #B71C1C;">' +
            '<div style="width:80px;height:80px;margin:0 auto 18px;background:rgba(183,28,28,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:36px;color:#B71C1C;">' +
                '<i class="fas fa-helmet-safety"></i>' +
            '</div>' +
            '<h3 style="font-family:\'Noto Serif Bengali\',serif;font-size:20px;font-weight:700;color:#1A1A1A;margin-bottom:10px;">' + ucTitle + '</h3>' +
            '<p style="font-size:13px;color:#666;line-height:1.6;margin-bottom:20px;">' + ucMessage + '</p>' +
            '<button id="ucCloseBtn" style="background:#B71C1C;color:#fff;border:none;padding:9px 28px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">' + ucCloseLabel + '</button>' +
        '</div>';
    document.body.appendChild(ucOverlay);

    function showUnderConstruction(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (ucOverlay) ucOverlay.style.display = 'flex';
    }
    function hideUnderConstruction() {
        if (ucOverlay) ucOverlay.style.display = 'none';
    }
    // ★ Only the header hamburger (☰) triggers Under Construction.
    if (menuBtn) menuBtn.addEventListener('click', showUnderConstruction);
    ucOverlay.addEventListener('click', function(e) {
        if (e.target === ucOverlay || e.target.id === 'ucCloseBtn' || e.target.closest('#ucCloseBtn')) {
            hideUnderConstruction();
        }
    });

    if (sbClose) sbClose.addEventListener('click', closeSidebar);
    document.addEventListener('click', function(e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (appSidebar && appSidebar.contains(e.target)) return;
        if (e.target === menuBtn || (menuBtn && menuBtn.contains(e.target))) return;
        closeSidebar();
    });
    if (appSidebar) {
        appSidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    }

    // Copy link button
    var copyBtn = document.getElementById('copyLinkBtn');
    if (copyBtn) {
        copyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var tempInput = document.createElement('input');
            tempInput.value = window.location.href;
            document.body.appendChild(tempInput);
            tempInput.select();
            tempInput.setSelectionRange(0, 99999);
            try {
                document.execCommand('copy');
                var icon = copyBtn.querySelector('i');
                var oldClass = icon.className;
                icon.className = 'fas fa-check';
                setTimeout(function() { icon.className = oldClass; }, 2000);
            } catch(err) {}
            document.body.removeChild(tempInput);
        });
    }

    // ★ v3.41: Text-to-Speech (TTS) — Bengali & English
    var ttsSynth = window.speechSynthesis;
    var ttsUtterance = null;
    var ttsPlaying = false;

    function getArticleText() {
        var content = document.getElementById('artContent');
        if (!content) return '';
        // Get text from paragraphs, headings — skip images, videos, ads
        var nodes = content.querySelectorAll('p, h2, h3, h4, li');
        var text = '';
        for (var i = 0; i < nodes.length; i++) {
            var n = nodes[i];
            if (n.closest('.ad-wrapper') || n.closest('.news-video-wrapper') || n.closest('.twitter-embed-wrapper')) continue;
            var t = n.textContent.trim();
            if (t) text += t + '. ';
        }
        return text;
    }

    function toggleTTS() {
        if (ttsPlaying) { stopTTS(); return; }
        if (!ttsSynth) { alert('Speech synthesis not supported'); return; }

        var text = getArticleText();
        if (!text) { alert('No content to read'); return; }

        // Stop any existing speech
        ttsSynth.cancel();

        ttsUtterance = new SpeechSynthesisUtterance(text);

        // ★ Language detection — Bengali or English
        var isEn = (document.documentElement.getAttribute('lang') || 'bn').toLowerCase() === 'en';
        ttsUtterance.lang = isEn ? 'en-US' : 'bn-IN';

        // Try to find a matching voice
        var voices = ttsSynth.getVoices();
        var bnVoice = null;
        var enVoice = null;
        for (var v = 0; v < voices.length; v++) {
            var vl = (voices[v].lang || '').toLowerCase();
            if (vl.indexOf('bn') === 0) bnVoice = voices[v];
            if (vl.indexOf('en') === 0 && vl.indexOf('en-us') >= 0) enVoice = voices[v];
        }
        if (isEn && enVoice) ttsUtterance.voice = enVoice;
        else if (bnVoice) ttsUtterance.voice = bnVoice;

        // Speech settings
        ttsUtterance.rate = 1.0;
        ttsUtterance.pitch = 1.0;
        ttsUtterance.volume = 1.0;

        ttsUtterance.onstart = function() {
            ttsPlaying = true;
            var playBtn = document.getElementById('ttsPlayBtn');
            var stopBtn = document.getElementById('ttsStopBtn');
            var icon = document.getElementById('ttsIcon');
            var label = document.getElementById('ttsLabel');
            if (playBtn) { playBtn.style.display = 'none'; }
            if (stopBtn) { stopBtn.style.display = 'inline-flex'; stopBtn.classList.add('playing'); }
        };

        ttsUtterance.onend = function() {
            ttsPlaying = false;
            var playBtn = document.getElementById('ttsPlayBtn');
            var stopBtn = document.getElementById('ttsStopBtn');
            if (playBtn) { playBtn.style.display = 'inline-flex'; }
            if (stopBtn) { stopBtn.style.display = 'none'; stopBtn.classList.remove('playing'); }
        };

        ttsUtterance.onerror = function() {
            ttsPlaying = false;
            var playBtn = document.getElementById('ttsPlayBtn');
            var stopBtn = document.getElementById('ttsStopBtn');
            if (playBtn) { playBtn.style.display = 'inline-flex'; }
            if (stopBtn) { stopBtn.style.display = 'none'; stopBtn.classList.remove('playing'); }
        };

        ttsSynth.speak(ttsUtterance);
    }

    function stopTTS() {
        if (ttsSynth) ttsSynth.cancel();
        ttsPlaying = false;
        var playBtn = document.getElementById('ttsPlayBtn');
        var stopBtn = document.getElementById('ttsStopBtn');
        if (playBtn) { playBtn.style.display = 'inline-flex'; }
        if (stopBtn) { stopBtn.style.display = 'none'; stopBtn.classList.remove('playing'); }
    }

    // Expose to global scope for onclick handlers
    window.toggleTTS = toggleTTS;
    window.stopTTS = stopTTS;

    // ★ v3.62: Read More / Read Less toggle
    window.toggleReadMore = function() {
        var content = document.getElementById('artContent');
        var btn = document.getElementById('readMoreBtn');
        if (!content || !btn) return;
        if (content.classList.contains('art-content-collapsed')) {
            content.classList.remove('art-content-collapsed');
            content.classList.add('art-content-expanded');
            btn.innerHTML = '<i class="fas fa-chevron-up" style="margin-right:5px;"></i> ' + (btn.getAttribute('data-less') || 'Read Less');
        } else {
            content.classList.remove('art-content-expanded');
            content.classList.add('art-content-collapsed');
            btn.innerHTML = '<i class="fas fa-chevron-down" style="margin-right:5px;"></i> ' + (btn.getAttribute('data-more') || 'Read More');
            content.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    };

    // Load voices (some browsers need this)
    if (ttsSynth) {
        ttsSynth.onvoiceschanged = function() { ttsSynth.getVoices(); };
    }
})();
</script>
JS;

    $newSingleHTML .= '</body></html>';

    echo $newSingleHTML;
    return;
}

// ★ v3.106: Dedicated Video Page (?page=videos) — matches index.php's video page.
//    Renders videos from the `videos` table in a mobile-optimized grid with embedded
//    iframes. Each card shows the video embed + title + date.
if ($page === 'videos' || $page === 'video') {

    // Fetch videos directly from the DB (more reliable than HTML extraction)
    $_pageVideos = [];
    if (isset($pdo)) {
        try {
            @$pdo->exec("SET NAMES utf8mb4");
            $_pageVideos = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $_pve) { $_pageVideos = []; }
    }

    // Also try extracting from index.php's HTML (fallback if DB query fails)
    if (empty($_pageVideos)) {
        // index.php renders .vp-card elements with .video-embed, .vp-title, .vp-date
        if (preg_match_all('#<div class="vp-card"[^>]*>\s*<div class="video-embed"[^>]*>(.*?)</div>\s*<div class="vp-body"[^>]*>\s*<h3 class="vp-title"[^>]*>(.*?)</h3>\s*<p class="vp-date"[^>]*>(.*?)</p>#us', $html, $_vm)) {
            foreach ($_vm[1] as $_vi => $_vcode) {
                $_pageVideos[] = [
                    'code' => $_vcode,
                    'title' => html_entity_decode(trim(strip_tags($_vm[2][$_vi])), ENT_QUOTES, 'UTF-8'),
                    'created_at' => '',
                    'date_html' => trim($_vm[3][$_vi]),
                ];
            }
        }
    }

    $_videoPageTitle = $isEnglish ? 'Video News' : 'ভিডিও সংবাদ';
    $_noVideosMsg = $isEnglish ? 'No videos available' : 'কোনো ভিডিও নেই';
    $_noVideosDesc = $isEnglish ? 'No videos available at the moment.' : 'এই মুহূর্তে কোনো ভিডিও উপলব্ধ নেই।';

    // Build the video page HTML (same header/footer/sidebar structure as home)
    $_videoPageHTML = <<<HTML
<!DOCTYPE html>
<html lang="{$langAttr}" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=5.0">
<meta name="theme-color" content="#B71C1C">
<title>{$_videoPageTitle} — {$L['appName']}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--red:#B71C1C;--red-dark:#7F0000;--gold:#D4950A;--bg:#F4F1EB;--card-bg:#FFFFFF;--text:#1A1A1A;--muted:#666;--border:#E5E0D8;--tab-bg:#F5F5F5}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--text);line-height:1.5;-webkit-tap-highlight-color:transparent}
a{color:inherit;text-decoration:none}img{max-width:100%;display:block}
.app-header{background:var(--red);color:#fff;padding:10px 14px;display:flex;align-items:center;justify-content:space-between;gap:8px;position:sticky;top:0;z-index:100;box-shadow:0 2px 8px rgba(0,0,0,.15)}
.app-header .brand{display:flex;align-items:center;gap:8px;font-family:'Noto Serif Bengali',serif;font-size:17px;font-weight:700;white-space:nowrap;flex-shrink:0}
.app-header .brand i{font-size:18px;color:var(--gold)}
.app-header .actions{display:flex;align-items:center;gap:4px;margin-left:auto}
.app-header .icon-btn{width:38px;height:38px;border:none;background:rgba(255,255,255,.12);color:#fff;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:14px}
.header-datetime{display:flex;flex-direction:column;align-items:center;background:rgba(0,0,0,.25);padding:2px 5px;border-radius:3px;white-space:nowrap;flex-shrink:0;line-height:1.1}
.header-datetime .hd-date{font-size:8px;color:rgba(255,255,255,.65)}
.header-datetime .hd-time{font-family:monospace;color:#80DEEA;font-weight:700;font-size:9px}
.icon-btn.lang-btn{background:rgba(255,255,255,.18);font-size:11px;font-weight:700;padding:0 10px;width:auto;min-width:38px;border-radius:19px}
.content-wrap{padding:12px 0}
.video-page-head{padding:12px 14px 8px;display:flex;align-items:center;gap:8px}
.video-page-head .vph-icon{width:32px;height:32px;background:var(--red);color:#fff;border-radius:7px;display:flex;align-items:center;justify-content:center;font-size:14px;flex-shrink:0}
.video-page-head .vph-title{font-family:'Noto Serif Bengali',serif;font-size:18px;font-weight:700;color:var(--text)}
.video-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;padding:0 10px 24px}
.video-card{background:var(--card-bg);border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,.06);border:1px solid var(--border);border-bottom:3px solid var(--red);cursor:pointer;transition:transform .2s,box-shadow .2s}
.video-card:hover{transform:translateY(-3px);box-shadow:0 6px 18px rgba(0,0,0,.12)}
.video-embed-box{position:relative;width:100%;aspect-ratio:16/9;background:#000;overflow:hidden}
.video-embed-box iframe,.video-embed-box video{position:absolute;top:0;left:0;width:100%!important;height:100%!important;border:0}
/* Poster thumbnail + play button overlay (click to play) */
.video-poster{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;z-index:2;transition:transform .3s}
.video-card:hover .video-poster{transform:scale(1.05)}
.video-play-btn{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:52px;height:52px;background:rgba(183,28,28,.85);border-radius:50%;display:flex;align-items:center;justify-content:center;z-index:3;transition:all .2s;box-shadow:0 2px 12px rgba(0,0,0,.4);pointer-events:none}
.video-card:hover .video-play-btn{background:var(--red);transform:translate(-50%,-50%) scale(1.12);box-shadow:0 4px 20px rgba(183,28,28,.5)}
.video-play-btn i{color:#fff;font-size:18px;margin-left:3px}
.video-poster-placeholder{width:100%;height:100%;background:linear-gradient(135deg,#1a1a1a,#2d2d2d);position:absolute;top:0;left:0;z-index:1;display:flex;align-items:center;justify-content:center}
.video-poster-placeholder .vpp-label{color:rgba(255,255,255,.4);font-size:9px;font-weight:600;text-transform:uppercase;letter-spacing:1px}
.video-card-body{padding:8px 10px 10px}
.video-card-title{font-family:'Noto Serif Bengali',serif;font-size:13px;font-weight:600;color:#1a1a1a;line-height:1.35;margin:0 0 4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.video-card-date{font-size:10px;color:#888;display:flex;align-items:center;gap:4px}
.video-card-date i{color:var(--red);font-size:9px}
.no-videos{text-align:center;padding:60px 20px;color:var(--muted)}
.no-videos i{font-size:48px;color:var(--border);margin-bottom:12px}
.no-videos h3{font-size:16px;color:var(--text);margin-bottom:6px}
.no-videos p{font-size:13px}
.app-footer{background:var(--red-dark);color:rgba(255,255,255,.85);padding:18px 14px 14px;text-align:center;font-size:11px;margin-top:30px}
.app-footer .ft-brand{font-family:'Noto Serif Bengali',serif;font-size:14px;font-weight:700;color:#fff;margin-bottom:4px}
.fab-sidebar{position:fixed;bottom:20px;right:20px;width:48px;height:48px;background:var(--red);color:#fff;border:none;border-radius:50%;font-size:18px;cursor:pointer;box-shadow:0 4px 14px rgba(183,28,28,.4);z-index:95;display:flex;align-items:center;justify-content:center}
.app-sidebar{position:fixed;top:0;left:-280px;bottom:0;width:280px;background:var(--card-bg);z-index:200;box-shadow:4px 0 24px rgba(0,0,0,.2);transition:left .3s;overflow-y:auto}
.app-sidebar.open{left:0}
body.sidebar-open::before{content:'';position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:150}
body.sidebar-open{overflow:hidden}
.app-sidebar .sb-header{background:var(--red);color:#fff;padding:16px 14px;display:flex;align-items:center;justify-content:space-between}
.app-sidebar .sb-header h3{font-family:'Noto Serif Bengali',serif;font-size:16px;font-weight:700}
.app-sidebar .sb-close{background:rgba(255,255,255,.15);border:none;color:#fff;width:32px;height:32px;border-radius:50%;cursor:pointer;font-size:16px}
.app-sidebar .sb-section{padding:12px 14px;border-bottom:1px solid var(--border)}
.app-sidebar .sb-section h4{font-family:'Noto Serif Bengali',serif;font-size:13px;color:var(--red);margin-bottom:8px;font-weight:700}
.app-sidebar .sb-cat-item{display:flex;align-items:center;gap:8px;padding:7px 8px;border-radius:5px;font-size:13px;color:var(--text);transition:background .15s}
.app-sidebar .sb-cat-item:hover,.app-sidebar .sb-cat-item.active{background:rgba(183,28,28,.06);color:var(--red)}
.app-sidebar .sb-cat-item i{width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:rgba(183,28,28,.08);color:var(--red);border-radius:4px;font-size:11px;flex-shrink:0}
.app-sidebar .sb-cat-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.app-sidebar .sb-cat-count{margin-left:auto;background:var(--red);color:#fff;font-size:10px;font-weight:700;padding:2px 8px;border-radius:10px;min-width:22px;text-align:center}
@media(max-width:600px){.video-grid{grid-template-columns:repeat(2,1fr);gap:8px}.video-play-btn{width:40px;height:40px}.video-play-btn i{font-size:14px}.video-card-title{font-size:12px}.video-card-body{padding:6px 8px 8px}}
@media(max-width:380px){.video-grid{grid-template-columns:1fr}}
@media(min-width:769px){.video-grid{max-width:900px;margin:0 auto}}
</style>
</head>
<body class="app-view">
<header class="app-header">
    <a href="?page=home" class="brand"><i class="fas fa-newspaper"></i><span>{$L['appName']}&nbsp;&nbsp;</span></a>
    <div class="actions">
        <div class="header-datetime" id="headerDateTime"><span class="hd-date" id="hdDate">--</span><span class="hd-time" id="hdTime">--:--</span></div>
        <a href="{$langSwitchHref}" class="icon-btn lang-btn">{$langSwitchLabel}</a>
        <button class="icon-btn" id="searchBtn"><i class="fas fa-search"></i></button>
        <button class="icon-btn" id="menuBtn"><i class="fas fa-bars"></i></button>
    </div>
</header>
<div class="content-wrap">
    <div class="video-page-head">
        <div class="vph-icon"><i class="fas fa-video"></i></div>
        <div class="vph-title">{$_videoPageTitle}</div>
    </div>
HTML;

    if (!empty($_pageVideos)) {
        $_videoPageHTML .= '<div class="video-grid">';
        foreach ($_pageVideos as $_pvid) {
            $_pvidCode = $_pvid['code'] ?? '';
            $_pvidTitle = $_pvid['title'] ?? '';
            $_pvidDate = '';
            if (!empty($_pvid['date_html'])) {
                $_pvidDate = $_pvid['date_html'];  // Already HTML from extraction
            } elseif (!empty($_pvid['created_at'])) {
                $_pvidDate = '<i class="far fa-calendar-alt"></i> ' . date("d M Y", strtotime($_pvid['created_at']));
            }

            // Translate title for English mode
            if ($isEnglish && function_exists('bn_to_en')) {
                $_pvidTitle = bn_to_en($_pvidTitle);
            }

            // ★ v3.107: Extract embed URL + poster image for click-to-play
            //    DON'T render iframe directly (causes autoplay on page load).
            //    Instead: show poster + play button, load iframe on click.
            $_pvidEmbedUrl = '';
            $_pvidPoster = '';

            // Step 1: Extract the video URL from the code field
            if (!empty($_pvidCode)) {
                // If code contains an <iframe>, extract its src
                if (preg_match('/src=["\']?([^"\'>\s]+)["\']?/i', $_pvidCode, $_srcM)) {
                    $_pvidEmbedUrl = $_srcM[1];
                } else {
                    // Code is a plain URL
                    $_pvidEmbedUrl = trim($_pvidCode);
                }
            }

            // Step 2: Convert to proper embed URL + get poster
            if (!empty($_pvidEmbedUrl)) {
                // YouTube: extract video ID → embed URL + thumbnail
                if (preg_match('#(?:youtube\.com/(?:watch\?v=|embed/|v/|shorts/)|youtu\.be/|youtube-nocookie\.com/embed/)([A-Za-z0-9_-]{6,15})#', $_pvidEmbedUrl, $_ytM)) {
                    $_pvidEmbedUrl = 'https://www.youtube.com/embed/' . $_ytM[1] . '?autoplay=1&rel=0';
                    $_pvidPoster = 'https://img.youtube.com/vi/' . $_ytM[1] . '/hqdefault.jpg';
                }
                // Vimeo
                elseif (preg_match('#vimeo\.com/(?:video/)?(\d+)#', $_pvidEmbedUrl, $_vmM)) {
                    $_pvidEmbedUrl = 'https://player.vimeo.com/video/' . $_vmM[1] . '?autoplay=1';
                    // Vimeo needs API for thumbnail — use placeholder
                    $_pvidPoster = '';
                }
                // Facebook
                elseif (preg_match('#facebook\.com#i', $_pvidEmbedUrl)) {
                    $_pvidEmbedUrl = 'https://www.facebook.com/plugins/video.php?href=' . rawurlencode($_pvidEmbedUrl) . '&show_text=false&width=560&height=315&autoplay=1';
                    $_pvidPoster = '';
                }
                // Direct video file (.mp4 etc.)
                elseif (preg_match('#\.(mp4|webm|ogg|mov|m4v)#i', $_pvidEmbedUrl)) {
                    // Keep the URL as-is; will use <video> tag on click
                    // Poster = the video file itself (will show first frame via <video preload="metadata">)
                    $_pvidPoster = 'video-file:' . $_pvidEmbedUrl;
                }
                // If it's already an embed URL (youtube.com/embed/, player.vimeo.com), keep it
                // but ensure autoplay=1 is present for click-to-play
                else {
                    if (strpos($_pvidEmbedUrl, 'youtube.com/embed/') !== false || strpos($_pvidEmbedUrl, 'player.vimeo.com') !== false) {
                        if (strpos($_pvidEmbedUrl, 'autoplay=1') === false) {
                            $_pvidEmbedUrl .= (strpos($_pvidEmbedUrl, '?') !== false ? '&' : '?') . 'autoplay=1';
                        }
                    }
                }
            }

            // Step 3: Render card with poster + play button (NO iframe loaded yet)
            $_videoPageHTML .= '<div class="video-card" data-embed="' . htmlspecialchars($_pvidEmbedUrl) . '"' . (strpos($_pvidPoster, 'video-file:') === 0 ? ' data-video-file="' . htmlspecialchars(substr($_pvidPoster, 11)) . '"' : '') . '>';
            $_videoPageHTML .= '<div class="video-embed-box">';

            if (!empty($_pvidPoster) && strpos($_pvidPoster, 'video-file:') !== 0) {
                // Image poster (YouTube thumbnail)
                $_videoPageHTML .= '<img class="video-poster" src="' . htmlspecialchars($_pvidPoster) . '" alt="' . htmlspecialchars($_pvidTitle) . '" loading="lazy">';
            } elseif (strpos($_pvidPoster, 'video-file:') === 0) {
                // Direct video file — use <video preload="metadata"> to show first frame
                $_videoFileUrl = substr($_pvidPoster, 11);
                $_videoPageHTML .= '<video class="video-poster" src="' . htmlspecialchars($_videoFileUrl) . '" muted preload="metadata" style="object-fit:cover;"></video>';
            } else {
                // No poster available (Facebook/Vimeo without API) — dark placeholder
                $_videoPageHTML .= '<div class="video-poster-placeholder"><span class="vpp-label"><i class="fas fa-video"></i> Video</span></div>';
            }

            // Play button overlay
            $_videoPageHTML .= '<div class="video-play-btn"><i class="fas fa-play"></i></div>';
            $_videoPageHTML .= '</div>'; // .video-embed-box

            $_videoPageHTML .= '<div class="video-card-body">';
            $_videoPageHTML .= '<h3 class="video-card-title">' . htmlspecialchars($_pvidTitle) . '</h3>';
            if ($_pvidDate) {
                $_videoPageHTML .= '<p class="video-card-date">' . $_pvidDate . '</p>';
            }
            $_videoPageHTML .= '</div></div>'; // .video-card-body + .video-card
        }
        $_videoPageHTML .= '</div>';
    } else {
        $_videoPageHTML .= '<div class="no-videos"><i class="fas fa-video"></i><h3>' . $_noVideosMsg . '</h3><p>' . $_noVideosDesc . '</p></div>';
    }

    $_videoPageHTML .= '</div>'; // .content-wrap

    // Footer
    $_videoPageHTML .= <<<HTML
<footer class="app-footer">
    <div class="ft-brand">{$L['appName']}</div>
    <div>Truthy &amp; Trusted News Portal</div>
    <div style="margin-top:8px;">&copy; 2026 {$L['appName']}</div>
</footer>
<button class="fab-sidebar" id="fabSidebar" aria-label="Menu"><i class="fas fa-bars"></i></button>
HTML;

    // Sidebar (same as home page)
    $_videoPageHTML .= '<div class="app-sidebar" id="appSidebar">';
    $_videoPageHTML .= '<div class="sb-header"><h3>' . $L['appName'] . '</h3><button class="sb-close" id="sbClose">&times;</button></div>';
    $_videoPageHTML .= '<div class="sb-section"><h4>' . $L['allCats'] . '</h4>';
    foreach ($catList as $_vpcat) {
        $_vpcatName = $_vpcat['name'];
        $_vpcatDisplay = $_vpcat['displayName'];
        $_vpcatHref = $_vpcat['href'];
        $_vpcatIcon = $_vpcat['icon'];
        $_vpcatCount = $_vpcat['count'];
        $_vpIsActive = ($page === 'videos' && $_vpcatName === 'videos_page') ? ' active' : '';
        $_vpCountBadge = $_vpcatCount > 0 ? '<span class="sb-cat-count">' . (int)$_vpcatCount . '</span>' : '';
        $_videoPageHTML .= '<a href="' . htmlspecialchars($_vpcatHref) . '" class="sb-cat-item' . $_vpIsActive . '"><i class="fas ' . htmlspecialchars($_vpcatIcon) . '"></i><span class="sb-cat-name">' . htmlspecialchars($_vpcatDisplay) . '</span>' . $_vpCountBadge . '</a>';
    }
    $_videoPageHTML .= '</div></div>';

    // JavaScript (date/time, search, sidebar)
    $_videoPageHTML .= <<<JS
<script>
(function(){
    function updateDateTime(){try{var n=new Date(),i=n.getTime()+(5.5*60*60*1000),d=new Date(i),dt=d.getUTCDate(),mn=['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'],h=d.getUTCHours(),m=d.getUTCMinutes(),s=d.getUTCSeconds(),ampm=h>=12?'PM':'AM';h=h%12;if(h===0)h=12;var ds=dt+' '+mn[d.getUTCMonth()]+' '+d.getUTCFullYear();var ts=h+':'+(m<10?'0':'')+m+':'+(s<10?'0':'')+s+' '+ampm;var e1=document.getElementById('hdDate'),e2=document.getElementById('hdTime');if(e1)e1.textContent=ds;if(e2)e2.textContent=ts;}catch(e){}}
    updateDateTime();setInterval(updateDateTime,1000);
    var fab=document.getElementById('fabSidebar'),sb=document.getElementById('appSidebar'),sbc=document.getElementById('sbClose');
    function openSb(){sb.classList.add('open');document.body.classList.add('sidebar-open');}
    function closeSb(){sb.classList.remove('open');document.body.classList.remove('sidebar-open');}
    if(fab)fab.addEventListener('click',openSb);
    if(sbc)sbc.addEventListener('click',closeSb);
    document.addEventListener('click',function(e){if(!document.body.classList.contains('sidebar-open'))return;if(sb&&sb.contains(e.target))return;if(e.target===fab)return;closeSb();});
    if(sb){sb.querySelectorAll('a').forEach(function(l){l.addEventListener('click',closeSb);});}
    var mb=document.getElementById('menuBtn');
    if(mb)mb.addEventListener('click',openSb);

    // ★ v3.107: Click-to-play for video cards
    //    Videos DON'T load until user clicks. On click, the poster+play button
    //    is replaced with an iframe (YouTube/Vimeo/Facebook) or <video> (direct files).
    //    The embed URL has autoplay=1 so it starts playing immediately on click.
    document.querySelectorAll('.video-card[data-embed]').forEach(function(card){
        card.addEventListener('click', function(e){
            e.preventDefault();
            e.stopPropagation();
            // Don't re-load if already playing
            if(card.classList.contains('video-playing')) return;
            card.classList.add('video-playing');

            var embedUrl = card.getAttribute('data-embed');
            var videoFile = card.getAttribute('data-video-file');
            var embedBox = card.querySelector('.video-embed-box');
            if(!embedBox) return;

            if(videoFile){
                // Direct video file → <video> tag with controls
                embedBox.innerHTML = '<video controls autoplay preload="metadata" style="position:absolute;top:0;left:0;width:100%!important;height:100%!important;border:0;object-fit:contain;"><source src="' + videoFile + '"></video>';
            } else if(embedUrl){
                // YouTube/Vimeo/Facebook → <iframe>
                var ifr=document.createElement('iframe');
                ifr.src=embedUrl;
                ifr.setAttribute('frameborder','0');
                ifr.setAttribute('allowfullscreen','1');
                ifr.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture');
                ifr.style.cssText='position:absolute;top:0;left:0;width:100%!important;height:100%!important;border:0;z-index:10;';
                embedBox.innerHTML='';
                embedBox.appendChild(ifr);
            }
        });
    });
})();
</script>
JS;

    $_videoPageHTML .= '</body></html>';
    echo $_videoPageHTML;
    return;
}

// ★ Home / Category / Search page — render new UI

// ★ Home / Category / Search page — render new UI
$newHTML = <<<HTML
<!DOCTYPE html>
<html lang="{$langAttr}" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0,maximum-scale=5.0">
<meta name="theme-color" content="#B71C1C">
<title>{$L['appName']}</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root {
    --red: #B71C1C;
    --red-dark: #7F0000;
    --gold: #D4950A;
    --bg: #F4F1EB;
    --card-bg: #FFFFFF;
    --text: #1A1A1A;
    --muted: #666;
    --border: #E5E0D8;
    --tab-bg: #F5F5F5;
}
* { margin: 0; padding: 0; box-sizing: border-box; }
body {
    font-family: 'Hind Siliguri', sans-serif;
    background: var(--bg);
    color: var(--text);
    line-height: 1.5;
    -webkit-tap-highlight-color: transparent;
}
a { color: inherit; text-decoration: none; }
img { max-width: 100%; display: block; }

/* ═══════ TOP HEADER (compact, like reference image) ═══════ */
.app-header {
    background: var(--red);
    color: #fff;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
    overflow: hidden;
}
.app-header .brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Noto Serif Bengali', serif;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: .3px;
    white-space: nowrap;
    flex-shrink: 0;
    z-index: 2;
}
.app-header .brand i {
    font-size: 18px;
    color: var(--gold);
}
.app-header .actions {
    display: flex;
    align-items: center;
    gap: 4px;
    flex-shrink: 1;
    margin-left: auto;
    z-index: 1;
}
.app-header .icon-btn {
    width: 38px;
    height: 38px;
    border: none;
    background: rgba(255,255,255,.12);
    color: #fff;
    border-radius: 50%;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    transition: background .2s;
    position: relative;
}
.app-header .icon-btn:hover { background: rgba(255,255,255,.25); }
.app-header .icon-btn .badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: var(--gold);
    color: #000;
    font-size: 9px;
    font-weight: 700;
    padding: 1px 5px;
    border-radius: 8px;
    min-width: 14px;
    text-align: center;
}

/* Search overlay */
.search-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.6);
    z-index: 200;
    align-items: flex-start;
    justify-content: center;
    padding: 60px 16px 0;
}
.search-overlay.open { display: flex; }
.search-overlay form {
    width: 100%;
    max-width: 500px;
    background: #fff;
    border-radius: 8px;
    overflow: hidden;
    display: flex;
    box-shadow: 0 8px 30px rgba(0,0,0,.3);
}
.search-overlay input {
    flex: 1;
    border: none;
    padding: 14px 16px;
    font-size: 15px;
    font-family: inherit;
    outline: none;
    color: var(--text);
}
.search-overlay button {
    border: none;
    background: var(--red);
    color: #fff;
    padding: 0 18px;
    cursor: pointer;
    font-size: 16px;
}
.search-overlay button:hover { background: var(--red-dark); }

/* ═══════ BREAKING BAR ═══════ */
.breaking-bar {
    background: var(--red-dark);
    color: #fff;
    overflow: hidden;
    white-space: nowrap;
    height: 32px;
    display: flex;
    align-items: center;
    position: relative;
}
.breaking-bar .label {
    background: var(--gold);
    color: #000;
    padding: 0 12px;
    height: 100%;
    display: flex;
    align-items: center;
    font-weight: 700;
    font-size: 11px;
    letter-spacing: .5px;
    flex-shrink: 0;
    z-index: 2;
}
.breaking-bar .track {
    display: flex;
    align-items: center;
    height: 100%;
    width: max-content;
    animation: scroll-x 80s linear infinite;
    padding-left: 20px;
}
.breaking-bar:hover .track { animation-play-state: paused; }
.breaking-bar .track span {
    padding: 0 30px;
    font-size: 12px;
    font-weight: 500;
}
@keyframes scroll-x {
    0% { transform: translateX(0); }
    100% { transform: translateX(-50%); }
}

/* ═══════ CATEGORY TABS (horizontal scrollable) ═══════ */
.cat-tabs {
    background: var(--tab-bg);
    border-bottom: 1px solid var(--border);
    overflow-x: auto;
    scrollbar-width: none;
    display: flex;
    align-items: center;
    padding: 0 6px;
    position: sticky;
    top: 58px;
    z-index: 90;
}
.cat-tabs::-webkit-scrollbar { display: none; }
.cat-tab {
    padding: 10px 14px;
    font-size: 12.5px;
    font-weight: 600;
    color: var(--text);
    white-space: nowrap;
    border-bottom: 2px solid transparent;
    flex-shrink: 0;
    transition: all .2s;
}
.cat-tab:hover { color: var(--red); }
.cat-tab.active {
    color: var(--red);
    border-bottom-color: var(--red);
    font-weight: 700;
}
.cat-tab.home-tab {
    color: var(--red);
    font-weight: 700;
}
.cat-tab .ct-icon { margin-right: 4px; font-size: 11px; opacity: .6; }

/* ★ v2.6: "More" tab CSS removed (More tab was removed in v2.4) */

/* Language switch button in header */
.icon-btn.lang-btn {
    background: rgba(255,255,255,.18);
    font-size: 11px;
    font-weight: 700;
    padding: 0 10px;
    width: auto;
    min-width: 38px;
    border-radius: 19px;
    letter-spacing: .3px;
}
.icon-btn.lang-btn:hover { background: rgba(255,255,255,.3); }

/* ★ v3.28: Date & time — always visible (article page) */
.header-datetime {
    display: flex !important;
    flex-direction: column !important;
    align-items: center;
    gap: 0;
    background: rgba(0,0,0,.25);
    padding: 2px 5px;
    border-radius: 3px;
    margin-right: 2px;
    white-space: nowrap;
    line-height: 1.1;
    flex-shrink: 0;
    border: 1px solid rgba(255,255,255,.1);
}
.header-datetime .hd-date {
    font-weight: 500;
    font-size: 8px;
    color: rgba(255,255,255,.65);
    display: block;
    letter-spacing: 0.2px;
}
.header-datetime .hd-time {
    font-family: monospace;
    color: #80DEEA;
    font-weight: 700;
    font-size: 9px;
    line-height: 1.15;
    letter-spacing: 0.3px;
    display: flex;
    align-items: center;
    justify-content: center;
}
.header-datetime .hd-live {
    display: inline-block;
    width: 3px;
    height: 3px;
    background: #69F0AE;
    border-radius: 50%;
    margin-right: 2px;
    animation: hdLivePulse 1.5s ease-in-out infinite;
    flex-shrink: 0;
}
@keyframes hdLivePulse {
    0%, 100% { opacity: 1; }
    50% { opacity: .2; }
}
/* ★ v3.28: Date & time — always visible (was hidden on <520px) */
.header-datetime {
    display: flex !important;
}

/* ═══════ FEATURED NEWS ═══════ */
.content-wrap { padding: 12px 0; }

.featured-card {
    margin: 0 12px 16px;
    background: var(--card-bg);
    border-radius: 10px;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,.06);
    cursor: pointer;
    transition: transform .2s;
}
.featured-card:active { transform: scale(.99); }
.featured-card .fc-img {
    width: 100%;
    aspect-ratio: 16/9;
    object-fit: cover;
    background: #eee;
}
.featured-card .fc-body { padding: 14px 14px 12px; }
.featured-card .fc-cat {
    display: inline-block;
    background: var(--red);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 3px;
    letter-spacing: .3px;
    margin-bottom: 8px;
    text-transform: uppercase;
}
.featured-card .fc-title {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 18px;
    font-weight: 700;
    line-height: 1.35;
    color: var(--text);
    margin-bottom: 8px;
}
.featured-card .fc-meta {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 11px;
    color: var(--muted);
}
.featured-card .fc-meta i { margin-right: 3px; font-size: 10px; }
.featured-card .fc-meta .author { color: var(--red); font-weight: 600; }

/* ═══════ SIDE FEATURED ITEMS (compact list) ═══════ */
.side-list {
    margin: 0 12px 16px;
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}
.side-item {
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    cursor: pointer;
    transition: transform .2s;
}
.side-item:active { transform: scale(.98); }
.side-item .si-img {
    width: 100%;
    aspect-ratio: 16/10;
    object-fit: cover;
    background: #eee;
}
.side-item .si-body { padding: 8px 10px 10px; }
.side-item .si-cat {
    font-size: 9px;
    color: var(--red);
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 3px;
    letter-spacing: .3px;
}
.side-item .si-title {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
    color: var(--text);
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* ═══════ SECTION TITLE ═══════ */
.section-head {
    padding: 12px 14px 8px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.section-head .sh-title {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 16px;
    font-weight: 700;
    color: var(--text);
    border-left: 4px solid var(--red);
    padding-left: 10px;
}
.section-head .sh-link {
    font-size: 11px;
    color: var(--red);
    font-weight: 600;
}

/* ═══════ NEWS GRID (2-column, compact cards) ═══════ */
.news-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
    padding: 0 12px 16px;
}
.news-card {
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
    display: flex;
    flex-direction: column;
}
.news-card:active { transform: scale(.98); }
.news-card .nc-img {
    width: 100%;
    aspect-ratio: 16/10;
    object-fit: cover;
    background: #eee;
}
.news-card .nc-body { padding: 8px 10px 10px; flex: 1; display: flex; flex-direction: column; }
.news-card .nc-cat {
    font-size: 9px;
    color: var(--red);
    font-weight: 700;
    text-transform: uppercase;
    margin-bottom: 3px;
    letter-spacing: .3px;
    display: flex;
    align-items: center;
    gap: 2px;
    flex-wrap: wrap;
    line-height: 1.4;
}
/* ★ v3.88: Subcategory label next to category — "Category / Subcategory" format */
.news-card .nc-cat .nc-cat-sep {
    color: var(--muted);
    opacity: .6;
    font-weight: 400;
    margin: 0 1px;
}
/* ★ v3.88b: Subcategory — BOLD GOLD colour (user requested "bold gold colour") */
.news-card .nc-cat .nc-sub {
    color: #B8860B;
    font-weight: 900;
    text-transform: none;
    letter-spacing: 0;
    font-size: 10px;
    text-shadow: 0 0 1px rgba(184,134,11,.15);
}
/* ★ v3.88: Subcategory inside featured-card & side-item labels */
.featured-card .fc-cat .nc-cat-sep,
.side-item .si-cat .nc-cat-sep {
    color: rgba(255,255,255,.6);
    opacity: 1;
    font-weight: 400;
    margin: 0 2px;
}
/* ★ v3.88b: BOLD GOLD subcategory for featured-card & side-item */
.featured-card .fc-cat .nc-sub,
.side-item .si-cat .nc-sub {
    color: #FFD700;
    font-weight: 900;
    text-shadow: 0 1px 2px rgba(0,0,0,.3);
}
.news-card .nc-title {
    font-size: 12.5px;
    font-weight: 600;
    line-height: 1.35;
    color: var(--text);
    display: -webkit-box;
    -webkit-line-clamp: 3;
    -webkit-box-orient: vertical;
    overflow: hidden;
    margin-bottom: 6px;
}
.news-card .nc-time {
    font-size: 10px;
    color: var(--muted);
    margin-top: auto;
    display: flex;
    align-items: center;
    gap: 4px;
}
.news-card .nc-time i { font-size: 9px; }

/* ═══════ PAGINATION (v3.68) — 4 news per page, Prev/Next buttons ═══════ */
.pagination {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 12px 18px;
    flex-wrap: wrap;
}
.pagination .pg-btn {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: var(--card-bg);
    color: var(--red);
    border: 1px solid rgba(183,28,28,.3);
    border-radius: 20px;
    padding: 8px 16px;
    font-size: 12.5px;
    font-weight: 700;
    font-family: inherit;
    text-decoration: none;
    cursor: pointer;
    transition: background .2s, color .2s, transform .15s;
    box-shadow: 0 1px 3px rgba(183,28,28,.08);
}
.pagination .pg-btn:hover {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
    box-shadow: 0 3px 10px rgba(183,28,28,.25);
    transform: translateY(-1px);
}
.pagination .pg-btn:active { transform: scale(.97); }
.pagination .pg-btn.pg-disabled {
    color: var(--muted);
    background: var(--bg);
    border-color: var(--border);
    box-shadow: none;
    cursor: not-allowed;
    opacity: .55;
}
.pagination .pg-btn.pg-disabled:hover {
    background: var(--bg);
    color: var(--muted);
    border-color: var(--border);
    transform: none;
    box-shadow: none;
}
.pagination .pg-info {
    font-size: 12px;
    font-weight: 600;
    color: var(--text);
    background: rgba(183,28,28,.06);
    padding: 7px 14px;
    border-radius: 20px;
    border: 1px solid rgba(183,28,28,.15);
    white-space: nowrap;
}

/* ═══════ POPULAR NEWS — REMOVED (v3.40) ═══════ */
/* ★ v3.38: Sports section CSS REMOVED (duplicate — now in forced category panels) */
.popular-scroll {
    display: flex;
    gap: 10px;
    padding: 0 12px;
    overflow-x: auto;
    scrollbar-width: none;
}
.popular-scroll::-webkit-scrollbar { display: none; }
.pop-card {
    flex: 0 0 200px;
    background: var(--card-bg);
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 1px 4px rgba(0,0,0,.05);
    cursor: pointer;
    transition: transform .2s;
}
.pop-card:active { transform: scale(.98); }
.pop-card .pc-img {
    width: 100%;
    aspect-ratio: 16/10;
    object-fit: cover;
}
.pop-card .pc-body { padding: 8px 10px 10px; }
.pop-card .pc-title {
    font-size: 12px;
    font-weight: 600;
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.pop-card .pc-time {
    font-size: 10px;
    color: var(--muted);
    margin-top: 4px;
}

/* ═══════ TAGS — FULLY REMOVED (v3.14 + v3.17) ═══════ */
/* All tag-related UI removed: home page popular tags (v3.14), article bottom tags (v3.17),
   and in-content tags from index.php's single-tags section (v3.17 regex cleanup). */

/* ═══════ NO RESULTS ═══════ */
.no-results {
    text-align: center;
    padding: 60px 20px;
    color: var(--muted);
}
.no-results i {
    font-size: 48px;
    color: var(--border);
    margin-bottom: 12px;
}
.no-results h3 { font-size: 16px; color: var(--text); margin-bottom: 6px; }
.no-results a {
    display: inline-block;
    margin-top: 12px;
    padding: 8px 20px;
    background: var(--red);
    color: #fff;
    border-radius: 4px;
    font-weight: 600;
}

/* ═══════ FOOTER ═══════ */
.app-footer {
    background: var(--red-dark);
    color: rgba(255,255,255,.85);
    padding: 18px 14px 14px;
    text-align: center;
    font-size: 11px;
}
.app-footer .ft-brand {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 14px;
    font-weight: 700;
    color: #fff;
    margin-bottom: 4px;
}
.app-footer .ft-social {
    display: flex;
    justify-content: center;
    gap: 8px;
    margin: 10px 0;
}
.app-footer .ft-social a {
    width: 32px;
    height: 32px;
    border-radius: 50%;
    background: rgba(255,255,255,.1);
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-size: 13px;
    transition: background .2s;
}
.app-footer .ft-social a:hover { background: rgba(255,255,255,.25); }
/* ★ v3.48: Retro counter-style visitor count */
.app-footer .ft-counter {
    margin-top: 6px;
    padding-top: 6px;
    border-top: 1px solid rgba(255,255,255,.1);
    text-align: center;
}
.app-footer .counter-display {
    display: inline-flex;
    align-items: center;
    gap: 1px;
    margin-bottom: 2px;
}
.app-footer .counter-digit {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 11px;
    height: 15px;
    padding: 0 2px;
    background: #000;
    color: #fff;
    font-family: 'Hind Siliguri', 'Courier New', monospace;
    font-size: 9px;
    font-weight: 700;
    border-radius: 1px;
    border: 1px solid rgba(255,255,255,.15);
    box-shadow: 0 1px 1px rgba(0,0,0,.3);
    line-height: 1;
}
.app-footer .counter-sep {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 3px;
    color: rgba(255,255,255,.5);
    font-size: 8px;
    font-weight: 700;
}
.app-footer .counter-label {
    font-size: 7px;
    color: rgba(255,255,255,.7);
    text-transform: uppercase;
    letter-spacing: 1px;
    font-weight: 600;
}
/* ★ v3.51: Green live-dot before count (pulsing "site is live" indicator) */
.app-footer .counter-live-dot {
    display: inline-flex;
    align-items: center;
    width: 5px;
    height: 5px;
    background: #69F0AE;
    border-radius: 50%;
    margin-right: 3px;
    flex-shrink: 0;
    box-shadow: 0 0 4px rgba(105, 240, 174, 0.7);
    animation: livePulse 1.5s ease-in-out infinite;
}
@keyframes livePulse {
    0%, 100% { opacity: 1; transform: scale(1); }
    50% { opacity: 0.4; transform: scale(0.85); }
}

/* ═══════ FLOATING BUTTONS ═══════ */
.fab-sidebar {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 48px;
    height: 48px;
    background: var(--red);
    color: #fff;
    border: none;
    border-radius: 50%;
    font-size: 18px;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(183,28,28,.4);
    z-index: 95;
    display: flex;
    align-items: center;
    justify-content: center;
}

/* ★ v3.25: News notification popup (bell icon) */
.news-popup {
    display: none;
    position: fixed;
    bottom: 80px;
    right: 16px;
    width: 320px;
    max-width: calc(100vw - 32px);
    background: #fff;
    border-radius: 10px;
    box-shadow: 0 8px 30px rgba(0,0,0,.25);
    z-index: 9999;
    overflow: hidden;
    border-top: 4px solid var(--red);
    animation: popupSlideUp .3s ease;
}
.news-popup.open { display: block; }
@keyframes popupSlideUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}
.news-popup .np-header {
    background: var(--red);
    color: #fff;
    padding: 10px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    font-size: 13px;
    font-weight: 700;
}
.news-popup .np-header .np-title {
    display: flex;
    align-items: center;
    gap: 6px;
}
.news-popup .np-header .np-title i { color: var(--gold); }
.news-popup .np-close {
    background: rgba(255,255,255,.2);
    border: none;
    color: #fff;
    width: 24px;
    height: 24px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 14px;
    line-height: 1;
}
.news-popup .np-body {
    max-height: 300px;
    overflow-y: auto;
}
.news-popup .np-item {
    display: flex;
    gap: 10px;
    padding: 10px 14px;
    border-bottom: 1px solid var(--border);
    cursor: pointer;
    transition: background .15s;
}
.news-popup .np-item:hover { background: rgba(183,28,28,.04); }
.news-popup .np-item:last-child { border-bottom: none; }
.news-popup .np-item .npi-icon {
    width: 32px;
    height: 32px;
    background: rgba(183,28,28,.1);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--red);
    font-size: 12px;
    flex-shrink: 0;
}
/* ★ v3.46: Thumbnail image for today's news popup items */
.news-popup .np-item .npi-img {
    width: 50px;
    height: 50px;
    border-radius: 6px;
    overflow: hidden;
    flex-shrink: 0;
    background: #eee;
}
.news-popup .np-item .npi-img img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.news-popup .np-item .npi-cat {
    font-size: 9px;
    color: var(--red);
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .3px;
    margin-bottom: 2px;
    display: inline-block;
}
.news-popup .np-item .npi-text { flex: 1; min-width: 0; }
.news-popup .np-item .npi-title {
    font-size: 13px;
    font-weight: 600;
    color: var(--text);
    line-height: 1.35;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}
.news-popup .np-item .npi-time {
    font-size: 10px;
    color: var(--muted);
    margin-top: 2px;
}
.news-popup .np-empty {
    padding: 30px 20px;
    text-align: center;
    color: var(--muted);
    font-size: 13px;
}
.news-popup .np-empty i {
    font-size: 32px;
    color: var(--border);
    margin-bottom: 8px;
    display: block;
}
.icon-btn .badge.new-news {
    animation: badgePulse 1s ease-in-out infinite;
}
@keyframes badgePulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.3); }
}

/* ═══════ SIDEBAR (slide-in) ═══════ */
.app-sidebar {
    position: fixed;
    top: 0;
    left: -280px;
    bottom: 0;
    width: 280px;
    background: var(--card-bg);
    z-index: 200;
    box-shadow: 4px 0 24px rgba(0,0,0,.2);
    transition: left .3s;
    overflow-y: auto;
}
.app-sidebar.open { left: 0; }
.app-sidebar .sb-header {
    background: var(--red);
    color: #fff;
    padding: 16px 14px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.app-sidebar .sb-header h3 {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 16px;
    font-weight: 700;
}
.app-sidebar .sb-close {
    background: rgba(255,255,255,.15);
    border: none;
    color: #fff;
    width: 32px;
    height: 32px;
    border-radius: 50%;
    cursor: pointer;
    font-size: 16px;
}
.app-sidebar .sb-section { padding: 12px 14px; border-bottom: 1px solid var(--border); }
.app-sidebar .sb-section h4 {
    font-family: 'Noto Serif Bengali', serif;
    font-size: 13px;
    color: var(--red);
    margin-bottom: 8px;
    font-weight: 700;
}
.app-sidebar .sb-cat-item {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 7px 8px;
    border-radius: 5px;
    font-size: 13px;
    color: var(--text);
    transition: background .15s;
}
.app-sidebar .sb-cat-item:hover { background: rgba(183,28,28,.06); color: var(--red); }
.app-sidebar .sb-cat-item i {
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(183,28,28,.08);
    color: var(--red);
    border-radius: 4px;
    font-size: 11px;
    flex-shrink: 0;
}
/* ★ v3.43: Category name + news count badge in sidebar */
.app-sidebar .sb-cat-item .sb-cat-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.app-sidebar .sb-cat-item .sb-cat-count {
    margin-left: auto;
    background: var(--red);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 2px 8px;
    border-radius: 10px;
    min-width: 22px;
    text-align: center;
    flex-shrink: 0;
    line-height: 1.4;
}
.app-sidebar .sb-cat-item:hover .sb-cat-count {
    background: #fff;
    color: var(--red);
}
.app-sidebar .sb-cat-item.active {
    background: rgba(183,28,28,.1);
    color: var(--red);
}
/* ★ v3.45: Category wrapper + subcategory list */
.app-sidebar .sb-cat-wrap {
    position: relative;
}
.app-sidebar .sb-cat-wrap.has-subs .sb-cat-item {
    padding-right: 36px;
}
.app-sidebar .sb-sub-toggle {
    position: absolute;
    right: 4px;
    top: 50%;
    transform: translateY(-50%);
    width: 26px;
    height: 26px;
    background: rgba(183,28,28,.08);
    border: 1px solid rgba(183,28,28,.15);
    color: var(--red);
    border-radius: 4px;
    cursor: pointer;
    font-size: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all .2s;
    z-index: 5;
}
.app-sidebar .sb-sub-toggle:hover {
    background: rgba(183,28,28,.18);
}
.app-sidebar .sb-sub-toggle.open {
    background: var(--red);
    color: #fff;
    border-color: var(--red);
}
.app-sidebar .sb-sub-toggle i {
    transition: transform .3s;
}
.app-sidebar .sb-sub-toggle.open i {
    transform: rotate(180deg);
}
.app-sidebar .sb-sub-list {
    display: none;
    padding: 2px 0 4px 30px;
    margin: 0 0 2px 8px;
    border-left: 2px solid var(--border);
}
.app-sidebar .sb-sub-list.open {
    display: block;
}
.app-sidebar .sb-sub-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 8px;
    border-radius: 4px;
    font-size: 11.5px;
    color: var(--muted);
    transition: all .15s;
}
.app-sidebar .sb-sub-item:hover,
.app-sidebar .sb-sub-item.active {
    background: rgba(183,28,28,.06);
    color: var(--red);
}
.app-sidebar .sb-sub-item i {
    font-size: 6px;
    opacity: .4;
    flex-shrink: 0;
}
.app-sidebar .sb-sub-name {
    flex: 1;
    min-width: 0;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.app-sidebar .sb-sub-count {
    font-size: 9px;
    color: var(--muted);
    background: var(--bg);
    padding: 1px 5px;
    border-radius: 6px;
    flex-shrink: 0;
}

/* Overlay when sidebar open */
body.sidebar-open::before {
    content: '';
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.5);
    z-index: 150;
}
body.sidebar-open { overflow: hidden; }

/* ═══════ CATEGORY PANEL removed in v2.4 (categories now in sidebar) ═══════ */

/* ═══════ RESPONSIVE ═══════ */
@media (max-width: 380px) {
    .news-grid { grid-template-columns: 1fr; }
    .side-list { grid-template-columns: 1fr; }
    .featured-card .fc-title { font-size: 16px; }
}
@media (min-width: 768px) {
    .content-wrap { max-width: 720px; margin: 0 auto; }
    .news-grid { grid-template-columns: repeat(3, 1fr); }
    .side-list { grid-template-columns: repeat(4, 1fr); }
}
</style>
</head>
<body class="app-view">

<!-- ═══════ TOP HEADER ═══════ -->
<header class="app-header">
    <a href="?page=home" class="brand">
        <i class="fas fa-newspaper"></i>
        <span>{$L['appName']}&nbsp;&nbsp;</span>
    </a>
    <div class="actions">
        <!-- ★ v3.22: Date (top) + live time (bottom) with pulsing dot -->
        <div class="header-datetime" id="headerDateTime">
            <span class="hd-date" id="hdDate">--</span>
            <span class="hd-time"><span class="hd-live"></span><span id="hdTime">--:--</span></span>
        </div>
        <!-- ★ Language switch button — toggles between বাং / EN -->
        <a href="{$langSwitchHref}" class="icon-btn lang-btn" id="langSwitchBtn" aria-label="Switch Language" title="Switch Language">
            {$langSwitchLabel}
        </a>
        <button class="icon-btn" id="notifBtn" aria-label="Notifications">
            <i class="far fa-bell"></i>
            <span class="badge" id="notifBadge" style="display:none">0</span>
        </button>
        <button class="icon-btn" id="searchBtn" aria-label="Search">
            <i class="fas fa-search"></i>
        </button>
        <!-- ★ 3-dot (⋮) removed — hamburger ☰ menu below has all categories -->
        <button class="icon-btn" id="menuBtn" aria-label="Menu">
            <i class="fas fa-bars"></i>
        </button>
    </div>
</header>

<!-- Search Overlay -->
<div class="search-overlay" id="searchOverlay">
    <form action="?" method="GET">
        <input type="hidden" name="page" value="home">
        <input type="text" name="q" placeholder="{$L['search']}" autofocus>
        <button type="submit"><i class="fas fa-search"></i></button>
    </form>
</div>

HTML;

// ── Breaking bar ──────────────────────────────────────────────────
if (!empty($breakingItems)) {
    $breakingTrack = '';
    foreach ($breakingItems as $bi) {
        $breakingTrack .= '<span>' . htmlspecialchars($bi) . '</span>';
    }
    // Duplicate for marquee
    $breakingTrack .= $breakingTrack;
    $newHTML .= <<<HTML
<div class="breaking-bar">
    <div class="label"><i class="fas fa-bolt"></i> {$L['breaking']}</div>
    <div class="track">{$breakingTrack}</div>
</div>
HTML;
}

// ── Category tabs (only 4 visible — rest accessed via hamburger ☰ sidebar) ──
// ★ v2.6: $hiddenCats variable removed (3-dot panel was removed in v2.4)
$visibleCats = array_slice($categories, 0, 4);

$newHTML .= '<div class="cat-tabs">';
$newHTML .= '<a href="?page=home" class="cat-tab home-tab' . ($page === 'home' && !$currentCat && !$searchQuery ? ' active' : '') . '"><i class="fas fa-home ct-icon"></i>' . $L['home'] . '</a>';
foreach ($visibleCats as $cat) {
    $isActive = ($currentCat === $cat['name']) ? ' active' : '';
    $newHTML .= '<a href="' . htmlspecialchars($cat['href']) . '" class="cat-tab' . $isActive . '"><i class="fas ' . htmlspecialchars($cat['icon']) . ' ct-icon"></i>' . htmlspecialchars($cat['name']) . '</a>';
}
$newHTML .= '</div>';

// ── Content area ──────────────────────────────────────────────────
$newHTML .= '<div class="content-wrap">';

// Featured news (home page only — NOT on category/search/article pages)
// ★ v3.69: Added !$currentCat so featured card doesn't appear on "More" → category pages
if ($page === 'home' && !$currentCat && !$searchQuery && !empty($featured['title'])) {
    $_featured_thumb = _renderCardThumb($featured['img'], 'fc-img');
    $newHTML .= <<<HTML
<a href="{$featured['href']}" class="featured-card">
    {$_featured_thumb}
    <div class="fc-body">
        <span class="fc-cat">{$featured['cat']}</span>
        <h2 class="fc-title">{$featured['title']}</h2>
        <div class="fc-meta">
            <span class="author"><i class="fas fa-user"></i>{$L['appName']}</span>
            <span><i class="far fa-clock"></i>{$L['latest']}</span>
        </div>
    </div>
</a>
HTML;
}

// ★ v3.69: "শীর্ষ সংবাদ" (Top Stories) — RESTORED on home page only.
//    Condition: $page === 'home' && !$currentCat && !$searchQuery
//    → Shows ONLY on true home page.
//    → Does NOT show on category pages (when user clicks "More" → ?cat=...)
//    → Does NOT show on search pages (?q=...)
//    → Does NOT show on single article pages (?page=single)
if ($page === 'home' && !$currentCat && !$searchQuery && !empty($sideItems)) {
    $topStoriesLabel = $isEnglish ? 'Top Stories' : 'শীর্ষ সংবাদ';
    $newHTML .= <<<HTML
<div class="section-head">
    <div class="sh-title">{$topStoriesLabel}</div>
</div>
<div class="side-list">
HTML;
    foreach ($sideItems as $si) {
        // ★ v3.88: Look up subcategory for side item via batch map
        $_si_sub = $ncGetSub($si['href']);
        // ★ v3.95: Parse cat field — avoid duplicate subcategory display
        $_v395_siParsed = _v395_parseCatSub($si['cat'], $_si_sub);
        $_si_cat = $_v395_siParsed['cat'];
        if (!empty($_v395_siParsed['sub'])) {
            $_si_sub = $_v395_siParsed['sub'];
        } elseif ($_v395_siParsed['subAlreadyInCat']) {
            $_si_sub = '';
        }
        if (function_exists('en_label') && $_si_sub !== '') {
            $_si_sub = en_label($_si_sub);
        }
        if (function_exists('en_label') && $_si_cat !== '') {
            $_si_cat = en_label($_si_cat);
        }
        // ★ v3.88: "Category / Subcategory" format
        $_si_catStr = '<div class="si-cat">' . htmlspecialchars($_si_cat);
        if (!empty($_si_sub)) {
            $_si_catStr .= '<span class="nc-cat-sep">/</span><span class="nc-sub">' . htmlspecialchars($_si_sub) . '</span>';
        }
        $_si_catStr .= '</div>';
        $_si_thumb = _renderCardThumb($si['img'], 'si-img');
        $newHTML .= <<<HTML
<a href="{$si['href']}" class="side-item">
    {$_si_thumb}
    <div class="si-body">
        {$_si_catStr}
        <div class="si-title">{$si['title']}</div>
    </div>
</a>
HTML;
    }
    $newHTML .= '</div>';
}

// ★ v3.32: "সর্বশেষ সংবাদ" (Latest News) section REMOVED from home page (user requested).
// Per-category panels below show all the news. Latest news grid only shows on search/category pages.
// News grid + section title — only show on search or category pages (NOT home)
if ($searchQuery || $currentCat) {

// ★ v3.79: NEW — On CATEGORY pages, FIRST show "সর্বশেষ সংবাদ" (Latest News) section
//    with ONLY 1 latest news item from the SAME category the user is browsing.
//    THEN below, show the category-specific news section (existing code below).
//    Home page stays UNCHANGED. Search page stays UNCHANGED.
//    Only fires when $currentCat is set AND not a search query.
//    ★ v3.80: Changed from "6 items across all categories" to
//             "1 item from the same category", rendered as a big featured card.
if ($currentCat && !$searchQuery && isset($pdo)) {
    $_v379_latestCards = [];
    try {
        @$pdo->exec("SET NAMES utf8mb4");
        @$pdo->exec("SET time_zone = '+05:30'");

        // Build query — filter by SAME category (and subcategory if set)
        $_v379_cat = isset($_GET['cat']) ? $_GET['cat'] : '';
        $_v379_sub = isset($_GET['sub']) ? (int)$_GET['sub'] : 0;

        // ★ v3.89 FIX: Removed correlated subquery — same fix as v3.78.
        //    Subcategory fetched by batch lookup ($ncGetSub) during rendering.
        $_v379_sql = "SELECT n.id, n.title, n.image, n.created_at, c.name as cat_name
                      FROM news n
                      LEFT JOIN categories c ON n.category_id = c.id
                      WHERE n.status = 'published'
                        AND n.created_at <= NOW()
                        AND n.created_at >= (NOW() - INTERVAL 30 DAY)";
        $_v379_params = array();

        if ($_v379_cat !== '') {
            $_v379_sql .= " AND c.name = ?";
            $_v379_params[] = $_v379_cat;
        }
        if ($_v379_sub > 0) {
            $_v379_sql .= " AND n.id IN (SELECT news_id FROM news_subcategories WHERE subcategory_id = ?)";
            $_v379_params[] = $_v379_sub;
        }

        // ★ v3.80: LIMIT 1 — only ONE latest news item from the same category
        $_v379_sql .= " ORDER BY n.created_at DESC LIMIT 1";

        $_v379_stmt = $pdo->prepare($_v379_sql);
        $_v379_stmt->execute($_v379_params);
        $_v379_rows = $_v379_stmt->fetchAll(PDO::FETCH_ASSOC);

        $_v379_placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";

        foreach ($_v379_rows as $_v379_row) {
            // Image URL (same logic as v3.78 block)
            $_v379_img = $_v379_placeholder;
            if (!empty($_v379_row['image'])) {
                $_v379_imgRaw = trim($_v379_row['image']);
                if ($_v379_imgRaw !== '' && $_v379_imgRaw !== '0') {
                    if (preg_match('#^https?://#i', $_v379_imgRaw) || strpos($_v379_imgRaw, '//') === 0) {
                        $_v379_img = $_v379_imgRaw;
                    } else {
                        while (strpos($_v379_imgRaw, 'uploads/uploads/') === 0) {
                            $_v379_imgRaw = substr($_v379_imgRaw, 8);
                        }
                        if (strpos($_v379_imgRaw, 'uploads/') !== 0 && strpos($_v379_imgRaw, '/') !== 0) {
                            $_v379_img = 'uploads/' . $_v379_imgRaw;
                        } else {
                            $_v379_img = $_v379_imgRaw;
                        }
                    }
                }
            }

            // Time ago
            $_v379_time = '';
            if (function_exists('timeAgo')) {
                $_v379_time = timeAgo($_v379_row['created_at']);
            } else {
                $_v379_time = date('d M Y', strtotime($_v379_row['created_at']));
            }

            $_v379_latestCards[] = [
                'href'  => '?page=single&id=' . (int)$_v379_row['id'],
                'img'   => $_v379_img,
                'title' => $_v379_row['title'],
                'cat'   => $_v379_row['cat_name'],
                'sub'   => '',  // ★ v3.89: Populated by $ncGetSub() during rendering
                'time'  => $_v379_time,
            ];
        }
    } catch (Exception $_v379_e) {
        // Silent fail — section just won't appear
    }

    // Render "সর্বশেষ সংবাদ" section — ONE big featured-card style item
    // ★ v3.87: If on a subcategory page, show the subcategory name (e.g., "কলকাতা")
    //    instead of "সর্বশেষ সংবাদ". $_subName is set earlier in the section-title logic.
    if (!empty($_v379_latestCards)) {
        if (!empty($_subName)) {
            $_v379_latestLabel = $_subName;  // Show subcategory name (কলকাতা)
        } else {
            $_v379_latestLabel = $isEnglish ? 'Latest News' : 'সর্বশেষ সংবাদ';
        }
        $newHTML .= <<<HTML
<div class="section-head">
    <div class="sh-title">{$_v379_latestLabel}</div>
</div>
HTML;
        foreach ($_v379_latestCards as $_v379_nc) {
            // Apply translation for English mode (same logic as main grid below)
            $_v379_title = $_v379_nc['title'];
            $_v379_cat   = $_v379_nc['cat'];
            // ★ v3.88: Get subcategory (from DB field or batch lookup map)
            $_v379_sub   = !empty($_v379_nc['sub']) ? $_v379_nc['sub'] : $ncGetSub($_v379_nc['href']);
            // ★ v3.95: Parse cat field — avoid duplicate subcategory display
            $_v395_v379parsed = _v395_parseCatSub($_v379_cat, $_v379_sub);
            $_v379_cat = $_v395_v379parsed['cat'];
            if (!empty($_v395_v379parsed['sub'])) {
                $_v379_sub = $_v395_v379parsed['sub'];
            } elseif ($_v395_v379parsed['subAlreadyInCat']) {
                $_v379_sub = '';
            }
            if ($isEnglish && function_exists('bn_to_en')) {
                $_v379_title = bn_to_en($_v379_title);
                if (function_exists('en_label')) {
                    $_v379_cat = en_label($_v379_cat);
                    if ($_v379_sub !== '') $_v379_sub = en_label($_v379_sub);
                }
            } elseif (function_exists('en_label')) {
                if ($_v379_cat !== '') $_v379_cat = en_label($_v379_cat);
                if ($_v379_sub !== '') $_v379_sub = en_label($_v379_sub);
            }
            $_v379_timeHtml = !empty($_v379_nc['time']) ? '<span><i class="far fa-clock"></i> ' . htmlspecialchars($_v379_nc['time']) . '</span>' : '';
            // ★ v3.88: "Category / Subcategory" format on featured card
            $_v379_catHtml = '';
            if (!empty($_v379_cat) || !empty($_v379_sub)) {
                if (!empty($_v379_cat)) $_v379_catHtml .= htmlspecialchars($_v379_cat);
                if (!empty($_v379_cat) && !empty($_v379_sub)) $_v379_catHtml .= '<span class="nc-cat-sep">/</span>';
                if (!empty($_v379_sub)) $_v379_catHtml .= '<span class="nc-sub">' . htmlspecialchars($_v379_sub) . '</span>';
            }
            // ★ v3.80: Use featured-card style (big, full-width) — same as home page
            $_v379_thumb = _renderCardThumb($_v379_nc['img'], 'fc-img');
            $newHTML .= <<<HTML
<a href="{$_v379_nc['href']}" class="featured-card">
    {$_v379_thumb}
    <div class="fc-body">
        <span class="fc-cat">{$_v379_catHtml}</span>
        <h2 class="fc-title">{$_v379_title}</h2>
        <div class="fc-meta">
            <span class="author"><i class="fas fa-user"></i>{$L['appName']}</span>
            {$_v379_timeHtml}
        </div>
    </div>
</a>
HTML;
        }
    }
}

// Section title
$newHTML .= <<<HTML
<div class="section-head">
    <div class="sh-title">{$sectionTitle}</div>
</div>
HTML;

// News grid
// ★ v3.68: Pagination — show only 4 news per page, with Prev/Next buttons.
//    Page number comes from $_GET['p'] (1-based). Preserves cat/q/sub/lang params.
// ★ v3.71: $newsCards contains .nc cards + .cat-panel-cards (merged with dedup).
//    This is the SAME source the home page uses, so category page shows the same
//    latest news in the same order. array_slice preserves order — just takes 4 at a time.
if (!empty($newsCards)) {
    $perPage     = 4;
    $totalCards  = count($newsCards);
    $totalPages  = $totalCards > 0 ? (int)ceil($totalCards / $perPage) : 1;
    // ★ v3.74: Use $moPageNum (from 'mop' GET param) instead of $_GET['p'].
    //    'p' is index.php's own pagination param — using it here caused conflicts
    //    and made the category page show only index.php's page-1 cards.
    $currentPage = max(1, $moPageNum);
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset     = ($currentPage - 1) * $perPage;
    $pagedCards = array_slice($newsCards, $offset, $perPage);

    $newHTML .= '<div class="news-grid">';
    foreach ($pagedCards as $nc) {
        // ★ v3.75: Apply bn_to_en() for English mode (database titles are Bengali)
        $_nc_title = $nc['title'];
        $_nc_cat   = $nc['cat'];
        // ★ v3.88: Get subcategory (from DB field or batch lookup map)
        $_nc_sub_raw = !empty($nc['sub']) ? $nc['sub'] : $ncGetSub($nc['href']);
        $_nc_sub = $_nc_sub_raw;
        // ★ v3.95: Parse cat field — index.php HTML may already have "Category / Subcategory"
        //    If so, extract the embedded subcategory and avoid DUPLICATE display.
        $_v395_parsed = _v395_parseCatSub($_nc_cat, $_nc_sub);
        $_nc_cat = $_v395_parsed['cat'];
        if (!empty($_v395_parsed['sub'])) {
            // Subcategory was embedded in cat field — use it
            $_nc_sub = $_v395_parsed['sub'];
        } elseif ($_v395_parsed['subAlreadyInCat']) {
            // DB subcategory is already in cat field — don't add again (avoid duplicate)
            $_nc_sub = '';
        }
        // ★ v3.98: Collect per-card debug info
        $_v398_cardDebug[] = [
            'href' => $nc['href'] ?? '',
            'cat_orig' => $nc['cat'] ?? '',
            'sub_from_db' => $_nc_sub_raw,
            'sub_final' => $_nc_sub,
            'subAlreadyInCat' => $_v395_parsed['subAlreadyInCat'],
            'has_slash_in_cat' => strpos($nc['cat'] ?? '', '/') !== false,
            'ncSubMap_has' => isset($ncSubMap[(int)preg_replace('/.*[?&]id=(\d+).*/', '$1', $nc['href'] ?? '0')]),
        ];
        if ($isEnglish && function_exists('bn_to_en')) {
            $_nc_title = bn_to_en($_nc_title);
            if (function_exists('en_label')) {
                $_nc_cat = en_label($_nc_cat);
                if ($_nc_sub !== '') $_nc_sub = en_label($_nc_sub);
            }
        } elseif (function_exists('en_label')) {
            if ($_nc_cat !== '') $_nc_cat = en_label($_nc_cat);
            if ($_nc_sub !== '') $_nc_sub = en_label($_nc_sub);
        }
        $timeStr = !empty($nc['time']) ? '<div class="nc-time"><i class="far fa-clock"></i>' . htmlspecialchars($nc['time']) . '</div>' : '';
        // ★ v3.88: "Category / Subcategory" format — red category, gold subcategory, grey slash
        $catStr = '';
        if (!empty($_nc_cat) || !empty($_nc_sub)) {
            $catStr = '<div class="nc-cat">';
            if (!empty($_nc_cat))   $catStr .= htmlspecialchars($_nc_cat);
            if (!empty($_nc_cat) && !empty($_nc_sub)) $catStr .= '<span class="nc-cat-sep">/</span>';
            if (!empty($_nc_sub))   $catStr .= '<span class="nc-sub">' . htmlspecialchars($_nc_sub) . '</span>';
            $catStr .= '</div>';
        }
        // ★ v3.103: Render thumbnail — <video> if image is .mp4, else <img>
        $_nc_thumb = _renderCardThumb($nc['img'], 'nc-img');
        $newHTML .= <<<HTML
<a href="{$nc['href']}" class="news-card">
    {$_nc_thumb}
    <div class="nc-body">
        {$catStr}
        <div class="nc-title">{$_nc_title}</div>
        {$timeStr}
    </div>
</a>
HTML;
    }
    $newHTML .= '</div>';

    // ★ v3.74: Pagination controls — use 'mop' param (NOT 'p' which belongs to index.php)
    if ($totalPages > 1) {
        // Build base query string preserving cat / q / sub / lang
        $baseParams = '';
        if (!empty($currentCat))  $baseParams .= '&cat=' . urlencode($currentCat);
        if (!empty($searchQuery)) $baseParams .= '&q=' . urlencode($searchQuery);
        if (isset($_GET['sub']) && $_GET['sub'] !== '') $baseParams .= '&sub=' . (int)$_GET['sub'];
        if (isset($_GET['lang']) && in_array($_GET['lang'], ['bn','en'], true)) {
            $baseParams .= '&lang=' . $_GET['lang'];
        }

        $prevPage = max(1, $currentPage - 1);
        $nextPage = min($totalPages, $currentPage + 1);

        // ★ v3.74: Use 'mop' (mo page) instead of 'p'
        $prevHref = '?page=home' . $baseParams . '&mop=' . $prevPage;
        $nextHref = '?page=home' . $baseParams . '&mop=' . $nextPage;

        $prevLabel = $isEnglish ? '&#8592; Previous' : '&#8592; আগের';
        $nextLabel = $isEnglish ? 'Next &#8594;' : 'পরবর্তী &#8594;';
        $pageLabel = $isEnglish ? 'Page' : 'পৃষ্ঠা';

        $prevHtml = ($currentPage <= 1)
            ? '<span class="pg-btn pg-disabled">' . $prevLabel . '</span>'
            : '<a href="' . htmlspecialchars($prevHref) . '" class="pg-btn">' . $prevLabel . '</a>';
        $nextHtml = ($currentPage >= $totalPages)
            ? '<span class="pg-btn pg-disabled">' . $nextLabel . '</span>'
            : '<a href="' . htmlspecialchars($nextHref) . '" class="pg-btn">' . $nextLabel . '</a>';

        $newHTML .= <<<HTML
<div class="pagination">
    {$prevHtml}
    <span class="pg-info">{$pageLabel} {$currentPage} / {$totalPages}</span>
    {$nextHtml}
</div>
HTML;
    }
} else {
    $newHTML .= <<<HTML
<div class="no-results">
    <i class="far fa-newspaper"></i>
    <h3>{$L['noResult']}</h3>
    <a href="?page=home">{$L['backHome']}</a>
</div>
HTML;
}
} // end if (searchQuery || currentCat)

// ★ v3.40: "জনপ্রিয় সংবাদ" (Popular News) section REMOVED from home page (user requested).

// ★ v3.38: Duplicate "খেলাধুলা" sports panel REMOVED — already shown in forced category panels below.

// ★ v3.39: Force exactly 4 category panels: জাতীয়, আন্তর্জাতিক, খেলাধুলা, বিনোদন
// ★ v3.68: "শীর্ষ সংবাদ" (Top Stories) side-list removed above; only these 4 panels remain on home.
if ($page === 'home' && !$searchQuery && !$currentCat) {
    $forcedCats = [
        ['bn' => 'জাতীয়',       'en' => 'National',       'icon' => 'fa-flag'],
        ['bn' => 'আন্তর্জাতিক',  'en' => 'International',   'icon' => 'fa-globe'],
        ['bn' => 'খেলাধুলা',     'en' => 'Sports',          'icon' => 'fa-futbol'],
        ['bn' => 'বিনোদন',       'en' => 'Entertainment',   'icon' => 'fa-film'],
    ];

    foreach ($forcedCats as $fc) {
        $catNameBn = $fc['bn'];
        $catNameEn = $fc['en'];
        $catIcon   = $fc['icon'];
        $catLabel  = $isEnglish ? $catNameEn : $catNameBn;
        $catHref   = '?cat=' . urlencode($catNameBn);

        // Try to find this category's panel cards from index.php's HTML
        $panelCards = [];

        // Method 1: Find cat-panel-sec for this specific category
        if (preg_match('#<div class="cat-panel-sec">\s*<div class="cat-panel-head">\s*<h2 class="cat-panel-title"[^>]*>\s*<i class="fas [^"]+"></i>\s*(?:' . preg_quote($catNameBn, '#') . '|' . preg_quote($catNameEn, '#') . ')\s*</h2>.*?<div class="cat-panel-row">(.*?)</div>\s*</div>#us', $html, $catMatch)) {
            $catBody = $catMatch[1];
            if (preg_match_all('#<a href="([^"]+)"\s+class="cat-panel-card"[^>]*>(.*?)</a>#us', $catBody, $cardMatches)) {
                foreach ($cardMatches[1] as $cIdx => $cardHref) {
                    if ($cIdx >= 4) break;
                    $block = $cardMatches[2][$cIdx];
                    $cImg = $cTitle = $cTime = '';
                    if (preg_match('#<img[^>]+src="([^"]+)"#u', $block, $cim)) $cImg = $cim[1];
                    if (preg_match('#<h3[^>]*>([^<]+)</h3>#u', $block, $ctm)) $cTitle = html_entity_decode(trim($ctm[1]), ENT_QUOTES, 'UTF-8');
                    if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $block, $ctm2)) $cTime = trim($ctm2[1]);
                    if ($cTitle && $cImg) {
                        $panelCards[] = ['href' => $cardHref, 'img' => $cImg, 'title' => $cTitle, 'time' => $cTime];
                    }
                }
            }
        }

        // Method 2: Search in $newsCards for this category
        if (empty($panelCards)) {
            foreach ($newsCards as $nc) {
                $catField = $nc['cat'] ?? '';
                if ($catField && (
                    mb_strpos($catField, $catNameBn, 0, 'UTF-8') !== false ||
                    mb_strpos($catField, $catNameEn, 0, 'UTF-8') !== false
                )) {
                    $panelCards[] = ['href' => $nc['href'], 'img' => $nc['img'], 'title' => $nc['title'], 'time' => $nc['time'] ?? ''];
                    if (count($panelCards) >= 4) break;
                }
            }
        }

        // Method 3: Search ALL cat-panel-cards in HTML for this category name
        if (empty($panelCards)) {
            if (preg_match_all('#<a href="([^"]+)"\s+class="cat-panel-card"[^>]*>(.*?)</a>#us', $html, $allCards)) {
                foreach ($allCards[2] as $aIdx => $aBlock) {
                    if (mb_strpos($aBlock, $catNameBn, 0, 'UTF-8') !== false || mb_strpos($aBlock, $catNameEn, 0, 'UTF-8') !== false) {
                        $cImg = $cTitle = $cTime = '';
                        if (preg_match('#<img[^>]+src="([^"]+)"#u', $aBlock, $cim)) $cImg = $cim[1];
                        if (preg_match('#<h3[^>]*>([^<]+)</h3>#u', $aBlock, $ctm)) $cTitle = html_entity_decode(trim($ctm[1]), ENT_QUOTES, 'UTF-8');
                        if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $aBlock, $ctm2)) $cTime = trim($ctm2[1]);
                        if ($cTitle && $cImg) {
                            $panelCards[] = ['href' => $allCards[1][$aIdx], 'img' => $cImg, 'title' => $cTitle, 'time' => $cTime];
                            if (count($panelCards) >= 4) break;
                        }
                    }
                }
            }
        }

        // ★ v3.90 FIX: DB fallback for forced category panels.
        //    If HTML extraction (Methods 1-3) returned nothing, query the DB
        //    DIRECTLY for the latest 4 news in this category. This guarantees
        //    the home page always shows 4 panels with real news — even if
        //    index.php's HTML structure doesn't match our regex patterns.
        if (empty($panelCards) && isset($pdo)) {
            try {
                @$pdo->exec("SET NAMES utf8mb4");
                @$pdo->exec("SET time_zone = '+05:30'");

                // Query latest 4 published news for this category
                $_v390_sql = "SELECT n.id, n.title, n.image, n.created_at, c.name as cat_name
                              FROM news n
                              LEFT JOIN categories c ON n.category_id = c.id
                              WHERE n.status = 'published'
                                AND n.created_at <= NOW()
                                AND n.created_at >= (NOW() - INTERVAL 30 DAY)
                                AND (c.name = ? OR c.name = ?)
                              ORDER BY n.created_at DESC
                              LIMIT 4";
                $_v390_stmt = $pdo->prepare($_v390_sql);
                $_v390_stmt->execute([$catNameBn, $catNameEn]);
                $_v390_rows = $_v390_stmt->fetchAll(PDO::FETCH_ASSOC);

                // ★ v3.90b: If exact match returned nothing, try LIKE fallback
                //    (some installations store category names with extra spaces or different spelling)
                if (empty($_v390_rows)) {
                    $_v390_sql2 = "SELECT n.id, n.title, n.image, n.created_at, c.name as cat_name
                                   FROM news n
                                   LEFT JOIN categories c ON n.category_id = c.id
                                   WHERE n.status = 'published'
                                     AND n.created_at <= NOW()
                                     AND n.created_at >= (NOW() - INTERVAL 30 DAY)
                                     AND (c.name LIKE ? OR c.name LIKE ?)
                                   ORDER BY n.created_at DESC
                                   LIMIT 4";
                    $_v390_stmt2 = $pdo->prepare($_v390_sql2);
                    $_v390_stmt2->execute(['%' . $catNameBn . '%', '%' . $catNameEn . '%']);
                    $_v390_rows = $_v390_stmt2->fetchAll(PDO::FETCH_ASSOC);
                }

                $_v390_placeholder = "data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='400' height='250'%3E%3Crect width='400' height='250' fill='%23e8e4de'/%3E%3Ctext x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'%3E%E0%A6%9B%E0%A6%AC%E0%A6%BF%3C/text%3E%3C/svg%3E";

                foreach ($_v390_rows as $_v390_row) {
                    // Image URL (same logic as v3.78)
                    $_v390_img = $_v390_placeholder;
                    if (!empty($_v390_row['image'])) {
                        $_v390_imgRaw = trim($_v390_row['image']);
                        if ($_v390_imgRaw !== '' && $_v390_imgRaw !== '0') {
                            if (preg_match('#^https?://#i', $_v390_imgRaw) || strpos($_v390_imgRaw, '//') === 0) {
                                $_v390_img = $_v390_imgRaw;
                            } else {
                                while (strpos($_v390_imgRaw, 'uploads/uploads/') === 0) {
                                    $_v390_imgRaw = substr($_v390_imgRaw, 8);
                                }
                                if (strpos($_v390_imgRaw, 'uploads/') !== 0 && strpos($_v390_imgRaw, '/') !== 0) {
                                    $_v390_img = 'uploads/' . $_v390_imgRaw;
                                } else {
                                    $_v390_img = $_v390_imgRaw;
                                }
                            }
                        }
                    }

                    // Time ago
                    $_v390_time = '';
                    if (function_exists('timeAgo')) {
                        $_v390_time = timeAgo($_v390_row['created_at']);
                    } else {
                        $_v390_time = date('d M Y', strtotime($_v390_row['created_at']));
                    }

                    $panelCards[] = [
                        'href'  => '?page=single&id=' . (int)$_v390_row['id'],
                        'img'   => $_v390_img,
                        'title' => $_v390_row['title'],
                        'time'  => $_v390_time,
                        'id'    => (int)$_v390_row['id'],  // ★ For subcategory lookup
                    ];
                }
            } catch (Exception $_v390_e) {
                // Silent fail — panel will show empty message
            }
        }

        // ★ v3.39: Old "শীর্ষ সংবাদ" special fallback REMOVED — now handled by side-list title above.

        // Render this panel (even if empty, show "More" button)
        $moreLabel = $isEnglish ? 'More' : 'আরও';
        $newHTML .= <<<HTML
<div class="cat-panel-mo">
    <div class="section-head">
        <div class="sh-title"><i class="fas {$catIcon}"></i> {$catLabel}</div>
        <a href="{$catHref}" class="sh-link">{$moreLabel} <i class="fas fa-arrow-right"></i></a>
    </div>
    <div class="news-grid cat-panel-grid">
HTML;
        if (!empty($panelCards)) {
            foreach ($panelCards as $pc) {
                $pcTime = !empty($pc['time']) ? '<div class="nc-time"><i class="far fa-clock"></i> ' . htmlspecialchars($pc['time']) . '</div>' : '';
                // ★ v3.88: Look up subcategory for this panel card via batch map
                $_pc_sub = $ncGetSub($pc['href']);
                // ★ v3.95+v3.99: Duplicate check — don't add gold sub ONLY if:
                //    catLabel contains sub AND catLabel is LONGER than sub (real duplicate).
                //    ★ v3.99: If cat and sub are SAME NAME → STILL show gold sub.
                if (!empty($_pc_sub) && mb_stripos($catLabel, $_pc_sub, 0, 'UTF-8') !== false) {
                    $_v399_pcCatLen = mb_strlen($catLabel, 'UTF-8');
                    $_v399_pcSubLen = mb_strlen($_pc_sub, 'UTF-8');
                    if ($_v399_pcCatLen > $_v399_pcSubLen) {
                        // cat is longer → sub is part of longer cat → suppress (real duplicate)
                        $_pc_sub = '';
                    }
                    // If catLen === subLen → same name → DON'T suppress (v3.99)
                }
                if ($isEnglish && function_exists('en_label') && $_pc_sub !== '') {
                    $_pc_sub = en_label($_pc_sub);
                } elseif (!$isEnglish && function_exists('en_label') && $_pc_sub !== '') {
                    $_pc_sub = en_label($_pc_sub);
                }
                // ★ v3.88: "Category / Subcategory" format — red category, gold subcategory
                $_pc_catStr = '<div class="nc-cat">' . htmlspecialchars($catLabel);
                if (!empty($_pc_sub)) {
                    $_pc_catStr .= '<span class="nc-cat-sep">/</span><span class="nc-sub">' . htmlspecialchars($_pc_sub) . '</span>';
                }
                $_pc_catStr .= '</div>';
                // Apply English translation to title if needed
                $_pc_title = $pc['title'];
                if ($isEnglish && function_exists('bn_to_en')) {
                    $_pc_title = bn_to_en($_pc_title);
                }
                // ★ v3.103: Render thumbnail — <video> if image is .mp4, else <img>
                $_pc_thumb = _renderCardThumb($pc['img'], 'nc-img');
                $newHTML .= <<<HTML
<a href="{$pc['href']}" class="news-card">
    {$_pc_thumb}
    <div class="nc-body">
        {$_pc_catStr}
        <div class="nc-title">{$_pc_title}</div>
        {$pcTime}
    </div>
</a>
HTML;
            }
        } else {
            $emptyMsg = $isEnglish ? 'No news available' : 'এই বিভাগে কোনো সংবাদ নেই';
            $newHTML .= '<p style="grid-column:1/-1;text-align:center;color:var(--muted);padding:20px;font-size:13px;">' . $emptyMsg . '</p>';
        }
        $newHTML .= '</div></div>';
    }
}

// ★ v3.14: Popular Tags section REMOVED from home page (user requested)

$newHTML .= '</div>'; // .content-wrap

// ── Footer ────────────────────────────────────────────────────────
$newHTML .= <<<HTML
<footer class="app-footer">
    <div class="ft-brand">{$L['appName']}</div>
    <div>Truthy &amp; Trusted News Portal</div>
    <div class="ft-social">
        <a href="#" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
        <a href="#" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
        <a href="#" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
        <a href="#" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
    </div>
    <div style="display:flex;align-items:center;">
        <div style="flex:1;text-align:left;">{$ucFooterHTML}</div>
        <div style="flex:1;text-align:center;">&copy; 2026 {$L['appName']}</div>
        <div style="flex:1;text-align:right;">&nbsp;</div>
    </div>
</footer>
HTML;

// ── Floating sidebar button ──────────────────────────────────────
$newHTML .= '<button class="fab-sidebar" id="fabSidebar" aria-label="Menu"><i class="fas fa-bars"></i></button>';

// ── Sidebar (slide-in) ───────────────────────────────────────────
$newHTML .= '<div class="app-sidebar" id="appSidebar">';
$newHTML .= '<div class="sb-header"><h3>' . $L['appName'] . '</h3><button class="sb-close" id="sbClose" aria-label="Close">&times;</button></div>';
// Categories
$newHTML .= '<div class="sb-section"><h4>' . $L['allCats'] . '</h4>';
// ★ v3.45: Real DB counts + subcategories with counts (from index.php)
foreach ($catList as $cat) {
    $catName    = $cat['name'];
    $catDisplay = $cat['displayName'];
    $catHref    = $cat['href'];
    $catIcon    = $cat['icon'];
    $catCount   = $cat['count'];
    $catSubs    = $cat['subs'];
    $hasSubs    = !empty($catSubs);
    $isActive   = ($currentCat === $catName && !$dbCurrentSub) ? ' active' : '';
    $countBadge = $catCount > 0 ? '<span class="sb-cat-count">' . (int)$catCount . '</span>' : '';
    $newHTML .= '<div class="sb-cat-wrap' . ($hasSubs ? ' has-subs' : '') . '">';
    $newHTML .= '<a href="' . htmlspecialchars($catHref) . '" class="sb-cat-item' . $isActive . '"><i class="fas ' . htmlspecialchars($catIcon) . '"></i><span class="sb-cat-name">' . htmlspecialchars($catDisplay) . '</span>' . $countBadge . '</a>';
    if ($hasSubs) {
        $newHTML .= '<button class="sb-sub-toggle" data-cat="' . htmlspecialchars($catDisplay) . '" aria-label="Sub-categories"><i class="fas fa-chevron-down"></i></button>';
        $newHTML .= '<div class="sb-sub-list" data-cat="' . htmlspecialchars($catDisplay) . '">';
        foreach ($catSubs as $sub) {
            $subCount  = $dbSubPostCounts[$sub['id']] ?? 0;
            $subBadge  = $subCount > 0 ? '<span class="sb-sub-count">' . (int)$subCount . '</span>' : '';
            $subHref   = '?cat=' . urlencode($catName) . '&sub=' . (int)$sub['id'];
            $subActive = ($dbCurrentSub === (int)$sub['id']) ? ' active' : '';
            $newHTML .= '<a href="' . htmlspecialchars($subHref) . '" class="sb-sub-item' . $subActive . '"><i class="fas fa-circle"></i><span class="sb-sub-name">' . htmlspecialchars($sub['name']) . '</span>' . $subBadge . '</a>';
        }
        $newHTML .= '</div>';
    }
    $newHTML .= '</div>';
}
$newHTML .= '</div>';
// Popular posts
if (!empty($popularPosts)) {
    $newHTML .= '<div class="sb-section"><h4>' . $L['popular'] . '</h4>';
    foreach ($popularPosts as $pp) {
        $newHTML .= '<a href="' . htmlspecialchars($pp['href']) . '" class="sb-cat-item"><i class="far fa-newspaper"></i>' . htmlspecialchars($pp['title']) . '</a>';
    }
    $newHTML .= '</div>';
}
$newHTML .= '</div>'; // .app-sidebar

// ── ★ 3-dot Category panel removed (v2.4) ───────────────────────────
// Categories are now accessed via hamburger ☰ menu (sidebar) — all 20
// categories are listed in the sidebar's "All Categories" section.

// ── JavaScript ────────────────────────────────────────────────────
$newHTML .= <<<JS
<script>
(function() {
    'use strict';

    // ★ v3.22: Date & live time updater (IST, English, with seconds)
    // Date stays fixed on top line. Time updates every second on bottom line.
    // IST = UTC + 5:30. getTime() returns UTC ms, we add IST offset.
    function updateDateTime() {
        try {
            var now = new Date();
            var istMs = now.getTime() + (5.5 * 60 * 60 * 1000);
            var ist = new Date(istMs);
            var date = ist.getUTCDate();
            var month = ist.getUTCMonth();
            var year = ist.getUTCFullYear();
            var h = ist.getUTCHours();
            var m = ist.getUTCMinutes();
            var s = ist.getUTCSeconds();
            var ampm = h >= 12 ? 'PM' : 'AM';
            var h12 = h % 12; if (h12 === 0) h12 = 12;
            var enMonths = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
            // Date: "29 Oct 2026"
            var dateStr = date + ' ' + enMonths[month] + ' ' + year;
            // Time with seconds: "3:45:23 PM" (seconds make live update visible)
            var timeStr = h12 + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s + ' ' + ampm;
            var hdDate = document.getElementById('hdDate');
            var hdTime = document.getElementById('hdTime');
            if (hdDate) hdDate.textContent = dateStr;
            if (hdTime) hdTime.textContent = timeStr;
        } catch(e) {}
    }
    updateDateTime();
    setInterval(updateDateTime, 1000);

    // Search overlay
    var searchBtn = document.getElementById('searchBtn');
    var searchOverlay = document.getElementById('searchOverlay');
    if (searchBtn && searchOverlay) {
        searchBtn.addEventListener('click', function() {
            searchOverlay.classList.add('open');
            var input = searchOverlay.querySelector('input');
            if (input) setTimeout(function() { input.focus(); }, 50);
        });
        searchOverlay.addEventListener('click', function(e) {
            if (e.target === searchOverlay) searchOverlay.classList.remove('open');
        });
    }

    // ★ v3.42: Header hamburger (☰) button — shows "Under Construction" popup
    //    instead of opening the sidebar (user requested).
    //    Note: The floating FAB button (#fabSidebar) still opens the sidebar.
    var fabSidebar = document.getElementById('fabSidebar');
    var menuBtn = document.getElementById('menuBtn');
    var appSidebar = document.getElementById('appSidebar');
    var sbClose = document.getElementById('sbClose');

    // ★ v3.45: Subcategory expand/collapse toggle in sidebar.
    //    Real DB counts come from PHP (index.php's $catPostCounts / $subPostCounts)
    //    — no AJAX needed. JS only handles the expand/collapse UI.
    document.querySelectorAll('.sb-sub-toggle').forEach(function(btn) {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var cat = this.getAttribute('data-cat');
            var list = document.querySelector('.sb-sub-list[data-cat="' + cat + '"]');
            if (list) list.classList.toggle('open');
            this.classList.toggle('open');
        });
    });

    function openSidebar() {
        if (appSidebar) appSidebar.classList.add('open');
        document.body.classList.add('sidebar-open');
    }
    function closeSidebar() {
        if (appSidebar) appSidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }

    // ★ v3.42: "Under Construction" popup — bilingual (Bengali / English)
    var isEnUC = (document.documentElement.getAttribute('lang') || 'bn').toLowerCase() === 'en';
    var ucTitle   = isEnUC ? 'Under Construction' : 'নির্মাণাধীন';
    var ucMessage = isEnUC
        ? 'This section is currently under construction. Please check back soon.'
        : 'এই অংশটি বর্তমানে নির্মাণাধীন। অনুগ্রহ করে শীঘ্রই আবার দেখুন।';
    var ucCloseLabel = isEnUC ? 'Close' : 'বন্ধ করুন';

    var ucOverlay = document.createElement('div');
    ucOverlay.id = 'ucOverlay';
    ucOverlay.style.cssText = 'display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:10000;align-items:center;justify-content:center;padding:20px;';
    ucOverlay.innerHTML =
        '<div style="background:#fff;border-radius:14px;max-width:340px;width:100%;padding:30px 24px;text-align:center;box-shadow:0 12px 40px rgba(0,0,0,.3);border-top:6px solid #B71C1C;">' +
            '<div style="width:80px;height:80px;margin:0 auto 18px;background:rgba(183,28,28,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:36px;color:#B71C1C;">' +
                '<i class="fas fa-helmet-safety"></i>' +
            '</div>' +
            '<h3 style="font-family:\'Noto Serif Bengali\',serif;font-size:20px;font-weight:700;color:#1A1A1A;margin-bottom:10px;">' + ucTitle + '</h3>' +
            '<p style="font-size:13px;color:#666;line-height:1.6;margin-bottom:20px;">' + ucMessage + '</p>' +
            '<button id="ucCloseBtn" style="background:#B71C1C;color:#fff;border:none;padding:9px 28px;border-radius:20px;font-size:13px;font-weight:600;cursor:pointer;font-family:inherit;">' + ucCloseLabel + '</button>' +
        '</div>';
    document.body.appendChild(ucOverlay);

    function showUnderConstruction(e) {
        if (e) { e.preventDefault(); e.stopPropagation(); }
        if (ucOverlay) ucOverlay.style.display = 'flex';
    }
    function hideUnderConstruction() {
        if (ucOverlay) ucOverlay.style.display = 'none';
    }

    // ★ FAB (bottom-right) still opens sidebar normally.
    if (fabSidebar) fabSidebar.addEventListener('click', openSidebar);
    // ★ Only the header hamburger (☰) triggers Under Construction.
    if (menuBtn) menuBtn.addEventListener('click', showUnderConstruction);
    ucOverlay.addEventListener('click', function(e) {
        if (e.target === ucOverlay || e.target.id === 'ucCloseBtn' || e.target.closest('#ucCloseBtn')) {
            hideUnderConstruction();
        }
    });

    if (sbClose) sbClose.addEventListener('click', closeSidebar);
    document.addEventListener('click', function(e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (appSidebar && appSidebar.contains(e.target)) return;
        if (e.target === fabSidebar || (fabSidebar && fabSidebar.contains(e.target))) return;
        if (e.target === menuBtn || (menuBtn && menuBtn.contains(e.target))) return;
        closeSidebar();
    });

    // ★ 3-dot panel removed (v2.4) — sidebar handles all categories

    // ★ v3.46: Bell icon — shows TODAY's posted news (from DB via AJAX)
    //    Replaces old breaking-news popup with a real list of today's news,
    //    each with thumbnail, category, and time-ago. Badge shows today's count.
    var notifBtn = document.getElementById('notifBtn');
    var notifBadge = document.getElementById('notifBadge');

    // Create popup element dynamically
    var newsPopup = document.createElement('div');
    newsPopup.className = 'news-popup';
    newsPopup.id = 'newsPopup';
    document.body.appendChild(newsPopup);

    function escapeHtml(text) {
        if (!text) return '';
        var map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
        return String(text).replace(/[&<>"']/g, function(m) { return map[m]; });
    }

    // ★ Fetch today's news COUNT for the badge (lightweight, called on page load)
    function fetchTodayNewsCount() {
        fetch('?ajax_today_news=1&count_only=1&t=' + Date.now(), { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data && data.status === 'ok' && data.count > 0 && notifBadge) {
                    notifBadge.textContent = data.count;
                    notifBadge.style.display = 'inline-block';
                    notifBadge.classList.add('new-news');
                }
            })
            .catch(function() {});
    }

    // ★ Fetch today's news ITEMS for the popup (with thumbnails)
    function fetchTodayNewsPopup() {
        var popup = document.getElementById('newsPopup');
        if (!popup) return;

        var isEn = (document.documentElement.getAttribute('lang') || 'bn') === 'en';
        var todayLabel  = isEn ? "Today's News" : 'আজকের সংবাদ';
        var loadingLbl  = isEn ? 'Loading today\'s news…' : 'আজকের সংবাদ আনা হচ্ছে…';
        var emptyLbl    = isEn ? 'No news posted today' : 'আজ কোনো সংবাদ পোস্ট হয়নি';
        var errorLbl    = isEn ? 'Failed to load' : 'লোড করতে ব্যর্থ';

        // Show loading state
        popup.innerHTML =
            '<div class="np-header">' +
                '<span class="np-title"><i class="fas fa-bell"></i> ' + todayLabel + '</span>' +
                '<button class="np-close" id="npClose">&times;</button>' +
            '</div>' +
            '<div class="np-body">' +
                '<div class="np-empty"><i class="fas fa-spinner fa-spin"></i>' + loadingLbl + '</div>' +
            '</div>';
        popup.classList.add('open');

        fetch('?ajax_today_news=1&t=' + Date.now(), { cache: 'no-store' })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data || data.status !== 'ok') {
                    popup.innerHTML =
                        '<div class="np-header">' +
                            '<span class="np-title"><i class="fas fa-bell"></i> ' + todayLabel + '</span>' +
                            '<button class="np-close" id="npClose">&times;</button>' +
                        '</div>' +
                        '<div class="np-body"><div class="np-empty"><i class="fas fa-exclamation-circle"></i>' + errorLbl + '</div></div>';
                    return;
                }

                var items = data.items || [];
                var bodyHtml = '';

                if (items.length === 0) {
                    bodyHtml = '<div class="np-empty"><i class="far fa-bell-slash"></i>' + emptyLbl + '</div>';
                } else {
                    items.forEach(function(item) {
                        var title = escapeHtml(item.title || '');
                        var cat   = escapeHtml(item.category_name || '');
                        var time  = escapeHtml(item.time_ago || '');
                        var img   = item.image_url || '';
                        var href  = '?page=single&id=' + item.id;
                        bodyHtml +=
                            '<a href="' + href + '" class="np-item">' +
                                '<div class="npi-img"><img src="' + img + '" alt="" loading="lazy"></div>' +
                                '<div class="npi-text">' +
                                    (cat ? '<div class="npi-cat">' + cat + '</div>' : '') +
                                    '<div class="npi-title">' + title + '</div>' +
                                    '<div class="npi-time"><i class="far fa-clock"></i> ' + time + '</div>' +
                                '</div>' +
                            '</a>';
                    });
                }

                popup.innerHTML =
                    '<div class="np-header">' +
                        '<span class="np-title"><i class="fas fa-bell"></i> ' + todayLabel + ' (' + items.length + ')</span>' +
                        '<button class="np-close" id="npClose">&times;</button>' +
                    '</div>' +
                    '<div class="np-body">' + bodyHtml + '</div>';
            })
            .catch(function() {
                popup.innerHTML =
                    '<div class="np-header">' +
                        '<span class="np-title"><i class="fas fa-bell"></i> ' + todayLabel + '</span>' +
                        '<button class="np-close" id="npClose">&times;</button>' +
                    '</div>' +
                    '<div class="np-body"><div class="np-empty"><i class="fas fa-exclamation-circle"></i>' + errorLbl + '</div></div>';
            });
    }

    if (notifBtn) {
        // Fetch today's count on page load (for the badge)
        fetchTodayNewsCount();

        notifBtn.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var popup = document.getElementById('newsPopup');
            if (!popup) return;
            if (popup.classList.contains('open')) {
                popup.classList.remove('open');
            } else {
                fetchTodayNewsPopup();
                if (notifBadge) notifBadge.classList.remove('new-news');
            }
        });

        // Close popup on close button or outside click
        document.addEventListener('click', function(e) {
            var popup = document.getElementById('newsPopup');
            if (!popup || !popup.classList.contains('open')) return;
            if (popup.contains(e.target)) {
                if (e.target.id === 'npClose' || e.target.closest('#npClose')) {
                    popup.classList.remove('open');
                }
                return;
            }
            if (e.target === notifBtn || notifBtn.contains(e.target)) return;
            popup.classList.remove('open');
        });
    }

    // Close sidebar when clicking a link inside
    if (appSidebar) {
        appSidebar.querySelectorAll('a').forEach(function(link) {
            link.addEventListener('click', closeSidebar);
        });
    }
})();
</script>
JS;

$newHTML .= '</body></html>';

// ★ v3.94: Removed visible debug banner (subcategory feature confirmed working!).
//    VLM verification confirmed "আলোচনা / ফিচার" showing correctly.
//    Debug HTML comment kept for developer reference (invisible to users).
//    To re-enable visible banner, add ?debug_sub to URL.

if (isset($_GET['debug_sub'])) {
    $_v393_debugText = "v3.98 SUBCATEGORY DIAGNOSTICS\n";
    $_v393_debugText .= "─────────────────────────────\n";
    $_v393_debugText .= "dbCatSubcats empty? : " . ($_v392_debug['dbCatSubcats_empty'] ?? '?') . "\n";
    $_v393_debugText .= "subNameMap size     : " . ($_v392_debug['subNameMap_size'] ?? 0) . " (subcategory names found)\n";
    $_v393_debugText .= "subcatTable         : " . ($_v392_debug['subcatTable'] ?: '(none discovered)') . "\n";
    $_v393_debugText .= "subcatNameCol       : " . ($_v392_debug['subcatNameCol'] ?: '(none)') . "\n";
    $_v393_debugText .= "junctionTable       : " . ($_v392_debug['junctionTable'] ?: '(none discovered)') . "\n";
    $_v393_debugText .= "newsIdCol           : " . ($_v392_debug['newsIdCol'] ?? 'news_id') . "\n";
    $_v393_debugText .= "subIdCol            : " . ($_v392_debug['subIdCol'] ?? 'subcategory_id') . "\n";
    $_v393_debugText .= "ncSubMap size       : " . ($_v392_debug['ncSubMap_size'] ?? 0) . " (news→sub mappings found)\n";
    $_v393_debugText .= "─────────────────────────────\n";

    // ★ v3.98: Show per-card debug info
    $_v393_debugText .= "PER-CARD DEBUG (current page cards):\n";
    $_v393_debugText .= "─────────────────────────────\n";
    if (!empty($_v398_cardDebug)) {
        foreach ($_v398_cardDebug as $_v398_i => $_v398_cd) {
            $_v393_debugText .= "Card " . ($_v398_i + 1) . ": " . ($_v398_cd['href'] ?? '') . "\n";
            $_v393_debugText .= "  cat_orig         : '" . ($_v398_cd['cat_orig'] ?? '') . "'\n";
            $_v393_debugText .= "  sub_from_db      : '" . ($_v398_cd['sub_from_db'] ?? '') . "'\n";
            $_v393_debugText .= "  sub_final        : '" . ($_v398_cd['sub_final'] ?? '') . "'\n";
            $_v393_debugText .= "  subAlreadyInCat  : " . (($_v398_cd['subAlreadyInCat'] ?? false) ? 'true' : 'false') . "\n";
            $_v393_debugText .= "  has_slash_in_cat : " . (($_v398_cd['has_slash_in_cat'] ?? false) ? 'true' : 'false') . "\n";
            $_v393_debugText .= "  ncSubMap_has     : " . (($_v398_cd['ncSubMap_has'] ?? false) ? 'true' : 'false') . "\n";
            $_v393_debugText .= "\n";
        }
    } else {
        $_v393_debugText .= "(No news cards on this page — try a category or search page)\n\n";
    }
    $_v393_debugText .= "─────────────────────────────\n";

    if (isset($pdo)) {
        try {
            $_v393_allTables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            $_v393_subRelated = [];
            foreach ($_v393_allTables as $_v393_t) {
                if (stripos($_v393_t, 'sub') !== false || stripos($_v393_t, 'categ') !== false) {
                    $_v393_subRelated[] = $_v393_t;
                }
            }
            $_v393_debugText .= "Subcategory-related tables: " . (implode(', ', $_v393_subRelated) ?: '(none)') . "\n\n";
            $_v393_debugText .= "All tables: " . implode(', ', array_slice($_v393_allTables, 0, 30)) . "\n";
        } catch (Exception $_v393_e) {
            $_v393_debugText .= "(Could not list tables: " . $_v393_e->getMessage() . ")\n";
        }
    }

    $_v393_bannerStyle = 'position:fixed;top:10px;right:10px;z-index:99999;background:#1a1a1a;color:#0f0;border:2px solid #B71C1C;border-radius:8px;padding:12px 14px;font-family:monospace;font-size:10px;line-height:1.5;max-width:480px;max-height:90vh;overflow:auto;white-space:pre-wrap;box-shadow:0 4px 20px rgba(0,0,0,.5);';
    $_v393_banner = '<div style="' . $_v393_bannerStyle . '" id="v393DebugBanner">' . htmlspecialchars($_v393_debugText) . '<br><button onclick="this.parentElement.remove()" style="margin-top:8px;background:#B71C1C;color:#fff;border:none;padding:4px 10px;border-radius:3px;cursor:pointer;font-family:inherit;">Close</button></div>';

    $newHTML = str_replace('<body class="app-view">', '<body class="app-view">' . $_v393_banner, $newHTML);
    if (strpos($newHTML, $_v393_banner) === false) {
        $newHTML = $_v393_banner . $newHTML;
    }
}

// ★ Invisible HTML comment with debug info (always on, for developer reference)
$_v392_debugJson = json_encode($_v392_debug, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
$newHTML .= "\n<!-- v3.94 DEBUG: " . $_v392_debugJson . " -->\n";

echo $newHTML;
