<?php
require_once __DIR__ . '/config.php';

// ====== AJAX ENDPOINT FOR NEW NEWS NOTIFICATION (Fallback if WebSocket fails) ======
if (isset($_GET['ajax_check_new_news'])) {
    header('Content-Type: application/json');
    $last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;
    try {
        $stmt = $pdo->prepare("SELECT id, title FROM news WHERE status = 'published' AND id > ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$last_id]);
        $newNews = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($newNews) {
            echo json_encode(['status' => 'new', 'id' => $newNews['id'], 'title' => $newNews['title']]);
        } else {
            echo json_encode(['status' => 'no_new']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error']);
    }
    exit;
}
// =====================================================================

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS password_reset_requests (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(255) NOT NULL,
        status ENUM('pending','approved','rejected') DEFAULT 'pending',
        new_password VARCHAR(255) DEFAULT NULL,
        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        approved_at TIMESTAMP NULL DEFAULT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (PDOException $e) {}

if (!function_exists('loadSettings')) {
    function loadSettings($pdo, $keys) {
        $v = [];
        try {
            $in = implode(",", array_fill(0, count($keys), "?"));
            $st = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($in)");
            $st->execute($keys);
            $rows = $st->fetchAll(PDO::FETCH_KEY_PAIR);
            foreach ($keys as $k) { $v[$k] = $rows[$k] ?? ""; }
        } catch (Exception $e) { foreach ($keys as $k) { $v[$k] = ""; } }
        return $v;
    }
}

if (!function_exists('formatDate')) {
    function formatDate($dateStr) {
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $months = ['জানুয়ারি','ফেব্রুয়ারি','মার্চ','এপ্রিল','মে','জুন','জুলাই','আগস্ট','সেপ্টেম্বর','অক্টোবর','নভেম্বর','ডিসেম্বর'];
        try {
            $d = new DateTime($dateStr);
            $out = $d->format('d') . ' ' . $months[(int)$d->format('m') - 1] . ' ' . $d->format('Y');
            return str_replace($en, $bn, $out);
        } catch (Exception $e) { return htmlspecialchars($dateStr); }
    }
}

if (!function_exists('timeAgo')) {
    function timeAgo($dateStr) {
        $bn = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
        $en = ['0','1','2','3','4','5','6','7','8','9'];
        $now = time();
        try { $d = new DateTime($dateStr); $ts = $d->getTimestamp(); } catch (Exception $e) { return ''; }
        $diff = $now - $ts;
        if ($diff < 60) return 'এইমাত্র';
        if ($diff < 3600) { $m = (int)($diff / 60); return str_replace($en, $bn, $m) . ' মিনিট আগে'; }
        if ($diff < 86400) { $h = (int)($diff / 3600); return str_replace($en, $bn, $h) . ' ঘন্টা আগে'; }
        if ($diff < 2592000) { $d2 = (int)($diff / 86400); return str_replace($en, $bn, $d2) . ' দিন আগে'; }
        if ($diff < 31536000) { $mo = (int)($diff / 2592000); return str_replace($en, $bn, $mo) . ' মাস আগে'; }
        $y = (int)($diff / 31536000); return str_replace($en, $bn, $y) . ' বছর আগে';
    }
}

if (!function_exists('newsImage')) {
    function newsImage($img, $placeholder) {
        if (empty($img)) return $placeholder;
        $img = trim($img);
        if ($img === '' || $img === '0') return $placeholder;
        if (preg_match('#^https?://#i', $img)) return $img;
        if (strpos($img, '//') === 0) return $img;
        if (strpos($img, 'uploads/') === 0) {
            while (strpos($img, 'uploads/uploads/') === 0) { $img = substr($img, 8); }
            return $img;
        }
        if (strpos($img, '/') === 0) return $img;
        return 'uploads/' . $img;
    }
}

if (!function_exists('videoEmbedUrl')) {
    function videoEmbedUrl($url) {
        if (empty($url)) return '';
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return 'https://www.youtube.com/embed/' . $m[1] . '?rel=0&modestbranding=1';
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }
        if (strpos($url, 'youtube.com/embed/') !== false || strpos($url, 'player.vimeo.com') !== false) return $url;
        return $url;
    }
}

if (!function_exists('videoThumbUrl')) {
    function videoThumbUrl($url) {
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
            return 'https://img.youtube.com/vi/' . $m[1] . '/mqdefault.jpg';
        }
        return '';
    }
}

if (!function_exists('extractVideoEmbedUrl')) {
    function extractVideoEmbedUrl($rawCode) {
        if (empty($rawCode)) return '';
        $url = '';
        if (preg_match('/src=[\"\']?([^\"\'>\s]+)[\"\']?/i', $rawCode, $match)) {
            $url = $match[1];
        } else {
            $url = trim($rawCode);
        }
        if (preg_match('#(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/|youtube-nocookie\.com/embed/)([a-zA-Z0-9_-]{11})#', $url, $m)) {
            $url = 'https://www.youtube.com/embed/' . $m[1];
        }
        if (preg_match('#vimeo\.com/(\d+)#', $url, $m)) {
            $url = 'https://player.vimeo.com/video/' . $m[1];
        }
        if (strpos($url, 'http://') !== 0 && strpos($url, 'https://') !== 0) {
            if (strpos($url, '//') === 0) { $url = 'https:' . $url; } else { $url = 'https://' . $url; }
        }
        if (strpos($url, 'youtube.com/embed/') !== false || strpos($url, 'player.vimeo.com/video/') !== false) {
            if (strpos($url, 'autoplay=1') === false) {
                $url .= (strpos($url, '?') !== false ? '&' : '?') . 'autoplay=1&rel=0';
            }
        }
        return $url;
    }
}

if (!function_exists('getActiveAds')) {
    function getActiveAds($pdo, $position) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM advertisements WHERE position = ? AND is_active = 1 AND (start_date IS NULL OR start_date <= CURDATE()) AND (end_date IS NULL OR end_date >= CURDATE()) ORDER BY sort_order ASC, id DESC");
            $stmt->execute([$position]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) { return []; }
    }
}

if (!function_exists('renderAd')) {
    function renderAd($ad) {
        if (!$ad) return '';
        $html = '<div class="ad-wrapper" style="margin:15px auto;text-align:center;max-width:100%;overflow:hidden;">';
        $html .= '<div class="ad-label">- Sponsored -</div>';
        if ($ad['ad_type'] === 'image' && !empty($ad['image'])) {
            $link = !empty($ad['link_url']) ? $ad['link_url'] : '#';
            $html .= '<a href="' . htmlspecialchars($link) . '" target="_blank" rel="nofollow sponsored">';
            $html .= '<img src="' . htmlspecialchars($ad['image']) . '" alt="' . htmlspecialchars($ad['title']) . '" style="max-width:100%;height:auto;border-radius:8px;" loading="lazy">';
            $html .= '</a>';
        } elseif ($ad['ad_type'] === 'code' && !empty($ad['ad_code'])) {
            $html .= $ad['ad_code'];
        }
        $html .= '</div>';
        return $html;
    }
}

if (!function_exists('renderAdsByPos')) {
    function renderAdsByPos($pdo, $position) {
        $ads = getActiveAds($pdo, $position);
        $out = '';
        foreach ($ads as $ad) { $out .= renderAd($ad); }
        return $out;
    }
}

 $page = $_GET['page'] ?? 'home';

if (isset($_GET['action']) && $_GET['action'] === 'logout') { session_unset(); session_destroy(); header('Location: ?page=home'); exit; }

if ($page === 'admin_login') {
    require_once __DIR__ . '/admin_login.php';
    exit;
}

 $otherAdminPages = ['admin_dashboard','admin_add','admin_edit','admin_manage','admin_breaking','admin_categories','admin_cat_edit','admin_password'];
if (in_array($page, $otherAdminPages)) {
    if (!isset($_SESSION['user_id'])) { header('Location: ?page=admin_login'); exit; }
    $filePath = __DIR__ . '/' . $page . '.php';
    if (file_exists($filePath)) { require_once $filePath; exit; }
    header('Location: ?page=admin_dashboard'); exit;
}

try { $pdo->exec("DELETE t1 FROM categories t1 INNER JOIN categories t2 WHERE t1.name = t2.name AND t1.id > t2.id"); } catch(PDOException $e) {}

 $defaultCategories = ['প্রধান খবর','জাতীয়','রাজনীতি','আন্তর্জাতিক','অর্থনীতি','খেলাধুলা','বিনোদন','শিক্ষা','প্রযুক্তি','স্বাস্থ্য','বিশেষ সংবাদ','লাইফস্টাইল','ধর্ম','সংস্কৃতি','মতামত','ক্রাইম','কৃষি','ভ্রমণ','চাকরি'];
 $existingCats = $pdo->query("SELECT name FROM categories GROUP BY name ORDER BY MIN(id) ASC")->fetchAll(PDO::FETCH_COLUMN);
foreach ($defaultCategories as $cat) {
    if (!in_array($cat, $existingCats)) {
        $catSlug = generateSlug($cat); if (empty($catSlug)) $catSlug = 'cat-' . time() . '-' . rand(100, 9999);
        $checkCatSlug = $pdo->prepare("SELECT id FROM categories WHERE slug = ?"); $checkCatSlug->execute([$catSlug]);
        $slugBase = $catSlug; $slugCounter = 1;
        while ($checkCatSlug->fetch()) { $catSlug = $slugBase . '-' . $slugCounter; $slugCounter++; $checkCatSlug->execute([$catSlug]); }
        $pdo->prepare("INSERT INTO categories (name, slug) VALUES (?, ?)")->execute([$cat, $catSlug]);
    }
}
try { $pdo->exec("ALTER TABLE news ADD COLUMN tags VARCHAR(500) DEFAULT '' AFTER image"); } catch(PDOException $e) {}

 $pdo->exec("CREATE TABLE IF NOT EXISTS subcategories (id INT AUTO_INCREMENT PRIMARY KEY,category_id INT NOT NULL,name VARCHAR(255) NOT NULL,slug VARCHAR(255) NOT NULL,created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,INDEX idx_category (category_id),INDEX idx_slug (slug)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
 $pdo->exec("CREATE TABLE IF NOT EXISTS news_subcategories (news_id INT NOT NULL,subcategory_id INT NOT NULL,PRIMARY KEY (news_id, subcategory_id),INDEX idx_sub (subcategory_id),INDEX idx_news (news_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

 $currentCat = $_GET['cat'] ?? '';
 $searchQuery = trim($_GET['q'] ?? '');
 $currentSub = isset($_GET['sub']) ? (int)$_GET['sub'] : 0;
 $archYear = isset($_GET['y']) ? (int)$_GET['y'] : 0;
 $archMonth = isset($_GET['m']) ? (int)$_GET['m'] : 0;
 
 $currentSubData = null;
if ($currentSub) {
    $subStmt = $pdo->prepare("SELECT s.*, c.name as category_name FROM subcategories s JOIN categories c ON s.category_id = c.id WHERE s.id = ?");
    $subStmt->execute([$currentSub]); $currentSubData = $subStmt->fetch();
    if ($currentSubData) { $currentCat = $currentSubData['category_name']; } else { $currentSub = 0; }
}

 $show_all_news = isset($_COOKIE['show_all_news']) && $_COOKIE['show_all_news'] === 'yes';
 $dateLimitSQL = $show_all_news ? "" : " AND n.created_at >= (NOW() - INTERVAL 30 DAY)";

 $archiveDates = [];
try {
    $archStmt = $pdo->query("SELECT YEAR(created_at) as y, MONTH(created_at) as m FROM news WHERE status = 'published' GROUP BY y, m ORDER BY y DESC, m DESC");
    $archiveDates = $archStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
 $bnMonths = [1=>'জানুয়ারি', 2=>'ফেব্রুয়ারি', 3=>'মার্চ', 4=>'এপ্রিল', 5=>'মে', 6=>'জুন', 7=>'জুলাই', 8=>'আগস্ট', 9=>'সেপ্টেম্বর', 10=>'অক্টোবর', 11=>'নভেম্বর', 12=>'ডিসেম্বর'];

 $categories = $pdo->query("SELECT MIN(id) as id, name FROM categories GROUP BY name ORDER BY MIN(id) ASC")->fetchAll();
 $catIdMap = [];
foreach ($pdo->query("SELECT id, name FROM categories")->fetchAll() as $c) { $catIdMap[$c['id']] = $c['name']; }

 $allNewsData = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $pdo->query("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.status = 'published'{$dateLimitSQL} ORDER BY n.created_at DESC")->fetchAll()));
 $breakingData = array_map(function($b){ return ['id'=>$b['id'],'text'=>$b['text'],'time'=>$b['created_at']]; }, $pdo->query("SELECT * FROM breaking_news ORDER BY created_at DESC")->fetchAll());

 $latestNewsId = !empty($allNewsData[0]['id']) ? (int)$allNewsData[0]['id'] : 0;

 $tagCounts = [];
foreach ($allNewsData as $n) {
    if ($n['status'] !== 'published' || empty($n['tags'])) continue;
    $tags = array_filter(array_map('trim', explode(',', $n['tags'])));
    foreach ($tags as $tag) { if ($tag !== '') { if (!isset($tagCounts[$tag])) $tagCounts[$tag] = 0; $tagCounts[$tag]++; } }
}
arsort($tagCounts); $popularTags = array_slice($tagCounts, 0, 25, true);

 $featuredItems = array_filter($allNewsData, function($n){ return $n['status'] === 'published' && $n['featured']; });
if (empty($featuredItems)) $featuredItems = array_filter($allNewsData, function($n){ return $n['status'] === 'published'; });
 $popularPosts = array_slice($featuredItems, 0, 6);

 $catPostCounts = [];
foreach ($allNewsData as $n) { if ($n['status'] !== 'published') continue; $c = $n['category']; $catPostCounts[$c] = ($catPostCounts[$c] ?? 0) + 1; }

 $catSubcategories = [];
 $subRows = $pdo->query("SELECT s.id, s.category_id, s.name, s.slug FROM subcategories s ORDER BY s.category_id ASC, s.id ASC")->fetchAll();
foreach ($subRows as $sr) { $catName = $catIdMap[$sr['category_id']] ?? ''; if ($catName) { if (!isset($catSubcategories[$catName])) $catSubcategories[$catName] = []; $catSubcategories[$catName][] = ['id' => (int)$sr['id'], 'name' => $sr['name'], 'slug' => $sr['slug']]; } }

 $subPostCounts = [];
if (!empty($subRows)) {
    $subIds = array_column($subRows, 'id');
    if (!empty($subIds)) {
        $countStmt = $pdo->query("SELECT ns.subcategory_id, COUNT(DISTINCT ns.news_id) as cnt FROM news_subcategories ns JOIN news n ON ns.news_id = n.id AND n.status = 'published' WHERE ns.subcategory_id IN (" . implode(',', array_map('intval', $subIds)) . ") GROUP BY ns.subcategory_id");
        foreach ($countStmt->fetchAll() as $cr) { $subPostCounts[(int)$cr['subcategory_id']] = (int)$cr['cnt']; }
    }
}

 $navInlineCats = array_slice(array_column($categories, 'name'), 0, 8);
 $singleNews = null; $related = []; $displayFeatured = []; $pagedGrid = []; $totalGrid = 0; $totalPages = 1; $currentPage = 1;

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
    'কৃষি' => ['icon' => 'fa-seedling', 'color' => '#8BC34A'],
];
 $homeCatPanels = [];
 $showHomeVideos = false;
 $showLatestNews = false;

try {
    $homeSettings = loadSettings($pdo, ['home_cat_panels']);
    $activeCatIds = json_decode($homeSettings['home_cat_panels'] ?? '[]', true);
    if (!is_array($activeCatIds)) $activeCatIds = [];
    if (in_array('videos_section', $activeCatIds)) $showHomeVideos = true;
    if (in_array('latest_news_section', $activeCatIds)) $showLatestNews = true;
    $numericCatIds = array_filter($activeCatIds, 'is_numeric');
    if (!empty($numericCatIds)) {
        $inQuery = implode(',', array_fill(0, count($numericCatIds), '?'));
        $catStmt = $pdo->prepare("SELECT name FROM categories WHERE id IN ($inQuery) ORDER BY id ASC");
        $catStmt->execute($numericCatIds);
        $activeCatNames = $catStmt->fetchAll(PDO::FETCH_COLUMN);
        foreach ($activeCatNames as $hpName) {
            $homeCatPanels[] = ['name' => $hpName, 'icon' => $panelDefaults[$hpName]['icon'] ?? 'fa-folder', 'color' => $panelDefaults[$hpName]['color'] ?? '#B71C1C'];
        }
    }
} catch (Exception $e) {}

if (empty($homeCatPanels)) {
    foreach (['জাতীয়', 'আন্তর্জাতিক', 'খেলাধুলা', 'বিনোদন'] as $defName) {
        $homeCatPanels[] = ['name' => $defName, 'icon' => $panelDefaults[$defName]['icon'] ?? 'fa-folder', 'color' => $panelDefaults[$defName]['color'] ?? '#B71C1C'];
    }
}

 $homePanelData = [];
foreach ($homeCatPanels as $hcp) {
    $hpStmt = $pdo->prepare("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE c.name = ? AND n.status = 'published'{$dateLimitSQL} ORDER BY n.created_at DESC LIMIT 5");
    $hpStmt->execute([$hcp['name']]);
    $homePanelData[$hcp['name']] = ['info' => $hcp, 'items' => array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $hpStmt->fetchAll()))];
}

 $videos = [];
