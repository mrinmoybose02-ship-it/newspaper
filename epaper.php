<?php
// ============================================================
// epaper.php — ই-পেপার ভিউয়ার + বাংলা ক্যালেন্ডার
// ============================================================
// কী করে:
//   1) epaper/ ফোল্ডারে YYYY-MM-DD.pdf নামে রাখা PDF গুলো স্ক্যান করে
//   2) বাম পাশে PDF ভিউয়ার (iframe), ডান পাশে বাংলা মাস-ক্যালেন্ডার
//   3) ক্যালেন্ডারে যে তারিখে PDF আছে সেগুলো হাইলাইট থাকে;
//      ক্লিক করলে সেই দিনের PDF খুলে যায়।
//
// সেটআপ:
//   1) এই ফাইলটি আপনার সাইট রুটে রাখুন (index.php-এর পাশে)
//   2) epaper/ ফোল্ডার তৈরি করুন এবং সেখানে PDF দিন, যেমন:
//        epaper/2026-10-24.pdf
//        epaper/2026-10-23.pdf
//        epaper/2026-10-22.pdf
//   3) index.php-তে দুটি ছোট পরিবর্তন করুন (README.md দেখুন)
// ============================================================

// ক্যাশিং বন্ধ — নতুন PDF সাথে সাথে দেখাবে
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

date_default_timezone_set('Asia/Kolkata');

// ====== কনফিগারেশন (প্রয়োজনে এখানে পরিবর্তন করুন) ======
$EPAPER_DIR     = __DIR__ . '/epaper';   // সার্ভার পাথ
$EPAPER_URL_BASE = 'epaper';             // URL বেস (relative)
// =================================================

// ====== ঐচ্ছিক: config.php লোড (সাইট টাইটলের জন্য) ======
$siteTitle = 'সংবাদ সংকলন';
$siteLoaded = false;
$configFile = __DIR__ . '/config.php';
if (file_exists($configFile)) {
    try {
        require_once $configFile;
        $siteLoaded = true;
    } catch (Throwable $e) { /* কনফিগ না থাকলেও চলবে */ }
}
// MySQL সেশন টাইমজোন IST-এ সিঙ্ক
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $pdo->exec("SET time_zone = '+05:30';");
    }
} catch (Throwable $e) {}

// সাইট টাইটল ডাটাবেস থেকে আনা
try {
    if (isset($pdo) && $pdo instanceof PDO) {
        $st = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'site_title' LIMIT 1");
        $st->execute();
        $r = $st->fetchColumn();
        if ($r) $siteTitle = $r;
    }
} catch (Throwable $e) {}

// ====== বাংলা সংখ্যা / মাস / দিন ======
$bnDigits = ['০','১','২','৩','৪','৫','৬','৭','৮','৯'];
$enDigits = ['0','1','2','3','4','5','6','7','8','9'];
$bnMonths = [1=>'জানুয়ারি',2=>'ফেব্রুয়ারি',3=>'মার্চ',4=>'এপ্রিল',5=>'মে',6=>'জুন',7=>'জুলাই',8=>'আগস্ট',9=>'সেপ্টেম্বর',10=>'অক্টোবর',11=>'নভেম্বর',12=>'ডিসেম্বর'];
$bnDaysShort = ['Sun'=>'রবি','Mon'=>'সোম','Tue'=>'মঙ্গল','Wed'=>'বুধ','Thu'=>'বৃহ','Fri'=>'শুক্র','Sat'=>'শনি'];

if (!function_exists('toBn')) {
    function toBn($str) {
        global $bnDigits, $enDigits;
        return str_replace($enDigits, $bnDigits, (string)$str);
    }
}
if (!function_exists('epaper_formatFileSize')) {
    function epaper_formatFileSize($bytes) {
        if ($bytes < 1024) return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 2) . ' MB';
    }
}
if (!function_exists('epaper_formatDisplayDate')) {
    function epaper_formatDisplayDate($dateStr) {
        global $bnMonths;
        try {
            $d = new DateTime($dateStr);
            return $d->format('d') . ' ' . $bnMonths[(int)$d->format('m')] . ' ' . $d->format('Y');
        } catch (Exception $e) { return $dateStr; }
    }
}

