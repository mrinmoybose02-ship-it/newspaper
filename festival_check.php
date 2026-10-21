<?php
// ============================================================
// দুর্গাপুজো লোগো ডায়াগনস্টিক টুল (v5 — ফাজি ম্যাচিং + সেপারেটর নরমালাইজেশন)
// ব্যবহার: https://আপনার-সাইট.com/festival_check.php খুলুন
// ⚠ কাজ শেষ হলে এই ফাইলটি অবশ্যই ডিলিট করবেন!
// ============================================================
date_default_timezone_set('Asia/Kolkata');
header('Content-Type: text/html; charset=UTF-8');
$today = date('Y-m-d');

// সব সম্ভাব্য লোকেশনে "festivals" ফোল্ডার খোঁজা হয়
$candidates = ['uploads/festivals', 'festivals', 'uploads/festival', 'festival'];
$detected = '';
foreach ($candidates as $c) { if (is_dir(__DIR__ . '/' . $c)) { $detected = $c; break; } }

// সমর্থিত ছবির এক্সটেনশন
$exts = ['png', 'jpg', 'jpeg', 'webp', 'gif'];

// লোগোর ভিত্তি নাম (এক্সটেনশন ছাড়া)
$logos = ['durgapujo', 'maha_shashthi', 'maha_saptami', 'maha_ashtami', 'maha_navami', 'bijoya_dashami'];
$other_logos = [
    'saraswati', 'dol_yatra', 'dol_purnima', 'buddha_purnima', 'rath_yatra',
    'janmashtami', 'mahalaya', 'durga_ashtami', 'durga_nabami', 'durga_puja',
    'lakshmi_puja', 'kali_puja', 'diwali', 'bhai_phonta', 'new_year', 'netaji',
    'republic_day', 'pohela_falgun', 'chaitra_sankranti', 'pohela_boishakh',
    'rabindra_jayanti', 'independence_day', 'christmas', 'new_year_eve'
];

// বানান ভ্যারিয়েশন — বাংলা ট্রান্সলিটারেশনের জন্য
$spellVariants = [
    'ashtami' => ['ashtami', 'astami', 'ashthami', 'asthami', 'ashtomi'],
    'nabami'  => ['nabami', 'navami', 'nobami', 'nabomi', 'navmi'],
    'saptami' => ['saptami', 'saptmi', 'shaptami'],
    'shashthi'=> ['shashthi', 'shashti', 'sashthi', 'shashthi', 'shoshti'],
    'navami'  => ['navami', 'nabami', 'nobami'],
    'dashami' => ['dashami', 'dashomi', 'dasami', 'dussehra'],
    'durgapujo'=> ['durgapujo', 'durgapuja', 'durga_pujo', 'durga_puja', 'durgapoojo', 'durgapooja'],
    'bijoya'  => ['bijoya', 'bijoya_dashami', 'bijaya', 'bijoya_dashomi'],
];

// ---- নরমালাইজেশন ফাংশন ----
// সেপারেটর সব _ - space কে একটাতে রূপান্তর করি, তারপর lowercase
$normalize = function ($s) {
    // সব সেপারেটরকে underscore করি
    $s = str_replace(['-', ' ', '.', '__'], '_', $s);
    $s = preg_replace('/_+/', '_', $s); // multiple underscore → single
    return strtolower(trim($s, '_'));
};

// বেস নাম থেকে সব সম্ভাব্য ভ্যারিয়েন্ট তৈরি করি
$expandVariants = function ($base) use ($spellVariants, $normalize) {
    $variants = [$normalize($base)];
    // যদি বানান ভ্যারিয়েন্ট থাকে, সবগুলো যোগ করি
    foreach ($spellVariants as $key => $vals) {
        if (strpos($base, $key) !== false) {
            foreach ($vals as $v) {
                $variants[] = $normalize(str_replace($key, $v, $base));
            }
        }
    }
    return array_values(array_unique($variants));
};