try {
    if (isset($pdo)) {
        $videos = $pdo->query("SELECT * FROM videos ORDER BY created_at DESC")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {
    $videos = [];
}
 $homeVideos = array_slice($videos, 0, 6);

if ($page === 'single') {
    $stmt = $pdo->prepare("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.id = ? AND n.status = 'published'");
    $stmt->execute([(int)($_GET['id'] ?? 0)]);
    if ($row = $stmt->fetch()) {
        $row = safeMapNewsKeys($row);
        $row['subcategory'] = $row['subcategory_name'] ?? '';
        $singleNews = $row;
        $singleRelatedTags = !empty($singleNews['tags']) ? array_filter(array_map('trim', explode(',', $singleNews['tags']))) : [];
        $related = [];
        if (!empty($singleRelatedTags)) {
            $stmt2 = $pdo->prepare("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.id != ? AND n.status = 'published'{$dateLimitSQL} AND (" . implode(' OR ', array_fill(0, count($singleRelatedTags), 'n.tags LIKE ?')) . ") ORDER BY n.created_at DESC LIMIT 6");
            $stmt2->execute(array_merge([$singleNews['id']], array_map(function($t){ return "%$t%"; }, $singleRelatedTags)));
            $related = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt2->fetchAll()));
        }
        if (empty($related)) {
            $stmt2 = $pdo->prepare("SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.id != ? AND c.name = ? AND n.status = 'published'{$dateLimitSQL} ORDER BY n.created_at DESC LIMIT 6");
            $stmt2->execute([$singleNews['id'], $singleNews['category']]);
            $related = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt2->fetchAll()));
        }
    }
    if (!$singleNews) $page = 'home';
} elseif ($page !== 'video' && $page !== 'videos') {
    $sql = "SELECT n.*, c.name as category_name, sc.name as subcategory_name, sc.id as subcategory_id FROM news n LEFT JOIN categories c ON n.category_id = c.id LEFT JOIN (SELECT news_id, subcategory_id FROM news_subcategories GROUP BY news_id) nsc ON n.id = nsc.news_id LEFT JOIN subcategories sc ON nsc.subcategory_id = sc.id WHERE n.status = 'published'";
    $countSql = "SELECT COUNT(*) FROM news n LEFT JOIN categories c ON n.category_id = c.id WHERE n.status = 'published'";
    $params = [];
    if ($currentCat) { $sql .= " AND c.name = ?"; $countSql .= " AND c.name = ?"; $params[] = $currentCat; }
    if ($searchQuery) { $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)"; $countSql .= " AND (n.title LIKE ? OR n.content LIKE ?)"; $params[] = "%$searchQuery%"; $params[] = "%$searchQuery%"; }
    if ($currentSub) { $sql .= " AND n.id IN (SELECT news_id FROM news_subcategories WHERE subcategory_id = ?)"; $countSql .= " AND n.id IN (SELECT news_id FROM news_subcategories WHERE subcategory_id = ?)"; $params[] = $currentSub; }
    
    if ($archYear) {
        $sql .= " AND YEAR(n.created_at) = ?"; $countSql .= " AND YEAR(n.created_at) = ?"; $params[] = $archYear;
        if ($archMonth) {
            $sql .= " AND MONTH(n.created_at) = ?"; $countSql .= " AND MONTH(n.created_at) = ?"; $params[] = $archMonth;
        }
    } else {
        $sql .= $dateLimitSQL;
        $countSql .= $dateLimitSQL;
    }

    $gridSql = $sql . " ORDER BY n.created_at DESC";
    $gridCountSql = $countSql;
    if (!$searchQuery && !$currentSub && !$archYear && !$archMonth) {
        $hasRealFeatured = false;
        try { $stmt = $pdo->prepare($sql . " AND n.is_featured = 1 ORDER BY n.created_at DESC LIMIT 5"); $stmt->execute($params); $displayFeatured = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt->fetchAll())); if (!empty($displayFeatured)) $hasRealFeatured = true; } catch (PDOException $e) {}
        if (!$hasRealFeatured) { try { $stmt = $pdo->prepare($sql . " AND n.featured = 1 ORDER BY n.created_at DESC LIMIT 5"); $stmt->execute($params); $displayFeatured = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt->fetchAll())); if (!empty($displayFeatured)) $hasRealFeatured = true; } catch (PDOException $e) {} }
        if (!$hasRealFeatured) { $stmt = $pdo->prepare($sql . " ORDER BY n.created_at DESC LIMIT 5"); $stmt->execute($params); $displayFeatured = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt->fetchAll())); }
        if ($hasRealFeatured) { try { $gridSql = $sql . " AND n.is_featured = 0 ORDER BY n.created_at DESC"; $gridCountSql = $countSql . " AND n.is_featured = 0"; } catch (PDOException $e) { try { $gridSql = $sql . " AND n.featured = 0 ORDER BY n.created_at DESC"; $gridCountSql = $countSql . " AND n.featured = 0"; } catch (PDOException $e) { $gridSql = $sql . " ORDER BY n.created_at DESC"; $gridCountSql = $countSql; } } }
    }
    $stmt = $pdo->prepare($gridCountSql); $stmt->execute($params); $totalGrid = (int)$stmt->fetchColumn();
    $currentPage = max(1, intval($_GET['p'] ?? 1)); $totalPages = max(1, ceil($totalGrid / NEWS_PER_PAGE));
    if ($currentPage > $totalPages) $currentPage = $totalPages;
    $offset = ($currentPage - 1) * NEWS_PER_PAGE;
    $stmt = $pdo->prepare($gridSql . " LIMIT " . NEWS_PER_PAGE . " OFFSET " . $offset); $stmt->execute($params);
    $pagedGrid = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt->fetchAll()));
    if (empty($pagedGrid) && !empty($displayFeatured) && !$searchQuery && !$currentSub && !$archYear && !$archMonth) {
        $stmt = $pdo->prepare($sql . " LIMIT " . NEWS_PER_PAGE . " OFFSET 0"); $stmt->execute($params);
        $pagedGrid = array_map(function($row) { $row['subcategory'] = $row['subcategory_name'] ?? ''; return $row; }, array_map('safeMapNewsKeys', $stmt->fetchAll()));
        $stmt = $pdo->prepare($countSql); $stmt->execute($params); $totalGrid = (int)$stmt->fetchColumn();
        $totalPages = max(1, ceil($totalGrid / NEWS_PER_PAGE));
    }
}

 $seo = buildSEO($page, $singleNews, $currentCat, $searchQuery);
 $canonicalUrl = getCanonicalURL();
 $pagBase = '?page=home';
if ($currentCat) $pagBase .= '&cat=' . urlencode($currentCat);
if ($searchQuery) $pagBase .= '&q=' . urlencode($searchQuery);
if ($currentSub) $pagBase .= '&sub=' . $currentSub;
if ($archYear) $pagBase .= '&y=' . $archYear;
if ($archMonth) $pagBase .= '&m=' . $archMonth;
 $paginationHTML = renderPagination($currentPage, $totalPages, $pagBase);

 $navCatIcons = ['প্রধান খবর'=>'fa-fire','জাতীয়'=>'fa-flag','রাজনীতি'=>'fa-landmark','আন্তর্জাতিক'=>'fa-globe','অর্থনীতি'=>'fa-chart-line','খেলাধুলা'=>'fa-futbol','বিনোদন'=>'fa-film','শিক্ষা'=>'fa-graduation-cap','প্রযুক্তি'=>'fa-microchip','স্বাস্থ্য'=>'fa-heart-pulse','বিশেষ সংবাদ'=>'fa-star','লাইফস্টাইল'=>'fa-spa','ধর্ম'=>'fa-mosque','সংস্কৃতি'=>'fa-masks-theater','মতামত'=>'fa-comment-dots','ক্রাইম'=>'fa-gavel','কৃষি'=>'fa-seedling','ভ্রমণ'=>'fa-plane','চাকরি'=>'fa-briefcase'];

 $bnDays = ['Sunday'=>'রবিবার','Monday'=>'সোমবার','Tuesday'=>'মঙ্গলবার','Wednesday'=>'বুধবার','Thursday'=>'বৃহস্পতিবার','Friday'=>'শুক্রবার','Saturday'=>'শনিবার'];
 $todayStr = date('d F Y') . ', ' . ($bnDays[date('l')] ?? date('l'));

 $isLoggedIn = isset($_SESSION['user_id']);
 $loginLink = $isLoggedIn ? '?page=admin_dashboard' : '?page=admin_login';
 $loginLabel = $isLoggedIn ? 'অ্যাডমিন' : 'লগইন';

 $contactKeys = ['contact_address','contact_email_1','contact_email_2','contact_email_3','contact_phone','contact_phone_2','contact_phone_3','contact_whatsapp','contact_office_hours'];
 $contactVals = [];
