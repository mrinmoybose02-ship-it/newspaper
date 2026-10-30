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
            // Process image URL (same logic as index.php)
            $imgUrl = $placeholderImg;
            if (!empty($n['image'])) {
                $img = trim($n['image']);
                if ($img !== '' && $img !== '0') {
                    if (preg_match('#^https?://#i', $img) || strpos($img, '//') === 0) {
                        $imgUrl = $img;
                    } else {
                        while (strpos($img, 'uploads/uploads/') === 0) { $img = substr($img, 8); }
                        $imgUrl = (strpos($img, '/') === 0 ? '' : 'uploads/') . $img;
                    }
                }
            }
            $n['image_url'] = $imgUrl;

            // Format time-ago in Bengali
            $n['time_ago'] = '';
            $ts = strtotime($n['created_at']);
            if ($ts) {
                $diff = time() - $ts;
                if ($diff < 60) {
                    $n['time_ago'] = 'এইমাত্র';
                } elseif ($diff < 3600) {
                    $m = (int)($diff / 60);
                    $n['time_ago'] = str_replace($enDigits, $bnDigits, (string)$m) . ' মিনিট আগে';
                } elseif ($diff < 86400) {
                    $h = (int)($diff / 3600);
                    $n['time_ago'] = str_replace($enDigits, $bnDigits, (string)$h) . ' ঘণ্টা আগে';
                } else {
                    $n['time_ago'] = date('d M Y', $ts);
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
//    Creates visitor_stats table, tracks each visitor by SHA256(IP + UserAgent).
//    Counts: online (last 5 min) + total unique visitors. Bengali digit output.
$ucText = '';
try {
    if (isset($pdo)) {
        // Create table if not exists (safe — runs once)
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitor_stats (
            id INT AUTO_INCREMENT PRIMARY KEY,
            visitor_hash VARCHAR(64) NOT NULL UNIQUE,
            first_visit TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Track current visitor by IP only (1 IP = 1 visitor, regardless of browser/device)
        // ★ v3.49: Track by IP only — not IP+UserAgent. Same person from different
        //    browsers counts as 1 visitor. Also detects real IP behind proxy/CDN.
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
        $visitorHash = hash('sha256', $clientIP);
        $trackStmt = $pdo->prepare("INSERT INTO visitor_stats (visitor_hash) VALUES (?) ON DUPLICATE KEY UPDATE last_activity = NOW()");
        $trackStmt->execute([$visitorHash]);

        // Get counts
        // ★ v3.50: Online count removed from display — only total visits shown.
        $totalRaw  = (int)$pdo->query("SELECT COUNT(*) FROM visitor_stats")->fetchColumn();

        // Convert to Bengali digits (with thousand separators)
        $bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        $enDigits = ['0','1','2','3','4','5','6','7','8','9'];
        $ucTotal  = str_replace($enDigits, $bnDigits, number_format($totalRaw));

        // ★ v3.48: Retro counter-style — each digit in a black box (odometer style)
        // ★ v3.51: Green live-dot before the count (pulsing "site is live" indicator)
        $counterHTML = '<span class="counter-live-dot"></span>';
        $chars = preg_split('//u', $ucTotal, -1, PREG_SPLIT_NO_EMPTY);
        foreach ($chars as $char) {
            if ($char === ',') {
                $counterHTML .= '<span class="counter-sep">,</span>';
            } else {
                $counterHTML .= '<span class="counter-digit">' . $char . '</span>';
            }
        }
        $visitsLabel = $isEnglish ? 'Visits' : 'ভিজিট';
        $ucFooterHTML = '<div class="ft-counter">'
            . '<div class="counter-display">' . $counterHTML . '</div>'
            . '<div class="counter-label">' . $visitsLabel . '</div>'
            . '</div>';
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
$langSwitchHref = $langSwitchUrl;

// ★ Section title (for home/category/search pages)
if ($searchQuery) {
    $sectionTitle = $isEnglish ? 'Search: ' . $searchQuery : 'সার্চ: ' . $searchQuery;
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
if (preg_match_all('#<a href="([^"]+)"\s+class="nc"[^>]*>(.*?)</a>#us', $html, $matches)) {
    foreach ($matches[1] as $idx => $href) {
        $block = $matches[2][$idx];
        $img = $title = $cat = $time = '';
        if (preg_match('#<img[^>]+src="([^"]+)"#u', $block, $im)) $img = $im[1];
        if (preg_match('#<h3[^>]*>([^<]+)</h3>#u', $block, $tm)) $title = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
        if (preg_match('#<div class="nc-cat"[^>]*>([^<]+)</div>#u', $block, $cm)) $cat = trim($cm[1]);
        if (preg_match('#<i class="far fa-clock"></i>\s*([^<]+)#u', $block, $tm2)) $time = trim($tm2[1]);
        if ($title && $img) $newsCards[] = ['href' => $href, 'img' => $img, 'title' => $title, 'cat' => $cat, 'time' => $time];
    }
}

// ★ v3.15: Also extract home panel cards (.cat-panel-card) and merge into newsCards.
// These are category-specific news cards that appear below the main grid on home page.
// User wants them in "সর্বশেষ সংবাদ" (Latest News) section instead of separate panels.
if (preg_match_all('#<a href="([^"]+)"\s+class="cat-panel-card"[^>]*>(.*?)</a>#us', $html, $cpMatches)) {
    // ★ v3.26: Build hash set for O(1) duplicate lookup (was O(n²))
    $existingHrefs = [];
    foreach ($newsCards as $nc) {
        $existingHrefs[$nc['href']] = true;
    }
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
    if (preg_match('#<a href="([^"]+)"\s+class="cb"[^>]*>([^<]+)</a>#u', $html, $cm)) {
        $article['catHref'] = $cm[1];
        $article['category'] = trim($cm[2]);
    }
    // Subcategory (second .cb link)
    if (preg_match_all('#<a href="([^"]+)"\s+class="cb"[^>]*>([^<]+)</a>#u', $html, $cmAll)) {
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
    // Method 2 (fallback): Broader match in entire HTML, but filter aggressively
    if (empty($article['tags'])) {
        if (preg_match_all('#<a[^>]*class="tag-pill"[^>]*>\s*<i class="fas fa-hashtag"></i>\s*([^<]+)</a>#u', $html, $tagMatches)) {
            foreach ($tagMatches[1] as $tagText) {
                $tag = trim(html_entity_decode($tagText, ENT_QUOTES, 'UTF-8'));
                // Only accept reasonable tag names (1-50 chars)
                if ($tag && mb_strlen($tag, 'UTF-8') <= 50) {
                    $article['tags'][] = $tag;
                }
            }
        }
    }
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

/* Category badges */
.art-cats { margin-bottom: 10px; }
.art-cat {
    display: inline-block;
    background: var(--red);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 3px 10px;
    border-radius: 3px;
    letter-spacing: .3px;
    text-transform: uppercase;
    margin-right: 5px;
}
.art-cat.sub { background: var(--gold); color: #000; }

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

    // Category badges
    $catBadges = '';
    if (!empty($article['category'])) {
        $catBadges .= '<a href="' . htmlspecialchars($article['catHref']) . '" class="art-cat">' . htmlspecialchars($article['category']) . '</a>';
    }
    if (!empty($article['subCat'])) {
        $catBadges .= '<a href="' . htmlspecialchars($article['subHref']) . '" class="art-cat sub">' . htmlspecialchars($article['subCat']) . '</a>';
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
        $newSingleHTML .= '<div class="art-img"><img src="' . htmlspecialchars($article['image']) . '" alt="' . htmlspecialchars($article['title']) . '" decoding="async" fetchpriority="high"></div>';
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
        $newSingleHTML .= '<div class="art-content" id="artContent">' . $article['content'] . '</div>';
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
    if (!empty($article['tags'])) {
        $tagsLabel = $isEnglish ? 'Tags' : 'ট্যাগ';
        $newSingleHTML .= '<div class="art-tags">';
        $newSingleHTML .= '<span class="art-tags-label"><i class="fas fa-tags"></i> ' . $tagsLabel . '</span>';
        foreach ($article['tags'] as $tag) {
            $tag = trim($tag);
            if ($tag) {
                $tagHref = '?q=' . urlencode($tag);
                $newSingleHTML .= '<a href="' . htmlspecialchars($tagHref) . '" class="art-tag-pill"><i class="fas fa-hashtag"></i>' . htmlspecialchars($tag) . '</a>';
            }
        }
        $newSingleHTML .= '</div>';
    }

    $newSingleHTML .= '</div>'; // .article-body
    $newSingleHTML .= '</div>'; // .article-wrap

    // Related news
    if (!empty($article['related'])) {
        $relLabel = $isEnglish ? 'Related News' : 'সম্পর্কিত সংবাদ';
        $newSingleHTML .= '<div class="rel-sec"><div class="rel-head">' . $relLabel . '</div><div class="rel-grid">';
        foreach ($article['related'] as $rel) {
            $relTimeStr = !empty($rel['time']) ? '<div class="rc-time"><i class="far fa-clock"></i> ' . htmlspecialchars($rel['time']) . '</div>' : '';
            $relCatStr  = !empty($rel['cat']) ? '<div class="rc-cat">' . htmlspecialchars($rel['cat']) . '</div>' : '';
            $newSingleHTML .= '<a href="' . $rel['href'] . '" class="rel-card"><img src="' . $rel['img'] . '" alt="" loading="lazy"><div class="rc-body">' . $relCatStr . '<div class="rc-title">' . $rel['title'] . '</div>' . $relTimeStr . '</div></a>';
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
    <div>&copy; 2026 {$L['appName']}</div>
    {$ucFooterHTML}
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

// Featured news (home page only, not on search)
if ($page === 'home' && !$searchQuery && !empty($featured['title'])) {
    $newHTML .= <<<HTML
<a href="{$featured['href']}" class="featured-card">
    <img src="{$featured['img']}" alt="" class="fc-img" loading="eager">
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

// ★ v3.39: "শীর্ষ সংবাদ" (Top Stories) — side-list with section title
if ($page === 'home' && !empty($sideItems)) {
    $topStoriesLabel = $isEnglish ? 'Top Stories' : 'শীর্ষ সংবাদ';
    $newHTML .= <<<HTML
<div class="section-head">
    <div class="sh-title">{$topStoriesLabel}</div>
</div>
<div class="side-list">
HTML;
    foreach ($sideItems as $si) {
        $newHTML .= <<<HTML
<a href="{$si['href']}" class="side-item">
    <img src="{$si['img']}" alt="" class="si-img" loading="lazy">
    <div class="si-body">
        <div class="si-cat">{$si['cat']}</div>
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

// Section title
$newHTML .= <<<HTML
<div class="section-head">
    <div class="sh-title">{$sectionTitle}</div>
</div>
HTML;

// News grid
if (!empty($newsCards)) {
    $newHTML .= '<div class="news-grid">';
    foreach ($newsCards as $nc) {
        $timeStr = !empty($nc['time']) ? '<div class="nc-time"><i class="far fa-clock"></i>' . htmlspecialchars($nc['time']) . '</div>' : '';
        $catStr  = !empty($nc['cat']) ? '<div class="nc-cat">' . htmlspecialchars($nc['cat']) . '</div>' : '';
        $newHTML .= <<<HTML
<a href="{$nc['href']}" class="news-card">
    <img src="{$nc['img']}" alt="" class="nc-img" loading="lazy">
    <div class="nc-body">
        {$catStr}
        <div class="nc-title">{$nc['title']}</div>
        {$timeStr}
    </div>
</a>
HTML;
    }
    $newHTML .= '</div>';
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
// "শীর্ষ সংবাদ" is now the side-list title above (no duplicate).
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
                $newHTML .= <<<HTML
<a href="{$pc['href']}" class="news-card">
    <img src="{$pc['img']}" alt="" class="nc-img" loading="lazy">
    <div class="nc-body">
        <div class="nc-cat">{$catLabel}</div>
        <div class="nc-title">{$pc['title']}</div>
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
    <div>&copy; 2026 {$L['appName']}</div>
    {$ucFooterHTML}
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

echo $newHTML;