// ====== URL প্যারামিটার গ্রহণ ======
$selectedDate = isset($_GET['date']) ? trim($_GET['date']) : '';
$calYear  = isset($_GET['y']) ? (int)$_GET['y'] : (int)date('Y');
$calMonth = isset($_GET['m']) ? (int)$_GET['m'] : (int)date('n');

// তারিখ ফরম্যাট যাচাই
if ($selectedDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $selectedDate)) {
    $selectedDate = '';
}
// বছর/মাস যাচাই
if ($calYear < 2000 || $calYear > 2100) $calYear = (int)date('Y');
if ($calMonth < 1 || $calMonth > 12) $calMonth = (int)date('n');

// ====== epaper/ ফোল্ডার স্ক্যান — সব উপলব্ধ PDF তালিকা ======
$availableDates = [];
if (is_dir($EPAPER_DIR)) {
    foreach (glob($EPAPER_DIR . '/*.pdf') as $file) {
        $basename = basename($file, '.pdf');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $basename)) {
            $size = filesize($file);
            $availableDates[$basename] = [
                'file'           => basename($file),
                'path'           => $EPAPER_URL_BASE . '/' . rawurlencode(basename($file)),
                'size'           => $size,
                'size_formatted' => epaper_formatFileSize($size),
                'mtime'          => filemtime($file),
            ];
        }
    }
}
krsort($availableDates); // নতুন তারিখ আগে

// ====== কোন PDF দেখানো হবে তা ঠিক করুন ======
$currentDate = $selectedDate;
$currentPdf = null;
$currentPdfError = '';

if ($currentDate) {
    if (isset($availableDates[$currentDate])) {
        $currentPdf = $availableDates[$currentDate];
    } else {
        $currentPdfError = 'এই তারিখের কোনো ই-পেপার পাওয়া যায়নি — ' . toBn(epaper_formatDisplayDate($currentDate));
    }
} else {
    if (!empty($availableDates)) {
        $currentDate = array_key_first($availableDates);
        $currentPdf  = $availableDates[$currentDate];
    } else {
        $currentPdfError = 'এখনও কোনো ই-পেপার আপলোড করা হয়নি।';
    }
}

// ====== পূর্ব/পরবর্তী দিন নেভিগেশন (উপলব্ধ তারিখ অনুযায়ী) ======
$ascDates = array_keys($availableDates);
sort($ascDates);
$currentIdx = ($currentDate && in_array($currentDate, $ascDates, true)) ? array_search($currentDate, $ascDates, true) : false;
$prevDate = ($currentIdx !== false && $currentIdx > 0) ? $ascDates[$currentIdx - 1] : null;
$nextDate = ($currentIdx !== false && $currentIdx !== false && $currentIdx < count($ascDates) - 1) ? $ascDates[$currentIdx + 1] : null;

// ====== মাস নেভিগেশন ======
$prevMonth = $calMonth - 1; $prevYear = $calYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
$nextMonth = $calMonth + 1; $nextYear = $calYear;
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

$todayStr = date('Y-m-d');