try { $in = implode(",", array_fill(0, count($contactKeys), "?")); $cs = $pdo->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_key IN ($in)"); $cs->execute($contactKeys); $csRows = $cs->fetchAll(PDO::FETCH_KEY_PAIR); foreach ($contactKeys as $ck) { $contactVals[$ck] = $csRows[$ck] ?? ""; } } catch (Exception $e) { foreach ($contactKeys as $ck) { $contactVals[$ck] = ""; } }

 $adKeys = ['ad_bottom_1_embed', 'ad_bottom_2_embed'];
 $adVals = loadSettings($pdo, $adKeys);

 $adHeaderBanner = renderAdsByPos($pdo, 'header_banner');
 $adContentTop = renderAdsByPos($pdo, 'content_top');
 $adAfter1st = renderAdsByPos($pdo, 'after_1st_para');
 $adAfter2nd = renderAdsByPos($pdo, 'after_2nd_para');
 $adAfter3rd = renderAdsByPos($pdo, 'after_3rd_para');
 $adContentMiddle = renderAdsByPos($pdo, 'content_middle');
 $adContentBottom = renderAdsByPos($pdo, 'content_bottom');
 $adSidebarTop = renderAdsByPos($pdo, 'sidebar_top');
 $adSidebarMiddle = renderAdsByPos($pdo, 'sidebar_middle');
 $adFooterBanner = renderAdsByPos($pdo, 'footer_banner');
 $adPopup = renderAdsByPos($pdo, 'popup');

 $nseStockList = [['s'=>'RELIANCE.NS','short'=>'RELIANCE'],['s'=>'TCS.NS','short'=>'TCS'],['s'=>'INFY.NS','short'=>'INFY'],['s'=>'HDFCBANK.NS','short'=>'HDFCBANK'],['s'=>'ICICIBANK.NS','short'=>'ICICIBANK'],['s'=>'SBIN.NS','short'=>'SBIN'],['s'=>'WIPRO.NS','short'=>'WIPRO'],['s'=>'BHARTIARTL.NS','short'=>'BHARTIARTL'],['s'=>'ITC.NS','short'=>'ITC'],['s'=>'LT.NS','short'=>'LT'],['s'=>'HINDUNILVR.NS','short'=>'HINDUNILVR'],['s'=>'BAJFINANCE.NS','short'=>'BAJFINANCE'],['s'=>'MARUTI.NS','short'=>'MARUTI'],['s'=>'ADANIENT.NS','short'=>'ADANIENT'],['s'=>'TATAMOTORS.NS','short'=>'TATAMOTORS'],['s'=>'SUNPHARMA.NS','short'=>'SUNPHARMA']];
 $nseCacheFile = __DIR__ . '/cache_nse_stocks.json';
 $nseData = [];
if (file_exists($nseCacheFile) && (time() - filemtime($nseCacheFile)) < 300) { $nseCached = json_decode(file_get_contents($nseCacheFile), true); if (is_array($nseCached)) $nseData = $nseCached; }
if (empty($nseData)) { foreach ($nseStockList as $st) { $ch = curl_init(); curl_setopt_array($ch, [CURLOPT_URL=>"https://query1.finance.yahoo.com/v8/finance/chart/".$st['s']."?range=1d&interval=1d",CURLOPT_RETURNTRANSFER=>true,CURLOPT_TIMEOUT=>6,CURLOPT_SSL_VERIFYPEER=>false,CURLOPT_HTTPHEADER=>['User-Agent: Mozilla/5.0'],CURLOPT_FOLLOWLOCATION=>true]); $resp = curl_exec($ch); $hc = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch); $p=0;$c=0;$cp=0;$ok=false; if($resp&&$hc===200){$j=json_decode($resp,true);if(isset($j['chart']['result'][0]['meta'])){$m=$j['chart']['result'][0]['meta'];if(isset($m['regularMarketPrice'])){$p=round($m['regularMarketPrice'],2);$pc2=$m['chartPreviousClose']??$p;$c=round($p-$pc2,2);$cp=$pc2>0?round(($c/$pc2)*100,2):0;$ok=true;}}} $nseData[]=['short'=>$st['short'],'price'=>$p,'change'=>$c,'changePct'=>$cp,'fetched'=>$ok]; } @file_put_contents($nseCacheFile, json_encode($nseData), LOCK_EX); }

 $placeholderImg = "data:image/svg+xml," . urlencode("<svg xmlns='http://www.w3.org/2000/svg' width='400' height='250'><rect width='400' height='250' fill='%23e8e4de'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='14'>ছবি</text></svg>");
 $placeholderImgSm = "data:image/svg+xml," . urlencode("<svg xmlns='http://www.w3.org/2000/svg' width='120' height='90'><rect width='120' height='90' fill='%23e8e4de'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23999' font-family='sans-serif' font-size='10'>ছবি</text></svg>");

 $breakingItems = []; foreach ($breakingData as $bi) { $breakingItems[] = '<span>' . htmlspecialchars($bi['text']) . '</span>'; }
 $breakingTrackHTML = !empty($breakingItems) ? implode('', $breakingItems) . implode('', $breakingItems) : '';
 $nseItems = []; foreach ($nseData as $ni) { if (!$ni['fetched']) continue; $cls = $ni['change'] >= 0 ? 'nse-up' : 'nse-down'; $sign = $ni['change'] >= 0 ? '+' : ''; $nseItems[] = '<div class="nse-si"><span class="nse-sym">' . htmlspecialchars($ni['short']) . '</span><span class="nse-pr">' . number_format($ni['price'], 2) . '</span><span class="nse-chg ' . $cls . '">' . $sign . number_format($ni['change'], 2) . ' (' . $sign . number_format($ni['changePct'], 2) . '%)</span></div>'; }
 $nseTrackHTML = !empty($nseItems) ? implode('', $nseItems) . implode('', $nseItems) : '';
