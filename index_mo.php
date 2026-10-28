<?php
// =====================================================================
//  index_mo.php — Sangbad Sankalan Android App (v2.0 — New UI)
//  ★ REFERENCE IMAGE LAYOUT — বাংলা খবর style
//    • Compact top header (logo + bell + search + hamburger)
//    • Horizontal scrollable category tabs
//    • Large featured news + byline
//    • 2-column news grid (compact cards)
//  ★ COLOR UNCHANGED — Original red theme (#B71C1C) preserved
//  ★ SELF-CONTAINED — মূল index.php সম্পূর্ণ অপরিবর্তিত
//  ★ BILINGUAL — Bengali ও English দুটোতেই কাজ করে
// =====================================================================

// ── Step 1: Capture index.php output ──────────────────────────────────
ob_start();
include __DIR__ . '/index.php';
$html = ob_get_clean();

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

// Page context
$page    = $_GET['page'] ?? 'home';
$currentCat  = $_GET['cat'] ?? '';
$searchQuery = trim($_GET['q'] ?? '');

// Section title
$sectionTitle = $L['latest'];
if ($searchQuery) $sectionTitle = $isEnglish ? 'Search: ' . $searchQuery : 'সার্চ: ' . $searchQuery;
elseif ($currentCat) $sectionTitle = $currentCat;
elseif ($page === 'home') $sectionTitle = $L['latest'];

// ── Step 4: Build new UI ─────────────────────────────────────────────
$langAttr = $isEnglish ? 'en' : 'bn';
$isSinglePage = ($page === 'single');

// ★ Language switch button — toggles Bengali ↔ English
//    If currently English → switch to Bengali (বাং)
//    If currently Bengali → switch to English (EN)
$langSwitch      = $isEnglish ? 'bn' : 'en';
$langSwitchLabel = $isEnglish ? 'বাং' : 'EN';

// Build full URL for language switch — preserves current page/cat/search params
// ★ URL format: index_mo.php?lang=<target>&page=...&cat=...&q=...
$langSwitchUrl = '?lang=' . $langSwitch;
if ($page !== 'home' && !empty($page)) $langSwitchUrl .= '&page=' . urlencode($page);
if (!empty($currentCat))   $langSwitchUrl .= '&cat=' . urlencode($currentCat);
if (!empty($searchQuery))   $langSwitchUrl .= '&q=' . urlencode($searchQuery);
// Preserve single news id if present
if (isset($_GET['id']) && !empty($_GET['id'])) {
    $langSwitchUrl .= '&id=' . urlencode($_GET['id']);
}
// Preserve sub/category etc
if (isset($_GET['sub']) && !empty($_GET['sub'])) {
    $langSwitchUrl .= '&sub=' . urlencode($_GET['sub']);
}
if (isset($_GET['y']) && !empty($_GET['y'])) {
    $langSwitchUrl .= '&y=' . urlencode($_GET['y']);
}
if (isset($_GET['m']) && !empty($_GET['m'])) {
    $langSwitchUrl .= '&m=' . urlencode($_GET['m']);
}
// $langSwitchUrl is now like "?lang=bn&page=single&id=5" — use directly as href
$langSwitchHref = $langSwitchUrl;