// ====== ক্যালেন্ডার গ্রিড তৈরি ======
function epaper_buildCalendar($year, $month, $availableDates, $selectedDate, $todayStr) {
    global $bnDaysShort, $bnDigits, $enDigits;
    $firstDay = mktime(0, 0, 0, $month, 1, $year);
    $daysInMonth = (int)date('t', $firstDay);
    $startDayOfWeek = (int)date('w', $firstDay); // 0=Sunday

    $cells = [];
    // দিনের হেডার
    foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $day) {
        $cells[] = '<div class="cal-day-header">' . $bnDaysShort[$day] . '</div>';
    }
    // ফাঁকা সেল
    for ($i = 0; $i < $startDayOfWeek; $i++) {
        $cells[] = '<div class="cal-empty"></div>';
    }
    // দিনের সেল
    for ($day = 1; $day <= $daysInMonth; $day++) {
        $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
        $bnDay = str_replace($enDigits, $bnDigits, (string)$day);
        $isAvailable = isset($availableDates[$dateStr]);
        $isSelected  = ($dateStr === $selectedDate);
        $isToday     = ($dateStr === $todayStr);
        $isFuture    = ($dateStr > $todayStr);

        $classes = ['cal-day'];
        if ($isAvailable) $classes[] = 'available';
        if ($isSelected)  $classes[] = 'selected';
        if ($isToday)    $classes[] = 'today';
        if ($isFuture)   $classes[] = 'future';
        $cls = implode(' ', $classes);

        if ($isAvailable) {
            $cells[] = '<a href="?date=' . $dateStr . '" class="' . $cls . '" title="' . htmlspecialchars(toBn(epaper_formatDisplayDate($dateStr))) . '">'
                     . '<span class="day-num">' . $bnDay . '</span>'
                     . '<span class="day-dot"></span>'
                     . '</a>';
        } else {
            $cells[] = '<div class="' . $cls . '"><span class="day-num">' . $bnDay . '</span></div>';
        }
    }
    return implode('', $cells);
}