// ---- সব ক্যান্ডিডেট ফোল্ডার স্ক্যান করে একটি লুকআপ টেবিল তৈরি ----
$allFiles = [];        // normalized_key => [path, actual, folder]
$allFilesList = [];    // প্রদর্শনের জন্য সব ফাইলের তালিকা
foreach ($candidates as $c) {
    $dir = __DIR__ . '/' . $c;
    if (!is_dir($dir)) continue;
    foreach (scandir($dir) as $x) {
        if ($x === '.' || $x === '..') continue;
        if (is_dir($dir . '/' . $x)) {
            // সাব-ফোল্ডার স্ক্যান
            foreach (scandir($dir . '/' . $x) as $y) {
                if ($y === '.' || $y === '..') continue;
                $norm = $normalize($y);
                $path = $c . '/' . $x . '/' . $y;
                if (!isset($allFiles[$norm])) {
                    $allFiles[$norm] = ['path' => $path, 'actual' => $y, 'folder' => $c . '/' . $x];
                }
                $allFilesList[] = ['path' => $path, 'actual' => $y, 'folder' => $c . '/' . $x];
            }
            continue;
        }
        $norm = $normalize($x);
        $path = $c . '/' . $x;
        if (!isset($allFiles[$norm])) {
            $allFiles[$norm] = ['path' => $path, 'actual' => $x, 'folder' => $c];
        }
        $allFilesList[] = ['path' => $path, 'actual' => $x, 'folder' => $c];
    }
}

// একটি লোগো খোঁজা — নরমালাইজড + ভ্যারিয়েন্ট ম্যাচিং
$festResolve = function ($base) use ($allFiles, $exts, $expandVariants) {
    $variants = $expandVariants($base);
    foreach ($variants as $v) {
        foreach ($exts as $ext) {
            $key = $v . '_' . $ext;
            if (isset($allFiles[$key])) return $allFiles[$key]['path'];
        }
        // এক্সটেনশন ছাড়াও চেক করি (ভ্যারিয়েন্ট নামটি হুবহু ফাইলের নামের অংশ হলে)
        foreach ($exts as $ext) {
            foreach ($allFiles as $normName => $info) {
                // এক্সটেনশন বাদ দিয়ে নাম মেলাই
                $nameWithoutExt = preg_replace('/_' . preg_quote($ext, '/') . '$/', '', $normName);
                if ($nameWithoutExt === $v) return $info['path'];
            }
        }
    }
    return '';
};