if ($isSinglePage) {
    // ★ Single article page — keep original index.php HTML (no redesign needed)
    // Just inject minimal app-only CSS (mobile viewport + green status bar styling
    // is unnecessary — original red theme is kept)
    $html = preg_replace('/<body(\s[^>]*)?>/s', '<body$1 class="app-view">', $html, 1);

    // ★ Fix language switch links in single page — they must preserve page/cat/id
    // Original index.php's lang dropdown uses "?lang=bn"/"?lang=en" which loses
    // the current article id. We rewrite to keep current params.
    $preserveParams = '';
    if (!empty($page))           $preserveParams .= '&page=' . urlencode($page);
    if (isset($_GET['id']))      $preserveParams .= '&id=' . urlencode($_GET['id']);
    if (!empty($currentCat))    $preserveParams .= '&cat=' . urlencode($currentCat);
    if (isset($_GET['sub']))    $preserveParams .= '&sub=' . urlencode($_GET['sub']);

    // Replace bare "?lang=bn" and "?lang=en" links with preserved versions
    if ($preserveParams) {
        $html = preg_replace(
            '#href="\?lang=(bn|en)"#u',
            'href="?lang=$1' . $preserveParams . '"',
            $html
        );
    }

    $singleCSS = <<<CSS

/* App-only styles for single article page (v2.0) */
body.app-view .kol-topbar { padding: 4px 0; }
body.app-view .kn-search input { width: 80px; }
body.app-view .kn-search input:focus { width: 120px; }
@media (max-width: 768px) {
    body.app-view .kn-search input { width: 70px; }
    body.app-view .kn-search input:focus { width: 100px; }
}

CSS;
    $html = preg_replace('/<\/style>(?!.*<\/style>)/s', $singleCSS . "</style>", $html, 1);

    // Inject the same sidebar fix JS for single page
    $singleJS = <<<JS
<script>
(function() {
    'use strict';
    var sbToggle = document.getElementById('sbToggle');
    var kolSidebar = document.getElementById('kolSidebar');
    if (sbToggle && kolSidebar) {
        sbToggle.addEventListener('click', function() {
            kolSidebar.classList.toggle('open');
            var isOpen = kolSidebar.classList.contains('open');
            document.body.classList.toggle('sidebar-open', isOpen);
        }, true);
    }
    document.addEventListener('click', function(e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (!kolSidebar || kolSidebar.contains(e.target)) return;
        if (e.target === sbToggle || (sbToggle && sbToggle.contains(e.target))) return;
        kolSidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    });
})();
</script>
JS;
    $html = str_replace('</body>', $singleJS . "\n</body>", $html);
    echo $html;
    return;
}

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
    position: sticky;
    top: 0;
    z-index: 100;
    box-shadow: 0 2px 8px rgba(0,0,0,.15);
}
.app-header .brand {
    display: flex;
    align-items: center;
    gap: 8px;
    font-family: 'Noto Serif Bengali', serif;
    font-size: 17px;
    font-weight: 700;
    letter-spacing: .3px;
}
.app-header .brand i {
    font-size: 18px;
    color: var(--gold);
}
.app-header .actions {
    display: flex;
    align-items: center;
    gap: 4px;
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

/* "More" tab — distinct style to indicate overflow */
.cat-tab.more-tab {
    color: var(--red);
    background: rgba(183,28,28,.06);
    font-weight: 700;
    border-left: 1px solid var(--border);
    margin-left: auto;
}
.cat-tab.more-tab:hover { background: rgba(183,28,28,.12); }
.cat-tab.more-tab .more-count {
    background: var(--red);
    color: #fff;
    font-size: 10px;
    padding: 1px 6px;
    border-radius: 8px;
    margin-left: 4px;
    font-weight: 700;
}

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

/* ═══════ POPULAR NEWS (horizontal scroll) ═══════ */
.popular-section { margin-bottom: 16px; }
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

/* ═══════ TAGS ═══════ */
.tags-wrap {
    padding: 0 14px 16px;
    display: flex;
    flex-wrap: wrap;
    gap: 6px;
}
.tag-pill {
    background: rgba(183,28,28,.08);
    color: var(--red);
    padding: 5px 12px;
    border-radius: 16px;
    font-size: 11px;
    font-weight: 600;
    transition: background .2s, color .2s;
}
.tag-pill:hover { background: var(--red); color: #fff; }
.tag-pill i { margin-right: 3px; font-size: 9px; }

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

/* ═══════ CATEGORY PANEL (3-dot menu popup) ═══════ */
.cat-panel {
    display: none;
    position: fixed;
    top: 58px;
    right: 8px;
    width: 280px;
    max-height: 70vh;
    overflow-y: auto;
    background: var(--card-bg);
    border-radius: 8px;
    box-shadow: 0 8px 30px rgba(0,0,0,.25);
    z-index: 200;
    padding: 8px 0;
}
.cat-panel.open { display: block; }
.cat-panel .cp-header {
    padding: 10px 14px 6px;
    font-family: 'Noto Serif Bengali', serif;
    font-size: 13px;
    font-weight: 700;
    color: var(--red);
    border-bottom: 1px solid var(--border);
    margin-bottom: 4px;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.cat-panel .cp-header .cp-count {
    background: var(--red);
    color: #fff;
    font-size: 10px;
    font-weight: 700;
    padding: 1px 8px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
}
.cat-panel .cp-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 14px;
    font-size: 13px;
    color: var(--text);
    transition: background .15s;
}
.cat-panel .cp-item:hover { background: rgba(183,28,28,.06); color: var(--red); }
.cat-panel .cp-item i {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(183,28,28,.08);
    color: var(--red);
    border-radius: 5px;
    font-size: 11px;
}

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
        <span>{$L['appName']}</span>
    </a>
    <div class="actions">
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

// ── Category tabs (only 4 visible — rest moved to header 3-dot panel) ──
$visibleCats = array_slice($categories, 0, 4);
$hiddenCats  = array_slice($categories, 4);  // ★ used in header 3-dot panel below

$newHTML .= '<div class="cat-tabs">';
$newHTML .= '<a href="?page=home" class="cat-tab home-tab' . ($page === 'home' && !$currentCat && !$searchQuery ? ' active' : '') . '"><i class="fas fa-home ct-icon"></i>' . $L['home'] . '</a>';
foreach ($visibleCats as $cat) {
    $isActive = ($currentCat === $cat['name']) ? ' active' : '';
    $newHTML .= '<a href="' . htmlspecialchars($cat['href']) . '" class="cat-tab' . $isActive . '"><i class="fas ' . htmlspecialchars($cat['icon']) . ' ct-icon"></i>' . htmlspecialchars($cat['name']) . '</a>';
}
// ★ "More" tab removed — 3-dot button in header handles rest of categories
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

// Side featured items (only on home)
if ($page === 'home' && !empty($sideItems)) {
    $newHTML .= '<div class="side-list">';
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

// Popular news (horizontal scroll, home only)
if ($page === 'home' && !empty($popularPosts)) {
    $newHTML .= <<<HTML
<div class="popular-section">
    <div class="section-head">
        <div class="sh-title">{$L['popular']}</div>
    </div>
    <div class="popular-scroll">
HTML;
    foreach ($popularPosts as $pp) {
        $newHTML .= <<<HTML
<a href="{$pp['href']}" class="pop-card">
    <img src="{$pp['img']}" alt="" class="pc-img" loading="lazy">
    <div class="pc-body">
        <div class="pc-title">{$pp['title']}</div>
        <div class="pc-time"><i class="far fa-clock"></i>{$pp['time']}</div>
    </div>
</a>
HTML;
    }
    $newHTML .= '</div></div>';
}

// Popular tags (home only)
if ($page === 'home' && !empty($popularTags)) {
    $newHTML .= <<<HTML
<div class="section-head">
    <div class="sh-title">{$L['tags']}</div>
</div>
<div class="tags-wrap">
HTML;
    foreach ($popularTags as $pt) {
        $newHTML .= '<a href="' . htmlspecialchars($pt['href']) . '" class="tag-pill"><i class="fas fa-hashtag"></i>' . htmlspecialchars($pt['name']) . '</a>';
    }
    $newHTML .= '</div>';
}

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
</footer>
HTML;

// ── Floating sidebar button ──────────────────────────────────────
$newHTML .= '<button class="fab-sidebar" id="fabSidebar" aria-label="Menu"><i class="fas fa-bars"></i></button>';

// ── Sidebar (slide-in) ───────────────────────────────────────────
$newHTML .= '<div class="app-sidebar" id="appSidebar">';
$newHTML .= '<div class="sb-header"><h3>' . $L['appName'] . '</h3><button class="sb-close" id="sbClose" aria-label="Close">&times;</button></div>';
// Categories
$newHTML .= '<div class="sb-section"><h4>' . $L['allCats'] . '</h4>';
foreach ($categories as $cat) {
    $isActive = ($currentCat === $cat['name']) ? ' style="background:rgba(183,28,28,.1);color:var(--red)"' : '';
    $newHTML .= '<a href="' . htmlspecialchars($cat['href']) . '" class="sb-cat-item"' . $isActive . '><i class="fas ' . htmlspecialchars($cat['icon']) . '"></i>' . htmlspecialchars($cat['name']) . '</a>';
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

    // Sidebar toggle
    var fabSidebar = document.getElementById('fabSidebar');
    var menuBtn = document.getElementById('menuBtn');
    var appSidebar = document.getElementById('appSidebar');
    var sbClose = document.getElementById('sbClose');

    function openSidebar() {
        if (appSidebar) appSidebar.classList.add('open');
        document.body.classList.add('sidebar-open');
        var catPanel = document.getElementById('catPanel');
        if (catPanel) catPanel.classList.remove('open');
    }
    function closeSidebar() {
        if (appSidebar) appSidebar.classList.remove('open');
        document.body.classList.remove('sidebar-open');
    }
    if (fabSidebar) fabSidebar.addEventListener('click', openSidebar);
    if (menuBtn) menuBtn.addEventListener('click', openSidebar);
    if (sbClose) sbClose.addEventListener('click', closeSidebar);
    document.addEventListener('click', function(e) {
        if (!document.body.classList.contains('sidebar-open')) return;
        if (appSidebar && appSidebar.contains(e.target)) return;
        if (e.target === fabSidebar || (fabSidebar && fabSidebar.contains(e.target))) return;
        if (e.target === menuBtn || (menuBtn && menuBtn.contains(e.target))) return;
        closeSidebar();
    });

    // ★ 3-dot panel removed (v2.4) — sidebar handles all categories

    // Notification button — for now just shows last breaking news
    var notifBtn = document.getElementById('notifBtn');
    var notifBadge = document.getElementById('notifBadge');
    if (notifBtn && notifBadge) {
        var breakingCount = document.querySelectorAll('.breaking-bar .track span').length / 2;
        if (breakingCount > 0) {
            notifBadge.textContent = breakingCount;
            notifBadge.style.display = 'inline-block';
        }
        notifBtn.addEventListener('click', function() {
            var bar = document.querySelector('.breaking-bar');
            if (bar) {
                bar.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
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