?>
<!DOCTYPE html>
<html lang="bn" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($seo['title']) ?></title>
<meta name="description" content="<?= htmlspecialchars($seo['description']) ?>">
<meta name="robots" content="<?= $seo['robots'] ?>">
<link rel="canonical" href="<?= htmlspecialchars($canonicalUrl) ?>">
<meta property="og:title" content="<?= htmlspecialchars($seo['title']) ?>">
<meta property="og:description" content="<?= htmlspecialchars($seo['description']) ?>">
<meta property="og:image" content="<?= htmlspecialchars($seo['image']) ?>">
<meta property="og:url" content="<?= htmlspecialchars($canonicalUrl) ?>">
<meta property="og:locale" content="bn_BD">
<meta name="twitter:card" content="summary_large_image">
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<style>
:root{--red:#B71C1C;--red-dark:#7F0000;--gold:#D4950A;--bg:#F4F1EB;--fg:#1A1A1A;--muted:#666;--card:#FFF;--border:#DDD5C8;--nav-bg:#1A1A1A;--sidebar-w:260px}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--fg);line-height:1.6}
a{color:inherit;text-decoration:none}img{max-width:100%;display:block}
.kol-topbar{background:var(--red);color:#fff;padding:6px 0;font-size:12px;border-bottom:1px solid rgba(0,0,0,.2)}
.kol-topbar .container{display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px}
.kol-topbar .tl{display:flex;align-items:center;gap:12px;flex:1;min-width:0}
.kol-topbar .tr{display:flex;align-items:center;gap:8px}
.kol-topbar .clock{font-family:monospace;font-size:12px;color:var(--gold);font-weight:600;letter-spacing:.5px}
.kol-topbar .date{color:rgba(255,255,255,.85);font-size:12px;border-left:1px solid rgba(255,255,255,.2);padding-left:12px}
.kol-topbar .date i{margin-right:4px;opacity:.7}
.kol-si{display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:50%;background:rgba(0,0,0,.2);color:#fff;font-size:10px;transition:all .2s}
.kol-si:hover{background:rgba(255,255,255,.3);transform:translateY(-1px)}
.kol-si.si-fb:hover{background:#1877F2}.kol-si.si-tw:hover{background:#000}.kol-si.si-yt:hover{background:#FF0000}.kol-si.si-ig:hover{background:linear-gradient(45deg,#f09433,#dc2743,#bc1888)}.kol-si.si-tg:hover{background:#0088cc}
.kol-login{font-size:11px;color:rgba(255,255,255,.7);padding:4px 10px;border:1px solid rgba(255,255,255,.2);border-radius:3px;transition:all .2s}
.kol-login:hover{color:#fff;background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.3)}
.breaking-bar{background:var(--red-dark);color:#fff;overflow:hidden;white-space:nowrap;height:38px;display:flex;align-items:stretch;position:relative}
.breaking-bar::before{content:'';position:absolute;left:0;top:0;bottom:0;width:130px;background:linear-gradient(to right,var(--red-dark) 0%,var(--red-dark) 60%,rgba(127,0,0,0.6) 80%,transparent 100%);z-index:3;pointer-events:none}
.breaking-bar::after{content:'';position:absolute;right:0;top:0;bottom:0;width:50px;background:linear-gradient(to left,var(--red-dark) 0%,transparent 100%);z-index:3;pointer-events:none}
.breaking-label{background:var(--gold);color:#000;padding:0 16px;font-weight:700;font-size:12px;height:100%;display:flex;align-items:center;flex-shrink:0;letter-spacing:.5px;position:relative;z-index:5;box-shadow:6px 0 12px rgba(127,0,0,0.8)}
.breaking-label i{margin-right:6px;font-size:11px}
.breaking-track{display:inline-flex;width:max-content;animation:bts 40s linear infinite;padding-left:30px;will-change:transform;position:relative;z-index:1}
.breaking-track span{padding:0 40px;font-size:13px;font-weight:500;white-space:nowrap}
.breaking-track span::before{content:'\25C6';margin-right:8px;color:var(--gold);font-size:8px}
@keyframes bts{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.breaking-bar:hover .breaking-track{animation-play-state:paused}
.kol-header{text-align:center;padding:16px 0 12px;border-bottom:3px solid var(--red);background:#fff}
.kol-header h1{font-family:'Noto Serif Bengali',serif;font-weight:900;font-size:clamp(28px,5vw,48px);color:var(--red);letter-spacing:3px;line-height:1.1}
.kol-header .tagline{font-size:12px;color:var(--muted);letter-spacing:4px;text-transform:uppercase;margin-top:2px}
.kol-nav{background:var(--nav-bg);position:sticky;top:0;z-index:100;border-bottom:2px solid var(--red)}
.kol-nav .container{display:flex;align-items:stretch;overflow-x:auto;scrollbar-width:none}
.kol-nav .container::-webkit-scrollbar{display:none}
.kn-link{color:#ccc;padding:12px 14px;font-size:12.5px;font-weight:600;white-space:nowrap;transition:all .2s;border-bottom:2px solid transparent;display:flex;align-items:center;gap:6px;flex-shrink:0}
.kn-link i{font-size:11px;opacity:.4;transition:opacity .2s}
.kn-link:hover{color:var(--gold);background:rgba(255,255,255,.04);border-bottom-color:rgba(212,149,67,.3)}
.kn-link:hover i{opacity:1}
.kn-link.active{color:var(--gold);background:rgba(212,149,67,.08);border-bottom-color:var(--gold)}
.kn-link.active i{opacity:1}
.kn-home{color:#fff!important;font-weight:700!important}
.kn-home i{font-size:13px;opacity:.9!important}
.kn-sep{width:1px;background:rgba(255,255,255,.08);flex-shrink:0;margin:10px 2px}
.kn-inline{position:relative;flex-shrink:0;display:inline-flex;align-items:stretch}
.kn-inline .kn-link{padding-right:6px}
.kn-inline .kn-arrow{font-size:7px;opacity:.4;cursor:pointer;padding:4px 8px 4px 4px;transition:all .2s;color:#ccc;display:flex;align-items:center}
.kn-inline .kn-arrow:hover{opacity:1;color:var(--gold)}
.kn-inline.open .kn-arrow{transform:rotate(180deg);opacity:1;color:var(--gold)}
.kn-drop{display:none;position:fixed;min-width:220px;background:var(--nav-bg);border:1px solid rgba(255,255,255,.12);border-top:2px solid var(--gold);border-radius:0 0 6px 6px;box-shadow:0 10px 30px rgba(0,0,0,.7);z-index:10000;padding:4px 0;animation:kdSlide .15s ease}
.kn-inline.open .kn-drop{display:block}
@keyframes kdSlide{from{opacity:0;transform:translateY(-4px)}to{opacity:1;transform:translateY(0)}}
.kn-drop-link{display:flex;align-items:center;gap:6px;padding:9px 14px;font-size:12px;color:rgba(255,255,255,.6);transition:all .15s}
.kn-drop-link:hover{color:var(--gold);background:rgba(212,149,67,.1);padding-left:20px}
.kn-drop-link.active{color:var(--gold);background:rgba(212,149,67,.12)}
.kn-drop-link .kn-dc{font-size:9px;color:rgba(255,255,255,.25);background:rgba(255,255,255,.06);padding:1px 6px;border-radius:8px;margin-left:auto}
.kn-drop-all{padding:9px 14px;font-size:11.5px;font-weight:600;color:var(--gold);border-top:1px solid rgba(255,255,255,.08);margin-top:3px;cursor:pointer;transition:background .15s;display:block}
.kn-drop-all:hover{background:rgba(212,149,67,.1)}
.kn-more-btn{color:#ccc;padding:12px 12px;font-size:12px;font-weight:600;white-space:nowrap;transition:all .2s;display:inline-flex;align-items:center;gap:6px;cursor:pointer;background:none;border:none;font-family:inherit;border-bottom:2px solid transparent;flex-shrink:0}
.kn-more-btn i.fa-th{font-size:11px;opacity:.5}
.kn-more-btn i.fa-chevron-down{font-size:8px;opacity:.3;transition:transform .2s}
.kn-more-btn:hover{color:var(--gold);background:rgba(255,255,255,.04)}
.kn-more-btn:hover i{opacity:1}
.kn-more-btn:hover i.fa-chevron-down{transform:rotate(180deg)}
.kn-more-btn.is-active{color:var(--gold);border-bottom-color:var(--gold)}
.kn-panel{display:none;position:absolute;top:100%;right:0;min-width:320px;max-width:380px;max-height:70vh;overflow-y:auto;background:var(--nav-bg);border:1px solid rgba(255,255,255,.1);border-top:2px solid var(--gold);border-radius:0 0 8px 8px;box-shadow:0 8px 24px rgba(0,0,0,.6);z-index:999;padding:6px 0;scrollbar-width:4px}
.kn-panel::-webkit-scrollbar{width:4px}.kn-panel::-webkit-scrollbar-thumb{background:rgba(255,255,255,.1);border-radius:2px}
.kn-panel.open{display:block;animation:kdSlide .15s ease}
.kn-pl-link{display:flex;align-items:center;gap:8px;padding:9px 14px;font-size:12.5px;font-weight:500;color:#ccc;transition:all .15s;position:relative;cursor:pointer}
.kn-pl-link:hover{background:rgba(255,255,255,.04);color:var(--gold)}
.kn-pl-link.active{color:var(--gold);background:rgba(212,149,67,.08)}
.kn-pl-link .kpi{width:28px;height:28px;display:flex;align-items:center;justify-content:center;background:rgba(255,255,255,.06);border-radius:5px;font-size:11px;color:#aaa;flex-shrink:0;transition:all .15s}
.kn-pl-link:hover .kpi{background:rgba(212,149,67,.15);color:var(--gold)}
.kn-pl-link .kpn{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.kn-pl-link .kpc{font-size:10px;color:rgba(255,255,255,.25);background:rgba(255,255,255,.06);padding:2px 7px;border-radius:8px;text-align:center;flex-shrink:0}
.kn-pl-link.has-psubs{padding-right:44px}
.kn-pl-link.has-psubs .kpc{margin-right:0}
.kn-sub-tog{position:absolute;right:6px;top:50%;transform:translateY(-50%);width:30px;height:30px;display:flex;align-items:center;justify-content:center;background:rgba(212,149,67,.2);border:1px solid rgba(212,149,67,.35);color:var(--gold);font-size:10px;cursor:pointer;border-radius:5px;transition:all .2s;z-index:20}
.kn-sub-tog:hover{background:rgba(212,149,67,.4);transform:translateY(-50%) scale(1.1)}
.kn-sub-tog.open{background:var(--gold);color:#000;border-color:var(--gold)}
.kn-sub-tog i{transition:transform .3s}.kn-sub-tog.open i{transform:rotate(180deg)}
.kn-subs{display:none;padding:4px 0;border-top:1px solid rgba(255,255,255,.06);margin:0 8px 4px;background:rgba(0,0,0,.25);border-radius:0 0 5px 5px}
.kn-subs.open{display:block}
.kn-sub-link{display:flex;align-items:center;gap:6px;padding:7px 14px 7px 42px;font-size:11.5px;color:rgba(255,255,255,.5);transition:all .15s;position:relative}
.kn-sub-link::before{content:'';position:absolute;left:30px;top:50%;transform:translateY(-50%);width:5px;height:5px;border-radius:50%;background:rgba(255,255,255,.15);transition:all .15s}
.kn-sub-link:hover{color:var(--gold);background:rgba(212,149,67,.06)}
.kn-sub-link:hover::before{background:var(--gold);left:34px;box-shadow:0 0 6px rgba(212,149,67,.5)}
.kn-sub-link.active{color:var(--gold);background:rgba(212,149,67,.1)}
.kn-sub-link.active::before{background:var(--gold);box-shadow:0 0 6px rgba(212,149,67,.5)}
.kn-sub-link .ksn{flex:1}
.kn-sub-link .ksc{font-size:9px;color:rgba(255,255,255,.25);background:rgba(255,255,255,.06);padding:1px 6px;border-radius:6px}
.kn-search{margin-left:auto;display:flex;align-items:center;flex-shrink:0}
.kn-search form{display:flex;align-items:center;background:rgba(255,255,255,.04);border:1px solid rgba(255,255,255,.08);border-radius:4px;height:32px;margin:6px 10px 6px 4px;overflow:hidden;transition:all .2s}
.kn-search form:focus-within{border-color:rgba(212,149,67,.4);background:rgba(255,255,255,.06)}
.kn-search input{width:120px;padding:0 8px;height:100%;border:none;background:transparent;color:#ddd;font-size:12px;font-family:inherit;outline:none;transition:width .3s}
.kn-search input::placeholder{color:rgba(255,255,255,.2);font-size:11px}
.kn-search input:focus{width:160px;color:#fff}
.kn-search button{display:flex;align-items:center;justify-content:center;width:30px;height:100%;background:transparent;color:rgba(255,255,255,.3);border:none;border-left:1px solid rgba(255,255,255,.08);cursor:pointer;transition:color .2s}
.kn-search button:hover{color:var(--gold)}
.container{max-width:1200px;margin:0 auto;padding:0 12px}
.kol-layout{display:grid;grid-template-columns:var(--sidebar-w) 1fr;gap:16px;align-items:start}
.kol-layout.sb-hidden{grid-template-columns:0px 1fr;gap:0}
.kol-sidebar{position:sticky;top:72px;max-height:calc(100vh - 72px);overflow-y:auto;scrollbar-width:3px;transition:max-height .4s,opacity .4s}
.kol-sidebar::-webkit-scrollbar{width:3px}.kol-sidebar::-webkit-scrollbar-thumb{background:#ccc;border-radius:2px}
.sb-close{display:flex;align-items:center;justify-content:center;gap:6px;width:100%;padding:8px 12px;background:var(--bg);border-bottom:1px solid var(--border);color:var(--muted);font-size:11px;font-weight:600;cursor:pointer;transition:all .15s;border-radius:6px 6px 0 0;margin:-1px -1px 0 -1px}
.sb-close:hover{background:var(--red);color:#fff}
.sb-close i{font-size:10px}
.kol-main{min-width:0;overflow:hidden}
.ad-bottom-sec{margin:24px 0;display:flex;flex-wrap:wrap;gap:16px;justify-content:center}
.ad-bottom-sec .ad-block{flex:1;min-width:300px;max-width:728px;background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;text-align:center;margin-bottom:0}
.ad-block .ad-label{font-size:10px;color:var(--muted);text-transform:uppercase;letter-spacing:1px;padding:5px 0;background:var(--bg);border-bottom:1px solid var(--border)}
.ad-block .ad-content{padding:15px;min-height:100px;display:flex;align-items:center;justify-content:center;flex-direction:column}
.ad-block .ad-content img{max-width:100%;height:auto;border-radius:4px}
.ad-wrapper{margin:15px auto;text-align:center;max-width:100%;overflow:hidden}
.ad-wrapper img{max-width:100%;height:auto;border-radius:8px}
.ad-label{font-size:10px;color:#999;text-transform:uppercase;letter-spacing:1px;margin-bottom:5px}
.sb-block{background:var(--card);border:1px solid var(--border);border-radius:6px;margin-bottom:12px;overflow:hidden}
.sb-block h3{font-family:'Noto Serif Bengali',serif;font-size:14px;font-weight:700;color:var(--red);padding:10px 12px 8px;border-bottom:2px solid var(--red);display:flex;align-items:center;gap:6px}
.sb-block h3 i{font-size:12px}
.sb-cat-list{list-style:none;padding:4px 0}
.sb-ci{position:relative}
.sb-ci a{display:flex;align-items:center;gap:8px;padding:6px 8px;border-radius:4px;font-size:12.5px;font-weight:500;color:var(--fg);transition:all .15s;text-decoration:none}
.sb-ci.has-subs a{padding-right:32px}
.sb-ci a:hover,.sb-ci a.active{background:rgba(183,28,28,.06);color:var(--red)}
.sb-ci a i{width:24px;height:24px;display:flex;align-items:center;justify-content:center;background:rgba(183,28,28,.08);border-radius:4px;font-size:10px;color:var(--red);flex-shrink:0}
.sb-ci a span{flex:1}
.sb-cc{font-size:10px;color:var(--muted);background:var(--bg);padding:0 5px;border-radius:8px;min-width:22px;text-align:center}
.sb-st{position:absolute;right:2px;top:4px;width:26px;height:26px;display:flex;align-items:center;justify-content:center;background:rgba(183,28,28,.08);border:1px solid rgba(183,28,28,.15);color:var(--red);font-size:8px;cursor:pointer;border-radius:4px;transition:all .2s;z-index:20}
.sb-st:hover{background:rgba(183,28,28,.18);transform:scale(1.1)}
.sb-st.open{background:var(--red);color:#fff;border-color:var(--red)}
.sb-st i{transition:transform .3s}.sb-st.open i{transform:rotate(180deg)}
.sb-sl{display:none;list-style:none;padding:2px 0 2px 10px;margin:0 0 4px 12px;border-left:2px solid var(--border)}
.sb-sl.open{display:block}
.sb-si a{display:flex;align-items:center;gap:5px;padding:5px 8px;border-radius:4px;font-size:11.5px;color:var(--muted);transition:all .15s;text-decoration:none}
.sb-si a:hover,.sb-si a.active{background:rgba(183,28,28,.06);color:var(--red)}
.sb-si a i{font-size:7px;opacity:.4;width:10px;text-align:center;flex-shrink:0}
.sb-si a:hover i{opacity:1}
.sb-si .ssc{font-size:9px;color:var(--muted);background:var(--bg);padding:0 5px;border-radius:6px;margin-left:auto}
.pop-item{display:flex;gap:10px;padding:8px 0;border-bottom:1px solid var(--border);cursor:pointer;transition:background .15s;border-radius:4px;padding-left:4px}
.pop-item:hover{background:rgba(183,28,28,.04)}
.pop-item:last-child{border-bottom:none}
.pop-item img{width:58px;height:44px;object-fit:cover;border-radius:3px;flex-shrink:0}
.pop-item .pi-info{flex:1;min-width:0}
.pop-item .pi-t{font-size:12px;font-weight:600;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.pop-item .pi-m{font-size:10px;color:var(--muted);margin-top:2px}
.tag-pill{display:inline-flex;align-items:center;gap:3px;padding:3px 10px;border-radius:16px;background:rgba(183,28,28,.06);color:var(--red);font-size:11px;font-weight:600;transition:all .15s;line-height:1.5}
.tag-pill:hover{background:var(--red);color:#fff}
.tag-pill i{font-size:8px}
.cat-panel-sec{margin-bottom:28px}
.cat-panel-head{display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;border-bottom:2px solid var(--red);padding-bottom:8px}
.cat-panel-title{font-family:'Noto Serif Bengali',serif;font-size:18px;font-weight:700;color:var(--fg);display:flex;align-items:center;gap:8px;margin:0}
.cat-panel-title i{font-size:14px}
.cat-panel-all{font-size:12px;font-weight:600;color:var(--red);text-decoration:none;transition:all .2s;display:flex;align-items:center;gap:4px}
.cat-panel-all:hover{color:var(--red-dark);gap:6px}
.cat-panel-row{display:grid;grid-template-columns:repeat(5,1fr);gap:12px}
.cat-panel-card{background:var(--card);border:1px solid var(--border);border-radius:6px;overflow:hidden;transition:all .25s;cursor:pointer;text-decoration:none;color:inherit;border-top:3px solid transparent}
.cat-panel-card:hover{transform:translateY(-4px);box-shadow:0 8px 24px rgba(0,0,0,.12);border-top-color:var(--red)}
.cpc-img{height:120px;overflow:hidden}
.cpc-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.cat-panel-card:hover .cpc-img img{transform:scale(1.08)}
.cpc-body{padding:10px 12px}
.cpc-body h3{font-size:13px;font-weight:600;line-height:1.4;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;color:var(--fg)}
.cpc-time{font-size:11px;color:var(--muted);margin-top:6px;display:flex;align-items:center;gap:4px}
.cpc-time i{font-size:10px}
.feat-section{margin-bottom:20px}
.feat-grid{display:grid;grid-template-columns:1.5fr 1fr;gap:12px;align-items:stretch}
.feat-main{position:relative;border-radius:8px;overflow:hidden;cursor:pointer;min-height:380px}
.feat-main img{width:100%;height:100%;object-fit:cover;transition:transform .5s}
.feat-main:hover img{transform:scale(1.04)}
.feat-main .ov{position:absolute;bottom:0;left:0;right:0;padding:24px 20px;background:linear-gradient(transparent,rgba(0,0,0,.9));color:#fff}
.feat-main .ov .cb{display:inline-block;background:var(--red);padding:2px 10px;border-radius:2px;font-size:11px;font-weight:700;margin-bottom:8px}
.feat-main .ov h2{font-size:20px;font-weight:700;line-height:1.35}
.feat-main .ov p{font-size:13px;line-height:1.6;color:rgba(255,255,255,.78);display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.feat-side{display:flex;flex-direction:column;gap:0;overflow:hidden;border-radius:8px;background:var(--card);border:1px solid var(--border)}
.feat-si{display:flex;gap:0;border-left:3px solid var(--red);overflow:hidden;transition:background .2s}
.feat-si+.feat-si{border-top:1px solid var(--border)}
.feat-si:first-child{border-radius:8px 0 0 0}
.feat-si:last-child{border-radius:0 0 0 8px}
.feat-si:hover{background:#fdf6f6}
.feat-si img{width:110px;height:90px;object-fit:cover;flex-shrink:0}
.feat-si .fi{padding:10px 12px;display:flex;flex-direction:column;justify-content:center;min-width:0}
.feat-si .fcs{font-size:10px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:.4px;margin-bottom:3px}
.feat-si h3{font-size:12.5px;font-weight:600;line-height:1.35;margin:0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.feat-si .fdt{font-size:10px;color:var(--muted);margin-top:5px;display:flex;align-items:center;gap:4px}
.feat-si .fdt i{font-size:9px}
.vid-sec{margin-bottom:28px}
.vid-grid{display:grid;grid-template-columns:1.4fr 1fr;gap:14px}
.vid-main{position:relative;border-radius:8px;overflow:hidden;background:#000;cursor:pointer;min-height:340px}
.vid-poster{width:100%;height:100%;object-fit:cover;position:absolute;top:0;left:0;z-index:2;transition:transform .4s}
.vid-main:hover .vid-poster{transform:scale(1.04)}
.vid-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:68px;height:68px;background:rgba(183,28,28,.85);border-radius:50%;color:#fff;font-size:24px;display:flex;align-items:center;justify-content:center;z-index:3;transition:all .2s;box-shadow:0 4px 20px rgba(0,0,0,.5)}
.vid-main:hover .vid-play{background:var(--red);transform:translate(-50%,-50%) scale(1.12)}
.vid-info{position:absolute;bottom:0;left:0;right:0;padding:18px 20px;background:linear-gradient(transparent,rgba(0,0,0,.9));color:#fff;z-index:4}
.vid-info .cb{display:inline-block;background:var(--red);padding:2px 10px;border-radius:2px;font-size:11px;font-weight:700;margin-bottom:6px}
.vid-info h3{font-size:18px;font-weight:700;line-height:1.4}
.vid-info .vdt{font-size:11px;color:rgba(255,255,255,.7);margin-top:4px;display:flex;align-items:center;gap:6px}
.vid-info .vdt i{font-size:10px}
.vid-side{display:flex;flex-direction:column;gap:10px}
.vid-card{display:flex;gap:0;border-radius:6px;overflow:hidden;background:var(--card);border:1px solid var(--border);cursor:pointer;transition:all .2s}
.vid-card:hover{box-shadow:0 4px 12px rgba(0,0,0,.08);transform:translateX(2px)}
.vid-thumb{width:160px;min-height:100px;position:relative;overflow:hidden;flex-shrink:0;background:#000}
.vid-thumb img{width:100%;height:100%;object-fit:cover;transition:transform .3s}
.vid-card:hover .vid-thumb img{transform:scale(1.06)}
.vid-sm-play{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:36px;height:36px;background:rgba(183,28,28,.85);border-radius:50%;color:#fff;font-size:11px;display:flex;align-items:center;justify-content:center;z-index:3;transition:all .2s}
.vid-card:hover .vid-sm-play{background:var(--red);transform:translate(-50%,-50%) scale(1.15)}
.vid-card .vc-info{padding:10px 12px;display:flex;flex-direction:column;justify-content:center;min-width:0;flex:1}
.vid-card .vc-cat{font-size:10px;color:var(--red);font-weight:700}
.vid-card h4{font-size:12.5px;font-weight:600;line-height:1.4;margin:2px 0 0;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.vid-card .vc-time{font-size:10px;color:var(--muted);margin-top:4px}
.vid-card .vc-time i{font-size:9px;margin-right:3px}
.news-sec{padding:12px 0 24px}
.sec-title{font-family:'Noto Serif Bengali',serif;font-size:20px;font-weight:700;color:var(--fg);padding-bottom:8px;margin-bottom:14px;border-bottom:3px solid var(--red);display:inline-block}
.news-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.nc{background:var(--card);border-radius:6px;overflow:hidden;cursor:pointer;transition:box-shadow .2s,transform .2s;border:1px solid var(--border)}
.nc:hover{box-shadow:0 4px 16px rgba(0,0,0,.1);transform:translateY(-2px)}
.nc .nc-img{height:160px;overflow:hidden}
.nc .nc-img img{width:100%;height:100%;object-fit:cover;transition:transform .4s}
.nc:hover .nc-img img{transform:scale(1.06)}
.nc .nc-body{padding:12px}
.nc .nc-cat{font-size:10px;color:var(--red);font-weight:700;text-transform:uppercase;letter-spacing:.4px}
.nc h3{font-size:14px;font-weight:700;margin-top:3px;line-height:1.4}
.nc p{font-size:12px;color:var(--muted);margin-top:4px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}
.nc .nc-meta{display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:var(--muted);border-top:1px solid var(--border);padding-top:7px}
.single-wrap{background:var(--card);border:1px solid var(--border);border-radius:6px;padding:24px}
.single-wrap .cb{display:inline-block;background:var(--red);color:#fff;padding:3px 12px;border-radius:3px;font-size:12px;font-weight:700;margin-bottom:10px;text-decoration:none}
.single-wrap h1{font-family:'Noto Serif Bengali',serif;font-size:24px;font-weight:900;line-height:1.35}
.single-meta{display:flex;gap:16px;color:var(--muted);font-size:13px;margin-top:8px;padding-bottom:10px;border-bottom:2px solid var(--border);flex-wrap:wrap}
.single-img{margin:14px 0;border-radius:4px;overflow:hidden}
.single-img img{width:100%;border-radius:4px}
.single-content{font-size:16px;line-height:1.9;color:#333}
.single-content p{margin-bottom:14px}
.single-content h2,.single-content h3,.single-content h4{font-family:'Noto Serif Bengali',serif;margin:20px 0 10px;color:#1a1a1a}
.single-content img {
    max-width: 80%;
    height: auto;
    border-radius: 8px;
    margin: 20px auto; /* মাঝখানে থাকবে */
    display: block;
    box-shadow: 0 4px 10px rgba(0,0,0,0.1);
}
.single-tags{margin-top:18px;padding-top:14px;border-top:1px solid var(--border);display:flex;flex-wrap:wrap;gap:6px;align-items:center}
.single-tags-label{font-size:12px;font-weight:700;color:var(--muted);margin-right:4px}
.pg{display:flex;justify-content:center;align-items:center;gap:4px;padding:24px 0 12px;flex-wrap:wrap}
.pg a,.pg span{display:inline-flex;align-items:center;justify-content:center;min-width:36px;height:36px;padding:0 10px;border:1px solid var(--border);border-radius:4px;font-size:13px;font-weight:600;transition:all .2s;cursor:pointer;text-decoration:none}
.pg a:hover{border-color:var(--red);color:var(--red)}
.pg .pa{background:var(--red);color:#fff;border-color:var(--red)}
.pg .pd{opacity:.4;pointer-events:none}
.pg .pdd{border:none;cursor:default}
.rel-sec{padding:20px 0 8px}
.rel-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px}
.no-res{text-align:center;padding:50px 20px;color:var(--muted)}
.no-res i{font-size:42px;color:var(--border);margin-bottom:12px}
.no-res h3{font-size:18px;color:var(--fg);margin-bottom:6px}
.no-res a{display:inline-block;margin-top:12px;padding:8px 20px;background:var(--red);color:#fff;border-radius:4px;font-weight:600;text-decoration:none}
.kol-footer{background:var(--nav-bg);color:#aaa;padding:24px 0 16px;margin-top:30px;border-top:3px solid var(--red)}
.ft-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:24px;margin-bottom:18px}
.ft-grid h3{font-family:'Noto Serif Bengali',serif;font-size:18px;font-weight:900;color:#fff;margin-bottom:10px;padding-bottom:8px;border-bottom:2px solid var(--red)}
.ft-grid h4{color:var(--gold);font-size:13px;font-weight:700;margin-bottom:10px}
.ft-about p{font-size:12px;line-height:1.7;color:#888;margin-bottom:12px}
.ft-social{display:flex;gap:8px;flex-wrap:wrap}
.ft-social a{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:50%;background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);color:#888;font-size:13px;transition:all .2s;text-decoration:none}
.ft-social a:hover{transform:translateY(-2px);color:#fff;border-color:transparent}
.ft-social a.sf:hover{background:#1877F2}.ft-social a.sx:hover{background:#000}.ft-social a.sy:hover{background:#FF0000}.ft-social a.si:hover{background:linear-gradient(45deg,#f09433,#dc2743,#bc1888)}.ft-social a.st:hover{background:#0088cc}
.ft-links{list-style:none}
.ft-links li{margin-bottom:6px}
.ft-links li a{font-size:12px;color:#888;transition:color .15s;text-decoration:none}
.ft-links li a:hover{color:var(--gold);padding-left:3px}
.ft-bottom{border-top:1px solid rgba(255,255,255,.06);padding-top:14px;text-align:center;font-size:11px;color:#555}
.ft-bottom a{color:var(--gold)}
.ft-bottom a:hover{color:var(--red)}
.ft-contact h4{display:flex;align-items:center}
.ft-contact-list{list-style:none;padding:0;margin:0}
.ft-addr-item{display:flex;gap:10px;margin-bottom:10px;font-size:12px;color:#888;line-height:1.6;align-items:flex-start}
.ft-addr-item i{color:var(--gold);font-size:11px;width:16px;height:16px;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:3px;background:rgba(212,149,67,.15);border-radius:3px;padding:2px}
.ft-addr-item a{color:#888;transition:color .15s;text-decoration:none}
.ft-addr-item a:hover{color:var(--gold)}
.nse-bar{background:#080810;color:#ccc;display:flex;align-items:stretch;height:36px;overflow:hidden;border-top:1px solid rgba(255,255,255,.04);position:relative}
.nse-bar::before{content:'';position:absolute;inset:0;background:linear-gradient(90deg,rgba(0,0,0,.8) 0%,transparent 6%,transparent 94%,rgba(0,0,0,.8) 100%);z-index:2;pointer-events:none}
.nse-lbl{background:linear-gradient(135deg,#0d47a1,#1565c0);padding:0 14px;display:flex;align-items:center;gap:5px;font-size:10px;font-weight:700;color:#fff;flex-shrink:0;z-index:3;white-space:nowrap;letter-spacing:.4px}
.nse-lbl i{color:#64b5f6;font-size:12px}
.nse-scroll{flex:1;overflow:hidden}
.nse-track{display:inline-flex;align-items:center;white-space:nowrap;animation:nseSc 70s linear infinite;height:100%}
.nse-bar:hover .nse-track{animation-play-state:paused}
@keyframes nseSc{0%{transform:translateX(0)}100%{transform:translateX(-50%)}}
.nse-si{display:inline-flex;align-items:center;gap:6px;padding:0 16px;height:100%;border-right:1px solid rgba(255,255,255,.04);transition:background .15s}
.nse-sym{font-size:10px;font-weight:700;color:#90caf9;letter-spacing:.3px;min-width:60px}
.nse-pr{font-size:12px;font-weight:600;color:#e0e0e0;font-variant-numeric:tabular-nums;min-width:55px}
.nse-chg{font-size:9px;font-weight:700;font-variant-numeric:tabular-nums}
.nse-up{color:#00c853}.nse-down{color:#ff1744}
.sb-toggle{position:fixed;bottom:20px;right:20px;width:44px;height:44px;border-radius:50%;background:var(--red);color:#fff;border:none;font-size:18px;z-index:90;box-shadow:0 4px 16px rgba(183,28,28,.4);transition:all .2s;align-items:center;justify-content:center;display:flex}
.sb-toggle:hover{transform:scale(1.1);box-shadow:0 6px 20px rgba(183,28,28,.5)}

.video-embed { position: relative; width: 100%; padding-bottom: 56.25%; height: 0; background: #000; overflow: hidden; }
.video-embed iframe, .video-embed video, .video-embed object, .video-embed embed { position: absolute; top: 0; left: 0; width: 100% !important; height: 100% !important; border: 0; }

.news-video-wrapper {
    position: relative;
    width: 100%;
    max-width: 700px;
    margin: 20px auto;
    aspect-ratio: 16 / 9;
    background: #000;
    border-radius: 8px;
    overflow: hidden;
}
.news-video-wrapper iframe {
    position: absolute;
    top: 0;
    left: 0;
    width: 100% !important;
    height: 100% !important;
    border: 0;
}
.single-content iframe[src*="youtube.com"],
.single-content iframe[src*="vimeo.com"] {
    max-width: 700px;
    width: 100%;
    height: auto;
    aspect-ratio: 16 / 9;
    margin: 20px auto;
    display: block;
    border: none;
    border-radius: 8px;
}

@media(max-width:1024px){
    .kol-layout{grid-template-columns:1fr}
    .kol-sidebar{position:fixed;left:-280px;top:0;bottom:0;width:280px;background:var(--bg);z-index:200;box-shadow:4px 0 24px rgba(0,0,0,.2);transition:left .3s;max-height:100vh;overflow-y:auto}
    .kol-sidebar.open{left:0}
    .sb-close{display:none}
    .vid-grid{grid-template-columns:1fr}
    .cat-panel-row{grid-template-columns:repeat(3,1fr)}
    .feat-grid{grid-template-columns:1fr}
    .feat-main{min-height:280px}
    .news-grid{grid-template-columns:repeat(2,1fr)}
}
@media(max-width:640px){
    .kol-topbar .date{display:none}
    .kn-search input{width:90px}
    .kn-search input:focus{width:120px}
    .kn-panel{min-width:280px;max-width:calc(100vw - 24px);right:0}
    .vid-main{min-height:220px}
    .vid-grid{grid-template-columns:1fr}
    .vid-card{flex-direction:column}
    .vid-thumb{width:100%;min-height:180px}
    .cat-panel-row{grid-template-columns:repeat(2,1fr)}
    .news-grid{grid-template-columns:1fr}
    .feat-side .feat-si img{width:90px;height:72px}
    .rel-grid{grid-template-columns:1fr}
    .single-wrap{padding:16px}
    .single-wrap h1{font-size:20px}
}
</style>
</head>
<body>

<div class="kol-topbar">
<div class="container">
<div class="tl">
<span class="clock" id="liveClock"></span>
<span class="date"><i class="far fa-calendar-alt"></i> <?= htmlspecialchars($todayStr) ?></span>
</div>
<div class="tr">
<a href="#" class="kol-si si-fb" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
<a href="#" class="kol-si si-tw" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
<a href="#" class="kol-si si-yt" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
<a href="#" class="kol-si si-ig" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
<a href="#" class="kol-si si-tg" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
<a href="<?= htmlspecialchars($loginLink) ?>" class="kol-login" target="_blank" rel="noopener noreferrer"><i class="fas fa-user-shield"></i> <?= htmlspecialchars($loginLabel) ?></a>
</div>
</div>
</div>

<?php if (!empty($breakingTrackHTML)): ?>
<div class="breaking-bar">
<div class="breaking-label"><i class="fas fa-bolt"></i> ব্রেকিং</div>
<div class="breaking-track"><?= $breakingTrackHTML ?></div>
</div>
<?php endif; ?>

<header class="kol-header">
<div class="container">
<h1>সংবাদ সংকলন</h1>
<div class="tagline">Truthy &amp; Trusted News Portal</div>
</div>
</header>

<?php if (!empty($adHeaderBanner)) echo $adHeaderBanner; ?>

<nav class="kol-nav" id="kolNav">
<div class="container">
<a href="?" class="kn-link kn-home"><i class="fas fa-home"></i> হোম</a>
<div class="kn-sep"></div>
<a href="?page=videos" class="kn-link<?= ($page === 'videos' || $page === 'video') ? ' active' : '' ?>"><i class="fas fa-video"></i> ভিডিও</a>
<div class="kn-sep"></div>
<?php foreach ($navInlineCats as $nci): ?>
<?php
 $ncIcon = $navCatIcons[$nci] ?? 'fa-folder';
 $ncSubs = $catSubcategories[$nci] ?? [];
 $ncHasSubs = !empty($ncSubs);
 $ncActive = ($currentCat === $nci && !$currentSub);
?>
<?php if ($ncHasSubs): ?>
<div class="kn-inline" data-cat="<?= htmlspecialchars($nci) ?>">
<a href="?cat=<?= urlencode($nci) ?>" class="kn-link<?= $ncActive ? ' active' : '' ?>"><i class="fas <?= $ncIcon ?>"></i> <?= htmlspecialchars($nci) ?></a>
<span class="kn-arrow"><i class="fas fa-chevron-down"></i></span>
<div class="kn-drop">
<?php foreach ($ncSubs as $ns): $nsActive = ($currentSub === (int)$ns['id']); ?>
<a href="?cat=<?= urlencode($nci) ?>&sub=<?= $ns['id'] ?>" class="kn-drop-link<?= $nsActive ? ' active' : '' ?>"><?= htmlspecialchars($ns['name']) ?><?php $sc = $subPostCounts[$ns['id']] ?? 0; if ($sc > 0): ?><span class="kn-dc"><?= $sc ?></span><?php endif; ?></a>
<?php endforeach; ?>
<a href="?cat=<?= urlencode($nci) ?>" class="kn-drop-all"><i class="fas fa-th-list"></i> সব দেখুন</a>
</div>
</div>
<?php else: ?>
<a href="?cat=<?= urlencode($nci) ?>" class="kn-link<?= $ncActive ? ' active' : '' ?>"><i class="fas <?= $ncIcon ?>"></i> <?= htmlspecialchars($nci) ?></a>
<?php endif; ?>
<?php endforeach; ?>

<div class="kn-sep"></div>
<div class="kn-inline" id="archiveMenu">
    <a href="#" class="kn-link" onclick="event.preventDefault()"><i class="fas fa-clock-rotate-left"></i> আর্কাইভ</a>
    <span class="kn-arrow"><i class="fas fa-chevron-down"></i></span>
    <div class="kn-drop" style="min-width: 180px;">
        <?php if(!empty($archiveDates)): ?>
            <?php foreach($archiveDates as $ad): 
                $y = $ad['y'];
                $m = $ad['m'];
                $mName = $bnMonths[$m] ?? '';
                $archActive = ($archYear == $y && $archMonth == $m);
            ?>
                <a href="?y=<?= $y ?>&m=<?= $m ?>" class="kn-drop-link<?= $archActive ? ' active' : '' ?>">
                    <i class="far fa-calendar-alt"></i> <?= htmlspecialchars($mName . ' ' . $y) ?>
                </a>
            <?php endforeach; ?>
        <?php else: ?>
            <span class="kn-drop-link" style="cursor:default; justify-content:center;">কোনো আর্কাইভ নেই</span>
        <?php endif; ?>
    </div>
</div>

<div class="kn-sep"></div>
<button class="kn-more-btn" id="knMoreBtn"><i class="fas fa-th"></i> আরও <i class="fas fa-chevron-down"></i></button>
<div class="kn-search">
<form action="?" method="GET"><input type="hidden" name="page" value="home"><input type="text" name="q" placeholder="খবর খুঁজুন..." value="<?= htmlspecialchars($searchQuery) ?>" aria-label="খবর খুঁজুন"><button type="submit" aria-label="সার্চ"><i class="fas fa-search"></i></button></form>
</div>
</div>
<div class="kn-panel" id="knPanel">
<?php foreach ($categories as $cat): $cn = $cat['name']; $cIcon = $navCatIcons[$cn] ?? 'fa-folder'; $cSubs = $catSubcategories[$cn] ?? []; $cHasSubs = !empty($cSubs); $cActive = ($currentCat === $cn && !$currentSub); $cPostCnt = $catPostCounts[$cn] ?? 0; if (in_array($cn, $navInlineCats)) continue; ?>
<div class="kn-pl-link<?= $cActive ? ' active' : '' ?><?= $cHasSubs ? ' has-psubs' : '' ?>" data-pcat="<?= htmlspecialchars($cn) ?>">
<div class="kpi"><i class="fas <?= $cIcon ?>"></i></div>
<span class="kpn"><?= htmlspecialchars($cn) ?></span>
<?php if ($cPostCnt > 0): ?><span class="kpc"><?= $cPostCnt ?></span><?php endif; ?>
<?php if ($cHasSubs): ?><span class="kn-sub-tog" data-ptog="<?= htmlspecialchars($cn) ?>" title="সাব-ক্যাটাগরি"><i class="fas fa-chevron-down"></i></span><?php endif; ?>
</div>
<?php if ($cHasSubs): ?>
<div class="kn-subs" data-psubs="<?= htmlspecialchars($cn) ?>">
<?php foreach ($cSubs as $ps): $psActive = ($currentSub === (int)$ps['id']); $psc = $subPostCounts[$ps['id']] ?? 0; ?>
<a href="?cat=<?= urlencode($cn) ?>&sub=<?= $ps['id'] ?>" class="kn-sub-link<?= $psActive ? ' active' : '' ?>"><span class="ksn"><?= htmlspecialchars($ps['name']) ?></span><?php if ($psc > 0): ?><span class="ksc"><?= $psc ?></span><?php endif; ?></a>
<?php endforeach; ?>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
</nav>

<div class="container" style="padding-top:16px;padding-bottom:16px">
<div class="kol-layout" id="kolLayout">

<aside class="kol-sidebar" id="kolSidebar">
<button class="sb-close" id="sbClose"><i class="fas fa-chevron-left"></i> সাইডবার লুকান</button>

<?php if (!empty($adSidebarTop)): ?>
<div class="sb-block" style="text-align:center;padding:10px;">
<?= $adSidebarTop ?>
</div>
<?php endif; ?>

<div class="sb-block">
<h3><i class="fas fa-layer-group"></i> বিভাগসমূহ</h3>
<ul class="sb-cat-list">
<?php foreach ($categories as $cat): $cn = $cat['name']; $cIcon = $navCatIcons[$cn] ?? 'fa-folder'; $cSubs = $catSubcategories[$cn] ?? []; $cHasSubs = !empty($cSubs); $cActive = ($currentCat === $cn && !$currentSub); $cCnt = $catPostCounts[$cn] ?? 0; ?>
<li class="sb-ci<?= $cHasSubs ? ' has-subs' : '' ?>">
<a href="?cat=<?= urlencode($cn) ?>" class="<?= $cActive ? 'active' : '' ?>"><i class="fas <?= $cIcon ?>"></i><span><?= htmlspecialchars($cn) ?></span><?php if ($cCnt > 0): ?><span class="sb-cc"><?= $cCnt ?></span><?php endif; ?></a>
<?php if ($cHasSubs): ?><span class="sb-st" data-stog="<?= htmlspecialchars($cn) ?>"><i class="fas fa-chevron-down"></i></span>
<ul class="sb-sl" data-ssubs="<?= htmlspecialchars($cn) ?>">
<?php foreach ($cSubs as $ss): $ssActive = ($currentSub === (int)$ss['id']); $ssc = $subPostCounts[$ss['id']] ?? 0; ?>
<li class="sb-si"><a href="?cat=<?= urlencode($cn) ?>&sub=<?= $ss['id'] ?>" class="<?= $ssActive ? ' active' : '' ?>"><i class="fas fa-circle"></i><?= htmlspecialchars($ss['name']) ?><?php if ($ssc > 0): ?><span class="ssc"><?= $ssc ?></span><?php endif; ?></a></li>
<?php endforeach; ?>
</ul><?php endif; ?>
</li>
<?php endforeach; ?>
</ul>
</div>

<?php if (!empty($popularPosts)): ?>
<div class="sb-block">
<h3><i class="fas fa-fire-alt"></i> জনপ্রিয় সংবাদ</h3>
<?php foreach ($popularPosts as $pp): ?>
<a href="?page=single&id=<?= $pp['id'] ?>" class="pop-item">
<img src="<?= newsImage($pp['image'], $placeholderImgSm) ?>" alt="" loading="lazy">
<div class="pi-info">
<div class="pi-t"><?= htmlspecialchars($pp['title']) ?></div>
<div class="pi-m"><?= timeAgo($pp['created_at']) ?></div>
</div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($adSidebarMiddle)): ?>
<div class="sb-block" style="text-align:center;padding:10px;">
<?= $adSidebarMiddle ?>
</div>
<?php endif; ?>

<?php if (!empty($popularTags)): ?>
<div class="sb-block">
<h3><i class="fas fa-tags"></i> জনপ্রিয় ট্যাগ</h3>
<div style="padding:10px 12px;display:flex;flex-wrap:wrap;gap:5px">
<?php foreach ($popularTags as $pt => $ptc): ?>
<a href="?q=<?= urlencode($pt) ?>" class="tag-pill"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($pt) ?></a>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>
</aside>

<main class="kol-main">

<?php if (!empty($adContentTop)) echo $adContentTop; ?>

<?php if ($page === 'videos' || $page === 'video'): ?>
<section class="video-section" style="padding: 40px 0; background: #f9f9f9; margin-top: 20px; border-radius: 8px;">
    <div class="container" style="max-width: 1200px; margin: 0 auto; padding: 0 15px;">
        <h2 style="text-align: center; margin-bottom: 30px; font-size: 28px; color: #1a1a1a;">Latest Videos</h2>
        <?php if (!empty($videos)): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 25px;">
                <?php foreach ($videos as $vid): 
                    $videoCode = $vid['code'] ?? '';
                    if (!empty($videoCode) && stripos($videoCode, '<iframe') === false && stripos($videoCode, '<video') === false && stripos($videoCode, '<embed') === false) {
                        $embedUrl = extractVideoEmbedUrl($videoCode);
                        if (!empty($embedUrl) && filter_var($embedUrl, FILTER_VALIDATE_URL)) {
                            $videoCode = '<iframe src="' . htmlspecialchars($embedUrl) . '" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>';
                        }
                    }
                ?>
                    <div style="background: #fff; border-radius: 10px; overflow: hidden; box-shadow:0 4px 15px rgba(0,0,0,0.1); transition: transform 0.3s ease;" onmouseover="this.style.transform='translateY(-5px)'" onmouseout="this.style.transform='translateY(0)'">
                        <div class="video-embed"><?= $videoCode ?></div>
                        <div style="padding: 15px 20px;">
                            <h3 style="margin: 0 0 8px 0; font-size: 18px; color: #1a1a1a; line-height: 1.4;"><?= htmlspecialchars($vid['title']) ?></h3>
                            <p style="margin: 0; font-size: 13px; color: #888;"><i class="far fa-calendar-alt"></i> <?= date("d M Y", strtotime($vid['created_at'])) ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div style="text-align: center; padding: 50px 20px; color: #777; background: #fff; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.05);">
                <i class="fas fa-video" style="font-size: 40px; margin-bottom: 15px; opacity: 0.3; display: block;"></i>
                <p style="font-size: 16px; margin: 0;">No videos available at the moment. Please check back later.</p>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php elseif ($page === 'single' && $singleNews): ?>
<article class="single-wrap">
<div style="margin-bottom:10px;">
<a href="?cat=<?= urlencode($singleNews['category']) ?>" class="cb"><?= htmlspecialchars($singleNews['category']) ?></a>
<?php if (!empty($singleNews['subcategory'])): ?>
<a href="?cat=<?= urlencode($singleNews['category']) ?>&sub=<?= (int)$singleNews['subcategory_id'] ?>" class="cb" style="background:var(--gold);color:#000;margin-left:5px;"><?= htmlspecialchars($singleNews['subcategory']) ?></a>
<?php endif; ?>
</div>
<h1><?= htmlspecialchars($singleNews['title']) ?></h1>
<div class="single-meta">
<span><i class="far fa-clock"></i> <?= formatDate($singleNews['created_at']) ?></span>
<span><i class="far fa-user"></i> সংবাদ সংকলন</span>
<?php if (!empty($singleNews['tags'])): ?>
<span><i class="fas fa-tags"></i> <?= htmlspecialchars($singleNews['tags']) ?></span>
<?php endif; ?>
</div>
<?php if (!empty($singleNews['image'])): ?>
<div class="single-img"><img src="<?= newsImage($singleNews['image'], $placeholderImg) ?>" alt="<?= htmlspecialchars($singleNews['title']) ?>"></div>
<?php endif; ?>
<?php
// ====== INSERT ADS AFTER PARAGRAPHS ======
 $content = $singleNews['content'];
 
 // 1. Replace plain YouTube URLs
 $content = preg_replace_callback(
    '~(?<!src=["\'])https?://(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/|v/)|youtu\.be/)([a-zA-Z0-9_-]{11})(?:\S*)~i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://www.youtube.com/embed/' . $matches[1] . '?rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

// 2. Clean up existing YouTube iframes to fix attributes and wrapper
 $content = preg_replace_callback(
    '/<iframe[^>]+src=["\'](https?:\/\/(?:www\.)?(?:youtube(?:-nocookie)?\.com\/(?:embed\/|v\/)|youtu\.be\/)([a-zA-Z0-9_-]{11})\S*)["\'][^>]*><\/iframe>/i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://www.youtube.com/embed/' . $matches[2] . '?rel=0&modestbranding=1" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

// 3. Replace plain Vimeo URLs
 $content = preg_replace_callback(
    '~(?<!src=["\'])https?://(?:www\.)?vimeo\.com/([0-9]+)(?:\S*)~i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://player.vimeo.com/video/' . $matches[1] . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

// 4. Clean up existing Vimeo iframes
 $content = preg_replace_callback(
    '/<iframe[^>]+src=["\'](https?:\/\/(?:www\.)?player\.vimeo\.com\/video\/([0-9]+)\S*)["\'][^>]*><\/iframe>/i',
    function($matches) {
        return '<div class="news-video-wrapper"><iframe src="https://player.vimeo.com/video/' . $matches[2] . '" frameborder="0" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen></iframe></div>';
    },
    $content
);

 $paragraphs = preg_split('/(<\/p>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE);
 $out = ''; $paraCount = 0;
foreach ($paragraphs as $i => $p) {
    $out .= $p;
    if ($p === '</p>') {
        $paraCount++;
        if ($paraCount === 1 && !empty($adAfter1st)) $out .= $adAfter1st;
        if ($paraCount === 2 && !empty($adAfter2nd)) $out .= $adAfter2nd;
        if ($paraCount === 3 && !empty($adAfter3rd)) $out .= $adAfter3rd;
        if ($paraCount === 5 && !empty($adContentMiddle)) $out .= $adContentMiddle;
    }
}
echo '<div class="single-content">' . $out . '</div>';
?>
<?php if (!empty($singleNews['tags'])): $sTags = array_filter(array_map('trim', explode(',', $singleNews['tags']))); if (!empty($sTags)): ?>
<div class="single-tags">
<span class="single-tags-label"><i class="fas fa-tags"></i> ট্যাগ:</span>
<?php foreach ($sTags as $st): ?>
<a href="?q=<?= urlencode($st) ?>" class="tag-pill"><i class="fas fa-hashtag"></i> <?= htmlspecialchars($st) ?></a>
<?php endforeach; ?>
</div>
<?php endif; endif; ?>
</article>

<?php if (!empty($adContentBottom)) echo $adContentBottom; ?>

<?php if (!empty($related)): ?>
<div class="rel-sec">
<h2 class="sec-title">সম্পর্কিত সংবাদ</h2>
<div class="rel-grid">
<?php foreach ($related as $ri): ?>
<a href="?page=single&id=<?= $ri['id'] ?>" class="nc">
<div class="nc-img"><img src="<?= newsImage($ri['image'], $placeholderImg) ?>" alt="" loading="lazy"></div>
<div class="nc-body">
<div class="nc-cat"><?= htmlspecialchars($ri['category']) ?><?php if (!empty($ri['subcategory'])): ?> <span style="color:#D4950A">/ <?= htmlspecialchars($ri['subcategory']) ?></span><?php endif; ?></div>
<h3><?= htmlspecialchars($ri['title']) ?></h3>
<div class="nc-meta"><span><i class="far fa-clock"></i> <?= timeAgo($ri['created_at']) ?></span></div>
</div>
</a>
<?php endforeach; ?>
</div>
</div>
<?php endif; ?>

<?php elseif ($searchQuery && empty($pagedGrid) && empty($displayFeatured)): ?>
<div class="no-res">
<i class="fas fa-search"></i>
<h3>"<?= htmlspecialchars($searchQuery) ?>" পাওয়া যায়নি</h3>
<p>অন্য কিছু দিয়ে খুঁজে দেখুন।</p>
<a href="?">হোমে ফিরুন</a>
</div>

<?php else: ?>

<?php if (!empty($displayFeatured) && !$searchQuery && !$currentSub && !$archYear && !$archMonth): ?>
<div class="feat-section">
<?php $fm = $displayFeatured[0]; $fsItems = array_slice($displayFeatured, 1); ?>
<div class="feat-grid">
<a href="?page=single&id=<?= $fm['id'] ?>" class="feat-main">
<img src="<?= newsImage($fm['image'], $placeholderImg) ?>" alt="" loading="lazy">
<div class="ov">
<span class="cb"><?= htmlspecialchars($fm['category']) ?><?php if (!empty($fm['subcategory'])): ?> / <?= htmlspecialchars($fm['subcategory']) ?><?php endif; ?></span>
<h2><?= htmlspecialchars($fm['title']) ?></h2>
<?php if (!empty($fm['excerpt'])): ?><p><?= htmlspecialchars($fm['excerpt']) ?></p><?php endif; ?>
</div>
</a>
<?php if (!empty($fsItems)): ?>
<div class="feat-side">
<?php foreach ($fsItems as $fsi): ?>
<a href="?page=single&id=<?= $fsi['id'] ?>" class="feat-si">
<img src="<?= newsImage($fsi['image'], $placeholderImgSm) ?>" alt="" loading="lazy">
<div class="fi">
<div class="fcs"><?= htmlspecialchars($fsi['category']) ?><?php if (!empty($fsi['subcategory'])): ?> / <?= htmlspecialchars($fsi['subcategory']) ?><?php endif; ?></div>
<h3><?= htmlspecialchars($fsi['title']) ?></h3>
<div class="fdt"><i class="far fa-clock"></i> <?= timeAgo($fsi['created_at']) ?></div>
</div>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php if (!$searchQuery && !$currentSub && !$currentCat && !$archYear && !$archMonth && $page === 'home' && !empty($homeVideos) && $showHomeVideos): ?>
<div class="vid-sec">
<h2 class="sec-title"><span style="background:var(--red);color:#fff;width:32px;height:32px;border-radius:7px;display:inline-flex;align-items:center;justify-content:center;font-size:14px;margin-right:8px"><i class="fas fa-play" style="margin-left:2px"></i></span> ভিডিও সংবাদ</h2>
<div class="vid-grid">
<?php
 $vidMain = array_shift($homeVideos);
 $vidEmbed = '';
if (!empty($vidMain['code'])) { $vidEmbed = extractVideoEmbedUrl($vidMain['code']); } elseif (!empty($vidMain['video_url'])) { $vidEmbed = extractVideoEmbedUrl($vidMain['video_url']); }
 $vidPoster = !empty($vidMain['thumbnail']) ? newsImage($vidMain['thumbnail'], '') : '';
if (empty($vidPoster)) { $vidPoster = videoThumbUrl($vidEmbed); }
if (empty($vidPoster)) $vidPoster = $placeholderImg;
 $vidCat = $vidMain['category_name'] ?? 'ভিডিও';
?>
<?php if(!empty($vidEmbed)): ?>
<div class="vid-main" data-embed="<?= htmlspecialchars($vidEmbed) ?>">
<img class="vid-poster" src="<?= htmlspecialchars($vidPoster) ?>" alt="<?= htmlspecialchars($vidMain['title'] ?? '') ?>">
<span class="vid-play"><i class="fas fa-play" style="margin-left:3px"></i></span>
<div class="vid-info">
<span class="cb"><?= htmlspecialchars($vidCat) ?></span>
<h3><?= htmlspecialchars($vidMain['title'] ?? '') ?></h3>
<div class="vdt"><i class="far fa-clock"></i> <?= timeAgo($vidMain['created_at'] ?? 'now') ?></div>
</div>
</div>
<?php else: ?>
<div style="grid-column: 1/-1; text-align:center; padding: 30px; color:var(--muted);">
    <i class="fas fa-video-slash" style="font-size:32px; margin-bottom:10px; opacity:0.3; display:block;"></i>
    <p>No videos available at the moment.</p>
</div>
<?php endif; ?>

<?php if (!empty($homeVideos)): ?>
<div class="vid-side">
<?php foreach ($homeVideos as $v):
 $vEmbed = '';
if (!empty($v['code'])) { $vEmbed = extractVideoEmbedUrl($v['code']); } elseif (!empty($v['video_url'])) { $vEmbed = extractVideoEmbedUrl($v['video_url']); }
 $vThumb = !empty($v['thumbnail']) ? newsImage($v['thumbnail'], '') : '';
if (empty($vThumb)) { $vThumb = videoThumbUrl($vEmbed); }
if (empty($vThumb)) $vThumb = $placeholderImgSm;
 $vCat = $v['category_name'] ?? 'ভিডিও';
?>
<?php if(!empty($vEmbed)): ?>
<div class="vid-card" data-embed="<?= htmlspecialchars($vEmbed) ?>">
<div class="vid-thumb">
<img src="<?= htmlspecialchars($vThumb) ?>" alt="" loading="lazy">
<span class="vid-sm-play"><i class="fas fa-play" style="margin-left:2px"></i></span>
</div>
<div class="vc-info">
<?php if ($vCat !== 'ভিডিও'): ?><div class="vc-cat"><?= htmlspecialchars($vCat) ?></div><?php endif; ?>
<h4><?= htmlspecialchars($v['title'] ?? '') ?></h4>
<div class="vc-time"><i class="far fa-clock"></i> <?= timeAgo($v['created_at'] ?? 'now') ?></div>
</div>
</div>
<?php endif; ?>
<?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>
<?php endif; ?>

<?php 
 $showStandardNewsGrid = !empty($pagedGrid);
if ($page === 'home' && !$searchQuery && !$currentSub && !$currentCat && !$showLatestNews && !$archYear && !$archMonth) {
    $showStandardNewsGrid = false;
}
?>

<?php if ($showStandardNewsGrid): ?>
<div class="news-sec">
    <?php if ($searchQuery): ?>
    <h2 class="sec-title">সার্চ: "<?= htmlspecialchars($searchQuery) ?>"</h2>
    <?php elseif ($archYear && $archMonth): ?>
    <h2 class="sec-title">আর্কাইভ: <?= htmlspecialchars($bnMonths[$archMonth] . ' ' . $archYear) ?></h2>
    <?php elseif ($currentSub && $currentSubData): ?>
    <h2 class="sec-title"><?= htmlspecialchars($currentSubData['name']) ?></h2>
    <?php elseif ($currentCat): ?>
    <h2 class="sec-title"><?= htmlspecialchars($currentCat) ?></h2>
    <?php else: ?>
    <h2 class="sec-title">সর্বশেষ সংবাদ</h2>
    <?php endif; ?>
    <div class="news-grid">
        <?php foreach ($pagedGrid as $ni): ?>
        <a href="?page=single&id=<?= $ni['id'] ?>" class="nc">
            <div class="nc-img"><img src="<?= newsImage($ni['image'], $placeholderImg) ?>" alt="" loading="lazy"></div>
            <div class="nc-body">
                <div class="nc-cat"><?= htmlspecialchars($ni['category']) ?><?php if (!empty($ni['subcategory'])): ?> <span style="color:#D4950A">/ <?= htmlspecialchars($ni['subcategory']) ?></span><?php endif; ?></div>
                <h3><?= htmlspecialchars($ni['title']) ?></h3>
                <?php if (!empty($ni['excerpt'])): ?><p><?= htmlspecialchars($ni['excerpt']) ?></p><?php endif; ?>
                <div class="nc-meta">
                    <span><i class="far fa-clock"></i> <?= timeAgo($ni['created_at']) ?></span>
                    <span><i class="far fa-eye"></i> <?= htmlspecialchars($ni['views'] ?? '0') ?></span>
                </div>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?= $paginationHTML ?>
</div>
<?php if (!empty($adContentMiddle)) echo $adContentMiddle; ?>
<?php elseif (empty($displayFeatured) || $searchQuery || $currentSub || $archYear || $archMonth): ?>
<div class="no-res">
    <i class="fas fa-newspaper"></i>
    <h3>কোনো সংবাদ পাওয়া যায়নি</h3>
    <a href="?">হোমে ফিরুন</a>
</div>
<?php endif; ?>

<?php if (!$searchQuery && !$currentSub && !$currentCat && !$archYear && !$archMonth && $page === 'home'): ?>
    <?php foreach ($homePanelData as $hpName => $hpData): $hpInfo = $hpData['info']; $hpItems = $hpData['items']; ?>
    <div class="cat-panel-sec">
        <div class="cat-panel-head">
            <h2 class="cat-panel-title" style="color:<?= $hpInfo['color'] ?>"><i class="fas <?= $hpInfo['icon'] ?>"></i> <?= htmlspecialchars($hpName) ?></h2>
            <a href="?cat=<?= urlencode($hpName) ?>" class="cat-panel-all">সব দেখুন <i class="fas fa-arrow-right"></i></a>
        </div>
        <div class="cat-panel-row">
            <?php if (empty($hpItems)): ?>
            <p style="grid-column:1/-1;text-align:center;color:var(--muted);padding:20px;">এই বিভাগে কোনো সংবাদ নেই।</p>
            <?php else: ?>
            <?php foreach ($hpItems as $hpi): ?>
            <a href="?page=single&id=<?= $hpi['id'] ?>" class="cat-panel-card">
                <div class="cpc-img"><img src="<?= newsImage($hpi['image'], $placeholderImg) ?>" alt="" loading="lazy"></div>
                <div class="cpc-body">
                    <h3><?= htmlspecialchars($hpi['title']) ?></h3>
                    <div class="cpc-time"><i class="far fa-clock"></i> <?= timeAgo($hpi['created_at']) ?> <span style="margin-left:5px;color:var(--red)"><?= htmlspecialchars($hpi['category']) ?><?php if (!empty($hpi['subcategory'])): ?> / <?= htmlspecialchars($hpi['subcategory']) ?><?php endif; ?></span></div>
                </div>
            </a>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
<?php endif; ?>

<?php endif; ?>

</main>
</div>

<div class="ad-bottom-sec">
    <div class="ad-block">
        <div class="ad-label">- Advertisement -</div>
        <div class="ad-content">
            <?php if (!empty($adVals['ad_bottom_1_embed'])): ?>
                <?= $adVals['ad_bottom_1_embed'] ?>
            <?php else: ?>
                <a href="#" target="_blank" rel="noopener noreferrer">
                    <img src="https://via.placeholder.com/728x90?text=Bottom+Advertisement+1" alt="Advertisement">
                </a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="ad-block">
        <div class="ad-label">- Sponsored -</div>
        <div class="ad-content">
            <?php if (!empty($adVals['ad_bottom_2_embed'])): ?>
                <?= $adVals['ad_bottom_2_embed'] ?>
            <?php else: ?>
                <a href="#" target="_blank" rel="noopener noreferrer">
                    <img src="https://via.placeholder.com/300x250?text=Bottom+Advertisement+2" alt="Advertisement">
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

</div>

<?php if (!empty($nseTrackHTML)): ?>
<div class="nse-bar">
<div class="nse-lbl"><i class="fas fa-chart-line"></i> NSE</div>
<div class="nse-scroll">
<div class="nse-track"><?= $nseTrackHTML ?></div>
</div>
</div>
<?php endif; ?>

<?php if (!empty($adFooterBanner)) echo $adFooterBanner; ?>

<footer class="kol-footer">
<div class="container">
<div class="ft-grid">
<div class="ft-about">
<h3>সংবাদ সংকলন</h3>
<p>সত্য ও বিশ্বস্ত সংবাদ প্রতিদিন। বাংলায় সর্বশেষ খবর, বিশ্লেষণ ও মতামত পড়ুন সংবাদ সংকলনে।</p>
<div class="ft-social">
<a href="#" class="sf" aria-label="Facebook"><i class="fab fa-facebook-f"></i></a>
<a href="#" class="sx" aria-label="Twitter"><i class="fab fa-x-twitter"></i></a>
<a href="#" class="sy" aria-label="YouTube"><i class="fab fa-youtube"></i></a>
<a href="#" class="si" aria-label="Instagram"><i class="fab fa-instagram"></i></a>
<a href="#" class="st" aria-label="Telegram"><i class="fab fa-telegram-plane"></i></a>
</div>
</div>
<div>
<h3>বিভাগসমূহ</h3>
<ul class="ft-links">
<?php foreach (array_slice($categories, 0, 6) as $fc): ?>
<li><a href="?cat=<?= urlencode($fc['name']) ?>"><?= htmlspecialchars($fc['name']) ?></a></li>
<?php endforeach; ?>
</ul>
</div>
<div>
<h3>দ্রুত লিংক</h3>
<ul class="ft-links">
<li><a href="?">হোম</a></li>
<li><a href="?cat=জাতীয়">জাতীয়</a></li>
<li><a href="?cat=আন্তর্জাতিক">আন্তর্জাতিক</a></li>
<li><a href="?cat=খেলাধুলা">খেলাধুলা</a></li>
<li><a href="?cat=বিনোদন">বিনোদন</a></li>
<li><a href="?cat=প্রযুক্তি">প্রযুক্তি</a></li>
</ul>
</div>
<div class="ft-contact">
<h4><i class="fas fa-address-card"></i> যোগাযোগ</h4>
<ul class="ft-contact-list">
<?php if (!empty($contactVals['contact_address'])): ?>
<li class="ft-addr-item"><i class="fas fa-map-marker-alt"></i><span><?= htmlspecialchars($contactVals['contact_address']) ?></span></li>
<?php endif; ?>
<?php if (!empty($contactVals['contact_email_1'])): ?>
<li class="ft-addr-item"><i class="fas fa-envelope"></i><a href="mailto:<?= htmlspecialchars($contactVals['contact_email_1']) ?>"><?= htmlspecialchars($contactVals['contact_email_1']) ?></a></li>
<?php endif; ?>
<?php if (!empty($contactVals['contact_phone'])): ?>
<li class="ft-addr-item"><i class="fas fa-phone"></i><a href="tel:<?= htmlspecialchars($contactVals['contact_phone']) ?>"><?= htmlspecialchars($contactVals['contact_phone']) ?></a></li>
<?php endif; ?>
<?php if (!empty($contactVals['contact_whatsapp'])): ?>
<li class="ft-addr-item"><i class="fab fa-whatsapp"></i><a href="https://wa.me/<?= preg_replace('/[^0-9+]/', '', $contactVals['contact_whatsapp']) ?>" target="_blank" rel="noopener"><?= htmlspecialchars($contactVals['contact_whatsapp']) ?></a></li>
<?php endif; ?>
<?php if (!empty($contactVals['contact_office_hours'])): ?>
<li class="ft-addr-item"><i class="far fa-clock"></i><span><?= htmlspecialchars($contactVals['contact_office_hours']) ?></span></li>
<?php endif; ?>
</ul>
</div>
</div>
<div class="ft-bottom">
&copy; <?= date('Y') ?> <a href="?">সংবাদ সংকলন</a>। সর্বস্বত্ব সংরক্ষিত।
</div>
</div>
</footer>

<button class="sb-toggle" id="sbToggle" aria-label="সাইডবার টগল"><i class="fas fa-bars"></i></button>

<!-- Permission Popup (Step 1) -->
<div id="allowNewsPopup" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.7);z-index:100000;align-items:center;justify-content:center;">
    <div style="background:#fff;padding:40px 30px;border-radius:12px;text-align:center;max-width:380px;width:90%;box-shadow:0 10px 30px rgba(0,0,0,.3);">
        <div style="width:70px;height:70px;background:#FFF0F0;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;">
            <i class="fas fa-bell" style="font-size:32px;color:var(--red);"></i>
        </div>
        <h3 style="font-family:'Noto Serif Bengali',serif;margin-bottom:15px;color:#333;font-size:22px;font-weight:700;">নোটিফিকেশন চালু করুন</h3>
        <p style="font-size:16px;color:#333;margin-bottom:25px;font-weight:600;">আপনি কি সংবাদ গুলোর নোটিফিকেশন পেতে চান?</p>
        <div style="display:flex;justify-content:center;gap:15px;">
            <button id="btnAllowYes" style="background:var(--red);color:#fff;border:none;padding:10px 40px;border-radius:5px;cursor:pointer;font-weight:bold;font-size:16px;font-family:'Hind Siliguri',sans-serif;">হ্যাঁ</button>
            <button id="btnAllowNo" style="background:#fff;color:var(--red);border:1px solid var(--red);padding:10px 40px;border-radius:5px;cursor:pointer;font-weight:bold;font-size:16px;font-family:'Hind Siliguri',sans-serif;">না</button>
        </div>
    </div>
</div>

<!-- HTML Fallback Popup for New News -->
<div id="newNewsPopup" style="display:none;position:fixed;bottom:20px;right:20px;width:350px;max-width:90%;background:#fff;border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,.3);z-index:100000;overflow:hidden;border-top:4px solid var(--red);">
    <div style="padding:15px 20px;display:flex;align-items:center;gap:15px;">
        <div style="width:40px;height:40px;background:rgba(183,28,28,.1);border-radius:50%;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
            <i class="fas fa-bell" style="color:var(--red);font-size:18px;"></i>
        </div>
        <div style="flex:1;">
            <h4 style="font-family:'Noto Serif Bengali',serif;font-size:14px;font-weight:700;margin:0 0 4px 0;color:#333;">নতুন সংবাদ প্রকাশিত!</h4>
            <p id="newNewsTitleFallback" style="font-size:13px;color:#666;margin:0;line-height:1.4;"></p>
        </div>
        <button id="btnCloseNewNews" style="background:none;border:none;color:#999;cursor:pointer;font-size:16px;padding:5px;">&times;</button>
    </div>
    <a id="newNewsLinkFallback" href="#" style="display:block;text-align:center;padding:10px;background:var(--red);color:#fff;text-decoration:none;font-weight:bold;font-size:13px;font-family:'Hind Siliguri',sans-serif;">এখনই পড়ুন</a>
</div>

<?php if (!empty($adPopup)): ?>
<div id="popupAdOverlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.6);z-index:99998;align-items:center;justify-content:center;">
<div style="position:relative;max-width:400px;width:90%;background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 10px 40px rgba(0,0,0,.3);">
<button onclick="document.getElementById('popupAdOverlay').style.display='none'" style="position:absolute;top:8px;right:10px;z-index:10;background:rgba(0,0,0,.5);color:#fff;border:none;width:28px;height:28px;border-radius:50%;cursor:pointer;font-size:16px;">&times;</button>
<?= $adPopup ?>
</div>
</div>
<script>
setTimeout(function(){var o=document.getElementById('popupAdOverlay');if(o)o.style.display='flex';},5000);
document.addEventListener('click',function(e){if(e.target.id==='popupAdOverlay')e.target.style.display='none';});
</script>
<?php endif; ?>

<script>
(function(){
function updateClock(){
    var now=new Date(),h=String(now.getHours()).padStart(2,'0'),m=String(now.getMinutes()).padStart(2,'0'),s=String(now.getSeconds()).padStart(2,'0');
    var el=document.getElementById('liveClock');if(el)el.textContent=h+':'+m+':'+s;
}
updateClock();setInterval(updateClock,1000);

var sbToggle=document.getElementById('sbToggle'),kolSidebar=document.getElementById('kolSidebar'),kolLayout=document.getElementById('kolLayout'),sbClose=document.getElementById('sbClose');

function toggleSidebar(){
    kolLayout.classList.toggle('sb-hidden');
    var icon=sbToggle.querySelector('i');
    if(kolLayout.classList.contains('sb-hidden')){icon.className='fas fa-bars';sbToggle.title='সাইডবার দেখান';}
    else{icon.className='fas fa-times';sbToggle.title='সাইডবার লুকান';}
}
if(sbToggle)sbToggle.addEventListener('click',toggleSidebar);
if(sbClose)sbClose.addEventListener('click',function(){if(!kolLayout.classList.contains('sb-hidden'))toggleSidebar();});
if(sbToggle){if(kolLayout.classList.contains('sb-hidden')){sbToggle.querySelector('i').className='fas fa-bars';}else{sbToggle.querySelector('i').className='fas fa-times';}}

document.querySelectorAll('.sb-st').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var cat=this.getAttribute('data-stog');var sl=document.querySelector('.sb-sl[data-ssubs="'+cat+'"]');if(sl)sl.classList.toggle('open');this.classList.toggle('open');});});

function closeAllInlineDrops(){document.querySelectorAll('.kn-inline.open').forEach(function(el){el.classList.remove('open');var d=el.querySelector('.kn-drop');if(d){d.style.top='';d.style.left='';}});}
document.querySelectorAll('.kn-inline .kn-arrow').forEach(function(arrow){arrow.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var inline=this.closest('.kn-inline'),drop=inline.querySelector('.kn-drop'),wasOpen=inline.classList.contains('open');closeAllInlineDrops();if(!wasOpen){inline.classList.add('open');var r=inline.getBoundingClientRect();drop.style.top=r.bottom+'px';drop.style.left=r.left+'px';}});});
document.addEventListener('click',function(e){if(!e.target.closest('.kn-inline'))closeAllInlineDrops();});
window.addEventListener('scroll',function(){closeAllInlineDrops();},{passive:true});

var moreBtn=document.getElementById('knMoreBtn'),morePanel=document.getElementById('knPanel');
if(moreBtn&&morePanel){
    moreBtn.addEventListener('click',function(e){e.stopPropagation();morePanel.classList.toggle('open');moreBtn.classList.toggle('is-active');});
    document.addEventListener('click',function(e){if(!morePanel.contains(e.target)&&e.target!==moreBtn&&!moreBtn.contains(e.target)){morePanel.classList.remove('open');moreBtn.classList.remove('is-active');}});
}

document.querySelectorAll('.kn-pl-link.has-psubs').forEach(function(row){row.addEventListener('click',function(e){if(e.target.closest('.kn-sub-link'))return;var cat=this.getAttribute('data-pcat');var sl=document.querySelector('.kn-subs[data-psubs="'+cat+'"]');var tb=this.querySelector('.kn-sub-tog');if(sl)sl.classList.toggle('open');if(tb)tb.classList.toggle('open');});});
document.querySelectorAll('.kn-sub-tog').forEach(function(btn){btn.addEventListener('click',function(e){e.preventDefault();e.stopPropagation();var cat=this.getAttribute('data-ptog');var sl=document.querySelector('.kn-subs[data-psubs="'+cat+'"]');if(sl)sl.classList.toggle('open');this.classList.toggle('open');});});

document.addEventListener('click',function(e){
    var vm=e.target.closest('.vid-main,.vid-card');
    if(!vm)return;
    var emb=vm.getAttribute('data-embed');
    if(!emb)return;
    var w=vm.offsetWidth||400;
    var h=vm.offsetHeight||225;
    var ifr=document.createElement('iframe');
    ifr.src=emb;ifr.width=w;ifr.height=h;
    ifr.setAttribute('frameborder','0');ifr.setAttribute('allowfullscreen','1');
    ifr.setAttribute('allow','accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture');
    ifr.style.cssText='width:100%;height:100%;position:absolute;top:0;left:0;border:0;z-index:10';
    vm.innerHTML='';vm.appendChild(ifr);
});

if(window.innerWidth<=1024){document.querySelectorAll('.kol-sidebar a').forEach(function(link){link.addEventListener('click',function(){kolSidebar.classList.remove('open');kolLayout.classList.remove('sb-hidden');});});}

// ====== WEBSOCKET NOTIFICATION LOGIC ======
var latestNewsId = <?= $latestNewsId ?>;
var allowNewsPopup = document.getElementById('allowNewsPopup');
var newNewsPopup = document.getElementById('newNewsPopup');
var newNewsTitleFallback = document.getElementById('newNewsTitleFallback');
var newNewsLinkFallback = document.getElementById('newNewsLinkFallback');
var btnCloseNewNews = document.getElementById('btnCloseNewNews');
var currentNewNewsId = 0;
var ws;

// Register Service Worker
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('sw.js').then(function(registration) {
            console.log('ServiceWorker registration successful');
        }).catch(function(err) {
            console.log('ServiceWorker registration failed: ', err);
        });
    });
}

// Function to display Native OS Notification
function showSystemNotification(title, body, url) {
    if (!("Notification" in window)) return false;
    if (Notification.permission === "granted") {
        var options = {
            body: body,
            icon: 'https://via.placeholder.com/150/B71C1C/FFFFFF?text=News',
            tag: 'new-news-' + url, 
            data: { url: url }
        };
        
        try {
            if ('serviceWorker' in navigator) {
                navigator.serviceWorker.ready.then(function(registration) {
                    registration.showNotification(title, options);
                });
            } else {
                var notification = new Notification(title, options);
                notification.onclick = function() {
                    window.focus();
                    window.location.href = url;
                    notification.close();
                };
            }
            return true;
        } catch(e) {
            console.error("Notification error:", e);
            return false;
        }
    }
    return false;
}

// Function to show HTML Popup Fallback
function showHtmlPopup(title, url) {
    if (newNewsPopup && newNewsPopup.style.display !== 'block') {
        if (newNewsTitleFallback) newNewsTitleFallback.textContent = title;
        if (newNewsLinkFallback) newNewsLinkFallback.href = url;
        newNewsPopup.style.display = 'block';
        
        setTimeout(function() {
            if (newNewsPopup) newNewsPopup.style.display = 'none';
        }, 10000);
    }
}

if (btnCloseNewNews) {
    btnCloseNewNews.addEventListener('click', function() {
        if (newNewsPopup) newNewsPopup.style.display = 'none';
    });
}

// WebSocket Connection Function
function connectWebSocket() {
    var wsUrl = (window.location.protocol === "https:" ? "wss://" : "ws://") + window.location.hostname + ":8080";
    ws = new WebSocket(wsUrl);

    ws.onopen = function() {
        console.log("WebSocket Connected!");
        ws.send(JSON.stringify({ action: 'init', last_id: latestNewsId }));
    };

    ws.onmessage = function(event) {
        try {
            var data = JSON.parse(event.data);
            if (data.status === 'new') {
                currentNewNewsId = data.id;
                var url = '?page=single&id=' + currentNewNewsId;
                
                var systemShown = showSystemNotification('নতুন সংবাদ প্রকাশিত হয়েছে!', data.title, url);

                if (!systemShown) {
                    showHtmlPopup(data.title, url);
                }
                
                latestNewsId = currentNewNewsId;
            }
        } catch (e) {
            console.error("Error parsing WebSocket data:", e);
        }
    };

    ws.onclose = function() {
        console.log("WebSocket Disconnected. Reconnecting in 5 seconds...");
        setTimeout(connectWebSocket, 5000);
    };
    
    ws.onerror = function(error) {
        console.error("WebSocket Error:", error);
        ws.close();
    };
}

function startNewsCheck() {
    if (!ws || ws.readyState === WebSocket.CLOSED) {
        connectWebSocket();
    }
}

var newsAllowed = localStorage.getItem('news_popup_allowed');

if (newsAllowed === 'granted') {
    startNewsCheck();
} else if (newsAllowed === null || newsAllowed === undefined) {
    setTimeout(function() {
        if (allowNewsPopup) allowNewsPopup.style.display = 'flex';
    }, 2000);
}

var btnAllowYes = document.getElementById('btnAllowYes');
if (btnAllowYes) {
    btnAllowYes.addEventListener('click', function() {
        if (allowNewsPopup) allowNewsPopup.style.display = 'none';

        if (!("Notification" in window)) {
            localStorage.setItem('news_popup_allowed', 'granted');
            startNewsCheck();
            return;
        }

        Notification.requestPermission().then(function (permission) {
            if (permission === "granted") {
                localStorage.setItem('news_popup_allowed', 'granted');
                startNewsCheck();
                showSystemNotification('বিজ্ঞপ্তি সফল হয়েছে!', 'আপনি এখন নতুন সংবাদের বিজ্ঞপ্তি পাবেন।', '?page=home');
            } else {
                localStorage.setItem('news_popup_allowed', 'granted');
                startNewsCheck();
            }
        });
    });
}

var btnAllowNo = document.getElementById('btnAllowNo');
if (btnAllowNo) {
    btnAllowNo.addEventListener('click', function() {
        localStorage.setItem('news_popup_allowed', 'denied');
        if (allowNewsPopup) allowNewsPopup.style.display = 'none';
    });
}
})();
</script>
</body>
</html>