// ফাজি ম্যাচ — similar_text দিয়ে কাছের ফাইল খুঁজি
$suggestFuzzy = function ($base) use ($allFilesList, $exts) {
    $baseLower = strtolower($base);
    $best = null;
    $bestScore = 0;
    foreach ($allFilesList as $info) {
        $actual = $info['actual'];
        $actualLower = strtolower($actual);
        // এক্সটেনশন বাদ
        $nameOnly = $actualLower;
        foreach ($exts as $ext) {
            $nameOnly = preg_replace('/\.' . preg_quote($ext, '/') . '$/i', '', $nameOnly);
        }
        // কেস ১: একটি অন্যটির সাবস্ট্রিং হলে
        if (strpos($nameOnly, $baseLower) !== false || strpos($baseLower, $nameOnly) !== false) {
            similar_text($nameOnly, $baseLower, $pct);
            if ($pct > $bestScore) {
                $bestScore = $pct;
                $best = $info;
            }
            continue;
        }
        // কেস ২: similar_text স্কোর 55%+
        similar_text($nameOnly, $baseLower, $pct);
        if ($pct >= 55 && $pct > $bestScore) {
            $bestScore = $pct;
            $best = $info;
        }
    }
    return $best ? ['info' => $best, 'score' => round($bestScore)] : null;
};
?>
<!DOCTYPE html>
<html lang="bn">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Festival Logo Check v5</title>
<style>
body{font-family:'Hind Siliguri',sans-serif;max-width:920px;margin:30px auto;padding:0 16px;color:#1a1a1a;line-height:1.55}
.row{padding:10px 14px;margin:8px 0;border-radius:6px}
.ok{background:#e8f5e9}
.miss{background:#ffebee}
.opt{background:#fff8e1}
pre{background:#f4f1eb;padding:10px;border-radius:4px;overflow-x:auto;font-size:13px;max-height:240px}
.suggest{background:#fff3e0;padding:8px 12px;border-left:3px solid #FF9800;margin-top:8px;font-size:14px;border-radius:0 4px 4px 0}
img{max-width:180px;max-height:120px;border:1px solid #ccc;margin:6px 8px 6px 0;vertical-align:middle;border-radius:4px}
img.small{max-width:120px;max-height:80px}
code{background:#f0f0f0;padding:2px 6px;border-radius:3px;font-family:'Consolas',monospace;font-size:13px}
small{color:#555}
.badge{display:inline-block;padding:2px 8px;border-radius:10px;font-size:12px;font-weight:bold;margin-left:6px}
.badge.high{background:#c8e6c9;color:#1b5e20}
.badge.mid{background:#ffe0b2;color:#e65100}
</style>
</head>
<body>
<h2 style="color:#B71C1C">এমব্লেম লোগো ডায়াগনস্টিক (v5 — ফাজি ম্যাচিং)</h2>
<p>
<b>আজকের তারিখ:</b> <?= $today ?> (Asia/Kolkata)<br>
<b>PHP ভার্সন:</b> <?= PHP_VERSION ?><br>
<b>index.php-এর ফোল্ডার:</b> <?= htmlspecialchars(__DIR__) ?><br>
<b>ফোল্ডারে মোট ফাইল:</b> <?= count($allFilesList) ?>টি
</p>

<?php if ($detected !== '') { ?>
    <div class="row ok">✅ <b>ফোল্ডার পাওয়া গেছে:</b> <code><?= htmlspecialchars($detected) ?></code></div>
<?php } else { ?>
    <div class="row miss">
        ❌ <b>কোথাও "festivals" ফোল্ডার পাওয়া যায়নি!</b><br>
        নিচের যেকোনো একটি জায়গায় ফোল্ডারটি রাখুন:<br>
        ① <code><?= htmlspecialchars(__DIR__) ?>/uploads/festivals/</code><br>
        ② <code><?= htmlspecialchars(__DIR__) ?>/festivals/</code>
    </div>
<?php } ?>

<p style="background:#e3f2fd;padding:10px;border-radius:6px;font-size:14px">
<b>v5 এ কী নতুন:</b> 
① সেপারেটর নরমালাইজেশন — <code>durga-ashtami</code>, <code>durga ashtami</code>, <code>Durga_Ashtami</code> সব একইভাবে মিলবে<br>
② বানান ভ্যারিয়েশন — <code>ashtami/astami/ashthami</code>, <code>nabami/navami/nobami</code> সব চলবে<br>
③ ফাজি ম্যাচিং — 55%+ মিল থাকলে সাজেশন দেবে স্কোরসহ
</p>

<?php
$allOk = true;
echo '<h3>লোগো ফাইল (৬টি — আবশ্যক)</h3>';
foreach ($logos as $base) {
    $p = $festResolve($base);
    $ok = $p !== '';
    if (!$ok) $allOk = false;
    echo '<div class="row ' . ($ok ? 'ok' : 'miss') . '">';
    echo ($ok ? '✅ [OK]' : '❌ [MISSING]') . ' প্রত্যাশিত: <code>festivals/' . $base . '.png</code>';
    if ($ok) {
        echo '<br><img src="' . htmlspecialchars($p) . '?t=' . time() . '" alt="">';
        echo '<br><small>✓ প্রকৃতপক্ষে: <code>' . htmlspecialchars($p) . '</code></small>';
    } else {
        $fuzzy = $suggestFuzzy($base);
        if ($fuzzy) {
            $scoreClass = $fuzzy['score'] >= 75 ? 'high' : 'mid';
            echo '<div class="suggest">';
            echo '💡 <b>সাজেশন:</b> এই ফাইলটি সম্ভবত মিলে যায়: <code>' . htmlspecialchars($fuzzy['info']['path']) . '</code>';
            echo '<span class="badge ' . $scoreClass . '">' . $fuzzy['score'] . '% মিল</span><br>';
            echo '<small><b>ফিক্স করার দুটি উপায়:</b><br>';
            echo '&nbsp;&nbsp;① ফাইলের নাম পরিবর্তন করে <code>' . $base . '.png</code> করুন, অথবা<br>';
            echo '&nbsp;&nbsp;② <code>index.php</code> / <code>bengali_calendar.php</code>-তে লোগো নাম হিসেবে <code>' . htmlspecialchars($fuzzy['info']['actual']) . '</code> ব্যবহার করুন</small>';
            echo '</div>';
        } else {
            echo '<div class="suggest">⚠ কোনো কাছাকাছি মিল পাওয়া যায়নি। নিচের "ফোল্ডারে থাকা সব ফাইল" লিস্ট দেখুন।</div>';
        }
    }
    echo '</div>';
}

echo '<h3>অন্যান্য উৎসবের লোগো (২৪টি — ঐচ্ছিক)</h3>';
foreach ($other_logos as $base) {
    $p = $festResolve($base);
    $ok = $p !== '';
    echo '<div class="row ' . ($ok ? 'ok' : 'opt') . '">';
    echo ($ok ? '✅ [OK]' : '⚠ [ঐচ্ছিক — নেই]') . ' প্রত্যাশিত: <code>festivals/' . $base . '.png</code>';
    if ($ok) {
        echo '<br><img class="small" src="' . htmlspecialchars($p) . '?t=' . time() . '" alt="">';
        echo '<small>✓ পাওয়া গেছে: <code>' . htmlspecialchars($p) . '</code></small>';
    } else {
        $fuzzy = $suggestFuzzy($base);
        if ($fuzzy) {
            $scoreClass = $fuzzy['score'] >= 75 ? 'high' : 'mid';
            echo '<div class="suggest">';
            echo '💡 সম্ভবত মিলে যায়: <code>' . htmlspecialchars($fuzzy['info']['path']) . '</code>';
            echo '<span class="badge ' . $scoreClass . '">' . $fuzzy['score'] . '% মিল</span> ';
            echo '<small>(নাম পরিবর্তন করুন বা index.php-এ আপডেট করুন)</small>';
            echo '</div>';
        }
    }
    echo '</div>';
}

echo '<h3>ফোল্ডার রিপোর্ট</h3>';
foreach ($candidates as $d) {
    $exists = is_dir(__DIR__ . '/' . $d);
    echo '<div class="row" style="background:' . ($exists ? '#e8f5e9' : '#f5f5f5') . '">';
    echo ($exists ? '✓ আছে:' : '— নেই:') . ' ' . htmlspecialchars($d);
    if ($exists) {
        $files = [];
        foreach (scandir(__DIR__ . '/' . $d) as $x) { if ($x !== '.' && $x !== '..') $files[] = $x; }
        if (count($files) > 0) {
            echo '<pre>' . htmlspecialchars(implode("\n", $files)) . '</pre>';
        } else {
            echo '<br><small>⚠ ফোল্ডারটি খালি!</small>';
        }
    }
    echo '</div>';
}

echo '<h3>ফোল্ডারে থাকা সব ফাইল (সম্পূর্ণ তালিকা)</h3>';
if (count($allFilesList) === 0) {
    echo '<div class="row miss">কোনো ফাইল পাওয়া যায়নি!</div>';
} else {
    echo '<div class="row" style="background:#f4f1eb"><pre>';
    foreach ($allFilesList as $info) {
        echo htmlspecialchars($info['path']) . "\n";
    }
    echo '</pre></div>';
}

echo '<h3 style="color:' . ($allOk ? '#1b5e20' : '#B71C1C') . '">' . ($allOk ? '🎉 সব আবশ্যক ফাইল ঠিক আছে! এখন হোমপেজ রিফ্রেশ করুন (Ctrl+F5)' : '⚠ উপরের [MISSING] ফাইলগুলো ঠিক করুন') . '</h3>';
?>
<p style="background:#fff3e0;padding:10px;border-radius:6px"><b>নিরাপত্তা নোট:</b> কাজ শেষ হলে এই <b>festival_check.php</b> ফাইলটি সার্ভার থেকে ডিলিট করে দিন!</p>
</body>
</html>