// SEO
$pageTitle = 'ই-পেপার';
if ($currentDate) $pageTitle = 'ই-পেপার — ' . toBn(epaper_formatDisplayDate($currentDate));
?>
<!DOCTYPE html>
<html lang="bn" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($pageTitle) ?> | <?= htmlspecialchars($siteTitle) ?></title>
<meta name="description" content="ই-পেপার — প্রতিদিনের ডিজিটাল সংস্করণ। ক্যালেন্ডার থেকে যেকোনো তারিখ নির্বাচন করে পড়ুন।">
<meta name="robots" content="index,follow">

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Hind+Siliguri:wght@300;400;500;600;700&family=Noto+Serif+Bengali:wght@400;600;700;900&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{--red:#B71C1C;--red-dark:#7F0000;--gold:#D4950A;--bg:#F4F1EB;--fg:#1A1A1A;--muted:#666;--card:#FFF;--border:#DDD5C8;--nav-bg:#1A1A1A}
*{margin:0;padding:0;box-sizing:border-box}
body{font-family:'Hind Siliguri',sans-serif;background:var(--bg);color:var(--fg);line-height:1.6}
a{color:inherit;text-decoration:none}
img{max-width:100%;display:block}

/* ====== টপ বার ====== */
.ep-topbar{background:var(--red);color:#fff;padding:8px 0;border-bottom:2px solid var(--red-dark)}
.ep-topbar .container{display:flex;align-items:center;justify-content:space-between;max-width:1400px;margin:0 auto;padding:0 16px;gap:16px}
.ep-topbar .back-link{color:#fff;font-size:13px;font-weight:600;display:inline-flex;align-items:center;gap:8px;transition:opacity .2s;padding:4px 0}
.ep-topbar .back-link:hover{opacity:.85}
.ep-topbar .site-name{font-family:'Noto Serif Bengali',serif;font-weight:900;font-size:18px;letter-spacing:1px;text-align:center;flex:1}
.ep-topbar .site-name span{color:var(--gold);margin-left:6px}

.container{max-width:1400px;margin:0 auto;padding:0 16px}

/* ====== হেডার ====== */
.ep-header{background:#fff;border-bottom:3px solid var(--red);padding:24px 0 18px;text-align:center}
.ep-header h1{font-family:'Noto Serif Bengali',serif;font-size:clamp(24px,4vw,40px);font-weight:900;color:var(--red);letter-spacing:2px;display:inline-flex;align-items:center;gap:12px}
.ep-header h1 i{color:var(--gold);font-size:.85em}
.ep-header .subtitle{font-size:12px;color:var(--muted);letter-spacing:3px;text-transform:uppercase;margin-top:4px}

/* ====== মূল লেআউট ====== */
.ep-layout{display:grid;grid-template-columns:1fr 340px;gap:20px;padding:24px 0;align-items:start}

/* ====== PDF কার্ড ====== */
.pdf-card{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04)}
.pdf-card-header{display:flex;align-items:center;justify-content:space-between;padding:12px 18px;background:var(--nav-bg);color:#fff;border-bottom:2px solid var(--red);flex-wrap:wrap;gap:10px}
.pdf-card-title-wrap{display:flex;flex-direction:column;gap:3px;min-width:0;flex:1}
.pdf-card-title{font-family:'Noto Serif Bengali',serif;font-size:17px;font-weight:700;display:flex;align-items:center;gap:10px;line-height:1.3}
.pdf-card-title i{color:var(--gold);font-size:18px;flex-shrink:0}
.pdf-card-date{font-size:12px;color:rgba(255,255,255,.7);display:flex;align-items:center;gap:6px;flex-wrap:wrap}
.pdf-card-date i{opacity:.7}
.pdf-card-actions{display:flex;gap:6px;align-items:center;flex-wrap:wrap;flex-shrink:0}
.pdf-action-btn{display:inline-flex;align-items:center;gap:6px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.18);padding:7px 12px;border-radius:5px;font-size:12px;font-weight:600;cursor:pointer;transition:all .2s;font-family:inherit;text-decoration:none}
.pdf-action-btn:hover:not(:disabled){background:var(--gold);color:#000;border-color:var(--gold)}
.pdf-action-btn:disabled{opacity:.35;cursor:not-allowed}
.pdf-action-btn.download{background:var(--red);border-color:var(--red)}
.pdf-action-btn.download:hover{background:var(--red-dark);color:#fff;border-color:var(--red-dark)}

.pdf-viewer-wrap{position:relative;background:#525659;min-height:600px;display:flex;align-items:center;justify-content:center}
.pdf-viewer-wrap iframe{width:100%;height:82vh;min-height:600px;border:0;display:block;background:#525659}
.pdf-empty{padding:60px 20px;text-align:center;color:#fff;max-width:460px}
.pdf-empty i{font-size:64px;color:rgba(255,255,255,.3);margin-bottom:18px;display:block}
.pdf-empty h3{font-family:'Noto Serif Bengali',serif;font-size:20px;font-weight:700;margin-bottom:10px;color:#fff;line-height:1.4}
.pdf-empty p{font-size:14px;color:rgba(255,255,255,.7);line-height:1.6}

/* ====== ক্যালেন্ডার কার্ড ====== */
.cal-card{background:var(--card);border:1px solid var(--border);border-radius:8px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,.04);position:sticky;top:16px}
.cal-card-header{background:var(--nav-bg);color:#fff;padding:12px 16px;border-bottom:2px solid var(--red);display:flex;align-items:center;justify-content:space-between;gap:8px}
.cal-card-title{font-family:'Noto Serif Bengali',serif;font-size:15px;font-weight:700;display:flex;align-items:center;gap:8px;min-width:0}
.cal-card-title i{color:var(--gold);flex-shrink:0}
.cal-card-title .cm{white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.cal-nav{display:flex;gap:4px;flex-shrink:0}
.cal-nav-btn{width:28px;height:28px;border-radius:4px;background:rgba(255,255,255,.08);color:#fff;border:1px solid rgba(255,255,255,.18);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:all .2s;font-size:11px;text-decoration:none}
.cal-nav-btn:hover{background:var(--gold);color:#000;border-color:var(--gold)}

.cal-grid{padding:12px;display:grid;grid-template-columns:repeat(7,1fr);gap:4px}
.cal-day-header{text-align:center;font-size:11px;font-weight:700;color:var(--muted);padding:6px 0}
.cal-day{aspect-ratio:1;display:flex;flex-direction:column;align-items:center;justify-content:center;border-radius:6px;font-size:13px;font-weight:600;color:var(--muted);background:#f7f4ee;cursor:default;position:relative;transition:all .15s;text-decoration:none;border:1px solid transparent}
.cal-day .day-num{font-family:'Hind Siliguri',sans-serif;line-height:1}
.cal-day .day-dot{display:none;width:5px;height:5px;background:var(--red);border-radius:50%;margin-top:3px}
.cal-day.available{background:#fff;border-color:var(--border);color:var(--fg);cursor:pointer}
.cal-day.available .day-dot{display:block}
.cal-day.available:hover{background:var(--red);color:#fff;border-color:var(--red);transform:translateY(-2px);box-shadow:0 4px 12px rgba(183,28,28,.25)}
.cal-day.available:hover .day-dot{background:#fff}
.cal-day.selected{background:var(--red);color:#fff;border-color:var(--red)}
.cal-day.selected .day-dot{background:#fff}
.cal-day.today{box-shadow:inset 0 0 0 2px var(--gold)}
.cal-day.today.selected{box-shadow:inset 0 0 0 2px var(--gold)}
.cal-day.future{opacity:.4}

.cal-legend{padding:10px 16px 14px;border-top:1px solid var(--border);background:#fafaf7;font-size:11px;color:var(--muted);display:flex;flex-wrap:wrap;gap:12px}
.cal-legend-item{display:inline-flex;align-items:center;gap:5px}
.cal-legend-dot{width:11px;height:11px;border-radius:3px;display:inline-block;border:1px solid var(--border);background:#fff}
.cal-legend-dot.sel{background:var(--red);border-color:var(--red)}
.cal-legend-dot.tod{box-shadow:inset 0 0 0 2px var(--gold);background:#fff}

/* ====== সাম্প্রতিক ই-পেপার তালিকা ====== */
.recent-list{padding:6px 0;max-height:240px;overflow-y:auto;border-top:1px solid var(--border);scrollbar-width:thin}
.recent-list::-webkit-scrollbar{width:5px}
.recent-list::-webkit-scrollbar-thumb{background:#ccc;border-radius:3px}
.recent-list-title{padding:8px 16px 4px;font-size:10px;font-weight:700;color:var(--muted);text-transform:uppercase;letter-spacing:.5px;display:flex;align-items:center;gap:6px}
.recent-list-title i{color:var(--red);font-size:11px}
.recent-item{display:flex;align-items:center;gap:10px;padding:7px 16px;font-size:12px;transition:background .15s;cursor:pointer;text-decoration:none;color:inherit}
.recent-item:hover{background:rgba(183,28,28,.05)}
.recent-item.active{background:rgba(183,28,28,.08);color:var(--red);font-weight:700}
.recent-item i{font-size:11px;opacity:.6;color:var(--red)}
.recent-item.active i{opacity:1}
.recent-item .ri-name{flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.recent-item .ri-size{font-size:10px;color:var(--muted);flex-shrink:0}
.recent-item.active .ri-size{color:var(--red);opacity:.8}

/* ====== ফুটার ====== */
.ep-footer{background:var(--nav-bg);color:#888;padding:16px 0;margin-top:30px;border-top:3px solid var(--red);text-align:center;font-size:12px}
.ep-footer a{color:var(--gold)}
.ep-footer a:hover{color:#fff}

/* ====== রেসপন্সিভ ====== */
@media(max-width:1024px){
    .ep-layout{grid-template-columns:1fr}
    .cal-card{position:static}
    .pdf-viewer-wrap iframe{height:60vh;min-height:420px}
}
@media(max-width:640px){
    .ep-topbar .container{flex-wrap:wrap;gap:6px}
    .ep-topbar .site-name{font-size:14px;order:-1;width:100%}
    .ep-topbar .back-link{font-size:11px;padding:2px 0}
    .pdf-card-header{padding:10px 12px}
    .pdf-card-title{font-size:14px}
    .pdf-card-date{font-size:11px;width:100%}
    .pdf-card-actions{width:100%;justify-content:space-between}
    .pdf-action-btn{padding:6px 10px;font-size:11px;flex:1;justify-content:center}
    .pdf-viewer-wrap iframe{height:50vh;min-height:340px}
    .cal-grid{padding:8px;gap:2px}
    .cal-day{font-size:11px}
    .cal-day-header{font-size:10px}
    .ep-header h1{font-size:24px;letter-spacing:1px;gap:8px}
    .ep-header .subtitle{letter-spacing:1.5px;font-size:10px}
}
</style>
</head>
<body>

<!-- ====== টপ বার ====== -->
<div class="ep-topbar">
<div class="container">
<a href="index.php" class="back-link"><i class="fas fa-arrow-left"></i> ওয়েবসাইটে ফিরুন</a>
<div class="site-name"><?= htmlspecialchars($siteTitle) ?> <span>ই-পেপার</span></div>
<a href="?y=<?= (int)date('Y') ?>&m=<?= (int)date('n') ?>" class="back-link"><i class="far fa-calendar-alt"></i> আজকের মাস</a>
</div>
</div>

<!-- ====== হেডার ====== -->
<header class="ep-header">
<div class="container">
<h1><i class="far fa-newspaper"></i> ই-পেপার</h1>
<div class="subtitle">Daily Digital Edition</div>
</div>
</header>

<!-- ====== মূল লেআউট ====== -->
<div class="container">
<div class="ep-layout">

<!-- বাম: PDF ভিউয়ার -->
<section class="pdf-card">
<div class="pdf-card-header">
<div class="pdf-card-title-wrap">
<div class="pdf-card-title">
<i class="far fa-file-pdf"></i>
<?php if ($currentPdf): ?>
<span><?= htmlspecialchars(toBn(epaper_formatDisplayDate($currentDate))) ?></span>
<?php else: ?>
<span>ই-পেপার</span>
<?php endif; ?>
</div>
<?php if ($currentPdf): ?>
<div class="pdf-card-date">
<span><i class="far fa-calendar"></i> <?= htmlspecialchars(toBn($currentDate)) ?></span>
<span><i class="fas fa-weight-hanging"></i> <?= htmlspecialchars($currentPdf['size_formatted']) ?></span>
</div>
<?php endif; ?>
</div>

<div class="pdf-card-actions">
<a class="pdf-action-btn" href="<?= $prevDate ? '?date=' . $prevDate : '#' ?>" <?= $prevDate ? '' : 'onclick="return false" aria-disabled="true"' ?>>
<i class="fas fa-chevron-left"></i> পূর্ববর্তী
</a>
<a class="pdf-action-btn" href="<?= $nextDate ? '?date=' . $nextDate : '#' ?>" <?= $nextDate ? '' : 'onclick="return false" aria-disabled="true"' ?>>
পরবর্তী <i class="fas fa-chevron-right"></i>
</a>
<?php if ($currentPdf): ?>
<a class="pdf-action-btn download" href="<?= htmlspecialchars($currentPdf['path']) ?>" download>
<i class="fas fa-download"></i> ডাউনলোড
</a>
<?php endif; ?>
</div>
</div>

<div class="pdf-viewer-wrap">
<?php if ($currentPdf): ?>
<iframe src="<?= htmlspecialchars($currentPdf['path']) ?>#toolbar=1&view=FitH&zoom=page-width" title="ePaper <?= htmlspecialchars($currentDate) ?>"></iframe>
<?php else: ?>
<div class="pdf-empty">
<i class="far fa-newspaper"></i>
<h3><?= htmlspecialchars($currentPdfError ?: 'কোনো ই-পেপার পাওয়া যায়নি') ?></h3>
<p>অনুগ্রহ করে ক্যালেন্ডার থেকে অন্য তারিখ নির্বাচন করুন অথবা নতুন ই-পেপার আপলোড হওয়া পর্যন্ত অপেক্ষা করুন।</p>
</div>
<?php endif; ?>
</div>
</section>

<!-- ডান: ক্যালেন্ডার -->
<aside class="cal-card">
<div class="cal-card-header">
<div class="cal-card-title">
<i class="far fa-calendar-alt"></i>
<span class="cm"><?= htmlspecialchars(toBn($bnMonths[$calMonth])) ?> <?= htmlspecialchars(toBn((string)$calYear)) ?></span>
</div>
<div class="cal-nav">
<a href="?y=<?= $prevYear ?>&m=<?= $prevMonth ?><?= $selectedDate ? '&date=' . $selectedDate : '' ?>" class="cal-nav-btn" title="পূর্ববর্তী মাস" aria-label="পূর্ববর্তী মাস"><i class="fas fa-chevron-left"></i></a>
<a href="?y=<?= $nextYear ?>&m=<?= $nextMonth ?><?= $selectedDate ? '&date=' . $selectedDate : '' ?>" class="cal-nav-btn" title="পরবর্তী মাস" aria-label="পরবর্তী মাস"><i class="fas fa-chevron-right"></i></a>
</div>
</div>

<div class="cal-grid">
<?= epaper_buildCalendar($calYear, $calMonth, $availableDates, $currentDate, $todayStr) ?>
</div>

<div class="cal-legend">
<span class="cal-legend-item"><span class="cal-legend-dot"></span> ই-পেপার আছে</span>
<span class="cal-legend-item"><span class="cal-legend-dot sel"></span> নির্বাচিত</span>
<span class="cal-legend-item"><span class="cal-legend-dot tod"></span> আজ</span>
</div>

<?php if (!empty($availableDates)): ?>
<div class="recent-list">
<div class="recent-list-title"><i class="fas fa-history"></i> সাম্প্রতিক ই-পেপার</div>
<?php $i = 0; foreach ($availableDates as $date => $info): if ($i++ >= 12) break; ?>
<a href="?date=<?= $date ?>" class="recent-item <?= ($date === $currentDate) ? 'active' : '' ?>">
<i class="far fa-file-pdf"></i>
<span class="ri-name"><?= htmlspecialchars(toBn(epaper_formatDisplayDate($date))) ?></span>
<span class="ri-size"><?= htmlspecialchars($info['size_formatted']) ?></span>
</a>
<?php endforeach; ?>
</div>
<?php endif; ?>
</aside>

</div>
</div>

<!-- ====== ফুটার ====== -->
<footer class="ep-footer">
<div class="container">
&copy; <?= toBn(date('Y')) ?> <a href="index.php"><?= htmlspecialchars($siteTitle) ?></a>। সর্বস্বত্ব সংরক্ষিত।
</div>
</footer>

<script>
// কীবোর্ড শর্টকাট — বাম/ডান তীর চাপলে পূর্ব/পরবর্তী ই-পেপার
(function(){
    var prevUrl = <?= json_encode($prevDate ? '?date=' . $prevDate : null) ?>;
    var nextUrl = <?= json_encode($nextDate ? '?date=' . $nextDate : null) ?>;
    document.addEventListener('keydown', function(e){
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
        if (e.altKey || e.ctrlKey || e.metaKey) return;
        if (e.key === 'ArrowLeft' && prevUrl) window.location.href = prevUrl;
        if (e.key === 'ArrowRight' && nextUrl) window.location.href = nextUrl;
    });
})();
</script>

</body>
</html>
